# Fotos de perfil de usuário — Plano de Implementação

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Migrar as fotos de perfil do legado, capturar o avatar do Google no login e dar ao membro o controle da própria foto (remover + precedência), sempre caindo nas iniciais quando não houver foto.

**Architecture:** Reusa `BaixadorImagem::baixarCapado()` (bytes em memória, esquema http(s) validado) + `addMediaFromString()` na coleção `foto` do `PerfilMembro`. Três frentes: (A) comando `cema:importar-fotos-usuarios`; (B) `CapturarAvatarGoogleJob` em fila disparado pelo `GoogleController`; (C) botão "Remover foto" no `EditarPerfil` + coluna `foto_definida_pelo_membro`. Auto-população nunca vence o membro.

**Tech Stack:** Laravel 13 · Spatie Media Library · Livewire · fila `database` · Docker (`cema-app`).

## Global Constraints

- **Guard da auto-população (Partes A e B):** agir **só se** `PerfilMembro::podeAutoPopularFoto()` for true, i.e. `foto_definida_pelo_membro === false` **E** a coleção `foto` estiver vazia.
- **Downloads:** `BaixadorImagem::baixarCapado($url, 2000)` → `addMediaFromString($bytes)->usingFileName(<nome>)->toMediaCollection(PerfilMembro::COLECAO_FOTO)`. Nunca `baixarPara`/staging em disco.
- **`foto_definida_pelo_membro`** vira `true` quando o membro **sobe** OU **remove** a foto no `EditarPerfil`. Setada por **código controlado** — **não** é propriedade bindável do Livewire (blindagem) e **fica fora do `$fillable`**. Consequência para **testes e código**: **nunca** setar via mass-assignment (`create([... 'foto_definida_pelo_membro' => true])`) — o app não usa strict mode e o valor seria descartado em silêncio, deixando o flag `false`. Setar sempre pela via controlada: `$p = PerfilMembro::create(['user_id' => …]); $p->foto_definida_pelo_membro = true; $p->save();`.
- **Coluna nova** via migration **incremental**. 🚫 NUNCA `migrate:fresh`/`refresh`/`wipe`/`reset` nem seed/factory destrutivo.
- **Comando** `cema:importar-fotos-usuarios` replica o guard de conexão `legado` (`instanceof LeitorUsuariosMysql` + `DB::connection('legado')->getPdo()`).
- **Testes de foto usam bytes de imagem REAIS** (`UploadedFile::fake()->image(...)->get()`) — as conversões `web`/`thumb` do Spatie rodam **síncronas** e o GD precisa abrir os bytes. Mock de `BaixadorImagem::baixarCapado` devolvendo esses bytes.
- **`mockGoogle()`** de `GoogleLoginTest.php` passa a estubar `getAvatar` (senão `BadMethodCallException` derruba 3 testes).
- **`@unserialize(..., ['allowed_classes' => false])`** defensivo; parse inválido = candidata ausente, sem interromper.
- **Retorno de `ImportadorFotosUsuarios::importar()`:** `array{anexadas:int, puladas:int, sem_candidata:int, falhas:int, avisos:string[]}`.
- **npm no HOST**; artisan/testes/pint no container `cema-app` (`docker compose exec -T app ...`).
- **GATE DE MERGE (bloqueante):** conferência manual do SQL novo do `LeitorUsuariosMysql` contra o **legado vivo** (túnel SSH) — Task 6.
- pt-BR; cabeçalho de autoria `Thiago Mourão — https://github.com/MouraoBSB — 2026-07-04` nos arquivos novos.

---

## Estrutura de arquivos

| Arquivo | Responsabilidade | Ação |
|---|---|---|
| `database/migrations/2026_07_04_100001_add_foto_definida_pelo_membro_to_perfis_membro_table.php` | Coluna `foto_definida_pelo_membro` | Criar |
| `app/Models/PerfilMembro.php` | Cast bool + `podeAutoPopularFoto()` | Modificar |
| `app/Importacao/LeitorUsuariosMysql.php` | `usuarios()` expõe `fotos_urls` | Modificar |
| `app/Importacao/ImportadorFotosUsuarios.php` | Baixa+anexa fotos (best-effort) | Criar |
| `app/Console/Commands/ImportarFotosUsuarios.php` | Comando `cema:importar-fotos-usuarios` | Criar |
| `app/Jobs/CapturarAvatarGoogleJob.php` | Baixa avatar do Google (fila) | Criar |
| `app/Http/Controllers/Auth/GoogleController.php` | Despacha o job no login | Modificar |
| `app/Livewire/Conta/EditarPerfil.php` + `resources/views/livewire/conta/editar-perfil.blade.php` | Botão "Remover foto" + flag | Modificar |
| `tests/Feature/Auth/GoogleLoginTest.php` | `mockGoogle` estuba `getAvatar` + assert dispatch | Modificar |
| `tests/Feature/Importacao/ImportadorFotosUsuariosTest.php` | Testes da migração de fotos | Criar |
| `tests/Feature/Jobs/CapturarAvatarGoogleJobTest.php` | Testes do job | Criar |
| `tests/Feature/Conta/EditarPerfilTest.php` | Casos de remover foto + flag | Modificar |

