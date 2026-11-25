<?php
declare(strict_types=1);

/**
 * API publique : messagerie d'assistance (widget chat).
 * Actions : create, send, poll, history
 * Les messages ne sont PAS traduits automatiquement (classe notranslate côté affichage).
 */

require_once __DIR__ . '/../includes/config_bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

function jError(int $code, string $msg): never
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'message' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

$db = db();
$action = $_GET['action'] ?? ($_POST['action'] ?? '');
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

// --- Anti-spam : stockage simple par IP (fichier JSON) ---
$spamFile = __DIR__ . '/../../uploads/chat_spam.json';
$spamDir = dirname($spamFile);
if (!is_dir($spamDir)) {
    @mkdir($spamDir, 0755, true);
}

function spamLoad(string $file): array
{
    if (!file_exists($file)) {
        return [];
    }
    $d = json_decode(file_get_contents($file), true);
    return is_array($d) ? $d : [];
}

function spamSave(string $file, array $data): void
{
    file_put_contents($file, json_encode($data));
    @chmod($file, 0644);
}

function spamCheck(string $ip, string $file): void
{
    $now = time();
    $data = spamLoad($file);
    $arr = $data[$ip] ?? [];
    $arr = array_values(array_filter($arr, fn($t) => $now - (int)$t < 60)); // fenêtre 60s
    if (count($arr) >= 10) { // max 10 messages/minute par IP
        jError(429, 'Trop de messages. Veuillez patienter avant d en renvoyer.');
    }
    $arr[] = $now;
    $data[$ip] = $arr;
    spamSave($file, $data);
}

// ----------------------------------------------------------------
// 1. Créer une conversation (visiteur)
// ----------------------------------------------------------------
if ($action === 'create') {
    $name = trim($_POST['visitor_name'] ?? '');
    $email = trim($_POST['visitor_email'] ?? '');

    if ($name === '') {
        jError(400, 'Votre nom est requis.');
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jError(400, 'Adresse email invalide.');
    }

    $db->prepare(
        "INSERT INTO conversations (visitor_name, visitor_email, visitor_ip, status, unread_by_admin, last_message_at, created_at, updated_at)
         VALUES (?, ?, ?, 'ouverte', 1, NOW(), NOW(), NOW())"
    )->execute([$name, $email ?: null, $ip]);

    $cid = (int)$db->lastInsertId();

    echo json_encode(['ok' => true, 'conversation_id' => $cid], JSON_UNESCAPED_UNICODE);
    exit;
}

// ----------------------------------------------------------------
// 2. Envoyer un message (visiteur)
// ----------------------------------------------------------------
if ($action === 'send') {
    $cid = (int)($_POST['conversation_id'] ?? 0);
    $message = trim($_POST['message'] ?? '');

    if ($cid <= 0) {
        jError(400, 'Conversation invalide.');
    }
    if ($message === '') {
        jError(400, 'Message vide.');
    }

    spamCheck($ip, $spamFile);

    $stmt = $db->prepare("SELECT id, status FROM conversations WHERE id = ? LIMIT 1");
    $stmt->execute([$cid]);
    $conv = $stmt->fetch();
    if ($conv === false) {
        jError(404, 'Conversation introuvable.');
    }
    if ((string)$conv['status'] === 'bloquée') {
        jError(403, 'Cette conversation a été bloquée.');
    }

    $image = null;
    if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($_FILES['image']['tmp_name']);
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
        if (!isset($allowed[$mime])) {
            jError(400, 'Type d image non autorisé.');
        }
        if ($_FILES['image']['size'] > 5 * 1024 * 1024) {
            jError(400, 'Image trop volumineuse (5 Mo max).');
        }
        $ext = $allowed[$mime];
        $name = 'chat_' . $cid . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $dir = __DIR__ . '/../../uploads/chat/images/';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        if (!move_uploaded_file($_FILES['image']['tmp_name'], $dir . $name)) {
            jError(500, 'Erreur lors de l upload de l image.');
        }
        $image = 'uploads/chat/images/' . $name;
    }

    $db->prepare(
        "INSERT INTO messages (conversation_id, sender, message, image, is_read, created_at)
         VALUES (?, 'visitor', ?, ?, 0, NOW())"
    )->execute([$cid, $message, $image]);

    $db->prepare(
        "UPDATE conversations SET unread_by_admin = 1, last_message_at = NOW(), updated_at = NOW() WHERE id = ?"
    )->execute([$cid]);

    $msgId = (int)$db->lastInsertId();

    echo json_encode([
        'ok' => true,
        'message_id' => $msgId,
        'image' => $image,
        'server_time' => date('Y-m-d H:i:s'),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ----------------------------------------------------------------
// 3. Long polling : récupérer les nouveaux messages depuis $since
// ----------------------------------------------------------------
if ($action === 'poll') {
    $cid = (int)($_GET['conversation_id'] ?? 0);
    $since = (string)($_GET['since'] ?? '0');

    if ($cid <= 0) {
        jError(400, 'Conversation invalide.');
    }

    // Libérer le verrou de session : sinon les autres requêtes (envoi, statut…)
    // restent bloquées pendant tout le long polling.
    session_write_close();

    $start = time();
    $timeout = 12; // secondes max de long polling
    while (time() - $start < $timeout) {
        $stmt = $db->prepare(
            "SELECT m.id, m.sender, m.message, m.image, m.created_at, m.is_read
             FROM messages m
             WHERE m.conversation_id = ? AND m.created_at > ?
             ORDER BY m.created_at ASC"
        );
        $stmt->execute([$cid, $since]);
        $msgs = $stmt->fetchAll();
        if ($msgs !== []) {
            break;
        }
        sleep(1);
    }

    echo json_encode([
        'ok' => true,
        'messages' => $msgs,
        'server_time' => date('Y-m-d H:i:s'),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// ----------------------------------------------------------------
// 4. Historique complet d'une conversation
// ----------------------------------------------------------------
if ($action === 'history') {
    $cid = (int)($_GET['conversation_id'] ?? 0);
    if ($cid <= 0) {
        jError(400, 'Conversation invalide.');
    }

    $stmt = $db->prepare(
        "SELECT m.id, m.sender, m.message, m.image, m.created_at, m.is_read
         FROM messages m
         WHERE m.conversation_id = ?
         ORDER BY m.created_at ASC"
    );
    $stmt->execute([$cid]);

    echo json_encode([
        'ok' => true,
        'messages' => $stmt->fetchAll(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// ----------------------------------------------------------------
// Statut en ligne / hors ligne (horaires d'ouverture)
// ----------------------------------------------------------------
if ($action === 'status') {
    $settings = loadSettings($db);
    $timezone = $settings['timezone'] ?? 'Europe/Paris';
    $hours = $settings['chat_hours'] ?? 'Lundi à vendredi, 9h00 à 18h00';

    // Détermination simple : en ligne du lundi au vendredi, 9h-18h, fuseau admin
    try {
        $tz = new DateTimeZone($timezone);
    } catch (Throwable $e) {
        $tz = new DateTimeZone('Europe/Paris');
    }
    $now = new DateTime('now', $tz);
    $day = (int)$now->format('N'); // 1=lundi ... 7=dimanche
    $hour = (int)$now->format('H');

    $online = ($day >= 1 && $day <= 5 && $hour >= 9 && $hour < 18);

    echo json_encode([
        'ok' => true,
        'online' => $online,
        'hours' => $hours,
        'timezone' => $timezone,
        'offline_message' => 'Nous sommes hors ligne (' . $hours . '). Laissez un message, nous vous répondrons dès notre retour.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

jError(400, 'Action inconnue.');
