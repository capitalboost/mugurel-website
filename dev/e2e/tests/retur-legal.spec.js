// 16 oct (N10): formularul de retur, cererea in admin, analiza doar dupa acord, sigla ANPC SAL, paginile legale.
const { test, expect } = require('@playwright/test');

const MAILPIT = 'http://127.0.0.1:8025/api/v1';
const ANALYTICS = /googletagmanager\.com|google-analytics\.com|clarity\.ms/;

async function login(page) {
  await page.goto('/admin/?page=login');
  await page.fill('input[name="username"]', 'admin');
  await page.fill('input[name="password"]', 'local-test-2026');
  await page.click('button[type="submit"]');
  await expect(page).not.toHaveURL(/page=login/);
}

test('analiza (Google Analytics, Clarity) porneste doar dupa „Accept"', async ({ page }) => {
  const hits = [];
  page.on('request', (r) => { if (ANALYTICS.test(r.url())) { hits.push(r.url()); } });
  await page.route(ANALYTICS, (r) => r.abort()); // nu trimitem nimic real la Google/Microsoft
  await page.route(/fonts\.(googleapis|gstatic)/, (r) => r.abort());
  await page.goto('/');
  await expect(page.locator('#gdpr-banner')).toBeVisible();
  await page.waitForLoadState('networkidle');
  expect(hits, 'nimic incarcat inainte de acord').toEqual([]);

  await page.click('#gdpr-decline');
  await page.goto('/acoperis.html');
  await page.waitForLoadState('networkidle');
  expect(hits, 'nimic incarcat dupa „Refuz"').toEqual([]);

  await page.evaluate(() => localStorage.removeItem('gdpr_consent'));
  await page.reload();
  await page.click('#gdpr-accept');
  await expect.poll(() => hits.length, { message: 'dupa „Accept" analiza porneste fara reincarcare' }).toBeGreaterThan(0);
});

test('footer: sigla ANPC SAL cu legatura oficiala, email office@, legatura spre cererea de retur', async ({ page }) => {
  await page.route(ANALYTICS, (r) => r.abort());
  await page.goto('/contact.html');
  const sal = page.locator('.f-anpc a');
  await expect(sal).toHaveAttribute('href', 'https://anpc.ro/ce-este-sal/');
  await expect(sal.locator('img')).toBeVisible();
  expect(await sal.locator('img').evaluate((i) => i.naturalWidth)).toBeGreaterThan(0);
  await expect(page.locator('footer.footer')).toContainText('office@mugurel-bricolaj.ro');
  await expect(page.locator('footer.footer a[href="/cerere-retur.html"]')).toHaveCount(1);
  await expect(page.locator('body')).not.toContainText('dunlite@gmail.com');
});

test('paginile legale: texte la zi (comanda online, Dolj, curier pe costul magazinului, formular-tip)', async ({ page }) => {
  await page.route(ANALYTICS, (r) => r.abort());
  await page.goto('/termeni-conditii.html');
  const t = page.locator('.legal-content');
  await expect(t).toContainText('acestei confirmări telefonice');
  await expect(t).toContainText('județul Dolj');
  await expect(t).toContainText('J16/1357/1996');
  await expect(t).not.toContainText('nu sunt afisate pe site');

  await page.goto('/politica-retur.html');
  const r = page.locator('.legal-content');
  await expect(r).toContainText('14 zile');
  await expect(r).toContainText('Costul transportului pentru retur îl suportăm noi');
  await expect(r).toContainText('confecționate la comandă');
  await expect(page.locator('#formular-retragere')).toBeVisible();
  await expect(r).not.toContainText('ambalaj deteriorat sau lipsa');

  await page.goto('/politica-confidentialitate.html');
  const c = page.locator('.legal-content');
  await expect(c).toContainText('doar dacă apeși „Accept”');
  await expect(c).toContainText('6 luni');
  await expect(c).not.toContainText('Prin continuarea navigarii');
});

