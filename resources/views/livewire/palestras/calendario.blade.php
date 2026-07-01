<div>
    {{-- Destaque: próxima palestra (sem fallback) --}}
    @if ($proxima)
        @php($pp = $proxima->palestrantesAtivos->first())
        @php($ptema = $proxima->assuntos->first())
        <section class="mb-10" aria-label="Próxima palestra">
            <p class="mb-3 inline-flex items-center gap-2 font-display text-base font-semibold text-primary">
                <span class="inline-block size-2.5 animate-pulse rounded-full bg-gold" aria-hidden="true"></span> Próxima palestra
            </p>
            <div class="relative overflow-hidden rounded-[18px] bg-gradient-to-r from-[#3a3266] via-primary to-[#5b4f92] p-6 text-white sm:p-8">
                <span aria-hidden="true" class="pointer-events-none absolute -top-[40px] -right-[30px] size-[180px] rounded-full bg-gold/[0.14]"></span>
                <span aria-hidden="true" class="pointer-events-none absolute -bottom-[60px] right-[120px] size-[150px] rounded-full bg-secondary/[0.16]"></span>
                <div class="relative flex flex-col items-center gap-6 sm:flex-row sm:gap-7">
                    <span class="cema-cal-avatar cema-cal-avatar-{{ $proxima->id % 4 }} flex size-[88px] shrink-0 items-center justify-center overflow-hidden rounded-full ring-4 ring-white/20">
                        @if ($pp?->foto_thumb_url)
                            <img src="{{ $pp->foto_thumb_url }}" alt="{{ $pp->nome }}" width="88" height="88" class="size-full object-cover">
                        @else
                            <span class="font-display text-2xl font-semibold text-[#3a2f00]">{{ $pp ? collect(explode(' ', $pp->nome))->take(2)->map(fn ($n) => mb_substr($n, 0, 1))->implode('') : 'CEMA' }}</span>
                        @endif
                    </span>
                    <div class="flex-1 text-center sm:text-left">
                        <div class="flex flex-wrap items-center justify-center gap-2 sm:justify-start">
                            <span class="inline-flex items-center gap-1.5 rounded-pill bg-gold px-3 py-1 font-mono text-xs font-semibold text-[#3a2f00]">
                                {{ $proxima->data_da_palestra->translatedFormat('d \d\e M') }} · {{ $proxima->data_da_palestra->format('H\hi') }}
                            </span>
                            <x-palestra.badge-formato :palestra="$proxima" variante="solido" />
                        </div>
                        <h3 class="mt-3 font-display text-2xl font-semibold">{{ $proxima->titulo }}</h3>
                        @if ($pp || $ptema)
                            <p class="mt-1 text-white/80">@if ($pp)com <strong class="font-semibold">{{ $proxima->palestrantesAtivos->pluck('nome')->join(', ', ' e ') }}</strong>@endif@if ($pp && $ptema) · @endif@if ($ptema){{ $ptema->nome }}@endif</p>
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

    {{-- Barra de período: tabs + navegação de mês + seletor de ano --}}
    <div class="flex flex-col gap-4 rounded-2xl border border-border-muted bg-white p-4 shadow-card sm:flex-row sm:items-center sm:justify-between">
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

        @if ($mesFoco)
            <div class="flex items-center gap-3">
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
                    <p class="mb-3 font-mono text-[11px] uppercase tracking-[0.14em] text-text-muted">Dias com palestra</p>
                    <div class="grid grid-cols-7 gap-1 text-center">
                        @foreach (['D', 'S', 'T', 'Q', 'Q', 'S', 'S'] as $inicial)
                            <span wire:key="dow-{{ $loop->index }}" class="py-1 font-mono text-[11px] font-semibold text-text-muted" aria-hidden="true">{{ $inicial }}</span>
                        @endforeach
                        @for ($v = 0; $v < $matriz['diasVazios']; $v++)
                            <span wire:key="vazio-{{ $v }}" aria-hidden="true"></span>
                        @endfor
                        @foreach ($matriz['dias'] as $celula)
                            @if ($celula['palestra'])
                                <button type="button"
                                        wire:key="dia-{{ $celula['dia'] }}"
                                        class="cema-cal-day cema-cal-day--com-palestra @if ($celula['hoje']) cema-cal-day--hoje @endif"
                                        title="{{ $celula['palestra']['titulo'] }}"
                                        aria-label="{{ $celula['dia'] }}: {{ $celula['palestra']['titulo'] }}"
                                        x-data
                                        x-on:click="
                                            const alvo = document.getElementById('linha-{{ $celula['palestra']['slug'] }}');
                                            if (alvo) {
                                                alvo.scrollIntoView({ behavior: 'smooth', block: 'center' });
                                                alvo.classList.add('is-destaque');
                                                setTimeout(() => alvo.classList.remove('is-destaque'), 1900);
                                            }
                                        ">{{ $celula['dia'] }}</button>
                            @else
                                <span wire:key="dia-{{ $celula['dia'] }}" class="cema-cal-day @if ($celula['hoje']) cema-cal-day--hoje @endif">{{ $celula['dia'] }}</span>
                            @endif
                        @endforeach
                    </div>
                    <div class="mt-3 flex items-center gap-4 text-[11px] text-text-muted">
                        <span class="inline-flex items-center gap-1.5"><span class="inline-block size-3 rounded-full bg-gold"></span> Palestra</span>
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
                    <span class="rounded-pill bg-surface px-2.5 py-0.5 text-xs font-semibold text-primary">{{ $palestrasDoMes->count() }} {{ \Illuminate\Support\Str::plural('palestra', $palestrasDoMes->count()) }}</span>
                </div>

                <div class="flex flex-col gap-3">
                    @forelse ($palestrasDoMes as $p)
                        @php($pa = $p->palestrantesAtivos->first())
                        @php($ptag = $p->assuntos->first())
                        <a wire:key="linha-{{ $p->id }}" id="linha-{{ $p->slug }}" href="{{ route('palestras.show', $p->slug) }}"
                           class="cema-row group flex items-stretch gap-4 rounded-2xl border border-border-muted bg-white p-3 shadow-card sm:p-4">
                            <span @class(['flex w-[72px] shrink-0 flex-col items-center justify-center rounded-xl py-2 text-center', 'cema-chip-data--proxima' => $p->eh_proxima, 'cema-chip-data--realizada' => ! $p->eh_proxima])>
                                <span class="font-mono text-[10px] uppercase">{{ $p->data_da_palestra->translatedFormat('D') }}</span>
                                <span class="font-display text-2xl font-bold leading-none">{{ $p->data_da_palestra->format('d') }}</span>
                                <span class="font-mono text-[10px]">{{ $p->data_da_palestra->format('H\hi') }}</span>
                            </span>
                            <div class="flex min-w-0 flex-1 flex-col justify-center">
                                <div class="flex flex-wrap items-center gap-2">
                                    <x-palestra.badge-formato :palestra="$p" variante="claro" />
                                    @if ($ptag)
                                        <span class="rounded-pill bg-[#EFEBF7] px-2.5 py-0.5 text-[11px] font-semibold text-[#6a6390]">{{ $ptag->nome }}</span>
                                    @endif
                                    @if ($p->eh_proxima)
                                        <span class="rounded-pill bg-gold/[0.16] px-2.5 py-0.5 text-[11px] font-semibold text-[#8a6a1e]">Próxima</span>
                                    @elseif ($p->eh_realizada)
                                        <span class="rounded-pill bg-surface px-2.5 py-0.5 text-[11px] font-semibold text-text-muted">Realizada</span>
                                    @endif
                                    @if ($p->tem_gravacao)
                                        <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-danger" aria-hidden="true">▶ gravada</span>
                                    @endif
                                </div>
                                <h3 class="mt-1 truncate font-display font-semibold text-text-ink group-hover:text-primary">{{ $p->titulo }}</h3>
                                @if ($pa)
                                    <p class="mt-0.5 truncate text-sm text-text-secondary">com {{ $p->palestrantesAtivos->pluck('nome')->join(', ', ' e ') }}</p>
                                @endif
                            </div>
                            <span class="cema-row-cta hidden shrink-0 items-center self-center rounded-pill border border-border-muted px-4 py-2 text-sm font-semibold text-primary transition sm:inline-flex">Ver palestra</span>
                        </a>
                    @empty
                        <div class="rounded-2xl border border-dashed border-border-muted bg-surface px-6 py-12 text-center">
                            <p class="text-lg font-semibold text-text-secondary">Nenhuma palestra neste período</p>
                            <button type="button" wire:click="$set('modo', 'proximas')" class="mt-3 rounded-pill bg-primary px-5 py-2 text-sm font-semibold text-white transition hover:opacity-90">Ver próximas palestras</button>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    @else
        {{-- Estado vazio total (nenhum mês no modo) --}}
        <div class="mt-6 rounded-2xl border border-dashed border-border-muted bg-surface px-6 py-16 text-center">
            <p class="text-4xl" aria-hidden="true">🗓️</p>
            <p class="mt-2 text-lg font-semibold text-text-secondary">Nenhuma palestra {{ $modo === 'realizadas' ? 'realizada' : 'agendada' }} no momento</p>
            @if ($modo === 'realizadas')
                <button type="button" wire:click="$set('modo', 'proximas')" class="mt-3 rounded-pill bg-primary px-5 py-2 text-sm font-semibold text-white transition hover:opacity-90">Ver próximas palestras</button>
            @endif
        </div>
    @endif
</div>
