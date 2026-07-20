<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-19

namespace Tests\Feature\Importacao;

use App\Importacao\ImportadorDirecionadasMensagens;
use App\Importacao\LeitorDirecionadasMensagem;
use App\Models\Mensagem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportadorDirecionadasMensagensTest extends TestCase
{
    use RefreshDatabase;

    /** @param array<int, array{wp_id:int, destinatarios_wp_ids:array<int,int>}> $dados */
    private function leitor(array $dados): LeitorDirecionadasMensagem
    {
        return new class($dados) implements LeitorDirecionadasMensagem
        {
            public function __construct(private array $dados) {}

            public function direcionadas(): array
            {
                return $this->dados;
            }
        };
    }

    private function fixtures(): void
    {
        Mensagem::factory()->create(['wp_id' => 100, 'slug' => 'm100', 'nivel' => 'direcionada']);
        Mensagem::factory()->create(['wp_id' => 101, 'slug' => 'm101', 'nivel' => 'direcionada']);
        User::factory()->create(['origem_legado_id' => 900]);
        User::factory()->create(['origem_legado_id' => 901]);
        // 902 NÃO existe (destinatário sem User); wp_id 999 NÃO existe (mensagem ausente).
    }

    public function test_casa_por_origem_legado_id_e_conta(): void
    {
        $this->fixtures();
        $importador = new ImportadorDirecionadasMensagens($this->leitor([
            ['wp_id' => 100, 'destinatarios_wp_ids' => [900, 901, 902]],
            ['wp_id' => 101, 'destinatarios_wp_ids' => [900]],
            ['wp_id' => 999, 'destinatarios_wp_ids' => [900]],
        ]));

        $resumo = $importador->importar(fn () => null);

        $this->assertSame(2, $resumo['direcionadas']);           // 100 e 101 (999 pulada)
        $this->assertSame(3, $resumo['vinculos']);               // 100→[900,901], 101→[900]
        $this->assertSame(2, $resumo['destinatarios_distintos']); // 900, 901
        $this->assertSame(1, $resumo['mensagem_nao_encontrada']); // 999
        $this->assertSame(1, $resumo['user_nao_encontrado']);     // 902

        $m100 = Mensagem::firstWhere('wp_id', 100);
        $this->assertEqualsCanonicalizing(
            [900, 901],
            $m100->destinatarios()->pluck('origem_legado_id')->map(fn ($v) => (int) $v)->all(),
        );
        $this->assertSame(
            [900],
            Mensagem::firstWhere('wp_id', 101)->destinatarios()->pluck('origem_legado_id')->map(fn ($v) => (int) $v)->all(),
        );
    }

    public function test_idempotente_e_sync_substitui(): void
    {
        $this->fixtures();

        $primeiro = new ImportadorDirecionadasMensagens($this->leitor([
            ['wp_id' => 100, 'destinatarios_wp_ids' => [900, 901]],
        ]));
        $primeiro->importar(fn () => null);
        $primeiro->importar(fn () => null); // 2ª rodada não duplica
        $this->assertSame(2, Mensagem::firstWhere('wp_id', 100)->destinatarios()->count());

        // sync substitui: agora só 900
        (new ImportadorDirecionadasMensagens($this->leitor([
            ['wp_id' => 100, 'destinatarios_wp_ids' => [900]],
        ])))->importar(fn () => null);
        $this->assertSame(
            [900],
            Mensagem::firstWhere('wp_id', 100)->destinatarios()->pluck('origem_legado_id')->map(fn ($v) => (int) $v)->all(),
        );
    }
}
