# Camada 4 · Fatia F4c-D — Plano de implementação

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fundir a coluna `contexto` de `mensagens` em `resumo` (um só campo editorial, um só lugar de render) e criar os arquivos de idioma pt-BR que faltam, matando a causa raiz das mensagens de validação em inglês.

**Architecture:** O código para de usar a coluna **antes** de a coluna sumir (Tasks 1–3), e só então as migrations rodam (Task 4) — a mesma ordem do cutover, pelo mesmo motivo: código novo com banco velho é benigno, o inverso é erro 500. O bloco 2 (Tasks 5–7) é independente do bloco 1 e pode ser revisado à parte.

**Tech Stack:** PHP 8.3 · Laravel 13.17 · Filament 5 · Livewire 3 · MySQL 8 (dev) / SQLite `:memory:` (suíte) · PHPUnit · Pint.

**SPEC:** [2026-07-23-camada-4-fatia-f4c-d-fusao-contexto-resumo.md](../specs/2026-07-23-camada-4-fatia-f4c-d-fusao-contexto-resumo.md) — ratificada em 2026-07-23.
**Branch:** `camada-4-fatia-f4c-d-fusao-resumo`, a partir de `4e466c9`. **Baseline: 1278 passed.**

## Global Constraints

- **Tudo em português brasileiro** — comentários, mensagens de interface, commits. Sintaxe e APIs de terceiros no original.
- 🚫 **PROIBIDO** `migrate:fresh`, `migrate:refresh`, `migrate:reset`, `db:wipe` e seed/factory destrutivo. **Só `php artisan migrate` incremental.** Eles apagam os dados importados do legado.
- **Comandos rodam no container:** `docker compose exec -T app php artisan …` e `docker compose exec -T app ./vendor/bin/pint`. **Nunca** `sail`. (npm/Vite, se preciso, rodam no **host** — mas esta fatia **não** precisa de `npm run build`.)
- **Pint antes de qualquer push** — o CI roda `pint --test` **antes** dos testes e aborta o job.
- **Os três sentidos de "contexto"** (SPEC §3.2): o **campo** morre; o **método** `AuditoriaAutorizacao::contexto()` **não se toca** ([Mensagem.php:289](app/Models/Mensagem.php#L289), [User.php:140](app/Models/User.php#L140), [AgendaDia.php:177](app/Models/AgendaDia.php#L177), `AuditoriaHelperTest:48`, `HistoricoMensagemTest:132`); a **prosa** de UI é decisão editorial (Task 7).
- **Não há strict mode no Eloquent** ⇒ chave não-`fillable` é descartada **em silêncio** e atributo inexistente devolve `null`. Vários testes ficam **verdes por vacuidade** se não forem tocados à mão. O framework não vai avisar.
- **Cabeçalho de autoria** em arquivo novo relevante: `// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-23`.
- **Commits atômicos**, um por task, mensagem em pt-BR.

---

## Estrutura de arquivos

**Modificados (bloco 1):**

| Arquivo | Responsabilidade nesta fatia |
|---|---|
| [app/Models/Mensagem.php](app/Models/Mensagem.php) | tirar `contexto` de `$fillable` (:50), `logOnly` (:267) e do laço de redação (:299) |
| [app/Support/Mensagens/GlossarioCamposMensagem.php](app/Support/Mensagens/GlossarioCamposMensagem.php) | tirar o rótulo (:21) e corrigir o docblock (12 → 11) |
| [database/factories/MensagemFactory.php](database/factories/MensagemFactory.php) | tirar `'contexto' => null` (:22) |
| [app/Importacao/ImportadorMensagens.php](app/Importacao/ImportadorMensagens.php) | comentário-contrato (:51) |
| [app/Filament/Schemas/MensagemForm.php](app/Filament/Schemas/MensagemForm.php) | 3 `Textarea` de `contexto` saem (:67, :239, :315); `resumo` entra no médium; rótulo "Resumo" nos três |
| [resources/views/mensagens/show.blade.php](resources/views/mensagens/show.blade.php) | a faixa sai (:85-93); a meta description perde o elo do meio (:7) |

**Criados (bloco 1):** duas migrations em `database/migrations/` + `tests/Feature/Mensagens/FusaoContextoResumoTest.php`.

**Criados (bloco 2):** `lang/pt_BR/validation.php`, `lang/pt_BR/auth.php`, `lang/pt_BR/passwords.php` + `tests/Feature/Idioma/ValidationPtBrTest.php`.

---

## Task 1: O model para de usar a coluna

A coluna **continua no banco**; só o código deixa de tocá-la. Depois desta task, `contexto` está fora do `$fillable`, da trilha de auditoria e do glossário — e os testes que dependiam dele foram convertidos para `resumo`, não apagados.

**Files:**
- Modify: `app/Models/Mensagem.php:50,267,299`
- Modify: `app/Support/Mensagens/GlossarioCamposMensagem.php:11,21`
- Modify: `database/factories/MensagemFactory.php:22`
- Modify: `app/Importacao/ImportadorMensagens.php:51`
- Test: `tests/Feature/Models/MensagemTest.php:40,69` · `tests/Feature/Autorizacao/AuditoriaMensagemTest.php:57,82,97` · `tests/Feature/Importacao/ImportadorMensagensTest.php:94,228,233,241` · `tests/Feature/Conta/HistoricoMensagemTest.php:23,54`

**Interfaces:**
- Consumes: nada (primeira task).
- Produces: `Mensagem::$fillable` com **12** chaves (`titulo, slug, corpo, resumo, formato, data_recebimento, casa, link_arquivo, liberar_download, nivel, status, wp_id`); `logOnly` e `GlossarioCamposMensagem::CAMPOS_ROTULOS` com **11** cada.

- [ ] **Step 1: Escrever os testes que provam a nova regra**

Em `tests/Feature/Models/MensagemTest.php`, substituir o corpo de `test_fillable_exato` (linha ~40) e **reescrever** `test_contexto_e_texto_puro_persistido` (linha ~69) — o round-trip muda de campo, não desaparece (é a única prova desse tipo fora do `corpo`):

```php
    public function test_fillable_exato(): void
    {
        $this->assertSame(
            ['titulo', 'slug', 'corpo', 'resumo', 'formato', 'data_recebimento', 'casa', 'link_arquivo', 'liberar_download', 'nivel', 'status', 'wp_id'],
            (new Mensagem)->getFillable(),
        );
    }
```

```php
    public function test_resumo_e_texto_puro_persistido(): void
    {
        $m = Mensagem::factory()->create(['resumo' => 'Recebida na reunião pública de quarta.']);
        $this->assertSame('Recebida na reunião pública de quarta.', $m->fresh()->resumo);
    }
```

Em `tests/Feature/Autorizacao/AuditoriaMensagemTest.php`: **apagar** `test_editar_contexto_registra_a_chave_mas_nunca_o_texto` (linhas 57-72 — o par do `resumo` já existe em `MensagemTest:187`) e **reescrever** o teste do valor nulo (linhas 74-95), que é o **único** da suíte a exercitar valor novo `null` no laço de redação:

```php
    /**
     * Achado Important da revisão da Task 1 da F4b: nenhum teste da suíte exercitava um valor NULL
     * em corpo/resumo — trocar array_key_exists por isset no laço de redação não reprovaria nenhum
     * teste, porque isset(string não vazia) é sempre true. Aqui o valor NOVO é null: isset(null) é
     * false e pularia a redação (o campo ficaria null, em vez de virar '[texto não registrado]'),
     * enquanto array_key_exists redige do mesmo jeito, porque é a CHAVE — não a verdade do valor —
     * que decide.
     */
    public function test_editar_resumo_para_null_redige_o_campo_mesmo_com_o_valor_novo_nulo(): void
    {
        $m = Mensagem::factory()->create(['resumo' => 'SENTINELA-ANTES-DE-NULL']);
        Activity::query()->delete();

        $m->update(['resumo' => null]);

        $props = Activity::where('log_name', 'mensagem')->latest('id')->first()->properties;
        $this->assertArrayHasKey('resumo', $props['attributes']); // a CHAVE sobrevive mesmo com valor novo null
        $this->assertSame('[texto não registrado]', $props['attributes']['resumo']); // isset(null) pularia a redação

        $json = Activity::where('log_name', 'mensagem')->get()->toJson();
        $this->assertStringNotContainsString('SENTINELA-ANTES-DE-NULL', $json);
    }
```

Na mesma classe, o comentário da linha 97 vira: `/** A redação é cirúrgica: só corpo/resumo são trocados — titulo continua com o valor real. */`

Em `tests/Feature/Importacao/ImportadorMensagensTest.php` — **os dois pontos são falsos-verdes** (SPEC R2), não falham sozinhos. Linha 94:

```php
        $this->assertNull($m->resumo);              // texto editorial da curadoria — o import de mensagens não escreve
```

E em `test_reimport_preserva_curadoria_do_admin` (linhas 226-243), trocar as duas ocorrências e o comentário:

```php
        // Curadoria = slug/status/nivel (create-only) + resumo (nunca) + relacionadas (nunca).
        $this->importar([$this->mensagemLegado(['nivel' => null])]);
        $m = Mensagem::firstWhere('wp_id', 21694);
        $outra = Mensagem::factory()->create();

        $m->update(['nivel' => 'publico', 'status' => 'despublicada', 'resumo' => 'nota do admin']);
        $m->sincronizarRelacionadas([$outra->id]);

        $this->importar([$this->mensagemLegado(['nivel' => null])]);   // re-import (legado sem termo)

        $m->refresh();
        $this->assertSame('publico', $m->nivel, 'nível classificado pelo admin foi zerado');
        $this->assertSame('despublicada', $m->status, 'status do admin foi sobrescrito');
        $this->assertSame('nota do admin', $m->resumo, 'resumo foi tocado pelo import');
        $this->assertTrue($m->relacionadas->contains('id', $outra->id), 'relacionadas foi tocada pelo import');
```

- [ ] **Step 2: Rodar e verificar que falha**

```
docker compose exec -T app php artisan test --filter="MensagemTest|AuditoriaMensagemTest"
```

Esperado: **FAIL**. `test_fillable_exato` reprova por diferença de array (o `$fillable` ainda tem `contexto`); `test_resumo_e_texto_puro_persistido` **passa** desde já (o campo já existe — é regressão, não novidade); `test_editar_resumo_para_null…` passa (o `resumo` já está no `logOnly`). Se `test_fillable_exato` **não** reprovar, a edição não foi salva.

- [ ] **Step 3: Tirar o campo do model, do glossário e da factory**

`app/Models/Mensagem.php` — **três** pontos. Apagar a linha 50 (`'contexto', // texto puro …`) do `$fillable`; no `logOnly` (:267):

```php
            ->logOnly(['titulo', 'slug', 'corpo', 'resumo', 'formato', 'data_recebimento',
                'casa', 'link_arquivo', 'liberar_download', 'nivel', 'status'])
```

e no laço de redação (:299):

```php
            foreach (['corpo', 'resumo'] as $campo) {
```

⚠️ **A linha 289 (`AuditoriaAutorizacao::contexto()`) NÃO se toca** — é o método, não o campo.

`app/Support/Mensagens/GlossarioCamposMensagem.php` — apagar a linha 21 (`'contexto' => 'Contexto',`) e corrigir o docblock da linha 11: *"Mesmos **11** campos de …"*.

`database/factories/MensagemFactory.php` — apagar a linha 22 (`'contexto' => null,`).

`app/Importacao/ImportadorMensagens.php:51` — o comentário-contrato passa a:

```php
                // casa (default 'CEMA') NUNCA é setada pelo import; o resumo vem do cema:importar-resumos.
```

- [ ] **Step 4: Rodar e verificar que passa**

```
docker compose exec -T app php artisan test --filter="MensagemTest|AuditoriaMensagemTest|ImportadorMensagensTest|GlossarioCamposParidadeTest|HistoricoMensagemTest"
```

Esperado: **PASS**, tudo verde. O `GlossarioCamposParidadeTest` é o gate: ele reprova se `logOnly` e glossário saírem em passos diferentes.

- [ ] **Step 5: Ajustar os comentários que mencionam o campo**

`tests/Feature/Conta/HistoricoMensagemTest.php` — linhas 23 e 54: *"corpo/contexto"* → *"corpo/resumo"*. São comentários; nenhuma asserção muda. **Não tocar a linha 132** (`AuditoriaAutorizacao::contexto()`) nem o nome do teste da linha 48 (*"contexto técnico"* = `user_agent`/`attributes`).

- [ ] **Step 6: Pint e commit**

```bash
docker compose exec -T app ./vendor/bin/pint
git add app/Models/Mensagem.php app/Support/Mensagens/GlossarioCamposMensagem.php database/factories/MensagemFactory.php app/Importacao/ImportadorMensagens.php tests/
git commit -m "refactor(f4c-d): tira o campo contexto do model, da trilha e do glossario

O round-trip de texto puro e a prova de redacao com valor nulo migram para
resumo em vez de sumir: eram as unicas do tipo na suite.

Os dois pontos do ImportadorMensagensTest eram falso-verde (sem strict mode,
atributo inexistente devolve null) — trocados a mao."
```

---

## Task 2: O formulário — sai `contexto`, entra `resumo` no médium

**Files:**
- Modify: `app/Filament/Schemas/MensagemForm.php:67-71,73-78,239-243,315-319,321-326`
- Test: `tests/Feature/Filament/MensagemResourceTest.php:54-58,116-121` · `tests/Feature/Conta/MensagensContaCriarTest.php:246-266` · `tests/Feature/Conta/CuradoriaContaTest.php:226-234`

**Interfaces:**
- Consumes: `Mensagem::$fillable` com `resumo` (Task 1) — é o que faz o campo do médium gravar sem tocar `MensagensConta`.
- Produces: os três schemas com o campo `resumo` (`Textarea`, `rows(4)`, `maxLength(1500)`, rótulo **"Resumo"**) e **nenhum** campo `contexto`.

- [ ] **Step 1: Escrever as guardas negativas e o round-trip**

⚠️ **Armadilha nº 1 (SPEC R3):** em componente Livewire+Filament o schema só existe **depois de montado**. `assertFormFieldDoesNotExist` sem o `->call('novo')` / `->call('editar', $id)` antes passa por **vacuidade**.

`tests/Feature/Filament/MensagemResourceTest.php` — **apagar** `test_form_tem_textarea_contexto` (linhas 54-58) e acrescentar a negativa ao teste que já existe (linha 116):

```php
    public function test_form_nao_tem_campos_podados(): void
    {
        Livewire::test(CreateMensagem::class)
            ->assertFormFieldDoesNotExist('origem_da_mensagem')
            ->assertFormFieldDoesNotExist('grupo_mediunico')
            ->assertFormFieldDoesNotExist('casa_espirita')
            ->assertFormFieldDoesNotExist('contexto');   // F4c-D: fundido em `resumo`
    }
```

`tests/Feature/Conta/MensagensContaCriarTest.php` — **inverter** as duas linhas de `test_i22_campos_do_medium` (256 e 260):

```php
            ->assertFormFieldDoesNotExist('contexto')   // F4c-D: fundido em `resumo`
            ->assertFormFieldExists('titulo')
            ->assertFormFieldExists('formato')
            ->assertFormFieldExists('data_recebimento')
            ->assertFormFieldExists('resumo')           // F4c-D (D2): revoga o I11 da F4c-AC
            ->assertFormFieldExists('corpo')
```

Na mesma classe, o teste novo de **persistência** (hoje não existe nenhum — SPEC §3.6):

```php
    /** I6 (F4c-D): o resumo digitado pelo médium chega ao banco — o schemaMedium não tem allowlist. */
    public function test_i6_resumo_do_medium_persiste(): void
    {
        $medium = $this->medium();

        Livewire::actingAs($medium)->test(MensagensConta::class)
            ->call('novo')
            ->fillForm([
                'titulo' => 'Mensagem com resumo',
                'formato' => 'psicografia',
                'data_recebimento' => '2027-02-10',
                'corpo' => '<p>Corpo da mensagem.</p>',
                'resumo' => 'Abertura editorial escrita pelo médium.',
            ])
            ->call('salvar')
            ->assertHasNoFormErrors();

        $this->assertSame(
            'Abertura editorial escrita pelo médium.',
            Mensagem::where('titulo', 'Mensagem com resumo')->firstOrFail()->resumo,
        );
    }
```

`tests/Feature/Conta/CuradoriaContaTest.php` — o docblock da linha 226 fica **falso** com o D2; trocar por uma guarda negativa que **ninguém tinha** (o `schemaCuradoria` é o terceiro schema):

```php
    /** I11 (F4c-D): a curadoria edita o `resumo`; o `contexto` foi fundido nele e não voltou. */
    public function test_i11_form_da_curadoria_tem_resumo_e_nao_tem_contexto(): void
    {
        $pendente = Mensagem::factory()->pendente()->create();

        Livewire::actingAs($this->diretorDepae())->test(CuradoriaConta::class)
            ->call('editar', $pendente->id)
            ->assertFormFieldExists('resumo')
            ->assertFormFieldDoesNotExist('contexto');
    }
```

- [ ] **Step 2: Rodar e verificar que falha**

```
docker compose exec -T app php artisan test --filter="MensagemResourceTest|MensagensContaCriarTest|CuradoriaContaTest"
```

Esperado: **FAIL** em `test_form_nao_tem_campos_podados` (o Textarea `contexto` ainda existe no `schemaAdmin`), em `test_i22_campos_do_medium` (o `resumo` ainda não existe no `schemaMedium`), em `test_i6_resumo_do_medium_persiste` (campo inexistente no `fillForm`) e em `test_i11_…` (o `contexto` ainda está no `schemaCuradoria`).

- [ ] **Step 3: Editar os três schemas**

Em `app/Filament/Schemas/MensagemForm.php`:

**(a) `schemaAdmin`** — apagar o bloco das linhas 67-71 (`Textarea::make('contexto')…`) e limpar o rótulo do `resumo` (:73-78):

```php
                    Textarea::make('resumo')
                        ->label('Resumo')
                        ->helperText('Aparece no card, na busca do Google e como abertura da página. Importado do site antigo quando havia. Opcional.')
                        ->rows(4)
                        ->maxLength(1500)
                        ->columnSpan(2),
```

**(b) `schemaMedium`** — **substituir** o bloco das linhas 239-243 pelo campo novo (mesmo contrato dos outros dois; o `contexto` do médium não tinha `maxLength` nenhum):

```php
                    Textarea::make('resumo')
                        ->label('Resumo')
                        ->helperText('Texto curto que abre a página da mensagem e aparece no card. Opcional.')
                        ->rows(4)
                        ->maxLength(1500)
                        ->columnSpan(2),
```

**(c) `schemaCuradoria`** — apagar o bloco das linhas 315-319 e limpar o rótulo do `resumo` (:321-326), idêntico ao (a).

Atualizar o docblock do `schemaCuradoria` (:294-299), que descreve o schema como *"o schemaAdmin SEM …"* — a lista não muda, mas a frase sobre o `contexto` some se houver.

- [ ] **Step 4: Rodar e verificar que passa**

```
docker compose exec -T app php artisan test --filter="MensagemResourceTest|MensagensContaCriarTest|MensagensContaEditarTest|CuradoriaContaTest|CuradoriaPublicarTest"
```

Esperado: **PASS**. Nenhum outro teste de save quebra: o `resumo` entra **opcional** (sem `required`) e ninguém preenchia `contexto`.

- [ ] **Step 5: Pint e commit**

```bash
docker compose exec -T app ./vendor/bin/pint
git add app/Filament/Schemas/MensagemForm.php tests/
git commit -m "feat(f4c-d): o medium ganha o campo Resumo; o contexto sai dos 3 schemas

Revoga o I11 da F4c-AC: o argumento (o medium ja tem contexto) caiu junto
com o contexto. Rotulo 'Resumo' limpo nos tres, helper por publico.

Guarda negativa nos TRES schemas — a da curadoria nao existia — sempre com
o form montado, senao a assercao passa por vacuidade."
```

---

## Task 3: A página da mensagem — um só lugar de render

**Files:**
- Modify: `resources/views/mensagens/show.blade.php:7,85-93`
- Test: `tests/Feature/Front/MensagemShowTest.php:37-44,216-247`

**Interfaces:**
- Consumes: nada das tasks anteriores.
- Produces: meta description e `og:description` com a cadeia `resumo ?: corpo`.

- [ ] **Step 1: Escrever/ajustar os testes**

Em `tests/Feature/Front/MensagemShowTest.php`:

**(a) apagar** `test_contexto_e_escapado` (linhas 37-44) — testa a faixa. O escape do texto que sobra (o lead) já é coberto por `test_resumo_do_lead_e_escapado` (:263), que é **mais forte**, porque o lead usa `{!! nl2br(e()) !!}` e a faixa usava `{{ }}`.

**(b) reescrever** os dois da meta description (216-247). ⚠️ O primeiro é **falso-verde** (SPEC R2): sem a chave `contexto` na fixture ele continuaria passando e viraria asserção vazia.

```php
    /**
     * I8: a asserção é sobre a TAG. O resumo também vira lead no corpo da página
     * (show.blade.php:139-147) — `assertSee`/`assertDontSee` soltos não distinguiriam
     * a meta description de nada.
     */
    public function test_meta_description_usa_o_resumo_quando_existe(): void
    {
        Mensagem::factory()->publica()->create([
            'slug' => 'com-resumo',
            'resumo' => 'Radian convida os trabalhadores a refletirem sobre a palavra.',
            'corpo' => '<p>Corpo que nao deve aparecer.</p>',
        ]);

        $this->get(route('mensagens.show', 'com-resumo'))
            ->assertOk()
            ->assertSee('name="description" content="Radian convida os trabalhadores a refletirem sobre a palavra."', false)
            ->assertDontSee('name="description" content="Corpo que nao deve aparecer."', false);
    }

    /** GUARDA: com a fusão, o corpo é o único fallback — a cadeia não pode ficar sem rede. */
    public function test_meta_description_cai_no_corpo_sem_resumo(): void
    {
        Mensagem::factory()->publica()->create([
            'slug' => 'sem-resumo', 'resumo' => null, 'corpo' => '<p>Corpo.</p>',
        ]);

        $this->get(route('mensagens.show', 'sem-resumo'))
            ->assertOk()
            ->assertSee('name="description" content="Corpo."', false);
    }
```

**(c) acrescentar** a guarda de que a faixa sumiu (I7):

```php
    /** I7 (F4c-D): a faixa "Contexto —" foi removida; o lead do resumo é o único texto editorial. */
    public function test_faixa_de_contexto_nao_existe_mais(): void
    {
        Mensagem::factory()->publica()->create([
            'slug' => 'sem-faixa', 'resumo' => 'Abertura editorial.',
        ]);

        $res = $this->get(route('mensagens.show', 'sem-faixa'));
        $res->assertOk()
            ->assertSee('cema-msg-resumo', false)          // o lead continua
            ->assertDontSee('>Contexto</strong>', false);  // a faixa não
    }
```

- [ ] **Step 2: Provar o vermelho — e desfazer a prova**

⚠️ **Este teste passa por vacuidade se rodado como está.** A faixa só renderiza quando `contexto` tem valor, e desde a Task 1 o campo está **fora do `$fillable`** — `factory()->create(['contexto' => …])` descarta a chave em silêncio. Sem a prova do vermelho, não se sabe se o `assertDontSee` procura a string certa.

Acrescentar **temporariamente** a gravação por fora do model, logo depois do `create` (a coluna ainda existe no banco — o drop é a Task 4):

```php
        // TEMPORÁRIO — só para provar o vermelho. Remover no Step 3.
        \Illuminate\Support\Facades\DB::table('mensagens')
            ->where('slug', 'sem-faixa')
            ->update(['contexto' => 'FAIXA AINDA VISIVEL']);
```

```
docker compose exec -T app php artisan test --filter=MensagemShowTest
```

Esperado: **FAIL** em `test_faixa_de_contexto_nao_existe_mais`, com o `assertDontSee` encontrando `>Contexto</strong>`. Se ele **passar** mesmo com a linha temporária, a string do `assertDontSee` está errada — corrija antes de seguir.

⚠️ **Apagar as 4 linhas temporárias no Step 3.** Elas não podem sobreviver à Task 4: depois do `dropColumn`, esse `update` estoura com *column not found*. Os dois testes da meta description passam desde já (são regressão).

- [ ] **Step 3: Editar a view — e apagar a prova temporária**

Primeiro, **remover as 4 linhas temporárias** do Step 2 (o `DB::table(...)->update([...])` e o comentário). O teste volta a ser o da versão final.

`resources/views/mensagens/show.blade.php` — **apagar as linhas 85-93 inteiras** (o comentário `{{-- Faixa de contexto … --}}` e a `<section>` que ele abre).

⚠️ **Apagar só até a linha 93.** O bloco "Direcionada a você" começa na **95** e é outra coisa.

E na linha 7, a cadeia perde o elo do meio:

```blade
              :description="\Illuminate\Support\Str::limit(strip_tags($mensagem->resumo ?: $mensagem->corpo), 155)">
```

Lembrar que esse valor alimenta **duas** tags: `description` e `og:description` ([layout/app.blade.php:13,15](resources/views/components/layout/app.blade.php#L13)).

- [ ] **Step 4: Rodar e verificar que passa**

```
docker compose exec -T app php artisan test --filter="MensagemShowTest|MensagemSeoTest|MensagemBarreiraTest"
```

Esperado: **PASS**. A barreira continua interceptando antes do render (regressão do I10 da F4c-AC).

- [ ] **Step 5: Commit**

```bash
git add resources/views/mensagens/show.blade.php tests/
git commit -m "feat(f4c-d): remove a faixa de contexto; o lead do resumo fica sozinho

A meta description (e o og:description, que sai da mesma variavel) passa de
resumo ?: contexto ?: corpo para resumo ?: corpo.

O teste do escape da faixa sai sem deixar buraco: o do lead ja cobre o mesmo,
e com mais rigor (a faixa usava {{ }}, o lead usa nl2br(e()))."
```

---

## Task 4: As migrations — funde e dropa

Agora, e só agora, o banco muda. **Esta é a ordem do cutover** (SPEC §7): o código já não usa a coluna.

**Files:**
- Create: `database/migrations/2026_07_23_000001_funde_contexto_em_resumo_nas_mensagens.php`
- Create: `database/migrations/2026_07_23_000002_drop_contexto_from_mensagens_table.php`
- Create: `tests/Feature/Mensagens/FusaoContextoResumoTest.php`
- Modify: `tests/Feature/Models/MensagemTest.php:30-38`

**Interfaces:**
- Consumes: `Mensagem::$fillable` sem `contexto` (Task 1) — é o que permite recriar a coluna no teste sem que o model a enxergue.
- Produces: a tabela `mensagens` sem a coluna `contexto`.

- [ ] **Step 1: Escrever o teste da fusão**

O teste **recria a coluna** e roda o `up()` da migration sobre dado fabricado — é o que prova a **semântica** (o CI migra banco vazio, então `migrate` com exit 0 não prova cópia nenhuma).

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-23

namespace Tests\Feature\Mensagens;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Prova a SEMÂNTICA da migration de fusão (F4c-D, I1/I2). O CI roda `migrate` contra um MySQL
 * VAZIO: exit 0 não prova cópia alguma — um WHERE errado copia 0 linhas em silêncio. Aqui a
 * coluna `contexto` (já dropada pela migration seguinte) é recriada, o dado é inserido por
 * DB::table (o model não a enxerga mais) e o up() roda de verdade.
 */
class FusaoContextoResumoTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): object
    {
        return require database_path('migrations/2026_07_23_000001_funde_contexto_em_resumo_nas_mensagens.php');
    }

    /** Recria a coluna dropada e devolve os ids das 4 linhas de fixture. */
    private function cenario(): array
    {
        Schema::table('mensagens', function (Blueprint $tabela) {
            $tabela->text('contexto')->nullable();
        });

        $base = [
            'formato' => 'psicografia', 'casa' => 'CEMA', 'status' => 'pendente',
            'created_at' => now(), 'updated_at' => now(),
        ];

        return [
            'so_contexto' => DB::table('mensagens')->insertGetId($base + [
                'titulo' => 'Só contexto', 'slug' => 'so-contexto',
                'contexto' => 'Texto que precisa sobreviver.', 'resumo' => null,
            ]),
            'resumo_vazio' => DB::table('mensagens')->insertGetId($base + [
                'titulo' => 'Resumo vazio', 'slug' => 'resumo-vazio',
                'contexto' => 'Também precisa sobreviver.', 'resumo' => '',
            ]),
            'os_dois' => DB::table('mensagens')->insertGetId($base + [
                'titulo' => 'Os dois', 'slug' => 'os-dois',
                'contexto' => 'Texto do contexto.', 'resumo' => 'Texto do resumo.',
            ]),
            'sem_contexto' => DB::table('mensagens')->insertGetId($base + [
                'titulo' => 'Sem contexto', 'slug' => 'sem-contexto',
                'contexto' => null, 'resumo' => 'Resumo intacto.',
            ]),
        ];
    }

    private function resumo(int $id): ?string
    {
        return DB::table('mensagens')->where('id', $id)->value('resumo');
    }

    /** I1: onde o resumo estava vazio (NULL ou ''), o texto do contexto passa a ser o resumo. */
    public function test_i1_copia_o_contexto_quando_o_resumo_esta_vazio(): void
    {
        $ids = $this->cenario();

        $this->migration()->up();

        $this->assertSame('Texto que precisa sobreviver.', $this->resumo($ids['so_contexto']));
        $this->assertSame('Também precisa sobreviver.', $this->resumo($ids['resumo_vazio']));
    }

    /** I2: com os dois preenchidos, o resumo VENCE — precedência explícita, não acidente de ordem. */
    public function test_i2_resumo_preenchido_nao_e_sobrescrito(): void
    {
        $ids = $this->cenario();

        $this->migration()->up();

        $this->assertSame('Texto do resumo.', $this->resumo($ids['os_dois']));
        $this->assertSame('Resumo intacto.', $this->resumo($ids['sem_contexto']));
    }

    /** Idempotência: rodar duas vezes não muda nada (é o que torna o par de migrations seguro). */
    public function test_rodar_duas_vezes_e_no_op(): void
    {
        $ids = $this->cenario();

        $this->migration()->up();
        $this->migration()->up();

        $this->assertSame('Texto que precisa sobreviver.', $this->resumo($ids['so_contexto']));
        $this->assertSame('Texto do resumo.', $this->resumo($ids['os_dois']));
    }
}
```

E em `tests/Feature/Models/MensagemTest.php`, mover `contexto` para a lista das **podadas** (I3 — a rede contra um rollback silencioso da fusão):

```php
    public function test_colunas_esperadas_e_podadas(): void
    {
        foreach (['titulo', 'slug', 'corpo', 'resumo', 'formato', 'data_recebimento', 'casa', 'link_arquivo', 'liberar_download', 'nivel', 'status', 'wp_id'] as $coluna) {
            $this->assertTrue(Schema::hasColumn('mensagens', $coluna), "coluna esperada ausente: {$coluna}");
        }
        foreach (['origem_da_mensagem', 'grupo_mediunico', 'casa_espirita', 'contexto'] as $coluna) {
            $this->assertFalse(Schema::hasColumn('mensagens', $coluna), "coluna podada presente: {$coluna}");
        }
    }
```

- [ ] **Step 2: Rodar e verificar que falha**

```
docker compose exec -T app php artisan test --filter="FusaoContextoResumoTest|MensagemTest"
```

Esperado: **FAIL**. O `FusaoContextoResumoTest` estoura no `require` (a migration não existe) e o `cenario()` estoura ao criar uma coluna que ainda existe; `test_colunas_esperadas_e_podadas` reprova com *"coluna podada presente: contexto"*.

- [ ] **Step 3: Criar a migration de fusão**

`database/migrations/2026_07_23_000001_funde_contexto_em_resumo_nas_mensagens.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-23

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Funde `contexto` em `resumo` (F4c-D). O texto só é copiado onde `resumo` está vazio (NULL ou '')
 * e `contexto` tem conteúdo; onde os dois estão preenchidos o `resumo` VENCE, e o `contexto` é
 * descartado junto com a coluna, na migration seguinte.
 *
 * DB::table e não Eloquent: o model tem LogsActivity, e um laço com ->save() viraria uma enxurrada
 * de "mensagem atualizada" no histórico que o diretor do DEPAE lê — mesmo motivo do
 * activity()->withoutLogs() em ImportarResumosMensagens.php:45.
 *
 * Dev em 2026-07-22: 181 mensagens, 2 com contexto, 1 realmente copiada (id 191).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('mensagens')
            ->whereNotNull('contexto')
            ->where('contexto', '<>', '')
            ->where(function ($consulta) {
                // blank() em SQL: NULL e '' — mesmo critério de ImportarResumosMensagens.php:68.
                $consulta->whereNull('resumo')->orWhere('resumo', '');
            })
            // Identificador NU de propósito: "contexto" vira literal de string no MySQL e
            // `contexto` quebra no SQLite. Sem aspas, os dois drivers leem a coluna.
            ->update(['resumo' => DB::raw('contexto')]);
    }

    public function down(): void
    {
        // Sem reversão: nada distingue o resumo que veio do contexto do que sempre foi resumo.
        // Molde de 2026_06_29_000001_mover_fotos_palestrante_para_media_library.php:33.
    }
};
```

- [ ] **Step 4: Criar a migration de drop**

`database/migrations/2026_07_23_000002_drop_contexto_from_mensagens_table.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-23

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remove `contexto` (F4c-D): o texto editorial da mensagem passou a ser o `resumo`, renderizado
 * como lead. A migration anterior já fundiu o dado. Par no molde de
 * 2026_06_29_000001 + 2026_06_29_000002 (migra o dado, depois dropa a coluna).
 *
 * São DUAS migrations porque migration NÃO roda em transação em MySQL nem em SQLite
 * (Migrator.php:448-451 + Schema/Grammars/Grammar.php:31, que só Postgres e SQL Server
 * sobrescrevem): separadas, o passo concluído fica registrado em `migrations` e um novo
 * `migrate` retoma do ponto certo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mensagens', function (Blueprint $tabela) {
            $tabela->dropColumn('contexto');
        });
    }

    /**
     * DESTRUTIVO — recria a coluna VAZIA. O texto fundido fica só no `resumo`, e o `up()` da
     * migration anterior já descartou de propósito o `contexto` das linhas que tinham resumo:
     * não há de onde voltar. Rollback aqui é de SCHEMA, nunca de dado.
     */
    public function down(): void
    {
        Schema::table('mensagens', function (Blueprint $tabela) {
            // ->after('corpo') devolve a posição original no MySQL; no SQLite é ignorado em silêncio.
            $tabela->text('contexto')->nullable()->after('corpo');
        });
    }
};
```

- [ ] **Step 5: Rodar e verificar que passa**

```
docker compose exec -T app php artisan test --filter="FusaoContextoResumoTest|MensagemTest"
```

Esperado: **PASS** — 3 testes de fusão + o de colunas. A suíte roda `migrate` do zero em SQLite, então as duas migrations novas já são exercitadas aqui.

- [ ] **Step 6: Suíte completa — é o checkpoint do bloco 1**

```
docker compose exec -T app php artisan test
```

Esperado: **PASS, 1280**. A conta, task a task — **3 removidos** (`test_editar_contexto_registra_a_chave…` na 1, `test_form_tem_textarea_contexto` na 2, `test_contexto_e_escapado` na 3) e **5 novos** (`test_i6_resumo_do_medium_persiste` na 2, `test_faixa_de_contexto_nao_existe_mais` na 3, os 3 de fusão na 4). Os renomeados (`test_resumo_e_texto_puro_persistido`, `test_editar_resumo_para_null…`, `test_i11_form_da_curadoria_tem_resumo_e_nao_tem_contexto`, os dois de meta description) **não** mudam a contagem. 1278 − 3 + 5 = **1280**. Outro número ⇒ conferir a lista antes de seguir.

- [ ] **Step 7: Pint e commit**

```bash
docker compose exec -T app ./vendor/bin/pint
git add database/migrations/ tests/
git commit -m "feat(f4c-d): funde contexto em resumo e dropa a coluna

