<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-21

namespace Tests\Feature\Conta;

use App\Enums\VisibilidadeMensagem;
use App\Livewire\Conta\CuradoriaConta;
use App\Livewire\Conta\MensagensConta;
use App\Models\AutorEspiritual;
use App\Models\Cargo;
use App\Models\Mensagem;
use App\Models\Setor;
use App\Models\User;
use Database\Seeders\EstruturaCemaSeeder;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Fila e Salvar da CURADORIA em /minha-conta/curadoria (Fatia F4b, Task 9). O botão Publicar (o
 * martelo) é da Task 10 e não existe ainda — `salvar()` NUNCA muda o status. NUNCA usar
 * actingAsAdmin(): o Gate::before do admin passaria em tudo e mascararia o eixo de AUTORIA sob
 * teste (além de o admin puro ser, por desenho, 403 aqui — ver AbaCuradoriaTest).
 */
class CuradoriaContaTest extends TestCase
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

    private function medium(): User
    {
        $user = User::factory()->create();
        $user->assignRole('trabalhador');
        $user->setores()->attach(Setor::where('slug', Setor::SLUG_MEDIUM)->value('id'), ['funcao' => 'membro']);

        return $user->fresh();
    }

    /** FILA: só PENDENTES entram — sem o where('status', ...), a fila listaria também as publicadas. */
    public function test_fila_mostra_so_as_pendentes(): void
    {
        Mensagem::factory()->publicada()->comNivel('publico')->create(['titulo' => 'JA-PUBLICADA-NAO-ENTRA']);
        Mensagem::factory()->pendente()->create(['titulo' => 'NA-FILA']);

        Livewire::actingAs($this->diretorDepae())->test(CuradoriaConta::class)
            ->assertSee('NA-FILA')
            ->assertDontSee('JA-PUBLICADA-NAO-ENTRA');
    }

    /**
     * I25 (fila): pendente do legado (medium_id/nivel nulos, 47 casos reais no dev) não derruba a
     * página — protege também contra um VisibilidadeMensagem::from() acidental na fila, que
     * estouraria com nível null. Pendente de um médium mostra o nome de quem lançou.
     */
    public function test_fila_rotula_pendente_do_legado_e_mostra_o_nome_do_medium(): void
    {
        $medium = $this->medium();
        Mensagem::factory()->pendente()->create(['medium_id' => null, 'nivel' => null, 'titulo' => 'DO-LEGADO']);
        Mensagem::factory()->pendente()->create(['medium_id' => $medium->id, 'titulo' => 'DO-MEDIUM']);

        $this->actingAs($this->diretorDepae())->get(route('conta.curadoria'))
            ->assertOk()
            ->assertSee('Importada do legado')
            ->assertSee($medium->name);
    }

    /**
     * V4 — o furo B4: uma implementação que autorize com `curar` (sem objeto) deixaria o curador
     * abrir/editar uma mensagem JÁ PUBLICADA, porque o id vem do cliente. `editar()` autoriza com
     * `editarNaCuradoria` (com o registro), que exige status pendente.
     *
     * O botão Publicar (Task 10) ainda não existe neste componente — não testado aqui.
     */
    public function test_v4_curador_nao_edita_mensagem_ja_publicada(): void
    {
        $publicada = Mensagem::factory()->publicada()->create();

        Livewire::actingAs($this->diretorDepae())->test(CuradoriaConta::class)
            ->call('editar', $publicada->id)
            ->assertForbidden();
    }

    /**
     * I11 — trava de contrato (NÃO é prova da reasserção do servidor): o Select `status` não existe
     * em schemaCuradoria, então a chave forjada em `data.status` é PODADA pelo getState() antes de
     * chegar a qualquer lógica de salvar() — nunca sobrevive para ser reasserida ou não. A prova é
     * de que o schema em si não expõe esse campo, não de um `unset` no servidor.
     */
    public function test_i11_forjar_status_no_estado_nao_publica_prova_a_poda_do_getstate(): void
    {
        $pendente = Mensagem::factory()->pendente()->create();

        Livewire::actingAs($this->diretorDepae())->test(CuradoriaConta::class)
            ->call('editar', $pendente->id)
            ->set('data.status', Mensagem::STATUS_PUBLICADO)
            ->call('salvar')
            ->assertHasNoFormErrors();

        $this->assertSame(Mensagem::STATUS_PENDENTE, $pendente->fresh()->status);
    }

    /** I12: altera título+corpo+nível e salva ⇒ persistido e a mensagem continua pendente. */
    public function test_i12_altera_titulo_corpo_e_nivel_e_salva_persiste_e_continua_pendente(): void
    {
        $pendente = Mensagem::factory()->pendente()->create([
            'titulo' => 'Original',
            'corpo' => '<p>Corpo original.</p>',
            'nivel' => null,
        ]);

        Livewire::actingAs($this->diretorDepae())->test(CuradoriaConta::class)
            ->call('editar', $pendente->id)
            ->fillForm([
                'titulo' => 'Título Curado',
                'corpo' => '<p>SENTINELA-CORPO-CURADO</p>',
                'nivel' => VisibilidadeMensagem::Diretores->value,
            ])
            ->call('salvar')
            ->assertHasNoFormErrors();

        $pendente->refresh();
        $this->assertSame('Título Curado', $pendente->titulo);
        $this->assertStringContainsString('SENTINELA-CORPO-CURADO', $pendente->corpo);
        $this->assertSame(VisibilidadeMensagem::Diretores->value, $pendente->nivel);
        $this->assertSame(Mensagem::STATUS_PENDENTE, $pendente->status);
    }

    /**
     * Mesma armadilha do P1/M2 da Task 8 (o próprio relatório da 8 avisa que "provavelmente se
     * repete" aqui): editar SÓ o título de uma direcionada pendente não pode esvaziar o pivô de
     * destinatários nem o de autores — ambos são virtuais/dehydrated(false), fora do
     * `attributesToArray()`.
     */
    public function test_editar_so_o_titulo_preserva_destinatarios_e_autores(): void
    {
        $u1 = User::factory()->create();
        $u2 = User::factory()->create();
        $autor = AutorEspiritual::factory()->create();

        $pendente = Mensagem::factory()->pendente()->comNivel(VisibilidadeMensagem::Direcionada)->create([
            'titulo' => 'Título original',
        ]);
        $pendente->destinatarios()->sync([$u1->id, $u2->id]);
        $pendente->autores()->sync([$autor->id]);

        Livewire::actingAs($this->diretorDepae())->test(CuradoriaConta::class)
            ->call('editar', $pendente->id)
            ->fillForm(['titulo' => 'Novo'])
            ->call('salvar')
            ->assertHasNoFormErrors();

        $pendente->refresh();
        $this->assertSame('Novo', $pendente->titulo);
        $this->assertSame(
            [$u1->id, $u2->id],
            $pendente->destinatarios()->pluck('users.id')->sort()->values()->all()
        );
        $this->assertSame(1, DB::table('mensagem_autor_espiritual')->where('mensagem_id', $pendente->id)->count());
        $this->assertSame(VisibilidadeMensagem::Direcionada->value, $pendente->nivel);
        $this->assertSame(Mensagem::STATUS_PENDENTE, $pendente->status);
    }

    /** I12 (contrato de ausência): sem excluir/despublicar/devolver — nem aqui, nem no molde do médium. */
    public function test_metodos_de_estado_destrutivo_nao_existem(): void
    {
        foreach (['excluir', 'despublicar', 'devolver'] as $metodo) {
            $this->assertFalse(method_exists(CuradoriaConta::class, $metodo));
            $this->assertFalse(method_exists(MensagensConta::class, $metodo));
        }
    }

    /** I12 (contrato): todas as rotas do grupo conta.* são só GET (nenhum POST/PUT/PATCH/DELETE). */
    public function test_todas_as_rotas_conta_sao_somente_get(): void
    {
        $metodos = collect(Route::getRoutes())
            ->filter(fn ($rota) => str_starts_with((string) $rota->getName(), 'conta.'))
            ->flatMap(fn ($rota) => $rota->methods());

        $this->assertNotEmpty($metodos);
        $this->assertEmpty($metodos->diff(['GET', 'HEAD']));
    }

    /** I25/§8-32: o required condicional do /admin não pode vazar para a curadoria. */
    public function test_i25_nivel_da_curadoria_continua_nao_required(): void
    {
        $pendente = Mensagem::factory()->pendente()->create();

        Livewire::actingAs($this->diretorDepae())->test(CuradoriaConta::class)
            ->call('editar', $pendente->id)
            ->assertFormFieldExists('nivel', fn (Select $f): bool => ! $f->isRequired());
    }

    /** I23: o Select `nivel` da curadoria tem as 6 opções do enum, incluindo 'diretor-depae'. */
    public function test_i23_select_nivel_tem_as_6_opcoes_incluindo_diretor_depae(): void
    {
        $pendente = Mensagem::factory()->pendente()->create();

        Livewire::actingAs($this->diretorDepae())->test(CuradoriaConta::class)
            ->call('editar', $pendente->id)
            ->assertFormFieldExists(
                'nivel',
                fn (Select $campo): bool => count($campo->getOptions()) === 6
                    && array_key_exists('diretor-depae', $campo->getOptions())
            );
    }

    /** I11: o resumo é texto de quem CURA — existe no schemaCuradoria (e não no form do médium). */
    public function test_i11_form_da_curadoria_tem_o_campo_resumo(): void
    {
        $pendente = Mensagem::factory()->pendente()->create();

        Livewire::actingAs($this->diretorDepae())->test(CuradoriaConta::class)
            ->call('editar', $pendente->id)
            ->assertFormFieldExists('resumo');
    }

    /**
     * I18 (V1) — sobre o ESTADO, não sobre o HTML: assertDontSee do Livewire apaga o wire:snapshot
     * antes de comparar, então passaria com ou sem o $hidden do model. A prova real é no array `data`.
     */
    public function test_i18_campos_privilegiados_nao_entram_no_estado_do_form(): void
    {
        $pendente = Mensagem::factory()->pendente()->create();

        $c = Livewire::actingAs($this->diretorDepae())->test(CuradoriaConta::class)->call('editar', $pendente->id);

        $this->assertArrayNotHasKey('medium_id', $c->get('data'));
        $this->assertArrayNotHasKey('publicado_por_id', $c->get('data'));
        $this->assertArrayNotHasKey('publicado_em', $c->get('data'));
    }

    /**
     * Achado do review final (Minor 4 — defesa em profundidade): `atualizarRegistro()`/`publicar()`
     * não faziam o `unset()` explícito de `medium_id`/`publicado_por_id`/`publicado_em` que
     * `MensagensConta` faz — seguro hoje (o `getState()` já poda, e os três não são `$fillable`),
     * mas o `DATA-MODEL.md` afirma que AMBOS os componentes fazem o `unset`. Trava o invariante:
     * mesmo que um forjado sobreviva no array algum dia, `salvar()`/`publicar()` nunca gravam.
     */
    public function test_review_forjar_campos_privilegiados_no_salvar_nao_tem_efeito(): void
    {
        $curador = $this->diretorDepae();
        $medium = User::factory()->create();
        $outroCurador = User::factory()->create();
        $pendente = Mensagem::factory()->pendente()->create(['medium_id' => $medium->id]);

        Livewire::actingAs($curador)->test(CuradoriaConta::class)
            ->call('editar', $pendente->id)
            ->set('data.medium_id', $outroCurador->id)
            ->set('data.publicado_por_id', $outroCurador->id)
            ->set('data.publicado_em', now()->subYear())
            ->fillForm(['titulo' => 'Novo título'])
            ->call('salvar')
            ->assertHasNoFormErrors();

        $pendente->refresh();
        $this->assertSame($medium->id, $pendente->medium_id);
        $this->assertNull($pendente->publicado_por_id);
        $this->assertNull($pendente->publicado_em);
    }

    /**
     * Achado do review final (Minor 6 — A11y): "Editada pelo autor após o lançamento" é o sinal
     * FUNCIONAL mais importante da fila, mas usava `text-orange` (#e79048) em `text-xs` sobre
     * branco — ~2,2:1, reprova WCAG AA (mínimo 4,5:1). Troca para um token de texto com contraste
     * adequado, mantendo o destaque visual (peso), não a cor fraca.
     */
    public function test_review_aviso_editada_pelo_autor_usa_token_de_contraste_adequado(): void
    {
        $medium = $this->medium();
        $pendente = Mensagem::factory()->pendente()->create(['medium_id' => $medium->id]);

        $this->actingAs($medium);
        $pendente->update(['titulo' => 'Editado pelo próprio autor após o lançamento']);

        $html = $this->actingAs($this->diretorDepae())->get(route('conta.curadoria'))->getContent();

        $this->assertStringContainsString('text-danger">Editada pelo autor após o lançamento', $html);
        $this->assertStringNotContainsString('text-orange', $html);
    }

    /**
     * Achado do review final (Minor 7 — performance): `render()` sempre reconsulta a fila inteira
     * de pendentes (`where status = pendente`), mesmo com a fila ESCONDIDA pelo `@if
     * ($mostrandoForm)` — recomputada a cada `->live()` do form (47 pendentes no dev). Com o form
     * aberto, a query da fila não deveria rodar.
     */
    public function test_review_render_pula_query_da_fila_quando_mostrando_form(): void
    {
        Mensagem::factory()->pendente()->count(2)->create();
        $pendente = Mensagem::factory()->pendente()->create();

        $consultasFila = 0;
        DB::listen(function ($query) use (&$consultasFila): void {
            if (str_contains($query->sql, '`mensagens`')
                && str_contains($query->sql, '`status`')
                && str_contains($query->sql, '`data_recebimento`')) {
                $consultasFila++;
            }
        });

        Livewire::actingAs($this->diretorDepae())->test(CuradoriaConta::class)
            ->call('editar', $pendente->id);

        $this->assertSame(0, $consultasFila, 'a query da fila rodou com o form aberto (mostrandoForm=true)');
    }

    /**
     * Achado do review final (Minor 8 — cobertura): o componente `x-conta.historico-mensagem` só
     * era testado ISOLADO (HistoricoMensagemTest); o ramo `$mostrandoForm = true` da view da
     * curadoria — que é quem de fato inclui o componente — nunca era exercitado por um teste do
     * PRÓPRIO componente `CuradoriaConta`.
     */
    public function test_review_form_aberto_mostra_a_secao_historico(): void
    {
        $pendente = Mensagem::factory()->pendente()->create();

        Livewire::actingAs($this->diretorDepae())->test(CuradoriaConta::class)
            ->call('editar', $pendente->id)
            ->assertSee('Histórico');
    }
}
