<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-21

namespace Tests\Feature\Mensagens;

use App\Enums\VisibilidadeMensagem;
use App\Models\Mensagem;
use App\Models\User;
use App\Support\Mensagens\SincronizadorDestinatarios;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SincronizadorDestinatariosTest extends TestCase
{
    use RefreshDatabase;

    public function test_filtrar_por_nivel_publico_zera(): void
    {
        $this->assertSame([], SincronizadorDestinatarios::filtrarPorNivel('publico', [7, 9]));
    }

    public function test_filtrar_por_nivel_nulo_zera(): void
    {
        $this->assertSame([], SincronizadorDestinatarios::filtrarPorNivel(null, [7]));
    }

    /** CRU (armadilha #1): preserva ids ainda que inexistentes — existência é responsabilidade de aplicar(). */
    public function test_filtrar_por_nivel_direcionada_preserva_ids_crus(): void
    {
        $this->assertSame(
            [7, 9],
            SincronizadorDestinatarios::filtrarPorNivel(VisibilidadeMensagem::Direcionada->value, [7, 9])
        );
    }

    /** Armadilha #2: id inexistente não pode virar QueryException de FK — aplicar() filtra antes do sync. */
    public function test_aplicar_filtra_ids_inexistentes_contra_users(): void
    {
        $mensagem = Mensagem::factory()->create();
        $usuario = User::factory()->create();

        SincronizadorDestinatarios::aplicar($mensagem, VisibilidadeMensagem::Direcionada->value, [$usuario->id, 99999]);

        $this->assertSame([$usuario->id], $mensagem->destinatarios()->pluck('users.id')->all());
    }

    /** I7: usuário inativo forjado no payload nunca entra no pivô, mesmo existindo. */
    public function test_aplicar_filtra_usuario_inativo_contra_users_ativos(): void
    {
        $mensagem = Mensagem::factory()->create();
        $ativo = User::factory()->create(['ativo' => true]);
        $inativo = User::factory()->create(['ativo' => false]);

        SincronizadorDestinatarios::aplicar(
            $mensagem,
            VisibilidadeMensagem::Direcionada->value,
            [$ativo->id, $inativo->id]
        );

        $this->assertSame([$ativo->id], $mensagem->destinatarios()->pluck('users.id')->all());
    }

    public function test_aplicar_com_nivel_diferente_de_direcionada_esvazia_o_pivo(): void
    {
        $mensagem = Mensagem::factory()->create();
        $usuario = User::factory()->create();
        $mensagem->destinatarios()->sync([$usuario->id]);

        SincronizadorDestinatarios::aplicar($mensagem, VisibilidadeMensagem::Publico->value, [$usuario->id]);

        $this->assertSame([], $mensagem->destinatarios()->pluck('users.id')->all());
    }
}