---

## Task 1: Coluna `foto_definida_pelo_membro` + guard no PerfilMembro

**Files:**
- Create: `database/migrations/2026_07_04_100001_add_foto_definida_pelo_membro_to_perfis_membro_table.php`
- Modify: `app/Models/PerfilMembro.php`
- Test: `tests/Feature/Conta/PerfilMembroFotoGuardTest.php` (novo)

**Interfaces:**
- Produces: `PerfilMembro::podeAutoPopularFoto(): bool` (consumido pelas Tasks 3 e 4); coluna `foto_definida_pelo_membro` (bool, default false).

- [ ] **Step 1: Escrever o teste (falhando)**

Criar `tests/Feature/Conta/PerfilMembroFotoGuardTest.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-04

namespace Tests\Feature\Conta;

use App\Models\PerfilMembro;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class PerfilMembroFotoGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_pode_auto_popular_quando_sem_foto_e_flag_false(): void
    {
        $perfil = PerfilMembro::create(['user_id' => User::factory()->create()->id]);

        $this->assertFalse($perfil->foto_definida_pelo_membro);
        $this->assertTrue($perfil->podeAutoPopularFoto());
    }

    public function test_nao_auto_popula_quando_flag_true(): void
    {
        // O flag NÃO está no $fillable (blindagem) → mass-assignment o descartaria em
        // silêncio (app sem strict mode). Setar sempre pela via controlada.
        $perfil = PerfilMembro::create(['user_id' => User::factory()->create()->id]);
        $perfil->foto_definida_pelo_membro = true;
        $perfil->save();

        $this->assertTrue($perfil->fresh()->foto_definida_pelo_membro);
        $this->assertFalse($perfil->podeAutoPopularFoto());
    }

    public function test_nao_auto_popula_quando_ja_ha_foto(): void
    {
        $perfil = PerfilMembro::create(['user_id' => User::factory()->create()->id]);
        $perfil->addMedia(UploadedFile::fake()->image('f.jpg')->getRealPath())
            ->usingFileName('f.jpg')->toMediaCollection(PerfilMembro::COLECAO_FOTO);

        $this->assertTrue($perfil->fresh()->foto_definida_pelo_membro === false);
        $this->assertFalse($perfil->fresh()->podeAutoPopularFoto());
    }
}
```

- [ ] **Step 2: Rodar e ver falhar**

`docker compose exec -T app php artisan test --filter=PerfilMembroFotoGuardTest`
Esperado: FALHA (coluna/método não existem).

- [ ] **Step 3: Criar a migration**

`database/migrations/2026_07_04_100001_add_foto_definida_pelo_membro_to_perfis_membro_table.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-04

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('perfis_membro', function (Blueprint $table) {
            $table->boolean('foto_definida_pelo_membro')->default(false)->after('endereco');
        });
    }

    public function down(): void
    {
        Schema::table('perfis_membro', function (Blueprint $table) {
            $table->dropColumn('foto_definida_pelo_membro');
        });
    }
};
```

> Conferir com `Schema::hasColumn('perfis_membro', 'endereco')` mentalmente: a coluna `endereco` existe (DATA-MODEL). Se o `->after('endereco')` falhar por ordem, remover o `->after(...)` (posição não importa).

- [ ] **Step 4: Ajustar o PerfilMembro**

Em `app/Models/PerfilMembro.php`: adicionar o cast e o método guard. **NÃO** adicionar `foto_definida_pelo_membro` ao `$fillable` (setada por código controlado). Adicionar ao `casts()` (ou `$casts`) `'foto_definida_pelo_membro' => 'boolean'` e o método:

```php
/** Auto-população (migração/Google) só age se o membro não definiu a foto e não há foto ainda. */
public function podeAutoPopularFoto(): bool
{
    return ! $this->foto_definida_pelo_membro && ! $this->hasMedia(self::COLECAO_FOTO);
}
```

- [ ] **Step 5: Migrar (incremental) e rodar o teste**

