// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-27
// Config do Vitest para testar as extensões TipTap do editor (jsdom = DOM no Node).
import { defineConfig } from 'vitest/config'

export default defineConfig({
    test: {
        environment: 'jsdom',
        include: ['tests/js/**/*.test.js'],
    },
})
