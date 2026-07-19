<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-19

namespace Tests\Feature\Front;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MensagemUrlCompatTest extends TestCase
{
    use RefreshDatabase;

    public function test_archive_antigo_301_para_a_base_nova(): void
    {
        $this->get('/mensagem-mediunicas')
            ->assertStatus(301)
            ->assertRedirect('/mensagens-mediunicas');
    }

    public function test_single_antigo_301_preserva_o_slug(): void
    {
        $this->get('/mensagem-mediunicas/paz-e-luz')
            ->assertStatus(301)
            ->assertRedirect(route('mensagens.show', 'paz-e-luz'));
    }
}
