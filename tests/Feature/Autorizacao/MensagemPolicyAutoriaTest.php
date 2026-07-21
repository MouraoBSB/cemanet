<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-21

namespace Tests\Feature\Autorizacao;

use App\Models\Cargo;
use App\Models\Mensagem;
use App\Models\Setor;
use App\Models\User;
use Database\Seeders\EstruturaCemaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * Eixo AUTORIA (F4b) da MensagemPolicy — pertencimento por setor/cargo, NUNCA capacidade/matriz.
 * Todo teste usa não-admin (Gate::forUser($naoAdmin)): admin passa em tudo via Gate::before e não prova nada.
 */
class MensagemPolicyAutoriaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstruturaCemaSeeder::class); // papéis + setores + cargos
    }

    /** Usuário com papel + setores/cargos opcionais (slugs). Molde: MensagemVisibilidadeAcessoTest::usuario(). */
    private function usuario(?string $papel, array $setores = [], array $cargos = []): User
    {
        $u = User::factory()->create();
        if ($papel !== null) {
            $u->assignRole($papel);
        }
        foreach ($setores as $slug) {
            $u->setores()->attach(Setor::where('slug', $slug)->value('id'), ['funcao' => 'membro']);
        }
        foreach ($cargos as $slug) {
            $u->cargos()->attach(Cargo::where('slug', $slug)->value('id'));
        }

        return $u->fresh();
    }

    // ---- lancar (sem objeto) ----

    public function test_medium_pode_lancar(): void
    {
        $medium = $this->usuario('trabalhador', [Setor::SLUG_MEDIUM]);

        $this->assertTrue(Gate::forUser($medium)->check('lancar', Mensagem::class));
    }

    public function test_frequentador_nao_pode_lancar(): void
    {
        $frequentador = $this->usuario('frequentador');

        $this->assertFalse(Gate::forUser($frequentador)->check('lancar', Mensagem::class));
    }

    public function test_diretor_depae_nao_medium_nao_pode_lancar(): void
    {
        $diretorDepae = $this->usuario('diretor', [], [Cargo::SLUG_DIRETOR_DEPAE]);

        $this->assertFalse(Gate::forUser($diretorDepae)->check('lancar', Mensagem::class));
    }

    // ---- curar (sem objeto) ----

    public function test_diretor_depae_pode_curar(): void
    {
        $diretorDepae = $this->usuario('diretor', [], [Cargo::SLUG_DIRETOR_DEPAE]);

        $this->assertTrue(Gate::forUser($diretorDepae)->check('curar', Mensagem::class));
    }

    public function test_presidente_pode_curar(): void
    {
        $presidente = $this->usuario('frequentador', [], [Cargo::SLUG_PRESIDENTE]);

        $this->assertTrue(Gate::forUser($presidente)->check('curar', Mensagem::class));
    }

    public function test_medium_comum_nao_pode_curar(): void
    {
        $medium = $this->usuario('trabalhador', [Setor::SLUG_MEDIUM]);

        $this->assertFalse(Gate::forUser($medium)->check('curar', Mensagem::class));
    }

    // ---- editarPendente ----

    public function test_dono_edita_a_propria_mensagem_pendente(): void
    {
        $medium = $this->usuario('trabalhador', [Setor::SLUG_MEDIUM]);
        $mensagem = Mensagem::factory()->pendente()->create(['medium_id' => $medium->id]);

        $this->assertTrue(Gate::forUser($medium)->check('editarPendente', $mensagem));
    }

    public function test_dono_nao_edita_a_propria_mensagem_ja_publicada(): void
    {
        $medium = $this->usuario('trabalhador', [Setor::SLUG_MEDIUM]);
        $mensagem = Mensagem::factory()->publicada()->create(['medium_id' => $medium->id]);

        $this->assertFalse(Gate::forUser($medium)->check('editarPendente', $mensagem));
    }

    public function test_outro_medium_nao_edita_pendente_alheia(): void
    {
        $dono = $this->usuario('trabalhador', [Setor::SLUG_MEDIUM]);
        $outroMedium = $this->usuario('trabalhador', [Setor::SLUG_MEDIUM]);
        $mensagem = Mensagem::factory()->pendente()->create(['medium_id' => $dono->id]);

        $this->assertFalse(Gate::forUser($outroMedium)->check('editarPendente', $mensagem));
    }

    public function test_nao_medium_nao_edita_pendente(): void
    {
        $dono = $this->usuario('trabalhador', [Setor::SLUG_MEDIUM]);
        $naoMedium = $this->usuario('trabalhador');
        $mensagem = Mensagem::factory()->pendente()->create(['medium_id' => $dono->id]);

        $this->assertFalse(Gate::forUser($naoMedium)->check('editarPendente', $mensagem));
    }

    // ---- editarNaCuradoria / publicar (publicar delega — mesma regra) ----

    public function test_curador_diretor_depae_edita_e_publica_pendente(): void
    {
        $diretorDepae = $this->usuario('diretor', [], [Cargo::SLUG_DIRETOR_DEPAE]);
        $mensagem = Mensagem::factory()->pendente()->create();

        $this->assertTrue(Gate::forUser($diretorDepae)->check('editarNaCuradoria', $mensagem));
        $this->assertTrue(Gate::forUser($diretorDepae)->check('publicar', $mensagem));
    }

    public function test_curador_presidente_edita_e_publica_pendente(): void
    {
        $presidente = $this->usuario('frequentador', [], [Cargo::SLUG_PRESIDENTE]);
        $mensagem = Mensagem::factory()->pendente()->create();

        $this->assertTrue(Gate::forUser($presidente)->check('editarNaCuradoria', $mensagem));
        $this->assertTrue(Gate::forUser($presidente)->check('publicar', $mensagem));
    }

    /** O7/I13 — o furo do passe: curador NÃO edita/publica uma mensagem já publicada (isso é tarefa do /admin). */
    public function test_curador_nao_edita_nem_publica_mensagem_ja_publicada(): void
    {
        $diretorDepae = $this->usuario('diretor', [], [Cargo::SLUG_DIRETOR_DEPAE]);
        $mensagem = Mensagem::factory()->publicada()->create();

        $this->assertFalse(Gate::forUser($diretorDepae)->check('editarNaCuradoria', $mensagem));
        $this->assertFalse(Gate::forUser($diretorDepae)->check('publicar', $mensagem));
    }

    public function test_medium_comum_nao_edita_nem_publica_na_curadoria(): void
    {
        $medium = $this->usuario('trabalhador', [Setor::SLUG_MEDIUM]);
        $mensagem = Mensagem::factory()->pendente()->create();

        $this->assertFalse(Gate::forUser($medium)->check('editarNaCuradoria', $mensagem));
        $this->assertFalse(Gate::forUser($medium)->check('publicar', $mensagem));
    }
}
