@props(['palestra'])

@php
    $thumb = $palestra->youtube_thumb_hq;
    $data = $palestra->data_da_palestra;
    $palestrante = $palestra->palestrantesAtivos->first();
    $tema = $palestra->assuntos->first();
    $grad = $palestra->id % 8;
@endphp
<article {{ $attributes->class(['cema-talk-card group flex overflow-hidden rounded-[14px] border border-border-muted bg-white shadow-card']) }}>
    <a href="{{ route('palestras.show', $palestra->slug) }}" class="flex w-full items-stretch">
        <div class="cema-poster cema-grad-{{ $grad }} relative w-[130px] shrink-0 overflow-hidden sm:w-[150px]">
            @if ($thumb)
                <img src="{{ $thumb }}" alt="" loading="lazy" width="150" height="110" class="absolute inset-0 size-full object-cover">
            @else
                <img src="{{ asset('images/logos/logo-icone.png') }}" alt="" aria-hidden="true"
                     class="absolute left-1/2 top-1/2 h-8 w-auto -translate-x-1/2 -translate-y-1/2 opacity-80">
            @endif
        </div>
        <div class="flex flex-1 flex-wrap items-center justify-between gap-x-4 gap-y-2 px-4 py-4 sm:px-5">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2 text-[12px] text-text-muted">
                    @if ($data)<time datetime="{{ $data->toIso8601String() }}">{{ $data->translatedFormat('d \d\e M Y') }}</time>@endif
                    <x-palestra.badge-formato :palestra="$palestra" variante="claro" />
                    @if ($tema)<span class="rounded-pill bg-[#EFEBF7] px-2 py-0.5 text-[11px] text-[#6a6390]">{{ $tema->nome }}</span>@endif
                </div>
                <h3 class="mt-1 font-display text-[16.5px] font-semibold leading-snug text-text-ink group-hover:text-primary">{{ $palestra->titulo }}</h3>
                @if ($palestrante)<p class="mt-0.5 text-[13px] text-text-muted">com {{ $palestra->palestrantesAtivos->pluck('nome')->join(', ', ' e ') }}</p>@endif
            </div>
            <span class="cema-talk-cta inline-flex shrink-0 items-center gap-1.5 rounded-pill bg-cream px-4 py-2 text-[13px] font-medium text-primary transition">Ver palestra</span>
        </div>
    </a>
</article>
