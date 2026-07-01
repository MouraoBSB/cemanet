<x-layout.app title="Calendário de Palestras" description="Agenda das próximas palestras públicas do Centro Espírita Maria Madalena (CEMA).">
    <section class="bg-gradient-to-br from-primary to-footer-bg text-white">
        <div class="mx-auto max-w-[1240px] px-6 py-14">
            <p class="font-mono text-xs uppercase tracking-[0.14em] text-gold">Agenda</p>
            <h1 class="mt-2 font-display text-3xl font-semibold sm:text-4xl">Calendário de Palestras</h1>
            <p class="mt-3 max-w-xl text-white/85">Próximas palestras públicas do CEMA.</p>
        </div>
    </section>

    <section class="mx-auto max-w-[1240px] px-6 py-12">
        @if ($proximas->isEmpty())
            <p class="rounded-lg border border-border-muted bg-surface px-6 py-10 text-center text-text-secondary">
                Nenhuma palestra futura agendada no momento.
            </p>
        @else
            <ul class="flex flex-col gap-3">
                @foreach ($proximas as $palestra)
                    <li>
                        <a href="{{ route('palestras.show', $palestra->slug) }}"
                           class="flex flex-wrap items-center gap-x-4 gap-y-1 rounded-lg border border-border-muted bg-white px-5 py-4 shadow-card transition hover:border-primary">
                            <time datetime="{{ $palestra->data_da_palestra->toIso8601String() }}"
                                  class="font-mono text-sm text-primary">{{ $palestra->data_da_palestra->translatedFormat('d \d\e M Y · H\hi') }}</time>
                            <span class="font-display font-semibold text-text-ink">{{ $palestra->titulo }}</span>
                            <span class="rounded-pill bg-surface px-2.5 py-0.5 text-xs text-primary">{{ $palestra->online ? 'Online' : 'Presencial' }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</x-layout.app>
