<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-21

namespace Tests\Feature\Autorizacao;

use App\Enums\VisibilidadeMensagem;
use App\Livewire\Conta\CuradoriaConta;
use App\Livewire\Conta\MensagensConta;
use App\Models\Cargo;
use App\Models\Mensagem;
use App\Models\Setor;
use App\Models\User;
use App\Support\Autorizacao\AuditoriaAutorizacao;
use Database\Seeders\EstruturaCemaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * I14 (Fatia F4b, Task 12) — /minha-conta não é painel Filament: a porta 'perfil' TEM de ser
 * marcada em toda requisição Livewire, via `boot()` — que roda em toda hidratação, inclusive no
 * `wire:click` de salvar() — nunca em `mount()`, que só roda UMA vez, no carregamento inicial da
 * página. Molde: AuditoriaAgendaPortaTest.
 *
 * O `usarPorta(null)` entre o `test()` e a ação é o que DISCRIMINA os dois pontos: se a marcação
 * estivesse em `mount()`, este reset sobreviveria até o `salvar()` e a entrada gravaria 'sistema'.
 */
class AuditoriaMensagemPortaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstruturaCemaSeeder::class);
        AuditoriaAutorizacao::usarPorta(null); // isolamento entre testes
    }

    private function medium(): User
    {
        $user = User::factory()->create();
        $user->assignRole('trabalhador');
        $user->setores()->attach(Setor::where('slug', Setor::SLUG_MEDIUM)->value('id'), ['funcao' => 'membro']);

        return $user->fresh();
    }

    private function diretorDepae(): User
    {
        $user = User::factory()->create();
        $user->assignRole('diretor');
        $user->cargos()->attach(Cargo::where('slug', Cargo::SLUG_DIRETOR_DEPAE)->value('id'));

        return $user->fresh();
    }

    /** I14 — MensagensConta: criar pelo médium grava porta 'perfil', mesmo com o reset no meio. */
    public function test_criar_pelo_medium_grava_porta_perfil(): void
    {
        $medium = $this->medium();

        $c = Livewire::actingAs($medium)->test(MensagensConta::class); // aqui o mount/boot marcou
        AuditoriaAutorizacao::usarPorta(null); // simula o processo novo do wire:click

        $c->call('novo')
            ->fillForm([
                'titulo' => 'Porta Perfil Médium',
                'formato' => 'psicografia',
                'corpo' => '<p>Corpo de teste.</p>',
            ])
            ->call('salvar')
            ->assertHasNoFormErrors();

        $atividade = Activity::where('log_name', 'mensagem')->latest('id')->first();
        $this->assertNotNull($atividade);
        $this->assertSame('perfil', $atividade->properties['porta']);
    }

    /** I14 — CuradoriaConta: salvar pela curadoria grava porta 'perfil', mesmo com o reset no meio. */
    public function test_salvar_pela_curadoria_grava_porta_perfil(): void
    {
        $curador = $this->diretorDepae();
        $pendente = Mensagem::factory()->pendente()->create();
        Activity::query()->delete(); // ignora o 'created' do factory

        $c = Livewire::actingAs($curador)->test(CuradoriaConta::class); // aqui o mount/boot marcou
        AuditoriaAutorizacao::usarPorta(null); // simula o processo novo do wire:click

        $c->call('editar', $pendente->id)
            ->fillForm(['titulo' => 'Porta Perfil Curadoria'])
            ->call('salvar')
            ->assertHasNoFormErrors();

        $atividade = Activity::where('log_name', 'mensagem')->latest('id')->first();
        $this->assertNotNull($atividade);
        $this->assertSame('perfil', $atividade->properties['porta']);
    }

    /** I14 — CuradoriaConta: publicar (o martelo) também grava porta 'perfil'. */
    public function test_publicar_pela_curadoria_grava_porta_perfil(): void
    {
        $curador = $this->diretorDepae();
        $pendente = Mensagem::factory()->pendente()->create();
        Activity::query()->delete(); // ignora o 'created' do factory

        $c = Livewire::actingAs($curador)->test(CuradoriaConta::class)->call('editar', $pendente->id); // mount/boot marcou
        AuditoriaAutorizacao::usarPorta(null); // simula o processo novo do wire:click

        $c->fillForm(['nivel' => VisibilidadeMensagem::Publico->value])
            ->call('publicar', $pendente->id)
            ->assertHasNoFormErrors();

        $atividade = Activity::where('log_name', 'mensagem')->where('event', 'updated')->latest('id')->first();
        $this->assertNotNull($atividade);
        $this->assertSame('perfil', $atividade->properties['porta']);
    }
}
