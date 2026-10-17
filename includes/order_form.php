<?php
/**
 * Formularul de comanda: campurile, normalizarea si validarea lor.
 *
 * Regulile stau DOAR aici; browserul face o verificare de baza (required, maxlength), dar
 * serverul decide. orderFormValidate() intoarce datele curatate si erorile pe camp, ca
 * formularul sa poata fi reafisat cu valorile introduse si mesajele langa fiecare camp.
 */

const ORDER_JUDETE = [
    'Alba', 'Arad', 'Argeș', 'Bacău', 'Bihor', 'Bistrita-Nasaud', 'Botoșani', 'Brăila', 'Brașov',
    'București', 'Buzău', 'Călărași', 'Caraș-Severin', 'Cluj', 'Constanța', 'Covasna', 'Dâmbovița',
    'Dolj', 'Galați', 'Giurgiu', 'Gorj', 'Harghita', 'Hunedoara', 'Ialomița', 'Iași', 'Ilfov',
    'Maramureș', 'Mehedinți', 'Mureș', 'Neamț', 'Olt', 'Prahova', 'Sălaj', 'Satu Mare', 'Sibiu',
    'Suceava', 'Teleorman', 'Timiș', 'Tulcea', 'Vâlcea', 'Vaslui', 'Vrancea',
];

/** Judetele in care livram (decizia din 16 oct: doar Dolj). Ridicarea din magazin e posibila de oriunde. */
const ORDER_DELIVERY_JUDETE = ['Dolj'];
const ORDER_DELIVERY_NOTE = 'Livrăm doar în județul Dolj. Pentru alt județ alege „Ridicare din magazin" sau sună-ne la 0749 130 565.';

/** Lungimile maxime (si in HTML, ca maxlength). */
const ORDER_MAX = [
    'nume' => 100, 'telefon' => 20, 'email' => 254, 'judet' => 40,
    'localitate' => 80, 'adresa' => 200, 'mentiuni' => 1000,
    // persoana juridica (T8)
    'firma' => 150, 'cui' => 16, 'reg_com' => 24, 'sediu' => 200,
];

/**
 * Metodele de plata (decizia din 13 oct: fara card online). Textele apar pe pagina de comanda
 * si in emailuri — de confirmat cu Mugurel inainte de lansare.
 */
const ORDER_PLATA = [
    'ramburs'  => ['label' => 'Ramburs la livrare',
                   'info'  => 'Plătești numerar la primirea mărfii.'],
    'transfer' => ['label' => 'Transfer bancar (ordin de plată)',
                   'info'  => 'Îți trimitem pe email factura proformă cu datele de plată, după ce confirmăm comanda.'],
    'ridicare' => ['label' => 'Ridicare și plată în magazin',
                   'info'  => 'Plătești numerar sau cu cardul când ridici marfa: Strada Caracal 66, Dăbuleni, L–V 8:00–18:00. Te anunțăm când comanda e pregătită.'],
];

/** Mesajul despre transport (acelasi peste tot). */
const ORDER_TRANSPORT_INFO = 'Livrăm în județul Dolj. Transportul nu este inclus în total — costul ți-l spunem la telefon, când confirmăm comanda.';

