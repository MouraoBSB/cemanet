<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-10

namespace App\Filament\Schemas;

use App\Enums\VisibilidadeEvento;
use App\Filament\Support\ComponentesImagem;
use App\Models\Evento;
use App\Support\Eventos\PeriodoEvento;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Str;

/**
 * Fonte única dos CAMPOS do formulário de Evento: rótulos, componentes, uploads e as
 * regras de campo (required, unique, afterOrEqual, e hora de término posterior à de
 * início no mesmo dia). Consumido pelo painel (EventoResource) e destinado a
 * formulários de Evento embutidos fora do /admin.
 *
 * A rede server-side de período NÃO está aqui. PeriodoEvento::erros() cobre dois casos
 * que as regras de campo não pegam — hora de término sem hora de início, e hora fora
 * de HH:MM. No painel quem a aplica é o trait ValidaPeriodoEvento. Todo consumidor novo
 * precisa aplicá-la: getState() sozinho não basta.
 */
class EventoForm
{
    /** @return array<Component> */
    public static function schema(): array
    {
        return [
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
        ];
    }
}
