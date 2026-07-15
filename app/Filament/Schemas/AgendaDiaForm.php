<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-15

namespace App\Filament\Schemas;

use App\Models\AgendaDia;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;

/**
 * Fonte única dos CAMPOS do formulário de AgendaDia: rótulos, componentes e regras de campo.
 * Consumido pelo painel (AgendaDiaResource) e pelo componente do site (App\Livewire\Conta\AgendaConta).
 *
 * O campo `departamentos` é PRIVILEGIADO (§5/§7 do spec): no site ele é AUSENTE do schema
 * (comDepartamentos: false) e o servidor força o valor (DED+DECOM na criação; preservado na edição).
 * A sanitização de HTML dos textos já vive no model (mutators clean()), não aqui.
 */
class AgendaDiaForm
{
    /** @return array<Component> */
    public static function schema(bool $comDepartamentos = true): array
    {
        $campos = [
            Grid::make(2)->schema([
                DatePicker::make('data')
                    ->label('Data')
                    ->required()
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->unique(table: 'agenda_dias', column: 'data', ignoreRecord: true),
                Select::make('status')
                    ->label('Status')
                    ->required()
                    ->options([
                        AgendaDia::STATUS_PUBLICADO => 'Publicado',
                        AgendaDia::STATUS_RASCUNHO => 'Rascunho',
                    ])
                    ->default(AgendaDia::STATUS_PUBLICADO),
            ]),
            // HTML cru; a sanitização (clean $v,'conteudo') vem do mutator do model.
            RichEditor::make('reflexao')
                ->label('Reflexão e Vivência (Evangelho)')
                ->columnSpanFull(),
            RichEditor::make('meta_mes_texto')
                ->label('Meta do Mês — citação do dia')
                ->columnSpanFull(),
            TextInput::make('meta_dia_titulo')
                ->label('Meta do Dia — título')
                ->maxLength(255)
                ->columnSpanFull(),
            RichEditor::make('meta_dia_texto')
                ->label('Meta do Dia — texto')
                ->columnSpanFull(),
            RichEditor::make('prece')
                ->label('Sugestão de Prece')
                ->columnSpanFull(),
        ];

        if ($comDepartamentos) {
            $campos[] = Select::make('departamentos')
                ->label('Departamentos')
                ->relationship('departamentos', 'nome')
                ->multiple()
                ->searchable()
                ->preload()
                ->required()
                ->columnSpanFull();
        }

        return $campos;
    }
}
