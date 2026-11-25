<?php
declare(strict_types=1);

/**
 * API : gestion livraison (zones, tarifs, pays rattachés).
 * CSRF vérifié, requêtes préparées PDO.
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
$action = $_GET['action'] ?? $_POST['action'] ?? '';

if (!in_array($action, ['zone_save','zone_delete','rate_save','rate_delete','zone_rates','zone_countries','zone_countries_save'], true)) {
    apiError(400, 'Action inconnue.');
}

/*
 * Les actions passent tantôt par un formulaire encodé, tantôt par du JSON.
 * On accepte le jeton depuis POST, GET, l’en-tête X-CSRF-Token ou le corps JSON,
 * sans jamais accepter une requête non authentifiée.
 */
$jsonInput = null;
if (strpos((string)($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json') !== false) {
    $decoded = json_decode(file_get_contents('php://input'), true);
    $jsonInput = is_array($decoded) ? $decoded : [];
}

if ($action !== 'zone_rates' && $action !== 'zone_countries') {
    $csrf = $_POST['csrf_token'] ?? ($_GET['csrf_token'] ?? '');
    if ((!is_string($csrf) || $csrf === '') && !empty($_SERVER['HTTP_X_CSRF_TOKEN'])) {
        $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'];
    }
    if ((!is_string($csrf) || $csrf === '') && isset($jsonInput['csrf_token'])) {
        $csrf = $jsonInput['csrf_token'];
    }
    if (!is_string($csrf) || !csrfCheck($csrf)) {
        apiError(403, 'Jeton CSRF invalide.');
    }
}

function redirectWithFlash(string $url, string $message, string $type = 'success'): never
{
    $_SESSION['flash'][$type] = $message;
    header_remove();
    header('Location: ' . $url);
    exit;
}

try {
    if ($action === 'zone_save') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        if ($name === '') {
            $_SESSION['flash']['error'] = 'Nom obligatoire.';
            header('Location: /admin/livraison.php');
            exit;
        }
        if ($id > 0) {
            $db->prepare("UPDATE shipping_zones SET name=?, description=?, sort_order=?, is_active=?, updated_at=NOW() WHERE id=?")->execute([$name, $description, $sortOrder, $isActive, $id]);
        } else {
            $db->prepare("INSERT INTO shipping_zones (name, description, sort_order, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())")->execute([$name, $description, $sortOrder, $isActive]);
        }
        redirectWithFlash('/admin/livraison.php', 'Zone enregistrée.');
    }

    if ($action === 'zone_delete') {
        $id = (int)($_POST['id'] ?? 0);
        $db->prepare("DELETE FROM shipping_zones WHERE id = ?")->execute([$id]);
        redirectWithFlash('/admin/livraison.php', 'Zone supprimée.');
    }

    if ($action === 'rate_save') {
        $id = (int)($_POST['id'] ?? 0);
        $zoneId = (int)($_POST['zone_id'] ?? 0);
        $type = $_POST['type'] ?? 'fixed';
        $price = (float)($_POST['price'] ?? 0);
        $currency = trim($_POST['currency_code'] ?? 'EUR');
        $daysMin = trim($_POST['delivery_days_min'] ?? '') === '' ? null : (int)($_POST['delivery_days_min']);
        $daysMax = trim($_POST['delivery_days_max'] ?? '') === '' ? null : (int)($_POST['delivery_days_max']);
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        if ($zoneId <= 0 || $price < 0) {
            $_SESSION['flash']['error'] = 'Données tarif invalides.';
            header('Location: /admin/livraison.php');
            exit;
        }
        if ($id > 0) {
            $db->prepare("UPDATE shipping_rates SET type=?, price=?, currency_code=?, delivery_days_min=?, delivery_days_max=?, is_active=?, updated_at=NOW() WHERE id=? AND zone_id=?")->execute([$type, $price, $currency, $daysMin, $daysMax, $isActive, $id, $zoneId]);
        } else {
            $db->prepare("INSERT INTO shipping_rates (zone_id, type, price, currency_code, delivery_days_min, delivery_days_max, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())")->execute([$zoneId, $type, $price, $currency, $daysMin, $daysMax, $isActive]);
        }
        redirectWithFlash('/admin/livraison.php', 'Tarif enregistré.');
    }

    if ($action === 'rate_delete') {
        $id = (int)($_POST['id'] ?? 0);
        $db->prepare("DELETE FROM shipping_rates WHERE id = ?")->execute([$id]);
        echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'zone_rates') {
        $zoneId = (int)($_GET['zone_id'] ?? 0);
        $stmt = $db->prepare("SELECT * FROM shipping_rates WHERE zone_id = ? ORDER BY id ASC");
        $stmt->execute([$zoneId]);
        echo json_encode(['ok' => true, 'rates' => $stmt->fetchAll()], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'zone_countries') {
        $zoneId = (int)($_GET['zone_id'] ?? 0);
        $stmt = $db->prepare("SELECT c.iso_alpha2 FROM countries c JOIN shipping_zone_countries zc ON zc.country_id = c.id WHERE zc.zone_id = ?");
        $stmt->execute([$zoneId]);
        $inZone = array_column($stmt->fetchAll(), 'iso_alpha2');
        $all = $db->query("SELECT iso_alpha2, name, phone_code, is_blocked FROM countries ORDER BY name ASC")->fetchAll();
        echo json_encode(['ok' => true, 'in_zone' => $inZone, 'all' => $all], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'zone_countries_save') {
        $input = is_array($jsonInput) ? $jsonInput : [];
        $zoneId = (int)($input['zone_id'] ?? 0);
        $countries = $input['countries'] ?? [];
        if ($zoneId <= 0 || !is_array($countries)) { apiError(400, 'Données de zone invalides.'); }

        $zoneCheck = $db->prepare("SELECT id FROM shipping_zones WHERE id = ? LIMIT 1");
        $zoneCheck->execute([$zoneId]);
        if ($zoneCheck->fetch() === false) { apiError(404, 'Zone de livraison introuvable.'); }

        $lookup = $db->prepare("SELECT id FROM countries WHERE iso_alpha2 = ? LIMIT 1");
        $insert = $db->prepare("INSERT IGNORE INTO shipping_zone_countries (zone_id, country_id) VALUES (?, ?)");

        $db->beginTransaction();
        $db->prepare("DELETE FROM shipping_zone_countries WHERE zone_id = ?")->execute([$zoneId]);
        foreach ($countries as $iso) {
            if (!is_string($iso) || !preg_match('/^[A-Za-z]{2}$/', $iso)) { continue; }
            $lookup->execute([strtoupper($iso)]);
            $cid = $lookup->fetch();
            if ($cid !== false) { $insert->execute([$zoneId, (int)$cid['id']]); }
        }
        $db->commit();
        echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }

} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Erreur serveur lors de l’enregistrement.'], JSON_UNESCAPED_UNICODE);
    exit;
}
