# Blog — Spatie Media Library + Imagens alinhadas (Implementation Plan)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Migrar o storage de imagens do blog (destacada, galeria **e imagens do corpo**) para **Spatie Media Library** (WebP + responsive/srcset + EXIF removido + otimização), com UX de admin (preview, upload múltiplo, reordenar); e **integrar** a feature de **alinhamento/redimensionamento de imagem** no editor — tudo num **único reimport** dos 45 posts.

**Architecture:** `Post implements HasMedia, HasRichContent`. Coleções `destacada`(singleFile)/`galeria`(multiple, ordenada)/`og` + conversões `web`(Fit::Max 1920, WebP, srcset)/`thumb`/`og`(1200×630). Imagens do **corpo** vão para a ML via o provider OFICIAL `SpatieMediaLibraryFileAttachmentProvider` (registrado em `setUpRichContent()`); o framework resolve "post ainda não existe" adiando os anexos para após o INSERT. O front mantém `{!! $post->conteudo !!}` + nosso purifier (liberando `data-id` p/ o cleanup do provider e as classes de alinhamento). O importador troca string por `addMediaFromUrl()` e converte colunas Gutenberg; **1 reimport**. A extensão TipTap de alinhamento (atributos `align`/`size` → classes WP) entra por cima.

**Tech Stack:** PHP 8.3 (GD/exif ok) · Laravel 13 · Filament 5.6.7 · spatie/laravel-medialibrary v11 + filament/spatie-laravel-media-library-plugin ^5 · spatie/image-optimizer (binários no Docker) · Tailwind v4 · Docker (php:8.3-cli, Debian trixie). Testes: `docker compose exec -T app php artisan test`. Build: `npm run build` (host).

## Global Constraints

- **NÃO guardar o original cru — capar na ENTRADA (POLÍTICA DECIDIDA):** a Spatie ML guarda o arquivo enviado em tamanho cheio por padrão e os otimizadores **não reduzem dimensão** → disco enche de arquivos grandes (o que o cliente quer evitar). O **original armazenado deve já vir capado** a **≤ ~2000px** (og ≤ ~1200px) nos **3 caminhos de entrada**: (a) admin via `imageResize*` do FileUpload; (b) importador via resize antes do `addMediaFromString` (nunca `addMediaFromUrl` direto p/ destacada/galeria/og); (c) corpo via provider — confirmar/forçar capado. Acervo em alta-resolução é responsabilidade do cliente, fora do sistema.
- **Imagens otimizadas:** WebP, máx 1920 (web)/1200×630 (og), srcset, EXIF removido (`--strip-all`), conversões em **fila** (worker) — `nonQueued()` nas que o front precisa imediatas.
- **Render inalterado:** single segue `{!! $post->conteudo !!}` + mutator `clean($v,'conteudo_blog')`. Purifier libera **`data-id`** no `<img>` (p/ o cleanup do provider) e as **classes** de alinhamento/tamanho (allow-list fechada, sem `style` inline).
- **Coleção exclusiva do corpo** (`conteudo`) — não misturar com destacada/galeria (o provider faz cleanup de órfãos nessa coleção).
- **Idempotência:** `clearMediaCollection()` antes de re-adicionar; `addMediaFromUrl` em `try/catch` (lança exceção). 1 reimport.
- **Legado SOMENTE LEITURA.** Migrations novas (nunca editar as já aplicadas). Cabeçalho de autoria nas classes novas. pt-BR. NUNCA `migrate:fresh`/`db:wipe` na default.
- **Saída de alinhamento por CLASSES** (`alignleft/alignright/aligncenter/alignnone`, `size-*`/`is-resized`), nunca px fixo. CSS em `.conteudo-artigo`.

---

## File Structure

