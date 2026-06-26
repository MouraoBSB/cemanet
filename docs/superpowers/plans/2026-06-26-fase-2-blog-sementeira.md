# Blog "Sementeira de Luz" — Fatia 1 (Implementation Plan)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Entregar a fatia vertical do blog Sementeira de Luz (banco → importação → admin Filament → front público variante B + SEO) com os 44 posts do WordPress legado migrados.

**Architecture:** Espelha o módulo Palestras. Importação modular (`LeitorBlog` interface + `LeitorBlogMysql` impl + `TransformadorBlog` estático + `ImportadorBlog` orquestrador + `BaixadorImagem` reutilizado). Models Eloquent com sanitização via mutator. Admin em `PostResource` (Filament 5 Schemas). Front Blade SSR + Livewire 4 (listagem reativa) + Alpine (interações). SEO por meta + JSON-LD + sitemap + 301.

**Tech Stack:** PHP 8.3 · Laravel 13 · Filament 5 · Livewire 4 · MySQL 8 · Blade SSR · Tailwind v4 · Docker. Comandos: `docker compose exec -T app php artisan …`. Build: `npm run build` (host).

## Global Constraints

- **Idioma:** tudo em pt-BR (identificadores de domínio, labels, mensagens, commits). Diacríticos corretos.
- **Cabeçalho de autoria** em toda classe PHP nova relevante: `// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-26`.
- **Legado é SOMENTE LEITURA** (conexão `legado`, SELECT apenas; túnel SSH). Nunca migrations/seeders/escritas nela. Imagens vêm por GET na URL pública.
- **Idempotência por slug** (`updateOrCreate`); também grava `wp_id`. Rodar 2× = mesmo estado.
- **Sem autor público:** o blog é assinado pela instituição (Centro Espírita Maria Madalena). `posts.criado_por_id` é só administrativo (null na importação); JSON-LD usa `Organization`.
- **URL:** canônica `/sementeira` e `/sementeira/{slug}`; **301** da raiz `/{slug}/` e de `/categoria/{slug}` (permalink legado é `/%postname%/`).
- **Conteúdo:** preservar HTML do Gutenberg; sanitizar com perfil `conteudo_blog` (remove comentários `<!-- wp:… -->` e classes `jet-sm-gb-*` por padrão do HTMLPurifier).
- **Escopo Fatia 1:** variante **B "Semente de Luz"** apenas. **Deferidos:** comentários, variante A + alternância mensal, vínculo post↔palestra (guardar `wp_id`), newsletter funcional (faixa só visual).
- **Front:** mobile-first, A11y (semântica + `aria-*`, foco visível, `prefers-reduced-motion`), `lazy-load` + `width/height` nas imagens, HTML enxuto. Cores de acento só em elementos grandes/ícones.
- **DB:** MySQL só por migrations/seeders; conferir o que já existe antes de criar. FKs sempre.
- **Cores por categoria** (design): Reflexões e Espiritualidade `#4E4483`, Estudando a Mediunidade `#6E9FCB`, Prática do Amor ao Próximo `#89AB98`, Datas Comemorativas `#F2A81E`, CEMA em Ação `#E79048`, Sem categoria `#7A8A8A`.
- **Referências de design:** `design_handoff_sementeira/` (prototype `Sementeira de Luz.dc.html`, screenshots, `design-system/`). Recriar em Blade/Tailwind (NÃO portar `support.js`). Padrões de código: módulo Palestras.

---

## File Structure

**Backend / dados**
- `database/migrations/*_create_categorias_table.php`, `*_create_tags_table.php`, `*_create_posts_table.php`, `*_create_categoria_post_table.php`, `*_create_post_tag_table.php`, `*_create_post_faqs_table.php`, `*_create_post_imagens_table.php`, `*_create_configuracoes_table.php`
- `database/seeders/CategoriaSeeder.php`
- `database/factories/{Post,Categoria,Tag}Factory.php`
- `app/Models/{Post,Categoria,Tag,PostFaq,PostImagem,Configuracao}.php`
- `config/purifier.php` (adicionar perfil `conteudo_blog`)

**Importação**
- `app/Importacao/TransformadorBlog.php` (estático)
- `app/Importacao/ReescritorImagensConteudo.php`
- `app/Importacao/BaixadorImagem.php` (adicionar `baixarPara()`)
- `app/Importacao/LeitorBlog.php` (interface) + `app/Importacao/LeitorBlogMysql.php`
- `app/Importacao/ImportadorBlog.php`
- `app/Console/Commands/ImportarBlog.php`
- `app/Providers/AppServiceProvider.php` (bind `LeitorBlog`, `FonteReflexao`)

**Admin**
- `app/Support/Blog/PlacarSeo.php`
- `app/Filament/Resources/Posts/PostResource.php` + `Pages/{ListPosts,CreatePost,EditPost}.php`
- `app/Filament/Pages/ConfiguracoesBlog.php` (Settings page) + view
- `resources/views/filament/seo-placar.blade.php`

**Front / rotas / SEO**
- `app/Support/Blog/FonteReflexao.php` (interface) + `app/Support/Blog/ReflexaoConfig.php`
- `app/Http/Controllers/BlogController.php`
- `app/Livewire/Blog/Lista.php` + `resources/views/livewire/blog/lista.blade.php`
- `resources/views/blog/index.blade.php`, `resources/views/blog/show.blade.php`
- `resources/views/components/blog/card.blade.php`
- `app/Http/Controllers/SitemapController.php`
- `routes/web.php` (rotas + 301), `config/navegacao.php` (Sementeira ativo)

---

## Task 1: Camada de dados (migrations + seeder + factories)

**Files:**
- Create: as 8 migrations acima, `CategoriaSeeder.php`, `PostFactory.php`, `CategoriaFactory.php`, `TagFactory.php`
- Test: `tests/Feature/Models/BlogSchemaTest.php`

**Interfaces — Produces:** tabelas `categorias`, `tags`, `posts`, `categoria_post`, `post_tag`, `post_faqs`, `post_imagens`, `configuracoes`; `CategoriaSeeder` cria 6 categorias.

