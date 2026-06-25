<div>
    {{-- Busca + filtro --}}
    <form class="mb-8 flex flex-col gap-3 sm:flex-row" wire:submit.prevent>
        <label for="busca-lista" class="sr-only">Buscar palestras</label>
        <input id="busca-lista" type="search" wire:model.live.debounce.350ms="q"
               placeholder="Buscar por título ou assunto…"
               class="w-full rounded-pill border border-border bg-white px-5 py-2.5 font-sans text-sm text-text outline-none focus:border-primary">
    </form>

    @if ($this->assunto !== '')
        <p class="mb-4 text-sm text-text-secondary">
            Filtrando por assunto:
            <button type="button" wire:click="$set('assunto','')" class="font-semibold text-secondary hover:underline">limpar filtro ✕</button>
        </p>
    @endif

    @if ($palestras->isEmpty())
        <p class="rounded-lg border border-border-muted bg-surface px-6 py-10 text-center text-text-secondary">
            Nenhuma palestra encontrada.
        </p>
    @else
        <div class="grid gap-6 sm:grid-cols-2 desktop-sm:grid-cols-3">
            @foreach ($palestras as $palestra)
                <x-palestra.card :palestra="$palestra" wire:key="palestra-{{ $palestra->id }}" />
            @endforeach
        </div>

        <div class="mt-10">
            {{ $palestras->onEachSide(1)->links() }}
        </div>
    @endif
</div>
