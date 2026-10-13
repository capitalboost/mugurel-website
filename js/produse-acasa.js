/*
 * Prima pagina (index.html, HTML static): cardurile „Produse reprezentative" au data-product-id.
 * La incarcare le completam din baza — pret, stoc si butonul „Adauga in cos" — prin acelasi
 * endpoint ca pagina cosului (/cos-produse.php). Asa raman la zi cand Mugurel schimba un pret.
 * Daca cererea esueaza, cardurile raman cum sunt in HTML (cu butonul WhatsApp, care merge oricum).
 * Clicul pe buton e tratat de js/cos.js (delegare pe [data-cart-id]).
 */
(function () {
  'use strict';

  var cards = Array.prototype.slice.call(document.querySelectorAll('.prod-card[data-product-id]'));
  if (!cards.length) { return; }

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  var items = cards.map(function (c) { return { id: parseInt(c.getAttribute('data-product-id'), 10), qty: 1 }; });

  fetch('/cos-produse.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ items: items }),
    credentials: 'same-origin',
  }).then(function (res) {
    if (!res.ok) { throw new Error('HTTP ' + res.status); }
    return res.json();
  }).then(function (cart) {
    var byId = {};
    cart.lines.forEach(function (l) { byId[l.id] = l; });

    cards.forEach(function (card) {
      var l = byId[parseInt(card.getAttribute('data-product-id'), 10)];
      if (!l || !l.name) { return; } // produsul nu mai exista pe site: lasam cardul static

      var body = card.querySelector('.prod-body');
      var footer = card.querySelector('.prod-footer');
      var price = card.querySelector('.prod-price');

      if (l.stock_label && !card.querySelector('.stock')) {
        footer.insertAdjacentHTML('beforebegin',
          '<span class="stock stock--' + esc(l.stock) + '">' + esc(l.stock_label) + '</span>');
      }
      if (l.orderable) {
        price.textContent = l.price_label + (l.unit ? ' / ' + l.unit : '');
        if (!card.querySelector('.btn-cart')) {
          body.insertAdjacentHTML('beforeend',
            '<button type="button" class="btn-cart" data-cart-id="' + esc(l.id) + '" data-cart-name="' + esc(l.name) + '">Adauga in cos</button>');
        }
      }
      card.setAttribute('data-hydrated', '1');
    });
  }).catch(function () { /* cardurile raman statice */ });
})();
