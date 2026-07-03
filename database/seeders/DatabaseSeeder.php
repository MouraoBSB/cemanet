<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Admin DURÁVEL e idempotente (sobrevive a db:seed; sem usuário aleatório).
        // Credenciais via .env (não versionadas); o model não tem mais o cast 'hashed'
        // (HasherLegadoCema cuida do legado), então a senha é hasheada aqui explicitamente.
        User::updateOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@cema.local')],
            [
                'name' => env('ADMIN_NAME', 'Admin CEMA'),
                'password' => Hash::make(env('ADMIN_PASSWORD', 'password')),
                'email_verified_at' => now(),
            ],
        );

        $this->call(CategoriaSeeder::class);
        $this->call(EstruturaCemaSeeder::class);
    }
}
