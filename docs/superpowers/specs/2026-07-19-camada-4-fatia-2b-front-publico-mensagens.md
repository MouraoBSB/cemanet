# Spec — Camada 4 · Fatia 2B · Front público das Mensagens (listagem/detalhe + lista/perfil de autores)

> Autoria: Thiago Mourão — https://github.com/MouraoBSB — 2026-07-19
> Enquadramento travado com o dono no kickoff da Fatia 2B (front público das Mensagens Mediúnicas). Este spec
> **não** improvisa além das decisões travadas; **cada afirmação sobre o terreno foi verificada contra o código
> real** (evidência `arquivo:linha` no §3, levantada por 8 leitores paralelos + releitura direta dos 6 molds
> carga-de-prova) e o **desenho das 4 páginas foi mapeado contra os 4 `design_handoff_*`** (README + protótipo
> `.dc.html` + screenshots, §4). O protótipo é **React-em-runtime (dc-runtime)** — **referência visual, não
> código-alvo**; recriar na stack (Blade + Livewire + Tailwind v4, tokens `@theme`), **sem copiar HTML**. Pontos
> em aberto (divergências handoff↔modelo real e escolhas do dono) estão no §13 para o **passe adversarial**.
> Destino: **SPEC** — vai ao passe adversarial **antes** de virar plano de implementação. **NÃO implementar ainda.**
> Base: `origin/main` (HEAD `161b502`, PR #37 — **Fatia 2A mesclada**; a Fatia 2B ramifica daqui). Suíte baseline:
> **~972 testes** (a `MEMORY.md` registra 972 pós-2A; medir com `artisan test --list-tests` antes de começar).
> Fundação: a **Fatia 2A** (`Mensagem` entidade+dados: `scopePublica`, `autores()` N:N, `relacionadas()` simétrica,
> coleção `pictografia`, `contexto`, `link_arquivo`/`liberar_download`, `data_recebimento`, enum `FormatoMensagem`)
> + a **Fatia 1** (`AutorEspiritual`: `scopeAtivo`, `foto_url`/`foto_thumb_url`, trait `TemIniciais`, `chamada`/`bio`).

---

## 0. Recorte: por que esta é a fatia "2B" (e o que fica na 3/5)

A Fatia 2 (grande) foi partida em duas no kickoff da Camada 4:

- **2A (mesclada, PR #37):** `Mensagem` como **entidade + dados** — model, enum, migrations, Camada 1, importação
  das 179 e CRUD no `/admin`. Sem superfície de site.
- **2B (ESTE spec):** o **front público** — as **4 páginas** recriadas dos `design_handoff_*` na stack real,
  consumindo **só a camada Públicas** (`Mensagem::publica()` — filtro FIXO) e os **autores ativos**
  (`AutorEspiritual::ativo()`). É a fatia de **apresentação**: nenhuma migration de dados, nenhum seeder, nenhuma
  mudança no núcleo de autorização.

**A 2B usa SÓ o filtro fixo `publica()`; NADA de visibilidade por papel/nível.** Os 6 níveis, o resolvedor por
papel, badge de nível + cadeado, legenda de bolinhas, "Direcionada"/destinatários, `noindex` condicional e o
403/redirect-login são **Fatia 3** (F3). **Consequência dura:** como tudo o que a 2B mostra é Pública, os **badges
de nível e a legenda somem** dos cards/single, e **mensagem não-pública = 404** (nunca 403, nunca vaza a
existência). O engajamento (favoritar, lida/não-lida, "curtir do autor", "vistas recentemente") é **Fatia 5** (F5)
— nada disso agora.

---

## 1. Contexto e objetivo

A Camada 4 é o módulo **Mensagens Mediúnicas**: o corpo psicografado/psicofônico/pictográfico, atribuído a um ou
mais **autores espirituais**. A 2A criou a entidade e migrou as 179 mensagens. Esta **Fatia 2B** entrega a
**superfície pública** dessas mensagens e dos autores.

**Objetivo:** recriar, na stack, **4 páginas públicas**, consumindo o modelo já mesclado:

1. **Lista de mensagens** — `GET /mensagens-mediunicas` (`mensagens.index`): grade/lista de cards com filtros
   (De/Até por `data_recebimento`, Autor, Ordenar), toggle grade↔lista, paginação, contador, estado vazio.
2. **Single de mensagem** — `GET /mensagens-mediunicas/{slug}` (`mensagens.show`): 3 corpos por formato, faixa de
   contexto, card do(s) autor(es), download, "Recebidas no mesmo dia" + "Relacionadas", interações client-side.
3. **Lista de autores** — `GET /autores-espirituais` (`autores.index`): grade de autores ativos com contagem de
   mensagens **públicas** + sidebar institucional/mini-stats/destaque.
4. **Perfil de autor** — `GET /autores-espirituais/{slug}` (`autores.show`): hero, grade das mensagens **públicas**
   do autor (filtro+ordenação client-side), 3 tiles de stats, sidebar (destaque/formatos/compartilhar), rodapé
   estático de login.

**A 2B clona moldes de front já testados**, sem inventar arquitetura:
- **listagem com filtros** (`#[Url]`, toggle, paginação): o **`Palestras\Lista`** (Livewire);
- **single por slug** (`firstOrFail`→404, ordenação client-side, meta/OG na view): o **`PalestranteController`** +
  **`PalestraController`**;
- **classe de stats/resumo** (agregação em PHP, portável): o **`ResumoPerfil`**;
- **grade com contagem**: o **`Palestrantes\Lista`** (`withCount`);
- **SEO** (sitemap por scope, meta/canonical/OG por slot, 301 do WP): o **`SitemapController`** + as views
  `palestrantes/show`/`eventos/show` + o bloco 301 de Eventos.

**A única mudança de DOMÍNIO da 2B** é criar a relação inversa **`AutorEspiritual::mensagens()`** (a 2A só criou
`Mensagem::autores()`). Sem migration (o pivô `mensagem_autor_espiritual` já existe). Tudo o mais é
controller/Livewire/view/rota/sitemap.

---

## 2. Decisões travadas (não reabrir)

Do kickoff da 2B (dono) + heranças da 2A:

1. **Filtro público FIXO:** toda query da 2B parte de `Mensagem::publica()` (`status='publicado' AND
   nivel='publico'`) — **nunca** um scope de visibilidade por papel. Single não-pública = **404** (jamais 403).
2. **Autor é N:N** (`Mensagem::autores()`, pivô `mensagem_autor_espiritual`): 0, 1 ou vários. O card exibe 1+
   autores ou **"Sem assinatura"** quando 0 (51 publish têm 0 autor — 2A §4.5). **Não** assumir `belongsTo`/
   `autor_espiritual_id` (o handoff sugere errado).
3. **Relacionadas** exibidas no single = **curadas** (`Mensagem::relacionadas()`, simétrica) **+** seção **"Recebidas
   no mesmo dia"** (query por `data_recebimento`, públicas). Ambas as seções existem.
4. **`contexto`** exibido no front é **texto puro manual** → **escapado** com `{{ }}` (nunca `{!! !!}`).
5. **Download**: só se `liberar_download` **e** `link_arquivo` presente; o `link_arquivo` **já vem normalizado**
   (mutator `LinkDrive`) — **não** re-tratar. Pictografia: download das **imagens locais** da MediaLibrary.
6. **Tema só CLARO.** O site não tem dark mode — ignorar a coluna "escuro" dos handoffs de autor; sem toggle nem
   tokens `dark:`.
7. **URL da mensagem = `/mensagens-mediunicas/{slug}`** (plural, `{slug}` cru + `->where`, nomes `mensagens.*`) +
   **301 do WP** (`/mensagem-mediunicas/...`). Autores em `/autores-espirituais/{slug}` (nomes `autores.*`).
8. **Curtir do autor → Fatia 5.** A 2B é **só leitura**: não criar coluna/tabela de curtidas, não reusar
   `Palestras\Curtir`, remover o tile "Curtidas".
9. **Sem F3 na 2B:** como tudo é Pública, os badges de nível, o cadeado e a legenda de bolinhas **somem**; zero PII
   de destinatários; nenhum 403/redirect por nível.
10. **Recriar na stack** reusando `x-layout.app`, `x-ui.particulas` e tokens `@theme` — **não** copiar o HTML do
    `.dc.html` nem embutir SVG gigante inline (envelope/onda viram parcial/componente).
11. **Sem `migrate:fresh`/`refresh`/`wipe`/`reset` nem seed destrutivo** no dev ([[nunca-migrate-fresh-no-dev]]).
    (Nem se aplica: a 2B não tem migration/seeder.)

---

## 3. Terreno confirmado por leitura (não presumir diferente)

Verificado no código em 2026-07-19 (base `161b502`). **Docblock não é evidência** — o que segue foi lido no fonte.

### 3.1 Os modelos-molde já prontos (2A/Fatia 1) — a 2B só CONSOME (exceto 1 relação)

- [Mensagem](../../../app/Models/Mensagem.php): `$table='mensagens'`; `scopePublica`
  ([:63-68](../../../app/Models/Mensagem.php#L63-L68)) = `status='publicado' AND nivel='publico'` — o comentário
  ([:62](../../../app/Models/Mensagem.php#L62)) diz "filtro FIXO … nunca um scope de visibilidade por papel; isso é
  Fatia 3". **É o portão único da 2B.** `autores()` **BelongsToMany**
  ([:75-78](../../../app/Models/Mensagem.php#L75-L78)); `relacionadas()` **BelongsToMany self simétrica**
  ([:80-114](../../../app/Models/Mensagem.php#L80-L114)); coleção `pictografia` **multi** com conversões `web`/`thumb`
  ([:116-120](../../../app/Models/Mensagem.php#L116-L120)); `corpo` **HTML único** saneado por `clean(...,'conteudo')`
  ([:122-127](../../../app/Models/Mensagem.php#L122-L127)); `data_recebimento` Attribute Carbon↔`Y-m-d`
  ([:133-139](../../../app/Models/Mensagem.php#L133-L139)); `link_arquivo` normalizado por `LinkDrive::paraDownload`
  no set ([:145-150](../../../app/Models/Mensagem.php#L145-L150)); `liberar_download` cast bool
  ([:54-60](../../../app/Models/Mensagem.php#L54-L60)); `formato` → `FormatoMensagem`. **A Mensagem já tem tudo — a
  2B não a edita.**
- [AutorEspiritual](../../../app/Models/AutorEspiritual.php): `scopeAtivo`
  ([:36-39](../../../app/Models/AutorEspiritual.php#L36-L39)); `foto_url` (`getFirstMediaUrl('foto','web')`) e
  `foto_thumb_url` ([:53-66](../../../app/Models/AutorEspiritual.php#L53-L66)) — **existem** (corrige a dúvida do
  leitor de rotas: og:image do autor é viável); trait `TemIniciais`
  ([:20](../../../app/Models/AutorEspiritual.php#L20)); `chamada`/`bio` fillable
  ([:27](../../../app/Models/AutorEspiritual.php#L27)). **FALTA a relação inversa `mensagens()`** — só existe a ida
  `Mensagem::autores()`. ⇒ **a 2B cria `AutorEspiritual::mensagens(): BelongsToMany`** sobre `mensagem_autor_espiritual`
  (chaves `autor_espiritual_id`/`mensagem_id`) — única mudança de domínio (§6.1).
- [FormatoMensagem](../../../app/Enums/FormatoMensagem.php): `Psicografia`/`Psicofonia`/`Pictografia`, `->rotulo()`,
  `::opcoes()`.

### 3.2 O controller-molde (single por slug, 404, ordenação client-side)

[PalestranteController::show(string $slug): View](../../../app/Http/Controllers/PalestranteController.php#L29-L69):
`Palestrante::query()->ativo()->where('slug',$slug)->firstOrFail()`
([:31-34](../../../app/Http/Controllers/PalestranteController.php#L31-L34)) — o scope de publicação vem **antes** do
`firstOrFail`, então não-público retorna **404**, não 403. Ordenação "recentes" **em PHP** (portável), nulos por
último ([:37-42](../../../app/Http/Controllers/PalestranteController.php#L37-L42)):
`->get()->sortByDesc(fn ($p) => $p->data...?->getTimestamp() ?? PHP_INT_MIN)->values()`. `new ResumoPerfil($palestras)`
([:44](../../../app/Http/Controllers/PalestranteController.php#L44)). **Payload da ordenação client-side (Alpine)**
([:54-58](../../../app/Http/Controllers/PalestranteController.php#L54-L58)): array enxuto `{id, titulo, ts}` que a
view entrega ao Alpine para reordenar a grade **no cliente** sem round-trip. Passa dados por **array associativo
explícito** ([:60-68](../../../app/Http/Controllers/PalestranteController.php#L60-L68)). **Nenhum meta/OG no
controller** — isso vive na view (§3.5). [PalestraController](../../../app/Http/Controllers/PalestraController.php)
adiciona o molde de **anterior/próxima** e de **relacionadas por taxonomia com fallback** (para "mesmo dia" +
"relacionadas").

### 3.3 O Livewire-molde de listagem com filtros (`#[Url]`, toggle, paginação)

[Palestras\Lista](../../../app/Livewire/Palestras/Lista.php): `extends Component`, `use WithPagination`
([:17-19](../../../app/Livewire/Palestras/Lista.php#L17-L19)). Props **`public string`** com `#[Url(as:…, except:…)]`
— nome PHP pode divergir do query-param (`$dataDe`→`de`), `except:` = default (URL limpa no estado neutro)
([:21-46](../../../app/Livewire/Palestras/Lista.php#L21-L46)). `updated()` chama `resetPage()` só para filtros reais
via whitelist ([:48-53](../../../app/Livewire/Palestras/Lista.php#L48-L53)) — **`visao` fora da whitelist** (trocar
visão não reseta paginação). `limparFiltros()` ([:55-60](../../../app/Livewire/Palestras/Lista.php#L55-L60)),
`removerFiltro()` com mapa param→prop ([:62-73](../../../app/Livewire/Palestras/Lista.php#L62-L73)),
`alternarVisao()` com whitelist e **sem** `resetPage` ([:75-79](../../../app/Livewire/Palestras/Lista.php#L75-L79)),
`filtrosAtivos()` resolvendo nome por slug ([:81-108](../../../app/Livewire/Palestras/Lista.php#L81-L108)).
`render()` = pipeline `when()` com **eager-load** (`->with([...])`), **validação da entrada da URL antes do SQL**
(`Carbon::hasFormat(...,'Y-m-d')`, `ctype_digit`), ordenação `orderByRaw('col IS NULL, col …')` e `->paginate(9)`
([:122-145](../../../app/Livewire/Palestras/Lista.php#L122-L145)). O **contador** e o **estado vazio** vêm de graça
do paginator/`@forelse` na Blade. [Palestrantes\Lista](../../../app/Livewire/Palestrantes/Lista.php) é o molde de
**grade com `withCount([alias => closure])`** + `match($ordenar)` com `default` + `->paginate(12)`.

### 3.4 A classe de stats/resumo-molde (agregação em PHP, portável)

[ResumoPerfil](../../../app/Support/Palestrantes/ResumoPerfil.php): `__construct(private Collection $palestras)`
([:22](../../../app/Support/Palestrantes/ResumoPerfil.php#L22)) — recebe a coleção **já materializada** (o controller
faz `->get()`); **nenhuma query dentro da classe** (100% testável, portável SQLite/MySQL). `totalPalestras()`,
`ultimaPalestra(): ?Carbon` (`pluck->filter->max`, [:34-41](../../../app/Support/Palestrantes/ResumoPerfil.php#L34-L41)),
`percentualOnline()` com guarda de zero, e o coração **`areas()`**
([:63-80](../../../app/Support/Palestrantes/ResumoPerfil.php#L63-L80)): `flatMap → groupBy → map(count + cor id%8) →
sortByDesc('count') → values` — o **primeiro item é o predominante**. `areasHero()` = `areas()->take(CHIPS_HERO)`
([:82-86](../../../app/Support/Palestrantes/ResumoPerfil.php#L82-L86)). ⇒ **clonável quase 1:1** para o resumo do
autor (total/última/por-formato/predominante — §6.5).

### 3.5 Rotas, 301, sitemap e meta/OG (o SEO da casa)

- **[routes/web.php](../../../routes/web.php)** — ordem canônica: estáticas → 301 → `.ics` → `{slug}` com
  `->where`; `Route::fallback` **sempre por último** ([:126-132](../../../routes/web.php#L126-L132)). Regex de slug
  **`[a-z0-9-]+`**. **Perfil por slug sem `.ics`** (molde do autor):
  `Route::get('/palestrantes/{slug}', [...])->name('palestrantes.show')` **sem** `->where`
  ([:75-76](../../../routes/web.php#L75-L76)) — mas Eventos/Palestras **usam** `->where('slug','[a-z0-9-]+')`
  ([:64-66](../../../routes/web.php#L64-L66),[:88-89](../../../routes/web.php#L88-L89)); a 2B **usa** (mais seguro).
- **301 (molde EXATO — Eventos, [:93-96](../../../routes/web.php#L93-L96)):**
  ```php
  Route::permanentRedirect('/_evento', '/eventos');                         // archive: destino fixo
  Route::get('/_evento/{slug}', fn (string $slug) =>                        // single: preserva o slug
      redirect()->route('eventos.show', ['slug' => $slug], 301))
      ->where('slug', '[a-z0-9-]+');
  ```
- **[SitemapController::index(): Response](../../../app/Http/Controllers/SitemapController.php#L15-L36)** — carrega
  coleções por **scope** (`Post::publicado()`, `Evento::publicado()->visiveisPara(null)` —
  [:28-31](../../../app/Http/Controllers/SitemapController.php#L28-L31), com o comentário "só o que um visitante
  anônimo vê … nada restrito vaza"), `compact(...)` para `view('sitemap')`, header `application/xml`.
  ⇒ **ponto de inserção:** somar `Mensagem::publica()` e `AutorEspiritual::ativo()` (§6.7). Há testes:
  [EventoSitemapTest](../../../tests/Feature/Front/EventoSitemapTest.php) (público entra, restrito fora) —
  **molde** para os da 2B.
- **[x-layout.app](../../../resources/views/components/layout/app.blade.php)** — `@props(['title','description'])`
  ([:1](../../../resources/views/components/layout/app.blade.php#L1)); o layout já emite `<title>`+sufixo `— CEMA`,
  `meta description`, `og:title`, `og:description`, `og:type=website`, `og:url=url()->current()`
  ([:12-17](../../../resources/views/components/layout/app.blade.php#L12-L17)). **NÃO** emite `canonical` nem
  `og:image` — cada single injeta via **slot `head`** ([:26](../../../resources/views/components/layout/app.blade.php#L26)).
  Molde de injeção: `palestrantes/show.blade.php` (canonical + og:image condicional + JSON-LD `Person` via
  `json_encode(array_filter([...]), JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_HEX_TAG)`) e
  `eventos/show.blade.php:29-33` (`og:image` condicional). **Breadcrumb** é markup manual dentro do hero (não é
  slot/prop). [x-ui.particulas](../../../resources/views/components/ui/particulas.blade.php) = **sem props**, primeiro
  filho da `<section>` do hero (`relative overflow-hidden`); `<x-ui.particulas />`.

### 3.6 O selo de VISIBILIDADE existe — e a 2B NÃO usa (é F3)

[x-ui.selo-visibilidade](../../../resources/views/components/ui/selo-visibilidade.blade.php): `@props(['rotulo','cor'])`
— pill "ponto colorido na cor do nível + rótulo". É o selo genérico de **visibilidade por nível**. A 2B trata
`nivel` como string BRUTA e filtra por `scopePublica` (fixo); **exibir qualquer selo de nível vazaria a existência
de mensagens restritas**. ⇒ **a 2B não renderiza selo de nível algum** (os "selos" que a 2B usa são de **formato**,
não de nível — §6.6). O `x-ui.selo-visibilidade` fica reservado para a F3.

### 3.7 O componente a NÃO reusar

[Palestras\Curtir](../../../app/Livewire/Palestras/Curtir.php): componente de **engajamento (F5)** que **escreve no
banco** a cada clique (`increment`/`decrement`, rate-limit por IP). A 2B é **leitura pública**; nada em
`ResumoPerfil`/`Lista`/controllers depende de `Curtir`. ⇒ clonar os molds de listagem/single **não** implica clonar
`Curtir`. **Fora da 2B.**

---

## 4. Estudo dos handoffs (as "medições" — desenho ↔ modelo real, 2026-07-19)

Cada handoff = README + protótipo `.dc.html` (React/dc-runtime — **referência, não copiar**) + screenshots. Abaixo,
a anatomia a recriar e as **divergências handoff↔modelo** que este spec já corrige (⚠️ = armadilha; §13/C).

### 4.1 Lista de mensagens (`mensagens.index`)

- **Hero** roxo (gradiente radial+linear) com `<x-ui.particulas>` + **envelope dourado animado** (SVG ~20 nós +
  raios + glow; anima em ~2,2s; **some abaixo de 1280px**; respeitar `prefers-reduced-motion`) → **parcial**
  `x-mensagem.envelope-hero`; kicker mono, H1, régua dourada, subtítulo; **card contador** translúcido (total de
  públicas). **Breadcrumb** `Início › Mensagens Mediúnicas`.
- **Filtros** (card branco): **De**/**Até** (`type=date` → `data_recebimento`), **Autor** (select), **Ordenar**
  (`recente`/`antiga`/`az`), **toggle grade↔lista** (segmented), **chips de filtros ativos** removíveis + "Limpar
  tudo", **contador** ("Mostrando X–Y de N"), **paginação** (default **9**/página; grade `auto-fill,minmax(280px,1fr)`
  → 3→2→1), **estado vazio** (card tracejado + "Limpar filtros").
- **Card (grade)**: título (Work Sans), **trecho** 2 linhas (Roboto Slab 300), **autor** (avatar-iniciais dourado
  **ou "Sem assinatura"** itálico), **data** (`dd mmm aaaa` pt-BR), **formato** (rótulo mono), CTA "Ler mensagem".
  **Card (lista)**: faixa lateral, meta (formato + data), título, **"por {Autor}"**/"Sem assinatura", CTA "Ler".
- **"Veja também"** (pílulas para outras seções do site).
- ⚠️ **EXCLUIR (F3):** barra/badge de **nível** colorido, **cadeado**, **legenda de bolinhas**, persona
  "direcionadas"/"Área pessoal", `countLabel` variável por nível. ⚠️ **EXCLUIR (F5):** ícone **lida/não-lida** e sua
  legenda; favoritar não existe no protótipo. ⚠️ **NÃO PORTAR:** barra "Demonstração·ver como", toast de protótipo,
  qualquer dark-mode (é do runtime do editor, não da página).
- ⚠️ **C-A (card sem miniatura):** na lista, **pictografia não exibe miniatura** — só rótulo + trecho textual
  (fiel ao handoff). (No **perfil** do autor, o card de pictografia **tem** miniatura — §4.4; divergência entre
  handoffs, ambos respeitados.)

### 4.2 Single de mensagem (`mensagens.show`) — o núcleo

- **Hero**: kicker "Mensagem Mediúnica · {formato}", **badge** (na 2B **fixo "Pública", sem cadeado** — F3 removido),
  H1 título, **chips**: autor(es) (N:N — 0/1/vários, link ao perfil), data ("Recebida em {data por extenso}"),
  formato, "CEMA". Breadcrumb `Início › Mensagens Mediúnicas › {título}`.
- **Faixa de contexto** (só se `contexto` não-vazio): `<strong>Contexto</strong> — {{ $mensagem->contexto }}` —
  **escapado** (texto puro, nunca `{!! !!}`).
- **Os 3 corpos** — ⚠️ **C-B (fato crítico de modelagem):** no modelo real, o corpo é **UM campo HTML único**
  (`Mensagem::corpo`, saneado por `clean(...,'conteudo')`). O protótipo estrutura psicografia em `paragraphs[]` e
  psicofonia em `blocks[sub/q/a]` — **dados que não existem separados**. Em produção os 3 formatos são **3
  renderizações/estilizações do MESMO `corpo`** (+ a MediaLibrary na pictografia):
  - **Psicografia** — `{!! $corpo !!}` numa `.cema-msg-prose` (Roboto Slab 300, `line-height` alto) + **assinatura**
    à direita (nome do(s) autor(es) + `casa`/`data_recebimento`; ⚠️ `casa` é constante **"CEMA"** — 2A §4.3 — não
    inventar cidade).
  - **Psicofonia = PROSA** (decisão do dono; O1 **fechado** — §13). **Mesma renderização da psicografia**
    (`{!! $corpo !!}` em `.cema-msg-prose`, Roboto Slab) + uma **nota** "Transcrição por psicofonia". A checagem de
    psicofonia prevista na 2A (§9.7 da 2A) **foi feita: 0/40** psicofonias do legado usam `table`/`div` ⇒ **não há**
    pergunta/resposta estruturada no dado (o `clean('conteudo')` removeria `div/table` de qualquer forma — 2A §12);
    os balões P&R do handoff **não têm o que os preencha**. **Não** criar infra de balões nem pré-processar o corpo
    (YAGNI); apenas estilizar `h3`/`blockquote` **se** aparecerem no HTML.
  - **Pictografia** — galeria das **imagens locais** da coleção `pictografia` (`$mensagem->getMedia('pictografia')`,
    WebP `web`; `object-cover`) + **download por imagem** (arquivo local, não Drive).
- **Card de autor(es)** — N:N: repete por autor (foto `foto_url` **ou** iniciais `TemIniciais`; `nome`; `bio`; link
  "Ver todas as mensagens de {nome}" → perfil). **"Sem assinatura"** quando `autores` vazio.
- **Download** — ⚠️ **C-C:** o anexo é um **link Drive** (`link_arquivo` já normalizado), **sem** colunas de
  nome/tamanho — o protótipo mostra `fileName · fileSize`, que **não existem**. ⇒ botão "Baixar arquivo" (só se
  `liberar_download && link_arquivo`), **sem** a linha de metadados de arquivo. Pictografia = download das imagens
  locais.
- **Sidebar** — **"Recebidas no mesmo dia"** (query `data_recebimento` == a da mensagem, `publica()`, exclui a
  própria) + **"Relacionadas"** (curada `relacionadas()`, `publica()`). ⚠️ **C-D:** o bloco "Vistas recentemente"
  do protótipo é **histórico do usuário (F5)** — **substituído** por "Relacionadas". O estado lida/não-lida dos
  itens (F5) **some** (ícone neutro).
- **Interações client-side (Alpine, não React):** **barra de progresso** de leitura (`@scroll.window.passive` + rAF),
  **A−/A+** (4 tamanhos, persistidos em `localStorage`), **Copiar** (`navigator.clipboard`), **Compartilhar**
  (`navigator.share` + fallback `wa.me`), toast. ⚠️ **EXCLUIR (F5):** botão **Favoritar**.
- ⚠️ **EXCLUIR (F3):** screenshot `05-direcionada` = **Mensagem Direcionada** (card "direcionada a" + **lista de
  destinatários com PII** + aviso "Área pessoal" + 403/redirect por nível). **Nada disso na 2B.** Mensagem
  não-pública = **404** via `Mensagem::publica()->where('slug',$slug)->firstOrFail()`.

### 4.3 Lista de autores (`autores.index`)

- **Hero** (mais baixo; **onda SVG branca** na base + `<x-ui.particulas>`), breadcrumb `Início › Mensagens ›
  Autores Espirituais`.
- **Grade** de cards de autor (`auto-fill,minmax(245px,1fr)` → 4→2→1): **foto 3:4** (`object-cover`) **ou**
  iniciais + gradiente; **nome**; **chamada** (Roboto Slab itálico); **`mensagens_count` SÓ das públicas**
  (`withCount(['mensagens' => fn($q)=>$q->publica()])`, com pluralização pt-BR "1 mensagem"/"N mensagens");
  **pontinhos de formato** = formatos **distintos das públicas**.
- **Sidebar institucional** (pode ser Blade estático + 3 números dinâmicos): card "Os Autores Espirituais"
  (título+régua+2 parágrafos fixos); **mini-stats** (`total de autores` = `AutorEspiritual::ativo()->count()`;
  `total de mensagens públicas`); card **"Autor em evidência"** (nome/chamada/link — **O3** para o critério).
- ⚠️ **EXCLUIR:** qualquer "curtir autor"/coluna `curtidas` (F5 — **não criar**); dark-mode (screenshot 03);
  barra de demonstração.
- **O7:** a lista **não tem filtro nem paginação** no handoff (~19 autores) — recomendação: listar todos (§13).

### 4.4 Perfil de autor (`autores.show`)

- **Hero**: **foto 3:4** (`foto_url` WebP) **ou** iniciais + gradiente; **breadcrumb dentro do hero**; nome; régua;
  **chamada**; **selos de formato** (um por formato distinto das **públicas**); **CTA "Ver mensagens ↓"** (scroll
  suave). ⚠️ **EXCLUIR** o botão "Curtir · N" (F5).
- **Grade das mensagens do autor** — **só públicas** (`$autor->mensagens()->publica()`, ordenada por recente em PHP,
  molde `PalestranteController::show`): **chips de formato** (Todos/Psicografia/Psicofonia/Pictografia) + **Ordenar**
  (recente/antiga/az), **client-side** (estender o Alpine para **filtrar+ordenar**, §6.6). Card: se pictografia,
  **miniatura no topo** (`getFirstMediaUrl('pictografia','web'|'thumb')`); **selo de formato** com ícone
  (pena/ondas/moldura); título; **data** mono dourada; **trecho** 3 linhas.
- **3 tiles de stats** (das **públicas**): **total**; **formato predominante** (`rotulo()`); **última mensagem**
  (mês/ano). ⚠️ **EXCLUIR** o 4º tile "Curtidas" (F5) — ficam **exatamente 3**.
- **Sidebar**: **"Em destaque"** (mensagem **mais recente pública** do autor); **"Formatos"** (`groupBy('formato')`
  das públicas + contagem); **"Compartilhar autor"** (Facebook/WhatsApp/**copiar link** — client-side; molde
  `palestrantes/perfil/sidebar`).
- **Rodapé estático**: "Somente mensagens públicas. Há conteúdos restritos… **entre** para vê-los." → texto + link
  **estático** para `route('login')`, **sem** contagem de ocultas, cadeado ou lógica de nível (F3).
- ⚠️ **EXCLUIR:** curtir do autor + tile curtidas (F5); níveis/cadeado/legenda (F3); dark-mode.
- **404 se inativo:** `AutorEspiritual::query()->ativo()->where('slug',$slug)->firstOrFail()`.

---

## 5. Invariantes (cada um vira teste que reprova)

| # | Invariante | Teste (§9) |
|---|---|---|
| **I1** | **Lista só-Pública:** `mensagens.index` parte de `Mensagem::publica()` **sempre**; mensagem `pendente`/`despublicada`/`nivel≠publico` **nunca** aparece na grade, no contador nem no filtro de autor. | §9.2 |
| **I2** | **Single não-pública = 404:** `mensagens.show` de slug não-público (pendente/nível restrito/inexistente) retorna **404** (`firstOrFail` após `publica()`), **nunca 403**, sem vazar título/existência. | §9.3 |
| **I3** | **Autor N:N:** mensagem com **0** autores exibe **"Sem assinatura"** (card e assinatura da psicografia); com **1+**, exibe todos; o link vai ao perfil. Nenhuma suposição de `belongsTo`. | §9.3 |
| **I4** | **Contexto escapado:** `contexto` sai com `{{ }}`; um `contexto` com `<script>`/HTML aparece **escapado** no HTML renderizado (nunca `{!! !!}`). | §9.3 |
| **I5** | **Contagens/stats do autor só das públicas:** `mensagens_count` (lista), os 3 tiles, os selos/pontinhos de formato, "Em destaque" e "Formatos" (perfil) contam **apenas** `publica()` — uma mensagem restrita do autor **não** altera número algum. | §9.4/§9.5 |
| **I6** | **N+1 evitado:** a grade de `mensagens.index` faz **eager-load** de `autores` (`->with('autores')`); a grade de `autores.index` usa `withCount(['mensagens' => fn($q)=>$q->publica()])`. | §9.2/§9.4 |
| **I7** | **Download condicionado:** botão de anexo só quando `liberar_download === true` **e** `link_arquivo` não-vazio; `liberar_download=false` **não** renderiza o botão nem expõe o link. | §9.3 |
| **I8** | **Pictografia local:** no single, a galeria vem de `getMedia('pictografia')` (imagens locais WebP), com download por imagem; **não** usa `link_arquivo`. | §9.3 |
| **I9** | **Sitemap só público/ativo:** `sitemap.xml` inclui `mensagens.index` + cada `mensagens.show` de `Mensagem::publica()` e `autores.index` + cada `autores.show` de autor **`ativo()` COM ≥1 mensagem pública** (O5a); **exclui** mensagem não-pública, autor inativo **e autor ativo sem pública**. | §9.6 |
| **I10** | **301 do WP:** `/mensagem-mediunicas` → **301** `/mensagens-mediunicas`; `/mensagem-mediunicas/{slug}` → **301** `mensagens.show` (slug preservado); a rota compat vem **antes** do `{slug}` real e casa a regex `[a-z0-9-]+`. | §9.7 |
| **I11** | **Relação inversa:** `AutorEspiritual::mensagens()` (BelongsToMany, pivô `mensagem_autor_espiritual`) lê as mensagens do autor; `->publica()` encadeia; o perfil (grade/stats/destaque/formatos) consome **só** `mensagens()->publica()`. | §9.1/§9.5 |
| **I12** | **Perfil: inativo = 404, ativo-sem-pública = 200:** `autores.show` de autor `ativo=false`/inexistente retorna **404** (`ativo()->firstOrFail`); autor **ativo sem nenhuma pública** ainda retorna **200** por URL direta (só sai da grade/sitemap — O5a), com grade vazia e stats zerados. | §9.5 |
| **I13** | **SEO por página:** cada página passa `:title`/`:description` ao `x-layout.app`; os singles injetam `canonical` (via `route(...)`) + `og:image` **condicional** (`pictografia`→web / `foto_url`) + JSON-LD (`Article`/`CreativeWork` e `Person`) no slot `head`. | §9.8 |
| **I14** | **Zero F3/F5 na saída:** o HTML público **não** contém badge de nível, cadeado, legenda de bolinhas, card/lista de destinatários, botão favoritar, ícone lida/não-lida nem botão de curtir. | §9.3/§9.5 |
| **I-neutro** | Nenhuma asserção existente muda de cor (a 2B só **adiciona**). Suíte **~972** + novos, verde; `Pint` verde. | §9.9 |

---

## 6. Decisões de desenho

### 6.1 A relação inversa `AutorEspiritual::mensagens()` (única mudança de domínio)

Em [AutorEspiritual](../../../app/Models/AutorEspiritual.php), somar (espelhando `Mensagem::autores()`
[:75-78](../../../app/Models/Mensagem.php#L75-L78)):
```php
public function mensagens(): BelongsToMany
{
    return $this->belongsToMany(Mensagem::class, 'mensagem_autor_espiritual', 'autor_espiritual_id', 'mensagem_id');
}
```
Sem migration (o pivô existe desde a 2A). O escopo `publica()` encadeia direto: `$autor->mensagens()->publica()`.
**Não** adicionar cast/coluna. É a **única** edição de model da 2B.

### 6.2 Rotas + 301 (em `routes/web.php`, no molde de Eventos)

Bloco novo (posição: junto aos demais recursos públicos; o segmento base novo **difere** do WP → sem colisão com
`{slug}`):
```php
// Mensagens Mediúnicas (front público — só Públicas).
Route::get('/mensagens-mediunicas', [MensagemController::class, 'index'])->name('mensagens.index');
Route::get('/mensagens-mediunicas/{slug}', [MensagemController::class, 'show'])
    ->name('mensagens.show')->where('slug', '[a-z0-9-]+');

// Compat 301 do CPT WP 'mensagem-mediunicas' (singular) → base nova (plural).
Route::permanentRedirect('/mensagem-mediunicas', '/mensagens-mediunicas');
Route::get('/mensagem-mediunicas/{slug}', fn (string $slug) =>
    redirect()->route('mensagens.show', ['slug' => $slug], 301))->where('slug', '[a-z0-9-]+');

// Autores Espirituais (perfil por slug, sem .ics).
Route::get('/autores-espirituais', [AutorEspiritualController::class, 'index'])->name('autores.index');
Route::get('/autores-espirituais/{slug}', [AutorEspiritualController::class, 'show'])
    ->name('autores.show')->where('slug', '[a-z0-9-]+');
```
- **Fonte do 301 (O6):** o CPT do WP é **`mensagem-mediunicas`** (singular "mensagem" + plural "mediunicas") —
  confirmado em [LeitorMensagensMysql.php:26](../../../app/Importacao/LeitorMensagensMysql.php#L26) e
  [DB-LEGADO.md:118](../../../DB-LEGADO.md#L118). O **permalink** (`/mensagem-mediunicas/{slug}/`) é **inferido** do
  padrão dos CPTs migrados (não há JSON de registro do CPT; DB-LEGADO não traz o `rewrite`). **Slug preservado 1:1**
  ([ImportadorMensagens.php:53](../../../app/Importacao/ImportadorMensagens.php#L53), create-only do `post_name`; os
  39 sem slug são **pending** → nunca tiveram URL pública). Confirmar rewrite/trailing-slash/`has_archive` quando o
  túnel voltar (mesma classe do R3 da 2A).
- **Autores (O6):** a rota nova `/autores-espirituais/{slug}` == permalink presumido do CPT `autores-espirituais`
  → provável **identidade** (sem 301) ou o CPT **não tinha single público** no WP. Não cabear 301 de autor sem
  confirmar que existiu URL pública.
- Teste de compat clonando [PalestraUrlCompatTest]/[BlogUrlCompatTest] (padrão da casa) — §9.7.

### 6.3 `MensagemController` + `App\Livewire\Mensagens\Lista`

- **[Controller]** `App\Http\Controllers\MensagemController`:
  - `index(): View` → `view('mensagens.index')` (a view embute `<livewire:mensagens.lista />`; contagem total de
    públicas para o card do hero pode vir de `Mensagem::publica()->count()`).
  - `show(string $slug): View` → `Mensagem::query()->publica()->with(['autores', 'relacionadas' => fn ($q) =>
    $q->publica(), 'media'])->where('slug',$slug)->firstOrFail()`; monta: **"mesmo dia"** (`Mensagem::publica()
    ->whereDate('data_recebimento', $m->data_recebimento)->where('id','!=',$m->id)->with('autores')->get()` — só se
    houver data); **relacionadas** já eager-carregadas e filtradas por `publica()`; passa por **array associativo
    explícito**. **Sem** meta/OG no controller (vai na view).
- **[Livewire]** `App\Livewire\Mensagens\Lista` (molde `Palestras\Lista`), `use WithPagination`. Props `#[Url]`
  (o handoff da lista **não tem busca textual** — só data/autor/ordenar/visão):

  | Prop | Default | `#[Url]` |
  |---|---|---|
  | `$dataDe` | `''` | `as:'de', except:''` |
  | `$dataAte` | `''` | `as:'ate', except:''` |
  | `$autor` | `''` | `as:'autor', except:''` (slug do autor, ou o sentinela `'sem-assinatura'` — O5) |
  | `$ordenar` | `'recente'` | `as:'ordenar', except:'recente'` (`recente`/`antiga`/`az`) |
  | `$visao` | `'grid'` | `as:'visao', except:'grid'` |

  `updated()` reseta página para `['dataDe','dataAte','autor','ordenar']` (não `visao`); `alternarVisao()` sem
  `resetPage`; `filtrosAtivos()`/`removerFiltro()` (mapa `de→dataDe`, `ate→dataAte`); `limparFiltros()` preserva
  `visao`. `render()` (pipeline `when()`, **validação antes do SQL**):
  ```php
  Mensagem::query()->publica()->with('autores')
    ->when($this->dataDe !== '' && Carbon::hasFormat($this->dataDe,'Y-m-d'),
        fn ($q) => $q->whereDate('data_recebimento','>=',$this->dataDe))
    ->when($this->dataAte !== '' && Carbon::hasFormat($this->dataAte,'Y-m-d'),
        fn ($q) => $q->whereDate('data_recebimento','<=',$this->dataAte))
    ->when($this->autor === 'sem-assinatura', fn ($q) => $q->whereDoesntHave('autores'))
    ->when($this->autor !== '' && $this->autor !== 'sem-assinatura',
        fn ($q) => $q->whereHas('autores', fn ($a) => $a->where('autores_espirituais.slug', $this->autor)))
    ->when($this->ordenar === 'az',
        fn ($q) => $q->orderBy('titulo'),
        fn ($q) => $q->orderByRaw('data_recebimento IS NULL, data_recebimento '.($this->ordenar==='antiga'?'asc':'desc')))
    ->paginate(9);
  ```
  Opções do select de autor = `AutorEspiritual::whereHas('mensagens', fn ($q)=>$q->publica())->orderBy('nome')
  ->get(['nome','slug'])` **+** a opção **"Sem assinatura"** (O5). Contador/estado-vazio vêm do paginator/`@forelse`.

### 6.4 Views das mensagens + parciais dos 3 corpos

- `resources/views/mensagens/index.blade.php` — `<x-layout.app :title="'Mensagens Mediúnicas'" :description="…">`
  → hero (`<x-mensagem.envelope-hero/>` + `<x-ui.particulas/>` + breadcrumb) + `<livewire:mensagens.lista/>` +
  "Veja também" (só links de páginas que **existem** — O-nota).
- `resources/views/livewire/mensagens/lista.blade.php` — filtros, toggle, chips, contador, grade↔lista, paginação,
  vazio. Grade usa `<x-mensagem.card :mensagem="$m"/>`; lista usa `<x-mensagem.linha :mensagem="$m"/>`.
- `resources/views/mensagens/show.blade.php` — `<x-layout.app :title="$mensagem->titulo" :description="…">` + slot
  `head` (SEO, §6.7). Hero + faixa contexto (`{{ }}`) + **`@include` do corpo por formato** + card autor(es) +
  download + sidebar ("mesmo dia" + "relacionadas") + toolbar Alpine. Corpo:
  ```blade
  @switch($mensagem->formato)
    @case(\App\Enums\FormatoMensagem::Psicografia) @include('mensagens.corpos.psicografia') @break
    @case(\App\Enums\FormatoMensagem::Psicofonia)  @include('mensagens.corpos.psicofonia')  @break
    @case(\App\Enums\FormatoMensagem::Pictografia) @include('mensagens.corpos.pictografia') @break
  @endswitch
  ```
- `resources/views/mensagens/corpos/psicografia.blade.php` — `{!! $mensagem->corpo !!}` em `.cema-msg-prose` +
  assinatura (autor(es) + `casa`/`data_recebimento`; "Sem assinatura" se vazio).
- `.../psicofonia.blade.php` — **idêntico à psicografia** (`{!! $mensagem->corpo !!}` em `.cema-msg-prose`) + uma
  **nota** "Transcrição por psicofonia" (O1 fechado: prosa, sem balões — §4.2). Estiliza `h3`/`blockquote` se
  existirem.
- `.../pictografia.blade.php` — `@foreach ($mensagem->getMedia('pictografia') as $img)` `<figure><img
  src="{{ $img->getUrl('web') }}" loading="lazy">` + botão "Baixar" (**R3**: atributo `download` com **nome
  amigável** derivado do título — ex. `download="{{ Str::slug($mensagem->titulo) }}-{n}.jpg"` — apontando ao
  original `$img->getUrl()`).

> **Nota (O1):** `{!! $corpo !!}` é seguro **porque** o `corpo` já foi saneado no set por `clean(...,'conteudo')`
> ([Mensagem.php:122-127](../../../app/Models/Mensagem.php#L122-L127)) — é o mesmo contrato do Post/Evento/Autor.
> O `contexto`, por ser texto puro, **sempre** `{{ }}` (I4).

### 6.5 `AutorEspiritualController` + views + `ResumoAutor`

- **[Controller]** `App\Http\Controllers\AutorEspiritualController`:
  - `index(): View` — grade sem filtro/paginação (O7): **só autor ativo COM ≥1 mensagem pública** (O5a — decisão do
    dono: autor sem pública **some** da grade): `AutorEspiritual::query()->ativo()->whereHas('mensagens', fn ($q) =>
    $q->publica())->withCount(['mensagens' => fn ($q) => $q->publica()])->orderBy('nome')->get()`; mais os selos de
    formato por autor (distinct das públicas) + mini-stats + destaque (O3). Como **não** há filtros/paginação,
    **controller puro** (sem Livewire). ⚠️ O **perfil** (`show`, abaixo) segue acessível por URL direta a autor
    ativo **sem** pública — **200, não 404** (só sai da grade/sitemap — O5a).
  - `show(string $slug): View` — `AutorEspiritual::query()->ativo()->where('slug',$slug)->firstOrFail()`; puxa as
    **públicas** ordenadas em PHP (molde `PalestranteController::show`):
    `$publicas = $autor->mensagens()->publica()->with('media')->get()->sortByDesc(fn ($m) =>
    $m->data_recebimento?->getTimestamp() ?? PHP_INT_MIN)->values();` → `new ResumoAutor($publicas)`; `$itensFiltro`
    = `{id, titulo, ts, formato}` para o Alpine (filtro+ordenação client-side).
- **[Stats]** `App\Support\AutoresEspirituais\ResumoAutor` (clone de `ResumoPerfil`, agregação em PHP):
  `__construct(private Collection $mensagens)`; `total(): int`; `ultimaMensagem(): ?Carbon`
  (`pluck('data_recebimento')->filter()->max()`); `porFormato(): Collection` (`groupBy('formato')` → `{formato,
  rotulo, count, cor}` → `sortByDesc('count')`); `predominante(): ?FormatoMensagem` (primeiro de `porFormato`);
  `selos(): Collection` (formatos distintos, para o hero). O empate do predominante segue a ordem do enum (O-nota).
- `resources/views/autores/index.blade.php` — hero (onda + partículas) + grade `<x-autor.card :autor="$a"/>` +
  sidebar (card institucional estático + mini-stats + destaque).
- `resources/views/autores/show.blade.php` — `<x-layout.app :title="$autor->nome" :description="…">` + slot `head`
  (canonical + og:image=`foto_url` condicional + JSON-LD `Person`). Hero + 3 tiles + grade das públicas (chips +
  ordenar client-side, cards `<x-mensagem.card>` reusados) + sidebar (destaque/formatos/compartilhar) + rodapé
  estático de login.

### 6.6 Componentes, parciais e Alpine

- **Componentes Blade novos:** `x-mensagem.card` (grade — molde `x-palestra.card`) **com prop `:variante`
  (`'lista'` | `'perfil'`)** — **obrigatório (Consultor):** o card da **lista** é **sem miniatura**, trecho 2 linhas,
  data simples (§4.1/C-A); o do **perfil** tem **miniatura de pictografia**, data mono dourada, trecho 3 linhas
  (§4.4); a variante liga/desliga a miniatura e troca o estilo de trecho/data — reusar o componente **sem** variante
  quebraria um dos dois (alternativa: dois componentes). `x-mensagem.linha` (lista — molde `x-palestra.linha`),
  `x-mensagem.selo-formato` (**pílula + ícone pena/ondas/moldura + cores AA**; **compartilhado** entre
  lista/single/perfil), `x-mensagem.envelope-hero` (SVG animado pesado → parcial, CSS no tema, `prefers-reduced-motion`
  + `@media(max-width:1280px){display:none}`), `x-autor.card` (grade de autor).
  Reusar `<x-ui.particulas/>`. **Onda do hero:** reusar o componente/parcial existente do hero de palestrante se
  houver; senão criar `x-ui.onda-hero` (SVG leve). **Não** usar `x-palestra.badge-formato` (é formato de
  palestra, presencial/online — semântica diferente). **Nunca** `x-ui.selo-visibilidade` (F3, §3.6).
- **Alpine — single** (`resources/js/app.js`, novo `mensagemLeitura()`): progresso de leitura (scroll+rAF),
  A−/A+ com `localStorage`, copiar (`navigator.clipboard`), compartilhar (`navigator.share` + `wa.me`), toast.
- **Alpine — perfil** (estender o `palestranteDetalhe` existente **ou** clonar `autorMensagens({itens})`): o molde
  atual **só ordena** por CSS `order`; o perfil precisa **filtrar por formato (chips)** `+ ordenar` → adicionar
  `x-show` por `formato` do item + o mesmo padrão de reordenação. **Sem** round-trip Livewire (client-side puro,
  molde da casa).
- **CSS** (no tema/`app.css`): `.cema-msg-prose` (Roboto Slab), balões de psicofonia (sobre tags semânticas),
  keyframes do envelope, hover dos cards. Tokens via `var(--color-*)` (nunca `theme()`); `text-text-ink` (não
  `text-text`).

### 6.7 SEO — sitemap, meta/canonical/OG

- **Sitemap** — em [SitemapController::index](../../../app/Http/Controllers/SitemapController.php#L15-L36) somar
  `use App\Models\Mensagem; use App\Models\AutorEspiritual;` e:
  ```php
  $mensagens = Mensagem::publica()->orderByDesc('data_recebimento')->get(['slug','updated_at','data_recebimento']);
  // O5a: só autor ativo COM ≥1 pública entra no sitemap (perfil vazio não é indexável).
  $autores   = AutorEspiritual::ativo()->whereHas('mensagens', fn ($q) => $q->publica())
                   ->orderBy('nome')->get(['slug','updated_at']);
  ```
  incluir em `compact(...)`; em `resources/views/sitemap.blade.php`, dois blocos no molde de Eventos
  ([sitemap … `@foreach`]): a `<url>` da listagem (`mensagens.index`/`autores.index`) + `@foreach` de
  `mensagens.show`/`autores.show` (lastmod = `updated_at->toAtomString()`). (`Mensagem` **não** tem
  `data_publicacao` — usar `updated_at` no lastmod e `data_recebimento` só na ordenação.)
- **Meta/OG por página** (via `x-layout.app` + slot `head`, molde `palestrantes/show`):
  - `mensagens.show`: `:title="$mensagem->titulo"`, `:description` = `Str::limit(strip_tags($mensagem->contexto ?:
    $mensagem->corpo), 155)`; slot: `canonical` = `route('mensagens.show',$mensagem->slug)`; `og:image` **condicional**
    (`$mensagem->getFirstMediaUrl('pictografia','web')` se houver); JSON-LD `Article`/`CreativeWork` (author =
    nomes de `autores`).
  - `autores.show`: `:title="$autor->nome"`, `:description` = `Str::limit(strip_tags($autor->chamada ?: $autor->bio),
    155)`; slot: `canonical`; `og:image` **condicional** (`$autor->foto_url`); JSON-LD `Person`.
  - `mensagens.index`/`autores.index`: `:title` + `:description` **própria** por página (R1 — pt-BR, com o tema da
    página; **não** deixar cair no default institucional do layout); o layout cobre og:*.

### 6.8 A11y, responsivo, performance (guardrails herdados)

- **Mobile-first**; grades `auto-fill,minmax(...)` (3→2→1 / 4→2→1); sidebar deixa de ser `sticky` abaixo de
  `desktop-sm`; hero-decoração pesada some no mobile (envelope `<1280px`).
- **A11y:** breadcrumb `<nav aria-label>` com `aria-current="page"`; `<a>` inteiro clicável nos cards (foco visível,
  outline dourado); toggles com `aria-label`/`aria-pressed`; partículas/onda `aria-hidden`; **`prefers-reduced-motion`**
  desliga progresso/envelope/partículas; contraste `text-text-ink`.
- **Performance/SEO:** SSR por padrão + Alpine leve (client-side sem round-trip); **eager-load** (I6); imagens WebP
  `web`/`thumb` com `loading="lazy"`; HTML enxuto (orçamento do CLAUDE.md); canonical/OG/JSON-LD; 301 preserva SEO;
  contagens/distintos/min-ano **em PHP** (portabilidade SQLite×MySQL).

---

## 7. As peças (inventário)

**Novos (com cabeçalho de autoria — CLAUDE.md §8):**
`app/Models/…` — (nenhum model novo; só a relação em AutorEspiritual, §11) ·
`app/Http/Controllers/MensagemController.php` · `app/Http/Controllers/AutorEspiritualController.php` ·
`app/Livewire/Mensagens/Lista.php` · `app/Support/AutoresEspirituais/ResumoAutor.php` ·
`resources/views/mensagens/{index,show}.blade.php` + `corpos/{psicografia,psicofonia,pictografia}.blade.php` ·
`resources/views/livewire/mensagens/lista.blade.php` · `resources/views/autores/{index,show}.blade.php` ·
`resources/views/components/mensagem/{card,linha,selo-formato,envelope-hero}.blade.php` ·
`resources/views/components/autor/card.blade.php` · (se necessário) `components/ui/onda-hero.blade.php` ·
testes (§9).

**Editados (mínimo):** `app/Models/AutorEspiritual.php` (**+1 relação `mensagens()`** — §6.1) ·
`routes/web.php` (bloco `mensagens.*`/`autores.*` + 301) · `app/Http/Controllers/SitemapController.php` (2 coleções) ·
`resources/views/sitemap.blade.php` (2 blocos `@foreach`) · `resources/js/app.js` (Alpine: single + perfil) ·
`resources/css/app.css` (ou o tema) (prose/balões/envelope/hover).

**NÃO toca:** `Mensagem` (2A já completa — `scopePublica`/`autores`/`relacionadas`/`pictografia`/`contexto`/
`link_arquivo` prontos) · Policies/Camada 1/`MatrizCapacidades`/`/admin`/Resource/importação (o front público é
read-only por `publica()`/`ativo()`) · `Palestras\Curtir`/qualquer engajamento · `x-ui.selo-visibilidade` (F3) ·
os molds clonados (`ResumoPerfil`/`Palestras\Lista`/`PalestranteController` permanecem intactos).

---

## 8. Cutover (o que roda no deploy — do dono)

A 2B **não tem migration nem seeder** (é apresentação). Deploy padrão de front:
1. `git pull` (código) — sem novas dependências Composer.
2. `npm run build` (Vite, **no host** — o container não tem Node, [[npm-vite-no-host]]).
3. `php artisan optimize:clear` (route/view/config cache) + `docker compose restart app worker`
   ([[dev-opcache-restart-app-worker]]).
4. Nada de dados: as 4 páginas passam a servir `Mensagem::publica()`/`AutorEspiritual::ativo()`; o `sitemap.xml`
   passa a incluir mensagens/autores automaticamente; os 301 do WP entram no ar.

**Ciência:** os **2 publish sem nível** (2A §12) e as 47 pendentes **não** aparecem (fail-closed correto). Quando o
dono classificar o nível dos 49 sem-termo pela tela, elas entram/saem do público sem redeploy.

---

## 9. Plano de teste (TDD real, vermelho primeiro)

Feature tests de front usam `Tests\TestCase` + `RefreshDatabase`, factories da 2A (`Mensagem::factory()->publica()`,
`->pendente()`; `AutorEspiritual::factory()`), `Storage::fake('public')` onde houver mídia.

### 9.0 Ordenação (constraint)
`AutorEspiritual::mensagens()` (§6.1) deve existir **antes** dos controllers/testes que a consomem. Sequência:
relação → controllers/Livewire/ResumoAutor → views → rotas/301 → sitemap → SEO.

### 9.1 `AutorEspiritualMensagensTest` (relação) — molde `AutorEspiritualTest`
`mensagens()` anexa/lê pelo pivô `mensagem_autor_espiritual`; `->publica()` encadeia (autor com 2 públicas + 1
pendente ⇒ `mensagens()->publica()->count()===2`); simetria com `Mensagem::autores()`.

### 9.2 `MensagemListaTest` (Livewire) — molde `Palestras\Lista` (via `Livewire::test`)
Só-Pública (I1: cria pública + pendente + pública-nível-restrito → só a pública na grade e no contador); filtro
`de`/`ate` por `data_recebimento` (validação `Y-m-d`); filtro `autor` por slug **e** `sem-assinatura`
(`whereDoesntHave`); ordenar `recente`/`antiga`/`az`; `alternarVisao` não reseta página; `limparFiltros` preserva
`visao`; paginação (`paginate(9)`); eager-load de `autores` (I6). **Estado vazio** renderiza o card "Limpar filtros".

### 9.3 `MensagemShowTest` (controller) — molde `PalestranteController`/`PalestraController`
404 de pendente/nível-restrito/inexistente (I2, **nunca 403**, `assertNotFound`); pública renderiza; **contexto
escapado** (I4: `contexto='<script>x</script>'` → `assertSee('&lt;script&gt;', false)`, `assertDontSee('<script>x')`);
autor N:N (I3: 0 → "Sem assinatura"; 2 → dois nomes/links); download (I7: `liberar_download=false` → sem botão;
`true`+link → botão com o link normalizado); pictografia local (I8: `getMedia('pictografia')` renderiza `<img>` da
conversão `web`, não `link_arquivo`); "mesmo dia" (outra pública com a mesma `data_recebimento` aparece; pendente
do mesmo dia **não**); "relacionadas" só as `publica()`; **zero F3/F5** (I14: `assertDontSee` cadeado/"Direcionada"/
destinatário/favoritar/curtir).

### 9.4 `AutoresIndexTest` (controller) — molde `Palestrantes\Lista`
Grade lista **só autor `ativo()` com ≥1 pública** (O5a: autor ativo **sem** pública **não** aparece na grade);
`mensagens_count` **só das públicas** (I5: autor com 3 públicas + 2 restritas → "3 mensagens"); pontinhos = formatos
distintos das públicas; mini-stats (total autores ativos / total mensagens públicas); autor inativo não aparece;
pluralização "1 mensagem"/"N mensagens".

### 9.5 `AutorShowTest` (controller) — molde `PalestranteController::show`
404 de inativo/inexistente (I12); **autor ativo sem pública = 200** com grade vazia/stats zerados (I12/O5a); grade
só `mensagens()->publica()` (I11); 3 tiles (total/predominante/última) **das públicas** (I5) e **sem** tile
"Curtidas" (I14); "Em destaque" = mais recente pública; "Formatos" = `groupBy` públicas; rodapé estático com
`href="{{ route('login') }}"` (sem lógica de nível); `$itensFiltro` com `formato` (Alpine filtro+ordenação); zero
F3/F5 (I14).

### 9.6 `MensagemSitemapTest` + `AutorSitemapTest` — molde `EventoSitemapTest`
`/sitemap.xml` `assertOk` + `application/xml`; `assertSee(route('mensagens.index'), false)` e a `mensagens.show` de
uma pública; `assertDontSee` da não-pública (I9); idem `autores.index` + autor ativo **com pública** entra / inativo
**e ativo-sem-pública** fora (O5a).

### 9.7 `MensagemUrlCompatTest` (301) — molde `PalestraUrlCompatTest`
`get('/mensagem-mediunicas')` → 301 `/mensagens-mediunicas`; `get('/mensagem-mediunicas/abc')` → 301
`route('mensagens.show','abc')` (I10, slug preservado); a rota compat casa `[a-z0-9-]+`.

### 9.8 `MensagemSeoTest` / `AutorSeoTest` — molde `BlogSeoTest`
`canonical` presente e = `route(...)`; `og:image` **condicional** (com pictografia/foto → presente; sem → ausente);
`og:title`/`description` (do layout); JSON-LD parseável (`Article`/`Person`) (I13).

### 9.9 Regressão + suíte
Baseline **~972** (`docker compose exec -T app php artisan test --list-tests`); alvo **~972 + novos**, verde;
**Pint** verde no container ([[pint-antes-de-push]], [[flaky-importadorblog-gd-cap-imagem]]). **Conferir no
localhost** as 4 páginas (DoD: `npm run build` + `restart app worker` + abrir e navegar). **Verificação visual**
contra os screenshots dos 4 handoffs (fidelidade de recriação, tema claro).

---

## 10. Fora de escopo (Fatias 3/5 — não fazer agora)

- **Visibilidade rica** (F3): 6 níveis, resolvedor por papel, badge de nível + cadeado, legenda de bolinhas,
  "Direcionada"/destinatários (PII), "Minhas mensagens direcionadas", card de destinatários, 403/redirect-login por
  nível, `noindex` condicional. **A 2B usa só `publica()` fixo; não-pública = 404; zero PII.**
- **Engajamento** (F5): favoritar mensagem, ícone lida/não-lida (pivô) + "marcar lida ao abrir", "vistas
  recentemente", **curtir do autor** (coluna/tabela). **Não** criar nada disso; **não** reusar `Palestras\Curtir`;
  tile "Curtidas" removido.
- **Dark mode:** ignorar a coluna "escuro" dos handoffs (site é light-only).

---

## 11. Fronteiras: o que toca × o que NÃO toca

**Toca (novo):** 2 controllers + 1 Livewire + 1 classe de stats + views (index/show de mensagens e autores + 3
parciais de corpo + livewire) + componentes (`mensagem.{card,linha,selo-formato,envelope-hero}`, `autor.card`,
`ui.onda-hero`?) + Alpine (single + perfil) + testes.
**Toca (edição mínima):** `AutorEspiritual` (**+1 relação**) · `routes/web.php` (rotas + 301) · `SitemapController`
(2 coleções) · `sitemap.blade.php` (2 blocos) · `app.js` (Alpine) · `app.css`/tema (estilos).
**NÃO toca:** `Mensagem` (2A completa) · núcleo de autorização (Policies/Camada 1/`MatrizCapacidades`/`/admin`/
Resource) · importação · `Palestras\Curtir` · `x-ui.selo-visibilidade` · os molds clonados (permanecem intactos).

---

## 12. Ciências (não são tarefa desta fatia)

- **`{!! $corpo !!}` é seguro** porque o `corpo` já foi saneado no import/edição (`clean('conteudo')` no set do
  model). O `contexto` (texto puro) é **sempre** `{{ }}`. Se a checagem pré-merge de psicofonia da 2A (§9.7 da 2A)
  achar corpos com layout em `table`/`div`, os balões pergunta/resposta degradam (o `clean` os achata) — ligado a O1.
- **`data_recebimento` pode ser null** (a coluna é `nullable`; hoje todos os importados têm data, mas o form
  `/admin` permite vazio). A ordenação e o "mesmo dia" tratam null (nulos por último; "mesmo dia" só roda com data).
- **"Sem assinatura" é comum** (51 publish com 0 autor — 2A §4.5): o desenho de autor/assinatura assume o caso vazio
  como **normal**, não excepcional.
- **Pictografia é rara** (2 mensagens no legado — 2A §4.3); o card da lista sem miniatura e o single com galeria são
  o comportamento correto para o volume real.
- **A capacidade/Policy da Mensagem segue INERTE** (2A §12): o front público não a aciona (é `publica()` fixo). A
  edição pelo site (setor Médium etc.) é F4.
- **`og:type` fixo `website` (dívida do LAYOUT, não da 2B — R2/Consultor):** o `x-layout.app` emite
  `og:type=website` **antes** do slot `head`
  ([:16](../../../resources/views/components/layout/app.blade.php#L16)), então um single **não** consegue trocar para
  `article`/`profile` sem emitir **dois** `og:type`. Vale para **todos** os singles do site (palestrante/evento/post),
  não só a 2B. **Não** hackear no slot; fica como dívida do layout a resolver num passe transversal futuro.

---

## 13. Passe adversarial próprio (19/jul) — achados e pendências para o dono

> **Passe interno rodado antes da entrega:** 8 leitores paralelos mapearam os 4 handoffs (README + `.dc.html` +
> screenshots) e os molds, com **evidência `arquivo:linha`**; os 6 molds carga-de-prova (rotas/301, layout/OG,
> controller, ResumoPerfil, sitemap, Livewire Lista) foram **relidos direto** por mim. As divergências
> handoff↔modelo abaixo **já estão incorporadas** ao spec.

**Correções que ESTE spec já incorpora (o handoff/protótipo diverge do modelo real da 2A):**

- **C-A — pictografia SEM miniatura no card da lista (§4.1)**, mas COM miniatura no card do perfil de autor (§4.4).
  Divergência entre os dois handoffs; ambos respeitados fielmente.
- **C-B — o `corpo` é UM campo HTML único (§4.2).** O protótipo estrutura psicografia/psicofonia em dados tipados
  que **não existem**; os 3 formatos são 3 renderizações do mesmo `corpo` (+ MediaLibrary na pictografia). Base do O1.
- **C-C — o anexo Drive não tem nome/tamanho (§4.2).** O protótipo mostra `fileName · fileSize` inexistentes; o
  botão "Baixar arquivo" vai sem a linha de metadados.
- **C-D — "Vistas recentemente" do single é F5 (§4.2).** Substituída por "Relacionadas" (curada `relacionadas()`);
  estado lida/não-lida some.
- **C-E — autor é N:N, não `belongsTo` (§2/§4).** `Mensagem::autores()`
  ([:75-78](../../../app/Models/Mensagem.php#L75-L78)); 0/1/vários; "Sem assinatura" quando 0.
- **C-F — `AutorEspiritual::mensagens()` não existe (§6.1).** A 2B cria a relação inversa (única mudança de domínio).
- **C-G — `AutorEspiritual` TEM `foto_url`/`foto_thumb_url` (§3.1).** Corrige a dúvida do leitor de rotas: og:image
  do autor é viável; card/hero usam foto quando houver, senão iniciais.
- **C-H — `casa` é constante "CEMA" (§4.2).** A assinatura "Cidade, {data}" do protótipo não tem cidade real; usar
  `casa`/`data`, sem inventar.
- **C-I — selo de nível/cadeado/legenda/destinatários são F3 (§3.6/§4).** `x-ui.selo-visibilidade` reservado à F3; a
  2B não renderiza selo de nível; PII de destinatários **jamais**.

**Pontos ABERTOS para o passe adversarial do dono:**

1. **O1 — render da PSICOFONIA → PROSA. ✅ RESOLVIDO (dono/Consultor).** A checagem de psicofonia (prevista na 2A
   §9.7) **foi feita: 0/40** psicofonias do legado usam `table`/`div` ⇒ **não há** pergunta/resposta estruturada no
   dado. Decisão: renderizar a psicofonia **como a psicografia** (`{!! $corpo !!}` em `.cema-msg-prose`, Roboto Slab)
   + uma **nota** "Transcrição por psicofonia"; estilizar `h3`/`blockquote` se aparecerem. **Não** criar infra de
   balões nem pré-processar o corpo (YAGNI). Aplicado em §4.2/§6.4.
2. **O2 — fonte do TRECHO/excerpt** dos cards (lista e perfil). `Str::limit(strip_tags($corpo), 160)` (recomendado)
   vs `contexto` (opcional, muitas vezes null — é enquadramento, não resumo). **Confirmar.**
3. **O3 — "Autor em evidência"/"Em destaque".** Não há coluna `em_destaque`. **Recomendo:** lista → autor com MAIS
   mensagens públicas (determinístico, desempate por nome); perfil → mensagem mais recente pública do autor.
   Alternativa: flag manual futura. **Confirmar.**
4. **O4 — avatar sem foto:** gradiente FIXO do handoff (`#4E4483→#6E9FCB`) vs o rotativo existente
   `cema-grad-{id%8}`. **Recomendo** `cema-grad-{id%8}` (determinístico por id, consistência site-wide). **Confirmar.**
5. **O5 — ✅ RESOLVIDO (dono/Consultor).** (a) Autor `ativo()` com 0 públicas: **OCULTAR** da grade e do sitemap
   (`->whereHas('mensagens', fn ($q) => $q->publica())`); o **perfil** segue acessível por URL direta — **200, não
   404** (§6.5/§6.7, I9/I12). (b) Filtro de autor na lista de mensagens: **incluir** "Sem assinatura"
   (`whereDoesntHave('autores')`) — 51 publish têm 0 autor (§6.3).
6. **O6 — 301: permalink do WP ✅ CONFIRMADO (Consultor).** O material do dono traz as URLs reais:
   `cemanet.org.br/mensagem-mediunicas/{slug}` (5 singles) + `/mensagem-mediunicas` (archive) — **não é
   inferência**; o 301 do §6.2 está correto e **não** depende do túnel. Slug preservado 1:1 (pending sem slug nunca
   teve URL pública). **Autores:** **sem** evidência de single público de autor no WP ⇒ **manter SEM 301 de autor**.
7. **O7 — lista de autores sem filtro/paginação** (~19 autores; o handoff não tem nenhum). **Recomendo** listar
   todos (controller puro, sem Livewire); paginar/buscar só se crescer. **Confirmar.**
8. **O8 — reset de página no "Ordenar" (lista de mensagens).** O protótipo **não** reseta; o molde
   `Palestras\Lista::updated()` **inclui** `ordenar` no `resetPage` ([:50](../../../app/Livewire/Palestras/Lista.php#L50)).
   **Recomendo** seguir o molde (resetar — consistência com Palestras). **Confirmar** (micro).
9. **O-nota — "Veja também" e URL da mensagem.** (a) "Veja também" só deve linkar páginas que **existem** (ex.:
   "Pedido de Prece Online" pode não existir) — omitir as ausentes. (b) A URL `/mensagens-mediunicas/{slug}`
   (plural) já foi **travada** no kickoff (§2.7); registrado que marketing poderia preferir a singular do kickoff
   original (`/mensagem-mediunica/...`) — mantida a plural.
10. **Regra sempre:** pt-BR em tudo; cabeçalho de autoria no PHP novo; `Pint` antes do push; `docker compose exec
    -T app php artisan test`; `npm run build` **no host**; **todo brief de subagente que rode `artisan` DEVE proibir
    `migrate:fresh/refresh/wipe/reset` e seed destrutivo** e reafirmar `legado` read-only ([[nunca-migrate-fresh-no-dev]]).

---

### Passe adversarial do CONSULTOR (19/jul) — veredito: ✅ APROVADA para virar plano

O Consultor verificou as afirmações carga-de-prova contra o código (`161b502`) — **todas conferem** (`corpo`/`clean`,
purifier `'conteudo'`, mutator de data, layout sem `canonical`, enum, O8, chaves dos pivôs) — e as correções
C-A..C-I estão certas. **Zero bloqueador factual.**

**Obrigatórios — APLICADOS neste spec:**
- **O1 — psicofonia = PROSA** (0/40 do legado com `table/div`; sem infra de balões). §4.2/§6.4/§13-O1.
- **O5a — autor sem pública: OCULTAR** da grade e do sitemap (`whereHas('mensagens', publica())`); o perfil por URL
  direta segue **200** (não 404). §6.5/§6.7, I9/I12, testes §9.4/§9.5/§9.6.
- **Coerência do card** — `x-mensagem.card` ganha prop **`:variante` (`lista`|`perfil`)** (lista sem miniatura/2
  linhas; perfil com miniatura/3 linhas/data dourada), para não violar C-A nem perder a miniatura do perfil. §6.6.

**Pontos que o Consultor FECHOU (não precisam mais do dono):**
- **O6 — permalink WP CONFIRMADO** (URLs reais no material do dono: 5 singles + archive; sem espera de túnel; sem
  301 de autor).
- **O2** excerpt `Str::limit(strip_tags($corpo),~160)` · **O3** destaque determinístico · **O4** `cema-grad-{id%8}` ·
  **O5b** filtro "Sem assinatura" · **O7** lista de autores sem paginação (controller puro) · **O8** resetar página
  no "Ordenar" (molde `Palestras\Lista` [:50](../../../app/Livewire/Palestras/Lista.php#L50)) · **O-nota** "Veja
  também" só páginas existentes + URL plural — todos **ENDOSSADOS**.

**Refinamentos (menores) — INCORPORADOS:**
- **R1** — `:description` própria em `mensagens.index`/`autores.index` (§6.7).
- **R2** — `og:type=website` fixo é **dívida do LAYOUT** (todos os singles do site), **não** da 2B — ciência §12,
  sem hack no slot.
- **R3** — download da pictografia com **nome de arquivo amigável** no atributo `download` (§6.4).
- **R4** — confirmar os states de factory da 2A (`Mensagem::factory()->publica()`/`->pendente()`) antes dos testes
  §9; criar se faltarem.

**Veredito:** **segue para o PLANO** (molde das fatias anteriores, TDD real). O Consultor fará o passe do plano
antes da execução.

---

### Passe do PLANO — CONSULTOR (19/jul) — ✅ APROVADO após 2 obrigatórios

Plano em [`../plans/2026-07-19-camada-4-fatia-2b-front-publico-mensagens.md`](../plans/2026-07-19-camada-4-fatia-2b-front-publico-mensagens.md).
TDD real, 9 tasks encadeadas, I1–I14 bem cobertos. Moldes/assinaturas verificados contra o código (`161b502`):
`<x-slot:head>` ([palestrantes/show:4](../../../resources/views/palestrantes/show.blade.php#L4)), `$autor->iniciais`
(Attribute em `TemIniciais`), factory `publica()`/`pendente()`, e os moldes de teste citados **existem**.

**Obrigatórios — APLICADOS no plano:**
- **B1 — pluralização de "mensagem":** **nunca** `Str::plural('mensagem', …)` (gera "mensagems" —
  [Mensagem.php:25](../../../app/Models/Mensagem.php#L25)); usar ternário pt-BR `{{ $n === 1 ? 'mensagem' :
  'mensagens' }}` em todo contador (card de autor, contador da lista, single). Constraint global + Task 5.
- **B2 — `ResumoAutorTest`:** de Unit puro (`new Mensagem([...])` boota traits Spatie, instável) → **Feature**
  (`tests/Feature/Support/`, `Tests\TestCase` + `RefreshDatabase` + `Mensagem::factory()->publica()->create()`),
  espelhando `ResumoPerfilTest`. A classe `ResumoAutor` segue agregação pura em PHP.

**Refinamentos — INCORPORADOS:** R1 (etiqueta **Livewire 4** `^4.3`, não 3); R2 (nota de fallback no
`test_alternar_visao` para o padrão da casa se o `viewData()` divergir no LW4); R3 (factory nasce `nivel=null` ⇒
`create()` sem state não é pública — não presumir).

**Confirmado (confere):** 404≠403, contexto escapado, N:N/"Sem assinatura", "mesmo dia" (`whereDate` + exclui a
própria), relacionadas via `sincronizarRelacionadas`, 301 (bases distintas, sem colisão), sitemap
só-público/ativo-com-pública.

**Veredito:** **segue para a EXECUÇÃO** (subagente-driven, Task 0→9; cada task vermelho→verde→Pint→commit;
CP-1/2/3 rodam a suíte completa). O Consultor oferece o **passe final do PR** antes do merge (CI verde no último
commit + go do dono).
