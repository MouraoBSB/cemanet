# Autenticação pública de membro — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Dar ao front público login/logout/cadastro aberto/"esqueci a senha" por e-mail-senha e por Google, na identidade CEMA, reaproveitando o back-end de auth (hasher `cema` + `rehash_on_login`, papel `frequentador`, `perfis_membro`).

**Architecture:** Fortify **headless** (rotas ignoradas; declaradas em pt-BR no `web.php`, acima de um `Route::fallback`) com `authenticateUsing` customizado (bloqueia `ativo=false`, rehash manual) + action `CreateNewUser` (frequentador + perfil). Socialite para Google. Forms Blade + POST num layout de auth enxuto CEMA.

**Tech Stack:** PHP 8.3 · Laravel 13 · laravel/fortify · laravel/socialite · Blade + Tailwind v4 · MySQL 8 (dev) / SQLite (testes) · Docker.

## Global Constraints

- **PRÉ-REQUISITO:** este plano executa na branch `fase-auth` **depois** de `git merge main` com o **PR #7 mergeado** (back-end de usuários: `User` com `HasRoles`, driver `cema`, papel `frequentador`, `perfis_membro`, coluna `ativo`). Sem isso, os testes de papel/perfil falham.
- **Sem verificação de e-mail** (decisão do cliente): NUNCA adicionar `MustVerifyEmail` ao `User` nem o feature `emailVerification` ao Fortify.
- **Fortify sem rotas próprias:** `Fortify::ignoreRoutes()` no provider; TODAS as rotas de auth são declaradas no `web.php`, acima do `Route::fallback`.
- **Nomes de rota preservados** (o reset resolve por nome): `login`, `register`, `logout`, `password.request`, `password.email`, `password.reset`, `password.update`. A rota GET de reset é `/redefinir-senha/{token}` com nome `password.reset`.
- **`/auth/google/callback` NÃO traduzido** (já cadastrado no Google Console).
- **Filament intacto:** admin segue `/admin/login` + `canAccessPanel`. Não tocar.
- **Migrations INCREMENTAIS.** Nunca `migrate:fresh`/`refresh`/`reset`/`wipe` no dev.
- **Comandos rodam no container:** prefixe com `docker exec cema-app`. Tests em SQLite; dev MySQL.
- **Pint antes de commitar** (`docker exec cema-app ./vendor/bin/pint`).
- **Cabeçalho de autoria** em todo PHP novo: `// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-04`.
- **Brazilian Portuguese** em identificadores de domínio, labels, mensagens.

Spec: `docs/superpowers/specs/2026-07-03-auth-publica-membro-design.md`.

---

## File Structure

- `config/fortify.php` — features (registration + resetPasswords), home, guard.
- `config/services.php` — bloco `google`.
- `app/Providers/FortifyServiceProvider.php` — `ignoreRoutes`, view bindings, `authenticateUsing`, `createUsersUsing`, rate-limiter `login`.
- `app/Actions/Fortify/CreateNewUser.php` + `PasswordValidationRules.php` (stub do Fortify).
- `app/Http/Controllers/Auth/GoogleController.php`.
- `database/migrations/2026_07_04_000001_add_google_id_to_users_table.php`.
- `app/Models/User.php` — `google_id` no `#[Fillable]`.
- `routes/web.php` — rotas de auth + Google; catch-all → `Route::fallback`.
- `resources/views/components/layout/auth.blade.php` — layout de auth enxuto.
- `resources/views/auth/{login,register,forgot-password,reset-password}.blade.php`.
- `tests/Feature/Auth/*` — login, cadastro, google, reset, rotas.

---

## Task 1: Dependências + schema (`google_id`) + config

**Files:**
- Modify: `composer.json` (require), `config/fortify.php`, `config/services.php`, `.env.example`, `app/Models/User.php`
- Create: `database/migrations/2026_07_04_000001_add_google_id_to_users_table.php`
- Delete: a migration de 2FA que o `fortify:install` cria
- Test: `tests/Feature/Auth/ConfigAuthTest.php`

**Interfaces:**
- Produces: `laravel/fortify` + `laravel/socialite` instalados; `users.google_id` (string nullable unique); `config('fortify.features')` sem emailVerification; `config('services.google')`.

- [ ] **Step 1: Instalar os pacotes**

Run: `docker exec cema-app composer require laravel/fortify:^1.24 laravel/socialite:^5.16`
Expected: ambos adicionados ao `composer.json`.

- [ ] **Step 2: Publicar o Fortify e remover a migration de 2FA**

