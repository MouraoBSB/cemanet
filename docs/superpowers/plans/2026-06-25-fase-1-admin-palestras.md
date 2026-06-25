# Admin Filament — Módulo Palestras (Plano 5) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) ou superpowers:executing-plans para implementar tarefa a tarefa. Steps usam checkbox (`- [ ]`).

**Goal:** Entregar o admin (Filament 5) do módulo Palestras — CRUD de Palestra, Palestrante e Assunto — respeitando a cardinalidade (1–2 palestrantes / 0–1 diretor), com upload de mídia, sanitização de HTML e validação, fechando a Fase 1.

**Architecture:** Resources Filament 5 (API de **Schemas**) descobertos em `app/Filament/Resources` pelo `AdminPanelProvider` (painel `/admin`). A relação Palestra↔Palestrante com papel (pivô `palestra_pessoa.papel`) é modelada por **dois campos virtuais** no form (Palestrantes múltiplo + Diretor único), com cardinalidade validada **antes de salvar** e o pivô sincronizado nos hooks das páginas Create/Edit. HTML de `descricao`/`bio` é sanitizado por **mutator no model** (defesa em todos os pontos de escrita). assuntos via Select múltiplo, destaques via Repeater.

**Tech Stack:** PHP 8.3 · Laravel 13 · **Filament 5.6** · Livewire 4 · MySQL 8 (dev) / SQLite in-memory (testes) · `mews/purifier` (sanitização) · Docker.

## Global Constraints

- **Idioma pt-BR** em tudo (labels, mensagens, comentários, commits). Sintaxe/APIs no original. Os identificadores de domínio já existem em pt-BR.
- **Comandos no container:** `docker compose exec -T app <cmd>` (sem PHP/Composer no host). Ex.: `docker compose exec -T app php artisan make:filament-resource ...`, `... php artisan test`, `... ./vendor/bin/pint`, `... composer require ...`.
- **Filament 5 (instalado v5.6.7) — NÃO usar API do Filament 3.** Diferenças confirmadas no vendor:
  - Form: `public static function form(Schema $schema): Schema` com `->components([...])`. `Schema` = `Filament\Schemas\Schema`.
  - Layouts (`Section`, `Grid`, `Tabs`) em `Filament\Schemas\Components\*`. Campos (`TextInput`, `Textarea`, `RichEditor`, `Select`, `Toggle`, `FileUpload`, `DateTimePicker`, `ColorPicker`, `Repeater`) em `Filament\Forms\Components\*`.
  - Table: `public static function table(Table $table): Table`; actions em `Filament\Actions\*` (`EditAction`, `DeleteAction`, `BulkActionGroup`, `DeleteBulkAction`); `->recordActions([...])` e `->bulkActions([...])`.
  - Repeater: ordenação via `->orderColumn('ordem')` (não `orderable()`).
  - **Se uma classe/método não resolver, verificar o namespace/assinatura real no `vendor/filament/` antes de adaptar** — o vendor é a fonte da verdade para a versão exata.
- **Regra de negócio INVIOLÁVEL:** Palestra exige **1–2 palestrantes** (papel `palestrante`) e **0–1 diretor** (papel `diretor`), validada na aplicação (`App\Support\Palestras\CardinalidadePalestra::erros()`). A validação deve impedir o salvamento de um registro inválido (não criar palestra órfã).
- **Segurança — sanitização de HTML:** `descricao` (Palestra) e `bio` (Palestrante) são HTML editável (RichEditor não sanitiza). Sanitizar com allow-list **no model** (mutator `set`), protegendo admin, importador e futuras entradas. Validar `cor_fundo` (hex). Restrição-chave do projeto.
- **Acesso ao painel:** em `local`, qualquer `User` acessa (comportamento padrão do Filament 5). Restrição por `FilamentUser::canAccessPanel()` fica para o hardening de produção (fora deste plano) — registrar, não implementar.
- **Sem soft deletes** (o schema não tem `deleted_at`; adicionar seria fora de escopo). Delete é definitivo.
- **Tokens/marca:** opcional alinhar a cor primária do painel ao roxo institucional (`#4e4483`) — Task 5.
- **Autoria** em classes PHP novas relevantes (Resources, Pages): `// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-25`.
- **Pint** ao final de cada task com PHP novo.
- **Testes:** usar os helpers de teste do Filament/Livewire (`Livewire::test(PageClass::class)->fillForm([...])->call('create'|'save')->assertHasNoFormErrors()`), autenticando um `User` (`actingAs`). SQLite in-memory.

