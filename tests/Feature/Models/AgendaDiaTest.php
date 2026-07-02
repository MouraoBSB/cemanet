<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-02

namespace Tests\Feature\Models;

use App\Models\AgendaDia;
use App\Models\AgendaMetaMes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AgendaDiaTest extends TestCase
{
    use RefreshDatabase;

    public function test_escopo_publicado_filtra_rascunho(): void
    {
        AgendaDia::factory()->create(['data' => '2026-06-01']);
        AgendaDia::factory()->rascunho()->create(['data' => '2026-06-02']);

        $this->assertCount(1, AgendaDia::publicado()->get());
    }

    public function test_mutator_remove_script_e_preserva_formatacao(): void
    {
        $dia = AgendaDia::factory()->create([
            'reflexao' => '<p>Paz <strong>e amor</strong></p><script>alert(1)</script>',
            'prece' => '<p>Prece</p><script>alert(2)</script>',
        ]);

        $this->assertStringNotContainsString('<script', (string) $dia->fresh()->reflexao);
        $this->assertStringContainsString('<strong>e amor</strong>', (string) $dia->fresh()->reflexao);
        $this->assertStringNotContainsString('<script', (string) $dia->fresh()->prece);
    }

    public function test_mutator_valor_nulo_permanece_nulo(): void
    {
        $dia = AgendaDia::factory()->create(['prece' => null]);

        $this->assertNull($dia->fresh()->prece);
    }

    public function test_meta_mes_resolve_por_ano_e_mes(): void
    {
        $meta = AgendaMetaMes::factory()->create([
            'ano' => 2026,
            'mes' => 6,
            'titulo' => 'Combater o egoísmo: indiferença e ingratidão',
        ]);
        $dia = AgendaDia::factory()->create(['data' => '2026-06-15']);

        $this->assertTrue($meta->is($dia->metaMes()));
        $this->assertSame('Combater o egoísmo: indiferença e ingratidão', $dia->metaMes()->titulo);
    }

    public function test_meta_mes_ausente_retorna_null(): void
    {
        $dia = AgendaDia::factory()->create(['data' => '2026-07-15']);

        $this->assertNull($dia->metaMes());
    }

    public function test_titulo_extenso_em_ptbr_capitalizado(): void
    {
        $dia = AgendaDia::factory()->create(['data' => '2026-06-15']);

        $this->assertSame('Segunda-feira, 15 de junho de 2026', $dia->tituloExtenso());
    }

    public function test_descricao_seo_limita_155_sem_tags(): void
    {
        $dia = AgendaDia::factory()->create([
            'reflexao' => '<p>'.str_repeat('a', 300).'</p>',
        ]);

        $seo = $dia->descricaoSeo();

        $this->assertStringNotContainsString('<', $seo);
        $this->assertStringEndsWith('...', $seo);
        $this->assertLessThanOrEqual(158, Str::length($seo)); // 155 + reticências
    }
}
