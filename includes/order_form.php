<?php
/**
 * Formularul de comanda: campurile, normalizarea si validarea lor.
 *
 * Regulile stau DOAR aici; browserul face o verificare de baza (required, maxlength), dar
 * serverul decide. orderFormValidate() intoarce datele curatate si erorile pe camp, ca
 * formularul sa poata fi reafisat cu valorile introduse si mesajele langa fiecare camp.
 */

const ORDER_JUDETE = [
    'Alba', 'Arad', 'Arges', 'Bacau', 'Bihor', 'Bistrita-Nasaud', 'Botosani', 'Braila', 'Brasov',
    'Bucuresti', 'Buzau', 'Calarasi', 'Caras-Severin', 'Cluj', 'Constanta', 'Covasna', 'Dambovita',
    'Dolj', 'Galati', 'Giurgiu', 'Gorj', 'Harghita', 'Hunedoara', 'Ialomita', 'Iasi', 'Ilfov',
    'Maramures', 'Mehedinti', 'Mures', 'Neamt', 'Olt', 'Prahova', 'Salaj', 'Satu Mare', 'Sibiu',
    'Suceava', 'Teleorman', 'Timis', 'Tulcea', 'Valcea', 'Vaslui', 'Vrancea',
];

/** Lungimile maxime (si in HTML, ca maxlength). */
const ORDER_MAX = [
    'nume' => 100, 'telefon' => 20, 'email' => 254, 'judet' => 40,
    'localitate' => 80, 'adresa' => 200, 'mentiuni' => 1000,
];

/** Text dintr-un camp: string, fara caractere de control, spatii comprimate, taiat la limita. */
function orderText(mixed $v, int $max, bool $multiline = false): string
{
    if (!is_string($v)) {
        return '';
    }
    $v = $multiline
        ? preg_replace('/[^\P{C}\n]+/u', '', str_replace("\r\n", "\n", $v))
        : preg_replace('/\p{C}+/u', ' ', $v);
    $v = $multiline ? preg_replace("/[ \t]+/u", ' ', (string) $v) : preg_replace('/\s+/u', ' ', (string) $v);
    $v = trim((string) $v);
    return mb_substr($v, 0, $max);
}

/**
 * Telefon romanesc => format +40XXXXXXXXX, sau null daca nu e valid.
 * Accepta 07xx xxx xxx, +40 7xx..., 0040..., cu spatii, puncte, cratime sau paranteze.
 * Fix (02x/03x) si mobil (07x).
 */
function orderPhone(string $raw): ?string
{
    $d = preg_replace('/[\s.\-()\/]/', '', $raw);
    if (preg_match('/^(?:\+40|0040|40)?0?([237]\d{8})$/', (string) $d, $m)) {
        return '+40' . $m[1];
    }
    return null;
}

/**
 * Valideaza datele unei comenzi de persoana fizica.
 * @return array{data: array<string,string>, errors: array<string,string>}
 */
function orderFormValidate(array $in): array
{
    $d = [
        'nume'       => orderText($in['nume'] ?? null, ORDER_MAX['nume']),
        'telefon'    => orderText($in['telefon'] ?? null, ORDER_MAX['telefon']),
        'email'      => mb_strtolower(orderText($in['email'] ?? null, ORDER_MAX['email'])),
        'judet'      => orderText($in['judet'] ?? null, ORDER_MAX['judet']),
        'localitate' => orderText($in['localitate'] ?? null, ORDER_MAX['localitate']),
        'adresa'     => orderText($in['adresa'] ?? null, ORDER_MAX['adresa']),
        'mentiuni'   => orderText($in['mentiuni'] ?? null, ORDER_MAX['mentiuni'], true),
    ];
    $e = [];

    if (mb_strlen($d['nume']) < 3) {
        $e['nume'] = 'Scrie numele si prenumele.';
    } elseif (!preg_match("/^[\p{L}][\p{L} .'\-]*$/u", $d['nume'])) {
        $e['nume'] = 'Numele poate contine doar litere, spatii, cratima si apostrof.';
    }

    if ($d['telefon'] === '') {
        $e['telefon'] = 'Scrie un numar de telefon — te sunam ca sa confirmam comanda.';
    } elseif (($phone = orderPhone($d['telefon'])) === null) {
        $e['telefon'] = 'Numarul de telefon nu pare corect (ex. 0749 130 565).';
    } else {
        $d['telefon'] = $phone;
    }

    if ($d['email'] === '') {
        $e['email'] = 'Scrie adresa de email — iti trimitem confirmarea comenzii.';
    } elseif (!filter_var($d['email'], FILTER_VALIDATE_EMAIL) || !preg_match('/\.[a-z]{2,}$/', $d['email'])) {
        $e['email'] = 'Adresa de email nu pare corecta.';
    }

    if (!in_array($d['judet'], ORDER_JUDETE, true)) {
        $e['judet'] = 'Alege judetul.';
    }
    if (mb_strlen($d['localitate']) < 2) {
        $e['localitate'] = 'Scrie localitatea.';
    }
    if (mb_strlen($d['adresa']) < 5) {
        $e['adresa'] = 'Scrie adresa de livrare (strada, numar, bloc, apartament).';
    }

    return ['data' => $d, 'errors' => $e];
}