## Dados disponíveis (já implementados — não recriar)

- Models `Palestra`, `Palestrante`, `Assunto`, `PalestraDestaque` (ver Plano 1). `Palestra`: relações `palestrantes()` (belongsToMany withPivot `papel`), `assuntos()`, `destaques()` (hasMany orderBy ordem), constantes `STATUS_*`/`PAPEL_*`. `Palestrante`: `scopeAtivo`, campos foto/bio/etc. `Assunto`: `parent()/children()`.
- `App\Support\Palestras\CardinalidadePalestra::erros(int $nPalestrantes, int $nDiretores): array`.
- Factories: `PalestraFactory`, `PalestranteFactory` (estados `ativo()/inativo()`), `AssuntoFactory`, `UserFactory`.
- `AdminPanelProvider` (painel `admin`, path `/admin`, descobre resources em `app/Filament/Resources`). Painel local-aberto. Foto em disco `public` (`storage:link` existe).
- `config/app.php` locale `pt_BR`; `Carbon::setLocale('pt_BR')` no boot.

## File Structure

**Criados:**
- `config/purifier.php` — perfil `conteudo` (allow-list de HTML).
- `app/Filament/Resources/PalestranteResource.php` (+ `Pages/{List,Create,Edit}Palestrante.php`).
- `app/Filament/Resources/PalestraResource.php` (+ `Pages/{List,Create,Edit}Palestra.php` com hooks de pivô/cardinalidade).
- `app/Filament/Resources/AssuntoResource.php` (+ `Pages/...`).
- Testes: `tests/Feature/Models/SanitizacaoHtmlTest.php`, `tests/Feature/Filament/PalestranteResourceTest.php`, `tests/Feature/Filament/PalestraResourceTest.php`, `tests/Feature/Filament/AssuntoResourceTest.php`.

**Modificados:**
- `app/Models/Palestra.php` — mutator `set` de `descricao` (sanitização).
- `app/Models/Palestrante.php` — mutator `set` de `bio` (sanitização).
- `composer.json` — `mews/purifier`.
- `app/Providers/Filament/AdminPanelProvider.php` — (Task 5) cor primária + label de navegação.

---

## Task 1: Sanitização de HTML (segurança) — mews/purifier + mutators

**Files:**
- Modify: `composer.json` (via `composer require`)
- Create: `config/purifier.php`
- Modify: `app/Models/Palestra.php`, `app/Models/Palestrante.php`
- Test: `tests/Feature/Models/SanitizacaoHtmlTest.php`

**Interfaces:**
- Produces: ao gravar `Palestra::descricao` e `Palestrante::bio`, o HTML passa por `clean($html, 'conteudo')` (allow-list). Tags perigosas (`<script>`, `onerror`, etc.) são removidas; formatação legítima preservada.

- [ ] **Step 1: Instalar mews/purifier**

Run: `docker compose exec -T app composer require mews/purifier`
Expected: instala `mews/purifier` e publica (ou permite publicar) `config/purifier.php`.

- [ ] **Step 2: Publicar/criar a config com perfil `conteudo`**

Run (se o require não publicou): `docker compose exec -T app php artisan vendor:publish --provider="Mews\Purifier\PurifierServiceProvider"`
Depois, garantir em `config/purifier.php` um perfil `conteudo` permissivo o suficiente para conteúdo editorial (preserva títulos, listas, links, imagens; remove scripts/handlers):
```php
'conteudo' => [
    'HTML.Allowed' => 'p,br,b,strong,i,em,u,s,h2,h3,h4,ul,ol,li,blockquote,a[href|title|target|rel],img[src|alt|width|height],figure,figcaption',
    'HTML.TargetBlank' => true,
    'AutoFormat.RemoveEmpty' => true,
    'URI.AllowedSchemes' => ['http' => true, 'https' => true, 'mailto' => true],
],
```
> Manter também o perfil `default` que o pacote traz. Não remover perfis existentes.

- [ ] **Step 3: Escrever o teste de sanitização (falha primeiro)**

