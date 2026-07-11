# Spec — Fase A · Modelo de Capacidades

> Autoria: Thiago Mourão — https://github.com/MouraoBSB — 2026-07-11
> Enquadramento travado com o dono em sessão (dono + consultor). Este spec **não** improvisa
> além das decisões travadas; os pontos que o enquadramento não previu foram cravados por
> pergunta (ver §3) ou marcados como "a confirmar no passe adversarial" (ver §12).
> Destino: **SPEC** — vai ao passe adversarial **antes** de virar plano de implementação.

## 1. Contexto e objetivo

O modelo de usuário do CEMA separa dois eixos ortogonais (ver memória `modelo-capacidades-cema`):

- **VISIBILIDADE** — *quem vê o publicado*. Já existe e **não se toca**: `roles.nivel`,
  `User::nivelMaximo()`, `Evento::podeSerVistoPor()` / `scopeVisiveisPara()`.
- **CAPACIDADE** — *quem edita*. **Não existe ainda.** É o que esta fase institui.

O `/admin` é e continua **exclusivo de administrador** (`User::canAccessPanel()` = `hasRole('administrador')`,
portão único). A edição por trabalhadores e diretores acontecerá **fora** do painel, nos formulários
do site em `/minha-conta` (Fase D). Esta fase entrega a **fundação server-side** dessa edição: o
vocabulário de permissões, o portão do admin no Gate, o vínculo editorial usuário→departamento e as
policies que combinam permissão + escopo de departamento. Nada disso muda o comportamento do `/admin`
(o admin passa por cima de tudo no `Gate::before`); as policies só passam a "morder" quando os forms
do site existirem, na Fase D.

Esta fase **absorve a antiga "Fase 4" do módulo Eventos** (a camada de capacidade que ficara adiada).

## 2. Terreno confirmado por leitura (não presumir diferente)

Verificado no código em 2026-07-11:

- **spatie/laravel-permission**, guard `web`, **teams OFF** (`config/permission.php` → `'teams' => false`),
  **wildcard OFF** (`'enable_wildcard_permission' => false`). `register_permission_check_method` está em
  `true` hoje (`config/permission.php:108`) — a **decisão 3.0 o desliga** (ver §3).
- Papéis semeados em `EstruturaCemaSeeder` via `Role::updateOrCreate(['name'=>$slug,'guard_name'=>'web'],['nivel'=>$nivel])`,
  a partir de `GlossarioUsuarios::PAPEIS` (`frequentador`=10, `trabalhador`=20, `diretor`=30, `administrador`=100).
- **8 departamentos** (`GlossarioUsuarios::DEPARTAMENTOS`: DAS, DDA, DED, DEMAPA, DEPAE, DEPRO, DIJ, DECOM).
- `User` usa `HasRoles`; tem `setores()`, `cargos()`, `atributos()`, `perfil()`; **não** tem `departamentos()`.
- **Nenhum `Gate::before` autoral** hoje. Com `register_permission_check_method` ligado, o **spatie
  instala o seu em runtime** (`PermissionRegistrar::registerPermissions()` → `checkPermissionTo($ability) ?: null`),
  que resolve **nomes crus de permissão** como abilities de gate — vetor de escalonamento. A **decisão 3.0
  desliga o flag**, eliminando esse `Gate::before` (e a classe de bypass) **na raiz**; o único `Gate::before`
  do sistema passa a ser o do admin (autoral, §6.1).
- **Sem `AuthServiceProvider`** → policies **auto-descobertas** por convenção (Laravel 11+). Providers
  registrados: `AppServiceProvider`, `FortifyServiceProvider` e `Filament\AdminPanelProvider`.
- Só **`Evento`** tem departamento: `Evento::departamentos()` = `belongsToMany(Departamento, 'departamento_evento')`.
  `Palestra`, `Post` e os models de Agenda **não** têm coluna/relação de departamento.
