<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-21

namespace App\Filament\Schemas;

use App\Enums\FormatoMensagem;
use App\Enums\VisibilidadeMensagem;
use App\Filament\Support\ComponentesImagem;
use App\Models\Mensagem;
use App\Models\User;
use Closure;
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

                    Textarea::make('resumo')
                        ->label('Resumo (texto editorial)')
                        ->helperText('Aparece no card, na busca do Google e como abertura da página. Importado do site antigo quando havia. Opcional.')
                        ->rows(4)
                        ->maxLength(1500)
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
                        ->options(VisibilidadeMensagem::opcoes())
                        ->live() // pré-requisito do visible da Section Destinatários / required condicional
                        ->helperText('Define quem pode acessar esta mensagem no site.'),

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

    /**
     * Bloco de destinatários compartilhado por schemaMedium/schemaCuradoria — PARAMETRIZADO pelo predicado
     * de visibilidade/obrigatoriedade, porque o form do médium não tem o campo `nivel` (quem arbitra o nível
     * é o diretor, na curadoria). NÃO é usado pelo schemaAdmin, que mantém a Section inline (filtra `ativo`
     * e não tem o helperText do painel — compartilhar mudaria o /admin em silêncio).
     *
     * Achado do review final (Important 2a): as options SEMPRE incluem os já selecionados (`orWhereIn`),
     * mesmo que tenham deixado de estar `ativo` depois — senão o `Select` injeta `Rule::in(options)` sem o
     * id hidratado pelo `fill()` (vindo do pivô), e um destinatário desativado DEPOIS de uma direcionada
     * existir trava até um simples Salvar de título, sem saída (a opção nem aparece pra ser removida). Quem
     * garante que um inativo nunca É GRAVADO no pivô é o filtro de integridade de sempre, em
     * `SincronizadorDestinatarios::aplicar()` (I7) — aqui é só sobre a OPÇÃO existir na tela.
     *
     * @param  Closure(Get): bool  $ehDirecionada
     */
    private static function blocoDestinatarios(Closure $ehDirecionada): Section
    {
        return Section::make('Destinatários')
            ->description('Usuários a quem esta mensagem direcionada foi endereçada.')
            ->visible($ehDirecionada)
            ->schema([
                Select::make('destinatarios')
                    ->label('Destinatários')
                    ->options(fn (Get $get) => User::query()
                        ->where('ativo', true)
                        ->orWhereIn('id', (array) $get('destinatarios'))
                        ->orderBy('name')
                        ->pluck('name', 'id'))
                    ->multiple()
                    ->searchable()
                    ->required($ehDirecionada)
                    ->minItems(1)
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Form do médium em /minha-conta/mensagens: a mensagem nasce PENDENTE, sem nível — quem arbitra o
     * nível é o diretor na curadoria. Por isso não tem `nivel`, `status`, `slug` (gerado no servidor),
     * `link_arquivo`, `liberar_download` nem `relacionadas` (D6).
     *
     * @return array<Component>
     */
    public static function schemaMedium(): array
    {
        return [
            Section::make('Conteúdo')
                ->columns(2)
                ->schema([
                    TextInput::make('titulo')
                        ->label('Título')
                        ->required()
                        ->maxLength(255)
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

            Section::make('Classificação')
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
                ]),

            Section::make('Autoria')
                ->schema([
                    // ->relationship() é OBRIGATÓRIO aqui (não trocar por ->options()): fica dehydrated(false)
                    // e só grava em saveRelationships(), o que dá sentido ao G1 (autores + pictografia).
                    Select::make('autores')
                        ->label('Autores espirituais')
                        ->relationship('autores', 'nome')
                        ->multiple()
                        ->preload()
                        ->searchable(),
                ]),

            Section::make('Pictografia')
                ->schema([
                    ComponentesImagem::upload('pictografia', Mensagem::COLECAO_PICTOGRAFIA, multiplas: true)
                        ->label('Imagens (pictografia)'),
                ]),

            Toggle::make('direcionar')
                ->label('Direcionar a pessoas específicas')
                ->live(),

            self::blocoDestinatarios(fn (Get $get): bool => (bool) $get('direcionar')),
        ];
    }

    /**
     * Form da curadoria em /minha-conta/curadoria: o diretor do DEPAE arbitra o nível entre as 6 opções do
     * enum (inclui `diretor-depae`, que a constante antiga do Resource não tem — I23). É o schemaAdmin SEM
     * `slug` (gerado no servidor), SEM o Select `status` (o estado é decidido pelos botões Salvar/Publicar,
     * não por um Select solto — furaria a RegraPublicacao) e SEM `relacionadas` (a persistência vive num
     * trait das Pages do /admin; herdar aqui entregaria um campo que não grava nada, em silêncio).
     *
     * @return array<Component>
     */
    public static function schemaCuradoria(): array
    {
        return [
            Section::make('Conteúdo')
                ->columns(2)
                ->schema([
                    TextInput::make('titulo')
                        ->label('Título')
                        ->required()
                        ->maxLength(255)
                        ->columnSpan(2),

                    Textarea::make('contexto')
                        ->label('Contexto (faixa editorial — manual)')
                        ->helperText('Texto curto de contexto exibido acima da mensagem. Opcional.')
                        ->rows(3)
                        ->columnSpan(2),

                    Textarea::make('resumo')
                        ->label('Resumo (texto editorial)')
                        ->helperText('Aparece no card, na busca do Google e como abertura da página. Importado do site antigo quando havia. Opcional.')
                        ->rows(4)
                        ->maxLength(1500)
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
                        ->options(VisibilidadeMensagem::opcoes())
                        ->live() // pré-requisito do visible/required do bloco de Destinatários
                        ->helperText('Define quem pode acessar esta mensagem no site.'),

                    Toggle::make('liberar_download')
                        ->label('Liberar download do arquivo'),

                    TextInput::make('link_arquivo')
                        ->label('Link do arquivo (Google Drive)')
                        ->url()
                        ->maxLength(500)
                        ->columnSpan(2),
                ]),

            Section::make('Autoria')
                ->schema([
                    // ->relationship() é OBRIGATÓRIO aqui (não trocar por ->options()): fica dehydrated(false)
                    // e só grava em saveRelationships(), o que dá sentido ao G1 (autores + pictografia).
                    Select::make('autores')
                        ->label('Autores espirituais')
                        ->relationship('autores', 'nome')
                        ->multiple()
                        ->preload()
                        ->searchable(),
                ]),

            self::blocoDestinatarios(
                fn (Get $get): bool => $get('nivel') === VisibilidadeMensagem::Direcionada->value
            ),

            Section::make('Pictografia')
                ->schema([
                    ComponentesImagem::upload('pictografia', Mensagem::COLECAO_PICTOGRAFIA, multiplas: true)
                        ->label('Imagens (pictografia)'),
                ]),
        ];
    }
}
