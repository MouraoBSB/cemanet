<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-19

namespace Tests\Feature\Support;

use App\Enums\FormatoMensagem;
use App\Models\Mensagem;
use App\Support\AutoresEspirituais\ResumoAutor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResumoAutorTest extends TestCase
{
    use RefreshDatabase;

    public function test_total_predominante_e_ultima(): void
    {
        $mensagens = collect([
            Mensagem::factory()->publica()->create(['formato' => 'psicografia', 'data_recebimento' => '2024-01-01']),
            Mensagem::factory()->publica()->create(['formato' => 'psicografia', 'data_recebimento' => '2025-06-01']),
            Mensagem::factory()->publica()->create(['formato' => 'psicofonia', 'data_recebimento' => '2023-01-01']),
        ]);
        $r = new ResumoAutor($mensagens);

        $this->assertSame(3, $r->total());
        $this->assertSame(FormatoMensagem::Psicografia, $r->predominante());
        $this->assertSame('2025-06-01', $r->ultimaMensagem()->format('Y-m-d'));
    }
}
