<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-28

namespace App\Filament\RichContent\Actions;

use App\Filament\Forms\Components\SeletorMidiaBiblioteca;
use App\Models\Biblioteca;
use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\RichEditor\EditorCommand;
use Filament\Forms\Components\TextInput;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class InserirDaBibliotecaAction
{
    public static function make(): Action
    {
        return Action::make('inserirDaBiblioteca')
            ->label('Inserir da biblioteca')
            ->modalHeading('Inserir imagem da biblioteca')
            ->modalSubmitActionLabel('Inserir')
            ->schema([
                SeletorMidiaBiblioteca::make('midia_id')
                    ->label('Escolha uma imagem')
                    ->required(),
                TextInput::make('alt')
                    ->label('Texto alternativo (alt)')
                    ->helperText('Descreve a imagem (A11y/SEO). Em branco, usa o alt guardado na mídia.')
                    ->maxLength(255),
            ])
            ->action(function (array $arguments, array $data, RichEditor $component): void {
                $media = Media::query()
                    ->where('collection_name', Biblioteca::COLECAO)
                    ->findOrFail($data['midia_id']);

                $alt = filled($data['alt'] ?? null)
                    ? $data['alt']
                    : ($media->getCustomProperty('alt') ?: $media->name);

                $component->runCommands(
                    [EditorCommand::make('insertContent', [[
                        'type'  => 'image',
                        'attrs' => [
                            'src' => route('midia.serve', [$media->id, 'web']),
                            'alt' => $alt,
                            'id'  => null,
                        ],
                    ]])],
                    editorSelection: $arguments['editorSelection'] ?? null,
                );
            });
    }
}
