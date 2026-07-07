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

    // Valor padrão em memória logo após create(): sem isso, o atributo fica null até um
    // fresh()/refresh(), pois a coluna não é fillable e o INSERT delega o default ao banco.
    protected $attributes = [
        'foto_definida_pelo_membro' => false,
    ];

    protected function casts(): array
    {
        return [
            'whatsapp_publico' => 'boolean',
            'data_nascimento' => 'date',
            'foto_definida_pelo_membro' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Auto-população (migração/Google) só age se o membro não definiu a foto e não há foto ainda. */
    public function podeAutoPopularFoto(): bool
    {
        return ! $this->foto_definida_pelo_membro && ! $this->hasMedia(self::COLECAO_FOTO);
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
