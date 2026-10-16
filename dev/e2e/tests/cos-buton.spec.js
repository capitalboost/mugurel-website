// T4 / N9: butonul „Adauga in cos" pe catalogul nou (liste si pagina produsului).
// Necesita fixturile locale din dev/fixtures/cos_test.sql (produsele de test din fixtures.js).
const { test, expect } = require('@playwright/test');
const { P, ids } = require('./fixtures');

const THIRD_PARTY = /googletagmanager|google-analytics|clarity\.ms|fonts\.(googleapis|gstatic)/;

test.beforeEach(async ({ page }) => {
  await page.route(THIRD_PARTY, (r) => r.abort());
});

test('listele: fiecare produs cu pret are buton de cos; fara pret => „Cere oferta" pe WhatsApp', async ({ page }) => {
  await page.goto('/adezivi-gresie-si-faianta.html');
  const cards = page.locator('.cat-card');
  const n = await cards.count();
  const fara = await cards.filter({ hasText: 'Pret la cerere' }).count();
  const indisp = await cards.filter({ has: page.locator('.stock--indisponibil') }).count();
  await expect(page.locator('.cat-card .btn-cart')).toHaveCount(n - fara - indisp);
  await expect(page.locator('.cat-card', { hasText: P.IND.name }).locator('.stock')).toHaveText('Indisponibil');
  await expect(page.locator('.cat-card', { hasText: P.IND.name }).locator('.btn-cart')).toHaveCount(0);
  await expect(page.locator('.cat-card', { hasText: P.LA.name }).locator('.stock')).toHaveText('La comanda');
  await expect(page.locator('.cat-card', { hasText: P.LA.name }).locator('.btn-cart')).toHaveCount(1);

  await page.goto('/profile-laminate.html');
  const np = page.locator('.cat-card', { hasText: P.NP.name });
  await expect(np.locator('.prod-price')).toHaveText('Pret la cerere');
  await expect(np.locator('.btn-cart')).toHaveCount(0);
  await expect(np.locator('.btn-wa-prod')).toHaveAttribute('href', /wa\.me\/40749130565/);
});

test('click: confirmare pe buton, mesaj si evenimentul cos:adauga', async ({ page, request }) => {
  const id = (await ids(request)).A;
  await page.goto('/adezivi-gresie-si-faianta.html');
  await page.evaluate(() => {
    window.__cos = [];
    document.addEventListener('cos:adauga', (e) => window.__cos.push(e.detail));
  });
  const btn = page.locator(`.btn-cart[data-cart-id="${id}"]`);
  await btn.scrollIntoViewIfNeeded();
  await btn.click();

  await expect(btn).toHaveText('Adaugat in cos ✓');
  await expect(btn).toHaveClass(/is-added/);
  await expect(page.locator('.cart-toast')).toHaveText(`${P.A.name} a fost adaugat in cos.`);
  expect(await page.evaluate(() => window.__cos)).toEqual([{ id, name: P.A.name }]);
  await expect(btn).toHaveText('Adauga in cos', { timeout: 4000 }); // revine la textul initial
});

test('butonul merge si de la tastatura (Enter)', async ({ page, request }, info) => {
  test.skip(info.project.name === 'mobil', 'tastatura doar pe tableta/desktop');
  const id = (await ids(request)).B;
  await page.goto('/adezivi-gresie-si-faianta.html');
  const btn = page.locator(`.btn-cart[data-cart-id="${id}"]`);
  await btn.focus();
  await page.keyboard.press('Enter');
  await expect(btn).toHaveText('Adaugat in cos ✓');
});

test('butonul de pe pagina produsului, din catalog si din colectii', async ({ page }) => {
  await page.goto(P.EL.url);
  const btn = page.locator('.produs-buy .btn-cart');
  await btn.click();
  await expect(btn).toHaveText('Adaugat in cos ✓');
  await expect(page.locator('.hdr-cart-count')).toHaveText('1');

  await page.goto(P.IND.url);
  await expect(page.locator('.produs-buy .btn-cart')).toHaveCount(0);
  await expect(page.locator('.produs-buy .stock')).toHaveText('Indisponibil');

  await page.goto('/catalog.html');
  await expect(page.locator('.cat-card .btn-cart').first()).toBeVisible();
  await page.goto('/fier-forjat.html');
  await expect(page.locator('.cat-card .btn-cart').first()).toBeVisible();
});
