<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-21

namespace Tests\Feature\Conta;

use App\Enums\VisibilidadeMensagem;
use App\Livewire\Conta\CuradoriaConta;
use App\Models\AutorEspiritual;
use App\Models\Cargo;
use App\Models\Mensagem;
use App\Models\Setor;
use App\Models\User;
use Database\Seeders\EstruturaCemaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * O martelo da CURADORIA (Fatia F4b, Task 10): `publicar()` arbitra o nível de acesso e põe a
 * mensagem no ar. `RegraPublicacao::erros()` (Task 3) devolve as mensagens — quem lança é o
 * componente, com a chave `data.nivel` (statePath). NUNCA usar actingAsAdmin(): o Gate::before
 * do admin mascararia o eixo de AUTORIA sob teste — ver CuradoriaContaTest.
 */
class CuradoriaPublicarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstruturaCemaSeeder::class);
    }

    private function diretorDepae(): User
    {
        $user = User::factory()->create();
        $user->assignRole('diretor');
        $user->cargos()->attach(Cargo::where('slug', Cargo::SLUG_DIRETOR_DEPAE)->value('id'));

        return $user->fresh();
    }

    /** D3 — conflito de interesse é permitido: o mesmo usuário é médium E diretor do DEPAE (molde Aury/Charles). */
    private function mediumEDiretorDepae(): User
    {
        $user = User::factory()->create();
        $user->assignRole('diretor');
        $user->cargos()->attach(Cargo::where('slug', Cargo::SLUG_DIRETOR_DEPAE)->value('id'));
        $user->setores()->attach(Setor::where('slug', Setor::SLUG_MEDIUM)->value('id'), ['funcao' => 'membro']);

        return $user->fresh();
    }

    /** I8: publicar grava status/publicado_por_id/publicado_em; medium_id (autoria) fica intacto. */
    public function test_i8_publicar_grava_status_publicado_por_e_publicado_em_preserva_medium_id(): void
    {
        $curador = $this->diretorDepae();
        $medium = User::factory()->create();
        $pendente = Mensagem::factory()->pendente()->create(['medium_id' => $medium->id]);

        Livewire::actingAs($curador)->test(CuradoriaConta::class)
            ->call('editar', $pendente->id)
            ->fillForm(['nivel' => VisibilidadeMensagem::Publico->value])
            ->call('publicar', $pendente->id)
            ->assertHasNoFormErrors();

        $pendente->refresh();
        $this->assertSame(Mensagem::STATUS_PUBLICADO, $pendente->status);
        $this->assertSame($curador->id, $pendente->publicado_por_id);
        $this->assertNotNull($pendente->publicado_em);
        $this->assertSame($medium->id, $pendente->medium_id);
    }

    /**
     * I9 (VERMELHO): nível NUNCA tocado (fica null, como veio do `editar()`) ⇒ recusa e continua
     * pendente. É também o caso da pendente LEGADA (medium_id null — 47 casos reais no dev), pois
     * a factory não fixa medium_id.
     */
    public function test_i9_publicar_com_nivel_null_recusa_e_continua_pendente(): void
    {
        $curador = $this->diretorDepae();
        $pendente = Mensagem::factory()->pendente()->create(['titulo' => 'Título original']);

        Livewire::actingAs($curador)->test(CuradoriaConta::class)
            ->call('editar', $pendente->id)
            ->call('publicar', $pendente->id)
            ->assertHasFormErrors(['nivel']);

        $pendente->refresh();
        $this->assertSame(Mensagem::STATUS_PENDENTE, $pendente->status);
        $this->assertNull($pendente->publicado_por_id);
        $this->assertSame('Título original', $pendente->titulo);
    }

    /** I9: nível string vazia (equivalente a null na RegraPublicacao) ⇒ recusa. */
    public function test_i9_publicar_com_nivel_vazio_recusa_e_continua_pendente(): void
    {
        $curador = $this->diretorDepae();
        $pendente = Mensagem::factory()->pendente()->create();

        Livewire::actingAs($curador)->test(CuradoriaConta::class)
            ->call('editar', $pendente->id)
            ->set('data.nivel', '')
            ->call('publicar', $pendente->id)
            ->assertHasFormErrors(['nivel']);

        $this->assertSame(Mensagem::STATUS_PENDENTE, $pendente->fresh()->status);
    }

    /** I9: slug fora do enum ⇒ recusa (também bloqueado nativamente pelo Rule::in do Select). */
    public function test_i9_publicar_com_nivel_invalido_recusa_e_continua_pendente(): void
    {
        $curador = $this->diretorDepae();
        $pendente = Mensagem::factory()->pendente()->create();

        Livewire::actingAs($curador)->test(CuradoriaConta::class)
            ->call('editar', $pendente->id)
            ->set('data.nivel', 'lixo-invalido')
            ->call('publicar', $pendente->id)
            ->assertHasFormErrors(['nivel']);

        $this->assertSame(Mensagem::STATUS_PENDENTE, $pendente->fresh()->status);
    }

    /**
     * I9 — a asserção que morde o "getState dentro da transação": pendente com autor A; troca para
     * autor B e nível null (recusado) ⇒ o pivô de autores tem de continuar em A. Com o `getState()`
     * fora da `DB::transaction`, o `saveRelationships()` já teria gravado B antes da recusa.
     */
    public function test_i9_recusa_nao_deixa_pivo_de_autores_meio_gravado(): void
    {
        $curador = $this->diretorDepae();
        $autorA = AutorEspiritual::factory()->create();
        $autorB = AutorEspiritual::factory()->create();

        $pendente = Mensagem::factory()->pendente()->create();
        $pendente->autores()->sync([$autorA->id]);

        Livewire::actingAs($curador)->test(CuradoriaConta::class)
            ->call('editar', $pendente->id)
            ->fillForm(['autores' => [$autorB->id], 'nivel' => null])
            ->call('publicar', $pendente->id)
            ->assertHasFormErrors(['nivel']);

        $pendente->refresh();
        $this->assertSame(Mensagem::STATUS_PENDENTE, $pendente->status);
        $this->assertSame([$autorA->id], $pendente->autores->pluck('id')->all());
    }

    /** I9-direcionada: publicar `direcionada` sem nenhum destinatário ⇒ recusa, continua pendente. */
    public function test_i9_direcionada_sem_destinatario_recusa(): void
    {
        $curador = $this->diretorDepae();
        $pendente = Mensagem::factory()->pendente()->create();

        Livewire::actingAs($curador)->test(CuradoriaConta::class)
            ->call('editar', $pendente->id)
            ->fillForm(['nivel' => VisibilidadeMensagem::Direcionada->value])
            ->call('publicar', $pendente->id)
            ->assertHasFormErrors();

        $this->assertSame(Mensagem::STATUS_PENDENTE, $pendente->fresh()->status);
    }

    /**
     * I10 (sem ambiguidade — a mensagem tem de estar pendente, senão bateria no 403 da
     * `editarNaCuradoria` por outro motivo): direcionada com 2 destinatários, publicada sob outro
     * nível ⇒ o pivô esvazia (o guard de `SincronizadorDestinatarios::filtrarPorNivel`).
     */
    public function test_i10_trocar_nivel_de_direcionada_para_outro_ao_publicar_esvazia_pivo(): void
    {
        $curador = $this->diretorDepae();
        $u1 = User::factory()->create();
        $u2 = User::factory()->create();

        $pendente = Mensagem::factory()->pendente()->comNivel(VisibilidadeMensagem::Direcionada)->create();
        $pendente->destinatarios()->sync([$u1->id, $u2->id]);

        Livewire::actingAs($curador)->test(CuradoriaConta::class)
            ->call('editar', $pendente->id)
            ->fillForm(['nivel' => VisibilidadeMensagem::Trabalhadores->value])
            ->call('publicar', $pendente->id)
            ->assertHasNoFormErrors();

        $pendente->refresh();
        $this->assertSame(Mensagem::STATUS_PUBLICADO, $pendente->status);
        $this->assertSame(0, $pendente->destinatarios()->count());
    }

    /**
     * Fix pós-revisão (Important — id ≠ editandoId): `publicar($id)` usava DOIS registros
     * diferentes — `$registro` (do `$id` do cliente) para fill/save, mas `$this->form->getState()`
     * (que dispara `saveRelationships()` de autores/pictografia) opera sobre o modelo ANCORADO em
     * `$this->editandoId` (P1, `editar()`). `publicar` é um método público Livewire: nada impede um
     * curador autenticado de chamá-lo com um `$id` diferente do `editandoId` corrente, fora da UI.
     * Prova: abre `editar(A)` (schema ancora em A) e chama `publicar(B)` — sem o guard, B seria
     * publicada com título/nível de A e o pivô de autores de A seria alterado como efeito colateral.
     */
    public function test_publicar_com_id_diferente_do_editandoid_e_recusado_e_nada_muda(): void
    {
        $curador = $this->diretorDepae();
        $autorA = AutorEspiritual::factory()->create();

        $pendenteA = Mensagem::factory()->pendente()->create(['titulo' => 'Título A']);
        $pendenteA->autores()->sync([$autorA->id]);

        $pendenteB = Mensagem::factory()->pendente()->create(['titulo' => 'Título B']);

        Livewire::actingAs($curador)->test(CuradoriaConta::class)
            ->call('editar', $pendenteA->id)
            ->fillForm(['nivel' => VisibilidadeMensagem::Publico->value])
            ->call('publicar', $pendenteB->id)
            ->assertForbidden();

        $pendenteA->refresh();
        $pendenteB->refresh();

        $this->assertSame(Mensagem::STATUS_PENDENTE, $pendenteB->status);
        $this->assertSame('Título B', $pendenteB->titulo);
        $this->assertSame(Mensagem::STATUS_PENDENTE, $pendenteA->status);
        $this->assertSame('Título A', $pendenteA->titulo);
        $this->assertSame([$autorA->id], $pendenteA->autores->pluck('id')->all());
    }

    /**
     * I13 (D3 — conflito de interesse é PERMITIDO): médium E diretor do DEPAE, não-admin,
     * não-presidente, publica a PRÓPRIA mensagem ⇒ permitido; a trilha registra o causer.
     */
    public function test_i13_medium_e_diretor_depae_publica_a_propria_mensagem(): void
    {
        $curador = $this->mediumEDiretorDepae();
        $this->assertFalse($curador->hasRole('administrador'));
        $this->assertFalse($curador->ehPresidente());

        $pendente = Mensagem::factory()->pendente()->create(['medium_id' => $curador->id]);
        Activity::query()->delete(); // ignora o 'created' do factory

        Livewire::actingAs($curador)->test(CuradoriaConta::class)
            ->call('editar', $pendente->id)
            ->fillForm(['nivel' => VisibilidadeMensagem::Publico->value])
            ->call('publicar', $pendente->id)
            ->assertHasNoFormErrors();

        $pendente->refresh();
        $this->assertSame(Mensagem::STATUS_PUBLICADO, $pendente->status);
        $this->assertSame($curador->id, $pendente->publicado_por_id);

        $atividade = Activity::where('log_name', 'mensagem')->where('event', 'updated')->latest('id')->first();
        $this->assertNotNull($atividade);
        $this->assertSame($curador->id, $atividade->causer_id);
    }
}
