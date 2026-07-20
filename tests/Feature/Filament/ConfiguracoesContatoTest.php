<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-20

namespace Tests\Feature\Filament;

use App\Filament\Pages\ConfiguracoesContato;
use App\Models\Configuracao;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ConfiguracoesContatoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsAdmin();
    }

    public function test_pagina_renderiza(): void
    {
        $this->get('/admin/configuracoes-contato')->assertOk();
    }

    public function test_salva_email_e_whatsapp(): void
    {
        Livewire::test(ConfiguracoesContato::class)
            ->fillForm(['contato_email' => 'contato@cema.org', 'contato_whatsapp' => '+5561999990000'])
            ->call('salvar')
            ->assertHasNoFormErrors();

        $this->assertSame('contato@cema.org', Configuracao::valor('contato.email'));
        $this->assertSame('+5561999990000', Configuracao::valor('contato.whatsapp'));
    }
}
