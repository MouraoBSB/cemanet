# Camada 4 · Fatia 3B — Front da visibilidade rica das Mensagens (badges + barreira de login + noindex + menu)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ligar o resolvedor da 3A (hoje inerte) no front das Mensagens: trocar `Mensagem::publica()` por `publicado()->visiveisPara($user)` (lista/single/select de autor); exibir **badge de nível + cadeado + legenda** para logados (anônimo mantém o look 2B); erguer a **barreira de acesso** ao single restrito (**modal de login inline** em view própria e cega — corpo fora do HTML — com 3 desfechos); `noindex` nas restritas e telas de barreira; religar o menu "Mensagens Mediúnicas". **Sem migration.**

**Architecture:** O resolvedor é fonte única no model (3A) — a 3B só o **consome**. O `scopeVisiveisPara` filtra só o **nível** (status é ortogonal), então a 3B adiciona `Mensagem::scopePublicado()` (status-only) e usa `publicado()->visiveisPara($u)` (para anônimo isso é **idêntico** a `publica()` → paridade exata com a 2B). A barreira do single é uma **view própria** (`mensagens/barreira.blade.php`): o `MensagemController@show` resolve por `publicado()->firstOrFail()` (404 real) e, se `! podeSerVistoPor($u)`, grava `url.intended` e serve a barreira **genérica** (nunca o `show.blade`) — o corpo jamais entra no HTML de quem não pode ver. O badge é o componente `x-mensagem.selo-nivel`, **null-safe** (`@if ($visibilidade)`), que é o guard do defeito B1 (2 mensagens `nivel=null` publicadas que só o admin vê). Os canais de contato da tela "sem permissão" vêm de `App\Models\Configuracao` (editáveis por uma Página Filament — o único toque no `/admin`).

**Tech Stack:** PHP 8.3 · Laravel 13 · Filament 5 · Livewire 4 · Tailwind v4 · Fortify (headless) + Socialite · MySQL 8 (dev/prod) e SQLite (testes) · PHPUnit.

**Spec:** [`docs/superpowers/specs/2026-07-20-camada-4-fatia-3b-front-visibilidade-mensagens.md`](../specs/2026-07-20-camada-4-fatia-3b-front-visibilidade-mensagens.md) — aprovada pelo Consultor após 2 bloqueadores (B1 null-guard, B2 contato editável) + R1–R5; forks fechados (F1 split 3C, F2 não expor PII, F5 barreira-200 cega).

## Global Constraints

