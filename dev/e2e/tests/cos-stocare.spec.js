// T5 / N9: cosul se pastreaza in localStorage, contorul din header apare pe toate paginile.
// Necesita fixturile locale din dev/fixtures/cos_test.sql (aplicate de run.sh).
const { test, expect } = require('@playwright/test');
const { P, ids } = require('./fixtures');

const THIRD_PARTY = /googletagmanager|google-analytics|clarity\.ms|fonts\.(googleapis|gstatic)/;
const KEY = 'mugurel_cos';
const LISTA = '/adezivi-gresie-si-faianta.html'; // are produsele de test A, B, LA, IND
let I;

test.beforeEach(async ({ context, request }) => {
  await context.route(THIRD_PARTY, (r) => r.abort());
  I = await ids(request);
});

const badge = (page) => page.locator('.hdr-cart .hdr-cart-count');
const stored = (page) => page.evaluate((k) => JSON.parse(localStorage.getItem(k) || 'null'), KEY);

async function addFrom(page, id) {
  const btn = page.locator(`.btn-cart[data-cart-id="${id}"]`).first();
  await btn.scrollIntoViewIfNeeded();
  await btn.click();
  await expect(btn).toHaveText('Adaugat in cos ✓');
}

test('cos gol: legatura „Cos" exista, fara numar', async ({ page }) => {
  await page.goto(LISTA);
  const link = page.locator('.hdr-cart');
  await expect(link).toBeVisible();
  await expect(link).toHaveAttribute('href', '/cos.html');
  await expect(link).toHaveAttribute('aria-label', 'Cos de cumparaturi: 0 produse');
  await expect(badge(page)).toBeHidden();
});

test('produsele raman in cos intre pagini (lista, produs, statice, colectie) si dupa reincarcare', async ({ page }) => {
  await page.goto(LISTA);
  await addFrom(page, I.A);
  await expect(badge(page)).toHaveText('1');

  await page.goto(P.EL.url);
  await expect(badge(page)).toHaveText('1');
  await addFrom(page, I.EL);
  await expect(badge(page)).toHaveText('2');
  await expect(page.locator('.hdr-cart')).toHaveAttribute('aria-label', 'Cos de cumparaturi: 2 produse');

  for (const u of ['/index.html', '/contact.html', '/fier-forjat.html', '/catalog.html?q=ceresit']) {
    await page.goto(u);
    await expect(badge(page)).toHaveText('2');
  }
  await page.reload();
  await expect(badge(page)).toHaveText('2');
  expect(await stored(page)).toEqual({ v: 1, items: [{ id: I.A, qty: 1 }, { id: I.EL, qty: 1 }] });
});

test('acelasi produs adaugat de doua ori => un rand, cantitate 2', async ({ page }) => {
  await page.goto(LISTA);
  await addFrom(page, I.B);
  await addFrom(page, I.B);
  await expect(badge(page)).toHaveText('1');
  expect((await stored(page)).items).toEqual([{ id: I.B, qty: 2 }]);
});

test('in localStorage se salveaza doar id si cantitate (fara pret sau nume)', async ({ page }) => {
  await page.goto(LISTA);
  await addFrom(page, I.A);
  const raw = await page.evaluate((k) => localStorage.getItem(k), KEY);
  expect(raw).not.toMatch(/pret|price|Adeziv|25[.,]24/i);
});

test('date corupte sau modificate in localStorage nu strica pagina', async ({ page }) => {
  const errors = [];
  page.on('pageerror', (e) => errors.push(e.message));

  await page.goto(LISTA);
  await page.evaluate((k) => localStorage.setItem(k, '{nu e json'), KEY);
  await page.reload();
  await expect(badge(page)).toBeHidden();

  await page.evaluate(([k, I]) => localStorage.setItem(k, JSON.stringify({ v: 1, items: [
    { id: I.A, qty: 2 },
    { id: I.A, qty: 5 },        // dublura => ignorata
    { id: -1, qty: 1 },         // id invalid
    { id: 'abc', qty: 1 },
    { id: I.B, qty: 0 },        // cantitate invalida
    { id: I.KG, qty: '2,5' },   // zecimale cu virgula => 2.5
    { id: 1.5, qty: 1 },
    null,
  ] })), [KEY, I]);
  await page.reload();
  await expect(badge(page)).toHaveText('2');
  expect(await page.evaluate(() => window.MugurelCos.read())).toEqual([{ id: I.A, qty: 2 }, { id: I.KG, qty: 2.5 }]);

  // Adaugarea dupa date corupte rescrie cosul curat.
  await addFrom(page, I.B);
  expect((await stored(page)).items).toEqual([{ id: I.A, qty: 2 }, { id: I.KG, qty: 2.5 }, { id: I.B, qty: 1 }]);
  expect(errors).toEqual([]);
});

test('localStorage blocat: mesaj de eroare clar, fara confirmare falsa', async ({ page }) => {
  await page.addInitScript(() => {
    Storage.prototype.setItem = function () { throw new DOMException('blocat', 'SecurityError'); };
  });
  await page.goto(LISTA);
  const btn = page.locator(`.btn-cart[data-cart-id="${I.A}"]`);
  await btn.scrollIntoViewIfNeeded();
  await btn.click();
  await expect(page.locator('.cart-toast')).toContainText('Cosul nu poate fi salvat');
  await expect(page.locator('.cart-toast')).toHaveClass(/cart-toast--error/);
  await expect(btn).toHaveText('Adauga in cos');
  await expect(badge(page)).toBeHidden();
});

test('contorul se actualizeaza si in celalalt tab', async ({ context }) => {
  const a = await context.newPage();
  const b = await context.newPage();
  await a.goto(LISTA);
  await b.goto('/acoperis.html');
  await expect(badge(b)).toBeHidden();
  await addFrom(a, I.A);
  await expect(badge(b)).toHaveText('1');
});

test('header-ul incape pe ecran, cu contorul vizibil', async ({ page }, info) => {
  await page.goto(LISTA);
  await addFrom(page, I.A);
  await expect(badge(page)).toBeVisible();
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
  expect(overflow).toBeLessThanOrEqual(0);
  if (info.project.name === 'mobil') {
    await expect(page.locator('.hdr-cart-label')).toBeHidden(); // pe mobil doar iconita + numar
  } else {
    await expect(page.locator('.hdr-cart-label')).toBeVisible();
  }
});
