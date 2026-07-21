<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-21

namespace App\Filament\Schemas;

use App\Enums\FormatoMensagem;
use App\Enums\VisibilidadeMensagem;
use App\Filament\Resources\Mensagens\MensagemResource;
use App\Filament\Support\ComponentesImagem;
use App\Models\Mensagem;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Str;

/**
 * Fonte única dos CAMPOS do formulário de Mensagem.
 *
 * Passo (i) da extração (Fatia F4b, I20): cópia LITERAL, campo a campo, do que hoje vive em
 * MensagemResource::form() — sem uma vírgula de diferença. As composições novas
 * (schemaMedium/schemaCuradoria, para o site) entram no passo (ii), em task própria.
 */
class MensagemForm
{
    /** @return array<Component> */
    public static function schemaAdmin(): array
    {
        return [
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
                        ->options(MensagemResource::NIVEIS)
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
        ];
    }
}
