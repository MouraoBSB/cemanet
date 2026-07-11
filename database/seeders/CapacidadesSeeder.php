<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-11

namespace Database\Seeders;

use App\Support\Autorizacao\GlossarioCapacidades;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

/**
 * Semeia as 16 permissions de capacidade (guard web), idempotente. NÃO atribui a papéis:
 * a matriz papel→permissão é a Fase C. Ver App\Support\Autorizacao\GlossarioCapacidades.
 */
class CapacidadesSeeder extends Seeder
{
    public function run(): void
    {
        foreach (GlossarioCapacidades::permissions() as $nome) {
            Permission::updateOrCreate(['name' => $nome, 'guard_name' => 'web']);
        }
    }
}
