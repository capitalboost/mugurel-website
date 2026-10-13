// T10: protectiile formularului vazute din browser. Necesita fixturile din dev/fixtures/cos_test.sql.
const { test, expect } = require('@playwright/test');

const THIRD_PARTY = /googletagmanager|google-analytics|clarity\.ms|fonts\.(googleapis|gstatic)/;

test.beforeEach(async ({ context, page }) => {
  await context.route(THIRD_PARTY, (r) => r.abort());
  await page.goto('/index.html');
  await page.evaluate(() => localStorage.setItem('mugurel_cos', JSON.stringify({ v: 1, items: [{ id: 3470, qty: 1 }] })));
  await page.goto('/comanda.html');
  await expect(page.locator('[data-cos-total]')).toBeVisible();
});

async function fillAll(page) {
  await page.fill('#f-nume', 'Ion Popescu');
  await page.fill('#f-telefon', '0749130565');
  await page.fill('#f-email', 'ion@exemplu.ro');
  await page.check('input[name="plata"][value="ramburs"]');
  await page.selectOption('#f-judet', 'Dolj');
  await page.fill('#f-localitate', 'Dabuleni');
  await page.fill('#f-adresa', 'Str. Caracal 52');
}
async function submit(page) {
  const [res] = await Promise.all([
    page.waitForResponse((r) => r.url().endsWith('/comanda.html') && r.request().method() === 'POST'),
    page.click('[data-comanda-submit]'),
  ]);
  return res.status();
}

test('acordul GDPR: obligatoriu, cu legaturi spre Termeni si Politica', async ({ page }) => {
  const acord = page.locator('.form-acord');
  await expect(acord.getByRole('link', { name: 'Termenii si conditiile' })).toHaveAttribute('href', '/termeni-conditii.html');
  await expect(acord.getByRole('link', { name: 'Politica de confidentialitate' })).toHaveAttribute('href', '/politica-confidentialitate.html');
  await expect(page.locator('input[name="acord"]')).not.toBeChecked(); // nu e bifat din oficiu
  await fillAll(page);
  await page.click('[data-comanda-submit]');
  await expect(page.locator('#err-acord')).toContainText('trebuie sa accepti');
  expect(await page.locator('.form-acord-label + #err-acord').count()).toBe(1); // sub text, nu in interiorul etichetei
  await page.check('input[name="acord"]');
  await expect(page.locator('#err-acord')).toHaveCount(0);
});

test('campul-capcana nu se vede si nu primeste focus', async ({ page }) => {
  const hp = page.locator('input[name="website"]');
  await expect(hp).toHaveCount(1);
  await expect(hp).not.toBeInViewport();
  await expect(hp).toHaveAttribute('tabindex', '-1');
});

test('sesiune expirata: mesaj clar, datele raman, a doua trimitere trece', async ({ page, context }) => {
  await fillAll(page);
  await page.check('input[name="acord"]');
  await context.clearCookies(); // simuleaza sesiunea expirata
  expect(await submit(page)).toBe(400);
  await expect(page.locator('[data-form-notice]')).toContainText('Sesiunea a expirat');
  await expect(page.locator('#f-nume')).toHaveValue('Ion Popescu');
  await expect(page.locator('input[name="plata"][value="ramburs"]')).toBeChecked();
  await expect(page.locator('[data-cos-total]')).toBeVisible();
  expect(await submit(page)).toBe(503); // pagina noua are token valid; inregistrarea reala vine in T11
});

test('cos modificat ocolind interfata: serverul il reverifica si refuza', async ({ page }) => {
  await fillAll(page);
  await page.check('input[name="acord"]');
  await page.evaluate(() => { document.querySelector('[data-cos-field]').value = JSON.stringify([{ id: 3686, qty: 1 }]); });
  expect(await submit(page)).toBe(422);
  await expect(page.locator('[data-cart-error]')).toContainText('nu se mai pot comanda');
  await expect(page.locator('[data-cart-error] a')).toHaveAttribute('href', '/cos.html');
});
