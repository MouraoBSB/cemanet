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

class AutorRodapeCondicionalTest extends TestCase
{
    use RefreshDatabase;

    private const FRASE_LOGADO = 'Este autor tem mensagens restritas que você ainda não pode ver';

    private const FRASE_ANONIMO = 'Há mensagens restritas a trabalhadores e médiuns';

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

    private function autor(string $slug): AutorEspiritual
    {
        return AutorEspiritual::factory()->create(['slug' => $slug, 'ativo' => true]);
    }

    /** I6: aparece para quem tem oculta hierárquica; some para o admin (vê tudo). */
    public function test_i6_aparece_para_quem_nao_ve_e_some_para_o_admin(): void
    {
        $autor = $this->autor('i6');
        Mensagem::factory()->publica()->create()->autores()->attach($autor->id);
        Mensagem::factory()->create(['status' => 'publicado', 'nivel' => 'diretores'])->autores()->attach($autor->id);

        $trab = $this->comPapel('trabalhador');   // nível 20 < 30 → não vê 'diretores'
        $this->actingAs($trab)->get(route('autores.show', 'i6'))->assertOk()->assertSee(self::FRASE_LOGADO);

        $admin = $this->comPapel('administrador');
        $this->actingAs($admin)->get(route('autores.show', 'i6'))->assertOk()->assertDontSee(self::FRASE_LOGADO);
    }

    /**
     * I7 (anti-PII): uma Direcionada a TERCEIRO não faz o rodapé aparecer.
     * Não-vacuoso: o mesmo trabalhador vê a frase num autor com oculta hierárquica (controle).
     */
    public function test_i7_direcionada_a_terceiro_nao_dispara(): void
    {
        $trab = $this->comPapel('trabalhador');
        $terceiro = User::factory()->create();

        // Autor A: só uma direcionada a um TERCEIRO → nada oculto "hierárquico" para o trabalhador.
        $autorA = $this->autor('i7-direcionada');
        $dir = Mensagem::factory()->create(['status' => 'publicado', 'nivel' => 'direcionada']);
        $dir->destinatarios()->attach($terceiro->id);
        $dir->autores()->attach($autorA->id);

        // Autor B (controle): uma oculta hierárquica → a frase PODE aparecer.
        $autorB = $this->autor('i7-controle');
        Mensagem::factory()->create(['status' => 'publicado', 'nivel' => 'diretores'])->autores()->attach($autorB->id);

        $this->actingAs($trab)->get(route('autores.show', 'i7-direcionada'))->assertOk()->assertDontSee(self::FRASE_LOGADO);
        $this->actingAs($trab)->get(route('autores.show', 'i7-controle'))->assertOk()->assertSee(self::FRASE_LOGADO);
    }

    /** I8: mensagem publicada com nivel=null não dispara o rodapé (whereNotNull a exclui). */
    public function test_i8_nivel_null_nao_dispara(): void
    {
        $autor = $this->autor('i8');
        Mensagem::factory()->create(['status' => 'publicado', 'nivel' => null])->autores()->attach($autor->id);
        $trab = $this->comPapel('trabalhador');

        $this->actingAs($trab)->get(route('autores.show', 'i8'))->assertOk()->assertDontSee(self::FRASE_LOGADO);
    }

    /** I9 (A2, anônimo): só-público → sem rodapé; com restrita hierárquica → rodapé @guest com login. */
    public function test_i9_anonimo_ve_rodape_so_quando_ha_restrita(): void
    {
        $soPublico = $this->autor('i9-publico');
        Mensagem::factory()->publica()->create()->autores()->attach($soPublico->id);

        $comRestrita = $this->autor('i9-restrita');
        Mensagem::factory()->publica()->create()->autores()->attach($comRestrita->id);
        Mensagem::factory()->create(['status' => 'publicado', 'nivel' => 'trabalhadores'])->autores()->attach($comRestrita->id);

        $this->get(route('autores.show', 'i9-publico'))->assertOk()->assertDontSee(self::FRASE_ANONIMO);

        $this->get(route('autores.show', 'i9-restrita'))->assertOk()
            ->assertSee(self::FRASE_ANONIMO)
            ->assertSee(route('login'), false);   // o rodapé @guest traz o link de login
    }
}
