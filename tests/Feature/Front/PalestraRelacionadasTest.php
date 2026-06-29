<?php

namespace Tests\Feature\Front;

use App\Models\Assunto;
use App\Models\Palestra;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PalestraRelacionadasTest extends TestCase
{
    use RefreshDatabase;

    public function test_controller_monta_relacionadas_por_assunto(): void
    {
        $assunto = Assunto::factory()->create();
        $atual = Palestra::factory()->create(['slug' => 'atual', 'status' => Palestra::STATUS_PUBLICADO]);
        $atual->assuntos()->attach($assunto);

        $irma = Palestra::factory()->create(['titulo' => 'Palestra Irmã', 'status' => Palestra::STATUS_PUBLICADO]);
        $irma->assuntos()->attach($assunto);

        $resp = $this->get(route('palestras.show', 'atual'));

        $resp->assertOk();
        // Testa o DADO passado à view (não o HTML — a partial nasce na Task 7).
        $this->assertTrue($resp->viewData('relacionadas')->contains('id', $irma->id));
    }

    public function test_relacionadas_usam_fallback_quando_sem_assunto(): void
    {
        Palestra::factory()->create(['slug' => 'atual', 'status' => Palestra::STATUS_PUBLICADO]);
        $outra = Palestra::factory()->create(['titulo' => 'Outra Recente', 'status' => Palestra::STATUS_PUBLICADO]);

        $resp = $this->get(route('palestras.show', 'atual'));

        $resp->assertOk();
        $this->assertTrue($resp->viewData('relacionadas')->contains('id', $outra->id));
    }
}
