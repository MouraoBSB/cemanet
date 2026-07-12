# Spec — Fase B · Departamento nos Conteúdos

> Autoria: Thiago Mourão — https://github.com/MouraoBSB — 2026-07-11
> Enquadramento travado com o dono (dono + consultor) no kickoff da Fase B. Este spec **não**
> improvisa além das decisões travadas; cada afirmação sobre o terreno foi **verificada contra o
> código real** (evidência `arquivo:linha` no §2) e os pontos que o enquadramento não previu — ou
> em que o enquadramento diverge do código — estão no §14 para o **passe adversarial**.
> Destino: **SPEC** — vai ao passe adversarial **antes** de virar plano de implementação.
> Fundação: [SPEC — Fase A](2026-07-11-fase-a-modelo-capacidades.md) (aprovada e mesclada, PR #25).

## 1. Contexto e objetivo

A **Fase A** instituiu o eixo **CAPACIDADE** (quem edita), separado da **VISIBILIDADE** (quem vê),
como fundação server-side (ver memória `modelo-capacidades-cema`): 16 permissions `recurso.acao`
(guard `web`, sem atribuição a papéis — a matriz é a Fase C), o `Gate::before` do admin, o flag
`register_permission_check_method` OFF, o pivot `departamento_usuario` (nasce vazio), o contrato
`TemDepartamento` + o trait `AutorizaPorDepartamento`, e a `EventoPolicy` com **filtro real de
departamento**. `Palestra`/`Post`/`AgendaDia` receberam policies **fail-closed** (negam tudo a
não-admin) à espera desta fase.

A **Fase B departamentaliza os conteúdos**: tira as três policies do "nega tudo", traz `Palestrante`
para o modelo de capacidade, e semeia os vínculos que dão sentido ao filtro. Concretamente:

1. **Vocabulário** — soma `palestrante` aos recursos: de 16 para **20 permissions**.
2. **Modelagem** — 4 pivots N:N novos (`departamento_palestra`, `departamento_post`,
   `departamento_palestrante`, `departamento_agenda_dia`) e os 4 models passando a `implements
   TemDepartamento`.
3. **Policies** — `Palestra`/`Post`/`AgendaDia` **deixam** o fail-closed e passam ao molde real da
   `EventoPolicy` (permissão + escopo de departamento via trait); `Palestrante` ganha uma
   `PalestrantePolicy` **nova** no mesmo molde.
4. **Backfill dos conteúdos** — comando idempotente que vincula cada conteúdo ao departamento que o
   **mantém** (critério de posse, não de tema).
5. **Backfill do vínculo dos diretores** — preenche `departamento_usuario` a partir do **cargo** de
   cada usuário (dá o vínculo; a permissão é a Fase C).

Como na Fase A, **nada muda no `/admin`** (admin-only; o admin passa no `Gate::before`; o Filament v5
não consome as abilities pt-BR das policies) nem no **front público de visibilidade**
(`podeSerVistoPor`/`scopeVisiveisPara`/scopes de publicação **intactos**). As policies de capacidade
só "morderão" nos forms do site em `/minha-conta` (Fase D).

## 2. Terreno confirmado por leitura (não presumir diferente)

Verificado no código em 2026-07-11 (6 frentes de verificação read-only):

**Vocabulário**
- `App\Support\Autorizacao\GlossarioCapacidades` — `RECURSOS = ['evento','palestra','post','agenda']`
  (`:14`), `ACOES = ['ver','criar','editar','excluir']` (`:16`), `permissions()` deriva o produto
  cartesiano (`:19-29`). O único consumidor no código é o `CapacidadesSeeder`
  (`database/seeders/CapacidadesSeeder.php:19`, `Permission::updateOrCreate`, idempotente; o seeder vive em
  `database/seeders/`, **não** em `app/`). Adicionar `palestrante` propaga sozinho ao seeder —
  **nenhuma outra linha de código muda**.
- ⚠️ O docblock da classe (`GlossarioCapacidades.php:8-10`) diz **"Palestrante/Biblioteca ficam FORA
  (decisão pendente do 'Meu Perfil')"**. A Fase B **contradiz** isso quanto a `palestrante`; o docblock
  (e o comentário "os 16 nomes" em `:18`, e "Semeia as 16 permissions" no seeder `:12`) precisam ser
  atualizados. **Biblioteca continua fora** (singleton admin-only — §11).
- `tests/Feature/Autorizacao/CapacidadesSeederTest.php` assere `assertSame(16, …count())` (`:28`) e
  lista os 16 nomes em `$esperados` (`:21-26`). É o **único** teste que conta permissions — grep na
  suíte confirma que **nenhum outro** teste depende do número 16 nem enumera a lista.

**Contrato, trait e policies (o molde)**
- `App\Models\Contracts\TemDepartamento` (`:10-13`) exige `departamentos(): BelongsToMany`.
- `App\Policies\Concerns\AutorizaPorDepartamento::objetoNoDepartamentoDoUsuario(User, TemDepartamento): bool`
  (`:16-27`) — interseção por ids, **fail-closed nas duas pontas** (usuário sem depto **ou** objeto sem
  depto ⇒ `false`). Usa nomes qualificados `departamentos.id` → a relação `departamentos()` dos models
  deve apontar a tabela `departamentos`.
- `EventoPolicy` (molde canônico): `use AutorizaPorDepartamento` (`:22`); capacidades `ver`/`editar`/`excluir`
  = `hasPermissionTo('evento.<acao>') && objetoNoDepartamentoDoUsuario(...)` com `User` **não-nulável**;
  `criar(User $user)` = `hasPermissionTo('evento.criar') && $user->departamentos()->exists()` (`:39-42`,
  objectless); `view`/`viewAny` com `?User` (visibilidade). `hasPermissionTo`, **nunca** `can()`.
- `PalestraPolicy`/`PostPolicy`/`AgendaDiaPolicy` hoje têm **só** `ver`/`criar`/`editar`/`excluir`,
  todos `return false;`, **sem** o trait, **sem** `hasPermissionTo`, **sem** `view`/`viewAny`
  (`PalestraPolicy.php:16-34` e análogos). São placeholders fail-closed.
- **Não existe `PalestrantePolicy`** (glob de `app/Policies` retorna só Agenda/Evento/Palestra/Post +
  `Concerns/`). **Não há `AuthServiceProvider`** nem `Gate::policy`/`$policies` — auto-discovery Laravel
  11+ (`App\Models\Palestrante` → `App\Policies\PalestrantePolicy` sem registro manual).

**Models de conteúdo**
- `Evento` (referência): `class Evento extends Model implements HasMedia, TemDepartamento` (`:22`);
  `departamentos()` = `belongsToMany(Departamento::class, 'departamento_evento', 'evento_id', 'departamento_id')`
  (`:96-99`), **sem** `withTimestamps`/`withPivot`.
- Estado atual dos 4 alvos (nenhum tem `departamentos()`; confirmado por grep):
  - `Palestra`: `class Palestra extends Model` (sem `implements`).
  - `Post`: `class Post extends Model implements HasMedia, HasRichContent`.
  - `AgendaDia`: `class AgendaDia extends Model`; `protected $table = 'agenda_dias'` (`:22`).
  - `Palestrante`: `class Palestrante extends Model implements HasMedia`; tabela `palestrantes`.
- 🔑 **`Palestrante` é um model único** (tabela `palestrantes`) que serve **palestrante E diretor _de
  palestra_** via `palestra_pessoa.papel` — constantes `PAPEL_PALESTRANTE`/`PAPEL_DIRETOR` em
  `Palestra.php:23,25`; a relação `palestrantes()` + `getDiretorAttribute` em `Palestra.php:49-73`; FK
  histórica `pessoa_id`. **Não há model `Diretor`.** Departamentalizar `Palestrante` cobre também os
  diretores-de-palestra (mesma entidade). Ver o alerta de nomenclatura no §2.1.
- Recurso `agenda` = model **`AgendaDia`** (`app/Filament/Resources/Agenda/AgendaDiaResource.php:30`).
  Há **também** um `AgendaMetaMesResource` (CRUD do "tema do mês", com teste próprio) — `AgendaMetaMes` é
  **metadado**, não conteúdo editorial por dia, e fica **fora** da Fase B (sem departamento/policy; §11, §14).
- **Factories existem e `->create()` sem argumentos funciona** para os 4 (`PalestranteFactory`,
  `PalestraFactory`, `PostFactory`, `AgendaDiaFactory`). **Não há** `EventoFactory` nem
  `DepartamentoFactory` — os testes da Fase A criam `Departamento`/`Evento` por `::create([...])`;
  a Fase B segue o mesmo (`Departamento::create(...)` + `Model::factory()->create()`).

**Migrations de pivot (padrão)**
- Molde `departamento_evento` (`2026_07_08_000004:11-17`): `id()` + `foreignId('<x>_id')->constrained('<tab>')->cascadeOnDelete()`
  + `foreignId('departamento_id')->constrained('departamentos')->cascadeOnDelete()` + `unique(['<x>_id','departamento_id'])`.
  **Sem** `timestamps()`. Idêntico a `departamento_usuario` (`2026_07_11_000001`).
- `departamentos` tem `sigla` **UNIQUE** (`:13`) e `slug` **UNIQUE** (`:15`) → resolver por sigla é
  seguro. Semeado em `EstruturaCemaSeeder.php:28-33` (`slug = Str::slug(sigla)`).
- Última migration é `2026_07_11_000001`; as novas são incrementais posteriores.

**Estrutura organizacional (para o backfill de diretores)**
- 8 departamentos (`App\Importacao\GlossarioUsuarios::DEPARTAMENTOS`, `:18-27` — o glossário vive em
  `app/Importacao/` e é **só constantes**): DAS, DDA, DED, DEMAPA, DEPAE, DEPRO, DIJ, DECOM.
  DED = "Estudos Doutrinários"; DECOM = "Comunicação e Multimídia".
- `Cargo` tem `departamento_id` **nullable** (`migration 2026_07_03_000004:13`, FK `nullOnDelete`);
  `Cargo::departamento()` = `belongsTo` (`Cargo.php:20-23`). `User::cargos()` = `belongsToMany(Cargo, 'cargo_usuario')`
  (`User.php:50-53`); `User::departamentos()` = `belongsToMany(Departamento, 'departamento_usuario')` (`:55-58`).
- 🔑 **O kickoff diz "9 cargos `diretor_*` têm `departamento_id`" — o código diz 8.** Há 9 chaves com
  prefixo `diretor_`: 7 departamentais em `CARGOS` (`:52-58`: dda/ded/decom/demapa/depae/depro/dij),
  `diretor_presidente` (`:59`, **institucional, sigla `null`**), e `diretor_das` em `CARGOS_EXTRA`
  (`:67-69`). Só **8** têm departamento (os 7 + `diretor_das`); o Presidente **não**. Ver §8 e §14.
- 🔑 **Não filtrar por slug** `LIKE 'diretor%'`: o slug no banco é `Str::slug(nome)`
  (`EstruturaCemaSeeder.php:46`), então `diretor_presidente` → `'presidente'` (perde o prefixo) e os
  demais → `'diretor-do-<x>'`. O filtro determinístico é **`cargos.departamento_id IS NOT NULL`**
  (equivale, no conjunto atual, a `institucional = false`).
- **`diretor_das` está em `CARGOS_EXTRA`**, que `resolverCargos()` (`app/Importacao/TransformadorUsuarios.php:90`)
  **ignora** (só usa `CARGOS`). Logo nenhum usuário importado recebe esse cargo → o backfill produz
  **0 vínculos para o DAS** (o cargo existe no catálogo — `EstruturaCemaSeeder` agrega `CARGOS` +
  `CARGOS_EXTRA` — mas sem ocupante). Esperado, não é bug (§8, §14).
- Nada no código atribui permission a papel/cargo hoje (a matriz é a Fase C) — o backfill de diretores
  toca **só** `departamento_usuario`.

**Contagens reais no dev** (container de pé, leitura): **Palestra=127, Post=45, AgendaDia=123,
Palestrante=59** — o kickoff cita ~123/44 (drift para cima). O critério do backfill é **"todos"**, então
os números são referência, não parâmetro (§7).

### 2.1 Alerta de nomenclatura — dois "diretores" distintos

O sistema tem **duas** entidades chamadas "diretor", de naturezas diferentes. O SPEC as mantém
separadas e o plano/execução **não** pode confundi-las:

| "Diretor" | O que é | Onde vive | Papel na Fase B |
|-----------|---------|-----------|-----------------|
| **Diretor de palestra** | Uma **pessoa** (`Palestrante`) que dirigiu uma palestra | `palestra_pessoa.papel = 'diretor'` | Coberto pela departamentalização do model **`Palestrante`** (decisão 1/5) |
| **Diretor departamental** | Um **`User`** que gere um departamento | Cargo `diretor_*` com `departamento_id` | Recebe o **vínculo** em `departamento_usuario` (decisão 6) |

## 3. Decisões travadas (do enquadramento) e cravadas por verificação

Do kickoff (dono + consultor). Ordem espelha o enquadramento; a verificação refina onde o código
diverge.

1. **Escopo** — 4 tipos ganham departamento: **`Palestra`, `Post`, `AgendaDia`, `Palestrante`**
   (`Evento` já tem). **`Biblioteca` fica de fora** (é `singleton`, pool central de mídia do blog, sem
   registros por departamento — segue admin-only; §11). Não mexer nela.
2. **Vocabulário** — adicionar `'palestrante'` aos `RECURSOS` de `GlossarioCapacidades` ⇒ **20
   permissions**. O `CapacidadesSeeder` já é idempotente e cresce sozinho; o `CapacidadesSeederTest`
   passa a asserir os **20 nomes exatos**.
3. **Modelagem** — N:N, forçada pelo contrato `TemDepartamento`. Migrations **incrementais** de pivot no
   padrão `departamento_evento`: `departamento_palestra`, `departamento_post`, `departamento_palestrante`
   e **`departamento_agenda_dia`** (singular de `agenda_dias`; §5). Os 4 models: `implements TemDepartamento`
   + relação `departamentos(): BelongsToMany`.
4. **Policies** — `Palestra`/`Post`/`AgendaDia` **deixam** o fail-closed e passam a **usar o trait**
   (molde `EventoPolicy`: `hasPermissionTo` + escopo). `Palestrante` ganha **`PalestrantePolicy` nova**,
   mesmo molde. Testes seguem o padrão da Fase A, **incluindo o caso disjunto obrigatório** (usuário no
   depto X, objeto no depto Y ⇒ nega — o único caso que reprova uma trait sem interseção).
5. **Backfill dos conteúdos** — comando idempotente, critério **"quem mantém, não o tema"**:
   `Palestra → DED`, `Post → DECOM`, `AgendaDia → DECOM`, `Palestrante → DED`.
6. **Backfill do vínculo dos diretores** — em `departamento_usuario`: cada `User` cujo **cargo tem
   departamento** (`departamento_id IS NOT NULL`) recebe o attach desse departamento. Dá o **vínculo**;
   a **permissão** é a Fase C (o diretor ainda não edita nada — fail-closed por falta de permissão).
7. **Fail-closed na transição** — registro sem departamento ⇒ só admin. É o comportamento do trait;
   garantido nos testes.

**Decisões cravadas por verificação (o enquadramento não previu, ou o código exige):**

- **(a) Só o eixo CAPACIDADE nas novas policies** — as quatro policies (Palestra/Post/AgendaDia/Palestrante)
  recebem **apenas** `ver`/`criar`/`editar`/`excluir`. **Sem** `view`/`viewAny`. Justificativa: (i) a
  decisão de não tocar a visibilidade; (ii) diferente do `Evento` (que tem `podeSerVistoPor` por papel),
  a visibilidade pública desses conteúdos é resolvida por scopes de publicação (ex.: `Palestra::scopePublicado`),
  **não** por uma ability `view` de policy; (iii) as fail-closed atuais já não têm `view`/`viewAny` e a
  suíte está verde. Confirmar no §14.
- **(b) `criar` passa a exigir vínculo** — como esses 4 recursos agora têm departamento, `criar` deixa
  de ser `return false` e adota o molde do `Evento`: `hasPermissionTo('<recurso>.criar') && $user->departamentos()->exists()`.
- **(c) Backfill de diretores por `departamento_id IS NOT NULL`** (8 cargos), **não** por prefixo de
  slug — corrige o "9" do kickoff e exclui o `diretor_presidente` (§2, §8, §14).
- **(d) Backfill como comando** `cema:*` (não seeder) — recomendação forte da verificação (§7).

## 4. Vocabulário — de 16 para 20 permissions

`GlossarioCapacidades::RECURSOS` ganha `'palestrante'`. **Posição recomendada: append no fim**
(mantém estável a ordem dos 16 nomes já semeados; menor churn no array de teste) — confirmar no §14:

```
RECURSOS = ['evento', 'palestra', 'post', 'agenda', 'palestrante']   // biblioteca segue FORA
ACOES    = ['ver', 'criar', 'editar', 'excluir']
permissions(): produto cartesiano => 20 nomes
```

Os **4 novos** (guard `web`): `palestrante.ver`, `palestrante.criar`, `palestrante.editar`,
`palestrante.excluir`. Sem colisão (`palestra` ≠ `palestrante`). O `CapacidadesSeeder`
(`Permission::updateOrCreate`) semeia os 20 sem alteração de código — só **atualizar os docblocks**
("16" → "20"; remover "Palestrante fica FORA").

O `CapacidadesSeederTest` passa a: `assertSame(20, …count())`, `$esperados` com os 20 nomes (asseridos
um a um — um typo ainda daria 20 no produto), e o método renomeado (`…_os_20_nomes_…`).

## 5. Modelagem — 4 pivots + contrato nos 4 models

**4 migrations incrementais** (uma por pivot, no padrão `departamento_evento`: `id()` + duas FKs
`constrained(...)->cascadeOnDelete()` + `unique(par)`, **sem** `timestamps`), posteriores a
`2026_07_11_000001`:

| Pivot | FK do conteúdo | `constrained` | `unique` |
|-------|----------------|---------------|----------|
| `departamento_palestra` | `palestra_id` | `palestras` | `['palestra_id','departamento_id']` |
| `departamento_post` | `post_id` | `posts` | `['post_id','departamento_id']` |
| `departamento_palestrante` | `palestrante_id` | `palestrantes` | `['palestrante_id','departamento_id']` |
| `departamento_agenda_dia` | `agenda_dia_id` | `agenda_dias` | `['agenda_dia_id','departamento_id']` |

> ⚠️ **`departamento_palestrante` usa `palestrante_id`** (FK própria do novo pivot) — **não** confundir
> com o `pessoa_id` do pivot legado `palestra_pessoa`.
> ⚠️ **`departamento_agenda_dia`**: a convenção nativa do Laravel ordenaria alfabeticamente
> (`agenda_dia_departamento`); o **padrão do projeto** é `departamento_<x>`. Por isso — e em todos os 4 —
> a relação `belongsToMany` **nomeia a tabela e as chaves explicitamente** (como faz o `Evento`).

**Os 4 models `implements TemDepartamento`** e ganham a relação (molde `Evento::departamentos()`,
sem `withTimestamps`/`withPivot`):

- `Palestra`: `class Palestra extends Model implements TemDepartamento` — `departamentos()` →
  `belongsToMany(Departamento::class, 'departamento_palestra', 'palestra_id', 'departamento_id')`.
- `Post`: `class Post extends Model implements HasMedia, HasRichContent, TemDepartamento` — pivot
  `departamento_post`.
- `AgendaDia`: `class AgendaDia extends Model implements TemDepartamento` — pivot `departamento_agenda_dia`,
  chave `agenda_dia_id`.
- `Palestrante`: `class Palestrante extends Model implements HasMedia, TemDepartamento` — pivot
  `departamento_palestrante`, chave `palestrante_id`.

**Fora de escopo (não criar):** relações inversas em `Departamento` (`palestras()`, `posts()`, …) —
não são necessárias para policies nem backfill (o backfill opera do lado do conteúdo:
`$conteudo->departamentos()->syncWithoutDetaching(...)`). Evitar escopo supérfluo.

## 6. Policies — sair do fail-closed + `PalestrantePolicy` nova

As **três** policies existentes (`PalestraPolicy`, `PostPolicy`, `AgendaDiaPolicy`) são **reescritas**
do fail-closed (`return false`) para o molde real da `EventoPolicy`, e a **`PalestrantePolicy`** nasce
no mesmo molde. Cada uma: `use AutorizaPorDepartamento`; `User` **não-nulável**; `hasPermissionTo`,
**nunca** `can()`; **só** as 4 abilities de capacidade (§3(a)):

```php
public function ver(User $user, <Model> $obj): bool
{
    return $user->hasPermissionTo('<recurso>.ver') && $this->objetoNoDepartamentoDoUsuario($user, $obj);
}

public function criar(User $user): bool   // objectless
{
    return $user->hasPermissionTo('<recurso>.criar') && $user->departamentos()->exists();
}

public function editar(User $user, <Model> $obj): bool  { /* '<recurso>.editar' + objetoNoDepartamentoDoUsuario */ }
public function excluir(User $user, <Model> $obj): bool { /* '<recurso>.excluir' + objetoNoDepartamentoDoUsuario */ }
```

Mapeamento recurso → model: `palestra`→`Palestra`, `post`→`Post`, `agenda`→`AgendaDia`,
`palestrante`→`Palestrante`. `PalestrantePolicy` é auto-descoberta (sem registro manual).

**Não-regressão do `/admin`:** as abilities **inglesas** que o Filament poderia consultar (`viewAny`,
`view`, `create`, `update`, `delete`) **continuam sem método** nas policies (como nas fail-closed) →
negadas para não-admin, admin passa no `Gate::before`. Como a Fase A provou que policies parciais não
afetam o painel, criar/estender estas quatro é **inerte** para o `/admin` — provado pelos resource-tests
(§10.6), **agora com `PalestranteResourceTest` na guarda**. Esse teste **já existe** (11 métodos, desde
2026-06-25) e roda como **admin** (imune à policy nova via `Gate::before`); a Fase B só precisa
**mantê-lo verde**, não criá-lo — ele passa a ser guarda relevante porque `Palestrante` ganha policy
(antes não tinha).

## 7. Backfill dos conteúdos (comando idempotente)

**Artefato: um comando artisan** `cema:departamentalizar-conteudos` (não seeder). Justificativa
verificada: no projeto, **seeders = catálogo** (`Categoria`, `EstruturaCema`, `Capacidades`, `Admin`) e
**conteúdo é importado por comando** (`cema:importar-*`); o `DatabaseSeeder` encadeia só catálogo, então
um seeder de conteúdo rodaria em `db:seed` onde o conteúdo pode não existir. Um comando standalone roda
**depois** dos importadores, na ordem certa, e é **100 % local** (dispensa o túnel `legado`).

Mecânica (critério **"quem mantém"**, aplicado a **todos** os registros de cada tipo):

```
DED   = Departamento::where('sigla', 'DED')->firstOrFail();
DECOM = Departamento::where('sigla', 'DECOM')->firstOrFail();

Palestra    (todas) -> syncWithoutDetaching([DED->id])
Palestrante (todos) -> syncWithoutDetaching([DED->id])
Post        (todos) -> syncWithoutDetaching([DECOM->id])
AgendaDia   (todos) -> syncWithoutDetaching([DECOM->id])
```

- **`syncWithoutDetaching`** (não `sync` puro): idempotente **e** não-destrutivo — preserva vínculos
  extras adicionados à mão numa reexecução; a `unique(par)` do pivot impede duplicação.
- Referência atual (não é parâmetro; o critério é "todos"): 127 palestras + 59 palestrantes → **DED**;
  45 posts + 123 agenda-dias → **DECOM**. O comando **loga** a contagem por tipo/departamento.
- **Chunk** ao iterar (evitar carregar 350+ registros de uma vez).

> **Ciência (Palestrante é pessoa compartilhável):** `Palestrante → DED (todos)` vincula ao DED **todos**
> os registros de `palestrantes`, inclusive quem aparece **só** como diretor-de-palestra. É intencional
> pelo critério "quem mantém o cadastro = DED" (§14 confirma).

## 8. Backfill do vínculo dos diretores (`departamento_usuario`)

**Artefato:** comando artisan `cema:vincular-diretores-departamento` (idempotente; separado do §7 —
domínio distinto: vínculo de **usuário**, não de conteúdo).

Mecânica (filtro **determinístico** `departamento_id IS NOT NULL` — §2, §3(c)):

```
Para cada User que ocupa >=1 cargo com departamento (via cargo_usuario + cargos.departamento_id NOT NULL):
    $deptoIds = ids distintos dos departamentos desses cargos
    $user->departamentos()->syncWithoutDetaching($deptoIds)
```

- **Capta os 8 cargos de diretor com departamento** no catálogo e **exclui** `diretor_presidente`
  (sigla `null`) e os institucionais. Mas — atenção à contagem — **`diretor_das` não tem ocupante**
  (§2), então o backfill gera **no máximo 7 vínculos reais** (DED/DDA/DECOM/DEMAPA/DEPAE/DEPRO/DIJ),
  **nunca DAS**. "8 cargos no catálogo" ≠ "8 linhas em `departamento_usuario`".
- **Semântica do filtro (blindar):** `departamento_id IS NOT NULL` significa "ocupa um cargo de um
  departamento" — logo **candidato a editor** dele. Hoje isso **coincide** com os diretores (nenhum cargo
  não-diretor tem departamento), mas é propriedade dos **dados**, não estrutural: o admin pode criar um
  cargo não-institucional **com** departamento no `CargoResource`. Mantemos o filtro (é o correto para o
  vínculo) e **cravamos a invariante em teste** (§10.4); só restringir literalmente a "diretor" se o dono
  quiser excluir futuros vice/coordenador (§14).
