<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-21

namespace App\Filament\Schemas;

use App\Enums\FormatoMensagem;
use App\Enums\VisibilidadeMensagem;
use App\Filament\Support\AvatarOpcao;
use App\Filament\Support\ComponentesImagem;
use App\Models\AutorEspiritual;
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
    /**
     * Frase única do /admin para "publicada precisa de nível" — consumida pelo
     * validationMessages() do Select E pela Action Publicar. A RegraPublicacao NÃO muda: ela é
     * compartilhada com a curadoria do site, onde o texto genérico é adequado, e tem teste
     * unitário próprio.
     */
    public const MSG_NIVEL_OBRIGATORIO = 'Selecione o nível de acesso para manter esta mensagem publicada.';

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
                        // Precede a frase genérica do lang/pt_BR/validation.php, de propósito:
                        // 39 das 47 pendentes têm slug de máquina e precisam de revisão.
                        ->validationMessages(['unique' => 'Este slug já está em uso. Ajuste-o antes de salvar.'])
                        ->columnSpan(2),

                    Textarea::make('resumo')
                        ->label('Resumo')
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
                        ->required(fn (Get $get): bool => $get('status') === Mensagem::STATUS_PUBLICADO)
                        ->validationMessages(['required' => self::MSG_NIVEL_OBRIGATORIO])
                        ->helperText('Define quem pode acessar esta mensagem no site.'),

                    Select::make('status')
                        ->label('Status')
                        ->options([
                            Mensagem::STATUS_PUBLICADO => 'Publicada',
                            Mensagem::STATUS_PENDENTE => 'Pendente',
                            Mensagem::STATUS_DESPUBLICADA => 'Despublicada',
                        ])
                        ->default(Mensagem::STATUS_PUBLICADO)
                        ->live() // o required condicional do `nivel` depende deste estado
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
                    self::selectAutores(),

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
                    self::selectDestinatarios()
                        ->helperText('Obrigatório para mensagens de nível "Direcionada".')
                        ->required(fn (Get $get): bool => $get('nivel') === VisibilidadeMensagem::Direcionada->value),
                ]),

            Section::make('Imagens')
                ->schema([
                    ComponentesImagem::upload('imagens', Mensagem::COLECAO_IMAGENS, multiplas: true)
                        ->label('Imagens da mensagem'),
                ]),
        ];
    }

    /**
     * Select `autores` compartilhado pelos 3 schemas. `->relationship()` é OBRIGATÓRIO (F2):
     * fica dehydrated(false) e grava só em saveRelationships(). O avatar da opção vem do helper via
     * getOptionLabelFromRecordUsing (allowHtml não escapa — O2). O eager-load da mídia é feito pelo 3º arg de
     * relationship() (`->with('media')`) — evita N+1 ao ler foto_thumb_url por autor (O1/A2).
     */
    private static function selectAutores(): Select
    {
        return Select::make('autores')
            ->label('Autores espirituais')
            ->relationship('autores', 'nome', fn ($query) => $query->with('media'))
            ->multiple()
            ->preload()
            ->searchable()
            ->allowHtml()
            ->getOptionLabelFromRecordUsing(
                fn (AutorEspiritual $record): string => AvatarOpcao::html($record->foto_thumb_url, $record->nome, $record->iniciais)
            );
    }

    /**
     * Base do Select `destinatarios` compartilhada pelo inline do schemaAdmin e por blocoDestinatarios().
     * Motor SERVER-SIDE (D2): a busca casa a coluna `name` (O3, imune ao filtro sobre HTML do allowHtml);
     * getOptionLabelsUsing hidrata os já-selecionados INCLUSIVE inativos (whereKey SEM filtro `ativo`,
     * SEM limit — papel do antigo orWhereIn; senão o Rule::in trava até um Salvar de título, A3).
     * Foto do destinatário via perfil (User não é HasMedia — A1), eager `perfil.media` (O1). Avatar
     * pelo helper (allowHtml não escapa — O2). Cada call site aplica ->helperText()/->required().
     */
    private static function selectDestinatarios(): Select
    {
        return Select::make('destinatarios')
            ->label('Destinatários')
            ->multiple()
            ->searchable()
            ->minItems(1)
            ->columnSpanFull()
            ->allowHtml()
            ->getSearchResultsUsing(fn (string $search): array => User::query()
                ->where('ativo', true)
                ->where('name', 'like', "%{$search}%")
                ->with('perfil.media')
                ->orderBy('name')
                ->limit(50)
                ->get()
                // LIKE cru: `%`/`_` do termo NÃO escapados; sem generate_search_term_expression (R8, aceito).
                ->mapWithKeys(fn (User $u) => [$u->id => AvatarOpcao::html($u->perfil?->foto_thumb_url, $u->name, $u->iniciais)])
                ->all())
            // As options SEMPRE incluem os já selecionados, mesmo que tenham deixado de estar
            // `ativo` depois — senão o Select injeta Rule::in(options) sem o id hidratado pelo
            // fill() e trava até um simples Salvar de título, sem a opção aparecer para ser
            // removida. Quem garante que um inativo nunca É GRAVADO no pivô é o filtro de
            // integridade de sempre, em SincronizadorDestinatarios::aplicar() — aqui é só sobre
            // a OPÇÃO existir na tela.
            ->getOptionLabelsUsing(fn (array $values): array => User::query()
                ->whereKey($values)
                ->with('perfil.media')
                ->orderBy('name')
                ->get()
                ->mapWithKeys(fn (User $u) => [$u->id => AvatarOpcao::html($u->perfil?->foto_thumb_url, $u->name, $u->iniciais)])
                ->all());
    }

    /**
     * Bloco de destinatários compartilhado por schemaMedium/schemaCuradoria — PARAMETRIZADO pelo predicado
     * de visibilidade/obrigatoriedade, porque o form do médium não tem o campo `nivel` (quem arbitra o nível
     * é o diretor, na curadoria). NÃO é usado pelo schemaAdmin, que mantém a Section inline por
     * causa do helperText próprio do painel — o motor server-side (selectDestinatarios) é o MESMO nos dois
     * desde a F4c (antes desta fatia o docblock afirmava que o admin filtrava, e ele não filtrava).
     *
     * @param  Closure(Get): bool  $ehDirecionada
     */
    private static function blocoDestinatarios(Closure $ehDirecionada): Section
    {
        return Section::make('Destinatários')
            ->description('Usuários a quem esta mensagem direcionada foi endereçada.')
            ->visible($ehDirecionada)
            ->schema([
                self::selectDestinatarios()->required($ehDirecionada),
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

                    Textarea::make('resumo')
                        ->label('Resumo')
                        ->helperText('Texto curto que abre a página da mensagem e aparece no card. Opcional.')
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
                    self::selectAutores(),
                ]),

            Section::make('Imagens')
                ->schema([
                    ComponentesImagem::upload('imagens', Mensagem::COLECAO_IMAGENS, multiplas: true)
                        ->label('Imagens da mensagem'),
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

                    Textarea::make('resumo')
                        ->label('Resumo')
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
                    self::selectAutores(),
                ]),

            self::blocoDestinatarios(
                fn (Get $get): bool => $get('nivel') === VisibilidadeMensagem::Direcionada->value
            ),

            Section::make('Imagens')
                ->schema([
                    ComponentesImagem::upload('imagens', Mensagem::COLECAO_IMAGENS, multiplas: true)
                        ->label('Imagens da mensagem'),
                ]),
        ];
    }
}
