<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // EstruturaCemaSeeder cria os papéis (Role) antes do AdminSeeder atribuir
        // 'administrador' — ordem importa: syncRoles() falha se o papel ainda não existe.
        $this->call(CategoriaSeeder::class);
        $this->call(EstruturaCemaSeeder::class);

        // Admin DURÁVEL e idempotente (sobrevive a db:seed; sem usuário aleatório), já com
        // o papel administrador — fonte única do admin do site novo (ver AdminSeeder).
        $this->call(AdminSeeder::class);
    }
}
