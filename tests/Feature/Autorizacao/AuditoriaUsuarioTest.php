<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-13

namespace Tests\Feature\Autorizacao;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AuditoriaUsuarioTest extends TestCase
{
    use RefreshDatabase;

    public function test_atualizar_coluna_auditada_gera_entrada_usuario(): void
    {
        $u = User::factory()->create(['ativo' => true]);
        $u->update(['ativo' => false]);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'usuario',
            'event' => 'updated',
            'description' => 'usuário atualizado',
            'subject_type' => $u->getMorphClass(),
            'subject_id' => $u->id,
        ]);

        $props = Activity::query()->where('log_name', 'usuario')->where('event', 'updated')
            ->latest('id')->first()->properties->toArray();
        $this->assertFalse((bool) $props['attributes']['ativo']);
        $this->assertTrue((bool) $props['old']['ativo']);
        $this->assertSame('sistema', $props['porta']);
    }

    public function test_criacao_e_exclusao_geram_entradas(): void
    {
        $u = User::factory()->create();
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'usuario', 'event' => 'created', 'subject_id' => $u->id,
        ]);

        $id = $u->id;
        $u->delete();
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'usuario', 'event' => 'deleted', 'subject_id' => $id,
        ]);
    }

    public function test_mudar_so_coluna_fora_das_cinco_nao_loga(): void
    {
        $u = User::factory()->create();
        Activity::query()->delete(); // limpa a entrada 'created'

        $u->update(['email_verified_at' => now()]);

        $this->assertSame(0, Activity::query()->count());
    }

    public function test_porta_admin_quando_no_painel(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $u = User::factory()->create();

        $props = Activity::query()->where('log_name', 'usuario')->where('event', 'created')
            ->latest('id')->first()->properties->toArray();
        $this->assertSame('admin', $props['porta']);
    }
}
