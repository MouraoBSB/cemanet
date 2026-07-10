<?php

namespace Tests\Feature\Front;

use App\Models\Palestra;
use App\Support\Palestras\FeedIcs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class FeedIcsTest extends TestCase
{
    use RefreshDatabase;

    public function test_escapar_trata_caracteres_especiais_e_quebras(): void
    {
        $this->assertSame('a\\;b\\,c\\\\d', FeedIcs::escapar('a;b,c\\d'));
        $this->assertSame('L1\\nL2', FeedIcs::escapar("L1\r\nL2"));
    }

    public function test_vevento_usa_hora_real_domingo_19h(): void
    {
        $p = Palestra::factory()->create([
            'titulo' => 'Palestra Dominical',
            'online' => false,
            'duracao' => null,
            'status' => Palestra::STATUS_PUBLICADO,
            'data_da_palestra' => Carbon::create(2026, 6, 21, 19, 0, 0, 'America/Sao_Paulo'), // domingo
        ])->load(['palestrantesAtivos', 'assuntos']);

        $linhas = FeedIcs::vevento($p);

        $this->assertContains('BEGIN:VEVENT', $linhas);
        $this->assertContains('DTSTART:20260621T220000Z', $linhas);
        $this->assertContains('DTEND:20260621T233000Z', $linhas);
        $this->assertContains('UID:palestra-'.$p->id.'@cemanet.org.br', $linhas);
        $this->assertContains('SUMMARY:Palestra Dominical', $linhas);
        $this->assertTrue((bool) collect($linhas)->first(fn ($l) => str_starts_with($l, 'LOCATION:Centro Espírita')));
    }

    public function test_vevento_usa_hora_real_segunda_20h(): void
    {
        $p = Palestra::factory()->create([
            'online' => true,
            'duracao' => null,
            'status' => Palestra::STATUS_PUBLICADO,
            'data_da_palestra' => Carbon::create(2026, 6, 22, 20, 0, 0, 'America/Sao_Paulo'), // segunda 20h
        ])->load(['palestrantesAtivos', 'assuntos']);

        $linhas = FeedIcs::vevento($p);

        $this->assertContains('DTSTART:20260622T230000Z', $linhas); // 20h SP => 23h UTC
        $this->assertTrue((bool) collect($linhas)->first(fn ($l) => str_starts_with($l, 'LOCATION:Online')));
    }

    public function test_documento_embrulha_em_vcalendar_com_crlf_e_pula_sem_data(): void
    {
        $comData = Palestra::factory()->create([
            'titulo' => 'Com Data',
            'status' => Palestra::STATUS_PUBLICADO,
            'data_da_palestra' => Carbon::create(2026, 6, 21, 19, 0, 0, 'America/Sao_Paulo'),
        ])->load(['palestrantesAtivos', 'assuntos']);
        $semData = Palestra::factory()->create([
            'titulo' => 'Sem Data',
            'status' => Palestra::STATUS_PUBLICADO,
            'data_da_palestra' => null,
        ])->load(['palestrantesAtivos', 'assuntos']);

        $doc = FeedIcs::documento([$comData, $semData]);

        $this->assertStringStartsWith('BEGIN:VCALENDAR', $doc);
        $this->assertStringContainsString('PRODID:'.FeedIcs::PRODID, $doc);
        $this->assertStringContainsString("\r\n", $doc);
        $this->assertStringContainsString('SUMMARY:Com Data', $doc);
        $this->assertStringNotContainsString('Sem Data', $doc);
        $this->assertSame(1, substr_count($doc, 'BEGIN:VEVENT'));
        $this->assertStringEndsWith("END:VCALENDAR\r\n", $doc);
    }

    public function test_dobrar_quebra_por_octetos_sem_partir_multibyte(): void
    {
        // 'é' = 2 octetos; "X:" + 60×'é' = 122 octetos → precisa dobrar.
        $linha = 'X:'.str_repeat('é', 60);

        $dobrada = FeedIcs::dobrar($linha);

        foreach (explode("\r\n", $dobrada) as $fisica) {
            $this->assertLessThanOrEqual(75, strlen($fisica), "Linha física excede 75 octetos: {$fisica}");
        }
        // desdobrar (remover CRLF+espaço) reconstrói o original — nenhum 'é' foi partido ao meio.
        $this->assertSame($linha, str_replace("\r\n ", '', $dobrada));
    }

    public function test_documento_dobra_location_longo_preservando_utf8(): void
    {
        // Presencial → LOCATION ~94 octetos (com em-dash "—" e acentos) que excede 75 e precisa dobrar.
        $p = Palestra::factory()->create([
            'titulo' => 'Palestra Presencial',
            'online' => false,
            'status' => Palestra::STATUS_PUBLICADO,
            'data_da_palestra' => Carbon::create(2026, 6, 21, 19, 0, 0, 'America/Sao_Paulo'),
        ])->load(['palestrantesAtivos', 'assuntos']);

        $doc = FeedIcs::documento([$p]);

        // (0) houve dobra de fato (continuação CRLF+espaço presente).
        $this->assertStringContainsString("\r\n ", $doc);
        // (1) nenhuma linha física excede 75 octetos.
        foreach (explode("\r\n", $doc) as $fisica) {
            $this->assertLessThanOrEqual(75, strlen($fisica), "Linha física excede 75 octetos: {$fisica}");
        }
        // (2) desdobrar reconstrói o LOCATION original intacto (em-dash/acentos não partidos).
        $desdobrado = str_replace("\r\n ", '', $doc);
        $localEscapado = FeedIcs::escapar('Centro Espírita Maria Madalena — Quadra 02, Lote 16, Vila Vicentina, Planaltina, DF');
        $this->assertStringContainsString('LOCATION:'.$localEscapado, $desdobrado);
    }

    public function test_vevento_carimba_dtstamp_e_sequence_do_updated_at(): void
    {
        $p = Palestra::factory()->create([
            'status' => Palestra::STATUS_PUBLICADO,
            'data_da_palestra' => Carbon::create(2026, 6, 21, 19, 0, 0, 'America/Sao_Paulo'),
        ])->load(['palestrantesAtivos', 'assuntos']);

        $linhas = FeedIcs::vevento($p);

        // DTSTAMP/SEQUENCE vêm de updated_at (determinístico), não de now().
        $this->assertContains('DTSTAMP:'.$p->updated_at->copy()->utc()->format('Ymd\THis\Z'), $linhas);
        $this->assertContains('SEQUENCE:'.$p->updated_at->getTimestamp(), $linhas);
    }
}