- [ ] **Step 1: Migration `categorias`**

```php
Schema::create('categorias', function (Blueprint $table) {
    $table->id();
    $table->string('nome');
    $table->string('slug')->unique();
    $table->string('cor', 7)->nullable();
    $table->string('descricao')->nullable();
    $table->unsignedSmallInteger('ordem')->default(0);
    $table->unsignedBigInteger('wp_term_id')->nullable();
    $table->timestamps();
});
```

- [ ] **Step 2: Migration `tags`**

```php
Schema::create('tags', function (Blueprint $table) {
    $table->id();
    $table->string('nome');
    $table->string('slug')->unique();
    $table->unsignedBigInteger('wp_term_id')->nullable();
    $table->timestamps();
});
```

- [ ] **Step 3: Migration `posts`** (criar **depois** de `categorias` e `users`)

```php
Schema::create('posts', function (Blueprint $table) {
    $table->id();
    $table->string('titulo');
    $table->string('slug')->unique();
    $table->text('resumo')->nullable();
    $table->longText('conteudo')->nullable();
    $table->string('imagem_destacada')->nullable();
    $table->string('imagem_destacada_alt')->nullable();
    $table->foreignId('criado_por_id')->nullable()->constrained('users')->nullOnDelete();
    $table->foreignId('categoria_principal_id')->nullable()->constrained('categorias')->nullOnDelete();
    $table->boolean('destaque')->default(false);
    $table->unsignedSmallInteger('tempo_leitura_min')->default(0);
    $table->unsignedInteger('visualizacoes')->default(0);
    $table->dateTime('data_publicacao');
    $table->string('status')->default('publicado');
    $table->unsignedBigInteger('wp_id')->nullable()->unique();
    $table->string('seo_titulo')->nullable();
    $table->string('seo_descricao')->nullable();
    $table->string('seo_keyword')->nullable();
    $table->string('og_imagem')->nullable();
    $table->boolean('robots_noindex')->default(false);
    $table->string('canonical')->nullable();
    $table->timestamps();
    $table->index('status');
    $table->index('data_publicacao');
    $table->index('destaque');
});
```

- [ ] **Step 4: Migrations dos pivôs e filhos**

```php
// categoria_post
Schema::create('categoria_post', function (Blueprint $table) {
    $table->id();
    $table->foreignId('post_id')->constrained('posts')->cascadeOnDelete();
    $table->foreignId('categoria_id')->constrained('categorias')->cascadeOnDelete();
    $table->unique(['post_id', 'categoria_id']);
});
// post_tag
Schema::create('post_tag', function (Blueprint $table) {
    $table->id();
    $table->foreignId('post_id')->constrained('posts')->cascadeOnDelete();
    $table->foreignId('tag_id')->constrained('tags')->cascadeOnDelete();
    $table->unique(['post_id', 'tag_id']);
});
// post_faqs
Schema::create('post_faqs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('post_id')->constrained('posts')->cascadeOnDelete();
    $table->string('pergunta');
    $table->text('resposta');
    $table->unsignedSmallInteger('ordem')->default(0);
    $table->timestamps();
});
// post_imagens
Schema::create('post_imagens', function (Blueprint $table) {
    $table->id();
    $table->foreignId('post_id')->constrained('posts')->cascadeOnDelete();
    $table->string('caminho');
    $table->string('url_legado')->nullable();
    $table->string('alt')->nullable();
    $table->unsignedSmallInteger('ordem')->default(0);
    $table->timestamps();
});
// configuracoes (chave/valor — usado p/ "Reflexão do dia")
Schema::create('configuracoes', function (Blueprint $table) {
    $table->id();
    $table->string('chave')->unique();
    $table->text('valor')->nullable();
    $table->timestamps();
});
```

- [ ] **Step 5: `CategoriaSeeder`** (idempotente por slug)

```php
// database/seeders/CategoriaSeeder.php  — // Thiago Mourão — https://github.com/MouraoBSB — 2026-06-26
$cats = [
    ['nome' => 'Reflexões e Espiritualidade', 'slug' => 'reflexoes-e-espiritualidade', 'cor' => '#4E4483', 'ordem' => 1],
    ['nome' => 'Estudando a Mediunidade',     'slug' => 'estudando-a-mediunidade',     'cor' => '#6E9FCB', 'ordem' => 2],
    ['nome' => 'Prática do Amor ao Próximo',  'slug' => 'pratica-do-amor-ao-proximo',  'cor' => '#89AB98', 'ordem' => 3],
    ['nome' => 'Datas Comemorativas',         'slug' => 'datas-comemorativas',         'cor' => '#F2A81E', 'ordem' => 4],
    ['nome' => 'CEMA em Ação',                'slug' => 'cema-em-acao',                'cor' => '#E79048', 'ordem' => 5],
    ['nome' => 'Sem categoria',               'slug' => 'sem-categoria',               'cor' => '#7A8A8A', 'ordem' => 99],
];
foreach ($cats as $c) {
    \App\Models\Categoria::updateOrCreate(['slug' => $c['slug']], $c);
}
```

Registrar a chamada em `database/seeders/DatabaseSeeder.php` (`$this->call(CategoriaSeeder::class);`).

- [ ] **Step 6: Factories** (`PostFactory`, `CategoriaFactory`, `TagFactory`) — espelhar `PalestraFactory`. `PostFactory` define `titulo`, `slug` (único), `conteudo`, `data_publicacao` (`now()`), `status` (`publicado`), `tempo_leitura_min` (1). State `rascunho()` e `agendado()` (`data_publicacao` futura).

- [ ] **Step 7: Teste de schema/seeder**

```php
public function test_schema_e_seeder(): void {
    $this->seed(\Database\Seeders\CategoriaSeeder::class);
    $this->assertSame(6, \App\Models\Categoria::count());
    $this->assertDatabaseHas('categorias', ['slug' => 'cema-em-acao', 'cor' => '#E79048']);
    \App\Models\Post::factory()->create(['slug' => 'x']);
    $this->assertDatabaseHas('posts', ['slug' => 'x']);
}
```

