# Spec — Módulo "Eventos"

> Autoria: Thiago Mourão — https://github.com/MouraoBSB — 2026-07-08
> Design de front: handoff hi-fi em `design_handoff_eventos/` (documento `Eventos - Handoff CEMA.dc.html`,
> protótipo navegável e screenshots `01`–`07-eventos.png`).
> Estrutura legada: exports JetEngine em `design_handoff_eventos/Estrutura Site antigo wordpress/`
> **confirmados ao vivo** contra o banco `legado` (read-only) do WordPress atual.

## 1. Contexto e objetivo

**Eventos** são todas as atividades ligadas à casa (brechós, feirões de livros, encontros de
família, campanhas, cursos, e também reuniões internas). No WordPress atual é o CPT `_evento`
(JetEngine), com página de arquivo `/_evento/` e singles `/_evento/{slug}/`.

Este módulo recria e **incrementa** essa área na stack nova (**PHP 8.3 · Laravel 13 · Filament 5 ·
Livewire/Blade SSR · Tailwind v4**), seguindo o **mesmo padrão vertical** dos módulos já entregues
(Palestras, Blog, Agenda): migrations → admin Filament → páginas públicas SSR → importação
idempotente do legado. O análogo direto é **Palestras** (evento com data/hora): dele
reaproveitamos ICS, "Adicionar ao Google Calendar", SEO `schema.org/Event`, rotas datadas + 301
e a camada `App\Support\` de lógica pura.

**Entrega:** (a) **arquivo `/eventos`** — hero, "Próximo destaque", abas *Próximos × Já
aconteceram*, busca por título, filtro por mês, chips de categoria e grade de cards com selos de
categoria e de contagem regressiva; (b) **single `/eventos/{slug}`** — hero com selos, barra de
compartilhar, card lateral *sticky* com flyer, bloco "Serviço", galeria opcional e "Outros
eventos"; (c) **Admin** (CRUD de Evento + Categoria; cor/ícone em Departamento); (d) **importação**
dos 54 eventos do legado; (e) **visibilidade por papel** (público × 3 níveis restritos).

## 2. Decisões travadas (com o dono)

**Produto**
- **Taxonomia em dois eixos.** (1) **Categoria** — *tipo* de evento, legível e colorido, é o
  **filtro público**: `Brechó Solidário`, `Feirão de Livros`, `Encontro & Família`, `Campanha`,
  `Estudo & Curso`. (2) **Departamento** — *quem organiza*, reaproveita a tabela existente
  (`departamentos`), aparece só no bloco "Serviço" (siglas DED/DEPRO/…). Os dois são
  independentes.
- **Data/hora em campos separados:** `data_inicio` (obrigatória), `hora_inicio` (opcional),
  `data_fim` (opcional → assume `data_inicio`), `hora_fim` (opcional). **Sem `hora_inicio` = "dia
  inteiro"** (substitui o antigo switch `mostrar_horario`). `data_fim > data_inicio` = evento de
  vários dias. Sem `hora_fim` = duração padrão **2h** no Google Calendar/.ics.
- **Visibilidade por papel:** `publico` (qualquer visitante) · `logados` (qualquer conta =
  frequentador+) · `trabalhadores` (trabalhador + diretoria) · `diretoria` (só diretoria).
  Cumulativo por nível; **administrador (nível 100) sempre vê tudo**. Eventos restritos ficam
  **fora** do sitemap e do feed `.ics` público e retornam **404** (não 403) para quem não pode
  ver (não vazar existência).
- **Galeria mantida:** flyer (capa, 1) como destaque **+** galeria opcional (N fotos) exibida na
  single só quando houver. Importa as 11 galerias legadas.
- **Inscrições:** apenas **ponto de extensão** no design (ver §12) — **sem código nesta fase**.
- **URL limpa:** `/eventos` e `/eventos/{slug}`; **301** de `/_evento` e `/_evento/{slug}`.

**Regras de sempre** (CLAUDE.md): pt-BR em tudo; tokens Tailwind v4 (`@theme`); **nunca**
`migrate:fresh`/`refresh`/`wipe`/`reset`/seed destrutivo (só `migrate` incremental); **Pint**
antes do push; testes (`docker compose exec -T app php artisan test`) + conferência real no
`localhost`; segredos só no `.env`; cabeçalho de autoria nos arquivos novos relevantes; leitura
**somente** no legado (SELECT).

## 3. Fonte no legado (o que a introspecção ao vivo revelou)

CPT **`_evento`** (com underscore; `rewrite_slug=_evento`, `has_archive`, `with_front=0`).
Timezone do site: `America/Sao_Paulo`. **54 posts `publish`** (+1 rascunho, +1 lixeira, fora de
escopo). Faixa de datas dos eventos: 2023-12-02 → 2026-06-27.

**Campos nativos (cobertura sobre 54):** `post_title` 54/54 · `post_content` (corpo HTML) 49/54
(~2.000 chars médios) · `post_excerpt` (resumo) **33/54** · `post_name` (slug) 54/54 · `post_date`
= data de publicação (**≠** data do evento).

**Meta de domínio (`wp_postmeta`) — a migrar:**

| meta_key | Cobertura | Formato | Destino |
|---|---:|---|---|
| `data_do_evento` | 54/54 | **Unix timestamp** (`is_timestamp`); ⚠️ **13 eventos têm a linha duplicada** | `data_inicio` + `hora_inicio` |
| `local` | 54/54 | texto livre e "sujo" ("CEMA", "on-ine" [sic], endereços) | `local` |
| `evento_publico` | 54/54 | flag **suja** (`true`=36, `on`=11, ``=5, `false`=2) | `visibilidade` |
| `mostrar_horario` | 28/54 | flag **suja** (`true`/`on`/``) | controla se `hora_inicio` é preservada |
| `_thumbnail_id` | 48/54 | id de attachment | mídia coleção `flyer` |
| `_galeria-de-imagens` | **11/54** | **CSV de ids** de attachment | mídia coleção `galeria` |
| `_descricao_evento` | 3/54 | descrição curta | descartável (corpo real = `post_content`) |

**Taxonomia (uma só): `_departamentos_tax`** — 49/54 eventos classificados, **N:N** (67 vínculos;
alguns eventos em vários departamentos; **5 sem departamento**). Termos **planos** (`parent=0`),
casando com a tabela `departamentos` nova por **sigla**:

| term_id legado | sigla | eventos |
|---:|---|---:|
| 225 | DED | 16 |
| 229 | DEPRO | 13 |
| 223 | DDA | 12 |
| 224 | DIJ | 11 |
| 228 | DEPAE | 9 |
| 226 | DAS | 4 |
| 227 | DEMAPA | 2 |

(DECOM não aparece nos eventos. A meta espelhada `jet_tax___departamentos_tax` é redundante e
menos coberta — **usar a taxonomia canônica** via `wp_term_relationships`, não a meta.)

**Sem relações Jet** (`wp_jet_rel_107/108` não referenciam eventos) e **sem nível-de-acesso** no
legado: a visibilidade por papel é **incremento novo** (o legado só tinha o binário
`evento_publico`). **Não há campo de categoria** no legado — o eixo Categoria é novo e será
inferido na importação (§5).

**Ruído a descartar:** `rank_math_*`/`_yoast_*` (SEO fragmentado, baixa cobertura),
`_elementor_*`, `_edit_*`, `_oembed_*`, contadores de curtida/pageview.

## 4. Modelo de dados (migrations aditivas + models, domínio pt-BR)

> Migrations **aditivas**; conferir tabelas existentes antes; **nunca** `migrate:fresh`.

### `categorias` — o eixo público (novo; catálogo CRUD, molde de `departamentos`)
| Coluna | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| nome | string | "Brechó Solidário" |
| slug | string unique | `brecho` |
| cor | string(7) | hex do selo (ex.: `#89AB98`) |
| cor_texto | string(7) null | override p/ contraste (Campanha usa `#3a3266`) |
| icone | string null | opcional |
| ordem | smallint unsigned | default 0 |
| ativo | boolean | default true |
| timestamps | | |

