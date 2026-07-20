<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-19

namespace Tests\Feature\Mensagens;

use App\Enums\VisibilidadeMensagem;
use App\Models\Mensagem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MensagemVisibilidadeAccessorTest extends TestCase
{
    use RefreshDatabase;

    public function test_reidrata_o_enum_a_partir_do_slug(): void
    {
        $m = Mensagem::factory()->comNivel(VisibilidadeMensagem::Mediuns)->create();
        $this->assertSame(VisibilidadeMensagem::Mediuns, $m->visibilidade());
    }

    public function test_null_e_slug_desconhecido_dao_null_fail_closed(): void
    {
        $this->assertNull(Mensagem::factory()->create(['nivel' => null])->visibilidade());
        $this->assertNull(Mensagem::factory()->create(['nivel' => 'xpto-inexistente'])->visibilidade());
    }

    public function test_nivel_permanece_string_bruta_neutralidade(): void
    {
        $m = Mensagem::factory()->comNivel('trabalhadores')->create();
        $this->assertSame('trabalhadores', $m->nivel); // NÃO castado — a suite 2A segue verde
    }
}
