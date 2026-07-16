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
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AgendaContaCriarTest extends TestCase
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
    }

    private function editorDecom(array $capacidades): User
    {
        Role::findByName('diretor', 'web')->syncPermissions($capacidades);
        $decom = Departamento::where('sigla', 'DECOM')->value('id');
        $user = User::factory()->create();
        $user->assignRole('diretor');
        $user->departamentos()->sync([$decom]);
        // um AgendaDia no escopo p/ a aba/rota abrir (data fixa longe das datas de teste, evita colisão de unique)
        AgendaDia::factory()->create(['data' => '2020-01-01'])->departamentos()->sync([$decom]);

        return $user;
    }

    /**
     * Era test_criar_forca_departamentos_ded_e_decom. Sob o regime "do tipo" o AgendaDia criado
     * pelo site nasce SEM pivô (§6.4/decisão 7) e o responsável o edita assim mesmo (I9) — o
     * objeto não tem escopo próprio. Complementa test_i9_... (CamadaUmFiltroPorTipoTest), que
     * parte da factory: aqui o registro nasce pela UI.
     */
    public function test_criar_nasce_sem_pivo_e_o_responsavel_edita(): void
    {
        $user = $this->editorDecom(['agenda.ver', 'agenda.criar', 'agenda.editar']);

        Livewire::actingAs($user)->test(AgendaConta::class)
            ->call('novo')
            ->fillForm([
                'data' => '2027-03-15',
                'status' => AgendaDia::STATUS_PUBLICADO,
                'reflexao' => '<p>Reflexão do dia.</p>',
                'meta_dia_titulo' => 'Perseverança',
            ])
            ->call('salvar')
            ->assertHasNoFormErrors();

        $novo = AgendaDia::whereDate('data', '2027-03-15')->firstOrFail();
        $this->assertSame([], $novo->departamentos()->pluck('departamentos.id')->all(), 'nasce sem pivô');

        // I9: o pivô vazio não impede — um diretor do DED (responsável pela Agenda) edita.
        Role::findByName('diretor', 'web')->syncPermissions(['agenda.ver', 'agenda.criar', 'agenda.editar']);
        $diretorDed = User::factory()->create();
        $diretorDed->assignRole('diretor');
        $diretorDed->departamentos()->sync([Departamento::where('sigla', 'DED')->value('id')]);
        $this->assertTrue(Gate::forUser($diretorDed)->check('editar', $novo));
    }

    /**
     * O POST continua sem mandar no pivô — só o resultado mudou: antes o servidor forçava
     * DED+DECOM, agora não grava pivô nenhum (§6.4). O campo forjado segue ignorado.
     */
    public function test_departamentos_forjado_no_post_e_ignorado(): void
    {
        $user = $this->editorDecom(['agenda.ver', 'agenda.criar', 'agenda.editar']);
        $das = Departamento::where('sigla', 'DAS')->value('id'); // depto alheio

        Livewire::actingAs($user)->test(AgendaConta::class)
            ->call('novo')
            ->fillForm(['data' => '2027-08-08', 'status' => AgendaDia::STATUS_PUBLICADO, 'reflexao' => '<p>x</p>'])
            ->set('data.departamentos', [$das]) // injeta o campo privilegiado direto no estado (bypass da UI)
            ->call('salvar')
            ->assertHasNoFormErrors();

        $novo = AgendaDia::whereDate('data', '2027-08-08')->firstOrFail();
        $ids = $novo->departamentos()->pluck('departamentos.id')->all();
        $this->assertSame([], $ids);                         // o pivô não é gravado
        $this->assertNotContains($das, $ids);                // o depto forjado NÃO entrou
    }

    public function test_status_fora_do_enum_e_rejeitado(): void
    {
        $user = $this->editorDecom(['agenda.ver', 'agenda.criar', 'agenda.editar']);

        Livewire::actingAs($user)->test(AgendaConta::class)
            ->call('novo')
            ->fillForm(['data' => '2027-06-06', 'status' => 'invalido', 'reflexao' => '<p>x</p>'])
            ->call('salvar')
            ->assertHasFormErrors(['status']); // o enum do Select barra no getState (belt server-side)
    }

    public function test_criar_sem_editar_forca_rascunho(): void
    {
        $user = $this->editorDecom(['agenda.ver', 'agenda.criar']); // sem agenda.editar

        Livewire::actingAs($user)->test(AgendaConta::class)
            ->call('novo')
            ->fillForm(['data' => '2027-04-01', 'status' => AgendaDia::STATUS_PUBLICADO, 'reflexao' => '<p>x</p>'])
            ->call('salvar')
            ->assertHasNoFormErrors();

        $this->assertSame(AgendaDia::STATUS_RASCUNHO, AgendaDia::whereDate('data', '2027-04-01')->value('status'));
    }

    public function test_criar_com_editar_respeita_status_publicado(): void
    {
        $user = $this->editorDecom(['agenda.ver', 'agenda.criar', 'agenda.editar']);

        Livewire::actingAs($user)->test(AgendaConta::class)
            ->call('novo')
            ->fillForm(['data' => '2027-04-02', 'status' => AgendaDia::STATUS_PUBLICADO, 'reflexao' => '<p>x</p>'])
            ->call('salvar')
            ->assertHasNoFormErrors();

        $this->assertSame(AgendaDia::STATUS_PUBLICADO, AgendaDia::whereDate('data', '2027-04-02')->value('status'));
    }

    public function test_criar_rejeita_data_duplicada(): void
    {
        $user = $this->editorDecom(['agenda.ver', 'agenda.criar', 'agenda.editar']);
        AgendaDia::factory()->create(['data' => '2027-05-05']);

        Livewire::actingAs($user)->test(AgendaConta::class)
            ->call('novo')
            ->fillForm(['data' => '2027-05-05', 'status' => AgendaDia::STATUS_RASCUNHO, 'reflexao' => '<p>x</p>'])
            ->call('salvar')
            ->assertHasFormErrors(['data']);
    }

    /**
     * Sob o regime "do tipo" a lista é TUDO-OU-NADA: o responsável (DECOM, da semente
     * agenda ⇒ DED+DECOM) enxerga todos os dias, inclusive os de pivô disjunto. Era
     * test_lista_mostra_so_o_escopo_do_usuario, cuja premissa (interseção por objeto) a
     * decisão 4 do §5 matou. O deny do não-responsável continua coberto por
     * AbaAgendaTest::test_scope_do_tipo_e_tudo_ou_nada (assertSame(0, ...) para o DEPRO).
     */
    public function test_lista_mostra_tudo_ao_responsavel(): void
    {
        $user = $this->editorDecom(['agenda.ver', 'agenda.criar']);
        $decom = Departamento::where('sigla', 'DECOM')->value('id');
        $ded = Departamento::where('sigla', 'DED')->value('id');

        $meu = AgendaDia::factory()->create(['meta_dia_titulo' => 'MeuDoDecom']);
        $meu->departamentos()->sync([$decom]);
        $alheio = AgendaDia::factory()->create(['meta_dia_titulo' => 'AlheioDoDed']);
        $alheio->departamentos()->sync([$ded]);

        Livewire::actingAs($user)->test(AgendaConta::class)
            ->assertSee('MeuDoDecom')
            ->assertSee('AlheioDoDed');   // era assertDontSee: o pivô não restringe mais
    }
}
