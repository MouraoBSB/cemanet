# Spec — Módulo "Agenda Reforma Íntima"

> Autoria: Thiago Mourão — https://github.com/MouraoBSB — 2026-07-02
> Design de front: handoff hi-fi em `design_handoff_agenda_reforma_intima/` (protótipo + screenshots).
> Estrutura de dados: introspecção **ao vivo** do banco `legado` (read-only) do WordPress atual.

## 1. Contexto e objetivo

A **Agenda Reforma Íntima** é um **devocional diário** produzido anualmente pela Editora
Auta de Sousa: **uma entrada por data de calendário**, com quatro blocos de leitura
(Reflexão do Evangelho · Meta do Mês · Meta do Dia · Sugestão de Prece). É uma das páginas
de **maior tráfego** do site — por isso **SEO é requisito de fundação**, não acabamento.

Este módulo recria a experiência do WordPress atual (`cemanet.org.br/agenda-reforma/`) na
stack nova (**PHP 8.3 · Laravel 13 · Filament 5 · Livewire/Blade SSR · Tailwind v4**),
seguindo o **mesmo padrão vertical** dos módulos já entregues (Palestras, Blog):
migrations → admin Filament → páginas públicas SSR → importação idempotente do legado.

Entrega: **uma página** com (a) o **conteúdo do dia** em card, (b) um **calendário mensal
navegável**, (c) **compartilhamento**, (d) seções "Sobre o projeto" e "Veja também".

## 2. Decisões travadas (com o dono)

**Produto**
- **Fuso do visitante:** o "dia de hoje" acompanha o **fuso do navegador** (vira à
  meia-noite local de cada um). O servidor renderiza o dia de **Brasília** como padrão
  estável (SSR/robôs); um script mínimo, só na URL nua, **navega** para a URL datada local.
- **Futuro liberado:** todo dia com conteúdo é legível a qualquer momento (passado, hoje
  ou futuro). Dias sem conteúdo ficam **inertes** (sem link). **Sem** a "janela de
  publicação" que o handoff descrevia (essa regra foi descartada).
- **URL de caminho** (não query string): `/agenda-reforma-intima/AAAA-MM-DD`. A **URL nua**
  `/agenda-reforma-intima` = "hoje" (evergreen, canônica de si mesma); cada **data** é
  canônica de si mesma.

**SEO (fundação — os 8 requisitos)**
1. **URL de caminho** por data (item acima).
2. **Navegação crawlável:** células do calendário (dias com conteúdo) e prev/next como
   `<a href>` **reais** para as URLs datadas; Livewire/Alpine só como *enhancement*
   (`wire:navigate`). Sem isso o Google indexaria só "hoje".
3. **JSON-LD por dia:** `CreativeWork`/`Article` (headline, datePublished, articleBody,
   `inLanguage: pt-BR`, author/publisher = CEMA) **+ `BreadcrumbList`**.
4. **Sitemap:** Agenda no `SitemapController` — todas as URLs datadas (com `lastmod`) + a
   URL nua. Obrigatório.
5. **`<title>` e `<meta name="description">` únicos por dia** (description = trecho da
   reflexão); **canonical por data**.
6. **Mapa de 301** dos URLs antigos do WP → URLs datadas novas (confirmado no legado).
7. **Fuso no cliente = navegação, não troca de conteúdo:** URL nua = hoje de Brasília no
   servidor; se o dia local diferir, o script **navega** para a URL datada (nunca troca o
   conteúdo da URL nua "por baixo").
8. **Conteúdo do dia SSR:** reflexão/meta/prece renderizados **no servidor**, nunca via JS.

**Regras de sempre** (CLAUDE.md): pt-BR em tudo; tokens Tailwind v4 (`@theme`); **nunca**
`migrate:fresh`/`refresh`/`wipe`/`reset`/seed destrutivo (só `migrate` incremental); **Pint**
antes do push; testes (`php artisan test`) + conferência real no `localhost`; segredos só
no `.env`; cabeçalho de autoria nos arquivos novos relevantes; leitura **somente** no
legado (SELECT).

