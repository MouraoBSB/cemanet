# Camada 4 · Fatia 3C — "Minhas Direcionadas" (aba read-only no /minha-conta) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Dar ao destinatário um **índice** das suas mensagens **direcionadas publicadas** dentro do `/minha-conta` — uma **aba read-only** (`conta.direcionadas`), condicional (só quem tem ≥1 direcionada publicada), cada card linkando ao single (onde ele já passa o gate da 3B). Aditiva, **sem migration**. É a superfície de navegação que o SPLIT F1 da 3B deixou de fora.

**Architecture:** Um portão `App\Support\Conta\AbaDirecionadas::visivelPara` (molde `AbaAgenda`, WeakMap por request) com critério **por pertencimento + blindagem por nível**: `mensagensDirecionadas()->publicado()->where('nivel', Direcionada)->exists()`. A nav (`x-conta.nav`) mostra o item condicionalmente; o `ContaController@direcionadas` repete o portão (`abort_unless 403`) e serve a **mesma cadeia** ordenada por data à view estática `conta/direcionadas.blade.php` (grade de `x-mensagem.card variante='perfil'` + cabeçalho creme "Área pessoal", **sem PII**). Nada de Livewire, form ou mutação. **Consome** o pivô da 3A (`mensagem_destinatario`), o `scopePublicado` (3B) e o card/badge da 2B/3B — não recria nada.

**Tech Stack:** PHP 8.3 · Laravel 13 · Filament 5 · Livewire 4 · Blade SSR · Tailwind v4 · MySQL. Docker (`docker compose exec -T app php artisan ...`). npm/Vite **no host** (o container não tem Node, [[npm-vite-no-host]]).

**Spec:** [docs/superpowers/specs/2026-07-20-camada-4-fatia-3c-minhas-direcionadas.md](../specs/2026-07-20-camada-4-fatia-3c-minhas-direcionadas.md) (✅ aprovada no passe do consultor; O1–O5 fechados). Todas as referências `§N`/`I#` abaixo são a esse spec.

**PR único (sem split):** diferente da Fase D (que foi partida em D1/D2 pelo risco de CSS/tema), a 3C **não** tem migration, tema, form nem risco de CSS — é read-only e aditiva. Um só PR: `feat(camada-4-fatia-3c): Minhas Direcionadas (aba read-only /minha-conta)`.

**Passe interno do plano (20/jul):** 3 verificadores adversariais (produção · testes · fronteiras/molde) rechecaram este plano contra o código real — as queries centrais (`AbaDirecionadas`, o scope do controller, `->with('autores','media')`, a ordenação) foram **provadas em runtime** por sondas tinker read-only em usuários reais do dev; veredito **sólido, zero bloqueador**. Ajustes menores já dobrados: âncora `assertOk`/`assertSee` antes do `strpos` no teste de ordenação; um assert leve de neutralidade (I6: `mensagens.index` 200 p/ anônimo). Segue para o **passe do consultor** antes da execução.

## Global Constraints

- **pt-BR em tudo**: código (identificadores de domínio), comentários, mensagens de UI, commits.
- **Cabeçalho de autoria** em todo PHP/Blade novo: `// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-20` (Blade: `{{-- … --}}`).
- **0 migrations** nesta fatia — o pivô `mensagem_destinatario` já existe (3A). **Nunca** `migrate:fresh`/`refresh`/`wipe`/`reset` nem seed destrutivo (o dev tem 179 mensagens + 73 vínculos + palestras/posts/mídia importados — [[nunca-migrate-fresh-no-dev]]). Conexão `legado` é **read-only** (SELECT).
- **Read-only**: nenhuma rota de mutação, form ou Livewire de escrita. A rota `conta.direcionadas` é **só GET** (provado no teste I5).
- **Sem PII (F2)**: a aba **nunca** exibe a lista de destinatários — o card só mostra autores espirituais + título + data. `mensagensDirecionadas()` é usado **só** para o próprio `auth()->user()`.
- **Blindagem O5**: a aba **e** a listagem compõem `publicado()->where('nivel', VisibilidadeMensagem::Direcionada->value)` — os **dois** filtros provados por teste (I7), não por premissa.
- **Fronteiras (§11)**: **não** tocar `Mensagens\Lista` / `MensagemController` / a barreira / o single / o resolvedor (3A) / o sitemap / Autores / o pivô (só lê) / `x-mensagem.card`/`selo-nivel` (só usa).
- **Testes**: `docker compose exec -T app php artisan test --filter=<Nome>` por task; **suíte inteira** no fechamento. **Pint antes de qualquer commit**: `docker compose exec -T app vendor/bin/pint <arquivos>`.
- **Cada commit** termina com o trailer `Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>`.
- **Ordem (§9.0)**: `AbaDirecionadas` (Task 1) → rota+controller+view (Task 2) → nav (Task 3) → suíte+browser+PR (Task 4). O **teste-contrato da blindagem O5 (I7)** entra em cada superfície (aba na Task 1, lista na Task 2).
- **WeakMap estático**: `AbaDirecionadas::$cache` é `static` mas indexado pelo **objeto** `User` — cada teste usa instância nova, sem contaminação entre testes (molde `AbaAgenda`); **não** resetar entre testes.

