# Design — Blog "Sementeira de Luz" (Fase 2, Fatia 1)

Data: 2026-06-26 · Stack: Laravel 13 · Filament 5 · MySQL 8 · Blade/Livewire 4 · Tailwind v4 · Docker.
Referências: `design_handoff_sementeira/` (README, design-system, protótipos, screenshots),
`DATA-MODEL.md`, `DB-LEGADO.md`, `ROADMAP.md`, `PROJECT.md`.

## Objetivo

Entregar a **fatia vertical** do blog **Sementeira de Luz** (portal de notícias/reflexões do CEMA):
banco → importação (do WordPress legado) → admin (Filament) → front público na **variante B
"Semente de Luz"**, com os **44 posts publicados** migrados com fidelidade ao handoff de design.
O blog é a porta de entrada do público ao Espiritismo e à Casa; hoje tem cara de tema genérico de
WordPress — este trabalho o torna um produto editorial profissional dentro da identidade do CEMA.

## Escopo e fatiamento

- **Fatia 1 (este spec):** migração + admin + público (variante B) + SEO essencial com placar.
- **Fatia 2 (próximo spec):** sistema de **comentários** próprio (Livewire, aberto sem conta,
  moderação progressiva, anti-spam, LGPD). Modelo já em `DATA-MODEL.md`.
- **Deferidos (registrados, não construídos nesta fatia):**
  - Variante A "Luz Serena" + ativação da **alternância mensal** (a infra fica pronta como config).
  - Vínculo **post↔palestra** (relação Jet `rel_id=200`, 12 vínculos) — guardamos `wp_id` para ligar depois.
  - **Newsletter** funcional (por ora, faixa apenas visual, como na Fase 1).
  - "Reflexão do dia" puxada do módulo **Agenda Reforma Íntima** (fonte trocável; ver §SEO/Front).

## Decisões (alinhadas no brainstorming)

1. **Fonte da importação:** banco `legado` (read-only, túnel SSH) — mais rico que a REST.
2. **Conteúdo:** editor **RichEditor (TipTap/HTML)**; a migração **preserva o HTML do Gutenberg**
   (remove comentários de bloco `<!-- wp:… -->`, limpa wrappers JetStyleManager `jet-sm-gb-*` e
   atributos `crocoblock_styles` órfãos, e **sanitiza**).
3. **Variante visual:** **B "Semente de Luz"** (roxo-noite) primeiro; alternância mês a mês como
   config; variante A depois.
4. **SEO:** camada própria **essencial + placar de redação** no Filament (analisador ao vivo
   estilo Rank Math). Migrar campos do Rank Math (fallback Yoast); a *pontuação* do Rank Math é
   só auxílio de redação e **não** é migrada (é recalculada).
5. **URL:** canônica nova `/sementeira` (listagem) e `/sementeira/{slug}` (artigo) + **301** da raiz
   `/{slug}/` e de `/categoria/{slug}` — preserva todos os links divulgados (permalink atual é
   `/%postname%/`, na raiz).
6. **Autor:** **sem autor público** — o blog é assinado pela instituição (Centro Espírita Maria
   Madalena). Existe apenas **autoria administrativa** (qual usuário do painel criou/editou),
   nunca exibida no site. Não acoplar ao módulo Usuários (futuro).
7. **"Mais lidas":** contador de **visualizações** próprio (incremento por sessão, com throttle).
8. **"Reflexão do dia":** frase **configurável no admin** agora, com a fonte desenhada de forma
   **trocável** (contrato/serviço) para futuramente vir da Agenda Reforma Íntima.
9. **Membros:** os posts **não** usam a taxonomia `nivel-de-acesso` → blog 100% público (gating N/A).
10. **Imagens no conteúdo:** o RichEditor deve permitir **alinhar/flutuar** a imagem
    (esquerda/direita/centro — o texto **contorna**) e **redimensionar** a largura. Saída HTML
    **responsiva** (largura em **% / max-width**, nunca px fixo; no mobile a imagem flutuante
    **empilha** full-width). O HTML migrado do Gutenberg que já traz alinhamento/tamanho é
    **preservado** — não remover essas classes na limpeza; estilizá-las no CSS.

