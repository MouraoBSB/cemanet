# Handoff — Site CEMA (Centro Espírita Maria Madalena)

Pacote de transferência para implementar o site do CEMA em um codebase real,
usando o **Claude Code**. Reúne (1) a documentação do design system e (2) um
protótipo navegável em HTML que serve de referência visual e de comportamento.

---

## Como usar este pacote com o Claude Code

1. Coloque esta pasta (`design_handoff_cemanet/`) na raiz do seu repositório (ou
   abra o Claude Code dentro dela).
2. Comece a sessão pedindo algo como:
   > "Leia `design_handoff_cemanet/README.md` e os arquivos em `design-system/`.
   > Vamos reconstruir o site do CEMA neste projeto seguindo a stack descrita.
   > Use o protótipo em `prototype/` como referência visual e de interação."
3. **Fonte da verdade dos tokens e componentes:** os arquivos em `design-system/`
   (foram escritos justamente para reconstruir o site **sem WordPress**).
4. **Referência visual e de comportamento:** os arquivos em `prototype/`.

> **Ordem de leitura recomendada para o agente:**
> `design-system/design-system.md` → `tokens.json` → `componentes.md` →
> `paginas.md` → abrir o protótipo.

---

## Sobre os arquivos de design

Os arquivos em `prototype/` são **referências de design feitas em HTML** — um
protótipo que mostra a aparência e o comportamento pretendidos, **não** código de
produção para copiar e colar. A tarefa é **recriar esses designs no ambiente do
codebase de destino** (React, Vue, Astro, etc.), usando os padrões e bibliotecas
estabelecidos do projeto. Se ainda não houver projeto, ver a stack sugerida abaixo.

O protótipo foi construído como um único componente que troca de "tela" por estado
(roteamento simulado). Numa implementação real, cada tela vira uma **rota/página**.

---

## Fidelidade

**Média-alta.** Cores, tipografia, espaçamentos e interações são fiéis à marca e ao
design system. As **imagens são placeholders** (faixas com rótulo do conteúdo
esperado) e os **textos são representativos** (conteúdo de exemplo). O dev deve:
- Reproduzir layout, hierarquia, cores e interações com fidelidade.
- Substituir placeholders por imagens reais e conteúdo real (vindo do CMS/API).

---

## Stack sugerida (se o projeto ainda não existe)

Detalhes em `design-system/design-system.md` (seção 8). Resumo:
- **Framework:** Astro (ideal — SSG, HTML mínimo, zero-JS por padrão) ou Next.js se
  precisar de mais interatividade / área logada.
- **Estilo:** CSS Modules ou Tailwind, **alimentados pelos tokens** como CSS custom
  properties no `:root` (fonte única da verdade). Evitar reintroduzir page-builder.
- **Conteúdo:** SSG para palestras, palestrantes, evangelho, eventos e agenda
  (conteúdo majoritariamente estático). CMS headless (Strapi/Directus/Payload) se a
  equipe precisar editar por painel.
- **Substitutos de plugins WP/Jet → web nativa:** tabela completa em
  `design-system.md` (seção 8) — formulários HTML5 + função serverless, `<dialog>`
  para popups, `<details>` para acordeões, etc.

---

## Screenshots

Capturas das telas em `screenshots/` (referência rápida; layout mobile/estreito):
`inicio.png`, `palestra.png`, `palestra-conteudo.png`, `contato.png`,
`historia.png`, `guia.png`. Para ver as interações e o layout desktop, abra o
protótipo em `prototype/` (ver instruções abaixo).

## Telas / Views do protótipo

O protótipo cobre 4 templates de página + 1 guia de estilo. A descrição detalhada,
seção a seção, de **todos** os templates (T01–T10) está em `design-system/paginas.md`.

### 1. Início (T01) — `prototype/` rota "home"
- **Objetivo:** porta de entrada institucional.
- **Hero (3 variações para avaliação):**
  - V1 — centralizado, fundo roxo em gradiente, "partículas" douradas animadas,
    chip "Fé · Estudo · Caridade", H1, subtítulo, 2 CTAs.
  - V2 — painel duplo: à esquerda H1 + card "Meta do mês"; à direita 3 cartões
    Deus / Cristo / Caridade (roxo / azul / verde).
  - V3 — imagem full-bleed com overlay e card de texto sobreposto.
  - *Escolher uma para produção; o seletor "HERO 1/2/3" é só ferramenta de review.*
