<?php

namespace Tests\Feature\Importacao;

use App\Importacao\GlossarioAgenda;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlossarioAgendaTest extends TestCase
{
    use RefreshDatabase;

    public function test_chave_conhecida_resolve_para_o_texto(): void
    {
        $this->assertSame(
            ['valor' => 'Desenvolver abnegação, renúncia e solidariedade', 'aviso' => null],
            GlossarioAgenda::resolver('maio_2026'),
        );
        $this->assertSame(
            ['valor' => 'Desenvolver a Renúncia', 'aviso' => null],
            GlossarioAgenda::resolver('meta_dia_maio_2026_02'),
        );
    }

    public function test_chave_2026_desconhecida_grava_null_e_avisa(): void
    {
        $resultado = GlossarioAgenda::resolver('setembro_2026');
        $this->assertNull($resultado['valor']);
        $this->assertNotNull($resultado['aviso']);
        $this->assertStringContainsString('setembro_2026', $resultado['aviso']);

        // chave de meta do dia não mapeada também cai no null + aviso
        $meta = GlossarioAgenda::resolver('meta_dia_setembro_2026_01');
        $this->assertNull($meta['valor']);
        $this->assertNotNull($meta['aviso']);
    }

    public function test_texto_normal_e_null_passam_sem_alteracao(): void
    {
        $this->assertSame(
            ['valor' => 'Combater o egoísmo: indiferença e ingratidão', 'aviso' => null],
            GlossarioAgenda::resolver('Combater o egoísmo: indiferença e ingratidão'),
        );
        $this->assertSame(['valor' => null, 'aviso' => null], GlossarioAgenda::resolver(null));
    }
}