- `composer.json` (+2 pacotes), `Dockerfile` (binários), `docker-compose.yml` (serviço worker), `config/media-library.php` (otimizador/fila), `config/purifier.php` (data-id + classes).
- `database/migrations/*_create_media_table.php` (lib), `*_drop_post_imagens_table.php`, `*_remove_imagem_colunas_from_posts.php`.
- `app/Models/Post.php` (HasMedia + HasRichContent + coleções/conversões + provider), `app/Models/PostImagem.php` (deletar).
- `app/Filament/Resources/Posts/PostResource.php` (3 campos → `SpatieMediaLibraryFileUpload`).
- `resources/views/blog/show.blade.php`, `components/blog/card.blade.php`, `livewire/blog/lista.blade.php` (URLs ML).
- `database/factories/PostFactory.php` (states de mídia).
- `app/Importacao/{ImportadorBlog,TransformadorBlog,ReescritorImagensConteudo}.php` (ML + colunas).
- Extensão de alinhamento: `app/Filament/RichContent/{Plugins/ImagemPlugin,TipTap/ImagemExtension}.php`, `resources/js/filament/imagem-alinhada.js`, `app/Providers/Filament/AdminPanelProvider.php`.
- CSS: `resources/css/conteudo.css` + import em `resources/css/app.css`.

> **Plano irmão (já escrito):** `docs/superpowers/plans/2026-06-26-blog-imagens-alinhamento.md` contém o código concreto da extensão TipTap (Tasks 1/4), do CSS (`.conteudo-artigo`) e da conversão de colunas. As Tasks 9–12 abaixo o **executam/sequenciam** sobre o schema ML.

---

## Task 1: Infra — pacotes, Docker, fila, tabela `media`

**Files:** Modify `composer.json` (via require), `Dockerfile`, `docker-compose.yml`, `config/media-library.php`, `.env`/`.env.example`; Test `tests/Feature/MediaLibrary/InfraTest.php`.

- [ ] **Step 1: Confirmar GD** — `docker compose exec -T app php -m | grep -i gd` (esperado: `gd`, `exif`). Se faltar, parar e instalar no Dockerfile.
- [ ] **Step 2: Pacotes** — `docker compose exec -T app composer require spatie/laravel-medialibrary "filament/spatie-laravel-media-library-plugin:^5" -W`.
- [ ] **Step 3: Migration `media` + config** — `php artisan vendor:publish --provider="Spatie\MediaLibrary\MediaLibraryServiceProvider" --tag="medialibrary-migrations"` e `--tag="medialibrary-config"`; `php artisan migrate`.
- [ ] **Step 4: Dockerfile** — acrescentar à lista `apt-get install` existente: `jpegoptim optipng pngquant gifsicle webp`. Rebuild: `docker compose build app && docker compose up -d`. Validar binários: `docker compose exec -T app sh -c 'jpegoptim --version; cwebp -version'`.
- [ ] **Step 5: Fila** — definir `QUEUE_CONNECTION=database` no `.env`/`.env.example`; em `config/media-library.php`: optimizers com `Jpegoptim => ['-m80','--strip-all','--all-progressive']`, `Webp => ['-q','80','-m','6','-mt']`, etc.; `queue_conversions_by_default => env('QUEUE_CONNECTION') !== 'sync'`. Adicionar serviço `worker` ao `docker-compose.yml` (mesma imagem, `command: php artisan queue:work --sleep=1 --tries=3 --max-time=3600`, `depends_on: db`). `docker compose up -d worker`.
- [ ] **Step 6: Teste de infra** — `tests/Feature/MediaLibrary/InfraTest.php`: a tabela `media` existe (`Schema::hasTable('media')`); um model `HasMedia` de teste consegue `addMediaFromString('x')->toMediaCollection()` (Storage::fake). 
- [ ] **Step 7:** Rodar → PASS. Commit `chore(blog): instala Spatie Media Library + binários do optimizer + worker de fila`.

---

## Task 2: `Post` como `HasMedia` — coleções e conversões

**Files:** Modify `app/Models/Post.php`; Test `tests/Feature/Models/PostMediaTest.php`.

**Interfaces — Produces:** consts `COLECAO_DESTACADA='destacada'`, `COLECAO_GALERIA='galeria'`, `COLECAO_OG='og'`, `COLECAO_CONTEUDO='conteudo'`; `registerMediaCollections()`/`registerMediaConversions()`; accessor `getImagemDestacadaUrlAttribute(): ?string` (= `getFirstMediaUrl('destacada','web') ?: null`).

- [ ] **Step 1: Teste (falha primeiro)** — anexar mídia e ler URL:

