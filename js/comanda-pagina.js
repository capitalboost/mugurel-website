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
        '<p class="cos-empty-title">Coșul tău este gol.</p>' +
        '<p>Adaugă produse ca să poți plasa o comandă.</p>' +
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
      ? '<p class="cos-msg cos-msg--warn" role="alert">Unele produse din coș nu se mai pot comanda. ' +
        '<a href="/cos.html">Verifică coșul</a> înainte să trimiți comanda.</p>'
      : '';
    summary.innerHTML =
      '<h2 class="comanda-sum-title">Comanda ta</h2>' +
      '<ul class="comanda-sum-lines">' + lines + '</ul>' +
      '<p class="cos-summary-row cos-summary-total"><span>Total (cu TVA)</span><span data-cos-total>' + esc(cart.total_label) + '</span></p>' +
      '<p class="cos-summary-note">Costul transportului nu este inclus.</p>' +
      problems +
      '<a class="cos-back" href="/cos.html">← Modifică coșul</a>';
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
        esc((err && err.message !== 'Eroare' && err.message) || ('Coșul nu poate fi verificat acum. Sună la ' + PHONE + '.')) + '</p>';
      block('server');
    });
  }

  // ── verificare rapida in browser ──────────────────────────────────────

  var CHECKS = {
    nume: function (v) { return v.length >= 3 ? '' : 'Scrie numele și prenumele.'; },
    telefon: function (v) { return v.replace(/\D/g, '').length >= 9 ? '' : (v ? 'Numărul de telefon nu pare corect (ex. 0749 130 565).' : 'Scrie un număr de telefon — te sunăm ca să confirmăm comanda.'); },
    email: function (v) { return /^[^\s@]+@[^\s@]+\.[a-z]{2,}$/i.test(v) ? '' : (v ? 'Adresa de email nu pare corectă.' : 'Scrie adresa de email — îți trimitem confirmarea comenzii.'); },
    plata: function () { return form.querySelector('[data-plata]:checked') ? '' : 'Alege cum vrei să plătești.'; },
    acord: function () { return form.elements.acord.checked ? '' : 'Ca să trimiți comanda, trebuie să accepți Termenii și Politica de confidențialitate.'; },
    judet: function (v) { return v ? '' : 'Alege județul.'; },
    localitate: function (v) { return v.length >= 2 ? '' : 'Scrie localitatea.'; },
    adresa: function (v) { return v.length >= 5 ? '' : 'Scrie adresa de livrare (strada, număr, bloc, apartament).'; },
  };
  // Doar pentru persoana juridica. CUI si Registrul Comertului se verifica complet pe server.
  var CHECKS_PJ = {
    firma: function (v) { return v.length >= 2 ? '' : 'Scrie denumirea firmei.'; },
    cui: function (v) { return v ? '' : 'Scrie codul fiscal (CUI) al firmei.'; },
    reg_com: function (v) { return v ? '' : 'Scrie numărul de la Registrul Comerțului.'; },
    sediu: function (v) { return v.length >= 5 ? '' : 'Scrie adresa sediului social.'; },
  };

  // ── persoana fizica / juridica ──────────────────────────────────────────
  var pjBlock = form.querySelector('[data-pj-block]');
  var adresaBlock = form.querySelector('[data-adresa-block]');
  var ADRESA = ['judet', 'localitate', 'adresa'];

  function isPickup() {
    var r = form.querySelector('[data-plata]:checked');
    return !!r && r.value === 'ridicare';
  }

  /** La ridicarea din magazin nu cerem adresa de livrare. */
  function applyPlata() {
    adresaBlock.hidden = isPickup();
    form.querySelector('[data-transport-info]').hidden = isPickup(); // la ridicare nu exista transport
    if (isPickup()) { ADRESA.forEach(function (n) { setError(form.elements[n], ''); }); }
  }
  var numeLabel = form.querySelector('[data-nume-label]');

  function isPj() {
    var r = form.querySelector('[data-tip]:checked');
    return !!r && r.value === 'pj';
  }

  function applyTip() {
    var pj = isPj();
    pjBlock.hidden = !pj;
    numeLabel.firstChild.textContent = numeLabel.getAttribute(pj ? 'data-pj' : 'data-pf') + ' ';
    if (!pj) { // erorile campurilor de firma nu mai sunt relevante
      Object.keys(CHECKS_PJ).forEach(function (n) { if (form.elements[n]) { setError(form.elements[n], ''); } });
    }
  }
  form.addEventListener('change', function (ev) {
    if (ev.target.hasAttribute('data-tip')) { applyTip(); }
    if (ev.target.hasAttribute('data-plata')) { applyPlata(); checkField(form.elements.plata[0]); }
  });

  function setError(field, msg) {
    var id = 'err-' + field.name;
    var p = document.getElementById(id);
    // La un grup de butoane radio, eroarea apartine grupului (fieldset), nu primului buton.
    var target = field.type === 'radio' ? field.closest('fieldset') : field;
    var anchor = field.type === 'radio' ? target.querySelector('.form-plata-options')
      : field.type === 'checkbox' ? field.closest('label') : field;
    if (!msg) {
      target.removeAttribute('aria-invalid');
      target.removeAttribute('aria-describedby');
      if (p) { p.remove(); }
      return;
    }
    target.setAttribute('aria-invalid', 'true');
    target.setAttribute('aria-describedby', id);
    if (!p) {
      p = document.createElement('p');
      p.className = 'form-error';
      p.id = id;
      anchor.insertAdjacentElement('afterend', p);
    }
    p.textContent = msg;
  }

  function checkField(field) {
    var check = CHECKS[field.name] || (isPj() && CHECKS_PJ[field.name]);
    if (!check || (isPickup() && ADRESA.indexOf(field.name) !== -1)) { return true; }
    var msg = check(field.value.trim());
    setError(field, msg);
    return !msg;
  }

  form.addEventListener('submit', function (ev) {
    var first = null;
    var names = isPj() ? Object.keys(CHECKS_PJ).concat(Object.keys(CHECKS)) : Object.keys(CHECKS);
    names.forEach(function (name) {
      var field = form.elements[name];
      if (field && field.length !== undefined && !field.tagName) { field = field[0]; } // grup de butoane radio
      if (field && !checkField(field) && !first) { first = field; }
    });
    if (first || submit.disabled || !cosField.value) {
      ev.preventDefault();
      if (first) { first.focus(); }
      return;
    }
    // Semn pentru pagina de confirmare: goleste cosul o singura data (js/comanda-trimisa.js).
    try { window.sessionStorage.setItem('mugurel_comanda_trimisa', '1'); } catch (e) { /* fara sessionStorage cosul ramane; nu e grav */ }
    submit.disabled = true; // un singur click; protectia reala la dubluri e pe server (T11)
    submit.textContent = 'Se trimite…';
  });

  // Dupa o eroare, mesajul dispare cand campul e corectat.
  form.addEventListener('change', function (ev) {
    if (ev.target.getAttribute('aria-invalid') === 'true') { checkField(ev.target); }
  });

  // „Inapoi" dupa trimitere: browserul poate reda pagina veche din memorie (formular completat, buton
  // „Se trimite…", cos plin). Reincarcam, ca pagina sa arate starea reala (cosul e deja golit).
  window.addEventListener('pageshow', function (ev) {
    if (ev.persisted) { window.location.reload(); }
  });

  var serverErrors = document.querySelector('[data-form-errors]');
  if (serverErrors) { serverErrors.focus(); }

  applyTip();
  applyPlata();
  loadSummary();
})();