- Dá **só o vínculo**. A **permissão** é a Fase C → o diretor continua sem editar nada (fail-closed por
  ausência de permissão). Coerente.
- **DAS sem diretor:** como `diretor_das` está em `CARGOS_EXTRA` (ignorado no import), **0 vínculos** para
  o DAS — esperado (§2, §14).
- `syncWithoutDetaching` + `unique(user_id, departamento_id)` ⇒ idempotente e reexecutável.

## 9. Fail-closed na transição + ordem de execução

**Fail-closed** (garantido pelo trait): objeto sem departamento, usuário sem vínculo, ou ausência da
permissão ⇒ negar; só o admin passa (antes, no `Gate::before`). Provado por teste (§10).

**Ordem de execução (importa em dev/deploy, não nos testes):**

0. **Vocabulário primeiro** — `GlossarioCapacidades` recebe `palestrante` e o `CapacidadesSeeder` semeia
   as **20** permissions **antes** de qualquer policy chamar `hasPermissionTo`. Se `palestrante.*` não
   existir no catálogo, o Spatie lança `PermissionDoesNotExist` para não-admin em runtime. Armadilha: o
   `PalestranteResourceTest` **não** pega isso (roda como admin ⇒ passa no `Gate::before` antes da policy);
   a guarda real é o teste de capacidade (§10.2), cujo `setUp` semeia as 20.
