<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->password_change_required) {
            return response()->json([
                'message' => 'Необходимо сменить временный пароль перед продолжением работы.',
                'code' => 'password_change_required',
            ], 403);
        }

        return $next($request);
    }
}