**Seed** (`updateOrCreate` por slug): `brecho` `#89AB98` · `feirao` `#6E9FCB` · `familia`
`#E79048` · `campanha` `#F2A81E` (`cor_texto=#3a3266`) · `estudo` `#4E4483`.

### `eventos` — a entidade
| Coluna | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| titulo | string | |
| slug | string unique | idempotência/URL |
| resumo | text null | excerpt/SEO (card + hero) |
| conteudo | longText null | corpo HTML **sanitizado** (`clean($v,'conteudo')` no setter) |
| data_inicio | date | **obrigatório**; mutator `Attribute` ↔ `Y-m-d` (portável) |
| hora_inicio | string(5) null | `HH:MM`; **null = dia inteiro** (não divulga hora) |
| data_fim | date null | mutator `Y-m-d`; null → assume `data_inicio` |
| hora_fim | string(5) null | `HH:MM` |
| local | string null | texto livre |
| categoria_id | FK null → `categorias` | `nullOnDelete` (evento interno pode não ter categoria pública) |
| visibilidade | string | `publico`\|`logados`\|`trabalhadores`\|`diretoria` (default `publico`) |
| status | string | `publicado`\|`rascunho` (default `publicado`) |
| wp_id | unsigned bigint null | id legado (rastreio + 301); `unique` |
| timestamps | | |