- **`departamento_usuario` não existe** (existe `departamento_evento`, `setor_usuario`, `cargo_usuario`).
- ⚠️ **A `EventoPolicy` já existe** e é de **visibilidade**: `view()` delega a `podeSerVistoPor`;
  `viewAny()` **retorna `true`** (a listagem é filtrada por `scopeVisiveisPara` na *query*, não na policy).
  Está **em uso** — `Gate::forUser($u)->allows('view',$evento)` em
  `tests/Feature/Eventos/VisibilidadeEventoAcessoTest.php` e `@can('view',$evento)` previsto nas views.
  As capacidades desta fase **coabitam** essa policy (ver §3.1 e §7).

## 3. Decisões travadas

### 3.0 Do enquadramento (dono + consultor)

0. **Desligar `register_permission_check_method`** (`config/permission.php` → `false`). Elimina **na raiz**
   o `Gate::before` que o spatie instala e que resolvia **nomes crus de permissão** como ability de gate
   (o vetor de escalonamento). Simplifica a fundação. Consequências:
   - **(A)** Some a classe inteira de bypass. O único caminho de autorização por objeto passa a ser a
     ability da policy (`check('editar', $evento)`); o nome cru (`can('evento.editar', $evento)`) passa a
     **negar** — não há policy nem gate que o resolva. Provado no §9.5. A antiga "dualidade perigosa"
     (ability de policy × nome cru) **deixa de existir** (ver a nota ao fim do §7).
   - **(B, crítica)** Com o flag OFF, `$user->can('evento.editar')` **não resolve mais**. Portanto, **dentro
     das policies**, a posse da permissão é checada por **`$user->hasPermissionTo('recurso.acao')`** (método
     direto do trait), **nunca** por `$user->can(...)`. Cravado no §6.4.
   - **(C)** Sinais de menu/aba (Fase D, sem objeto) usam **`$user->hasPermissionTo('recurso.ver')`** — não o Gate.
   - O único `Gate::before` do sistema passa a ser o **do admin** (autoral, §6.1), independente do flag.

1. **Vocabulário — 16 permissions**, convenção `recurso.acao` em pt-BR.
   - Recursos: `evento`, `palestra`, `post`, `agenda`. Ações: `ver`, `criar`, `editar`, `excluir`.
   - Nascem de **uma constante declarativa** (molde de `GlossarioUsuarios::PAPEIS`) → seeder idempotente
     (`Permission::updateOrCreate`, guard `web`).
   - **`palestrante` e `biblioteca` ficam FORA** (decisão pendente do "Meu Perfil").
   - **`ver` = acessar a aba e listar na área de edição; NÃO é visibilidade pública.**
2. **`Gate::before`** em `AppServiceProvider::boot()`: `administrador` ⇒ `true` (passa tudo);
   demais ⇒ `null` (deixa a policy decidir).
3. **Vínculo editorial dedicado**: pivot **nova** `departamento_usuario (user_id, departamento_id)`,
   migration **incremental**, **nasce vazia**. `User::departamentos()` = **fonte única** do
   "departamento do usuário" nas policies. **Não** fazer agora backfill de diretores nem tela de
   atribuição (Fase B/C).
4. **Escopo nas policies — filtro de objeto, spatie puro**: a permissão é por papel; o departamento
   **não** entra na atribuição (teams OFF). Regra: a ação exige a **permissão** *E* o **objeto pertencer
   a um departamento de `User::departamentos()`**. **Fail-closed**: objeto sem departamento ⇒ só admin.
   Extrair o molde em **trait `AutorizaPorDepartamento`** + **contrato `TemDepartamento`** no model
   (não repetir a regra em 4 lugares).
