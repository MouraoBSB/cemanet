{{-- Thiago Mourão — https://github.com/MouraoBSB — 2026-07-20 --}}
{{-- Badge de NÍVEL de visibilidade: pílula AA (fundo translúcido + texto escurecido) + cadeado se restrito.
     NULL-GUARD (B1): $visibilidade pode ser null (mensagem nivel=null vista pelo admin) => NÃO renderiza nada.
     Fonte da cor/rótulo: App\Enums\VisibilidadeMensagem (cor/corFundo/corTexto/ehRestrito/rotulo). --}}
@props(['visibilidade'])
@if ($visibilidade)
    <span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 rounded-pill px-2.5 py-1 text-[11px] font-semibold']) }}
          style="background:{{ $visibilidade->corFundo() }};color:{{ $visibilidade->corTexto() }}">
        @if ($visibilidade->ehRestrito())
            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-label="Acesso restrito"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
        @else
            <span class="inline-block size-2 rounded-full" style="background:{{ $visibilidade->cor() }}" aria-hidden="true"></span>
        @endif
        {{ $visibilidade->rotulo() }}
    </span>
@endif
