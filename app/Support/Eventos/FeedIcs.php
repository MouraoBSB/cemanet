<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-09

namespace App\Support\Eventos;

use App\Models\Evento;
use Illuminate\Support\Carbon;

final class FeedIcs
{
    public const PRODID = '-//CEMA//Eventos//PT-BR';

    public const FUSO = 'America/Sao_Paulo';

    /** Escapa valor para iCal (\, ; , e quebras de linha). */
    public static function escapar(string $v): string
    {
        return str_replace(
            ['\\', ';', ',', "\r\n", "\r", "\n"],
            ['\\\\', '\\;', '\\,', '\\n', '\\n', '\\n'],
            $v
        );
    }

    /**
     * Dobra a linha lógica em linhas físicas de ≤75 OCTETOS (RFC 5545 §3.1), sem
     * partir sequência multibyte UTF-8. A continuação começa com um espaço.
     */
    public static function dobrar(string $linha): string
    {
        if (strlen($linha) <= 75) {
            return $linha;
        }

        $saida = '';
        $atual = 0;

        foreach (mb_str_split($linha) as $ch) {
            $octetos = strlen($ch);
            if ($atual + $octetos > 75) {
                $saida .= "\r\n ";
                $atual = 1;
            }
            $saida .= $ch;
            $atual += $octetos;
        }

        return $saida;
    }

    public static function temHora(Evento $e): bool
    {
        return $e->temHora();
    }

    /** @return list<string> */
    public static function vevento(Evento $e): array
    {
        if (self::temHora($e)) {
            // Instantes vêm do model (fonte única compartilhada com o botão Google Calendar).
            $dt = [
                'DTSTART:'.$e->inicioUtc()->format('Ymd\THis\Z'),
                'DTEND:'.$e->fimUtc()->format('Ymd\THis\Z'),
            ];
        } else {
            // Dia inteiro: VALUE=DATE, DTEND exclusivo (data_fim, ou início, + 1 dia).
            $ini = Carbon::parse($e->getRawOriginal('data_inicio'))->format('Ymd');
            $fimExcl = Carbon::parse($e->getRawOriginal('data_fim') ?: $e->getRawOriginal('data_inicio'))
                ->addDay()->format('Ymd');
            $dt = ["DTSTART;VALUE=DATE:{$ini}", "DTEND;VALUE=DATE:{$fimExcl}"];
        }

        $descricao = trim(strip_tags((string) $e->resumo))."\n".route('eventos.show', $e->slug);
        $local = $e->local ?: config('cema.endereco');
        // DTSTAMP/SEQUENCE (RFC 5545 §3.6.1/§3.8.7.4) derivados de updated_at, NÃO de now():
        // o feed fica determinístico (preserva ETag/If-Modified-Since) e o cliente só reprocessa
        // o evento quando ele muda de verdade.
        $carimbo = $e->updated_at->copy()->utc();

        return array_merge(
            [
                'BEGIN:VEVENT',
                'UID:evento-'.$e->id.'@cemanet.org.br',
                'DTSTAMP:'.$carimbo->format('Ymd\THis\Z'),
                'SEQUENCE:'.$carimbo->getTimestamp(),
            ],
            $dt,
            [
                'SUMMARY:'.self::escapar($e->titulo),
                'DESCRIPTION:'.self::escapar($descricao),
                'LOCATION:'.self::escapar($local),
                'END:VEVENT',
            ]
        );
    }

    /** Documento VCALENDAR completo com N VEVENTs. */
    public static function documento(iterable $eventos): string
    {
        $linhas = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:'.self::PRODID,
            'X-WR-CALNAME:Eventos CEMA',
            'X-WR-TIMEZONE:'.self::FUSO,
        ];

        foreach ($eventos as $e) {
            $linhas = array_merge($linhas, self::vevento($e));
        }

        $linhas[] = 'END:VCALENDAR';

        return implode("\r\n", array_map([self::class, 'dobrar'], $linhas))."\r\n";
    }
}
