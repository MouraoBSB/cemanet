<?php

namespace App\Filament\Resources\Palestras\Pages;

use App\Filament\Resources\Palestras\PalestraResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPalestra extends EditRecord
{
    protected static string $resource = PalestraResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