Run: `docker exec cema-app php artisan fortify:install`
Expected: cria `app/Providers/FortifyServiceProvider.php`, `config/fortify.php`, `app/Actions/Fortify/*`, registra o provider em `bootstrap/providers.php`, e cria uma migration `*_add_two_factor_columns_to_users_table.php`.

Remover a migration de 2FA (não usamos 2FA):
Run: `docker exec cema-app bash -lc 'rm database/migrations/*_add_two_factor_columns_to_users_table.php'`
Expected: arquivo removido.

- [ ] **Step 3: Migration da coluna `google_id`**

Create `database/migrations/2026_07_04_000001_add_google_id_to_users_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('google_id')->nullable()->unique()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('google_id');
        });
    }
};
```

- [ ] **Step 4: `google_id` no fillable do User**

Modify `app/Models/User.php` — adicionar `'google_id'` ao atributo `#[Fillable([...])]` (mantendo os campos que já existem: `name, email, password, origem_legado_id, socio, ativo, email_verified_at`).

- [ ] **Step 5: Rodar as migrations**

Run: `docker exec cema-app php artisan migrate`
Expected: `google_id` criada. NÃO usar `--fresh`.

- [ ] **Step 6: Configurar `config/fortify.php`**

Substituir o conteúdo relevante de `config/fortify.php`:
- `'home' => '/',`
- `'features' => [ Features::registration(), Features::resetPasswords() ],` (remover `emailVerification`, `updateProfileInformation`, `updatePasswords`, `twoFactorAuthentication` se presentes).
- `'views' => true,`
- Garantir `'guard' => 'web'` e `'passwords' => 'users'`.

- [ ] **Step 7: Configurar `config/services.php` (Google)**

Adicionar em `config/services.php` (dentro do array de retorno):

```php
'google' => [
    'client_id' => env('GOOGLE_CLIENT_ID'),
    'client_secret' => env('GOOGLE_CLIENT_SECRET'),
    'redirect' => env('GOOGLE_REDIRECT_URI', 'http://localhost:8000/auth/google/callback'),
],
```

Adicionar em `.env` e `.env.example`:

```
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI=http://localhost:8000/auth/google/callback
```

- [ ] **Step 8: Teste de config**

Create `tests/Feature/Auth/ConfigAuthTest.php`:

```php
<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Fortify\Features;
use Tests\TestCase;

class ConfigAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_google_id_existe_e_features_sem_verificacao(): void
    {
        $this->assertTrue(Schema::hasColumn('users', 'google_id'));
        $this->assertTrue(Features::enabled(Features::registration()));
        $this->assertTrue(Features::enabled(Features::resetPasswords()));
        $this->assertFalse(Features::enabled(Features::emailVerification()));
    }
}
```

- [ ] **Step 9: Rodar teste + Pint + commit**

Run: `docker exec cema-app php artisan test --filter=ConfigAuthTest`
Expected: PASS.

```bash
docker exec cema-app ./vendor/bin/pint
git add composer.json composer.lock config/fortify.php config/services.php .env.example database/migrations app/Models/User.php app/Providers/FortifyServiceProvider.php app/Actions bootstrap/providers.php tests/Feature/Auth/ConfigAuthTest.php
git commit -m "feat(auth): instala fortify+socialite, google_id e config (sem verificacao de e-mail)"
```

---

## Task 2: Provider headless + rotas pt-BR + `Route::fallback` + views CEMA

**Files:**
- Modify: `app/Providers/FortifyServiceProvider.php`, `routes/web.php`
- Create: `resources/views/components/layout/auth.blade.php`, `resources/views/auth/login.blade.php`, `register.blade.php`, `forgot-password.blade.php`, `reset-password.blade.php`
- Test: `tests/Feature/Auth/RotasAuthTest.php`

**Interfaces:**
- Consumes: Fortify instalado (Task 1).
- Produces: `Fortify::ignoreRoutes()`; view bindings (`auth.login`/`auth.register`/`auth.forgot-password`/`auth.reset-password`); rotas nomeadas (`login`,`register`,`logout`,`password.request`,`password.email`,`password.reset`,`password.update`); `Route::fallback`. As actions `authenticateUsing`/`createUsersUsing` são adicionadas nas Tasks 3-4.

- [ ] **Step 1: Provider — ignoreRoutes + view bindings + rate-limiter**

