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
 * (aceeasi sectiune, doua stiluri de card).
 *
 * Citeste din pageProductsIndexed($page), care aduce toata pagina intr-o
 * singura interogare (plus attachProperties()) la primul apel si o tine in
 * memorie pentru restul sectiunilor aceleiasi pagini — vezi products_repo.php.
 * $limit/$offset/$modifier se aplica in PHP, pe subsetul deja incarcat.
 */
function section(string $page, string $key, string $kind = 'material', ?int $limit = null, int $offset = 0, ?string $modifier = null): string {
    $rows = pageProductsIndexed($page)[$key][$kind] ?? [];

    if ($modifier === '') {
        $rows = array_values(array_filter($rows, fn($r) => $r['card_modifier'] === null));
    } elseif ($modifier !== null) {
        $rows = array_values(array_filter($rows, fn($r) => $r['card_modifier'] === $modifier));
    }
    if ($limit !== null) {
        $rows = array_slice($rows, max(0, $offset), max(0, $limit));
    }

    if (!$rows) { return renderEmptyNotice(); }
    return $kind === 'accessory' ? renderAccessoryCards($rows) : renderMaterialCards($rows);
}
