# Handoff: Sementeira de Luz — blog do CEMA

## Visão geral

**Sementeira de Luz** é o blog / portal de notícias do CEMA (Centro Espírita Maria Madalena). Este pacote redesenha o blog — hoje uma grade genérica de tema WordPress — em um produto editorial profissional, dentro da identidade visual do site CEMA.

O nome é simbólico ("a sementeira que emana luz" — uma árvore plantada que floresce). O conceito da **árvore que emana luz** aparece de forma sutil, apenas nos cabeçalhos das páginas e em detalhes (nascente de luz, sementes ao vento), nunca dominando o conteúdo.

O redesenho entrega **duas direções visuais** mantidas como **variantes alternáveis**, mais a **página de artigo (single)**:

- **Variante A — "Luz Serena"**: revista espírita serena, fundo creme, grade editorial *masonry*, tipografia protagonista, muito respiro.
- **Variante B — "Semente de Luz"**: portal vivo, herói imersivo roxo-noite com a árvore de luz animada, chips de categoria e barra lateral (Mais lidas / Reflexão do dia).
- **Single (artigo)**: experiência de leitura compartilhada pelas duas variantes (só o herói muda conforme a variante ativa).

### Alternância mês a mês (requisito do cliente)

O cliente quer **alternar a variante mês a mês**. No protótipo isso é uma prop configurável `variant` com 3 valores: `auto` (padrão), `serena`, `semente`.

- Em **`auto`**, o protótipo escolhe a variante pela **paridade do mês** (`new Date().getMonth() % 2 === 0 ? 'serena' : 'semente'`). Esse é o gancho de "alternância automática" — na implementação final, troque por uma regra de calendário/CMS conforme a política editorial (ex.: tabela mês→variante, ou um campo configurável por mês).
- O seletor flutuante no rodapé do protótipo serve **apenas para pré-visualizar** as duas variantes e as duas páginas; **não faz parte do produto final** e deve ser removido na implementação.

---

## Sobre os arquivos de design

Os arquivos em `prototype/` são **referências de design feitas em HTML** — protótipos que mostram a aparência e o comportamento pretendidos, **não código de produção para copiar diretamente**.

A tarefa é **recriar estes designs no ambiente do codebase de destino** (no caso do CEMA, a recomendação do design system é **Astro** ou **Next.js** sem WordPress — ver `design-system/design-system.md` §8), usando os padrões e bibliotecas estabelecidos. Se ainda não houver ambiente, escolha o framework mais adequado e implemente lá.

> Os protótipos usam um runtime próprio ("Design Component" / `support.js`) só para renderizar HTML+JS num único arquivo. **Ignore esse runtime** — ele não deve ser portado. Interesse: a marcação, os estilos inline, os tokens e os comportamentos descritos abaixo.

### Como abrir os protótipos
Abra `prototype/Sementeira de Luz.dc.html` em um navegador. Use o seletor no rodapé para alternar **Variante** (Luz Serena / Semente de Luz) e **Página** (Notícias / Artigo). O arquivo `prototype/Sementeira — Notícias (direções).dc.html` mostra as duas direções da listagem lado a lado (artefato de comparação).

---

## Fidelidade

**Alta fidelidade (hifi).** Cores, tipografia, espaçamentos e interações são finais e devem ser reproduzidos fielmente, usando as bibliotecas/padrões do codebase. Os valores exatos estão na seção **Design Tokens** e no `design-system/`.

As áreas listradas com rótulo em monoespaçada são **placeholders de imagem** — marcam onde entram fotos/ilustrações reais (ver **Assets**). A ilustração da **árvore de luz** (herói da variante B e faixa do masthead da variante A) é o ponto de **arte autoral** a ser produzido.

---

## Telas / Views

