// T8: comanda pe persoana juridica. Necesita fixturile din dev/fixtures/cos_test.sql.
const { test, expect } = require('@playwright/test');

const THIRD_PARTY = /googletagmanager|google-analytics|clarity\.ms|fonts\.(googleapis|gstatic)/;

test.beforeEach(async ({ context, page }) => {
  await context.route(THIRD_PARTY, (r) => r.abort());
  await page.goto('/index.html');
  await page.evaluate(() => localStorage.setItem('mugurel_cos', JSON.stringify({ v: 1, items: [{ id: 3470, qty: 1 }] })));
  await page.goto('/comanda.html');
  await expect(page.locator('[data-cos-total]')).toBeVisible();
});

const pjRadio = (page) => page.locator('input[name="tip"][value="pj"]');
const pfRadio = (page) => page.locator('input[name="tip"][value="pf"]');

async function fillCommon(page) {
  await page.fill('#f-nume', 'Maria Ionescu');
  await page.fill('#f-telefon', '0749130565');
  await page.fill('#f-email', 'achizitii@firma.ro');
  await page.selectOption('#f-judet', 'Dolj');
  await page.fill('#f-localitate', 'Dabuleni');
  await page.fill('#f-adresa', 'Santier, Str. Noua 7');
  await page.check('input[name="plata"][value="transfer"]');
  await page.check('input[name="acord"]');
}
async function fillCompany(page, cui = 'RO14399840') {
  await page.fill('#f-firma', 'Constructii Dabuleni SRL');
  await page.fill('#f-cui', cui);
  await page.fill('#f-reg_com', 'j16/0123/2020');
  await page.fill('#f-sediu', 'Str. Principala 1, Dabuleni, Dolj');
}

test('implicit persoana fizica: fara campurile de firma', async ({ page }) => {
  await expect(pfRadio(page)).toBeChecked();
  await expect(page.locator('[data-pj-block]')).toBeHidden();
  await expect(page.locator('[data-nume-label]')).toContainText('Nume si prenume');
});

test('comutarea PF ↔ PJ arata campurile de firma si nu pierde datele comune', async ({ page }) => {
  await fillCommon(page);
  await pjRadio(page).check();
  await expect(page.locator('[data-pj-block]')).toBeVisible();
  await expect(page.locator('[data-nume-label]')).toContainText('Persoana de contact');
  await page.fill('#f-firma', 'Firma Test SRL');
  await pfRadio(page).check();
  await expect(page.locator('[data-pj-block]')).toBeHidden();
  await pjRadio(page).check();
  await expect(page.locator('#f-nume')).toHaveValue('Maria Ionescu');
  await expect(page.locator('#f-email')).toHaveValue('achizitii@firma.ro');
  await expect(page.locator('#f-firma')).toHaveValue('Firma Test SRL');
});

test('PJ trimis fara date de firma: erori pe campurile de firma, focus pe primul', async ({ page }) => {
  await fillCommon(page);
  await pjRadio(page).check();
  await page.click('[data-comanda-submit]');
  for (const f of ['firma', 'cui', 'reg_com', 'sediu']) {
    await expect(page.locator(`#err-${f}`)).toBeVisible();
  }
  await expect(page.locator('#f-firma')).toBeFocused();

  await pfRadio(page).check(); // inapoi la PF: erorile de firma dispar
  await expect(page.locator('#err-firma')).toHaveCount(0);
});

test('CUI cu cifra de control gresita: respins de server, formularul ramane pe PJ', async ({ page }) => {
  await fillCommon(page);
  await pjRadio(page).check();
  await fillCompany(page, 'RO14399841');
  await page.click('[data-comanda-submit]');
  await expect(page.locator('#err-cui')).toHaveText('Codul fiscal nu este valid — verifica cifrele (ex. RO14399840).');
  await expect(pjRadio(page)).toBeChecked();
  await expect(page.locator('[data-pj-block]')).toBeVisible();
  await expect(page.locator('#f-firma')).toHaveValue('Constructii Dabuleni SRL');
  await expect(page.locator('[data-nume-label]')).toContainText('Persoana de contact');
});

test('PJ valid: comanda pe firma e salvata', async ({ page }) => {
  await fillCommon(page);
  await pjRadio(page).check();
  await fillCompany(page);
  const [res] = await Promise.all([
    page.waitForResponse((r) => r.url().endsWith('/comanda.html') && r.request().method() === 'POST'),
    page.click('[data-comanda-submit]'),
  ]);
  expect(res.status()).toBe(303); // salvata; normalizarea CUI / RC e verificata in test_order_form.php
  await expect(page).toHaveURL(/comanda-trimisa\.html\?t=[0-9a-f]{48}$/);
});
