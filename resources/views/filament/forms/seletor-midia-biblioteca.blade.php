@php
    $medias = \Spatie\MediaLibrary\MediaCollections\Models\Media::query()
        ->where('collection_name', \App\Models\Biblioteca::COLECAO)
        ->latest('id')
        ->get();
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        x-data="{ sel: $wire.$entangle('{{ $getStatePath() }}'), q: '' }"
        class="fi-fo-field-wrp"
    >
        <input
            type="text"
            x-model="q"
            placeholder="Buscar por nome…"
            class="mb-3 block w-full rounded-lg border-gray-300 text-sm shadow-sm dark:bg-gray-800 dark:border-gray-600"
        >

        @if ($medias->isEmpty())
            <p class="text-sm text-gray-500">A biblioteca ainda não tem imagens. Suba uma no menu "Biblioteca".</p>
        @else
            <div class="grid grid-cols-3 gap-2 sm:grid-cols-4 md:grid-cols-5 max-h-80 overflow-y-auto p-1">
                @foreach ($medias as $m)
                    <button
                        type="button"
                        x-show="q === '' || @js(\Illuminate\Support\Str::lower($m->name)).includes(q.toLowerCase())"
                        x-on:click="sel = {{ $m->id }}"
                        :class="sel === {{ $m->id }} ? 'ring-2 ring-primary-500' : 'ring-1 ring-gray-200 dark:ring-gray-700'"
                        class="relative aspect-square overflow-hidden rounded-lg transition focus:outline-none"
                        title="{{ $m->name }}"
                    >
                        <img
                            src="{{ route('midia.serve', [$m->id, 'thumb']) }}"
                            alt="{{ $m->getCustomProperty('alt') ?? $m->name }}"
                            loading="lazy"
                            class="h-full w-full object-cover"
                        >
                    </button>
                @endforeach
            </div>
        @endif
    </div>
</x-dynamic-component>
