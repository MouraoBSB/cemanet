<?php

namespace Tests\Feature\Front;

use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlogUrlCompatTest extends TestCase
{
    use RefreshDatabase;

    /** Post publicado na raiz deve redirecionar 301 para /sementeira/{slug}. */
    public function test_slug_publicado_na_raiz_redireciona_301_para_sementeira(): void
    {
        Post::factory()->create(['slug' => 'meu-post-teste', 'status' => Post::STATUS_PUBLICADO]);

        $resp = $this->get('/meu-post-teste');

        $resp->assertStatus(301);
        $resp->assertRedirect('/sementeira/meu-post-teste');
    }

    /** URL antiga de categoria redireciona 301 para listagem filtrada. */
    public function test_url_categoria_antiga_redireciona_301_para_listagem_filtrada(): void
    {
        $resp = $this->get('/categoria/reflexoes-e-espiritualidade');

        $resp->assertStatus(301);
        $resp->assertRedirect('/sementeira?categoria=reflexoes-e-espiritualidade');
    }

    /** Slug inexistente na raiz resulta em 404. */
    public function test_slug_inexistente_na_raiz_retorna_404(): void
    {
        $resp = $this->get('/nao-existe-mesmo');

        $resp->assertStatus(404);
    }

    /** Rota nomeada /palestrantes não é capturada pelo catch-all. */
    public function test_rota_palestrantes_nao_e_capturada_pelo_catch_all(): void
    {
        $resp = $this->get(route('palestrantes.index'));

        $resp->assertStatus(200);
    }
}
