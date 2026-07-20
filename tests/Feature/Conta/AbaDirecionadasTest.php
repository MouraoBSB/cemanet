<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-20

namespace Tests\Feature\Conta;

use App\Enums\VisibilidadeMensagem;
use App\Models\Mensagem;
use App\Models\User;
use App\Support\Conta\AbaDirecionadas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * O setUp NÃO semeia nada (nem EstruturaCemaSeeder, nem permissions): a 3C decide o acesso
 * por PERTENCIMENTO ao pivô, não por capacidade. Que estes testes passem sem nenhum seed de
 * permissão É a prova de que AbaDirecionadas não consulta permission (contraste com AbaAgenda).
 */
class AbaDirecionadasTest extends TestCase
{
    use RefreshDatabase;

    private function direcionadaPublicadaPara(User $user): Mensagem
    {
        $m = Mensagem::factory()->comNivel(VisibilidadeMensagem::Direcionada)->create(); // nasce STATUS_PUBLICADO
        $m->destinatarios()->attach($user->id);

        return $m;
    }

    public function test_aba_visivel_para_destinatario_de_direcionada_publicada(): void
    {
        $user = User::factory()->create();
        $this->direcionadaPublicadaPara($user);

        $this->assertTrue(AbaDirecionadas::visivelPara($user));
    }

    public function test_aba_oculta_sem_direcionada(): void
    {
        $this->assertFalse(AbaDirecionadas::visivelPara(User::factory()->create()));
    }

    /** publicado(): uma direcionada PENDENTE (curadoria F4) NÃO acende a aba. */
    public function test_pendente_nao_acende_a_aba(): void
    {
        $user = User::factory()->create();
        $m = Mensagem::factory()->comNivel(VisibilidadeMensagem::Direcionada)->pendente()->create();
        $m->destinatarios()->attach($user->id);

        $this->assertFalse(AbaDirecionadas::visivelPara($user));
    }

    /** Blindagem O5 (I7): vínculo a uma PUBLICADA de OUTRO nível NÃO conta como direcionada. */
    public function test_outro_nivel_publicado_nao_acende_a_aba(): void
    {
        $user = User::factory()->create();
        $m = Mensagem::factory()->comNivel('trabalhadores')->create(); // publicada, nivel != direcionada
        $m->destinatarios()->attach($user->id);

        $this->assertFalse(AbaDirecionadas::visivelPara($user), 'vínculo a mensagem de outro nível não conta (O5)');
    }

    /** O pivô é por user_id: a direcionada de OUTRO usuário não acende a minha aba. */
    public function test_direcionada_de_outro_usuario_nao_conta(): void
    {
        $this->direcionadaPublicadaPara(User::factory()->create());

        $this->assertFalse(AbaDirecionadas::visivelPara(User::factory()->create()));
    }
}
