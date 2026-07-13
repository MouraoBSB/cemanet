<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-09

namespace Tests\Feature\Calendario;

use App\Enums\VisibilidadeEvento;
use App\Models\Evento;
use App\Models\User;
use App\Support\Calendario\Fontes\EventosFonte;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventosFonteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Fixa "hoje" no dia 5 do mês corrente. Os cenários usam datas relativas
        // (now()->addDays(10), now()->subDay(), etc.); sem isso, o teste multidia
        // falhava quando o dia real do mês tornava um evento "futuro" já passado.
        // Determinístico quanto ao dia-do-mês, sem prender ano/mês.
        Carbon::setTestNow(Carbon::now()->startOfMonth()->addDays(4));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function evento(array $o = []): Evento
    {
        return Evento::create(array_merge([
            'titulo' => 'Brechó', 'slug' => 'brecho',
            'data_inicio' => Carbon::now()->addDays(10)->toDateString(),
            'visibilidade' => VisibilidadeEvento::Publico, 'status' => Evento::STATUS_PUBLICADO,
        ], $o));
    }

    private function diretor(): User
    {
        Role::updateOrCreate(['name' => 'diretor', 'guard_name' => 'web'], ['nivel' => 30]);
        $u = User::factory()->create();
        $u->assignRole('diretor');

        return $u;
    }

    public function test_anonimo_nao_ve_evento_restrito_diretor_ve(): void
    {
        $mes = (int) Carbon::now()->addDays(10)->month;
        $ano = (int) Carbon::now()->addDays(10)->year;
        $this->evento(['slug' => 'reservado', 'titulo' => 'Reunião', 'visibilidade' => VisibilidadeEvento::Diretoria]);

        $fonte = new EventosFonte;

        $this->assertTrue($fonte->ocorrencias($ano, $mes, 'proximas', null)->isEmpty());
        $this->assertCount(1, $fonte->ocorrencias($ano, $mes, 'proximas', $this->diretor()));
        // e o mês nem aparece p/ anônimo
        $this->assertSame([], $fonte->meses('proximas', null));
    }

    public function test_selo_visibilidade_so_para_quem_ve_restrito(): void
    {
        $quando = Carbon::now()->addDays(10);
        $this->evento(['slug' => 'reservado', 'visibilidade' => VisibilidadeEvento::Diretoria]);

        $oc = (new EventosFonte)->ocorrencias((int) $quando->year, (int) $quando->month, 'proximas', $this->diretor())->first();

        $this->assertNotNull($oc->seloVisibilidade);
        $this->assertSame('Somente diretoria', $oc->seloVisibilidade['rotulo']);
    }

    public function test_multidia_acende_todos_os_dias_do_mes(): void
    {
        $ini = Carbon::now()->addDays(10)->startOfMonth()->addDays(9); // dia 10 do mês
        $this->evento(['slug' => 'semana', 'data_inicio' => $ini->toDateString(), 'data_fim' => $ini->copy()->addDays(2)->toDateString()]);

        $oc = (new EventosFonte)->ocorrencias((int) $ini->year, (int) $ini->month, 'proximas', null)->first();

        $this->assertSame([(int) $ini->day, (int) $ini->day + 1, (int) $ini->day + 2], $oc->diasNoMes((int) $ini->year, (int) $ini->month));
    }

    public function test_realizado_usa_coalesce_data_fim(): void
    {
        // começou ontem, termina amanhã → é PRÓXIMO (em andamento), não realizado
        $this->evento(['slug' => 'andamento', 'data_inicio' => Carbon::now()->subDay()->toDateString(), 'data_fim' => Carbon::now()->addDay()->toDateString()]);

        $fonte = new EventosFonte;
        $mes = (int) now()->month;
        $ano = (int) now()->year;

        $this->assertCount(1, $fonte->ocorrencias($ano, $mes, 'proximas', null));
        $this->assertTrue($fonte->ocorrencias($ano, $mes, 'realizadas', null)->isEmpty());
    }
}
