@props(['palestra'])

@php
    $primeiro = $palestra->palestrantesAtivos->first();
    $foto = $primeiro?->foto ? asset('storage/'.$primeiro->foto) : null;
    $data = $palestra->data_da_palestra;
@endphp
<article {{ $attributes->class(['group flex flex-col overflow-hidden rounded-lg border border-border-muted bg-white shadow-card transition hover:shadow-elevated']) }}>
    <a href="{{ route('palestras.show', $palestra->slug) }}" class="flex h-full flex-col">
        <div class="aspect-[16/10] overflow-hidden bg-cream">
            @if ($foto)
                <img src="{{ $foto }}" alt="{{ $primeiro->nome }}" loading="lazy" width="320" height="200"
                     class="size-full object-cover transition duration-300 group-hover:scale-[1.03]">
            @else
                <div aria-hidden="true" class="flex size-full items-center justify-center font-mono text-xs text-text-muted">CEMA</div>
            @endif
        </div>
        <div class="flex flex-1 flex-col p-5">
            <div class="mb-2 flex items-center gap-2 font-mono text-[11px] uppercase tracking-wide text-text-muted">
                @if ($data)<time datetime="{{ $data->toIso8601String() }}">{{ $data->translatedFormat('d \d\e M Y') }}</time>@endif
                <span class="rounded-pill bg-surface px-2 py-0.5 text-[10px] text-primary">{{ $palestra->online ? 'Online' : 'Presencial' }}</span>
            </div>
            <h3 class="font-display text-lg font-semibold leading-snug text-primary group-hover:underline">{{ $palestra->titulo }}</h3>
            @if ($palestra->subtitulo)
                <p class="mt-1 line-clamp-2 text-sm text-text-secondary">{{ $palestra->subtitulo }}</p>
            @endif
            @if ($palestra->palestrantesAtivos->isNotEmpty())
                <p class="mt-3 text-sm text-text-secondary">
                    <span class="text-text-muted">Palestrante:</span>
                    {{ $palestra->palestrantesAtivos->pluck('nome')->join(', ', ' e ') }}
                </p>
            @endif
        </div>
    </a>
</article>
