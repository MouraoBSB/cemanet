<?php

namespace Tests\Feature\Importacao;

use App\Importacao\LeitorLegado;
use App\Models\Palestra;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportarPalestrasCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_comando_importa_usando_o_leitor_injetado(): void
    {
        Storage::fake('public');
        // injeta um leitor fake no container (evita depender do legado)
        $this->app->bind(LeitorLegado::class, fn () => new class implements LeitorLegado
        {
            public function assuntos(): array
            {
                return [['nome' => 'Fé', 'slug' => 'fe', 'parent_slug' => null]];
            }

            public function palestrantes(): array
            {
                return [['nome' => 'Ana', 'slug' => 'ana', 'bio' => null, 'email' => null, 'telefone' => null, 'mostrar_email' => false, 'mostrar_telefone' => false, 'ativo' => true, 'foto_url' => null]];
            }

            public function palestras(): array
            {
                return [['titulo' => 'T', 'slug' => 't', 'subtitulo' => null, 'resumo' => null, 'descricao' => null, 'data_da_palestra' => Carbon::parse('2026-06-28 16:00:00'), 'online' => false, 'link_youtube' => null, 'slide' => 'https://drive.google.com/file/d/1ABCdefg_hij/view?usp=sharing', 'cor_fundo' => null, 'publico_online' => null, 'publico_presencial' => null, 'publico_total' => null, 'status' => 'publicado', 'palestrantes_slugs' => ['ana'], 'diretor_slug' => null, 'assuntos_slugs' => ['fe'], 'destaques' => []]];
            }
        });

        $this->artisan('cema:importar-palestras')
            ->expectsOutputToContain('Importação concluída')
            ->assertExitCode(0);

        $this->assertSame(1, Palestra::count());

        $palestra = Palestra::first();
        $this->assertSame('https://drive.google.com/file/d/1ABCdefg_hij/view?usp=sharing', $palestra->slide);
        $this->assertSame('https://drive.google.com/uc?export=download&id=1ABCdefg_hij', $palestra->slide_download_url);
    }
}
