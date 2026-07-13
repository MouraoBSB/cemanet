# Spec — Fase C · Matriz papel×capacidade + atribuição de vínculos

> Autoria: Thiago Mourão — https://github.com/MouraoBSB — 2026-07-12
> Enquadramento travado com o dono (dono + consultor) no kickoff da Fase C. Este spec **não**
> improvisa além das decisões travadas; **cada afirmação sobre o terreno foi verificada contra o
> código real** (evidência `arquivo:linha` no §2) e os pontos que o enquadramento não previu — ou
> em que o enquadramento **diverge do código** — estão no §14 para o **passe adversarial**.
> Destino: **SPEC** — vai ao passe adversarial **antes** de virar plano de implementação.
> Fundação: [SPEC — Fase A](2026-07-11-fase-a-modelo-capacidades.md) (PR #25) e
> [SPEC — Fase B](2026-07-11-fase-b-departamento-conteudos.md) (PR #26), ambas mescladas na `main`.

## 1. Contexto e objetivo

As Fases A e B deixaram o eixo **CAPACIDADE** de pé, mas **inerte**: **20 permissions** `recurso.acao`
(guard `web`; recursos `evento`/`palestra`/`post`/`agenda`/`palestrante` × ações
`ver`/`criar`/`editar`/`excluir`), **0 atribuídas a papéis** (`role_has_permissions` nasce vazia); 5
policies reais (`hasPermissionTo` + escopo de departamento via trait `AutorizaPorDepartamento`); o
vínculo editorial `departamento_usuario` (backfillado por comando); e os 4 conteúdos
(`Palestra`/`Post`/`AgendaDia`/`Palestrante`) departamentalizados (N:N). Tudo permanece **sem efeito**
porque **nenhum papel tem capacidade** e porque a edição de não-admin ainda não existe (é a Fase D, em
`/minha-conta`).

A **Fase C LIGA a autorização**. Três peças, **sem código de autorização novo** (não se toca em
policy/trait/pivot/contrato — Fases A/B):

1. **Peça 1 — Matriz papel×capacidade** (tela nova). Uma **Página Filament** dedicada, admin-only, onde o
   administrador liga/desliga cada uma das **20 capacidades** para os papéis **`trabalhador`** e
   **`diretor`**. Ao salvar, escreve `role_has_permissions` via `Role::syncPermissions()` — tirando as
   permissions da inércia. É o **primeiro e único escritor** de `role_has_permissions` no projeto.
2. **Peça 2 — Usuário → departamento**. Um `Select` de `departamentos` no `UserResource` (molde exato dos
   `setores`/`cargos` já ali). É onde o administrador atribui **manualmente** o(s) departamento(s) de cada
   usuário — inclusive o **caso presidente** (papel `diretor` + vínculo aos **8** departamentos).
3. **Peça 3 — Conteúdo → departamento**. O mesmo `Select` de `departamentos` no form dos **4 conteúdos**
   (`Palestra`/`Post`/`AgendaDia`/`Palestrante`; **`Evento` já tem** — Fase B). É onde a palestra ganha o
   **DECOM** como 2º departamento (**caso DECOM**).

Como nas fases anteriores, **nada muda no comportamento visível hoje**: o `/admin` continua admin-only
(o admin passa no `Gate::before`; o Filament v5 não consome as abilities pt-BR das policies) e a
**visibilidade pública** (`podeSerVistoPor`/`scopeVisiveisPara`/scopes de publicação) permanece intocada.
A diferença é que, **a partir desta fase**, existe um estado de `role_has_permissions` **não vazio** — a
fundação server-side deixa de ser inerte e passará a "morder" quando os forms do site existirem (Fase D).

> **A matriz é 2D — papel × capacidade.** O **departamento NÃO entra na matriz**: continua sendo o
> **vínculo do usuário** (`departamento_usuario`, filtro de objeto das policies A/B). São eixos separados:
> a matriz diz *"o papel diretor pode editar palestra"*; o vínculo diz *"este diretor cuida do DECOM"*; a
> policy exige **os dois** (mais o conteúdo ter departamento) — ver §7.

## 2. Terreno confirmado por leitura (não presumir diferente)

Verificado no código em 2026-07-12 (8 frentes read-only; `spatie/laravel-permission` **6.25.0** em
`composer.lock`).

### 2.1 Peça 1 — Molde da Página Filament (a matriz)

- **Duas páginas customizadas** existem: `app/Filament/Pages/ConfiguracoesBlog.php` e
  `ConfiguracoesAgenda.php` (só esses `.php` na pasta). **`ConfiguracoesBlog` é o molde-base correto** —
  ele faz `getState()` → **persistência explícita** (`ConfiguracoesBlog.php:68-78`), que é exatamente o
  fluxo `getState()` → `Role::syncPermissions`. `ConfiguracoesAgenda` depende de `->model($record)` +
  auto-save de relação de mídia (`ConfiguracoesAgenda.php:47,76`) — **não** aplicável.
- **Declaração**: `class ConfiguracoesBlog extends Page` (`:18`), **sem** `implements HasForms`/`use
  InteractsWithForms` no corpo. O suporte a formulário vem da base v5 (`HasSchemas` +
  `InteractsWithSchemas`, herdado de `Filament\Pages\Page`) — a página nova **não declara nada** disso.
- **Estado**: `public ?array $data = [];` (`:31`) + `->statePath('data')` (`:43`). O estado vive sob
  `data.*` no Livewire.
- **Assinatura do form (v5)**: `public function form(Schema $schema): Schema` usando
  **`Filament\Schemas\Schema`** (`:40`, import `:15`) — **não** `Filament\Forms\Form`.
- ⚠️ **A Blade NÃO renderiza `{{ $this->form }}` — renderiza `{{ $this->content }}`**
  (`resources/views/filament/pages/configuracoes-blog.blade.php:1-3`). O form é embutido via um método
  `content(Schema $schema): Schema` (`ConfiguracoesBlog.php:52-66`) que monta
  `Form::make([EmbeddedSchema::make('form')])->id('form')->livewireSubmitHandler('salvar')->footer([Actions::make([Action::make('salvar')->submit('salvar')])])`.
  **Refuta o item 8 do kickoff** ("a view renderiza `{{ $this->form }}`"). Imports:
  `Filament\Schemas\Components\{Actions, EmbeddedSchema, Form}` (`:12-14`) e `Filament\Actions\Action`
  (`:8`).
- **Pré-carga ao abrir**: `mount()` via `$this->form->fill([...])` (`:33-38`), lendo o estado atual (no
  Blog, de `Configuracao::valor`).
- ⚠️ **O método de salvar chama-se `salvar()`** (pt-BR, `:68-78`), **não** `save`/`submit`, e está amarrado
  em **dois lugares**: `livewireSubmitHandler('salvar')` **e** `Action::make('salvar')->submit('salvar')`.
  Nomear o método `save` sem trocar os dois faz o submit virar **no-op silencioso**. Ele faz `getState()`
  → persistência → `Notification::make()->title(...)->success()->send()`.
- **Navegação**: `navigationIcon`, `navigationLabel`, `title`, `slug` (`:22-28`). **NÃO** há
  `navigationGroup`/`navigationSort` — grep zerado em **todo** `app/Filament`: nenhum resource/page do
  projeto define grupo ou ordem de navegação. A nav do `/admin` é plana/default.
- **Admin-only sem `canAccess()`**: grep por `canAccess|shouldRegisterNavigation` em `app/Filament` →
  **zero**. O portão único é `app/Models/User.php:27-32` `canAccessPanel()` → `hasRole('administrador')`
  (comentário no código: *"É o ÚNICO portão do painel"*). Uma Page nova em `app/Filament/Pages` é
  **auto-descoberta** (`AdminPanelProvider.php` `discoverPages`) e **já nasce admin-only** — a matriz
  **não precisa** de `canAccess()` próprio.
- ⚠️ **Não há molde de "grade de checkboxes"**: **`CheckboxList` tem ZERO ocorrências** em `app/`;
  `Toggle::make` aparece 12× (flags booleanos avulsos, ex. `UserResource.php` `socio`,
  `PalestranteResource.php` `mostrar_email`); `Repeater::make` 3×. **A grade papel×capacidade é
  construção nova** sobre `Grid` (`Filament\Schemas\Components\Grid`, ver `EventoForm.php:18`) + `Toggle`
  (`Filament\Forms\Components\Toggle`). ⚠️ **Namespaces divergem por tipo**: `Grid` está em
  `Filament\Schemas\Components\*`, `Toggle`/`Checkbox` em `Filament\Forms\Components\*` — errar o import
  quebra a montagem.
- **Teste de página**: **NÃO existe `ConfiguracoesBlogTest`** (refuta o item 9 do kickoff). O molde de
  teste está em `tests/Feature/Filament/ConfiguracoesAgendaTest.php` (`Livewire::test(Page::class)
  ->fillForm([...])->call('salvar')->assertHasNoFormErrors()` `:36-39`; render por rota `:27`;
  `$this->actingAsAdmin()` no `setUp` `:22`) e o teste de "salvar + asserir efeito" do Blog vive
  **dentro** de `tests/Feature/Filament/PostResourceTest.php:170-178`
  (`fillForm(...)->call('salvar')` + `assertSame(..., Configuracao::valor(...))`).

### 2.2 Peça 1 — spatie: papéis, `role_has_permissions`, `syncPermissions`, cache

- `register_permission_check_method => false` (`config/permission.php:108`, decisão 3.0 da Fase A) —
  confirma que o **único** `Gate::before` é o do admin (`AppServiceProvider.php:59`).
- Tabelas **padrão**: `roles`, `permissions`, `role_has_permissions`, `model_has_roles`,
  `model_has_permissions` (`config/permission.php:43-75`). **teams OFF** (`:138`), **wildcard OFF**
  (`:173`) ⇒ `role_has_permissions` é pivô simples `(permission_id, role_id)`, sem `team_id`.
- **Cache**: `key => 'spatie.permission.cache'` (`:196`), `store => 'default'` (`:204`), TTL 24h (`:190`).
  ⚠️ **A chave é global** — salvar 1 papel invalida o cache de **todos**. Não modelar "cache por papel".
- **4 papéis fixos** semeados por `EstruturaCemaSeeder.php:20-25` (`Role::updateOrCreate(['name'=>$slug,
  'guard_name'=>'web'], ['nivel'=>$nivel])`) a partir de `GlossarioUsuarios::PAPEIS`
  (`app/Importacao/GlossarioUsuarios.php:10-15`): `frequentador`=10, `trabalhador`=20, `diretor`=30,
  `administrador`=100.
- ⚠️ **`nivel` é coluna custom** de `roles` (`migration 2026_07_03_105901_add_nivel_to_roles_table.php:14`,
  default 0). Se a matriz usar `Role::create/updateOrCreate` sem `nivel`, **zera o nível do papel**. A
  matriz **só** pode `Role::findByName($slug,'web')->syncPermissions([...])` — **nunca** recriar o papel.
- **Estado atual — `role_has_permissions` vazia**: grep por `givePermissionTo|syncPermissions|
  ->permissions()` em `app/`+`database/` só acha o schema do pivô; `CapacidadesSeeder.php:12-13`
  declara *"NÃO atribui a papéis: a matriz papel→permissão é a Fase C"*; a única atribuição existente é de
  **papel a usuário** (`AdminSeeder.php:29` `syncRoles(['administrador'])`). ⇒ **A matriz é o 1º e único
  escritor de `role_has_permissions`.** Não há padrão de `syncPermissions`/`forgetCachedPermissions` a
  copiar no repo.
- **Ler o estado atual (pré-marca)**: `$role->permissions()->pluck('name')->all()` — query fresca (evita
  relação stale). API confirmada em `vendor/.../HasPermissions.php:80-98,488-491`.
- **Salvar**: `Role::syncPermissions(...$permissions)` (variadic; `HasPermissions.php:450-459`) — aceita
  **array de nomes string**, faz `detach()` + `givePermissionTo`. Array **vazio** = detach total (correto
  para papel sem nenhuma caixa marcada). ⚠️ Lança `PermissionDoesNotExist` se um nome não existir no
  catálogo ⇒ depende de `CapacidadesSeeder` aplicado. ⚠️ Guard: como tudo é `web`, ok; se a página
  resolver num guard diferente, `GuardDoesNotMatch`.
- ✅ **`syncPermissions` sobre um `Role` JÁ limpa o cache do spatie automaticamente**
  (`HasPermissions.php:424-426`: `if (is_a($this, Role::class)) { $this->forgetCachedPermissions(); }`,
  chamado por `givePermissionTo`). Logo um `forgetCachedPermissions()` extra é **redundante** (inofensivo)
  — só obrigatório se mutar o pivô por SQL cru. O manual existe:
  `app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions()`
  (`PermissionRegistrar.php:140-146`), equivalente a `artisan permission:cache-reset`.
- `Role::findByName('trabalhador','web')` (`Models/Role.php:106-117`) — lança `RoleDoesNotExist` se
  ausente; papéis fixos já existem via seeder.

### 2.3 Peça 1 — GlossarioCapacidades (as linhas da grade)

- `app/Support/Autorizacao/GlossarioCapacidades.php`: `RECURSOS = ['evento','palestra','post','agenda',
  'palestrante']` (`:13`), `ACOES = ['ver','criar','editar','excluir']` (`:15`), `permissions()` = produto
  cartesiano (`:18-28`) ⇒ **20 nomes** `recurso.acao`. Docblock já diz **"20"** (`:17`); Biblioteca fora
  (`:9`). O **único consumidor** é `CapacidadesSeeder` (que vive em `database/seeders/`, **não** em
  `app/` — um grep restrito a `app/` daria zero). A matriz será o **2º consumidor** e o 1º em `app/`.
- ⚠️ **Não há rótulos legíveis** — só strings cruas. `Glob app/Support/Autorizacao/**` → só o glossário;
  nenhum enum/lang/const de rótulo. Sem um mapa novo, a grade exibiria literalmente `palestra.editar`.
  Dois casos **slug ≠ nome do model**: recurso `agenda` → model **`AgendaDia`** (rótulo "Agenda do Dia");
  `palestrante` → model `Palestrante`.
- **Agrupar por recurso** (5 grupos × 4 ações = 20 linhas) é derivável direto de `RECURSOS`×`ACOES`, sem
  lista manual. Usar `"{$recurso}.{$acao}"` como a chave da permission (mesmo formato do seeder).

### 2.4 Peça 2 — UserResource (usuário → departamento)

- ⚠️ **Path real**: `app/Filament/Resources/Users/UserResource.php` (subdir `Users/`). O kickoff cita
  `UserResource.php:95-105` sem o prefixo — os **números batem**, o **path não** (PARCIAL).
- **Molde exato** (`:95-99` setores, `:101-105` cargos): `Select::make('setores')->label('Setores')
  ->relationship('setores','nome')->multiple()->preload()`. ⚠️ **Sem** `->searchable()`, **sem**
  `->columnSpanFull()`. O `Select` de `roles` (`:87-93`) usa `->maxItems(1)->required()` — **não** copiar
  isso para departamentos (é multi e opcional, como setores/cargos).
- **Ponto de inserção**: dentro do `->schema([])` da `Section::make('Papel e estrutura')->columns(2)`
  (`:82-106`), **após** o `Select` de cargos (`:105`). Form é **inline** no Resource (`form(Schema
  $schema)` `:46-129`); `EditUser`/`CreateUser` **não** sobrescrevem ⇒ **um único** ponto de edição.
- `User::departamentos()` = `belongsToMany(Departamento::class, 'departamento_usuario')`
  (`app/Models/User.php:55-58`) — Fase A. ⚠️ **Sem** `->withTimestamps()`/`->withPivot()` (o pivô
  `departamento_usuario` não tem timestamps — `migration 2026_07_11_000001`). **Não** adicionar
  `withTimestamps` (o sync do Filament quebraria com coluna inexistente). O `Select`
  `->relationship('departamentos','nome')->multiple()->preload()` funciona (só grava
  `user_id`+`departamento_id`; `unique(['user_id','departamento_id'])` impede duplicação).
- `Departamento` tem `nome` (`migration 2026_07_03_000002:14`; `Departamento.php:13`) — válido como
  `titleAttribute`. Tem coluna `ativo` (`:17`) — o molde setores/cargos **não** filtra por ativo (fork §14).
- O comando **`cema:vincular-diretores-departamento`** (Fase B,
  `app/Console/Commands/VincularDiretoresDepartamento.php`) segue válido como **bootstrap** do vínculo; a
  Peça 2 é a via **manual** (as duas convergem no mesmo pivô via `sync`).

### 2.5 Peça 3 — Forms dos 4 conteúdos (conteúdo → departamento)

- **Molde funcional** = `Evento` (que já ganhou departamento na Fase B): `app/Filament/Schemas/
  EventoForm.php:107-113` — `Select::make('departamentos')->label('Departamentos organizadores')
  ->relationship('departamentos','nome')->multiple()->searchable()->preload()`. ⚠️ **Refuta o kickoff**:
  **tem** `->searchable()` (não citado), **não tem** `->required()` nem `->columnSpanFull()`. (`EventoForm`
  é o **único** arquivo Schema/Form separado do projeto; os 4 conteúdos usam `form(Schema $schema)` inline
  no Resource.)
- **Ponto de inserção por conteúdo** (todos inline no Resource; nenhum tem campo de departamento hoje):

| Conteúdo | Arquivo | Estrutura do form | Onde inserir |
|----------|---------|-------------------|--------------|
| `Palestra` | `PalestraResource.php:47` | Tabs | Tab **"Assuntos e destaques"** (`:151`), ao lado do `Select 'assuntos'` (`:152-157`, molde idêntico ao Evento) |
| `Post` | `PostResource.php:53` | Tabs | Tab **"Taxonomia e Publicação"** (`:197`), junto de `categorias`/`tags` (`:199-228`) |
| `AgendaDia` | `AgendaDiaResource.php:38` | **FLAT** (`Grid` + `RichEditor`s, sem Tab/Section) | `Select` ao final, **`->columnSpanFull()`** (exceção ao molde — cabe no layout flat) |
| `Palestrante` | `PalestranteResource.php:44` | Sections (não Tabs) | nova `Section::make('Departamentos')` após "Contato e exibição" (`:106-123`) |

- Os 4 models **implementam `TemDepartamento`** com `departamentos()` `belongsToMany` (Fase B):
  `Palestra.php:16,69-72` (`departamento_palestra`); `Post.php:23,205-208` (`departamento_post`);
  `AgendaDia.php:16,47-50` (`departamento_agenda_dia`); `Palestrante.php:18,55-58`
  (`departamento_palestrante`) — todas casam com `->relationship('departamentos','nome')`.
- ⚠️ **`Palestra` tem save customizado** (`trait SincronizaPessoas`, `CreatePalestra.php:10`) que mexe
  **só** em `ids_palestrantes`/`id_diretor` (cardinalidade do pivô `palestra_pessoa.papel`). O `Select` de
  `departamentos` é `relationship` multiple **padrão** — salva sozinho via `sync` do Filament (como
  `assuntos`, que já convive no mesmo form). **Não** tocar o trait, `$fillable`, nem as Pages.
- ⚠️ **`Evento` NÃO entra na Peça 3** — já tem o `Select` (Fase B, `EventoForm`). Embora `evento` seja
  linha da matriz (Peça 1), o form de Evento **não** é alterado. **Não duplicar.**
- Todos os 4 resources são **admin-only** por natureza (`/admin`) — inserir o `Select` ali já é admin-only.

### 2.6 Fronteiras (o que a matriz LIGA × o que NÃO toca)

- `Gate::before(fn (User $u) => $u->hasRole('administrador') ? true : null)` (`AppServiceProvider.php:59`)
  — admin ⇒ `true` (onipotente, **fora da grade**); demais ⇒ `null` (cai nas policies).
- As **5 policies** (`Evento`/`Palestra`/`Post`/`AgendaDia`/`Palestrante`) usam **`hasPermissionTo`**
  (nunca `can()`) + trait (ex. `EventoPolicy.php:46`, `PalestraPolicy.php:32`, `PostPolicy.php:31`,
  `AgendaDiaPolicy.php:31`, `PalestrantePolicy.php:33`). São o **consumidor** do que a matriz liga —
  **fora de escopo (não tocar)**, mas atribuir a permission ao papel faz a policy morder **direto** (a
  herança papel→permissão do spatie alimenta `hasPermissionTo`).
- `AutorizaPorDepartamento.php:16-27`: `editar` exige permissão **E** interseção de departamento
  (`whereIn(...)->exists()`); **fail-closed nos dois lados** (usuário sem depto **ou** objeto sem depto ⇒
  `false`). `criar` usa `departamentos()->exists()` (objectless).
- ⚠️ **Frequentador NÃO é curto-circuitado** por `Gate::before` (só `administrador` é). Se a matriz
  atribuir permission a `frequentador`, ele ganharia a capacidade **real**. A exclusão de
  admin/frequentador da grade é **100% decisão de UI** — o `salvar()` deve tocar **só** `trabalhador` e
  `diretor` e **jamais** os outros dois.
- **`register_permission_check_method` OFF** ⇒ o nome cru `evento.editar` **não** é ability de Gate:
  `Gate::allows('evento.editar')` = `false` **mesmo com** a permission (provado em
  `GateFundacaoTest.php:35-45`). A matriz **só ESCREVE** via `syncPermissions`; a leitura de capacidade é
  `hasPermissionTo` nas policies. **Não** checar capacidade por `Gate::allows('recurso.acao')`.

### 2.7 Padrão de testes

- **Página**: `Livewire::test(Page::class)->fillForm([...])->call('salvar')->assertHasNoFormErrors()`
  (`ConfiguracoesAgendaTest.php:36-39`) + render por rota (`$this->get('/admin/<slug>')->assertOk()`) +
  `actingAsAdmin()` no `setUp`. "Salvar + asserir efeito": `PostResourceTest.php:170-178`.
  ⚠️ Página usa `->call('salvar')` (pt-BR); Resource usa `->call('create')`/`->call('save')`.
- **Policy de capacidade** (A/B): `EventoPolicyCapacidadeTest.php` / `CapacidadeConteudosTest.php`. `setUp`
  = `Role::findOrCreate('administrador','web')` + `seed(CapacidadesSeeder)`; usuário fabricado com
  `givePermissionTo` + `departamentos()->sync`; checagem `Gate::forUser($u)->check('editar', $obj)`.
  ⚠️ Esses testes atribuem capacidade **direto ao usuário** (`givePermissionTo`), **sem papel** — a Fase C
  precisa do caminho **por PAPEL** (`assignRole` após a matriz sincronizar). Nenhum teste em
  `tests/Feature/Autorizacao` usa `assignRole('trabalhador')` hoje.
- ⚠️ **Nome cru NEGA, ability PERMITE** (`CapacidadeConteudosTest.php:169-170`): todo teste de capacidade
  checa a **ability** (`check('editar', $obj)`), nunca `->allows('post.editar', $obj)`.
- **Resource-tests** (guarda de regressão do `/admin`): existem para os 4 conteúdos + Evento
  (`{Palestra,Post,AgendaDia,Palestrante,Evento}ResourceTest`), padrão `actingAsAdmin` +
  `Livewire::test(CreateX)->fillForm(...)->call('create')`. **`EventoResourceTest.php:33-53` já exercita
  `fillForm(['departamentos'=>[$dep->id]])` + assert** — molde 1:1 para os 4 conteúdos.
- **UserResource** tem teste real em `tests/Feature/Usuarios/UsuarioResourceTest.php` (nome/dir pt-BR,
  namespace do Resource `Users\Pages\CreateUser`) — já testa `Select` multiple relationship (`roles`).
- ⚠️ **`diretor`/`frequentador` recebem `assertForbidden()` em `/admin`** (`GatePainelTest.php:23-39`).
  O teste "trabalhador/diretor ganha capacidade" **não pode logar esse usuário no `/admin`** — prova via
  `Gate::forUser($u)->check('editar',$obj)` (a capacidade é consumida em `/minha-conta`, Fase D).
- **Factories**: `User`/`Palestra`/`Post`/`AgendaDia`/`Palestrante`. ⚠️ **Não há `DepartamentoFactory`** —
  `Departamento::create(['sigla'=>,'nome'=>,'slug'=>])`. Siglas reais: DAS, DDA, DED, DEMAPA, DEPAE, DEPRO,
  DIJ, DECOM.

### 2.8 Nenhuma migration nesta fase

Todo o schema já existe (A/B): `role_has_permissions` (Fase A, spatie), `departamento_usuario` (Fase A),
os 4 pivôs `departamento_<conteudo>` (Fase B). A Peça 1 escreve `role_has_permissions` **em runtime**; as
Peças 2/3 usam pivôs já criados. **A Fase C não tem mudança de schema** — 0 migrations.

## 3. Decisões travadas (do enquadramento) e cravadas por verificação

Do kickoff (dono + consultor, 12/jul). Ordem espelha o enquadramento; a verificação refina onde o código
diverge.

1. **Matriz 2D — papel × capacidade**, no spatie (`role_has_permissions`). O **departamento NÃO entra na
   matriz** — continua sendo o vínculo do usuário (filtro de objeto A/B). **NÃO tocar**
   policies/trait/pivô/contrato.
2. **Editores transversais por DADOS, sem código de autorização novo**:
   - **presidente edita tudo** = papel `diretor` + vínculo aos **8** departamentos (Peça 2);
   - **DECOM edita palestras** = a palestra ganha DECOM como **2º** departamento no N:N (Peça 3).
3. **Escopo = 3 peças**: (1) matriz papel→capacidade; (2) atribuir depto ao **usuário**; (3) atribuir
   depto ao **conteúdo**.
4. **UI da matriz** = grade única (papel × capacidade), **Página Filament dedicada**.
5. **Auditoria da matriz** = **espera a fase de auditoria** (activitylog, antes da Fase D). A matriz é
   candidata #1 a auditar — **ciência, não implementar** (§12).
6. **Papéis FIXOS**: a tela só liga/desliga capacidades, **não** cria/apaga papel. Admin **fora** da grade
   (onipotente via `Gate::before`); frequentador **fora** (não edita). Colunas = `trabalhador` e `diretor`.
7. **Ao salvar, limpar o cache do spatie** — cravado no kickoff.

**Decisões cravadas por verificação (o enquadramento não previu, ou o código exige/diverge):**

- **(a) `salvar()`, não `save()`** — o molde v5 (`ConfiguracoesBlog`) usa `salvar()` amarrado por
  `livewireSubmitHandler('salvar')` **e** `Action->submit('salvar')`; a Blade usa `{{ $this->content }}`.
  Seguir o molde **ipsis litteris** (§2.1). Refuta o item 8 do kickoff.
- **(b) `forgetCachedPermissions()` explícito é REDUNDANTE** — `Role::syncPermissions` já limpa o cache
  (`HasPermissions.php:424-426`). O kickoff pede "limpar cache"; o código **já cumpre** ao usar
  `syncPermissions`. Mantê-lo explícito é opcional/cinturão (§14).
- **(c) Grade é construção nova** — não há `CheckboxList` no projeto; a matriz será `Grid` + `Toggle` por
  célula (§2.1, §4). O molde ensina o **esqueleto** (declarar/pré-carregar/persistir), não a grade.
- **(d) Rótulos legíveis e lista de colunas NÃO existem** — precisam ser **criados** nesta fase (§8): o
  glossário só tem strings cruas e `GlossarioUsuarios::PAPEIS` não tem flag "editável".
- **(e) `Evento` fora da Peça 3** — já tem o `Select` (Fase B). Refina o kickoff ("os 4 conteúdos").
- **(f) `salvar()` sincroniza SÓ `trabalhador`/`diretor`** — frequentador não é curto-circuitado (§2.6).
- **(g) Assinatura do `Select` de conteúdo** = molde Evento `multiple()->searchable()->preload()` **+
  `->required()`** (decisão do dono no passe, F5 — ver §3.1); `->columnSpanFull()` só em `AgendaDia` (layout
  flat). O `->searchable()` vem do molde Evento (o kickoff não citava); o `->required()` **diverge** do
  `Evento` de propósito (o `Evento` permanece opcional — fora de escopo).

### 3.1 Decisões do passe adversarial (12/jul) — forks do §14 resolvidos

O passe **aprovou** o SPEC (✅, sem bloqueador) com **1 obrigatório** e endossos; os forks do §14 estão
**todos resolvidos** (rastreio no §14):

- **F5 — departamento no conteúdo é OBRIGATÓRIO (`->required()`) nos 4 forms** (decisão do dono). Alinha
  com o critério "quem mantém" do backfill da Fase B e garante que a delegação a diretores **morde**: todo
  conteúdo passa a ter ≥1 departamento, eliminando o buraco fail-closed "conteúdo sem depto = só admin".
- **O1 (obrigatório — consequência do `required`)** — o `required` **quebra os create-tests existentes** dos
  4 resources, que hoje criam conteúdo **sem** departamento. É preciso **atualizar TODOS** esses create-tests
  para incluir `'departamentos' => [$dep->id]` no `fillForm`, **não só** adicionar o método de regressão
  novo (§10.9, §13).
- **Endossados** (seguir o SPEC como está): **F1** (molde `salvar()`/`content()`/`EmbeddedSchema`/
  `livewireSubmitHandler`/`Action->submit` ipsis litteris), **F2** (statePath aninhado
  `data.<papel>.<recurso>.<acao>`, dot-notation no `Toggle`; o `fillForm` do teste espelha a árvore),
  **F3** (grade `Grid`+`Toggle`; atenção aos namespaces `Grid` em `Schemas\Components`, `Toggle` em
  `Forms\Components`), **F3'** (rótulos em `GlossarioCapacidades`), **F4** (`PAPEIS_EDITAVEIS` em
  `GlossarioUsuarios`), **F6** (`searchable`), **F6'** (não filtrar por `ativo`), **F7** (sem
  `navigationGroup`), **F8** (cobertura mínima de teste), **F9** (presidente = atribuição manual pela Peça 2),
  **F10** — **não** adicionar `forgetCachedPermissions`: `syncPermissions` sobre `Role` já limpa o cache,
  **inclusive com array vazio** (o caminho do `Role` existente não tem early-return antes do forget).

