/*
 * Filtrele si ordonarea din listele catalogului (templates/catalog_list.php). Fara JS totul merge prin butoanele
 * „Aplica"; cu JS: bifarea unui filtru sau schimbarea ordinii reincarca lista imediat, filtrele lungi se strang
 * („Arata toate"), iar pe telefon panoul de filtre porneste inchis, ca produsele sa se vada primele.
 */
(function () {
  'use strict';

  var form = document.querySelector('[data-cat-filter-form]');
  if (form) {
    form.addEventListener('change', function (ev) {
      if (ev.target && ev.target.type === 'checkbox') { submitFilters(form); }
    });
    var apply = form.querySelector('.cat-filters-apply');
    if (apply) { apply.hidden = true; }

    Array.prototype.forEach.call(form.querySelectorAll('[data-facet-more]'), function (btn) {
      var hidden = btn.parentNode.querySelectorAll('.cat-facet-opt--more');
      if (!hidden.length) { return; }
      Array.prototype.forEach.call(hidden, function (el) { el.hidden = true; });
      btn.hidden = false;
      btn.addEventListener('click', function () {
        Array.prototype.forEach.call(hidden, function (el) { el.hidden = false; });
        btn.remove();
      });
    });
  }

  // Adresa scurta si lizibila: ?grosime=grosime-2-mm,grosime-3-mm in loc de grosime[]=...&grosime[]=...
  function submitFilters(f) {
    var groups = {}, order = [];
    Array.prototype.forEach.call(f.elements, function (el) {
      if (!el.name || el.disabled) { return; }
      if (el.type === 'checkbox' && !el.checked) { return; }
      var name = el.name.replace(/\[\]$/, '');
      if (!groups[name]) { groups[name] = []; order.push(name); }
      groups[name].push(el.value);
    });
    var qs = order.map(function (n) {
      return encodeURIComponent(n) + '=' + groups[n].map(encodeURIComponent).join(',');
    }).join('&');
    window.location.href = f.getAttribute('action') + (qs ? '?' + qs : '');
  }

  var sort = document.querySelector('[data-cat-sort-form]');
  if (sort) {
    sort.addEventListener('change', function () { sort.submit(); });
  }

  var panel = document.querySelector('[data-cat-filters]');
  if (panel && window.matchMedia && window.matchMedia('(max-width: 768px)').matches) {
    panel.open = false;
  }
})();
