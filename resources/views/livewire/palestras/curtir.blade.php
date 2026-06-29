<div x-data="{ curtido: $persist(false).as('curtida_palestra_{{ $palestraId }}') }">
    <button type="button"
            @click="curtido ? ($wire.descurtir(), curtido = false) : ($wire.curtir(), curtido = true)"
            :aria-pressed="curtido"
            class="flex items-center gap-2 rounded-pill border border-border bg-white px-4 py-2 text-[13px] font-semibold transition"
            :class="curtido ? 'text-danger border-danger' : 'text-primary'">
        <span x-text="curtido ? '♥' : '♡'" aria-hidden="true"></span>
        <span x-text="curtido ? 'Curtido' : 'Curtir'">Curtir</span>
        <span class="rounded-full bg-cream px-2 py-0.5 text-[12px] text-primary">{{ number_format($curtidas, 0, ',', '.') }}</span>
    </button>
</div>
