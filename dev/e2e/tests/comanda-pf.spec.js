// T7: formularul de comanda pentru persoana fizica. Necesita fixturile din dev/fixtures/cos_test.sql.
const { test, expect } = require('@playwright/test');

const THIRD_PARTY = /googletagmanager|google-analytics|clarity\.ms|fonts\.(googleapis|gstatic)/;
const KEY = 'mugurel_cos';

test.beforeEach(async ({ context }) => {
  await context.route(THIRD_PARTY, (r) => r.abort());
});

async function openOrder(page, items) {
  await page.goto('/index.html');
  await page.evaluate(([k, its]) => localStorage.setItem(k, JSON.stringify({ v: 1, items: its })), [KEY, items]);
  await page.goto('/comanda.html');
}

const VALID = {
  '#f-nume': 'Ion Popescu',
  '#f-telefon': '0749 130 565',
  '#f-email': 'ion@exemplu.ro',
  '#f-localitate': 'Dabuleni',
  '#f-adresa': 'Str. Caracal nr. 52',
};
async function fillValid(page) {
  for (const [sel, val] of Object.entries(VALID)) { await page.fill(sel, val); }
  await page.selectOption('#f-judet', 'Dolj');
}

test('cos gol: fara formular, cu legatura spre catalog', async ({ page }) => {
  await page.goto('/comanda.html');
  await expect(page.locator('[data-cos-state="empty"]')).toBeVisible();
  await expect(page.locator('[data-comanda-form]')).toHaveCount(0);
  await expect(page.locator('meta[name="robots"]')).toHaveAttribute('content', 'noindex, nofollow');
});

test('sumarul comenzii vine de la server si cosul ajunge in campul ascuns', async ({ page }) => {
  await openOrder(page, [{ id: 3470, qty: 2 }, { id: 3674, qty: 2.5 }]);
  await expect(page.locator('[data-cos-total]')).toHaveText('201,23 lei');
  await expect(page.locator('.comanda-sum-line')).toHaveCount(2);
  await expect(page.locator('.comanda-sum-line').first()).toContainText('× 2 buc');
  expect(JSON.parse(await page.locator('[data-cos-field]').inputValue())).toEqual([{ id: 3470, qty: 2 }, { id: 3674, qty: 2.5 }]);
  await expect(page.locator('[data-comanda-submit]')).toBeEnabled();
});

test('trimis gol: erori langa fiecare camp, focus pe primul, ramane pe pagina', async ({ page }) => {
  await openOrder(page, [{ id: 3470, qty: 1 }]);
  await expect(page.locator('[data-cos-total]')).toBeVisible();
  await page.click('[data-comanda-submit]');
  await expect(page.locator('.form-error')).toHaveCount(6);
  await expect(page.locator('#err-nume')).toHaveText('Scrie numele si prenumele.');
  await expect(page.locator('#f-nume')).toBeFocused();
  await expect(page.locator('#f-nume')).toHaveAttribute('aria-describedby', 'err-nume');
  await expect(page).toHaveURL(/\/comanda\.html$/);
});

test('eroarea dispare dupa corectare', async ({ page }) => {
  await openOrder(page, [{ id: 3470, qty: 1 }]);
  await fillValid(page);
  await page.fill('#f-email', 'ion@exemplu');
  await page.click('[data-comanda-submit]');
  await expect(page.locator('#err-email')).toHaveText('Adresa de email nu pare corecta.');
  await page.fill('#f-email', 'ion@exemplu.ro');
  await page.locator('#f-email').blur();
  await expect(page.locator('#err-email')).toHaveCount(0);
});

test('formular valid: ajunge la server, care pastreaza datele (trimiterea reala vine in T11)', async ({ page }) => {
  await openOrder(page, [{ id: 3470, qty: 1 }]);
  await expect(page.locator('[data-cos-total]')).toBeVisible();
  await fillValid(page);
  await page.fill('#f-mentiuni', 'Sunati inainte de livrare.');
  const [res] = await Promise.all([
    page.waitForResponse((r) => r.url().endsWith('/comanda.html') && r.request().method() === 'POST'),
    page.click('[data-comanda-submit]'),
  ]);
  expect(res.status()).toBe(503);
  await expect(page.locator('[data-form-notice]')).toContainText('nu este inca activa');
  await expect(page.locator('#f-nume')).toHaveValue('Ion Popescu');
  await expect(page.locator('#f-telefon')).toHaveValue('+40749130565'); // normalizat de server
  await expect(page.locator('#f-judet')).toHaveValue('Dolj');
  await expect(page.locator('#f-mentiuni')).toHaveValue('Sunati inainte de livrare.');
});

test('produse care nu se mai pot comanda => butonul de trimitere e blocat', async ({ page }) => {
  await openOrder(page, [{ id: 3470, qty: 1 }, { id: 3686, qty: 1 }]);
  await expect(page.locator('.comanda-summary .cos-msg--warn')).toBeVisible();
  await expect(page.locator('[data-comanda-submit]')).toBeDisabled();
});

test('pagina incape pe ecran; pe mobil sumarul e deasupra formularului', async ({ page }, info) => {
  await openOrder(page, [{ id: 3470, qty: 1 }]);
  await expect(page.locator('[data-cos-total]')).toBeVisible();
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
  expect(overflow).toBeLessThanOrEqual(0);
  const sumY = (await page.locator('#comanda-sumar').boundingBox()).y;
  const formY = (await page.locator('[data-comanda-form]').boundingBox()).y;
  if (info.project.name === 'desktop') {
    expect(Math.abs(sumY - formY)).toBeLessThan(5); // alaturi
  } else {
    expect(sumY).toBeLessThan(formY); // deasupra
  }
});

test.describe('fara JavaScript', () => {
  test.use({ javaScriptEnabled: false });
  test.beforeEach(({}, info) => test.skip(info.project.name !== 'desktop', 'o singura data'));

  test('serverul valideaza singur si pastreaza ce s-a scris', async ({ page }) => {
    await page.goto('/comanda.html');
    await expect(page.locator('noscript')).toHaveCount(1);
    await page.fill('#f-nume', 'Ion Popescu');
    await page.fill('#f-email', 'gresit');
    await page.click('[data-comanda-submit]');
    await expect(page.locator('[data-form-errors]')).toContainText('Verifica campurile marcate mai jos (5)');
    await expect(page.locator('#err-email')).toHaveText('Adresa de email nu pare corecta.');
    await expect(page.locator('#f-nume')).toHaveValue('Ion Popescu');
    await expect(page.locator('#f-email')).toHaveValue('gresit');
  });
});
