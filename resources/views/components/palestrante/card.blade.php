@props(['palestrante'])

@php
    $foto = $palestrante->foto ? asset('storage/'.$palestrante->foto) : null;
    $resumoBio = $palestrante->bio ? \Illuminate\Support\Str::limit(strip_tags($palestrante->bio), 120) : null;
@endphp
<article {{ $attributes->class(['group flex flex-col overflow-hidden rounded-lg border border-border-muted bg-white shadow-card transition hover:shadow-elevated']) }}>
    <a href="{{ route('palestrantes.show', $palestrante->slug) }}" class="flex h-full flex-col">
        <div class="aspect-square overflow-hidden bg-cream">
            @if ($foto)
                <img src="{{ $foto }}" alt="{{ $palestrante->nome }}" loading="lazy" width="320" height="320"
                     class="size-full object-cover transition duration-300 group-hover:scale-[1.03]">
            @else
                <div class="flex size-full items-center justify-center font-mono text-xs text-text-muted" aria-hidden="true">CEMA</div>
            @endif
        </div>
        <div class="flex flex-1 flex-col p-5">
            <h3 class="font-display text-lg font-semibold text-primary group-hover:underline">{{ $palestrante->nome }}</h3>
            @isset($palestrante->palestras_ministradas_count)
                <p class="mt-1 font-mono text-[11px] uppercase tracking-wide text-text-muted">
                    {{ $palestrante->palestras_ministradas_count }} {{ \Illuminate\Support\Str::plural('palestra', $palestrante->palestras_ministradas_count) }}
                </p>
            @endisset
            @if ($resumoBio)
                <p class="mt-2 line-clamp-3 text-sm text-text-secondary">{{ $resumoBio }}</p>
            @endif
        </div>
    </a>
</article>
