# Spec — Camada 1 · Configuração de acesso por tipo (integrada à matriz)

**Data:** 2026-07-16
**Base:** `origin/main` = `995f54e` (PR #31 — docs). `80af57d` (Fase D, PR #30) é ancestral.
**Branches:** `camada-1-acesso-por-tipo-e1` → `camada-1-acesso-por-tipo-e2` (dois PRs).
**Substitui:** a "Fase E" do enquadramento antigo.
**Fases anteriores:** A (capacidades), B (departamento nos conteúdos), C (matriz papel×capacidade),
Auditoria (activitylog), D (Agenda no /minha-conta).

---

## 1. Contexto e objetivo

Hoje, um não-admin edita um objeto quando **o objeto está num departamento em comum com o usuário** —
interseção de dois pivôs, avaliada objeto a objeto
([AutorizaPorDepartamento.php:16-27](../../../app/Policies/Concerns/AutorizaPorDepartamento.php#L16-L27)).

A Camada 1 troca o eixo: o não-admin edita quando **ele está num departamento responsável pelo TIPO**.
Onde mora a verdade do escopo deixa de ser o registro e passa a ser o tipo — configurado uma vez, numa
tela, por quem administra.

**Isto é a fatia mais estrutural desde a Fase A.** O filtro é o coração da segurança: um erro aqui abre
acesso indevido. Por isso o trabalho vai em **dois PRs** (§8), e cada invariante de segurança (§7) vira
teste que reprova de verdade.

**Escopo:** só "quem edita / é responsável" por tipo. A visibilidade pública (quem vê o publicado)
fica **fora** — é outro eixo (`roles.nivel` / `scopeVisiveisPara` / `VisibilidadeEvento`), tratado na
Camada 4. Quem edita é quem vê a aba.

**Forma:** integrada à `MatrizCapacidades` (`/admin/matriz-capacidades`), que já é organizada por tipo.
Cada `Section` de recurso ganha o **regime** + os **departamentos responsáveis**, ao lado dos toggles de
papel. Um lugar só por tipo. **Não** se cria tela separada.

---

## 2. Vocabulário

| Termo deste SPEC | Significado | Como se chamava antes |
|---|---|---|
| **Regime "do tipo"** (`RegimeAcesso::DoTipo`, `'do_tipo'`) | Os departamentos responsáveis são fixos, configurados no tipo. O registro não é consultado. | "do tipo" |
| **Regime "por registro"** (`RegimeAcesso::PorRegistro`, `'por_registro'`) | Os departamentos são definidos em cada registro (pivô `departamento_<x>`). É o filtro de hoje, inalterado. | **"do autor"** |
| **Departamentos responsáveis** | Os departamentos que respondem por um tipo, no regime "do tipo". | — |
| **Pivô congelado** | Nos 4 tipos "do tipo", `departamento_<x>` deixa de ser lido **e** de ser gravado. Nada é apagado. | — |

**Equivalência a registrar:** o enquadramento e o documento de estado-atual chamavam de **"do autor"** o
regime que este SPEC chama de **"por registro"**. É o mesmo regime, com nome corrigido.

**Por que o nome mudou.** O regime responde **onde mora a verdade do escopo** — no tipo ou no registro.
"Do autor" responde outra pergunta: **como** o escopo chega lá. São eixos ortogonais. Além disso a
premissa não existe: `eventos` **não tem coluna de autor** — o `$fillable` vai de `'titulo'` a `'wp_id'`
([Evento.php:34-38](../../../app/Models/Evento.php#L34-L38)) e a migration
`2026_07_08_000003_create_eventos_table.php` só traz `categoria_evento_id`; não há `user_id` nem
`criado_por_id`. O valor do enum **persiste no banco**: se um dia houver auto-atribuição pelo autor,
`'por_registro'` continua correto (o escopo segue no registro; o autor só passa a preenchê-lo), enquanto
`'do_autor'` estaria errado hoje e ambíguo depois. Renomear valor de enum depois custa migration de dados.

---

## 3. Terreno confirmado por leitura

Tudo abaixo foi verificado no código de `origin/main`. **Docblock não é evidência** — ver §3.7.

### 3.1 O filtro atual (o que a Camada 1 reescreve)

[AutorizaPorDepartamento.php:16-27](../../../app/Policies/Concerns/AutorizaPorDepartamento.php#L16-L27) —
`objetoNoDepartamentoDoUsuario(User $user, TemDepartamento $objeto): bool`. Fail-closed dos dois lados:
usuário sem departamento (`:20-22`, `$idsUsuario === []`) **ou** objeto sem departamento (`:24-26`,
`exists()` falso) ⇒ `false`. São **2 queries por chamada**, sem cache: `pluck('departamentos.id')` (`:18`)
+ `whereIn(...)->exists()` (`:24-26`). A interseção é SQL, não `array_intersect`.

O contrato é [TemDepartamento.php:10-13](../../../app/Models/Contracts/TemDepartamento.php#L10-L13)
(interface pura: `departamentos(): BelongsToMany`). **Não existe trait/concern de departamento nos
models** — cada model redeclara a relação à mão (`AgendaDia.php:66-69`, `Palestra.php:69-72`,
`Post.php:205-208`, `Palestrante.php:55-58`, `Evento.php:96-99`, `User.php:59-62`).

**Usado por 5 policies, em `ver`/`editar`/`excluir`** (15 chamadas, todas confirmadas):

| Policy | ver | editar | excluir | criar |
|---|---|---|---|---|
| [EventoPolicy](../../../app/Policies/EventoPolicy.php) | :36 | :46 | :51 | **:41** |
| [AgendaDiaPolicy](../../../app/Policies/AgendaDiaPolicy.php) | :21 | :31 | :36 | **:26** |
| [PalestraPolicy](../../../app/Policies/PalestraPolicy.php) | :22 | :32 | :37 | **:27** |
| [PalestrantePolicy](../../../app/Policies/PalestrantePolicy.php) | :23 | :33 | :38 | **:28** |
| [PostPolicy](../../../app/Policies/PostPolicy.php) | :21 | :31 | :36 | **:26** |

`EventoPolicy` tem 2 métodos a mais, do **eixo de visibilidade** — `view(?User, Evento)` (`:24-27` →
`$evento->podeSerVistoPor($user)`) e `viewAny(?User)` (`:29-32` → `return true`). **Intocados** (§10).

### 3.2 O furo do `criar` (o que I3 fecha)

**`criar` não usa o trait.** Os 5 são idênticos e não recebem objeto:

```php
// AgendaDiaPolicy.php:24-27 (idem Post:26, Evento:41, Palestra:27, Palestrante:28)
public function criar(User $user): bool
{
    return $user->hasPermissionTo('agenda.criar') && $user->departamentos()->exists();
}
```

`$user->departamentos()->exists()` = **qualquer departamento serve**. Um diretor do DEPRO com
`agenda.criar` cria um AgendaDia (que nasce DED+DECOM, [AgendaConta.php:181-183](../../../app/Livewire/Conta/AgendaConta.php#L181-L183))
e depois **não consegue editá-lo**. Medido em §4.3: são **10 diretores** hoje.

### 3.3 O portão do admin

[AppServiceProvider.php:57-59](../../../app/Providers/AppServiceProvider.php#L57-L59):
`Gate::before(fn (User $usuario) => $usuario->hasRole('administrador') ? true : null)`. É o **único**
`Gate::before`/`Gate::define` do projeto; `config/permission.php:108` tem
`'register_permission_check_method' => false`. **Intocado** (I6).

### 3.4 Listagem escopada e a aba

[AgendaDia.php:55-64](../../../app/Models/AgendaDia.php#L55-L64) — `scopeNoEscopoDe()`:
`whereHas('departamentos', whereIn ids do usuário)`; usuário sem departamento ⇒ `whereRaw('1 = 0')`.
**É o único scope do gênero** — Palestra/Post/Palestrante/Evento não têm.

Consumidores: [AbaAgenda.php:41](../../../app/Support/Conta/AbaAgenda.php#L41) e
[AgendaConta.php:223](../../../app/Livewire/Conta/AgendaConta.php#L223).

[AbaAgenda.php](../../../app/Support/Conta/AbaAgenda.php) — memo por request via `WeakMap` chaveado pelo
`User` (`:26-33`); `calcular()` (`:35-42`) = `checkPermissionTo('agenda.ver')` **e**
`AgendaDia::noEscopoDe($user)->exists()` (a "Fórmula 1" da Fase D).

**3 portões fora de policy**, todos ancorados nesse scope: `ContaController.php:40`
(`abort_unless(AbaAgenda::visivelPara(...), 403)`), `AgendaConta.php:45` (idem, no `mount`) e
`resources/views/components/conta/nav.blade.php:8`. **A lista em `AgendaConta.php:223` não chama
`authorize()` por linha** — a confidencialidade da listagem depende 100% do scope.

### 3.5 Superfície de consumo do filtro

**Nenhum Filament Resource escopa por departamento.** Só 2 sobrescrevem `getEloquentQuery()`, e **nenhum
filtra por departamento**: `UserResource.php:192-196` (só `with(['perfil.media'])`, `:195`) e
`BibliotecaResource.php:40-44` (filtra por `collection_name`, `:43` — filtro de coleção, não de acesso).
**Nenhuma Page tem `canAccess()`.** O painel é admin-only por `User::canAccessPanel` (`User.php:31-36` →
`hasRole('administrador')`) + o `Gate::before`. **Blade não decide capacidade em lugar nenhum.**

⇒ **Fora das policies, o filtro de departamento tem exatamente um consumidor de produção: o
`/minha-conta`.** Isso dimensiona E2 (§9) e é um critério de passe (§11.5).

### 3.6 O hardcode a matar

[AgendaMantenedores.php:17](../../../app/Support/Agenda/AgendaMantenedores.php#L17) —
`public const SIGLAS = ['DED', 'DECOM'];`; `ids()` resolve por sigla. Usos: `AgendaConta.php:182` e
`tests/Feature/Conta/AbaAgendaTest.php:44`.

### 3.7 `hasPermissionTo` × `checkPermissionTo` (corrige a premissa do enquadramento)

**`AbaAgenda` não tem catch.** [AbaAgenda.php:37](../../../app/Support/Conta/AbaAgenda.php#L37) usa
`checkPermissionTo`, e o try/catch real vive no spatie
(`vendor/spatie/laravel-permission/src/Traits/HasPermissions.php:260-267`: `try { return
$this->hasPermissionTo(...) } catch (PermissionDoesNotExist $e) { return false; }`). O docblock em
[AbaAgenda.php:18-22](../../../app/Support/Conta/AbaAgenda.php#L18-L22) descreve o **efeito**, não o
mecanismo — e induziu ao erro. **E2 corrige esse docblock** (§9.4).

A escolha é **por contexto**, não regra fixa — e é o que o código já faz:

- **Policy ⇒ `hasPermissionTo`** (lança `PermissionDoesNotExist` se a capacidade não existe no catálogo;
  explodir é o certo — é bug de seeder, não decisão de acesso).
- **Sinal de UI/nav ⇒ `checkPermissionTo`** (fail-closed; nunca derrubar a nav). Assim já fazem
  `AbaAgenda.php:37` e `AgendaConta.php:67,73,79,174`.
- **`can()` com nome cru de permissão: proibido, absoluto** (`register_permission_check_method` OFF ⇒ o
  nome cru não é ability de Gate e fura o escopo).
- Para a **config nova**, o equivalente do fail-closed é o **I2**: recurso sem linha ⇒ nega, não explode.

### 3.8 O campo `departamentos` nos forms

**Não existe "fonte única `App\Filament\Schemas\*Form`" para os 4 tipos.** Só `AgendaDiaForm` e
`EventoForm` são Schemas; os outros têm `Select` **inline no Resource**:

| Onde | Linhas | `required()`? | Destino em E2 |
|---|---|---|---|
| [AgendaDiaForm.php](../../../app/Filament/Schemas/AgendaDiaForm.php) | :64-72, atrás de `schema(bool $comDepartamentos = true)` (`:26`) | sim (`:71`) | **sai** (e some o parâmetro) |
| [PalestraResource.php](../../../app/Filament/Resources/Palestras/PalestraResource.php) | :158-164 (inline) | sim (`:164`) | **sai** |
| [PostResource.php](../../../app/Filament/Resources/Posts/PostResource.php) | :229-235 (inline) | sim (`:235`) | **sai** |
| [PalestranteResource.php](../../../app/Filament/Resources/Palestrantes/PalestranteResource.php) | :126-135 (inline) | sim (`:134`) | **sai a `Section::make('Departamentos')` inteira** — o Select é o único filho |
| [EventoForm.php](../../../app/Filament/Schemas/EventoForm.php) | :107-112 ("Departamentos organizadores") | **não** | **permanece** (é "por registro") |
| [UserResource.php](../../../app/Filament/Resources/Users/UserResource.php) | :107-111 (vínculo `departamento_usuario`) | **não** | **não tocar** — é o lado do usuário, que a Camada 1 segue lendo |

O site já chama `AgendaDiaForm::schema(comDepartamentos: false)`
([AgendaConta.php:58](../../../app/Livewire/Conta/AgendaConta.php#L58)).

### 3.9 A tela que hospeda a config

[MatrizCapacidades.php](../../../app/Filament/Pages/MatrizCapacidades.php) — `mount()` (`:43-58`, monta
`$estado[papel][recurso][acao]` + `form->fill`); `form()` (`:60-65`, `statePath('data')`);
`secoesPorRecurso()` (`:68-89`, uma `Section` por recurso, `Toggle::make("{$papel}.{$recurso}.{$acao}")`
— dot-notation aninhado, provado no PR #27); `content()` (`:91-105`, `Form` + `EmbeddedSchema` + footer
com `Action` submit `'salvar'`); `salvar()` (`:107-134`, `findByName` + `syncPermissions` + auditoria).
View: `resources/views/filament/pages/matriz-capacidades.blade.php` (3 linhas, `{{ $this->content }}`).

### 3.10 Auditoria — o helper e uma armadilha

[AuditoriaAutorizacao.php](../../../app/Support/Autorizacao/AuditoriaAutorizacao.php) — `LOG='autorizacao'`
(`:15`); `porta()` (`:27-30`); `contexto()` (`:33-42`, porta+ip+user_agent); `diff()` (`:45-51`, diff de
nomes); `registrarPapelCapacidades()` (`:54-57`); `registrarDepartamentosUsuario()` (`:71-88` — **molde do
diff por id com itens `{id,nome}`**, estável a rename); `registrarDepartamentosConteudo()` (`:97-108`,
`logName: 'agenda'`); `registrar()` privado (`:111-122`).

> ⚠️ **Armadilha do `registrar()`.** `:113-115` faz `if (empty($diff['adicionados']) &&
> empty($diff['removidos'])) return;`. Um diff de **outro formato** (ex.: `['regime' => ..., 'departamentos'
> => ...]`) cai nesse `empty()` e vira **no-op silencioso** — a mudança seria "auditada" sem gravar nada.
> Por isso §9.5 usa **dois métodos**, cada um no formato `{adicionados, removidos}` que o privado já
> entende, sem tocar no privado.

**Precedente ruim a não seguir:** `ConfiguracoesBlog::salvar()` (`:72`, sobre `Configuracao::definir`)
**não audita nada**. A config nova é núcleo de autorização e audita (I7).

### 3.11 Glossário, seeders e colisão de nomes

[GlossarioCapacidades.php](../../../app/Support/Autorizacao/GlossarioCapacidades.php) — `RECURSOS` (`:13`:
`['evento','palestra','post','agenda','palestrante']`), `ACOES` (`:15`), `RECURSOS_ROTULOS` (`:18-24`),
`permissions()` (`:35-45`, os 20 nomes). O comentário `:17` já avisa: **slug ≠ model em `'agenda'` →
`AgendaDia` e `'palestrante'` → `Palestrante`**. **Não existe mapa slug→model** (§9.2 cria).

[CapacidadesSeeder](../../../database/seeders/CapacidadesSeeder.php) — molde do seeder idempotente
(`updateOrCreate` sobre o glossário). `DatabaseSeeder::run()` chama, nesta ordem: `CategoriaSeeder`,
`EstruturaCemaSeeder` (papéis + departamentos), `CapacidadesSeeder`, `AdminSeeder`, `CategoriaEventoSeeder`.
Nos testes: `EstruturaCemaSeeder` em 39 pontos, `CapacidadesSeeder` em 19. **Não existe factory de
departamento** — os testes usam o seeder ou `Departamento::create` (`EventoPolicyCapacidadeTest.php:49`).

**Colisão verificada — 52 tabelas no dev.** Ocupados: `configuracoes` ([Configuracao](../../../app/Models/Configuracao.php),
chave/valor, `valor()`/`definir()`), `agenda_configuracoes` ([ConfiguracaoAgenda](../../../app/Models/ConfiguracaoAgenda.php),
singleton) e os 6 pivôs `departamento_{evento,usuario,palestra,post,palestrante,agenda_dia}`.
**`tipos_conteudo`, `TipoConteudo` e `tipo_conteudo` não existem em lugar nenhum do repo.**

Molde do pivô (`2026_07_11_000005_create_departamento_agenda_dia_table.php`) — **tem `id()` próprio**,
o que é a origem da armadilha do `pluck` (§12.1):

```php
Schema::create('departamento_agenda_dia', function (Blueprint $table) {
    $table->id();
    $table->foreignId('agenda_dia_id')->constrained('agenda_dias')->cascadeOnDelete();
    $table->foreignId('departamento_id')->constrained('departamentos')->cascadeOnDelete();
    $table->unique(['agenda_dia_id', 'departamento_id']);
});
```

Molde de enum: [VisibilidadeEvento.php](../../../app/Enums/VisibilidadeEvento.php) — backed string com
`rotulo()` e `opcoes()` para o Select do Filament. É o único enum do projeto.

### 3.12 Leitura pública do pivô — só o Evento

Verificado em views, controllers, Livewire, feeds/ics/sitemap, mails e rotas: **só o Evento exibe
departamento publicamente** — `resources/views/eventos/_servico.blade.php:35`
(`$evento->departamentos->pluck('sigla')`), com eager-load em `EventoController.php:39` (a linha `:27`
do index é morta). **Palestra, Post, AgendaDia e Palestrante: zero leitura pública do pivô.**

⇒ Tirar o campo do form nos 4 **não quebra exibição nenhuma**. E o único que exibe é justamente o que
mantém o campo.

---

## 4. Medições no dev (banco real, somente leitura, 16/07/2026)

### 4.1 Registros com zero departamento

| Tipo | Total | Com departamento | **Zero departamento** |
|---|---|---|---|
| AgendaDia | 123 | 123 | **0** |
| Palestra | 127 | 127 | **0** |
| Post | 45 | 45 | **0** |
| Palestrante | 59 | 59 | **0** |
| Evento | **56** | 49 | **7** |

⇒ **I9 (§7) alarga exatamente 0 registros hoje.** O alargamento é regra para o futuro, não risco de
migração. Continua sendo decisão escrita + teste.

⇒ **Correção do enquadramento:** são **56** eventos (não 54) e **7 têm zero departamento** (a afirmação
"nenhum com 0" era expectativa, não medição). Ciência em §13.2.

### 4.2 Divergência da semente: nenhuma

Combinação **exata** de departamentos, por tipo:

| Tipo | Combinação | Registros | Semente proposta | Divergência |
|---|---|---|---|---|
| AgendaDia | `DECOM+DED` | 123 / 123 | DED + DECOM | **nenhuma** |
| Palestra | `DED` | 127 / 127 | DED | **nenhuma** |
| Post | `DECOM` | 45 / 45 | DECOM | **nenhuma** |
| Palestrante | `DED` | 59 / 59 | DED | **nenhuma** |
| Evento | 12 combinações distintas | 49 / 56 | *(por registro)* | n/a |

⇒ **No cutover dos 4 tipos, ninguém ganha nem perde acesso.** O risco de E2 está **todo no código**
(o filtro), **nenhum nos dados**.

**Por que bate 100%:** o pivô dos 4 tipos **nunca carregou decisão editorial**. Só
[ImportadorEventos.php:118](../../../app/Importacao/ImportadorEventos.php#L118) vincula departamento na
importação — `ImportadorAgenda`, `ImportadorBlog` e `ImportadorPalestras` têm **zero** menção a
departamento. Todo o pivô dos 4 tipos é artefato do backfill `cema:departamentalizar-conteudos`
([DepartamentalizarConteudos.php:43-51](../../../app/Console/Commands/DepartamentalizarConteudos.php#L43-L51)):
ninguém **nunca** escolheu o departamento de uma palestra. **Congelar o pivô não descarta informação —
para de ler um eco do backfill.** É a justificativa central do congelamento (§6.4).

### 4.3 O furo do `criar`, medido (o cenário de I3)

| Medida | Valor |
|---|---|
| Usuários com `agenda.criar` via papel | 78 (29 diretores + 49 trabalhadores) |
| Destes, vinculados a algum departamento ⇒ **criam hoje** | 16 |
| Destes, em DED ou DECOM ⇒ **conseguem editar o que criam** | 6 |
| **Criam e não conseguem editar** | **10** |

Os 10: Charles (DEPAE), Cris (DIJ), Iara (DIJ), Daniel (DDA), Marcio (DDA), Marli (DDA), Maria Aparecida
(DEPRO), Emanuela (DEPRO), Gaspar (DEMAPA), Salvador (DEMAPA). **I3 tira o `criar` desses 10** — é a
correção desejada. O teste de I3 usa esse cenário real (§10.3).

### 4.4 Contagens de referência

- **Departamentos:** 8 — DAS(1), DDA(2), DED(3), DEMAPA(4), DEPAE(5), DEPRO(6), DIJ(7), DECOM(8).
- **`departamento_usuario`:** **30 vínculos / 16 usuários / 8 departamentos** (DAS=**2**) — e não
  "15 / 7 / DAS=0". O cutover do **dev** da Fase D já rodou: 15 (backfill dos diretores) + 8 (Elizabete,
  sem vínculo antes) + 7 (Aury, que já tinha DEPAE) = 30; 15 usuários + Elizabete = 16.
- **Todos os 16 vinculados são `diretor`.** Nenhum trabalhador está em `departamento_usuario` — confirma
  §13.4.
- **Papéis:** frequentador (nivel 10, 69 usuários), trabalhador (20, 49), diretor (30, 29),
  administrador (100, 1).
- **`role_has_permissions` no dev:** `diretor` e `trabalhador` têm `agenda.{ver,criar,editar,excluir}`
  ligados (cutover do dev da Fase D). Os outros 16 nomes estão desligados.
- **Suíte:** 798/798 (2418 assertions) medidos no commit `6c3cdff` — fonte:
  `.superpowers/sdd/progress.md:30`. O `ROADMAP.md:149` ainda diz "252 testes PHP + 5 JS" (defasado).

---

## 5. Decisões travadas (não reabrir)

1. **Escopo:** só "quem edita / é responsável" por tipo. Visibilidade pública fica fora (Camada 4).
2. **Forma:** integrada à `MatrizCapacidades`, que vira **"Configuração de acesso por tipo"**. Um lugar
   só por tipo. Sem tela separada.
3. **Regimes:** "do tipo" (Agenda, Palestra, Palestrante, Post) e "por registro" (só o Evento). **O
   regime é escolhido na tela, não hardcoded.**
4. **O campo "Departamentos" por objeto some** dos 4 tipos "do tipo" — no `/admin` **e** no site. Fica só
   no Evento. **Consequência aceita:** morre a exceção por objeto (a ideia da Fase C de "DECOM edita
   palestras dando DECOM como 2º depto àquela palestra"). Agora é config do tipo inteiro.
5. **Semente = o que cada tipo já tem hoje:** Agenda = DED+DECOM · Palestra = DED · Palestrante = DED ·
   Post = DECOM · Evento = por registro. Ninguém começa do zero. **Medição confirma 100%** (§4.2).
6. **Split em dois PRs** (E1 → E2), §8.
7. **Pivô congelado** nos 4 tipos: nem lido, nem gravado. **Nada é apagado** (§6.4).
8. **Rótulo do regime:** "Em cada registro" (§2).
9. **Nome:** `TipoConteudo` / `tipos_conteudo` / `departamento_tipo_conteudo` (§6.1).
10. **A aba não consulta registro** (§6.3).
11. Substitui o `AgendaMantenedores` hardcoded do D2.

---

## 6. Decisões de desenho

### 6.1 O nome e a segunda lista de recursos

Model `TipoConteudo`, tabela `tipos_conteudo`, pivô `departamento_tipo_conteudo` com FK — o pivô segue o
padrão `departamento_<x>` dos 6 existentes. Sem colisão (§3.11).

**Bônus do eixo:** quando a Camada 4 pedir "publicar separado de editar", é **coluna nova em
`tipos_conteudo`** — não outra tabela.

> ⚠️ **Ressalva obrigatória — a segunda lista.** `tipos_conteudo` terá 1 linha por recurso, espelhando
> `GlossarioCapacidades::RECURSOS`, que é a fonte única do catálogo hoje. **É assim que nasce divergência
> silenciosa.** Cravado, e cada item vira teste (§10.2):
>
> **(a)** O **glossário** segue a fonte única do catálogo de recursos.
> **(b)** `tipos_conteudo.recurso` é **unique** e só aceita slug existente no glossário.
> **(c)** O seeder é **idempotente** (`updateOrCreate` por `'recurso'`, molde do `CapacidadesSeeder`) e
> semeia **todos** os recursos do glossário.
> **(d)** Recurso do glossário **sem linha** na tabela ⇒ **nega** (fail-closed), não explode. É o **I2**.

### 6.2 A pergunta única

"Sou responsável pelo tipo X?" passa a ser a **mesma pergunta em três lugares** — a aba, o `criar` (I3) e
o filtro. **Três implementações da mesma pergunta é como nasce divergência de acesso.** Portanto: **uma
função, um lugar só** — `AcessoPorTipo::usuarioHabilitadoNoTipo()` (§9.3), em
`App\Support\Autorizacao`. Nenhum outro ponto do código responde essa pergunta por conta própria.

### 6.3 A aba não consulta registro

`AbaAgenda` passa a ser **capacidade + a pergunta única**, sem `AgendaDia::noEscopoDe($user)->exists()`.

A "Fórmula 1" da Fase D existia porque `agenda.ver` é global por papel e a aba apareceria vazia para todo
trabalhador/diretor. **"Sou responsável" já resolve isso** (só DED/DECOM). Manter a query paga 1 query em
**toda** página `/minha-conta` por um benefício que a config já entrega, **e** perpetua o furo do 1º
registro (com a tabela vazia a aba some e ninguém entra para criar o primeiro dia).

Custo aceito: um responsável com só `agenda.ver` vê aba vazia — mas só se a Agenda **inteira** estiver
vazia (há 123 registros, e a listagem no "do tipo" é tudo-ou-nada). Estado vazio honesto resolve.

> **Ciência — a Fórmula 1 morre também no "por registro".** No ramo `PorRegistro`,
> `usuarioHabilitadoNoTipo` resolve para `$user->departamentos()->exists()` (§9.3) — **qualquer**
> departamento. Se um dia a Agenda for trocada para "por registro" **pela tela** (o regime é configurável,
> §5.3), a aba volta a aparecer para todo diretor vinculado, possivelmente **vazia** — exatamente o que a
> Fórmula 1 evitava. Hoje é inócuo (`agenda` = "do tipo"). **Registrado; não muda o desenho.**

### 6.4 Pivô congelado, dado intacto

Nos 4 tipos "do tipo", `departamento_<x>` deixa de ser **lido** e de ser **gravado**.

- **Não** remover as tabelas nem as relações `departamentos()` dos models — o contrato `TemDepartamento`
  continua (o Evento o usa de verdade; nos 4, a relação segue existindo e íntegra).
- **Migration destrutiva é proibida.** Nada é apagado.
- **Justificativa:** o pivô dos 4 nunca carregou decisão editorial — é 100% eco do backfill (§4.2).

**Consequência no Evento:** `departamento_evento` deixa de ser "dado de autorização de um regime único" e
passa a ser explicitamente **dado editorial de exibição** (é a única leitura pública do repo, §3.12) **e**
o escopo do regime "por registro". Sua ausência (permitida pelo form, §3.8) continua significando
fail-closed — ver §13.2.

### 6.5 Memo, não cache — e `scoped`, não `singleton`

O lookup da config entra no caminho de **toda** checagem de policy. **Memo por escopo; jamais cache
persistente** — invalidação stale em config de acesso é furo de segurança que ninguém vê.

Implementação: `AcessoPorTipo` registrado como **`scoped`** no container
(`$this->app->scoped(AcessoPorTipo::class, fn () => new AcessoPorTipo)`), **nunca `singleton`**.

> ⚠️ **Por que não `singleton`.** Em HTTP e nos testes o container já morre a cada request/teste, e os dois
> seriam equivalentes. **Mas a stack fixa tem um `worker`** (`cema-worker`, `docker-compose.yml:41-45`,
> rodando `queue:work`): ali o container **não** é reconstruído entre jobs — o worker chama
> `forgetScopedInstances()` (`vendor/laravel/framework/src/Illuminate/Queue/QueueServiceProvider.php:263`),
> que **esquece `scoped` e preserva `singleton`** (`Container.php:1719-1729` percorre só
> `$this->scopedInstances`). Com `singleton`, o memo viraria **cache persistente de config de acesso dentro
> do worker**, com janela stale de até o `--max-time` do processo — exatamente o furo que este parágrafo
> proíbe. `scoped` dá a semântica desejada: memo por request **e** por job.

Sem estado `static`, que vazaria entre testes. (O molde `WeakMap` de `AbaAgenda:26-33` é chaveado por
`User`; aqui a chave é o recurso, e o `scoped` é mais simples.) **Não há `Cache::` em `app/` hoje** — e não
passa a haver.

### 6.6 O que sobra do log depto↔conteúdo

Com o pivô congelado, o `sync` some do create da Agenda e a chamada
[AgendaConta.php:186-187](../../../app/Livewire/Conta/AgendaConta.php#L186-L187)
(`registrarDepartamentosConteudo`, `log_name: 'agenda'`) deixa de ter o que logar. **O helper fica**
([AuditoriaAutorizacao.php:97-108](../../../app/Support/Autorizacao/AuditoriaAutorizacao.php#L97-L108)) —
o Evento ainda usa esse eixo. **Apagar a chamada, não o helper.**

### 6.7 Troca de regime futura (deixar possível, não implementar)

O Post é o caso certo: hoje é só a Sementeira (DECOM), mas vão migrar Evangelho (DED) e
Mensagens/Vibrações (DEPAE); então Post terá de virar "por registro" (ou ganhar tipos próprios). Com o
pivô congelado, os posts criados no período "do tipo" **nascem sem pivô** ⇒ a troca de regime exigirá um
comando `cema:*` de backfill. **Isso é aceito.** O SPEC só precisa **não impedir** a troca: por isso o
regime é dado (não constante), o trait trata os dois ramos, e o scope trata os dois ramos.

### 6.8 A tela: rota estável, título novo

A `MatrizCapacidades` mantém a **classe** e o **slug** (`matriz-capacidades`) — trocar a rota quebraria
links e não traz benefício. Mudam `$title` e `$navigationLabel` para **"Configuração de acesso por
tipo"**. O arquivo e a rota continuam onde estão.

---

## 7. Invariantes de segurança (cada um vira teste que reprova)

| # | Invariante | Teste (§10) |
|---|---|---|
| **I1** | **Fail-closed do tipo:** tipo "do tipo" com **nenhum** departamento responsável ⇒ **nega tudo** (só admin, via `Gate::before`). Config vazia nunca permite. | §10.3 |
| **I2** | **Tabela não semeada** (ambiente/teste sem seeder) ⇒ **nega e não explode**. O que falta no catálogo **fecha a porta**, não quebra a página (é o "O3" da Fase B: semear antes de consultar, e testar o caminho sem semente). | §10.3 |
| **I3** | **Criar no "do tipo":** `hasPermissionTo('x.criar')` **E** usuário ∈ departamentos responsáveis do tipo. Fecha o furo de §3.2/§4.3. | §10.3 |
| **I4** | **"Por registro" (Evento): a REGRA de escopo é INALTERADA** — dado `evento` configurado como "por registro", o filtro é o de hoje (objeto ∈ deptos do usuário; criar = tem algum depto), byte a byte no ramo `PorRegistro`. **Ressalva de I2:** como todo recurso, o Evento passa a exigir a linha `tipos_conteudo.recurso='evento'`; sem ela, nega os 4 verbos (fail-closed). **"Inalterado" = a regra do ramo, não a independência de config.** **Não** implementar "nasce no depto do autor". | §10.3 |
| **I5** | Posse de permissão **nunca** via `can()` com nome cru. `hasPermissionTo` em policy / `checkPermissionTo` em sinal de UI — **por contexto** (§3.7). | §10.2 |
| **I6** | Admin nunca chega às capacidades (passa antes no `Gate::before`). **Não mexer.** | §10.3 |
| **I7** | **Mudar a config = mudar quem-pode-o-quê ⇒ auditar** como a matriz: `log_name` `'autorizacao'`, diff `{id,nome}` para deptos e diff de nomes para regime, via o helper. **Não é opcional.** | §10.2 |
| **I8** | **Escritor único:** a matriz segue única escritora de `role_has_permissions`; **a tela é a única escritora da config**. O seeder é **insert-only** — cria a linha ausente com a semente e **nunca** altera regime ou responsáveis de linha existente; reexecutar `db:seed` **preserva integralmente** a config feita na tela (§9.1). **Ninguém escreve a config por efeito colateral:** `departamento_tipo_conteudo` usa `restrictOnDelete` no `departamento_id` — remover um responsável só pela tela. **Nada de comando `cema:*` escrevendo config.** | §10.2 |
| **I9** | **Alargamento consciente:** no "do tipo" **o objeto não é consultado** ⇒ um registro **sem departamento** (hoje: só admin) passa a ser **editável pelos responsáveis do tipo**. É **desejado** (o objeto não tem escopo próprio nesse regime) e dissolve o furo do "1º registro num depto sem nenhum". **Alarga 0 registros hoje** (§4.1), mas é decisão escrita + teste — não efeito colateral silencioso. "O objeto não é consultado" é testado nos **três** estados do pivô: vazio, disjunto do usuário e coincidente com o usuário. | §10.3 |

---

## 8. O split

### E1 — Fundação. **Comportamento-neutro em acesso.**

**Entrega:** modelo de dados da config · enum `RegimeAcesso` · mapa canônico recurso↔model · seeder
idempotente com a semente · UI na matriz (regime + multiselect por Section) · auditoria da config ·
serviço `AcessoPorTipo` (escrito e testado, **mas ninguém o chama ainda**).

**Não toca:** policies, trait, scope, forms dos conteúdos, `AgendaConta`, `AgendaMantenedores`, `AbaAgenda`.

**Critério de aceite:** a suíte fica verde (**798 + novos**) e **nenhuma ASSERÇÃO de teste existente muda
de cor**. A única alteração permitida em teste existente no E1 é somar `$this->seed(TiposConteudoSeeder::class)`
ao `setUp` dos **dois testes da própria tela** (§10.2) — mudança de *setup*, não de cor. **Qualquer outra
mudança em teste existente ⇒ o E1 está errado.**

> ⚠️ Por que os dois: o `Select` de regime é `required()` (§9.5) e entra no schema que `salvar()` valida
> via `$this->form->getState()` (`MatrizCapacidades.php:109` → `schemas/src/Concerns/HasState.php:450`,
> `$state = $this->validate()`).
> Com `tipos_conteudo` vazia, `mount()` preenche `regime => null` ⇒ o `required` reprova o submit ⇒ caem os
> `assertHasNoFormErrors()` existentes. Isso é **descoberta do passe adversarial**, não escolha: sem essa
> ressalva, o critério "798 verde" seria falso e mandaria o executor procurar um bug que não existe.

### E2 — Troca do filtro. **É onde o acesso muda.**

**Entrega:** trait com os dois caminhos lendo a config · `criar` por regime (I3) · scope do "do tipo" ·
`AbaAgenda` sem a query · campo "Departamentos" sai dos 4 forms (no Palestrante, a `Section` inteira) ·
`AgendaConta` para de forçar DED+DECOM · `AgendaMantenedores` **deletado** · **docblock de `AbaAgenda`
reescrito (`:11-23`)** · reescrita dos testes afetados (§10.4).

---

## 9. As peças

### 9.1 Modelo de dados (E1)

Duas migrations incrementais (**nunca** `migrate:fresh`/`refresh`/`wipe`/`reset`):

```php
// create_tipos_conteudo_table
Schema::create('tipos_conteudo', function (Blueprint $table) {
    $table->id();
    $table->string('recurso')->unique();   // slug do GlossarioCapacidades::RECURSOS (6.1.b)
    $table->string('regime');              // RegimeAcesso (enum PHP; string no banco)
    $table->timestamps();
});

// create_departamento_tipo_conteudo_table  (molde: departamento_agenda_dia, §3.11 — MAS ver a nota da FK)
Schema::create('departamento_tipo_conteudo', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tipo_conteudo_id')->constrained('tipos_conteudo')->cascadeOnDelete();
    // NÃO é cascade (diverge do molde dos 6 pivôs, de propósito): esta é tabela de AUTORIZAÇÃO.
    // Cascade faria do DELETE do DepartamentoResource um SEGUNDO escritor da config — sem passar
    // pela tela e sem trilha, furando I7 e I8.
    $table->foreignId('departamento_id')->constrained('departamentos')->restrictOnDelete();
    $table->unique(['tipo_conteudo_id', 'departamento_id']);
});
```

> ⚠️ **Não superestimar o `restrict`.** O efeito do cascade seria **fail-closed** — o tipo ficaria sem
> responsáveis e I1 negaria tudo (indisponibilidade), **não** acesso indevido. O `restrict` protege a
> **trilha de auditoria** e contra **lockout silencioso**; **não** é barreira contra furo de acesso. Sem
> ele o sistema **não abre** — trava. A divergência do molde está justificada por isso, e custa as três
> peças abaixo.

**O lado da UX do `restrict`** (E1, para a FK não virar erro 500): no `DepartamentoResource` — `:144`
(`DeleteAction`) e `:148` (`DeleteBulkAction`) — e no `EditDepartamento` (`:16`), a ação ganha `->before()`
que checa a inversa e **cancela**:

- **`$action->cancel()`** (`vendor/filament/actions/src/Action.php:677`, lança `Cancel`) — **não**
  `halt()` (`:682`), que manteria o modal de confirmação aberto. **`Notification` sozinha NÃO aborta**: o
  delete prosseguiria e estouraria a FK como 500 — exatamente o que o `->before()` existe para evitar.
- ⚠️ **Assinaturas diferentes:** `DeleteAction::before()` recebe **`$record`** (Model);
  `DeleteBulkAction::before()` recebe **`$records`** (Collection) e precisa **varrer a coleção**. Não
  copiar o mesmo closure para os dois.
- Mensagem em pt-BR: *"O departamento {sigla} responde pelo tipo {recurso}. Remova-o em Configuração de
  acesso por tipo antes de excluir."*

> **Ciência:** `departamento_usuario` segue com `cascadeOnDelete` e a mesma lacuna — está em §14 (fora de
> escopo) e continua. A divergência de FK entre os dois pivôs é **consciente**.

`App\Models\TipoConteudo`: `$fillable = ['recurso', 'regime']`, `$casts = ['regime' => RegimeAcesso::class]`,
`departamentos(): BelongsToMany` (`'departamento_tipo_conteudo', 'tipo_conteudo_id', 'departamento_id'`).
**Não** implementa `TemDepartamento` — não é conteúdo, e o contrato existe para o trait de policy.

**A inversa, em `App\Models\Departamento`** (peça obrigatória — hoje o model só tem `setores()` (`:20`),
`cargos()` (`:25`) e `eventos()` (`:30`); **sem ela o `->before()` abaixo é erro fatal**):

```php
public function tiposConteudo(): BelongsToMany
{
    return $this->belongsToMany(TipoConteudo::class, 'departamento_tipo_conteudo',
        'departamento_id', 'tipo_conteudo_id');
}
```

`App\Enums\RegimeAcesso` (molde: `VisibilidadeEvento`):

```php
enum RegimeAcesso: string
{
    case DoTipo = 'do_tipo';
    case PorRegistro = 'por_registro';

    public function rotulo(): string
    {
        return match ($this) {
            self::DoTipo => 'Departamentos fixos do tipo',
            self::PorRegistro => 'Departamentos definidos em cada registro',
        };
    }

    /** Mapa value => rótulo, para o Select do Filament. */
    public static function opcoes(): array { /* idem VisibilidadeEvento::opcoes() */ }
}
```

`Database\Seeders\TiposConteudoSeeder` — idempotente, molde do `CapacidadesSeeder`, semeia **todos** os
recursos do glossário (6.1.c) resolvendo departamento por **sigla**:

```php
private const SEMENTE = [
    'agenda'      => ['regime' => RegimeAcesso::DoTipo,      'siglas' => ['DED', 'DECOM']],
    'palestra'    => ['regime' => RegimeAcesso::DoTipo,      'siglas' => ['DED']],
    'palestrante' => ['regime' => RegimeAcesso::DoTipo,      'siglas' => ['DED']],
    'post'        => ['regime' => RegimeAcesso::DoTipo,      'siglas' => ['DECOM']],
    'evento'      => ['regime' => RegimeAcesso::PorRegistro, 'siglas' => []],
];
```

Para cada recurso de `GlossarioCapacidades::RECURSOS`, o seeder é **insert-only** (I8): cria a linha
ausente com a semente e **nunca** toca em regime ou responsáveis de linha existente — a tela é a dona da
config. **Recurso do glossário ausente da semente ⇒ falha explícita no seeder** (é bug de catálogo, e o
seeder é o lugar de explodir — não a autorização).

```php
foreach (GlossarioCapacidades::RECURSOS as $recurso) {
    $semente = self::SEMENTE[$recurso] ?? throw new RuntimeException(
        "Recurso '{$recurso}' do glossário sem semente em TiposConteudoSeeder."
    );

    $tipo = TipoConteudo::firstOrCreate(['recurso' => $recurso], ['regime' => $semente['regime']]);

    // Insert-only: linha existente é config da tela (I8) — o seeder NÃO a reescreve.
    if ($tipo->wasRecentlyCreated) {
        $tipo->departamentos()->sync(
            Departamento::whereIn('sigla', $semente['siglas'])->pluck('departamentos.id')->all()
        );
    }
}
```

> ⚠️ **Por que não `updateOrCreate` + `sync` incondicional** (como no `CapacidadesSeeder`): lá o seeder
> semeia **catálogo** (as 20 permissions), que a tela não edita. Aqui a tabela guarda **decisão de acesso**,
> que a tela edita. Um `sync` incondicional faria `db:seed` **desfazer silenciosamente** a configuração do
> admin — o seeder viraria um segundo escritor, furando I8. A distinção catálogo × decisão é a mesma que o
> `CapacidadesSeeder` já estabelece ao **não** atribuir permissions a papéis.

Encadeamento: `DatabaseSeeder::run()` chama `TiposConteudoSeeder` **depois** de `EstruturaCemaSeeder`
(precisa dos departamentos). Nos testes é **explícito** — quem precisa de autorização semeia (o I2 exige
que dê para **não** semear).

> **Consequência a registrar:** como o seeder não reconcilia, corrigir a semente depois de a linha existir
> exige a tela. É o comportamento desejado (I8).

### 9.2 O mapa canônico recurso↔model (E1)

As policies hardcodam a string (`'agenda.ver'`) e o glossário só tem slugs e rótulos. **Não existe mapa
slug→model**, e slug ≠ model em 2 casos (`'agenda'` → `AgendaDia`, `'palestrante'` → `Palestrante`).

Em `GlossarioCapacidades` (fonte única do vocabulário de capacidade — é o lugar):

```php
/** Mapa canônico recurso => model. Slug ≠ model em 'agenda' e 'palestrante' (ver :17). */
public const RECURSOS_MODELS = [
    'evento' => Evento::class,
    'palestra' => Palestra::class,
    'post' => Post::class,
    'agenda' => AgendaDia::class,
    'palestrante' => Palestrante::class,
];
```

**Onde a policy pega seu recurso:** ela **declara**. No `criar` **não há objeto** — logo o trait não pode
derivar o recurso do model, e a única forma que serve aos 4 métodos é a policy declarar:

```php
// AgendaDiaPolicy
protected function recurso(): string { return 'agenda'; }
```

É o **mesmo literal** que a policy já hardcoda em `'agenda.ver'` — zero divergência nova. O mapa amarra os
dois lados por teste (§10.2): para cada policy, `recurso()` ∈ `RECURSOS` e `RECURSOS_MODELS[recurso()]` é
o model que ela autoriza.

### 9.3 O serviço — a pergunta única (E1)

`App\Support\Autorizacao\AcessoPorTipo`, **`scoped`** (`AppServiceProvider::register` — `scoped`, **não**
`singleton`: ver §6.5):

```php
final class AcessoPorTipo
{
    /** Memo por escopo (request ou job) — o scoped morre com o escopo; nunca static, que vaza entre testes. */
    private array $memo = [];

    private function tipo(string $recurso): ?TipoConteudo
    {
        // array_key_exists (e não ??=) para memoizar TAMBÉM o null — senão o caminho I2 refaz a query
        // a cada checagem de policy.
        if (! array_key_exists($recurso, $this->memo)) {
            $this->memo[$recurso] = TipoConteudo::with('departamentos')
                ->where('recurso', $recurso)->first();
        }

        return $this->memo[$recurso];
    }

    public function regime(string $recurso): ?RegimeAcesso
    {
        return $this->tipo($recurso)?->regime;   // null = recurso sem linha (I2)
    }

    /** @return list<int> ids responsáveis; [] se tipo ausente ou sem responsáveis (I1/I2). */
    public function departamentosResponsaveis(string $recurso): array
    {
        return $this->tipo($recurso)?->departamentos->pluck('id')->all() ?? [];
    }

    /** A PERGUNTA ÚNICA (§6.2): o usuário está habilitado a tocar neste tipo? Fail-closed. */
    public function usuarioHabilitadoNoTipo(User $user, string $recurso): bool
    {
        return match ($this->regime($recurso)) {
            RegimeAcesso::DoTipo => $this->usuarioResponsavel($user, $recurso),
            RegimeAcesso::PorRegistro => $user->departamentos()->exists(),   // I4: inalterado
            null => false,                                                   // I1/I2
        };
    }

    private function usuarioResponsavel(User $user, string $recurso): bool
    {
        $ids = $this->departamentosResponsaveis($recurso);

        if ($ids === []) {
            return false;   // I1: config vazia nunca permite
        }

        // 'departamentos.id' QUALIFICADO: o pivô tem id próprio (§12.1).
        return $user->departamentos()->whereIn('departamentos.id', $ids)->exists();
    }
}
```

> ⚠️ **Não usar `$this->memo[$recurso] ??= ...`**: `??=` reavalia quando o valor é `null`, então um recurso
> ausente refaz a query a cada checagem. Seria **correto** (fail-closed) mas desperdiça query exatamente no
> caminho I2. Daí o `array_key_exists` acima.

### 9.4 O trait e as policies (E2)

```php
trait AutorizaPorDepartamento
{
    /** Slug do recurso deste tipo no GlossarioCapacidades (o mesmo de 'x.ver'). */
    abstract protected function recurso(): string;

    /** ver/editar/excluir: o escopo depende do regime. */
    protected function noEscopo(User $user, TemDepartamento $objeto): bool
    {
        $acesso = app(AcessoPorTipo::class);

        return match ($acesso->regime($this->recurso())) {
            RegimeAcesso::DoTipo => $acesso->usuarioHabilitadoNoTipo($user, $this->recurso()),
            RegimeAcesso::PorRegistro => $this->objetoNoDepartamentoDoUsuario($user, $objeto),
            null => false,   // I1/I2
        };
    }

    /** criar: não há objeto — a pergunta é sempre sobre o tipo (I3 no "do tipo"; I4 no "por registro"). */
    protected function podeCriarNoEscopo(User $user): bool
    {
        return app(AcessoPorTipo::class)->usuarioHabilitadoNoTipo($user, $this->recurso());
    }

    /** Regime "por registro": intacto (I4). */
    private function objetoNoDepartamentoDoUsuario(User $user, TemDepartamento $objeto): bool
    { /* corpo atual, :16-27, sem alteração */ }
}
```

O diff nas 5 policies é mínimo e uniforme:

```php
// AgendaDiaPolicy
protected function recurso(): string { return 'agenda'; }

public function ver(User $user, AgendaDia $a): bool
{
    return $user->hasPermissionTo('agenda.ver') && $this->noEscopo($user, $a);   // era objetoNoDepartamentoDoUsuario
}

public function criar(User $user): bool
{
    return $user->hasPermissionTo('agenda.criar') && $this->podeCriarNoEscopo($user);   // era departamentos()->exists()
}
```

**Com a config semeada** (linha `recurso='evento'`, regime `por_registro`), **o `EventoPolicy` produz
resultado de autorização idêntico** ao de hoje: `'evento'` cai no ramo "por registro", onde `noEscopo`
chama `objetoNoDepartamentoDoUsuario` (intacto) e `podeCriarNoEscopo` resolve para
`$user->departamentos()->exists()` (intacto). `view`/`viewAny` não são tocados. **I4 é verificável no diff
do ramo `PorRegistro`.**

> ⚠️ **I4 não é "ausência de dependência".** Depois de E2 o Evento **passa a depender** de existir a linha
> em `tipos_conteudo`: sem ela, `regime('evento')` é `null` e o Evento **nega tudo** para não-admin (I2).
> Isso é **correto e inegociável**. A consequência prática está em §10.3/§10.4: o
> `EventoPolicyCapacidadeTest` **ganha a semente no `setUp`** — só o `setUp`, nenhuma asserção.

**Scope** (`AgendaDia::scopeNoEscopoDe`) — tudo-ou-nada no "do tipo", fail-closed nos dois ramos:

```php
public function scopeNoEscopoDe(Builder $q, User $user): Builder
{
    $acesso = app(AcessoPorTipo::class);

    return match ($acesso->regime('agenda')) {
        RegimeAcesso::DoTipo => $acesso->usuarioHabilitadoNoTipo($user, 'agenda') ? $q : $q->whereRaw('1 = 0'),
        RegimeAcesso::PorRegistro => $q->whereHas('departamentos', /* corpo atual */),
        null => $q->whereRaw('1 = 0'),
    };
}
```

**`AbaAgenda`** (§6.3) — e **reescrever o docblock inteiro (`:11-23`)**, não só a menção ao catch: além de
o catch não existir (`:20-22` descreve o efeito, não o mecanismo — §3.7), a **decisão 1 da Fase D**
("registro no escopo", `:14-15`) foi **revogada** por §6.3. Docblock e código ficam:

```php
/**
 * Fonte única do acesso à aba/rota "Agenda" no /minha-conta.
 * Usada por: a nav (mostrar/ocultar), o ContaController@agenda (abort_unless) e o mount do
 * componente. Aba visível ⇔ capacidade de ver + "sou responsável pelo tipo" (a pergunta única
 * do AcessoPorTipo). NÃO consulta registro: a config já restringe a quem mantém a agenda, e
 * consultar perpetuaria o furo do 1º registro (tabela vazia ⇒ aba some ⇒ ninguém cria o
 * primeiro dia).
 *
 * Memoizada por request via WeakMap pelo objeto User (a nav renderiza em TODA página
 * /minha-conta; auth()->user() devolve a mesma instância no request).
 *
 * Sinal de nav ⇒ checkPermissionTo, NUNCA hasPermissionTo: o fail-closed é do próprio spatie
 * (HasPermissions::checkPermissionTo captura PermissionDoesNotExist e devolve false). Com
 * hasPermissionTo, um ambiente sem CapacidadesSeeder derrubaria a nav de todas as páginas.
 */
private static function calcular(User $user): bool
{
    if (! $user->checkPermissionTo('agenda.ver')) {
        return false;
    }

    return app(AcessoPorTipo::class)->usuarioHabilitadoNoTipo($user, 'agenda');
}
```

**`AgendaConta`** — remover: `AgendaMantenedores::ids()` (`:182`), o `sync` (`:183`) e o
`registrarDepartamentosConteudo` (`:186-187`). O `create` (`:179`) fica. **Deletar**
`app/Support/Agenda/AgendaMantenedores.php`.

### 9.5 A UI e a auditoria da config (E1)

Cada `Section` de `secoesPorRecurso()` (`:68-89`) ganha, **acima** dos toggles:

- `Select::make("{$recurso}.regime")` — `->options(RegimeAcesso::opcoes())`, `->required()`, `->live()`;
- `Select::make("{$recurso}.departamentos")` — `->multiple()`, `->options(...)`,
  `->label('Departamentos responsáveis')`, **`->disabled()` quando o regime não é "do tipo" — nunca
  `->visible()`/`->hidden()` puro**:

> As opções são **hoistadas para fora do laço** de `secoesPorRecurso()` (uma variável antes do `foreach`,
> ou closure memoizada). `->options(Departamento::pluck('nome','id'))` dentro do laço é avaliado na
> construção do schema ⇒ **5 queries idênticas por render**, uma por Section.

```php
$departamentos = Departamento::pluck('nome', 'id');   // hoistado: 1 query, não 5

Select::make("{$recurso}.departamentos")
    ->multiple()
    ->options($departamentos)
    ->label('Departamentos responsáveis')
    ->disabled(fn (Get $get) => $get("{$recurso}.regime") !== RegimeAcesso::DoTipo->value)
    ->dehydrated(true)   // obrigatório: disabled não desidrata por padrão
    ->helperText(fn (Get $get) => $get("{$recurso}.regime") === RegimeAcesso::DoTipo->value
        ? 'Quem responde por este tipo. Responsabilidade só chega ao usuário pelo vínculo em /admin.'
        : 'Regime "por registro": estes responsáveis ficam GUARDADOS, mas não são lidos. Voltar ao "do tipo" os restaura.');
```

> ⚠️ **`->visible()` apagaria os responsáveis.** O Filament **não desidrata componente oculto**
> (`vendor/filament/schemas/src/Components/Concerns/HasState.php:774-783`): com `->visible(...)`, trocar o
> regime para "por registro" e salvar entregaria `[]` a `salvar()` ⇒ `sync([])` ⇒ **os responsáveis são
> apagados do banco**, e a auditoria atribuiria ao admin uma remoção que ele não fez. Voltar ao "do tipo"
> traria o tipo **sem responsáveis** — que por I1 **nega tudo**. Se preferir ocultar de fato,
> `->hidden(...)` **exige** `->dehydratedWhenHidden()`; `->dehydrated(true)` sozinho **não** basta (o
> `:782` só é alcançado depois do early-return).
>
> **Por que o `->dehydrated(true)` é obrigatório junto do `disabled()`:**
> `vendor/filament/schemas/src/Components/Concerns/CanBeDisabled.php:25` — `disabled()` chama
> `$this->saved(fn (Component $c): bool => ! $c->evaluate($condition))`; em `isDehydrated()`,
> `$this->isDehydrated` é `null` ⇒ cai em `isSaved()` ⇒ `false`.

**🔒 O cinto server-side do `salvar()` (obrigatório).** A preservação **não pode** depender da hidratação:
com `disabled()` + `dehydrated(true)`, o valor **vem do cliente**. O próprio vendor avisa, no arquivo que
sustenta este desenho (`CanBeDisabled.php:20-24`, comentário literal):

> *"Security: Disabling a field prevents it from being saved, but skilled users can manipulate Livewire's
> JavaScript to bypass the disabled state on the client. Always enforce authorization on the backend (...)
> for sensitive fields."*

Um POST forjado com `data.agenda.departamentos = []` no regime `por_registro` faria `sync([])` — o mesmo
estrago do `->visible()`, entrando por outra porta. Logo, **o servidor decide, não o estado do form**:

```php
// salvar(), por recurso:
if ($regime === RegimeAcesso::DoTipo) {
    $tipo->departamentos()->sync($ids);   // + auditoria (I7)
}
// por_registro: NÃO sincroniza — os responsáveis são preservados POR CONSTRUÇÃO, não por hidratação.
```

Com o `if`, `disabled()` + `dehydrated(true)` volta a ser o que deve ser — **UX**, não mecanismo de
integridade. **É a regra que o projeto já tem** e que este SPEC seria o único lugar a não seguir: *"campos
privilegiados NUNCA confiam no POST"* (`AgendaConta.php:26-30`, e os belts de `:171-176`, `:213-219`).

> **Não é escalonamento de privilégio** — a tela é admin-only (`canAccessPanel` + `Gate::before`), e quem
> forja o POST já pode fazer o mesmo pela tela. É integridade e trilha: sem o `if`, o admin ganharia uma
> entrada de auditoria com uma remoção que ele não fez.

**Cravado:** os responsáveis são **preservados** no regime "por registro" — deixam de ser **lidos**, não de
existir. Simétrico ao §13.3, que preserva o pivô de registro.

O `$estado` de `mount()` ganha `$estado[$recurso]['regime']` e `$estado[$recurso]['departamentos']` —
**namespace separado** dos toggles, que são `$estado[$papel][$recurso][$acao]`. Os papéis são
`GlossarioUsuarios::PAPEIS_EDITAVEIS` (`trabalhador`, `diretor`); nenhum recurso do glossário colide com
esses nomes, então as duas árvores convivem em `data`. **Teste crava isso** (§10.2).

**Recurso sem linha em `tipos_conteudo`:** `mount()` preenche `regime => null` e `departamentos => []`;
`salvar()` **ignora** recursos sem linha (não cria) — a tela é escritora da config **existente**, não do
catálogo; quem semeia é o `TiposConteudoSeeder` (I8). Consequência prática: com a tabela vazia o `Select`
`required()` reprova o submit — por isso **todo teste que chama `salvar()` semeia `TiposConteudoSeeder`**
(§10.2).

`salvar()` grava a config **além** das permissions, com auditoria (I7) — **dois métodos**, pela armadilha
do `registrar()` (§3.10):

```php
// App\Support\Autorizacao\AuditoriaAutorizacao (novos; o registrar() privado NÃO muda)

/** Regime do tipo: subject = TipoConteudo; diff de nomes (formato {adicionados, removidos}). */
public static function registrarRegimeTipo(TipoConteudo $tipo, ?string $antes, string $depois): void
{
    self::registrar($tipo, "regime do tipo {$tipo->recurso} alterado",
        self::diff($antes === null ? [] : [$antes], [$depois]));
}

/** Responsáveis do tipo: subject = TipoConteudo; diff por id, itens {id,nome} (molde :71-88). */
public static function registrarDepartamentosTipo(TipoConteudo $tipo, array $antes, array $depois): void
{ /* molde exato de registrarDepartamentosUsuario:71-88 */ }
```

Cada um é **no-op se não mudou** (comportamento do privado, `:113-115`) — mudar só o regime grava 1
entrada; mudar só os responsáveis grava 1; mudar os dois grava 2. `log_name` = `'autorizacao'`, porta
`'admin'` (vem do `Filament::getCurrentPanel()`, `:29`). O **antes relê do banco** antes do sync, como
`MatrizCapacidades:125`.

Título e navegação da página passam a **"Configuração de acesso por tipo"** (§6.8).

---

## 10. Testes

### 10.1 Moldes

Sem factory de departamento (§3.11): usar `EstruturaCemaSeeder` (molde de 39 pontos; busca por sigla —
`Departamento::where('sigla','DECOM')->value('id')`) ou `Departamento::create` (molde de
`EventoPolicyCapacidadeTest.php:49`). **Não criar factory só para isto.**

> ⚠️ **A `AgendaDiaFactory` NÃO anexa departamento.** Todo caso do §10.3 que observe o filtro precisa de
> `->departamentos()->attach(...)` explícito no arrange — senão o pivô fica vazio e o teste não distingue
> "o objeto é ignorado" de "o objeto está vazio".
>
> ⚠️ **`AbaAgenda::$cache` é `static WeakMap` (`:26-33`), chaveado pelo objeto `User`, e sobrevive dentro
> do mesmo teste.** Um caso que consulte a aba, mude a config/vínculo e **reconsulte com o mesmo `$user`**
> recebe o memo velho — e **fica verde por memo, não por regra**. Usar **`$user->fresh()`** (objeto novo ⇒
> chave nova ⇒ recalcula); o projeto já faz isso nos testes de Gate (`CapacidadeViaPapelTest:58,62`). O
> `scoped` de §6.5 resolve o memo do `AcessoPorTipo`, mas esse `static` **já existe e fica**.

### 10.2 E1 (nenhum teste existente muda de cor)

- **`TipoConteudoTest`** — a segunda lista (§6.1): seeder idempotente (rodar 2× não duplica: 5 linhas);
  semeia **todos** os `RECURSOS`; `recurso` é unique; recurso fora do glossário é rejeitado; a semente
  bate exatamente com §4.2 (Agenda DED+DECOM, Palestra DED, Palestrante DED, Post DECOM, Evento
  `por_registro` sem responsáveis).
  **+ Seeder não sobrescreve a tela (I8):** rodar o seeder ⇒ alterar pela tela (Agenda passa a só DED,
  regime trocado) ⇒ rodar o seeder **de novo** ⇒ **regime e responsáveis preservados** (DECOM **não**
  volta) e **nenhuma entrada nova** em `activity_log`. *Reprova se o seeder usar `updateOrCreate`/`sync`
  incondicional.*
  **+ FK `restrict` (I8):** excluir um departamento responsável por algum tipo **reprova** (não deleta,
  config intacta, notificação de erro); excluir departamento sem tipo responsável **passa**.
- **`AcessoPorTipoTest`** — o serviço isolado: `regime()` de recurso ausente ⇒ `null`;
  `departamentosResponsaveis()` de tipo ausente ⇒ `[]`; `usuarioHabilitadoNoTipo` fail-closed nos 3
  caminhos (`null`, DoTipo sem responsáveis, DoTipo com usuário disjunto).
  **+ Memo morre com o escopo (§6.5):** resolver o serviço, memoizar um recurso, **alterar a config no
  banco**, chamar `app()->forgetScopedInstances()`, resolver de novo ⇒ instância nova, config atualizada.
  *Reprova se o binding for `singleton` — é o teste que trava o furo do worker.*
- **`MapaRecursoModelTest`** (I5, amarração do §9.2) — para cada policy: `recurso()` ∈ `RECURSOS`;
  `RECURSOS_MODELS[recurso()]` é o model que a policy autoriza; `RECURSOS_MODELS` cobre exatamente
  `RECURSOS`; **nenhuma policy usa `can()` com nome cru** (varredura).
- **`MatrizCapacidadesConfigTest`** — a UI: as duas árvores de `data` convivem (nenhum recurso colide com
  `PAPEIS_EDITAVEIS`); salvar grava regime + responsáveis **e audita**; **`salvar()` continua sendo o único
  escritor** (I8).
  **+ Round-trip preserva (§9.5):** trocar `do_tipo`→`por_registro`→`do_tipo` pela tela **preserva os
  responsáveis** (`departamento_tipo_conteudo` intacto); salvar no "por registro" **não zera** o pivô da
  config. *Reprova o `->visible()`.*
  **+ Forja do POST (o cinto do §9.5):** regime `por_registro` + state forjado com
  `data.<recurso>.departamentos = []` ⇒ **pivô da config intacto** e **nenhuma entrada de auditoria**.
  *Reprova o `sync()` incondicional — é o teste que prova que a preservação é do servidor, não da
  hidratação.*
  **+ `salvar()` com `regime` vazio ⇒ erro de validação e nenhuma linha gravada/alterada** (o `required()`
  é o guarda do NOT NULL da §9.1).
- **`AuditoriaTipoConteudoTest`** (I7) — **pela página, sempre** (molde: `AuditoriaMatrizTest:33-51` —
  `Livewire::test(MatrizCapacidades::class)->fillForm([...])->call('salvar')->assertHasNoFormErrors()`, e
  **só então** ler o `activity_log`). Casos: mudar só o regime ⇒ 1 entrada `'autorizacao'` com `subject` =
  `TipoConteudo`; mudar só os responsáveis ⇒ 1 entrada com diff `{id,nome}`; mudar os dois num **único**
  `salvar()` ⇒ 2; **salvar sem mudar nada ⇒ 0** (o no-op de `AuditoriaAutorizacao:113-115`); `properties`
  carrega porta `'admin'` + ip + user_agent.
  **+ Caso que reprova a leitura tardia do "antes":** trocar responsáveis de `[DED]` para `[DED, DECOM]` ⇒
  1 entrada com `diff.adicionados == [{id: <decom>, nome: 'DECOM'}]` e `diff.removidos == []`. *Diff vazio
  aqui significa que o "antes" foi lido **depois** do sync.*
  **+ Trocar só o regime `do_tipo`→`por_registro` ⇒ 1 entrada (a do regime), ZERO de responsáveis** —
  *reprova o `sync([])` do `->visible()`, que geraria 2 e atribuiria ao admin uma remoção que ele não fez.*
  > Teste unitário dos dois helpers é **complemento, nunca substituto**: sozinho, fica verde com `salvar()`
  > não auditando nada.

**Testes existentes que E1 TOCA (só o `setUp`, nenhuma asserção).** O `->required()` do Select de regime
entra no schema que `salvar()` valida (`MatrizCapacidades.php:109` →
`schemas/src/Concerns/HasState.php:450`). Sem linha em
`tipos_conteudo`, `mount()` põe `regime => null` ⇒ 5 erros de `required` ⇒ caem os `assertHasNoFormErrors()`
de **`MatrizCapacidadesTest:49,57,85,98`** e **`AuditoriaMatrizTest:36,67,79`** (e as asserções seguintes,
por cascata). **Correção: somar `$this->seed(TiposConteudoSeeder::class)` ao `setUp` dos dois** —
`MatrizCapacidadesTest:23-29` e `AuditoriaMatrizTest:22-29`, **depois** do `EstruturaCemaSeeder` (a semente
resolve departamento por sigla).

> 🚫 **Proibido remover o `->required()` para restaurar o verde:** abriria gravar `regime` vazio numa coluna
> `string` NOT NULL (§9.1), cujo efeito no filtro é `null ⇒ deny` (§9.3) — **lockout silencioso de todos os
> não-admins do tipo**.
>
> ⚠️ `AuditoriaMatrizTest::test_salvar_sem_mudanca_nao_loga` (`:75-82`, `assertSame(0, ...)`): com a config
> semeada, `salvar()` passa a comparar regime/responsáveis. Ele só continua verde porque `mount()` reflete
> exatamente a semente ⇒ diff vazio ⇒ no-op. **Isso é invariante, não sorte** — cravado no
> `MatrizCapacidadesConfigTest` acima.

**Aceite de E1:** `798 + novos`, todos verdes; **nenhuma asserção de teste existente muda de cor** (só os
2 `setUp` acima).

### 10.3 E2 (os invariantes)

Um `CamadaUmFiltroPorTipoTest` (ou por invariante), cada caso reprovando de verdade:

> ⚠️ **Todo caso do regime "do tipo" DEVE fixar o estado do pivô do objeto.** Sem isso o teste não reprova
> nada: um filtro híbrido (`usuarioHabilitadoNoTipo && objetoNoDepartamentoDoUsuario`, ou a variante
> permissiva com `||`) passaria verde. A `AgendaDiaFactory` **não** anexa departamento — os casos exigem
> `->departamentos()->attach()` explícito no arrange.

| Invariante | Caso que **reprova** |
|---|---|
| **I1** | `agenda` = "do tipo" **sem responsáveis** ⇒ diretor com as 4 capacidades e vínculo em DED **não** vê/edita/exclui/cria. **Admin passa** (Gate::before). |
| **I2** | `tipos_conteudo` **vazia** (sem `TiposConteudoSeeder`) ⇒ nega os 4 verbos **e não lança** — **nos 5 recursos, inclusive `evento`**. *Estender ao Evento trava por teste o fallback proibido (§12.8).* |
| **I3** | **O cenário real de §4.3:** diretor vinculado ao **DEPRO**, com `agenda.criar` via papel, `agenda` responsável = DED+DECOM ⇒ **não cria**. E o diretor do DED **cria**. |
| **Disjunto** | Usuário do depto **X**; tipo responsável = depto **Y** (X ≠ Y); **AgendaDia com pivô = X** ⇒ **nega** ver/editar/excluir/criar. |
| **Pivô ignorado (reprova o `&&`)** | Usuário ∈ **DED**; `agenda` responsável = **DED**; AgendaDia com pivô = **DEPRO** (disjunto do usuário) ⇒ **PERMITE** ver/editar/excluir. *Reprova o AND puro e o híbrido `habilitado && (objeto sem pivô \|\| objetoNoDepartamento...)`.* |
| **Pivô não abre (reprova o `\|\|`)** | Usuário ∈ **DEPRO**; `agenda` responsável = **DED**; AgendaDia com pivô = **DEPRO** (intersecta o usuário) ⇒ **NEGA** ver/editar/excluir. *Reprova a variante permissiva `habilitado \|\| objetoNoDepartamento...`.* |
| **I4** | `EventoPolicyCapacidadeTest` passa com **uma única alteração: `$this->seed(TiposConteudoSeeder::class)` no `setUp` (`:25`), após o `CapacidadesSeeder`** — nenhuma asserção, nenhum cenário, nenhum resultado esperado muda. **Se alguma asserção precisar mudar, o E2 vazou.** + caso novo: `evento` = "por registro" ⇒ objeto no depto do usuário permite, objeto fora nega, objeto **sem** departamento nega, criar = tem algum depto. |
| **I6** | Admin passa em tudo, em qualquer config (inclusive I1/I2). |
| **I9** | AgendaDia **sem departamento** (pivô vazio — o 3º estado), `agenda` responsável = DED ⇒ diretor do DED **edita** (hoje negaria). Decisão escrita, não efeito colateral. |
| **Aba** | Responsável + `agenda.ver` ⇒ aba visível **com a tabela vazia** (§6.3). Não-responsável com 123 registros ⇒ aba oculta + `abort 403` nos 2 portões (`ContaController:40`, `AgendaConta:45`). |
| **Scope** | "Do tipo": responsável ⇒ **todos** os registros (inclusive os de pivô disjunto); não-responsável ⇒ **nenhum** (tudo-ou-nada). |

> **Nota de montagem do I4:** o `EventoPolicyCapacidadeTest` cria os departamentos **dentro** de cada caso
> (`Departamento::create` em `:47-50`), depois do `setUp`. Como `evento` é `por_registro` com `siglas: []`
> por definição (§9.1), o seeder no `setUp` grava a linha do Evento **sem responsáveis** e não há conflito
> — os 4 tipos "do tipo" ficam com `sync([])`, o que é irrelevante para este teste.

### 10.4 Testes existentes que mudam de cor em E2 (esperado)

**Muda só o `setUp`:** `EventoPolicyCapacidadeTest` ganha `$this->seed(TiposConteudoSeeder::class)` (`:25`)
— exigência de I2, que vale para **todo** recurso, inclusive o Evento. **Nenhuma asserção muda de cor. Se
alguma mudar, o E2 vazou** para o "por registro".

> 🚫 Se um teste fica vermelho porque `tipos_conteudo` está vazia, **semeie o teste**. Jamais "conserte"
> trocando `null => false` por fallback ao pivô — ver §12.8.

**Procedimento (a lista abaixo é indicativa, NÃO exaustiva):** antes de fechar E2, rodar
`grep -rn "check('ver'\|check('criar'\|check('editar'\|check('excluir'" tests/` e, em **cada** arquivo que
aparecer, decidir explicitamente: semear `TiposConteudoSeeder`, ajustar a asserção, ou confirmar que é
Evento. **Nenhum arquivo pode ficar sem decisão escrita.**

**Muda:** `CapacidadeConteudosTest`, `ConteudosTemDepartamentoTest`, `AbaAgendaTest` (usa
`AgendaMantenedores::ids()` na `:44`), `AgendaContaCriarTest`, `AgendaContaEditarExcluirTest`,
`AcessoAgendaContaTest`, **`AuditoriaAgendaPortaTest`** (e **não** `AuditoriaAgendaDiaTest`, que tem zero
ocorrência de departamento), `AgendaDiaFormSchemaTest`, os 4 ResourceTests — e, porque o `setUp` semeia
`EstruturaCemaSeeder`+`CapacidadesSeeder` mas **nunca** `TiposConteudoSeeder` (caindo em I2 ⇒ deny):
**`CapacidadeViaPapelTest`** (`:58`, `:73`, `:85`) e **`MatrizCapacidadesTest`** (`:107`). Nos dois, o
conserto é **semear o seeder no `setUp`** e ajustar os vínculos do usuário para DED.

> 🚫 **Proibido** "consertar" o `CapacidadeViaPapelTest` semeando `palestra ⇒ DED+DECOM` para o `assertTrue`
> voltar: divergiria de §4.2/§9.1 e mataria a cobertura da decisão real.

**Destino do teste que cobria a exceção morta** (decisão 4 do §5):
`CapacidadeViaPapelTest::test_decom_edita_palestra_com_dois_departamentos_por_intersecao` (`:77-90`) testa
**nominalmente** a exceção que a decisão 4 mata — sua premissa deixa de existir. É **reescrito** (não
deletado) como o **caso canônico do I9/congelamento**, renomeado para
`test_regime_do_tipo_ignora_o_pivo_do_objeto`, com o seeder:

- usuário ∈ **DED**, `palestra` responsável = **DED**, palestra com pivô **DED+DECOM** ⇒ **permite** (o
  objeto não é consultado);
- usuário ∈ **DED**, palestra com pivô **só DECOM** ⇒ **permite** — é o congelamento provado por teste;
- usuário ∈ **DECOM** (não responsável), qualquer pivô ⇒ **nega** — substitui o antigo `assertTrue` da `:85`.

Passa a ser o guardião do item 5 de §11 ("algum caminho ainda lê o pivô para autorizar?").

**Os 4 `assertHasFormErrors(['departamentos'])` morrem** (o campo sai do schema):
`PalestraResourceTest:280`, `PostResourceTest:381`, `PalestranteResourceTest:173`,
`AgendaDiaResourceTest:85`.

**Os 4 `test_salva_departamento` morrem inteiros** — o campo saiu do schema ⇒ o `->relationship()` não faz
mais o sync ⇒ a assertiva de pivô é insalvável. **Apagar o método completo; NÃO reintroduzir o sync**
(violaria a decisão 7 / §6.4):

| Arquivo | Método (linhas) | Assertiva que morre |
|---|---|---|
| `PalestraResourceTest` | ~248-266 | `:265` |
| `PostResourceTest` | ~353-369 | `:368` |
| `PalestranteResourceTest` | `:148-162` | `:161` |
| `AgendaDiaResourceTest` | `:62-75` | `:74` |

**Os `fillForm` a limpar** (inverso do "O1" da Fase C — a Fase C teve de **adicionar** em todos). Já
**excluídas** as linhas que pertencem aos métodos deletados acima (259/362/155/68 — são "a apagar", não "a
limpar"):

| Arquivo | Linhas |
|---|---|
| `PalestraResourceTest` | 40, 73, 97, 115, 147, 172, 191, 210, 231 |
| `PostResourceTest` | 56, 73, 90, 110, 130, 156, 173, 278 |
| `PalestranteResourceTest` | 79, 92, 107, 138 |
| `AgendaDiaResourceTest` | 35, 56 |

**Não tocar:** `EventoResourceTest:43` e `UsuarioResourceTest:62` passam `'departamentos'` **sem** o campo
ser required — o primeiro é o Evento (permanece), o segundo é o vínculo do usuário. Idem
`AuditoriaUserResourceTest:45,94`.

---

## 11. O que o passe adversarial vai caçar

1. **I1/I2/I3/I9** escritos e com teste que **reprova de verdade** (o caso disjunto: usuário do depto X,
   tipo responsável = depto Y ⇒ nega).
2. Se o **"por registro" (Evento) saiu do diff intacto**.
3. Se a **config vazia/ausente fecha** em vez de abrir.
4. Se a **auditoria da config está lá** (I7) — inclusive o no-op do `registrar()` (§3.10).
5. Se **algum caminho ainda lê o pivô para autorizar** nos 4 tipos "do tipo" (seria a divergência
   silenciosa que o congelamento existe para evitar).
6. Se E2 fez aparecer o **primeiro `getEloquentQuery()` escopado** ou o **primeiro `canAccess()`** — seria
   escopo vazando (§3.5).
7. **Citação arquivo:linha** de tudo que o SPEC afirma sobre o código atual.

---

## 12. Armadilhas já pagas neste projeto (não repetir)

1. **`pluck('col','id')` em `BelongsToMany` cujo pivô tem id próprio ⇒ "ambiguous column: id"** (SQLite
   **e** MySQL). Qualificar: `->departamentos()->pluck('departamentos.nome','departamentos.id')`. Mordeu
   no PR #28 — e **todos** os pivôs deste projeto têm `id()` (§3.11), inclusive o novo.
2. **Livewire v3:** propriedade + método com o mesmo nome ⇒ `wire:click` vira no-op silencioso; testes
   `->call()` não pegam.
3. **Dev:** editar Blade/PHP exige `docker compose restart app worker` (OPcache `validate_timestamps=0`;
   `view:clear` não basta).
4. **Pint por task** — o CI roda `pint --test` **antes** dos testes e aborta o job.
5. **Contar ocorrências com `grep -rn`** (o `-rl` esconde a 2ª ocorrência no mesmo arquivo).
6. **Verificação real** antes de declarar pronto: `docker compose exec -T app php artisan test` (o projeto
   **não** usa Sail) + abrir a página no localhost.
7. **Docblock não é evidência** (§3.7). Ler o código — foi o que induziu o enquadramento ao erro do "catch"
   do `AbaAgenda`.
8. 🚫 **Regime `null` NUNCA tem fallback** — nem para `PorRegistro`, nem para nada: `null ⇒ deny`, sempre
   (I1/I2). Se um teste ficar vermelho porque `tipos_conteudo` está vazia, **semeie o teste**. Jamais
   afrouxar `AcessoPorTipo::regime()` (ex.: `?->regime ?? RegimeAcesso::PorRegistro`) nem acrescentar
   `null => $this->objetoNoDepartamentoDoUsuario(...)` ao trait. Qualquer um dos dois faz recurso sem linha
   **ABRIR**, destrói I1/I2 e **reabre o pivô congelado** (354 registros de eco do backfill, §13.3) como
   fonte de autorização — e o teste de I2, se escrito só sobre `agenda`, continuaria **verde**, escondendo
   o furo. Daí o I2 cobrir os 5 recursos (§10.3).
9. **Filament não desidrata componente oculto**
   (`schemas/src/Components/Concerns/HasState.php:774-783`): `->visible()`/`->hidden()` num campo que
   `salvar()` sincroniza **apaga o dado**. E `disabled()` também não desidrata sozinho
   (`CanBeDisabled.php:25`). Ver §9.5 — e **nunca** deixar a integridade por conta da hidratação: o
   servidor decide (o `if` do regime no `salvar()`).
10. **`scoped`, não `singleton`**, para qualquer serviço memoizado que o `worker` toque (§6.5).
11. ⚠️ **Dois `HasState.php` no Filament** — ao conferir citação, olhar o caminho:
    `schemas/src/Concerns/HasState.php` (`getState()`/`validate()`, `:450`) ≠
    `schemas/src/Components/Concerns/HasState.php` (`isDehydrated()`, `:774-783`).
12. **Memo `static` sobrevive dentro do mesmo teste** — `AbaAgenda::$cache` é `WeakMap` chaveado por
    `User`: reconsultar com o mesmo objeto devolve o memo velho e o teste fica verde por memo, não por
    regra. Usar `$user->fresh()` (§10.1).

---

## 13. Ciência (registrar, não consertar agora)

### 13.1 Impacto no cutover de produção

O cutover de PROD da Fase D **ainda não rodou** (o site novo sobe em big-bang; prod ainda não existe). Se
a Camada 1 for mesclada antes do deploy:

- **`cema:departamentalizar-conteudos`** e **`cema:somar-ded-agenda`** passam a ser **irrelevantes para
  acesso** nos 4 tipos (a config manda). **Não apagar** os comandos nem os testes agora; saem do caminho
  crítico do cutover. Escrevem o pivô que a Camada 1 congela — rodá-los é inofensivo e inútil.
- **`cema:vincular-diretores-departamento`** e **`cema:vincular-presidentes-departamentos`** **continuam
  essenciais**: são o lado do **usuário** (`departamento_usuario`), que a Camada 1 segue lendo — é ele que
  responde "estou num departamento responsável?".
- **Ligar `agenda.*` na matriz continua essencial.**
- **Novo passo obrigatório:** rodar o `TiposConteudoSeeder` no ambiente (ou configurar pela tela). Sem
  ele, I2 nega tudo para não-admin — fail-closed, mas o site "não funciona" para diretores. **Vale para os
  5 recursos, inclusive o Evento** (que passa a exigir a linha `por_registro`, §7 I4) — não só para os 4
  tipos "do tipo". Como o seeder é **insert-only** (§9.1), rodá-lo é seguro e idempotente: nunca desfaz
  configuração feita na tela.

### 13.2 Os 7 eventos órfãos

Ids **12, 29, 31, 50, 52, 56, 57** ("63 anos CEMA", "Formação de Evangelização pré-concepção (DIJ/FEDF)",
"Reunião de planejamento Campanha 'E a Vida Continua'", "12º Encontro da Família", "Feirão de Livros
Espíritas", e duas "Reunião") — todos **publicados**, todos com **zero departamento**.

O Evento fica "por registro" e o filtro dele não muda: esses 7 são **só-admin hoje e continuam só-admin**.
São uma **bomba adiada** — quando o Evento ganhar superfície de edição no site, são 7 registros publicados
que ninguém além do admin edita. **Registrar; não consertar nesta fatia.**

> **E o 8º pode nascer.** O furo do `criar` (§3.2) é fechado **só no "do tipo"** (I3): no "por registro",
> `podeCriarNoEscopo` resolve para `$user->departamentos()->exists()` — **qualquer departamento serve**, e
> o furo **permanece no Evento**. É consequência correta de I4 ("não mexer"), e hoje só não morde porque o
> Evento não tem superfície de criação fora do `/admin` (admin-only). Pior: como `EventoForm:107-112`
> **não** é `required`, um evento criado assim nasce **órfão** — o 8º desta lista. **Registrar; não
> consertar.**

### 13.3 O que o congelamento deixa para trás

Nos 4 tipos, `departamento_<x>` vira dado histórico: 123 + 127 + 45 + 59 = **354 registros de pivô** que
deixam de ser lidos. Intactos, íntegros, com FK. A relação `departamentos()` continua nos models. Se a
Camada 4 trocar o regime do Post (§6.7), o pivô do Post volta a ser lido — e o que estiver lá (DECOM em
todos os 45) volta a valer, mais o backfill dos posts nascidos no período "do tipo".

### 13.4 O vínculo editorial só tem diretores

`departamento_usuario` tem **16 usuários, todos `diretor`** (§4.4). Um trabalhador comum do DECOM **não
está** em `departamento_usuario` — logo **não edita nada** até ser vinculado à mão no `/admin`, mesmo com
`agenda.*` ligado no papel `trabalhador` (que está ligado no dev). Isso é a **Camada 3** (setor/função),
não esta fatia. **Só não deixar a UI mentir sobre isso** — o helper do multiselect diz "departamentos
responsáveis", e responsabilidade só chega ao usuário via vínculo.

---

## 14. Fora de escopo (não vazar)

- **Camada 3** (escopo por setor/função, acesso hierárquico) e **Camada 4** (Mensagens: curadoria,
  publicar separado de editar, visibilidade rica, direcionada a pessoa).
- **Visibilidade pública:** `roles.nivel` / `scopeVisiveisPara` / `VisibilidadeEvento` /
  `EventoPolicy::view`/`viewAny` — **intocados**.
- **"Evento nasce no depto do autor"** (auto-atribuição) — não existe hoje e não nasce aqui.
- `Gate::before`, criar/apagar papéis, `config/permission.php`.
- `departamento_usuario` e `UserResource:107-111`.
- Qualquer **migration destrutiva**; `migrate:fresh`/`refresh`/`wipe`/`reset`; seed destrutivo.
  **Migrations só incrementais.**
