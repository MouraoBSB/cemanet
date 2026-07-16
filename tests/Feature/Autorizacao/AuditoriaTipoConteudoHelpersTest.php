<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-16

namespace Tests\Feature\Autorizacao;

use App\Enums\RegimeAcesso;
use App\Models\TipoConteudo;
use App\Support\Autorizacao\AuditoriaAutorizacao;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AuditoriaTipoConteudoHelpersTest extends TestCase
{
    use RefreshDatabase;

    private function tipo(): TipoConteudo
    {
        return TipoConteudo::create(['recurso' => 'agenda', 'regime' => RegimeAcesso::DoTipo]);
    }

    private function ultimaEntrada(): array
    {
        return Activity::query()->where('log_name', 'autorizacao')->latest('id')->first()->properties->toArray();
    }

    public function test_regime_alterado_gera_entrada_com_diff_de_nomes(): void
    {
        $tipo = $this->tipo();

        AuditoriaAutorizacao::registrarRegimeTipo($tipo, 'do_tipo', 'por_registro');

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'autorizacao',
            'description' => 'regime do tipo agenda alterado',
            'subject_type' => $tipo->getMorphClass(),
            'subject_id' => $tipo->id,
        ]);

        $props = $this->ultimaEntrada();
        $this->assertSame(['por_registro'], $props['diff']['adicionados']);
        $this->assertSame(['do_tipo'], $props['diff']['removidos']);
        $this->assertArrayHasKey('porta', $props);
        $this->assertArrayHasKey('ip', $props);
        $this->assertArrayHasKey('user_agent', $props);
    }

    public function test_regime_sem_mudanca_nao_gera_entrada(): void
    {
        AuditoriaAutorizacao::registrarRegimeTipo($this->tipo(), 'do_tipo', 'do_tipo');

        $this->assertSame(0, DB::table('activity_log')->where('log_name', 'autorizacao')->count());
    }

    public function test_regime_de_linha_nova_registra_so_a_adicao(): void
    {
        AuditoriaAutorizacao::registrarRegimeTipo($this->tipo(), null, 'do_tipo');

        $props = $this->ultimaEntrada();
        $this->assertSame(['do_tipo'], $props['diff']['adicionados']);
        $this->assertSame([], $props['diff']['removidos']);
    }

    public function test_responsaveis_alterados_geram_diff_por_id_com_nome(): void
    {
        $tipo = $this->tipo();

        AuditoriaAutorizacao::registrarDepartamentosTipo($tipo, [3 => 'Estudos Doutrinários'], [3 => 'Estudos Doutrinários', 8 => 'Comunicação e Multimídia']);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'autorizacao',
            'description' => 'departamentos responsáveis do tipo agenda alterados',
            'subject_id' => $tipo->id,
        ]);

        $props = $this->ultimaEntrada();
        $this->assertSame([['id' => 8, 'nome' => 'Comunicação e Multimídia']], $props['diff']['adicionados']);
        $this->assertSame([], $props['diff']['removidos']);
    }

    public function test_responsavel_removido_carrega_o_nome_de_antes(): void
    {
        AuditoriaAutorizacao::registrarDepartamentosTipo($this->tipo(), [3 => 'Estudos Doutrinários'], []);

        $props = $this->ultimaEntrada();
        $this->assertSame([['id' => 3, 'nome' => 'Estudos Doutrinários']], $props['diff']['removidos']);
        $this->assertSame([], $props['diff']['adicionados']);
    }

    public function test_responsaveis_sem_mudanca_nao_geram_entrada(): void
    {
        AuditoriaAutorizacao::registrarDepartamentosTipo($this->tipo(), [3 => 'Estudos Doutrinários'], [3 => 'Estudos Doutrinários']);

        $this->assertSame(0, DB::table('activity_log')->where('log_name', 'autorizacao')->count());
    }
}
