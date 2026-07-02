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
