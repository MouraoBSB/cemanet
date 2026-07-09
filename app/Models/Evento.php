<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-08

namespace App\Models;

use App\Enums\VisibilidadeEvento;
use App\Models\Concerns\RegistraImagensPadrao;
use App\Support\Eventos\PeriodoEvento;
use App\Support\Eventos\StatusEvento;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Evento extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia, RegistraImagensPadrao;

    public const STATUS_PUBLICADO = 'publicado';

    public const STATUS_RASCUNHO = 'rascunho';

    public const COLECAO_FLYER = 'flyer';

    public const COLECAO_GALERIA = 'galeria';

    protected $fillable = [
        'titulo', 'slug', 'resumo', 'conteudo',
        'data_inicio', 'hora_inicio', 'data_fim', 'hora_fim',
        'local', 'categoria_evento_id', 'visibilidade', 'status', 'wp_id',
    ];

    protected function casts(): array
    {
        return [
            'visibilidade' => VisibilidadeEvento::class,
        ];
    }

    public function registerMediaCollections(): void
    {
        // Flyer/capa (1 imagem) + galeria (N imagens), tratamento padrão do sistema.
        $this->registrarColecaoImagem(self::COLECAO_FLYER);
        $this->registrarColecaoImagem(self::COLECAO_GALERIA, unica: false, larguraWeb: 1920);
    }

    public function scopePublicado(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLICADO);
    }

    /** Regra de visibilidade por papel (fonte única). Admin (nível 100) satisfaz qualquer >=. */
    public function podeSerVistoPor(?User $usuario): bool
    {
        $nivel = $usuario?->nivelMaximo() ?? 0;

        return match ($this->visibilidade) {
            VisibilidadeEvento::Publico => true,
            VisibilidadeEvento::Logados => $usuario !== null,
            VisibilidadeEvento::Trabalhadores => $usuario !== null && $nivel >= VisibilidadeEvento::Trabalhadores->nivelMinimo(),
            VisibilidadeEvento::Diretoria => $usuario !== null && $nivel >= VisibilidadeEvento::Diretoria->nivelMinimo(),
        };
    }

    /** Filtra no banco os eventos que o usuário (ou anônimo) pode ver — não vaza títulos restritos. */
    public function scopeVisiveisPara(Builder $query, ?User $usuario): Builder
    {
        $nivel = $usuario?->nivelMaximo() ?? 0;

        return $query->where(function (Builder $q) use ($usuario, $nivel) {
            $q->where('visibilidade', VisibilidadeEvento::Publico->value);
            if ($usuario !== null) {
                $q->orWhere('visibilidade', VisibilidadeEvento::Logados->value);
                if ($nivel >= VisibilidadeEvento::Trabalhadores->nivelMinimo()) {
                    $q->orWhere('visibilidade', VisibilidadeEvento::Trabalhadores->value);
                }
                if ($nivel >= VisibilidadeEvento::Diretoria->nivelMinimo()) {
                    $q->orWhere('visibilidade', VisibilidadeEvento::Diretoria->value);
                }
            }
        });
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(CategoriaEvento::class, 'categoria_evento_id');
    }

    public function departamentos(): BelongsToMany
    {
        return $this->belongsToMany(Departamento::class, 'departamento_evento', 'evento_id', 'departamento_id');
    }

    /** data_inicio: string Y-m-d na escrita (portável), Carbon na leitura. */
    protected function dataInicio(): Attribute
    {
        return Attribute::make(
            get: fn (?string $v) => $v !== null ? Carbon::parse($v) : null,
            set: fn ($v) => $v !== null ? Carbon::parse($v)->format('Y-m-d') : null,
        );
    }

    protected function dataFim(): Attribute
    {
        return Attribute::make(
            get: fn (?string $v) => $v !== null ? Carbon::parse($v) : null,
            set: fn ($v) => $v !== null ? Carbon::parse($v)->format('Y-m-d') : null,
        );
    }

    protected function horaInicio(): Attribute
    {
        return Attribute::make(set: fn (?string $v) => self::normalizaHora($v));
    }

    protected function horaFim(): Attribute
    {
        return Attribute::make(set: fn (?string $v) => self::normalizaHora($v));
    }

    protected function conteudo(): Attribute
    {
        return Attribute::make(
            set: fn (?string $v) => $v !== null ? clean($v, 'conteudo') : null,
        );
    }

    protected function resumo(): Attribute
    {
        return Attribute::make(
            set: fn (?string $v) => $v !== null ? clean($v, 'conteudo') : null,
        );
    }

    /** Período por extenso (via classe pura). Usa os valores crus Y-m-d. */
    public function getPeriodoAttribute(): string
    {
        $inicio = $this->attributes['data_inicio'] ?? null;
        if ($inicio === null) {
            return '';
        }

        return PeriodoEvento::formata($inicio, $this->hora_inicio, $this->attributes['data_fim'] ?? null, $this->hora_fim);
    }

    /** URL do flyer (WebP web) via Media Library, ou null. */
    protected function flyerUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->getFirstMediaUrl(self::COLECAO_FLYER, 'web') ?: null);
    }

    /** @return array{estado:string,rotulo:string,cor:string,cor_texto:string} */
    public function getStatusSeloAttribute(): array
    {
        return StatusEvento::para($this->attributes['data_inicio'] ?? null, $this->attributes['data_fim'] ?? null);
    }

    public function getEhPassadoAttribute(): bool
    {
        return $this->status_selo['estado'] === 'passado';
    }

    /** Instante de início em UTC (para Google Calendar/ICS com hora); dia inteiro → 00:00. */
    public function inicioUtc(): Carbon
    {
        $data = $this->attributes['data_inicio'];
        $hora = ($this->hora_inicio ?? '') !== '' ? $this->hora_inicio : '00:00';

        return Carbon::parse("{$data} {$hora}", 'America/Sao_Paulo')->utc();
    }

    /** Instante de fim em UTC: hora_fim quando há hora; senão início + 2h. */
    public function fimUtc(): Carbon
    {
        if (($this->hora_inicio ?? '') !== '' && ($this->hora_fim ?? '') !== '') {
            $dataFim = ($this->attributes['data_fim'] ?? null) ?: $this->attributes['data_inicio'];

            return Carbon::parse("{$dataFim} {$this->hora_fim}", 'America/Sao_Paulo')->utc();
        }

        return $this->inicioUtc()->addHours(2);
    }

    /** Verdadeiro quando há hora de início definida (senão o evento é "dia inteiro"). */
    public function temHora(): bool
    {
        return ($this->hora_inicio ?? '') !== '';
    }

    /**
     * Parâmetro `dates` do Google Calendar (TEMPLATE).
     * Com hora: instantes UTC. Dia inteiro: datas Ymd com fim EXCLUSIVO (data_fim, ou início, +1 dia).
     */
    public function googleCalendarDates(): string
    {
        if ($this->temHora()) {
            return $this->inicioUtc()->format('Ymd\THis\Z').'/'.$this->fimUtc()->format('Ymd\THis\Z');
        }

        $inicio = $this->getRawOriginal('data_inicio');
        $fimExclusivo = Carbon::parse($this->getRawOriginal('data_fim') ?: $inicio)->addDay()->format('Ymd');

        return Carbon::parse($inicio)->format('Ymd').'/'.$fimExclusivo;
    }

    /**
     * startDate/endDate para o JSON-LD Event (schema.org).
     * Com hora: ISO-8601 local (com offset). Dia inteiro: datas Y-m-d, fim INCLUSIVO (último dia real).
     *
     * @return array{inicio:string,fim:string}
     */
    public function intervaloSchema(): array
    {
        if ($this->temHora()) {
            return [
                'inicio' => $this->inicioUtc()->setTimezone(StatusEvento::FUSO)->toIso8601String(),
                'fim' => $this->fimUtc()->setTimezone(StatusEvento::FUSO)->toIso8601String(),
            ];
        }

        return [
            'inicio' => $this->getRawOriginal('data_inicio'),
            'fim' => $this->getRawOriginal('data_fim') ?: $this->getRawOriginal('data_inicio'),
        ];
    }

    /** Normaliza hora para 'HH:MM' zero-padded; aceita 'H:i' ou 'H:i:s'. Inválido passa cru p/ validação acusar. */
    private static function normalizaHora(?string $v): ?string
    {
        if ($v === null || trim($v) === '') {
            return null;
        }

        if (preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', trim($v), $m)) {
            return sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
        }

        return trim($v);
    }
}
