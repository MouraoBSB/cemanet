<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Palestras\Lista;
use App\Models\Assunto;
use App\Models\Palestra;
use App\Models\Palestrante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PalestrasFiltrosTest extends TestCase
{
    use RefreshDatabase;

    public function test_filtra_por_palestrante(): void
    {
        $a = Palestrante::factory()->ativo()->create(['nome' => 'Ana', 'slug' => 'ana']);
        $b = Palestrante::factory()->ativo()->create(['nome' => 'Bruno', 'slug' => 'bruno']);
        $pa = Palestra::factory()->create(['titulo' => 'Da Ana', 'status' => Palestra::STATUS_PUBLICADO]);
        $pb = Palestra::factory()->create(['titulo' => 'Do Bruno', 'status' => Palestra::STATUS_PUBLICADO]);
        $pa->palestrantes()->attach($a, ['papel' => Palestra::PAPEL_PALESTRANTE]);
        $pb->palestrantes()->attach($b, ['papel' => Palestra::PAPEL_PALESTRANTE]);

        Livewire::test(Lista::class)
            ->set('palestrante', 'ana')
            ->assertSee('Da Ana')
            ->assertDontSee('Do Bruno');
    }

    public function test_filtra_por_assunto(): void
    {
        $assunto = Assunto::factory()->create(['slug' => 'mediunidade']);
        $com = Palestra::factory()->create(['titulo' => 'Com Assunto', 'status' => Palestra::STATUS_PUBLICADO]);
        $sem = Palestra::factory()->create(['titulo' => 'Sem Assunto', 'status' => Palestra::STATUS_PUBLICADO]);
        $com->assuntos()->attach($assunto);

        Livewire::test(Lista::class)
            ->set('assunto', 'mediunidade')
            ->assertSee('Com Assunto')
            ->assertDontSee('Sem Assunto');
    }

    public function test_filtra_por_intervalo_de_data(): void
    {
        Palestra::factory()->create(['titulo' => 'Antiga', 'data_da_palestra' => '2020-01-01 16:00:00', 'status' => Palestra::STATUS_PUBLICADO]);
        Palestra::factory()->create(['titulo' => 'Recente', 'data_da_palestra' => '2026-01-01 16:00:00', 'status' => Palestra::STATUS_PUBLICADO]);

        Livewire::test(Lista::class)
            ->set('dataDe', '2025-01-01')
            ->assertSee('Recente')
            ->assertDontSee('Antiga');
    }

    public function test_ordena_antiga_primeiro(): void
    {
        $antiga = Palestra::factory()->create(['titulo' => 'Antiga', 'data_da_palestra' => '2020-01-01 16:00:00', 'status' => Palestra::STATUS_PUBLICADO]);
        $recente = Palestra::factory()->create(['titulo' => 'Recente', 'data_da_palestra' => '2026-01-01 16:00:00', 'status' => Palestra::STATUS_PUBLICADO]);

        $ids = Livewire::test(Lista::class)
            ->set('ordenar', 'antiga')
            ->viewData('palestras')
            ->pluck('id')
            ->all();

        $this->assertSame([$antiga->id, $recente->id], $ids);
    }
}