- **Idioma:** todo código, comentário, mensagem de UI/erro e commit em **português brasileiro**. Sintaxe/APIs de terceiros no original.
- **Branch:** `camada-4-fatia-3b-front-visibilidade` (já criada de `origin/main` = **`0fa26c4`**, PR #39/3A). **Nunca** na `main`. A SPEC já está commitada (`11d11be`); este plano entra junto no PR.
- **Cabeçalho de autoria** em todo arquivo **PHP novo**: `// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-20`. Em Blade novo: `{{-- Thiago Mourão — https://github.com/MouraoBSB — 2026-07-20 --}}`.
- **🚫 SEM migration nesta fatia** (é front; o pivô `mensagem_destinatario` já existe da 3A). **PROIBIDO** `migrate:fresh`/`refresh`/`db:wipe`/`migrate:reset` e qualquer seed/factory destrutivo — o dev tem 152 usuários + 179 mensagens + mídia. **Todo brief de subagente que rode `artisan` DEVE proibir esses comandos** e reafirmar `legado` READ-ONLY.
- **NÃO reabrir decisão travada da SPEC** — o plano deriva 100% dela. Consumir o resolvedor da 3A; **não** reimplementar regra de visibilidade no front.
- **Fonte única do form/paleta:** o form de login vive num parcial reusado (`x-auth.form-login`); a paleta AA vem do enum (`cor`/`corFundo`/`corTexto`/`ehRestrito`); o badge sempre pelo componente `x-mensagem.selo-nivel` (null-safe).
- **B1 (aceite duro):** **nenhum** call-site pode chamar `visibilidade()->metodo()` sem guard — passar por `x-mensagem.selo-nivel` (`@if`) ou usar `?->`. As comparações (`===`/`!==`) são null-safe; as **chamadas** de método não.
- **Aceite:** suíte verde (**~1032 + novos**); as **únicas** asserções que mudam de cor são as intencionais (I-chg: `MensagemShowTest` restrito 404→barreira; o selo hardcoded do single vira dinâmico). `Pint` verde.
- **Comandos:** testes focados por task `docker compose exec -T app php artisan test --filter=X` (o projeto **não** usa Sail). **Pint antes de cada commit:** `docker compose exec -T app ./vendor/bin/pint <arquivos>` ([[pint-antes-de-push]]). Front: `npm run build` roda **no HOST** ([[npm-vite-no-host]]). Se um teste rodar código **stale** após editar arquivo existente, `docker compose restart app worker` (OPcache `validate_timestamps=0`) e rode de novo.
- **Ciência de flaky:** [[flaky-importadorblog-gd-cap-imagem]] — 2 testes de cap de imagem do blog podem falhar sob carga; se passam isolados/no CI, não é regressão desta fatia.

---

### Task 0: Branch

**Files:** nenhum (só git).

- [ ] **Passo 1: Confirmar a branch**

```bash
cd "d:/Claude Code - Projetos/Cemanet - Novo Site"
git branch --show-current   # esperado: camada-4-fatia-3b-front-visibilidade
git log --oneline -2        # esperado: 11d11be (SPEC) sobre 0fa26c4 (PR #39)
```

Esperado: HEAD na branch da 3B, com a SPEC já commitada. Se não estiver na branch: `git switch camada-4-fatia-3b-front-visibilidade`.

---

### Task 1: `scopePublicado` + paleta AA do enum (`cor`/`corFundo`/`corTexto`/`ehRestrito`)

**Files:**
- Modify: `app/Models/Mensagem.php` (`scopePublicado` após `scopePublica`)
- Modify: `app/Enums/VisibilidadeMensagem.php` (`cor()` hues reais + `corFundo()` + `corTexto()` + `ehRestrito()`)
- Test: `tests/Feature/Mensagens/MensagemScopePublicadoTest.php`
- Test: `tests/Unit/Enums/VisibilidadeMensagemBadgeTest.php`

**Interfaces:**
- Consumes: `Mensagem::STATUS_PUBLICADO`, `Mensagem::scopeVisiveisPara` (3A).
- Produces:
  - `Mensagem::scopePublicado(Builder): Builder` → `Mensagem::publicado()` (status-only).
  - `VisibilidadeMensagem::cor(): string` (hues do designer), `corFundo(): string` (rgba), `corTexto(): string` (AA), `ehRestrito(): bool` (`!== Publico`).

**Contexto:** `scopeVisiveisPara` filtra **só o nível** (3A §6.2), logo precisa compor com `publicado()` (status). `publicado()->visiveisPara(null)` ≡ `publica()` — a paridade anônima (I2). `ehRestrito()` (`!= Publico`, inclui escada) é **outro conceito** que `ehRecorte()` (pertencimento — R3).

- [ ] **Passo 1: Escrever o teste do scope que falha**

`tests/Feature/Mensagens/MensagemScopePublicadoTest.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-20

namespace Tests\Feature\Mensagens;

use App\Models\Mensagem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MensagemScopePublicadoTest extends TestCase
{
    use RefreshDatabase;

    public function test_publicado_ignora_status_nao_publicado(): void
    {
        Mensagem::factory()->publica()->create(['slug' => 'p1']);                                  // publicado + publico
        Mensagem::factory()->create(['status' => 'publicado', 'nivel' => 'trabalhadores', 'slug' => 'p2']);
        Mensagem::factory()->pendente()->create(['slug' => 'p3']);

        $this->assertSame(2, Mensagem::publicado()->count());   // p1 + p2 (não a pendente p3)
    }

    public function test_paridade_anonima_com_publica_e_null_fora(): void
    {
        Mensagem::factory()->publica()->create(['slug' => 'pub']);
        Mensagem::factory()->create(['status' => 'publicado', 'nivel' => 'trabalhadores', 'slug' => 'trab']);
        Mensagem::factory()->create(['status' => 'publicado', 'nivel' => null, 'slug' => 'nula']);   // R5: publicada sem nível
        Mensagem::factory()->pendente()->create(['slug' => 'pend']);

        $anon = Mensagem::publicado()->visiveisPara(null)->pluck('slug')->sort()->values()->all();
        $publica = Mensagem::publica()->pluck('slug')->sort()->values()->all();

        $this->assertSame(['pub'], $anon);        // só a pública — a 'nula' e a 'trab' NÃO vazam ao anônimo
        $this->assertSame($publica, $anon);       // paridade exata com a 2B (I2)
    }
}
```

- [ ] **Passo 2: Escrever o teste do enum que falha**

`tests/Unit/Enums/VisibilidadeMensagemBadgeTest.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-20

namespace Tests\Unit\Enums;

use App\Enums\VisibilidadeMensagem;
use PHPUnit\Framework\TestCase;

class VisibilidadeMensagemBadgeTest extends TestCase
{
    public function test_eh_restrito_e_diferente_de_eh_recorte(): void
    {
        // ehRestrito = != Publico (inclui a escada Trabalhadores/Diretores).
        $this->assertFalse(VisibilidadeMensagem::Publico->ehRestrito());
        $this->assertTrue(VisibilidadeMensagem::Trabalhadores->ehRestrito());
        $this->assertTrue(VisibilidadeMensagem::Diretores->ehRestrito());
        $this->assertTrue(VisibilidadeMensagem::Mediuns->ehRestrito());

        // ehRecorte = pertencimento (só Mediuns/DEPAE/Direcionada) — conceito distinto (R3).
        $this->assertFalse(VisibilidadeMensagem::Trabalhadores->ehRecorte()); // difere de ehRestrito aqui
        $this->assertTrue(VisibilidadeMensagem::Mediuns->ehRecorte());
    }

    public function test_paleta_tem_hue_fundo_e_texto_por_nivel(): void
    {
        foreach (VisibilidadeMensagem::cases() as $v) {
            $this->assertMatchesRegularExpression('/^#[0-9A-Fa-f]{6}$/', $v->cor(), $v->value);
            $this->assertMatchesRegularExpression('/^#[0-9A-Fa-f]{6}$/', $v->corTexto(), $v->value);
            $this->assertStringStartsWith('rgba(', $v->corFundo());
        }
    }
}
```

- [ ] **Passo 3: Rodar e ver falhar**

Run: `docker compose exec -T app php artisan test --filter="MensagemScopePublicadoTest|VisibilidadeMensagemBadgeTest"`
Esperado: FAIL (`publicado`/`corFundo`/`corTexto`/`ehRestrito` inexistentes).

- [ ] **Passo 4: Adicionar `scopePublicado` na `Mensagem`**

Em `app/Models/Mensagem.php`, logo após `scopePublica()` (fecha em `:69`):

```php
    /** Só o status publicado (ortogonal ao nível) — compõe com visiveisPara(): publicado()->visiveisPara($u). */
    public function scopePublicado(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLICADO);
    }
```

- [ ] **Passo 5: Substituir `cor()` e acrescentar `corFundo`/`corTexto`/`ehRestrito` no enum**

Em `app/Enums/VisibilidadeMensagem.php`, **substituir** o método `cor()` (`:45-56`) e **acrescentar** os demais (após `cor()`, antes de `opcoes()`):

```php
    /** Hue do nível (ponto/barra/legenda — decorativos, ao lado de rótulo textual ⇒ isentos de contraste). */
    public function cor(): string
    {
        return match ($this) {
            self::Publico => '#6E9FCB',
            self::Trabalhadores => '#A34E5C',
            self::Mediuns => '#5E8770',
            self::Diretores => '#3A4585',
            self::DiretorDepae => '#7C4D8F',
            self::Direcionada => '#26242E',
        };
    }

    /** Fundo translúcido do badge (rgba do hue) — base clara sobre a qual corTexto() atinge AA. */
    public function corFundo(): string
    {
        return match ($this) {
            self::Publico => 'rgba(110,159,203,0.16)',
            self::Trabalhadores => 'rgba(163,78,92,0.14)',
            self::Mediuns => 'rgba(94,135,112,0.18)',
            self::Diretores => 'rgba(58,69,133,0.14)',
            self::DiretorDepae => 'rgba(124,77,143,0.14)',
            self::Direcionada => 'rgba(38,36,46,0.10)',
        };
    }

    /** Cor de TEXTO do badge — escurecida do hue, ≥4,5:1 sobre corFundo() (AA). Validada na implementação. */
    public function corTexto(): string
    {
        return match ($this) {
            self::Publico => '#35618F',
            self::Trabalhadores => '#8F3F4D',
            self::Mediuns => '#3F7256',
            self::Diretores => '#3A4585',
            self::DiretorDepae => '#6A3E7C',
            self::Direcionada => '#26242E',
        };
    }

    /** Restrito = qualquer nível diferente de Público (inclui Trabalhadores/Diretores). NÃO é ehRecorte() (R3). */
    public function ehRestrito(): bool
    {
        return $this !== self::Publico;
    }
```

- [ ] **Passo 6: Rodar e ver passar**

Run: `docker compose exec -T app php artisan test --filter="MensagemScopePublicadoTest|VisibilidadeMensagemBadgeTest"`
Esperado: PASS. **Validação AA (manual, obrigatória):** conferir cada par `corTexto()`×`corFundo()` num verificador de contraste (alvo ≥4,5:1). Se algum reprovar, escurecer o `corTexto()` daquele nível.

- [ ] **Passo 7: Pint + commit**

```bash
docker compose exec -T app ./vendor/bin/pint app/Models/Mensagem.php app/Enums/VisibilidadeMensagem.php tests/Feature/Mensagens/MensagemScopePublicadoTest.php tests/Unit/Enums/VisibilidadeMensagemBadgeTest.php
git add app/Models/Mensagem.php app/Enums/VisibilidadeMensagem.php tests/Feature/Mensagens/MensagemScopePublicadoTest.php tests/Unit/Enums/VisibilidadeMensagemBadgeTest.php
git commit -m "feat(camada-4-fatia-3b): scopePublicado + paleta AA do enum (cor/corFundo/corTexto/ehRestrito)"
```

---

### Task 2: Componentes visuais — `x-mensagem.selo-nivel` (null-guard) + `x-mensagem.legenda-niveis` + CSS

**Files:**
- Create: `resources/views/components/mensagem/selo-nivel.blade.php`
- Create: `resources/views/components/mensagem/legenda-niveis.blade.php`
- Modify: `resources/css/mensagens.css` (comentário de bloco dos badges — os estilos são via `style=` inline do enum, mas registramos a convenção)
- Test: `tests/Feature/Componentes/SeloNivelTest.php`

**Interfaces:**
- Consumes: `VisibilidadeMensagem::cor/corFundo/corTexto/ehRestrito/rotulo/nivelMinimo` (Task 1).
- Produces:
  - `<x-mensagem.selo-nivel :visibilidade="?VisibilidadeMensagem" />` — badge AA + cadeado se restrito; **null ⇒ nada** (B1).
  - `<x-mensagem.legenda-niveis :niveis="Collection<VisibilidadeMensagem>" />` — "Nível de acesso:" + bolinhas.

**Contexto:** o **null-guard é do componente** (`@if ($visibilidade)`) — a fonte única do defeito B1. O cadeado (SVG) só quando `ehRestrito()`; Público leva um ponto na cor. Molde de pílula AA = `x-mensagem.selo-formato`.

- [ ] **Passo 1: Escrever o teste dos componentes que falha**

`tests/Feature/Componentes/SeloNivelTest.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-20

namespace Tests\Feature\Componentes;

use App\Enums\VisibilidadeMensagem;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class SeloNivelTest extends TestCase
{
    private function selo(?VisibilidadeMensagem $v): string
    {
        return Blade::render('<x-mensagem.selo-nivel :visibilidade="$v" />', ['v' => $v]);
    }

    public function test_null_nao_renderiza_nada(): void
    {
        // B1: mensagem nivel=null (vista pelo admin) NÃO pode chamar null->rotulo() (500).
        $this->assertSame('', trim($this->selo(null)));
    }

    public function test_publico_sem_cadeado(): void
    {
        $html = $this->selo(VisibilidadeMensagem::Publico);
        $this->assertStringContainsString('Público', $html);
        $this->assertStringNotContainsString('Acesso restrito', $html); // sem cadeado
    }

    public function test_restrito_com_cadeado(): void
    {
        $html = $this->selo(VisibilidadeMensagem::Diretores);
        $this->assertStringContainsString('Diretores', $html);
        $this->assertStringContainsString('Acesso restrito', $html);    // cadeado (aria-label)
        $this->assertStringContainsString('#3A4585', $html);            // corTexto do nível
    }

    public function test_legenda_lista_niveis_presentes(): void
    {
        $html = Blade::render('<x-mensagem.legenda-niveis :niveis="$n" />', [
            'n' => collect([VisibilidadeMensagem::Publico, VisibilidadeMensagem::Trabalhadores]),
        ]);
        $this->assertStringContainsString('Nível de acesso', $html);
        $this->assertStringContainsString('Público', $html);
        $this->assertStringContainsString('Trabalhadores', $html);
    }
}
```

- [ ] **Passo 2: Rodar e ver falhar**

Run: `docker compose exec -T app php artisan test --filter=SeloNivelTest`
Esperado: FAIL (componentes inexistentes). **Prova do guard (B1):** ao criar o componente (Passo 3), confirme **na prática** que, **sem** o `@if ($visibilidade)`, o `test_null_nao_renderiza_nada` **explode** (`null->rotulo()`), e só passa a verde **com** o `@if` — é a regressão que ancora o null-guard (não só afirmá-lo).

- [ ] **Passo 3: Criar `x-mensagem.selo-nivel`**

`resources/views/components/mensagem/selo-nivel.blade.php`:

```blade
{{-- Thiago Mourão — https://github.com/MouraoBSB — 2026-07-20 --}}
{{-- Badge de NÍVEL de visibilidade: pílula AA (fundo translúcido + texto escurecido) + cadeado se restrito.
     NULL-GUARD (B1): $visibilidade pode ser null (mensagem nivel=null vista pelo admin) => NÃO renderiza nada.
     Fonte da cor/rótulo: App\Enums\VisibilidadeMensagem (cor/corFundo/corTexto/ehRestrito/rotulo). --}}
@props(['visibilidade'])
@if ($visibilidade)
    <span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 rounded-pill px-2.5 py-1 text-[11px] font-semibold']) }}
          style="background:{{ $visibilidade->corFundo() }};color:{{ $visibilidade->corTexto() }}">
        @if ($visibilidade->ehRestrito())
            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-label="Acesso restrito"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
        @else
            <span class="inline-block size-2 rounded-full" style="background:{{ $visibilidade->cor() }}" aria-hidden="true"></span>
        @endif
        {{ $visibilidade->rotulo() }}
    </span>
@endif
```

- [ ] **Passo 4: Criar `x-mensagem.legenda-niveis`**

`resources/views/components/mensagem/legenda-niveis.blade.php`:

```blade
{{-- Thiago Mourão — https://github.com/MouraoBSB — 2026-07-20 --}}
{{-- Legenda de bolinhas dos NÍVEIS presentes no resultado. $niveis = Collection<VisibilidadeMensagem> (sem null).
     O call-site já a envolve em @auth; aqui só renderiza quando há ao menos um nível. --}}
@props(['niveis'])
@if (filled($niveis))
    <div class="flex flex-wrap items-center gap-x-4 gap-y-1.5 text-[12px] text-text-secondary">
        <span class="font-mono text-[10.5px] uppercase tracking-[0.08em] text-text-muted">Nível de acesso:</span>
        @foreach ($niveis as $nivel)
            <span class="inline-flex items-center gap-1.5">
                <span class="inline-block size-2 rounded-full" style="background:{{ $nivel->cor() }}" aria-hidden="true"></span>
                {{ $nivel->rotulo() }}
            </span>
        @endforeach
    </div>
@endif
```

- [ ] **Passo 5: Registrar a convenção de badges no CSS de Mensagens**

Em `resources/css/mensagens.css`, ao final do bloco `@layer components`, acrescentar o comentário-âncora (os estilos de cor vêm do `style=` inline do enum; a régua/legenda usam utilitários — nada a computar aqui, só ancorar a origem):

```css
/* Badges de nível (Fatia 3B): a cor/fundo/texto vêm de App\Enums\VisibilidadeMensagem
   (cor/corFundo/corTexto), aplicados via style= inline nos componentes x-mensagem.selo-nivel
   e x-mensagem.legenda-niveis. A barra 4px/faixa 5px do card/linha usa visibilidade()?->cor()
   (null-safe). Nada de cor hardcoded aqui — fonte única no enum. */
```

- [ ] **Passo 6: Rodar e ver passar**

Run: `docker compose exec -T app php artisan test --filter=SeloNivelTest`
Esperado: PASS.

- [ ] **Passo 7: Pint + commit**

```bash
docker compose exec -T app ./vendor/bin/pint tests/Feature/Componentes/SeloNivelTest.php
git add resources/views/components/mensagem/selo-nivel.blade.php resources/views/components/mensagem/legenda-niveis.blade.php resources/css/mensagens.css tests/Feature/Componentes/SeloNivelTest.php
git commit -m "feat(camada-4-fatia-3b): componentes selo-nivel (null-guard) + legenda-niveis"
```

---

### Task 3: Swap DATA — `Mensagens\Lista` (render + select) + `MensagemController@index` (contador + rótulo + Cache-Control)

**Files:**
- Modify: `app/Livewire/Mensagens/Lista.php` (`render`: `publicado()->visiveisPara`; select de autor; `$niveis`)
- Modify: `app/Http/Controllers/MensagemController.php` (`index`: contador visível + rótulo + Cache-Control logado)
- Modify: `resources/views/mensagens/index.blade.php` (contador dinâmico)
- Test: `tests/Feature/Front/MensagemListaVisibilidadeTest.php`
- Test: `tests/Feature/Front/MensagemIndexContadorTest.php`

**Interfaces:**
- Consumes: `Mensagem::publicado()`/`scopeVisiveisPara` (Tasks 1/3A); `VisibilidadeMensagem` (Task 1).
- Produces: `Mensagens\Lista::render` passa `$niveis` (níveis presentes); `MensagemController@index` passa `$total`/`$logado` e emite `Cache-Control: private` a logado (R2).

**Contexto:** o `visiveisPara($user)` filtra no banco (não vaza). O contador do hero muda de rótulo por persona (§4.3): anônimo "públicas", logado "disponíveis a você" — só 2 rótulos na 3B (o 3º, "direcionadas a você", é 3C). O select de autor lista quem tem ≥1 mensagem **visível** ao usuário.

- [ ] **Passo 1: Escrever o teste da lista que falha**

`tests/Feature/Front/MensagemListaVisibilidadeTest.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-20

namespace Tests\Feature\Front;

use App\Livewire\Mensagens\Lista;
use App\Models\Mensagem;
use App\Models\User;
use Database\Seeders\EstruturaCemaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MensagemListaVisibilidadeTest extends TestCase
{
    use RefreshDatabase;

    private function comPapel(string $papel): User
    {
        $this->seed(EstruturaCemaSeeder::class);
        $u = User::factory()->create();
        $u->assignRole($papel);

        return $u->fresh();
    }

    public function test_anonimo_ve_so_publicas_paridade_2b(): void
    {
        Mensagem::factory()->publica()->create(['titulo' => 'Pública Visível']);
        Mensagem::factory()->create(['status' => 'publicado', 'nivel' => 'trabalhadores', 'titulo' => 'Restrita Oculta']);

        Livewire::test(Lista::class)
            ->assertSee('Pública Visível')
            ->assertDontSee('Restrita Oculta');   // não vaza título restrito ao anônimo (I10)
    }

    public function test_trabalhador_ve_trabalhadores_nao_mediuns(): void
    {
        $trab = $this->comPapel('trabalhador');
        Mensagem::factory()->publica()->create(['titulo' => 'Pub']);
        Mensagem::factory()->create(['status' => 'publicado', 'nivel' => 'trabalhadores', 'titulo' => 'Trab']);
        Mensagem::factory()->create(['status' => 'publicado', 'nivel' => 'mediuns-trabalhadores', 'titulo' => 'Med']);

        Livewire::actingAs($trab)->test(Lista::class)
            ->assertSee('Pub')->assertSee('Trab')->assertDontSee('Med'); // recorte médium não vaza
    }

    public function test_medium_ve_mediuns_trabalhadores(): void
    {
        // Caso POSITIVO do recorte (paridade no front com o resolvedor da 3A): médium vê 'mediuns-trabalhadores'.
        $medium = $this->comPapel('trabalhador');
        $medium->setores()->attach(\App\Models\Setor::where('slug', \App\Models\Setor::SLUG_MEDIUM)->value('id'), ['funcao' => 'membro']);
        Mensagem::factory()->create(['status' => 'publicado', 'nivel' => 'mediuns-trabalhadores', 'titulo' => 'Doc Medium']);

        Livewire::actingAs($medium->fresh())->test(Lista::class)->assertSee('Doc Medium');
    }

    public function test_select_de_autor_so_com_mensagem_visivel(): void
    {
        $trab = $this->comPapel('trabalhador');
        $autorPub = \App\Models\AutorEspiritual::factory()->create(['nome' => 'Autor Público', 'slug' => 'autor-pub']);
        $autorRest = \App\Models\AutorEspiritual::factory()->create(['nome' => 'Autor Só Diretores', 'slug' => 'autor-dir']);
        Mensagem::factory()->publica()->create()->autores()->attach($autorPub->id);
        Mensagem::factory()->create(['status' => 'publicado', 'nivel' => 'diretores'])->autores()->attach($autorRest->id);

        Livewire::actingAs($trab)->test(Lista::class)
            ->assertSee('Autor Público')->assertDontSee('Autor Só Diretores'); // trabalhador não vê Diretores
    }
}
```

- [ ] **Passo 2: Escrever o teste do contador que falha**

`tests/Feature/Front/MensagemIndexContadorTest.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-20

namespace Tests\Feature\Front;

use App\Models\Mensagem;
use App\Models\User;
use Database\Seeders\EstruturaCemaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MensagemIndexContadorTest extends TestCase
{
    use RefreshDatabase;

    public function test_rotulo_dinamico_e_cache_privado(): void
    {
        Mensagem::factory()->count(2)->publica()->create();   // 2 => PLURAL nos dois cenários (total !== 1)

        $this->get(route('mensagens.index'))->assertOk()->assertSee('mensagens públicas'); // anônimo (plural)

        $this->seed(EstruturaCemaSeeder::class);
        $u = User::factory()->create();
        $u->assignRole('trabalhador');

        $res = $this->actingAs($u)->get(route('mensagens.index'));
        $res->assertOk()->assertSee('mensagens disponíveis a você');   // logado
        $this->assertStringContainsString('private', $res->headers->get('Cache-Control')); // R2
    }
}
```

- [ ] **Passo 3: Rodar e ver falhar**

Run: `docker compose exec -T app php artisan test --filter="MensagemListaVisibilidadeTest|MensagemIndexContadorTest"`
Esperado: FAIL (ainda `publica()` fixo; sem rótulo dinâmico; `$totalPublicas` some).

- [ ] **Passo 4: Trocar o `render()` da `Mensagens\Lista`**

Em `app/Livewire/Mensagens/Lista.php`, adicionar os imports no topo (após `use App\Models\Mensagem;`):

```php
use App\Enums\VisibilidadeMensagem;
use Illuminate\Support\Facades\Auth;
```

Substituir o `render()` (`:81-103`) por:

```php
    public function render()
    {
        $usuario = Auth::user();

        $mensagens = Mensagem::query()
            ->publicado()->visiveisPara($usuario)   // 3B: troca o publica() fixo (I1); anônimo = paridade 2B
            ->with('autores')
            ->when($this->dataDe !== '' && Carbon::hasFormat($this->dataDe, 'Y-m-d'),
                fn (Builder $q) => $q->whereDate('data_recebimento', '>=', $this->dataDe))
            ->when($this->dataAte !== '' && Carbon::hasFormat($this->dataAte, 'Y-m-d'),
                fn (Builder $q) => $q->whereDate('data_recebimento', '<=', $this->dataAte))
            ->when($this->autor === 'sem-assinatura', fn (Builder $q) => $q->whereDoesntHave('autores'))
            ->when($this->autor !== '' && $this->autor !== 'sem-assinatura',
                fn (Builder $q) => $q->whereHas('autores', fn (Builder $a) => $a->where('autores_espirituais.slug', $this->autor)))
            ->when($this->ordenar === 'az',
                fn (Builder $q) => $q->orderBy('titulo'),
                fn (Builder $q) => $q->orderByRaw('data_recebimento IS NULL, data_recebimento '.($this->ordenar === 'antiga' ? 'asc' : 'desc')))
            ->paginate(9);

        // Níveis presentes no resultado (para a legenda; a view só a exibe @auth). null é descartado (B1).
        $niveis = $mensagens->getCollection()
            ->map(fn (Mensagem $m) => $m->visibilidade())
            ->filter()
            ->unique()
            ->sortBy(fn (VisibilidadeMensagem $v) => $v->nivelMinimo() ?? 99)
            ->values();

        return view('livewire.mensagens.lista', [
            'mensagens' => $mensagens,
            'niveis' => $niveis,
            'autores' => AutorEspiritual::whereHas('mensagens', fn (Builder $q) => $q->publicado()->visiveisPara($usuario))->orderBy('nome')->get(['nome', 'slug']),
            'filtrosAtivos' => $this->filtrosAtivos(),
        ]);
    }
```

- [ ] **Passo 5: Trocar o `index()` do `MensagemController`**

Em `app/Http/Controllers/MensagemController.php`, adicionar imports:

```php
use Illuminate\Http\Request;
use Illuminate\Http\Response;
```

Substituir `index()` (`:12-17`) por:

```php
    public function index(Request $request): Response
    {
        $usuario = $request->user();

        $resposta = response()->view('mensagens.index', [
            'total' => Mensagem::publicado()->visiveisPara($usuario)->count(),
            'logado' => $usuario !== null,
        ]);

        if ($usuario !== null) {
            $resposta->header('Cache-Control', 'private, no-store'); // R2: contagem/lista variam por usuário
        }

        return $resposta;
    }
```

- [ ] **Passo 6: Ajustar o contador em `index.blade.php`**

Em `resources/views/mensagens/index.blade.php`, substituir o bloco do contador (`:35-38`):

```blade
                <span>
                    <span class="block font-display text-[22px] font-bold leading-tight">{{ $total }}</span>
                    <span class="block max-w-[180px] text-[12.5px] text-[#c7d0ea]">
                        @if ($logado)
                            {{ $total === 1 ? 'mensagem disponível a você' : 'mensagens disponíveis a você' }}
                        @else
                            {{ $total === 1 ? 'mensagem pública' : 'mensagens públicas' }}
                        @endif
                    </span>
                </span>
```

- [ ] **Passo 7: Rodar e ver passar**

Run: `docker compose exec -T app php artisan test --filter="MensagemListaVisibilidadeTest|MensagemIndexContadorTest"`
Esperado: PASS.

- [ ] **Passo 8: Pint + commit**

```bash
docker compose exec -T app ./vendor/bin/pint app/Livewire/Mensagens/Lista.php app/Http/Controllers/MensagemController.php tests/Feature/Front/MensagemListaVisibilidadeTest.php tests/Feature/Front/MensagemIndexContadorTest.php
git add app/Livewire/Mensagens/Lista.php app/Http/Controllers/MensagemController.php resources/views/mensagens/index.blade.php tests/Feature/Front/MensagemListaVisibilidadeTest.php tests/Feature/Front/MensagemIndexContadorTest.php
git commit -m "feat(camada-4-fatia-3b): lista/contador por visiveisPara(user) + rotulo dinamico + cache privado (I1/I10/R2)"
```

---

### Task 4: Badges `@auth` na lista — legenda + badge/cadeado + barra na cor do nível (I9/I14-lista)

**Files:**
- Modify: `resources/views/livewire/mensagens/lista.blade.php` (legenda `@auth`)
- Modify: `resources/views/components/mensagem/card.blade.php` (barra `@auth` + badge `@auth`)
- Modify: `resources/views/components/mensagem/linha.blade.php` (faixa `@auth` + badge `@auth`)
- Test: `tests/Feature/Front/MensagemBadgesTest.php`

**Interfaces:**
- Consumes: `x-mensagem.selo-nivel`/`legenda-niveis` (Task 2); `Mensagem::visibilidade()` (3A); `$niveis` (Task 3).
- Produces: badges/legenda/barra visíveis **só `@auth`**; anônimo = look 2B (I9); null-safe (I14).

**Contexto:** o padrão canônico de Eventos: badge só `@auth`. O null-guard vive no componente (Task 2); a barra usa `visibilidade()?->cor()` (null-safe). Público logado **também** ganha badge (lista mista, O3).

- [ ] **Passo 1: Escrever o teste dos badges que falha**

`tests/Feature/Front/MensagemBadgesTest.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-20

namespace Tests\Feature\Front;

use App\Livewire\Mensagens\Lista;
use App\Models\Mensagem;
use App\Models\User;
use Database\Seeders\EstruturaCemaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MensagemBadgesTest extends TestCase
{
    use RefreshDatabase;

    public function test_anonimo_sem_legenda_nem_badge(): void
    {
        Mensagem::factory()->publica()->create(['titulo' => 'P']);

        Livewire::test(Lista::class)->assertDontSee('Nível de acesso'); // sem legenda p/ anônimo (I9)
    }

    public function test_logado_ve_legenda_e_badge(): void
    {
        $this->seed(EstruturaCemaSeeder::class);
        $trab = User::factory()->create();
        $trab->assignRole('trabalhador');
        Mensagem::factory()->create(['status' => 'publicado', 'nivel' => 'trabalhadores', 'titulo' => 'Doc Trab']);

        Livewire::actingAs($trab)->test(Lista::class)
            ->assertSee('Nível de acesso')   // legenda @auth
            ->assertSee('Trabalhadores');    // badge do nível
    }

    public function test_null_publicado_admin_lista_sem_badge_sem_500(): void
    {
        // I14/B1: sem o null-guard, o card chamaria null->rotulo() e daria 500 aqui.
        $this->seed(EstruturaCemaSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('administrador');
        Mensagem::factory()->create(['status' => 'publicado', 'nivel' => null, 'titulo' => 'Sem Nivel']);

        Livewire::actingAs($admin)->test(Lista::class)->assertSee('Sem Nivel'); // renderiza (não 500)
    }
}
```

- [ ] **Passo 2: Rodar e ver falhar**

Run: `docker compose exec -T app php artisan test --filter=MensagemBadgesTest`
Esperado: FAIL (`test_logado_ve_legenda_e_badge` — sem legenda/badge).

- [ ] **Passo 3: Legenda `@auth` na `lista.blade.php`**

Em `resources/views/livewire/mensagens/lista.blade.php`, **substituir** o bloco "Mostrando…" (`:68-72`) por (a legenda entra logo abaixo do contador, só logado):

```blade
    @if ($mensagens->total() > 0)
        <div class="mb-5 mt-6 flex flex-wrap items-center justify-between gap-3">
            <p class="text-[13.5px] text-text-muted">
                Mostrando {{ $mensagens->firstItem() }}–{{ $mensagens->lastItem() }} de {{ $mensagens->total() }} {{ $mensagens->total() === 1 ? 'mensagem' : 'mensagens' }}
            </p>
            @auth
                <x-mensagem.legenda-niveis :niveis="$niveis" />
            @endauth
        </div>
    @endif
```

- [ ] **Passo 4: Barra + badge `@auth` no `card.blade.php`**

Em `resources/views/components/mensagem/card.blade.php`, **substituir** a faixa superior (`:18-19`) por (barra na cor do nível a logado; marca para anônimo; null-safe):

```blade
        {{-- Faixa superior: cor do NÍVEL a logado (null-safe); marca para anônimo (look 2B). --}}
        @auth
            <span class="block h-1" style="background:{{ $mensagem->visibilidade()?->cor() ?? '#cbb26a' }}" aria-hidden="true"></span>
        @else
            <span class="block h-1 bg-gradient-to-r from-gold to-primary" aria-hidden="true"></span>
        @endauth
```

E **substituir** a barra de meta (`:28-30`) por (badge de nível à direita do selo de formato, só logado):

```blade
            <div class="flex items-center justify-between gap-2">
                <x-mensagem.selo-formato :formato="$mensagem->formato" />
                @auth <x-mensagem.selo-nivel :visibilidade="$mensagem->visibilidade()" /> @endauth
            </div>
```

- [ ] **Passo 5: Faixa + badge `@auth` no `linha.blade.php`**

Em `resources/views/components/mensagem/linha.blade.php`, **substituir** a faixa lateral (`:11`) por:

```blade
        @auth
            <span class="w-[5px] shrink-0" style="background:{{ $mensagem->visibilidade()?->cor() ?? '#cbb26a' }}" aria-hidden="true"></span>
        @else
            <span class="w-[5px] shrink-0 bg-gradient-to-b from-gold to-primary" aria-hidden="true"></span>
        @endauth
```

E **substituir** o bloco de meta (`:14-19`) por (badge de nível após o selo de formato/data):

```blade
                <div class="mb-1.5 flex flex-wrap items-center gap-2">
                    <x-mensagem.selo-formato :formato="$mensagem->formato" />
                    @auth <x-mensagem.selo-nivel :visibilidade="$mensagem->visibilidade()" /> @endauth
                    @if ($data)
                        <time datetime="{{ $data->toDateString() }}" class="text-[12px] text-text-muted">{{ $data->translatedFormat('d M Y') }}</time>
                    @endif
                </div>
```

- [ ] **Passo 6: Rodar e ver passar**

Run: `docker compose exec -T app php artisan test --filter=MensagemBadgesTest`
Esperado: PASS (inclusive o null-guard do admin, sem 500).

- [ ] **Passo 7: Pint + commit**

```bash
docker compose exec -T app ./vendor/bin/pint tests/Feature/Front/MensagemBadgesTest.php
git add resources/views/livewire/mensagens/lista.blade.php resources/views/components/mensagem/card.blade.php resources/views/components/mensagem/linha.blade.php tests/Feature/Front/MensagemBadgesTest.php
git commit -m "feat(camada-4-fatia-3b): badges/legenda/barra de nivel na lista (so @auth, null-safe) (I9/I14)"
```

---

### Task 5: Barreira do single — `MensagemController@show` + view de barreira + parcial de login · CP-1

**Files:**
- Modify: `app/Http/Controllers/MensagemController.php` (`show`: swap + barreira + 404 real + Cache-Control + `setIntendedUrl`)
- Create: `resources/views/mensagens/barreira.blade.php`
- Create: `resources/views/components/auth/form-login.blade.php`
- Modify: `resources/views/auth/login.blade.php` (usa o parcial)
- Modify: `tests/Feature/Front/MensagemShowTest.php` (I-chg: restrito 404 → barreira)
- Test: `tests/Feature/Front/MensagemBarreiraTest.php`

**Interfaces:**
- Consumes: `Mensagem::publicado()`/`podeSerVistoPor`/`visibilidade` (3A/Task 1); `Configuracao::valor` (existente); `route('login')`/`route('google.redirect')` (Fortify/Socialite).
- Produces: `MensagemController@show(Request, string): Response` (autorizado → `show.blade`; anônimo restrito → barreira `login`; logado sem acesso → barreira `sem-permissao`; inexistente/não-publicada → 404).

**Contexto:** o gate roda **antes** de montar a view — a barreira é **view própria** (corpo/título/OG fora do HTML, I8). `setIntendedUrl(url()->current())` (efeito colateral; retorno descartado de propósito — R4) faz o login (e-mail/senha **e** Google) voltar à mensagem. O modal abre no load via `x-init` (reabre naturalmente após erro — R1). Contato via `Configuracao` (B2, degrada). **404 só** para inexistente/não-publicada (F5).

- [ ] **Passo 1: Escrever o teste da barreira que falha**

`tests/Feature/Front/MensagemBarreiraTest.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-20

namespace Tests\Feature\Front;

use App\Models\Configuracao;
use App\Models\Mensagem;
use App\Models\User;
use Database\Seeders\EstruturaCemaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MensagemBarreiraTest extends TestCase
{
    use RefreshDatabase;

    private function comPapel(string $papel): User
    {
        $this->seed(EstruturaCemaSeeder::class);
        $u = User::factory()->create();
        $u->assignRole($papel);

        return $u->fresh();
    }

    public function test_restrita_anonimo_barreira_login_cega(): void
    {
        Mensagem::factory()->create([
            'status' => 'publicado', 'nivel' => 'diretores', 'slug' => 'seg',
            'titulo' => 'Segredo dos Diretores', 'corpo' => '<p>CorpoSecreto</p>',
        ]);

        $res = $this->get(route('mensagens.show', 'seg'));

        $res->assertOk();                              // 200 (não 404/403 — F5)
        $res->assertSee('Conteúdo restrito');
        $res->assertSee(route('login'), false);        // form de login
        $res->assertSee('name="robots"', false);       // noindex
        $res->assertDontSee('Segredo dos Diretores');  // cego: sem título (I7)
        $res->assertDontSee('CorpoSecreto');           // sem corpo (I8)
        $res->assertDontSee('application/ld+json', false); // sem SEO rico
        $this->assertStringContainsString('/mensagens-mediunicas/seg', session('url.intended'));
    }

    public function test_restrita_logado_sem_acesso_sem_permissao_com_contato(): void
    {
        $u = $this->comPapel('frequentador');
        Configuracao::definir('contato.email', 'contato@cema.org');
        Mensagem::factory()->create(['status' => 'publicado', 'nivel' => 'diretores', 'slug' => 'seg', 'titulo' => 'Segredo']);

        $res = $this->actingAs($u)->get(route('mensagens.show', 'seg'));

        $res->assertOk()->assertSee('não tem permissão')->assertSee('contato@cema.org');
        $res->assertSee('name="robots"', false);
        $res->assertDontSee('Segredo');   // cego
    }

    public function test_sem_permissao_sem_contato_nao_quebra(): void
    {
        $u = $this->comPapel('frequentador');
        Mensagem::factory()->create(['status' => 'publicado', 'nivel' => 'diretores', 'slug' => 'seg']);

        $this->actingAs($u)->get(route('mensagens.show', 'seg'))->assertOk(); // degrada sem contato
    }

    public function test_inexistente_e_pendente_dao_404(): void
    {
        Mensagem::factory()->pendente()->create(['slug' => 'pend']);

        $this->get(route('mensagens.show', 'nao-existe'))->assertNotFound();
        $this->get(route('mensagens.show', 'pend'))->assertNotFound();
    }

    public function test_direcionada_cega_a_nao_destinatario_destinatario_ve(): void
    {
        $dest = $this->comPapel('frequentador');
        $diretor = User::factory()->create();
        $diretor->assignRole('diretor');
        $m = Mensagem::factory()->create([
            'status' => 'publicado', 'nivel' => 'direcionada', 'slug' => 'dir',
            'titulo' => 'Para Voce', 'corpo' => '<p>CorpoDir</p>',
        ]);
        $m->destinatarios()->attach($dest->id);

        // não-destinatário (diretor, alto papel mas não é destinatário) → barreira cega
        $r1 = $this->actingAs($diretor)->get(route('mensagens.show', 'dir'));
        $r1->assertOk()->assertDontSee('Para Voce')->assertDontSee('CorpoDir');

        // destinatário → vê a mensagem (caminho autorizado serve o show.blade)
        $r2 = $this->actingAs($dest)->get(route('mensagens.show', 'dir'));
        $r2->assertOk()->assertSee('Para Voce');
    }
}
```

- [ ] **Passo 2: Atualizar o `MensagemShowTest` (2B) — I-chg (restrito 404 → barreira)**

Em `tests/Feature/Front/MensagemShowTest.php`, **substituir** `test_pendente_e_restrita_dao_404_nunca_403` (`:27-36`) por (a parte "restrita → 404" **migra** para a barreira; pendente/inexistente seguem 404):

```php
    public function test_pendente_e_inexistente_dao_404(): void
    {
        Mensagem::factory()->pendente()->create(['slug' => 'pendente-x']);

        $this->get(route('mensagens.show', 'pendente-x'))->assertNotFound();
        $this->get(route('mensagens.show', 'nao-existe'))->assertNotFound();
        // A mensagem RESTRITA publicada deixou de dar 404: vira barreira-200 cega (ver MensagemBarreiraTest).
    }
```

- [ ] **Passo 3: Rodar e ver falhar**

Run: `docker compose exec -T app php artisan test --filter="MensagemBarreiraTest|MensagemShowTest"`
Esperado: FAIL (a restrita ainda dá 404 — sem barreira).

- [ ] **Passo 4: Reescrever `show()` no `MensagemController`**

Em `app/Http/Controllers/MensagemController.php`, adicionar o import do enum:

```php
use App\Enums\VisibilidadeMensagem;
```

Substituir `show()` (`:19-42`) por:

```php
    public function show(Request $request, string $slug): Response
    {
        $usuario = $request->user();

        // 404 real: inexistente OU não-publicada (status). Ainda NÃO filtra por nível.
        $mensagem = Mensagem::query()->publicado()->where('slug', $slug)->firstOrFail();

        if (! $mensagem->podeSerVistoPor($usuario)) {
            // Grava url.intended na sessão (efeito colateral) ANTES de qualquer login — sobrevive ao regenerate e ao
            // round-trip do Google. NÃO "corrigir" para `return redirect()->...`: o retorno é descartado DE PROPÓSITO.
            redirect()->setIntendedUrl(url()->current());

            return response()
                ->view('mensagens.barreira', ['modo' => $usuario === null ? 'login' : 'sem-permissao'])
                ->header('Cache-Control', 'private, no-store'); // barreira nunca é cacheável por proxy
        }

        // AUTORIZADO: carrega o resto por visiveisPara($usuario) (mesmoDia/relacionadas não vazam).
        $mensagem->load(['autores', 'media', 'relacionadas' => fn ($q) => $q->publicado()->visiveisPara($usuario)]);

        $mesmoDia = $mensagem->data_recebimento
            ? Mensagem::query()->publicado()->visiveisPara($usuario)
                ->whereDate('data_recebimento', $mensagem->data_recebimento->format('Y-m-d'))
                ->where('id', '!=', $mensagem->id)
                ->orderBy('titulo')
                ->get()
            : collect();

        // Nota "Direcionada a você" (Task 6): só se ESTE usuário é destinatário (não a um bypass admin/presidente).
        $ehDestinatario = $usuario !== null
            && $mensagem->visibilidade() === VisibilidadeMensagem::Direcionada
            && $mensagem->destinatarios()->whereKey($usuario->id)->exists();

        $resposta = response()->view('mensagens.show', [
            'mensagem' => $mensagem,
            'mesmoDia' => $mesmoDia,
            'relacionadas' => $mensagem->relacionadas,
            'ehDestinatario' => $ehDestinatario,
        ]);

        if ($mensagem->visibilidade() !== VisibilidadeMensagem::Publico) {
            $resposta->header('Cache-Control', 'private, no-store'); // restrito (ou null) não é cacheável
        }

        return $resposta;
    }
```

- [ ] **Passo 5: Criar o parcial reutilizável `x-auth.form-login`**

`resources/views/components/auth/form-login.blade.php` (extraído de `auth/login.blade.php`, reusado pela tela cheia **e** pelo modal; o `@error('email')` aparece após um login inválido — R1):

```blade
{{-- Thiago Mourão — https://github.com/MouraoBSB — 2026-07-20 --}}
{{-- Formulário de login reutilizável (tela cheia /entrar E modal da barreira). Posta no pipeline Fortify
     (throttle/ativo/rehash herdados). O retorno pós-login usa url.intended (gravado no GET da barreira). --}}
@if (session('status'))
    <p class="mb-4 rounded-md bg-accent/15 px-3 py-2 text-sm text-success" role="status">{{ session('status') }}</p>
@endif

<form method="POST" action="{{ route('login') }}" class="space-y-4">
    @csrf
    <div>
        <label for="email" class="block text-sm font-medium">E-mail</label>
        <input id="email" name="email" type="email" required autofocus autocomplete="email"
               value="{{ old('email') }}" @error('email') aria-invalid="true" aria-describedby="email-erro" @enderror
               class="mt-1 w-full rounded-md border border-border px-3 py-2 focus:border-primary focus:ring-primary">
        @error('email')<p id="email-erro" class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="password" class="block text-sm font-medium">Senha</label>
        <input id="password" name="password" type="password" required autocomplete="current-password"
               class="mt-1 w-full rounded-md border border-border px-3 py-2 focus:border-primary focus:ring-primary">
    </div>
    <label class="flex items-center gap-2 text-sm">
        <input type="checkbox" name="remember" class="rounded border-border text-primary focus:ring-primary"> Lembrar de mim
    </label>
    <button type="submit" class="w-full rounded-pill bg-primary px-4 py-2.5 font-medium text-white hover:bg-primary/90">Entrar</button>
</form>

<a href="{{ route('google.redirect') }}" class="mt-3 flex w-full items-center justify-center gap-2 rounded-pill border border-border px-4 py-2.5 font-medium hover:bg-surface">
    <x-icon.google />
    Entrar com Google
</a>

<div class="mt-4 flex justify-between text-sm">
    <a href="{{ route('password.request') }}" class="text-text-muted underline hover:text-primary">Esqueci a senha</a>
    <a href="{{ route('register') }}" class="text-text-muted underline hover:text-primary">Criar conta</a>
</div>
```

- [ ] **Passo 6: Trocar `auth/login.blade.php` para usar o parcial**

Substituir `resources/views/auth/login.blade.php` inteiro por (a tela cheia passa a consumir o mesmo parcial — fonte única):

```blade
{{-- Thiago Mourão — https://github.com/MouraoBSB — 2026-07-04 --}}
<x-layout.auth titulo="Entrar">
    <x-auth.form-login />
</x-layout.auth>
```

- [ ] **Passo 7: Criar a view de barreira**

`resources/views/mensagens/barreira.blade.php` (genérica e cega — nunca a mensagem-alvo; noindex; modal de login abre no load):

```blade
{{-- Thiago Mourão — https://github.com/MouraoBSB — 2026-07-20 --}}
{{-- Barreira CEGA do single restrito: NUNCA renderiza título/corpo/OG/destinatários da mensagem-alvo.
     $modo = 'login' (anônimo → modal) | 'sem-permissao' (logado sem acesso → contato). --}}
@php
    $emailContato = \App\Models\Configuracao::valor('contato.email');
    $whatsappContato = \App\Models\Configuracao::valor('contato.whatsapp');
    $whatsappDigitos = $whatsappContato ? preg_replace('/\D/', '', $whatsappContato) : null;
@endphp
<x-layout.app title="Conteúdo restrito"
              description="Esta mensagem é reservada. Entre para vê-la, se estiver disponível para você.">
    <x-slot:head>
        <meta name="robots" content="noindex, nofollow">
    </x-slot:head>

    <section class="relative overflow-hidden text-white"
             style="background:radial-gradient(circle at 78% 22%, rgba(110,159,203,0.40), transparent 54%), linear-gradient(135deg,#0b1030 0%,#1a1f4a 48%,#2c2f64 100%);">
        <x-ui.particulas />
        <div class="relative z-[2] mx-auto max-w-[720px] px-6 py-20 text-center">
            <span class="mx-auto grid size-14 place-items-center rounded-2xl bg-white/12" aria-hidden="true">
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#f2a81e" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            </span>
            <h1 class="mt-6 font-display text-3xl font-semibold sm:text-4xl">Conteúdo restrito</h1>

            @if ($modo === 'login')
                <p class="mx-auto mt-4 max-w-md font-light text-[#d7def0]">Esta mensagem é reservada aos membros da Casa. Entre para vê-la — se estiver disponível para você.</p>
                <button type="button" x-data @click="$dispatch('abrir-login')"
                        class="mt-7 inline-flex items-center gap-2 rounded-pill bg-gold px-6 py-3 font-medium text-[#3a3266] transition hover:bg-[#e59e12]">
                    Entrar para ver
                </button>
            @else
                <p class="mx-auto mt-4 max-w-md font-light text-[#d7def0]">Você não tem permissão para ver esta mensagem.</p>
                @if ($emailContato || $whatsappDigitos)
                    <p class="mt-4 text-[13.5px] text-[#c7d0ea]">Em caso de dúvida, entre em contato:</p>
                    <div class="mt-3 flex flex-wrap items-center justify-center gap-3">
                        @if ($emailContato)
                            <a href="mailto:{{ $emailContato }}" class="rounded-pill border border-white/22 bg-white/10 px-5 py-2.5 text-sm transition hover:bg-white/18">{{ $emailContato }}</a>
                        @endif
                        @if ($whatsappDigitos)
                            <a href="https://wa.me/{{ $whatsappDigitos }}" target="_blank" rel="noopener noreferrer" class="rounded-pill border border-white/22 bg-white/10 px-5 py-2.5 text-sm transition hover:bg-white/18">WhatsApp: {{ $whatsappContato }}</a>
                        @endif
                    </div>
                @endif
            @endif
        </div>
    </section>

    @if ($modo === 'login')
        {{-- Modal de login inline: abre no load (x-init) e reabre em qualquer recarga (inclui pós-erro do Fortify — R1). --}}
        <dialog x-data x-init="$el.showModal()" @abrir-login.window="$el.showModal()"
                class="m-auto w-[min(92vw,420px)] rounded-2xl bg-white p-0 text-text-ink shadow-elevated backdrop:bg-black/50">
            <div class="p-6 sm:p-7">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="font-display text-xl font-semibold text-primary">Entrar</h2>
                    <button type="button" @click="$el.closest('dialog').close()" aria-label="Fechar"
                            class="grid size-8 place-items-center rounded-full text-text-muted transition hover:bg-surface">×</button>
                </div>
                <x-auth.form-login />
            </div>
        </dialog>
    @endif
</x-layout.app>
```

- [ ] **Passo 8: Rodar e ver passar**

Run: `docker compose exec -T app php artisan test --filter="MensagemBarreiraTest|MensagemShowTest"`
Esperado: PASS (barreira cega; 404 real; destinatário vê; a atualização I-chg do 2B verde).

- [ ] **Passo 9: Pint + commit** (com `index`/`show` retornando `Response`, o `use Illuminate\Contracts\View\View;` fica **órfão** — o `Pint` `no_unused_imports` o remove; conferir que sumiu)

```bash
docker compose exec -T app ./vendor/bin/pint app/Http/Controllers/MensagemController.php tests/Feature/Front/MensagemBarreiraTest.php tests/Feature/Front/MensagemShowTest.php
git add app/Http/Controllers/MensagemController.php resources/views/mensagens/barreira.blade.php resources/views/components/auth/form-login.blade.php resources/views/auth/login.blade.php tests/Feature/Front/MensagemBarreiraTest.php tests/Feature/Front/MensagemShowTest.php
git commit -m "feat(camada-4-fatia-3b): barreira cega do single (modal login inline, 404 real, setIntendedUrl) (I3-I8/R1/R4)"
```

> **CP-1 (fim da Task 5):** a barreira está verde — o corpo não vaza (view própria), 404 só para inexistente/não-publicada, contato via `Configuracao`. Rodar a **suíte completa** (`docker compose exec -T app php artisan test`) para confirmar que só o I-chg mudou de cor. Só então seguir.

---

### Task 6: Single autorizado — selo dinâmico + `noindex`/OG condicionais + nota "Direcionada a você" (I5/I9/I11/I14/§6.7-nota)

**Files:**
- Modify: `resources/views/mensagens/show.blade.php` (selo dinâmico `@auth`; `noindex`/OG por Público; nota direcionada)
- Test: `tests/Feature/Front/MensagemSingleRicoTest.php`

**Interfaces:**
- Consumes: `x-mensagem.selo-nivel` (Task 2); `Mensagem::visibilidade()` (3A); `$ehDestinatario` (Task 5).
- Produces: single autorizado com badge dinâmico (null-safe); `noindex` quando não-Público; SEO rico só para Público; nota "Direcionada a você" ao destinatário (**sem** PII — F2).

**Contexto:** o viewer aqui **já** é autorizado (senão cai na barreira). O selo hardcoded "Pública" vira dinâmico `@auth`. O gate de `noindex` é `!== Publico` (inclui `null` — desejado). SEO rico (og:image/ld+json) **só** para Público. A nota usa `$ehDestinatario` (calculado no controller — nunca lista destinatários).

- [ ] **Passo 1: Escrever o teste do single rico que falha**

`tests/Feature/Front/MensagemSingleRicoTest.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-20

namespace Tests\Feature\Front;

use App\Models\Mensagem;
use App\Models\User;
use Database\Seeders\EstruturaCemaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MensagemSingleRicoTest extends TestCase
{
    use RefreshDatabase;

    private function comPapel(string $papel): User
    {
        $this->seed(EstruturaCemaSeeder::class);
        $u = User::factory()->create();
        $u->assignRole($papel);

        return $u->fresh();
    }

    public function test_publico_indexavel_sem_badge_ao_anonimo(): void
    {
        Mensagem::factory()->publica()->create(['slug' => 'pub', 'titulo' => 'Luz']);

        $res = $this->get(route('mensagens.show', 'pub'));
        $res->assertOk()->assertSee('Luz');
        $res->assertDontSee('name="robots"', false);   // Público indexável (I11)
        $res->assertDontSee('Nível de acesso');         // sem badge ao anônimo (I9)
    }

    public function test_restrito_autorizado_badge_e_noindex(): void
    {
        $dir = $this->comPapel('diretor');
        Mensagem::factory()->create(['status' => 'publicado', 'nivel' => 'diretores', 'slug' => 'doc', 'titulo' => 'Doc Diretoria']);

        $res = $this->actingAs($dir)->get(route('mensagens.show', 'doc'));
        $res->assertOk()->assertSee('Doc Diretoria')->assertSee('Diretores'); // badge dinâmico
        $res->assertSee('name="robots"', false);                              // noindex (I11)
        $res->assertDontSee('application/ld+json', false);                    // SEO rico só p/ Público
    }

    public function test_null_admin_single_200_sem_selo(): void
    {
        // I14/B1: single de nivel=null visto pelo admin não pode dar 500 no selo do hero.
        $admin = $this->comPapel('administrador');
        Mensagem::factory()->create(['status' => 'publicado', 'nivel' => null, 'slug' => 'n', 'titulo' => 'Sem Nivel']);

        $this->actingAs($admin)->get(route('mensagens.show', 'n'))->assertOk()->assertSee('Sem Nivel');
    }

    public function test_nota_direcionada_ao_destinatario_sem_pii(): void
    {
        $dest = $this->comPapel('frequentador');
        $outro = User::factory()->create(['name' => 'Beltrano Outro']);
        $m = Mensagem::factory()->create(['status' => 'publicado', 'nivel' => 'direcionada', 'slug' => 'dd', 'titulo' => 'Msg']);
        $m->destinatarios()->attach([$dest->id, $outro->id]);

        $res = $this->actingAs($dest)->get(route('mensagens.show', 'dd'));
        $res->assertOk()->assertSee('Direcionada a você');
        $res->assertDontSee('Beltrano Outro');   // F2: nenhum destinatário (PII) no HTML
    }
}
```

- [ ] **Passo 2: Rodar e ver falhar**

Run: `docker compose exec -T app php artisan test --filter=MensagemSingleRicoTest`
Esperado: FAIL (selo hardcoded "Pública"; sem noindex; sem nota).

- [ ] **Passo 3: SEO condicional no slot `head`**

Em `resources/views/mensagens/show.blade.php`, **substituir** o `<x-slot:head>` (`:8-19`) por (canonical sempre; og:image/ld+json só Público; noindex quando não-Público — inclui `null`):

```blade
    <x-slot:head>
        <link rel="canonical" href="{{ $url }}">
        @if ($mensagem->visibilidade() === \App\Enums\VisibilidadeMensagem::Publico)
            @if ($ogImg)<meta property="og:image" content="{{ $ogImg }}">@endif
            <script type="application/ld+json">
            @php echo json_encode(array_filter([
                '@context' => 'https://schema.org', '@type' => 'CreativeWork',
                'name' => $mensagem->titulo, 'url' => $url,
                'datePublished' => $mensagem->data_recebimento?->toDateString(),
                'author' => $mensagem->autores->pluck('nome')->all() ?: null,
            ], fn ($v) => $v !== null && $v !== []), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG); @endphp
            </script>
        @else
            {{-- Restrito (ou nivel=null): fora do índice; sem preview social do conteúdo reservado. --}}
            <meta name="robots" content="noindex, nofollow">
        @endif
    </x-slot:head>
```

- [ ] **Passo 4: Selo de nível dinâmico no hero (`@auth`, null-safe)**

Em `resources/views/mensagens/show.blade.php`, **substituir** o selo hardcoded "Pública" (`:45-47`) por:

```blade
                    @auth
                        <x-mensagem.selo-nivel :visibilidade="$mensagem->visibilidade()" />
                    @endauth
```

- [ ] **Passo 5: Nota "Direcionada a você" (sem PII)**

Em `resources/views/mensagens/show.blade.php`, logo **após** a faixa de contexto (após o `@endif` em `:88`), acrescentar:

```blade
        {{-- Nota "Direcionada a você": só ao destinatário (calculado no controller); SEM lista de destinatários (F2). --}}
        @if ($ehDestinatario ?? false)
            <section class="border-b border-[#ECE6D6] bg-[#FAF8F2]">
                <div class="mx-auto flex max-w-[1100px] items-start gap-3.5 px-6 py-5">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#c19532" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mt-0.5 shrink-0" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    <p class="text-[14.5px] leading-relaxed text-text-secondary"><strong class="font-semibold text-primary">Direcionada a você</strong> — esta mensagem foi endereçada pessoalmente a você nas reuniões mediúnicas da Casa.</p>
                </div>
            </section>
        @endif
```

- [ ] **Passo 6: Rodar e ver passar**

Run: `docker compose exec -T app php artisan test --filter=MensagemSingleRicoTest`
Esperado: PASS. Conferir também que `MensagemShowTest`/`MensagemSeoTest` (2B) seguem verdes:
`docker compose exec -T app php artisan test --filter="MensagemShowTest|MensagemSeoTest"`.

- [ ] **Passo 7: Pint + commit**

```bash
docker compose exec -T app ./vendor/bin/pint tests/Feature/Front/MensagemSingleRicoTest.php
git add resources/views/mensagens/show.blade.php tests/Feature/Front/MensagemSingleRicoTest.php
git commit -m "feat(camada-4-fatia-3b): single rico (selo dinamico, noindex/OG por Publico, nota direcionada sem PII) (I5/I11/I14)"
```

---

### Task 7: Religar o menu "Mensagens Mediúnicas" (+ submenu Autores Espirituais) (I13)

**Files:**
- Modify: `config/navegacao.php` (item Mensagens → ativo + submenu)
- Test: `tests/Feature/Front/MenuMensagensTest.php`

**Interfaces:**
- Consumes: rotas `mensagens.index`/`autores.index` (existentes, `web.php:101/:111`).
- Produces: item de menu ativo com dropdown; **nenhuma** mudança no `header.blade.php` (a config dirige o render).

**Contexto:** o `header.blade.php` já renderiza pai-com-`rota` + submenu pela config (molde Palestras); dar `itens[]` aciona o caret/dropdown. Cache: `config:clear` + `restart app worker` no deploy.

- [ ] **Passo 1: Escrever o teste do menu que falha**

`tests/Feature/Front/MenuMensagensTest.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-20

namespace Tests\Feature\Front;

use App\Models\Mensagem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuMensagensTest extends TestCase
{
    use RefreshDatabase;

    public function test_config_menu_mensagens_ativo_com_submenu_autores(): void
    {
        $item = collect(config('navegacao.menu'))->firstWhere('rotulo', 'Mensagens Mediúnicas');

        $this->assertTrue($item['ativo']);
        $this->assertSame('mensagens.index', $item['rota']);
        $this->assertContains('autores.index', array_column($item['itens'], 'rota'));
    }

    public function test_header_mostra_links_mensagens_e_autores(): void
    {
        Mensagem::factory()->publica()->create(); // garante a página renderizar o layout/header

        $res = $this->get(route('mensagens.index'));
        $res->assertSee(route('mensagens.index'), false);
        $res->assertSee(route('autores.index'), false);
    }
}
```

- [ ] **Passo 2: Rodar e ver falhar**

Run: `docker compose exec -T app php artisan test --filter=MenuMensagensTest`
Esperado: FAIL (item ainda `ativo=false`, sem `rota`, `itens=[]`).

- [ ] **Passo 3: Religar o item em `config/navegacao.php`**

Em `config/navegacao.php`, **substituir** a linha do item (`:24`):

```php
        ['rotulo' => 'Mensagens Mediúnicas', 'ativo' => false, 'itens' => []],
```

por (espelhando o bloco Palestras `:15-23`):

```php
        [
            'rotulo' => 'Mensagens Mediúnicas',
            'rota' => 'mensagens.index',
            'ativo' => true,
            'itens' => [
                ['rotulo' => 'Mensagens Públicas', 'rota' => 'mensagens.index', 'ativo' => true],
                ['rotulo' => 'Autores Espirituais', 'rota' => 'autores.index', 'ativo' => true],
            ],
        ],
```

- [ ] **Passo 4: Rodar e ver passar**

Run: `docker compose exec -T app php artisan test --filter=MenuMensagensTest`
Esperado: PASS. (No dev, `docker compose exec -T app php artisan config:clear` + `docker compose restart app worker` para ver no navegador.)

- [ ] **Passo 5: Commit** (não há PHP com cabeçalho; `Pint` não afeta config array, mas rode por segurança)

```bash
docker compose exec -T app ./vendor/bin/pint config/navegacao.php tests/Feature/Front/MenuMensagensTest.php
git add config/navegacao.php tests/Feature/Front/MenuMensagensTest.php
git commit -m "feat(camada-4-fatia-3b): religar menu Mensagens Mediunicas + submenu Autores Espirituais (I13)"
```

---

### Task 8: Página Filament `ConfiguracoesContato` — canais editáveis no `/admin` (B2)

**Files:**
- Create: `app/Filament/Pages/ConfiguracoesContato.php`
- Create: `resources/views/filament/pages/configuracoes-contato.blade.php`
- Test: `tests/Feature/Filament/ConfiguracoesContatoTest.php`

**Interfaces:**
- Consumes: `App\Models\Configuracao::valor/definir` (existente).
- Produces: Página `/admin/configuracoes-contato` que grava `contato.email`/`contato.whatsapp` (lidos pela barreira, Task 5).

**Contexto:** molde exato de `app/Filament/Pages/ConfiguracoesBlog.php`. É o **único toque da 3B no `/admin`**. Sem migration (o store `configuracoes` já existe).

- [ ] **Passo 1: Escrever o teste da página que falha**

`tests/Feature/Filament/ConfiguracoesContatoTest.php` (molde `ConfiguracoesAgendaTest`):

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-20

namespace Tests\Feature\Filament;

use App\Filament\Pages\ConfiguracoesContato;
use App\Models\Configuracao;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ConfiguracoesContatoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsAdmin();
    }

    public function test_pagina_renderiza(): void
    {
        $this->get('/admin/configuracoes-contato')->assertOk();
    }

    public function test_salva_email_e_whatsapp(): void
    {
        Livewire::test(ConfiguracoesContato::class)
            ->fillForm(['contato_email' => 'contato@cema.org', 'contato_whatsapp' => '+5561999990000'])
            ->call('salvar')
            ->assertHasNoFormErrors();

        $this->assertSame('contato@cema.org', Configuracao::valor('contato.email'));
        $this->assertSame('+5561999990000', Configuracao::valor('contato.whatsapp'));
    }
}
```

- [ ] **Passo 2: Rodar e ver falhar**

Run: `docker compose exec -T app php artisan test --filter=ConfiguracoesContatoTest`
Esperado: FAIL (`ConfiguracoesContato` inexistente).

- [ ] **Passo 3: Criar a Página Filament**

`app/Filament/Pages/ConfiguracoesContato.php` (molde `ConfiguracoesBlog`, com dois `TextInput`):

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-20

namespace App\Filament\Pages;

use App\Models\Configuracao;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ConfiguracoesContato extends Page
{
    protected string $view = 'filament.pages.configuracoes-contato';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static ?string $navigationLabel = 'Configurações de Contato';

    protected static ?string $title = 'Configurações de Contato';

    protected static ?string $slug = 'configuracoes-contato';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'contato_email' => Configuracao::valor('contato.email', ''),
            'contato_whatsapp' => Configuracao::valor('contato.whatsapp', ''),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                TextInput::make('contato_email')
                    ->label('E-mail de contato')
                    ->email()
                    ->maxLength(255)
                    ->helperText('Exibido na tela "sem permissão" das mensagens restritas.'),
                TextInput::make('contato_whatsapp')
                    ->label('WhatsApp (com DDI/DDD)')
                    ->maxLength(30)
                    ->helperText('Ex.: +55 61 99999-0000'),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('salvar')
                ->footer([
                    Actions::make([
                        Action::make('salvar')
                            ->label('Salvar')
                            ->submit('salvar'),
                    ]),
                ]),
        ]);
    }

    public function salvar(): void
    {
        $dados = $this->form->getState();

        Configuracao::definir('contato.email', $dados['contato_email'] ?? '');
        Configuracao::definir('contato.whatsapp', $dados['contato_whatsapp'] ?? '');

        Notification::make()
            ->title('Configurações salvas com sucesso.')
            ->success()
            ->send();
    }
}
```

