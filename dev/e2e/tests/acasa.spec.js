// Prima pagina: 6 produse recomandate din catalog (randate pe server, fara JS), meniul cu panouri derulante,
// iar pe telefon produsele apar imediat (fara hero si fara cardurile de avantaje).
const { test, expect } = require('@playwright/test');

test.beforeEach(async ({ context }) => {
  await context.route(/googletagmanager|google-analytics|clarity\.ms|fonts\.(googleapis|gstatic)/, (r) => r.abort());
});

test('6 produse recomandate cu pret, stoc si buton de cos; adaugarea ajunge in cos', async ({ page }) => {
  await page.goto('/');
  const cards = page.locator('.prod-section .cat-card');
  await expect(cards).toHaveCount(6);
  await expect(cards.locator('.btn-cart')).toHaveCount(6);
  await expect(cards.locator('.stock')).toHaveCount(6);
  await expect(cards.locator('.prod-price').filter({ hasText: 'Pret la cerere' })).toHaveCount(0);

  const first = cards.first();
  const name = (await first.locator('.prod-name').textContent()).trim();
  const id = await first.getAttribute('data-product-id');
  await first.locator('.btn-cart').scrollIntoViewIfNeeded();
  await first.locator('.btn-cart').click();
  await expect(page.locator('.hdr-cart-count')).toHaveText('1');
  await page.goto('/cos.html');
  await expect(page.locator(`.cos-line[data-id="${id}"] .cos-line-name`)).toHaveText(name);
});

test('meniul de sus: panoul derulant al unei ramuri duce la categorii', async ({ page }, info) => {
  test.skip(info.project.name !== 'desktop', 'meniul de sus e doar pe desktop');
  await page.goto('/');
  const dd = page.locator('.nav-dd').first();
  await dd.hover();
  const panel = dd.locator('.nav-dd-menu');
  await expect(panel).toBeVisible();
  await panel.locator('.nav-dd-link', { hasText: 'Țevi rotunde' }).click();
  await expect(page).toHaveURL(/\/tevi-rotunde\.html$/);
  await expect(page.locator('h1')).toHaveText('Țevi rotunde');
});

test('pe telefon: fara hero si fara avantaje, produsele vin primele', async ({ page }, info) => {
  test.skip(info.project.name !== 'mobil', 'doar pe telefon');
  const hero = [];
  page.on('request', (r) => { if (r.url().includes('hero.avif')) { hero.push(r.url()); } });
  await page.goto('/');
  await expect(page.locator('.hero')).toBeHidden();
  await expect(page.locator('.trust-bar')).toBeHidden();
  const y = (await page.locator('.prod-section .cat-card').first().boundingBox()).y;
  expect(y).toBeLessThan(600);
  expect(hero, 'imaginea hero nu se descarca pe telefon').toEqual([]);
});
