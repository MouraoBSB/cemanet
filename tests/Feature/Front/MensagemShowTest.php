<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-19

namespace Tests\Feature\Front;

use App\Enums\VisibilidadeMensagem;
use App\Models\AutorEspiritual;
use App\Models\Mensagem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MensagemShowTest extends TestCase
{
    use RefreshDatabase;

    /** PNG 1x1 mínimo (evita GD real sob carga — flaky conhecido do blog). */
    private const PNG_1X1 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAAC0lEQVQImWNgAAIAAAUAAWJVMogAAAAASUVORK5CYII=';

    public function test_publica_renderiza_200(): void
    {
        $m = Mensagem::factory()->publica()->create(['slug' => 'paz-e-luz', 'titulo' => 'Paz e Luz']);

        $this->get(route('mensagens.show', $m->slug))->assertOk()->assertSee('Paz e Luz');
    }

    public function test_pendente_e_inexistente_dao_404(): void
    {
        Mensagem::factory()->pendente()->create(['slug' => 'pendente-x']);

        $this->get(route('mensagens.show', 'pendente-x'))->assertNotFound();
        $this->get(route('mensagens.show', 'nao-existe'))->assertNotFound();
        // A mensagem RESTRITA publicada deixou de dar 404: vira barreira-200 cega (ver MensagemBarreiraTest).
    }

    public function test_sem_autor_mostra_sem_assinatura(): void
    {
        $m = Mensagem::factory()->publica()->create(['slug' => 'sa']);

        $this->get(route('mensagens.show', 'sa'))->assertSee('Sem assinatura');
    }

    public function test_dois_autores_aparecem(): void
    {
        $m = Mensagem::factory()->publica()->create(['slug' => 'dois']);
        $a1 = AutorEspiritual::factory()->create(['nome' => 'Emmanuel']);
        $a2 = AutorEspiritual::factory()->create(['nome' => 'André Luiz']);
        $m->autores()->sync([$a1->id, $a2->id]);

        $this->get(route('mensagens.show', 'dois'))->assertSee('Emmanuel')->assertSee('André Luiz');
    }

    public function test_download_so_quando_liberado(): void
    {
        $com = Mensagem::factory()->publica()->create(['slug' => 'com-dl', 'liberar_download' => true, 'link_arquivo' => 'https://drive.google.com/file/d/1AbC/view']);
        $sem = Mensagem::factory()->publica()->create(['slug' => 'sem-dl', 'liberar_download' => false, 'link_arquivo' => 'https://drive.google.com/file/d/1AbC/view']);

        $this->get(route('mensagens.show', 'com-dl'))->assertSee('Baixar arquivo');
        $this->get(route('mensagens.show', 'sem-dl'))->assertDontSee('Baixar arquivo');
    }

    public function test_recebidas_no_mesmo_dia_so_publicas(): void
    {
        $m = Mensagem::factory()->publica()->create(['slug' => 'hoje', 'data_recebimento' => '2025-03-10']);
        Mensagem::factory()->publica()->create(['data_recebimento' => '2025-03-10', 'titulo' => 'Irmã do mesmo dia']);
        Mensagem::factory()->pendente()->create(['data_recebimento' => '2025-03-10', 'titulo' => 'Pendente do mesmo dia']);

        $res = $this->get(route('mensagens.show', 'hoje'));
        $res->assertSee('Irmã do mesmo dia');
        $res->assertDontSee('Pendente do mesmo dia');
    }

    public function test_relacionadas_so_publicas(): void
    {
        $m = Mensagem::factory()->publica()->create(['slug' => 'rel']);
        $pub = Mensagem::factory()->publica()->create(['titulo' => 'Relacionada Pública']);
        $rest = Mensagem::factory()->create(['status' => 'publicado', 'nivel' => 'trabalhadores', 'titulo' => 'Relacionada Restrita']);
        $m->sincronizarRelacionadas([$pub->id, $rest->id]);

        $res = $this->get(route('mensagens.show', 'rel'));
        $res->assertSee('Relacionada Pública');
        $res->assertDontSee('Relacionada Restrita');
    }

    public function test_formato_null_nao_causa_500(): void
    {
        $m = Mensagem::factory()->publica()->create(['slug' => 'sem-formato', 'formato' => null]);

        $this->get(route('mensagens.show', 'sem-formato'))->assertOk();
    }

    public function test_sem_f3_e_f5(): void
    {
        $m = Mensagem::factory()->publica()->create(['slug' => 'limpa']);

        $res = $this->get(route('mensagens.show', 'limpa'));
        foreach (['Nível de acesso', 'Mensagem direcionada', 'Favoritar', 'Curtir'] as $proibido) {
            $res->assertDontSee($proibido);
        }
    }

    // -----------------------------------------------------------------------
    // I8: galeria de pictografia (com mídia) + download por imagem
    // -----------------------------------------------------------------------

    public function test_pictografia_renderiza_galeria_e_download(): void
    {
        Storage::fake('public');
        $m = Mensagem::factory()->publica()->create([
            'slug' => 'pict-galeria',
            'titulo' => 'Desenho Mediúnico',
            'formato' => 'pictografia',
        ]);
        $m->addMediaFromString(base64_decode(self::PNG_1X1))
            ->usingFileName('desenho.png')
            ->toMediaCollection(Mensagem::COLECAO_IMAGENS);

        $media = $m->fresh()->getMedia(Mensagem::COLECAO_IMAGENS)->first();

        $res = $this->get(route('mensagens.show', 'pict-galeria'));

        $res->assertOk();
        // <img> da galeria aponta para a conversão web (WebP), não o original.
        $res->assertSee($media->getUrl('web'), false);
        // Link de download por imagem: original + nome amigável derivado do título,
        // extensão derivada do arquivo real (aqui .png — NÃO mais hardcoded .jpg).
        $res->assertSee($media->getUrl(), false);
        $res->assertSee('download="desenho-mediunico-1.png"', false);
        $res->assertSee('Baixar', false);
    }

    // -----------------------------------------------------------------------
    // F4c-AC Task 7: galeria de imagens única para os 3 formatos
    // -----------------------------------------------------------------------

    /** @return Mensagem mensagem pública do formato dado, com 1 imagem na coleção */
    private function mensagemComImagem(string $formato, string $slug, ?string $corpo = '<p>Texto.</p>'): Mensagem
    {
        Storage::fake('public');
        $m = Mensagem::factory()->publica()->create(['slug' => $slug, 'formato' => $formato, 'titulo' => 'Mensagem Ilustrada', 'corpo' => $corpo]);
        $m->addMediaFromString(base64_decode(self::PNG_1X1))->usingFileName('img.png')
            ->toMediaCollection(Mensagem::COLECAO_IMAGENS);

        return $m->fresh();
    }

    /** I12: hoje a imagem some do site quando o formato não é Pictografia. */
    public function test_imagem_em_psicografia_aparece_no_corpo(): void
    {
        $m = $this->mensagemComImagem('psicografia', 'psico-com-imagem');

        $this->get(route('mensagens.show', 'psico-com-imagem'))
            ->assertOk()
            ->assertSee($m->getMedia(Mensagem::COLECAO_IMAGENS)->first()->getUrl('web'), false)
            ->assertSee('Imagem 1')
            ->assertSee('Baixar')
            ->assertSee('— imagem 1', false)        // I28: o alt segue a legenda (A11y)
            ->assertDontSee('— desenho 1', false)   // hoje o alt é hardcoded "desenho"
            ->assertSee('class="cema-pictografia-grid mt-8"', false);  // $attributes->class() mescla o mt-8 da psicografia
    }

    /** I13: a psicofonia inclui a psicografia — a galeria não pode sair em dobro. */
    public function test_psicofonia_mostra_a_galeria_uma_unica_vez(): void
    {
        $this->mensagemComImagem('psicofonia', 'psicofonia-com-imagem');

        $html = $this->get(route('mensagens.show', 'psicofonia-com-imagem'))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'Imagem 1'), 'a galeria duplicou pelo @include');
    }

    /** I28: na pictografia os desenhos SÃO a mensagem — a legenda diz isso. */
    public function test_pictografia_mantem_a_legenda_desenho(): void
    {
        $this->mensagemComImagem('pictografia', 'pict-legenda', null);

        $this->get(route('mensagens.show', 'pict-legenda'))
            ->assertOk()
            ->assertSee('Desenho 1')
            ->assertDontSee('Imagem 1')
            ->assertSee('— desenho 1', false)   // I28: o alt acompanha
            ->assertDontSee('ainda não tem desenhos disponíveis');  // não pode vazar com galeria cheia
    }

    /** I14: o texto de vazio é da pictografia e não pode vazar. Exige corpo NULL (senão passa por vacuidade). */
    public function test_estado_vazio_da_pictografia_nao_vaza_para_psicografia(): void
    {
        Mensagem::factory()->publica()->create(['slug' => 'psico-sem-nada', 'formato' => 'psicografia', 'corpo' => null]);

        $this->get(route('mensagens.show', 'psico-sem-nada'))
            ->assertOk()
            ->assertDontSee('ainda não tem desenhos disponíveis');
    }

    public function test_estado_vazio_continua_na_pictografia_sem_corpo_e_sem_imagem(): void
    {
        Mensagem::factory()->publica()->create(['slug' => 'pict-vazia', 'formato' => 'pictografia', 'corpo' => null]);

        $this->get(route('mensagens.show', 'pict-vazia'))->assertOk()->assertSee('ainda não tem desenhos disponíveis');
    }

    // -----------------------------------------------------------------------
    // F4c-AC Task 4: o resumo no single (meta description e lead)
    // -----------------------------------------------------------------------

    /**
     * I8: a asserção é sobre a TAG. O resumo também vira lead no corpo da página
     * (show.blade.php) — `assertSee`/`assertDontSee` soltos não distinguiriam
     * a meta description de nada.
     */
    public function test_meta_description_usa_o_resumo_quando_existe(): void
    {
        Mensagem::factory()->publica()->create([
            'slug' => 'com-resumo',
            'resumo' => 'Radian convida os trabalhadores a refletirem sobre a palavra.',
            'corpo' => '<p>Corpo que nao deve aparecer.</p>',
        ]);

        $this->get(route('mensagens.show', 'com-resumo'))
            ->assertOk()
            ->assertSee('name="description" content="Radian convida os trabalhadores a refletirem sobre a palavra."', false)
            ->assertDontSee('name="description" content="Corpo que nao deve aparecer."', false);
    }

    /** GUARDA: com a fusão, o corpo é o único fallback — a cadeia não pode ficar sem rede. */
    public function test_meta_description_cai_no_corpo_sem_resumo(): void
    {
        Mensagem::factory()->publica()->create([
            'slug' => 'sem-resumo', 'resumo' => null, 'corpo' => '<p>Corpo.</p>',
        ]);

        $this->get(route('mensagens.show', 'sem-resumo'))
            ->assertOk()
            ->assertSee('name="description" content="Corpo."', false);
    }

    /** I7 (F4c-D): a faixa "Contexto —" foi removida; o lead do resumo é o único texto editorial. */
    public function test_faixa_de_contexto_nao_existe_mais(): void
    {
        Mensagem::factory()->publica()->create([
            'slug' => 'sem-faixa', 'resumo' => 'Abertura editorial.',
        ]);

        $res = $this->get(route('mensagens.show', 'sem-faixa'));
        $res->assertOk()
            ->assertSee('cema-msg-resumo', false)          // o lead continua
            ->assertDontSee('>Contexto</strong>', false);  // a faixa não
    }

    /** D7: o lead é editorial e visualmente distinto da prosa mediúnica. */
    public function test_lead_do_resumo_aparece_no_single(): void
    {
        Mensagem::factory()->publica()->create([
            'slug' => 'lead-visivel', 'resumo' => "Primeiro parágrafo.\nSegundo parágrafo.",
        ]);

        $this->get(route('mensagens.show', 'lead-visivel'))
            ->assertOk()
            ->assertSee('cema-msg-resumo', false)
            ->assertSee('Primeiro parágrafo.<br />', false);   // nl2br honra os 12 com parágrafos
    }

    /** O lead é a única linha `{!! !!}` da fatia: e() ANTES de nl2br, senão é injeção. */
    public function test_resumo_do_lead_e_escapado(): void
    {
        Mensagem::factory()->publica()->create(['slug' => 'lead-xss', 'resumo' => "Nota <script>alert(1)</script>\nfim"]);

        $this->get(route('mensagens.show', 'lead-xss'))
            ->assertOk()
            ->assertSee('Nota &lt;script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('<br />', false);   // nl2br continua depois do e()
    }

    public function test_lead_nao_aparece_sem_resumo(): void
    {
        Mensagem::factory()->publica()->create(['slug' => 'sem-lead', 'resumo' => null]);

        $this->get(route('mensagens.show', 'sem-lead'))->assertOk()->assertDontSee('cema-msg-resumo', false);
    }

    /** I10 / REGRESSÃO (T10): a barreira intercepta ANTES do render — o resumo restrito não vaza. */
    public function test_barreira_continua_interceptando_o_restrito_depois_do_resumo(): void
    {
        Mensagem::factory()->comNivel(VisibilidadeMensagem::Trabalhadores)->create([
            'slug' => 'restrita-com-resumo',
            'status' => Mensagem::STATUS_PUBLICADO,
            'resumo' => 'Resumo reservado que o anônimo não pode ler.',
        ]);

        $this->get(route('mensagens.show', 'restrita-com-resumo'))
            ->assertOk()   // barreira-200 CEGA da 3B — sem o assertOk, um 404 deixaria isto verde
            ->assertDontSee('Resumo reservado', false);
    }
}
