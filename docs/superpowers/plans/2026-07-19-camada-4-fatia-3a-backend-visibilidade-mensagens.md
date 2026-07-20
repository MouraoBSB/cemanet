# Camada 4 · Fatia 3A — Backend da visibilidade rica das Mensagens (comportamento-neutro)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Criar o **backend da visibilidade rica** das Mensagens — o enum `VisibilidadeMensagem` (6 níveis reais), o resolvedor no model (`podeSerVistoPor`/`scopeVisiveisPara`, molde do `Evento`, com **escada + 3 recortes de pertencimento + bypass explícito**), o pivô `mensagem_destinatario` (N:N mensagem↔User, PII), a importação idempotente dos **73 vínculos / 15 mensagens / 17 destinatários** do legado (rel 38 reversa, casados por `origem_legado_id`), e os métodos `view`/`viewAny` na `MensagemPolicy`. **Comportamento-NEUTRO:** o front público segue com `Mensagem::publica()` (fixo da 2B); o resolvedor nasce **inerte**, provado por **teste de unidade** (matriz de 8 personas × 6 níveis). Front rico = 3B; curadoria = F4; engajamento = F5.

**Architecture:** O `VisibilidadeMensagem` é o catálogo tipado por cima do `nivel` string BRUTO (a coluna **não** é castada — castar quebraria 4 asserções da suíte 2A; a semântica vem de um **accessor derivado** `Mensagem::visibilidade()` via `tryFrom`, fail-closed em `null`/slug desconhecido). O resolvedor clona `Evento::podeSerVistoPor`/`scopeVisiveisPara` e **estende** com 3 recortes (`orWhere` por PERTENCIMENTO, não posição): Médiuns (setor `medium`), Diretor-DEPAE (cargo `diretor-do-depae`), Direcionada (pivô `mensagem_destinatario`). Como os recortes quebram o "nível-100-do-admin-cobre-tudo" do Evento, há **bypass explícito** de admin **e** presidente (o `Gate::before` é admin-only, então o presidente **precisa** do bypass no resolvedor E no scope). Os slugs de setor/cargo viram **constantes de segurança** em `Setor`/`Cargo` (O5, obrigatório do Consultor). A importação clona a cadeia dos `cema:importar-*` (Leitor interface + Mysql + Importador + Command + **bind de container obrigatório**), lendo `wp_jet_rel_default` rel 38 (parent=user → child=mensagem), agrupando por mensagem, casando cada destinatário por `origem_legado_id` (17/17 hoje), e sincronizando o pivô idempotentemente (user sem correspondente → aviso, nunca cria).

**Tech Stack:** PHP 8.3 · Laravel 13 · Filament 5 · MySQL 8 (dev/prod) e SQLite (testes) · spatie/laravel-permission · spatie/laravel-medialibrary · PHPUnit.

**Spec:** [`docs/superpowers/specs/2026-07-19-camada-4-fatia-3a-backend-visibilidade-mensagens.md`](../specs/2026-07-19-camada-4-fatia-3a-backend-visibilidade-mensagens.md) (aprovada pelo Consultor: **zero bloqueador**; O5 elevado a obrigatório; ciência do presidente/PII registrada para a 3B).

## Global Constraints