```php
public function test_coleções_e_url_da_destacada(): void
{
    \Illuminate\Support\Facades\Storage::fake('public');
    $post = Post::factory()->create();
    $post->addMediaFromString('bytes')->usingFileName('a.jpg')->toMediaCollection(Post::COLECAO_DESTACADA);
    $this->assertNotEmpty($post->getFirstMediaUrl(Post::COLECAO_DESTACADA));
    $post->addMediaFromString('b')->usingFileName('b.jpg')->toMediaCollection(Post::COLECAO_DESTACADA);
    $this->assertCount(1, $post->getMedia(Post::COLECAO_DESTACADA)); // singleFile substitui
}
```

- [ ] **Step 2: Implementar** — `Post implements HasMedia`, `use InteractsWithMedia`; coleções/conversões conforme a pesquisa (frente spatie-filament): `destacada` (`singleFile()`, conversões web+thumb+og), `galeria` (web+thumb), `og` (1200×630 jpg). `web`: `->fit(Fit::Max,1920,1920)->format('webp')->quality(82)->withResponsiveImages()->nonQueued()`; `og`: `->fit(Fit::Crop,1200,630)->format('jpg')->quality(85)->nonQueued()`; `thumb`: `->fit(Fit::Crop,400,300)->format('webp')->queued()`. (web/og `nonQueued` p/ o front ter imagem imediata após publicar.)
- [ ] **Step 3:** Rodar `--filter=PostMediaTest` → PASS. Commit `feat(blog): Post como HasMedia (coleções destacada/galeria/og + conversões WebP/srcset)`.

---

## Task 3: Schema — remover colunas/tabela antigas

**Files:** Create 2 migrations; Modify `app/Models/Post.php` (`$fillable`, remover relação `imagens()`); Delete `app/Models/PostImagem.php`; Test `tests/Feature/Models/PostMediaTest.php` (ajuste).

- [ ] **Step 1: Migrations** — `*_drop_post_imagens_table.php` (`Schema::dropIfExists('post_imagens')`; `down()` recria conforme a migration original) e `*_remove_imagem_colunas_from_posts.php` (`$table->dropColumn(['imagem_destacada','og_imagem'])`; manter `imagem_destacada_alt`). `php artisan migrate`.
- [ ] **Step 2: Model** — remover `imagem_destacada`/`og_imagem` de `$fillable`; **remover** `imagens(): HasMany`; **deletar** `app/Models/PostImagem.php`. Manter `imagem_destacada_alt`.
- [ ] **Step 3: Teste** — `assertFalse(Schema::hasColumn('posts','imagem_destacada'))`, `assertFalse(Schema::hasTable('post_imagens'))`. Rodar → PASS. Commit `feat(blog): remove colunas/tabela de imagem (migrado p/ Media Library)`.

---

## Task 4: Imagens do corpo na ML — provider oficial (+ cap do original)

**Files:** Modify `app/Models/Post.php` (HasRichContent + setUpRichContent), `app/Filament/Resources/Posts/PostResource.php` (RichEditor); Create `app/Listeners/CaparOriginalDaMidia.php` + registrar o listener; Test `tests/Feature/Filament/RichEditorAnexoMlTest.php`, `tests/Feature/MediaLibrary/CapOriginalTest.php`.

**Interfaces — Consumes:** coleção `conteudo` (Task 2 — adicionar). Produces: o RichEditor anexa imagens do corpo na coleção ML `conteudo`; **listener** `CaparOriginalDaMidia` que, em `Spatie\MediaLibrary\MediaCollections\Events\MediaHasBeenAddedEvent`, redimensiona o **original** no disco para ≤ teto da coleção (`og`→1200, demais→2000) quando a largura exceder — garante a política de cap mesmo no caminho do corpo (o RichEditor não tem API de resize por-anexo como o `FileUpload`).

- [ ] **Step 1:** Em `Post`, `implements HasRichContent` + `use InteractsWithRichContent`; adicionar coleção `conteudo` (web+thumb) no `registerMediaCollections()`; e:

```php
public function setUpRichContent(): void
{
    $this->registerRichContent('conteudo')
        ->fileAttachmentProvider(
            \Filament\Forms\Components\RichEditor\FileAttachmentProviders\SpatieMediaLibraryFileAttachmentProvider::make()
                ->collection(self::COLECAO_CONTEUDO),
        );
}
```

