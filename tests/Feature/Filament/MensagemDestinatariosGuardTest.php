<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-20

namespace Tests\Feature\Filament;

use App\Enums\VisibilidadeMensagem;
use App\Filament\Resources\Mensagens\Pages\SincronizaDestinatarios;
use Tests\TestCase;

/**
 * Testa o GUARD do trait isoladamente (sem UI, sem DB). capturarDestinatarios é `protected`;
 * o harness é uma classe anônima que `use` o trait e expõe o método + o estado idsDestinatarios.
 */
class MensagemDestinatariosGuardTest extends TestCase
{
    private function harness(): object
    {
        return new class
        {
            use SincronizaDestinatarios;

            /** @return array{data: array, ids: array} */
            public function exec(array $data): array
            {
                $limpo = $this->capturarDestinatarios($data);

                return ['data' => $limpo, 'ids' => $this->idsDestinatarios];
            }
        };
    }

    public function test_direcionada_preserva_os_destinatarios_do_payload(): void
    {
        $r = $this->harness()->exec([
            'nivel' => VisibilidadeMensagem::Direcionada->value,
            'destinatarios' => [7, 9],
        ]);

        $this->assertSame([7, 9], $r['ids']);
        $this->assertArrayNotHasKey('destinatarios', $r['data']); // sempre sai do $data (fora do auto-sync)
    }

    /** VERMELHO #2 (I4-guard, DISCRIMINANTE): nível != direcionada ⇒ conjunto VAZIO, ainda que o payload traga ids. */
    public function test_nao_direcionada_zera_mesmo_com_payload(): void
    {
        $r = $this->harness()->exec([
            'nivel' => VisibilidadeMensagem::Publico->value,
            'destinatarios' => [7, 9],
        ]);

        $this->assertSame([], $r['ids'], 'o guard vence o payload — não confia na UI');
    }

    public function test_nivel_ausente_zera(): void
    {
        $r = $this->harness()->exec(['destinatarios' => [7]]);

        $this->assertSame([], $r['ids']);
    }
}
