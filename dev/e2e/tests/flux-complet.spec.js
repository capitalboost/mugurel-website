// T15 / N9: testare integrata pe catalogul nou. Fluxuri complete din interfata la 375 / 768 / 1280 px, cu capturi la
// fiecare pas, emailurile verificate in Mailpit, parcurgere doar cu tastatura si verificare de accesibilitate (axe-core).
// Necesita fixturile din dev/fixtures/cos_test.sql si Mailpit pornit.
const { test, expect } = require('@playwright/test');
const AxeBuilder = require('@axe-core/playwright').default;
const path = require('path');
const { P, ids } = require('./fixtures');

const THIRD_PARTY = /googletagmanager|google-analytics|clarity\.ms|fonts\.(googleapis|gstatic)/;
const MAILPIT = 'http://127.0.0.1:8025/api/v1';
const CAPTURI = path.join(__dirname, '..', 'capturi');
let I;

test.beforeEach(async ({ context, request }) => {
  await context.route(THIRD_PARTY, (r) => r.abort());
  await context.addInitScript(() => { try { localStorage.setItem('gdpr_consent', 'declined'); } catch (e) {} });
  I = await ids(request);
});

const shot = (page, info, name) =>
  page.screenshot({ path: path.join(CAPTURI, `${info.project.name}-${name}.png`), fullPage: true });

async function mailsTo(request, address) {
  const res = await request.get(`${MAILPIT}/search?query=${encodeURIComponent('to:' + address)}`);
  return (await res.json()).messages || [];
}

// Produsele se pun in cos din locuri diferite: pagina produsului, lista categoriei, colectie.
const SCENARII = [
  { nume: 'pf-ramburs', tip: 'pf', plata: 'ramburs', email: 'flux-pf@exemplu.ro',
    produse: [['A', '/adezivi-gresie-si-faianta.html'], ['KG', P.KG.url]], total: '47,67 lei' },        // 25,24 + 22,43
  { nume: 'pj-transfer', tip: 'pj', plata: 'transfer', email: 'flux-pj@exemplu.ro',
    produse: [['B', P.B.url], ['EL', '/aspiratoare.html'], ['LA', '/adezivi-gresie-si-faianta.html']], total: '451,45 lei' }, // 56,83 + 373,31 + 21,31
  { nume: 'pf-ridicare', tip: 'pf', plata: 'ridicare', email: 'flux-rid@exemplu.ro',
    produse: [['EL', P.EL.url]], total: '373,31 lei' },
];

for (const s of SCENARII) {
  test(`flux complet ${s.nume}: produse → cos → comanda → confirmare + 2 emailuri`, async ({ page, request }, info) => {
    const email = `${info.project.name}-${s.email}`; // adresa unica per latime: fara „dubluri" intre proiecte
    // emailurile ramase de la o rulare anterioara pentru aceeasi adresa (testul nu depinde de ordinea suitei)
    await request.delete(`${MAILPIT}/search?query=${encodeURIComponent('to:' + email)}`);
    for (const [rol, url] of s.produse) {
      await page.goto(url);
      const btn = page.locator(`.btn-cart[data-cart-id="${I[rol]}"]`).first();
      await btn.scrollIntoViewIfNeeded();
      await btn.click();
      await expect(btn).toHaveText('Adaugat in cos ✓');
    }
    await shot(page, info, `${s.nume}-1-produs-adaugat`);
    await expect(page.locator('.hdr-cart-count')).toHaveText(String(s.produse.length));

    await page.locator('.hdr-cart').click();
    await expect(page.locator('[data-cos-total]')).toHaveText(s.total);
    await shot(page, info, `${s.nume}-2-cos`);

    await page.locator('a.cos-continue').click();
    await expect(page.locator('[data-cos-total]')).toHaveText(s.total);
    if (s.tip === 'pj') {
      await page.check('input[name="tip"][value="pj"]');
      await page.fill('#f-firma', 'Constructii Test SRL');
      await page.fill('#f-cui', 'RO14399840');
      await page.fill('#f-reg_com', 'J16/123/2020');
      await page.fill('#f-sediu', 'Str. Principala 1, Dabuleni, Dolj');
    }
    await page.fill('#f-nume', 'Client Flux');
    await page.fill('#f-telefon', '0749 130 565');
    await page.fill('#f-email', email);
    await page.check(`input[name="plata"][value="${s.plata}"]`);
    if (s.plata !== 'ridicare') {
      await page.selectOption('#f-judet', 'Dolj');
      await page.fill('#f-localitate', 'Dabuleni');
      await page.fill('#f-adresa', 'Str. Caracal 52');
    }
    await page.check('input[name="acord"]');
    await shot(page, info, `${s.nume}-3-formular`);
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
    expect(overflow, 'fara scroll orizontal pe formular').toBeLessThanOrEqual(0);

    await page.click('[data-comanda-submit]');
    await expect(page).toHaveURL(/\/comanda-trimisa\.html\?t=[0-9a-f]{48}$/);
    await expect(page.locator('[data-order-total]')).toHaveText(s.total);
    await page.waitForLoadState('load');
    await expect(page.locator('.hdr-cart-count')).toBeHidden();
    await shot(page, info, `${s.nume}-4-confirmare`);
    const nr = (await page.locator('[data-order-number]').textContent()).trim();

    await expect.poll(async () => (await mailsTo(request, email)).length, { message: 'confirmarea clientului in Mailpit' }).toBe(1);
    const client = (await mailsTo(request, email))[0];
    expect(client.Subject).toBe(`Am primit comanda ta ${nr} — Mugurel`);
    const intern = (await mailsTo(request, 'lucian@capitalboost.ro')).filter((m) => m.Subject.includes(nr));
    expect(intern, 'o singura notificare interna pentru comanda').toHaveLength(1);
    expect(intern[0].Subject).toContain(s.total);
  });
}

