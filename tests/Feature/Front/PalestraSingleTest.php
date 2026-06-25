<?php

namespace Tests\Feature\Front;

use App\Models\Palestra;
use App\Models\Palestrante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PalestraSingleTest extends TestCase
{
    use RefreshDatabase;

    private function palestraComPessoas(): Palestra
    {
        $palestra = Palestra::factory()->create([
            'titulo' => 'Auxílios do Invisível',
            'slug' => 'auxilios-do-invisivel',
            'status' => Palestra::STATUS_PUBLICADO,
        ]);
        $ativo = Palestrante::factory()->ativo()->create(['nome' => 'João Ativo']);
        $diretor = Palestrante::factory()->inativo()->create(['nome' => 'Maria Inativa']);
        $palestra->palestrantes()->attach($ativo, ['papel' => Palestra::PAPEL_PALESTRANTE]);
        $palestra->palestrantes()->attach($diretor, ['papel' => Palestra::PAPEL_DIRETOR]);
        $palestra->destaques()->create(['destaque' => 'A fé raciocinada', 'texto' => 'Estudo sério.', 'ordem' => 0]);

        return $palestra;
    }

    public function test_single_publica_retorna_200_com_conteudo(): void
    {
        $this->palestraComPessoas();

        $resp = $this->get(route('palestras.show', 'auxilios-do-invisivel'));

        $resp->assertOk();
        $resp->assertSee('Auxílios do Invisível');
        $resp->assertSee('João Ativo');           // palestrante ativo aparece
        $resp->assertSee('A fé raciocinada');      // destaque aparece
    }

    public function test_single_nao_mostra_diretor_inativo(): void
    {
        $this->palestraComPessoas();

        $resp = $this->get(route('palestras.show', 'auxilios-do-invisivel'));

        $resp->assertDontSee('Maria Inativa');
    }

    public function test_single_rascunho_da_404(): void
    {
        Palestra::factory()->create(['slug' => 'oculta', 'status' => Palestra::STATUS_RASCUNHO]);

        $this->get(route('palestras.show', 'oculta'))->assertNotFound();
    }

    public function test_single_slug_inexistente_da_404(): void
    {
        $this->get(route('palestras.show', 'nao-existe'))->assertNotFound();
    }

    public function test_single_tem_jsonld_event(): void
    {
        $this->palestraComPessoas();

        $resp = $this->get(route('palestras.show', 'auxilios-do-invisivel'));

        $resp->assertSee('application/ld+json', false);
        $resp->assertSee('"@type":"Event"', false);
    }

    public function test_navegacao_funciona_quando_palestra_nao_tem_data(): void
    {
        // 3 palestras publicadas; a do meio (por id) sem data_da_palestra.
        Palestra::factory()->create([
            'titulo' => 'Palestra Anterior',
            'slug' => 'palestra-anterior',
            'status' => Palestra::STATUS_PUBLICADO,
        ]);
        $semData = Palestra::factory()->create([
            'titulo' => 'Paz e Nós',
            'slug' => 'paz-e-nos',
            'status' => Palestra::STATUS_PUBLICADO,
            'data_da_palestra' => null,
        ]);
        Palestra::factory()->create([
            'titulo' => 'Palestra Seguinte',
            'slug' => 'palestra-seguinte',
            'status' => Palestra::STATUS_PUBLICADO,
        ]);

        $resp = $this->get(route('palestras.show', 'paz-e-nos'));

        $resp->assertOk();
        // A navegação deve exibir os títulos da anterior e da próxima.
        $resp->assertSee('Palestra Anterior');
        $resp->assertSee('Palestra Seguinte');
    }

    public function test_single_linka_perfil_do_palestrante(): void
    {
        $palestra = Palestra::factory()->create(['slug' => 'aux-link', 'status' => Palestra::STATUS_PUBLICADO]);
        $p = Palestrante::factory()->ativo()->create(['nome' => 'João Ativo', 'slug' => 'joao-ativo']);
        $palestra->palestrantes()->attach($p, ['papel' => Palestra::PAPEL_PALESTRANTE]);

        $resp = $this->get(route('palestras.show', 'aux-link'));

        $resp->assertOk();
        $resp->assertSee(route('palestrantes.show', 'joao-ativo'), false);
    }

    public function test_jsonld_escapa_tag_de_fechamento_de_script(): void
    {
        // Título com vetor XSS: se JSON_HEX_TAG estiver ausente, o </script>
        // fecha o bloco cedo e o restante do JSON vaza como HTML.
        Palestra::factory()->create([
            'titulo' => 'Ataque </script> XSS',
            'slug' => 'ataque-xss-jsonld',
            'status' => Palestra::STATUS_PUBLICADO,
        ]);

        $resp = $this->get(route('palestras.show', 'ataque-xss-jsonld'));

        $resp->assertOk();
        // Com JSON_HEX_TAG, o '<' do título vira '<' — nunca '</script>' literal no HTML.
        $resp->assertSee('<', false);
        // A sequência crua '</script> XSS' não deve aparecer no HTML.
        $resp->assertDontSee('</script> XSS', false);
    }

    public function test_hero_e_sempre_roxo_e_ignora_cor_fundo(): void
    {
        Palestra::factory()->create([
            'slug' => 'hero-roxo',
            'status' => Palestra::STATUS_PUBLICADO,
            'cor_fundo' => '#abcdef',
        ]);

        $resp = $this->get(route('palestras.show', 'hero-roxo'));

        $resp->assertOk();
        $resp->assertSee('from-primary to-footer-bg', false);   // gradiente institucional
        $resp->assertSee('cema-hero-deco', false);              // partículas (efeito CSS)
        $resp->assertDontSee('background:#abcdef', false);      // cor_fundo NÃO é aplicada no hero
    }
}
