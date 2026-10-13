// T6: pagina cosului si endpoint-ul /cos-produse.php. Necesita fixturile din dev/fixtures/cos_test.sql.
const { test, expect } = require('@playwright/test');

const THIRD_PARTY = /googletagmanager|google-analytics|clarity\.ms|fonts\.(googleapis|gstatic)/;
const KEY = 'mugurel_cos';

test.beforeEach(async ({ context }) => {
  await context.route(THIRD_PARTY, (r) => r.abort());
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

test('produsele adaugate din pagini apar cu numele si pretul din baza', async ({ page }) => {
  await page.goto('/acoperis.html');
  await page.locator('.btn-cart[data-cart-id="3470"]').click();
  await page.goto('/electrice.html');
  await page.locator('.btn-cart[data-cart-id="3674"]').click();
  await page.locator('.hdr-cart').click();

  await expect(page).toHaveURL(/\/cos\.html$/);
  await expect(page.locator('.cos-line')).toHaveCount(2);
  await expect(line(page, 3470).locator('.cos-line-name')).toHaveText('Jgheab Semicircular Ø125mm');
  await expect(line(page, 3470).locator('.cos-line-price')).toHaveText('85,00 lei / buc');
  await expect(line(page, 3674).locator('.cos-line-price')).toHaveText('12,49 lei / ml');
  await expect(total(page)).toHaveText('97,49 lei');
  await expect(page.locator('a.cos-continue')).toHaveAttribute('href', '/comanda.html');
});

test('un pret falsificat in localStorage e ignorat — conteaza doar baza', async ({ page }) => {
  await openCart(page, [{ id: 3470, qty: 2, price: 0.01, name: 'Gratis' }]);
  await expect(line(page, 3470).locator('.cos-line-name')).toHaveText('Jgheab Semicircular Ø125mm');
  await expect(total(page)).toHaveText('170,00 lei');
});

test('+ / − schimba cantitatea si totalul se recalculeaza pe server', async ({ page }) => {
  await openCart(page, [{ id: 3470, qty: 1 }]);
  const l = line(page, 3470);
  await l.getByRole('button', { name: 'Creste cantitatea' }).click();
  await expect(l.locator('input')).toHaveValue('2');
  await expect(total(page)).toHaveText('170,00 lei');
  await expect(l.locator('.cos-line-total')).toHaveText('170,00 lei');

  await l.getByRole('button', { name: 'Scade cantitatea' }).click();
  await expect(total(page)).toHaveText('85,00 lei');
  await l.getByRole('button', { name: 'Scade cantitatea' }).click(); // nu coboara sub 1
  await expect(l.locator('input')).toHaveValue('1');
  expect((await page.evaluate((k) => JSON.parse(localStorage.getItem(k)), KEY)).items).toEqual([{ id: 3470, qty: 1 }]);
});

test('cantitati: zecimale la ml, intregi la buc', async ({ page }) => {
  await openCart(page, [{ id: 3674, qty: 1 }, { id: 3471, qty: 1 }]);
  const ml = line(page, 3674).locator('input');
  await ml.fill('2,5');
  await ml.press('Tab');
  await expect(line(page, 3674).locator('.cos-line-total')).toHaveText('31,23 lei'); // 12,49 × 2,5 = 31,225
  await expect(ml).toHaveValue('2,5'); // afisat cu virgula

  const buc = line(page, 3471).locator('input');
  await buc.fill('2.7');
  await buc.press('Tab');
  await expect(buc).toHaveValue('3');
  await expect(line(page, 3471).locator('.cos-line-total')).toHaveText('225,00 lei');

  await buc.fill('');
  await buc.press('Tab');
  await expect(buc).toHaveValue('3'); // camp golit => revine la cantitatea salvata
  await expect(total(page)).toHaveText('256,23 lei');
});

test('„Scoate" elimina produsul; ultimul scos => cos gol', async ({ page }) => {
  await openCart(page, [{ id: 3470, qty: 1 }, { id: 3678, qty: 2 }]);
  await expect(page.locator('.hdr-cart-count')).toHaveText('2');
  await line(page, 3470).getByRole('button', { name: /Scoate/ }).click();
  await expect(page.locator('.cos-line')).toHaveCount(1);
  await expect(page.locator('.hdr-cart-count')).toHaveText('1');
  await expect(total(page)).toHaveText('48,00 lei');
  await line(page, 3678).getByRole('button', { name: /Scoate/ }).click();
  await expect(page.locator('[data-cos-state="empty"]')).toBeVisible();
  await expect(page.locator('.hdr-cart-count')).toBeHidden();
});

test('produse care nu se mai pot comanda: mesaj pe rand si comanda blocata pana sunt scoase', async ({ page }) => {
  await openCart(page, [{ id: 3470, qty: 1 }, { id: 3686, qty: 1 }, { id: 999999, qty: 1 }]);
  await expect(line(page, 3686).locator('.cos-line-problem')).toHaveText('Produsul nu mai este in stoc.');
  await expect(line(page, 999999).locator('.cos-line-problem')).toHaveText('Produsul nu mai este disponibil pe site.');
  await expect(total(page)).toHaveText('85,00 lei');
  await expect(page.locator('button.cos-continue')).toBeDisabled();
  await expect(page.locator('.cos-msg--warn')).toBeVisible();

  for (const id of [3686, 999999]) {
    await line(page, id).getByRole('button', { name: /Scoate/ }).click();
  }
  await expect(page.locator('a.cos-continue')).toBeVisible();
  await expect(page.locator('.cos-msg--warn')).toHaveCount(0);
});

test('serverul indisponibil: mesaj clar si „Incearca din nou" functioneaza', async ({ page }) => {
  await page.route('**/cos-produse.php', (r) =>
    r.fulfill({ status: 503, contentType: 'application/json', body: JSON.stringify({ error: 'Cosul nu poate fi verificat acum.' }) }));
  await openCart(page, [{ id: 3470, qty: 1 }]);
  await expect(page.locator('[data-cos-state="error"]')).toHaveText('Cosul nu poate fi verificat acum.');
  await page.unroute('**/cos-produse.php');
  await page.getByRole('button', { name: 'Incearca din nou' }).click();
  await expect(total(page)).toHaveText('85,00 lei');
});

test('pagina incape pe ecran (fara scroll orizontal)', async ({ page }) => {
  await openCart(page, [{ id: 3470, qty: 12 }, { id: 3674, qty: 1234.5 }, { id: 3686, qty: 1 }]);
  await expect(page.locator('.cos-line')).toHaveCount(3);
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
  expect(overflow).toBeLessThanOrEqual(0);
  await expect(line(page, 3470).getByRole('button', { name: 'Creste cantitatea' })).toBeVisible();
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

  test('raspuns corect, fara cache, cu aceleasi chei pe fiecare rand', async ({ request }) => {
    const res = await request.post('/cos-produse.php', { data: { items: [{ id: 3470, qty: 2 }, { id: 999999, qty: 1 }] } });
    expect(res.status()).toBe(200);
    expect(res.headers()['cache-control']).toBe('no-store');
    const body = await res.json();
    expect(body.total_label).toBe('170,00 lei');
    expect(Object.keys(body.lines[0]).sort()).toEqual(Object.keys(body.lines[1]).sort());
  });
});