test('parcurgere doar cu tastatura: adaug in cos, deschid cosul, ajung la formular', async ({ page }, info) => {
  test.skip(info.project.name === 'mobil', 'tastatura fizica doar pe tableta/desktop');
  await page.goto('/adezivi-gresie-si-faianta.html');
  const btn = page.locator(`.btn-cart[data-cart-id="${I.A}"]`);
  await btn.focus();
  await page.keyboard.press('Enter');
  await expect(page.locator('.hdr-cart-count')).toHaveText('1');
  await page.locator('.hdr-cart').focus();
  await page.keyboard.press('Enter');
  await expect(page).toHaveURL(/\/cos\.html$/);
  await expect(page.locator('a.cos-continue')).toBeVisible();
  await page.locator('a.cos-continue').focus();
  await page.keyboard.press('Enter');
  await expect(page).toHaveURL(/\/comanda\.html$/);
  // tab prin formular: fiecare camp primeste focus in ordinea vizuala, capcana anti-robot nu
  const order = [];
  await page.locator('input[name="tip"][value="pf"]').focus();
  for (let i = 0; i < 12; i++) {
    await page.keyboard.press('Tab');
    order.push(await page.evaluate(() => document.activeElement && (document.activeElement.name || document.activeElement.id)));
  }
  expect(order).not.toContain('website');
  expect(order.indexOf('nume')).toBeGreaterThan(-1);
  expect(order.indexOf('telefon')).toBeGreaterThan(order.indexOf('nume'));
  expect(order.indexOf('email')).toBeGreaterThan(order.indexOf('telefon'));
});

const AXE_PAGES = [
  ['/cos.html', (I) => [{ id: I.A, qty: 2 }, { id: I.IND, qty: 1 }]],
  ['/comanda.html', (I) => [{ id: I.A, qty: 1 }]],
  [P.A.url, null],
  ['/tevi-patrate-si-rectangulare.html', null],
  ['/', null],
];
for (const [pagina, items] of AXE_PAGES) {
  test(`accesibilitate (axe): ${pagina} fara probleme grave sau critice`, async ({ page }, info) => {
    test.skip(info.project.name !== 'desktop', 'o singura data');
    if (items) {
      await page.goto('/index.html');
      await page.evaluate(([its]) => localStorage.setItem('mugurel_cos', JSON.stringify({ v: 1, items: its })), [items(I)]);
    }
    await page.goto(pagina);
    if (items) { await page.waitForSelector('[data-cos-total]'); }
    if (pagina === '/comanda.html') { await page.click('[data-comanda-submit]'); await page.waitForSelector('.form-error'); }
    // Exceptii acceptate (decizia lui Lucian, 16 oct: butoanele WhatsApp raman in culorile actuale): textul alb pe verdele #25D366
    // are contrast 1,98:1 (minim 4,5:1) pe toate butoanele WhatsApp ale site-ului, si pe live; butonul secundar din hero
    // e semnalat fals (axe nu vede fundalul inchis al hero-ului).
    const r = await new AxeBuilder({ page }).include('main').exclude('.btn-wa-hero').exclude('.btn-wa-prod').exclude('.btn-catalog-hero').analyze();
    const grave = r.violations.filter((v) => ['serious', 'critical'].includes(v.impact));
    expect(grave.map((v) => `${v.id}: ${v.help} (${v.nodes.length})`)).toEqual([]);
  });
}