**Índices:** `slug` unique · `wp_id` unique · `data_inicio` · `data_fim` · `status` · `visibilidade` ·
`categoria_id`.

> **Por que `string(5)` para horas** e não `time`/cast: o projeto exige portabilidade
> SQLite(testes)×MySQL(prod) (memória `padrao-data-mutator-portavel`). Guardar `HH:MM` como string
> normalizada evita as divergências de tipo `TIME`/cast entre os bancos; a hora é dado de
> **exibição** e de composição do datetime do `.ics`, não chave de ordenação (ordena-se por
> `data_inicio`). Datas usam o **mutator `Attribute`** (get→Carbon, set→`Y-m-d`), como em `AgendaDia`.

### `departamento_evento` — pivot N:N (evento ↔ departamento)
`evento_id` (FK cascade) · `departamento_id` (FK cascade) · `unique(evento_id, departamento_id)`.
N:N porque o legado tem eventos multi-departamento (sem perda de dado). No "Serviço" listam-se
todos os departamentos do evento.

### Alteração em `departamentos`
Adicionar `cor` (string(7) null) e `icone` (string null) — o legado tinha `tax-color`/`_tax_icon`;
a tabela atual não tem. Alimenta chips/etiquetas por departamento na UI e no admin.

### Mídia (spatie/laravel-medialibrary, trait `RegistraImagensPadrao`)
Coleções no `Evento`: **`flyer`** (capa/destaque, single) · **`galeria`** (N, ordenável) · **`og`**.
Conversões WebP `web`/`thumb` automáticas; original capado (padrão do projeto).

### Models
- **`App\Models\Evento`** — `const STATUS_*`, `const VISIBILIDADE_*` (ou via enum, abaixo);
  mutators `data_inicio`/`data_fim` (Carbon↔`Y-m-d`); setters de `conteudo`/`resumo` sanitizam
  (`clean($v,'conteudo')`); implementa `HasMedia` + `RegistraImagensPadrao`. Relações:
  `categoria()` (belongsTo), `departamentos()` (belongsToMany via `departamento_evento`,
  `withTimestamps`). Scopes: `publicado()`, `visiveisPara(?User)` (§7), `proximos()`/`anteriores()`
  (por `data_fim` coalescido a `data_inicio` vs `now()`). Accessors de apresentação: `periodo`
  (→ `PeriodoEvento`), `status` (→ `StatusEvento`), `flyerUrl`, `ehPassado`. Método de
  autorização `podeSerVistoPor(?User): bool` (fonte única, §7).
- **`App\Models\Categoria`** — fillable (`nome`,`slug`,`cor`,`cor_texto`,`icone`,`ordem`,`ativo`);
  scope `ativo()`; `hasMany(Evento)`.
- **`App\Models\Departamento`** (existente) — adicionar `cor`/`icone` ao `$fillable` e a relação
  `eventos()` (belongsToMany).

## 5. Importação (`cema:importar-eventos`) — molde do importador de Palestras/Agenda

Espelha o pipeline existente (interface + leitor MySQL + importador + command + bind em
`AppServiceProvider`). **Somente SELECT** na conexão `legado`.

- **`app/Importacao/LeitorEventos.php`** (interface): `public function eventos(): array;`
- **`app/Importacao/LeitorEventosMysql.php`**: `DB::connection('legado')` no construtor; SELECTs
  read-only com placeholders. Uma linha por post `_evento`/`publish`, já normalizada:
  `['wp_id','titulo','slug','resumo','conteudo','post_date', 'data_do_evento'(unix, **dedup** via
  MAX/DISTINCT — 13 duplicadas), 'mostrar_horario','evento_publico','local','thumbnail_id',
  'galeria_ids'(csv), 'departamentos'(siglas via wp_term_relationships/wp_term_taxonomy)]`.
  Achata `wp_postmeta` (`metasDe($id)` no molde de Palestras).
- **`app/Importacao/ClassificadorCategoria.php`** — heurística **pelo título** → slug de categoria:
  `Brechó`→`brecho` · `Feirão`/`Livros`→`feirao` · `Encontro`/`Família`→`familia` ·
  `Campanha`→`campanha` · `Curso`/`Estudo`/`CEMART`→`estudo`. Sem match → `null` (revisão manual no
  admin) + **aviso** no log. (Determinístico e testável isoladamente.)
