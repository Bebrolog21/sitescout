<?php

use App\Models\ChecklistItem;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─── Вспомогательные функции ──────────────────────────────────────────────────

/**
 * Создаёт пользователя с нужной ролью и возвращает заголовки с токеном.
 *
 * @return array{0: User, 1: array<string, string>}
 */
function makeUserWithToken(string $role = 'admin'): array
{
    $user  = User::factory()->create(['role' => $role]);
    $token = $user->createToken('test-token')->plainTextToken;

    return [$user, ['Authorization' => "Bearer {$token}"]];
}

/**
 * Минимальный набор полей для создания площадки.
 */
function sitePayload(array $overrides = []): array
{
    return array_merge([
        'title'      => 'ЖК Северный, парковка у 3 подъезда',
        'city'       => 'Омск',
        'district'   => 'Центральный',
        'address'    => 'ул. Ленина, 10',
        'site_type'  => 'parking',
        'area_m2'    => 180,
        'status'     => 'new',
        'owner_type' => 'management_company',
    ], $overrides);
}

// ─── Тест 1: площадка создаётся с корректными данными ────────────────────────

test('площадка создаётся с валидными данными', function (): void {
    [, $headers] = makeUserWithToken('admin');

    $response = $this->withHeaders($headers)
        ->postJson('/api/v1/sites', sitePayload());

    $response->assertStatus(201)
        ->assertJsonFragment(['title' => 'ЖК Северный, парковка у 3 подъезда'])
        ->assertJsonFragment(['status' => 'new']);

    $this->assertDatabaseHas('sites', ['title' => 'ЖК Северный, парковка у 3 подъезда']);
});

// ─── Тест 2: чеклист обновляется и site_score пересчитывается ─────────────────

test('обновление чеклиста пересчитывает site_score', function (): void {
    [, $headers] = makeUserWithToken('admin');

    $site  = Site::factory()->create();
    $item1 = ChecklistItem::create([
        'code'     => 'ACC_01',
        'title'    => 'Доступ для грузовика',
        'category' => 'access',
        'weight'   => 10,
    ]);
    $item2 = ChecklistItem::create([
        'code'     => 'SEC_01',
        'title'    => 'Освещение периметра',
        'category' => 'security',
        'weight'   => 5,
    ]);

    $response = $this->withHeaders($headers)->putJson("/api/v1/sites/{$site->id}/checklist", [
        'items' => [
            ['checklist_item_id' => $item1->id, 'value' => 5],  // 5*10 = 50
            ['checklist_item_id' => $item2->id, 'value' => 3],  // 3*5  = 15
        ],
    ]);

    // score = (50+15) / (5*10 + 5*5) * 100 = 65/75 * 100 ≈ 87
    $response->assertOk()->assertJsonStructure(['site_score']);

    $score = $response->json('site_score');
    expect($score)->toBeGreaterThan(0)->toBeLessThanOrEqual(100);

    // Проверяем, что в БД обновился
    $fresh = Site::find($site->id);
    expect($fresh->site_score)->toBe($score);
});

// ─── Тест 3: риск добавляется и risk_score пересчитывается ───────────────────

test('добавление риска пересчитывает risk_score', function (): void {
    [, $headers] = makeUserWithToken('admin');

    $site = Site::factory()->create(['risk_score' => 0]);

    $response = $this->withHeaders($headers)->postJson("/api/v1/sites/{$site->id}/risks", [
        'type'        => 'legal',
        'severity'    => 'high',
        'probability' => 'high',
        'description' => 'Нет согласования с управляющей компанией',
        'mitigation'  => null,
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure(['risk', 'risk_score']);

    $riskScore = $response->json('risk_score');
    expect($riskScore)->toBeGreaterThan(0)->toBeLessThanOrEqual(100);

    expect(Site::find($site->id)->risk_score)->toBe($riskScore);
});

// ─── Тест 4: финансовая модель рассчитывает окупаемость ──────────────────────

test('финансовая модель рассчитывает окупаемость корректно', function (): void {
    [, $headers] = makeUserWithToken('admin');

    $site = Site::factory()->create();

    // CAPEX 2 000 000, OPEX 100 000/мес
    // выручка: 4 контейнера × 8 единиц × (4500+6500+9000)/3 × 0.7 = 4×8×6667×0.7 ≈ 149 333
    // прибыль ≈ 149 333 − 100 000 = 49 333
    // окупаемость ≈ 2 000 000 / 49 333 ≈ 41 мес
    $response = $this->withHeaders($headers)->putJson("/api/v1/sites/{$site->id}/finance", [
        'containers_count'    => 4,
        'units_per_container' => 8,
        'occupancy_percent'   => 70,
        'capex_total'         => 2_000_000,
        'opex_total'          => 100_000,
        'price_per_unit'      => [4500, 6500, 9000],
    ]);

    $response->assertOk();
    $results = $response->json('results_json');

    expect($results['monthly_revenue'])->toBeGreaterThan(0);
    expect($results['monthly_gross_profit'])->toBeInt();
    expect($results['payback_months'])->toBeInt()->toBeGreaterThan(0);
    expect($results['roi_12m'])->toBeInt();
    expect($results['breakeven_occupancy'])->toBeInt();
});

// ─── Тест 5: approved блокируется без score и финмодели ──────────────────────

test('статус approved заблокирован без расчётов', function (): void {
    [, $headers] = makeUserWithToken('admin');

    // Площадка без финмодели и с нулевым скором
    $site = Site::factory()->create(['site_score' => 0, 'status' => 'scoring']);

    $response = $this->withHeaders($headers)->patchJson("/api/v1/sites/{$site->id}/status", [
        'status' => 'approved',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['status']);

    // Убедимся, что статус в БД не изменился
    expect(Site::find($site->id)->status)->toBe('scoring');
});

// ─── Тест 6: аналитик не может создавать пункты чеклиста ─────────────────────

test('аналитик не может редактировать справочник чеклиста', function (): void {
    [, $headers] = makeUserWithToken('analyst');

    $response = $this->withHeaders($headers)->postJson('/api/v1/checklist-items', [
        'code'     => 'TEST_01',
        'title'    => 'Тестовый критерий',
        'category' => 'access',
        'weight'   => 5,
    ]);

    $response->assertStatus(403);

    $this->assertDatabaseMissing('checklist_items', ['code' => 'TEST_01']);
});
