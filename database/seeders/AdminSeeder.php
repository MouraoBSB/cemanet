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
        $admin = User::updateOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@cemanet.org.br')],
            [
                'name' => env('ADMIN_NAME', 'Administrador CEMA'),
                'password' => Hash::make(env('ADMIN_PASSWORD', 'trocar-esta-senha')),
                'email_verified_at' => now(),
            ],
        );
        $admin->syncRoles(['administrador']);
    }
}
