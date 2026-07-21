{{-- Thiago Mourão — https://github.com/MouraoBSB — 2026-07-21 --}}
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h2 class="font-display text-xl font-semibold text-primary">Curadoria</h2>
    </div>

    @if ($mostrandoForm)
        <section class="rounded-lg bg-white p-6 shadow-card">
            <form wire:submit="salvar" class="space-y-4">
                {{ $this->form }}
                <div class="flex gap-2">
                    <button type="submit" class="rounded-pill bg-primary px-4 py-2 text-sm font-medium text-white">
                        Salvar
                    </button>
                    <button
                        type="button"
                        wire:click="publicar({{ $editandoId }})"
                        wire:confirm="Publicar esta mensagem? O nível de acesso definido passa a valer imediatamente no site."
                        class="rounded-pill bg-primary px-4 py-2 text-sm font-medium text-white"
                    >
                        Publicar
                    </button>
                    <button type="button" wire:click="cancelar" class="rounded-pill bg-surface px-4 py-2 text-sm text-text">Cancelar</button>
                </div>
            </form>
        </section>

        @if ($editando)
            <section class="rounded-lg bg-white p-6 shadow-card">
                <x-conta.historico-mensagem :mensagem="$editando" />
            </section>
        @endif
    @else
        {{-- Fila de TODAS as pendentes (não só as do curador) — molde de card próprio, como
             mensagens-conta.blade.php: nunca linka para mensagens.show (item pendente dá 404). --}}
        <div class="grid gap-3">
            @forelse ($itens as $item)
                <article class="flex items-center justify-between rounded-lg bg-white p-4 shadow-card">
                    <div>
                        <p class="font-medium text-text">{{ $item->titulo }}</p>
                        <p class="text-sm text-text-muted">
                            {{ $item->medium?->name ?? 'Importada do legado' }}
                            &middot;
                            {{ $item->data_recebimento?->format('d/m/Y') ?: '—' }}
                        </p>
                        {{-- Task 11: alerta quando o PRÓPRIO médium editou a mensagem após o lançamento. --}}
                        @if (in_array($item->id, $editadasPeloAutor, true))
                            <p class="mt-1 text-xs font-medium text-orange">Editada pelo autor após o lançamento</p>
                        @endif
                    </div>
                    <button type="button" wire:click="editar({{ $item->id }})" class="text-sm text-primary hover:underline">Editar</button>
                </article>
            @empty
                <p class="text-sm text-text-muted">Nenhuma mensagem pendente no momento.</p>
            @endforelse
        </div>
    @endif
</div>
