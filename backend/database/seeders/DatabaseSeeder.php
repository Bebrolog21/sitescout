<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Единственный стартовый аккаунт — администратор.
        // Остальных пользователей создаёт сам admin через интерфейс.
        User::create([
            'name'     => 'Администратор',
            'email'    => 'admin@sitescout.test',
            'password' => Hash::make('password'),
            'role'     => 'admin',
        ]);

        $this->call(DemoDataSeeder::class);
    }
}
