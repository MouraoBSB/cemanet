<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-02

namespace Tests\Feature\Front;

use App\Models\AgendaDia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgendaSeoTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_tem_jsonld_article_breadcrumb_e_canonical(): void
    {
        AgendaDia::factory()->create([
            'data' => '2026-07-15',
            'reflexao' => '<p>Reflexão do dia sobre caridade e renúncia.</p>',
            'status' => AgendaDia::STATUS_PUBLICADO,
        ]);

        $resp = $this->get(route('agenda.show', '2026-07-15'));

        $resp->assertOk();
        $resp->assertSee('application/ld+json', false);
        $resp->assertSee('"@type":"Article"', false);
        $resp->assertSee('"@type":"BreadcrumbList"', false);
        $resp->assertSee('rel="canonical"', false);
        // Organization CEMA como author/publisher do Article.
        $resp->assertSee('Centro Espírita Maria Madalena', false);
    }

    public function test_show_nao_duplica_og_type(): void
    {
        AgendaDia::factory()->create([
            'data' => '2026-07-16',
            'status' => AgendaDia::STATUS_PUBLICADO,
        ]);

        $resp = $this->get(route('agenda.show', '2026-07-16'));

        // O layout já emite og:type=website; a agenda não pode emitir og:type=article.
        $resp->assertDontSee('content="article"', false);
    }

    public function test_dia_sem_conteudo_tem_noindex(): void
    {
        // Data válida (futuro) sem AgendaDia publicado → 200 + noindex (a casca da Task 11 já trata isso).
        $resp = $this->get(route('agenda.show', '2026-12-25'));

        $resp->assertOk();
        $resp->assertSee('name="robots" content="noindex"', false);
    }

    public function test_dia_publicado_nao_tem_noindex(): void
    {
        AgendaDia::factory()->create([
            'data' => '2026-07-17',
            'status' => AgendaDia::STATUS_PUBLICADO,
        ]);

        $resp = $this->get(route('agenda.show', '2026-07-17'));

        $resp->assertDontSee('content="noindex"', false);
    }
}
