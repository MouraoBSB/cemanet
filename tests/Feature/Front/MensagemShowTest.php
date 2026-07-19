<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-19

namespace Tests\Feature\Front;

use App\Models\AutorEspiritual;
use App\Models\Mensagem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MensagemShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_publica_renderiza_200(): void
    {
        $m = Mensagem::factory()->publica()->create(['slug' => 'paz-e-luz', 'titulo' => 'Paz e Luz']);

        $this->get(route('mensagens.show', $m->slug))->assertOk()->assertSee('Paz e Luz');
    }

    public function test_pendente_e_restrita_dao_404_nunca_403(): void
    {
        $pendente = Mensagem::factory()->pendente()->create(['slug' => 'pendente-x']);
        $restrita = Mensagem::factory()->create(['status' => 'publicado', 'nivel' => 'diretores', 'slug' => 'restrita-x', 'titulo' => 'Segredo dos Diretores']);

        $this->get(route('mensagens.show', 'pendente-x'))->assertNotFound();
        $r = $this->get(route('mensagens.show', 'restrita-x'));
        $r->assertNotFound();
        $r->assertDontSee('Segredo dos Diretores');   // não vaza existência
    }

    public function test_contexto_e_escapado(): void
    {
        $m = Mensagem::factory()->publica()->create(['slug' => 'ctx', 'contexto' => 'Nota <script>alert(1)</script> final']);

        $res = $this->get(route('mensagens.show', 'ctx'));
        $res->assertSee('Nota &lt;script&gt;', false);   // escapado
        $res->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_sem_autor_mostra_sem_assinatura(): void
    {
        $m = Mensagem::factory()->publica()->create(['slug' => 'sa']);

        $this->get(route('mensagens.show', 'sa'))->assertSee('Sem assinatura');
    }

    public function test_dois_autores_aparecem(): void
    {
        $m = Mensagem::factory()->publica()->create(['slug' => 'dois']);
        $a1 = AutorEspiritual::factory()->create(['nome' => 'Emmanuel']);
        $a2 = AutorEspiritual::factory()->create(['nome' => 'André Luiz']);
        $m->autores()->sync([$a1->id, $a2->id]);

        $this->get(route('mensagens.show', 'dois'))->assertSee('Emmanuel')->assertSee('André Luiz');
    }

    public function test_download_so_quando_liberado(): void
    {
        $com = Mensagem::factory()->publica()->create(['slug' => 'com-dl', 'liberar_download' => true, 'link_arquivo' => 'https://drive.google.com/file/d/1AbC/view']);
        $sem = Mensagem::factory()->publica()->create(['slug' => 'sem-dl', 'liberar_download' => false, 'link_arquivo' => 'https://drive.google.com/file/d/1AbC/view']);

        $this->get(route('mensagens.show', 'com-dl'))->assertSee('Baixar arquivo');
        $this->get(route('mensagens.show', 'sem-dl'))->assertDontSee('Baixar arquivo');
    }

    public function test_recebidas_no_mesmo_dia_so_publicas(): void
    {
        $m = Mensagem::factory()->publica()->create(['slug' => 'hoje', 'data_recebimento' => '2025-03-10']);
        Mensagem::factory()->publica()->create(['data_recebimento' => '2025-03-10', 'titulo' => 'Irmã do mesmo dia']);
        Mensagem::factory()->pendente()->create(['data_recebimento' => '2025-03-10', 'titulo' => 'Pendente do mesmo dia']);

        $res = $this->get(route('mensagens.show', 'hoje'));
        $res->assertSee('Irmã do mesmo dia');
        $res->assertDontSee('Pendente do mesmo dia');
    }

    public function test_relacionadas_so_publicas(): void
    {
        $m = Mensagem::factory()->publica()->create(['slug' => 'rel']);
        $pub = Mensagem::factory()->publica()->create(['titulo' => 'Relacionada Pública']);
        $rest = Mensagem::factory()->create(['status' => 'publicado', 'nivel' => 'trabalhadores', 'titulo' => 'Relacionada Restrita']);
        $m->sincronizarRelacionadas([$pub->id, $rest->id]);

        $res = $this->get(route('mensagens.show', 'rel'));
        $res->assertSee('Relacionada Pública');
        $res->assertDontSee('Relacionada Restrita');
    }

    public function test_sem_f3_e_f5(): void
    {
        $m = Mensagem::factory()->publica()->create(['slug' => 'limpa']);

        $res = $this->get(route('mensagens.show', 'limpa'));
        foreach (['Nível de acesso', 'Mensagem direcionada', 'Favoritar', 'Curtir'] as $proibido) {
            $res->assertDontSee($proibido);
        }
    }
}
