# Spec — Camada 4 · Fatia 3B · Front da visibilidade rica das Mensagens (badges + barreira de login + noindex + menu)

> Autoria: Thiago Mourão — https://github.com/MouraoBSB — 2026-07-20
> Enquadramento travado com o dono no kickoff da Fatia 3B. Este spec **não** improvisa além das decisões
> travadas; **cada afirmação sobre o terreno foi verificada contra o código real** (evidência `arquivo:linha`
> no §3, levantada por 6 leitores paralelos + releitura direta do resolvedor da 3A e do `MensagemController`).
> O desenho rico foi mapeado contra os handoffs `design_handoff_mensagens_lista/` e `design_handoff_mensagem_single/`
> (README + protótipo `.dc.html` + screenshots, §4). O protótipo é **referência visual, não código-alvo**;
> recriar na stack (Blade + Livewire 4 + Tailwind v4, tokens `@theme`), **sem copiar HTML**.
> Destino: **SPEC** — vai ao passe adversarial **antes** de virar plano. **NÃO implementar ainda.**
> Base: `origin/main` (HEAD **`0fa26c4`**, PR #39 — **Fatia 3A mesclada**; a 3B ramifica daqui). Branch de
> trabalho: `camada-4-fatia-3b-front-visibilidade`. Suíte baseline: **~1032 testes** (a `MEMORY.md` registra
> 3A CP-3 1032; medir com `docker compose exec -T app php artisan test --list-tests` antes de começar).
> Fundação: a **Fatia 3A** (backend da visibilidade — o resolvedor `Mensagem::podeSerVistoPor`/`scopeVisiveisPara`,
> o accessor `visibilidade()`, o pivô `mensagem_destinatario`, `MensagemPolicy::view/viewAny`, o enum
> `VisibilidadeMensagem` de 6 níveis, os predicados `User::ehMedium/ehDiretorDepae/ehPresidente/mensagensDirecionadas`)
> — hoje **INERTE no site** — e a **Fatia 2B** (o front público só-Pública: `MensagemController`, `Mensagens\Lista`,
> `AutorEspiritualController`, sitemap, componentes `x-mensagem.*`/`x-autor.card`, 301, SEO).

---

## 0. Recorte: por que esta é a fatia "3B" (e o que fica na 3C/F4/F5)

A Fatia 3 (Visibilidade rica das Mensagens) foi partida em **3A** (backend, mesclada) e **3B** (front, ESTE spec).
A 3B é onde a visibilidade rica **finalmente aparece** no site e onde nasce a **barreira de acesso**.

- **3A (mesclada, PR #39):** o **backend** — o resolvedor que decide "quem vê", **provado por teste de unidade**,
  **sem consumidor no front** (o site seguia `Mensagem::publica()` fixo da 2B).
- **3B (ESTE spec):** o **front** — (1) **lista/single ricos para logados** (badge de nível + cadeado + legenda),
  (2) a **barreira de acesso** ao single restrito (modal de login inline + os 3 desfechos), (3) **`noindex`** nas
  restritas e nas telas de barreira, (4) **religar o menu** "Mensagens Mediúnicas", e a **troca**
  `publica()` → `publicado()->visiveisPara($user)` nas superfícies de Mensagens. É a fatia da **decisão "quem vê"
  virando pixel + a barreira que impede o vazamento** de um link restrito que circula fora do site.

**Split 3B / 3C — FECHADO pelo dono (F1):** a superfície **"Minhas mensagens direcionadas"** (a aba/modo de
navegação das Direcionadas na lista) vai para a **3C** e **sai desta fatia** (removidos §4.5-modo / §6.7-modo / §9.5 /
o invariante do modo). A **3B** entrega: badges+cadeado+legenda, a **barreira de login inline** (cega, que **cobre o
single de uma Direcionada**), `noindex`, religar o menu, o swap `publica()`→`publicado()->visiveisPara($u)`, e a
**nota "direcionada a você"** no single do **próprio destinatário** (§6.7-nota — **sem** lista de destinatários). A
barreira da Direcionada fica na 3B porque um link de Direcionada circula em WhatsApp — anônimo/não-destinatário caindo
nele **tem** de ser barrado **cegamente** já agora; a leitura da própria Direcionada pelo destinatário vem de graça do
`visiveisPara`.

O que **não** é da 3B: **Curadoria (F4)** — médium cria, diretor-DEPAE ratifica/publica, máquina de estados, porta
`perfil`, campo destinatários no `/admin`. **Engajamento (F5)** — favoritar, **lida/não-lida** (pivô `mensagem_lidas`
do handoff — **não existe** e **não** se cria aqui), "vistas recentemente", curtir. O **tema é só claro** (herdado da 2B).

---

## 1. Contexto e objetivo

A Camada 4 é o módulo **Mensagens Mediúnicas**. A 2A criou a entidade e migrou as 179 mensagens. A 2B publicou o
front **só das Públicas** (`Mensagem::publica()` fixo) — badges de nível, cadeado, legenda e Direcionada foram
deliberadamente **removidos** (a 2B chama isso de "F3"). A 3A entregou o **resolvedor** de visibilidade, hoje inerte.
Esta **Fatia 3B** liga o resolvedor no front e traz a **barreira de acesso**.

**Objetivo:**

1. **Consumir `visiveisPara($user)` no front** (trocar `Mensagem::publica()` fixo por
   `Mensagem::publicado()->visiveisPara($user)`) na **lista** (`Mensagens\Lista`), no **single**
   (`MensagemController@show` + `mesmoDia`/`relacionadas`) e no **select de autor** da lista.
2. **Lista/single ricos para LOGADOS:** badge de nível (`x-ui.selo-visibilidade` / selo-nível novo) + **cadeado**
   nos restritos + **legenda de bolinhas** + barra/faixa na cor do nível. **Anônimo vê só Público, sem badge algum**
   (o look da 2B, intacto) — googlebot (anônimo) continua indexando só o Público.
3. **BARREIRA DE ACESSO ao single restrito** (requisito do dono — link de msg restrita circula em WhatsApp):
   - **restrita + anônimo** → **MODAL de login INLINE** na própria página (e-mail/senha + "Entrar com Google");
     logou e pode → vê a mensagem; logou e **não** pode → "sem permissão";
   - **restrita + logado SEM acesso** → "você não tem permissão; em caso de dúvida, entre em contato" (e-mail + WhatsApp);
   - **inexistente / não-publicada** → **404**.
   O **corpo só vai ao HTML depois de autorizar** (barreira = **view própria**, sem corpo/título/OG). A Direcionada
   é **cega**: a quem não pode ver, **nunca** revelar título nem destinatários.
4. **`noindex`** nas restritas (single autorizado com nível ≠ Público) e em **toda tela de barreira**; o **Público
   segue indexável** e o **sitemap fica intacto** (só o Público — segue `publica()`).
5. **Religar o menu:** `config/navegacao.php` item "Mensagens Mediúnicas" → `rota=>'mensagens.index'`, `ativo=>true`
   + submenu "Autores Espirituais" (simetria com Palestras).

**A regra de "quem vê" NÃO é reimplementada no front** — é consumida do model (fonte única da 3A). A 3B **não**
duplica escada/recorte/bypass; só chama `publicado()->visiveisPara($u)` (listas) e `podeSerVistoPor($u)` (single).

---

## 2. Decisões travadas (não reabrir)

Do kickoff da 3B (dono) + heranças da 3A/2B:

1. **Barreira = MODAL de login inline**, **não** redirect ao `/entrar`. Mesma URL, sem ir ao menu.
2. **Direcionada = barreira CEGA:** a quem **não** pode ver, **nunca** revelar título nem destinatários (o título
   pode conter nomes). O destinatário vê a mensagem + a nota "direcionada a você" — **sem** a lista de destinatários.
3. **O CORPO só vai ao HTML depois de autorizar** — nunca antes da barreira (senão "ver fonte" burla). A barreira é
   **view própria** (não o `show.blade` com o corpo escondido por CSS).
4. **`noindex`** em **toda** tela restrita/barreira. Só o Público indexa (casa com o sitemap, que segue `publica()`).
5. **Reusar** o resolvedor da 3A (fonte única) — **não** reimplementar regra de visibilidade no front.
6. **Recriar o design na stack** (Blade+Livewire4+Tailwind) reusando `x-layout.app`, `x-ui.particulas`,
   `x-ui.selo-visibilidade` e os componentes da 2B (`x-mensagem.card` etc.) — **não** copiar o HTML do `.dc.html`.
7. **Tema só claro** (herdado da 2B). Sem dark-mode, sem F5 (favoritar/lida/vistas).
8. **A paleta AA das badges é da 3B** (a `VisibilidadeMensagem::cor()` da 3A é placeholder). Rótulos = os da 3A
   (`rotulo()`), já prontos.
9. **Sem `migrate:fresh`/`refresh`/`wipe`/`reset` nem seed destrutivo** no dev ([[nunca-migrate-fresh-no-dev]]).
   (A 3B **não** tem migration — é front; o pivô `mensagem_destinatario` já existe da 3A.)
10. **F1 (split) FECHADO (dono):** a 3B **não** inclui o modo "minhas direcionadas" (= 3C); inclui a barreira **cega**
    da Direcionada + a nota "direcionada a você" no single do destinatário (§0/§6.7-nota).
11. **F2 FECHADO (dono):** a **lista de destinatários (PII) não é exibida a ninguém** no front (nem admin/presidente);
    o destinatário vê só "direcionada a você". A lista PII é do `/admin` (F4).
12. **F5 FECHADO (dono):** **barreira-200 cega uniforme** para **todo** restrito (inclusive Direcionada); **404 só**
    para inexistente ou não-publicada.
13. **Canais de contato (F3) EDITÁVEIS pelo `/admin`** (não em arquivo de config): store `App\Models\Configuracao`
    (`contato.email`/`contato.whatsapp`) + uma Página Filament (molde `ConfiguracoesBlog`) — o dono muda sem deploy.

---

## 3. Terreno confirmado por leitura (não presumir diferente)

Verificado no código em 2026-07-20 (base `0fa26c4`). **Docblock não é evidência** — o que segue foi lido no fonte
(6 leitores paralelos + releitura direta). Referências relativas a partir de `docs/superpowers/specs/` (`../../../`).

### 3.1 O backend da 3A a CONSUMIR (fonte única — reusar, não recriar)

- [Mensagem::scopeVisiveisPara(Builder,?User)](../../../app/Models/Mensagem.php#L114-L145) — filtra **no banco**,
  não vaza título restrito; anônimo/`null` = só `nivel='publico'`; escada + 3 recortes + bypass admin/presidente.
  **Filtra só o eixo `nivel`** (o `where('nivel', Publico)` sempre + `orWhere` por nível — [:123-144](../../../app/Models/Mensagem.php#L123-L144)).
- [Mensagem::podeSerVistoPor(?User)](../../../app/Models/Mensagem.php#L90-L112) — item a item (para a barreira do single).
- [Mensagem::visibilidade(): ?VisibilidadeMensagem](../../../app/Models/Mensagem.php#L75-L78) — accessor derivado
  (método, **não** propriedade — colisão Eloquent); `null` para `nivel=null`/desconhecido (fail-closed).
- [Mensagem::scopePublica](../../../app/Models/Mensagem.php#L63-L69) = `status='publicado' AND nivel='publico'`
  (o **filtro fixo** da 2B). **⚠️ NÃO existe `scopePublicado` isolado** (status-only) na `Mensagem` — só o Evento
  tem `publicado()`. Como o `scopeVisiveisPara` **não** filtra `status` (é ortogonal, decisão da 3A §6.2), a 3B
  **precisa** de `scopePublicado(Builder): Builder` = `where('status', STATUS_PUBLICADO)` (molde do Evento) para
  compor: `Mensagem::publicado()->visiveisPara($u)`. Para anônimo isso é **idêntico** a `publica()`
  (`status='publicado' AND nivel='publico'`) ⇒ **paridade exata com a 2B** para o visitante (§6.1).
- [MensagemPolicy::view/viewAny](../../../app/Policies/MensagemPolicy.php) — delegam ao resolvedor (`Gate::forUser(null)`
  funciona para anônimo). A 3B pode usar a Policy **ou** `podeSerVistoPor` direto no controller (§6.3).
- [VisibilidadeMensagem](../../../app/Enums/VisibilidadeMensagem.php#L7-L68): 6 casos com backing values reais
  (`publico`/`trabalhadores`/`mediuns-trabalhadores`/`diretores`/`diretor-depae`/`direcionada`); `rotulo()`
  ([:33-43](../../../app/Enums/VisibilidadeMensagem.php#L33-L43)) — **pronto**; `cor(): string`
  ([:45-56](../../../app/Enums/VisibilidadeMensagem.php#L45-L56)) — **PLACEHOLDER da 3B** (docstring crava "paleta
  final da badge é da Fatia 3B"); `ehRecorte()`/`nivelMinimo()`/`opcoes()`.
- [User::mensagensDirecionadas()](../../../app/Models/User.php) (pivô `mensagem_destinatario`, **PII**),
  `ehMedium/ehDiretorDepae/ehPresidente`.

### 3.2 O front da 2B a MODIFICAR — call-sites exatos

**Rotas** — [routes/web.php:100-113](../../../routes/web.php#L100-L113): **todas públicas** (sem grupo `auth`).
`mensagens.index` ([:101](../../../routes/web.php#L101)), `mensagens.show` (`{slug}` `[a-z0-9-]+`),
`autores.index` ([:111](../../../routes/web.php#L111)), `autores.show`. ⇒ o resolvedor recebe `Auth::user()` que
pode ser `null`.

**[MensagemController](../../../app/Http/Controllers/MensagemController.php) (43 linhas):**
- `index()` [:12-17](../../../app/Http/Controllers/MensagemController.php#L12-L17) — `'totalPublicas' =>
  Mensagem::publica()->count()` ([:15](../../../app/Http/Controllers/MensagemController.php#L15)).
- `show(string $slug)` [:19-42](../../../app/Http/Controllers/MensagemController.php#L19-L42):
  `Mensagem::query()->publica()->with([...])->where('slug',$slug)->firstOrFail()`
  ([:21-25](../../../app/Http/Controllers/MensagemController.php#L21-L25)) — **hoje restrito = 404**; relacionadas
  eager `->publica()` ([:23](../../../app/Http/Controllers/MensagemController.php#L23)); **"mesmo dia"** `->publica()`
  ([:29-35](../../../app/Http/Controllers/MensagemController.php#L29-L35)). Array à view
  ([:37-41](../../../app/Http/Controllers/MensagemController.php#L37-L41)). **Sem** meta/OG no controller (vive na view).

**[Mensagens\Lista](../../../app/Livewire/Mensagens/Lista.php):** props `#[Url]`
([:19-32](../../../app/Livewire/Mensagens/Lista.php#L19-L32)); `render()`
([:81-103](../../../app/Livewire/Mensagens/Lista.php#L81-L103)) = `Mensagem::query()->publica()->with('autores')`
(**o `publica()` está em [:84](../../../app/Livewire/Mensagens/Lista.php#L84)**) + filtros + `paginate(9)`; **select
de autor** ([:100](../../../app/Livewire/Mensagens/Lista.php#L100)) = `AutorEspiritual::whereHas('mensagens',
fn ($q) => $q->publica())->...`.

**[show.blade.php](../../../resources/views/mensagens/show.blade.php) — onde o conteúdo sensível vai ao HTML:**
- **Corpo** (`@switch` de formato) [:122-130](../../../resources/views/mensagens/show.blade.php#L122-L130) (núcleo
  [:124-128](../../../resources/views/mensagens/show.blade.php#L124-L128)); a `<article>` inteira é
  [:95-131](../../../resources/views/mensagens/show.blade.php#L95-L131). Card(s) de autor
  [:134-153](../../../resources/views/mensagens/show.blade.php#L134-L153).
- **Contexto** [:80-88](../../../resources/views/mensagens/show.blade.php#L80-L88) (`{{ $mensagem->contexto }}`
  escapado, [:85](../../../resources/views/mensagens/show.blade.php#L85)).
- **Download** [:158-168](../../../resources/views/mensagens/show.blade.php#L158-L168) (gate só de negócio
  `@if ($mensagem->liberar_download && $mensagem->link_arquivo)`, [:159](../../../resources/views/mensagens/show.blade.php#L159)).
- **OG/SEO no `<head>`** — `$ogImg = getFirstMediaUrl(COLECAO_PICTOGRAFIA,'web')`
  ([:3](../../../resources/views/mensagens/show.blade.php#L3)), `<meta property="og:image">`
  ([:10](../../../resources/views/mensagens/show.blade.php#L10)), `$textoCopia`/`ld+json` derivados de
  `corpo`/`titulo` ([:4](../../../resources/views/mensagens/show.blade.php#L4),[:11-18](../../../resources/views/mensagens/show.blade.php#L11-L18)).
- **Selo "Pública" HARDCODED** no hero [:45-47](../../../resources/views/mensagens/show.blade.php#L45-L47) — hoje
  afirma "Pública" para qualquer mensagem servida (a 2B só serve pública). A 3B o torna **dinâmico** (§6.4).
- Corpos: [psicografia.blade.php:6](../../../resources/views/mensagens/corpos/psicografia.blade.php#L6)
  `{!! $mensagem->corpo !!}`; [psicofonia.blade.php:15](../../../resources/views/mensagens/corpos/psicofonia.blade.php#L15)
  `@include('mensagens.corpos.psicografia')`; [pictografia.blade.php](../../../resources/views/mensagens/corpos/pictografia.blade.php)
  intro `{!! corpo !!}` + galeria local. ⇒ **a barreira fica ACIMA do `@switch`** (não dentro dos corpos): a 3B
  **não renderiza o `show.blade`** para quem não pode ver — serve uma **view de barreira** (§6.3).

**[AutorEspiritualController](../../../app/Http/Controllers/AutorEspiritualController.php):** `index()`
[:15-37](../../../app/Http/Controllers/AutorEspiritualController.php#L15-L37) — `whereHas('mensagens', publica())`
([:21](../../../app/Http/Controllers/AutorEspiritualController.php#L21)), `withCount(['mensagens as
mensagens_publicas_count' => publica()])` ([:22](../../../app/Http/Controllers/AutorEspiritualController.php#L22)),
`with(['mensagens' => publica()])` ([:24](../../../app/Http/Controllers/AutorEspiritualController.php#L24)),
`Mensagem::publica()->count()` ([:28](../../../app/Http/Controllers/AutorEspiritualController.php#L28)); `show()`
[:39-68](../../../app/Http/Controllers/AutorEspiritualController.php#L39-L68) — `ativo()->firstOrFail()`
([:43](../../../app/Http/Controllers/AutorEspiritualController.php#L43)), `$autor->mensagens()->publica()`
([:47](../../../app/Http/Controllers/AutorEspiritualController.php#L47)). Rodapé estático de login em
[autores/show.blade.php:169-173](../../../resources/views/autores/show.blade.php#L169-L173). **⚠️ Acoplamento:**
[autor/card.blade.php:3-11](../../../resources/views/components/autor/card.blade.php#L3-L11) consome o count literal
`mensagens_publicas_count` e a relação `mensagens` **pré-filtrada por `publica()`** — trocar o filtro sem ajustar o
card produz inconsistência. ⇒ **manter `publica()` nas superfícies de Autores** (§6.6, §13-F1-menor).

**[SitemapController](../../../app/Http/Controllers/SitemapController.php):** `Mensagem::publica()`
([:36-38](../../../app/Http/Controllers/SitemapController.php#L36-L38)) + autores
([:40-44](../../../app/Http/Controllers/SitemapController.php#L40-L44)); Eventos já usa `visiveisPara(null)`
([:31-34](../../../app/Http/Controllers/SitemapController.php#L31-L34)). **A 3B MANTÉM** — para Mensagem,
`publica()` ≡ `publicado()->visiveisPara(null)` (§6.5).

### 3.3 A stack de LOGIN (para o modal inline) — Fortify headless

- Fortify **ignora as rotas nativas** ([FortifyServiceProvider:22](../../../app/Providers/FortifyServiceProvider.php#L22))
  e declara em pt-BR: `route('login')` = **`GET /entrar`** ([web.php:28](../../../routes/web.php#L28)); autenticação =
  **`POST /entrar`** → `AuthenticatedSessionController@store` ([web.php:29](../../../routes/web.php#L29)); ambos sob
  **middleware `guest`** ([web.php:27](../../../routes/web.php#L27)). Logout = `POST /sair` (`logout`, `auth`).
- **Google (Socialite):** `route('google.redirect')` = `GET /auth/google` ([web.php:51](../../../routes/web.php#L51));
  callback `google.callback`. [GoogleController](../../../app/Http/Controllers/Auth/GoogleController.php): `redirect()`
  ([:19-22](../../../app/Http/Controllers/Auth/GoogleController.php#L19-L22)) faz **full-page redirect** ao Google;
  `callback()` faz `Auth::login(remember:true)` ([:66](../../../app/Http/Controllers/Auth/GoogleController.php#L66)),
  `session()->regenerate()` ([:67](../../../app/Http/Controllers/Auth/GoogleController.php#L67)) e termina em
  **`redirect()->intended('/minha-conta')`** ([:77](../../../app/Http/Controllers/Auth/GoogleController.php#L77)).
- [config/fortify.php](../../../config/fortify.php): guard `web` ([:18](../../../config/fortify.php#L18)); `home =
  '/minha-conta'` ([:76](../../../config/fortify.php#L76)); features **só** `registration()`+`resetPasswords()`
  ([:149-152](../../../config/fortify.php#L149-L152)); `limiters.login = null` ⇒ **throttle embutido 5/min por
  email+IP** no pipeline ([:121-123](../../../config/fortify.php#L121-L123)); **sem** email verification.
- [auth/login.blade.php](../../../resources/views/auth/login.blade.php) — **Blade puro** postando no Fortify:
  `<form method="POST" action="{{ route('login') }}">@csrf` com `name=email`/`name=password`/`name=remember`
  ([:7-25](../../../resources/views/auth/login.blade.php#L7-L25)); botão Google = **link GET**
  `route('google.redirect')` ([:27-30](../../../resources/views/auth/login.blade.php#L27-L30)); usa `<x-layout.auth>`.
  **Não há componente Livewire de login.**
- **`redirect()->intended`:** **não há `LoginResponse` custom** ⇒ vale o Fortify padrão
  `redirect()->intended(config('fortify.home'))`. Hoje o **único** ponto que popula `url.intended` é o middleware
  `auth` de `/minha-conta` ([web.php:42-46](../../../routes/web.php#L42-L46)); a página de mensagem restrita renderiza
  **200 sem `auth`** ⇒ **nada popula `url.intended`** ⇒ login volta a `/minha-conta`. **A 3B precisa gravar a
  intended na sessão no GET da barreira** (§6.3). `session()->regenerate()` **preserva** dados da sessão (só rotaciona
  o ID) ⇒ a intended sobrevive ao POST **e** ao round-trip do Google.
- **CSRF:** o `<form>@csrf` funciona; **não há** `<meta name="csrf-token">` global no
  [layout público](../../../resources/views/components/layout/app.blade.php) ⇒ **evitar** login por `fetch`.
- **`authenticateUsing`** ([FortifyServiceProvider:27-45](../../../app/Providers/FortifyServiceProvider.php#L27-L45))
  valida email+senha, **bloqueia `!ativo`** e faz rehash — qualquer caminho de login **tem** de herdar isso ⇒ usar o
  pipeline Fortify (o `<form>` POST), **não** reimplementar `Auth::attempt`.
- **Molde de modal pronto:** [ui/assinar-modal.blade.php](../../../resources/views/components/ui/assinar-modal.blade.php)
  — Alpine + `<dialog>.showModal()`, `x-on:...window`, backdrop, fechar por Esc/click-fora. Bom molde para o modal de
  login. Header já usa `@guest/@else` + `route('login')` ([header.blade.php:28-30](../../../resources/views/components/layout/header.blade.php#L28-L30)).

### 3.4 O molde do FRONT-RICO com visibilidade — Eventos (fase 3)

- **Eventos barra por 404 total** — [EventoController:38-43](../../../app/Http/Controllers/EventoController.php#L38-L43):
  `...->publicado()->...->firstOrFail()` + `abort_unless($evento->podeSerVistoPor($usuario), 404)`. **Sem** modal, **sem**
  redirect, **sem** 403, **sem** noindex (o restrito nunca renderiza a não-autorizados). Consumo de `visiveisPara` em
  lista/destaque/relacionados ([:23-29](../../../app/Http/Controllers/EventoController.php#L23-L29),[:46-57](../../../app/Http/Controllers/EventoController.php#L46-L57));
  [Eventos\Lista::baseVisivel()](../../../app/Livewire/Eventos/Lista.php#L40-L43). **`Cache-Control: private, no-store`**
  na single restrita ([:66-68](../../../app/Http/Controllers/EventoController.php#L66-L68)).
- **Selo de visibilidade** — [evento/card.blade.php:31-35](../../../resources/views/components/evento/card.blade.php#L31-L35):
  **só `@auth` E `visibilidade !== Publico`** → `<x-ui.selo-visibilidade :rotulo :cor>`. **Padrão canônico a
  replicar.** O single de Evento **não** exibe o selo de visibilidade (só categoria/status).
- **Sitemap** `visiveisPara(null)` — [SitemapController:31-34](../../../app/Http/Controllers/SitemapController.php#L31-L34).
- ⚠️ **DIFERENÇA da 3B (decisão do dono):** a 3B **não** barra por 404 no restrito-publicado — barra por **página 200
  com barreira/modal** (o login pode conceder acesso). Isso obriga: **cegueira ativa** (Eventos "não vaza" porque 404;
  a 3B renderiza 200, então a barreira **tem** de ser genérica, sem título/destinatários), **corpo só após autorizar**
  (via view de barreira, não CSS), e **`noindex` explícito** (Eventos herda proteção do 404; a 3B serve 200, então
  precisa do meta). **404** fica só para **inexistente/não-publicada**.

### 3.5 `noindex`, layout e menu — pontos de inserção

- [layout/app.blade.php](../../../resources/views/components/layout/app.blade.php): `@props(['title','description'])`
  ([:1](../../../resources/views/components/layout/app.blade.php#L1)); **não há** prop `robots`/`noindex`. O `<head>`
  ([:9-27](../../../resources/views/components/layout/app.blade.php#L9-L27)) emite title/description/og:* e tem o
  **slot `head`** ([:26](../../../resources/views/components/layout/app.blade.php#L26)) — o ponto de SEO por página.
- **Precedentes de `noindex`:** [blog/show.blade.php:64-65](../../../resources/views/blog/show.blade.php#L64-L65)
  (`@if ($post->robots_noindex) <meta name="robots" content="noindex">` via `<x-slot:head>`);
  [agenda/index.blade.php:44](../../../resources/views/agenda/index.blade.php#L44) (sempre). Testes fixam o contrato
  (`assertSee('name="robots"', false)`). ⇒ **padrão da casa = emitir via `<x-slot:head>`**; a prop `:noindex` no
  layout é açúcar opcional (§6.5).
- [config/navegacao.php](../../../config/navegacao.php): o item **"Mensagens Mediúnicas"** (linha 24) é
  `['rotulo' => 'Mensagens Mediúnicas', 'ativo' => false, 'itens' => []]` — placeholder cinza/desabilitado. **Palestras**
  (linhas 15-23) é o molde: pai com `rota`+`ativo=true`+`itens[]`; cada subitem `rotulo`+`rota`+`ativo`.
- [layout/header.blade.php](../../../resources/views/components/layout/header.blade.php) renderiza o menu (desktop
  [:66-98](../../../resources/views/components/layout/header.blade.php#L66-L98), mobile [:131-161]) lendo
  `config('navegacao.menu')`. Item com `ativo=false`/sem `rota` → `<span aria-disabled>` cinza; `route()` **só** é
  chamado com `ativo && rota` (guarda dupla — sem risco de `RouteNotFoundException`). **Nenhuma mudança no header** é
  necessária: dar `itens[]` já aciona o caret/dropdown automaticamente.

### 3.6 Componentes e enum a REUSAR/ESTENDER

- [ui/selo-visibilidade.blade.php:1-9](../../../resources/views/components/ui/selo-visibilidade.blade.php#L1-L9) —
  `@props(['rotulo','cor'])`; **AA já resolvido**: `text-text-ink` (#26242e) sobre `bg-surface` (#f6f6f6) ≈ 14:1; a cor
  do enum entra **só no ponto** (`size-2 rounded-full`, `aria-hidden`). **Não** tem cadeado nem tratamento de recorte.
- [mensagem/card.blade.php:1-61](../../../resources/views/components/mensagem/card.blade.php#L1-L61) — `@props(['mensagem',
  'variante' => 'lista'])`; comentário-âncora [:6](../../../resources/views/components/mensagem/card.blade.php#L6) "Sem
  F3/F5: nenhum badge de nível, cadeado, legenda…"; **slot vazio à direita** do `justify-between`
  ([:27-30](../../../resources/views/components/mensagem/card.blade.php#L27-L30)) = onde entra o badge de nível +
  cadeado; faixa superior [:18-19](../../../resources/views/components/mensagem/card.blade.php#L18-L19) é **marca, NÃO
  nível**.
- [mensagem/linha.blade.php:14-19](../../../resources/views/components/mensagem/linha.blade.php#L14-L19) — meta com slot
  para o badge; [mensagem/selo-formato.blade.php:1-29](../../../resources/views/components/mensagem/selo-formato.blade.php#L1-L29)
  — **o molde AA de "pílula + ícone + rótulo"**: fundo `rgba(cor, 0.10-0.20)` + **texto escurecido da cor**
  (`#4E4483`/`#356197`/`#3f7256`) para atingir AA. **É este o padrão para o cadeado/badge colorido** (§6.4).
- [autor/card.blade.php](../../../resources/views/components/autor/card.blade.php) — count = `mensagens_publicas_count`
  (públicas), pluralização manual ("nunca `Str::plural`").
- [ui/particulas.blade.php](../../../resources/views/components/ui/particulas.blade.php) — **sem props** (reusar direto).
- **`x-ui.selo-visibilidade` só é usado em Eventos** (card [:33](../../../resources/views/components/evento/card.blade.php#L33),
  calendário) — em Mensagens está **reservado à 3B** (a 2B o proibiu). Assinatura `rotulo`+`cor` = **plug-and-play**
  com `VisibilidadeMensagem`.
- **CSS:** tokens em `@theme` de [app.css:21-76](../../../resources/css/app.css#L21-L76) (`--color-primary #4e4483`,
  `secondary #6e9fcb`, `accent #89ab98`, `gold #f2a81e`, `orange #e79048`, `danger #c33a36`, `cream #f3eddd`,
  `text-ink #26242e`, `text-muted #7a8a8a` ⚠️ reprova AA em texto pequeno, `surface #f6f6f6`). O CSS de Mensagens vive
  em [resources/css/mensagens.css](../../../resources/css/mensagens.css) (`@layer components`); `.cema-msg-prose`,
  `.cema-msg-card`, `.cema-msg-trecho`. **Não há** classe de badge/cadeado/legenda ainda — a 3B cria (novo bloco em
  `mensagens.css`). ⚠️ Os placeholders `VisibilidadeMensagem::cor()` **`#7C6FB0` (Médiuns) e `#C9803B` (DEPAE) não têm
  token `@theme`** — a 3B formaliza a paleta (§6.4).

---

## 4. Estudo dos handoffs (o desenho rico ↔ o que se corta)

`design_handoff_mensagens_lista/` e `design_handoff_mensagem_single/` = README + protótipo `.dc.html` (referência,
**não** copiar) + screenshots. Abaixo, a anatomia a recriar e o que **cortar** (F5 / a barreira antiga por 403).

### 4.1 Badge de nível + cadeado (lista e single)

- **Grade:** barra superior 4px na **cor do nível** (`.dc.html:276`); **badge pílula** colorido
  (`:515-518` — fundo=cor, texto branco); **cadeado** SVG quando `restricted = level!=='publica'` (`:280`,`:526`).
- **Lista:** faixa lateral 5px da cor + badge (sem cadeado no protótipo, `:321`,`:327`).
- **Single (hero):** badge "**Nível de acesso: {rótulo}**" (`.dc.html:530`) na cor do nível + cadeado se restrito
  (`:138`); `levelKey` é forçado a `'direcionada'` quando direcionada (`:498`).
- **Cores dos níveis (base do designer)** — README lista `:108`/`:105`, confirmado em `.dc.html:429-436`:
  Pública `#6E9FCB` · Trabalhadores `#A34E5C` · Médiuns `#5e8770` · Diretores `#3a4585` · Diretor-DEPAE `#7c4d8f`
  · Direcionada `#26242e`. ⚠️ **O designer usa pílula sólida + texto BRANCO** — **reprova AA** em 4 das 6 cores
  (§6.4 dá a versão AA). Rótulo do DEPAE na UI é **"Diretor-DEPAE"** (a 3A usa "Diretor do DEPAE" em `rotulo()` — §13-O2).

### 4.2 Legenda de bolinhas (dot legend)

`.dc.html:257-269`: prefixo "**Nível de acesso:**" + 5 bolinhas 8×8 com rótulo — Pública · Trabalhadores · Médiuns ·
Diretores · Diretor-DEPAE (`:566` — **sem** "Direcionada"). **Só quando logado** (é ocultada no visitante e no modo
direcionadas). ⚠️ **CORTAR (F5):** a legenda "lida/não-lida" (envelopes, `:263-267`) e os ícones lida/não-lida dos
cards (`:285-286`,`:323-324`) — dependem do pivô `mensagem_lidas` **inexistente**.

### 4.3 Contador dinâmico (hero)

`.dc.html:563`: **visitante** → "mensagens públicas"; **logado** → "mensagens disponíveis a você" (o 3º rótulo
"mensagens direcionadas a você" é do modo direcionadas = **3C**, fora daqui — a 3B tem **só 2**). O número =
`visibleBase().length` = `Mensagem::publicado()->visiveisPara($user)->count()` (base visível, **sem** filtros).

### 4.4 Single — os 3 corpos e a Direcionada (já construídos na 2B, exceto a Direcionada)

Os 3 corpos (psicografia/psicofonia/pictografia) **já existem** (2B) — a 3B **não** os toca (o gate é **acima**, §6.3).
O card "**Mensagem direcionada a**" (`.dc.html:265-281`, screenshot `05-direcionada.png`) tem a **lista de
destinatários (PII)** em pílulas com avatar-iniciais + a copy "Visível apenas para as pessoas relacionadas abaixo"
(`:271`). ⚠️ **§13-F2 (recomendação): NÃO exibir a lista de destinatários a ninguém no front** (nem ao presidente) —
o destinatário vê só a **nota "direcionada a você"** (a lista de destinatários é matéria do `/admin` = F4). A copy de
privacidade "**Área pessoal.** Estas mensagens foram endereçadas a você… Somente você e a diretoria do DEPAE têm
acesso." (lista `:251`) é reaproveitável para a nota + a barreira.

### 4.5 Modo "Minhas mensagens direcionadas" — **3C (fora da 3B; F1 fechado)**

`.dc.html:470/482-485/560-564`: a **aba/modo** que troca a base para as **direcionadas do próprio usuário**
(`User::mensagensDirecionadas`), com hero "Minhas Mensagens Direcionadas" e card "Área pessoal". **Vai para a 3C** —
**não** é desta fatia. O que a 3B garante sobre Direcionada: a **barreira cega do single** (§6.3) e a **nota
"direcionada a você"** no single do destinatário (§6.7-nota); só a *navegação* das direcionadas é 3C.

### 4.6 Telas de BARREIRA e "sem permissão" — SEM mockup (design novo, da 3B)

**Verificado:** grep de "login"/"permiss"/"403"/"restrit" nos dois `.dc.html` **não retorna nada** — o protótipo só
renderiza o estado **com** acesso. A regra antiga do README single (`:34-35`, "403/redirect p/ login se restrita")
foi **substituída** pelo dono (modal inline). ⇒ **a barreira e o "sem permissão" são desenho novo** (§6.3), no tom das
copys de privacidade existentes e reusando o modal `assinar-modal` + o form de `auth/login.blade.php`.

---

## 5. Invariantes (cada um vira teste que reprova)

| # | Invariante | Teste (§9) |
|---|---|---|
| **I1** | **Swap + paridade anônima:** lista (`Mensagens\Lista`), single (`MensagemController@show`), `mesmoDia`/`relacionadas` e o select de autor partem de `Mensagem::publicado()->visiveisPara($user)` (não `publica()`). Para **anônimo**, o conjunto é **idêntico** ao da 2B (só Público publicado) — nenhuma pública some, nenhuma restrita aparece. | §9.2/§9.3 |
| **I2** | **`scopePublicado`:** existe `Mensagem::publicado(): Builder` = `status='publicado'` (status-only), e `publicado()->visiveisPara(null)` ≡ `publica()`. | §9.1 |
| **I3** | **Barreira — anônimo:** single **restrito publicado** + anônimo → **200** com a view de barreira (modal de login: form `POST /entrar`@csrf + link Google) — **nunca 404, nunca 403**; grava `url.intended = url()->current()`; **noindex**; `Cache-Control: private`. | §9.4 |
| **I4** | **Barreira — logado sem acesso:** single restrito + usuário logado que **não** pode ver → **200** "sem permissão" + contato (e-mail/WhatsApp); **noindex**; **cego**. | §9.4 |
| **I5** | **Autorizado vê:** single restrito + usuário que **pode** ver (nível/recorte/destinatário/bypass) → **200** com a mensagem completa (corpo, contexto, autor, download conforme negócio) + **badge de nível dinâmico** (não o "Pública" hardcoded); **noindex** (restrito). | §9.4 |
| **I6** | **404 real:** slug **inexistente** ou mensagem **não-publicada** (pendente/despublicada) → **404** (`publicado()->firstOrFail`), para qualquer persona. | §9.4 |
| **I7** | **Direcionada CEGA:** não-destinatário (anônimo **ou** logado, inclusive diretor) **nunca** recebe título, corpo nem destinatários de uma direcionada — só a barreira genérica; o **destinatário** vê a mensagem + a nota "direcionada a você" (**sem** lista de destinatários — F2). | §9.4/§9.5 |
| **I8** | **Corpo só após autorizar:** o HTML das telas de barreira/"sem permissão" **não contém** `corpo`, `titulo`, `contexto`, `og:image`, `ld+json` nem destinatários da mensagem-alvo (grep no HTML renderizado). | §9.4 |
| **I9** | **Badges só `@auth`:** badge de nível + cadeado + legenda de bolinhas + barra/faixa na cor do nível aparecem **só para logado**; **anônimo** vê o card/single **sem** badge algum (look 2B). | §9.2/§9.3 |
| **I10** | **Não-vazamento na lista:** para um logado, a lista mostra **exatamente** o conjunto de `visiveisPara($u)` (filtra no banco); título de restrita que ele **não** pode ver **nunca** volta na grade, no contador nem no select de autor. | §9.2 |
| **I11** | **`noindex` correto:** single restrito (autorizado) **e** telas de barreira emitem `<meta name="robots" content="noindex, nofollow">`; single **Público** **não** emite noindex; a lista/`autores.*` seguem indexáveis (anônimo = só Público). | §9.6 |
| **I12** | **Sitemap intacto:** `sitemap.xml` segue só o **Público** (mensagens `publica()`, autores ativos com ≥1 pública) — nenhuma restrita/barreira entra; nenhuma asserção de `MensagemSitemapTest`/`AutorSitemapTest` (2B) muda de cor. | §9.6 |
| **I13** | **Menu religado:** "Mensagens Mediúnicas" fica **ativo** (`rota=>'mensagens.index'`) com submenu **"Autores Espirituais"** (`autores.index`); item deixa de ser `<span>` cinza e ganha dropdown. | §9.7 |
| **I14** | **Null-guard (`nivel=null` publicado):** uma mensagem publicada com `nivel=null` (**há 2 no dev**) é vista **só** por admin/presidente (bypass `veTudo`); o gate de badge/selo/barra é **null-safe** (`visibilidade()?->ehRestrito()`) ⇒ lista **e** single **200 SEM badge/selo** (nunca `null->rotulo()`/`->cor()` = 500); o **`noindex` é emitido** (null tratado como restrito). | §9.4 |
| **I-chg** | **Mudanças intencionais (não-neutras):** o `MensagemShowTest` (2B) que assere **restrito=404** passa a asserir **restrito=barreira(200)**; o selo "Pública" hardcoded do single vira dinâmico. São mudanças **desta fatia** (§11/§12) — o resto da suíte 2A/2B/3A **não** muda de cor. Suíte **~1032 + novos**, verde; `Pint` verde. | §9.8 |

---

## 6. Decisões de desenho

### 6.1 A troca `publica()` → `publicado()->visiveisPara($user)` (com `scopePublicado` novo)

**Add** em [Mensagem](../../../app/Models/Mensagem.php) (molde do Evento; aditivo, não toca `publica()`):
```php
/** Só o status publicado (ortogonal ao nível) — para compor com visiveisPara(): publicado()->visiveisPara($u). */
public function scopePublicado(Builder $query): Builder
{
    return $query->where('status', self::STATUS_PUBLICADO);
}
```
**Trocar** (o `$user = Auth::user()` pode ser `null`):

| Arquivo:linha | De | Para |
|---|---|---|
| `MensagemController@show` [:22] | `Mensagem::query()->publica()` | `Mensagem::query()->publicado()->visiveisPara(Auth::user())` **+** barreira `podeSerVistoPor` (§6.3) |
| `MensagemController@show` relacionadas [:23] | `fn ($q) => $q->publica()` | `fn ($q) => $q->publicado()->visiveisPara(Auth::user())` |
| `MensagemController@show` mesmoDia [:30] | `Mensagem::query()->publica()` | `Mensagem::query()->publicado()->visiveisPara(Auth::user())` |
| `MensagemController@index` contador [:15] | `Mensagem::publica()->count()` | `Mensagem::publicado()->visiveisPara(Auth::user())->count()` + rótulo dinâmico (§6.4) |
| `Mensagens\Lista@render` [:84] | `Mensagem::query()->publica()` | `Mensagem::query()->publicado()->visiveisPara(Auth::user())` |
| `Mensagens\Lista` select autor [:100] | `whereHas('mensagens', fn ($q) => $q->publica())` | `whereHas('mensagens', fn ($q) => $q->publicado()->visiveisPara(Auth::user()))` |

**MANTER `publica()`** (institucional / SEO): `SitemapController` [:36,:42]; **todas** as superfícies de Autores
(`AutorEspiritualController@index` [:21,:22,:24,:28] e `@show` [:47]) — o card de autor consome o count literal
`mensagens_publicas_count` e a relação pré-filtrada; a página de Autores é um **diretório institucional público**
(§13-F1-menor). ⇒ **nenhuma edição** em `autor/card.blade.php` nem no `AutorEspiritualController`.

- **Ciência de N+1 (herdada da 3A):** `visiveisPara` chama `ehMedium/ehDiretorDepae/ehPresidente` **uma vez** (barato).
  A lista já faz `->with('autores')`. **Não** chamar `podeSerVistoPor` por item numa lista.

### 6.2 Quem vê o quê (a matriz, do resolvedor da 3A — só recapitulada)

Anônimo/frequentador → Público. Trabalhador (20) → Público+Trabalhadores. **Médium** (setor `medium`) → +Médiuns.
Diretor (30) → Público+Trabalhadores+Diretores (**não** Médiuns). Diretor-DEPAE (cargo) → +Diretor-DEPAE.
**Destinatário** → +a(s) direcionada(s) dele. **Presidente/admin** → **tudo** (bypass). `nivel=null` → fail-closed.
**A 3B não reimplementa nada disso** — chama `visiveisPara`/`podeSerVistoPor`.

### 6.3 A BARREIRA do single (o coração da 3B)

**`MensagemController@show` reescrito** (o gate roda **antes** de montar a view; a barreira é **view própria** — o
corpo nunca entra no HTML de quem não pode ver):
```php
public function show(string $slug): View|Response
{
    $usuario = Auth::user();
    // 404 real: inexistente OU não-publicada (status). Ainda NÃO filtra por nível.
    $mensagem = Mensagem::query()->publicado()->where('slug', $slug)->firstOrFail();

    if (! $mensagem->podeSerVistoPor($usuario)) {
        // Grava url.intended na sessão (efeito colateral) ANTES de qualquer login — sobrevive ao regenerate e ao
        // round-trip do Google. NÃO "corrigir" para `return redirect()->...`: o retorno é descartado DE PROPÓSITO (R4).
        redirect()->setIntendedUrl(url()->current());

        $modo = $usuario === null ? 'login' : 'sem-permissao';   // anônimo → modal; logado sem acesso → contato
        return response()
            ->view('mensagens.barreira', ['modo' => $modo])       // view CEGA: sem título/corpo/OG/destinatários
            ->header('Cache-Control', 'private, no-store');
    }

    // AUTORIZADO: carrega o resto por visiveisPara($usuario) (mesmoDia/relacionadas não vazam)
    $mensagem->load(['autores', 'relacionadas' => fn ($q) => $q->publicado()->visiveisPara($usuario), 'media']);
    $mesmoDia = /* ...->publicado()->visiveisPara($usuario)->whereDate(...)... */;
    $resposta = response()->view('mensagens.show', compact('mensagem', 'mesmoDia') + ['relacionadas' => $mensagem->relacionadas]);
    if ($mensagem->visibilidade() !== VisibilidadeMensagem::Publico) {
        $resposta->header('Cache-Control', 'private, no-store');   // restrito não é cacheável por proxy
    }
    return $resposta;
}
```

**A view de barreira `resources/views/mensagens/barreira.blade.php`** (`@props`/dados **genéricos** — nunca a
mensagem-alvo):
- `<x-layout.app :title="'Conteúdo restrito'" :description="...">` + **`<x-slot:head><meta name="robots"
  content="noindex, nofollow"></x-slot:head>`** (I11).
- **`$modo === 'login'`:** hero genérico ("Conteúdo restrito — esta mensagem é reservada. Entre para vê-la (se for
  para você).") + **modal de login inline** (molde `assinar-modal`): um `<form method="POST" action="{{ route('login')
  }}">@csrf` com `email`/`password`/`remember` (extrair o form de `auth/login.blade.php` para um **parcial**
  `x-auth.form-login` reusado pela tela cheia **e** pelo modal) + o link **"Entrar com Google"**
  `<a href="{{ route('google.redirect') }}">`. **Sem** campo `redirect` no form (o `intended` na sessão resolve os dois
  caminhos — e-mail/senha via `LoginResponse` padrão e Google via `callback`, ambos consomem `url.intended`). Link
  discreto "criar conta" → `route('register')` e "esqueci a senha" → reset (existentes).
  - **⚠️ R1 — reabrir o modal em erro de login:** o Fortify, em falha (senha errada / throttle 5/min), faz `back()`
    com `$errors` na sessão — a barreira **recarrega com o `<dialog>` FECHADO**. Abrir o modal **automaticamente**
    quando `$errors->any()` (`x-data`/`x-init` que faz `showModal()` se houver erro) e renderizar `@error('email')`
    dentro do form. Sem isso, "senha errada = nada acontece" visível ao usuário.
- **`$modo === 'sem-permissao'`:** card "Você não tem permissão para ver esta mensagem." + "Em caso de dúvida, entre
  em contato:" + e-mail (`Configuracao::valor('contato.email')`) + WhatsApp (`Configuracao::valor('contato.whatsapp')`)
  — **F3/B2** (canais **editáveis pelo `/admin`**, não em arquivo de config; a tela **degrada** mostrando só o que
  estiver preenchido). **Sem** botão de login (logar não ajuda).

**Cegueira (I7):** a barreira **não** distingue Direcionada de outro nível na cópia (genérica "conteúdo restrito");
**não** emite badge/rótulo do nível (para não revelar "é uma direcionada"). Revela **só** que existe algo restrito
naquele slug — a existência (nunca o conteúdo), que é o comportamento desejado do link circulado (§12 registra o
trade-off de enumeração).

**Canais de contato (F3/B2) — store editável pelo `/admin`, NÃO config file:** a tela "sem permissão" lê
`Configuracao::valor('contato.email')` / `Configuracao::valor('contato.whatsapp')` do store chave-valor **já
existente** [App\Models\Configuracao](../../../app/Models/Configuracao.php#L19-L25) (`valor()` [:19], `definir()`
[:25]). A edição é uma **Página Filament nova** `App\Filament\Pages\ConfiguracoesContato` no **molde** de
[ConfiguracoesBlog](../../../app/Filament/Pages/ConfiguracoesBlog.php) /
[ConfiguracoesAgenda](../../../app/Filament/Pages/ConfiguracoesAgenda.php) (o form/`content()` lê via
`Configuracao::valor`; salvar grava via `Configuracao::definir('contato.email', …)`). É o **único toque da 3B no
`/admin`** (alargamento consciente, molde pronto) ⇒ o dono muda os canais **sem deploy**. **NÃO** criar
`config/contato.php`, `config/cema.php` nem usar `.env`.

**Por que `setIntendedUrl` (opção c1) e não um `LoginResponse` custom (c2):** zero código de resposta novo; a URL é
**server-derived** (`url()->current()`), sem risco de open-redirect; sobrevive ao `regenerate()` e ao round-trip do
Google **sem** tocar o `GoogleController`. Alternativa (c2, `LoginResponse` honrando um hidden `redirect` validado) em
§13-F4 caso o "sangramento" da intended (logar pela home leva à última mensagem restrita aberta) incomode.

### 6.4 Badge de nível + cadeado + legenda (paleta AA — a entrega visual da 3B)

**Componente novo `x-mensagem.selo-nivel`** (molde do `selo-formato` — pílula + ponto/cadeado + rótulo AA):
`@props(['visibilidade'])` (o enum). Padrão AA = **fundo translúcido da cor + texto escurecido da cor** (como o
`selo-formato`), com **cadeado** (SVG 12×12) quando `visibilidade->ehRestrito()` (≠ Público). Rótulo = `rotulo()`.

**Paleta AA (proposta — o dono confirma em §13-O1):** a 3B **substitui** `VisibilidadeMensagem::cor()` pelos **hues
do designer** (usados no **ponto/bolinha/barra 4px/faixa 5px** — decorativos, ao lado de rótulo textual ⇒ isentos de
contraste) e **acrescenta** `corTexto(): string` (o texto AA do badge, ≥ 4.5:1 sobre o fundo translúcido claro):

| Nível | `cor()` (hue — ponto/barra/legenda) | fundo do badge (rgba do hue) | `corTexto()` (AA ≥4.5:1) |
|---|---|---|---|
| Público | `#6E9FCB` | `rgba(110,159,203,0.16)` | `#35618F` |
| Trabalhadores | `#A34E5C` | `rgba(163,78,92,0.14)` | `#8F3F4D` |
| Médiuns | `#5E8770` | `rgba(94,135,112,0.18)` | `#3F7256` |
| Diretores | `#3A4585` | `rgba(58,69,133,0.14)` | `#3A4585` |
| Diretor-DEPAE | `#7C4D8F` | `rgba(124,77,143,0.14)` | `#6A3E7C` |
| Direcionada | `#26242E` | `rgba(38,36,46,0.10)` | `#26242E` |

- **Validar cada par `corTexto()`×fundo com um verificador de contraste** na implementação (alvo AA 4.5:1 texto;
  os hues do ponto/barra são decorativos). Formalizar `#5E8770`/`#7C4D8F` (sem token `@theme`) no CSS de Mensagens.
- **Alternativa AA de menor risco (§13-O1):** reusar **`x-ui.selo-visibilidade`** (texto `text-text-ink` + ponto na
  `cor()`) + um `<span>` de cadeado — **já AA-garantido e já construído**; a cor aparece no ponto + barra + legenda.
  Recomendo o **soft-badge colorido** (tabela acima, fiel ao handoff **e** AA); o `selo-visibilidade` é o plano B.

**⚠️ B1 — NULL-SAFE (bloqueador):** há **2 mensagens publicadas com `nivel=null`** no dev; só admin/presidente as
veem (bypass `veTudo`, [Mensagem.php:117](../../../app/Models/Mensagem.php#L117)), e `visibilidade()` devolve **null**
([Mensagem.php:75-78](../../../app/Models/Mensagem.php#L75-L78)). O gate ingênuo `visibilidade() !== Publico` é **true**
para null ⇒ `null->rotulo()`/`->cor()` = **500** na lista **e** no single do admin (a suíte não pega porque nunca cria
null-publicado). ⇒ **o componente `x-mensagem.selo-nivel` é a fonte única do null-guard:** `@props(['visibilidade'])`
+ `@if ($visibilidade) …render… @endif` (null ⇒ **não renderiza nada**). Todo call-site passa `$mensagem->visibilidade()`
cru (o componente ignora null); a **barra/faixa** usa `$mensagem->visibilidade()?->cor()` (null ⇒ sem cor → faixa-marca
da 2B).

**⚠️ R3 — `ehRestrito()` ≠ `ehRecorte()`:** o enum **já** tem `ehRecorte()` (Médiuns/DEPAE/Direcionada = pertencimento,
[VisibilidadeMensagem.php:28](../../../app/Enums/VisibilidadeMensagem.php#L28)). O **novo** `ehRestrito(): bool` =
`$this !== self::Publico` (inclui **Trabalhadores/Diretores**) é **outro conceito** — o **cadeado** usa `ehRestrito()`,
**nunca** `ehRecorte()`.

**Onde e QUANDO (`@auth`; o null-guard vive no componente; Público logado TAMBÉM ganha badge — O3):**
- **Card grade** — no slot vazio de [card.blade.php:27-30]:
  `@auth <x-mensagem.selo-nivel :visibilidade="$mensagem->visibilidade()"/> @endauth`. O badge mostra rótulo/cor de
  **qualquer** nível conhecido (Público inclusive — ajuda na lista mista, O3); **null → nada** (guard do componente).
  Cadeado **dentro** do componente quando `ehRestrito()`. Barra 4px superior = `visibilidade()?->cor()` **só `@auth`**
  (null/anônimo → faixa-marca da 2B).
- **Linha (visão lista)** — badge na meta [linha.blade.php:14-19], mesmo componente; faixa lateral 5px `?->cor()` `@auth`.
- **Legenda de bolinhas** — parcial novo `x-mensagem.legenda-niveis` acima da grade (`resources/views/livewire/
  mensagens/lista.blade.php`, perto de [:69-71]), **só `@auth`**: "Nível de acesso:" + os níveis **presentes no
  resultado** (O3). **Sem** legenda lida/não-lida (F5).
- **Single (hero)** — trocar o selo hardcoded [show.blade.php:45-47] por `@auth <x-mensagem.selo-nivel
  :visibilidade="$mensagem->visibilidade()"/> @endauth` (o viewer aqui **já** é autorizado; um Público visto por
  anônimo mantém o hero limpo; um `nivel=null` visto pelo admin **não** renderiza selo — guard). `noindex` e a nota
  Direcionada são tratados à parte (§6.5/§6.7-nota).

### 6.5 `noindex` + SEO das restritas (sem tocar o sitemap)

- **Padrão da casa:** emitir `<meta name="robots" content="noindex, nofollow">` via **`<x-slot:head>`** (molde
  [blog/show.blade.php:64-65]). Aplicar em: `mensagens/barreira.blade.php` (**sempre**) e `mensagens/show.blade.php`
  **quando não-Público**: `@if ($mensagem->visibilidade() !== VisibilidadeMensagem::Publico)` — este gate **inclui
  `null`** (`null !== Publico` = true), e isso é **desejado** (B1: `nivel=null` é tratado como **restrito para
  noindex**, mesmo **sem** renderizar selo — o gate de noindex é `!== Publico`, distinto do gate de badge que é o
  null-safe do componente, §6.4). Só o Público (`=== Publico`) **não** emite noindex. Açúcar opcional (§13-O5): prop
  `:noindex` no `x-layout.app`.
- **⚠️ R2 — `Cache-Control: private` na lista/index logada (defesa em profundidade):** além do single restrito (§6.3),
  a `mensagens.index` e a grade Livewire variam por usuário (títulos de restritas, contador "disponíveis a você") ⇒
  emitir `Cache-Control: private` quando `Auth::check()` na `mensagens.index`. (O vetor exige CDN ignorando
  `Set-Cookie`; por isso **refinamento**, não bloqueador.)
- **OG/rich-SEO só para Público:** no `show.blade`, **suprimir** `og:image` (pictografia), `ld+json` e a
  `og:description` derivada do corpo **quando restrito** (o Público mantém tudo). Conteúdo restrito **não** deve ter
  preview social rico. Envolver [show.blade.php:3-18] em `@if (Público)`.
- **Sitemap intacto:** `SitemapController` segue `publica()` (mensagens) e autores-ativos-com-pública — **nada muda**
  (I12). Para Mensagem, `publica()` ≡ `publicado()->visiveisPara(null)`; manter `publica()` literal (menor diff).

### 6.6 Autores — mantém institucional (sem per-usuário)

`autores.index`/`autores.show` seguem `publica()` (grade, `mensagens_publicas_count`, pontinhos de formato, 3 tiles,
"em destaque", sitemap) — **diretório público institucional**, cacheável, SEO-limpo, sem variar por usuário. O rodapé
estático de login [autores/show.blade.php:169-173] **fica como está** (texto fixo + `route('login')`). A navegação
per-usuário do conteúdo do autor vive na **lista de mensagens filtrada por autor** (que já usa `visiveisPara`). **Não
tocar** `autor/card.blade.php` nem `AutorEspiritualController`. (§13-F1-menor registra a alternativa de tornar a grade
do **perfil** per-usuário.)

### 6.7 Nota "direcionada a você" (3B) · o modo "Minhas mensagens direcionadas" (3C, fora)

**FICA na 3B — a nota (§6.7-nota):** "Esta mensagem foi direcionada a você" no single da própria direcionada do
destinatário (card creme, molde do handoff `:265-281`, **SEM** a lista de destinatários — F2), condicionada a
`$mensagem->visibilidade() === Direcionada && $usuario && $mensagem->destinatarios()->whereKey($usuario->id)->exists()`
(reaproveita `podeSerVistoPor`, já resolvido). É o **único** traço de Direcionada no front autorizado da 3B.

**VAI à 3C (F1 FECHADO — fora desta fatia):** o **modo "minhas direcionadas"** na `Mensagens\Lista` (prop `#[Url]
$modo` que troca a base para `Auth::user()->mensagensDirecionadas()`, hero/countLabel "direcionadas a você", card
"Área pessoal", ocultando filtros/legenda). **Não** implementar aqui.

### 6.8 Religar o menu (`config/navegacao.php`)

Substituir a **linha 24** (espelhando Palestras [:15-23]):
```php
[
    'rotulo' => 'Mensagens Mediúnicas',
    'rota' => 'mensagens.index',
    'ativo' => true,
    'itens' => [
        ['rotulo' => 'Mensagens Públicas', 'rota' => 'mensagens.index', 'ativo' => true],
        ['rotulo' => 'Autores Espirituais', 'rota' => 'autores.index', 'ativo' => true],
    ],
],
```
**Nenhuma** mudança no `header.blade.php` (já renderiza pai+submenu pela config; o caret/dropdown surge sozinho).
Cache: `docker compose exec -T app php artisan config:clear` + `docker compose restart app worker` (OPcache dev,
[[dev-opcache-restart-app-worker]]). Rótulo do 1º subitem "Mensagens Públicas" por simetria com "Palestras Públicas"
(§13-O2 ajusta se o dono preferir outro).

### 6.9 A11y, responsivo, performance (guardrails herdados)

- **Mobile-first:** badges/legenda quebram em wrap; a legenda vira coluna no mobile; o modal de login ocupa a largura
  no mobile (o `<dialog>` full-width < `sm`).
- **A11y:** cadeado com `aria-label="acesso restrito"`; badge é texto (não só cor); ponto/barra `aria-hidden`; o modal
  de login com `role="dialog"`/`aria-modal`/foco preso/`Esc` (molde `assinar-modal`); contraste `text-text-ink`/
  `corTexto()` AA (§6.4). `prefers-reduced-motion` já herdado (partículas/envelope).
- **Performance/SEO:** SSR; **eager-load** (`->with('autores')`) mantido; `Cache-Control: private` só no restrito;
  o Público segue cacheável e indexável; **sem** N+1 (visiveisPara chama predicados 1×).

---

## 7. As peças (inventário)

**Novos (cabeçalho de autoria no PHP — CLAUDE.md §8):**
`resources/views/mensagens/barreira.blade.php` ·
`resources/views/components/mensagem/selo-nivel.blade.php` ·
`resources/views/components/mensagem/legenda-niveis.blade.php` ·
`resources/views/components/auth/form-login.blade.php` (parcial extraído de `auth/login.blade.php`, reusado pela tela
cheia **e** pelo modal) ·
`app/Filament/Pages/ConfiguracoesContato.php` (+ view se o molde usar — B2: edição de `contato.email`/`contato.whatsapp`
via `Configuracao`, molde `ConfiguracoesBlog`/`ConfiguracoesAgenda`) · testes (§9).

**Editados (aditivo/cirúrgico):**
`app/Models/Mensagem.php` (**+`scopePublicado`**) ·
`app/Enums/VisibilidadeMensagem.php` (`cor()` → hues reais + **`corTexto()`** + **`ehRestrito()`** helper) ·
`app/Http/Controllers/MensagemController.php` (`show` com barreira; `index` contador+rótulo) ·
`app/Livewire/Mensagens/Lista.php` (`render`+select por `publicado()->visiveisPara`; countLabel dinâmico) ·
`resources/views/mensagens/show.blade.php` (selo dinâmico; noindex+OG condicionais; nota "direcionada a você") ·
`resources/views/mensagens/index.blade.php` (rótulo dinâmico do contador) ·
`resources/views/livewire/mensagens/lista.blade.php` (legenda `@auth`; barra na cor do nível) ·
`resources/views/components/mensagem/card.blade.php` + `linha.blade.php` (badge no slot, `@auth`) ·
`resources/views/auth/login.blade.php` (passa a `@include`/usar o parcial `form-login`) ·
`config/navegacao.php` (religar o item + submenu) ·
`resources/css/mensagens.css` (badge/cadeado/legenda/barra).

**Toca o `/admin` (alargamento consciente — B2):** **1** Página Filament nova (`ConfiguracoesContato`, molde
`ConfiguracoesBlog`) — a única superfície de admin da 3B; **não** toca Resources, policies, matriz nem o resolvedor.

**NÃO toca:** o **resolvedor da 3A** (`visiveisPara`/`podeSerVistoPor`/`visibilidade`/enum-`rotulo`/predicados/pivô —
só consome; `cor()` é da 3B por design) · `scopePublica`/`casts`/`nivel` da Mensagem · **Autores** inteiro
(`AutorEspiritualController`, `autor/card`, sitemap de autor) · `Evento`/`EventoController`/`VisibilidadeEvento` ·
núcleo de capacidade (Policies de capacidade/Camada 1/MatrizCapacidades/Resources) · importação · `Palestras\Curtir` ·
o **modo "minhas direcionadas"** (3C).

---

## 8. Cutover (o que roda no deploy — do dono)

A 3B **não tem migration nem seeder** (é front; o pivô `mensagem_destinatario` já veio da 3A). Deploy padrão de front:
1. `git pull` (código) — sem novas dependências Composer.
2. `npm run build` (Vite, **no host** — o container não tem Node, [[npm-vite-no-host]]).
3. `php artisan optimize:clear` (route/view/**config**) + `docker compose restart app worker`
   ([[dev-opcache-restart-app-worker]]) — o `config:clear` é o que faz o **menu religado** aparecer.
4. **Pré-requisito de dados (já feito no cutover da 3A):** os **73 vínculos / 15 mensagens / 17 destinatários** do
   `cema:importar-direcionadas` **precisam** estar populados para a Direcionada funcionar no front. Em produção, rodar
   o cutover da 3A (`migrate` → `cema:importar-direcionadas`) **antes** de subir a 3B, se ainda não foi.
5. **F3/B2:** cadastrar o e-mail/WhatsApp reais da tela "sem permissão" pela **Página `/admin` → Configurações de
   Contato** (grava em `Configuracao`; **sem deploy** para trocar depois). A Página sobe com o código (passo 1).

**Ciência:** a partir da 3B, um **link restrito que circula em WhatsApp** deixa de dar 404 e passa a mostrar a
barreira — o logado-com-acesso vê; o resto é barrado **cegamente**. As pendentes/`nivel=null` seguem 404/invisíveis.

---

## 9. Plano de teste (TDD real, vermelho primeiro)

Feature tests de front usam `Tests\TestCase` + `RefreshDatabase`, `EstruturaCemaSeeder` (papéis/setores/cargos),
factories da 2A/3A (`Mensagem::factory()->publica()`/`->pendente()`/`->comNivel(...)`; `MensagemFactory::comNivel`
grava o slug bruto), `Storage::fake('public')` na pictografia. Personas via `assignRole` + `setores()->attach` +
`cargos()->attach` + `destinatarios()->attach` (molde `MensagemVisibilidadeAcessoTest` da 3A).

### 9.0 Ordenação (constraint)
`scopePublicado` (§6.1) e `VisibilidadeMensagem::corTexto()/ehRestrito()` (§6.4) **antes** dos controllers/views que os
consomem. Sequência: `scopePublicado`+enum → swap lista/single (I1/I10) → barreira (I3–I8) → badges `@auth` (I9) →
noindex/OG (I11) → menu (I13) → (3C-cond.) direcionadas → sitemap-guarda (I12) → regressão.

### 9.1 `MensagemScopePublicadoTest` (unidade/feature) — I2/R5
`publicado()` = só `status='publicado'` (pendente/despublicada fora); `publicado()->visiveisPara(null)->get()` **==**
`publica()->get()` (I2 — paridade anônima); um `comNivel('trabalhadores')` publicado **entra** em `publicado()` mas
**sai** de `publica()`. **R5:** incluir **1 publicada com `nivel=null`** e conferir que `publicado()->visiveisPara(null)`
a **exclui** (conjunto == `publica()`) — o null não vaza ao anônimo (fail-closed do scope).

### 9.2 `MensagemListaVisibilidadeTest` (Livewire, molde `Palestras\Lista`) — I1/I9/I10
Cria públicas + restritas de vários níveis + 1 direcionada. `Livewire::test(Lista::class)` como: **anônimo** (só
públicas na grade/contador/select — idêntico à 2B, **sem** badge no HTML); **trabalhador** (públicas+trabalhadores,
**não** médiuns/direcionada-de-outro; badge presente `@auth`); **médium** (+médiuns); **diretor** (+diretores, **não**
médiuns); **destinatário** (+a direcionada dele, **não** a de outro); **admin/presidente** (tudo). Assert: o `count()`
por persona é o de `visiveisPara`; `assertDontSee` do título de uma restrita que a persona **não** pode ver; o select
de autor só lista autores com ≥1 mensagem visível à persona.

### 9.3 `MensagemShowAutorizadoTest` (controller) — I1/I5/I9/I11
Público → 200 com corpo + (anônimo) **sem** badge / (logado) **com** badge dinâmico (`assertSee('Trabalhadores')` só
quando aplicável); **sem** noindex no Público; restrito-autorizado → 200 com corpo + badge + **noindex**
(`assertSee('name="robots"', false)`) + `Cache-Control: private`; `mesmoDia`/`relacionadas` só as visíveis à persona.
**Atualiza** o `MensagemShowTest` (2B): a asserção "restrito → 404" **muda** para "restrito → barreira/200" (I-chg).

### 9.4 `MensagemBarreiraTest` (controller — o núcleo) — I3/I4/I6/I7/I8
- **Anônimo** em restrita publicada → **200**, view de barreira, `assertSee` do form `action="/entrar"` + `@csrf` +
  link `route('google.redirect')`; **`assertDontSee($mensagem->titulo)`**, `assertDontSee($mensagem->corpo)`,
  `assertDontSee('og:image')`, `assertDontSee('application/ld+json')` (I8); `assertSee('name="robots"', false)`;
  a sessão tem `url.intended == url()->current()`.
- **Logado sem acesso** (frequentador em Diretores; diretor em Médiuns; não-destinatário em Direcionada) → **200**
  "sem permissão", `assertSee` contato, **cego** (I7 — `assertDontSee` título/corpo/destinatários), **sem** botão login.
- **Inexistente** e **pendente/despublicada** → **404** (I6, `assertNotFound`) para anônimo **e** logado.
- **Direcionada cega:** anônimo e não-destinatário → barreira genérica **sem** revelar "direcionada"/destinatários;
  destinatário → 200 com a mensagem + nota "direcionada a você" + **sem** lista de destinatários (I7/F2).
- **⚠️ I14 — null-guard (reproduz o 500):** mensagem publicada `nivel=null` vista por **admin** → **lista 200** sem
  badge (`assertOk` + `assertDontSee` do badge) **e** **single 200** sem selo; o single emite `noindex`. (Este teste
  **falha com 500** se o gate não for null-safe — §6.4.)
- **⚠️ R1 — modal reabre em erro:** POST de login inválido pela barreira → `assertSessionHasErrors('email')`; a
  barreira recarrega com o `<dialog>` marcado para abrir (assert do estado/atributo de abertura + `@error`).
- **⚠️ B2 — contato:** `Configuracao::definir('contato.email','x@cema')` → "sem permissão" `assertSee('x@cema')`;
  **sem** configurar → a tela **não quebra** (degrada, `assertOk`).

### 9.5 `MensagemNotaDirecionadaTest` (controller) — §6.7-nota/F2
Direcionada + **destinatário** → single **200** com a nota "direcionada a você"; **`assertDontSee`** de qualquer nome
de outro destinatário (F2 — **nenhuma** lista PII no HTML). Não-destinatário nunca chega aqui (barreira, §9.4). O
**modo "minhas direcionadas"** é **3C** — fora desta fatia (sem teste aqui).

### 9.6 `MensagemNoindexSitemapTest` — I11/I12
Restrito-autorizado + barreira emitem `noindex, nofollow`; Público **não**; `mensagens.index`/`autores.*` **não**
emitem noindex (anônimo). `sitemap.xml` **inalterado** (só Público) — reusar/confirmar `MensagemSitemapTest`/
`AutorSitemapTest` (2B) **verdes** (nenhuma restrita/barreira entra).

### 9.7 `MenuMensagensTest` — I13
`config('navegacao.menu')` tem o item Mensagens com `ativo=true`, `rota='mensagens.index'` e subitem
`autores.index`; `get('/')` (ou a home) `assertSee(route('mensagens.index'), false)` **e** `route('autores.index')`;
o item deixa de ser `aria-disabled`.

### 9.8 Regressão + neutralidade + suíte
Baseline **~1032** (`--list-tests`); alvo **~1032 + novos**, verde. **Mudanças intencionais (I-chg):** o
`MensagemShowTest` (2B) muda a asserção restrito=404→barreira; o teste do selo "Pública" hardcoded (se houver) vira
dinâmico — **atualizar esses**, não os demais. **Conferir no localhost:** `npm run build` + `restart app worker` +
navegar as 4 personas (anônimo/trabalhador/destinatário/diretor) na lista e no single, testar o modal de login E o
retorno do Google (dev usa Mailpit; OAuth exige credencial — se indisponível no dev, testar só o e-mail/senha e
registrar). **Verificação visual** contra os screenshots (badge/cadeado/legenda). `Pint` verde ([[pint-antes-de-push]],
[[flaky-importadorblog-gd-cap-imagem]]).

---

## 10. Fora de escopo (3C/F4/F5 — não fazer agora)

- **3C (F1 FECHADO):** o **modo "minhas direcionadas"** (aba de navegação das Direcionadas na lista) + contador
  "direcionadas a você" + card "Área pessoal". A **barreira do single de direcionada**, a **leitura da própria
  direcionada** e a **nota "direcionada a você"** **ficam na 3B**.
- **F4 (curadoria):** médium **cria** mensagem, diretor-DEPAE ratifica/publica, máquina de estados, porta `perfil`,
  campo **destinatários no `/admin`** (a lista PII vive lá, não no front — F2). A 3B é **só quem vê**, no front.
- **F5 (engajamento):** favoritar, **lida/não-lida** (pivô `mensagem_lidas` — **não** criar), "vistas recentemente",
  curtir do autor. O handoff mostra isso; a 3B **corta** tudo.
- **Dark mode:** site é só claro.

---

## 11. Fronteiras: o que toca × o que NÃO toca

**Toca (novo):** view de barreira + `x-mensagem.selo-nivel`/`legenda-niveis` + parcial `x-auth.form-login` + testes.
**Toca (novo no `/admin` — B2):** 1 Página Filament `ConfiguracoesContato` (molde `ConfiguracoesBlog`) + leitura de
`Configuracao::valor('contato.*')` na tela de barreira.
**Toca (edição):** `Mensagem` (+`scopePublicado`) · `VisibilidadeMensagem` (`cor()`+`corTexto()`+`ehRestrito()`) ·
`MensagemController` (barreira + contador + `Cache-Control`) · `Mensagens\Lista` (swap + label) · views/CSS de Mensagens
(badge/legenda/noindex/OG/selo dinâmico/nota direcionada) · `auth/login.blade.php` (usa o parcial) · `config/navegacao.php`.
**NÃO toca:** resolvedor da 3A · `scopePublica`/`nivel`/`casts` · **Autores inteiro** · Eventos · núcleo de capacidade
(Resources/policies/matriz) · importação · `Palestras\Curtir` · sitemap (mantém `publica()`) · o modo "minhas
direcionadas" (3C).
**Mudança de comportamento intencional (I-chg — não é regressão):** single **restrito publicado** deixa de dar 404 e
passa a barreira/200; o selo do single vira dinâmico. Só os testes 2B que fixavam esses dois pontos são atualizados.

---

## 12. Ciências (não são tarefa desta fatia)

- **A barreira REVELA a existência de um restrito** (200 com "conteúdo restrito") vs. o **404 de Eventos** (esconde
  tudo). É **decisão do dono** (o link de WhatsApp aponta a uma mensagem real; a barreira é a UX desejada). Trade-off:
  um atacante poderia distinguir "restrito existe" (barreira) de "não existe" (404) e **enumerar slugs** — mitigado por
  slugs não-adivinháveis (derivados do título) e por a barreira **nunca** revelar conteúdo/título. Registrado para o
  passe; se o dono preferir, um subconjunto (ex.: Direcionada) pode voltar a 404 (§13-F5).
- **`corpo` só ao HTML após autorizar** é garantido pela **view separada** (não CSS/`@if` no `show.blade`), imune a
  "ver fonte".
- **`Cache-Control: private, no-store`** no restrito impede cache por proxy/CDN de conteúdo por-usuário (molde Eventos).
- **O selo "Pública" do single era hardcoded** (a 2B só servia pública); a 3B o torna dinâmico — some para anônimo,
  aparece para logado. Não é regressão.
- **O contador do hero muda de rótulo por persona** ("públicas" vs "disponíveis a você") — é o comportamento do handoff.
- **Autores fica institucional** (públicas): um logado que queira as restritas de um autor usa a **lista filtrada por
  autor** (per-usuário). Decisão consciente (§6.6/§13-F1-menor).
- **`mensagem_lidas`/favoritos são F5** e **não existem** — o handoff os mostra; ignorar.

---

## 13. Passe adversarial próprio (20/jul) — achados e FORKS para o dono

> **Passe interno rodado antes da entrega:** 6 leitores paralelos mapearam o front 2B, a stack de login (Fortify
> headless + Socialite), o molde de Eventos (front-rico + visibilidade), layout/noindex/menu, componentes/enum e os
> handoffs — todos com **evidência `arquivo:linha`**; o resolvedor da 3A e o `MensagemController`/`Mensagem` foram
> **relidos direto**. As divergências handoff↔modelo abaixo já estão incorporadas.

**Correções que ESTE spec já incorpora (o handoff/protótipo diverge do real):**

- **C-A — a barreira do handoff é "403/redirect p/ login"; o dono trocou por MODAL inline (200).** O protótipo **não**
  tem mockup de barreira/login/403 (grep vazio) ⇒ desenho **novo** (§4.6/§6.3).
- **C-B — Eventos barra por 404, não por página-200-com-modal.** A 3B diverge por decisão do dono: 200+barreira cega +
  noindex explícito + corpo só após autorizar (§3.4/§6.3). O 404 fica só para inexistente/não-publicada.
- **C-C — pílula sólida branca do handoff REPROVA AA** em 4/6 cores ⇒ a 3B usa **soft-badge** (fundo translúcido +
  texto escurecido, molde `selo-formato`) com paleta AA própria (§6.4). A `cor()` da 3A é placeholder por design.
- **C-D — não existe `scopePublicado` na Mensagem** (só `publica()`); a 3B o cria (status-only) para compor com
  `visiveisPara` (que filtra só o nível). Sem isso, `visiveisPara(null)` incluiria pendentes público (§3.1/§6.1).
- **C-E — `mensagem_lidas` (lida/não-lida) e favoritar são F5 e NÃO existem.** O pivô real da 3A é
  `mensagem_destinatario` (singular). O handoff cita `mensagem_destinatarios`/`mensagem_lidas` — **não** criar (§4.2).
- **C-F — nada popula `url.intended`** na página de mensagem restrita (ela é 200 sem `auth`) ⇒ a 3B grava
  `setIntendedUrl(url()->current())` no GET da barreira; sobrevive ao `regenerate` e ao round-trip do Google (§3.3/§6.3).
- **C-G — sem `<meta name="csrf-token">` global** ⇒ login por `<form>@csrf`, nunca `fetch` (§3.3/§6.3).
- **C-H — Autores consome `mensagens_publicas_count` literal + relação pré-filtrada** ⇒ manter `publica()` lá evita
  quebrar o card (§3.2/§6.6).

**FORKS — propostos no passe interno; TODOS resolvidos no passe do Consultor (ver o bloco final). Recomendações
abaixo, já confirmadas pelo dono (F1/F2/F5) ou fechadas pelo Consultor (F3→B2, F4=c1, O2):**

1. **F1 — SPLIT 3B / 3C.** **Recomendo SPLIT:** 3B = visibilidade rica (badges/legenda/cadeado) + **barreira de login
   inline** (modal + 3 desfechos, **cega**, aplicável ao single de **qualquer** restrito **inclusive Direcionada**) +
   `noindex` + religar menu + o swap. 3C = a superfície de **navegação "Minhas mensagens direcionadas"** (aba/modo na
   lista + card "Área pessoal"). Racional: a 3B já carrega front + auth(modal) + a barreira; o **vazamento que o dono
   teme (link de direcionada em WhatsApp) já é resolvido na 3B** (§0/§6.7); o modo é aditivo. **Se rejeitado**, o modo
   entra na 3B (§6.7 dá o caminho). **Confirmar.**
   - **F1-menor — Autores:** **Recomendo MANTER `publica()`** em `autores.index`/`autores.show` (diretório
     institucional público; não tocar o card). Alternativa: tornar a **grade do perfil** (`autores.show:47`)
     per-usuário (`visiveisPara`), mantendo os counts públicos. **Confirmar** (recomendo manter).
2. **F2 — exibir a lista de DESTINATÁRIOS (PII) no front?** **Recomendo NÃO** exibir a ninguém (nem ao presidente/
   admin); o destinatário vê só "direcionada a você". A curadoria/lista de destinatários é **/admin (F4)**. **Confirmar.**
3. **F3 — canais de contato** (e-mail + WhatsApp) da tela "sem permissão" — **o dono informa** (a página Contato ainda
   não está no ar). **→ FECHADO por B2:** a 3B lê de `App\Models\Configuracao` (`contato.email`/`contato.whatsapp`),
   **editável pelo `/admin`** (não config file); degrada mostrando só o preenchido. **Dono cadastra os canais.**
4. **F4 — mecanismo do retorno pós-login:** **Recomendo (c1)** `setIntendedUrl(url()->current())` no GET da barreira
   (zero código de resposta; URL server-derived; cobre e-mail/senha **e** Google). Alternativa (c2): `LoginResponse`
   custom honrando um hidden `redirect` **validado** — só se o "sangramento" da intended (logar pela home levar à
   última restrita aberta) incomodar. **Confirmar** (recomendo c1).
5. **F5 — a barreira revela existência (200) vs 404 seco.** **Recomendo a barreira-200** para todos os restritos
   (é o pedido do dono — link circulado). Alternativa conservadora: **Direcionada** volta a **404** (nem barreira),
   mantendo barreira-200 só para níveis de escada — reduz enumeração de direcionadas. **Confirmar** (recomendo
   barreira-200 uniforme; a cegueira já protege o conteúdo).

**Pontos ABERTOS (menores) — recomendação + confirmar:**

- **O1 — paleta AA das badges:** **Recomendo o soft-badge** (tabela §6.4, fiel ao handoff **e** AA), validando cada
  par com verificador de contraste; plano B = reusar `x-ui.selo-visibilidade` (ink + ponto). **Confirmar a paleta.**
- **O2 — rótulos/nomes:** o `VisibilidadeMensagem::rotulo()` diz "**Diretor do DEPAE**"; o handoff usa "**Diretor-DEPAE**"
  na UI. **Recomendo** usar o `rotulo()` da 3A (fonte única) — se o dono quiser "Diretor-DEPAE" na badge, ajusto o
  `rotulo()` (afeta 3A). Idem o subitem de menu "Mensagens Públicas". **Confirmar** (micro).
- **O3 — badge para Público logado + escopo da legenda:** **Recomendo** mostrar o badge "Pública" também ao logado
  (lista mista) — diferente de Eventos (que oculta Público); e a legenda listar **os níveis presentes no resultado**
  (não os 5 fixos). **Confirmar** (micro).
- **O4 — nota "direcionada a você" no single:** **Recomendo** exibi-la (card creme, sem PII) ao destinatário; é o
  único traço de Direcionada no front autorizado. **Confirmar.**
- **O5 — noindex via slot vs prop:** **Recomendo** o `<x-slot:head>` (padrão da casa: blog/agenda); prop `:noindex` no
  layout é opcional. `content="noindex, nofollow"` (o dono pediu "noindex"; os precedentes usam só "noindex" — uso
  "noindex, nofollow" nas restritas por serem privadas). **Confirmar** (micro).
- **Regra sempre:** pt-BR em tudo; cabeçalho de autoria no PHP novo; `Pint` antes do push; `docker compose exec -T app
  php artisan test`; `npm run build` **no host**; **todo brief de subagente que rode `artisan` DEVE proibir
  `migrate:fresh/refresh/wipe/reset` e seed destrutivo** e reafirmar `legado` read-only ([[nunca-migrate-fresh-no-dev]]).

---

### Passe adversarial do CONSULTOR (20/jul) — veredito: ✅ APROVADA após 2 bloqueadores (B1/B2) + refinamentos

O Consultor verificou a SPEC contra o código real (base `0fa26c4`): o terreno **bate** (paridade I2 exata;
barreira/Fortify/rotas conferem; `cor()` sem consumidor hoje); as contagens **batem com o dev vivo**
(179 total / 132 publicadas / 15 direcionadas / 73 vínculos). **Forks fechados pelo dono.**

**Forks FECHADOS (incorporados ao spec):**
- **F1 = SPLIT 3B/3C.** 3B = badges+cadeado+legenda + **barreira de login inline** (cega, cobre o single de uma
  Direcionada) + `noindex` + religar menu + o swap + a **nota "direcionada a você"** no single do destinatário. **3C
  (fora):** o modo/aba "Minhas mensagens direcionadas". ⇒ **removidos** da 3B §4.5-modo, §6.7-modo, o teste do modo, e
  o invariante do modo (**I14 reaproveitado** para o null-guard). Mantidas a barreira cega e a nota.
- **F2 = NÃO exibir destinatários (PII)** a ninguém no front (nem admin/presidente); destinatário vê só "direcionada a
  você"; a lista PII fica no `/admin` (F4).
- **F5 = BARREIRA-200 cega uniforme** para **todo** restrito (inclusive Direcionada); **404 só** para inexistente ou
  não-publicada.

**OBRIGATÓRIOS (bloqueadores) — INCORPORADOS:**
- **B1 — NULL-GUARD nos badges/selo/barra.** Medido: **há 2 mensagens publicadas com `nivel=null`** no dev; admin/
  presidente as veem (bypass `veTudo`, [Mensagem.php:117](../../../app/Models/Mensagem.php#L117)), `visibilidade()` =
  null ([:75-78](../../../app/Models/Mensagem.php#L75-L78)) ⇒ o gate `!== Publico` renderiza selo com null ⇒
  `null->rotulo()`/`->cor()` = **500** na lista **e** no single do admin, **sem** a suíte pegar (nunca cria null-publicado).
  Corrigido: o **componente `x-mensagem.selo-nivel` é o null-guard** (`@if ($visibilidade)`), a barra usa `?->cor()`, e o
  `noindex` trata null como restrito (gate `!== Publico`, §6.5, **desejado**). **Teste novo (I14):** null-publicado
  visto por admin → lista/single **200 sem selo** (reproduz o 500). (§6.4/§9.4.)
- **B2 — canais de contato EDITÁVEIS no `/admin`**, não em config file (o dono muda sem deploy). Fonte =
  `App\Models\Configuracao` (`valor()` [:19] / `definir()` [:25], **verificados**) + **1 Página Filament
  `ConfiguracoesContato`** (molde `ConfiguracoesBlog`/`ConfiguracoesAgenda`, **verificados**). É o **único toque da 3B
  no `/admin`**. Removido `config/contato.php` do inventário. (§2-13/§6.3/§7/§8/§11.)

**REFINAMENTOS — INCORPORADOS:**
- **R1** — o modal de login **reabre em erro** (`$errors->any()` → `showModal()` + `@error`); senão "senha errada =
  nada acontece" (o Fortify faz `back()` com o modal fechado). (§6.3/§9.4.)
- **R2** — `Cache-Control: private` também na `mensagens.index` logada (defesa em profundidade; vetor exige CDN
  ignorando `Set-Cookie`). (§6.5.)
- **R3** — `ehRestrito()` (novo, `!= Publico`, inclui Trabalhadores/Diretores) **≠** `ehRecorte()` (existente,
  pertencimento, [VisibilidadeMensagem.php:28](../../../app/Enums/VisibilidadeMensagem.php#L28)); o cadeado usa
  `ehRestrito()`. (§6.4.)
- **R4** — `setIntendedUrl` solto (retorno descartado **de propósito**) **confirmado** correto (Laravel 13;
  `LoginResponse`/`GoogleController` consomem `intended`); comentário anti-"correção" no código. (§6.3.)
- **R5** — o teste de paridade I2 inclui **1 publicada `nivel=null`** e confere que `visiveisPara(null)` a **exclui**.
  (§9.1.)

**Menores ENDOSSADOS:** **O1** soft-badge (validar cada par `corTexto`×fundo com verificador de contraste; plano B =
`x-ui.selo-visibilidade`). **O2 FECHADO:** usar o `rotulo()` da 3A ("Diretor do DEPAE") — **não mexer no `rotulo()` do
enum** (só `cor()`+`corTexto()`+`ehRestrito()` mudam). **O3** badge "Público" também ao logado (lista mista) + legenda
lista os níveis **presentes**. **O5** `noindex` via `<x-slot:head>` (padrão blog), `"noindex, nofollow"`.

**Veredito:** **segue para o PLANO** (molde das fatias anteriores, TDD real; ordem do §9.0: `scopePublicado`+enum →
swap → barreira → badges `@auth` → noindex/OG → menu → Página de contato → sitemap-guarda → regressão). O Consultor
fará o passe do plano antes da execução. **Sem migration nesta fatia.**

---
