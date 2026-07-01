<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-01

namespace App\Support\Palestras;

use App\Models\Palestra;

final class FeedIcs
{
    public const PRODID = '-//CEMA//Palestras//PT-BR';

    private const LOCAL_PRESENCIAL = 'Centro Espírita Maria Madalena — Quadra 02, Lote 16, Vila Vicentina, Planaltina, DF';

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
        $local = $p->online ? 'Online — YouTube' : self::LOCAL_PRESENCIAL;

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

        return implode("\r\n", $linhas)."\r\n";
    }
}
