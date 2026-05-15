<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials)) {
            return response()->json(['message' => 'Invalid credentials.'], 422);
        }

        $token = $request->user()->createToken('api-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $request->user(),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($request->user());
    }

    public function changeInitialPassword(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->password_change_required) {
            return response()->json([
                'message' => 'Смена пароля не требуется.',
            ], 422);
        }

        $data = $request->validate([
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ]);

        if (Hash::check($data['password'], $user->password)) {
            return response()->json([
                'message' => 'Новый пароль должен отличаться от временного.',
                'errors' => ['password' => ['Новый пароль должен отличаться от временного.']],
            ], 422);
        }

        $currentTokenId = $user->currentAccessToken()?->id;

        $user->forceFill([
            'password' => Hash::make($data['password']),
            'password_change_required' => false,
            'remember_token' => Str::random(60),
        ])->save();

        // Чистим все остальные API-токены, оставляем текущий.
        $user->tokens()
            ->when($currentTokenId, fn ($q) => $q->where('id', '!=', $currentTokenId))
            ->delete();

        return response()->json([
            'message' => 'Пароль успешно установлен.',
            'user' => $user->fresh(),
        ]);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $status = Password::sendResetLink($request->only('email'));

        // Не раскрываем существование email: всегда отдаём одно и то же сообщение,
        // если только не сработал throttle.
        if ($status === Password::RESET_THROTTLED) {
            return response()->json([
                'message' => 'Слишком много запросов. Попробуйте позже.',
            ], 429);
        }

        return response()->json([
            'message' => 'Если такой email зарегистрирован, мы отправили на него ссылку для сброса пароля.',
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ]);

        // Внутри Password::reset колбэк уже получает резолвнутого user'а — там
        // проверяем, что новый пароль не совпадает с текущим, и если совпадает,
        // бросаем доменное исключение, которое ловим снаружи как 422.
        $samePasswordError = false;

        try {
            $status = Password::reset(
                $request->only('email', 'password', 'password_confirmation', 'token'),
                function ($user, string $password) use (&$samePasswordError): void {
                    if (Hash::check($password, $user->password)) {
                        $samePasswordError = true;
                        throw new \RuntimeException('SAME_PASSWORD');
                    }

                    $user->forceFill([
                        'password' => Hash::make($password),
                        'remember_token' => Str::random(60),
                    ])->save();

                    // Инвалидируем все ранее выпущенные API-токены.
                    $user->tokens()->delete();

                    event(new PasswordReset($user));
                }
            );
        } catch (\RuntimeException $e) {
            if (! $samePasswordError) {
                throw $e;
            }
            return response()->json([
                'message' => 'Новый пароль должен отличаться от текущего.',
                'errors' => ['password' => ['Новый пароль должен отличаться от текущего.']],
            ], 422);
        }

        if ($status === Password::PASSWORD_RESET) {
            return response()->json([
                'message' => 'Пароль успешно изменён.',
            ]);
        }

        $message = match ($status) {
            Password::INVALID_TOKEN => 'Ссылка для сброса пароля недействительна или устарела.',
            Password::INVALID_USER => 'Пользователь с таким email не найден.',
            default => 'Не удалось сбросить пароль.',
        };

        return response()->json([
            'message' => $message,
            'errors' => ['email' => [$message]],
        ], 422);
    }
}