## Introspecção do legado (resultado — 2026-06-26, conexão `legado` read-only)

Multisite confirmado, mas o blog vive no **site principal** (`wp_blogs.blog_id=1`,
`cemanet.org.br`, path `/`, prefixo `wp_`). Sem subsite.

| Item | Realidade | Destino |
|---|---|---|
| Volume | `post_type='post'`: **44 publish** (+1 draft), de nov/2024 a jun/2026 | `posts` |
| Conteúdo | `post_content` = **Gutenberg limpo** (com wrappers `jet-sm-gb-*`). `_elementor_data` é resíduo em ~12 posts; o corpo real está sempre no `post_content` | `posts.conteudo` (limpo+sanitizado) |
| Imagem destacada | **44/44** têm `_thumbnail_id` (cobertura 100%) | `posts.imagem_destacada` (+ alt) |
| **FAQ** | meta `_faq` = repeater JetEngine serializado `item-N → {_pergunta_faq, _resposta_faq}`; **28/44** têm ≥1 | `post_faqs` (1:N) |
| **Galeria** | meta `_fotos_carrossel_` serializado `i:N → {id, url}`; **14/44** (19–34 fotos em alguns) | `post_imagens` (1:N) |
| Categorias | taxonomia `category`: **5 reais** + "Sem categoria"; 41 posts c/ 1, 3 c/ 2 | `categorias` + pivô N:N |
| Tags | `post_tag`: usada só por 1 post | `tags` + pivô (secundário) |
| Categoria principal | meta `rank_math_primary_category` (30 posts) | `posts.categoria_principal_id` |
| Autor | `post_author` 1 (DECOM1) e 3 (apagado) — irrelevante p/ exibição | autoria só **administrativa** (`criado_por_id`); **não** público |
| SEO | `rank_math_description` (20), `rank_math_focus_keyword` (30), `rank_math_title` (1), OG (1–2); resíduo Yoast `_yoast_wpseo_*` | colunas SEO em `posts` |
| post↔palestra | relação Jet `wp_jet_rel_default` **rel_id=200** (parent=palestra, child=post), 12 vínculos | deferido (guardar `wp_id`) |
| Reviews | `jet-review-*` em todos (sem estrelas no design) | **não migrar** |
| Footnotes | meta `footnotes` em 15 posts (Gutenberg) | nice-to-have (avaliar na implementação) |

**Categorias reais e cores (do design-system do handoff):**

| Categoria | slug | Cor (kicker/acento) |
|---|---|---|
| Reflexões e Espiritualidade | `reflexoes-e-espiritualidade` | `#4E4483` (roxo) |
| Estudando a Mediunidade | `estudando-a-mediunidade` | `#6E9FCB` (azul) |
| Prática do Amor ao Próximo | `pratica-do-amor-ao-proximo` | `#89AB98` / texto `#5f8a72` (verde) |
| Datas Comemorativas | `datas-comemorativas` | `#F2A81E` / texto `#c98a2e` (dourado) |
| CEMA em Ação | `cema-em-acao` | `#E79048` (laranja) |
| Sem categoria | `sem-categoria` | neutro |

## Modelo de dados (migrations + seeders)

> Conferir o que já existe antes de criar (módulo Palestras já tem suas tabelas). FKs sempre.
> Nomes de domínio em pt-BR. `comentarios` fica para a Fatia 2 (já modelada em `DATA-MODEL.md`).
> **Mídia (decisão 2026-06-26):** imagem destacada e galeria via **Spatie Media Library**
> (conversões/WebP/srcset/optimizer; upload múltiplo e reordenável no Filament) — **substitui**
> as colunas string `imagem_destacada` e `post_imagens.caminho` por coleções de mídia.
> Detalhes e parâmetros no backlog `2026-06-26-blog-sementeira-ajustes.md`.

