<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-13

namespace Tests\Feature\Autorizacao;

use App\Models\User;
use App\Support\Autorizacao\AuditoriaAutorizacao;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AuditoriaHelperTest extends TestCase
{
    use RefreshDatabase;

    public function test_diff_calcula_adicionados_e_removidos(): void
    {
        $diff = AuditoriaAutorizacao::diff(['a', 'b'], ['b', 'c']);

        $this->assertSame(['c'], $diff['adicionados']);
        $this->assertSame(['a'], $diff['removidos']);
    }

    public function test_porta_e_sistema_sem_painel_corrente(): void
    {
        $this->assertSame('sistema', AuditoriaAutorizacao::porta());
    }

    public function test_porta_e_admin_com_painel_admin_corrente(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->assertSame('admin', AuditoriaAutorizacao::porta());
    }

    public function test_registrar_papel_usuario_sem_mudanca_nao_loga(): void
    {
        $u = User::factory()->create();

        AuditoriaAutorizacao::registrarPapelUsuario($u, ['diretor'], ['diretor']);

        $this->assertSame(0, DB::table('activity_log')->where('log_name', 'autorizacao')->count());
    }

    public function test_registrar_papel_usuario_loga_diff_e_contexto(): void
    {
        $u = User::factory()->create();

        AuditoriaAutorizacao::registrarPapelUsuario($u, ['diretor'], ['trabalhador']);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'autorizacao',
            'description' => 'papel do usuário alterado',
            'subject_type' => $u->getMorphClass(),
            'subject_id' => $u->id,
        ]);

        $props = Activity::query()->where('log_name', 'autorizacao')->latest('id')->first()->properties->toArray();
        $this->assertSame(['trabalhador'], $props['diff']['adicionados']);
        $this->assertSame(['diretor'], $props['diff']['removidos']);
        $this->assertSame('sistema', $props['porta']);
        $this->assertArrayHasKey('ip', $props);
        $this->assertArrayHasKey('user_agent', $props);
    }

    public function test_registrar_departamentos_usa_id_e_nome(): void
    {
        $u = User::factory()->create();

        AuditoriaAutorizacao::registrarDepartamentosUsuario($u, [3 => 'DECOM'], [3 => 'DECOM', 5 => 'DAS']);

        $props = Activity::query()->where('log_name', 'autorizacao')->latest('id')->first()->properties->toArray();
        $this->assertSame([['id' => 5, 'nome' => 'DAS']], $props['diff']['adicionados']);
        $this->assertSame([], $props['diff']['removidos']);
    }
}
