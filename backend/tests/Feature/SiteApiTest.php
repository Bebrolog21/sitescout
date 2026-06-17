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

    // Площадка в «Согласование» без финмодели и с нулевым скором.
    // negotiation → approved — валидное ребро, поэтому отказ именно из-за гейтов.
    $site = Site::factory()->create(['site_score' => 0, 'status' => 'negotiation']);

    $response = $this->withHeaders($headers)->patchJson("/api/v1/sites/{$site->id}/status", [
        'status' => 'approved',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['status']);

    // Убедимся, что статус в БД не изменился
    expect(Site::find($site->id)->status)->toBe('negotiation');
});

// ─── Тесты воронки статусов ──────────────────────────────────────────────────

test('forward-переход new → screening разрешён', function (): void {
    [, $headers] = makeUserWithToken('manager');

    $site = Site::factory()->create(['status' => 'new']);

    $this->withHeaders($headers)
        ->patchJson("/api/v1/sites/{$site->id}/status", ['status' => 'screening'])
        ->assertOk()
        ->assertJsonFragment(['status' => 'screening']);

    expect(Site::find($site->id)->status)->toBe('screening');
});

test('new → approved заблокирован без заполненных данных', function (): void {
    [, $headers] = makeUserWithToken('manager');

    $site = Site::factory()->create(['status' => 'new', 'site_score' => 0]);

    $this->withHeaders($headers)
        ->patchJson("/api/v1/sites/{$site->id}/status", ['status' => 'approved'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['status']);

    expect(Site::find($site->id)->status)->toBe('new');
});

test('можно сразу согласовать при заполненных данных', function (): void {
    [, $headers] = makeUserWithToken('admin');

    // Балл рассчитан, критических рисков нет — осталось добавить финмодель.
    $site = Site::factory()->create(['status' => 'inspection', 'site_score' => 80]);

    $this->withHeaders($headers)->putJson("/api/v1/sites/{$site->id}/finance", [
        'containers_count'    => 4,
        'units_per_container' => 8,
        'occupancy_percent'   => 70,
        'capex_total'         => 2_000_000,
        'opex_total'          => 100_000,
        'price_per_unit'      => [4500, 6500, 9000],
    ])->assertOk();

    // Прямой переход inspection → approved минуя scoring/negotiation.
    $this->withHeaders($headers)
        ->patchJson("/api/v1/sites/{$site->id}/status", ['status' => 'approved'])
        ->assertOk()
        ->assertJsonFragment(['status' => 'approved']);

    expect(Site::find($site->id)->status)->toBe('approved');
});

test('заполненная площадка прыгает на любой статус, кроме «Запущена»', function (): void {
    [, $headers] = makeUserWithToken('admin');

    // Площадка с данными для согласования (балл + финмодель, нет крит. рисков).
    $site = Site::factory()->create(['status' => 'inspection', 'site_score' => 80]);
    $this->withHeaders($headers)->putJson("/api/v1/sites/{$site->id}/finance", [
        'containers_count'    => 4,
        'units_per_container' => 8,
        'occupancy_percent'   => 70,
        'capex_total'         => 2_000_000,
        'opex_total'          => 100_000,
        'price_per_unit'      => [4500, 6500, 9000],
    ])->assertOk();

    // Прыжок вперёд через этапы — разрешён.
    $this->withHeaders($headers)
        ->patchJson("/api/v1/sites/{$site->id}/status", ['status' => 'negotiation'])
        ->assertOk();
    expect(Site::find($site->id)->status)->toBe('negotiation');

    // А вот «Запущена» напрямую (минуя «Согласована») — нельзя.
    $this->withHeaders($headers)
        ->patchJson("/api/v1/sites/{$site->id}/status", ['status' => 'launched'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['status']);
});

test('inspection → scoring заблокирован без заполненного чек-листа', function (): void {
    [, $headers] = makeUserWithToken('manager');

    $site = Site::factory()->create(['status' => 'inspection', 'site_score' => 0]);

    $this->withHeaders($headers)
        ->patchJson("/api/v1/sites/{$site->id}/status", ['status' => 'scoring'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['status']);

    expect(Site::find($site->id)->status)->toBe('inspection');
});

test('inspection → scoring проходит при рассчитанном балле', function (): void {
    [, $headers] = makeUserWithToken('manager');

    $site = Site::factory()->create(['status' => 'inspection', 'site_score' => 65]);

    $this->withHeaders($headers)
        ->patchJson("/api/v1/sites/{$site->id}/status", ['status' => 'scoring'])
        ->assertOk();

    expect(Site::find($site->id)->status)->toBe('scoring');
});

test('шаг назад scoring → inspection разрешён без условий', function (): void {
    [, $headers] = makeUserWithToken('manager');

    $site = Site::factory()->create(['status' => 'scoring', 'site_score' => 0]);

    $this->withHeaders($headers)
        ->patchJson("/api/v1/sites/{$site->id}/status", ['status' => 'inspection'])
        ->assertOk();

    expect(Site::find($site->id)->status)->toBe('inspection');
});

test('площадку можно отклонить и архивировать с активного этапа', function (): void {
    [, $headers] = makeUserWithToken('manager');

    $rejected = Site::factory()->create(['status' => 'screening']);
    $this->withHeaders($headers)
        ->patchJson("/api/v1/sites/{$rejected->id}/status", ['status' => 'rejected'])
        ->assertOk();
    expect(Site::find($rejected->id)->status)->toBe('rejected');

    $archived = Site::factory()->create(['status' => 'scoring']);
    $this->withHeaders($headers)
        ->patchJson("/api/v1/sites/{$archived->id}/status", ['status' => 'archived'])
        ->assertOk();
    expect(Site::find($archived->id)->status)->toBe('archived');
});

test('happy-path negotiation → approved при выполненных условиях', function (): void {
    [, $headers] = makeUserWithToken('admin');

    $site = Site::factory()->create(['status' => 'negotiation', 'site_score' => 80]);

    // Сохраняем финмодель с положительной прибылью.
    $this->withHeaders($headers)->putJson("/api/v1/sites/{$site->id}/finance", [
        'containers_count'    => 4,
        'units_per_container' => 8,
        'occupancy_percent'   => 70,
        'capex_total'         => 2_000_000,
        'opex_total'          => 100_000,
        'price_per_unit'      => [4500, 6500, 9000],
    ])->assertOk();

    $this->withHeaders($headers)
        ->patchJson("/api/v1/sites/{$site->id}/status", ['status' => 'approved'])
        ->assertOk()
        ->assertJsonFragment(['status' => 'approved']);

    expect(Site::find($site->id)->status)->toBe('approved');
});

test('ответ updateStatus содержит метаданные воронки', function (): void {
    [, $headers] = makeUserWithToken('manager');

    $site = Site::factory()->create(['status' => 'new']);

    $this->withHeaders($headers)
        ->patchJson("/api/v1/sites/{$site->id}/status", ['status' => 'screening'])
        ->assertOk()
        ->assertJsonStructure([
            'workflow' => ['current', 'current_label', 'transitions'],
        ]);
});

test('из «Запущена» нельзя прыгнуть напрямую в произвольный статус', function (): void {
    [, $headers] = makeUserWithToken('manager');

    $site = Site::factory()->create(['status' => 'launched']);

    $this->withHeaders($headers)
        ->patchJson("/api/v1/sites/{$site->id}/status", ['status' => 'screening'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['status']);

    expect(Site::find($site->id)->status)->toBe('launched');
});

test('из «Запущена» через «Согласована» можно попасть на любой статус', function (): void {
    [, $headers] = makeUserWithToken('admin');

    $site = Site::factory()->create([
        'status' => 'launched',
        'site_score' => 80,
        'risk_score' => 10,
    ]);

    // Финмодель нужна для возврата в «Согласована».
    $this->withHeaders($headers)->putJson("/api/v1/sites/{$site->id}/finance", [
        'containers_count'    => 4,
        'units_per_container' => 8,
        'occupancy_percent'   => 70,
        'capex_total'         => 2_000_000,
        'opex_total'          => 100_000,
        'price_per_unit'      => [4500, 6500, 9000],
    ])->assertOk();

    // launched → approved → любой статус
    $this->withHeaders($headers)
        ->patchJson("/api/v1/sites/{$site->id}/status", ['status' => 'approved'])
        ->assertOk();
    $this->withHeaders($headers)
        ->patchJson("/api/v1/sites/{$site->id}/status", ['status' => 'inspection'])
        ->assertOk();

    expect(Site::find($site->id)->status)->toBe('inspection');
});

test('заполненную площадку из «В архиве» можно вернуть в любой статус', function (): void {
    [, $headers] = makeUserWithToken('admin');

    $site = Site::factory()->create(['status' => 'archived', 'site_score' => 80]);
    $this->withHeaders($headers)->putJson("/api/v1/sites/{$site->id}/finance", [
        'containers_count'    => 4,
        'units_per_container' => 8,
        'occupancy_percent'   => 70,
        'capex_total'         => 2_000_000,
        'opex_total'          => 100_000,
        'price_per_unit'      => [4500, 6500, 9000],
    ])->assertOk();

    $this->withHeaders($headers)
        ->patchJson("/api/v1/sites/{$site->id}/status", ['status' => 'negotiation'])
        ->assertOk();

    expect(Site::find($site->id)->status)->toBe('negotiation');
});

test('пустую площадку из терминальных статусов нельзя ставить в произвольный статус', function (): void {
    [, $headers] = makeUserWithToken('manager');

    // Пустой архив → нельзя сразу в «Согласование».
    $archived = Site::factory()->create(['status' => 'archived', 'site_score' => 0]);
    $this->withHeaders($headers)
        ->patchJson("/api/v1/sites/{$archived->id}/status", ['status' => 'negotiation'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['status']);
    expect(Site::find($archived->id)->status)->toBe('archived');

    // Но реактивировать в «Новая» можно.
    $this->withHeaders($headers)
        ->patchJson("/api/v1/sites/{$archived->id}/status", ['status' => 'new'])
        ->assertOk();
    expect(Site::find($archived->id)->status)->toBe('new');
});

test('из «Отклонена» пустую площадку можно реактивировать в «Новая»', function (): void {
    [, $headers] = makeUserWithToken('manager');

    $site = Site::factory()->create(['status' => 'rejected', 'site_score' => 0]);

    // Прямой прыжок в середину воронки — нельзя.
    $this->withHeaders($headers)
        ->patchJson("/api/v1/sites/{$site->id}/status", ['status' => 'screening'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['status']);

    // Реактивация в «Новая» — можно.
    $this->withHeaders($headers)
        ->patchJson("/api/v1/sites/{$site->id}/status", ['status' => 'new'])
        ->assertOk();
    expect(Site::find($site->id)->status)->toBe('new');
});

test('нерекомендованную площадку нельзя запустить', function (): void {
    [, $headers] = makeUserWithToken('manager');

    // approved, но балл ниже порога и без финмодели → не рекомендована.
    $site = Site::factory()->create(['status' => 'approved', 'site_score' => 50]);

    $this->withHeaders($headers)
        ->patchJson("/api/v1/sites/{$site->id}/status", ['status' => 'launched'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['status']);

    expect(Site::find($site->id)->status)->toBe('approved');
});

test('рекомендованную площадку можно запустить', function (): void {
    [, $headers] = makeUserWithToken('admin');

    $site = Site::factory()->create([
        'status' => 'approved',
        'site_score' => 80,
        'risk_score' => 10,
    ]);

    // Финмодель с окупаемостью ≤ 18 мес: CAPEX 500 000 / GP ≈ 49 333 ≈ 11 мес.
    $this->withHeaders($headers)->putJson("/api/v1/sites/{$site->id}/finance", [
        'containers_count'    => 4,
        'units_per_container' => 8,
        'occupancy_percent'   => 70,
        'capex_total'         => 500_000,
        'opex_total'          => 100_000,
        'price_per_unit'      => [4500, 6500, 9000],
    ])->assertOk();

    $this->withHeaders($headers)
        ->patchJson("/api/v1/sites/{$site->id}/status", ['status' => 'launched'])
        ->assertOk();

    expect(Site::find($site->id)->status)->toBe('launched');
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
