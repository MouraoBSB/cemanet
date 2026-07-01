<x-layout.app title="Palestrantes" description="Conheça os palestrantes do CEMA — colaboradores que partilham as reflexões do Evangelho à luz da Doutrina Espírita.">
    @php
        $breadcrumbJsonLd = [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'Início', 'item' => url('/')],
                ['@type' => 'ListItem', 'position' => 2, 'name' => 'Palestras', 'item' => url('/palestra_publica')],
                ['@type' => 'ListItem', 'position' => 3, 'name' => 'Palestrantes'],
            ],
        ];
    @endphp
    <x-slot:head>
        <script type="application/ld+json">
            @json($breadcrumbJsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        </script>
    </x-slot:head>

    {{-- Hero --}}
    <section class="relative overflow-hidden bg-gradient-to-br from-primary to-footer-bg text-white">
        <x-ui.particulas />
        <div class="relative mx-auto flex max-w-[1240px] flex-col gap-8 px-6 py-16 desktop-sm:flex-row desktop-sm:items-center desktop-sm:justify-between">
            <div class="max-w-xl">
                <p class="font-mono text-xs uppercase tracking-[0.14em] text-[#9db8e0]">Palestras Públicas · CEMA</p>
                <h1 class="mt-3 font-display text-4xl font-semibold sm:text-5xl">Palestrantes</h1>
                <div class="mt-4 h-1 w-16 rounded-full bg-gold"></div>
                <p class="mt-4 font-light text-white/85">Colaboradores incansáveis que, com simplicidade e fraternidade, partilham conosco as reflexões do Evangelho à luz da Doutrina Espírita.</p>
            </div>
            <a href="{{ route('palestras.calendario') }}"
               class="flex items-center gap-3 rounded-2xl border border-white/20 bg-white/10 px-5 py-4 transition hover:bg-white/15">
                <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-gold text-[#3a2f00]" aria-hidden="true">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="17" rx="2"/><path d="M3 9h18M8 2v4M16 2v4" stroke-linecap="round"/></svg>
                </span>
                <span>
                    <span class="block font-display font-semibold">Calendário de Palestras</span>
                    <span class="block text-sm text-white/75">Veja a programação completa →</span>
                </span>
            </a>
        </div>
    </section>

    {{-- Breadcrumb --}}
    <nav aria-label="Trilha de navegação" class="border-b border-border-muted bg-surface">
        <div class="mx-auto max-w-[1240px] px-6 py-2.5 text-[13px] text-text-muted">
            <a href="{{ url('/') }}" class="hover:text-primary">Início</a>
            <span aria-hidden="true"> › </span>
            <a href="{{ route('palestras.index') }}" class="hover:text-primary">Palestras</a>
            <span aria-hidden="true"> › </span>
            <span class="text-text-secondary" aria-current="page">Palestrantes</span>
        </div>
    </nav>

    {{-- Conteúdo + sidebar --}}
    <section class="bg-surface">
        <div class="mx-auto flex max-w-[1240px] flex-col gap-8 px-6 py-12 desktop-sm:flex-row desktop-sm:items-start">
            <div class="min-w-0 flex-1">
                <livewire:palestrantes.lista />
            </div>
            <aside class="w-full shrink-0 desktop-sm:w-[340px]">
                {{-- Os Palestrantes --}}
                <div class="rounded-2xl border border-border-muted bg-white p-6 shadow-card">
                    <h2 class="font-display text-lg font-semibold text-primary">Os Palestrantes</h2>
                    <p class="mt-3 text-sm text-text-secondary">Cada palestra do CEMA nasce do trabalho voluntário e amoroso de irmãos e irmãs que dedicam seu tempo, seu estudo e seu coração à difusão dos ensinamentos do Evangelho à luz da Doutrina Espírita.</p>
                    <p class="mt-2 text-sm text-text-secondary">Não são oradores profissionais, mas companheiros de caminhada que, com simplicidade e fraternidade, aproximam o conhecimento espírita do dia a dia de cada um de nós.</p>
                    <div class="mt-5 grid grid-cols-2 gap-3">
                        <div class="rounded-xl bg-cream px-4 py-3">
                            <p class="font-display text-2xl font-bold text-primary">{{ $totalColaboradores }}</p>
                            <p class="text-xs text-text-muted">Colaboradores</p>
                        </div>
                        <div class="rounded-xl bg-secondary/[0.12] px-4 py-3">
                            <p class="font-display text-2xl font-bold text-secondary">{{ $totalAcervo }}</p>
                            <p class="text-xs text-text-muted">Palestras no acervo</p>
                        </div>
                    </div>
                </div>

                {{-- Em destaque: próxima palestra (sem fallback) --}}
                @if ($proxima)
                    @php($dpp = $proxima->palestrantesAtivos->first())
                    <div class="relative mt-6 overflow-hidden rounded-2xl bg-gradient-to-br from-primary to-[#3a3266] p-6 text-white shadow-card">
                        <p class="mb-3 inline-flex items-center gap-2 font-display text-sm font-semibold">
                            <span class="inline-block size-2 animate-pulse rounded-full bg-gold motion-reduce:animate-none" aria-hidden="true"></span> Em destaque
                        </p>
                        <div class="flex items-center gap-3">
                            <span class="cema-spk-avatar cema-grad-{{ ($dpp?->id ?? $proxima->id) % 8 }} grid size-12 shrink-0 place-items-center overflow-hidden rounded-full ring-2 ring-white/25">
                                @if ($dpp?->foto_thumb_url)
                                    <img src="{{ $dpp->foto_thumb_url }}" alt="" width="48" height="48" class="size-full object-cover">
                                @else
                                    <span class="font-display text-sm font-semibold text-white/90" aria-hidden="true">{{ $dpp?->iniciais ?? 'CEMA' }}</span>
                                @endif
                            </span>
                            <div class="min-w-0">
                                @if ($dpp)<p class="truncate text-sm text-white/80">{{ $dpp->nome }}</p>@endif
                                @if ($proxima->data_da_palestra)
                                    <p class="font-mono text-xs text-gold">{{ $proxima->data_da_palestra->translatedFormat('d \d\e M \d\e Y') }} · {{ $proxima->data_da_palestra->format('H\hi') }}</p>
                                @endif
                            </div>
                        </div>
                        <h3 class="mt-3 font-display font-semibold">{{ $proxima->titulo }}</h3>
                        <a href="{{ route('palestras.show', $proxima->slug) }}"
                           class="mt-4 inline-flex rounded-pill bg-gold px-5 py-2 text-sm font-semibold text-[#3a2f00] transition hover:brightness-105">Ver palestra</a>
                    </div>
                @endif
            </aside>
        </div>
    </section>
</x-layout.app>
