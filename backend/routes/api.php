<?php

use App\Http\Controllers\Api\V1\AttachmentController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ChecklistItemController;
use App\Http\Controllers\Api\V1\FinanceController;
use App\Http\Controllers\Api\V1\ResidentialComplexController;
use App\Http\Controllers\Api\V1\RiskController;
use App\Http\Controllers\Api\V1\SiteChecklistController;
use App\Http\Controllers\Api\V1\SiteController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\VisitController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {

    // Корневой health/info — чтобы при открытии /api/v1 в браузере не было 404.
    Route::get('/', function (): array {
        return [
            'service' => 'SiteScout API',
            'version' => '1.2.0',
            'status'  => 'ok',
            'docs'    => 'backend/openapi/openapi.yaml',
            'endpoints' => [
                'auth'                  => '/api/v1/auth/*',
                'users'                 => '/api/v1/users',
                'sites'                 => '/api/v1/sites',
                'residential_complexes' => '/api/v1/residential-complexes',
                'checklist_items'       => '/api/v1/checklist-items',
                'attachments'           => '/api/v1/attachments',
            ],
        ];
    });

    Route::post('/auth/login',           [AuthController::class, 'login']);
    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/auth/reset-password',  [AuthController::class, 'resetPassword']);

    Route::middleware('auth:sanctum')->group(function (): void {
        // Доступно даже при необходимости сменить временный пароль:
        Route::post('/auth/logout',                  [AuthController::class, 'logout']);
        Route::get('/auth/me',                       [AuthController::class, 'me']);
        Route::post('/auth/change-initial-password', [AuthController::class, 'changeInitialPassword']);

        // Всё остальное API заблокировано до смены временного пароля.
        Route::middleware('password.changed')->group(function (): void {

            // ── Пользователи (только admin) ───────────────────────
            Route::middleware('role:admin')->group(function (): void {
                Route::get('/users',               [UserController::class, 'index']);
                Route::post('/users',              [UserController::class, 'store']);
                Route::patch('/users/{user}/role', [UserController::class, 'updateRole']);
                Route::delete('/users/{user}',     [UserController::class, 'destroy']);
            });

            // ── ЖК ────────────────────────────────────────────────
            Route::get('/residential-complexes',                [ResidentialComplexController::class, 'index']);
            Route::get('/residential-complexes/{residential_complex}', [ResidentialComplexController::class, 'show']);
            Route::middleware('role:admin,analyst')->group(function (): void {
                Route::post('/residential-complexes',                [ResidentialComplexController::class, 'store']);
                Route::put('/residential-complexes/{residential_complex}',   [ResidentialComplexController::class, 'update']);
                Route::patch('/residential-complexes/{residential_complex}', [ResidentialComplexController::class, 'update']);
            });

            // ── Площадки: чтение всем ─────────────────────────────
            Route::get('/sites',                  [SiteController::class, 'index']);
            Route::get('/sites/{site}',           [SiteController::class, 'show']);
            Route::get('/sites/{site}/passport',     [SiteController::class, 'passport']);
            Route::get('/sites/{site}/passport.pdf', [SiteController::class, 'passportPdf']);
            Route::get('/sites/{site}/stats',     [SiteController::class, 'stats']);

            // Площадки: создание/редактирование контента — admin + analyst
            Route::middleware('role:admin,analyst')->group(function (): void {
                Route::post('/sites',         [SiteController::class, 'store']);
                Route::put('/sites/{site}',   [SiteController::class, 'update']);
                Route::patch('/sites/{site}', [SiteController::class, 'update']);
                Route::delete('/sites/{site}',[SiteController::class, 'destroy']);
            });

            // Перевод статусов — admin + manager
            Route::middleware('role:admin,manager')->group(function (): void {
                Route::patch('/sites/{site}/status', [SiteController::class, 'updateStatus']);
            });

            // ── Чеклист площадки ─────────────────────────────────
            Route::get('/sites/{site}/checklist', [SiteChecklistController::class, 'show']);
            Route::middleware('role:admin,analyst')->group(function (): void {
                Route::put('/sites/{site}/checklist',          [SiteChecklistController::class, 'update']);
                Route::post('/sites/{site}/recalculate-score', [SiteChecklistController::class, 'recalculate']);
            });

            // Справочник критериев чеклиста: чтение — всем, запись — только admin
            Route::get('/checklist-items', [ChecklistItemController::class, 'index']);
            Route::middleware('role:admin')->group(function (): void {
                Route::post('/checklist-items',         [ChecklistItemController::class, 'store']);
                Route::put('/checklist-items/{checklist_item}',   [ChecklistItemController::class, 'update']);
                Route::patch('/checklist-items/{checklist_item}', [ChecklistItemController::class, 'update']);
            });

            // ── Риски: чтение всем, запись — admin + analyst ─────
            Route::get('/sites/{site}/risks', [RiskController::class, 'index']);
            Route::middleware('role:admin,analyst')->group(function (): void {
                Route::post('/sites/{site}/risks', [RiskController::class, 'store']);
                Route::put('/risks/{risk}',        [RiskController::class, 'update']);
                Route::delete('/risks/{risk}',     [RiskController::class, 'destroy']);
            });

            // ── Финансы: чтение всем, запись — admin + analyst ───
            Route::get('/sites/{site}/finance',         [FinanceController::class, 'show']);
            Route::get('/sites/{site}/finance/summary', [FinanceController::class, 'summary']);
            Route::middleware('role:admin,analyst')->group(function (): void {
                Route::put('/sites/{site}/finance',              [FinanceController::class, 'update']);
                Route::post('/sites/{site}/finance/recalculate', [FinanceController::class, 'recalculate']);
            });

            // ── Визиты: чтение всем, запись — admin + analyst ────
            Route::get('/sites/{site}/visits', [VisitController::class, 'index']);
            Route::get('/visits/{visit}',      [VisitController::class, 'show']);
            Route::middleware('role:admin,analyst')->group(function (): void {
                Route::post('/sites/{site}/visits', [VisitController::class, 'store']);
                Route::put('/visits/{visit}',       [VisitController::class, 'update']);
                Route::delete('/visits/{visit}',    [VisitController::class, 'destroy']);
            });

            // ── Файлы: чтение всем, запись — admin + analyst ─────
            Route::get('/attachments', [AttachmentController::class, 'index']);
            Route::middleware('role:admin,analyst')->group(function (): void {
                Route::post('/attachments',                [AttachmentController::class, 'store']);
                Route::delete('/attachments/{attachment}', [AttachmentController::class, 'destroy']);
            });
        });
    });
});
