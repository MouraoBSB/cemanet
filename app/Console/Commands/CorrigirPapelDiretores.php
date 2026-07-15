<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-15

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Autorizacao\AuditoriaAutorizacao;
use Illuminate\Console\Command;

/**
 * Correção de dado (§9a): quem ocupa cargo de DIRETOR com departamento (cargos.departamento_id
 * NOT NULL, institucional=false) mas ainda tem papel 'trabalhador' é promovido a 'diretor'.
 * Filtro SEMÂNTICO (não hardcode de nome). No dev, o único desalinhado é o Valdemarques (§3.1).
 * Idempotente; audita a troca de papel. NUNCA destrutivo.
 */
class CorrigirPapelDiretores extends Command
{
    protected $signature = 'cema:corrigir-papel-diretores';

    protected $description = 'Promove a diretor quem tem cargo de diretor com departamento mas ainda é trabalhador (idempotente).';

    public function handle(): int
    {
        $usuarios = User::role('trabalhador')
            ->whereHas('cargos', fn ($q) => $q->whereNotNull('departamento_id')->where('institucional', false))
            ->get();

        $corrigidos = 0;
        foreach ($usuarios as $usuario) {
            $antes = $usuario->roles->pluck('name')->all();
            $usuario->syncRoles(['diretor']);
            AuditoriaAutorizacao::registrarPapelUsuario($usuario, $antes, ['diretor']);
            $corrigidos++;
        }

        $this->info(sprintf('%d usuário(s) promovido(s) a diretor.', $corrigidos));

        return self::SUCCESS;
    }
}
