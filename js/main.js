/* ═══════════════════════════════════════════════
   MUGUREL — JavaScript principal
   ═══════════════════════════════════════════════ */

const WA_NUMBER = '40749130565';
const WA_DEFAULT_MSG = 'Bună ziua! Vă contactez de pe site-ul Mugurel. Puteți să mă ajutați?';

/* ── WhatsApp link builder ── */
function waLink(msg) {
  return 'https://wa.me/' + WA_NUMBER + '?text=' + encodeURIComponent(msg || WA_DEFAULT_MSG);
}

/* ── Sidebar category toggle ── */
document.addEventListener('DOMContentLoaded', function () {

  // Sidebar expand/collapse
  document.querySelectorAll('.sidebar-cat-head').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      const cat = btn.closest('.sidebar-cat');
      const sub = cat.querySelector('.sidebar-sub');
      const href = btn.getAttribute('href') || '';
      // no sub-items OR direct page link → navigate normally
      if (!sub) return;
      e.preventDefault();
      const isOpen = cat.classList.contains('open');
      // close all
      document.querySelectorAll('.sidebar-cat').forEach(c => c.classList.remove('open'));
      document.querySelectorAll('.sidebar-cat-head').forEach(b => b.classList.remove('open'));
      if (!isOpen) {
        cat.classList.add('open');
        btn.classList.add('open');
      }
    });
  });

  // WhatsApp product buttons
  document.querySelectorAll('[data-wa-product]').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      const product = btn.getAttribute('data-wa-product');
      const custom = btn.getAttribute('data-wa-text');
      const msg = custom || ('Bună ziua! Sunt interesat de produsul: ' + product + '. Puteți să îmi confirmați disponibilitatea și prețul?');
      window.open(waLink(msg), '_blank');
    });
  });

  // Floating WA button
  const waFloat = document.getElementById('wa-float');
  if (waFloat) {
    waFloat.href = waLink(WA_DEFAULT_MSG);
  }

  // Mobile menu
  const hamburger = document.getElementById('hamburger');
  const mobileMenu = document.getElementById('mobile-menu');
  const mobileClose = document.getElementById('mobile-close');
  if (hamburger && mobileMenu) {
    hamburger.addEventListener('click', () => mobileMenu.classList.add('open'));
    if (mobileClose) mobileClose.addEventListener('click', () => mobileMenu.classList.remove('open'));
  }

  // Footer accordion pe mobil
  if (window.innerWidth <= 768) {
    document.querySelectorAll('.f-col').forEach(function(col) {
      var h4 = col.querySelector('h4');
      if (!h4) return;
      if (h4.textContent.trim() === 'Contact') {
        col.classList.add('open');
        return;
      }
      h4.addEventListener('click', function() {
        col.classList.toggle('open');
      });
    });
  }

  // Back to top button
  var btt = document.getElementById('back-to-top');
  if (btt) {
    window.addEventListener('scroll', function () {
      if (window.scrollY > 400) btt.classList.add('visible');
      else btt.classList.remove('visible');
    }, { passive: true });
    btt.addEventListener('click', function () {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  }

  // Active nav link
  const current = window.location.pathname.split('/').pop() || 'index.html';
  document.querySelectorAll('.nav-item[href]').forEach(function (a) {
    if (a.getAttribute('href') === current) a.classList.add('active');
  });

  // Header search. Pe catalog.html campul e un <form> care trimite GET direct la server
  // (pastreaza si filtrul de categorie prin inputul hidden) — nu are nevoie de redirect din JS.
  var searchInput = document.querySelector('.searchbar input');
  var searchBtn = document.querySelector('.searchbar button');
  var searchIsForm = searchInput && searchInput.closest('form');
  function doSearch() {
    var q = searchInput ? searchInput.value.trim() : '';
    if (q.length < 2) return;
    window.location.href = 'catalog.html?q=' + encodeURIComponent(q);
  }
  if (!searchIsForm) {
    if (searchBtn) searchBtn.addEventListener('click', doSearch);
    if (searchInput) {
      searchInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') doSearch();
      });
    }
  }


});