### `posts`
| Coluna | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| titulo | string | ← `post_title` |
| slug | string unique | ← `post_name` (chave de idempotência) |
| resumo | text null | dek/subtítulo ← `post_excerpt` |
| conteudo | longtext | HTML limpo+sanitizado ← `post_content` |
| imagem_destacada | string null | caminho no storage (← `_thumbnail_id`, re-hospedada) |
| imagem_destacada_alt | string null | ← `_wp_attachment_image_alt` |
| criado_por_id | bigint null FK→users | autoria **administrativa** (quem criou no painel); null na importação; **nunca público** |
| categoria_principal_id | bigint null FK→categorias | ← `rank_math_primary_category` |
| destaque | bool default false | herói da listagem (fallback: mais recente) |
| tempo_leitura_min | smallint | calculado do nº de palavras (~200 ppm) |
| visualizacoes | unsigned int default 0 | "Mais lidas" |
| data_publicacao | datetime | ← `post_date` |
| status | enum(`publicado`,`rascunho`,`agendado`) | ← `post_status` (publish/draft/future) |
| wp_id | unsigned bigint unique null | id do post no legado (idempotência + vínculo futuro) |
| seo_titulo | string null | ← `rank_math_title` (fallback `_yoast_wpseo_title`) |
| seo_descricao | string null | ← `rank_math_description` (fallback `_yoast_wpseo_metadesc`) |
| seo_keyword | string null | ← `rank_math_focus_keyword` (fallback `_yoast_wpseo_focuskw`) |
| og_imagem | string null | ← `rank_math_og_content_image` (fallback: imagem destacada) |
| robots_noindex | bool default false | controle de indexação |
| canonical | string null | URL canônica custom (raro) |
| timestamps | | |

### `categorias`
| Coluna | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| nome | string | |
| slug | string unique | resolução na importação |
| cor | string(7) null | hex do design (kicker/acento) |
| descricao | string null | |
| ordem | smallint default 0 | |
| wp_term_id | unsigned bigint null | rastreio do termo legado |

### `tags`
`id` · `nome` · `slug` unique · `wp_term_id` null.

### Pivôs N:N
- `categoria_post`: `post_id` FK · `categoria_id` FK.
- `post_tag`: `post_id` FK · `tag_id` FK.

### `post_faqs`
`id` · `post_id` FK · `pergunta` string · `resposta` text · `ordem` smallint · timestamps.

### `post_imagens` (galeria)
`id` · `post_id` FK · `caminho` string (re-hospedada) · `url_legado` string · `alt` string null ·
`ordem` smallint · timestamps.

## Models e relações (Eloquent)

- **Post**: `belongsToMany` categorias (`categoria_post`); `belongsTo` categoriaPrincipal;
  `belongsToMany` tags; `hasMany` faqs (ordenadas); `hasMany` imagens (ordenadas).
  Escopos: `scopePublicado` (status=publicado **e** `data_publicacao <= now`),
  `scopeMaisLidas`, `scopeDestaque`. Acessores: `urlPublica`, `corCategoria` (da principal),
  `excerptSeo`. Cast de datas/bool/inteiros. Sanitização de `conteudo` via mutator (purifier
  `conteudo`) — cobre admin **e** importador, como nas palestras.
- **Categoria**: `belongsToMany` posts; `hasMany` postsPrincipais. `scopeComPostsPublicados`.
- **Tag / PostFaq / PostImagem**: relações triviais; `PostFaq`/`PostImagem` ordenáveis por `ordem`.
- **Tempo de leitura**: observer/mutator calcula `tempo_leitura_min` a partir de `strip_tags(conteudo)`.

## Importação — `php artisan cema:importar-blog`

Comando único orquestrador lendo `DB::connection('legado')` (read-only). Verifica a conexão no
início e **aborta com mensagem clara** se o túnel não estiver ativo. Idempotente: **upsert por
slug** (e por `wp_id`); nunca duplica; resolve categorias/tags por slug e **loga** o que não
resolver (não cria às cegas).

