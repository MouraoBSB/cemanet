{{-- Placeholder mínimo — versão completa (mega-menu + off-canvas + busca) na Task 2. --}}
<header class="sticky top-0 z-50 bg-white shadow-[0_1px_0_var(--color-border)]">
    <div class="mx-auto flex max-w-[1240px] items-center gap-6 px-6 py-3">
        <a href="{{ route('home') }}" class="shrink-0">
            <img src="{{ asset('images/logos/logo-horizontal.png') }}"
                 alt="CEMA — Centro Espírita Maria Madalena" class="h-11 w-auto" width="180" height="46">
        </a>
        <nav class="ml-auto" aria-label="Navegação principal">
            <ul class="flex gap-4">
                @foreach (config('navegacao.menu') as $item)
                    <li>
                        @if (($item['ativo'] ?? false) && ($item['rota'] ?? null))
                            <a href="{{ route($item['rota']) }}" class="font-ui text-sm text-primary hover:underline">{{ $item['rotulo'] }}</a>
                        @else
                            <span class="font-ui text-sm text-text-muted" aria-disabled="true">{{ $item['rotulo'] }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </nav>
    </div>
</header>
