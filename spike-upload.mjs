// SPIKE (descartável): prova o round-trip real do upload no browser (FilePond -> Livewire).
import { chromium } from 'playwright-core';

const BASE = 'http://localhost:8000';
const browser = await chromium.launch({ channel: 'chrome', headless: true });
const ctx = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
const page = await ctx.newPage();

const uploads = [];
page.on('response', (r) => {
    const u = r.url();
    if (u.includes('upload') || u.includes('livewire')) uploads.push({ url: u.replace(BASE, ''), status: r.status(), metodo: r.request().method() });
});
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

// Imagem REAL do repositório (evita falso-positivo de "source image could not be decoded"
// causado por um PNG sintético minúsculo).
const inputArquivo = page.locator('input[type="file"]').first();
await inputArquivo.setInputFiles('public/images/logos/logo-icone.png');
await page.waitForTimeout(6000); // upload temporário + preview do FilePond

const estado = await page.evaluate(() => ({
    itensFilePond: document.querySelectorAll('.filepond--item').length,
    temPreview: document.querySelectorAll('.filepond--image-preview, .filepond--file-info').length,
    nomeVisivel: [...document.querySelectorAll('.filepond--file-info-main')].map((e) => e.textContent.trim()),
}));

await page.screenshot({ path: 'spike-shots/upload-browser.png', fullPage: false });
const uploadsLivewire = uploads.filter((u) => u.url.includes('upload'));
console.log(JSON.stringify({ estado, uploadsLivewire, erros }, null, 2));
await browser.close();
