// Regresie: paginile principale se incarca fara erori JS, fara blocari CSP noi si fara
// resurse proprii lipsa (in afara celor deja cunoscute din jurnal).
const { test, expect } = require('@playwright/test');
const { P } = require('./fixtures');

const PAGES = ['/', '/acoperis.html', '/tevi-patrate-si-rectangulare.html?grosime=grosime-2-mm', '/electrocasnice.html',
  '/aspiratoare.html', '/fier-forjat.html', P.A.url, P.EL.url, '/catalog.html', '/catalog.html?q=ceresit', '/contact.html',
  '/despre-noi.html', '/cos.html', '/termeni-conditii.html', '/politica-retur.html', '/politica-confidentialitate.html',
  '/cerere-retur.html'];
const THIRD_PARTY = /googletagmanager|google-analytics|clarity\.ms|fonts\.(googleapis|gstatic)/;

// Probleme existente si pe live, documentate in docs/JURNAL-LUCRU.md (13 oct). Nu le ascundem:
// fiecare intrare de aici trebuie sa dispara cand problema e reparata.
const KNOWN = [
  /Refused to frame 'https:\/\/maps\.google/, // harta de pe contact blocata de CSP (punctul 11)
];
const isKnown = (s) => KNOWN.some((re) => re.test(s));

for (const path of PAGES) {
  test(`pagina ${path} fara erori JS/CSP, fara scroll orizontal`, async ({ page, baseURL }) => {
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
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
    expect(overflow, 'fara scroll orizontal').toBeLessThanOrEqual(0);
  });
}

test('catalogul arata toate produsele vizibile', async ({ page }) => {
  await page.goto('/catalog.html');
  await expect(page.locator('.filter-bar .results strong')).toHaveText(/^\d{3,}$/);
});
