<?php

namespace Tests\Feature\Front;

use App\Models\Palestra;
use App\Models\Palestrante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PalestrantePerfilTest extends TestCase
{
    use RefreshDatabase;

    public function test_perfil_ativo_mostra_bio_e_palestras(): void
    {
        $pessoa = Palestrante::factory()->ativo()->create([
            'nome' => 'Abadio Rodrigues', 'slug' => 'abadio-rodrigues',
            'bio' => '<p>Trabalhador da casa.</p>',
        ]);
        $palestra = Palestra::factory()->create(['titulo' => 'Auxílios do Invisível', 'status' => Palestra::STATUS_PUBLICADO]);
        $palestra->palestrantes()->attach($pessoa, ['papel' => Palestra::PAPEL_PALESTRANTE]);

        $resp = $this->get(route('palestrantes.show', 'abadio-rodrigues'));

        $resp->assertOk();
        $resp->assertSee('Abadio Rodrigues');
        $resp->assertSee('Trabalhador da casa', false);
        $resp->assertSee('Auxílios do Invisível');
        $resp->assertSee('"@type":"Person"', false);
    }

    public function test_contato_respeita_flags(): void
    {
        Palestrante::factory()->ativo()->create([
            'slug' => 'com-email', 'email' => 'pessoa@cema.org', 'telefone' => '61999990000',
            'mostrar_email' => true, 'mostrar_telefone' => false,
        ]);

        $resp = $this->get(route('palestrantes.show', 'com-email'));

        $resp->assertSee('pessoa@cema.org');
        $resp->assertDontSee('61999990000');
    }

    public function test_email_oculto_quando_flag_desligada(): void
    {
        Palestrante::factory()->ativo()->create([
            'slug' => 'sem-email-publico', 'email' => 'oculto@cema.org', 'telefone' => '61888880000',
            'mostrar_email' => false, 'mostrar_telefone' => true,
        ]);

        $resp = $this->get(route('palestrantes.show', 'sem-email-publico'));

        $resp->assertOk();
        $resp->assertDontSee('oculto@cema.org'); // mostrar_email=false → e-mail nunca aparece
        $resp->assertSee('61888880000');         // telefone visível com mostrar_telefone=true
    }

    public function test_inativo_da_404(): void
    {
        Palestrante::factory()->inativo()->create(['slug' => 'oculto']);
        $this->get(route('palestrantes.show', 'oculto'))->assertNotFound();
    }

    public function test_slug_inexistente_da_404(): void
    {
        $this->get(route('palestrantes.show', 'nao-existe'))->assertNotFound();
    }
}
