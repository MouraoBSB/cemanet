<div>
    {{-- Barra de filtros --}}
    <div class="rounded-2xl border border-border-muted bg-white p-5 shadow-card sm:px-6">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <h2 class="inline-flex items-center gap-2 font-display text-base font-semibold text-primary">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                Filtrar mensagens
                <span class="rounded-pill bg-cream px-2.5 py-0.5 text-xs font-medium text-primary">Total {{ $mensagens->total() }}</span>
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
                    <button type="button" wire:click="alternarVisao('grid')" aria-label="Ver em grade" aria-pressed="{{ $visao === 'grid' ? 'true' : 'false' }}"
                            @class(['flex size-8 items-center justify-center', 'bg-white text-primary shadow-sm' => $visao === 'grid', 'bg-transparent text-text-muted' => $visao !== 'grid'])>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                    </button>
                    <button type="button" wire:click="alternarVisao('list')" aria-label="Ver em lista" aria-pressed="{{ $visao === 'list' ? 'true' : 'false' }}"
                            @class(['flex size-8 items-center justify-center', 'bg-white text-primary shadow-sm' => $visao === 'list', 'bg-transparent text-text-muted' => $visao !== 'list'])>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
                    </button>
                </div>
            </div>
        </div>

        <div class="grid gap-3.5 sm:grid-cols-2 desktop-sm:grid-cols-3">
            <div>
                <label for="f-de" class="mb-1 block text-xs text-text-muted">De</label>
                <input id="f-de" type="date" wire:model.live="dataDe" class="w-full rounded-[9px] border border-border-muted bg-surface px-3 py-2 text-sm text-text-ink">
            </div>
            <div>
                <label for="f-ate" class="mb-1 block text-xs text-text-muted">Até</label>
                <input id="f-ate" type="date" wire:model.live="dataAte" class="w-full rounded-[9px] border border-border-muted bg-surface px-3 py-2 text-sm text-text-ink">
            </div>
            <div>
                <label for="f-autor" class="mb-1 block text-xs text-text-muted">Autor espiritual</label>
                <select id="f-autor" wire:model.live="autor" class="w-full rounded-[9px] border border-border-muted bg-surface px-3 py-2 text-sm text-text-ink">
                    <option value="">Todos os autores</option>
                    <option value="sem-assinatura">Sem assinatura</option>
                    @foreach ($autores as $a)
                        <option value="{{ $a->slug }}">{{ $a->nome }}</option>
                    @endforeach
                </select>
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

    @if ($mensagens->total() > 0)
        <p class="mb-5 mt-6 text-[13.5px] text-text-muted">
            Mostrando {{ $mensagens->firstItem() }}–{{ $mensagens->lastItem() }} de {{ $mensagens->total() }} {{ $mensagens->total() === 1 ? 'mensagem' : 'mensagens' }}
        </p>
    @endif

    @if ($mensagens->isEmpty())
        <div class="rounded-2xl border border-dashed border-[#DAD5E6] bg-white px-6 py-16 text-center">
            <div class="mx-auto mb-4 flex size-16 items-center justify-center rounded-full bg-cream text-primary" aria-hidden="true">
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5" stroke-linecap="round"/></svg>
            </div>
            <h3 class="font-display text-lg font-semibold text-primary">Nenhuma mensagem encontrada</h3>
            <p class="mx-auto mt-2 max-w-md text-sm text-text-muted">Ajuste o período ou o autor para ver mais resultados.</p>
            <button type="button" wire:click="limparFiltros" class="mt-5 rounded-pill bg-primary px-5 py-2.5 text-sm font-medium text-white hover:bg-primary/90">Limpar filtros</button>
        </div>
    @elseif ($visao === 'list')
        <div class="flex flex-col gap-3">
            @foreach ($mensagens as $m)
                <x-mensagem.linha :mensagem="$m" wire:key="linha-{{ $m->id }}" />
            @endforeach
        </div>
        <div class="mt-9">{{ $mensagens->onEachSide(1)->links() }}</div>
    @else
        <div class="grid grid-cols-[repeat(auto-fill,minmax(280px,1fr))] gap-[22px]">
            @foreach ($mensagens as $m)
                <x-mensagem.card :mensagem="$m" variante="lista" wire:key="card-{{ $m->id }}" />
            @endforeach
        </div>
        <div class="mt-9">{{ $mensagens->onEachSide(1)->links() }}</div>
    @endif
</div>
