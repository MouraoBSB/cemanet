<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-19

namespace Tests\Feature\Models;

use App\Models\AutorEspiritual;
use App\Models\Mensagem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutorEspiritualMensagensTest extends TestCase
{
    use RefreshDatabase;

    public function test_mensagens_pelo_pivo_e_publica_encadeia(): void
    {
        $autor = AutorEspiritual::factory()->create();
        $pub1 = Mensagem::factory()->publica()->create();
        $pub2 = Mensagem::factory()->publica()->create();
        $pendente = Mensagem::factory()->pendente()->create();

        $autor->mensagens()->sync([$pub1->id, $pub2->id, $pendente->id]);

        // a relação lê as 3 vinculadas...
        $this->assertSame(3, $autor->fresh()->mensagens()->count());
        // ...e o scope publica() encadeia (só as 2 públicas).
        $this->assertSame(2, $autor->fresh()->mensagens()->publica()->count());
    }

    public function test_simetria_com_mensagem_autores(): void
    {
        $autor = AutorEspiritual::factory()->create();
        $m = Mensagem::factory()->create();

        $m->autores()->sync([$autor->id]);

        $this->assertTrue($autor->fresh()->mensagens->contains('id', $m->id));
    }
}
