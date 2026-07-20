<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-20

namespace Tests\Feature\Front;

use App\Models\Configuracao;
use App\Models\Mensagem;
use App\Models\User;
use Database\Seeders\EstruturaCemaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MensagemBarreiraTest extends TestCase
{
    use RefreshDatabase;

    private function comPapel(string $papel): User
    {
        $this->seed(EstruturaCemaSeeder::class);
        $u = User::factory()->create();
        $u->assignRole($papel);

        return $u->fresh();
    }

    public function test_restrita_anonimo_barreira_login_cega(): void
    {
        Mensagem::factory()->create([
            'status' => 'publicado', 'nivel' => 'diretores', 'slug' => 'seg',
            'titulo' => 'Segredo dos Diretores', 'corpo' => '<p>CorpoSecreto</p>',
        ]);

        $res = $this->get(route('mensagens.show', 'seg'));

        $res->assertOk();                              // 200 (não 404/403 — F5)
        $res->assertSee('Conteúdo restrito');
        $res->assertSee(route('login'), false);        // form de login
        $res->assertSee('name="robots"', false);       // noindex
        $res->assertDontSee('Segredo dos Diretores');  // cego: sem título (I7)
        $res->assertDontSee('CorpoSecreto');           // sem corpo (I8)
        $res->assertDontSee('application/ld+json', false); // sem SEO rico
        $this->assertStringContainsString('/mensagens-mediunicas/seg', session('url.intended'));
    }

    public function test_restrita_logado_sem_acesso_sem_permissao_com_contato(): void
    {
        $u = $this->comPapel('frequentador');
        Configuracao::definir('contato.email', 'contato@cema.org');
        Mensagem::factory()->create(['status' => 'publicado', 'nivel' => 'diretores', 'slug' => 'seg', 'titulo' => 'Segredo']);

        $res = $this->actingAs($u)->get(route('mensagens.show', 'seg'));

        $res->assertOk()->assertSee('não tem permissão')->assertSee('contato@cema.org');
        $res->assertSee('name="robots"', false);
        $res->assertDontSee('Segredo');   // cego
    }

    public function test_sem_permissao_sem_contato_nao_quebra(): void
    {
        $u = $this->comPapel('frequentador');
        Mensagem::factory()->create(['status' => 'publicado', 'nivel' => 'diretores', 'slug' => 'seg']);

        $this->actingAs($u)->get(route('mensagens.show', 'seg'))->assertOk(); // degrada sem contato
    }

    public function test_inexistente_e_pendente_dao_404(): void
    {
        Mensagem::factory()->pendente()->create(['slug' => 'pend']);

        $this->get(route('mensagens.show', 'nao-existe'))->assertNotFound();
        $this->get(route('mensagens.show', 'pend'))->assertNotFound();
    }

    public function test_direcionada_cega_a_nao_destinatario_destinatario_ve(): void
    {
        $dest = $this->comPapel('frequentador');
        $diretor = User::factory()->create();
        $diretor->assignRole('diretor');
        $m = Mensagem::factory()->create([
            'status' => 'publicado', 'nivel' => 'direcionada', 'slug' => 'dir',
            'titulo' => 'Para Voce', 'corpo' => '<p>CorpoDir</p>',
        ]);
        $m->destinatarios()->attach($dest->id);

        // não-destinatário (diretor, alto papel mas não é destinatário) → barreira cega
        $r1 = $this->actingAs($diretor)->get(route('mensagens.show', 'dir'));
        $r1->assertOk()->assertDontSee('Para Voce')->assertDontSee('CorpoDir');

        // destinatário → vê a mensagem (caminho autorizado serve o show.blade)
        $r2 = $this->actingAs($dest)->get(route('mensagens.show', 'dir'));
        $r2->assertOk()->assertSee('Para Voce');
    }
}
