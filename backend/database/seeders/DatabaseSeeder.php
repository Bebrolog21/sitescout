<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Идемпотентно создаём админа: если такой email уже есть, не трогаем.
        User::firstOrCreate(
            ['email' => 'admin@sitescout.test'],
            [
                'name'     => 'Администратор',
                'password' => Hash::make('password'),
                'role'     => 'admin',
            ],
        );

        $this->call(DemoDataSeeder::class);
    }
}
