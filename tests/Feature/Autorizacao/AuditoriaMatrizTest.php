<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-13

namespace Tests\Feature\Autorizacao;

use App\Filament\Pages\MatrizCapacidades;
use Database\Seeders\CapacidadesSeeder;
use Database\Seeders\EstruturaCemaSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuditoriaMatrizTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        (new EstruturaCemaSeeder)->run();
        $this->seed(CapacidadesSeeder::class);
        $this->actingAsAdmin();
        Filament::setCurrentPanel(Filament::getPanel('admin')); // porta = admin
    }

    public function test_salvar_capacidade_loga_adicao_com_porta_admin(): void
    {
        Livewire::test(MatrizCapacidades::class)
            ->fillForm(['diretor.palestra.editar' => true])
            ->call('salvar')
            ->assertHasNoFormErrors();

        $diretor = Role::findByName('diretor', 'web');
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'autorizacao',
            'description' => 'capacidades do papel diretor alteradas',
            'subject_type' => $diretor->getMorphClass(),
            'subject_id' => $diretor->id,
        ]);

        $props = Activity::query()->where('log_name', 'autorizacao')
            ->where('subject_id', $diretor->id)->latest('id')->first()->properties->toArray();
        $this->assertContains('palestra.editar', $props['diff']['adicionados']);
        $this->assertSame('admin', $props['porta']);
        $this->assertArrayHasKey('ip', $props);
        $this->assertArrayHasKey('user_agent', $props);
    }

    public function test_salvar_desmarcando_loga_remocao(): void
    {
        Role::findByName('diretor', 'web')->givePermissionTo('palestra.editar');

        Livewire::test(MatrizCapacidades::class)
            ->fillForm(['diretor.palestra.editar' => false])
            ->call('salvar')
            ->assertHasNoFormErrors();

        $diretor = Role::findByName('diretor', 'web');
        $props = Activity::query()->where('log_name', 'autorizacao')
            ->where('subject_id', $diretor->id)->latest('id')->first()->properties->toArray();
        $this->assertContains('palestra.editar', $props['diff']['removidos']);
    }

    public function test_salvar_sem_mudanca_nao_loga(): void
    {
        Livewire::test(MatrizCapacidades::class)
            ->call('salvar')
            ->assertHasNoFormErrors();

        $this->assertSame(0, DB::table('activity_log')->where('log_name', 'autorizacao')->count());
    }

    public function test_pivo_por_console_nao_loga_autorizacao(): void
    {
        // Escrita direta (fora da Página) NÃO passa pelo log manual — fronteira conhecida do SPEC §8.
        Role::findByName('diretor', 'web')->givePermissionTo('palestra.editar');

        $this->assertSame(0, DB::table('activity_log')->where('log_name', 'autorizacao')->count());
    }
}
