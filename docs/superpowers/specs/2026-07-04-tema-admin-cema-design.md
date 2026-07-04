# Tema CEMA para o painel Filament (/admin) — Design

**Data:** 2026-07-04 · **Fatia:** Tematização visual do painel admin · **Branch:** `fase-tema-admin`

## Objetivo

Dar ao painel administrativo (`/admin`) a identidade visual do CEMA, mantendo-o
como uma camada **puramente visual**: fontes, cores, logos, superfícies e acentos
da marca, **sem alterar** funcionalidade (resources, forms, policies, widgets ou
rotas já existentes e testados). O admin deve parecer acabado e branded no
lançamento, à altura do front público (princípio big-bang).

## Contexto (o que já existe — não refazer)

- `AdminPanelProvider` já define `->colors(['primary' => Color::hex('#4e4483')])`
  (roxo institucional CEMA). O restante é o look padrão do Filament.
- Tokens CEMA vivem no `@theme` de `resources/css/app.css` e em
  `design-system/tokens.json` (primary `#4E4483`, secondary `#6E9FCB`,
  gold `#F2A81E`, danger `#C33A36`, success `#008000`, cream `#F3EDDD`,
  surface `#F6F6F6`, raio pílula `50px`, etc.).
- Fontes já são **self-hosted** no build via `bunny()` no `vite.config.js`
  (Poppins, Work Sans, Roboto e família) — sem CDN.
- Os logos já estão em `public/images/logos/` (`logo-horizontal.png`,
  `logo-icone.png`, `logo-branco.png`, `logo-vert*.png`); `public/favicon.ico`
  existe. A fonte canônica dos logos é `design_handoff_cemanet/prototype/assets/`.
- Existe `resources/css/filament/editor.css`, registrado via `FilamentAsset` no
  `boot()` do provider, com escopo `.editor-conteudo-blog` (preview do RichEditor
  do blog). **Não pode ser quebrado.**
- **Nenhum tema Filament dedicado existe ainda** (`make:filament-theme` não foi
  rodado).

## Decisões (fixadas na etapa de brainstorming)

1. **Dark mode desligado** (`->darkMode(false)`): tema claro/quente único,
   coerente com o front; menos superfície de tema para manter e testar.
2. **Dourado (`#F2A81E`) = `warning` + acento de marca**: a cor semântica
   `warning` recebe o dourado (já é um âmbar — baixo risco) **e** o dourado
   entra como acento (item de menu ativo, anel de foco, detalhes finos).
   `danger`/`success` seguem vermelho/verde.
3. **`gray` = `Color::Neutral`** (não Stone): a base do front CEMA é neutra
   (surface `#F6F6F6`); o calor vem dos acentos (creme/dourado). `Neutral` +
   esses acentos reproduz o front sem esquentar o painel além dele. Se na
   verificação visual ficar frio demais, subir para `Color::Stone` (troca de
   uma linha).
4. **Raio pílula só em botões e badges**: inputs e cards mantêm raio comedido
   (o arredondamento agressivo fica reservado aos elementos de ação/rótulo,
   como no front).

## Abordagem (escolhida: tema Filament dedicado)

Caminho canônico do Filament v5, durável a upgrades e que reusa os tokens
`@theme`:

- `php artisan make:filament-theme admin` gera
  `resources/css/filament/admin/theme.css` (importa o preset do Filament) e
  registra a entrada no `vite.config.js`.
- O provider passa a usar `->viteTheme('resources/css/filament/admin/theme.css')`.
- O `theme.css` carrega as fontes CEMA, os tokens de superfície/creme, o raio
  pílula (botões/badges) e o acento dourado.
- O `editor.css` permanece **intacto** (segue registrado via `FilamentAsset`,
  escopo `.editor-conteudo-blog`); o tema novo não sobrescreve suas regras.

Alternativas descartadas: (B) apenas um CSS "patch" via `FilamentAsset::make(Css)`
sobrescrevendo variáveis — é remendo, não reusa `@theme` e é frágil em upgrade;
(C) híbrido — paga o build do tema sem aproveitar os utilitários.

## Componentes do design

### 1. Logos, favicon e marca (provider)

Antes de referenciar, **sincronizar os logos** com a fonte canônica (passo
idempotente — os arquivos já existem em `public/images/logos/`, copiar de
`design_handoff_cemanet/prototype/assets/` garante que batem com o handoff):

- `logo-horizontal.png` → `public/images/logos/logo-horizontal.png`
- `logo-icone.png` → `public/images/logos/logo-icone.png`

Wiring no `AdminPanelProvider->panel()`:

- `->brandLogo(asset('images/logos/logo-horizontal.png'))`
- `->brandLogoHeight('2rem')`
- `->collapsedBrandLogo(asset('images/logos/logo-icone.png'))` (sidebar recolhida)
- `->favicon(asset('images/logos/logo-icone.png'))` (mesmo ícone do front)

### 2. Fontes (self-hosted, sem CDN)

O `theme.css` é um bundle **separado** do `app.css`; o `/admin` carrega só o
`theme.css` e **não** passa pela diretiva `@vite` do Blade — logo, o
`fonts.css` que o `bunny()` gera (e injeta globalmente via `@vite`) **não**
chega ao painel. Os `.woff2` do `bunny()` também têm nomes hasheados no build,
sem caminho estável para referência a partir do fonte. Por isso as `@font-face`
precisam estar **dentro do próprio bundle do tema**, via **fontsource**:

