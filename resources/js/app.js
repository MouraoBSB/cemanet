// Componente Alpine do perfil do palestrante: ordenação client-side da grade.
// (O filtro por tema virou navegação para a archive; aqui fica só a ordenação.)
// Alpine vem do bundle do Livewire; registramos no evento alpine:init.
document.addEventListener('alpine:init', () => {
    window.Alpine.data('palestranteDetalhe', (config) => ({
        sort: 'recent',
        itens: config.itens ?? [],

        get ordemPorId() {
            const arr = [...this.itens];
            if (this.sort === 'recent') {
                arr.sort((a, b) => (b.ts ?? -Infinity) - (a.ts ?? -Infinity));
            } else if (this.sort === 'old') {
                arr.sort((a, b) => (a.ts ?? Infinity) - (b.ts ?? Infinity));
            } else {
                arr.sort((a, b) => a.titulo.localeCompare(b.titulo, 'pt'));
            }
            const mapa = {};
            arr.forEach((i, idx) => {
                mapa[i.id] = idx;
            });

            return mapa;
        },

        ordem(id) {
            return this.ordemPorId[id] ?? 0;
        },
    }));
});

// Componente Alpine do single de mensagem: barra de progresso de leitura, tamanho do texto
// (A−/A+ persistido em localStorage), copiar, compartilhar e toast. Client-side puro, sem
// round-trip Livewire. Respeita prefers-reduced-motion na barra de progresso.
document.addEventListener('alpine:init', () => {
    window.Alpine.data('mensagemLeitura', (config = {}) => ({
        titulo: config.titulo ?? document.title,
        textoCopia: config.textoCopia ?? '',
        url: config.url ?? window.location.href,
        tamanhos: [15.5, 17, 18.5, 20],
        passo: 1,
        progresso: 0,
        reduzido: false,
        rafPendente: false,
        toastMsg: '',
        toastVisivel: false,
        _toast: null,

        init() {
            const salvo = parseInt(localStorage.getItem('cema-msg-passo'), 10);
            if (!Number.isNaN(salvo) && salvo >= 0 && salvo < this.tamanhos.length) {
                this.passo = salvo;
            }
            this.reduzido = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            this.atualizarProgresso();
        },

        get tamanhoAtual() {
            return this.tamanhos[this.passo];
        },

        aumentar() {
            if (this.passo < this.tamanhos.length - 1) {
                this.passo++;
                this.persistir();
            }
        },

        diminuir() {
            if (this.passo > 0) {
                this.passo--;
                this.persistir();
            }
        },

        persistir() {
            try {
                localStorage.setItem('cema-msg-passo', String(this.passo));
            } catch (e) {
                // localStorage indisponível (modo privado): degrada sem persistir.
            }
        },

        atualizarProgresso() {
            if (this.reduzido || this.rafPendente) {
                return;
            }
            this.rafPendente = true;
            requestAnimationFrame(() => {
                const doc = document.documentElement;
                const total = doc.scrollHeight - doc.clientHeight;
                this.progresso = total > 0
                    ? Math.min(100, Math.max(0, (doc.scrollTop / total) * 100))
                    : 0;
                this.rafPendente = false;
            });
        },

        copiar() {
            if (!navigator.clipboard) {
                this.mostrarToast('Não foi possível copiar');

                return;
            }
            navigator.clipboard.writeText(this.textoCopia)
                .then(() => this.mostrarToast('Mensagem copiada'))
                .catch(() => this.mostrarToast('Não foi possível copiar'));
        },

        async compartilhar() {
            if (navigator.share) {
                try {
                    await navigator.share({ title: this.titulo, url: this.url });
                } catch (e) {
                    // Usuário cancelou o compartilhamento nativo.
                }

                return;
            }
            window.open('https://wa.me/?text=' + encodeURIComponent(this.titulo + ' — ' + this.url), '_blank', 'noopener');
        },

        mostrarToast(msg) {
            this.toastMsg = msg;
            this.toastVisivel = true;
            clearTimeout(this._toast);
            this._toast = setTimeout(() => {
                this.toastVisivel = false;
            }, 2200);
        },
    }));
});
