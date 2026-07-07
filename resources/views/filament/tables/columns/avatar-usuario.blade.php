{{-- Thiago Mourão — https://github.com/MouraoBSB — 2026-07-07 --}}
@php($url = $getRecord()->perfil?->foto_thumb_url)
@if ($url)
    <img src="{{ $url }}" alt="" class="size-8 rounded-full object-cover">
@else
    <span class="flex size-8 items-center justify-center rounded-full text-xs font-semibold text-white" style="background-color:#4E4483">{{ $getRecord()->iniciais }}</span>
@endif
