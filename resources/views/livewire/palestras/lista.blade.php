<div>
    {{-- Barra de filtros --}}
    <div class="rounded-2xl border border-border-muted bg-white p-5 shadow-card sm:px-6">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <h2 class="inline-flex items-center gap-2 font-display text-base font-semibold text-primary">
                Filtrar palestras
                <span class="rounded-pill bg-cream px-2.5 py-0.5 text-xs font-medium text-primary">Total {{ $palestras->total() }}</span>
            </h2>
            <div class="flex items-center gap-3">
                <label class="flex items-center gap-2 text-xs text-text-muted">
                    Ordenar:
                    <select wire:model.live="ordenar" class="rounded-md border border-border-muted bg-surface px-2.5 py-1.5 text-sm text-text-ink">
                        <option value="recente">Mais recentes</option>
                        <option value="antiga">Mais antigas</option>
                        <option value="az">Título (A–Z)</option>
                    </select>
                </label>
                <div class="inline-flex overflow-hidden rounded-md border border-border-muted" role="group" aria-label="Modo de exibição">
                    <button type="button" wire:click="alternarVisao('grid')" aria-label="Grade" aria-pressed="{{ $visao === 'grid' ? 'true' : 'false' }}"
                            @class(['flex size-8 items-center justify-center text-sm', 'bg-white text-primary shadow-sm' => $visao === 'grid', 'bg-transparent text-text-muted' => $visao !== 'grid'])>▦</button>
                    <button type="button" wire:click="alternarVisao('list')" aria-label="Lista" aria-pressed="{{ $visao === 'list' ? 'true' : 'false' }}"
                            @class(['flex size-8 items-center justify-center text-sm', 'bg-white text-primary shadow-sm' => $visao === 'list', 'bg-transparent text-text-muted' => $visao !== 'list'])>☰</button>
                </div>
            </div>
        </div>

        <div class="grid gap-3.5 sm:grid-cols-2 desktop-sm:grid-cols-3 desktop:grid-cols-4">
            <div>
                <label for="f-de" class="mb-1 block text-xs text-text-muted">De</label>
                <input id="f-de" type="date" wire:model.live="dataDe" class="w-full rounded-[9px] border border-border-muted bg-surface px-3 py-2 text-sm">
            </div>
            <div>
                <label for="f-ate" class="mb-1 block text-xs text-text-muted">Até</label>
                <input id="f-ate" type="date" wire:model.live="dataAte" class="w-full rounded-[9px] border border-border-muted bg-surface px-3 py-2 text-sm">
            </div>
            <div>
                <label for="f-ano" class="mb-1 block text-xs text-text-muted">Ano</label>
                <select id="f-ano" wire:model.live="ano" class="w-full rounded-[9px] border border-border-muted bg-surface px-3 py-2 text-sm">
                    <option value="">Todos</option>
                    @foreach ($anos as $a)
                        <option value="{{ $a }}">{{ $a }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="f-palestrante" class="mb-1 block text-xs text-text-muted">Palestrante</label>
                <select id="f-palestrante" wire:model.live="palestrante" class="w-full rounded-[9px] border border-border-muted bg-surface px-3 py-2 text-sm">
                    <option value="">Todos</option>
                    @foreach ($palestrantes as $p)
                        <option value="{{ $p->slug }}">{{ $p->nome }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="f-tema" class="mb-1 block text-xs text-text-muted">Tema</label>
                <select id="f-tema" wire:model.live="assunto" class="w-full rounded-[9px] border border-border-muted bg-surface px-3 py-2 text-sm">
                    <option value="">Todos</option>
                    @foreach ($assuntos as $a)
                        <option value="{{ $a->slug }}">{{ $a->nome }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="f-video" class="mb-1 block text-xs text-text-muted">Vídeo</label>
                <select id="f-video" wire:model.live="video" class="w-full rounded-[9px] border border-border-muted bg-surface px-3 py-2 text-sm">
                    <option value="">Todos</option>
                    <option value="com">Com vídeo</option>
                    <option value="sem">Sem vídeo</option>
                </select>
            </div>
            <div class="sm:col-span-2">
                <label for="f-titulo" class="mb-1 block text-xs text-text-muted">Título</label>
                <input id="f-titulo" type="search" wire:model.live.debounce.350ms="q" placeholder="Buscar por título…"
                       class="w-full rounded-[9px] border border-border-muted bg-surface px-3 py-2 text-sm">
            </div>
        </div>

        @if (count($filtrosAtivos) > 0)
            <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-border-muted pt-4">
                <span class="text-xs text-text-muted">Filtros ativos:</span>
                @foreach ($filtrosAtivos as $chip)
                    <span class="inline-flex items-center gap-1.5 rounded-pill bg-cream py-1 pl-3 pr-1 text-[12.5px] text-[#4a4663]">
                        {{ $chip['rotulo'] }}
                        <button type="button" wire:click="removerFiltro('{{ $chip['chave'] }}')" aria-label="Remover filtro {{ $chip['rotulo'] }}"
                                class="flex size-[18px] items-center justify-center rounded-full bg-primary/10 text-[13px] text-primary hover:bg-primary/20">×</button>
                    </span>
                @endforeach
                <button type="button" wire:click="limparFiltros" class="ml-auto text-[13px] font-medium text-secondary hover:underline">Limpar tudo</button>
            </div>
        @endif
    </div>

    @if ($palestras->total() > 0)
        <p class="mb-5 mt-6 text-[13.5px] text-text-muted">Mostrando {{ $palestras->firstItem() }}–{{ $palestras->lastItem() }} de {{ $palestras->total() }} palestra(s)</p>
    @endif

    @if ($palestras->isEmpty())
        <div class="rounded-2xl border border-dashed border-[#DAD5E6] bg-white px-6 py-16 text-center">
            <div class="mx-auto mb-4 flex size-16 items-center justify-center rounded-full bg-cream text-2xl" aria-hidden="true">🔍</div>
            <h3 class="font-display text-lg font-semibold text-primary">Nenhuma palestra encontrada</h3>
            <p class="mx-auto mt-2 max-w-md text-sm text-text-muted">Ajuste o período, o tema ou a busca para ver mais resultados.</p>
            <button type="button" wire:click="limparFiltros" class="mt-5 rounded-pill bg-primary px-5 py-2.5 text-sm font-medium text-white hover:bg-primary/90">Limpar filtros</button>
        </div>
    @elseif ($visao === 'list')
        <div class="flex flex-col gap-3.5">
            @foreach ($palestras as $palestra)
                <x-palestra.linha :palestra="$palestra" wire:key="linha-{{ $palestra->id }}" />
            @endforeach
        </div>
        <div class="mt-9">{{ $palestras->onEachSide(1)->links() }}</div>
    @else
        <div class="grid gap-[22px] sm:grid-cols-2 desktop-sm:grid-cols-3">
            @foreach ($palestras as $palestra)
                <x-palestra.card :palestra="$palestra" wire:key="card-{{ $palestra->id }}" />
            @endforeach
        </div>
        <div class="mt-9">{{ $palestras->onEachSide(1)->links() }}</div>
    @endif
</div>
