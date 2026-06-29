# Spec — Redesign do single de palestra + slide, referências e "vídeo em breve"

> Thiago Mourão — https://github.com/MouraoBSB — 2026-06-29
>
> Frente **1 de 3** do redesign de Palestras/Palestrantes (público). As outras duas
> (listagem de Palestras Públicas e listagem de Palestrantes) terão specs próprios.
>
> **Revisado em 2026-06-29** (revisão multi-agente confrontada com o código real): D6 passou
> a **contador real** de curtidas; incorporadas correções de fuso no calendário, `LinkDrive`
> por host, thumb do YouTube, leitura do `_slides`, ordem do Repeater, OG/JSON-LD e sanitização.

## 1. Contexto e objetivo

A página de detalhe de uma palestra (`/palestra_publica/{slug}`, ex.:
`/palestra_publica/cema-65-anos`) já existe e está no visual roxo/dourado, mas o layout é
raso. O handoff **`design_handoff_palestra_single`** propõe um redesenho profundo (conteúdo +
sidebar sticky, player "CEMA TV" lazy, referências em cards, etc.) e, junto, queremos
**três funcionalidades novas**:

1. **Slide** — campo para o link dos slides da palestra; o sistema trata o link do Google
   Drive para **download direto** (aposentando o gerador externo
   `MouraoBSB/Gerador-de-Link-de-Download---Cemanet`), e um botão **"Baixar slides"** aparece
   na página **somente quando o campo está preenchido**.
2. **Referências bibliográficas** — hoje vêm embutidas no texto; passam a ter campos próprios
   (doutrinárias estruturadas + evangélicas em texto) e são renderizadas **em destaque**,
   como no handoff.
3. **"Vídeo em breve"** — quando a palestra ainda não tem link de vídeo, o player mostra um
   estado "em breve" no lugar de simplesmente sumir.

**Objetivo:** entregar o single redesenhado conforme o handoff, com as três funcionalidades,
mantendo SEO/A11y/performance e o padrão idempotente de importação do legado.

## 2. Escopo

### No escopo (Frente 1)
- Migrations incrementais para os campos/relação novos.
- Tratamento do link de slide (Drive → download direto), idempotente e reaproveitável.
- Importação do `_slides` do legado (19 palestras, todas Google Drive `uc?export=download`).
- Campos no admin Filament (`PalestraResource`).
- Redesenho da view `palestras/show.blade.php` conforme o handoff.
- Funcionalidades de apoio: **curtir com contador** (componente Livewire), **adicionar ao
  calendário** (`.ics` + link Google Agenda), **barra de progresso**, **relacionadas por
  assunto**, **"vídeo em breve"**, **JSON-LD `VideoObject`**.
- Testes (unit + feature + importação).

### Fora de escopo (só desenhado; implementado depois)
- **Favoritar** (membro logado, lista de favoritas) → entra na **migração da área de
  membros**. É **independente** do contador de curtidas anônimo desta frente (§10).
- **Integração Google Calendar com lembrete automático** (OAuth/Calendar API) → caminho
  aberto (§10). Agora só o `.ics`/link (a pessoa adiciona e o próprio Google lembra).
- Redesign das **listagens** de palestras e de palestrantes (specs próprios).

## 3. Decisões registradas (não rediscutir)

| # | Decisão |
|---|---|
| D1 | Redesign **completo** do single conforme o handoff (não só as 3 features). |
| D2 | Referências **doutrinárias** → tabela 1:N `palestra_referencias` (padrão de `palestra_destaques`). **Evangélicas** e **duração** → colunas em `palestras`. |
| D3 | **Slide**: guarda-se o link **original** colado; o link de download é **derivado** por accessor (não-destrutivo, idempotente). |
| D4 | Referências/duração: **preenchimento manual** no Filament (não extrair do texto legado). |
| D5 | Slides do legado: **importar junto** (19 registros, todos Drive `uc?export=download`). |
| D6 | **Curtir com contador REAL** (coluna `curtidas` + incremento atômico + componente Livewire isolado + dedup por navegador). Independente do favoritar de membros (§10). |
| D7 | **Calendário** agora na versão simples (`.ics` + link Google Agenda). Lembrete automático = futuro. |
| D8 | **Relacionadas** derivadas dos **assuntos** (taxonomias), sem indicação manual. |

