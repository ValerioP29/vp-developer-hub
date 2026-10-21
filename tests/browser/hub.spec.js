import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import fs from 'node:fs';
import crypto from 'node:crypto';
const secrets = fs.readFileSync('storage/app/demo-credentials.txt','utf8');
const pass = email => secrets.split('\n').find(line => line.startsWith(email+': ')).slice(email.length+2);
const secretFile = 'storage/app/browser-mfa-secret.txt';
function totp(secret) {
    const alphabet='ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';let bits='';for(const c of secret.toUpperCase())bits+=alphabet.indexOf(c).toString(2).padStart(5,'0');
    const key=Buffer.from(bits.match(/.{8}/g).map(b=>parseInt(b,2)));const count=Buffer.alloc(8);count.writeBigUInt64BE(BigInt(Math.floor(Date.now()/30000)));
    const digest=crypto.createHmac('sha1',key).update(count).digest();const offset=digest[19]&15;return ((digest.readUInt32BE(offset)&0x7fffffff)%1000000).toString().padStart(6,'0');
}
async function login(page,email='cliente@vp-hub.test') {
    await page.goto('/login');await page.getByLabel('Email',{exact:true}).fill(email);await page.getByLabel('Password',{exact:true}).fill(pass(email));await page.getByRole('button',{name:'Accedi al workspace'}).click();
    if(email.startsWith('admin')) {
        await page.waitForURL(/dashboard|security|confirm-password|two-factor-challenge/);
        if(page.url().includes('two-factor-challenge')){await page.getByLabel('Codice a 6 cifre').fill(totp(fs.readFileSync(secretFile,'utf8')));await page.getByRole('button',{name:'Continua'}).click();}
        if(page.url().includes('confirm-password')){await page.getByLabel('Password',{exact:true}).fill(pass(email));await page.getByRole('button',{name:'Continua'}).click();}
        await page.waitForURL(/security|dashboard/);
        if(page.url().includes('security')) {
            if(await page.getByRole('button',{name:'Configura autenticazione a due fattori'}).count())await page.getByRole('button',{name:'Configura autenticazione a due fattori'}).click();
            const key=await page.locator('code').textContent();fs.writeFileSync(secretFile,key,{mode:0o600});
            await page.getByLabel('Codice a 6 cifre').fill(totp(key));await page.getByRole('button',{name:'Conferma e attiva'}).click();await page.getByRole('link',{name:'Apri il workspace'}).click();
        }
    }
    await expect(page).toHaveURL(/dashboard/);
}
test('client desktop: dashboard, privacy, exports and documents',async({page})=>{
    const errors=[];page.on('pageerror',e=>errors.push(e.message));await login(page);
    await expect(page.getByRole('heading',{name:'GED · Demo',exact:true})).toBeVisible();await expect(page.locator('body')).not.toContainText('Studio Orizzonte');await page.screenshot({path:'docs/screenshots/client-desktop.png',fullPage:true});
    await page.goto('/billing');await expect(page.getByRole('heading',{name:'Numeri trasparenti.'})).toBeVisible();
    const downloadPromise=page.waitForEvent('download');await page.getByRole('link',{name:'Report PDF',exact:true}).click();const download=await downloadPromise;expect(download.suggestedFilename()).toMatch(/\.pdf$/);
    await page.goto('/documents');await expect(page.getByRole('heading',{name:'Tutto al suo posto.'})).toBeVisible();expect(errors).toEqual([]);
});
test('client mobile: navigation, no horizontal overflow and dark mode',async({page})=>{
    await page.setViewportSize({width:390,height:844});await login(page);
    expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth)).toBeTruthy();await page.screenshot({path:'docs/screenshots/client-mobile.png',fullPage:true});
    await page.getByRole('button',{name:'Apri o chiudi navigazione'}).click();await page.locator('#sidebar').getByRole('link',{name:'Richieste assistenza'}).click();await expect(page).toHaveURL(/manage\/tickets/);
    await page.getByRole('button',{name:'Cambia tema chiaro o scuro'}).click();await expect(page.locator('html')).toHaveAttribute('data-theme','dark');await page.screenshot({path:'docs/screenshots/client-mobile-dark.png',fullPage:true});
});
test('client creates request, comments and uploads private attachment',async({page})=>{
    await login(page);await page.goto('/manage/tickets/create');const title='Verifica browser '+Date.now();
    await page.getByLabel('Oggetto').fill(title);await page.getByLabel('Descrivi la richiesta').fill('Richiesta fittizia creata dal collaudo browser.');await page.getByRole('button',{name:'Invia richiesta',exact:true}).click();await expect(page.getByRole('heading',{name:title,exact:true})).toBeVisible();
    await page.getByLabel('Aggiungi un commento').fill('Un aggiornamento dal browser.');await page.getByRole('button',{name:'Invia commento'}).click();await expect(page.getByText('Un aggiornamento dal browser.',{exact:true})).toBeVisible();
    await page.getByLabel('Titolo',{exact:true}).fill('Allegato di prova');await page.getByLabel('File',{exact:true}).setInputFiles({name:'prova.txt',mimeType:'text/plain',buffer:Buffer.from('Documento fittizio di collaudo')});await page.getByRole('button',{name:'Carica documento'}).click();await expect(page.getByText('Documento caricato in archivio privato.')).toBeVisible();
});
test('admin enrollment, dashboard and entity CRUD in real browser',async({page})=>{
    await login(page,'admin@vp-hub.test');await expect(page.getByRole('heading',{name:'Tutto sotto controllo.'})).toBeVisible();await page.screenshot({path:'docs/screenshots/admin-desktop.png',fullPage:true});
    await page.goto('/manage/projects/create');await page.getByLabel('Azienda').selectOption({label:'GED · Demo'});const name='Progetto collaudo '+Date.now();await page.getByLabel('Nome *',{exact:true}).fill(name);await page.getByLabel('Descrizione',{exact:true}).fill('Progetto dimostrativo.');await page.getByRole('combobox',{name:'Stato *',exact:true}).selectOption('active');await page.getByRole('button',{name:'Salva progetto'}).click();await expect(page.getByText('Dati salvati.')).toBeVisible();await expect(page.getByText(name,{exact:true})).toBeVisible();
    await page.getByRole('row').filter({hasText:name}).getByRole('link',{name:'Dettagli'}).click();await page.getByRole('link',{name:'Modifica',exact:true}).click();await page.getByRole('combobox',{name:'Stato *',exact:true}).selectOption('archived');await page.getByRole('button',{name:'Salva progetto'}).click();await expect(page.getByRole('row').filter({hasText:name})).toContainText('Archiviato');
});
test('accessibility: login and light/dark dashboard have no serious or critical violations',async({page})=>{
    await page.goto('/login');let result=await new AxeBuilder({page}).withTags(['wcag2a','wcag2aa','wcag21aa']).analyze();expect(result.violations.filter(v=>['serious','critical'].includes(v.impact)).map(v=>({id:v.id,nodes:v.nodes.map(n=>({target:n.target,summary:n.failureSummary}))}))).toEqual([]);
    await login(page);result=await new AxeBuilder({page}).withTags(['wcag2a','wcag2aa','wcag21aa']).analyze();expect(result.violations.filter(v=>['serious','critical'].includes(v.impact)).map(v=>({id:v.id,nodes:v.nodes.map(n=>({target:n.target,summary:n.failureSummary}))}))).toEqual([]);
    await page.getByRole('button',{name:'Cambia tema chiaro o scuro'}).click();result=await new AxeBuilder({page}).withTags(['wcag2a','wcag2aa','wcag21aa']).analyze();expect(result.violations.filter(v=>['serious','critical'].includes(v.impact)).map(v=>({id:v.id,nodes:v.nodes.map(n=>({target:n.target,summary:n.failureSummary}))}))).toEqual([]);
});
test('security: CSRF is enforced by the live HTTP server',async({request})=>{
    const response=await request.post('/login',{form:{email:'cliente@vp-hub.test',password:'wrong'}});expect(response.status()).toBe(419);
    const health=await request.get('/health');expect(health.status()).toBe(200);expect(await health.json()).toEqual({status:'ok'});
});