## 4. Peça 1 — Matriz papel×capacidade (a Página Filament)

**Artefato**: `app/Filament/Pages/MatrizCapacidades.php` (nome proposto; slug `matriz-capacidades`, title
"Matriz de capacidades") + view `resources/views/filament/pages/matriz-capacidades.blade.php`. Molde
estrutural = **`ConfiguracoesBlog`** (§2.1).

**Linhas** = as **20 capacidades**, agrupadas por **recurso** (5 grupos × 4 ações), derivadas de
`GlossarioCapacidades::RECURSOS`×`ACOES` (§2.3). **Colunas** = os **2 papéis editáveis** `['trabalhador',
'diretor']` (§8). **Célula** = um `Toggle` booleano.

**Estado (statePath das células)** — proposta a cravar (§14 F2): estado aninhado
`data.<papel>.<recurso>.<acao> => bool`, um `Toggle` por par (20×2 = **40 toggles**). O `Toggle` de cada
célula é `Toggle::make("{$papel}.{$recurso}.{$acao}")` sob `->statePath('data')` (mesmo esqueleto do Blog).
Assim o `fillForm` do teste espelha exatamente essa árvore (§10).

**Montagem** (`form(Schema $schema): Schema`): iterar `RECURSOS` → um agrupador (Grid/Section rotulado com
o **rótulo do recurso**, §8) → para cada `ACAO`, uma linha com um `Toggle` por papel (rótulo da ação +
rótulo do papel). Namespaces: `Grid` de `Filament\Schemas\Components\*`, `Toggle` de
`Filament\Forms\Components\*` (§2.1). O botão Salvar via `content()`+`EmbeddedSchema`+`Action->submit
('salvar')` **replicado do molde** (§2.1, gotcha (a)).

