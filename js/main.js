/* ═══════════════════════════════════════════════
   MUGUREL — JavaScript principal
   ═══════════════════════════════════════════════ */

const WA_NUMBER = '40749130565';
const WA_DEFAULT_MSG = 'Buna ziua! Va contactez de pe site-ul Mugurel. Puteti sa ma ajutati?';

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
      if (!sub || href.endsWith('.html')) return;
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
      const msg = 'Buna ziua! Sunt interesat de produsul: ' + product + '. Puteti sa imi confirmati disponibilitatea si pretul?';
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

  // Catalog filter chips
  document.querySelectorAll('.chip[data-filter]').forEach(function (chip) {
    chip.addEventListener('click', function () {
      const filter = chip.getAttribute('data-filter');
      document.querySelectorAll('.chip[data-filter]').forEach(c => c.classList.remove('active'));
      chip.classList.add('active');
      filterProducts(filter);
    });
  });

  // Active nav link
  const current = window.location.pathname.split('/').pop() || 'index.html';
  document.querySelectorAll('.nav-item[href]').forEach(function (a) {
    if (a.getAttribute('href') === current) a.classList.add('active');
  });


});

/* ── Product filter ── */
function filterProducts(filter) {
  const cards = document.querySelectorAll('.prod-card[data-cat]');
  let visible = 0;
  cards.forEach(function (card) {
    const cat = card.getAttribute('data-cat');
    if (filter === 'all' || cat === filter) {
      card.style.display = '';
      visible++;
    } else {
      card.style.display = 'none';
    }
  });
  const counter = document.getElementById('result-count');
  if (counter) counter.textContent = visible;
}

function updateResultCount() {
  const cards = document.querySelectorAll('.prod-card[data-cat]');
  const visible = Array.from(cards).filter(c => c.style.display !== 'none').length;
  const counter = document.getElementById('result-count');
  if (counter) counter.textContent = visible;
}

/* ── GDPR Cookie Banner ── */
(function () {
  if (localStorage.getItem('gdpr_consent')) return;

  const banner = document.createElement('div');
  banner.id = 'gdpr-banner';
  banner.innerHTML =
    '<div class="gdpr-text">Folosim cookie-uri pentru a asigura functionarea corecta a site-ului. Prin continuarea navigarii esti de acord cu utilizarea acestora. <a href="politica-confidentialitate.html">Politica de confidentialitate</a></div>' +
    '<div class="gdpr-btns">' +
    '<button class="gdpr-decline" id="gdpr-decline">Refuz</button>' +
    '<button class="gdpr-accept" id="gdpr-accept">Accept</button>' +
    '</div>';

  document.body.appendChild(banner);
  requestAnimationFrame(function () {
    requestAnimationFrame(function () { banner.classList.add('gdpr-show'); });
  });

  function dismiss(val) {
    localStorage.setItem('gdpr_consent', val);
    banner.classList.remove('gdpr-show');
    setTimeout(function () { banner.remove(); }, 320);
  }

  document.getElementById('gdpr-accept').addEventListener('click', function () { dismiss('accepted'); });
  document.getElementById('gdpr-decline').addEventListener('click', function () { dismiss('declined'); });
}());
