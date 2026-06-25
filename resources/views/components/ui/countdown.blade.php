@props(['data', 'compacto' => false])

{{-- Contagem regressiva (Alpine, ao vivo) — só para palestras futuras. --}}
@if ($data && $data->isFuture())
    <div
        x-data="{
            alvo: new Date(@js($data->toIso8601String())).getTime(),
            d: 0, h: 0, m: 0, s: 0, ativo: true,
            atualiza() {
                const diff = this.alvo - Date.now();
                this.ativo = diff > 0;
                const t = Math.max(0, diff);
                this.d = Math.floor(t / 86400000);
                this.h = Math.floor((t % 86400000) / 3600000);
                this.m = Math.floor((t % 3600000) / 60000);
                this.s = Math.floor((t % 60000) / 1000);
            },
        }"
        x-init="atualiza(); setInterval(() => atualiza(), 1000)"
        x-show="ativo"
    >
        @if ($compacto)
            <span class="inline-flex items-center gap-1 rounded-pill bg-gold/15 px-2.5 py-0.5 font-mono text-[10px] font-semibold uppercase tracking-wide text-primary">
                <span aria-hidden="true">⏳</span>
                <span>faltam <span x-text="d"></span>d <span x-text="h"></span>h</span>
            </span>
        @else
            <div class="flex items-center gap-2.5" role="timer" aria-label="Contagem regressiva para a palestra">
                @foreach (['d' => 'dias', 'h' => 'horas', 'm' => 'min', 's' => 'seg'] as $campo => $rotulo)
                    @if (! $loop->first)<span class="text-xl font-semibold text-white/40">:</span>@endif
                    <div class="min-w-[2.5rem] text-center">
                        <span class="block font-display text-2xl font-semibold tabular-nums" x-text="String({{ $campo }}).padStart(2, '0')"></span>
                        <span class="font-mono text-[10px] uppercase tracking-wide text-white/70">{{ $rotulo }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endif