Duas migrations, nao uma: migration nao roda em transacao em MySQL nem em
SQLite, entao UPDATE e DROP nunca sao atomicos entre si. Separados, o passo
concluido fica registrado e um novo migrate retoma do ponto certo.

O teste recria a coluna e roda o up() de verdade: o CI migra banco VAZIO, e
exit 0 nao prova copia nenhuma. Precedencia travada — com os dois campos
preenchidos, o resumo vence."
```

---

## Task 5: `lang/pt_BR/validation.php`

**Files:**
- Create: `lang/pt_BR/validation.php`
- Create: `tests/Feature/Idioma/ValidationPtBrTest.php`

**Interfaces:**
- Consumes: nada do bloco 1 — esta task é independente.
- Produces: o arquivo de idioma. Nenhuma API de código.

- [ ] **Step 1: Escrever o teste de paridade (I13)**

⚠️ **O nível da comparação é o invariante** (SPEC §4, I13): **recursiva**, excluindo `custom` e `attributes` — as duas seções que divergem por desenho. Recursiva total dá vermelho falso; só 1º nível perde ~40 sub-chaves.

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-23

namespace Tests\Feature\Idioma;

use Tests\TestCase;

/**
 * Trava a completude de lang/pt_BR/validation.php contra o canônico do framework. O Translator faz
 * fallback por CHAVE, não por arquivo: uma regra faltando sai em inglês NA MESMA TELA, ao lado das
 * traduzidas. Quando um composer update trouxer regra nova, este teste fica vermelho — que é
 * exatamente o aviso que se quer.
 *
 * A comparação é RECURSIVA e EXCLUI `custom` e `attributes`: `attributes` é [] no canônico e tem
 * conteúdo no pt-BR (é o que faz as telas fora do Filament dizerem "data de nascimento" em vez de
 * "data nascimento"), e `custom` é placeholder. Comparar as duas daria vermelho falso — e o
 * "conserto" seria esvaziar justamente a seção que motiva o arquivo.
 */
class ValidationPtBrTest extends TestCase
{
    private const CAMINHO_CANONICO = 'vendor/laravel/framework/src/Illuminate/Translation/lang/en/validation.php';

    private const SECOES_QUE_DIVERGEM = ['custom', 'attributes'];

    /** @return list<string> chaves em notação de ponto, ordenadas */
    private function chaves(array $itens, string $prefixo = ''): array
    {
        $chaves = [];

        foreach ($itens as $chave => $valor) {
            $caminho = $prefixo === '' ? (string) $chave : "{$prefixo}.{$chave}";
            $chaves[] = $caminho;

            if (is_array($valor)) {
                $chaves = array_merge($chaves, $this->chaves($valor, $caminho));
            }
        }

        sort($chaves);

        return $chaves;
    }

    private function semSecoesQueDivergem(array $itens): array
    {
        return array_diff_key($itens, array_flip(self::SECOES_QUE_DIVERGEM));
    }

    public function test_cobre_todas_as_chaves_do_canonico(): void
    {
        $canonico = base_path(self::CAMINHO_CANONICO);

        $this->assertFileExists($canonico, "O canônico do Laravel mudou de lugar: {$canonico} não existe. Reveja este teste (I13) antes de concluir que falta tradução.");

        $esperadas = $this->chaves($this->semSecoesQueDivergem(require $canonico));
        $traduzidas = $this->chaves($this->semSecoesQueDivergem(require lang_path('pt_BR/validation.php')));

        $this->assertSame($esperadas, $traduzidas, 'lang/pt_BR/validation.php divergiu do canônico — chave faltando sai em inglês na mesma tela');
    }

    /** A seção `attributes` só importa fora do Filament (lá o :attribute vem do ->label()). */
    public function test_attributes_cobre_as_telas_fora_do_filament(): void
    {
        $traduzido = require lang_path('pt_BR/validation.php');

        foreach (['name', 'email', 'password', 'password_confirmation', 'token',
            'data_nascimento', 'endereco', 'whatsapp', 'whatsapp_publico', 'foto'] as $campo) {
            $this->assertArrayHasKey($campo, $traduzido['attributes'], "atributo sem rótulo pt-BR: {$campo}");
        }
    }

    /** Prova que o arquivo está em uso de verdade — não basta existir no disco. */
    public function test_mensagem_nativa_sai_em_portugues(): void
    {
        $this->assertSame('pt_BR', app()->getLocale());
        $this->assertSame('O campo nome é obrigatório.', __('validation.required', ['attribute' => 'nome']));
    }
}
```