**Abrir (`mount()` → pré-marca)**: para cada papel, ler uma vez `Role::findByName($papel,'web')
->permissions()->pluck('name')->all()`; montar `$dados[$papel][$recurso][$acao] = in_array("$recurso.$acao",
$nomes, true)`; `$this->form->fill($dados)`. (Query fresca, sem N consultas por célula — §2.2.)

**Salvar (`salvar()`)**:
```
$estado = $this->form->getState();               // data.<papel>.<recurso>.<acao> => bool
foreach (['trabalhador', 'diretor'] as $papel) {  // SÓ estes dois — nunca admin/frequentador
    $marcados = [];                               // nomes "recurso.acao" com toggle = true
    foreach (GlossarioCapacidades::RECURSOS as $recurso) {
        foreach (GlossarioCapacidades::ACOES as $acao) {
            if (data_get($estado, "{$papel}.{$recurso}.{$acao}")) {
                $marcados[] = "{$recurso}.{$acao}";
            }
        }
    }
    Role::findByName($papel, 'web')->syncPermissions($marcados);   // detach + attach; array vazio = zera
}
// syncPermissions já limpa o cache do spatie (redundante repetir); Notification de sucesso.
```

⚠️ Gotchas (§2.1/§2.2): `salvar()` (não `save`); `Role::findByName` + `syncPermissions` (nunca
`create/updateOrCreate` — zeraria `nivel`); guard `'web'` explícito; depende de `CapacidadesSeeder` (senão
`PermissionDoesNotExist`); tocar **só** os 2 papéis.

