# Padrão único de imagens — CEMA

> **Regra de ouro:** qualquer imagem do site é servida em **WebP** e o disco guarda **só WebP**
> (nada de original "gordo" JPEG/PNG). **Não** reinvente upload/otimização por módulo — use as
> peças abaixo. Palestrante é a implementação de referência.

O tratamento é feito com **Spatie Media Library**. Há **duas trilhas**, ambas WebP:

| Trilha | Quando | Como |
|---|---|---|
| **1. Imagem de entidade** | a entidade *tem* uma foto/galeria (palestrante, evento, página institucional…) | trait `RegistraImagensPadrao` no model + `ComponentesImagem::upload()` no Filament |
| **2. Imagem no corpo de texto** | imagem *dentro* de um rich text (post do blog) | biblioteca de mídia central (`Biblioteca`), servida por `/midia/{id}/web` |

Em todas: o **original é capado e reencodado para WebP** no upload (listener global
`App\Listeners\CaparOriginalDaMidia`, no evento `MediaHasBeenAddedEvent`): lado mais longo
≤ **2000px** (≤ **1200px** na coleção `og`), depois **WebP** (q85). As conversões `web`/`thumb`
são geradas **a partir** desse WebP, **síncronas** (`nonQueued`), no disco `public`. SVG passa intacto.

---

## Trilha 1 — imagem de entidade (o caso comum)

### 1) Model — `HasMedia` + trait + coleção

```php
use App\Models\Concerns\RegistraImagensPadrao;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Evento extends Model implements HasMedia
{
    use InteractsWithMedia, RegistraImagensPadrao;

    public const COLECAO_CAPA = 'capa';

    public function registerMediaCollections(): void
    {
        // Assinatura: registrarColecaoImagem($colecao, $unica = true, $larguraWeb = 1600, $ladoThumb = 400)
        $this->registrarColecaoImagem(self::COLECAO_CAPA);           // 1 imagem (singleFile)
        // Galeria (múltiplas): $this->registrarColecaoImagem('galeria', unica: false, larguraWeb: 1920);
    }

    /** URL da conversão WebP servida no site (nunca o original). */
    protected function capaUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->getFirstMediaUrl(self::COLECAO_CAPA, 'web') ?: null);
    }

    protected function capaThumbUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->getFirstMediaUrl(self::COLECAO_CAPA, 'thumb') ?: null);
    }
}
```

A trait registra, na coleção, as conversões **`web`** (WebP q82, `Fit::Max` até `larguraWeb`) e
**`thumb`** (WebP `Fit::Crop` quadrada `ladoThumb`), ambas `nonQueued` (existem no momento de servir).

### 2) Filament — campo de upload padrão

```php
use App\Filament\Support\ComponentesImagem;

// no schema do Resource:
ComponentesImagem::upload('capa', Evento::COLECAO_CAPA)->label('Capa'),
// galeria: ComponentesImagem::upload('galeria', 'galeria', multiplas: true),
```

`ComponentesImagem::upload($nome, $colecao, $multiplas = false)` já faz: disco `public`,
resize client-side ≤ 2000px, editor de imagem e preview via `thumb`.

### 3) Servir no Blade — sempre a conversão, com fallback

```blade
@if ($evento->capa_url)
    <img src="{{ $evento->capa_url }}" alt="{{ $evento->titulo }}" loading="lazy" class="...">
@else
    {{-- sem foto: iniciais/gradiente, como em <x-palestra.card> e no perfil do palestrante --}}
@endif
```

**Nunca** sirva `getUrl()` sem conversão (é o original) — use sempre `getFirstMediaUrl($colecao, 'web'|'thumb')`.

---

## Trilha 2 — imagem no corpo de rich text (blog)

Imagens **dentro** do corpo de um post não são coleções do model; vão para o **pool central**
`App\Models\Biblioteca` (coleção `biblioteca`) e são referenciadas por URL estável
**`/midia/{id}/web`** (`MidiaController`), que serve a conversão WebP. Há dedup por hash
SHA-256 (`CalcularHashMidia`) e a ferramenta "Inserir da biblioteca" no editor. Ver
`BibliotecaResource`, `MidiaController` e `Biblioteca::instance()`.

---

## Checklist ao adicionar imagem a um módulo novo

- [ ] Model `implements HasMedia` + `use InteractsWithMedia, RegistraImagensPadrao;`.
- [ ] `registerMediaCollections()` chama `registrarColecaoImagem(...)` (uma const por coleção).
- [ ] Accessors `...Url`/`...ThumbUrl` via `getFirstMediaUrl($colecao, 'web'|'thumb')`.
- [ ] Filament usa `ComponentesImagem::upload(...)` (não um `FileUpload` cru).
- [ ] Blade serve a conversão (`web`/`thumb`) + fallback quando não há foto.
- [ ] **Não** criar disco/otimização/listener próprios: o pipeline WebP já é global.

## O que NÃO fazer

- ❌ `FileUpload::make()` cru gravando em disco arbitrário (foge do pipeline WebP).
- ❌ Servir o original (`getUrl()` sem conversão) ou assumir que ele é JPEG/PNG — ele é WebP.
- ❌ Registrar conversões em formato não-WebP, ou uma coleção sem passar pela trait.
- ❌ Otimizar imagem "na mão" no controller/observer — o listener global já capa + WebP-ifica.
