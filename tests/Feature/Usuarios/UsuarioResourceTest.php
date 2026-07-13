<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-03

namespace Tests\Feature\Usuarios;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Models\Departamento;
use App\Models\User;
use Database\Seeders\EstruturaCemaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UsuarioResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        (new EstruturaCemaSeeder)->run();
        $this->actingAsAdmin();
    }

    public function test_admin_acessa_listagem_de_usuarios(): void
    {
        $this->get('/admin/users')->assertSuccessful();
    }

    public function test_form_do_admin_salva_o_papel(): void
    {
        $trabalhador = Role::findByName('trabalhador');

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Fulano de Teste',
                'email' => 'fulano@teste.com',
                'password' => 'senha-super-forte-2026',
                'roles' => [$trabalhador->id],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $user = User::where('email', 'fulano@teste.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('trabalhador'));
    }

    public function test_form_do_admin_salva_departamentos(): void
    {
        $trabalhador = Role::findByName('trabalhador', 'web');
        $decom = Departamento::where('sigla', 'DECOM')->first();

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Diretor do DECOM',
                'email' => 'decom@teste.com',
                'password' => 'senha-super-forte-2026',
                'roles' => [$trabalhador->id],
                'departamentos' => [$decom->id],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $user = User::where('email', 'decom@teste.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->departamentos->contains($decom));
    }
}
