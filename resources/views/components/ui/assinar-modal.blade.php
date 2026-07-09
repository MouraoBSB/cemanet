{{-- Thiago Mourão — https://github.com/MouraoBSB — 2026-07-09 --}}
{{-- Modal "Assinar calendário": 1+ feeds. Abre no evento de janela `open-assinar`. --}}
@props(['feeds']) {{-- feeds: list<array{rotulo:string,url:string}> --}}
<div x-data="{ aberto:false, abre(){ this.aberto=true; $nextTick(()=>$refs.dlg?.showModal()); }, fecha(){ this.aberto=false; $refs.dlg?.close(); } }"
     x-on:open-assinar.window="abre()">
    <dialog x-ref="dlg" x-on:close="aberto=false" x-on:click.self="fecha()" role="dialog" aria-modal="true"
            aria-labelledby="assinar-cal-titulo"
            class="cema-modal m-auto w-[min(92vw,460px)] rounded-2xl border border-border-muted bg-white p-0 text-text-ink backdrop:bg-black/50">
        <div class="p-6 sm:p-7">
            <div class="flex items-start justify-between gap-4">
                <h2 id="assinar-cal-titulo" class="font-display text-xl font-semibold text-primary">Assinar calendário</h2>
                <button type="button" x-on:click="fecha()" aria-label="Fechar" class="shrink-0 rounded-full p-1.5 text-text-muted transition hover:bg-surface hover:text-text-ink">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" stroke-linecap="round"/></svg>
                </button>
            </div>
            @foreach ($feeds as $feed)
                @php
                    $parts = parse_url($feed['url']);
                    $webcal = 'webcal://'.($parts['host'] ?? request()->getHost()).($parts['path'] ?? '');
                    $google = 'https://calendar.google.com/calendar/r?cid='.rawurlencode($webcal);
                @endphp
                <div class="mt-5">
                    @if (count($feeds) > 1)
                        <p class="mb-2 font-mono text-[11px] uppercase tracking-[0.12em] text-text-muted">{{ $feed['rotulo'] }}</p>
                    @endif
                    <div class="flex flex-col gap-2.5">
                        <a href="{{ $google }}" target="_blank" rel="noopener" class="flex items-center gap-3 rounded-xl border border-border-muted bg-surface px-4 py-3 font-medium transition hover:border-primary"><span class="grid size-9 place-items-center rounded-lg bg-white text-lg" aria-hidden="true">📅</span> Google Calendar</a>
                        <a href="{{ $webcal }}" class="flex items-center gap-3 rounded-xl border border-border-muted bg-surface px-4 py-3 font-medium transition hover:border-primary"><span class="grid size-9 place-items-center rounded-lg bg-white text-lg" aria-hidden="true">🍎</span> Apple Calendar</a>
                        <a href="{{ $feed['url'].'?download=1' }}" class="flex items-center gap-3 rounded-xl border border-border-muted bg-surface px-4 py-3 font-medium transition hover:border-primary"><span class="grid size-9 place-items-center rounded-lg bg-white text-lg" aria-hidden="true">⬇️</span> Baixar .ics</a>
                    </div>
                </div>
            @endforeach
            <p class="mt-4 text-xs text-text-muted">No Google, "assinar por URL" só sincroniza em produção (o Google não alcança o localhost).</p>
        </div>
    </dialog>
</div>