**Navegação/acesso**: `navigationIcon`/`navigationLabel`/`title`/`slug`, **sem** `navigationGroup`
(status quo — §14 F7). **Sem `canAccess()`** (admin-only pelo portão do painel — §2.1). Rota:
`/admin/matriz-capacidades`.

## 5. Peça 2 — Usuário → departamento (UserResource)

**Alterado**: `app/Filament/Resources/Users/UserResource.php` — inserir, **dentro** da `Section
'Papel e estrutura'` (`:82-106`), após o `Select` de cargos (`:105`), o molde **1:1** de setores/cargos:

```php
Select::make('departamentos')
    ->label('Departamentos')
    ->relationship('departamentos', 'nome')
    ->multiple()
    ->preload(),
```

⚠️ Sem `->searchable()`/`->columnSpanFull()` (1:1 com setores/cargos); sem `->maxItems`/`->required`
(multi e opcional); **não** adicionar `withTimestamps` na relação (§2.4). É onde o admin dá ao **presidente**
o papel `diretor` (já via `Select 'roles'`) + o vínculo aos **8** departamentos. O comando
`cema:vincular-diretores-departamento` (Fase B) permanece como bootstrap.

## 6. Peça 3 — Conteúdo → departamento (forms dos 4 conteúdos)

**Alterados**: `PalestraResource.php`, `PostResource.php`, `AgendaDiaResource.php`,
`PalestranteResource.php` — inserir o `Select` de `departamentos` no ponto de cada um (tabela §2.5),
padronizado no **molde do Evento** (§14 F6):

