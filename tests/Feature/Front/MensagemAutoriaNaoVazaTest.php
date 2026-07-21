<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-21

namespace Tests\Feature\Front;

use App\Enums\VisibilidadeMensagem;
use App\Livewire\Conta\CuradoriaConta;
use App\Livewire\Conta\MensagensConta;
use App\Models\AutorEspiritual;
use App\Models\Cargo;
use App\Models\Mensagem;
use App\Models\Setor;
use App\Models\User;
use Database\Seeders\EstruturaCemaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * I18 (Fatia F4b, Task 12) — trava de regressão: a autoria INTERNA (`medium_id`/`publicado_por_id`/
 * `publicado_em`) nunca pode aparecer em superfície pública, nem como nome (relação `medium`/
 * `publicadoPor`), nem como chave crua. `$hidden` no model (Task 7/D8) já protege `toArray()`/JSON,
 * mas NÃO protege acesso direto em Blade (`$mensagem->medium->name`) — daí a trava aqui, sobre o
 * HTML de fato renderizado.
 *
 * ⚠️ `assertDontSee` do LIVEWIRE remove o `wire:snapshot` antes de comparar (falso-verde num
 * eventual vazamento ali) — por isso todas as asserções usam o `TestResponse` do Laravel
 * (`$this->get(...)`), nunca `Livewire::test()->assertDontSee()` (ver CuradoriaContaTest::
 * test_i18_campos_privilegiados_nao_entram_no_estado_do_form, que já prova o ESTADO; aqui é o HTML).
 *
 * I19 (a ponte) mora neste mesmo arquivo (o "Files:" do brief da Task 12 não abre um 4º arquivo):
 * uma mensagem PENDENTE criada pelo fluxo novo (médium + curadoria) continua respeitando os
 * scopes/policies da Fatia 3B/3C — fora de tudo enquanto pendente, aparece só após publicada.
 */
class MensagemAutoriaNaoVazaTest extends TestCase
{
    use RefreshDatabase;

    private const NOME_MEDIUM = 'MEDIUM-SENTINELA-NAO-VAZA';

