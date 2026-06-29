{{-- Thiago Mourão — https://github.com/MouraoBSB — 2026-06-28 --}}
{{--
    Grade visual de seleção de mídia da biblioteca.
    Estilos INLINE de propósito: o painel admin não tem tema Tailwind custom que varra
    esta view, então classes utilitárias (grid/aspect/ring) não seriam compiladas. Inline
    garante o layout (quadradinhos) e o feedback de seleção independentemente da CSS.
--}}
@php
    $medias = \Spatie\MediaLibrary\MediaCollections\Models\Media::query()
        ->where('collection_name', \App\Models\Biblioteca::COLECAO)
        ->latest('id')
        ->get();

    $metas = $medias->mapWithKeys(fn ($m) => [$m->id => [
        'nome' => $m->name,
        'alt'  => $m->getCustomProperty('alt'),
    ]]);
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        x-data="{
            sel: $wire.$entangle('{{ $getStatePath() }}'),
            q: '',
            metas: {{ \Illuminate\Support\Js::from($metas) }},
        }"
    >
        @if ($medias->isEmpty())
            <p style="font-size:0.875rem; color:#6b7280;">
                A biblioteca ainda não tem imagens. Use a aba "Subir nova" ou o menu "Biblioteca".
            </p>
        @else
            <input
                type="text"
                x-model="q"
                placeholder="Buscar por nome…"
                style="width:100%; padding:6px 10px; border:1px solid #d1d5db; border-radius:8px; font-size:0.875rem; margin-bottom:8px; box-sizing:border-box;"
            >

            <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(92px, 1fr)); gap:8px; max-height:320px; overflow-y:auto; padding:2px;">
                @foreach ($medias as $m)
                    <button
                        type="button"
                        x-show="q === '' || @js(\Illuminate\Support\Str::lower($m->name)).includes(q.toLowerCase())"
                        x-on:click="sel = {{ $m->id }}"
                        :style="(sel === {{ $m->id }})
                            ? 'outline:3px solid #6d28d9; outline-offset:1px;'
                            : 'outline:1px solid #e5e7eb;'"
                        style="position:relative; aspect-ratio:1 / 1; overflow:hidden; border-radius:8px; padding:0; margin:0; cursor:pointer; background:#f3f4f6; border:none;"
                        title="{{ $m->name }}"
                    >
                        <img
                            src="{{ route('midia.serve', [$m->id, 'thumb']) }}"
                            alt="{{ $m->getCustomProperty('alt') ?? $m->name }}"
                            loading="lazy"
                            style="width:100%; height:100%; object-fit:cover; display:block;"
                        >
                    </button>
                @endforeach
            </div>

            {{-- SEO/metadados da imagem selecionada --}}
            <div
                x-show="sel"
                style="margin-top:10px; font-size:0.8125rem; color:#374151; background:#f9fafb; border:1px solid #e5e7eb; border-radius:8px; padding:8px 10px; line-height:1.4;"
            >
                <div><strong>Selecionada:</strong> <span x-text="metas[sel]?.nome ?? ''"></span></div>
                <div>
                    <strong>Alt (SEO):</strong>
                    <span x-text="metas[sel]?.alt || '— sem alt definido; preencha abaixo ou edite no menu Biblioteca'"></span>
                </div>
            </div>
        @endif
    </div>
</x-dynamic-component>