## 3. Fonte no legado (o que a introspecção revelou)

CPT `agenda-reforma` (`wp_jet_post_types` id 164). Timezone do site: `America/Sao_Paulo`.
Permalink `/%postname%/`, `rewrite_slug=agenda-reforma`, `with_front=0`.

**Volume (snapshot — o legado está VIVO e muda):** ~123 posts válidos (`publish`+`future`),
**um por data**, cobrindo **01/05/2026 → 31/08/2026** (4 meses) + 1 rascunho ignorado. Entre
snapshots o banco se altera (agendados publicam; o dono edita/limpa — ex.: o "duplicado" de
05/08 foi para a **lixeira** entre duas leituras). **A importação de go-live é a que vale** e
precisa ser robusta a isso. Os 14 meta-fields têm cobertura completa nos posts válidos.

**Semântica confirmada (cardinalidade real sobre 124 posts):**

| Bloco / campo | meta_key | Cadência | Distintos | Destino |
|---|---|---|---:|---|
| Reflexão e Vivência (Evangelho) | `_reflexao` | diário | 123 | `agenda_dias.reflexao` |
| Meta do Mês — **título** | `_mes_titulo` | **fixo no mês** | 5 | `agenda_metas_mes.titulo` |
| Meta do Mês — citação | `_mes_texto` | **diário** | 119 | `agenda_dias.meta_mes_texto` |
| Meta do Dia — título | `_titulo_meta_dia` | dura vários dias | 18 | `agenda_dias.meta_dia_titulo` |
| Meta do Dia — texto | `_dia` | diário | 120 | `agenda_dias.meta_dia_texto` |
| Prece diária | `_prece` | diário | 83 | `agenda_dias.prece` |
| Data da agenda | `_dia_agenda` (unix) | 1/dia | — | `agenda_dias.data` (usar `DATE(post_date)`) |

> **Correção do handoff:** a citação da Meta do Mês (`_mes_texto`) é **diária**, não mensal.
> Só o **título** (`_mes_titulo`) é fixo por mês. E as **referências/autores vêm embutidas
> no HTML** (ex.: "(Lucas, 7:15)", "(Rodolfo Calligaris…)") — mantemos embutido (fiel;
> separar exigiria parsing frágil).

**Abandonados em 2026** (100% dos posts): `possui_imagem=false`, `possui_tema=false` →
`_imagem`, `_texto_imagem`, `_tema`, `possui_*` **fora do escopo** (não migram, não entram
no schema).

**Glossary (JetEngine) — só afeta MAIO:** 28 posts de maio guardam a **chave crua** em vez
do texto (`_mes_titulo='maio_2026'`; `_titulo_meta_dia='meta_dia_maio_2026_01..04'`).
Jun/Jul/Ago já têm texto puro. A importação **resolve** essas chaves (tabela fixa abaixo).
Os itens do glossary **não** ficam em tabela consultável do WP; usamos o mapa confirmado.

**Títulos mensais (confirmados nos dados):**
- `2026-05` → **"Desenvolver abnegação, renúncia e solidariedade"** (= resolução de
  `maio_2026`; confirmado por 3 posts de maio que já traziam o texto resolvido)
- `2026-06` → "Combater o egoísmo: indiferença e ingratidão"
- `2026-07` → "Combater o egoísmo: inveja, ciúme e maledicência"
- `2026-08` → "Desenvolver a caridade moral"

**Mapa de resolução de chaves (maio):**
```
maio_2026            → Desenvolver abnegação, renúncia e solidariedade
meta_dia_maio_2026_01 → Desenvolver Abnegação
meta_dia_maio_2026_02 → Desenvolver a Renúncia
meta_dia_maio_2026_03 → Desenvolver Renúncia no Lar
meta_dia_maio_2026_04 → Desenvolver a Solidariedade
```
(Os 4 `meta_dia_*` vêm do print do glossary do dono; o importador **loga** qualquer chave
`*_2026_*` não resolvida como aviso, para não gravar chave crua.)

