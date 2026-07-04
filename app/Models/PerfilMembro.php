<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-03

namespace App\Models;

use App\Models\Concerns\RegistraImagensPadrao;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class PerfilMembro extends Model implements HasMedia
{
    use InteractsWithMedia, RegistraImagensPadrao;

    public const COLECAO_FOTO = 'foto';

    protected $table = 'perfis_membro';

    protected $fillable = [
        'user_id', 'whatsapp', 'whatsapp_publico', 'data_nascimento', 'endereco',
    ];

    protected function casts(): array
    {
        return ['whatsapp_publico' => 'boolean', 'data_nascimento' => 'date'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function registerMediaCollections(): void
    {
        // Avatar do membro: WebP web ≤640px + thumb quadrado 400×400 (o avatar nunca aparece em 1600px).
        $this->registrarColecaoImagem(self::COLECAO_FOTO, larguraWeb: 640);
    }

    protected function fotoUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->getFirstMediaUrl(self::COLECAO_FOTO, 'web') ?: null);
    }

    protected function fotoThumbUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->getFirstMediaUrl(self::COLECAO_FOTO, 'thumb') ?: null);
    }
}