## 4. Modelo de dados (migrations incrementais)

> 🚫 Apenas `php artisan migrate` incremental. Nunca `migrate:fresh/refresh/wipe/reset`.

**4.1 Alterar `palestras`** (uma migration):
- `slide` `string` nullable — link dos slides (como colado).
- `duracao` `string(40)` nullable — ex.: `≈1h10` (texto livre, manual).
- `referencias_evangelicas` `text` nullable — parágrafo livre (ex.: "a promessa do
  Consolador (João 14) e o episódio da tempestade acalmada no barco").
- `curtidas` `unsignedInteger` default 0 — contador público de curtidas (D6). Começa em 0
  (não há like no legado para semear).

**4.2 Criar `palestra_referencias`** (referências doutrinárias, 1:N):
- `id`
- `palestra_id` `foreignId`->`constrained('palestras')`->`cascadeOnDelete()`
- `obra` `string` **NOT NULL** — ex.: "O Livro dos Espíritos"
- `autor` `string` nullable — ex.: "Allan Kardec"
- `nota` `text` nullable — descrição do que é referenciado
- `ordem` `unsignedInteger` default 0
- `timestamps`

**Sem** tabela de favoritos agora (favoritar é da fase de membros, §10); o contador
`curtidas` (anônimo) é independente disso.

Model `Palestra`:
- `$fillable` **+** `slide`, `duracao`, `referencias_evangelicas`, `curtidas` — **antes** de
  importar/salvar; senão `updateOrCreate` descarta os campos em silêncio (perderia os 19
  slides e os campos do Filament).
- `referencias(): HasMany` (ordenada por `ordem`).
- accessor `slide_download_url` (§5).

Model novo `PalestraReferencia` (`HasFactory`; `$fillable`: `obra`, `autor`, `nota`, `ordem`).

## 5. Tratamento do link de slide — `App\Support\Palestras\LinkDrive`

Classe utilitária, método estático `paraDownload(?string $link): ?string`. **Decide "é Drive"
pelo HOST primeiro** (não por "conseguiu extrair um token"), para nunca corromper URL não-Drive:

1. `null`/vazio → `null`.
2. `html_entity_decode($link, ENT_QUOTES | ENT_HTML5)` (resolve `&amp;` do legado) + `trim`.
3. `$host = parse_url(..., PHP_URL_HOST)`. **Se `$host` não contém `drive.google.com`**
   → retorna o link **original intacto** (cobre Dropbox/CDN/`docs.google.com/presentation`/
   qualquer outro — não tentamos extrair ID deles).
4. Host é Drive, mas é **pasta** (`/drive/folders/`) → retorna **intacto** (uc?export não
   baixa pasta).
5. Extrai o **file ID** (só agora, com host confirmado): `?id=`/`&id=`
   (`[?&]id=([A-Za-z0-9_-]{10,})`); caminho `/file/d/([A-Za-z0-9_-]{10,})`; senão token bruto
   `([A-Za-z0-9_-]{25,})`.
6. Extraiu ID → `https://drive.google.com/uc?export=download&id={ID}`. Não extraiu (Drive sem
   ID reconhecível) → retorna **intacto**.

**Idempotente:** `uc?export=download&id=ID` reextrai o mesmo ID e devolve o mesmo resultado.

Model `Palestra`: accessor `slide_download_url` (`Attribute::get`) =
`LinkDrive::paraDownload($this->slide)`. A view usa `slide_download_url`; o admin guarda
`slide` cru.

**Teste-âncora (casos):**
- `https://drive.google.com/file/d/1ABC.../view?usp=sharing` → `uc?export=download&id=1ABC...`
- `https://drive.google.com/open?id=1ABC...` → idem
- `https://drive.google.com/uc?export=download&amp;id=1ABC...` (com `&amp;`) → idem (idempotente)
- `https://drive.google.com/drive/folders/1ABC...` → **intacto** (não converte pasta)
- `https://www.dropbox.com/s/AAAAAAAAAAAAAAAAAAAAAAAAAA/x.pptx` (25+ chars, não-Drive) → **intacto**
- `https://exemplo.com/arquivo.pptx` (não-Drive) → **intacto**
- `null`/`''` → `null`

> **Robustez opcional (não nos dados atuais):** os 19 `_slides` são 100% `uc?export=download`
> — zero `docs.google.com/presentation` e zero `/drive/folders/`. O passo 3 já blinda o
> sistema para entrada manual futura desses formatos (Slides nativo precisaria de
> `/export/pptx`, fora de escopo).

## 6. Importação (`cema:importar-palestras`, idempotente)

Confirmado por leitura do código: hoje [LeitorLegadoMysql::palestras()] **não** lê `_slides` e
o importador **não** grava `slide`. Mudanças:
- No leitor, acrescentar ao array da palestra:
  `'slide' => html_entity_decode((string) ($meta['_slides'] ?? ''), ENT_QUOTES | ENT_HTML5) ?: null`
  (o `metasDe()` já carrega o meta).
- Em `ImportadorPalestras::importarPalestras()`, incluir `'slide' => $d['slide'] ?? null` no
  `updateOrCreate` (mantém idempotência por `slug`). O accessor trata na exibição.
- **Não** importa referências/duração (D4) — nascem vazios.
- Estado atual confirmado via túnel: **19** palestras com `_slides`, **todas** Google Drive
  `uc?export=download`.

## 7. Admin — `PalestraResource` (Filament 5)

> **Ordem obrigatória** (senão `Repeater->relationship('referencias')` lança `LogicException`
> e derruba Create/Edit): (1) migration `palestra_referencias` → (2) model `PalestraReferencia`
> → (3) `Palestra::referencias()` `HasMany` → (4) só então o Repeater no Resource.

Adicionar ao formulário:
- `slide` — `TextInput::url()`, label "Link dos slides (Google Drive)", `helperText`:
  *"Cole o link normal do Google Drive; o sistema gera o download direto automaticamente."*
- `duracao` — `TextInput`, label "Duração", placeholder "≈1h10".
- `referencias_evangelicas` — **`Textarea` (texto puro)**, label "Referências evangélicas".
  Renderizado com `{{ }}` (auto-escape); **sem** `clean()`/HTMLPurifier.
- **Repeater `referencias`** (`->relationship()`, espelhar `destaques`): `obra` (`required`),
  `autor`, `nota` (`Textarea`), `reorderable` + `orderColumn('ordem')`, label "Referências
  doutrinárias".

Padrão visual/idioma seguindo os demais campos do Resource. Sem alterar campos existentes.

## 8. Front — `resources/views/palestras/show.blade.php` (segue o handoff)

Container de leitura `max-w-[1100px] px-6`. Seções (ver `design_handoff_palestra_single`
README §4 e screenshots):

1. **Barra de progresso** — `fixed top-0 h-[3px]`, largura = % de scroll (Alpine +
   `requestAnimationFrame`); respeita `prefers-reduced-motion`.
2. **Hero roxo** — `x-ui.particulas` + breadcrumb (Início › Palestras Públicas › título) +
   eyebrow mono "PALESTRA PÚBLICA" + H1 `font-display` + frase (`subtitulo`, `font-serif
   italic`) + **chips de meta** (`📅 data·hora`, `🌐 modalidade`, `👤 palestrante`).
3. **Grid** `lg:grid-cols-[minmax(0,1fr)_320px]`: conteúdo + `<aside class="sticky top-24">`.
4. **Player "CEMA TV" (16:9)** — **reescrita de comportamento** (a view atual embute o
   `<iframe>` no load; passa a ser lazy): thumb SSR = accessor `youtube_thumb` (**mqdefault**,
   sempre existe) ou `youtube_thumb_hq` (`hqdefault`) — **não** `maxresdefault` (404 cinza em
   vídeo não-HD); `onerror` só como rede de segurança. Barra "CEMA TV"; botão play vermelho;
   clique → injeta o `<iframe ...?autoplay=1>` (Alpine), **só no clique**.
   **Sem `link_youtube` → estado "Vídeo em breve"**: player com gradiente da marca + ícone +
   texto **"O vídeo desta palestra estará disponível em breve."** (sem botão de play).
5. **Cartões meta** — Data · Modalidade (`online ? Online : Presencial`) · **Duração**
   (`duracao`). O cartão de Duração é **omitido** quando `duracao` está vazio.
6. **"Sobre a palestra"** (card branco) — prosa serifada de `descricao`
   (`p { line-height:1.82; color:#3a3553 }`). Em seguida, quando houver:
   - **Referências doutrinárias** — bloco com `border-t`; cada item de `referencias` é um card
     creme `#FAF8F2`/borda `#ECE6D6` com ícone-livro (lombada roxa + faixa dourada): **obra**
     (negrito) · `autor` (cinza) + `nota`. Render com `{{ }}` (auto-escape).
   - **Referências evangélicas** — quando `referencias_evangelicas` preenchido: parágrafo final
     com rótulo "Referências evangélicas —" + texto (`{{ }}`).
7. **Tópicos** (acordeão numerado) — reusa `destaques` (`palestra_destaques`):
   `<details>`/`<summary>` com número mono + título + ícone "+".
8. **Assuntos** (tags) — `assuntos`, pílulas que linkam para a listagem filtrada
   (`palestras.index?assunto={slug}`).
9. **Sidebar (sticky `top-24`; estática < ~900px):**
   - **Palestrante** — faixa-capa + avatar (foto via Media Library `foto_thumb_url`; fallback
     iniciais), "PALESTRANTE", nome, bio curta, "Ver perfil completo →".
   - **Ações** — botão vermelho **"Assistir no YouTube"** (se houver link); **"Baixar
     slides"** *(só quando `slide` preenchido)* → `slide_download_url` com
     `target="_blank" rel="noopener"`; **"Adicionar ao calendário"** (§9.2).
   - **Compartilhar** — Facebook, WhatsApp, copiar link (já existem) + **curtir com contador**
     via `<livewire:palestras.curtir :palestra="$palestra" />` (§9.1).
10. **Anterior / Próxima** — já existe; manter, restilizar conforme handoff §4.8.
11. **Relacionadas** (§9.3) — partial protegida por `@forelse`/`@empty` (nunca quebra vazia).

Componentização sugerida (arquivos focados): partials Blade
(`palestras/partials/player.blade.php`, `referencias.blade.php`, `relacionadas.blade.php`).
Padrões reusados: `x-layout.app`, `x-ui.particulas`.

## 9. Funcionalidades de apoio

**9.1 Curtir com contador (D6).** Componente Livewire isolado
`App\Livewire\Palestras\Curtir` (`<livewire:palestras.curtir :palestra="$palestra" />`) só no
botão — o resto da página segue Blade estático (resolve CSRF/estado/atomicidade):
- Incremento/decremento **atômico**: `$palestra->increment('curtidas')` / `decrement`.
- **Dedup por navegador:** o `$persist` já existente (`curtida_palestra_{id}`,
  [show.blade.php:56]) é o gate — coração preenchido + impede recontar. Alpine decide, no
  clique, entre `$wire.curtir()` e `$wire.descurtir()` e atualiza o `$persist`.
- **Throttle/rate-limit** no método para conter spam.
- Exibe o número **formatado** ao lado do coração. Começa em 0.
- Métrica **anônima** e inflável por design (limpar o navegador permite recurtir) — aceito;
  não é a "favorita por conta" (que é da fase de membros, §10).

**9.2 Adicionar ao calendário (D7).** Datas **convertidas para UTC** antes de formatar
(`data_da_palestra` é hora de parede `America/Sao_Paulo` — [config/app.php], [TransformadorLegado]):
- **Início:** `$palestra->data_da_palestra->copy()->utc()->format('Ymd\THis\Z')`.
- **Fim:** início + duração. `duracao` é string livre → **parser tolerante** (regex de
  `h`/`min`, ignorando `≈`/`aprox`); **fallback explícito +1h30** quando não parsear. Mesmo
  `->utc()->format(...)`.
- **Google Agenda** — `https://calendar.google.com/calendar/render?action=TEMPLATE&text={titulo}&dates={inicioUTC}/{fimUTC}&details={url}&location={endereco}`.
- **`.ics`** — rota `GET /palestra_publica/{slug}/calendario.ics` (`PalestraController@calendario`)
  com `VEVENT` (`DTSTART`/`DTEND` em UTC, `SUMMARY`/`DESCRIPTION`/`LOCATION`), `text/calendar`.
- Local: "Centro Espírita Maria Madalena — Quadra 02, Lote 16, Vila Vicentina, Planaltina, DF".
- Só renderiza quando `data_da_palestra` não é nula.
- **Teste:** palestra 19:00 BRT → `DTSTART` contém `T220000Z`.

**9.3 Relacionadas (D8).** No `PalestraController@show` — montar `$relacionadas` **antes** da
view (hoje o controller só retorna `palestra`/`anterior`/`proxima`):
1. `Palestra::publicado()` que compartilham ≥1 `assunto` com a atual
   (`whereHas('assuntos', in [ids])`), `whereKeyNot($id)`, `with('palestrantesAtivos')`,
   `orderByDesc('data_da_palestra')`, `take(3)`.
2. Fallback se < 3 (ou atual sem assunto — 11 casos): completar com palestras do mesmo
   palestrante; depois, mais recentes. Sempre excluir a atual e **deduplicar**.
Render: grid de mini-cards (linguagem da listagem) com data · palestrante. Partial com
`@forelse`/`@empty`.

**9.4 "Vídeo em breve" (§8.4).** Derivado de `link_youtube` vazio. Sem campo novo.

## 10. Preparado para o futuro (só desenho)

**Favoritar (área de membros).** Quando houver autenticação de membros:
- Pivô `palestra_usuario` (`user_id`, `palestra_id`, `created_at`) — N:N.
- `User::favoritas()` / `Palestra::favoritadaPor($user)`; página "Minhas palestras favoritas".
- UI: para **logado**, o coração pode oferecer "favoritar" (salvo na conta) **além** do
  contador público; para anônimo, segue só o contador de curtidas.
- **Independente** do contador `curtidas` desta frente. **Nada disso é implementado agora** —
  registrado aqui e a ligar no `DATA-MODEL.md` na fase de membros.

**Google Calendar com lembrete automático.** Integração OAuth + Calendar API para criar o
evento na conta do usuário e disparar lembrete. Caminho aberto; agora ficamos no `.ics`/link.

## 11. SEO · A11y · Performance

- **JSON-LD:** manter `Event`; **acrescentar `VideoObject` somente quando `youtube_id != null`**
  (name, thumbnailUrl = thumb resolvida, embedUrl, `uploadDate` =
  `data_da_palestra->toIso8601String()` — offset `−03:00`, documentar como aproximação).
- **OG:** o layout tem o slot `{{ $head }}` ([app.blade.php:22]) e `og:type=website` **fixo**
  ([:16]) e **não** emite `og:image`. Emitir `og:image` (= thumb resolvida) via `<x-slot:head>`
  na própria `show`; **não** duplicar `og:type` (manter `website` ou tratar a sobrescrita de
  forma única se optar por `video.other`).
- **A11y:** `<details>` nativo; botões com `aria-label`/`title`; player é `<button>` rotulado;
  foco visível; contraste; vídeo com `title`. `prefers-reduced-motion` na barra de progresso.
- **Performance:** embed do YouTube **só no clique** (lazy); imagens `loading="lazy"`; HTML
  enxuto.
- **Livewire v4** (`^4.3`): `$persist` funciona hoje (Alpine bundlado, `csp_safe=false`). Ao
  expandir Alpine (progresso por rAF, embed lazy), **validar no navegador**; se um dia ativar
  `csp_safe`/app.js próprio com `import 'alpinejs'`, registrar `Alpine.plugin(persist)`.

## 12. Testes

- **Unit `LinkDriveTest`** — casos do §5: formatos Drive → download; `&amp;`; idempotência;
  `/drive/folders/` intacto; **não-Drive (inclusive 25+ chars) intacto**; null/vazio.
- **Feature `PalestraShowTest`** (datas com `Carbon::setTestNow()` + fuso explícito):
  - "Baixar slides" aparece **só** quando `slide` preenchido e aponta para `slide_download_url`;
  - sem `link_youtube` → "vídeo em breve" e **não** carrega iframe no load;
  - referências doutrinárias em cards; evangélicas quando preenchidas (escapadas);
  - relacionadas: 3 por assunto; fallback quando a palestra não tem assunto;
  - curtir: incremento **atômico** (curtir +1, descurtir −1); throttle.
- **Feature calendário** — `.ics` responde `text/calendar`; `DTSTART` de 19h BRT contém
  `T220000Z` (fuso); `DTEND` = +duração ou +1h30.
- **Importação** (`ImportarPalestrasCommandTest`) — fake com `_slides`; `slide` importado e
  exibido como download direto; idempotente (rodar 2× não duplica nem altera).

## 13. Riscos e mitigação

- **Variações de URL do Drive** → host-first + token só dentro do host Drive (§5); não-Drive
  intacto; teste-âncora.
- **Fuso (calendário)** → conversão `->utc()` explícita + teste do `T220000Z`.
- **`updateOrCreate` silencioso** → `$fillable` atualizado antes de importar/salvar (§4).
- **Repeater sem relação** → ordem obrigatória migration→model→relação→Repeater (§7).
- **Thumb 404** → `mqdefault`/`hqdefault` SSR, `onerror` só de rede de segurança.
- **Crescimento da view** → partials Blade focados (§8).
- **Relacionadas pobres** (palestra sem assunto) → fallback em cascata + `@forelse` (§9.3).
- **Importação** → idempotente por `slug`; jamais `migrate:fresh` (regra dura do projeto).

## 14. Arquivos afetados (mapa)

- `database/migrations/2026_06_29_*_add_slide_duracao_refs_curtidas_to_palestras.php` (novo)
- `database/migrations/2026_06_29_*_create_palestra_referencias_table.php` (novo)
- `app/Models/Palestra.php` (`$fillable`, `referencias()`, accessor `slide_download_url`,
  `youtube_thumb_hq` se necessário)
- `app/Models/PalestraReferencia.php` (novo)
- `app/Support/Palestras/LinkDrive.php` (novo)
- `app/Livewire/Palestras/Curtir.php` + `resources/views/livewire/palestras/curtir.blade.php` (novo)
- `app/Importacao/LeitorLegadoMysql.php` + `ImportadorPalestras.php` (ler/gravar `_slides`)
- `app/Filament/Resources/.../PalestraResource.php` (+ schema/form) (campos novos)
- `app/Http/Controllers/PalestraController.php` (`show` + `$relacionadas`; `calendario`)
- `routes/web.php` (rota `.ics`)
- `resources/views/palestras/show.blade.php` + partials novos
- `resources/css/...` (prosa serifada / cards de referência, se necessário)
- Testes: `tests/Unit/Palestras/LinkDriveTest.php`, `tests/Feature/.../PalestraShowTest.php`,
  `tests/Feature/.../CalendarioPalestraTest.php`, ajuste em `ImportarPalestrasCommandTest`.

## 15. A verificar durante a implementação

- Estrutura real do `PalestraResource` (form em classe Schema separada?) antes de inserir
  campos — não duplicar.
- `font-serif` (Roboto Slab) já no `@theme`/`app.css`; adicionar se faltar.
- Conferir nos 19 `_slides` o compartilhamento "qualquer pessoa com o link" (download de
  arquivo privado devolve página de login) — `target="_blank" rel="noopener"` cobre o eventual
  interstitial de vírus de arquivos grandes.
