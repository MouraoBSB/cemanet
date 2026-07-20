{{-- Thiago Mourão — https://github.com/MouraoBSB — 2026-07-20 --}}
{{-- Legenda de bolinhas dos NÍVEIS presentes no resultado. $niveis = Collection<VisibilidadeMensagem> (sem null).
     O call-site já a envolve em @auth; aqui só renderiza quando há ao menos um nível. --}}
@props(['niveis'])
@if (filled($niveis))
    <div class="flex flex-wrap items-center gap-x-4 gap-y-1.5 text-[12px] text-text-secondary">
        <span class="font-mono text-[10.5px] uppercase tracking-[0.08em] text-text-muted">Nível de acesso:</span>
        @foreach ($niveis as $nivel)
            <span class="inline-flex items-center gap-1.5">
                <span class="inline-block size-2 rounded-full" style="background:{{ $nivel->cor() }}" aria-hidden="true"></span>
                {{ $nivel->rotulo() }}
            </span>
        @endforeach
    </div>
@endif
