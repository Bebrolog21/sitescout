<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    // ── Только admin ────────────────────────────────────────────

    private function requireAdmin(Request $request): void
    {
        abort_unless($request->user()?->isAdmin(), 403, 'Доступ разрешён только администратору.');
    }

    // ── GET /api/v1/users ────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $this->requireAdmin($request);

        $users = User::query()
            ->select('id', 'name', 'email', 'role', 'password_change_required', 'created_at')
            ->orderBy('id')
            ->get();

        return response()->json($users);
    }

    // ── POST /api/v1/users ───────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        $this->requireAdmin($request);

        // email:rfc (без dns) — иначе зарезервированные TLD типа .test / .local
        // не проходят, хотя они корректны по синтаксису и нужны для демо.
        $data = $request->validate(
            [
                'name'              => ['required', 'string', 'max:120'],
                'email'             => ['required', 'email:rfc,dns', 'max:255', 'unique:users,email'],
                'temporary_password' => ['required', 'string', Password::min(8)],
                'role'              => ['required', 'in:admin,analyst,manager'],
            ],
            [
                'required'           => 'Поле «:attribute» обязательно к заполнению.',
                'email.email'        => 'Введите корректный email (например, user@example.com).',
                'email.unique'       => 'Пользователь с таким email уже существует.',
                'name.max'           => 'Имя не должно быть длиннее :max символов.',
                'email.max'          => 'Email не должен быть длиннее :max символов.',
                'temporary_password.min' => 'Временный пароль должен быть не короче :min символов.',
                'role.in'            => 'Недопустимое значение роли.',
            ],
            [
                'name'               => 'имя',
                'email'              => 'email',
                'temporary_password' => 'временный пароль',
                'role'               => 'роль',
            ],
        );

        $user = User::create([
            'name'                     => $data['name'],
            'email'                    => $data['email'],
            'password'                 => Hash::make($data['temporary_password']),
            'role'                     => $data['role'],
            'password_change_required' => true,
        ]);

        return response()->json(
            $user->only('id', 'name', 'email', 'role', 'password_change_required', 'created_at'),
            201,
        );
    }

    // ── PATCH /api/v1/users/{user}/role ──────────────────────────

    public function updateRole(Request $request, User $user): JsonResponse
    {
        $this->requireAdmin($request);

        // Нельзя снять роль admin у самого себя
        if ($user->id === $request->user()->id && $request->input('role') !== 'admin') {
            abort(422, 'Нельзя изменить собственную роль.');
        }

        $data = $request->validate([
            'role' => ['required', 'in:admin,analyst,manager'],
        ]);

        $user->update(['role' => $data['role']]);

        return response()->json($user->only('id', 'name', 'email', 'role', 'password_change_required', 'created_at'));
    }

    // ── DELETE /api/v1/users/{user} ──────────────────────────────

    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->requireAdmin($request);

        // Нельзя удалить самого себя
        if ($user->id === $request->user()->id) {
            abort(422, 'Нельзя удалить собственный аккаунт.');
        }

        $user->delete();

        return response()->json(['message' => 'Пользователь удалён.']);
    }
}
