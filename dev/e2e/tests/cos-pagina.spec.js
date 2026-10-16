// T6 / N9: pagina cosului si endpoint-ul /cos-produse.php pe catalogul nou.
// Necesita fixturile din dev/fixtures/cos_test.sql (produsele de test din fixtures.js).
const { test, expect } = require('@playwright/test');
const { P, ids, addFromProductPage } = require('./fixtures');

const THIRD_PARTY = /googletagmanager|google-analytics|clarity\.ms|fonts\.(googleapis|gstatic)/;
const KEY = 'mugurel_cos';
let I; // id-urile produselor de test

test.beforeEach(async ({ context, request }) => {
  await context.route(THIRD_PARTY, (r) => r.abort());
  I = await ids(request);
});

/** Pune direct continutul cosului (orice forma, inclusiv date falsificate) si deschide /cos.html. */
async function openCart(page, items) {
  await page.goto('/index.html');
  await page.evaluate(([k, its]) => localStorage.setItem(k, JSON.stringify({ v: 1, items: its })), [KEY, items]);
  await page.goto('/cos.html');
}
const line = (page, id) => page.locator(`.cos-line[data-id="${id}"]`);
const total = (page) => page.locator('[data-cos-total]');

test('cos gol: mesaj, legatura spre catalog, fara „Continua comanda"', async ({ page }) => {
  await page.goto('/cos.html');
  await expect(page.locator('[data-cos-state="empty"]')).toBeVisible();
  await expect(page.getByRole('link', { name: 'Vezi catalogul' })).toHaveAttribute('href', '/catalog.html');
  await expect(page.locator('.cos-continue')).toHaveCount(0);
  await expect(page.locator('meta[name="robots"]')).toHaveAttribute('content', 'noindex, nofollow');
});

test('produsele adaugate apar cu numele si pretul din baza; numele duce la pagina produsului', async ({ page }) => {
  await addFromProductPage(page, 'A');
  await addFromProductPage(page, 'KG');
  await page.locator('.hdr-cart').click();

  await expect(page).toHaveURL(/\/cos\.html$/);
  await expect(page.locator('.cos-line')).toHaveCount(2);
  await expect(line(page, I.A).locator('.cos-line-name')).toHaveText(P.A.name);
  await expect(line(page, I.A).locator('.cos-line-price')).toHaveText(P.A.price);
  await expect(line(page, I.KG).locator('.cos-line-price')).toHaveText(P.KG.price);
  await expect(total(page)).toHaveText('47,67 lei'); // 25,24 + 22,43
  await expect(page.locator('a.cos-continue')).toHaveAttribute('href', '/comanda.html');
  await line(page, I.A).locator('.cos-line-name a').click();
  await expect(page).toHaveURL(new RegExp(P.A.slug));
});

test('un pret falsificat in localStorage e ignorat — conteaza doar baza', async ({ page }) => {
  await openCart(page, [{ id: I.A, qty: 2, price: 0.01, name: 'Gratis' }]);
  await expect(line(page, I.A).locator('.cos-line-name')).toHaveText(P.A.name);
  await expect(total(page)).toHaveText('50,48 lei');
});

test('+ / − schimba cantitatea si totalul se recalculeaza pe server', async ({ page }) => {
  await openCart(page, [{ id: I.A, qty: 1 }]);
  const l = line(page, I.A);
  await l.getByRole('button', { name: 'Creste cantitatea' }).click();
  await expect(l.locator('input')).toHaveValue('2');
  await expect(total(page)).toHaveText('50,48 lei');
  await expect(l.locator('.cos-line-total')).toHaveText('50,48 lei');

  await l.getByRole('button', { name: 'Scade cantitatea' }).click();
  await expect(total(page)).toHaveText('25,24 lei');
  await l.getByRole('button', { name: 'Scade cantitatea' }).click(); // nu coboara sub 1
  await expect(l.locator('input')).toHaveValue('1');
  expect((await page.evaluate((k) => JSON.parse(localStorage.getItem(k)), KEY)).items).toEqual([{ id: I.A, qty: 1 }]);
});

test('cantitati: zecimale la kg, intregi la sac', async ({ page }) => {
  await openCart(page, [{ id: I.KG, qty: 1 }, { id: I.B, qty: 1 }]);
  const kg = line(page, I.KG).locator('input');
  await kg.fill('2,5');
  await kg.press('Tab');
  await expect(line(page, I.KG).locator('.cos-line-total')).toHaveText('56,08 lei'); // 22,43 × 2,5 = 56,075
  await expect(kg).toHaveValue('2,5'); // afisat cu virgula

  const sac = line(page, I.B).locator('input');
  await sac.fill('2.7');
  await sac.press('Tab');
  await expect(sac).toHaveValue('3');
  await expect(line(page, I.B).locator('.cos-line-total')).toHaveText('170,49 lei');

  await sac.fill('');
  await sac.press('Tab');
  await expect(sac).toHaveValue('3'); // camp golit => revine la cantitatea salvata
  await expect(total(page)).toHaveText('226,57 lei');
});

