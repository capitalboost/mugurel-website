-- Fixturi de test pentru cos si comenzi — DOAR baza LOCALA. Nu se ruleaza niciodata pe productie.
--
-- Catalogul nou (16 oct): produsele de test se aleg dupa CODUL DE BARE (id-urile se schimba la reimport).
-- Rolurile (aceleasi in admin/tests/catalog_fixture.php si in dev/e2e/tests/fixtures.js):
--   A    5945762000019  Adeziv Adeplast AFI 25 kg      25,24 lei / sac      in stoc
--   B    5946043001367  Adeziv Ceresit CM 11 25 kg     56,83 lei / sac      in stoc
--   KG   1000000023680  Platbanda zincata 25 x 4 mm    22,43 lei / kg       in stoc (cantitati cu zecimale)
--   EL   5901890030199  Aspirator Teesa Eco White 700  373,31 lei / bucata  in stoc
--   LA   5946043002111  Adeziv Ceresit CM 11 5 kg      21,31 lei / sac      LA COMANDA (buton de cos + eticheta)
--   IND  5948598045902  Adeziv Ceresit CM 11 Alb       73,93 lei / sac      INDISPONIBIL (fara buton de cos)
--   NP   11000000004530 Profil IPE 240                 fara pret            „Pret la cerere" (fara cos)
-- Preturile se fixeaza aici ca testele sa nu depinda de un reimport. Rulare: dev/e2e/run.sh si dev/run_tests.sh (idempotent).

UPDATE products SET price = 25.24,  price_unit = 'sac',    price_is_from = 0, stock_status = 'in_stoc',      is_visible = 1 WHERE barcode = '5945762000019';
UPDATE products SET price = 56.83,  price_unit = 'sac',    price_is_from = 0, stock_status = 'in_stoc',      is_visible = 1 WHERE barcode = '5946043001367';
UPDATE products SET price = 22.43,  price_unit = 'kg',     price_is_from = 0, stock_status = 'in_stoc',      is_visible = 1 WHERE barcode = '1000000023680';
UPDATE products SET price = 373.31, price_unit = 'bucată', price_is_from = 0, stock_status = 'in_stoc',      is_visible = 1 WHERE barcode = '5901890030199';
UPDATE products SET price = 21.31,  price_unit = 'sac',    price_is_from = 0, stock_status = 'la_comanda',   is_visible = 1 WHERE barcode = '5946043002111';
UPDATE products SET price = 73.93,  price_unit = 'sac',    price_is_from = 0, stock_status = 'indisponibil', is_visible = 1 WHERE barcode = '5948598045902';
UPDATE products SET price = NULL,                          price_is_from = 0, stock_status = 'in_stoc',      is_visible = 1 WHERE barcode = '11000000004530';

-- Comenzile create de testele automate (adresele de email folosite doar in teste).
DELETE FROM orders WHERE email IN ('ion@exemplu.ro', 'achizitii@firma.ro', 'test@exemplu.ro', 'http@exemplu.ro', 'mailtest@exemplu.ro') OR email LIKE '%-flux-%@exemplu.ro';
-- Cererile de retur create de testele automate.
DELETE FROM return_requests WHERE email LIKE 'retur-%@exemplu.ro';