- [ ] **Step 2:** No `PostResource`, `RichEditor::make('conteudo')->preventFileAttachmentPathTampering()`.
- [ ] **Step 3: Listener de cap do original (falha primeiro)** — `tests/Feature/MediaLibrary/CapOriginalTest.php`: anexar à coleção `conteudo` uma imagem **3000×3000** (`UploadedFile::fake()->image('g.jpg',3000,3000)->get()` via `addMediaFromString`/`addMedia`) e asserir que o **original** salvo tem largura **≤ 2000** (ler dimensão do arquivo em `$media->getPath()` com `getimagesize`); e que na coleção `og` o teto é **≤ 1200**. Rodar → FAIL.
- [ ] **Step 4: Implementar o listener** — `app/Listeners/CaparOriginalDaMidia.php` (cabeçalho de autoria) escutando `MediaHasBeenAddedEvent`: para o `$media` recém-adicionado, teto = `$media->collection_name === Post::COLECAO_OG ? 1200 : 2000`; se `getimagesize($media->getPath())[0] > $teto`, redimensionar **in-place** com `spatie/image` (`Image::load($path)->width($teto)->save()`) preservando proporção, e atualizar `size` do registro. Registrar o listener (no `AppServiceProvider::boot()` via `Event::listen(...)` ou `EventServiceProvider`). Rodar `--filter=CapOriginalTest` → PASS.
- [ ] **Step 5: Teste do anexo** — exercitar o save do RichEditor com um anexo temporário (espelhar a doc do provider) e asserir que uma media entra na coleção `conteudo` do post após o save. (Se o teste do provider for difícil de montar headless, validar no Step de verificação manual e cobrir o caminho de import na Task 9/10.)
- [ ] **Step 6:** Commit `feat(blog): imagens do corpo via Media Library + cap do original (≤2000/≤1200px)`.

---

## Task 5: Purifier — `data-id` + classes de alinhamento

**Files:** Modify `config/purifier.php`; Test `tests/Feature/Models/SanitizacaoBlogTest.php`.

- [ ] **Step 1: Teste (falha primeiro)** — `data-id` no `<img>` sobrevive (cleanup do provider depende dele); classes da allow-list sobrevivem; classe fora dela e `style` somem:

```php
public function test_sanitiza_mantem_data_id_e_classes_de_imagem(): void
{
    $p = Post::factory()->create([
        'conteudo' => '<figure class="wp-block-image size-large alignleft x">'
            .'<img src="/s/1/a.webp" data-id="42" alt="" class="alignright evil" style="width:9px"></figure>',
    ]);
    $this->assertStringContainsString('data-id="42"', $p->conteudo);
    foreach (['wp-block-image','size-large','alignleft','alignright'] as $c) $this->assertStringContainsString($c, $p->conteudo);
    $this->assertStringNotContainsString('evil', $p->conteudo);
    $this->assertStringNotContainsString('style=', $p->conteudo);
}
```

- [ ] **Step 2: Implementar** — no perfil `conteudo_blog`: `HTML.Allowed` com `img[src|alt|width|height|class|data-id]`, `figure[class]`, `div[class]`; `Attr.AllowedClasses` (allow-list — ver plano irmão Task 2: alignleft/right/center/none, has-text-align-*, size-thumbnail/medium/large/full, is-resized, wp-block-image, wp-block-media-text, colunas, coluna). Para `data-id` ser aceito (HTMLPurifier 4.01 não conhece `data-*` por padrão), adicionar no bloco `custom_definition.attributes` existente: `['img','data-id','Text']`.
- [ ] **Step 3:** Rodar `--filter=SanitizacaoBlogTest` → PASS. Commit `feat(blog): purifier libera data-id e classes de imagem`.

---

## Task 6: Filament — `SpatieMediaLibraryFileUpload`

**Files:** Modify `app/Filament/Resources/Posts/PostResource.php`, revisar `Pages/{CreatePost,EditPost}.php`; Test `tests/Feature/Filament/PostResourceTest.php`.