`tests/Feature/Models/SanitizacaoHtmlTest.php`:
```php
<?php

namespace Tests\Feature\Models;

use App\Models\Palestra;
use App\Models\Palestrante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SanitizacaoHtmlTest extends TestCase
{
    use RefreshDatabase;

    public function test_descricao_remove_script_e_preserva_formatacao(): void
    {
        $p = Palestra::factory()->create([
            'descricao' => '<p>Olá <strong>mundo</strong></p><script>alert(1)</script>',
        ]);

        $this->assertStringNotContainsString('<script', $p->fresh()->descricao);
        $this->assertStringContainsString('<strong>mundo</strong>', $p->fresh()->descricao);
    }

    public function test_descricao_remove_handler_onerror(): void
    {
        $p = Palestra::factory()->create([
            'descricao' => '<img src=x onerror="alert(1)">',
        ]);

        $this->assertStringNotContainsString('onerror', (string) $p->fresh()->descricao);
    }

    public function test_bio_do_palestrante_e_sanitizada(): void
    {
        $pessoa = Palestrante::factory()->create([
            'bio' => '<p>Bio</p><script>alert(1)</script>',
        ]);

        $this->assertStringNotContainsString('<script', (string) $pessoa->fresh()->bio);
        $this->assertStringContainsString('<p>Bio</p>', (string) $pessoa->fresh()->bio);
    }

    public function test_descricao_nula_permanece_nula(): void
    {
        $p = Palestra::factory()->create(['descricao' => null]);

        $this->assertNull($p->fresh()->descricao);
    }
}
```

- [ ] **Step 4: Rodar (deve falhar)**

Run: `docker compose exec -T app php artisan test --filter=SanitizacaoHtmlTest`
Expected: FAIL (sem mutator, o `<script>` persiste).

- [ ] **Step 5: Adicionar os mutators**

Em `app/Models/Palestra.php`, adicionar (usar `Illuminate\Database\Eloquent\Casts\Attribute`):
```php
use Illuminate\Database\Eloquent\Casts\Attribute;

protected function descricao(): Attribute
{
    return Attribute::make(
        set: fn (?string $value) => $value !== null ? clean($value, 'conteudo') : null,
    );
}
```
Em `app/Models/Palestrante.php`, análogo para `bio`:
```php
use Illuminate\Database\Eloquent\Casts\Attribute;

protected function bio(): Attribute
{
    return Attribute::make(
        set: fn (?string $value) => $value !== null ? clean($value, 'conteudo') : null,
    );
}
```

- [ ] **Step 6: Rodar (deve passar)**

Run: `docker compose exec -T app php artisan test --filter=SanitizacaoHtmlTest`
Expected: PASS. Depois a suíte completa (`docker compose exec -T app php artisan test`) — sem regressões.
> Nota: as 123 palestras já importadas têm `descricao` crua (fonte confiável — WP do CEMA). O mutator protege escritas futuras (admin/reimport). Re-sanitizar as existentes é opcional (uma reimportação passaria pelo mutator) — não fazer aqui.

- [ ] **Step 7: Pint + commit**

Run: `docker compose exec -T app ./vendor/bin/pint`
```bash
git add -A
git commit -m "feat(seguranca): sanitiza descricao/bio (mews/purifier) via mutator no model"
```

---

## Task 2: PalestranteResource (CRUD + foto + bio)

**Files:**
- Create: `app/Filament/Resources/PalestranteResource.php` + `app/Filament/Resources/PalestranteResource/Pages/{ListPalestrantes,CreatePalestrante,EditPalestrante}.php`
- Test: `tests/Feature/Filament/PalestranteResourceTest.php`

**Interfaces:**
- Consumes: model `Palestrante`, disco `public`.
- Produces: recurso Filament em `/admin/palestrantes` (List/Create/Edit). Form com nome/slug (auto), foto (FileUpload disco public/`palestrantes`), email/telefone + flags, `ativo`, bio (RichEditor → sanitizada pelo mutator). Faz-se primeiro porque o PalestraResource depende de palestrantes existentes.

- [ ] **Step 1: Gerar o resource**

Run: `docker compose exec -T app php artisan make:filament-resource Palestrante --generate`
> Verificar as flags reais com `docker compose exec -T app php artisan make:filament-resource --help`. O objetivo é ter `PalestranteResource.php` + as 3 páginas. Se o v5 gerar Schemas/Tables em classes separadas, pode-se mantê-las OU consolidar o form/table inline no Resource (estilo deste plano). Escolha o estilo inline para simplicidade.

- [ ] **Step 2: Escrever o teste do resource (falha primeiro)**

`tests/Feature/Filament/PalestranteResourceTest.php`:
```php
<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\PalestranteResource\Pages\CreatePalestrante;
use App\Models\Palestrante;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PalestranteResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_cria_palestrante_com_slug_auto_e_bio_sanitizada(): void
    {
        Livewire::test(CreatePalestrante::class)
            ->fillForm([
                'nome' => 'Maria das Dores',
                'slug' => 'maria-das-dores',
                'ativo' => true,
                'bio' => '<p>Bio</p><script>alert(1)</script>',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $pessoa = Palestrante::where('slug', 'maria-das-dores')->first();
        $this->assertNotNull($pessoa);
        $this->assertStringNotContainsString('<script', (string) $pessoa->bio);
    }

    public function test_lista_renderiza(): void
    {
        Palestrante::factory()->count(3)->create();

        $this->get('/admin/palestrantes')->assertOk();
    }
}
```

