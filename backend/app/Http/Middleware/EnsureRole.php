<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    /**
     * Пропускает запрос, только если у пользователя одна из перечисленных ролей.
     * Применяется так: ->middleware('role:admin,analyst').
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! in_array($user->role, $roles, true)) {
            return response()->json([
                'message' => 'Недостаточно прав для выполнения операции.',
                'code' => 'forbidden_for_role',
                'required_roles' => $roles,
            ], 403);
        }

        return $next($request);
    }
}
