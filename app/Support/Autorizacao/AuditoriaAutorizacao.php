<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-13

namespace App\Support\Autorizacao;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role;

class AuditoriaAutorizacao
{
    /** log_name das entradas manuais dos 3 pivôs de autorização. */
    public const LOG = 'autorizacao';

    /** Porta forçada pelo contexto (ex.: 'perfil' no /minha-conta, que não é painel Filament). */
    private static ?string $portaForcada = null;

    /** Marca a porta corrente (setado no boot() do componente do site). Reset com null. */
    public static function usarPorta(?string $porta): void
    {
        self::$portaForcada = $porta;
    }

    /** Painel corrente: override > painel Filament > 'sistema'. Nunca cai no default por acidente. */
    public static function porta(): string
    {
        return self::$portaForcada ?? Filament::getCurrentPanel()?->getId() ?? 'sistema';
    }

    /** Contexto comum a toda entrada: porta + IP + user-agent (null fora de request HTTP). */
    public static function contexto(): array
    {
        $request = request();

        return [
            'porta' => self::porta(),
            'ip' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ];
    }

    /** Diff de duas listas de nomes: {adicionados, removidos} reindexados. */
    public static function diff(array $antes, array $depois): array
    {
        return [
            'adicionados' => array_values(array_diff($depois, $antes)),
            'removidos' => array_values(array_diff($antes, $depois)),
        ];
    }

    /** Matriz papel×capacidade: subject = Role; diff de nomes de permission. */
    public static function registrarPapelCapacidades(Role $papel, array $antes, array $depois): void
    {
        self::registrar($papel, "capacidades do papel {$papel->name} alteradas", self::diff($antes, $depois));
    }

    /** Papel do usuário: subject = User; diff de nomes de papel. */
    public static function registrarPapelUsuario(User $usuario, array $antes, array $depois): void
    {
        self::registrar($usuario, 'papel do usuário alterado', self::diff($antes, $depois));
    }

    /**
     * Vínculo editorial: subject = User; diff por id, itens {id, nome} (estável a rename).
     *
     * @param  array<int, string>  $antes  [id => nome] antes do sync
     * @param  array<int, string>  $depois  [id => nome] depois do sync
     */
    public static function registrarDepartamentosUsuario(User $usuario, array $antes, array $depois): void
    {
        $idsAdicionados = array_diff(array_keys($depois), array_keys($antes));
        $idsRemovidos = array_diff(array_keys($antes), array_keys($depois));

        $diff = [
            'adicionados' => array_values(array_map(
                fn (int $id): array => ['id' => $id, 'nome' => $depois[$id]],
                $idsAdicionados,
            )),
            'removidos' => array_values(array_map(
                fn (int $id): array => ['id' => $id, 'nome' => $antes[$id]],
                $idsRemovidos,
            )),
        ];

        self::registrar($usuario, 'departamentos do usuário alterados', $diff);
    }

    /**
     * Vínculo depto↔conteúdo: subject = o conteúdo; log_name = 'agenda' (mesma trilha do trait,
     * §8.3 do spec). Diff por id, itens {id, nome} (estável a rename).
     *
     * @param  array<int, string>  $antes  [id => nome] antes do sync
     * @param  array<int, string>  $depois  [id => nome] depois do sync
     */
    public static function registrarDepartamentosConteudo(Model $conteudo, array $antes, array $depois): void
    {
        $idsAdicionados = array_diff(array_keys($depois), array_keys($antes));
        $idsRemovidos = array_diff(array_keys($antes), array_keys($depois));

        $diff = [
            'adicionados' => array_values(array_map(fn (int $id): array => ['id' => $id, 'nome' => $depois[$id]], $idsAdicionados)),
            'removidos' => array_values(array_map(fn (int $id): array => ['id' => $id, 'nome' => $antes[$id]], $idsRemovidos)),
        ];

        self::registrar($conteudo, 'departamentos do conteúdo alterados', $diff, logName: 'agenda');
    }

    /** Escreve 1 entrada; no-op se o diff for vazio. */
    private static function registrar(Model $subject, string $descricao, array $diff, string $logName = self::LOG): void
    {
        if (empty($diff['adicionados']) && empty($diff['removidos'])) {
            return;
        }

        activity($logName)
            ->performedOn($subject)
            ->causedBy(auth()->user())
            ->withProperties(['diff' => $diff] + self::contexto())
            ->log($descricao);
    }
}
