<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-19

namespace App\Enums;

enum VisibilidadeMensagem: string
{
    case Publico = 'publico';
    case Trabalhadores = 'trabalhadores';
    case Mediuns = 'mediuns-trabalhadores';   // RECORTE — setor Médium
    case Diretores = 'diretores';
    case DiretorDepae = 'diretor-depae';       // RECORTE — cargo Diretor do DEPAE
    case Direcionada = 'direcionada';          // RECORTE — pivô de destinatários

    /** Piso de escada (roles.nivel); null = RECORTE (pertencimento, não posição na escada). */
    public function nivelMinimo(): ?int
    {
        return match ($this) {
            self::Publico => 0,
            self::Trabalhadores => 20,
            self::Diretores => 30,
            self::Mediuns, self::DiretorDepae, self::Direcionada => null,
        };
    }

    /** Verdadeiro para os níveis de PERTENCIMENTO (Médiuns/Diretor-DEPAE/Direcionada). */
    public function ehRecorte(): bool
    {
        return $this->nivelMinimo() === null;
    }

    public function rotulo(): string
    {
        return match ($this) {
            self::Publico => 'Público',
            self::Trabalhadores => 'Trabalhadores',
            self::Mediuns => 'Médiuns',
            self::Diretores => 'Diretores',
            self::DiretorDepae => 'Diretor do DEPAE',
            self::Direcionada => 'Direcionada',
        };
    }

    /** Cor placeholder (AA). A paleta final da badge é da Fatia 3B (SPEC §13-O6). */
    public function cor(): string
    {
        return match ($this) {
            self::Publico => '#89AB98',
            self::Trabalhadores => '#6E9FCB',
            self::Mediuns => '#7C6FB0',
            self::Diretores => '#E79048',
            self::DiretorDepae => '#C9803B',
            self::Direcionada => '#C33A36',
        };
    }

    /** Mapa value => rótulo, para o Select do Filament (Fatias 3B/F4). */
    public static function opcoes(): array
    {
        $opcoes = [];
        foreach (self::cases() as $caso) {
            $opcoes[$caso->value] = $caso->rotulo();
        }

        return $opcoes;
    }
}
