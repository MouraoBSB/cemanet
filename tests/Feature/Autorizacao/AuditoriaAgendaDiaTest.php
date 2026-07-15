<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-15

namespace Tests\Feature\Autorizacao;

use App\Models\AgendaDia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AuditoriaAgendaDiaTest extends TestCase
{
    use RefreshDatabase;

    public function test_editar_campo_gera_entrada_com_os_7_campos_no_escopo(): void
    {
        $ag = AgendaDia::factory()->create(['status' => AgendaDia::STATUS_RASCUNHO]);
        Activity::query()->delete(); // ignora o 'created' do factory

        $ag->update(['status' => AgendaDia::STATUS_PUBLICADO, 'reflexao' => '<p>nova</p>']);

        $atividade = Activity::where('log_name', 'agenda')->latest('id')->first();
        $this->assertNotNull($atividade);
        $this->assertSame('updated', $atividade->event);
        $this->assertArrayHasKey('status', $atividade->changes()['attributes']);
        $this->assertArrayHasKey('reflexao', $atividade->changes()['attributes']);
    }

    public function test_save_sem_mudanca_nao_gera_entrada(): void
    {
        $ag = AgendaDia::factory()->create();
        Activity::query()->delete();

        $ag->save(); // nada dirty

        $this->assertSame(0, Activity::where('log_name', 'agenda')->count());
    }

    public function test_entrada_carrega_porta_no_properties(): void
    {
        $ag = AgendaDia::factory()->create();

        $atividade = Activity::where('log_name', 'agenda')->first();
        $this->assertNotNull($atividade);
        $this->assertArrayHasKey('porta', $atividade->properties->toArray());
    }
}
