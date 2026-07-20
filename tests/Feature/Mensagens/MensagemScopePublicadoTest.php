<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-20

namespace Tests\Feature\Mensagens;

use App\Models\Mensagem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MensagemScopePublicadoTest extends TestCase
{
    use RefreshDatabase;

    public function test_publicado_ignora_status_nao_publicado(): void
    {
        Mensagem::factory()->publica()->create(['slug' => 'p1']);                                  // publicado + publico
        Mensagem::factory()->create(['status' => 'publicado', 'nivel' => 'trabalhadores', 'slug' => 'p2']);
        Mensagem::factory()->pendente()->create(['slug' => 'p3']);

        $this->assertSame(2, Mensagem::publicado()->count());   // p1 + p2 (não a pendente p3)
    }

    public function test_paridade_anonima_com_publica_e_null_fora(): void
    {
        Mensagem::factory()->publica()->create(['slug' => 'pub']);
        Mensagem::factory()->create(['status' => 'publicado', 'nivel' => 'trabalhadores', 'slug' => 'trab']);
        Mensagem::factory()->create(['status' => 'publicado', 'nivel' => null, 'slug' => 'nula']);   // R5: publicada sem nível
        Mensagem::factory()->pendente()->create(['slug' => 'pend']);

        $anon = Mensagem::publicado()->visiveisPara(null)->pluck('slug')->sort()->values()->all();
        $publica = Mensagem::publica()->pluck('slug')->sort()->values()->all();

        $this->assertSame(['pub'], $anon);        // só a pública — a 'nula' e a 'trab' NÃO vazam ao anônimo
        $this->assertSame($publica, $anon);       // paridade exata com a 2B (I2)
    }
}