- [ ] **Step 8:** Rodar `docker compose exec -T app php artisan test --filter=BlogSchemaTest` → PASS. Commit: `feat(blog): camada de dados (migrations, seeder, factories)`.

---

## Task 2: Models e relações + sanitização

**Files:**
- Create: `app/Models/{Post,Categoria,Tag,PostFaq,PostImagem,Configuracao}.php`
- Modify: `config/purifier.php` (perfil `conteudo_blog`)
- Test: `tests/Feature/Models/PostTest.php`, `tests/Feature/Models/SanitizacaoBlogTest.php`

**Interfaces — Produces:**
- `Post`: consts `STATUS_PUBLICADO='publicado'`, `STATUS_RASCUNHO='rascunho'`, `STATUS_AGENDADO='agendado'`; `scopePublicado(Builder): Builder` (status publicado **e** `data_publicacao <= now()`); `scopeMaisLidas(Builder): Builder`; relações `categorias()`, `categoriaPrincipal()`, `tags()`, `faqs()` (ordenada), `imagens()` (ordenada); accessor `getUrlPublicaAttribute(): string` (= `route('blog.show', $this->slug)`); accessor `getCorCategoriaAttribute(): string` (cor da principal ou `#7A8A8A`); mutator de `conteudo` (`clean($v,'conteudo_blog')`).
- `Categoria`: `posts()` (belongsToMany), `scopeComPostsPublicados(Builder): Builder`.
- `PostFaq`, `PostImagem`, `Tag`, `Configuracao` (com `static valor(string $chave, $default=null)` e `static definir(string $chave, $valor)`).

- [ ] **Step 1: Perfil purifier `conteudo_blog`** — em `config/purifier.php`, dentro de `settings`, adicionar (estende o `conteudo`, permitindo títulos, tabelas e iframes seguros):

```php
'conteudo_blog' => [
    'HTML.Allowed' => 'p,br,b,strong,i,em,u,s,h2,h3,h4,h5,ul,ol,li,blockquote,a[href|title|target|rel],img[src|alt|width|height],figure,figcaption,table,thead,tbody,tr,th,td,iframe[src|width|height|allowfullscreen|frameborder|allow]',
    'HTML.SafeIframe' => true,
    'URI.SafeIframeRegexp' => '%^(https?:)?//(www\.youtube\.com/embed/|player\.vimeo\.com/video/)%',
    'HTML.TargetBlank' => true,
    'AutoFormat.RemoveEmpty' => true,
    'URI.AllowedSchemes' => ['http' => true, 'https' => true, 'mailto' => true],
],
```

- [ ] **Step 2: Teste de sanitização (falha primeiro)**

```php
public function test_sanitiza_conteudo_remove_comentarios_e_classes(): void {
    $p = Post::factory()->create([
        'conteudo' => '<!-- wp:paragraph --><p class="jet-sm-gb-wrapper">Olá <script>alert(1)</script></p><!-- /wp:paragraph -->',
    ]);
    $this->assertStringNotContainsString('wp:paragraph', $p->conteudo);
    $this->assertStringNotContainsString('jet-sm-gb', $p->conteudo);
    $this->assertStringNotContainsString('<script>', $p->conteudo);
    $this->assertStringContainsString('Olá', $p->conteudo);
}
```

- [ ] **Step 3: Models** — `Post` espelha `Palestra` (consts, `$fillable` com todas as colunas de Task 1, `casts()` com `data_publicacao`=datetime, `destaque`/`robots_noindex`=boolean, inteiros). Mutator:

```php
protected function conteudo(): Attribute {
    return Attribute::make(set: fn (?string $v) => $v !== null ? clean($v, 'conteudo_blog') : null);
}
public function scopePublicado(Builder $q): Builder {
    return $q->where('status', self::STATUS_PUBLICADO)->where('data_publicacao', '<=', now());
}
public function scopeMaisLidas(Builder $q): Builder {
    return $q->publicado()->orderByDesc('visualizacoes');
}
public function categorias(): BelongsToMany { return $this->belongsToMany(Categoria::class, 'categoria_post'); }
public function categoriaPrincipal(): BelongsTo { return $this->belongsTo(Categoria::class, 'categoria_principal_id'); }
public function tags(): BelongsToMany { return $this->belongsToMany(Tag::class, 'post_tag'); }
public function faqs(): HasMany { return $this->hasMany(PostFaq::class)->orderBy('ordem'); }
public function imagens(): HasMany { return $this->hasMany(PostImagem::class)->orderBy('ordem'); }
public function getUrlPublicaAttribute(): string { return route('blog.show', $this->slug); }
public function getCorCategoriaAttribute(): string { return $this->categoriaPrincipal?->cor ?? '#7A8A8A'; }
```

`Configuracao`: `protected $fillable = ['chave','valor'];` + `static valor()` (cache simples via `firstWhere`) e `static definir()` (`updateOrCreate`).

- [ ] **Step 4: Teste de relações/escopo** — `PostTest`: `scopePublicado` exclui `rascunho` e `agendado` (data futura); `categorias()`/`tags()`/`faqs()`/`imagens()` retornam relacionados; `corCategoria` cai para `#7A8A8A` sem principal.

- [ ] **Step 5:** Rodar os 2 testes → PASS. Commit: `feat(blog): models, relações, scopes e sanitização`.

---

## Task 3: TransformadorBlog (helpers de transformação)

**Files:** Create `app/Importacao/TransformadorBlog.php`; Test `tests/Unit/Importacao/TransformadorBlogTest.php`.

**Interfaces — Produces (estáticos):**
- `faqsDoRepeater(?string $serial): array` → `[['pergunta'=>..,'resposta'=>..,'ordem'=>N], …]` (vazio se nulo/inválido).
- `galeriaDoRepeater(?string $serial): array` → `[['url'=>..,'wp_id'=>int,'ordem'=>N], …]`.
- `tempoLeitura(?string $html): int` → minutos (≥1; `ceil(palavras/200)`).
- `statusPost(string $wp): string` → `publish`→`publicado`, `future`→`agendado`, senão `rascunho`.

