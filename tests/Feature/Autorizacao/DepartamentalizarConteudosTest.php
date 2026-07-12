<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-11

namespace Tests\Feature\Autorizacao;

use App\Models\AgendaDia;
use App\Models\Departamento;
use App\Models\Palestra;
use App\Models\Palestrante;
use App\Models\Post;
use Database\Seeders\EstruturaCemaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepartamentalizarConteudosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstruturaCemaSeeder::class); // cria os 8 departamentos (DED, DECOM, ...)
    }

    public function test_vincula_cada_conteudo_ao_departamento_que_mantem_e_e_idempotente(): void
    {
        $palestra = Palestra::factory()->create();
        $palestrante = Palestrante::factory()->create();
        $post = Post::factory()->create();
        $agenda = AgendaDia::factory()->create();

        $this->artisan('cema:departamentalizar-conteudos')->assertSuccessful();
        $this->artisan('cema:departamentalizar-conteudos')->assertSuccessful(); // 2ª vez não duplica

        $ded = Departamento::where('sigla', 'DED')->first();
        $decom = Departamento::where('sigla', 'DECOM')->first();

        $this->assertSame([$ded->id], $palestra->fresh()->departamentos()->pluck('departamentos.id')->all());
        $this->assertSame([$ded->id], $palestrante->fresh()->departamentos()->pluck('departamentos.id')->all());
        $this->assertSame([$decom->id], $post->fresh()->departamentos()->pluck('departamentos.id')->all());
        $this->assertSame([$decom->id], $agenda->fresh()->departamentos()->pluck('departamentos.id')->all());
    }

    public function test_preserva_vinculo_manual_extra(): void
    {
        $palestra = Palestra::factory()->create();
        $decom = Departamento::where('sigla', 'DECOM')->first();
        $palestra->departamentos()->attach($decom->id); // vínculo manual pré-existente (fora do critério)

        $this->artisan('cema:departamentalizar-conteudos')->assertSuccessful();

        $ded = Departamento::where('sigla', 'DED')->first();
        $ids = $palestra->fresh()->departamentos()->pluck('departamentos.id')->all();

        $this->assertContains($ded->id, $ids);    // recebeu o DED (critério)
        $this->assertContains($decom->id, $ids);  // preservou o DECOM manual (syncWithoutDetaching)
        $this->assertCount(2, $ids);
    }
}
