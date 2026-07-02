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
    <section class="agenda-hero relative overflow-hidden text-white">
        <x-ui.particulas />
        <div class="relative mx-auto flex max-w-[1240px] flex-wrap items-center gap-12 px-6 py-16">
            <div class="min-w-[280px] flex-1">
                <p class="font-mono text-xs uppercase tracking-[0.18em] text-[#9db8e0]">Editora Auta de Sousa · Projeto CEMA</p>
                <h1 class="mt-3 font-display text-4xl font-semibold sm:text-5xl">Agenda Reforma Íntima</h1>
                <div class="mt-4 h-1 w-16 rounded-full bg-gold"></div>
                <p class="mt-4 max-w-xl font-sans font-light text-white/85">Transforme-se a cada dia: reflexões do Evangelho, metas espirituais e uma sugestão de prece para a sua jornada de renovação interior.</p>
            </div>
            @if ($capaAgenda)
                <div class="relative mx-auto shrink-0">
                    <div class="absolute inset-0 rounded-full" style="background:radial-gradient(circle, rgba(122,170,225,0.55), transparent 68%); filter:blur(14px);" aria-hidden="true"></div>
                    {{-- drop-shadow (não box-shadow) segue o canal alfa do PNG: sombra na silhueta do livro, sem borda retangular. --}}
                    <img src="{{ $capaAgenda }}" alt="Agenda Reforma Íntima" class="agenda-capa relative block w-[230px] max-w-[54vw]" style="filter: drop-shadow(0 22px 30px rgba(0,0,0,0.45));">
                </div>
            @endif
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
        <div class="mx-auto max-w-[980px] px-6 py-14">
            <p class="font-mono text-xs uppercase tracking-[0.16em] text-[#9a8c5e]">Sobre o projeto</p>
            <h2 class="mt-2 font-display text-2xl font-semibold text-primary">Um caminho diário de renovação interior</h2>
            <p class="mt-4 text-[15px] leading-[1.8] text-text-secondary">A <strong>Agenda Reforma Íntima</strong> é um projeto desenvolvido pela <strong>Editora Auta de Sousa</strong>, com o propósito de incentivar o hábito da transformação moral e espiritual no dia a dia. Inspirada nos ensinamentos do Evangelho e nas orientações de obras espíritas como <em>O Evangelho Segundo o Espiritismo</em>, <em>O Livro dos Espíritos</em> e autores como Emmanuel e André Luiz, a Agenda oferece uma abordagem prática para o progresso íntimo.</p>
            <p class="mt-3 text-[15px] leading-[1.8] text-text-secondary">A proposta é simples, mas transformadora: estimular reflexões diárias e práticas constantes que conduzam à reforma íntima, ajudando na construção de virtudes como justiça, amor, caridade, gratidão e responsabilidade.</p>
            <div class="mt-8 grid gap-4" style="grid-template-columns:repeat(auto-fit,minmax(220px,1fr));">
                @foreach ([
                    ['Tema para Reflexão e Vivência', 'Um pequeno trecho do Evangelho de Jesus, retirado do Novo Testamento, para ser meditado e vivenciado ao longo do dia.'],
                    ['Meta do Mês e do Dia', 'Uma diretriz ou exercício prático baseado em orientações de Benfeitores Espirituais, auxiliando no desenvolvimento de hábitos virtuosos.'],
                    ['Prece Diária', 'Uma oração para promover sintonia com as forças do bem, fortalecendo a conexão com as Esferas Superiores e a busca pela edificação espiritual.'],
                ] as [$ct, $cd])
                    <div class="rounded-[14px] border border-[#E8E2D0] bg-white p-5">
                        <h3 class="font-display text-[15px] font-semibold text-primary">{{ $ct }}</h3>
                        <p class="mt-2 text-[13.5px] leading-relaxed text-text-secondary">{{ $cd }}</p>
                    </div>
                @endforeach
            </div>
            <blockquote class="mt-8 border-l-4 border-gold pl-4">
                <p class="font-serif text-[15px] italic text-[#3a3553]">"Todas as conquistas do espírito se efetuam na base de lições recapituladas."</p>
                <cite class="mt-1 block text-[13px] not-italic text-text-muted">— Emmanuel</cite>
            </blockquote>
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
