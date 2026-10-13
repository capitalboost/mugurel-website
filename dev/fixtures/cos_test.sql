-- Fixturi de test pentru cos — DOAR baza LOCALA. Nu se ruleaza niciodata pe productie.
--
-- Din 13 oct toate produsele au pret fix (decizia: fara „de la"), deci toate se pot pune in cos.
-- Aici fixam doar cateva stari speciale, ca testele sa le poata verifica.
-- Rulare: mysql -u root -h 127.0.0.1 mugurel_cms < dev/fixtures/cos_test.sql  (idempotent; dev/e2e/run.sh il aplica)

-- Produsele folosite de teste: pret fix, in stoc
UPDATE products SET price_is_from = 0, stock_status = 'in_stoc'      WHERE id IN (1, 2, 3470, 3471, 3678, 10447, 10482);
-- La comanda: buton de cos prezent, eticheta „La comanda"
UPDATE products SET price_is_from = 0, stock_status = 'la_comanda'   WHERE id = 3682;
-- Indisponibil: FARA buton de cos
UPDATE products SET price_is_from = 0, stock_status = 'indisponibil' WHERE id = 3686;
-- Pret cu zecimale si unitate la metru, pentru calculul totalurilor (12,49 lei/ml)
UPDATE products SET price = 12.49, price_unit = 'ml', price_is_from = 0, stock_status = 'in_stoc' WHERE id = 3674;

-- Comenzile create de testele automate (adresele de email folosite doar in teste).
DELETE FROM orders WHERE email IN ('ion@exemplu.ro', 'achizitii@firma.ro', 'test@exemplu.ro', 'http@exemplu.ro') OR email LIKE '%-flux-%@exemplu.ro';
