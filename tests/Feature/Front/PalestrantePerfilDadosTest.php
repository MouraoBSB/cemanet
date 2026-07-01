<?php

namespace Tests\Feature\Front;

use App\Models\Assunto;
use App\Models\Palestra;
use App\Models\Palestrante;
use App\Support\Palestrantes\ResumoPerfil;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PalestrantePerfilDadosTest extends TestCase
{
    use RefreshDatabase;

    private function palestranteCom(array $palestras): Palestrante
    {
        $pessoa = Palestrante::factory()->ativo()->create(['slug' => 'fulano']);
        foreach ($palestras as $attrs) {
            $p = Palestra::factory()->create($attrs);
            $p->palestrantes()->attach($pessoa, ['papel' => Palestra::PAPEL_PALESTRANTE]);
        }

        return $pessoa;
    }

    public function test_view_recebe_resumo_areas_e_itens(): void
    {
        $assunto = Assunto::factory()->create(['nome' => 'Evangelho', 'slug' => 'evangelho']);
        $pessoa = $this->palestranteCom([
            ['titulo' => 'Antiga', 'data_da_palestra' => '2020-01-01 19:30', 'status' => Palestra::STATUS_PUBLICADO],
            ['titulo' => 'Recente', 'data_da_palestra' => '2024-01-01 19:30', 'status' => Palestra::STATUS_PUBLICADO],
        ]);
        $pessoa->palestras->first()->assuntos()->attach($assunto);

        $resp = $this->get(route('palestrantes.show', 'fulano'));
        $resp->assertOk();

        $this->assertInstanceOf(ResumoPerfil::class, $resp->viewData('resumo'));
        // Ordem "recentes": a mais recente primeiro.
        $this->assertSame('Recente', $resp->viewData('palestras')->first()->titulo);
        $this->assertCount(2, $resp->viewData('itensFiltro'));
        $this->assertArrayHasKey('ts', $resp->viewData('itensFiltro')->first());
    }

    public function test_proxima_e_apenas_futura_publicada(): void
    {
        $pessoa = $this->palestranteCom([
            ['titulo' => 'Passada', 'data_da_palestra' => now()->subMonth(), 'status' => Palestra::STATUS_PUBLICADO],
            ['titulo' => 'Futura', 'data_da_palestra' => now()->addMonth(), 'status' => Palestra::STATUS_PUBLICADO],
        ]);

        $proxima = $this->get(route('palestrantes.show', 'fulano'))->viewData('proxima');
        $this->assertNotNull($proxima);
        $this->assertSame('Futura', $proxima->titulo);
    }

    public function test_proxima_null_sem_futura(): void
    {
        $this->palestranteCom([
            ['titulo' => 'Só passada', 'data_da_palestra' => now()->subMonth(), 'status' => Palestra::STATUS_PUBLICADO],
        ]);

        $this->assertNull($this->get(route('palestrantes.show', 'fulano'))->viewData('proxima'));
    }
}