---

## Task 1: `App\Support\Conta\AbaDirecionadas` — o portão (pertencimento + blindagem O5)

**Files:**
- Create: `app/Support/Conta/AbaDirecionadas.php`
- Test: `tests/Feature/Conta/AbaDirecionadasTest.php`

**Interfaces:**
- Produces: `App\Support\Conta\AbaDirecionadas::visivelPara(User $user): bool` — `true` ⇔ o usuário é destinatário de **≥1 mensagem direcionada publicada** (`mensagensDirecionadas()->publicado()->where('nivel', Direcionada)->exists()`). Memoizado por request via `WeakMap`. **Não** consulta permission (critério por pertencimento).
- Consumes: `User::mensagensDirecionadas()` (3A), `Mensagem::scopePublicado` (3B), `VisibilidadeMensagem::Direcionada` (3A).

- [ ] **Step 1: Escrever os testes que falham**

Create `tests/Feature/Conta/AbaDirecionadasTest.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-20

namespace Tests\Feature\Conta;

use App\Enums\VisibilidadeMensagem;
use App\Models\Mensagem;
use App\Models\User;
use App\Support\Conta\AbaDirecionadas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * O setUp NÃO semeia nada (nem EstruturaCemaSeeder, nem permissions): a 3C decide o acesso
 * por PERTENCIMENTO ao pivô, não por capacidade. Que estes testes passem sem nenhum seed de
 * permissão É a prova de que AbaDirecionadas não consulta permission (contraste com AbaAgenda).
 */
class AbaDirecionadasTest extends TestCase
{
    use RefreshDatabase;

    private function direcionadaPublicadaPara(User $user): Mensagem
    {
        $m = Mensagem::factory()->comNivel(VisibilidadeMensagem::Direcionada)->create(); // nasce STATUS_PUBLICADO
        $m->destinatarios()->attach($user->id);

        return $m;
    }

    public function test_aba_visivel_para_destinatario_de_direcionada_publicada(): void
    {
        $user = User::factory()->create();
        $this->direcionadaPublicadaPara($user);

        $this->assertTrue(AbaDirecionadas::visivelPara($user));
    }

    public function test_aba_oculta_sem_direcionada(): void
    {
        $this->assertFalse(AbaDirecionadas::visivelPara(User::factory()->create()));
    }

    /** publicado(): uma direcionada PENDENTE (curadoria F4) NÃO acende a aba. */
    public function test_pendente_nao_acende_a_aba(): void
    {
        $user = User::factory()->create();
        $m = Mensagem::factory()->comNivel(VisibilidadeMensagem::Direcionada)->pendente()->create();
        $m->destinatarios()->attach($user->id);

        $this->assertFalse(AbaDirecionadas::visivelPara($user));
    }

    /** Blindagem O5 (I7): vínculo a uma PUBLICADA de OUTRO nível NÃO conta como direcionada. */
    public function test_outro_nivel_publicado_nao_acende_a_aba(): void
    {
        $user = User::factory()->create();
        $m = Mensagem::factory()->comNivel('trabalhadores')->create(); // publicada, nivel != direcionada
        $m->destinatarios()->attach($user->id);

        $this->assertFalse(AbaDirecionadas::visivelPara($user), 'vínculo a mensagem de outro nível não conta (O5)');
    }

    /** O pivô é por user_id: a direcionada de OUTRO usuário não acende a minha aba. */
    public function test_direcionada_de_outro_usuario_nao_conta(): void
    {
        $this->direcionadaPublicadaPara(User::factory()->create());

        $this->assertFalse(AbaDirecionadas::visivelPara(User::factory()->create()));
    }
}
```