- [ ] **Step 3: Rodar (deve falhar)**

Run: `docker compose exec -T app php artisan test --filter=PalestranteResourceTest`
Expected: FAIL (form ainda não configurado).

- [ ] **Step 4: Implementar o PalestranteResource**

`app/Filament/Resources/PalestranteResource.php` (estilo inline; ajustar namespaces ao vendor se necessário):
```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-25

namespace App\Filament\Resources;

use App\Filament\Resources\PalestranteResource\Pages;
use App\Models\Palestrante;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class PalestranteResource extends Resource
{
    protected static ?string $model = Palestrante::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $modelLabel = 'Palestrante';

    protected static ?string $pluralModelLabel = 'Palestrantes';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identificação')->columns(2)->schema([
                TextInput::make('nome')
                    ->label('Nome')->required()->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (string $operation, ?string $state, callable $set) {
                        if ($operation === 'create') {
                            $set('slug', Str::slug($state ?? ''));
                        }
                    }),
                TextInput::make('slug')
                    ->label('Slug')->required()->maxLength(255)
                    ->unique(table: 'palestrantes', column: 'slug', ignoreRecord: true),
                FileUpload::make('foto')
                    ->label('Foto')->disk('public')->directory('palestrantes')
                    ->image()->maxSize(2048)->columnSpanFull(),
            ]),
            Section::make('Contato')->columns(2)->schema([
                TextInput::make('email')->label('E-mail')->email(),
                TextInput::make('telefone')->label('Telefone')->tel(),
                Toggle::make('mostrar_email')->label('Exibir e-mail no site'),
                Toggle::make('mostrar_telefone')->label('Exibir telefone no site'),
                Toggle::make('ativo')->label('Ativo (aparece no site)')->default(true),
            ]),
            Section::make('Biografia')->schema([
                RichEditor::make('bio')->label('Biografia')->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('foto')->label('Foto')->disk('public')->circular(),
                TextColumn::make('nome')->label('Nome')->searchable()->sortable(),
                TextColumn::make('email')->label('E-mail')->searchable()->toggleable(),
                IconColumn::make('ativo')->label('Ativo')->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPalestrantes::route('/'),
            'create' => Pages\CreatePalestrante::route('/create'),
            'edit' => Pages\EditPalestrante::route('/{record}/edit'),
        ];
    }
}
```
As 3 páginas são as geradas pelo artisan (sem hooks especiais). Conferir os namespaces/classes (`ListRecords`/`CreateRecord`/`EditRecord`) gerados.

- [ ] **Step 5: Rodar (deve passar)**

Run: `docker compose exec -T app php artisan test --filter=PalestranteResourceTest`
Expected: PASS. Depois a suíte completa.

- [ ] **Step 6: Pint + commit**

```bash
docker compose exec -T app ./vendor/bin/pint
git add -A
git commit -m "feat(admin): PalestranteResource (CRUD, foto, bio rich text)"
```

---

## Task 3: PalestraResource — form (campos, assuntos, destaques, cor) + table

**Files:**
- Create: `app/Filament/Resources/PalestraResource.php` + `Pages/{ListPalestras,CreatePalestra,EditPalestra}.php` (páginas geradas; hooks de pivô na Task 4)
- Test: `tests/Feature/Filament/PalestraResourceTest.php` (parte 1 — campos simples)

**Interfaces:**
- Consumes: model `Palestra`, relações `assuntos`/`destaques`, `Palestrante::ativo()`.
- Produces: recurso em `/admin/palestras` com form em Tabs (Conteúdo, Pessoas, Dados, Assuntos+Destaques) e table com filtros. **Nesta task** os dois selects de Pessoas existem (virtuais, `dehydrated(false)`) com as regras de cardinalidade, mas o sync do pivô é da Task 4. assuntos (Select múltiplo `->relationship`) e destaques (Repeater `->relationship` com `orderColumn('ordem')`) persistem pelo fluxo normal do Filament.

- [ ] **Step 1: Gerar o resource**

Run: `docker compose exec -T app php artisan make:filament-resource Palestra --generate`
(Mesma observação de estilo inline da Task 2.)

