<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-21

namespace Tests\Feature\Conta;

use App\Livewire\Conta\CuradoriaConta;
use App\Models\Cargo;
use App\Models\User;
use App\Support\Conta\AbaCuradoria;
use Database\Seeders\EstruturaCemaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * O setUp semeia só EstruturaCemaSeeder (setores/cargos), SEM nenhuma capacidade: AbaCuradoria
 * decide por PERTENCIMENTO a cargo (Diretor do DEPAE OU Presidente), não por permission (molde
 * AbaMensagensTest). Que estes testes passem sem seed de permissão É a prova de que a aba não
 * consulta a matriz.
 */
class AbaCuradoriaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstruturaCemaSeeder::class);
    }

    private function diretorDepae(): User
    {
        $user = User::factory()->create();
        $user->assignRole('diretor');
        $user->cargos()->attach(Cargo::where('slug', Cargo::SLUG_DIRETOR_DEPAE)->value('id'));

        return $user->fresh();
    }

    private function presidente(): User
    {
        $user = User::factory()->create();
        $user->assignRole('frequentador');
        $user->cargos()->attach(Cargo::where('slug', Cargo::SLUG_PRESIDENTE)->value('id'));

        return $user->fresh();
    }

    private function medium(): User
    {
        $user = User::factory()->create();
        $user->assignRole('trabalhador');

        return $user;
    }

    /** Admin puro (sem cargo Diretor-DEPAE/Presidente) — decisão §6.3: o admin edita pelo /admin, não aqui. */
    private function adminPuro(): User
    {
        $user = User::factory()->create();
        $user->assignRole('administrador');

        return $user;
    }

    // ---- AbaCuradoria::visivelPara — os dois ramos, SEPARADAMENTE ----

    public function test_visivel_para_diretor_depae(): void
    {
        $this->assertTrue(AbaCuradoria::visivelPara($this->diretorDepae()));
    }

    public function test_visivel_para_presidente(): void
    {
        $this->assertTrue(AbaCuradoria::visivelPara($this->presidente()));
    }

    public function test_oculta_para_medium_comum(): void
    {
        $this->assertFalse(AbaCuradoria::visivelPara($this->medium()));
    }

    /** Intencional: o admin puro não tem cargo Diretor-DEPAE/Presidente ⇒ aba oculta (edita pelo /admin). */
    public function test_oculta_para_admin_puro(): void
    {
        $this->assertFalse(AbaCuradoria::visivelPara($this->adminPuro()));
    }

    // ---- I2: rota E mount, os dois ramos de acesso + os dois negativos ----

    public function test_medium_comum_recebe_403_na_rota_e_no_componente(): void
    {
        $medium = $this->medium();

        $this->actingAs($medium)->get(route('conta.curadoria'))->assertForbidden();

        Livewire::actingAs($medium)->test(CuradoriaConta::class)->assertForbidden();
    }

    /**
     * Intencional (§6.3): o portão da rota usa AbaCuradoria::visivelPara, que NÃO passa pelo
     * Gate::before do admin. O admin edita pelo /admin; a área do membro não é atalho de painel.
     */
    public function test_admin_puro_recebe_403_na_rota_e_no_componente(): void
    {
        $admin = $this->adminPuro();

        $this->actingAs($admin)->get(route('conta.curadoria'))->assertForbidden();

        Livewire::actingAs($admin)->test(CuradoriaConta::class)->assertForbidden();
    }

    public function test_diretor_depae_ve_200(): void
    {
        $this->actingAs($this->diretorDepae())->get(route('conta.curadoria'))
            ->assertOk()
            ->assertSee('Curadoria');
    }

    public function test_presidente_ve_200(): void
    {
        $this->actingAs($this->presidente())->get(route('conta.curadoria'))
            ->assertOk()
            ->assertSee('Curadoria');
    }

    public function test_anonimo_e_redirecionado_ao_login(): void
    {
        $this->get(route('conta.curadoria'))->assertRedirect(route('login'));
    }

    /** I25: a tela é noindex,nofollow — área pessoal, nunca indexada. */
    public function test_view_e_noindex(): void
    {
        $this->actingAs($this->diretorDepae())->get(route('conta.curadoria'))
            ->assertSee('noindex, nofollow', false);
    }
}
