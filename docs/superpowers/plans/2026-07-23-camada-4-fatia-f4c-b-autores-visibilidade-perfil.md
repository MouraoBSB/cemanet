# Camada 4 · Fatia F4c-B — Plano de implementação

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Levar a visibilidade rica (`publicado()->visiveisPara($usuario)`) para a lista e o perfil de Autores Espirituais — o último lugar do módulo Mensagens ainda no filtro público fixo — e completar o perfil com o bloco "Sobre {nome}" e uma imagem de fallback para autor sem foto.

**Architecture:** B1 (Tasks 1–3) troca o escopo no `AutorEspiritualController` (fonte única), carimba `Cache-Control: private, no-store` no logado e torna rótulos e rodapé *viewer-aware*; o anônimo continua byte-idêntico à 2B porque `visiveisPara(null) ≡ publica()`. B2 (Tasks 4–5) é apresentação no perfil, independente da lógica de B1. Sem migration — a regra de quem-vê-o-quê já existe (3A); esta fatia só a **consome** numa superfície nova.

**Tech Stack:** PHP 8.3 · Laravel 13.17 · Filament 5 · Livewire 3 · Blade · MySQL 8 (dev) / SQLite `:memory:` (suíte) · PHPUnit · Pint. Sem Vite nesta fatia (o fallback é asset estático servido por `asset()`).

**SPEC:** [2026-07-23-camada-4-fatia-f4c-b-autores-visibilidade-perfil.md](../specs/2026-07-23-camada-4-fatia-f4c-b-autores-visibilidade-perfil.md) — ratificada + passe incorporado (§12).
**Branch:** `camada-4-fatia-f4c-b-autores`, a partir de `8b2c03f`. **Baseline: reconfirmar no arranque** (main pós-F4c-D ≈ **1286 passed**).

## Global Constraints

