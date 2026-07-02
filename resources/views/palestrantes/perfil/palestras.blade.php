{{-- Cabeçalho + temas abordados (links p/ a archive filtrada, em sanfona) + ordenação + grade.
     A ordenação é client-side via o escopo Alpine `palestranteDetalhe` (definido na casca);
     os temas NÃO filtram esta página — cada um navega para /palestra_publica?assunto={slug}. --}}
<div class="mt-8">
    <div class="mb-[18px] flex flex-wrap items-baseline justify-between gap-3">
        <div class="flex items-center gap-2.5">
            <span class="h-[3px] w-[22px] rounded-sm bg-gold" aria-hidden="true"></span>
            <h2 class="font-display text-[21px] font-semibold text-primary">Palestras de {{ $palestrante->nome }}</h2>
        </div>
        <p class="text-[13px] text-text-muted">{{ $palestras->count() }} {{ \Illuminate\Support\Str::plural('palestra', $palestras->count()) }}</p>
    </div>

    {{-- Temas abordados: navegam para a archive filtrada. Sanfona: mostra os 12 primeiros, expande o resto. --}}
    @if ($areas->isNotEmpty())
        <div class="mb-5 rounded-[14px] border border-border-muted bg-white p-4 shadow-card" x-data="{ abertos: false }">
            <p class="mb-3 text-[13px] font-medium text-text-secondary">Temas abordados</p>
            <div class="flex flex-wrap gap-2">
                @foreach ($areas as $areaItem)
                    <a href="{{ route('palestras.index', ['assunto' => $areaItem['slug']]) }}"
                       @if ($loop->index >= 12) x-show="abertos" x-cloak @endif
                       class="inline-flex items-center gap-2 rounded-pill bg-surface px-3.5 py-1.5 text-[12.5px] font-medium text-text-secondary transition hover:bg-primary hover:text-white">
                        <span class="cema-dot-{{ $areaItem['cor'] }} inline-block size-[7px] rounded-full"></span>{{ $areaItem['nome'] }}
                    </a>
                @endforeach
            </div>
            @if ($areas->count() > 12)
                <button type="button" @click="abertos = !abertos"
                        class="mt-3 inline-flex items-center gap-1 text-[13px] font-medium text-primary hover:underline">
                    <span x-show="!abertos">Ver todos os {{ $areas->count() }} temas ↓</span>
                    <span x-show="abertos" x-cloak>Ver menos ↑</span>
                </button>
            @endif
        </div>
    @endif

    @if ($palestras->isNotEmpty())
        <div class="mb-5 flex items-center justify-end gap-2">
            <label for="ordenar-palestras" class="whitespace-nowrap text-[13px] text-text-muted">Ordenar:</label>
            <select id="ordenar-palestras" x-model="sort"
                    class="cursor-pointer rounded-[10px] border border-border bg-white px-3 py-2 text-[13.5px] text-text-secondary outline-none">
                <option value="recent">Mais recentes</option>
                <option value="old">Mais antigas</option>
                <option value="az">Título (A–Z)</option>
            </select>
        </div>

        <div class="grid gap-5 [grid-template-columns:repeat(auto-fill,minmax(258px,1fr))]">
            @foreach ($palestras as $palestra)
                <x-palestra.card
                    :palestra="$palestra"
                    x-bind:style="{ order: ordem({{ $palestra->id }}) }" />
            @endforeach
        </div>
    @else
        <p class="rounded-lg border border-border-muted bg-white px-6 py-8 text-text-secondary">Nenhuma palestra publicada por ora.</p>
    @endif
</div>
