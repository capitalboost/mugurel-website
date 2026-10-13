-- Fixturi de test pentru cos — DOAR baza LOCALA. Nu se ruleaza niciodata pe productie.
--
-- Estimarile de pret importate local sunt toate „de la", deci niciun produs n-ar avea buton de cos.
-- Aici marcam cateva produse ca avand pret FIX, ca sa putem testa fluxul cos → comanda.
-- Rulare: mysql -u root -h 127.0.0.1 mugurel_cms < dev/fixtures/cos_test.sql  (idempotent)

-- Pret fix, in stoc: apar cu buton de cos
UPDATE products SET price_is_from = 0, stock_status = 'in_stoc'      WHERE id IN (2, 3470, 3471, 3678, 10447, 10482);
-- Pret fix, la comanda: buton de cos prezent, eticheta „La comanda"
UPDATE products SET price_is_from = 0, stock_status = 'la_comanda'   WHERE id = 3682;
-- Pret fix, dar indisponibil: FARA buton de cos
UPDATE products SET price_is_from = 0, stock_status = 'indisponibil' WHERE id = 3686;
-- Pret fix cu zecimale si unitate la metru, pentru calculul totalurilor
UPDATE products SET price = 12.49, price_unit = 'ml', price_is_from = 0, stock_status = 'in_stoc' WHERE id = 3674;
