<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Single seed user — registration is intentionally not part of the app.
        // Credentials are configurable via env so the hosted demo can differ.
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
