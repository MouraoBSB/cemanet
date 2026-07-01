# Redesign da Listagem Pública de Palestras (archive) — Design/Spec

**Data:** 2026-06-30
**Autor:** Thiago Mourão — https://github.com/MouraoBSB
**Handoff visual:** `design_handoff_palestras-archive/` (README + `prototipo/Palestras.dc.html` + 3 screenshots)
**Abordagem aprovada:** **A** — evoluir o componente `App\Livewire\Palestras\Lista` e suas views *in place* (não reescrever), preservando o back-end e o contrato de URL/testes.

---

## 1. Objetivo

Reconstruir o **front-end** da listagem pública de palestras (`/palestra_publica`) fiel ao
`design_handoff_palestras-archive` — hero, banner "Próxima palestra", barra de filtros redesenhada,
chips de filtros ativos, alternância **grade ↔ lista**, card-pôster 16:10, paginação numerada,
estado vazio e seção "Veja também" — **sem alterar o schema do banco** e **sem quebrar** o contrato
de comportamento já coberto por testes.

### Em escopo
- Redesenho das views da listagem (hero, destaque, barra de filtros, chips, cards grade/lista, paginação, vazio, "Veja também").
- Evolução do componente `Palestras\Lista`: novos parâmetros `ano`, `video`, `visao`; ordenação `az`; contagem/faixa de resultados; chips.
- Accessor `formato` no model `Palestra` (a partir do booleano `online`) — **sem migração**.
- CSS próprio da archive (overlay do pôster, 8 gradientes de fallback, banner, partículas, animações) via `@theme`/`var(--color-*)`.
- **Preparação da rota do atalho "Calendário"** (1ª tarefa): rename da rota `.ics` + registro do stub canônico `palestras.calendario`.
- Atualização dos testes de contrato afetados + novos testes.

### Fora de escopo (fatias posteriores, já desenhadas)
- **Módulo Calendário** completo (`design_handoff_calendario/`): aqui só entra o **stub** de rota/página com o nome canônico, para o atalho do hero não ser link morto. A fatia do Calendário preenche o `<livewire:palestras.calendario />` depois.
- **Listagem de Palestrantes** (`design_handoff_palestrantes/`).
- Qualquer mudança de schema, importação ou campo novo no admin.

---

## 2. Contexto: o que já existe (invariantes a preservar)

Fonte: `app/Livewire/Palestras/Lista.php`, `resources/views/palestras/index.blade.php`,
`routes/web.php`, testes em `tests/Feature/{Front,Livewire}/`.

- **Componente** `App\Livewire\Palestras\Lista` (`WithPagination`), com `#[Url]`:
  - `$q` → `#[Url(as:'q', except:'')]` (busca em `titulo`/`subtitulo`/`resumo`, LIKE).
  - `$assunto` → `as:'assunto'` (slug; `whereHas('assuntos', slug=…)`) — **este é o "Tema"** do handoff.
  - `$palestrante` → `as:'palestrante'` (slug; `whereHas('palestrantesAtivos', palestrantes.slug=…)`).
  - `$dataDe` → **`as:'de'`** (propriedade `dataDe`; `whereDate('data_da_palestra','>=')`).
  - `$dataAte` → **`as:'ate'`** (propriedade `dataAte`; `whereDate('data_da_palestra','<=')`).
  - `$ordenar` → `as:'ordenar', except:'recente'` (valores `recente|antiga`; ordena `data_da_palestra` desc/asc com NULLs ao fim).
  - `->paginate(12)` (será **9**, ver §Decisões).
  - Dropdowns: `Palestrante::ativo()->orderBy('nome')`; `Assunto::whereHas('palestras', publicado())->orderBy('nome')`.
