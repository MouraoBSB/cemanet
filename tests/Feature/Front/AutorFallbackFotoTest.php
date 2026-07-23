<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-23

namespace Tests\Feature\Front;

use App\Models\AutorEspiritual;
use App\Models\Mensagem;
use App\Models\Palestrante;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AutorFallbackFotoTest extends TestCase
{
    use RefreshDatabase;

    /** PNG 1x1 mínimo (evita GD real sob carga — flaky conhecido). */
    private const PNG_1X1 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAAC0lEQVQImWNgAAIAAAUAAWJVMogAAAAASUVORK5CYII=';

    /** I12: autor SEM foto mostra o SVG (não as iniciais) — no card (lista) e no hero (perfil). */
    public function test_i12_autor_sem_foto_mostra_o_svg(): void
    {
        $autor = AutorEspiritual::factory()->create(['nome' => 'Sem Retrato', 'slug' => 'sem-retrato', 'ativo' => true]);
        Mensagem::factory()->publica()->create()->autores()->attach($autor->id);

        $this->get(route('autores.index'))->assertOk()->assertSee('images/autor-fallback.svg', false);
        $this->get(route('autores.show', 'sem-retrato'))->assertOk()->assertSee('images/autor-fallback.svg', false);
    }

    /** I12: autor COM foto mostra a foto, não o SVG. */
    public function test_i12_autor_com_foto_mostra_a_foto(): void
    {
        Storage::fake('public');
        $autor = AutorEspiritual::factory()->create(['nome' => 'Com Retrato', 'slug' => 'com-retrato', 'ativo' => true]);
        $autor->addMediaFromString(base64_decode(self::PNG_1X1))->usingFileName('f.png')->toMediaCollection(AutorEspiritual::COLECAO_FOTO);
        Mensagem::factory()->publica()->create()->autores()->attach($autor->id);

        $res = $this->get(route('autores.show', 'com-retrato'));
        $res->assertOk()
            ->assertSee($autor->fresh()->foto_url, false)
            ->assertDontSee('images/autor-fallback.svg', false);
    }

    /** I13 (não-regressão): o trait compartilhado segue dando iniciais — o fallback é só do autor. */
    public function test_i13_fallback_nao_afeta_palestrante_nem_user(): void
    {
        // Atribuição direta (sem depender de $fillable): o trait TemIniciais é o que importa.
        $palestrante = new Palestrante;
        $palestrante->nome = 'Bezerra Menezes';
        $user = new User;
        $user->name = 'Ana Prado';

        $this->assertSame('BM', $palestrante->iniciais);
        $this->assertSame('AP', $user->iniciais);
    }
}
