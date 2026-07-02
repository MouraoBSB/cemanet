{{-- Agenda Reforma Íntima — casca SSR (card do dia + calendário navegável). --}}
@php
    // Esta casca é a DONA ÚNICA do <x-slot:head> de SEO da Agenda (canonical + noindex + JSON-LD).
    // A Task 13 apenas adiciona um teste de regressão (AgendaSeoTest); NÃO reescreve este bloco.
    $tituloPagina = 'Agenda Reforma Íntima — '.$dataAtual->format('d/m/Y');
    $urlCanonica = $ehUrlNua ? route('agenda.index') : route('agenda.show', $dataAtual->format('Y-m-d'));

    $org = ['@type' => 'Organization', 'name' => 'Centro Espírita Maria Madalena'];
    $graph = [];
    if ($temConteudo) {
        $graph[] = array_filter([
            '@type' => 'Article',
            'headline' => $tituloPagina,
            'datePublished' => $dia->data->toIso8601String(),
            'dateModified' => $dia->updated_at?->toIso8601String(),
            'articleBody' => trim(strip_tags((string) $dia->reflexao)) ?: null,
            'inLanguage' => 'pt-BR',
            'author' => $org,
            'publisher' => $org,
            'mainEntityOfPage' => $urlCanonica,
            'description' => $dia->descricaoSeo() ?: null,
        ], fn ($v) => $v !== null);
    }
    // BreadcrumbList SEMPRE (inclusive no dia vazio — nome null-safe).
    $graph[] = [
        '@type' => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Início', 'item' => route('home')],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'Agenda Reforma Íntima', 'item' => route('agenda.index')],
            ['@type' => 'ListItem', 'position' => 3, 'name' => $dia?->tituloExtenso() ?? $dataAtual->format('d/m/Y'), 'item' => $urlCanonica],
        ],
    ];
    $jsonLd = json_encode(
        ['@context' => 'https://schema.org', '@graph' => $graph],
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG
    );
@endphp

<x-layout.app :title="$tituloPagina" :description="$dia?->descricaoSeo()">
    <x-slot:head>
        {{-- DONO ÚNICO do <head> de SEO da Agenda. --}}
        <link rel="canonical" href="{{ $urlCanonica }}">
        @unless ($temConteudo)
            <meta name="robots" content="noindex">
        @endunless
        {{-- Sem og:type/og:url aqui: o layout já emite og:type=website + og:url. --}}
        <script type="application/ld+json">{!! $jsonLd !!}</script>
    </x-slot:head>

    {{-- Hero --}}
    <section class="relative overflow-hidden bg-gradient-to-br from-primary to-footer-bg text-white">
        <x-ui.particulas />
        <div class="relative mx-auto max-w-[1240px] px-6 py-16">
            <p class="font-mono text-xs uppercase tracking-[0.14em] text-gold">Devocional diário</p>
            <h1 class="mt-3 font-display text-4xl font-semibold sm:text-5xl">Agenda Reforma Íntima</h1>
            <div class="mt-4 h-1 w-16 rounded-full bg-gold"></div>
            <p class="mt-4 max-w-xl font-light text-[#d7def0]">Uma reflexão à luz do Evangelho para cada dia — com meta do mês, meta do dia e sugestão de prece.</p>
        </div>
    </section>

    {{-- Breadcrumb --}}
    <nav aria-label="Trilha de navegação" class="border-b border-border-muted bg-surface">
        <div class="mx-auto max-w-[1240px] px-6 py-2.5 text-[13px] text-text-muted">
            <a href="{{ url('/') }}" class="hover:text-primary">Início</a>
            <span aria-hidden="true"> › </span>
            <span class="text-text-secondary" aria-current="page">Agenda Reforma Íntima</span>
        </div>
    </nav>

    {{-- Card do dia + calendário --}}
    <section class="bg-surface">
        <div class="mx-auto grid max-w-[1240px] gap-8 px-6 py-12 desktop-sm:grid-cols-[minmax(0,1fr)_340px]">
            <div class="min-w-0">
                @if ($temConteudo)
                    @include('agenda._dia')
                @else
                    <div class="agenda-vazio rounded-2xl border border-dashed border-border-muted bg-white px-6 py-16 text-center">
                        <p class="text-4xl" aria-hidden="true">🕊️</p>
                        <p class="mt-3 text-lg font-semibold text-text-secondary">Não há reflexão publicada para {{ \Illuminate\Support\Str::ucfirst($dataAtual->translatedFormat('l, d \d\e F \d\e Y')) }}.</p>
                        <a href="{{ route('agenda.index') }}" wire:navigate
                           class="mt-4 inline-flex rounded-pill bg-primary px-5 py-2.5 text-sm font-semibold text-white transition hover:opacity-90">Voltar para hoje</a>
                    </div>
                @endif
            </div>
            <aside class="min-w-0">
                @include('agenda._calendario')
            </aside>
        </div>
    </section>

    {{-- Sobre o projeto --}}
    <section class="bg-cream">
        <div class="mx-auto max-w-[1240px] px-6 py-12">
            <h2 class="font-display text-lg font-semibold text-primary">Sobre a Agenda Reforma Íntima</h2>
            <p class="mt-3 max-w-3xl font-serif text-[15px] leading-[1.8] text-[#3a3553]">A Agenda Reforma Íntima é um devocional diário editado pela Editora Auta de Sousa. A cada data, uma reflexão à luz do Evangelho, uma meta do mês, uma meta do dia e uma sugestão de prece — um roteiro simples para o trabalho de autotransformação moral.</p>
        </div>
    </section>

    {{-- Veja também --}}
    <section class="mx-auto max-w-[1240px] px-6 pb-16 pt-12">
        <div class="border-t border-border-muted pt-8">
            <h2 class="font-display text-lg font-semibold text-primary">Veja também</h2>
            <div class="mt-4 flex flex-wrap gap-3">
                @foreach ([['Palestras Públicas', route('palestras.index')], ['Calendário de Palestras', route('palestras.calendario')], ['Blog Sementeira de Luz', route('blog.index')]] as [$rotulo, $url])
                    <a href="{{ $url }}" class="inline-flex items-center gap-2 rounded-pill border border-border-muted bg-white px-5 py-2.5 text-sm text-[#3a3553] transition hover:border-primary">
                        <span class="size-2 rounded-full bg-accent" aria-hidden="true"></span>{{ $rotulo }}
                    </a>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Fuso do visitante (só na URL nua): navega para a URL datada local, sem trocar o conteúdo da nua. --}}
    @if ($ehUrlNua)
        <script>
            (function () {
                var local = new Date().toLocaleDateString('en-CA'); // 'AAAA-MM-DD' no fuso do navegador
                var brasilia = @json($hojeBrasilia->format('Y-m-d'));
                if (local !== brasilia) {
                    location.replace(@json(url('/agenda-reforma-intima')) + '/' + local);
                }
            })();
        </script>
    @endif
</x-layout.app>
