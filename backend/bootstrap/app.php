<?php

use App\Http\Middleware\EnsurePasswordChanged;
use App\Http\Middleware\EnsureRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Проект использует Bearer-токены (Sanctum Token Auth), а не
        // stateful cookie-сессии. statefulApi() НЕ нужен — его присутствие
        // добавляло CSRF-проверку ко всем API-маршрутам через
        // EnsureFrontendRequestsAreStateful, что приводило к ошибке 419
        // при POST /api/v1/auth/login (и любых других POST-запросах)
        // с фронтенда, который не отправляет CSRF-токен.

        $middleware->alias([
            'password.changed' => EnsurePasswordChanged::class,
            'role'             => EnsureRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Для всех /api/* возвращаем JSON 401 вместо редиректа на несуществующий
        // именованный роут `login`. Иначе при заходе в браузере на защищённый
        // эндпоинт без токена Laravel падает на RouteNotFoundException.
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }
            return null;
        });
    })
    ->create();
