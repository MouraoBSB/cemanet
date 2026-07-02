<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-02

namespace App\Filament\Resources\Agenda;

use App\Filament\Resources\Agenda\Pages\CreateAgendaMetaMes;
use App\Filament\Resources\Agenda\Pages\EditAgendaMetaMes;
use App\Filament\Resources\Agenda\Pages\ListAgendaMetasMes;
use App\Models\AgendaMetaMes;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class AgendaMetaMesResource extends Resource
{
    protected static ?string $model = AgendaMetaMes::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $modelLabel = 'Tema do mês';

    protected static ?string $pluralModelLabel = 'Temas do mês';

    protected static ?string $recordTitleAttribute = 'titulo';

    // Rótulos pt-BR dos meses; reaproveitado no Select e na coluna da tabela.
    protected static function meses(): array
    {
        return [
            1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
            5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
            9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro',
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(2)->schema([
                TextInput::make('ano')
                    ->label('Ano')
                    ->required()
                    ->numeric()
                    ->minValue(2000)
                    ->maxValue(2100),
                Select::make('mes')
                    ->label('Mês')
                    ->required()
                    ->options(self::meses())
                    // Unicidade composta (ano, mes): a regra roda na coluna 'mes'
                    // restrita ao 'ano' informado; ignora o próprio registro na edição.
                    ->rules([
                        fn (Get $get, ?Model $record) => Rule::unique('agenda_metas_mes', 'mes')
                            ->where('ano', $get('ano'))
                            ->ignore($record),
                    ])
                    ->validationMessages([
                        'unique' => 'Já existe um tema cadastrado para este mês e ano.',
                    ]),
            ]),
            TextInput::make('titulo')
                ->label('Título')
                ->required()
                ->maxLength(255)
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('ano')
                    ->label('Ano')
                    ->sortable(),
                TextColumn::make('mes')
                    ->label('Mês')
                    ->formatStateUsing(fn (int $state) => self::meses()[$state] ?? $state)
                    ->sortable(),
                TextColumn::make('titulo')
                    ->label('Título')
                    ->searchable()
                    ->limit(60),
            ])
            // defaultSort não faz ordenação composta; garantimos ano desc, mês desc na query.
            ->modifyQueryUsing(fn (Builder $query) => $query->orderByDesc('ano')->orderByDesc('mes'))
            ->filters([])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAgendaMetasMes::route('/'),
            'create' => CreateAgendaMetaMes::route('/create'),
            'edit' => EditAgendaMetaMes::route('/{record}/edit'),
        ];
    }
}
