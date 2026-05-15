<?php

use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array{0: User, 1: array<string, string>}
 */
function actingAs(string $role): array
{
    $user = User::factory()->create([
        'role' => $role,
        'password_change_required' => false,
    ]);

    return [$user, ['Authorization' => 'Bearer '.$user->createToken('t')->plainTextToken]];
}

// ─── Площадки: создание ───────────────────────────────────────────────────────

test('manager не может создавать площадки', function (): void {
    [, $headers] = actingAs('manager');

    $response = $this->withHeaders($headers)->postJson('/api/v1/sites', [
        'title' => 'Test',
        'city' => 'Омск',
        'district' => 'Центральный',
        'address' => 'ул. Ленина, 1',
        'site_type' => 'parking',
        'area_m2' => 100,
        'status' => 'new',
        'owner_type' => 'private',
    ]);

    $response->assertStatus(403);
});

test('analyst может создавать площадки', function (): void {
    [, $headers] = actingAs('analyst');

    $response = $this->withHeaders($headers)->postJson('/api/v1/sites', [
        'title' => 'Test',
        'city' => 'Омск',
        'district' => 'Центральный',
        'address' => 'ул. Ленина, 1',
        'site_type' => 'parking',
        'area_m2' => 100,
        'status' => 'new',
        'owner_type' => 'private',
    ]);

    $response->assertStatus(201);
});

// ─── Площадки: смена статуса ──────────────────────────────────────────────────

test('analyst не может менять статус площадки', function (): void {
    [, $headers] = actingAs('analyst');
    $site = Site::factory()->create(['status' => 'new']);

    $response = $this->withHeaders($headers)
        ->patchJson("/api/v1/sites/{$site->id}/status", ['status' => 'screening']);

    $response->assertStatus(403);
});

test('manager может менять статус площадки', function (): void {
    [, $headers] = actingAs('manager');
    $site = Site::factory()->create(['status' => 'new']);

    $response = $this->withHeaders($headers)
        ->patchJson("/api/v1/sites/{$site->id}/status", ['status' => 'screening']);

    $response->assertOk();
});

// ─── Риски ────────────────────────────────────────────────────────────────────

test('manager не может добавлять риски', function (): void {
    [, $headers] = actingAs('manager');
    $site = Site::factory()->create();

    $response = $this->withHeaders($headers)->postJson("/api/v1/sites/{$site->id}/risks", [
        'type' => 'legal',
        'severity' => 'medium',
        'probability' => 'medium',
        'description' => 'test',
    ]);

    $response->assertStatus(403);
});

test('manager может просматривать риски', function (): void {
    [, $headers] = actingAs('manager');
    $site = Site::factory()->create();

    $response = $this->withHeaders($headers)->getJson("/api/v1/sites/{$site->id}/risks");
    $response->assertOk();
});

// ─── Финансы ──────────────────────────────────────────────────────────────────

test('manager не может изменять финмодель', function (): void {
    [, $headers] = actingAs('manager');
    $site = Site::factory()->create();

    $response = $this->withHeaders($headers)->putJson("/api/v1/sites/{$site->id}/finance", [
        'containers_count' => 4,
        'units_per_container' => 8,
        'occupancy_percent' => 70,
        'capex_total' => 1_000_000,
        'opex_total' => 50_000,
        'price_per_unit' => [4500, 6500, 9000],
    ]);

    $response->assertStatus(403);
});

// ─── Пользователи (только admin) ──────────────────────────────────────────────

test('analyst не может создавать пользователей', function (): void {
    [, $headers] = actingAs('analyst');

    $response = $this->withHeaders($headers)->postJson('/api/v1/users', [
        'name' => 'Test',
        'email' => 'test@test.com',
        'temporary_password' => 'password123',
        'role' => 'manager',
    ]);

    $response->assertStatus(403);
});

test('manager не может создавать пользователей', function (): void {
    [, $headers] = actingAs('manager');

    $response = $this->withHeaders($headers)->postJson('/api/v1/users', [
        'name' => 'Test',
        'email' => 'test@test.com',
        'temporary_password' => 'password123',
        'role' => 'manager',
    ]);

    $response->assertStatus(403);
});

// ─── Чтение доступно всем ─────────────────────────────────────────────────────

test('manager может читать список площадок', function (): void {
    [, $headers] = actingAs('manager');

    $response = $this->withHeaders($headers)->getJson('/api/v1/sites');
    $response->assertOk();
});

test('analyst может читать справочник чеклиста', function (): void {
    [, $headers] = actingAs('analyst');

    $response = $this->withHeaders($headers)->getJson('/api/v1/checklist-items');
    $response->assertOk();
});

// ─── Гейт временного пароля ───────────────────────────────────────────────────

test('пользователь с временным паролем не может работать с API', function (): void {
    $user = User::factory()->create([
        'role' => 'admin',
        'password_change_required' => true,
    ]);
    $headers = ['Authorization' => 'Bearer '.$user->createToken('t')->plainTextToken];

    $response = $this->withHeaders($headers)->getJson('/api/v1/sites');
    $response->assertStatus(403)
        ->assertJsonFragment(['code' => 'password_change_required']);
});

test('пользователь с временным паролем может вызвать change-initial-password', function (): void {
    $user = User::factory()->create([
        'role' => 'analyst',
        'password' => bcrypt('temp-pass-1'),
        'password_change_required' => true,
    ]);
    $headers = ['Authorization' => 'Bearer '.$user->createToken('t')->plainTextToken];

    $response = $this->withHeaders($headers)->postJson('/api/v1/auth/change-initial-password', [
        'password' => 'new-permanent-password',
        'password_confirmation' => 'new-permanent-password',
    ]);

    $response->assertOk();
    expect($user->fresh()->password_change_required)->toBeFalse();
});