```php
Select::make('departamentos')
    ->label('Departamentos')
    ->relationship('departamentos', 'nome')
    ->multiple()
    ->searchable()
    ->preload()
    ->required(),
    // AgendaDia (form flat): acrescentar ->columnSpanFull()
```

⚠️ (§2.5): salva sozinho via `sync` do Filament (como `assuntos`/`categorias`); **não** tocar
`$fillable`/Pages/`trait SincronizaPessoas` (Palestra); **não** alterar `Evento` (já tem). É onde a
**palestra ganha o DECOM** como 2º departamento (caso DECOM).

⚠️ **`->required()` (decisão do dono, F5 §3.1)** — todo conteúdo passa a exigir ≥1 departamento no `/admin`,
garantindo que a delegação a diretores morde. **Consequência O1**: os create-tests existentes dos 4
resources criam conteúdo **sem** departamento e **quebram** com o `required` — a Fase C **atualiza todos**
eles (§10.9, §13), não só adiciona o método de regressão.

## 7. Fronteiras: o que a matriz LIGA × o que NÃO toca (as 3 condições de edição)

A matriz é **uma** das três condições que a policy exige para um não-admin **editar** um objeto (§2.6):

| Condição | Origem | Peça |
|----------|--------|------|
| **permissão** (`hasPermissionTo('recurso.acao')`) | papel → permissão (`role_has_permissions`) | **1 — matriz** |
| **vínculo do usuário** a um departamento | `departamento_usuario` | **2 — usuário→depto** |
| **objeto pertence** a um departamento em comum | `departamento_<conteudo>` | **3 — conteúdo→depto** |