### 1. Notícias — Variante A "Luz Serena"
- **Propósito**: página inicial do blog (arquivo/listagem) na variante editorial serena.
- **Layout** (container central, `max-width: 1080px`, `padding-inline: 26px`):
  1. **Masthead** (centralizado): fundo `linear-gradient(180deg,#F6F1E4,#F3EDDD 60%,#fff)`; halo de luz radial dourado animado atrás do título (`radial-gradient` dourado, animação de respiro 7s); kicker mono "BLOG ESPÍRITA DO CEMA" em verde `#89AB98`; H1 "Sementeira de Luz" Work Sans 600, `clamp(2.6rem,1.8rem+3vw,3.9rem)`, cor `#4E4483`, `letter-spacing:-0.02em`; subtítulo em Roboto Slab 18px `#5b5766`; faixa ilustrada (placeholder, altura 120px, `border-radius:14px`) — copa de árvore emanando luz.
  2. **Abas de categoria**: linha centralizada, `border-bottom:1px solid #EBE8E8`; item ativo "Todas" `#4E4483` 600 com `border-bottom:2px solid #4E4483`; demais `#5b5766`. Work Sans 14px.
  3. **Destaque**: grid 2 colunas `1.05fr 0.95fr`, gap 38px. Esquerda: imagem `height:340px`, `border-radius:16px`, `box-shadow:0 14px 36px rgba(78,68,131,0.12)`. Direita: kicker mono laranja `#E79048` "CEMA em Ação · Reportagem especial"; H2 Work Sans 600 `clamp(1.9rem,1.4rem+1.6vw,2.3rem)` `#3a3266`; dek `#5b5766` 15.5px; meta mono 11.5px `#7A8A8A` (data · tempo de leitura); CTA pílula `#4E4483` texto branco.
  4. **Grade "Mais recentes"** (*masonry* via `column-count:3; column-gap:30px`, cards com `break-inside:avoid; margin-bottom:30px`): ritmo editorial com alturas de imagem variadas (155–235px), **cards só-texto** (sem imagem) e um **card de citação** em creme `#F3EDDD` (aspas Roboto Slab douradas, frase em Roboto Slab `#4E4483`, rótulo "Reflexão da semana" mono verde). Cada card: kicker de categoria (cor por categoria — ver tokens), H4 Work Sans 600 18–20px `#3a3266`, dek opcional, meta mono 10.5px.
  5. **Botão "Carregar mais publicações"**: contorno `#4E4483`, pílula.
  6. **Newsletter**: faixa `#4E4483`, centralizada, kicker dourado, H3 branco 30px, input pílula + botão `#F2A81E`.

### 2. Notícias — Variante B "Semente de Luz"
- **Propósito**: mesma listagem, na variante "portal vivo".
- **Layout**:
  1. **Herói imersivo** (`min-height:480px`, conteúdo alinhado embaixo): fundo `linear-gradient(165deg,#2f2952,#3c3468 50%,#4E4483)`. Camadas decorativas à direita: **raios** (`conic-gradient` dourado/azul, animação `slRays` 8s), **halo** radial dourado (animação `slGlow` 6s), **ilustração da árvore de luz** (placeholder, faixa direita ~430px) e **5 partículas/sementes** subindo (`@keyframes slRise`, durações 6–8s, delays escalonados). Conteúdo (esquerda, `max-width:640px`): chip dourado "Sementeira de Luz" + kicker "Em destaque"; H1 Work Sans 600 `clamp(2.1rem,1.5rem+2vw,2.85rem)` branco; dek `#d8d2ec`; CTA `#F2A81E` + meta mono.
  2. **Chips de categoria**: linha branca com `border-bottom`; chip ativo "Todas" sólido `#4E4483`; demais pílulas contornadas `#E4E4E4`, texto na cor da categoria.
  3. **Corpo**: grid `1fr 312px`, gap 42px, `max-width:1180px`.
     - **Coluna principal**: cabeçalho "Últimas publicações" + "Ordenar". 1º item = card horizontal (imagem 230px + texto). Depois grid `1fr 1fr` (gap 26px) de cards com imagem 170px; um card tem `border-left:3px solid #6E9FCB` (acento de categoria). Botão "Carregar mais" sólido `#4E4483`.
     - **Barra lateral** (`aside`, gap 26px): **"Mais lidas"** (lista numerada, números Work Sans 700 22px `#e3dcef`); card **"Reflexão do dia"** com `linear-gradient(160deg,#4E4483,#3a3266)`, glow dourado no canto, citação Roboto Slab; card **"Navegar por categoria"** (lista com contagem).
  4. **Newsletter**: faixa creme `#F3EDDD`, layout horizontal (texto à esquerda, form à direita), botão `#4E4483`.

