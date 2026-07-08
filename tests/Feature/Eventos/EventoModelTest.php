<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-08

namespace Tests\Feature\Eventos;

use App\Enums\VisibilidadeEvento;
use App\Models\CategoriaEvento;
use App\Models\Departamento;
use App\Models\Evento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class EventoModelTest extends TestCase
{
    use RefreshDatabase;

    private function eventoBase(array $overrides = []): Evento
    {
        return Evento::create(array_merge([
            'titulo' => 'Brechó Solidário',
            'slug' => 'brecho-solidario',
            'data_inicio' => '2026-06-27',
            'status' => Evento::STATUS_PUBLICADO,
        ], $overrides));
    }

    public function test_data_inicio_grava_string_e_le_carbon(): void
    {
        $evento = $this->eventoBase(['data_inicio' => Carbon::parse('2026-06-27 15:00')]);

        // grava só a data (Y-m-d), sem hora
        $this->assertSame('2026-06-27', $evento->getRawOriginal('data_inicio'));
        $this->assertInstanceOf(Carbon::class, $evento->fresh()->data_inicio);
    }

    public function test_hora_e_normalizada_para_hh_mm(): void
    {
        $evento = $this->eventoBase(['hora_inicio' => '8:30', 'hora_fim' => '12:00:00']);

        $this->assertSame('08:30', $evento->fresh()->hora_inicio);
        $this->assertSame('12:00', $evento->fresh()->hora_fim);
    }

    public function test_conteudo_e_sanitizado(): void
    {
        $evento = $this->eventoBase(['conteudo' => '<p>Oi</p><script>alert(1)</script>']);

        $this->assertStringNotContainsString('<script>', (string) $evento->conteudo);
        $this->assertStringContainsString('Oi', (string) $evento->conteudo);
    }

    public function test_visibilidade_e_enum(): void
    {
        $evento = $this->eventoBase(['visibilidade' => VisibilidadeEvento::Diretoria]);

        $this->assertSame(VisibilidadeEvento::Diretoria, $evento->fresh()->visibilidade);
    }

    public function test_relacoes_categoria_e_departamentos(): void
    {
        $cat = CategoriaEvento::create(['nome' => 'Brechó', 'slug' => 'brecho', 'cor' => '#89AB98']);
        $dep = Departamento::create(['sigla' => 'DEPRO', 'nome' => 'Promoções', 'slug' => 'depro']);

        $evento = $this->eventoBase(['categoria_evento_id' => $cat->id]);
        $evento->departamentos()->sync([$dep->id]);

        $this->assertSame('brecho', $evento->fresh()->categoria->slug);
        $this->assertTrue($evento->fresh()->departamentos->contains($dep));
    }

    public function test_scope_publicado(): void
    {
        $this->eventoBase(['slug' => 'a', 'status' => Evento::STATUS_PUBLICADO]);
        $this->eventoBase(['slug' => 'b', 'status' => Evento::STATUS_RASCUNHO]);

        $this->assertSame(1, Evento::publicado()->count());
    }

    public function test_accessor_periodo(): void
    {
        Carbon::setLocale('pt_BR');
        $evento = $this->eventoBase(['data_inicio' => '2026-06-27', 'hora_inicio' => '08:30', 'hora_fim' => '12:00']);

        $this->assertSame('27 de junho de 2026 · 8h30 – 12h', $evento->periodo);
    }
}
