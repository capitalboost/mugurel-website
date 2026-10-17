/*
 * Pagina cosului (/cos.html). Depinde de js/cos.js (window.MugurelCos), incarcat inainte.
 *
 * Flux: citeste {id, qty} din localStorage → POST /cos-produse.php → afiseaza raspunsul serverului.
 * Pretul, numele, stocul si totalurile vin DOAR de la server; la orice schimbare de cantitate
 * cosul se recalculeaza tot acolo.
 */
(function () {
  'use strict';

  var app = document.getElementById('cos-app');
  if (!app || !window.MugurelCos) { return; }
  var Cos = window.MugurelCos;

  var PHONE = '0749 130 565';
  var requestSeq = 0;
  var refreshTimer = null;

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  /** Cantitatea afisata in format romanesc: 2,5 (nu 2.5). */
  function fmtQty(q) {
    return String(q).replace('.', ',');
  }

  function showEmpty() {
    app.innerHTML =
      '<div class="cos-empty" data-cos-state="empty">' +
        '<p class="cos-empty-title">Coșul tău este gol.</p>' +
        '<p>Adaugă produse din paginile de categorii sau din catalog.</p>' +
        '<a class="btn-cart cos-empty-cta" href="/catalog.html">Vezi catalogul</a>' +
      '</div>';
  }

  function showError(text) {
    app.innerHTML =
      '<p class="cos-msg cos-msg--error" role="alert" data-cos-state="error">' + esc(text) + '</p>' +
      '<p><button type="button" class="cos-retry">Încearcă din nou</button></p>';
  }

  function lineHtml(l) {
    var unit = l.unit ? ' / ' + esc(l.unit) : '';
    var img = l.image
      ? '<img class="cos-line-img" src="/' + esc(String(l.image).replace(/^\//, '')) + '" alt="" width="72" height="72" loading="lazy" onerror="this.style.visibility=\'hidden\'">'
      : '<div class="cos-line-img cos-line-img--none" aria-hidden="true"></div>';
    var name = l.name ? esc(l.name) : 'Produs indisponibil (cod ' + esc(l.id) + ')';

    if (!l.orderable && !l.over_stock) {
      return '<li class="cos-line cos-line--problem" data-id="' + esc(l.id) + '">' + img +
        '<div class="cos-line-main">' +
          '<p class="cos-line-name">' + name + '</p>' +
          '<p class="cos-line-problem">' + esc(l.problem) + '</p>' +
        '</div>' +
        '<button type="button" class="cos-remove" data-action="remove" aria-label="Scoate ' + name + ' din coș">Scoate din coș</button>' +
      '</li>';
    }

    var stock = l.stock_label ? '<span class="stock stock--' + esc(l.stock) + '">' + esc(l.stock_label) + '</span>' : '';
    var inputId = 'qty-' + esc(l.id);
    // numele duce la pagina produsului (doar adrese interne /produs/..., venite de la server)
    var nameHtml = l.url && /^\/produs\/[a-z0-9%-]+\.html$/.test(l.url) ? '<a href="' + esc(l.url) + '">' + name + '</a>' : name;
    // peste stoc: randul ramane cu cantitatea, ca sa poata fi micsorata pe loc
    var over = l.over_stock ? '<p class="cos-line-problem" role="alert">' + esc(l.problem) + '</p>' : '';
    return '<li class="cos-line' + (l.over_stock ? ' cos-line--over' : '') + '" data-id="' + esc(l.id) + '" data-decimals="' + (l.decimals ? '1' : '0') + '"' +
      (l.max !== null && l.max !== undefined ? ' data-max="' + esc(l.max) + '"' : '') + '>' + img +
      '<div class="cos-line-main">' +
        '<p class="cos-line-name">' + nameHtml + '</p>' +
        '<p class="cos-line-price">' + esc(l.price_label) + unit + '</p>' +
        stock + over +
      '</div>' +
      '<div class="cos-qty">' +
        '<label class="cos-qty-label" for="' + inputId + '">Cantitate' + (l.unit ? ' (' + esc(l.unit) + ')' : '') + '</label>' +
        '<div class="cos-qty-ctrl">' +
          '<button type="button" data-action="minus" aria-label="Scade cantitatea">−</button>' +
          // type="text": un camp numeric refuza virgula in multe browsere, iar clientii scriu „2,5".
          '<input id="' + inputId + '" type="text" inputmode="' + (l.decimals ? 'decimal' : 'numeric') + '" autocomplete="off" maxlength="9" value="' + esc(fmtQty(l.qty)) + '">' +
          '<button type="button" data-action="plus" aria-label="Crește cantitatea">+</button>' +
        '</div>' +
      '</div>' +
      '<p class="cos-line-total" aria-label="Subtotal">' + esc(l.line_label || '') + '</p>' +
      '<button type="button" class="cos-remove" data-action="remove" aria-label="Scoate ' + name + ' din coș">Scoate</button>' +
    '</li>';
  }

  function render(cart) {
    if (!cart.lines.length) { showEmpty(); return; }
    var blocked = cart.problem_count > 0;
    // doar cantitati peste stoc (nimic de scos din cos) => alt mesaj
    var onlyOver = blocked && cart.lines.every(function (l) { return l.orderable || l.over_stock; });
    var canContinue = cart.orderable_count > 0 && !blocked;

    app.innerHTML =
      '<div class="cos-layout" data-cos-state="ready">' +
        '<ul class="cos-lines">' + cart.lines.map(lineHtml).join('') + '</ul>' +
        '<aside class="cos-summary">' +
          '<p class="cos-summary-row"><span>Produse</span><span>' + cart.orderable_count + '</span></p>' +
          '<p class="cos-summary-row cos-summary-total"><span>Total (cu TVA)</span><span data-cos-total>' + esc(cart.total_label) + '</span></p>' +
          '<p class="cos-summary-note">Costul transportului nu este inclus.</p>' +
          (blocked
            ? '<p class="cos-msg cos-msg--warn" role="alert">' + (onlyOver
                ? 'Micșorează cantitatea la produsele marcate (nu avem destul pe stoc) ca să continui — sau sună la ' + PHONE + '.'
                : 'Unele produse nu se pot comanda online. Scoate-le din coș ca să continui — sau sună la ' + PHONE + '.') + '</p>'
            : '') +
          (canContinue
            ? '<a class="btn-cart cos-continue" href="/comanda.html">Continuă comanda</a>'
            : '<button type="button" class="btn-cart cos-continue" disabled>Continuă comanda</button>') +
          '<a class="cos-back" href="/catalog.html">← Continuă cumpărăturile</a>' +
        '</aside>' +
      '</div>';
  }

  function refresh() {
    var items = Cos.read();
    if (!items.length) { showEmpty(); return; }
    var seq = ++requestSeq;
    app.setAttribute('aria-busy', 'true');
    fetch('/cos-produse.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ items: items }),
      credentials: 'same-origin',
    }).then(function (res) {
      return res.json().catch(function () { return {}; }).then(function (body) {
        if (!res.ok) { throw new Error(body.error || 'Eroare la verificarea coșului.'); }
        return body;
      });
    }).then(function (cart) {
      if (seq !== requestSeq) { return; } // un raspuns mai vechi a ajuns dupa unul nou
      render(cart);
    }).catch(function (err) {
      if (seq !== requestSeq) { return; }
      showError((err && err.message) || ('Coșul nu poate fi verificat acum. Sună la ' + PHONE + '.'));
    }).then(function () {
      app.removeAttribute('aria-busy');
    });
  }

  function scheduleRefresh() {
    clearTimeout(refreshTimer);
    refreshTimer = setTimeout(refresh, 350);
  }

  function lineOf(el) {
    var li = el.closest('.cos-line');
    var max = li && li.getAttribute('data-max');
    return li ? { li: li, id: parseInt(li.getAttribute('data-id'), 10), decimals: li.getAttribute('data-decimals') === '1',
                  max: max !== null && max !== '' ? parseFloat(max) : null } : null;
  }

  function normalizeQty(value, decimals) {
    var q = Cos.cleanQty(value);
    if (q === null) { return null; }
    return decimals ? q : Math.max(1, Math.round(q));
  }

  app.addEventListener('click', function (ev) {
    var btn = ev.target.closest('button');
    if (!btn) { return; }
    if (btn.classList.contains('cos-retry')) { refresh(); return; }
    var line = lineOf(btn);
    if (!line) { return; }
    var action = btn.getAttribute('data-action');

    if (action === 'remove') {
      Cos.remove(line.id);
      line.li.remove();
      refresh();
      return;
    }
    if (action === 'plus' || action === 'minus') {
      var input = line.li.querySelector('input');
      var cur = normalizeQty(input.value, line.decimals) || 1;
      var next = action === 'plus' ? cur + 1 : cur - 1;
      if (next < (line.decimals ? 0.01 : 1)) { return; } // scoaterea se face explicit, cu „Scoate"
      if (action === 'plus' && line.max !== null && next > line.max) { next = line.max; } // nu peste stoc
      next = normalizeQty(next, line.decimals);
      input.value = fmtQty(next);
      Cos.setQty(line.id, next);
      scheduleRefresh();
    }
  });

  app.addEventListener('change', function (ev) {
    if (ev.target.tagName !== 'INPUT') { return; }
    var line = lineOf(ev.target);
    if (!line) { return; }
    var q = normalizeQty(ev.target.value, line.decimals);
    if (q === null) {
      // valoare goala sau invalida: revenim la cantitatea salvata
      var saved = Cos.read().filter(function (it) { return it.id === line.id; })[0];
      ev.target.value = fmtQty(saved ? saved.qty : 1);
      return;
    }
    ev.target.value = fmtQty(q);
    Cos.setQty(line.id, q);
    scheduleRefresh();
  });

  // Cosul modificat in alt tab => reafisam.
  window.addEventListener('storage', function (ev) {
    if (ev.key === 'mugurel_cos' || ev.key === null) { scheduleRefresh(); }
  });

  refresh();
})();