1. Migrations dos 4 pivots + os 4 models `implements TemDepartamento`.
2. **Backfill dos conteúdos** (`cema:departamentalizar-conteudos`) — popula os vínculos.
3. **Só então** as policies passam a usar o trait / a `PalestrantePolicy` nasce.

Motivo: a trait é fail-closed — se as policies saíssem do fail-closed com o pivot **vazio**, todo editor
não-admin seria negado no próprio conteúdo. Nos **testes** a ordem é irrelevante (os vínculos são
fabricados por caso). Em produção o risco é teórico nesta fase (não há forms até a Fase D), mas a ordem
lógica de deploy é essa. O backfill de diretores (§8) é independente e pode rodar a qualquer momento após
as migrations da Fase A (a `departamento_usuario` já existe).

## 10. O que o spec deve provar (testes desta fase)

Todos por **teste** com `RefreshDatabase` (banco de teste isolado; `Gate::forUser` com usuário fabricado;
sem tela):

1. **Seeder 20** — `CapacidadesSeeder` roda 2× ⇒ exatamente os **20 nomes** (guard `web`), asseridos um a
   um, sem duplicar (`CapacidadesSeederTest` atualizado).
2. **Capacidade departamentalizada — matriz por model** (parametrizado por `[Palestra, 'palestra']`,
   `[Post, 'post']`, `[AgendaDia, 'agenda']`, `[Palestrante, 'palestrante']`; os 4 têm factory e
   `departamentos()`). **`setUp` semeia o `CapacidadesSeeder` (as 20) e cria o papel `administrador`**
   (molde do `EventoPolicyCapacidadeTest`) — sem `palestrante.*` no catálogo, `hasPermissionTo` lançaria
   `PermissionDoesNotExist` (é a guarda do passo 0 do §9). Para cada model, via `check('<acao>', $obj)`:
   - permissão `+` vínculo ao **mesmo** departamento ⇒ **pode** (`ver`/`editar`/`excluir`);
   - **caso disjunto (obrigatório)** — usuário no depto X, objeto no depto Y (X≠Y), com a permissão ⇒
     **não**;
   - permissão mas **sem nenhum vínculo** ⇒ **não**;
   - **objeto sem departamento** ⇒ **só admin** (não-admin negado mesmo com a permissão);
   - **sem a permissão** (com vínculo) ⇒ **não**;
   - `criar` (com a **classe**): permissão `+` ≥1 departamento ⇒ pode; sem departamento ⇒ não;
   - **nome cru nega** — `allows('<recurso>.editar', $obj)` = **false** (flag OFF), mas `check('editar', $obj)`
     = true;
   - **visitante anônimo** — `Gate::forUser(null)->check('editar', $obj)` ⇒ **não** (sem 500);
   - **admin** ⇒ passa em tudo (via `Gate::before`).
   > **Destino do `PoliciesFailClosedTest` da Fase A:** ele **não** ficaria vermelho — cria usuário
   > **sem** departamento (`:53`), e a policy departamentalizada também nega user-sem-vínculo (trait
   > fail-closed), então os `assertFalse` seguem verdes. Por isso o passe não é "deletar": é
   > **reescrever/renomear** por **clareza** (o nome "FailClosed" fica obsoleto) e **estender** com os
   > cenários **positivos** (user COM interseção ⇒ pode). **Preservar** a guarda
   > `test_policies_sao_resolvidas_por_auto_discovery` (agora também para `PalestrantePolicy`). Na prática,
   > este item §10.2 **é** essa reescrita.
