# Avatares nos Selects do MensagemForm — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** dar avatar circular (foto) + fallback de iniciais às OPÇÕES dos Selects `autores` e `destinatarios` do `MensagemForm`, mudando só a apresentação — sem tocar gravação, pivô ou validação de nível.

**Architecture:** um helper único `App\Filament\Support\AvatarOpcao::html(?url, nome, iniciais)` monta o HTML da opção (estilo inline, `e()` no nome e na URL). O Select `autores` mantém `->relationship()` e ganha eager-load via 3º arg + `getOptionLabelFromRecordUsing`. O Select `destinatarios` troca o motor `->options()` client-side por `getSearchResultsUsing` (busca server-side sobre `name`) + `getOptionLabelsUsing` (hidrata selecionados, inclusive inativos). Ambos consolidados em métodos privados `selectAutores()`/`selectDestinatarios()`.

**Tech Stack:** PHP 8.3 · Laravel 13 · Filament 5 · Livewire 3 · PHPUnit · SQLite (teste) / MySQL (dev/prod).

**Referências:** SPEC [docs/superpowers/specs/2026-07-23-avatares-selects-mensagem-form.md](../specs/2026-07-23-avatares-selects-mensagem-form.md). Alvo: [MensagemForm.php](../../../app/Filament/Schemas/MensagemForm.php). Referência visual do fallback: [mensagens/show.blade.php:61-64](../../../resources/views/mensagens/show.blade.php#L61-L64).

## Global Constraints

- **Idioma:** tudo em pt-BR (comentários, mensagens, commits). Sintaxe/APIs de terceiros no original.
- **Sem migration, sem asset novo, sem `npm run build`.** Nenhuma mudança de banco.
- **Não tocar gravação (F1):** `SincronizadorDestinatarios`, `saveRelationships` dos autores, `RegraPublicacao`. Só a apresentação da opção.
- **Autores mantêm `->relationship()` (F2):** não trocar por `->options()` (precisa de `dehydrated(false)`/`saveRelationships`).
- **O2 — escape sempre:** `allowHtml()` não escapa; `e()` no **nome** E na **URL** ao montar o HTML.
- **O4 — estilo inline:** o HTML da opção usa `style="..."`; **nunca** classe utilitária do site (`from-gold`, `size-7`, `bg-gradient-to-br`).
- **O1 — eager-load:** autores via 3º arg `->with('media')`; destinatários `->with('perfil.media')` nas duas closures.
- **A1 — foto do destinatário:** `User` não é `HasMedia`; usar `$user->perfil?->foto_thumb_url` (o `?->` é obrigatório) e eager `perfil.media` (`->with('media')` no `User` estoura).
- **A3 — hidratar inativos:** `getOptionLabelsUsing` usa `whereKey($values)` **sem** filtro `ativo` e **sem** `limit`; senão o save de uma direcionada existente trava na validação (`Rule::in`).
- **R9 — testes ASCII:** nomes/termos de busca sem acento (LIKE é accent-insensitive no MySQL, accent-sensitive no SQLite).
- **Fallback visual (D1):** círculo `1.75rem`, gradiente `linear-gradient(to bottom right,#f2a81e,#d98a14)`, texto `#3a3266`, negrito `10px`; com foto, `<img>` `object-fit:cover`.
- **Cabeçalho de autoria** em arquivo novo: `Thiago Mourão — https://github.com/MouraoBSB — 2026-07-23`.
- **Dev:** editar PHP exige `docker compose restart app worker` (OPcache); **nunca** `migrate:fresh`/`wipe`/seed destrutivo; Pint antes de push.

---

## File Structure

- **Criar** `app/Filament/Support/AvatarOpcao.php` — helper que monta o HTML da opção (avatar + fallback). Responsabilidade única.
- **Criar** `tests/Unit/Filament/AvatarOpcaoTest.php` — unit do helper (I1–I5).
- **Criar** `tests/Feature/Filament/MensagemFormAutoresSelectTest.php` — I6 (autores sem N+1).
- **Modificar** `app/Filament/Schemas/MensagemForm.php` — imports; `selectAutores()`; `selectDestinatarios()`; 3 call sites de `autores` e 2 de `destinatarios`.
- **Evoluir** `tests/Feature/Filament/MensagemDestinatariosFormTest.php` — reescrever 2 métodos (`:89`→I7, `:108`→I8a) + 2 novos (I9, I10). I8(b) "save não trava" **já é coberto** por `MensagemDestinatariosPersistenciaTest:154` (não duplicar). **Um só lar dos invariantes de OPÇÃO dos destinatários.**

---

## Task 1: Helper `AvatarOpcao` (I1–I5)

**Files:**
- Create: `app/Filament/Support/AvatarOpcao.php`
- Test: `tests/Unit/Filament/AvatarOpcaoTest.php`

**Interfaces:**
- Produces: `App\Filament\Support\AvatarOpcao::html(?string $fotoUrl, string $nome, string $iniciais): string` — HTML de uma opção; `<img>` circular quando há URL, senão `<span>` com iniciais no gradiente; `e()` no nome e na URL; estilo 100% inline.

- [ ] **Step 1: Escrever os testes que falham (I1–I5)**

Create `tests/Unit/Filament/AvatarOpcaoTest.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-23

namespace Tests\Unit\Filament;

use App\Filament\Support\AvatarOpcao;
use Tests\TestCase;

class AvatarOpcaoTest extends TestCase
{
    /** I3: sem foto → círculo de iniciais, nenhum <img>. */
    public function test_sem_foto_usa_iniciais_e_nao_tem_img(): void
    {
        $html = AvatarOpcao::html(null, 'Ana Prado', 'AP');

        $this->assertStringContainsString('>AP<', $html);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringContainsString('linear-gradient(to bottom right,#f2a81e,#d98a14)', $html);
    }

    /** I4: com foto → <img> circular, sem o círculo de iniciais. */
    public function test_com_foto_usa_img_e_nao_tem_iniciais(): void
    {
        $html = AvatarOpcao::html('https://ex.test/f.webp', 'Ana Prado', 'AP');

        $this->assertStringContainsString('<img src="https://ex.test/f.webp"', $html);
        $this->assertStringContainsString('object-fit:cover', $html);
        $this->assertStringNotContainsString('linear-gradient', $html);
    }

    /** I1: o nome é escapado (allowHtml não escapa — O2). */
    public function test_escapa_o_nome(): void
    {
        $html = AvatarOpcao::html(null, '<img src=x onerror=alert(1)>"Fulano', 'FU');

        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $html);
        $this->assertStringContainsString('&quot;Fulano', $html);
        // Não há <img> vindo do NOME (só poderia haver o do avatar, que aqui é null):
        $this->assertStringNotContainsString('<img', $html);
    }

    /** I2: a URL é escapada dentro do src (O2). */
    public function test_escapa_a_url(): void
    {
        $html = AvatarOpcao::html('x" onerror="alert(1)', 'Fulano', 'FU');

        $this->assertStringContainsString('src="x&quot; onerror=&quot;alert(1)"', $html);
    }

    /** I5: estilo inline, sem classe utilitária do site (O4). */
    public function test_usa_estilo_inline_sem_classe_do_site(): void
    {
        $html = AvatarOpcao::html(null, 'Ana Prado', 'AP');

        $this->assertStringContainsString('style=', $html);
        $this->assertStringNotContainsString('class=', $html);
        foreach (['from-gold', 'size-7', 'bg-gradient-to-br', 'rounded-full'] as $token) {
            $this->assertStringNotContainsString($token, $html);
        }
    }
}
```

- [ ] **Step 2: Rodar os testes para vê-los falhar**

Run: `docker compose exec -T app php artisan test --filter=AvatarOpcaoTest`
Expected: FAIL com `Class "App\Filament\Support\AvatarOpcao" not found`.

- [ ] **Step 3: Implementar o helper**

Create `app/Filament/Support/AvatarOpcao.php`:

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

- [ ] **Step 4: Rodar os testes para vê-los passar**

Run: `docker compose exec -T app php artisan test --filter=AvatarOpcaoTest`
Expected: PASS (5 testes).

- [ ] **Step 5: Commit**

```bash
git add app/Filament/Support/AvatarOpcao.php tests/Unit/Filament/AvatarOpcaoTest.php
git commit -m "feat(avatares-selects): helper AvatarOpcao (avatar + fallback de iniciais, inline, escapado)"
```

---

## Task 2: Select `autores` com avatar + eager-load (I6)

**Files:**
- Modify: `app/Filament/Schemas/MensagemForm.php` (imports; novo `selectAutores()`; 3 call sites `:131`, `:270`, `:362`)
- Test: `tests/Feature/Filament/MensagemFormAutoresSelectTest.php` (criar)

**Interfaces:**
- Consumes: `AvatarOpcao::html()` (Task 1).
- Produces: `MensagemForm::selectAutores(): Filament\Forms\Components\Select` (privado estático) — o Select `autores` com `->relationship('autores','nome', with media)`, `->allowHtml()` e `getOptionLabelFromRecordUsing`.

- [ ] **Step 1: Escrever o teste de N+1 que falha (I6)**

Create `tests/Feature/Filament/MensagemFormAutoresSelectTest.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-23

namespace Tests\Feature\Filament;

use App\Filament\Resources\Mensagens\Pages\CreateMensagem;
use App\Models\AutorEspiritual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class MensagemFormAutoresSelectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsAdmin();
    }

    /**
     * I6: as opções dos autores têm avatar E eager-loadam a mídia — abrir o form dispara
     * EXATAMENTE 1 query na tabela `media` (o whereIn eager), não 1 por autor (N+1).
     * R1: conta só as queries que tocam `media` (estável; independe do total do mount).
     * P5: se instável na execução, converter para verificação no browser (SPEC §7).
     */
    public function test_opcoes_de_autores_carregam_a_midia_em_uma_query(): void
    {
        AutorEspiritual::factory()->count(3)->create();

        DB::connection()->flushQueryLog();
        DB::connection()->enableQueryLog();
        Livewire::test(CreateMensagem::class);
        $queriesDeMidia = collect(DB::connection()->getQueryLog())
            ->filter(fn (array $q): bool => str_contains($q['query'], '"media"'))
            ->count();
        DB::connection()->disableQueryLog();

        $this->assertSame(1, $queriesDeMidia, 'as opções de autores devem eager-loadar a mídia numa única query (sem N+1)');
    }
}
```

- [ ] **Step 2: Rodar o teste para vê-lo falhar (ainda não há avatar)**

Run: `docker compose exec -T app php artisan test --filter=MensagemFormAutoresSelectTest`
Expected: **FAIL** (`0 !== 1`) — hoje o Select `autores` não tem `getOptionLabelFromRecordUsing`, então o preload faz `pluck` do título ([Select.php:1046-1048](../../../vendor/filament/forms/src/Components/Select.php#L1046-L1048)) sem instanciar record nem tocar `foto_thumb_url` → **0 query de mídia**. (Este vermelho é "ainda não há avatar"; o vermelho do **N+1** aparece no Step 6.)

- [ ] **Step 3: Adicionar os imports em `MensagemForm.php`**

Modify `app/Filament/Schemas/MensagemForm.php` — na lista de `use`, acrescentar:

```php
use App\Filament\Support\AvatarOpcao;
use App\Models\AutorEspiritual;
```

- [ ] **Step 4: Adicionar `selectAutores()` SEM `->with('media')` (só o callback)**

Modify `app/Filament/Schemas/MensagemForm.php` — acrescentar o método privado (por ex. logo antes de `blocoDestinatarios()`). **Nesta primeira versão o 3º arg de eager-load fica de fora**, de propósito, para o I6 provar que pega o N+1 (Step 6):

```php
    /**
     * Select `autores` compartilhado pelos 3 schemas. `->relationship()` é OBRIGATÓRIO (F2):
     * fica dehydrated(false) e grava só em saveRelationships(). O avatar da opção vem do helper via
     * getOptionLabelFromRecordUsing (allowHtml não escapa — O2). O eager-load da mídia (3º arg) entra no Step 7.
     */
    private static function selectAutores(): Select
    {
        return Select::make('autores')
            ->label('Autores espirituais')
            ->relationship('autores', 'nome')
            ->multiple()
            ->preload()
            ->searchable()
            ->allowHtml()
            ->getOptionLabelFromRecordUsing(
                fn (AutorEspiritual $record): string => AvatarOpcao::html($record->foto_thumb_url, $record->nome, $record->iniciais)
            );
    }
```

- [ ] **Step 5: Trocar os 3 call sites de `autores` por `self::selectAutores()`**

Modify `app/Filament/Schemas/MensagemForm.php`. Em `schemaAdmin` (Section "Autoria e relações", ~`:131`), `schemaMedium` (Section "Autoria", ~`:270`) e `schemaCuradoria` (Section "Autoria", ~`:362`), substituir o bloco:

```php
                    Select::make('autores')
                        ->label('Autores espirituais')
                        ->relationship('autores', 'nome')
                        ->multiple()
                        ->preload()
                        ->searchable(),
```

por:

```php
                    self::selectAutores(),
```

(Em `schemaMedium`/`schemaCuradoria` remover também os comentários `// ->relationship() é OBRIGATÓRIO...` que precediam o Select — a justificativa agora vive no docblock de `selectAutores()`.)

- [ ] **Step 6: Rodar o I6 → o vermelho do N+1**

Run: `docker compose exec -T app php artisan test --filter=MensagemFormAutoresSelectTest`
Expected: **FAIL** (`3 !== 1`) — agora o `getOptionLabelFromRecordUsing` instancia cada autor e lê `foto_thumb_url` ([Select.php:1098,1104-1108](../../../vendor/filament/forms/src/Components/Select.php#L1098)), disparando **1 query de mídia por autor**. É este o vermelho que prova o teste pegando o N+1 (mesma armadilha que o Step 8 da Task 3 evita para o I9).

- [ ] **Step 7: Adicionar `->with('media')` ao 3º arg de `selectAutores()`**

Modify `app/Filament/Schemas/MensagemForm.php` — em `selectAutores()`, trocar `->relationship('autores', 'nome')` por (3º arg = eager-load — O1/A2; closure SEM type-hint, evita TypeError Relation×Builder):

```php
            ->relationship('autores', 'nome', fn ($query) => $query->with('media'))
```

- [ ] **Step 8: Rodar o I6 (e os testes de autores existentes) → verde**

Run: `docker compose exec -T app php artisan test --filter=MensagemFormAutoresSelectTest`
Expected: PASS (`1 === 1` — uma única query de mídia, eager).

Run: `docker compose exec -T app php artisan test --filter=MensagemResourceTest`
Expected: PASS (nenhum teste de autores lê o label; o HTML na opção não os quebra).

- [ ] **Step 9: Commit**

```bash
git add app/Filament/Schemas/MensagemForm.php tests/Feature/Filament/MensagemFormAutoresSelectTest.php
git commit -m "feat(avatares-selects): avatar nas opcoes de autores (selectAutores + eager-load media)"
```

---

## Task 3: Select `destinatarios` server-side com avatar (I7, I8a, I9, I10)

**Files:**
- Modify: `app/Filament/Schemas/MensagemForm.php` (novo `selectDestinatarios()`; call site inline `schemaAdmin` `:153-169`; call site em `blocoDestinatarios()` `:202-213`)
- Evolve: `tests/Feature/Filament/MensagemDestinatariosFormTest.php` (reescrever `:89`, `:108`; +2 novos)
- Guarda existente (não tocar, deve seguir verde): `tests/Feature/Filament/MensagemDestinatariosPersistenciaTest.php:154,176` — prova end-to-end do A3 (o `getOptionLabelsUsing` hidratar os selecionados).

**Interfaces:**
- Consumes: `AvatarOpcao::html()` (Task 1); `App\Models\User` (já importado); `App\Enums\VisibilidadeMensagem` (já importado).
- Produces: `MensagemForm::selectDestinatarios(): Filament\Forms\Components\Select` (privado estático) — base do Select `destinatarios` com `->allowHtml()`, `getSearchResultsUsing` (busca server-side sobre `name`, só ativos, eager `perfil.media`, teto 50) e `getOptionLabelsUsing` (hidrata selecionados, inclusive inativos). Cada call site aplica `->helperText()`/`->required()` por cima.

- [ ] **Step 1: Reescrever o teste `:89` para a nova API (I7) — deve falhar**

Modify `tests/Feature/Filament/MensagemDestinatariosFormTest.php`. Substituir o método `test_select_de_destinatarios_nao_oferece_usuario_inativo` (`:89`) por:

```php
    /** I7: a busca é server-side sobre `name` (não sobre o HTML) e só oferece ATIVOS. */
    public function test_busca_de_destinatarios_e_por_nome_e_so_ativos(): void
    {
        $ativo = User::factory()->create(['name' => 'Ana Ativa']);
        User::factory()->create(['name' => 'Ivo Inativo', 'ativo' => false]);

        Livewire::test(CreateMensagem::class)
            ->fillForm(['nivel' => 'direcionada'])
            ->assertFormFieldExists('destinatarios', function (Select $f) use ($ativo): bool {
                $porNome = $f->getSearchResults('Ana');           // acha o ativo
                $inativo = $f->getSearchResults('Ivo');           // NÃO acha o inativo (where ativo=true)
                $porHtml = $f->getSearchResults('span');          // termo que só existe no markup do avatar

                return array_key_exists($ativo->id, $porNome)
                    && $inativo === []
                    && $porHtml === [];
            });
    }
```

- [ ] **Step 2: Reescrever o teste `:108` para a nova API (I8a) + adicionar I9, I10**

Modify `tests/Feature/Filament/MensagemDestinatariosFormTest.php`. Substituir o método `test_select_mantem_o_destinatario_ja_selecionado_que_ficou_inativo` (`:108`) pelos **três** métodos abaixo. Acrescentar `use Illuminate\Support\Facades\DB;` no topo do arquivo. **I8(b) NÃO vira método novo:** "salvar direcionada com inativo sem travar" já é coberto ponta-a-ponta por [MensagemDestinatariosPersistenciaTest::test_nao_grava_destinatario_inativo_no_pivo:154](../../../tests/Feature/Filament/MensagemDestinatariosPersistenciaTest.php#L154) (ativo+inativo → sem erro + inativo filtrado do pivô por `efetivos()`), que passa a depender do novo `getOptionLabelsUsing` — é o guarda de regressão do A3 (Task 4 Step 2).

```php
    /**
     * I8(a): quem JÁ está selecionado é hidratado mesmo tendo sido desativado depois —
     * getOptionLabelsUsing (whereKey SEM filtro ativo) faz o papel do antigo orWhereIn; senão
     * getInValidationRuleValues devolve [] e o Rule::in trava até um simples Salvar de título.
     */
    public function test_selecionado_que_ficou_inativo_e_hidratado(): void
    {
        $u = User::factory()->create(['name' => 'Ivo Desativado Depois']);
        $m = Mensagem::factory()->comNivel(VisibilidadeMensagem::Direcionada)->create();
        $m->destinatarios()->sync([$u->id]);
        $u->update(['ativo' => false]);

        Livewire::test(EditMensagem::class, ['record' => $m->getRouteKey()])
            ->assertFormFieldExists('destinatarios', fn (Select $f): bool => array_key_exists($u->id, $f->getOptionLabels()));
    }

    /**
     * I9: busca e hidratação eager-loadam perfil.media — a busca dispara EXATAMENTE 1 query na
     * tabela `media` (o whereIn eager), não 1 por usuário (N+1). R1: conta só o que toca `media`.
     */
    public function test_busca_de_destinatarios_carrega_a_midia_em_uma_query(): void
    {
        for ($i = 1; $i <= 4; $i++) {
            User::factory()->create(['name' => "Teste {$i}"])->perfil()->create([]); // perfil sem foto: exercita os 2 hops (perfil + media)
        }

        $queriesDeMidia = 0;
        Livewire::test(CreateMensagem::class)
            ->fillForm(['nivel' => 'direcionada'])
            ->assertFormFieldExists('destinatarios', function (Select $f) use (&$queriesDeMidia): bool {
                DB::connection()->flushQueryLog();
                DB::connection()->enableQueryLog();
                $f->getSearchResults('Teste');
                $queriesDeMidia = collect(DB::connection()->getQueryLog())
                    ->filter(fn (array $q): bool => str_contains($q['query'], '"media"'))
                    ->count();
                DB::connection()->disableQueryLog();

                return true;
            });

        $this->assertSame(1, $queriesDeMidia, 'a busca deve eager-loadar perfil.media numa única query (sem N+1)');
    }

    /** I10: usuário SEM PerfilMembro passa pelas closures (?->) sem "read property on null". */
    public function test_usuario_sem_perfil_nao_quebra(): void
    {
        $u = User::factory()->create(['name' => 'Sem Perfil']); // sem $u->perfil()->create()

        Livewire::test(CreateMensagem::class)
            ->fillForm(['nivel' => 'direcionada'])
            ->assertFormFieldExists('destinatarios', fn (Select $f): bool => array_key_exists($u->id, $f->getSearchResults('Sem Perfil')));
    }
```

- [ ] **Step 3: Rodar os testes de destinatários para ver o estado atual (I7, I9, I10 vermelhos)**

Run: `docker compose exec -T app php artisan test --filter=MensagemDestinatariosFormTest`
Expected: **FAIL** em I7, I9 **e** I10 — o form ainda usa `->options()` (sem `getSearchResultsUsing`), então `getSearchResults()` devolve `[]` ([Select.php:703](../../../vendor/filament/forms/src/Components/Select.php#L703)): I7 não acha o ativo, I9 conta **0** query de mídia (≠ 1) e I10 não acha o user sem perfil. **I8a** já passa pelo `orWhereIn` atual — é migração de API, não driver vermelho. (R2: I9 e I10 também são vermelhos-antes; o Step 8 confirma depois que o **1** do I9 é especificamente o eager-load.)

- [ ] **Step 4: Adicionar o método `selectDestinatarios()` em `MensagemForm.php`**

Modify `app/Filament/Schemas/MensagemForm.php` — acrescentar o método privado (por ex. logo antes de `blocoDestinatarios()`):

```php
    /**
     * Base do Select `destinatarios` compartilhada pelo inline do schemaAdmin e por blocoDestinatarios().
     * Motor SERVER-SIDE (D2): a busca casa a coluna `name` (O3, imune ao filtro sobre HTML do allowHtml);
     * getOptionLabelsUsing hidrata os já-selecionados INCLUSIVE inativos (whereKey SEM filtro `ativo`,
     * SEM limit — papel do antigo orWhereIn; senão o Rule::in trava até um Salvar de título, A3).
     * Foto do destinatário via perfil (User não é HasMedia — A1), eager `perfil.media` (O1). Avatar
     * pelo helper (allowHtml não escapa — O2). Cada call site aplica ->helperText()/->required().
     */
    private static function selectDestinatarios(): Select
    {
        return Select::make('destinatarios')
            ->label('Destinatários')
            ->multiple()
            ->searchable()
            ->minItems(1)
            ->columnSpanFull()
            ->allowHtml()
            ->getSearchResultsUsing(fn (string $search): array => User::query()
                ->where('ativo', true)
                ->where('name', 'like', "%{$search}%")
                ->with('perfil.media')
                ->orderBy('name')
                ->limit(50)
                ->get()
                // LIKE cru: `%`/`_` do termo NÃO escapados; sem generate_search_term_expression (R8, aceito).
                ->mapWithKeys(fn (User $u) => [$u->id => AvatarOpcao::html($u->perfil?->foto_thumb_url, $u->name, $u->iniciais)])
                ->all())
            ->getOptionLabelsUsing(fn (array $values): array => User::query()
                ->whereKey($values)
                ->with('perfil.media')
                ->orderBy('name')
                ->get()
                ->mapWithKeys(fn (User $u) => [$u->id => AvatarOpcao::html($u->perfil?->foto_thumb_url, $u->name, $u->iniciais)])
                ->all());
    }
```

- [ ] **Step 5: Trocar o call site inline do `schemaAdmin`**

Modify `app/Filament/Schemas/MensagemForm.php`. Na Section "Destinatários" do `schemaAdmin` (`:149-170`), substituir o Select inline (`:153-169`) por:

```php
                    self::selectDestinatarios()
                        ->helperText('Obrigatório para mensagens de nível "Direcionada".')
                        ->required(fn (Get $get): bool => $get('nivel') === VisibilidadeMensagem::Direcionada->value),
```

(Mantém a `Section::make('Destinatários')->description(...)->visible(...)` ao redor, inalterada.)

- [ ] **Step 6: Trocar o call site em `blocoDestinatarios()`**

Modify `app/Filament/Schemas/MensagemForm.php`. Em `blocoDestinatarios()` (`:196-215`), substituir o Select inteiro (`:202-213`) por:

```php
                self::selectDestinatarios()->required($ehDirecionada),
```

- [ ] **Step 7: Rodar os testes de destinatários para vê-los passar**

Run: `docker compose exec -T app php artisan test --filter=MensagemDestinatariosFormTest`
Expected: PASS (todos — incluindo os visíveis/required que já existiam: `test_nivel_e_live...`, `test_destinatarios_visivel...`, `test_direcionada_sem_destinatario_reprova`, `test_nao_direcionada_sem_destinatario_salva`).

- [ ] **Step 8: Confirmar que o I9 pega a regressão de N+1**

Temporariamente remover `->with('perfil.media')` das DUAS closures de `selectDestinatarios()` e rodar:

Run: `docker compose exec -T app php artisan test --filter=test_busca_de_destinatarios_carrega_a_midia_em_uma_query`
Expected: **FAIL** (`4 !== 1` — uma query de mídia por usuário) — confirma que o `1` do I9 é o eager-load, não um acaso.

Restaurar o `->with('perfil.media')` nas duas closures e rodar de novo:

Run: `docker compose exec -T app php artisan test --filter=test_busca_de_destinatarios_carrega_a_midia_em_uma_query`
Expected: PASS (`1 === 1`).

- [ ] **Step 9: Commit**

```bash
git add app/Filament/Schemas/MensagemForm.php tests/Feature/Filament/MensagemDestinatariosFormTest.php
git commit -m "feat(avatares-selects): destinatarios server-side com avatar (selectDestinatarios, evolui I7/I8)"
```

---

## Task 4: Verificação final + cutover no dev

**Files:** nenhum arquivo de produção novo. Roda a suíte completa, o Pint e o cutover.

- [ ] **Step 1: Pint (o CI aborta o job se houver drift)**

Run: `docker compose exec -T app ./vendor/bin/pint`
Expected: arquivos formatados (0 problemas após ajuste). Se alterar algo, `git add -A && git commit -m "style(avatares-selects): pint"`.

- [ ] **Step 2: Suíte completa (0 regressões)**

Run: `docker compose exec -T app php artisan test`
Expected: PASS. **Guardas do A3 que DEVEM seguir verdes** (dependem do novo `getOptionLabelsUsing` hidratar os selecionados): `MensagemDestinatariosPersistenciaTest::test_nao_grava_destinatario_inativo_no_pivo` (:154 — ativo+inativo → sem erro + inativo fora do pivô) e `::test_id_inexistente_reprova_na_validacao_e_nao_entra_no_pivo` (:176 — `whereKey` exclui id forjado → `Rule::in` vazio reprova em `.0`). Também verdes sem tocar: o resto de `MensagemDestinatariosPersistenciaTest`, `MensagemResourceTest`, `CuradoriaContaTest`, `MensagensConta*`, `RegraPublicacao*`, e os `getOptions()` do `nivel` (`MensagemAdminAutoriaNivelTest:31`, `CuradoriaContaTest:221`, `MensagemResourceTest:75`) — I11.

- [ ] **Step 3: Greps de allowlist (sanidade da SPEC §9)**

Run: `docker compose exec -T app grep -rn "->options(" app/Filament/Schemas/MensagemForm.php`
Expected: nenhuma linha do Select `destinatarios` (só permanecem `formato`, `nivel`, `status`, `relacionadas`, que usam `->options()` legitimamente).

Run: `docker compose exec -T app grep -rn "class=" app/Filament/Support/AvatarOpcao.php`
Expected: nenhuma ocorrência (estilo 100% inline — O4).

- [ ] **Step 4: Cutover no dev**

```bash
docker compose exec -T app php artisan optimize:clear
docker compose restart app worker
```

- [ ] **Step 5: Conferência visual no browser (do dono — o que a suíte não prova, R7/§7 da SPEC)**

1. `/admin` → criar/editar Mensagem → abrir **Autores**: opção com foto = miniatura circular; sem foto = iniciais no gradiente gold. Repetir em **Destinatários** (nível "Direcionada").
2. `/minha-conta/mensagens` (médium) e `/minha-conta/curadoria` (diretor DEPAE): repetir nos 2 Selects.
3. Busca de destinatários: parte de um nome filtra por nome; `web`/`img` NÃO traz todos; o dropdown pede "digite para buscar" ao abrir (P2).
4. Direcionada com destinatário inativo: badge aparece e é removível; Salvar título não trava.
5. Debugbar: nº de queries constante ao abrir cada dropdown (não 1 por linha).
6. Autor/usuário de teste com nome `<img src=x onerror=...>"` não executa nem quebra o markup.

- [ ] **Step 6: Commit dos docs (SPEC/plano) se ainda não versionados**

```bash
git add docs/superpowers/specs/2026-07-23-avatares-selects-mensagem-form.md docs/superpowers/plans/2026-07-23-avatares-selects-mensagem-form.md
git commit -m "docs(avatares-selects): spec + plano da fatia de avatares nos Selects"
```

---

## Self-Review

- **Cobertura da SPEC:** I1–I5 → Task 1; I6 → Task 2; I7/I8(a)/I9/I10 → Task 3; **I8(b)** (save não trava) → guarda existente `MensagemDestinatariosPersistenciaTest:154` (fica verde só com o A3 correto); I11 (não-regressão) → Task 4 Step 2. O1/O2/O4/A1/A3 → embutidos no helper e nas closures (Tasks 1–3). D1 (fallback visual) → Task 1 + conferência Task 4 Step 5. P1 (consolidação) → `selectAutores`/`selectDestinatarios`. P2 (digite p/ buscar) → efeito do Step 4-6 da Task 3, conferido em Task 4 Step 5. R8/R9 → comentário na closure + nomes ASCII nos testes.
- **Placeholders:** nenhum — todo passo traz o código/comando real e o resultado esperado.
- **Consistência de tipos:** `AvatarOpcao::html(?string,string,string): string` usado igual nas Tasks 2 e 3; `selectAutores()`/`selectDestinatarios()` retornam `Select`; `getSearchResults`/`getOptionLabels`/`getOptions` são a API pública do `Select` do Filament 5 (verificada na SPEC §3.3).
- **Fronteira:** nenhuma task toca `SincronizadorDestinatarios`/`saveRelationships`/`RegraPublicacao` (F1). Autores mantêm `->relationship()` (F2). Sem migration/asset/npm (Global Constraints).
