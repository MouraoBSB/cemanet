<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-20

namespace Tests\Feature\Front;

use App\Models\Mensagem;
use App\Models\User;
use Database\Seeders\EstruturaCemaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MensagemSingleRicoTest extends TestCase
{
    use RefreshDatabase;

    private function comPapel(string $papel): User
    {
        $this->seed(EstruturaCemaSeeder::class);
        $u = User::factory()->create();
        $u->assignRole($papel);

        return $u->fresh();
    }

    public function test_publico_indexavel_sem_badge_ao_anonimo(): void
    {
        Mensagem::factory()->publica()->create(['slug' => 'pub', 'titulo' => 'Luz']);

        $res = $this->get(route('mensagens.show', 'pub'));
        $res->assertOk()->assertSee('Luz');
        $res->assertDontSee('name="robots"', false);   // Público indexável (I11)
        $res->assertDontSee('Nível de acesso');         // sem badge ao anônimo (I9)
    }

    public function test_restrito_autorizado_badge_e_noindex(): void
    {
        $dir = $this->comPapel('diretor');
        Mensagem::factory()->create(['status' => 'publicado', 'nivel' => 'diretores', 'slug' => 'doc', 'titulo' => 'Doc Diretoria']);

        $res = $this->actingAs($dir)->get(route('mensagens.show', 'doc'));
        $res->assertOk()->assertSee('Doc Diretoria')->assertSee('Diretores'); // badge dinâmico
        $res->assertSee('name="robots"', false);                              // noindex (I11)
        $res->assertDontSee('application/ld+json', false);                    // SEO rico só p/ Público
    }

    public function test_null_admin_single_200_sem_selo(): void
    {
        // I14/B1: single de nivel=null visto pelo admin não pode dar 500 no selo do hero.
        $admin = $this->comPapel('administrador');
        Mensagem::factory()->create(['status' => 'publicado', 'nivel' => null, 'slug' => 'n', 'titulo' => 'Sem Nivel']);

        $this->actingAs($admin)->get(route('mensagens.show', 'n'))->assertOk()->assertSee('Sem Nivel');
    }

    public function test_nota_direcionada_ao_destinatario_sem_pii(): void
    {
        $dest = $this->comPapel('frequentador');
        $outro = User::factory()->create(['name' => 'Beltrano Outro']);
        $m = Mensagem::factory()->create(['status' => 'publicado', 'nivel' => 'direcionada', 'slug' => 'dd', 'titulo' => 'Msg']);
        $m->destinatarios()->attach([$dest->id, $outro->id]);

        $res = $this->actingAs($dest)->get(route('mensagens.show', 'dd'));
        $res->assertOk()->assertSee('Direcionada a você');
        $res->assertDontSee('Beltrano Outro');   // F2: nenhum destinatário (PII) no HTML
    }
}