**Qualidade de dados a tratar na importação:**
- **Slugs antigos MISTOS** (afeta o 301): maio (31 posts) tem slug **numérico** (= post ID,
  ex.: `27057`); jun/jul/ago (92) tem slug **de data** (`02-de-julho-de-2026`). Guardar o
  `post_name` **real** de cada post num mapa slug→data (§4/§5) — **não** presumir formato.
- **Lixeira/duplicatas:** excluir posts com slug terminando em `__trashed` (resto de trash,
  ex.: `05-de-agosto-de-2026-2__trashed`) e **dedupe defensivo por `data`** (manter o slug
  limpo / `post_modified` mais recente).
- **1 rascunho** → **ignorado** (só `publish`+`future` entram).

## 4. Modelo de dados (migrations + models, domínio pt-BR)

> Migrations **aditivas**; conferir tabelas existentes antes; nunca `migrate:fresh`.

### `agenda_metas_mes` — o tema fixo do mês (substitui o glossary)
| Coluna | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| ano | smallint unsigned | |
| mes | tinyint unsigned | 1–12 |
| titulo | string | ex.: "Combater o egoísmo: inveja, ciúme e maledicência" |
| timestamps | | **`unique(ano, mes)`** |

### `agenda_dias` — uma entrada por data
| Coluna | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| data | date | **unique** — chave natural / idempotência |
| reflexao | text null | HTML (Evangelho, referência embutida) |
| meta_mes_texto | text null | HTML (citação do dia sob a Meta do Mês) |
| meta_dia_titulo | string null | dura vários dias |
| meta_dia_texto | text null | HTML |
| prece | text null | HTML |
| status | string | `publicado` \| `rascunho` (default `publicado` na importação) |
| wp_id | unsigned bigint null | id legado (rastreio; `unique`) |
| timestamps | | |

### `agenda_slugs_legado` — mapa de URLs antigas → data (para os 301)
| Coluna | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| slug | string unique | `post_name` real do WP (numérico OU de data — o que houver) |
| data | date | destino do 301 (aponta para `agenda_dias.data`) |

**N** slugs podem apontar para a mesma `data` (duplicatas históricas que o Google indexou).
Populada na importação a partir do `post_name` de **cada** post válido — desacopla o 301 do
conteúdo e cobre slug numérico (maio) e de data (jun-ago) sem presumir formato.

**Índices:** `agenda_dias.data` unique; `agenda_dias.wp_id` unique; `agenda_dias.status`
(scope); `agenda_slugs_legado.slug` unique + índice em `data`. Título do mês resolvido por
`ano+mes` da `data` (sem FK — catálogo raso). Sem override por dia (YAGNI; se surgir, adicionar
`meta_mes_id` nullable depois).

### Models
- `App\Models\AgendaDia` — cast `'data' => 'date'`; `const STATUS_PUBLICADO='publicado'`,
  `STATUS_RASCUNHO='rascunho'`; scope `publicado()`; **mutators de sanitização** nos 4
  campos HTML (`reflexao`, `meta_mes_texto`, `meta_dia_texto`, `prece`) via
  `Attribute::make(set: fn ($v) => $v !== null ? clean($v, 'conteudo') : null)` (perfil
  `conteudo` já existe em `config/purifier.php`; cobre admin **e** import). Accessors de
  apresentação: `metaMes()` (resolve `AgendaMetaMes` do mês, com cache por request),
  `tituloExtenso()` (`data->translatedFormat('l, d \d\e F \d\e Y')` + `Str::ucfirst`),
  `descricaoSeo()` (`Str::limit(strip_tags($reflexao), 155)`).
- `App\Models\AgendaMetaMes` — `$table='agenda_metas_mes'`; casts inteiros; sem HTML.
- `App\Models\AgendaSlugLegado` — `$table='agenda_slugs_legado'`; cast `'data'=>'date'`;
  usado só nos 301 (não aparece no admin).

