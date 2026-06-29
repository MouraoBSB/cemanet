<?php

namespace Tests\Feature\Front;

use App\Models\Palestra;
use App\Models\Palestrante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PalestraMultiPalestranteTest extends TestCase
{
    use RefreshDatabase;

    public function test_dois_palestrantes_aparecem_em_modo_compacto(): void
    {
        $palestra = Palestra::factory()->create(['slug' => 'dois-pal', 'status' => Palestra::STATUS_PUBLICADO]);
        $a = Palestrante::factory()->ativo()->create([
            'nome' => 'Wagner Alberto', 'slug' => 'wagner-alberto',
            'bio' => '<p>Bio extensa do Wagner que nao deve aparecer no compacto.</p>',
        ]);
        $b = Palestrante::factory()->ativo()->create([
            'nome' => 'Anderson Portugal', 'slug' => 'anderson-portugal',
            'bio' => '<p>Bio extensa do Anderson.</p>',
        ]);
        $palestra->palestrantes()->attach($a, ['papel' => Palestra::PAPEL_PALESTRANTE]);
        $palestra->palestrantes()->attach($b, ['papel' => Palestra::PAPEL_PALESTRANTE]);

        $resp = $this->get(route('palestras.show', 'dois-pal'));

        $resp->assertOk();
        // ambos os palestrantes aparecem, cada um com link de perfil
        $resp->assertSee('Wagner Alberto');
        $resp->assertSee('Anderson Portugal');
        $resp->assertSee(route('palestrantes.show', 'wagner-alberto'), false);
        $resp->assertSee(route('palestrantes.show', 'anderson-portugal'), false);
        // no modo compacto a bio NÃO é renderizada (distingue do card rico de 1 palestrante)
        $resp->assertDontSee('Bio extensa do Wagner');
        $resp->assertDontSee('Bio extensa do Anderson');
    }

    public function test_um_palestrante_mantem_o_card_rico_com_bio(): void
    {
        $palestra = Palestra::factory()->create(['slug' => 'um-pal', 'status' => Palestra::STATUS_PUBLICADO]);
        $a = Palestrante::factory()->ativo()->create([
            'nome' => 'Murilo Britto',
            'slug' => 'murilo-britto',
            'bio' => '<p>Palestrante dedicado ao estudo do Evangelho.</p>',
        ]);
        $palestra->palestrantes()->attach($a, ['papel' => Palestra::PAPEL_PALESTRANTE]);

        $resp = $this->get(route('palestras.show', 'um-pal'));

        $resp->assertOk();
        $resp->assertSee('Murilo Britto');
        $resp->assertSee('Palestrante dedicado ao estudo do Evangelho.'); // bio aparece no card rico
        $resp->assertSee('Ver perfil completo');
    }
}
