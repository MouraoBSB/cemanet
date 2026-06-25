<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-25

namespace App\Filament\Resources\Palestras;

use App\Filament\Resources\Palestras\Pages\CreatePalestra;
use App\Filament\Resources\Palestras\Pages\EditPalestra;
use App\Filament\Resources\Palestras\Pages\ListPalestras;
use App\Models\Palestra;
use App\Models\Palestrante;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class PalestraResource extends Resource
{
    protected static ?string $model = Palestra::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMicrophone;

    protected static ?string $modelLabel = 'Palestra';

    protected static ?string $pluralModelLabel = 'Palestras';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('Palestra')->columnSpanFull()->tabs([
                Tabs\Tab::make('Conteúdo')->schema([
                    Grid::make(2)->schema([
                        TextInput::make('titulo')
                            ->label('Título')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (string $operation, ?string $state, callable $set) {
                                if ($operation === 'create') {
                                    $set('slug', Str::slug($state ?? ''));
                                }
                            }),
                        TextInput::make('slug')
                            ->label('Slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(table: 'palestras', column: 'slug', ignoreRecord: true),
                    ]),
                    TextInput::make('subtitulo')
                        ->label('Subtítulo')
                        ->maxLength(255),
                    Textarea::make('resumo')
                        ->label('Resumo')
                        ->rows(3)
                        ->columnSpanFull(),
                    RichEditor::make('descricao')
                        ->label('Descrição')
                        ->columnSpanFull(),
                ]),
                Tabs\Tab::make('Pessoas')->schema([
                    Select::make('ids_palestrantes')
                        ->label('Palestrantes (1 a 2, obrigatório)')
                        ->options(fn () => Palestrante::ativo()->orderBy('nome')->pluck('nome', 'id'))
                        ->multiple()
                        ->searchable()
                        ->maxItems(2)
                        ->dehydrated(false),
                    Select::make('id_diretor')
                        ->label('Diretor (opcional)')
                        ->options(fn () => Palestrante::ativo()->orderBy('nome')->pluck('nome', 'id'))
                        ->searchable()
                        ->dehydrated(false),
                ]),
                Tabs\Tab::make('Dados')->schema([
                    Grid::make(2)->schema([
                        DateTimePicker::make('data_da_palestra')
                            ->label('Data e hora')
                            ->seconds(false),
                        Select::make('status')
                            ->label('Status')
                            ->required()
                            ->options([
                                Palestra::STATUS_PUBLICADO => 'Publicado',
                                Palestra::STATUS_RASCUNHO => 'Rascunho',
                            ])
                            ->default(Palestra::STATUS_RASCUNHO),
                    ]),
                    Grid::make(2)->schema([
                        Toggle::make('online')
                            ->label('Disponível online'),
                        TextInput::make('link_youtube')
                            ->label('Link do YouTube')
                            ->url()
                            ->maxLength(500),
                    ]),
                    Grid::make(3)->schema([
                        TextInput::make('publico_presencial')
                            ->label('Público presencial')
                            ->numeric(),
                        TextInput::make('publico_online')
                            ->label('Público online')
                            ->numeric(),
                        TextInput::make('publico_total')
                            ->label('Público total')
                            ->numeric(),
                    ]),
                    ColorPicker::make('cor_fundo')
                        ->label('Cor de fundo (hero)')
                        ->rules(['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/']),
                ]),
                Tabs\Tab::make('Assuntos e destaques')->schema([
                    Select::make('assuntos')
                        ->label('Assuntos')
                        ->relationship('assuntos', 'nome')
                        ->multiple()
                        ->searchable()
                        ->preload(),
                    Repeater::make('destaques')
                        ->label('Destaques')
                        ->relationship('destaques')
                        ->schema([
                            TextInput::make('destaque')
                                ->label('Título')
                                ->required()
                                ->maxLength(255),
                            Textarea::make('texto')
                                ->label('Texto')
                                ->rows(2),
                        ])
                        ->orderColumn('ordem')
                        ->collapsible()
                        ->defaultItems(0)
                        ->addActionLabel('Adicionar destaque')
                        ->columnSpanFull(),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('titulo')
                    ->label('Título')
                    ->searchable()
                    ->sortable()
                    ->limit(60),
                TextColumn::make('data_da_palestra')
                    ->label('Data')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state) => $state === Palestra::STATUS_PUBLICADO ? 'success' : 'gray'),
                IconColumn::make('online')
                    ->label('Online')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        Palestra::STATUS_PUBLICADO => 'Publicado',
                        Palestra::STATUS_RASCUNHO => 'Rascunho',
                    ]),
            ])
            ->defaultSort('data_da_palestra', 'desc')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPalestras::route('/'),
            'create' => CreatePalestra::route('/create'),
            'edit' => EditPalestra::route('/{record}/edit'),
        ];
    }
}