- [ ] **Step 2: Escrever o teste (parte 1 — falha primeiro)**

`tests/Feature/Filament/PalestraResourceTest.php`:
```php
<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\PalestraResource\Pages\CreatePalestra;
use App\Models\Assunto;
use App\Models\Palestra;
use App\Models\Palestrante;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PalestraResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_cria_palestra_com_assuntos_destaques_e_um_palestrante(): void
    {
        $p1 = Palestrante::factory()->ativo()->create();
        $assunto = Assunto::factory()->create();

        Livewire::test(CreatePalestra::class)
            ->fillForm([
                'titulo' => 'Auxílios do Invisível',
                'slug' => 'auxilios-do-invisivel',
                'status' => Palestra::STATUS_PUBLICADO,
                'ids_palestrantes' => [$p1->id],
                'assuntos' => [$assunto->id],
                'destaques' => [
                    ['destaque' => 'A fé raciocinada', 'texto' => 'Estudo sério.'],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $palestra = Palestra::where('slug', 'auxilios-do-invisivel')->first();
        $this->assertNotNull($palestra);
        $this->assertTrue($palestra->assuntos->contains($assunto));
        $this->assertCount(1, $palestra->destaques);
    }

    public function test_lista_renderiza(): void
    {
        Palestra::factory()->count(3)->create();

        $this->get('/admin/palestras')->assertOk();
    }
}
```
> Este teste depende dos hooks de pivô da Task 4 para persistir o palestrante; **na Task 3** pode falhar a parte do pivô. Para a Task 3, manter apenas as asserções de assuntos/destaques/criação; a asserção de pivô (papel) entra na Task 4. Ajustar conforme a ordem de execução.

- [ ] **Step 3: Implementar o PalestraResource (form + table)**

`app/Filament/Resources/PalestraResource.php`:
```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-25

namespace App\Filament\Resources;

use App\Filament\Resources\PalestraResource\Pages;
use App\Models\Palestra;
use App\Models\Palestrante;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class PalestraResource extends Resource
{
    protected static ?string $model = Palestra::class;

    protected static ?string $navigationIcon = 'heroicon-o-microphone';

    protected static ?string $modelLabel = 'Palestra';

    protected static ?string $pluralModelLabel = 'Palestras';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('Palestra')->columnSpanFull()->tabs([
                Tabs\Tab::make('Conteúdo')->schema([
                    Grid::make(2)->schema([
                        TextInput::make('titulo')
                            ->label('Título')->required()->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (string $operation, ?string $state, callable $set) {
                                if ($operation === 'create') {
                                    $set('slug', Str::slug($state ?? ''));
                                }
                            }),
                        TextInput::make('slug')
                            ->label('Slug')->required()->maxLength(255)
                            ->unique(table: 'palestras', column: 'slug', ignoreRecord: true),
                    ]),
                    TextInput::make('subtitulo')->label('Subtítulo')->maxLength(255),
                    Textarea::make('resumo')->label('Resumo')->rows(3)->columnSpanFull(),
                    RichEditor::make('descricao')->label('Descrição')->columnSpanFull(),
                ]),
                Tabs\Tab::make('Pessoas')->schema([
                    Select::make('ids_palestrantes')
                        ->label('Palestrantes (1 a 2, obrigatório)')
                        ->options(fn () => Palestrante::ativo()->orderBy('nome')->pluck('nome', 'id'))
                        ->multiple()->searchable()
                        ->minItems(1)->maxItems(2)->required()
                        ->dehydrated(false),
                    Select::make('id_diretor')
                        ->label('Diretor (opcional)')
                        ->options(fn () => Palestrante::ativo()->orderBy('nome')->pluck('nome', 'id'))
                        ->searchable()
                        ->dehydrated(false),
                ]),
                Tabs\Tab::make('Dados')->schema([
                    Grid::make(2)->schema([
                        DateTimePicker::make('data_da_palestra')->label('Data e hora')->seconds(false),
                        Select::make('status')->label('Status')->required()
                            ->options([
                                Palestra::STATUS_PUBLICADO => 'Publicado',
                                Palestra::STATUS_RASCUNHO => 'Rascunho',
                            ])->default(Palestra::STATUS_RASCUNHO),
                    ]),
                    Grid::make(2)->schema([
                        Toggle::make('online')->label('Disponível online'),
                        TextInput::make('link_youtube')->label('Link do YouTube')->url()->maxLength(500),
                    ]),
                    Grid::make(3)->schema([
                        TextInput::make('publico_presencial')->label('Público presencial')->numeric(),
                        TextInput::make('publico_online')->label('Público online')->numeric(),
                        TextInput::make('publico_total')->label('Público total')->numeric(),
                    ]),
                    ColorPicker::make('cor_fundo')->label('Cor de fundo (hero)')
                        ->rules(['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/']),
                ]),
                Tabs\Tab::make('Assuntos e destaques')->schema([
                    Select::make('assuntos')
                        ->label('Assuntos')
                        ->relationship('assuntos', 'nome')
                        ->multiple()->searchable()->preload(),
                    Repeater::make('destaques')
                        ->label('Destaques')
                        ->relationship('destaques')
                        ->schema([
                            TextInput::make('destaque')->label('Título')->required()->maxLength(255),
                            Textarea::make('texto')->label('Texto')->rows(2),
                        ])
                        ->orderColumn('ordem')
                        ->collapsible()->defaultItems(0)
                        ->addActionLabel('Adicionar destaque')
                        ->columnSpanFull(),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('titulo')->label('Título')->searchable()->sortable()->limit(60),
                TextColumn::make('data_da_palestra')->label('Data')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('status')->label('Status')->badge()
                    ->color(fn (string $state) => $state === Palestra::STATUS_PUBLICADO ? 'success' : 'gray'),
                IconColumn::make('online')->label('Online')->boolean(),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    Palestra::STATUS_PUBLICADO => 'Publicado',
                    Palestra::STATUS_RASCUNHO => 'Rascunho',
                ]),
            ])
            ->defaultSort('data_da_palestra', 'desc')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPalestras::route('/'),
            'create' => Pages\CreatePalestra::route('/create'),
            'edit' => Pages\EditPalestra::route('/{record}/edit'),
        ];
    }
}
```

