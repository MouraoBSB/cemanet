<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-28

namespace App\Filament\RichContent\Actions;

use App\Filament\Forms\Components\SeletorMidiaBiblioteca;
use App\Models\Biblioteca;
use App\Support\Biblioteca\RegistraMidiaBiblioteca;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\RichEditor\EditorCommand;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class InserirDaBibliotecaAction
{
    public static function make(): Action
    {
        return Action::make('inserirDaBiblioteca')
            ->label('Inserir da biblioteca')
            ->modalHeading('Inserir imagem')
            ->modalSubmitActionLabel('Inserir')
            ->schema([
                Tabs::make('origem')->tabs([
                    Tabs\Tab::make('Escolher da biblioteca')->schema([
                        SeletorMidiaBiblioteca::make('midia_id')
                            ->label('Escolha uma imagem')
                            ->required(fn (Get $get): bool => blank($get('arquivo'))),
                    ]),
                    Tabs\Tab::make('Subir nova')->schema([
                        FileUpload::make('arquivo')
                            ->label('Imagem')
                            ->image()
                            ->disk('local')
                            ->directory('biblioteca-tmp')
                            ->imageResizeMode('contain')
                            ->imageResizeTargetWidth('2000')
                            ->imageResizeUpscale(false)
                            ->required(fn (Get $get): bool => blank($get('midia_id'))),
                        TextInput::make('legenda')
                            ->label('Legenda (opcional)')
                            ->maxLength(255),
                    ]),
                ]),
                TextInput::make('alt')
                    ->label('Texto alternativo (alt)')
                    ->helperText('Descreve a imagem (A11y/SEO). Em branco, usa o alt guardado na mídia.')
                    ->maxLength(255),
            ])
            ->action(function (array $arguments, array $data, RichEditor $component): void {
                // Modo "subir nova": registra na biblioteca (cap + dedup) e usa a mídia resultante.
                if (filled($data['arquivo'] ?? null)) {
                    $caminho = Storage::disk('local')->path($data['arquivo']);
                    // finally: remove o temporário mesmo se o registro lançar (sem órfãos).
                    try {
                        $media = app(RegistraMidiaBiblioteca::class)->aPartirDoCaminho(
                            $caminho,
                            basename($data['arquivo']),
                            ['alt' => $data['alt'] ?? null, 'legenda' => $data['legenda'] ?? null],
                        );
                    } finally {
                        Storage::disk('local')->delete($data['arquivo']);
                    }
                } else {
                    // Modo "escolher": mídia já existente.
                    $media = Media::query()
                        ->where('collection_name', Biblioteca::COLECAO)
                        ->findOrFail($data['midia_id']);
                }

                $alt = filled($data['alt'] ?? null)
                    ? $data['alt']
                    : ($media->getCustomProperty('alt') ?: $media->name);

                $component->runCommands(
                    [EditorCommand::make('insertContent', [[
                        'type' => 'image',
                        'attrs' => [
                            // URL RELATIVA (sem domínio) → portável p/ troca de domínio/CDN (constraint #13).
                            'src' => route('midia.serve', [$media->id, 'web'], false),
                            'alt' => $alt,
                            'id' => null,
                        ],
                    ]])],
                    editorSelection: $arguments['editorSelection'] ?? null,
                );
            });
    }
}