- **`app/Importacao/ImportadorEventos.php`**:
  - `Evento::updateOrCreate(['slug'=>...], [...])` — **idempotente** (upsert por slug; `wp_id`
    também `unique`). Rodar 2× não duplica.
  - **`data_do_evento`** (unix) → `data_inicio` (`Y-m-d`) + `hora_inicio` (`H:i`) no TZ
    `America/Sao_Paulo`. `data_fim`/`hora_fim` ficam **null** (não existem no legado).
  - **`mostrar_horario`** normalizado (`true`/`on`→true; ``/`false`→false); quando **false**,
    `hora_inicio = null` (não divulga a hora — coerente com "dia inteiro").
  - **`evento_publico`** normalizado → `visibilidade`: `true`/`on` → `publico`; ``/`false` →
    **`logados`** (padrão; dono revisa os ~7 casos no admin) + aviso.
  - **`local`** → `local` (texto livre; sem geocodificar/parsear).
  - **Categoria** ← `ClassificadorCategoria` (resolve `categoria_id` por slug).
  - **Departamentos** ← `sync()` por **sigla** contra `departamentos` (49/54; **loga** siglas não
    resolvidas — não cria às cegas; DECOM ausente é esperado).
  - **Mídia:** `_thumbnail_id` → coleção `flyer` (48/54); `_galeria-de-imagens` → coleção `galeria`
    (explode CSV preservando ordem; 11/54). Resolver/baixar os attachments do legado no padrão já
    usado por Blog/Palestras (dedup por SHA-256 quando aplicável).
  - `DB::transaction` por evento; `avisos[]` no resumo (categoria não inferida, depto não
    resolvido, não-público mapeado, dedup de timestamp).
- **`app/Console/Commands/ImportarEventos.php`**: `signature='cema:importar-eventos'`;
  `handle(LeitorEventos $leitor, ImportadorEventos $importador)`; **guarda de túnel** só se
  `$leitor instanceof LeitorEventosMysql` (`DB::connection('legado')->getPdo()` em try/catch com
  dica do `ssh -L`); callback de log + resumo/avisos.
- **Bind** da interface em `AppServiceProvider::register()` (→ `LeitorEventosMysql`), como os demais
  `cema:importar-*`.

## 6. Arquitetura do front (SSR-first, crawlável)

### 6.1 Rotas (`routes/web.php`) — estáticas antes de `{slug}`
```php
Route::get('/eventos', [EventoController::class, 'index'])->name('eventos.index');
Route::get('/eventos/calendario.ics', [EventoController::class, 'feed'])->name('eventos.feed-ics');
Route::get('/eventos/{slug}', [EventoController::class, 'show'])
    ->name('eventos.show')->where('slug', '[a-z0-9-]+');
Route::get('/eventos/{slug}/calendario.ics', [EventoController::class, 'calendario'])
    ->name('eventos.evento-ics')->where('slug', '[a-z0-9-]+');

// 301 de compatibilidade das URLs antigas do WP
Route::permanentRedirect('/_evento', '/eventos');
Route::get('/_evento/{slug}', function (string $slug) {
    $evento = \App\Models\Evento::where('slug', $slug)->first();
    abort_if($evento === null, 404);
    return redirect()->route('eventos.show', $evento->slug, 301);
})->where('slug', '[a-z0-9-]+');
```

### 6.2 Controller `App\Http\Controllers\EventoController`
- `index()` — renderiza a casca `eventos/index.blade.php` que embute o componente Livewire
  `Eventos\Lista`. Passa o **próximo destaque** (§6.3) e o payload de SEO (BreadcrumbList).
- `show(string $slug)` — `Evento::publicado()->where('slug',$slug)->firstOrFail()`; **autoriza**
  (`abort_unless($evento->podeSerVistoPor(auth()->user()), 404)` — 404, não 403). Carrega
  anterior/próximo cronológicos e **relacionados** (mesma categoria; futuros primeiro, depois por
  proximidade; fallback para quaisquer futuros) já **filtrados por visibilidade**. Envia headers
  `Cache-Control: private` quando o evento é restrito.
- `feed()` — `.ics` agregado **só de eventos públicos e não encerrados** (`visibilidade=publico`
  + `data_fim>=hoje`); `Content-Type: text/calendar; charset=utf-8`; `?download=1` →
  `Content-Disposition: attachment`.
- `calendario(string $slug)` — `.ics` de **um** evento (autoriza igual ao `show`; restrito só
  serve a quem pode); sempre `attachment` `evento-{slug}.ics`.

