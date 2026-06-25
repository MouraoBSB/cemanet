<div>
    {{-- Painel de filtros --}}
    <div class="mb-8 rounded-2xl border border-border-muted bg-white p-6 shadow-card">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="font-display text-xl font-semibold text-primary">Filtrar Palestras</h2>
            <span class="font-display text-sm font-semibold text-primary">Total {{ $palestras->total() }}</span>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 desktop-sm:grid-cols-4">
            <div>
                <label for="f-de" class="mb-1 block text-sm text-text-secondary">De</label>
                <input id="f-de" type="date" wire:model.live="dataDe" class="w-full rounded-md border border-border bg-white px-3 py-2 text-sm">
            </div>
            <div>
                <label for="f-ate" class="mb-1 block text-sm text-text-secondary">Até</label>
                <input id="f-ate" type="date" wire:model.live="dataAte" class="w-full rounded-md border border-border bg-white px-3 py-2 text-sm">
            </div>
            <div>
                <label for="f-palestrante" class="mb-1 block text-sm text-text-secondary">Palestrante</label>
                <select id="f-palestrante" wire:model.live="palestrante" class="w-full rounded-md border border-border bg-white px-3 py-2 text-sm">
                    <option value="">Selecione…</option>
                    @foreach ($palestrantes as $p)
                        <option value="{{ $p->slug }}">{{ $p->nome }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="f-assunto" class="mb-1 block text-sm text-text-secondary">Assunto</label>
                <select id="f-assunto" wire:model.live="assunto" class="w-full rounded-md border border-border bg-white px-3 py-2 text-sm">
                    <option value="">Todos</option>
                    @foreach ($assuntos as $a)
                        <option value="{{ $a->slug }}">{{ $a->nome }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end">
            <div class="flex-1">
                <label for="f-titulo" class="mb-1 block text-sm text-text-secondary">Título</label>
                <input id="f-titulo" type="search" wire:model.live.debounce.350ms="q" placeholder="Pesquisar…"
                       class="w-full rounded-md border border-border bg-white px-4 py-2 text-sm">
            </div>
            <div>
                <label for="f-ordenar" class="mb-1 block text-sm text-text-secondary">Organizar</label>
                <select id="f-ordenar" wire:model.live="ordenar" class="w-full rounded-md border border-border bg-white px-3 py-2 text-sm">
                    <option value="recente">Mais recentes</option>
                    <option value="antiga">Mais antigas</option>
                </select>
            </div>
            <button type="button" wire:click="limparFiltros" class="rounded-pill border border-border px-4 py-2 text-sm text-text-secondary hover:border-primary hover:text-primary">Limpar</button>
        </div>
    </div>

    {{-- Grid --}}
    @if ($palestras->isEmpty())
        <p class="rounded-lg border border-border-muted bg-surface px-6 py-10 text-center text-text-secondary">Nenhuma palestra encontrada.</p>
    @else
        <div class="grid gap-5 sm:grid-cols-2 desktop-sm:grid-cols-3 desktop:grid-cols-4">
            @foreach ($palestras as $palestra)
                <x-palestra.card :palestra="$palestra" wire:key="palestra-{{ $palestra->id }}" />
            @endforeach
        </div>
        <div class="mt-10">{{ $palestras->onEachSide(1)->links() }}</div>
    @endif
</div>
