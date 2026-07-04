{{-- Thiago Mourão — https://github.com/MouraoBSB — 2026-07-04 --}}
@assets
    @vite('resources/js/cropper-perfil.js')
@endassets
<form wire:submit="salvar" class="space-y-6">
    <section class="rounded-lg bg-white p-6 shadow-card" x-data="cropperPerfil">
        <h3 class="mb-4 font-display font-semibold text-primary">Foto de perfil</h3>
        <div class="flex items-center gap-4">
            <div class="size-20 overflow-hidden rounded-full bg-primary/10">
                @if ($foto && $foto->isPreviewable())
                    <img src="{{ $foto->temporaryUrl() }}" alt="Prévia" class="size-full object-cover">
                @elseif (auth()->user()->perfil?->foto_thumb_url)
                    <img src="{{ auth()->user()->perfil->foto_thumb_url }}" alt="" class="size-full object-cover">
                @else
                    <span class="flex size-full items-center justify-center text-lg font-semibold text-primary">{{ auth()->user()->iniciais }}</span>
                @endif
            </div>
            <div>
                {{-- Sem wire:model (desacoplado): o x-on:change abre o cropper e o recorte quadrado é a ÚNICA via de upload ($wire.upload no confirmar()) — determinístico, sem upload duplo nem original ao cancelar. --}}
                <input type="file" x-on:change="aoEscolher" accept="image/jpeg,image/png,image/webp"
                       class="block text-sm text-text file:mr-3 file:rounded-pill file:border-0 file:bg-surface file:px-4 file:py-2 file:text-sm file:text-primary">
                <p class="mt-1 text-xs text-text-muted">Tamanho máximo: 1 MB. A capa é gerada automaticamente.</p>
                @error('foto') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
            </div>
        </div>

        {{-- Modal do cropper --}}
        <div x-show="aberto" x-cloak class="fixed inset-0 z-[80] flex items-center justify-center bg-black/60 p-4">
            <div class="w-full max-w-md rounded-lg bg-white p-4">
                <div class="max-h-[60vh] overflow-hidden"><img x-ref="imagem" alt="Recorte da foto" class="block max-w-full"></div>
                <div class="mt-3 flex justify-end gap-2">
                    <button type="button" @click="fechar" class="rounded-pill px-4 py-2 text-sm text-text-muted hover:text-primary">Cancelar</button>
                    <button type="button" @click="confirmar" class="rounded-pill bg-primary px-4 py-2 text-sm font-medium text-white">Usar recorte</button>
                </div>
            </div>
        </div>
    </section>

    <section class="rounded-lg bg-white p-6 shadow-card">
        <h3 class="mb-4 font-display font-semibold text-primary">Dados pessoais</h3>
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label for="ep-name" class="block text-sm font-medium">Nome público</label>
                <input id="ep-name" type="text" wire:model="name" class="mt-1 w-full rounded-md border border-border px-3 py-2 focus:border-primary focus:ring-primary">
                @error('name') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="ep-nasc" class="block text-sm font-medium">Data de nascimento</label>
                <input id="ep-nasc" type="date" wire:model="data_nascimento" class="mt-1 w-full rounded-md border border-border px-3 py-2 focus:border-primary focus:ring-primary">
                @error('data_nascimento') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
            </div>
            <div class="sm:col-span-2">
                <label for="ep-end" class="block text-sm font-medium">Endereço <span class="text-xs font-normal text-text-muted">(não é público — apenas administrativo)</span></label>
                <input id="ep-end" type="text" wire:model="endereco" class="mt-1 w-full rounded-md border border-border px-3 py-2 focus:border-primary focus:ring-primary">
                @error('endereco') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
            </div>
        </div>
    </section>

    <section class="rounded-lg bg-white p-6 shadow-card">
        <h3 class="mb-4 font-display font-semibold text-primary">Contato</h3>
        <div>
            <label for="ep-wa" class="block text-sm font-medium">WhatsApp</label>
            <input id="ep-wa" type="text" wire:model="whatsapp" class="mt-1 w-full max-w-xs rounded-md border border-border px-3 py-2 focus:border-primary focus:ring-primary">
            @error('whatsapp') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
        </div>
        <label class="mt-3 flex items-center gap-2 text-sm">
            <input type="checkbox" wire:model="whatsapp_publico" role="switch" class="rounded border-border text-primary focus:ring-primary">
            Visível para outros membros
        </label>
    </section>

    <section class="rounded-lg bg-surface p-6 ring-1 ring-dashed ring-border">
        <div class="flex items-center gap-2 text-sm text-text-muted">
            <span class="rounded-pill bg-border-muted px-2.5 py-0.5 text-[11px] font-medium text-text-secondary">Somente leitura</span>
            Sua atuação, papel e situação de sócio são geridos pela casa.
        </div>
    </section>

    <div class="sticky bottom-0 -mx-1 flex justify-end gap-3 border-t border-border bg-cream/95 px-1 py-3 backdrop-blur">
        <a href="{{ route('conta.perfil') }}" class="rounded-pill px-4 py-2 text-sm font-medium text-text-muted hover:text-primary">Cancelar</a>
        <button type="submit" class="rounded-pill bg-primary px-5 py-2 text-sm font-medium text-white hover:bg-primary/90"
                wire:loading.attr="disabled">Salvar alterações</button>
    </div>
</form>