- [ ] **Step 2: Rodar e ver falhar (RED — não vacuous)**

Run: `docker compose exec -T app php artisan test --filter=AbaDirecionadasTest`
Expected: FAIL — `Class "App\Support\Conta\AbaDirecionadas" not found`.

> **Prova de não-vacuidade (§13/O5):** além do RED por classe inexistente, os testes `test_pendente_...` e `test_outro_nivel_...` **reprovam** contra um portão ingênuo `mensagensDirecionadas()->exists()` (sem `publicado()`/`where('nivel')`). Ou seja: cada filtro é exercido por um teste que fica vermelho se o guard sumir — não é premissa.

- [ ] **Step 3: Criar `AbaDirecionadas`**

Create `app/Support/Conta/AbaDirecionadas.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-20

namespace App\Support\Conta;

use App\Enums\VisibilidadeMensagem;
use App\Models\User;
use WeakMap;

/**
 * Fonte única do acesso à aba/rota "Minhas Direcionadas" no /minha-conta.
 * Aba visível ⇔ o usuário é destinatário de ≥1 mensagem DIRECIONADA PUBLICADA (pertencimento, não
 * capacidade): pendente (curadoria F4) OU vínculo a mensagem de outro nível NÃO conta (blindagem O5).
 * Memoizada por request via WeakMap (a nav renderiza em TODA página /minha-conta; auth()->user()
 * devolve a mesma instância no request; WeakMap não sofre reuso de spl_object_id).
 *
 * NÃO consulta permission (checkPermissionTo/AcessoPorTipo) — a Direcionada é por PERTENCIMENTO;
 * por isso o comentário hasPermissionTo-vs-checkPermissionTo do AbaAgenda não se aplica aqui.
 */
class AbaDirecionadas
{
    private static ?WeakMap $cache = null;

    public static function visivelPara(User $user): bool
    {
        self::$cache ??= new WeakMap;

        return self::$cache[$user] ??= $user->mensagensDirecionadas()
            ->publicado()
            ->where('nivel', VisibilidadeMensagem::Direcionada->value)
            ->exists();
    }
}
```

- [ ] **Step 4: Rodar e ver passar (GREEN)**

Run: `docker compose exec -T app php artisan test --filter=AbaDirecionadasTest`
Expected: PASS (5 testes).

- [ ] **Step 5: Pint + commit**

Run: `docker compose exec -T app vendor/bin/pint app/Support/Conta/AbaDirecionadas.php tests/Feature/Conta/AbaDirecionadasTest.php`

```bash
git add app/Support/Conta/AbaDirecionadas.php tests/Feature/Conta/AbaDirecionadasTest.php
git commit -m "feat(camada-4-fatia-3c): AbaDirecionadas (pertencimento + blindagem O5 por nivel)"
```

---

## Task 2: Rota `conta.direcionadas` + `ContaController@direcionadas` + view

**Files:**
- Modify: `routes/web.php` (nova rota no grupo `conta.`, após `agenda` — `:45`)
- Modify: `app/Http/Controllers/ContaController.php` (método `direcionadas` + 2 imports)
- Create: `resources/views/conta/direcionadas.blade.php`
- Test: `tests/Feature/Conta/MinhasDirecionadasTest.php`

**Interfaces:**
- Consumes: `AbaDirecionadas::visivelPara` (Task 1), `User::mensagensDirecionadas()`, `Mensagem::scopePublicado`, `VisibilidadeMensagem::Direcionada`, `x-layout.conta`, `x-mensagem.card variante='perfil'`.
- Produces: rota nomeada `conta.direcionadas` (`GET /minha-conta/direcionadas`); `ContaController::direcionadas(): View` (403 se não-visível; senão a lista ordenada); a view `conta.direcionadas` (noindex + cabeçalho creme + grade de cards).

- [ ] **Step 1: Escrever os testes que falham**

