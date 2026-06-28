# Fatia B — Biblioteca de mídia reutilizável (Opção B) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development. Steps usam checkbox (`- [ ]`). **Pré-requisito:** Fatia A mesclada/verificada. **Merge próprio.**

**Goal:** Pool central de mídia reutilizável: no editor, **escolher uma imagem já enviada** (sem re-subir), servida por uma **rota estável**; imagens novas do corpo viram **referência por URL portável** → conserta a imagem do corpo no front (#2) para conteúdo novo e remove a classe de bug "editor apaga imagem". Dedup por hash; deleção autoritativa.

**Architecture:** Singleton `Biblioteca` (HasMedia) dono da coleção `biblioteca`. Rota `GET /midia/{media}/{conversao?}` **restrita à coleção `biblioteca`**, servindo a WebP `web` (cache `immutable`) com fallback ao original (cache curto). Tool **"Inserir da biblioteca"** via `Action` (modal busca/preview) → insere `<img src="/midia/{id}/web">` (sem `data-id`). Dedup SHA-256 (hash pós-cap). Conversões **síncronas** (rápidas, pivô da Fatia A) → a `web` já existe ao servir. Reaproveita `CaparOriginalDaMidia` e o padrão de Resource.

**Tech Stack:** PHP 8.3 · Laravel 13 · Filament 5.6.7 (RichEditor TipTap v3 + tiptap-php) · Spatie ML v11 · mews/purifier · MySQL 8 / SQLite :memory:.

## Global Constraints

- **Imagem do conteúdo referenciada por URL relativa** `/midia/{id}/web` (sem domínio → portável p/ S3/CDN e troca de domínio). Comprovado seguro: as migradas já usam `src="/storage/..."` relativo e renderizam + passam no purifier (Task B2 reconfirma com round-trip).
- **A biblioteca é dona; o post NUNCA.** O cleanup de anexos do post só atua na coleção `COLECAO_CONTEUDO` **dele** (`cleanUpFileAttachments` → `clearMediaCollectionExcept`). A mídia da biblioteca é de outro dono/coleção → **nunca** é candidata a esse cleanup. (`id=null` no `<img>` inserido é para passar no **tamper-check** e não ser tratado como anexo gerenciado — **não** é a proteção primária; a proteção é por **coleção/dono**.)
- **Conversões da biblioteca SÍNCRONAS** (`nonQueued`, coerente com a Fatia A) → a `web` existe quando a rota serve. O **fallback** (servir o original) é caso raro (conversão ausente/falha) e usa **cache curto**.
- **NÃO usar Curator.** NÃO mexer no clipe/attach. NÃO quebrar destacada/galeria/og.
- **NÃO TOCAR na cadeia de dimensionamento de imagem** (já corrigida e validada): `resources/js/filament/imagem-alinhada.js`, `floatingToolbars`/toolbar do `PostResource`, `resources/css/filament/editor.css`. A Task B5 mexe no mesmo `PostResource` — apenas **acrescentar** plugin/tool, sem alterar o bloco de imagem; **UAT** após (selecionar imagem → tamanho/alinhamento ainda funcionam).
- **Dedup por SHA-256 do arquivo CAPADO** (listener após `CaparOriginalDaMidia`), em `custom_properties['sha256']`.
- **Deleção autoritativa**: `Post::whereRaw('conteudo LIKE ?', ["%/midia/{id}/%"])` (com a **barra final** → `12` não casa `123`; teste de fronteira). Bloquear/avisar se em uso.
- pt-BR; cabeçalho de autoria; commits com `Co-Authored-By: Claude Opus 4.8`. Testes/assets/migrations via Docker; `restart app` após PHP (opcache).

## Fora de escopo (adiado — confirmado pelo dono)

- **Re-migração dos 44 posts** (corpo → referências da biblioteca): o corpo já renderiza via `/storage` hoje (provider com fallback). É **portabilidade futura** (S3/CDN), em fatia própria, com **lógica nova + dry-run + backup** da coluna `conteudo` (NÃO reusar o `ReescritorImagensConteudo`, que faz `clearMediaCollection(corpo)` e regex `wp-content/uploads`).
- **Polish do textColor** (#1): a base já existe (`textColors` no PostResource + `.color` no editor.css). Se for tratar, **investigar `data-color` (front) vs `--color` inline (editor)** — não reimplementar.

## File Structure

**Criar:** `app/Models/Biblioteca.php` + migration `bibliotecas`; `app/Listeners/CalcularHashMidia.php`; `app/Support/Biblioteca/RegistraMidiaBiblioteca.php`; `app/Http/Controllers/MidiaController.php`; `app/Filament/RichContent/Actions/InserirDaBibliotecaAction.php`; `app/Filament/RichContent/Plugins/BibliotecaMidiaPlugin.php`; `app/Filament/Resources/Bibliotecas/BibliotecaResource.php` (+ Pages); testes.
**Modificar:** `routes/web.php`; `app/Providers/AppServiceProvider.php`; `app/Filament/Resources/Posts/PostResource.php` (só acrescentar plugin/tool).

---

### Task B1: Modelo singleton `Biblioteca` + coleção `biblioteca` (conversões síncronas)

**Files:** `app/Models/Biblioteca.php`, `database/migrations/2026_06_28_000002_create_bibliotecas_table.php`; Test `tests/Feature/Biblioteca/BibliotecaModelTest.php`.

- [ ] **Migration**: `bibliotecas` (`id`, `tipo` unique default 'principal', timestamps).
- [ ] **Teste**: `Biblioteca::instance()` singleton (mesmo id 2×, count=1) + `instanceof HasMedia`.
- [ ] **Modelo** (HasMedia + InteractsWithMedia), `COLECAO='biblioteca'`, `instance()` via `firstOrCreate(['tipo'=>'principal'])`. Conversões **síncronas**:

```php
$this->addMediaCollection(self::COLECAO)->registerMediaConversions(function (Media $m) {
    $this->addMediaConversion('web')->fit(Fit::Max, 1920, 1920)->format('webp')->quality(82)->nonQueued();
    $this->addMediaConversion('thumb')->fit(Fit::Crop, 400, 300)->format('webp')->nonQueued();
});
```

- [ ] `migrate` + teste verde. Commit.

---

### Task B2: Rota estável `/midia/{media}/{conversao?}` (restrita à coleção, cache ramificado)

**Files:** `app/Http/Controllers/MidiaController.php`; `routes/web.php`; Test `tests/Feature/Midia/MidiaRotaTest.php` + caso em `SanitizacaoBlogTest`.

- [ ] **Testes**: serve `web` (200); 404 p/ inexistente; **404 p/ mídia fora da coleção `biblioteca`** (#5); rejeita conversão fora de `['web','thumb']` → usa `web` (#11); round-trip purifier mantém `/midia/12/web`.
- [ ] **Controller** — **#5 escopo** + **#11 allowlist** + **#1 cache ramificado**:

```php
public function serve(int $media, string $conversao = 'web')
{
    $m = Media::query()
        ->where('collection_name', \App\Models\Biblioteca::COLECAO) // #5: só biblioteca
        ->findOrFail($media);

    $conversao = in_array($conversao, ['web', 'thumb'], true) ? $conversao : 'web'; // #11

    $gerada  = $m->hasGeneratedConversion($conversao);
    $caminho = $gerada ? $m->getPath($conversao) : $m->getPath();
    abort_unless(is_file($caminho), 404);

    // #1: immutable só quando a conversão existe (conteúdo estável por media id);
    // fallback (original servido sob a URL da conversão) = cache curto, p/ pegar a WebP depois.
    $cache = $gerada
        ? 'public, max-age=31536000, immutable'
        : 'public, max-age=60';

    return response()->file($caminho, [
        'Cache-Control' => $cache,
        'Content-Type'  => $gerada ? 'image/webp' : $m->mime_type,
    ]);
}
```
(S3 futuro: trocar só `response()->file()` por `Storage::disk($m->disk)->response(...)`.)

- [ ] **Rota** antes do catch-all: `Route::get('/midia/{media}/{conversao?}', [MidiaController::class,'serve'])->name('midia.serve')->where('media','[0-9]+')->where('conversao','[a-z]+');`
- [ ] Testes verdes. Commit.

---

### Task B3: Dedup SHA-256 — listener pós-cap + serviço

**Files:** `app/Listeners/CalcularHashMidia.php`, `app/Support/Biblioteca/RegistraMidiaBiblioteca.php`; `app/Providers/AppServiceProvider.php`; Test `tests/Feature/Biblioteca/DedupMidiaTest.php`.

- [ ] **Teste**: registrar o mesmo arquivo 2× → 1 mídia; `custom_properties['sha256']` preenchido (do arquivo capado).
- [ ] **Listener** `CalcularHashMidia`: `setCustomProperty('sha256', hash_file('sha256', $media->getPath()))->saveQuietly()`.
- [ ] **Registrar APÓS o cap** em `AppServiceProvider::boot()` (ordem garante hash pós-cap):

```php
Event::listen(MediaHasBeenAddedEvent::class, CaparOriginalDaMidia::class);
Event::listen(MediaHasBeenAddedEvent::class, CalcularHashMidia::class); // DEPOIS
```

- [ ] **Serviço** `RegistraMidiaBiblioteca::aPartirDoCaminho(...)`: checa hash de entrada na coleção (`whereJsonContains('custom_properties->sha256', $hash)`) → retorna existente; senão `addMediaFromString(...)->toMediaCollection('biblioteca')` e **reverifica** pelo hash canônico (pós-cap), descartando duplicata. (código no plano anterior, mantido.)
- [ ] Testes verdes (incl. não-regressão do cap). Commit.

---

### Task B4: `BibliotecaResource` (navegar/buscar/preview/upload) + deleção autoritativa

**Files:** `app/Filament/Resources/Bibliotecas/BibliotecaResource.php` (+ Pages); Test `tests/Feature/Filament/BibliotecaResourceTest.php`.

- [ ] **Testes**: listagem renderiza; **deleção autoritativa** — mídia referenciada em `Post.conteudo` via `/midia/{id}/` é bloqueada/avisada; mídia livre deleta; **teste de fronteira do LIKE (#11)**: `/midia/12/` NÃO casa um post que só usa `/midia/123/`.
- [ ] **Resource**: tabela com `ImageColumn` (preview `route('midia.serve',[id,'thumb'])`), nome/size/data, **busca por nome**, paginação. **Upload** (ação/modal) via `RegistraMidiaBiblioteca` (dedup). Padrão `PalestranteResource`/`PostResource`.
- [ ] **Resize client-side nativo no upload** (absorve a Task A3 adiada, sem JS custom): o `FileUpload`/`SpatieMediaLibraryFileUpload` do modal de upload usa `->imageResizeTargetWidth('2000')->imageResizeMode('contain')->imageResizeUpscale(false)` → o navegador encolhe a imagem para ≤2000px de largura **antes** de subir, aliviando transferência e o save síncrono. (O cap server-side `Fit::Max 1920` continua como rede de segurança.) Confirmar os nomes exatos dos métodos no Filament 5 antes de aplicar.
- [ ] **Deleção autoritativa** (varredura ANTES, com barra final):

```php
$usos = Post::whereRaw('conteudo LIKE ?', ["%/midia/{$media->id}/%"])->count();
if ($usos > 0) { Notification::make()->danger()->title('Imagem em uso')
    ->body("Usada em {$usos} post(s). Remova as referências antes de excluir.")->send(); return; }
```

- [ ] Testes verdes. Commit.

---

### Task B5: Editor — tool "Inserir da biblioteca" (Action/modal) — sem regredir dimensionamento

**Files:** `app/Filament/RichContent/Actions/InserirDaBibliotecaAction.php`, `app/Filament/RichContent/Plugins/BibliotecaMidiaPlugin.php` (+ componente/Blade da grade); `app/Filament/Resources/Posts/PostResource.php`; Test `tests/Feature/Filament/PostResourceTest.php`.

- [ ] **Teste**: toolbar inclui `inserirDaBiblioteca` (`hasToolbarButton`).
- [ ] **Ação** (`#9` via `->action()`, NÃO jsHandler): modal com busca + grade de miniaturas; ao escolher, insere via `runCommands`:

```php
->action(function (array $arguments, array $data, RichEditor $component): void {
    $media = Media::query()->where('collection_name', Biblioteca::COLECAO)->findOrFail($data['midia_id']);
    $component->runCommands(
        [EditorCommand::make('insertContent', arguments: [[
            'type'  => 'image',
            'attrs' => ['src' => route('midia.serve', [$media->id, 'web']),
                        'alt' => $media->getCustomProperty('alt') ?? $media->name,
                        'id'  => null], // tamper-check ok; cleanup é por coleção (não por isto)
        ]])],
        editorSelection: $arguments['editorSelection'],
    );
});
```
(Grade: componente reativo com `$set('midia_id', id)` — independe de índice de modal.)

- [ ] **Plugin** `BibliotecaMidiaPlugin`: `getEditorTools()` → `RichEditorTool::make('inserirDaBiblioteca')->label('Inserir da biblioteca')->icon(...)->action()`; `getEditorActions()` → `[InserirDaBibliotecaAction::make()]`; demais `[]`.
- [ ] **Registrar no `PostResource`** — **APENAS acrescentar** (`#4` INTOCÁVEL): `->plugins([ImagemPlugin::make(), TextoAlinhamentoPlugin::make(), BibliotecaMidiaPlugin::make()])` + `'inserirDaBiblioteca'` no `toolbarButtons`. **NÃO** alterar o bloco das ferramentas de imagem (alinhar/tamanho/floatingToolbars).
- [ ] **Verificação manual (obrigatória):** inserir da biblioteca → busca/preview/insere → salva → **front renderiza** (`/midia/{id}/web`); reabrir o post → imagem permanece (não some). **UAT do dimensionamento (#4):** selecionar uma imagem → tamanho/alinhamento **ainda funcionam** (não regrediu); barra flutuante lilás ok.
- [ ] Teste verde. Commit.

---

## Verificação final (Fatia B)

- [ ] Suíte verde; `filament:assets` + `restart app`.
- [ ] **Aceite (do dono):** inserir da biblioteca sem re-upload ✔ · dedup por hash ✔ · deleção autoritativa (com fronteira do LIKE) ✔ · imagem nova do corpo por rota estável + WebP, renderiza no front e não some ao reabrir ✔ · dimensionamento de imagem intacto (UAT) ✔.
- [ ] **Merge próprio** da Fatia B.
- [ ] **Backlog registrado:** re-migração dos 44 posts (portabilidade S3/CDN, com dry-run+backup) + polish do textColor.