- [ ] **Step 2: Rodar e verificar que falha**

```
docker compose exec -T app php artisan test --filter=ValidationPtBrTest
```

Esperado: **FAIL** — `require lang_path('pt_BR/validation.php')` estoura (arquivo inexistente). O `assertFileExists` do canônico deve **passar**; se ele falhar, o caminho do vendor mudou e é o teste que precisa de revisão.

- [ ] **Step 3: Criar o arquivo**

`lang/pt_BR/validation.php` — as **107 regras** do canônico da v13.17.0, na mesma ordem, mais `custom` e `attributes`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-23

return [

    /*
    |--------------------------------------------------------------------------
    | Mensagens de validação (pt-BR)
    |--------------------------------------------------------------------------
    |
    | Espelha o canônico do framework (Illuminate/Translation/lang/en/validation.php,
    | v13.17.0). A completude é travada por Tests\Feature\Idioma\ValidationPtBrTest:
    | o Translator faz fallback por CHAVE, então uma regra faltando sai em inglês
    | ao lado das traduzidas, na mesma tela.
    |
    | Dentro de schemas Filament o :attribute vem do ->label() do campo, e a seção
    | `attributes` daqui é ignorada. Ela vale para o que é validado fora do Filament:
    | Fortify (login/cadastro/senha) e o Livewire EditarPerfil.
    |
    */

    'accepted' => 'O campo :attribute deve ser aceito.',
    'accepted_if' => 'O campo :attribute deve ser aceito quando :other for :value.',
    'active_url' => 'O campo :attribute deve ser uma URL válida.',
    'after' => 'O campo :attribute deve ser uma data posterior a :date.',
    'after_or_equal' => 'O campo :attribute deve ser uma data posterior ou igual a :date.',
    'alpha' => 'O campo :attribute deve conter apenas letras.',
    'alpha_dash' => 'O campo :attribute deve conter apenas letras, números, hifens e sublinhados.',
    'alpha_num' => 'O campo :attribute deve conter apenas letras e números.',
    'any_of' => 'O campo :attribute é inválido.',
    'array' => 'O campo :attribute deve ser uma lista.',
    'ascii' => 'O campo :attribute deve conter apenas caracteres alfanuméricos e símbolos de um byte.',
    'before' => 'O campo :attribute deve ser uma data anterior a :date.',
    'before_or_equal' => 'O campo :attribute deve ser uma data anterior ou igual a :date.',
    'between' => [
        'array' => 'O campo :attribute deve ter entre :min e :max itens.',
        'file' => 'O campo :attribute deve ter entre :min e :max quilobytes.',
        'numeric' => 'O campo :attribute deve estar entre :min e :max.',
        'string' => 'O campo :attribute deve ter entre :min e :max caracteres.',
    ],
    'boolean' => 'O campo :attribute deve ser verdadeiro ou falso.',
    'can' => 'O campo :attribute contém um valor não autorizado.',
    'confirmed' => 'A confirmação do campo :attribute não confere.',
    'contains' => 'O campo :attribute não contém um valor obrigatório.',
    'current_password' => 'A senha está incorreta.',
    'date' => 'O campo :attribute deve ser uma data válida.',
    'date_equals' => 'O campo :attribute deve ser uma data igual a :date.',
    'date_format' => 'O campo :attribute deve corresponder ao formato :format.',
    'decimal' => 'O campo :attribute deve ter :decimal casas decimais.',
    'declined' => 'O campo :attribute deve ser recusado.',
    'declined_if' => 'O campo :attribute deve ser recusado quando :other for :value.',
    'different' => 'Os campos :attribute e :other devem ser diferentes.',
    'digits' => 'O campo :attribute deve ter :digits dígitos.',
    'digits_between' => 'O campo :attribute deve ter entre :min e :max dígitos.',
    'dimensions' => 'O campo :attribute tem dimensões de imagem inválidas.',
    'distinct' => 'O campo :attribute tem um valor duplicado.',
    'doesnt_contain' => 'O campo :attribute não pode conter nenhum dos seguintes: :values.',
    'doesnt_end_with' => 'O campo :attribute não pode terminar com um dos seguintes: :values.',
    'doesnt_start_with' => 'O campo :attribute não pode começar com um dos seguintes: :values.',
    'email' => 'O campo :attribute deve ser um endereço de e-mail válido.',
    'encoding' => 'O campo :attribute deve estar codificado em :encoding.',
    'ends_with' => 'O campo :attribute deve terminar com um dos seguintes: :values.',
    'enum' => 'O valor selecionado em :attribute é inválido.',
    'exists' => 'O valor selecionado em :attribute é inválido.',
    'extensions' => 'O campo :attribute deve ter uma das seguintes extensões: :values.',
    'file' => 'O campo :attribute deve ser um arquivo.',
    'filled' => 'O campo :attribute deve ter um valor.',
    'gt' => [
        'array' => 'O campo :attribute deve ter mais de :value itens.',
        'file' => 'O campo :attribute deve ser maior que :value quilobytes.',
        'numeric' => 'O campo :attribute deve ser maior que :value.',
        'string' => 'O campo :attribute deve ter mais de :value caracteres.',
    ],
    'gte' => [
        'array' => 'O campo :attribute deve ter :value itens ou mais.',
        'file' => 'O campo :attribute deve ser maior ou igual a :value quilobytes.',
        'numeric' => 'O campo :attribute deve ser maior ou igual a :value.',
        'string' => 'O campo :attribute deve ter :value caracteres ou mais.',
    ],
    'hex_color' => 'O campo :attribute deve ser uma cor hexadecimal válida.',
    'image' => 'O campo :attribute deve ser uma imagem.',
    'in' => 'O valor selecionado em :attribute é inválido.',
    'in_array' => 'O campo :attribute deve existir em :other.',
    'in_array_keys' => 'O campo :attribute deve conter pelo menos uma das seguintes chaves: :values.',
    'integer' => 'O campo :attribute deve ser um número inteiro.',
    'ip' => 'O campo :attribute deve ser um endereço IP válido.',
    'ipv4' => 'O campo :attribute deve ser um endereço IPv4 válido.',
    'ipv6' => 'O campo :attribute deve ser um endereço IPv6 válido.',
    'json' => 'O campo :attribute deve ser um texto JSON válido.',
    'list' => 'O campo :attribute deve ser uma lista.',
    'lowercase' => 'O campo :attribute deve estar em letras minúsculas.',
    'lt' => [
        'array' => 'O campo :attribute deve ter menos de :value itens.',
        'file' => 'O campo :attribute deve ser menor que :value quilobytes.',
        'numeric' => 'O campo :attribute deve ser menor que :value.',
        'string' => 'O campo :attribute deve ter menos de :value caracteres.',
    ],
    'lte' => [
        'array' => 'O campo :attribute não pode ter mais de :value itens.',
        'file' => 'O campo :attribute deve ser menor ou igual a :value quilobytes.',
        'numeric' => 'O campo :attribute deve ser menor ou igual a :value.',
        'string' => 'O campo :attribute deve ter :value caracteres ou menos.',
    ],
    'mac_address' => 'O campo :attribute deve ser um endereço MAC válido.',
    'max' => [
        'array' => 'O campo :attribute não pode ter mais de :max itens.',
        'file' => 'O campo :attribute não pode ter mais de :max quilobytes.',
        'numeric' => 'O campo :attribute não pode ser maior que :max.',
        'string' => 'O campo :attribute não pode ter mais de :max caracteres.',
    ],
    'max_digits' => 'O campo :attribute não pode ter mais de :max dígitos.',
    'mimes' => 'O campo :attribute deve ser um arquivo do tipo: :values.',
    'mimetypes' => 'O campo :attribute deve ser um arquivo do tipo: :values.',
    'min' => [
        'array' => 'O campo :attribute deve ter pelo menos :min itens.',
        'file' => 'O campo :attribute deve ter pelo menos :min quilobytes.',
        'numeric' => 'O campo :attribute deve ser pelo menos :min.',
        'string' => 'O campo :attribute deve ter pelo menos :min caracteres.',
    ],
    'min_digits' => 'O campo :attribute deve ter pelo menos :min dígitos.',
    'missing' => 'O campo :attribute deve estar ausente.',
    'missing_if' => 'O campo :attribute deve estar ausente quando :other for :value.',
    'missing_unless' => 'O campo :attribute deve estar ausente a menos que :other seja :value.',
    'missing_with' => 'O campo :attribute deve estar ausente quando :values estiver presente.',
    'missing_with_all' => 'O campo :attribute deve estar ausente quando :values estiverem presentes.',
    'multiple_of' => 'O campo :attribute deve ser um múltiplo de :value.',
    'not_in' => 'O valor selecionado em :attribute é inválido.',
    'not_regex' => 'O formato do campo :attribute é inválido.',
    'numeric' => 'O campo :attribute deve ser um número.',
    'password' => [
        'letters' => 'O campo :attribute deve conter pelo menos uma letra.',
        'mixed' => 'O campo :attribute deve conter pelo menos uma letra maiúscula e uma minúscula.',
        'numbers' => 'O campo :attribute deve conter pelo menos um número.',
        'symbols' => 'O campo :attribute deve conter pelo menos um símbolo.',
        'uncompromised' => 'O valor informado em :attribute apareceu em um vazamento de dados. Escolha outro.',
    ],
    'present' => 'O campo :attribute deve estar presente.',
    'present_if' => 'O campo :attribute deve estar presente quando :other for :value.',
    'present_unless' => 'O campo :attribute deve estar presente a menos que :other seja :value.',
    'present_with' => 'O campo :attribute deve estar presente quando :values estiver presente.',
    'present_with_all' => 'O campo :attribute deve estar presente quando :values estiverem presentes.',
    'prohibited' => 'O campo :attribute é proibido.',
    'prohibited_if' => 'O campo :attribute é proibido quando :other for :value.',
    'prohibited_if_accepted' => 'O campo :attribute é proibido quando :other for aceito.',
    'prohibited_if_declined' => 'O campo :attribute é proibido quando :other for recusado.',
    'prohibited_unless' => 'O campo :attribute é proibido a menos que :other esteja em :values.',
    'prohibits' => 'O campo :attribute impede que :other esteja presente.',
    'regex' => 'O formato do campo :attribute é inválido.',
    'required' => 'O campo :attribute é obrigatório.',
    'required_array_keys' => 'O campo :attribute deve conter entradas para: :values.',
    'required_if' => 'O campo :attribute é obrigatório quando :other for :value.',
    'required_if_accepted' => 'O campo :attribute é obrigatório quando :other for aceito.',
    'required_if_declined' => 'O campo :attribute é obrigatório quando :other for recusado.',
    'required_unless' => 'O campo :attribute é obrigatório a menos que :other esteja em :values.',
    'required_with' => 'O campo :attribute é obrigatório quando :values está presente.',
    'required_with_all' => 'O campo :attribute é obrigatório quando :values estão presentes.',
    'required_without' => 'O campo :attribute é obrigatório quando :values não está presente.',
    'required_without_all' => 'O campo :attribute é obrigatório quando nenhum de :values está presente.',
    'same' => 'Os campos :attribute e :other devem ser iguais.',
    'size' => [
        'array' => 'O campo :attribute deve conter :size itens.',
        'file' => 'O campo :attribute deve ter :size quilobytes.',
        'numeric' => 'O campo :attribute deve ser :size.',
        'string' => 'O campo :attribute deve ter :size caracteres.',
    ],
    'starts_with' => 'O campo :attribute deve começar com um dos seguintes: :values.',
    'string' => 'O campo :attribute deve ser um texto.',
    'timezone' => 'O campo :attribute deve ser um fuso horário válido.',
    'unique' => 'O valor informado em :attribute já está em uso.',
    'uploaded' => 'Falha ao enviar o campo :attribute.',
    'uppercase' => 'O campo :attribute deve estar em letras maiúsculas.',
    'url' => 'O campo :attribute deve ser uma URL válida.',
    'ulid' => 'O campo :attribute deve ser um ULID válido.',
    'uuid' => 'O campo :attribute deve ser um UUID válido.',

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
    ],

    /*
    | Só vale FORA do Filament (Fortify + Livewire EditarPerfil). Nos schemas Filament o
    | :attribute vem do ->label(), e pôr um campo daqueles aqui criaria uma segunda fonte
    | de verdade para o mesmo rótulo.
    */
    'attributes' => [
        'name' => 'nome',
        'email' => 'e-mail',
        'password' => 'senha',
        'password_confirmation' => 'confirmação de senha',
        'token' => 'token',
        'data_nascimento' => 'data de nascimento',
        'endereco' => 'endereço',
        'whatsapp' => 'WhatsApp',
        'whatsapp_publico' => 'exibir WhatsApp',
        'foto' => 'foto',
    ],

];
```

- [ ] **Step 4: Rodar e verificar que passa**

```
docker compose exec -T app php artisan test --filter=ValidationPtBrTest
```

Esperado: **PASS**, 3 testes. Se `test_cobre_todas_as_chaves_do_canonico` reprovar, o `assertSame` mostra exatamente qual chave falta ou sobra — **acrescentar a chave, nunca relaxar o teste**.

- [ ] **Step 5: Suíte completa — a mudança é global**

```
docker compose exec -T app php artisan test
```

Esperado: **PASS**. Nenhum teste da suíte compara texto de validação (o único que compara usa a constante `MensagemForm::MSG_NIVEL_OBRIGATORIO`, que vence o arquivo de idioma) — mas a mudança atinge o site inteiro, então aqui **não** vale `--filter`.

- [ ] **Step 6: Pint e commit**

```bash
docker compose exec -T app ./vendor/bin/pint
git add lang/pt_BR/validation.php tests/Feature/Idioma/
git commit -m "feat(f4c-d): mensagens de validacao em pt-BR

