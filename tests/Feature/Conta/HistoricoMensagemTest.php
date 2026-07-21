<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-21

namespace Tests\Feature\Conta;

use App\Enums\VisibilidadeMensagem;
use App\Livewire\Conta\CuradoriaConta;
use App\Models\Cargo;
use App\Models\Mensagem;
use App\Models\User;
use App\Support\Autorizacao\AuditoriaAutorizacao;
use App\Support\Mensagens\HistoricoMensagem;
use Database\Seeders\EstruturaCemaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * Primeiro leitor de `activity_log` do projeto (Fatia F4b, Task 11): `HistoricoMensagem` +
 * `x-conta.historico-mensagem`. A redação da Task 1 já limpa corpo/contexto NA ESCRITA — por
 * isso as asserções negativas do renderizador precisam de uma linha SUJA injetada à mão
 * (`withProperties` direto), senão seriam vacuosas.
 */
class HistoricoMensagemTest extends TestCase
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

    // ---- I15: renderizador ISOLADO, com linha suja injetada à mão ----------------------------

    public function test_i15_renderizador_isolado_nao_vaza_valor_nem_contexto_tecnico(): void
    {
        $m = Mensagem::factory()->create();
        Activity::query()->delete(); // ignora o 'created' do factory

        activity()->useLog('mensagem')->performedOn($m)
            ->withProperties([
                'attributes' => ['corpo' => 'SENTINELA-VAZAMENTO-XYZ', 'titulo' => 'T'],
                'old' => ['corpo' => 'SENTINELA-ANTIGO-XYZ'],
                'porta' => 'perfil', 'ip' => '127.0.0.1', 'user_agent' => 'Symfony',
            ])
            ->event('updated')->log('mensagem atualizada');

        $view = $this->blade('<x-conta.historico-mensagem :mensagem="$m" />', ['m' => $m]);

        $view->assertSee('Corpo da mensagem')
            ->assertDontSee('SENTINELA-VAZAMENTO-XYZ')->assertDontSee('SENTINELA-ANTIGO-XYZ')
            ->assertDontSee('user_agent', false)->assertDontSee('attributes', false);
    }

    /** Entrada manual (event null, chave 'diff') — o nome dentro do diff pode ser PII; nunca aparece. */
    public function test_i15_entrada_manual_com_diff_nao_vaza_nome_sigiloso_no_componente(): void
    {
        $m = Mensagem::factory()->create();
        Activity::query()->delete();

        activity()->useLog('mensagem')->performedOn($m)
            ->withProperties(['diff' => ['adicionados' => ['Fulano Sigiloso']]])
            ->log('destinatários alterados');

        $view = $this->blade('<x-conta.historico-mensagem :mensagem="$m" />', ['m' => $m]);

        $view->assertDontSee('Fulano Sigiloso');
    }

    // ---- Unidade do leitor -------------------------------------------------------------------

    public function test_created_nao_tem_old_e_nao_quebra(): void
    {
        $m = Mensagem::factory()->create(['titulo' => 'Original']);

        $linhas = HistoricoMensagem::linhas($m);

        $this->assertNotEmpty($linhas);
        $this->assertContains('Título', $linhas[0]['campos']);
    }

    public function test_updated_uniao_de_chaves_de_attributes_e_old(): void
    {
        $m = Mensagem::factory()->create(['titulo' => 'Original', 'casa' => 'CEMA']);
        Activity::query()->delete();

        $m->update(['titulo' => 'Novo']); // logOnlyDirty: só 'titulo' muda

        $linhas = HistoricoMensagem::linhas($m);

        $this->assertSame(['Título'], $linhas[0]['campos']);
    }

    public function test_campo_fora_da_lista_branca_nao_aparece(): void
    {
        $m = Mensagem::factory()->create();
        Activity::query()->delete();

        activity()->useLog('mensagem')->performedOn($m)
            ->withProperties(['attributes' => ['campo_fantasma' => 'x', 'titulo' => 'Y']])
            ->event('updated')->log('mensagem atualizada');

        $linhas = HistoricoMensagem::linhas($m);

        $this->assertSame(['Título'], $linhas[0]['campos']);
    }

    public function test_entrada_manual_nao_quebra_o_leitor(): void
    {
        $m = Mensagem::factory()->create();
        Activity::query()->delete();

        activity()->useLog('mensagem')->performedOn($m)
            ->withProperties(['diff' => ['adicionados' => ['Fulano Sigiloso']]] + AuditoriaAutorizacao::contexto())
            ->log('destinatários alterados');

        $linhas = HistoricoMensagem::linhas($m);

        $this->assertCount(1, $linhas);
        $this->assertSame([], $linhas[0]['campos']);
        $this->assertSame('destinatários alterados', $linhas[0]['descricao']);
    }

    public function test_causer_null_mostra_sistema(): void
    {
        $m = Mensagem::factory()->create();
        Activity::query()->delete();

        $m->update(['titulo' => 'Alterada sem usuário autenticado']); // sem actingAs => causer null

        $linhas = HistoricoMensagem::linhas($m);

        $this->assertSame('Sistema', $linhas[0]['quem']);
    }

    /** O diff mostrando status indo para 'publicado' rotula a linha como "publicada". */
    public function test_publicar_rotula_a_linha_como_publicada(): void
    {
        $curador = $this->diretorDepae();
        $pendente = Mensagem::factory()->pendente()->create();
        Activity::query()->delete();

        Livewire::actingAs($curador)->test(CuradoriaConta::class)
            ->call('editar', $pendente->id)
            ->fillForm(['nivel' => VisibilidadeMensagem::Publico->value])
            ->call('publicar', $pendente->id)
            ->assertHasNoFormErrors();

        $linhas = HistoricoMensagem::linhas($pendente->fresh());

        $this->assertSame('publicada', $linhas[0]['descricao']);
    }

    // ---- B3: morph com id FORÇADO --------------------------------------------------------------

    public function test_b3_activity_de_outro_subject_type_com_mesmo_id_e_ignorada(): void
    {
        $medium = User::factory()->create();
        $m = Mensagem::factory()->create(['medium_id' => $medium->id]);
        Activity::query()->delete();

        DB::table('activity_log')->insert([
            'log_name' => 'mensagem',
            'description' => 'forjada',
            'subject_type' => (new User)->getMorphClass(),
            'subject_id' => $m->id,
            'causer_type' => (new User)->getMorphClass(),
            'causer_id' => $medium->id,
            'event' => 'updated',
            'properties' => '{}',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame([], HistoricoMensagem::linhas($m));
        $this->assertSame([], HistoricoMensagem::editadasPeloAutor(collect([$m])));
    }

    // ---- I16: editadasPeloAutor -----------------------------------------------------------------

    public function test_i16_editada_pelo_autor_marca_so_updated_do_proprio_medium(): void
    {
        $medium = User::factory()->create();
        $curador = User::factory()->create();

        $recemCriada = Mensagem::factory()->create(['medium_id' => $medium->id]); // só 'created'
        $editadaPeloCurador = Mensagem::factory()->create(['medium_id' => $medium->id]);
        $editadaPeloAutor = Mensagem::factory()->create(['medium_id' => $medium->id]);
        $legada = Mensagem::factory()->create(['medium_id' => null]);

        $this->actingAs($curador);
        $editadaPeloCurador->update(['titulo' => 'Curador mexeu']);

        $this->actingAs($medium);
        $editadaPeloAutor->update(['titulo' => 'Autor mexeu']);
        $legada->update(['titulo' => 'Legada mexida']); // causer=medium, mas medium_id é null

        $ids = HistoricoMensagem::editadasPeloAutor(collect([
            $recemCriada, $editadaPeloCurador, $editadaPeloAutor, $legada,
        ]));

        $this->assertSame([$editadaPeloAutor->id], $ids);
    }

    // ---- I24: PII de destinatários nunca vaza na trilha 'mensagem' ------------------------------

    public function test_i24_direcionada_com_destinatarios_nao_vaza_pii_na_trilha(): void
    {
        $curador = $this->diretorDepae();
        $sentinela = User::factory()->create(['name' => 'Fulano Sigiloso-PII']);
        $outro = User::factory()->create();

        $pendente = Mensagem::factory()->pendente()->create();

        Livewire::actingAs($curador)->test(CuradoriaConta::class)
            ->call('editar', $pendente->id)
            ->fillForm([
                'nivel' => VisibilidadeMensagem::Direcionada->value,
                'destinatarios' => [$sentinela->id, $outro->id],
            ])
            ->call('publicar', $pendente->id)
            ->assertHasNoFormErrors();

        $this->assertSame(2, $pendente->fresh()->destinatarios()->count());

        $json = Activity::where('log_name', 'mensagem')->get()->toJson();

        $this->assertStringNotContainsString('Fulano Sigiloso-PII', $json);
        $this->assertStringNotContainsString('destinatario', $json);
        $this->assertStringNotContainsString('medium_id', $json);
        $this->assertStringNotContainsString('publicado_por_id', $json);
    }

    // ---- R3: aviso de corte --------------------------------------------------------------------

    public function test_r3_com_21_entradas_avisa_que_mostra_as_20_mais_recentes(): void
    {
        $m = Mensagem::factory()->create();
        Activity::query()->delete();

        for ($i = 0; $i < 21; $i++) {
            activity()->useLog('mensagem')->performedOn($m)
                ->withProperties(['attributes' => ['titulo' => "Versão {$i}"]])
                ->event('updated')->log('mensagem atualizada');
        }

        $view = $this->blade('<x-conta.historico-mensagem :mensagem="$m" />', ['m' => $m]);

        $view->assertSee('Mostrando as 20 mais recentes');
    }

    public function test_com_poucas_entradas_nao_avisa_corte(): void
    {
        $m = Mensagem::factory()->create(); // só o 'created'

        $view = $this->blade('<x-conta.historico-mensagem :mensagem="$m" />', ['m' => $m]);

        $view->assertDontSee('mais recentes');
    }
}
