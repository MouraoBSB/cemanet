<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-23

namespace Tests\Feature\Conta;

use App\Models\Cargo;
use App\Models\Setor;
use App\Models\User;
use Database\Seeders\EstruturaCemaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guarda do molde Filament-no-site: as páginas Fase E (/minha-conta/mensagens e /curadoria) precisam
 * injetar a paleta raw do Filament (--gray-* e --primary-*) num <style> INLINE no <head>. Ela NÃO pode
 * viver no theme.css: o transform do Tailwind v4 poda variáveis raw declaradas à mão em CSS processado
 * (medido: somem do bundle). Inline no HTML é imune ao build — como o @filamentStyles do /admin faz.
 * Sem a paleta, o trilho do fi-toggle (bg-gray-200 -> var(--gray-200)) computa transparent e o médium
 * não consegue enviar. Este teste prova, via GET HTTP real, que o <style> chega às DUAS telas com as
 * cores CEMA (primary roxo, matiz ~288), não a paleta default do Filament (âmbar, matiz ~58).
 * Ver docs/superpowers/specs/2026-07-23-molde-filament-no-site-paleta.md.
 */
class MoldeSitePaletaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstruturaCemaSeeder::class);
    }

    private function medium(): User
    {
        $user = User::factory()->create();
        $user->assignRole('trabalhador');
        $user->setores()->attach(Setor::where('slug', Setor::SLUG_MEDIUM)->value('id'), ['funcao' => 'membro']);

        return $user->fresh();
    }

    private function diretorDepae(): User
    {
        $user = User::factory()->create();
        $user->assignRole('diretor');
        $user->cargos()->attach(Cargo::where('slug', Cargo::SLUG_DIRETOR_DEPAE)->value('id'));

        return $user->fresh();
    }

    private function assertPaletaCemaInline(string $html): void
    {
        // Presença: --gray-200 pinta o trilho OFF do toggle.
        $this->assertStringContainsString('--gray-200:', $html);

        // Identidade CEMA: --primary-600 é o ROXO (matiz oklch ~288), não a paleta DEFAULT do Filament
        // (âmbar, matiz ~58) — que sairia se a paleta viesse da fonte errada. Aceita 200–299, rejeita ~58.
        $this->assertMatchesRegularExpression(
            '/--primary-600:\s*oklch\([\d.]+\s+[\d.]+\s+2\d\d/',
            $html,
        );
    }

    public function test_pagina_do_medium_injeta_a_paleta_do_filament_inline(): void
    {
        $response = $this->actingAs($this->medium())->get(route('conta.mensagens'));

        $response->assertOk();
        $this->assertPaletaCemaInline($response->getContent());
    }

    public function test_pagina_da_curadoria_injeta_a_paleta_do_filament_inline(): void
    {
        $response = $this->actingAs($this->diretorDepae())->get(route('conta.curadoria'));

        $response->assertOk();
        $this->assertPaletaCemaInline($response->getContent());
    }
}
