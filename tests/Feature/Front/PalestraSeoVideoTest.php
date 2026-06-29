<?php

namespace Tests\Feature\Front;

use App\Models\Palestra;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PalestraSeoVideoTest extends TestCase
{
    use RefreshDatabase;

    public function test_videoobject_presente_quando_ha_video(): void
    {
        Palestra::factory()->create([
            'slug' => 'com-video',
            'status' => Palestra::STATUS_PUBLICADO,
            'link_youtube' => 'https://youtube.com/live/ABCdefGhijk',
        ]);

        $resp = $this->get(route('palestras.show', 'com-video'));

        $resp->assertOk();
        $resp->assertSee('"@type":"VideoObject"', false);
        $resp->assertSee('og:image', false);
    }

    public function test_videoobject_ausente_sem_video(): void
    {
        Palestra::factory()->create(['slug' => 'sem-video', 'status' => Palestra::STATUS_PUBLICADO, 'link_youtube' => null]);

        $this->get(route('palestras.show', 'sem-video'))->assertOk()->assertDontSee('"@type":"VideoObject"', false);
    }
}