3. **Backfill dos conteúdos** — após `cema:departamentalizar-conteudos`: toda `Palestra`/`Palestrante`
   tem **DED**; todo `Post`/`AgendaDia` tem **DECOM**; **idempotente** (2× não duplica);
   `syncWithoutDetaching` **preserva** um vínculo manual extra pré-existente.
4. **Backfill dos diretores** — `User` com cargo `diretor_ded` (departamento DED) ⇒ ganha DED em
   `departamento_usuario`; `User` só com cargo **institucional** (ex.: Presidente, `departamento_id` null)
   ⇒ **nenhum** vínculo; **idempotente**. Asserir também que **o DAS não recebe vínculo** (cargo sem
   ocupante) — cobertura real de **≤7** departamentos, não 8 (§8). E a **invariante do filtro** (§8): um
   cargo **não-institucional com departamento** também gera vínculo (o critério é "cargo com departamento",
   não o slug `diretor_*`).
5. **Não-regressão do eixo de visibilidade** — a suíte de visibilidade do `Evento`
   (`VisibilidadeEventoAcessoTest`) e os scopes de publicação seguem intactos (nada nesta fase os toca).
6. **Regressão do `/admin`** (camada certa) — resource-tests **seguem verdes**: `EventoResourceTest`,
   `PostResourceTest`, `PalestraResourceTest`, `AgendaDiaResourceTest` **e `PalestranteResourceTest`**
   (este **já existe**; entra na guarda agora porque `Palestrante` ganha policy — roda como admin, imune à
   policy via `Gate::before`. **Não** é teste novo a criar).
