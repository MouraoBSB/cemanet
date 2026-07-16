<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-16

namespace Tests\Feature\Autorizacao;

use App\Enums\RegimeAcesso;
use App\Models\Departamento;
use App\Models\TipoConteudo;
use App\Models\User;
use App\Support\Autorizacao\AcessoPorTipo;
use Database\Seeders\EstruturaCemaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AcessoPorTipoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        (new EstruturaCemaSeeder)->run();
    }

    private function idDe(string $sigla): int
    {
        return Departamento::where('sigla', $sigla)->value('id');
    }

    private function usuarioEm(string ...$siglas): User
    {
        $u = User::factory()->create();
        $u->departamentos()->sync(array_map(fn (string $s): int => $this->idDe($s), $siglas));

        return $u;
    }

    private function tipo(string $recurso, RegimeAcesso $regime, array $siglas = []): TipoConteudo
    {
        $tipo = TipoConteudo::create(['recurso' => $recurso, 'regime' => $regime]);
        $tipo->departamentos()->sync(array_map(fn (string $s): int => $this->idDe($s), $siglas));

        return $tipo;
    }

    private function servico(): AcessoPorTipo
    {
        return app(AcessoPorTipo::class);
    }

    // --- I2: recurso sem linha ⇒ fecha, não explode ---

    public function test_recurso_sem_linha_devolve_regime_null_e_nao_lanca(): void
    {
        $this->assertNull($this->servico()->regime('agenda'));
    }

    public function test_recurso_sem_linha_nao_habilita_ninguem(): void
    {
        $usuario = $this->usuarioEm('DED');

        $this->assertFalse($this->servico()->usuarioHabilitadoNoTipo($usuario, 'agenda'));
    }

    public function test_recurso_sem_linha_devolve_lista_vazia_de_responsaveis(): void
    {
        $this->assertSame([], $this->servico()->departamentosResponsaveis('agenda'));
    }

    // --- I1: config vazia nunca permite ---

    public function test_do_tipo_sem_responsaveis_nao_habilita_ninguem(): void
    {
        $this->tipo('agenda', RegimeAcesso::DoTipo, []);
        $usuario = $this->usuarioEm('DED');

        $this->assertFalse($this->servico()->usuarioHabilitadoNoTipo($usuario, 'agenda'));
    }

    // --- "do tipo": só quem está num depto responsável ---

    public function test_do_tipo_habilita_quem_esta_em_depto_responsavel(): void
    {
        $this->tipo('agenda', RegimeAcesso::DoTipo, ['DED', 'DECOM']);

        $this->assertTrue($this->servico()->usuarioHabilitadoNoTipo($this->usuarioEm('DED'), 'agenda'));
    }

    public function test_do_tipo_nega_usuario_de_depto_disjunto(): void
    {
        $this->tipo('agenda', RegimeAcesso::DoTipo, ['DED']);

        $this->assertFalse($this->servico()->usuarioHabilitadoNoTipo($this->usuarioEm('DEPRO'), 'agenda'));
    }

    public function test_do_tipo_nega_usuario_sem_nenhum_departamento(): void
    {
        $this->tipo('agenda', RegimeAcesso::DoTipo, ['DED']);

        $this->assertFalse($this->servico()->usuarioHabilitadoNoTipo(User::factory()->create(), 'agenda'));
    }

    // --- I4: "por registro" = tem algum depto (o filtro do objeto é do trait, não daqui) ---

    public function test_por_registro_habilita_quem_tem_algum_departamento(): void
    {
        $this->tipo('evento', RegimeAcesso::PorRegistro, []);

        $this->assertTrue($this->servico()->usuarioHabilitadoNoTipo($this->usuarioEm('DEPRO'), 'evento'));
    }

    public function test_por_registro_nega_quem_nao_tem_departamento(): void
    {
        $this->tipo('evento', RegimeAcesso::PorRegistro, []);

        $this->assertFalse($this->servico()->usuarioHabilitadoNoTipo(User::factory()->create(), 'evento'));
    }

    public function test_responsaveis_devolve_os_ids(): void
    {
        $this->tipo('agenda', RegimeAcesso::DoTipo, ['DED', 'DECOM']);

        $ids = $this->servico()->departamentosResponsaveis('agenda');
        sort($ids);
        $esperado = [$this->idDe('DECOM'), $this->idDe('DED')];
        sort($esperado);

        $this->assertSame($esperado, $ids);
    }

    // --- §6.5: memo por escopo, e o escopo morre ---

    public function test_memo_evita_reconsultar_o_banco_no_mesmo_escopo(): void
    {
        $this->tipo('agenda', RegimeAcesso::DoTipo, ['DED']);
        $servico = $this->servico();
        $servico->regime('agenda');   // aquece o memo

        DB::enableQueryLog();
        $servico->regime('agenda');
        $servico->departamentosResponsaveis('agenda');

        $this->assertCount(0, DB::getQueryLog(), 'o memo não segurou: reconsultou o banco');
        DB::disableQueryLog();
    }

    public function test_recurso_ausente_tambem_e_memoizado(): void
    {
        // o serviço é scoped: app() devolve a MESMA instância dentro do teste
        $this->servico()->regime('agenda');   // null

        DB::enableQueryLog();
        $this->servico()->regime('agenda');

        $this->assertCount(0, DB::getQueryLog(), 'o null não foi memoizado (usou ??= em vez de array_key_exists?)');
        DB::disableQueryLog();
    }

    /** É o teste que reprova o binding singleton (o worker preservaria a instância entre jobs). */
    public function test_o_memo_morre_com_o_escopo(): void
    {
        $this->tipo('agenda', RegimeAcesso::DoTipo, ['DED']);
        $primeiro = $this->servico();
        $this->assertSame(RegimeAcesso::DoTipo, $primeiro->regime('agenda'));

        TipoConteudo::where('recurso', 'agenda')->first()->update(['regime' => RegimeAcesso::PorRegistro]);

        app()->forgetScopedInstances();
        $segundo = $this->servico();

        $this->assertNotSame($primeiro, $segundo, 'a instância sobreviveu ao escopo — binding é singleton?');
        $this->assertSame(RegimeAcesso::PorRegistro, $segundo->regime('agenda'));
    }
}
