# Fatia A — Performance do upload do corpo — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development. Steps usam checkbox (`- [ ]`).

**Goal:** O upload de imagem do corpo deixa de travar. Causa-raiz **confirmada por medição**: os limites de upload do PHP estão nos defaults (`upload_max_filesize=2M`, `post_max_size=8M`) → uma foto de 7,7 MB é rejeitada no upload → "Enviando 100%" trava para sempre. A conversão **não** é o gargalo (medido: cap 640ms + web 241ms + og 162ms ≈ **1s** para 24 MP com GD). Fatia própria, **merge próprio**.

**Architecture:** Alinhar os **três** tetos de upload (PHP · Livewire · anexo do RichEditor) em ~20 MB; manter a conversão **síncrona** (rápida) — sem imagick, sem fila (o que evita por construção os bugs de cache/fallback e de quebrar destacada/galeria/og). O resize client-side do corpo (JS custom) fica **adiado** (decisão do dono): o encolhimento client-side entra de forma **nativa** na Fatia B (upload da `BibliotecaResource` com `imageResizeTargetWidth`); reavaliar o JS custom no clipe do corpo só se a produção mostrar lentidão.

**Tech Stack:** PHP 8.3 (Docker `php artisan serve` no dev) · Laravel 13 · Filament 5.6.7 RichEditor · Spatie ML v11.

## Constraints / fatos confirmados

- **Conversão roda no SAVE**, não no attach: o drop/paste cria `TemporaryUploadedFile` (`AttachFilesAction` com `storeFiles(false)`); a persistência+conversão ocorrem no submit (`saveFileAttachments` → `SpatieMediaLibraryFileAttachmentProvider::addMediaFromString`). **Implicação:** o custo síncrono é **~1s por imagem no save** → o aceite inclui **cenário multi-imagem**.
- **`withResponsiveImages` já foi removido** do upload novo do corpo (coleção `conteudo`, `Post.php:137-148`). Nada a fazer lá. Os templates **não emitem `srcset`** (grep: 0) e o corpo serve `<img src>` simples.
- Conversão `web`/`og` são `nonQueued` (síncronas) — **manter assim** (pivô aprovado).
- Dev = `php artisan serve` **single-thread** (um upload travado serializa os outros — explica a foto de 1,6 MB que também "não terminava"). Produção = VPS+Docker (espelhar limites no nginx+fpm).
- pt-BR; cabeçalho de autoria; branch `fase-2-blog-editor-ux`; commits com `Co-Authored-By: Claude Opus 4.8`.
- Testes via Docker; após mudar PHP/Dockerfile, `docker compose build app && up -d`; OPcache `validate_timestamps=0` no dev (já configurado).

---

### Task A1: Alinhar os três tetos de upload em ~20 MB (CORE — conserta a dor)

**Files:** `Dockerfile`; `config/livewire.php` (publicar); `app/Filament/Resources/Posts/PostResource.php`; Test `tests/Feature/Filament/UploadLimitesTest.php`.

> ⚠️ Os três tetos coexistem; o **menor vence**. Hoje o efetivo seria ~12 MB (default do Livewire), mesmo subindo o PHP. Definir os três conscientemente.

- [ ] **Step 1: PHP** — no `Dockerfile`, junto às demais ini, criar `uploads.ini`:

```dockerfile
RUN { \
        echo "upload_max_filesize=20M"; \
        echo "post_max_size=22M"; \
    } > /usr/local/etc/php/conf.d/uploads.ini
```

- [ ] **Step 2: Livewire** — publicar `config/livewire.php` (`php artisan livewire:publish --config`) e ajustar a regra do upload temporário para ~20 MB:

```php
'temporary_file_upload' => [
    // ...
    'rules' => ['required', 'file', 'max:20480'], // 20 MB (KB)
],
```

- [ ] **Step 3: RichEditor** — limitar o anexo do campo `conteudo` (rejeita arquivo absurdo com mensagem clara). Confirmar o método exato no Filament 5 (provável `->fileAttachmentsMaxSize(20480)` em KB; o JS `LocalFiles` recebe `maxSize`). Aplicar em `PostResource` no `RichEditor::make('conteudo')`.

- [ ] **Step 4: Teste** — asserir o teto do anexo no campo e a presença do ini:

```php
public function test_campo_conteudo_tem_teto_de_anexo(): void
{
    Livewire::test(CreatePost::class)
        ->assertFormFieldExists('conteudo', fn (\Filament\Forms\Components\RichEditor $c): bool =>
            $c->getFileAttachmentsMaxSize() >= 20480); // método a confirmar
}
```

- [ ] **Step 5:** `docker compose build app worker && up -d` + testes verdes. **Verificação manual (aceite parte 1):** soltar foto de 7–8 MB no corpo → **não trava**; salvar → request retorna **≤ 3s** e a imagem aparece. Commit.

---

### Task A2: Higiene — remover `withResponsiveImages` ocioso (disco)

**Files:** `app/Models/Post.php`; Test `tests/Feature/Models/PostMediaTest.php`.

- [ ] **Step 1:** Confirmar (grep) que **nenhum** template usa `srcset`/responsive (confirmado: 0). O lightbox da galeria usa `getUrl('web')` simples (`show.blade:251`).
- [ ] **Step 2:** Remover `->withResponsiveImages()` de `Post.php`: coleção `corpo` (:157) e — confirmado o uso simples — `destacada` (:92) e `galeria` (:114). (Não afeta arquivos já gerados; só para de gerar variantes ociosas no futuro.)
- [ ] **Step 3: Teste** — `web` de `corpo`/`galeria` não gera responsive (espelhar o teste já existente de `conteudo`). Verdes. Commit.

---

### Task A3 — ADIADA (decisão do dono): Resize client-side no attach do corpo

> **Decisão do dono:** **não entra nesta fatia.** Os limites alinhados (A1) já destravam o 7–8 MB e o cap server-side normaliza para 2000px, então o resize client-side do corpo não é necessário agora. O encolhimento client-side entra de forma **nativa** na **Fatia B (Task B4)**: o upload da `BibliotecaResource` usa `->imageResizeTargetWidth(...)` (recurso nativo do `FileUpload`/`SpatieMediaLibraryFileUpload`), sem JS custom. **Reavaliar** o JS custom no clipe do corpo (`canvas`/`createImageBitmap` antes do `$wire.upload`) **somente** se a produção mostrar lentidão de upload pelo clipe.
>
> **Honestidade técnica (registro):** o `LocalFiles` do RichEditor (clipe do corpo) só faz checagem de `maxSize`, sem resize — por isso o resize do corpo exigiria JS custom; já o upload da biblioteca é um `FileUpload` comum, que tem o resize nativo. Nada a implementar nesta fatia.

---

## Verificação final (Fatia A)

- [ ] Suíte verde; `docker compose build` aplicado.
- [ ] **Aceite (segundos):** (a) 1 foto de 7–8 MB no corpo → request retorna **≤ 3s**, sem travar; (b) post com **~10 imagens** salva **sem travar** (medir o submit; com conversão síncrona ~1s/imagem, esperado ~poucos a ~10s — aceitável e finito; a Fatia B torna repetições instantâneas via picker). Hoje: infinito.
- [ ] **Nota de produção** (registrar): no VPS, espelhar `client_max_body_size 20m` (nginx) + `upload_max_filesize/post_max_size` (php-fpm) + manter `opcache.validate_timestamps=0`.
- [ ] **Merge próprio** da Fatia A em `main` (ou na branch combinada, conforme decisão de merge).
