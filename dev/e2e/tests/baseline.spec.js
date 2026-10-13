// Regresie: paginile principale se incarca fara erori JS, fara blocari CSP noi si fara
// resurse proprii lipsa (in afara celor deja cunoscute din jurnal).
const { test, expect } = require('@playwright/test');

const PAGES = ['/', '/electrice.html', '/acoperis.html', '/catalog.html', '/contact.html'];
const THIRD_PARTY = /googletagmanager|google-analytics|clarity\.ms|fonts\.(googleapis|gstatic)/;

// Probleme existente si pe live, documentate in docs/JURNAL-LUCRU.md (13 oct). Nu le ascundem:
// fiecare intrare de aici trebuie sa dispara cand problema e reparata.
const KNOWN = [
  /\/img\/[a-z0-9-]+\.jpg$/,                 // fotografiile de produs lipsa (punctul 7)
  /Refused to frame 'https:\/\/maps\.google/, // harta de pe contact blocata de CSP (punctul 11)
];
const isKnown = (s) => KNOWN.some((re) => re.test(s));

for (const path of PAGES) {
  test(`pagina ${path} fara erori JS/CSP`, async ({ page, baseURL }) => {
    const errors = [];
    page.on('pageerror', (e) => errors.push(`pageerror: ${e.message}`));
    page.on('console', (m) => {
      const t = m.text();
      // Mesajul generic de resursa esuata nu are URL; resursele le verificam mai jos, dupa raspuns.
      if (m.type() === 'error' && !t.startsWith('Failed to load resource') && !isKnown(t)) errors.push(`console: ${t}`);
    });
    page.on('response', (r) => {
      if (r.url().startsWith(baseURL) && r.status() >= 400 && !isKnown(r.url())) errors.push(`HTTP ${r.status()}: ${r.url()}`);
    });
    await page.route(THIRD_PARTY, (r) => r.abort());

    const res = await page.goto(path);
    expect(res.status()).toBe(200);
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).toBeVisible();
    expect(errors, errors.join('\n')).toEqual([]);
  });
}

test('catalogul arata 534 de produse', async ({ page }) => {
  await page.goto('/catalog.html');
  await expect(page.getByText(/din 534 produse/).first()).toBeVisible();
});
