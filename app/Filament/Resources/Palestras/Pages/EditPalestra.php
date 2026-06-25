<?php

namespace App\Filament\Resources\Palestras\Pages;

use App\Filament\Resources\Palestras\PalestraResource;
use App\Models\Palestra;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPalestra extends EditRecord
{
    use SincronizaPessoas;

    protected static string $resource = PalestraResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['ids_palestrantes'] = $this->record->palestrantes()
            ->wherePivot('papel', Palestra::PAPEL_PALESTRANTE)
            ->pluck('palestrantes.id')->all();

        $data['id_diretor'] = $this->record->palestrantes()
            ->wherePivot('papel', Palestra::PAPEL_DIRETOR)
            ->value('palestrantes.id');

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->capturarPessoas($data);
    }

    protected function afterSave(): void
    {
        $this->sincronizarPessoas($this->record);
    }
}
