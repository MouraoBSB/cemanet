# Spec — Molde Filament-no-site · Avatares nas opções dos Selects do MensagemForm (foto circular + fallback de iniciais)

- **Base:** `origin/main` = `5a9696b` (molde Filament-no-site / paleta mesclado pelo PR #48).
- **Branch:** `avatares-selects-mensagem-form` (a criar de `5a9696b`).
- **Formato:** 1 PR, uma peça (só apresentação de opção). Sem blocos separados.
- **Data:** 2026-07-23.
- **Autor:** Thiago Mourão — https://github.com/MouraoBSB

> Enquadramento travado com o dono no kickoff desta fatia. As afirmações técnicas do kickoff (O1–O4) foram
> reverificadas contra o código real e o vendor Filament 5 por um passe de 4 investigadores — CONFIRMADAS,
> com 3 refinamentos incorporados como adendos `A1–A3` (§2). Sem migration, sem asset novo, sem `npm run build`.
>
> **Passe do dono (2026-07-23): APROVADA** — 1 obrigatório (**O1**) + refinamentos aplicados (§12); **P1–P5 ratificadas** (§11).

---

## 0. Recorte: o que esta fatia fecha (e o que ela NÃO é)

**Fecha:** a apresentação das OPÇÕES de 2 Selects do [MensagemForm.php](app/Filament/Schemas/MensagemForm.php) —
**Autores espirituais** (foto do `AutorEspiritual`) e **Destinatários** (foto do `PerfilMembro` do usuário) —
ganha um **avatar circular** com **fallback de iniciais** no mesmo idioma visual do single da mensagem. É a
próxima fatia do molde Filament-no-site, já **nomeada como "a próxima"** na SPEC anterior
([molde-paleta §1](docs/superpowers/specs/2026-07-23-molde-filament-no-site-paleta.md)).

**NÃO é:**
- Não toca **gravação nem pivô** — só a apresentação da opção (fronteira confirmada, §3.5).
- Não é uma mudança de dados: **sem migration, sem seeder, sem importador, sem asset, sem `npm run build`**.
- Não redesenha as telas nem os 3 formulários — só troca o rótulo das opções de 2 campos.
- Não mexe no `autor-fallback.svg` (é o retrato 3:4 do single de autores, outro fallback — F3).

---

## 1. Contexto e objetivo

Os 3 formulários de Mensagem (fonte única [MensagemForm.php](app/Filament/Schemas/MensagemForm.php),
consumida por `/admin`, `/minha-conta/mensagens` e `/minha-conta/curadoria`) hoje listam autores e
destinatários como **texto puro** (só o nome). Numa casa com 148 usuários e 19 autores, escolher pessoas por
uma lista de nomes iguais é lento e propenso a erro. O objetivo é **reconhecimento visual**: cada opção mostra
a **foto** da pessoa (miniatura circular) e, quando não há foto, um **círculo com as iniciais** — o mesmo
idioma já usado no single da mensagem ([show.blade.php:61-64](resources/views/mensagens/show.blade.php#L61-L64)).

Só a **APRESENTAÇÃO das opções** muda. O que é gravado (os ids no pivô), a validação de nível/publicação e a
sincronização de destinatários ficam **intactos** (§3.5).

---

## 2. Decisões travadas (não reabrir)

| # | Decisão | Origem |
|---|---|---|
| **D1** | Fallback sem foto = **iniciais em círculo** (NÃO Heroicon), reusando `->iniciais`. Idioma visual do single: círculo `1.75rem` (`size-7`), gradiente `#f2a81e → #d98a14` (canto sup-esq → inf-dir), texto `#3a3266`, negrito 10px. Com foto: `<img>` circular `object-fit:cover`. | Dono, kickoff |
| **D2** | Destinatários: **trocar o motor de busca para server-side** (`getSearchResultsUsing` + `getOptionLabelsUsing`), reabrindo conscientemente o O4 da F4b (que era `->options()` client-side). Motivo: `allowHtml` quebra a busca client-side (O3). | Dono, kickoff |
| **O1** | *(obrigatório)* **Eager-load das fotos** — sem N+1. Autores: 3º arg de `relationship()` com `->with('media')`. Destinatários: `->with('perfil.media')` nas duas closures (busca e hidratação). | Dono, kickoff |
| **O2** | *(obrigatório)* `allowHtml()` **não escapa** → `e()` no **nome** E na **URL**, sempre, nos dois Selects, ao montar o HTML da opção. | Dono, kickoff |
| **O3** | *(obrigatório)* Busca de destinatários **server-side sobre a coluna `name`** (não sobre o HTML). Autores já são imunes (relationship busca sobre `nome` no servidor). | Dono, kickoff |
| **O4** | *(obrigatório)* HTML da opção com **estilo INLINE** — não depender de classe utilitária do site (o dropdown injeta o HTML por `innerHTML`, fora do bundle do site). | Dono, kickoff |
| **F1** | *(fork/corte)* **Não tocar** `SincronizadorDestinatarios`, `saveRelationships` dos autores, `RegraPublicacao`. Só apresentação. | Dono, kickoff |
| **F2** | *(fork/corte)* **Não trocar autores por `->options()`** — `->relationship()` é obrigatório para `dehydrated(false)`/`saveRelationships()`. | Dono, kickoff |
| **F3** | *(fork/corte)* Não mexer no `autor-fallback.svg`. Sem migration, asset novo ou `npm run build`. | Dono, kickoff |
| **A1** | *(adendo — passe de verificação, FOCO A)* O `User` **não tem** `foto_thumb_url` **nem é `HasMedia`**: a foto vive no `PerfilMembro`. A foto do destinatário é `$user->perfil?->foto_thumb_url` (o `?->` é obrigatório — usuário pode não ter perfil), e o eager-load é `->with('perfil.media')` (`->with('media')` no `User` estoura `undefined relationship [media]`). Corrige a formulação do kickoff. | Verificação, [User.php:38-41](app/Models/User.php#L38-L41), [PerfilMembro.php:63-66](app/Models/PerfilMembro.php#L63-L66) |
| **A2** | *(adendo — FOCO B)* Eager-load dos autores **só** pelo **3º argumento posicional** de `relationship($name, $titleAttribute, $modifyQueryUsing)`. **`->modifyOptionsQueryUsing()` NÃO existe no `Select`** (é do `MorphToSelect\Type`). Descarta a alternativa "OU" do kickoff. | Verificação, [Select.php:781](vendor/filament/forms/src/Components/Select.php#L781) |
| **A3** | *(adendo — FOCO D)* Ao trocar `->options()`, o `getOptionLabelsUsing` dos destinatários **DEVE** cobrir todos os ids já selecionados **inclusive inativos** (`whereKey($values)` **sem** filtro `ativo`) — é o papel exato do `orWhereIn` de hoje. Sem isso, `getInValidationRuleValues()` reprova o save (Rule::in). Vira **I8**. | Verificação, [Select.php:1733-1775](vendor/filament/forms/src/Components/Select.php#L1733-L1775) |

---

## 3. Terreno confirmado por medição

### 3.1 Dev — números da casa (⚠️ snapshot 2026-07-23, reconferir no arranque)

Medido no dev vivo pelo dono e reconfirmado: **148 usuários ativos** (23 com foto, 125 sem) · **19 autores**
ativos (14 com foto, 5 sem). Ambos os extremos (com e sem foto) existem hoje → as duas telas exercitam
`<img>` **e** o fallback de iniciais.

### 3.2 Os 5 call sites (o que muda) — [MensagemForm.php](app/Filament/Schemas/MensagemForm.php)

| Select | Ocorrência | Linhas | Como está hoje |
|---|---|---|---|
| `autores` | `schemaAdmin` | [131-136](app/Filament/Schemas/MensagemForm.php#L131-L136) | `->relationship('autores','nome')->multiple()->preload()->searchable()` |
| `autores` | `schemaMedium` | [270-275](app/Filament/Schemas/MensagemForm.php#L270-L275) | idem |
| `autores` | `schemaCuradoria` | [362-367](app/Filament/Schemas/MensagemForm.php#L362-L367) | idem |
| `destinatarios` | inline em `schemaAdmin` | [153-169](app/Filament/Schemas/MensagemForm.php#L153-L169) | `->options(User::where('ativo',true)->orWhereIn('id',selecionados)->orderBy('name')->pluck('name','id'))->multiple()->searchable()` + `helperText` próprio |
| `destinatarios` | `blocoDestinatarios()` (médium + curadoria) | [196-215](app/Filament/Schemas/MensagemForm.php#L196-L215) | idem, sem `helperText`, `required($ehDirecionada)` |

O `orWhereIn('id', selecionados)` **não é decorativo**: inclui os já-selecionados mesmo inativos, senão o
`Rule::in` trava até um simples Salvar de título. O comentário longo em
[MensagemForm.php:156-164](app/Filament/Schemas/MensagemForm.php#L156-L164) e
[:187-192](app/Filament/Schemas/MensagemForm.php#L187-L192) documenta isso. **Todo refactor preserva esse papel**
— agora no `getOptionLabelsUsing` (A3/I8).

### 3.3 Vendor Filament — O1/O2/O3 verificados

- **O2 (allowHtml não escapa):** `allowHtml()` só guarda um bool ([CanAllowHtml.php:13-25](vendor/filament/forms/src/Components/Concerns/CanAllowHtml.php#L13-L25), com aviso de segurança do próprio vendor no comentário :9-11). O label vai para `innerHTML` **cru** em todos os pontos de render — opção do dropdown [select.js:558-562](vendor/filament/support/resources/js/utilities/select.js#L558-L562) **e** badge do item já selecionado ([:770-774](vendor/filament/support/resources/js/utilities/select.js#L770-L774)). ⇒ **obrigatório `e()` no nome e na URL**.
- **O1 (eager-load autores):** `relationship(string $name, string $titleAttribute, ?Closure $modifyQueryUsing, ...)` — o 3º arg aplica-se à query das opções e da busca ([Select.php:781,785](vendor/filament/forms/src/Components/Select.php#L781)). O HTML da opção vem de `getOptionLabelFromRecordUsing(fn ($record) => …)`, que recebe o **Model** (com `media` já eager, se o 3º arg fez `->with`) **depois** do `where` ([Select.php:1462-1486](vendor/filament/forms/src/Components/Select.php#L1462-L1486)). `->modifyOptionsQueryUsing()` **não** é método do `Select` (A2).
- **O3 (busca sobre HTML):** para `->options()`, o filtro é **client-side** e casa `option.label.toLowerCase().includes(query)` sobre o **HTML cru** ([select.js:1965-1969](vendor/filament/support/resources/js/utilities/select.js#L1965-L1969)) — buscar `'web'` casa `.webp` do `src`, `'img'` casa a `<img>`. O gatilho real é `hasDynamicSearchResults === false`, não "opções serem array estático" (o `->options(fn…)` atual é closure, mas a **busca continua client-side**). Com `getSearchResultsUsing` setado, `hasDynamicSearchResults` vira `true` e a busca é **100% server-side** ([Select.php:1595-1602](vendor/filament/forms/src/Components/Select.php#L1595-L1602)) → imune ao O3. `->relationship` já é imune (busca `where` sobre a coluna `nome`, [Select.php:1434-1460](vendor/filament/forms/src/Components/Select.php#L1434-L1460)).
- **O3-fix (motor server-side):** `getSearchResultsUsing(fn (string $search): array)` roda no servidor a cada tecla ([Select.php:487-492,701-718](vendor/filament/forms/src/Components/Select.php#L701-L718)); `getOptionLabelsUsing(fn (array $values): array)` (plural) hidrata os múltiplos já-selecionados ([Select.php:480-485,615-682](vendor/filament/forms/src/Components/Select.php#L615-L682)). Existem no v5.

### 3.4 Modelos e accessors (fallback pronto nos dois lados)

- **AutorEspiritual** — `use …, TemIniciais` ([AutorEspiritual.php:20](app/Models/AutorEspiritual.php#L20)); `->iniciais` deriva de `$this->nome` (default do trait, [TemIniciais.php:25-28](app/Models/Concerns/TemIniciais.php#L25-L28)); `foto_thumb_url` = `getFirstMediaUrl('foto','thumb') ?: null` ([AutorEspiritual.php:66-71](app/Models/AutorEspiritual.php#L66-L71)); é `HasMedia` → `->with('media')` válido.
- **User** — `use …, TemIniciais` e **sobrescreve** `nomeParaIniciais()` para `$this->name` ([User.php:93-96](app/Models/User.php#L93-L96)) — logo `$user->iniciais` funciona (a hipótese de quebra do kickoff **não** ocorre). **Não** é `HasMedia`; a foto está em `perfil` (A1).
- **PerfilMembro** — `foto_thumb_url` = `getFirstMediaUrl('foto','thumb') ?: null` ([PerfilMembro.php:63-66](app/Models/PerfilMembro.php#L63-L66)); é `HasMedia`. `User->perfil` = `hasOne(PerfilMembro)`, pode ser `null` ([User.php:38-41](app/Models/User.php#L38-L41)).
- **Referência visual** (o alvo do D1) — [show.blade.php:61-64](resources/views/mensagens/show.blade.php#L61-L64): `<img … class="size-7 rounded-full object-cover">`; senão `<span class="grid size-7 … rounded-full bg-gradient-to-br from-gold to-[#d98a14] … text-[10px] font-semibold text-[#3a3266]">{{ iniciais }}</span>`. Recriado em **estilo inline** (O4), não copiando as classes.

### 3.5 Fronteira da gravação — apresentação-only CONFIRMADO

- **Autores** gravam por `saveRelationships()` → `sync($state)`, `dehydrated(false)` para relationship múltipla ([Select.php:977,955-957,1404-1421](vendor/filament/forms/src/Components/Select.php#L977)). A fonte é o **array de ids** (state); o label/HTML **não** participa. `getOptionLabelFromRecordUsing` só muda o texto exibido.
- **Destinatários** gravam por `SincronizadorDestinatarios::aplicar()` a partir de `$dados['destinatarios']` (state de ids), filtrando inativos no gravar (`efetivos()`, I7) — [SincronizadorDestinatarios.php:53-75](app/Support/Mensagens/SincronizadorDestinatarios.php#L53-L75). `getSearchResultsUsing`/`getOptionLabelsUsing`/`allowHtml` afetam **só** exibição (e a allow-list de validação — A3). O valor submetido continua sendo o array de ids ([OptionsArrayStateCast](vendor/filament/forms/src/Components/Select.php#L1723-L1724)).
- **RegraPublicacao** lê só `nivel` + presença de `destinatarios` ([RegraPublicacao.php:18-36](app/Support/Mensagens/RegraPublicacao.php#L18-L36)); não conhece label de opção.

---

## 4. Invariantes (cada um vira teste que reprova, ou verificação explícita)

Todos sobre a **peça nova** (o helper `AvatarOpcao`) e as **duas closures** dos Selects. Os de N+1 no
runtime do dropdown são **verificação** (contagem de queries); os demais são teste unit/feature.

- **I1 — escapa o nome (O2).** `AvatarOpcao::html(null, '<img src=x onerror=alert(1)>"', 'AB')` produz saída em que o nome está **escapado** (`&lt;img …`), sem `<img` cru vindo do nome nem `"` que quebre atributo. *(teste unit — não vacuoso: asserir a sequência escapada, não só "não vazio")*
- **I2 — escapa a URL (O2).** `AvatarOpcao::html('x" onerror=alert(1)', 'Fulano', 'F')` escapa a URL dentro do `src="…"` (`&quot;`/`%22`), sem atributo injetado. *(teste unit)*
- **I3 — fallback sem foto (D1).** URL `null` → saída contém o `<span>` de iniciais com as iniciais dadas e **não** contém `<img`. *(teste unit)*
- **I4 — com foto (D1).** URL não-nula → saída contém `<img src="…"` circular e **não** o `<span>` de iniciais. *(teste unit)*
- **I5 — estilo inline, sem classe do site (O4).** A saída contém `style=` e **não** contém `class=` nem tokens do site (`from-gold`, `size-7`, `bg-gradient-to-br`). *(teste unit — guarda contra regressão a classes utilitárias)*
- **I6 — autores sem N+1 (O1).** Abrir o form dispara **exatamente 1** query na tabela `media` (whereIn eager pelo 3º arg `->with('media')`), não 1 por autor. *(teste de mount contando só queries que tocam `media` — R1; **red-first**: o `getOptionLabelFromRecordUsing` introduz o N+1, o 3º arg o previne — plano Task 2. P5: cair p/ verificação §7 se instável.)*
- **I7 — destinatários buscam server-side por NOME (O3).** `$f->getSearchResults('Ana')` inclui o ativo; `$f->getSearchResults('Ivo')` (inativo) **não** inclui (busca é `where ativo=true`); e um termo que só existe no HTML da opção (`'span'`, `'img'`, `'webp'`) retorna **vazio** (a busca casa a coluna `name`, não o markup). Termos/nomes **ASCII** (R9). *(EVOLUI [MensagemDestinatariosFormTest::test_select_de_destinatarios_nao_oferece_usuario_inativo:89](tests/Feature/Filament/MensagemDestinatariosFormTest.php#L89) — troca `getOptions()` por `getSearchResults()`)*
- **I8 — hidrata selecionados INCLUSIVE inativos (A3, paridade `orWhereIn`).** (a) `$f->getOptionLabels()` de uma direcionada com destinatário que ficou inativo **contém** o id dele (hidratado por `getOptionLabelsUsing`, `whereKey` **sem** filtro `ativo`, **sem** `limit`); (b) **salvar** o título dessa direcionada **não** trava na validação (`Rule::in` recebe o id). *((a) EVOLUI [:108](tests/Feature/Filament/MensagemDestinatariosFormTest.php#L108) — `getOptions()`→`getOptionLabels()`; (b) método novo de save)*
- **I9 — destinatários sem N+1 (O1/A1).** A busca (`getSearchResults`) usa `->with('perfil.media')` e dispara **exatamente 1** query na tabela `media`, não 1 por usuário. *(teste contando só queries que tocam `media` — R1; Step 8 do plano confirma removendo o eager)*
- **I10 — null-safety do perfil (A1).** Usuário **sem** `PerfilMembro` passa pelas closures (`?->foto_thumb_url` → `null` → iniciais) sem `Attempt to read property on null`. *(teste unit com user sem perfil)*
- **I11 — gravação inalterada (F1).** Salvar com a nova apresentação grava o **mesmo conjunto de ids** no pivô. Os testes de **gravação** — `SincronizadorDestinatarios`, save/publicação (`MensagensConta`/`CuradoriaConta`/Pages do `/admin`), `RegraPublicacao` — seguem **verdes sem tocar** (não leem `getOptions()` dos destinatários). ⚠️ **Exceção (O1 do passe):** os 2 testes de OPÇÃO em [MensagemDestinatariosFormTest](tests/Feature/Filament/MensagemDestinatariosFormTest.php) (`:89`, `:108`) leem `getOptions()`, ficam **vazios** com o novo motor e **quebram** — são a forma antiga de I7/I8 e **evoluem** (§8), não "seguem verdes". Os `getOptions()` do campo `nivel` (`CuradoriaContaTest:221`, `MensagemResourceTest:75`, `MensagemAdminAutoriaNivelTest:31`) **não** mudam.

---

## 5. Decisões de desenho

*Esboços para o plano — não é o diff final; o plano/execução refina.*

### 5.1 O helper único `App\Filament\Support\AvatarOpcao`

Um ponto só monta o HTML da opção (evita repetir `e()`, o estilo inline e o fallback nos 5 call sites — a
sugestão do kickoff). Vizinho de [ComponentesImagem](app/Filament/Support/ComponentesImagem.php) no mesmo
namespace. Retorna `string` (o `allowHtml` faz o `innerHTML`).

```php
<?php
// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-23
namespace App\Filament\Support;

/**
 * HTML de uma opção de Select: avatar circular + fallback de iniciais.
 * Idioma visual do single da mensagem (resources/views/mensagens/show.blade.php:61-64).
 * Estilo INLINE de propósito: o dropdown do Filament injeta este HTML por innerHTML,
 * fora do bundle do site — classe utilitária do site pode não existir ali (O4).
 * `e()` no nome E na URL: allowHtml não escapa (O2).
 */
class AvatarOpcao
{
    public static function html(?string $fotoUrl, string $nome, string $iniciais): string
    {
        $circulo = $fotoUrl !== null
            ? '<img src="'.e($fotoUrl).'" alt="" style="width:1.75rem;height:1.75rem;border-radius:9999px;object-fit:cover;flex-shrink:0;">'
            : '<span aria-hidden="true" style="display:inline-grid;place-items:center;width:1.75rem;height:1.75rem;border-radius:9999px;background-image:linear-gradient(to bottom right,#f2a81e,#d98a14);font-size:10px;font-weight:600;color:#3a3266;flex-shrink:0;">'.e($iniciais).'</span>';

        return '<span style="display:inline-flex;align-items:center;gap:0.5rem;">'.$circulo.'<span>'.e($nome).'</span></span>';
    }
}
```

### 5.2 O Select `autores` — consolidado num `selectAutores(): Select`

Colapsa as 3 cópias idênticas (§3.2) num método privado — reduz O1/O2/O4 a **um** ponto (P1). Mantém
`->relationship()` (F2). O param da closure do 3º arg fica **sem type-hint** de propósito (dodge do TypeError
`Relation` × `Builder` visto na F4c-B).

```php
private static function selectAutores(): Select
{
    return Select::make('autores')
        ->label('Autores espirituais')
        ->relationship('autores', 'nome', fn ($query) => $query->with('media')) // 3º arg = eager-load (O1/A2)
        ->multiple()
        ->preload()
        ->searchable()
        ->allowHtml()
        ->getOptionLabelFromRecordUsing(
            fn (AutorEspiritual $record): string => AvatarOpcao::html($record->foto_thumb_url, $record->nome, $record->iniciais)
        );
}
```

Os 3 call sites ([131](app/Filament/Schemas/MensagemForm.php#L131), [270](app/Filament/Schemas/MensagemForm.php#L270), [362](app/Filament/Schemas/MensagemForm.php#L362)) viram `self::selectAutores()`.

### 5.3 O Select `destinatarios` — base num `selectDestinatarios(): Select` + motor server-side (D2)

Colapsa a base comum das 2 ocorrências (P1). Cada call site aplica `->helperText()`/`->required()` por cima
(preservando a diferença atual entre inline e bloco).

```php
private static function selectDestinatarios(): Select
{
    return Select::make('destinatarios')
        ->label('Destinatários')
        ->multiple()
        ->searchable()
        ->minItems(1)
        ->columnSpanFull()
        ->allowHtml()
        // Busca server-side sobre `name` (O3): só ATIVOS, eager perfil.media (O1/A1), teto de 50.
        // LIKE cru — `%`/`_` do termo NÃO escapados; sem generate_search_term_expression (R8, aceito).
        ->getSearchResultsUsing(fn (string $search): array => User::query()
            ->where('ativo', true)
            ->where('name', 'like', "%{$search}%")
            ->with('perfil.media')
            ->orderBy('name')
            ->limit(50)
            ->get()
            ->mapWithKeys(fn (User $u) => [$u->id => AvatarOpcao::html($u->perfil?->foto_thumb_url, $u->name, $u->iniciais)])
            ->all())
        // Hidrata os JÁ SELECIONADOS, INCLUSIVE inativos — papel do antigo orWhereIn (A3/I8). SEM filtro `ativo`.
        ->getOptionLabelsUsing(fn (array $values): array => User::query()
            ->whereKey($values)
            ->with('perfil.media')
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (User $u) => [$u->id => AvatarOpcao::html($u->perfil?->foto_thumb_url, $u->name, $u->iniciais)])
            ->all());
}
```

Call sites:
- **inline `schemaAdmin`** ([153-169](app/Filament/Schemas/MensagemForm.php#L153-L169)): dentro da `Section 'Destinatários'` (visible inalterado) → `self::selectDestinatarios()->helperText('Obrigatório para mensagens de nível "Direcionada".')->required(fn (Get $get): bool => $get('nivel') === VisibilidadeMensagem::Direcionada->value)`.
- **`blocoDestinatarios()`** ([196-215](app/Filament/Schemas/MensagemForm.php#L196-L215)): `self::selectDestinatarios()->required($ehDirecionada)`.

O comentário longo de hoje sobre "por que hidratar inativos" migra para junto do `getOptionLabelsUsing`.

### 5.4 Imports a acrescentar em [MensagemForm.php](app/Filament/Schemas/MensagemForm.php)

`use App\Filament\Support\AvatarOpcao;` e `use App\Models\AutorEspiritual;` (`User`, `Get`, `Select` já
importados). Nenhum outro.

---

## 6. Riscos e armadilhas

- **R1 — `getOptionLabelsUsing` incompleto trava o save (A3).** Se a hidratação filtrar `ativo` ou faltar um id selecionado, `getInValidationRuleValues()` reprova o save de uma direcionada existente (falsa reprovação de validação, **não** vazamento pra gravação). *Mitigação:* `whereKey($values)` sem filtro; coberto por **I8**.
- **R2 — eager-load errado no `User` (A1).** `->with('media')` no `User` estoura `undefined relationship [media]`. *Mitigação:* `->with('perfil.media')`; coberto por **I9/I10**.
- **R3 — N+1 silencioso.** Esquecer o `->with` em qualquer das 3 queries reintroduz 1 query de media por linha (até 148/19). *Mitigação:* **I6/I9** + conferência de queries no dropdown aberto (§7).
- **R4 — XSS por nome do WP (O2).** 145 nomes vêm do WordPress; um nome com `"><script>` ou `<img onerror>` executaria no `innerHTML` do dropdown/badge sem `e()`. *Mitigação:* `e()` no helper; coberto por **I1/I2** (usar de fato um registro de teste com payload).
- **R5 — regressão a classe utilitária do site (O4).** Reintroduzir `class="… from-gold size-7 …"` no HTML da opção some no runtime do Filament (mesma família da dívida do molde recém-fechada). *Mitigação:* **I5** (o teste reprova `class=`/tokens do site).
- **R6 — TypeError na closure do 3º arg.** Type-hint `Builder`/`Relation` errado na closure de `relationship()` estoura em runtime (gotcha da F4c-B). *Mitigação:* param **sem type-hint** (§5.2).
- **R7 — teste de avatar não é testável na suíte de verdade.** O render do dropdown é JS/Livewire; o idioma visual (círculo, gradiente, alinhamento) **não** se prova em PHPUnit. *Mitigação:* invariantes testam o **HTML do helper** (I1–I5) e as **closures** (I7–I10); o **visual** é conferência no browser (§7/§9), atribuída ao dono.
- **R8 — `LIKE` cru na busca de destinatários (registrado, aceito).** `getSearchResultsUsing` faz `->where('name','like',"%{$search}%")` **sem escapar `%`/`_`** (um médium digitando `%` casa todos) e **sem** o `generate_search_term_expression` que o `->relationship` usa nos autores — pequena assimetria de robustez. **Baixa gravidade; decisão de NÃO tratar, aceita** (R2 do passe do dono). Escapar `%`/`_` no termo é trivial e pode entrar depois, se incomodar.
- **R9 — `LIKE` accent-sensitive difere SQLite×MySQL.** Produção (MySQL) é accent-insensitive pela collation; o teste (SQLite) só normaliza ASCII → buscar `'jose'` esperando `'José'` diverge. *Mitigação:* termos/nomes **ASCII** nos testes de I7 (R1 do passe; família da armadilha SQLite×MySQL do projeto). O motor de produção **não** muda.

---

## 7. Cutover (dev; PROD do dono, quando houver)

Sem migration, sem importador, sem asset, sem `npm run build`. O molde já tem a paleta do Filament nas 3
blades Fase E; esta fatia não a toca.

1) `docker compose exec -T app php artisan optimize:clear`
2) `docker compose restart app worker` *(edição de PHP no dev exige restart — OPcache `validate_timestamps=0`)*

**Conferir na tela (localhost) — o que a suíte não prova (R7):**
1. `/admin` → editar/criar Mensagem → abrir o Select **Autores**: opção com foto mostra a miniatura circular; sem foto, iniciais no gradiente gold. Idem o Select **Destinatários** (só com nível "Direcionada").
2. `/minha-conta/mensagens` (médium) e `/minha-conta/curadoria` (diretor DEPAE): repetir nos 2 Selects.
3. **Busca de destinatários**: digitar parte de um nome filtra por nome; `web`/`img` **não** traz todos.
4. **Já-selecionado inativo**: numa direcionada existente com destinatário inativo, o badge aparece e é removível; Salvar título não trava.
5. **N+1**: com o Debugbar, abrir cada dropdown — nº de queries **constante**, não 1 por linha.
6. **XSS**: um autor/usuário de teste com nome `<img src=x onerror=…>"` não executa nem quebra o markup.

⚠️ Sem `cema:importar-*`, sem migration, sem tocar o banco.

---

## 8. Testes — lista nominal

**Novo arquivo:**

| Arquivo | O que prova |
|---|---|
| `tests/Unit/Filament/AvatarOpcaoTest.php` (novo) | I1–I5 (escape nome/URL, fallback sem foto, com foto, sem classe do site) |

**Arquivo EVOLUÍDO — [MensagemDestinatariosFormTest.php](tests/Feature/Filament/MensagemDestinatariosFormTest.php)** — um só lar dos invariantes de destinatários (**não** criar arquivo paralelo, **não** deletar):

| Método | Mudança |
|---|---|
| `…nao_oferece_usuario_inativo` (:89) | **reescrito** → I7: `getOptions()` vira `getSearchResults('Ana')`/`getSearchResults('Ivo')` + termo-HTML (`'span'`/`'img'`/`'webp'`) vazio; ASCII (R9) |
| `…mantem_o_destinatario_ja_selecionado_que_ficou_inativo` (:108) | **reescrito** → I8(a): `getOptions()` vira `getOptionLabels()` |
| *(novo)* 1 query de mídia na busca | I9: `getSearchResults` dispara **exatamente 1** query na tabela `media` (eager `perfil.media`), não 1 por usuário — conta só o que toca `media` (R1) |
| *(novo)* user-sem-perfil | I10: user sem `PerfilMembro` passa pelas closures (`?->`) sem erro |

**I6 (autores, N+1):** `tests/Feature/Filament/MensagemFormAutoresSelectTest.php` (novo) — query-count no mount com N autores; **ou** verificação §7 se o mount ficar frágil (P5).

**I8(b) (save não trava) já é coberto** — descoberta do plano — por [MensagemDestinatariosPersistenciaTest::test_nao_grava_destinatario_inativo_no_pivo:154](tests/Feature/Filament/MensagemDestinatariosPersistenciaTest.php#L154) (ativo+inativo → sem erro + inativo filtrado do pivô por `efetivos()`); ele **passa a depender** do novo `getOptionLabelsUsing`, virando o guarda de regressão do A3 (com `:176`, `whereKey` exclui id forjado). **Não duplicar** — I8(b) não vira método novo.

**Seguem verdes sem tocar** (I11): esses 2 guardas do A3 em `MensagemDestinatariosPersistenciaTest` (`:154`, `:176`), mais `SincronizadorDestinatarios`, save/publicação (`MensagensConta`/`CuradoriaConta`/Pages do `/admin`), `RegraPublicacao`, e os `getOptions()` do campo `nivel` (`MensagemAdminAutoriaNivelTest:31`, `CuradoriaContaTest:221`, `MensagemResourceTest:75`). **Delta real da suíte** (a apurar no plano): `AvatarOpcaoTest` (~5) + `MensagemDestinatariosFormTest` (**+2** métodos, 2 reescritos) + I6 (0 ou 1) — **não** "3 arquivos novos" (R3 do passe).

**Armadilha de falso-verde a evitar:** asserção "não vazio" no helper é vacuosa; **I1/I2 devem asserir a
sequência escapada exata** (`&lt;img`, `&quot;`), e **I7 deve asserir a AUSÊNCIA** dos ids errados (o inativo e o
termo-HTML), não só a presença do certo.

---

## 9. O que prova que está pronto

- Suíte **verde** (`docker compose exec -T app php artisan test`); baseline atual + os novos de §8, 0 regressões.
- `docker compose exec -T app ./vendor/bin/pint --test` **limpo** (roda antes dos testes no CI).
- Grep de allowlist: `allowHtml`, `getSearchResultsUsing`, `getOptionLabelsUsing`, `AvatarOpcao::html` aparecem só nos pontos previstos; **nenhum** `->options(` remanescente nos destinatários; **nenhum** `class=` no HTML da opção.
- **CI verde no último commit** do PR + **"go" do dono**. Não pré-configurar merge automático.
- Conferência visual da §7 nas **3 telas** — atribuída ao dono (R7).

---

## 10. Fora de escopo

- Gravação/pivô/validação de nível (F1) — intactos.
- Trocar autores por `->options()` (F2); mexer no `autor-fallback.svg` (F3).
- Avatares em **colunas de tabela** do `/admin` (já existe o [avatar-usuario.blade.php](resources/views/filament/tables/columns/avatar-usuario.blade.php) para isso) — esta fatia é só as **opções** dos Selects.
- Avatar no Select `relacionadas` (mensagens, não pessoas) e em qualquer outro form.
- Qualquer mudança de UX além do avatar/busca — layout, ordenação por relevância, agrupamento.

---

## 11. Pendências de ratificação (o passe adversarial do dono decide)

| # | Pendência | Recomendação |
|---|---|---|
| **P1** | Consolidar as 3 cópias do Select `autores` em `selectAutores()` e a base dos `destinatarios` em `selectDestinatarios()` (§5.2/5.3), colapsando O1/O2/O3/O4 a um ponto cada — vs. repetir a config nos 5 call sites. | **SIM** (menos superfície de erro; segue o padrão do `blocoDestinatarios()` já extraído). |
| **P2** | Consequência do **D2**: sem `->options()`/`->preload()`, o dropdown de destinatários passa a **pedir "digite para buscar"** ao abrir, em vez de listar os 148 ativos. | **Aceitar** (148 pessoas — teclar é melhor que despejar a lista; e é o preço do server-side que evita o O3). |
| **P3** | Teto de resultados da busca de destinatários = **50** (default `optionsLimit` do Filament). | Manter 50. |
| **P4** | `font-family` display do círculo de iniciais **omitida** no estilo inline (herda a fonte do dropdown do Filament) — mesma dívida do O4; a 10px é visualmente irrelevante. | **Aceitar** (não reabrir a paleta/fonte do molde por isto). |
| **P5** | I6 (N+1 dos autores) como **teste de mount Livewire** vs. **verificação no browser** (§7), se o mount ficar frágil. | Tentar teste; cair para verificação se instável. |

**Passe do dono (2026-07-23): P1–P5 RATIFICADAS** conforme as recomendações. P2 vale também para o **inline do
`schemaAdmin`** — as 3 telas perdem a lista-ao-abrir (é o preço do server-side que mata o O3).

---

## 12. Correções aplicadas no passe do dono (2026-07-23)

| # | Achado | Correção |
|---|---|---|
| **O1** *(obrigatório)* | I11 dizia "testes existentes seguem verdes sem tocar", mas remover `->options()`/`->preload()` esvazia `getOptions()` → [MensagemDestinatariosFormTest:89](tests/Feature/Filament/MensagemDestinatariosFormTest.php#L89) e [:108](tests/Feature/Filament/MensagemDestinatariosFormTest.php#L108) **quebram** (são I7/I8 na API antiga). | **§4 e §8 corrigidos:** os 2 métodos **evoluem** no MESMO arquivo (`getOptions()`→`getSearchResults()`/`getOptionLabels()`) + 3 novos; **I11 reservado à gravação**. |
| **R1** *(refinamento)* | `LIKE` accent-sensitive difere SQLite×MySQL. | Termos/nomes **ASCII** nos testes de I7 → **R9** (§6). |
| **R2** *(refinamento)* | `LIKE` cru não escapa `%`/`_` e não usa `generate_search_term_expression`. | Decisão **aceita** (baixa gravidade), registrada como **R8** (§6) + comentário no §5.3. |
| **R3** *(refinamento)* | §8 dizia "3 arquivos novos". | §8 recontado: 1 novo (`AvatarOpcaoTest`) + 1 **evoluído** + I6 (0/1); delta real da suíte a apurar no plano. |

**Confirmado pelo dono, mantido como está:** A2 (`->modifyOptionsQueryUsing` inexistente), A3/I8 (essencial — `count(state) > count(labels)` ⇒ `Rule::in([])` trava), O1 autores (preload pula o cache e aplica `->with('media')`, [Select.php:1066,1083-1088,1104-1108](vendor/filament/forms/src/Components/Select.php#L1083-L1088)), fronteira apresentação-only, O2. **P1–P5 ratificadas (§11).**

---

## 13. Passe do plano (2026-07-24) — 1 obrigatório + 2 refinamentos

| # | Achado | Correção no plano |
|---|---|---|
| **O1** *(obrigatório)* | O I6 não era red-first: hoje (sem `getOptionLabelFromRecordUsing`) o preload dos autores faz **pluck** do título ([Select.php:1046-1048](vendor/filament/forms/src/Components/Select.php#L1046-L1048)) — não instancia record, 0 query de mídia → I6 passaria vacuoso. O N+1 é **introduzido** pelo callback e **prevenido** pelo 3º arg. | Task 2 reestruturada: `selectAutores()` **sem** `->with('media')` → I6 **vermelho** (N+1) → adicionar o 3º arg → verde (espelha o Step 8 da Task 3). |
| **R1** *(refinamento)* | Comparar o total de queries do mount é frágil (P5). | I6/I9 contam **só** as queries que tocam a tabela `media` e asseguram **exatamente 1** (determinístico). |
| **R2** *(refinamento)* | Narrativa: no arranque da Task 3, I9 e I10 também são vermelhos-antes. | Step 3 da Task 3 corrigido. |

**Confirmado no passe do plano:** a descoberta do I8b (guardas existentes `Persistencia:154`/`:176`), o I7 como driver vermelho, a consolidação P1, imports, fronteira, greps, cutover PHP-only. **Aprovado para execução por subagentes.**

---

*Esteira: SPEC ✅ → passe do dono ✅ → PLANO em tasks TDD ✅ → passe do plano ✅ → execução SDD → PR → passe
final. Merge = CI verde no último commit + "go" do dono.*
