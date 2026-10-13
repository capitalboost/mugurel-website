// T14: pagina de confirmare si fluxul complet din browser. Necesita fixturile din dev/fixtures/cos_test.sql.
const { test, expect } = require('@playwright/test');

const THIRD_PARTY = /googletagmanager|google-analytics|clarity\.ms|fonts\.(googleapis|gstatic)/;

test.beforeEach(async ({ context }) => {
  await context.route(THIRD_PARTY, (r) => r.abort());
});

async function placeOrder(page, { email = 'ion@exemplu.ro', plata = 'ramburs' } = {}) {
  await page.goto('/acoperis.html');
  await page.locator('.btn-cart[data-cart-id="3470"]').click();
  await page.goto('/electrice.html');
  await page.locator('.btn-cart[data-cart-id="3678"]').click();
  await page.locator('.hdr-cart').click();
  await page.locator('a.cos-continue').click();
  await expect(page.locator('[data-cos-total]')).toHaveText('109,00 lei');
  await page.fill('#f-nume', 'Ion Popescu');
  await page.fill('#f-telefon', '0749 130 565');
  await page.fill('#f-email', email);
  await page.check(`input[name="plata"][value="${plata}"]`);
  if (plata !== 'ridicare') {
    await page.selectOption('#f-judet', 'Dolj');
    await page.fill('#f-localitate', 'Dabuleni');
    await page.fill('#f-adresa', 'Str. Caracal 52');
  }
  await page.check('input[name="acord"]');
  await page.click('[data-comanda-submit]');
  await expect(page).toHaveURL(/\/comanda-trimisa\.html\?t=[0-9a-f]{48}$/);
  await expect(page.locator('[data-order-number]')).toBeVisible();
  await page.waitForLoadState('load'); // scripturile paginii (golirea cosului) au rulat
}

test('flux complet: produs → cos → comanda → confirmare, cosul golit', async ({ page }) => {
  await placeOrder(page);
  await expect(page.locator('h1')).toHaveText('Multumim! Comanda a fost trimisa');
  await expect(page.locator('[data-order-number]')).toHaveText(/^MUG-\d{4}-\d{6}$/);
  await expect(page.locator('[data-order-total]')).toHaveText('109,00 lei');
  await expect(page.locator('.comanda-sum-line')).toHaveCount(2);
  await expect(page.locator('.confirmare-wrap')).toContainText('ion@exemplu.ro');
  await expect(page.locator('.confirmare-pasi')).toContainText('+40749130565');
  await expect(page.locator('.confirmare-pasi')).toContainText('Str. Caracal 52, Dabuleni, jud. Dolj');
  await expect(page.locator('.hdr-cart-count')).toBeHidden(); // cosul a fost golit
  expect(await page.evaluate(() => window.MugurelCos.read())).toEqual([]);
});

test('reincarcarea confirmarii nu retrimite nimic si arata aceeasi comanda', async ({ page }) => {
  await placeOrder(page);
  const nr = await page.locator('[data-order-number]').textContent();
  const res = await page.reload();
  expect(res.request().method()).toBe('GET');
  await expect(page.locator('[data-order-number]')).toHaveText(nr);
});

test('„Inapoi" dupa confirmare: cosul e gol, comanda nu se poate retrimite', async ({ page }) => {
  await placeOrder(page);
  await page.goBack();
  await expect(page.locator('[data-cos-state="empty"]')).toBeVisible();
  await expect(page.locator('[data-comanda-submit]')).toHaveCount(0);
});

test('legatura redeschisa mai tarziu nu goleste un cos nou', async ({ page, context }) => {
  await placeOrder(page);
  const url = page.url();
  await page.goto('/acoperis.html');
  await page.locator('.btn-cart[data-cart-id="3471"]').click();
  await expect(page.locator('.hdr-cart-count')).toHaveText('1');
  const later = await context.newPage(); // ca din email, in alt tab
  await later.goto(url);
  await expect(later.locator('[data-order-number]')).toBeVisible();
  await expect(later.locator('.hdr-cart-count')).toHaveText('1');
});

test('ridicare din magazin: confirmarea arata adresa magazinului, fara transport', async ({ page }) => {
  await placeOrder(page, { email: 'achizitii@firma.ro', plata: 'ridicare' });
  await expect(page.locator('.confirmare-pasi')).toContainText('Ridici marfa din magazin: Strada Caracal 52, Dabuleni.');
  await expect(page.locator('.confirmare-wrap')).not.toContainText('Transportul nu este inclus');
});

test('token gresit: 404 cu mesaj util; pagina nu se indexeaza si nu incarca analiza', async ({ page }) => {
  const res = await page.goto('/comanda-trimisa.html?t=' + '0'.repeat(48));
  expect(res.status()).toBe(404);
  await expect(page.locator('h1')).toHaveText('Comanda nu a fost gasita');
  await expect(page.locator('meta[name="robots"]')).toHaveAttribute('content', 'noindex, nofollow');
  expect(await page.locator('script[src*="googletagmanager"]').count()).toBe(0);
  expect(await page.content()).not.toContain('clarity.ms/tag');
  expect((await page.goto('/comanda-trimisa.html')).status()).toBe(404);
});