```bash
docker compose exec -T app php artisan migrate
docker compose exec -T app php artisan test --filter=PerfilMembroFotoGuardTest
```
Esperado: migra sem tocar dados existentes; teste PASSA.

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_07_04_100001_add_foto_definida_pelo_membro_to_perfis_membro_table.php app/Models/PerfilMembro.php tests/Feature/Conta/PerfilMembroFotoGuardTest.php
git commit -m "feat(conta/foto): coluna foto_definida_pelo_membro + guard podeAutoPopularFoto"
```

---

## Task 2: Leitor expõe `fotos_urls` (candidatas em ordem)

**Files:**
- Modify: `app/Importacao/LeitorUsuariosMysql.php`
- Test: (cobertura automatizada é via Fake na Task 3 + **GATE manual** na Task 6 — o `LeitorUsuariosMysql` não tem teste unitário hoje, só Fake; consistente com o projeto)

**Interfaces:**
- Produces: cada item de `usuarios()` ganha `'fotos_urls' => array<string>` (candidatas em ordem, deduplicadas, sem vazias). Consumido pela Task 3.

- [ ] **Step 1: Estender `usuarios()`**

Em `app/Importacao/LeitorUsuariosMysql.php`, dentro do `yield` (após `'meta' => [...]`), adicionar:

```php
'fotos_urls' => $this->candidatasFoto($meta, $conn),
```

E adicionar o método privado (resolução defensiva; ordem `_foto_de_perfil`.url → `_foto_de_perfil`.id→guid → `wp_user_avatar`.id→guid):

```php
/**
 * URLs candidatas de foto do usuário, em ordem de prioridade (deduplicadas, sem vazias).
 * Parse defensivo: dado corrompido ou id sem guid → candidata ausente, sem interromper.
 */
private function candidatasFoto(\Illuminate\Support\Collection $meta, $conn): array
{
    $urls = [];

    // 1) _foto_de_perfil: serializado {id, url}
    $fp = @unserialize((string) ($meta['_foto_de_perfil'] ?? ''), ['allowed_classes' => false]);
    if (is_array($fp)) {
        if (! empty($fp['url']) && is_string($fp['url'])) {
            $urls[] = $fp['url'];
        }
        if (! empty($fp['id'])) {
            $urls[] = $this->guidDoAttachment($conn, (int) $fp['id']);
        }
    }

    // 2) wp_user_avatar: attachment id
    if (! empty($meta['wp_user_avatar'])) {
        $urls[] = $this->guidDoAttachment($conn, (int) $meta['wp_user_avatar']);
    }

    // remove vazias/nulas e deduplica preservando a ordem
    return array_values(array_unique(array_filter($urls, fn ($u) => is_string($u) && $u !== '')));
}

private function guidDoAttachment($conn, int $id): ?string
{
    if ($id <= 0) {
        return null;
    }
    $row = $conn->table('posts')->where('ID', $id)->where('post_type', 'attachment')->first();

    return $row->guid ?? null;
}
```

> Espelha `LeitorLegadoMysql::urlDaImagem` (guid do attachment). O `$conn` já é `DB::connection('legado')` no início de `usuarios()`.

- [ ] **Step 2: Confirmar que os testes existentes seguem verdes**

`docker compose exec -T app php artisan test --filter='LeitorUsuariosFakeTest|ImportadorUsuariosTest'`
Esperado: PASSAM (a chave nova é ignorada pelo import de dados; o Fake não valida shape).

- [ ] **Step 3: Commit**

```bash
git add app/Importacao/LeitorUsuariosMysql.php
git commit -m "feat(import/usuarios): leitor expoe fotos_urls (candidatas em ordem)"
```

> **Cobertura:** a resolução real (SQL em `usermeta` + guid) **não** tem teste automatizado (padrão do projeto: leitores `*Mysql` só têm Fake). É coberta pelo **GATE manual da Task 6** contra o legado vivo.

---

## Task 3: `ImportadorFotosUsuarios` + comando `cema:importar-fotos-usuarios`

**Files:**
- Create: `app/Importacao/ImportadorFotosUsuarios.php`
- Create: `app/Console/Commands/ImportarFotosUsuarios.php`
- Test: `tests/Feature/Importacao/ImportadorFotosUsuariosTest.php`

**Interfaces:**
- Consumes: `LeitorUsuarios::usuarios()` com `fotos_urls` (Task 2); `PerfilMembro::podeAutoPopularFoto()` (Task 1); `BaixadorImagem::baixarCapado()`.
- Produces: `ImportadorFotosUsuarios::importar(callable $log): array{anexadas, puladas, sem_candidata, falhas, avisos}`.

- [ ] **Step 1: Escrever os testes (falhando)**

Criar `tests/Feature/Importacao/ImportadorFotosUsuariosTest.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-04

namespace Tests\Feature\Importacao;

use App\Importacao\BaixadorImagem;
use App\Importacao\ImportadorFotosUsuarios;
use App\Importacao\LeitorUsuariosFake;
use App\Models\PerfilMembro;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Mockery;
use Tests\TestCase;

class ImportadorFotosUsuariosTest extends TestCase
{
    use RefreshDatabase;

    private function bytesImagem(): string
    {
        return UploadedFile::fake()->image('a.jpg', 800, 800)->get();
    }

