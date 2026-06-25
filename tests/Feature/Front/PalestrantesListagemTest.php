<?php

namespace Tests\Feature\Front;

use App\Models\Palestrante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PalestrantesListagemTest extends TestCase
{
    use RefreshDatabase;

    public function test_lista_mostra_ativos_e_oculta_inativos(): void
    {
        Palestrante::factory()->ativo()->create(['nome' => 'João Ativo']);
        Palestrante::factory()->inativo()->create(['nome' => 'Maria Inativa']);

        $resp = $this->get(route('palestrantes.index'));

        $resp->assertOk();
        $resp->assertSee('João Ativo');
        $resp->assertDontSee('Maria Inativa');
    }
}