- [ ] **Step 1:** Trocar no form (com **cap na entrada** `imageResizeMode('contain')` + alvo ≤2000px, og ≤1200 — o `FileUpload` redimensiona **antes** de armazenar, então o original na ML já entra capado):
  - `FileUpload imagem_destacada` → `SpatieMediaLibraryFileUpload::make('destacada')->collection(Post::COLECAO_DESTACADA)->image()->imageEditor()->imageResizeMode('contain')->imageResizeTargetWidth(2000)->imageResizeTargetHeight(2000)->conversion('thumb')->responsiveImages()`.
  - `FileUpload og_imagem` → idem coleção `og`, com `->imageResizeTargetWidth(1200)->imageResizeTargetHeight(1200)`.
  - `Repeater('imagens')` → `SpatieMediaLibraryFileUpload::make('galeria')->collection(Post::COLECAO_GALERIA)->image()->multiple()->reorderable()->appendFiles()->maxFiles(50)->imageResizeMode('contain')->imageResizeTargetWidth(2000)->imageResizeTargetHeight(2000)->conversion('thumb')->responsiveImages()`. (`maxFiles(50)`: galerias migradas têm até 34 fotos — 20 travaria a edição das maiores.)
  - Manter `imagem_destacada_alt` (TextInput).
- [ ] **Step 2:** Revisar `CreatePost`/`EditPost` — remover hooks que setavam as strings (o componente cuida do save).
- [ ] **Step 3: Teste** — `PostResourceTest`: criar post via `CreatePost` continua persistindo (categorias/FAQ/tags); adicionar um caso que sobe uma imagem para `destacada` e confirma a media na coleção. Rodar `--filter=PostResourceTest` → PASS. Commit `feat(blog): admin usa SpatieMediaLibraryFileUpload (preview, múltiplo, reordenar)`.

---

## Task 7: Front — renderizar via Media Library

**Files:** Modify `resources/views/blog/show.blade.php`, `components/blog/card.blade.php`, `livewire/blog/lista.blade.php`; Test `tests/Feature/Front/BlogMediaRenderTest.php`.

- [ ] **Step 1:** Trocar todos os `$post->imagem_destacada`/`asset('storage/'...)` por `$post->getFirstMediaUrl('destacada','web')` (com guarda `@if($post->getFirstMedia('destacada'))`); usar `{{ $post->getFirstMedia('destacada')('web') }}` onde quiser srcset (hero/destaque). Galeria do single: `$post->getMedia('galeria')` iterando `->getUrl('web')`/`->getUrl('thumb')` + `->getCustomProperty('alt')`. OG (Task 13 do blog): `getFirstMediaUrl('destacada','og')` com fallback. Card/lista: `getFirstMediaUrl('destacada','web')` + `imagem_destacada_alt`. Hero do single (background) e imagem de abertura também passam a usar a URL da ML.
- [ ] **Step 2: Teste** — `BlogMediaRenderTest`: post com media `destacada` anexada (via factory state) → `/sementeira/{slug}` e `/sementeira` veem a URL da media (assertSee da URL real, ex.: contém `/storage/` + o nome do arquivo de conversão), e single sem media não quebra.
- [ ] **Step 3:** `npm run build`; rodar → PASS. Commit `feat(blog): front renderiza imagens via Media Library (web/og/srcset)`.

---

## Task 8: Factory + corrigir testes que quebram

**Files:** Modify `database/factories/PostFactory.php`, `tests/Feature/Models/PostTest.php`, `tests/Feature/Front/{BlogSingleTest,BlogListagemTest,BlogSeoTest}.php`.

- [ ] **Step 1:** `PostFactory` — states `comImagemDestacada()` e `comGaleria(int $n=2)` via `afterCreating(fn(Post $p)=>$p->addMediaFromString(...)->toMediaCollection(...))` (Storage::fake nos testes que usarem).
- [ ] **Step 2:** Reescrever os asserts que usavam `'imagem_destacada'=>'blog/destacada/...'` + `assertSee('storage/blog/destacada/...')` para anexar media (state) + assertar pela presença de `<img`/URL da media. `PostTest::test_relacao_imagens_*` → usar `getMedia('galeria')` ordenada. `BlogSeoTest` (post sem imagem) → post sem media na coleção. Ver lista exata na pesquisa (frente schema-importador §3).
- [ ] **Step 3:** `docker compose exec -T app php artisan test` → suíte inteira verde (todos os testes pré-existentes ajustados). Commit `test(blog): factory de mídia + ajuste dos testes p/ Media Library`.

---

## Task 9: TransformadorBlog — `limparGutenberg` (colunas + preservar tamanhos)

