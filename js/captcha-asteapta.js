/* Captcha (Cloudflare Turnstile) pe formularele de comanda si de retur: daca clientul apasa „Trimite" inainte ca
 * verificarea sa se termine (1–3 secunde de la incarcare), formularul asteapta raspunsul si se trimite singur —
 * nimeni nu primeste o eroare doar pentru ca a fost rapid. Ruleaza DUPA validarea paginii: un formular cu greseli
 * isi arata intai greselile. Fara caseta pe pagina, nu face nimic. */
(function () {
  'use strict';
  function token(form) {
    var f = form.querySelector('[name="cf-turnstile-response"]');
    return f ? f.value : null;
  }
  document.addEventListener('submit', function (ev) {
    var form = ev.target;
    if (ev.defaultPrevented) { return; } // validarea paginii a oprit trimiterea (campuri gresite)
    if (!form.querySelector || !form.querySelector('.cf-turnstile') || token(form)) { return; }
    if (form.getAttribute('data-captcha-waited')) { return; } // a asteptat deja o data: decide serverul
    form.setAttribute('data-captcha-waited', '1');
    ev.preventDefault();
    var btn = form.querySelector('[type="submit"]');
    var label = btn ? btn.getAttribute('data-label') || btn.textContent : '';
    if (btn) { btn.setAttribute('data-label', label); btn.textContent = 'Se verifică…'; btn.disabled = true; }
    var tries = 0;
    var timer = setInterval(function () {
      tries++;
      if (!token(form) && tries <= 50) { return; } // ~15 s
      clearInterval(timer);
      if (btn) { btn.disabled = false; btn.textContent = label; }
      // retrimitere: validarea paginii ruleaza din nou, apoi formularul pleaca; fara raspuns, decide serverul
      if (form.requestSubmit) { form.requestSubmit(btn || undefined); } else { form.submit(); }
    }, 300);
  });
}());
