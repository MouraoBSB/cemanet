<?php

namespace Tests\Unit\Importacao;

use App\Importacao\TransformadorBlog;
use PHPUnit\Framework\TestCase;

class TransformadorBlogTest extends TestCase
{
    public function test_faqs_do_repeater(): void
    {
        $s = serialize(['item-0' => ['_pergunta_faq' => 'P1', '_resposta_faq' => 'R1'], 'item-1' => ['_pergunta_faq' => 'P2', '_resposta_faq' => 'R2']]);
        $f = TransformadorBlog::faqsDoRepeater($s);
        $this->assertCount(2, $f);
        $this->assertSame(['pergunta' => 'P1', 'resposta' => 'R1', 'ordem' => 0], $f[0]);
        $this->assertSame([], TransformadorBlog::faqsDoRepeater(null));
        $this->assertSame([], TransformadorBlog::faqsDoRepeater('lixo'));

        // item parcial (sem '_resposta_faq') é ignorado
        $parcial = serialize([['_pergunta_faq' => 'P']]);
        $this->assertSame([], TransformadorBlog::faqsDoRepeater($parcial));
    }

    public function test_galeria_do_repeater(): void
    {
        $s = serialize([0 => ['id' => 10, 'url' => 'https://x/a.jpg'], 1 => ['id' => 11, 'url' => 'https://x/b.jpg']]);
        $g = TransformadorBlog::galeriaDoRepeater($s);
        $this->assertSame(['url' => 'https://x/a.jpg', 'wp_id' => 10, 'ordem' => 0], $g[0]);
        $this->assertSame([], TransformadorBlog::galeriaDoRepeater(null));
        $this->assertSame([], TransformadorBlog::galeriaDoRepeater('lixo'));
    }

    public function test_tempo_leitura(): void
    {
        $this->assertSame(1, TransformadorBlog::tempoLeitura('<p>'.str_repeat('palavra ', 50).'</p>'));
        $this->assertSame(2, TransformadorBlog::tempoLeitura('<p>'.str_repeat('palavra ', 300).'</p>'));
    }

    public function test_status_post(): void
    {
        $this->assertSame('publicado', TransformadorBlog::statusPost('publish'));
        $this->assertSame('agendado', TransformadorBlog::statusPost('future'));
        $this->assertSame('rascunho', TransformadorBlog::statusPost('draft'));
    }
}
