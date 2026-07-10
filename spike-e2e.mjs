// E2E do spike (DESCARTÁVEL): sonda os controles e exercita salvar + upload + validação.
import { chromium } from 'playwright-core';

const BASE = 'http://localhost:8000';
const modo = process.argv[2] ?? 'sonda'; // sonda | salvar | validacao

const browser = await chromium.launch({ channel: 'chrome', headless: true });
const ctx = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
const page = await ctx.newPage();

const erros = [];
page.on('console', (m) => { if (m.type() === 'error') erros.push(m.text().slice(0, 200)); });
page.on('pageerror', (e) => erros.push(String(e).slice(0, 200)));

await page.goto(`${BASE}/entrar`, { waitUntil: 'load' });
await page.fill('input[name="email"]', 'spike@cema.test');
await page.fill('input[name="password"]', 'spike-cema-2026');
await page.click('button[type="submit"]');
await page.waitForTimeout(2000);
await page.goto(`${BASE}/minha-conta/spike-evento`, { waitUntil: 'load' });
await page.waitForTimeout(2500);

if (modo === 'sonda') {
    const controles = await page.evaluate(() =>
        [...document.querySelectorAll('input, textarea, select')].map((e) => ({
            tag: e.tagName.toLowerCase(),
            type: e.getAttribute('type'),
            id: e.getAttribute('id'),
            wire: e.getAttribute('wire:model') ?? e.getAttribute('wire:model.blur') ?? e.getAttribute('wire:model.live'),
            visivel: e.offsetParent !== null,
        }))
    );
    const abas = await page.evaluate(() => [...document.querySelectorAll('button[role="tab"], .fi-tabs-item')].map((b) => b.textContent.trim()));
    console.log(JSON.stringify({ controles, abas, erros }, null, 2));
    await browser.close();
    process.exit(0);
}

const marca = Date.now();
const titulo = `Spike Evento ${marca}`;

// Aba Conteúdo: título (slug preenche sozinho pelo afterStateUpdated)
await page.fill('#form\\.titulo', titulo);
await page.locator('#form\\.titulo').blur();
await page.waitForTimeout(1200);

// Upload do flyer (Spatie/FilePond) — input nativo por trás do dropzone
const inputs = await page.locator('input[type="file"]').all();
if (inputs.length > 0) {
    await inputs[0].setInputFiles({ name: 'flyer.png', mimeType: 'image/png', buffer: Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', 'base64') });
    await page.waitForTimeout(3000); // upload temporário do Livewire
}

// Aba Data & Local
await page.getByRole('tab', { name: 'Data & Local' }).click().catch(async () => {
    await page.locator('.fi-tabs-item', { hasText: 'Data & Local' }).click();
});
await page.waitForTimeout(600);

// DatePicker native(false): input é readonly -> abre o painel e clica um dia.
await page.click('#form\\.data_inicio');
await page.waitForTimeout(800);
// Só o painel VISÍVEL (o do data_fim também existe no DOM, fechado, e interceptaria).
const painel = page.locator('.fi-fo-date-time-picker-panel:visible').first();
await painel.waitFor({ state: 'visible', timeout: 5000 });
const dia = painel.getByRole('button', { name: '15', exact: true }).first();
if (await dia.count()) {
    await dia.click();
} else {
    const amostra = await painel.locator('button').allTextContents();
    console.error('dia não encontrado; botões do painel:', JSON.stringify(amostra.slice(0, 15)));
}
await page.waitForTimeout(800);

if (modo === 'validacao') {
    // hora_fim ANTES da hora_inicio no MESMO dia -> deve falhar (PeriodoEvento)
    await page.fill('#form\\.hora_inicio', '10:00');
    await page.fill('#form\\.data_fim', '01/08/2026');
    await page.fill('#form\\.hora_fim', '09:00');
    await page.waitForTimeout(500);
}

await page.screenshot({ path: `spike-shots/e2e-${modo}-antes-submit.png`, fullPage: true });

await page.getByRole('button', { name: 'Salvar evento' }).click();
await page.waitForTimeout(4000);

const ok = await page.locator('#spike-ok').count();
const mensagensErro = await page.evaluate(() =>
    [...document.querySelectorAll('.fi-fo-field-wrp-error-message, [data-validation-error], .fi-color-danger')]
        .map((e) => e.textContent.trim()).filter(Boolean).slice(0, 6)
);

await page.screenshot({ path: `spike-shots/e2e-${modo}-depois-submit.png`, fullPage: true });
console.log(JSON.stringify({ modo, titulo, sucesso: ok > 0, mensagensErro, erros }, null, 2));
await browser.close();
