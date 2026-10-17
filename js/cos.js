/*
 * Cosul de cumparaturi — partea din browser.
 *
 * Butoanele „Adauga in cos" sunt randate de PHP (includes/product_grid.php, cartButton())
 * doar pentru produsele cu pret fix si disponibile. Aici:
 *   - pastram cosul in localStorage: DOAR {id, qty}. Numele, pretul si stocul se recitesc
 *     din baza pe server la fiecare pas (T6) — nimic din browser nu e de incredere;
 *   - click pe [data-cart-id] => adauga in cos, confirma vizual, anunta „cos:adauga";
 *   - legatura „Cos" cu numarul de produse, inserata in header pe toate paginile
 *     (cosul exista doar cu JavaScript, deci si legatura).
 *
 * window.MugurelCos e folosit de pagina cosului (cos.php).
 * Fisier separat de main.js, incarcat cu ?v= ca sa nu ramana vechi in cache-ul Cloudflare.
 */
(function () {
  'use strict';

  var KEY = 'mugurel_cos';
  var MAX_LINES = 100;
  var MAX_QTY = 99999;

  /** Cantitate valida: numar > 0, maximum 2 zecimale (m², ml), plafonat. Altfel null. */
  function cleanQty(q) {
    var n = typeof q === 'string' ? parseFloat(q.replace(',', '.')) : Number(q);
    if (!isFinite(n) || n <= 0) { return null; }
    return Math.min(Math.round(n * 100) / 100, MAX_QTY);
  }

  /** Citeste cosul. Orice forma neasteptata (date vechi, modificate de mana, corupte) e curatata. */
  function read() {
    var raw;
    try { raw = window.localStorage.getItem(KEY); } catch (e) { return []; }
    if (!raw) { return []; }
    var data;
    try { data = JSON.parse(raw); } catch (e) { return []; }
    var items = data && Array.isArray(data.items) ? data.items : [];
    var seen = {};
    var out = [];
    items.forEach(function (it) {
      var id = it && Number(it.id);
      var qty = it && cleanQty(it.qty);
      if (!(id > 0) || Math.floor(id) !== id || qty === null || seen[id] || out.length >= MAX_LINES) { return; }
      seen[id] = true;
      out.push({ id: id, qty: qty });
    });
    return out;
  }

  /** Scrie cosul. Intoarce false daca browserul nu permite salvarea (mod privat, stocare blocata). */
  function write(items) {
    try {
      window.localStorage.setItem(KEY, JSON.stringify({ v: 1, items: items }));
    } catch (e) {
      return false;
    }
    renderCount();
    return true;
  }

  function add(id, qty) {
    var items = read();
    var step = cleanQty(qty === undefined ? 1 : qty);
    if (!(id > 0) || step === null) { return false; }
    var line = items.filter(function (it) { return it.id === id; })[0];
    if (line) {
      line.qty = Math.min(cleanQty(line.qty + step), MAX_QTY);
    } else {
      if (items.length >= MAX_LINES) { return false; }
      items.push({ id: id, qty: step });
    }
    return write(items);
  }

  function setQty(id, qty) {
    var q = cleanQty(qty);
    if (q === null) { return remove(id); }
    return write(read().map(function (it) { return it.id === id ? { id: id, qty: q } : it; }));
  }

  function remove(id) {
    return write(read().filter(function (it) { return it.id !== id; }));
  }

  function clear() {
    return write([]);
  }

  /** Numarul de produse distincte (nu suma cantitatilor: 120 m² de tabla e un singur produs). */
  function count() {
    return read().length;
  }

  // ── Legatura „Cos" din header ───────────────────────────────────────────

  var CART_ICON = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>';

  function headerLink() {
    var link = document.querySelector('.hdr-cart');
    if (link) { return link; }
    var right = document.querySelector('.hdr-right');
    if (!right) { return null; }
    link = document.createElement('a');
    link.href = '/cos.html';
    link.className = 'hdr-cart';
    link.innerHTML = CART_ICON + '<span class="hdr-cart-label">Coș</span><span class="hdr-cart-count" hidden></span>';
    var hamburger = right.querySelector('.hamburger');
    right.insertBefore(link, hamburger || null);
    return link;
  }

  function renderCount() {
    var link = headerLink();
    if (!link) { return; }
    var n = count();
    var badge = link.querySelector('.hdr-cart-count');
    badge.textContent = n > 99 ? '99+' : String(n);
    badge.hidden = n === 0;
    link.setAttribute('aria-label', 'Coș de cumpărături: ' + (n === 1 ? '1 produs' : n + ' produse'));
  }

  // ── Mesaj de confirmare ─────────────────────────────────────────────────

  var toastTimer = null;

  function toast(text, isError) {
    var el = document.querySelector('.cart-toast');
    if (!el) {
      el = document.createElement('div');
      el.className = 'cart-toast';
      el.setAttribute('role', 'status');
      el.setAttribute('aria-live', 'polite');
      document.body.appendChild(el);
    }
    el.textContent = text;
    el.classList.toggle('cart-toast--error', !!isError);
    el.hidden = false;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () { el.hidden = true; }, isError ? 5000 : 2500);
  }

  function confirmButton(btn) {
    if (!btn.dataset.label) { btn.dataset.label = btn.textContent; }
    btn.textContent = 'Adăugat în coș ✓';
    btn.classList.add('is-added');
    clearTimeout(btn._cartTimer);
    btn._cartTimer = setTimeout(function () {
      btn.textContent = btn.dataset.label;
      btn.classList.remove('is-added');
    }, 1800);
  }

  document.addEventListener('click', function (ev) {
    var btn = ev.target.closest && ev.target.closest('[data-cart-id]');
    if (!btn) { return; }
    ev.preventDefault();

    var id = parseInt(btn.getAttribute('data-cart-id'), 10);
    if (!(id > 0)) { return; }
    var name = btn.getAttribute('data-cart-name') || 'Produsul';

    if (!add(id, 1)) {
      toast('Coșul nu poate fi salvat în acest browser. Comandă pe WhatsApp sau la 0749 130 565.', true);
      return;
    }
    document.dispatchEvent(new CustomEvent('cos:adauga', { detail: { id: id, name: name } }));
    confirmButton(btn);
    toast(name + ' a fost adăugat în coș.');
  });

  // Alt tab a modificat cosul => actualizam contorul.
  window.addEventListener('storage', function (ev) {
    if (ev.key === KEY || ev.key === null) { renderCount(); }
  });

  window.MugurelCos = { read: read, add: add, setQty: setQty, remove: remove, clear: clear, count: count, cleanQty: cleanQty };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', renderCount);
  } else {
    renderCount();
  }
})();
