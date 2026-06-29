@if ($palestra->referencias->isNotEmpty() || filled($palestra->referencias_evangelicas))
    <div class="mt-8 border-t border-border-muted pt-6">
        @if ($palestra->referencias->isNotEmpty())
            <h2 class="mb-4 font-display text-lg font-semibold text-primary">Referências doutrinárias</h2>
            <div class="flex flex-col gap-3">
                @foreach ($palestra->referencias as $ref)
                    <div class="flex gap-3 rounded-xl border border-[#ECE6D6] bg-[#FAF8F2] p-4">
                        <span class="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-md bg-primary text-white shadow-[inset_3px_0_0_0_var(--color-gold)]" aria-hidden="true">📖</span>
                        <div>
                            <p class="font-display text-[15px] font-semibold text-primary">{{ $ref->obra }}@if ($ref->autor)<span class="font-normal text-text-muted"> · {{ $ref->autor }}</span>@endif</p>
                            @if ($ref->nota)
                                <p class="mt-1 text-sm text-text-secondary">{{ $ref->nota }}</p>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        @if (filled($palestra->referencias_evangelicas))
            <p class="mt-4 text-sm text-text-secondary">
                <span class="font-semibold text-primary">Referências evangélicas</span> — {{ $palestra->referencias_evangelicas }}
            </p>
        @endif
    </div>
@endif