- [ ] **Step 4: Rodar e commitar**

Run: `docker compose exec -T app php artisan test --filter=PalestraResourceTest` (a parte de assuntos/destaques/lista deve passar; a de pivô/papel virá na Task 4).
Run: `docker compose exec -T app ./vendor/bin/pint`
```bash
git add -A
git commit -m "feat(admin): PalestraResource form (tabs) e table (filtros)"
```

---

## Task 4: Pivô palestrantes/diretor + cardinalidade (hooks das páginas)

**Files:**
- Modify: `app/Filament/Resources/PalestraResource/Pages/CreatePalestra.php`, `EditPalestra.php`
- Test: `tests/Feature/Filament/PalestraResourceTest.php` (acrescenta casos de pivô/cardinalidade)

**Interfaces:**
- Consumes: campos virtuais `ids_palestrantes`/`id_diretor` do form (Task 3), `CardinalidadePalestra::erros()`.
- Produces: ao salvar (create/edit), o pivô `palestra_pessoa` é sincronizado com `papel` correto; a cardinalidade (1–2 palestrantes, 0–1 diretor) é garantida **antes** de persistir (via regras do form). No Edit, os selects são pré-preenchidos a partir do pivô por papel.

> **Correção de design (importante):** a cardinalidade é validada **no form** (`required`, `minItems(1)`, `maxItems(2)` no select de palestrantes; o diretor é select único → 0–1 inerente), que roda ANTES do save. O sync do pivô ocorre em `afterCreate`/`afterSave`. **Não** validar/halt em `afterCreate` (lá o registro já existe → palestra órfã). A reutilização de `CardinalidadePalestra::erros()` aqui é defensiva (log/observação), não o gate primário.

- [ ] **Step 1: Escrever os testes de pivô/cardinalidade (falham primeiro)**

