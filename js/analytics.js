/*
 * Google Analytics 4 si Microsoft Clarity — pornesc DOAR dupa acordul vizitatorului (GDPR; jurnal, punctul 6).
 * Pana pe 16 oct 2026 se incarcau din <head> la fiecare vizita, iar „Refuz" nu oprea nimic.
 *
 * Alegerea se pastreaza in localStorage („gdpr_consent" = accepted / declined), setata de bannerul din js/main.js,
 * care anunta si alegerea facuta acum prin evenimentul „mugurel:consent" — analiza porneste fara reincarcarea paginii.
 * Paginile cu date secrete in adresa (confirmarea comenzii) nu includ deloc acest fisier.
 */
(function () {
  'use strict';
  var GA_ID = 'G-R6M7YYLB48';
  var CLARITY_ID = 'y5btegvf2j';
  var loaded = false;

  function consent() {
    try { return window.localStorage.getItem('gdpr_consent'); } catch (e) { return null; }
  }

  function load() {
    if (loaded) { return; }
    loaded = true;

    window.dataLayer = window.dataLayer || [];
    window.gtag = function () { window.dataLayer.push(arguments); };
    window.gtag('js', new Date());
    window.gtag('config', GA_ID);
    var ga = document.createElement('script');
    ga.async = true;
    ga.src = 'https://www.googletagmanager.com/gtag/js?id=' + GA_ID;
    document.head.appendChild(ga);

    window.clarity = window.clarity || function () { (window.clarity.q = window.clarity.q || []).push(arguments); };
    var cl = document.createElement('script');
    cl.async = true;
    cl.src = 'https://www.clarity.ms/tag/' + CLARITY_ID;
    document.head.appendChild(cl);
  }

  if (consent() === 'accepted') { load(); }
  document.addEventListener('mugurel:consent', function (e) {
    if (e.detail === 'accepted') { load(); }
  });
})();