Ordem:
1. **Categorias** — seeder com as 5 categorias + cores (idempotente por slug); a importação
   vincula por slug. "Sem categoria" tratada como neutra.
2. **Posts** — `post_type='post'`, status em `publish/draft/future`:
   - Campos do post (titulo/slug/resumo/data/status) + `wp_id`.
   - **Conteúdo:** remover `<!-- wp:… -->` / `<!-- /wp:… -->`; limpar classes `jet-sm-gb-*` e
     atributos `crocoblock_styles`; **reescrever URLs** `wp-content/uploads` → baixar e
     re-hospedar no storage; sanitizar (purifier `conteudo`). **Preservar** as classes de
     alinhamento/tamanho de imagem do Gutenberg (`alignleft`/`alignright`/`aligncenter`/
     `size-*`, `wp-block-image`, `wp-block-media-text`) — não remover na limpeza; serão
     estilizadas no CSS público para manter o layout dos posts antigos.
   - **Imagem destacada:** baixar `_thumbnail_id` (+ `alt`).
   - **Autor:** não migrado (sem autor público; `criado_por_id` = null nos importados).
   - **SEO:** Rank Math → fallback Yoast.
   - **Tempo de leitura:** calculado.
3. **Categorias/Tags do post** — `wp_term_relationships` (taxonomias `category`/`post_tag`),
   resolvendo por slug; principal ← `rank_math_primary_category`.
4. **FAQ** — `unserialize(_faq)` → `post_faqs` (ordem = índice do item).
5. **Galeria** — `unserialize(_fotos_carrossel_)` → baixar cada imagem → `post_imagens` (ordem = índice).

**Mídia:** baixada via **GET na URL pública** do attachment (leitura, sem tocar no WP), salva em
`storage/app/public/blog/…`; idempotente (não rebaixa se já existe).

**A confirmar na implementação:** fuso de `post_date` (alinhar com o tratamento das palestras);
cobertura/limpeza fina das classes Croco; quais das 14 galerias têm imagens 404 no legado (logar).

## Admin (Filament 5) — `PostResource`

CRUD completo:
- **Conteúdo:** RichEditor (preserva HTML), com **alinhamento/float de imagem** (esquerda/
  direita/centro — texto contornando) e **redimensionamento** de largura (presets P/M/G/100%
  e/ou alças de arraste; salvar em **% / max-width**, evitar px fixo). Título → slug automático
  (editável). Resumo (dek).
- **Mídia:** upload da imagem destacada + `alt`; **galeria** (upload múltiplo ordenável → `post_imagens`).
- **Taxonomia:** categorias (multiselect) + **categoria principal** + tags.
- **Publicação:** `destaque` (toggle), status + agendamento (`data_publicacao`).
- **FAQ:** repeater (`pergunta`/`resposta`, ordenável).
- **Painel de SEO:** `seo_titulo`/`seo_descricao` (com contadores + preview de snippet do Google),
  `seo_keyword`, `og_imagem`, `robots_noindex`, `canonical`, e o **placar de redação ao vivo**
  (componente Livewire/Alpine no form): nota 0–100 + checklist de sinais — keyword no título,
  na URL, no 1º parágrafo, densidade, tamanho do conteúdo, presença de subtítulos, `alt` nas
  imagens, links internos/externos. Reativo ao conteúdo digitado; **não** persiste a nota.
- `canAccessPanel` segue o padrão da Fase 1 (gate por ambiente; hardening de papel é item geral).

## Front público — Variante B "Semente de Luz" (Blade SSR + Livewire/Alpine pontual)

Reaproveita o layout base da Fase 1 (`<x-layout.app>`: header sticky + nav roxa + footer). Item de
menu "Sementeira" ativo. Tokens, cores e tipografia conforme `design_handoff_sementeira/design-system/`.
Fidelidade alta ao handoff; a ilustração autoral da **árvore de luz** entra como placeholder até a
arte ser fornecida (as animações CSS de raios/halo/partículas já emolduram o espaço).

