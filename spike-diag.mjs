// Diagnóstico do spike (DESCARTÁVEL): quais assets carregaram, Alpine, hidratação, CSS.
import { chromium } from 'playwright-core';

const BASE = 'http://localhost:8000';
const browser = await chromium.launch({ channel: 'chrome', headless: true });
const ctx = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
const page = await ctx.newPage();

const falhas = [];
page.on('response', (r) => { if (r.status() >= 400) falhas.push({ url: r.url(), status: r.status() }); });
const erros = [];
page.on('console', (m) => { if (m.type() === 'error' || m.type() === 'warning') erros.push(`${m.type()}: ${m.text().slice(0, 200)}`); });
page.on('pageerror', (e) => erros.push(`pageerror: ${String(e).slice(0, 200)}`));

await page.goto(`${BASE}/entrar`, { waitUntil: 'load' });
await page.fill('input[name="email"]', 'spike@cema.test');
await page.fill('input[name="password"]', 'spike-cema-2026');
await page.click('button[type="submit"]');
await page.waitForTimeout(2000);

await page.goto(`${BASE}/minha-conta/spike-evento`, { waitUntil: 'load' });
await page.waitForTimeout(2500);

const diag = await page.evaluate(() => {
    const scripts = [...document.querySelectorAll('script[src]')].map((s) => s.getAttribute('src'));
    const estilos = [...document.querySelectorAll('link[rel="stylesheet"]')].map((l) => l.getAttribute('href'));

    const input = document.querySelector('.fi-input') || document.querySelector('input[type="text"]');
    const cs = input ? getComputedStyle(input) : null;

    // Abas: se Alpine hidratou, só 1 painel visível.
    const paineis = [...document.querySelectorAll('.fi-tabs-panel, [role="tabpanel"]')];
    const paineisVisiveis = paineis.filter((p) => p.offsetParent !== null).length;

    return {
        scripts,
        estilos,
        alpine: typeof window.Alpine,
        alpineVersao: window.Alpine?.version ?? null,
        livewire: typeof window.Livewire,
        filePond: typeof window.FilePond,
        temElementosFi: document.querySelectorAll('[class*="fi-"]').length,
        inputBorda: cs ? `${cs.borderWidth} ${cs.borderStyle}` : null,
        inputFundo: cs ? cs.backgroundColor : null,
        paineisTotal: paineis.length,
        paineisVisiveis,
        fileInputsNativos: document.querySelectorAll('input[type="file"]:not([style*="display: none"])').length,
        temFilePondRoot: document.querySelectorAll('.filepond--root').length,
        xLoadPendentes: document.querySelectorAll('[x-load]').length,
    };
});

console.log(JSON.stringify({ diag, falhasHttp: falhas, console: erros }, null, 2));
await browser.close();