7. **Suíte inteira + Pint** verdes no container (ciência da memória `flaky-importadorblog-gd-cap-imagem`:
   2 testes de cap de imagem do blog podem falhar sob carga; se passam isolados, não é regressão desta
   fase).

## 11. Fora de escopo (não fazer nesta fase)

- **Matriz papel×tipo×ação** (atribuir permissions a papéis) — **Fase C**.
- **Abas/forms de edição no `/minha-conta`** — **Fase D**.
- **Tela de atribuição** do vínculo `departamento_usuario` (inclusive corrigir o DAS sem diretor) — **Fase C**.
- **Eixo de visibilidade** nas novas policies (`view`/`viewAny`) — §3(a); não se aplica a estes conteúdos.
- **Relações inversas em `Departamento`** — desnecessárias (§5).
- **`Biblioteca`** — `singleton` admin-only; não recebe departamento nem policy (§2, decisão 1).
- **`AgendaMetaMes`** ("tema do mês", com `AgendaMetaMesResource` próprio) — metadado, não conteúdo
  editorial por dia; sem departamento/policy (§2, §14). Rever só na Fase D se o editor do DECOM precisar do
  tema do mês junto com os dias.
- **Auditoria** (`spatie/laravel-activitylog`) — fase própria, antes da Fase D.

