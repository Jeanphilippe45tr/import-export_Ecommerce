<?php
declare(strict_types=1);

/**
 * API : authentification client (déconnexion).
 */

require_once __DIR__ . '/../includes/config_bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

if ($action === 'logout') {
    $_SESSION = [];
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    session_destroy();
    header('Location: ' . url(''));
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'message' => 'Action inconnue.'], JSON_UNESCAPED_UNICODE);
exit;