A causa raiz da interface em ingles nao era o APP_LOCALE (ja e pt_BR): era a
ausencia de lang/pt_BR/validation.php, que jogava toda chave no fallback en.

O teste compara as chaves com o canonico do vendor de forma RECURSIVA, menos
custom e attributes: recursiva total daria vermelho falso, e so o 1o nivel
perderia as ~40 sub-chaves das regras de tamanho e do password."
```

---

## Task 6: `auth.php` e `passwords.php`

Sem estes dois, o `/entrar` fica meio-traduzido: "O campo e-mail é obrigatório." ao lado de "These credentials do not match our records."

**Files:**
- Create: `lang/pt_BR/auth.php`
- Create: `lang/pt_BR/passwords.php`
- Test: `tests/Feature/Idioma/ValidationPtBrTest.php` (acrescenta 1 teste)

**Interfaces:**
- Consumes: `Tests\Feature\Idioma\ValidationPtBrTest` (Task 5).
- Produces: os dois arquivos de idioma.

- [ ] **Step 1: Escrever o teste**

Acrescentar a `tests/Feature/Idioma/ValidationPtBrTest.php`:

```php
    /** D9: meio-traduzido é pior que consistentemente em inglês — o /entrar mistura os dois arquivos. */
    public function test_auth_e_passwords_estao_traduzidos(): void
    {
        $this->assertSame('Estas credenciais não conferem com nossos registros.', __('auth.failed'));
        $this->assertSame('A senha informada está incorreta.', __('auth.password'));
        $this->assertSame('Enviamos o link de redefinição de senha para o seu e-mail.', __('passwords.sent'));
        $this->assertSame('Não encontramos nenhum usuário com esse endereço de e-mail.', __('passwords.user'));
    }
