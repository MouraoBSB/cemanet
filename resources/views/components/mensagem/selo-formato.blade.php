@props(['formato'])

{{-- Selo de FORMATO (não de nível): pílula + ícone (pena/ondas/moldura) + rótulo, cores AA.
     Compartilhado entre lista, single e perfil (SPEC §4.4/§6.6). NÃO é selo de visibilidade. --}}
@php
    $cfg = match ($formato) {
        \App\Enums\FormatoMensagem::Psicografia => ['fundo' => 'rgba(78,68,131,0.10)', 'cor' => '#4E4483', 'icone' => 'pena'],
        \App\Enums\FormatoMensagem::Psicofonia => ['fundo' => 'rgba(110,159,203,0.16)', 'cor' => '#356197', 'icone' => 'ondas'],
        \App\Enums\FormatoMensagem::Pictografia => ['fundo' => 'rgba(137,171,152,0.20)', 'cor' => '#3f7256', 'icone' => 'moldura'],
        default => null,
    };
@endphp
@if ($cfg)
    <span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 rounded-pill px-2.5 py-1 font-mono text-[10.5px] font-medium uppercase tracking-[0.08em]']) }}
          style="background:{{ $cfg['fundo'] }};color:{{ $cfg['cor'] }}">
        @switch($cfg['icone'])
            @case('pena')
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.24 12.24a6 6 0 0 0-8.49-8.49L5 10.5V19h8.5z"/><line x1="16" y1="8" x2="2" y2="22"/><line x1="17.5" y1="15" x2="9" y2="15"/></svg>
                @break
            @case('ondas')
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 8c2.5-3 4.5-3 7 0s4.5 3 7 0 4.5-3 6 0"/><path d="M2 16c2.5-3 4.5-3 7 0s4.5 3 7 0 4.5-3 6 0"/></svg>
                @break
            @case('moldura')
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>
                @break
        @endswitch
        {{ $formato->rotulo() }}
    </span>
@endif
