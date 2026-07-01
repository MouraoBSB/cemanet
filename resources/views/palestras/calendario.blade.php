<x-layout.app title="Calendário de Palestras" description="Todo domingo, às 19h. Assine e receba cada palestra pública do CEMA no seu calendário.">
    @php
        $eventos = $proximasParaSeo->map(function ($p) {
            $inicio = $p->data_da_palestra;
            $fim = $inicio->copy()->addMinutes(\App\Support\Palestras\DuracaoPalestra::minutos($p->duracao));
            $ev = [
                '@type' => 'Event',
                'name' => $p->titulo,
                'startDate' => $inicio->toIso8601String(),
                'endDate' => $fim->toIso8601String(),
                'eventAttendanceMode' => $p->online
                    ? 'https://schema.org/OnlineEventAttendanceMode'
                    : 'https://schema.org/OfflineEventAttendanceMode',
                'location' => $p->online
                    ? ['@type' => 'VirtualLocation', 'url' => $p->link_youtube]
                    : ['@type' => 'Place', 'name' => 'Centro Espírita Maria Madalena', 'address' => 'Quadra 02, Lote 16, Vila Vicentina, Planaltina, DF'],
                'url' => route('palestras.show', $p->slug),
            ];
            if ($p->palestrantesAtivos->isNotEmpty()) {
                $ev['performer'] = $p->palestrantesAtivos->map(fn ($x) => ['@type' => 'Person', 'name' => $x->nome])->all();
            }

            return $ev;
        })->all();

        $calendarioJsonLd = [
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            'name' => 'Próximas palestras públicas do CEMA',
            'itemListElement' => collect($eventos)->map(fn ($ev, $i) => [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'item' => $ev,
            ])->all(),
        ];
    @endphp
    <x-slot:head>
        @if (! empty($eventos))
            <script type="application/ld+json">
                @json($calendarioJsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            </script>
        @endif
    </x-slot:head>

    {{-- Hero --}}
    <section class="relative overflow-hidden bg-gradient-to-br from-primary to-footer-bg text-white">
        <x-ui.particulas />
        <div class="relative mx-auto flex max-w-[1240px] flex-col gap-8 px-6 py-16 desktop-sm:flex-row desktop-sm:items-center desktop-sm:justify-between">
            <div>
                <p class="font-mono text-xs uppercase tracking-[0.14em] text-gold">Agenda</p>
                <h1 class="mt-3 font-display text-4xl font-semibold sm:text-5xl">Calendário de Palestras</h1>
                <div class="mt-4 h-1 w-16 rounded-full bg-gold"></div>
                <p class="mt-4 max-w-xl font-light text-[#d7def0]">Todo domingo, às 19h, presencialmente e ao vivo pelo nosso canal. Assine e receba cada palestra no seu calendário.</p>
            </div>
            <button type="button" x-data x-on:click="$dispatch('open-assinar')"
                    class="flex items-center gap-3 rounded-2xl border border-white/20 bg-white/10 px-5 py-4 transition hover:bg-white/15">
                <span class="text-2xl text-gold" aria-hidden="true">🔔</span>
                <span class="font-display font-semibold">Assinar calendário</span>
            </button>
        </div>
    </section>

    {{-- Breadcrumb --}}
    <nav aria-label="Trilha de navegação" class="border-b border-border-muted bg-surface">
        <div class="mx-auto max-w-[1240px] px-6 py-2.5 text-[13px] text-text-muted">
            <a href="{{ url('/') }}" class="hover:text-primary">Início</a>
            <span aria-hidden="true"> › </span>
            <a href="{{ route('palestras.index') }}" class="hover:text-primary">Palestras</a>
            <span aria-hidden="true"> › </span>
            <span class="text-text-secondary" aria-current="page">Calendário</span>
        </div>
    </nav>

    {{-- Calendário (Livewire) --}}
    <section class="bg-surface">
        <div class="mx-auto max-w-[1240px] px-6 py-12">
            <livewire:palestras.calendario />
        </div>
    </section>

    {{-- Veja também --}}
    <section class="mx-auto max-w-[1240px] px-6 pb-16">
        <div class="border-t border-border-muted pt-8">
            <h2 class="font-display text-lg font-semibold text-primary">Veja também</h2>
            <div class="mt-4 flex flex-wrap gap-3">
                @foreach ([['Palestras Públicas', route('palestras.index')], ['Palestrantes', route('palestrantes.index')], ['Blog Sementeira de Luz', route('blog.index')]] as [$rotulo, $url])
                    <a href="{{ $url }}" class="inline-flex items-center gap-2 rounded-pill border border-border-muted bg-white px-5 py-2.5 text-sm text-[#3a3553] transition hover:border-primary">
                        <span class="size-2 rounded-full bg-accent" aria-hidden="true"></span>{{ $rotulo }}
                    </a>
                @endforeach
            </div>
        </div>
    </section>

    <x-palestras.assinar-modal :feed-url="route('palestras.calendario-ics')" />
</x-layout.app>
