# Spec — Camada 4 · Fatia 3C · "Minhas Direcionadas" (aba read-only no /minha-conta)

> Autoria: Thiago Mourão — https://github.com/MouraoBSB — 2026-07-20
> Enquadramento travado com o dono no kickoff da Fatia 3C. Este spec **não** improvisa além das decisões
> travadas; **cada afirmação sobre o terreno foi verificada contra o código real** (evidência `arquivo:linha`
> no §3, levantada por leitura direta dos moldes: `AbaAgenda`, `ContaController`, `nav.blade.php`,
> `x-layout.conta`, `x-mensagem.card`, `User::mensagensDirecionadas`, `Mensagem::scopePublicado` e a nota
> "Direcionada a você" da 3B em `mensagens/show.blade.php`).
> Destino: **SPEC — ✅ APROVADA no passe do consultor (zero bloqueador; O1–O5 fechados com o dono, §13).** Segue para
> o **PLANO** (TDD, ordem §9.0). **NÃO implementar ainda** — o plano vai ao passe do consultor antes da execução.
> Base: `origin/main` (HEAD **`c517b70`**, PR #40 — **Fatia 3B mesclada**; a 3C ramifica daqui). Branch de
> trabalho: `camada-4-fatia-3c-minhas-direcionadas`. Suíte baseline: **~1063 testes** (a `MEMORY.md` registra a
> 3B em 1063; medir com `docker compose exec -T app php artisan test --list-tests` antes de começar).
> Fundação: a **Fatia 3A** (o pivô `mensagem_destinatario`, `User::mensagensDirecionadas()`, o accessor
> `visibilidade()`, `podeSerVistoPor`/`scopeVisiveisPara`) e a **Fatia 3B** (o `scopePublicado` status-only, a
> **barreira cega** do single, a leitura da própria direcionada via `visiveisPara`, a **nota "Direcionada a você"**
> no single do destinatário, o card `x-mensagem.card` com badge Direcionada `@auth`). A 3C **só consome** o que
> a 3A/3B já entregaram — é a **superfície de navegação** que o SPLIT F1 da 3B deixou de fora.

---

## 0. Recorte: por que esta é a fatia "3C" (e o que já ficou na 3B)

A Fatia 3 (visibilidade rica das Mensagens) foi partida em **3A** (backend, mesclada), **3B** (front rico + barreira,
mesclada) e **3C** (ESTE spec). O **coração da Direcionada já está na 3B** — o SPLIT **F1** (fechado pelo dono na 3B,
§0/§13 do spec da 3B) deixou para a 3C **apenas** a superfície de **navegação** das direcionadas do próprio usuário.

- **3A (mesclada, PR #39):** o pivô `mensagem_destinatario` (PII) + `User::mensagensDirecionadas()` + o resolvedor
  (`podeSerVistoPor`/`scopeVisiveisPara`) — inerte no site.
- **3B (mesclada, PR #40):** o front rico (badges/cadeado/legenda), a **barreira cega** do single (anônimo/não-
  destinatário caindo num link de direcionada que circula em WhatsApp é barrado **sem** revelar título/corpo/
  destinatários), a **leitura da própria direcionada** pelo destinatário (via `visiveisPara`), e a **nota
  "Direcionada a você"** no single do destinatário (`mensagens/show.blade.php`, gate `$ehDestinatario`).
- **3C (ESTE spec):** o **ÍNDICE** — uma **aba read-only** no `/minha-conta` (`conta.direcionadas`) onde o
  destinatário vê a **lista** das suas direcionadas **publicadas**, cada card linkando ao single (onde ele já
  passa o gate da 3B). **Nada mais** — aditiva, sem tocar nenhuma superfície pública.

**O que a 3C NÃO é:** não é um "modo" na lista pública (`Mensagens\Lista` fica **intacta**, **não** vira dual-mode);
não cria/edita/marca-como-lida direcionada (isso é **F4/F5**); não expõe a **lista de destinatários** (F2, PII);
não toca a barreira, o single, o resolvedor, o sitemap nem Autores.

---

## 1. Contexto e objetivo

Um destinatário logado hoje só chega às **suas** direcionadas por um **link direto** ao single (recebido fora do
site) — não há, no site, **nenhum lugar** que liste "as mensagens endereçadas a mim". A 3C dá esse **índice pessoal**.

**Objetivo (read-only, aditivo):**

1. **Aba condicional "Minhas Direcionadas"** no menu do `/minha-conta` — visível **só** para quem tem **≥1
   direcionada publicada** endereçada a si (molde `AbaAgenda`).
2. **Rota `conta.direcionadas`** (GET, sob `middleware('auth')`, prefixo `minha-conta`) servindo uma **listagem
   simples** das direcionadas **publicadas** do usuário logado, ordenadas por data de recebimento (mais recentes
   primeiro), reusando o `x-mensagem.card` (badge Direcionada `@auth`).
3. Cada card linka ao **single** (`mensagens.show`), onde o destinatário **já** passa o gate da 3B e vê a mensagem +
   a nota "Direcionada a você".

**A regra de "quem vê" NÃO é reimplementada** — a 3C só filtra `mensagensDirecionadas()->publicado()` (o pivô já é,
por construção, "as minhas"; o `publicado()` remove pendentes/despublicadas). Não há barreira nem noindex-de-
conteúdo novos: a página inteira é **auth-gated** (o middleware redireciona anônimo ao login) e recebe **noindex**
por precaução (lista títulos de direcionadas). **Sem migration** (o pivô veio da 3A).

---

## 2. Decisões travadas (não reabrir)

Do kickoff da 3C (dono) + heranças da 3A/3B:

1. **ONDE VIVE = ABA no `/minha-conta`** (`conta.direcionadas`), **não** um modo na lista pública. A
   `Mensagens\Lista` pública fica **INTACTA** (não vira dual-mode). (F1 já fechado na 3B.)
2. **COMO APARECE = aba CONDICIONAL** — só para quem tem **≥1 direcionada PUBLICADA** (molde `AbaAgenda`).
3. **READ-ONLY:** sem criar/editar/marcar-como-lida (= F4/F5); só as do próprio usuário; **SEM lista de
   destinatários** (F2, PII); **noindex**; `@auth` (a rota é auth-gated).
4. **STATUS obrigatório:** a aba **e** a listagem usam `mensagensDirecionadas()->publicado()` — uma direcionada
   **PENDENTE** (a curadoria F4 ainda não publicou) **não** conta para a aba nem aparece na lista.
5. **Acesso por PERTENCIMENTO, não por capacidade:** `AbaDirecionadas::visivelPara` usa
   `$user->mensagensDirecionadas()->publicado()->where('nivel', VisibilidadeMensagem::Direcionada->value)->exists()`
   — **não** `checkPermissionTo`/`AcessoPorTipo` (a Direcionada não é capacidade; é "esta mensagem foi endereçada a
   mim"). O filtro por `nivel` é a **blindagem O5** (fechada pelo dono): casa a semântica "direcionada" com o vínculo
   do pivô, na **aba e na lista** (§6.1/§6.2/§9 — teste-contrato I7).
6. **Recriar o visual na stack** (Blade + Tailwind), reusando `x-layout.conta` + `x-mensagem.card` — **não** copiar
   HTML. O handoff `design_handoff_mensagens_lista` (hero/card "Área pessoal", §4) é **referência**, **cortando
   lida/não-lida** (F5).
7. **Sem `migrate:fresh`/`refresh`/`wipe`/`reset` nem seed destrutivo** no dev ([[nunca-migrate-fresh-no-dev]]); o
   `legado` é **read-only**. (A 3C **não** tem migration — o pivô `mensagem_destinatario` já existe da 3A.)
8. **Fronteiras (§11):** **não** tocar `Mensagens\Lista` / `MensagemController` / a barreira / o single (3B) / o
   resolvedor (3A) / o sitemap / Autores. A 3C é **só** a área pessoal.

---

## 3. Terreno confirmado por leitura (não presumir diferente)

Verificado no código em 2026-07-20 (base `c517b70`). **Docblock não é evidência** — o que segue foi lido no fonte.
Referências relativas a partir de `docs/superpowers/specs/` (`../../../`). **`AbaDirecionadas` e `conta.direcionadas`
NÃO existem** (grep confirmou: o único `direcionadas()` no código é dos leitores de importação, `App\Importacao\
LeitorDirecionadasMensagem*`) ⇒ tudo novo, aditivo.

### 3.1 O backend a CONSUMIR (fonte única da 3A/3B — reusar, não recriar)

- [User::mensagensDirecionadas(): BelongsToMany](../../../app/Models/User.php#L71-L74) — `belongsToMany(Mensagem::
  class, 'mensagem_destinatario', 'user_id', 'mensagem_id')`. **Lado inverso** de `Mensagem::destinatarios()`; é
  **PII** (§9-I3). **⚠️ NÃO filtra status** — puxa o pivô cru (inclui pendentes). ⇒ **a 3C compõe `->publicado()`.**
- [Mensagem::scopePublicado(Builder): Builder](../../../app/Models/Mensagem.php#L72-L75) — `where('status',
  self::STATUS_PUBLICADO)` (status-only, criado na 3B). **Compõe** com `mensagensDirecionadas()` porque `status` só
  existe na tabela `mensagens` (o pivô `mensagem_destinatario` só tem `user_id`/`mensagem_id`) ⇒ o `where('status',
  …)` no JOIN é **inambíguo**; o `BelongsToMany` encaminha o scope local ao model relacionado (`__call` → Builder).
  ⇒ **`$user->mensagensDirecionadas()->publicado()` = as direcionadas publicadas do usuário** (o núcleo da 3C).
- [Mensagem::scopePublica(Builder)](../../../app/Models/Mensagem.php#L64-L69) = `status='publicado' AND
  nivel='publico'` (o filtro FIXO da 2B) — **não** serve à 3C (excluiria as direcionadas, `nivel='direcionada'`).
- [Mensagem::visibilidade(): ?VisibilidadeMensagem](../../../app/Models/Mensagem.php#L81-L84) — accessor derivado
  (usado **dentro** do `x-mensagem.card`/`selo-nivel`; a 3C **não** o chama diretamente).
- **A nota "Direcionada a você" (3B)** — [show.blade.php:95-103](../../../resources/views/mensagens/show.blade.php#L95-L103):
  `@if ($ehDestinatario ?? false)` (o flag é calculado no `MensagemController@show` da 3B). ⇒ quando um card da 3C
  linka a `mensagens.show`, o destinatário **já** passa o gate da 3B (`podeSerVistoPor` via `destinatarios`) e vê a
  mensagem + a nota. **A 3C não precisa mexer nisso** — herda de graça.

### 3.2 Os MOLDES de `/minha-conta` (recriar por analogia — evidência exata)

**Acesso — [AbaAgenda](../../../app/Support/Conta/AbaAgenda.php) (molde de `AbaDirecionadas`):**
- `visivelPara(User): bool` **memoizado por request** via `WeakMap` indexado pelo objeto `User`
  ([:29-36](../../../app/Support/Conta/AbaAgenda.php#L29-L36)) — a nav renderiza em **toda** página `/minha-conta`, e
  `auth()->user()` devolve a **mesma** instância no request; `WeakMap` não sofre reuso de `spl_object_id`.
- `calcular(User): bool` ([:38-45](../../../app/Support/Conta/AbaAgenda.php#L38-L45)) — a Agenda usa
  `checkPermissionTo('agenda.ver')` + `AcessoPorTipo`. **⚠️ A 3C NÃO copia esse critério** (§2-5): a Direcionada é
  por **pertencimento**, não por capacidade ⇒ o `calcular` da 3C é `$user->mensagensDirecionadas()->publicado()
  ->exists()`. (O comentário do `AbaAgenda` sobre `checkPermissionTo` vs `hasPermissionTo` **não se aplica** — a 3C
  não consulta permission alguma.)

**Rota — [routes/web.php:42-46](../../../routes/web.php#L42-L46):** o grupo
`Route::middleware('auth')->prefix('minha-conta')->name('conta.')->group(...)` com `painel` (`/`), `perfil`
(`/perfil`), `agenda` (`/agenda`). ⇒ **anônimo é redirecionado ao login pelo middleware `auth`** (nada renderiza sem
sessão). A 3C **acrescenta** `Route::get('/direcionadas', [ContaController::class, 'direcionadas'])
->name('direcionadas')` **dentro** desse grupo.

**Controller — [ContaController](../../../app/Http/Controllers/ContaController.php):** `agenda()`
([:38-43](../../../app/Http/Controllers/ContaController.php#L38-L43)) = `abort_unless(AbaAgenda::visivelPara(auth()
->user()), 403); return view('conta.agenda');`. `painel()`/`perfil()`
([:14-36](../../../app/Http/Controllers/ContaController.php#L14-L36)) = `view(..., compact(...))` (**sem** Livewire).
⇒ o `direcionadas()` da 3C segue o molde de `painel`/`perfil` (view + `compact`) com o `abort_unless` de `agenda`.

**Nav — [components/conta/nav.blade.php](../../../resources/views/components/conta/nav.blade.php):**
`@props(['ativo' => 'painel'])`; monta `$itens` com `['chave','rotulo','rota']`
([:4-7](../../../resources/views/components/conta/nav.blade.php#L4-L7)) e **empurra condicionalmente** o item Agenda
dentro de `if (AbaAgenda::visivelPara(auth()->user()))`
([:8-10](../../../resources/views/components/conta/nav.blade.php#L8-L10)); o loop marca `aria-current` e a cor ativa
por `chave`. ⇒ a 3C acrescenta um `if (AbaDirecionadas::visivelPara(...)) $itens[] = ['chave'=>'direcionadas',
'rotulo'=>'Minhas Direcionadas', 'rota'=>'conta.direcionadas'];`.

**Layout — [components/layout/conta.blade.php](../../../resources/views/components/layout/conta.blade.php):**
`@props(['titulo' => null, 'ativo' => 'painel'])`; **repassa os slots** `headTop`/**`head`**/`scripts` ao
`x-layout.app` ([:6-8](../../../resources/views/components/layout/conta.blade.php#L6-L8)); renderiza `x-conta.nav
:ativo` + `$slot`. ⇒ **o `<meta name="robots">` da 3C entra via `<x-slot:head>`** (o layout já o encaminha).

**View — [conta/agenda.blade.php](../../../resources/views/conta/agenda.blade.php)** e
[conta/painel.blade.php](../../../resources/views/conta/painel.blade.php): `<x-layout.conta titulo="…" ativo="…">`;
a `agenda` injeta Livewire (não é o caso da 3C — read-only); a `painel`
([:23-27](../../../resources/views/conta/painel.blade.php#L23-L27)) itera com `@forelse … @empty` (molde do **estado
vazio**). ⇒ a 3C é uma view **estática** `conta/direcionadas.blade.php` (grid de cards + estado vazio).

**Card — [components/mensagem/card.blade.php](../../../resources/views/components/mensagem/card.blade.php):**
`@props(['mensagem', 'variante' => 'lista'])` ([:1](../../../resources/views/components/mensagem/card.blade.php#L1)).
O card inteiro é `<a href="{{ route('mensagens.show', $mensagem->slug) }}">`
([:17](../../../resources/views/components/mensagem/card.blade.php#L17)); o **badge de nível** (que rotula
"Direcionada" para essas mensagens) sai via `@auth <x-mensagem.selo-nivel :visibilidade="$mensagem->visibilidade()"/>
@endauth` ([:34](../../../resources/views/components/mensagem/card.blade.php#L34)); a faixa superior é
`@auth …?->cor()… @endauth` (null-safe, [:19-23](../../../resources/views/components/mensagem/card.blade.php#L19-L23)).
Na 3C o viewer **está sempre logado** (rota auth-gated) ⇒ `@auth` é sempre verdadeiro ⇒ o badge Direcionada aparece.
- **`variante`:** `'lista'` (sem miniatura) é usada na lista pública
  ([livewire/mensagens/lista.blade.php:98](../../../resources/views/livewire/mensagens/lista.blade.php#L98));
  **`'perfil'`** (COM miniatura de pictografia, trecho 3 linhas, data mono dourada) é usada em
  [autores/show.blade.php:158](../../../resources/views/autores/show.blade.php#L158) — o card "rico" de área pessoal.
  ⚠️ O card `variante='perfil'` chama `getFirstMediaUrl('pictografia','web')` **só nos itens de formato Pictografia**
  ([:11-13](../../../resources/views/components/mensagem/card.blade.php#L11-L13); gate `$perfil && formato===Pictografia`)
  ⇒ **eager-load de `media`** evita N+1 nesses itens (§6.5/O1).

**Teste-molde — [tests/Feature/Conta/AbaAgendaTest.php](../../../tests/Feature/Conta/AbaAgendaTest.php):** `setUp`
seeda `EstruturaCemaSeeder` + `TiposConteudoSeeder`; helpers `editorDe(sigla)`; asserts diretos de
`AbaAgenda::visivelPara(...)` (true/false) + um teste Livewire de `assertForbidden` no 2º portão. ⇒ molde do
`AbaDirecionadasTest` (asserts de `visivelPara` + `get(route('conta.direcionadas'))->assertOk()/assertForbidden()`).

### 3.3 Dimensão do dado (medida na main, base `c517b70`)

**73 vínculos / 15 mensagens direcionadas / 17 usuários** com direcionada; média **4,3** por usuário, **MAX 14**.
⇒ lista **CURTA**: listagem simples ordenada por data, **SEM** a máquina de filtros/paginação da lista pública
(máx 14 cabe numa página; **sem paginação** — O2 fechado, §6.4). (Reconferir os números no dev antes do plano — a curadoria
F4 pode ter mudado; o comportamento **não** depende do número, só o dimensionamento.)

---

## 4. Estudo do handoff (o que se reusa ↔ o que se corta)

`design_handoff_mensagens_lista/` (README + protótipo `.dc.html` + screenshots) é **referência visual**, **não**
código. O handoff descreve a lista **pública** com um **"modo Minhas mensagens direcionadas"** embutido — mas o
dono **tirou o modo da lista** (F1) e o pôs numa **aba própria** no `/minha-conta`. Da anatomia do handoff, a 3C
reaproveita **só**:

- **Card "Área pessoal"** (README §4.6-7 / §6 — o card rico com miniatura) → o `x-mensagem.card variante='perfil'`
  **já existente** (não recriar).
- **Aviso "Área pessoal"** (README §4.4 — card creme "Estas mensagens foram endereçadas a você…") → um **cabeçalho
  explicativo** da aba (card creme, molde da nota da 3B), **SEM** a lista de destinatários (F2).

**CORTAR (F5 — não existe/não criar):** ícones **lida/não-lida** (envelopes) nos cards e a legenda lida/não-lida
(dependem do pivô `mensagem_lidas`, **inexistente**); favoritar; "vistas recentemente". **CORTAR (fica na lista
pública, não na aba):** os filtros (De/Até/Autor), o toggle grade/lista, o select de ordenação, a legenda de níveis
de acesso (a aba é **só** direcionadas — um único nível). **Sem** hero navy imersivo: a aba vive dentro do
`x-layout.conta` (cabeçalho simples da área do membro).

---

## 5. Invariantes (cada um vira teste que reprova)

| # | Invariante | Teste (§9) |
|---|---|---|
| **I1** | **Aba/rota condicional por PUBLICADA:** usuário com **≥1 direcionada publicada** vê a aba (`AbaDirecionadas::visivelPara`=true) **e** a rota responde **200**; usuário **sem** direcionada — **ou só com pendente/despublicada** — **não** vê a aba (=false) **e** a rota dá **403** (`abort_unless`). | §9.1/§9.2/§9.3 |
| **I2** | **Só as DELE, só publicadas:** a lista mostra **exatamente** `mensagensDirecionadas()->publicado()` do usuário logado; a direcionada de **OUTRO** usuário **nunca** aparece (PII não vaza), e uma **pendente** dele **também não**. | §9.2 |
| **I3** | **Sem destinatários no HTML (F2/PII):** o HTML da aba **não** contém nome/e-mail de **nenhum** outro destinatário (o card só mostra autores espirituais + título + data); grep no HTML renderizado. | §9.2 |
| **I4** | **`noindex` + `@auth`:** anônimo em `conta.direcionadas` → **redirect ao login** (middleware `auth`), nunca 200; a view autenticada emite `<meta name="robots" content="noindex, nofollow">`. | §9.2 |
| **I5** | **Read-only:** a aba **não** tem nenhuma ação de mutação (form/POST/Livewire de escrita); é `view`+`compact`, como `painel`/`perfil`. | §9.2 (ausência) |
| **I7** | **Blindagem por nível (O5):** a aba **e** a lista compõem `publicado()->where('nivel', Direcionada)`. Uma mensagem **publicada de OUTRO nível** (ex.: `trabalhadores`) vinculada ao user no pivô **não** acende a aba nem aparece na lista (exercita o `where nivel`); uma **pendente** direcionada dele **também não** (exercita o `publicado()`). Os **dois filtros** ficam provados, não por premissa. | §9.1/§9.2 |
| **I6** | **Lista pública INTACTA:** `/mensagens-mediunicas` (`Mensagens\Lista`), o single, a barreira (3B), o resolvedor (3A), o sitemap e Autores **não** mudam de comportamento — a suíte 2B/3B/3A permanece **verde** (a 3C é puramente aditiva). | §9.4 |
| **I-reg** | **Neutralidade + suíte:** nenhuma regressão; suíte **~1063 + novos**, verde; `Pint` verde. **Sem migration.** | §9.4 |

---

## 6. Decisões de desenho

### 6.1 `App\Support\Conta\AbaDirecionadas` (o portão — molde `AbaAgenda`, critério simples)

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-20

namespace App\Support\Conta;

use App\Enums\VisibilidadeMensagem;
use App\Models\User;
use WeakMap;

/**
 * Fonte única do acesso à aba/rota "Minhas Direcionadas" no /minha-conta.
 * Aba visível ⇔ o usuário é destinatário de ≥1 mensagem DIRECIONADA PUBLICADA (pertencimento, não
 * capacidade): pendente (curadoria F4) OU vínculo a mensagem de outro nível NÃO conta (blindagem O5).
 * Memoizada por request via
 * WeakMap (a nav renderiza em toda página /minha-conta; auth()->user() é a mesma instância no request).
 */
class AbaDirecionadas
{
    private static ?WeakMap $cache = null;

    public static function visivelPara(User $user): bool
    {
        self::$cache ??= new WeakMap;

        return self::$cache[$user] ??= $user->mensagensDirecionadas()
            ->publicado()
            ->where('nivel', VisibilidadeMensagem::Direcionada->value)
            ->exists();
    }
}
```

- **Critério por PERTENCIMENTO** (§2-5): `mensagensDirecionadas()->publicado()->where('nivel', Direcionada)
  ->exists()`. **Não** usa `checkPermissionTo`/`AcessoPorTipo` (a Direcionada não é capacidade). O `publicado()`
  garante que uma pendente não acende a aba; o `where('nivel', Direcionada)` é a **blindagem O5** (só direcionadas
  contam, mesmo que o pivô um dia guarde vínculo a mensagem de outro nível). Importar `App\Enums\VisibilidadeMensagem`.
- **⚠️ PONTO TÉCNICO (o núcleo da 3C):** `mensagensDirecionadas()` **não** filtra status
  ([User.php:71-74](../../../app/Models/User.php#L71-L74)); é `->publicado()` (scope da 3B, [Mensagem.php:72-75])
  que remove pendentes/despublicadas. **Todo** consumo (aba **e** listagem) usa `->publicado()`.

### 6.2 Rota + `ContaController@direcionadas` (molde `@agenda`/`@painel` — sem Livewire)

**Rota** (dentro do grupo existente [routes/web.php:42-46](../../../routes/web.php#L42-L46), junto de
painel/perfil/agenda):
```php
Route::get('/direcionadas', [ContaController::class, 'direcionadas'])->name('direcionadas');
```

**Controller** (molde do `@agenda` para o portão + `@painel`/`@perfil` para o `view`+`compact`):
```php
public function direcionadas(): View
{
    $user = auth()->user();
    abort_unless(AbaDirecionadas::visivelPara($user), 403);

    $direcionadas = $user->mensagensDirecionadas()
        ->publicado()
        ->where('nivel', VisibilidadeMensagem::Direcionada->value)   // blindagem O5: só direcionadas (§9/I7)
        ->with('autores', 'media')          // eager-load: autor (card) + media (miniatura pictografia) — sem N+1
        ->orderByDesc('data_recebimento')
        ->get();

    return view('conta.direcionadas', compact('direcionadas'));
}
```

- **`abort_unless(…, 403)`** cobre o logado-sem-direcionada (I1); o **anônimo** nunca chega (middleware `auth`, I4).
- **`->where('nivel', VisibilidadeMensagem::Direcionada->value)`** = **blindagem O5** (importar
  `App\Enums\VisibilidadeMensagem` no controller); `nivel` é inambíguo no JOIN (só existe em `mensagens`). **Mesma
  cadeia da aba** (§6.1) — os **dois filtros** (`publicado()` + `nivel`) são provados pelo teste-contrato (§9/I7).
- **`orderByDesc('data_recebimento')`** — `data_recebimento` só existe em `mensagens` (inambíguo no JOIN); a coluna é
  `nullable` (NULLs vão ao fim no `DESC` — cosmético).
- **`->with('autores', 'media')`** — o card (`variante='perfil'` — **O1 fechado**, §6.4) lê `$mensagem->autores` e
  `getFirstMediaUrl('pictografia','web')`; eager-load evita N+1 (CLAUDE.md-performance).
- **Sem paginação** (**O2 fechado**; §3.3: máx 14 por usuário cabe numa página; `->get()` simples).

### 6.3 Nav — item condicional (molde do bloco Agenda)

Em [nav.blade.php](../../../resources/views/components/conta/nav.blade.php), após o `if` da Agenda
([:8-10](../../../resources/views/components/conta/nav.blade.php#L8-L10)):
```php
if (\App\Support\Conta\AbaDirecionadas::visivelPara(auth()->user())) {
    $itens[] = ['chave' => 'direcionadas', 'rotulo' => 'Minhas Direcionadas', 'rota' => 'conta.direcionadas'];
}
```
O loop existente ([:14-21](../../../resources/views/components/conta/nav.blade.php#L14-L21)) já cuida de
`aria-current`/cor-ativa por `chave` — **nenhuma** outra mudança no componente.

### 6.4 View `conta/direcionadas.blade.php` (read-only — molde `conta/painel`)

```blade
{{-- Thiago Mourão — https://github.com/MouraoBSB — 2026-07-20 --}}
<x-layout.conta titulo="Minhas Mensagens Direcionadas" ativo="direcionadas">
    <x-slot:head><meta name="robots" content="noindex, nofollow"></x-slot:head>

    {{-- Cabeçalho "Área pessoal" (card creme; SEM lista de destinatários — F2). --}}
    <section class="mb-6 rounded-lg …creme… ">
        <h2>Minhas mensagens direcionadas</h2>
        <p>Mensagens endereçadas pessoalmente a você nas reuniões mediúnicas da Casa.</p>
    </section>

    @forelse ($direcionadas as $mensagem)
        {{-- (grade) --}}
    @empty
        {{-- estado vazio (molde painel :26) --}}
    @endforelse
</x-layout.conta>
```
- **`<x-slot:head>` com `noindex, nofollow`** (I4) — o `x-layout.conta` repassa o slot `head`
  ([:7](../../../resources/views/components/layout/conta.blade.php#L7)). `"noindex, nofollow"` por simetria com as
  restritas da 3B.
- **Grade** = `@forelse` sobre `$direcionadas` → `<x-mensagem.card :mensagem="$mensagem" variante="perfil"
  wire:key…não…/>` (é Blade estático, **sem** `wire:key`). Grid responsivo `auto-fill minmax(280px,1fr)` (molde da
  lista pública) ou o grid de cards do `painel`. **Estado vazio** improvável (a aba só aparece com ≥1), mas o
  `@empty` degrada com o card tracejado (defesa contra corrida: uma direcionada despublicada entre render-da-nav e
  clique).
- **Cabeçalho creme** reaproveita a copy da nota "Direcionada a você" (3B) / do aviso "Área pessoal" do handoff —
  **sem** PII (F2).

### 6.5 A11y, responsivo, performance (guardrails herdados)

- **Mobile-first:** a grade colapsa 3→2→1; o `x-layout.conta` já é responsivo (nav vira barra rolável no mobile).
- **A11y:** o `x-mensagem.card` já é acessível (link único, foco visível, badge textual + `aria-hidden` no
  ponto/faixa); o cabeçalho é `<section>`/`<h2>` semântico.
- **Performance/SEO:** SSR; **eager-load** `->with('autores','media')` (sem N+1); **sem** paginação (lista curta);
  `noindex` (privado). A página é auth-gated ⇒ **não** cacheável por proxy por padrão (sessão); sem necessidade de
  `Cache-Control` explícito (diferente do single restrito público-URL da 3B).

---

## 7. As peças (inventário)

**Novos (cabeçalho de autoria no PHP/Blade — CLAUDE.md §8):**
`app/Support/Conta/AbaDirecionadas.php` ·
`app/Http/Controllers/ContaController.php` (**+método `direcionadas`** — edição, mas o arquivo é existente) ·
`resources/views/conta/direcionadas.blade.php` ·
`tests/Feature/Conta/AbaDirecionadasTest.php` (+ um `MinhasDirecionadasTest` de controller, §9).

**Editados (aditivo/cirúrgico):**
`routes/web.php` (**+1 rota** no grupo `conta.`) ·
`app/Http/Controllers/ContaController.php` (**+método `direcionadas`**) ·
`resources/views/components/conta/nav.blade.php` (**+item condicional**).

**NÃO toca (fronteiras, §11):** `Mensagens\Lista` · `MensagemController` · a **barreira**/`mensagens/barreira.blade`
· o **single** `mensagens/show.blade` (a nota "Direcionada a você" já existe — só é **atravessada** pelo link do
card) · o **resolvedor** da 3A (`visiveisPara`/`podeSerVistoPor`/`visibilidade`/enum) · `scopePublica`/`scopePublicado`
(só **consome** o `publicado()`) · o **sitemap** · **Autores** inteiro · o pivô `mensagem_destinatario` (só **lê**) ·
núcleo de capacidade (policies/matriz/Resources) · `x-mensagem.card`/`selo-nivel` (só **usa**) · **sem migration**.

---

## 8. Cutover (o que roda no deploy — do dono)

A 3C **não tem migration nem seeder** (o pivô veio da 3A; os 73 vínculos já foram importados no cutover da 3A).
Deploy padrão de front:
1. `git pull` (código) — sem novas dependências Composer.
2. `npm run build` (Vite, **no host** — o container não tem Node, [[npm-vite-no-host]]) — há Blade/CSS novo (a grade).
3. `php artisan optimize:clear` (route/view) + `docker compose restart app worker`
   ([[dev-opcache-restart-app-worker]]) — o `route:clear` publica a nova rota `conta.direcionadas`.

**Pré-requisito (já satisfeito na 3A):** os vínculos de `cema:importar-direcionadas` precisam estar populados; caso
a produção ainda não tenha rodado o cutover da 3A, rodar (`migrate` → `cema:importar-direcionadas`) antes.

**Ciência:** a partir da 3C, um destinatário logado passa a ter, no `/minha-conta`, o **índice** das suas
direcionadas — cada uma abrindo no single (onde ele já tinha acesso pela 3B). Nada muda para anônimo ou não-
destinatário.

---

## 9. Plano de teste (TDD real, vermelho primeiro)

Feature tests usam `Tests\TestCase` + `RefreshDatabase`, `EstruturaCemaSeeder`, e as factories da 2A/3A
(`Mensagem::factory()->publica()`/`->pendente()`/`->comNivel('direcionada')`; `destinatarios()->attach($user)` para
vincular — molde `MensagemVisibilidadeAcessoTest`/`MensagemDestinatarioTest` da 3A). **Todo brief de subagente que
rode `artisan` DEVE proibir `migrate:fresh/refresh/wipe/reset` + seed destrutivo** e reafirmar `legado` read-only.

### 9.0 Ordenação (constraint)
`AbaDirecionadas` (§6.1) **antes** da rota/controller/nav que a consomem. Sequência: `AbaDirecionadas` (I1/I2/I7) →
rota + controller (I2/I4/I5/I7) → nav + view (I1/I3) → regressão (I6/I-reg). O **teste-contrato da blindagem O5 (I7)**
entra junto de cada superfície (aba na §9.1, lista na §9.2).

### 9.1 `AbaDirecionadasTest` (unidade — molde `AbaAgendaTest`) — I1
- destinatário de **1 publicada** → `visivelPara`=**true**;
- usuário **sem** direcionada → **false**;
- destinatário só de uma **pendente/despublicada** → **false** (o `publicado()` a exclui — **o ponto técnico**);
- destinatário só de uma **publicada de OUTRO nível** (ex.: `->comNivel('trabalhadores')`) vinculada no pivô →
  **false** (o `where('nivel', Direcionada)` a exclui — **blindagem O5/I7**);
- destinatário de outro usuário **não** conta (o pivô é por `user_id`);
- **não estoura** sem seed de permissão (a 3C não consulta permission — critério simples).

### 9.2 `MinhasDirecionadasTest` (controller/rota) — I1/I2/I3/I4/I5
- **destinatário com publicada** → `get(route('conta.direcionadas'))` **200**; `assertSee` do título da **sua**
  publicada (fixture `Mensagem::factory()->comNivel('direcionada')` — nasce `STATUS_PUBLICADO`; **não** `->publica()`,
  que forçaria `nivel=publico`); **`assertDontSee`** do título de **(a)** uma direcionada **publicada** de **OUTRO**
  usuário — vinculada a **esse outro `user_id`**, para exercitar o **filtro por `user_id`** (não só o `publicado()`;
  se seedada como pendente, um bug de "não filtra por `user_id`" passaria despercebido) — **(b)** uma **pendente**
  dele (`->comNivel('direcionada')->pendente()`), para exercitar o `publicado()`; **e (c)** uma **publicada de OUTRO
  nível** dele (`->comNivel('trabalhadores')`, vinculada no pivô), para exercitar o `where('nivel', Direcionada)` —
  **blindagem O5** (I2/I7); **`assertDontSee`** do nome/e-mail de qualquer outro destinatário (I3/PII);
  `assertSee('name="robots"', false)` = `noindex` (I4);
- **logado sem direcionada** (ou só com pendente) → **403** (I1, `assertForbidden`);
- **anônimo** → **redirect** para `route('login')` (I4, `assertRedirect`), **nunca** 200;
- **ordenação** por `data_recebimento` desc (asserção de ordem entre 2 datas);
- **read-only** (I5): a resposta **não** contém `<form method="POST">` nem componente Livewire de mutação
  (asserção de ausência — **guarda anti-regressão fraco**: a view nasce sem form, então passa trivialmente; a
  garantia **real** de read-only vem de **não haver rota/método de mutação** (§11), não deste assert).

### 9.3 `NavDirecionadasTest` (Blade/integração) — I1 (nav)
Renderizar `/minha-conta` como destinatário-com-publicada → a nav mostra o item **"Minhas Direcionadas"** com
`route('conta.direcionadas')` (`assertSee`, false); como usuário-sem-direcionada → o item **não** aparece
(`assertDontSee`). (Molde da forma como `AbaAgenda` acende o item Agenda.)

### 9.4 Regressão + neutralidade + suíte
Baseline **~1063** (`--list-tests`); alvo **~1063 + novos**, verde. **Nenhum** teste 2B/3B/3A muda de cor (a 3C é
aditiva — I6): rodar a suíte de Mensagens completa e confirmar verde. **Conferir no localhost:** `npm run build` +
`restart app worker` + logar como um destinatário real (dev tem 17) e navegar `/minha-conta` → aba → card → single
(ver a nota "Direcionada a você"); logar como usuário **sem** direcionada e confirmar **ausência** da aba + **403**
na rota direta. `Pint` verde ([[pint-antes-de-push]], [[flaky-importadorblog-gd-cap-imagem]]).

---

## 10. Fora de escopo (F4/F5 e o resto — não fazer agora)

- **F4 (curadoria):** médium **cria** direcionada, diretor-DEPAE ratifica/**publica**, campo destinatários no
  `/admin` (a lista PII vive lá). A 3C é **só leitura** do que já está publicado.
- **F5 (engajamento):** **lida/não-lida** (pivô `mensagem_lidas` — **não** criar), favoritar, "vistas
  recentemente". O handoff mostra; a 3C **corta**.
- **Modo na lista pública:** a `Mensagens\Lista` **não** vira dual-mode (F1 fechado — a navegação das direcionadas
  é a **aba**, não um modo na lista).
- **Filtros/ordenação/toggle na aba:** a lista é curta (máx 14) — sem a máquina de filtros da lista pública.
- **Dark mode:** site é só claro.

---

## 11. Fronteiras: o que toca × o que NÃO toca

**Toca (novo):** `AbaDirecionadas` · `conta/direcionadas.blade.php` · testes.
**Toca (edição cirúrgica):** `routes/web.php` (+1 rota no grupo `conta.`) · `ContaController` (+`direcionadas`) ·
`components/conta/nav.blade.php` (+item condicional).
**NÃO toca:** `Mensagens\Lista` · `MensagemController` · barreira/single (3B) · resolvedor (3A) · `scopePublica`/
`scopePublicado`/`nivel`/`casts` · sitemap · **Autores** inteiro · núcleo de capacidade (policies/matriz/Resources) ·
`x-mensagem.card`/`selo-nivel` (só usa) · importação · o pivô `mensagem_destinatario` (só lê). **Sem migration.**
**Sem mudança de comportamento nas superfícies existentes** (aditiva pura) — nenhum teste existente muda de cor.

---

## 12. Ciências (não são tarefa desta fatia)

- **A aba lista títulos de direcionadas** (conteúdo endereçado); por isso `noindex` + auth-gate. **Não** lista
  destinatários (F2) — o único traço de "para quem" é o implícito "são as minhas".
- **`mensagensDirecionadas()` é PII** (o pivô inverso): a 3C só o usa para o **próprio** usuário logado
  (`auth()->user()`), nunca para terceiros — não há vazamento cruzado.
- **A composição `mensagensDirecionadas()->publicado()` depende de `status` ser inambíguo no JOIN** (só existe em
  `mensagens`, não no pivô). Se um dia o pivô ganhar uma coluna `status`, o scope precisaria qualificar
  `mensagens.status` — hoje **não** é o caso (verificado). O teste I2 (pendente não aparece) trava esse contrato.
- **A nota "Direcionada a você" (3B)** aparece **de graça** no single ao chegar pelo card da 3C — a 3C não a
  reimplementa; só provê o link.
- **`variante='perfil'` vs `'lista'`** é a única escolha visual real (O1); ambas linkam ao single e mostram o badge
  Direcionada `@auth` — a diferença é a miniatura de pictografia (que exige eager-load de `media`).
- **Blindagem O5 (FECHADA — implementada):** a composição filtra `publicado()->where('nivel', Direcionada)` na
  **aba e na lista** (§6.1/§6.2). Isso casa a semântica "direcionada" com o vínculo do pivô: hoje só direcionadas o
  populam (import rel 38 reversa; não há UI que anexe destinatário a outra mensagem), mas mesmo que um dia guarde
  vínculo a uma publicada de outro nível, ela **não** entra na aba/lista (nem levaria um não-autorizado à barreira da
  3B ao clicar). O **teste-contrato** (§9.1/§9.2/I7) prova os **dois** filtros; `nivel` é inambíguo no JOIN (só em
  `mensagens`, como `status`/`data_recebimento`).

---

## 13. Passes adversariais — próprio (5 verificadores) + Consultor (✅ APROVADA); O1–O5 FECHADOS

> **Passe interno rodado antes da entrega:** leitura direta dos moldes (`AbaAgenda`, `ContaController`,
> `nav.blade.php`, `x-layout.conta`, `x-mensagem.card`, `User::mensagensDirecionadas`, `Mensagem::scopePublicado`,
> a nota "Direcionada a você" da 3B) — todos com **evidência `arquivo:linha`** (§3). `AbaDirecionadas`/
> `conta.direcionadas` **confirmados inexistentes** (grep). A composição `mensagensDirecionadas()->publicado()` foi
> raciocinada contra o JOIN real (`status` inambíguo — a migration do pivô `mensagem_destinatario` só tem
> `mensagem_id`/`user_id`, verificada). Depois, **5 verificadores adversariais paralelos** (terreno · técnica ·
> forks/fronteiras · cobertura · molde) rechecaram cada afirmação contra o código real: **veredito sólido em todas
> as dimensões** — sem bloqueador, sem fork reaberto, sem creep. Os achados menores já estão incorporados (C-E de
> redação da mídia; ref de teste do I4 corrigida p/ §9.2; fixtures não-vacuous no §9.2; a ciência `status`-vs-`nivel`
> no §12/O5).

**Correções que ESTE spec já incorpora:**
- **C-A — `mensagensDirecionadas()` NÃO filtra status** ([User.php:71-74]) ⇒ a aba **e** a listagem **precisam** de
  `->publicado()`; sem isso, uma pendente (F4) acenderia a aba e vazaria na lista. (§6.1/§6.2/I1/I2.)
- **C-B — o critério da aba é PERTENCIMENTO, não capacidade** ⇒ `AbaDirecionadas` **não** copia o
  `checkPermissionTo`/`AcessoPorTipo` do `AbaAgenda` (§2-5/§6.1). O comentário `hasPermissionTo`-vs-`checkPermissionTo`
  do `AbaAgenda` **não** se aplica (a 3C não consulta permission).
- **C-C — `variante='perfil'` do card faz `getFirstMediaUrl` por item** ⇒ **eager-load `media`** no controller para
  evitar N+1 (§6.2/§6.5/O1).
- **C-D — anônimo NÃO precisa de barreira** (a rota é auth-gated; o middleware redireciona ao login) — diferente do
  single público-URL da 3B. O `noindex` é **defesa em profundidade** (a página lista títulos), não uma barreira.
- **C-E — `getFirstMediaUrl` do card `perfil` é CONDICIONAL** ao formato Pictografia
  ([card.blade.php:11-13]), não "por item" incondicional; o eager-load de `media` segue correto para esses itens
  (§3.2 ajustado — evita N+1 nos pictográficos).

**Pontos FECHADOS (O1–O5 — resolvidos com o dono no passe do consultor):**
- **O1 — `variante` do card = `'perfil'`** (card rico com miniatura) + eager-load `media` no controller (§6.2).
- **O2 — SEM paginação** (`->get()`; máx 14/user, §3.3/§6.2).
- **O3 — rótulos mantidos:** "Minhas Direcionadas" (nav) / "Minhas Mensagens Direcionadas" (título, §6.4).
- **O4 — cabeçalho creme "Área pessoal"** (copy da nota da 3B), **sem** PII (F2) (§6.4).
- **O5 — BLINDAR por nível (aba E lista):** a composição vira `publicado()->where('nivel',
  VisibilidadeMensagem::Direcionada->value)` nos **dois** lugares (§6.1/§6.2) + **teste-contrato** que prova os dois
  filtros (§9.1/§9.2/I7). `nivel` é inambíguo no JOIN — custo zero.
- **Regra sempre:** pt-BR em tudo; cabeçalho de autoria no PHP/Blade novo; `Pint` antes do push; `docker compose
  exec -T app php artisan test`; `npm run build` **no host**; **todo brief de subagente que rode `artisan` DEVE
  proibir `migrate:fresh/refresh/wipe/reset` e seed destrutivo** e reafirmar `legado` read-only
  ([[nunca-migrate-fresh-no-dev]]).

---

### Passe adversarial do CONSULTOR (20/jul) — veredito: ✅ APROVADA, zero bloqueador

O Consultor verificou o terreno contra a main (`c517b70`): a composição `mensagensDirecionadas()->publicado()` foi
**provada rodando a query real** — SQL `… where mensagem_destinatario.user_id = ? and status = ? order by
data_recebimento desc`, **sem ambiguidade** (`status`/`data_recebimento` só existem em `mensagens`; o pivô só tem
`mensagem_id`/`user_id`). A `MensagemFactory` sustenta os fixtures (`comNivel('direcionada')` nasce publicada).
`AbaDirecionadas`/`conta.direcionadas` **confirmados inexistentes** (sem colisão). Moldes e linhas **batem**.

**O1–O5 fechados com o dono** (acima). O único ajuste de conteúdo do passe do consultor é o **O5** (blindar por
`nivel` na aba **e** na lista + teste-contrato) — **já incorporado** neste spec (§2-5 · §5-I7 · §6.1 · §6.2 · §9.1 ·
§9.2 · §12).

**Destino:** **PLANO** (TDD real, molde das fatias anteriores; ordem do §9.0: `AbaDirecionadas` → rota+controller →
nav+view → **blindagem O5 (I7)** → regressão), cobrindo I1–I7 e o teste-contrato do O5 (confirmando que o teste da
aba condicional **reprova sem o guard** — não vacuous). O Consultor fará o **passe do plano** antes da execução.
**Sem migration nesta fatia.**

---
