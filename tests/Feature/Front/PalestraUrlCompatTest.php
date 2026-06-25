<?php

namespace Tests\Feature\Front;

use App\Models\Palestra;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PalestraUrlCompatTest extends TestCase
{
    use RefreshDatabase;

    public function test_listagem_responde_em_palestra_publica(): void
    {
        $this->get('/palestra_publica')->assertOk();
        $this->assertSame(url('/palestra_publica'), route('palestras.index'));
    }

    public function test_single_responde_em_palestra_publica_slug(): void
    {
        Palestra::factory()->create(['slug' => 'auxilios-do-invisivel', 'status' => Palestra::STATUS_PUBLICADO]);

        $this->get('/palestra_publica/auxilios-do-invisivel')->assertOk();
        $this->assertSame(url('/palestra_publica/auxilios-do-invisivel'), route('palestras.show', 'auxilios-do-invisivel'));
    }

    public function test_url_antiga_da_listagem_redireciona_301(): void
    {
        $this->get('/palestras')->assertRedirect('/palestra_publica')->assertStatus(301);
    }

    public function test_url_antiga_da_single_redireciona_301(): void
    {
        Palestra::factory()->create(['slug' => 'paz-e-luz', 'status' => Palestra::STATUS_PUBLICADO]);

        $resp = $this->get('/palestras/paz-e-luz');
        $resp->assertStatus(301);
        $resp->assertRedirect('/palestra_publica/paz-e-luz');
    }
}
