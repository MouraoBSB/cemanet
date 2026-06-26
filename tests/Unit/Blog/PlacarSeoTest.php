<?php

namespace Tests\Unit\Blog;

use App\Support\Blog\PlacarSeo;
use PHPUnit\Framework\TestCase;

class PlacarSeoTest extends TestCase
{
    private function conteudoCompleto(string $keyword = 'meditação espírita'): string
    {
        // 1º parágrafo com keyword (nos primeiros 120 chars do texto limpo)
        $paragrafo1 = "<p>A {$keyword} é uma prática fundamental para o crescimento espiritual. "
            . 'Ela nos conecta com as forças do bem e nos prepara para enfrentar os desafios da vida com serenidade. '
            . 'Praticada com regularidade, traz paz interior e equilíbrio emocional profundo.</p>';

        // Resto do conteúdo: subtítulo, parágrafos suficientes para ≥ 300 palavras, img com alt, link
        $resto = '<h2>Benefícios da Prática</h2>'
            . '<p>Entre os principais benefícios desta prática estão: melhora da concentração, '
            . 'ampliação da percepção espiritual, serenidade emocional, fortalecimento da fé e '
            . 'desenvolvimento da caridade. Cada um desses aspectos contribui para uma vida mais '
            . 'equilibrada e harmoniosa, tanto no plano físico quanto no espiritual permanente.</p>'
            . '<p>Os espíritas que praticam regularmente relatam uma melhora significativa em sua '
            . 'qualidade de vida, nas relações interpessoais e na capacidade de lidar com adversidades. '
            . 'A busca pelo autoconhecimento é a base de toda evolução espiritual verdadeira.</p>'
            . '<p>Dedicar alguns minutos diários à reflexão e ao silêncio interior é uma forma poderosa '
            . 'de se reconectar com o propósito mais elevado da existência humana e espiritual. '
            . 'Muitos médiuns e trabalhadores espirituais apontam a prática como essencial para o equilíbrio fluidico.</p>'
            . '<p>O amor ao próximo, a humildade e a perseverança são virtudes que se desenvolvem '
            . 'naturalmente quando a pessoa adota hábitos regulares de introspecção e oração sincera. '
            . 'A Doutrina Espírita ensina que a evolução moral e intelectual é contínua e progressiva.</p>'
            . '<p>As sessões de estudo, a prática da caridade e o trabalho mediúnico realizado com '
            . 'seriedade são pilares fundamentais para o crescimento espiritual de qualquer trabalhador. '
            . 'Cada ação praticada com amor e intenção elevada contribui para a nossa evolução coletiva.</p>'
            . '<p>A harmonia familiar, o respeito às diferenças e a tolerância são ensinamentos que '
            . 'encontramos na obra dos espíritos e que nos guiam na vida cotidiana com sabedoria. '
            . 'Praticar esses valores transforma positivamente a convivência em todos os ambientes sociais.</p>'
            . '<p>A gratidão pelas oportunidades de aprendizado e o reconhecimento dos erros passados '
            . 'são atitudes que aceleram a renovação espiritual e o progresso da alma imortal ao longo '
            . 'de suas diversas existências no plano material e espiritual conforme a doutrina ensina.</p>'
            . '<img src="meditacao.jpg" alt="Pessoa em meditação espírita" />'
            . '<a href="https://cemanet.org.br/programacao">Ver programação</a>';

        return $paragrafo1 . $resto;
    }

    public function test_tudo_ok_retorna_nota_100_e_todos_itens_true(): void
    {
        $keyword    = 'meditação espírita';
        $titulo     = 'A meditação espírita e seus benefícios';
        $conteudo   = $this->conteudoCompleto($keyword);
        $descricao  = 'Descubra como a meditação espírita transforma a vida e traz paz interior aos praticantes.';

        $resultado = PlacarSeo::analisar($conteudo, $titulo, $keyword, $descricao);

        $this->assertArrayHasKey('nota', $resultado);
        $this->assertArrayHasKey('itens', $resultado);
        $this->assertSame(100, $resultado['nota']);

        foreach ($resultado['itens'] as $item) {
            $this->assertTrue($item['ok'], "Item \"{$item['rotulo']}\" deveria ser ok=true");
        }
    }

