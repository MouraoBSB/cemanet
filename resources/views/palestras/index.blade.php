<x-layout.app title="Palestras Públicas" description="Palestras públicas do Centro Espírita Maria Madalena (CEMA): reflexões à luz do Espiritismo, abertas a todos.">
    @php
        $breadcrumbJsonLd = [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'Início', 'item' => url('/')],
                ['@type' => 'ListItem', 'position' => 2, 'name' => 'Palestras', 'item' => url('/palestra_publica')],
                ['@type' => 'ListItem', 'position' => 3, 'name' => 'Palestras Públicas'],
            ],
        ];
    @endphp
    <x-slot:head>
        <script type="application/ld+json">
            @json($breadcrumbJsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        </script>
    </x-slot:head>

    {{-- Hero --}}
    <section class="relative overflow-hidden bg-gradient-to-br from-[#0b1030] to-footer-bg text-white">
        <div class="cema-archive-particles" aria-hidden="true"></div>
        <div class="relative mx-auto flex max-w-[1240px] flex-col gap-8 px-6 py-16 desktop-sm:flex-row desktop-sm:items-center desktop-sm:justify-between">
            <div>
                <p class="font-mono text-xs uppercase tracking-[0.14em] text-[#9db8e0]">Centro Espírita Maria Madalena</p>
                <h1 class="mt-3 font-display text-4xl font-semibold sm:text-5xl">Palestras Públicas</h1>
                <div class="mt-4 h-1 w-16 rounded-full bg-gold"></div>
                <p class="mt-4 max-w-xl font-light text-[#d7def0]">Reflexões à luz do Espiritismo, abertas a todos — todos os domingos, às 19h, presencialmente e ao vivo pelo nosso canal.</p>
            </div>
            <a href="{{ route('calendario.index', ['tipo' => 'palestras']) }}"
               class="flex items-center gap-3 rounded-2xl border border-white/20 bg-white/10 px-5 py-4 transition hover:bg-white/15">
                <span class="text-2xl text-gold" aria-hidden="true">📅</span>
                <span class="font-display font-semibold">Calendário de Palestras</span>
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
            <span class="text-text-secondary">Palestras Públicas</span>
        </div>
    </nav>

    {{-- Destaque: Próxima palestra --}}
    @if ($proxima)
        @php($pp = $proxima->palestrantesAtivos->first())
        @php($ptema = $proxima->assuntos->first())
        <section class="mx-auto max-w-[1240px] px-6 pt-12" aria-label="Próxima palestra">
            <p class="mb-3 inline-flex items-center gap-2 font-display text-base font-semibold text-primary">
                <span class="inline-block size-2.5 animate-pulse rounded-full bg-gold" aria-hidden="true"></span> Próxima palestra
            </p>
            <div class="relative overflow-hidden rounded-[18px] bg-gradient-to-r from-[#3a3266] via-primary to-[#5b4f92] p-6 text-white sm:p-8">
                <span aria-hidden="true" class="pointer-events-none absolute -top-[40px] -right-[30px] size-[180px] rounded-full bg-gold/[0.14]"></span>
                <span aria-hidden="true" class="pointer-events-none absolute -bottom-[60px] right-[120px] size-[150px] rounded-full bg-secondary/[0.16]"></span>
                <div class="relative flex flex-col items-center gap-6 sm:flex-row sm:gap-7">
                    <span class="flex size-24 shrink-0 items-center justify-center overflow-hidden rounded-full bg-white/15 ring-4 ring-white/20">
                        @if ($pp?->foto_thumb_url)
                            <img src="{{ $pp->foto_thumb_url }}" alt="{{ $pp->nome }}" width="96" height="96" class="size-full object-cover">
                        @elseif ($pp)
                            <span class="font-display text-2xl font-semibold">{{ collect(explode(' ', $pp->nome))->take(2)->map(fn ($n) => mb_substr($n, 0, 1))->implode('') }}</span>
                        @endif
                    </span>
                    <div class="flex-1 text-center sm:text-left">
                        @if ($proxima->data_da_palestra)
                            <span class="inline-flex items-center gap-1.5 rounded-pill bg-gold px-3 py-1 font-mono text-xs font-semibold text-[#3a2f00]">
                                {{ $proxima->data_da_palestra->translatedFormat('d \d\e F \d\e Y') }} · {{ $proxima->data_da_palestra->format('H\hi') }}
                            </span>
                        @endif
                        <h2 class="mt-3 font-display text-2xl font-semibold">{{ $proxima->titulo }}</h2>
                        @if ($pp || $ptema)
                            <p class="mt-1 text-white/80">@if ($pp)com {{ $pp->nome }}@endif@if ($pp && $ptema) · @endif@if ($ptema){{ $ptema->nome }}@endif</p>
                        @endif
                        <div class="mt-4 flex justify-center sm:justify-start">
                            <x-ui.countdown :data="$proxima->data_da_palestra" />
                        </div>
                    </div>
                    <a href="{{ route('palestras.show', $proxima->slug) }}"
                       class="shrink-0 rounded-pill bg-white px-6 py-3 font-semibold text-primary transition hover:bg-cream">Ver palestra</a>
                </div>
            </div>
        </section>
    @endif

    {{-- Listagem --}}
    <section class="mx-auto max-w-[1240px] px-6 py-12">
        {{-- #[Url] lê q/assunto da query string no load inicial; a busca do header (GET ?q=) cai aqui. --}}
        <livewire:palestras.lista />
    </section>

    {{-- Veja também --}}
    <section class="mx-auto max-w-[1240px] px-6 pb-16">
        <div class="border-t border-border-muted pt-8">
            <h2 class="font-display text-lg font-semibold text-primary">Veja também</h2>
            <div class="mt-4 flex flex-wrap gap-3">
                @foreach ([['Palestrantes', route('palestrantes.index')], ['Calendário de Palestras', route('calendario.index', ['tipo' => 'palestras'])], ['Blog Sementeira de Luz', route('blog.index')]] as [$rotulo, $url])
                    <a href="{{ $url }}" class="inline-flex items-center gap-2 rounded-pill border border-border-muted bg-white px-5 py-2.5 text-sm text-[#3a3553] transition hover:border-primary">
                        <span class="size-2 rounded-full bg-accent" aria-hidden="true"></span>{{ $rotulo }}
                    </a>
                @endforeach
            </div>
        </div>
    </section>
</x-layout.app>
