<?php

namespace Tests\Feature\Models;

use App\Models\Palestra;
use App\Models\Palestrante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PalestranteTest extends TestCase
{
    use RefreshDatabase;

    public function test_cria_palestrante_com_atributos(): void
    {
        $p = Palestrante::create([
            'nome' => 'Moisés Andrade',
            'slug' => 'moises-andrade',
            'bio' => '<p>Bio</p>',
            'email' => 'moises@cema.org.br',
            'telefone' => '61999990000',
            'mostrar_email' => true,
            'mostrar_telefone' => false,
            'ativo' => true,
        ]);

        $this->assertDatabaseHas('palestrantes', ['slug' => 'moises-andrade']);
        $this->assertTrue($p->mostrar_email);
        $this->assertFalse($p->mostrar_telefone);
        $this->assertTrue($p->ativo);
    }

    public function test_escopo_ativo_filtra_inativos(): void
    {
        Palestrante::factory()->ativo()->create();
        Palestrante::factory()->inativo()->create();

        $this->assertCount(2, Palestrante::all());
        $this->assertCount(1, Palestrante::ativo()->get());
    }

    public function test_palestras_ministradas_so_traz_papel_palestrante(): void
    {
        $pessoa = Palestrante::factory()->ativo()->create();
        $comoPalestrante = Palestra::factory()->create();
        $comoDiretor = Palestra::factory()->create();
        $comoPalestrante->palestrantes()->attach($pessoa, ['papel' => Palestra::PAPEL_PALESTRANTE]);
        $comoDiretor->palestrantes()->attach($pessoa, ['papel' => Palestra::PAPEL_DIRETOR]);

        $ids = $pessoa->palestrasMinistradas()->pluck('palestras.id')->all();

        $this->assertContains($comoPalestrante->id, $ids);
        $this->assertNotContains($comoDiretor->id, $ids);
    }
}