### `/sementeira` — listagem (componente Livewire reativo)
- **Herói imersivo** (`min-height:480px`, gradiente roxo): camadas decorativas à direita (raios
  `slRays`, halo `slGlow`, partículas `slRise`, placeholder da árvore) + post em **destaque**
  (chip dourado, kicker "Em destaque", H1, dek, CTA, meta). `prefers-reduced-motion` desliga animações.
- **Chips de categoria** reativos (filtra a lista sem reload; chip ativo sólido roxo).
- **Corpo** `grid 1fr 312px`:
  - **Coluna principal:** cabeçalho "Últimas publicações" + ordenar; 1º item = card horizontal
    (imagem + texto); demais em grid `1fr 1fr` com acento de cor por categoria; "Carregar mais".
  - **Barra lateral:** **Mais lidas** (top por `visualizacoes`, numerada) · **Reflexão do dia**
    (card roxo com glow; frase de uma fonte **trocável** — hoje `ReflexaoDoDiaConfig`/setting,
    futuramente Agenda Reforma Íntima) · **Navegar por categoria** (com contagem).
- **Newsletter:** faixa creme (apenas visual nesta fatia).

### `/sementeira/{slug}` — artigo (single, SSR)
- **Barra de progresso de leitura** (fixed, top; largura = % de rolagem; `scroll` passive).
- **Herói (variante B):** escuro com foto de abertura ao fundo + overlay roxo; breadcrumb claro;
  chip dourado de categoria (cor da principal); H1 branco; meta (data + tempo de leitura; **sem
  byline de autor** — assinatura institucional do CEMA).
- **Corpo de leitura** (trilho de compartilhar + coluna `max-width:720px`):
  - **Trilho sticky:** WhatsApp (`wa.me`), Facebook (sharer), Copiar link (`navigator.clipboard`),
    **Curtir** (toggle, `localStorage`, contador) — ícones SVG inline.
  - **Artigo:** parágrafo de abertura, corpo, subtítulos H2, **pull-quotes**, **imagens
    alinhadas/redimensionadas** no corpo (flutuam com o texto contornando; empilham no mobile —
    CSS cobre tanto a saída nova do editor quanto as classes do Gutenberg migrado), **galeria
    3-col → lightbox** (de `post_imagens`), **acordeão de FAQ** (de `post_faqs`), callout do
    Evangelho quando presente no conteúdo, barra de tags. **Sem caixa de autor** (blog assinado
    pela instituição).
  - **+1 em `visualizacoes`** (uma vez por sessão; throttle).
- **Anterior/próxima** (por data, dentro do escopo publicado) e **Relacionados "Continue
  semeando"** (3 cards da mesma categoria principal; hover eleva).

### Alternância de variante (infra)
`config('blog.variante') = auto | serena | semente`. Em `auto`, regra mensal (a definir a política
editorial — paridade do mês como gancho do protótipo). Nesta fatia, fixa em `semente` (só B existe).
O layout do single é compartilhado; só o herói muda por variante.

## SEO (essencial + placar)

- **Por página:** `<title>` e meta description (custom ou default
  `{titulo} — Sementeira de Luz · CEMA` / `resumo`), `canonical`, `robots`, **OpenGraph/Twitter**
  (imagem = `og_imagem` ?? destacada).
- **Dados estruturados (JSON-LD):** `Article` (single; `author`/`publisher` = `Organization`
  "Centro Espírita Maria Madalena") + `FAQPage` (quando há FAQ) + `BreadcrumbList`; `ImageObject`
  para a destacada. `Blog`/`CollectionPage` na listagem.
- **Sitemap:** `sitemap.xml` com posts publicados + páginas de categoria (estender/criar).
- **Placar de redação:** no Filament (ver Admin) — auxílio ao redator, não afeta a página.

## Estratégia de URL e redirects

- Canônica: **`/sementeira`** (listagem), **`/sementeira/{slug}`** (artigo),
  `/sementeira?categoria={slug}` (filtro).
