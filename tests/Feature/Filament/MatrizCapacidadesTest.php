<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-12

namespace Tests\Feature\Filament;

use App\Filament\Pages\MatrizCapacidades;
use App\Models\Departamento;
use App\Models\Palestra;
use App\Models\User;
use Database\Seeders\CapacidadesSeeder;
use Database\Seeders\EstruturaCemaSeeder;
use Database\Seeders\TiposConteudoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MatrizCapacidadesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        (new EstruturaCemaSeeder)->run();   // 4 papéis (web) + 8 departamentos + cargos
        $this->seed(CapacidadesSeeder::class); // as permissions do glossário (web)
        $this->seed(TiposConteudoSeeder::class);   // o Select de regime é required (Camada 1/E1)
        $this->actingAsAdmin();
    }

    public function test_renderiza(): void
    {
        $this->get('/admin/matriz-capacidades')->assertOk();
    }

    public function test_nao_admin_nao_acessa_a_pagina(): void
    {
        $diretor = User::factory()->create();
        $diretor->assignRole('diretor');

        $this->actingAs($diretor)->get('/admin/matriz-capacidades')->assertForbidden();
    }

    public function test_salvar_atribui_e_remove_permissao_do_papel(): void
    {
        Livewire::test(MatrizCapacidades::class)
            ->fillForm(['diretor.palestra.editar' => true])
            ->call('salvar')
            ->assertHasNoFormErrors();

        $this->assertTrue(Role::findByName('diretor', 'web')->hasPermissionTo('palestra.editar'));

        // desmarca e salva de novo => syncPermissions([]) faz o detach
        Livewire::test(MatrizCapacidades::class)
            ->fillForm(['diretor.palestra.editar' => false])
            ->call('salvar')
            ->assertHasNoFormErrors();

        $this->assertFalse(Role::findByName('diretor', 'web')->hasPermissionTo('palestra.editar'));
    }

    public function test_abre_com_pre_marca_do_estado_atual(): void
    {
        Role::findByName('diretor', 'web')->givePermissionTo('post.criar');

        Livewire::test(MatrizCapacidades::class)
            ->assertFormSet([
                'diretor.post.criar' => true,
                'diretor.post.editar' => false,
            ]);
    }

    public function test_salvar_nao_toca_admin_nem_frequentador(): void
    {
        // mesmo que o state do form contenha chaves de papéis fora de PAPEIS_EDITAVEIS
        // (fillForm seta pela chave, sem exigir campo no schema), salvar() as ignora.
        Livewire::test(MatrizCapacidades::class)
            ->fillForm([
                'diretor.palestra.editar' => true,
                'trabalhador.post.criar' => true,
                'frequentador.palestra.editar' => true,
                'administrador.post.criar' => true,
            ])
            ->call('salvar')
            ->assertHasNoFormErrors();

        $this->assertSame(0, Role::findByName('frequentador', 'web')->permissions()->count());
        $this->assertSame(0, Role::findByName('administrador', 'web')->permissions()->count());
    }

    public function test_salvar_concede_capacidade_que_a_policy_consome(): void
    {
        // DED é o responsável por 'palestra' na semente da config (TiposConteudoSeeder).
        $ded = Departamento::where('sigla', 'DED')->first();

        Livewire::test(MatrizCapacidades::class)
            ->fillForm(['diretor.palestra.editar' => true])
            ->call('salvar')
            ->assertHasNoFormErrors();

        $diretor = User::factory()->create();
        $diretor->assignRole('diretor');
        $diretor->departamentos()->sync([$ded->id]);

        $palestra = Palestra::factory()->create();
        $palestra->departamentos()->sync([$ded->id]);

        $this->assertTrue(Gate::forUser($diretor)->check('editar', $palestra));
    }
}
