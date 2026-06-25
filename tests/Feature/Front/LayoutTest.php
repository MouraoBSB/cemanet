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
}
