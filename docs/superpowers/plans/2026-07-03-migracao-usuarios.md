# Migração de Usuários — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Importar os ~145 usuários não-admin do WordPress legado para o site novo — classificados (papel, setores com função, cargos, sócio, perfil) e mantendo a senha (rehash transparente) — e geri-los no admin Filament.

**Architecture:** Espelha o namespace `App\Importacao` das fatias anteriores (interface `Leitor*` + `*Mysql` + `Fake`, `Transformador*`, `Importador*`, comando `cema:importar-*`). RBAC via `spatie/laravel-permission` (4 papéis + coluna `nivel`, sem permissões finas). Senha legada validada por um `Hasher` custom que estende o BcryptHasher. De-para de estrutura organizacional num `GlossarioUsuarios`.

**Tech Stack:** PHP 8.3 · Laravel 13 · Filament 5 · Livewire 4 · MySQL 8 (dev) / SQLite (testes) · spatie/laravel-permission · Docker.

## Global Constraints

- **Migrations INCREMENTAIS.** PROIBIDO `migrate:fresh`, `migrate:refresh`, `migrate:reset`, `db:wipe`, seed/factory destrutivo no banco de dev — apagam palestras/blog/agenda/mídia já importados. Só `php artisan migrate`.
- **Legado é SOMENTE LEITURA** (`SELECT`). Nunca `INSERT/UPDATE/DELETE/DDL` na conexão `legado`; nunca commitar dump nem PII.
- **Identidade = e-mail** (0 vazios/duplicados no legado). `user_login` do WP descartado como credencial (só `origem_legado_id`).
- **Não migram:** `administrator` (4 contas técnicas) e `subscriber` (1). Ambos logados, não criados.
- **`email_verified_at` preenchido** na importação (contas ativas).
- **Coordenação:** slug `Coordenador de X` → setor-base X + `funcao=coordenador`; nunca criar setor "Coordenador de…".
- **Todos os comandos rodam no container:** prefixe com `docker exec cema-app` (ex.: `docker exec cema-app php artisan test`).
- **Portabilidade SQLite×MySQL:** testes rodam em SQLite; colunas `date` seguem o padrão do projeto (mutator Attribute, não cast). `RefreshDatabase` só afeta o banco de teste, nunca o dev.
- **Pint antes de commitar** (`docker exec cema-app ./vendor/bin/pint`) — o CI roda `pint --test`.
- **Cabeçalho de autoria** em todo arquivo PHP novo relevante: `// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-03`.

Referência de design: `docs/superpowers/specs/2026-07-03-migracao-usuarios-design.md`.

---

## File Structure

**Migrations** (`database/migrations/`): coluna `nivel` em roles; catálogos `departamentos`/`setores`/`cargos`/`atributos`; colunas em `users`; `perfis_membro`; `cursos_realizados`; pivôs `setor_usuario`/`cargo_usuario`/`atributo_usuario`.

**Models** (`app/Models/`): `Departamento`, `Setor`, `Cargo`, `Atributo`, `PerfilMembro`, `CursoRealizado`; ampliar `User`.

**Domínio/importação** (`app/`):
- `App\Auth\HasherLegadoCema` — verificação de hash `$wp$`/`$P$`.
- `App\Importacao\GlossarioUsuarios` — de-para (papéis, departamentos, setores, cargos).
- `App\Importacao\LeitorUsuarios` (interface) · `LeitorUsuariosMysql` · `LeitorUsuariosFake` (teste).
- `App\Importacao\TransformadorUsuarios` — sanitização/normalização/resolução.
- `App\Importacao\ImportadorUsuarios` — upsert idempotente.
- `App\Console\Commands\ImportarUsuarios` — comando `cema:importar-usuarios`.

**Seeders** (`database/seeders/`): `EstruturaCemaSeeder` (papéis/deptos/setores/cargos/atributo), `AdminSeeder`.

**Filament** (`app/Filament/Resources/`): `DepartamentoResource`, `SetorResource`, `CargoResource`, `UsuarioResource`.

---

## Task 1: Instalar spatie/laravel-permission + coluna `nivel`

**Files:**
- Modify: `composer.json` (via composer require)
- Create: `config/permission.php`, migrations do Spatie (via publish)
- Create: `database/migrations/2026_07_03_000001_add_nivel_to_roles_table.php`
- Modify: `app/Models/User.php`
- Test: `tests/Feature/Usuarios/RbacSetupTest.php`

**Interfaces:**
- Produces: `User` usa `Spatie\Permission\Traits\HasRoles`; tabela `roles` tem coluna `nivel` (int, default 0). Papéis serão criados no seeder da Task 5.

- [ ] **Step 1: Instalar o pacote**

Run: `docker exec cema-app composer require spatie/laravel-permission:^6.9`
Expected: pacote adicionado; `Spatie\Permission\PermissionServiceProvider` auto-descoberto.

- [ ] **Step 2: Publicar migrations e config**

Run: `docker exec cema-app php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"`
Expected: cria `config/permission.php` e a migration `..._create_permission_tables.php`.

- [ ] **Step 3: Criar migration da coluna `nivel`**

Create `database/migrations/2026_07_03_000001_add_nivel_to_roles_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->unsignedSmallInteger('nivel')->default(0)->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('nivel');
        });
    }
};
```

- [ ] **Step 4: Rodar as migrations (incremental)**

Run: `docker exec cema-app php artisan migrate`
Expected: tabelas do Spatie criadas + coluna `nivel`. NÃO usar `migrate:fresh`.

- [ ] **Step 5: Adicionar `HasRoles` ao User**

Modify `app/Models/User.php` — adicionar o import e o trait:

```php
use Spatie\Permission\Traits\HasRoles;
// ...
class User extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable, HasRoles;
```

- [ ] **Step 6: Teste de fumaça do RBAC**

Create `tests/Feature/Usuarios/RbacSetupTest.php`:

```php
<?php

namespace Tests\Feature\Usuarios;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RbacSetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_usuario_recebe_papel_com_nivel(): void
    {
        $papel = Role::create(['name' => 'diretor', 'nivel' => 30]);
        $user = User::factory()->create();

        $user->assignRole($papel);

        $this->assertTrue($user->hasRole('diretor'));
        $this->assertSame(30, (int) Role::findByName('diretor')->nivel);
    }
}
```

- [ ] **Step 7: Rodar o teste**

Run: `docker exec cema-app php artisan test --filter=RbacSetupTest`
Expected: PASS.

- [ ] **Step 8: Pint + commit**

```bash
docker exec cema-app ./vendor/bin/pint
git add composer.json composer.lock config/permission.php database/migrations app/Models/User.php tests/Feature/Usuarios/RbacSetupTest.php
git commit -m "feat(usuarios): instala spatie/laravel-permission + coluna nivel em roles"
```

---

## Task 2: Migrations + Models dos catálogos

**Files:**
- Create: `database/migrations/2026_07_03_000002_create_departamentos_table.php`, `..._000003_create_setores_table.php`, `..._000004_create_cargos_table.php`, `..._000005_create_atributos_table.php`
- Create: `app/Models/Departamento.php`, `Setor.php`, `Cargo.php`, `Atributo.php`
- Test: `tests/Feature/Usuarios/CatalogosTest.php`

**Interfaces:**
- Produces: `Departamento` (sigla, nome, slug, ativo, ordem); `Setor` (nome, slug, departamento_id?, provisorio, ativo) `belongsTo` Departamento; `Cargo` (nome, slug, departamento_id?, institucional, ativo); `Atributo` (nome, slug). Todos com `$fillable` e resolução por `slug`.

- [ ] **Step 1: Migration departamentos**

Create `database/migrations/2026_07_03_000002_create_departamentos_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departamentos', function (Blueprint $table) {
            $table->id();
            $table->string('sigla')->unique();
            $table->string('nome');
            $table->string('slug')->unique();
            $table->text('descricao')->nullable();
            $table->boolean('ativo')->default(true);
            $table->unsignedSmallInteger('ordem')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departamentos');
    }
};
```

- [ ] **Step 2: Migration setores**

Create `database/migrations/2026_07_03_000003_create_setores_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('setores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('departamento_id')->nullable()->constrained('departamentos')->nullOnDelete();
            $table->string('nome');
            $table->string('slug')->unique();
            $table->boolean('provisorio')->default(false);
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('setores');
    }
};
```

- [ ] **Step 3: Migration cargos**

Create `database/migrations/2026_07_03_000004_create_cargos_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cargos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('departamento_id')->nullable()->constrained('departamentos')->nullOnDelete();
            $table->string('nome');
            $table->string('slug')->unique();
            $table->boolean('institucional')->default(false);
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cargos');
    }
};
```

- [ ] **Step 4: Migration atributos**