```

- [ ] **Step 2: Rodar e verificar que falha**

```
docker compose exec -T app php artisan test --filter=test_auth_e_passwords_estao_traduzidos
```

Esperado: **FAIL** — o `__()` devolve o texto em inglês do fallback.

- [ ] **Step 3: Criar `lang/pt_BR/auth.php`**

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-23

return [

    /*
    | Mensagens de autenticação (pt-BR) — espelha Illuminate/Translation/lang/en/auth.php.
    | Sem este arquivo o /entrar mostra a frase de credencial inválida em inglês, ao lado
    | das mensagens de validação já traduzidas.
    */

    'failed' => 'Estas credenciais não conferem com nossos registros.',
    'password' => 'A senha informada está incorreta.',
    'throttle' => 'Tentativas de acesso demais. Tente novamente em :seconds segundos.',

];
```

- [ ] **Step 4: Criar `lang/pt_BR/passwords.php`**

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-23

return [

    /*
    | Mensagens do broker de senha (pt-BR) — espelha Illuminate/Translation/lang/en/passwords.php.
    | São as respostas de /esqueci-a-senha e /redefinir-senha.
    */

    'reset' => 'Sua senha foi redefinida.',
    'sent' => 'Enviamos o link de redefinição de senha para o seu e-mail.',
    'throttled' => 'Aguarde um pouco antes de tentar novamente.',
    'token' => 'Este token de redefinição de senha é inválido.',
    'user' => 'Não encontramos nenhum usuário com esse endereço de e-mail.',

];
```

- [ ] **Step 5: Rodar e verificar que passa**

```
docker compose exec -T app php artisan test --filter="ValidationPtBrTest|Auth"
```

Esperado: **PASS**. Os testes de autenticação existentes asseram redirecionamento e sessão, não texto — nenhum quebra.

- [ ] **Step 6: Pint e commit**

```bash
docker compose exec -T app ./vendor/bin/pint
git add lang/pt_BR/auth.php lang/pt_BR/passwords.php tests/Feature/Idioma/
git commit -m "feat(f4c-d): traduz auth e passwords para pt-BR

