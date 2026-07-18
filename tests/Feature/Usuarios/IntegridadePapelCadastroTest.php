<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-17

namespace Tests\Feature\Usuarios;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\Cargo;
use App\Models\Setor;
use App\Models\User;
use Database\Seeders\EstruturaCemaSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class IntegridadePapelCadastroTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        (new EstruturaCemaSeeder)->run();
        $this->actingAsAdmin();
        Filament::setCurrentPanel(Filament::getPanel('admin')); // porta = admin
    }

    private function papel(string $slug): Role
    {
        return Role::findByName($slug, 'web');
    }

    private function medium(): Setor
    {
        return Setor::where('slug', 'medium')->firstOrFail();
    }

    private function cargo(): Cargo
    {
        return Cargo::where('nome', 'Diretor do DED')->firstOrFail();
    }

    // --- I0: a transação está ligada nas duas páginas (guardrail do bloqueador O1) ---

    public function test_ambas_paginas_ligam_a_transacao(): void
    {
        foreach ([CreateUser::class, EditUser::class] as $pagina) {
            $default = (new \ReflectionClass($pagina))->getDefaultProperties()['hasDatabaseTransactions'] ?? null;
            $this->assertTrue($default, "{$pagina} deve declarar \$hasDatabaseTransactions = true (senão a trava vaza).");
        }
    }

    // --- I1/I2 (create): reprova E nada persiste (o gate é o assertDatabaseMissing) ---

    public function test_create_frequentador_com_setor_e_abortado_e_nada_persiste(): void
    {
        $c = Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Inválido R1',
                'email' => 'r1@teste.com',
                'password' => 'senha-super-forte-2026',
                'roles' => [$this->papel('frequentador')->id],
                'setores' => [$this->medium()->id],
            ])
            ->call('create');

        // GATE DURO primeiro, SEM encadear: o inválido NÃO foi gravado (rollback real — depende de I0).
        // Se a chave 'data.roles' estiver errada, é o assertHasFormErrors (abaixo) que falha, não este.
        $this->assertDatabaseMissing('users', ['email' => 'r1@teste.com']);

        $c->assertHasFormErrors(['roles']); // SECUNDÁRIO (UX)
    }

    public function test_create_trabalhador_com_cargo_e_abortado_e_nada_persiste(): void
    {
        $c = Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Inválido R2',
                'email' => 'r2@teste.com',
                'password' => 'senha-super-forte-2026',
                'roles' => [$this->papel('trabalhador')->id],
                'cargos' => [$this->cargo()->id],
            ])
            ->call('create');

        $this->assertDatabaseMissing('users', ['email' => 'r2@teste.com']); // gate duro 1º, sem encadear

        $c->assertHasFormErrors(['roles']);
    }

    // --- I6/I7: casos válidos salvam (sem falso-positivo) ---

    public function test_create_admin_com_setor_e_cargo_salva(): void
    {
        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Admin Completo',
                'email' => 'admin2@teste.com',
                'password' => 'senha-super-forte-2026',
                'roles' => [$this->papel('administrador')->id],
                'setores' => [$this->medium()->id],
                'cargos' => [$this->cargo()->id],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('users', ['email' => 'admin2@teste.com']);
    }

    public function test_create_trabalhador_com_setor_salva(): void
    {
        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Trabalhador Médium',
                'email' => 'trab@teste.com',
                'password' => 'senha-super-forte-2026',
                'roles' => [$this->papel('trabalhador')->id],
                'setores' => [$this->medium()->id],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $u = User::where('email', 'trab@teste.com')->first();
        $this->assertNotNull($u);
        $this->assertTrue($u->setores->contains($this->medium()));
    }

    public function test_create_diretor_com_cargo_salva(): void
    {
        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Diretor com Cargo',
                'email' => 'dir@teste.com',
                'password' => 'senha-super-forte-2026',
                'roles' => [$this->papel('diretor')->id],
                'cargos' => [$this->cargo()->id],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('users', ['email' => 'dir@teste.com']);
    }

    // --- I3: morde ao REBAIXAR; o pivô setor_usuario não fica stale ---

    public function test_edit_rebaixar_trabalhador_com_setor_e_abortado_pivo_intacto(): void
    {
        $u = User::factory()->create();
        $u->assignRole('trabalhador');
        $u->setores()->attach($this->medium()->id); // estado inicial VÁLIDO

        $c = Livewire::test(EditUser::class, ['record' => $u->getKey()])
            ->fillForm(['roles' => [$this->papel('frequentador')->id]]) // rebaixa, mantém o setor
            ->call('save');

        // GATE DURO primeiro, sem encadear: papel NÃO virou frequentador e o setor continua no pivô.
        $this->assertTrue($u->fresh()->hasRole('trabalhador'));
        $this->assertFalse($u->fresh()->hasRole('frequentador'));
        $this->assertDatabaseHas('setor_usuario', ['user_id' => $u->id, 'setor_id' => $this->medium()->id]);

        $c->assertHasFormErrors(['roles']);
    }

    // --- I4: morde ao ADICIONAR ---

    public function test_edit_adicionar_setor_a_frequentador_e_abortado(): void
    {
        $u = User::factory()->create();
        $u->assignRole('frequentador'); // sem setor/cargo (válido)

        $c = Livewire::test(EditUser::class, ['record' => $u->getKey()])
            ->fillForm(['setores' => [$this->medium()->id]]) // tenta ganhar setor sendo frequentador
            ->call('save');

        $this->assertDatabaseMissing('setor_usuario', ['user_id' => $u->id, 'setor_id' => $this->medium()->id]);

        $c->assertHasFormErrors(['roles']);
    }

    // --- I5: POST forjado (UI "desligada") não fura ---

    public function test_estado_forjado_com_ui_desligada_nao_fura(): void
    {
        // Seta o estado direto (como um POST forjado que ignora qualquer reação de UI).
        $c = Livewire::test(CreateUser::class)
            ->set('data.name', 'Forjado')
            ->set('data.email', 'forjado@teste.com')
            ->set('data.password', 'senha-super-forte-2026')
            ->set('data.roles', [$this->papel('frequentador')->id])
            ->set('data.setores', [$this->medium()->id])
            ->call('create');

        $this->assertDatabaseMissing('users', ['email' => 'forjado@teste.com']); // gate duro 1º, sem encadear

        $c->assertHasFormErrors(['roles']);
    }

    // --- I8: auditoria atômica — o abort não deixa log órfão (prova a conexão do activity_log) ---

    public function test_edit_abortado_nao_deixa_log_orfao(): void
    {
        $u = User::factory()->create(['name' => 'Original']);
        $u->assignRole('trabalhador');
        $u->setores()->attach($this->medium()->id);
        $logsAntes = DB::table('activity_log')->where('subject_id', $u->id)->count();

        Livewire::test(EditUser::class, ['record' => $u->getKey()])
            ->fillForm([
                'name' => 'Alterado',                          // dispara o auto-log (LogsActivity) do User
                'roles' => [$this->papel('frequentador')->id], // rebaixa com setor => R1 => abort
            ])
            ->call('save')
            ->assertHasFormErrors(['roles']);

        // O auto-log do 'name' rodou dentro da transação; o rollback o desfez. Se activity_log
        // estivesse em conexão separada, teria sobrado um log órfão aqui.
        $this->assertSame('Original', $u->fresh()->name);
        $this->assertSame($logsAntes, DB::table('activity_log')->where('subject_id', $u->id)->count());
    }
}
