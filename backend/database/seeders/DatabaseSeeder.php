<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Единственный сид-пользователь — регистрации в приложении намеренно нет.
        // Логин/пароль берём из env, чтобы на демо-хостинге можно было задать свои.
        User::updateOrCreate(
            ['email' => env('SEED_USER_EMAIL', 'demo@example.com')],
            [
                'name' => 'Demo User',
                'password' => Hash::make(env('SEED_USER_PASSWORD', 'password')),
                'email_verified_at' => now(),
            ],
        );
    }
}