/* ── GDPR Cookie Banner ── */
// Apare la prima vizita si se redeschide oricand din link-ul „Setari cookie" din footer ([data-cookie-settings]),
// ca acordul sa se poata retrage la fel de usor cum a fost dat (GDPR).
(function () {
  function read() { try { return localStorage.getItem('gdpr_consent'); } catch (e) { return null; } }

  // Retragerea acordului: stergem cookie-urile Google Analytics / Clarity si reincarcam pagina — scripturile deja
  // pornite nu se pot opri altfel.
  function clearAnalyticsCookies() {
    var host = location.hostname.replace(/^www\./, '');
    document.cookie.split(';').forEach(function (c) {
      var name = c.split('=')[0].trim();
      if (!/^(_ga|_gid|_gat|_clck|_clsk|CLID|MUID)/.test(name)) { return; }
      ['', host, '.' + host].forEach(function (d) {
        document.cookie = name + '=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/' + (d ? '; domain=' + d : '');
      });
    });
  }

  function open() {
    var old = document.getElementById('gdpr-banner');
    if (old) { old.remove(); } // un banner abia inchis (in animatie) nu blocheaza redeschiderea
    var previous = read();
    var banner = document.createElement('div');
    banner.id = 'gdpr-banner';
    banner.setAttribute('role', 'dialog');
    banner.setAttribute('aria-label', 'Setări cookie');
    banner.innerHTML =
      '<div class="gdpr-text">Folosim cookie-uri strict necesare pentru funcționarea site-ului (coș, formulare). Cu acordul tău folosim și cookie-uri de analiză (Google Analytics, Microsoft Clarity), ca să înțelegem cum e folosit site-ul. Dacă refuzi, acestea nu se încarcă. Îți poți schimba alegerea oricând din „Setări cookie”, în subsolul paginii. <a href="/politica-confidentialitate.html#cookie">Politica de confidențialitate</a>' +
      (previous ? '<br><span class="gdpr-current">Alegerea ta actuală: <strong>' + (previous === 'accepted' ? 'accepți' : 'refuzi') + '</strong> cookie-urile de analiză.</span>' : '') + '</div>' +
      '<div class="gdpr-btns">' +
      '<button type="button" class="gdpr-decline" id="gdpr-decline">Refuz</button>' +
      '<button type="button" class="gdpr-accept" id="gdpr-accept">Accept</button>' +
      '</div>';

    document.body.appendChild(banner);
    requestAnimationFrame(function () {
      requestAnimationFrame(function () { banner.classList.add('gdpr-show'); });
    });

    function dismiss(val) {
      try { localStorage.setItem('gdpr_consent', val); } catch (e) { /* fara stocare: alegerea tine doar pe pagina curenta */ }
      banner.classList.remove('gdpr-show');
      setTimeout(function () { banner.remove(); }, 320);
      if (val === 'declined' && previous === 'accepted') {
        clearAnalyticsCookies();
        setTimeout(function () { location.reload(); }, 350);
        return;
      }
      // js/analytics.js porneste analiza imediat dupa „Accept", fara reincarcarea paginii
      document.dispatchEvent(new CustomEvent('mugurel:consent', { detail: val }));
    }

    document.getElementById('gdpr-accept').addEventListener('click', function () { dismiss('accepted'); });
    document.getElementById('gdpr-decline').addEventListener('click', function () { dismiss('declined'); });
    document.getElementById(previous === 'accepted' ? 'gdpr-decline' : 'gdpr-accept').focus({ preventScroll: true });
  }

  document.addEventListener('click', function (e) {
    var link = e.target.closest && e.target.closest('[data-cookie-settings]');
    if (!link) { return; }
    e.preventDefault();
    open();
  });

  if (!read()) { open(); }
}());