Create `database/migrations/2026_07_03_000005_create_atributos_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('atributos', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('slug')->unique();
            $table->text('descricao')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('atributos');
    }
};
```

- [ ] **Step 5: Models dos catálogos**

Create `app/Models/Departamento.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-03

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Departamento extends Model
{
    protected $fillable = ['sigla', 'nome', 'slug', 'descricao', 'ativo', 'ordem'];

    protected function casts(): array
    {
        return ['ativo' => 'boolean'];
    }

    public function setores(): HasMany
    {
        return $this->hasMany(Setor::class);
    }

    public function cargos(): HasMany
    {
        return $this->hasMany(Cargo::class);
    }
}
```

Create `app/Models/Setor.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-03

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Setor extends Model
{
    protected $fillable = ['departamento_id', 'nome', 'slug', 'provisorio', 'ativo'];

    protected function casts(): array
    {
        return ['provisorio' => 'boolean', 'ativo' => 'boolean'];
    }

    public function departamento(): BelongsTo
    {
        return $this->belongsTo(Departamento::class);
    }

    public function usuarios(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'setor_usuario')
            ->withPivot('funcao', 'desde')->withTimestamps();
    }
}
```

Create `app/Models/Cargo.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-03

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Cargo extends Model
{
    protected $fillable = ['departamento_id', 'nome', 'slug', 'institucional', 'ativo'];

    protected function casts(): array
    {
        return ['institucional' => 'boolean', 'ativo' => 'boolean'];
    }

    public function departamento(): BelongsTo
    {
        return $this->belongsTo(Departamento::class);
    }

    public function usuarios(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'cargo_usuario')->withTimestamps();
    }
}
```

Create `app/Models/Atributo.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-03

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Atributo extends Model
{
    protected $fillable = ['nome', 'slug', 'descricao'];

    public function usuarios(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'atributo_usuario')
            ->withPivot('desde', 'ate')->withTimestamps();
    }
}
```

- [ ] **Step 6: Rodar migrations**

Run: `docker exec cema-app php artisan migrate`
Expected: 4 tabelas criadas.

- [ ] **Step 7: Teste dos catálogos**

Create `tests/Feature/Usuarios/CatalogosTest.php`:

```php
<?php

namespace Tests\Feature\Usuarios;

use App\Models\Departamento;
use App\Models\Setor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogosTest extends TestCase
{
    use RefreshDatabase;

    public function test_setor_pertence_a_departamento_e_pode_ser_sem_departamento(): void
    {
        $depto = Departamento::create(['sigla' => 'DEPAE', 'nome' => 'Assistência Espiritual', 'slug' => 'depae']);
        $comDepto = Setor::create(['nome' => 'Médium', 'slug' => 'medium', 'departamento_id' => $depto->id]);
        $pamana = Setor::create(['nome' => 'PAMANA', 'slug' => 'pamana', 'departamento_id' => null]);

        $this->assertSame('DEPAE', $comDepto->departamento->sigla);
        $this->assertNull($pamana->departamento);
    }
}
```

- [ ] **Step 8: Rodar teste + Pint + commit**

Run: `docker exec cema-app php artisan test --filter=CatalogosTest`
Expected: PASS.

```bash
docker exec cema-app ./vendor/bin/pint
git add database/migrations app/Models/Departamento.php app/Models/Setor.php app/Models/Cargo.php app/Models/Atributo.php tests/Feature/Usuarios/CatalogosTest.php
git commit -m "feat(usuarios): catalogos departamentos/setores/cargos/atributos"
```

---

## Task 3: Colunas em `users`, `perfis_membro`, `cursos_realizados`

**Files:**
- Create: `database/migrations/2026_07_03_000006_add_campos_cema_to_users_table.php`, `..._000007_create_perfis_membro_table.php`, `..._000008_create_cursos_realizados_table.php`
- Create: `app/Models/PerfilMembro.php`, `app/Models/CursoRealizado.php`
- Modify: `app/Models/User.php`
- Test: `tests/Feature/Usuarios/PerfilTest.php`

**Interfaces:**
- Produces: `users` ganha `origem_legado_id` (unsigned unique nullable), `socio` (bool indexado), `ativo` (bool). `User` tem `perfil()` (hasOne), `cursos()` (hasMany). `PerfilMembro` (whatsapp, whatsapp_publico, data_nascimento, endereco, foto_perfil).

- [ ] **Step 1: Migration de colunas em users**

