<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-23

namespace Tests\Feature\Front;

use App\Models\AutorEspiritual;
use App\Models\Mensagem;
use App\Models\User;
use Database\Seeders\EstruturaCemaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AutorVisibilidadeTest extends TestCase
{
    use RefreshDatabase;

    private bool $estruturaSemeada = false;

    /** Semeia a estrutura UMA vez por teste; findOrCreate cobre 'administrador' (fora do EstruturaCemaSeeder). */
    private function comPapel(string $papel): User
    {
        if (! $this->estruturaSemeada) {
            $this->seed(EstruturaCemaSeeder::class);
            $this->estruturaSemeada = true;
        }
        Role::findOrCreate($papel, 'web');
        $u = User::factory()->create();
        $u->assignRole($papel);

        return $u->fresh();
    }

    /** @return array{pub: AutorEspiritual, restrito: AutorEspiritual} */
    private function doisAutores(): array
    {
        $pub = AutorEspiritual::factory()->create(['nome' => 'Autor Público', 'slug' => 'autor-pub', 'ativo' => true]);
        $restrito = AutorEspiritual::factory()->create(['nome' => 'Autor Só Trabalhadores', 'slug' => 'autor-trab', 'ativo' => true]);
        Mensagem::factory()->publica()->create()->autores()->attach($pub->id);
        Mensagem::factory()->create(['status' => 'publicado', 'nivel' => 'trabalhadores'])->autores()->attach($restrito->id);

        return ['pub' => $pub, 'restrito' => $restrito];
    }

    /** I1: o anônimo vê exatamente a grade de hoje (só quem tem pública). */
    public function test_i1_anonimo_ve_so_autor_com_publica(): void
    {
        $this->doisAutores();

        $this->get(route('autores.index'))->assertOk()
            ->assertSee('Autor Público')
            ->assertDontSee('Autor Só Trabalhadores');   // sem pública, some para o anônimo
    }

    /** I3: o logado vê na grade o autor que só tem restrita do nível dele. */
    public function test_i3_logado_ve_autor_so_restrito_na_grade(): void
    {
        $this->doisAutores();
        $trab = $this->comPapel('trabalhador');

        $this->actingAs($trab)->get(route('autores.index'))->assertOk()
            ->assertSee('Autor Público')
            ->assertSee('Autor Só Trabalhadores');   // trabalhador enxerga o nível 'trabalhadores'
    }

    /** I4: a contagem do card varia por usuário — mesmo escopo que a grade. */
    public function test_i4_contagem_do_card_e_viewer_aware(): void
    {
        $autor = AutorEspiritual::factory()->create(['nome' => 'Bezerra', 'slug' => 'bezerra', 'ativo' => true]);
        Mensagem::factory()->publica()->create()->autores()->attach($autor->id);
        Mensagem::factory()->create(['status' => 'publicado', 'nivel' => 'trabalhadores'])->autores()->attach($autor->id);

        $this->get(route('autores.index'))->assertOk()->assertSee('1 mensagem');   // anônimo: só a pública

        $trab = $this->comPapel('trabalhador');
        $this->actingAs($trab)->get(route('autores.index'))->assertOk()->assertSee('2 mensagens'); // logado: pública + trabalhadores
    }

    /**
     * I5: a resposta logada não é cacheável por proxy; a anônima é. Na lista e no perfil.
     * ⚠️ Os GET anônimos vêm ANTES do actingAs — o actingAs PERSISTE pelo resto do teste
     * (molde de MensagemIndexContadorTest:17-30). Intercalar daria falso-vermelho: a 2ª volta
     * "anônima" já viria logada e o assertStringNotContainsString falharia com o código certo.
     */
    public function test_i5_cache_control_privado_no_logado(): void
    {
        $autor = AutorEspiritual::factory()->create(['slug' => 'cache-autor', 'ativo' => true]);
        Mensagem::factory()->publica()->create()->autores()->attach($autor->id);

        foreach ([route('autores.index'), route('autores.show', 'cache-autor')] as $url) {
            $anon = $this->get($url);
            $this->assertStringNotContainsString('no-store', (string) $anon->headers->get('Cache-Control'));
        }

        $this->actingAs($this->comPapel('trabalhador'));

        foreach ([route('autores.index'), route('autores.show', 'cache-autor')] as $url) {
            $logado = $this->get($url);
            $this->assertStringContainsString('no-store', (string) $logado->headers->get('Cache-Control'));
        }
    }
}