Sao 8 chaves. Sem elas o /entrar responderia 'O campo e-mail e obrigatorio.'
ao lado de 'These credentials do not match our records.' — meio-traduzido e
pior que consistentemente em ingles."
```

---

## Task 7: A frase do slug e a copy dos autores

**Files:**
- Modify: `app/Filament/Schemas/MensagemForm.php:60-65`
- Modify: `resources/views/autores/index.blade.php:61`
- Test: `tests/Feature/Filament/MensagemResourceTest.php`

**Interfaces:**
- Consumes: `lang/pt_BR/validation.php` (Task 5) — a frase específica **precede** a genérica; as duas coexistem por decisão (SPEC §5.5).
- Produces: nada consumido adiante.

- [ ] **Step 1: Escrever o teste da frase**

⚠️ O `->unique()` do Filament **não** é a regra `unique` do Laravel: é um closure com `$fail(__(…))`. `assertHasFormErrors(['slug' => 'unique'])` na forma "nome da regra" **não funciona** — a asserção é sobre a **chave** ou sobre o **texto**.

Acrescentar a `tests/Feature/Filament/MensagemResourceTest.php`:

```php
    /** I12 (F4c-D): slug repetido responde em pt-BR, com frase acionável. */
    public function test_slug_repetido_reprova_em_portugues(): void
    {
        Mensagem::factory()->create(['slug' => 'slug-ja-usado']);

        Livewire::test(CreateMensagem::class)
            ->fillForm([
                'titulo' => 'Outra mensagem',
                'slug' => 'slug-ja-usado',
                'formato' => 'psicografia',
                'status' => Mensagem::STATUS_PENDENTE,
            ])
            ->call('create')
            ->assertHasFormErrors(['slug' => 'Este slug já está em uso. Ajuste-o antes de salvar.']);
    }
