<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-18

namespace App\Filament\Resources\Mensagens;

use App\Enums\FormatoMensagem;
use App\Enums\VisibilidadeMensagem;
use App\Filament\Resources\Mensagens\Pages\CreateMensagem;
use App\Filament\Resources\Mensagens\Pages\EditMensagem;
use App\Filament\Resources\Mensagens\Pages\ListMensagens;
use App\Filament\Support\ComponentesImagem;
use App\Models\Mensagem;
use App\Models\User;
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
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class MensagemResource extends Resource
{
    protected static ?string $model = Mensagem::class;

    // Sem $slug o Laravel geraria 'mensagems' (pluralizador inglês) — travamos a rota pt-BR.
    protected static ?string $slug = 'mensagens';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?string $navigationLabel = 'Mensagens Mediúnicas';

    protected static ?string $modelLabel = 'Mensagem';

    protected static ?string $pluralModelLabel = 'Mensagens';

    protected static ?string $recordTitleAttribute = 'titulo';

    /** Níveis de acesso BRUTOS (slugs da taxonomia legada). A semântica rica é da Fatia 3. */
    public const NIVEIS = [
        'publico' => 'Público',
        'trabalhadores' => 'Trabalhadores',
        'mediuns-trabalhadores' => 'Médiuns',
        'direcionada' => 'Direcionada',
        'diretores' => 'Diretores',
    ];

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Conteúdo')
                    ->columns(2)
                    ->schema([
                        TextInput::make('titulo')
                            ->label('Título')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (string $operation, ?string $state, callable $set) {
                                if ($operation === 'create') {
                                    $set('slug', Str::slug($state ?? ''));
                                }
                            })
                            ->columnSpan(2),

                        TextInput::make('slug')
                            ->label('Slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->columnSpan(2),

                        Textarea::make('contexto')
                            ->label('Contexto (faixa editorial — manual)')
                            ->helperText('Texto curto de contexto exibido acima da mensagem. Opcional.')
                            ->rows(3)
                            ->columnSpan(2),

                        RichEditor::make('corpo')
                            ->label('Corpo da mensagem')
                            ->toolbarButtons([
                                'bold', 'italic', 'underline', 'strike', 'link',
                                'bulletList', 'orderedList', 'blockquote', 'h2', 'h3',
                            ])
                            ->columnSpanFull(),
                    ]),

                Section::make('Classificação e download')
                    ->columns(2)
                    ->schema([
                        Select::make('formato')
                            ->label('Formato')
                            ->options(FormatoMensagem::opcoes())
                            ->required(),

                        DatePicker::make('data_recebimento')
                            ->label('Data de recebimento')
                            ->native(false)
                            ->displayFormat('d/m/Y'),

                        Select::make('nivel')
                            ->label('Nível de acesso')
                            ->options(self::NIVEIS)
                            ->live() // pré-requisito do visible da Section Destinatários / required condicional
                            ->helperText('Só as Públicas aparecem no site (por ora). A visibilidade rica virá na próxima fase.'),

                        Select::make('status')
                            ->label('Status')
                            ->options([
                                Mensagem::STATUS_PUBLICADO => 'Publicada',
                                Mensagem::STATUS_PENDENTE => 'Pendente',
                                Mensagem::STATUS_DESPUBLICADA => 'Despublicada',
                            ])
                            ->default(Mensagem::STATUS_PUBLICADO)
                            ->required(),

                        Toggle::make('liberar_download')
                            ->label('Liberar download do arquivo'),

                        TextInput::make('link_arquivo')
                            ->label('Link do arquivo (Google Drive)')
                            ->url()
                            ->maxLength(500)
                            ->columnSpan(2),
                    ]),

                Section::make('Autoria e relações')
                    ->columns(2)
                    ->schema([
                        Select::make('autores')
                            ->label('Autores espirituais')
                            ->relationship('autores', 'nome')
                            ->multiple()
                            ->preload()
                            ->searchable(),

                        Select::make('relacionadas')
                            ->label('Mensagens relacionadas')
                            ->multiple()
                            ->searchable()
                            ->options(fn (?Mensagem $record) => Mensagem::query()
                                ->when($record, fn ($q) => $q->whereKeyNot($record->getKey()))
                                ->orderBy('titulo')
                                ->pluck('titulo', 'id'))
                            ->helperText('Relação simétrica: ao relacionar A→B, B também passa a listar A.'),
                    ]),

                Section::make('Destinatários')
                    ->description('Usuários a quem esta mensagem direcionada foi endereçada.')
                    ->visible(fn (Get $get): bool => $get('nivel') === VisibilidadeMensagem::Direcionada->value)
                    ->schema([
                        Select::make('destinatarios')
                            ->label('Destinatários')
                            ->helperText('Obrigatório para mensagens de nível "Direcionada".')
                            ->options(fn () => User::orderBy('name')->pluck('name', 'id'))
                            ->multiple()
                            ->searchable()
                            ->required(fn (Get $get): bool => $get('nivel') === VisibilidadeMensagem::Direcionada->value)
                            ->minItems(1)
                            ->columnSpanFull(),
                    ]),

                Section::make('Pictografia')
                    ->schema([
                        ComponentesImagem::upload('pictografia', Mensagem::COLECAO_PICTOGRAFIA, multiplas: true)
                            ->label('Imagens (pictografia)'),
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

                TextColumn::make('formato')
                    ->label('Formato')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state instanceof FormatoMensagem ? $state->rotulo() : (string) $state),

                TextColumn::make('data_recebimento')
                    ->label('Recebida em')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('nivel')
                    ->label('Nível')
                    ->formatStateUsing(fn (?string $state): string => self::NIVEIS[$state] ?? '—')
                    ->toggleable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        Mensagem::STATUS_PUBLICADO => 'success',
                        Mensagem::STATUS_PENDENTE => 'warning',
                        default => 'gray',
                    }),

                IconColumn::make('liberar_download')
                    ->label('Download')
                    ->boolean()
                    ->toggleable(),

                SpatieMediaLibraryImageColumn::make('pictografia')
                    ->label('Pictografia')
                    ->collection(Mensagem::COLECAO_PICTOGRAFIA)
                    ->conversion('thumb')
                    ->toggleable(),

                TextColumn::make('destinatarios_count')
                    ->label('Destinatários')
                    ->counts('destinatarios')
                    ->badge()
                    ->toggleable(),
            ])
            ->defaultSort('data_recebimento', 'desc')
            ->filters([
                SelectFilter::make('status')->options([
                    Mensagem::STATUS_PUBLICADO => 'Publicada',
                    Mensagem::STATUS_PENDENTE => 'Pendente',
                    Mensagem::STATUS_DESPUBLICADA => 'Despublicada',
                ]),
                SelectFilter::make('formato')->options(FormatoMensagem::opcoes()),
                Filter::make('com_destinatarios')
                    ->label('Tem destinatário')
                    ->query(fn (Builder $query): Builder => $query->has('destinatarios')),
            ])
            ->recordActions([
                EditAction::make()->label('Editar'),
                DeleteAction::make()->label('Excluir'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->label('Excluir selecionadas'),
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
            'index' => ListMensagens::route('/'),
            'create' => CreateMensagem::route('/create'),
            'edit' => EditMensagem::route('/{record}/edit'),
        ];
    }
}
