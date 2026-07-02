<?php

namespace Tests\Feature\Support;

use App\Models\Assunto;
use App\Models\Palestra;
use App\Support\Palestrantes\ResumoPerfil;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class ResumoPerfilTest extends TestCase
{
    use RefreshDatabase;

    private function palestra(array $attrs, array $assuntos = []): Palestra
    {
        $p = Palestra::factory()->create($attrs);
        foreach ($assuntos as $a) {
            $p->assuntos()->attach($a);
        }

        return $p->load('assuntos');
    }

    public function test_totais_temas_ultima_e_percentual(): void
    {
        $evangelho = Assunto::factory()->create(['nome' => 'Evangelho', 'slug' => 'evangelho']);
        $perdao = Assunto::factory()->create(['nome' => 'Perdão', 'slug' => 'perdao']);

        $palestras = new Collection([
            $this->palestra(['data_da_palestra' => '2024-03-10 19:30', 'online' => true], [$evangelho]),
            $this->palestra(['data_da_palestra' => '2022-08-01 19:30', 'online' => false], [$evangelho, $perdao]),
            $this->palestra(['data_da_palestra' => null, 'online' => true], [$perdao]),
        ]);

        $r = new ResumoPerfil($palestras);

        $this->assertSame(3, $r->totalPalestras());
        $this->assertSame(2, $r->totalTemas());
        $this->assertSame('2024-03-10', $r->ultimaPalestra()?->format('Y-m-d')); // mais recente (ignora null)
        $this->assertSame(67, $r->percentualOnline()); // 2 de 3 → 66.67 → 67
    }

    public function test_areas_com_contagem_cor_e_ordem(): void
    {
        $evangelho = Assunto::factory()->create(['nome' => 'Evangelho', 'slug' => 'evangelho']);
        $perdao = Assunto::factory()->create(['nome' => 'Perdão', 'slug' => 'perdao']);

        $palestras = new Collection([
            $this->palestra(['data_da_palestra' => '2024-01-01'], [$evangelho, $perdao]),
            $this->palestra(['data_da_palestra' => '2024-02-01'], [$evangelho]),
        ]);

        $areas = (new ResumoPerfil($palestras))->areas();

        $this->assertSame('evangelho', $areas->first()['slug']); // maior contagem primeiro
        $this->assertSame(2, $areas->first()['count']);
        $this->assertSame($evangelho->id % 8, $areas->first()['cor']);
        $this->assertEqualsCanonicalizing(['evangelho', 'perdao'], $areas->pluck('slug')->all());
    }

    public function test_null_safe_sem_palestras(): void
    {
        $r = new ResumoPerfil(new Collection);

        $this->assertSame(0, $r->totalPalestras());
        $this->assertSame(0, $r->totalTemas());
        $this->assertNull($r->ultimaPalestra());
        $this->assertNull($r->percentualOnline()); // guarda de divisão por zero
        $this->assertTrue($r->areas()->isEmpty());
    }

    public function test_areas_hero_limita_top_6(): void
    {
        $palestras = new Collection;
        for ($i = 0; $i < 10; $i++) {
            $a = Assunto::factory()->create(['slug' => "assunto-$i"]);
            $palestras->push($this->palestra(['data_da_palestra' => '2024-01-0'.(($i % 9) + 1)], [$a]));
        }

        $this->assertSame(6, (new ResumoPerfil($palestras))->areasHero()->count());
    }
}
