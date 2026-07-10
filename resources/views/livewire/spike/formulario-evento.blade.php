{{-- SPIKE (descartável): Filament Form embutido numa página do site. --}}
<div>
    @if ($eventoSalvoId)
        <p id="spike-ok" class="mb-6 rounded-xl border border-green-300 bg-green-50 p-4 font-semibold text-green-900">
            Evento #{{ $eventoSalvoId }} salvo com sucesso.
        </p>
    @endif

    <form wire:submit="salvar" class="space-y-6">
        {{ $this->form }}

        <button type="submit"
                class="rounded-pill bg-primary px-6 py-3 text-sm font-semibold text-white transition hover:opacity-90">
            Salvar evento
        </button>
    </form>

    <x-filament-actions::modals />
</div>