Fail-closed: faltando qualquer uma, o não-admin é **negado** (só o admin passa, antes, no `Gate::before`).
Por isso as 3 peças andam juntas: a matriz sozinha **não** habilita edição.

- **Caso presidente** (decisão 2): papel `diretor` (com as capacidades ligadas na matriz) + vínculo aos
  **8** departamentos (Peça 2) ⇒ edita conteúdo de **qualquer** departamento (a interseção sempre acha
  um). ⚠️ Nuance (§14 F9): "Presidente" hoje é **cargo institucional** (`diretor_presidente`, depto `null`)
  — o backfill da B **não** o alcança; o vínculo aos 8 é **atribuição manual** pela Peça 2.
- **Caso DECOM** (decisão 2): palestra com **DED+DECOM** (Peça 3) + diretor do DECOM (papel `diretor` +
  capacidade de palestra na matriz + vínculo ao DECOM) ⇒ edita **essa** palestra por interseção; é negado
  em palestra de outro departamento (caso disjunto).

**NÃO se toca**: `Gate::before`, `config/permission.php`, as 5 policies, o trait, o contrato, os pivôs,
`EventoForm`, `CapacidadesSeeder`, `EstruturaCemaSeeder`. **Não** criar/apagar papéis.

## 8. Duas fontes novas: rótulos legíveis e papéis-coluna

A grade precisa de dois dados que **não existem** hoje (§2.3, §2.2):

1. **Rótulos legíveis** (recurso→rótulo, ação→rótulo). Proposta (§14 F3): estender `GlossarioCapacidades`
   com `const RECURSOS_ROTULOS` e `const ACOES_ROTULOS` (fonte única, testável, reaproveitável por
   auditoria/`minha-conta`), com **fallback** `ucfirst(slug)` para chave ausente. Cobrir explicitamente
   `agenda` → "Agenda do Dia" e `palestrante` → "Palestrante" (slug ≠ model). É a **primeira** ocupação de
   rótulos — confirmar local no §14.
2. **Papéis-coluna** `['trabalhador','diretor']`. Proposta (§14 F4): `const PAPEIS_EDITAVEIS` em
   `GlossarioUsuarios` (fonte única) — **não** derivar por faixa de nível (frágil). Alternativa: hardcodar
   os 2 slugs na Página.

## 9. Ordem de execução

Nos **testes** a ordem é irrelevante (cada caso fabrica seu estado). Em **dev/deploy**:

0. **Pré-requisito** (já satisfeito na `main`): `CapacidadesSeeder` (20 permissions) e `EstruturaCemaSeeder`
   (4 papéis) aplicados — senão `syncPermissions` lança `PermissionDoesNotExist`.
1. Peça 1 (matriz) — a página escreve `role_has_permissions` em runtime. Sem passo de dados prévio.
2. Peças 2/3 (Selects) — usam pivôs existentes; o admin atribui os vínculos pela tela (o
   `cema:vincular-diretores-departamento` já bootstrapou os diretores).

Não há migration, seed novo, nem comando novo nesta fase.

## 10. O que o spec deve provar (testes desta fase)

**Peça 1 — matriz** (E2E de página, molde `ConfiguracoesAgendaTest` + assert de efeito do
`PostResourceTest:170-178`; `setUp` `actingAsAdmin` + `seed(EstruturaCemaSeeder)` + `seed(CapacidadesSeeder)`):

1. **Render** — `$this->get('/admin/matriz-capacidades')->assertOk()`.
2. **Salvar atribui/remove + limpa cache** — `Livewire::test(MatrizCapacidades::class)
   ->fillForm([...marcar `diretor.palestra.editar`...])->call('salvar')->assertHasNoFormErrors()`; depois
   `assertTrue(Role::findByName('diretor','web')->hasPermissionTo('palestra.editar'))`. Desmarcar e salvar
   de novo ⇒ `assertFalse(...)` (prova o detach de `syncPermissions`; o cache reflete na mesma request —
   `syncPermissions` já limpou).