Acrescentar a `tests/Feature/Filament/PalestraResourceTest.php`:
```php
    public function test_pivo_grava_papel_correto_para_palestrante_e_diretor(): void
    {
        $pal = Palestrante::factory()->ativo()->create();
        $dir = Palestrante::factory()->ativo()->create();

        Livewire::test(CreatePalestra::class)
            ->fillForm([
                'titulo' => 'Com Diretor',
                'slug' => 'com-diretor',
                'status' => Palestra::STATUS_PUBLICADO,
                'ids_palestrantes' => [$pal->id],
                'id_diretor' => $dir->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $palestra = Palestra::where('slug', 'com-diretor')->first();
        $this->assertEqualsCanonicalizing(
            [$pal->id],
            $palestra->palestrantes()->wherePivot('papel', Palestra::PAPEL_PALESTRANTE)->pluck('palestrantes.id')->all()
        );
        $this->assertSame(
            $dir->id,
            $palestra->palestrantes()->wherePivot('papel', Palestra::PAPEL_DIRETOR)->value('palestrantes.id')
        );
    }

    public function test_rejeita_zero_palestrantes(): void
    {
        Livewire::test(CreatePalestra::class)
            ->fillForm([
                'titulo' => 'Sem Palestrante',
                'slug' => 'sem-palestrante',
                'status' => Palestra::STATUS_PUBLICADO,
                'ids_palestrantes' => [],
            ])
            ->call('create')
            ->assertHasFormErrors(['ids_palestrantes']);

        $this->assertDatabaseMissing('palestras', ['slug' => 'sem-palestrante']);
    }

    public function test_rejeita_tres_palestrantes(): void
    {
        $tres = Palestrante::factory()->ativo()->count(3)->create()->pluck('id')->all();

        Livewire::test(CreatePalestra::class)
            ->fillForm([
                'titulo' => 'Três Palestrantes',
                'slug' => 'tres-palestrantes',
                'status' => Palestra::STATUS_PUBLICADO,
                'ids_palestrantes' => $tres,
            ])
            ->call('create')
            ->assertHasFormErrors(['ids_palestrantes']);

        $this->assertDatabaseMissing('palestras', ['slug' => 'tres-palestrantes']);
    }

    public function test_edit_preenche_selects_a_partir_do_pivo(): void
    {
        $palestra = Palestra::factory()->create();
        $pal = Palestrante::factory()->ativo()->create();
        $dir = Palestrante::factory()->ativo()->create();
        $palestra->palestrantes()->attach($pal, ['papel' => Palestra::PAPEL_PALESTRANTE]);
        $palestra->palestrantes()->attach($dir, ['papel' => Palestra::PAPEL_DIRETOR]);

        Livewire::test(\App\Filament\Resources\PalestraResource\Pages\EditPalestra::class, ['record' => $palestra->id])
            ->assertFormSet([
                'ids_palestrantes' => [$pal->id],
                'id_diretor' => $dir->id,
            ]);
    }
```

- [ ] **Step 2: Rodar (deve falhar)**

Run: `docker compose exec -T app php artisan test --filter=PalestraResourceTest`
Expected: FAIL (sem hooks, o pivô não é gravado/pré-preenchido).

- [ ] **Step 3: Implementar os hooks — trait compartilhada**

Criar `app/Filament/Resources/PalestraResource/Pages/SincronizaPessoas.php` (trait reusada por Create e Edit):
```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-25

namespace App\Filament\Resources\PalestraResource\Pages;

use App\Models\Palestra;

trait SincronizaPessoas
{
    protected function sincronizarPessoas(Palestra $palestra, array $estado): void
    {
        $idsPalestrantes = array_values(array_filter((array) ($estado['ids_palestrantes'] ?? [])));
        $idDiretor = $estado['id_diretor'] ?? null;

        $sync = [];
        foreach ($idsPalestrantes as $id) {
            $sync[$id] = ['papel' => Palestra::PAPEL_PALESTRANTE];
        }
        if ($idDiretor) {
            $sync[$idDiretor] = ['papel' => Palestra::PAPEL_DIRETOR];
        }

        $palestra->palestrantes()->sync($sync);
    }
}
```

`CreatePalestra.php`:
```php
<?php

namespace App\Filament\Resources\PalestraResource\Pages;

use App\Filament\Resources\PalestraResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePalestra extends CreateRecord
{
    use SincronizaPessoas;

    protected static string $resource = PalestraResource::class;

    protected function afterCreate(): void
    {
        $this->sincronizarPessoas($this->record, $this->form->getRawState());
    }
}
```

`EditPalestra.php`:
```php
<?php

namespace App\Filament\Resources\PalestraResource\Pages;

use App\Filament\Resources\PalestraResource;
use App\Models\Palestra;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPalestra extends EditRecord
{
    use SincronizaPessoas;

    protected static string $resource = PalestraResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['ids_palestrantes'] = $this->record->palestrantes()
            ->wherePivot('papel', Palestra::PAPEL_PALESTRANTE)
            ->pluck('palestrantes.id')->all();

        $data['id_diretor'] = $this->record->palestrantes()
            ->wherePivot('papel', Palestra::PAPEL_DIRETOR)
            ->value('palestrantes.id');

        return $data;
    }

    protected function afterSave(): void
    {
        $this->sincronizarPessoas($this->record, $this->form->getRawState());
    }
}
```
> Verificar: `$this->form->getRawState()` retorna o estado incluindo campos `dehydrated(false)`. Se a API v5 expuser de outro modo (ex.: `$this->data`), usar o método correto confirmado no vendor/teste.

