// Prima pagina: cardurile statice „Produse reprezentative" sunt completate din baza (pret, stoc, buton de cos).
const { test, expect } = require('@playwright/test');

test.beforeEach(async ({ context }) => {
  await context.route(/googletagmanager|google-analytics|clarity\.ms|fonts\.(googleapis|gstatic)/, (r) => r.abort());
});

test('cele 6 produse de pe prima pagina au pret din baza, stoc si buton de cos', async ({ page }) => {
  await page.goto('/');
  const cards = page.locator('.prod-card[data-product-id]');
  await expect(cards).toHaveCount(6);
  await expect(page.locator('.prod-card[data-hydrated]')).toHaveCount(6);
  await expect(page.locator('.prod-card[data-product-id="30"] .prod-price')).toHaveText('44,00 lei / m²');
  await expect(page.locator('.prod-card[data-product-id] .prod-price').filter({ hasText: 'Pret la cerere' })).toHaveCount(0);
  await expect(page.locator('.prod-card[data-product-id] .btn-cart')).toHaveCount(6);
  await expect(page.locator('.prod-card[data-product-id] .stock')).toHaveCount(6);

  const btn = page.locator('.prod-card[data-product-id="47"] .btn-cart');
  await btn.scrollIntoViewIfNeeded();
  await btn.click();
  await expect(page.locator('.hdr-cart-count')).toHaveText('1');
  await page.goto('/cos.html');
  await expect(page.locator('.cos-line[data-id="47"] .cos-line-name')).toHaveText('Bloc BCA Standard (D400–D500)');
});

test('daca serverul nu raspunde, cardurile raman cu WhatsApp (fara erori)', async ({ page }) => {
  const errors = [];
  page.on('pageerror', (e) => errors.push(e.message));
  await page.route('**/cos-produse.php', (r) => r.fulfill({ status: 503, body: '{}' }));
  await page.goto('/');
  await expect(page.locator('.prod-card[data-product-id] .btn-wa-prod')).toHaveCount(6);
  await expect(page.locator('.prod-card[data-product-id] .btn-cart')).toHaveCount(0);
  expect(errors).toEqual([]);
});