3. **Pré-marca (abrir reflete o estado)** — com `diretor` já tendo `post.criar`, abrir a página e asserir
   que a célula correspondente vem **marcada** no estado (`data.diretor.post.criar === true`).
4. **Isolamento de papéis** — salvar a matriz **não** atribui nada a `administrador` nem `frequentador`
   (`assertSame(0, Role::findByName('frequentador','web')->permissions()->count())`), mesmo marcando tudo
   nas 2 colunas.

**Ligação papel→policy** (via **PAPEL**, não `givePermissionTo` direto — §2.7; prova por
`Gate::forUser($u)->check(...)`, **nunca** logando no `/admin`):

5. **Usuário do papel ganha/perde a capacidade** — `Role::findByName('diretor','web')->syncPermissions
   (['palestra.editar'])`; `$u->assignRole('diretor')`; `$u->departamentos()->sync([$decom->id])`;
   `$palestra->departamentos()->sync([$decom->id])` ⇒ `assertTrue(Gate::forUser($u)->check('editar',
   $palestra))`. Remover a permission do papel ⇒ `assertFalse(...)`.
6. **Caso presidente** — papel `diretor` + as capacidades na matriz + vínculo aos **8** departamentos ⇒
   edita conteúdo de **qualquer** departamento (asserir em ≥2 departamentos distintos).
7. **Caso DECOM (interseção com 2 departamentos — lacuna sem cobertura hoje, §12)** — palestra em
   **[DED, DECOM]**; diretor com `palestra.editar` (matriz) vinculado **só ao DECOM** ⇒ `assertTrue(check
   ('editar', $palestra))`; e **caso disjunto**: palestra só em DED, diretor só no DECOM ⇒ `assertFalse`.

**Peças 2/3 — regressão do `/admin`** (molde `EventoResourceTest:33-53`):

8. **UserResource salva departamentos** — `Livewire::test(CreateUser)->fillForm([... 'departamentos' =>
   [$dep->id] ...])->call('create')` + `assertTrue($user->departamentos->contains($dep))`
   (`tests/Feature/Usuarios/UsuarioResourceTest.php`).
9. **Cada conteúdo salva departamentos + create-tests atualizados (O1)** — o `->required()` (F5) **quebra**
   os create-tests existentes dos 4 resources, que hoje criam conteúdo **sem** departamento (`departamento`
   aparece 0× neles; ex.: `PalestraResourceTest` tem 8 creates). Ação **obrigatória**:
   - **atualizar TODOS** os create-tests dos 4 resources para incluir `'departamentos' => [$dep->id]` no
     `fillForm` — os de **sucesso** (`test_cria_*`, `test_aceita_cor_fundo_hex_valido`,
     `test_cria_*_com_slide_*`…) falhariam por departamento faltando; os de **rejeição**
     (`test_rejeita_zero_palestrantes`, `_cor_invalido`) passariam pelo **motivo errado** (rejeição por
     departamento) = **falso-positivo** que mascara a regra sob teste;
   - **adicionar 1 método de regressão** por resource que prova o vínculo:
     `fillForm(['departamentos'=>[$dep->id]])->call('create')` + `assertTrue($obj->departamentos->
     contains($dep))` (molde `EventoResourceTest:33-53`).
10. **Suíte inteira + Pint** verdes no container (ciência `flaky-importadorblog-gd-cap-imagem`: 2 testes de
    cap de imagem do blog podem falhar sob carga; se passam isolados, não é regressão desta fase).

⚠️ Todos os `fillForm` de página miram `data.*` (§2.7); a grade exige statePath estável (§4, §14 F2) —
cravar o contrato **antes** de escrever o teste 2/3.

## 11. Fora de escopo (não fazer nesta fase)

- **Policies / trait `AutorizaPorDepartamento` / pivôs / contrato `TemDepartamento`** (A/B) — **não tocar**.
- **`/minha-conta`** e os forms de edição embutidos no site — **Fase D**.
- **Visibilidade pública** (`podeSerVistoPor`/`scopeVisiveisPara`/scopes de publicação) — intocada.
- **`Gate::before`, `config/permission.php`** — intocados. **Criar/apagar papéis** — os 4 são fixos.
- **Auditoria** (`spatie/laravel-activitylog`) — **fase própria** (antes da Fase D). A matriz é candidata
  #1 (§12), mas **nenhum código** de auditoria aqui.
- **`Evento` na Peça 3** — já tem o `Select` (Fase B). **Não duplicar.**
- **Escalonamento de privilégio** (filtrar visibilidade/estado no save) — requisito dos forms da Fase D.

## 12. Ciências (não são tarefa desta fase)

- **A matriz é candidata #1 a auditoria** (decisão 5): quando a fase de auditoria (activitylog) rodar,
  registrar quem alterou `role_has_permissions` e quando. Aqui é **ciência**, não implementação.
- **Caso DECOM (objeto com 2+ departamentos) não tinha cobertura** — nenhum teste A/B sincroniza >1
  departamento a um conteúdo (todos os call sites de `objeto()` passam 1 id). A interseção (`whereIn(...)
  ->exists()`) suporta N, mas nunca foi exercitada com N>1. O teste 7 (§10) fecha essa lacuna; se um dia a
  trait virar `first()`/igualdade, o DECOM quebraria sem alarme.
- **`forgetCachedPermissions` redundante** — `syncPermissions` sobre `Role` já limpa o cache
  (`HasPermissions.php:424-426`). A chave é **global** (salvar 1 papel invalida todos). Ciência para
  quando houver workers/fila que precisem de invalidação imediata fora do request.
- **`frequentador` não é curto-circuitado** — só `administrador` passa no `Gate::before`. A exclusão de
  admin/frequentador da grade é decisão de UI, sem trava no dado. Vigiar em qualquer evolução da matriz.
- **Verbos pt-BR × verbos do Filament** (herdada da Fase B): as policies têm `ver`/`criar`/`editar`/
  `excluir`; o Filament nativo consulta `viewAny`/`view`/`create`/`update`/`delete`. Hoje inerte
  (`/admin` admin-only). Se uma fase futura quiser o painel consultando a policy para não-admin, faltará
  mapear os verbos ingleses.

## 13. Artefatos

**Novos**
- `app/Filament/Pages/MatrizCapacidades.php` — a Página da matriz (molde `ConfiguracoesBlog`).
- `resources/views/filament/pages/matriz-capacidades.blade.php` — view (`{{ $this->content }}`).
- `tests/Feature/Filament/MatrizCapacidadesTest.php` — render + salvar/pré-marca/isolamento (§10.1-4).
- `tests/Feature/Autorizacao/CapacidadeViaPapelTest.php` — ligação papel→policy + presidente + DECOM
  (§10.5-7), sob `tests/Feature/Autorizacao/` (padrão A/B).

**Alterados**
- `app/Support/Autorizacao/GlossarioCapacidades.php` — `const RECURSOS_ROTULOS`/`ACOES_ROTULOS` + fallback
  (§8, §14 F3).