- [ ] **Passo 4: Criar a view da página**

`resources/views/filament/pages/configuracoes-contato.blade.php` (molde `configuracoes-blog.blade.php` — renderiza o schema de conteúdo):

```blade
<x-filament-panels::page>
    {{ $this->content }}
</x-filament-panels::page>
```

- [ ] **Passo 5: Rodar e ver passar**

Run: `docker compose exec -T app php artisan test --filter=ConfiguracoesContatoTest`
Esperado: PASS. (Se a view do molde real diferir, alinhar `configuracoes-contato.blade.php` a `configuracoes-blog.blade.php`.)

- [ ] **Passo 6: Pint + commit**

```bash
docker compose exec -T app ./vendor/bin/pint app/Filament/Pages/ConfiguracoesContato.php tests/Feature/Filament/ConfiguracoesContatoTest.php
git add app/Filament/Pages/ConfiguracoesContato.php resources/views/filament/pages/configuracoes-contato.blade.php tests/Feature/Filament/ConfiguracoesContatoTest.php
git commit -m "feat(camada-4-fatia-3b): Pagina Filament ConfiguracoesContato (canais editaveis no /admin) (B2)"
```

---

### Task 9: Guarda do sitemap (I12) + regressão + suíte completa · CP-2

**Files:**
- Test: `tests/Feature/Front/MensagemSitemapNaoVazaTest.php` (guarda explícita de que nada restrito entra)
- (nenhuma edição de produção — o sitemap **mantém** `publica()`)