5. **As 4 policies já nesta fase**: `EventoPolicy` com filtro real (Evento tem depto N:N);
   `Palestra`/`Post`/`Agenda` sem coluna de depto ⇒ filtro vazio ⇒ **fail-closed** (só admin) até a Fase B.
   Como o `/admin` é admin-only e o admin passa no `Gate::before`, **nada muda no painel**; as policies só
   mordem nos forms do site (Fase D). Provar por **teste de unidade** (`Gate::forUser` com usuário
   fabricado), **não** pela tela.

### 3.1 Cravadas por pergunta neste spec (pontos que o enquadramento não previu)

- **Nomes das abilities de capacidade × policy de visibilidade existente** → *pt-BR, na mesma policy.*
  Os métodos de capacidade `ver`/`criar`/`editar`/`excluir` (pt-BR) **convivem** com `view`/`viewAny`
  (visibilidade, inglês) na **mesma** `EventoPolicy`. Não há colisão de nome (`ver` ≠ `view`). Casa com a
  convenção `recurso.acao` pt-BR. Checagem nos testes e na Fase D: `Gate::forUser($u)->check('editar', $evento)`.
  **Dualidade a documentar e vigiar** (ver §7): `@can('view',$e)` = visibilidade pública;
  `@can('ver',$e)` = capacidade de edição.
- **Onde semear as 16 permissions** → *seeder dedicado novo* `CapacidadesSeeder`, registrado no
  `DatabaseSeeder` (isola o vocabulário de capacidade da estrutura organizacional).

## 4. Vocabulário — as 16 permissions

Constante declarativa nova (fonte única) — mesmo **padrão** de `GlossarioUsuarios` (uma constante que
declara o vocabulário), mas em **local próprio**: `App\Support\Autorizacao` (não `App\Importacao`, onde vive
`GlossarioUsuarios` por ser insumo de importação):

```
App\Support\Autorizacao\GlossarioCapacidades
  RECURSOS = ['evento', 'palestra', 'post', 'agenda']   // palestrante/biblioteca FORA (pendente Meu Perfil)
  ACOES    = ['ver', 'criar', 'editar', 'excluir']
  permissions(): array  // produto cartesiano => 16 nomes "recurso.acao"
```

Os 16 nomes (guard `web`):

```
evento.ver     evento.criar     evento.editar     evento.excluir
palestra.ver   palestra.criar   palestra.editar   palestra.excluir
post.ver       post.criar       post.editar       post.excluir
agenda.ver     agenda.criar     agenda.editar     agenda.excluir
```

Semeados por `Database\Seeders\CapacidadesSeeder` (novo), `Permission::updateOrCreate(['name'=>$n,'guard_name'=>'web'])`,
registrado no `DatabaseSeeder::run()` após `EstruturaCemaSeeder`.

> **`ver` não é visibilidade.** É a capacidade de **acessar a aba/listar na área de edição** (nesta fase
> só existe a policy; o consumo — as abas — é Fase D). A visibilidade do publicado permanece em
> `podeSerVistoPor`/`scopeVisiveisPara`, intacta.

## 5. As permissions NÃO são atribuídas a papéis nesta fase

A **matriz papel×tipo×ação** (quais papéis recebem quais permissions) é **Fase C** (fora de escopo, §10).
Aqui as 16 permissions são **criadas** no catálogo, mas **nenhuma é atribuída** a papel algum. Portanto:

- Os **testes de unidade** desta fase fabricam a condição "tem a permissão" **diretamente** no usuário
  de teste (`$user->givePermissionTo('evento.editar')` ou um papel de teste com a permissão), para
  exercitar a **mecânica** das policies — não a matriz real, que ainda não existe.
- Em produção, enquanto a Fase C não roda, nenhum não-admin tem capacidade alguma (fail-closed por
  ausência de atribuição, além do fail-closed das policies).

## 6. Mecânica

### 6.1 `Gate::before` (portão do admin)

`AppServiceProvider::boot()`:

```
Gate::before(fn (User $u) => $u->hasRole('administrador') ? true : null);
```

