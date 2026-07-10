<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-09

namespace Tests\Feature\Calendario;

use App\Models\Palestra;
use App\Support\Calendario\Fontes\PalestrasFonte;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PalestrasFonteTest extends TestCase
{
    use RefreshDatabase;

    private function palestra(array $o = []): Palestra
    {
        return Palestra::factory()->create(array_merge([
            'status' => 'publicado',
            'data_da_palestra' => Carbon::now()->addDays(7)->setTime(19, 0),
        ], $o));
    }

    public function test_proximas_e_realizadas_separam_por_data(): void
    {
        $this->palestra(['slug' => 'futura', 'data_da_palestra' => Carbon::now()->addDays(10)->setTime(19, 0)]);
        $this->palestra(['slug' => 'passada', 'data_da_palestra' => Carbon::now()->subDays(10)->setTime(19, 0)]);

        $fonte = new PalestrasFonte;

        $this->assertCount(1, $fonte->ocorrencias((int) now()->addDays(10)->year, (int) now()->addDays(10)->month, 'proximas', null));
        $this->assertSame('palestra', $fonte->tipo());
    }

    public function test_ocorrencia_vira_dto_com_hora_e_sem_visibilidade(): void
    {
        $quando = Carbon::now()->addDays(5)->setTime(19, 0);
        $this->palestra(['slug' => 'p1', 'titulo' => 'Mediunidade', 'data_da_palestra' => $quando]);

        $oc = (new PalestrasFonte)->ocorrencias((int) $quando->year, (int) $quando->month, 'proximas', null)->first();

        $this->assertSame('palestra', $oc->tipo);
        $this->assertTrue($oc->temHora);
        $this->assertNull($oc->fim);
        $this->assertNull($oc->seloVisibilidade);
        $this->assertStringContainsString('Mediunidade', $oc->titulo);
    }

    public function test_proxima_retorna_a_mais_proxima_futura(): void
    {
        $this->palestra(['slug' => 'depois', 'data_da_palestra' => Carbon::now()->addDays(20)->setTime(19, 0)]);
        $this->palestra(['slug' => 'antes', 'titulo' => 'A Próxima', 'data_da_palestra' => Carbon::now()->addDays(3)->setTime(19, 0)]);

        $this->assertStringContainsString('A Próxima', (new PalestrasFonte)->proxima(null)->titulo);
    }

    public function test_meses_separam_proximas_de_realizadas_por_modo(): void
    {
        $futura = Carbon::now()->addMonths(2)->startOfMonth()->setTime(19, 0);
        $passada = Carbon::now()->subMonths(2)->startOfMonth()->setTime(19, 0);
        $this->palestra(['slug' => 'fut', 'data_da_palestra' => $futura]);
        $this->palestra(['slug' => 'pas', 'data_da_palestra' => $passada]);

        $fonte = new PalestrasFonte;

        $this->assertSame([$futura->format('Y-m')], $fonte->meses('proximas', null));
        $this->assertSame([$passada->format('Y-m')], $fonte->meses('realizadas', null));
    }
}
