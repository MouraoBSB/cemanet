# Spec — Fase D · Edição da Agenda no /minha-conta (piloto do "não-admin edita pelo site")

> Autoria: Thiago Mourão — https://github.com/MouraoBSB — 2026-07-13
> Enquadramento travado com o dono (dono + consultor) no kickoff da Fase D. Este spec **não**
> improvisa além das decisões travadas; **cada afirmação sobre o terreno foi verificada contra o
> código real** (evidência `arquivo:linha` no §2) e os pontos que o enquadramento não previu — ou
> em que o enquadramento **diverge do código** — estão no §15 para o **passe adversarial**.
> Destino: **SPEC** — vai ao passe adversarial **antes** de virar plano de implementação.
> Base: `origin/main` (HEAD `c316527`, PR #28 da Auditoria mesclado).
> Fundação: [SPEC — Fase C](2026-07-12-fase-c-matriz-papel-capacidade.md) (PR #27, matriz + departamento
> no conteúdo), [SPEC — Auditoria](2026-07-13-fase-auditoria-activitylog.md) (PR #28, trilha append-only) e
> o [Spike — Filament Form no site](../spikes/2026-07-10-filament-forms-no-site.md) (fundação do PR #24).

## 1. Contexto e objetivo

As Fases A/B/C montaram a **autorização server-side** e a deixaram **quase inerte**: as **20 permissions**
`recurso.acao` existem, a **matriz papel×capacidade** (`/admin/matriz-capacidades`) liga capacidade por
papel, o **departamento** é o vínculo do usuário (`departamento_usuario`) e o filtro de objeto das 5
policies (`hasPermissionTo` + interseção de departamento, **fail-closed**). A Auditoria (PR #28) já registra
quem mexe no núcleo de autorização. **Falta o consumidor**: uma superfície onde um **não-admin** de fato
edite conteúdo — hoje inexistente, porque o `/admin` é **admin-only** (`User::canAccessPanel` →
`hasRole('administrador')`, `app/Models/User.php:31-36`) e não há form de edição fora dele.

A **Fase D abre essa superfície** para **um** conteúdo — a **Agenda da Reforma Íntima** (`AgendaDia`) — em
`/minha-conta`, com um **Filament Form embutido** (fundação provada no spike, §2.9), respeitando
**capacidade + filtro de departamento**, com **auditoria** e **escalonamento server-side**. É o **PILOTO**:
a Fase E replica o padrão para Blog/Eventos/Palestras. A vertical é **completa**: **listar + criar + editar
+ excluir**.

A partir desta fase, a fundação server-side de A/B/C **"morde" pela primeira vez** para um não-admin: um
diretor/colaborador de **DED** ou **DECOM** (após as correções de dados do §9) passa a criar/editar/excluir
`AgendaDia` **do seu departamento** pelo site, sem nunca tocar o `/admin`.

> **Três eixos, um objeto.** A matriz diz *"o papel diretor pode editar agenda"* (capacidade); o vínculo
> diz *"este diretor cuida do DECOM"* (`departamento_usuario`); a policy exige **os dois** mais o objeto ter
> departamento em comum (`AgendaDiaPolicy` + trait, §2.4). A Fase D **não cria autorização nova** — **consome**
> a de A/B/C e a **reforça no servidor** (nada do POST é confiável: §7).

## 2. Terreno confirmado por leitura (não presumir diferente)

Verificado no código em 2026-07-13 (base `c316527`). Versões travadas em `composer.lock`:
`filament/filament` **v5.6.7**, `livewire/livewire` **v4.3.2**, `spatie/laravel-activitylog` e
`spatie/laravel-permission` (guard `web`).

### 2.1 O modelo `AgendaDia` (o objeto a editar)

- `class AgendaDia extends Model implements TemDepartamento` (`app/Models/AgendaDia.php:16`) — **já**
  departamentalizado (Fase B). `departamentos()` = `belongsToMany(Departamento::class,
  'departamento_agenda_dia', 'agenda_dia_id', 'departamento_id')` (`:47-50`). Pivô **sem** timestamps/pivot
  extra ⇒ `->relationship('departamentos','nome')` grava só os dois ids.
- **Status**: consts `STATUS_PUBLICADO='publicado'` / `STATUS_RASCUNHO='rascunho'` (`:20-22`);
  `scopePublicado()` (`:42-45`).
- **`data`**: mutator `Attribute` get(Carbon)/set(string `Y-m-d`) (`:58-64`) — **não** é cast; consultar/
  upsert por strings `Y-m-d` (portabilidade SQLite×MySQL). Ver [[padrao-data-mutator-portavel]].
- **Sanitização**: `reflexao`/`meta_mes_texto`/`meta_dia_texto`/`prece` têm mutator `set` que aplica
  `clean($v,'conteudo')` (`:66-92`) — **o HTML já é sanitizado no model**, então o form embutido não
  precisa (nem deve) re-sanitizar; salvar via `$model->fill()`/`create()` basta.
- `$fillable` = `data, reflexao, meta_mes_texto, meta_dia_titulo, meta_dia_texto, prece, status, wp_id`
  (`:26-35`). **`departamentos` NÃO é fillable** (é relação) — sincroniza à parte (§7).
- ⚠️ **`AgendaDia` NÃO usa `LogsActivity` hoje** (imports do arquivo `:5-14`, sem
  `Spatie\Activitylog\...`). O trait é **adição desta fase** (§8, decisão 7).

### 2.2 O form do painel (o que vira a FONTE ÚNICA)

- `AgendaDiaResource::form(Schema $schema): Schema` é **inline no Resource** e retorna
  `$schema->components([...])` (`app/Filament/Resources/Agenda/AgendaDiaResource.php:38-83`). Campos, na
  ordem:
  - `DatePicker::make('data')->required()->native(false)->displayFormat('d/m/Y')->unique(table:
    'agenda_dias', column:'data', ignoreRecord:true)` (`:42-47`);
  - `Select::make('status')->required()->options([publicado,rascunho])->default(STATUS_PUBLICADO)` (`:48-55`);
  - 4× `RichEditor` `reflexao`/`meta_mes_texto`/`meta_dia_texto`/`prece` `->columnSpanFull()` (`:58-73`);
  - `TextInput::make('meta_dia_titulo')->maxLength(255)->columnSpanFull()` (`:64-67`);
  - `Select::make('departamentos')->relationship('departamentos','nome')->multiple()->searchable()
    ->preload()->required()->columnSpanFull()` (`:74-81`) — **acrescido na Fase C**.
- Layout **FLAT**: um `Grid::make(2)` (data + status) e o resto `columnSpanFull` (sem Tabs/Section).
  Namespaces: `Grid` de `Filament\Schemas\Components\*` (`:21`); `DatePicker`/`RichEditor`/`Select`/
  `TextInput` de `Filament\Forms\Components\*` (`:16-19`); `Schema` de `Filament\Schemas\Schema` (`:22`).
- **Molde de fonte única já existe**: `EventoForm::schema(): array` (`app/Filament/Schemas/EventoForm.php:37`)
  consumido por `EventoResource::form` via `return $schema->components(EventoForm::schema());`
  (`app/Filament/Resources/Eventos/EventoResource.php:39`). **É o padrão a replicar.** `EventoForm` é hoje o
  **único** arquivo Schema separado; os demais Resources têm o form inline.
- **Pages do AgendaDia são VANILLA**: `CreateAgendaDia extends CreateRecord` (corpo vazio) e `EditAgendaDia
  extends EditRecord` (só um `DeleteAction` no header) — **sem** trait de save custom (diferente de
  `Palestra`/`SincronizaPessoas`). ⇒ Nada de lógica de página a replicar no site além de
  `create/update/delete` + `saveRelationships()`.

### 2.3 A superfície `/minha-conta` (onde embutir)

- **Rotas** (`routes/web.php:40-43`): `Route::middleware('auth')->prefix('minha-conta')->name('conta.')
  ->group(...)` com **duas** rotas — `conta.painel` em `/` e `conta.perfil` em `/perfil`
  (`ContaController@painel`/`@perfil`). **É o molde exato** para a rota irmã `conta.agenda` (decisão 1).
- **`ContaController`** (`app/Http/Controllers/ContaController.php:13-35`): métodos finos `painel()`/
  `perfil()` que resolvem `auth()->user()`, montam dados e retornam `view(...)`. Molde para um método/
  controller de `agenda`.
- **Layout `x-layout.conta`** (`resources/views/components/layout/conta.blade.php`): `@props(['titulo',
  'ativo' => 'painel'])` (`:2`); **repassa os 3 slots** `headTop`/`head`/`scripts` (`:6-8`); renderiza
  `<x-conta.saudacao/>`, mensagem `session('status')` e `<x-conta.nav :ativo="$ativo"/>` (`:18`) + `{{ $slot
  }}`. Os slots `headTop`/`scripts` são a **porta do tema Filament** (§2.9), já provados no PR #24.
- **`x-layout.app`** (`resources/views/components/layout/app.blade.php`): `{{ $headTop ?? '' }}` (`:23`) é
  emitido **ANTES** de `@vite(['resources/css/app.css', ...])` (`:24`) — de propósito, para o CSS do site
  vencer a cascata; `{{ $scripts ?? '' }}` vem **DEPOIS** de `@livewireScripts` (`:38-42`). O layout usa
  `@vite`, **não** `@filamentStyles`/`@filamentScripts`.
- **Nav** (`resources/views/components/conta/nav.blade.php:2-8`): `@props(['ativo'])` + array **hardcoded**
  `$itens` com **2** itens (painel, perfil). Aba ativa por `chave`. **Adicionar o item `agenda`
  condicional** (decisão 1) exige computar a condição (§6).

### 2.4 A autorização já pronta (o que a Fase D consome)

- **Policy**: `AgendaDiaPolicy` (`app/Policies/AgendaDiaPolicy.php:15-38`) — `ver`/`criar`/`editar`/`excluir`,
  todas `hasPermissionTo('agenda.<acao>')` **E** escopo de departamento; `criar` usa
  `$user->departamentos()->exists()` (objectless, `:26`); as demais usam `objetoNoDepartamentoDoUsuario`
  (`:21,31,36`). **Pronta — não tocar.**
- **Trait de escopo**: `AutorizaPorDepartamento::objetoNoDepartamentoDoUsuario` (`app/Policies/Concerns/
  AutorizaPorDepartamento.php:16-27`): `pluck('departamentos.id')` **já qualificado** (evita "ambiguous
  column"); **fail-closed** dos dois lados (usuário **ou** objeto sem depto ⇒ `false`).
- **Capacidades** `agenda.*`: `GlossarioCapacidades::RECURSOS` inclui `'agenda'` (`app/Support/Autorizacao/
  GlossarioCapacidades.php:13`); `ACOES = [ver,criar,editar,excluir]` (`:15`); rótulos legíveis já existem —
  `RECURSOS_ROTULOS['agenda'] = 'Agenda do Dia'` (`:18-24`), `ACOES_ROTULOS` (`:27-32`), helpers
  `rotuloRecurso`/`rotuloAcao` (`:47-55`).
- **Gate::before** só cai para `administrador` (fundação A) — o não-admin **cai nas policies**; o admin passa
  antes (onipotente). Confirmado no comentário da própria policy (`:12-13`).
- ⚠️ **Precondição de runtime (não é código desta fase)**: para o piloto morder, a **matriz** precisa ter
  `agenda.*` ligado a `diretor` (e `trabalhador`, decisão 6). Isso é **estado de `role_has_permissions`**,
  configurado pelo admin em `/admin/matriz-capacidades` (Fase C) — **não** há comando/seeder que o faça, e o
  kickoff **não** o lista entre as 3 correções (§9). Nos testes cada caso fabrica o vínculo via
  `Role::findByName(...)->syncPermissions([...])`. **Resolvido no passe (D-F5): cutover MANUAL** (o admin liga
  na matriz), **sem 4º comando** — um comando escritor de `role_has_permissions` violaria a invariante da Fase
  C ("a matriz é o único escritor"). Ver §3.1/§13.

### 2.5 O vínculo do usuário e os dados a corrigir

- **Vínculo**: `User::departamentos()` = `belongsToMany(Departamento::class, 'departamento_usuario')`
  (`app/Models/User.php:59-62`), pivô sem timestamps. É o filtro de objeto.
- **Departamentos e ids**: `GlossarioUsuarios::DEPARTAMENTOS` (`app/Importacao/GlossarioUsuarios.php:21-30`)
  em ordem `DAS, DDA, DED, DEMAPA, DEPAE, DEPRO, DIJ, DECOM`. `EstruturaCemaSeeder` insere nessa ordem
  (`database/seeders/EstruturaCemaSeeder.php:28-33`) ⇒ **DED = id 3**, **DECOM = id 8** (bate com o kickoff).
- **Presidente**: cargo `diretor_presidente` é **institucional** e **sem departamento** (`departamento_id`
  null) (`GlossarioUsuarios.php:62`). O backfill `cema:vincular-diretores-departamento` **não o alcança**
  (só cargos com `departamento_id NOT NULL`, `VincularDiretoresDepartamento.php:24`) — daí a correção (c) do §9.
- **Papel do diretor por cargo**: quem ocupa um cargo `diretor_<sigla>` (não institucional, com depto)
  **deveria** ter papel `diretor`. O caso Valdemarques (papel `trabalhador`, cargo Diretor do DED) é um
  **erro de dado** — correção (a) do §9.
- **Dados de hoje** (FATOS VERIFICADOS do kickoff, reconfirmar no dev na execução — as consultas são
  read-only): **123 `AgendaDia`** todos **só em DECOM** (id 8); **DED (id 3)** ainda não está no N:N da
  agenda ⇒ correção (b) do §9 **soma** DED (não migra).

### 2.6 A auditoria já pronta (o helper a reusar)

- `AuditoriaAutorizacao` (`app/Support/Autorizacao/AuditoriaAutorizacao.php`): `LOG='autorizacao'` (`:15`);
  `porta()` = `Filament::getCurrentPanel()?->getId() ?? 'sistema'` (`:19-21`); `contexto()` = `porta + ip +
  user_agent` (`:24-33`); `diff()` (`:35-42`); `registrarPapelUsuario`/`registrarDepartamentosUsuario`
  (subject = `User`, `:51-79`); `registrar()` privado — **no-op se o diff for vazio** (`:82-93`).
- ⚠️ **A armadilha da porta**: `porta()` depende de `Filament::getCurrentPanel()`. Em `/minha-conta`
  **não há painel Filament** ⇒ `getCurrentPanel()` é `null` ⇒ `porta()` cairia em **`'sistema'`**, não
  **`'perfil'`**. E o momento que importa (o save do form) roda numa requisição **`POST /livewire/update`**,
  **não** na rota `conta.agenda` ⇒ detecção por rota (`routeIs('conta.*')`) **falha** no save. A premissa da
  Auditoria ("perfil nasce com o painel") **não vale aqui** — a porta precisa ser **marcada explicitamente**
  (§8, decisão 7).
- **Molde do trait**: `User` usa `LogsActivity` com `getActivitylogOptions()` (`useLogName` + `logOnly` +
  `logOnlyDirty` + `dontSubmitEmptyLogs` + `setDescriptionForEvent`, `app/Models/User.php:98-111`) e
  `tapActivity(Activity, string)` que faz `$activity->properties->merge(AuditoriaAutorizacao::contexto())`
  (`:114-117`). **É o molde 1:1** para o trait do `AgendaDia`.

### 2.7 Comandos `cema:*` (molde das correções de dados)

- **Molde**: `VincularDiretoresDepartamento` (`app/Console/Commands/VincularDiretoresDepartamento.php`) —
  `$signature='cema:vincular-diretores-departamento'`; filtro **semântico** (não por slug); idempotente via
  `departamentos()->syncWithoutDetaching($ids)` (`:31`); `$this->info(...)`; `return self::SUCCESS`.
- Há **8** comandos `cema:*` hoje (`app/Console/Commands/`): 6 importadores + `DepartamentalizarConteudos`
  + `VincularDiretoresDepartamento`. Todos idempotentes (upsert/sync). **Molde consolidado.**

### 2.8 Padrões de teste (moldes existentes)

- **Conta (site + Livewire)**: `tests/Feature/Conta/` — `AcessoContaTest`, `EditarPerfilTest`, `PainelTest`,
  `PerfilViewTest`, `PerfilFotoTest`, etc. Molde do componente `EditarPerfil` (`app/Livewire/Conta/
  EditarPerfil.php`): `Livewire\Component`, `mount()`/`rules()`/`salvar()` (`DB::transaction` + `session()->
  flash('status',...)` + `redirect(route('conta.perfil'), navigate:true)`), embutido via
  `<livewire:conta.editar-perfil/>` na view (`resources/views/conta/perfil.blade.php:76`).
- **Autorização/capacidade**: `tests/Feature/Autorizacao/` — `CapacidadeViaPapelTest` (caminho por **papel**:
  `Role::findByName(...)->syncPermissions([...])` + `assignRole` + `departamentos()->sync` +
  `Gate::forUser($u)->check('editar',$obj)`), `EventoPolicyCapacidadeTest`, `CapacidadeConteudosTest`.
- **Resource (regressão do /admin)**: `tests/Feature/Filament/AgendaDiaResourceTest.php` **já existe** —
  guarda que a extração do schema (§4) **não** quebra o painel.
- **Auditoria**: `tests/Feature/Autorizacao/Auditoria*Test` (`AuditoriaHelperTest`, `AuditoriaInfraTest`,
  `AuditoriaMatrizTest`, `AuditoriaUserResourceTest`, `AuditoriaUsuarioTest`) — molde para o teste do trait
  do `AgendaDia` e da **porta='perfil'**.
- **Factories**: `AgendaDia`, `User` existem. ⚠️ **Não há `DepartamentoFactory`** (herdado da Fase C) —
  usar `Departamento::create([...])` ou `seed(EstruturaCemaSeeder::class)`.

### 2.9 O spike (a fundação do form embutido) — o que provou e o que faltou

- **PASSOU**: o **mesmo** `EventoForm::schema()` renderiza e **salva** dentro de uma página do site, com a
  validação do Filament (spike report `docs/superpowers/spikes/2026-07-10-filament-forms-no-site.md`). O
  componente do spike (na tag `spike-filament-forms-2026-07-10`, `app/Livewire/Spike/FormularioEvento.php`)
  é o **molde de consumo**:
  ```
  class FormularioEvento extends Component implements HasForms { use InteractsWithForms;
    public ?array $data = [];
    mount(): $this->form->fill();
    form(Schema $schema): $schema->components(EventoForm::schema())->model(Evento::class)
                                 ->statePath('data')->operation('create');
    salvar(): $dados = $this->form->getState(); $e = Evento::create($dados);
             $this->form->model($e)->saveRelationships(); $this->form->fill();
  }
  ```
  (`HasForms` de `Filament\Forms\Contracts`; `InteractsWithForms` de `Filament\Forms\Concerns`; `Schema` de
  `Filament\Schemas\Schema`.)
- ⚠️ **CAUSA RAIZ do spike**: `@filamentStyles` **não** entrega o CSS dos componentes (`fi-*`) — eles vivem
  no **tema compilado do painel**. Sem o tema, o form renderiza cru. **Correção**: carregar o tema
  **escopado à página** e **antes** do `app.css`, via slots `headTop`/`scripts` (já no `x-layout.app`/
  `x-layout.conta`, §2.3). **Esta é a fundação nº 1 da Fase D** (§4).
- **Wiring de assets do tema** (`vite.config.js`): input inclui `resources/css/filament/admin/theme.css`; o
  tema do painel importa `vendor/filament/filament/resources/css/theme.css` + fontsource (Poppins/Work Sans)
  + `@source '../../../../app/Filament/**/*'` (`resources/css/filament/admin/theme.css:1-25`). **O tema do
  site será um arquivo NOVO** (§4), **não** o do painel.
- ⚠️ **Custos/lacunas do spike** (a fechar nesta fase):
  - **Peso**: `theme-*.css` do painel = **609 KB** (~63 KB gzip). Reusá-lo é caro; o kickoff pede um **tema
    enxuto do site** (§4).
  - **DatePicker `native(false)`** tem input `readonly` + painel flutuante — o spike **provou save/validação
    server-side**, **não** a interação real do DatePicker/RichEditor no browser. **Lacuna a fechar** no E2E
    (§10, decisão da ordem de construção 6).
  - O spike rodou em `x-layout.app` (não `x-layout.conta`); o `x-layout.conta` **passou a repassar** os slots
    no PR #24 (`conta.blade.php:6-8`) — **já pronto**.

### 2.10 Nenhuma migration nesta fase

Todo o schema já existe: `agenda_dias` + `departamento_agenda_dia` (B), `departamento_usuario` (A),
`role_has_permissions` (A), `activity_log` (Auditoria). O trait `LogsActivity` no `AgendaDia` **não** cria
tabela (usa `activity_log`). As correções do §9 são **comandos idempotentes**, não migrations. **0 migrations.**

## 3. Decisões travadas (do enquadramento) e cravadas por verificação

Do kickoff (dono + consultor, 13/jul). Ordem espelha o enquadramento; a verificação refina onde o código
diverge.

1. **Navegação = ROTAS IRMÃS + NAV CONDICIONAL.** Nova `conta.agenda` em `/minha-conta/agenda` (molde
   `conta.painel`/`conta.perfil`, §2.3). A aba só aparece com **CAPACIDADE E REGISTRO NO ESCOPO** (existe
   `AgendaDia` num depto do usuário) — senão apareceria vazia para todo diretor. **NÃO hardcodar DED/DECOM**
   na condição.
2. **Form = FONTE ÚNICA.** Extrair `AgendaDiaForm::schema(): array` (molde `EventoForm`, §2.2), usada pelo
   `AgendaDiaResource` **E** pelo componente do site.
3. **Listagem = LISTA PRÓPRIA do site** (Blade+Livewire, visual do site, mobile-first), escopada ao depto —
   **NÃO** a Filament Table.
4. **Publicação = parte de editar.** Quem tem `agenda.editar` controla o `status`; **NÃO** criar ação nova.
   O escalonamento server-side barra quem não tem a capacidade.
5. **Campo `departamentos` OCULTO e FORÇADO no servidor** para não-admin (criação = depto(s) do usuário;
   edição = preserva os do registro). **NUNCA confiar no POST.** `status` e `departamentos` são os **2 campos
   privilegiados** — filtrar **AMBOS** no servidor.
6. **Acesso da Agenda**: conteúdo N:N = **DED + DECOM**; a matriz liga `agenda.*` para **DIRETOR +
   TRABALHADOR** (colaborador). O filtro por depto garante que só quem é de DED/DECOM edita. Editores após as
   correções: **diretores/colaboradores de DED e DECOM + presidentes**.
7. **Auditoria do conteúdo**: trait `LogsActivity` no `AgendaDia` com `logOnly` dos **7 campos** (`data`,
   `status`, `reflexao`, `meta_mes_texto`, `meta_dia_titulo`, `meta_dia_texto`, `prece`) + **LOG MANUAL** do
   depto↔conteúdo (reusar/estender o helper `AuditoriaAutorizacao`). **PORTA = 'perfil'** (marcada
   explicitamente — §2.6, §8).
8. **Correções de dados (3 comandos `cema:*` idempotentes NESTE PR)**: (a) Valdemarques Dias Soares papel
   `trabalhador`→`diretor`; (b) **somar** DED ao N:N dos 123 `AgendaDia` (que já têm DECOM); (c) vincular os
   presidentes (Aury Cleide, Elizabete) aos **8** departamentos.

**Decisões cravadas por verificação (o enquadramento não previu, ou o código exige/diverge):**

- **(a) Rótulos legíveis já existem** — `GlossarioCapacidades::RECURSOS_ROTULOS`/`ACOES_ROTULOS` foram
  criados na Fase C (`:18-32`); a Fase D **reusa**, não recria.
- **(b) `AgendaDia` sem `LogsActivity` hoje** — o trait é adição nova (§2.1). Molde = `User` (§2.6).
- **(c) A porta 'perfil' precisa de marcação explícita** — `getCurrentPanel()` é null em `/minha-conta` e o
  save roda em `/livewire/update` (§2.6). Refina a decisão 7: a "marcação por rota" do kickoff tem de
  sobreviver ao `/livewire/update` (§8 propõe `boot()` do componente).
- **(d) O schema NÃO herda a validação de página do Resource** — mas o `AgendaDia` **não tem** trait de
  página custom (§2.2); a única regra a **reasserir no servidor** é o `unique('data')` (armadilha do kickoff)
  + o forço de `departamentos`/`status` (decisão 5). Diferente do `Evento`, que tinha `ValidaPeriodoEvento`.
- **(e) `departamentos` não é fillable** e a sanitização do HTML já está no model (§2.1) — o save do site
  fica simples: `fill/create/update` + `sync` explícito de departamentos.
- **(f) A matriz `agenda.*`→papéis é precondição de runtime**, não código desta fase (§2.4) — resolvido no
  passe como **passo de cutover manual** (sem 4º comando — §3.1, D-F5).

### 3.1 Decisões do passe adversarial (15/jul) — O1+O2 aplicados, D-F resolvidos

O passe **aprovou** o SPEC (✅, sem bloqueador de estrutura; 35/35 citações confirmadas) com **2 obrigatórios**
e cravou as confirmações. Rastreio (as mudanças estão nas seções citadas):

- **O1 — CREATE sempre nasce DED+DECOM** (decisão do dono; **supera e dissolve o D-F8**). No create, o
  registro nasce com os **departamentos mantenedores da Agenda** (DED+DECOM), para **qualquer** autor —
  **não** os departamentos do autor. Senão um editor só-do-DED criaria registro só-DED e o **DECOM não
  editaria** o novo (violaria a decisão 6 "ambos editam TODA a Agenda"). Derivar sem hardcode (`distinct` dos
  deptos dos `AgendaDia` existentes) ou por sigla (DED+DECOM) no piloto. `editar` **preserva** os deptos do
  registro. Aplicado em **§7**, teste **§10.7**; **D-F8 removido**.
- **O2 — `log_name` do log manual do depto = `'agenda'`** (não `'autorizacao'`). O `registrar()` do helper
  passa a aceitar um `$logName` (default `'autorizacao'`, preserva os callers de usuário) e o
  `registrarDepartamentosConteudo` passa `'agenda'`, unindo o histórico do `AgendaDia` (atributos + depto)
  numa trilha só. Aplicado em **§8.3**, teste **§10.12**.
- **Confirmações cravadas** (seguir o SPEC): **D-F1** (parâmetro booleano, campo **ausente** no site — não
  hidden/disabled), **D-F2** (guardar aba **E** rota por `agenda.ver` + registro no escopo; bootstrap do 1º
  registro só via `/admin` = ciência), **D-F3** (porta via `boot()` — **verificado no vendor**:
  `SupportLifecycleHooks:30`/`:46`), **D-F4** (correção (a) semântica atinge **só** o Valdemarques; (b) **NÃO
  auditada** — dado de cutover), **D-F5** (**SEM 4º comando**: ligar `agenda.*` na matriz por comando
  **violaria** a invariante da Fase C "a matriz é o único escritor de `role_has_permissions`"; é **cutover
  manual** pelo `/admin/matriz-capacidades`), **D-F6** (`agenda.ver` abre a aba), **D-F9** (forçar
  `status=rascunho` no create de quem tem `criar` mas não `editar`). **D-F7** (UX da lista) segue **aberto
  para o plano/execução**.

## 4. Fundação — `AgendaDiaForm::schema()` + tema Filament escopado (D1)

### 4.1 Extrair `AgendaDiaForm::schema(): array` (fonte única)

**Novo**: `app/Filament/Schemas/AgendaDiaForm.php` (molde `EventoForm`, §2.2). Move o array de componentes
hoje inline em `AgendaDiaResource::form` (`:40-82`) para `AgendaDiaForm::schema(): array`; o Resource passa a
`return $schema->components(AgendaDiaForm::schema());` (molde `EventoResource.php:39`). Namespaces preservados
(`Grid`/`Schema` de `Filament\Schemas\*`; `DatePicker`/`RichEditor`/`Select`/`TextInput` de
`Filament\Forms\Components\*`).

**⚠️ O campo `departamentos` é privilegiado** (decisão 5). Para manter **uma** fonte e ainda **ocultá-lo** no
site, **decidido no passe (D-F1)**: `schema(bool $comDepartamentos = true): array` — o Resource chama com o
default `true` (o `/admin` continua com o `Select`); o componente do site chama com `false` e o campo fica
**AUSENTE do schema** (não `hidden`/`disabled`, que ainda tramitariam estado) e o servidor **força** o valor
(§7). "Oculto e forçado" = ausente do schema do site + setado no `salvar()`.

**Regressão**: `AgendaDiaResourceTest` (`tests/Feature/Filament/AgendaDiaResourceTest.php`) deve continuar
verde — a extração é **comportamento-preservador** no painel.

### 4.2 Tema Filament escopado ao site (a fundação de CSS)

**Novo**: um **tema enxuto do site** (arquivo CSS novo, ex. `resources/css/filament/site/theme.css`, +
entrada no `input` do `vite.config.js`), carregado **só** na página da agenda via `x-slot:headTop`
(**antes** do `app.css`, §2.3) e o JS dos componentes via `x-slot:scripts` (**depois** de
`@livewireScripts`, §2.3). Requisitos (armadilhas do kickoff + custos do spike, §2.9):

- **SEM preflight** — o site já tem o seu (evita duplo reset que quebraria header/footer).
- **SEM a fonte Inter** — o site tem a própria tipografia; o tema não deve importar/forçar Inter.
- **`@source` restrito** aos componentes de form realmente usados (`DatePicker`, `RichEditor`, `Select`,
  `TextInput`, `Grid`) — enxugar os 609 KB do tema do painel.
- **Escopado**: carregado só na página da agenda; **não vaza** para painel/perfil/demais.

⚠️ **Este é o item de MAIOR RISCO da fase** (CSS de tema Filament v5 + Tailwind v4, sem preflight/Inter). A
receita exata (o que importar do `vendor/filament/.../theme.css`, como cortar preflight, qual diretiva de JS
— `@filamentScripts` vs entrada Vite) é **detalhe de build a cravar em D1**, provado **no browser** (fecha a
lacuna do spike). npm/Vite rodam **no host** (o container não tem Node — [[npm-vite-no-host]]);
`make:filament-theme` **aborta no container** ([[filament-tema-fonte-e-gotchas]]).

> **Consideração de split (não decidir no SPEC — §14):** D1 = §4 (fonte única + tema + aba com o form já
> renderizando e salvando). D2 = §5–§9 (vertical CRUD + autorização + auditoria + correções + E2E).

## 5. Superfície — rota `conta.agenda` + componente Livewire (lista + form embutido)

### 5.1 Rota e controller

**Alterado**: `routes/web.php` (grupo `conta.`, `:40-43`) — adicionar `Route::get('/agenda', [ContaController
::class, 'agenda'])->name('agenda')`, dentro do grupo `auth`/`prefix('minha-conta')`. **Alterado**:
`ContaController` — método `agenda()` que **autoriza o acesso** (§6) e retorna `view('conta.agenda')`.

### 5.2 Componente Livewire (o coração da superfície)

**Novo**: `app/Livewire/Conta/AgendaDia*` — um componente Livewire (molde: `HasForms` + `InteractsWithForms`
do spike, §2.9, combinado com o estilo `EditarPerfil`, §2.8). A **lista** é Blade+Livewire própria (decisão
3), **não** Filament Table; o **form** de criar/editar é o Filament Form embutido (fonte única, §4). Estados:

- **Modo lista** (default): renderiza a lista **escopada ao(s) departamento(s) do usuário** (§6), visual do
  site, **mobile-first** (cards/linhas), com ações "Editar"/"Excluir" por item e um botão "Novo".
- **Modo form** (criar/editar): monta `AgendaDiaForm::schema(comDepartamentos: false)` via
  `->statePath('data')->model(AgendaDia::class | $registro)->operation('create'|'edit')`; `salvar()` valida
  (`getState()`), persiste e força os campos privilegiados (§7); `excluir($id)` remove após `authorize`.

**Padrões herdados** (`EditarPerfil`, §2.8): `session()->flash('status', ...)` + `redirect(route('conta.
agenda'), navigate:true)` no fim de cada ação; colisão propriedade↔método evitada ([[livewire-colisao-
propriedade-metodo]] — nomes de método distintos das propriedades). O componente é embutido na view
`resources/views/conta/agenda.blade.php` com `<x-layout.conta titulo="Agenda da Reforma Íntima"
ativo="agenda">` + os `x-slot:headTop`/`x-slot:scripts` do tema (§4.2).

### 5.3 Nav condicional

**Alterado**: `resources/views/components/conta/nav.blade.php` — inserir o item `agenda` (chave `'agenda'`,
rótulo ex. "Agenda", rota `conta.agenda`) no array `$itens` **condicionalmente** (decisão 1): só quando
`AbaAgenda::visivelPara(auth()->user())` (§6.1). O item ativo (`$ativo === 'agenda'`) é passado por
`x-layout.conta ativo="agenda"`.

## 6. Autorização, escopo e escalonamento (o núcleo server-side)

### 6.1 Fonte única da visibilidade/acesso da aba

**Novo**: `App\Support\Conta\AbaAgenda` (ou gate dedicado) — `visivelPara(User $u): bool` =
`$u->hasPermissionTo('agenda.ver')` **E** existe `AgendaDia` num depto do usuário:

```
$ids = $u->departamentos()->pluck('departamentos.id')->all();      // qualificado (armadilha ambiguous)
return $ids !== []
    && $u->hasPermissionTo('agenda.ver')
    && AgendaDia::whereHas('departamentos', fn ($q) => $q->whereIn('departamentos.id', $ids))->exists();
```

⚠️ **Ordem importa** (perf): checar a capacidade (em memória, via papéis/permissions já carregados) **antes**
da query `exists()` — a nav renderiza em **toda** página `/minha-conta`. Memoizar no request. **Fonte única
usada por 3 lugares**: (1) a **nav** (mostrar/ocultar a aba), (2) o **controller** `agenda()`
(`abort_unless(...)`), (3) o **mount** do componente (guarda). Evita divergência nav↔rota.

> **Nuance (decidido no passe — D-F2):** a aba **e a rota** são guardadas por `agenda.ver` **+ registro no
> escopo** (decisão 1). Um diretor com capacidade mas **sem** `AgendaDia` no seu depto **não vê a aba nem
> alcança a rota** — logo não cria o 1º registro pelo site. Após a correção (b) do §9, **todo** editor de
> DED/DECOM tem os 123 registros no escopo, então na prática a aba aparece. O "bootstrap do 1º registro de um
> depto sem agenda" fica **só pelo /admin** — limitação aceitável do piloto (**ciência** §13).

### 6.2 Lista escopada ao departamento

A lista (modo lista, §5.2) consulta **apenas** `AgendaDia` cujos departamentos intersectam os do usuário
(mesma expressão `whereHas` do §6.1) — **fail-closed** (usuário sem depto ⇒ lista vazia). **Nunca** lista a
agenda inteira. O admin continua vendo tudo pelo `/admin` (fora de escopo).

### 6.3 Ações gateadas por policy

Cada ação chama a **policy real** (§2.4), não reimplementa autorização:

- **listar/abrir**: `AbaAgenda::visivelPara` (capacidade `agenda.ver` + registro no escopo) — §6.1.
- **criar**: `$this->authorize('criar', AgendaDia::class)` ⇒ `agenda.criar` + `departamentos()->exists()`.
- **editar**: `$this->authorize('editar', $registro)` ⇒ `agenda.editar` + interseção de departamento.
- **excluir**: `$this->authorize('excluir', $registro)` ⇒ `agenda.excluir` + interseção de departamento.

Assim o não-admin de outro departamento é **negado** mesmo forjando um `id` de outro depto (a policy
`objetoNoDepartamentoDoUsuario` barra).

### 7. Os 2 campos privilegiados forçados no servidor (escalonamento)

Nada do POST é confiável (decisão 5, CLAUDE.md §7 de segurança). No `salvar()`:

- **`departamentos` (oculto e forçado)** — ausente do schema do site (§4.1). O servidor define a relação:
  - **criar (O1 do passe — todo novo `AgendaDia` nasce DED+DECOM)**: o registro nasce com os **departamentos
    mantenedores da Agenda** = **DED + DECOM**, para **qualquer** autor — **NÃO** os departamentos do autor.
    Racional: um editor só-do-DED que criasse um registro só-DED faria o **DECOM não editar** o novo registro,
    violando a decisão 6 ("ambos editam TODA a Agenda"). Derivar **sem hardcode**: `distinct` dos
    departamentos dos `AgendaDia` existentes (após a correção (b), = {DED, DECOM}); ou, explícito no piloto,
    **por sigla** (`Departamento::whereIn('sigla', ['DED','DECOM'])->pluck('id')`). Invariante: **todo novo
    `AgendaDia` = DED+DECOM**, independente de quem cria — `$registro->departamentos()->sync($idsMantenedores)`.
    (Isso **dissolve** o antigo D-F8: o presidente também cria DED+DECOM, não os 8 deptos dele.)
  - **editar**: **preservar** os departamentos do registro (não tocar) — o não-admin **não** reassocia
    departamentos. (Só o admin, pelo `/admin`, reassocia.)
- **`status` (visível, mas validado)** — é editável (publicar = parte de editar, decisão 4); o servidor
  **reasserta** que `status ∈ {publicado, rascunho}` (belt sobre o enum do Select). ⚠️ **No create, forçar
  `status=rascunho` para quem tem `agenda.criar` mas NÃO `agenda.editar`** (D-F9, resolvido no passe): a
  policy `criar` não exige `editar` (`AgendaDiaPolicy.php:24-27`) e o default do campo é `publicado` — sem o
  forço, um criador-sem-editar publicaria já na criação, contra a decisão 4. Quem tem `agenda.editar` controla
  o status normalmente (publica/despublica).
- **`unique('data')` reasserido** (armadilha do kickoff) — a regra de campo já viaja no schema
  (`AgendaDiaResource.php:47`), mas depende de o form saber o registro (`->model()->operation('edit')`); como
  **cinturão**, reasserir server-side no `salvar()` (`Rule::unique('agenda_dias','data')->ignore($id)` ou
  query por string `Y-m-d`, respeitando o mutator, §2.1) antes de persistir.

Persistência (molde spike + Pages vanilla, §2.2/§2.9): `getState()` → `AgendaDia::create($dados)` /
`$registro->update($dados)` → **sync explícito** de `departamentos` (acima) → (nada de `saveRelationships`
para departamentos, já que o campo não está no schema do site). O `id`/PK nunca vem do POST.

## 8. Auditoria do conteúdo (trait + porta 'perfil' + log manual do depto)

### 8.1 Trait `LogsActivity` no `AgendaDia`

**Alterado**: `app/Models/AgendaDia.php` — `use LogsActivity` + `getActivitylogOptions()` +
`tapActivity()`, **molde 1:1 do `User`** (§2.6):

- `getActivitylogOptions()`: `useLogName('agenda')` · `logOnly(['data','status','reflexao','meta_mes_texto',
  'meta_dia_titulo','meta_dia_texto','prece'])` (os **7 campos** da decisão 7) · `logOnlyDirty()` ·
  `dontSubmitEmptyLogs()` · `setDescriptionForEvent` (created/updated/deleted em pt-BR).
- `tapActivity(Activity $a, string $evento)`: `$a->properties = $a->properties->merge(AuditoriaAutorizacao::
  contexto())` — carrega **porta + ip + user_agent** em toda entrada automática (a porta resolve pela §8.2).

⚠️ **Efeito colateral esperado**: o trait passa a registrar **qualquer** save de `AgendaDia`, inclusive o
`/admin` (porta `admin`) e o importador `cema:importar-agenda` (porta `sistema`, sem request). `logOnlyDirty`
+ `dontSubmitEmptyLogs` evitam ruído em re-imports idempotentes. Molde já validado no `User` (que também é
importado). Ciência §13.

### 8.2 A porta 'perfil' marcada explicitamente

**Alterado**: `AuditoriaAutorizacao` — adicionar uma **marcação de porta** que sobreponha o default, para o
contexto `/minha-conta`. Proposta (§15, D-F3): um override estático + `porta()` lê-o primeiro:

```
private static ?string $portaForcada = null;
public static function usarPorta(?string $porta): void { self::$portaForcada = $porta; }
public static function porta(): string {
    return self::$portaForcada ?? Filament::getCurrentPanel()?->getId() ?? 'sistema';
}
```

O **componente Livewire da agenda** seta `AuditoriaAutorizacao::usarPorta('perfil')` no **`boot()`** — que
no Livewire **v4.3.2** roda em **toda** requisição do componente (mount **e** hydration/`/livewire/update`),
portanto cobre o momento do save. ✅ **Verificado no passe adversarial no vendor**: `SupportLifecycleHooks:30`
(mount) e `:46` (hydrate) chamam `boot()` nos dois caminhos. Assim **ambos** os registros (o automático do
trait **e** o manual do depto, §8.3) saem com `porta='perfil'`, sem quebrar o `/admin` (override só é setado
pelo componente do site) nem o CLI (porta `sistema`).

⚠️ **Ciência (§13)**: estático vive **por request** em PHP-FPM (sem vazamento entre requests). Sob Octane
precisaria reset em `terminating`/dehydrate — o projeto **não** usa Octane hoje. Detecção por rota
(`routeIs('conta.*')`) **não** serve (falha no `/livewire/update`, §2.6). Alternativas no §15.

### 8.3 Log manual do depto↔conteúdo

O `logOnly` **não** captura relação N:N (só atributos dirty). Como o `departamentos` é forçado no servidor
(§7), registrar o vínculo **manualmente**, **estendendo** o helper (kickoff: "reusar/estender; NÃO recriar"):

**Alterado**: `AuditoriaAutorizacao` — (1) o `registrar()` privado (`:82-93`) passa a aceitar um **`string
$logName = self::LOG`** (default `'autorizacao'`, **preservando** os callers dos pivôs de usuário); (2) novo
`registrarDepartamentosConteudo(Model $conteudo, array $antes, array $depois): void` (subject = o conteúdo,
mesma forma diff-por-id de `registrarDepartamentosUsuario`, `:62-79`; **no-op** se o diff for vazio) que chama
`registrar(..., $logName: 'agenda')`.

⚠️ **O `log_name` é `'agenda'`, NÃO `'autorizacao'`** (O2 do passe): o trait do `AgendaDia` grava em
`useLogName('agenda')` (§8.1), então o histórico de um `AgendaDia` — **atributos E vínculo de depto** — fica
numa **trilha única** `log_name='agenda'` (não fragmentado entre 'agenda' e 'autorizacao'). Chamado no
`salvar()`:

- **criar**: `antes=[]`, `depois=[id=>nome]` dos departamentos mantenedores (**DED+DECOM**, §7 O1) ⇒ registra
  a associação inicial em `log_name='agenda'`.
- **editar**: `antes=depois` (departamentos preservados) ⇒ **no-op** (o helper ignora diff vazio) — correto,
  o não-admin não muda departamentos.

## 9. Correções de dados (3 comandos `cema:*` idempotentes)

Todos molde `VincularDiretoresDepartamento` (§2.7): `cema:*`, idempotentes (`sync`/`syncWithoutDetaching`/
`updateOrCreate`), `$this->info` com contagem, `return self::SUCCESS`. **Rodar no dev** (nunca destrutivo) e
**versionar** para o cutover. Onde tocam pivô de autorização, **auditar** via helper (porta `sistema` no
CLI, correto). **Nomes propostos** (a cravar — §15, D-F4).

- **(a) `cema:corrigir-papel-diretores`** — Valdemarques Dias Soares `trabalhador`→`diretor`. Identificação
  **semântica** (mais robusta que hardcode de nome): usuários que ocupam um cargo **de diretor com
  departamento** (`cargos.departamento_id NOT NULL`, `institucional=false`) **e** têm papel `trabalhador` ⇒
  `syncRoles(['diretor'])` (ou `assignRole` + `removeRole`), auditando via `registrarPapelUsuario` (`:51-54`).
  ✅ **Passe confirmou**: o único usuário desalinhado é o Valdemarques — o filtro semântico atinge **só** ele.
- **(b) `cema:somar-ded-agenda`** — para cada `AgendaDia` que **tem DECOM** (id 8), **somar** DED (id 3):
  `departamentos()->syncWithoutDetaching([$dedId])`. Idempotente (não duplica; não remove DECOM). Ids
  resolvidos por **sigla** (`Departamento::where('sigla','DED')`), não hardcode numérico.
  ✅ **(b) NÃO é auditada (decidido no passe)**: é **dado de cutover**, não ação de usuário; auditar 123
  entradas `sistema` seria ruído. (a)/(c) auditam porque mudam papel/vínculo de **usuário**; a (b) só
  materializa o N:N do **conteúdo** em massa. Intencional.
- **(c) `cema:vincular-presidentes-departamentos`** — presidentes (Aury Cleide, Elizabete) → **8**
  departamentos. Proposta **semântica**: usuários com cargo `diretor_presidente` (`GlossarioUsuarios.php:62`)
  ⇒ `departamentos()->syncWithoutDetaching(Departamento::pluck('id'))`, auditando via
  `registrarDepartamentosUsuario` (`:62-79`). Materializa a decisão de Fase C (presidente edita tudo).

⚠️ **`agenda.*`→papéis na matriz** **não** é comando desta fase (§2.4, precondição de runtime) — **cutover
MANUAL** (o admin liga em `/admin/matriz-capacidades`), **sem 4º comando** (resolvido no passe, D-F5/§3.1: um
comando escritor de `role_has_permissions` violaria a invariante da Fase C).

## 10. O que o spec deve provar (testes desta fase)

**Fundação (§4)** — molde `AgendaDiaResourceTest` + `EventoResourceTest`:

1. **Regressão do painel** — `AgendaDiaResourceTest` continua verde após extrair `AgendaDiaForm::schema()`
   (create/edit/departamentos no `/admin` intactos).
2. **Fonte única** — `AgendaDiaForm::schema()` retorna os campos esperados; `schema(comDepartamentos:false)`
   **omite** o `Select` de `departamentos` (asserção sobre a árvore de componentes).
3. **Tema escopado (browser/E2E)** — a página `conta.agenda` renderiza o form **estilizado** (DatePicker com
   painel, RichEditor com toolbar) e **não vaza** CSS do Filament para `/minha-conta`/`/perfil` (hash/render
   de controle, molde do critério 4 do spike). Fecha a lacuna do spike (interação real, §2.9).

**Superfície + autorização (§5–§7)** — molde `tests/Feature/Conta/` + `CapacidadeViaPapelTest`:

4. **Nav condicional** — a aba "Agenda" **aparece** para quem tem `agenda.ver` **e** `AgendaDia` no escopo;
   **some** para quem não tem capacidade, e para quem tem capacidade mas **sem** registro no escopo (decisão
   1). `AbaAgenda::visivelPara` testado direto + via render da nav.
5. **Acesso à rota** — `GET /minha-conta/agenda` **200** para editor no escopo; **403** para usuário sem
   capacidade / sem registro no escopo (mesma fonte única do §6.1).
6. **Lista escopada** — o editor vê **só** os `AgendaDia` do(s) seu(s) depto(s); um registro de outro depto
   **não** aparece (fail-closed).
7. **CRUD via papel** (caminho por **PAPEL**, não `givePermissionTo` — §2.8): `Role::findByName('diretor',
   'web')->syncPermissions(['agenda.ver','agenda.criar','agenda.editar','agenda.excluir'])`; `$u->assignRole
   ('diretor')`; `$u->departamentos()->sync([$decom])`:
   - **criar** pelo componente ⇒ o novo registro nasce com **DED+DECOM** (mantenedores, §7 O1), **não** só o
     depto do autor — asserir que um editor **só-do-DECOM** cria um `AgendaDia` que o **DED também edita**
     (`Gate::forUser($diretorDED)->check('editar', $novo)` = true), e que um depto forjado no POST **não** entra;
   - **editar** ⇒ `data`/`status`/textos mudam; **departamentos preservados** (POST forjado ignorado);
   - **excluir** ⇒ remove; **negado** (`403`/`authorize` falha) para registro de **outro** depto;
   - **criar sem `agenda.editar`** ⇒ o registro nasce **`rascunho`** mesmo com o POST pedindo `publicado`
     (D-F9, §7).
8. **Escalonamento de `status`** — editor publica/despublica (parte de editar); valor fora do enum é
   rejeitado (belt server-side). Usuário sem `agenda.editar` **não** alcança a ação (403).
9. **Isolamento entre departamentos** — editor **só do DED** edita `AgendaDia` em [DED, DECOM]
   (interseção), mas é **negado** em `AgendaDia` só de DECOM sem interseção com ele (caso disjunto) — molde do
   teste de interseção da Fase C.

**Auditoria (§8)** — molde `Auditoria*Test`:

10. **Trait registra os 7 campos** — editar `reflexao`/`status`/`data` gera entrada `log_name='agenda'` com
    o diff dos campos dirty; save sem mudança ⇒ **nenhuma** entrada (`logOnlyDirty`+`dontSubmitEmptyLogs`).
11. **Porta 'perfil' no site** — save pelo componente da agenda ⇒ `properties['porta'] === 'perfil'`; o
    **mesmo** trait salvando no `/admin` ⇒ `'admin'`; pelo comando CLI ⇒ `'sistema'` (prova a marcação da §8.2).
12. **Log manual do depto** — criar registra `registrarDepartamentosConteudo` com **`log_name='agenda'`** (O2)
    e adicionados = **DED+DECOM** (mantenedores, §7 O1); editar sem mudar depto ⇒ **no-op** (diff vazio).
    Asserir que atributos **e** vínculo de depto do `AgendaDia` ficam na **mesma** trilha `log_name='agenda'`.

**Correções de dados (§9)** — molde `VincularDiretoresDepartamentoTest`:

13. **(a)** roda ⇒ o alvo vira `diretor`; **idempotente** (rodar 2× não duplica papel); **não** promove quem
    não deve (asserir um `trabalhador` sem cargo de diretor permanece `trabalhador`).
14. **(b)** roda ⇒ `AgendaDia` com DECOM passa a ter **DED+DECOM**; DECOM **preservado**; idempotente.
15. **(c)** roda ⇒ presidente vinculado aos **8** departamentos; idempotente; auditado.

16. **Suíte inteira + Pint** verdes no container ([[pint-antes-de-push]]; ciência
    [[flaky-importadorblog-gd-cap-imagem]]: 2 testes de cap de imagem do blog podem falhar sob carga — se
    passam isolados, não é regressão desta fase).

## 11. Fora de escopo (não fazer nesta fase)

- **Blog/Eventos/Palestras e demais abas** — **Fase E** (esta é o piloto; o padrão nasce aqui).
- **Filament Table no site** — a lista é Blade+Livewire própria (decisão 3).
- **Viewer/tela de auditoria** — a Auditoria só **grava** (a leitura é fase futura).
- **Reabrir A/B/C ou o modelo de capacidades** — policies/trait/pivôs/contrato/matriz **intocados**.
- **Editar `departamentos` de `AgendaDia` pelo não-admin** — forçado no servidor; só o admin reassocia
  (`/admin`).
- **Ação de "publicar" separada** — publicar é parte de editar (decisão 4).
- **Configurar a matriz `agenda.*`→papéis** — precondição de runtime (§2.4), não código desta fase.

## 12. Fronteiras: o que a Fase D toca × o que NÃO toca

**Toca (novo)**: `AgendaDiaForm` (schema) · tema CSS do site · componente Livewire da agenda + view + rota +
`ContaController@agenda` · `AbaAgenda` (fonte única de acesso) · item condicional na nav · trait `LogsActivity`
+ `tapActivity` no `AgendaDia` · `AuditoriaAutorizacao` (override de porta + `registrarDepartamentosConteudo`)
· 3 comandos `cema:*`.

**NÃO toca**: `AgendaDiaPolicy`, `AutorizaPorDepartamento`, `TemDepartamento`, `Gate::before`,
`config/permission.php`, as demais 4 policies, `EventoForm`, a matriz `MatrizCapacidades`, `CapacidadesSeeder`,
`EstruturaCemaSeeder`, `GlossarioCapacidades` (rótulos já existem), `GlossarioUsuarios::PAPEIS_EDITAVEIS`. O
`AgendaDiaResource::table`/Pages do `/admin` seguem intactas (só o `form()` passa a consumir a fonte única).

## 13. Ciências (não são tarefa desta fase)

- **Bootstrap do 1º registro de um depto sem agenda** — a aba/rota exige registro no escopo (decisão 1); um
  depto novo com capacidade mas sem `AgendaDia` só cria o 1º pelo `/admin`. Aceitável no piloto; revisitar na
  Fase E (talvez guardar a rota por capacidade e a **lista** por escopo).
- **Porta via estático é por-request (PHP-FPM)** — sem vazamento hoje; se o projeto adotar Octane, resetar em
  `terminating`/dehydrate. Vigiar.
- **`LogsActivity` no `AgendaDia` audita também import/admin** — porta `sistema`/`admin`, esperado; `logOnly
  Dirty`+`dontSubmitEmptyLogs` contêm o ruído. Mesmo comportamento do `User`.
- **A matriz `agenda.*`→papéis é estado de runtime** — se ninguém ligar `agenda.editar` para `diretor`, o
  piloto **não morde** em produção mesmo com tudo implementado. Passo de **cutover MANUAL** obrigatório (o
  admin liga em `/admin/matriz-capacidades`), sem 4º comando (§3.1/§15, D-F5).
- **`trabalhador` (colaborador) edita agenda** (decisão 6) — a matriz pode ligar `agenda.*` a `trabalhador`;
  como `frequentador` **não** é curto-circuitado pelo `Gate::before` (herdado A/B/C), qualquer capacidade
  ligada a ele morderia. A grade da matriz **só** toca `trabalhador`/`diretor` (Fase C) — vigiar.
- **DatePicker `native(false)` é readonly** (spike, problema 3) — E2E clica como usuário real; automação
  Playwright precisa lidar com o painel flutuante.

## 14. Consideração de execução (D1/D2 — decisão do PLANO, não do SPEC)

A fase é grande (tema CSS de risco → E2E de JS). **Avaliar no plano** dividir em:

- **D1 (fundação)**: §4 — `AgendaDiaForm::schema()` extraído (Resource verde) + tema escopado + rota/aba com o
  form **renderizando e salvando** um `AgendaDia` (o "hello world" do form no site), provado no browser.
- **D2 (vertical completa)**: §5–§9 — lista escopada + CRUD + policies/escopo + campos privilegiados forçados
  + auditoria (porta 'perfil' + log de depto) + 3 correções + E2E completo.

Racional: D1 isola o **risco de CSS/tema** (o que quase reprovou o spike) num PR pequeno e verificável antes
de empilhar a lógica de autorização/auditoria. **Não decidir aqui** — o plano decide após o passe.

## 15. Pontos a confirmar no passe adversarial — RESOLVIDOS (passe de 15/jul)

> **Veredito: ✅ APROVADO após 2 ajustes** (**O1** = create nasce DED+DECOM; **O2** = `log_name='agenda'` no
> log manual do depto) + confirmações. As resoluções estão na **§3.1** e aplicadas nas seções citadas; os
> itens abaixo ficam como registro. **Só o D-F7 (UX) segue aberto** — vai ao plano/execução. Nada reabre A/B/C.

- **D-F1 (resolvido — parâmetro booleano).** `AgendaDiaForm::schema(bool $comDepartamentos = true)`; no site o
  campo é **ausente** do schema (não `hidden`/`disabled`) + forçado no servidor (§4.1/§7). ✅
- **D-F2 (resolvido — capacidade + registro no escopo).** Aba **E** rota guardadas por `agenda.ver` + registro
  no escopo; bootstrap do 1º registro de um depto sem agenda fica só via `/admin` (**ciência** §13). ✅
- **D-F3 (resolvido — porta via `boot()`).** Override estático setado no `boot()` do componente (§8.2);
  **verificado no vendor** (`SupportLifecycleHooks:30` mount / `:46` hydrate — cobre o `/livewire/update`).
  Nuance Octane = ciência §13. ✅
- **D-F4 (resolvido — semântica; (b) sem auditoria).** (a) filtro semântico atinge **só** o Valdemarques
  (confirmado); (b) **NÃO auditada** (dado de cutover, não ação de usuário — evita 123 entradas `sistema` de
  ruído); (c) semântica por cargo `diretor_presidente`. ✅
- **D-F5 (resolvido — SEM 4º comando; cutover manual).** Ligar `agenda.*` na matriz por comando **violaria** a
  invariante da Fase C ("a matriz é o **único** escritor de `role_has_permissions`"). O cutover é **manual**:
  o admin liga `agenda.*` para `diretor`/`trabalhador` em `/admin/matriz-capacidades`. Documentado como passo
  de cutover (§2.4, §13). ✅
- **D-F6 (resolvido — `agenda.ver`).** `agenda.ver` abre a aba; criar/editar/excluir gateados por suas
  próprias abilities dentro da superfície (§6.3). ✅
- **D-F7 — Redação/UX da lista mobile-first e do fluxo criar/editar** (cards vs linhas, inline vs rota
  `/agenda/{id}/editar`). **Segue aberto** — **decisão de UI** no plano/execução, com os handoffs de design em
  `design_handoff_agenda_reforma_intima/` (untracked) como referência.
- **~~D-F8~~ (dissolvido pelo O1).** O create passa a nascer **DED+DECOM** para qualquer autor — o presidente
  também cria DED+DECOM (não os 8 deptos dele). Sem fork. ✅
- **D-F9 (resolvido — forçar `rascunho`).** No create, o servidor força `status=rascunho` para quem tem
  `agenda.criar` mas **não** `agenda.editar` (§7); quem tem `editar` controla o status normalmente. ✅

**Regras de sempre** (CLAUDE.md — reforçar ao gerar os briefs de execução): pt-BR em tudo; **cabeçalho de
autoria** nos PHP novos; `Pint` antes do push; `docker compose exec -T app php artisan test`; commits
atômicos; **0 migrations** (§2.10); correções de dados via `cema:*` idempotente. ⚠️ **Todo brief de subagente
que rode `artisan` DEVE proibir explicitamente `migrate:fresh`/`refresh`/`wipe`/`reset` e seed destrutivo**
(o dev tem 123 palestras/agenda + 44 posts + mídia importados; incidente 28/06 zerou o dev por um
`migrate:fresh` de subagente) e reafirmar que a **conexão `legado` é read-only**. Ver
[[nunca-migrate-fresh-no-dev]].
