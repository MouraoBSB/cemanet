<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-12

namespace Tests\Feature\Autorizacao;

use App\Importacao\GlossarioUsuarios;
use App\Support\Autorizacao\GlossarioCapacidades;
use Tests\TestCase;

class GlossarioRotulosTest extends TestCase
{
    public function test_rotulos_de_recurso_cobrem_os_casos_slug_diferente_do_model(): void
    {
        $this->assertSame('Agenda do Dia', GlossarioCapacidades::rotuloRecurso('agenda'));
        $this->assertSame('Palestrante', GlossarioCapacidades::rotuloRecurso('palestrante'));
        $this->assertSame('Evento', GlossarioCapacidades::rotuloRecurso('evento'));
        $this->assertSame('Xyz', GlossarioCapacidades::rotuloRecurso('xyz')); // fallback ucfirst
    }

    public function test_rotulos_de_acao(): void
    {
        $this->assertSame('Ver', GlossarioCapacidades::rotuloAcao('ver'));
        $this->assertSame('Editar', GlossarioCapacidades::rotuloAcao('editar'));
        $this->assertSame('Excluir', GlossarioCapacidades::rotuloAcao('excluir'));
    }

    public function test_papeis_editaveis_sao_trabalhador_e_diretor(): void
    {
        $this->assertSame(['trabalhador', 'diretor'], GlossarioUsuarios::PAPEIS_EDITAVEIS);
    }
}
