import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import fs from 'node:fs';
const secrets = fs.readFileSync(process.env.HUB_TEST_CREDENTIALS || 'storage/app/demo-credentials.txt','utf8');
const pass = email => secrets.split('\n').find(line => line.startsWith(email+': ')).slice(email.length+2);
// Optional only for a local test server that trusts its loopback proxy.
// Distinct client IPs isolate tests while keeping production rate limits enabled.
test.beforeEach(async ({ page }, testInfo) => {
    if (process.env.HUB_TEST_PROXY_CLIENTS === 'true') {
        const index = testInfo.title.split('').reduce((sum, char) => sum + char.charCodeAt(0), 0);
        await page.setExtraHTTPHeaders({ 'X-Forwarded-For': `198.18.${Math.floor(index / 250) % 250}.${index % 250 + 1}` });
    }
});
async function login(page,email='cliente@vp-hub.test') {
    await page.goto('/login');await page.getByLabel('Email o username',{exact:true}).fill(email);await page.getByLabel('Password',{exact:true}).fill(pass(email));await page.getByRole('button',{name:'Accedi al workspace'}).click();
    await expect(page).toHaveURL(/dashboard/);
}
test('client desktop: dashboard, privacy, exports and documents',async({page})=>{
    const errors=[];page.on('pageerror',e=>errors.push(e.message));await login(page);
    await expect(page.getByRole('heading',{name:'GED · Demo',exact:true})).toBeVisible();await expect(page.locator('body')).not.toContainText('Studio Orizzonte');await page.screenshot({path:'storage/app/browser-results/client-desktop.png',fullPage:true});
    await page.goto('/billing');await expect(page.getByRole('heading',{name:'Numeri trasparenti.'})).toBeVisible();
    const downloadPromise=page.waitForEvent('download');await page.getByRole('link',{name:'Report PDF',exact:true}).click();const download=await downloadPromise;expect(download.suggestedFilename()).toMatch(/\.pdf$/);
    await page.goto('/documents');await expect(page.getByRole('heading',{name:'Tutto al suo posto.'})).toBeVisible();expect(errors).toEqual([]);
});
test('client mobile: navigation, no horizontal overflow and dark mode',async({page})=>{
    await page.setViewportSize({width:390,height:844});await login(page);
    expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth)).toBeTruthy();await page.screenshot({path:'storage/app/browser-results/client-mobile.png',fullPage:true});
    await page.getByRole('button',{name:'Apri o chiudi navigazione'}).click();await page.locator('#sidebar').getByRole('link',{name:'Richieste assistenza'}).click();await expect(page).toHaveURL(/manage\/tickets/);
    await page.getByRole('button',{name:'Cambia tema chiaro o scuro'}).click();await expect(page.locator('html')).toHaveAttribute('data-theme','dark');await page.screenshot({path:'storage/app/browser-results/client-mobile-dark.png',fullPage:true});
});
test('client creates request, comments and uploads private attachment',async({page})=>{
    await login(page);await page.goto('/manage/tickets/create');const title='Verifica browser '+Date.now();
    await page.getByLabel('Oggetto').fill(title);await page.getByLabel('Descrivi la richiesta').fill('Richiesta fittizia creata dal collaudo browser.');await page.getByRole('button',{name:'Invia richiesta',exact:true}).click();await expect(page.getByRole('heading',{name:title,exact:true})).toBeVisible();
    await page.getByLabel('Aggiungi un commento').fill('Un aggiornamento dal browser.');await page.getByRole('button',{name:'Invia commento'}).click();await expect(page.getByText('Un aggiornamento dal browser.',{exact:true})).toBeVisible();
    await page.getByLabel('Titolo',{exact:true}).fill('Allegato di prova');await page.getByLabel('File',{exact:true}).setInputFiles({name:'prova.txt',mimeType:'text/plain',buffer:Buffer.from('Documento fittizio di collaudo')});await page.getByRole('button',{name:'Carica documento'}).click();await expect(page.getByText('Documento caricato in archivio privato.')).toBeVisible();
});
test('admin login, dashboard and entity CRUD in real browser',async({page})=>{
    await login(page,'admin@vp-hub.test');await expect(page.getByRole('heading',{name:'Tutto sotto controllo.'})).toBeVisible();await page.screenshot({path:'storage/app/browser-results/admin-desktop.png',fullPage:true});
    await page.goto('/manage/projects/create');await page.getByLabel('Azienda').selectOption({label:'GED · Demo'});const name='Progetto collaudo '+Date.now();await page.getByLabel('Nome *',{exact:true}).fill(name);await page.getByLabel('Descrizione',{exact:true}).fill('Progetto dimostrativo.');await page.getByRole('combobox',{name:'Stato *',exact:true}).selectOption('active');await page.getByRole('button',{name:'Salva progetto'}).click();await expect(page.getByText('Dati salvati.')).toBeVisible();await expect(page.getByText(name,{exact:true})).toBeVisible();
    await page.getByRole('row').filter({hasText:name}).getByRole('link',{name:'Dettagli'}).click();await page.getByRole('link',{name:'Modifica',exact:true}).click();await page.getByRole('combobox',{name:'Stato *',exact:true}).selectOption('archived');await page.getByRole('button',{name:'Salva progetto'}).click();await expect(page.getByRole('row').filter({hasText:name})).toContainText('Archiviato');
});
test('accessibility: login and light/dark dashboard have no serious or critical violations',async({page})=>{
    await page.goto('/login');let result=await new AxeBuilder({page}).withTags(['wcag2a','wcag2aa','wcag21aa']).analyze();expect(result.violations.filter(v=>['serious','critical'].includes(v.impact)).map(v=>({id:v.id,nodes:v.nodes.map(n=>({target:n.target,summary:n.failureSummary}))}))).toEqual([]);
    await login(page);result=await new AxeBuilder({page}).withTags(['wcag2a','wcag2aa','wcag21aa']).analyze();expect(result.violations.filter(v=>['serious','critical'].includes(v.impact)).map(v=>({id:v.id,nodes:v.nodes.map(n=>({target:n.target,summary:n.failureSummary}))}))).toEqual([]);
    await page.getByRole('button',{name:'Cambia tema chiaro o scuro'}).click();result=await new AxeBuilder({page}).withTags(['wcag2a','wcag2aa','wcag21aa']).analyze();expect(result.violations.filter(v=>['serious','critical'].includes(v.impact)).map(v=>({id:v.id,nodes:v.nodes.map(n=>({target:n.target,summary:n.failureSummary}))}))).toEqual([]);
});
test('security: CSRF is enforced by the live HTTP server',async({request})=>{
    const response=await request.post('/login',{form:{login:'cliente@vp-hub.test',password:'wrong'}});expect(response.status()).toBe(419);
    const health=await request.get('/health');expect(health.status()).toBe(200);expect(await health.json()).toEqual({status:'ok'});
});

