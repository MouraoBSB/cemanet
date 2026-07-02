<?php echo '<?xml version="1.0" encoding="UTF-8"?>'; ?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    {{-- Listagem principal do blog --}}
    <url>
        <loc>{{ route('blog.index') }}</loc>
        <changefreq>daily</changefreq>
        <priority>0.8</priority>
    </url>

    {{-- Páginas de categoria --}}
    @foreach ($categorias as $cat)
    <url>
        <loc>{{ route('blog.index', ['categoria' => $cat->slug]) }}</loc>
        <changefreq>weekly</changefreq>
        <priority>0.6</priority>
    </url>
    @endforeach

    {{-- Posts publicados --}}
    @foreach ($posts as $post)
    <url>
        <loc>{{ route('blog.show', $post->slug) }}</loc>
        <lastmod>{{ $post->updated_at->toAtomString() }}</lastmod>
        <changefreq>monthly</changefreq>
        <priority>0.7</priority>
    </url>
    @endforeach

    {{-- Agenda Reforma Íntima — URL nua ("hoje" evergreen) --}}
    <url>
        <loc>{{ route('agenda.index') }}</loc>
        <changefreq>daily</changefreq>
        <priority>0.9</priority>
    </url>

    {{-- Agenda Reforma Íntima — dias publicados --}}
    @foreach ($agendaDias as $agendaDia)
    <url>
        <loc>{{ route('agenda.show', $agendaDia->data->format('Y-m-d')) }}</loc>
        <lastmod>{{ $agendaDia->updated_at->toAtomString() }}</lastmod>
        <changefreq>monthly</changefreq>
        <priority>0.7</priority>
    </url>
    @endforeach
</urlset>