```

- [ ] **Step 2: Rodar e verificar que falha**

```
docker compose exec -T app php artisan test --filter=test_slug_repetido_reprova_em_portugues
```

Esperado: **FAIL** — a mensagem atual é a genérica do `validation.php` da Task 5 ("O valor informado em slug já está em uso."), não a acionável.

- [ ] **Step 3: Acrescentar a frase**

`app/Filament/Schemas/MensagemForm.php:60-65`, molde de [AgendaMetaMesResource.php:72-74](app/Filament/Resources/Agenda/AgendaMetaMesResource.php#L72-L74):

```php
                    TextInput::make('slug')
                        ->label('Slug')
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true)
                        // Precede a frase genérica do lang/pt_BR/validation.php, de propósito:
                        // 39 das 47 pendentes têm slug de máquina e precisam de revisão.
                        ->validationMessages(['unique' => 'Este slug já está em uso. Ajuste-o antes de salvar.'])
                        ->columnSpan(2),
```

- [ ] **Step 4: Rodar e verificar que passa**

```
docker compose exec -T app php artisan test --filter=MensagemResourceTest
```

Esperado: **PASS**.

- [ ] **Step 5: Ajustar a copy dos autores (D10)**

`resources/views/autores/index.blade.php:61` — a frase promete o que o site deixou de entregar. Trocar o trecho *"a data de recebimento, o formato e o contexto de cada comunicação"* por:

```
o acervo é mantido pelo DEPAE com respeito e critério: registramos a data de recebimento e o formato de cada comunicação, para que sirvam ao estudo sereno da doutrina.
```

Manter a marcação Blade da linha intacta — muda só o texto entre as tags.

- [ ] **Step 6: Rodar a suíte completa e commitar**

```
docker compose exec -T app php artisan test
docker compose exec -T app ./vendor/bin/pint
```

```bash
git add app/Filament/Schemas/MensagemForm.php resources/views/autores/index.blade.php tests/
git commit -m "feat(f4c-d): frase pt-BR do slug repetido e copy dos autores

