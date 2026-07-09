{{-- Até 3 eventos relacionados (mesma categoria, com fallback), vindos de EventoController::show. --}}
@if ($relacionados->isNotEmpty())
    <section class="border-t border-border-muted bg-white">
        <div class="mx-auto max-w-[1100px] px-6 py-12">
            <div class="mb-5 flex items-center justify-between">
                <h2 class="font-display text-2xl font-semibold text-primary">Outros eventos</h2>
                <a href="{{ route('eventos.index') }}" class="text-sm font-semibold text-secondary hover:underline">Ver todos →</a>
            </div>
            <div class="grid gap-5 sm:grid-cols-2 desktop-sm:grid-cols-3">
                @foreach ($relacionados as $r)
                    <x-evento.card :evento="$r" :compacto="true" />
                @endforeach
            </div>
        </div>
    </section>
@endif
