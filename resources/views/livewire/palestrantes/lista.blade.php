<div>
    <form class="mb-8" wire:submit.prevent>
        <label for="busca-palestrantes" class="sr-only">Buscar palestrante por nome</label>
        <input id="busca-palestrantes" type="search" wire:model.live.debounce.350ms="q"
               placeholder="Buscar palestrante…"
               class="w-full rounded-pill border border-border bg-white px-5 py-2.5 font-sans text-sm text-text outline-none focus:border-primary sm:max-w-md">
    </form>

    @if ($palestrantes->isEmpty())
        <p class="rounded-lg border border-border-muted bg-surface px-6 py-10 text-center text-text-secondary">Nenhum palestrante encontrado.</p>
    @else
        <div class="grid gap-6 sm:grid-cols-2 desktop-sm:grid-cols-3 desktop:grid-cols-4">
            @foreach ($palestrantes as $palestrante)
                <x-palestrante.card :palestrante="$palestrante" wire:key="palestrante-{{ $palestrante->id }}" />
            @endforeach
        </div>
        <div class="mt-10">{{ $palestrantes->onEachSide(1)->links() }}</div>
    @endif
</div>
