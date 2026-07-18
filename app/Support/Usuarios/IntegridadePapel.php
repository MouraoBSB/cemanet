<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-17

namespace App\Support\Usuarios;

use App\Importacao\GlossarioUsuarios;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Trava de integridade papel × estrutura no cadastro de usuário.
 * R1: ter setor exige papel >= Trabalhador. R2: ter cargo exige papel >= Diretor.
 *
 * A GARANTIA é server-side: assegurar() lê o estado REAL gravado (pós-sync) e aborta o save. Vale
 * nos dois sentidos (rebaixar papel × adicionar setor/cargo), porque avalia o estado final combinado.
 */
final class IntegridadePapel
{
    private const NIVEL_MIN_SETOR = GlossarioUsuarios::PAPEIS['trabalhador']; // 20

    private const NIVEL_MIN_CARGO = GlossarioUsuarios::PAPEIS['diretor'];     // 30

    /**
     * @return list<string> mensagens de violação (vazio = íntegro). Pura — testável sem banco.
     */
    public static function violacoes(int $nivel, bool $temSetor, bool $temCargo): array
    {
        $violacoes = [];

        if ($temSetor && $nivel < self::NIVEL_MIN_SETOR) {
            $violacoes[] = 'Um usuário com setor precisa ter papel Trabalhador ou acima. '
                .'Remova os setores ou eleve o papel.';
        }

        if ($temCargo && $nivel < self::NIVEL_MIN_CARGO) {
            $violacoes[] = 'Um usuário com cargo precisa ter papel Diretor. '
                .'Remova os cargos ou eleve o papel.';
        }

        return $violacoes;
    }

    /**
     * Lê o estado REAL sincronizado (queries frescas — nunca a coleção cacheada de nivelMaximo,
     * que no afterSave é a de antes do sync) e aborta o save se ferir R1/R2.
     */
    public static function assegurar(User $registro): void
    {
        $violacoes = self::violacoes(
            (int) $registro->roles()->max('nivel'),
            $registro->setores()->exists(),
            $registro->cargos()->exists(),
        );

        if ($violacoes !== []) {
            // Repropagada pelo catch(Throwable) do Filament => rollback do save (com a transação
            // ligada) + erro no campo. Chave completa 'data.roles' (statePath 'data' + campo 'roles').
            throw ValidationException::withMessages(['data.roles' => $violacoes]);
        }
    }
}
