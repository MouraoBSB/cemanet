@props(['title' => null, 'description' => null])

@php
    $tituloPagina = $title ? $title.' — CEMA' : 'CEMA — Centro Espírita Maria Madalena';
    $descricaoPagina = $description ?? 'Centro Espírita Maria Madalena — uma casa de fé, estudo e caridade em Planaltina, DF.';
@endphp
<!DOCTYPE html>
<html lang="pt-BR" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $tituloPagina }}</title>
    <meta name="description" content="{{ $descricaoPagina }}">
    <meta property="og:title" content="{{ $tituloPagina }}">
    <meta property="og:description" content="{{ $descricaoPagina }}">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">
    <link rel="icon" href="{{ asset('images/logos/logo-icone.png') }}" type="image/png">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    {{ $head ?? '' }}
</head>
<body class="min-h-screen flex flex-col bg-white font-sans text-text antialiased">
    <a href="#conteudo" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-[100] focus:rounded-md focus:bg-primary focus:px-4 focus:py-2 focus:text-white">Pular para o conteúdo</a>
    <x-layout.header />

    <main id="conteudo" class="flex-1">
        {{ $slot }}
    </main>

    <x-layout.footer />

    @livewireScripts
</body>
</html>