    private const NOME_CURADOR = 'CURADOR-SENTINELA-NAO-VAZA';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstruturaCemaSeeder::class); // as páginas do site renderizam nav/saudação
    }

    private function medium(): User
    {
        $user = User::factory()->create();
        $user->assignRole('trabalhador');
        $user->setores()->attach(Setor::where('slug', Setor::SLUG_MEDIUM)->value('id'), ['funcao' => 'membro']);

        return $user->fresh();
    }

    private function diretorDepae(): User
    {
        $user = User::factory()->create();
        $user->assignRole('diretor');
        $user->cargos()->attach(Cargo::where('slug', Cargo::SLUG_DIRETOR_DEPAE)->value('id'));

        return $user->fresh();
    }

    private function assertAutoriaNaoVaza(TestResponse $resposta): TestResponse
    {
        return $resposta
            ->assertDontSee(self::NOME_MEDIUM)
            ->assertDontSee(self::NOME_CURADOR)
            ->assertDontSee('medium_id')
            ->assertDontSee('publicado_por_id')
            ->assertDontSee('publicado_em');
    }

    /** I18: single (anônimo e logado), lista, sitemap e perfil do autor — todos com nomes-sentinela na autoria. */
    public function test_autoria_nao_vaza_em_single_lista_sitemap_e_perfil_do_autor(): void
    {
        $medium = User::factory()->create(['name' => self::NOME_MEDIUM]);
        $curador = User::factory()->create(['name' => self::NOME_CURADOR]);
        $autor = AutorEspiritual::factory()->create();
        $leitor = User::factory()->create();

        $publica = Mensagem::factory()->publica()->create([
            'titulo' => 'Mensagem Pública Sem Vazamento',
            'medium_id' => $medium->id,
            'publicado_por_id' => $curador->id,
            'publicado_em' => now(),
        ]);
        $publica->autores()->sync([$autor->id]);

        // Single — anônimo.
        $this->assertAutoriaNaoVaza(
            $this->get(route('mensagens.show', $publica->slug))->assertOk()
        );

        // Single — logado (usuário comum, sem relação nenhuma com a mensagem).
        $this->assertAutoriaNaoVaza(
            $this->actingAs($leitor)->get(route('mensagens.show', $publica->slug))->assertOk()
        );

        // Lista pública — TestResponse (full-page: o <livewire:mensagens.lista /> renderiza no SSR).
        $this->assertAutoriaNaoVaza(
            $this->get(route('mensagens.index'))->assertOk()
        );

        // Sitemap.
        $this->assertAutoriaNaoVaza(
            $this->get(route('sitemap'))->assertOk()
        );

        // Perfil do autor espiritual.
        $this->assertAutoriaNaoVaza(
            $this->get(route('autores.show', $autor->slug))->assertOk()
        );
    }

    /** I18: /minha-conta/direcionadas (visão do PRÓPRIO destinatário) também não vaza a autoria. */
    public function test_autoria_nao_vaza_em_minhas_direcionadas(): void
    {
        $medium = User::factory()->create(['name' => self::NOME_MEDIUM]);
        $curador = User::factory()->create(['name' => self::NOME_CURADOR]);
        $destinatario = User::factory()->create();

        $direcionada = Mensagem::factory()->publicada()->comNivel(VisibilidadeMensagem::Direcionada)->create([
            'titulo' => 'Direcionada Sem Vazamento',
            'medium_id' => $medium->id,
            'publicado_por_id' => $curador->id,
            'publicado_em' => now(),
        ]);
        $direcionada->destinatarios()->sync([$destinatario->id]);

        $this->assertAutoriaNaoVaza(
            $this->actingAs($destinatario)->get(route('conta.direcionadas'))->assertOk()->assertSee('Direcionada Sem Vazamento')
        );
    }

    /**
     * I19 — a ponte: mensagem PENDENTE criada pelo fluxo novo (médium lança, direcionada a alguém)
     * fica fora da lista, 404 no single, fora do sitemap e fora de "Minhas Direcionadas" — os
     * scopes/policies da 3B/3C seguem intactos mesmo com `medium_id`/pivô de destinatários já
     * preenchidos pela F4b. Publicada pela curadoria com nível Público (I10: o pivô de direcionada
     * esvazia ao trocar de nível) ⇒ aparece na lista e no single.
     */
    public function test_i19_pendente_fica_fora_de_tudo_e_publicada_publico_aparece(): void
    {
        $medium = $this->medium();
        $curador = $this->diretorDepae();
        $destinatario = User::factory()->create();

        Livewire::actingAs($medium)->test(MensagensConta::class)
            ->call('novo')
            ->fillForm([
                'titulo' => 'Mensagem Da Ponte 3B',
                'formato' => 'psicografia',
                'corpo' => '<p>Corpo da ponte.</p>',
                'direcionar' => true,
                'destinatarios' => [$destinatario->id],
            ])
            ->call('salvar')
            ->assertHasNoFormErrors();

        $mensagem = Mensagem::where('titulo', 'Mensagem Da Ponte 3B')->firstOrFail();
        $this->assertSame(Mensagem::STATUS_PENDENTE, $mensagem->status);
        $this->assertSame(VisibilidadeMensagem::Direcionada->value, $mensagem->nivel);

        // PENDENTE: fora de tudo — inclusive das Direcionadas do próprio destinatário (403, aba apagada).
        $this->get(route('mensagens.index'))->assertDontSee('Mensagem Da Ponte 3B');
        $this->get(route('mensagens.show', $mensagem->slug))->assertNotFound();
        $this->get(route('sitemap'))->assertDontSee($mensagem->slug);
        $this->actingAs($destinatario)->get(route('conta.direcionadas'))->assertForbidden();

        // Curadoria publica trocando o nível para Público.
        Livewire::actingAs($curador)->test(CuradoriaConta::class)
            ->call('editar', $mensagem->id)
            ->fillForm(['nivel' => VisibilidadeMensagem::Publico->value])
            ->call('publicar', $mensagem->id)
            ->assertHasNoFormErrors();

        $mensagem->refresh();
        $this->assertSame(Mensagem::STATUS_PUBLICADO, $mensagem->status);
        $this->assertSame(VisibilidadeMensagem::Publico->value, $mensagem->nivel);
        $this->assertSame(0, $mensagem->destinatarios()->count(), 'I10: pivô de direcionada esvazia ao trocar de nível');

        // PUBLICADA PÚBLICA: aparece na lista e no single.
        $this->get(route('mensagens.index'))->assertSee('Mensagem Da Ponte 3B');
        $this->get(route('mensagens.show', $mensagem->slug))->assertOk()->assertSee('Mensagem Da Ponte 3B');
    }
}
