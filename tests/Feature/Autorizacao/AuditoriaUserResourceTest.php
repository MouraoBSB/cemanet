<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-13

namespace Tests\Feature\Autorizacao;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\Departamento;
use App\Models\User;
use Database\Seeders\CapacidadesSeeder;
use Database\Seeders\EstruturaCemaSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuditoriaUserResourceTest extends TestCase
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

    public function test_criar_usuario_com_papel_e_departamento_loga_duas_entradas(): void
    {
        $diretor = Role::findByName('diretor', 'web');
        $decom = Departamento::where('sigla', 'DECOM')->first();

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Fulano de Teste',
                'email' => 'fulano@teste.com',
                'password' => 'senha-super-forte-2026',
                'roles' => [$diretor->id],
                'departamentos' => [$decom->id],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $u = User::where('email', 'fulano@teste.com')->first();

        $papel = Activity::query()->where('log_name', 'autorizacao')->where('subject_id', $u->id)
            ->where('description', 'papel do usuário alterado')->first();
        $this->assertNotNull($papel);
        $this->assertSame(['diretor'], $papel->properties->toArray()['diff']['adicionados']);
        $this->assertSame('admin', $papel->properties->toArray()['porta']);

        $deptos = Activity::query()->where('log_name', 'autorizacao')->where('subject_id', $u->id)
            ->where('description', 'departamentos do usuário alterados')->first();
        $this->assertNotNull($deptos);
        $this->assertSame(
            [['id' => $decom->id, 'nome' => $decom->nome]],
            $deptos->properties->toArray()['diff']['adicionados'],
        );
    }

    public function test_editar_troca_papel_loga_diff(): void
    {
        $u = User::factory()->create();
        $u->assignRole('diretor');
        $trabalhador = Role::findByName('trabalhador', 'web');

        Livewire::test(EditUser::class, ['record' => $u->getKey()])
            ->fillForm(['roles' => [$trabalhador->id]])
            ->call('save')
            ->assertHasNoFormErrors();

        $props = Activity::query()->where('log_name', 'autorizacao')->where('subject_id', $u->id)
            ->where('description', 'papel do usuário alterado')->latest('id')->first()->properties->toArray();
        $this->assertSame(['trabalhador'], $props['diff']['adicionados']);
        $this->assertSame(['diretor'], $props['diff']['removidos']);
    }

    public function test_editar_troca_departamento_loga_diff_id_nome(): void
    {
        // Fecha a rede: exercita registrarDepartamentosUsuario pelo fluxo do B1 (save() override), o mais arriscado.
        $u = User::factory()->create();
        $u->assignRole('diretor');
        $decom = Departamento::where('sigla', 'DECOM')->first();
        $outro = Departamento::where('id', '!=', $decom->id)->first();
        $u->departamentos()->sync([$decom->id]); // estado inicial: DECOM

        Livewire::test(EditUser::class, ['record' => $u->getKey()])
            ->fillForm(['departamentos' => [$outro->id]]) // troca DECOM -> outro
            ->call('save')
            ->assertHasNoFormErrors();

        $props = Activity::query()->where('log_name', 'autorizacao')->where('subject_id', $u->id)
            ->where('description', 'departamentos do usuário alterados')->latest('id')->first()->properties->toArray();
        $this->assertSame([['id' => $outro->id, 'nome' => $outro->nome]], $props['diff']['adicionados']);
        $this->assertSame([['id' => $decom->id, 'nome' => $decom->nome]], $props['diff']['removidos']);
    }

    public function test_editar_sem_mudar_papel_ou_departamento_nao_loga_autorizacao(): void
    {
        $u = User::factory()->create();
        $u->assignRole('diretor');
        $antes = DB::table('activity_log')->where('log_name', 'autorizacao')->count();

        Livewire::test(EditUser::class, ['record' => $u->getKey()])
            ->fillForm(['name' => 'Nome Alterado'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($antes, DB::table('activity_log')->where('log_name', 'autorizacao')->count());
    }
}
