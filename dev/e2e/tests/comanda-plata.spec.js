// T9: metoda de plata si mesajele despre transport. Necesita fixturile din dev/fixtures/cos_test.sql.
const { test, expect } = require('@playwright/test');
const { P, ids, addFromProductPage } = require('./fixtures');
let I; // id-urile produselor de test (dev/fixtures/cos_test.sql)

test.beforeEach(async ({ request }) => { I = await ids(request); });

const THIRD_PARTY = /googletagmanager|google-analytics|clarity\.ms|fonts\.(googleapis|gstatic)/;

test.beforeEach(async ({ context, page }) => {
  await context.route(THIRD_PARTY, (r) => r.abort());
  await page.goto('/index.html');
  await page.evaluate((id) => localStorage.setItem('mugurel_cos', JSON.stringify({ v: 1, items: [{ id, qty: 1 }] })), I.A);
  await page.goto('/comanda.html');
  await expect(page.locator('[data-cos-total]')).toBeVisible();
});

const radio = (page, v) => page.locator(`input[name="plata"][value="${v}"]`);

async function fillContact(page) {
  await page.fill('#f-nume', 'Ion Popescu');
  await page.fill('#f-telefon', '0749130565');
  await page.fill('#f-email', 'ion@exemplu.ro');
  await page.check('input[name="acord"]');
}
async function submitAndGetStatus(page) {
  const [res] = await Promise.all([
    page.waitForResponse((r) => r.url().endsWith('/comanda.html') && r.request().method() === 'POST'),
    page.click('[data-comanda-submit]'),
  ]);
  return res.status();
}

test('cele trei metode, fiecare cu explicatie, si mesajul despre transport', async ({ page }) => {
  await expect(page.locator('.form-plata-option')).toHaveCount(3);
  await expect(page.locator('.form-plata-option', { hasText: 'Ramburs la livrare' })).toContainText('numerar la primirea marfii');
  await expect(page.locator('.form-plata-option', { hasText: 'Transfer bancar' })).toContainText('factura proforma');
  await expect(page.locator('.form-plata-option', { hasText: 'Ridicare si plata in magazin' })).toContainText('Strada Caracal 52, Dabuleni');
  await expect(page.locator('[data-transport-info]')).toContainText('Transportul nu este inclus in total');
  await expect(page.locator('.form-plata-option', { hasText: /card online/i })).toHaveCount(0);
});

test('fara metoda aleasa: eroare pe grup, sub optiuni', async ({ page }) => {
  await fillContact(page);
  await page.click('[data-comanda-submit]');
  await expect(page.locator('#err-plata')).toHaveText('Alege cum vrei sa platesti.');
  await expect(page.locator('fieldset.form-plata')).toHaveAttribute('aria-invalid', 'true');
  // mesajul sta dupa lista de optiuni, nu in interiorul primei optiuni
  expect(await page.locator('.form-plata-options + #err-plata').count()).toBe(1);
  await radio(page, 'ramburs').check();
  await expect(page.locator('#err-plata')).toHaveCount(0);
});

test('ridicare din magazin: adresa de livrare dispare si nu e ceruta', async ({ page }) => {
  await fillContact(page);
  await radio(page, 'ridicare').check();
  await expect(page.locator('[data-adresa-block]')).toBeHidden();
  await expect(page.locator('[data-transport-info]')).toBeHidden();
  expect(await submitAndGetStatus(page)).toBe(303); // salvata fara adresa de livrare
  await expect(page).toHaveURL(/comanda-trimisa\.html\?t=[0-9a-f]{48}$/);
});

test('ramburs: adresa de livrare ramane obligatorie', async ({ page }) => {
  await fillContact(page);
  await radio(page, 'ridicare').check();
  await radio(page, 'ramburs').check();
  await expect(page.locator('[data-adresa-block]')).toBeVisible();
  await page.click('[data-comanda-submit]');
  await expect(page.locator('#err-adresa')).toBeVisible();
  await expect(page.locator('#err-judet')).toBeVisible();
});

test('serverul refuza o metoda de plata inventata', async ({ page }) => {
  await fillContact(page);
  await page.selectOption('#f-judet', 'Dolj');
  await page.fill('#f-localitate', 'Dabuleni');
  await page.fill('#f-adresa', 'Str. Caracal 52');
  await radio(page, 'ramburs').check();
  await page.evaluate(() => { document.querySelector('input[name="plata"][value="ramburs"]').value = 'card'; });
  expect(await submitAndGetStatus(page)).toBe(422);
  await expect(page.locator('#err-plata')).toHaveText('Alege cum vrei sa platesti.');
});

test('mesajul de transport apare si in cos', async ({ page }) => {
  await page.goto('/cos.html');
  await expect(page.locator('.cos-summary-note')).toContainText('transportului nu este inclus');
});
