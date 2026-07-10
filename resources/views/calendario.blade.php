<!-- Thiago Mourão — https://github.com/MouraoBSB — 2026-07-09 -->
<x-layout.app title="Calendário" description="Palestras e eventos do CEMA em um só calendário. Confira as próximas atividades e assine o feed.">
    @php
        // @json() explode(',') o expression inteiro — array literal com várias chaves quebra a
        // diretiva. Monta o array numa variável única antes (mesmo padrão de palestras/calendario.blade.php).
        $calendarioJsonLd = [
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            'name' => 'Próximas atividades do CEMA',
            'itemListElement' => $ocorrenciasSeo->values()->map(fn ($ev, $i) => [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'item' => $ev,
            ])->all(),
        ];
    @endphp
    <x-slot:head>
        <link rel="canonical" href="{{ route('calendario.index') }}">
        @if ($ocorrenciasSeo->isNotEmpty())
            <script type="application/ld+json">
                @json($calendarioJsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG)
            </script>
        @endif
    </x-slot:head>

    {{-- Hero --}}
    <section class="relative overflow-hidden bg-gradient-to-br from-primary to-footer-bg text-white">
        <x-ui.particulas />
        <div class="relative mx-auto flex max-w-[1240px] flex-col gap-8 px-6 py-16 desktop-sm:flex-row desktop-sm:items-center desktop-sm:justify-between">
            <div>
                <p class="font-mono text-xs uppercase tracking-[0.14em] text-gold">Agenda</p>
                <h1 class="mt-3 font-display text-4xl font-semibold sm:text-5xl">Calendário</h1>
                <div class="mt-4 h-1 w-16 rounded-full bg-gold"></div>
                <p class="mt-4 max-w-xl font-light text-[#d7def0]">Palestras e eventos do CEMA em um só lugar. Assine e receba cada atividade pública no seu calendário.</p>
            </div>
        </div>
    </section>

    {{-- Breadcrumb --}}
    <nav aria-label="Trilha de navegação" class="border-b border-border-muted bg-surface">
        <div class="mx-auto max-w-[1240px] px-6 py-2.5 text-[13px] text-text-muted">
            <a href="{{ url('/') }}" class="hover:text-primary">Início</a>
            <span aria-hidden="true"> › </span>
            <span class="text-text-secondary" aria-current="page">Calendário</span>
        </div>
    </nav>

    {{-- Calendário (Livewire) --}}
    <section class="bg-surface">
        <div class="mx-auto max-w-[1240px] px-6 py-12">
            <livewire:calendario.calendario />
        </div>
    </section>
</x-layout.app>
