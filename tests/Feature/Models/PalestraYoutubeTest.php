<?php

namespace Tests\Feature\Models;

use App\Models\Palestra;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PalestraYoutubeTest extends TestCase
{
    use RefreshDatabase;

    public function test_extrai_id_de_formatos_comuns(): void
    {
        $casos = [
            'https://www.youtube.com/watch?v=ABC123defGH' => 'ABC123defGH',
            'https://youtu.be/ABC123defGH' => 'ABC123defGH',
            'https://www.youtube.com/live/ABC123defGH' => 'ABC123defGH',
            'https://www.youtube.com/embed/ABC123defGH' => 'ABC123defGH',
            'https://www.youtube.com/shorts/ABC123defGH' => 'ABC123defGH',
            'https://youtu.be/ABC123defGH?si=XYZ123' => 'ABC123defGH', // ignora query string
        ];
        foreach ($casos as $url => $id) {
            $p = Palestra::factory()->make(['link_youtube' => $url]);
            $this->assertSame($id, $p->youtube_id, "Falhou para: $url");
            $this->assertSame("https://i.ytimg.com/vi/{$id}/mqdefault.jpg", $p->youtube_thumb);
        }
    }

    public function test_sem_link_retorna_null(): void
    {
        $p = Palestra::factory()->make(['link_youtube' => null]);
        $this->assertNull($p->youtube_id);
        $this->assertNull($p->youtube_thumb);
    }
}
