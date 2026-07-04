// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-04
import Cropper from 'cropperjs';
import 'cropperjs/dist/cropper.css';

// Alpine data: abre o cropper ao escolher um arquivo, envia o recorte quadrado ao Livewire.
document.addEventListener('alpine:init', () => {
    window.Alpine.data('cropperPerfil', () => ({
        cropper: null,
        aberto: false,
        srcPreview: null,

        aoEscolher(evento) {
            const arquivo = evento.target.files?.[0];
            if (!arquivo) return;

            const leitor = new FileReader();
            leitor.onload = (e) => {
                this.srcPreview = e.target.result;
                this.aberto = true;
                this.$nextTick(() => {
                    const img = this.$refs.imagem;
                    img.src = this.srcPreview;
                    this.cropper?.destroy();
                    this.cropper = new Cropper(img, { aspectRatio: 1, viewMode: 1, autoCropArea: 1 });
                });
            };
            leitor.readAsDataURL(arquivo);
            evento.target.value = ''; // permite reescolher o mesmo arquivo
        },

        confirmar() {
            this.cropper.getCroppedCanvas({ width: 800, height: 800 }).toBlob((blob) => {
                const arquivo = new File([blob], 'foto.webp', { type: 'image/webp' });
                this.$wire.upload('foto', arquivo);
                this.fechar();
            }, 'image/webp', 0.85);
        },

        fechar() {
            this.cropper?.destroy();
            this.cropper = null;
            this.aberto = false;
        },
    }));
});