Create `database/migrations/2026_07_03_000006_add_campos_cema_to_users_table.php`:

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
            $table->unsignedBigInteger('origem_legado_id')->nullable()->unique()->after('id');
            $table->boolean('socio')->default(false)->index()->after('email');
            $table->boolean('ativo')->default(true)->after('socio');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['origem_legado_id', 'socio', 'ativo']);
        });
    }
};
```

- [ ] **Step 2: Migration perfis_membro**

Create `database/migrations/2026_07_03_000007_create_perfis_membro_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('perfis_membro', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('whatsapp')->nullable();
            $table->boolean('whatsapp_publico')->default(false);
            $table->date('data_nascimento')->nullable();
            $table->text('endereco')->nullable();
            $table->string('foto_perfil')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('perfis_membro');
    }
};
```

- [ ] **Step 3: Migration cursos_realizados**

Create `database/migrations/2026_07_03_000008_create_cursos_realizados_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cursos_realizados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('nome');
            $table->unsignedSmallInteger('ano')->nullable();
            $table->string('local')->nullable();
            $table->unsignedSmallInteger('ordem')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cursos_realizados');
    }
};
```

- [ ] **Step 4: Models de perfil**

Create `app/Models/PerfilMembro.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-03

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PerfilMembro extends Model
{
    protected $table = 'perfis_membro';

    protected $fillable = [
        'user_id', 'whatsapp', 'whatsapp_publico', 'data_nascimento', 'endereco', 'foto_perfil',
    ];

    protected function casts(): array
    {
        return ['whatsapp_publico' => 'boolean', 'data_nascimento' => 'date'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

Create `app/Models/CursoRealizado.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-03

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CursoRealizado extends Model
{
    protected $table = 'cursos_realizados';

    protected $fillable = ['user_id', 'nome', 'ano', 'local', 'ordem'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

- [ ] **Step 5: Relações + fillable no User**

Modify `app/Models/User.php` — atualizar o atributo `#[Fillable(...)]` e adicionar relações:

```php
#[Fillable(['name', 'email', 'password', 'origem_legado_id', 'socio', 'ativo'])]
```

Adicionar os métodos (imports `HasOne`, `HasMany`, `BelongsToMany`):

```php
public function perfil(): \Illuminate\Database\Eloquent\Relations\HasOne
{
    return $this->hasOne(PerfilMembro::class);
}

public function cursos(): \Illuminate\Database\Eloquent\Relations\HasMany
{
    return $this->hasMany(CursoRealizado::class);
}

public function setores(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
{
    return $this->belongsToMany(Setor::class, 'setor_usuario')
        ->withPivot('funcao', 'desde')->withTimestamps();
}

public function cargos(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
{
    return $this->belongsToMany(Cargo::class, 'cargo_usuario')->withTimestamps();
}

public function atributos(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
{
    return $this->belongsToMany(Atributo::class, 'atributo_usuario')
        ->withPivot('desde', 'ate')->withTimestamps();
}
```

Adicionar `socio` e `ativo` ao `casts()`:

```php
'socio' => 'boolean',
'ativo' => 'boolean',
```

- [ ] **Step 6: Rodar migrations**

Run: `docker exec cema-app php artisan migrate`
Expected: colunas + 2 tabelas criadas.

> Nota: os pivôs (`setores()`/`cargos()`/`atributos()`) só terão tabela na Task 4. Não exercite essas relações em testes até lá.

- [ ] **Step 7: Teste do perfil 1:1**

Create `tests/Feature/Usuarios/PerfilTest.php`:

```php
<?php

namespace Tests\Feature\Usuarios;

use App\Models\PerfilMembro;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PerfilTest extends TestCase
{
    use RefreshDatabase;

    public function test_usuario_tem_um_perfil_membro(): void
    {
        $user = User::factory()->create(['socio' => true]);
        PerfilMembro::create([
            'user_id' => $user->id,
            'whatsapp' => '61999998888',
            'endereco' => 'Qd 1 Casa 2 - Planaltina - DF',
        ]);

        $this->assertTrue($user->fresh()->socio);
        $this->assertSame('61999998888', $user->perfil->whatsapp);
    }
}
```

- [ ] **Step 8: Rodar teste + Pint + commit**

Run: `docker exec cema-app php artisan test --filter=PerfilTest`
Expected: PASS.

```bash
docker exec cema-app ./vendor/bin/pint
git add database/migrations app/Models/PerfilMembro.php app/Models/CursoRealizado.php app/Models/User.php tests/Feature/Usuarios/PerfilTest.php
git commit -m "feat(usuarios): perfil de membro, cursos e colunas em users"
```

---

## Task 4: Pivôs de lotação

**Files:**
- Create: `database/migrations/2026_07_03_000009_create_setor_usuario_table.php`, `..._000010_create_cargo_usuario_table.php`, `..._000011_create_atributo_usuario_table.php`
- Test: `tests/Feature/Usuarios/PivosTest.php`

**Interfaces:**
- Produces: `setor_usuario` (setor_id, user_id, funcao, desde), `cargo_usuario` (cargo_id, user_id), `atributo_usuario` (atributo_id, user_id, desde, ate). Habilita `User::setores()/cargos()/atributos()` (Task 3).

- [ ] **Step 1: Migration setor_usuario**

Create `database/migrations/2026_07_03_000009_create_setor_usuario_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('setor_usuario', function (Blueprint $table) {
            $table->foreignId('setor_id')->constrained('setores')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('funcao', ['membro', 'coordenador'])->default('membro');
            $table->date('desde')->nullable();
            $table->timestamps();
            $table->primary(['setor_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('setor_usuario');
    }
};
```

- [ ] **Step 2: Migration cargo_usuario**

Create `database/migrations/2026_07_03_000010_create_cargo_usuario_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cargo_usuario', function (Blueprint $table) {
            $table->foreignId('cargo_id')->constrained('cargos')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['cargo_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cargo_usuario');
    }
};
```

- [ ] **Step 3: Migration atributo_usuario**

Create `database/migrations/2026_07_03_000011_create_atributo_usuario_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('atributo_usuario', function (Blueprint $table) {
            $table->foreignId('atributo_id')->constrained('atributos')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('desde')->nullable();
            $table->date('ate')->nullable();
            $table->timestamps();
            $table->primary(['atributo_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('atributo_usuario');
    }
};
```

- [ ] **Step 4: Rodar migrations**

Run: `docker exec cema-app php artisan migrate`
Expected: 3 pivôs criados.

- [ ] **Step 5: Teste dos pivôs (N:N com função)**

Create `tests/Feature/Usuarios/PivosTest.php`:

```php
<?php

namespace Tests\Feature\Usuarios;

use App\Models\Setor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PivosTest extends TestCase
{
    use RefreshDatabase;

    public function test_usuario_em_varios_setores_com_funcao(): void
    {
        $user = User::factory()->create();
        $brecho = Setor::create(['nome' => 'Brechó', 'slug' => 'brecho']);
        $campanha = Setor::create(['nome' => 'Campanha Auta de Souza', 'slug' => 'campanha-auta-de-souza']);

        $user->setores()->attach([
            $brecho->id => ['funcao' => 'membro'],
            $campanha->id => ['funcao' => 'coordenador'],
        ]);

        $this->assertCount(2, $user->setores);
        $this->assertSame('coordenador', $user->setores()->find($campanha->id)->pivot->funcao);
    }
}
```

- [ ] **Step 6: Rodar teste + Pint + commit**

Run: `docker exec cema-app php artisan test --filter=PivosTest`
Expected: PASS.

```bash
docker exec cema-app ./vendor/bin/pint
git add database/migrations tests/Feature/Usuarios/PivosTest.php
git commit -m "feat(usuarios): pivos setor_usuario/cargo_usuario/atributo_usuario"
```

---

## Task 5: Glossário (de-para) + Seeder de estrutura

**Files:**
- Create: `app/Importacao/GlossarioUsuarios.php`
- Create: `database/seeders/EstruturaCemaSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php` (registrar, sem seeds destrutivos)
- Test: `tests/Feature/Usuarios/EstruturaSeederTest.php`

**Interfaces:**
- Produces: `GlossarioUsuarios::PAPEIS` (slug=>nivel), `::DEPARTAMENTOS` (sigla=>nome), `::SETORES` (slugLegado=>[nome, sigla?, funcao]), `::CARGOS` (slugLegado=>[nome, sigla?, institucional]). `EstruturaCemaSeeder` popula papéis/deptos/setores/cargos/atributo `socio` de forma **idempotente por slug**. Consumido pela Task 7/9.

- [ ] **Step 1: Criar o Glossário**

Create `app/Importacao/GlossarioUsuarios.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-03

namespace App\Importacao;

class GlossarioUsuarios
{
    /** Papéis (slug => nível). Ordem = hierarquia linear. */
    public const PAPEIS = [
        'frequentador' => 10,
        'trabalhador' => 20,
        'diretor' => 30,
        'administrador' => 100,
    ];

    /** Departamentos (sigla => nome). */
    public const DEPARTAMENTOS = [
        'DAS' => 'Assistência Social',
        'DDA' => 'Divulgação e Artes',
        'DED' => 'Estudos Doutrinários',
        'DEMAPA' => 'Manutenção Patrimonial',
        'DEPAE' => 'Assistência Espiritual',
        'DEPRO' => 'Promoções e Eventos',
        'DIJ' => 'Infância e Juventude',
        'DECOM' => 'Comunicação e Multimídia',
    ];

    /** slug legado do setor => [nome do setor-base, sigla depto|null, funcao]. */
    public const SETORES = [
        'atendimento_fraterno' => ['Atendimento Fraterno', 'DEPAE', 'membro'],
        'medium' => ['Médium', 'DEPAE', 'membro'],
        'passista_passe_magnetico' => ['Passe Magnético', 'DEPAE', 'membro'],
        'harmonizacao' => ['Harmonização', 'DECOM', 'membro'],
        'brecho' => ['Brechó', 'DEPRO', 'membro'],
        'corte_de_verdurasopa' => ['Corte de Verduras / Sopa', 'DAS', 'membro'],
        'recepcionista' => ['Recepção', 'DAS', 'membro'],
        'caravaneiro_de_auta_de_souza' => ['Campanha Auta de Souza', 'DDA', 'membro'],
        'coordenador_da_campanha_auta_de_souza' => ['Campanha Auta de Souza', 'DDA', 'coordenador'],
        'coralista_do_cemad' => ['Coral CEMAD', 'DDA', 'membro'],
        'teluzes' => ['TELUZES (Teatro)', 'DDA', 'membro'],
        'coolaborador_decom' => ['Colaboração DECOM', 'DECOM', 'membro'],
        'evangelizador_da_infancia' => ['Evangelização da Infância', 'DIJ', 'membro'],
        'evangelizador_da_mocidade' => ['Evangelização da Mocidade', 'DIJ', 'membro'],
        'evangelizador_do_ded' => ['Evangelização (DED)', 'DED', 'membro'],
        'livraria' => ['Livraria', 'DED', 'membro'],
        'pamana' => ['PAMANA', null, 'membro'],
    ];

    /** slug legado do cargo => [nome, sigla depto|null, institucional]. */
    public const CARGOS = [
        'diretor_dda' => ['Diretor do DDA', 'DDA', false],
        'diretor_ded' => ['Diretor do DED', 'DED', false],
        'diretor_decom' => ['Diretor do DECOM', 'DECOM', false],
        'diretor_demapa' => ['Diretor do DEMAPA', 'DEMAPA', false],
        'diretor_depae' => ['Diretor do DEPAE', 'DEPAE', false],
        'diretor_depro' => ['Diretor do DEPRO', 'DEPRO', false],
        'diretor_dij' => ['Diretor do DIJ', 'DIJ', false],
        'diretor_presidente' => ['Presidente', null, true],
        'conselho_diretor' => ['Conselho Diretor', null, true],
        'conselho_fiscal' => ['Conselho Fiscal', null, true],
        'secretario' => ['Secretário', null, true],
        'tesoureiro' => ['Tesoureiro', null, true],
    ];

    /** Cargos de catálogo sem ocupante no legado (completude). */
    public const CARGOS_EXTRA = [
        'diretor_das' => ['Diretor do DAS', 'DAS', false],
    ];
}
```

- [ ] **Step 2: Criar o EstruturaCemaSeeder**

Create `database/seeders/EstruturaCemaSeeder.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-03

namespace Database\Seeders;

use App\Importacao\GlossarioUsuarios;
use App\Models\Atributo;
use App\Models\Cargo;
use App\Models\Departamento;
use App\Models\Setor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class EstruturaCemaSeeder extends Seeder
{
    public function run(): void
    {
        foreach (GlossarioUsuarios::PAPEIS as $slug => $nivel) {
            Role::updateOrCreate(
                ['name' => $slug, 'guard_name' => 'web'],
                ['nivel' => $nivel],
            );
        }

        $ordem = 0;
        foreach (GlossarioUsuarios::DEPARTAMENTOS as $sigla => $nome) {
            Departamento::updateOrCreate(
                ['slug' => Str::slug($sigla)],
                ['sigla' => $sigla, 'nome' => $nome, 'ordem' => $ordem++],
            );
        }

        $siglaParaId = Departamento::pluck('id', 'sigla');

        foreach (GlossarioUsuarios::SETORES as [$nome, $sigla, $funcao]) {
            Setor::updateOrCreate(
                ['slug' => Str::slug($nome)],
                ['nome' => $nome, 'departamento_id' => $sigla ? $siglaParaId[$sigla] : null],
            );
        }

        $cargos = GlossarioUsuarios::CARGOS + GlossarioUsuarios::CARGOS_EXTRA;
        foreach ($cargos as [$nome, $sigla, $institucional]) {
            Cargo::updateOrCreate(
                ['slug' => Str::slug($nome)],
                [
                    'nome' => $nome,
                    'departamento_id' => $sigla ? $siglaParaId[$sigla] : null,
                    'institucional' => $institucional,
                ],
            );
        }

        Atributo::updateOrCreate(['slug' => 'socio'], ['nome' => 'Sócio']);
    }
}
```

- [ ] **Step 3: Registrar o seeder (não-destrutivo)**

Modify `database/seeders/DatabaseSeeder.php` — no método `run()`, adicionar:

```php
$this->call(EstruturaCemaSeeder::class);
```

- [ ] **Step 4: Teste de idempotência do seeder**

Create `tests/Feature/Usuarios/EstruturaSeederTest.php`:

```php
<?php

namespace Tests\Feature\Usuarios;

use App\Models\Cargo;
use App\Models\Departamento;
use App\Models\Setor;
use Database\Seeders\EstruturaCemaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EstruturaSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_idempotente_cria_estrutura(): void
    {
        (new EstruturaCemaSeeder)->run();
        (new EstruturaCemaSeeder)->run(); // 2x → sem duplicar

        $this->assertSame(4, Role::count());
        $this->assertSame(8, Departamento::count());
        $this->assertSame(16, Setor::count()); // 17 slugs → 16 setores-base (campanha colapsa)
        $this->assertSame(13, Cargo::count()); // 12 + Diretor do DAS
        $this->assertNull(Setor::where('slug', 'pamana')->first()->departamento_id);
        $this->assertSame(30, (int) Role::findByName('diretor')->nivel);
    }
}
```

- [ ] **Step 5: Rodar teste + Pint + commit**

Run: `docker exec cema-app php artisan test --filter=EstruturaSeederTest`
Expected: PASS.

```bash
docker exec cema-app ./vendor/bin/pint
git add app/Importacao/GlossarioUsuarios.php database/seeders tests/Feature/Usuarios/EstruturaSeederTest.php
git commit -m "feat(usuarios): glossario de-para + EstruturaCemaSeeder idempotente"
```

- [ ] **Step 6: Semear a estrutura no dev (incremental, seguro)**

Run: `docker exec cema-app php artisan db:seed --class=EstruturaCemaSeeder`
Expected: papéis/deptos/setores/cargos/atributo criados no banco de dev (idempotente).

---

## Task 6: Hasher de senha legada + rehash transparente

**Files:**
- Create: `app/Auth/HasherLegadoCema.php`
- Modify: `app/Providers/AppServiceProvider.php`, `config/hashing.php`, `.env`/`.env.example`
- Test: `tests/Unit/HasherLegadoCemaTest.php`

**Interfaces:**
- Produces: driver de hash `cema` que valida `$wp$` (pré-hash HMAC-SHA384 + `password_verify`) e `$P$`/`$H$` (phpass), delega bcrypt nativo ao pai, e `needsRehash()` → true para formatos legados.

- [ ] **Step 1: Escrever o teste (TDD) — valida os dois formatos**

Create `tests/Unit/HasherLegadoCemaTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Auth\HasherLegadoCema;
use PHPUnit\Framework\TestCase;

class HasherLegadoCemaTest extends TestCase
{
    private HasherLegadoCema $hasher;

    protected function setUp(): void
    {
        $this->hasher = new HasherLegadoCema(['rounds' => 10]);
    }

    public function test_valida_hash_wp_bcrypt(): void
    {
        // hash $wp$ gerado com a mesma receita do WP 6.8 para a senha 'segredo123'
        $pre = base64_encode(hash_hmac('sha384', 'segredo123', 'wp-sha384', true));
        $hash = '$wp'.password_hash($pre, PASSWORD_BCRYPT);

        $this->assertTrue($this->hasher->check('segredo123', $hash));
        $this->assertFalse($this->hasher->check('errada', $hash));
        $this->assertTrue($this->hasher->needsRehash($hash));
    }

    public function test_valida_hash_phpass(): void
    {
        // round-trip: gera um $P$ e confere
        $setting = '$P$B'.'k9d2Xa7Q';
        $hash = $this->hasher->phpass('MinhaSenha#2026', $setting);

        $this->assertTrue($this->hasher->check('MinhaSenha#2026', $hash));
        $this->assertFalse($this->hasher->check('outra', $hash));
        $this->assertTrue($this->hasher->needsRehash($hash));
    }

    public function test_bcrypt_nativo_passa_direto_e_nao_precisa_rehash(): void
    {
        $hash = $this->hasher->make('nativa');

        $this->assertTrue($this->hasher->check('nativa', $hash));
        $this->assertFalse($this->hasher->needsRehash($hash));
    }
}
```

- [ ] **Step 2: Rodar o teste (deve falhar — classe não existe)**

Run: `docker exec cema-app php artisan test --filter=HasherLegadoCemaTest`
Expected: FAIL ("Class App\Auth\HasherLegadoCema not found").

- [ ] **Step 3: Implementar o Hasher**

Create `app/Auth/HasherLegadoCema.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-03

namespace App\Auth;

use Illuminate\Hashing\BcryptHasher;

class HasherLegadoCema extends BcryptHasher
{
    private const ITOA64 = './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

    public function check(#[\SensitiveParameter] $value, $hashedValue, array $options = []): bool
    {
        if ($hashedValue === null || $hashedValue === '') {
            return false;
        }
        if (str_starts_with($hashedValue, '$wp')) {
            $pre = base64_encode(hash_hmac('sha384', trim((string) $value), 'wp-sha384', true));

            return password_verify($pre, substr($hashedValue, 3));
        }
        if (str_starts_with($hashedValue, '$P$') || str_starts_with($hashedValue, '$H$')) {
            return hash_equals($hashedValue, $this->phpass(trim((string) $value), $hashedValue));
        }

        return parent::check($value, $hashedValue, $options);
    }

    public function needsRehash($hashedValue, array $options = []): bool
    {
        $h = (string) $hashedValue;
        if (str_starts_with($h, '$wp') || str_starts_with($h, '$P$') || str_starts_with($h, '$H$')) {
            return true;
        }

        return parent::needsRehash($hashedValue, $options);
    }

    public function phpass(#[\SensitiveParameter] string $password, string $setting): string
    {
        $output = '*0';
        if (substr($setting, 0, 2) === $output) {
            $output = '*1';
        }
        if (substr($setting, 0, 3) !== '$P$' && substr($setting, 0, 3) !== '$H$') {
            return $output;
        }
        $countLog2 = strpos(self::ITOA64, $setting[3]);
        if ($countLog2 < 7 || $countLog2 > 30) {
            return $output;
        }
        $count = 1 << $countLog2;
        $salt = substr($setting, 4, 8);
        if (strlen($salt) !== 8) {
            return $output;
        }
        $hash = md5($salt.$password, true);
        do {
            $hash = md5($hash.$password, true);
        } while (--$count);

        return substr($setting, 0, 12).$this->encode64($hash, 16);
    }

    private function encode64(string $input, int $count): string
    {
        $output = '';
        $i = 0;
        do {
            $value = ord($input[$i++]);
            $output .= self::ITOA64[$value & 0x3f];
            if ($i < $count) {
                $value |= ord($input[$i]) << 8;
            }
            $output .= self::ITOA64[($value >> 6) & 0x3f];
            if ($i++ >= $count) {
                break;
            }
            if ($i < $count) {
                $value |= ord($input[$i]) << 16;
            }
            $output .= self::ITOA64[($value >> 12) & 0x3f];
            if ($i++ >= $count) {
                break;
            }
            $output .= self::ITOA64[($value >> 18) & 0x3f];
        } while ($i < $count);

        return $output;
    }
}
```

- [ ] **Step 4: Rodar o teste (deve passar)**

Run: `docker exec cema-app php artisan test --filter=HasherLegadoCemaTest`
Expected: PASS (3 testes).

- [ ] **Step 5: Registrar o driver `cema`**

Modify `app/Providers/AppServiceProvider.php` — no método `boot()`:

```php
\Illuminate\Support\Facades\Hash::extend('cema', function ($app) {
    return new \App\Auth\HasherLegadoCema($app['config']['hashing.bcrypt'] ?? []);
});
```

Modify `config/hashing.php` — trocar o driver default:

```php
'driver' => env('HASH_DRIVER', 'cema'),
```

Adicionar em `.env` e `.env.example`:

```
HASH_DRIVER=cema
```

Modify `app/Models/User.php` — **remover** `'password' => 'hashed'` do `casts()`.
Motivo: o cast re-hashearia os hashes legados `$wp$`/`$P$` na escrita (não passam em
`Hash::isHashed`), corrompendo a senha migrada. Sem o cast, o importador grava o hash
bruto e o rehash transparente moderniza no 1º login; a criação de usuários no
admin/seed hasheia explicitamente (Filament form com `Hash::make` + `AdminSeeder`).
O factory padrão já hasheia, então nada quebra.

- [ ] **Step 6: Teste de integração do rehash transparente via Auth**

Adicionar em `tests/Feature/Usuarios/RbacSetupTest.php` (ou novo `LoginRehashTest.php`):

```php
public function test_login_faz_rehash_de_hash_legado(): void
{
    $pre = base64_encode(hash_hmac('sha384', 'segredo123', 'wp-sha384', true));
    $user = \App\Models\User::factory()->create(['password' => '$wp'.password_hash($pre, PASSWORD_BCRYPT)]);

    $this->assertTrue(\Illuminate\Support\Facades\Auth::attempt(['email' => $user->email, 'password' => 'segredo123']));

    $novo = $user->fresh()->password;
    $this->assertStringStartsWith('$2y$', $novo); // virou bcrypt nativo
    $this->assertStringStartsNotWith('$wp', $novo);
}
```

> Se `assertStringStartsNotWith` não existir na versão do PHPUnit, usar `$this->assertFalse(str_starts_with($novo, '$wp'));`.

- [ ] **Step 7: Rodar teste + Pint + commit**

Run: `docker exec cema-app php artisan test --filter=HasherLegadoCemaTest && docker exec cema-app php artisan test --filter=RbacSetupTest`
Expected: PASS.

```bash
docker exec cema-app ./vendor/bin/pint
git add app/Auth/HasherLegadoCema.php app/Providers/AppServiceProvider.php config/hashing.php .env.example tests
git commit -m "feat(usuarios): hasher de senha legada (wp/phpass) + rehash transparente"
```

---

## Task 7: TransformadorUsuarios

**Files:**
- Create: `app/Importacao/TransformadorUsuarios.php`
- Test: `tests/Unit/TransformadorUsuariosTest.php`

**Interfaces:**
- Consumes: `GlossarioUsuarios` (Task 5).
- Produces:
  - `nomeTitulo(string): string` — Title Case com preposições minúsculas.
  - `flagTresEstados(?string): ?bool`.
  - `papelDe(array $rolesWp): ?string` — maior nível; null se só roles ignorados.
  - `resolverSetores(array $slugs): array` — lista de `['slug'=>setorSlug, 'funcao'=>...]` (regra de coordenação aplicada).
  - `resolverCargos(array $slugs): array` — lista de slugs de cargo resolvidos.

- [ ] **Step 1: Escrever o teste (TDD)**

Create `tests/Unit/TransformadorUsuariosTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Importacao\TransformadorUsuarios;
use PHPUnit\Framework\TestCase;

class TransformadorUsuariosTest extends TestCase
{
    private TransformadorUsuarios $t;

    protected function setUp(): void
    {
        $this->t = new TransformadorUsuarios;
    }

    public function test_nome_title_case_com_preposicoes(): void
    {
        $this->assertSame('Ana Karla da Silva', $this->t->nomeTitulo('ANA KARLA DA SILVA'));
        $this->assertSame('Ana Maria de Barros Amaral', $this->t->nomeTitulo('ana maria DE barros amaral'));
        $this->assertSame('Maria da Conceição Rocha', $this->t->nomeTitulo('MARIA DA CONCEIÇÃO ROCHA'));
    }

    public function test_flag_tres_estados(): void
    {
        $this->assertTrue($this->t->flagTresEstados('true'));
        $this->assertTrue($this->t->flagTresEstados('on'));
        $this->assertFalse($this->t->flagTresEstados('FALSE'));
        $this->assertNull($this->t->flagTresEstados(''));
        $this->assertNull($this->t->flagTresEstados(null));
    }

    public function test_papel_precedencia_maior_nivel(): void
    {
        $this->assertSame('diretor', $this->t->papelDe(['trabalhador', 'diretor']));
        $this->assertSame('frequentador', $this->t->papelDe(['frequentador']));
        $this->assertNull($this->t->papelDe(['administrator'])); // ignorado
        $this->assertNull($this->t->papelDe(['subscriber']));
    }

    public function test_resolver_setores_aplica_regra_coordenacao(): void
    {
        $r = $this->t->resolverSetores([
            'coordenador_da_campanha_auta_de_souza',
            'medium',
        ]);

        $slugs = array_column($r, 'funcao', 'slug');
        $this->assertSame('coordenador', $slugs['campanha-auta-de-souza']);
        $this->assertSame('membro', $slugs['medium']);
    }

    public function test_resolver_cargos(): void
    {
        $this->assertSame(['diretor-do-depae', 'tesoureiro'], $this->t->resolverCargos(['diretor_depae', 'tesoureiro']));
    }
}
```

- [ ] **Step 2: Rodar o teste (deve falhar)**

Run: `docker exec cema-app php artisan test --filter=TransformadorUsuariosTest`
Expected: FAIL (classe não existe).

- [ ] **Step 3: Implementar o Transformador**

Create `app/Importacao/TransformadorUsuarios.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-03

namespace App\Importacao;

use Illuminate\Support\Str;

class TransformadorUsuarios
{
    private const PREPOSICOES = ['de', 'da', 'do', 'das', 'dos', 'e', 'di', 'du'];

    public function nomeTitulo(string $nome): string
    {
        $nome = trim(preg_replace('/\s+/u', ' ', $nome));
        if ($nome === '') {
            return '';
        }
        $palavras = explode(' ', mb_strtolower($nome, 'UTF-8'));
        $resultado = [];
        foreach ($palavras as $i => $palavra) {
            if ($palavra === '') {
                continue;
            }
            $resultado[] = ($i > 0 && in_array($palavra, self::PREPOSICOES, true))
                ? $palavra
                : mb_convert_case($palavra, MB_CASE_TITLE, 'UTF-8');
        }

        return implode(' ', $resultado);
    }

    public function flagTresEstados(?string $valor): ?bool
    {
        if ($valor === null) {
            return null;
        }
        $v = mb_strtolower(trim($valor), 'UTF-8');
        if ($v === '') {
            return null;
        }
        if (in_array($v, ['true', 'on', '1', 'sim', 'yes'], true)) {
            return true;
        }
        if (in_array($v, ['false', '0', 'nao', 'não', 'no'], true)) {
            return false;
        }

        return null;
    }

    public function papelDe(array $rolesWp): ?string
    {
        $candidatos = [];
        foreach ($rolesWp as $role) {
            if (isset(GlossarioUsuarios::PAPEIS[$role])) {
                $candidatos[$role] = GlossarioUsuarios::PAPEIS[$role];
            }
        }
        if ($candidatos === []) {
            return null;
        }
        arsort($candidatos);

        return array_key_first($candidatos);
    }

    /** @return array<int, array{slug:string, funcao:string}> */
    public function resolverSetores(array $slugsLegado): array
    {
        $resultado = [];
        foreach ($slugsLegado as $slugLegado) {
            $map = GlossarioUsuarios::SETORES[$slugLegado] ?? null;
            if ($map === null) {
                continue; // não resolvido — o Importador loga
            }
            [$nome, , $funcao] = $map;
            $slug = Str::slug($nome);
            // se o mesmo setor-base já veio, coordenador prevalece sobre membro
            if (isset($resultado[$slug]) && $funcao === 'membro') {
                continue;
            }
            $resultado[$slug] = ['slug' => $slug, 'funcao' => $funcao];
        }

        return array_values($resultado);
    }

    /** @return array<int, string> slugs de cargo resolvidos */
    public function resolverCargos(array $slugsLegado): array
    {
        $resultado = [];
        foreach ($slugsLegado as $slugLegado) {
            $map = GlossarioUsuarios::CARGOS[$slugLegado] ?? null;
            if ($map === null) {
                continue;
            }
            $resultado[] = Str::slug($map[0]);
        }

        return array_values(array_unique($resultado));
    }
}
```

- [ ] **Step 4: Rodar o teste (deve passar)**

Run: `docker exec cema-app php artisan test --filter=TransformadorUsuariosTest`
Expected: PASS (5 testes).

- [ ] **Step 5: Pint + commit**

```bash
docker exec cema-app ./vendor/bin/pint
git add app/Importacao/TransformadorUsuarios.php tests/Unit/TransformadorUsuariosTest.php
git commit -m "feat(usuarios): TransformadorUsuarios (nome, flags, papel, setores/cargos)"
```

---

## Task 8: Leitor do legado (interface + Mysql + Fake)

**Files:**
- Create: `app/Importacao/LeitorUsuarios.php` (interface), `LeitorUsuariosMysql.php`, `LeitorUsuariosFake.php`
- Test: `tests/Unit/LeitorUsuariosFakeTest.php`

**Interfaces:**
- Produces: `LeitorUsuarios::usuarios(): iterable` — cada item é um array normalizado:
  `['origem_id'=>int, 'login'=>string, 'nome'=>string, 'email'=>string, 'senha'=>string, 'registrado'=>?string, 'roles'=>string[], 'setores'=>string[], 'cargos'=>string[], 'socio'=>?string, 'meta'=>array]` onde `meta` traz `whatsapp/nascimento/endereco/whatsapp_publico/cursos`.
- `LeitorUsuariosMysql` lê da conexão `legado` (SELECT); `LeitorUsuariosFake` devolve um array fixo para testes.

- [ ] **Step 1: Definir a interface**

Create `app/Importacao/LeitorUsuarios.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-03

namespace App\Importacao;

interface LeitorUsuarios
{
    /** @return iterable<int, array<string, mixed>> */
    public function usuarios(): iterable;
}
```

- [ ] **Step 2: Implementar o Fake (para testes) e o teste**

Create `app/Importacao/LeitorUsuariosFake.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-03

namespace App\Importacao;

class LeitorUsuariosFake implements LeitorUsuarios
{
    /** @param array<int, array<string, mixed>> $itens */
    public function __construct(private array $itens = []) {}

    public function usuarios(): iterable
    {
        return $this->itens;
    }
}
```

Create `tests/Unit/LeitorUsuariosFakeTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Importacao\LeitorUsuariosFake;
use PHPUnit\Framework\TestCase;

class LeitorUsuariosFakeTest extends TestCase
{
    public function test_fake_devolve_os_itens(): void
    {
        $fake = new LeitorUsuariosFake([['email' => 'a@b.com']]);
        $this->assertSame('a@b.com', iterator_to_array((function () use ($fake) {
            yield from $fake->usuarios();
        })())[0]['email']);
    }
}
```

- [ ] **Step 3: Rodar o teste**

Run: `docker exec cema-app php artisan test --filter=LeitorUsuariosFakeTest`
Expected: PASS.

- [ ] **Step 4: Implementar o LeitorUsuariosMysql**

Create `app/Importacao/LeitorUsuariosMysql.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-03

namespace App\Importacao;

use Illuminate\Support\Facades\DB;

class LeitorUsuariosMysql implements LeitorUsuarios
{
    public function usuarios(): iterable
    {
        $conn = DB::connection('legado');

        $users = $conn->table('users')->select('ID', 'user_login', 'display_name', 'user_email', 'user_pass', 'user_registered')->get();

        foreach ($users as $u) {
            $meta = $conn->table('usermeta')->where('user_id', $u->ID)
                ->pluck('meta_value', 'meta_key');

            yield [
                'origem_id' => (int) $u->ID,
                'login' => $u->user_login,
                'nome' => $u->display_name,
                'email' => $u->user_email,
                'senha' => $u->user_pass,
                'registrado' => $u->user_registered,
                'roles' => $this->tokensRole($meta['wp_capabilities'] ?? ''),
                'setores' => $this->itens($meta['locais_de_trabalho_trabalhador'] ?? ''),
                'cargos' => $this->itens($meta['locais_de_trabalho_diretor'] ?? ''),
                'socio' => $meta['_socio'] ?? null,
                'meta' => [
                    'whatsapp' => $meta['_whatsapp'] ?? null,
                    'whatsapp_publico' => $meta['_liberar_whatsapp_publico'] ?? null,
                    'nascimento' => $meta['data_de_nascimento'] ?? null,
                    'endereco' => $meta['_endereco'] ?? null,
                    'cursos' => $meta['cursos_realizados'] ?? null,
                ],
            ];
        }
    }

    /** Extrai os slugs de role de um wp_capabilities serializado. */
    private function tokensRole(string $serializado): array
    {
        if (preg_match_all('/"([a-z_]+)";b:1/', $serializado, $m)) {
            return $m[1];
        }

        return [];
    }

    /** Desserializa um array PHP de slugs (locais_de_trabalho_*). */
    private function itens(string $serializado): array
    {
        if ($serializado === '') {
            return [];
        }
        $a = @unserialize($serializado);

        return is_array($a) ? array_values(array_filter($a, fn ($x) => is_string($x) && $x !== '')) : [];
    }
}
```

- [ ] **Step 5: Verificar o SQL real contra o legado (guard — só há Fake nos testes)**

Run: `docker exec cema-app php artisan tinker --execute="dump(iterator_to_array((new App\Importacao\LeitorUsuariosMysql)->usuarios())[0]);"`
Expected: um array com `email`, `roles`, `setores`, `senha` começando com `$wp$`/`$P$`. (Túnel SSH ativo.)

- [ ] **Step 6: Pint + commit**

```bash
docker exec cema-app ./vendor/bin/pint
git add app/Importacao/LeitorUsuarios.php app/Importacao/LeitorUsuariosMysql.php app/Importacao/LeitorUsuariosFake.php tests/Unit/LeitorUsuariosFakeTest.php
git commit -m "feat(usuarios): LeitorUsuarios (interface + mysql legado + fake)"
```

---

## Task 9: ImportadorUsuarios + comando

**Files:**
- Create: `app/Importacao/ImportadorUsuarios.php`, `app/Console/Commands/ImportarUsuarios.php`
- Modify: `app/Providers/AppServiceProvider.php` (bind `LeitorUsuarios` → `LeitorUsuariosMysql`)
- Test: `tests/Feature/Usuarios/ImportadorUsuariosTest.php`

**Interfaces:**
- Consumes: `LeitorUsuarios`, `TransformadorUsuarios`, `GlossarioUsuarios`, models, `EstruturaCemaSeeder`.
- Produces: `ImportadorUsuarios::importar(callable $log): array` retorna `['usuarios'=>int, 'ignorados'=>int, 'avisos'=>string[]]`. Comando `cema:importar-usuarios`.

- [ ] **Step 1: Escrever o teste (TDD) — idempotência + classificação + exclusões**

Create `tests/Feature/Usuarios/ImportadorUsuariosTest.php`:

```php
<?php

namespace Tests\Feature\Usuarios;

use App\Importacao\ImportadorUsuarios;
use App\Importacao\LeitorUsuariosFake;
use App\Models\User;
use Database\Seeders\EstruturaCemaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportadorUsuariosTest extends TestCase
{
    use RefreshDatabase;

    private function fake(): LeitorUsuariosFake
    {
        $pre = base64_encode(hash_hmac('sha384', 'segredo123', 'wp-sha384', true));

        return new LeitorUsuariosFake([
            [
                'origem_id' => 26, 'login' => 'ana', 'nome' => 'ANA KARLA DA SILVA',
                'email' => 'ana@exemplo.com', 'senha' => '$wp'.password_hash($pre, PASSWORD_BCRYPT),
                'registrado' => '2024-01-10 12:00:00',
                'roles' => ['trabalhador'], 'setores' => ['medium', 'coordenador_da_campanha_auta_de_souza'],
                'cargos' => [], 'socio' => 'true',
                'meta' => ['whatsapp' => '61999998888', 'whatsapp_publico' => 'on', 'nascimento' => '1980-05-02', 'endereco' => 'Qd 1', 'cursos' => null],
            ],
            [
                'origem_id' => 1, 'login' => 'DECOM1', 'nome' => 'DECOM1', 'email' => 'decom1@cemanet.org.br',
                'senha' => '$P$Bxxxxxxxxxxxxxxxxxxxxx', 'registrado' => null,
                'roles' => ['administrator'], 'setores' => [], 'cargos' => [], 'socio' => null, 'meta' => [],
            ],
        ]);
    }

    public function test_importa_classifica_e_ignora_admin_idempotente(): void
    {
        (new EstruturaCemaSeeder)->run();
        $importador = new ImportadorUsuarios($this->fake(), app(\App\Importacao\TransformadorUsuarios::class));

        $r1 = $importador->importar(fn ($m) => null);
        $r2 = $importador->importar(fn ($m) => null); // 2x → estável

        $this->assertSame(1, $r1['usuarios']);
        $this->assertSame(1, User::count()); // admin não migrou; idempotente
        $this->assertSame(1, $r1['ignorados']); // o admin

        $ana = User::where('email', 'ana@exemplo.com')->first();
        $this->assertSame('Ana Karla da Silva', $ana->name);
        $this->assertTrue($ana->socio);
        $this->assertNotNull($ana->email_verified_at);
        $this->assertTrue($ana->hasRole('trabalhador'));
        $this->assertSame('coordenador', $ana->setores()->where('slug', 'campanha-auta-de-souza')->first()->pivot->funcao);
        $this->assertSame('61999998888', $ana->perfil->whatsapp);
        $this->assertTrue($ana->perfil->whatsapp_publico);
    }

    public function test_senha_legada_valida_no_login(): void
    {
        (new EstruturaCemaSeeder)->run();
        (new ImportadorUsuarios($this->fake(), app(\App\Importacao\TransformadorUsuarios::class)))->importar(fn ($m) => null);

        $this->assertTrue(\Illuminate\Support\Facades\Auth::attempt([
            'email' => 'ana@exemplo.com', 'password' => 'segredo123',
        ]));
    }
}
```

- [ ] **Step 2: Rodar o teste (deve falhar)**

Run: `docker exec cema-app php artisan test --filter=ImportadorUsuariosTest`
Expected: FAIL (classe não existe).

- [ ] **Step 3: Implementar o Importador**

Create `app/Importacao/ImportadorUsuarios.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-03

namespace App\Importacao;

use App\Models\Atributo;
use App\Models\Cargo;
use App\Models\CursoRealizado;
use App\Models\PerfilMembro;
use App\Models\Setor;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ImportadorUsuarios
{
    public function __construct(
        private LeitorUsuarios $leitor,
        private TransformadorUsuarios $transformador,
    ) {}

    public function importar(callable $log): array
    {
        $setoresPorSlug = Setor::pluck('id', 'slug');
        $cargosPorSlug = Cargo::pluck('id', 'slug');
        $socioId = Atributo::where('slug', 'socio')->value('id');

        $usuarios = 0;
        $ignorados = 0;
        $avisos = [];

        foreach ($this->leitor->usuarios() as $bruto) {
            $papel = $this->transformador->papelDe($bruto['roles']);
            if ($papel === null || $papel === 'administrador') {
                $ignorados++;
                $avisos[] = "Ignorado (papel {$this->join($bruto['roles'])}): {$bruto['email']}";

                continue;
            }
            if (! str_starts_with($bruto['senha'], '$wp') && ! str_starts_with($bruto['senha'], '$P$')) {
                $ignorados++;
                $avisos[] = "Ignorado (hash não reconhecido): {$bruto['email']}";

                continue;
            }

            DB::transaction(function () use ($bruto, $papel, $setoresPorSlug, $cargosPorSlug, $socioId, &$avisos) {
                $existe = User::where('origem_legado_id', $bruto['origem_id'])->first();

                $dados = [
                    'name' => $this->transformador->nomeTitulo($bruto['nome']),
                    'email' => mb_strtolower(trim($bruto['email'])),
                    'ativo' => true,
                    'socio' => $this->transformador->flagTresEstados($bruto['socio']) === true,
                    'email_verified_at' => $bruto['registrado'] ?? now(),
                ];

                // senha = hash bruto (o cast 'hashed' foi removido do User): só grava no
                // create ou enquanto o hash ainda for legado; nunca sobrescreve bcrypt modernizado.
                $atual = $existe?->password;
                if (! $atual || str_starts_with($atual, '$wp') || str_starts_with($atual, '$P$')) {
                    $dados['password'] = $bruto['senha'];
                }

                $user = User::updateOrCreate(['origem_legado_id' => $bruto['origem_id']], $dados);

                $user->syncRoles([$papel]);

                $setores = [];
                foreach ($this->transformador->resolverSetores($bruto['setores']) as $s) {
                    if (isset($setoresPorSlug[$s['slug']])) {
                        $setores[$setoresPorSlug[$s['slug']]] = ['funcao' => $s['funcao']];
                    } else {
                        $avisos[] = "Setor não resolvido '{$s['slug']}' para {$bruto['email']}";
                    }
                }
                $user->setores()->sync($setores);

                $cargos = [];
                foreach ($this->transformador->resolverCargos($bruto['cargos']) as $slug) {
                    if (isset($cargosPorSlug[$slug])) {
                        $cargos[] = $cargosPorSlug[$slug];
                    } else {
                        $avisos[] = "Cargo não resolvido '{$slug}' para {$bruto['email']}";
                    }
                }
                $user->cargos()->sync($cargos);

                $user->atributos()->sync(
                    $user->socio && $socioId ? [$socioId] : []
                );

                $this->perfil($user, $bruto['meta']);
            });

            $usuarios++;
            $log("Importado: {$bruto['email']}");
        }

        return ['usuarios' => $usuarios, 'ignorados' => $ignorados, 'avisos' => $avisos];
    }

    private function perfil(User $user, array $meta): void
    {
        PerfilMembro::updateOrCreate(
            ['user_id' => $user->id],
            [
                'whatsapp' => $meta['whatsapp'] ?? null,
                'whatsapp_publico' => $this->transformador->flagTresEstados($meta['whatsapp_publico'] ?? null) === true,
                'data_nascimento' => $this->data($meta['nascimento'] ?? null),
                'endereco' => $meta['endereco'] ?? null,
            ],
        );

        // cursos_realizados (repeater serializado) → delete + recriação ordenada
        $user->cursos()->delete();
        $cursos = @unserialize($meta['cursos'] ?? '');
        if (is_array($cursos)) {
            $ordem = 0;
            foreach ($cursos as $item) {
                if (! is_array($item) || empty($item['nome_do_curso'])) {
                    continue;
                }
                CursoRealizado::create([
                    'user_id' => $user->id,
                    'nome' => $item['nome_do_curso'],
                    'ano' => is_numeric($item['ano_de_conclusao'] ?? null) ? (int) $item['ano_de_conclusao'] : null,
                    'local' => $item['local_de_conclusao'] ?? null,
                    'ordem' => $ordem++,
                ]);
            }
        }
    }

    private function data(?string $valor): ?string
    {
        if (! $valor || ! preg_match('/^\d{4}-\d{2}-\d{2}/', $valor)) {
            return null;
        }

        return substr($valor, 0, 10);
    }

    private function join(array $roles): string
    {
        return implode(',', $roles) ?: '(nenhum)';
    }
}
```

- [ ] **Step 4: Bind do Leitor real + comando**

Modify `app/Providers/AppServiceProvider.php` — no `register()`:

```php
$this->app->bind(\App\Importacao\LeitorUsuarios::class, \App\Importacao\LeitorUsuariosMysql::class);
```

Create `app/Console/Commands/ImportarUsuarios.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-03

namespace App\Console\Commands;

use App\Importacao\ImportadorUsuarios;
use App\Importacao\LeitorUsuarios;
use App\Importacao\LeitorUsuariosMysql;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportarUsuarios extends Command
{
    protected $signature = 'cema:importar-usuarios';

    protected $description = 'Importa os usuários do WordPress legado (somente leitura) para o MySQL local.';

    public function handle(LeitorUsuarios $leitor, ImportadorUsuarios $importador): int
    {
        if ($leitor instanceof LeitorUsuariosMysql) {
            try {
                DB::connection('legado')->getPdo();
            } catch (\Throwable $e) {
                $this->error('Não foi possível conectar ao banco legado. O túnel SSH está ativo?');
                $this->line('Detalhe: '.$e->getMessage());

                return self::FAILURE;
            }
        }

        $resumo = $importador->importar(fn (string $m) => $this->line($m));

        $this->newLine();
        $this->info("Concluído: {$resumo['usuarios']} usuários importados, {$resumo['ignorados']} ignorados.");
        if (! empty($resumo['avisos'])) {
            $this->warn('Avisos ('.count($resumo['avisos']).'):');
            foreach ($resumo['avisos'] as $aviso) {
                $this->line('  - '.$aviso);
            }
        }

        return self::SUCCESS;
    }
}
```

- [ ] **Step 5: Rodar o teste (deve passar)**

Run: `docker exec cema-app php artisan test --filter=ImportadorUsuariosTest`
Expected: PASS (2 testes).

- [ ] **Step 6: Pint + commit**

```bash
docker exec cema-app ./vendor/bin/pint
git add app/Importacao/ImportadorUsuarios.php app/Console/Commands/ImportarUsuarios.php app/Providers/AppServiceProvider.php tests/Feature/Usuarios/ImportadorUsuariosTest.php
git commit -m "feat(usuarios): ImportadorUsuarios idempotente + comando cema:importar-usuarios"
```

- [ ] **Step 7: Rodar a importação real no dev (com túnel SSH ativo)**

Run: `docker exec cema-app php artisan cema:importar-usuarios`
Expected: ~145 importados, ~5 ignorados (4 admin + 1 subscriber); rodar 2× → contagem estável. Conferir avisos.

---

## Task 10: Gate do painel por papel + AdminSeeder

**Files:**
- Modify: `app/Models/User.php` (`canAccessPanel`)
- Create: `database/seeders/AdminSeeder.php`
- Test: `tests/Feature/Usuarios/GatePainelTest.php`

**Interfaces:**
- Consumes: papéis (Task 5), `HasRoles` (Task 1).
- Produces: `canAccessPanel` libera só `administrador`/`diretor`; `AdminSeeder` cria o admin do site novo.

- [ ] **Step 1: Escrever o teste (TDD)**

Create `tests/Feature/Usuarios/GatePainelTest.php`:

```php
<?php

namespace Tests\Feature\Usuarios;

use App\Models\User;
use Database\Seeders\EstruturaCemaSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GatePainelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        (new EstruturaCemaSeeder)->run();
    }

    public function test_diretor_acessa_e_frequentador_nao(): void
    {
        $painel = Filament::getPanel('admin');

        $diretor = User::factory()->create();
        $diretor->assignRole('diretor');
        $freq = User::factory()->create();
        $freq->assignRole('frequentador');

        $this->assertTrue($diretor->canAccessPanel($painel));
        $this->assertFalse($freq->canAccessPanel($painel));
    }
}
```

- [ ] **Step 2: Rodar o teste (deve falhar — hoje libera todos em testing)**

Run: `docker exec cema-app php artisan test --filter=GatePainelTest`
Expected: FAIL (frequentador ainda acessa).

- [ ] **Step 3: Ajustar `canAccessPanel`**

Modify `app/Models/User.php`:

```php
public function canAccessPanel(Panel $panel): bool
{
    return $this->hasAnyRole(['administrador', 'diretor']);
}
```

- [ ] **Step 4: Criar o AdminSeeder**

Create `database/seeders/AdminSeeder.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-03

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::updateOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@cemanet.org.br')],
            [
                'name' => 'Administrador CEMA',
                'password' => Hash::make(env('ADMIN_PASSWORD', 'trocar-esta-senha')),
                'ativo' => true,
                'email_verified_at' => now(),
            ],
        );
        $admin->syncRoles(['administrador']);
    }
}
```

- [ ] **Step 5: Rodar o teste (deve passar)**

Run: `docker exec cema-app php artisan test --filter=GatePainelTest`
Expected: PASS.

- [ ] **Step 6: Criar o admin no dev + Pint + commit**

Run: `docker exec cema-app php artisan db:seed --class=AdminSeeder`
Expected: admin criado (idempotente).

```bash
docker exec cema-app ./vendor/bin/pint
git add app/Models/User.php database/seeders/AdminSeeder.php tests/Feature/Usuarios/GatePainelTest.php
git commit -m "feat(usuarios): gate do painel por papel (diretor/admin) + AdminSeeder"
```

---

## Task 11: Filament Resources (Departamento, Setor, Cargo, Usuário)

**Files:**
- Create: `app/Filament/Resources/DepartamentoResource.php` (+ Pages), `SetorResource.php`, `CargoResource.php`, `UsuarioResource.php`
- Test: `tests/Feature/Usuarios/UsuarioResourceTest.php`

**Interfaces:**
- Consumes: models + papéis. Produces: CRUD no `/admin` para os catálogos e usuários; atribuição de papel via `Select`.

> Gerar o scaffold com o comando do Filament e depois ajustar o schema. Cada Resource é um deliverable; commitar juntos por serem a mesma responsabilidade (administração).

- [ ] **Step 1: Gerar os Resources (scaffold)**

Run:
```bash
docker exec cema-app php artisan make:filament-resource Departamento --generate
docker exec cema-app php artisan make:filament-resource Setor --generate
docker exec cema-app php artisan make:filament-resource Cargo --generate
docker exec cema-app php artisan make:filament-resource User --generate
```
Expected: Resources criados em `app/Filament/Resources/`.

- [ ] **Step 2: Ajustar o UserResource (form) — papel, setores, cargos, sócio, perfil**

Modify o `form()`/`schema()` do `UsuarioResource` (nome do arquivo gerado: `UserResource`) para conter:

```php
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

// dentro do schema do form:
TextInput::make('name')->label('Nome')->required(),
TextInput::make('email')->label('E-mail')->email()->required()->unique(ignoreRecord: true),
Toggle::make('socio')->label('Sócio'),
Toggle::make('ativo')->label('Ativo')->default(true),
Select::make('roles')
    ->label('Papel')
    ->relationship('roles', 'name')
    ->options(\Spatie\Permission\Models\Role::pluck('name', 'name'))
    ->required(),
Select::make('setores')
    ->label('Setores')
    ->relationship('setores', 'nome')
    ->multiple()->preload(),
Select::make('cargos')
    ->label('Cargos')
    ->relationship('cargos', 'nome')
    ->multiple()->preload(),
```

> Nota: senha **não** é campo obrigatório aqui (usuários migrados já têm hash). Para novos usuários criados no admin, adicionar um `TextInput::make('password')->password()->dehydrated(fn ($state) => filled($state))->dehydrateStateUsing(fn ($state) => \Illuminate\Support\Facades\Hash::make($state))->required(fn (string $context) => $context === 'create')`.

- [ ] **Step 3: Ajustar a tabela do UserResource (colunas úteis)**

No `table()` do `UserResource`, colunas:

```php
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;

TextColumn::make('name')->label('Nome')->searchable()->sortable(),
TextColumn::make('email')->label('E-mail')->searchable(),
TextColumn::make('roles.name')->label('Papel')->badge(),
IconColumn::make('socio')->label('Sócio')->boolean(),
TextColumn::make('setores.nome')->label('Setores')->badge()->limitList(3),
```

- [ ] **Step 4: Ajustar SetorResource (departamento opcional) e CargoResource (institucional)**

No `form()` do `SetorResource`:

```php
Select::make('departamento_id')->label('Departamento')
    ->relationship('departamento', 'nome')->nullable()
    ->helperText('Deixe vazio para setor sem departamento (ex.: PAMANA).'),
```

No `form()` do `CargoResource`, o campo de departamento fica oculto quando institucional:

```php
Toggle::make('institucional')->label('Institucional (sem departamento)')->live(),
Select::make('departamento_id')->label('Departamento')
    ->relationship('departamento', 'nome')
    ->hidden(fn (\Filament\Forms\Get $get) => $get('institucional')),
```

- [ ] **Step 5: Teste de fumaça do UserResource (renderiza + lista)**

Create `tests/Feature/Usuarios/UsuarioResourceTest.php`:

```php
<?php

namespace Tests\Feature\Usuarios;

use App\Models\User;
use Database\Seeders\EstruturaCemaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UsuarioResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_acessa_listagem_de_usuarios(): void
    {
        (new EstruturaCemaSeeder)->run();
        $admin = User::factory()->create();
        $admin->assignRole('administrador');

        $this->actingAs($admin)
            ->get('/admin/users')
            ->assertSuccessful();
    }
}
```

- [ ] **Step 6: Rodar o teste**

Run: `docker exec cema-app php artisan test --filter=UsuarioResourceTest`
Expected: PASS. (Se a rota for `/admin/usuarios`, ajustar conforme o slug gerado.)

- [ ] **Step 7: Verificação manual + Pint + commit**

Verificação: abrir `http://localhost:8000/admin`, logar com o admin (AdminSeeder), conferir que Usuários/Departamentos/Setores/Cargos aparecem, que um usuário migrado mostra papel/setores/sócio, e que dá para trocar o papel via Select.

```bash
docker exec cema-app ./vendor/bin/pint
git add app/Filament tests/Feature/Usuarios/UsuarioResourceTest.php
git commit -m "feat(usuarios): Filament Resources (usuario, departamento, setor, cargo)"
```

- [ ] **Step 8: Suíte completa + verificação final**

Run: `docker exec cema-app php artisan test`
Expected: toda a suíte verde.

Run: `docker exec cema-app ./vendor/bin/pint --test`
Expected: sem drift de estilo.

---

## Critério de pronto (checklist final)

- [ ] `cema:importar-usuarios` traz ~145 usuários idempotente (2× → estável).
- [ ] Um usuário do legado loga com a senha antiga; o hash vira bcrypt nativo após o login.
- [ ] Papel/setores(com função)/cargos/sócio/perfil corretos num usuário de amostra (ex.: quem tinha `coordenador_da_campanha_auta_de_souza`).
- [ ] Admins (4) e subscriber (1) não migrados (aparecem nos avisos).
- [ ] `/admin` acessível a diretor/admin; bloqueado a frequentador/trabalhador.
- [ ] Suíte verde + Pint limpo.
