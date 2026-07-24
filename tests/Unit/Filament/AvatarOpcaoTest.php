<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-23

namespace Tests\Unit\Filament;

use App\Filament\Support\AvatarOpcao;
use Tests\TestCase;

class AvatarOpcaoTest extends TestCase
{
    /** I3: sem foto → círculo de iniciais, nenhum <img>. */
    public function test_sem_foto_usa_iniciais_e_nao_tem_img(): void
    {
        $html = AvatarOpcao::html(null, 'Ana Prado', 'AP');

        $this->assertStringContainsString('>AP<', $html);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringContainsString('linear-gradient(to bottom right,#f2a81e,#d98a14)', $html);
    }

    /** I4: com foto → <img> circular, sem o círculo de iniciais. */
    public function test_com_foto_usa_img_e_nao_tem_iniciais(): void
    {
        $html = AvatarOpcao::html('https://ex.test/f.webp', 'Ana Prado', 'AP');

        $this->assertStringContainsString('<img src="https://ex.test/f.webp"', $html);
        $this->assertStringContainsString('object-fit:cover', $html);
        $this->assertStringNotContainsString('linear-gradient', $html);
    }

    /** I1: o nome é escapado (allowHtml não escapa — O2). */
    public function test_escapa_o_nome(): void
    {
        $html = AvatarOpcao::html(null, '<img src=x onerror=alert(1)>"Fulano', 'FU');

        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $html);
        $this->assertStringContainsString('&quot;Fulano', $html);
        // Não há <img> vindo do NOME (só poderia haver o do avatar, que aqui é null):
        $this->assertStringNotContainsString('<img', $html);
    }

    /** I2: a URL é escapada dentro do src (O2). */
    public function test_escapa_a_url(): void
    {
        $html = AvatarOpcao::html('x" onerror="alert(1)', 'Fulano', 'FU');

        $this->assertStringContainsString('src="x&quot; onerror=&quot;alert(1)"', $html);
    }

    /** I5: estilo inline, sem classe utilitária do site (O4). */
    public function test_usa_estilo_inline_sem_classe_do_site(): void
    {
        $html = AvatarOpcao::html(null, 'Ana Prado', 'AP');

        $this->assertStringContainsString('style=', $html);
        $this->assertStringNotContainsString('class=', $html);
        foreach (['from-gold', 'size-7', 'bg-gradient-to-br', 'rounded-full'] as $token) {
            $this->assertStringNotContainsString($token, $html);
        }
    }
}
