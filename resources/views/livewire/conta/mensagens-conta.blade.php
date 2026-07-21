{{-- Thiago Mourão — https://github.com/MouraoBSB — 2026-07-21 --}}
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h2 class="font-display text-xl font-semibold text-primary">Minhas Mensagens</h2>
        @unless ($mostrandoForm)
            <button type="button" wire:click="novo"
                    class="rounded-pill bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary/90">
                Nova mensagem
            </button>
        @endunless
    </div>

    @if ($mostrandoForm)
        <section class="rounded-lg bg-white p-6 shadow-card">
            <form wire:submit="salvar" class="space-y-4">
                {{ $this->form }}
                <div class="flex gap-2">
                    <button type="submit" class="rounded-pill bg-primary px-4 py-2 text-sm font-medium text-white">
                        {{ $editandoId ? 'Salvar alterações' : 'Enviar para curadoria' }}
                    </button>
                    <button type="button" wire:click="cancelar" class="rounded-pill bg-surface px-4 py-2 text-sm text-text">Cancelar</button>
                </div>
            </form>
        </section>
    @endif

    {{-- Cards próprios (NÃO x-mensagem.card/linha: eles linkam incondicionalmente para
         mensagens.show, que dá 404 em pendente — D10: só título/data/status/nível, nunca o corpo). --}}
    <div class="grid gap-3">
        @forelse ($itens as $item)
            <article class="flex items-center justify-between rounded-lg bg-white p-4 shadow-card">
                <div>
                    <p class="font-medium text-text">{{ $item->titulo }}</p>
                    <p class="text-sm text-text-muted">{{ $item->data_recebimento?->format('d/m/Y') ?: '—' }}</p>
                </div>
                <div class="flex items-center gap-2">
                    @if ($item->nivel === \App\Enums\VisibilidadeMensagem::Direcionada->value)
                        <span class="rounded-pill bg-border-muted px-2.5 py-0.5 text-xs font-medium text-text-secondary">Direcionada</span>
                    @endif
                    <span @class([
                        'rounded-pill px-2.5 py-0.5 text-xs font-medium capitalize',
                        'bg-accent/15 text-success' => $item->status === \App\Models\Mensagem::STATUS_PUBLICADO,
                        'bg-border-muted text-text-secondary' => $item->status !== \App\Models\Mensagem::STATUS_PUBLICADO,
                    ])>{{ $item->status }}</span>
                    {{-- Só a PENDENTE é editável pelo médium (policy editarPendente); após publicada,
                         a posse passa ao curador (D10) — sem link, sem corpo, sem botão aqui. --}}
                    @if ($item->status === \App\Models\Mensagem::STATUS_PENDENTE)
                        <button type="button" wire:click="editar({{ $item->id }})" class="text-sm text-primary hover:underline">Editar</button>
                    @endif
                </div>
            </article>
        @empty
            <p class="text-sm text-text-muted">Você ainda não lançou nenhuma mensagem.</p>
        @endforelse
    </div>
</div>
