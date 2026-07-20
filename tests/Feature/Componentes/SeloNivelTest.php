<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-20

namespace Tests\Feature\Componentes;

use App\Enums\VisibilidadeMensagem;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class SeloNivelTest extends TestCase
{
    private function selo(?VisibilidadeMensagem $v): string
    {
        return Blade::render('<x-mensagem.selo-nivel :visibilidade="$v" />', ['v' => $v]);
    }

    public function test_null_nao_renderiza_nada(): void
    {
        // B1: mensagem nivel=null (vista pelo admin) NÃO pode chamar null->rotulo() (500).
        $this->assertSame('', trim($this->selo(null)));
    }

    public function test_publico_sem_cadeado(): void
    {
        $html = $this->selo(VisibilidadeMensagem::Publico);
        $this->assertStringContainsString('Público', $html);
        $this->assertStringNotContainsString('Acesso restrito', $html); // sem cadeado
    }

    public function test_restrito_com_cadeado(): void
    {
        $html = $this->selo(VisibilidadeMensagem::Diretores);
        $this->assertStringContainsString('Diretores', $html);
        $this->assertStringContainsString('Acesso restrito', $html);    // cadeado (aria-label)
        $this->assertStringContainsString('#3A4585', $html);            // corTexto do nível
    }

    public function test_variante_escura_texto_branco(): void
    {
        // Hero navy do single: pílula translúcida BRANCA (texto branco), não a pílula clara do card/lista.
        $html = Blade::render('<x-mensagem.selo-nivel :visibilidade="$v" :escuro="true" />', [
            'v' => VisibilidadeMensagem::Diretores,
        ]);
        $this->assertStringContainsString('text-white/90', $html);
        $this->assertStringContainsString('Diretores', $html);
        $this->assertStringNotContainsString('#3A4585', $html); // NÃO usa o corTexto() escuro (seria ilegível no navy)
    }

    public function test_legenda_lista_niveis_presentes(): void
    {
        $html = Blade::render('<x-mensagem.legenda-niveis :niveis="$n" />', [
            'n' => collect([VisibilidadeMensagem::Publico, VisibilidadeMensagem::Trabalhadores]),
        ]);
        $this->assertStringContainsString('Nível de acesso', $html);
        $this->assertStringContainsString('Público', $html);
        $this->assertStringContainsString('Trabalhadores', $html);
    }
}
