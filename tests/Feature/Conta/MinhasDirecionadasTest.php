<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-20

namespace Tests\Feature\Conta;

use App\Enums\VisibilidadeMensagem;
use App\Models\Mensagem;
use App\Models\User;
use Database\Seeders\EstruturaCemaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MinhasDirecionadasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstruturaCemaSeeder::class); // a página renderiza nav/saudação (papéis/setores)
    }

    private function direcionadaPara(User $user, array $attrs = []): Mensagem
    {
        $m = Mensagem::factory()->comNivel(VisibilidadeMensagem::Direcionada)->create($attrs);
        $m->destinatarios()->attach($user->id);

        return $m;
    }

    public function test_destinatario_ve_a_lista_com_noindex(): void
    {
        $user = User::factory()->create();
        $this->direcionadaPara($user, ['titulo' => 'Recado do plano espiritual']);

        $this->actingAs($user)->get(route('conta.direcionadas'))
            ->assertOk()
            ->assertSee('Recado do plano espiritual')
            ->assertSee('noindex, nofollow', false); // I4
    }

    /** I2 — filtro por user_id: a direcionada de OUTRO usuário nunca aparece. */
    public function test_nao_mostra_direcionada_de_outro_usuario(): void
    {
        $user = User::factory()->create();
        $this->direcionadaPara($user, ['titulo' => 'Minha direcionada']);
        $this->direcionadaPara(User::factory()->create(), ['titulo' => 'Direcionada de outro']);

        $this->actingAs($user)->get(route('conta.direcionadas'))
            ->assertSee('Minha direcionada')
            ->assertDontSee('Direcionada de outro');
    }

    /** publicado(): uma pendente dele não aparece na lista. */
    public function test_nao_mostra_pendente(): void
    {
        $user = User::factory()->create();
        $this->direcionadaPara($user, ['titulo' => 'Publicada visivel']);
        $pend = Mensagem::factory()->comNivel(VisibilidadeMensagem::Direcionada)->pendente()->create(['titulo' => 'Pendente oculta']);
        $pend->destinatarios()->attach($user->id);

        $this->actingAs($user)->get(route('conta.direcionadas'))
            ->assertSee('Publicada visivel')
            ->assertDontSee('Pendente oculta');
    }

    /** Blindagem O5 (I7): uma PUBLICADA de outro nível vinculada a ele não aparece. */
    public function test_nao_mostra_publicada_de_outro_nivel(): void
    {
        $user = User::factory()->create();
        $this->direcionadaPara($user, ['titulo' => 'Direcionada real']);
        $outroNivel = Mensagem::factory()->comNivel('trabalhadores')->create(['titulo' => 'Nivel trabalhadores']);
        $outroNivel->destinatarios()->attach($user->id); // vínculo anômalo no pivô

        $this->actingAs($user)->get(route('conta.direcionadas'))
            ->assertSee('Direcionada real')
            ->assertDontSee('Nivel trabalhadores');
    }

    /** I3 — nenhuma PII de outro destinatário no HTML (o card só mostra autores). */
    public function test_nao_vaza_destinatarios_pii(): void
    {
        $user = User::factory()->create(['name' => 'Titular da Conta']);
        $outroDest = User::factory()->create(['name' => 'Outro Destinatario Sigiloso']);
        $m = $this->direcionadaPara($user, ['titulo' => 'Compartilhada']);
        $m->destinatarios()->attach($outroDest->id); // dois destinatários na mesma mensagem

        $this->actingAs($user)->get(route('conta.direcionadas'))
            ->assertOk()
            ->assertSee('Compartilhada')
            ->assertDontSee('Outro Destinatario Sigiloso');
    }

    /** I1 — logado sem direcionada → 403. */
    public function test_logado_sem_direcionada_recebe_403(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('conta.direcionadas'))
            ->assertForbidden();
    }

    /** I1 + publicado(): só com pendente → 403. */
    public function test_so_com_pendente_recebe_403(): void
    {
        $user = User::factory()->create();
        $pend = Mensagem::factory()->comNivel(VisibilidadeMensagem::Direcionada)->pendente()->create();
        $pend->destinatarios()->attach($user->id);

        $this->actingAs($user)->get(route('conta.direcionadas'))->assertForbidden();
    }

    /** I4 — anônimo → redirect ao login (middleware auth). */
    public function test_anonimo_redireciona_ao_login(): void
    {
        $this->get(route('conta.direcionadas'))->assertRedirect(route('login'));
    }

    public function test_ordena_por_data_recebimento_desc(): void
    {
        $user = User::factory()->create();
        $this->direcionadaPara($user, ['titulo' => 'MsgAntiga', 'data_recebimento' => '2026-01-01']);
        $this->direcionadaPara($user, ['titulo' => 'MsgRecente', 'data_recebimento' => '2026-06-01']);

        $resp = $this->actingAs($user)->get(route('conta.direcionadas'))
            ->assertOk()->assertSee('MsgAntiga')->assertSee('MsgRecente'); // ancora antes do strpos (evita false→0)
        $html = $resp->getContent();
        $this->assertLessThan(strpos($html, 'MsgAntiga'), strpos($html, 'MsgRecente'), 'a mais recente vem primeiro');
    }

    /** I5 — read-only: a rota é só GET (nenhum verbo de mutação). */
    public function test_rota_e_somente_leitura(): void
    {
        $rota = collect(app('router')->getRoutes())->first(fn ($r) => $r->getName() === 'conta.direcionadas');

        $this->assertNotNull($rota);
        $this->assertEqualsCanonicalizing(['GET', 'HEAD'], $rota->methods());
    }

    /** I6 (reforço leve): a 3C é aditiva — a lista pública segue 200 para anônimo (comportamento 2B intacto). */
    public function test_lista_publica_permanece_intacta(): void
    {
        $this->get(route('mensagens.index'))->assertOk();
    }
}
