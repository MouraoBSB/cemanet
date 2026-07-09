<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-01

namespace App\Support\Palestras;

use App\Models\Palestra;

final class FeedIcs
{
    public const PRODID = '-//CEMA//Palestras//PT-BR';

    /** Escapa valor para iCal: \, ; , e quebras de linha (CRLF/CR/LF → \n). */
    public static function escapar(string $v): string
    {
        return str_replace(
            ['\\', ';', ',', "\r\n", "\r", "\n"],
            ['\\\\', '\\;', '\\,', '\\n', '\\n', '\\n'],
            $v
        );
    }

    /**
     * Dobra uma linha lógica em linhas físicas de no máximo 75 OCTETOS (RFC 5545 §3.1).
     * A continuação começa com um espaço (que conta no limite). A quebra é por octetos,
     * mas nunca parte uma sequência multibyte UTF-8 (o em-dash e acentos ficam intactos).
     */
    public static function dobrar(string $linha): string
    {
        if (strlen($linha) <= 75) {
            return $linha;
        }

        $saida = '';
        $atual = 0; // octetos já escritos na linha física corrente

        foreach (mb_str_split($linha) as $ch) {
            $octetos = strlen($ch);
            if ($atual + $octetos > 75) {
                $saida .= "\r\n "; // continuação: CRLF + espaço (o espaço já ocupa 1 octeto)
                $atual = 1;
            }
            $saida .= $ch;
            $atual += $octetos;
        }

        return $saida;
    }

    /**
     * Linhas de UM VEVENT a partir da palestra (usa a hora REAL de data_da_palestra).
     *
     * @return list<string>
     */
    public static function vevento(Palestra $p): array
    {
        $inicio = $p->data_da_palestra->copy()->utc();
        $fim = $inicio->copy()->addMinutes(DuracaoPalestra::minutos($p->duracao));
        $fmt = fn ($d) => $d->format('Ymd\THis\Z');

        $palestrantes = $p->relationLoaded('palestrantesAtivos')
            ? $p->palestrantesAtivos->pluck('nome')->join(', ', ' e ')
            : '';
        $tema = $p->relationLoaded('assuntos') ? optional($p->assuntos->first())->nome : null;

        $partes = array_filter([
            $palestrantes !== '' ? 'com '.$palestrantes : null,
            $tema,
            $p->online ? 'Online' : 'Presencial',
        ]);
        $descricao = implode(' · ', $partes)."\n".route('palestras.show', $p->slug);
        $local = $p->online ? 'Online — YouTube' : config('cema.nome').' — '.config('cema.endereco');

        return [
            'BEGIN:VEVENT',
            'UID:palestra-'.$p->id.'@cemanet.org.br',
            'DTSTART:'.$fmt($inicio),
            'DTEND:'.$fmt($fim),
            'SUMMARY:'.self::escapar($p->titulo),
            'DESCRIPTION:'.self::escapar($descricao),
            'LOCATION:'.self::escapar($local),
            'END:VEVENT',
        ];
    }

    /** Documento VCALENDAR completo com N VEVENTs; pula palestras sem data. */
    public static function documento(iterable $palestras): string
    {
        $linhas = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:'.self::PRODID,
            'X-WR-CALNAME:Palestras CEMA',
            'X-WR-TIMEZONE:America/Sao_Paulo',
        ];

        foreach ($palestras as $p) {
            if ($p->data_da_palestra === null) {
                continue;
            }
            $linhas = array_merge($linhas, self::vevento($p));
        }

        $linhas[] = 'END:VCALENDAR';

        // Dobra cada linha lógica em linhas físicas ≤75 octetos antes de unir com CRLF.
        return implode("\r\n", array_map([self::class, 'dobrar'], $linhas))."\r\n";
    }
}
