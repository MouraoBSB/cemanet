<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-02

namespace App\Models;

use App\Models\Contracts\TemDepartamento;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class AgendaDia extends Model implements TemDepartamento
{
    use HasFactory;

    public const STATUS_PUBLICADO = 'publicado';

    public const STATUS_RASCUNHO = 'rascunho';

    protected $table = 'agenda_dias';

    protected $fillable = [
        'data',
        'reflexao',
        'meta_mes_texto',
        'meta_dia_titulo',
        'meta_dia_texto',
        'prece',
        'status',
        'wp_id',
    ];

    /** Cache por instância da Meta do Mês resolvida (evita nova query a cada acesso). */
    private ?AgendaMetaMes $metaMesCache = null;

    private bool $metaMesResolvida = false;

    public function scopePublicado(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLICADO);
    }

    public function departamentos(): BelongsToMany
    {
        return $this->belongsToMany(Departamento::class, 'departamento_agenda_dia', 'agenda_dia_id', 'departamento_id');
    }

    /**
     * Normaliza `data` para string Y-m-d na escrita (robusto a string ou
     * Carbon) e devolve Carbon na leitura. Mesmo molde de
     * `AgendaSlugLegado::data()`: o cast nativo 'date:Y-m-d' diverge entre
     * SQLite (testes) e MySQL (prod) quando o atributo é atribuído como Carbon.
     */
    protected function data(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value !== null ? Carbon::parse($value) : null,
            set: fn ($value) => $value !== null ? Carbon::parse($value)->format('Y-m-d') : null,
        );
    }

    protected function reflexao(): Attribute
    {
        return Attribute::make(
            set: fn (?string $v) => $v !== null ? clean($v, 'conteudo') : null,
        );
    }

    protected function metaMesTexto(): Attribute
    {
        return Attribute::make(
            set: fn (?string $v) => $v !== null ? clean($v, 'conteudo') : null,
        );
    }

    protected function metaDiaTexto(): Attribute
    {
        return Attribute::make(
            set: fn (?string $v) => $v !== null ? clean($v, 'conteudo') : null,
        );
    }

    protected function prece(): Attribute
    {
        return Attribute::make(
            set: fn (?string $v) => $v !== null ? clean($v, 'conteudo') : null,
        );
    }

    /** Resolve o tema fixo do mês (ano+mes da data), memoizado por instância. */
    public function metaMes(): ?AgendaMetaMes
    {
        if (! $this->metaMesResolvida) {
            $this->metaMesCache = $this->data
                ? AgendaMetaMes::query()
                    ->where('ano', $this->data->year)
                    ->where('mes', $this->data->month)
                    ->first()
                : null;
            $this->metaMesResolvida = true;
        }

        return $this->metaMesCache;
    }

    /** Data por extenso em pt-BR, capitalizada (ex.: "Segunda-feira, 15 de junho de 2026"). */
    public function tituloExtenso(): string
    {
        return Str::ucfirst($this->data->translatedFormat('l, d \d\e F \d\e Y'));
    }

    /** Trecho da reflexão sem HTML para <meta description>, limitado a 155 caracteres. */
    public function descricaoSeo(): string
    {
        return Str::limit(trim(strip_tags((string) $this->reflexao)), 155);
    }
}