test('cererea de retur: validare, trimitere, confirmare, 2 emailuri, apoi in admin cu schimbarea starii', async ({ page, request }, info) => {
  test.skip(info.project.name !== 'desktop', 'fluxul complet o singura data');
  await page.route(ANALYTICS, (r) => r.abort());
  const email = `retur-e2e-${Date.now()}@exemplu.ro`;
  await page.goto('/politica-retur.html');
  await page.locator('.legal-cta a').click();
  await expect(page).toHaveURL(/\/cerere-retur\.html$/);

  await page.click('[data-retur-submit]');
  await expect(page.locator('[data-form-errors]')).toBeVisible();
  await expect(page.locator('#err-tip')).toBeVisible();

  await page.check('input[name="tip"][value="retragere"]');
  await page.fill('#f-order_ref', 'MUG-2026-000777');
  await page.fill('#f-produse', 'Adeziv Ceresit CM 11, 25 kg — 2 saci');
  await page.fill('#f-nume', 'Client Retur');
  await page.fill('#f-telefon', '0749 130 565');
  await page.fill('#f-email', email);
  await page.selectOption('#f-judet', 'Dolj');
  await page.fill('#f-localitate', 'Dabuleni');
  await page.fill('#f-adresa', 'Str. Caracal 52');
  await page.check('input[name="acord"]');
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
  expect(overflow).toBeLessThanOrEqual(0);
  await page.click('[data-retur-submit]');

  await expect(page).toHaveURL(/\/cerere-retur\.html\?trimis=RET-\d{4}-\d{6}$/);
  const nr = new URL(page.url()).searchParams.get('trimis');
  await expect(page.locator('[data-retur-trimis]')).toContainText(nr);

  await expect.poll(async () => ((await (await request.get(`${MAILPIT}/search?query=${encodeURIComponent('to:' + email)}`)).json()).messages || []).length).toBe(1);
  const shop = ((await (await request.get(`${MAILPIT}/search?query=${encodeURIComponent('to:lucian@capitalboost.ro')}`)).json()).messages || [])
    .filter((m) => m.Subject.includes(nr));
  expect(shop).toHaveLength(1);

  await login(page);
  await page.goto('/admin/?page=returns&status=noua');
  const card = page.locator('details.adm-return', { hasText: nr });
  await expect(card).toContainText('MUG-2026-000777');
  await card.locator('button', { hasText: 'Finalizata' }).click();
  await expect(page.locator('.flash--success')).toBeVisible();
  await page.goto('/admin/?page=returns&status=finalizata');
  await expect(page.locator('details.adm-return', { hasText: nr })).toHaveCount(1);
});

test('„Setari cookie" din footer: alegerea se poate schimba si retrage oricand', async ({ page }) => {
  const hits = [];
  page.on('request', (r) => { if (ANALYTICS.test(r.url())) { hits.push(r.url()); } });
  await page.route(ANALYTICS, (r) => r.abort());
  await page.route(/fonts\.(googleapis|gstatic)/, (r) => r.abort());
  await page.goto('/contact.html');
  await page.click('#gdpr-decline');
  await expect(page.locator('#gdpr-banner')).toHaveCount(0);

  // redeschid din footer: bannerul arata alegerea curenta; „Accept" porneste analiza fara reincarcare
  const settings = page.locator('footer [data-cookie-settings]');
  // pe telefon/tableta (<= 768 px) coloanele din footer sunt stranse: vizitatorul deschide intai „Informatii"
  if (page.viewportSize().width <= 768) { await page.locator('footer .f-col h4', { hasText: 'Informatii' }).click(); }
  await settings.click();
  await expect(page.locator('#gdpr-banner')).toBeVisible();
  await expect(page.locator('.gdpr-current')).toContainText('refuzi');
  await page.click('#gdpr-accept');
  await expect.poll(() => hits.length).toBeGreaterThan(0);
  expect(await page.evaluate(() => localStorage.getItem('gdpr_consent'))).toBe('accepted');

  // retrag acordul: cookie-urile de analiza se sterg, pagina se reincarca si analiza nu mai porneste
  await page.evaluate(() => { document.cookie = '_ga=GA1.1.test; path=/'; });
  await settings.click();
  await expect(page.locator('.gdpr-current')).toContainText('accepți');
  hits.length = 0;
  await Promise.all([page.waitForEvent('load'), page.click('#gdpr-decline')]);
  await page.waitForLoadState('networkidle');
  expect(await page.evaluate(() => localStorage.getItem('gdpr_consent'))).toBe('declined');
  expect(await page.evaluate(() => document.cookie)).not.toContain('_ga=');
  expect(hits, 'dupa retragere nu se mai incarca nimic').toEqual([]);
});