## 5. Importação (`cema:importar-agenda`) — molde do importador de Palestras

Espelha o pipeline existente (`ImportarPalestras` → `ImportadorPalestras` +
`LeitorLegado`/`LeitorLegadoMysql`, bind em `AppServiceProvider::register()`).

- **`app/Importacao/LeitorAgenda.php`** (interface): `public function entradas(): array;`
- **`app/Importacao/LeitorAgendaMysql.php`**: `DB::connection('legado')` no construtor;
  SELECT read-only com placeholders; retorna arrays já normalizados. Uma linha por post
  **publish/future** (ignora draft **e slugs `%__trashed`**), com:
  `['data', 'wp_id', 'post_name', meta achatada]`. A **`data`** é derivada **cruzando**
  `DATE(post_date)` × `_dia_agenda` (unix→data) e, quando o slug é de data, o slug —
  **aviso/abort em divergência**. Achata `wp_postmeta` (`metasDe($id)` no molde do Palestras)
  e **resolve as chaves de glossary** (mapa da §3) para `_mes_titulo`/`_titulo_meta_dia`;
  chave `*_2026_*` **não resolvida → grava `null`** (nunca a chave crua) + **aviso**.
- **`app/Importacao/ImportadorAgenda.php`**:
  - **Dedupe defensivo por `data`** (mantém o slug limpo / `post_modified` mais recente).
  - `AgendaMetaMes::updateOrCreate(['ano'=>Y,'mes'=>m], ['titulo'=>_mes_titulo])` a partir dos
    títulos distintos por mês.
  - `AgendaDia::updateOrCreate(['data'=>...], [reflexao, meta_mes_texto, meta_dia_titulo,
    meta_dia_texto, prece, status='publicado', wp_id])` — HTML cru; **mutator do model
    sanitiza**.
  - `AgendaSlugLegado::updateOrCreate(['slug'=>post_name], ['data'=>...])` para **cada** post
    válido (mapa de 301, N:1).
  - `DB::transaction` por entrada; `avisos[]` no resumo (glossary não resolvido, divergência de
    data, dedup aplicado).
  - **Idempotente** (upsert por `data`/`slug`); rodar 2× não duplica.
- **`app/Console/Commands/ImportarAgenda.php`**: `signature='cema:importar-agenda'`;
  `handle(LeitorAgenda $leitor, ImportadorAgenda $importador)`; **guarda de túnel** só se
  `$leitor instanceof LeitorAgendaMysql` (`DB::connection('legado')->getPdo()` em try/catch
  com dica do `ssh -L 3307:...`); callback de log + resumo/avisos.

## 6. Arquitetura do front (SSR-first, crawlável)

