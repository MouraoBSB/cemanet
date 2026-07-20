# Spec — Camada 4 · Fatia 3A · Backend da visibilidade rica das Mensagens (comportamento-neutro)

> Autoria: Thiago Mourão — https://github.com/MouraoBSB — 2026-07-19
> Enquadramento travado com o dono no kickoff da Fatia 3A. Este spec **não** improvisa além das decisões
> travadas; **cada afirmação sobre o terreno foi verificada contra o código real** (evidência `arquivo:linha`
> no §3) e o **legado foi medido AO VIVO** pela conexão `legado` (túnel SSH ativo, somente `SELECT`, §4).
> Destino: **SPEC** — vai ao passe adversarial **antes** de virar plano de implementação. **NÃO implementar ainda.**
> Base: `origin/main` (HEAD `b7f9402`, PR #38 — **Fatia 2B mesclada**; a Fatia 3A ramifica daqui). Suíte baseline:
> **~1011 testes** (a `MEMORY.md` registra 1011 pós-2B; medir com `docker compose exec -T app php artisan test
> --list-tests` antes de começar).
> Fundação: o **modelo de capacidades** (VISIBILIDADE × CAPACIDADE), a **Fatia 2A** (`Mensagem` entidade+dados:
> `nivel` string BRUTA, `scopePublica` fixo, enum `FormatoMensagem`, pivôs `autores`/`relacionadas`) e a **Fatia 3
> de Eventos** (`VisibilidadeEvento` + `Evento::podeSerVistoPor`/`scopeVisiveisPara` + `EventoPolicy` de dois eixos)
> — o **molde exato** que a 3A clona e **estende** com 3 recortes laterais e um bypass explícito.

---

## 0. Recorte: por que esta é a fatia "3A" (e o que fica na 3B/F4/F5)

A Fatia 3 (Visibilidade rica das Mensagens) foi partida em duas, como a 2A/2B:

- **3A (ESTE spec):** o **BACKEND** da visibilidade — o enum de 6 níveis, o **resolvedor** no model
  (`podeSerVistoPor`/`scopeVisiveisPara`), o **pivô Direcionada** (N:N mensagem↔user, PII), a **importação dos 15
  direcionamentos** do legado, e a **Policy de VER** (`view`/`viewAny`). É **COMPORTAMENTO-NEUTRO no site:** o
  resolvedor **nasce mas NINGUÉM o consome ainda**. O front público segue com `Mensagem::publica()` (filtro FIXO da
  2B) até a 3B. Prova por **TESTE DE UNIDADE** (`Gate::forUser` + `scopeVisiveisPara`/`podeSerVistoPor` diretos por
  persona), **não** pela tela — padrão do E1 (Camada 1), da 2A e do próprio `VisibilidadeEventoAcessoTest`.
- **3B (depois):** o **FRONT** — lista rica (badges/níveis/legenda/"minhas direcionadas"), barreira de login
  inline, `noindex` condicional, religar o menu, trocar `publica()` por `visiveisPara($user)` nas páginas. **NÃO é
  desta fatia.** A 3A **não toca** nenhuma view/rota/Livewire da 2B.

O que a 3A entrega é a **camada de decisão "quem vê"** — inerte no site, provada por teste. Ligar o front é a 3B.

---

## 1. Contexto e objetivo

A Camada 4 é o módulo **Mensagens Mediúnicas**. A 2A criou a entidade e migrou as 179 mensagens com o `nivel`
como **string crua** (o slug da taxonomia `nivel-de-acesso`). A 2B publicou o front **só das Públicas** por um
filtro fixo (`Mensagem::publica()`). Esta **Fatia 3A** entrega o **backend da visibilidade rica**: dado um usuário
(ou anônimo), **decidir quais mensagens ele pode ver** — sem vazar as restritas.

**Objetivo:** criar `App\Enums\VisibilidadeMensagem` (6 níveis, slugs reais do legado — §4), o **resolvedor** no
model `Mensagem` (`podeSerVistoPor(?User)` + `scopeVisiveisPara(Builder,?User)`, molde EXATO do `Evento`), o **pivô
`mensagem_destinatario`** (N:N mensagem↔`User`, é **PII**) com `Mensagem::destinatarios()` e
`User::mensagensDirecionadas()`, a **cadeia de importação idempotente** (`cema:importar-direcionadas`) que traz os
**73 vínculos / 15 mensagens / 17 destinatários** do legado (casados por `origem_legado_id`), e os métodos
`view`/`viewAny` na `MensagemPolicy` (delegando ao resolvedor — molde `EventoPolicy`).

**A regra de visibilidade combina uma ESCADA e três RECORTES laterais** (decisão travada do dono — §2.3):

- **ESCADA** (`>= nivelMaximo` do usuário, `roles.nivel`): **Público** (0) · **Trabalhadores** (20) · **Diretores**
  (30). É a mesma mecânica do `Evento`.
- **3 RECORTES por PERTENCIMENTO** (`orWhere` condicionados a estar-em, **não** a posição na escada):
  - **Médiuns** — o usuário tem o **setor** `Médium` (slug `medium`, DEPAE);
  - **Diretor-DEPAE** — o usuário tem o **cargo** `Diretor do DEPAE` (slug `diretor-do-depae`);
  - **Direcionada** — o usuário está no **pivô** de destinatários **daquela** mensagem.
- **BYPASS total** (vê tudo, inclusive nível `null`): **administrador** (papel nível 100) **ou** **presidente**
  (cargo slug `presidente`).

**Consequência da regra (travada):** um **Médium** vê Médiuns + Trabalhadores + Público; um **Diretor** **NÃO** vê
Médiuns (é recorte, não escada); Presidente/admin veem **tudo**. **Nível `null` = fail-closed** (só bypass vê).

**A Mensagem clona o molde do Evento e o ESTENDE.** O `Evento` tem escada pura (`scopeVisiveisPara` só com
`orWhere` de nível); o admin (nível 100) cobria tudo pela própria escada, **sem** bypass explícito. A Mensagem
introduz **3 recortes de pertencimento** que a escada não expressa — e por isso precisa de um **bypass explícito**
(o nível 100 do admin **não** o torna médium nem destinatário). É o único desvio arquitetural real desta fatia.

---

## 2. Decisões travadas (não reabrir)

Do kickoff da 3A (dono) + heranças da 2A/2B:

1. **Split 3A (backend) / 3B (front)** — esta é a **3A**. O front (barreira modal inline + contato + Direcionada
   cega + `noindex` + religar menu) é **3B** e **não** entra aqui.
2. **Importar as 15 direcionadas do legado** (casar destinatários por chave estável). Legado **READ-ONLY** (só
   `SELECT`). Re-run **não** duplica. User do legado sem correspondente no novo → **IGNORA e loga** (nunca cria user).
3. **Regra de visibilidade (decisão 3 da Camada 4):** escada (Público/Trabalhadores/Diretores) **+** 3 recortes
   (Médiuns/Diretor-DEPAE/Direcionada). **Médium vê Médiuns+Trabalhadores+Público; Diretor NÃO vê Médiuns;
   Presidente/admin veem tudo.**
4. **Comportamento-neutro:** a 3A **só ADICIONA**. A suíte da 2A/2B **não muda de cor**; **nenhuma** view/rota/
   Livewire da 2B é tocada; o front segue `Mensagem::publica()` (fixo). O resolvedor nasce **inerte** (provado por
   unidade).
5. **A 3A é só QUEM VÊ**, nunca quem cria/edita. A **curadoria** (médium cria, diretor-DEPAE ratifica/publica,
   máquina de estados, porta perfil, auditoria) é **F4**. O **engajamento** (favoritar/lida/vistas/curtir) é **F5**.
6. **`nivel` continua BRUTO** na coluna (string do slug legado). A 3A dá a **semântica tipada** por cima, **sem**
   migrar a coluna nem alterar `scopePublica`/`NIVEL_PUBLICO` (a 2B depende deles intactos).
7. **Sem `migrate:fresh`/`refresh`/`wipe`/`reset` nem seed destrutivo** no dev (152 usuários + 179 mensagens + mídia
   + etc.). Só `migrate` incremental ([[nunca-migrate-fresh-no-dev]]).

---

## 3. Terreno confirmado por leitura (não presumir diferente)

Verificado no código em 2026-07-19 (base `b7f9402`). **Docblock não é evidência** — o que segue foi lido no fonte.

### 3.1 O molde EXATO — `VisibilidadeEvento` + `Evento::podeSerVistoPor`/`scopeVisiveisPara` + `EventoPolicy`

- [VisibilidadeEvento](../../../app/Enums/VisibilidadeEvento.php): `enum … : string` com 4 casos
  (`publico`/`logados`/`trabalhadores`/`diretoria`), `nivelMinimo(): int` (**0/10/20/30**,
  [:15-23](../../../app/Enums/VisibilidadeEvento.php#L15-L23)), `rotulo()` ([:25-33](../../../app/Enums/VisibilidadeEvento.php#L25-L33)),
  `opcoes(): array` (value⇒rótulo, [:36-44](../../../app/Enums/VisibilidadeEvento.php#L36-L44)), `cor(): string`
  ([:46-54](../../../app/Enums/VisibilidadeEvento.php#L46-L54)). **Molde direto do `VisibilidadeMensagem`.**
- [Evento::podeSerVistoPor(?User)](../../../app/Models/Evento.php#L60-L70): `$nivel = $usuario?->nivelMaximo() ?? 0;`
  + `match($this->visibilidade)` — cada caso mapeia a um predicado; o admin (nível 100) satisfaz qualquer `>=`.
- [Evento::scopeVisiveisPara(Builder,?User)](../../../app/Models/Evento.php#L73-L89): filtra **no banco**
  (`->where(fn($q)=>…)` com `orWhere` por nível), **não vaza títulos restritos**. **Escada PURA** — **sem** recortes
  laterais. ⇒ os 3 recortes da Mensagem são **novos**; o bypass explícito também (§6.2).
- [EventoPolicy](../../../app/Policies/EventoPolicy.php): **dois eixos** — `view(?User,Evento)` →
  `podeSerVistoPor` ([:30-33](../../../app/Policies/EventoPolicy.php#L30-L33)); `viewAny(?User)` → `true` (a listagem
  filtra pelo scope, [:35-38](../../../app/Policies/EventoPolicy.php#L35-L38)); `ver/criar/editar/excluir` =
  **capacidade** (`hasPermissionTo` + escopo, [:40-58](../../../app/Policies/EventoPolicy.php#L40-L58)). O docblock
  ([:11-19](../../../app/Policies/EventoPolicy.php#L11-L19)) crava: `$user` **nullável** em `view/viewAny`
  (anônimo passa por `Gate::forUser(null)`), o **admin nunca chega às capacidades** (passa antes no `Gate::before`),
  e **Filament não usa strict authorization** ⇒ a policy parcial de visibilidade é segura no `/admin`. ⇒ a 3A
  **acrescenta `view`/`viewAny` à `MensagemPolicy`** sem tocar as 4 de capacidade (§6.5).

### 3.2 A `Mensagem` (2A) — o que já existe e o que a 3A NÃO pode quebrar

[Mensagem](../../../app/Models/Mensagem.php): `$table='mensagens'`; `NIVEL_PUBLICO='publico'`
([:35](../../../app/Models/Mensagem.php#L35)); `scopePublica` = `status='publicado' AND nivel='publico'`
([:63-68](../../../app/Models/Mensagem.php#L63-L68)) — **filtro FIXO, a 2B depende dele; NÃO alterar**. `nivel` está
no `$fillable` como **string crua** ([:49](../../../app/Models/Mensagem.php#L49)) e **NÃO** aparece em `casts()`
([:54-60](../../../app/Models/Mensagem.php#L54-L60)) — só `formato`⇒enum e `liberar_download`⇒bool. `autores()`
([:75-78](../../../app/Models/Mensagem.php#L75-L78)) e `relacionadas()`+`sincronizarRelacionadas()`
([:80-114](../../../app/Models/Mensagem.php#L80-L114)) são o molde de N:N com **chaves explícitas** + escrita
transacional. `departamentos()` ([:70-73](../../../app/Models/Mensagem.php#L70-L73)) (contrato `TemDepartamento`).

⚠️ **Neutralidade (achado C1 — §13):** a suíte **já lê `$m->nivel` como STRING**:
[MensagemTest.php:98](../../../tests/Feature/Models/MensagemTest.php#L98) `assertSame('publico', …->nivel)`;
[ImportadorMensagensTest.php:90](../../../tests/Feature/Importacao/ImportadorMensagensTest.php#L90),
[:100](../../../tests/Feature/Importacao/ImportadorMensagensTest.php#L100) (`assertNull(…->nivel)`),
[:238](../../../tests/Feature/Importacao/ImportadorMensagensTest.php#L238). **Castar `nivel`** para o enum (como o
kickoff pede literalmente) **quebraria essas 4 asserções** (passariam a receber o enum, não `'publico'`/`null`) ⇒
**viola o comportamento-neutro**. ⇒ a 3A **NÃO casta** `nivel`; expõe um **accessor derivado** `visibilidade(): ?VisibilidadeMensagem`
via `tryFrom` (§6.1). Grep confirma **zero** leitura de `->nivel` no front (`resources/views/mensagens`) — só
`scopePublica` usa a coluna (num `where`, imune ao accessor).

### 3.3 O `User` — nível, setores, cargos (as fontes dos recortes)

[User](../../../app/Models/User.php): `nivelMaximo(): int` = `(int) $this->roles->max('nivel')`, **0 sem papel**
([:92-96](../../../app/Models/User.php#L92-L96)); `setores(): BelongsToMany` via `setor_usuario`
`withPivot('funcao','desde')` ([:48-52](../../../app/Models/User.php#L48-L52)); `cargos(): BelongsToMany` via
`cargo_usuario` ([:54-57](../../../app/Models/User.php#L54-L57)); `departamentos()` via `departamento_usuario`
([:59-62](../../../app/Models/User.php#L59-L62)); `origem_legado_id` no `#[Fillable]`
([:24](../../../app/Models/User.php#L24)); `canAccessPanel` = `hasRole('administrador')`
([:31-36](../../../app/Models/User.php#L31-L36)). **A 3A acrescenta** `ehMedium()`, `ehDiretorDepae()`,
`ehPresidente()`, `mensagensDirecionadas()` (§6.4) — tudo aditivo.

### 3.4 As fontes dos recortes — CRAVADAS (glossário + estrutura + dev DB)

[GlossarioUsuarios](../../../app/Importacao/GlossarioUsuarios.php): **PAPEIS** (slug⇒nível)
`frequentador=10,trabalhador=20,diretor=30,administrador=100` ([:10-15](../../../app/Importacao/GlossarioUsuarios.php#L10-L15))
— **bate com `VisibilidadeEvento`**. **SETORES**: `'medium' => ['Médium','DEPAE','membro']`
([:35](../../../app/Importacao/GlossarioUsuarios.php#L35)). **CARGOS**: `'diretor_depae' => ['Diretor do DEPAE','DEPAE',false]`
([:59](../../../app/Importacao/GlossarioUsuarios.php#L59)) e `'diretor_presidente' => ['Presidente',null,true]`
([:62](../../../app/Importacao/GlossarioUsuarios.php#L62)).

[EstruturaCemaSeeder](../../../database/seeders/EstruturaCemaSeeder.php): papéis via `Role::updateOrCreate(['name'=>slug],['nivel'=>…])`
([:20-25](../../../database/seeders/EstruturaCemaSeeder.php#L20-L25)); setores/cargos gravam **`slug = Str::slug($nome)`**
([:39](../../../database/seeders/EstruturaCemaSeeder.php#L39), [:47](../../../database/seeders/EstruturaCemaSeeder.php#L47)).
[Setor](../../../app/Models/Setor.php#L15) e [Cargo](../../../app/Models/Cargo.php#L13) têm `slug` + `nome` + (cargo)
`institucional`. **Confirmado no dev DB (SELECT, 19/jul):** `setores.slug='medium'` (nome `Médium`);
`cargos.slug='diretor-do-depae'` (nome `Diretor do DEPAE`, `institucional=0`); `cargos.slug='presidente'` (nome
`Presidente`, `institucional=1`); `roles` = frequentador 10 / trabalhador 20 / diretor 30 / administrador 100. ⇒ os
predicados usam esses **slugs** (chave estável — §6.4). `User.origem_legado_id` existe (colunas confirmadas).

### 3.5 O `Gate::before` é admin-only ⇒ presidente exige bypass EXPLÍCITO

[AppServiceProvider::boot](../../../app/Providers/AppServiceProvider.php#L71): `Gate::before(fn (User $usuario) =>
$usuario->hasRole('administrador') ? true : null)` — **o único** `Gate::before` do sistema, e cobre **só o admin**.
⇒ o **presidente NÃO passa** pelo Gate; o bypass dele tem de estar **dentro** de `podeSerVistoPor` (chamado por
`MensagemPolicy::view`) **e** dentro de `scopeVisiveisPara` (que **não** passa por Gate nenhum). Por isso o bypass é
`hasRole('administrador') || ehPresidente()`, aplicado nos **dois** pontos (§6.2).

### 3.6 A cadeia de importação-molde + a chave de casamento

- **BIND obrigatório:** [AppServiceProvider::register](../../../app/Providers/AppServiceProvider.php#L40-L47) tem
  **7 binds** de leitor (`LeitorMensagens` em [:46](../../../app/Providers/AppServiceProvider.php#L46)); o
  command/importador type-hintam a **interface**. Sem `bind(LeitorDirecionadasMensagem::class, …Mysql::class)`, o
  comando quebra **em produção** com a **suíte verde** (o teste injeta fake) — **defeito C7** (padrão da 2A/I16).
- **Chave de casamento = `origem_legado_id`:** [ImportadorUsuarios](../../../app/Importacao/ImportadorUsuarios.php#L48)
  `User::where('origem_legado_id', $bruto['origem_id'])` e [:65](../../../app/Importacao/ImportadorUsuarios.php#L65)
  `updateOrCreate(['origem_legado_id'=>…])`; o `origem_id` é o **`wp_users.ID`**
  ([LeitorUsuariosMysql.php:23](../../../app/Importacao/LeitorUsuariosMysql.php#L23)). Setores/cargos são anexados
  por **`sync` keyed em slug** ([:70-87](../../../app/Importacao/ImportadorUsuarios.php#L70-L87)) — o molde de
  construir usuário-persona nos testes.
- **Leitor de relação Jet-molde:** [LeitorMensagensMysql::autoresSlugsDe](../../../app/Importacao/LeitorMensagensMysql.php#L87-L98)
  lê `wp_jet_rel_default` com `rel_id` + `parent_object_id` + JOIN em `wp_posts` — o molde do **SQL da direcionada**
  (rel 38, §4.3/§6.6), adaptado para JOIN em `wp_users` (o child é mensagem; o parent é user).
- **Command-molde:** [ImportarMensagens](../../../app/Console/Commands/ImportarMensagens.php) valida `legado->getPdo()`
  só com o leitor real; resumo com contadores.

### 3.7 Os testes-molde (personas) — o que a 3A clona

[VisibilidadeEventoAcessoTest](../../../tests/Feature/Eventos/VisibilidadeEventoAcessoTest.php): `setUp` semeia os
papéis com nível ([:22-24](../../../tests/Feature/Eventos/VisibilidadeEventoAcessoTest.php#L22-L24)); `usuario(?string $papel)`
→ `null` (anônimo) ou `User::factory()->create()->assignRole($papel)` ([:27-36](../../../tests/Feature/Eventos/VisibilidadeEventoAcessoTest.php#L27-L36));
**matriz `[papel, [esperado por nível]]`** iterando persona×nível sobre `podeSerVistoPor`
([:46-75](../../../tests/Feature/Eventos/VisibilidadeEventoAcessoTest.php#L46-L75) — **nota**: lista **indexada**, não
associativa, para `null` não virar `''`); `scopeVisiveisPara(...)->count()` por persona
([:77-86](../../../tests/Feature/Eventos/VisibilidadeEventoAcessoTest.php#L77-L86)); `Gate::forUser($u)->allows('view', …)`
([:88-96](../../../tests/Feature/Eventos/VisibilidadeEventoAcessoTest.php#L88-L96)). [VisibilidadeEventoTest](../../../tests/Unit/Enums/VisibilidadeEventoTest.php)
é o molde do teste **de enum**. **A 3A estende a matriz** para 8 personas × 6 níveis (§9.2).

---

## 4. Medições no legado (banco vivo, somente `SELECT`, 2026-07-19)

Consultas na conexão `legado` (túnel SSH ativo, container `cema-app`, prefixo `wp_`). **Nenhuma escrita.**

### 4.1 Os 6 níveis da taxonomia `nivel-de-acesso` (RE-MEDIDO ao vivo — confere com a 2A §4.7 e o dev DB)

| slug (backing do enum) | name | mensagens (publish+pending) |
|---|---|--:|
| `publico` | Público | **29** |
| `trabalhadores` | Trabalhadores | **44** |
| `mediuns-trabalhadores` | Médiuns | **33** |
| `diretores` | Diretores | **9** |
| `diretor-depae` | Diretor-DEPAE | **0** (termo ocioso) |
| `direcionada` | Direcionada | **15** |

- **Com termo = 130**; **sem termo = 49** (nível `null` — os 47 pending + 2 publish; ciência 2A §12). Total **179**.
- **0 mensagens com >1 termo** (nível é **escalar**). O dev DB (a importação da 2A) bate 1:1 com esta medição.
- ⚠️ **O slug de "Médiuns" é `mediuns-trabalhadores`** (não `mediuns`) — o kickoff abreviou; o **backing value do
  enum tem de ser o slug REAL** que a 2A gravou na coluna. O `diretor-depae` existe na taxonomia mas está **ocioso**
  (0 mensagens hoje) — o enum o inclui mesmo assim (o admin pode classificar uma mensagem nesse nível pela tela).

### 4.2 Fonte dos recortes — CRAVADA no dev DB (a estrutura seeded)

| recorte | fonte | chave estável (slug) | cardinalidade hoje |
|---|---|---|---|
| **Médiuns** | `setores` (via `setor_usuario`) | `medium` (nome `Médium`, DEPAE) | — |
| **Diretor-DEPAE** | `cargos` (via `cargo_usuario`) | `diretor-do-depae` (`institucional=0`) | — |
| **Presidente** (bypass) | `cargos` | `presidente` (`institucional=1`) | — |
| **admin** (bypass) | `roles` | papel `administrador` (nível 100) | — |

### 4.3 Direcionada — a fonte real (CORRIGE o kickoff **e** a 2A §4.5)

Medido ao vivo em `wp_jet_rel_default` (colunas `rel_id`,`parent_object_id`,`child_object_id`):

| Medida | Valor |
|---|---|
| `rel_id` presentes | **37** (96 = autores) · **38** (73) · **200** (12) |
| **rel 38** — `child_object_id` distintos | **15** — TODOS = os 15 posts com nível `direcionada` (0 fora) |
| **rel 38** — `parent_object_id` distintos | **17** — TODOS em `wp_users` (0 são autores/posts de verdade) |
| **rel 38** — total de linhas (vínculos) | **73** |
| destinatários por mensagem | 11 · 3 · 3 · 9 · 9 · 3 · 3 · 5 · 5 · 3 · 5 · 5 · 1 · 5 · 3 (**~5** média) |
| **17 destinatários com `User` no novo** (`origem_legado_id`) | **17/17 — 0 faltando** (100% vinculável) |

⚠️ **A direção é REVERSA:** na rel 38, o **`parent_object_id` é o USUÁRIO** e o **`child_object_id` é a mensagem
direcionada**. O kickoff acertou o `rel_id=38` e o "~5 users cada", mas **inverteu a direção** (supôs
mensagem→user). ⚠️ **A 2A §4.5 ERROU:** concluiu "rel 38 = ruído de template (elementor_library/page/jet-theme-core)"
porque consultou a rel 38 supondo a **mensagem como parent** e deu JOIN de `child_object_id` em `wp_posts` — como o
parent é o **user**, e **3 dos 17 IDs de user coincidem** com IDs de `wp_posts` (elementor/page/jet-theme-core), o
JOIN gerou o falso "template". **Esta medição substitui a da 2A.**

**SQL do leitor (a espelhar — molde `autoresSlugsDe`, JOIN em `wp_users`):**
```sql
SELECT r.child_object_id  AS wp_id,          -- a mensagem direcionada
       r.parent_object_id AS wp_user_id      -- o destinatário (usuário)
FROM wp_jet_rel_default r
JOIN wp_users u ON u.ID = r.parent_object_id                              -- garante que o parent é usuário
JOIN wp_posts m ON m.ID = r.child_object_id AND m.post_type = 'mensagem-mediunicas'
WHERE r.rel_id = '38';
```
O `JOIN wp_users` descarta os 0 falsos-positivos e o `JOIN wp_posts (post_type)` garante que o child é mensagem.
Agrupar por `wp_id` ⇒ lista de `wp_user_id` por mensagem. **Nenhuma direcionada é pending** (as 15 são publish;
confirmado — todas têm nível `direcionada` que só aparece em publish, 2A §4.7).

### 4.4 Ciência de contexto (não é tarefa)

O plugin **AME Content Permissions** (`_ame_cpe_post_policy = {"accessProtection":{"active":"replace"}}` em 14/15
direcionadas) apenas **liga** a proteção no WP; a **lista de destinatários vive na rel 38** (acima), não no
postmeta. Há também `je_data_store_mensagens-favoritas` em `wp_usermeta` — é o **store de favoritos** (JetEngine),
matéria da **F5 (engajamento)**, **não** da direcionada. Ambos ficam **fora da 3A**.

---

## 5. Invariantes (cada um vira teste que reprova)

| # | Invariante | Teste (§9) |
|---|---|---|
| **I1** | **Enum:** `App\Enums\VisibilidadeMensagem: string` com **6** casos e os **slugs reais** (`publico`,`trabalhadores`,`mediuns-trabalhadores`,`diretores`,`diretor-depae`,`direcionada`); `nivelMinimo(): ?int` = 0/20/30 para a escada (Público/Trabalhadores/Diretores) e **`null`** para os 3 recortes; `ehRecorte()`; `rotulo()`/`cor()`/`opcoes()`. | §9.1 |
| **I2** | **Accessor derivado (NÃO cast):** `Mensagem::visibilidade(): ?VisibilidadeMensagem` = `VisibilidadeMensagem::tryFrom($this->nivel)`; `null`/slug desconhecido ⇒ **`null`** (fail-closed). `$m->nivel` **permanece string** (a suíte 2A não muda de cor). | §9.1/§9.7 |
| **I3** | **Escada:** anônimo vê só `publico`; frequentador (10) idem; trabalhador (20) vê `publico`+`trabalhadores`; diretor (30) vê `publico`+`trabalhadores`+`diretores`. `podeSerVistoPor` e `scopeVisiveisPara` concordam. | §9.2 |
| **I4** | **Recorte Médiuns:** um usuário com **setor `medium`** vê `mediuns-trabalhadores` **além** do que a escada lhe dá; um **diretor sem setor médium NÃO** vê `mediuns-trabalhadores` (recorte, não escada). | §9.2 |
| **I5** | **Recorte Diretor-DEPAE:** usuário com **cargo `diretor-do-depae`** vê `diretor-depae`; um diretor comum (sem esse cargo) **não** vê. | §9.2 |
| **I6** | **Recorte Direcionada:** usuário **destinatário** (pivô) vê **aquela** mensagem `direcionada`; **não-destinatário** (mesmo diretor) **não** vê; um destinatário de A **não** vê a direcionada B. Filtra por mensagem, não por nível. | §9.2 |
| **I7** | **Bypass:** **admin** (papel `administrador`) e **presidente** (cargo `presidente`) veem **TODOS** os níveis, **inclusive `nivel=null`**. O presidente passa **sem** depender do `Gate::before` (bypass no resolvedor e no scope). | §9.2 |
| **I8** | **Fail-closed do `null`:** mensagem com `nivel=null` (ou slug desconhecido) **não** é vista por ninguém **exceto** bypass. Nunca tratada como pública. | §9.2 |
| **I9** | **Scope não vaza:** `scopeVisiveisPara` filtra **no banco** — a contagem por persona é **exatamente** o conjunto permitido; título de mensagem restrita **nunca** volta na query. `visiveisPara(null)` = só as `publico`. | §9.2 |
| **I10** | **Pivô destinatários:** `mensagem_destinatario` (FKs `mensagem_id`/`user_id` cascade + unique explícito); `Mensagem::destinatarios()` e `User::mensagensDirecionadas()` anexam/leem; anexar A→u ⇒ `u` vê A por `mensagensDirecionadas`. **É PII** — só exposto via resolvedor. | §9.1 |
| **I11** | **Policy dois eixos:** `MensagemPolicy::view(?User,Mensagem)` = `podeSerVistoPor`; `viewAny(?User)` = `true`; as 4 de **capacidade** (`ver/criar/editar/excluir`) **permanecem intactas**; `Gate::forUser(null)->allows('view', $publica)` = true e do restrito = false. | §9.3 |
| **I12** | **Import — mapeamento:** `cema:importar-direcionadas` traz **15** mensagens / **73** vínculos / **17** destinatários; casa cada `wp_user_id` (rel 38 parent) por `origem_legado_id`; `sync` do pivô por mensagem (resolvida por `wp_id`). | §9.4 |
| **I13** | **Import — idempotência + fail-safe:** rodar 2x ⇒ **73** vínculos (não duplica); `wp_user_id` **sem** `User` novo → **aviso**, **não** cria user, **não** quebra; `wp_id` **sem** `Mensagem` → aviso. Legado só `SELECT`. | §9.4 |
| **I14** | **Command + bind:** `cema:importar-direcionadas` valida `legado` só com leitor real; com fake dá `assertSuccessful`; resolver `LeitorDirecionadasMensagem` pelo container devolve `…Mysql` (guarda C7). | §9.5 |
| **I-neutro** | **Nenhuma** asserção existente muda de cor (a 3A só ADICIONA — `nivel` NÃO castado; `scopePublica`/`NIVEL_PUBLICO` intactos; nenhuma view/rota/Livewire da 2B tocada). Suíte **~1011** + novos, verde; `Pint` verde. | §9.7 |

---

## 6. Decisões de desenho

### 6.1 O enum `App\Enums\VisibilidadeMensagem` + o accessor (NÃO cast)

Molde [VisibilidadeEvento](../../../app/Enums/VisibilidadeEvento.php), estendido para escada **+** recorte:

```php
enum VisibilidadeMensagem: string
{
    case Publico       = 'publico';
    case Trabalhadores = 'trabalhadores';
    case Mediuns       = 'mediuns-trabalhadores';  // RECORTE — setor Médium
    case Diretores     = 'diretores';
    case DiretorDepae  = 'diretor-depae';          // RECORTE — cargo Diretor do DEPAE
    case Direcionada   = 'direcionada';            // RECORTE — pivô destinatários

    /** Piso de escada (roles.nivel); null = RECORTE (pertencimento, não posição). */
    public function nivelMinimo(): ?int
    {
        return match ($this) {
            self::Publico       => 0,
            self::Trabalhadores => 20,
            self::Diretores     => 30,
            self::Mediuns, self::DiretorDepae, self::Direcionada => null,
        };
    }

    public function ehRecorte(): bool { return $this->nivelMinimo() === null; }
    public function rotulo(): string { /* Público, Trabalhadores, Médiuns, Diretores, Diretor do DEPAE, Direcionada */ }
    public function cor(): string { /* AA — placeholder p/ 3B, §13-O6 */ }
    public static function opcoes(): array { /* value ⇒ rótulo, molde VisibilidadeEvento::opcoes() */ }
}
```

- Os pisos **20/30** batem com `VisibilidadeEvento` (Trabalhadores/Diretoria) e com `roles.nivel` (§3.4). `Publico=0`.
- **`nivel` NÃO é castado** (I2/C1). Em `Mensagem`, um accessor derivado (`use App\Enums\VisibilidadeMensagem;`):
  ```php
  public function visibilidade(): ?VisibilidadeMensagem
  {
      return $this->nivel !== null ? VisibilidadeMensagem::tryFrom($this->nivel) : null;
  }
  ```
  `tryFrom` devolve `null` para `null` **e** para slug desconhecido ⇒ **fail-closed** (mais robusto que o cast, que
  lançaria `ValueError`). `$m->nivel` segue string — a suíte 2A intacta. **Chamar sempre `->visibilidade()` (método),
  nunca `->visibilidade` (propriedade)** para não colidir com resolução de relação do Eloquent — §13-O2.

### 6.2 O resolvedor no model `Mensagem` (escada + 3 recortes + bypass)

Molde [Evento::podeSerVistoPor/scopeVisiveisPara](../../../app/Models/Evento.php#L60-L89), estendido. `use App\Models\User;`.

```php
/** Bypass total: admin (papel 100) ou presidente (cargo). */
private static function veTudo(?User $u): bool
{
    return $u !== null && ($u->hasRole('administrador') || $u->ehPresidente());
}

public function podeSerVistoPor(?User $usuario): bool
{
    if (self::veTudo($usuario)) return true;

    $v = $this->visibilidade();
    if ($v === null) return false;                 // nível null/desconhecido = fail-closed
    $nivel = $usuario?->nivelMaximo() ?? 0;

    return match ($v) {
        VisibilidadeMensagem::Publico       => true,
        VisibilidadeMensagem::Trabalhadores => $nivel >= VisibilidadeMensagem::Trabalhadores->nivelMinimo(),
        VisibilidadeMensagem::Diretores     => $nivel >= VisibilidadeMensagem::Diretores->nivelMinimo(),
        VisibilidadeMensagem::Mediuns       => $usuario !== null && $usuario->ehMedium(),
        VisibilidadeMensagem::DiretorDepae  => $usuario !== null && $usuario->ehDiretorDepae(),
        VisibilidadeMensagem::Direcionada   => $usuario !== null
            && $this->destinatarios()->whereKey($usuario->id)->exists(),
    };
}

public function scopeVisiveisPara(Builder $query, ?User $usuario): Builder
{
    if (self::veTudo($usuario)) return $query;      // bypass: sem filtro (vê tudo, inclusive null)

    $nivel = $usuario?->nivelMaximo() ?? 0;

    return $query->where(function (Builder $q) use ($usuario, $nivel) {
        $q->where('nivel', VisibilidadeMensagem::Publico->value);        // sempre
        if ($usuario !== null) {
            if ($nivel >= VisibilidadeMensagem::Trabalhadores->nivelMinimo()) {
                $q->orWhere('nivel', VisibilidadeMensagem::Trabalhadores->value);
            }
            if ($nivel >= VisibilidadeMensagem::Diretores->nivelMinimo()) {
                $q->orWhere('nivel', VisibilidadeMensagem::Diretores->value);
            }
            if ($usuario->ehMedium())       $q->orWhere('nivel', VisibilidadeMensagem::Mediuns->value);
            if ($usuario->ehDiretorDepae()) $q->orWhere('nivel', VisibilidadeMensagem::DiretorDepae->value);
            $q->orWhere(fn (Builder $d) => $d
                ->where('nivel', VisibilidadeMensagem::Direcionada->value)
                ->whereHas('destinatarios', fn (Builder $u) => $u->whereKey($usuario->id)));
        }
    });
}
```

- **`nivel=null` não casa nenhum ramo ⇒ excluído** (fail-closed no banco). O `where(fn…)` externo **isola** os
  `orWhere` entre parênteses (compõe com qualquer `->where('status',…)` que o consumidor 3B encadeie).
- **`scopeVisiveisPara` filtra só o eixo `nivel`** — **status é ortogonal** (a 2B/3B encadeia `publicado()`
  separadamente, como `Evento::publicado()->visiveisPara(null)` no sitemap). Na 3A o scope é exercido **só por
  unidade** (front inerte).
- **Bypass nos DOIS pontos** (§3.5): o presidente não está no `Gate::before` (admin-only). `veTudo` é `private static`
  (fonte única) e null-safe.
- **Ciência de performance (para a 3B):** `ehMedium/ehDiretorDepae/ehPresidente` fazem `exists()` (1 query cada);
  no **scope** rodam **uma vez** (barato). `podeSerVistoPor` chamado **por item** numa lista causaria N+1 ⇒ a 3B usa
  `scopeVisiveisPara` para listas e, se precisar do resolvedor item-a-item, faz eager-load de `setores`/`cargos`. §12.

### 6.3 O pivô `mensagem_destinatario` (N:N, PII) — 1 migration

```php
Schema::create('mensagem_destinatario', function (Blueprint $t) {
    $t->foreignId('mensagem_id')->constrained('mensagens')->cascadeOnDelete();
    $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
    $t->unique(['mensagem_id', 'user_id'], 'mensagem_destinatario_unique');
});
```
- Nome de tabela **explícito** nos dois FKs (o `constrained` de `user_id` inferiria `users` — ok — mas mantemos o
  padrão da 2A). **Sem** `timestamps` (YAGNI; §13-O8). **cascade nos dois lados** ⇒ deletar mensagem **ou** usuário
  remove os vínculos, **sem órfão**. Nome do unique explícito (padrão I17 da 2A; o auto-nome ~48 chars caberia, mas
  a convenção é nomear).

### 6.4 Model — relações e predicados (aditivo, neutro)

- **`Mensagem`** (edita, aditivo): `destinatarios()` → `belongsToMany(User::class, 'mensagem_destinatario',
  'mensagem_id', 'user_id')`; + `visibilidade()` + `podeSerVistoPor()` + `scopeVisiveisPara()` (§6.1/6.2). **Não**
  altera `nivel`/`casts`/`scopePublica`/`NIVEL_PUBLICO`.
- **`User`** (edita, aditivo):
  ```php
  public function mensagensDirecionadas(): BelongsToMany
  { return $this->belongsToMany(Mensagem::class, 'mensagem_destinatario', 'user_id', 'mensagem_id'); }

  public function ehMedium(): bool       { return $this->setores()->where('setores.slug', 'medium')->exists(); }
  public function ehDiretorDepae(): bool { return $this->cargos()->where('cargos.slug', 'diretor-do-depae')->exists(); }
  public function ehPresidente(): bool   { return $this->cargos()->where('cargos.slug', 'presidente')->exists(); }
  ```
  Os slugs são **literais cravados no dev DB** (§3.4). Recomendação (§13-O5): promovê-los a **constantes** (ex.:
  `Setor::SLUG_MEDIUM`, `Cargo::SLUG_DIRETOR_DEPAE`/`SLUG_PRESIDENTE`) citando `EstruturaCemaSeeder`, para não
  espalhar strings mágicas.

### 6.5 A `MensagemPolicy` — acrescentar os dois eixos de VISIBILIDADE

Molde [EventoPolicy](../../../app/Policies/EventoPolicy.php). **Acrescentar** (sem tocar as 4 de capacidade da 2A):
```php
public function view(?User $user, Mensagem $mensagem): bool { return $mensagem->podeSerVistoPor($user); }
public function viewAny(?User $user): bool { return true; } // a listagem filtra por scopeVisiveisPara
```
- `$user` **nullável** nesses dois (anônimo passa por `Gate::forUser(null)`); as 4 de capacidade seguem
  **não-nuláveis**. O admin passa antes no `Gate::before`; **Filament não usa strict authorization** ⇒ acrescentar
  `view/viewAny` **não muda** o comportamento do `/admin` (o admin vê tudo pelo Gate). **Inerte no front** (a 2B não
  chama `view`/`viewAny` — usa `publica()`). Atualizar o docblock para "dois eixos" (molde `EventoPolicy`).

### 6.6 A cadeia de importação das direcionadas (clone do padrão `cema:importar-*`)

- **`App\Importacao\LeitorDirecionadasMensagem`** (interface): `direcionadas(): array` →
  `[['wp_id'=>int, 'destinatarios_wp_ids'=>int[]], …]` (uma entrada por mensagem direcionada).
- **`App\Importacao\LeitorDirecionadasMensagemMysql`**: o SQL da rel 38 (§4.3, JOIN `wp_users`+`wp_posts`),
  **agrupado por `wp_id`** (a mensagem). Emite os 15 registros com seus `wp_user_id`. `DB::connection('legado')`,
  prefixo `wp_` literal (molde `LeitorMensagensMysql`).
- **`App\Importacao\ImportadorDirecionadasMensagens`** (DI `LeitorDirecionadasMensagem`): para cada entrada, numa
  `DB::transaction`:
  - `Mensagem::where('wp_id', $wpId)->first()` — se **ausente** → aviso `mensagem_nao_encontrada++`, pula (não cria);
  - para cada `wp_user_id`, `User::where('origem_legado_id', $id)->first()` — se **ausente** → aviso
    `user_nao_encontrado++` (registra o `wp_user_id`), **não** cria user, segue;
  - `$mensagem->destinatarios()->sync($idsResolvidos)` (idempotente — 2ª rodada não duplica; **substitui** o conjunto);
  - contadores: `direcionadas`, `vinculos`, `destinatarios_distintos`, `user_nao_encontrado`, `mensagem_nao_encontrada`.
  - **Fallback por e-mail (opcional, §13-O)**: hoje **0/17 faltam** por `origem_legado_id`; o fallback só entra se
    uma futura re-medição achar destinatário sem `origem_legado_id` — então casa por `user_email` (LOWER/trim).
- **`App\Console\Commands\ImportarDirecionadasMensagens`**: `signature 'cema:importar-direcionadas'`; valida
  `legado->getPdo()` **só** com `LeitorDirecionadasMensagemMysql`; resumo. Pré-requisito: usuários **e** mensagens já
  importados (o cutover roda depois de ambos).
- **`AppServiceProvider::register()`**: **bind obrigatório** `bind(LeitorDirecionadasMensagem::class,
  LeitorDirecionadasMensagemMysql::class)` após [:47](../../../app/Providers/AppServiceProvider.php#L47) (**C7**).

### 6.7 Factory — estado de nível para as personas

[MensagemFactory](../../../database/factories/MensagemFactory.php) já tem `publicada()`/`pendente()`/`publica()`
(nível `publico`) e `nivel` default `null` ([:27](../../../database/factories/MensagemFactory.php#L27)). **Acrescentar**
um estado utilitário para os testes de persona:
```php
public function comNivel(VisibilidadeMensagem|string $v): static
{ return $this->state(fn () => ['nivel' => $v instanceof VisibilidadeMensagem ? $v->value : $v]); }
```
(A factory grava a **string**; com o accessor derivado — não cast — `create(['nivel'=>'trabalhadores'])` funciona
sem coerção. Aditivo, não altera os estados existentes.)

---

## 7. As peças (inventário)

**Novos (com cabeçalho de autoria — CLAUDE.md §8):**
`app/Enums/VisibilidadeMensagem.php` ·
`database/migrations/xxxx_create_mensagem_destinatario_table.php` ·
`app/Importacao/LeitorDirecionadasMensagem.php` + `LeitorDirecionadasMensagemMysql.php` +
`ImportadorDirecionadasMensagens.php` · `app/Console/Commands/ImportarDirecionadasMensagens.php` · testes (§9).

**Editados (mínimo, aditivo):** `app/Models/Mensagem.php` (accessor `visibilidade` + `podeSerVistoPor` +
`scopeVisiveisPara` + `destinatarios` — **sem** tocar `nivel`/`scopePublica`) · `app/Models/User.php` (`ehMedium`/
`ehDiretorDepae`/`ehPresidente`/`mensagensDirecionadas`) · `app/Policies/MensagemPolicy.php` (+`view`/`viewAny`) ·
`app/Providers/AppServiceProvider.php` (**1 bind** — C7) · `database/factories/MensagemFactory.php` (+estado `comNivel`)
· (opcional §13-O5) `app/Models/Setor.php`/`Cargo.php` (constantes de slug).

**NÃO toca:** `scopePublica`/`NIVEL_PUBLICO`/`casts` da Mensagem · qualquer view/rota/Livewire/controller da 2B ·
`Evento`/`VisibilidadeEvento`/`EventoPolicy` (molde, intacto) · `AutorizaPorDepartamento`/`AcessoPorTipo`/
`GlossarioCapacidades`/`MatrizCapacidades`/seeders de capacidade · `ImportadorUsuarios`/`ImportadorMensagens`
(reuso) · o sitemap/SEO da 2B.

---

## 8. Cutover (o que roda no deploy — do dono, idempotente)

Ordem em produção (todos idempotentes; **nunca** destrutivo):
1. `php artisan migrate` (a migration nova `mensagem_destinatario`, incremental).
2. Garantir usuários e mensagens importados (a 2A/2B já os trouxeram; o import de direcionadas **precisa** de ambos):
   `cema:importar-usuarios` → `cema:importar-mensagens` (se ainda não rodados no ambiente).
3. `php artisan cema:importar-direcionadas` (túnel SSH ativo — os **73 vínculos / 15 mensagens / 17 destinatários**).

**A visibilidade rica nasce INERTE no site** — o front só passa a consumir `scopeVisiveisPara` na **3B**. Hoje o
resolvedor existe, está testado por unidade, e o pivô está populado; a tela pública segue `publica()`.

---

## 9. Plano de teste (TDD real, vermelho primeiro)

### 9.0 Ordenação (constraint)
O enum e o pivô devem existir **antes** do resolvedor/import. Sequência: enum → migration+relações (`destinatarios`/
`mensagensDirecionadas`) → predicados `User` → accessor+resolvedor `Mensagem` → Policy → cadeia de import → command+bind.

### 9.1 `VisibilidadeMensagemTest` (enum) + relações/accessor — molde `VisibilidadeEventoTest`
Os 6 casos e **backing values** exatos; `nivelMinimo()` = 0/20/30 (escada) e `null` (recortes); `ehRecorte()`;
`opcoes()` (6 pares); `rotulo()`. **Accessor:** `visibilidade()` reidrata o enum a partir de `nivel`; `null` para
`nivel=null` **e** para slug desconhecido (`'xpto'`). **Pivô:** `destinatarios()`/`mensagensDirecionadas()` anexam/
leem por `mensagem_destinatario`; `sync` idempotente; cascade no delete.

### 9.2 `MensagemVisibilidadeAcessoTest` (o coração) — molde `VisibilidadeEventoAcessoTest`
`setUp` semeia papéis (10/20/30/100) **e** cria o setor `medium` + cargos `diretor-do-depae`/`presidente` (via
`EstruturaCemaSeeder` ou factories diretas). Personas construídas com papel + setor/cargo (molde
`ImportadorUsuarios`: `assignRole` + `setores()->attach($id,['funcao'=>'membro'])` + `cargos()->attach($id)`).
**Matriz `[persona, [esperado por nível]]`** (lista **indexada**), níveis na ordem
`[publico, trabalhadores, mediuns-trabalhadores, diretores, diretor-depae, direcionada(destinatário?)]`:

| persona | publico | trabalhadores | mediuns | diretores | diretor-depae | direcionada (é destinatário) |
|---|:--:|:--:|:--:|:--:|:--:|:--:|
| anônimo (null) | ✔ | ✘ | ✘ | ✘ | ✘ | ✘ |
| frequentador (10) | ✔ | ✘ | ✘ | ✘ | ✘ | ✘ |
| trabalhador (20) | ✔ | ✔ | ✘ | ✘ | ✘ | ✘ |
| **médium** (20 + setor medium) | ✔ | ✔ | **✔** | ✘ | ✘ | ✘ |
| **diretor** (30) | ✔ | ✔ | **✘** | ✔ | ✘ | ✘ |
| **diretor-DEPAE** (30 + cargo) | ✔ | ✔ | ✘ | ✔ | **✔** | ✘ |
| **presidente** (cargo) | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ |
| **admin** (100) | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ |
| **destinatário** (freq. no pivô de A) | ✔ | ✘ | ✘ | ✘ | ✘ | ✔ (A) / ✘ (B) |

Asserções: (a) `podeSerVistoPor` bate a matriz célula a célula (mensagem por nível); (b) `scopeVisiveisPara($p)->count()`
= **exatamente** o nº de ✔ da linha (cria 1 mensagem por nível + 1 direcionada-para-A + 1 direcionada-para-B) — prova
que **não vaza** (o título restrito nunca volta); (c) `nivel=null` invisível a todos **menos** admin/presidente
(I7/I8); (d) `veTudo` do presidente vale **sem** `Gate::before`; (e) `visiveisPara(null)->pluck('nivel')` = só
`['publico']`.

### 9.3 `MensagemPolicyVisibilidadeTest` — molde `VisibilidadeEventoAcessoTest::test_policy_view_via_gate`
`Gate::forUser($persona)->allows('view', $mensagem)` para pública/restrita; `Gate::forUser(null)->allows('view',
$publica)`=true e do restrito=false; **viewAny**=true para qualquer. **Guarda de neutralidade:** o
`MensagemPolicyCapacidadeTest` (2A) continua verde (as 4 de capacidade intactas) — **não** editar esse teste.

### 9.4 `ImportadorDirecionadasMensagensTest` — molde `ImportadorMensagensTest`
Fake leitor (classe anônima) devolvendo `[['wp_id'=>X,'destinatarios_wp_ids'=>[...]], …]`. Fixtures: mensagens com
`wp_id` + users com `origem_legado_id`. Casos: **casa por `origem_legado_id`** e popula o pivô; **`wp_user_id` sem
User** → aviso, não cria, não quebra (vínculo omitido); **`wp_id` sem Mensagem** → aviso, pula; **idempotência**
(2x ⇒ mesmos vínculos, sem duplicar); **sync substitui** (remover um destinatário no legado reflete no re-run);
contadores (`direcionadas`/`vinculos`/`destinatarios_distintos`/`user_nao_encontrado`/`mensagem_nao_encontrada`).

### 9.5 `ImportarDirecionadasCommandTest` — molde `ImportarMensagensCommandTest`
`bind` do fake + `artisan('cema:importar-direcionadas')->assertSuccessful()`; confere contadores. **+ Guarda do
binding (C7/I14):** `app(LeitorDirecionadasMensagem::class)` **sem** fake ⇒ instância `…Mysql` (só resolve o
container, **não** chama `direcionadas()`, **não** toca o legado).

### 9.6 (R3, pré-merge) Leitor real contra o legado vivo
`[[verificar-leitor-legado-contra-banco-real]]`: rodar `cema:importar-direcionadas` contra o **legado vivo** antes do
merge (os `*Mysql` só têm fake na suíte) e confirmar **15 · 73 · 17 · 0 não-resolvidos** (medição §4.3). Confere que
o SQL da rel 38 (direção parent=user) roda sem erro e casa 100%.

### 9.7 Regressão + neutralidade + suíte
Baseline **~1011** (`docker compose exec -T app php artisan test --list-tests`); alvo **~1011 + novos**, verde.
**I-neutro:** confirmar que `MensagemTest`/`ImportadorMensagensTest` (que leem `$m->nivel` string) **seguem verdes**
(prova de que `nivel` **não** foi castado). `docker compose exec -T app php artisan test` + **Pint** verde no
container ([[pint-antes-de-push]]); ciência [[flaky-importadorblog-gd-cap-imagem]].

---

## 10. Fora de escopo (Fatias 3B/4/5 — não fazer agora)

- **Front rico** (3B): trocar `publica()` por `visiveisPara($user)` nas 4 páginas; badge de nível + cadeado;
  legenda de bolinhas; "minhas direcionadas"; barreira de login inline (modal + contato); `noindex` condicional;
  card de destinatários **cego** (nunca PII); religar o menu; SEO por nível. **A 3A não toca a 2B.**
- **Curadoria** (F4): médium **cria** mensagem (`mensagens.publicar` via setor Médium), diretor-DEPAE
  ratifica/publica (`mensagens.definir-nivel`), máquina de estados, porta `perfil`, auditoria, campo destinatários no
  `/admin`. A 3A é **só quem vê**.
- **Engajamento** (F5): favoritar (`je_data_store_mensagens-favoritas` do legado — §4.4), lida/não-lida, vistas,
  curtir do autor. **Nada agora.**

---

## 11. Fronteiras: o que toca × o que NÃO toca

**Toca (novo):** enum + 1 migration (pivô) + cadeia de import (Leitor/interface + Importador + Command) + testes.
**Toca (edição aditiva):** `Mensagem` (accessor+resolvedor+`destinatarios`) · `User` (3 predicados +
`mensagensDirecionadas`) · `MensagemPolicy` (+`view`/`viewAny`) · `AppServiceProvider` (1 bind) · `MensagemFactory`
(1 estado) · (opcional) `Setor`/`Cargo` (constantes).
**NÃO toca:** `nivel`/`casts`/`scopePublica`/`NIVEL_PUBLICO` da Mensagem · a 2B inteira (views/rotas/Livewire/
controllers/sitemap) · `Evento`/`VisibilidadeEvento`/`EventoPolicy` · núcleo de capacidade (trait/policies de
capacidade/glossário/matriz/seeders) · `ImportadorUsuarios`/`ImportadorMensagens`.

---

## 12. Ciências (não são tarefa desta fatia)

- **Resolvedor INERTE no site** por desenho (§0/§2.4): o front 2B usa `publica()` fixo; `podeSerVistoPor`/
  `scopeVisiveisPara`/`view`/`viewAny` só são exercidos por **teste de unidade**. A 3B os liga. Não é bug.
- **Recorte ≠ escada (semântica ortogonal):** um médium vê Trabalhadores **porque** tem o papel `trabalhador`
  (nível 20), não porque é médium — o recorte só adiciona `mediuns-trabalhadores`. Um "médium-que-não-é-trabalhador"
  (anomalia de dados) veria Médiuns+Público, **não** Trabalhadores. É o comportamento correto do modelo (§13-O7).
- **Bypass explícito (desvio do Evento):** no Evento o nível 100 do admin cobria tudo pela escada; os 3 recortes da
  Mensagem quebram isso (o admin não é médium nem destinatário) ⇒ bypass explícito de admin **e** presidente, nos
  dois pontos, porque o `Gate::before` cobre só o admin (§3.5).
- **PII da Direcionada:** o pivô `mensagem_destinatario` diz **a quem** uma mensagem pessoal foi dirigida — só pode
  ser lido pelo resolvedor (`podeSerVistoPor`/`scopeVisiveisPara`) e pelo próprio destinatário (`mensagensDirecionadas`).
  A 3A **não** o expõe em nenhuma view/Resource; o import é `SELECT`-only e casa por `origem_legado_id`.
- **N+1 na 3B:** `ehMedium/ehDiretorDepae/ehPresidente` consultam o banco; para listas a 3B usa `scopeVisiveisPara`
  (chama os predicados **uma vez**), não `podeSerVistoPor` por item (§6.2).
- **`nivel` continua BRUTO** e **não** castado — a semântica vem do accessor derivado. Castar quebraria a suíte 2A e
  lançaria `ValueError` em slug futuro; `tryFrom` degrada para `null` (fail-closed).
- **`diretor-depae` ocioso (0 mensagens hoje):** o enum e o recorte existem para quando o admin classificar uma
  mensagem nesse nível pela tela; hoje o recorte nunca casa (não há linha). Não bloqueia nada.

---

## 13. Passe adversarial próprio (19/jul) — achados e pendências para o dono

> **Passe interno rodado antes da entrega:** legado medido **ao vivo** (túnel reaberto durante o passe, só `SELECT`);
> os moldes (Evento/VisibilidadeEvento/EventoPolicy, User, Mensagem 2A, GlossarioUsuarios/EstruturaCemaSeeder,
> Gate::before, cadeia de import) foram lidos direto com **evidência `arquivo:linha`**; slugs/roles conferidos no dev
> DB; a fonte da Direcionada **cravada ao vivo**. As divergências abaixo **já estão incorporadas** ao spec.

**Correções que ESTE spec já incorpora (divergências do kickoff/2A, todas confirmadas):**

- **C1 — NÃO castar `nivel` (§3.2/I2).** O kickoff manda "castar a coluna `nivel`", mas a suíte **já lê `$m->nivel`
  como string** (MensagemTest:98; ImportadorMensagensTest:90/100/238) ⇒ o cast **quebraria 4 asserções** (viola
  neutralidade) e lançaria `ValueError` em slug desconhecido. Solução: **accessor derivado `visibilidade()` via
  `tryFrom`** — neutro **e** fail-closed. **Divergência deliberada do kickoff.**
- **C2 — Direcionada é rel 38 REVERSA (§4.3).** A fonte é `wp_jet_rel_default` `rel_id=38` com **parent=USUÁRIO →
  child=mensagem** (73 vínculos, 15 msgs, **17 destinatários, 0 faltando**). O kickoff acertou o `rel_id`/count mas
  **inverteu a direção**. **A 2A §4.5 ERROU** ("rel 38 = ruído elementor/page"): supôs mensagem como parent e deu
  JOIN em `wp_posts` — 3 dos 17 IDs de user coincidem com posts, gerando o falso "template". Esta medição a substitui.
- **C3 — "Diretor-DEPAE" e "Presidente" são CARGOS, não papel/depto (§3.4).** `cargos.slug='diretor-do-depae'`
  (institucional=0) e `cargos.slug='presidente'` (institucional=1). "Médium" é **setor** (`setores.slug='medium'`).
  Predicados por **slug** (chave estável do `EstruturaCemaSeeder`).
- **C4 — bypass explícito de admin E presidente (§3.5/I7).** `Gate::before` é **admin-only**; o presidente **não**
  passa por ele ⇒ bypass no resolvedor **e** no scope (o Evento não precisava — escada pura).
- **C5 — slug de "Médiuns" é `mediuns-trabalhadores` (§4.1).** O backing value do enum tem de ser o slug **real**
  gravado pela 2A, não a abreviação "mediuns" do kickoff. O `diretor-depae` é termo **ocioso** (0 msgs) mas entra no
  enum.
- **C6 — chave de casamento = `origem_legado_id` (§3.6).** = `wp_users.ID`; **17/17 resolvem** hoje. Fallback e-mail
  só se uma re-medição achar destinatário sem `origem_legado_id`.

**Pontos ABERTOS para o passe adversarial do dono:**

1. **O1 — comando dedicado `cema:importar-direcionadas` (recomendado) vs dobrar em `cema:importar-mensagens`.**
   Recomendo **dedicado**: precisa de usuários **e** mensagens já importados; concern separado; padrão `cema:*`
   um-por-tarefa. **Confirmar.**
2. **O2 — accessor `visibilidade()` (método) vs `Attribute` de leitura `visibilidade` (propriedade).** Recomendo o
   **método** `visibilidade(): ?VisibilidadeMensagem` (evita a colisão do Eloquent property↔relation). Alternativa:
   um `Attribute` get-only `visibilidade` (permite `$m->visibilidade`), idiomático mas exige atenção ao não colidir.
   Ambos deixam `->nivel` string. **Confirmar** (micro).
3. **O3 — expor destinatários no `/admin` MensagemResource agora?** Recomendo **NÃO** na 3A (é edição = F4; e como o
   form não tem o campo, salvar no `/admin` **não** apaga o pivô importado — seguro). A curadoria de destinatários
   entra na **F4**. **Confirmar.**
4. **O4 — bypass do presidente: cargo `presidente` (slug) — e se o presidente também for admin?** Cobre os dois
   (`hasRole('administrador') || ehPresidente()`); um presidente sem papel algum ainda vê tudo (é o objetivo).
   **Confirmar** que "presidente" = o cargo institucional `presidente` (não um papel).
5. **O5 — strings mágicas dos slugs** (`medium`/`diretor-do-depae`/`presidente`). Recomendo promovê-las a
   **constantes** em `Setor`/`Cargo` citando `EstruturaCemaSeeder`. **Confirmar** (custo baixo, robustez).
6. **O6 — paleta/rótulos do enum (`cor()`/`rotulo()`).** Inertes na 3A (a 3B desenha badges). Proponho rótulos =
   os `name` da taxonomia (Público/Trabalhadores/Médiuns/Diretores/Diretor do DEPAE/Direcionada) e cores AA
   placeholder, **refinadas na 3B** (mesmo follow-up "paleta de formato" da 2B). **Confirmar** que a paleta fica p/ 3B.
7. **O7 — médium-não-trabalhador (anomalia).** Pela ortogonalidade, veria Médiuns+Público mas **não** Trabalhadores.
   Na prática todo médium é trabalhador (o setor é trabalho do DEPAE), então o caso não ocorre. **Confirmar** que a
   regra "Médium vê Trabalhadores" se apoia no papel `trabalhador` (não é forçada pelo recorte).
8. **O8 — `timestamps` no pivô `mensagem_destinatario`?** Recomendo **sem** (YAGNI). Se a F4/auditoria quiser saber
   *quando* foi direcionada, acrescenta depois. **Confirmar.**
9. **Regra sempre:** pt-BR em tudo; cabeçalho de autoria no PHP novo; `Pint` antes do push; `docker compose exec -T
   app php artisan test`; **todo brief de subagente que rode `artisan` DEVE proibir `migrate:fresh/refresh/wipe/reset`
   e seed destrutivo** e reafirmar `legado` como read-only ([[nunca-migrate-fresh-no-dev]]).

---

### Passe adversarial do CONSULTOR (19/jul) — veredito: ✅ APROVADA para virar plano (zero bloqueador)

O Consultor **re-mediu o legado AO VIVO** e re-verificou as afirmações críticas contra o código — **todas conferem**:

- **C2 (rel 38 REVERSA) confirmado ao vivo:** 73 linhas · 17 parents = users · 15 childs = mensagens · controle
  (childs que são users) = 0 · **17/17 destinatários casam por `origem_legado_id`**. A correção da 2A §4.5 procede.
- **C4 (Gate::before admin-only, [AppServiceProvider:71](../../../app/Providers/AppServiceProvider.php#L71))**
  confirmado ⇒ o bypass explícito do presidente (resolvedor + scope) é **necessário**.
- **C1 (as 4 asserções de `nivel` string existem)** confirmado (MensagemTest:98; ImportadorMensagensTest:90/100/238)
  ⇒ o cast quebraria; o **accessor `tryFrom` é melhor** (fail-closed em slug desconhecido). Divergência **CERTA**.
- **C3/C5 (slugs no dev DB)** confirmado: setor `medium`, cargos `diretor-do-depae`/`presidente`; níveis reais +
  49 null; o enum bate 1:1.
- Resolvedor analisado: `null`=fail-closed no banco, `orWhere` isolado no `where(fn)`, Direcionada por `whereHas`,
  bypass nos 2 pontos — **sem vazamento**. `visibilidade()` como método é seguro (sem colisão hoje).

**Abertos ENDOSSADOS:** O1 (comando dedicado) · O2 (accessor **método**, não Attribute) · O3 (NÃO expor
destinatários no `/admin` agora — F4; o form sem o campo não apaga o pivô no save) · O4 (bypass =
`hasRole('administrador') || cargo 'presidente'`) · O6 (paleta/rótulos p/ a 3B) · O7 ("Médium vê Trabalhadores"
pelo **papel** trabalhador, não pelo recorte) · O8 (pivô **sem** timestamps).

**ELEVADO — O5 vira OBRIGATÓRIO (FAÇA):** os slugs (`medium`/`diretor-do-depae`/`presidente`) são **código de
SEGURANÇA** — promover a **CONSTANTES** em `Setor`/`Cargo` ancoradas no `EstruturaCemaSeeder`. O TDD de persona já
pega um slug errado, mas a constante evita **string mágica em predicado que decide acesso a PII**. Baixo custo, alto
valor. **Incorporado ao plano** (`Setor::SLUG_MEDIUM`, `Cargo::SLUG_DIRETOR_DEPAE`/`SLUG_PRESIDENTE`).

**Ciência p/ a 3B (registrar, não é da 3A):** o **presidente (bypass) VÊ as Direcionadas de TODAS as pessoas**. Na
3B, a tela do presidente decidirá se exibe a **LISTA de destinatários (PII)** a ele — é o override travado, mas a
exibição dos destinatários é decisão de **UX/PII da 3B**, não desta fatia.

**Veredito:** **segue para o PLANO** (TDD, ordem do §9.0: enum → migration+relações → predicados User →
accessor+resolvedor → policy → import → command+bind; a matriz de 8 personas × 6 níveis do §9.2 é a prova de
não-vazamento — **não relaxar o count por persona**). O Consultor fará o passe do plano antes da execução.

---

### Passe do PLANO — CONSULTOR (19/jul) — ✅ APROVADO (zero bloqueador)

Plano em [`../plans/2026-07-19-camada-4-fatia-3a-backend-visibilidade-mensagens.md`](../plans/2026-07-19-camada-4-fatia-3a-backend-visibilidade-mensagens.md).
TDD real, ordem do §9.0, código completo. O Consultor verificou célula a célula a matriz 8×6 e os counts do
scope-não-vaza (1/1/2/3/3/4/2/8), a guarda C7 (conexão lazy), a policy (dois eixos, capacidade intacta), o SQL da
rel 38 e a neutralidade — **tudo confere**. **Elevou o O5 (constantes de slug em `Setor`/`Cargo`) a OBRIGATÓRIO**
(código de acesso a PII, não string mágica). **Ciência p/ a 3B:** o presidente (bypass) vê as Direcionadas de todas
as pessoas — exibir a LISTA de destinatários (PII) a ele é decisão de UX/PII da 3B. Ajuste cosmético aplicado
(int-map no `assertEqualsCanonicalizing`).

---

### Execução (subagente-driven, TDD) — ✅ COMPLETA NO DEV (aguarda passe do PR)

Branch `camada-4-fatia-3a-visibilidade` (de `b7f9402`). 6 tasks TDD, cada uma implementer → review por-task
(**0 findings** em T1–T6): T1 enum+constantes · T2 pivô `mensagem_destinatario` · T3 predicados+accessor · T4
resolvedor+matriz de personas · T5 policy · T6 cadeia de import+bind (guarda C7). **CP-1 ✅ 1026 · CP-2 ✅ 1032 ·
CP-3 ✅ 1032** (3112 assertions); **Pint** sem drift. **Review final da branch (opus): Ready to merge** — 0
Critical/Important, 1 Minor **corrigido** (`sync` de destinatários em `DB::transaction`, aderência ao §6.6).
**R3 ao vivo confirmado** (túnel ativo, `cema:importar-direcionadas`): **15 direcionadas · 73 vínculos · 17
destinatários · 0 não-resolvidos** — o cutover de DEV está feito (o pivô do dev tem os destinatários reais; PII
**local**, jamais commitada). Comportamento-neutro provado (a suíte 2A/2B não mudou de cor; o resolvedor nasce
inerte — **zero** consumidor no front, que segue `Mensagem::publica()`). **Cutover de PROD = do dono no deploy**
(§8: `migrate` → `cema:importar-direcionadas`). Front rico = **3B**. **Parado no PR para o passe do PR do Consultor
(CI verde no último commit) + go do dono.**

---
