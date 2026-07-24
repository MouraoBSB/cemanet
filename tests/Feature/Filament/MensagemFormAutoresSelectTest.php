<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-23

namespace Tests\Feature\Filament;

use App\Filament\Resources\Mensagens\Pages\CreateMensagem;
use App\Models\AutorEspiritual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class MensagemFormAutoresSelectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsAdmin();
    }

    /**
     * I6: as opções dos autores têm avatar E eager-loadam a mídia — abrir o form dispara
     * EXATAMENTE 1 query na tabela `media` (o whereIn eager), não 1 por autor (N+1).
     * R1: conta só as queries que tocam `media` (estável; independe do total do mount).
     * P5: se instável na execução, converter para verificação no browser (SPEC §7).
     */
    public function test_opcoes_de_autores_carregam_a_midia_em_uma_query(): void
    {
        AutorEspiritual::factory()->count(3)->create();

        DB::connection()->flushQueryLog();
        DB::connection()->enableQueryLog();
        Livewire::test(CreateMensagem::class);
        $queriesDeMidia = collect(DB::connection()->getQueryLog())
            ->filter(fn (array $q): bool => str_contains($q['query'], '"media"'))
            ->count();
        DB::connection()->disableQueryLog();

        $this->assertSame(1, $queriesDeMidia, 'as opções de autores devem eager-loadar a mídia numa única query (sem N+1)');
    }
}
