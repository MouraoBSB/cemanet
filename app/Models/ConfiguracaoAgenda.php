<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-02

namespace App\Models;

use App\Models\Concerns\RegistraImagensPadrao;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Configurações da Agenda. Singleton: existe um único registro, dono da coleção
 * 'capa' (imagem de capa do livro da Agenda), otimizada via Media Library (mesmo
 * tratamento padrão do blog/palestrantes: WebP + miniatura, síncronas).
 */
class ConfiguracaoAgenda extends Model implements HasMedia
{
    use InteractsWithMedia, RegistraImagensPadrao;

    protected $table = 'agenda_configuracoes';

    public const COLECAO_CAPA = 'capa';

    protected $guarded = [];

    /** Recupera (ou cria) o único registro de configurações da Agenda. */
    public static function instance(): self
    {
        return static::firstOrCreate([]);
    }

    public function registerMediaCollections(): void
    {
        $this->registrarColecaoImagem(self::COLECAO_CAPA, unica: true, larguraWeb: 1200);
    }

    /** URL da capa (WebP web) via Media Library, ou null. */
    protected function capaUrl(): Attribute
    {
        return Attribute::get(
            fn (): ?string => $this->getFirstMediaUrl(self::COLECAO_CAPA, 'web') ?: null,
        );
    }
}
