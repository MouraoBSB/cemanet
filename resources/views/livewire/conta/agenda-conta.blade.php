{{-- Thiago Mourão — https://github.com/MouraoBSB — 2026-07-15 --}}
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h2 class="font-display text-xl font-semibold text-primary">Agenda da Reforma Íntima</h2>
        <button type="button" wire:click="novo"
                class="rounded-pill bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary/90">
            Novo dia
        </button>
    </div>

    @if ($mostrandoForm)
        <section class="rounded-lg bg-white p-6 shadow-card">
            <form wire:submit="salvar" class="space-y-4">
                {{ $this->form }}
                <div class="flex gap-2">
                    <button type="submit" class="rounded-pill bg-primary px-4 py-2 text-sm font-medium text-white">Salvar</button>
                    <button type="button" wire:click="cancelar" class="rounded-pill bg-surface px-4 py-2 text-sm text-text">Cancelar</button>
                </div>
            </form>
        </section>
    @endif

    <div class="grid gap-3">
        @forelse ($itens as $item)
            <article class="flex items-center justify-between rounded-lg bg-white p-4 shadow-card">
                <div>
                    <p class="font-medium text-text">{{ $item->data?->format('d/m/Y') }}</p>
                    <p class="text-sm text-text-muted">{{ $item->meta_dia_titulo ?: '—' }}</p>
                </div>
                <span @class([
                    'rounded-pill px-2.5 py-0.5 text-xs font-medium capitalize',
                    'bg-accent/15 text-success' => $item->status === \App\Models\AgendaDia::STATUS_PUBLICADO,
                    'bg-border-muted text-text-secondary' => $item->status !== \App\Models\AgendaDia::STATUS_PUBLICADO,
                ])>{{ $item->status }}</span>
            </article>
        @empty
            <p class="text-sm text-text-muted">Nenhum dia de agenda no seu departamento ainda.</p>
        @endforelse
    </div>
</div>
