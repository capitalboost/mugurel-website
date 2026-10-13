// T15: testare integrata. Fluxuri complete din interfata la 375 / 768 / 1280 px, cu capturi la fiecare pas,
// emailurile verificate in Mailpit, parcurgere doar cu tastatura si verificare de accesibilitate (axe-core).
// Necesita fixturile din dev/fixtures/cos_test.sql si Mailpit pornit.
const { test, expect } = require('@playwright/test');
const AxeBuilder = require('@axe-core/playwright').default;
const path = require('path');

const THIRD_PARTY = /googletagmanager|google-analytics|clarity\.ms|fonts\.(googleapis|gstatic)/;
const MAILPIT = 'http://127.0.0.1:8025/api/v1';
const CAPTURI = path.join(__dirname, '..', 'capturi');

test.beforeEach(async ({ context }) => {
  await context.route(THIRD_PARTY, (r) => r.abort());
  await context.addInitScript(() => { try { localStorage.setItem('gdpr_consent', 'declined'); } catch (e) {} });
});

const shot = (page, info, name) =>
  page.screenshot({ path: path.join(CAPTURI, `${info.project.name}-${name}.png`), fullPage: true });

async function mailsTo(request, address) {
  const res = await request.get(`${MAILPIT}/search?query=${encodeURIComponent('to:' + address)}`);
  return (await res.json()).messages || [];
}

const SCENARII = [
  { nume: 'pf-ramburs', tip: 'pf', plata: 'ramburs', email: 'flux-pf@exemplu.ro', produse: [3470, 3674], total: '97,49 lei' },
  { nume: 'pj-transfer', tip: 'pj', plata: 'transfer', email: 'flux-pj@exemplu.ro', produse: [3471, 3678, 3682], total: '134,00 lei' },
  { nume: 'pf-ridicare', tip: 'pf', plata: 'ridicare', email: 'flux-rid@exemplu.ro', produse: [10482], total: '180,00 lei' },
];
const PAGINA = { 3470: '/acoperis.html', 3471: '/acoperis.html', 3674: '/electrice.html', 3678: '/electrice.html',
                 3682: '/electrice.html', 10482: '/bucatarie.html' };

for (const s of SCENARII) {
  test(`flux complet ${s.nume}: produse → cos → comanda → confirmare + 2 emailuri`, async ({ page, request }, info) => {
    const email = `${info.project.name}-${s.email}`; // adresa unica per latime: fara „dubluri" intre proiecte
    for (const id of s.produse) {
      await page.goto(PAGINA[id]);
      const btn = page.locator(`.btn-cart[data-cart-id="${id}"]`);
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
  await page.goto('/acoperis.html');
  const btn = page.locator('.btn-cart[data-cart-id="3470"]');
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

for (const [pagina, pregatire] of [
  ['/cos.html', async (page) => { await page.goto('/index.html'); await page.evaluate(() => localStorage.setItem('mugurel_cos', JSON.stringify({ v: 1, items: [{ id: 3470, qty: 2 }, { id: 3686, qty: 1 }] }))); }],
  ['/comanda.html', async (page) => { await page.goto('/index.html'); await page.evaluate(() => localStorage.setItem('mugurel_cos', JSON.stringify({ v: 1, items: [{ id: 3470, qty: 1 }] }))); }],
]) {
  test(`accesibilitate (axe): ${pagina} fara probleme grave sau critice`, async ({ page }, info) => {
    test.skip(info.project.name !== 'desktop', 'o singura data');
    await pregatire(page);
    await page.goto(pagina);
    await page.waitForSelector('[data-cos-total]');
    if (pagina === '/comanda.html') { await page.click('[data-comanda-submit]'); await page.waitForSelector('.form-error'); }
    const r = await new AxeBuilder({ page }).include('main').analyze();
    const grave = r.violations.filter((v) => ['serious', 'critical'].includes(v.impact));
    expect(grave.map((v) => `${v.id}: ${v.help} (${v.nodes.length})`)).toEqual([]);
  });
}