Create `tests/Feature/Conta/MinhasDirecionadasTest.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-20

namespace Tests\Feature\Conta;

use App\Enums\VisibilidadeMensagem;
use App\Models\Mensagem;
use App\Models\User;
use Database\Seeders\EstruturaCemaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MinhasDirecionadasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstruturaCemaSeeder::class); // a página renderiza nav/saudação (papéis/setores)
    }

    private function direcionadaPara(User $user, array $attrs = []): Mensagem
    {
        $m = Mensagem::factory()->comNivel(VisibilidadeMensagem::Direcionada)->create($attrs);
        $m->destinatarios()->attach($user->id);

        return $m;
    }

    public function test_destinatario_ve_a_lista_com_noindex(): void
    {
        $user = User::factory()->create();
        $this->direcionadaPara($user, ['titulo' => 'Recado do plano espiritual']);

        $this->actingAs($user)->get(route('conta.direcionadas'))
            ->assertOk()
            ->assertSee('Recado do plano espiritual')
            ->assertSee('noindex, nofollow', false); // I4
    }

    /** I2 — filtro por user_id: a direcionada de OUTRO usuário nunca aparece. */
    public function test_nao_mostra_direcionada_de_outro_usuario(): void
    {
        $user = User::factory()->create();
        $this->direcionadaPara($user, ['titulo' => 'Minha direcionada']);
        $this->direcionadaPara(User::factory()->create(), ['titulo' => 'Direcionada de outro']);

        $this->actingAs($user)->get(route('conta.direcionadas'))
            ->assertSee('Minha direcionada')
            ->assertDontSee('Direcionada de outro');
    }

    /** publicado(): uma pendente dele não aparece na lista. */
    public function test_nao_mostra_pendente(): void
    {
        $user = User::factory()->create();
        $this->direcionadaPara($user, ['titulo' => 'Publicada visivel']);
        $pend = Mensagem::factory()->comNivel(VisibilidadeMensagem::Direcionada)->pendente()->create(['titulo' => 'Pendente oculta']);
        $pend->destinatarios()->attach($user->id);

        $this->actingAs($user)->get(route('conta.direcionadas'))
            ->assertSee('Publicada visivel')
            ->assertDontSee('Pendente oculta');
    }

    /** Blindagem O5 (I7): uma PUBLICADA de outro nível vinculada a ele não aparece. */
    public function test_nao_mostra_publicada_de_outro_nivel(): void
    {
        $user = User::factory()->create();
        $this->direcionadaPara($user, ['titulo' => 'Direcionada real']);
        $outroNivel = Mensagem::factory()->comNivel('trabalhadores')->create(['titulo' => 'Nivel trabalhadores']);
        $outroNivel->destinatarios()->attach($user->id); // vínculo anômalo no pivô

        $this->actingAs($user)->get(route('conta.direcionadas'))
            ->assertSee('Direcionada real')
            ->assertDontSee('Nivel trabalhadores');
    }

    /** I3 — nenhuma PII de outro destinatário no HTML (o card só mostra autores). */
    public function test_nao_vaza_destinatarios_pii(): void
    {
        $user = User::factory()->create(['name' => 'Titular da Conta']);
        $outroDest = User::factory()->create(['name' => 'Outro Destinatario Sigiloso']);
        $m = $this->direcionadaPara($user, ['titulo' => 'Compartilhada']);
        $m->destinatarios()->attach($outroDest->id); // dois destinatários na mesma mensagem

        $this->actingAs($user)->get(route('conta.direcionadas'))
            ->assertOk()
            ->assertSee('Compartilhada')
            ->assertDontSee('Outro Destinatario Sigiloso');
    }

    /** I1 — logado sem direcionada → 403. */
    public function test_logado_sem_direcionada_recebe_403(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('conta.direcionadas'))
            ->assertForbidden();
    }

    /** I1 + publicado(): só com pendente → 403. */
    public function test_so_com_pendente_recebe_403(): void
    {
        $user = User::factory()->create();
        $pend = Mensagem::factory()->comNivel(VisibilidadeMensagem::Direcionada)->pendente()->create();
        $pend->destinatarios()->attach($user->id);

        $this->actingAs($user)->get(route('conta.direcionadas'))->assertForbidden();
    }

    /** I4 — anônimo → redirect ao login (middleware auth). */
    public function test_anonimo_redireciona_ao_login(): void
    {
        $this->get(route('conta.direcionadas'))->assertRedirect(route('login'));
    }

    public function test_ordena_por_data_recebimento_desc(): void
    {
        $user = User::factory()->create();
        $this->direcionadaPara($user, ['titulo' => 'MsgAntiga', 'data_recebimento' => '2026-01-01']);
        $this->direcionadaPara($user, ['titulo' => 'MsgRecente', 'data_recebimento' => '2026-06-01']);

        $resp = $this->actingAs($user)->get(route('conta.direcionadas'))
            ->assertOk()->assertSee('MsgAntiga')->assertSee('MsgRecente'); // ancora antes do strpos (evita false→0)
        $html = $resp->getContent();
        $this->assertLessThan(strpos($html, 'MsgAntiga'), strpos($html, 'MsgRecente'), 'a mais recente vem primeiro');
    }

    /** I5 — read-only: a rota é só GET (nenhum verbo de mutação). */
    public function test_rota_e_somente_leitura(): void
    {
        $rota = collect(app('router')->getRoutes())->first(fn ($r) => $r->getName() === 'conta.direcionadas');

        $this->assertNotNull($rota);
        $this->assertEqualsCanonicalizing(['GET', 'HEAD'], $rota->methods());
    }

    /** I6 (reforço leve): a 3C é aditiva — a lista pública segue 200 para anônimo (comportamento 2B intacto). */
    public function test_lista_publica_permanece_intacta(): void
    {
        $this->get(route('mensagens.index'))->assertOk();
    }
}
```

