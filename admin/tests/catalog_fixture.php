<?php
/**
 * Produsele de test din catalogul nou, dupa rol (vezi dev/fixtures/cos_test.sql — acolo li se fixeaza pretul si stocul).
 * fx('A') => id-ul produsului cu codul de bare al rolului A. Id-urile se schimba la reimport, codurile de bare nu.
 */
require_once __DIR__ . '/../models/Database.php';

const FX_BARCODES = [
    'A'   => '5945762000019',  // Adeziv Adeplast AFI 25 kg — 25,24 lei / sac
    'B'   => '5946043001367',  // Adeziv Ceresit CM 11 25 kg — 56,83 lei / sac
    'KG'  => '1000000023680',  // Platbanda zincata 25 x 4 mm — 22,43 lei / kg
    'EL'  => '5901890030199',  // Aspirator Teesa Eco White 700 — 373,31 lei / bucata
    'LA'  => '5946043002111',  // la comanda
    'IND' => '5948598045902',  // indisponibil
    'NP'  => '11000000004530', // fara pret
];

function fx(string $role): int
{
    static $ids = [];
    if (!isset($ids[$role])) {
        $stmt = Database::get()->prepare('SELECT id FROM products WHERE barcode = ?');
        $stmt->execute([FX_BARCODES[$role]]);
        $id = $stmt->fetchColumn();
        if ($id === false) {
            fwrite(STDERR, "Produsul de test $role (" . FX_BARCODES[$role] . ") lipseste din baza locala — importa catalogul nou.\n");
            exit(1);
        }
        $ids[$role] = (int) $id;
    }
    return $ids[$role];
}