- **301** (preservar links divulgados):
  - raiz `/{slug}/` → `/sementeira/{slug}` (rota catch-all registrada **por último**, só para
    slugs de posts existentes; senão segue o fluxo normal/404).
  - `/categoria/{slug}` → `/sementeira?categoria={slug}`.
- Cuidado com colisão de slug na raiz (páginas/rotas nomeadas resolvem antes do catch-all).

## Performance / A11y (orçamento)

- HTML enxuto (o WP atual gasta ~0,5 MB/página; ficar bem abaixo); `lazy-load` + `width/height`
  nas imagens (galerias podem ter 30+); fontes self-hosted já no build.
- `prefers-reduced-motion` desliga as animações decorativas; foco visível; semântica + `aria-*`;
  contraste — cores de acento só em elementos grandes/ícones, nunca texto pequeno.
- Mobile-first: masonry/colunas caem para 1; barra lateral empilha; trilho de compartilhar vira
  barra inferior; herói reduz altura; header off-canvas (já existe).

## CSS do conteúdo (referência)

Folha única que estiliza o corpo do artigo cobrindo **a saída nova do RichEditor e o HTML
do Gutenberg migrado**: imagem flutuante (texto contornando), redimensionamento, legendas,
bloco "Mídia e Texto" e responsividade (mobile empilha). Colocar em arquivo importado após o
Tailwind (ex.: `resources/css/conteudo.css`) ou em `@layer components`. Aplicar a classe
`.conteudo-artigo` no contêiner que renderiza `{!! $post->conteudo !!}` (a coluna de leitura
`max-width:720px` do single). **Simplificação recomendada:** fazer o editor emitir as mesmas
classes do WordPress (`alignleft`/`alignright`/`aligncenter`) → um só CSS cobre tudo (já há
aliases `.align-*` caso o TipTap gere outro nome). Trocar o cinza da legenda pelo token do design.

```css
/* Contém os floats internos (clearfix) */
.conteudo-artigo::after { content: ""; display: block; clear: both; }

/* Imagem nunca estoura o contêiner e mantém a proporção mesmo com largura definida */
.conteudo-artigo img { max-width: 100%; height: auto; }

/* Alinhamento / float (editor novo + Gutenberg) */
.conteudo-artigo .alignleft, .conteudo-artigo .align-left, .conteudo-artigo figure.alignleft {
  float: left; margin: 0.25rem 1.5rem 1rem 0;
}
.conteudo-artigo .alignright, .conteudo-artigo .align-right, .conteudo-artigo figure.alignright {
  float: right; margin: 0.25rem 0 1rem 1.5rem;
}
.conteudo-artigo .aligncenter, .conteudo-artigo .align-center, .conteudo-artigo figure.aligncenter {
  display: block; float: none; clear: both; margin-left: auto; margin-right: auto;
}
.conteudo-artigo .alignnone { float: none; }

/* Subtítulos sempre começam ABAIXO de uma imagem flutuante */
.conteudo-artigo h2, .conteudo-artigo h3 { clear: both; }

/* Legenda (figcaption) */
.conteudo-artigo figure { margin: 1.5rem 0; }
.conteudo-artigo figure.alignleft, .conteudo-artigo figure.alignright { margin-top: 0.25rem; }
.conteudo-artigo figcaption {
  margin-top: 0.5rem; font-size: 0.875rem; line-height: 1.4;
  color: #6b7280; /* trocar pelo token do design */ text-align: center;
}

/* Tamanhos do Gutenberg (fallback quando não há largura no estilo) */
.conteudo-artigo .size-thumbnail { max-width: 150px; }
.conteudo-artigo .size-medium    { max-width: 300px; }
.conteudo-artigo .size-large     { max-width: 1024px; }
.conteudo-artigo .size-full      { max-width: 100%; }

/* Bloco "Mídia e Texto" (foto e texto lado a lado, sem contorno) */
.conteudo-artigo .wp-block-media-text {
  display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; align-items: center; margin: 1.5rem 0;
}
.conteudo-artigo .wp-block-media-text .wp-block-media-text__media img { width: 100%; }

/* Responsivo: no mobile tudo empilha (sem float, largura cheia) */
@media (max-width: 640px) {
  .conteudo-artigo .alignleft, .conteudo-artigo .align-left,
  .conteudo-artigo .alignright, .conteudo-artigo .align-right,
  .conteudo-artigo figure.alignleft, .conteudo-artigo figure.alignright {
    float: none; width: 100% !important; max-width: 100%; margin: 1rem 0;
  }
  .conteudo-artigo .wp-block-media-text { grid-template-columns: 1fr; }
}
```

