<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-20

namespace Tests\Feature\Front;

use App\Models\Mensagem;
use App\Models\User;
use Database\Seeders\EstruturaCemaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MensagemIndexContadorTest extends TestCase
{
    use RefreshDatabase;

    public function test_rotulo_dinamico_e_cache_privado(): void
    {
        Mensagem::factory()->count(2)->publica()->create();   // 2 => PLURAL nos dois cenários (total !== 1)

        $this->get(route('mensagens.index'))->assertOk()->assertSee('mensagens públicas'); // anônimo (plural)

        $this->seed(EstruturaCemaSeeder::class);
        $u = User::factory()->create();
        $u->assignRole('trabalhador');

        $res = $this->actingAs($u)->get(route('mensagens.index'));
        $res->assertOk()->assertSee('mensagens disponíveis a você');   // logado
        $this->assertStringContainsString('private', $res->headers->get('Cache-Control')); // R2
    }
}
