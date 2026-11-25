<?php
declare(strict_types=1);

/**
 * API : changement de statut d'une commande admin.
 */

require_once __DIR__ . '/../../includes/config_bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

function apiError(int $httpCode, string $message): never
{
    http_response_code($httpCode);
    echo json_encode(['ok' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($_SESSION['admin_id'])) {
    apiError(403, 'Connexion admin requise.');
}

$db = db();
$action = $_GET['action'] ?? '';

$csrf = $_POST['csrf_token'] ?? ($_GET['csrf_token'] ?? '');
if (in_array($action, ['status_update', 'delete'], true) && !csrfCheck($csrf)) {
    apiError(403, 'Jeton CSRF invalide.');
}

try {
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? ($_GET['id'] ?? 0));
        if ($id <= 0) {
            apiError(400, 'Commande invalide.');
        }
        $stmt = $db->prepare("SELECT id, order_number FROM orders WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $order = $stmt->fetch();
        if ($order === false) {
            apiError(404, 'Commande introuvable.');
        }

        $db->beginTransaction();
        $db->prepare("DELETE FROM credit_notes WHERE invoice_id IN (SELECT id FROM invoices WHERE order_id = ?)")->execute([$id]);
        $db->prepare("DELETE FROM invoice_items WHERE invoice_id IN (SELECT id FROM invoices WHERE order_id = ?)")->execute([$id]);
        $db->prepare("DELETE FROM invoices WHERE order_id = ?")->execute([$id]);
        $db->prepare("DELETE FROM order_items WHERE order_id = ?")->execute([$id]);
        $db->prepare("DELETE FROM orders WHERE id = ?")->execute([$id]);
        $db->commit();

        $_SESSION['flash']['success'] = 'Commande « ' . $order['order_number'] . ' » supprimée.';
        header('Location: ' . url('admin/commandes.php'));
        exit;
    }

    if ($action === 'status_update') {
        $id = (int)($_POST['id'] ?? 0);
        $status = $_POST['status'] ?? 'nouvelle';
        $carrier = trim($_POST['carrier'] ?? '');
        $tracking = trim($_POST['tracking'] ?? '');

        $allowed = ['nouvelle', 'confirmée', 'expédiée', 'livrée', 'annulée'];
        if (!in_array($status, $allowed, true)) {
            apiError(400, 'Statut invalide.');
        }

        $stmt = $db->prepare("SELECT id FROM orders WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        if ($stmt->fetch() === false) {
            apiError(404, 'Commande introuvable.');
        }

        $db->prepare(
            "UPDATE orders SET status = ?, tracking_carrier = ?, tracking_number = ?, updated_at = NOW() WHERE id = ?"
        )->execute([$status, $carrier ?: null, $tracking ?: null, $id]);

        echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }

    apiError(400, 'Action inconnue.');
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}