- [ ] **Step 4: Rodar (deve passar)**

Run: `docker compose exec -T app php artisan test --filter=PalestraResourceTest`
Expected: PASS (incluindo pivô, rejeição de 0/3 e pré-preenchimento no edit). Depois a suíte completa.

- [ ] **Step 5: Pint + commit**

```bash
docker compose exec -T app ./vendor/bin/pint
git add -A
git commit -m "feat(admin): pivô palestrantes/diretor com papel + cardinalidade validada no form"
```

---

## Task 5: AssuntoResource + acabamento do painel + verificação

**Files:**
- Create: `app/Filament/Resources/AssuntoResource.php` + páginas
- Modify: `app/Providers/Filament/AdminPanelProvider.php` (cor primária + agrupamento de navegação)
- Test: `tests/Feature/Filament/AssuntoResourceTest.php`

**Interfaces:**
- Produces: recurso simples de Assunto (`nome`, `slug` auto, `parent_id` via Select de assuntos) em `/admin/assuntos`; painel com cor primária roxa institucional.

- [ ] **Step 1: Gerar e testar (falha primeiro)**

Run: `docker compose exec -T app php artisan make:filament-resource Assunto --generate`
`tests/Feature/Filament/AssuntoResourceTest.php`:
```php
<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\AssuntoResource\Pages\CreateAssunto;
use App\Models\Assunto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AssuntoResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_cria_assunto_com_pai(): void
    {
        $pai = Assunto::factory()->create(['nome' => 'Espiritismo']);

        Livewire::test(CreateAssunto::class)
            ->fillForm([
                'nome' => 'Mediunidade',
                'slug' => 'mediunidade',
                'parent_id' => $pai->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $filho = Assunto::where('slug', 'mediunidade')->first();
        $this->assertTrue($filho->parent->is($pai));
    }
}
```

- [ ] **Step 2: Implementar AssuntoResource**

Form: `nome` (live slug), `slug` (unique ignoreRecord), `parent_id` (Select `->relationship('parent','nome')` ou `->options(Assunto::pluck('nome','id'))`, nullable, `->searchable()`). Table: `nome`, `parent.nome`, contagem de `children`. Páginas padrão. (Seguir o mesmo padrão das Tasks 2/3; ajustar namespaces ao vendor.)

- [ ] **Step 3: Acabamento do painel**

Em `AdminPanelProvider::panel()`, trocar a cor primária para o roxo institucional e (opcional) agrupar a navegação:
```php
->colors([
    'primary' => Color::hex('#4e4483'),
])
```
(Confirmar a API `Color::hex` no vendor do Filament 5; alternativa: paleta custom.)

- [ ] **Step 4: Rodar tudo + verificação manual**

Run: `docker compose exec -T app php artisan test` — tudo verde.
Verificação manual (registrar no relatório): abrir `http://localhost:8000/admin`, logar, e:
- criar/editar uma Palestra escolhendo 1–2 palestrantes + (opcional) diretor; confirmar que 0 ou 3 palestrantes bloqueiam o save; confirmar que ao reabrir, os selects vêm preenchidos por papel;
- editar a `descricao` com um `<script>` e confirmar que não persiste (sanitização);
- abrir a mesma palestra no front (`/palestras/{slug}`) e confirmar que reflete a edição e que só palestrantes ativos aparecem.

- [ ] **Step 5: Pint + commit**

```bash
docker compose exec -T app ./vendor/bin/pint
git add -A
git commit -m "feat(admin): AssuntoResource + cor do painel + verificação manual"
```

---

## Verificação final (whole-branch)

- [ ] `docker compose exec -T app php artisan test` — tudo verde (incluindo os 38 testes do front).
- [ ] Admin permite CRUD de Palestra/Palestrante/Assunto respeitando cardinalidade e sanitização.
- [ ] Front continua correto após edições no admin (só ativos; conteúdo sanitizado).
- [ ] Atualizar `ROADMAP.md`: marcar o item "Filament Resources" como concluído e fechar a Fase 1.

## Critérios de pronto (Definition of Done)

- CRUD de Palestra (com palestrantes/diretor por papel, assuntos, destaques, mídia), Palestrante (foto/bio) e Assunto (hierárquico) no `/admin`.
- Cardinalidade 1–2/0–1 garantida antes de salvar; HTML sanitizado; `cor_fundo` validada.
- `php artisan test` verde + verificação manual no localhost.
- Fase 1 concluída (banco → importação → admin → front, com as 123 palestras).
