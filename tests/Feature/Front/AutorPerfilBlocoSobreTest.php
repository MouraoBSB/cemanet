<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-23

namespace Tests\Feature\Front;

use App\Models\AutorEspiritual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutorPerfilBlocoSobreTest extends TestCase
{
    use RefreshDatabase;

    /** I14: com bio, o perfil mostra o bloco "Sobre {nome}" + a prosa. */
    public function test_i14_com_bio_mostra_o_bloco_sobre(): void
    {
        AutorEspiritual::factory()->create([
            'nome' => 'Bezerra de Menezes', 'slug' => 'bezerra', 'ativo' => true,
            'bio' => '<p>Médico e benfeitor espiritual, dedicou-se à caridade.</p>',
        ]);

        $this->get(route('autores.show', 'bezerra'))->assertOk()
            ->assertSee('Sobre Bezerra de Menezes')
            ->assertSee('Médico e benfeitor espiritual, dedicou-se à caridade.', false);
    }

    /** I14: sem bio, o bloco não existe (nem card vazio). */
    public function test_i14_sem_bio_nao_tem_o_bloco(): void
    {
        AutorEspiritual::factory()->create([
            'nome' => 'Autor Sem Bio', 'slug' => 'sem-bio', 'ativo' => true, 'bio' => null,
        ]);

        $this->get(route('autores.show', 'sem-bio'))->assertOk()->assertDontSee('Sobre Autor Sem Bio');
    }

    /** I15 (não-regressão): chamada vazia não deixa órfão nem quebra o perfil. */
    public function test_i15_chamada_vazia_nao_quebra(): void
    {
        AutorEspiritual::factory()->create([
            'nome' => 'Sem Chamada', 'slug' => 'sem-chamada', 'ativo' => true, 'chamada' => null,
        ]);

        $this->get(route('autores.show', 'sem-chamada'))->assertOk()->assertSee('Sem Chamada');
    }
}
