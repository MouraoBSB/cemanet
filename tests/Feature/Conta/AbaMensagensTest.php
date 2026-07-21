<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-21

namespace Tests\Feature\Conta;

use App\Livewire\Conta\MensagensConta;
use App\Models\Setor;
use App\Models\User;
use App\Support\Conta\AbaMensagens;
use Database\Seeders\EstruturaCemaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * O setUp semeia só EstruturaCemaSeeder (setores/cargos), SEM nenhuma capacidade: AbaMensagens
 * decide por PERTENCIMENTO ao setor Médium, não por permission (molde AbaDirecionadasTest). Que
 * estes testes passem sem seed de permissão É a prova de que a aba não consulta a matriz.
 */
class AbaMensagensTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstruturaCemaSeeder::class);
    }

    private function medium(): User
    {
        $user = User::factory()->create();
        $user->assignRole('trabalhador');
        $user->setores()->attach(Setor::where('slug', Setor::SLUG_MEDIUM)->value('id'), ['funcao' => 'membro']);

        return $user->fresh();
    }

    private function naoMedium(): User
    {
        $user = User::factory()->create();
        $user->assignRole('frequentador');

        return $user;
    }

    public function test_visivel_para_medium(): void
    {
        $this->assertTrue(AbaMensagens::visivelPara($this->medium()));
    }

    public function test_oculta_para_nao_medium(): void
    {
        $this->assertFalse(AbaMensagens::visivelPara($this->naoMedium()));
    }

    /** I1: não-médium recebe 403 na rota E no mount do componente (molde AbaAgendaTest.php:112-118). */
    public function test_nao_medium_recebe_403_na_rota_e_no_componente(): void
    {
        $naoMedium = $this->naoMedium();

        $this->actingAs($naoMedium)->get(route('conta.mensagens'))->assertForbidden();

        Livewire::actingAs($naoMedium)->test(MensagensConta::class)->assertForbidden();
    }

    /** I1: anônimo é redirecionado ao login (grupo de rotas sob middleware auth). */
    public function test_anonimo_e_redirecionado_ao_login(): void
    {
        $this->get(route('conta.mensagens'))->assertRedirect(route('login'));
    }

    /** I1: médium recebe 200 e a nav mostra "Minhas Mensagens". */
    public function test_medium_ve_200_e_a_aba_na_nav(): void
    {
        $this->actingAs($this->medium())->get(route('conta.mensagens'))
            ->assertOk()
            ->assertSee('Minhas Mensagens');
    }

    /** I25: a tela é noindex,nofollow — área pessoal, nunca indexada. */
    public function test_view_e_noindex(): void
    {
        $this->actingAs($this->medium())->get(route('conta.mensagens'))
            ->assertSee('noindex, nofollow', false);
    }
}