- [ ] **Step 1: Testes (falham primeiro)** com amostras reais da introspecção:

```php
public function test_faqs_do_repeater(): void {
    $s = serialize(['item-0' => ['_pergunta_faq' => 'P1', '_resposta_faq' => 'R1'], 'item-1' => ['_pergunta_faq' => 'P2', '_resposta_faq' => 'R2']]);
    $f = TransformadorBlog::faqsDoRepeater($s);
    $this->assertCount(2, $f);
    $this->assertSame(['pergunta' => 'P1', 'resposta' => 'R1', 'ordem' => 0], $f[0]);
    $this->assertSame([], TransformadorBlog::faqsDoRepeater(null));
    $this->assertSame([], TransformadorBlog::faqsDoRepeater('lixo'));
}
public function test_galeria_do_repeater(): void {
    $s = serialize([0 => ['id' => 10, 'url' => 'https://x/a.jpg'], 1 => ['id' => 11, 'url' => 'https://x/b.jpg']]);
    $g = TransformadorBlog::galeriaDoRepeater($s);
    $this->assertSame(['url' => 'https://x/a.jpg', 'wp_id' => 10, 'ordem' => 0], $g[0]);
}
public function test_tempo_leitura(): void {
    $this->assertSame(1, TransformadorBlog::tempoLeitura('<p>'.str_repeat('palavra ', 50).'</p>'));
    $this->assertSame(2, TransformadorBlog::tempoLeitura('<p>'.str_repeat('palavra ', 300).'</p>'));
}
public function test_status_post(): void {
    $this->assertSame('publicado', TransformadorBlog::statusPost('publish'));
    $this->assertSame('agendado', TransformadorBlog::statusPost('future'));
    $this->assertSame('rascunho', TransformadorBlog::statusPost('draft'));
}
```

- [ ] **Step 2: Implementar** `TransformadorBlog` (cabeçalho de autoria). `faqsDoRepeater`/`galeriaDoRepeater` usam `@unserialize` com guarda (`is_array`); ignoram itens sem chaves esperadas; `ordem` = índice incremental. `tempoLeitura`: `max(1, (int) ceil(str_word_count(strip_tags($html)) / 200))`. `statusPost`: `match`.

- [ ] **Step 3:** Rodar `--filter=TransformadorBlogTest` → PASS. Commit: `feat(blog): TransformadorBlog (faq, galeria, tempo de leitura, status)`.

---

## Task 4: Download e reescrita de imagens do conteúdo

**Files:** Modify `app/Importacao/BaixadorImagem.php`; Create `app/Importacao/ReescritorImagensConteudo.php`; Test `tests/Feature/Importacao/ReescritorImagensConteudoTest.php`.

**Interfaces:**
- *Consumes:* `BaixadorImagem`.
- *Produces:* `BaixadorImagem::baixarPara(?string $url, string $pasta, string $nome): ?string` (salva `{pasta}/{nome}.{ext}` no disco public; idempotente; retorna caminho ou null). `ReescritorImagensConteudo::reescrever(string $html, string $slugPost): string` (baixa cada `<img src>` de `wp-content/uploads` e troca o `src` por `Storage::url(...)`).

- [ ] **Step 1:** Em `BaixadorImagem`, **adicionar** `baixarPara()` (move a lógica atual, parametrizando pasta/nome) e fazer `baixar($url,$slug)` delegar para `baixarPara($url,'palestrantes',$slug)` — **sem alterar** o comportamento/teste de palestras.

```php
public function baixar(?string $url, string $slug): ?string {
    return $this->baixarPara($url, 'palestrantes', $slug);
}
public function baixarPara(?string $url, string $pasta, string $nome): ?string {
    if (empty($url)) return null;
    $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION)) ?: 'jpg';
    $caminho = "{$pasta}/{$nome}.{$ext}";
    $disco = Storage::disk('public');
    if ($disco->exists($caminho)) return $caminho;
    try {
        $r = Http::timeout(30)->get($url);
        if (! $r->successful()) return null;
        $disco->put($caminho, $r->body());
        return $caminho;
    } catch (\Throwable $e) { report($e); return null; }
}
```

- [ ] **Step 2: Teste do reescritor (falha primeiro)**

```php
public function test_reescreve_e_baixa_imagens_do_conteudo(): void {
    Storage::fake('public');
    Http::fake(['*' => Http::response('binario', 200)]);
    $html = '<p>x</p><img src="https://cemanet.org.br/wp-content/uploads/2025/01/foto.jpg" alt="a">';
    $out = app(\App\Importacao\ReescritorImagensConteudo::class)->reescrever($html, 'meu-post');
    $this->assertStringNotContainsString('cemanet.org.br/wp-content', $out);
    $this->assertStringContainsString('/storage/blog/conteudo/', $out);
    Storage::disk('public')->assertExists(\Illuminate\Support\Str::after(\Illuminate\Support\Str::before($out, '" alt'), '/storage/'));
}
```

- [ ] **Step 3: Implementar** `ReescritorImagensConteudo` (recebe `BaixadorImagem` no construtor). Regex `~<img[^>]+src=["\']([^"\']+wp-content/uploads/[^"\']+)["\']~i`; para cada URL, `baixarPara($url,'blog/conteudo', md5($url))`; se baixou, `str_replace($url, Storage::url($caminho), $html)`. Não falha o post se uma imagem der 404 (mantém a URL original e loga).

- [ ] **Step 4:** Rodar o teste + `--filter=BaixadorImagemTest` (garantir que palestras não quebrou) → PASS. Commit: `feat(blog): download/reescrita de imagens de conteúdo`.

---

## Task 5: LeitorBlog (interface + leitura do legado)

**Files:** Create `app/Importacao/LeitorBlog.php`, `app/Importacao/LeitorBlogMysql.php`; Modify `app/Providers/AppServiceProvider.php`.

