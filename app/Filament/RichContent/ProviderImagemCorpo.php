<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-27

namespace App\Filament\RichContent;

use Filament\Forms\Components\RichEditor\FileAttachmentProviders\SpatieMediaLibraryFileAttachmentProvider;
use Throwable;

/**
 * Provider de anexos do corpo do RichEditor que serve a conversão WebP 'web'
 * (otimizada) no lugar do original — com FALLBACK ao original capado enquanto a
 * conversão ainda não existir. Preserva o comportamento de visibilidade do
 * provider base (URL temporária quando o anexo é privado).
 */
class ProviderImagemCorpo extends SpatieMediaLibraryFileAttachmentProvider
{
    public function getFileAttachmentUrl(mixed $file): ?string
    {
        $media = $this->getMedia();

        if (! $media || ! $media->has($file)) {
            return null;
        }

        $anexo = $media->get($file);

        // Serve a WebP otimizada quando já gerada; senão, cai no original capado.
        $conversao = $anexo->hasGeneratedConversion('web') ? 'web' : '';

        if ($this->attribute->getFileAttachmentsVisibility() === 'private') {
            try {
                return $anexo->getTemporaryUrl(
                    now()->addMinutes(config('filament.temporary_file_url_expiry_minutes', 30))->endOfHour(),
                    $conversao,
                );
            } catch (Throwable $e) {
                // Driver sem suporte a URL temporária — segue para a URL pública.
            }
        }

        return $anexo->getUrl($conversao);
    }
}