`true` ⇒ admin passa em **qualquer** ability (inclusive recursos sem policy). `null` ⇒ segue para a
policy. Não retornar `false` aqui (não bloquear ninguém neste ponto; o bloqueio é responsabilidade das
policies).

Com a **decisão 3.0** (flag OFF), este é o **único** `Gate::before` do sistema — o spatie não instala mais
o seu. O portão do admin é **necessário** porque, enquanto a **matriz papel→permissão não roda (Fase C)**,
o admin **não** tem permissões atribuídas; sem este `before`, o admin cairia nas policies escopadas por
departamento e seria barrado.

### 6.2 Vínculo editorial `departamento_usuario`

- Migration incremental nova `create_departamento_usuario_table`: `user_id` + `departamento_id`
  (ambos `foreignId(...)->constrained(...)->cascadeOnDelete()`), `unique(['user_id','departamento_id'])`.
  Segue o padrão de `departamento_evento`. **Nasce vazia.**
- `User::departamentos(): BelongsToMany` = `belongsToMany(Departamento::class, 'departamento_usuario')`.
  **Fonte única** do "departamento do usuário" nas policies. (Sem pivot extra: só o par.)
- **Não** nesta fase: backfill de diretores (Fase B), tela de atribuição (Fase C).

### 6.3 Contrato + trait (o molde, uma vez só)

- **Contrato `App\Models\Contracts\TemDepartamento`** — declara que o model pertence a departamentos
  (expõe `departamentos(): BelongsToMany`). Implementado por `Evento` nesta fase; por `Palestra`/`Post`/
  Agenda quando ganharem departamento (Fase B).
- **Trait `App\Policies\Concerns\AutorizaPorDepartamento`** — método único que recebe `(User, TemDepartamento)`
  e responde se o objeto pertence a **algum** departamento de `User::departamentos()`.
  **Fail-closed**: objeto **sem** departamento ⇒ `false` (só o admin chega a passar, e ele já saiu antes no
  `Gate::before`). Comparação por interseção de ids, uma consulta.

### 6.4 As 4 policies (abilities pt-BR)

Todas as abilities: `ver`, `criar`, `editar`, `excluir`, tipadas **`User $user` não-nulável** (sem `?`).
Assim o Gate **pula** o método para visitante anônimo e devolve *deny* limpo — ao contrário de `view(?User…)`
(visibilidade), que aceita anônimo de propósito. (Se as capacidades copiassem `?User`, o visitante entraria
com `null` e o trait chamaria `->departamentos()` sobre `null` → erro 500.) Regra geral de cada método
(não-admin): **tem a permissão** — checada por **`$user->hasPermissionTo('recurso.acao')`** (método direto
do trait; `$user->can(...)` **não** resolve nomes de permissão com o flag OFF, decisão 3.0) — *E* (para
ações com objeto) **o objeto pertence a um departamento do usuário** (via trait `AutorizaPorDepartamento`).

> **`ver` como policy é por-objeto** (pode abrir a edição *deste* evento). O "listar na área de edição"
> da decisão 1 é o **consumo** disso na Fase D: a aba lista apenas os objetos dos departamentos do
> usuário (a mesma regra aplicada à coleção). Não há ability de coleção separada — o vocabulário travado
> é de 4 ações.

- **`EventoPolicy`** — **estender a policy existente** (mesmo arquivo; auto-discovery mapeia 1 policy/model).
  Mantém `view`/`viewAny` (visibilidade) e **adiciona** `ver`/`criar`/`editar`/`excluir` (capacidade).
  `Evento` implementa `TemDepartamento` (já tem `departamentos()`). Filtro real de departamento.
