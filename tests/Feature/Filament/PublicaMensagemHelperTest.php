<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-22

namespace Tests\Feature\Filament;

use App\Enums\VisibilidadeMensagem;
use App\Filament\Resources\Mensagens\Pages\PublicaMensagem;
use App\Filament\Schemas\MensagemForm;
use App\Models\Mensagem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Testa a REASSERÇÃO server-side isoladamente. Ela existe porque o `->required()` do form é
 * hidratação, não integridade — e os testes de UI passam pelo required, nunca por aqui.
 */
class PublicaMensagemHelperTest extends TestCase
{
    use RefreshDatabase;

    private function harness(): object
    {
        return new class
        {
            use PublicaMensagem;

            public function exec(array $data): array
            {
                return $this->reasserirRegraDePublicacao($data);
            }
        };
    }

    /**
     * Captura FORA do catch: `$this->fail()` dentro de `catch (RuntimeException)` é engolido,
     * porque AssertionFailedError estende RuntimeException.
     *
     * @return array<string, array<int, string>>
     */
    private function errosAoExecutar(array $data): array
    {
        $erros = null;

        try {
            $this->harness()->exec($data);
        } catch (ValidationException $e) {
            $erros = $e->errors();
        }

        $this->assertNotNull($erros, 'esperava ValidationException e não veio nenhuma');

        return $erros;
    }

    /** Ramo 1: status != publicado ⇒ early-return, sem validar e sem lançar. */
    public function test_status_nao_publicado_passa_direto(): void
    {
        $data = ['status' => Mensagem::STATUS_PENDENTE, 'nivel' => null, 'titulo' => 'Rascunho'];

        $this->assertSame($data, $this->harness()->exec($data));
    }

    /** Ramo 2: publicado sem nível ⇒ data.nivel, com a frase que ENSINA o caminho (C4). */
    public function test_publicado_sem_nivel_lanca_na_chave_do_nivel(): void
    {
        $erros = $this->errosAoExecutar(['status' => Mensagem::STATUS_PUBLICADO, 'nivel' => null]);

        $this->assertArrayHasKey('data.nivel', $erros);
        $this->assertSame(MensagemForm::MSG_NIVEL_OBRIGATORIO, $erros['data.nivel'][0]);
    }

    /** Ramo 2b: nível fora do enum é tão inválido quanto nulo (tryFrom fail-closed). */
    public function test_publicado_com_nivel_inexistente_lanca_na_chave_do_nivel(): void
    {
        $erros = $this->errosAoExecutar(['status' => Mensagem::STATUS_PUBLICADO, 'nivel' => 'lixo-invalido']);

        $this->assertArrayHasKey('data.nivel', $erros);
    }

    /** Ramo 3: direcionada cujo conjunto EFETIVO é vazio ⇒ data.destinatarios. */
    public function test_direcionada_so_com_inativo_lanca_na_chave_dos_destinatarios(): void
    {
        $inativo = User::factory()->create(['ativo' => false]);

        $erros = $this->errosAoExecutar([
            'status' => Mensagem::STATUS_PUBLICADO,
            'nivel' => VisibilidadeMensagem::Direcionada->value,
            'destinatarios' => [$inativo->id],
        ]);

        $this->assertArrayHasKey('data.destinatarios', $erros);
    }

    /** Guarda: direcionada com destinatário ATIVO passa — a regra não é um "não" universal. */
    public function test_direcionada_com_ativo_passa(): void
    {
        $ativo = User::factory()->create();

        $data = [
            'status' => Mensagem::STATUS_PUBLICADO,
            'nivel' => VisibilidadeMensagem::Direcionada->value,
            'destinatarios' => [$ativo->id],
        ];

        $this->assertSame($data, $this->harness()->exec($data));
    }
}