> **Divergência deliberada do handoff** (justificada pelo requisito SEO #2/#8): em vez de um
> componente Livewire que troca dia/mês por `wire:click` (o molde do Calendário de Palestras
> **não é crawlável**), usamos **Controller + Blade SSR**, com cada data sendo uma **URL
> real**. `wire:navigate` dá a sensação SPA sem sacrificar SEO nem o funcionamento sem JS.
> Reaproveitamos do molde a **montagem da matriz** (offset domingo + `daysInMonth`), a
> marcação de "hoje", o JSON-LD via `<x-slot:head>` e os `translatedFormat` pt-BR.

### 6.1 Rotas (`routes/web.php`) — estáticas antes de `{param}`
```php
Route::get('/agenda-reforma-intima', [AgendaController::class, 'index'])->name('agenda.index');
Route::get('/agenda-reforma-intima/{data}', [AgendaController::class, 'show'])
    ->name('agenda.show')->where('data', '\d{4}-\d{2}-\d{2}');

// 301 de compatibilidade (base antiga confirmada: /agenda-reforma[/{slug}])
Route::permanentRedirect('/agenda-reforma', '/agenda-reforma-intima');
Route::get('/agenda-reforma/{slug}', function (string $slug) {
    $data = \App\Models\AgendaSlugLegado::where('slug', $slug)->value('data');
    abort_if($data === null, 404);
    return redirect()->route('agenda.show', $data->format('Y-m-d'), 301);
})->where('slug', '[a-z0-9-]+');   // slug numérico (maio) OU de data (jun-ago)
```

### 6.2 Controller `App\Http\Controllers\AgendaController`
- `index()` — resolve a **data de hoje em Brasília** (`Carbon::today()` no TZ da app) e
  renderiza a mesma view do `show`, marcando `ehUrlNua = true` (habilita o script de fuso e
  o canonical evergreen).
- `show(string $data)` — **valida a data de verdade** (`Carbon::createFromFormat('!Y-m-d',
  $data)` + checagem de validade real; a regex `\d{4}-\d{2}-\d{2}` aceita `2026-13-45`, então
  data inválida → **`abort(404)`**, nunca 500). Depois carrega:
  `AgendaDia::publicado()->firstWhere('data', $data)` (+ `metaMes`), a **matriz do mês**
  (dias do mês + quais têm conteúdo publicado, via um `AgendaCalendario` service/DTO
  reaproveitando a lógica de `Calendario::matriz()`), **dia anterior/próximo com conteúdo**
  e **mês anterior/próximo** (primeiro dia com conteúdo de cada), e o **payload de SEO**.
  Sem conteúdo para a data (formato válido): 200 + estado vazio + `noindex` + calendário e
  link para hoje/dia mais próximo.

### 6.3 View `resources/views/agenda/index.blade.php` (casca) + parciais
Estrutura como `palestras/calendario.blade.php` (casca) + `palestras/index.blade.php` (hero/breadcrumb):
- `<x-layout.app :title="..." :description="$dia?->descricaoSeo()">` — sufixo " — CEMA"
  automático. `title` = "Agenda Reforma Íntima — {d/m/Y}".
- **`<x-slot:head>`** (SSR): `<link rel="canonical">` (data, ou a própria URL nua se
  `ehUrlNua`), **JSON-LD** (`@graph` **`Article`**+`BreadcrumbList`, `array_filter` p/ nulos,
  serializado com `JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_HEX_TAG`), e
  `<meta robots noindex>` quando estado vazio. **Não** emitir `og:type`/`og:url` aqui — o
  layout já emite `og:type=website` + `og:url=url()->current()`; duplicar prejudica.
- **Hero roxo** (`bg-gradient-to-br from-primary to-footer-bg`, `<x-ui.particulas>`, kicker
  font-mono, H1 font-display, régua `h-1 w-16 bg-gold`) — copy do handoff §4.1/§6.
- **Breadcrumb inline** (`<nav aria-label="Trilha de navegação">`) — não há componente; seguir
  `palestras/index.blade.php`.
- **Card do dia** (parcial `agenda/_dia.blade.php`): cabeçalho roxo com data por extenso
  (`translatedFormat('l, d \d\e F \d\e Y')` + `Str::ucfirst`) e **setas ‹ ›** como
  `<a href="{{ route('agenda.show', $anterior) }}" wire:navigate>`; quando ≠ hoje, pílula
  dourada "Voltar para hoje" (`route('agenda.index')`). Quatro blocos SSR (Reflexão / Meta
  do Mês [título do mês + citação do dia] / Meta do Dia [título + texto] / Prece) com ticks
  dourados e divisórias — medidas no handoff §4.3. **Compartilhar** (caixa creme) com o
  padrão Alpine existente (Facebook `sharer.php?u=`, WhatsApp `wa.me/?text=`, copiar
  `navigator.clipboard`, e Web Share opcional) — texto = título + data + reflexão + meta do
  dia + prece + "CEMA".
- **Calendário** (parcial `agenda/_calendario.blade.php`): cabeçalho do mês com ‹ › como
  `<a href wire:navigate>` (mês anterior/próximo); grade `grid grid-cols-7`; **cada dia com
  conteúdo = `<a href="{{ route('agenda.show', $ymd) }}" wire:navigate>`** (crawlável), dia
  sem conteúdo = `<span>` inerte; marcação de **hoje** (`border-2 border-primary` +
  `aria-current="date"`) e **dia exibido** (`bg-primary text-white`); marcador "bookmark"
  nos dias com conteúdo; legenda. Estilos via tokens + um CSS auxiliar
  (`resources/css/agenda.css`, no padrão de `palestras-calendario.css`).
- **"Sobre o projeto"** (`bg-cream`) e **"Veja também"** (pílulas) — handoff §4.5/§4.6,
  copy §6; links reais quando as rotas existirem.

### 6.4 Fuso do visitante (só na URL nua)
Na casca, quando `ehUrlNua`, um script inline compara a data local do navegador com a de
Brasília (SSR) e, se diferirem, **navega** para a URL datada local (não altera a URL nua):
```blade
@if ($ehUrlNua)
<script>
  (function () {
    var local = new Date().toLocaleDateString('en-CA'); // 'AAAA-MM-DD' no fuso do navegador
    var brasilia = @json($hojeBrasilia->format('Y-m-d'));
    if (local !== brasilia) { location.replace(@json(url('/agenda-reforma-intima')) + '/' + local); }
  })();
</script>
@endif
```
Robôs (sem JS) ficam no dia de Brasília (estável, canônico de si). A URL datada nunca
redireciona (evita loop). O conteúdo é sempre SSR na página de destino.

## 7. SEO — mapa concreto (arquivos a editar)

- **Sitemap** (`SitemapController::index()` + `resources/views/sitemap.blade.php`): carregar
  `AgendaDia::publicado()->get(['data','updated_at'])`; na view, URL nua
  (`route('agenda.index')`, `daily`, priority `0.9`) + `@foreach` das datadas
  (`route('agenda.show', $a->data->format('Y-m-d'))`, `lastmod`=`updated_at->toAtomString()`,
  `monthly`, `0.7`). (Opcional: anunciar o sitemap no `public/robots.txt`.)
- **JSON-LD por dia** (na casca, `<x-slot:head>`): `@graph` com
  **`Article`** (headline = "Agenda Reforma Íntima — {data extenso}", `datePublished` =
  `data->toIso8601String()`, `dateModified` = `updated_at`, `articleBody` =
  `strip_tags(reflexao)`, `inLanguage='pt-BR'`, author/publisher = Organization "Centro
  Espírita Maria Madalena") **+ `BreadcrumbList`** (Início › Agenda Reforma Íntima › {data}).
  Molde: `blog/show.blade.php:8-49`.
- **title/description/canonical:** props do layout + `<x-slot:head>` (layout **não** tem prop
  `canonical`; injetar `<link rel="canonical">` no slot, como `blog/show.blade.php:61`).
- **OG:** **não** emitir `og:type`/`og:url` próprios — o layout já cobre (`og:type=website`,
  `og:url=url()->current()`); duplicar prejudica.
- **301:** §6.1 (archive estático + singles por lookup em `AgendaSlugLegado`, cobrindo slug
  **numérico** de maio e **de data** de jun-ago). **Pré-go-live (tráfego-crítico):** confirmar
  o formato REAL das URLs antigas no **Search Console**/resultado do Google **antes** de fixar
  o mapa (o slug varia; o base path pode reservar surpresas).

## 8. Admin (Filament 5)

Dois Resources em `app/Filament/Resources/Agenda/`, molde `PalestraResource`/`PostResource`
(cabeçalho de autoria; `form(Schema)`, `table(Table)`, `getPages()`; painel gateado por
`User::canAccessPanel` já existente = local/testing).

- **`AgendaMetaMesResource`**: `TextInput ano` (numérico), `Select mes` (1–12 rotulados em
  pt-BR) ou `TextInput` com regra 1–12, `TextInput titulo`. **Unicidade composta (ano,mes)**
  — sem precedente no repo: usar `Rule::unique('agenda_metas_mes')->where('ano',$get('ano'))`
  com `ignoreRecord`, além do índice `unique(ano,mes)` na migration. Tabela ordenada por
  `ano desc, mes desc`.
- **`AgendaDiaResource`**: `DatePicker data` (novo no repo, padrão Filament 5) com
  `->unique(table:'agenda_dias', column:'data', ignoreRecord:true)->required()`;
  `RichEditor` simples (estilo `PalestraResource::descricao`, sem toolbar custom) para
  `reflexao`, `meta_mes_texto`, `meta_dia_texto`, `prece`; `TextInput meta_dia_titulo`;
  `Select status`. Tabela: colunas `data` (`->date('d/m/Y')`), `meta_dia_titulo`, `status`
  (badge), `->defaultSort('data','desc')`, `SelectFilter status`. Permite criar **dias
  futuros** (a agenda fica ~1 mês à frente). A sanitização vem do **mutator do model**.

## 9. Acessibilidade · Responsivo · Performance

- **A11y:** links reais com foco visível; `aria-current="date"` no hoje; `aria-label` nas
  setas; `<nav aria-label>` no breadcrumb; contraste conforme design; `prefers-reduced-motion`
  (o `app.css` já cobre os heros/partículas).
- **Responsivo (mobile-first):** abaixo de `desktop-sm` (1024px) as duas colunas empilham
  (calendário abaixo do card). Header/rodapé já têm versão mobile.
- **Performance:** HTML enxuto (bem abaixo do ~0,5 MB/página do WP atual); zero fetch
  externo (tudo Eloquent); `wire:navigate` para transições leves; imagens — nenhuma no
  escopo 2026.

## 10. Testes (PHPUnit + `RefreshDatabase`, nomes pt-BR)

- **Importação** (`tests/Feature/Importacao/`): `ImportadorAgendaTest` com classe anônima
  implementando `LeitorAgenda`, rodando **2×** (idempotência: contagens não duplicam),
  cobrindo **dedupe 05/08**, **resolução de chave de glossary**, **criação de
  `agenda_metas_mes`** por mês e **ignore de draft**; `ImportarAgendaCommandTest` rebinda a
  interface e roda `$this->artisan('cema:importar-agenda')`.
- **Rotas/SEO** (`tests/Feature/Front/`): `agenda.index` 200; `agenda.show` 200 com o dia
  correto (SSR contém a reflexão); **futuro legível** (data futura com conteúdo → 200);
  data sem conteúdo → 200 + `noindex`; **data inválida** (`/agenda-reforma-intima/2026-13-45`)
  → **404** (nunca 500); **301** de `/agenda-reforma` e de `/agenda-reforma/{slug}` via
  `AgendaSlugLegado` — cobrir **slug numérico** (maio) **e de data** (jun-ago) —, com
  `assertRedirect`+`assertStatus(301)`; canonical + JSON-LD `Article`/`BreadcrumbList`
  presentes e **`og:type`/`og:url` NÃO duplicados** (`assertSee`/`assertDontSee(..., false)`);
  presença das URLs no `/sitemap.xml`.
  `assertSame(url('/agenda-reforma-intima'), route('agenda.index'))`.
- **Model:** sanitização (script removido de `reflexao`), scope `publicado()`, resolução da
  Meta do Mês por ano+mes, `descricaoSeo()`.
- **Verificação manual** no `localhost` (abrir hoje, navegar dias/meses por link, conferir
  compartilhar e o redirect de fuso). **Pint** antes do push.

## 11. Divergências deliberadas do handoff (transparência)

1. **Sem "janela de publicação"/trava de futuro** (decisão do dono: futuro liberado).
2. **URL de caminho** `/AAAA-MM-DD` em vez de `?data=` (decisão do dono; melhor canonical).
3. **Controller + Blade SSR** (não um componente Livewire que troca conteúdo por
   `wire:click`), para navegação **crawlável** e conteúdo **SSR** — requisitos SEO. Mantém-se
   o *visual* e as *medidas* do protótipo e a lógica de matriz do molde de Palestras.
4. **Meta do Mês:** título mensal em `agenda_metas_mes`; **citação é diária** em
   `agenda_dias` (o handoff tratava a citação como mensal). Referência/autor **embutidos** no
   HTML (handoff previa campos separados).
5. **Capa do livro no hero:** opcional/omitida por padrão (padrão do site é hero roxo + partículas).
6. **OG:** sem `og:type`/`og:url` próprios no slot (o layout já cobre); JSON-LD usa **`Article`**.

## 12. Fora de escopo (agora)

Imagem do dia, "Tema para Reflexão", feed `.ics`/assinatura da agenda, comentários,
override de Meta do Mês por dia, separação de referência/autor em colunas, "Gerar
description por IA". Nenhum bloqueia reativação futura (migrations aditivas).

## 13. Ordem de implementação (sugestão)

1. Migrations `agenda_metas_mes` + `agenda_dias` (+ índices) e models (casts, scope,
   mutators, accessors). Conferir tabelas existentes antes.
2. `LeitorAgenda`/`LeitorAgendaMysql` + bind + `ImportadorAgenda` + `cema:importar-agenda`
   (com mapa de glossary, dedupe, ignore draft) + testes de importação.
3. Filament `AgendaMetaMesResource` + `AgendaDiaResource`.
4. `AgendaController` + rotas (+ 301) + casca `agenda/index.blade.php` + parciais
   (`_dia`, `_calendario`) + `agenda.css`; script de fuso; compartilhar (Alpine).
5. SEO: `<x-slot:head>` (canonical + JSON-LD + og), `SitemapController` + `sitemap.blade.php`.
6. Testes de rota/SEO/301 + A11y/responsivo; conferência no `localhost`; Pint; commit/branch.
7. **Pré-go-live (tráfego-crítico):** confirmar as URLs antigas no **Search Console**; rodar a
   importação real e **revisar os avisos** (glossary de maio não resolvido, divergências
   `post_date`×`_dia_agenda`, dedup aplicado); conferir os 301 (slug numérico e de data).

## 14. Arquivos a criar/editar (mapa)

**Criar:** `database/migrations/*_create_agenda_metas_mes_table.php`,
`*_create_agenda_dias_table.php`, `*_create_agenda_slugs_legado_table.php`;
`app/Models/AgendaDia.php`, `AgendaMetaMes.php`, `AgendaSlugLegado.php`;
`app/Importacao/LeitorAgenda.php`, `LeitorAgendaMysql.php`, `ImportadorAgenda.php`;
`app/Console/Commands/ImportarAgenda.php`; `app/Http/Controllers/AgendaController.php`;
(opcional) `app/Support/Agenda/CalendarioAgenda.php` (matriz);
`app/Filament/Resources/Agenda/AgendaDiaResource.php` + `AgendaMetaMesResource.php` (+ Pages);
`resources/views/agenda/index.blade.php`, `agenda/_dia.blade.php`, `agenda/_calendario.blade.php`;
`resources/css/agenda.css`; testes em `tests/Feature/Importacao/` e `tests/Feature/Front/`.

**Editar:** `routes/web.php` (rotas + 301); `app/Providers/AppServiceProvider.php` (bind
`LeitorAgenda`); `app/Http/Controllers/SitemapController.php` + `resources/views/sitemap.blade.php`;
`resources/css/app.css` (`@import 'agenda.css'`); **`config/navegacao.php` — ATIVAR o item
'Agenda'** (hoje `ativo=>false, itens=>[]`, linha 27) → `['rotulo'=>'Agenda',
'rota'=>'agenda.index', 'ativo'=>true, 'itens'=>[]]` (página de maior tráfego, precisa de link
no nav global); `ROADMAP.md`/`DATA-MODEL.md`/`DB-LEGADO.md` (registrar a fatia ao concluir).
