# Spec — Fase de Auditoria · Trilha de alterações do núcleo de autorização (spatie/laravel-activitylog)

> Autoria: Thiago Mourão — https://github.com/MouraoBSB — 2026-07-13
> Enquadramento travado com o dono (dono + consultor) no kickoff da Fase de Auditoria. Este spec **não**
> improvisa além das decisões travadas; **cada afirmação sobre o terreno foi verificada contra o código
> real** (evidência `arquivo:linha` no §2) e os pontos que o enquadramento não previu — ou em que ele
> divergiu do código — estão no §14 para o **passe adversarial**.
> Destino: **SPEC** — vai ao passe adversarial (o consultor confere cada afirmação contra `vendor/` do
> activitylog e o código real) **antes** de virar plano de implementação. **Nenhum código nesta fase.**
> Fundação: [SPEC — Fase A](2026-07-11-fase-a-modelo-capacidades.md) (PR #25),
> [SPEC — Fase B](2026-07-11-fase-b-departamento-conteudos.md) (PR #26) e
> [SPEC — Fase C](2026-07-12-fase-c-matriz-papel-capacidade.md) (PR #27), todas mescladas na `main`.

## 1. Contexto e objetivo

As Fases A→C montaram e **ligaram** o núcleo de autorização: **20 permissions** `recurso.acao` (guard
`web`), a **matriz papel×capacidade** (único escritor de `role_has_permissions`), o **papel** do usuário
(`model_has_roles`) e o **vínculo editorial** `departamento_usuario`. Hoje **todo esse núcleo só é editável
por administrador**, dentro do `/admin`. A **Fase D** vai abrir edição a **não-admin** em `/minha-conta`
(painel `perfil`) — e, a partir daí, mudanças sensíveis de acesso deixam de ter um único autor confiável.

Esta **Fase de Auditoria** instala `spatie/laravel-activitylog` e instrumenta **o núcleo de autorização**
com uma **trilha append-only** — **antes** da Fase D, para que nenhuma edição de acesso (atual no `/admin`
ou futura no `/minha-conta`) fique sem rastro. É uma **fase interstitial** na trilha de capacidades
(`A → B → C → [Auditoria] → D`); **não** é a "Fase D" e **não** reabre nada de A/C.

**Escopo v1 = núcleo de autorização.** Dois mecanismos, **um pacote**, **um helper**:

1. **Mecanismo automático — trait `LogsActivity` só no `User`.** Audita as **5 colunas** de identidade/status
   (`name`, `email`, `google_id`, `socio`, `ativo`) em **criação, atualização e exclusão**, em **qualquer
   contexto** (é model-event: pega painel, console, seeder, fila). Registra o **diff campo a campo** (velho
   → novo) que o pacote já produz.
2. **Mecanismo manual — log com diff antes/depois nos 3 pivôs de autorização.** O trait **não** captura
   pivôs; e os **eventos do spatie são insuficientes** (§2.7). Então, **no próprio ponto de escrita**,
   lê-se o estado **antes**, deixa-se o `sync` rodar, e loga-se **só o diff** (adicionados/removidos):
   - **(a)** matriz papel×capacidade — `MatrizCapacidades::salvar()` (`syncPermissions`), subject = **Role**;
   - **(b)** papel do usuário — `UserResource` campo `roles` (`sync`), subject = **User**;
   - **(c)** vínculo editorial — `UserResource` campo `departamentos` (`sync`), subject = **User**.

**Nada muda no comportamento visível.** Não há tela de auditoria nesta fase (só a trilha); o `/admin`
continua admin-only; a visibilidade pública é intocada. A diferença é que passa a existir a tabela
`activity_log` acumulando entradas a cada escrita no núcleo de autorização.

> **A "porta" nasce pronta agora.** Toda entrada grava de qual **painel** veio a ação (`admin` × `perfil`).
> Na v1 os valores emitidos são **`admin`** (ação nos painéis) e **`sistema`** (console/fila/seeder, sem
> request de painel). **`perfil`** só passará a ser emitido quando a Fase D criar o painel homônimo — **sem
> uma linha de código a mais**, porque o predicado é derivado do painel corrente (§6).

## 2. Terreno confirmado por leitura (não presumir diferente)

Verificado no código em 2026-07-13 (8 frentes read-only). Versões travadas em `composer.lock`:
`laravel/framework` **v13.17.0**, `filament/filament` **v5.6.7**, `spatie/laravel-permission` **6.25.0**.

### 2.1 Campo limpo — pacote/tabela/config ausentes (sem colisão)
- `spatie/laravel-activitylog` **NÃO** está em `composer.json` (`require` `:8-20`; `require-dev` `:21-29`) nem
  em `composer.lock` (grep `activitylog` → nada).
- **Não existe** `config/activitylog.php` (lista de `config/` tem 18 arquivos; ele não está).
- **Nenhuma** migration menciona `activity` (glob `database/migrations/*activity*` → vazio) → as tabelas
  `activity_log`/`activity_logs` **não existem**; publicar a migration do pacote **não colide**.

### 2.2 `User` — colunas, traits, casts (Mecanismo 1)
- `app/Models/User.php:25` — `use HasFactory, HasRoles, Notifiable, TemIniciais;` → **sem** trait de
  auditoria hoje; **usa `HasRoles`** (spatie).
- `#[Fillable(['name','email','password','origem_legado_id','socio','ativo','email_verified_at','google_id'])]`
  (`User.php:20`); `#[Hidden(['password','remember_token'])]` (`:21`).
- `casts()` (`User.php:71-81`): `email_verified_at => datetime`, `socio => boolean`, `ativo => boolean`
  (**sem** cast `hashed` em `password`, por causa dos hashes legados).
- Colunas reais de `users` (migrations): `id`, `origem_legado_id` (uBigInt null/unique,
  `2026_07_03_000006:12`), `name`, `email`, `google_id` (string null/unique, `2026_07_04_000001:12`),
  `socio` (boolean default false, `2026_07_03_000006:13`), `ativo` (boolean default true,
  `2026_07_03_000006:14`), `email_verified_at`, `password`, `remember_token`, `created_at/updated_at`.
  → **"status de conta" = coluna `ativo`.**

### 2.3 Matriz papel×capacidade — ponto de escrita (a)
- `app/Filament/Pages/MatrizCapacidades.php` — `class MatrizCapacidades extends Page` (`:27`), admin-only pelo
  gate único do painel (`User::canAccessPanel()` exige `administrador`; reforço em `AppServiceProvider.php:59`).
- `salvar()` (`:106-129`): monta `$marcados` (lista de `"{$recurso}.{$acao}"` marcados) e, por papel de
  `GlossarioUsuarios::PAPEIS_EDITAVEIS = ['trabalhador','diretor']` (`GlossarioUsuarios.php:17`), chama
  **`Role::findByName($papel,'web')->syncPermissions($marcados);`** (`:123`). `syncPermissions` faz **set
  completo** (substitui; o que sai de `$marcados` é **removido**).
- **O "antes" não sobrevive:** `mount()` (`:42-57`) lê o estado atual com
  `Role::findByName($papel,'web')->permissions()->pluck('name')->all()` e joga no form; **não** guarda em
  propriedade. → para o diff, **reler do banco dentro de `salvar()`, imediatamente antes do `sync`.**
- Universo: 20 permissions (`RECURSOS` = evento/palestra/post/agenda/palestrante ×
  `ACOES` = ver/criar/editar/excluir, `GlossarioCapacidades.php:13,15`), guard `web`, só para os 2 papéis.

### 2.4 `UserResource` — pontos de escrita (b) e (c)
- `app/Filament/Resources/Users/UserResource.php:32` — `class UserResource extends Resource`.
- Campo **`roles`** (`:85-93`): `Select::make('roles')->relationship('roles','name')->multiple()->maxItems(1)
  ->preload()->required()`. Pivot **`model_has_roles`** (morph `model_id`+`model_type`).
- Campo **`departamentos`** (`:107-111`): `Select::make('departamentos')->relationship('departamentos','nome')
  ->multiple()->preload()`. Pivot **`departamento_usuario`**.
- **Sync é o `->relationship()` padrão do Filament**, executado por `saveRelationships()`. **Não há hook
  custom** nas Pages: `CreateUser.php` e `EditUser.php` são "vazias" (só declaram `$resource`; `EditUser` só
  adiciona `DeleteAction`). **Não há Observer** de `User`.
- ⚠️ **Timing verificado no vendor (crava o §5, resolve B1):** no `EditRecord`, o `saveRelationships()` roda
  **dentro** de `form->getState()` — `schemas/src/Concerns/HasState.php:497` (ramo `if ($afterValidate)`),
  chamado por `EditRecord::save()` em `EditRecord.php:168` — ou seja, **antes** de
  `mutateFormDataBeforeSave()` (`:174`), `handleRecordUpdate()` (`:176`) e `callHook('afterSave')` (`:178`).
  Logo `mutateFormDataBeforeSave` **NÃO serve** para o snapshot "antes" (leria o pivô já sincronizado →
  diff vazio). No `CreateRecord`, `saveRelationships()` é explícito em `CreateRecord.php:115`, **antes** de
  `callHook('afterCreate')` (`:117`) → `afterCreate` está correto. → snapshot do "antes" em `save()`
  **sobrescrito** (antes do `parent::save()`), com **query fresca**; log em `afterSave()`/`afterCreate()`.
- ⚠️ No mesmo bloco há `setores` (`:95-99`) e `cargos` (`:101-105`), que sincronizam pelo mesmo mecanismo —
  **fora do escopo** (RH, não capacidade). O SPEC **não** os loga.

### 2.5 Painel Filament — a "porta"
- **Um único painel:** `AdminPanelProvider` → `->id('admin')->path('admin')->default()`
  (`AdminPanelProvider.php:44-49`). Painel `perfil`/`minha-conta` **não existe** (Fase D).
- O middleware `SetUpPanel` fixa o painel corrente por rota (`Filament::setCurrentPanel($panel)`,
  `vendor/filament/filament/src/Http/Middleware/SetUpPanel.php:15`). Em qualquer request de `/admin`, o
  painel `admin` já está corrente.
- API (`FilamentManager.php`): `getCurrentPanel(): ?Panel` (`:122`) retorna **exatamente** o painel setado
  pelo middleware (ou `null`); `getCurrentOrDefaultPanel()`/`getId()` fazem **fallback ao default** (`admin`),
  o que daria **falso positivo** em console/fila/request público. → predicado correto no §6.

### 2.6 Os 3 pivôs — shape do diff
- `role_has_permissions` (`create_permission_tables.php:97-112`): `permission_id`, `role_id`; **sem
  timestamps**. Subject natural do diff = **Role**; rótulo = `Permission.name`.
- `model_has_roles` (`:74-95`): `role_id`, `model_type`, `model_id`; **sem timestamps**. Subject = **User**;
  rótulo = `Role.name`.
- `departamento_usuario` (`2026_07_11_000001:11-17`): `id`, `user_id`, `departamento_id`, unique; **sem
  timestamps**, **sem coluna extra**. Subject = **User**; rótulo legível = `Departamento.nome` (`nome`
  string not-null, `create_departamentos_table.php:14`; `slug`/`sigla` únicos disponíveis; `id` é a chave
  estável). Relação só existe pelo lado `User::departamentos()` (`User.php:55-58`); `Departamento` **não**
  define `usuarios()`.
- **Consequência:** nenhum pivô tem `updated_at` nem model Eloquent de pivô observável → a instrumentação
  **tem** de envolver a chamada de escrita (não dá para observar o pivô).

### 2.7 Por que **não** usar os eventos do spatie (diretriz travada)
- `config/permission.php:126` → **`'events_enabled' => false`** (permanece assim; **não** ligar).
- Mesmo se ligados, seriam **incompletos** para os nossos caminhos de escrita:
  - `syncPermissions()` faz `detach()` **direto** e **não** dispara `PermissionDetached` → **toda remoção**
    da matriz seria **perdida**.
  - O Filament salva `roles` via `sync()` do **relacionamento**, que **não** passa por `syncRoles()` → o
    `RoleAttached` **não** dispara no painel.
- → **captura dos 3 pivôs = log manual com diff antes/depois no ponto de escrita** (§5). Escritas de pivô
  **via console/tinker/fila** ficam como **fronteira conhecida** (§8), **não** cobertas por eventos meia-boca.

### 2.8 Convenções de teste
- `phpunit.xml:26-27` — SQLite `:memory:`; guard `web`; `BCRYPT_ROUNDS=4`. Todo teste com DB usa
  `RefreshDatabase`.
- Setup: `(new EstruturaCemaSeeder)->run()` (4 papéis + 8 departamentos + cargos) + `CapacidadesSeeder` (20
  permissions). Auth de admin pelo helper **`actingAsAdmin(): User`** (`tests/TestCase.php:11-19`).
- Páginas Filament: `Livewire::test(Page::class)->fillForm([...])->call('salvar'|'create'|'save')
  ->assertHasNoFormErrors()`; EditRecord recebe `['record' => $model->getKey()]`.
- Assert em tabela de infra: **`assertDatabaseHas('activity_log', [...])`** (molde
  `CapacidadesSeederTest.php:31` para `permissions`) e/ou `DB::table('activity_log')->where(...)->count()`
  (molde de pivô `DepartamentoUsuarioTest.php:19`). Inspeção de `properties` (JSON) via `Activity::query()`.
- Factories existentes: `UserFactory` (só colunas; papel/departamento anexados no teste). **Não** há factory
  de Role/Permission/Departamento — vêm dos seeders.

## 3. Decisões travadas (do enquadramento) e cravadas por verificação

Do kickoff (dono + consultor, 13/jul) — **não reabrir**:

1. **Retenção INDEFINIDA, sem poda.** **Não** agendar `activitylog:clean` (append-only puro). Salvaguarda
   **inerte**: `delete_records_older_than_days` alto (ex.: **3650**), **nunca** ligado no scheduler.
2. **Escopo v1 = núcleo de autorização.** Conteúdos (`Post/Evento/Palestra/AgendaDia/Palestrante`) e o
   vínculo depto↔conteúdo ficam para a **Fase D** (grava no form do próprio conteúdo).
3. **setores/cargos do usuário: FORA** (estrutura de RH, não capacidade editorial).
4. **Append-only:** **sem UI de exclusão**, nem para admin. Na v1 **não** se registra nenhum Filament
   Resource de `Activity` (nem viewer). Um viewer futuro tem de ser read-only.
5. **IP + user-agent em toda entrada** (não vêm de graça): via `tapActivity()` no trait e via
   `withProperties()` nos manuais — **de uma fonte única** (o helper, §7).
6. **Porta nas properties** (admin×perfil), derivada do painel corrente.
7. **Backfill, se houver, dentro de `activity()->withoutLogs()`** (não poluir com "sistema").

### 3.1 Decisões do dono (respostas 13/jul) e diretriz de arquitetura

- **P1 — colunas do trait: `logOnly(['name','email','google_id','socio','ativo'])`** + `logOnlyDirty()` +
  `dontSubmitEmptyLogs()`. `logExcept(['password','remember_token'])` fica **redundante** com `logOnly` —
  no máximo como **defesa em profundidade opcional**, não como mecanismo. (Motivo: `logExcept` sozinho
  logaria também `email_verified_at` — ruído a cada verificação de e-mail — `origem_legado_id` e timestamps.)
- **P2 — eventos do ciclo de vida: `created` + `updated` + `deleted`** (o conjunto padrão do trait). A
  exclusão de conta é hard-delete (`DeleteAction` em `EditUser`); logar `deleted` grava a identidade final
  (name/email) **antes** do registro sumir.
- **P3 — porta `sistema` quando não há request** (console/fila/seeder). O trait é model-event, então cobre
  bem as **colunas** do `User` mesmo por tinker/seeder/fila. → **a porta tem 3 valores na v1: `admin`,
  `sistema`** (e `perfil` na Fase D).
- **Arquitetura (verificado no vendor, §2.7): NÃO usar eventos do spatie.** Captura dos 3 pivôs = **log
  manual com diff antes/depois**. Cobertura de pivô via console/tinker é **fronteira conhecida** (§8),
  **fora do v1** — e **não** se tenta remendar com os eventos incompletos.

## 4. Mecanismo 1 — Trait `LogsActivity` no `User` (automático)

**Artefato alterado:** `app/Models/User.php`. Adicionar `use ...\LogsActivity;` ao `use` da linha 25 e
implementar `getActivitylogOptions()` + `tapActivity()`.

```php
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Contracts\Activity as ActivityContract;

public function getActivitylogOptions(): LogOptions
{
    return LogOptions::defaults()
        ->useLogName('usuario')
        ->logOnly(['name', 'email', 'google_id', 'socio', 'ativo'])
        ->logOnlyDirty()
        ->dontSubmitEmptyLogs()
        ->setDescriptionForEvent(fn (string $evento): string => match ($evento) {
            'created' => 'usuário criado',
            'updated' => 'usuário atualizado',
            'deleted' => 'usuário excluído',
            default   => "usuário {$evento}",
        });
}

// IP + user-agent + porta em TODA entrada automática (fonte única: o helper, §7).
public function tapActivity(ActivityContract $activity, string $eventName): void
{
    $activity->properties = $activity->properties->merge(AuditoriaAutorizacao::contexto());
}
```

- **Eventos:** `created`/`updated`/`deleted` (padrão — não restringir). `User` **não** tem SoftDeletes → sem
  `restored`.
- **Diff campo a campo:** com `logOnlyDirty`, o pacote grava `properties.attributes` (novo) e `properties.old`
  (anterior) **só das colunas mudadas** entre as 5. `dontSubmitEmptyLogs` evita entrada quando nenhuma das 5
  mudou (ex.: só `password`/`remember_token`/`email_verified_at` → **nenhuma** entrada).
- **Causer:** o pacote resolve o usuário autenticado automaticamente; em `sistema` (sem auth) fica `null`, e a
  porta `sistema` já sinaliza a origem.
- **Porta cobre todos os contextos** aqui, porque é model-event (painel `admin`, console/seeder/fila
  `sistema`, e `perfil` na Fase D).

## 5. Mecanismo 2 — Log manual dos 3 pivôs (diff antes/depois)

Padrão único nos 3 pontos: **ler `antes` → deixar o `sync` rodar → logar o diff pelo helper** (§7). O helper
**não** emite entrada com diff vazio (equivalente manual do `dontSubmitEmptyLogs`).

**(a) Matriz — `app/Filament/Pages/MatrizCapacidades.php::salvar()` (alterado).** Dentro do loop por papel,
reler o "antes" imediatamente antes do `sync` (§2.3) e logar por papel (subject = **Role**):

```php
foreach (GlossarioUsuarios::PAPEIS_EDITAVEIS as $papel) {
    $marcados = /* ...monta como hoje... */;
    $role  = Role::findByName($papel, 'web');
    $antes = $role->permissions()->pluck('name')->all();   // ANTES (relê do banco)
    $role->syncPermissions($marcados);                      // como hoje
    AuditoriaAutorizacao::registrarPapelCapacidades($role, $antes, $marcados);
}
```

**(b)+(c) UserResource — hooks NOVOS em `CreateUser` e `EditUser`.** O sync é o `->relationship()` padrão e
roda **dentro de `getState()`, antes de `afterSave`** (§2.4, B1). Por isso o snapshot do "antes" tem de vir
**antes** de `parent::save()`, sempre com **query fresca** (`->roles()`/`->departamentos()` — parênteses =
consulta ao banco; **nunca** a propriedade cacheada, que pode estar hidratada pós-sync):

- **`EditUser`:** sobrescrever `save()` para tirar o snapshot **antes** do `parent::save()`, e logar em
  `afterSave()` (pós-sync, ainda dentro da transação do `save()` → o `activity_log` é atômico com a mudança):

  ```php
  public array $papelAntes = [];   // sempre inicializado ([]): propriedade Livewire tipada não-inicializada
  public array $deptosAntes = [];  // quebra a hidratação ("must not be accessed before initialization"). [id => nome]

  public function save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void
  {
      $this->papelAntes  = $this->record->roles()->pluck('name')->all();       // ANTES (query fresca)
      $this->deptosAntes = $this->record->departamentos()->pluck('nome', 'id')->all();
      parent::save($shouldRedirect, $shouldSendSavedNotification);
  }

  protected function afterSave(): void
  {
      $papelDepois  = $this->record->roles()->pluck('name')->all();            // DEPOIS (query fresca)
      $deptosDepois = $this->record->departamentos()->pluck('nome', 'id')->all();
      AuditoriaAutorizacao::registrarPapelUsuario($this->record, $this->papelAntes, $papelDepois);
      AuditoriaAutorizacao::registrarDepartamentosUsuario($this->record, $this->deptosAntes, $deptosDepois);
  }
  ```

- **`CreateUser`:** não há "antes" (conjuntos vazios). Em `afterCreate()` (roda após o `saveRelationships`
  explícito de `CreateRecord.php:115`), reler por query fresca e logar `[] → depois`.

Ambos com subject = **User**. Duas entradas independentes por save (papel; departamentos), cada uma
**condicionada ao seu diff** (helper no-op se vazio). **`mutateFormDataBeforeSave` NÃO é usado** (B1).

> **Exclusão de usuário:** o `DeleteAction` do `EditUser` é hard-delete; o **Mecanismo 1** já grava
> `usuário excluído` (subject = User, com a identidade final). As remoções em cascata de `model_has_roles`/
> `departamento_usuario` **não** geram entrada `autorizacao` própria — a exclusão do usuário **subsume**
> esses pivôs. Comportamento **intencional** (§8), não lacuna.

## 6. A "porta" (admin × sistema × perfil)

Predicado único, no helper (§7):

```php
public static function porta(): string
{
    return \Filament\Facades\Filament::getCurrentPanel()?->getId() ?? 'sistema';
}
```

- **`admin`** — request servido por um painel Filament cujo id é `admin` (o `SetUpPanel` já setou o painel
  corrente, §2.5). Todos os 3 logs manuais rodam **dentro do `/admin`** → porta `admin` **garantida** na v1.
- **`sistema`** — sem painel corrente: console (artisan/tinker), fila/worker, seeders, importadores,
  request web fora de painel. Só o **Mecanismo 1** chega aqui na v1 (os manuais são só de painel).
- **`perfil`** — **reservado**; passa a ser emitido **automaticamente** quando a Fase D registrar o painel
  `perfil` (mesmo predicado, zero código a mais). **Não** emitido na v1.
- **Por que não `getCurrentOrDefaultPanel()`/`getId()`:** ambos caem no default (`admin` é `->default()`) →
  classificariam console/fila como `admin` — falso positivo inaceitável numa trilha de auditoria (§2.5).

## 7. Formato do log e o helper único

**Dois `log_name`**, para a query da trilha (e de um viewer futuro) ficar limpa:

| `log_name`    | Origem                        | Subject | `description` (pt-BR)                              | `event`        |
|---------------|-------------------------------|---------|----------------------------------------------------|----------------|
| `usuario`     | Mecanismo 1 (trait)           | User    | `usuário criado/atualizado/excluído`               | created/updated/deleted |
| `autorizacao` | Mecanismo 2 — matriz (a)      | Role    | `capacidades do papel {papel} alteradas`           | (null)         |
| `autorizacao` | Mecanismo 2 — papel (b)       | User    | `papel do usuário alterado`                        | (null)         |
| `autorizacao` | Mecanismo 2 — departamentos (c)| User   | `departamentos do usuário alterados`               | (null)         |

**Shape das `properties`.** Toda entrada carrega **porta + ip + user_agent** (do helper). Além disso:

- **Mecanismo 1 (automático):** o pacote grava o diff campo a campo:
  ```json
  { "attributes": {"ativo": false}, "old": {"ativo": true},
    "porta": "admin", "ip": "203.0.113.7", "user_agent": "Mozilla/5.0 ..." }
  ```
- **Mecanismo 2 (manual):** diff de listas, shape do topo uniforme (`diff.adicionados`/`diff.removidos`):
  ```json
  { "diff": {"adicionados": ["palestra.editar"], "removidos": ["post.excluir"]},
    "porta": "admin", "ip": "203.0.113.7", "user_agent": "Mozilla/5.0 ..." }
  ```
  **Vocabulário dos itens do diff por ponto:**
  - **(a) capacidades** → `Permission.name` (string). No spatie o **nome é a identidade** (as 20 permissions
    são fixas) → sem id.
  - **(b) papéis** → `Role.name` (string). Idem — os 4 papéis são fixos, nome é a chave → sem id.
  - **(c) departamentos** → **objeto `{id, nome}`** (R3): `id` é a chave **estável a rename**, `nome` o
    rótulo **histórico** (o `Departamento` é editável pelo admin, ao contrário de papéis/permissions):
    ```json
    { "diff": {"adicionados": [{"id":3,"nome":"DECOM"}], "removidos": []},
      "porta":"admin", "ip":"...", "user_agent":"..." }
    ```
  A assimetria (nome puro em a/b; `{id,nome}` em c) é **intencional** e documentada — reflete que só o
  departamento é renomeável.

**Helper único — `app/Support/Autorizacao/AuditoriaAutorizacao.php` (NOVO).** Segue o molde do domínio
(`GlossarioCapacidades`/`CardinalidadePalestra`): cabeçalho de autoria, classe sem estado, métodos
`public static`. Concentra **porta + contexto + diff + escrita**, reusado pelo `tapActivity` (M1) e pelos 3
pontos manuais (M2):

```php
// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-13
final class AuditoriaAutorizacao
{
    public const LOG = 'autorizacao';

    public static function porta(): string { /* §6 */ }

    /** porta + ip + user_agent — fonte única de IP/UA (null fora de request HTTP). */
    public static function contexto(): array
    {
        $req = request();
        return [
            'porta'      => self::porta(),
            'ip'         => $req?->ip(),
            'user_agent' => $req?->userAgent(),
        ];
    }

    /** {adicionados, removidos} a partir de duas listas de nomes. */
    public static function diff(array $antes, array $depois): array
    {
        return [
            'adicionados' => array_values(array_diff($depois, $antes)),
            'removidos'   => array_values(array_diff($antes, $depois)),
        ];
    }

    /** Escreve 1 entrada 'autorizacao'; no-op se o diff for vazio. */
    public static function registrar(Model $subject, string $descricao, array $diff): void
    {
        if (! $diff['adicionados'] && ! $diff['removidos']) {
            return;
        }
        activity(self::LOG)
            ->performedOn($subject)
            ->causedBy(auth()->user())
            ->withProperties(['diff' => $diff] + self::contexto())
            ->log($descricao);
    }

    // Açúcares por ponto (montam descrição + vocabulário do diff). a/b usam diff() sobre listas de nome;
    // c usa diff por chave [id => nome] e emite itens {id, nome}:
    public static function registrarPapelCapacidades(Role $papel, array $antes, array $depois): void { /* subject=Role, "capacidades do papel {$papel->name} alteradas" */ }
    public static function registrarPapelUsuario(User $u, array $antes, array $depois): void         { /* subject=User, "papel do usuário alterado" */ }
    /** @param array<int,string> $antes/$depois no formato [id => nome]; diff por id, itens {id, nome} (R3) */
    public static function registrarDepartamentosUsuario(User $u, array $antes, array $depois): void  { /* subject=User, "departamentos do usuário alterados" */ }
}
```

## 8. Fronteira conhecida (declarada, fora do v1)

- **Escritas de pivô fora do painel** (`Role::...->givePermissionTo()`/`syncPermissions()`,
  `$user->syncRoles()`, `$user->departamentos()->sync()` via **console/tinker/fila/seeder**) **não** são
  logadas na v1. Os manuais estão amarrados aos **pontos de escrita de painel** (matriz/UserResource); não há
  um interceptador global de pivô — e os eventos do spatie são incompletos (§2.7). **As COLUNAS do `User`
  por esses canais continuam cobertas** (M1 é model-event); só o **eixo de pivô** por console fica de fora.
  Um teste-guarda (§10) fixa essa expectativa para um dev futuro não presumir cobertura.
- **Exclusão de usuário:** pivôs em cascata **não** geram entrada `autorizacao` — a entrada `usuário excluído`
  (M1) subsume (§5).
- **Sem viewer/telas de auditoria** nesta fase.

## 9. Ordem de execução (para o plano — não implementar aqui)

1. `composer require "spatie/laravel-activitylog:^4.12"` — constraint cravada (R1): a **v5 exige PHP ^8.4**
   e o projeto é **8.3**, então o resolvedor trava a **4.12.3**, que declara `illuminate/* ^13`. Não é
   limitação de Laravel; é do PHP. (Detalhe em §14 A1.)
2. `vendor:publish` das **migrations** e da **config** do pacote; **inspecionar** a migration publicada
   (§14 A2) → `php artisan migrate` **incremental** (nunca `fresh/refresh/wipe/reset`).
3. `config/activitylog.php`: manter defaults; `delete_records_older_than_days` alto (3650) **inerte** —
   **não** agendar `activitylog:clean`.
4. Helper `App\Support\Autorizacao\AuditoriaAutorizacao` (porta + contexto + diff + registrar + açúcares).
5. Mecanismo 1: trait no `User` (`getActivitylogOptions` + `tapActivity`).
6. Mecanismo 2 (a): diff antes/depois em `MatrizCapacidades::salvar()`.
7. Mecanismo 2 (b)+(c): hooks novos em `CreateUser`/`EditUser` (snapshot antes + log depois).
8. Testes por ponto de escrita (§10) → **Pint** + **suíte inteira** no container.

## 10. O que o spec deve provar (testes desta fase)

Cobertura **por ponto de escrita** (SQLite `:memory:`, `RefreshDatabase`, seeders + `actingAsAdmin`,
`assertDatabaseHas('activity_log', ...)` + `Activity::query()` para `properties`).

**Infra — `tests/Feature/Autorizacao/AuditoriaInfraTest.php`**
1. A tabela `activity_log` existe e uma entrada ida-e-volta funciona (pacote instalado/migrado).
2. **Nenhum** Filament Resource de `Activity` registrado no painel `admin` (append-only, sem UI de
   exclusão) — asserção sobre os resources registrados do painel.

**Mecanismo 1 — `tests/Feature/Autorizacao/AuditoriaUsuarioTest.php`**
3. `update` de `ativo` (ou `email`/`name`/`socio`/`google_id`) → 1 entrada `log_name='usuario'`,
   `event='updated'`, subject = o `User`, `properties.old`/`attributes` com a coluna mudada.
4. `create` → entrada `usuário criado`; `delete` → entrada `usuário excluído` (com a identidade final).
5. Alterar **só** `password` (ou `email_verified_at`) → **nenhuma** entrada (logOnly 5 + dontSubmitEmptyLogs).
6. **Porta:** `update` via console (sem painel) → `properties.porta='sistema'`; via `UserResource`
   (`EditUser`, painel) → `properties.porta='admin'`.

**Mecanismo 2 (a) matriz — `tests/Feature/Autorizacao/AuditoriaMatrizTest.php`**
7. `Livewire::test(MatrizCapacidades)->fillForm(['diretor.palestra.editar'=>true])->call('salvar')` →
   entrada `autorizacao`, subject = Role(diretor), `description='capacidades do papel diretor alteradas'`,
   `diff.adicionados` contém `palestra.editar`, `porta='admin'`, `ip`/`user_agent` presentes.
8. Desmarcar e salvar → `diff.removidos` contém `palestra.editar`.
9. Salvar **sem** mudança → **nenhuma** entrada nova; salvar mexendo só em `diretor` → **nenhuma** entrada
   para `trabalhador` (diff vazio no-op).

**Mecanismo 2 (b)+(c) UserResource — `tests/Feature/Autorizacao/AuditoriaUserResourceTest.php`**
10. `Livewire::test(CreateUser)->fillForm([... papel diretor + 1 departamento ...])->call('create')` →
    2 entradas `autorizacao` (papel `adicionados=['diretor']`; departamentos `adicionados=[{id,nome}]`),
    subject = User, `porta='admin'`.
11. `Livewire::test(EditUser, ['record'=>$u->getKey()])->fillForm([...])->call('save')`: trocar papel
    `diretor→trabalhador` → `diff.removidos=['diretor']`, `adicionados=['trabalhador']`; adicionar/remover
    departamento → `diff` com itens `{id,nome}`. **Este teste é o guard do B1** (falharia com o snapshot em
    `mutateFormDataBeforeSave`; passa com o `save()` sobrescrito).
12. `EditUser` salvando sem mexer em papel/departamento → **nenhuma** entrada `autorizacao`.

**Fronteira (§8) — guarda**
13. Escrita de pivô por console (ex.: `Role::findByName('diretor','web')->givePermissionTo('palestra.editar')`
    fora de painel) → **nenhuma** entrada `autorizacao` (fixa a fronteira conhecida, sem prometer cobertura).

## 11. Fora de escopo (não fazer nesta fase)

- **Conteúdos** (`Post/Evento/Palestra/AgendaDia/Palestrante`) e o **vínculo depto↔conteúdo** — **Fase D**
  (grava no form do próprio conteúdo).
- **setores/cargos** do usuário — RH, não capacidade.
- **Eventos do spatie** (`events_enabled`, `RoleAttached`/`PermissionDetached`) — **não** ligar, **não** usar.
- **Pruning/retention** (`activitylog:clean` no scheduler) — **nunca** (retenção indefinida).
- **Viewer/tela de auditoria** e qualquer Filament Resource de `Activity` — não nesta fase.
- **Emissão da porta `perfil`** — Fase D (o gancho já nasce pronto).
- **Cobertura de pivô por console/tinker/fila** — fronteira conhecida (§8).
- **Migrations do domínio** — só as **do pacote** (publicadas), nada mais.

## 12. Ciências (não são tarefa desta fase)

- **Pivô por console** vira rastreável no dia em que houver um ponto único de escrita de pivô (serviço de
  domínio) — aí o log manual migra para lá e a fronteira §8 fecha. Hoje os escritores são a matriz e o
  `UserResource`; não vale criar abstração antes da Fase D.
- **`id`+`nome` no diff de departamentos (R3, decidido):** logamos **`{id, nome}`** — `id` para reconciliar
  após rename; `nome` como rótulo histórico. Papéis/permissions ficam por nome (identidade fixa no spatie).
- **`event` nos manuais:** deixamos `null` (o `log_name`+`description` já classificam). Um viewer que queira
  filtrar por tipo de mudança poderia setar `->event('capacidades'|'papeis'|'departamentos')` — barato de
  acrescentar depois.
- **Causer `null` em `sistema`:** correto por ora (porta sinaliza a origem). Se um comando quiser se
  identificar (ex.: `causedBy` um usuário de serviço), é aditivo.

## 13. Artefatos

**Novos**
- `app/Support/Autorizacao/AuditoriaAutorizacao.php` — helper único (porta + contexto + diff + registrar +
  açúcares dos 3 pontos). Cabeçalho de autoria.
- `config/activitylog.php` — publicado do pacote (retenção inerte 3650; não agendado).
- `database/migrations/*_create_activity_log_table.php` (+ `add_event_column`, `add_batch_uuid_column`) —
  publicadas do pacote, inspecionadas antes do `migrate`.
- `tests/Feature/Autorizacao/AuditoriaInfraTest.php`, `AuditoriaUsuarioTest.php`, `AuditoriaMatrizTest.php`,
  `AuditoriaUserResourceTest.php` — cobertura §10 (cabeçalho de autoria).

**Alterados**
- `composer.json`/`composer.lock` — `spatie/laravel-activitylog`.
- `app/Models/User.php` — trait `LogsActivity` + `getActivitylogOptions()` + `tapActivity()` (§4).
- `app/Filament/Pages/MatrizCapacidades.php` — diff antes/depois em `salvar()` (§5a).
- `app/Filament/Resources/Users/Pages/CreateUser.php` — `afterCreate()` (§5b/c).
- `app/Filament/Resources/Users/Pages/EditUser.php` — `save()` sobrescrito (snapshot "antes" com query
  fresca) + `afterSave()` (log pós-sync). **Não** usa `mutateFormDataBeforeSave` (B1, §2.4/§5).

**Não se toca**: `config/permission.php` (eventos ficam `false`), as 5 policies, `AutorizaPorDepartamento`,
os pivôs, `EstruturaCemaSeeder`/`CapacidadesSeeder`/`DatabaseSeeder`, `AdminPanelProvider`, `setores`/`cargos`
no `UserResource`. **0 migrations de domínio** (só as do pacote).

**Regras de sempre** (CLAUDE.md): pt-BR em tudo (código, `description`, commit); **nada destrutivo no dev**
(nunca `fresh`/`refresh`/`wipe`/`reset`/seed destrutivo — só `migrate` incremental); publicar a migration do
pacote e **conferir o schema antes de rodar**; guard `web`; cabeçalho de autoria nos PHP novos; **Pint** antes
do push; `docker compose exec -T app php artisan test`; commits atômicos; branch nova de `main`
(ex.: `fase-auditoria-activitylog`).

## 14. Pontos do passe adversarial — resolvidos (passe de 13/jul)

> **Veredito: aprovado após corrigir B1** (timing do snapshot no `EditUser`), com **R1/R3 incorporados** e
> **R2/R4 como verificações de execução** (não redesenho). Abaixo, o registro do que foi levantado; o desenho
> travado está nas §2.4/§5/§7.

- **B1 (BLOQUEADOR) — timing do snapshot no `EditUser` — CORRIGIDO.** No Filament v5, `saveRelationships()`
  roda **dentro** de `form->getState()` (`schemas/src/Concerns/HasState.php:497`, via `EditRecord::save()`
  `EditRecord.php:168`), **antes** de `mutateFormDataBeforeSave` (`:174`)/`handleRecordUpdate`
  (`:176`)/`afterSave` (`:178`) — logo o snapshot em `mutateFormDataBeforeSave` leria o pivô já sincronizado
  (diff vazio). Correção: snapshot do "antes" em `save()` **sobrescrito** (antes do `parent::save()`), com
  **query fresca** (`->roles()`/`->departamentos()`); log em `afterSave()`. `CreateUser::afterCreate` já
  estava correto (após `CreateRecord.php:115`). Refletido em §2.4, §5, §10.11, §13. *(Verificado contra o
  `vendor/` do projeto, não só contra a memória.)*
- **A1 (R1) — RESOLVIDO.** Constraint = **`spatie/laravel-activitylog:^4.12`** (o resolvedor trava **4.12.3**).
  A **v5 exige PHP ^8.4**; o projeto é **8.3** → cai na 4.x (não por Laravel). A 4.12.3 declara
  `illuminate/* ^13`. Removida a dúvida "^4 cega" (§9.1).
- **A7 (R2) — CONFIRMADO** no fonte da 4.12.3: `Activity` faz `$casts['properties' => 'collection']` → o
  `->merge(...)` do `tapActivity` funciona. Sem ação.
- **A5 (R3) — RESOLVIDO:** diff de departamentos loga **`{id, nome}`** (id estável a rename; nome histórico).
  Permissions/roles seguem por **nome** (identidade fixa no spatie). Refletido em §5, §7, §12.
- **A2/A3/A8 (R4) — verificações do 1º commit da execução** (com `vendor/` presente), não redesenho:
  **(A2)** quais migrations o pacote publica + nome da tabela (esperado `activity_log`); **(A3)** `tapActivity`
  roda só no fluxo model-driven, não no `activity()->log()` manual (por isso os manuais levam ip/ua/porta via
  `withProperties` — mantido); **(A8)** `logOnly`+`dontSubmitEmptyLogs` não emite ao mudar só coluna fora das
  5 (o teste §10.5 depende disso).
- **A6/A9/A10 — verificação leve na execução:** **(A6)** `causedBy(auth()->user())` = o admin no `/admin`
  (guard `web`), sem causer duplo com o M1; **(A9)** update de `User` em request público pré-Fase D cairia em
  `sistema` — confirmar que não há caminho atual esperando `admin`; **(A10)** definir a asserção do §10.2
  (iterar `Filament::getPanel('admin')->getResources()` ou, se frágil, asserção de rota inexistente).