- [ ] **Step 2: Rodar e ver falhar (RED)**

Run: `docker compose exec -T app php artisan test --filter=MinhasDirecionadasTest`
Expected: FAIL — rota `conta.direcionadas` não definida (`Route [conta.direcionadas] not defined`).

- [ ] **Step 3: Adicionar a rota**

Modify `routes/web.php` — dentro do grupo `conta.` (`:42-46`), após a rota `agenda` (`:45`):

```php
    Route::get('/direcionadas', [ContaController::class, 'direcionadas'])->name('direcionadas');
```

- [ ] **Step 4: Adicionar o método ao controller**

Modify `app/Http/Controllers/ContaController.php` — adicionar os imports no topo (junto aos demais `use`):

```php
use App\Enums\VisibilidadeMensagem;
use App\Support\Conta\AbaDirecionadas;
```

e o método (após `agenda()`):

```php
    public function direcionadas(): View
    {
        $user = auth()->user();
        abort_unless(AbaDirecionadas::visivelPara($user), 403);

        $direcionadas = $user->mensagensDirecionadas()
            ->publicado()
            ->where('nivel', VisibilidadeMensagem::Direcionada->value)   // blindagem O5 (I7): só direcionadas
            ->with('autores', 'media')          // eager-load: autor (card) + media (miniatura pictografia) — sem N+1
            ->orderByDesc('data_recebimento')
            ->get();

        return view('conta.direcionadas', compact('direcionadas'));
    }
```

- [ ] **Step 5: Criar a view**

Create `resources/views/conta/direcionadas.blade.php`:

```blade
{{-- Thiago Mourão — https://github.com/MouraoBSB — 2026-07-20 --}}
<x-layout.conta titulo="Minhas Mensagens Direcionadas" ativo="direcionadas">
    <x-slot:head><meta name="robots" content="noindex, nofollow"></x-slot:head>

    <div class="space-y-6">
        {{-- Cabeçalho "Área pessoal" — card creme, SEM lista de destinatários (F2). --}}
        <section class="flex items-start gap-3.5 rounded-lg border border-[#ECE6D6] bg-[#FAF8F2] p-6 shadow-card">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#c19532" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mt-0.5 shrink-0" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            <div>
                <h2 class="font-display text-lg font-semibold text-primary">Minhas mensagens direcionadas</h2>
                <p class="mt-1 text-sm text-text-secondary">Mensagens endereçadas pessoalmente a você nas reuniões mediúnicas da Casa. Somente você as vê por aqui.</p>
            </div>
        </section>

        {{-- Grade das direcionadas (só as minhas, publicadas — controller). Card 'perfil' (badge Direcionada @auth). --}}
        @if ($direcionadas->isNotEmpty())
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 desktop-sm:grid-cols-3">
                @foreach ($direcionadas as $mensagem)
                    <x-mensagem.card :mensagem="$mensagem" variante="perfil" />
                @endforeach
            </div>
        @else
            {{-- Estado vazio: improvável (a aba só aparece com ≥1), mas defende a corrida despublicar↔clicar. --}}
            <p class="rounded-lg border border-dashed border-border bg-surface px-4 py-10 text-center text-sm text-text-muted">
                Nenhuma mensagem direcionada a você no momento.
            </p>
        @endif
    </div>
</x-layout.conta>
```