- **`PalestraPolicy`, `PostPolicy`, `AgendaDiaPolicy`** — **novas**, **fail-closed que negam direto**: cada
  ability **retorna `false`** para não-admin, **sem** usar o trait `AutorizaPorDepartamento`. Os models não
  implementam `TemDepartamento`, e o trait tipa `(User, TemDepartamento)` — passá-los daria **TypeError**. O
  molde de departamento (decisão 4, "uma vez só") vale, **nesta fase, apenas para `Evento`**; Palestra/Post/
  AgendaDia entram no trait na **Fase B**, quando ganharem departamento. (O recurso "agenda" mapeia a
  **`AgendaDia`**, o CRUD de conteúdo — `AgendaDiaResource`; `AgendaMetaMes` é config de tema do mês, fora daqui.)

**`criar` não tem objeto.** Como ability *objectless*, só cai na policy quando invocada com a **classe**:
`Gate::forUser($u)->check('criar', Evento::class)` (não uma instância; um `check('criar')` puro nega e o
teste positivo falharia). O filtro de objeto (decisão 4) não se aplica literalmente a `criar`. Regra
proposta (fail-closed, **a confirmar** — §12): `criar` exige a permissão `recurso.criar` **E** o usuário
pertencer a **≥1 departamento** (senão não há departamento a que vincular o novo conteúdo; a atribuição do
departamento do criador ao objeto é imposta no save da Fase D). Para os recursos sem departamento
(palestra/post/agenda) `criar` segue fail-closed (return false) como as demais ações.

## 7. Convivência capacidade × visibilidade (dualidade a vigiar)

Na **mesma** `EventoPolicy`, dois eixos coexistem:

| ability            | eixo         | fonte da regra                         | consumo |
|--------------------|--------------|----------------------------------------|---------|
| `view` / `viewAny` | visibilidade | `podeSerVistoPor`/`scopeVisiveisPara`  | front público (já existe) |
| `ver`              | capacidade   | permissão `evento.ver` + departamento  | abas de edição (Fase D) |
| `criar`/`editar`/`excluir` | capacidade | permissão + departamento         | forms do site (Fase D) |

Risco a documentar para a Fase D: **`@can('view',$e)` ≠ `@can('ver',$e)`**. O primeiro pergunta "pode
enxergar o publicado?"; o segundo, "pode abrir a edição?". São eixos diferentes que, por acaso, quase
homônimos. O spec de Fase D deve reforçar isso.

> A antiga "dualidade perigosa" (ability de policy × nome cru de permissão resolvido pelo `Gate::before` do
> spatie) **deixou de existir** com a **decisão 3.0** (flag OFF): o nome cru não é mais uma ability de gate,
> passa a **negar** e o único caminho de autorização por objeto é a ability da policy. Ver a prova no §9.5.

## 8. Fronteira: o que muda de comportamento agora

**Nada, no que é visível hoje.** O `/admin` é admin-only e o admin passa no `Gate::before` ⇒ o painel
continua idêntico. O Filament v5 sequer consome as abilities pt-BR das policies (não usa strict
authorization; ver a nota da `EventoPolicy`), então adicioná-las é **inerte** para o painel. A **prova**
dessa não-regressão é a **suíte de resource-tests existente** seguir verde (§9.7) — **não** o Gate cru do
§9.2. Não há forms do site ainda (Fase D). As policies de capacidade só são exercitadas por **teste de
unidade** nesta fase. O front público de visibilidade permanece intocado.

## 9. O que o spec deve provar (testes desta fase)

Todos por **teste de unidade** (`Gate::forUser` com usuário fabricado; sem tela):

1. **Seeder idempotente** — `CapacidadesSeeder` roda **2×** ⇒ exatamente **os 16 nomes esperados** (guard
   `web`), asseridos **um a um** (não só a contagem — um typo num nome ainda daria 16 no produto cartesiano),
   sem duplicar.
2. **`Gate::before`** — `administrador` **passa em tudo**: as 4 ações de todos os recursos, inclusive um
   objeto de evento **sem** departamento, e inclusive palestra/post/agenda. (Prova o portão do admin no Gate,
   **não** a não-regressão do painel — essa é o item 7.)
