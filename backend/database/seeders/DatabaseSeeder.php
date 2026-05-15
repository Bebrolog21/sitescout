<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    private const ADMIN_EMAIL = 'admin@sitescout.test';
    private const ADMIN_PASSWORD = 'password';

    public function run(): void
    {
        // Идемпотентно создаём демо-админа.
        $admin = User::firstOrCreate(
            ['email' => self::ADMIN_EMAIL],
            [
                'name'                     => 'Администратор',
                'password'                 => Hash::make(self::ADMIN_PASSWORD),
                'role'                     => 'admin',
                'password_change_required' => false,
            ],
        );

        // Самовосстановление: если запись существует, но пароль был испорчен
        // (например, после неудачных seed-попыток с другим APP_KEY), сбрасываем
        // его на демо-значение. Так демо-логин остаётся предсказуемым между деплоями.
        if (! Hash::check(self::ADMIN_PASSWORD, $admin->password)) {
            $admin->forceFill([
                'password'                 => Hash::make(self::ADMIN_PASSWORD),
                'password_change_required' => false,
            ])->save();
            $this->command?->warn('Демо-пароль для '.self::ADMIN_EMAIL.' восстановлен.');
        }

        $this->call(DemoDataSeeder::class);
    }
}