**Interfaces:**
- Consumes: `SitemapController` (intacto — `publica()` / autores ativos com pública).
- Produces: garantia testada de que o `sitemap.xml` segue só o Público (I12) e que a suíte inteira está verde.

**Contexto:** a 3B **não toca** o sitemap. Esta task fixa a garantia (uma restrita publicada **não** entra no `sitemap.xml`) e roda a regressão completa. Confirma o I-chg (só `MensagemShowTest` mudou de cor).

- [ ] **Passo 1: Escrever a guarda do sitemap**

`tests/Feature/Front/MensagemSitemapNaoVazaTest.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-20

namespace Tests\Feature\Front;

use App\Models\Mensagem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MensagemSitemapNaoVazaTest extends TestCase
{
    use RefreshDatabase;

    public function test_sitemap_so_publico_apos_3b(): void
    {
        $pub = Mensagem::factory()->publica()->create(['slug' => 'pub-sm']);
        $rest = Mensagem::factory()->create(['status' => 'publicado', 'nivel' => 'diretores', 'slug' => 'rest-sm']);

        $res = $this->get('/sitemap.xml');

        $res->assertOk();
        $res->assertSee(route('mensagens.show', 'pub-sm'), false);   // pública indexada
        $res->assertDontSee(route('mensagens.show', 'rest-sm'), false); // restrita fora (I12)
    }
}
```

