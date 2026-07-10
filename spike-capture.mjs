// Capturador do spike (DESCARTÁVEL — não commitar): screenshots + console de cada página.
// Uso: node spike-capture.mjs <rotulo> [/rota/extra ...]
import { chromium } from 'playwright-core';
import { mkdirSync } from 'node:fs';

const rotulo = process.argv[2] ?? 'run';
const extras = process.argv.slice(3);
const SAIDA = `spike-shots/${rotulo}`;
mkdirSync(SAIDA, { recursive: true });

const BASE = 'http://localhost:8000';
const paginas = [
    ['eventos', '/eventos'],
    ['calendario', '/calendario'],
    ['minha-conta', '/minha-conta'],
    ...extras.map((p) => [p.replace(/[^\w]+/g, '-').replace(/^-|-$/g, ''), p]),
];

const browser = await chromium.launch({ channel: 'chrome', headless: true });
const ctx = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
const page = await ctx.newPage();

let logs = [];
page.on('console', (m) => {
    if (m.type() === 'error' || m.type() === 'warning') logs.push({ tipo: m.type(), texto: m.text().slice(0, 300) });
});
page.on('pageerror', (e) => logs.push({ tipo: 'pageerror', texto: String(e).slice(0, 300) }));

// Login (usuário de spike)
await page.goto(`${BASE}/entrar`, { waitUntil: 'load' });
await page.fill('input[name="email"]', 'spike@cema.test');
await page.fill('input[name="password"]', 'spike-cema-2026');
await page.click('button[type="submit"]');
await page.waitForTimeout(2500);
const logado = !page.url().includes('/entrar');

const resultado = { rotulo, logado, paginas: [] };
for (const [nome, url] of paginas) {
    logs = [];
    let status = null;
    try {
        const resp = await page.goto(`${BASE}${url}`, { waitUntil: 'load', timeout: 30000 });
        status = resp?.status() ?? null;
        await page.waitForTimeout(1500); // Alpine/Livewire assentarem
        await page.screenshot({ path: `${SAIDA}/${nome}.png`, fullPage: true });
    } catch (e) {
        logs.push({ tipo: 'navegacao', texto: String(e).slice(0, 300) });
    }
    resultado.paginas.push({ pagina: nome, url, status, console: logs });
}

await browser.close();
console.log(JSON.stringify(resultado, null, 2));
