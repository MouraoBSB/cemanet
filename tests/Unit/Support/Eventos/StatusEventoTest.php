<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-08

namespace Tests\Unit\Support\Eventos;

use App\Support\Eventos\StatusEvento;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

class StatusEventoTest extends TestCase
{
    private string $hoje = '2026-06-27';

    // Nome "status" colide com o método final PHPUnit\Framework\TestCase::status() (PHPUnit 12).
    private function selo(string $ini, ?string $fim = null): array
    {
        // Mesmo fuso do StatusEvento (senão o offset de 3h mascara erros de limite de dia).
        return StatusEvento::para($ini, $fim, Carbon::parse($this->hoje, StatusEvento::FUSO));
    }

    public function test_encerrado_quando_fim_passou(): void
    {
        $s = $this->selo('2026-06-20', '2026-06-25');
        $this->assertSame('passado', $s['estado']);
        $this->assertSame('Encerrado', $s['rotulo']);
    }

    public function test_acontecendo_agora_so_multi_dia_em_curso(): void
    {
        $s = $this->selo('2026-06-26', '2026-06-28'); // hoje 27, dentro do intervalo
        $this->assertSame('acontecendo', $s['estado']);
        $this->assertSame('Acontecendo agora', $s['rotulo']);
    }

    public function test_evento_de_um_dia_hoje_e_e_hoje_nao_acontecendo(): void
    {
        $s = $this->selo('2026-06-27', '2026-06-27'); // 1 dia, hoje
        $this->assertSame('É hoje', $s['rotulo']); // NÃO "Acontecendo agora"
    }

    public function test_amanha_faltam_e_em_n_dias(): void
    {
        $this->assertSame('É amanhã', $this->selo('2026-06-28')['rotulo']);
        $this->assertSame('Faltam 5 dias', $this->selo('2026-07-02')['rotulo']);
        $this->assertSame('Em 30 dias', $this->selo('2026-07-27')['rotulo']);
    }

    public function test_cor_texto_para_contraste(): void
    {
        $this->assertSame('#FFFFFF', $this->selo('2026-06-20', '2026-06-25')['cor_texto']); // Encerrado (fundo escuro)
        $this->assertSame('#26242E', $this->selo('2026-06-28')['cor_texto']);               // É amanhã (fundo claro)
        $this->assertSame('#26242E', $this->selo('2026-07-27')['cor_texto']);               // Em N dias (fundo claro)
    }
}