test('„Scoate" elimina produsul; ultimul scos => cos gol', async ({ page }) => {
  await openCart(page, [{ id: I.A, qty: 1 }, { id: I.EL, qty: 2 }]);
  await expect(page.locator('.hdr-cart-count')).toHaveText('2');
  await line(page, I.A).getByRole('button', { name: /Scoate/ }).click();
  await expect(page.locator('.cos-line')).toHaveCount(1);
  await expect(page.locator('.hdr-cart-count')).toHaveText('1');
  await expect(total(page)).toHaveText('746,62 lei');
  await line(page, I.EL).getByRole('button', { name: /Scoate/ }).click();
  await expect(page.locator('[data-cos-state="empty"]')).toBeVisible();
  await expect(page.locator('.hdr-cart-count')).toBeHidden();
});

test('produse care nu se mai pot comanda: mesaj pe rand si comanda blocata pana sunt scoase', async ({ page }) => {
  await openCart(page, [{ id: I.A, qty: 1 }, { id: I.IND, qty: 1 }, { id: I.NP, qty: 1 }, { id: 999999, qty: 1 }]);
  await expect(line(page, I.IND).locator('.cos-line-problem')).toHaveText('Produsul nu mai este in stoc.');
  await expect(line(page, I.NP).locator('.cos-line-problem')).not.toBeEmpty();
  await expect(line(page, 999999).locator('.cos-line-problem')).toHaveText('Produsul nu mai este disponibil pe site.');
  await expect(total(page)).toHaveText('25,24 lei');
  await expect(page.locator('button.cos-continue')).toBeDisabled();
  await expect(page.locator('.cos-msg--warn')).toBeVisible();

  for (const id of [I.IND, I.NP, 999999]) {
    await line(page, id).getByRole('button', { name: /Scoate/ }).click();
  }
  await expect(page.locator('a.cos-continue')).toBeVisible();
  await expect(page.locator('.cos-msg--warn')).toHaveCount(0);
});

test('serverul indisponibil: mesaj clar si „Incearca din nou" functioneaza', async ({ page }) => {
  await page.route('**/cos-produse.php', (r) =>
    r.fulfill({ status: 503, contentType: 'application/json', body: JSON.stringify({ error: 'Cosul nu poate fi verificat acum.' }) }));
  await openCart(page, [{ id: I.A, qty: 1 }]);
  await expect(page.locator('[data-cos-state="error"]')).toHaveText('Cosul nu poate fi verificat acum.');
  await page.unroute('**/cos-produse.php');
  await page.getByRole('button', { name: 'Incearca din nou' }).click();
  await expect(total(page)).toHaveText('25,24 lei');
});

test('pagina incape pe ecran (fara scroll orizontal), si cu nume lungi', async ({ page }) => {
  await openCart(page, [{ id: I.A, qty: 12 }, { id: I.KG, qty: 1234.5 }, { id: I.IND, qty: 1 }, { id: I.EL, qty: 1 }]);
  await expect(page.locator('.cos-line')).toHaveCount(4);
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
  expect(overflow).toBeLessThanOrEqual(0);
  await expect(line(page, I.A).getByRole('button', { name: 'Creste cantitatea' })).toBeVisible();
});

test.describe('endpoint /cos-produse.php', () => {
  test.beforeEach(({}, info) => test.skip(info.project.name !== 'desktop', 'API-ul se testeaza o singura data'));

  test('accepta doar POST si date valide', async ({ request }) => {
    expect((await request.get('/cos-produse.php')).status()).toBe(405);
    expect((await request.post('/cos-produse.php', { data: 'nu e json', headers: { 'Content-Type': 'application/json' } })).status()).toBe(400);
    expect((await request.post('/cos-produse.php', { data: { lista: [] } })).status()).toBe(400);
    const big = { items: Array.from({ length: 2000 }, (_, i) => ({ id: i + 1, qty: 1, umplutura: 'x'.repeat(10) })) };
    expect((await request.post('/cos-produse.php', { data: big })).status()).toBe(413);
  });

  test('raspuns corect, fara cache, cu aceleasi chei pe fiecare rand si adresa produsului', async ({ request }) => {
    const res = await request.post('/cos-produse.php', { data: { items: [{ id: I.A, qty: 2 }, { id: 999999, qty: 1 }] } });
    expect(res.status()).toBe(200);
    expect(res.headers()['cache-control']).toBe('no-store');
    const body = await res.json();
    expect(body.total_label).toBe('50,48 lei');
    expect(body.lines[0].url).toBe(P.A.url);
    expect(Object.keys(body.lines[0]).sort()).toEqual(Object.keys(body.lines[1]).sort());
  });
});
