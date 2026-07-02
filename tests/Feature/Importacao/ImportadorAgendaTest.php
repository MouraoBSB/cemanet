<?php

namespace Tests\Feature\Importacao;

use App\Importacao\ImportadorAgenda;
use App\Importacao\LeitorAgenda;
use App\Models\AgendaDia;
use App\Models\AgendaMetaMes;
use App\Models\AgendaSlugLegado;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportadorAgendaTest extends TestCase
{
    use RefreshDatabase;

    private function leitorFake(): LeitorAgenda
    {
        return new class implements LeitorAgenda
        {
            public function entradas(): array
            {
                return [
                    // maio: slug numérico (= post ID), glossary já resolvido pelo leitor
                    [
                        'data' => '2026-05-01', 'wp_id' => 27057, 'post_name' => '27057',
                        'reflexao' => '<p>Reflexão de 1º de maio</p>',
                        'mes_titulo' => 'Desenvolver abnegação, renúncia e solidariedade',
                        'mes_texto' => '<p>Citação do dia (maio)</p>',
                        'meta_dia_titulo' => 'Desenvolver Abnegação',
                        'meta_dia_texto' => '<p>Meta do dia</p>',
                        'prece' => '<p>Prece de maio</p>',
                        'avisos' => [],
                    ],
                    // agosto: original (ID menor, slug limpo)
                    [
                        'data' => '2026-08-05', 'wp_id' => 30001, 'post_name' => '05-de-agosto-de-2026',
                        'reflexao' => '<p>Reflexão de 5 de agosto (A)</p>',
                        'mes_titulo' => 'Desenvolver a caridade moral',
                        'mes_texto' => '<p>Citação A</p>',
                        'meta_dia_titulo' => 'Caridade',
                        'meta_dia_texto' => '<p>Meta A</p>',
                        'prece' => '<p>Prece A</p>',
                        'avisos' => [],
                    ],
                    // agosto DUPLICADO na mesma data -> dedupe: 1 AgendaDia, 2 slugs 301
                    [
                        'data' => '2026-08-05', 'wp_id' => 30002, 'post_name' => '05-de-agosto-de-2026-2',
                        'reflexao' => '<p>Reflexão duplicada (B)</p>',
                        'mes_titulo' => 'Desenvolver a caridade moral',
                        'mes_texto' => '<p>Citação B</p>',
                        'meta_dia_titulo' => 'Caridade',
                        'meta_dia_texto' => '<p>Meta B</p>',
                        'prece' => '<p>Prece B</p>',
                        'avisos' => [],
                    ],
                    // glossary não resolvido pelo leitor -> mes_titulo/meta_dia_titulo null + aviso carregado
                    [
                        'data' => '2026-09-01', 'wp_id' => 31000, 'post_name' => 'setembro-2026-slug',
                        'reflexao' => '<p>Reflexão de setembro</p>',
                        'mes_titulo' => null,
                        'mes_texto' => '<p>Citação de setembro</p>',
                        'meta_dia_titulo' => null,
                        'meta_dia_texto' => '<p>Meta de setembro</p>',
                        'prece' => '<p>Prece de setembro</p>',
                        'avisos' => ["[setembro-2026-slug] Chave de glossary não resolvida: 'setembro_2026' (gravado null)."],
                    ],
                ];
            }
        };
    }

    public function test_importa_e_e_idempotente_com_dedupe_glossary_e_meta_do_mes(): void
    {
        $importador = new ImportadorAgenda($this->leitorFake());

        // roda 2x -> idempotência (contagens não duplicam)
        $importador->importar();
        $resumo = $importador->importar();

        // 3 datas (05/08 deduplicado), 2 metas de mês, 4 slugs
        $this->assertSame(3, AgendaDia::count());
        $this->assertSame(2, AgendaMetaMes::count());
        $this->assertSame(4, AgendaSlugLegado::count());
        $this->assertSame(['metas' => 2, 'dias' => 3, 'slugs' => 4], [
            'metas' => $resumo['metas'], 'dias' => $resumo['dias'], 'slugs' => $resumo['slugs'],
        ]);

        // dedupe: 05/08 = 1 dia (conteúdo do slug limpo/original) e 2 slugs para a mesma data
        $this->assertSame(1, AgendaDia::where('data', '2026-08-05')->count());
        $this->assertSame(2, AgendaSlugLegado::where('data', '2026-08-05')->count());
        $this->assertStringContainsString('(A)', AgendaDia::where('data', '2026-08-05')->value('reflexao'));

        // meta do mês criada por ano+mes
        $this->assertSame(
            'Desenvolver abnegação, renúncia e solidariedade',
            AgendaMetaMes::where('ano', 2026)->where('mes', 5)->value('titulo'),
        );

        // glossary não resolvido: sem meta de setembro; meta_dia_titulo gravado null
        $this->assertFalse(AgendaMetaMes::where('ano', 2026)->where('mes', 9)->exists());
        $this->assertNull(AgendaDia::where('data', '2026-09-01')->value('meta_dia_titulo'));

        // avisos: o carregado pelo leitor (glossary) + o do dedupe estão no resumo
        $this->assertTrue(collect($resumo['avisos'])->contains(fn ($a) => str_contains($a, 'glossary não resolvida')));
        $this->assertTrue(collect($resumo['avisos'])->contains(fn ($a) => str_contains($a, '[dedupe]')));

        // status default publicado; conteúdo HTML sanitizado pelo mutator do model, texto preservado
        $this->assertSame(AgendaDia::STATUS_PUBLICADO, AgendaDia::where('data', '2026-05-01')->value('status'));
        $this->assertStringContainsString('1º de maio', AgendaDia::where('data', '2026-05-01')->value('reflexao'));
    }
}
