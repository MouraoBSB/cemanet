<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-18

namespace App\Models;

use App\Enums\FormatoMensagem;
use App\Enums\VisibilidadeMensagem;
use App\Models\Concerns\RegistraImagensPadrao;
use App\Models\Contracts\TemDepartamento;
use App\Support\Palestras\LinkDrive;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Mensagem extends Model implements HasMedia, TemDepartamento
{
    use HasFactory, InteractsWithMedia, RegistraImagensPadrao;

    // Pluralização pt-BR: o pluralizador do Laravel geraria 'mensagems'.
    protected $table = 'mensagens';

    public const STATUS_PUBLICADO = 'publicado';

    public const STATUS_PENDENTE = 'pendente';

    public const STATUS_DESPUBLICADA = 'despublicada';

    // Slug do termo "Público" da taxonomia nivel-de-acesso (nível BRUTO — a semântica rica é da Fatia 3).
    public const NIVEL_PUBLICO = 'publico';

    public const COLECAO_PICTOGRAFIA = 'pictografia';

    protected $fillable = [
        'titulo',
        'slug',
        'corpo',   // saneado pelo mutator corpo()
        'contexto', // texto puro (manual, não-IA); exibido escapado no front
        'formato',
        'data_recebimento',
        'casa',
        'link_arquivo',
        'liberar_download',
        'nivel',
        'status',
        'wp_id',
    ];

    protected function casts(): array
    {
        return [
            'formato' => FormatoMensagem::class,
            'liberar_download' => 'boolean',
        ];
    }

    /** Só as Públicas publicadas — filtro FIXO (nunca um scope de visibilidade por papel; isso é Fatia 3). */
    public function scopePublica(Builder $query): Builder
    {
        return $query
            ->where('status', self::STATUS_PUBLICADO)
            ->where('nivel', self::NIVEL_PUBLICO);
    }

    /**
     * Visibilidade tipada derivada do slug BRUTO em `nivel` (não é cast — `->nivel` segue string,
     * preservando a suíte 2A). `tryFrom` devolve null para null E para slug desconhecido ⇒ fail-closed.
     */
    public function visibilidade(): ?VisibilidadeMensagem
    {
        return $this->nivel !== null ? VisibilidadeMensagem::tryFrom($this->nivel) : null;
    }

    /** Bypass total de visibilidade: admin (papel nível 100) OU presidente (cargo). */
    private static function veTudo(?User $usuario): bool
    {
        return $usuario !== null && ($usuario->hasRole('administrador') || $usuario->ehPresidente());
    }

    /**
     * Regra de visibilidade por papel + pertencimento (fonte única). Escada (Público/Trabalhadores/
     * Diretores) + 3 recortes (Médiuns/Diretor-DEPAE/Direcionada). null = fail-closed; admin/presidente = bypass.
     */
    public function podeSerVistoPor(?User $usuario): bool
    {
        if (self::veTudo($usuario)) {
            return true;
        }

        $visibilidade = $this->visibilidade();
        if ($visibilidade === null) {
            return false; // nível null/desconhecido = fail-closed
        }

        $nivel = $usuario?->nivelMaximo() ?? 0;

        return match ($visibilidade) {
            VisibilidadeMensagem::Publico => true,
            VisibilidadeMensagem::Trabalhadores => $nivel >= VisibilidadeMensagem::Trabalhadores->nivelMinimo(),
            VisibilidadeMensagem::Diretores => $nivel >= VisibilidadeMensagem::Diretores->nivelMinimo(),
            VisibilidadeMensagem::Mediuns => $usuario !== null && $usuario->ehMedium(),
            VisibilidadeMensagem::DiretorDepae => $usuario !== null && $usuario->ehDiretorDepae(),
            VisibilidadeMensagem::Direcionada => $usuario !== null
                && $this->destinatarios()->whereKey($usuario->id)->exists(),
        };
    }

    /** Filtra no banco as mensagens que o usuário (ou anônimo) pode ver — não vaza título restrito. */
    public function scopeVisiveisPara(Builder $query, ?User $usuario): Builder
    {
        if (self::veTudo($usuario)) {
            return $query; // bypass: sem filtro (vê tudo, inclusive nível null)
        }

        $nivel = $usuario?->nivelMaximo() ?? 0;

        return $query->where(function (Builder $q) use ($usuario, $nivel) {
            $q->where('nivel', VisibilidadeMensagem::Publico->value); // sempre

            if ($usuario !== null) {
                if ($nivel >= VisibilidadeMensagem::Trabalhadores->nivelMinimo()) {
                    $q->orWhere('nivel', VisibilidadeMensagem::Trabalhadores->value);
                }
                if ($nivel >= VisibilidadeMensagem::Diretores->nivelMinimo()) {
                    $q->orWhere('nivel', VisibilidadeMensagem::Diretores->value);
                }
                if ($usuario->ehMedium()) {
                    $q->orWhere('nivel', VisibilidadeMensagem::Mediuns->value);
                }
                if ($usuario->ehDiretorDepae()) {
                    $q->orWhere('nivel', VisibilidadeMensagem::DiretorDepae->value);
                }
                // Direcionada: só as mensagens em que ESTE usuário é destinatário (não vaza as dos outros).
                $q->orWhere(fn (Builder $d) => $d
                    ->where('nivel', VisibilidadeMensagem::Direcionada->value)
                    ->whereHas('destinatarios', fn (Builder $u) => $u->whereKey($usuario->id)));
            }
        });
    }

    public function departamentos(): BelongsToMany
    {
        return $this->belongsToMany(Departamento::class, 'departamento_mensagem', 'mensagem_id', 'departamento_id');
    }

    public function autores(): BelongsToMany
    {
        return $this->belongsToMany(AutorEspiritual::class, 'mensagem_autor_espiritual', 'mensagem_id', 'autor_espiritual_id');
    }

    public function relacionadas(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'mensagem_relacionada', 'mensagem_id', 'relacionada_id');
    }

    /** Destinatários de uma mensagem DIRECIONADA (N:N, PII). Só o resolvedor de visibilidade o lê. */
    public function destinatarios(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'mensagem_destinatario', 'mensagem_id', 'user_id');
    }

    /**
     * Sincroniza as mensagens relacionadas de forma SIMÉTRICA (A↔B): grava os dois sentidos
     * numa transação e nunca cria auto-relação. Substitui completamente o conjunto de vínculos
     * desta mensagem. O Select do /admin chama isto (fora do auto-sync do Filament — I15/O3).
     *
     * @param  array<int, int|string>  $ids
     */
    public function sincronizarRelacionadas(array $ids): void
    {
        $ids = collect($ids)
            ->map(fn ($id) => (int) $id)
            ->reject(fn (int $id) => $id === (int) $this->id || $id === 0)
            ->unique()
            ->values();

        DB::transaction(function () use ($ids) {
            // remove os DOIS sentidos que envolvem esta mensagem
            DB::table('mensagem_relacionada')
                ->where('mensagem_id', $this->id)
                ->orWhere('relacionada_id', $this->id)
                ->delete();

            foreach ($ids as $id) {
                DB::table('mensagem_relacionada')->insert([
                    ['mensagem_id' => $this->id, 'relacionada_id' => $id],
                    ['mensagem_id' => $id, 'relacionada_id' => $this->id],
                ]);
            }
        });
    }

    public function registerMediaCollections(): void
    {
        // Pictografia: MÚLTIPLAS imagens (o legado tem mensagem com 2). WebP web + miniatura pelo trait.
        $this->registrarColecaoImagem(self::COLECAO_PICTOGRAFIA, unica: false);
    }

    protected function corpo(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => $value !== null ? clean($value, 'conteudo') : null,
        );
    }

    /**
     * `data_recebimento` como coluna `date` portável (Carbon↔string Y-m-d): o cast nativo `date`
     * diverge entre SQLite (testes) e MySQL (prod). Mesmo molde de AgendaDia::data().
     */
    protected function dataRecebimento(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value !== null ? Carbon::parse($value) : null,
            set: fn ($value) => $value !== null ? Carbon::parse($value)->format('Y-m-d') : null,
        );
    }

    /**
     * Normaliza o link para download direto (Drive `uc?export=download&id=...`) no SET — vale
     * tanto para a importação quanto para um link colado no /admin (R-A). Não-Drive fica intacto.
     */
    protected function linkArquivo(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => LinkDrive::paraDownload($value),
        );
    }
}