3. **`EventoPolicy` — matriz de casos** (usuário não-admin, permissão fabricada; via `check('<acao>', $evento)`):
   - tem `evento.<acao>` **+ vínculo** ao **mesmo** departamento do evento ⇒ **pode** (`ver`/`editar`/`excluir`);
   - **caso disjunto (obrigatório)** — usuário vinculado ao depto **X**, evento no depto **Y** (X≠Y), com a
     permissão ⇒ **não**. É o **único** caso que reprova uma trait que cheque "usuário tem *algum* depto **e**
     o objeto tem *algum*" em vez de **interseção** — e a interseção é a razão de ser da fase;
   - tem a permissão, mas o usuário **sem nenhum vínculo** ⇒ **não**;
   - **objeto sem departamento** ⇒ **só admin** (não-admin negado mesmo com a permissão);
   - **sem a permissão** ⇒ **não** (mesmo com vínculo);
   - `criar` (invocado com a **classe**: `check('criar', Evento::class)`): com `evento.criar` + ≥1 departamento
     ⇒ pode; sem departamento ⇒ não (conforme §6.4, sujeito a §12).
4. **`PalestraPolicy` / `PostPolicy` / `AgendaDiaPolicy`** — **qualquer** não-admin ⇒ **negado** em todas as
   ações (fail-closed), mesmo com a permissão fabricada; **admin** ⇒ permitido (via `Gate::before`).
5. **Nome cru de permissão NEGA** (decisão 3.0) — com o flag OFF, o nome cru não é mais uma ability válida:
   `assertFalse(Gate::forUser($u)->allows('evento.editar', $evento))` **mesmo com** a permissão. Prova que o
   único caminho de autorização por objeto é a ability da policy (`check('editar', $evento)`).
6. **Visitante anônimo** — `Gate::forUser(null)->check('editar', $evento)` ⇒ **não** (o método tipado `User`
   não-nulável faz o Gate pular para convidado; sem 500 por `->departamentos()` em `null`).
7. **Regressão do `/admin`** (na camada certa, **não** pelo Gate cru) — a guarda é a **suíte de
   resource-tests existente** — `EventoResourceTest`, `PostResourceTest`, `PalestraResourceTest`,
   `AgendaDiaResourceTest` (`actingAs` admin + `Livewire::test(...)->fillForm(...)`, cobrindo list/create/edit)
   — que deve **seguir verde** após as policies novas. O Filament não consome as abilities pt-BR das policies,
   então o §9.2 não é a prova de que o painel não regrediu.

## 10. Fora de escopo (não fazer nesta fase)

- **Matriz papel×tipo×ação** — Fase C (atribuir permissions a papéis; ver §5).
- **Abas no `/minha-conta`** e os forms de edição do site — Fase D.
- **Backfill de departamento nos conteúdos** (palestra/post/agenda ganharem departamento) — Fase B.
- **Tela de atribuição** do vínculo `departamento_usuario` — Fase C.
- **Auditoria** (`spatie/laravel-activitylog`) — **fase própria, antes da Fase D**. Aqui é citada **apenas
  como dependência** a existir antes da D; nenhum código de auditoria nesta fase.
- **Escalonamento de privilégio** (visibilidade/status filtrados no servidor) — **requisito dos forms da
  Fase D**. A base server-side já é o par **policies + `Gate::before`** desta fase; a filtragem dos campos
  sensíveis (o usuário não pode se auto-conceder visibilidade/estado acima do seu papel) é imposta na D.

Esta fase **absorve a Fase 4 do módulo Eventos**.

## 11. Ciência (não é tarefa desta fase)

