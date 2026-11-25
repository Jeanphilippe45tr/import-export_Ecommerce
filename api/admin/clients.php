<?php
declare(strict_types=1);

/**
 * API : export CSV clients, suppression (anonymisation RGPD).
 */

require_once __DIR__ . '/../../includes/config_bootstrap.php';

if (empty($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Connexion admin requise.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$action = $_GET['action'] ?? '';

$csrf = $_POST['csrf_token'] ?? ($_GET['csrf_token'] ?? '');
if ($action === 'delete' && !csrfCheck($csrf)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Jeton CSRF invalide.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$db = db();

if ($action === 'export_csv') {
    $search = trim($_GET['search'] ?? '');
    $where = ['is_active = 1'];
    $params = [];
    if ($search !== '') {
        $where[] = '(email LIKE ? OR firstname LIKE ? OR lastname LIKE ? OR company LIKE ?)';
        $like = '%' . $search . '%';
        $params = array_merge($params, [$like, $like, $like, $like]);
    }
    $whereSql = implode(' AND ', $where);
    $stmt = $db->prepare("SELECT id, email, firstname, lastname, company, vat_number, phone, address, city, postcode, created_at FROM customers WHERE $whereSql ORDER BY created_at DESC");
    foreach ($params as $i => $p) {
        $stmt->bindValue($i + 1, $p, PDO::PARAM_STR);
    }
    $stmt->execute();
    $rows = $stmt->fetchAll();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="clients_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID', 'Email', 'Prénom', 'Nom', 'Entreprise', 'TVA', 'Téléphone', 'Adresse', 'Ville', 'Code postal', 'Créé le'], ';');
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['id'], $r['email'], $r['firstname'], $r['lastname'], $r['company'] ?? '', $r['vat_number'] ?? '',
            $r['phone'] ?? '', $r['address'] ?? '', $r['city'] ?? '', $r['postcode'] ?? '', $r['created_at']
        ], ';');
    }
    fclose($out);
    exit;
}

if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? ($_GET['id'] ?? 0));
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Client invalide.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    // Anonymisation RGPD : commandes conservées, données perso anonymisées
    $anonEmail = 'supprime_' . $id . '@supprime.local';
    try {
        $db->beginTransaction();
        $db->prepare("UPDATE customers SET email=?, firstname='Supprimé', lastname='Client', phone=NULL, address=NULL, city=NULL, postcode=NULL, company=NULL, vat_number=NULL, is_active=0, password_hash=NULL WHERE id=?")->execute([$anonEmail, $id]);
        $db->commit();
        $_SESSION['flash']['success'] = 'Client anonymisé (RGPD).';
        header('Location: ' . url('admin/clients.php'));
        exit;
    } catch (Throwable $e) {
        $db->rollBack();
        $_SESSION['flash']['error'] = 'Erreur : ' . $e->getMessage();
        header('Location: ' . url('admin/clients.php'));
        exit;
    }
}

http_response_code(400);
echo json_encode(['ok' => false, 'message' => 'Action inconnue.'], JSON_UNESCAPED_UNICODE);
exit;
