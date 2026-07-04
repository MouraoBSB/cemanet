{{-- Thiago Mourão — https://github.com/MouraoBSB — 2026-07-04 --}}
@props(['ativo' => 'painel'])
@php
    $itens = [
        ['chave' => 'painel', 'rotulo' => 'Painel', 'rota' => 'conta.painel'],
        ['chave' => 'perfil', 'rotulo' => 'Meu Perfil', 'rota' => 'conta.perfil'],
    ];
@endphp
<nav aria-label="Navegação da conta"
     class="flex gap-2 overflow-x-auto pb-1 desktop-sm:flex-col desktop-sm:gap-1 desktop-sm:overflow-visible">
    @foreach ($itens as $item)
        @php($atual = $ativo === $item['chave'])
        <a href="{{ route($item['rota']) }}" @if ($atual) aria-current="page" @endif
           class="shrink-0 rounded-pill px-4 py-2 font-ui text-sm font-medium transition desktop-sm:rounded-md
                  {{ $atual ? 'bg-primary text-white' : 'bg-surface text-text hover:bg-border-muted' }}">
            {{ $item['rotulo'] }}
        </a>
    @endforeach
</nav>