**Interfaces — Produces:** `LeitorBlog::posts(): array`. Cada item:
```
['titulo','slug','resumo','conteudo'(raw),'data_publicacao'(Carbon),'status','wp_id'(int),
 'imagem_url'(?string),'imagem_alt'(?string),'categorias_slugs'(array),'categoria_principal_slug'(?string),
 'tags'(array de ['nome','slug']),'faqs'(array de Transformador),'galeria'(array de Transformador),
 'seo'=>['titulo'=>?,'descricao'=>?,'keyword'=>?,'og_imagem'=>?]]
```

- [ ] **Step 1:** Definir `interface LeitorBlog { public function posts(): array; }`.

- [ ] **Step 2:** Implementar `LeitorBlogMysql` espelhando `LeitorLegadoMysql` (construtor `DB::connection('legado')`; helper `metasDe(int): array`; `urlDaImagem(int $attId)` via `guid` de `wp_posts`). Query base: `post_type='post' AND post_status IN ('publish','draft','future')`. Mapear:
  - `data_publicacao` ← `Carbon::parse($p->post_date, 'America/Sao_Paulo')`.
  - `status` ← `TransformadorBlog::statusPost($p->post_status)`.
  - `imagem_url` ← `urlDaImagem((int)$meta['_thumbnail_id'])`; `imagem_alt` ← meta `_wp_attachment_image_alt` do attachment.
  - `categorias_slugs` ← termos da taxonomia `category` (mesmo SQL de `assuntosDaPalestra`, taxonomy `category`).
  - `categoria_principal_slug` ← se `rank_math_primary_category` (term_id) → slug; senão a 1ª categoria.
  - `tags` ← termos `post_tag` (`['nome'=>name,'slug'=>slug]`).
  - `faqs` ← `TransformadorBlog::faqsDoRepeater($meta['_faq'] ?? null)`.
  - `galeria` ← `TransformadorBlog::galeriaDoRepeater($meta['_fotos_carrossel_'] ?? null)`.
  - `seo.titulo` ← `rank_math_title` ?? `_yoast_wpseo_title`; `seo.descricao` ← `rank_math_description` ?? `_yoast_wpseo_metadesc`; `seo.keyword` ← `rank_math_focus_keyword` ?? `_yoast_wpseo_focuskw`; `seo.og_imagem` ← `rank_math_og_content_image`.

- [ ] **Step 3:** Em `AppServiceProvider::register()`, `$this->app->bind(LeitorBlog::class, LeitorBlogMysql::class);`.

- [ ] **Step 4:** Sem teste de DB real (depende do túnel). Validação acontece no Task 6 (leitor fake) e no Task 7 (smoke real). Commit: `feat(blog): LeitorBlog + leitura do legado`.

---

## Task 6: ImportadorBlog (orquestrador idempotente)

**Files:** Create `app/Importacao/ImportadorBlog.php`; Test `tests/Feature/Importacao/ImportadorBlogTest.php`.

**Interfaces:**
- *Consumes:* `LeitorBlog`, `BaixadorImagem`, `ReescritorImagensConteudo`.
- *Produces:* `importar(?callable $log=null): array` → `['posts'=>int,'avisos'=>array]`.

- [ ] **Step 1: Teste com leitor fake (falha primeiro)** — espelha `ImportadorPalestrasTest`: leitor anônimo `implements LeitorBlog` retornando 1 post com 1 categoria conhecida (`reflexoes-e-espiritualidade`), 1 categoria desconhecida (gera aviso), 2 tags, 1 FAQ, 1 imagem de galeria, SEO. `Storage::fake('public')`, `Http::fake`. Rodar `importar()` 2× e asserir:

```php
$this->seed(\Database\Seeders\CategoriaSeeder::class);
$imp = app(\App\Importacao\ImportadorBlog::class, ['leitor' => $this->leitorFake()]);
$imp->importar(); $resumo = $imp->importar();
$this->assertSame(1, Post::count());                       // idempotente
$post = Post::first();
$this->assertSame('publicado', $post->status);
$this->assertCount(1, $post->categorias);                  // só a conhecida vinculou
$this->assertSame('reflexoes-e-espiritualidade', $post->categoriaPrincipal->slug);
$this->assertCount(2, $post->tags);
$this->assertCount(1, $post->faqs);
$this->assertCount(1, $post->imagens);
$this->assertSame(123, $post->wp_id);
$this->assertNull($post->criado_por_id);                   // sem autor público
$this->assertNotEmpty($resumo['avisos']);                  // categoria desconhecida logada
```

- [ ] **Step 2: Implementar** `ImportadorBlog::importar()`. Para cada post (dentro de `DB::transaction`):
  - `conteudo` ← `ReescritorImagensConteudo::reescrever($d['conteudo'], $d['slug'])`.
  - `imagem` ← `baixarPara($d['imagem_url'], 'blog/destacada', $d['slug'])`.
  - `Post::updateOrCreate(['slug'=>$d['slug']], [...campos..., 'wp_id'=>$d['wp_id'], 'criado_por_id'=>null, 'tempo_leitura_min'=>TransformadorBlog::tempoLeitura($d['conteudo']), seo...])` (só sobrescreve `imagem_destacada` se baixou).
  - **Categorias:** resolver slugs em ids; slug sem categoria seeded → `$this->avisos[] = "[{slug}] categoria desconhecida: {x}"`. `sync` dos ids; `categoria_principal_id` = id do `categoria_principal_slug` (ou 1º).
  - **Tags:** `Tag::firstOrCreate(['slug'=>..],['nome'=>..])`; `sync`.
  - **FAQs:** `faqs()->delete()` + recriar de `$d['faqs']`.
  - **Galeria:** `imagens()->delete()`; para cada item, `baixarPara($url,'blog/galeria', $slug.'-'.$ordem)` → `imagens()->create(['caminho'=>..,'url_legado'=>$url,'ordem'=>$ordem])` (pula itens cujo download falhou, com aviso).

- [ ] **Step 3:** Rodar `--filter=ImportadorBlogTest` → PASS. Commit: `feat(blog): ImportadorBlog idempotente`.