/** Tipurile de client acceptate. */
const ORDER_TIPURI = ['pf' => 'Persoană fizică', 'pj' => 'Persoană juridică'];

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
 * CUI romanesc (cu sau fara „RO") cu cifra de control corecta => doar cifrele, altfel null.
 * Algoritmul oficial: cheia 753217532 aplicata primelor cifre (completate la stanga cu 0 pana la 9),
 * suma × 10 mod 11 (10 => 0) trebuie sa fie egala cu ultima cifra.
 */
function orderCui(string $raw): ?string
{
    $c = preg_replace('/^RO/i', '', preg_replace('/[\s.\-]/', '', $raw));
    if (!preg_match('/^\d{2,10}$/', (string) $c)) {
        return null;
    }
    $body = str_pad(substr($c, 0, -1), 9, '0', STR_PAD_LEFT);
    $key = '753217532';
    $sum = 0;
    for ($i = 0; $i < 9; $i++) {
        $sum += (int) $body[$i] * (int) $key[$i];
    }
    $control = ($sum * 10) % 11;
    return ($control === 10 ? 0 : $control) === (int) substr($c, -1) ? $c : null;
}

/**
 * Numarul de inregistrare la Registrul Comertului => forma canonica, sau null.
 * Accepta formatul clasic (J16/123/2020, F…, C…) si formatul unic din 2024 (J2024000123168).
 */
function orderRegCom(string $raw): ?string
{
    $r = strtoupper(preg_replace('/\s+/', '', $raw));
    if (preg_match('#^([JFC])(\d{1,2})/(\d{1,7})/((?:19|20)\d{2})$#', $r, $m)) {
        return $m[1] . str_pad($m[2], 2, '0', STR_PAD_LEFT) . '/' . ltrim($m[3], '0') . '/' . $m[4];
    }
    if (preg_match('#^[JFC](?:19|20)\d{2}\d{6,10}$#', $r)) {
        return $r;
    }
    return null;
}

/**
 * Valideaza datele unei comenzi (persoana fizica sau juridica).
 * @return array{data: array<string,string>, errors: array<string,string>}
 */
function orderFormValidate(array $in): array
{
    $d = [
        'tip'        => is_string($in['tip'] ?? null) && isset(ORDER_TIPURI[$in['tip']]) ? $in['tip'] : 'pf',
        'nume'       => orderText($in['nume'] ?? null, ORDER_MAX['nume']),
        'telefon'    => orderText($in['telefon'] ?? null, ORDER_MAX['telefon']),
        'email'      => mb_strtolower(orderText($in['email'] ?? null, ORDER_MAX['email'])),
        'judet'      => orderText($in['judet'] ?? null, ORDER_MAX['judet']),
        'localitate' => orderText($in['localitate'] ?? null, ORDER_MAX['localitate']),
        'adresa'     => orderText($in['adresa'] ?? null, ORDER_MAX['adresa']),
        'mentiuni'   => orderText($in['mentiuni'] ?? null, ORDER_MAX['mentiuni'], true),
        'plata'      => is_string($in['plata'] ?? null) && isset(ORDER_PLATA[$in['plata']]) ? $in['plata'] : '',
        'acord'      => ($in['acord'] ?? null) === '1' ? '1' : '',
        'firma'      => '',
        'cui'        => '',
        'reg_com'    => '',
        'sediu'      => '',
    ];
    // La ridicarea din magazin nu exista livrare, deci nici adresa de livrare.
    if ($d['plata'] === 'ridicare') {
        $d['judet'] = $d['localitate'] = $d['adresa'] = '';
    }
    // Campurile de firma conteaza doar la persoana juridica; la PF sunt ignorate complet.
    if ($d['tip'] === 'pj') {
        foreach (['firma', 'cui', 'reg_com', 'sediu'] as $k) {
            $d[$k] = orderText($in[$k] ?? null, ORDER_MAX[$k]);
        }
    }
    $e = [];

    if (mb_strlen($d['nume']) < 3) {
        $e['nume'] = $d['tip'] === 'pj' ? 'Scrie numele persoanei de contact.' : 'Scrie numele și prenumele.';
    } elseif (!preg_match("/^[\p{L}][\p{L} .'\-]*$/u", $d['nume'])) {
        $e['nume'] = 'Numele poate conține doar litere, spații, cratimă și apostrof.';
    }

    if ($d['telefon'] === '') {
        $e['telefon'] = 'Scrie un număr de telefon — te sunăm ca să confirmăm comanda.';
    } elseif (($phone = orderPhone($d['telefon'])) === null) {
        $e['telefon'] = 'Numărul de telefon nu pare corect (ex. 0749 130 565).';
    } else {
        $d['telefon'] = $phone;
    }

    if ($d['email'] === '') {
        $e['email'] = 'Scrie adresa de email — îți trimitem confirmarea comenzii.';
    } elseif (!filter_var($d['email'], FILTER_VALIDATE_EMAIL) || !preg_match('/\.[a-z]{2,}$/', $d['email'])) {
        $e['email'] = 'Adresa de email nu pare corectă.';
    }

    if ($d['plata'] === '') {
        $e['plata'] = 'Alege cum vrei să plătești.';
    }
    if ($d['plata'] !== 'ridicare') {
        if (!in_array($d['judet'], ORDER_JUDETE, true)) {
            $e['judet'] = 'Alege județul.';
        } elseif (!in_array($d['judet'], ORDER_DELIVERY_JUDETE, true)) {
            $e['judet'] = ORDER_DELIVERY_NOTE;
        }
        if (mb_strlen($d['localitate']) < 2) {
            $e['localitate'] = 'Scrie localitatea.';
        }
        if (mb_strlen($d['adresa']) < 5) {
            $e['adresa'] = 'Scrie adresa de livrare (strada, număr, bloc, apartament).';
        }
    }

    if ($d['acord'] !== '1') {
        $e['acord'] = 'Ca să trimiți comanda, trebuie să accepți Termenii și Politica de confidențialitate.';
    }

    if ($d['tip'] === 'pj') {
        if (mb_strlen($d['firma']) < 2) {
            $e['firma'] = 'Scrie denumirea firmei.';
        }
        if ($d['cui'] === '') {
            $e['cui'] = 'Scrie codul fiscal (CUI) al firmei.';
        } elseif (($cui = orderCui($d['cui'])) === null) {
            $e['cui'] = 'Codul fiscal nu este valid — verifică cifrele (ex. RO14399840).';
        } else {
            // Pastram prefixul RO daca a fost scris: arata ca firma e platitoare de TVA.
            $d['cui'] = (stripos(ltrim($d['cui']), 'RO') === 0 ? 'RO' : '') . $cui;
        }
        if ($d['reg_com'] === '') {
            $e['reg_com'] = 'Scrie numărul de la Registrul Comerțului.';
        } elseif (($rc = orderRegCom($d['reg_com'])) === null) {
            $e['reg_com'] = 'Numărul de la Registrul Comerțului nu pare corect (ex. J16/123/2020).';
        } else {
            $d['reg_com'] = $rc;
        }
        if (mb_strlen($d['sediu']) < 5) {
            $e['sediu'] = 'Scrie adresa sediului social.';
        }
    }

    return ['data' => $d, 'errors' => $e];
}

/** Atributele de eroare ale unui camp + mesajul afisat sub el. */
function fieldError(array $errors, string $name): array
{
    if (!isset($errors[$name])) {
        return ['', ''];
    }
    $id = 'err-' . $name;
    return [
        ' aria-invalid="true" aria-describedby="' . $id . '"',
        '<p class="form-error" id="' . $id . '">' . htmlspecialchars($errors[$name], ENT_QUOTES, 'UTF-8') . '</p>',
    ];
}