    /** @param array<int, array<string,mixed>> $itens */
    private function importador(array $itens, ?BaixadorImagem $baixador = null): ImportadorFotosUsuarios
    {
        return new ImportadorFotosUsuarios(new LeitorUsuariosFake($itens), $baixador ?? app(BaixadorImagem::class));
    }

    private function baixadorQueRetorna(?string $bytes): BaixadorImagem
    {
        $m = Mockery::mock(BaixadorImagem::class);
        $m->shouldReceive('baixarCapado')->andReturn($bytes);

        return $m;
    }

    public function test_anexa_foto_quando_perfil_sem_foto(): void
    {
        $user = User::factory()->create(['origem_legado_id' => 77]);
        PerfilMembro::create(['user_id' => $user->id]);

        $resumo = $this->importador(
            [['origem_id' => 77, 'fotos_urls' => ['https://x/a.jpg']]],
            $this->baixadorQueRetorna($this->bytesImagem()),
        )->importar(fn ($m) => null);

        $this->assertSame(1, $resumo['anexadas']);
        $this->assertTrue($user->perfil->fresh()->hasMedia(PerfilMembro::COLECAO_FOTO));
    }

    public function test_idempotente_nao_reanexa(): void
    {
        $user = User::factory()->create(['origem_legado_id' => 77]);
        $perfil = PerfilMembro::create(['user_id' => $user->id]);
        $perfil->addMedia(UploadedFile::fake()->image('já.jpg')->getRealPath())
            ->usingFileName('ja.jpg')->toMediaCollection(PerfilMembro::COLECAO_FOTO);

        $resumo = $this->importador(
            [['origem_id' => 77, 'fotos_urls' => ['https://x/a.jpg']]],
            $this->baixadorQueRetorna($this->bytesImagem()),
        )->importar(fn ($m) => null);

        $this->assertSame(1, $resumo['puladas']);
        $this->assertSame(1, $perfil->fresh()->getMedia(PerfilMembro::COLECAO_FOTO)->count());
    }

    public function test_respeita_flag_do_membro(): void
    {
        $user = User::factory()->create(['origem_legado_id' => 77]);
        // flag fora do $fillable → setar pela via controlada (mass-assignment o descartaria)
        $perfil = PerfilMembro::create(['user_id' => $user->id]);
        $perfil->foto_definida_pelo_membro = true;
        $perfil->save();

        $resumo = $this->importador(
            [['origem_id' => 77, 'fotos_urls' => ['https://x/a.jpg']]],
            $this->baixadorQueRetorna($this->bytesImagem()),
        )->importar(fn ($m) => null);

        $this->assertSame(1, $resumo['puladas']);
        $this->assertFalse($user->perfil->fresh()->hasMedia(PerfilMembro::COLECAO_FOTO));
    }

    public function test_tenta_candidatas_em_ordem_ate_uma_funcionar(): void
    {
        $user = User::factory()->create(['origem_legado_id' => 77]);
        PerfilMembro::create(['user_id' => $user->id]);

        $bytes = $this->bytesImagem();
        $m = Mockery::mock(BaixadorImagem::class);
        $m->shouldReceive('baixarCapado')->once()->with('https://x/quebrada.jpg', Mockery::any())->andReturn(null);
        $m->shouldReceive('baixarCapado')->once()->with('https://x/ok.jpg', Mockery::any())->andReturn($bytes);

        $resumo = $this->importador(
            [['origem_id' => 77, 'fotos_urls' => ['https://x/quebrada.jpg', 'https://x/ok.jpg']]],
            $m,
        )->importar(fn ($m) => null);

        $this->assertSame(1, $resumo['anexadas']);
        $this->assertTrue($user->perfil->fresh()->hasMedia(PerfilMembro::COLECAO_FOTO));
    }

