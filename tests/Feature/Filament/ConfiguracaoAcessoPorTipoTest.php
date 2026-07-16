<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-16

namespace Tests\Feature\Filament;

use App\Enums\RegimeAcesso;
use App\Filament\Pages\MatrizCapacidades;
use App\Models\Departamento;
use App\Models\TipoConteudo;
use App\Models\User;
use Database\Seeders\CapacidadesSeeder;
use Database\Seeders\EstruturaCemaSeeder;
use Database\Seeders\TiposConteudoSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ConfiguracaoAcessoPorTipoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        (new EstruturaCemaSeeder)->run();
        $this->seed(CapacidadesSeeder::class);
        $this->seed(TiposConteudoSeeder::class);
        $this->actingAsAdmin();
        Filament::setCurrentPanel(Filament::getPanel('admin'));   // porta = admin
    }

    private function idDe(string $sigla): int
    {
        return Departamento::where('sigla', $sigla)->value('id');
    }

    private function siglasDe(string $recurso): array
    {
        return TipoConteudo::where('recurso', $recurso)->first()
            ->departamentos->pluck('sigla')->sort()->values()->all();
    }

    public function test_abre_com_a_config_atual_pre_carregada(): void
    {
        Livewire::test(MatrizCapacidades::class)
            ->assertFormSet([
                'agenda.regime' => RegimeAcesso::DoTipo->value,
                'evento.regime' => RegimeAcesso::PorRegistro->value,
            ]);
    }

    public function test_as_duas_arvores_do_state_convivem(): void
    {
        // toggles ($estado[papel][recurso][acao]) e config ($estado[recurso][...]) no mesmo data
        Livewire::test(MatrizCapacidades::class)
            ->fillForm([
                'diretor.palestra.editar' => true,
                'palestra.regime' => RegimeAcesso::DoTipo->value,
                'palestra.departamentos' => [$this->idDe('DECOM')],
            ])
            ->call('salvar')
            ->assertHasNoFormErrors();

        $this->assertTrue(Role::findByName('diretor', 'web')->hasPermissionTo('palestra.editar'));
        $this->assertSame(['DECOM'], $this->siglasDe('palestra'));
    }

    public function test_salvar_grava_regime_e_responsaveis(): void
    {
        Livewire::test(MatrizCapacidades::class)
            ->fillForm(['agenda.departamentos' => [$this->idDe('DED')]])
            ->call('salvar')
            ->assertHasNoFormErrors();

        $this->assertSame(['DED'], $this->siglasDe('agenda'));
    }

    public function test_regime_vazio_reprova_e_nao_grava(): void
    {
        Livewire::test(MatrizCapacidades::class)
            ->fillForm(['agenda.regime' => null])
            ->call('salvar')
            ->assertHasFormErrors(['agenda.regime']);

        $this->assertSame(RegimeAcesso::DoTipo, TipoConteudo::where('recurso', 'agenda')->first()->regime);
        $this->assertSame(['DECOM', 'DED'], $this->siglasDe('agenda'));
    }

    /** Round-trip: trocar de regime e voltar PRESERVA os responsáveis (reprova o ->visible()). */
    public function test_round_trip_de_regime_preserva_os_responsaveis(): void
    {
        Livewire::test(MatrizCapacidades::class)
            ->fillForm(['agenda.regime' => RegimeAcesso::PorRegistro->value])
            ->call('salvar')
            ->assertHasNoFormErrors();

        $this->assertSame(['DECOM', 'DED'], $this->siglasDe('agenda'), 'os responsáveis foram apagados ao sair do "do tipo"');

        Livewire::test(MatrizCapacidades::class)
            ->fillForm(['agenda.regime' => RegimeAcesso::DoTipo->value])
            ->call('salvar')
            ->assertHasNoFormErrors();

        $this->assertSame(['DECOM', 'DED'], $this->siglasDe('agenda'));
    }

    /** O cinto server-side: no "por registro" o POST não manda nos responsáveis. */
    public function test_post_forjado_no_por_registro_nao_apaga_os_responsaveis(): void
    {
        Livewire::test(MatrizCapacidades::class)
            ->fillForm([
                'agenda.regime' => RegimeAcesso::PorRegistro->value,
                'agenda.departamentos' => [],   // forja: o cliente manda vazio
            ])
            ->call('salvar')
            ->assertHasNoFormErrors();

        $this->assertSame(['DECOM', 'DED'], $this->siglasDe('agenda'), 'o POST forjado apagou os responsáveis');
    }

    // --- I7: auditoria pela página ---

    public function test_trocar_so_o_regime_gera_1_entrada_e_nenhuma_de_responsaveis(): void
    {
        Livewire::test(MatrizCapacidades::class)
            ->fillForm(['agenda.regime' => RegimeAcesso::PorRegistro->value])
            ->call('salvar')
            ->assertHasNoFormErrors();

        $this->assertSame(1, DB::table('activity_log')->where('log_name', 'autorizacao')->count());
        $this->assertDatabaseHas('activity_log', ['description' => 'regime do tipo agenda alterado']);
    }

    public function test_trocar_so_os_responsaveis_gera_1_entrada_com_diff_por_id(): void
    {
        Livewire::test(MatrizCapacidades::class)
            ->fillForm(['agenda.departamentos' => [$this->idDe('DED'), $this->idDe('DECOM'), $this->idDe('DIJ')]])
            ->call('salvar')
            ->assertHasNoFormErrors();

        $this->assertSame(1, DB::table('activity_log')->where('log_name', 'autorizacao')->count());

        $props = Activity::query()
            ->where('log_name', 'autorizacao')->latest('id')->first()->properties->toArray();

        $this->assertSame([['id' => $this->idDe('DIJ'), 'nome' => 'Infância e Juventude']], $props['diff']['adicionados']);
        $this->assertSame([], $props['diff']['removidos']);
        $this->assertSame('admin', $props['porta']);
    }

    /** Reprova a leitura tardia do "antes": se o antes for lido DEPOIS do sync, o diff vem vazio. */
    public function test_o_antes_e_lido_do_banco_antes_do_sync(): void
    {
        Livewire::test(MatrizCapacidades::class)
            ->fillForm(['palestra.departamentos' => [$this->idDe('DED'), $this->idDe('DECOM')]])
            ->call('salvar')
            ->assertHasNoFormErrors();

        $props = Activity::query()
            ->where('log_name', 'autorizacao')->latest('id')->first()->properties->toArray();

        $this->assertSame([['id' => $this->idDe('DECOM'), 'nome' => 'Comunicação e Multimídia']], $props['diff']['adicionados']);
    }

    public function test_salvar_sem_mudar_nada_nao_loga(): void
    {
        Livewire::test(MatrizCapacidades::class)
            ->call('salvar')
            ->assertHasNoFormErrors();

        $this->assertSame(0, DB::table('activity_log')->where('log_name', 'autorizacao')->count());
    }

    public function test_nao_admin_nao_acessa(): void
    {
        $diretor = User::factory()->create();
        $diretor->assignRole('diretor');

        $this->actingAs($diretor)->get('/admin/matriz-capacidades')->assertForbidden();
    }
}
