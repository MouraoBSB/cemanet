{{-- Corpo pictográfico (I8): introdução textual (campo único $mensagem->corpo, saneado) +
     galeria das imagens LOCAIS da MediaLibrary (coleção pictografia, WebP web). Download por
     imagem (R3): atributo download com nome amigável derivado do título, apontando ao original
     (getUrl() sem conversão). NÃO usa link_arquivo (esse é o anexo Drive da sidebar). --}}
@php $desenhos = $mensagem->getMedia(\App\Models\Mensagem::COLECAO_PICTOGRAFIA); @endphp

@if (filled($mensagem->corpo))
    <div class="cema-msg-prose mb-8">{!! $mensagem->corpo !!}</div>
@endif

@if ($desenhos->isNotEmpty())
    <div class="cema-pictografia-grid">
        @foreach ($desenhos as $i => $img)
            <figure class="flex flex-col overflow-hidden rounded-[14px] border border-border-muted bg-[#FAFAFB]">
                <div class="aspect-[4/3] overflow-hidden bg-cream">
                    <img src="{{ $img->getUrl('web') }}" loading="lazy" decoding="async"
                         alt="{{ $mensagem->titulo }} — desenho {{ $i + 1 }}"
                         class="size-full object-cover">
                </div>
                <figcaption class="flex items-center justify-between gap-3 px-4 py-3">
                    <span class="font-mono text-[11px] uppercase tracking-[0.06em] text-text-muted">Desenho {{ $i + 1 }}</span>
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
@elseif (blank($mensagem->corpo))
    <p class="font-serif text-[15px] italic text-text-muted">Esta comunicação pictográfica ainda não tem desenhos disponíveis.</p>
@endif
