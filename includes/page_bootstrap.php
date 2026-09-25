<?php
/**
 * Prologul paginilor de categorie. Leaga repository-ul de randare, ca paginile
 * sa contina un singur apel per grila.
 *
 * Sta separat de product_grid.php ca acela sa ramana pur de randare, fara SQL.
 */
require_once __DIR__ . '/products_repo.php';
require_once __DIR__ . '/product_grid.php';

/**
 * Randeaza o sectiune, sau mesajul de indisponibilitate daca lista e goala.
 *
 * $limit/$offset si $modifier exista doar pentru cazul rar in care o singura
 * cheie de sectiune din baza acopera doua grile HTML distincte in pagina
 * (aceeasi sectiune, doua stiluri de card). Vezi productsForSection().
 */
function section(string $page, string $key, string $kind = 'material', ?int $limit = null, int $offset = 0, ?string $modifier = null): string {
    $rows = productsForSection($page, $key, $kind, $limit, $offset, $modifier);
    if (!$rows) { return renderEmptyNotice(); }
    return $kind === 'accessory' ? renderAccessoryCards($rows) : renderMaterialCards($rows);
}
