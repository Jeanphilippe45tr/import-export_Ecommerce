<?php
declare(strict_types=1);

/**
 * API : gestion TVA (taux par pays, règles).
 */

require_once __DIR__ . '/../../includes/config_bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['admin_id'])) {
    apiError(403, 'Connexion admin requise.');
}

function apiError(int $httpCode, string $message): never
{
    http_response_code($httpCode);
    echo json_encode(['ok' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

$db = db();
$action = $_GET['action'] ?? '';

$csrf = $_POST['csrf_token'] ?? ($_GET['csrf_token'] ?? '');
if ($action !== 'rate_list' && !csrfCheck($csrf)) {
    apiError(403, 'Jeton CSRF invalide.');
}

try {
    if ($action === 'rate_save') {
        $id = (int)($_POST['id'] ?? 0);
        $iso = strtoupper(trim($_POST['country_iso'] ?? ''));
        $rate = (float)($_POST['rate'] ?? 0);
        $type = $_POST['type'] ?? 'standard';
        $isEu = isset($_POST['is_eu']) ? 1 : 0;
        if ($iso === '' || $rate < 0) { apiError(400, 'Pays et taux obligatoires.'); }
        $countryName = '';
        $stmt = $db->prepare("SELECT name FROM countries WHERE iso_alpha2 = ? LIMIT 1");
        $stmt->execute([$iso]);
        $c = $stmt->fetch();
        if ($c !== false) { $countryName = $c['name']; }
        if ($id > 0) {
            $db->prepare("UPDATE vat_rates SET country_name=?, rate=?, type=?, is_eu=?, updated_at=NOW() WHERE country_iso_alpha2 = ?")->execute([$countryName, $rate, $type, $isEu, $iso]);
        } else {
            $db->prepare("INSERT INTO vat_rates (country_iso_alpha2, country_name, rate, type, is_eu, notes, updated_at) VALUES (?, ?, ?, ?, ?, '', NOW())")->execute([$iso, $countryName, $rate, $type, $isEu]);
        }
        echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'rate_delete') {
        $iso = strtoupper(trim($_POST['id'] ?? ''));
        $db->prepare("DELETE FROM vat_rates WHERE country_iso_alpha2 = ?")->execute([$iso]);
        echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'rules_save') {
        $vatEnabled = isset($_POST['vat_enabled']) ? '1' : '0';
        $shopCountry = strtoupper(trim($_POST['shop_country'] ?? 'FR'));
        $euVatRule = $_POST['eu_vat_rule'] ?? 'destination';
        foreach (['vat_enabled' => $vatEnabled, 'shop_country' => $shopCountry, 'eu_vat_rule' => $euVatRule] as $k => $v) {
            $db->prepare("INSERT INTO settings (`key`, `value`, `type`, group_name, updated_at) VALUES (?, ?, 'text', 'tax', NOW()) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`), updated_at=NOW()")->execute([$k, $v]);
        }
        echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }

    apiError(400, 'Action inconnue.');
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}
