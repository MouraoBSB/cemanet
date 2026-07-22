@props(['mensagem', 'legenda' => 'Imagem'])

{{-- Galeria das imagens LOCAIS da MediaLibrary (coleção imagens, WebP web), compartilhada
     pelos 3 formatos. Na pictografia os desenhos SÃO a mensagem (legenda "Desenho"); nos
     demais são ilustração ("Imagem"). Download por item apontando ao ORIGINAL (getUrl() sem
     conversão), nome amigável derivado do título. NÃO usa link_arquivo (anexo Drive da sidebar). --}}
@php $imagens = $mensagem->getMedia(\App\Models\Mensagem::COLECAO_IMAGENS); @endphp

@if ($imagens->isNotEmpty())
    {{-- $attributes->class(): sem isto, o `class="mt-8"` que a psicografia passa é descartado
         em silêncio (molde de components/mensagem/card.blade.php:16). --}}
    <div {{ $attributes->class(['cema-pictografia-grid']) }}>
        @foreach ($imagens as $i => $img)
            <figure class="flex flex-col overflow-hidden rounded-[14px] border border-border-muted bg-[#FAFAFB]">
                <div class="aspect-[4/3] overflow-hidden bg-cream">
                    <img src="{{ $img->getUrl('web') }}" loading="lazy" decoding="async"
                         alt="{{ $mensagem->titulo }} — {{ mb_strtolower($legenda) }} {{ $i + 1 }}"
                         class="size-full object-cover">
                </div>
                <figcaption class="flex items-center justify-between gap-3 px-4 py-3">
                    <span class="font-mono text-[11px] uppercase tracking-[0.06em] text-text-muted">{{ $legenda }} {{ $i + 1 }}</span>
                    <a href="{{ $img->getUrl() }}"
                       download="{{ \Illuminate\Support\Str::slug($mensagem->titulo) }}-{{ $i + 1 }}.{{ $img->extension ?: 'jpg' }}"
                       class="inline-flex items-center gap-1.5 rounded-pill bg-cream px-3 py-1.5 text-[12px] font-medium text-primary transition hover:bg-primary hover:text-white">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        Baixar
                    </a>
                </figcaption>
            </figure>
        @endforeach
    </div>
@endif
