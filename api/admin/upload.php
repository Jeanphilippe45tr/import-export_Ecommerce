<?php
declare(strict_types=1);

/**
 * API : upload et gestion des images produits (admin).
 * Actions : upload, delete_image, set_primary
 * CSRF vérifié, MIME vérifié, renommage, taille max.
 */

require_once __DIR__ . '/../../includes/config_bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['admin_id'])) {
    upError(403, 'Connexion admin requise.');
}

function upError(int $httpCode, string $message): never
{
    http_response_code($httpCode);
    echo json_encode(['ok' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

$db = db();
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

if ($action === 'upload') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!csrfCheck($csrf)) {
        upError(403, 'Jeton CSRF invalide.');
    }

    $productId = (int)($_POST['product_id'] ?? 0);
    if ($productId <= 0) {
        upError(400, 'Produit invalide.');
    }

    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        upError(400, 'Aucune image reçue.');
    }

    $file = $_FILES['image'];
    $maxSize = 8 * 1024 * 1024; // 8 Mo
    if ($file['size'] > $maxSize) {
        upError(400, 'Fichier trop volumineux (8 Mo max).');
    }

    // Vérification MIME réel via finfo
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    if (!isset($allowed[$mime])) {
        upError(400, 'Type de fichier non autorisé (JPEG, PNG, WebP, GIF).');
    }

    $ext = $allowed[$mime];
    $newName = 'product_' . $productId . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $destDir = IMAGES_DIR . '/products';
    if (!is_dir($destDir)) {
        @mkdir($destDir, 0755, true);
    }
    $destPath = $destDir . DIRECTORY_SEPARATOR . $newName;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        upError(500, 'Impossible d enregistrer le fichier.');
    }

    // Déterminer si c'est la première image → principale
    $stmt = $db->prepare("SELECT COUNT(*) AS nb FROM product_images WHERE product_id = ?");
    $stmt->execute([$productId]);
    $count = (int)$stmt->fetch()['nb'];
    $isPrimary = $count === 0 ? 1 : 0;

    // Si c'est la première, on retire le statut primaire des autres
    if ($isPrimary === 1) {
        $db->prepare("UPDATE product_images SET is_primary = 0 WHERE product_id = ?")->execute([$productId]);
    }

    $db->prepare(
        "INSERT INTO product_images (product_id, image, is_primary, sort_order, alt_text, created_at)
         VALUES (?, ?, ?, ?, ?, NOW())"
    )->execute([
        $productId,
        'uploads/images/products/' . $newName,
        $isPrimary,
        $count + 1,
        ''
    ]);

    echo json_encode([
        'ok' => true,
        'image' => 'uploads/images/products/' . $newName,
        'is_primary' => $isPrimary,
        'image_id' => (int)$db->lastInsertId(),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'delete_image') {
    $csrf = $_GET['csrf_token'] ?? ($_POST['csrf_token'] ?? '');
    if (!csrfCheck($csrf)) {
        upError(403, 'Jeton CSRF invalide.');
    }
    $imageId = (int)($_GET['id'] ?? 0);
    if ($imageId <= 0) {
        upError(400, 'Image invalide.');
    }

    $stmt = $db->prepare("SELECT image, product_id, is_primary FROM product_images WHERE id = ? LIMIT 1");
    $stmt->execute([$imageId]);
    $img = $stmt->fetch();
    if ($img === false) {
        upError(404, 'Image introuvable.');
    }

    // Supprimer le fichier
    $filePath = __DIR__ . '/../../' . $img['image'];
    if (file_exists($filePath)) {
        @unlink($filePath);
    }

    $db->prepare("DELETE FROM product_images WHERE id = ?")->execute([$imageId]);

    // Si c'était la principale, promouvoir une autre
    if ((int)$img['is_primary'] === 1) {
        $db->prepare("UPDATE product_images SET is_primary = 0 WHERE product_id = ?")->execute([$img['product_id']]);
        $stmt = $db->prepare("SELECT id FROM product_images WHERE product_id = ? ORDER BY sort_order ASC, id ASC LIMIT 1");
        $stmt->execute([$img['product_id']]);
        $next = $stmt->fetch();
        if ($next !== false) {
            $db->prepare("UPDATE product_images SET is_primary = 1 WHERE id = ?")->execute([(int)$next['id']]);
        }
    }

    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'set_primary') {
    $csrf = $_GET['csrf_token'] ?? ($_POST['csrf_token'] ?? '');
    if (!csrfCheck($csrf)) {
        upError(403, 'Jeton CSRF invalide.');
    }
    $imageId = (int)($_GET['id'] ?? 0);
    $productId = (int)($_GET['product_id'] ?? 0);
    if ($imageId <= 0 || $productId <= 0) {
        upError(400, 'Paramètres invalides.');
    }
    $db->prepare("UPDATE product_images SET is_primary = 0 WHERE product_id = ?")->execute([$productId]);
    $db->prepare("UPDATE product_images SET is_primary = 1 WHERE id = ? AND product_id = ?")->execute([$imageId, $productId]);
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

upError(400, 'Action inconnue.');
