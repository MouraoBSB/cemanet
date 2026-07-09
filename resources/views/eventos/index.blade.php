@php
    $breadcrumbJsonLd = [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Início', 'item' => url('/')],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'Eventos'],
        ],
    ];

    if ($destaque) {
        $ds = $destaque->status_selo;
        $dGcInicio = $destaque->inicioUtc()->format('Ymd\THis\Z');
        $dGcFim = $destaque->fimUtc()->format('Ymd\THis\Z');
        $dGcLocal = $destaque->local ? $destaque->local.' — '.config('cema.endereco') : config('cema.endereco');
        $dGoogleAgenda = 'https://calendar.google.com/calendar/render?action=TEMPLATE&text='.urlencode($destaque->titulo)
            .'&dates='.$dGcInicio.'/'.$dGcFim
            .'&details='.urlencode(route('eventos.show', $destaque->slug))
            .'&location='.urlencode($dGcLocal);
    }
@endphp

<x-layout.app title="Eventos" description="Programação de eventos do CEMA: brechós, palestras temáticas, encontros e atividades abertas à comunidade.">
    <x-slot:head>
        <script type="application/ld+json">
            @json($breadcrumbJsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        </script>
    </x-slot:head>

    {{-- Hero --}}
    <section class="relative overflow-hidden bg-[linear-gradient(150deg,#4E4483,#2f2952)] text-white">
        <div class="relative mx-auto max-w-[1240px] px-6 py-16">
            <p class="font-mono text-xs uppercase tracking-[0.14em] text-[#9db8e0]">Programação do CEMA</p>
            <h1 class="mt-3 font-display text-4xl font-semibold sm:text-5xl">Eventos</h1>
            <div class="mt-4 h-1 w-16 rounded-full bg-gold"></div>
            <p class="mt-4 max-w-xl font-light text-[#d7def0]">Brechós, palestras temáticas, encontros e atividades abertas à comunidade.</p>
        </div>
    </section>

    {{-- Breadcrumb --}}
    <nav aria-label="Trilha de navegação" class="border-b border-border-muted bg-surface">
        <div class="mx-auto max-w-[1240px] px-6 py-2.5 text-[13px] text-text-muted">
            <a href="{{ route('home') }}" class="hover:text-primary">Início</a>
            <span aria-hidden="true"> › </span>
            <span class="text-text-secondary">Eventos</span>
        </div>
    </nav>

    {{-- Próximo destaque --}}
    @if ($destaque)
        <section class="mx-auto max-w-[1240px] px-6 pt-12" aria-label="Próximo destaque">
            <p class="mb-3 inline-flex items-center gap-2 font-display text-base font-semibold text-primary">
                <span class="inline-block size-2.5 animate-pulse rounded-full bg-gold" aria-hidden="true"></span> Próximo destaque
            </p>
            <div class="grid overflow-hidden rounded-[22px] border border-border-muted bg-cream sm:grid-cols-2">
                <div class="relative h-[220px] overflow-hidden sm:h-full">
                    <img src="{{ $destaque->flyerUrl ?? asset('images/logos/logo-icone.png') }}" alt="{{ $destaque->titulo }}"
                         loading="lazy" class="size-full object-cover">
                    <div class="absolute left-4 top-4 flex flex-wrap gap-2">
                        @if ($destaque->categoria)
                            <span class="rounded-pill px-3 py-1.5 font-mono text-[11px] font-medium uppercase tracking-wide"
                                  style="background: {{ $destaque->categoria->cor }}; color: {{ $destaque->categoria->cor_texto ?? '#fff' }};">
                                {{ $destaque->categoria->nome }}
                            </span>
                        @endif
                        <span class="rounded-pill px-3 py-1.5 text-[11px] font-semibold"
                              style="background: {{ $ds['cor'] }}; color: {{ $ds['cor_texto'] }};">
                            {{ $ds['rotulo'] }}
                        </span>
                    </div>
                </div>
                <div class="flex flex-col justify-center gap-3 p-7 sm:p-9">
                    <h2 class="font-display text-2xl font-semibold text-primary">{{ $destaque->titulo }}</h2>
                    <p class="text-sm font-medium text-text-secondary">{{ $destaque->periodo }}</p>
                    <p class="text-sm text-text-muted">{{ $destaque->local ?: 'Local a confirmar' }}</p>
                    <div class="mt-3 flex flex-wrap gap-2.5">
                        <a href="{{ route('eventos.show', $destaque->slug) }}"
                           class="rounded-pill bg-primary px-5 py-2.5 text-sm font-semibold text-white transition hover:opacity-90">Ver evento</a>
                        <a href="{{ $dGoogleAgenda }}" target="_blank" rel="noopener"
                           class="flex items-center gap-2 rounded-pill border border-primary px-5 py-2.5 text-sm font-semibold text-primary transition hover:bg-white">
                            📅 Adicionar à agenda
                        </a>
                    </div>
                </div>
            </div>
        </section>
    @endif

    {{-- Listagem --}}
    <section class="mx-auto max-w-[1240px] px-6 py-12">
        @livewire('eventos.lista', ['destaqueId' => $destaque?->id])
    </section>
</x-layout.app>
