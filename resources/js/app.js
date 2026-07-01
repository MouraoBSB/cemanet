// Componente Alpine do perfil do palestrante: filtro por tema + ordenação (client-side).
// Alpine vem do bundle do Livewire; registramos no evento alpine:init.
document.addEventListener('alpine:init', () => {
    window.Alpine.data('palestranteDetalhe', (config) => ({
        area: 'todos',
        sort: 'recent',
        itens: config.itens ?? [],
        areas: config.areas ?? [],

        visivel(id) {
            if (this.area === 'todos') {
                return true;
            }
            const item = this.itens.find((i) => i.id === id);

            return !!item && item.assuntos.includes(this.area);
        },

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

        selecionar(slug) {
            this.area = this.area === slug ? 'todos' : slug;
        },

        get filtradas() {
            return this.itens.filter((i) => this.visivel(i.id));
        },

        get vazio() {
            return this.filtradas.length === 0;
        },

        get rotulo() {
            const n = this.filtradas.length;
            const base = n === 1 ? '1 palestra' : `${n} palestras`;
            if (this.area === 'todos') {
                return base;
            }
            const a = this.areas.find((x) => x.slug === this.area);

            return a ? `${base} em ${a.nome}` : base;
        },
    }));
});