    public function test_vazio_retorna_nota_baixa_e_keyword_nao_definida(): void
    {
        $resultado = PlacarSeo::analisar(null, null, null, null);

        $this->assertArrayHasKey('nota', $resultado);
        $this->assertArrayHasKey('itens', $resultado);
        $this->assertLessThan(50, $resultado['nota']);

        $itemKeyword = collect($resultado['itens'])->firstWhere('rotulo', 'Keyword definida');
        $this->assertNotNull($itemKeyword, 'Item "Keyword definida" deve existir');
        $this->assertFalse($itemKeyword['ok']);
    }

    public function test_img_sem_alt_marca_item_como_false(): void
    {
        $keyword   = 'meditação espírita';
        $titulo    = 'A meditação espírita na prática diária';
        $descricao = 'Aprenda como a meditação espírita pode transformar sua vida espiritual de forma profunda.';

        $conteudo = "<p>A {$keyword} é fundamental para o crescimento espiritual e para o equilíbrio "
            . 'emocional dos praticantes. Dedicar tempo à prática diária traz inúmeros benefícios.</p>'
            . '<h2>Como praticar</h2>'
            . '<p>Existem diversas formas de incorporar a meditação à rotina. Cada pessoa encontra '
            . 'seu próprio caminho, respeitando seus limites e possibilidades. O importante é manter '
            . 'a regularidade e a sinceridade de propósito em cada sessão praticada diariamente.</p>'
            . '<p>Com dedicação e perseverança, os resultados aparecem naturalmente ao longo do tempo. '
            . 'A paciência é uma virtude fundamental nesta jornada de autoconhecimento espiritual.</p>'
            . '<img src="sem-alt.jpg" />'  // sem alt
            . '<a href="https://cemanet.org.br">Saiba mais</a>';

        $resultado = PlacarSeo::analisar($conteudo, $titulo, $keyword, $descricao);

        $itemAlt = collect($resultado['itens'])->firstWhere('rotulo', 'Imagens com atributo alt');
        $this->assertNotNull($itemAlt, 'Item "Imagens com atributo alt" deve existir');
        $this->assertFalse($itemAlt['ok']);
    }

    public function test_keyword_ausente_do_titulo_marca_item_como_false(): void
    {
        $keyword   = 'meditação espírita';
        $titulo    = 'Práticas de equilíbrio interior';
        $descricao = 'Uma análise profunda das práticas espirituais que transformam a vida cotidiana dos praticantes.';

        $conteudo = "<p>A {$keyword} transforma vidas e traz serenidade aos praticantes hodiernos.</p>"
            . '<h2>Benefícios</h2>'
            . '<p>Os benefícios são inúmeros e impactam positivamente todas as áreas da vida.</p>'
            . str_repeat('<p>Texto adicional para atingir o mínimo de palavras necessárias.</p>', 8)
            . '<img src="img.jpg" alt="Meditação" />'
            . '<a href="https://cemanet.org.br">Link</a>';

        $resultado = PlacarSeo::analisar($conteudo, $titulo, $keyword, $descricao);

        $itemTitulo = collect($resultado['itens'])->firstWhere('rotulo', 'Keyword no título');
        $this->assertNotNull($itemTitulo);
        $this->assertFalse($itemTitulo['ok']);
    }

    public function test_descricao_muito_curta_marca_item_como_false(): void
    {
        $keyword   = 'meditação espírita';
        $titulo    = 'A meditação espírita e a paz interior';
        $descricao = 'Curta demais.'; // < 50 caracteres

        $conteudo = "<p>A {$keyword} é uma prática transformadora para todos.</p>"
            . '<h2>Prática diária</h2>'
            . str_repeat('<p>Conteúdo de preenchimento para atingir trezentas palavras no total do texto.</p>', 6)
            . '<img src="img.jpg" alt="Meditação espírita" />'
            . '<a href="https://cemanet.org.br">Ver mais</a>';

        $resultado = PlacarSeo::analisar($conteudo, $titulo, $keyword, $descricao);

        $itemDesc = collect($resultado['itens'])->firstWhere('rotulo', 'Meta description (50–160 caracteres)');
        $this->assertNotNull($itemDesc);
        $this->assertFalse($itemDesc['ok']);
    }

    public function test_estrutura_de_retorno_contem_nove_itens(): void
    {
        $resultado = PlacarSeo::analisar(null, null, null, null);

        $this->assertCount(9, $resultado['itens']);

        foreach ($resultado['itens'] as $item) {
            $this->assertArrayHasKey('ok', $item);
            $this->assertArrayHasKey('rotulo', $item);
            $this->assertIsBool($item['ok']);
            $this->assertIsString($item['rotulo']);
        }
    }
}