A frase especifica precede a generica do validation.php de proposito: 39 das
47 pendentes tem slug de maquina e precisam de revisao antes de publicar.

A copy da lista de autores prometia registrar 'o contexto de cada
comunicacao' — promessa que a fusao desfaz."
```

---

## Verificação final (antes do PR)

- [ ] **Suíte completa verde:** `docker compose exec -T app php artisan test` — esperado **1285 passed**. Fechamento da conta: 1280 ao fim da Task 4 (bloco 1) **+ 3** do `ValidationPtBrTest` (Task 5) **+ 1** de auth/passwords (Task 6) **+ 1** da frase do slug (Task 7).
- [ ] **Pint limpo:** `docker compose exec -T app ./vendor/bin/pint --test`.
- [ ] **Allowlist do `contexto` fechada** (molde do I16 da F4c-AC):

```
docker compose exec -T app grep -rn "contexto" app/ resources/ database/ tests/
```

Esperado: **apenas** as ocorrências do **método** `AuditoriaAutorizacao::contexto()` (`AuditoriaAutorizacao.php:18,33,34,157` · `Mensagem.php:289` · `User.php:140` · `AgendaDia.php:177` · `AuditoriaHelperTest:48` · `HistoricoMensagemTest:132`), as **migrations históricas** (`2026_07_18_000001:19`, `2026_07_22_000001:15`) e as duas migrations **novas**. Zero ocorrências do campo em código de produção vivo.

- [ ] **Cutover no dev, nesta ordem** (SPEC §7 — inverter abre janela de erro 500):

```
docker compose exec -T app php artisan optimize:clear
docker compose restart app worker
docker compose exec -T app php artisan migrate
```

- [ ] **Conferir os 5 itens** da SPEC §7 — em especial o **5º**, que é o único que prova o bloco 1:

```
docker compose exec -T app php artisan tinker --execute="echo App\Models\Mensagem::find(191)->resumo;"
```

Esperado: o texto de 329 caracteres que era o `contexto`. **`migrate` com exit 0 não prova cópia.**

- [ ] **No corpo do PR, declarar:** (1) o `validation.php` é mudança **global** de mensagens, fora do módulo Mensagens — e conserta de brinde a frase em inglês de `/minha-conta/agenda`; (2) o estado intermediário do cutover (entre o restart e o migrate, o texto do `contexto` fica invisível — é a ordem, não perda de dado); (3) a divergência com o handoff de design, que documenta a "Faixa de contexto" como aprovada.
