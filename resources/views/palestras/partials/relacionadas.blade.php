@if ($relacionadas->isNotEmpty())
    <section class="bg-surface">
        <div class="mx-auto max-w-[1100px] px-6 py-10">
            <h2 class="mb-5 font-display text-2xl font-semibold text-primary">Você também pode gostar</h2>
            <div class="grid gap-5 sm:grid-cols-2 desktop-sm:grid-cols-3">
                @forelse ($relacionadas as $rel)
                    <a href="{{ route('palestras.show', $rel->slug) }}"
                       class="block overflow-hidden rounded-2xl border border-border-muted bg-white shadow-card transition hover:-translate-y-1">
                        @php($thumb = $rel->youtube_thumb ?: optional($rel->palestrantesAtivos->first())->foto_thumb_url)
                        @if ($thumb)
                            <img src="{{ $thumb }}" alt="{{ $rel->titulo }}" loading="lazy" class="aspect-video w-full bg-surface object-cover">
                        @else
                            <div class="aspect-video bg-gradient-to-br from-primary to-footer-bg"></div>
                        @endif
                        <div class="p-4">
                            <p class="font-display font-semibold text-text-ink">{{ \Illuminate\Support\Str::limit($rel->titulo, 50) }}</p>
                            <p class="mt-1 text-xs text-text-muted">
                                {{ $rel->data_da_palestra?->translatedFormat('d \d\e F \d\e Y') ?? 'A confirmar' }}
                                @if ($rel->palestrantesAtivos->isNotEmpty()) · {{ $rel->palestrantesAtivos->first()->nome }} @endif
                            </p>
                        </div>
                    </a>
                @empty
                @endforelse
            </div>
        </div>
    </section>
@endif