Executar a **Task 3 do plano irmão** `docs/superpowers/plans/2026-06-26-blog-imagens-alinhamento.md` (método `TransformadorBlog::limparGutenberg()`: remove comentários `wp:`, converte `wp-block-columns/column`(+flex-basis) → `.colunas/.coluna`, preserva `wp-block-image`/`size-*`/`aligncenter`) **+ os testes** lá descritos. (Concreto e auto-contido lá.) Commit `feat(blog): TransformadorBlog converte colunas e preserva tamanhos`.

---

## Task 10: Importador — `addMediaFromUrl` (destacada/galeria/og + corpo) + colunas

**Files:** Modify `app/Importacao/ImportadorBlog.php`, `app/Importacao/ReescritorImagensConteudo.php`; Test `tests/Feature/Importacao/ImportadorBlogTest.php`.

- [ ] **Step 1: Teste (ajustar/falha)** — leitor fake com destacada+galeria+og+1 imagem no corpo; `Storage::fake('public')`, `Http::fake` devolvendo **bytes de imagem válidos e GRANDES** (`UploadedFile::fake()->image('x.jpg',3000,2000)->get()` — a ML valida mime e queremos exercitar o cap). Rodar `importar()` 2× e asserir: `assertCount(1,$post->getMedia('destacada'))`, `getMedia('galeria')` com N na ordem, media na coleção `og`, media na coleção `conteudo` (corpo), **idempotente**; **e** que o **original** da destacada tem largura **≤ 2000** e o da `og` **≤ 1200** (via `getimagesize($media->getPath())`).
- [ ] **Step 2: Implementar** — em `ImportadorBlog`, por post (na transação). **Cap na entrada:** baixar os bytes e **redimensionar p/ o teto ANTES** de gravar na ML (nunca `addMediaFromUrl` direto — ele guardaria o arquivo cheio). Helper privado:

```php
// teto: 2000 (destacada/galeria/conteudo) ou 1200 (og). Retorna bytes capados ou null (404).
private function baixarCapado(string $url, int $teto): ?string
{
    $bytes = $this->baixador->baixar($url);      // bytes crus; null em falha
    if ($bytes === null) { return null; }
    $tmp = tempnam(sys_get_temp_dir(), 'cema_mid');
    file_put_contents($tmp, $bytes);
    $dim = @getimagesize($tmp);
    if ($dim && $dim[0] > $teto) {
        \Spatie\Image\Image::load($tmp)->width($teto)->save(); // preserva proporção
    }
    $capado = file_get_contents($tmp);
    @unlink($tmp);
    return $capado;
}
```

  - Destacada/OG/Galeria: `clearMediaCollection(...)`; para cada imagem, `$bytes = $this->baixarCapado($url, $teto)` (em `null` → `$this->avisos[]` e segue); `$post->addMediaFromString($bytes)->usingFileName(basename($url))->withCustomProperties(['alt'=>...,'url_legado'=>$url])->toMediaCollection(...)`, preservando a ordem na galeria.
  - `conteudo` = `ReescritorImagensConteudo::reescrever(TransformadorBlog::limparGutenberg($d['conteudo'] ?? ''), $d['slug'], $post)` — o reescritor passa a, para cada `<img wp-content/uploads>`, baixar via `baixarCapado($url, 2000)` + `addMediaFromString(...)->toMediaCollection('conteudo')` e reescrever o `src` para `$media->getUrl('web')` **+ `data-id="{$media->getKey()}"`** (corpo na ML). Em falha (404), manter URL original + aviso.
  - O listener `CaparOriginalDaMidia` (Task 4) permanece como **rede de segurança** (no-op quando os bytes já vêm capados). Manter `BaixadorImagem` apenas como baixador de bytes (não mais como gravador de arquivo final).
- [ ] **Step 3:** Rodar `--filter=ImportadorBlogTest` → PASS; suíte completa verde. Commit `feat(blog): importador grava imagens na Media Library (destacada/galeria/og/corpo) + colunas`.

---

## Task 11: Extensão TipTap de alinhamento (JS + PHP + plugin + tools)

