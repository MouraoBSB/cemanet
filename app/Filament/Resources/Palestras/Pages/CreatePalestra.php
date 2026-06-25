<?php

namespace App\Filament\Resources\Palestras\Pages;

use App\Filament\Resources\Palestras\PalestraResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePalestra extends CreateRecord
{
    use SincronizaPessoas;

    protected static string $resource = PalestraResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->capturarPessoas($data);
    }

    protected function afterCreate(): void
    {
        $this->sincronizarPessoas($this->record);
    }
}
