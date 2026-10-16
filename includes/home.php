<?php
/**
 * Prima pagina: sectiunile generate din catalog (sidebar-ul de categorii, grila de categorii, produse recomandate).
 * Inlocuieste cardurile scrise de mana + js/produse-acasa.js din catalogul vechi.
 */
require_once __DIR__ . '/catalog_page.php';
require_once __DIR__ . '/site_nav.php';

/**
 * Produsele recomandate: cate unul din fiecare grupa a meniului (pret fix, verificate intai), apoi completam.
 * Ordinea e stabila (aceleasi produse la fiecare incarcare), ca pagina sa poata fi pusa in cache.
 */
function homeFeatured(int $limit = 6): array
{
    try {
        $pdo = Database::get();
        $picked = [];
        foreach (siteNavItems() as $it) {
            $ids = catDescendantIds($it['id']);
            $id = $pdo->query('SELECT id FROM products WHERE is_visible = 1 AND barcode IS NOT NULL AND price > 0
                               AND stock_status <> \'indisponibil\' AND category_id IN (' . implode(',', array_map('intval', $ids)) . ")
                               ORDER BY verify_status = 'ok' DESC, sort_order LIMIT 1")->fetchColumn();
            if ($id !== false) { $picked[] = (int) $id; }
            if (count($picked) >= $limit) { break; }
        }
        if (count($picked) < $limit) {
            $more = $pdo->query('SELECT id FROM products WHERE is_visible = 1 AND barcode IS NOT NULL AND price > 0'
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
    foreach (siteNavItems() as $it) {
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
    $out = '<div class="sidebar-head"><span>Categorii produse</span></div><ul class="cat-tree">';
    foreach (catTreeForSidebar() as $node) { $out .= catTreeNode($node, []); }
    $out .= '</ul>';
    $cols = catCollections();
    if ($cols) {
        $out .= '<div class="sidebar-head"><span>Colectii</span></div><ul class="cat-tree">';
        foreach ($cols as $c) {
            $out .= '<li class="cat-tree-item cat-tree-item--d1"><a href="/' . e($c['slug']) . '.html">' . e($c['name'])
                  . ' <span class="cat-tree-n">' . (int) $c['count'] . '</span></a></li>';
        }
        $out .= '</ul>';
    }
    return $out;
}