- **Rotas** (`routes/web.php`): `palestras.index` (`/palestra_publica`), `palestras.show` (`/palestra_publica/{slug}`, **sem constraint hoje**), `palestras.calendario` (**hoje = `.ics` por-palestra**, `/palestra_publica/{slug}/calendario.ics`, `where slug [a-z0-9-]+`). 301: `/palestras → /palestra_publica`; `/palestras/{slug} → palestras.show`.
- **Destaque** montado no `PalestraController@index` (`$proxima` = próxima futura por data asc; senão a mais recente por data desc). A view usa `<x-ui.countdown :data="$proxima->data_da_palestra" />`.
- **Dados reais:** 123 palestras publicadas; `online` booleano (123/123); taxonomia `assunto` **populada** (141 termos, 112 palestras ligadas); ~3 palestras sem `link_youtube` (que é **`NULL`**, não `''`); palestrantes com foto via Media Library (`foto_thumb_url`).

### Contrato coberto por testes (não regredir)
- `PalestrasListagemTest`: só **publicado()** visível; **placeholder `logo-icone`** e **sem** `i.ytimg.com/vi/` quando `link_youtube = null` (linhas 26-38); só palestrante **ativo** + papel PALESTRANTE (linhas 40-55).
- `PalestrasDestaqueTest`: texto `Próximas Palestras` (linha 22 — **será ajustado**, ver §Decisões #8); markup `Contagem regressiva para a palestra` presente com futura / ausente sem futura (linhas 33, 43).
- `PalestrasFiltrosTest`: `palestrante`, `assunto`, `dataDe`, `ordenar='antiga'` (propriedades, via `->set(...)`).
- `PalestrasListaTest`: `q` busca `titulo`; ordem padrão desc.
- `PalestraUrlCompatTest`: `/palestra_publica` 200; 301 de `/palestras` e `/palestras/{slug}`.
- `CalendarioPalestraTest`: `.ics` em UTC/escape/404 (usa `route('palestras.calendario', slug)` — **será renomeado**, ver §Decisões #10).

---

## 3. Decisões (aprovadas)

Da rodada de perguntas + revisão do dono contra o código real:

1. **Imagem do card:** thumb do YouTube (`youtube_thumb_hq`, hqdefault) como fundo do pôster 16:10 + **8 gradientes** de fallback (`palestra->id % 8`) para as sem vídeo. Título/chips como **overlay em texto** (não embutidos na imagem). **Sem** campo/upload novo.
2. **Formato:** derivado do booleano `online` — **Online** (cor `secondary`) / **Presencial** (cor `accent`). **Sem "Híbrida"**, sem migração.
3. **Destaque:** banner roxo do handoff **mantendo a contagem regressiva** (`<x-ui.countdown>` reaproveitado).
4. **Filtros:** todos do handoff **+ `ano` + `video` (com/sem)**.
5. **Reuso de contrato:** manter propriedades/aliases existentes (`q`, `assunto`, `palestrante`, `dataDe`/`de`, `dataAte`/`ate`, `ordenar`). **Não** criar props `de`/`ate`/`tema`/`titulo`. Só adicionar: `ano` (int), `video` (`com|sem`), `visao` (`grid|list`, default `grid`).
6. **resetPage:** só os filtros de **resultado** resetam a paginação (`q, assunto, palestrante, dataDe, dataAte, ordenar, ano, video`). **`visao` e troca de página NÃO** chamam `resetPage()`.
7. **Filtro de ano (portável SQLite):** `->when($ano, fn($qr)=>$qr->whereYear('data_da_palestra', $ano))`. Dropdown de anos por **distinct em PHP**: `Palestra::publicado()->pluck('data_da_palestra')->filter()->map->year->unique()->sortDesc()->values()`. **Evitar** `YEAR()`/`selectRaw`.
8. **Filtro de vídeo:** `com` → `whereNotNull('link_youtube')`; `sem` → `whereNull('link_youtube')` (o campo é `NULL` sem vídeo).
9. **`ordenar='az'`:** ordena `titulo` asc (`->orderBy('titulo')`). Os valores `recente|antiga` mantêm o `orderByRaw` atual (NULLs ao fim).
10. **`formato` accessor:** `getFormatoAttribute()` retorna estrutura `{ slug, rotulo, cor }` (`online` → `{online, 'Online', 'secondary'}` / `{presencial, 'Presencial', 'accent'}`). **Sem** mudança de banco.
11. **Countdown do destaque:** reusar `<x-ui.countdown :data="$proxima->data_da_palestra" />` (variante **não** compacta → emite `role="timer" aria-label="Contagem regressiva para a palestra"`, o que mantém `PalestrasDestaqueTest` linhas 33/43). O cabeçalho muda de "Próximas Palestras" → **"Próxima palestra"** (handoff); **`PalestrasDestaqueTest` linha 22 será atualizada** para o novo texto (confirmado: é o único teste que assertava esse título).
12. **`ano` × `dataDe/dataAte`:** ambos filtram `data_da_palestra` e **combinam via AND**, cada um com **chip próprio** (comportamento explícito; `ano` NÃO preenche De/Até automaticamente).
13. **Paginação:** **9 por página** (grid 3×3 do protótipo; era 12) — decisão do cliente.
14. **Busca:** mantém `LIKE` (acento-insensível funciona no MySQL de produção); **nenhum teste** exigirá acento-insensível no SQLite.

---

## 4. Preparação da rota "Calendário" (1ª tarefa da fatia)

Motivo: o nome `palestras.calendario` hoje pertence ao `.ics` por-palestra (exige `{slug}`); o atalho do hero (sem slug) estouraria. Além disso, `palestras.show` está **sem constraint** e engoliria `/palestra_publica/calendario`.

**Mudanças em `routes/web.php`:**
1. **Renomear** a rota `.ics`: `->name('palestras.calendario')` → **`->name('palestras.evento-ics')`** (path e `where slug` inalterados).
2. **Registrar o stub** da página, **antes** de `palestras.show`:
   ```php
   Route::get('/palestra_publica/calendario', [CalendarioController::class, 'index'])
       ->name('palestras.calendario');
   ```
3. **Constraint** em `palestras.show`: `->where('slug', '[a-z0-9-]+')` (belt-and-suspenders; a ordem já protege, mas o dono pediu explicitamente).

**Usos a atualizar pelo rename** (blast-radius **real**, verificado por grep):
- `routes/web.php` (definição).
- `CalendarioPalestraTest.php` linhas 25, 43, 54 (`route('palestras.calendario', …)` → `route('palestras.evento-ics', …)`).
- **`resources/views/palestras/show.blade.php` NÃO referencia essa rota** — o botão "Adicionar ao calendário" (linha 198) usa uma **URL do Google Agenda** (linha 23). Portanto **nenhuma view muda** (correção ao pressuposto de "2 usos": há só 1 uso em código, no teste).

**Stub `CalendarioController@index`** (novo, mínimo e **real** — nada de "em breve"):
- `app/Http/Controllers/CalendarioController.php` → `index()` renderiza uma casca `resources/views/pages/calendario.blade.php` com H1 "Calendário de Palestras" + lista server-side das **próximas** palestras publicadas (`data_da_palestra >= now()`, asc: data · título · formato · link para o single). Retorna **200**, é útil e indexável. A fatia do Calendário substitui o corpo por `<livewire:palestras.calendario />` depois.
- O atalho do hero aponta para `route('palestras.calendario')`.

---

## 5. Componente `Palestras\Lista` (evolução)

**Novos `#[Url]`** (além dos existentes do §2):
```php
#[Url(as: 'ano', except: '')]      public string $ano = '';        // int como string p/ compat com <select>
#[Url(as: 'video', except: '')]    public string $video = '';      // '' | 'com' | 'sem'
#[Url(as: 'visao', except: 'grid')] public string $visao = 'grid'; // 'grid' | 'list'
```

**`updated($name)`** — resetPage para: `['q','assunto','palestrante','dataDe','dataAte','ordenar','ano','video']`. **Não** inclui `visao`.

**`limparFiltros()`** — `reset(['q','assunto','palestrante','dataDe','dataAte','ordenar','ano','video'])` + `resetPage()`. **Mantém** `visao` (é preferência de exibição, não filtro).

**`removerFiltro(string $chave)`** — zera a propriedade correspondente (map `chave→prop`) + `resetPage()`.

**`alternarVisao(string $v)`** — define `visao` (`grid|list`), **sem** `resetPage()`.

**Computeds:**
- `filtrosAtivos()` → array de `{ chave, rotulo, valor }` para os chips (ex.: `assunto` → rótulo do assunto pelo slug; `palestrante` → nome; `video` → "Com vídeo"/"Sem vídeo"; `de`/`ate`/`ano` → valor formatado).
- `anosDisponiveis()` → distinct em PHP (§Decisões #7).

**`render()`** — acrescenta os `when` de `ano`/`video` e o ramo `az`:
```php
->when($this->ano !== '', fn (Builder $q) => $q->whereYear('data_da_palestra', (int) $this->ano))
->when($this->video === 'com', fn (Builder $q) => $q->whereNotNull('link_youtube'))
->when($this->video === 'sem', fn (Builder $q) => $q->whereNull('link_youtube'))
->when($this->ordenar === 'az',
    fn (Builder $q) => $q->orderBy('titulo'),
    fn (Builder $q) => $q->orderByRaw('data_da_palestra IS NULL, data_da_palestra '.($this->ordenar === 'antiga' ? 'asc' : 'desc')))
->paginate(9);
```
Passa também `anos`, `palestrantes`, `assuntos`, e o total/faixa via o próprio `LengthAwarePaginator` (`total()`, `firstItem()`, `lastItem()`).

---

## 6. Model `Palestra` — accessor `formato`

```php
// Sem coluna nova. Deriva do booleano `online`.
protected function formato(): Attribute
{
    return Attribute::get(fn () => $this->online
        ? ['slug' => 'online', 'rotulo' => 'Online', 'cor' => 'secondary']
        : ['slug' => 'presencial', 'rotulo' => 'Presencial', 'cor' => 'accent']);
}
```
Usado nos badges do card (grade/lista) e do banner. (Se preferir, um `<x-palestra.badge-formato :palestra="…" />` encapsula rótulo+cor.)

---

## 7. Views (estrutura)

Todas com **mobile-first**, tokens via classes utilitárias (`bg-primary`, `text-gold`, …) e `var(--color-*)` quando precisar de CSS puro. Reaproveitar `<x-layout.app>`.

### 7.1 `resources/views/palestras/index.blade.php` (modificar)
- **Hero** roxo (`from-primary to-footer-bg`) com kicker mono, H1 "Palestras Públicas", régua dourada, subtítulo, partículas decorativas (`.cema-hero-deco`/keyframes com `prefers-reduced-motion`) e **card-atalho "Calendário de Palestras"** → `route('palestras.calendario')`.
- **Breadcrumb** `Início › Palestras › Palestras Públicas` (`bg-surface`, `text-muted`) + **JSON-LD BreadcrumbList**.
- **Banner "Próxima palestra"** (quando `$proxima`): gradiente roxo, avatar (`foto_thumb_url` ou iniciais), chip de data (`d 'de' F · H'h'i`), badge de formato, título, "com PALESTRANTE · Tema", botão branco "Ver palestra", **e `<x-ui.countdown :data="$proxima->data_da_palestra" />`** quando futura. Cabeçalho: **"Próxima palestra"**.
- `<livewire:palestras.lista />`.
- **Seção "Veja também"** (rodapé): pílulas linkando **rotas reais** — `palestrantes.index`, `blog.index` (Sementeira), `palestras.calendario` (stub). **Sem** link morto.

### 7.2 `resources/views/livewire/palestras/lista.blade.php` (reescrever)
- **Barra de filtros** (card branco): título "Filtrar palestras" + badge "Total {{ $palestras->total() }}"; à direita `select` **Ordenar** (`Mais recentes|Mais antigas|Título (A–Z)`) + **toggle grade|lista** (`wire:click="alternarVisao('grid'|'list')"`, `aria-pressed`).
- **Grid de filtros** (`auto-fit minmax(180px,1fr)`): **De** (`wire:model.live=dataDe`), **Até** (`dataAte`), **Ano** (`ano`, options de `$anos`), **Palestrante** (`palestrante`), **Tema** (`assunto`), **Vídeo** (`video`: Todos/Com vídeo/Sem vídeo), **Título** (busca `q`, `wire:model.live.debounce.350ms`, span 2).
- **Chips de filtros ativos** (`@if count(filtrosAtivos)`): cada um com "×" (`wire:click="removerFiltro('…')"`); "Limpar tudo" (`wire:click="limparFiltros"`).
- **Linha de resultados:** "Mostrando {{ $palestras->firstItem() }}–{{ $palestras->lastItem() }} de {{ $palestras->total() }} palestra(s)".
- **Container:** `@if($visao==='grid')` grade de `<x-palestra.card>`, `@else` lista de `<x-palestra.linha>`.
- **Estado vazio** (`@if $palestras->isEmpty()`): painel tracejado, ícone lupa, "Nenhuma palestra encontrada", botão "Limpar filtros".
- **Paginação:** `{{ $palestras->onEachSide(1)->links() }}` (estilo numerado do handoff via view de paginação/tema).

### 7.3 `resources/views/components/palestra/card.blade.php` (reescrever — grade)
Pôster 16:10: `background` = `youtube_thumb_hq` **ou** gradiente `id % 8`; overlay escuro; eyebrow "PALESTRA PÚBLICA" (mono); badge de formato; título branco (overlay); chip do palestrante (avatar/iniciais + nome). Rodapé: data (ícone calendário) + tag do tema (1º assunto) + CTA "Ver". **Card é `<a href="{{ route('palestras.show', $palestra->slug) }}">`** (navegável por teclado). **Sem vídeo:** usa gradiente + logo-ícone, **nunca** emite `i.ytimg.com/vi/`. `loading="lazy"` + `width/height` (anti-CLS).

### 7.4 `resources/views/components/palestra/linha.blade.php` (novo — lista)
Linha horizontal: thumb 150px à esquerda (mesmas regras de imagem/fallback do card) + bloco (data · badge formato · tag tema · H3 título · "com Palestrante" · CTA "Ver palestra"). Responsivo: em mobile vira card compacto empilhado.

### 7.5 CSS — `resources/css/palestras-archive.css` (novo)
`@import` no `resources/css/app.css` (após `conteudo.css`). Contém o que não é utilitário puro: `.cema-talk-card` (hover translateY + sombra), overlay do pôster, `.cema-poster-grad-0..7` (8 gradientes), círculos do banner, partículas do hero, `fade-up` — tudo com `var(--color-*)` e `@media (prefers-reduced-motion: reduce)`. Build por `npm run build` no host.

---

## 8. SEO / A11y / Performance

- **SEO:** breadcrumb visual + **JSON-LD BreadcrumbList**; **CollectionPage/ItemList** leve com as palestras da página; `canonical`; `og:*` mantidos (`<x-layout.app>`). Título/description atuais preservados.
- **A11y:** cards como `<a>`; `aria-label` no toggle de visão e nos "×" dos chips; `<label>` em todo input; foco visível; contraste ≥ 4.5; `text-shadow` no overlay para legibilidade; `role="timer"` do countdown.
- **Performance:** `loading="lazy"` + dimensões nos pôsteres; eager loading `with(['palestrantesAtivos','assuntos'])` (sem N+1); debounce na busca; HTML enxuto; 9/página.

---

## 9. Plano de testes

**Rodar a suíte COMPLETA** (`docker compose exec -T app php artisan test`, **não** `--filter`) para pegar regressão de publicadas/ativo/fallback/slug/301 + a rota renomeada. **Pint antes do commit.** Após editar Blade/PHP no dev, `docker compose restart app worker` (OPcache).

### Ajustar (contrato existente)
- `PalestrasDestaqueTest:22` → `'Próxima palestra'` (mantém asserts de countdown 33/43).
- `PalestrasListagemTest` → atualizar seletores ao novo markup, **mantendo** as asserções: publicada visível / rascunho oculta; placeholder `logo-icone` e ausência de `i.ytimg.com/vi/` sem vídeo; só palestrante ativo.
- `CalendarioPalestraTest` (25/43/54) → `route('palestras.evento-ics', …)`.
- `PalestrasFiltrosTest`, `PalestrasListaTest`, `PalestraUrlCompatTest` → devem continuar **verdes** sem alteração de lógica (só markup, se algum assert tocar layout).

### Novos
- `PalestrasFiltroAnoTest`: `->set('ano','2026')` mostra só de 2026; `anosDisponiveis` lista distinct desc.
- `PalestrasFiltroVideoTest`: `video='com'` só com `link_youtube`; `video='sem'` só `NULL`.
- `PalestrasOrdenarAzTest`: `ordenar='az'` ordena `titulo` asc (via `viewData('palestras')`).
- `PalestrasVisaoTest`: default renderiza grade; `visao='list'` renderiza a linha; **trocar `visao` não reseta a página** (paginação preservada).
- `PalestrasChipsTest`: filtros ativos geram chips; `removerFiltro` remove um; `limparFiltros` zera filtros **mas mantém `visao`**.
- `PalestrasPaginacaoTest`: com 15 publicadas, a página 1 traz **9** itens.
- `PalestrasArchiveSeoTest`: `BreadcrumbList` presente na `palestras.index`.
- `CalendarioStubTest`: `GET /palestra_publica/calendario` → 200 e `route('palestras.calendario')` resolve; `GET /palestra_publica/{slug}` ainda 200 (constraint não quebra o single).

---

## 10. Riscos & mitigações

| Risco | Mitigação |
|---|---|
| Rename da rota `.ics` deixar referência órfã | grep confirmou: só `routes/web.php` + `CalendarioPalestraTest`; a view do single usa Google Agenda. Rodar suíte completa. |
| `/palestra_publica/calendario` engolido por `{slug}` | Stub registrado **antes** do `show` + `where('slug','[a-z0-9-]+')`. Teste `CalendarioStubTest`. |
| `whereYear`/ordenação quebrar no SQLite | `whereYear` é portável; anos por distinct em PHP; `az` por `orderBy('titulo')`. |
| Redesign quebrar asserts de markup | Atualizar os testes de contrato mantendo as **asserções semânticas** (não o layout). Suíte completa. |
| OPcache servindo Blade antigo no dev | `docker compose restart app worker` após edições (memória do projeto). |
| Tailwind v4: `theme()` não resolve | Usar utilitários + `var(--color-*)`; `npm run build` no host. |
| `migrate:fresh`/destrutivo zerar o dev | **Proibido**; esta fatia **não tem migração** (formato é accessor; filtros usam colunas existentes). |

---

## 11. Constraints globais (herdadas pelo plano)

- **Stack:** PHP 8.3 · Laravel 13 · Livewire 4 · Filament 5 · Tailwind v4 · MySQL 8 (dev) / SQLite `:memory:` (testes).
- **Sem migração / sem mudança de schema** nesta fatia.
- **Não** renomear propriedades/aliases existentes do `Lista` (§Decisões #5).
- Testes por `docker compose exec -T app php artisan test` (suíte completa); **Pint** antes do commit; `restart app worker` após edição de Blade/PHP.
- Tokens via classes utilitárias / `var(--color-*)` (nunca `theme()`); build `npm run build` no host.
- Commits atômicos; branch a partir de `main`; PROIBIDO `migrate:fresh/refresh/wipe/reset/db:wipe`/seed destrutivo.
- pt-BR em tudo (identificadores de domínio, UI, comentários, commits).
