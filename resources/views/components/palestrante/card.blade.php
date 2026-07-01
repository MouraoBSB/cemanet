@props(['palestrante'])

@php($contagem = $palestrante->palestras_ministradas_count ?? 0)
<a {{ $attributes->except(['palestrante']) }} href="{{ route('palestrantes.show', $palestrante->slug) }}" aria-label="{{ $palestrante->nome }}"
   class="cema-spk-card group flex flex-col overflow-hidden rounded-2xl border border-border-muted bg-white shadow-card">
    {{-- Topo: foto ou gradiente + iniciais --}}
    <div class="cema-spk-avatar cema-grad-{{ $palestrante->id % 8 }} relative h-[188px] w-full overflow-hidden">
        @if ($palestrante->foto_url)
            <img src="{{ $palestrante->foto_url }}" alt="" loading="lazy" width="212" height="188"
                 class="size-full object-cover">
        @else
            <span class="flex size-full items-center justify-center font-display text-[54px] font-semibold text-white/90" aria-hidden="true">{{ $palestrante->iniciais }}</span>
        @endif
        <span class="absolute right-2.5 top-2.5 inline-flex items-center gap-1 rounded-pill bg-black/[0.28] px-2.5 py-1 text-xs font-semibold text-white backdrop-blur">
            <svg class="size-3.5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 15a3 3 0 0 0 3-3V6a3 3 0 1 0-6 0v6a3 3 0 0 0 3 3Zm5-3a5 5 0 0 1-10 0H5a7 7 0 0 0 6 6.92V22h2v-3.08A7 7 0 0 0 19 12h-2Z"/></svg>
            {{ $contagem }}
        </span>
    </div>
    {{-- Corpo: nome + botão --}}
    <div class="flex flex-1 flex-col gap-3 p-4">
        <h3 class="font-display text-[16.5px] font-semibold text-text-ink">{{ $palestrante->nome }}</h3>
        <span class="cema-spk-cta mt-auto inline-flex w-fit items-center gap-1.5 rounded-pill bg-cream px-4 py-2 text-sm font-semibold text-primary transition">
            Ver palestras
            <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M9 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </span>
    </div>
</a>
