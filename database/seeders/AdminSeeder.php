<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-03

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $senha = env('ADMIN_PASSWORD');

        if (blank($senha)) {
            throw new \RuntimeException('Defina ADMIN_PASSWORD no .env antes de rodar o AdminSeeder.');
        }

        $admin = User::updateOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@cema.local')],
            [
                'name' => env('ADMIN_NAME', 'Administrador CEMA'),
                'password' => Hash::make($senha),
                'email_verified_at' => now(),
            ],
        );
        $admin->syncRoles(['administrador']);
    }
}
