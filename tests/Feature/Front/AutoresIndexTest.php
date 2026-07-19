<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-19

namespace Tests\Feature\Front;

use App\Models\AutorEspiritual;
use App\Models\Mensagem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutoresIndexTest extends TestCase
{
    use RefreshDatabase;

    private function autorComPublicas(string $nome, int $qtd): AutorEspiritual
    {
        $autor = AutorEspiritual::factory()->create(['nome' => $nome, 'ativo' => true]);
        Mensagem::factory()->publica()->count($qtd)->create()->each(fn ($m) => $m->autores()->sync([$autor->id]));

        return $autor;
    }

    public function test_lista_so_ativo_com_publica(): void
    {
        $this->autorComPublicas('Emmanuel', 3);
        // ativo sem pública: OCULTO (O5a)
        AutorEspiritual::factory()->create(['nome' => 'Autor Vazio', 'ativo' => true]);
        // inativo: fora
        $inativo = AutorEspiritual::factory()->create(['nome' => 'Autor Inativo', 'ativo' => false]);
        Mensagem::factory()->publica()->create()->autores()->sync([$inativo->id]);

        $res = $this->get(route('autores.index'));
        $res->assertOk()->assertSee('Emmanuel');
        $res->assertDontSee('Autor Vazio');
        $res->assertDontSee('Autor Inativo');
    }

    public function test_contagem_so_das_publicas(): void
    {
        $autor = $this->autorComPublicas('Bezerra', 3);
        Mensagem::factory()->create(['status' => 'publicado', 'nivel' => 'trabalhadores'])->autores()->sync([$autor->id], false);

        // "3 mensagens" (só públicas), não 4.
        $this->get(route('autores.index'))->assertSee('3 mensagens');
    }
}
