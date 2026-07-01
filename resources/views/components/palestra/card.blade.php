@props(['palestra'])

@php
    $thumb = $palestra->youtube_thumb_hq;
    $data = $palestra->data_da_palestra;
    $palestrante = $palestra->palestrantesAtivos->first();
    $tema = $palestra->assuntos->first();
    $grad = $palestra->id % 8;
@endphp
<article {{ $attributes->class(['cema-talk-card group flex flex-col overflow-hidden rounded-lg border border-border-muted bg-white shadow-card']) }}>
    <a href="{{ route('palestras.show', $palestra->slug) }}" class="flex h-full flex-col">
        {{-- Pôster 16:10 --}}
        <div class="cema-poster cema-grad-{{ $grad }} relative aspect-[16/10] overflow-hidden">
            @if ($thumb)
                <img src="{{ $thumb }}" alt="" loading="lazy" width="480" height="360"
                     class="absolute inset-0 size-full object-cover transition duration-300 group-hover:scale-[1.03]">
            @endif
            <div class="cema-poster__overlay absolute inset-0 flex flex-col justify-between p-4">
                <div class="flex items-start justify-between gap-2">
                    <span class="font-mono text-[9.5px] font-medium uppercase tracking-[0.14em] text-white/80">Palestra Pública</span>
                    <x-palestra.badge-formato :palestra="$palestra" />
                </div>
                <div>
                    <h3 class="cema-poster__titulo font-display text-lg font-bold leading-tight text-white">{{ $palestra->titulo }}</h3>
                    @if ($palestrante)
                        <span class="mt-2 inline-flex items-center gap-2 rounded-pill bg-black/25 py-1 pl-1 pr-3 backdrop-blur-sm">
                            <span class="flex size-6 items-center justify-center overflow-hidden rounded-full bg-white/90">
                                @if ($palestrante->foto_thumb_url)
                                    <img src="{{ $palestrante->foto_thumb_url }}" alt="" class="size-full object-cover">
                                @else
                                    <span class="font-display text-[10px] font-semibold text-primary">{{ collect(explode(' ', $palestrante->nome))->take(2)->map(fn ($n) => mb_substr($n, 0, 1))->implode('') }}</span>
                                @endif
                            </span>
                            <span class="text-[11.5px] font-medium text-white">{{ $palestrante->nome }}</span>
                        </span>
                    @endif
                </div>
            </div>
            @unless ($thumb)
                <img src="{{ asset('images/logos/logo-icone.png') }}" alt="" aria-hidden="true"
                     class="pointer-events-none absolute right-3 top-3 h-6 w-auto opacity-70">
            @endunless
        </div>
        {{-- Rodapé --}}
        <div class="flex flex-1 items-center justify-between gap-2 px-4 py-3.5">
            <div class="flex flex-wrap items-center gap-2 text-[11px] text-text-muted">
                @if ($data)
                    <time datetime="{{ $data->toIso8601String() }}" class="inline-flex items-center gap-1">
                        <span aria-hidden="true">📅</span>{{ $data->translatedFormat('d \d\e M Y') }}
                    </time>
                @endif
                @if ($tema)
                    <span class="rounded-pill bg-[#EFEBF7] px-2 py-0.5 text-[11px] text-[#6a6390]">{{ $tema->nome }}</span>
                @endif
            </div>
            <span class="cema-talk-cta inline-flex items-center gap-1.5 rounded-pill bg-cream px-3.5 py-1.5 text-[12.5px] font-medium text-primary transition">Ver</span>
        </div>
    </a>
</article>
