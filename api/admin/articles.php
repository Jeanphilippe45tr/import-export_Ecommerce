<?php
declare(strict_types=1);

/** API admin : suppression d'un article et de ses dépendances. */

require_once __DIR__ . '/../../includes/config_bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

function ajError(int $code, string $message): never
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($_SESSION['admin_id'])) {
    ajError(403, 'Connexion admin requise.');
}

$db = db();
$action = $_GET['action'] ?? ($_POST['action'] ?? 'delete');
$csrf = $_POST['csrf_token'] ?? ($_GET['csrf_token'] ?? '');

if (!csrfCheck($csrf)) {
    ajError(403, 'Jeton CSRF invalide.');
}

if ($action !== 'delete') {
    ajError(400, 'Action inconnue.');
}

$id = (int)($_POST['id'] ?? ($_GET['id'] ?? 0));
if ($id <= 0) {
    ajError(400, 'Article invalide.');
}

$stmt = $db->prepare("SELECT id, reference, name FROM products WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$product = $stmt->fetch();
if ($product === false) {
    ajError(404, 'Article introuvable.');
}

$stmt = $db->prepare("SELECT id, image FROM product_images WHERE product_id = ?");
$stmt->execute([$id]);
$images = $stmt->fetchAll();

try {
    $db->beginTransaction();

    foreach ($images as $img) {
        $path = APP_DIR . '/' . ltrim((string)$img['image'], '/');
        if (is_file($path)) {
            @unlink($path);
        }
    }
    $db->prepare("DELETE FROM product_images WHERE product_id = ?")->execute([$id]);
    $db->prepare("DELETE FROM order_items WHERE product_id = ?")->execute([$id]);
    $db->prepare("DELETE FROM products WHERE id = ?")->execute([$id]);

    $db->commit();
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    $_SESSION['flash']['error'] = 'Suppression impossible : ' . $e->getMessage();
    header('Location: ' . url('admin/articles.php'));
    exit;
}

$_SESSION['flash']['success'] = 'Article « ' . $product['name'] . ' » supprimé.';

if (!empty($_GET['format']) && $_GET['format'] === 'json') {
    echo json_encode(['ok' => true, 'deleted' => $id], JSON_UNESCAPED_UNICODE);
    exit;
}

header('Location: ' . url('admin/articles.php'));
exit;
