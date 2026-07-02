# Redesign da página de detalhe do Palestrante (single) — Design

> **Rota:** `GET /palestrantes/{slug}` → `PalestranteController@show`
> **Fonte visual:** `design_handoff_palestrante_detalhe/` (README + `prototipo/` + 3 screenshots).
> **Fidelidade:** alta (hi-fi), **recriando na stack real** (Laravel 13 · Livewire 4 · Blade ·
> Tailwind v4 · Alpine) — **não** copiar o HTML/JS do protótipo.
> **Molde:** a listagem redesenhada (`palestrantes/index.blade.php`, PR #3) e o
> `<x-palestra.card>` (archive, PR #1). DRY: reusar tudo o que já existe.

---

## 1. Objetivo

Transformar a single atual do palestrante — hoje uma coluna longa e fraca (foto pequena, bio
solta, grid de thumbnails desalinhados) — numa página **conteúdo principal + sidebar sticky**
na nova identidade: hero roxo com foto em destaque, estatísticas reais, "Sobre" com tipografia
de leitura, grade de palestras filtrável por tema/ordenável (client-side) reaproveitando o
card-pôster, e sidebar com "próxima palestra", "áreas de atuação" (clicáveis = filtram) e
"compartilhar".

## 2. Correções obrigatórias — o handoff diverge do modelo real

O handoff pressupõe campos/relações que **não existem** ou têm outro nome. Confirmado lendo o
código (`app/Models/Palestrante.php`, `app/Models/Palestra.php`, migrações,
`PalestranteController.php`, `resources/views/components/palestra/card.blade.php`):

| Handoff diz | Realidade | Decisão |
|---|---|---|
| Frase do hero via `frase`/`tagline` | **Nenhuma coluna** — `palestrantes` só tem `nome, slug, bio, email, telefone, mostrar_email, mostrar_telefone, ativo` (a `foto` foi para a Media Library) | **NOVA coluna `chamada`** (string, nullable) via **migração aditiva**. Hero exibe só quando preenchida (`@if`), esconde quando vazia (degrada por registro; big-bang-safe). |
| `palestras()` (`latest('data')`) | `palestras()` inclui diretor; a relação certa é **`palestrasMinistradas()`** (`wherePivot papel=palestrante`). A coluna de data é **`data_da_palestra`**, não `data`. | Usar `palestrasMinistradas()->publicado()`, ordem por `data_da_palestra`. |
| Tema **único** por palestra; mapa fixo de 6 cores (nome→cor) | `assuntos` é **belongsToMany** (N por palestra, ~140 no acervo). Sem coluna "área". | "Áreas de atuação" = **`assuntos` distintos** das palestras do palestrante. Cor **rotacionada `assunto->id % 8`** (não a tabela do §8 do handoff). No card, o assunto exibido é `assuntos->first()` (como já faz). |
| Controller com binding implícito `show(Palestrante $palestrante)` + `abort_unless(ativo)` | Padrão do projeto é **`show(string $slug)` + `Palestrante::query()->ativo()->where('slug',$slug)->firstOrFail()`** | Manter o padrão atual (rota `{slug}`, não `{palestrante:slug}`). |
| Reusar "componente de share" existente | **Não existe** | Criar botões de compartilhar **client-side** (Alpine) nesta fatia. |
| Chips do hero decorativos ("hero-independentes") | — | **Decisão do dono:** os chips do hero **participam do mesmo estado** de filtro (compartilhado com a lista lateral e o grid). Ver §6. |

## 3. Modelo de dados (o que já existe e o que muda)

**`Palestrante`** (real): `nome`, `slug`, `bio` (HTML sanitizado no mutator via `clean()`),
`email`/`telefone` (+ flags `mostrar_*`), `ativo`; foto via Spatie Media Library
(`foto_url` web, `foto_thumb_url` thumb); accessors `iniciais` (fallback). Relações
`palestras()` e `palestrasMinistradas()`.

**Mudança única de schema:** adicionar **`chamada`** `string` **nullable** (frase curta do
hero). Aditiva (`php artisan migrate` forward). Texto simples (sem HTML) → sem sanitização
extra, exibido com `{{ }}`. Incluída em `$fillable` e no Filament (`TextInput` opcional,
`maxLength(180)`).

**`Palestra`** (real, sem mudança): `titulo`, `slug`, `resumo`, `data_da_palestra` (datetime,
nullable), `online` (boolean), `status`; scope `publicado()`; relações `assuntos()`,
`palestrantesAtivos()`; accessors `formato` (Online/Presencial derivado de `online`),
`youtube_thumb_hq`.

## 4. Arquitetura

Página **majoritariamente estática (SSR)** + **filtro/ordenação client-side (Alpine)** — o
conjunto de palestras de um palestrante é pequeno, então mostra/oculta/reordena no cliente,
sem Livewire e sem estado na URL (o canônico é a URL do perfil). Sem JS, todos os cards
aparecem na ordem "mais recentes" (progressive enhancement; big-bang-safe).

**Arquivos:**

- **Migração** `database/migrations/<ts>_add_chamada_to_palestrantes_table.php` — coluna aditiva.
- **Model** `app/Models/Palestrante.php` — `chamada` em `$fillable`.
- **Filament** `app/Filament/Resources/Palestrantes/PalestranteResource.php` — `TextInput('chamada')`.
- **Support** `app/Support/Palestrantes/ResumoPerfil.php` (novo) — calcula, **em PHP** (portável,
  sem SQL raw), a partir da coleção de palestras: estatísticas (nº palestras, temas distintos,
  ano ativo desde, % online) e as **áreas de atuação** (assuntos distintos com contagem + índice
  de cor), incluindo o subconjunto **top-N para os chips do hero**.
- **Controller** `app/Http/Controllers/PalestranteController.php` — `show()` reescrito: eager-load,
  monta `$palestras` (ordem "recentes"), `ResumoPerfil`, `$proxima`, e o **payload do Alpine**
  (itens com ranks de ordenação + áreas).
- **View** `resources/views/palestrantes/show.blade.php` — casca (layout, SEO, root `x-data`,
  includes) + **parciais** em `resources/views/palestrantes/perfil/`:
  `hero.blade.php`, `estatisticas.blade.php`, `sobre.blade.php`, `palestras.blade.php`,
  `sidebar.blade.php`.
- **CSS** `resources/css/palestrantes.css` (já existe e já importado) — acréscimos: paleta de
  bolinhas `.cema-dot-{0..7}`, tiles de estatística, botões de compartilhar, sticky da sidebar,
  moldura da foto do hero; `prefers-reduced-motion` já coberto por `<x-ui.particulas>`.
- **JS** `resources/js/app.js` — registrar `Alpine.data('palestranteDetalhe', …)` (via
  `alpine:init`; Alpine vem do bundle do Livewire).
- **Reuso sem recriar:** `<x-layout.app>` (+ `<x-slot:head>`), `<x-ui.particulas>`,
  `<x-palestra.card>`, `cema-grad-{id%8}`, `route('palestras.calendario')`,
  `route('palestras.show', slug)`, accessor `iniciais`, `foto_url`/`foto_thumb_url`.

## 5. Estrutura da página (seções, na ordem)

Container de leitura: `max-w-[1160px]`, `px-6`.

### 5.1 Hero (roxo + partículas + onda SVG)
Seção full-width, gradiente roxo escuro + `<x-ui.particulas>` + **onda SVG** clara na base
(`viewBox="0 0 1440 120"`, `fill` = `surface` `#F6F6F6`). Dentro:
- **Breadcrumb** Início › Palestrantes › **nome** (`aria-current="page"`).
- **Foto** em moldura translúcida (`bg-white/8 border-white/16 rounded-[22px] p-2`), retrato
  **3:4** (`aspect-[3/4] rounded-[15px] object-cover`). Sem foto → **iniciais** sobre
  `cema-grad-{{ $palestrante->id % 8 }}`.
- Eyebrow mono "PALESTRANTE · CEMA"; **H1** `font-display` (clamp ~2.2→3.4rem); tick dourado;
  **`chamada`** `font-serif italic text-white/85` **apenas se preenchida**; **chips das áreas**
  (top-N por frequência, `rounded-pill bg-white/10 border-white/20`, bolinha `.cema-dot-*`) —
  **clicáveis** (filtram o grid), `aria-pressed`.
- **CTA "Calendário de Palestras"** (card `bg-white/10 border-white/22 rounded-2xl`, ícone
  dourado) → `route('palestras.calendario')`.

### 5.2 Estatísticas (4 tiles)
`grid auto-fit minmax(130px,1fr)`; fundos suaves alternados (creme/azul/verde/cinza): valor
grande roxo + rótulo pequeno. Todos **reais**:
- **Palestras** = `$palestras->count()` (publicadas ministradas — igual ao grid).
- **Temas abordados** = nº de `assuntos` distintos.
- **Ativo no CEMA desde** = **menor ano** de `data_da_palestra` (null-safe → `—` se 0 palestras/sem data).
- **Palestras online** = `round(online / total * 100)`% com **guarda de divisão por zero**
  (`—` quando `total = 0`).

### 5.3 "Sobre {nome}" (card branco)
Tick dourado + título. Prosa de leitura (`{!! $palestrante->bio !!}` — já sanitizado no set do
model — com classe de prosa: ~15.5px, `line-height` alto). Esconder a seção se `bio` vazia.

### 5.4 Palestras do palestrante
- Cabeçalho: tick + H2 "Palestras de {nome}" + **contador** reativo ("N palestras" / "N em {tema}").
- **Barra de filtro** (card branco): chips (Todas + um por tema; ativo = roxo, `aria-pressed`)
  + **select de ordenação** (Mais recentes · Mais antigas · Título A–Z), com `<label>`.
- **Grid** `repeat(auto-fill,minmax(258px,1fr))`, gap 20, reaproveitando **`<x-palestra.card>`**
  (badge de formato, chip de assunto, cema-grad, data, "Ver"; co-palestrante já tratado pelo card).
  Cada card carrega `data-assuntos` (slugs), `data-recent`/`data-old`/`data-az` (ranks) e as
  diretivas Alpine `x-show` (filtro) + `x-bind:style` (`order:` para reordenar via CSS `order`).
- **Empty state** quando o filtro zera (mensagem + botão "Ver todas" que reseta para "Todas").

### 5.5 Sidebar (sticky `top-24`; estática < ~desktop-sm)
- **Próxima palestra:** card gradiente roxo "EM DESTAQUE" com avatar (foto ou **iniciais** sobre
  `cema-grad-{{ $palestrante->id % 8 }}`), nome, data·hora (`translatedFormat` pt-BR), título e
  botão dourado "Ver palestra" → detalhe da palestra. **Ocultar se não houver futura** (sem
  fallback para "mais recente"; big-bang).
- **Áreas de atuação:** lista dos temas (bolinha `.cema-dot-*` + label + contagem); **clique
  filtra** o grid (mesmo estado dos chips), `aria-pressed`.
- **Compartilhar palestrante:** botões redondos Facebook (`sharer`), WhatsApp (`wa.me`) e
  **copiar link** (`navigator.clipboard`) — client-side, `aria-label`.
- **Contato (preservar comportamento existente):** a single atual exibe e-mail/telefone
  condicionados às flags `mostrar_email`/`mostrar_telefone` (com testes que asseguram isso —
  `PalestrantePerfilTest`). O redesenho **não pode regredir** essa função: exibir um card
  "Contato" na sidebar **apenas quando** houver e-mail visível ou telefone visível, com cada
  linha gated pela sua flag. (O e-mail nunca aparece com `mostrar_email=false`.)

## 6. Interações (Alpine — estado único compartilhado)

Um único `x-data="palestranteDetalhe({ itens, areas })"` no wrapper que envolve **hero +
conteúdo + sidebar**, para os três pontos de filtro compartilharem o estado:

- Estado: `area` (`'todos'` | slug do assunto), `sort` (`'recent'` | `'old'` | `'az'`).
- **Filtro:** chips do hero **+** chips da barra **+** lista lateral setam `area`; clicar no
  ativo volta a `'todos'`. Cards com `x-show` (visível se `area==='todos'` ou o assunto está
  no card). `aria-pressed` reflete o ativo em todos os pontos.
- **Ordenação:** `select` altera `sort`; os cards reordenam por **CSS `order`** (ranks
  pré-computados no servidor: `recent` = `data_da_palestra` desc nulls-last; `old` = asc
  nulls-last; `az` = título A–Z, `localeCompare` pt).
- **Contador/empty:** derivados reativamente de `itens.filter(visível)`; label "N em {tema}"
  usa o nome do assunto de `areas`.
- **Compartilhar:** Facebook/WhatsApp abrem URL de share; copiar usa `navigator.clipboard`
  (com feedback textual acessível).
- **Sem JS:** todos os cards aparecem (ordem "recentes"), filtro/ordenação inertes — página
  100% utilizável (SSR).

## 7. SEO / A11y / Responsivo / Performance

- **SEO:** `<title>`/description já pelo `<x-layout.app>`; no `<x-slot:head>`: **JSON-LD
  `Person`** (nome, image só quando há foto, description da bio, url, worksFor CEMA — como já
  faz a single atual), **`<link rel="canonical">`** por slug, **`og:image`** com a foto real
  quando houver (senão omite; o site não tem OG image padrão).
- **A11y:** chips/botões com `aria-pressed`/`aria-label`; `<label>` no select; foco visível;
  contraste ok; `<x-ui.particulas>` já desliga animação em `prefers-reduced-motion`.
- **Responsivo (mobile-first):** abaixo de `desktop-sm` a sidebar deixa de ser sticky e vai
  abaixo do conteúdo; o hero empilha (foto → texto → CTA); grid colapsa colunas.
- **Performance:** HTML enxuto; imagens `loading="lazy"` (o card já faz); sem JS pesado
  (só o pequeno componente Alpine); CSS no bundle existente.

## 8. Guardrails do projeto (herdados)

- **Migração aditiva** só via `php artisan migrate` (forward). 🚫 **PROIBIDO**
  `migrate:fresh`/`refresh`/`wipe`/`reset`/seed destrutivo — o dev tem dados reais
  (127 palestras + 44 posts + palestrantes).
- **Portabilidade SQLite:** `min(ano)`, contagens, distintos e ranks **em PHP** (coleção).
  Nada de `selectRaw`/`YEAR()`/`DATE_FORMAT()`.
- **Big-bang:** esconder quando vazio (chamada, próxima, bio, stats null-safe) — sem "em breve".
- **Tailwind v4:** tokens via `var(--color-*)` (nunca `theme()`); `text-text-ink` (não
  `text-text` = #000).
- **Autoria** nos PHP novos (`Thiago Mourão — https://github.com/MouraoBSB — 2026-07-01`);
  **pt-BR** com acentos; commits terminam com `Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>`.
- **DoD:** suíte completa + `pint --test` verdes; `npm run build`; `docker compose restart app
  worker`; conferência manual no `localhost`. CI roda a suíte completa em todo PR/push.

## 9. Fora de escopo (YAGNI nesta fatia)

- Filtro/ordenação via querystring/SEO por tema (o handoff cita como alternativa; não faremos —
  estado só no cliente).
- Foto default de OG do site (não existe hoje).
- Redirect do slug antigo do WordPress (a rota `{slug}` já existe; mapeamento de slugs legados
  é tema de outra fatia de migração/SEO).
- Qualquer taxonomia de "área" própria (adiada; hoje áreas = `assuntos`).
