<?php

namespace Tests\Feature\Front;

use App\Models\Assunto;
use App\Models\Palestra;
use App\Models\Palestrante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PalestrantePerfilRedesignTest extends TestCase
{
    use RefreshDatabase;

    private function palestrante(array $attrs = []): Palestrante
    {
        return Palestrante::factory()->ativo()->create(array_merge(['slug' => 'fulano', 'nome' => 'Fulano de Tal'], $attrs));
    }

    private function comPalestra(Palestrante $pessoa, array $attrs, ?Assunto $assunto = null): Palestra
    {
        $p = Palestra::factory()->create(array_merge(['status' => Palestra::STATUS_PUBLICADO], $attrs));
        $p->palestrantes()->attach($pessoa, ['papel' => Palestra::PAPEL_PALESTRANTE]);
        if ($assunto) {
            $p->assuntos()->attach($assunto);
        }

        return $p;
    }

    public function test_hero_eyebrow_h1_e_cta_calendario(): void
    {
        $this->palestrante();
        $resp = $this->get(route('palestrantes.show', 'fulano'));

        $resp->assertOk();
        $resp->assertSee('Palestrante'); // eyebrow "Palestrante · CEMA" (maiúsculo é só CSS); assertSee é case-sensitive
        $resp->assertSee('Fulano de Tal');
        $resp->assertSee(route('calendario.index', ['tipo' => 'palestras']), false);
        $resp->assertSee('palestranteDetalhe(', false); // wiring do Alpine
    }

    public function test_chamada_exibida_quando_preenchida_e_oculta_quando_vazia(): void
    {
        $this->palestrante(['chamada' => 'Servindo desde a infância.']);
        $this->get(route('palestrantes.show', 'fulano'))->assertSee('Servindo desde a infância.');

        Palestrante::query()->where('slug', 'fulano')->update(['chamada' => null]);
        $this->get(route('palestrantes.show', 'fulano'))->assertDontSee('Servindo desde a infância.');
    }

    public function test_temas_linkam_para_archive_filtrada_e_ordenacao(): void
    {
        $pessoa = $this->palestrante();
        $evangelho = Assunto::factory()->create(['nome' => 'Evangelho', 'slug' => 'evangelho']);
        $this->comPalestra($pessoa, ['titulo' => 'Palestra A'], $evangelho);

        $resp = $this->get(route('palestrantes.show', 'fulano'));
        $resp->assertSee('Evangelho');
        // Chip de tema navega para a archive já filtrada por aquele assunto.
        $resp->assertSee(route('palestras.index', ['assunto' => 'evangelho']), false);
        $resp->assertSee('Título (A–Z)'); // opção de ordenação (client-side) permanece
        $resp->assertSee('Palestra A');   // card via <x-palestra.card>
    }

    public function test_stats_reais_e_null_safe(): void
    {
        $pessoa = $this->palestrante();
        $this->comPalestra($pessoa, ['data_da_palestra' => '2023-05-01 19:30', 'online' => true]);

        $resp = $this->get(route('palestrantes.show', 'fulano'));
        $resp->assertSee('Última palestra');
        $resp->assertSee('2023');   // mês/ano da mais recente
        $resp->assertSee('100%');   // 1 de 1 online

        // Sem palestras → última/percentual viram "—" (null-safe).
        $vazio = $this->palestrante(['slug' => 'sem-palestras', 'nome' => 'Sem Palestras']);
        $this->get(route('palestrantes.show', 'sem-palestras'))->assertSee('—');
    }

    public function test_sobre_aparece_com_bio_e_some_sem_bio(): void
    {
        $this->palestrante(['bio' => '<p>Biografia rica.</p>']);
        $this->get(route('palestrantes.show', 'fulano'))->assertSee('Biografia rica', false);

        Palestrante::query()->where('slug', 'fulano')->update(['bio' => null]);
        $this->get(route('palestrantes.show', 'fulano'))->assertDontSee('Sobre Fulano de Tal');
    }

    public function test_sidebar_proxima_e_compartilhar(): void
    {
        $pessoa = $this->palestrante();
        // A palestra futura precisa de um assunto: o bloco "Áreas de atuação"
        // só renderiza quando $areas não está vazio (big-bang — @if isNotEmpty).
        $evangelho = Assunto::factory()->create(['nome' => 'Evangelho', 'slug' => 'evangelho']);
        $this->comPalestra(
            $pessoa,
            ['titulo' => 'Palestra Futura', 'slug' => 'palestra-futura', 'data_da_palestra' => now()->addMonth()],
            $evangelho,
        );

        $resp = $this->get(route('palestrantes.show', 'fulano'));
        $resp->assertSee('Em destaque');
        $resp->assertSee('Palestra Futura');
        $resp->assertSee('facebook.com/sharer', false);
        $resp->assertSee('wa.me', false);
        $resp->assertSee('Áreas de atuação');
    }

    public function test_proxima_oculta_sem_futura(): void
    {
        $pessoa = $this->palestrante();
        $this->comPalestra($pessoa, ['titulo' => 'Só passada', 'data_da_palestra' => now()->subMonth()]);

        $this->get(route('palestrantes.show', 'fulano'))->assertDontSee('Em destaque');
    }

    public function test_seo_canonical_e_jsonld(): void
    {
        $this->palestrante();
        $resp = $this->get(route('palestrantes.show', 'fulano'));

        $resp->assertSee('rel="canonical"', false);
        $resp->assertSee(route('palestrantes.show', 'fulano'), false);
        $resp->assertSee('"@type":"Person"', false);
        $resp->assertDontSee('og:image'); // sem foto → sem meta og:image
    }
}