test('guest root renders login with compiled assets and no CSP violations', async ({ page, request }) => {
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    page.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });
    await page.addInitScript(() => {
        window.cspViolations = [];
        document.addEventListener('securitypolicyviolation', event => window.cspViolations.push(event.violatedDirective));
    });
    const response = await page.goto('/');
    expect(response.status()).toBe(200);
    expect(response.headers()['content-security-policy']).toContain("script-src 'self';");
    await expect(page).toHaveURL(/\/login$/);
    await expect(page.getByLabel('Email o username')).toBeVisible();
    await expect(page.getByLabel('Password', { exact: true })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Accedi al workspace' })).toBeVisible();
    expect(await page.locator('form input').count()).toBe(3);
    expect(await page.locator('main').evaluate(element => getComputedStyle(element).display)).toBe('grid');
    const assets = await page.locator('link[rel="stylesheet"], script[src]').evaluateAll(elements => elements.map(element => element.href || element.src));
    expect(assets.length).toBeGreaterThanOrEqual(2);
    for (const asset of assets) {
        expect(new URL(asset).origin).toBe(new URL(page.url()).origin);
        expect(new URL(asset).pathname).toMatch(/^\/build\/assets\//);
        const result = await request.get(asset);
        expect(result.status()).toBe(200);
        expect((await result.body()).length).toBeGreaterThan(100);
        expect(result.headers()['content-type']).toMatch(/css|javascript/);
    }
    await page.evaluate(() => document.fonts.ready);
    expect(await page.evaluate(() => window.cspViolations)).toEqual([]);
    expect(errors).toEqual([]);
});

test('username login, authenticated root and logout work without verification screens', async ({ page }) => {
    await login(page);
    const username = await page.locator('.user-card small').last().textContent();
    await page.getByRole('button', { name: 'Esci dall’account' }).click();
    await expect(page).toHaveURL(/\/login$/);
    await page.getByLabel('Email o username').fill(username.toUpperCase());
    await page.getByLabel('Password', { exact: true }).fill(pass('cliente@vp-hub.test'));
    await page.getByRole('button', { name: 'Accedi al workspace' }).click();
    await expect(page).toHaveURL(/\/dashboard$/);
    await page.goto('/');
    await expect(page).toHaveURL(/\/dashboard$/);
    expect((await page.goto('/two-factor-challenge')).status()).toBe(404);
    await page.goto('/dashboard');
    await page.getByRole('button', { name: 'Esci dall’account' }).click();
    await page.goto('/dashboard');
    await expect(page).toHaveURL(/\/login$/);
});

test.describe('server rendered login', () => {
    test.use({ javaScriptEnabled: false });
    test('works without JavaScript', async ({ page }) => {
        await page.goto('/');
        await expect(page.getByLabel('Email o username')).toBeVisible();
        await login(page);
        await expect(page.getByRole('heading', { name: 'GED · Demo', exact: true })).toBeVisible();
    });
});