## 12. Ciências (não são tarefa desta fase)

- **Posts de outros departamentos** (herdada da Fase A): virão *Evangelho da semana* (DED), *Mensagens
  mediúnicas*/*Vibrações* (DEPAE). O `Post` **não é monodepartamental** — o filtro por objeto é o que
  separa editores. O backfill inicial põe **todos** os posts atuais no **DECOM** ("quem mantém hoje"); a
  reclassificação por conteúdo é editorial/futura, acomodada pelo mesmo mecanismo (contrato + trait +
  pivot) sem retrabalho.
- **`Palestrante` compartilhável** entre contextos: recebe **um** departamento (DED) mesmo aparecendo como
  diretor-de-palestra em várias palestras. É o critério de **posse do cadastro**, não de participação.
- **`hasPermissionTo` lança se o catálogo não estiver semeado** (herdada): o deploy precisa rodar o
  `CapacidadesSeeder` (agora 20) — crítico na Fase D.
- **N+1 do trait** (herdada): ~2 consultas por objeto. Nesta fase o uso é **por-objeto** (ok). A Fase D,
  que lista coleções, precisará de eager-load.
- **Verbos pt-BR × verbos do Filament** (ciência p/ Fase C, **não agir** agora): as policies têm só
  `ver`/`criar`/`editar`/`excluir`; o Filament nativo consulta `viewAny`/`view`/`create`/`update`/`delete`
  (inglês). Hoje é **inerte** (o `/admin` é admin-only; o não-admin toma 403 antes de a policy ser
  consultada). Se uma fase futura quiser que o **painel** consulte a policy para não-admin, faltará
  **mapear os verbos ingleses** para as capacidades pt-BR — senão o Filament não enxerga a autorização.
  (O modelo travado mantém a edição de não-admin **fora** do `/admin`, em `/minha-conta` — Fase D.)

## 13. Artefatos

**Novos**
- `database/migrations/<ts>_create_departamento_palestra_table.php`
- `database/migrations/<ts>_create_departamento_post_table.php`
- `database/migrations/<ts>_create_departamento_palestrante_table.php`
- `database/migrations/<ts>_create_departamento_agenda_dia_table.php`
- `app/Policies/PalestrantePolicy.php` — capacidade departamentalizada (molde `EventoPolicy`).
- `app/Console/Commands/DepartamentalizarConteudos.php` — `cema:departamentalizar-conteudos` (§7).
- `app/Console/Commands/VincularDiretoresDepartamento.php` — `cema:vincular-diretores-departamento` (§8).
- Testes: capacidade departamentalizada por model (§10.2), backfill de conteúdos (§10.3), backfill de
  diretores (§10.4), sob `tests/Feature/Autorizacao/` (padrão da Fase A).

**Alterados**
- `app/Support/Autorizacao/GlossarioCapacidades.php` — `+ 'palestrante'` em `RECURSOS`; docblocks.
- `app/Models/Palestra.php`, `Post.php`, `AgendaDia.php`, `Palestrante.php` — `implements TemDepartamento`
  + `departamentos()`.
- `app/Policies/PalestraPolicy.php`, `PostPolicy.php`, `AgendaDiaPolicy.php` — fail-closed → molde real.
- `database/seeders/CapacidadesSeeder.php` — docblock ("16" → "20"); **sem** mudança de lógica.
- `tests/Feature/Autorizacao/CapacidadesSeederTest.php` — 16 → 20.
- `tests/Feature/Autorizacao/PoliciesFailClosedTest.php` — **reescrito/renomeado + estendido** com
  cenários positivos, preservando a guarda `test_policies_sao_resolvidas_por_auto_discovery` (§10.2). **Não**
  ficaria vermelho (nega user-sem-vínculo de qualquer modo) — a mudança é por clareza/cobertura, não por quebra.

**Não se toca:** `config/permission.php`, `app/Providers/AppServiceProvider.php` (`Gate::before`),
`app/Models/User.php`, `app/Models/Evento.php`, `EventoPolicy`, `AutorizaPorDepartamento`,
`TemDepartamento`, `database/seeders/DatabaseSeeder.php` (o `CapacidadesSeeder` já está registrado).

**Regras de sempre** (CLAUDE.md): pt-BR em tudo; migrations **só incrementais** (nunca
`fresh`/`refresh`/`wipe`/`reset`/seed destrutivo); nada destrutivo no dev; guard `web`; Pint antes do
push; `docker compose exec -T app php artisan test`; cabeçalho de autoria nos PHP novos (**exceto
migrations**); commits atômicos; branch nova de `main` (ex.: `fase-b-departamento`).

## 14. Pontos a confirmar no passe adversarial

1. **"9 vs 8 cargos de diretor" (impacto real).** O kickoff (decisão 6) diz "9 cargos `diretor_*` têm
   `departamento_id`"; o código diz **8** (`diretor_presidente` é institucional, sigla `null`). Proposta:
   filtro **`cargos.departamento_id IS NOT NULL`** (8; exclui o Presidente). Confirmar que o Presidente
   **não** entra em `departamento_usuario`.
2. **DAS sem diretor.** `diretor_das` está em `CARGOS_EXTRA` (ignorado no import) ⇒ backfill produz **0
   vínculos** para o DAS. Aceitar como esperado (correção manual = Fase C) ou tratar agora?
3. **`Palestrante → DED (todos)` inclui diretores-de-palestra.** Confirmar que "todos os `Palestrante`"
   abrange registros que só têm papel `diretor` no `palestra_pessoa` (critério "quem mantém o cadastro").
4. **Só capacidade nas novas policies (§3(a)).** Confirmar que Palestra/Post/AgendaDia/Palestrante **não**
   recebem `view`/`viewAny` (visibilidade permanece em scopes/`podeSerVistoPor` do domínio, fora da policy).
5. **Regra de `criar` (§3(b)).** "permissão + pertencer a ≥1 departamento" (molde `Evento`). Alternativa:
   só a permissão. Recomendo a primeira (fail-closed; simétrica ao `Evento`).
6. **Comando vs seeder, e um vs dois comandos.** Recomendo **comando** (não seeder) e **dois** comandos
   separados (conteúdos §7; diretores §8). Alternativa: um comando com duas etapas. Baixo risco.
7. **Posição de `'palestrante'` em `RECURSOS`.** Recomendo **append no fim** (ordem estável dos 16
   existentes). Alternativa: após `'palestra'` (afinidade de domínio). Afeta só a ordem do array de teste.
8. **4 migrations separadas** (uma por pivot, padrão do projeto) vs 1 migration com 4 `Schema::create`.
   Recomendo 4 separadas. Baixo risco.
9. **Nome `departamento_agenda_dia`** (departamento-primeiro; exige `$table`/chaves explícitas no
   `belongsToMany`, o que já fazemos em todos). Confirmar (alternativa nativa: `agenda_dia_departamento`).
10. **`PoliciesFailClosedTest` — reescrever, não deletar** (§10.2). Ele **não** quebraria (nega
    user-sem-vínculo de qualquer modo); é **renomeado + estendido** com cenários positivos, **preservando**
    a guarda de auto-discovery. Confirmar que é a intenção (correção do passe O1).
11. **Semântica do filtro do backfill de diretores** (§8, refinamento R3). `departamento_id IS NOT NULL` =
    "ocupa cargo de um departamento" ⇒ candidato a editor dele. Hoje coincide com os diretores, mas o admin
    pode criar cargo não-institucional **com** departamento no `CargoResource`. Recomendo **manter o filtro
    semântico** (e cravar a invariante em teste); restringir literalmente a "diretor" só se o dono quiser
    excluir futuros vice/coordenador.
12. **`AgendaMetaMes` fora da Fase B** (§2, §11, refinamento O2). Tem `AgendaMetaMesResource` próprio, mas é
    metadado ("tema do mês"), não conteúdo editorial por dia. Recomendo **fora** (sem departamento/policy);
    rever só na Fase D se o editor do DECOM precisar do tema junto com os dias.
