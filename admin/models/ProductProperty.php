<?php
class ProductProperty {
    /** Blocurile unui produs, in ordinea de afisare. */
    public static function forProduct(int $productId): array {
        $stmt = Database::get()->prepare(
            'SELECT id, product_id, sort_order, label, body, is_list
             FROM product_properties WHERE product_id = ? ORDER BY sort_order'
        );
        $stmt->execute([$productId]);
        return $stmt->fetchAll();
    }

    /**
     * Inlocuieste toate blocurile unui produs cu cele din $blocks (sterge + reinsereaza,
     * in tranzactie — acelasi tipar ca importul). sort_order se deduce din ordinea din array.
     * Fiecare bloc: ['label' => string, 'body' => string, 'is_list' => bool|int].
     */
    public static function replaceAll(int $productId, array $blocks): void {
        $db = Database::get();
        $db->beginTransaction();
        try {
            $db->prepare('DELETE FROM product_properties WHERE product_id = ?')->execute([$productId]);
            if ($blocks) {
                $insert = $db->prepare(
                    'INSERT INTO product_properties (product_id, sort_order, label, body, is_list)
                     VALUES (?, ?, ?, ?, ?)'
                );
                $order = 1;
                foreach ($blocks as $block) {
                    $insert->execute([
                        $productId,
                        $order++,
                        $block['label'],
                        $block['body'],
                        !empty($block['is_list']) ? 1 : 0,
                    ]);
                }
            }
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }
}
