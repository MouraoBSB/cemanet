<?php

namespace Tests\Feature\Front;

use App\Livewire\Palestras\Calendario;
use App\Models\Palestra;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class CalendarioComponentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::create(2026, 7, 1, 12, 0, 0)); // fronteira fixa (tz do app)
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // NOTA (Livewire v4.3.2): o Testable NÃO tem `assertViewHas`, mas TEM `viewData($key)`
    // (retorna getView()->getData()[$key]) — mesma API já usada na archive mergeada. Aferimos os
    // dados de render DIRETAMENTE pelo dado (proxima, mesFoco, palestrasDoMes com eh_*, matriz,
    // temAnterior/temProximo) e usamos `assertSet` para estado público (modo/mes) onde couber.

    public function test_destaque_usa_proxima_futura_sem_fallback(): void
    {
        Palestra::factory()->create(['titulo' => 'Passada', 'status' => Palestra::STATUS_PUBLICADO, 'data_da_palestra' => Carbon::create(2026, 6, 15, 19, 0)]);
        Palestra::factory()->create(['titulo' => 'Futura', 'status' => Palestra::STATUS_PUBLICADO, 'data_da_palestra' => Carbon::create(2026, 7, 5, 19, 0)]);

        $proxima = Livewire::test(Calendario::class)->viewData('proxima');

        $this->assertNotNull($proxima);
        $this->assertSame('Futura', $proxima->titulo);
    }

    public function test_sem_futura_destaque_e_nulo(): void
    {
        Palestra::factory()->create(['titulo' => 'Só Passada', 'status' => Palestra::STATUS_PUBLICADO, 'data_da_palestra' => Carbon::create(2026, 6, 15, 19, 0)]);

        $this->assertNull(Livewire::test(Calendario::class)->viewData('proxima')); // sem fallback
    }

    public function test_modo_realizadas_alterna_conjunto_e_reseta_mes(): void
    {
        Palestra::factory()->create(['status' => Palestra::STATUS_PUBLICADO, 'data_da_palestra' => Carbon::create(2026, 8, 2, 19, 0)]);  // futura
        Palestra::factory()->create(['status' => Palestra::STATUS_PUBLICADO, 'data_da_palestra' => Carbon::create(2026, 5, 10, 19, 0)]); // passada

        $c = Livewire::test(Calendario::class);
        $this->assertSame('2026-08', $c->viewData('mesFoco'));   // proximas: mês da futura

        $c->set('modo', 'realizadas');                            // dispara updatedModo → reseta $mes
        $c->assertSet('modo', 'realizadas');
        $this->assertSame('2026-05', $c->viewData('mesFoco'));   // realizadas: mês mais recente do passado
    }

    public function test_navegacao_de_mes_respeita_limites(): void
    {
        Palestra::factory()->create(['status' => Palestra::STATUS_PUBLICADO, 'data_da_palestra' => Carbon::create(2026, 7, 5, 19, 0)]);
        Palestra::factory()->create(['status' => Palestra::STATUS_PUBLICADO, 'data_da_palestra' => Carbon::create(2026, 8, 9, 19, 0)]);
        Palestra::factory()->create(['status' => Palestra::STATUS_PUBLICADO, 'data_da_palestra' => Carbon::create(2026, 9, 6, 19, 0)]);

        $c = Livewire::test(Calendario::class);
        $this->assertSame('2026-07', $c->viewData('mesFoco'));
        $this->assertFalse($c->viewData('temAnterior'));
        $this->assertTrue($c->viewData('temProximo'));

        $c->call('mesProximo');
        $this->assertSame('2026-08', $c->viewData('mesFoco'));

        $c->call('mesProximo');
        $this->assertSame('2026-09', $c->viewData('mesFoco'));
        $this->assertFalse($c->viewData('temProximo'));

        $c->call('mesProximo'); // topo: não avança além do limite
        $this->assertSame('2026-09', $c->viewData('mesFoco'));

        $c->call('mesAnterior');
        $this->assertSame('2026-08', $c->viewData('mesFoco'));
    }

    public function test_palestra_realizada_mais_cedo_hoje_e_marcada_sem_orfa(): void
    {
        // Fronteira now() consistente: palestra de hoje 09h (já passou às 12h) → Realizada+gravada, não órfã.
        // Sob a fronteira antiga (startOfDay) eh_realizada seria FALSE → sem marca (órfã). Aferimos PELO DADO.
        Palestra::factory()->create([
            'titulo' => 'Hoje Cedo',
            'status' => Palestra::STATUS_PUBLICADO,
            'link_youtube' => 'https://youtube.com/live/abc1234',
            'data_da_palestra' => Carbon::create(2026, 7, 1, 9, 0),
        ]);
        Palestra::factory()->create([
            'titulo' => 'Ainda Vem',
            'status' => Palestra::STATUS_PUBLICADO,
            'data_da_palestra' => Carbon::create(2026, 7, 5, 19, 0),
        ]);

        $c = Livewire::test(Calendario::class);

        // não é a próxima
        $this->assertSame('Ainda Vem', $c->viewData('proxima')->titulo);

        // em Próximas, o mês em foco (julho) lista AMBAS; marcas aferidas pelo dado
        $col = $c->viewData('palestrasDoMes');
        $hoje = $col->firstWhere('titulo', 'Hoje Cedo');
        $futura = $col->firstWhere('titulo', 'Ainda Vem');

        $this->assertNotNull($hoje);
        $this->assertTrue((bool) $hoje->eh_realizada);   // realizada mais cedo hoje (fronteira now())
        $this->assertTrue((bool) $hoje->tem_gravacao);   // realizada + youtube
        $this->assertFalse((bool) $hoje->eh_proxima);    // não é a próxima (não órfã)
        $this->assertTrue((bool) $futura->eh_proxima);
        $this->assertFalse((bool) $futura->eh_realizada);
    }

    public function test_mini_calendario_marca_dia_nao_domingo(): void
    {
        // 2026-06-22 é uma SEGUNDA-feira (20h) → dia com palestra no mini-calendário (não assume domingo).
        Palestra::factory()->create(['titulo' => 'Segunda 20h', 'status' => Palestra::STATUS_PUBLICADO, 'data_da_palestra' => Carbon::create(2026, 6, 22, 20, 0)]);

        $c = Livewire::test(Calendario::class)->set('modo', 'realizadas'); // 22/jun é passado (now = 01/jul)
        $this->assertSame('2026-06', $c->viewData('mesFoco'));

        $matriz = $c->viewData('matriz');
        $dia22 = collect($matriz['dias'])->firstWhere('dia', 22); // 22/jun/2026 = segunda-feira
        $this->assertNotNull($dia22);
        $this->assertNotNull($dia22['palestra']);
        $this->assertSame('Segunda 20h', $dia22['palestra']['titulo']);
    }
}
