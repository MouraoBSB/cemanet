<x-layout.app :title="$evento->titulo" :description="\Illuminate\Support\Str::limit(strip_tags((string) $evento->resumo), 155)">
    <div class="mx-auto max-w-[1240px] px-4 py-10"><h1>{{ $evento->titulo }}</h1></div>
</x-layout.app>