    public function test_sem_candidata_ou_sem_user_local_nao_faz_nada(): void
    {
        // usuário sem User local (origem 999 não existe) + usuário sem fotos_urls
        User::factory()->create(['origem_legado_id' => 77]);
        PerfilMembro::create(['user_id' => User::where('origem_legado_id', 77)->value('id')]);

        $resumo = $this->importador([
            ['origem_id' => 999, 'fotos_urls' => ['https://x/a.jpg']],
            ['origem_id' => 77, 'fotos_urls' => []],
        ], $this->baixadorQueRetorna($this->bytesImagem()))->importar(fn ($m) => null);

        $this->assertSame(0, $resumo['anexadas']);
        $this->assertSame(1, $resumo['sem_candidata']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
```

- [ ] **Step 2: Rodar e ver falhar**

`docker compose exec -T app php artisan test --filter=ImportadorFotosUsuariosTest`
Esperado: FALHA (classe não existe).

- [ ] **Step 3: Implementar o importador**

`app/Importacao/ImportadorFotosUsuarios.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-04

namespace App\Importacao;

use App\Models\PerfilMembro;
use App\Models\User;

class ImportadorFotosUsuarios
{
    public function __construct(
        private LeitorUsuarios $leitor,
        private BaixadorImagem $baixador,
    ) {}

    /** @return array{anexadas:int, puladas:int, sem_candidata:int, falhas:int, avisos:string[]} */
    public function importar(callable $log): array
    {
        $anexadas = $puladas = $semCandidata = $falhas = 0;
        $avisos = [];

        foreach ($this->leitor->usuarios() as $bruto) {
            $candidatas = $bruto['fotos_urls'] ?? [];
            if (empty($candidatas)) {
                $semCandidata++;

                continue;
            }

            $user = User::where('origem_legado_id', $bruto['origem_id'])->first();
            if (! $user) {
                continue; // não importado (admin/hash ignorado)
            }

            $perfil = PerfilMembro::firstOrCreate(['user_id' => $user->id]);
            if (! $perfil->podeAutoPopularFoto()) {
                $puladas++;

                continue;
            }

            $anexou = false;
            foreach ($candidatas as $url) {
                $bytes = $this->baixador->baixarCapado($url, 2000);
                if ($bytes === null) {
                    continue;
                }
                $perfil->addMediaFromString($bytes)
                    ->usingFileName(basename(parse_url($url, PHP_URL_PATH) ?? 'foto.jpg') ?: 'foto.jpg')
                    ->toMediaCollection(PerfilMembro::COLECAO_FOTO);
                $anexou = true;
                $anexadas++;
                $log("Foto anexada: {$user->email}");
                break;
            }

            if (! $anexou) {
                $falhas++;
                $avisos[] = "Nenhuma URL baixou para {$user->email}";
            }
        }

        return [
            'anexadas' => $anexadas,
            'puladas' => $puladas,
            'sem_candidata' => $semCandidata,
            'falhas' => $falhas,
            'avisos' => $avisos,
        ];
    }
}
```

- [ ] **Step 4: Implementar o comando**

`app/Console/Commands/ImportarFotosUsuarios.php` (replica o guard de conexão do `ImportarUsuarios`):

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-04

namespace App\Console\Commands;

use App\Importacao\ImportadorFotosUsuarios;
use App\Importacao\LeitorUsuarios;
use App\Importacao\LeitorUsuariosMysql;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportarFotosUsuarios extends Command
{
    protected $signature = 'cema:importar-fotos-usuarios';

    protected $description = 'Migra as fotos de perfil dos usuários do legado (somente leitura) para a Media Library.';

    public function handle(LeitorUsuarios $leitor, ImportadorFotosUsuarios $importador): int
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

        $r = $importador->importar(fn (string $m) => $this->line($m));

        $this->newLine();
        $this->info("Concluído: {$r['anexadas']} anexadas, {$r['puladas']} puladas, {$r['sem_candidata']} sem candidata, {$r['falhas']} falhas.");
        foreach ($r['avisos'] as $aviso) {
            $this->warn('  - '.$aviso);
        }

        return self::SUCCESS;
    }
}
```

- [ ] **Step 5: Rodar os testes**

`docker compose exec -T app php artisan test --filter=ImportadorFotosUsuariosTest`
Esperado: PASSAM (todos os casos).

- [ ] **Step 6: Commit**

```bash
git add app/Importacao/ImportadorFotosUsuarios.php app/Console/Commands/ImportarFotosUsuarios.php tests/Feature/Importacao/ImportadorFotosUsuariosTest.php
git commit -m "feat(import/fotos): comando cema:importar-fotos-usuarios (best-effort, idempotente)"
```

---

## Task 4: `CapturarAvatarGoogleJob` + despacho no `GoogleController`

**Files:**
- Create: `app/Jobs/CapturarAvatarGoogleJob.php`
- Modify: `app/Http/Controllers/Auth/GoogleController.php`
- Modify: `tests/Feature/Auth/GoogleLoginTest.php` (mockGoogle + assert dispatch)
- Test: `tests/Feature/Jobs/CapturarAvatarGoogleJobTest.php`

**Interfaces:**
- Consumes: `PerfilMembro::podeAutoPopularFoto()`; `BaixadorImagem::baixarCapado()`.
- Produces: `CapturarAvatarGoogleJob::dispatch(int $userId, string $avatarUrl)`.

- [ ] **Step 1: Atualizar o `mockGoogle` e escrever os testes de despacho (falhando)**

Em `tests/Feature/Auth/GoogleLoginTest.php`, mudar a assinatura do helper para estubar `getAvatar` (default null → não despacha):

```php
private function mockGoogle(string $id, ?string $email, string $nome = 'Membro Google', ?string $avatar = null): void
{
    $abstract = Mockery::mock(SocialiteUser::class);
    $abstract->shouldReceive('getId')->andReturn($id);
    $abstract->shouldReceive('getEmail')->andReturn($email);
    $abstract->shouldReceive('getName')->andReturn($nome);
    $abstract->shouldReceive('getNickname')->andReturn(null);
    $abstract->shouldReceive('getAvatar')->andReturn($avatar);
    // ... resto igual
}
```

Adicionar dois testes (usando `Bus::fake()`):

```php
public function test_login_google_com_avatar_enfileira_job_quando_sem_foto(): void
{
    Bus::fake();
    $this->mockGoogle('g-777', 'foto@gmail.com', avatar: 'https://lh3.google/a.jpg');

    $this->get('/auth/google/callback')->assertRedirect('/minha-conta');

    Bus::assertDispatched(\App\Jobs\CapturarAvatarGoogleJob::class);
}

public function test_login_google_sem_avatar_nao_enfileira(): void
{
    Bus::fake();
    $this->mockGoogle('g-778', 'semfoto@gmail.com'); // avatar null

    $this->get('/auth/google/callback')->assertRedirect('/minha-conta');

    Bus::assertNotDispatched(\App\Jobs\CapturarAvatarGoogleJob::class);
}
```

E criar `tests/Feature/Jobs/CapturarAvatarGoogleJobTest.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-04

namespace Tests\Feature\Jobs;

use App\Importacao\BaixadorImagem;
use App\Jobs\CapturarAvatarGoogleJob;
use App\Models\PerfilMembro;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Mockery;
use Tests\TestCase;

class CapturarAvatarGoogleJobTest extends TestCase
{
    use RefreshDatabase;

    private function comBaixador(?string $bytes): void
    {
        $m = Mockery::mock(BaixadorImagem::class);
        $m->shouldReceive('baixarCapado')->andReturn($bytes);
        $this->app->instance(BaixadorImagem::class, $m);
    }

    public function test_anexa_avatar_quando_perfil_sem_foto(): void
    {
        $this->comBaixador(UploadedFile::fake()->image('g.jpg', 800, 800)->get());
        $user = User::factory()->create();
        PerfilMembro::create(['user_id' => $user->id]);

        (new CapturarAvatarGoogleJob($user->id, 'https://lh3/g.jpg'))->handle(app(BaixadorImagem::class));

        $this->assertTrue($user->perfil->fresh()->hasMedia(PerfilMembro::COLECAO_FOTO));
    }

    public function test_respeita_flag_do_membro(): void
    {
        $this->comBaixador(UploadedFile::fake()->image('g.jpg')->get());
        $user = User::factory()->create();
        // flag fora do $fillable → setar pela via controlada (mass-assignment o descartaria)
        $perfil = PerfilMembro::create(['user_id' => $user->id]);
        $perfil->foto_definida_pelo_membro = true;
        $perfil->save();

        (new CapturarAvatarGoogleJob($user->id, 'https://lh3/g.jpg'))->handle(app(BaixadorImagem::class));

        $this->assertFalse($user->perfil->fresh()->hasMedia(PerfilMembro::COLECAO_FOTO));
    }

    public function test_cria_perfil_se_ausente(): void
    {
        $this->comBaixador(UploadedFile::fake()->image('g.jpg', 800, 800)->get());
        $user = User::factory()->create(); // sem perfil

        (new CapturarAvatarGoogleJob($user->id, 'https://lh3/g.jpg'))->handle(app(BaixadorImagem::class));

        $this->assertNotNull($user->perfil()->first());
        $this->assertTrue($user->perfil->fresh()->hasMedia(PerfilMembro::COLECAO_FOTO));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
```

- [ ] **Step 2: Rodar e ver falhar**

`docker compose exec -T app php artisan test --filter='CapturarAvatarGoogleJobTest|GoogleLoginTest'`
Esperado: FALHA (job não existe; dispatch não implementado).

- [ ] **Step 3: Implementar o job**

`app/Jobs/CapturarAvatarGoogleJob.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-04

namespace App\Jobs;

use App\Importacao\BaixadorImagem;
use App\Models\PerfilMembro;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class CapturarAvatarGoogleJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $userId, public string $avatarUrl) {}

    public function handle(BaixadorImagem $baixador): void
    {
        $user = User::find($this->userId);
        if (! $user) {
            return;
        }

        $perfil = PerfilMembro::firstOrCreate(['user_id' => $user->id]);
        if (! $perfil->podeAutoPopularFoto()) {
            return;
        }

        $bytes = $baixador->baixarCapado($this->avatarUrl, 2000);
        if ($bytes === null) {
            Log::info('Avatar do Google não baixado', ['user' => $user->id]);

            return;
        }

        $perfil->addMediaFromString($bytes)
            ->usingFileName('google-avatar.jpg')
            ->toMediaCollection(PerfilMembro::COLECAO_FOTO);
    }
}
```

- [ ] **Step 4: Despachar no `GoogleController`**

Em `app/Http/Controllers/Auth/GoogleController.php`, no `callback()`, **depois** de `Auth::login($user, ...)` e **antes** do `return redirect()->intended(...)`, inserir:

```php
$avatar = $g->getAvatar();
if ($avatar) {
    $perfil = $user->perfil()->firstOrCreate([]);
    if ($perfil->podeAutoPopularFoto()) {
        \App\Jobs\CapturarAvatarGoogleJob::dispatch($user->id, $avatar);
    }
}
```

(Importar a classe no topo se preferir, em vez do FQCN.)

- [ ] **Step 5: Rodar os testes (build da fila em `sync` no phpunit)**

`docker compose exec -T app php artisan test --filter='CapturarAvatarGoogleJobTest|GoogleLoginTest'`
Esperado: PASSAM (os 5 testes antigos do Google + os 2 de dispatch + os 3 do job).

- [ ] **Step 6: Commit**

```bash
git add app/Jobs/CapturarAvatarGoogleJob.php app/Http/Controllers/Auth/GoogleController.php tests/Feature/Auth/GoogleLoginTest.php tests/Feature/Jobs/CapturarAvatarGoogleJobTest.php
git commit -m "feat(auth/google): captura avatar do Google no login via job (respeita a foto do membro)"
```

---

## Task 5: "Remover foto" no `EditarPerfil` + flag do membro

**Files:**
- Modify: `app/Livewire/Conta/EditarPerfil.php`
- Modify: `resources/views/livewire/conta/editar-perfil.blade.php`
- Test: `tests/Feature/Conta/EditarPerfilTest.php`

**Interfaces:**
- Consumes: coluna `foto_definida_pelo_membro` (Task 1).

- [ ] **Step 1: Escrever os testes (falhando)**

Adicionar a `tests/Feature/Conta/EditarPerfilTest.php` (usando o login/setup já existente no arquivo; seguir o padrão dele):

```php
public function test_remover_foto_limpa_colecao_e_seta_flag(): void
{
    $user = $this->usuarioLogado(); // helper existente no arquivo (ou criar+actingAs)
    $perfil = $user->perfil()->firstOrCreate([]);
    $perfil->addMedia(\Illuminate\Http\UploadedFile::fake()->image('f.jpg')->getRealPath())
        ->usingFileName('f.jpg')->toMediaCollection(\App\Models\PerfilMembro::COLECAO_FOTO);

    \Livewire\Livewire::test(\App\Livewire\Conta\EditarPerfil::class)
        ->call('removerFoto')
        ->call('salvar');

    $this->assertFalse($perfil->fresh()->hasMedia(\App\Models\PerfilMembro::COLECAO_FOTO));
    $this->assertTrue($perfil->fresh()->foto_definida_pelo_membro);
}

public function test_upload_seta_flag_do_membro(): void
{
    $user = $this->usuarioLogado();
    $user->perfil()->firstOrCreate([]);

    \Livewire\Livewire::test(\App\Livewire\Conta\EditarPerfil::class)
        ->set('foto', \Illuminate\Http\UploadedFile::fake()->image('nova.jpg', 800, 800))
        ->call('salvar');

    $this->assertTrue($user->perfil->fresh()->foto_definida_pelo_membro);
    $this->assertTrue($user->perfil->fresh()->hasMedia(\App\Models\PerfilMembro::COLECAO_FOTO));
}
```

> Se o arquivo não tiver um helper `usuarioLogado()`, seguir exatamente o padrão de setup já usado nos outros testes do `EditarPerfilTest` (criar user com papel `frequentador` + `actingAs`).

- [ ] **Step 2: Rodar e ver falhar**

`docker compose exec -T app php artisan test --filter=EditarPerfilTest`
Esperado: FALHA (`removerFoto` não existe; flag não setada).

- [ ] **Step 3: Implementar no componente**

Em `app/Livewire/Conta/EditarPerfil.php`:
- Adicionar `public bool $removerFoto = false;`
- Adicionar métodos:

```php
public function removerFoto(): void
{
    $this->removerFoto = true;
    $this->foto = null; // remover e enviar são mutuamente exclusivos
}

public function temFoto(): bool
{
    return auth()->user()->perfil()->firstOrCreate([])->hasMedia(PerfilMembro::COLECAO_FOTO);
}
```

- No `salvar()`, dentro da transação, **depois** do `$perfil->update([...])`, ajustar o bloco da foto:

```php
if ($this->foto) {
    $perfil->addMedia($this->foto->getRealPath())
        ->usingFileName('foto.'.$this->foto->getClientOriginalExtension())
        ->toMediaCollection(PerfilMembro::COLECAO_FOTO);
    $perfil->foto_definida_pelo_membro = true;
    $perfil->save();
} elseif ($this->removerFoto) {
    $perfil->clearMediaCollection(PerfilMembro::COLECAO_FOTO);
    $perfil->foto_definida_pelo_membro = true;
    $perfil->save();
}
```

- [ ] **Step 4: Botão na view**

Em `resources/views/livewire/conta/editar-perfil.blade.php`, dentro do card da foto, adicionar (visível só quando há foto e sem remoção pendente):

```blade
@if ($this->temFoto() && ! $removerFoto)
    <button type="button" wire:click="removerFoto"
            class="mt-2 text-sm text-danger underline">Remover foto</button>
@elseif ($removerFoto)
    <p class="mt-2 text-xs text-text-muted">A foto será removida ao salvar.</p>
@endif
```

> Ajustar as classes ao estilo já usado no card; `$this->temFoto()` é o método público do componente.

- [ ] **Step 5: Rodar os testes**

`docker compose exec -T app php artisan test --filter=EditarPerfilTest`
Esperado: PASSAM (os antigos + os 2 novos).

- [ ] **Step 6: Commit**

```bash
git add app/Livewire/Conta/EditarPerfil.php resources/views/livewire/conta/editar-perfil.blade.php tests/Feature/Conta/EditarPerfilTest.php
git commit -m "feat(conta/perfil): botao Remover foto + flag foto_definida_pelo_membro"
```

---

## Task 6: Verificação final (inclui GATE do legado — bloqueante)

**Files:** nenhum (verificação).

- [ ] **Step 1: Suíte completa + Pint (container)**

```bash
docker compose exec -T app php artisan test
docker compose exec -T app ./vendor/bin/pint
docker compose exec -T app ./vendor/bin/pint --test
```
Esperado: toda a suíte verde; Pint sem drift. (Flaky conhecido dos 2 testes GD de cap do blog: reexecutar isolado se cair sob carga.)

- [ ] **Step 2: GATE OBRIGATÓRIO — SQL do leitor contra o legado vivo**

**Não mesclar sem este passo.** Com o **túnel SSH ativo**, conferir a resolução real (formato do `wp_user_avatar`/`_foto_de_perfil` e os `wp_posts.guid`):

```bash
docker compose exec -T app php artisan tinker --execute='
$c = 0;
foreach (app(App\Importacao\LeitorUsuariosMysql::class)->usuarios() as $u) {
    if (! empty($u["fotos_urls"])) {
        echo $u["email"], " => ", implode(" | ", $u["fotos_urls"]), PHP_EOL;
        if (++$c >= 15) break;
    }
}'
```
Conferir: URLs plausíveis (http(s) do domínio do CEMA), sem lixo; alguns `wp_user_avatar` resolvem a guids válidos. Se o formato divergir (ex.: `wp_user_avatar` serializado), ajustar o `candidatasFoto()` e re-verificar. **Se o túnel não estiver disponível, o merge fica bloqueado até este passo.**

- [ ] **Step 3: Ensaio real da migração (opcional, com túnel)**

```bash
docker compose exec -T app php artisan cema:importar-fotos-usuarios
```
Conferir o resumo (anexadas/puladas/sem_candidata/falhas) coerente com a cobertura esperada (~15%). Idempotência: rodar 2× → 2ª rodada com tudo em `puladas`.

- [ ] **Step 4: Verificação visual (Minha Conta)**

No dev (após `restart app worker`): abrir a edição do perfil — botão "Remover foto" aparece só com foto; remover → "será removida ao salvar" → Salvar → cai nas iniciais; Cancelar/sair descarta a remoção pendente.

---

## Self-Review (feito na escrita do plano)

- **Cobertura da spec:** §A → Tasks 2+3; §B → Task 4; §C → Tasks 1+5; regra transversal → `podeAutoPopularFoto()` (Task 1) usado em 3 e 4; GATE do legado → Task 6 Step 2.
- **Placeholders:** sem TBD; todo passo com código/comando + saída esperada. (Nota explícita no retorno do importador sobre a chave `sem_candidata`.)
- **Consistência de tipos/nomes:** `PerfilMembro::COLECAO_FOTO` e `podeAutoPopularFoto()` idênticos em todas as tasks; `fotos_urls` produzido na Task 2 e consumido na 3; `baixarCapado($url, 2000)`/`addMediaFromString(...)->usingFileName(...)` idênticos em 3 e 4; shape de retorno `{anexadas, puladas, sem_candidata, falhas, avisos}` batendo entre importador (Task 3) e comando (Task 3 Step 4).
- **Achados da verificação adversarial:** todos endereçados — mockGoogle+getAvatar (Task 4), bytes de imagem reais (Tasks 3/4), guard de conexão legado (Task 3), firstOrCreate na Parte B (Task 4), @unserialize defensivo (Task 2), colisão eliminada por bytes-em-memória (sem staging), GATE do legado (Task 6).
