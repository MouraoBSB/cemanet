<?php

namespace Tests\Feature\Front;

use App\Models\Palestra;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PalestraCardArchiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_card_com_video_usa_hqdefault(): void
    {
        Palestra::factory()->create([
            'titulo' => 'Palestra Com Video',
            'status' => Palestra::STATUS_PUBLICADO,
            'link_youtube' => 'https://youtu.be/ABCdef12345',
        ]);

        $this->get(route('palestras.index'))
            ->assertOk()
            ->assertSee('hqdefault.jpg', false)
            ->assertSee('Palestra Pública', false);
    }

    public function test_card_sem_video_nao_emite_capa_youtube(): void
    {
        Palestra::factory()->create(['status' => Palestra::STATUS_PUBLICADO, 'link_youtube' => null]);

        $this->get(route('palestras.index'))
            ->assertOk()
            ->assertSee('logo-icone', false)
            ->assertDontSee('i.ytimg.com/vi/', false);
    }
}
