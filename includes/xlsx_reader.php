<?php
declare(strict_types=1);

/**
 * Citeste o foaie dintr-un .xlsx ca lista de randuri [litera coloana => valoare].
 * Fara biblioteci: un .xlsx e o arhiva zip cu XML. $sheetName = null => prima foaie din fisier.
 */
function readXlsxSheet(string $path, ?string $sheetName): array
{
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        throw new RuntimeException("Nu pot deschide fisierul: $path");
    }
    try {
        $ns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
        $relNs = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

        $workbook = simplexml_load_string((string) $zip->getFromName('xl/workbook.xml'));
        $rels = simplexml_load_string((string) $zip->getFromName('xl/_rels/workbook.xml.rels'));
        if ($workbook === false || $rels === false) {
            throw new RuntimeException('Fisierul nu pare a fi un .xlsx valid.');
        }

        $target = null;
        foreach ($workbook->children($ns)->sheets->sheet as $sheet) {
            if ($target === null && ($sheetName === null || (string) $sheet->attributes()['name'] === $sheetName)) {
                $rid = (string) $sheet->attributes($relNs)['id'];
                foreach ($rels->Relationship as $rel) {
                    if ((string) $rel['Id'] === $rid) {
                        $target = 'xl/' . ltrim(preg_replace('#^/?xl/#', '', (string) $rel['Target']), '/');
                    }
                }
            }
        }
        if ($target === null) {
            throw new RuntimeException("Foaia „{$sheetName}\" nu exista in fisier.");
        }

        $shared = [];
        $sst = $zip->getFromName('xl/sharedStrings.xml');
        if ($sst !== false) {
            foreach (simplexml_load_string($sst)->children($ns)->si as $si) {
                // Text simplu (<t>) sau text formatat pe bucati (<r><t>).
                $shared[] = isset($si->t) ? (string) $si->t : implode('', array_map('strval', $si->xpath('.//*[local-name()="t"]')));
            }
        }

        $sheetXml = simplexml_load_string((string) $zip->getFromName($target));
        $rows = [];
        foreach ($sheetXml->children($ns)->sheetData->row as $row) {
            $cells = [];
            foreach ($row->c as $c) {
                $col = preg_replace('/\d+/', '', (string) $c->attributes()['r']);
                $type = (string) $c->attributes()['t'];
                if ($type === 's') {
                    $val = $shared[(int) $c->v] ?? '';
                } elseif ($type === 'inlineStr') {
                    $val = (string) $c->is->t;
                } else {
                    $val = isset($c->v) ? (string) $c->v : '';
                }
                $cells[$col] = $val;
            }
            $rows[(int) $row->attributes()['r']] = $cells;
        }
        return $rows;
    } finally {
        $zip->close();
    }
}
