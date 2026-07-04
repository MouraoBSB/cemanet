{{-- Thiago Mourão — https://github.com/MouraoBSB — 2026-07-04 --}}
@php
    $papel = $user->roles->first()?->name;
    $nivel = $user->roles->first()?->nivel;
@endphp
<x-layout.conta titulo="Meu Perfil" ativo="perfil">
    <div x-data="{ editando: false }" class="space-y-6">
        {{-- Cabeçalho da seção --}}
        <div class="flex items-center justify-between" x-show="!editando">
            <h2 class="font-display text-xl font-semibold text-primary">Meu Perfil</h2>
            <button type="button" @click="editando = true"
                    class="rounded-pill bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary/90">Editar perfil</button>
        </div>

        {{-- VISUALIZAÇÃO --}}
        <div x-show="!editando" class="space-y-6">
            <section class="rounded-lg bg-white p-6 shadow-card">
                <h3 class="mb-4 font-display font-semibold text-primary">Dados pessoais</h3>
                <dl class="grid gap-4 sm:grid-cols-2">
                    <div><dt class="text-xs uppercase tracking-wide text-text-muted">Nome público</dt><dd class="mt-0.5 text-text">{{ $user->name }}</dd></div>
                    <div><dt class="text-xs uppercase tracking-wide text-text-muted">Data de nascimento</dt><dd class="mt-0.5 text-text">{{ $perfil->data_nascimento?->format('d/m/Y') ?? '—' }}</dd></div>
                    <div class="sm:col-span-2">
                        <dt class="text-xs uppercase tracking-wide text-text-muted">Endereço <span class="ml-1 rounded bg-surface px-1.5 py-0.5 text-[10px] font-normal normal-case text-text-muted">não é público — apenas administrativo</span></dt>
                        <dd class="mt-0.5 text-text">{{ $perfil->endereco ?: '—' }}</dd>
                    </div>
                </dl>
            </section>

            <section class="rounded-lg bg-white p-6 shadow-card">
                <h3 class="mb-4 font-display font-semibold text-primary">Contato</h3>
                <dl>
                    <dt class="text-xs uppercase tracking-wide text-text-muted">WhatsApp
                        <span class="ml-1 rounded bg-surface px-1.5 py-0.5 text-[10px] font-normal normal-case text-text-muted">{{ $perfil->whatsapp_publico ? 'visível para outros membros' : 'visível só para a casa' }}</span>
                    </dt>
                    <dd class="mt-0.5 text-text">{{ $perfil->whatsapp ?: '—' }}</dd>
                </dl>
            </section>

            <section class="rounded-lg bg-surface p-6 shadow-card ring-1 ring-border">
                <div class="mb-4 flex items-center gap-2">
                    <h3 class="font-display font-semibold text-primary">Minha atuação no CEMA</h3>
                    <span class="rounded-pill bg-border-muted px-2.5 py-0.5 text-[11px] font-medium text-text-secondary">Gerido pela casa</span>
                </div>

                <p class="mb-1 text-xs uppercase tracking-wide text-text-muted">Áreas</p>
                @if ($user->setores->isNotEmpty())
                    <div class="mb-4 flex flex-wrap gap-2">
                        @foreach ($user->setores as $setor)
                            <span class="rounded-pill bg-white px-3 py-1 text-sm text-text ring-1 ring-border">
                                {{ $setor->nome }}@if ($setor->pivot->funcao === 'coordenador') · <span class="font-medium text-primary">Coordenador</span>@endif
                            </span>
                        @endforeach
                    </div>
                @else
                    <p class="mb-4 text-sm text-text-muted">Você ainda não atua em um setor da casa.</p>
                @endif

                @if ($user->cargos->isNotEmpty())
                    <p class="mb-1 text-xs uppercase tracking-wide text-text-muted">Cargos</p>
                    <div class="mb-4 flex flex-wrap gap-2">
                        @foreach ($user->cargos as $cargo)
                            <span class="rounded-pill bg-white px-3 py-1 text-sm text-text ring-1 ring-border">{{ $cargo->nome }}</span>
                        @endforeach
                    </div>
                @endif

                <div class="flex flex-wrap gap-6 text-sm">
                    <div><span class="text-text-muted">Papel:</span> <span class="capitalize text-text">{{ $papel ?? '—' }}</span>@if ($nivel) <span class="text-text-muted">(nível {{ $nivel }})</span>@endif</div>
                    <div><span class="text-text-muted">Sócio:</span> <span class="text-text">{{ $user->socio ? 'Sim' : 'Não' }}</span></div>
                </div>
            </section>
        </div>

        {{-- EDIÇÃO (preenchida no Task 7) --}}
        <div x-show="editando" x-cloak>
            {{-- <livewire:conta.editar-perfil :perfil="$perfil" /> entra aqui no Task 7 --}}
        </div>
    </div>
</x-layout.conta>