Virão posts ainda não migrados de **departamentos diferentes**: *Evangelho da semana* (DED),
*Mensagens mediúnicas* e *Vibrações* (DEPAE). Logo **`post` não é monodepartamental** — é o **filtro de
objeto por departamento** que separa os editores. Se esses conteúdos virão como `Post` guarda-chuva ou como
models próprios se decide **quando migrarem**; o mecanismo desta fase (contrato `TemDepartamento` + trait +
policy) **acomoda os dois** sem retrabalho da fundação.

## 12. Pontos a confirmar no passe adversarial

Decisões derivadas (baixo risco, refináveis) que o enquadramento não cravou explicitamente:

1. **Regra de `criar`** (§6.4): "permissão + pertencer a ≥1 departamento" para recursos com departamento.
   Alternativa: só a permissão, deixando a amarração objeto→departamento inteiramente para o save da Fase D.
   Recomendo a primeira (fail-closed).
2. **Locais/nomes dos artefatos novos**: `App\Support\Autorizacao\GlossarioCapacidades`,
   `App\Models\Contracts\TemDepartamento`, `App\Policies\Concerns\AutorizaPorDepartamento`,
   `Database\Seeders\CapacidadesSeeder`. São a **primeira ocupação** de `App\Support\Autorizacao`,
   `App\Models\Contracts` e `App\Policies\Concerns` — confirmar convenção de pastas.
3. **Recurso `agenda` = `AgendaDia`** (§6.4). Confirmar que o CRUD editorial da agenda é `AgendaDia`
   (`AgendaDiaResource`) e não também `AgendaMetaMes`.
4. **Forma da constante declarativa** (§4): `RECURSOS`/`ACOES` + `permissions()` derivado (produto
   cartesiano) vs. lista explícita dos 16. Recomendo o derivado (uma fonte, sem lista a manter à mão).

## 13. Artefatos

**Novos**
- `app/Support/Autorizacao/GlossarioCapacidades.php` — constante declarativa dos recursos/ações + `permissions()`.
- `database/seeders/CapacidadesSeeder.php` — semeia as 16 permissions (idempotente, guard `web`).
- `database/migrations/<ts>_create_departamento_usuario_table.php` — pivot incremental, nasce vazia.
- `app/Models/Contracts/TemDepartamento.php` — contrato de "pertence a departamentos".
- `app/Policies/Concerns/AutorizaPorDepartamento.php` — trait do filtro de departamento (fail-closed).
- `app/Policies/PalestraPolicy.php`, `app/Policies/PostPolicy.php`, `app/Policies/AgendaDiaPolicy.php` —
  fail-closed que **negam direto** (`return false`; sem o trait, pois os models ainda não são `TemDepartamento`).
- Testes de unidade (§9.1–9.6), sob `tests/Unit/Autorizacao/` (ou `tests/Feature/...` conforme padrão do projeto).

**Alterados**
- `config/permission.php` — `register_permission_check_method => false` (decisão 3.0).
- `app/Providers/AppServiceProvider.php` — `Gate::before` do admin em `boot()`.
- `app/Models/User.php` — relação `departamentos()`.
- `app/Models/Evento.php` — `implements ... TemDepartamento` (já tem `departamentos()`).
- `app/Policies/EventoPolicy.php` — adicionar `ver`/`criar`/`editar`/`excluir` (`User` não-nulável),
  mantendo `view`/`viewAny`.
- `database/seeders/DatabaseSeeder.php` — registrar `CapacidadesSeeder`.

**Guarda de regressão do `/admin`** (não é artefato novo): a suíte de resource-tests existente
(`EventoResourceTest`, `PostResourceTest`, `PalestraResourceTest`, `AgendaDiaResourceTest`) deve seguir
verde — ver §8 e §9.7.

**Regras de sempre** (CLAUDE.md): pt-BR em tudo; migrations **só incrementais** (nunca
`fresh`/`refresh`/`wipe`/`reset`/seed destrutivo); nada destrutivo no banco de dev; Pint antes do push;
`docker compose exec -T app php artisan test`; cabeçalho de autoria nos arquivos novos relevantes.