---

## Task 7: Comando `cema:importar-blog`

**Files:** Create `app/Console/Commands/ImportarBlog.php`; Test `tests/Feature/Importacao/ImportarBlogCommandTest.php`.

- [ ] **Step 1:** Implementar espelhando `ImportarPalestras`: `signature='cema:importar-blog'`; valida `DB::connection('legado')->getPdo()` só quando `$leitor instanceof LeitorBlogMysql` (mensagem orientando o túnel SSH); chama `ImportadorBlog::importar(fn($m)=>$this->info($m))`; imprime resumo + avisos.

- [ ] **Step 2: Teste** — fazer bind de um `LeitorBlog` fake no container e asserir que `$this->artisan('cema:importar-blog')->assertSuccessful()` importa (sem tocar no legado).

- [ ] **Step 3:** Rodar o teste → PASS. Commit: `feat(blog): comando cema:importar-blog`.

---

## Task 8: PlacarSeo (pontuação de redação)

**Files:** Create `app/Support/Blog/PlacarSeo.php`; Test `tests/Unit/Blog/PlacarSeoTest.php`.

**Interfaces — Produces:** `PlacarSeo::analisar(?string $conteudo, ?string $titulo, ?string $keyword, ?string $descricao): array` → `['nota'=>int 0..100, 'itens'=>[['ok'=>bool,'rotulo'=>string], …]]`.

Sinais (peso igual; nota = aprovados/total × 100, arredondada):
1. Keyword definida. 2. Keyword no título. 3. Keyword no 1º parágrafo (primeiros 120 caracteres do texto sem tags). 4. Densidade da keyword entre 0,5% e 2,5%. 5. Conteúdo ≥ 300 palavras. 6. Há `<h2>`/`<h3>`. 7. Todas as `<img>` têm `alt`. 8. Meta description preenchida (50–160 caracteres). 9. Há link (`<a href`).

- [ ] **Step 1: Testes (falham primeiro)** — caso "tudo ok" → nota 100, todos `ok=true`; caso "vazio" → nota baixa, item "Keyword definida" `ok=false`; caso "img sem alt" → item de alt `ok=false`.

- [ ] **Step 2: Implementar** `PlacarSeo` (função pura, sem estado; cabeçalho de autoria). Usar `strip_tags`, `str_word_count`, `mb_stripos`, `preg_match_all` para imgs/alt e links.

- [ ] **Step 3:** Rodar `--filter=PlacarSeoTest` → PASS. Commit: `feat(blog): PlacarSeo (pontuação de redação)`.

---

## Task 9: Filament — PostResource + Configurações

**Files:** Create `app/Filament/Resources/Posts/PostResource.php` + `Pages/{ListPosts,CreatePost,EditPost}.php`; `app/Filament/Pages/ConfiguracoesBlog.php` + `resources/views/filament/pages/configuracoes-blog.blade.php`; `resources/views/filament/seo-placar.blade.php`; Test `tests/Feature/Filament/PostResourceTest.php`.

**Interfaces — Consumes:** `Post`, `Categoria`, `Tag`, `PlacarSeo`, `Configuracao`.

- [ ] **Step 1:** `PostResource` (Filament 5 Schemas, espelha `PalestraResource`). `form` com `Tabs`:
  - **Conteúdo:** `titulo` (live→`slug` no create), `slug` (unique ignoreRecord, table `posts`), `resumo` (Textarea), `RichEditor::make('conteudo')`.
  - **Mídia:** `FileUpload::make('imagem_destacada')->image()->disk('public')->directory('blog/destacada')`, `TextInput::make('imagem_destacada_alt')`, `Repeater::make('imagens')->relationship()` (`FileUpload caminho`, `TextInput alt`) `->orderColumn('ordem')`.
  - **Taxonomia/Publicação:** `Select::make('categorias')->relationship('categorias','nome')->multiple()->preload()`, `Select::make('categoria_principal_id')->relationship('categoriaPrincipal','nome')`, `Select::make('tags')->relationship('tags','nome')->multiple()->preload()->createOptionForm([...])`, `Toggle('destaque')`, `Select('status')` (3 opções; default rascunho), `DateTimePicker('data_publicacao')`.
  - **FAQ:** `Repeater::make('faqs')->relationship()` (`TextInput pergunta`, `Textarea resposta`) `->orderColumn('ordem')`.
  - **SEO:** `seo_titulo`, `seo_descricao` (Textarea, `->maxLength(160)->live()`), `seo_keyword` (`->live()`), `og_imagem` (FileUpload), `Toggle('robots_noindex')`, `TextInput('canonical')`, e o **placar**: `ViewField::make('placar')->view('filament.seo-placar')` (a view chama `PlacarSeo::analisar($get('conteudo'),$get('titulo'),$get('seo_keyword'),$get('seo_descricao'))` e renderiza nota + checklist; reativa por os campos serem `->live()`).
  - `table`: colunas `titulo`, `categoriaPrincipal.nome`, `status` (badge), `data_publicacao`, `visualizacoes`; filtro por `status` e por categoria; `defaultSort('data_publicacao','desc')`.

- [ ] **Step 2:** `ConfiguracoesBlog` (Filament custom Page) com formulário de 1 campo (`reflexao_do_dia`, Textarea) que lê/grava via `Configuracao::valor('blog.reflexao_do_dia')` / `Configuracao::definir(...)`.

- [ ] **Step 3: Teste** — espelha `PalestraResourceTest`: como usuário autenticado, `Livewire::test(ListPosts...)` carrega; criar um post via `CreatePost` persiste com categorias/FAQ; a página `ConfiguracoesBlog` grava a reflexão.

- [ ] **Step 4:** Rodar `--filter=PostResourceTest` → PASS. Commit: `feat(blog): admin Filament (PostResource + configurações + placar SEO)`.

---

## Task 10: Rotas, navegação e 301

**Files:** Modify `routes/web.php`, `config/navegacao.php`; Create `app/Http/Controllers/BlogController.php`; Test `tests/Feature/Front/BlogUrlCompatTest.php`.