### 6.3 Componente `App\Livewire\Eventos\Lista`
Filtros sincronizados na URL (`#[Url]`): `q` (busca por título), `mes` (`AAAA-MM`), `categoria`
(slug), `aba` (`proximos`|`anteriores`). Query **sempre** via `Evento::visiveisPara(auth()->user())`.
- **Próximo destaque:** o próximo evento **futuro** visível mais próximo (`data_fim>=hoje`,
  menor `data_inicio`), **independente dos filtros** (hero fixo do topo). É calculado **no
  controller** (`index()`) e seu `id` é passado ao componente como prop `destaqueId`; a query da
  aba "Próximos" faz `->where('id','!=',$destaqueId)` para **não duplicar** o destaque na grade.
- **Abas:** "Próximos" (`data_fim>=hoje`, ordem **crescente**) × "Já aconteceram"
  (`data_fim<hoje`, ordem **decrescente**).
- **Busca:** `titulo LIKE %q%` (case-insensitive) sobre a aba ativa.
- **Mês:** `<select>` populado dos meses existentes (aba ativa).
- **Categoria:** chips (seleção única; "Todas" limpa).
- **Estado vazio:** "Nenhum evento encontrado".

### 6.4 Views (Tailwind v4 + tokens; molde de `palestras/index`+`show`)
- **`eventos/index.blade.php`** — casca: `<x-layout.app :title="'Eventos — CEMA'"
  :description="...">`; hero roxo (`bg-gradient-to-br from-primary to-footer-bg`, kicker font-mono
  "PROGRAMAÇÃO DO CEMA", H1 "Eventos"); bloco "Próximo destaque" (card grande, raio 22px, 2
  colunas, CTAs "Ver evento" + "Adicionar à agenda"); `@livewire('eventos.lista')`;
  `<x-slot:head>` com JSON-LD **`BreadcrumbList`**.
- **`livewire/eventos/lista.blade.php`** — barra de filtros (abas com `role=tab` + sublinhado
  `bg-gold`; busca pílula; select de mês; chips de categoria; contador font-mono) + grade
  `grid auto-fill minmax(290px,1fr)` de **cards** (parcial `_card`).
- **`eventos/_card.blade.php`** — `<article>` cujo conteúdo é um `<a>` acessível para a single;
  faixa do flyer (WebP, `loading=lazy`) com **selo de categoria** (cor da categoria) e **selo de
  status** (`StatusEvento`); título + metadados (período/local com ícones stroke verde); hover
  eleva (`-translate-y-1` + sombra). Passados: flyer em grayscale + selo "Encerrado".
- **`eventos/show.blade.php`** — hero com breadcrumb multinível + par de selos (categoria +
  status); barra de ações (Facebook `sharer.php?u=`, WhatsApp `wa.me/?text=`, "Copiar link", e
  **"Adicionar à agenda"** = link Google Calendar `render?action=TEMPLATE&text=…&dates={ini}/{fim}
  &details={url}&location={local}`); corpo 2 colunas (`lead` + parágrafos à esquerda; **card
  `sticky top-[90px]`** com flyer + período/local + CTAs à direita); bloco **"Serviço"**
  (parcial `_servico`: período, horário, local, endereço da sede [constante], categoria,
  departamento(s)); **galeria** (parcial `_galeria`, só `@if` houver); **"Outros eventos"**
  (parcial `_relacionados`, até 3); `<x-slot:head>` com JSON-LD **`Event`** (+ `noindex` se
  restrito). Modal **assinar** (`eventos/assinar-modal`) reaproveitado de Palestras (Google/Apple/
  download a partir de `route('eventos.feed-ics')`).
- **`resources/css/eventos.css`** (import em `app.css`) — estilos auxiliares (selos, grayscale,
  sticky, pulse do "Próximo destaque").
- **Endereço da sede** como constante institucional (config/constante):
  `Quadra 02, Lote 16, Vila Vicentina, Planaltina-DF`.

## 7. Visibilidade / autorização (infra nova — hoje não existe no front)

Reaproveita `roles.nivel` (persistido: `frequentador=10`, `trabalhador=20`, `diretor=30`,
`administrador=100`).

- **`App\Enums\VisibilidadeEvento`** (string enum): `Publico` (nível exigido 0), `Logados` (10),
  `Trabalhadores` (20), `Diretoria` (30). Métodos `nivelMinimo()`, `rotulo()`, `cor()`.
- **`App\Models\User::nivelMaximo(): int`** — `(int) $this->roles->max('nivel')` (0 se anônimo).
- **`Evento::podeSerVistoPor(?User $u): bool`** — fonte única:
  ```
  publico        → true (mesmo anônimo)
  logados        → $u !== null
  trabalhadores  → $u && $u->nivelMaximo() >= 20
  diretoria      → $u && $u->nivelMaximo() >= 30
  (administrador nível 100 satisfaz qualquer >=; sempre vê)
  ```