- **Seções:** grid de 6 cards de atividades; card "Próxima Palestra" com
  **contagem regressiva ao vivo** (dias/horas/min/seg); smart listing do blog
  (1 destaque + 3 itens); evangelho da semana + meta do dia; newsletter (faixa roxa);
  faixa de parceiros (FEB/UFE).

### 2. Single Palestra Pública (T06) — rota "palestra"
- **Objetivo:** página de uma palestra.
- **Layout:** hero roxo com ticker de manchetes (desktop), breadcrumb multinível,
  H1, subtítulo e CTA "Ver calendário"; barra de ações (Facebook, WhatsApp, Copiar
  link, **Curtir** com toggle); 2 colunas (card do palestrante ~30% + conteúdo ~70%
  com player de vídeo, metadados data/local e **acordeão de tópicos**); pílulas de
  taxonomia; navegação anterior/próxima; smart listing de notícias.

### 3. Contato (T05) — rota "contato"
- **Objetivo:** canais e formulário de contato.
- **Layout:** hero com partículas + breadcrumb; intro 2 colunas (imagem + texto);
  bloco creme com **redes sociais** (esquerda) e **formulário** (direita: Nome*,
  E-mail*, WhatsApp, Assunto [select], Mensagem, Enviar) → popup/confirmação "Recebemos
  a sua solicitação"; cards de endereço/horários/CNPJ.

### 4. Nossa História (T03) — rota "historia"
- **Objetivo:** página narrativa institucional.
- **Layout:** hero `section-xxl`; **navegação lateral por âncoras** (pontos fixos à
  direita, com rolagem suave e destaque do ativo — desktop); 4 blocos alternados
  texto/imagem (lado invertido a cada bloco); carrossel horizontal de fotos
  (scroll-snap); tabela da diretoria (Nome / Período / Cargo).

### 5. Guia de Estilo — rota "guia"
- Paleta, escala tipográfica, botões, badges/tags e campos de formulário. Útil como
  checklist de componentes a portar.

### Globais (presentes em todas as telas)
- **Header:** logo + busca + links Entrar/Cadastrar (desktop) / hambúrguer (mobile).
  **Mega-menu** horizontal com dropdowns no desktop; **off-canvas** lateral no mobile
  (submenus em `<details>`). 8 itens raiz (lista completa em `componentes.md` §2 e
  `paginas.md`).
- **Footer:** logo, listas de navegação, newsletter, redes sociais, barra legal
  (endereço, CNPJ, copyright, créditos DECOM).

---

## Interações & Comportamento

- **Roteamento:** no protótipo é por estado; na produção, rotas reais por CPT
  (`/palestras/{slug}`, `/palestrantes/{slug}`, etc.).
- **Mega-menu (desktop):** dropdown revelado no `:hover` do item (CSS).
- **Off-canvas (mobile):** abre pelo hambúrguer; overlay escurece; submenus em
  `<details>` nativo. Implementar como `role="dialog" aria-modal` com focus-trap e
  fechamento por `Esc`.
- **Acordeões (tópicos / evangelho):** `<details>/<summary>` nativos.
- **Contagem regressiva:** `setInterval` a cada 1s calculando a diferença até a data
  da próxima palestra.
- **Curtir / Favoritar:** toggle de estado (localStorage anônimo ou conta via API).
- **Compartilhar:** links `wa.me` / `facebook.com/sharer` + Web Share API no mobile;
  "Copiar link" via Clipboard API.
- **Formulários:** validação HTML5 (`required`, `type="email"`) + submit AJAX →
  mensagem de confirmação (popup `<dialog>`).
- **Responsividade:** mobile-first. No protótipo a troca desktop/mobile é por
  `window.innerWidth < 1024`; na produção, prefira **um só markup responsivo** com
  CSS (grid/flex + `clamp()`), evitando markup duplicado por viewport.
- **Transições:** entradas suaves; hover em cards eleva (`translateY(-4px)` + sombra).

---

## Design Tokens

