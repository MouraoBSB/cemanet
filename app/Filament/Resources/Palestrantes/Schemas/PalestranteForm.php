<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-25

namespace App\Filament\Resources\Palestrantes\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PalestranteForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Dados pessoais')
                    ->columns(2)
                    ->schema([
                        TextInput::make('nome')
                            ->label('Nome')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(2),

                        TextInput::make('slug')
                            ->label('Slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),

                        TextInput::make('email')
                            ->label('E-mail')
                            ->email()
                            ->maxLength(255),
                    ]),

                Section::make('Foto')
                    ->schema([
                        FileUpload::make('foto')
                            ->label('Foto do palestrante')
                            ->image()
                            ->imageEditor()
                            ->disk('public')
                            ->directory('palestrantes/fotos')
                            ->maxSize(2048),
                    ]),

                Section::make('Biografia')
                    ->schema([
                        RichEditor::make('bio')
                            ->label('Biografia')
                            ->toolbarButtons([
                                'bold',
                                'italic',
                                'underline',
                                'strike',
                                'link',
                                'bulletList',
                                'orderedList',
                                'blockquote',
                                'h2',
                                'h3',
                            ])
                            ->columnSpanFull(),
                    ]),

                Section::make('Contato e exibição')
                    ->columns(2)
                    ->schema([
                        TextInput::make('telefone')
                            ->label('Telefone')
                            ->tel()
                            ->maxLength(20),

                        Toggle::make('mostrar_email')
                            ->label('Exibir e-mail no site'),

                        Toggle::make('mostrar_telefone')
                            ->label('Exibir telefone no site'),

                        Toggle::make('ativo')
                            ->label('Palestrante ativo')
                            ->default(true),
                    ]),
            ]);
    }
}