- **`Evento::scopeVisiveisPara(?User $u)`** — aplica a mesma regra **no banco** (WHERE por
  `visibilidade` conforme `nivelMaximo`), para listagens/Livewire/feeds **não vazarem títulos** de
  eventos restritos.
- **`App\Policies\EventoPolicy`** (`view`/`viewAny`) — auto-descoberta por convenção (Laravel 11+);
  usar `@can('view',$evento)` nas views onde fizer sentido.
- **Efeitos transversais:** restritos **fora** do `sitemap.xml`, **fora** do feed `.ics` público,
  **404** para quem não pode (não 403), `Cache-Control: private` na single restrita, `noindex` no
  `<head>`. Guard `web`.

## 8. Admin (Filament 5)

Em `app/Filament/Resources/Eventos/`, molde `PalestraResource` (cabeçalho de autoria;
`form(Schema)`, `table(Table)`, `getPages()`; painel gateado por `User::canAccessPanel`).

- **`EventoResource`** — form em **Tabs**:
  - *Conteúdo*: `titulo`, `slug` (auto via `afterStateUpdated` só no create), `resumo`, `conteudo`
    (RichEditor), **flyer** (SpatieMediaLibraryFileUpload coleção `flyer`), **galeria**
    (coleção `galeria`, `reorderable`, múltiplo).
  - *Data & Local*: `DatePicker data_inicio` (`->native(false)->displayFormat('d/m/Y')->required()`),
    `TimePicker hora_inicio` (`->seconds(false)`, nullable), `DatePicker data_fim` (nullable),
    `TimePicker hora_fim` (nullable), `TextInput local`.
  - *Classificação*: `Select categoria_id` (relação, com badge de cor), `Select departamentos`
    (multiple, relação).
  - *Publicação*: `Select status`, `Select visibilidade` (enum `VisibilidadeEvento`).
  - **Validação de período** via `App\Support\Eventos\PeriodoEvento::erros($data)` (classe pura,
    chamada em `mutateFormDataBeforeCreate/Save` **e** server-side): `data_fim >= data_inicio`;
    `hora_fim > hora_inicio` quando mesmo dia; formato `HH:MM`. (Espelha o papel de
    `CardinalidadePalestra` no molde de Palestras.)
  - Tabela: coluna **flyer** (thumb), **período** (via accessor), **categoria** (badge colorido),
    **departamentos**, **status** (badge), **visibilidade** (badge); filtros por
    categoria/departamento/status/visibilidade; `defaultSort('data_inicio','desc')`.
- **`CategoriaResource`** — CRUD simples (nome, slug auto, `ColorPicker cor`, `cor_texto`, `icone`,
  `ordem`, `ativo`); ordenação por `ordem`.
- **`DepartamentoResource`** (existente) — acrescentar `ColorPicker cor` e `icone` ao form.

## 9. Camada `App\Support\Eventos\` (lógica pura, testável) + `App\Enums`

- **`StatusEvento`** — a partir de `data_inicio`/`data_fim` vs hoje (TZ Brasília): retorna
  `estado` (`futuro`/`acontecendo`/`passado`), `rotulo` do selo e `cor`:
  - `hoje > data_fim` → **"Encerrado"** (roxo translúcido) · `data_inicio <= hoje <= data_fim`
    (multi-dia em curso) → **"Acontecendo agora"** (`#C33A36`) · dias==0 → **"É hoje"** (`#C33A36`)
    · dias==1 → **"É amanhã"** (`#E79048`) · 2–7 → **"Faltam N dias"** (`#E79048`) · >7 →
    **"Em N dias"** (`#89AB98`). (Regras do design §06, estendidas para intervalos.)
