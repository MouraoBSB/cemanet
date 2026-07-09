{{-- Bloco "Serviço": pares rótulo/valor com os dados práticos do evento. --}}
<div class="mt-9 rounded-xl border border-border-muted bg-white p-6">
    <p class="font-mono text-[11px] uppercase tracking-[0.1em] text-accent">Serviço</p>

    <div class="mt-4 grid gap-5" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
        <div>
            <p class="font-mono text-[10.5px] uppercase tracking-[0.1em] text-text-muted">Data</p>
            <p class="mt-1 font-semibold text-text-ink">{{ $evento->periodo }}</p>
        </div>

        @if ($evento->hora_inicio)
            <div>
                <p class="font-mono text-[10.5px] uppercase tracking-[0.1em] text-text-muted">Horário</p>
                <p class="mt-1 font-semibold text-text-ink">{{ $evento->hora_inicio }}{{ $evento->hora_fim ? ' – '.$evento->hora_fim : '' }}</p>
            </div>
        @endif

        <div>
            <p class="font-mono text-[10.5px] uppercase tracking-[0.1em] text-text-muted">Local</p>
            <p class="mt-1 font-semibold text-text-ink">{{ $evento->local ?: 'Local a confirmar' }}</p>
        </div>

        <div>
            <p class="font-mono text-[10.5px] uppercase tracking-[0.1em] text-text-muted">Endereço</p>
            <p class="mt-1 font-semibold text-text-ink">{{ config('cema.endereco') }}</p>
        </div>

        <div>
            <p class="font-mono text-[10.5px] uppercase tracking-[0.1em] text-text-muted">Categoria</p>
            <p class="mt-1 font-semibold text-text-ink">{{ $evento->categoria?->nome ?? '—' }}</p>
        </div>

        <div>
            <p class="font-mono text-[10.5px] uppercase tracking-[0.1em] text-text-muted">Departamento(s)</p>
            <p class="mt-1 font-semibold text-text-ink">{{ $evento->departamentos->pluck('sigla')->join(', ') ?: '—' }}</p>
        </div>
    </div>
</div>
