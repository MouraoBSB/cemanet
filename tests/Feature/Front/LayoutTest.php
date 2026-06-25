<?php

namespace Tests\Feature\Front;

use Tests\TestCase;

class LayoutTest extends TestCase
{
    public function test_home_renderiza_com_layout_base(): void
    {
        $resp = $this->get(route('home'));

        $resp->assertOk();
        $resp->assertSee('CEMA', false); // alt do logo / marca
        $resp->assertSee('lang="pt-BR"', false);
        // link para a listagem de palestras presente na navegação
        $resp->assertSee(route('palestras.index'), false);
    }

    public function test_header_tem_busca_e_itens_de_menu(): void
    {
        $resp = $this->get(route('home'));

        $resp->assertOk();
        // formulário de busca aponta para a listagem (GET ?q=)
        $resp->assertSee('action="'.route('palestras.index').'"', false);
        $resp->assertSee('name="q"', false);
        // item ativo é link; item futuro é placeholder (sem href de rota)
        $resp->assertSee('>Palestras<', false);
        $resp->assertSee('Mensagens Mediúnicas', false);
        // item futuro deve ser <span aria-disabled>, nunca <a href>
        $resp->assertSee('aria-disabled="true"', false);
        $resp->assertDontSee('href="'.route('palestras.index').'"'.'>Mensagens Mediúnicas', false);
    }

    public function test_footer_tem_cnpj_e_link_palestras(): void
    {
        $resp = $this->get(route('home'));

        $resp->assertOk();
        $resp->assertSee('01.600.089/0001-90', false);          // CNPJ
        $resp->assertSee(route('palestras.index'), false);       // link nas atividades
        $resp->assertSee('Inscreva-se', false);                  // newsletter (visual)
    }

    public function test_alpine_carregado_em_pagina_sem_componente_livewire(): void
    {
        // A home é Blade puro (sem componente Livewire); o header usa Alpine.
        // @livewireScripts no layout garante Livewire+Alpine carregados mesmo aqui.
        $resp = $this->get(route('home'));

        $resp->assertOk();
        $resp->assertSee('livewire', false); // tag de script do Livewire (traz o Alpine)
    }
}