### 3. Artigo (Single)
- **Propósito**: leitura de uma publicação. Reportagem de exemplo: "65 anos do CEMA: a sementeira que Zé Paulista plantou em Planaltina".
- **Barra de progresso de leitura**: `position:fixed; top:0; height:3px`, trilho `rgba(78,68,131,0.10)`, preenchimento `linear-gradient(90deg,#F2A81E,#E79048)` cuja largura = % de rolagem da página (`transition:width .12s linear`). Só na single.
- **Herói (varia por variante)**:
  - *Serena*: fundo creme + halo dourado; centralizado; breadcrumb; kicker laranja; H1 `clamp(2.1rem,1.5rem+2.4vw,3.1rem)` `#3a3266`; dek Roboto Slab 19px; linha de meta com avatar do autor (círculo `#4E4483` "D"), data e tempo; **imagem de abertura** larga `height:440px`, `border-radius:18px`, sombra forte.
  - *Semente*: herói escuro `min-height:560px` com **foto de abertura ao fundo** (opacity .5) + overlay `linear-gradient` roxo; halo + 2 partículas; breadcrumb claro; chip dourado; H1 branco; meta clara (avatar dourado).
- **Corpo de leitura** (grid `54px minmax(0,1fr)`, gap 30px, `max-width:1080px`):
  - **Trilho de compartilhar** (sticky, `top:96px`): botões circulares 44px — WhatsApp (`#25862e`), Facebook (`#3b5998`), Copiar link (`#4E4483`), **Curtir** (toggle: borda/cor `#C33A36`, preenche vermelho quando ativo) + contador.
  - **Coluna do artigo** (`max-width:720px`): parágrafo de abertura 19px `#3a3266`; corpo 16.5px `#403c4a` `line-height:1.8`; subtítulos H2 Work Sans 600 26px `#4E4483`; **pull-quote** em `figure` creme (aspas douradas, citação Roboto Slab 22px itálico `#4E4483`, legenda mono verde); **galeria** grid 3 colunas (imagens 150px, `cursor:zoom-in`) que abrem **lightbox**; **callout do Evangelho** com `border-left:3px solid #89AB98`, fundo `#f3f6f4`, citação Roboto Slab; barra de tags + compartilhar inline; **caixa do autor** (avatar 60px + bio) fundo `#F6F6F6`.
- **Navegação anterior/próxima**: faixa `#F6F6F6`, dois links com rótulo mono + título.
- **Relacionados "Continue semeando"**: grid 3 cards (imagem 160px + kicker + H3), hover eleva (`translateY(-4px)` + sombra).

### Globais (todas as telas)
- **Header** (sticky, `z-index:80`): barra branca com logo (`assets/logo-horizontal.png`, h40) + busca pílula + "Entrar"; abaixo, nav roxa `#4E4483` com 8 itens mono 12.5px (`#cfc7e8`); "Sementeira" ativo em pílula dourada `#F2A81E` texto `#3a3266`.
- **Footer**: `#2f2952`, grid 4 colunas (logo branco + descrição / Sementeira / Institucional / Acompanhe com ícones sociais circulares) + barra legal (endereço, CNPJ, copyright).
- **Lightbox**: overlay `rgba(28,24,46,0.88)`, `z-index:150`, imagem central `min(900px,92vw) × min(620px,80vh)` `border-radius:14px`; fecha no clique no fundo ou no "×".
- **Toast**: pílula `#3a3266` no topo, some em 2.4s.

---

## Interações e comportamento

