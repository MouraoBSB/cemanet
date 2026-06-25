@php
    $palestrantes = $palestra->palestrantesAtivos;
    $data = $palestra->data_da_palestra;
    $heroStyle = $palestra->cor_fundo ? 'background:'.$palestra->cor_fundo : null;
    // extrai o ID do YouTube de formatos comuns (watch?v=, youtu.be/, live/, embed/)
    $ytId = null;
    if ($palestra->link_youtube && preg_match('~(?:v=|youtu\.be/|live/|embed/)([A-Za-z0-9_-]{6,})~', $palestra->link_youtube, $m)) {
        $ytId = $m[1];
    }
    $jsonLd = json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'Event',
        'name' => $palestra->titulo,
        'startDate' => optional($data)->toIso8601String(),
        'eventAttendanceMode' => $palestra->online
            ? 'https://schema.org/OnlineEventAttendanceMode'
            : 'https://schema.org/OfflineEventAttendanceMode',
        'eventStatus' => 'https://schema.org/EventScheduled',
        'location' => [
            '@type' => 'Place',
            'name' => 'Centro Espírita Maria Madalena',
            'address' => 'Quadra 02, Lote 16, Vila Vicentina, Planaltina, DF',
        ],
        'performer' => $palestrantes->map(fn ($p) => ['@type' => 'Person', 'name' => $p->nome])->all(),
        'organizer' => ['@type' => 'Organization', 'name' => 'CEMA'],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
@endphp

