{{-- Placar SEO: exibe nota de 0-100 e checklist de itens --}}
@php
    $nota = $placar['nota'] ?? 0;
    $itens = $placar['itens'] ?? [];
    $cor = match(true) {
        $nota >= 70 => 'text-green-600',
        $nota >= 40 => 'text-yellow-600',
        default     => 'text-red-600',
    };
@endphp

<div class="space-y-3">
    <div class="flex items-center gap-3">
        <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Nota SEO:</span>
        <span class="text-2xl font-bold {{ $cor }}">{{ $nota }}/100</span>
    </div>

    @if(count($itens) > 0)
        <ul class="space-y-1">
            @foreach($itens as $item)
                <li class="flex items-center gap-2 text-sm">
                    @if($item['ok'])
                        <span class="text-green-600">✓</span>
                        <span class="text-gray-700 dark:text-gray-300">{{ $item['rotulo'] }}</span>
                    @else
                        <span class="text-red-500">✗</span>
                        <span class="text-gray-500 dark:text-gray-400">{{ $item['rotulo'] }}</span>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</div>