- [ ] **Step 1:** `BlogController@index` (retorna `view('blog.index')`, que embute `<livewire:blog.lista/>`) e `@show(string $slug)` (carrega `Post::publicado()->where('slug',$slug)->firstOrFail()` com `with(['categorias','tags','faqs','imagens','categoriaPrincipal'])`, incrementa view (Task 12) e retorna `view('blog.show', compact('post'))`).

- [ ] **Step 2:** Em `routes/web.php`, **após** as rotas de palestrantes e **antes** do catch-all:

```php
Route::get('/sementeira', [BlogController::class, 'index'])->name('blog.index');
Route::get('/sementeira/{slug}', [BlogController::class, 'show'])->name('blog.show');
// 301 da base de categoria antiga → listagem filtrada
Route::get('/categoria/{slug}', fn (string $slug) => redirect()->to('/sementeira?categoria='.$slug, 301));
```

E, como **última** rota do arquivo (catch-all raiz, só para slugs de posts existentes):

```php
Route::get('/{slug}', function (string $slug) {
    abort_unless(\App\Models\Post::where('slug', $slug)->exists(), 404);
    return redirect()->route('blog.show', ['slug' => $slug], 301);
})->where('slug', '[a-z0-9-]+');
```

- [ ] **Step 3:** `config/navegacao.php` — trocar o item `'Sementeira'` para `['rotulo' => 'Sementeira', 'rota' => 'blog.index', 'ativo' => true, 'itens' => []]`.

- [ ] **Step 4: Teste** `BlogUrlCompatTest`: post publicado com slug `x`; `get('/x')` → 301 para `/sementeira/x`; `get('/categoria/reflexoes-e-espiritualidade')` → 301 para `/sementeira?...`; slug inexistente na raiz → 404; rota nomeada existente (`/palestrantes`) **não** é capturada pelo catch-all.

- [ ] **Step 5:** Rodar o teste → PASS. Commit: `feat(blog): rotas, navegação e 301`.

---

## Task 11: Front — listagem `/sementeira` (variante B)

**Files:** Create `app/Livewire/Blog/Lista.php`, `resources/views/livewire/blog/lista.blade.php`, `resources/views/blog/index.blade.php`, `resources/views/components/blog/card.blade.php`, `app/Support/Blog/FonteReflexao.php`, `app/Support/Blog/ReflexaoConfig.php`; Modify `app/Providers/AppServiceProvider.php`; Test `tests/Feature/Livewire/BlogListaTest.php`, `tests/Feature/Front/BlogListagemTest.php`.

**Design:** variante B "Semente de Luz" — ver `design_handoff_sementeira/` (screenshot `02-noticias-semente-de-luz.png` e prototype). Herói roxo com animações CSS (raios/halo/partículas — reaproveitar `<x-ui.particulas>` e estender o CSS em `resources/css/app.css`, respeitando `prefers-reduced-motion`), chips de categoria, grid principal + barra lateral (Mais lidas / Reflexão do dia / Categorias). **Reflexão do dia** vem de `FonteReflexao` (trocável; default lê `Configuracao::valor('blog.reflexao_do_dia')`).

- [ ] **Step 1:** `FonteReflexao` (interface, `doDia(): ?string`) + `ReflexaoConfig` (lê `Configuracao`); bind em `AppServiceProvider`.

- [ ] **Step 2:** `Blog\Lista` (Livewire) espelha `Palestras\Lista`: `#[Url(as:'categoria',except:'')] public string $categoria=''`, `#[Url(as:'q',except:'')] public string $q=''`, `#[Url(as:'ordenar',except:'recente')]`. `render()` consulta `Post::publicado()->with(['categoriaPrincipal'])->when(categoria, whereHas categorias slug)->when(q, like titulo/resumo)->orderBy(...)->paginate(9)`; passa `categorias` (com contagem de posts publicados), `maisLidas` (`Post::maisLidas()->take(5)->get()`), `reflexao` (`app(FonteReflexao::class)->doDia()`), `destaque` (`Post::publicado()->where('destaque',true)->latest('data_publicacao')->first() ?? Post::publicado()->latest('data_publicacao')->first()`).

- [ ] **Step 3:** Views — `blog/index.blade.php` = `<x-layout.app title="Sementeira de Luz">…<livewire:blog.lista/>…`; `livewire/blog/lista.blade.php` reproduz o layout da variante B (herói + chips reativos `wire:click="$set('categoria', …)"` + grid de `<x-blog.card>` + sidebar + "Carregar mais" via paginação + faixa newsletter **só visual**); `components/blog/card.blade.php` (imagem destacada `loading=lazy width height`, kicker da categoria com `style="color: {{ $post->corCategoria }}"`, título, dek, meta tempo de leitura). Fidelidade às cores/tipografia do `design-system/`.

- [ ] **Step 4: Testes** — `BlogListagemTest`: `/sementeira` → 200, mostra post publicado, **não** mostra rascunho/futuro; "Mais lidas" ordena por `visualizacoes`; a reflexão configurada aparece. `BlogListaTest` (Livewire): filtrar por `categoria` reduz a lista.

- [ ] **Step 5:** `npm run build`; rodar os testes → PASS; abrir `/sementeira` no localhost. Commit: `feat(blog): front da listagem (variante B)`.

---

## Task 12: Front — single `/sementeira/{slug}`

**Files:** Create `resources/views/blog/show.blade.php`; Modify `app/Http/Controllers/BlogController.php` (incremento de view); Test `tests/Feature/Front/BlogSingleTest.php`.

**Design:** single da variante B — ver screenshots `03/05/06`. Herói escuro, **barra de progresso de leitura**, **trilho de compartilhar** (WhatsApp/Facebook/copiar/curtir — Alpine + `localStorage`, espelhar a barra de ações de `palestras/show.blade.php`), corpo de leitura (parágrafo de abertura, H2, pull-quotes, **galeria→lightbox**, **acordeão de FAQ** com `<details>`/`<summary>`), tags, **relacionados "Continue semeando"** (mesma categoria principal), anterior/próxima. **Sem caixa de autor.**

