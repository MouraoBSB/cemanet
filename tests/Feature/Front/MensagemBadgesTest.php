<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-20

namespace Tests\Feature\Front;

use App\Livewire\Mensagens\Lista;
use App\Models\Mensagem;
use App\Models\User;
use Database\Seeders\EstruturaCemaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MensagemBadgesTest extends TestCase
{
    use RefreshDatabase;

    public function test_anonimo_sem_legenda_nem_badge(): void
    {
        Mensagem::factory()->publica()->create(['titulo' => 'P']);

        Livewire::test(Lista::class)->assertDontSee('Nível de acesso'); // sem legenda p/ anônimo (I9)
    }

    public function test_logado_ve_legenda_e_badge(): void
    {
        $this->seed(EstruturaCemaSeeder::class);
        $trab = User::factory()->create();
        $trab->assignRole('trabalhador');
        Mensagem::factory()->create(['status' => 'publicado', 'nivel' => 'trabalhadores', 'titulo' => 'Doc Trab']);

        Livewire::actingAs($trab)->test(Lista::class)
            ->assertSee('Nível de acesso')   // legenda @auth
            ->assertSee('Trabalhadores');    // badge do nível
    }

    public function test_null_publicado_admin_lista_sem_badge_sem_500(): void
    {
        // I14/B1: sem o null-guard, o card chamaria null->rotulo() e daria 500 aqui.
        $this->seed(EstruturaCemaSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('administrador');
        Mensagem::factory()->create(['status' => 'publicado', 'nivel' => null, 'titulo' => 'Sem Nivel']);

        Livewire::actingAs($admin)->test(Lista::class)->assertSee('Sem Nivel'); // renderiza (não 500)
    }
}
