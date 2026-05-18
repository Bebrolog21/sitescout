<?php

use App\Models\FinancialModel;
use App\Models\Risk;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeUserWithTokenPdf(string $role = 'admin'): array
{
    $user = User::factory()->create(['role' => $role]);
    $token = $user->createToken('test-token')->plainTextToken;

    return [$user, ['Authorization' => "Bearer {$token}"]];
}

test('паспорт площадки скачивается как PDF', function (): void {
    [, $headers] = makeUserWithTokenPdf('admin');

    $site = Site::factory()->create([
        'site_score' => 82,
        'risk_score' => 25,
        'status' => 'scoring',
    ]);

    FinancialModel::create([
        'site_id' => $site->id,
        'assumptions_json' => [
            'containers_count' => 4,
            'units_per_container' => 8,
            'occupancy' => 0.7,
            'capex' => ['container' => 1_500_000, 'delivery' => 200_000],
            'opex' => ['land_rent' => 30_000, 'security' => 15_000],
        ],
        'results_json' => [
            'monthly_revenue' => 150_000,
            'monthly_gross_profit' => 50_000,
            'payback_months' => 12,
            'roi_12m' => 30,
            'breakeven_occupancy' => 55,
        ],
    ]);

    Risk::create([
        'site_id' => $site->id,
        'type' => 'legal',
        'severity' => 'medium',
        'probability' => 'low',
        'description' => 'Тестовый риск',
        'mitigation' => null,
    ]);

    $response = $this->withHeaders($headers)
        ->get("/api/v1/sites/{$site->id}/passport.pdf");

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
    expect($response->headers->get('content-disposition'))->toContain("passport-site-{$site->id}.pdf");

    $body = $response->getContent();
    expect(strlen($body))->toBeGreaterThan(1000);
    expect(substr($body, 0, 4))->toBe('%PDF');
});

test('паспорт PDF собирается даже без финансовой модели', function (): void {
    [, $headers] = makeUserWithTokenPdf('admin');

    $site = Site::factory()->create([
        'site_score' => 40,
        'risk_score' => 60,
    ]);

    $response = $this->withHeaders($headers)
        ->get("/api/v1/sites/{$site->id}/passport.pdf");

    $response->assertOk();
    expect(substr($response->getContent(), 0, 4))->toBe('%PDF');
});

test('паспорт PDF требует авторизации', function (): void {
    $site = Site::factory()->create();

    $response = $this->get("/api/v1/sites/{$site->id}/passport.pdf");

    $response->assertStatus(401);
});