- **`PeriodoEvento`** — `formata(Evento): string` ("27 de junho de 2026 · 8h30–12h" / "27 a 29 de
  junho de 2026" / "30 de junho a 2 de julho" / "dia inteiro") + `erros(array $dados): array`
  (validação usada no Filament).
- **`FeedIcs`** — clone de `App\Support\Palestras\FeedIcs`: `escapar()`, `dobrar()` (line-folding
  UTF-8), `vevento(Evento)` (`UID: evento-{id}@cemanet.org.br`; `DTSTART`/`DTEND` reais — **dia
  inteiro → `VALUE=DATE`** cobrindo o intervalo; com hora → UTC `Ymd\THis\Z`, `hora_fim` ou +2h;
  multi-dia respeita `data_fim`), `documento(iterable)` (`VCALENDAR` + `X-WR-CALNAME`/`TIMEZONE`).
- **`App\Enums\VisibilidadeEvento`** — §7.

## 10. SEO · performance · A11y

- **JSON-LD `schema.org/Event`** no single (`name`, `startDate`/`endDate` ISO-8601 compostos de
  data+hora, `eventStatus`, `location` Place [nome + endereço da sede], `organizer` Organization,
  `image` = flyer). `BreadcrumbList` no archive. Sempre
  `JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_HEX_TAG`. Molde: `palestras/show.blade.php`.
- **`title`/`description`/canonical** via layout + `<x-slot:head>`; **OG** já coberto pelo layout
  (não duplicar `og:type`/`og:url`); `og:image` = flyer.
- **Tokens Tailwind v4:** reconciliar no `@theme` os tokens do handoff de Eventos ausentes na raiz
  — **`gold #F2A81E`**, **`footer-bg #2F2952`**, **`text-ink #26242E`** e a família **Roboto Mono**
  (eyebrows/metadados/selos). A cor da categoria vem do banco (`categorias.cor`), aplicada inline.
- **Mobile-first:** grades `repeat(auto-fit/auto-fill, minmax(...,1fr))` colapsam para 1 coluna;
  tipografia fluida `clamp()`; filtros `flex-wrap`; card sticky segue o fluxo no mobile.
- **A11y:** cards como `<a>` com rótulo; abas `role=tab`/`tablist` + foco visível; `aria-label` nas
  ações; contraste (Campanha usa `cor_texto` escura); `prefers-reduced-motion` (já no `app.css`).
- **Performance:** HTML enxuto (bem abaixo do ~0,5 MB/página do WP atual); flyers WebP + `lazy`;
  status calculado no **servidor** (SSR), não só em JS; zero fetch externo.
- **Sitemap:** `SitemapController` inclui `eventos.index` + as singles **públicas** (com `lastmod`);
  restritos **excluídos**.

## 11. Testes (PHPUnit + `RefreshDatabase`, nomes pt-BR)

- **Importação** (`tests/Feature/Importacao/`): `ImportadorEventosTest` com leitor fake (classe
  anônima), rodando **2×** (idempotência), cobrindo **dedup do timestamp duplicado**, mapeamento de
  `evento_publico` sujo → visibilidade, `mostrar_horario=off` → `hora_inicio` null, resolução de
  departamentos por sigla (e aviso p/ não resolvido), explosão do CSV da galeria, e
  `ClassificadorCategoriaTest` (título → slug, incl. sem-match → null). `ImportarEventosCommandTest`
  rebinda a interface.
- **Visibilidade** (`tests/Feature/Front/`): cada nível vê/não vê (matriz anônimo/frequentador/
  trabalhador/diretor/admin × 4 visibilidades); **404** (não 403) para anônimo em restrito;
  restrito **fora** do `/sitemap.xml` e do feed `.ics`; `scopeVisiveisPara` não retorna títulos
  restritos; `Cache-Control: private` na single restrita.
- **Archive:** "Próximo destaque" é o futuro mais próximo e **não** duplica na grade; abas
  particionam e ordenam; busca/mês/categoria filtram; estado vazio.
- **Single:** relacionados (mesma categoria, futuros primeiro, fallback); restrito → 404;
  período/serviço renderizados; galeria só quando há fotos.
- **SEO:** JSON-LD `Event`/`BreadcrumbList` presentes; `og:type`/`og:url` **não** duplicados;
  `noindex` em restrito; URLs públicas no sitemap.
- **ICS:** `FeedIcsTest` (VEVENT com hora × dia inteiro `VALUE=DATE` × multi-dia; `hora_fim`
  ausente → +2h); feed agregado só públicos futuros; `.ics` de restrito só serve a quem pode.
- **Unit:** `StatusEventoTest` (todos os selos, incl. "Acontecendo agora"); `PeriodoEventoTest`
  (formatos + `erros()`).
- **301:** `/_evento` e `/_evento/{slug}` → novas URLs (`assertRedirect` + `assertStatus(301)`).
- **Verificação manual** no `localhost` (admin: criar evento multi-dia, dia inteiro, restrito;
  front: destaque, abas, filtros, Google Calendar, galeria, restrito 404 anônimo). **Pint** antes
  do push; suíte completa no container.

## 12. Fora de escopo agora (ponto de extensão preparado)

- **Inscrições/RSVP** — quando priorizado: `eventos.exige_inscricao` (bool) + tabela
  `inscricoes` (`evento_id`, `user_id`, `status`, dados do inscrito, `unique(evento_id,user_id)`) +
  fluxo público (form + e-mail transacional) + lista no admin. O modelo atual não cria nada disso
  (evita código sem uso); a adição é aditiva e localizada.
- Comentários, geocodificação do `local`, migração de SEO por-evento do Rank Math/Yoast (baixa
  cobertura), segundo eixo de taxonomia além de Categoria/Departamento.

## 13. Divergências deliberadas do handoff (transparência)

1. **Galeria mantida** (o handoff só previa flyer) — decisão do dono; exibida só quando houver.
2. **Categoria = tabela CRUD** (não enum fixo) — editável pelo dono, com cor/ícone, no idioma de
   `departamentos`.
3. **Departamento N:N** (o "Serviço" do protótipo sugeria 1) — preserva os eventos multi-departamento
   do legado (sem perda).
4. **Selo "Acontecendo agora"** acrescentado para eventos de vários dias em curso (o design era
   de data única).
5. **Visibilidade por papel** (novo) estende o binário `evento_publico` do legado; não-público
   legado → `logados` (revisável).
6. **URL `/eventos`** (o legado usava `/_evento/`) com 301.

## 14. Ordem de implementação (sugestão / faseamento)

1. **Dados + Admin.** Migrations (`categorias`, `eventos`, `departamento_evento`, alter
   `departamentos`) + models + seed de categorias + `VisibilidadeEvento` + `EventoResource`/
   `CategoriaResource` (+ cor/ícone no `DepartamentoResource`). Suporte `PeriodoEvento` (validação).
   Conferir tabelas existentes antes; só `migrate` incremental.
2. **Importador.** `LeitorEventos`/`LeitorEventosMysql` + bind + `ClassificadorCategoria` +
   `ImportadorEventos` + `cema:importar-eventos` + testes. Rodar a importação e **revisar avisos**.
3. **Front público.** Rotas (+301), `EventoController`, `Eventos\Lista`, casca + parciais
   (`_card`/`_servico`/`_galeria`/`_relacionados`), `eventos.css`, `FeedIcs` + `StatusEvento`,
   Google Calendar + modal assinar.
4. **Visibilidade + SEO + polish + testes.** `VisibilidadeEvento`/`podeSerVistoPor`/
   `scopeVisiveisPara`/`EventoPolicy`/`User::nivelMaximo`; JSON-LD `Event`; tokens Tailwind
   (`gold`/`footer-bg`/Roboto Mono); sitemap; suíte completa + conferência no `localhost`; Pint;
   branch/commit.

## 15. Arquivos a criar/editar (mapa)

**Criar:**
`database/migrations/*_create_categorias_table.php`, `*_create_eventos_table.php`,
`*_create_departamento_evento_table.php`, `*_add_cor_icone_to_departamentos_table.php`;
`database/seeders/CategoriaSeeder.php` (ou dentro do `EstruturaCemaSeeder`);
`app/Models/Evento.php`, `app/Models/Categoria.php`;
`app/Enums/VisibilidadeEvento.php`;
`app/Support/Eventos/StatusEvento.php`, `PeriodoEvento.php`, `FeedIcs.php`;
`app/Policies/EventoPolicy.php`;
`app/Importacao/LeitorEventos.php`, `LeitorEventosMysql.php`, `ImportadorEventos.php`,
`ClassificadorCategoria.php`; `app/Console/Commands/ImportarEventos.php`;
`app/Http/Controllers/EventoController.php`; `app/Livewire/Eventos/Lista.php`;
`app/Filament/Resources/Eventos/EventoResource.php` (+ Pages), `Categorias/CategoriaResource.php`
(+ Pages);
`resources/views/eventos/index.blade.php`, `show.blade.php`, `_card.blade.php`, `_servico.blade.php`,
`_galeria.blade.php`, `_relacionados.blade.php`, `livewire/eventos/lista.blade.php`,
`components/eventos/assinar-modal.blade.php`; `resources/css/eventos.css`;
testes em `tests/Feature/Importacao/`, `tests/Feature/Front/`, `tests/Unit/Support/Eventos/`.

**Editar:**
`routes/web.php` (rotas + 301); `app/Providers/AppServiceProvider.php` (bind `LeitorEventos`);
`app/Models/Departamento.php` (fillable `cor`/`icone` + `eventos()`);
`app/Models/User.php` (`nivelMaximo()`); `app/Filament/Resources/Departamentos/DepartamentoResource.php`
(cor/ícone); `app/Http/Controllers/SitemapController.php` + `resources/views/sitemap.blade.php`
(eventos públicos); `resources/css/app.css` (`@import 'eventos.css'` + tokens `@theme`);
`config/navegacao.php` (ativar item "Eventos" → `eventos.index`);
`ROADMAP.md`/`DATA-MODEL.md`/`DB-LEGADO.md` (registrar a fatia ao concluir).
