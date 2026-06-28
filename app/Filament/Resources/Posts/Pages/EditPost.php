<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-26

namespace App\Filament\Resources\Posts\Pages;

use App\Filament\Resources\Posts\PostResource;
use App\Models\Post;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPost extends EditRecord
{
    protected static string $resource = PostResource::class;

    public static bool $formActionsAreSticky = true;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /** "Publicar agora": ao publicar sem data, usa o instante atual. */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (($data['status'] ?? null) === Post::STATUS_PUBLICADO && blank($data['data_publicacao'] ?? null)) {
            $data['data_publicacao'] = now();
        }

        return $data;
    }
}