Notas críticas: o `width: 100% !important` no mobile **anula** a largura escolhida no desktop
(senão uma foto de 40% ficaria minúscula no celular); o `clear: both` nos `h2/h3` evita o
subtítulo "subir" ao lado da imagem.

## Testes e verificação

- **Unit:** `unserialize` de `_faq` e `_fotos_carrossel_` (ordem correta); limpeza de blocos
  Gutenberg + classes Croco; cálculo de tempo de leitura; defaults de SEO; fallback Rank Math→Yoast.
- **Feature:** importação **idempotente** (2× = mesmo estado); `/sementeira` e `/sementeira/{slug}`
  → 200 com conteúdo certo; **só `publicado` aparece**; **301** da raiz e de `/categoria`;
  JSON-LD `Article`/`FAQPage` presentes; incremento de `visualizacoes`; filtro por categoria.
- **Manual (localhost):** os 44 posts corretos no público e no admin; FAQ e galeria/lightbox
  funcionando; responsivo (mobile/tablet/desktop); peso de HTML bem abaixo do WP; contraste.

## Critérios de pronto (Definition of Done)

- 44 posts migrados (conteúdo limpo, imagem destacada, categorias, FAQ e galeria quando houver).
- Admin permite criar/editar post completo (conteúdo, mídia, taxonomia, FAQ, galeria, SEO + placar).
- Listagem e single públicas na variante B, responsivas e fiéis ao handoff.
- SEO essencial ativo (meta/OG/JSON-LD/sitemap/canonical) + 301 preservando os links antigos.
- `php artisan test` verde + verificação manual no localhost; página leve.

## Riscos / pontos de atenção

- **Túnel SSH:** importação e introspecção dependem dele ativo; o comando detecta e orienta.
- **Limpeza do HTML Gutenberg/Croco:** preservar a leitura sem deixar classes/markup órfãos
  quebrando o layout; sanitização não pode comer conteúdo legítimo (revisar o perfil purifier).
- **Galerias grandes** (até 34 imagens): custo de download/armazenamento e de render — `lazy` obrigatório.
- **Colisão de slug na raiz** (catch-all 301) — registrar por último e casar só posts existentes.
- **Arte da árvore de luz** ainda não existe — entregar com placeholder + animações CSS.
- **Fuso de `post_date`** — alinhar com o tratamento adotado nas palestras.
- **RichEditor: imagem ao lado do texto + redimensionar** — **decidido (2026-06-26): extensão
  TipTap (JS)**. A investigação no código mostrou que o *custom block* do Filament **não** gera
  HTML simples: é salvo como nó-placeholder e materializado pelo `RichContentRenderer` (sanitizado
  pelo **Symfony HtmlSanitizer**, não pelo purifier do projeto) — adotá-lo forçaria **trocar o
  pipeline de render do single** (hoje `{!! conteudo !!}` + purifier, que já funciona nos 45 posts)
  e conciliar dois sanitizers. A **extensão TipTap** emite `<figure class="alignleft size-50">`
  direto no HTML salvo → passa pelo purifier (allow-list ampliada) + render/CSS atuais, **zero
  mudança** no pipeline. Custo: módulo JS pequeno (build + registro via plugin + espelho PHP).
  HTML responsivo (% / max-width; empilha no mobile) e compatível com as classes do Gutenberg migrado.
