// T4: butonul „Adauga in cos". Necesita fixturile locale din dev/fixtures/cos_test.sql.
const { test, expect } = require('@playwright/test');

const THIRD_PARTY = /googletagmanager|google-analytics|clarity\.ms|fonts\.(googleapis|gstatic)/;

test.beforeEach(async ({ page }) => {
  await page.route(THIRD_PARTY, (r) => r.abort());
});

test('toate produsele disponibile au buton de cos (preturile sunt fixe)', async ({ page }) => {
  await page.goto('/acoperis.html');
  const cards = await page.locator('.material-card, .accessory-card').count();
  await expect(page.locator('.btn-cart')).toHaveCount(cards); // pe acoperis nu e niciun produs indisponibil
  await expect(page.locator('.material-card', { hasText: 'Tabla Cutata (Ondulata)' }).locator('.btn-cart')).toHaveCount(1);
  await expect(page.locator('.material-price, .accessory-price').filter({ hasText: /^de la/ })).toHaveCount(0); // doar preturile, nu textele editoriale

  await page.goto('/electrice.html');
  const indisponibil = page.locator('.accessory-card', { hasText: 'Doza electrica ingropata 60mm' });
  await expect(indisponibil.locator('.stock')).toHaveText('Indisponibil');
  await expect(indisponibil.locator('.btn-cart')).toHaveCount(0);
  const laComanda = page.locator('.accessory-card', { hasText: 'Spot LED incastrat tavan' });
  await expect(laComanda.locator('.stock')).toHaveText('La comanda');
  await expect(laComanda.locator('.btn-cart')).toHaveCount(1);
});

test('click: confirmare pe buton, mesaj si evenimentul cos:adauga', async ({ page }) => {
  await page.goto('/acoperis.html');
  await page.evaluate(() => {
    window.__cos = [];
    document.addEventListener('cos:adauga', (e) => window.__cos.push(e.detail));
  });
  const btn = page.locator('.btn-cart[data-cart-id="3470"]');
  await btn.scrollIntoViewIfNeeded();
  await btn.click();

  await expect(btn).toHaveText('Adaugat in cos ✓');
  await expect(btn).toHaveClass(/is-added/);
  await expect(page.locator('.cart-toast')).toHaveText('Jgheab Semicircular Ø125mm a fost adaugat in cos.');
  expect(await page.evaluate(() => window.__cos)).toEqual([{ id: 3470, name: 'Jgheab Semicircular Ø125mm' }]);

  await expect(btn).toHaveText('Adauga in cos', { timeout: 4000 }); // revine la textul initial
});

test('butonul merge si de la tastatura (Enter)', async ({ page }, info) => {
  test.skip(info.project.name === 'mobil', 'tastatura doar pe tableta/desktop');
  await page.goto('/acoperis.html');
  const btn = page.locator('.btn-cart[data-cart-id="3471"]');
  await btn.focus();
  await page.keyboard.press('Enter');
  await expect(btn).toHaveText('Adaugat in cos ✓');
});

test('butonul apare si in catalog si pe paginile de subcategorie (router.php)', async ({ page }) => {
  await page.goto('/catalog.html');
  await expect(page.locator('.prod-card .btn-cart').first()).toBeVisible();

  await page.goto('/bucatarie.html'); // servita de router.php + templates/category.php
  const btn = page.locator('.btn-cart[data-cart-id="10482"]');
  await expect(btn).toBeVisible();
  await btn.click();
  await expect(btn).toHaveText('Adaugat in cos ✓');
});
