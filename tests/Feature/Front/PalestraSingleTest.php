<?php

namespace Tests\Feature\Front;

use App\Models\Palestra;
use App\Models\Palestrante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PalestraSingleTest extends TestCase
{
    use RefreshDatabase;

    private function palestraComPessoas(): Palestra
    {
        $palestra = Palestra::factory()->create([
            'titulo' => 'Auxílios do Invisível',
            'slug' => 'auxilios-do-invisivel',
            'status' => Palestra::STATUS_PUBLICADO,
        ]);
        $ativo = Palestrante::factory()->ativo()->create(['nome' => 'João Ativo']);
        $diretor = Palestrante::factory()->inativo()->create(['nome' => 'Maria Inativa']);
        $palestra->palestrantes()->attach($ativo, ['papel' => Palestra::PAPEL_PALESTRANTE]);
        $palestra->palestrantes()->attach($diretor, ['papel' => Palestra::PAPEL_DIRETOR]);
        $palestra->destaques()->create(['destaque' => 'A fé raciocinada', 'texto' => 'Estudo sério.', 'ordem' => 0]);

        return $palestra;
    }

    public function test_single_publica_retorna_200_com_conteudo(): void
    {
        $this->palestraComPessoas();

        $resp = $this->get(route('palestras.show', 'auxilios-do-invisivel'));

        $resp->assertOk();
        $resp->assertSee('Auxílios do Invisível');
        $resp->assertSee('João Ativo');           // palestrante ativo aparece
        $resp->assertSee('A fé raciocinada');      // destaque aparece
    }

    public function test_single_nao_mostra_diretor_inativo(): void
    {
        $this->palestraComPessoas();

        $resp = $this->get(route('palestras.show', 'auxilios-do-invisivel'));

        $resp->assertDontSee('Maria Inativa');
    }

    public function test_single_rascunho_da_404(): void
    {
        Palestra::factory()->create(['slug' => 'oculta', 'status' => Palestra::STATUS_RASCUNHO]);

        $this->get(route('palestras.show', 'oculta'))->assertNotFound();
    }

    public function test_single_slug_inexistente_da_404(): void
    {
        $this->get(route('palestras.show', 'nao-existe'))->assertNotFound();
    }

    public function test_single_tem_jsonld_event(): void
    {
        $this->palestraComPessoas();

        $resp = $this->get(route('palestras.show', 'auxilios-do-invisivel'));

        $resp->assertSee('application/ld+json', false);
        $resp->assertSee('"@type":"Event"', false);
    }
}
