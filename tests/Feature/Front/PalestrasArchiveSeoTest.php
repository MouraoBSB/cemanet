<?php

namespace Tests\Feature\Front;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PalestrasArchiveSeoTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_tem_breadcrumb_jsonld(): void
    {
        $this->get(route('palestras.index'))
            ->assertOk()
            ->assertSee('application/ld+json', false)
            ->assertSee('"@type":"BreadcrumbList"', false);
    }

    public function test_veja_tambem_aponta_rotas_reais(): void
    {
        $resp = $this->get(route('palestras.index'));

        $resp->assertSee(route('palestrantes.index'), false);
        $resp->assertSee(route('blog.index'), false);
        $resp->assertSee(route('palestras.calendario'), false);
    }
}
