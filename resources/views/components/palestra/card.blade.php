@props(['palestra'])

@php
    $thumb = $palestra->youtube_thumb;
    $data = $palestra->data_da_palestra;
@endphp
<article {{ $attributes->class(['group flex flex-col overflow-hidden rounded-lg border border-border-muted bg-white shadow-card transition hover:shadow-elevated']) }}>
    <a href="{{ route('palestras.show', $palestra->slug) }}" class="flex h-full flex-col">
        <div class="relative aspect-video overflow-hidden bg-cream">
            @if ($thumb)
                <img src="{{ $thumb }}" alt="" loading="lazy" width="320" height="180"
                     class="size-full object-cover transition duration-300 group-hover:scale-[1.03]">
            @else
                <div aria-hidden="true" class="flex size-full items-center justify-center bg-gradient-to-br from-primary to-footer-bg">
                    <img src="{{ asset('images/logos/logo-icone.png') }}" alt="" class="h-10 w-auto opacity-90">
                </div>
            @endif
        </div>
        <div class="flex flex-1 flex-col p-4">
            <div class="mb-1.5 flex items-center gap-2 font-mono text-[10px] uppercase tracking-wide text-text-muted">
                @if ($data)<time datetime="{{ $data->toIso8601String() }}">{{ $data->translatedFormat('d \d\e M Y') }}</time>@endif
                <span class="rounded-pill bg-surface px-2 py-0.5 text-[10px] text-primary">{{ $palestra->online ? 'Online' : 'Presencial' }}</span>
            </div>
            <h3 class="line-clamp-2 font-display text-base font-semibold leading-snug text-primary group-hover:underline">{{ $palestra->titulo }}</h3>
            @if ($palestra->palestrantesAtivos->isNotEmpty())
                <p class="mt-2 line-clamp-1 text-xs text-text-muted">{{ $palestra->palestrantesAtivos->pluck('nome')->join(', ', ' e ') }}</p>
            @endif
        </div>
    </a>
</article>
