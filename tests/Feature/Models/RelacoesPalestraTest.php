<?php

namespace Tests\Feature\Models;

use App\Models\Assunto;
use App\Models\Palestra;
use App\Models\Palestrante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RelacoesPalestraTest extends TestCase
{
    use RefreshDatabase;

    public function test_palestrantes_diretor_e_assuntos(): void
    {
        $palestra = Palestra::factory()->create();
        $ativo = Palestrante::factory()->ativo()->create();
        $inativo = Palestrante::factory()->inativo()->create();
        $diretor = Palestrante::factory()->inativo()->create();
        $assunto = Assunto::factory()->create();

        $palestra->palestrantes()->attach($ativo, ['papel' => Palestra::PAPEL_PALESTRANTE]);
        $palestra->palestrantes()->attach($inativo, ['papel' => Palestra::PAPEL_PALESTRANTE]);
        $palestra->palestrantes()->attach($diretor, ['papel' => Palestra::PAPEL_DIRETOR]);
        $palestra->assuntos()->attach($assunto);

        $this->assertCount(3, $palestra->palestrantes);
        $this->assertCount(1, $palestra->palestrantesAtivos); // só o ativo com papel palestrante
        $this->assertTrue($palestra->diretor->is($diretor));
        $this->assertTrue($palestra->assuntos->contains($assunto));
        $this->assertSame(
            Palestra::PAPEL_PALESTRANTE,
            $ativo->palestras->first()->pivot->papel
        );

        // Diretor ATIVO não deve aparecer em palestrantesAtivos (filtro de papel atua além de ativo)
        $diretorAtivo = Palestrante::factory()->ativo()->create();
        $palestra->palestrantes()->attach($diretorAtivo, ['papel' => Palestra::PAPEL_DIRETOR]);
        $palestra->refresh();
        $this->assertCount(1, $palestra->palestrantesAtivos);
    }
}
