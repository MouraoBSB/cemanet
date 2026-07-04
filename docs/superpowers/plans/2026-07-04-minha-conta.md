# Minha Conta — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Área logada do membro (Painel + Meu Perfil, com edição do próprio perfil e foto), destino do pós-login, com o header do site refletindo o estado de auth em todas as páginas.

**Architecture:** Rotas `auth`+`/minha-conta` (nome `conta.`) → `ContaController` (SSR) renderiza Painel e Meu Perfil dentro de um layout `x-layout.conta` (que embrulha `x-layout.app` e injeta faixa de saudação + nav). A edição do perfil é um componente Livewire (`Conta\EditarPerfil`) com upload de foto via Spatie Media + cropper client-side. A foto migra de coluna string para Media Library; a lógica de iniciais vira trait compartilhada.

**Tech Stack:** PHP 8.3 · Laravel 13 · Livewire · Blade + Tailwind v4 · Spatie Media Library · Cropper.js · Alpine · MySQL 8 (dev) / SQLite (testes) · Docker.

## Global Constraints

- **PRÉ-REQUISITO:** branch a partir da `main` com as fatias de **autenticação** (PR #8) e **usuários** (PR #7) mescladas (`User` com `HasRoles`/`perfil()`/`setores()`/`cargos()`/`socio`, tabela `perfis_membro`, login/sessão).
- **Comandos rodam no container:** prefixe com `docker exec cema-app`. Testes em SQLite; dev em MySQL. Pint antes de commitar (`docker exec cema-app ./vendor/bin/pint`).
- **🚫 Banco:** só `php artisan migrate` incremental. NUNCA `migrate:fresh`/`refresh`/`reset`/`wipe` nem seed destrutivo (o dev tem 145 usuários, 123 palestras, 44 posts importados).
- **Reaproveitar, não forkar:** layout real (`x-layout.app`, header/footer), `x-palestra.linha`, `x-ui.particulas`, trait `RegistraImagensPadrao`, tokens do `app.css` (@theme). Traduzir o handoff `handoff_minha_conta/` para fidelidade visual — não copiar o `.dc.html`/`support.js`.
- **Foto = Spatie Media** na coleção `foto` com `larguraWeb: 640` (thumb 400×400); a coluna `foto_perfil` é **dropada**.
- **Atuação/papel/sócio são read-only:** NUNCA viram propriedades graváveis do componente Livewire.
- **Cropper quadrado (1:1)**, JS carregado **só na página de edição** (via `@assets` do Livewire), enhancement progressivo (sem JS, o input simples ainda envia). Validação da foto: `['image','mimes:jpg,jpeg,png,webp','max:1024']`.
- **Header logado:** avatar + "Olá, {primeiro nome}" + dropdown Alpine (Minha Conta / Sair); visitante: links reais Entrar/Cadastrar. Vale site-wide.
- **Cabeçalho de autoria** em todo PHP/Blade/JS novo: `// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-04` (Blade: `{{-- ... --}}`). Migrations do repo NÃO usam cabeçalho.
- **Brazilian Portuguese** em identificadores de domínio, labels e mensagens.

Spec: `docs/superpowers/specs/2026-07-04-minha-conta-design.md`.

---

## File Structure

- `app/Models/Concerns/TemIniciais.php` — trait: accessor `iniciais` + `nomeParaIniciais()` sobrescrevível.
- `app/Models/User.php` — usa `TemIniciais`, sobrescreve `nomeParaIniciais()` → `name`.
- `app/Models/Palestrante.php` — usa `TemIniciais`, remove o accessor `iniciais` local.
- `app/Models/PerfilMembro.php` — `HasMedia` + `RegistraImagensPadrao`, coleção `foto`, `fotoUrl`/`fotoThumbUrl`, remove `foto_perfil` do fillable.
- `database/migrations/2026_07_04_000002_drop_foto_perfil_from_perfis_membro_table.php` — dropa a coluna.
- `config/fortify.php` — `home` → `/minha-conta`.
- `routes/web.php` — grupo `conta.*`.
- `app/Http/Controllers/ContaController.php` — `painel()`, `perfil()`.
- `resources/views/components/layout/conta.blade.php` — layout logado (embrulha `x-layout.app`).
- `resources/views/components/conta/saudacao.blade.php` — faixa de saudação.
- `resources/views/components/conta/nav.blade.php` — nav da conta (sidebar/chips).
- `resources/views/conta/painel.blade.php`, `resources/views/conta/perfil.blade.php` — páginas SSR.
- `app/Livewire/Conta/EditarPerfil.php` + `resources/views/livewire/conta/editar-perfil.blade.php` — form de edição.
- `resources/views/components/layout/header.blade.php` — auth-aware (modificado).
- `resources/js/cropper-perfil.js` — Alpine data do cropper (+ `cropperjs` no `package.json`, entrada no `vite.config.js`).
- `tests/Feature/Conta/*`, `tests/Unit/TemIniciaisTest.php`.

---

## Task 1: Trait `TemIniciais` (DRY em User + Palestrante)

**Files:**
- Create: `app/Models/Concerns/TemIniciais.php`, `tests/Unit/TemIniciaisTest.php`
- Modify: `app/Models/User.php`, `app/Models/Palestrante.php`

**Interfaces:**
- Produces: accessor `iniciais` (string) em qualquer model que usar a trait; `nomeParaIniciais(): string` (default `$this->nome`, sobrescrevível). `User::iniciais` (de `name`), `Palestrante::iniciais` (de `nome`, saída inalterada).

- [ ] **Step 1: Teste do trait**

Create `tests/Unit/TemIniciaisTest.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-04

namespace Tests\Unit;

use App\Models\Palestrante;
use App\Models\User;
use Tests\TestCase;

class TemIniciaisTest extends TestCase
{
    public function test_iniciais_do_usuario_de_name(): void
    {
        $this->assertSame('TM', (new User(['name' => 'Thiago Mourão']))->iniciais);
        $this->assertSame('A', (new User(['name' => 'Ana']))->iniciais);
        $this->assertSame('?', (new User(['name' => '']))->iniciais);
        $this->assertSame('MC', (new User(['name' => '  maria   clara  ']))->iniciais);
    }

    public function test_iniciais_do_palestrante_de_nome_inalterada(): void
    {
        $p = new Palestrante(['nome' => 'João da Silva']);
        $this->assertSame('JS', $p->iniciais);
    }
}
```

- [ ] **Step 2: Rodar (deve falhar)**

Run: `docker exec cema-app php artisan test --filter=TemIniciaisTest`
Expected: FAIL (`User::iniciais` não existe ainda).

- [ ] **Step 3: Criar a trait**

Create `app/Models/Concerns/TemIniciais.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-04

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * Iniciais (1ª letra das 2 primeiras palavras do nome, maiúsculas, fallback '?').
 * Fallback de avatar reutilizável. O model define a fonte do nome via nomeParaIniciais().
 */
trait TemIniciais
{
    protected function iniciais(): Attribute
    {
        return Attribute::get(function (): string {
            $palavras = preg_split('/\s+/', trim($this->nomeParaIniciais()), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $letras = array_map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)), array_slice($palavras, 0, 2));

            return $letras === [] ? '?' : implode('', $letras);
        });
    }

    protected function nomeParaIniciais(): string
    {
        return (string) $this->nome;
    }
}
```

- [ ] **Step 4: Aplicar em User e Palestrante**

Modify `app/Models/User.php` — adicionar `use App\Models\Concerns\TemIniciais;` no topo, incluir `TemIniciais` na lista de traits (`use HasFactory, HasRoles, Notifiable, TemIniciais;`) e adicionar o override (junto aos outros métodos):

```php
    protected function nomeParaIniciais(): string
    {
        return (string) $this->name;
    }
```

Modify `app/Models/Palestrante.php` — adicionar `use App\Models\Concerns\TemIniciais;` no topo, incluir na lista de traits (`use HasFactory, InteractsWithMedia, RegistraImagensPadrao, TemIniciais;`) e **remover** o accessor `iniciais()` local (as ~9 linhas do método `protected function iniciais(): Attribute {...}`). O default `nomeParaIniciais()` já usa `$this->nome`.

- [ ] **Step 5: Rodar (deve passar) + regressão do Palestrante**

Run: `docker exec cema-app php artisan test --filter=TemIniciaisTest`
Expected: PASS.

Run: `docker exec cema-app php artisan test --filter=Palestrante`
Expected: PASS (os testes existentes que exercitam a foto/iniciais/card do Palestrante permanecem verdes — mesma saída).

- [ ] **Step 6: Pint + commit**

```bash
docker exec cema-app ./vendor/bin/pint app/Models/Concerns/TemIniciais.php app/Models/User.php app/Models/Palestrante.php tests/Unit/TemIniciaisTest.php
git add app/Models/Concerns/TemIniciais.php app/Models/User.php app/Models/Palestrante.php tests/Unit/TemIniciaisTest.php
git commit -m "refactor(models): trait TemIniciais compartilhada (User + Palestrante)"
```

---

## Task 2: `PerfilMembro` → Spatie Media (foto) + drop `foto_perfil`

**Files:**
- Create: `database/migrations/2026_07_04_000002_drop_foto_perfil_from_perfis_membro_table.php`, `tests/Feature/Conta/PerfilFotoTest.php`
- Modify: `app/Models/PerfilMembro.php`

**Interfaces:**
- Consumes: trait `RegistraImagensPadrao` (`registrarColecaoImagem(string, bool, int $larguraWeb, int $ladoThumb)`).
- Produces: `PerfilMembro::COLECAO_FOTO = 'foto'`; `PerfilMembro implements HasMedia`; accessors `fotoUrl` / `fotoThumbUrl` (`?string`). Coluna `foto_perfil` removida.

- [ ] **Step 1: Teste da foto + coluna removida**

Create `tests/Feature/Conta/PerfilFotoTest.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-04

namespace Tests\Feature\Conta;

use App\Models\PerfilMembro;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PerfilFotoTest extends TestCase
{
    use RefreshDatabase;

    public function test_coluna_foto_perfil_foi_removida(): void
    {
        $this->assertFalse(Schema::hasColumn('perfis_membro', 'foto_perfil'));
    }

    public function test_foto_armazena_via_media_library_com_conversoes(): void
    {
        Storage::fake('public');
        $perfil = User::factory()->create()->perfil()->create([]);

        $perfil->addMedia(UploadedFile::fake()->image('foto.jpg', 800, 800))
            ->toMediaCollection(PerfilMembro::COLECAO_FOTO);

        $this->assertNotNull($perfil->fresh()->foto_url);
        $this->assertNotNull($perfil->fresh()->foto_thumb_url);
    }
}
```

- [ ] **Step 2: Rodar (deve falhar)**

Run: `docker exec cema-app php artisan test --filter=PerfilFotoTest`
Expected: FAIL (coluna ainda existe; `foto_url` ausente; `PerfilMembro` não é `HasMedia`).

- [ ] **Step 3: Migration incremental (drop da coluna)**

Create `database/migrations/2026_07_04_000002_drop_foto_perfil_from_perfis_membro_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('perfis_membro', function (Blueprint $table) {
            $table->dropColumn('foto_perfil');
        });
    }

    public function down(): void
    {
        Schema::table('perfis_membro', function (Blueprint $table) {
            $table->string('foto_perfil')->nullable();
        });
    }
};
```

- [ ] **Step 4: Reescrever `PerfilMembro`**

Replace `app/Models/PerfilMembro.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-03

namespace App\Models;

use App\Models\Concerns\RegistraImagensPadrao;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class PerfilMembro extends Model implements HasMedia
{
    use InteractsWithMedia, RegistraImagensPadrao;

    public const COLECAO_FOTO = 'foto';

    protected $table = 'perfis_membro';

    protected $fillable = [
        'user_id', 'whatsapp', 'whatsapp_publico', 'data_nascimento', 'endereco',
    ];

    protected function casts(): array
    {
        return ['whatsapp_publico' => 'boolean', 'data_nascimento' => 'date'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function registerMediaCollections(): void
    {
        // Avatar do membro: WebP web ≤640px + thumb quadrado 400×400 (o avatar nunca aparece em 1600px).
        $this->registrarColecaoImagem(self::COLECAO_FOTO, larguraWeb: 640);
    }

    protected function fotoUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->getFirstMediaUrl(self::COLECAO_FOTO, 'web') ?: null);
    }

    protected function fotoThumbUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->getFirstMediaUrl(self::COLECAO_FOTO, 'thumb') ?: null);
    }
}
```

- [ ] **Step 5: Migrar + rodar (deve passar)**

Run: `docker exec cema-app php artisan migrate`
Expected: aplica a migration `drop_foto_perfil...`. NÃO usar `--fresh`.

Run: `docker exec cema-app php artisan test --filter=PerfilFotoTest`
Expected: PASS (2 testes).

- [ ] **Step 6: Pint + commit**

```bash
docker exec cema-app ./vendor/bin/pint app/Models/PerfilMembro.php tests/Feature/Conta/PerfilFotoTest.php
git add app/Models/PerfilMembro.php database/migrations/2026_07_04_000002_drop_foto_perfil_from_perfis_membro_table.php tests/Feature/Conta/PerfilFotoTest.php
git commit -m "feat(conta): foto do membro via Spatie Media (larguraWeb 640) + drop foto_perfil"
```

---

## Task 3: Rotas + home pós-login + `ContaController` + casca `x-layout.conta`

**Files:**
- Modify: `config/fortify.php`, `routes/web.php`
- Create: `app/Http/Controllers/ContaController.php`, `resources/views/components/layout/conta.blade.php`, `resources/views/components/conta/saudacao.blade.php`, `resources/views/components/conta/nav.blade.php`, `resources/views/conta/painel.blade.php`, `resources/views/conta/perfil.blade.php`, `tests/Feature/Conta/AcessoContaTest.php`

**Interfaces:**
- Consumes: `User::iniciais` (Task 1), `PerfilMembro` (Task 2), `x-layout.app`, `x-ui.particulas`.
- Produces: rotas nomeadas `conta.painel` (`/minha-conta`), `conta.perfil` (`/minha-conta/perfil`); `ContaController@painel`/`@perfil`; componentes `<x-layout.conta :titulo :ativo>`, `<x-conta.saudacao>`, `<x-conta.nav :ativo>`. Fortify `home` = `/minha-conta`.

- [ ] **Step 1: Teste de acesso + pós-login**

Create `tests/Feature/Conta/AcessoContaTest.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-04

namespace Tests\Feature\Conta;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AcessoContaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('frequentador', 'web');
    }

    public function test_guest_e_redirecionado_para_login(): void
    {
        $this->get('/minha-conta')->assertRedirect('/entrar');
        $this->get('/minha-conta/perfil')->assertRedirect('/entrar');
    }

    public function test_membro_logado_ve_a_saudacao(): void
    {
        $user = User::factory()->create(['name' => 'Maria Clara', 'ativo' => true]);
        $user->assignRole('frequentador');

        $this->actingAs($user)->get('/minha-conta')->assertOk()->assertSee('Maria Clara');
        $this->actingAs($user)->get('/minha-conta/perfil')->assertOk();
    }

    public function test_pos_login_vai_para_minha_conta(): void
    {
        $user = User::factory()->create(['password' => Hash::make('segredo123'), 'ativo' => true]);

        $this->post('/entrar', ['email' => $user->email, 'password' => 'segredo123'])
            ->assertRedirect('/minha-conta');
    }
}
```

- [ ] **Step 2: Rodar (deve falhar)**

Run: `docker exec cema-app php artisan test --filter=AcessoContaTest`
Expected: FAIL (rota `/minha-conta` inexistente; login redireciona para `/`).

- [ ] **Step 3: Fortify home → `/minha-conta`**

Modify `config/fortify.php` — trocar `'home' => '/',` por `'home' => '/minha-conta',`.

- [ ] **Step 4: Rotas**

Modify `routes/web.php` — adicionar o import no topo (`use App\Http\Controllers\ContaController;`) e o grupo logo **após** o grupo `guest` (e antes das rotas públicas de conteúdo):

```php
// Área do membro (self-service) — sob autenticação.
Route::middleware('auth')->prefix('minha-conta')->name('conta.')->group(function () {
    Route::get('/', [ContaController::class, 'painel'])->name('painel');
    Route::get('/perfil', [ContaController::class, 'perfil'])->name('perfil');
});
```

- [ ] **Step 5: `ContaController`**

Create `app/Http/Controllers/ContaController.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-04

namespace App\Http\Controllers;

use App\Models\Palestra;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;

class ContaController extends Controller
{
    public function painel(): View
    {
        auth()->user()->perfil()->firstOrCreate([]);

        $proximas = Palestra::publicado()
            ->whereNotNull('data_da_palestra')
            ->where('data_da_palestra', '>=', Carbon::today())
            ->with(['palestrantesAtivos', 'assuntos'])
            ->orderBy('data_da_palestra')
            ->take(4)
            ->get();

        return view('conta.painel', compact('proximas'));
    }

    public function perfil(): View
    {
        $user = auth()->user();
        $perfil = $user->perfil()->firstOrCreate([]);
        $user->load(['setores', 'cargos', 'roles']);

        return view('conta.perfil', compact('user', 'perfil'));
    }
}
```

- [ ] **Step 6: Faixa de saudação**

Create `resources/views/components/conta/saudacao.blade.php`:

```blade
{{-- Thiago Mourão — https://github.com/MouraoBSB — 2026-07-04 --}}
@php
    $user = auth()->user();
    $perfil = $user->perfil;
    $papel = $user->roles->first()?->name;
@endphp
<section class="relative overflow-hidden bg-gradient-to-br from-primary via-[#3c3468] to-footer-bg text-white">
    <x-ui.particulas />
    <div class="relative mx-auto flex max-w-[1240px] items-center gap-4 px-6 py-8">
        @if ($perfil?->foto_thumb_url)
            <img src="{{ $perfil->foto_thumb_url }}" alt="" class="size-16 rounded-full object-cover ring-2 ring-gold">
        @else
            <span class="flex size-16 items-center justify-center rounded-full bg-gold/20 text-xl font-semibold text-gold ring-2 ring-gold">{{ $user->iniciais }}</span>
        @endif
        <div>
            <p class="font-mono text-xs uppercase tracking-[0.12em] text-white/70">Olá,</p>
            <h1 class="font-display text-2xl font-bold leading-tight">{{ $user->name }}</h1>
            <div class="mt-1 flex flex-wrap items-center gap-2 text-sm">
                @if ($papel)
                    <span class="rounded-pill bg-white/15 px-3 py-0.5 capitalize">{{ $papel }}</span>
                @endif
                @if ($user->socio)
                    <span class="rounded-pill bg-gold/90 px-3 py-0.5 font-medium text-primary">Sócio</span>
                @endif
            </div>
        </div>
    </div>
</section>
```

- [ ] **Step 7: Nav da conta**

Create `resources/views/components/conta/nav.blade.php`:

```blade
{{-- Thiago Mourão — https://github.com/MouraoBSB — 2026-07-04 --}}
@props(['ativo' => 'painel'])
@php
    $itens = [
        ['chave' => 'painel', 'rotulo' => 'Painel', 'rota' => 'conta.painel'],
        ['chave' => 'perfil', 'rotulo' => 'Meu Perfil', 'rota' => 'conta.perfil'],
    ];
@endphp
<nav aria-label="Navegação da conta"
     class="flex gap-2 overflow-x-auto pb-1 desktop-sm:flex-col desktop-sm:gap-1 desktop-sm:overflow-visible">
    @foreach ($itens as $item)
        @php($atual = $ativo === $item['chave'])
        <a href="{{ route($item['rota']) }}" @if ($atual) aria-current="page" @endif
           class="shrink-0 rounded-pill px-4 py-2 font-ui text-sm font-medium transition desktop-sm:rounded-md
                  {{ $atual ? 'bg-primary text-white' : 'bg-surface text-text hover:bg-border-muted' }}">
            {{ $item['rotulo'] }}
        </a>
    @endforeach
</nav>
```

- [ ] **Step 8: Layout `x-layout.conta`**

Create `resources/views/components/layout/conta.blade.php`:

```blade
{{-- Thiago Mourão — https://github.com/MouraoBSB — 2026-07-04 --}}
@props(['titulo' => null, 'ativo' => 'painel'])
<x-layout.app :title="$titulo">
    <x-conta.saudacao />
    <div class="mx-auto grid max-w-[1240px] gap-6 px-6 py-8 desktop-sm:grid-cols-[220px_1fr]">
        <aside class="desktop-sm:sticky desktop-sm:top-24 desktop-sm:self-start">
            <x-conta.nav :ativo="$ativo" />
        </aside>
        <div>{{ $slot }}</div>
    </div>
</x-layout.app>
```

- [ ] **Step 9: Páginas mínimas (Painel + Perfil)**

Create `resources/views/conta/painel.blade.php`:

```blade
{{-- Thiago Mourão — https://github.com/MouraoBSB — 2026-07-04 --}}
<x-layout.conta titulo="Painel" ativo="painel">
    <div class="rounded-lg bg-white p-6 shadow-card">
        <h2 class="font-display text-lg font-semibold text-primary">Bem-vindo(a) de volta!</h2>
        <p class="mt-1 text-sm text-text-secondary">Este é o seu espaço no CEMA.</p>
    </div>
</x-layout.conta>
```

Create `resources/views/conta/perfil.blade.php`:

```blade
{{-- Thiago Mourão — https://github.com/MouraoBSB — 2026-07-04 --}}
<x-layout.conta titulo="Meu Perfil" ativo="perfil">
    <div class="rounded-lg bg-white p-6 shadow-card">
        <h2 class="font-display text-lg font-semibold text-primary">Meu Perfil</h2>
    </div>
</x-layout.conta>
```

- [ ] **Step 10: Rodar (deve passar) + Pint + commit**

Run: `docker exec cema-app php artisan test --filter=AcessoContaTest`
Expected: PASS (3 testes).

```bash
docker exec cema-app ./vendor/bin/pint app/Http/Controllers/ContaController.php config/fortify.php routes/web.php tests/Feature/Conta/AcessoContaTest.php
git add app/Http/Controllers/ContaController.php config/fortify.php routes/web.php resources/views/components/layout/conta.blade.php resources/views/components/conta resources/views/conta tests/Feature/Conta/AcessoContaTest.php
git commit -m "feat(conta): rotas + home pos-login + casca (layout, saudacao, nav) da area do membro"
```

---

## Task 4: Painel — próximas palestras + atalhos

**Files:**
- Modify: `resources/views/conta/painel.blade.php`
- Test: `tests/Feature/Conta/PainelTest.php`

**Interfaces:**
- Consumes: `$proximas` (Collection de `Palestra`, do controller), `<x-palestra.linha :palestra>`.

- [ ] **Step 1: (referência) o componente `<x-palestra.linha>`**

Verificado: `resources/views/components/palestra/linha.blade.php` é `@props(['palestra'])`, renderiza `$palestra->titulo`, a data, o tema (`assuntos->first`) e o palestrante (`palestrantesAtivos->first`) — todos guardados por `@if` (lida bem com relações vazias) — e linka para `route('palestras.show', $palestra->slug)`. O controller (Task 3) já faz eager-load de `palestrantesAtivos`/`assuntos`. Nenhuma adaptação necessária.

- [ ] **Step 2: Teste do Painel**

Create `tests/Feature/Conta/PainelTest.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-04

namespace Tests\Feature\Conta;

use App\Models\Palestra;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PainelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('frequentador', 'web');
    }

    private function membro(): User
    {
        $u = User::factory()->create(['ativo' => true]);
        $u->assignRole('frequentador');

        return $u;
    }

    public function test_painel_lista_proxima_palestra_de_hoje_ate_o_fim_do_dia(): void
    {
        Palestra::factory()->create([
            'titulo' => 'Palestra de Hoje',
            'data_da_palestra' => Carbon::today()->addHours(6)->subMinutes(1), // já passou o horário, mas é hoje
            'status' => Palestra::STATUS_PUBLICADO,
        ]);

        $this->actingAs($this->membro())->get('/minha-conta')
            ->assertOk()->assertSee('Palestra de Hoje');
    }

    public function test_painel_estado_vazio_sem_proximas(): void
    {
        Palestra::factory()->create([
            'data_da_palestra' => Carbon::yesterday(),
            'status' => Palestra::STATUS_PUBLICADO,
        ]);

        $this->actingAs($this->membro())->get('/minha-conta')
            ->assertOk()->assertSee('Nenhuma palestra agendada');
    }
}
```

- [ ] **Step 3: Rodar (deve falhar)**

Run: `docker exec cema-app php artisan test --filter=PainelTest`
Expected: FAIL (o Painel ainda é o placeholder do Task 3).

- [ ] **Step 4: Preencher o Painel**

Replace `resources/views/conta/painel.blade.php`:

```blade
{{-- Thiago Mourão — https://github.com/MouraoBSB — 2026-07-04 --}}
@php
    $atalhos = [
        ['rotulo' => 'Calendário de palestras', 'rota' => 'palestras.calendario'],
        ['rotulo' => 'Palestras', 'rota' => 'palestras.index'],
        ['rotulo' => 'Sementeira de Luz', 'rota' => 'blog.index'],
        ['rotulo' => 'Agenda Reforma Íntima', 'rota' => 'agenda.index'],
    ];
@endphp
<x-layout.conta titulo="Painel" ativo="painel">
    <div class="space-y-6">
        <div class="rounded-lg bg-white p-6 shadow-card">
            <h2 class="font-display text-lg font-semibold text-primary">Bem-vindo(a) de volta!</h2>
            <p class="mt-1 text-sm text-text-secondary">Este é o seu espaço no CEMA.</p>
        </div>

        <section class="rounded-lg bg-white p-6 shadow-card">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="font-display text-lg font-semibold text-primary">Próximas palestras</h2>
                <a href="{{ route('palestras.calendario') }}" class="text-sm font-medium text-secondary hover:text-primary">Ver todas →</a>
            </div>

            @forelse ($proximas as $palestra)
                <x-palestra.linha :palestra="$palestra" wire:key="proxima-{{ $palestra->id }}" />
            @empty
                <p class="rounded-md bg-surface px-4 py-6 text-center text-sm text-text-muted">Nenhuma palestra agendada no momento.</p>
            @endforelse
        </section>

        <section>
            <h2 class="mb-3 font-display text-lg font-semibold text-primary">Atalhos rápidos</h2>
            <div class="grid grid-cols-2 gap-3 tablet:grid-cols-4">
                @foreach ($atalhos as $atalho)
                    <a href="{{ route($atalho['rota']) }}"
                       class="rounded-lg bg-white p-4 text-center text-sm font-medium text-text shadow-card transition hover:shadow-elevated hover:text-primary">
                        {{ $atalho['rotulo'] }}
                    </a>
                @endforeach
            </div>
        </section>
    </div>
</x-layout.conta>
```

> `<x-palestra.linha>` renderiza o `titulo` e guarda palestrante/tema com `@if` (verificado no Step 1) — funciona numa coluna estreita sem adaptação.

- [ ] **Step 5: Rodar (deve passar) + Pint + commit**

Run: `docker exec cema-app php artisan test --filter=PainelTest`
Expected: PASS (2 testes).

```bash
docker exec cema-app ./vendor/bin/pint
git add resources/views/conta/painel.blade.php tests/Feature/Conta/PainelTest.php
git commit -m "feat(conta): Painel com proximas palestras (>= hoje) + atalhos rapidos"
```

---

## Task 5: Meu Perfil — visualização (SSR)

**Files:**
- Modify: `resources/views/conta/perfil.blade.php`
- Test: `tests/Feature/Conta/PerfilViewTest.php`

**Interfaces:**
- Consumes: `$user` (com `setores`/`cargos`/`roles` carregados), `$perfil`.
- Produces: o botão "Editar perfil" com `x-data="{ editando: false }"` e a região de edição (preenchida no Task 7).

- [ ] **Step 1: Teste da visualização**

Create `tests/Feature/Conta/PerfilViewTest.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-04

namespace Tests\Feature\Conta;

use App\Models\Setor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PerfilViewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('frequentador', 'web');
    }

    public function test_frequentador_sem_setor_ve_linha_discreta_e_papel(): void
    {
        $user = User::factory()->create(['name' => 'Ana Sem Setor', 'ativo' => true]);
        $user->assignRole('frequentador');
        $user->perfil()->create(['endereco' => 'Rua X, 100', 'whatsapp' => '61999998888']);

        $this->actingAs($user)->get('/minha-conta/perfil')
            ->assertOk()
            ->assertSee('Você ainda não atua em um setor da casa')
            ->assertSee('frequentador')
            ->assertSee('Rua X, 100')
            ->assertSee('apenas administrativo'); // selo do endereço
    }

    public function test_membro_com_setor_ve_o_chip_do_setor(): void
    {
        $user = User::factory()->create(['ativo' => true]);
        $user->assignRole('frequentador');
        $user->perfil()->create([]);
        $setor = Setor::create(['nome' => 'Atendimento Fraterno', 'slug' => 'atendimento-fraterno', 'ativo' => true]);
        $user->setores()->attach($setor->id, ['funcao' => 'coordenador']);

        $this->actingAs($user)->get('/minha-conta/perfil')
            ->assertOk()
            ->assertSee('Atendimento Fraterno')
            ->assertSee('Coordenador');
    }
}
```

> Verificado: `Setor` tem `$fillable = ['departamento_id','nome','slug','provisorio','ativo']` (`departamento_id` nullable; `provisorio`/`ativo` com default) — `nome`/`slug`/`ativo` bastam.

- [ ] **Step 2: Rodar (deve falhar)**

Run: `docker exec cema-app php artisan test --filter=PerfilViewTest`
Expected: FAIL (perfil ainda é placeholder).

- [ ] **Step 3: Preencher a visualização**

Replace `resources/views/conta/perfil.blade.php`:

```blade
{{-- Thiago Mourão — https://github.com/MouraoBSB — 2026-07-04 --}}
@php
    $papel = $user->roles->first()?->name;
    $nivel = $user->roles->first()?->nivel;
@endphp
<x-layout.conta titulo="Meu Perfil" ativo="perfil">
    <div x-data="{ editando: false }" class="space-y-6">
        {{-- Cabeçalho da seção --}}
        <div class="flex items-center justify-between" x-show="!editando">
            <h2 class="font-display text-xl font-semibold text-primary">Meu Perfil</h2>
            <button type="button" @click="editando = true"
                    class="rounded-pill bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary/90">Editar perfil</button>
        </div>

        {{-- VISUALIZAÇÃO --}}
        <div x-show="!editando" class="space-y-6">
            <section class="rounded-lg bg-white p-6 shadow-card">
                <h3 class="mb-4 font-display font-semibold text-primary">Dados pessoais</h3>
                <dl class="grid gap-4 sm:grid-cols-2">
                    <div><dt class="text-xs uppercase tracking-wide text-text-muted">Nome público</dt><dd class="mt-0.5 text-text">{{ $user->name }}</dd></div>
                    <div><dt class="text-xs uppercase tracking-wide text-text-muted">Data de nascimento</dt><dd class="mt-0.5 text-text">{{ $perfil->data_nascimento?->format('d/m/Y') ?? '—' }}</dd></div>
                    <div class="sm:col-span-2">
                        <dt class="text-xs uppercase tracking-wide text-text-muted">Endereço <span class="ml-1 rounded bg-surface px-1.5 py-0.5 text-[10px] font-normal normal-case text-text-muted">não é público — apenas administrativo</span></dt>
                        <dd class="mt-0.5 text-text">{{ $perfil->endereco ?: '—' }}</dd>
                    </div>
                </dl>
            </section>

            <section class="rounded-lg bg-white p-6 shadow-card">
                <h3 class="mb-4 font-display font-semibold text-primary">Contato</h3>
                <dt class="text-xs uppercase tracking-wide text-text-muted">WhatsApp
                    <span class="ml-1 rounded bg-surface px-1.5 py-0.5 text-[10px] font-normal normal-case text-text-muted">{{ $perfil->whatsapp_publico ? 'visível para outros membros' : 'visível só para a casa' }}</span>
                </dt>
                <dd class="mt-0.5 text-text">{{ $perfil->whatsapp ?: '—' }}</dd>
            </section>

            <section class="rounded-lg bg-surface p-6 shadow-card ring-1 ring-border">
                <div class="mb-4 flex items-center gap-2">
                    <h3 class="font-display font-semibold text-primary">Minha atuação no CEMA</h3>
                    <span class="rounded-pill bg-border-muted px-2.5 py-0.5 text-[11px] font-medium text-text-secondary">Gerido pela casa</span>
                </div>

                <p class="mb-1 text-xs uppercase tracking-wide text-text-muted">Áreas</p>
                @if ($user->setores->isNotEmpty())
                    <div class="mb-4 flex flex-wrap gap-2">
                        @foreach ($user->setores as $setor)
                            <span class="rounded-pill bg-white px-3 py-1 text-sm text-text ring-1 ring-border">
                                {{ $setor->nome }}@if ($setor->pivot->funcao === 'coordenador') · <span class="font-medium text-primary">Coordenador</span>@endif
                            </span>
                        @endforeach
                    </div>
                @else
                    <p class="mb-4 text-sm text-text-muted">Você ainda não atua em um setor da casa.</p>
                @endif

                @if ($user->cargos->isNotEmpty())
                    <p class="mb-1 text-xs uppercase tracking-wide text-text-muted">Cargos</p>
                    <div class="mb-4 flex flex-wrap gap-2">
                        @foreach ($user->cargos as $cargo)
                            <span class="rounded-pill bg-white px-3 py-1 text-sm text-text ring-1 ring-border">{{ $cargo->nome }}</span>
                        @endforeach
                    </div>
                @endif

                <div class="flex flex-wrap gap-6 text-sm">
                    <div><span class="text-text-muted">Papel:</span> <span class="capitalize text-text">{{ $papel ?? '—' }}</span>@if ($nivel) <span class="text-text-muted">(nível {{ $nivel }})</span>@endif</div>
                    <div><span class="text-text-muted">Sócio:</span> <span class="text-text">{{ $user->socio ? 'Sim' : 'Não' }}</span></div>
                </div>
            </section>
        </div>

        {{-- EDIÇÃO (preenchida no Task 7) --}}
        <div x-show="editando" x-cloak>
            {{-- <livewire:conta.editar-perfil :perfil="$perfil" /> entra aqui no Task 7 --}}
        </div>
    </div>
</x-layout.conta>
```

- [ ] **Step 4: Rodar (deve passar) + Pint + commit**

Run: `docker exec cema-app php artisan test --filter=PerfilViewTest`
Expected: PASS (2 testes).

```bash
docker exec cema-app ./vendor/bin/pint
git add resources/views/conta/perfil.blade.php tests/Feature/Conta/PerfilViewTest.php
git commit -m "feat(conta): Meu Perfil (visualizacao SSR) com atuacao read-only + estado vazio"
```

---

## Task 6: Header global auth-aware

**Files:**
- Modify: `resources/views/components/layout/header.blade.php`
- Test: `tests/Feature/Conta/HeaderAuthTest.php`

**Interfaces:**
- Consumes: `auth()`, `User::iniciais`, `User::perfil->foto_thumb_url`, rotas `login`/`register`/`conta.painel`/`logout`.

- [ ] **Step 1: Teste do header**

Create `tests/Feature/Conta/HeaderAuthTest.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-04

namespace Tests\Feature\Conta;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HeaderAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('frequentador', 'web');
    }

    public function test_visitante_ve_links_de_entrar_e_cadastrar(): void
    {
        $this->get('/')
            ->assertSee(route('login'), false)
            ->assertSee(route('register'), false)
            ->assertSee('Entrar')
            ->assertSee('Cadastrar');
    }

    public function test_membro_logado_ve_menu_da_conta(): void
    {
        $user = User::factory()->create(['name' => 'Bruno Alves', 'ativo' => true]);
        $user->assignRole('frequentador');

        $this->actingAs($user)->get('/')
            ->assertSee('Minha Conta')
            ->assertSee('Sair')
            ->assertSee('Bruno'); // primeiro nome na saudação do header
    }
}
```

- [ ] **Step 2: Rodar (deve falhar)**

Run: `docker exec cema-app php artisan test --filter=HeaderAuthTest`
Expected: FAIL (o header ainda mostra "Entrar/Cadastrar" como `<span>` e sem menu de membro).

- [ ] **Step 3: Bloco de auth do header (desktop)**

Modify `resources/views/components/layout/header.blade.php` — substituir o bloco `{{-- Auth (desktop) --}}` (as linhas com os `<span aria-disabled>` "Entrar"/"Cadastrar") por:

```blade
        {{-- Auth (desktop) --}}
        <div class="ml-auto hidden items-center gap-3 desktop-sm:flex font-ui text-sm">
            @guest
                <a href="{{ route('login') }}" class="font-semibold text-primary hover:underline">Entrar</a>
                <a href="{{ route('register') }}" class="rounded-pill bg-primary px-4 py-1.5 font-semibold text-white hover:bg-primary/90">Cadastrar</a>
            @else
                @php($u = auth()->user())
                <div class="relative" x-data="{ aberto: false }" @click.outside="aberto = false">
                    <button type="button" @click="aberto = !aberto" :aria-expanded="aberto" aria-haspopup="true"
                            class="flex items-center gap-2 rounded-pill py-1 pl-1 pr-2 hover:bg-surface">
                        @if ($u->perfil?->foto_thumb_url)
                            <img src="{{ $u->perfil->foto_thumb_url }}" alt="" class="size-8 rounded-full object-cover">
                        @else
                            <span class="flex size-8 items-center justify-center rounded-full bg-primary/10 text-xs font-semibold text-primary">{{ $u->iniciais }}</span>
                        @endif
                        <span class="text-text">Olá, {{ \Illuminate\Support\Str::of($u->name)->explode(' ')->first() }}</span>
                        <span aria-hidden="true" class="text-[9px] text-text-muted">▾</span>
                    </button>
                    <div x-show="aberto" x-cloak x-transition
                         class="absolute right-0 top-full z-50 mt-1 min-w-[180px] rounded-md border border-border bg-white py-1 shadow-elevated">
                        <a href="{{ route('conta.painel') }}" class="block px-4 py-2 text-text hover:bg-surface hover:text-primary">Minha Conta</a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="block w-full px-4 py-2 text-left text-text hover:bg-surface hover:text-primary">Sair</button>
                        </form>
                    </div>
                </div>
            @endguest
        </div>
```

- [ ] **Step 4: Seção de membro no off-canvas (mobile)**

Modify `resources/views/components/layout/header.blade.php` — dentro do `<aside id="menu-mobile">`, logo após o cabeçalho do drawer (a `<div>` com logo + botão fechar) e antes do `<nav>` do menu, inserir:

```blade
        <div class="border-b border-border-muted px-4 py-3">
            @guest
                <div class="flex gap-2">
                    <a href="{{ route('login') }}" class="flex-1 rounded-pill border border-primary px-4 py-2 text-center text-sm font-semibold text-primary">Entrar</a>
                    <a href="{{ route('register') }}" class="flex-1 rounded-pill bg-primary px-4 py-2 text-center text-sm font-semibold text-white">Cadastrar</a>
                </div>
            @else
                @php($u = auth()->user())
                <p class="mb-2 font-mono text-xs uppercase tracking-[0.08em] text-text-muted">Minha conta</p>
                <a href="{{ route('conta.painel') }}" class="flex items-center gap-2 py-1 text-text">
                    <span class="flex size-8 items-center justify-center rounded-full bg-primary/10 text-xs font-semibold text-primary">{{ $u->iniciais }}</span>
                    {{ $u->name }}
                </a>
                <form method="POST" action="{{ route('logout') }}" class="mt-1">
                    @csrf
                    <button type="submit" class="py-1 text-sm text-text-muted hover:text-primary">Sair</button>
                </form>
            @endguest
        </div>
```

- [ ] **Step 5: Rodar (deve passar) + regressão + Pint + commit**

Run: `docker exec cema-app php artisan test --filter=HeaderAuthTest`
Expected: PASS (2 testes).

Run: `docker exec cema-app php artisan test --filter=Agenda --filter=Palestra` (amostra de páginas públicas que usam o header — confirmar que renderizam para guest).
Expected: PASS (o header condicional não quebra as páginas públicas).

```bash
docker exec cema-app ./vendor/bin/pint
git add resources/views/components/layout/header.blade.php tests/Feature/Conta/HeaderAuthTest.php
git commit -m "feat(conta): header auth-aware (menu do membro / Entrar-Cadastrar) site-wide"
```

---

## Task 7: Edição do perfil — Livewire `EditarPerfil`

**Files:**
- Create: `app/Livewire/Conta/EditarPerfil.php`, `resources/views/livewire/conta/editar-perfil.blade.php`, `tests/Feature/Conta/EditarPerfilTest.php`
- Modify: `resources/views/conta/perfil.blade.php` (embutir o componente na região de edição)

**Interfaces:**
- Consumes: `PerfilMembro` (foto via `COLECAO_FOTO`), `User`.
- Produces: componente `<livewire:conta.editar-perfil>` — salva `name` (User) + `whatsapp`/`whatsapp_publico`/`data_nascimento`/`endereco` (perfil) + foto (Media). NUNCA toca papel/socio/setor.

- [ ] **Step 1: Teste do componente**

Create `tests/Feature/Conta/EditarPerfilTest.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-04

namespace Tests\Feature\Conta;

use App\Livewire\Conta\EditarPerfil;
use App\Models\PerfilMembro;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EditarPerfilTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('frequentador', 'web');
    }

    private function membro(): User
    {
        $u = User::factory()->create(['name' => 'Nome Antigo', 'ativo' => true]);
        $u->assignRole('frequentador');
        $u->perfil()->create([]);

        return $u;
    }

    public function test_salva_dados_pessoais_e_contato(): void
    {
        $user = $this->membro();

        Livewire::actingAs($user)->test(EditarPerfil::class)
            ->set('name', 'Nome Novo')
            ->set('data_nascimento', '1990-05-10')
            ->set('endereco', 'Rua Nova, 42')
            ->set('whatsapp', '61988887777')
            ->set('whatsapp_publico', true)
            ->call('salvar')
            ->assertHasNoErrors()
            ->assertRedirect(route('conta.perfil'));

        $user->refresh();
        $this->assertSame('Nome Novo', $user->name);
        $this->assertSame('Rua Nova, 42', $user->perfil->endereco);
        $this->assertSame('61988887777', $user->perfil->whatsapp);
        $this->assertTrue($user->perfil->whatsapp_publico);
        $this->assertSame('1990-05-10', $user->perfil->data_nascimento->format('Y-m-d'));
    }

    public function test_upload_de_foto_grava_na_media_library(): void
    {
        Storage::fake('public');
        $user = $this->membro();

        Livewire::actingAs($user)->test(EditarPerfil::class)
            ->set('foto', UploadedFile::fake()->image('avatar.jpg', 800, 800))
            ->call('salvar')
            ->assertHasNoErrors();

        $this->assertNotNull($user->perfil->fresh()->foto_url);
    }

    public function test_foto_rejeita_arquivo_nao_imagem(): void
    {
        $user = $this->membro();

        Livewire::actingAs($user)->test(EditarPerfil::class)
            ->set('foto', UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'))
            ->call('salvar')
            ->assertHasErrors(['foto']);
    }

    public function test_nao_permite_editar_papel_socio_ou_setor(): void
    {
        $user = $this->membro();
        $this->assertFalse($user->socio);

        Livewire::actingAs($user)->test(EditarPerfil::class)
            ->set('name', 'Só o Nome')
            ->call('salvar');

        $user->refresh();
        $this->assertFalse($user->socio);                     // socio intocado
        $this->assertTrue($user->hasRole('frequentador'));    // papel intocado
        $this->assertCount(0, $user->setores);                // setores intocados
    }
}
```

- [ ] **Step 2: Rodar (deve falhar)**

Run: `docker exec cema-app php artisan test --filter=EditarPerfilTest`
Expected: FAIL (classe `EditarPerfil` inexistente).

- [ ] **Step 3: Componente Livewire**

Create `app/Livewire/Conta/EditarPerfil.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-04

namespace App\Livewire\Conta;

use App\Models\PerfilMembro;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithFileUploads;

class EditarPerfil extends Component
{
    use WithFileUploads;

    public string $name = '';

    public ?string $data_nascimento = null;

    public ?string $endereco = null;

    public ?string $whatsapp = null;

    public bool $whatsapp_publico = false;

    public $foto = null;

    public function mount(): void
    {
        $user = auth()->user();
        $perfil = $user->perfil()->firstOrCreate([]);

        $this->name = (string) $user->name;
        $this->data_nascimento = $perfil->data_nascimento?->format('Y-m-d');
        $this->endereco = $perfil->endereco;
        $this->whatsapp = $perfil->whatsapp;
        $this->whatsapp_publico = (bool) $perfil->whatsapp_publico;
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'data_nascimento' => ['nullable', 'date'],
            'endereco' => ['nullable', 'string', 'max:500'],
            'whatsapp' => ['nullable', 'string', 'max:30'],
            'whatsapp_publico' => ['boolean'],
            'foto' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:1024'],
        ];
    }

    public function salvar()
    {
        $dados = $this->validate();
        $user = auth()->user();
        $perfil = $user->perfil()->firstOrCreate([]);

        DB::transaction(function () use ($user, $perfil, $dados) {
            $user->update(['name' => $dados['name']]);
            $perfil->update([
                'data_nascimento' => $dados['data_nascimento'],
                'endereco' => $dados['endereco'],
                'whatsapp' => $dados['whatsapp'],
                'whatsapp_publico' => $dados['whatsapp_publico'],
            ]);

            if ($this->foto) {
                $perfil->addMedia($this->foto->getRealPath())
                    ->usingFileName('foto.'.$this->foto->getClientOriginalExtension())
                    ->toMediaCollection(PerfilMembro::COLECAO_FOTO);
            }
        });

        session()->flash('status', 'Perfil atualizado.');

        return $this->redirect(route('conta.perfil'), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.conta.editar-perfil');
    }
}
```

- [ ] **Step 4: View do componente**

Create `resources/views/livewire/conta/editar-perfil.blade.php`:

```blade
{{-- Thiago Mourão — https://github.com/MouraoBSB — 2026-07-04 --}}
<form wire:submit="salvar" class="space-y-6">
    <section class="rounded-lg bg-white p-6 shadow-card">
        <h3 class="mb-4 font-display font-semibold text-primary">Foto de perfil</h3>
        <div class="flex items-center gap-4">
            <div class="size-20 overflow-hidden rounded-full bg-primary/10">
                @if ($foto)
                    <img src="{{ $foto->temporaryUrl() }}" alt="Prévia" class="size-full object-cover">
                @elseif (auth()->user()->perfil?->foto_thumb_url)
                    <img src="{{ auth()->user()->perfil->foto_thumb_url }}" alt="" class="size-full object-cover">
                @else
                    <span class="flex size-full items-center justify-center text-lg font-semibold text-primary">{{ auth()->user()->iniciais }}</span>
                @endif
            </div>
            <div>
                <input type="file" wire:model="foto" accept="image/jpeg,image/png,image/webp"
                       class="block text-sm text-text file:mr-3 file:rounded-pill file:border-0 file:bg-surface file:px-4 file:py-2 file:text-sm file:text-primary">
                <p class="mt-1 text-xs text-text-muted">Tamanho máximo: 1 MB. A capa é gerada automaticamente.</p>
                @error('foto') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
            </div>
        </div>
    </section>

    <section class="rounded-lg bg-white p-6 shadow-card">
        <h3 class="mb-4 font-display font-semibold text-primary">Dados pessoais</h3>
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label for="ep-name" class="block text-sm font-medium">Nome público</label>
                <input id="ep-name" type="text" wire:model="name" class="mt-1 w-full rounded-md border border-border px-3 py-2 focus:border-primary focus:ring-primary">
                @error('name') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="ep-nasc" class="block text-sm font-medium">Data de nascimento</label>
                <input id="ep-nasc" type="date" wire:model="data_nascimento" class="mt-1 w-full rounded-md border border-border px-3 py-2 focus:border-primary focus:ring-primary">
                @error('data_nascimento') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
            </div>
            <div class="sm:col-span-2">
                <label for="ep-end" class="block text-sm font-medium">Endereço <span class="text-xs font-normal text-text-muted">(não é público — apenas administrativo)</span></label>
                <input id="ep-end" type="text" wire:model="endereco" class="mt-1 w-full rounded-md border border-border px-3 py-2 focus:border-primary focus:ring-primary">
                @error('endereco') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
            </div>
        </div>
    </section>

    <section class="rounded-lg bg-white p-6 shadow-card">
        <h3 class="mb-4 font-display font-semibold text-primary">Contato</h3>
        <div>
            <label for="ep-wa" class="block text-sm font-medium">WhatsApp</label>
            <input id="ep-wa" type="text" wire:model="whatsapp" class="mt-1 w-full max-w-xs rounded-md border border-border px-3 py-2 focus:border-primary focus:ring-primary">
            @error('whatsapp') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
        </div>
        <label class="mt-3 flex items-center gap-2 text-sm">
            <input type="checkbox" wire:model="whatsapp_publico" role="switch" class="rounded border-border text-primary focus:ring-primary">
            Visível para outros membros
        </label>
    </section>

    <section class="rounded-lg bg-surface p-6 ring-1 ring-dashed ring-border">
        <div class="flex items-center gap-2 text-sm text-text-muted">
            <span class="rounded-pill bg-border-muted px-2.5 py-0.5 text-[11px] font-medium text-text-secondary">Somente leitura</span>
            Sua atuação, papel e situação de sócio são geridos pela casa.
        </div>
    </section>

    <div class="sticky bottom-0 -mx-1 flex justify-end gap-3 border-t border-border bg-cream/95 px-1 py-3 backdrop-blur">
        <a href="{{ route('conta.perfil') }}" class="rounded-pill px-4 py-2 text-sm font-medium text-text-muted hover:text-primary">Cancelar</a>
        <button type="submit" class="rounded-pill bg-primary px-5 py-2 text-sm font-medium text-white hover:bg-primary/90"
                wire:loading.attr="disabled">Salvar alterações</button>
    </div>
</form>
```

- [ ] **Step 5: Embutir o componente na página de perfil**

Modify `resources/views/conta/perfil.blade.php` — na região de edição (o `<div x-show="editando" x-cloak>` do Task 5), substituir o comentário placeholder por:

```blade
        <div x-show="editando" x-cloak>
            <livewire:conta.editar-perfil />
        </div>
```

- [ ] **Step 6: Rodar (deve passar) + Pint + commit**

Run: `docker exec cema-app php artisan test --filter=EditarPerfilTest`
Expected: PASS (4 testes).

```bash
docker exec cema-app ./vendor/bin/pint app/Livewire/Conta/EditarPerfil.php tests/Feature/Conta/EditarPerfilTest.php
git add app/Livewire/Conta/EditarPerfil.php resources/views/livewire/conta/editar-perfil.blade.php resources/views/conta/perfil.blade.php tests/Feature/Conta/EditarPerfilTest.php
git commit -m "feat(conta): edicao do perfil (Livewire) com upload de foto + atuacao blindada"
```

---

## Task 8: Cropper quadrado no upload da foto

**Files:**
- Modify: `package.json` (dep `cropperjs`), `vite.config.js` (entrada), `resources/views/livewire/conta/editar-perfil.blade.php` (integração), `resources/views/conta/perfil.blade.php` (nada — o componente já carrega o asset)
- Create: `resources/js/cropper-perfil.js`

**Interfaces:**
- Consumes: o input `wire:model="foto"` e o `$wire` do componente `EditarPerfil`.
- Produces: enquadramento quadrado client-side; o recorte substitui o arquivo enviado ao Livewire.

- [ ] **Step 1: Instalar o Cropper.js**

Run: `docker exec cema-app npm install cropperjs`
Expected: `cropperjs` adicionado ao `package.json`.

- [ ] **Step 2: Entrada Vite dedicada**

Modify `vite.config.js` — adicionar `resources/js/cropper-perfil.js` ao array `input` do plugin `laravel({ input: [...] })` (mantendo `resources/css/app.css` e `resources/js/app.js` já existentes).

- [ ] **Step 3: Módulo do cropper (Alpine data)**

Create `resources/js/cropper-perfil.js`:

```js
// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-04
import Cropper from 'cropperjs';
import 'cropperjs/dist/cropper.css';

// Alpine data: abre o cropper ao escolher um arquivo, envia o recorte quadrado ao Livewire.
document.addEventListener('alpine:init', () => {
    window.Alpine.data('cropperPerfil', () => ({
        cropper: null,
        aberto: false,
        srcPreview: null,

        aoEscolher(evento) {
            const arquivo = evento.target.files?.[0];
            if (!arquivo) return;

            const leitor = new FileReader();
            leitor.onload = (e) => {
                this.srcPreview = e.target.result;
                this.aberto = true;
                this.$nextTick(() => {
                    const img = this.$refs.imagem;
                    img.src = this.srcPreview;
                    this.cropper?.destroy();
                    this.cropper = new Cropper(img, { aspectRatio: 1, viewMode: 1, autoCropArea: 1 });
                });
            };
            leitor.readAsDataURL(arquivo);
            evento.target.value = ''; // permite reescolher o mesmo arquivo
        },

        confirmar() {
            this.cropper.getCroppedCanvas({ width: 800, height: 800 }).toBlob((blob) => {
                const arquivo = new File([blob], 'foto.webp', { type: 'image/webp' });
                this.$wire.upload('foto', arquivo);
                this.fechar();
            }, 'image/webp', 0.85);
        },

        fechar() {
            this.cropper?.destroy();
            this.cropper = null;
            this.aberto = false;
        },
    }));
});
```

- [ ] **Step 4: Integrar no componente (carregando o asset só aqui)**

Modify `resources/views/livewire/conta/editar-perfil.blade.php`:

(a) No topo do arquivo, antes do `<form>`, carregar o asset só quando o componente está presente:

```blade
@assets
    @vite('resources/js/cropper-perfil.js')
@endassets
```

(b) Trocar o bloco da foto (o `<section>` "Foto de perfil") para dirigir o cropper via Alpine (o input abre o modal; o preview mostra o recorte):

```blade
    <section class="rounded-lg bg-white p-6 shadow-card" x-data="cropperPerfil">
        <h3 class="mb-4 font-display font-semibold text-primary">Foto de perfil</h3>
        <div class="flex items-center gap-4">
            <div class="size-20 overflow-hidden rounded-full bg-primary/10">
                @if ($foto)
                    <img src="{{ $foto->temporaryUrl() }}" alt="Prévia" class="size-full object-cover">
                @elseif (auth()->user()->perfil?->foto_thumb_url)
                    <img src="{{ auth()->user()->perfil->foto_thumb_url }}" alt="" class="size-full object-cover">
                @else
                    <span class="flex size-full items-center justify-center text-lg font-semibold text-primary">{{ auth()->user()->iniciais }}</span>
                @endif
            </div>
            <div>
                {{-- Enhancement progressivo: sem JS, este input envia o arquivo direto (thumb central). --}}
                <input type="file" wire:model="foto" x-on:change="aoEscolher" accept="image/jpeg,image/png,image/webp"
                       class="block text-sm text-text file:mr-3 file:rounded-pill file:border-0 file:bg-surface file:px-4 file:py-2 file:text-sm file:text-primary">
                <p class="mt-1 text-xs text-text-muted">Tamanho máximo: 1 MB. A capa é gerada automaticamente.</p>
                @error('foto') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
            </div>
        </div>

        {{-- Modal do cropper --}}
        <div x-show="aberto" x-cloak class="fixed inset-0 z-[80] flex items-center justify-center bg-black/60 p-4">
            <div class="w-full max-w-md rounded-lg bg-white p-4">
                <div class="max-h-[60vh] overflow-hidden"><img x-ref="imagem" alt="Recorte da foto" class="block max-w-full"></div>
                <div class="mt-3 flex justify-end gap-2">
                    <button type="button" @click="fechar" class="rounded-pill px-4 py-2 text-sm text-text-muted hover:text-primary">Cancelar</button>
                    <button type="button" @click="confirmar" class="rounded-pill bg-primary px-4 py-2 text-sm font-medium text-white">Usar recorte</button>
                </div>
            </div>
        </div>
    </section>
```

> Como o `x-on:change="aoEscolher"` roda antes do `wire:model`, o fluxo com JS: escolher → cropper → "Usar recorte" → `$wire.upload('foto', recorte)`. Sem JS, o `wire:model="foto"` envia o arquivo original (validado igual). A validação `mimes:jpg,jpeg,png,webp,max:1024` do Task 7 cobre ambos os caminhos.

- [ ] **Step 5: Build + verificação**

Run: `docker exec cema-app npm run build`
Expected: build sem erros; o bundle `cropper-perfil` é gerado.

Run: `docker exec cema-app php artisan test --filter=EditarPerfilTest`
Expected: PASS (4 testes — o upload server-side segue igual; o recorte é client-side, não testado no servidor).

- [ ] **Step 6: Pint + commit**

```bash
docker exec cema-app ./vendor/bin/pint
git add package.json package-lock.json vite.config.js resources/js/cropper-perfil.js resources/views/livewire/conta/editar-perfil.blade.php
git commit -m "feat(conta): cropper quadrado no upload da foto (carregado so na edicao)"
```

---

## Verificação final (após todas as tasks)

- [ ] **Suíte completa + Pint**

Run: `docker exec cema-app php artisan test`
Expected: toda a suíte verde (fatia nova + pré-existente).

Run: `docker exec cema-app ./vendor/bin/pint --test`
Expected: sem drift de estilo.

- [ ] **Verificação manual (dev)**

- Logar → cair em `/minha-conta`; ver a saudação (avatar/iniciais, papel, Sócio quando aplicável).
- Painel: próximas palestras (ou estado vazio) + atalhos funcionando.
- Meu Perfil: dados + atuação read-only; "Editar perfil" abre o form; salvar nome/nascimento/endereço/WhatsApp/visibilidade persiste; enviar foto → cropper quadrado → avatar atualizado (fallback iniciais quando sem foto).
- Header: logado mostra avatar + "Olá, {nome}" + dropdown (Minha Conta / Sair); visitante mostra Entrar/Cadastrar — em todas as páginas.
- `frequentador` sem setor: "Minha atuação" mostra a linha discreta, com papel e Sócio.
- `/admin` continua exigindo diretor/admin (Filament intacto).

## Critério de pronto (checklist final)

- [ ] Pós-login vai para `/minha-conta`; casca (saudação + nav) renderiza.
- [ ] Painel: próximas palestras (`>= hoje`) + atalhos + estado vazio.
- [ ] Meu Perfil: visualização (dados + atuação read-only + estado vazio do frequentador) e edição (Livewire) com upload de foto (Spatie Media, fallback iniciais) e cropper quadrado.
- [ ] Atuação/papel/sócio nunca editáveis (blindados no Livewire).
- [ ] Header reflete o estado de auth em todas as páginas.
- [ ] `foto_perfil` dropada; foto via Media Library (coleção `foto`, web ≤640 / thumb 400).
- [ ] Trait `TemIniciais` compartilhada; testes do Palestrante verdes.
- [ ] Responsivo/acessível; Filament intacto; suíte verde; Pint limpo.
