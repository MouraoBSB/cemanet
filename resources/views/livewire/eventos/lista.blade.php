<div>
    {{-- Abas: Próximos × Anteriores --}}
    <div class="mb-6 flex items-center gap-6 border-b border-border-muted" role="tablist" aria-label="Período dos eventos">
        <button type="button" role="tab" aria-selected="{{ $aba === 'proximos' ? 'true' : 'false' }}"
                wire:click="$set('aba', 'proximos')"
                @class(['-mb-px border-b-2 px-1 pb-3 text-sm font-medium', 'border-gold text-primary' => $aba === 'proximos', 'border-transparent text-text-muted' => $aba !== 'proximos'])>
            Próximos
        </button>
        <button type="button" role="tab" aria-selected="{{ $aba === 'anteriores' ? 'true' : 'false' }}"
                wire:click="$set('aba', 'anteriores')"
                @class(['-mb-px border-b-2 px-1 pb-3 text-sm font-medium', 'border-gold text-primary' => $aba === 'anteriores', 'border-transparent text-text-muted' => $aba !== 'anteriores'])>
            Anteriores
        </button>
    </div>

    {{-- Barra de filtros --}}
    <div class="mb-6 rounded-2xl border border-border-muted bg-surface p-5 shadow-card sm:px-6">
        <div class="grid gap-3.5 sm:grid-cols-2 desktop-sm:grid-cols-4">
            <div class="sm:col-span-2">
                <label for="f-busca" class="mb-1 block text-xs text-text-muted">Buscar</label>
                <input id="f-busca" type="search" wire:model.live.debounce.350ms="q" placeholder="Buscar por título…"
                       class="w-full rounded-[9px] border border-border-muted bg-white px-3 py-2 text-sm">
            </div>
            <div>
                <label for="f-mes" class="mb-1 block text-xs text-text-muted">Mês</label>
                <select id="f-mes" wire:model.live="mes" class="w-full rounded-[9px] border border-border-muted bg-white px-3 py-2 text-sm">
                    <option value="">Todos</option>
                    @foreach ($meses as $m)
                        <option value="{{ $m }}">{{ \Illuminate\Support\Carbon::createFromFormat('Y-m', $m)->translatedFormat('F \d\e Y') }}</option>
                    @endforeach
                </select>
            </div>
            <div class="desktop-sm:col-span-1">
                <span class="mb-1 block text-xs text-text-muted">Categoria</span>
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" wire:click="$set('categoria', '')"
                            @class(['rounded-pill px-3 py-1 text-xs font-medium', 'bg-primary text-white' => $categoria === '', 'bg-white text-text-muted border border-border-muted' => $categoria !== ''])>
                        Todas
                    </button>
                    @foreach ($categorias as $cat)
                        <button type="button" wire:click="$set('categoria', '{{ $cat->slug }}')"
                                style="{{ $categoria === $cat->slug ? 'background:'.$cat->cor.'; color:'.($cat->cor_texto ?? '#fff').';' : '' }}"
                                @class(['rounded-pill px-3 py-1 text-xs font-medium', 'bg-white text-text-muted border border-border-muted' => $categoria !== $cat->slug])>
                            {{ $cat->nome }}
                        </button>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <p class="mb-5 font-mono text-[13.5px] text-text-muted">{{ $eventos->total() }} eventos</p>

    @if ($eventos->isEmpty())
        <div class="rounded-2xl border border-dashed border-[#DAD5E6] bg-white px-6 py-16 text-center">
            <div class="mx-auto mb-4 flex size-16 items-center justify-center rounded-full bg-cream text-2xl" aria-hidden="true">🔍</div>
            <h3 class="font-display text-lg font-semibold text-primary">Nenhum evento encontrado</h3>
            <p class="mx-auto mt-2 max-w-md text-sm text-text-muted">Ajuste a busca ou os filtros para ver outros eventos.</p>
        </div>
    @else
        <div class="grid grid-cols-[repeat(auto-fill,minmax(290px,1fr))] gap-6">
            @foreach ($eventos as $e)
                <x-evento.card :evento="$e" wire:key="ev-{{ $e->id }}" />
            @endforeach
        </div>
        <div class="mt-9">{{ $eventos->onEachSide(1)->links() }}</div>
    @endif
</div>
