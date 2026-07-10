<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-09

namespace Tests\Feature\Calendario;

use App\Enums\VisibilidadeEvento;
use App\Livewire\Calendario\Calendario;
use App\Models\Evento;
use App\Models\Palestra;
use App\Models\User;
use App\Support\Calendario\OcorrenciaCalendario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CalendarioLivewireTest extends TestCase
{
    use RefreshDatabase;

    private function semear(): void
    {
        $quando = Carbon::now()->addDays(10);
        Palestra::factory()->create(['status' => 'publicado', 'titulo' => 'Palestra X', 'slug' => 'px', 'data_da_palestra' => $quando->copy()->setTime(19, 0)]);
        Evento::create(['titulo' => 'Evento Y', 'slug' => 'ey', 'data_inicio' => $quando->toDateString(), 'visibilidade' => VisibilidadeEvento::Publico, 'status' => Evento::STATUS_PUBLICADO]);
        Evento::create(['titulo' => 'Reunião Secreta', 'slug' => 'rs', 'data_inicio' => $quando->toDateString(), 'visibilidade' => VisibilidadeEvento::Diretoria, 'status' => Evento::STATUS_PUBLICADO]);
    }

    private function diretor(): User
    {
        Role::updateOrCreate(['name' => 'diretor', 'guard_name' => 'web'], ['nivel' => 30]);
        $u = User::factory()->create();
        $u->assignRole('diretor');

        return $u;
    }

    public function test_todos_intercala_palestra_e_evento_publicos(): void
    {
        $this->semear();
        Livewire::test(Calendario::class)
            ->assertSee('Palestra X')
            ->assertSee('Evento Y')
            ->assertDontSee('Reunião Secreta'); // anônimo não vê restrito
    }

    public function test_filtro_tipo_isola_a_fonte(): void
    {
        $this->semear();
        Livewire::test(Calendario::class)->set('tipo', 'palestras')
            ->assertSee('Palestra X')->assertDontSee('Evento Y');
        Livewire::test(Calendario::class)->set('tipo', 'eventos')
            ->assertSee('Evento Y')->assertDontSee('Palestra X');
    }

    public function test_diretor_ve_evento_restrito(): void
    {
        $this->semear();
        Livewire::actingAs($this->diretor())->test(Calendario::class)
            ->assertSee('Reunião Secreta');
    }

    public function test_troca_de_tipo_preserva_o_mes_focado_quando_ainda_valido(): void
    {
        // M1 (now+10): palestra + evento; M2 (mês seguinte): SÓ palestra.
        // Focar M2 (não é o mês-padrão, que seria M1) e trocar p/ 'palestras':
        // M2 continua existindo entre as palestras → o mês focado deve ser PRESERVADO.
        // (Reset incondicional cairia em M1 e este teste pegaria a regressão.)
        $this->semear();
        $foco = Carbon::now()->addDays(10)->addMonthNoOverflow()->startOfMonth()->addDays(14);
        Palestra::factory()->create(['status' => 'publicado', 'titulo' => 'Palestra Futura', 'slug' => 'pf', 'data_da_palestra' => $foco->copy()->setTime(19, 0)]);
        $mesFoco = $foco->format('Y-m');

        Livewire::test(Calendario::class)
            ->set('mes', $mesFoco)
            ->set('tipo', 'palestras')
            ->assertSet('mes', $mesFoco);
    }

    public function test_troca_de_tipo_que_invalida_o_mes_cai_no_padrao(): void
    {
        // M2 existe SÓ via evento; ao trocar p/ 'palestras' o mês some do conjunto
        // → cai no mês-padrão (M1, primeiro ascendente no modo 'proximas').
        $this->semear();
        $foco = Carbon::now()->addDays(10)->addMonthNoOverflow()->startOfMonth()->addDays(14);
        Evento::create(['titulo' => 'Evento Futuro', 'slug' => 'ef', 'data_inicio' => $foco->toDateString(), 'visibilidade' => VisibilidadeEvento::Publico, 'status' => Evento::STATUS_PUBLICADO]);

        Livewire::test(Calendario::class)
            ->set('mes', $foco->format('Y-m'))
            ->set('tipo', 'palestras')
            ->assertSet('mes', Carbon::now()->addDays(10)->format('Y-m'));
    }

    public function test_selo_de_visibilidade_so_para_logado_em_evento_restrito(): void
    {
        $this->semear();
        // anônimo não vê o restrito (logo, nem o selo)
        Livewire::test(Calendario::class)->assertDontSee('Somente diretoria');
        // diretor vê o card restrito COM o selo
        Livewire::actingAs($this->diretor())->test(Calendario::class)
            ->assertSee('Reunião Secreta')->assertSee('Somente diretoria');
    }

    public function test_contador_do_mes_respeita_visibilidade_e_pluraliza_em_pt(): void
    {
        $this->semear(); // 1 evento público + 1 evento restrito no mesmo mês (+ 1 palestra)
        // anônimo (tipo=eventos): só o público conta
        Livewire::test(Calendario::class)->set('tipo', 'eventos')->assertSee('1 item');
        // diretor: os DOIS eventos contam → "2 itens". Asserção POSITIVA pega o bug do Str::plural (inglês → "2 items").
        Livewire::actingAs($this->diretor())->test(Calendario::class)->set('tipo', 'eventos')->assertSee('2 itens');
    }

    public function test_proxima_e_o_dto_cronologicamente_mais_cedo_entre_fontes(): void
    {
        // Evento Y é "dia inteiro" (inicia 00h); Palestra X é às 19h do mesmo dia → o evento vem primeiro.
        $this->semear();
        $proxima = Livewire::test(Calendario::class)->viewData('proxima');

        $this->assertInstanceOf(OcorrenciaCalendario::class, $proxima);
        $this->assertSame('evento', $proxima->tipo);
    }

    public function test_modo_realizadas_alterna_conjunto_e_reseta_mes(): void
    {
        Palestra::factory()->create(['status' => 'publicado', 'data_da_palestra' => Carbon::now()->addMonths(2)->setTime(19, 0)]); // futura
        Palestra::factory()->create(['status' => 'publicado', 'data_da_palestra' => Carbon::now()->subMonths(2)->setTime(19, 0)]); // passada

        $c = Livewire::test(Calendario::class);
        $mesProximas = $c->viewData('mesFoco');

        $c->set('modo', 'realizadas'); // dispara updatedModo → reseta $mes
        $c->assertSet('modo', 'realizadas');
        $this->assertNotSame($mesProximas, $c->viewData('mesFoco'));
        $this->assertSame(Carbon::now()->subMonths(2)->format('Y-m'), $c->viewData('mesFoco'));
    }

    public function test_navegacao_de_mes_respeita_limites(): void
    {
        // Âncoras deterministicas (mesmo padrão dos testes acima): sempre no futuro, meses distintos.
        $mes1 = Carbon::now()->addDays(10)->setTime(19, 0);
        $mes2 = Carbon::now()->addDays(10)->addMonthNoOverflow()->startOfMonth()->addDays(14)->setTime(19, 0);
        $mes3 = Carbon::now()->addDays(10)->addMonthsNoOverflow(2)->startOfMonth()->addDays(14)->setTime(19, 0);
        Palestra::factory()->create(['status' => 'publicado', 'data_da_palestra' => $mes1]);
        Palestra::factory()->create(['status' => 'publicado', 'data_da_palestra' => $mes2]);
        Palestra::factory()->create(['status' => 'publicado', 'data_da_palestra' => $mes3]);

        $c = Livewire::test(Calendario::class)->set('tipo', 'palestras');
        $this->assertSame($mes1->format('Y-m'), $c->viewData('mesFoco'));
        $this->assertFalse($c->viewData('temAnterior'));
        $this->assertTrue($c->viewData('temProximo'));

        $c->call('mesProximo');
        $this->assertSame($mes2->format('Y-m'), $c->viewData('mesFoco'));

        $c->call('mesProximo');
        $this->assertSame($mes3->format('Y-m'), $c->viewData('mesFoco'));
        $this->assertFalse($c->viewData('temProximo'));

        $c->call('mesProximo'); // topo: não avança além do limite
        $this->assertSame($mes3->format('Y-m'), $c->viewData('mesFoco'));

        $c->call('mesAnterior');
        $this->assertSame($mes2->format('Y-m'), $c->viewData('mesFoco'));
    }

    public function test_mini_calendario_marca_dia_com_ocorrencia(): void
    {
        $quando = Carbon::now()->addDays(10)->setTime(20, 0);
        Palestra::factory()->create(['status' => 'publicado', 'titulo' => 'Dia Marcado', 'data_da_palestra' => $quando]);

        $matriz = Livewire::test(Calendario::class)->set('tipo', 'palestras')->viewData('matriz');

        $dia = collect($matriz['dias'])->firstWhere('dia', (int) $quando->format('j'));
        $this->assertNotNull($dia);
        $this->assertNotEmpty($dia['ocorrencias']);
        $this->assertSame('Dia Marcado', $dia['ocorrencias'][0]['titulo']);
        $this->assertNotNull($dia['ancora']);
    }

    public function test_mini_grid_nao_acende_dia_de_evento_restrito_para_anonimo(): void
    {
        // Crown-jewel: a matriz deriva das ocorrências JÁ filtradas por visibilidade. Um evento
        // 'diretoria' num dia isolado NÃO pode acender a célula para o anônimo — rede explícita
        // contra uma refatoração futura que monte a matriz por query própria e reabra o vazamento.
        $base = Carbon::now()->addMonthNoOverflow()->startOfMonth();
        $diaRestrito = $base->copy()->addDays(9);  // dia 10: só o evento restrito
        $diaPublico = $base->copy()->addDays(19);  // dia 20: evento público (ancora o mês p/ o anônimo)

        Evento::create(['titulo' => 'Reunião Reservada', 'slug' => 'reuniao-reservada', 'data_inicio' => $diaRestrito->toDateString(),
            'visibilidade' => VisibilidadeEvento::Diretoria, 'status' => Evento::STATUS_PUBLICADO]);
        Evento::create(['titulo' => 'Feirão Aberto', 'slug' => 'feirao-aberto', 'data_inicio' => $diaPublico->toDateString(),
            'visibilidade' => VisibilidadeEvento::Publico, 'status' => Evento::STATUS_PUBLICADO]);

        $mes = $base->format('Y-m');
        $diaR = (int) $diaRestrito->day;

        // Anônimo: a célula do dia restrito fica APAGADA (o mês existe pelo evento público).
        $matrizAnon = Livewire::test(Calendario::class)->set('tipo', 'eventos')->set('mes', $mes)->viewData('matriz');
        $celulaAnon = collect($matrizAnon['dias'])->firstWhere('dia', $diaR);
        $this->assertSame([], $celulaAnon['ocorrencias']);
        $this->assertNull($celulaAnon['ancora']);

        // Diretor: a MESMA célula acende.
        $matrizDir = Livewire::actingAs($this->diretor())->test(Calendario::class)->set('tipo', 'eventos')->set('mes', $mes)->viewData('matriz');
        $celulaDir = collect($matrizDir['dias'])->firstWhere('dia', $diaR);
        $this->assertNotEmpty($celulaDir['ocorrencias']);
        $this->assertNotNull($celulaDir['ancora']);
    }
}