| Interação | Comportamento |
|---|---|
| **Trocar variante** | `variant` → `serena`/`semente`. No produto: definido pela regra mensal (não pelo seletor). |
| **Navegar Notícias ↔ Artigo** | Cliques em cards/destaque/CTA levam ao artigo; breadcrumb/logo "Sementeira" voltam à listagem. Ao navegar, rola ao topo e zera o progresso. |
| **Barra de progresso** | Listener de `scroll` (passive) → `scrollY / (scrollHeight - innerHeight)`, clamp 0–1, atualiza largura. Só na single. |
| **Galeria → Lightbox** | Clique na miniatura abre overlay com a imagem ampliada; clique no fundo/× fecha. |
| **Curtir** | Toggle local; muda cor para `#C33A36` e incrementa o contador; toast de confirmação. |
| **Compartilhar** | WhatsApp (`wa.me`), Facebook (`facebook.com/sharer`), **Copiar link** (`navigator.clipboard` + toast). No protótipo são stubs com toast. |
| **Newsletter / busca / menus** | Stubs com toast no protótipo — ligar a endpoint/serviço real. |
| **Hovers** | CTAs sobem `translateY(-2px)`; cards relacionados sobem `translateY(-4px)` + `box-shadow:0 16px 34px rgba(78,68,131,0.14)`. |
| **Animações de fundo** | `slGlow` (respiro do halo, 6–7s), `slRays` (raios, 8s), `slRise` (sementes subindo, 6–8s), `slFade` (entrada de toast/lightbox). Decorativas; respeitar `prefers-reduced-motion` na implementação. |

### Responsividade
O protótipo é **desktop-first**. Na implementação, aplicar os breakpoints do design system (`design-system.md` §4): a grade *masonry* de 3 colunas da variante A deve cair para 2/1 colunas; o layout `1fr 312px` da variante B deve empilhar a barra lateral abaixo no mobile; o trilho de compartilhar vertical vira barra horizontal/inferior; herói reduz altura. Header vira off-canvas (já existe no site CEMA).

---

## Gerenciamento de estado

Estado necessário (mínimo do protótipo):
- `variant`: `'serena' | 'semente'` — derivado da regra mensal (ou rota/config).
- `route`: `'list' | 'single'` — no produto, são **rotas reais** (`/sementeira` e `/sementeira/{slug}`), não estado.
- `progress`: número 0–1 — derivado do scroll (single).
- `liked` / `likes`: estado de curtida (anônimo em `localStorage`, ou via API com conta).
- `lightbox`: rótulo da imagem aberta ou `null`.
- `toast`: mensagem efêmera ou `null`.

Dados (hoje mockados no protótipo) devem vir do CMS/coleção de posts: título, slug, categoria, data, tempo de leitura, imagem de destaque, dek, corpo (rich text), galeria, tags, autor. Ver modelo de conteúdo em `design-system/design-system.md` §7 (o blog corresponde a um CPT de posts + taxonomia de categorias).

---

## Design Tokens

Fonte única da verdade: `design-system/tokens.json` e `design-system/design-system.md`. Resumo do que este blog usa:

### Cores
| Papel | Hex |
|---|---|
| Primária (roxo) | `#4E4483` |
| Roxo escuro (heróis/footer) | `#3c3468` / `#2f2952` / `#3a3266` |
| Dourado (marca/CTA) | `#F2A81E` |
| Verde (acento) | `#89AB98` (texto sobre claro: `#5f8a72`) |
| Azul (acento/links) | `#6E9FCB` |
| Laranja (alerta/CEMA em Ação) | `#E79048` (texto: `#c98a2e`) |
| Vermelho (curtir) | `#C33A36` |
| Creme (superfícies) | `#F3EDDD` / `#F6F1E4` |
| Cinza de superfície | `#F6F6F6` |
| Bordas | `#E4E4E4` / `#EBE8E8` / `#F2F1F4` |
| Texto corpo | `#403c4a` / `#3a3266` |
| Texto secundário | `#5b5766` |
| Texto de apoio / meta | `#7A8A8A` / `#9a93a8` |

