<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-08

namespace App\Filament\Resources\Eventos;

use App\Enums\VisibilidadeEvento;
use App\Filament\Resources\Eventos\Pages\CreateEvento;
use App\Filament\Resources\Eventos\Pages\EditEvento;
use App\Filament\Resources\Eventos\Pages\ListEventos;
use App\Filament\Support\ComponentesImagem;
use App\Models\Evento;
use App\Support\Eventos\PeriodoEvento;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class EventoResource extends Resource
{
    protected static ?string $model = Evento::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $modelLabel = 'Evento';

    protected static ?string $pluralModelLabel = 'Eventos';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('Evento')->columnSpanFull()->tabs([
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
                            ->unique(table: 'eventos', column: 'slug', ignoreRecord: true),
                    ]),
                    Textarea::make('resumo')
                        ->label('Resumo (chamada / SEO)')
                        ->rows(3)
                        ->columnSpanFull(),
                    RichEditor::make('conteudo')
                        ->label('Conteúdo')
                        ->columnSpanFull(),
                    ComponentesImagem::upload('flyer', Evento::COLECAO_FLYER)
                        ->label('Flyer (capa)'),
                    ComponentesImagem::upload('galeria', Evento::COLECAO_GALERIA, multiplas: true)
                        ->label('Galeria de imagens'),
                ]),
                Tabs\Tab::make('Data & Local')->schema([
                    Grid::make(2)->schema([
                        DatePicker::make('data_inicio')
                            ->label('Data de início')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->required(),
                        TimePicker::make('hora_inicio')
                            ->label('Hora de início (deixe vazio para "dia inteiro")')
                            ->seconds(false),
                        DatePicker::make('data_fim')
                            ->label('Data de término (opcional)')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->afterOrEqual('data_inicio'),
                        TimePicker::make('hora_fim')
                            ->label('Hora de término (opcional)')
                            ->seconds(false)
                            ->rules([
                                fn (Get $get): \Closure => function (string $attribute, $value, \Closure $fail) use ($get) {
                                    if (PeriodoEvento::horaFimAntesNoMesmoDia($get('data_inicio'), $get('hora_inicio'), $get('data_fim'), $value)) {
                                        $fail('No mesmo dia, a hora de término deve ser posterior à de início.');
                                    }
                                },
                            ]),
                    ]),
                    TextInput::make('local')
                        ->label('Local')
                        ->maxLength(255),
                ]),
                Tabs\Tab::make('Classificação')->schema([
                    Select::make('categoria_evento_id')
                        ->label('Categoria')
                        ->relationship('categoria', 'nome')
                        ->searchable()
                        ->preload(),
                    Select::make('departamentos')
                        ->label('Departamentos organizadores')
                        ->relationship('departamentos', 'nome')
                        ->multiple()
                        ->searchable()
                        ->preload(),
                ]),
                Tabs\Tab::make('Publicação')->schema([
                    Grid::make(2)->schema([
                        Select::make('status')
                            ->label('Status')
                            ->required()
                            ->options([
                                Evento::STATUS_PUBLICADO => 'Publicado',
                                Evento::STATUS_RASCUNHO => 'Rascunho',
                            ])
                            ->default(Evento::STATUS_RASCUNHO),
                        Select::make('visibilidade')
                            ->label('Visibilidade')
                            ->required()
                            ->options(VisibilidadeEvento::opcoes())
                            ->default(VisibilidadeEvento::Publico->value),
                    ]),
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
                TextColumn::make('periodo')
                    ->label('Período'),
                TextColumn::make('categoria.nome')
                    ->label('Categoria')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state) => $state === Evento::STATUS_PUBLICADO ? 'success' : 'gray'),
                TextColumn::make('visibilidade')
                    ->label('Visibilidade')
                    ->badge()
                    ->formatStateUsing(fn (VisibilidadeEvento $state) => $state->rotulo()),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        Evento::STATUS_PUBLICADO => 'Publicado',
                        Evento::STATUS_RASCUNHO => 'Rascunho',
                    ]),
                SelectFilter::make('categoria_evento_id')
                    ->label('Categoria')
                    ->relationship('categoria', 'nome'),
                SelectFilter::make('visibilidade')
                    ->options(VisibilidadeEvento::opcoes()),
            ])
            ->defaultSort('data_inicio', 'desc')
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
            'index' => ListEventos::route('/'),
            'create' => CreateEvento::route('/create'),
            'edit' => EditEvento::route('/{record}/edit'),
        ];
    }
}
