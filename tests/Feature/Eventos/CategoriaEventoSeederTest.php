<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-08

namespace Tests\Feature\Eventos;

use App\Models\CategoriaEvento;
use Database\Seeders\CategoriaEventoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoriaEventoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_semeia_as_cinco_categorias_e_e_idempotente(): void
    {
        $this->seed(CategoriaEventoSeeder::class);
        $this->seed(CategoriaEventoSeeder::class); // 2ª vez não duplica

        $this->assertSame(5, CategoriaEvento::count());

        $brecho = CategoriaEvento::where('slug', 'brecho')->first();
        $this->assertSame('Brechó Solidário', $brecho->nome);
        $this->assertSame('#89AB98', $brecho->cor);
        $this->assertSame('#26242E', $brecho->cor_texto);
        $this->assertTrue($brecho->ativo);
    }

    public function test_scope_ativo_filtra_inativas(): void
    {
        $this->seed(CategoriaEventoSeeder::class);
        CategoriaEvento::where('slug', 'estudo')->update(['ativo' => false]);

        $this->assertSame(4, CategoriaEvento::ativo()->count());
    }
}
