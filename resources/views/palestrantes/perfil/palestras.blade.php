{{-- Cabeçalho + barra de filtro/ordenação + grade (reusa <x-palestra.card>) + empty state.
     Filtro/ordenação client-side via o escopo Alpine `palestranteDetalhe` (definido na casca). --}}
<div class="mt-8">
    <div class="mb-[18px] flex flex-wrap items-baseline justify-between gap-3">
        <div class="flex items-center gap-2.5">
            <span class="h-[3px] w-[22px] rounded-sm bg-gold" aria-hidden="true"></span>
            <h2 class="font-display text-[21px] font-semibold text-primary">Palestras de {{ $palestrante->nome }}</h2>
        </div>
        <p class="text-[13px] text-text-muted" x-text="rotulo">{{ $palestras->count() }} {{ \Illuminate\Support\Str::plural('palestra', $palestras->count()) }}</p>
    </div>

    @if ($palestras->isNotEmpty())
        <div class="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-[14px] border border-border-muted bg-white px-4 py-3.5 shadow-card">
            <div class="flex flex-wrap gap-2">
                <button type="button" @click="area = 'todos'" :aria-pressed="area === 'todos'"
                        :class="area === 'todos' ? 'bg-primary text-white' : 'bg-surface text-text-secondary'"
                        class="rounded-pill px-3.5 py-1.5 text-[12.5px] font-medium transition">Todas</button>
                @foreach ($areas as $areaItem)
                    <button type="button"
                            @click="selecionar('{{ $areaItem['slug'] }}')"
                            :aria-pressed="area === '{{ $areaItem['slug'] }}'"
                            :class="area === '{{ $areaItem['slug'] }}' ? 'bg-primary text-white' : 'bg-surface text-text-secondary'"
                            class="inline-flex items-center gap-2 rounded-pill px-3.5 py-1.5 text-[12.5px] font-medium transition">
                        <span class="cema-dot-{{ $areaItem['cor'] }} inline-block size-[7px] rounded-full"></span>{{ $areaItem['nome'] }}
                    </button>
                @endforeach
            </div>
            <div class="flex items-center gap-2">
                <label for="ordenar-palestras" class="whitespace-nowrap text-[13px] text-text-muted">Ordenar:</label>
                <select id="ordenar-palestras" x-model="sort"
                        class="cursor-pointer rounded-[10px] border border-border bg-white px-3 py-2 text-[13.5px] text-text-secondary outline-none">
                    <option value="recent">Mais recentes</option>
                    <option value="old">Mais antigas</option>
                    <option value="az">Título (A–Z)</option>
                </select>
            </div>
        </div>

        <div class="grid gap-5 [grid-template-columns:repeat(auto-fill,minmax(258px,1fr))]" x-show="!vazio">
            @foreach ($palestras as $palestra)
                <x-palestra.card
                    :palestra="$palestra"
                    x-show="visivel({{ $palestra->id }})"
                    x-bind:style="{ order: ordem({{ $palestra->id }}) }" />
            @endforeach
        </div>

        <div x-show="vazio" x-cloak class="rounded-2xl border border-dashed border-[#DAD5E6] bg-white px-6 py-14 text-center">
            <p class="mb-1.5 font-display text-[17px] font-semibold text-[#3a3266]">Nenhuma palestra neste tema</p>
            <p class="mb-4 text-sm text-text-muted">Remova o filtro para ver todas as palestras.</p>
            <button type="button" @click="area = 'todos'" class="rounded-pill bg-primary px-5 py-2.5 text-sm font-medium text-white transition hover:brightness-110">Ver todas</button>
        </div>
    @else
        <p class="rounded-lg border border-border-muted bg-white px-6 py-8 text-text-secondary">Nenhuma palestra publicada por ora.</p>
    @endif
</div>
