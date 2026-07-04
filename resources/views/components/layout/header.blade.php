@php($menu = config('navegacao.menu'))

<header class="sticky top-0 z-50 bg-white shadow-[0_1px_0_var(--color-border)]"
        x-data="{ menuMobile: false }">
    {{-- Faixa 1: logo + busca + auth/hambúrguer --}}
    <div class="mx-auto flex max-w-[1240px] items-center gap-5 px-6 py-3">
        <a href="{{ route('home') }}" class="shrink-0" aria-label="Página inicial do CEMA">
            <img src="{{ asset('images/logos/logo-horizontal.png') }}"
                 alt="CEMA — Centro Espírita Maria Madalena" class="h-11 w-auto" width="180" height="46">
        </a>

        {{-- Busca (desktop) --}}
        <form role="search" method="GET" action="{{ route('palestras.index') }}"
              class="hidden flex-1 desktop-sm:flex max-w-[420px] items-center rounded-pill border border-border bg-surface">
            <label for="busca-topo" class="sr-only">Pesquisar palestras</label>
            <input id="busca-topo" type="search" name="q" placeholder="Pesquisar palestras…"
                   class="w-full bg-transparent px-4 py-2 font-sans text-sm text-text outline-none">
            <button type="submit" aria-label="Buscar"
                    class="m-1 flex size-8 items-center justify-center rounded-full bg-primary text-white">
                <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/>
                </svg>
            </button>
        </form>

        {{-- Auth (desktop) --}}
        <div class="ml-auto hidden items-center gap-3 desktop-sm:flex font-ui text-sm">
            @guest
                <a href="{{ route('login') }}" class="font-semibold text-primary hover:underline">Entrar</a>
                <a href="{{ route('register') }}" class="rounded-pill bg-primary px-4 py-1.5 font-semibold text-white hover:bg-primary/90">Cadastrar</a>
            @else
                @php($u = auth()->user())
                <div class="relative" x-data="{ aberto: false }" @click.outside="aberto = false">
                    <button type="button" @click="aberto = !aberto" :aria-expanded="aberto" aria-haspopup="true"
                            class="flex items-center gap-2 rounded-pill py-1 pl-1 pr-2 hover:bg-surface">
                        @if ($u->perfil?->foto_thumb_url)
                            <img src="{{ $u->perfil->foto_thumb_url }}" alt="" class="size-8 rounded-full object-cover">
                        @else
                            <span class="flex size-8 items-center justify-center rounded-full bg-primary/10 text-xs font-semibold text-primary">{{ $u->iniciais }}</span>
                        @endif
                        <span class="text-text">Olá, {{ \Illuminate\Support\Str::of($u->name)->explode(' ')->first() }}</span>
                        <span aria-hidden="true" class="text-[9px] text-text-muted">▾</span>
                    </button>
                    <div x-show="aberto" x-cloak x-transition
                         class="absolute right-0 top-full z-50 mt-1 min-w-[180px] rounded-md border border-border bg-white py-1 shadow-elevated">
                        <a href="{{ route('conta.painel') }}" class="block px-4 py-2 text-text hover:bg-surface hover:text-primary">Minha Conta</a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="block w-full px-4 py-2 text-left text-text hover:bg-surface hover:text-primary">Sair</button>
                        </form>
                    </div>
                </div>
            @endguest
        </div>

        {{-- Hambúrguer (mobile) --}}
        <button type="button" class="ml-auto desktop-sm:hidden rounded-md p-2 text-primary"
                @click="menuMobile = true" :aria-expanded="menuMobile" aria-controls="menu-mobile" aria-label="Abrir menu">
            <svg viewBox="0 0 24 24" class="size-6" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path d="M3 6h18M3 12h18M3 18h18"/>
            </svg>
        </button>
    </div>

    {{-- Faixa 2: mega-menu (desktop) --}}
    <nav class="hidden bg-primary desktop-sm:block" aria-label="Navegação principal">
        <ul class="mx-auto flex max-w-[1240px] items-stretch px-6">
            @foreach ($menu as $item)
                @php($temItens = ! empty($item['itens']))
                <li class="group relative">
                    @if (($item['ativo'] ?? false) && ($item['rota'] ?? null))
                        <a href="{{ route($item['rota']) }}"
                           class="flex items-center gap-1 px-4 py-3 font-ui text-sm text-[#efeaf7] hover:bg-white/10"
                           @if($temItens) aria-haspopup="true" aria-expanded="false" @endif>{{ $item['rotulo'] }}@if($temItens)<span aria-hidden="true" class="text-[9px]">▾</span>@endif</a>
                    @else
                        <span class="flex cursor-default items-center gap-1 px-4 py-3 font-ui text-sm text-[#efeaf7]/60"
                              aria-disabled="true" @if($temItens) aria-haspopup="true" aria-expanded="false" @endif>{{ $item['rotulo'] }}@if($temItens)<span aria-hidden="true" class="text-[9px]">▾</span>@endif</span>
                    @endif

                    @if($temItens)
                        <div class="invisible absolute left-0 top-full z-50 min-w-[232px] translate-y-2 rounded-b-xl border-t-[3px] border-gold bg-white p-2 opacity-0 shadow-elevated transition group-hover:visible group-hover:translate-y-0 group-hover:opacity-100">
                            <ul>
                                @foreach ($item['itens'] as $sub)
                                    <li>
                                        @if (($sub['ativo'] ?? false) && ($sub['rota'] ?? null))
                                            <a href="{{ route($sub['rota']) }}" class="block rounded-md px-3.5 py-2 font-ui text-sm text-text hover:bg-surface hover:text-primary">{{ $sub['rotulo'] }}</a>
                                        @else
                                            <span class="block rounded-md px-3.5 py-2 font-ui text-sm text-text-muted" aria-disabled="true">{{ $sub['rotulo'] }}</span>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>
    </nav>

    {{-- Off-canvas (mobile) --}}
    <div x-show="menuMobile" x-cloak class="fixed inset-0 z-[90] bg-[rgba(38,36,46,0.55)] desktop-sm:hidden"
         @click="menuMobile = false" x-transition.opacity></div>
    <aside id="menu-mobile" x-show="menuMobile" x-cloak role="dialog" aria-modal="true" aria-label="Menu"
           class="fixed inset-y-0 left-0 z-[95] flex w-[300px] max-w-[88vw] flex-col bg-white desktop-sm:hidden"
           x-transition:enter="transition ease-out duration-200" x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0"
           x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-x-0" x-transition:leave-end="-translate-x-full"
           @keydown.escape.window="menuMobile = false" x-trap="menuMobile">
        <div class="flex items-center justify-between border-b border-border-muted px-4 py-4">
            <img src="{{ asset('images/logos/logo-horizontal.png') }}" alt="CEMA" class="h-9 w-auto" width="150" height="38">
            <button type="button" class="flex size-9 items-center justify-center rounded-md bg-surface text-xl text-text" @click="menuMobile = false" aria-label="Fechar menu">×</button>
        </div>
        <div class="border-b border-border-muted px-4 py-3">
            @guest
                <div class="flex gap-2">
                    <a href="{{ route('login') }}" class="flex-1 rounded-pill border border-primary px-4 py-2 text-center text-sm font-semibold text-primary">Entrar</a>
                    <a href="{{ route('register') }}" class="flex-1 rounded-pill bg-primary px-4 py-2 text-center text-sm font-semibold text-white">Cadastrar</a>
                </div>
            @else
                @php($u = auth()->user())
                <p class="mb-2 font-mono text-xs uppercase tracking-[0.08em] text-text-muted">Minha conta</p>
                <a href="{{ route('conta.painel') }}" class="flex items-center gap-2 py-1 text-text">
                    <span class="flex size-8 items-center justify-center rounded-full bg-primary/10 text-xs font-semibold text-primary">{{ $u->iniciais }}</span>
                    {{ $u->name }}
                </a>
                <form method="POST" action="{{ route('logout') }}" class="mt-1">
                    @csrf
                    <button type="submit" class="py-1 text-sm text-text-muted hover:text-primary">Sair</button>
                </form>
            @endguest
        </div>
        <nav class="flex-1 overflow-y-auto px-2.5 py-2" aria-label="Navegação principal (mobile)">
            <p class="mx-2 mb-1 mt-2 font-mono text-xs uppercase tracking-[0.08em] text-text-muted">Menu</p>
            <ul>
                @foreach ($menu as $item)
                    <li class="border-b border-[#f2f1f4]">
                        @if (empty($item['itens']))
                            @if (($item['ativo'] ?? false) && ($item['rota'] ?? null))
                                <a href="{{ route($item['rota']) }}" class="block px-2 py-3 font-ui text-[15px] font-medium text-text">{{ $item['rotulo'] }}</a>
                            @else
                                <span class="block px-2 py-3 font-ui text-[15px] text-text-muted" aria-disabled="true">{{ $item['rotulo'] }}</span>
                            @endif
                        @else
                            <details>
                                <summary class="flex cursor-pointer items-center justify-between px-2 py-3 font-ui text-[15px] font-medium text-text">
                                    {{ $item['rotulo'] }}<span aria-hidden="true" class="text-[10px]">▾</span>
                                </summary>
                                <div class="pb-1.5">
                                    @foreach ($item['itens'] as $sub)
                                        @if (($sub['ativo'] ?? false) && ($sub['rota'] ?? null))
                                            <a href="{{ route($sub['rota']) }}" class="block py-2 pl-[18px] pr-2 font-ui text-sm text-text">{{ $sub['rotulo'] }}</a>
                                        @else
                                            <span class="block py-2 pl-[18px] pr-2 font-ui text-sm text-text-muted" aria-disabled="true">{{ $sub['rotulo'] }}</span>
                                        @endif
                                    @endforeach
                                </div>
                            </details>
                        @endif
                    </li>
                @endforeach
            </ul>
        </nav>
    </aside>
</header>
