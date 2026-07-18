# Spec — Camada 4 · Fatia 0 · Integridade de papéis no cadastro de usuário (pré-Mensagens)

> Autoria: Thiago Mourão — https://github.com/MouraoBSB — 2026-07-17
> Enquadramento travado com o dono no kickoff da Camada 4 (Mensagens Mediúnicas). Este spec **não**
> improvisa além das decisões travadas; **cada afirmação sobre o terreno foi verificada contra o código
> real** (evidência `arquivo:linha` no §3, inclusive o vendor do Filament) e os pontos em aberto — ou em
> que o enquadramento **diverge da medição** — estão no §13 para o **passe adversarial**.
> Destino: **SPEC** — vai ao passe adversarial **antes** de virar plano de implementação. **NÃO implementar
> ainda.**
> Base: `origin/main` (HEAD `cfe3873`, PR #34 da Camada 1 — A1 mesclado).
> Fundação conceitual: o **modelo de capacidades** (VISIBILIDADE × CAPACIDADE, CLAUDE.md) e a hierarquia
> linear de papéis (`GlossarioUsuarios::PAPEIS`). Esta fatia **não** cria papel, tabela ou capacidade nova —
> é uma **trava de integridade** no formulário que já existe.

---![alt text](image.png)

## 1. Contexto e objetivo

Vamos abrir o módulo **Mensagens Mediúnicas** (Camada 4). O módulo depende de uma pergunta que hoje o
sistema **não sabe responder com segurança**: *"quem é médium?"*. A definição travada é **médium = está no
setor Médium E tem papel ≥ Trabalhador** — um lançador de mensagem. Sem uma trava, um **frequentador solto
num setor** (ou um usuário sem papel algum) entraria na conta de "médium" como lançador indevido.

Antes de qualquer linha do módulo, esta **Fatia 0** fecha esse flanco na **origem do dado**: o cadastro de
usuário no `/admin`. Ela impede, **no servidor**, duas combinações estruturalmente inválidas entre **papel**
(hierarquia de acesso) e **estrutura** (setor / cargo), e resolve os **4 usuários sem papel** que existem
hoje no dev.

**Isto é PREVENÇÃO, não limpeza.** A medição de 17/jul (§4) mostra **zero** violações hoje: os 46 membros do
setor Médium são todos Trabalhador (29) ou Diretor (17); todo cargo é ocupado só por Diretor. A trava é uma
**garantia para o futuro** — que a base continue coerente à medida que o módulo passa a confiar nela — não um
mutirão de correção. O valor está em **impedir a entrada** do dado ruim, não em varrer dado ruim existente.

**Escopo:** só a coerência **papel × setor** e **papel × cargo** no cadastro. **Fora**: qualquer coisa do
módulo Mensagens (model, migração, visibilidade, curadoria); a `funcao` do pivô `setor_usuario`
(membro/coordenador — é a função *dentro* do setor, não o papel); criar/renomear papéis. Ver §10.

---

## 2. As regras (R1/R2) e o invariante crítico

Papel é **único** e **hierárquico** — `GlossarioUsuarios::PAPEIS` ([GlossarioUsuarios.php:10-15](../../../app/Importacao/GlossarioUsuarios.php#L10-L15)),
com nível em `roles.nivel`:

| Papel | `roles.nivel` |
|---|---|
| frequentador | 10 |
| trabalhador | 20 |
| diretor | 30 |
| administrador | 100 |

> **R1 — Setor exige Trabalhador ou acima.** Ter **qualquer** setor ⇒ papel com `nivel ≥ 20`.
> Frequentador (10) e **sem-papel** (0) **não** podem ter setor.

> **R2 — Cargo exige Diretor.** Ter **qualquer** cargo ⇒ papel com `nivel ≥ 30`.
> Trabalhador (20), Frequentador (10) e sem-papel (0) **não** podem ter cargo.

Propriedades das regras, todas travadas:

- **Vale nos DOIS sentidos.** A trava morde tanto ao **rebaixar o papel** com setor/cargo já presentes,
  quanto ao **adicionar setor/cargo** com papel insuficiente. Como a checagem avalia o **estado final
  combinado** (nível, tem-setor, tem-cargo), os dois sentidos caem no mesmo teste — não há dois códigos.
- **Admin (100) satisfaz ambas** — é o topo; nunca é barrado por R1/R2.
- **"Qualquer cargo" é qualquer cargo**, inclusive os **institucionais** (Presidente, Secretário,
  Tesoureiro, Conselhos — `CARGOS` com `institucional=true`, [GlossarioUsuarios.php:54-67](../../../app/Importacao/GlossarioUsuarios.php#L54-L67)).
  A medição (§4.3) confirma que **hoje 100% deles são Diretor** — a regra descreve o que já é verdade.
- **Departamento fica de fora de R1/R2.** O vínculo `departamento_usuario` é o eixo de *capacidade*
  (quem edita conteúdo), ortogonal ao par papel×estrutura desta fatia. A trava **não** olha departamento.

### Invariante crítico (o coração desta fatia)

**A validação TEM de morder no servidor, no momento da gravação — não só na UI.** Um `Select`
escondido, `disabled()` ou reativo **não protege** contra um POST forjado pelo `/livewire/update` (o
estado do formulário é do cliente até o servidor reassertar). **UX reativa é bônus; a garantia é
server-side, lendo o estado real que foi gravado.** Todo teste de invariante (§7) reprova de verdade um
POST que tente furar a trava com a UI desligada.

---

## 3. Terreno confirmado por leitura (não presumir diferente)

Verificado no código em 2026-07-17 (base `cfe3873`). **Docblock não é evidência** — o que segue foi lido no
fonte e no vendor.

### 3.1 A primitiva de nível já existe

[User::nivelMaximo()](../../../app/Models/User.php#L92-L96): `return (int) $this->roles->max('nivel');` —
maior nível entre os papéis; **0 se não tiver papel**. É a base de R1/R2. ⚠️ **Armadilha (§6.3):** lê a
**coleção `$this->roles` cacheada** — no `afterSave` do edit essa coleção é a de **antes** do sync. A trava
lê nível por **query fresca** (`$registro->roles()->max('nivel')`), **não** por `nivelMaximo()`.

Papel é único **por validação server-side** (não só UI): o `Select` de papel tem `->maxItems(1)`
([UserResource.php:91](../../../app/Filament/Resources/Users/UserResource.php#L91)) — que o Filament aplica
como *rule* Laravel `array`+`max:1` no `getState()`, **antes** do sync (`CanLimitItemsLength.php:14-37`), logo
um POST forjado com 2 papéis reprova ali; e `->required()` (`:93`). `roles.nivel` é `unsignedSmallInteger default 0`
([2026_07_03_105901_add_nivel_to_roles_table.php:14](../../../database/migrations/2026_07_03_105901_add_nivel_to_roles_table.php#L14)),
semeado por [EstruturaCemaSeeder.php:20-25](../../../database/seeders/EstruturaCemaSeeder.php#L20-L25) a partir de `PAPEIS`.

### 3.2 O formulário (onde a trava mora)

[UserResource::form](../../../app/Filament/Resources/Users/UserResource.php#L46-L135), `Section` **"Papel e
estrutura"** (`:82-112`), 4 `Select` de relação `->multiple()`:

| Campo | Linhas | Relação | Notas |
|---|---|---|---|
| `roles` (Papel) | :87-93 | `roles` | `->maxItems(1)->required()` — papel único |
| `setores` | :95-99 | `setores` | pivô `setor_usuario` (tem `funcao`) — **eixo de R1** |
| `cargos` | :101-105 | `cargos` | pivô `cargo_usuario` — **eixo de R2** |
| `departamentos` | :107-111 | `departamentos` | **fora de R1/R2** (eixo de capacidade) |

Hoje a `Section` **não tem nenhuma validação de integridade** entre esses campos. As relações do `User`:
[setores()](../../../app/Models/User.php#L48-L52) (`withPivot('funcao','desde')`),
[cargos()](../../../app/Models/User.php#L54-L57), [roles](../../../app/Models/User.php#L29) (trait `HasRoles`),
[departamentos()](../../../app/Models/User.php#L59-L62).

### 3.3 O pivô do setor tem `funcao` — **intocável nesta fatia**

[setor_usuario](../../../database/migrations/2026_07_03_000009_create_setor_usuario_table.php#L11-L18): PK
composta `(setor_id, user_id)`, coluna `funcao enum('membro','coordenador') default 'membro'` (`:14`). A
regra desta fatia é sobre **o PAPEL** (`roles`), **não** sobre a função no setor. **Não tocar** em `funcao`.
[cargo_usuario](../../../database/migrations/2026_07_03_000010_create_cargo_usuario_table.php#L11-L16): PK
`(cargo_id, user_id)`, sem coluna extra.

### 3.4 As âncoras server-side (as páginas já sobrescrevem o ciclo)

- [CreateUser](../../../app/Filament/Resources/Users/Pages/CreateUser.php) — `afterCreate()` (`:13-20`)
  audita papel/departamento (Fase D). **Roda depois do `saveRelationships`.**
- [EditUser](../../../app/Filament/Resources/Users/Pages/EditUser.php) — sobrescreve `save()` (`:25-33`,
  captura o "antes" por query fresca) **e** `afterSave()` (`:35-42`, audita o diff). **`afterSave` roda
  depois do `saveRelationships`.**

⇒ As duas páginas **já têm** o gancho pós-sincronização que a trava precisa. É o ponto que o kickoff apontou.

### 3.5 O ciclo do Filament v5 **pode** ser transacional — mas a transação é OPT-IN e está DESLIGADA (verificado no vendor)

`vendor/filament/filament/src/Resources/Pages/CreateRecord.php::create()` (núcleo `:86-134`; o método segue
até o commit em `:136`) e `.../EditRecord.php::save()` (`:159-204`), na ordem real:

```
create():                                   save():
  beginDatabaseTransaction()*  (:101)          beginDatabaseTransaction()*  (:164)   *no-op se flag OFF
  $data = form->getState()     (:105)          $data = form->getState()     (:168)
  mutateFormDataBeforeCreate() (:109)          mutateFormDataBeforeSave()   (:174)
  handleRecordCreation()       (:113)          handleRecordUpdate()         (:176)
  form->saveRelationships()    (:115)  ← sync  (no EDIT o sync roda dentro de getState(), ramo afterValidate
                                                — HasState.php:497 — antes de handleRecordUpdate e afterSave;
                                                handleRecordUpdate só faz $record->update($data))
  callHook('afterCreate')      (:117)          callHook('afterSave')        (:178)
  ...                                          ...
  catch (Throwable):                           catch (Throwable):
    rollBackDatabaseTransaction()               rollBackDatabaseTransaction()
    throw $exception           (:128-134)       throw $exception           (:187-190)
  commitDatabaseTransaction()                  commitDatabaseTransaction()
```

**Consequência — e a ARMADILHA que quase afunda o desenho.** `afterCreate`/`afterSave` rodam **depois** do
`saveRelationships` (papel/setores/cargos **já sincronizados**), dentro do `try`. Uma `ValidationException`
lançada ali cai no `catch (Throwable)`, que chama `rollBackDatabaseTransaction()` e **repropaga** (o Livewire
a captura e vira erro de form, §8.2).

> 🔴 **MAS a transação do Filament é OPT-IN e está DESLIGADA neste projeto** — sem ligá-la, o `rollBack` é
> **no-op** e a trava **vazaria** (grava o inválido e só mostra erro no form). Verificado no vendor
> (bloqueador achado no passe externo, §13):
> - `Pages/Concerns/CanUseDatabaseTransactions.php`: `begin`/`commit`/`rollBackDatabaseTransaction` fazem
>   `if (! $this->hasDatabaseTransactions()) return;` — **no-op com a flag off**. `hasDatabaseTransactions()`
>   = `$this->hasDatabaseTransactions ?? painel->hasDatabaseTransactions()`, e a propriedade da página nasce
>   **`null`**.
> - `Panel/Concerns/HasDatabaseTransactions.php`: default do painel **`false`**.
> - `grep databaseTransactions app/` = **ZERO** — o `AdminPanelProvider` **não** chama `->databaseTransactions()`.
>
> Sem a flag, o `saveRelationships` **já persistiu** (autocommit) e o `catch` não reverte nada. No **EDIT** é
> pior: o sync roda dentro de `getState()` (`HasState:497`), **antes de qualquer hook** — não há sequer ponto
> pré-persistência para barrar. **Só uma transação real reverte** ⇒ **a trava EXIGE ligar a flag** (§8.3).

Com a flag ligada nas duas páginas (§8.3), o `begin` abre transação real, o sync roda dentro dela e o
`catch (Throwable)` reverte de verdade ⇒ dá para **ler o estado real gravado** e **abortar o save inteiro**
sem depender de campo reativo, migration ou observer. **É o desenho do §6, agora com a peça que o sustenta.**

### 3.6 Toda conta nova já nasce com papel (o sem-papel é anomalia, não fluxo)

Os três caminhos que criam usuário atribuem papel:

- **Cadastro por senha:** [CreateNewUser.php:35](../../../app/Actions/Fortify/CreateNewUser.php#L35) —
  `$user->assignRole('frequentador')` (via `Fortify::createUsersUsing`, [FortifyServiceProvider.php:47](../../../app/Providers/FortifyServiceProvider.php#L47)).
- **Google OAuth (conta nova):** [GoogleController.php:59](../../../app/Http/Controllers/Auth/GoogleController.php#L59) —
  `$novo->assignRole('frequentador')`.
- **Importação do legado:** [ImportadorUsuarios.php:67](../../../app/Importacao/ImportadorUsuarios.php#L67) —
  `$user->syncRoles([$papel])`.

⇒ Em produção **não** deveria existir usuário sem papel. Os 4 do dev (§4.2) são **lixo de teste**, não fluxo
real. Isso muda o tratamento deles (§6.6).

### 3.7 Os testes que dirigem o formulário (não podem quebrar)

Dois arquivos exercitam as páginas `CreateUser`/`EditUser` via `Livewire::test`:

- [UsuarioResourceTest](../../../tests/Feature/Usuarios/UsuarioResourceTest.php): cria com
  `roles=[trabalhador]` (`:32-49`) e com `roles=[trabalhador]+departamentos=[DECOM]` (`:51-70`). **Sem setor,
  sem cargo.**
- [AuditoriaUserResourceTest](../../../tests/Feature/Autorizacao/AuditoriaUserResourceTest.php): cria com
  `roles=[diretor]+departamentos=[DECOM]` (`:34-65`); edita trocando papel diretor→trabalhador (`:67-82`);
  troca departamento (`:84-102`); edita só o `name` (`:104-116`). **Nenhum caso combina setor/cargo com papel
  baixo.**

⇒ **Nenhum teste existente cria uma combinação que a trava reprove.** A invariante "nenhum teste existente
muda de cor" (§7, I-neutro) se sustenta por construção — a trava só dispara em `setor∧nível<20` ou
`cargo∧nível<30`, que **nenhum** desses testes monta. (Provar isso é o teste I-neutro do §9.)

O helper de auditoria reusado no gancho: [AuditoriaAutorizacao::registrarPapelUsuario](../../../app/Support/Autorizacao/AuditoriaAutorizacao.php#L61-L64).

---

## 4. Medições no dev (banco real, somente leitura, 17/07/2026)

Consultas `SELECT` na conexão padrão (dev), via `tinker`. **Nenhuma escrita.** Confirmam o kickoff **e**
corrigem um ponto.

### 4.1 População e papéis

| Medida | Valor |
|---|---|
| **Total de usuários** | **152** |
| administrador | 1 |
| diretor | 29 |
| trabalhador | 49 |
| frequentador | 69 |
| **sem papel** | **4** |
| Usuários com **mais de um** papel (feriria `maxItems(1)`) | **0** |

### 4.2 Os 4 sem-papel — **achado: é lixo de teste, não conta de sistema nem membro real**

| # | Nome | E-mail | Setores | Cargos | Sinais |
|---|---|---|---|---|---|
| 148 | Cristopher Gottlieb | `debug@x.com` | — | — | e-mail de debug, `google_id=g-xyz` |
| 149 | Assunta Abshire | `debug2@x.com` | — | — | e-mail de debug |
| 150 | Nome Antigo | `roma63@example.net` | — | — | "Nome Antigo" + `example.net` (fixture de edição) |
| 155 | Quinten Mosciski | `leila.becker@example.org` | — | — | nome/e-mail Faker (`example.org`) |

**Correção ao enquadramento.** O kickoff propôs "atribuir Frequentador a esses 4, **salvo se algum for
conta de sistema**". A medição mostra terceira categoria que o enquadramento não previu: **nenhum é conta de
sistema, mas nenhum é membro real** — são resíduos de teste no dev (`debug@x.com`, e-mails `example.*`, nomes
Faker). Além disso, **nenhum dos 4 tem setor ou cargo** ⇒ **nenhum viola R1/R2** (R1/R2 falam de setor/cargo,
não de "ter papel"). Tratamento em §6.6; decisão do dono no §13.

### 4.3 Violações de R1/R2 hoje: **zero** (é prevenção)

| Regra | Definição da violação | Violações no dev |
|---|---|---|
| **R1** | tem ≥1 setor **e** `nivelMaximo < 20` (frequentador/sem-papel com setor) | **0** |
| **R2** | tem ≥1 cargo **e** `nivelMaximo < 30` (abaixo de diretor com cargo) | **0** |

- **Setor Médium: 46 membros = 29 trabalhador + 17 diretor** (bate exatamente com o kickoff). Nenhum
  frequentador, nenhum sem-papel.
- **Cargos ocupados** (todos só por **diretor**): Diretor do DDA(3), DED(2), DECOM(2), DEMAPA(2), DEPAE(2),
  DEPRO(2), DIJ(2); e institucionais **Presidente(2), Conselho Diretor(11), Conselho Fiscal(4),
  Secretário(2), Tesoureiro(2)** — **todos diretor**. ⇒ R2 vale para institucionais **sem** exceção hoje.

⇒ **A trava alarga/aperta 0 registros hoje.** É regra para o futuro, escrita + testada — não mutirão.

---

## 5. Decisões travadas (não reabrir)

1. **Escopo:** só a coerência **papel×setor (R1)** e **papel×cargo (R2)** no cadastro de usuário. Nada do
   módulo Mensagens; nada de `funcao` do `setor_usuario`; não criar/renomear papéis.
2. **A garantia é server-side, no save**, lendo o **estado real sincronizado** — não a UI (§6.1). **Depende de
   ligar a transação** (`$hasDatabaseTransactions = true`) **nas duas páginas** — sem ela o rollback é no-op e
   a trava vaza (§3.5/§8.3). Peça obrigatória, não bônus.
3. **Vale nos dois sentidos** (rebaixar papel × adicionar setor/cargo) — um único ponto de verdade.
4. **Admin (100) passa sempre**; sem-papel (0) e frequentador (10) não podem ter setor; abaixo de diretor
   (30) não pode ter cargo.
5. **Departamento fora de R1/R2.**
6. **Níveis vêm do glossário** (`PAPEIS['trabalhador']`, `PAPEIS['diretor']`) — **sem número mágico** no
   validador (§6.4).
7. **UX reativa é bônus, não a garantia**; se implementada, não pode deadlockar nem enfraquecer a trava
   (§6.5).
8. **Os 4 sem-papel são lixo de dev** (decisão do dono, 17/jul): **apagar** como fixtures de teste — one-off
   de dev, confirmando cada um, **não** comando `cema:*` de cutover (prod não tem sem-papel) (§6.6).
9. **Zero migrations, zero schema novo** — a trava é código de aplicação sobre tabelas existentes.

---

## 6. Decisões de desenho

### 6.1 A âncora: validar **pós-sync**, ler a verdade, abortar com rollback

A trava vive em **um** ponto de verdade, chamado das duas páginas **dentro da transação** (que a fatia
**liga** nas duas páginas — §3.5/§8.3), **depois** do `saveRelationships`:

- `CreateUser::afterCreate()` e `EditUser::afterSave()` chamam, **no topo**, `IntegridadePapel::assegurar($this->record)`.
- `assegurar` lê o **estado real gravado** (papel/setores/cargos já sincronizados) e, se ferir R1/R2, lança
  `ValidationException`. Com a transação ligada, o `catch (Throwable)` do Filament (`:128`/`:187`) faz
  **rollback** (desfaz o registro e o sync) e **repropaga** ⇒ o Livewire mostra erro de formulário e **nada
  persiste**. (Sem a flag, o `rollBack` é no-op e isto vazaria — por isso a flag é peça obrigatória, §8.3.)

**Por que pós-sync e não pré-persistência (`mutateFormDataBefore*`).** Ler `$data` antes de gravar tem uma
armadilha: se um campo (ex.: `setores`) for reativamente `disabled()`/`hidden()`, o Filament **não o
desidrata** e ele **some de `$data`** — então, ao **rebaixar** um papel deixando o setor no pivô, a
pré-validação veria "sem setor" (falso) e **deixaria passar uma linha stale** no `setor_usuario`. Ler o
estado **pós-sync** (o que de fato está no pivô) é **imune** a isso: qualquer que tenha sido a UI, `assegurar`
vê a verdade. A transação **ligada** (§8.3) torna o "gravar e reverter" seguro e determinístico. **É o desenho
mais conservador** (CLAUDE.md §36): a garantia não depende de nenhum estado de UI, presente ou futuro — só de
a flag estar ligada, o que um teste-guardrail trava (§9).

### 6.2 Um validador puro + um assegurador (testável isolado)

`App\Support\Usuarios\IntegridadePapel`:

- `violacoes(int $nivel, bool $temSetor, bool $temCargo): array` — **pura**, retorna mensagens pt-BR (vazio =
  íntegro). É a **tabela-verdade** de R1/R2, testável sem banco (§9.1).
- `assegurar(User $registro): void` — lê o estado real por **queries frescas** e lança se `violacoes` não for
  vazio.

### 6.3 Armadilha do `nivelMaximo()` no `afterSave`

`nivelMaximo()` lê `$this->roles` (**coleção cacheada**). No `afterSave` do edit, essa coleção é a de
**antes** do sync ⇒ usá-la leria o papel **antigo**. Por isso `assegurar` lê nível por **query**:
`(int) $registro->roles()->max('nivel')`; e tem-setor/tem-cargo por `->setores()->exists()` /
`->cargos()->exists()` (também queries frescas). É o mesmo cuidado que o [EditUser::save()](../../../app/Filament/Resources/Users/Pages/EditUser.php#L27-L30)
já toma ao capturar o "antes" por query.

### 6.4 Níveis do glossário, sem número mágico

`NIVEL_MIN_SETOR = GlossarioUsuarios::PAPEIS['trabalhador']` (20) e
`NIVEL_MIN_CARGO = GlossarioUsuarios::PAPEIS['diretor']` (30). Se a hierarquia mudar, a trava acompanha.

### 6.5 UX reativa (bônus) — como fazer sem deadlock

A garantia (§6.1) já basta. Se o plano quiser UX reativa (papel `->live()` que orienta os campos):

> ⚠️ **Deadlock a evitar.** Se ao baixar o papel os campos `setores`/`cargos` forem apenas `disabled()`, o
> admin **não consegue removê-los** para tornar o save válido — e o `disabled()` que os some de `$data` não
> os **desassocia** do pivô (armadilha da §6.1). Portanto, se houver reação, ela deve **limpar** (setar `[]`
> via `afterStateUpdated`), **não** só desabilitar. A opção mais simples e sem perda silenciosa é **não
> reagir**: manter os campos sempre habilitados e deixar a mensagem da trava orientar ("remova os setores ou
> eleve o papel"). **A escolha do sabor de UX é decisão do plano/execução** (§13) — a garantia não muda.

### 6.6 Os 4 sem-papel — one-off de dev, não comando de cutover

Como todo caminho de criação já atribui papel (§3.6), **prod não tem usuário sem papel** — um comando
`cema:*` "normalizar" seria **no-op no cutover**. Os 4 são resíduo de dev. **Decisão do dono (17/jul):
apagar os 4 como lixo de teste.** Logo:

- **Não** criar comando `cema:*` versionado (o molde reserva `cema:*` a correções que sobrevivem ao cutover;
  aqui não há o que correr em prod — os 4 não existem lá).
- Resolver **no dev** como **one-off de exclusão**, **confirmando cada um dos 4 antes de apagar** (são
  `debug@x.com` #148, `debug2@x.com` #149, `Nome Antigo/roma63@example.net` #150,
  `Quinten Mosciski/leila.becker@example.org` #155 — todos fixtures debug/Faker, §4.2). A exclusão cascateia
  os pivôs por FK (`cascadeOnDelete` em `setor_usuario`/`cargo_usuario`), mas os 4 **não têm** setor/cargo.
  **Fora do versionamento de código** e **fora da suíte** (é higiene de dev, não regra).
- ⚠️ **Guarda dura:** exclusão manual, pontual, na conexão **padrão (dev)**; **jamais** `migrate:*`
  destrutivo, `db:wipe` ou factory destrutivo ([[nunca-migrate-fresh-no-dev]]). Reconferir a lista no dev
  imediatamente antes (as consultas são read-only) — se surgir um 5º sem-papel que **não** seja fixture,
  parar e reavaliar.
- **Nenhuma mudança de código** é necessária para impedir novos sem-papel via `/admin`: o `Select` de papel
  já é `->required()` ([UserResource.php:93](../../../app/Filament/Resources/Users/UserResource.php#L93)).

---

## 7. Invariantes (cada um vira teste que reprova)

| # | Invariante | Teste (§9) |
|---|---|---|
| **I0** | **Transação ligada (guardrail):** com `$hasDatabaseTransactions = true` nas duas páginas, um save inválido **não persiste** (rollback real). **Sem** a flag, I1/I2/I3/I5 ficam **vermelhos** — são a prova viva de que a flag está ligada e pegam sua remoção acidental (§3.5/§8.3). | §9.5 |
| **I1** | **R1 server-side:** salvar usuário com ≥1 setor e papel `nivel<20` (frequentador **ou** sem-papel) ⇒ **abortado, nada persiste**. Vale no `create` **e** no `edit`. | §9.2 / §9.3 |
| **I2** | **R2 server-side:** salvar usuário com ≥1 cargo e papel `nivel<30` (trabalhador/abaixo) ⇒ **abortado, nada persiste**. `create` e `edit`. | §9.2 / §9.3 |
| **I3** | **Morde ao REBAIXAR:** trabalhador **com setor** editado para frequentador ⇒ abortado; o pivô `setor_usuario` **não** fica com a linha stale (rollback). Idem diretor-com-cargo → trabalhador. | §9.3 |
| **I4** | **Morde ao ADICIONAR:** frequentador editado para ganhar setor ⇒ abortado; trabalhador editado para ganhar cargo ⇒ abortado. | §9.3 |
| **I5** | **POST forjado não fura** (o invariante crítico do §2): dirigir o componente com a UI "desligada" (estado forjado combinando setor/cargo com papel baixo) ⇒ **abortado** pela leitura pós-sync, não pela UI. | §9.4 |
| **I6** | **Admin passa:** administrador (100) com setor **e** cargo salva normalmente. | §9.2 |
| **I7** | **Caso feliz intacto:** trabalhador+setor salva; diretor+cargo salva; diretor+setor salva. A trava **não** cria falso-positivo. | §9.2 |
| **I8** | **Auditoria atômica (com a flag):** save válido ⇒ auditoria de papel/departamento (Fase D) sai igual à de hoje; save abortado ⇒ o rollback desfaz **também** o auto-log e os `registrar*` (nenhum log órfão). | §9.3 |
| **I-neutro** | **Nenhuma asserção de teste existente muda de cor** (§3.7). A suíte fica verde (857 + novos). | §9.6 |

---

## 8. As peças

### 8.1 `App\Support\Usuarios\IntegridadePapel` (novo)

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-17

namespace App\Support\Usuarios;

use App\Importacao\GlossarioUsuarios;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Trava de integridade papel × estrutura no cadastro de usuário.
 * R1: setor exige papel >= Trabalhador. R2: cargo exige papel >= Diretor.
 * A GARANTIA é server-side: assegurar() lê o estado real gravado e aborta o save.
 */
final class IntegridadePapel
{
    private const NIVEL_MIN_SETOR = GlossarioUsuarios::PAPEIS['trabalhador']; // 20
    private const NIVEL_MIN_CARGO = GlossarioUsuarios::PAPEIS['diretor'];     // 30

    /** @return list<string> mensagens de violação (vazio = íntegro). Pura — testável sem banco. */
    public static function violacoes(int $nivel, bool $temSetor, bool $temCargo): array
    {
        $violacoes = [];

        if ($temSetor && $nivel < self::NIVEL_MIN_SETOR) {
            $violacoes[] = 'Um usuário com setor precisa ter papel Trabalhador ou acima. '
                . 'Remova os setores ou eleve o papel.';
        }

        if ($temCargo && $nivel < self::NIVEL_MIN_CARGO) {
            $violacoes[] = 'Um usuário com cargo precisa ter papel Diretor. '
                . 'Remova os cargos ou eleve o papel.';
        }

        return $violacoes;
    }

    /**
     * Lê o estado REAL sincronizado (queries frescas — nunca a coleção cacheada de nivelMaximo,
     * que no afterSave é a de antes do sync) e aborta o save se ferir R1/R2.
     */
    public static function assegurar(User $registro): void
    {
        $violacoes = self::violacoes(
            (int) $registro->roles()->max('nivel'),
            $registro->setores()->exists(),
            $registro->cargos()->exists(),
        );

        if ($violacoes !== []) {
            // Repropagada pelo catch(Throwable) do Filament => rollback do save + erro no form.
            throw ValidationException::withMessages(['data.roles' => $violacoes]);
        }
    }
}
```

### 8.2 O gancho nas duas páginas (duas mudanças por página)

```php
// EM AMBAS as páginas (CreateUser E EditUser) — LIGA a transação; sem isto o rollback é no-op (§3.5):
protected ?bool $hasDatabaseTransactions = true;

// CreateUser::afterCreate — 1ª linha ADICIONADA, ANTES da auditoria existente (NÃO substituir o método):
IntegridadePapel::assegurar($this->record);

// EditUser::afterSave — 1ª linha ADICIONADA, ANTES da auditoria existente (NÃO substituir o método):
IntegridadePapel::assegurar($this->record);
```

**As duas mudanças, nada além:**

1. **`protected ?bool $hasDatabaseTransactions = true;`** — liga a transação **só** nestas duas páginas (a
   propriedade da página vence o painel; **não** ligar no `AdminPanelProvider`, que afetaria
   Eventos/Palestras/Agenda/Matriz — fora de escopo e risco). É o que torna o `rollBack` do `catch` **real**;
   sem ela a trava **vaza** (§3.5). É **peça obrigatória**, não bônus.
2. **`IntegridadePapel::assegurar($this->record)` como 1ª linha** de `afterCreate`/`afterSave`, **acrescentada
   acima** da auditoria da Fase D — **não** substituir o método (senão somem
   `registrarPapelUsuario`/`registrarDepartamentosUsuario`, [CreateUser.php:15-19](../../../app/Filament/Resources/Users/Pages/CreateUser.php#L15)/[EditUser.php:37-41](../../../app/Filament/Resources/Users/Pages/EditUser.php#L37)).
   Como lança **antes** dos `registrar*`, um save reprovado nem audita — e, com a flag, o rollback desfaz o
   auto-log e os `registrar*` de todo modo (atomicidade — I8).

> **Nota de mecanismo (CONFIRMADO no vendor pelo passe adversarial).** A cadeia foi verificada ponta a ponta:
> o Filament repropaga a `ValidationException` (`CreateRecord.php:133`/`EditRecord.php:190`); o Livewire a
> captura em `SupportValidation::exception()` → `setErrorBag` + **para a propagação** (sem HTTP 500) → vira
> erro de formulário. **Mapeamento de chave (cravado, é armadilha):** lançar com a chave **completa**
> `data.<campo>` (statePath `data` + campo — ex.: `data.roles`) e **assertar com `assertHasFormErrors(['roles'])`**,
> porque o Filament prefixa `data.` sozinho (`TestsForms.php:101-113`). ⚠️ **Footgun:**
> `assertHasFormErrors(['data.roles'])` vira `data.data.roles` e **falha**; lançar com a chave crua `roles`
> também quebra o assert. O **invariante duro** testado continua sendo "**nada persiste**" (rollback). Plano B
> equivalente, se um dia preciso: `Notification::danger()` + `throw (new Halt)->rollBackDatabaseTransaction()`
> (fluente, `Halt.php:11-15`; o `catch (Halt)` do Filament também reverte).

### 8.3 Cabeçalho/idioma

Arquivo novo com cabeçalho de autoria (CLAUDE.md §8); mensagens em pt-BR; `Pint` antes do push
([[pint-antes-de-push]]).

---

## 9. Plano de teste (o que a fatia deve provar)

### 9.1 Unitário — tabela-verdade de `violacoes()` (pura, sem banco)

`tests/Unit/IntegridadePapelTest.php`. Varre a matriz `(nivel ∈ {0,10,20,30,100}) × temSetor × temCargo`:

- `nivel 0/10` + setor ⇒ **1 violação de R1**; `nivel≥20` + setor ⇒ **ok**.
- `nivel 0/10/20` + cargo ⇒ **violação de R2**; `nivel≥30` + cargo ⇒ **ok**.
- `nivel 10` + setor + cargo ⇒ **2 violações** (R1 e R2).
- `nivel 100` (admin) + setor + cargo ⇒ **ok** (I6).
- sem setor e sem cargo ⇒ **ok** em qualquer nível (cobre os 4 sem-papel — não violam).

### 9.2 Filament — `create` (molde `UsuarioResourceTest`/`AuditoriaUserResourceTest`)

`Livewire::test(CreateUser::class)->fillForm([...])->call('create')`:

- **I1/I2 (reprova):** `roles=[frequentador]+setores=[Médium]` ⇒ `assertHasFormErrors(['roles'])` (**nunca**
  `['data.roles']` — o Filament prefixa `data.` sozinho, §8.2) **e** `User::where(email)` **não existe**
  (rollback). Idem `roles=[trabalhador]+cargos=[algum]`.
- **I6:** `roles=[administrador]+setores=[..]+cargos=[..]` ⇒ `assertHasNoFormErrors`, usuário criado com os
  vínculos.
- **I7 (caso feliz):** `roles=[trabalhador]+setores=[Médium]` ⇒ criado; `roles=[diretor]+cargos=[Diretor do
  DED]` ⇒ criado.

### 9.3 Filament — `edit` nos dois sentidos + rollback do pivô

`Livewire::test(EditUser::class, ['record'=>...])`:

- **I3 (rebaixar):** trabalhador **com setor Médium** → `roles=[frequentador]` ⇒ abortado; reconsultar
  `setor_usuario` do user ⇒ **ainda 1 linha** (o save reverteu; o vínculo do estado inicial permanece, o papel
  **não** virou frequentador). Idem diretor-com-cargo → trabalhador.
- **I4 (adicionar):** frequentador **sem setor** → `setores=[Médium]` ⇒ abortado, sem vínculo criado.
  trabalhador **sem cargo** → `cargos=[..]` ⇒ abortado.
- **I8:** um edit válido (ex.: diretor+cargo, troca só o `name`) mantém as entradas de auditoria de papel/depto
  idênticas às de hoje; um edit abortado **não** grava entrada em `activity_log` (contar antes/depois).

### 9.4 Server-side / POST forjado (I5 — o invariante crítico)

Reproduzir "UI desligada": dirigir o componente setando o estado dos campos **diretamente** (via `set('data.setores', [$medium])`
+ `set('data.roles', [$frequentador])`, sem passar por reação de UI) e `call('create'|'save')` ⇒ **abortado,
nada persiste**. Prova que a garantia é a leitura pós-sync, não o `Select`. (Se o plano adotar UX reativa que
`disabled()` os campos, este teste **deve** forjar o estado mesmo assim e continuar reprovando.)

### 9.5 Guardrail da transação (I0 — trava o bloqueador O1)

O desenho pós-sync só não vaza porque a transação está **ligada** (§3.5/§8.3). O teste que prova isso é o
próprio "nada persiste": um `create`/`edit` inválido (I1/I2) **não** deixa registro/pivô. Para blindar contra
remoção acidental da flag, cravar em pelo menos um caso a expectativa explícita: **sem**
`$hasDatabaseTransactions = true`, esse mesmo teste **falharia** (o inválido persistiria). Opções, da mais
simples à mais robusta:

- **(mínimo)** confiar que I1/I2/I3/I5 já pegam o vazamento (sem a flag, `User::where(email)` existiria e
  `assertNoDatabaseRecord`/pivô falhariam) — é a "prova viva" do revisor.
- **(explícito, recomendado)** um teste dedicado que asserta `app(CreateUser::class)->hasDatabaseTransactions()
  === true` (e idem `EditUser`), documentando a dependência como contrato — barato e imune a "por que esse
  teste existe?".

### 9.6 Regressão (I-neutro) + suíte

- Rodar **os testes existentes** de `UsuarioResourceTest` e `AuditoriaUserResourceTest` **sem alteração** —
  todos verdes (§3.7). **Qualquer** necessidade de editar uma asserção existente ⇒ a trava está errada.
- **Baseline: 857 testes** em `cfe3873` (medido por `artisan test --list-tests`; ⚠️ `.superpowers/sdd/progress.md`
  está congelado na Fase D em **798** e **não** é a fonte). Alvo: **857 + novos**, sem asserção existente
  mudando de cor.
- `docker compose exec -T app php artisan test` + `Pint` verdes no container ([[pint-antes-de-push]]).
  Ciência [[flaky-importadorblog-gd-cap-imagem]]: 2 testes de cap de imagem do blog podem falhar sob carga —
  se passam isolados/no CI, não é regressão desta fatia.

---

## 10. Fora de escopo (não fazer nesta fatia)

- **Qualquer coisa do módulo Mensagens** (model, migração, visibilidade "quem vê", curadoria, lançamento) —
  são as próximas fatias da Camada 4.
- **`setor_usuario.funcao`** (membro/coordenador) — a regra é sobre o **papel**, não a função no setor.
- **Criar/renomear papéis**; mexer na hierarquia de `PAPEIS`.
- **Departamento×papel** — departamento é o eixo de capacidade, ortogonal a R1/R2.
- **Policies, trait `AutorizaPorDepartamento`, matriz, pivôs de conteúdo, `Gate::before`** — intocados.
- **Definição operacional de "médium"** (setor Médium ∧ papel≥Trabalhador) como *consulta* do módulo — nasce
  na próxima fatia; esta só **garante o dado** de que ela dependerá.

---

## 11. Fronteiras: o que toca × o que NÃO toca

**Toca (novo):** `App\Support\Usuarios\IntegridadePapel` (validador + assegurador) · em **`CreateUser` e
`EditUser`**, por página: `$hasDatabaseTransactions = true` **+** 1 linha `assegurar(...)` no gancho · testes
novos (§9). **One-off de dev** para os 4 sem-papel (§6.6), fora do versionamento de código.

**NÃO toca:** `UserResource::form` (o `Select` de papel já é `required`/`maxItems(1)`; **sem** reação
obrigatória) · migrations/schema (**0 migrations**) · `User`/`Setor`/`Cargo` (models) · `GlossarioUsuarios`
(só **lê** `PAPEIS`) · `AuditoriaAutorizacao` · policies/trait/matriz/`Gate::before` · `setor_usuario.funcao` ·
o **painel** (`AdminPanelProvider`) — a transação liga **só nas duas páginas**, não global.

---

## 12. Ciências (não são tarefa desta fatia)

- **A garantia cobre a única superfície INTERATIVA / de formulário que grava papel/setor/cargo: o cadastro do
  `/admin` (UserResource).** Ela **não** é o único *escritor* desses vínculos — **passam ao largo** (como
  qualquer invariante de aplicação): a **importação do legado** `cema:importar-usuarios`
  ([ImportadorUsuarios.php:67,77,87](../../../app/Importacao/ImportadorUsuarios.php#L67) — grava papel **+**
  setor **+** cargo juntos, **sem** validar R1/R2; é a via de bypass mais substantiva, hoje inócua só porque o
  legado atual é coerente, §4.3), comandos `cema:*` (ex.: `CorrigirPapelDiretores`, que só **eleva**
  trabalhador→diretor, rumo à conformidade — não vaza), `AdminSeeder` (só papel 100) e escrita direta
  (tinker/seed). **Se um re-import trouxer legado divergente, a violação entraria sem passar pela trava** —
  fica como **vigilância** (a trava é do cadastro, não da importação; não é tarefa desta fatia).
- **A trava está ancorada nas PÁGINAS (`afterCreate`/`afterSave`), não num Schema de form compartilhado.** O
  form de `User` tem **um** consumidor (o `/admin`), então basta hoje. Mas um futuro consumidor de form
  compartilhado (ex.: superfície não-admin da Camada 4) **não herda a trava** — terá de chamar
  `IntegridadePapel::assegurar` explicitamente (o assegurador é agnóstico de página). Vigiar — alinha com o
  princípio "fonte única" do CLAUDE.md.
- **Reação de UI é decisão do plano** (§6.5) — a garantia não depende dela; se adotada, limpar (não só
  desabilitar) para não deadlockar.
- **Institucionais em R2:** hoje 100% dos ocupantes de cargo institucional são diretor (§4.3). Se algum dia o
  negócio quiser um Secretário/Tesoureiro **não-diretor**, R2 barra — e a regra teria de ser revista **de
  propósito** (decisão de negócio, não bug). Registrado.
- **`nivelMaximo()` continua útil** para leitura (coleção já carregada); a trava só evita usá-lo no
  `afterSave` por causa do cache pré-sync (§6.3).

---

## 13. Passe adversarial (17/jul) — veredito e pendências

> **Dois passes. Veredito final: ✅ APROVADO após corrigir o bloqueador O1.**
>
> **(1) Passe interno (5 verificadores):** 4 `APROVADO_COM_AJUSTES` + 1 `APROVADO`. Confirmou o núcleo do
> desenho (leitura por query fresca; imunidade à armadilha do campo reativo — `BelongsToModel.php:47/51`,
> disabled/hidden pulam o sync ⇒ a leitura fresca vê a linha stale e reprova; `maxItems(1)` server-side; cadeia
> `ValidationException → rollback → erro de form` com o mapeamento de chave da §8.2) e pegou ajustes de citação
> (`:186`→`:187`; sync do edit em `HasState:497`) + o gap do importador (§12). ⚠️ **Ponto cego:** validou
> "ciclo transacional" checando só que `beginDatabaseTransaction()` é **chamado** — não que ele é **no-op** sem
> a flag.
>
> **(2) Passe externo (consultor):** 🔴 **1 BLOQUEADOR (O1)** — a transação do Filament é **OPT-IN e está
> DESLIGADA** neste projeto, então o `rollBack` do desenho seria no-op e a trava **vazaria** (gravaria o
> inválido). **Verificado por mim no vendor e corrigido.** O resto do passe **confirma** o desenho.

**Do passe externo — aplicados:**

- **O1 (BLOQUEADOR → RESOLVIDO):** §3.5 reescrito (transação opt-in/off, verificado em
  `CanUseDatabaseTransactions`/`HasDatabaseTransactions` + `grep` zero em `app/`); §8.2 passa a **ligar**
  `protected ?bool $hasDatabaseTransactions = true;` nas **duas** páginas (local, não no painel); §9.5 (I0) é o
  teste-guardrail. ✅
- **R1 (aplicado):** `assegurar` entra como **1ª linha acima** da auditoria da Fase D, **sem** substituir o
  método (§8.2). ✅
- **R2 (aplicado):** ligar a transação torna a auditoria da Fase D **atômica** — I8/§9.3 cobrem (save válido
  audita igual; abortado não deixa órfão). ✅
- **R3 (aplicado):** I3/§9.3 asseveram **explicitamente** o rebaixar-com-setor (o pivô `setor_usuario` fica
  intacto após o abort — protege contra a `UserFactory` anexar setor no futuro). ✅
- **R4 (a fechar no TDD):** mapeamento `data.roles → assertHasFormErrors(['roles'])` (§8.2) e imunidade ao
  campo reativo (§6.1) — verificados no passe interno; com O1 ligado a cadeia fecha. Confirmar rodando.

**Pendências / decisões:**

1. **~~Decisão do dono — os 4 sem-papel~~ (RESOLVIDO, 17/jul):** o dono escolheu **apagar** os 4 como lixo de
   teste (§6.6). One-off de dev, cada um confirmado antes; fora do versionamento e da suíte. ✅
2. **~~Mecanismo de abort~~ (RESOLVIDO no passe):** confirmado no vendor que
   `ValidationException::withMessages(['data.roles'=>...])` no `afterCreate`/`afterSave` reverte a transação e
   vira erro de form. Plano B (`Notification`+`Halt`) fica só como alternativa. ✅ (§8.2)
3. **~~`assertHasFormErrors` × path~~ (RESOLVIDO):** lançar com `data.roles` e **assertar `['roles']`** (o
   Filament prefixa `data.` sozinho, `TestsForms.php:101-113`); `['data.roles']` viraria `data.data.roles` e
   falharia. ✅ (§8.2/§9.2)
4. **Sabor de UX reativa (§6.5):** decidir no plano entre "sem reação + mensagem clara" (mais simples, sem
   perda silenciosa) e "reação que limpa setores/cargos ao baixar o papel". Nunca "só `disabled()`".
5. **Regra sempre:** pt-BR em tudo; cabeçalho de autoria no PHP novo; `Pint` antes do push;
   `docker compose exec -T app php artisan test`; **0 migrations**; **todo brief de subagente que rode
   `artisan` DEVE proibir `migrate:fresh`/`refresh`/`wipe`/`reset` e seed destrutivo** (o dev tem 152
   usuários + 123 palestras/agenda + 44 posts + mídia importados) e reafirmar a conexão `legado` como
   read-only. Ver [[nunca-migrate-fresh-no-dev]].