Reescrever `app/Providers/FortifyServiceProvider.php` (mantendo o namespace e o cabeçalho de autoria):

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-04

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        Fortify::ignoreRoutes(); // rotas declaradas no web.php (pt-BR, acima do fallback)
    }

    public function boot(): void
    {
        Fortify::loginView(fn () => view('auth.login'));
        Fortify::registerView(fn () => view('auth.register'));
        Fortify::requestPasswordResetLinkView(fn () => view('auth.forgot-password'));
        Fortify::resetPasswordView(fn (Request $request) => view('auth.reset-password', ['request' => $request]));

        RateLimiter::for('login', function (Request $request) {
            $chave = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($chave);
        });
    }
}
```

- [ ] **Step 2: Rotas de auth no `web.php` (acima do fallback) + trocar catch-all por `Route::fallback`**

Modify `routes/web.php`:

(a) Adicionar os imports no topo:

```php
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;
use Laravel\Fortify\Http\Controllers\NewPasswordController;
use Laravel\Fortify\Http\Controllers\PasswordResetLinkController;
use Laravel\Fortify\Http\Controllers\RegisteredUserController;
```

(b) Logo após a rota `home` (`Route::get('/', ...)`), inserir o bloco de auth:

```php
// Autenticação pública de membro (Fortify headless — rotas pt-BR, nomes preservados).
Route::middleware('guest')->group(function () {
    Route::get('/entrar', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/entrar', [AuthenticatedSessionController::class, 'store']);

    Route::get('/cadastro', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/cadastro', [RegisteredUserController::class, 'store'])->middleware('throttle:6,1');

    Route::get('/esqueci-a-senha', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/esqueci-a-senha', [PasswordResetLinkController::class, 'store'])->name('password.email')->middleware('throttle:6,1');

    Route::get('/redefinir-senha/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/redefinir-senha', [NewPasswordController::class, 'store'])->name('password.update');
});

Route::post('/sair', [AuthenticatedSessionController::class, 'destroy'])->name('logout')->middleware('auth');
```

(c) No fim do arquivo, trocar o catch-all `Route::get('/{slug}', function (string $slug) {...})->where(...)` por `Route::fallback`:

```php
// Fallback: avaliado SEMPRE por último. Slug de post no root → /sementeira/{slug} (301); senão 404.
Route::fallback(function (\Illuminate\Http\Request $request) {
    $slug = ltrim($request->path(), '/');
    if (preg_match('/^[a-z0-9-]+$/', $slug) && \App\Models\Post::where('slug', $slug)->exists()) {
        return redirect()->route('blog.show', ['slug' => $slug], 301);
    }
    abort(404);
});
```

- [ ] **Step 3: Layout de auth CEMA**

Create `resources/views/components/layout/auth.blade.php`:

```blade
{{-- Thiago Mourão — https://github.com/MouraoBSB — 2026-07-04 --}}
<!DOCTYPE html>
<html lang="pt-BR" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $titulo ?? 'Entrar' }} · CEMA</title>
    @vite('resources/css/app.css')
</head>
<body class="min-h-full bg-cream font-sans text-text-ink antialiased">
    <main class="mx-auto flex min-h-screen max-w-md flex-col justify-center px-4 py-10">
        <a href="{{ url('/') }}" class="mb-8 flex justify-center" aria-label="Voltar ao site do CEMA">
            <span class="font-display text-2xl font-bold text-primary">CEMA</span>
        </a>

        <section class="rounded-lg bg-white p-6 shadow-card sm:p-8">
            <h1 class="mb-6 font-display text-xl font-semibold text-primary">{{ $titulo ?? 'Entrar' }}</h1>
            {{ $slot }}
        </section>

        <a href="{{ url('/') }}" class="mt-6 text-center text-sm text-text-muted underline hover:text-primary">
            ← Voltar ao site
        </a>
    </main>
</body>
</html>
```

- [ ] **Step 4: View de login**

Create `resources/views/auth/login.blade.php`:

```blade
<x-layout.auth titulo="Entrar">
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
        Entrar com Google
    </a>

    <div class="mt-4 flex justify-between text-sm">
        <a href="{{ route('password.request') }}" class="text-text-muted underline hover:text-primary">Esqueci a senha</a>
        <a href="{{ route('register') }}" class="text-text-muted underline hover:text-primary">Criar conta</a>
    </div>
</x-layout.auth>
```

- [ ] **Step 5: Views de cadastro, esqueci e redefinir**

Create `resources/views/auth/register.blade.php`:

```blade
<x-layout.auth titulo="Criar conta">
    <form method="POST" action="{{ route('register') }}" class="space-y-4">
        @csrf
        <div>
            <label for="name" class="block text-sm font-medium">Nome</label>
            <input id="name" name="name" type="text" required autofocus autocomplete="name" value="{{ old('name') }}"
                   class="mt-1 w-full rounded-md border border-border px-3 py-2 focus:border-primary focus:ring-primary">
            @error('name')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="email" class="block text-sm font-medium">E-mail</label>
            <input id="email" name="email" type="email" required autocomplete="email" value="{{ old('email') }}"
                   class="mt-1 w-full rounded-md border border-border px-3 py-2 focus:border-primary focus:ring-primary">
            @error('email')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="password" class="block text-sm font-medium">Senha</label>
            <input id="password" name="password" type="password" required autocomplete="new-password"
                   class="mt-1 w-full rounded-md border border-border px-3 py-2 focus:border-primary focus:ring-primary">
            @error('password')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="password_confirmation" class="block text-sm font-medium">Confirmar senha</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"
                   class="mt-1 w-full rounded-md border border-border px-3 py-2 focus:border-primary focus:ring-primary">
        </div>
        <button type="submit" class="w-full rounded-pill bg-primary px-4 py-2.5 font-medium text-white hover:bg-primary/90">Cadastrar</button>
    </form>

    <a href="{{ route('google.redirect') }}" class="mt-3 flex w-full items-center justify-center gap-2 rounded-pill border border-border px-4 py-2.5 font-medium hover:bg-surface">
        Cadastrar com Google
    </a>

    <p class="mt-4 text-center text-sm text-text-muted">
        Já tem conta? <a href="{{ route('login') }}" class="underline hover:text-primary">Entrar</a>
    </p>
</x-layout.auth>
```

Create `resources/views/auth/forgot-password.blade.php`:

```blade
<x-layout.auth titulo="Esqueci a senha">
    @if (session('status'))
        <p class="mb-4 rounded-md bg-accent/15 px-3 py-2 text-sm text-success" role="status">{{ session('status') }}</p>
    @endif
    <p class="mb-4 text-sm text-text-secondary">Informe seu e-mail e enviaremos um link para redefinir a senha.</p>
    <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
        @csrf
        <div>
            <label for="email" class="block text-sm font-medium">E-mail</label>
            <input id="email" name="email" type="email" required autofocus autocomplete="email" value="{{ old('email') }}"
                   class="mt-1 w-full rounded-md border border-border px-3 py-2 focus:border-primary focus:ring-primary">
            @error('email')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
        </div>
        <button type="submit" class="w-full rounded-pill bg-primary px-4 py-2.5 font-medium text-white hover:bg-primary/90">Enviar link</button>
    </form>
    <p class="mt-4 text-center text-sm"><a href="{{ route('login') }}" class="underline hover:text-primary">Voltar ao login</a></p>
</x-layout.auth>
```

Create `resources/views/auth/reset-password.blade.php`:

```blade
<x-layout.auth titulo="Redefinir senha">
    <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">
        <div>
            <label for="email" class="block text-sm font-medium">E-mail</label>
            <input id="email" name="email" type="email" required autocomplete="email"
                   value="{{ old('email', $request->email) }}"
                   class="mt-1 w-full rounded-md border border-border px-3 py-2 focus:border-primary focus:ring-primary">
            @error('email')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="password" class="block text-sm font-medium">Nova senha</label>
            <input id="password" name="password" type="password" required autocomplete="new-password"
                   class="mt-1 w-full rounded-md border border-border px-3 py-2 focus:border-primary focus:ring-primary">
            @error('password')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="password_confirmation" class="block text-sm font-medium">Confirmar nova senha</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"
                   class="mt-1 w-full rounded-md border border-border px-3 py-2 focus:border-primary focus:ring-primary">
        </div>
        <button type="submit" class="w-full rounded-pill bg-primary px-4 py-2.5 font-medium text-white hover:bg-primary/90">Redefinir senha</button>
    </form>
</x-layout.auth>
```

- [ ] **Step 6: Teste de regressão de rotas**

Create `tests/Feature/Auth/RotasAuthTest.php`:

```php
<?php

namespace Tests\Feature\Auth;

use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RotasAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_paginas_de_auth_renderizam(): void
    {
        $this->get('/entrar')->assertOk()->assertSee('Entrar');
        $this->get('/cadastro')->assertOk()->assertSee('Criar conta');
        $this->get('/esqueci-a-senha')->assertOk();
        $this->get('/redefinir-senha/token-qualquer?email=a@b.com')->assertOk();
    }

    public function test_fallback_preserva_redirect_301_de_slug_de_post(): void
    {
        Post::factory()->create(['slug' => 'reflexao-do-dia', 'status' => 'publicado']);

        $this->get('/reflexao-do-dia')->assertRedirect('/sementeira/reflexao-do-dia');
        $this->get('/slug-que-nao-existe')->assertNotFound();
    }
}
```

> Nota: se `Post` não tiver factory, criar via `Post::create([...])` com os campos mínimos obrigatórios (checar `database/factories` / o model). O objetivo do 2º teste é só provar que o `Route::fallback` não sombreou as rotas de auth e ainda faz o 301 dos posts.

- [ ] **Step 7: Rodar teste + Pint + commit**

Run: `docker exec cema-app php artisan test --filter=RotasAuthTest`
Expected: PASS.

```bash
docker exec cema-app ./vendor/bin/pint
git add app/Providers/FortifyServiceProvider.php routes/web.php resources/views/components/layout/auth.blade.php resources/views/auth tests/Feature/Auth/RotasAuthTest.php
git commit -m "feat(auth): rotas pt-BR + Route::fallback + provider headless + views CEMA"
```

---

## Task 3: Login (`authenticateUsing` + rehash + `ativo` + remember)

**Files:**
- Modify: `app/Providers/FortifyServiceProvider.php`
- Test: `tests/Feature/Auth/LoginTest.php`

**Interfaces:**
- Consumes: hasher `cema` (`Hash::check`/`Hash::needsRehash`), `User.ativo`.
- Produces: `Fortify::authenticateUsing` no `boot()` — valida via `Hash::check`, bloqueia `ativo=false` com mensagem específica, rehash manual quando `needsRehash`.

- [ ] **Step 1: Escrever os testes (TDD)**

Create `tests/Feature/Auth/LoginTest.php`:

```php
<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_com_senha_bcrypt(): void
    {
        $user = User::factory()->create(['password' => Hash::make('segredo123'), 'ativo' => true]);

        $this->post('/entrar', ['email' => $user->email, 'password' => 'segredo123'])
            ->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_com_senha_legada_wp_faz_rehash(): void
    {
        $pre = base64_encode(hash_hmac('sha384', 'segredo123', 'wp-sha384', true));
        $user = User::factory()->create(['password' => '$wp'.password_hash($pre, PASSWORD_BCRYPT), 'ativo' => true]);

        $this->post('/entrar', ['email' => $user->email, 'password' => 'segredo123'])->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
        $this->assertStringStartsWith('$2y$', $user->fresh()->password); // modernizou
    }

    public function test_conta_inativa_bloqueada_com_mensagem_especifica(): void
    {
        $user = User::factory()->create(['password' => Hash::make('segredo123'), 'ativo' => false]);

        $this->from('/entrar')->post('/entrar', ['email' => $user->email, 'password' => 'segredo123'])
            ->assertRedirect('/entrar')
            ->assertSessionHasErrors(['email' => 'Sua conta está inativa. Fale com a secretaria da casa.']);
        $this->assertGuest();
    }

    public function test_credencial_errada_mensagem_generica_sem_enumeracao(): void
    {
        $user = User::factory()->create(['password' => Hash::make('segredo123'), 'ativo' => true]);

        // senha errada e e-mail inexistente devem produzir a MESMA mensagem
        $r1 = $this->from('/entrar')->post('/entrar', ['email' => $user->email, 'password' => 'errada'])->assertRedirect('/entrar');
        $r2 = $this->from('/entrar')->post('/entrar', ['email' => 'ninguem@x.com', 'password' => 'errada'])->assertRedirect('/entrar');
        $this->assertNotEmpty(session('errors')->get('email'));
        $this->assertGuest();
    }

    public function test_lembrar_de_mim_persiste_sessao(): void
    {
        $user = User::factory()->create(['password' => Hash::make('segredo123'), 'ativo' => true]);

        $this->post('/entrar', ['email' => $user->email, 'password' => 'segredo123', 'remember' => 'on']);
        $this->assertNotNull($user->fresh()->remember_token);
    }

    public function test_rate_limit_dispara_lockout_apos_muitas_tentativas(): void
    {
        Event::fake([Lockout::class]);
        $user = User::factory()->create(['password' => Hash::make('segredo123'), 'ativo' => true]);

        foreach (range(1, 6) as $tentativa) {
            $this->post('/entrar', ['email' => $user->email, 'password' => 'errada']);
        }

        Event::assertDispatched(Lockout::class); // throttle do Fortify (limiter 'login') bloqueou
    }
}
```

- [ ] **Step 2: Rodar (deve falhar — sem authenticateUsing, `ativo=false` e genérico não batem)**

Run: `docker exec cema-app php artisan test --filter=LoginTest`
Expected: FAIL (ex.: conta inativa loga, ou mensagem específica ausente).

- [ ] **Step 3: Adicionar `authenticateUsing` ao provider**

Modify `app/Providers/FortifyServiceProvider.php` — adicionar no topo do `boot()` (com os imports `App\Models\User`, `Illuminate\Support\Facades\Hash`, `Illuminate\Validation\ValidationException`):

```php
Fortify::authenticateUsing(function (Request $request) {
    $user = User::where('email', $request->email)->first();

    if (! $user || ! Hash::check($request->password, $user->password)) {
        return null; // mensagem genérica do Fortify (sem revelar se o e-mail existe)
    }

    if (! $user->ativo) {
        throw ValidationException::withMessages([
            Fortify::username() => 'Sua conta está inativa. Fale com a secretaria da casa.',
        ]);
    }

    if (Hash::needsRehash($user->password)) {
        $user->forceFill(['password' => Hash::make($request->password)])->save();
    }

    return $user;
});
```

- [ ] **Step 4: Rodar (deve passar)**

Run: `docker exec cema-app php artisan test --filter=LoginTest`
Expected: PASS (6 testes).

- [ ] **Step 5: Pint + commit**

```bash
docker exec cema-app ./vendor/bin/pint
git add app/Providers/FortifyServiceProvider.php tests/Feature/Auth/LoginTest.php
git commit -m "feat(auth): login com bloqueio de conta inativa + rehash transparente + remember"
```

---

## Task 4: Cadastro aberto (`CreateNewUser`)

**Files:**
- Modify: `app/Providers/FortifyServiceProvider.php`, `app/Actions/Fortify/CreateNewUser.php`
- Test: `tests/Feature/Auth/CadastroTest.php`

**Interfaces:**
- Consumes: `PasswordValidationRules` (stub do Fortify), papel `frequentador`, `User::perfil()`.
- Produces: `CreateNewUser::create(array): User` cria usuário `frequentador` + `perfis_membro` vazio + `email_verified_at`, em transação; `Fortify::createUsersUsing(CreateNewUser::class)`.

- [ ] **Step 1: Escrever os testes (TDD)**

Create `tests/Feature/Auth/CadastroTest.php`:

```php
<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CadastroTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('frequentador', 'web');
    }

    public function test_cadastro_cria_frequentador_com_perfil_e_verificado(): void
    {
        $this->post('/cadastro', [
            'name' => 'Fulano de Tal',
            'email' => 'fulano@exemplo.com',
            'password' => 'senha-super-forte-2026',
            'password_confirmation' => 'senha-super-forte-2026',
        ])->assertRedirect('/');

        $user = User::where('email', 'fulano@exemplo.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('frequentador'));
        $this->assertNotNull($user->email_verified_at);
        $this->assertNotNull($user->perfil); // perfis_membro 1:1 criado
        $this->assertAuthenticatedAs($user);
    }

    public function test_email_duplicado_rejeitado(): void
    {
        User::factory()->create(['email' => 'ja@existe.com']);

        $this->from('/cadastro')->post('/cadastro', [
            'name' => 'X', 'email' => 'ja@existe.com',
            'password' => 'senha-super-forte-2026', 'password_confirmation' => 'senha-super-forte-2026',
        ])->assertRedirect('/cadastro')->assertSessionHasErrors('email');
    }
}
```

- [ ] **Step 2: Rodar (deve falhar — CreateNewUser ainda é o stub sem papel/perfil)**

Run: `docker exec cema-app php artisan test --filter=CadastroTest`
Expected: FAIL (sem papel `frequentador`/perfil).

- [ ] **Step 3: Implementar `CreateNewUser`**

Reescrever `app/Actions/Fortify/CreateNewUser.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-04

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    public function create(array $input): User
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)],
            'password' => $this->passwordRules(),
        ])->validate();

        return DB::transaction(function () use ($input) {
            $user = User::create([
                'name' => $input['name'],
                'email' => mb_strtolower(trim($input['email'])),
                'password' => Hash::make($input['password']),
                'ativo' => true,
                'email_verified_at' => now(),
            ]);

            $user->assignRole('frequentador');
            $user->perfil()->create([]);

            return $user;
        });
    }
}
```

- [ ] **Step 4: Garantir o bind no provider**

Modify `app/Providers/FortifyServiceProvider.php` — adicionar no `boot()` (após o `authenticateUsing`):

```php
Fortify::createUsersUsing(\App\Actions\Fortify\CreateNewUser::class);
```

- [ ] **Step 5: Rodar (deve passar)**

Run: `docker exec cema-app php artisan test --filter=CadastroTest`
Expected: PASS (2 testes).

- [ ] **Step 6: Pint + commit**

```bash
docker exec cema-app ./vendor/bin/pint
git add app/Actions/Fortify/CreateNewUser.php app/Providers/FortifyServiceProvider.php tests/Feature/Auth/CadastroTest.php
git commit -m "feat(auth): cadastro aberto cria frequentador + perfil (transacao)"
```

---

## Task 5: Login com Google (Socialite)

**Files:**
- Create: `app/Http/Controllers/Auth/GoogleController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Auth/GoogleLoginTest.php`

**Interfaces:**
- Consumes: Socialite, `User.google_id`, papel `frequentador`.
- Produces: `google.redirect` + `google.callback`; `GoogleController` casa por `google_id`→e-mail, cria `frequentador` quando novo, bloqueia `ativo=false`.

- [ ] **Step 1: Escrever os testes (TDD, com Socialite mockado)**

Create `tests/Feature/Auth/GoogleLoginTest.php`:

```php
<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GoogleLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('frequentador', 'web');
    }

    private function mockGoogle(string $id, string $email, string $nome = 'Membro Google'): void
    {
        $abstract = Mockery::mock(SocialiteUser::class);
        $abstract->shouldReceive('getId')->andReturn($id);
        $abstract->shouldReceive('getEmail')->andReturn($email);
        $abstract->shouldReceive('getName')->andReturn($nome);
        $abstract->shouldReceive('getNickname')->andReturn(null);
        $provider = Mockery::mock('Laravel\Socialite\Contracts\Provider');
        $provider->shouldReceive('user')->andReturn($abstract);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    public function test_google_cria_frequentador_novo(): void
    {
        $this->mockGoogle('g-123', 'novo@gmail.com');

        $this->get('/auth/google/callback')->assertRedirect('/');

        $user = User::where('email', 'novo@gmail.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('g-123', $user->google_id);
        $this->assertTrue($user->hasRole('frequentador'));
        $this->assertNotNull($user->perfil);
        $this->assertAuthenticatedAs($user);
    }

    public function test_google_casa_usuario_existente_por_email_e_grava_google_id(): void
    {
        $user = User::factory()->create(['email' => 'migrado@gmail.com', 'google_id' => null, 'ativo' => true]);
        $this->mockGoogle('g-999', 'migrado@gmail.com');

        $this->get('/auth/google/callback')->assertRedirect('/');
        $this->assertSame('g-999', $user->fresh()->google_id);
        $this->assertAuthenticatedAs($user);
    }

    public function test_google_conta_inativa_bloqueada(): void
    {
        User::factory()->create(['email' => 'inativo@gmail.com', 'ativo' => false]);
        $this->mockGoogle('g-000', 'inativo@gmail.com');

        $this->get('/auth/google/callback')->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
```

- [ ] **Step 2: Rodar (deve falhar — rota/controller não existem)**

Run: `docker exec cema-app php artisan test --filter=GoogleLoginTest`
Expected: FAIL (404 / classe ausente).

- [ ] **Step 3: Implementar o `GoogleController`**

Create `app/Http/Controllers/Auth/GoogleController.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-04

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class GoogleController extends Controller
{
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback(): RedirectResponse
    {
        $g = Socialite::driver('google')->user();

        $user = User::where('google_id', $g->getId())->first()
            ?? User::where('email', $g->getEmail())->first();

        if ($user) {
            if (! $user->ativo) {
                return redirect()->route('login')
                    ->withErrors(['email' => 'Sua conta está inativa. Fale com a secretaria da casa.']);
            }
            if (! $user->google_id) {
                $user->forceFill(['google_id' => $g->getId()])->save();
            }
        } else {
            $user = DB::transaction(function () use ($g) {
                $novo = User::create([
                    'name' => $g->getName() ?: $g->getNickname() ?: 'Membro',
                    'email' => mb_strtolower(trim($g->getEmail())),
                    'password' => Hash::make(Str::random(64)), // inutilizável; "esqueci a senha" cria uma local
                    'google_id' => $g->getId(),
                    'ativo' => true,
                    'email_verified_at' => now(),
                ]);
                $novo->assignRole('frequentador');
                $novo->perfil()->create([]);

                return $novo;
            });
        }

        Auth::login($user, remember: true);

        return redirect()->intended('/');
    }
}
```

- [ ] **Step 4: Rotas do Google (antes do fallback)**

Modify `routes/web.php` — adicionar (junto ao bloco de auth, fora do grupo `guest` para o callback funcionar em qualquer estado; import `use App\Http\Controllers\Auth\GoogleController;`):

```php
Route::get('/auth/google', [GoogleController::class, 'redirect'])->name('google.redirect');
Route::get('/auth/google/callback', [GoogleController::class, 'callback'])->name('google.callback');
```

- [ ] **Step 5: Rodar (deve passar)**

Run: `docker exec cema-app php artisan test --filter=GoogleLoginTest`
Expected: PASS (3 testes).

- [ ] **Step 6: Pint + commit**

```bash
docker exec cema-app ./vendor/bin/pint
git add app/Http/Controllers/Auth/GoogleController.php routes/web.php tests/Feature/Auth/GoogleLoginTest.php
git commit -m "feat(auth): login com Google (Socialite) casando por google_id/e-mail"
```

---

## Task 6: Reset de senha + Google-define-senha + verificação final

**Files:**
- Test: `tests/Feature/Auth/ResetSenhaTest.php`

**Interfaces:**
- Consumes: broker `users` (Fortify reset), `google_id` (usuário sem senha usável).
- Produces: cobertura do reset e do fluxo "usuário Google define senha via esqueci a senha".

- [ ] **Step 1: Escrever os testes (o comportamento é 100% Fortify/broker — só cobrir)**

Create `tests/Feature/Auth/ResetSenhaTest.php`:

```php
<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Tests\TestCase;

class ResetSenhaTest extends TestCase
{
    use RefreshDatabase;

    public function test_solicita_link_de_reset(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'quem@x.com']);

        $this->post('/esqueci-a-senha', ['email' => 'quem@x.com'])->assertSessionHasNoErrors();
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_usuario_google_define_senha_via_reset_e_loga(): void
    {
        // usuário criado via Google tem senha aleatória inutilizável, mas tem e-mail
        $user = User::factory()->create([
            'email' => 'google@x.com',
            'google_id' => 'g-abc',
            'password' => Hash::make(Str::random(64)),
        ]);

        $token = Password::broker()->createToken($user);

        $this->post('/redefinir-senha', [
            'token' => $token,
            'email' => 'google@x.com',
            'password' => 'nova-senha-forte-2026',
            'password_confirmation' => 'nova-senha-forte-2026',
        ])->assertSessionHasNoErrors();

        // agora loga com a senha local recém-criada
        $this->post('/entrar', ['email' => 'google@x.com', 'password' => 'nova-senha-forte-2026'])
            ->assertRedirect('/');
        $this->assertAuthenticatedAs($user->fresh());
    }
}
```

> Nota: `test_usuario_google_define_senha...` depende do `authenticateUsing` (Task 3) e das rotas de reset (Task 2). Se `ativo` não estiver setado no factory, garantir `ativo => true`.

- [ ] **Step 2: Rodar**

Run: `docker exec cema-app php artisan test --filter=ResetSenhaTest`
Expected: PASS (2 testes). (Se a factory de `User` não setar `ativo`, adicionar `'ativo' => true` nos dois usuários.)

- [ ] **Step 3: Pint + commit**

```bash
docker exec cema-app ./vendor/bin/pint
git add tests/Feature/Auth/ResetSenhaTest.php
git commit -m "test(auth): reset de senha + usuario Google define senha via esqueci a senha"
```

- [ ] **Step 4: Suíte completa + Pint (verificação final)**

Run: `docker exec cema-app php artisan test`
Expected: toda a suíte verde (incluindo a suíte pré-existente do PR #7 e do front).

Run: `docker exec cema-app ./vendor/bin/pint --test`
Expected: sem drift de estilo.

- [ ] **Step 5: Verificação manual (dev, com o túnel/credenciais)**

- `http://localhost:8000/cadastro` cria conta e loga (vira `frequentador`).
- `http://localhost:8000/entrar` loga com um usuário migrado (senha antiga → moderniza).
- "Esqueci a senha" gera e-mail no Mailpit (`localhost:8025`) e o link `/redefinir-senha/{token}` redefine.
- `/admin` continua exigindo diretor/admin (Filament intacto).
- (Google exige `GOOGLE_CLIENT_ID/SECRET` no `.env` + o redirect URI no Console.)

---

## Critério de pronto (checklist final)

- [ ] Cadastro aberto cria `frequentador` + `perfis_membro` + `email_verified_at`, e já loga.
- [ ] Login por e-mail-senha (nova e legada com rehash) funciona; `ativo=false` barrado com mensagem específica; credencial errada genérica.
- [ ] "Lembrar de mim" persiste a sessão.
- [ ] Google casa por `google_id`/e-mail, cria `frequentador` novo, bloqueia inativo.
- [ ] Reset por e-mail funciona (Mailpit); usuário Google define senha local via reset.
- [ ] `Route::fallback` mantém o 301 de slug de post; rotas de auth resolvem.
- [ ] Filament intacto; suíte verde; Pint limpo.
