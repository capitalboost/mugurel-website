// N7: adminul catalogului nou — lista cu filtre, produs nou (cu validare), stocul ajunge pe site, importul Excel
// in doi pasi. Doar desktop. Produsul de test se sterge la final; stocul modificat se restaureaza.
const { test, expect } = require('@playwright/test');
const path = require('path');
const fs = require('fs');

test.beforeEach(({}, info) => test.skip(info.project.name !== 'desktop', 'adminul se testeaza doar pe desktop'));

const TABLA = 'G:/My Drive/Business - Online/Mugurel - Dunlite - Magazin Constructii/Fisiere de lucru/Prouse Dunlite client/tabla_complet_MU.xlsx';
const CM11 = '5946043001367'; // Adeziv Ceresit CM 11 Plus 25 kg

async function login(page) {
  await page.goto('/admin/?page=login');
  await page.fill('input[name="username"]', 'admin');
  await page.fill('input[name="password"]', 'local-test-2026');
  await page.click('button[type="submit"]');
  await expect(page).not.toHaveURL(/page=login/);
}

test('fara login, importul si produsele cer autentificare', async ({ page }) => {
  for (const u of ['/admin/?page=import', '/admin/?page=products&action=new']) {
    await page.goto(u);
    await expect(page).toHaveURL(/page=login/);
  }
});

test('lista: cautare dupa codul de bare si filtrul „De verificat"', async ({ page }) => {
  await login(page);
  await page.goto('/admin/?page=products&q=' + CM11);
  await expect(page.locator('.adm-table tbody tr')).toHaveCount(1);
  await expect(page.locator('.adm-table tbody tr')).toContainText('Ceresit CM 11 Plus');
  await page.goto('/admin/?page=products&verify=de_verificat');
  await expect(page.locator('h2')).toContainText('(');
  await expect(page.locator('.adm-badge--warn', { hasText: 'De verificat' }).first()).toBeVisible();
  await expect(page.locator('.adm-badge--ok')).toHaveCount(0);
});

test('produs nou: validare, salvare, pagina pe site, colectie dupa tag, stergere', async ({ page }) => {
  await login(page);
  await page.goto('/admin/?page=products&action=new');
  const nume = 'Produs test admin ' + Date.now();
  await page.fill('input[name="name"]', nume);
  await page.selectOption('select[name="category_id"]', { label: '— — Ciment' });
  await page.fill('input[name="price"]', 'abc');
  await page.fill('input[name="price_unit"]', 'sac');
  await page.fill('textarea[name="tags"]', 'Testbrand; grosime 1,5 mm; fațadă');
  await page.fill('textarea[name="description_html"]', '<p><strong>Titlu</strong></p><p>Primul paragraf.</p><script>alert(1)</script>');
  await page.locator('input[name="spec_key[]"]').first().fill('Greutate');
  await page.locator('input[name="spec_value[]"]').first().fill('25 kg');
  await page.click('form.adm-form button[type="submit"]');
  // pret invalid: eroare, iar formularul pastreaza ce s-a scris
  await expect(page.locator('.flash--error')).toContainText('Pretul');
  await expect(page.locator('input[name="name"]')).toHaveValue(nume);
  await expect(page.locator('textarea[name="tags"]')).toHaveValue('Testbrand; grosime 1,5 mm; fațadă');

  await page.fill('input[name="price"]', '12,50');
  await page.click('form.adm-form button[type="submit"]');
  await expect(page.locator('.flash--success')).toContainText('creat');
  await expect(page.locator('input[name="barcode"]')).toHaveValue(/^INT-\d{10}$/);
  await expect(page.locator('textarea[name="description_html"]')).not.toHaveValue(/script/);

  const href = await page.locator('a', { hasText: 'Vezi pe site' }).getAttribute('href');
  await page.goto(href);
  await expect(page.locator('h1')).toHaveText(nume);
  await expect(page.locator('.produs-price')).toHaveText('12,50 lei / sac');
  await expect(page.locator('.produs-specs')).toContainText('25 kg');
  await expect(page.locator('.produs-tags')).toContainText('Fațadă');
  await page.goto('/fatada.html');
  await expect(page.locator('.prod-card', { hasText: nume })).toHaveCount(1);

  await page.goto('/admin/?page=products&q=' + encodeURIComponent(nume));
  await page.locator('a', { hasText: 'Editeaza' }).first().click();
  page.once('dialog', (d) => d.accept());
  await page.click('form.adm-delete button');
  await expect(page.locator('.flash--success')).toContainText('sters');
  expect((await page.request.get(href)).status()).toBe(404);
});

test('stocul si vizibilitatea schimbate din admin ajung pe site', async ({ page }) => {
  await login(page);
  await page.goto('/admin/?page=products&q=' + CM11);
  await page.locator('a', { hasText: 'Editeaza' }).first().click();
  const edit = page.url();
  const site = await page.locator('a', { hasText: 'Vezi pe site' }).getAttribute('href');
  try {
    await page.selectOption('select[name="stock_status"]', 'la_comanda');
    await page.click('form.adm-form button[type="submit"]');
    await expect(page.locator('.flash--success')).toBeVisible();
    await page.goto(site);
    await expect(page.locator('.produs-buy .stock')).toHaveText('La comanda');

    await page.goto(edit);
    await page.uncheck('input[name="is_visible"]');
    await page.click('form.adm-form button[type="submit"]');
    expect((await page.request.get(site)).status()).toBe(404);
  } finally {
    await page.goto(edit);
    await page.selectOption('select[name="stock_status"]', 'in_stoc');
    await page.check('input[name="is_visible"]');
    await page.click('form.adm-form button[type="submit"]');
    await expect(page.locator('.flash--success')).toBeVisible();
  }
  await page.goto(site);
  await expect(page.locator('.produs-buy .stock')).toHaveText('In stoc');
});

test('import Excel: fisier gresit respins; fisierul real => raport, apoi aplicare fara dubluri', async ({ page }) => {
  test.skip(!fs.existsSync(TABLA), 'fisierul de la client nu e pe aceasta masina');
  test.setTimeout(90000);
  await login(page);
  await page.goto('/admin/?page=import');
  await page.setInputFiles('input[name="file"]', { name: 'nu-e-excel.xlsx', mimeType: 'application/octet-stream', buffer: Buffer.from('text simplu') });
  await page.click('form.adm-upload button[type="submit"]');
  await expect(page.locator('.flash--error')).toContainText('nu e un Excel');

  await page.setInputFiles('input[name="file"]', TABLA);
  await page.click('form.adm-upload button[type="submit"]');
  await expect(page).toHaveURL(/action=review/);
  const stat = (label) => page.locator('.adm-stat', { hasText: label }).locator('.adm-stat__num');
  await expect(stat('Produse in fisier')).toHaveText('164');
  await expect(stat('Produse noi')).toHaveText('0');
  await expect(stat('Se actualizeaza')).toHaveText('164');
  await expect(page.locator('.adm-report pre')).toContainText('Acoperiș');

  await page.click('button:has-text("Aplica importul")');
  await expect(page).toHaveURL(/page=products/);
  await expect(page.locator('.flash--success')).toContainText('0 produse noi, 164 actualizate');
  // a doua aplicare cu acelasi token nu mai e posibila (fisierul temporar a fost sters)
  await page.goto('/admin/?page=import&action=review');
  await expect(page.locator('.flash--error')).toContainText('Incarca din nou');
});