- [ ] **Step 6: Rodar e ver passar (GREEN)**

Run: `docker compose exec -T app php artisan test --filter=MinhasDirecionadasTest`
Expected: PASS (11 testes).

- [ ] **Step 7: Pint + commit**

Run: `docker compose exec -T app vendor/bin/pint routes/web.php app/Http/Controllers/ContaController.php tests/Feature/Conta/MinhasDirecionadasTest.php`

```bash
git add routes/web.php app/Http/Controllers/ContaController.php resources/views/conta/direcionadas.blade.php tests/Feature/Conta/MinhasDirecionadasTest.php
git commit -m "feat(camada-4-fatia-3c): rota+controller+view read-only (blindagem O5, noindex, sem PII)"
```

---

## Task 3: Item condicional na nav do /minha-conta

**Files:**
- Modify: `resources/views/components/conta/nav.blade.php` (item condicional, após o bloco Agenda `:8-10`)
- Test: `tests/Feature/Conta/NavDirecionadasTest.php`

**Interfaces:**
- Consumes: `AbaDirecionadas::visivelPara` (Task 1), a rota `conta.direcionadas` (Task 2).
- Produces: o item "Minhas Direcionadas" na `x-conta.nav`, presente **só** para quem tem direcionada publicada.

- [ ] **Step 1: Escrever os testes que falham**

Create `tests/Feature/Conta/NavDirecionadasTest.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-20

namespace Tests\Feature\Conta;

use App\Enums\VisibilidadeMensagem;
use App\Models\Mensagem;
use App\Models\User;
use Database\Seeders\EstruturaCemaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavDirecionadasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstruturaCemaSeeder::class);
    }

    public function test_nav_mostra_aba_para_destinatario(): void
    {
        $user = User::factory()->create();
        Mensagem::factory()->comNivel(VisibilidadeMensagem::Direcionada)->create()
            ->destinatarios()->attach($user->id);

        $this->actingAs($user)->get(route('conta.perfil'))
            ->assertSee('Minhas Direcionadas')
            ->assertSee(route('conta.direcionadas'), false);
    }

    public function test_nav_oculta_para_quem_nao_tem_direcionada(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('conta.perfil'))
            ->assertDontSee(route('conta.direcionadas'), false);
    }
}
```

- [ ] **Step 2: Rodar e ver falhar (RED)**

Run: `docker compose exec -T app php artisan test --filter=NavDirecionadasTest`
Expected: FAIL — `test_nav_mostra_aba_para_destinatario` não vê "Minhas Direcionadas" (item ainda não existe na nav).

- [ ] **Step 3: Adicionar o item condicional à nav**

Modify `resources/views/components/conta/nav.blade.php` — dentro do bloco `@php`, **após** o `if` da Agenda (`:8-10`):

```php
    if (\App\Support\Conta\AbaDirecionadas::visivelPara(auth()->user())) {
        $itens[] = ['chave' => 'direcionadas', 'rotulo' => 'Minhas Direcionadas', 'rota' => 'conta.direcionadas'];
    }
```

> O loop existente (`:14-21`) já cuida de `aria-current`/cor-ativa por `chave` — nenhuma outra mudança.

- [ ] **Step 4: Rodar e ver passar (GREEN)**

Run: `docker compose exec -T app php artisan test --filter=NavDirecionadasTest`
Expected: PASS (2 testes).

- [ ] **Step 5: Pint + commit**

Run: `docker compose exec -T app vendor/bin/pint tests/Feature/Conta/NavDirecionadasTest.php` (o `.blade.php` não é formatado pelo Pint)

```bash
git add resources/views/components/conta/nav.blade.php tests/Feature/Conta/NavDirecionadasTest.php
git commit -m "feat(camada-4-fatia-3c): item condicional 'Minhas Direcionadas' na nav do /minha-conta"
```

---

## Task 4: Fechamento — suíte inteira + Pint + prova no browser + PR

**Files:**
- Verify: suíte completa (regressão I6/I-reg) + navegador (o dev tem 17 destinatários reais).

**Interfaces:** nenhuma nova — prova o que as Tasks 1–3 produziram e a **neutralidade** (nenhuma superfície existente muda).

