<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-20

namespace Tests\Feature\Front;

use App\Livewire\Mensagens\Lista;
use App\Models\AutorEspiritual;
use App\Models\Mensagem;
use App\Models\Setor;
use App\Models\User;
use Database\Seeders\EstruturaCemaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MensagemListaVisibilidadeTest extends TestCase
{
    use RefreshDatabase;

    private function comPapel(string $papel): User
    {
        $this->seed(EstruturaCemaSeeder::class);
        $u = User::factory()->create();
        $u->assignRole($papel);

        return $u->fresh();
    }

    public function test_anonimo_ve_so_publicas_paridade_2b(): void
    {
        Mensagem::factory()->publica()->create(['titulo' => 'Pública Visível']);
        Mensagem::factory()->create(['status' => 'publicado', 'nivel' => 'trabalhadores', 'titulo' => 'Restrita Oculta']);

        Livewire::test(Lista::class)
            ->assertSee('Pública Visível')
            ->assertDontSee('Restrita Oculta');   // não vaza título restrito ao anônimo (I10)
    }

    public function test_trabalhador_ve_trabalhadores_nao_mediuns(): void
    {
        $trab = $this->comPapel('trabalhador');
        Mensagem::factory()->publica()->create(['titulo' => 'Pub']);
        Mensagem::factory()->create(['status' => 'publicado', 'nivel' => 'trabalhadores', 'titulo' => 'Trab']);
        Mensagem::factory()->create(['status' => 'publicado', 'nivel' => 'mediuns-trabalhadores', 'titulo' => 'Med']);

        Livewire::actingAs($trab)->test(Lista::class)
            ->assertSee('Pub')->assertSee('Trab')->assertDontSee('Med'); // recorte médium não vaza
    }

    public function test_medium_ve_mediuns_trabalhadores(): void
    {
        // Caso POSITIVO do recorte (paridade no front com o resolvedor da 3A): médium vê 'mediuns-trabalhadores'.
        $medium = $this->comPapel('trabalhador');
        $medium->setores()->attach(Setor::where('slug', Setor::SLUG_MEDIUM)->value('id'), ['funcao' => 'membro']);
        Mensagem::factory()->create(['status' => 'publicado', 'nivel' => 'mediuns-trabalhadores', 'titulo' => 'Doc Medium']);

        Livewire::actingAs($medium->fresh())->test(Lista::class)->assertSee('Doc Medium');
    }

    public function test_select_de_autor_so_com_mensagem_visivel(): void
    {
        $trab = $this->comPapel('trabalhador');
        $autorPub = AutorEspiritual::factory()->create(['nome' => 'Autor Público', 'slug' => 'autor-pub']);
        $autorRest = AutorEspiritual::factory()->create(['nome' => 'Autor Só Diretores', 'slug' => 'autor-dir']);
        Mensagem::factory()->publica()->create()->autores()->attach($autorPub->id);
        Mensagem::factory()->create(['status' => 'publicado', 'nivel' => 'diretores'])->autores()->attach($autorRest->id);

        Livewire::actingAs($trab)->test(Lista::class)
            ->assertSee('Autor Público')->assertDontSee('Autor Só Diretores'); // trabalhador não vê Diretores
    }
}
