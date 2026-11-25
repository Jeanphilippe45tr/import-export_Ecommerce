<?php
declare(strict_types=1);

/**
 * API : suggestions de recherche en direct (AJAX).
 * Retourne du JSON : [{reference, name, url, image, price}]
 * Aucune authentification requise (lecture publique du catalogue).
 */

require_once __DIR__ . '/../includes/config_bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $db = db();
    $q = trim($_GET['q'] ?? '');
    $limit = min((int)($_GET['limit'] ?? 8), 20);

    $results = [];
    if ($q !== '' && strlen($q) >= 2) {
        $like = '%' . $q . '%';
        $stmt = $db->prepare(
            "SELECT p.reference, p.name, p.price, c.name AS category_name
             FROM products p
             LEFT JOIN categories c ON p.category_id = c.id
             WHERE p.is_active = 1 AND (p.name LIKE ? OR p.reference LIKE ?)
             ORDER BY p.is_featured DESC, p.rating DESC, p.created_at DESC
             LIMIT ?"
        );
        $stmt->bindValue(1, $like, PDO::PARAM_STR);
        $stmt->bindValue(2, $like, PDO::PARAM_STR);
        $stmt->bindValue(3, $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        $currency = detectCurrency($db);
        foreach ($rows as $row) {
            $results[] = [
                'reference' => $row['reference'],
                'name' => $row['name'],
                'url' => APP_URL . '/produit.php?slug=' . urlencode($row['reference']),
                'image' => APP_URL . '/uploads/images/products/' . strtolower($row['reference']) . '-1.jpg',
                'price' => number_format(convertAmount((float)$row['price'], ['code' => 'EUR', 'exchange_rate' => 1.000000], $currency), 2, ',', ' ') . ' ' . $currency['symbol']
            ];
        }
    }

    echo json_encode(['results' => $results], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur serveur'], JSON_UNESCAPED_UNICODE);
    exit;
}
