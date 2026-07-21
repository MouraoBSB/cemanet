<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-21

namespace Tests\Feature\Conta;

use App\Enums\VisibilidadeMensagem;
use App\Livewire\Conta\MensagensConta;
use App\Models\AutorEspiritual;
use App\Models\Mensagem;
use App\Models\Setor;
use App\Models\User;
use Database\Seeders\EstruturaCemaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Edição de Mensagem pelo médium em /minha-conta/mensagens (Fatia F4b, Task 8, P1 + M2). Enquanto
 * PENDENTE, o próprio médium dono pode editar; após publicada, a posse passa ao curador e a aba
 * deixa de exibir o corpo ou linkar para a página pública (D10). NUNCA usar actingAsAdmin(): o
 * Gate::before do admin passaria em tudo e mascararia o eixo de AUTORIA sob teste.
 */
class MensagensContaEditarTest extends TestCase
{
    use RefreshDatabase;

    /** PNG 1x1 mínimo (evita GD real sob carga — flaky conhecido do blog). */
    private const PNG_1X1 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAAC0lEQVQImWNgAAIAAAUAAWJVMogAAAAASUVORK5CYII=';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstruturaCemaSeeder::class);
        Storage::fake('public');
    }

    private function medium(): User
    {
        $user = User::factory()->create();
        $user->assignRole('trabalhador');
        $user->setores()->attach(Setor::where('slug', Setor::SLUG_MEDIUM)->value('id'), ['funcao' => 'membro']);

        return $user->fresh();
    }

    /**
     * I6/I5b — VERMELHO do M2 e do P1: pendente do médium com nivel='direcionada' + 2 destinatários +
     * 1 autor + 1 mídia. Editar SÓ o título não pode esvaziar nada disso.
     */
    public function test_editar_so_o_titulo_preserva_destinatarios_autor_midia_e_nivel(): void
    {
        $medium = $this->medium();
        $u1 = User::factory()->create();
        $u2 = User::factory()->create();
        $autor = AutorEspiritual::factory()->create();

        $m = Mensagem::factory()->pendente()->comNivel(VisibilidadeMensagem::Direcionada)->create([
            'medium_id' => $medium->id,
            'titulo' => 'Título original',
        ]);
        $m->destinatarios()->sync([$u1->id, $u2->id]);
        $m->autores()->sync([$autor->id]);
        $m->addMediaFromString(base64_decode(self::PNG_1X1))
            ->usingFileName('pict.png')
            ->toMediaCollection(Mensagem::COLECAO_PICTOGRAFIA);

        Livewire::actingAs($medium)->test(MensagensConta::class)
            ->call('editar', $m->id)
            ->fillForm(['titulo' => 'Novo'])
            ->call('salvar')
            ->assertHasNoFormErrors();

        $m->refresh();
        $this->assertSame('Novo', $m->titulo);
        $this->assertSame(
            [$u1->id, $u2->id],
            $m->destinatarios()->pluck('users.id')->sort()->values()->all()
        );
        $this->assertSame(1, DB::table('mensagem_autor_espiritual')->where('mensagem_id', $m->id)->count());
        $this->assertSame(1, $m->getMedia(Mensagem::COLECAO_PICTOGRAFIA)->count());
        $this->assertSame(VisibilidadeMensagem::Direcionada->value, $m->nivel);
    }

    /** I6 (negativos): dona-mas-publicada e pendente-de-outro-médium ⇒ 403. */
    public function test_editar_publicada_propria_ou_pendente_de_outro_medium_e_negado(): void
    {
        $medium = $this->medium();
        $outroMedium = $this->medium();

        $publicadaPropria = Mensagem::factory()->publicada()->create(['medium_id' => $medium->id]);
        $pendenteDeOutro = Mensagem::factory()->pendente()->create(['medium_id' => $outroMedium->id]);

        Livewire::actingAs($medium)->test(MensagensConta::class)
            ->call('editar', $publicadaPropria->id)
            ->assertForbidden();

        Livewire::actingAs($medium)->test(MensagensConta::class)
            ->call('editar', $pendenteDeOutro->id)
            ->assertForbidden();
    }

    /**
     * I26 (D10): médium trabalhador autor de uma PUBLICADA (nivel='diretores') — a aba responde 200,
     * mostra o título, mas NUNCA o corpo nem o link para a página pública (a posse passou ao curador).
     * A mesma mensagem, enquanto PENDENTE, mostra o corpo no form de edição.
     */
    public function test_i26_aba_esconde_corpo_e_link_de_publicada_mas_mostra_o_da_pendente(): void
    {
        $medium = $this->medium();

        $publicada = Mensagem::factory()->publicada()->comNivel(VisibilidadeMensagem::Diretores)->create([
            'medium_id' => $medium->id,
            'titulo' => 'Mensagem Publicada Título',
            'corpo' => '<p>SENTINELA-CORPO-PUBLICADA-XYZ</p>',
            'slug' => 'mensagem-publicada-titulo',
        ]);

        $response = $this->actingAs($medium)->get(route('conta.mensagens'));

        $response->assertOk();
        $response->assertSee('Mensagem Publicada Título');
        $response->assertDontSee('SENTINELA-CORPO-PUBLICADA-XYZ');
        $response->assertDontSee(route('mensagens.show', $publicada->slug), false);

        $pendente = Mensagem::factory()->pendente()->create([
            'medium_id' => $medium->id,
            'titulo' => 'Mensagem Pendente Título',
            'corpo' => '<p>SENTINELA-CORPO-PENDENTE-XYZ</p>',
        ]);

        // Estado, não HTML: o RichEditor (Tiptap) transmite o corpo via $wire.$entangle, dentro do
        // wire:snapshot, e o próprio hidrata `data.corpo` como JSON estruturado (não a string HTML
        // crua) — o assertSee tem $stripInitialData=true por padrão (mesma armadilha do
        // assertDontSee, V1) e nunca veria o texto, com ou sem o bug corrigido.
        $c = Livewire::actingAs($medium)->test(MensagensConta::class)->call('editar', $pendente->id);
        $this->assertStringContainsString('SENTINELA-CORPO-PENDENTE-XYZ', json_encode($c->get('data')['corpo']));
    }

    /**
     * I18 (V1) — sobre o ESTADO, não sobre o HTML: assertDontSee do Livewire apaga o wire:snapshot
     * antes de comparar, então passaria com ou sem o $hidden do model. A prova real é no array `data`.
     */
    public function test_i18_campos_privilegiados_nao_entram_no_estado_do_form(): void
    {
        $medium = $this->medium();
        $m = Mensagem::factory()->pendente()->create(['medium_id' => $medium->id]);

        $c = Livewire::actingAs($medium)->test(MensagensConta::class)->call('editar', $m->id);

        $this->assertArrayNotHasKey('medium_id', $c->get('data'));
        $this->assertArrayNotHasKey('publicado_por_id', $c->get('data'));
        $this->assertArrayNotHasKey('publicado_em', $c->get('data'));
    }

    /**
     * Achado do review final (Important 1): `atualizarRegistro()` reescrevia `nivel = null` sempre
     * que `direcionar` chegasse `false` — o que é o caso de TODO nível da escada (`trabalhadores`,
     * `diretores`...), já que `editar()` só hidrata `direcionar = true` para `direcionada`. Cenário
     * real: o curador classifica a pendente como `trabalhadores` (sem publicar — o `salvar()` da
     * curadoria permite) e depois o médium corrige só o título ⇒ o nível arbitrado não pode sumir.
     */
    public function test_review_editar_so_o_titulo_preserva_nivel_da_escada_arbitrado_pelo_curador(): void
    {
        $medium = $this->medium();
        $m = Mensagem::factory()->pendente()->comNivel(VisibilidadeMensagem::Trabalhadores)->create([
            'medium_id' => $medium->id,
            'titulo' => 'Título original',
        ]);

        Livewire::actingAs($medium)->test(MensagensConta::class)
            ->call('editar', $m->id)
            ->fillForm(['titulo' => 'Novo'])
            ->call('salvar')
            ->assertHasNoFormErrors();

        $m->refresh();
        $this->assertSame('Novo', $m->titulo);
        $this->assertSame(VisibilidadeMensagem::Trabalhadores->value, $m->nivel);
    }

    /** Caso oposto do achado acima: direcionada + desmarcar o toggle ⇒ nível vira null e o pivô esvazia. */
    public function test_review_editar_desmarcando_direcionar_limpa_nivel_e_esvazia_pivo(): void
    {
        $medium = $this->medium();
        $u1 = User::factory()->create();

        $m = Mensagem::factory()->pendente()->comNivel(VisibilidadeMensagem::Direcionada)->create([
            'medium_id' => $medium->id,
        ]);
        $m->destinatarios()->sync([$u1->id]);

        Livewire::actingAs($medium)->test(MensagensConta::class)
            ->call('editar', $m->id)
            ->fillForm(['direcionar' => false])
            ->call('salvar')
            ->assertHasNoFormErrors();

        $m->refresh();
        $this->assertNull($m->nivel);
        $this->assertSame(0, $m->destinatarios()->count());
    }

    /**
     * Achado do review final (Important 2a): `schemaAdmin` usa `User::orderBy('name')` (todos),
     * `blocoDestinatarios` usa `User::where('ativo', true)` — duas listas para o MESMO campo. O
     * `Select` injeta `Rule::in(options)`: um destinatário DESATIVADO DEPOIS de uma direcionada
     * existir carrega o id no `fill()` (vindo do pivô) mas ele não está mais nas options ⇒ o
     * médium fica sem saída para salvar até um simples ajuste de título. O ATIVO continua
     * preservado; o INATIVO é descartado pelo filtro de integridade de sempre (I7) — o que muda
     * é que o form deixa de EXPLODIR com "The selected destinatários is invalid.".
     */
    public function test_review_editar_com_destinatario_desativado_depois_salva_sem_erro(): void
    {
        $medium = $this->medium();
        $ativo = User::factory()->create();
        $desativadoDepois = User::factory()->create();

        $m = Mensagem::factory()->pendente()->comNivel(VisibilidadeMensagem::Direcionada)->create([
            'medium_id' => $medium->id,
            'titulo' => 'Título original',
        ]);
        $m->destinatarios()->sync([$ativo->id, $desativadoDepois->id]);
        $desativadoDepois->update(['ativo' => false]);

        Livewire::actingAs($medium)->test(MensagensConta::class)
            ->call('editar', $m->id)
            ->fillForm(['titulo' => 'Novo'])
            ->call('salvar')
            ->assertHasNoFormErrors();

        $m->refresh();
        $this->assertSame('Novo', $m->titulo);
        $this->assertSame([$ativo->id], $m->destinatarios()->pluck('users.id')->all());
    }
}
