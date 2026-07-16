<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-03

namespace App\Filament\Resources\Departamentos;

use App\Filament\Resources\Departamentos\Pages\CreateDepartamento;
use App\Filament\Resources\Departamentos\Pages\EditDepartamento;
use App\Filament\Resources\Departamentos\Pages\ListDepartamentos;
use App\Models\Departamento;
use App\Support\Autorizacao\GlossarioCapacidades;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class DepartamentoResource extends Resource
{
    protected static ?string $model = Departamento::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected static ?string $navigationLabel = 'Departamentos';

    protected static ?string $modelLabel = 'Departamento';

    protected static ?string $pluralModelLabel = 'Departamentos';

    protected static ?string $recordTitleAttribute = 'nome';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Dados do departamento')
                    ->columns(2)
                    ->schema([
                        TextInput::make('sigla')
                            ->label('Sigla')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),

                        TextInput::make('nome')
                            ->label('Nome')
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
                            ->unique(ignoreRecord: true),

                        TextInput::make('ordem')
                            ->label('Ordem')
                            ->numeric()
                            ->default(0)
                            ->required(),

                        Textarea::make('descricao')
                            ->label('Descrição')
                            ->columnSpanFull(),

                        ColorPicker::make('cor')
                            ->label('Cor')
                            ->rules(['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/']),

                        TextInput::make('icone')
                            ->label('Ícone (opcional)')
                            ->maxLength(255),

                        Toggle::make('ativo')
                            ->label('Ativo')
                            ->default(true),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sigla')
                    ->label('Sigla')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('nome')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('ativo')
                    ->label('Ativo')
                    ->boolean(),

                TextColumn::make('ordem')
                    ->label('Ordem')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Atualizado em')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('ordem')
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make()->label('Editar'),
                DeleteAction::make()
                    ->label('Excluir')
                    // A FK de departamento_tipo_conteudo é restrictOnDelete: sem este guarda o
                    // DELETE estoura como 500. Notification NÃO aborta — quem aborta é cancel().
                    ->before(function (Departamento $record, DeleteAction $action): void {
                        self::barrarSeResponsavel($record, $action);
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Excluir selecionados')
                        // OBRIGATÓRIO: sem o opt-in, injetar $records lança LogicException
                        // (InteractsWithSelectedRecords:47-51; o default de
                        // canAccessSelectedRecords é false, :15, e o DeleteBulkAction não opta).
                        ->accessSelectedRecords()
                        // ATENÇÃO: o bulk recebe $records (Collection), não $record.
                        ->before(function (Collection $records, DeleteBulkAction $action): void {
                            foreach ($records as $record) {
                                self::barrarSeResponsavel($record, $action);
                            }
                        }),
                ]),
            ]);
    }

    /** Aborta a exclusão se o departamento responde por algum tipo (a config é dona; I8). Public: o EditDepartamento consome. */
    public static function barrarSeResponsavel(Departamento $departamento, Action $acao): void
    {
        $recursos = $departamento->tiposConteudo()->pluck('recurso');

        if ($recursos->isEmpty()) {
            return;
        }

        $rotulos = $recursos->map(fn (string $r): string => GlossarioCapacidades::rotuloRecurso($r))->implode(', ');

        Notification::make()
            ->title('Departamento em uso na configuração de acesso')
            ->body("O departamento {$departamento->sigla} responde por: {$rotulos}. Remova-o em Configuração de acesso por tipo antes de excluir.")
            ->danger()
            ->send();

        $acao->cancel();
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDepartamentos::route('/'),
            'create' => CreateDepartamento::route('/create'),
            'edit' => EditDepartamento::route('/{record}/edit'),
        ];
    }
}
