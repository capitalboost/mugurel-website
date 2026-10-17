<?php
/**
 * Prima pagina: sectiunile generate din catalog (sidebar-ul de categorii, grila de categorii, produse recomandate).
 * Inlocuieste cardurile scrise de mana + js/produse-acasa.js din catalogul vechi.
 */
require_once __DIR__ . '/catalog_page.php';
require_once __DIR__ . '/site_nav.php';

/**
 * Produsele recomandate: cate unul din fiecare grupa a meniului (pret fix, cu poza, verificate intai), apoi completam.
 * Doar produse cu poza (cerut de Lucian 18 oct): prima pagina nu arata panouri generate.
 * Ordinea e stabila (aceleasi produse la fiecare incarcare), ca pagina sa poata fi pusa in cache.
 */
function homeFeatured(int $limit = 6): array
{
    try {
        $pdo = Database::get();
        $picked = [];
        // grupele, luate pe rand din fiecare ramura (Acoperis, Aragazuri, Adezivi, Aspiratoare...)
        $byRoot = [];
        // doar ramurile mari (de la 20 de produse); o ramura cu 1–2 produse nu e „reprezentativa"
        $big = array_column(array_filter(siteNavItems(), fn ($r) => $r['count'] >= 20), 'id');
        foreach (siteNavGroups() as $g) { if (!$big || in_array($g['root'], $big, true)) { $byRoot[$g['root']][] = $g; } }
        $groups = [];
        for ($i = 0; $byRoot && $i < 50; $i++) {
            foreach ($byRoot as $r => $list) { if (isset($list[$i])) { $groups[] = $list[$i]; } }
            if (!array_filter($byRoot, fn ($l) => isset($l[$i + 1]))) { break; }
        }
        foreach ($groups as $it) {
            $ids = catDescendantIds($it['id']);
            $id = $pdo->query('SELECT id FROM products WHERE is_visible = 1 AND barcode IS NOT NULL AND price > 0 AND CHAR_LENGTH(image_path) > 0
                               AND stock_status <> \'indisponibil\' AND category_id IN (' . implode(',', array_map('intval', $ids)) . ")
                               ORDER BY verify_status = 'ok' DESC, sort_order LIMIT 1")->fetchColumn();
            if ($id !== false) { $picked[] = (int) $id; }
            if (count($picked) >= $limit) { break; }
        }
        if (count($picked) < $limit) {
            $more = $pdo->query('SELECT id FROM products WHERE is_visible = 1 AND barcode IS NOT NULL AND price > 0 AND CHAR_LENGTH(image_path) > 0'
                . ($picked ? ' AND id NOT IN (' . implode(',', $picked) . ')' : '')
                . " ORDER BY verify_status = 'ok' DESC, sort_order LIMIT " . ($limit - count($picked)))->fetchAll(PDO::FETCH_COLUMN);
            $picked = array_merge($picked, array_map('intval', $more));
        }
        return catProductsPage($picked, 'recomandate', 1, $limit);
    } catch (Throwable $e) {
        error_log('homeFeatured: ' . $e->getMessage());
        return [];
    }
}

/** Grila de categorii: grupele din meniu, fiecare cu subcategoriile ca text scurt. */
function renderHomeCategoryCards(): string
{
    $out = '';
    foreach (siteNavGroups() as $it) {
        $sub = implode(', ', array_map(fn ($c) => $c['name'], array_slice($it['children'], 0, 3)));
        $out .= '<a href="' . e($it['url']) . '" class="cat-card">'
              . '<div class="cat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg></div>'
              . '<div class="cat-name">' . e($it['name']) . '</div>'
              . '<div class="cat-sub">' . e($sub !== '' ? $sub : $it['count'] . ' produse') . '</div>'
              . '</a>';
    }
    foreach (catCollections() as $c) {
        $out .= '<a href="/' . e($c['slug']) . '.html" class="cat-card cat-card--collection">'
              . '<div class="cat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg></div>'
              . '<div class="cat-name">' . e($c['name']) . '</div>'
              . '<div class="cat-sub">' . (int) $c['count'] . ' produse</div>'
              . '</a>';
    }
    return $out;
}

/** Sidebar-ul primei pagini: acelasi arbore ca in liste + colectiile. */
function renderHomeSidebar(): string
{
    $out = '<div class="sidebar-head cat-tree-head"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg><span>Categorii produse</span></div><ul class="cat-tree">';
    foreach (catTreeForSidebar() as $node) { $out .= catTreeNode($node, []); }
    $out .= '</ul>';
    $cols = catCollections();
    if ($cols) {
        $out .= '<div class="sidebar-head"><span>Colecții</span></div><ul class="cat-tree">';
        foreach ($cols as $c) {
            $out .= '<li class="cat-tree-item cat-tree-item--d1"><a href="/' . e($c['slug']) . '.html">' . e($c['name'])
                  . ' <span class="cat-tree-n">' . (int) $c['count'] . '</span></a></li>';
        }
        $out .= '</ul>';
    }
    return $out;
}

/**
 * „Ce gasesti la Mugurel": fiecare grupa din meniu cu subcategoriile ei (ex. „Acoperiș: tablă tip țiglă, …").
 * Generata din catalog, ca textul de prezentare sa nu promita categorii care nu mai exista.
 */
function renderHomeOfferList(): string
{
    $out = '';
    foreach (siteNavGroups() as $g) {
        $names = $g['children'] ? array_column($g['children'], 'name')
               : (($g['target_name'] ?? $g['name']) !== $g['name'] ? [$g['target_name']] : []);
        $subs = array_map(fn ($n) => mb_strtolower(mb_substr($n, 0, 1)) . mb_substr($n, 1), $names);
        $out .= '<li><a href="' . e($g['url']) . '">' . e($g['name']) . '</a>' . ($subs ? ': ' . e(implode(', ', $subs)) : '') . '</li>';
    }
    return $out;
}
