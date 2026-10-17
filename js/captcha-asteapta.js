/* Captcha (Cloudflare Turnstile) pe formularele de comanda si de retur: daca clientul apasa „Trimite" inainte ca
 * verificarea sa se termine (1–3 secunde de la incarcare), formularul asteapta raspunsul si se trimite singur —
 * nimeni nu primeste o eroare doar pentru ca a fost rapid. Fara caseta pe pagina, nu face nimic. */
(function () {
  'use strict';
  function token(form) {
    var f = form.querySelector('[name="cf-turnstile-response"]');
    return f ? f.value : null;
  }
  document.addEventListener('submit', function (ev) {
    var form = ev.target;
    if (!form.querySelector || !form.querySelector('.cf-turnstile') || token(form) || form.getAttribute('data-captcha-waiting')) { return; }
    ev.preventDefault();
    ev.stopImmediatePropagation(); // ceilalti ascultatori (validarea din pagina) ruleaza la retrimitere
    form.setAttribute('data-captcha-waiting', '1');
    var btn = form.querySelector('[type="submit"]');
    var label = btn ? btn.textContent : '';
    if (btn) { btn.textContent = 'Se verifică…'; }
    var tries = 0;
    var timer = setInterval(function () {
      tries++;
      if (token(form) || tries > 50) { // ~15 s; dupa asta trimitem oricum, serverul decide
        clearInterval(timer);
        if (btn) { btn.textContent = label; }
        form.removeAttribute('data-captcha-waiting');
        if (token(form)) {
          if (form.requestSubmit) { form.requestSubmit(btn || undefined); } else { form.submit(); }
        }
      }
    }, 300);
  }, true);
}());
