<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-13

namespace Tests\Feature\Autorizacao;

use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AuditoriaInfraTest extends TestCase
{
    use RefreshDatabase;

    public function test_tabela_activity_log_existe_com_colunas_esperadas(): void
    {
        $this->assertTrue(Schema::hasTable('activity_log'));
        $this->assertTrue(Schema::hasColumns('activity_log', [
            'log_name', 'description', 'subject_type', 'subject_id',
            'causer_type', 'causer_id', 'properties', 'event', 'batch_uuid',
        ]));
    }

    public function test_entrada_ida_e_volta(): void
    {
        activity('teste')->log('ping');

        $this->assertDatabaseHas('activity_log', ['log_name' => 'teste', 'description' => 'ping']);
    }

    public function test_retencao_inerte_e_nao_agendada(): void
    {
        $this->assertSame(3650, config('activitylog.delete_records_older_than_days'));
    }

    public function test_painel_admin_nao_registra_resource_de_activity(): void
    {
        foreach (Filament::getPanel('admin')->getResources() as $resource) {
            $this->assertNotSame(Activity::class, $resource::getModel());
        }
    }
}
