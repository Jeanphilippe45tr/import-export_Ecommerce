<?php
declare(strict_types=1);

/**
 * API : gestion du panier (ajout, mise à jour quantité, suppression).
 * Le panier est stocké en session PHP.
 * Retourne du JSON : { ok: true, cart_count: int } ou { ok: false, message: ... }
 */

require_once __DIR__ . '/../includes/config_bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$response = ['ok' => false, 'message' => 'Requête invalide.'];

try {
    $action = $_POST['action'] ?? '';
    $csrf = $_POST['csrf_token'] ?? '';

    if (!csrfCheck($csrf)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'message' => 'Jeton CSRF invalide.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    if ($action === 'add') {
        $reference = trim($_POST['reference'] ?? '');
        $qty = max(1, (int)($_POST['qty'] ?? 1));

        if ($reference === '') {
            throw new Exception('Référence manquante.');
        }

        $db = db();
        $stmt = $db->prepare("SELECT id, reference, name, price, stock FROM products WHERE reference = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$reference]);
        $product = $stmt->fetch();

        if ($product === false) {
            throw new Exception('Article introuvable.');
        }

        $stock = (int)$product['stock'];
        $currentQty = isset($_SESSION['cart'][$reference]) ? (int)$_SESSION['cart'][$reference]['qty'] : 0;
        $newQty = $currentQty + $qty;

        if ($newQty > $stock && $stock >= 0) {
            throw new Exception('Stock insuffisant (restant : ' . $stock . ').');
        }

        $_SESSION['cart'][$reference] = [
            'reference' => $product['reference'],
            'name' => $product['name'],
            'price' => (float)$product['price'],
            'qty' => $newQty,
        ];

        $response = [
            'ok' => true,
            'cart_count' => array_sum(array_column($_SESSION['cart'], 'qty')),
            'message' => 'Article ajouté au panier.'
        ];
    } elseif ($action === 'update') {
        $reference = trim($_POST['reference'] ?? '');
        $qty = max(0, (int)($_POST['qty'] ?? 0));

        if (!isset($_SESSION['cart'][$reference])) {
            throw new Exception('Article non présent dans le panier.');
        }

        if ($qty === 0) {
            unset($_SESSION['cart'][$reference]);
        } else {
            $db = db();
            $stmt = $db->prepare("SELECT stock FROM products WHERE reference = ? AND is_active = 1 LIMIT 1");
            $stmt->execute([$reference]);
            $row = $stmt->fetch();
            $stock = $row !== false ? (int)$row['stock'] : 0;
            if ($qty > $stock && $stock >= 0) {
                throw new Exception('Stock insuffisant.');
            }
            $_SESSION['cart'][$reference]['qty'] = $qty;
        }

        $response = [
            'ok' => true,
            'cart_count' => array_sum(array_column($_SESSION['cart'], 'qty')),
            'message' => 'Panier mis à jour.'
        ];
    } elseif ($action === 'remove') {
        $reference = trim($_POST['reference'] ?? '');
        unset($_SESSION['cart'][$reference]);

        $response = [
            'ok' => true,
            'cart_count' => array_sum(array_column($_SESSION['cart'], 'qty')),
            'message' => 'Article retiré du panier.'
        ];
    } else {
        $response['message'] = 'Action inconnue.';
    }

    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;

} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}
