// Produsele de test din catalogul nou, dupa rol — aceleasi ca in dev/fixtures/cos_test.sql (care le fixeaza pretul si
// stocul) si admin/tests/catalog_fixture.php. Id-urile se schimba la reimport, deci se citesc din pagina produsului.
const P = {
  A:   { slug: 'adeziv-gresie-si-faianta-adeplast-afi-c1t-gri-interior-25-kg', name: 'Adeziv gresie și faianță Adeplast AFI, C1T, gri, interior, 25 kg', price: '25,24 lei / sac' },
  B:   { slug: 'adeziv-gresie-si-faianta-ceresit-cm-11-plus-gri-interior-exterior-25-kg', name: 'Adeziv gresie și faianță Ceresit CM 11 Plus, gri, interior / exterior, 25 kg', price: '56,83 lei / sac' },
  KG:  { slug: 'platbanda-zincata-otel-25-x-4-mm', name: 'Platbandă zincată, oțel, 25 x 4 mm', price: '22,43 lei / kg' },
  EL:  { slug: 'aspirator-cu-sac-teesa-eco-white-700-tsa5020-700-w-sac-3-l-filtru-hepa-h12-alb', name: 'Aspirator cu sac Teesa Eco White 700 TSA5020, 700 W, sac 3 l, filtru HEPA H12, alb', price: '373,31 lei / bucată' },
  LA:  { slug: 'adeziv-gresie-si-faianta-ceresit-cm-11-plus-gri-interior-exterior-5-kg', name: 'Adeziv gresie și faianță Ceresit CM 11 Plus, gri, interior / exterior, 5 kg', price: '21,31 lei / sac' },
  IND: { slug: 'adeziv-marmura-si-piatra-naturala-ceresit-cm-11-plus-alb-c1t-alb-interior-exterior-25-kg', name: 'Adeziv marmură și piatră naturală Ceresit CM 11 Plus Alb, C1T, alb, interior / exterior, 25 kg', price: '73,93 lei / sac' },
  NP:  { slug: 'profil-ipe-240-otel-laminat-la-cald', name: 'Profil IPE 240, oțel laminat la cald', price: 'Pret la cerere' },
};
for (const k of Object.keys(P)) { P[k].url = `/produs/${P[k].slug}.html`; }

let cache = null;
/** { A: 10632, B: ..., ... } — o singura citire pe rulare. */
async function ids(request) {
  if (cache) { return cache; }
  const out = {};
  for (const [k, p] of Object.entries(P)) {
    const html = await (await request.get(p.url)).text();
    const m = html.match(/<article class="produs" data-product-id="(\d+)"/);
    if (!m) { throw new Error(`Produsul de test ${k} (${p.slug}) nu exista local — importa catalogul nou si ruleaza fixturile.`); }
    out[k] = Number(m[1]);
  }
  cache = out;
  return out;
}

/** Pune produsul in cos de pe pagina lui (butonul „Adauga in cos"). */
async function addFromProductPage(page, role) {
  await page.goto(P[role].url);
  const btn = page.locator('.produs-buy .btn-cart');
  await btn.click();
  return btn;
}

module.exports = { P, ids, addFromProductPage };