- [ ] **Passo 2: Rodar e ver passar (deve passar de primeira — o sitemap já é só-público)**

Run: `docker compose exec -T app php artisan test --filter=MensagemSitemapNaoVazaTest`
Esperado: PASS (nenhuma mudança de produção — só fixa a garantia).

- [ ] **Passo 3: Suíte completa + Pint**

Run:
```bash
docker compose exec -T app php artisan test
docker compose exec -T app ./vendor/bin/pint --test
```
Esperado: **~1032 + novos** verde; as **únicas** asserções que mudaram de cor são as intencionais (o `MensagemShowTest` restrito 404→barreira — atualizado na Task 5). Se `MensagemShowTest`/`MensagemSeoTest`/`MensagemSitemapTest`/`AutorSitemapTest`/`MensagemUrlCompatTest` reprovarem por outra causa, investigar (não deveriam — a 3B não toca Autores nem o sitemap). Ciência [[flaky-importadorblog-gd-cap-imagem]].

- [ ] **Passo 4: Verificação no localhost (DoD)**

```bash
npm run build                                   # no HOST
docker compose exec -T app php artisan config:clear
docker compose restart app worker
```
Abrir e conferir: (a) `/mensagens-mediunicas` como **anônimo** (look 2B, sem badges) e como **trabalhador** logado (badges/legenda/barra); (b) um single **restrito** como anônimo (barreira + modal de login; testar e-mail/senha e o retorno; Google se a credencial existir no dev) e como **logado-sem-acesso** ("sem permissão" + contato após cadastrá-lo em `/admin/configuracoes-contato`); (c) um single **direcionada** como destinatário (nota "Direcionada a você", sem lista de destinatários); (d) o **menu** "Mensagens Mediúnicas" ativo com submenu. **Verificação visual** dos badges/cadeado/legenda contra os screenshots dos handoffs.

- [ ] **Passo 5: Commit**

```bash
docker compose exec -T app ./vendor/bin/pint tests/Feature/Front/MensagemSitemapNaoVazaTest.php
git add tests/Feature/Front/MensagemSitemapNaoVazaTest.php
git commit -m "test(camada-4-fatia-3b): guarda do sitemap (so publico) + regressao (I12)"
```

> **CP-2 (fim da Task 9):** front rico + barreira + noindex + menu + contato editável, suíte verde, Pint verde, navegado no localhost. **Parar para o passe do PR do Consultor** (CI verde no último commit) + go do dono. **Cutover de PROD** (do dono): `git pull` → `npm run build` (host) → `php artisan optimize:clear` + `restart app worker` → cadastrar os canais em `/admin/configuracoes-contato`. **Direcionada no front (modo "minhas direcionadas") = 3C.**

---
