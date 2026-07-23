# Spec — Camada 4 · Fatia F4c-B · Autores: visibilidade rica + perfil (Sobre + fallback de foto)

- **Base:** `origin/main` = `8b2c03f` (F4c-D mesclada pelo PR #45).
- **Branch:** `camada-4-fatia-f4c-b-autores`.
- **Formato:** 1 PR, 2 blocos de tasks separadas (B1 visibilidade · B2 perfil) que tocam o mesmo terreno.
- **Data:** 2026-07-23.
- **Autor:** Thiago Mourão — https://github.com/MouraoBSB

---

## 0. Recorte: o que esta fatia fecha

A superfície de **Autores Espirituais** (lista `/autores-espirituais` + perfil
`/autores-espirituais/{slug}`) é o **último lugar do módulo Mensagens** ainda preso ao filtro
público fixo `publica()`. A 3A/3B deram visibilidade rica à lista e ao single de Mensagens; os
Autores ficaram para trás. Dois blocos:

**Bloco B1 — visibilidade rica (núcleo, PII-sensível).** A lista e o perfil passam a mostrar
**o que cada usuário pode ver** (`publicado()->visiveisPara($usuario)`), não só o público.
Isso torna a grade, os contadores, os selos, a sidebar e o rodapé **viewer-aware**. Como é
por-usuário, entra `Cache-Control: private, no-store` no logado (molde do `MensagemController`
da 3B) e um **rodapé condicional anti-PII**.

**Bloco B2 — perfil.** Um bloco **"Sobre {nome}"** no corpo (hoje a bio só vive no `<head>`) e
uma **imagem de fallback** para autor sem foto (hoje: gradiente + iniciais).

**Fica de fora:** busca/ordenação na lista de autores (F3), engajamento (curtir), curadoria,
descoberta, e a dívida do molde Filament-no-site. Ver §10.

---

## 1. Contexto e objetivo

O anônimo já vê, hoje, exatamente as mensagens públicas — e vai **continuar vendo o mesmo**
(prova em §3.1: `publicado()->visiveisPara(null)` devolve o **mesmo conjunto** que `publica()`).
A fatia não muda nada para o crawler nem para o visitante deslogado. O que ela acrescenta é a
**camada logada**: o trabalhador, o diretor, o médium e o DEPAE passam a encontrar, na lista e
no perfil, as mensagens que a 3A/3B já lhes autoriza — e a página deixa de **mentir** ("Somente
mensagens públicas", "Mensagens públicas") para quem vê mais que isso.

Objetivo declarado, uma frase por bloco:

- **B1** — que a lista e o perfil mostrem e **contem** o que o usuário logado pode ver, com o
  mesmo escopo em todas as contagens da tela, sem vazar por cache nem por PII, e com um rodapé
  que só aparece quando há de fato conteúdo restrito **oculto para aquele usuário**.
- **B2** — que o perfil exponha a **bio** num bloco de corpo quando houver, e que o autor sem
  retrato apareça com uma **imagem simbólica intencional**, não com iniciais.

Nenhum bloco dá capacidade nova a ninguém: a regra de quem-vê-o-quê é a `visiveisPara` que já
existe (3A). A fatia é **consumo** dessa regra numa superfície que ainda não a consumia.

---

## 2. Decisões travadas (não reabrir)

| # | Decisão | Origem |
|---|---|---|
| **D1** | Critério da lista **e** do perfil: "tem mensagem que **você** pode ver" — `publicado()->visiveisPara($usuario)`, não `publica()` fixo. A grade da lista **também** vira viewer-aware (não só o perfil), para não haver autor visível no perfil e ausente na grade. | Dono, kickoff |
| **D2** | Autor sem foto → **imagem de fallback** (`autor-fallback.svg`). **Diverge** do handoff-base (gradiente + iniciais); a entrega mais nova `entrega_autor_fallback/` já implementa isso e vence. Vai **declarado** no PR. | Dono, kickoff (23/jul) |
| **D3** | `bio` e `chamada` vazias são **toleradas**. O bloco/elemento só renderiza quando há conteúdo (`filled()`). O dono **não** vai preencher agora (medido: 6/19 sem bio; 19/19 sem chamada). | Dono, kickoff |
| **D4** | **2 guardrails de PII, inegociáveis:** (O1) rodapé condicional sem vazar contagem de restritas nem direcionadas de terceiros; (O2) sitemap e meta continuam no critério **público**. | Dono, kickoff |
| **F1** | Corte: **1 PR, 2 blocos** de tasks separadas (mesmo terreno; B2 mexe nas views que B1 tocou). | Dono, arranque |
| **F2** | Rodapé condicional em **dois estados, sem número**: `@guest` com link de login; `@auth` (logado que não vê tudo) sem link; **some** para quem já vê tudo. Texto exato em §5.2. | Dono, arranque |
| **F3** | Busca/ordenação **na lista de autores** fica **fora** desta fatia. (O filtro/ordenação client-side das *mensagens* no perfil já existe e não é tocado.) | Dono, arranque |
| **A1** | **Rótulo "públicas" trocado de forma consistente:** 4 superfícies visíveis + o comentário-contrato do card + o nome interno do card (e correlatos). Interno vira *viewer-aware* (`…_visiveis_…`); rótulo visível fica **condicional** (anônimo "públicas" / logado neutro). Inventário em §5.3. | Dono, adendo do arranque |
| **A2** | **Rodapé tem teste para o anônimo:** anônimo em autor só-público → sem rodapé; anônimo em autor com restrita hierárquica → rodapé `@guest` com link. | Dono, adendo do arranque |
| **A3** | Sem `noindex` no perfil: o crawler é anônimo e vê a **versão pública canônica** (a proteção do logado é o `private, no-store`). Rótulo logado neutro = "Mensagens" (dropa "públicas"). `whereNotNull('nivel')` mantém as `nivel=null` **fora** do cálculo do rodapé. | Dono, aprovação do design |

---

## 3. Terreno confirmado por medição

Nada nesta seção é presumido. O **dev** foi medido por consulta somente-leitura em 2026-07-23
(`tinker --execute` no container `cema-app`); o **código**, por leitura de `origin/main` =
`8b2c03f`; o **handoff**, por leitura dos dois pacotes de design.

### 3.1 Dev — os números que a fatia move

⚠️ O dev é vivo; os números abaixo são de **2026-07-23** e devem ser **reconfirmados no arranque
da execução** antes de virarem número em teste. Os testes automatizados usam **factories**, não
o dev — os números do dev servem à conferência E2E do §7, não às asserções.

| Fato | Valor |
|---|---|
| Autores ativos (`::ativo()`) | **19** |
| Sem `bio` (`blank(strip_tags(bio))`) | **6** — Irmão Paulo, Irmã Rosa, Irmã Marta, Pai Joaquim, Maurício, Irmã Celina |
| Sem `chamada` (todas) | **19/19** — a coluna está 100% despovoada |
| Sem foto (`foto_url === null`) | **5** — Abílio, Pai Joaquim, Maurício, Irmã Celina, Marco Prisco |
| Grade **hoje** (`whereHas` `publica()`) | **5** autores |
| Anônimo (`publicado()->visiveisPara(null)`) | **5** — **igual** ao `publica()` |
| Thiago (user **105**, papel `diretor`, `ehMedium=true`, `nivelMaximo=30`) | **16** autores |
| `Mensagem::publica()->count()` (topo do index) | **31** |
| Mensagens `status=publicado` **e** `nivel IS NULL` | **2** |

**A leitura-chave:** `anônimo == grade de hoje == 5`. Como `publicado()->visiveisPara(null)`
filtra só `nivel='publico'` ([Mensagem.php:139](app/Models/Mensagem.php#L139)) — o **mesmo**
conjunto de `publica()` ([Mensagem.php:73-78](app/Models/Mensagem.php#L73-L78)) — **a página do
anônimo é byte-idêntica à 2B**. A visibilidade rica só muda a experiência do **logado** (Thiago:
16 vs 5). É o que sustenta o invariante I1 e o guardrail O2.

### 3.2 Os scopes de Mensagem — a máquina que a fatia liga

| Scope / método | Definição | Efeito para esta fatia |
|---|---|---|
| `scopePublica` [:73-78](app/Models/Mensagem.php#L73-L78) | `status='publicado' AND nivel='publico'` (FIXO) | é o que a superfície de autor usa **hoje**; sai |
| `scopePublicado` [:81-84](app/Models/Mensagem.php#L81-L84) | `status='publicado'` (ortogonal ao nível) | compõe: `publicado()->visiveisPara($u)` |
| `scopeVisiveisPara` [:130-160](app/Models/Mensagem.php#L130-L160) | bypass p/ admin+presidente (**inclui `nivel=null`**, [:132-134](app/Models/Mensagem.php#L132-L134)); senão `OR` só de níveis **conhecidos** ([:139-157](app/Models/Mensagem.php#L139-L157)) | é o novo escopo; **`nivel=null` nunca casa** nos `orWhere` ⇒ excluído para não-bypass |
| `visibilidade()` [:90-93](app/Models/Mensagem.php#L90-L93) | `tryFrom(nivel)` — **null** para null **e** para slug desconhecido (fail-closed) | o selo depende disto; ver R2 |
| `veTudo()` [:96-99](app/Models/Mensagem.php#L96-L99) | admin (`hasRole('administrador')`) **ou** `ehPresidente()` | quem some do rodapé (O1) |

Consequência do bypass (as **2** `nivel=null` do dev): para **admin/presidente**, a grade e as
contagens do perfil passam a **incluir** as 2 mensagens `nivel=null` que hoje `publica()` exclui.
É quirk de **dado** pré-existente (as mensagens que "travam edição até receberem nível", herança
da F4c-C), não regressão; o selo já tem null-guard (§3.5) e a página não quebra. **Declarado**
como R2 e no PR.

### 3.3 Controller — os pontos que mudam (T1/T2 do kickoff, corrigidos)

[AutorEspiritualController.php](app/Http/Controllers/AutorEspiritualController.php). Hoje
`index(): View` ([:15](app/Http/Controllers/AutorEspiritualController.php#L15)) e
`show(string $slug): View` ([:39](app/Http/Controllers/AutorEspiritualController.php#L39)) **não
recebem `Request`** e **retornam `View`** (não `Response`).

**`@index` tem QUATRO usos de `publica()`, não três** (o kickoff citou 3 — faltou o eager-load):

| Linha | Uso | Vira |
|---|---|---|
| [:21](app/Http/Controllers/AutorEspiritualController.php#L21) | `whereHas('mensagens', publica())` — filtra a grade | `visiveisPara($usuario)` |
| [:22](app/Http/Controllers/AutorEspiritualController.php#L22) | `withCount([... as mensagens_publicas_count => publica()])` — contador do card | `visiveisPara` + **renomear** o alias (A1) |
| [:24](app/Http/Controllers/AutorEspiritualController.php#L24) | `with(['mensagens' => publica()])` — eager dos **pontinhos de formato** | `visiveisPara` |
| [:28](app/Http/Controllers/AutorEspiritualController.php#L28) | `Mensagem::publica()->count()` — mini-stat do topo | `Mensagem::publicado()->visiveisPara($usuario)->count()` |

Os quatro **têm de mudar juntos, no mesmo escopo** (O3): o `withCount` alimenta o número do
card; o `with` alimenta os pontinhos; se um ficar `publica()` e outro virar `visiveisPara`, o
card mostra "5 mensagens" com 6 pontinhos. O `$destaque`
([:29](app/Http/Controllers/AutorEspiritualController.php#L29)) e `$totalAutores`/`$totalMensagensPublicas`
([:33-34](app/Http/Controllers/AutorEspiritualController.php#L33-L34)) derivam desses — variam
por usuário de graça.

**`@show`:** `$publicas` ([:47-49](app/Http/Controllers/AutorEspiritualController.php#L47-L49))
é a **fonte única** — `$resumo` ([:51](app/Http/Controllers/AutorEspiritualController.php#L51)),
`$itensFiltro` ([:54-59](app/Http/Controllers/AutorEspiritualController.php#L54-L59)),
`$destaque` ([:65](app/Http/Controllers/AutorEspiritualController.php#L65)) e a grade da view
**todos** derivam dela. Trocar a fonte em **um** ponto propaga. O `firstOrFail` por slug
([:43](app/Http/Controllers/AutorEspiritualController.php#L43)) **não muda**: o autor não é
restrito; as mensagens dele é que são (O5a — ativo sem visível segue 200, grade vazia).

### 3.4 Views — inventário fechado

**Perfil** [autores/show.blade.php](resources/views/autores/show.blade.php):

| Onde | O quê | Ação |
|---|---|---|
| [:17](resources/views/autores/show.blade.php#L17) | tile `'Mensagens públicas'` (`$resumo->total()`) | rótulo **condicional** (A1) |
| [:22-23](resources/views/autores/show.blade.php#L22-L23) | `:description` do layout = `chamada ?: bio` (**não** mensagens) | **não muda** (O2) |
| [:31-40](resources/views/autores/show.blade.php#L31-L40) | JSON-LD `image`/`og:image` = `foto_url`; description = `bio ?: chamada` | **não muda** (O2) |
| [:60-69](resources/views/autores/show.blade.php#L60-L69) | hero: `@if(foto_url)<img>@else<span cema-grad+iniciais>` | `@else` vira **fallback SVG** (B2) |
| [:108-116](resources/views/autores/show.blade.php#L108-L116) | 3 tiles | rótulo do tile 1 condicional |
| [:126](resources/views/autores/show.blade.php#L126) | `"{{ total }} pública/públicas"` | rótulo **condicional** (A1) |
| [:129-167](resources/views/autores/show.blade.php#L129-L167) | grade `x-mensagem.card variante=perfil` (`@auth`+selo já ok) | fonte viewer-aware; markup igual |
| [:165](resources/views/autores/show.blade.php#L165) | estado vazio `"Ainda não há mensagens públicas deste autor."` | rótulo **condicional** (A1) |
| [:169-173](resources/views/autores/show.blade.php#L169-L173) | rodapé **estático** de login ("Somente mensagens públicas… entre") | vira **condicional 2-estados** (O1) |
| **novo** | bloco **"Sobre {nome}"** entre os tiles ([:116](resources/views/autores/show.blade.php#L116)) e a grade ([:118](resources/views/autores/show.blade.php#L118)) | **criar** (B2) |
| [:178-209](resources/views/autores/show.blade.php#L178-L209) | sidebar "Em destaque"/"Formatos" (deriva de `$resumo`) | viewer-aware de graça; não vaza destinatários |

**Lista** [autores/index.blade.php](resources/views/autores/index.blade.php):

| Onde | O quê | Ação |
|---|---|---|
| [:49-51](resources/views/autores/index.blade.php#L49-L51) | grade `x-autor.card` | fonte viewer-aware (controller) |
| [:71-72](resources/views/autores/index.blade.php#L71-L72) | mini-stat `$totalMensagensPublicas` + rótulo `'Mensagens públicas'` | **renomear** var + rótulo condicional (A1) |

**Card** [components/autor/card.blade.php](resources/views/components/autor/card.blade.php):

| Onde | O quê | Ação |
|---|---|---|
| [:3-8](resources/views/components/autor/card.blade.php#L3-L8) | comentário-contrato ("pré-filtrada por `publica()`", "SÓ das públicas") | **reescrever** viewer-aware (A1) |
| [:10](resources/views/components/autor/card.blade.php#L10) | `$contagem = $autor->mensagens_publicas_count ?? 0` | **renomear** para `mensagens_visiveis_count` (A1) |
| [:20-27](resources/views/components/autor/card.blade.php#L20-L27) | avatar `@if(foto_url)<img>@else<iniciais>` | `@else` vira **fallback SVG** (B2) |
| [:34](resources/views/components/autor/card.blade.php#L34) | rótulo `"{{ contagem }} mensagem/mensagens"` | **já é neutro** — não muda |

O rótulo visível do card **já** é "N mensagens" (não "públicas"): a mentira do card é só o
**nome interno** e o **comentário** — exatamente o adendo A1.

### 3.5 O selo de nível já é seguro (o null-guard da 3B)

[components/mensagem/selo-nivel.blade.php](resources/views/components/mensagem/selo-nivel.blade.php)
tem `@if($visibilidade)` no topo — `nivel=null` ⇒ `visibilidade()` devolve `null`
([Mensagem.php:92](app/Models/Mensagem.php#L92)) ⇒ **não renderiza nada** (não chama
`->cor()`/`->rotulo()` em null). É chamado **sob `@auth`** dentro de
[components/mensagem/card.blade.php](resources/views/components/mensagem/card.blade.php), e a
variante `perfil` (usada na grade do perfil do autor,
[show.blade.php:158](resources/views/autores/show.blade.php#L158)) já consome esse selo com
guarda. **Conclusão:** a grade do perfil não quebra com `nivel=null` — mas **qualquer selo novo**
que a fatia introduza tem de repetir o guard (R2).

### 3.6 O molde exato de Cache-Control (3B)

[MensagemController.php:14-28](app/Http/Controllers/MensagemController.php#L14-L28) é o molde
literal a copiar:

```php
public function index(Request $request): Response
{
    $usuario = $request->user();
    $resposta = response()->view('mensagens.index', [
        'total' => Mensagem::publicado()->visiveisPara($usuario)->count(),
        'logado' => $usuario !== null,
    ]);
    if ($usuario !== null) {
        $resposta->header('Cache-Control', 'private, no-store'); // R2: contagem/lista variam por usuário
    }
    return $resposta;
}
```

Passa `'logado' => $usuario !== null` à view (é o bool que a fatia usa no rótulo condicional) e
só carimba `private, no-store` quando logado. O `@show` do `MensagemController`
([:70-72](app/Http/Controllers/MensagemController.php#L70-L72)) carimba quando a visibilidade
não é pública. **Hoje o `AutorEspiritualController` não faz nada disso** — é o buraco que O3/R3
fecham.

### 3.7 A bio é HTML saneado — a política de prosa

[AutorEspiritual.php:73-78](app/Models/AutorEspiritual.php#L73-L78): a `bio` tem mutator **`set`**
= `clean($value, 'conteudo')` — **o mesmo perfil** do corpo da mensagem. Logo, a bio é HTML já
higienizado, e renderiza **exatamente como o corpo**:
[corpos/psicografia.blade.php:6](resources/views/mensagens/corpos/psicografia.blade.php#L6) —
`<div class="cema-msg-prose">{!! $mensagem->corpo !!}</div>`. O `{!! !!}` é seguro **porque**
`clean()` já saneou no `set` (mesmo argumento do comentário
[psicografia.blade.php:1-3](resources/views/mensagens/corpos/psicografia.blade.php#L1-L3)).
O bloco "Sobre" usa `{!! $autor->bio !!}` num container de prosa; a tipografia do handoff
(14px/1.85, `var(--soft)`) é aplicada por classe (o plano decide entre reusar `.cema-msg-prose`
ou aplicar as utilities do handoff — ver §5.4).

### 3.8 O SVG de fallback já existe e serve aos dois contextos

`design_handoff_autor_espiritual_perfil/entrega_autor_fallback/autor-fallback.svg` — `viewBox
"0 0 600 800"` (**3:4**), **fundo lavanda próprio** (gradiente `#F0EDF8→#DDD6EE` num `rect` full),
composição de "luz espiritual" (halos + partículas), **sem rosto humano**. Por trazer fundo
próprio, funciona **sobre branco** (card da lista) e **sobre roxo** (moldura translúcida do
hero) com `object-fit: cover`, sem parecer imagem quebrada. A subpasta `entrega_autor_fallback/`
é a entrega **mais nova** e substitui o gradiente+iniciais do handoff-base (D2).

### 3.9 Foto e ativo — a API do model

[AutorEspiritual.php](app/Models/AutorEspiritual.php): `COLECAO_FOTO='foto'`
([:25](app/Models/AutorEspiritual.php#L25)); `foto_url` = `getFirstMediaUrl('foto','web') ?: null`
([:57-63](app/Models/AutorEspiritual.php#L57-L63)) — usado com truthiness (`@if($autor->foto_url)`);
`scopeAtivo` ([:36-39](app/Models/AutorEspiritual.php#L36-L39)); `bio` é `$fillable`
([:27](app/Models/AutorEspiritual.php#L27)). As `iniciais` vêm do trait
[TemIniciais](app/Models/Concerns/TemIniciais.php), **compartilhado** por `AutorEspiritual`,
`Palestrante` e `User` — por isso o fallback é **só das views de autor** (R7).

### 3.10 Sitemap e testes de guarda já existem (O2)

[SitemapController.php:41-44](app/Http/Controllers/SitemapController.php#L41-L44): os autores no
sitemap saem de `AutorEspiritual::ativo()->whereHas('mensagens', publica())` — **`publica()`,
não `visiveisPara`**. **Mantém.** Trocar por `visiveisPara($user)` vazaria URL de autor
só-restrito ao crawler (O2/R5). O teste [AutorSitemapTest](tests/Feature/Front/AutorSitemapTest.php)
já cobre "autor só-restrito fora"; [AutorSeoTest](tests/Feature/Front/AutorSeoTest.php) cobre a
meta. Ambos **devem seguir verdes sem alteração** — são a rede do O2.

---

## 4. Invariantes (cada um vira teste que reprova)

### Bloco B1 — visibilidade

- **I1 — anônimo idêntico ao 2B.** Para `$usuario === null`, a lista e o perfil renderizam o
  **mesmo conjunto** de mensagens/autores de hoje (o de `publica()`), com as **mesmas** contagens
  e o **mesmo** rótulo "públicas". Rede contra alguém "melhorar" a experiência do anônimo por
  engano. (Prova de base: §3.1 — `visiveisPara(null) ≡ publica()`.)
- **I2 — escopo único, sem `publica()` residual.** No `AutorEspiritualController`, **nenhum**
  ponto usa `publica()`; todos usam `publicado()->visiveisPara($usuario)`. Guarda por leitura
  (§9, grep) + o teste I4.
- **I3 — a lista é viewer-aware.** Um usuário que enxerga níveis restritos vê **mais autores** na
  grade que o anônimo (autor com só-restrita visível a ele entra; para o anônimo, não).
- **I4 — contadores == escopo que lista.** Para cada papel: o número do card
  (`mensagens_visiveis_count`) == a quantidade de mensagens que a grade daquele perfil mostra; e
  o mini-stat do topo do index conta pelo **mesmo** escopo. Nunca dois escopos na mesma tela.
- **I5 — Cache-Control.** A resposta **logada** (index e show) leva `Cache-Control: private,
  no-store`; a resposta **anônima** **não** leva (segue cacheável). Nos **dois** controllers.
- **I6 — rodapé condicional (O1).** O rodapé só aparece quando há mensagem **hierárquica**
  (`whereNotNull('nivel')` **e** `nivel != 'direcionada'`) do autor **invisível** àquele usuário.
  Some para admin/presidente e para quem já vê tudo do autor.
- **I7 — anti-PII do rodapé.** Uma **Direcionada a terceiro** anexada ao autor **não** faz o
  rodapé aparecer (direcionadas ficam fora dos **dois** lados da conta). ⚠️ Teste **não-vacuoso**:
  a string do rodapé tem de **poder** aparecer naquela superfície sem o guard (§8, R4).
- **I8 — `nivel=null` fora do rodapé.** Mensagem publicada com `nivel=null` **não** dispara o
  rodapé (`whereNotNull` a exclui dos dois lados) — não se inventa "há restrito" a partir de dado
  incompleto.
- **I9 — anônimo com rodapé condicional (A2).** Anônimo em autor **só-público** → **sem** rodapé;
  anônimo em autor com **restrita hierárquica** → rodapé `@guest` (com link de login).
- **I10 — O2 intacto.** O sitemap de autores segue em `publica()`; autor **só-restrito** **não**
  entra no sitemap; a meta description do perfil **não** reflete conteúdo restrito (deriva de
  `chamada`/`bio`).
- **I11 — rótulos consistentes (A1).** Nenhuma superfície visível **nem** nome interno afirma
  "públicas" quando o escopo é viewer-aware: anônimo lê "públicas"; logado lê neutro; o alias
  interno é `…_visiveis_…`; o comentário-contrato do card descreve `visiveisPara`.

### Bloco B2 — perfil

- **I12 — fallback nas duas telas.** Autor **sem** foto renderiza a **imagem SVG**
  (`asset('images/autor-fallback.svg')`), **não** as iniciais, tanto no **hero** do perfil quanto
  no **card** da lista. Autor **com** foto renderiza a foto (não o SVG).
- **I13 — fallback só do autor.** O trait `TemIniciais` **não** muda: `Palestrante` e `User`
  seguem renderizando iniciais (guarda de não-regressão — o fallback vive só nas 2 views de autor).
- **I14 — bloco "Sobre".** Perfil **com** bio mostra o bloco (título "Sobre {nome}" + a bio como
  prosa); perfil **sem** bio **não** tem o bloco (nem card vazio, nem placeholder).
- **I15 — sem órfão de `chamada`.** As `chamada` vazias (19/19 no dev) não deixam elemento órfão
  no hero, no card nem no destaque (já condicional — guarda de não-regressão).

---

## 5. Decisões de desenho

### 5.1 Controller (B1 — o coração)

Molde: [MensagemController](app/Http/Controllers/MensagemController.php) (§3.6). Ambos os métodos
passam a **injetar `Request`** e **retornar `Response`**:

```php
public function index(Request $request): Response
public function show(Request $request, string $slug): Response
```

`$usuario = $request->user()`. ⚠️ **NÃO unifique os 4 usos do `@index` num `Closure` tipado
`Builder`.** O eager-load `with(['mensagens' => ...])` recebe a **Relation** (`BelongsToMany`),
não um `Builder` (`eagerLoadRelation` chama `$constraints($relation)`) — um `fn (Builder $q)`
reusado ali estoura **`TypeError`** (type-hint de classe é estrito mesmo sem
`declare(strict_types)`). **Mantenha a estrutura atual** (o `whereHas`/`withCount` recebem
`Builder`; só o `with` recebe a Relation) e troque apenas o scope em cada ponto:

```php
->whereHas('mensagens', fn (Builder $q) => $q->publicado()->visiveisPara($usuario))
->withCount(['mensagens as mensagens_visiveis_count' => fn (Builder $q) => $q->publicado()->visiveisPara($usuario)])
->with(['mensagens' => fn ($q) => $q->publicado()->visiveisPara($usuario)])   // SEM type-hint: recebe a Relation
// ...
$totalMensagensVisiveis = Mensagem::publicado()->visiveisPara($usuario)->count();
```

No `@show`, a fonte única `$publicas` (renomear opcional; o miolo é trocar a origem):
`$autor->mensagens()->publicado()->visiveisPara($usuario)->with(['media','autores'])->get()->sortByDesc(...)`.
`ResumoAutor`, `$itensFiltro`, `$destaque` e a grade seguem derivando dela (nenhum outro ponto
muda). Ambos retornam `response()->view(...)` com `'logado' => $usuario !== null` no payload e o
header `private, no-store` **só** quando `$usuario !== null`.

### 5.2 Rodapé condicional (O1 — a peça PII-sensível)

A view precisa de um bool `$temRestritasOcultas`, calculado no `@show`:

```php
$hierarquicas = fn (Builder $q) => $q->publicado()->whereNotNull('nivel')
    ->where('nivel', '!=', VisibilidadeMensagem::Direcionada->value);

$temRestritasOcultas =
    (clone base do autor)->tap($hierarquicas)->count()
    > (clone base do autor)->tap($hierarquicas)->visiveisPara($usuario)->count();
```

*(Forma exata a cargo do plano — duas contagens sobre `$autor->mensagens()`, uma sem e outra com
`visiveisPara`, ambas restritas a hierárquicas não-direcionadas não-nulas.)*

- **Direcionada fora dos dois lados** → uma direcionada a terceiro nunca conta como "oculta"
  (I7/anti-PII); uma direcionada ao próprio já é visível (não é "oculta").
- **`whereNotNull('nivel')`** → as `nivel=null` não disparam (I8), e a semântica é explícita e
  **portável** (não depende de `NULL != 'x'` do SQL, que varia; embora coincidam em SQLite/MySQL).
- **Bypass** (admin/presidente): `visiveisPara` devolve tudo → visíveis == total → `false` → some.
- **Sem número** (F2): só a **existência**, nunca a contagem de ocultas.

**Copy (F2, dois estados),** substituindo o rodapé estático
[show.blade.php:169-173](resources/views/autores/show.blade.php#L169-L173), dentro de
`@if($temRestritasOcultas)`:

```blade
@guest
    Há mensagens restritas a trabalhadores e médiuns.
    <a href="{{ route('login') }}" ...>Entre</a> para ver o que é seu.
@else
    Este autor tem mensagens restritas que você ainda não pode ver.
@endguest
```

A copy `@guest` cita "trabalhadores e médiuns" como **exemplos**, mas o rodapé também dispara para
oculta de **diretores/diretor-DEPAE**: a frase é **deliberadamente genérica** — não nomeia o nível
oculto, para não vazar qual conteúdo específico o usuário não alcança. Aprovado pelo dono no passe.

### 5.3 Rótulos "públicas" — inventário do A1

**Nome interno (viewer-aware, incondicional):**

| Lugar | De | Para |
|---|---|---|
| [Controller:22](app/Http/Controllers/AutorEspiritualController.php#L22) alias `withCount` | `mensagens_publicas_count` | `mensagens_visiveis_count` |
| [Controller:29](app/Http/Controllers/AutorEspiritualController.php#L29) `sortByDesc(...)` | `'mensagens_publicas_count'` | `'mensagens_visiveis_count'` |
| [Controller:28,34](app/Http/Controllers/AutorEspiritualController.php#L28-L34) var + chave de view | `$totalMensagensPublicas` | `$totalMensagensVisiveis` |
| [card.blade.php:10](resources/views/components/autor/card.blade.php#L10) | `$autor->mensagens_publicas_count` | `$autor->mensagens_visiveis_count` |
| [card.blade.php:3-8](resources/views/components/autor/card.blade.php#L3-L8) comentário-contrato | "pré-filtrada por `publica()`", "SÓ das públicas" | descrever `publicado()->visiveisPara($usuario)` |
| [ResumoAutor.php:12-16,78](app/Support/AutoresEspirituais/ResumoAutor.php#L12-L16) docblock | "mensagens **PÚBLICAS**" | "mensagens **visíveis ao usuário**" |
| [Controller:17-18,45-46](app/Http/Controllers/AutorEspiritualController.php#L17-L18) comentários (a **L18** diz "já filtrado por **`publica()`**"; L45-46 "Só as PÚBLICAS") | | reescrever viewer-aware — a menção a `publica()` na **L18 tem de sair**, senão o grep §9.3 reprova (R2) |

**Rótulo visível (condicional em `$logado`)** — o logado usa **"disponíveis a você"**, alinhado ao
**precedente do módulo**: o índice de Mensagens já diz "mensagens disponíveis a você" para o logado
([MensagemController](app/Http/Controllers/MensagemController.php) + `mensagens.index`; travado em
[MensagemIndexContadorTest:28](tests/Feature/Front/MensagemIndexContadorTest.php#L28)). **Refina o
A3** (que dizia "Mensagens") — decidido no arranque do plano (2026-07-23) para a superfície de
Autores não introduzir um 3º vocabulário:

| Superfície | Anônimo | Logado |
|---|---|---|
| [index:72](resources/views/autores/index.blade.php#L72) mini-stat | "Mensagens públicas" | "Mensagens disponíveis a você" |
| [show:17](resources/views/autores/show.blade.php#L17) tile | "Mensagens públicas" | "Mensagens disponíveis a você" |
| [show:126](resources/views/autores/show.blade.php#L126) contagem da grade | "N pública/públicas" | "N disponíveis a você" |
| [show:165](resources/views/autores/show.blade.php#L165) estado vazio | "Ainda não há mensagens públicas deste autor." | "Ainda não há mensagens deste autor que você possa ver." (frase natural, não a frase-rótulo) |

O card ([:34](resources/views/components/autor/card.blade.php#L34)) já é **neutro** ("N mensagens",
nos dois casos) — não afirma "públicas", logo **não** muda.

### 5.4 Bloco "Sobre {nome}" (B2)

Novo bloco no perfil, **entre** os tiles ([show:116](resources/views/autores/show.blade.php#L116))
e o cabeçalho "Mensagens de {nome}" ([show:118](resources/views/autores/show.blade.php#L118)),
conforme o handoff (`design_handoff_autor_espiritual_perfil`, seção "Sobre {nome}"):

- **Card branco:** `bg-white`/`var(--card)`, `border`, `rounded-[18px]`, `shadow-card`, padding 28.
- **Título** `<h2>` "Sobre {nome}" — display 600, ~20px, `text-primary`.
- **Régua dourada** logo abaixo: `h-[3px] w-[52px] rounded bg-gold`.
- **Bio** em prosa: `{!! $autor->bio !!}` num container de prosa (14px, `leading` ~1.85,
  `var(--soft)`). A bio já é saneada (§3.7) — `{!! !!}` é seguro. O plano decide entre reusar
  `.cema-msg-prose` (se a tipografia bater) ou aplicar as utilities do handoff.
- **Condicional:** todo o bloco dentro de `@if(filled($autor->bio))` (D3) — sem bio, o bloco
  **não existe**.

### 5.5 Fallback de foto (B2 — O4)

1. **Copiar** `design_handoff_autor_espiritual_perfil/entrega_autor_fallback/autor-fallback.svg`
   para `public/images/autor-fallback.svg` (asset estático, molde `asset('images/...')` — sem
   Vite; §3.8).
2. **Substituir o ramo `@else`** (gradiente+iniciais) por `<img src="{{ asset('images/autor-fallback.svg') }}" ...>`
   em **exatamente duas views**:
   - hero: [show.blade.php:64-67](resources/views/autores/show.blade.php#L64-L67) — `alt="{{ $autor->nome }}"`.
     Aqui o `cema-grad-{id%8}` vive **dentro** do `@else` ([show:65](resources/views/autores/show.blade.php#L65)) → some com o ramo;
   - card: [card.blade.php:23-26](resources/views/components/autor/card.blade.php#L23-L26) —
     `alt=""` (o nome já está ao lado; imagem decorativa) + `loading="lazy"`.
   - ⚠️ **card, wrapper `:20`:** o `cema-grad-{{ $autor->id % 8 }}` está no `<span>` **envolvente**
     ([card.blade.php:20](resources/views/components/autor/card.blade.php#L20)), presente nos
     **dois** ramos — não só no `@else`. **Remover a classe `cema-grad-*` da `:20`** (o
     `cema-autor-avatar` fica): no caso COM foto o `<img size-full object-cover>` cobre tudo; no
     caso SEM foto o SVG traz fundo próprio — o gradiente-placeholder nunca aparece, some **sem
     efeito visível**. Sem isso, o grep de prova §9.4 reprova.
   Manter as classes da moldura (`aspect-[3/4]`, `object-cover`, cantos) — o SVG traz fundo próprio.
3. **NUNCA** tocar o trait `TemIniciais` (R7): `Palestrante`/`User` seguem com iniciais. O
   gradiente `cema-grad-{id%8}` some **só** dessas duas telas de autor.

### 5.6 ResumoAutor (B1, colateral)

[ResumoAutor](app/Support/AutoresEspirituais/ResumoAutor.php) **não muda de lógica** — recebe a
coleção já materializada pelo controller ([:28](app/Support/AutoresEspirituais/ResumoAutor.php#L28)),
que agora é viewer-aware. Só o **docblock** ([:12-16](app/Support/AutoresEspirituais/ResumoAutor.php#L12-L16),
[:78](app/Support/AutoresEspirituais/ResumoAutor.php#L78)) que diz "PÚBLICAS" é reescrito (A1).
Efeito de graça: `selos()`, `porFormato()`, `predominante()`, `ultimaMensagem()` passam a
refletir o que o usuário vê — inclusive a sidebar "Formatos" e "Em destaque". Nunca expõe
destinatários (a Direcionada só entra em `$publicas` se for **do próprio** usuário, via
`visiveisPara`).

---

## 6. Riscos e armadilhas

- **R1 — `publica()` residual no controller de autor.** Se sobrar **um** `publica()` entre os 4
  pontos, ou o contador diverge da grade, ou (pior) o anônimo muda. Allowlist pós-fatia:
  `publica()` na área de autores vive **só** no `SitemapController` (§3.10). Guarda: grep do §9.
- **R2 — `nivel=null` × selo × admin.** O selo já tem null-guard (§3.5) — **não introduzir selo
  novo sem `@if($visibilidade)`**. E o admin/presidente passa a ver as **2** `nivel=null` nas
  contagens/grade (bypass, §3.2) — quirk de dado, **declarado** no PR, não "consertado" aqui.
- **R3 — Cache-Control ausente = vazamento entre usuários.** Sem `response()->view` + header, um
  proxy serve o perfil/lista **rico de um usuário a outro** (títulos, contagens restritas, até
  Direcionada). Obrigatório nos **dois** métodos (I5). Hoje ambos retornam `View` cru — é o buraco.
- **R4 — rodapé "não-vacuoso".** `assertDontSee` do anti-PII (I7) só vale se a string do rodapé
  **puder** aparecer sem o guard. E `assertSee(route('login'))` é **enganoso**: o link de login
  também vive no **header** do layout para `@guest`
  ([header.blade.php:29](resources/views/components/layout/header.blade.php#L29),
  [:115](resources/views/components/layout/header.blade.php#L115)) — o teste do rodapé deve asserir
  a **frase** do rodapé, não a rota. É por isso que o teste atual (§8) fica **vacuoso**, não
  vermelho.
- **R5 — sitemap NÃO vira `visiveisPara($user)`.** URL de autor só-restrito vazaria ao crawler
  (O2). Mantém `publica()`.
- **R6 — rótulo hardcoded esquecido.** Se um dos 4 pontos visíveis ficar "públicas" para o logado,
  mente. Guarda: grep de "públicas" na área de autores (§9) + I11.
- **R7 — fallback no trait vaza para Palestrante/User.** O fallback é **só** das 2 views de autor.
  Mexer no `TemIniciais` trocaria a iniciais de palestrante e de usuário — proibido (I13).
- **R8 — lista × perfil coerentes por desenho.** Como **ambos** viram viewer-aware juntos (D1),
  não há "vejo no perfil um autor que não acho na grade". Se alguém trocar só o perfil, reabre —
  I3 (grade) é a rede.
- **R9 — `{!! bio !!}` é seguro porque saneado.** A bio passa por `clean('conteudo')` no `set`
  (§3.7). Renderizar com `e()` quebraria a formatação; `{!! !!}` de campo **não** saneado seria
  XSS — aqui é o mesmo caso do corpo, seguro.
- **R10 — ordem do cutover.** `restart` antes de `build` (se houver) deixaria a view nova sem o
  CSS; ver §7. E o SVG precisa **existir em `public/images/`** antes do `restart` (asset estático).

---

## 7. Cutover (dev; PROD do dono, quando houver)

Sem migration (a fatia não toca schema). Sem dado a mover. É **código + 1 asset estático**.

```
1) git checkout / pull do código novo   (traz o public/images/autor-fallback.svg versionado)
2) [se o plano introduzir classe Tailwind nova]  npm run build   ← no HOST (o container não tem Node)
3) docker compose exec app php artisan optimize:clear
4) docker compose restart app worker
```

**Sobre o `npm run build` (passo 2):** o bloco "Sobre" e os rótulos condicionais usam utilities
Tailwind que **provavelmente já existem** no bundle (reaproveitadas de outros cards/prosa). **A
regra:** o arranque da execução confere se surgiu classe nova (JIT); se **não** surgiu, pula o
build (o SVG é asset estático, servido por `asset()` sem manifest). Vite roda **no host**
(memória `npm-vite-no-host`).

**Conferir depois (localhost):**
1. **anônimo** vê a lista **idêntica** à de hoje (no dev, 5 autores) e **sem** rodapé num autor
   só-público;
2. **logado** (Thiago, 105) vê **16** autores na grade; o número do card bate com a grade de cada
   perfil;
3. o **rodapé** aparece num autor com restrita hierárquica para quem não a vê, e **some** para o
   admin;
4. **Abílio** (sem foto) mostra o **SVG** de fallback no card **e** no hero (não as iniciais);
5. um autor **com** bio mostra o bloco **"Sobre"**; um dos **6 sem bio** (ex.: Pai Joaquim) **não**
   tem o bloco;
6. no navegador logado, a resposta de `/autores-espirituais` traz `Cache-Control: private,
   no-store` (DevTools → Network); a anônima, não.

⚠️ **Sem `cema:importar-*`, sem migration, sem tocar o banco.** A fatia é 100% apresentação sobre
regra já existente.

---

## 8. Testes — lista nominal

Baseline a **reconfirmar** no arranque da execução (main pós-F4c-D ≈ **1286** passed).

**Tocar (ficam vacuosos ou imprecisos com B1 — nenhum "quebra"):**

| Arquivo:linha | Método | Ação |
|---|---|---|
| [AutorShowTest:53-60](tests/Feature/Front/AutorShowTest.php#L53-L60) | `test_sem_curtir_e_com_link_login` | **REESCREVER** — ⚠️ **NÃO quebra**: `route('login')` também vive no HEADER sob `@guest` ([header.blade.php:29](resources/views/components/layout/header.blade.php#L29) e [:115](resources/views/components/layout/header.blade.php#L115), links "Entrar"), então `assertSee(route('login'))` segue **verde pelo header** mesmo sem rodapé — o teste fica **vacuoso** quanto ao rodapé (é o R4, **não** um "red que guia a mudança"). Separar em (a) `test_sem_curtir` (só `assertDontSee('Curtir')`) e (b) **teste novo** do rodapé asserindo a **frase exata** ("Há mensagens restritas a trabalhadores e médiuns" / "Este autor tem mensagens restritas que você ainda não pode ver"), **nunca** `route('login')` |
| [AutorShowTest:34-43](tests/Feature/Front/AutorShowTest.php#L34-L43) | `test_grade_e_stats_so_das_publicas` | **MANTER como caso ANÔNIMO** (renomear para deixar claro que é o anônimo); segue válido (anônimo ≡ `publica()`) |
| [AutoresIndexTest:39-46](tests/Feature/Front/AutoresIndexTest.php#L39-L46) | `test_contagem_so_das_publicas` | **MANTER como caso ANÔNIMO** ("3 mensagens" segue certo para o anônimo); o caso logado é **teste novo** |

**Novos — Bloco B1:**

| # | O que prova |
|---|---|
| **I1** | anônimo: lista/perfil == conjunto e contagens de `publica()` (rótulo "públicas") |
| **I3/I4** | logado (trabalhador vê +trabalhadores; diretor+médium vê +médiuns): a grade cresce e a contagem do card == cards da grade, por papel; mini-stat do topo no mesmo escopo |
| **I5** | resposta logada tem `Cache-Control: private, no-store`; anônima não (index **e** show) |
| **I6** | rodapé aparece p/ quem tem oculta hierárquica; **some** p/ admin (vê tudo) |
| **I7** | **anti-PII**: Direcionada a **terceiro** anexada ao autor **não** faz o rodapé aparecer (não-vacuoso — a frase pode aparecer sem o guard) |
| **I8** | mensagem `nivel=null` do autor **não** dispara o rodapé |
| **I9 (A2)** | **anônimo**: autor só-público → sem rodapé; autor com restrita hierárquica → rodapé `@guest` com link |
| **I11 (A1)** | rótulo condicional: anônimo lê "públicas", logado lê "Mensagens"/"N mensagens" nas 4 superfícies |

**Novos — Bloco B2:**

| # | O que prova |
|---|---|
| **I12** | autor **sem** foto → `assertSee('images/autor-fallback.svg')` e `assertDontSee(iniciais)` no **card** (index) e no **hero** (show); autor **com** foto → a foto, não o SVG |
| **I13** | não-regressão: um `Palestrante`/`User` sem foto segue com **iniciais** (o trait não mudou) |
| **I14** | perfil **com** bio → `assertSee('Sobre {nome}')` + trecho da bio; perfil **sem** bio → `assertDontSee('Sobre')` (bloco ausente) |
| **I15** | `chamada` vazia não deixa órfão (guarda; provável já coberto — confirmar) |

**Seguir verdes sem tocar (a rede do O2):** [AutorSitemapTest](tests/Feature/Front/AutorSitemapTest.php)
(autor só-restrito fora do sitemap), [AutorSeoTest](tests/Feature/Front/AutorSeoTest.php) (meta
não reflete restrito). Se algum ainda **não** prova o `Cache-Control` do sitemap/anônimo, o plano
acrescenta.

---

## 9. O que prova que está pronto

1. Suíte **completa** verde (feature tests por papel; o comportamento é por-usuário).
2. `pint --test` limpo **antes** de qualquer push (o CI aborta no Pint, antes dos testes —
   memória `pint-antes-de-push`).
3. `grep -rn "publica()" app/Http/Controllers/AutorEspiritualController.php` devolve **zero** —
   **inclui o comentário da L18** ("já filtrado por publica()"), que sai junto (R2);
   `grep -rn "publicas\|públicas" resources/views/autores resources/views/components/autor` só
   devolve os rótulos **condicionais** do anônimo (allowlist fechada) — R1/R6.
4. `grep -rn "cema-grad" resources/views/autores resources/views/components/autor` devolve
   **zero** (o `@else` do hero virou SVG **e** a classe saiu do wrapper `:20` do card); `grep -rn
   "iniciais" ...` idem nas views de autor; o trait `TemIniciais` intacto (R7/I13).
5. CI **verde no último commit** (memória `merge-so-com-ci-verde-no-commit-final`).
6. Conferência no localhost dos 6 itens da §7.

---

## 10. Fora de escopo

- **Busca/ordenação na lista de autores** (F3): paginação/busca quando a lista crescer — fatia
  própria. (O filtro client-side das mensagens **no perfil** já existe e não é tocado.)
- **Engajamento / Curtir** (F5): botão, `aria-pressed`, tile "Curtidas" reativo, tabela de likes
  — todo o sistema fora.
- **Curadoria/descoberta:** "Autor em evidência"/"Em destaque" seguem como estão (não são o alvo
  do B1); nenhuma curadoria nova.
- **Compartilhar / newsletter:** já existem/são de outra fatia; não mudam.
- **Preencher `bio`/`chamada`/`foto`:** conteúdo do dono no `/admin` — a fatia só **tolera** o
  vazio (D3).
- **Dívida do molde Filament-no-site** (falta `@filamentStyles` + preflight): fatia própria.
- **Qualquer mudança em `Palestrante`/`User`:** o fallback e a visibilidade são **só** de autor.
- **`noindex` no perfil** (A3): desnecessário — o crawler vê a versão pública canônica.

---

## 11. Pendências de ratificação — **RATIFICADAS** no arranque (2026-07-23)

Levadas ao dono no arranque, com o dado medido, e aprovadas antes de escrever a SPEC.

| # | Pendência | Decisão |
|---|---|---|
| **F1** | Corte da fatia | ✅ **1 PR, 2 blocos** (mesmo terreno; B2 mexe nas views que B1 tocou) |
| **F2** | Texto do rodapé condicional | ✅ **Dois estados, sem número** (§5.2) |
| **F3** | Busca/ordenação na lista | ✅ **Fora** desta fatia |
| **A1** | Renomear "públicas" de forma consistente | ✅ 4 superfícies visíveis (condicional) **+** nome interno do card **+** comentário-contrato **+** docblock do `ResumoAutor` (viewer-aware). Inventário em §5.3 |
| **A2** | Teste do rodapé para o anônimo | ✅ I9 |
| **A3** | Defaults do design | ✅ rótulo logado neutro ("Mensagens"); `whereNotNull('nivel')` fora do rodapé; **sem** `noindex` |

**Divergência declarada no PR (D2):** o handoff-base resolve "sem foto" com **gradiente +
iniciais**; a decisão do dono e a entrega `entrega_autor_fallback/` usam **imagem de fallback**.
A entrega mais nova vence; o pacote é internamente inconsistente e isso vai escrito no corpo do PR.

---

## 12. Correções aplicadas no passe da SPEC (2026-07-23)

Passe do consultor: SPEC sólida, terreno conferido linha a linha contra `8b2c03f`; liberada para o
plano **após** 2 obrigatórios + 2 refinamentos, todos incorporados acima.

| # | Achado | Correção |
|---|---|---|
| **O1** (obrigatório) | §8 dizia que `test_sem_curtir_e_com_link_login` **quebra**. **Falso:** `route('login')` vive no header sob `@guest` ([header:29](resources/views/components/layout/header.blade.php#L29),[:115](resources/views/components/layout/header.blade.php#L115)) ⇒ `assertSee(route('login'))` segue verde pelo header; o teste fica **vacuoso** quanto ao rodapé, não vermelho (é o próprio R4). | §8 reescrito: o teste não guia a mudança; split em `test_sem_curtir` + teste novo do rodapé asserindo a **frase exata**, nunca a rota. R4 alinhado. |
| **O2** (obrigatório) | §5.1 recomendava um `Closure fn (Builder $q)` **único** para os 4 usos. **Bug:** o `with(['mensagens' => ...])` recebe a **Relation** (`BelongsToMany`), não `Builder` — closure tipado ali estoura `TypeError` (type-hint de classe é estrito mesmo sem `strict_types`). | §5.1 reescrito: manter a estrutura atual (só o `with` sem type-hint), trocando apenas `publica()`→`publicado()->visiveisPara($usuario)` em cada ponto. |
| **R1** (refinamento) | §5.5 trocava só o `@else` do card, mas `cema-grad-{id%8}` está no **wrapper `:20`** (nos dois ramos) ⇒ o grep de prova §9.4 reprovaria. | §5.5 inclui a `:20` (remover `cema-grad-*` do wrapper; some sem efeito visível). §9.4 alinhado. |
| **R2** (refinamento) | O grep `publica()`=zero do §9.3 casa o **comentário da L18** ("já filtrado por publica()"); §5.3 listava só "17,45-46". | §5.3 e §9.3 incluem a L18 no par de comentários a reescrever. |

**Não-bloqueante (mantido por decisão):** a copy `@guest` nomeia "trabalhadores e médiuns" como
exemplos, mas o rodapé dispara para qualquer oculta hierárquica (inclui diretores/DEPAE) — frase
**genérica de propósito**, não vaza o nível (§5.2).
