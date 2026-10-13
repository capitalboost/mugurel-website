/*
 * Pagina de comanda (/comanda.html). Depinde de js/cos.js (window.MugurelCos).
 *
 *  - sumarul cosului, calculat de server (/cos-produse.php);
 *  - campul ascuns „cos" primeste {id, qty} — serverul recalculeaza tot la trimitere;
 *  - verificare rapida in browser (campuri goale, format evident gresit). Regulile complete
 *    sunt pe server (includes/order_form.php); aici doar evitam un drum inutil pana la server.
 */
(function () {
  'use strict';

  var form = document.querySelector('[data-comanda-form]');
  var summary = document.getElementById('comanda-sumar');
  if (!form || !summary || !window.MugurelCos) { return; }
  var Cos = window.MugurelCos;
  var submit = form.querySelector('[data-comanda-submit]');
  var cosField = form.querySelector('[data-cos-field]');
  var PHONE = '0749 130 565';

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  function block(reason) {
    submit.disabled = true;
    submit.setAttribute('data-blocked', reason);
  }

  function showEmptyCart() {
    var layout = document.querySelector('.comanda-layout');
    layout.innerHTML =
      '<div class="cos-empty" data-cos-state="empty">' +
        '<p class="cos-empty-title">Cosul tau este gol.</p>' +
        '<p>Adauga produse ca sa poti plasa o comanda.</p>' +
        '<a class="btn-cart cos-empty-cta" href="/catalog.html">Vezi catalogul</a>' +
      '</div>';
  }

  function renderSummary(cart) {
    var lines = cart.lines.filter(function (l) { return l.orderable; }).map(function (l) {
      return '<li class="comanda-sum-line"><span>' + esc(l.name) + ' <span class="comanda-sum-qty">× ' +
        esc(String(l.qty).replace('.', ',')) + (l.unit ? ' ' + esc(l.unit) : '') + '</span></span>' +
        '<span>' + esc(l.line_label) + '</span></li>';
    }).join('');
    var problems = cart.problem_count > 0
      ? '<p class="cos-msg cos-msg--warn" role="alert">Unele produse din cos nu se mai pot comanda. ' +
        '<a href="/cos.html">Verifica cosul</a> inainte sa trimiti comanda.</p>'
      : '';
    summary.innerHTML =
      '<h2 class="comanda-sum-title">Comanda ta</h2>' +
      '<ul class="comanda-sum-lines">' + lines + '</ul>' +
      '<p class="cos-summary-row cos-summary-total"><span>Total (cu TVA)</span><span data-cos-total>' + esc(cart.total_label) + '</span></p>' +
      '<p class="cos-summary-note">Costul transportului nu este inclus.</p>' +
      problems +
      '<a class="cos-back" href="/cos.html">← Modifica cosul</a>';
    if (cart.problem_count > 0 || cart.orderable_count === 0) { block('cos'); }
  }

  function loadSummary() {
    var items = Cos.read();
    if (!items.length) { showEmptyCart(); return; }
    cosField.value = JSON.stringify(items);
    fetch('/cos-produse.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ items: items }),
      credentials: 'same-origin',
    }).then(function (res) {
      return res.json().catch(function () { return {}; }).then(function (body) {
        if (!res.ok) { throw new Error(body.error || 'Eroare'); }
        return body;
      });
    }).then(renderSummary).catch(function (err) {
      summary.innerHTML = '<p class="cos-msg cos-msg--error" role="alert">' +
        esc((err && err.message !== 'Eroare' && err.message) || ('Cosul nu poate fi verificat acum. Suna la ' + PHONE + '.')) + '</p>';
      block('server');
    });
  }

  // ── verificare rapida in browser ──────────────────────────────────────

  var CHECKS = {
    nume: function (v) { return v.length >= 3 ? '' : 'Scrie numele si prenumele.'; },
    telefon: function (v) { return v.replace(/\D/g, '').length >= 9 ? '' : (v ? 'Numarul de telefon nu pare corect (ex. 0749 130 565).' : 'Scrie un numar de telefon — te sunam ca sa confirmam comanda.'); },
    email: function (v) { return /^[^\s@]+@[^\s@]+\.[a-z]{2,}$/i.test(v) ? '' : (v ? 'Adresa de email nu pare corecta.' : 'Scrie adresa de email — iti trimitem confirmarea comenzii.'); },
    judet: function (v) { return v ? '' : 'Alege judetul.'; },
    localitate: function (v) { return v.length >= 2 ? '' : 'Scrie localitatea.'; },
    adresa: function (v) { return v.length >= 5 ? '' : 'Scrie adresa de livrare (strada, numar, bloc, apartament).'; },
  };

  function setError(field, msg) {
    var id = 'err-' + field.name;
    var p = document.getElementById(id);
    if (!msg) {
      field.removeAttribute('aria-invalid');
      field.removeAttribute('aria-describedby');
      if (p) { p.remove(); }
      return;
    }
    field.setAttribute('aria-invalid', 'true');
    field.setAttribute('aria-describedby', id);
    if (!p) {
      p = document.createElement('p');
      p.className = 'form-error';
      p.id = id;
      field.insertAdjacentElement('afterend', p);
    }
    p.textContent = msg;
  }

  function checkField(field) {
    var check = CHECKS[field.name];
    if (!check) { return true; }
    var msg = check(field.value.trim());
    setError(field, msg);
    return !msg;
  }

  form.addEventListener('submit', function (ev) {
    var first = null;
    Object.keys(CHECKS).forEach(function (name) {
      var field = form.elements[name];
      if (field && !checkField(field) && !first) { first = field; }
    });
    if (first || submit.disabled || !cosField.value) {
      ev.preventDefault();
      if (first) { first.focus(); }
      return;
    }
    submit.disabled = true; // un singur click; protectia reala la dubluri e pe server (T11)
    submit.textContent = 'Se trimite…';
  });

  // Dupa o eroare, mesajul dispare cand campul e corectat.
  form.addEventListener('change', function (ev) {
    if (ev.target.getAttribute('aria-invalid') === 'true') { checkField(ev.target); }
  });

  var serverErrors = document.querySelector('[data-form-errors]');
  if (serverErrors) { serverErrors.focus(); }

  loadSummary();
})();
