<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-19

namespace Tests\Feature\Front;

use App\Models\AutorEspiritual;
use App\Models\Mensagem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AutorShowTest extends TestCase
{
    use RefreshDatabase;

    /** PNG 1x1 mínimo (evita GD real sob carga — flaky conhecido do blog). */
    private const PNG_1X1 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAAC0lEQVQImWNgAAIAAAUAAWJVMogAAAAASUVORK5CYII=';

    public function test_inativo_da_404(): void
    {
        $a = AutorEspiritual::factory()->create(['ativo' => false, 'slug' => 'inativo']);

        $this->get(route('autores.show', 'inativo'))->assertNotFound();
    }

    public function test_ativo_sem_publica_da_200(): void
    {
        $a = AutorEspiritual::factory()->create(['ativo' => true, 'slug' => 'vazio', 'nome' => 'Autor Vazio']);

        $this->get(route('autores.show', 'vazio'))->assertOk()->assertSee('Autor Vazio');
    }

    public function test_grade_e_stats_so_das_publicas(): void
    {
        $a = AutorEspiritual::factory()->create(['ativo' => true, 'slug' => 'emmanuel', 'nome' => 'Emmanuel']);
        Mensagem::factory()->publica()->create(['titulo' => 'Pública do Autor'])->autores()->sync([$a->id]);
        Mensagem::factory()->create(['status' => 'publicado', 'nivel' => 'diretores', 'titulo' => 'Restrita do Autor'])->autores()->sync([$a->id], false);

        $res = $this->get(route('autores.show', 'emmanuel'));
        $res->assertSee('Pública do Autor');
        $res->assertDontSee('Restrita do Autor');
    }

    public function test_mensagem_com_formato_null_nao_causa_500(): void
    {
        $a = AutorEspiritual::factory()->create(['ativo' => true, 'slug' => 'sem-formato-autor']);
        Mensagem::factory()->publica()->create(['formato' => null])->autores()->sync([$a->id]);

        $this->get(route('autores.show', 'sem-formato-autor'))->assertOk();
    }

    public function test_sem_curtir_e_com_link_login(): void
    {
        $a = AutorEspiritual::factory()->create(['ativo' => true, 'slug' => 'x']);

        $res = $this->get(route('autores.show', 'x'));
        $res->assertDontSee('Curtir');   // F5 fora (tile e botão)
        $res->assertSee(route('login'), false);   // rodapé estático de login
    }

    /** I8: o card prefere o resumo e cai no corpo quando não há. */
    public function test_card_usa_o_resumo_e_cai_no_corpo_sem_ele(): void
    {
        $autor = AutorEspiritual::factory()->create(['slug' => 'radian', 'ativo' => true]);

        $comResumo = Mensagem::factory()->publica()->create([
            'titulo' => 'Com resumo', 'resumo' => 'Trecho editorial do card.',
            'corpo' => '<p>Corpo que nao deve aparecer no card.</p>',
        ]);
        $semResumo = Mensagem::factory()->publica()->create([
            'titulo' => 'Sem resumo', 'resumo' => null, 'corpo' => '<p>Corpo de reserva.</p>',
        ]);
        $comResumo->autores()->attach($autor);
        $semResumo->autores()->attach($autor);

        $this->get(route('autores.show', 'radian'))
            ->assertOk()
            ->assertSee('Trecho editorial do card.')
            ->assertDontSee('Corpo que nao deve aparecer no card.')
            ->assertSee('Corpo de reserva.');
    }

    /** I12: o gate de FORMATO cai; o de VARIANTE fica. */
    public function test_card_do_perfil_mostra_imagem_de_psicografia(): void
    {
        Storage::fake('public');
        $autor = AutorEspiritual::factory()->create(['slug' => 'radian', 'ativo' => true]);
        $m = Mensagem::factory()->publica()->create(['formato' => 'psicografia', 'titulo' => 'Ilustrada']);
        $m->addMediaFromString(base64_decode(self::PNG_1X1))->usingFileName('c.png')
            ->toMediaCollection(Mensagem::COLECAO_IMAGENS);
        $m->autores()->attach($autor);

        $this->get(route('autores.show', 'radian'))
            ->assertOk()
            ->assertSee($m->fresh()->getFirstMediaUrl(Mensagem::COLECAO_IMAGENS, 'web'), false);
    }

    /** I15/D11: a lista pública segue sem miniatura — decisão de design da 2B. */
    public function test_lista_publica_continua_sem_miniatura(): void
    {
        Storage::fake('public');
        $m = Mensagem::factory()->publica()->create(['formato' => 'pictografia', 'slug' => 'na-lista']);
        $m->addMediaFromString(base64_decode(self::PNG_1X1))->usingFileName('d.png')
            ->toMediaCollection(Mensagem::COLECAO_IMAGENS);

        $this->get(route('mensagens.index'))
            ->assertOk()
            ->assertDontSee($m->fresh()->getFirstMediaUrl(Mensagem::COLECAO_IMAGENS, 'web'), false);
    }
}
