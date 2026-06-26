<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-26

namespace App\Filament\Resources\Posts;

use App\Filament\Resources\Posts\Pages\CreatePost;
use App\Filament\Resources\Posts\Pages\EditPost;
use App\Filament\Resources\Posts\Pages\ListPosts;
use App\Models\Post;
use App\Support\Blog\PlacarSeo;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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

class PostResource extends Resource
{
    protected static ?string $model = Post::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNewspaper;

    protected static ?string $modelLabel = 'Post';

    protected static ?string $pluralModelLabel = 'Posts';

    protected static ?string $slug = 'posts';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('Post')->columnSpanFull()->tabs([

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
                            ->unique(table: 'posts', column: 'slug', ignoreRecord: true),
                    ]),
                    Textarea::make('resumo')
                        ->label('Resumo')
                        ->rows(3)
                        ->columnSpanFull(),
                    RichEditor::make('conteudo')
                        ->label('Conteúdo')
                        ->live(onBlur: true)
                        ->columnSpanFull(),
                ]),

                Tabs\Tab::make('Mídia')->schema([
                    Grid::make(2)->schema([
                        FileUpload::make('imagem_destacada')
                            ->label('Imagem destacada')
                            ->image()
                            ->disk('public')
                            ->directory('blog/destacada'),
                        TextInput::make('imagem_destacada_alt')
                            ->label('Alt da imagem destacada')
                            ->maxLength(255),
                    ]),
                    Repeater::make('imagens')
                        ->label('Galeria de imagens')
                        ->relationship('imagens')
                        ->schema([
                            FileUpload::make('caminho')
                                ->label('Imagem')
                                ->image()
                                ->disk('public')
                                ->directory('blog/galeria')
                                ->required(),
                            TextInput::make('alt')
                                ->label('Texto alternativo')
                                ->maxLength(255),
                        ])
                        ->orderColumn('ordem')
                        ->collapsible()
                        ->defaultItems(0)
                        ->addActionLabel('Adicionar imagem')
                        ->columnSpanFull(),
                ]),

                Tabs\Tab::make('Taxonomia e Publicação')->schema([
                    Grid::make(2)->schema([
                        Select::make('categorias')
                            ->label('Categorias')
                            ->relationship('categorias', 'nome')
                            ->multiple()
                            ->preload()
                            ->searchable(),
                        Select::make('categoria_principal_id')
                            ->label('Categoria principal')
                            ->relationship('categoriaPrincipal', 'nome')
                            ->searchable()
                            ->preload(),
                    ]),
                    Select::make('tags')
                        ->label('Tags')
                        ->relationship('tags', 'nome')
                        ->multiple()
                        ->preload()
                        ->searchable()
                        ->createOptionForm([
                            TextInput::make('nome')
                                ->label('Nome')
                                ->required()
                                ->maxLength(255)
                                ->live(onBlur: true)
                                ->afterStateUpdated(fn (?string $state, callable $set) => $set('slug', Str::slug($state ?? ''))),
                            TextInput::make('slug')
                                ->label('Slug')
                                ->required()
                                ->maxLength(255),
                        ]),
                    Grid::make(3)->schema([
                        Toggle::make('destaque')
                            ->label('Destaque'),
                        Select::make('status')
                            ->label('Status')
                            ->required()
                            ->options([
                                Post::STATUS_RASCUNHO  => 'Rascunho',
                                Post::STATUS_PUBLICADO => 'Publicado',
                                Post::STATUS_AGENDADO  => 'Agendado',
                            ])
                            ->default(Post::STATUS_RASCUNHO),
                        DateTimePicker::make('data_publicacao')
                            ->label('Data de publicação')
                            ->seconds(false),
                    ]),
                ]),

                Tabs\Tab::make('FAQ')->schema([
                    Repeater::make('faqs')
                        ->label('Perguntas frequentes')
                        ->relationship('faqs')
                        ->schema([
                            TextInput::make('pergunta')
                                ->label('Pergunta')
                                ->required()
                                ->maxLength(500),
                            Textarea::make('resposta')
                                ->label('Resposta')
                                ->required()
                                ->rows(3),
                        ])
                        ->orderColumn('ordem')
                        ->collapsible()
                        ->defaultItems(0)
                        ->addActionLabel('Adicionar pergunta')
                        ->columnSpanFull(),
                ]),

                Tabs\Tab::make('SEO')->schema([
                    Grid::make(2)->schema([
                        TextInput::make('seo_titulo')
                            ->label('Título SEO')
                            ->maxLength(255),
                        TextInput::make('seo_keyword')
                            ->label('Palavra-chave')
                            ->maxLength(255)
                            ->live(onBlur: true),
                    ]),
                    Textarea::make('seo_descricao')
                        ->label('Meta description')
                        ->maxLength(160)
                        ->rows(3)
                        ->live(onBlur: true)
                        ->columnSpanFull(),
                    Grid::make(2)->schema([
                        FileUpload::make('og_imagem')
                            ->label('Imagem OG (Open Graph)')
                            ->image()
                            ->disk('public')
                            ->directory('blog/og'),
                        TextInput::make('canonical')
                            ->label('URL canônica')
                            ->url()
                            ->maxLength(500),
                    ]),
                    Toggle::make('robots_noindex')
                        ->label('Noindex (ocultar do Google)'),
                    Placeholder::make('placar_seo')
                        ->label('Placar SEO')
                        ->content(fn (Get $get) => view('filament.seo-placar', [
                            'placar' => PlacarSeo::analisar(
                                $get('conteudo'),
                                $get('titulo'),
                                $get('seo_keyword'),
                                $get('seo_descricao'),
                            ),
                        ]))
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
                TextColumn::make('categoriaPrincipal.nome')
                    ->label('Categoria')
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        Post::STATUS_PUBLICADO => 'success',
                        Post::STATUS_AGENDADO  => 'warning',
                        default                => 'gray',
                    }),
                TextColumn::make('data_publicacao')
                    ->label('Publicação')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('visualizacoes')
                    ->label('Visualizações')
                    ->numeric()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        Post::STATUS_RASCUNHO  => 'Rascunho',
                        Post::STATUS_PUBLICADO => 'Publicado',
                        Post::STATUS_AGENDADO  => 'Agendado',
                    ]),
                SelectFilter::make('categorias')
                    ->label('Categoria')
                    ->relationship('categorias', 'nome')
                    ->multiple()
                    ->preload(),
            ])
            ->defaultSort('data_publicacao', 'desc')
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
            'index'  => ListPosts::route('/'),
            'create' => CreatePost::route('/create'),
            'edit'   => EditPost::route('/{record}/edit'),
        ];
    }
}
