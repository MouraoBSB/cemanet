{{-- Thiago Mourão — https://github.com/MouraoBSB — 2026-07-09 --}}
@php
    $tiposFiltro = ['todos' => 'Todos', 'palestras' => 'Palestras', 'eventos' => 'Eventos'];
    $substantivoVazio = match ($tipo) {
        'palestras' => 'Nenhuma palestra',
        'eventos' => 'Nenhum evento',
        default => 'Nenhuma ocorrência',
    };
    $adjetivoVazio = match (true) {
        $modo === 'realizadas' && $tipo === 'eventos' => 'realizado',
        $modo === 'realizadas' => 'realizada',
        $tipo === 'eventos' => 'agendado',
        default => 'agendada',
    };
@endphp
<div>
    {{-- Destaque: próxima ocorrência (sem fallback) --}}
    @if ($proxima)
        @php($ehPalestra = $proxima->tipo === 'palestra')
        <section class="mb-10" aria-label="Próxima ocorrência">
            <p class="mb-3 inline-flex items-center gap-2 font-display text-base font-semibold text-primary">
                <span class="inline-block size-2.5 animate-pulse rounded-full bg-gold" aria-hidden="true"></span> Próxima ocorrência
            </p>
            <div class="relative overflow-hidden rounded-[18px] bg-gradient-to-r from-[#3a3266] via-primary to-[#5b4f92] p-6 text-white sm:p-8">
                <span aria-hidden="true" class="pointer-events-none absolute -top-[40px] -right-[30px] size-[180px] rounded-full bg-gold/[0.14]"></span>
                <span aria-hidden="true" class="pointer-events-none absolute -bottom-[60px] right-[120px] size-[150px] rounded-full bg-secondary/[0.16]"></span>
                <div class="relative flex flex-col items-center gap-6 sm:flex-row sm:gap-7">
                    <span @class([
                        'flex size-[88px] shrink-0 items-center justify-center overflow-hidden ring-4 ring-white/20',
                        'cema-cal-avatar rounded-full' => $ehPalestra,
                        'rounded-2xl bg-white/10' => ! $ehPalestra,
                    ])>
                        @if ($proxima->imagem)
                            <img src="{{ $proxima->imagem }}" alt="{{ $proxima->titulo }}" width="88" height="88" class="size-full object-cover">
                        @elseif ($ehPalestra)
                            <span class="font-display text-2xl font-semibold text-[#3a2f00]">{{ $proxima->iniciais ?? 'CEMA' }}</span>
                        @else
                            <span class="text-4xl" aria-hidden="true">🗓️</span>
                        @endif
                    </span>
                    <div class="flex-1 text-center sm:text-left">
                        <div class="flex flex-wrap items-center justify-center gap-2 sm:justify-start">
                            <span class="inline-flex items-center gap-1.5 rounded-pill bg-gold px-3 py-1 font-mono text-xs font-semibold text-[#3a2f00]">
                                {{ $proxima->inicio->translatedFormat('d \d\e M') }} · @if ($proxima->temHora){{ $proxima->inicio->format('H\hi') }}@else dia inteiro @endif
                            </span>
                            <span class="rounded-pill px-3 py-1 text-xs font-semibold" style="background: {{ $proxima->selo['cor'] }}; color: {{ $proxima->selo['cor_texto'] }};">
                                {{ $proxima->selo['rotulo'] }}
                            </span>
                            @if ($proxima->seloVisibilidade)
                                <x-ui.selo-visibilidade :rotulo="$proxima->seloVisibilidade['rotulo']" :cor="$proxima->seloVisibilidade['cor']" />
                            @endif
                        </div>
                        <h3 class="mt-3 font-display text-2xl font-semibold">{{ $proxima->titulo }}</h3>
                        @if ($proxima->subtitulo)
                            <p class="mt-1 text-white/80">{{ $proxima->subtitulo }}</p>
                        @endif
                        <div class="mt-4 flex justify-center sm:justify-start">
                            @if ($proxima->inicio->isPast())
                                {{-- Evento multi-dia já começou: sem contagem regressiva negativa, mostra o selo de status. --}}
                                <span class="inline-flex items-center gap-2 rounded-pill bg-white/15 px-4 py-2 font-display text-sm font-semibold">
                                    <span class="inline-block size-2 animate-pulse rounded-full bg-danger" aria-hidden="true"></span> {{ $proxima->selo['rotulo'] }}
                                </span>
                            @else
                                <x-ui.countdown :data="$proxima->inicio" />
                            @endif
                        </div>
                    </div>
                    <a href="{{ $proxima->url }}"
                       class="shrink-0 rounded-pill bg-white px-6 py-3 font-semibold text-primary transition hover:bg-cream">Ver {{ $ehPalestra ? 'palestra' : 'evento' }}</a>
                </div>
            </div>
        </section>
    @endif

    {{-- Barra de período: tabs + filtro de tipo + assinar + navegação de mês + seletor de ano --}}
    <div class="flex flex-col gap-4 rounded-2xl border border-border-muted bg-white p-4 shadow-card">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-3">
                <div role="tablist" aria-label="Filtrar por período" class="inline-flex rounded-pill bg-surface p-1">
                    <button type="button" role="tab" aria-selected="{{ $modo === 'proximas' ? 'true' : 'false' }}"
                            wire:click="$set('modo', 'proximas')"
                            @class(['rounded-pill px-4 py-1.5 text-sm font-semibold transition', 'bg-primary text-white' => $modo === 'proximas', 'text-text-secondary hover:text-primary' => $modo !== 'proximas'])>
                        Próximas
                    </button>
                    <button type="button" role="tab" aria-selected="{{ $modo === 'realizadas' ? 'true' : 'false' }}"
                            wire:click="$set('modo', 'realizadas')"
                            @class(['rounded-pill px-4 py-1.5 text-sm font-semibold transition', 'bg-primary text-white' => $modo === 'realizadas', 'text-text-secondary hover:text-primary' => $modo !== 'realizadas'])>
                        Realizadas
                    </button>
                </div>

                <div role="group" aria-label="Filtrar por tipo" class="inline-flex rounded-pill bg-surface p-1">
                    @foreach ($tiposFiltro as $valor => $rotulo)
                        <button type="button" wire:key="tipo-{{ $valor }}" wire:click="$set('tipo', '{{ $valor }}')"
                                aria-pressed="{{ $tipo === $valor ? 'true' : 'false' }}"
                                @class(['rounded-pill px-4 py-1.5 text-sm font-semibold transition', 'bg-primary text-white' => $tipo === $valor, 'text-text-secondary hover:text-primary' => $tipo !== $valor])>
                            {{ $rotulo }}
                        </button>
                    @endforeach
                </div>
            </div>

            <button type="button" x-data x-on:click="$dispatch('open-assinar')"
                    class="inline-flex shrink-0 items-center gap-2 rounded-pill border border-border-muted px-4 py-2 text-sm font-semibold text-primary transition hover:border-primary hover:bg-surface">
                <span aria-hidden="true">🔔</span> Assinar calendário
            </button>
        </div>

        @if ($mesFoco)
            <div class="flex flex-wrap items-center justify-center gap-3 border-t border-border-muted pt-4 sm:justify-start">
                <div class="flex items-center gap-1.5">
                    <button type="button" wire:click="mesAnterior" @disabled(! $temAnterior) aria-label="Mês anterior"
                            class="grid size-9 place-items-center rounded-full border border-border-muted text-text-secondary transition hover:border-primary hover:text-primary disabled:cursor-not-allowed disabled:opacity-40">‹</button>
                    <span class="min-w-[9.5rem] text-center font-display font-semibold text-text-ink">
                        {{ \Illuminate\Support\Str::ucfirst(\Illuminate\Support\Carbon::createFromFormat('!Y-m', $mesFoco)->translatedFormat('F \d\e Y')) }}
                    </span>
                    <button type="button" wire:click="mesProximo" @disabled(! $temProximo) aria-label="Próximo mês"
                            class="grid size-9 place-items-center rounded-full border border-border-muted text-text-secondary transition hover:border-primary hover:text-primary disabled:cursor-not-allowed disabled:opacity-40">›</button>
                </div>
                @if (count($anos) > 1)
                    <select wire:change="irParaAno($event.target.value)" aria-label="Ir para o ano"
                            class="rounded-pill border border-border-muted bg-surface px-3 py-1.5 text-sm text-text-secondary">
                        @foreach ($anos as $ano)
                            <option value="{{ $ano }}" @selected(str_starts_with($mesFoco, $ano.'-'))>{{ $ano }}</option>
                        @endforeach
                    </select>
                @endif
            </div>
        @endif
    </div>

    {{-- Bloco do mês: mini-calendário + agenda --}}
    @if ($mesFoco)
        <div class="mt-6 flex flex-wrap gap-6">
            {{-- Mini-calendário --}}
            <aside class="w-full shrink-0 sm:sticky sm:top-[88px] sm:w-[300px] sm:self-start">
                <div class="rounded-2xl border border-border-muted bg-white p-4 shadow-card">
                    <p class="mb-3 font-mono text-[11px] uppercase tracking-[0.14em] text-text-muted">Dias com ocorrência</p>
                    <div class="grid grid-cols-7 gap-1 text-center">
                        @foreach (['D', 'S', 'T', 'Q', 'Q', 'S', 'S'] as $inicial)
                            <span wire:key="dow-{{ $loop->index }}" class="py-1 font-mono text-[11px] font-semibold text-text-muted" aria-hidden="true">{{ $inicial }}</span>
                        @endforeach
                        @for ($v = 0; $v < $matriz['diasVazios']; $v++)
                            <span wire:key="vazio-{{ $v }}" aria-hidden="true"></span>
                        @endfor
                        @foreach ($matriz['dias'] as $celula)
                            @if (! empty($celula['ocorrencias']))
                                <button type="button" wire:key="dia-{{ $celula['dia'] }}"
                                        class="cema-cal-day cema-cal-day--com-palestra relative @if ($celula['hoje']) cema-cal-day--hoje @endif"
                                        aria-label="{{ $celula['dia'] }}: {{ collect($celula['ocorrencias'])->pluck('titulo')->join('; ') }}"
                                        x-data
                                        x-on:click="
                                            const alvo = document.getElementById('linha-{{ $celula['ancora'] }}');
                                            if (alvo) { alvo.scrollIntoView({behavior:'smooth', block:'center'}); alvo.classList.add('is-destaque'); setTimeout(() => alvo.classList.remove('is-destaque'), 1900); }
                                        ">
                                    <span class="-translate-y-0.5">{{ $celula['dia'] }}</span>
                                    <span class="cema-cal-dots" aria-hidden="true">
                                        @foreach (array_slice(collect($celula['ocorrencias'])->unique('tipo')->values()->all(), 0, 2) as $pt)
                                            <span class="cema-cal-dot" style="background: {{ $pt['cor'] }}"></span>
                                        @endforeach
                                    </span>
                                </button>
                            @else
                                <span wire:key="dia-{{ $celula['dia'] }}" class="cema-cal-day @if ($celula['hoje']) cema-cal-day--hoje @endif">{{ $celula['dia'] }}</span>
                            @endif
                        @endforeach
                    </div>
                    <div class="mt-3 flex flex-wrap items-center gap-4 text-[11px] text-text-muted">
                        <span class="inline-flex items-center gap-1.5"><span class="inline-block size-3 rounded-full" style="background: #F4C24B"></span> Palestra</span>
                        <span class="inline-flex items-center gap-1.5"><span class="inline-block size-3 rounded-full" style="background: #89AB98"></span> Evento</span>
                        <span class="inline-flex items-center gap-1.5"><span class="inline-block size-3 rounded-full ring-2 ring-secondary"></span> Hoje</span>
                    </div>
                </div>
            </aside>

            {{-- Agenda --}}
            <div class="min-w-0 flex-1">
                <div class="mb-4 flex items-center gap-3">
                    <h2 class="font-display text-lg font-semibold text-primary">
                        {{ \Illuminate\Support\Str::ucfirst(\Illuminate\Support\Carbon::createFromFormat('!Y-m', $mesFoco)->translatedFormat('F \d\e Y')) }}
                    </h2>
                    <span class="rounded-pill bg-surface px-2.5 py-0.5 text-xs font-semibold text-primary">{{ $contagem }} {{ $contagem === 1 ? 'item' : 'itens' }}</span>
                </div>

                <div class="flex flex-col gap-3">
                    @forelse ($ocorrenciasDoMes as $oc)
                        <a wire:key="{{ $oc->chave }}" id="linha-{{ $oc->chave }}" href="{{ $oc->url }}"
                           class="cema-row group flex items-stretch gap-4 rounded-2xl border border-border-muted bg-white p-3 shadow-card sm:p-4">
                            <span @class(['flex w-[72px] shrink-0 flex-col items-center justify-center rounded-xl py-2 text-center', 'cema-chip-data--proxima' => $modo !== 'realizadas', 'cema-chip-data--realizada' => $modo === 'realizadas'])>
                                <span class="font-mono text-[10px] uppercase">{{ $oc->inicio->translatedFormat('D') }}</span>
                                <span class="font-display text-2xl font-bold leading-none">{{ $oc->inicio->format('d') }}</span>
                                <span class="font-mono text-[10px]">{{ $oc->temHora ? $oc->inicio->format('H\hi') : 'dia inteiro' }}</span>
                            </span>
                            <div class="flex min-w-0 flex-1 flex-col justify-center">
                                <div class="flex flex-wrap items-center gap-2">
                                    @if ($oc->tipo === 'palestra')
                                        <span class="rounded-pill bg-[#FBF1DA] px-2.5 py-0.5 text-[11px] font-semibold text-[#8A6A1E]">Palestra</span>
                                    @else
                                        <span class="rounded-pill bg-[#EAF1EC] px-2.5 py-0.5 text-[11px] font-semibold text-[#3A6B4E]">Evento</span>
                                    @endif
                                    <span class="rounded-pill px-2.5 py-0.5 text-[11px] font-semibold" style="background: {{ $oc->selo['cor'] }}; color: {{ $oc->selo['cor_texto'] }};">
                                        {{ $oc->selo['rotulo'] }}
                                    </span>
                                    @if ($oc->seloVisibilidade)
                                        <x-ui.selo-visibilidade :rotulo="$oc->seloVisibilidade['rotulo']" :cor="$oc->seloVisibilidade['cor']" />
                                    @endif
                                </div>
                                <h3 class="mt-1 truncate font-display font-semibold text-text-ink group-hover:text-primary">{{ $oc->titulo }}</h3>
                                @if ($oc->imagem || $oc->iniciais)
                                    <div class="mt-1 flex items-center gap-2 text-sm text-text-secondary">
                                        <span @class([
                                            'grid size-6 shrink-0 place-items-center overflow-hidden ring-1 ring-black/5',
                                            'cema-cal-avatar rounded-full' => $oc->tipo === 'palestra',
                                            'rounded-md bg-surface' => $oc->tipo !== 'palestra',
                                        ])>
                                            @if ($oc->imagem)
                                                <img src="{{ $oc->imagem }}" alt="" width="24" height="24" class="size-full object-cover">
                                            @else
                                                <span class="text-[9px] font-semibold text-[#3a2f00]">{{ $oc->iniciais }}</span>
                                            @endif
                                        </span>
                                        @if ($oc->subtitulo)
                                            <span class="truncate">{{ $oc->subtitulo }}</span>
                                        @endif
                                    </div>
                                @elseif ($oc->subtitulo)
                                    <p class="mt-1 truncate text-sm text-text-secondary">{{ $oc->subtitulo }}</p>
                                @endif
                            </div>
                            <span class="cema-row-cta hidden shrink-0 items-center self-center rounded-pill border border-border-muted px-4 py-2 text-sm font-semibold text-primary transition sm:inline-flex">Ver {{ $oc->tipo === 'palestra' ? 'palestra' : 'evento' }}</span>
                        </a>
                    @empty
                        <div class="rounded-2xl border border-dashed border-border-muted bg-surface px-6 py-12 text-center">
                            <p class="text-lg font-semibold text-text-secondary">{{ $substantivoVazio }} neste período</p>
                            @if ($modo === 'realizadas')
                                <button type="button" wire:click="$set('modo', 'proximas')" class="mt-3 rounded-pill bg-primary px-5 py-2 text-sm font-semibold text-white transition hover:opacity-90">Ver próximas</button>
                            @endif
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    @else
        {{-- Estado vazio total (nenhum mês no modo/tipo atual) --}}
        <div class="mt-6 rounded-2xl border border-dashed border-border-muted bg-surface px-6 py-16 text-center">
            <p class="text-4xl" aria-hidden="true">🗓️</p>
            <p class="mt-2 text-lg font-semibold text-text-secondary">{{ $substantivoVazio }} {{ $adjetivoVazio }} no momento</p>
            @if ($modo === 'realizadas')
                <button type="button" wire:click="$set('modo', 'proximas')" class="mt-3 rounded-pill bg-primary px-5 py-2 text-sm font-semibold text-white transition hover:opacity-90">Ver próximas</button>
            @endif
        </div>
    @endif

    <x-ui.assinar-modal :feeds="$feedsAssinar" />
</div>