Executar as **Tasks 1 e 4 do plano irmão** (`2026-06-26-blog-imagens-alinhamento.md`): o **spike** (módulo JS via `FilamentAsset->loadedOnRequest` + `RichContentPlugin::getTipTapJsExtensions` + import de `window.FilamentRichEditor.tiptap`; espelho PHP estendendo `ImageExtension`; round-trip provado no `getHtml`), seguido dos atributos `align`+`size` (classes) e os 6 tools na toolbar. **Gate:** só seguir após o round-trip real (classe no HTML salvo) — ver o plano irmão. Registrar o plugin no `RichEditor` do `PostResource` (junto do provider da Task 4). Commit conforme o plano irmão.

> Nota de integração: o nó `image` agora carrega `data-id` (anexo ML) **e** `align`/`size` (classe). Garantir que os `renderHTML` (JS e PHP) **somam** as classes e preservam `data-id`/`src`.

---

## Task 12: CSS público `.conteudo-artigo`

Executar a **Task 5 do plano irmão** (`resources/css/conteudo.css` com o CSS de referência do spec — alignment/size em %/responsivo/empilha no mobile + grid `.colunas` + legenda no token do design; import em `app.css`; classe `.conteudo-artigo` no wrapper do single). `npm run build` + teste de saída responsiva (classes presentes, sem px/style inline). Commit conforme o plano irmão.

---

## Task 13: Reimport único + verificação

- [ ] **Step 1:** Suíte completa `docker compose exec -T app php artisan test` → verde.
- [ ] **Step 2: Reimport (túnel ativo)** — `docker compose exec -T app php artisan cema:importar-blog` (1×). Garantir o **worker** rodando (conversões em fila) ou conversões `web/og` `nonQueued`. Conferir 45 posts, media nas coleções, conversões geradas.
- [ ] **Step 3: Verificar no banco/storage** — amostrar posts: `getMedia('destacada')`/`getMedia('galeria')` populadas; **original capado** (≤2000px; og ≤1200) — conferir dimensão do arquivo `getPath()` de uma amostra; conversões `web`(webp)/`og` presentes em `storage`; corpo com `<img src=".../web.webp" data-id=...>` e (onde houver) colunas `.colunas`.
- [ ] **Step 4: Verificação visual (localhost)** — (a) admin: subir **várias** fotos na galeria de uma vez, ver **preview**, **reordenar** arrastando; subir destacada com **image editor**; inserir imagem no corpo (vai p/ ML, WebP) e **alinhar à esquerda + redimensionar** (texto contorna; <640px empilha). (b) front: hero/cards/single com WebP+srcset; 2–3 posts migrados com colunas.

---

## Verificação final / DoD
- `php artisan test` verde (testes ajustados p/ ML + novos de mídia/alinhamento).
- Admin: preview + upload múltiplo + reordenar (galeria); image editor; imagem do corpo otimizada (WebP) e alinhável/redimensionável.
- Front: imagens via ML (WebP/srcset), OG 1200×630; 45 posts migrados (destacada/galeria/corpo na ML; colunas preservadas) num único reimport.
- Original **capado na entrada** (≤2000px; og ≤1200) nos 3 caminhos — não se guarda o arquivo cru em tamanho cheio.

## Riscos / pontos de atenção
- **Original não-capado (disco):** a ML guarda o original cru por padrão e os otimizadores NÃO reduzem dimensão. Cap obrigatório na entrada nos 3 caminhos (admin `imageResize*`, importador `baixarCapado`, corpo via listener `CaparOriginalDaMidia`); teste de largura ≤2000/≤1200 nas Tasks 4 e 10.
- **Fila/worker:** conversões em fila exigem worker ativo; usar `nonQueued()` nas conversões que o front precisa logo após publicar, ou garantir o serviço `worker`.
- **`data-id` no purifier:** sem ele, o cleanup do provider apaga as imagens do corpo — Task 5 é pré-requisito do corpo-na-ML.
- **`Http::fake` nos testes do importador:** a ML valida mime → devolver bytes de imagem reais (`UploadedFile::fake()->image()`).
- **Quebra de URL nos asserts:** `/storage/{id}/...` (ML) ≠ `storage/blog/...` — todos os asserts ajustados (Task 8).
- **Round-trip da extensão (Task 11):** gate antes de seguir; o nó imagem deve somar `data-id` + classes de alinhamento.
- **Reimport único:** ordem `limparGutenberg → reescritor(ML) → mutator`; idempotente (`clearMediaCollection`).
