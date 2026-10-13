// T2: bifa „Pret minim" din admin ajunge pe site. Restaureaza starea initiala la final.
const { test, expect } = require('@playwright/test');

test.beforeEach(({}, info) => test.skip(info.project.name !== 'desktop', 'adminul se testeaza doar pe desktop'));

const EDIT = '/admin/?page=products&action=edit&id=1'; // Tabla Cutata (Ondulata), pagina acoperis

async function login(page) {
  await page.goto('/admin/?page=login');
  await page.fill('input[name="username"]', 'admin');
  await page.fill('input[name="password"]', 'local-test-2026');
  await page.click('button[type="submit"], input[type="submit"]');
  await expect(page).not.toHaveURL(/page=login/);
}

async function setFrom(page, checked) {
  await page.goto(EDIT);
  const box = page.locator('input[name="price_is_from"]');
  await expect(box).toBeVisible();
  if ((await box.isChecked()) !== checked) await box.click();
  await page.click('button[type="submit"]');
  await expect(page).not.toHaveURL(/action=edit/);
}

const cardPrice = (page) =>
  page.locator('.material-card', { hasText: 'Tabla Cutata (Ondulata)' }).locator('.material-price');

test('bifa „Pret minim" controleaza eticheta „de la" pe site', async ({ page }) => {
  await login(page);
  await page.goto(EDIT);
  const initial = await page.locator('input[name="price_is_from"]').isChecked();
  try {
    await setFrom(page, false);
    await page.goto(EDIT);
    await expect(page.locator('input[name="price_is_from"]')).not.toBeChecked();
    await page.goto('/acoperis.html');
    await expect(cardPrice(page)).toHaveText(/^55,00 lei \/ m²$/);

    await setFrom(page, true);
    await page.goto('/acoperis.html');
    await expect(cardPrice(page)).toHaveText(/^de la 55,00 lei \/ m²$/);
  } finally {
    await setFrom(page, initial);
  }
});
