<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-15

namespace Tests\Feature\Autorizacao;

use App\Livewire\Conta\AgendaConta;
use App\Models\AgendaDia;
use App\Models\Departamento;
use App\Models\User;
use App\Support\Autorizacao\AuditoriaAutorizacao;
use Database\Seeders\EstruturaCemaSeeder;
use Database\Seeders\TiposConteudoSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuditoriaAgendaPortaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstruturaCemaSeeder::class);
        $this->seed(TiposConteudoSeeder::class);   // config de acesso por tipo (agenda => DED+DECOM)
        foreach (['agenda.ver', 'agenda.criar', 'agenda.editar'] as $p) {
            Permission::findOrCreate($p, 'web');
        }
        Role::findByName('diretor', 'web')->syncPermissions(['agenda.ver', 'agenda.criar', 'agenda.editar']);
        AuditoriaAutorizacao::usarPorta(null); // isolamento entre testes
    }

    protected function tearDown(): void
    {
        AuditoriaAutorizacao::usarPorta(null);
        parent::tearDown();
    }

    public function test_porta_default_e_sistema_fora_do_painel(): void
    {
        $this->assertSame('sistema', AuditoriaAutorizacao::porta());
    }

    public function test_override_de_porta_vence_o_default(): void
    {
        AuditoriaAutorizacao::usarPorta('perfil');
        $this->assertSame('perfil', AuditoriaAutorizacao::porta());
    }

    public function test_criar_pelo_site_grava_porta_perfil_e_nao_loga_depto(): void
    {
        $decom = Departamento::where('sigla', 'DECOM')->value('id');
        $user = User::factory()->create();
        $user->assignRole('diretor');
        $user->departamentos()->sync([$decom]);
        AgendaDia::factory()->create(['data' => '2020-01-01'])->departamentos()->sync([$decom]); // p/ a aba abrir

        Livewire::actingAs($user)->test(AgendaConta::class)
            ->call('novo')
            ->fillForm(['data' => '2027-07-07', 'status' => AgendaDia::STATUS_PUBLICADO, 'reflexao' => '<p>x</p>'])
            ->call('salvar')
            ->assertHasNoFormErrors();

        $novo = AgendaDia::whereDate('data', '2027-07-07')->firstOrFail();

        // Entrada automática do trait (atributos) com porta perfil:
        $auto = Activity::where('log_name', 'agenda')->where('subject_id', $novo->id)->where('event', 'created')->first();
        $this->assertNotNull($auto);
        $this->assertSame('perfil', $auto->properties['porta']);

        // O sync forçado de depto saiu (decisão 7 / §6.4): não há mais entrada manual.
        // Trava inversa — se alguém devolver o sync + registrarDepartamentosConteudo, isto fica vermelho.
        $this->assertSame(0, Activity::where('log_name', 'agenda')
            ->where('subject_id', $novo->id)
            ->whereNull('event')
            ->count(), 'sem sync de depto, não há entrada manual');
    }

    public function test_editar_nao_gera_log_manual_de_depto(): void
    {
        $decom = Departamento::where('sigla', 'DECOM')->value('id');
        $ded = Departamento::where('sigla', 'DED')->value('id');
        $user = User::factory()->create();
        $user->assignRole('diretor');
        $user->departamentos()->sync([$decom]);
        $ag = AgendaDia::factory()->create(['data' => '2020-02-02']);
        $ag->departamentos()->sync([$ded, $decom]); // no escopo do user (interseção DECOM)

        Livewire::actingAs($user)->test(AgendaConta::class)
            ->call('editar', $ag->id)
            ->fillForm(['meta_dia_titulo' => 'Editado'])
            ->call('salvar')
            ->assertHasNoFormErrors();

        // Edição NÃO mexe em depto ⇒ nenhuma entrada manual (só a 'updated' automática do trait).
        $manual = Activity::where('log_name', 'agenda')->where('subject_id', $ag->id)->whereNull('event')->count();
        $this->assertSame(0, $manual);
    }

    public function test_porta_reflete_o_painel_quando_sem_override(): void
    {
        AuditoriaAutorizacao::usarPorta(null);
        $painel = Filament::getDefaultPanel(); // painel /admin
        Filament::setCurrentPanel($painel);

        $this->assertSame($painel->getId(), AuditoriaAutorizacao::porta()); // 'admin'
    }
}
