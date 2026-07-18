<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-16

namespace Tests\Feature\Autorizacao;

use App\Enums\RegimeAcesso;
use App\Enums\VisibilidadeEvento;
use App\Models\AgendaDia;
use App\Models\Departamento;
use App\Models\Evento;
use App\Models\Palestra;
use App\Models\Palestrante;
use App\Models\Post;
use App\Models\TipoConteudo;
use App\Models\User;
use Database\Seeders\CapacidadesSeeder;
use Database\Seeders\EstruturaCemaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Os invariantes da Camada 1 (§7 do spec). Cada caso REPROVA uma implementação errada — não basta
 * ficar verde: o arranjo tem de distinguir "responsável pelo tipo" de "objeto no meu departamento".
 * Por isso TODO caso do regime "do tipo" fixa o pivô do objeto explicitamente (a AgendaDiaFactory
 * não anexa departamento; e o Evento NÃO tem factory — é criado à mão, no molde do
 * EventoPolicyCapacidadeTest, porque `slug` é UNIQUE).
 */
class CamadaUmFiltroPorTipoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        (new EstruturaCemaSeeder)->run();      // 8 departamentos + papéis
        $this->seed(CapacidadesSeeder::class); // as permissions do glossário
    }

    private function idDe(string $sigla): int
    {
        return Departamento::where('sigla', $sigla)->value('id');
    }

    /**
     * Configura o tipo direto na tabela (a tela é do E1; aqui interessa o estado).
     * SEMPRE antes da 1ª checagem: o memo do AcessoPorTipo é por RECURSO e fresh() não o invalida.
     */
    private function configurar(string $recurso, RegimeAcesso $regime, array $siglas = []): void
    {
        $tipo = TipoConteudo::updateOrCreate(['recurso' => $recurso], ['regime' => $regime]);
        $tipo->departamentos()->sync(array_map(fn (string $s): int => $this->idDe($s), $siglas));
    }

    private function diretorEm(array $siglas, array $capacidades = ['agenda.ver', 'agenda.criar', 'agenda.editar', 'agenda.excluir']): User
    {
        $u = User::factory()->create();
        foreach ($capacidades as $c) {
            $u->givePermissionTo($c);
        }
        $u->departamentos()->sync(array_map(fn (string $s): int => $this->idDe($s), $siglas));

        return $u;
    }

    /** O pivô do objeto é SEMPRE explícito: [] = vazio, ou as siglas dadas. */
    private function agendaComPivo(array $siglas): AgendaDia
    {
        $ag = AgendaDia::factory()->create();
        $ag->departamentos()->sync(array_map(fn (string $s): int => $this->idDe($s), $siglas));

        return $ag;
    }

    /** Evento NÃO tem factory (não existe database/factories/EventoFactory.php) — criar à mão; slug é UNIQUE. */
    private function eventoComPivo(string $slug, array $siglas = []): Evento
    {
        $e = Evento::create([
            'titulo' => 'E',
            'slug' => $slug,
            'data_inicio' => '2026-08-15',
            'visibilidade' => VisibilidadeEvento::Publico,
            'status' => Evento::STATUS_RASCUNHO,
        ]);
        $e->departamentos()->sync(array_map(fn (string $s): int => $this->idDe($s), $siglas));

        return $e;
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->assignRole(Role::findOrCreate('administrador', 'web'));

        return $u;
    }

    private function assertNegaTudo(User $u, AgendaDia $ag, string $porque): void
    {
        foreach (['ver', 'editar', 'excluir'] as $acao) {
            $this->assertFalse(Gate::forUser($u)->check($acao, $ag), "{$porque}: {$acao}");
        }
        $this->assertFalse(Gate::forUser($u)->check('criar', AgendaDia::class), "{$porque}: criar");
    }

    private function assertPermiteTudo(User $u, AgendaDia $ag, string $porque): void
    {
        foreach (['ver', 'editar', 'excluir'] as $acao) {
            $this->assertTrue(Gate::forUser($u)->check($acao, $ag), "{$porque}: {$acao}");
        }
    }

    // ---------- I1: config vazia NUNCA permite ----------

    public function test_i1_tipo_sem_responsaveis_nega_tudo_mesmo_com_capacidade_e_vinculo(): void
    {
        $this->configurar('agenda', RegimeAcesso::DoTipo, []);   // "do tipo" SEM responsáveis
        $diretor = $this->diretorEm(['DED']);
        $ag = $this->agendaComPivo(['DED']);                     // pivô coincidente — não pode salvar

        $this->assertNegaTudo($diretor, $ag, 'I1: tipo sem responsáveis');
    }

    public function test_i1_admin_passa_mesmo_com_config_vazia(): void
    {
        $this->configurar('agenda', RegimeAcesso::DoTipo, []);
        $ag = $this->agendaComPivo([]);

        $this->assertPermiteTudo($this->admin(), $ag, 'I6: admin passa no Gate::before');
        $this->assertTrue(Gate::forUser($this->admin())->check('criar', AgendaDia::class));
    }

    // ---------- I2: tabela não semeada ⇒ nega e NÃO explode, nos 5 recursos ----------

    /**
     * Sem TiposConteudoSeeder: tipos_conteudo VAZIA. Cobre os 5 recursos — INCLUSIVE o evento.
     * Estender ao Evento trava por teste o fallback proibido do §12.8: se alguém trocar
     * `null => false` por fallback ao pivô, este caso fica vermelho.
     */
    public function test_i2_tabela_vazia_nega_os_quatro_verbos_nos_cinco_recursos_e_nao_lanca(): void
    {
        $this->assertSame(0, TipoConteudo::count(), 'a tabela precisa estar vazia para este caso valer');

        $ded = $this->idDe('DED');

        $mapa = [
            'agenda' => [AgendaDia::class, AgendaDia::factory()->create()],
            'evento' => [Evento::class, $this->eventoComPivo('i2-evento')],
            'palestra' => [Palestra::class, Palestra::factory()->create()],
            'post' => [Post::class, Post::factory()->create()],
            'palestrante' => [Palestrante::class, Palestrante::factory()->create()],
        ];

        foreach ($mapa as $recurso => [$classe, $objeto]) {
            $objeto->departamentos()->sync([$ded]);   // pivô COINCIDENTE: o velho filtro permitiria

            $u = User::factory()->create();
            foreach (['ver', 'criar', 'editar', 'excluir'] as $acao) {
                $u->givePermissionTo("{$recurso}.{$acao}");
            }
            $u->departamentos()->sync([$ded]);

            foreach (['ver', 'editar', 'excluir'] as $acao) {
                $this->assertFalse(Gate::forUser($u)->check($acao, $objeto), "I2 {$recurso}.{$acao}");
            }
            $this->assertFalse(Gate::forUser($u)->check('criar', $classe), "I2 {$recurso}.criar");
        }
    }

    // ---------- I3: o furo do criar, com o cenário real do §4.3 ----------

    public function test_i3_diretor_do_depro_com_capacidade_nao_cria_agenda(): void
    {
        // O cenário medido no dev (§4.3): 10 diretores criam hoje e não conseguem editar.
        $this->configurar('agenda', RegimeAcesso::DoTipo, ['DED', 'DECOM']);
        $depro = $this->diretorEm(['DEPRO']);

        $this->assertFalse(Gate::forUser($depro)->check('criar', AgendaDia::class), 'I3: DEPRO não é responsável');
    }

    public function test_i3_diretor_do_ded_cria_agenda(): void
    {
        $this->configurar('agenda', RegimeAcesso::DoTipo, ['DED', 'DECOM']);

        $this->assertTrue(Gate::forUser($this->diretorEm(['DED']))->check('criar', AgendaDia::class));
    }

    // ---------- No regime "do tipo", o pivô do objeto NÃO decide ----------
    // Os 3 estados do pivô (§7/I9) estão distribuídos: DISJUNTO do usuário (test_disjunto_nega,
    // não-responsável; e test_pivo_ignorado_*, responsável), COINCIDENTE com o usuário
    // (test_pivo_nao_abre_*) e VAZIO (test_i9_*, na seção seguinte).

    /** Pivô DISJUNTO do usuário E usuário NÃO responsável ⇒ nega. O caso-base do não-responsável. */
    public function test_disjunto_nega(): void
    {
        $this->configurar('agenda', RegimeAcesso::DoTipo, ['DED']);
        $u = $this->diretorEm(['DEPRO']);
        $ag = $this->agendaComPivo(['DAS']);   // pivô disjunto do usuário E dos responsáveis

        $this->assertNegaTudo($u, $ag, 'usuário fora dos responsáveis, pivô disjunto dele');
    }

    /**
     * PIVÔ IGNORADO. Reprova o AND puro e o híbrido:
     * usuário responsável, mas o pivô do objeto é disjunto DELE ⇒ PERMITE mesmo assim.
     */
    public function test_pivo_ignorado_permite_e_reprova_o_and(): void
    {
        $this->configurar('agenda', RegimeAcesso::DoTipo, ['DED']);
        $u = $this->diretorEm(['DED']);
        $ag = $this->agendaComPivo(['DEPRO']);   // pivô DISJUNTO do usuário

        $this->assertPermiteTudo($u, $ag, 'no "do tipo" o objeto NÃO é consultado');
    }

    /**
     * PIVÔ NÃO ABRE. Pivô COINCIDENTE com o usuário, usuário DISJUNTO dos responsáveis ⇒ NEGA.
     * É o caso que SEPARA as duas causas possíveis de negação: aqui o pivô PERMITIRIA (o filtro
     * velho abre pela interseção usuário∩pivô) e só a não-responsabilidade nega — logo reprova a
     * variante permissiva (||) E o filtro velho.
     */
    public function test_pivo_nao_abre_e_reprova_o_or(): void
    {
        $this->configurar('agenda', RegimeAcesso::DoTipo, ['DED']);
        $u = $this->diretorEm(['DEPRO']);
        $ag = $this->agendaComPivo(['DEPRO']);   // pivô COINCIDE com o usuário — e ainda assim nega

        $this->assertNegaTudo($u, $ag, 'o pivô não pode abrir o que a config fechou');
    }

    // ---------- I9: alargamento consciente (o 3º estado: pivô VAZIO) ----------

    public function test_i9_objeto_sem_departamento_e_editavel_pelo_responsavel(): void
    {
        $this->configurar('agenda', RegimeAcesso::DoTipo, ['DED']);
        $u = $this->diretorEm(['DED']);
        $ag = $this->agendaComPivo([]);   // pivô VAZIO — hoje seria só-admin

        $this->assertPermiteTudo($u, $ag, 'I9: no "do tipo" o objeto não tem escopo próprio');
    }

    // ---------- I4: "por registro" (Evento) inalterado ----------

    public function test_i4_por_registro_permite_objeto_no_departamento_do_usuario(): void
    {
        $this->configurar('evento', RegimeAcesso::PorRegistro, []);
        $u = User::factory()->create();
        foreach (['ver', 'criar', 'editar', 'excluir'] as $a) {
            $u->givePermissionTo("evento.{$a}");
        }
        $u->departamentos()->sync([$this->idDe('DEPRO')]);

        $evento = $this->eventoComPivo('i4-dentro', ['DEPRO']);

        $this->assertTrue(Gate::forUser($u)->check('editar', $evento));
        $this->assertTrue(Gate::forUser($u)->check('criar', Evento::class), 'I4: criar = tem algum depto');
    }

    public function test_i4_por_registro_nega_objeto_fora_e_objeto_sem_departamento(): void
    {
        $this->configurar('evento', RegimeAcesso::PorRegistro, []);
        $u = User::factory()->create();
        $u->givePermissionTo('evento.editar');
        $u->departamentos()->sync([$this->idDe('DEPRO')]);

        $fora = $this->eventoComPivo('i4-fora', ['DED']);
        $orfao = $this->eventoComPivo('i4-orfao');   // sem departamento — os 7 do §13.2

        $this->assertFalse(Gate::forUser($u)->check('editar', $fora), 'objeto fora do meu depto');
        $this->assertFalse(Gate::forUser($u)->check('editar', $orfao), 'objeto sem departamento');
    }

    public function test_i4_por_registro_nega_quem_nao_tem_departamento(): void
    {
        $this->configurar('evento', RegimeAcesso::PorRegistro, []);
        $u = User::factory()->create();
        $u->givePermissionTo('evento.criar');

        $this->assertFalse(Gate::forUser($u)->check('criar', Evento::class));
    }

    // ---------- I6: admin passa em qualquer config ----------

    public function test_i6_admin_passa_no_por_registro_com_objeto_orfao(): void
    {
        $this->configurar('evento', RegimeAcesso::PorRegistro, []);
        $orfao = $this->eventoComPivo('i6-orfao');

        $this->assertTrue(Gate::forUser($this->admin())->check('editar', $orfao));
    }
}