- [ ] **Step 1: Suíte inteira + Pint**

Run: `docker compose exec -T app vendor/bin/pint --test`
Run: `docker compose exec -T app php artisan test`
Expected: verde. Baseline **~1063** → **~1063 + 18 novos** (5 `AbaDirecionadasTest` + 11 `MinhasDirecionadasTest` + 2 `NavDirecionadasTest`). Nenhum teste 2A/2B/3A/3B muda de cor (a 3C é aditiva — I6). Ciência [[flaky-importadorblog-gd-cap-imagem]]: 2 testes de cap de imagem do blog podem falhar sob carga — se passam isolados, **não** é regressão desta fatia.

- [ ] **Step 2: Build + prova no browser (o que os testes não cobrem)**

Run (host): `npm run build` (há Blade novo). Depois `docker compose restart app worker` ([[dev-opcache-restart-app-worker]]).

Com o container servindo, autenticar como um **destinatário real** (o dev tem 17 — se preciso, `docker compose exec -T app php artisan tinker` para achar um `User` com `mensagensDirecionadas()->publicado()->where('nivel','direcionada')->exists()`; **sem** comando destrutivo) e conferir:
- ✅ A aba **"Minhas Direcionadas"** aparece na nav do `/minha-conta`.
- ✅ A rota `/minha-conta/direcionadas` lista as direcionadas dele (cards `perfil` com badge **Direcionada**), ordenadas da mais recente; o cabeçalho creme "Área pessoal" aparece; **nenhum** nome de destinatário no HTML (ver fonte).
- ✅ Clicar um card → `mensagens.show` abre a mensagem completa + a nota **"Direcionada a você"** (herança da 3B).
- ✅ `<meta name="robots" content="noindex, nofollow">` no `<head>` da aba (ver fonte).
- ✅ Logar como um usuário **sem** direcionada → a aba **não** aparece; acessar `/minha-conta/direcionadas` direto → **403**.
- ✅ Anônimo em `/minha-conta/direcionadas` → redireciona ao login.
- ✅ **Neutralidade:** `/mensagens-mediunicas` (lista pública) e o single seguem idênticos (a 3C não os toca).

- [ ] **Step 3: Abrir o PR e PARAR para o passe do plano/PR**

> **Fim da execução.** Este plano vai ao **passe do consultor** ANTES da execução (o dono cravou: escrever o plano → passe do plano → execução → PR → passe do PR). **NÃO implementar** sem o go. Quando executado e verde, abrir o PR único:

```bash
git push -u origin camada-4-fatia-3c-minhas-direcionadas
gh pr create --base main --title "feat(camada-4-fatia-3c): Minhas Direcionadas (aba read-only /minha-conta)" --body "<resumo: aba condicional read-only; blindagem O5; noindex; sem PII (F2); I1–I7 verdes; sem migration>"
```

Mesclar **só** com o CI verde no **último** commit ([[merge-so-com-ci-verde-no-commit-final]]).

**Cutover de PROD (do dono):** deploy padrão de front (§8 do spec) — `git pull` → `npm run build` (host) → `php artisan optimize:clear` + `restart app worker` (o `route:clear` publica a rota). **Sem migration.**

---

## Cobertura dos invariantes (rastreabilidade)

| Invariante | Onde é provado |
|---|---|
| **I1** aba/rota condicional | Task 1 (`AbaDirecionadasTest`: visível/oculta) + Task 2 (`MinhasDirecionadasTest`: 403 sem direcionada / só-pendente) + Task 3 (nav) |
| **I2** só as dele, publicadas | Task 2 (`nao_mostra_direcionada_de_outro_usuario`, `nao_mostra_pendente`) |
| **I3** sem PII | Task 2 (`nao_vaza_destinatarios_pii`) |
| **I4** noindex + @auth | Task 2 (`destinatario_ve_a_lista_com_noindex`, `anonimo_redireciona_ao_login`) |
| **I5** read-only | Task 2 (`rota_e_somente_leitura`) |
| **I6** lista pública intacta | Task 2 (`lista_publica_permanece_intacta`) + Task 4 (suíte inteira + browser) |
| **I7** blindagem O5 por nível | Task 1 (`outro_nivel_publicado_nao_acende_a_aba`) + Task 2 (`nao_mostra_publicada_de_outro_nivel`) |
| **I-reg** neutralidade/suíte/Pint | Task 4 |
