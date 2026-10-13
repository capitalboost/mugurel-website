/*
 * Cosul de cumparaturi — partea din browser.
 *
 * Butoanele „Adauga in cos" sunt randate de PHP (includes/product_grid.php, cartButton())
 * doar pentru produsele cu pret fix si in stoc. Aici:
 *   - click pe [data-cart-id] => anunta adaugarea (evenimentul „cos:adauga") si confirma vizual.
 *
 * Fisier separat de main.js, incarcat cu ?v= ca sa nu ramana vechi in cache-ul Cloudflare.
 */
(function () {
  'use strict';

  var toastTimer = null;

  function toast(text) {
    var el = document.querySelector('.cart-toast');
    if (!el) {
      el = document.createElement('div');
      el.className = 'cart-toast';
      el.setAttribute('role', 'status');
      el.setAttribute('aria-live', 'polite');
      document.body.appendChild(el);
    }
    el.textContent = text;
    el.hidden = false;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () { el.hidden = true; }, 2500);
  }

  function confirmButton(btn) {
    if (!btn.dataset.label) { btn.dataset.label = btn.textContent; }
    btn.textContent = 'Adaugat in cos ✓';
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

    document.dispatchEvent(new CustomEvent('cos:adauga', { detail: { id: id, name: name } }));
    confirmButton(btn);
    toast(name + ' a fost adaugat in cos.');
  });
})();
