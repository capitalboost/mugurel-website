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
      e.preventDefault();
      const cat = btn.closest('.sidebar-cat');
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

  // Sidebar sub-items filter
  document.querySelectorAll('.sidebar-sub-item[data-filter]').forEach(function (item) {
    item.addEventListener('click', function (e) {
      e.preventDefault();
      const filter = item.getAttribute('data-filter');
      document.querySelectorAll('.sidebar-sub-item').forEach(i => i.classList.remove('active'));
      item.classList.add('active');
      filterProducts(filter);
      // Also update chip
      document.querySelectorAll('.chip[data-filter]').forEach(c => c.classList.remove('active'));
      const matchChip = document.querySelector('.chip[data-filter="' + filter + '"]');
      if (matchChip) matchChip.classList.add('active');
      // Update result count
      updateResultCount();
    });
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
