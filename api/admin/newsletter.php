<?php
declare(strict_types=1);

/**
 * API : export CSV newsletter, suppression abonné.
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
        $where[] = 'email LIKE ?';
        $params[] = '%' . $search . '%';
    }
    $whereSql = implode(' AND ', $where);
    $stmt = $db->prepare("SELECT id, email, subscribed_at, source FROM newsletter_subscribers WHERE $whereSql ORDER BY subscribed_at DESC");
    foreach ($params as $i => $p) {
        $stmt->bindValue($i + 1, $p, PDO::PARAM_STR);
    }
    $stmt->execute();
    $rows = $stmt->fetchAll();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="newsletter_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID', 'Email', 'Abonné le', 'Source'], ';');
    foreach ($rows as $r) {
        fputcsv($out, [$r['id'], $r['email'], $r['subscribed_at'], $r['source']], ';');
    }
    fclose($out);
    exit;
}

if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? ($_GET['id'] ?? 0));
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Abonné invalide.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $db->prepare("DELETE FROM newsletter_subscribers WHERE id = ?")->execute([$id]);
    $_SESSION['flash']['success'] = 'Abonné supprimé.';
    header('Location: ' . url('admin/newsletter.php'));
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'message' => 'Action inconnue.'], JSON_UNESCAPED_UNICODE);
exit;
