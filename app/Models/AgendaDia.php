<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-02

namespace App\Models;

use App\Enums\RegimeAcesso;
use App\Models\Contracts\TemDepartamento;
use App\Support\Autorizacao\AcessoPorTipo;
use App\Support\Autorizacao\AuditoriaAutorizacao;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Spatie\Activitylog\Contracts\Activity;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class AgendaDia extends Model implements TemDepartamento
{
    use HasFactory, LogsActivity;

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

    /**
     * AgendaDia no escopo do usuário, por REGIME (Camada 1):
     * - "do tipo": TUDO-OU-NADA — responsável vê todos os registros (o pivô não é consultado);
     * - "por registro": o filtro de objeto de sempre (interseção de departamentos).
     * Fail-closed: recurso sem linha em tipos_conteudo ⇒ nenhum registro (I1/I2).
     */
    public function scopeNoEscopoDe(Builder $query, User $user): Builder
    {
        $acesso = app(AcessoPorTipo::class);

        return match ($acesso->regime('agenda')) {
            RegimeAcesso::DoTipo => $acesso->usuarioHabilitadoNoTipo($user, 'agenda')
                ? $query
                : $query->whereRaw('1 = 0'),
            RegimeAcesso::PorRegistro => $this->escopoPorRegistro($query, $user),
            null => $query->whereRaw('1 = 0'),
        };
    }

    /** Regime "por registro": o corpo de sempre, intacto. */
    private function escopoPorRegistro(Builder $query, User $user): Builder
    {
        $ids = $user->departamentos()->pluck('departamentos.id')->all();

        if ($ids === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('departamentos', fn (Builder $q) => $q->whereIn('departamentos.id', $ids));
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

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('agenda')
            ->logOnly(['data', 'status', 'reflexao', 'meta_mes_texto', 'meta_dia_titulo', 'meta_dia_texto', 'prece'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $evento): string => match ($evento) {
                'created' => 'dia da agenda criado',
                'updated' => 'dia da agenda atualizado',
                'deleted' => 'dia da agenda excluído',
                default => "dia da agenda {$evento}",
            });
    }

    /** IP + user-agent + porta em toda entrada automática (fonte única: o helper). */
    public function tapActivity(Activity $activity, string $eventName): void
    {
        $activity->properties = $activity->properties->merge(AuditoriaAutorizacao::contexto());
    }
}