- **Idioma:** todo código, comentário, mensagem de UI/erro e commit em **português brasileiro**. Sintaxe e APIs de terceiros no original.
- **Branch:** criar `camada-4-fatia-3a-visibilidade` a partir de `origin/main` (= **`b7f9402`**, PR #38, Fatia 2B mesclada). **Nunca** na `main`. O PR leva código **e** os commits de docs (SPEC + este plano) juntos.
- **Cabeçalho de autoria** em todo arquivo PHP novo: `// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-19` (o factory do molde não tem cabeçalho — a edição da `MensagemFactory` preserva o estilo dela).
- **🚫 Banco (dev):** só `php artisan migrate` **incremental**. **PROIBIDO** `migrate:fresh`/`refresh`/`db:wipe`/`migrate:reset` e qualquer seed/factory destrutivo — o dev tem 152 usuários + 179 mensagens + mídia + etc. A conexão **`legado` é READ-ONLY** (só `SELECT`).
- **COMPORTAMENTO-NEUTRO (aceite duro):** a 3A **só ADICIONA**. **Nenhuma** asserção de teste existente muda de cor; **nenhuma** view/rota/Livewire/controller da 2B é tocada; `scopePublica`/`NIVEL_PUBLICO`/`casts` da `Mensagem` **intactos**; o `nivel` **NÃO é castado** (o accessor derivado preserva `$m->nivel` string — MensagemTest:98, ImportadorMensagensTest:90/100/238 seguem verdes).
- **I2 (fonte da verdade dos slugs):** os predicados de acesso a PII usam **constantes** `Setor::SLUG_MEDIUM='medium'`, `Cargo::SLUG_DIRETOR_DEPAE='diretor-do-depae'`, `Cargo::SLUG_PRESIDENTE='presidente'` — ancoradas no [EstruturaCemaSeeder](../../../database/seeders/EstruturaCemaSeeder.php#L37-L54) (`slug = Str::slug($nome)`), **nunca** string mágica no predicado (O5, obrigatório do Consultor).
- **I7/I8 (fail-closed):** `nivel=null`/slug desconhecido ⇒ `visibilidade()` = `null` ⇒ invisível a todos **menos** bypass (admin/presidente). **Nunca** tratar `null` como público.
- **Bypass nos DOIS pontos:** `podeSerVistoPor` (chamado por `MensagemPolicy::view`) **e** `scopeVisiveisPara` (que não passa por Gate) checam `hasRole('administrador') || ehPresidente()`. O [Gate::before](../../../app/Providers/AppServiceProvider.php#L71) cobre **só o admin** — o presidente depende do bypass.
- **PII:** o pivô `mensagem_destinatario` é PII; **não** é exposto em nenhuma view/Resource nesta fatia. Só o resolvedor e `User::mensagensDirecionadas()` o leem. O import é `SELECT`-only e casa por `origem_legado_id`.
- **I14 — bind de container obrigatório:** o command e o Importador type-hintam a **interface** `LeitorDirecionadasMensagem`; sem `bind(LeitorDirecionadasMensagem::class, LeitorDirecionadasMensagemMysql::class)` no `AppServiceProvider`, `cema:importar-direcionadas` quebra em **prod** com a **suíte verde**. Task 6 tem o teste-guarda (C7).
- **Aceite:** suíte verde (**~1011 + novos**) e **nenhuma** asserção existente muda de cor. `Pint` verde.
- **Comandos:** testes focados por task `docker compose exec -T app php artisan test --filter=X` (o projeto **não** usa Sail). **Pint antes de cada commit:** `docker compose exec -T app ./vendor/bin/pint <arquivos>` (o CI roda `pint --test` antes dos testes — [[pint-antes-de-push]]). Migrations no dev: `docker compose exec -T app php artisan migrate`. Se um teste rodar código aparentemente **stale** após editar um arquivo existente, `docker compose restart app worker` (OPcache `validate_timestamps=0`) e rode de novo.
- **Ciência de flaky:** [[flaky-importadorblog-gd-cap-imagem]] — 2 testes de cap de imagem do blog podem falhar sob carga; se passam isolados/no CI, não é regressão desta fatia.

---

### Task 0: Branch

**Files:** nenhum (só git).

- [ ] **Passo 1: Criar a branch a partir de `origin/main`**

```bash
cd "d:/Claude Code - Projetos/Cemanet - Novo Site"
git fetch origin
git switch -c camada-4-fatia-3a-visibilidade origin/main
git log --oneline -1
```

Esperado: HEAD em `b7f9402` (merge do PR #38 — Fatia 2B). Os commits de docs (SPEC + este plano) entram junto.

---

### Task 1: Enum `VisibilidadeMensagem` + constantes de slug (Setor/Cargo) + estado de factory

**Files:**
- Create: `app/Enums/VisibilidadeMensagem.php`
- Modify: `app/Models/Setor.php` (constante `SLUG_MEDIUM`)
- Modify: `app/Models/Cargo.php` (constantes `SLUG_DIRETOR_DEPAE`, `SLUG_PRESIDENTE`)
- Modify: `database/factories/MensagemFactory.php` (estado `comNivel`)
- Test: `tests/Unit/Enums/VisibilidadeMensagemTest.php`

**Interfaces:**
- Produces:
  - `App\Enums\VisibilidadeMensagem: string` — casos `Publico='publico'`, `Trabalhadores='trabalhadores'`, `Mediuns='mediuns-trabalhadores'`, `Diretores='diretores'`, `DiretorDepae='diretor-depae'`, `Direcionada='direcionada'`; `nivelMinimo(): ?int` (0/20/30 escada; `null` recorte); `ehRecorte(): bool`; `rotulo(): string`; `cor(): string`; `static opcoes(): array`.
  - `Setor::SLUG_MEDIUM = 'medium'`; `Cargo::SLUG_DIRETOR_DEPAE = 'diretor-do-depae'`; `Cargo::SLUG_PRESIDENTE = 'presidente'`.
  - `MensagemFactory::comNivel(VisibilidadeMensagem|string): static`.

**Contexto:** molde direto do [VisibilidadeEvento](../../../app/Enums/VisibilidadeEvento.php) (`nivelMinimo`/`rotulo`/`cor`/`opcoes`), estendido com `ehRecorte` (os 3 recortes têm `nivelMinimo()===null`). Os **backing values são os slugs REAIS** medidos no legado/dev DB (SPEC §4.1) — atenção a `mediuns-trabalhadores` (não "mediuns"). As constantes de slug ancoram os predicados (Task 3) na fonte única do [EstruturaCemaSeeder](../../../database/seeders/EstruturaCemaSeeder.php#L37-L54). `cor()` é placeholder (a paleta da badge é da 3B — SPEC §13-O6).

- [ ] **Passo 1: Escrever o teste do enum que falha**

`tests/Unit/Enums/VisibilidadeMensagemTest.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-19

namespace Tests\Unit\Enums;

use App\Enums\VisibilidadeMensagem;
use PHPUnit\Framework\TestCase;

class VisibilidadeMensagemTest extends TestCase
{
    public function test_tem_os_seis_niveis_com_slugs_reais(): void
    {
        $values = array_map(fn (VisibilidadeMensagem $v) => $v->value, VisibilidadeMensagem::cases());
        $this->assertSame(
            ['publico', 'trabalhadores', 'mediuns-trabalhadores', 'diretores', 'diretor-depae', 'direcionada'],
            $values,
        );
    }

    public function test_nivel_minimo_escada_vs_recorte(): void
    {
        $this->assertSame(0, VisibilidadeMensagem::Publico->nivelMinimo());
        $this->assertSame(20, VisibilidadeMensagem::Trabalhadores->nivelMinimo());
        $this->assertSame(30, VisibilidadeMensagem::Diretores->nivelMinimo());
        $this->assertNull(VisibilidadeMensagem::Mediuns->nivelMinimo());
        $this->assertNull(VisibilidadeMensagem::DiretorDepae->nivelMinimo());
        $this->assertNull(VisibilidadeMensagem::Direcionada->nivelMinimo());
    }

    public function test_eh_recorte(): void
    {
        $this->assertFalse(VisibilidadeMensagem::Publico->ehRecorte());
        $this->assertFalse(VisibilidadeMensagem::Trabalhadores->ehRecorte());
        $this->assertFalse(VisibilidadeMensagem::Diretores->ehRecorte());
        $this->assertTrue(VisibilidadeMensagem::Mediuns->ehRecorte());
        $this->assertTrue(VisibilidadeMensagem::DiretorDepae->ehRecorte());
        $this->assertTrue(VisibilidadeMensagem::Direcionada->ehRecorte());
    }

    public function test_opcoes_mapeia_value_para_rotulo(): void
    {
        $opcoes = VisibilidadeMensagem::opcoes();
        $this->assertSame('Público', $opcoes['publico']);
        $this->assertSame('Médiuns', $opcoes['mediuns-trabalhadores']);
        $this->assertSame('Diretor do DEPAE', $opcoes['diretor-depae']);
        $this->assertCount(6, $opcoes);
    }
}
```

- [ ] **Passo 2: Rodar o teste e ver falhar**

Run: `docker compose exec -T app php artisan test --filter=VisibilidadeMensagemTest`
Esperado: FAIL (`Class "App\Enums\VisibilidadeMensagem" not found`).

- [ ] **Passo 3: Criar o enum**

`app/Enums/VisibilidadeMensagem.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-19

namespace App\Enums;

enum VisibilidadeMensagem: string
{
    case Publico = 'publico';
    case Trabalhadores = 'trabalhadores';
    case Mediuns = 'mediuns-trabalhadores';   // RECORTE — setor Médium
    case Diretores = 'diretores';
    case DiretorDepae = 'diretor-depae';       // RECORTE — cargo Diretor do DEPAE
    case Direcionada = 'direcionada';          // RECORTE — pivô de destinatários

    /** Piso de escada (roles.nivel); null = RECORTE (pertencimento, não posição na escada). */
    public function nivelMinimo(): ?int
    {
        return match ($this) {
            self::Publico => 0,
            self::Trabalhadores => 20,
            self::Diretores => 30,
            self::Mediuns, self::DiretorDepae, self::Direcionada => null,
        };
    }

    /** Verdadeiro para os níveis de PERTENCIMENTO (Médiuns/Diretor-DEPAE/Direcionada). */
    public function ehRecorte(): bool
    {
        return $this->nivelMinimo() === null;
    }

    public function rotulo(): string
    {
        return match ($this) {
            self::Publico => 'Público',
            self::Trabalhadores => 'Trabalhadores',
            self::Mediuns => 'Médiuns',
            self::Diretores => 'Diretores',
            self::DiretorDepae => 'Diretor do DEPAE',
            self::Direcionada => 'Direcionada',
        };
    }

    /** Cor placeholder (AA). A paleta final da badge é da Fatia 3B (SPEC §13-O6). */
    public function cor(): string
    {
        return match ($this) {
            self::Publico => '#89AB98',
            self::Trabalhadores => '#6E9FCB',
            self::Mediuns => '#7C6FB0',
            self::Diretores => '#E79048',
            self::DiretorDepae => '#C9803B',
            self::Direcionada => '#C33A36',
        };
    }

    /** Mapa value => rótulo, para o Select do Filament (Fatias 3B/F4). */
    public static function opcoes(): array
    {
        $opcoes = [];
        foreach (self::cases() as $caso) {
            $opcoes[$caso->value] = $caso->rotulo();
        }

        return $opcoes;
    }
}
```

- [ ] **Passo 4: Adicionar as constantes de slug em `Setor` e `Cargo`**

Em `app/Models/Setor.php`, logo após `protected $table = 'setores';` ([:13](../../../app/Models/Setor.php#L13)):

```php
    /** Slug do setor Médium (Str::slug('Médium') no EstruturaCemaSeeder) — recorte de visibilidade. */
    public const SLUG_MEDIUM = 'medium';
```

Em `app/Models/Cargo.php`, logo após a abertura da classe ([:11-13](../../../app/Models/Cargo.php#L11-L13)):

```php
    /** Slugs de cargo usados como RECORTE/BYPASS de visibilidade (Str::slug do nome no EstruturaCemaSeeder). */
    public const SLUG_DIRETOR_DEPAE = 'diretor-do-depae';

    public const SLUG_PRESIDENTE = 'presidente';
```

- [ ] **Passo 5: Adicionar o estado `comNivel` na `MensagemFactory`**

Em `database/factories/MensagemFactory.php`, adicionar `use App\Enums\VisibilidadeMensagem;` no topo e o estado após `publica()` ([:44-47](../../../database/factories/MensagemFactory.php#L44-L47)):

```php
    /** Fixa o nível BRUTO (aceita o enum ou o slug cru) — para os testes de visibilidade. */
    public function comNivel(VisibilidadeMensagem|string $nivel): static
    {
        return $this->state(fn () => [
            'nivel' => $nivel instanceof VisibilidadeMensagem ? $nivel->value : $nivel,
        ]);
    }
```

- [ ] **Passo 6: Rodar o teste e ver passar**

Run: `docker compose exec -T app php artisan test --filter=VisibilidadeMensagemTest`
Esperado: PASS.

- [ ] **Passo 7: Pint + commit**

```bash
docker compose exec -T app ./vendor/bin/pint app/Enums/VisibilidadeMensagem.php app/Models/Setor.php app/Models/Cargo.php database/factories/MensagemFactory.php tests/Unit/Enums/VisibilidadeMensagemTest.php
git add app/Enums/VisibilidadeMensagem.php app/Models/Setor.php app/Models/Cargo.php database/factories/MensagemFactory.php tests/Unit/Enums/VisibilidadeMensagemTest.php
git commit -m "feat(camada-4-fatia-3a): enum VisibilidadeMensagem (6 niveis) + constantes de slug + estado comNivel"
```

---

### Task 2: Migration `mensagem_destinatario` + relações do pivô

**Files:**
- Create: `database/migrations/2026_07_19_000001_create_mensagem_destinatario_table.php`
- Modify: `app/Models/Mensagem.php` (relação `destinatarios()`)
- Modify: `app/Models/User.php` (relação `mensagensDirecionadas()`)
- Test: `tests/Feature/Mensagens/MensagemDestinatarioTest.php`

**Interfaces:**
- Produces:
  - Tabela `mensagem_destinatario` (`mensagem_id`/`user_id` FK cascade + unique `mensagem_destinatario_unique`).
  - `Mensagem::destinatarios(): BelongsToMany` (para `User`, pivô `mensagem_destinatario`).
  - `User::mensagensDirecionadas(): BelongsToMany` (para `Mensagem`, pivô `mensagem_destinatario`).

**Contexto:** pivô N:N mensagem↔user (PII). **cascade nos dois FKs** ⇒ deletar mensagem **ou** usuário remove os vínculos, sem órfão. Nome do unique explícito (padrão I17 da 2A). **Sem** `timestamps` (O8). A `Mensagem` ainda **não** importa `User` — este passo adiciona o `use App\Models\User;`.

- [ ] **Passo 1: Escrever o teste do pivô que falha**

`tests/Feature/Mensagens/MensagemDestinatarioTest.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-19

namespace Tests\Feature\Mensagens;

use App\Models\Mensagem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MensagemDestinatarioTest extends TestCase
{
    use RefreshDatabase;

    public function test_pivo_anexa_e_le_nos_dois_sentidos(): void
    {
        $m = Mensagem::factory()->create();
        $u1 = User::factory()->create();
        $u2 = User::factory()->create();

        $m->destinatarios()->sync([$u1->id, $u2->id]);

        $this->assertSame(2, $m->destinatarios()->count());
        $this->assertTrue($u1->mensagensDirecionadas()->where('mensagens.id', $m->id)->exists());
        $this->assertSame(1, $u1->mensagensDirecionadas()->count());
    }

    public function test_cascade_ao_deletar_mensagem_ou_usuario(): void
    {
        $m = Mensagem::factory()->create();
        $u = User::factory()->create();
        $m->destinatarios()->sync([$u->id]);
        $this->assertSame(1, DB::table('mensagem_destinatario')->count());

        $m->delete();
        $this->assertSame(0, DB::table('mensagem_destinatario')->count());

        $m2 = Mensagem::factory()->create();
        $m2->destinatarios()->sync([$u->id]);
        $u->delete();
        $this->assertSame(0, DB::table('mensagem_destinatario')->count());
    }
}
```

- [ ] **Passo 2: Rodar e ver falhar**

Run: `docker compose exec -T app php artisan test --filter=MensagemDestinatarioTest`
Esperado: FAIL (tabela/relação inexistente).

- [ ] **Passo 3: Criar a migration**

`database/migrations/2026_07_19_000001_create_mensagem_destinatario_table.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-19

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pivô das mensagens DIRECIONADAS (N:N mensagem↔usuário). É PII: só o resolvedor o consome.
        Schema::create('mensagem_destinatario', function (Blueprint $table) {
            $table->foreignId('mensagem_id')->constrained('mensagens')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unique(['mensagem_id', 'user_id'], 'mensagem_destinatario_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mensagem_destinatario');
    }
};
```

- [ ] **Passo 4: Rodar a migration no dev (incremental)**

Run: `docker compose exec -T app php artisan migrate`
Esperado: `Migrating: 2026_07_19_000001_create_mensagem_destinatario_table` → `DONE`. **Nunca** `migrate:fresh`.

- [ ] **Passo 5: Adicionar `destinatarios()` na `Mensagem`**

Em `app/Models/Mensagem.php`, adicionar `use App\Models\User;` no bloco de imports (após `use App\Models\Contracts\TemDepartamento;` [:9](../../../app/Models/Mensagem.php#L9)) e a relação após `relacionadas()` ([:80-83](../../../app/Models/Mensagem.php#L80-L83)):

```php
    /** Destinatários de uma mensagem DIRECIONADA (N:N, PII). Só o resolvedor de visibilidade o lê. */
    public function destinatarios(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'mensagem_destinatario', 'mensagem_id', 'user_id');
    }
```

- [ ] **Passo 6: Adicionar `mensagensDirecionadas()` no `User`**

Em `app/Models/User.php`, adicionar após `atributos()` ([:64-68](../../../app/Models/User.php#L64-L68)) (`BelongsToMany` e `Mensagem` já são resolvíveis — `BelongsToMany` está importado [:14](../../../app/Models/User.php#L14) e `Mensagem` é do mesmo namespace `App\Models`):

```php
    /** Mensagens direcionadas a este usuário (N:N, PII). Lado inverso de Mensagem::destinatarios(). */
    public function mensagensDirecionadas(): BelongsToMany
    {
        return $this->belongsToMany(Mensagem::class, 'mensagem_destinatario', 'user_id', 'mensagem_id');
    }
```

- [ ] **Passo 7: Rodar e ver passar**

Run: `docker compose exec -T app php artisan test --filter=MensagemDestinatarioTest`
Esperado: PASS.

- [ ] **Passo 8: Pint + commit**

```bash
docker compose exec -T app ./vendor/bin/pint database/migrations/2026_07_19_000001_create_mensagem_destinatario_table.php app/Models/Mensagem.php app/Models/User.php tests/Feature/Mensagens/MensagemDestinatarioTest.php
git add database/migrations/2026_07_19_000001_create_mensagem_destinatario_table.php app/Models/Mensagem.php app/Models/User.php tests/Feature/Mensagens/MensagemDestinatarioTest.php
git commit -m "feat(camada-4-fatia-3a): pivo mensagem_destinatario (PII) + relacoes destinatarios/mensagensDirecionadas"
```

---

### Task 3: Predicados de pertencimento no `User` + accessor `visibilidade()` na `Mensagem`

**Files:**
- Modify: `app/Models/User.php` (`ehMedium`, `ehDiretorDepae`, `ehPresidente`)
- Modify: `app/Models/Mensagem.php` (accessor `visibilidade()`)
- Test: `tests/Feature/Usuarios/UserPertencimentoTest.php`
- Test: `tests/Feature/Mensagens/MensagemVisibilidadeAccessorTest.php`

**Interfaces:**
- Consumes: `Setor::SLUG_MEDIUM`, `Cargo::SLUG_DIRETOR_DEPAE`, `Cargo::SLUG_PRESIDENTE` (Task 1); `VisibilidadeMensagem` (Task 1).
- Produces:
  - `User::ehMedium(): bool`, `User::ehDiretorDepae(): bool`, `User::ehPresidente(): bool`.
  - `Mensagem::visibilidade(): ?VisibilidadeMensagem` (via `tryFrom`; `null` para `nivel=null` **e** slug desconhecido).

**Contexto:** os predicados são **atômicos** (cada um 1 `exists()` qualificado pelo slug-constante). O accessor é **método** (não `Attribute`) para não colidir com resolução de relação do Eloquent (SPEC §13-O2). O `UserPertencimentoTest` semeia o [EstruturaCemaSeeder](../../../database/seeders/EstruturaCemaSeeder.php) — assim as constantes de slug ficam **ancoradas** às linhas reais (se o seeder gerar outro slug, o teste reprova). O `MensagemVisibilidadeAccessorTest` prova a **neutralidade** (`$m->nivel` continua string) e o **fail-closed** (slug desconhecido → `null`).

- [ ] **Passo 1: Escrever o teste dos predicados que falha**

`tests/Feature/Usuarios/UserPertencimentoTest.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-19

namespace Tests\Feature\Usuarios;

use App\Models\Cargo;
use App\Models\Setor;
use App\Models\User;
use Database\Seeders\EstruturaCemaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserPertencimentoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstruturaCemaSeeder::class);
    }

    public function test_eh_medium_pelo_setor(): void
    {
        $medium = User::factory()->create();
        $medium->setores()->attach(Setor::where('slug', Setor::SLUG_MEDIUM)->value('id'), ['funcao' => 'membro']);
        $outro = User::factory()->create();

        $this->assertTrue($medium->fresh()->ehMedium());
        $this->assertFalse($outro->ehMedium());
    }

    public function test_eh_diretor_depae_e_presidente_pelo_cargo(): void
    {
        $depae = User::factory()->create();
        $depae->cargos()->attach(Cargo::where('slug', Cargo::SLUG_DIRETOR_DEPAE)->value('id'));

        $presidente = User::factory()->create();
        $presidente->cargos()->attach(Cargo::where('slug', Cargo::SLUG_PRESIDENTE)->value('id'));

        $this->assertTrue($depae->fresh()->ehDiretorDepae());
        $this->assertFalse($depae->ehPresidente());
        $this->assertTrue($presidente->fresh()->ehPresidente());
        $this->assertFalse($presidente->ehDiretorDepae());
    }
}
```

- [ ] **Passo 2: Escrever o teste do accessor que falha**

`tests/Feature/Mensagens/MensagemVisibilidadeAccessorTest.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-19

namespace Tests\Feature\Mensagens;

use App\Enums\VisibilidadeMensagem;
use App\Models\Mensagem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MensagemVisibilidadeAccessorTest extends TestCase
{
    use RefreshDatabase;

    public function test_reidrata_o_enum_a_partir_do_slug(): void
    {
        $m = Mensagem::factory()->comNivel(VisibilidadeMensagem::Mediuns)->create();
        $this->assertSame(VisibilidadeMensagem::Mediuns, $m->visibilidade());
    }

    public function test_null_e_slug_desconhecido_dao_null_fail_closed(): void
    {
        $this->assertNull(Mensagem::factory()->create(['nivel' => null])->visibilidade());
        $this->assertNull(Mensagem::factory()->create(['nivel' => 'xpto-inexistente'])->visibilidade());
    }

    public function test_nivel_permanece_string_bruta_neutralidade(): void
    {
        $m = Mensagem::factory()->comNivel('trabalhadores')->create();
        $this->assertSame('trabalhadores', $m->nivel); // NÃO castado — a suite 2A segue verde
    }
}
```

- [ ] **Passo 3: Rodar os dois e ver falhar**

Run: `docker compose exec -T app php artisan test --filter="UserPertencimentoTest|MensagemVisibilidadeAccessorTest"`
Esperado: FAIL (métodos inexistentes).

- [ ] **Passo 4: Implementar os predicados no `User`**

Em `app/Models/User.php`, após `nivelMaximo()` ([:92-96](../../../app/Models/User.php#L92-L96)) (`Setor`/`Cargo` são do mesmo namespace `App\Models` — sem `use`):

```php
    /** Recorte "Médiuns": pertence ao setor Médium (fonte única: Setor::SLUG_MEDIUM). */
    public function ehMedium(): bool
    {
        return $this->setores()->where('setores.slug', Setor::SLUG_MEDIUM)->exists();
    }

    /** Recorte "Diretor-DEPAE": ocupa o cargo Diretor do DEPAE. */
    public function ehDiretorDepae(): bool
    {
        return $this->cargos()->where('cargos.slug', Cargo::SLUG_DIRETOR_DEPAE)->exists();
    }

    /** Bypass "Presidente": ocupa o cargo institucional Presidente (vê tudo, como o admin). */
    public function ehPresidente(): bool
    {
        return $this->cargos()->where('cargos.slug', Cargo::SLUG_PRESIDENTE)->exists();
    }
```

- [ ] **Passo 5: Implementar o accessor na `Mensagem`**

Em `app/Models/Mensagem.php`, adicionar `use App\Enums\VisibilidadeMensagem;` (após `use App\Enums\FormatoMensagem;` [:7](../../../app/Models/Mensagem.php#L7)) e o método após `scopePublica()` ([:63-68](../../../app/Models/Mensagem.php#L63-L68)):

```php
    /**
     * Visibilidade tipada derivada do slug BRUTO em `nivel` (não é cast — `->nivel` segue string,
     * preservando a suíte 2A). `tryFrom` devolve null para null E para slug desconhecido ⇒ fail-closed.
     */
    public function visibilidade(): ?VisibilidadeMensagem
    {
        return $this->nivel !== null ? VisibilidadeMensagem::tryFrom($this->nivel) : null;
    }
```

- [ ] **Passo 6: Rodar e ver passar**

Run: `docker compose exec -T app php artisan test --filter="UserPertencimentoTest|MensagemVisibilidadeAccessorTest"`
Esperado: PASS.

- [ ] **Passo 7: Pint + commit**

```bash
docker compose exec -T app ./vendor/bin/pint app/Models/User.php app/Models/Mensagem.php tests/Feature/Usuarios/UserPertencimentoTest.php tests/Feature/Mensagens/MensagemVisibilidadeAccessorTest.php
git add app/Models/User.php app/Models/Mensagem.php tests/Feature/Usuarios/UserPertencimentoTest.php tests/Feature/Mensagens/MensagemVisibilidadeAccessorTest.php
git commit -m "feat(camada-4-fatia-3a): predicados de pertencimento (User) + accessor visibilidade (fail-closed, sem cast)"
```

---

### Task 4: Resolvedor — `podeSerVistoPor` + `scopeVisiveisPara` (escada + 3 recortes + bypass) · CP-1

**Files:**
- Modify: `app/Models/Mensagem.php` (`veTudo`, `podeSerVistoPor`, `scopeVisiveisPara`)
- Test: `tests/Feature/Mensagens/MensagemVisibilidadeAcessoTest.php`

**Interfaces:**
- Consumes: `Mensagem::visibilidade()`/`destinatarios()` (Tasks 2/3); `User::nivelMaximo()`/`ehMedium()`/`ehDiretorDepae()`/`ehPresidente()`/`hasRole()`; `VisibilidadeMensagem::nivelMinimo()`.
- Produces:
  - `Mensagem::podeSerVistoPor(?User): bool` (item a item; usado pela Policy `view`).
  - `Mensagem::scopeVisiveisPara(Builder, ?User): Builder` → `Mensagem::visiveisPara($u)` (filtra no banco, não vaza).

**Contexto:** molde [Evento::podeSerVistoPor/scopeVisiveisPara](../../../app/Models/Evento.php#L60-L89), **estendido**: bypass explícito (`veTudo`), 3 recortes por pertencimento, e `nivel=null` fail-closed. O `where(fn)` externo isola os `orWhere` (compõe com `status` que a 3B encadeie). **A matriz de 8 personas × 6 níveis é a prova de que o scope NÃO vaza — não relaxar o count por persona** (obrigatório do Consultor). `hasRole` vem do trait `HasRoles`.

- [ ] **Passo 1: Escrever o teste-matriz que falha**

`tests/Feature/Mensagens/MensagemVisibilidadeAcessoTest.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-19

namespace Tests\Feature\Mensagens;

use App\Enums\VisibilidadeMensagem;
use App\Models\Cargo;
use App\Models\Mensagem;
use App\Models\Setor;
use App\Models\User;
use Database\Seeders\EstruturaCemaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MensagemVisibilidadeAcessoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstruturaCemaSeeder::class); // papéis 10/20/30/100 + setores + cargos
    }

    /** Usuário com papel + setores/cargos opcionais (slugs). null (tudo vazio) = anônimo. */
    private function usuario(?string $papel, array $setores = [], array $cargos = []): ?User
    {
        if ($papel === null && $setores === [] && $cargos === []) {
            return null;
        }
        $u = User::factory()->create();
        if ($papel !== null) {
            $u->assignRole($papel);
        }
        foreach ($setores as $slug) {
            $u->setores()->attach(Setor::where('slug', $slug)->value('id'), ['funcao' => 'membro']);
        }
        foreach ($cargos as $slug) {
            $u->cargos()->attach(Cargo::where('slug', $slug)->value('id'));
        }

        return $u->fresh();
    }

    private function mensagem(?string $nivel, string $slug): Mensagem
    {
        return Mensagem::factory()->create(['nivel' => $nivel, 'slug' => $slug]);
    }

    public function test_matriz_pode_ser_visto_por(): void
    {
        // uma mensagem por nível de ESCADA/RECORTE fixo
        $msgs = [
            'publico' => $this->mensagem('publico', 'm-pub'),
            'trabalhadores' => $this->mensagem('trabalhadores', 'm-trab'),
            'mediuns' => $this->mensagem('mediuns-trabalhadores', 'm-med'),
            'diretores' => $this->mensagem('diretores', 'm-dir'),
            'diretor-depae' => $this->mensagem('diretor-depae', 'm-dd'),
        ];

        $medium = $this->usuario('trabalhador', [Setor::SLUG_MEDIUM]);
        $diretorDepae = $this->usuario('diretor', [], [Cargo::SLUG_DIRETOR_DEPAE]);
        $presidente = $this->usuario('frequentador', [], [Cargo::SLUG_PRESIDENTE]); // cargo baixo → bypass mesmo assim

        // [persona, [publico, trabalhadores, mediuns, diretores, diretor-depae]]
        $matriz = [
            [$this->usuario(null), [true, false, false, false, false]],          // anônimo
            [$this->usuario('frequentador'), [true, false, false, false, false]],
            [$this->usuario('trabalhador'), [true, true, false, false, false]],
            [$medium, [true, true, true, false, false]],                          // recorte médium
            [$this->usuario('diretor'), [true, true, false, true, false]],        // NÃO vê médiuns
            [$diretorDepae, [true, true, false, true, true]],                     // recorte diretor-depae
            [$presidente, [true, true, true, true, true]],                        // bypass
            [$this->usuario('administrador'), [true, true, true, true, true]],    // bypass
        ];

        foreach ($matriz as [$u, $esperado]) {
            $i = 0;
            foreach ($msgs as $chave => $m) {
                $this->assertSame($esperado[$i], $m->podeSerVistoPor($u), "persona x {$chave}");
                $i++;
            }
        }
    }

    public function test_direcionada_so_destinatario_e_bypass(): void
    {
        $dirA = $this->mensagem('direcionada', 'm-dirA');
        $dirB = $this->mensagem('direcionada', 'm-dirB');
        $destinatario = $this->usuario('frequentador');
        $dirA->destinatarios()->attach($destinatario->id);

        $this->assertTrue($dirA->podeSerVistoPor($destinatario));
        $this->assertFalse($dirB->podeSerVistoPor($destinatario)); // não é destinatário de B
        $this->assertFalse($dirA->podeSerVistoPor($this->usuario('diretor'))); // diretor não é destinatário
        $this->assertTrue($dirA->podeSerVistoPor($this->usuario('administrador')));
        $this->assertTrue($dirA->podeSerVistoPor($this->usuario('frequentador', [], [Cargo::SLUG_PRESIDENTE])));
    }

    public function test_nivel_null_fail_closed_menos_bypass(): void
    {
        $nula = $this->mensagem(null, 'm-null');
        $this->assertFalse($nula->podeSerVistoPor(null));
        $this->assertFalse($nula->podeSerVistoPor($this->usuario('diretor')));
        $this->assertTrue($nula->podeSerVistoPor($this->usuario('administrador')));
        $this->assertTrue($nula->podeSerVistoPor($this->usuario('frequentador', [], [Cargo::SLUG_PRESIDENTE])));
    }

    public function test_scope_visiveis_para_nao_vaza(): void
    {
        // 8 mensagens: 5 níveis fixos + 2 direcionadas + 1 sem nível
        $this->mensagem('publico', 's-pub');
        $this->mensagem('trabalhadores', 's-trab');
        $this->mensagem('mediuns-trabalhadores', 's-med');
        $this->mensagem('diretores', 's-dir');
        $this->mensagem('diretor-depae', 's-dd');
        $dirA = $this->mensagem('direcionada', 's-dirA');
        $this->mensagem('direcionada', 's-dirB');
        $this->mensagem(null, 's-null');

        $destinatario = $this->usuario('frequentador');
        $dirA->destinatarios()->attach($destinatario->id);

        $this->assertSame(1, Mensagem::visiveisPara(null)->count());
        $this->assertSame(['publico'], Mensagem::visiveisPara(null)->pluck('nivel')->all());
        $this->assertSame(1, Mensagem::visiveisPara($this->usuario('frequentador'))->count());
        $this->assertSame(2, Mensagem::visiveisPara($this->usuario('trabalhador'))->count());
        $this->assertSame(3, Mensagem::visiveisPara($this->usuario('trabalhador', [Setor::SLUG_MEDIUM]))->count());
        $this->assertSame(3, Mensagem::visiveisPara($this->usuario('diretor'))->count());
        $this->assertSame(4, Mensagem::visiveisPara($this->usuario('diretor', [], [Cargo::SLUG_DIRETOR_DEPAE]))->count());
        $this->assertSame(2, Mensagem::visiveisPara($destinatario)->count()); // publico + a direcionada dele
        $this->assertSame(8, Mensagem::visiveisPara($this->usuario('administrador'))->count()); // bypass = tudo
        $this->assertSame(8, Mensagem::visiveisPara($this->usuario('frequentador', [], [Cargo::SLUG_PRESIDENTE]))->count());
    }
}
```

- [ ] **Passo 2: Rodar e ver falhar**

Run: `docker compose exec -T app php artisan test --filter=MensagemVisibilidadeAcessoTest`
Esperado: FAIL (`podeSerVistoPor`/`visiveisPara` inexistentes).

- [ ] **Passo 3: Implementar o resolvedor na `Mensagem`**

Em `app/Models/Mensagem.php`, adicionar após o accessor `visibilidade()` (Task 3). (`Builder`/`User` já importados; `VisibilidadeMensagem` também.)

```php
    /** Bypass total de visibilidade: admin (papel nível 100) OU presidente (cargo). */
    private static function veTudo(?User $usuario): bool
    {
        return $usuario !== null && ($usuario->hasRole('administrador') || $usuario->ehPresidente());
    }

    /**
     * Regra de visibilidade por papel + pertencimento (fonte única). Escada (Público/Trabalhadores/
     * Diretores) + 3 recortes (Médiuns/Diretor-DEPAE/Direcionada). null = fail-closed; admin/presidente = bypass.
     */
    public function podeSerVistoPor(?User $usuario): bool
    {
        if (self::veTudo($usuario)) {
            return true;
        }

        $visibilidade = $this->visibilidade();
        if ($visibilidade === null) {
            return false; // nível null/desconhecido = fail-closed
        }

        $nivel = $usuario?->nivelMaximo() ?? 0;

        return match ($visibilidade) {
            VisibilidadeMensagem::Publico => true,
            VisibilidadeMensagem::Trabalhadores => $nivel >= VisibilidadeMensagem::Trabalhadores->nivelMinimo(),
            VisibilidadeMensagem::Diretores => $nivel >= VisibilidadeMensagem::Diretores->nivelMinimo(),
            VisibilidadeMensagem::Mediuns => $usuario !== null && $usuario->ehMedium(),
            VisibilidadeMensagem::DiretorDepae => $usuario !== null && $usuario->ehDiretorDepae(),
            VisibilidadeMensagem::Direcionada => $usuario !== null
                && $this->destinatarios()->whereKey($usuario->id)->exists(),
        };
    }

    /** Filtra no banco as mensagens que o usuário (ou anônimo) pode ver — não vaza título restrito. */
    public function scopeVisiveisPara(Builder $query, ?User $usuario): Builder
    {
        if (self::veTudo($usuario)) {
            return $query; // bypass: sem filtro (vê tudo, inclusive nível null)
        }

        $nivel = $usuario?->nivelMaximo() ?? 0;

        return $query->where(function (Builder $q) use ($usuario, $nivel) {
            $q->where('nivel', VisibilidadeMensagem::Publico->value); // sempre

            if ($usuario !== null) {
                if ($nivel >= VisibilidadeMensagem::Trabalhadores->nivelMinimo()) {
                    $q->orWhere('nivel', VisibilidadeMensagem::Trabalhadores->value);
                }
                if ($nivel >= VisibilidadeMensagem::Diretores->nivelMinimo()) {
                    $q->orWhere('nivel', VisibilidadeMensagem::Diretores->value);
                }
                if ($usuario->ehMedium()) {
                    $q->orWhere('nivel', VisibilidadeMensagem::Mediuns->value);
                }
                if ($usuario->ehDiretorDepae()) {
                    $q->orWhere('nivel', VisibilidadeMensagem::DiretorDepae->value);
                }
                // Direcionada: só as mensagens em que ESTE usuário é destinatário (não vaza as dos outros).
                $q->orWhere(fn (Builder $d) => $d
                    ->where('nivel', VisibilidadeMensagem::Direcionada->value)
                    ->whereHas('destinatarios', fn (Builder $u) => $u->whereKey($usuario->id)));
            }
        });
    }
```

- [ ] **Passo 4: Rodar e ver passar**

Run: `docker compose exec -T app php artisan test --filter=MensagemVisibilidadeAcessoTest`
Esperado: PASS (todas as personas batem; o count do scope confere).

- [ ] **Passo 5: Pint + commit**

```bash
docker compose exec -T app ./vendor/bin/pint app/Models/Mensagem.php tests/Feature/Mensagens/MensagemVisibilidadeAcessoTest.php
git add app/Models/Mensagem.php tests/Feature/Mensagens/MensagemVisibilidadeAcessoTest.php
git commit -m "feat(camada-4-fatia-3a): resolvedor de visibilidade (escada + 3 recortes + bypass), matriz de personas"
```

> **CP-1 (fim da Task 4):** o resolvedor está verde — a matriz de 8 personas × 6 níveis prova que `scopeVisiveisPara` não vaza. Rodar a **suíte completa** (`docker compose exec -T app php artisan test`) para confirmar que a 2A/2B não mudou de cor (`nivel` não castado). Só então seguir.

---

### Task 5: Policy `view`/`viewAny` (eixo de visibilidade, delegando ao resolvedor)

**Files:**
- Modify: `app/Policies/MensagemPolicy.php` (`view`, `viewAny` + docblock de "dois eixos")
- Test: `tests/Feature/Mensagens/MensagemPolicyVisibilidadeTest.php`

**Interfaces:**
- Consumes: `Mensagem::podeSerVistoPor` (Task 4); `Gate::before` (admin) — [AppServiceProvider:71](../../../app/Providers/AppServiceProvider.php#L71).
- Produces: `MensagemPolicy::view(?User, Mensagem): bool`, `MensagemPolicy::viewAny(?User): bool`.

**Contexto:** molde [EventoPolicy](../../../app/Policies/EventoPolicy.php#L30-L38): `view` delega a `podeSerVistoPor`; `viewAny` = `true` (a listagem filtra pelo scope). `$user` **nullável** nesses dois (anônimo passa por `Gate::forUser(null)`); as 4 de **capacidade** (`ver/criar/editar/excluir`) **permanecem intactas** — **não** editar o `MensagemPolicyCapacidadeTest` (2A). Filament não usa strict authorization ⇒ o `/admin` não muda (o admin vê tudo pelo `Gate::before`).

- [ ] **Passo 1: Escrever o teste da policy que falha**

`tests/Feature/Mensagens/MensagemPolicyVisibilidadeTest.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-19

namespace Tests\Feature\Mensagens;

use App\Models\Mensagem;
use App\Models\User;
use Database\Seeders\EstruturaCemaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class MensagemPolicyVisibilidadeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstruturaCemaSeeder::class);
    }

    private function usuario(string $papel): User
    {
        $u = User::factory()->create();
        $u->assignRole($papel);

        return $u->fresh();
    }

    public function test_view_delega_a_pode_ser_visto_por(): void
    {
        $publica = Mensagem::factory()->create(['nivel' => 'publico', 'slug' => 'p-pub']);
        $restrita = Mensagem::factory()->create(['nivel' => 'diretores', 'slug' => 'p-dir']);

        $this->assertTrue(Gate::forUser($this->usuario('diretor'))->allows('view', $restrita));
        $this->assertFalse(Gate::forUser($this->usuario('frequentador'))->allows('view', $restrita));
        $this->assertFalse(Gate::forUser(null)->allows('view', $restrita)); // anônimo negado no restrito
        $this->assertTrue(Gate::forUser(null)->allows('view', $publica));    // anônimo vê a pública
        $this->assertTrue(Gate::forUser($this->usuario('administrador'))->allows('view', $restrita)); // Gate::before
    }

    public function test_view_any_sempre_permitido(): void
    {
        $this->assertTrue(Gate::forUser($this->usuario('frequentador'))->allows('viewAny', Mensagem::class));
        $this->assertTrue(Gate::forUser(null)->allows('viewAny', Mensagem::class));
    }
}
```

- [ ] **Passo 2: Rodar e ver falhar**

Run: `docker compose exec -T app php artisan test --filter=MensagemPolicyVisibilidadeTest`
Esperado: FAIL (a policy nega `view` do público ao anônimo — o método não existe, cai no default deny).

- [ ] **Passo 3: Implementar `view`/`viewAny` na `MensagemPolicy`**

Em `app/Policies/MensagemPolicy.php`, atualizar o docblock para "dois eixos" (molde `EventoPolicy`) e adicionar, antes de `ver()` ([:27](../../../app/Policies/MensagemPolicy.php#L27)):

```php
    public function view(?User $user, Mensagem $mensagem): bool
    {
        return $mensagem->podeSerVistoPor($user);
    }

    public function viewAny(?User $user): bool
    {
        return true; // a listagem filtra por scopeVisiveisPara; não há bloqueio geral
    }
```

Docblock (substituir o parágrafo de topo por, mantendo a nota da capacidade):

```php
/**
 * Policy de Mensagem nos DOIS eixos:
 * - VISIBILIDADE (quem vê): view/viewAny, delegadas a podeSerVistoPor / scopeVisiveisPara.
 *   $user é null-safe (visitante anônimo passa por Gate::forUser(null)).
 * - CAPACIDADE (quem edita): ver/criar/editar/excluir — permissão mensagem.* (hasPermissionTo, NUNCA can())
 *   + escopo por regime DoTipo (trait). Nasce INERTE (só admin edita via /admin). O eixo de autoria do
 *   médium (mensagem.publicar / definir-nivel) é outro eixo — Fatia 4. O admin passa antes no Gate::before.
 */
```

- [ ] **Passo 4: Rodar e ver passar**

Run: `docker compose exec -T app php artisan test --filter=MensagemPolicyVisibilidadeTest`
Esperado: PASS. **Conferir** que `MensagemPolicyCapacidadeTest` (2A) **segue verde**:
`docker compose exec -T app php artisan test --filter="MensagemPolicyVisibilidadeTest|MensagemPolicyCapacidadeTest"`.

- [ ] **Passo 5: Pint + commit**

```bash
docker compose exec -T app ./vendor/bin/pint app/Policies/MensagemPolicy.php tests/Feature/Mensagens/MensagemPolicyVisibilidadeTest.php
git add app/Policies/MensagemPolicy.php tests/Feature/Mensagens/MensagemPolicyVisibilidadeTest.php
git commit -m "feat(camada-4-fatia-3a): MensagemPolicy view/viewAny (visibilidade), capacidade intacta"
```

---

### Task 6: Cadeia de importação das direcionadas — Leitor + Importador + Command + bind · CP-2

**Files:**
- Create: `app/Importacao/LeitorDirecionadasMensagem.php`
- Create: `app/Importacao/LeitorDirecionadasMensagemMysql.php`
- Create: `app/Importacao/ImportadorDirecionadasMensagens.php`
- Create: `app/Console/Commands/ImportarDirecionadasMensagens.php`
- Modify: `app/Providers/AppServiceProvider.php` (1 bind — C7)
- Test: `tests/Feature/Importacao/ImportadorDirecionadasMensagensTest.php`
- Test: `tests/Feature/Importacao/ImportarDirecionadasCommandTest.php`

**Interfaces:**
- Consumes: `Mensagem` (por `wp_id`), `User` (por `origem_legado_id`), `Mensagem::destinatarios()` (Task 2).
- Produces:
  - `App\Importacao\LeitorDirecionadasMensagem` — `direcionadas(): array` (`[['wp_id'=>int,'destinatarios_wp_ids'=>int[]], …]`).
  - `App\Importacao\LeitorDirecionadasMensagemMysql` (SQL da rel 38 reversa).
  - `App\Importacao\ImportadorDirecionadasMensagens::importar(callable $log): array` (contadores).
  - `App\Console\Commands\ImportarDirecionadasMensagens` — `cema:importar-direcionadas`.
  - Bind `LeitorDirecionadasMensagem::class → …Mysql::class`.

**Contexto:** clona a cadeia dos `cema:importar-*`. O SQL da rel 38 (SPEC §4.3) é **reverso** — `parent=usuário → child=mensagem` — com `JOIN wp_users` (garante parent-usuário) + `JOIN wp_posts (post_type)` (garante child-mensagem). Agrupa por mensagem. O importador casa cada `wp_user_id` por `origem_legado_id` (17/17 hoje), `sync` idempotente; user/mensagem sem correspondente → **aviso**, nunca cria, não quebra. O command valida `legado->getPdo()` **só** com o leitor real (o teste injeta fake). O **bind é obrigatório** (C7).

- [ ] **Passo 1: Escrever o teste do importador que falha**

`tests/Feature/Importacao/ImportadorDirecionadasMensagensTest.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-19

namespace Tests\Feature\Importacao;

use App\Importacao\ImportadorDirecionadasMensagens;
use App\Importacao\LeitorDirecionadasMensagem;
use App\Models\Mensagem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportadorDirecionadasMensagensTest extends TestCase
{
    use RefreshDatabase;

    /** @param array<int, array{wp_id:int, destinatarios_wp_ids:array<int,int>}> $dados */
    private function leitor(array $dados): LeitorDirecionadasMensagem
    {
        return new class($dados) implements LeitorDirecionadasMensagem
        {
            public function __construct(private array $dados) {}

            public function direcionadas(): array
            {
                return $this->dados;
            }
        };
    }

    private function fixtures(): void
    {
        Mensagem::factory()->create(['wp_id' => 100, 'slug' => 'm100', 'nivel' => 'direcionada']);
        Mensagem::factory()->create(['wp_id' => 101, 'slug' => 'm101', 'nivel' => 'direcionada']);
        User::factory()->create(['origem_legado_id' => 900]);
        User::factory()->create(['origem_legado_id' => 901]);
        // 902 NÃO existe (destinatário sem User); wp_id 999 NÃO existe (mensagem ausente).
    }

    public function test_casa_por_origem_legado_id_e_conta(): void
    {
        $this->fixtures();
        $importador = new ImportadorDirecionadasMensagens($this->leitor([
            ['wp_id' => 100, 'destinatarios_wp_ids' => [900, 901, 902]],
            ['wp_id' => 101, 'destinatarios_wp_ids' => [900]],
            ['wp_id' => 999, 'destinatarios_wp_ids' => [900]],
        ]));

        $resumo = $importador->importar(fn () => null);

        $this->assertSame(2, $resumo['direcionadas']);           // 100 e 101 (999 pulada)
        $this->assertSame(3, $resumo['vinculos']);               // 100→[900,901], 101→[900]
        $this->assertSame(2, $resumo['destinatarios_distintos']); // 900, 901
        $this->assertSame(1, $resumo['mensagem_nao_encontrada']); // 999
        $this->assertSame(1, $resumo['user_nao_encontrado']);     // 902

        $m100 = Mensagem::firstWhere('wp_id', 100);
        $this->assertEqualsCanonicalizing(
            [900, 901],
            $m100->destinatarios()->pluck('origem_legado_id')->map(fn ($v) => (int) $v)->all(),
        );
        $this->assertSame(
            [900],
            Mensagem::firstWhere('wp_id', 101)->destinatarios()->pluck('origem_legado_id')->map(fn ($v) => (int) $v)->all(),
        );
    }

    public function test_idempotente_e_sync_substitui(): void
    {
        $this->fixtures();

        $primeiro = new ImportadorDirecionadasMensagens($this->leitor([
            ['wp_id' => 100, 'destinatarios_wp_ids' => [900, 901]],
        ]));
        $primeiro->importar(fn () => null);
        $primeiro->importar(fn () => null); // 2ª rodada não duplica
        $this->assertSame(2, Mensagem::firstWhere('wp_id', 100)->destinatarios()->count());

        // sync substitui: agora só 900
        (new ImportadorDirecionadasMensagens($this->leitor([
            ['wp_id' => 100, 'destinatarios_wp_ids' => [900]],
        ])))->importar(fn () => null);
        $this->assertSame(
            [900],
            Mensagem::firstWhere('wp_id', 100)->destinatarios()->pluck('origem_legado_id')->map(fn ($v) => (int) $v)->all(),
        );
    }
}
```

- [ ] **Passo 2: Rodar e ver falhar**

Run: `docker compose exec -T app php artisan test --filter=ImportadorDirecionadasMensagensTest`
Esperado: FAIL (classes inexistentes).

- [ ] **Passo 3: Criar a interface e o leitor Mysql**

`app/Importacao/LeitorDirecionadasMensagem.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-19

namespace App\Importacao;

interface LeitorDirecionadasMensagem
{
    /**
     * Uma entrada por mensagem direcionada, com os wp_users.ID dos destinatários.
     *
     * @return array<int, array{wp_id:int, destinatarios_wp_ids:array<int,int>}>
     */
    public function direcionadas(): array;
}
```

`app/Importacao/LeitorDirecionadasMensagemMysql.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-19

namespace App\Importacao;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

class LeitorDirecionadasMensagemMysql implements LeitorDirecionadasMensagem
{
    private ConnectionInterface $db;

    public function __construct()
    {
        $this->db = DB::connection('legado');
    }

    public function direcionadas(): array
    {
        // rel 38 = DIRECIONADA, direção REVERSA: parent = usuário, child = mensagem.
        // JOIN wp_users garante que o parent é usuário (descarta os IDs que coincidem com posts);
        // JOIN wp_posts (post_type) garante que o child é mensagem. Prefixo wp_ literal (select cru).
        $rows = $this->db->select(
            "SELECT r.child_object_id AS wp_id, r.parent_object_id AS wp_user_id
             FROM wp_jet_rel_default r
             JOIN wp_users u ON u.ID = r.parent_object_id
             JOIN wp_posts m ON m.ID = r.child_object_id AND m.post_type = 'mensagem-mediunicas'
             WHERE r.rel_id = '38'"
        );

        $porMensagem = [];
        foreach ($rows as $r) {
            $porMensagem[(int) $r->wp_id][] = (int) $r->wp_user_id;
        }

        $out = [];
        foreach ($porMensagem as $wpId => $userIds) {
            $out[] = ['wp_id' => $wpId, 'destinatarios_wp_ids' => array_values(array_unique($userIds))];
        }

        return $out;
    }
}
```

- [ ] **Passo 4: Criar o importador**

`app/Importacao/ImportadorDirecionadasMensagens.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-19

namespace App\Importacao;

use App\Models\Mensagem;
use App\Models\User;

class ImportadorDirecionadasMensagens
{
    public function __construct(private LeitorDirecionadasMensagem $leitor) {}

    /**
     * @return array{direcionadas:int, vinculos:int, destinatarios_distintos:int,
     *               mensagem_nao_encontrada:int, user_nao_encontrado:int, avisos:array<int,string>}
     */
    public function importar(callable $log): array
    {
        $direcionadas = 0;
        $vinculos = 0;
        $mensagemNaoEncontrada = 0;
        $userNaoEncontrado = 0;
        $distintos = [];
        $avisos = [];

        foreach ($this->leitor->direcionadas() as $item) {
            $mensagem = Mensagem::where('wp_id', $item['wp_id'])->first();
            if ($mensagem === null) {
                $mensagemNaoEncontrada++;
                $avisos[] = "Mensagem wp_id {$item['wp_id']} não encontrada (direcionada ignorada).";

                continue;
            }

            $ids = [];
            foreach ($item['destinatarios_wp_ids'] as $wpUserId) {
                $user = User::where('origem_legado_id', $wpUserId)->first();
                if ($user === null) {
                    $userNaoEncontrado++;
                    $avisos[] = "Destinatário wp_user_id {$wpUserId} sem User novo (vínculo omitido) — msg {$item['wp_id']}.";

                    continue;
                }
                $ids[] = $user->id;
                $distintos[$user->id] = true;
            }

            $mensagem->destinatarios()->sync($ids);
            $vinculos += count($ids);
            $direcionadas++;
            $log("Direcionada wp_id {$item['wp_id']}: ".count($ids).' destinatário(s).');
        }

        return [
            'direcionadas' => $direcionadas,
            'vinculos' => $vinculos,
            'destinatarios_distintos' => count($distintos),
            'mensagem_nao_encontrada' => $mensagemNaoEncontrada,
            'user_nao_encontrado' => $userNaoEncontrado,
            'avisos' => $avisos,
        ];
    }
}
```

- [ ] **Passo 5: Rodar o teste do importador e ver passar**

Run: `docker compose exec -T app php artisan test --filter=ImportadorDirecionadasMensagensTest`
Esperado: PASS.

- [ ] **Passo 6: Criar o command + o bind + o teste do command**

`app/Console/Commands/ImportarDirecionadasMensagens.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-19

namespace App\Console\Commands;

use App\Importacao\ImportadorDirecionadasMensagens;
use App\Importacao\LeitorDirecionadasMensagem;
use App\Importacao\LeitorDirecionadasMensagemMysql;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class ImportarDirecionadasMensagens extends Command
{
    protected $signature = 'cema:importar-direcionadas';

    protected $description = 'Importa os destinatários das mensagens direcionadas do legado (rel 38, SELECT-only, idempotente).';

    public function handle(LeitorDirecionadasMensagem $leitor, ImportadorDirecionadasMensagens $importador): int
    {
        // Só exige a conexão legado com o leitor real (o teste injeta fake).
        if ($leitor instanceof LeitorDirecionadasMensagemMysql) {
            try {
                DB::connection('legado')->getPdo();
            } catch (Throwable $e) {
                $this->error('Sem conexão com o legado (túnel SSH). '.$e->getMessage());

                return self::FAILURE;
            }
        }

        $resumo = $importador->importar(fn (string $m) => $this->line($m));

        $this->info("Direcionadas: {$resumo['direcionadas']} · vínculos: {$resumo['vinculos']} · destinatários distintos: {$resumo['destinatarios_distintos']}");
        $this->info("Mensagens não encontradas: {$resumo['mensagem_nao_encontrada']} · destinatários sem User: {$resumo['user_nao_encontrado']}");
        foreach ($resumo['avisos'] as $aviso) {
            $this->warn($aviso);
        }

        return self::SUCCESS;
    }
}
```

Em `app/Providers/AppServiceProvider.php`: adicionar os `use` de `LeitorDirecionadasMensagem`/`…Mysql` (junto aos demais leitores, ordem alfabética do Pint) e o bind após [:47](../../../app/Providers/AppServiceProvider.php#L47):

```php
        $this->app->bind(LeitorDirecionadasMensagem::class, LeitorDirecionadasMensagemMysql::class);
```

`tests/Feature/Importacao/ImportarDirecionadasCommandTest.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-19

namespace Tests\Feature\Importacao;

use App\Importacao\LeitorDirecionadasMensagem;
use App\Importacao\LeitorDirecionadasMensagemMysql;
use App\Models\Mensagem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportarDirecionadasCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_comando_roda_com_fake_e_popula(): void
    {
        Mensagem::factory()->create(['wp_id' => 100, 'slug' => 'm100', 'nivel' => 'direcionada']);
        User::factory()->create(['origem_legado_id' => 900]);

        $this->app->instance(LeitorDirecionadasMensagem::class, new class implements LeitorDirecionadasMensagem
        {
            public function direcionadas(): array
            {
                return [['wp_id' => 100, 'destinatarios_wp_ids' => [900]]];
            }
        });

        $this->artisan('cema:importar-direcionadas')->assertSuccessful();
        $this->assertSame(1, Mensagem::firstWhere('wp_id', 100)->destinatarios()->count());
    }

    public function test_bind_resolve_o_leitor_real_sem_tocar_o_legado(): void
    {
        // Guarda C7: sem fake, o container devolve o leitor Mysql (só resolve; não chama direcionadas()).
        $this->assertInstanceOf(
            LeitorDirecionadasMensagemMysql::class,
            $this->app->make(LeitorDirecionadasMensagem::class),
        );
    }
}
```

- [ ] **Passo 7: Rodar os testes do command e ver passar**

Run: `docker compose exec -T app php artisan test --filter="ImportarDirecionadasCommandTest|ImportadorDirecionadasMensagensTest"`
Esperado: PASS (se resolver código stale, `docker compose restart app worker` e repetir).

- [ ] **Passo 8: Pint + commit**

```bash
docker compose exec -T app ./vendor/bin/pint app/Importacao/LeitorDirecionadasMensagem.php app/Importacao/LeitorDirecionadasMensagemMysql.php app/Importacao/ImportadorDirecionadasMensagens.php app/Console/Commands/ImportarDirecionadasMensagens.php app/Providers/AppServiceProvider.php tests/Feature/Importacao/ImportadorDirecionadasMensagensTest.php tests/Feature/Importacao/ImportarDirecionadasCommandTest.php
git add app/Importacao/LeitorDirecionadasMensagem.php app/Importacao/LeitorDirecionadasMensagemMysql.php app/Importacao/ImportadorDirecionadasMensagens.php app/Console/Commands/ImportarDirecionadasMensagens.php app/Providers/AppServiceProvider.php tests/Feature/Importacao/ImportadorDirecionadasMensagensTest.php tests/Feature/Importacao/ImportarDirecionadasCommandTest.php
git commit -m "feat(camada-4-fatia-3a): cadeia cema:importar-direcionadas (rel 38 reversa) + bind + guarda C7"
```

> **CP-2 (fim da Task 6):** import verde. Rodar a **suíte completa** (`docker compose exec -T app php artisan test`) — deve estar **~1011 + novos**, verde, sem regressão.

---

### Task 7: Verificação end-to-end (R3 no legado vivo) + suíte + PR · CP-3

**Files:** nenhum de código; docs (SPEC + este plano) + PR.

- [ ] **Passo 1: Suíte completa + Pint**

```bash
docker compose exec -T app php artisan test
docker compose exec -T app ./vendor/bin/pint --test
```
Esperado: verde (**~1011 + novos**); **nenhuma** asserção existente muda de cor; Pint sem drift. (Ciência [[flaky-importadorblog-gd-cap-imagem]].)

- [ ] **Passo 2: R3 — rodar o leitor REAL contra o legado vivo (túnel ativo)**

```bash
docker compose exec -T app php artisan cema:importar-direcionadas
```
Esperado (medição da SPEC §4.3): **Direcionadas: 15 · vínculos: 73 · destinatários distintos: 17 · Mensagens não encontradas: 0 · destinatários sem User: 0**. Confirmar por consulta read-only:
```bash
docker compose exec -T app php artisan tinker --execute="echo \App\Models\Mensagem::has('destinatarios')->count().' msgs; '.\Illuminate\Support\Facades\DB::table('mensagem_destinatario')->count().' vinculos';"
```
Esperado: `15 msgs; 73 vinculos`. **É idempotente** — rodar 2x mantém 73. (O `legado` é `SELECT`-only; o import só escreve no pivô do dev.)

- [ ] **Passo 3: Commit dos docs (SPEC + plano)**

```bash
git add docs/superpowers/specs/2026-07-19-camada-4-fatia-3a-backend-visibilidade-mensagens.md docs/superpowers/plans/2026-07-19-camada-4-fatia-3a-backend-visibilidade-mensagens.md
git commit -m "docs(camada-4-fatia-3a): SPEC + plano do backend da visibilidade (aprovados no passe adversarial)"
```

- [ ] **Passo 4: Push + PR**

```bash
git push -u origin camada-4-fatia-3a-visibilidade
gh pr create --base main --title "Camada 4 · Fatia 3A — backend da visibilidade rica das Mensagens (neutro)" --body "$(cat <<'EOF'
## Resumo
Backend da visibilidade rica das Mensagens — **comportamento-neutro** (o front 2B segue `Mensagem::publica()`; o resolvedor nasce inerte, provado por unidade).

- Enum `VisibilidadeMensagem` (6 níveis reais) + accessor derivado `Mensagem::visibilidade()` (via `tryFrom`, fail-closed; `nivel` **não** castado → suíte 2A intacta).
- Resolvedor `podeSerVistoPor`/`scopeVisiveisPara` — escada (Público/Trabalhadores/Diretores) + 3 recortes de pertencimento (Médiuns/Diretor-DEPAE/Direcionada) + **bypass explícito** (admin/presidente).
- Pivô `mensagem_destinatario` (PII) + `Mensagem::destinatarios()`/`User::mensagensDirecionadas()`.
- Import `cema:importar-direcionadas` (legado rel 38 reversa) — **15 mensagens · 73 vínculos · 17 destinatários** (casados por `origem_legado_id`, 17/17).
- `MensagemPolicy::view`/`viewAny` (capacidade intacta).
- Constantes de slug de segurança (`Setor::SLUG_MEDIUM`, `Cargo::SLUG_DIRETOR_DEPAE`/`SLUG_PRESIDENTE`).

## Prova
Matriz de **8 personas × 6 níveis** (`MensagemVisibilidadeAcessoTest`) — `scopeVisiveisPara` não vaza título restrito; `nivel=null` fail-closed; presidente bypass sem `Gate::before`.

## Fora de escopo
Front rico (3B) · curadoria do médium (F4) · engajamento (F5).

🤖 Generated with [Claude Code](https://claude.com/claude-code)
EOF
)"
```

- [ ] **Passo 5: Conferir o CI verde no ÚLTIMO commit antes de mesclar** ([[merge-so-com-ci-verde-no-commit-final]]).

> **CP-3 (fim da Task 7):** suíte completa verde + R3 confirmado ao vivo (15·73·17·0) + PR aberto. O Consultor faz o passe do PR (CI verde no último commit + go do dono). **Cutover de prod** (o dono, no deploy): `migrate` (a tabela nova) → `cema:importar-direcionadas` (túnel ativo). A visibilidade rica segue **inerte** no site até a 3B.

---

## Checkpoints (para o controller do subagente-driven-development)

- **CP-1** (fim da Task 4): resolvedor verde — a **matriz de 8 personas × 6 níveis** (`MensagemVisibilidadeAcessoTest`) prova que `scopeVisiveisPara` não vaza. Rodar a suíte completa (neutralidade da 2A/2B).
- **CP-2** (fim da Task 6): import verde (`ImportadorDirecionadasMensagensTest`, `ImportarDirecionadasCommandTest` — inclui a guarda C7). Suíte completa.
- **CP-3** (fim da Task 7): suíte completa verde + Pint + **R3 ao vivo** (`cema:importar-direcionadas` = 15·73·17·0) + PR.

**Ordem TDD (SPEC §9.0):** enum (T1) → migration+relações (T2) → predicados User + accessor (T3) → resolvedor (T4) → policy (T5) → import+command+bind (T6) → E2E+R3 (T7). Cada task: vermelho → verde → Pint → commit; os CPs rodam a suíte completa.
