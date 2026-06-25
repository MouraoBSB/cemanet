<?php

namespace Tests\Feature\Front;

use App\Models\Palestra;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PalestraInteracoesTest extends TestCase
{
    use RefreshDatabase;

    public function test_barra_de_acoes_tem_compartilhar_e_curtir(): void
    {
        $palestra = Palestra::factory()->create([
            'titulo' => 'Auxílios do Invisível',
            'slug' => 'auxilios-do-invisivel',
            'status' => Palestra::STATUS_PUBLICADO,
        ]);

        $resp = $this->get(route('palestras.show', 'auxilios-do-invisivel'));

        $resp->assertOk();
        $resp->assertSee('wa.me', false);                              // WhatsApp
        $resp->assertSee('facebook.com/sharer', false);                // Facebook
        $resp->assertSee('Copiar link', false);                        // copiar
        $resp->assertSee('x-data', false);                             // Alpine presente
        // O título da palestra deve aparecer urlencoded no parâmetro ?text= do WhatsApp
        $resp->assertSee(urlencode($palestra->titulo), false);
    }
}