**Cor por categoria** (kicker/acento): Reflexões e Espiritualidade → `#4E4483`; Estudando a Mediunidade → `#6E9FCB`; Prática do Amor ao Próximo → `#89AB98`/`#5f8a72`; Datas Comemorativas → `#F2A81E`/`#c98a2e`; CEMA em Ação → `#E79048`.

### Tipografia (Google Fonts)
- **Work Sans** (400/500/600/700) — títulos, kickers de UI, números.
- **Poppins** (300/400/500/600) — corpo, dek, interface.
- **Roboto Mono** (400/500) — kickers de categoria, metadados, rótulos (uppercase, `letter-spacing` .1–.22em).
- **Roboto Slab** (400/500/600) — citações, pull-quotes, subtítulos editoriais (frequentemente itálico).
Escala fluida com `clamp()` — ver `design-system.md` §3.

### Raio / sombra / espaçamento
- Raios: cards 12–16px; imagens 11–18px; pílulas/badges `50px`; avatares/ícones `50%`.
- Sombras: card hover `0 16px 34px rgba(78,68,131,0.14)`; imagem destaque `0 14px 36px rgba(78,68,131,0.12)`; abertura single `0 22px 50px rgba(78,68,131,0.18)`; lightbox `0 30px 80px rgba(0,0,0,0.5)`.
- Containers: listagem `max-width:1080–1180px`; coluna de leitura `max-width:720px`; padding lateral 26px.
- Gaps: grade masonry `column-gap:30px`; grids de cards 24–26px; corpo do artigo 30px.

---

## Assets
- **Logos** (em `prototype/assets/`): `logo-horizontal.png` (header), `logo-branco.png` (footer), `logo-icone.png`, `logo-vert.png`, `logo-vert-comp.png`. São os logos oficiais do CEMA.
- **Placeholders de imagem**: todas as áreas listradas marcam fotos/ilustrações reais a serem fornecidas. Rótulos descritivos no protótipo indicam o conteúdo esperado (ex.: "foto — Zé Paulista plantando a árvore, anos 1960", "12º Encontro da Família CEMA").
- **Arte autoral — árvore de luz**: a ilustração-herói da variante B (tronco/raízes luminosos, copa que vira sementes brilhantes subindo) e a faixa do masthead da variante A são o principal item de design a produzir. As animações de raios/halo/partículas já existem em CSS e emolduram essa ilustração.
- **Ícones**: o protótipo usa letras (W/f/Y/I) e glifos como stand-in para ícones sociais e de ação — substituir por SVGs inline (WhatsApp, Facebook, YouTube, Instagram, copiar, coração, lupa, setas).

---

## Screenshots (pasta `screenshots/`)
Referências visuais renderizadas das telas (alta resolução):
- `01-noticias-luz-serena.png` — listagem completa, variante A.
- `02-noticias-semente-de-luz.png` — listagem completa, variante B.
- `03-artigo-single-serena.png` — artigo (single) completo, herói variante A.
- `04-artigo-single-semente.png` — artigo (single) completo, herói variante B.
- `05-artigo-corpo-leitura.png` — detalhe do corpo de leitura (subtítulo, pull-quote, galeria) em fidelidade legível.
- `06-galeria-lightbox.png` — galeria ampliada (lightbox aberto).

> O seletor flutuante de protótipo foi ocultado nas capturas — ele não faz parte do produto.

## Arquivos neste pacote
- `prototype/Sementeira de Luz.dc.html` — **protótipo principal** (2 variantes da listagem + single + globais + interações).
- `prototype/Sementeira — Notícias (direções).dc.html` — as duas direções da listagem lado a lado (comparação).
- `prototype/Placeholder.dc.html` — componente de placeholder de imagem usado pelos protótipos.
- `prototype/assets/` — logos do CEMA.
- `prototype/support.js` — runtime do protótipo (**não portar**).
- `design-system/` — guia completo da identidade do CEMA (`design-system.md`), tokens (`tokens.json`), componentes e templates. **Leitura obrigatória** — é a fonte da verdade de cores, tipografia e recomendações de stack (Astro/Next, sem WordPress).
