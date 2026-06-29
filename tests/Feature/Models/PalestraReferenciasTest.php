<?php

namespace Tests\Feature\Models;

use App\Models\Palestra;
use App\Models\PalestraReferencia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PalestraReferenciasTest extends TestCase
{
    use RefreshDatabase;

    public function test_palestra_tem_referencias_ordenadas(): void
    {
        $palestra = Palestra::factory()->create();
        $palestra->referencias()->create(['obra' => 'O Evangelho', 'autor' => 'Kardec', 'nota' => 'b', 'ordem' => 1]);
        $palestra->referencias()->create(['obra' => 'O Livro dos Espíritos', 'autor' => 'Kardec', 'nota' => 'a', 'ordem' => 0]);

        $obras = $palestra->refresh()->referencias->pluck('obra')->all();

        $this->assertSame(['O Livro dos Espíritos', 'O Evangelho'], $obras);
    }

    public function test_campos_novos_sao_mass_assignable(): void
    {
        $palestra = Palestra::factory()->create([
            'slide' => 'https://drive.google.com/file/d/1ABCdefg_hij/view',
            'duracao' => '≈1h10',
            'referencias_evangelicas' => 'João 14.',
            'curtidas' => 5,
        ]);

        $this->assertDatabaseHas('palestras', ['id' => $palestra->id, 'duracao' => '≈1h10', 'curtidas' => 5]);
    }

    public function test_slide_download_url_deriva_do_link_cru(): void
    {
        $palestra = \App\Models\Palestra::factory()->create([
            'slide' => 'https://drive.google.com/file/d/1ABCdefg_hij/view?usp=sharing',
        ]);

        $this->assertSame(
            'https://drive.google.com/uc?export=download&id=1ABCdefg_hij',
            $palestra->slide_download_url
        );
    }
}