- `app/Importacao/GlossarioUsuarios.php` — `const PAPEIS_EDITAVEIS` (§8, §14 F4) — **se** for a fonte única.
- `app/Filament/Resources/Users/UserResource.php` — `Select` de `departamentos` (§5).
- `app/Filament/Resources/Palestras/PalestraResource.php`, `Posts/PostResource.php`,
  `Agenda/AgendaDiaResource.php`, `Palestrantes/PalestranteResource.php` — `Select` de `departamentos` (§6).
- `tests/Feature/Usuarios/UsuarioResourceTest.php` — método de regressão do `Select` (§10.8).
- `tests/Feature/Filament/{Palestra,Post,AgendaDia,Palestrante}ResourceTest.php` — **O1**: atualizar
  **todos** os create-tests para incluir `'departamentos'=>[$dep->id]` (o `->required()` os quebra) **+**
  adicionar 1 método de regressão do `Select` (§10.9). O `$dep` vem de `Departamento::create([...])`
  (sem factory) ou do `EstruturaCemaSeeder` conforme o `setUp` de cada teste.

**Não se toca**: `config/permission.php`, `app/Providers/AppServiceProvider.php`, as 5 policies,
`AutorizaPorDepartamento`, `TemDepartamento`, os pivôs, `EventoForm`, `CapacidadesSeeder`,
`EstruturaCemaSeeder`, `DatabaseSeeder`. **0 migrations** (§2.8).

**Regras de sempre** (CLAUDE.md): pt-BR em tudo; **nada destrutivo no dev** (nunca
`fresh`/`refresh`/`wipe`/`reset`/seed destrutivo); guard `web`; cabeçalho de autoria nos PHP novos;
`Pint` antes do push; `docker compose exec -T app php artisan test`; commits atômicos; branch nova de
`main` (ex.: `fase-c-matriz-capacidade`).

## 14. Pontos a confirmar no passe adversarial — RESOLVIDOS (passe de 12/jul)

> **Veredito: ✅ APROVADO**, sem bloqueador — **1 obrigatório** (**O1**, §3.1/§10.9) + endossos. Resolução:
> **F5 = `->required()`** (decisão do dono); **F10 = NÃO** adicionar `forget` (redundante, inclusive com
> array vazio); **F1/F2/F3/F3'/F4/F6/F6'/F7/F8/F9 = endossados** (seguir o SPEC). Os itens abaixo ficam como
> registro do que foi levantado; as decisões travadas estão na **§3.1**.

1. **`salvar()`, não `save()` + `{{ $this->content }}` (F1).** O molde v5 (`ConfiguracoesBlog`) usa
   `salvar()` amarrado por `livewireSubmitHandler`/`Action->submit` e a Blade renderiza `{{ $this->content
   }}`. Refuta o item 8 do kickoff. Confirmar seguir o molde ipsis litteris (não o clássico `{{ $this->form
   }}`/`save`).
2. **Contrato do statePath da grade (F2).** Proposta: `data.<papel>.<recurso>.<acao> => bool` (aninhado,
   40 `Toggle`), com o `fillForm` do teste espelhando a árvore. Alternativas: chave achatada
   `'<papel>.<recurso>.<acao>'` ou uma `CheckboxList` por papel (inédita no projeto). **Cravar antes do
   teste** (senão o `fillForm` pode não alcançar a célula e passar falsamente).
3. **Componente da grade (F3).** `Grid`+`Toggle` por célula (único caminho com precedente — não há
   `CheckboxList` no projeto) vs `CheckboxList` por coluna (mais enxuto, inédito) vs `Repeater`. Recomendo
   `Grid`+`Toggle`. **Registrar que é construção nova, sem molde de grade.**
4. **Onde nascem os rótulos legíveis (F3').** Estender `GlossarioCapacidades` com `RECURSOS_ROTULOS`/
   `ACOES_ROTULOS` (recomendado — fonte única) vs mapa local na Página vs `lang/pt_BR/capacidades.php`.
   Cobrir `agenda`→"Agenda do Dia", `palestrante`→"Palestrante". É a 1ª ocupação de rótulos.
5. **Fonte das 2 colunas (F4).** `const PAPEIS_EDITAVEIS` em `GlossarioUsuarios` (recomendado — fonte
   única) vs hardcodar na Página. Evitar derivar por faixa de nível (frágil).
6. **`Select` de departamento nos 4 conteúdos: `required` ou opcional? (F5 — decisão FUNCIONAL).** O molde
   Evento é **opcional**. Mas, pelo fail-closed, **conteúdo sem departamento fica editável só por admin** —
   o modelo de capacidade silenciosamente **não morde** para diretores. Opções: (a) opcional (1:1 Evento,
   documentar "sem depto = admin-only por design"); (b) `required`; (c) default para o departamento do
   autor. **Decisão do dono** — não decidir sozinho.
7. **`->searchable()` no `Select` dos 4 conteúdos (F6).** Molde Evento (`multiple+searchable+preload`) vs
   molde User setores/cargos (`multiple+preload`, sem searchable). Recomendo o do Evento (irmão temático;
   ~8 departamentos, searchable é barato). Baixo risco.
8. **Filtrar o `Select` de departamento por `ativo=true`? (F6').** O molde setores/cargos **não** filtra.
   Recomendo seguir 1:1 (sem filtro) nas Peças 2 e 3; filtrar só se o dono priorizar limpeza da lista.
9. **Nuance do caso presidente (F9).** "Presidente" é **cargo institucional** (`diretor_presidente`, depto
   `null`) — o backfill da B **não** o vincula. Para "editar tudo", precisa de **atribuição manual** pela
   Peça 2 (papel `diretor` + os 8 departamentos). Confirmar que a via é a tela (não um comando novo) e que
   o teste 6 (§10) prova essa combinação.
10. **`forgetCachedPermissions()` explícito (F10).** `syncPermissions` sobre `Role` já limpa o cache
    (`HasPermissions.php:424-426`), então o passo "limpar cache" do kickoff **já está cumprido**. Adicionar
    `forget` explícito é redundante/inofensivo. Confirmar **não** adicionar (ou adicionar só como cinturão).
11. **Nome/posição da Página (F7).** Nome `MatrizCapacidades` / slug `matriz-capacidades`; **sem**
    `navigationGroup` (status quo — nenhum resource/page do projeto agrupa hoje). Se agrupar
    ("Acesso"/"Configurações"), seria o **primeiro** `navigationGroup` do projeto — decisão explícita.
12. **Estratégia de teste da matriz (F8).** E2E da Página (`fillForm->salvar` + assert `role_has_permissions`
    + `Gate::forUser($diretorComVinculo)->check(...)`) **mais** o teste de interseção com 2 departamentos
    (caso DECOM) **e** o caminho por PAPEL (`assignRole`, não `givePermissionTo`, que é como A/B testam).
    Confirmar que essa é a cobertura mínima.
