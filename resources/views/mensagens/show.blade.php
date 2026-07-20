@php
    $url = route('mensagens.show', $mensagem->slug);
    $ogImg = $mensagem->getFirstMediaUrl(\App\Models\Mensagem::COLECAO_PICTOGRAFIA, 'web') ?: null;
    $textoCopia = trim($mensagem->titulo."\n\n".strip_tags((string) $mensagem->corpo));
@endphp
<x-layout.app :title="$mensagem->titulo"
              :description="\Illuminate\Support\Str::limit(strip_tags($mensagem->contexto ?: $mensagem->corpo), 155)">
    <x-slot:head>
        <link rel="canonical" href="{{ $url }}">
        @if ($mensagem->visibilidade() === \App\Enums\VisibilidadeMensagem::Publico)
            @if ($ogImg)<meta property="og:image" content="{{ $ogImg }}">@endif
            <script type="application/ld+json">
            @php echo json_encode(array_filter([
                '@context' => 'https://schema.org', '@type' => 'CreativeWork',
                'name' => $mensagem->titulo, 'url' => $url,
                'datePublished' => $mensagem->data_recebimento?->toDateString(),
                'author' => $mensagem->autores->pluck('nome')->all() ?: null,
            ], fn ($v) => $v !== null && $v !== []), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG); @endphp
            </script>
        @else
            {{-- Restrito (ou nivel=null): fora do índice; sem preview social do conteúdo reservado. --}}
            <meta name="robots" content="noindex, nofollow">
        @endif
    </x-slot:head>

    <div x-data="mensagemLeitura({ titulo: @js($mensagem->titulo), textoCopia: @js($textoCopia), url: @js($url) })"
         :style="'--tamanho-prosa:' + tamanhoAtual + 'px'"
         @scroll.window.passive="atualizarProgresso()">

        {{-- Barra de progresso de leitura (respeita prefers-reduced-motion no JS: fica em 0). --}}
        <div class="fixed inset-x-0 top-0 z-[60] h-[3px]" aria-hidden="true">
            <div class="h-full bg-gradient-to-r from-gold to-[#e1900f]" :style="'width:' + progresso + '%'"></div>
        </div>

        {{-- Hero --}}
        <section class="relative overflow-hidden text-white"
                 style="background:radial-gradient(circle at 82% 18%, rgba(110,159,203,0.38), transparent 55%), linear-gradient(135deg,#0b1030 0%,#1a1f4a 48%,#2c2f64 100%);">
            <x-ui.particulas />
            <div class="relative z-[2] mx-auto max-w-[1100px] px-6 pb-14 pt-9">
                <nav aria-label="Trilha de navegação" class="text-[13px] text-[#c7d0ea]">
                    <a href="{{ url('/') }}" class="transition hover:text-white">Início</a>
                    <span aria-hidden="true"> › </span>
                    <a href="{{ route('mensagens.index') }}" class="transition hover:text-white">Mensagens Mediúnicas</a>
                    <span aria-hidden="true"> › </span>
                    <span class="text-white/90" aria-current="page">{{ \Illuminate\Support\Str::limit($mensagem->titulo, 40) }}</span>
                </nav>

                <div class="mt-7 flex flex-wrap items-center gap-3">
                    <p class="font-mono text-[11.5px] uppercase tracking-[0.16em] text-[#9db8e0]">Mensagem Mediúnica · {{ $mensagem->formato?->rotulo() }}</p>
                    @auth
                        <x-mensagem.selo-nivel :visibilidade="$mensagem->visibilidade()" />
                    @endauth
                </div>

                <h1 class="mt-3.5 max-w-3xl font-display text-4xl font-semibold leading-[1.1] text-balance sm:text-5xl">{{ $mensagem->titulo }}</h1>

                <div class="mt-7 flex flex-wrap items-center gap-2.5">
                    @forelse ($mensagem->autores as $a)
                        <a href="{{ route('autores.show', $a->slug) }}"
                           class="group inline-flex items-center gap-2 rounded-pill border border-white/18 bg-white/10 py-1.5 pl-1.5 pr-4 text-[13px] transition hover:bg-white/18">
                            @if ($a->foto_thumb_url)
                                <img src="{{ $a->foto_thumb_url }}" alt="" class="size-7 rounded-full object-cover">
                            @else
                                <span class="grid size-7 shrink-0 place-items-center rounded-full bg-gradient-to-br from-gold to-[#d98a14] font-display text-[10px] font-semibold text-[#3a3266]" aria-hidden="true">{{ $a->iniciais }}</span>
                            @endif
                            <span>por <span class="font-medium group-hover:underline">{{ $a->nome }}</span></span>
                        </a>
                    @empty
                        <span class="inline-flex items-center gap-2 rounded-pill border border-white/18 bg-white/10 px-4 py-2 text-[13px] italic text-white/75">Sem assinatura</span>
                    @endforelse

                    @if ($mensagem->data_recebimento)
                        <span class="inline-flex items-center gap-2 rounded-pill border border-white/18 bg-white/10 px-4 py-2 text-[13px] text-white/85">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#f2a81e" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                            Recebida em {{ $mensagem->data_recebimento->translatedFormat('j \d\e F \d\e Y') }}
                        </span>
                    @endif

                    <span class="inline-flex items-center rounded-pill border border-white/18 bg-white/10 px-4 py-2 font-mono text-[10.5px] uppercase tracking-[0.08em] text-white/80">{{ $mensagem->formato?->rotulo() }}</span>
                    <span class="font-mono text-[11px] uppercase tracking-[0.14em] text-white/55">CEMA</span>
                </div>
            </div>
        </section>

        {{-- Faixa de contexto (I4: sempre {{ }} — texto puro escapado). --}}
        @if (filled($mensagem->contexto))
            <section class="border-b border-border-muted bg-[#FAF8F2]">
                <div class="mx-auto flex max-w-[1100px] items-start gap-3.5 px-6 py-5">
                    <span class="mt-2.5 h-[3px] w-[22px] shrink-0 rounded-full bg-gold" aria-hidden="true"></span>
                    <p class="text-[14.5px] leading-relaxed text-text-secondary"><strong class="font-semibold text-primary">Contexto</strong> — {{ $mensagem->contexto }}</p>
                </div>
            </section>
        @endif

        {{-- Nota "Direcionada a você": só ao destinatário (calculado no controller); SEM lista de destinatários (F2). --}}
        @if ($ehDestinatario ?? false)
            <section class="border-b border-[#ECE6D6] bg-[#FAF8F2]">
                <div class="mx-auto flex max-w-[1100px] items-start gap-3.5 px-6 py-5">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#c19532" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mt-0.5 shrink-0" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    <p class="text-[14.5px] leading-relaxed text-text-secondary"><strong class="font-semibold text-primary">Direcionada a você</strong> — esta mensagem foi endereçada pessoalmente a você nas reuniões mediúnicas da Casa.</p>
                </div>
            </section>
        @endif

        {{-- Grid: corpo + sidebar (empilha abaixo de desktop-sm). --}}
        <section class="bg-surface">
            <div class="mx-auto grid max-w-[1100px] grid-cols-1 gap-8 px-6 py-10 desktop-sm:grid-cols-[minmax(0,1fr)_320px] desktop-sm:items-start">
                <div class="min-w-0">
                    {{-- Card da mensagem: toolbar + corpo por formato --}}
                    <article class="overflow-hidden rounded-2xl border border-border-muted bg-white shadow-card">
                        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-border-muted bg-[#FCFBFE] px-5 py-3.5 sm:px-7">
                            <div class="flex items-center gap-2.5">
                                <span class="h-[3px] w-6 rounded-full bg-gold" aria-hidden="true"></span>
                                <span class="font-display text-[15px] font-semibold text-text-ink">Mensagem</span>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <div class="inline-flex items-center overflow-hidden rounded-pill border border-border bg-white" role="group" aria-label="Tamanho do texto">
                                    <button type="button" @click="diminuir()" :disabled="passo === 0" aria-label="Diminuir o tamanho do texto"
                                            class="px-3 py-1.5 text-[13px] font-medium text-text-secondary transition hover:bg-surface disabled:opacity-40">A−</button>
                                    <span class="h-4 w-px bg-border" aria-hidden="true"></span>
                                    <button type="button" @click="aumentar()" :disabled="passo === tamanhos.length - 1" aria-label="Aumentar o tamanho do texto"
                                            class="px-3 py-1.5 text-[15px] font-semibold text-text-secondary transition hover:bg-surface disabled:opacity-40">A+</button>
                                </div>
                                <button type="button" @click="copiar()"
                                        class="inline-flex items-center gap-1.5 rounded-pill border border-border bg-white px-3.5 py-2 text-[13px] font-medium text-text-secondary transition hover:border-primary hover:text-primary">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                                    Copiar
                                </button>
                                <button type="button" @click="compartilhar()"
                                        class="inline-flex items-center gap-1.5 rounded-pill bg-primary px-4 py-2 text-[13px] font-medium text-white transition hover:bg-[#3f3670]">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.6" y1="13.5" x2="15.4" y2="17.5"/><line x1="15.4" y1="6.5" x2="8.6" y2="10.5"/></svg>
                                    Compartilhar
                                </button>
                            </div>
                        </div>

                        <div class="px-5 py-9 sm:px-10">
                            <div class="mx-auto max-w-[640px]">
                                @switch($mensagem->formato)
                                    @case(\App\Enums\FormatoMensagem::Psicografia) @include('mensagens.corpos.psicografia') @break
                                    @case(\App\Enums\FormatoMensagem::Psicofonia)  @include('mensagens.corpos.psicofonia')  @break
                                    @case(\App\Enums\FormatoMensagem::Pictografia) @include('mensagens.corpos.pictografia') @break
                                @endswitch
                            </div>
                        </div>
                    </article>

                    {{-- Card(s) do(s) autor(es) (N:N). "Sem assinatura" já consta no hero/assinatura. --}}
                    @foreach ($mensagem->autores as $a)
                        <div class="mt-6 flex flex-col gap-4 rounded-2xl border border-border-muted bg-white p-6 shadow-card sm:flex-row sm:items-start">
                            @if ($a->foto_url)
                                <img src="{{ $a->foto_url }}" alt="" class="size-[72px] shrink-0 rounded-full object-cover">
                            @else
                                <span class="grid size-[72px] shrink-0 place-items-center rounded-full bg-gradient-to-br from-gold to-[#d98a14] font-display text-[22px] font-semibold text-[#3a3266]" aria-hidden="true">{{ $a->iniciais }}</span>
                            @endif
                            <div class="min-w-0">
                                <p class="font-mono text-[10.5px] uppercase tracking-[0.1em] text-text-muted">Autor Espiritual</p>
                                <p class="mt-1 font-display text-lg font-semibold text-text-ink">{{ $a->nome }}</p>
                                @if (filled($a->bio))
                                    <p class="mt-1.5 text-[13.5px] leading-relaxed text-text-secondary">{{ \Illuminate\Support\Str::limit(strip_tags($a->bio), 180) }}</p>
                                @endif
                                <a href="{{ route('autores.show', $a->slug) }}" class="mt-3 inline-flex items-center gap-1 text-[13.5px] font-medium text-primary transition hover:gap-2">
                                    Ver todas as mensagens de {{ $a->nome }}
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg>
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Sidebar --}}
                <aside class="flex w-full flex-col gap-6 desktop-sm:sticky desktop-sm:top-24">
                    {{-- Anexo Drive (I7: só liberar_download + link_arquivo; C-C: sem metadados). --}}
                    @if ($mensagem->liberar_download && $mensagem->link_arquivo)
                        <div class="rounded-2xl border border-border-muted bg-white p-5 shadow-card">
                            <p class="mb-3 font-mono text-[10.5px] uppercase tracking-[0.1em] text-text-muted">Anexo</p>
                            <a href="{{ $mensagem->link_arquivo }}" target="_blank" rel="noopener noreferrer"
                               class="flex w-full items-center justify-center gap-2 rounded-pill bg-primary px-4 py-3 text-sm font-medium text-white transition hover:bg-[#3f3670]">
                                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                                Baixar arquivo
                            </a>
                        </div>
                    @endif

                    {{-- Recebidas no mesmo dia (só públicas; ícone neutro, sem lida/não-lida). --}}
                    @if ($mesmoDia->isNotEmpty())
                        <div class="rounded-2xl border border-border-muted bg-white p-5 shadow-card">
                            <h2 class="mb-3.5 flex items-center gap-2 font-display text-[15px] font-semibold text-text-ink">
                                <span class="h-[3px] w-5 rounded-full bg-gold" aria-hidden="true"></span>Recebidas no mesmo dia
                            </h2>
                            <ul class="flex flex-col divide-y divide-[#F0EEF4]">
                                @foreach ($mesmoDia as $item)
                                    <li>
                                        <a href="{{ route('mensagens.show', $item->slug) }}" class="group flex gap-3 py-3">
                                            <span class="mt-1 size-2 shrink-0 rounded-full bg-[#8fb4dc]" aria-hidden="true"></span>
                                            <span class="min-w-0">
                                                <span class="block truncate text-[13.5px] font-medium text-text-ink group-hover:text-primary">{{ $item->titulo }}</span>
                                                <span class="mt-0.5 block font-mono text-[10.5px] uppercase tracking-[0.05em] text-text-muted">{{ $item->formato?->rotulo() }}</span>
                                            </span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    {{-- Relacionadas (curadas; controller já filtrou por publica()). --}}
                    @if ($relacionadas->isNotEmpty())
                        <div class="rounded-2xl border border-border-muted bg-white p-5 shadow-card">
                            <h2 class="mb-3.5 flex items-center gap-2 font-display text-[15px] font-semibold text-text-ink">
                                <span class="h-[3px] w-5 rounded-full bg-gold" aria-hidden="true"></span>Relacionadas
                            </h2>
                            <ul class="flex flex-col divide-y divide-[#F0EEF4]">
                                @foreach ($relacionadas as $rel)
                                    <li>
                                        <a href="{{ route('mensagens.show', $rel->slug) }}" class="group flex flex-col gap-1.5 py-3">
                                            <span class="text-[13.5px] font-medium leading-snug text-text-ink group-hover:text-primary">{{ $rel->titulo }}</span>
                                            <span class="flex items-center gap-2">
                                                <x-mensagem.selo-formato :formato="$rel->formato" />
                                                @if ($rel->data_recebimento)
                                                    <time datetime="{{ $rel->data_recebimento->toDateString() }}" class="text-[11px] text-text-muted">{{ $rel->data_recebimento->translatedFormat('d M Y') }}</time>
                                                @endif
                                            </span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </aside>
            </div>
        </section>

        {{-- Toast (feedback de Copiar/Compartilhar). --}}
        <div x-cloak x-show="toastVisivel"
             x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-y-3 opacity-0" x-transition:enter-end="translate-y-0 opacity-100"
             x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
             class="fixed bottom-6 left-1/2 z-[70] -translate-x-1/2 rounded-pill bg-text-ink px-5 py-2.5 text-sm font-medium text-white shadow-elevated" role="status" aria-live="polite">
            <span x-text="toastMsg"></span>
        </div>
    </div>
</x-layout.app>
