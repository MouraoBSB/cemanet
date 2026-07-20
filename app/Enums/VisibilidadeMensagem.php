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

    /** Hue do nível (ponto/barra/legenda — decorativos, ao lado de rótulo textual ⇒ isentos de contraste). */
    public function cor(): string
    {
        return match ($this) {
            self::Publico => '#6E9FCB',
            self::Trabalhadores => '#A34E5C',
            self::Mediuns => '#5E8770',
            self::Diretores => '#3A4585',
            self::DiretorDepae => '#7C4D8F',
            self::Direcionada => '#26242E',
        };
    }

    /** Fundo translúcido do badge (rgba do hue) — base clara sobre a qual corTexto() atinge AA. */
    public function corFundo(): string
    {
        return match ($this) {
            self::Publico => 'rgba(110,159,203,0.16)',
            self::Trabalhadores => 'rgba(163,78,92,0.14)',
            self::Mediuns => 'rgba(94,135,112,0.18)',
            self::Diretores => 'rgba(58,69,133,0.14)',
            self::DiretorDepae => 'rgba(124,77,143,0.14)',
            self::Direcionada => 'rgba(38,36,46,0.10)',
        };
    }

    /** Cor de TEXTO do badge — escurecida do hue, ≥4,5:1 sobre corFundo() (AA). Validada na implementação. */
    public function corTexto(): string
    {
        return match ($this) {
            self::Publico => '#35618F',
            self::Trabalhadores => '#8F3F4D',
            self::Mediuns => '#3F7256',
            self::Diretores => '#3A4585',
            self::DiretorDepae => '#6A3E7C',
            self::Direcionada => '#26242E',
        };
    }

    /** Restrito = qualquer nível diferente de Público (inclui Trabalhadores/Diretores). NÃO é ehRecorte() (R3). */
    public function ehRestrito(): bool
    {
        return $this !== self::Publico;
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
