<?php
declare(strict_types=1);

/**
 * API : gestion des pays (espace administration).
 * Actions : pays_save, pays_delete, pays_list, pays_export.
 * Jeton CSRF vérifié sur TOUTES les actions, requêtes préparées PDO partout.
 */

require_once __DIR__ . '/../../includes/config_bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

function paysApiError(int $httpCode, string $message): never
{
    http_response_code($httpCode);
    echo json_encode(['ok' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

function paysJson(bool $ok, string $message = '', array $extra = []): never
{
    echo json_encode(array_merge(['ok' => $ok, 'message' => $message], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

function paysBool(array $source, string $key, int $default = 0): int
{
    if (!array_key_exists($key, $source)) {
        return $default;
    }
    return in_array($source[$key], [1, '1', 'on', 'true', true], true) ? 1 : 0;
}

function paysLength(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}

function paysPlural(int $count, string $singular, string $plural): string
{
    return $count . ' ' . ($count > 1 ? $plural : $singular);
}

/** Protège contre l’interprétation de formules dans Excel / LibreOffice. */
function paysCsvSafe($value): string
{
    $value = (string)$value;
    if ($value !== '' && in_array($value[0], ['=', '+', '-', '@'], true)) {
        $value = "'" . $value;
    }
    return $value;
}

if (empty($_SESSION['admin_id'])) {
    paysApiError(403, 'Connexion admin requise.');
}

$db = db();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

if (!in_array($action, ['pays_save', 'pays_delete', 'pays_list', 'pays_export'], true)) {
    paysApiError(400, 'Action inconnue.');
}

$csrf = $_POST['csrf_token'] ?? ($_GET['csrf_token'] ?? '');
if (!is_string($csrf) || !csrfCheck($csrf)) {
    paysApiError(403, 'Jeton CSRF invalide.');
}

try {
    if ($action === 'pays_save') {
        $id = (int)($_POST['id'] ?? 0);
        $existing = null;

        if ($id > 0) {
            $stmt = $db->prepare("SELECT * FROM countries WHERE id = ? LIMIT 1");
            $stmt->execute([$id]);
            $existing = $stmt->fetch();
            if ($existing === false) {
                paysApiError(404, 'Pays introuvable.');
            }
        }

        /*
         * Une mise à jour partielle est acceptée : seuls les champs présents
         * dans $_POST sont modifiés, les autres conservent leur valeur en base.
         * Cela permet le bouton « Bloquer / Débloquer » d’une ligne.
         */
        $name = trim((string)($_POST['name'] ?? ($existing['name'] ?? '')));
        $iso2 = strtoupper(trim((string)($_POST['iso_alpha2'] ?? ($existing['iso_alpha2'] ?? ''))));

        $rawIso3 = trim((string)($_POST['iso_alpha3'] ?? ($existing['iso_alpha3'] ?? '')));
        $iso3 = $rawIso3 === '' ? null : strtoupper($rawIso3);

        $phone = trim((string)($_POST['phone_code'] ?? ($existing['phone_code'] ?? '')));
        if ($phone !== '' && $phone[0] !== '+') {
            $phone = '+' . $phone;
        }

        $continent = trim((string)($_POST['continent'] ?? ($existing['continent'] ?? '')));
        $continent = $continent === '' ? null : $continent;

        $isEu = paysBool($_POST, 'is_eu', $existing === null ? 0 : (int)$existing['is_eu']);
        $isBlocked = paysBool($_POST, 'is_blocked', $existing === null ? 0 : (int)$existing['is_blocked']);
        $sortOrder = (int)($_POST['sort_order'] ?? ($existing['sort_order'] ?? 0));

        $errors = [];

        if ($name === '') {
            $errors[] = 'Le nom du pays est obligatoire.';
        } elseif (paysLength($name) > 150) {
            $errors[] = 'Le nom du pays ne doit pas dépasser 150 caractères.';
        }

        if (!preg_match('/^[A-Z]{2}$/', $iso2)) {
            $errors[] = 'Le code ISO Alpha-2 doit contenir exactement 2 lettres (ex. FR).';
        }

        if ($iso3 !== null && !preg_match('/^[A-Z]{3}$/', $iso3)) {
            $errors[] = 'Le code ISO Alpha-3 doit contenir exactement 3 lettres (ex. FRA).';
        }

        if (!preg_match('/^\+[0-9]{1,9}$/', $phone)) {
            $errors[] = 'L’indicatif téléphonique est invalide (ex. +33, +1, +971).';
        }

        if ($continent !== null && paysLength($continent) > 50) {
            $errors[] = 'Le continent ne doit pas dépasser 50 caractères.';
        }

        if ($sortOrder < 0 || $sortOrder > 4294967295) {
            $errors[] = 'L’ordre de tri doit être un nombre positif.';
        }

        $stmt = $db->prepare("SELECT COUNT(*) FROM countries WHERE iso_alpha2 = ? AND id <> ?");
        $stmt->execute([$iso2, $id]);
        if ((int)$stmt->fetchColumn() > 0) {
            $errors[] = 'Le code ISO Alpha-2 « ' . $iso2 . ' » est déjà utilisé par un autre pays.';
        }

        if ($iso3 !== null) {
            $stmt = $db->prepare("SELECT COUNT(*) FROM countries WHERE iso_alpha3 = ? AND id <> ?");
            $stmt->execute([$iso3, $id]);
            if ((int)$stmt->fetchColumn() > 0) {
                $errors[] = 'Le code ISO Alpha-3 « ' . $iso3 . ' » est déjà utilisé par un autre pays.';
            }
        }

        /*
         * vat_rates référence les pays par code ISO et sans clé étrangère.
         * Si l’on change l’ISO d’un pays déjà taxé, on évite de créer un taux orphelin.
         */
        if ($existing !== null && $existing['iso_alpha2'] !== $iso2) {
            $stmt = $db->prepare("SELECT COUNT(*) FROM vat_rates WHERE country_iso_alpha2 = ?");
            $stmt->execute([$iso2]);
            if ((int)$stmt->fetchColumn() > 0) {
                $errors[] = 'Un taux de TVA existe déjà pour le nouveau code « ' . $iso2 . ' ». Supprimez ou modifiez d’abord ce taux dans la page TVA.';
            }
        }

        if ($errors !== []) {
            paysApiError(422, implode(' ', $errors));
        }

        $db->beginTransaction();

        if ($existing !== null) {
            $db->prepare(
                "UPDATE countries
                 SET name = ?, iso_alpha2 = ?, iso_alpha3 = ?, phone_code = ?, continent = ?,
                     is_eu = ?, is_blocked = ?, sort_order = ?, updated_at = NOW()
                 WHERE id = ?"
            )->execute([$name, $iso2, $iso3, $phone, $continent, $isEu, $isBlocked, $sortOrder, $id]);

            if ($existing['iso_alpha2'] !== $iso2) {
                $db->prepare(
                    "UPDATE vat_rates SET country_iso_alpha2 = ?, country_name = ? WHERE country_iso_alpha2 = ?"
                )->execute([$iso2, $name, $existing['iso_alpha2']]);
            }

            $message = 'Pays mis à jour.';
        } else {
            $db->prepare(
                "INSERT INTO countries
                    (name, iso_alpha2, iso_alpha3, phone_code, continent, is_eu, is_blocked, sort_order, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())"
            )->execute([$name, $iso2, $iso3, $phone, $continent, $isEu, $isBlocked, $sortOrder]);

            $id = (int)$db->lastInsertId();
            $message = 'Pays ajouté.';
        }

        $db->commit();

        $_SESSION['flash']['success'] = $message;
        paysJson(true, $message, ['id' => $id]);
    }

    if ($action === 'pays_delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            paysApiError(400, 'Identifiant du pays invalide.');
        }

        $stmt = $db->prepare("SELECT name, iso_alpha2 FROM countries WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $country = $stmt->fetch();
        if ($country === false) {
            paysApiError(404, 'Pays introuvable.');
        }

        $reasons = [];

        $stmt = $db->prepare("SELECT COUNT(*) FROM orders WHERE country_id = ?");
        $stmt->execute([$id]);
        $orders = (int)$stmt->fetchColumn();
        if ($orders > 0) {
            $reasons[] = paysPlural($orders, 'commande', 'commandes');
        }

        $stmt = $db->prepare("SELECT COUNT(*) FROM shipping_zone_countries WHERE country_id = ?");
        $stmt->execute([$id]);
        $zones = (int)$stmt->fetchColumn();
        if ($zones > 0) {
            $reasons[] = paysPlural($zones, 'zone de livraison', 'zones de livraison');
        }

        $stmt = $db->prepare("SELECT COUNT(*) FROM vat_rates WHERE country_iso_alpha2 = ?");
        $stmt->execute([$country['iso_alpha2']]);
        $vatRates = (int)$stmt->fetchColumn();
        if ($vatRates > 0) {
            $reasons[] = paysPlural($vatRates, 'taux de TVA', 'taux de TVA');
        }

        if ($reasons !== []) {
            paysApiError(
                409,
                'Suppression impossible : ce pays est utilisé dans ' . implode(', ', $reasons) . '. Retirez-le d’abord de ces éléments.'
            );
        }

        $db->prepare("DELETE FROM countries WHERE id = ?")->execute([$id]);

        $message = 'Pays « ' . $country['name'] . ' » supprimé.';
        $_SESSION['flash']['success'] = $message;
        paysJson(true, $message);
    }

    if ($action === 'pays_list') {
        $countries = $db->query(
            "SELECT id, name, iso_alpha2, iso_alpha3, phone_code, continent, is_eu, is_blocked, sort_order
             FROM countries
             ORDER BY sort_order ASC, name ASC"
        )->fetchAll();

        paysJson(true, '', ['countries' => $countries]);
    }

    if ($action === 'pays_export') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="pays-' . date('Y-m-d') . '.csv"');

        $rows = $db->query(
            "SELECT id, name, iso_alpha2, iso_alpha3, phone_code, continent, is_eu, is_blocked, sort_order
             FROM countries
             ORDER BY sort_order ASC, name ASC"
        )->fetchAll();

        $handle = fopen('php://output', 'w');
        fwrite($handle, "\xEF\xBB\xBF"); // BOM UTF-8 pour Excel français.
        fputcsv($handle, [
            'ID', 'Nom', 'Code ISO Alpha-2', 'Code ISO Alpha-3',
            'Indicatif téléphonique', 'Continent', 'UE', 'Bloqué', 'Ordre'
        ], ';');

        foreach ($rows as $row) {
            fputcsv($handle, [
                (int)$row['id'],
                paysCsvSafe($row['name']),
                paysCsvSafe($row['iso_alpha2']),
                paysCsvSafe($row['iso_alpha3'] ?? ''),
                paysCsvSafe($row['phone_code']),
                paysCsvSafe($row['continent'] ?? ''),
                (int)$row['is_eu'] === 1 ? 'Oui' : 'Non',
                (int)$row['is_blocked'] === 1 ? 'Oui' : 'Non',
                (int)$row['sort_order'],
            ], ';');
        }

        fclose($handle);
        exit;
    }
} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }

    if ($e instanceof PDOException && (int)($e->errorInfo[1] ?? 0) === 1062) {
        paysApiError(409, 'Ce code ISO est déjà utilisé par un autre pays.');
    }

    paysApiError(500, 'Erreur serveur lors de l’enregistrement. Merci de réessayer.');
}
