<?php

namespace App\Filament\Resources\Setors\Pages;

use App\Filament\Resources\Setors\SetorResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSetor extends EditRecord
{
    protected static string $resource = SetorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