- **Tudo em português brasileiro** — comentários, mensagens de interface, commits. Sintaxe e APIs de terceiros no original.
- 🚫 **PROIBIDO** `migrate:fresh`, `migrate:refresh`, `migrate:reset`, `db:wipe` e seed/factory destrutivo. Esta fatia **não tem migration** e **não toca o banco de dev**.
- **Comandos rodam no container:** `docker compose exec -T app php artisan …` e `docker compose exec -T app ./vendor/bin/pint`. **Nunca** `sail`. **Sem `npm run build`** — o fallback é SVG estático em `public/images/` (servido por `asset()`, sem manifest); os rótulos/blocos novos reusam utilities e classes já no bundle.
- **Pint antes de qualquer push** — o CI roda `pint --test` **antes** dos testes e aborta o job.
- **Guardrails de PII (SPEC O1/O2), inegociáveis:** o rodapé condicional **nunca** conta direcionadas de terceiros nem mostra número; o **sitemap** ([SitemapController:41-44](app/Http/Controllers/SitemapController.php#L41-L44)) e a **meta description** do perfil continuam no critério **público** — **não trocar** por `visiveisPara($user)`.
- **O fallback é SÓ das 2 views de autor.** **NUNCA** tocar o trait [TemIniciais](app/Models/Concerns/TemIniciais.php) — ele é compartilhado com `Palestrante` e `User` (SPEC R7/I13).
- **Anônimo ≡ 2B:** para `$usuario === null`, `publicado()->visiveisPara(null)` devolve o mesmo conjunto de `publica()` — o anônimo **não pode mudar** (SPEC I1).
- **Cache-Control:** asserir por **substring** (`assertStringContainsString('no-store', …)`), nunca por igualdade — o Symfony normaliza/reordena o header. Molde: [MensagemIndexContadorTest:29](tests/Feature/Front/MensagemIndexContadorTest.php#L29).
- **`nivel=NULL` (2 no dev):** para não-admin, `visiveisPara` **exclui** (fail-closed); o bypass de admin **inclui**. Qualquer selo novo tem de repetir o null-guard `@if($visibilidade)` (SPEC R2) — mas esta fatia **não** cria selo novo.
- **Fonte do SVG de fallback:** `design_handoff_autor_espiritual_perfil/entrega_autor_fallback/autor-fallback.svg` (pasta **não versionada**, presente no diretório de trabalho).
- **Cabeçalho de autoria** em arquivo novo relevante: `// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-23`.
- **Commits atômicos**, um por task, mensagem em pt-BR.

---

## Estrutura de arquivos

**Modificados:**

| Arquivo | Responsabilidade nesta fatia |
|---|---|
| [app/Http/Controllers/AutorEspiritualController.php](app/Http/Controllers/AutorEspiritualController.php) | `@index`/`@show` viewer-aware (`publicado()->visiveisPara`), injetar `Request`, retornar `Response` + `Cache-Control` no logado, passar `'logado'`, renomear alias/var, `$temRestritasOcultas` (Task 3), reescrever comentários |
| [resources/views/autores/index.blade.php](resources/views/autores/index.blade.php) | renomear var (`:71`) + rótulo mini-stat condicional (`:72`) |
| [resources/views/autores/show.blade.php](resources/views/autores/show.blade.php) | rótulos condicionais (tile `:17`, contagem `:126`, vazio `:165`), fallback no hero (`:64-67`), rodapé condicional (`:169-173`), bloco "Sobre" (novo, após `:116`) |
| [resources/views/components/autor/card.blade.php](resources/views/components/autor/card.blade.php) | renomear consumo interno (`:10`) + comentário-contrato (`:3-8`), fallback (`:23-26`) + tirar `cema-grad-*` do wrapper (`:20`) |
| [app/Support/AutoresEspirituais/ResumoAutor.php](app/Support/AutoresEspirituais/ResumoAutor.php) | docblock "públicas" → "visíveis ao usuário" (`:12-16,78`) |

**Criados:**

- `public/images/autor-fallback.svg` (copiado do handoff).
- `tests/Feature/Front/AutorVisibilidadeTest.php` (B1: grade/contagem viewer-aware, Cache-Control, rótulos).
- `tests/Feature/Front/AutorRodapeCondicionalTest.php` (O1 + anti-PII + anônimo).
- `tests/Feature/Front/AutorFallbackFotoTest.php` (B2 fallback).
- `tests/Feature/Front/AutorPerfilBlocoSobreTest.php` (B2 "Sobre").

**Testes existentes tocados:** [tests/Feature/Front/AutorShowTest.php:53-60](tests/Feature/Front/AutorShowTest.php#L53-L60) (Task 3, split de `test_sem_curtir_e_com_link_login`).

---

## Task 1: Controller viewer-aware + Cache-Control + renome interno (B1, o coração)

O `AutorEspiritualController` passa a filtrar por `publicado()->visiveisPara($usuario)` nos **quatro** usos do `@index` e na fonte única do `@show`, injeta `Request`, retorna `Response` com `Cache-Control: private, no-store` quando logado, e renomeia o contador interno (`mensagens_publicas_count` → `mensagens_visiveis_count`; `$totalMensagensPublicas` → `$totalMensagensVisiveis`). Os **rótulos visíveis** ("públicas") ainda **não** mudam — é a Task 2. Os selos/grade do perfil já são `@auth`+null-guard (3B), então não quebram.

**Files:**
- Modify: `app/Http/Controllers/AutorEspiritualController.php` (reescrita dos dois métodos)
- Modify: `resources/views/autores/index.blade.php:71` (nome da var)
- Modify: `resources/views/components/autor/card.blade.php:3-8,10` (comentário-contrato + nome interno)
- Modify: `app/Support/AutoresEspirituais/ResumoAutor.php:12-16,78` (docblock)
- Test: `tests/Feature/Front/AutorVisibilidadeTest.php` (novo)

**Interfaces:**
- Consumes: `Mensagem::scopePublicado()` + `scopeVisiveisPara(?User)` (já existem, 3A).
- Produces: as views recebem `'logado' => bool` e `'totalMensagensVisiveis' => int`; o card recebe `mensagens_visiveis_count` (withCount) e a relação `mensagens` já filtrada por `visiveisPara`.

- [ ] **Step 1: Escrever o teste (grade + contagem + Cache-Control)**

⚠️ **Não asserir rótulos aqui** ("públicas"/"disponíveis a você" são a Task 2). Este teste prova **conjunto**, **número** e **header**.

Criar `tests/Feature/Front/AutorVisibilidadeTest.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-23

namespace Tests\Feature\Front;

use App\Models\AutorEspiritual;
use App\Models\Mensagem;
use App\Models\User;
use Database\Seeders\EstruturaCemaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AutorVisibilidadeTest extends TestCase
{
    use RefreshDatabase;

    private bool $estruturaSemeada = false;

    /** Semeia a estrutura UMA vez por teste; findOrCreate cobre 'administrador' (fora do EstruturaCemaSeeder). */
    private function comPapel(string $papel): User
    {
        if (! $this->estruturaSemeada) {
            $this->seed(EstruturaCemaSeeder::class);
            $this->estruturaSemeada = true;
        }
        Role::findOrCreate($papel, 'web');
        $u = User::factory()->create();
        $u->assignRole($papel);

        return $u->fresh();
    }

    /** @return array{pub: AutorEspiritual, restrito: AutorEspiritual} */
    private function doisAutores(): array
    {
        $pub = AutorEspiritual::factory()->create(['nome' => 'Autor Público', 'slug' => 'autor-pub', 'ativo' => true]);
        $restrito = AutorEspiritual::factory()->create(['nome' => 'Autor Só Trabalhadores', 'slug' => 'autor-trab', 'ativo' => true]);
        Mensagem::factory()->publica()->create()->autores()->attach($pub->id);
        Mensagem::factory()->create(['status' => 'publicado', 'nivel' => 'trabalhadores'])->autores()->attach($restrito->id);

        return ['pub' => $pub, 'restrito' => $restrito];
    }

    /** I1: o anônimo vê exatamente a grade de hoje (só quem tem pública). */
    public function test_i1_anonimo_ve_so_autor_com_publica(): void
    {
        $this->doisAutores();

        $this->get(route('autores.index'))->assertOk()
            ->assertSee('Autor Público')
            ->assertDontSee('Autor Só Trabalhadores');   // sem pública, some para o anônimo
    }

    /** I3: o logado vê na grade o autor que só tem restrita do nível dele. */
    public function test_i3_logado_ve_autor_so_restrito_na_grade(): void
    {
        $this->doisAutores();
        $trab = $this->comPapel('trabalhador');

        $this->actingAs($trab)->get(route('autores.index'))->assertOk()
            ->assertSee('Autor Público')
            ->assertSee('Autor Só Trabalhadores');   // trabalhador enxerga o nível 'trabalhadores'
    }

    /** I4: a contagem do card varia por usuário — mesmo escopo que a grade. */
    public function test_i4_contagem_do_card_e_viewer_aware(): void
    {
        $autor = AutorEspiritual::factory()->create(['nome' => 'Bezerra', 'slug' => 'bezerra', 'ativo' => true]);
        Mensagem::factory()->publica()->create()->autores()->attach($autor->id);
        Mensagem::factory()->create(['status' => 'publicado', 'nivel' => 'trabalhadores'])->autores()->attach($autor->id);

        $this->get(route('autores.index'))->assertOk()->assertSee('1 mensagem');   // anônimo: só a pública

        $trab = $this->comPapel('trabalhador');
        $this->actingAs($trab)->get(route('autores.index'))->assertOk()->assertSee('2 mensagens'); // logado: pública + trabalhadores
    }

    /**
     * I5: a resposta logada não é cacheável por proxy; a anônima é. Na lista e no perfil.
     * ⚠️ Os GET anônimos vêm ANTES do actingAs — o actingAs PERSISTE pelo resto do teste
     * (molde de MensagemIndexContadorTest:17-30). Intercalar daria falso-vermelho: a 2ª volta
     * "anônima" já viria logada e o assertStringNotContainsString falharia com o código certo.
     */
    public function test_i5_cache_control_privado_no_logado(): void
    {
        $autor = AutorEspiritual::factory()->create(['slug' => 'cache-autor', 'ativo' => true]);
        Mensagem::factory()->publica()->create()->autores()->attach($autor->id);

        foreach ([route('autores.index'), route('autores.show', 'cache-autor')] as $url) {
            $anon = $this->get($url);
            $this->assertStringNotContainsString('no-store', (string) $anon->headers->get('Cache-Control'));
        }

        $this->actingAs($this->comPapel('trabalhador'));

        foreach ([route('autores.index'), route('autores.show', 'cache-autor')] as $url) {
            $logado = $this->get($url);
            $this->assertStringContainsString('no-store', (string) $logado->headers->get('Cache-Control'));
        }
    }
}
```

- [ ] **Step 2: Rodar e verificar que falha**

```
docker compose exec -T app php artisan test --filter=AutorVisibilidadeTest
```

Esperado: **FAIL**. `test_i3_…` reprova (a grade ainda usa `publica()`, o autor só-restrito não aparece para o trabalhador); `test_i4_…` reprova (o trabalhador vê "1 mensagem"); `test_i5_…` reprova (o controller retorna `View` sem header). `test_i1_…` **passa** desde já (é a regressão do anônimo). Se `test_i1` falhar, o setup está errado.

- [ ] **Step 3: Reescrever o controller**

Substituir o corpo inteiro de `app/Http/Controllers/AutorEspiritualController.php` por:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-19

namespace App\Http\Controllers;

use App\Models\AutorEspiritual;
use App\Models\Mensagem;
use App\Support\AutoresEspirituais\ResumoAutor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AutorEspiritualController extends Controller
{
    public function index(Request $request): Response
    {
        $usuario = $request->user();

        // O5a: autor ativo SEM nenhuma mensagem VISÍVEL a este usuário some da grade (o perfil
        // dele segue 200 por URL direta). Eager-load de 'mensagens' (mesmo escopo viewer-aware)
        // evita N+1 nos pontinhos de formato do card.
        $autores = AutorEspiritual::query()
            ->ativo()
            ->whereHas('mensagens', fn (Builder $q) => $q->publicado()->visiveisPara($usuario))
            ->withCount(['mensagens as mensagens_visiveis_count' => fn (Builder $q) => $q->publicado()->visiveisPara($usuario)])
            // Eager-load recebe a própria relação (BelongsToMany), não um Builder — fechamento SEM tipo.
            ->with(['mensagens' => fn ($q) => $q->publicado()->visiveisPara($usuario)])
            ->orderBy('nome')
            ->get();

        $totalMensagensVisiveis = Mensagem::publicado()->visiveisPara($usuario)->count();
        $destaque = $autores->sortByDesc('mensagens_visiveis_count')->first();   // O3 (desempate por nome via orderBy prévio)

        $resposta = response()->view('autores.index', [
            'autores' => $autores,
            'totalAutores' => $autores->count(),
            'totalMensagensVisiveis' => $totalMensagensVisiveis,
            'destaque' => $destaque,
            'logado' => $usuario !== null,
        ]);

        if ($usuario !== null) {
            $resposta->header('Cache-Control', 'private, no-store'); // varia por usuário — não cacheável por proxy
        }

        return $resposta;
    }

    public function show(Request $request, string $slug): Response
    {
        $usuario = $request->user();

        // O5a: 404 só para autor inativo/inexistente. Autor ativo sem nenhuma mensagem VISÍVEL
        // segue acessível por URL direta — 200, grade vazia, stats zerados.
        $autor = AutorEspiritual::query()->ativo()->where('slug', $slug)->firstOrFail();

        // As mensagens que ESTE usuário pode ver (anônimo = só públicas ≡ 2B); ordem "recentes"
        // (data desc, nulos por último) em PHP (portável). with('autores'): o card variante=perfil
        // renderiza iniciais/nomes dos autores (evita N+1).
        $mensagens = $autor->mensagens()->publicado()->visiveisPara($usuario)->with(['media', 'autores'])->get()
            ->sortByDesc(fn (Mensagem $m) => $m->data_recebimento?->getTimestamp() ?? PHP_INT_MIN)
            ->values();

        $resumo = new ResumoAutor($mensagens);

        // Payload enxuto para o Alpine (filtro por formato + ordenação client-side).
        $itensFiltro = $mensagens->map(fn (Mensagem $m) => [
            'id' => $m->id,
            'titulo' => $m->titulo,
            'ts' => $m->data_recebimento?->getTimestamp(),
            'formato' => $m->formato?->value,
        ])->values();

        $resposta = response()->view('autores.show', [
            'autor' => $autor,
            'mensagens' => $mensagens,
            'resumo' => $resumo,
            'destaque' => $mensagens->first(),   // mais recente visível (ou null)
            'itensFiltro' => $itensFiltro,
            'logado' => $usuario !== null,
        ]);

        if ($usuario !== null) {
            $resposta->header('Cache-Control', 'private, no-store');
        }

        return $resposta;
    }
}
```

- [ ] **Step 4: Renomear os consumidores internos do contador**

`resources/views/autores/index.blade.php:71` — a var do mini-stat:

```blade
                        <p class="font-display text-2xl font-bold text-secondary">{{ $totalMensagensVisiveis }}</p>
```

`resources/views/components/autor/card.blade.php` — o bloco de comentário-contrato (`:3-8`) e o consumo (`:10`):

```blade
{{-- Requer: $autor com mensagens_visiveis_count e a relação mensagens já filtrada por
     publicado()->visiveisPara($usuario) no controller (senão vaza formatos de restritas ao anônimo).
     Card de autor (grade inteira clicável). Foto 3:4 ou imagem de fallback (autor-fallback.svg, O4).
     Contagem SÓ do que o usuário vê (B1 — nunca Str::plural) + pontinhos de formatos distintos do
     que ele vê (mensagens já vem eager-load filtrado no controller — sem N+1). Sem curtir (F5). --}}
@php
    $contagem = $autor->mensagens_visiveis_count ?? 0;
```

`app/Support/AutoresEspirituais/ResumoAutor.php` — o docblock da classe (`:12-16`) e o de `selos()` (`:78`): trocar "mensagens **PÚBLICAS**" / "das públicas" por "mensagens **visíveis ao usuário**" / "das visíveis". Nenhuma linha de lógica muda (a classe recebe a coleção pronta).

- [ ] **Step 5: Rodar e verificar que passa**

```
docker compose exec -T app php artisan test --filter="AutorVisibilidadeTest|AutoresIndexTest|AutorShowTest|AutorSeoTest|AutorSitemapTest"
```

Esperado: **PASS**. Os testes existentes de autor seguem verdes (o anônimo não mudou); os quatro novos passam.

- [ ] **Step 6: Pint e commit**

```bash
docker compose exec -T app ./vendor/bin/pint
git add app/Http/Controllers/AutorEspiritualController.php resources/views/autores/index.blade.php resources/views/components/autor/card.blade.php app/Support/AutoresEspirituais/ResumoAutor.php tests/Feature/Front/AutorVisibilidadeTest.php
git commit -m "feat(f4c-b): lista e perfil de autores viewer-aware + Cache-Control

Os 4 usos de publica() no @index e a fonte unica do @show passam a
publicado()->visiveisPara(usuario). @index/@show injetam Request e retornam
Response com private/no-store no logado (molde do MensagemController). O
anonimo segue identico a 2B (visiveisPara(null) == publica()).

with() fica SEM type-hint: o eager-load recebe a Relation, nao um Builder.
Contador interno renomeado para mensagens_visiveis_count; rotulos visiveis
ainda em publicas (Task 2)."
```

---

## Task 2: Rótulos visíveis condicionais (B1, A1)

As quatro superfícies que **afirmam** "públicas" passam a variar por `$logado`: anônimo lê "públicas"; logado lê **"disponíveis a você"** — a **mesma copy da tela de Mensagens** ([mensagens/index.blade.php:38-41](resources/views/mensagens/index.blade.php#L38-L41)). É divergência **ratificada** do A3 (que dizia "Mensagens") — declarar no PR. O card (`:34`) já é neutro — não muda. **Atenção às frases:** tile/mini-stat/contagem usam "disponíveis a você" (frase-rótulo), mas o **estado vazio** logado é a frase natural "Ainda não há mensagens deste autor que você possa ver." (não "…disponíveis a você deste autor.", que sai truncada).

**Files:**
- Modify: `resources/views/autores/index.blade.php:72` (mini-stat)
- Modify: `resources/views/autores/show.blade.php:16-20,126,163-167` (tile, contagem, vazio)
- Test: `tests/Feature/Front/AutorVisibilidadeTest.php` (acrescentar 2 métodos)

**Interfaces:**
- Consumes: `$logado` (Task 1) nas duas views.
- Produces: nenhuma API nova.

- [ ] **Step 1: Escrever os testes de rótulo**

Acrescentar a `tests/Feature/Front/AutorVisibilidadeTest.php`:

```php
    /** A1: o anônimo lê "públicas" na lista e no perfil. */
    public function test_a1_rotulos_do_anonimo_dizem_publicas(): void
    {
        $autor = AutorEspiritual::factory()->create(['slug' => 'rotulo-anon', 'ativo' => true]);
        Mensagem::factory()->count(2)->publica()->create()->each(fn ($m) => $m->autores()->attach($autor->id));

        $this->get(route('autores.index'))->assertOk()->assertSee('Mensagens públicas');
        $this->get(route('autores.show', 'rotulo-anon'))->assertOk()
            ->assertSee('Mensagens públicas')   // tile
            ->assertSee('2 públicas');          // contagem da grade
    }

    /** A1: o logado lê "disponíveis a você" (alinhado ao índice de Mensagens). */
    public function test_a1_rotulos_do_logado_dizem_disponiveis(): void
    {
        $autor = AutorEspiritual::factory()->create(['slug' => 'rotulo-log', 'ativo' => true]);
        Mensagem::factory()->count(2)->publica()->create()->each(fn ($m) => $m->autores()->attach($autor->id));
        $trab = $this->comPapel('trabalhador');

        $this->actingAs($trab)->get(route('autores.index'))->assertOk()->assertSee('Mensagens disponíveis a você');
        $this->actingAs($trab)->get(route('autores.show', 'rotulo-log'))->assertOk()
            ->assertSee('Mensagens disponíveis a você')   // tile
            ->assertSee('2 disponíveis a você');          // contagem da grade
    }
```

- [ ] **Step 2: Rodar e verificar que falha**

```
docker compose exec -T app php artisan test --filter="AutorVisibilidadeTest::test_a1"
```

Esperado: **FAIL** — `test_a1_rotulos_do_logado_…` reprova (as views ainda dizem "públicas" para todos). `test_a1_rotulos_do_anonimo_…` passa desde já.

- [ ] **Step 3: Tornar os rótulos condicionais**

`resources/views/autores/index.blade.php:72`:

```blade
                        <p class="text-xs text-text-muted">{{ $logado ? 'Mensagens disponíveis a você' : 'Mensagens públicas' }}</p>
```

`resources/views/autores/show.blade.php` — no bloco `@php` do topo (`:16-20`), acrescentar um helper de contagem e trocar o rótulo do tile 1:

```php
    $rotuloContagem = fn (int $n) => $logado
        ? ($n === 1 ? 'disponível a você' : 'disponíveis a você')
        : ($n === 1 ? 'pública' : 'públicas');

    $tiles = [
        ['valor' => $resumo->total(), 'rotulo' => $logado ? 'Mensagens disponíveis a você' : 'Mensagens públicas', 'bg' => 'bg-cream'],
        ['valor' => $predominante ? $predominante->rotulo() : '—', 'rotulo' => 'Formato predominante', 'bg' => 'bg-[#EAF0F6]'],
        ['valor' => $ultima ? ucfirst(str_replace('.', '', $ultima->translatedFormat('M/Y'))) : '—', 'rotulo' => 'Última mensagem', 'bg' => 'bg-[#EAF2EC]'],
    ];
```

A contagem da grade (`:126`):

```blade
                            <p class="font-mono text-[11px] uppercase tracking-[0.12em] text-[#b08a2e]">{{ $resumo->total() }} {{ $rotuloContagem($resumo->total()) }}</p>
```

O estado vazio (`:165`):

```blade
                                {{ $logado ? 'Ainda não há mensagens deste autor que você possa ver.' : 'Ainda não há mensagens públicas deste autor.' }}
```

- [ ] **Step 4: Rodar e verificar que passa**

```
docker compose exec -T app php artisan test --filter="AutorVisibilidadeTest|AutoresIndexTest"
```

Esperado: **PASS**. `AutoresIndexTest::test_contagem_so_das_publicas` (que assere "3 mensagens", rótulo neutro do card) segue verde.

- [ ] **Step 5: Pint e commit**

```bash
docker compose exec -T app ./vendor/bin/pint
git add resources/views/autores/index.blade.php resources/views/autores/show.blade.php tests/Feature/Front/AutorVisibilidadeTest.php
git commit -m "feat(f4c-b): rotulos de autores condicionais (anonimo publicas / logado disponiveis)

As 4 superficies que afirmavam 'publicas' variam por \$logado; o logado le
'disponiveis a voce', alinhado ao indice de Mensagens. O card ja era neutro."
```

---

## Task 3: Rodapé condicional anti-PII (B1, O1)

O rodapé estático de login vira **condicional**: só aparece quando o autor tem mensagem **hierárquica** (não-direcionada, com nível) que **este** usuário não vê. Direcionadas e `nivel=null` ficam **fora** do cálculo (anti-PII e dado incompleto). Dois estados de copy; some para quem vê tudo.

**Files:**
- Modify: `app/Http/Controllers/AutorEspiritualController.php` (`@show`: computar `$temRestritasOcultas`, passar à view; `use App\Enums\VisibilidadeMensagem;`)
- Modify: `resources/views/autores/show.blade.php:169-173` (rodapé)
- Modify: `tests/Feature/Front/AutorShowTest.php:53-60` (split de `test_sem_curtir_e_com_link_login`)
- Test: `tests/Feature/Front/AutorRodapeCondicionalTest.php` (novo)

**Interfaces:**
- Consumes: `Mensagem::scopePublicado`/`scopeVisiveisPara`; `VisibilidadeMensagem::Direcionada`.
- Produces: a view recebe `'temRestritasOcultas' => bool`.

- [ ] **Step 1: Ajustar o teste existente (O1 do passe) e escrever o novo**

⚠️ **`test_sem_curtir_e_com_link_login` NÃO quebra** com o rodapé condicional: `route('login')` vem do header (`@guest`, [header:29](resources/views/components/layout/header.blade.php#L29)/[:115](resources/views/components/layout/header.blade.php#L115)), então `assertSee(route('login'))` segue verde pelo header — o teste fica **vacuoso** quanto ao rodapé. Reduzir para só a parte do "Curtir":

Em `tests/Feature/Front/AutorShowTest.php`, substituir `test_sem_curtir_e_com_link_login` (linhas 53-60) por:

```php
    public function test_sem_curtir(): void
    {
        $a = AutorEspiritual::factory()->create(['ativo' => true, 'slug' => 'x']);

        $this->get(route('autores.show', 'x'))->assertDontSee('Curtir');   // F5 fora (tile e botão)
    }
```

Criar `tests/Feature/Front/AutorRodapeCondicionalTest.php` — o rodapé é asserido pela **FRASE**, nunca por `route('login')` (não-vacuoso):

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-23

namespace Tests\Feature\Front;

use App\Models\AutorEspiritual;
use App\Models\Mensagem;
use App\Models\User;
use Database\Seeders\EstruturaCemaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AutorRodapeCondicionalTest extends TestCase
{
    use RefreshDatabase;

    private const FRASE_LOGADO = 'Este autor tem mensagens restritas que você ainda não pode ver';
    private const FRASE_ANONIMO = 'Há mensagens restritas a trabalhadores e médiuns';

    private bool $estruturaSemeada = false;

    /** Semeia a estrutura UMA vez por teste; findOrCreate cobre 'administrador' (fora do EstruturaCemaSeeder). */
    private function comPapel(string $papel): User
    {
        if (! $this->estruturaSemeada) {
            $this->seed(EstruturaCemaSeeder::class);
            $this->estruturaSemeada = true;
        }
        Role::findOrCreate($papel, 'web');
        $u = User::factory()->create();
        $u->assignRole($papel);

        return $u->fresh();
    }

    private function autor(string $slug): AutorEspiritual
    {
        return AutorEspiritual::factory()->create(['slug' => $slug, 'ativo' => true]);
    }

    /** I6: aparece para quem tem oculta hierárquica; some para o admin (vê tudo). */
    public function test_i6_aparece_para_quem_nao_ve_e_some_para_o_admin(): void
    {
        $autor = $this->autor('i6');
        Mensagem::factory()->publica()->create()->autores()->attach($autor->id);
        Mensagem::factory()->create(['status' => 'publicado', 'nivel' => 'diretores'])->autores()->attach($autor->id);

        $trab = $this->comPapel('trabalhador');   // nível 20 < 30 → não vê 'diretores'
        $this->actingAs($trab)->get(route('autores.show', 'i6'))->assertOk()->assertSee(self::FRASE_LOGADO);

        $admin = $this->comPapel('administrador');
        $this->actingAs($admin)->get(route('autores.show', 'i6'))->assertOk()->assertDontSee(self::FRASE_LOGADO);
    }

    /**
     * I7 (anti-PII): uma Direcionada a TERCEIRO não faz o rodapé aparecer.
     * Não-vacuoso: o mesmo trabalhador vê a frase num autor com oculta hierárquica (controle).
     */
    public function test_i7_direcionada_a_terceiro_nao_dispara(): void
    {
        $trab = $this->comPapel('trabalhador');
        $terceiro = User::factory()->create();

        // Autor A: só uma direcionada a um TERCEIRO → nada oculto "hierárquico" para o trabalhador.
        $autorA = $this->autor('i7-direcionada');
        $dir = Mensagem::factory()->create(['status' => 'publicado', 'nivel' => 'direcionada']);
        $dir->destinatarios()->attach($terceiro->id);
        $dir->autores()->attach($autorA->id);

        // Autor B (controle): uma oculta hierárquica → a frase PODE aparecer.
        $autorB = $this->autor('i7-controle');
        Mensagem::factory()->create(['status' => 'publicado', 'nivel' => 'diretores'])->autores()->attach($autorB->id);

        $this->actingAs($trab)->get(route('autores.show', 'i7-direcionada'))->assertOk()->assertDontSee(self::FRASE_LOGADO);
        $this->actingAs($trab)->get(route('autores.show', 'i7-controle'))->assertOk()->assertSee(self::FRASE_LOGADO);
    }

    /** I8: mensagem publicada com nivel=null não dispara o rodapé (whereNotNull a exclui). */
    public function test_i8_nivel_null_nao_dispara(): void
    {
        $autor = $this->autor('i8');
        Mensagem::factory()->create(['status' => 'publicado', 'nivel' => null])->autores()->attach($autor->id);
        $trab = $this->comPapel('trabalhador');

        $this->actingAs($trab)->get(route('autores.show', 'i8'))->assertOk()->assertDontSee(self::FRASE_LOGADO);
    }

    /** I9 (A2, anônimo): só-público → sem rodapé; com restrita hierárquica → rodapé @guest com login. */
    public function test_i9_anonimo_ve_rodape_so_quando_ha_restrita(): void
    {
        $soPublico = $this->autor('i9-publico');
        Mensagem::factory()->publica()->create()->autores()->attach($soPublico->id);

        $comRestrita = $this->autor('i9-restrita');
        Mensagem::factory()->publica()->create()->autores()->attach($comRestrita->id);
        Mensagem::factory()->create(['status' => 'publicado', 'nivel' => 'trabalhadores'])->autores()->attach($comRestrita->id);

        $this->get(route('autores.show', 'i9-publico'))->assertOk()->assertDontSee(self::FRASE_ANONIMO);

        $this->get(route('autores.show', 'i9-restrita'))->assertOk()
            ->assertSee(self::FRASE_ANONIMO)
            ->assertSee(route('login'), false);   // o rodapé @guest traz o link de login
    }
}
```

- [ ] **Step 2: Rodar e verificar que falha**

```
docker compose exec -T app php artisan test --filter="AutorRodapeCondicionalTest|AutorShowTest"
```

Esperado: **FAIL**. O rodapé é estático hoje: `test_i6_…` reprova na parte do admin (`assertDontSee` acha a frase — o rodapé aparece para todos), `test_i7_…`/`test_i8_…` reprovam (a frase aparece onde não devia), `test_i9_…` reprova no autor só-público. `test_sem_curtir` passa.

- [ ] **Step 3: Computar `$temRestritasOcultas` no controller**

Em `app/Http/Controllers/AutorEspiritualController.php`, acrescentar o import:

```php
use App\Enums\VisibilidadeMensagem;
```

No `@show`, **depois** de `$autor = …firstOrFail();` e **antes** do `response()->view`, calcular a flag e incluí-la no payload:

```php
        // O1 (rodapé condicional): existe mensagem HIERÁRQUICA (nível definido, não-direcionada) do
        // autor que ESTE usuário não vê? Direcionadas de terceiros e nivel=null ficam fora (anti-PII).
        $totalHierarquicas = $autor->mensagens()
            ->publicado()->whereNotNull('nivel')
            ->where('nivel', '!=', VisibilidadeMensagem::Direcionada->value)
            ->count();
        $visiveisHierarquicas = $autor->mensagens()
            ->publicado()->visiveisPara($usuario)->whereNotNull('nivel')
            ->where('nivel', '!=', VisibilidadeMensagem::Direcionada->value)
            ->count();
        $temRestritasOcultas = $totalHierarquicas > $visiveisHierarquicas;
```

e no array do `response()->view('autores.show', [...])`, acrescentar:

```php
            'temRestritasOcultas' => $temRestritasOcultas,
```

- [ ] **Step 4: Tornar o rodapé condicional na view**

`resources/views/autores/show.blade.php` — substituir o rodapé estático (linhas 169-173) por:

```blade
                        {{-- Rodapé condicional (O1): só quando há oculta hierárquica para ESTE usuário. Sem número (anti-PII). --}}
                        @if ($temRestritasOcultas)
                            <p class="mt-8 rounded-xl border border-dashed border-border bg-white/60 px-5 py-4 text-center text-[13.5px] leading-relaxed text-text-secondary">
                                @guest
                                    Há mensagens restritas a trabalhadores e médiuns.
                                    <a href="{{ route('login') }}" class="font-medium text-primary underline hover:text-secondary">Entre</a> para ver o que é seu.
                                @else
                                    Este autor tem mensagens restritas que você ainda não pode ver.
                                @endguest
                            </p>
                        @endif
```

- [ ] **Step 5: Rodar e verificar que passa**

```
docker compose exec -T app php artisan test --filter="AutorRodapeCondicionalTest|AutorShowTest|AutorVisibilidadeTest"
```

Esperado: **PASS**. A frase agora só aparece nos casos certos, por papel e por estado de login.

- [ ] **Step 6: Pint e commit**

```bash
docker compose exec -T app ./vendor/bin/pint
git add app/Http/Controllers/AutorEspiritualController.php resources/views/autores/show.blade.php tests/Feature/Front/AutorRodapeCondicionalTest.php tests/Feature/Front/AutorShowTest.php
git commit -m "feat(f4c-b): rodape condicional anti-PII no perfil do autor

Aparece so quando ha mensagem hierarquica (nivel definido, nao-direcionada)
que o usuario nao ve; direcionadas de terceiros e nivel=null ficam fora dos
dois lados da conta. Dois estados de copy, sem numero; some para quem ve tudo.

O teste do rodape assere a FRASE, nunca route('login') (que vem do header e
tornaria a assercao vacua). test_sem_curtir_e_com_link_login vira test_sem_curtir."
```

---

## Task 4: Imagem de fallback para autor sem foto (B2, O4)

Autor sem foto passa a mostrar `autor-fallback.svg` (imagem simbólica com fundo próprio) no hero **e** no card, no lugar do gradiente+iniciais. **Só** nas duas views de autor — o trait `TemIniciais` (Palestrante/User) fica intacto.

**Files:**
- Create: `public/images/autor-fallback.svg` (copiado)
- Modify: `resources/views/autores/show.blade.php:64-67` (hero `@else`)
- Modify: `resources/views/components/autor/card.blade.php:20,23-26` (wrapper + `@else`)
- Test: `tests/Feature/Front/AutorFallbackFotoTest.php` (novo)

**Interfaces:**
- Consumes: `$autor->foto_url` (accessor existente; null quando não há mídia).
- Produces: o asset `images/autor-fallback.svg`.

- [ ] **Step 1: Copiar o SVG**

```bash
cp "design_handoff_autor_espiritual_perfil/entrega_autor_fallback/autor-fallback.svg" "public/images/autor-fallback.svg"
```

Conferir que veio (≈63 linhas, `viewBox="0 0 600 800"`):

```bash
head -1 public/images/autor-fallback.svg
```

Esperado: a linha `<svg ... viewBox="0 0 600 800" ...>`.

- [ ] **Step 2: Escrever o teste (fallback nas 2 telas + não-regressão)**

Criar `tests/Feature/Front/AutorFallbackFotoTest.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-23

namespace Tests\Feature\Front;

use App\Models\AutorEspiritual;
use App\Models\Mensagem;
use App\Models\Palestrante;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AutorFallbackFotoTest extends TestCase
{
    use RefreshDatabase;

    /** PNG 1x1 mínimo (evita GD real sob carga — flaky conhecido). */
    private const PNG_1X1 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAAC0lEQVQImWNgAAIAAAUAAWJVMogAAAAASUVORK5CYII=';

    /** I12: autor SEM foto mostra o SVG (não as iniciais) — no card (lista) e no hero (perfil). */
    public function test_i12_autor_sem_foto_mostra_o_svg(): void
    {
        $autor = AutorEspiritual::factory()->create(['nome' => 'Sem Retrato', 'slug' => 'sem-retrato', 'ativo' => true]);
        Mensagem::factory()->publica()->create()->autores()->attach($autor->id);

        $this->get(route('autores.index'))->assertOk()->assertSee('images/autor-fallback.svg', false);
        $this->get(route('autores.show', 'sem-retrato'))->assertOk()->assertSee('images/autor-fallback.svg', false);
    }

    /** I12: autor COM foto mostra a foto, não o SVG. */
    public function test_i12_autor_com_foto_mostra_a_foto(): void
    {
        Storage::fake('public');
        $autor = AutorEspiritual::factory()->create(['nome' => 'Com Retrato', 'slug' => 'com-retrato', 'ativo' => true]);
        $autor->addMediaFromString(base64_decode(self::PNG_1X1))->usingFileName('f.png')->toMediaCollection(AutorEspiritual::COLECAO_FOTO);
        Mensagem::factory()->publica()->create()->autores()->attach($autor->id);

        $res = $this->get(route('autores.show', 'com-retrato'));
        $res->assertOk()
            ->assertSee($autor->fresh()->foto_url, false)
            ->assertDontSee('images/autor-fallback.svg', false);
    }

    /** I13 (não-regressão): o trait compartilhado segue dando iniciais — o fallback é só do autor. */
    public function test_i13_fallback_nao_afeta_palestrante_nem_user(): void
    {
        // Atribuição direta (sem depender de $fillable): o trait TemIniciais é o que importa.
        $palestrante = new Palestrante;
        $palestrante->nome = 'Bezerra Menezes';
        $user = new User;
        $user->name = 'Ana Prado';

        $this->assertSame('BM', $palestrante->iniciais);
        $this->assertSame('AP', $user->iniciais);
    }
}
```

- [ ] **Step 3: Rodar e verificar que falha**

```
docker compose exec -T app php artisan test --filter=AutorFallbackFotoTest
```

Esperado: **FAIL** em `test_i12_autor_sem_foto_…` (as views ainda renderizam iniciais, não o SVG). `test_i12_autor_com_foto_…` e `test_i13_…` passam desde já.

- [ ] **Step 4: Trocar o `@else` nas duas views de autor**

`resources/views/autores/show.blade.php` — o hero (linhas 64-67, dentro do `@else`):

```blade
                        @else
                            <img src="{{ asset('images/autor-fallback.svg') }}" alt="{{ $autor->nome }}" width="186" height="248"
                                 class="block aspect-[3/4] w-full rounded-[15px] object-cover">
                        @endif
```

`resources/views/components/autor/card.blade.php` — **tirar `cema-grad-{{ $autor->id % 8 }}` do wrapper (`:20`)** e trocar o `@else` (`:23-26`):

```blade
    <span class="cema-autor-avatar relative block aspect-[3/4] w-full overflow-hidden" aria-hidden="true">
        @if ($autor->foto_url)
            <img src="{{ $autor->foto_url }}" alt="" loading="lazy" class="size-full object-cover transition duration-300 group-hover:scale-[1.03]">
        @else
            <img src="{{ asset('images/autor-fallback.svg') }}" alt="" loading="lazy" class="size-full object-cover">
        @endif
    </span>
```

⚠️ O SVG traz fundo próprio (lavanda) → cobre branco e roxo; `alt=""` no card (o nome está ao lado), `alt` com o nome no hero. **Não** tocar o trait `TemIniciais`.

- [ ] **Step 5: Rodar e verificar que passa**

```
docker compose exec -T app php artisan test --filter="AutorFallbackFotoTest|AutorShowTest|AutoresIndexTest"
```

Esperado: **PASS**. Nenhum teste de autor com foto quebra.

- [ ] **Step 6: Pint e commit**

```bash
docker compose exec -T app ./vendor/bin/pint
git add public/images/autor-fallback.svg resources/views/autores/show.blade.php resources/views/components/autor/card.blade.php tests/Feature/Front/AutorFallbackFotoTest.php
git commit -m "feat(f4c-b): imagem de fallback para autor sem foto (so nas views de autor)

autor-fallback.svg (fundo proprio, 3:4) substitui gradiente+iniciais no hero
e no card. O cema-grad sai tambem do wrapper :20 do card (estava nos dois
ramos). O trait TemIniciais (Palestrante/User) fica intacto — I13.

Diverge do handoff-base (gradiente+iniciais): a entrega mais nova
entrega_autor_fallback/ implementa a decisao do dono (imagem). Declarado no PR."
```

---

## Task 5: Bloco "Sobre {nome}" no perfil (B2, O5)

Um card branco com a bio em prosa, entre os tiles de stats e a grade de mensagens. Condicional a `filled($autor->bio)` — sem bio, o bloco não existe. A bio já é HTML saneado (`clean('conteudo')`), renderizada como o corpo da mensagem.

**Files:**
- Modify: `resources/views/autores/show.blade.php` (novo bloco após a `</div>` dos tiles, ~`:116`)
- Test: `tests/Feature/Front/AutorPerfilBlocoSobreTest.php` (novo)

**Interfaces:**
- Consumes: `$autor->bio` (accessor; HTML saneado ou null).
- Produces: nenhuma API nova.

- [ ] **Step 1: Escrever o teste (com bio / sem bio / chamada vazia)**

Criar `tests/Feature/Front/AutorPerfilBlocoSobreTest.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-23

namespace Tests\Feature\Front;

use App\Models\AutorEspiritual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutorPerfilBlocoSobreTest extends TestCase
{
    use RefreshDatabase;

    /** I14: com bio, o perfil mostra o bloco "Sobre {nome}" + a prosa. */
    public function test_i14_com_bio_mostra_o_bloco_sobre(): void
    {
        AutorEspiritual::factory()->create([
            'nome' => 'Bezerra de Menezes', 'slug' => 'bezerra', 'ativo' => true,
            'bio' => '<p>Médico e benfeitor espiritual, dedicou-se à caridade.</p>',
        ]);

        $this->get(route('autores.show', 'bezerra'))->assertOk()
            ->assertSee('Sobre Bezerra de Menezes')
            ->assertSee('Médico e benfeitor espiritual, dedicou-se à caridade.', false);
    }

    /** I14: sem bio, o bloco não existe (nem card vazio). */
    public function test_i14_sem_bio_nao_tem_o_bloco(): void
    {
        AutorEspiritual::factory()->create([
            'nome' => 'Autor Sem Bio', 'slug' => 'sem-bio', 'ativo' => true, 'bio' => null,
        ]);

        $this->get(route('autores.show', 'sem-bio'))->assertOk()->assertDontSee('Sobre Autor Sem Bio');
    }

    /** I15 (não-regressão): chamada vazia não deixa órfão nem quebra o perfil. */
    public function test_i15_chamada_vazia_nao_quebra(): void
    {
        AutorEspiritual::factory()->create([
            'nome' => 'Sem Chamada', 'slug' => 'sem-chamada', 'ativo' => true, 'chamada' => null,
        ]);

        $this->get(route('autores.show', 'sem-chamada'))->assertOk()->assertSee('Sem Chamada');
    }
}
```

- [ ] **Step 2: Rodar e verificar que falha**

```
docker compose exec -T app php artisan test --filter=AutorPerfilBlocoSobreTest
```

Esperado: **FAIL** em `test_i14_com_bio_…` (o bloco não existe ainda). Os outros dois passam (o bloco ausente e a chamada vazia já são o comportamento de hoje).

- [ ] **Step 3: Criar o bloco na view**

`resources/views/autores/show.blade.php` — **logo após** a `</div>` que fecha a grade de tiles (a linha 116, `@endforeach`/`</div>` do bloco `$tiles`) e **antes** de `{{-- Grade das mensagens públicas do autor --}}` (linha 118), inserir:

```blade
                    {{-- Sobre {nome}: bio em prosa. Só renderiza quando há bio (D3). --}}
                    @if (filled($autor->bio))
                        <div class="mt-8 rounded-[18px] border border-border-muted bg-white p-7 shadow-card">
                            <h2 class="font-display text-xl font-semibold text-primary">Sobre {{ $autor->nome }}</h2>
                            <div class="mb-4 mt-2.5 h-[3.5px] w-[52px] rounded-sm bg-gold"></div>
                            {{-- bio é HTML saneado por clean('conteudo') no model — {!! !!} é seguro (mesmo caso do corpo da mensagem). --}}
                            <div class="cema-msg-prose">{!! $autor->bio !!}</div>
                        </div>
                    @endif
```

⚠️ A classe `.cema-msg-prose` já existe no bundle (usada no corpo da mensagem) — **sem `npm run build`**.

- [ ] **Step 4: Rodar e verificar que passa**

```
docker compose exec -T app php artisan test --filter="AutorPerfilBlocoSobreTest|AutorShowTest"
```

Esperado: **PASS**.

- [ ] **Step 5: Pint e commit**

```bash
docker compose exec -T app ./vendor/bin/pint
git add resources/views/autores/show.blade.php tests/Feature/Front/AutorPerfilBlocoSobreTest.php
git commit -m "feat(f4c-b): bloco 'Sobre {nome}' no perfil do autor

Card branco com regua dourada e a bio em prosa (cema-msg-prose), entre os
tiles e a grade. Condicional a filled(bio) — sem bio, sem bloco. A bio ja e
saneada por clean('conteudo'), entao {!! !!} e seguro."
```

---

## Verificação final e handoff

Depois das 5 tasks, **na branch**, antes de abrir o PR:

- [ ] **Suíte completa verde** (o comportamento é por-usuário — não confiar em `--filter`):

```
docker compose exec -T app php artisan test
```

Esperado: **PASS**, `baseline + 16` (**4** na Task 1 · **2** na Task 2 · **4** na Task 3 · **3** na Task 4 · **3** na Task 5 = **+16**; o split da Task 3 **renomeia** `test_sem_curtir_e_com_link_login`, não soma). **Reconfirmar o baseline no arranque**; qualquer regressão (não só o número) trava o PR.

- [ ] **Pint limpo** (o CI aborta no Pint antes dos testes):

```
docker compose exec -T app ./vendor/bin/pint --test
```

- [ ] **Provas de allowlist (SPEC §9):**

```bash
grep -rn "publica()" app/Http/Controllers/AutorEspiritualController.php          # esperado: 0 (inclui comentarios)
grep -rnE "publicas|públicas" resources/views/autores resources/views/components/autor   # so os ramos condicionais do anonimo
grep -rn "cema-grad" resources/views/autores resources/views/components/autor    # esperado: 0
grep -rn "iniciais" resources/views/autores resources/views/components/autor     # esperado: 0 (fallback trocou o @else)
```

- [ ] **Conferência no localhost (SPEC §7)** — reconfirmar os números do dev no arranque:
  1. **anônimo** vê a lista idêntica à de hoje e **sem** rodapé num autor só-público;
  2. **logado** (ex.: Thiago) vê mais autores na grade; o número do card bate com a grade de cada perfil; a resposta traz `Cache-Control: private, no-store` (DevTools → Network);
  3. o **rodapé** aparece num autor com restrita hierárquica para quem não a vê e **some** para o admin;
  4. **Abílio** (sem foto) mostra o **SVG** no card e no hero;
  5. um autor **com** bio mostra o bloco **"Sobre"**; um dos **6 sem bio** não tem o bloco.

- [ ] **PR** — no corpo, declarar:
  - a **divergência D2**: o handoff-base resolvia "sem foto" com gradiente+iniciais; esta fatia usa **imagem de fallback** (decisão do dono; a entrega `entrega_autor_fallback/` já a implementa e vence — pacote internamente inconsistente);
  - a **copy do logado "disponíveis a você"**: reabre o **A3** (que dizia "Mensagens") — divergência **ratificada** pelo dono, alinhada à tela de Mensagens ([mensagens/index.blade.php:38-41](resources/views/mensagens/index.blade.php#L38-L41));
  - o **quirk `nivel=null`**: admin/presidente passam a ver as 2 mensagens `nivel=null` do dev nas contagens (bypass) — dado pré-existente, não regressão;
  - **merge = CI verde no ÚLTIMO commit + go do dono**. Não pré-configurar merge.
- [ ] **Cutover** (dev; PROD do dono): `optimize:clear` + `restart app worker`. **Sem migration, sem `npm run build`, sem `cema:importar-*`.**

---

## Self-review do plano (contra a SPEC)

- **Cobertura:** I1 (T1), I2 (grep final), I3/I4 (T1), I5 (T1), I6/I7/I8/I9 (T3), I10 (guarda O2 — testes existentes seguem verdes, T1 step 5), I11 (T2), I12/I13 (T4), I14/I15 (T5). O1→T3; O2→Global Constraints + grep; O3→T1; O4→T4; O5→T5; A1→T1(interno)+T2(visível); A2→T3(I9); A3→T2(rótulos). Todas as seções da SPEC têm task.
- **Sem placeholders:** todo passo com código traz o código real; comandos com saída esperada.
- **Consistência de tipos/nomes:** `mensagens_visiveis_count`, `$totalMensagensVisiveis`, `$temRestritasOcultas`, `$logado`, `$rotuloContagem` usados de forma idêntica entre controller, views e testes. `visiveisPara($usuario)` sempre encadeado após `publicado()`; o `with()` sempre **sem** type-hint.
