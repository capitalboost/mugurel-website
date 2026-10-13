/*
 * Pagina de confirmare (/comanda-trimisa.html). Goleste cosul — dar o singura data, imediat dupa
 * trimitere: js/comanda-pagina.js pune un semn in sessionStorage la trimiterea formularului.
 * Daca clientul redeschide mai tarziu legatura din email, cosul lui nou ramane neatins.
 */
(function () {
  'use strict';
  var page = document.querySelector('[data-confirmare][data-order]');
  if (!page || !window.MugurelCos) { return; }
  var flag;
  try { flag = window.sessionStorage.getItem('mugurel_comanda_trimisa'); } catch (e) { return; }
  if (flag !== '1') { return; }
  window.MugurelCos.clear();
  try { window.sessionStorage.removeItem('mugurel_comanda_trimisa'); } catch (e) { /* nimic */ }
})();
