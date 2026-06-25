<?php

namespace Tests\Feature\Front;

use App\Models\Palestra;
use App\Models\Palestrante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PalestrasListagemTest extends TestCase
{
    use RefreshDatabase;

    public function test_listagem_mostra_publicadas_e_oculta_rascunho(): void
    {
        $pub = Palestra::factory()->create(['titulo' => 'Auxílios do Invisível', 'status' => Palestra::STATUS_PUBLICADO]);
        $rasc = Palestra::factory()->create(['titulo' => 'Rascunho Secreto', 'status' => Palestra::STATUS_RASCUNHO]);

        $resp = $this->get(route('palestras.index'));

        $resp->assertOk();
        $resp->assertSee('Auxílios do Invisível');
        $resp->assertDontSee('Rascunho Secreto');
    }

    public function test_listagem_mostra_so_palestrante_ativo(): void
    {
        $palestra = Palestra::factory()->create();
        $ativo = Palestrante::factory()->ativo()->create(['nome' => 'João Ativo']);
        $diretor = Palestrante::factory()->inativo()->create(['nome' => 'Maria Inativa']);
        $inativoComoPalestrante = Palestrante::factory()->inativo()->create(['nome' => 'Carlos Inativo']);
        $palestra->palestrantes()->attach($ativo, ['papel' => Palestra::PAPEL_PALESTRANTE]);
        $palestra->palestrantes()->attach($diretor, ['papel' => Palestra::PAPEL_DIRETOR]);
        $palestra->palestrantes()->attach($inativoComoPalestrante, ['papel' => Palestra::PAPEL_PALESTRANTE]);

        $resp = $this->get(route('palestras.index'));

        $resp->assertSee('João Ativo');
        $resp->assertDontSee('Maria Inativa');
        $resp->assertDontSee('Carlos Inativo'); // papel correto, mas inativo → não aparece
    }
}
