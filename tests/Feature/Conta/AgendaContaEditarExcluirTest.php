<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-15

namespace Tests\Feature\Conta;

use App\Livewire\Conta\AgendaConta;
use App\Models\AgendaDia;
use App\Models\Departamento;
use App\Models\User;
use Database\Seeders\EstruturaCemaSeeder;
use Database\Seeders\TiposConteudoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AgendaContaEditarExcluirTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstruturaCemaSeeder::class);
        $this->seed(TiposConteudoSeeder::class);   // config de acesso por tipo (agenda => DED+DECOM)
        foreach (['agenda.ver', 'agenda.criar', 'agenda.editar', 'agenda.excluir'] as $p) {
            Permission::findOrCreate($p, 'web');
        }
        Role::findByName('diretor', 'web')->syncPermissions(['agenda.ver', 'agenda.criar', 'agenda.editar', 'agenda.excluir']);
    }

    private function editorDe(string $sigla): User
    {
        $user = User::factory()->create();
        $user->assignRole('diretor');
        $user->departamentos()->sync([Departamento::where('sigla', $sigla)->value('id')]);

        return $user;
    }

    private function agendaEm(array $siglas, array $attrs = []): AgendaDia
    {
        $ag = AgendaDia::factory()->create($attrs);
        $ag->departamentos()->sync(Departamento::whereIn('sigla', $siglas)->pluck('id')->all());

        return $ag;
    }

    public function test_editar_altera_conteudo_e_preserva_departamentos(): void
    {
        $user = $this->editorDe('DECOM');
        $ag = $this->agendaEm(['DED', 'DECOM'], ['meta_dia_titulo' => 'Antigo', 'status' => AgendaDia::STATUS_RASCUNHO]);
        $deptosAntes = $ag->departamentos()->pluck('departamentos.id')->sort()->values()->all();

        Livewire::actingAs($user)->test(AgendaConta::class)
            ->call('editar', $ag->id)
            ->fillForm(['meta_dia_titulo' => 'Novo', 'status' => AgendaDia::STATUS_PUBLICADO])
            ->call('salvar')
            ->assertHasNoFormErrors();

        $ag->refresh();
        $this->assertSame('Novo', $ag->meta_dia_titulo);
        $this->assertSame(AgendaDia::STATUS_PUBLICADO, $ag->status);
        $this->assertSame($deptosAntes, $ag->departamentos()->pluck('departamentos.id')->sort()->values()->all());
    }

    public function test_editar_registro_de_outro_departamento_e_negado(): void
    {
        $user = $this->editorDe('DECOM');
        $this->agendaEm(['DECOM']); // registro no próprio escopo → mount() passa; o 403 vem do authorize() da AÇÃO
        $alheio = $this->agendaEm(['DED']); // sem interseção com DECOM

        Livewire::actingAs($user)->test(AgendaConta::class)
            ->call('editar', $alheio->id)
            ->assertForbidden();
    }

    public function test_excluir_remove_registro_do_escopo(): void
    {
        $user = $this->editorDe('DECOM');
        $ag = $this->agendaEm(['DED', 'DECOM']);

        Livewire::actingAs($user)->test(AgendaConta::class)
            ->call('excluir', $ag->id)
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('agenda_dias', ['id' => $ag->id]);
    }

    public function test_excluir_registro_de_outro_departamento_e_negado(): void
    {
        $user = $this->editorDe('DECOM');
        $this->agendaEm(['DECOM']); // registro no próprio escopo → mount() passa; o 403 vem do authorize() da AÇÃO
        $alheio = $this->agendaEm(['DED']);

        Livewire::actingAs($user)->test(AgendaConta::class)
            ->call('excluir', $alheio->id)
            ->assertForbidden();

        $this->assertDatabaseHas('agenda_dias', ['id' => $alheio->id]);
    }
}
