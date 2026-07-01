{{-- Modal "Assinar calendário": <dialog> nativo + Alpine. Abre em `open-assinar`. --}}
@props(['feedUrl'])

@php
    $parts = parse_url($feedUrl);
    $host = $parts['host'] ?? request()->getHost();
    $path = $parts['path'] ?? '';
    $webcal = 'webcal://'.$host.$path;
    $google = 'https://calendar.google.com/calendar/r?cid='.rawurlencode($webcal);
    $download = $feedUrl.'?download=1';
@endphp

<div
    x-data="{ aberto: false, abre() { this.aberto = true; $nextTick(() => $refs.dlg?.showModal()); }, fecha() { this.aberto = false; $refs.dlg?.close(); } }"
    x-on:open-assinar.window="abre()"
>
    <dialog
        x-ref="dlg"
        x-on:close="aberto = false"
        x-on:click.self="fecha()"
        role="dialog"
        aria-modal="true"
        aria-labelledby="assinar-titulo"
        class="cema-modal m-auto w-[min(92vw,460px)] rounded-2xl border border-border-muted bg-white p-0 text-text-ink backdrop:bg-black/50"
    >
        <div class="p-6 sm:p-7">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 id="assinar-titulo" class="font-display text-xl font-semibold text-primary">Assinar calendário</h2>
                    <p class="mt-1 text-sm text-text-secondary">Assine uma vez e cada domingo, às 19h, entra automaticamente no seu calendário.</p>
                </div>
                <button type="button" x-on:click="fecha()" aria-label="Fechar" class="shrink-0 rounded-full p-1.5 text-text-muted transition hover:bg-surface hover:text-text-ink">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" stroke-linecap="round"/></svg>
                </button>
            </div>

            <div class="mt-5 flex flex-col gap-2.5">
                <a href="{{ $google }}" target="_blank" rel="noopener"
                   class="flex items-center gap-3 rounded-xl border border-border-muted bg-surface px-4 py-3 font-medium transition hover:border-primary">
                    <span class="grid size-9 place-items-center rounded-lg bg-white text-lg" aria-hidden="true">📅</span>
                    Google Calendar
                </a>
                <a href="{{ $webcal }}"
                   class="flex items-center gap-3 rounded-xl border border-border-muted bg-surface px-4 py-3 font-medium transition hover:border-primary">
                    <span class="grid size-9 place-items-center rounded-lg bg-white text-lg" aria-hidden="true">🍎</span>
                    Apple Calendar
                </a>
                <a href="{{ $download }}"
                   class="flex items-center gap-3 rounded-xl border border-border-muted bg-surface px-4 py-3 font-medium transition hover:border-primary">
                    <span class="grid size-9 place-items-center rounded-lg bg-white text-lg" aria-hidden="true">⬇️</span>
                    Baixar .ics
                </a>
            </div>

            <p class="mt-4 text-xs text-text-muted">No Google, "assinar por URL" só sincroniza em produção (o Google não alcança o localhost).</p>
        </div>
    </dialog>
</div>
