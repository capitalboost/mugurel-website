// T3: starea de stoc setata din admin apare pe site; o valoare falsificata e respinsa.
// Restaureaza starea initiala la final.
const { test, expect } = require('@playwright/test');

test.beforeEach(({}, info) => test.skip(info.project.name !== 'desktop', 'adminul se testeaza doar pe desktop'));

const EDIT = '/admin/?page=products&action=edit&id=1'; // Tabla Cutata (Ondulata), pagina acoperis
const card = (page) => page.locator('.material-card', { hasText: 'Tabla Cutata (Ondulata)' });

async function login(page) {
  await page.goto('/admin/?page=login');
  await page.fill('input[name="username"]', 'admin');
  await page.fill('input[name="password"]', 'local-test-2026');
  await page.click('button[type="submit"]');
  await expect(page).not.toHaveURL(/page=login/);
}

async function setStock(page, value) {
  await page.goto(EDIT);
  await page.selectOption('select[name="stock_status"]', value);
  await page.click('button[type="submit"]');
  await expect(page).not.toHaveURL(/action=edit/);
}

test('starea de stoc din admin ajunge pe pagina produsului', async ({ page }) => {
  await login(page);
  await page.goto(EDIT);
  const initial = await page.locator('select[name="stock_status"]').inputValue();
  expect(initial).toBe('in_stoc');
  try {
    await page.goto('/acoperis.html');
    await expect(card(page).locator('.stock')).toHaveText('In stoc');

    await setStock(page, 'la_comanda');
    await page.goto('/acoperis.html');
    await expect(card(page).locator('.stock')).toHaveText('La comanda');
    await expect(card(page).locator('.stock')).toHaveClass(/stock--la_comanda/);

    await setStock(page, 'indisponibil');
    await page.goto('/acoperis.html');
    await expect(card(page).locator('.stock')).toHaveText('Indisponibil');
  } finally {
    await setStock(page, initial);
  }
});

test('pretul e mereu fix: adminul nu mai are bifa „Pret minim", iar salvarea pastreaza pretul fix', async ({ page }) => {
  await login(page);
  await page.goto(EDIT);
  await expect(page.locator('input[name="price_is_from"]')).toHaveCount(0);
  await page.click('button[type="submit"]');
  await expect(page).not.toHaveURL(/action=edit/);
  await page.goto('/acoperis.html');
  await expect(card(page).locator('.material-price')).toHaveText(/^55,00 lei \/ m²$/);
  await expect(card(page).locator('.btn-cart')).toHaveCount(1);
});

test('o valoare de stoc falsificata in formular e respinsa', async ({ page }) => {
  await login(page);
  await page.goto(EDIT);
  // Simuleaza un POST modificat: o optiune care nu exista in lista.
  await page.evaluate(() => {
    const sel = document.querySelector('select[name="stock_status"]');
    sel.add(new Option('hack', 'hack'));
    sel.value = 'hack';
  });
  await page.click('button[type="submit"]');
  await expect(page.getByText('Starea de stoc nu e valida.')).toBeVisible();

  await page.goto('/acoperis.html');
  await expect(card(page).locator('.stock')).toHaveText('In stoc');
});