- [ ] **Step 1:** No `BlogController@show`, incrementar visualização 1×/sessão:

```php
$chave = 'post_visto_'.$post->id;
if (! session()->has($chave)) { $post->increment('visualizacoes'); session()->put($chave, true); }
```

- [ ] **Step 2: Teste (falha primeiro)** `BlogSingleTest`:

```php
public function test_single_publicado_200_com_faq_e_galeria(): void {
    $cat = Categoria::factory()->create(['slug' => 'reflexoes-e-espiritualidade', 'cor' => '#4E4483']);
    $post = Post::factory()->create(['slug' => 'meu-post', 'status' => 'publicado']);
    $post->categorias()->attach($cat); $post->update(['categoria_principal_id' => $cat->id]);
    $post->faqs()->create(['pergunta' => 'P?', 'resposta' => 'R.', 'ordem' => 0]);
    $post->imagens()->create(['caminho' => 'blog/galeria/x.jpg', 'ordem' => 0]);
    $r = $this->get('/sementeira/meu-post');
    $r->assertOk()->assertSee('P?')->assertSee('lightbox', false);
    $this->assertSame(1, $post->fresh()->visualizacoes);
}
public function test_rascunho_e_futuro_dao_404(): void {
    Post::factory()->create(['slug' => 'r', 'status' => 'rascunho']);
    Post::factory()->create(['slug' => 'f', 'status' => 'publicado', 'data_publicacao' => now()->addDay()]);
    $this->get('/sementeira/r')->assertNotFound();
    $this->get('/sementeira/f')->assertNotFound();
}
```

- [ ] **Step 3: Implementar** `blog/show.blade.php`: barra de progresso (Alpine `scroll` passive, `prefers-reduced-motion` desliga); trilho de compartilhar (reaproveitar lógica de `palestras/show.blade.php`); `{!! $post->conteudo !!}` (já sanitizado); galeria de `$post->imagens` em grid 3-col com Alpine lightbox; FAQ de `$post->faqs` em `<details>`; relacionados via `Post::publicado()->whereHas('categorias', principal)->where('id','!=',$post->id)->take(3)`. **Sem byline/caixa de autor.**

- [ ] **Step 4:** `npm run build`; rodar `--filter=BlogSingleTest` → PASS; abrir no localhost (conferir lightbox, FAQ, progresso). Commit: `feat(blog): página single (variante B)`.

---

## Task 13: SEO — JSON-LD, OG/Twitter, canonical e sitemap

**Files:** Modify `resources/views/blog/show.blade.php` (slot `head`), `resources/views/livewire/blog/lista.blade.php` (paginação SEO), `routes/web.php`; Create `app/Http/Controllers/SitemapController.php`, `resources/views/sitemap.blade.php`; Test `tests/Feature/Front/BlogSeoTest.php`.

- [ ] **Step 1:** No `blog/show.blade.php`, usar `<x-slot:head>` para: `<title>`/description (default `{{ $post->seo_titulo ?? $post->titulo.' — Sementeira de Luz · CEMA' }}` / `{{ $post->seo_descricao ?? $post->resumo }}`), OG/Twitter (imagem = `og_imagem ?? imagem_destacada`), `<link rel="canonical" href="{{ $post->canonical ?? $post->urlPublica }}">`, `@if($post->robots_noindex)<meta name="robots" content="noindex">@endif`, e **JSON-LD** (espelhar o padrão de `palestras/show.blade.php` com `JSON_HEX_TAG`):
  - `Article` com `author` e `publisher` = `{"@type":"Organization","name":"Centro Espírita Maria Madalena"}`, `headline`, `datePublished`, `image`.
  - `FAQPage` quando `$post->faqs->isNotEmpty()` (cada `Question`/`acceptedAnswer`).
  - `BreadcrumbList` (Início → Sementeira → título).

- [ ] **Step 2:** Sitemap — `SitemapController@index` retorna `response()->view('sitemap', [...])->header('Content-Type','application/xml')` com posts publicados (`loc`=`urlPublica`, `lastmod`=`updated_at`) + categorias. Rota `Route::get('/sitemap.xml', [SitemapController::class,'index'])`.

- [ ] **Step 3: Teste** `BlogSeoTest`: single tem `application/ld+json`, `"@type":"Event"`→ aqui `"@type":"Article"`, `"@type":"FAQPage"` quando há FAQ, e `Organization`; `robots_noindex` injeta `noindex`; `/sitemap.xml` → 200 com o slug do post; canonical presente.

- [ ] **Step 4:** Rodar `--filter=BlogSeoTest` → PASS. Commit: `feat(blog): SEO (JSON-LD, OG, canonical, sitemap)`.

---

## Verificação final (após todas as tasks)

- [ ] `docker compose exec -T app php artisan test` — suíte inteira verde (blog + palestras intactas).
- [ ] Com o túnel SSH ativo: `docker compose exec -T app php artisan db:seed --class=CategoriaSeeder` e `… cema:importar-blog` — confere **44 posts** importados (conteúdo limpo, imagem destacada, FAQ/galeria onde houver), idempotente rodando 2×.
- [ ] Abrir `/sementeira` e 3–4 singles no localhost: variante B fiel, responsivo (mobile/tablet/desktop), lightbox/FAQ/progresso funcionando, HTML enxuto, 301 da raiz e de `/categoria` OK.

## Riscos / pontos de atenção

- **Catch-all raiz** deve ser a **última** rota e casar só slugs de posts existentes (senão captura/quebra outras URLs).
- **Sanitização** não pode comer conteúdo legítimo; o perfil `conteudo_blog` preserva títulos/tabelas/iframes seguros. Vídeos `oembed` raros podem virar link — aceitável nesta fatia.
- **Galerias grandes** (até 34 imagens): `lazy` obrigatório; downloads idempotentes.
- **Fuso de `post_date`**: parse em `America/Sao_Paulo` (alinhado às palestras).
- **Túnel SSH**: importação depende dele; o comando detecta e orienta.