<x-layout.app :title="$palestra->titulo" :description="$palestra->subtitulo ?? $palestra->resumo">
    <x-slot:head>
        <script type="application/ld+json">{!! $jsonLd !!}</script>
    </x-slot:head>

    {{-- S1: Hero (cor_fundo da palestra quando houver; senão, gradiente roxo) --}}
    <section class="relative overflow-hidden text-white" @if($heroStyle) style="{{ $heroStyle }}" @endif>
        @unless ($heroStyle)
            <div class="absolute inset-0 bg-gradient-to-br from-primary to-footer-bg"></div>
        @endunless
        <div class="relative mx-auto max-w-[1100px] px-6 py-16">
            <nav aria-label="Você está em" class="mb-5 flex flex-wrap items-center gap-2 text-xs text-white/70">
                <a href="{{ route('home') }}" class="hover:text-white">Início</a><span aria-hidden="true">›</span>
                <a href="{{ route('palestras.index') }}" class="hover:text-white">Palestras Públicas</a><span aria-hidden="true">›</span>
                <span class="text-gold" aria-current="page">{{ \Illuminate\Support\Str::limit($palestra->titulo, 40) }}</span>
            </nav>
            <p class="font-mono text-xs uppercase tracking-[0.14em] text-white/60">Palestra Pública</p>
            <h1 class="mt-2 max-w-3xl font-display text-3xl font-semibold leading-tight md:text-5xl">{{ $palestra->titulo }}</h1>
            @if ($palestra->subtitulo)
                <p class="mt-3 max-w-2xl text-lg text-white/85">{{ $palestra->subtitulo }}</p>
            @endif
        </div>
    </section>

    {{-- S2: Barra de ações (markup; comportamento JS na Task 6) --}}
    <section class="border-b border-border-muted bg-white" data-acoes-palestra>
        <div class="mx-auto flex max-w-[1100px] flex-wrap items-center gap-2.5 px-6 py-4">
            <span class="text-sm text-text-muted">Compartilhar:</span>
            {{-- preenchido na Task 6 --}}
        </div>
    </section>

    {{-- S3: Conteúdo (grid 2 colunas) --}}
    <section class="mx-auto max-w-[1100px] px-6 py-12">
        <div class="grid items-start gap-9 desktop-sm:grid-cols-[300px_1fr]">
            {{-- Coluna esquerda: palestrante(s) --}}
            <aside class="space-y-5">
                @forelse ($palestrantes as $p)
                    <div class="overflow-hidden rounded-xl border border-border-muted bg-cream">
                        @if ($p->foto)
                            <img src="{{ asset('storage/'.$p->foto) }}" alt="{{ $p->nome }}"
                                 loading="lazy" width="300" height="230" class="h-[230px] w-full object-cover">
                        @endif
                        <div class="p-5">
                            <p class="font-mono text-[11px] uppercase tracking-[0.1em] text-accent">Palestrante</p>
                            <h2 class="mt-1 font-display text-xl font-semibold text-primary">{{ $p->nome }}</h2>
                            @if ($p->bio)
                                <div class="mt-2 line-clamp-4 text-sm text-text-secondary">{!! \Illuminate\Support\Str::limit(strip_tags($p->bio), 220) !!}</div>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-text-muted">Palestrante a confirmar.</p>
                @endforelse
            </aside>

            {{-- Coluna direita --}}
            <div>
                @if ($ytId)
                    <div class="mb-7 overflow-hidden rounded-2xl bg-black">
                        <iframe class="aspect-video w-full" src="https://www.youtube.com/embed/{{ $ytId }}"
                                title="Vídeo: {{ $palestra->titulo }}" loading="lazy"
                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe>
                    </div>
                @endif

                {{-- Data + Modalidade --}}
                <div class="mb-7 flex flex-wrap gap-3.5">
                    <div class="min-w-[170px] flex-1 rounded-xl border border-border-muted bg-white p-4">
                        <p class="font-mono text-[10.5px] uppercase tracking-[0.1em] text-text-muted">Data</p>
                        <p class="mt-1 font-semibold text-text-ink">{{ $data ? $data->translatedFormat('l, d \d\e F \d\e Y · H\hi') : 'A confirmar' }}</p>
                    </div>
                    <div class="min-w-[170px] flex-1 rounded-xl border border-border-muted bg-white p-4">
                        <p class="font-mono text-[10.5px] uppercase tracking-[0.1em] text-text-muted">Modalidade</p>
                        <p class="mt-1 font-semibold text-text-ink">{{ $palestra->online ? 'Online' : 'Presencial' }}</p>
                    </div>
                </div>

                {{-- Descrição --}}
                @if ($palestra->descricao)
                    <div class="mb-8 max-w-none text-text-secondary [&_p]:mb-4 [&_p]:leading-relaxed [&_a]:text-secondary [&_a]:underline">
                        {!! $palestra->descricao !!}
                    </div>
                @endif

                {{-- Acordeão de destaques --}}
                @if ($palestra->destaques->isNotEmpty())
                    <h2 class="mb-4 font-display text-2xl font-semibold text-primary">Principais tópicos abordados</h2>
                    <div class="flex flex-col gap-2.5">
                        @foreach ($palestra->destaques as $d)
                            <details class="group overflow-hidden rounded-xl border border-border-muted bg-white">
                                <summary class="flex cursor-pointer items-center justify-between gap-4 px-5 py-4 font-display font-medium text-text-ink">
                                    {{ $d->destaque }}
                                    <span aria-hidden="true" class="flex size-6 shrink-0 items-center justify-center rounded-full bg-cream text-primary transition group-open:rotate-45">+</span>
                                </summary>
                                @if ($d->texto)
                                    <div class="px-5 pb-5 text-sm text-text-secondary">{{ $d->texto }}</div>
                                @endif
                            </details>
                        @endforeach
                    </div>
                @endif

                {{-- Tags de assunto --}}
                @if ($palestra->assuntos->isNotEmpty())
                    <div class="mt-8">
                        <p class="font-mono text-[11px] uppercase tracking-[0.1em] text-text-muted">Assuntos principais</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            @foreach ($palestra->assuntos as $a)
                                <a href="{{ route('palestras.index', ['assunto' => $a->slug]) }}"
                                   class="rounded-pill border border-border bg-surface px-3.5 py-1.5 text-[13px] text-text-secondary hover:border-primary hover:text-primary">{{ $a->nome }}</a>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </section>

    {{-- S4: Navegação anterior/próxima --}}
    <section class="border-y border-border-muted bg-surface">
        <div class="mx-auto flex max-w-[1100px] flex-wrap justify-between gap-4 px-6 py-6">
            @if ($anterior)
                <a href="{{ route('palestras.show', $anterior->slug) }}" rel="prev" class="flex items-center gap-3 text-primary hover:underline">
                    <span aria-hidden="true" class="text-xl">‹</span>
                    <span>
                        <span class="block font-mono text-[10px] uppercase text-text-muted">Anterior</span>
                        <span class="font-semibold">{{ \Illuminate\Support\Str::limit($anterior->titulo, 38) }}</span>
                    </span>
                </a>
            @else <span></span> @endif

            @if ($proxima)
                <a href="{{ route('palestras.show', $proxima->slug) }}" rel="next" class="flex items-center gap-3 text-right text-primary hover:underline">
                    <span>
                        <span class="block font-mono text-[10px] uppercase text-text-muted">Próxima</span>
                        <span class="font-semibold">{{ \Illuminate\Support\Str::limit($proxima->titulo, 38) }}</span>
                    </span>
                    <span aria-hidden="true" class="text-xl">›</span>
                </a>
            @endif
        </div>
    </section>
</x-layout.app>
