@props(['palestra', 'variante' => 'solido'])

@php($f = $palestra->formato)
@if ($variante === 'claro')
    <span class="inline-flex items-center gap-1.5 rounded-pill px-2.5 py-0.5 text-[11px] font-semibold"
          style="color: var(--color-{{ $f['cor'] }}); background-color: color-mix(in srgb, var(--color-{{ $f['cor'] }}) 14%, transparent);">
        {{ $f['rotulo'] }}
    </span>
@else
    <span class="inline-flex items-center gap-1.5 rounded-pill bg-white/20 px-2.5 py-0.5 text-[10px] font-semibold text-white backdrop-blur">
        {{ $f['rotulo'] }}
    </span>
@endif
