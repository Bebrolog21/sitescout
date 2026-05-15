<?php

use Illuminate\Support\Facades\Route;

// Именованный роут `login` нужен Laravel'у как fallback: middleware
// `auth:sanctum`, видя браузерный (HTML) запрос без токена, делает
// redirect()->guest(route('login')). Web-логина в этом проекте нет
// (API-only), поэтому возвращаем здесь же честный 401 JSON.
Route::any('/login', function () {
    return response()->json(['message' => 'Unauthenticated.'], 401);
})->name('login');

// Раздача файла OpenAPI-спецификации напрямую из backend/openapi.
// public/-папка не подходит — YAML лежит вне веб-корня, чтобы не дублироваться.
Route::get('/openapi/openapi.yaml', function () {
    $path = base_path('openapi/openapi.yaml');
    abort_unless(file_exists($path), 404);
    return response()->file($path, [
        'Content-Type' => 'application/x-yaml; charset=UTF-8',
    ]);
});

// Корень — стандартная Laravel 13 welcome-страница.
// Машинные интеграции продолжают ходить за JSON-инфой на /api/v1.
Route::view('/', 'welcome');
