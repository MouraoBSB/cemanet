<div>
    {{-- Toolbar --}}
    <div class="rounded-2xl border border-border-muted bg-white p-[18px] shadow-card">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="relative w-full sm:max-w-md">
                <label for="busca-palestrantes" class="sr-only">Buscar palestrante pelo nome</label>
                <svg class="pointer-events-none absolute left-4 top-1/2 size-4 -translate-y-1/2 text-text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5" stroke-linecap="round"/></svg>
                <input id="busca-palestrantes" type="search" wire:model.live.debounce.300ms="q"
                       placeholder="Buscar palestrante pelo nome…"
                       class="w-full rounded-pill border border-border bg-surface py-2.5 pl-11 pr-10 font-sans text-sm text-text-ink outline-none focus:border-primary">
                @if ($q !== '')
                    <button type="button" wire:click="$set('q', '')" aria-label="Limpar busca"
                            class="absolute right-3 top-1/2 grid size-6 -translate-y-1/2 place-items-center rounded-full text-text-muted transition hover:bg-surface hover:text-text-ink">
                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" stroke-linecap="round"/></svg>
                    </button>
                @endif
            </div>
            <div class="flex items-center gap-2">
                <label for="ordenar-palestrantes" class="text-sm text-text-muted">Ordenar:</label>
                <select id="ordenar-palestrantes" wire:model.live="ordenar"
                        class="rounded-[10px] border border-border bg-white px-3 py-2 text-sm text-text-secondary outline-none focus:border-primary">
                    <option value="az">Nome (A–Z)</option>
                    <option value="za">Nome (Z–A)</option>
                    <option value="mais">Mais palestras</option>
                    <option value="menos">Menos palestras</option>
                </select>
            </div>
        </div>
    </div>

    {{-- Linha de resultados --}}
    <div class="mt-4 flex items-center justify-between">
        <p class="text-sm text-text-muted">{{ $palestrantes->total() }} {{ \Illuminate\Support\Str::plural('palestrante', $palestrantes->total()) }}</p>
        @if (! empty($filtrosAtivos))
            <button type="button" wire:click="limparFiltros" class="text-sm font-medium text-secondary transition hover:text-primary">Limpar filtros</button>
        @endif
    </div>

    {{-- Grade / estado vazio --}}
    @if ($palestrantes->isEmpty())
        <div class="mt-6 rounded-2xl border border-dashed border-border-muted bg-surface px-6 py-16 text-center">
            <span class="mx-auto grid size-14 place-items-center rounded-full bg-cream text-primary" aria-hidden="true">
                <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5" stroke-linecap="round"/></svg>
            </span>
            <p class="mt-3 text-lg font-semibold text-text-secondary">Nenhum palestrante encontrado</p>
            <p class="mt-1 text-sm text-text-muted">Tente outro nome ou limpe a busca.</p>
            <button type="button" wire:click="limparFiltros" class="mt-4 rounded-pill bg-primary px-5 py-2 text-sm font-semibold text-white transition hover:opacity-90">Limpar filtros</button>
        </div>
    @else
        <div class="mt-6 grid grid-cols-[repeat(auto-fill,minmax(212px,1fr))] gap-[18px]">
            @foreach ($palestrantes as $palestrante)
                <x-palestrante.card :palestrante="$palestrante" wire:key="palestrante-{{ $palestrante->id }}" />
            @endforeach
        </div>
        <div class="mt-10">{{ $palestrantes->onEachSide(1)->links() }}</div>
    @endif
</div>
