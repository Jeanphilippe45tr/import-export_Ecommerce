<?php
declare(strict_types=1);

/**
 * API : désabonnement newsletter (lien unique dans les emails).
 * GET ?token=XXXX
 */

require_once __DIR__ . '/../includes/config_bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$token = $_GET['token'] ?? '';

if ($token === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Token manquant.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $db = db();
    $stmt = $db->prepare("SELECT id FROM newsletter_subscribers WHERE token = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$token]);
    $sub = $stmt->fetch();

    if ($sub === false) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'message' => 'Token invalide ou déjà désabonné.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $db->prepare("UPDATE newsletter_subscribers SET is_active=0, unsubscribed_at=NOW() WHERE id=?")->execute([(int)$sub['id']]);

    echo json_encode(['ok' => true, 'message' => 'Désabonnement effectué.'], JSON_UNESCAPED_UNICODE);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Erreur serveur.'], JSON_UNESCAPED_UNICODE);
    exit;
}
