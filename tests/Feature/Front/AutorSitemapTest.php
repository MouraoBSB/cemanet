<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-19

namespace Tests\Feature\Front;

use App\Models\AutorEspiritual;
use App\Models\Mensagem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutorSitemapTest extends TestCase
{
    use RefreshDatabase;

    public function test_inclui_ativo_com_publica_e_exclui_inativo_e_ativo_sem_publica(): void
    {
        $ativoComPublica = AutorEspiritual::factory()->ativo()->create(['slug' => 'autor-pub']);
        $ativoComPublica->mensagens()->attach(Mensagem::factory()->publica()->create());

        $inativoComPublica = AutorEspiritual::factory()->inativo()->create(['slug' => 'autor-inativo']);
        $inativoComPublica->mensagens()->attach(Mensagem::factory()->publica()->create());

        $ativoSemPublica = AutorEspiritual::factory()->ativo()->create(['slug' => 'autor-sem-publica']);
        $ativoSemPublica->mensagens()->attach(Mensagem::factory()->pendente()->create());

        $res = $this->get('/sitemap.xml');
        $res->assertOk()->assertHeader('Content-Type', 'application/xml');
        $res->assertSee(route('autores.index'), false);
        $res->assertSee('/autores-espirituais/autor-pub', false);
        $res->assertDontSee('/autores-espirituais/autor-inativo', false);
        $res->assertDontSee('/autores-espirituais/autor-sem-publica', false);
    }
}
