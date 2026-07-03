<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-03

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $navigationLabel = 'Usuários';

    protected static ?string $modelLabel = 'Usuário';

    protected static ?string $pluralModelLabel = 'Usuários';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Dados da conta')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nome')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('email')
                            ->label('E-mail')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),

                        TextInput::make('password')
                            ->label('Senha')
                            ->password()
                            ->revealable()
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->dehydrateStateUsing(fn (string $state): string => Hash::make($state))
                            ->required(fn (string $context): bool => $context === 'create')
                            ->helperText('Deixe em branco para manter a senha atual.'),

                        Toggle::make('socio')
                            ->label('Sócio'),

                        Toggle::make('ativo')
                            ->label('Ativo')
                            ->default(true),
                    ]),

                Section::make('Papel e estrutura')
                    ->columns(2)
                    ->schema([
                        // `roles` é morphToMany (Spatie) → Filament salva via sync().
                        // maxItems(1) força papel único (hierarquia linear de acesso).
                        Select::make('roles')
                            ->label('Papel')
                            ->relationship('roles', 'name')
                            ->multiple()
                            ->maxItems(1)
                            ->preload()
                            ->required(),

                        Select::make('setores')
                            ->label('Setores')
                            ->relationship('setores', 'nome')
                            ->multiple()
                            ->preload(),

                        Select::make('cargos')
                            ->label('Cargos')
                            ->relationship('cargos', 'nome')
                            ->multiple()
                            ->preload(),
                    ]),

                Section::make('Perfil')
                    ->relationship('perfil')
                    ->columns(2)
                    ->schema([
                        TextInput::make('whatsapp')
                            ->label('WhatsApp')
                            ->tel()
                            ->maxLength(20),

                        Toggle::make('whatsapp_publico')
                            ->label('Exibir WhatsApp no site'),

                        DatePicker::make('data_nascimento')
                            ->label('Data de nascimento')
                            ->native(false),

                        Textarea::make('endereco')
                            ->label('Endereço')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->label('E-mail')
                    ->searchable(),

                TextColumn::make('roles.name')
                    ->label('Papel')
                    ->badge(),

                IconColumn::make('socio')
                    ->label('Sócio')
                    ->boolean(),

                IconColumn::make('ativo')
                    ->label('Ativo')
                    ->boolean(),

                TextColumn::make('setores.nome')
                    ->label('Setores')
                    ->badge()
                    ->limitList(3),

                TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make()->label('Editar'),
                DeleteAction::make()->label('Excluir'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->label('Excluir selecionados'),
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
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
