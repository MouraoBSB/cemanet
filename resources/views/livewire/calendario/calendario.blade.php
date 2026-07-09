<div>
    @foreach ($ocorrenciasDoMes as $oc)
        <a href="{{ $oc->url }}" wire:key="{{ $oc->chave }}">{{ $oc->titulo }}</a>
    @endforeach
</div>
