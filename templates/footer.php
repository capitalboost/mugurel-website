<footer class="footer">
  <div class="footer-inner">
    <div class="footer-top">
      <div class="f-about">
        <img src="/img/logo.svg" alt="Mugurel" class="f-logo" width="160" height="44" loading="eager" decoding="async">
        <p>Magazin de materiale de constructii si electrocasnice din Dabuleni. Preturi cu TVA, livrare in Dolj.</p>
        <div class="f-social">
          <a href="https://www.facebook.com/byMUGUREL" class="soc-btn" target="_blank" rel="noopener" aria-label="Facebook"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg></a>
          <a href="https://wa.me/40749130565" class="soc-btn" target="_blank" rel="noopener" aria-label="WhatsApp"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413z"/></svg></a>
        </div>
      </div>
      <div class="f-col f-col--cats">
        <h4>Categorii</h4>
        <ul>
          <?php foreach (array_slice($navItems ?? siteNavItems(), 0, 8) as $it): ?>
          <li><a href="<?= htmlspecialchars($it['url'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($it['name'], ENT_QUOTES, 'UTF-8') ?></a></li>
          <?php endforeach; ?>
          <?php foreach (catCollections() as $col): ?>
          <li><a href="/<?= htmlspecialchars($col['slug'], ENT_QUOTES, 'UTF-8') ?>.html"><?= htmlspecialchars($col['name'], ENT_QUOTES, 'UTF-8') ?></a></li>
          <?php endforeach; ?>
          <li><a href="/catalog.html">Toate produsele</a></li>
        </ul>
      </div>
      <div class="f-col">
        <h4>Informatii</h4>
        <ul>
          <li><a href="/despre-noi.html">Despre noi</a></li>
          <li><a href="/contact.html">Contact</a></li>
          <li><a href="/politica-retur.html">Politica de retur</a></li>
          <li><a href="/termeni-conditii.html">Termeni si conditii</a></li>
          <li><a href="/politica-confidentialitate.html">Confidentialitate</a></li>
        </ul>
      </div>
      <div class="f-col">
        <h4>Contact</h4>
        <div class="f-contact-row"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 13 19.79 19.79 0 0 1 1.61 4.4 2 2 0 0 1 3.6 2.2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 9.91a16 16 0 0 0 6.16 6.16l1.87-1.87a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg><a href="https://wa.me/40749130565">0749 130 565</a></div>
        <div class="f-contact-row"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg><a href="mailto:dunlite@gmail.com">dunlite@gmail.com</a></div>
        <div class="f-contact-row"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>L–V: 8:00–18:00</div>
      </div>
    </div>
    <div class="f-bottom">
      <span>© 2026 DUNLITE COM SRL — CIF: RO9027530 | J16/1357/1996 | Strada Caracal Nr.52, 207220 Dabuleni, Jud. Dolj</span>
      <span>Built by <a href="https://capitalboost.ro/" style="color:rgba(255,255,255,0.4);">CapitalBoost.ro</a></span>
    </div>
  </div>
</footer>

<?php if (empty($hideWaFloat)): /* prima pagina nu-l afiseaza: prea multe butoane WhatsApp */ ?>
<a href="#" id="wa-float" class="wa-float" target="_blank" rel="noopener" aria-label="WhatsApp">
  <div class="wa-float-pulse"></div>
  <div class="wa-float-btn"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413z"/></svg></div>
</a>
<?php endif; ?>

<div class="mobile-menu" id="mobile-menu">
  <div class="mobile-menu-header">
    <a href="/index.html" class="logo"><img src="/img/logo.svg" alt="Mugurel" style="height:36px;"></a>
    <button class="mobile-close" id="mobile-close" aria-label="Inchide meniu"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
  </div>
  <a href="/index.html" class="mobile-nav-item">Acasa</a>
  <a href="/catalog.html" class="mobile-nav-item">Toate produsele</a>
  <?php foreach ($navItems ?? siteNavItems() as $it): ?>
  <a href="<?= htmlspecialchars($it['url'], ENT_QUOTES, 'UTF-8') ?>" class="mobile-nav-item"><?= htmlspecialchars($it['name'], ENT_QUOTES, 'UTF-8') ?></a>
  <?php foreach ($it['children'] as $ch): ?>
  <a href="<?= htmlspecialchars($ch['url'], ENT_QUOTES, 'UTF-8') ?>" class="mobile-nav-item mobile-nav-item--sub"><?= htmlspecialchars($ch['name'], ENT_QUOTES, 'UTF-8') ?></a>
  <?php endforeach; ?>
  <?php endforeach; ?>
  <a href="/despre-noi.html" class="mobile-nav-item">Despre noi</a>
  <a href="/contact.html" class="mobile-nav-item">Contact</a>
  <a href="https://wa.me/40749130565?text=Buna%20ziua!%20Va%20contactez%20de%20pe%20site-ul%20Mugurel." class="mobile-wa" target="_blank" rel="noopener">
    <svg viewBox="0 0 24 24" fill="currentColor" style="width:20px;height:20px;"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413z"/></svg>
    Scrie-ne pe WhatsApp — 0749 130 565
  </a>
</div>

<button class="back-to-top" id="back-to-top" aria-label="Inapoi sus">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="18 15 12 9 6 15"/></svg>
</button>

<script src="/js/main.js"></script>

<script src="<?= '/js/cos.js?v=' . @filemtime(__DIR__ . '/../js/cos.js') ?>" defer></script>
<?php foreach (($pageScripts ?? []) as $script): /* scripturi proprii paginii, din js/, cu versiune */ ?>
<script src="<?= '/js/' . htmlspecialchars($script, ENT_QUOTES, 'UTF-8') . '?v=' . @filemtime(__DIR__ . '/../js/' . basename($script)) ?>" defer></script>
<?php endforeach; ?>
</body>
</html>
