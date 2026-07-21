# Spec — Camada 4 · Fatia F4a · Curadoria de Direcionadas no /admin (campo de destinatários)

> Autoria: Thiago Mourão — https://github.com/MouraoBSB — 2026-07-20
> Enquadramento travado com o dono no kickoff da F4a (split F4a/F4b). Este spec **não** improvisa além das decisões
> travadas; **cada afirmação sobre o terreno foi verificada contra o código real** (evidência `arquivo:linha` no §3,
> levantada por leitura direta: `MensagemResource`, `Mensagem::destinatarios/scopePublicado/podeSerVistoPor`, a
> migration do pivô, `CreateMensagem`/`EditMensagem`, o trait `SincronizaRelacionadas`, `PalestraResource`
> (`ids_palestrantes`), `EventoForm` (import de `Get`), `MensagemResourceTest`, `MensagemFactory`, `VisibilidadeMensagem`).
> Destino: **SPEC ✅ APROVADA no passe do consultor (zero bloqueador; O1–O4 FECHADOS, §13).** Passe interno adversarial
> (5 verificadores) + consultor confirmaram o ponto técnico O1 no vendor. Segue para o **PLANO** (TDD, ordem §9.0) →
> passe do plano → execução → PR. **NÃO implementar ainda** (o plano vai ao passe do consultor antes da execução).
> Base: `origin/main` (HEAD **`93999e8`**, PR #41 — **Fatia 3C mesclada**; a F4a ramifica daqui). Branch de trabalho:
> `camada-4-fatia-f4a-curadoria-direcionadas`. Suíte baseline: **~1081 testes** (a `MEMORY.md` registra a 3C em 1081;
> medir com `docker compose exec -T app php artisan test --list-tests` antes de começar).
> Fundação: a **Fatia 3A** (o pivô `mensagem_destinatario`, `Mensagem::destinatarios()`, o resolvedor
> `podeSerVistoPor`/`scopeVisiveisPara` que **lê** o pivô) e a **Fatia 3C** (a aba read-only "Minhas Direcionadas",
> que **lê** `mensagensDirecionadas()->publicado()->where('nivel', Direcionada)`). A F4a é quem finalmente **ESCREVE**
> o pivô pela UI do `/admin` — hoje só o importador `cema:importar-direcionadas` o popula.

---

## 0. Recorte: por que esta é a "F4a" (split F4a/F4b travado pelo dono)

A **Fatia 4 (curadoria)** foi partida em **F4a** (ESTE spec) e **F4b** (depois):

- **F4a (ESTE spec) — habilitar a criação de uma direcionada COMPLETA pelo `/admin`.** Hoje o `MensagemResource` já
  tem título/corpo/formato/**nível**/status/autores/relacionadas/pictografia — mas **falta o único campo que torna
  uma direcionada de fato endereçada: os destinatários**. Sem ele, o admin consegue marcar `nivel='direcionada'` mas
  **não** consegue dizer *a quem* — a mensagem nasce sem pivô e **ninguém** a vê (o resolvedor da 3A exige
  `destinatarios()->whereKey($user)->exists()`). F4a **fecha essa lacuna**: um `Select` de destinatários, condicional
  ao nível, com a integridade "direcionada ⇔ tem destinatário".
- **F4b (depois, fora deste spec) — fluxo não-admin no `/minha-conta`.** Médium **cria** rascunho / diretor-DEPAE
  **publica**; máquina de estados formal; ação "publicar"; capacidades `mensagem.*` **atribuídas** por setor/cargo.
  **Toda** a autorização por setor/cargo fica em F4b.

**Por que F4a é operada SÓ pelo admin** (verificado, §3.4): o `/admin` é **admin-only**
([User::canAccessPanel → hasRole('administrador')], regra dura do CLAUDE.md). A capacidade `mensagem.*` **existe mas
está inerte** — o admin edita tudo via `Gate::before`; os 2 diretores-DEPAE e os ~46 médiuns **não** são admin e **não**
entram no `/admin`. ⇒ **F4a não tem nada de autorização por papel/setor** — é o admin, no painel, preenchendo o campo
que faltava. Autorização = F4b.

**O que a F4a NÃO é:** não é o fluxo do `/minha-conta` (F4b); não é máquina de estados / ação "publicar" (no `/admin`
o admin já muda `status` livremente pelo Select existente); não atribui capacidades; não toca a leitura (3C/3B/3A),
o front público, o sitemap nem Autores. É **um campo novo no form do Resource + a integridade que ele exige**.

---

## 1. Contexto e objetivo

Uma mensagem `nivel='direcionada'` só é vista por quem está no pivô `mensagem_destinatario` (resolvedor 3A,
[Mensagem.php:115-116](../../../app/Models/Mensagem.php#L115-L116)). Hoje esse pivô só é populado pelo importador do
legado (`cema:importar-direcionadas`, rel 38 reversa) — **não há UI** para criar/editar uma direcionada e seus
destinatários. O admin que criar uma direcionada nova pelo painel produz uma mensagem **órfã de destinatários**
(invisível a todos, inclusive na aba da 3C, que exige `->exists()`).

**Objetivo (aditivo, no `/admin`):**

1. **Campo "Destinatários"** no form do `MensagemResource` — um multiselect de usuários, **condicional** a
   `nivel='direcionada'` (só aparece quando o nível é Direcionada).
2. **Integridade "direcionada ⇔ destinatário"**, em duas direções (§2.2):
   - `nivel='direcionada'` **exige ≥1 destinatário** (uma direcionada sem destinatário não é vista por ninguém — dado
     inútil e enganoso; **salvar deve FALHAR**).
   - `nivel != 'direcionada'` **⇒ pivô vazio** (ao salvar/trocar o nível para não-direcionada, os destinatários são
     **esvaziados** de forma determinística — nunca fica um pivô "fantasma" numa mensagem pública).
3. **(opcional / nice-to-have)** coluna-contador de destinatários + filtro "tem destinatário" na tabela do Resource.

**A leitura NÃO muda** — F4a só **escreve** o pivô que a 3A/3B/3C já leem. Sem migration (o pivô veio da 3A). O campo
mostra **nome/e-mail de usuários** (PII), o que é aceitável por ser **admin-only** (§3.4).

---

## 2. Decisões travadas (não reabrir)

Do kickoff da F4a (dono) + heranças da 3A/3C:

1. **F4a é SÓ o `/admin`.** O `/admin` é admin-only; a autorização por papel/setor e o fluxo `/minha-conta` são **F4b**.
   No painel, o admin já muda `status` livremente — F4a **não** cria ação "publicar" nem máquina de estados.
2. **Integridade "direcionada ⇔ destinatário" (as duas direções — §1.2):** `direcionada ⇒ ≥1 destinatário`
   (obrigatório) **e** `não-direcionada ⇒ pivô vazio` (limpar). Isso **preserva a premissa da 3C** ("só direcionada
   tem destinatário") na origem: a 3C **blinda a leitura** com `where('nivel', Direcionada)`, mas F4a **não deve
   sequer criar** o dado anômalo.
3. **Campo CONDICIONAL ao nível:** o `Select` de nível vira `->live()`; o campo de destinatários é `->visible(fn (Get
   $g) => $g('nivel') === VisibilidadeMensagem::Direcionada->value)`. PII visível ao admin (ok, admin-only).
4. **Determinismo (CLAUDE.md-9 — proibido gambiarra / comportamento não determinístico):** a sincronização do pivô
   (anexar **e** limpar) tem **um caminho server-side determinístico**, independente de a UI ter escondido o campo.
   A escolha do mecanismo (auto-sync `->relationship()` + guard **vs.** sync manual estilo `relacionadas`) é a **decisão
   O1** (§6.2 / §13) — recomendação: **manual, molde `relacionadas`/`ids_palestrantes`** (um só caminho).
5. **Sem `migrate:fresh`/`refresh`/`wipe`/`reset` nem seed destrutivo** no dev ([[nunca-migrate-fresh-no-dev]]); o
   `legado` é **read-only**. **F4a não tem migration** — o pivô `mensagem_destinatario` já existe da 3A.
6. **Fronteiras (§11):** **não** tocar `/minha-conta` (é F4b) · o resolvedor 3A (`podeSerVistoPor`/`visiveisPara`/
   `visibilidade`) · a aba/lista da 3C · a barreira/single da 3B · o front público · o sitemap · Autores · o importador.
   F4a é **cirúrgica no `MensagemResource` + suas páginas Create/Edit + testes**.

---

## 3. Terreno confirmado por leitura (não presumir diferente)

Verificado no código em 2026-07-20 (base `93999e8`). **Docblock não é evidência** — o que segue foi lido no fonte.
Referências relativas a partir de `docs/superpowers/specs/` (`../../../`). **O campo `destinatarios` NÃO existe no
form** hoje ([MensagemResource.php:140-159](../../../app/Filament/Resources/Mensagens/MensagemResource.php#L140-L159) —
a Section "Autoria e relações" só tem `autores` e `relacionadas`) ⇒ campo novo, aditivo.

### 3.1 O pivô e sua leitura (o que F4a alimenta — reusar, não recriar)

- [Mensagem::destinatarios(): BelongsToMany](../../../app/Models/Mensagem.php#L169-L172) —
  `belongsToMany(User::class, 'mensagem_destinatario', 'mensagem_id', 'user_id')`. Pivô **simples** (não simétrico, sem
  coluna extra); a migration só tem `mensagem_id`/`user_id` + `unique(['mensagem_id','user_id'])` + `cascadeOnDelete`
  ([migration:14-18](../../../database/migrations/2026_07_19_000001_create_mensagem_destinatario_table.php#L14-L18)).
  ⇒ um `->sync([ids])` basta para substituir o conjunto; um `->sync([])` esvazia.
- **A leitura já existe e depende do pivô** — o resolvedor da 3A: a única forma de uma direcionada ser vista é o
  usuário estar no pivô ([podeSerVistoPor, Mensagem.php:115-116](../../../app/Models/Mensagem.php#L115-L116):
  `Direcionada => $usuario && $this->destinatarios()->whereKey($usuario->id)->exists()`; e no
  [scopeVisiveisPara:146-148](../../../app/Models/Mensagem.php#L146-L148)). ⇒ **uma direcionada sem destinatário é
  invisível a todos** — daí a regra "≥1 obrigatório" (§2.2). F4a **não** toca esse resolvedor; só o **alimenta**.
- [Mensagem::scopePublicado](../../../app/Models/Mensagem.php#L72-L75) e a **aba/lista da 3C**
  (`mensagensDirecionadas()->publicado()->where('nivel', Direcionada)->exists()`) **consomem** o pivô que F4a escreve.
  F4a **não** os altera — apenas passa a existir dado real criado pela UI (antes, só o importador criava).

### 3.2 O form do Resource e os DOIS padrões de multiselect (o molde da decisão O1)

No **mesmo form** ([MensagemResource.php](../../../app/Filament/Resources/Mensagens/MensagemResource.php)) já convivem
os dois padrões — **é daqui que sai a decisão O1** (§6.2):

- **`autores` — `->relationship()` AUTO-SYNC** ([:143-148](../../../app/Filament/Resources/Mensagens/MensagemResource.php#L143-L148)):
  `Select::make('autores')->relationship('autores','nome')->multiple()->preload()->searchable()`. As páginas **não**
  fazem nada custom para `autores` — o Filament sincroniza o pivô sozinho no save (prova de que auto-sync funciona
  para um `belongsToMany` **plano, sem regra**).
- **`relacionadas` — `->options()` + SYNC MANUAL** ([:150-158](../../../app/Filament/Resources/Mensagens/MensagemResource.php#L150-L158)):
  `Select::make('relacionadas')->multiple()->searchable()->options(fn (?Mensagem $record) => …pluck('titulo','id'))`.
  Está **fora** do auto-sync de propósito (é simétrico) — via o trait [SincronizaRelacionadas](../../../app/Filament/Resources/Mensagens/Pages/SincronizaRelacionadas.php)
  (`capturarRelacionadas`/`aplicarRelacionadas`), plugado nas páginas.
- **O nível hoje NÃO é `->live()`** ([:115-118](../../../app/Filament/Resources/Mensagens/MensagemResource.php#L115-L118)):
  `Select::make('nivel')->options(self::NIVEIS)->helperText(...)` — **sem** `required` (aceita null). F4a **adiciona**
  `->live()` a esse Select (pré-requisito do `visible/required` condicional). As opções `self::NIVEIS`
  ([:53-59](../../../app/Filament/Resources/Mensagens/MensagemResource.php#L53-L59)) incluem `'direcionada' => 'Direcionada'`.

### 3.3 O precedente EXATO de "multiselect de pivô COM regra" — `ids_palestrantes` (Palestra)

[PalestraResource.php:81-88](../../../app/Filament/Resources/Palestras/PalestraResource.php#L81-L88): o campo
`ids_palestrantes` é o molde direto do campo de destinatários —
`Select::make('ids_palestrantes')->options(fn () => Palestrante::ativo()->orderBy('nome')->pluck('nome','id'))
->multiple()->searchable()->required()->minItems(1)->maxItems(2)` (opções `pluck`, **client-side** searchable sobre o
conjunto — sem `->relationship()`, sem `->preload()`), gravado por um **trait de sync manual** (`SincronizaPessoas`,
padrão irmão do `SincronizaRelacionadas`). E o `id_diretor` mostra a **validação cross-field server-side** via
`->rules([fn (Get $get): \Closure => function (...) use ($get) { … $fail(...); }])`
([:89-101](../../../app/Filament/Resources/Palestras/PalestraResource.php#L89-L101)). ⇒ **o padrão da casa para um
pivô-multiselect que carrega uma regra é `->options()` + sync manual + `->rules(fn (Get))`**, não `->relationship()`.

- **`Get`** = `Filament\Schemas\Components\Utilities\Get` (confirmado em [EventoForm.php:20](../../../app/Filament/Schemas/EventoForm.php#L20)
  e no `PalestraResource`; o mesmo import usado por 6+ Resources). O padrão `->rules([fn (Get $get): \Closure => …])`
  para regra que depende de OUTRO campo está em [EventoForm.php:89-95](../../../app/Filament/Schemas/EventoForm.php#L89-L95).

### 3.4 Admin-only + capacidade inerte (por que F4a não tem autorização)

- O `/admin` é **admin-only** (regra dura do CLAUDE.md; `User::canAccessPanel` → `hasRole('administrador')`). Os testes
  de Resource usam `$this->actingAsAdmin()` no `setUp` ([MensagemResourceTest.php:24-28](../../../tests/Feature/Filament/MensagemResourceTest.php#L24-L28)).
- A capacidade `mensagem.*` **existe mas é inerte** (só admin edita via `Gate::before`); os diretores-DEPAE e médiuns
  **não** são admin. ⇒ F4a é operada **só** pelo admin; **toda** a autorização por setor/cargo é **F4b**.

### 3.5 As páginas Create/Edit (onde o sync manual se pluga) e o molde de teste

- [CreateMensagem](../../../app/Filament/Resources/Mensagens/Pages/CreateMensagem.php): `use SincronizaRelacionadas`;
  `mutateFormDataBeforeCreate` → `capturarRelacionadas($data)`; `afterCreate` → `aplicarRelacionadas($this->record)`.
- [EditMensagem](../../../app/Filament/Resources/Mensagens/Pages/EditMensagem.php): idem + `mutateFormDataBeforeFill`
  hidrata `relacionadas` ([:24-29](../../../app/Filament/Resources/Mensagens/Pages/EditMensagem.php#L24-L29):
  `$data['relacionadas'] = $this->record->relacionadas()->pluck('mensagens.id')->all()`). ⇒ **o molde do fill/capture/
  aplicar dos destinatários (se O1 = manual) copia exatamente esse trio.**
- **Molde de teste — [MensagemResourceTest](../../../tests/Feature/Filament/MensagemResourceTest.php):**
  `Livewire::test(CreateMensagem::class)->fillForm([...])->call('create')->assertHasNoFormErrors()`;
  `assertFormFieldExists('autores', fn (Select $f) => $f->isMultiple())`; e o espelho de sync
  [test_criar_com_relacionadas_espelha:117-135](../../../tests/Feature/Filament/MensagemResourceTest.php#L117-L135)
  (fillForm com `relacionadas => [$b->id]`, `call('create')`, assert do pivô nos dois lados) — **molde direto** do
  teste "cria direcionada anexa destinatários". `assertHasFormErrors(['destinatarios'])` prova o **vermelho** do "≥1".
- **Factory — [MensagemFactory](../../../database/factories/MensagemFactory.php):** `comNivel($nivel)`
  ([:51-56](../../../database/factories/MensagemFactory.php#L51-L56)), `pendente()`, `publica()`; default `nivel=null`,
  `status=publicado`. **Não** há helper de destinatários — os testes anexam via `->destinatarios()->sync([$u->id])`
  (molde [MensagemDestinatarioTest:23](../../../tests/Feature/Mensagens/MensagemDestinatarioTest.php#L23)).

### 3.6 Dimensão do dado (para dimensionar o Select)

~**145 usuários** no dev (o kickoff cita 145). Conjunto **pequeno e limitado** ⇒ `->options(User::orderBy('name')
->pluck('name','id'))` com `->searchable()` **client-side** (molde `ids_palestrantes`) é adequado — não precisa de
busca server-side (`->relationship()->searchable()` sem `preload`). (Reconferir a contagem no dev antes do plano; o
comportamento **não** depende do número, só o dimensionamento do Select.)

---

## 4. Sem handoff de design (é form de `/admin`)

F4a é **UI de painel** (Filament nativo) — **não** há `design_handoff_*` para o form do Resource, e **não** se cria
página Blade nova. O visual é o do `/admin` (tema CEMA já existente). O único artefato visual é o `Select` +
`helperText`, no padrão dos campos vizinhos. **Nada** de front público, hero, card ou grade nesta fatia.

---

## 5. Invariantes (cada um vira teste que reprova)

| # | Invariante | Teste (§9) |
|---|---|---|
| **I1** | **Campo condicional existe:** o form tem um `Select` `destinatarios`, **múltiplo**; ele é **visível** quando `nivel='direcionada'` e **oculto** para qualquer outro nível (ou nível null). O `Select` de `nivel` é `->live()`. | §9.1 |
| **I2** | **Direcionada exige ≥1 (o vermelho), no create E no edit:** salvar `nivel='direcionada'` **sem** destinatário → **`assertHasFormErrors(['destinatarios'])`** (não persiste); no **edit**, remover **todos** os destinatários de uma direcionada também reprova o `required` **e não apaga** o pivô original. Com ≥1 → `assertHasNoFormErrors` e o pivô é anexado. | §9.2/§9.3 |
| **I3** | **Anexa / auto-fill / re-sincroniza / PRESERVA:** criar direcionada com `[u1,u2]` → pivô = exatamente esses ids (molde `test_criar_com_relacionadas`); **abrir o edit PRÉ-PREENCHE** o Select (`assertFormSet` — prova o `mutateFormDataBeforeFill`); re-sincronizar (`[u1,u2]`→`[u2,u3]`) reflete o novo conjunto; **editar só o título PRESERVA** `[u1,u2]` (sem o fill, o `sync([])` apagaria o pivô — corrupção que o teste de re-sync sozinho **não** pega). | §9.2/§9.3 |
| **I4** | **Não-direcionada ⇒ pivô vazio (clear determinístico + guard server-side):** editar direcionada **trocando `nivel`→`publico`** esvazia o pivô (0 linhas); e o **guard não confia na UI** — teste-unidade do trait: `capturarDestinatarios(['nivel'=>'publico','destinatarios'=>[u]])` devolve `[]` (o guard **vence o payload** — o caminho server-side que o teste de UI **não** discrimina, pois o campo oculto já chega ausente no `$data`). Criar/salvar qualquer não-direcionada nunca cria linha de pivô. | §9.3 |
| **I5** | **Nível não-direcionada não obriga destinatário:** salvar `nivel='publico'` (ou null, ou `trabalhadores`) **sem** destinatário → `assertHasNoFormErrors` (o `required` é **condicional**; uma pública legítima tem zero). | §9.2 |
| **I6** | **Leitura intacta (a F4a alimenta, não muda):** após criar uma direcionada com destinatário `u`, o resolvedor 3A concorda — `mensagem->podeSerVistoPor($u)===true` e `podeSerVistoPor($outro)===false`; e a mensagem entra em `u->mensagensDirecionadas()->publicado()->where('nivel',Direcionada)` (o núcleo da 3C) — **ponte** F4a→3A→3C provada de ponta a ponta. | §9.4 |
| **I7** | **Coluna-contador + filtro (O2):** a tabela do Resource expõe `destinatarios_count` (contagem do pivô — 2 numa direcionada de 2, 0 numa pública) e o filtro "tem destinatário" (`has('destinatarios')`) restringe a lista às mensagens com ≥1 destinatário; uma pública **some** sob o filtro. | §9.6 |
| **I-reg** | **Sem regressão:** os testes existentes do `MensagemResourceTest` (2A) + 3C/3B/3A + front + sitemap permanecem **verdes**; a suíte **~1081 + novos** verde; `Pint` verde. **Sem migration.** Nenhuma superfície de leitura muda de comportamento. | §9.5 |

---

## 6. Decisões de desenho

### 6.1 O campo `destinatarios` numa Section própria "Destinatários" (O3) — condicional ao nível

Decisão **O3 (dono):** o campo vive numa **Section própria "Destinatários"** (**não** dentro de "Autoria e relações"),
com a **Section inteira `->visible(...)`** ao nível Direcionada e o **`->required(...)` mantido no campo** dentro dela
(belt-and-suspenders). Tornar o `Select` de `nivel` **`->live()`**:

```php
// no Select de nível (linha ~115): acrescentar ->live()
Select::make('nivel')
    ->label('Nível de acesso')
    ->options(self::NIVEIS)
    ->live() // pré-requisito do visible da Section / required condicional dos destinatários
    ->helperText('Só as Públicas aparecem no site (por ora). A visibilidade rica virá na próxima fase.'),

// nova Section (após "Autoria e relações"): some INTEIRA quando não é direcionada (O3)
Section::make('Destinatários')
    ->description('Usuários a quem esta mensagem direcionada foi endereçada.')
    ->visible(fn (Get $get): bool => $get('nivel') === VisibilidadeMensagem::Direcionada->value)
    ->schema([
        Select::make('destinatarios')
            ->label('Destinatários')
            ->helperText('Obrigatório para mensagens de nível "Direcionada".')
            ->options(fn () => \App\Models\User::orderBy('name')->pluck('name', 'id')) // ~145 users: client-side (molde ids_palestrantes)
            ->multiple()
            ->searchable()
            ->required(fn (Get $get): bool => $get('nivel') === VisibilidadeMensagem::Direcionada->value)
            ->minItems(1)
            ->columnSpanFull(),
    ]),
```

- **O3 — Section própria + `->visible()` na Section:** quando o nível não é Direcionada a Section **inteira** some
  (filhos ocultos não são validados — vendor confirmou, §13). O `->required(fn (Get))` **no campo** é
  belt-and-suspenders (garante o "≥1" mesmo se o campo for reusado/reordenado).
- **`->live()` no nível** é o pré-requisito do `->visible(...)` da Section e do `->required(...)` re-avaliarem ao trocar
  de opção. Comparação com `VisibilidadeMensagem::Direcionada->value` (`'direcionada'`) — importar o enum + `Get`
  (`Filament\Schemas\Components\Utilities\Get`) no Resource.
- **`->required(fn (Get))` + `->minItems(1)` (O4):** num `multiple()`, `required` já exige ≥1; o `minItems(1)` reforça e
  documenta a intenção (molde `ids_palestrantes`). Section oculta ⇒ Filament **não** valida ⇒ I5 (pública sem
  destinatário passa).
- **`->options()` (não `->relationship()`) — O1 = Opção B (ratificada, §13):** o campo tem regra e clear condicional,
  então segue o padrão da casa para pivô-multiselect-com-regra (`ids_palestrantes`/`relacionadas`), com **sync manual**
  (§6.2). **Client-side** searchable sobre ~145 (§3.6).
- **PII**: mostra `name` (o admin pode buscar por nome) — aceitável, admin-only (§3.4).

### 6.2 Sincronização do pivô — anexar E limpar (a decisão **O1**)

O pivô precisa ser **substituído** no save (anexar o conjunto quando direcionada) e **esvaziado** quando o nível não é
direcionada — **de forma determinística**, independente de a UI ter escondido o campo (CLAUDE.md-9). Auto-sync
(`->relationship()`) cobre bem o **anexar** (prova: `autores`), mas o **clear condicional** com campo `->visible(false)`
**NÃO acontece por auto-sync** — **confirmado no vendor do Filament v5** (passe interno, §13): o `saveRelationships()`
de um componente **oculto** é **pulado** (early-return no gate `isHidden() && ! shouldSaveRelationshipsWhenHidden()`,
com `shouldSaveRelationshipsWhenHidden` default `false` — `vendor/filament/schemas/src/Components/Concerns/BelongsToModel.php:44,51-53`)
⇒ o pivô fica com as **linhas velhas** (não é esvaziado). Duas opções:

**Opção B — sync MANUAL (RECOMENDADA), molde `relacionadas`/`ids_palestrantes`:** um trait
`SincronizaDestinatarios` (irmão de `SincronizaRelacionadas`), plugado nas páginas. **Um só caminho** determinístico
para anexar **e** limpar, visibilidade-independente:

```php
// app/Filament/Resources/Mensagens/Pages/SincronizaDestinatarios.php (novo trait)
// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-20
trait SincronizaDestinatarios
{
    /** @var array<int, int|string> */
    protected array $idsDestinatarios = [];

    protected function capturarDestinatarios(array $data): array
    {
        // Integridade: só direcionada carrega destinatário; qualquer outro nível => vazio (limpa).
        $ehDirecionada = ($data['nivel'] ?? null) === VisibilidadeMensagem::Direcionada->value;
        $this->idsDestinatarios = $ehDirecionada ? ($data['destinatarios'] ?? []) : [];
        unset($data['destinatarios']); // fora do auto-sync do Filament

        return $data;
    }

    protected function aplicarDestinatarios(Mensagem $mensagem): void
    {
        $mensagem->destinatarios()->sync(
            collect($this->idsDestinatarios)->map(fn ($id) => (int) $id)->unique()->values()->all()
        );
    }
}
```

Plugagem nas páginas (compõe com o trait `SincronizaRelacionadas` já existente):
- **CreateMensagem**: `mutateFormDataBeforeCreate` → `capturarDestinatarios($this->capturarRelacionadas($data))`;
  `afterCreate` → `$this->aplicarRelacionadas(...); $this->aplicarDestinatarios($this->record);`.
- **EditMensagem**: `mutateFormDataBeforeFill` → **hidratar** `$data['destinatarios'] = $this->record->destinatarios()
  ->pluck('users.id')->all();` (molde do fill de `relacionadas`, [:26](../../../app/Filament/Resources/Mensagens/Pages/EditMensagem.php#L26));
  `mutateFormDataBeforeSave` → captura ambos; `afterSave` → aplica ambos.
- `->sync([])` numa não-direcionada esvazia o pivô (I4) — **determinístico**, mesmo com o campo oculto.

**Opção A — auto-sync `->relationship('destinatarios','name')` + GUARD de clear:** manter o auto-sync do Filament para
o anexar (dono's prior no kickoff) e garantir o clear com um detach explícito no after-hook:
`if ($this->record->nivel !== VisibilidadeMensagem::Direcionada->value) { $this->record->destinatarios()->detach(); }`.
Ganha busca server-side + auto-fill de graça; **mas** o vendor confirma que o componente oculto é **pulado** (deixa
linhas velhas) ⇒ a Opção A **exige obrigatoriamente** o guard de `detach()` — **dois mecanismos** (auto-sync + guard
manual) para um único pivô. **Menos conservador** que B.

**Recomendação (CLAUDE.md-36, opção mais conservadora/determinística): Opção B — RESOLVIDA no passe interno (vendor).**
Um só caminho, visibilidade-independente, espelha o irmão `relacionadas` e o precedente `ids_palestrantes`, e **dispensa**
o guard extra que a Opção A obrigaria. O passe interno **já confirmou no vendor** que a Opção A não esvazia sozinha
(§13, evidência `arquivo:linha`) e o **consultor RATIFICOU O1 = Opção B** (§13). **Seja qual for a opção, os testes
I2/I3/I4 são idênticos** (anexar, auto-fill, re-sync, preservação e **clear discriminante** — §9.3).

### 6.3 Coluna-contador + filtro "tem destinatário" na tabela (O2 — ENTREGUE nesta fatia)

Decisão **O2 (dono): ENTRA na F4a.** Na `table()` do `MensagemResource`:
- **Coluna:** `TextColumn::make('destinatarios_count')->counts('destinatarios')->label('Destinatários')->badge()
  ->toggleable()` — quantos destinatários cada mensagem tem (0 nas não-direcionadas); `->toggleable()` p/ não poluir
  a tabela por padrão.
- **Filtro "tem destinatário":** um toggle que restringe às mensagens com ≥1 destinatário —
  `Filter::make('com_destinatarios')->label('Tem destinatário')->query(fn (Builder $q) => $q->has('destinatarios'))`
  (importar `Illuminate\Database\Eloquent\Builder` + `Filament\Tables\Filters\Filter`).

Ambos são **read-only na tabela** (não mexem no pivô). Teste em §9.6 (I7).

### 6.4 A11y, performance, segurança (guardrails herdados)

- **A11y/UX**: o `Select` segue os componentes vizinhos (label, helperText); o painel Filament já é acessível.
- **Performance**: `->options()` sobre ~145 users é uma query leve no render do form; `->sync()` é uma escrita curta.
  Sem N+1 (não há listagem de cards aqui — é form de admin).
- **Segurança**: `/admin` admin-only + CSRF do Filament; a integridade "direcionada ⇔ destinatário" é reasserida no
  **servidor** (o `capturarDestinatarios` decide pelo `nivel`, não confia no que a UI mandou) — mesmo espírito do
  "campos privilegiados reasseridos no servidor" do CLAUDE.md, aqui aplicado à consistência do pivô.

---

## 7. As peças (inventário)

**Novos (cabeçalho de autoria no PHP — CLAUDE.md §8):**
`app/Filament/Resources/Mensagens/Pages/SincronizaDestinatarios.php` (se O1 = Opção B) ·
`tests/Feature/Filament/MensagemDestinatariosResourceTest.php` (ou novos testes no `MensagemResourceTest`, §9).

**Editados (aditivo/cirúrgico):**
`app/Filament/Resources/Mensagens/MensagemResource.php` (**+Section "Destinatários"** com o campo `destinatarios`,
**+`->live()`** no `nivel`, **+coluna `destinatarios_count`** + **filtro "tem destinatário"** na tabela, **+imports**
`VisibilidadeMensagem`/`Get`/`Builder`/`Filter`) ·
`app/Filament/Resources/Mensagens/Pages/CreateMensagem.php` (+plugagem do sync de destinatários) ·
`app/Filament/Resources/Mensagens/Pages/EditMensagem.php` (+fill/capture/aplicar de destinatários).

**NÃO toca (fronteiras, §11):** `/minha-conta` inteiro (F4b) · o resolvedor 3A (`podeSerVistoPor`/`visiveisPara`/
`visibilidade`/enum) · a aba/lista/controller da 3C · a barreira/single da 3B · `Mensagens\Lista`/`MensagemController`
· o front público · o sitemap · Autores · o importador `cema:importar-direcionadas` · o pivô/migration (só **escreve**
via `sync`) · o `Mensagem` model (a relação `destinatarios()` já existe). **Sem migration.**

---

## 8. Cutover (o que roda no deploy — do dono)

F4a **não tem migration nem seeder** (o pivô veio da 3A). Deploy padrão de `/admin` (mudança só de PHP):
1. `git pull` (código) — sem novas dependências Composer.
2. `php artisan optimize:clear` + `docker compose restart app worker` ([[dev-opcache-restart-app-worker]]) — recompila
   o Resource/páginas (OPcache `validate_timestamps=0` no dev/prod ⇒ **restart obrigatório**). **Sem** `npm run build`
   (não há Blade/CSS novo — é form de painel).

**Ciência:** a partir da F4a, o admin cria/edita uma direcionada **completa** pelo painel (título + nível Direcionada +
**destinatários**) e ela passa a ser vista pelos destinatários (single 3B) e a aparecer na aba pessoal deles (3C). O
importador continua sendo a via de massa (histórico do legado); F4a é a via **manual/curatorial**. Nada muda para
quem não é admin (F4b abre isso).

---

## 9. Plano de teste (TDD real, vermelho primeiro)

Testes de Resource usam `Tests\TestCase` + `RefreshDatabase`, `$this->actingAsAdmin()` no `setUp` (molde
`MensagemResourceTest`), `Livewire::test(CreateMensagem/EditMensagem::class)`, `MensagemFactory` (`comNivel`,
`pendente`, `publica`) e `->destinatarios()->sync([...])` para pré-vincular. **Todo brief de subagente que rode
`artisan` DEVE proibir `migrate:fresh/refresh/wipe/reset` + seed destrutivo** e reafirmar `legado` read-only
([[nunca-migrate-fresh-no-dev]]).

### 9.0 Ordenação (constraint)
Campo + `->live()`/`visible`/`required` (§6.1) e o sync/guard (§6.2) **antes** dos testes de integridade. Sequência:
existência/condicionalidade do campo (I1) → `required` condicional + anexar no create (I2-create/I5) →
**teste-unidade do guard** (I4-guard, isola o trait) → auto-fill + preservação + re-sync + required-no-edit + clear-UI
(I3/I2-edit/I4-clear) → ponte F4a→3A→3C (I6) → regressão (I-reg). **Dois testes-do-vermelho não-vacuous:** (a) I2 —
salvar direcionada **sem** destinatário **falha** (reprova antes do `required`); (b) I4-guard — o trait devolve `[]`
para não-direcionada com payload preenchido (reprova antes do guard de `nivel` existir). Cada um deve ser visto
**reprovar** antes da implementação correspondente.

### 9.1 Existência e condicionalidade do campo — I1
APIs de teste confirmadas no vendor v5.6 (§13): `isMultiple()`/`isLive()` são métodos reais alcançáveis pelo callback
do `assertFormFieldExists`; preferir os asserts vigentes **sem `Is`** — `assertFormFieldHidden`/`assertFormFieldVisible`
(os `...IsHidden`/`...IsVisible` existem mas o `...IsVisible` está `@deprecated`).
- `assertFormFieldExists('destinatarios', fn (Select $f) => $f->isMultiple())`;
- `assertFormFieldExists('nivel', fn (Select $f) => $f->isLive())`;
- **visibilidade condicional**: `fillForm(['nivel' => 'direcionada'])` → `assertFormFieldVisible('destinatarios')`;
  `fillForm(['nivel' => 'publico'])` (e null) → `assertFormFieldHidden('destinatarios')`.

### 9.2 Direcionada exige ≥1 (o vermelho) + não-direcionada não obriga — I2/I5
- **I2 (falha):** `Livewire::test(CreateMensagem::class)->fillForm([titulo, slug, formato, status=publicado,
  nivel='direcionada' /* SEM destinatarios */])->call('create')->assertHasFormErrors(['destinatarios'])` e
  `assertDatabaseMissing('mensagens', ['slug' => ...])` (não persistiu). **Este é o teste-do-vermelho** — deve reprovar
  **antes** de o `required` condicional existir.
- **I2 (sucesso):** mesma coisa **com** `destinatarios => [$u1->id, $u2->id]` → `assertHasNoFormErrors`; a mensagem
  existe e `Mensagem::where('slug',...)->first()->destinatarios()->count() === 2` com os ids certos (molde
  [test_criar_com_relacionadas:117-135](../../../tests/Feature/Filament/MensagemResourceTest.php#L117-L135)).
- **I5:** `fillForm([... nivel='publico', SEM destinatarios])->call('create')->assertHasNoFormErrors` (o `required` é
  condicional — pública sem destinatário passa); idem `nivel=null` e `nivel='trabalhadores'`.

### 9.3 Auto-fill, re-sync, preservação, required-no-edit e CLEAR determinístico — I2/I3/I4
> **Achados do passe adversarial (cobertura) incorporados:** o teste de re-sync sozinho é **enganoso** — o `fillForm`
> sobrescreve o estado, então ele passa **mesmo com o `mutateFormDataBeforeFill` quebrado**; e o teste de clear via UI
> passa **com ou sem** o guard de nível (o campo oculto já chega ausente no `$data`). Daí os testes **discriminantes**
> abaixo (`assertFormSet`, preservação, e o **teste-unidade do trait**), sem os quais uma regressão do fill/guard
> passaria silenciosa. Molde do `assertFormSet` já na casa: `PalestraResourceTest::test_edit_preenche_selects_a_partir_do_pivo`.

- **I3-fill (auto-fill do edit):** direcionada com `->destinatarios()->sync([$u1->id,$u2->id])`;
  `Livewire::test(EditMensagem::class, ['record'=>$m->getRouteKey()])->assertFormSet(['destinatarios' =>
  [$u1->id,$u2->id]])` — **prova** o `mutateFormDataBeforeFill` (sem ele, o Select abriria vazio).
- **I3-preservação (editar SÓ o título não apaga o pivô):** mesma direcionada; `fillForm(['titulo'=>'Novo'])->call('save')
  ->assertHasNoFormErrors`; `$m->fresh()->destinatarios()->pluck('users.id')` = ainda `[$u1,$u2]` — **o teste que pega a
  corrupção** (sem o fill, o save enviaria `destinatarios` vazio e `sync([])` apagaria o pivô).
- **I3-resync (troca do conjunto):** `fillForm(['destinatarios'=>[$u2->id,$u3->id]])->call('save')->assertHasNoFormErrors`;
  `$m->fresh()->destinatarios` = exatamente `[u2,u3]`.
- **I2-edit (required no caminho de edição):** direcionada com `sync([$u1])`; editar **removendo todos** →
  `fillForm(['destinatarios'=>[]])->call('save')->assertHasFormErrors(['destinatarios'])`; e o pivô original **não** é
  apagado (`assertSame(1, DB::table('mensagem_destinatario')->where('mensagem_id',$m->id)->count())`).
- **I4-clear-UI (troca de nível esvazia):** direcionada com `sync([$u1])`; `fillForm(['nivel'=>'publico'])->call('save')
  ->assertHasNoFormErrors`; `assertSame(0, DB::table('mensagem_destinatario')->where('mensagem_id',$m->id)->count())`.
- **I4-guard (teste-unidade do trait — DISCRIMINANTE):** instanciar/exercitar `capturarDestinatarios(['nivel'=>'publico',
  'destinatarios'=>[$u->id]])` e assertar `idsDestinatarios === []` (o guard **vence o payload** — reasserção server-side
  que **não** confia na UI). **Sem este teste, remover o guard mantém todos os outros verdes** (o campo oculto já chega
  ausente no `$data`, então o clear-UI passaria de qualquer jeito). É o teste que amarra o §6.4 ("decide pelo `nivel`,
  não confia no que a UI mandou").
  - **⚠️ HARNESS (cravar no plano):** `capturarDestinatarios` é `protected` do trait `SincronizaDestinatarios`. O teste
    precisa de uma **classe anônima** que `use SincronizaDestinatarios` e exponha o método por um wrapper público
    (`public function exec(array $d) { return [$this->capturarDestinatarios($d), $this->idsDestinatarios]; }`) — ou
    Reflection. Sem esse harness o executor trava. (Molde: qualquer teste que exercite um trait de Page fora da Page.)

### 9.4 Ponte F4a → 3A → 3C (integração end-to-end) — I6
- **⚠️ `$u` e `$outro` são `User::factory()` PUROS** (sem papel admin/presidente) — senão o `podeSerVistoPor` retorna
  `true` por **bypass** (`veTudo`, [Mensagem.php:87-99](../../../app/Models/Mensagem.php#L87-L99)) e o assert mascara o
  ramo Direcionada. `User::factory()->create()` nasce `nivelMaximo()=0` e não-presidente ⇒ exercita o ramo real.
- Criar (pela página) uma direcionada publicada com destinatário `$u`; então **sem** reabrir o form:
  `$m->fresh()->podeSerVistoPor($u) === true`; `->podeSerVistoPor($outro) === false`; e
  `$u->mensagensDirecionadas()->publicado()->where('nivel', VisibilidadeMensagem::Direcionada->value)->exists() ===
  true` (o mesmíssimo predicado da aba/lista da 3C, [[camada-4-fatia-3c-minhas-direcionadas-spec]]) — **opcional** amarrar
  ainda mais forte: `AbaDirecionadas::visivelPara($u) === true` (evita drift se o predicado inline e o real divergirem).
  Prova que o dado que F4a escreve é **exatamente** o que a 3A/3C leem — sem tocar nenhum deles.

### 9.5 Regressão + neutralidade + suíte — I-reg
Baseline **~1081** (`--list-tests`); alvo **~1081 + novos**, verde. **Nenhum** teste existente do `MensagemResourceTest`
(2A) muda de cor (o `nivel` ganhar `->live()` e o novo campo oculto por padrão **não** afetam os creates sem nível dos
testes atuais — `test_cria_mensagem_com_corpo_sanitizado`, `test_criar_com_relacionadas`, etc. têm `nivel=null` ⇒
campo oculto/não-obrigatório). 3C/3B/3A/front/sitemap **verdes**. **Conferir no `/admin`:** `restart app worker` +
logar como admin → criar uma direcionada com destinatário, ver a **Section "Destinatários"** aparecer só no nível
Direcionada, salvar, editar trocando o nível e confirmar o pivô esvaziado (Adminer), e a **coluna/filtro** na lista.
`Pint` verde ([[pint-antes-de-push]], [[flaky-importadorblog-gd-cap-imagem]]).

### 9.6 Coluna-contador + filtro "tem destinatário" na tabela — I7
- **Coluna:** seed de uma direcionada com `->destinatarios()->sync([$u1->id,$u2->id])` e uma `->publica()` (0
  destinatários); `Livewire::test(ListMensagens::class)` → assert de que a linha da direcionada mostra `2` e a da
  pública `0` na coluna `destinatarios_count` (a coluna é `->toggleable()` — se começar oculta, ligar via
  `->toggleTableColumn` no teste, ou assertar via `assertCanSeeTableRecords` + o estado da coluna conforme a API v5).
- **Filtro:** aplicar o filtro `com_destinatarios` (via `->filterTable('com_destinatarios')`) →
  `assertCanSeeTableRecords([$direcionada])->assertCanNotSeeTableRecords([$publica])` (a pública, sem pivô, **some**).
  Confirmar os nomes exatos dos métodos de teste de tabela do Filament v5 no 1º RED.

---

## 10. Fora de escopo (F4b/F5 e o resto — não fazer agora)

- **F4b (fluxo não-admin):** médium **cria** rascunho no `/minha-conta`; diretor-DEPAE **publica**; máquina de estados
  formal; ação "publicar"; **capacidades `mensagem.*` atribuídas** por setor/cargo. **Toda** a autorização vai aqui.
- **F5 (engajamento):** lida/não-lida, favoritar, "vistas recentemente" (pivô `mensagem_lidas` — não criar).
- **Constraint de banco** para "só direcionada tem destinatário": não há como expressar isso em FK/CHECK simples; F4a
  garante pela via de escrita (form + sync). Um **observer** de model seria belt-and-suspenders, mas poderia colidir com
  o importador — **fora** da F4a (candidato a endurecimento em F4b, se o dono quiser).
- **`->relationship()` server-side search / preload:** só se O1 fechar na Opção A (§6.2).
- **Dark mode / mudança de tema do painel:** não.

---

## 11. Fronteiras: o que toca × o que NÃO toca

**Toca (novo):** `SincronizaDestinatarios` (se O1=B) · testes.
**Toca (edição cirúrgica):** `MensagemResource` (+Section "Destinatários", +`->live()` no nível, +coluna/filtro,
+imports) · `CreateMensagem` / `EditMensagem` (+plugagem do sync/fill).
**NÃO toca:** `/minha-conta` inteiro (F4b) · resolvedor 3A (`podeSerVistoPor`/`visiveisPara`/`visibilidade`/enum) ·
aba/lista/controller da 3C · barreira/single da 3B · `Mensagens\Lista`/`MensagemController` · front público · sitemap ·
Autores · importador `cema:importar-direcionadas` · o pivô/migration (só **escreve** via `sync`) · o `Mensagem` model
(a relação já existe) · núcleo de capacidade (policies/matriz — inerte, F4b). **Sem migration. Sem mudança de
comportamento em nenhuma superfície de leitura** (aditiva de escrita) — nenhum teste de leitura muda de cor.

---

## 12. Ciências (não são tarefa desta fatia)

- **F4a é a primeira ESCRITA do pivô pela UI** — antes, só o importador (rel 38 reversa) o populava. A partir daqui há
  duas vias: massa (importador, histórico) e manual (admin, curatorial). Ambas produzem o mesmo formato de pivô que a
  3A/3C leem.
- **"Direcionada sem destinatário = invisível a todos"** é a razão do `required` — não é cosmético: o resolvedor 3A
  exige `destinatarios()->whereKey($user)->exists()`; zero destinatários ⇒ zero visibilidade ⇒ dado inútil/enganoso.
- **A integridade é reasserida no servidor** (`capturarDestinatarios` decide o conjunto pelo `nivel`, não confia na
  UI) — mesmo espírito do "POST não confiável" do CLAUDE.md, aqui pela **consistência do pivô** (evita pivô fantasma
  numa mensagem que deixou de ser direcionada).
- **A 3C já blinda a leitura** com `where('nivel', Direcionada)` (blindagem O5 da 3C): mesmo que F4a algum dia deixasse
  escapar um pivô anômalo, a 3C não o mostraria. F4a fecha a torneira na **origem**; a 3C é a rede na **leitura**. As
  duas juntas são defesa em profundidade.
- **O nível `->live()`** re-renderiza o form ao trocar de opção; isso é o que faz o campo aparecer/sumir e o `required`
  re-avaliar. Não há `afterStateUpdated` no `nivel` (diferente do `titulo`→`slug`), então `->live()` não colide com nada.

---

## 13. Passes adversariais — próprio (5 verificadores, ✅) + Consultor (✅ APROVADA); O1–O4 FECHADOS

> **Passe interno RODADO (5 verificadores adversariais paralelos, general-purpose, contra o código real + o vendor
> Filament v5):** terreno · técnica/vendor do sync-clear · forks/fronteiras · cobertura de teste · molde/consistência.
> **Veredito: SÓLIDO em 4 dimensões; `ajustes_menores` só na cobertura** (3 achados incorporados abaixo). Base da
> verificação: leitura direta de `MensagemResource` (os dois padrões de multiselect), `Mensagem::destinatarios`/
> `scopePublicado`/`podeSerVistoPor`/`scopeVisiveisPara`, a migration do pivô, `CreateMensagem`/`EditMensagem` + o trait
> `SincronizaRelacionadas`, `PalestraResource` (`ids_palestrantes` + `->rules(fn (Get))`), `EventoForm` (import de `Get`),
> `MensagemResourceTest`/`MensagemFactory`, `VisibilidadeMensagem` — todos com **evidência `arquivo:linha`** (§3). O campo
> `destinatarios` **confirmado inexistente** no form. Metadados (base `93999e8`, PR #41, branch, suíte 1081) conferidos.

**O1 — mecanismo de sync do pivô — RESOLVIDA no passe interno (vendor); ratificar com o dono.** O verificador leu o
vendor e **provou**: um `Select->relationship()->multiple()` **oculto** tem o `saveRelationships()` **pulado**
(early-return em `vendor/filament/schemas/src/Components/Concerns/BelongsToModel.php:44,51-53`;
`shouldSaveRelationshipsWhenHidden` default `false`) ⇒ **não** esvazia, deixa linhas velhas. Logo a **Opção A obrigaria
um guard de `detach()`** (dois mecanismos p/ um pivô); a **Opção B (sync manual) é a escolha determinística** (§6.2).
Recomendação forte = **Opção B**; o dono só ratifica.

**O4 — `required` condicional + `minItems(1)` — PRÉ-FECHADA** (evita parecer relitígio da decisão travada "≥1
obrigatório", §2.2): manter `->required(fn (Get))` **e** `->minItems(1)` como reforço/documentação (§6.1). Um `multiple`
`required` já exige ≥1; o `minItems(1)` documenta a intenção (molde `ids_palestrantes`).

**Achados de COBERTURA incorporados (o passe pegou 3 buracos reais de teste — §5/§9 já atualizados):**
- **I4 não discriminava o guard:** o clear-UI passa **com ou sem** o guard de `nivel` (campo oculto chega ausente no
  `$data`). ⇒ **novo teste-unidade do trait** (I4-guard, §9.3) que prova `capturarDestinatarios(nivel≠direcionada,
  destinatarios=[u]) === []`. Sem ele, remover o guard mantém tudo verde (regressão silenciosa).
- **I3 não provava o auto-fill nem a preservação:** o `fillForm` sobrescreve o estado ⇒ passava com o fill quebrado.
  ⇒ **`assertFormSet` do pré-preenchimento** + **teste de preservação** (editar só o título não apaga o pivô), senão
  editar uma direcionada **corromperia** os destinatários (§9.3).
- **`required` só no create:** ⇒ **teste do required no edit** (remover todos os destinatários de uma direcionada
  reprova e não apaga o pivô) (§9.3).

**Decisões de produto/UI (O2/O3) — FECHADAS pelo dono no passe do consultor** (detalhe na subseção abaixo): O2 **entra**
(coluna+filtro); O3 = **Section própria "Destinatários"** com `->visible` na Section.

**Regras sempre:** pt-BR em tudo; cabeçalho de autoria no PHP novo; `Pint` antes do push; `docker compose exec -T app
php artisan test`; **sem** `npm run build` (form de painel); **todo brief de subagente que rode `artisan` DEVE proibir
`migrate:fresh/refresh/wipe/reset` e seed destrutivo** e reafirmar `legado` read-only ([[nunca-migrate-fresh-no-dev]]).

---

### Passe do CONSULTOR (20/jul) — veredito: ✅ APROVADA, zero bloqueador

O Consultor verificou o ponto técnico central (O1) **contra o vendor** e confirmou:
`shouldSaveRelationshipsWhenHidden` **default `false`** ([vendor/filament/schemas/src/Components/Concerns/BelongsToModel.php:23])
+ early-return em `:51-53` ⇒ um `Select->relationship()` **oculto NÃO esvazia o pivô** ⇒ a Opção A obrigaria um
`detach()` extra; a **Opção B (sync manual) é a determinística**. Molde `SincronizaRelacionadas`, o namespace de `Get`
(`Filament\Schemas\Components\Utilities\Get`) e a **neutralidade** (os testes 2A usam `nivel=null` ⇒ Section oculta)
também conferem.

**Ratificado (técnico, do Consultor):**
- **O1 = Opção B** — trait `SincronizaDestinatarios` (`capturar` com **guard de nível** + `aplicar` via `->sync()`). Um
  caminho, visibilidade-independente.
- **O4 = `->required(fn (Get))` condicional + `->minItems(1)`.**

**Decidido pelo dono:**
- **O2 = ENTRA na F4a** — `TextColumn::make('destinatarios_count')->counts('destinatarios')->badge()->toggleable()` +
  filtro "tem destinatário" (`has('destinatarios')`). Deixa de ser opcional (§6.3).
- **O3 = Section própria "Destinatários"** (não dentro de "Autoria e relações"), com a **Section inteira
  `->visible(fn (Get) => nivel===Direcionada)`** — some inteira quando não-direcionada — **mantendo** o `->required(fn
  (Get))` no campo (belt-and-suspenders) (§6.1).

**Nota de execução (cravada p/ o plano):** o teste-unidade do guard (I4-guard) exercita um método **`protected`** de
trait ⇒ precisa de **harness** (classe anônima que `use` o trait e expõe o método, ou Reflection — §9.3). Confirmar no
1º RED as APIs Filament v5 (`isLive()`, `assertFormFieldVisible`/`assertFormFieldHidden` — o `...IsVisible` é
`@deprecated`; métodos de teste de tabela para o filtro).

**Destino:** SPEC **APROVADA** → **PLANO** (TDD real; ordem §9.0: campo/condicionalidade → **teste-unidade do guard** →
required/anexar no create → auto-fill/preservação/re-sync/required-no-edit/clear → ponte 3A/3C → coluna/filtro (O2) →
regressão), cobrindo I1–I7 e os **dois testes-do-vermelho não-vacuous** (I2 sem o `required`; I4-guard sem o guard de
`nivel`). **PARAR para o passe do plano** antes da execução. **Sem migration nesta fatia.**

---