Valores completos em `design-system/tokens.json` e bloco `:root` pronto em
`design-system/design-system.md` (seções 2–4). Resumo:

**Cores**
| Token | Hex | Uso |
|---|---|---|
| primary | `#4E4483` | roxo âncora — títulos, header, botões primários |
| secondary | `#6E9FCB` | azul — links, destaques |
| accent | `#89AB98` | verde — acentos, ícones |
| dourado (marca) | `#F2A81E` | destaque da logo — CTAs, badges, ativo |
| orange | `#E79048` | alerta / badge |
| danger | `#C33A36` | erro / curtido |
| cream | `#F3EDDD` | fundo suave / cartões |
| surface | `#F6F6F6` | fundo de seções |
| border | `#E4E4E4` | bordas / divisores |
| text | `#000000` / `#26242e` | texto |
| text-secondary | `#414141` | subtítulos |
| text-muted | `#7A8A8A` | metadados / legendas |
| footer-bg | `#2f2952` | roxo escuro do rodapé |

> **A11y:** `secondary` (#6E9FCB) e `accent` (#89AB98) ficam abaixo de 4.5:1 como
> texto sobre branco — reserve-os para elementos grandes, ícones, fundos ou bordas.

**Tipografia**
- Títulos (h1–h3): **Work Sans** (400/600). Corpo/subtítulos (h4–h5): **Poppins** (400).
- UI/labels: **Roboto** (apoio). Citações: **Roboto Slab**. Mono/labels técnicos no
  protótipo: **Roboto Mono**.
- Escala fluida com `clamp()` — valores exatos em `tokens.json` / `design-system.md` §3.

**Espaçamento, raios, sombras:** escala e variáveis em `design-system.md` §4.
Raios usados no protótipo: 9–22px (cards), 50px (pílulas/botões), 50% (avatares).

---

## Assets

Em `prototype/assets/` (logos enviadas pelo cliente, PNG com transparência):
- `logo-horizontal.png` — lockup horizontal (casa + texto), para o **header**.
- `logo-branco.png` — versão branca/dourada, para fundos escuros (**footer**).
- `logo-icone.png` — só o ícone da casa.
- `logo-vert.png` / `logo-vert-comp.png` — versões verticais.

As demais imagens no protótipo são **placeholders** (faixas listradas com rótulo
monospace indicando o conteúdo esperado, ex.: "foto do palestrante"). Substituir por
imagens reais em `webp/avif` com `srcset` e `loading="lazy"`.

**Ícones:** o protótipo usa SVGs simples (busca, setas) e marcadores textuais. No
original há dois sets (`huge.*` e `hm.*`); recomenda-se substituir por **SVGs inline**.

---

## Arquivos deste pacote

```
design_handoff_cemanet/
├── README.md                      ← este arquivo (auto-suficiente)
├── screenshots/                   ← capturas das telas (PNG)
├── design-system/
│   ├── design-system.md           ← guia consolidado (marca, tokens, stack)
│   ├── tokens.json                ← tokens (cores, tipo, espaçamento, breakpoints)
│   ├── componentes.md             ← inventário de componentes + estrutura HTML
│   └── paginas.md                 ← mapa de templates T01–T10, seção a seção
└── prototype/
    ├── CEMA Site.dc.html          ← protótipo navegável (todas as telas)
    ├── Placeholder.dc.html        ← componente de placeholder de imagem
    ├── support.js                 ← runtime necessário para abrir o protótipo
    └── assets/                    ← logos da marca
```

> **Como abrir o protótipo:** sirva a pasta `prototype/` por um servidor estático
> local (ex.: `npx serve prototype`) e abra `CEMA Site.dc.html` — abrir via
> `file://` direto pode bloquear o carregamento do `support.js` e dos assets.

---

## Modelo de conteúdo (CPTs)

O conteúdo atual vive em Custom Post Types do WordPress/JetEngine. Mapeamento completo
em `design-system/design-system.md` §7. Principais: `palestra_publica`, `palestrantes`,
`evangelho`, `mensagem-mediunicas`, `_evento`, `agenda-reforma`, `autores-espirituais`
+ taxonomia `assuntos-principais`. Numa reconstrução, viram coleções servidas por CMS
headless, dados estáticos (Markdown/JSON) ou API própria.