- `npm i -D @fontsource/poppins @fontsource/work-sans` (host — o container não
  tem Node).
- No `theme.css`: `@import '@fontsource/poppins/400.css';` e
  `@import '@fontsource/work-sans/400.css';` + `@import '@fontsource/work-sans/600.css';`.
  O Vite empacota os `.woff2` + `@font-face` no bundle do tema — determinístico,
  self-hosted, sem CDN e sem autoria manual de `@font-face`/`unicode-range`.
- A família de corpo do painel fica **Poppins** e a de títulos/marca **Work Sans**.
- O helper `->font()` do Filament (que puxaria a CDN do Bunny) **não** é usado.

### 3. Cores semânticas (`->colors` no provider)

| Papel Filament | Cor CEMA | Valor |
|---|---|---|
| `primary` | roxo institucional (já existe) | `Color::hex('#4E4483')` |
| `info` | azul de apoio (secondary) | `Color::hex('#6E9FCB')` |
| `warning` | dourado da marca | `Color::hex('#F2A81E')` |
| `danger` | vermelho CEMA | `Color::hex('#C33A36')` |
| `success` | verde CEMA | `Color::hex('#008000')` |
| `gray` | andaime neutro | `Color::Neutral` |

Racional do `gray`: o Filament exige uma escala neutra 50–950 completa para
fundos/sidebar/bordas; os tokens CEMA só têm neutros avulsos, insuficientes para
uma escala. `Color::Neutral` (paleta padrão do Tailwind, **não** um hex inventado)
serve de andaime; todos os hexes de marca dirigem as cores semânticas e o acento.

### 4. Tema CSS (acentos finos, sobre `@theme`)

No `resources/css/filament/admin/theme.css`, reusando os tokens CEMA (sem hex
inventado além do andaime `Neutral`):

- **Superfícies quentes**: fundo/superfícies do painel puxam o creme suave da
  marca (`#F3EDDD` em dose leve / cartões), via as variáveis de superfície do
  tema — sem competir com a legibilidade do conteúdo.
- **Raio pílula em botões e badges**: apenas esses elementos recebem o
  arredondamento do token `--radius-pill` (50px). Inputs e cards mantêm o raio
  comedido padrão.
- **Acento dourado**: item de menu ativo, anel de foco (focus ring) e detalhes
  finos usam `#F2A81E`.

### 5. Dark mode e login

- `->darkMode(false)` no provider: some o alternador claro/escuro; look único
  claro/quente.
- A tela de **login `/admin`** herda `brandLogo` + `primary` automaticamente
  (logo CEMA + botão primário roxo). Sem tela custom — apenas conferir que fica
  na identidade.

## Não-objetivos (fora de escopo)

- Nenhuma alteração em resources, forms, tables, actions, policies, widgets ou
  rotas. Se surgir necessidade funcional (ex.: gestão de usuários), é fatia à
  parte.
- Não reescrever o `editor.css` nem mudar seu registro.
- Não alterar breakpoints/responsividade do Filament (ele já é responsivo por
  padrão).
- Nenhuma CDN de fonte em runtime. As duas `devDependencies` do fontsource
  (`@fontsource/poppins`, `@fontsource/work-sans`) só existem em build e
  produzem `.woff2` self-hosted — não são dependência de runtime nem chamada
  externa.

## Salvaguardas

- Camada visual apenas — a lógica e os testes existentes do painel
  (`actingAsAdmin`, `*ResourceTest`, gestão de usuários) permanecem intocados.
- `editor.css` preservado (escopo `.editor-conteudo-blog` não conflita com o
  tema global).
- Reuso dos tokens CEMA; único não-token é o andaime `Color::Neutral`.
- Mobile: o tema não altera breakpoints; a responsividade nativa do Filament
  segue funcionando.

## Verificação (definição de pronto)

1. **Suíte Filament inteira verde** (`php artisan test`, incluindo
   `actingAsAdmin` e os `*ResourceTest`) — a mudança é config + CSS e não deve
   afetar testes; rodar para confirmar que nada quebrou.
2. **`npm run build` no host** (o container `cema-app` não tem Node) — o tema
   compila sem erro e gera os assets.
3. **Pint** limpo antes do push (o CI roda `pint --test` antes dos testes).
4. **Verificação visual manual** (dona/dono do projeto): login, dashboard, uma
   lista e um form de resource, ausência do alternador escuro, logos
   (horizontal + ícone recolhido), favicon, fontes Poppins/Work Sans renderizando
   e responsividade em mobile.

## Riscos e mitigações

- **Tema esfriar o painel** (percepção): mitigado por `Neutral` + acentos
  creme/dourado; plano B documentado (subir `gray` para `Stone`).
- **Build do tema quebrar o Vite**: entrada nova é isolada; rodar `npm run build`
  no host como gate.
- **Regra do tema vazar sobre o `editor.css`**: manter os acentos do tema em
  seletores globais do Filament, sem tocar `.editor-conteudo-blog`.
