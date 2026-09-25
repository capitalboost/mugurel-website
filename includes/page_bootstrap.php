<?php
/**
 * Prologul paginilor de categorie. Leaga repository-ul de randare, ca paginile
 * sa contina un singur apel per grila.
 *
 * Sta separat de product_grid.php ca acela sa ramana pur de randare, fara SQL.
 */
require_once __DIR__ . '/products_repo.php';
require_once __DIR__ . '/product_grid.php';

/** Randeaza o sectiune, sau mesajul de indisponibilitate daca lista e goala. */
function section(string $page, string $key, string $kind = 'material'): string {
    $rows = productsForSection($page, $key, $kind);
    if (!$rows) { return renderEmptyNotice(); }
    return $kind === 'accessory' ? renderAccessoryCards($rows) : renderMaterialCards($rows);
}
