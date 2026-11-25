<?php
declare(strict_types=1);

/**
 * API admin : messagerie d'assistance.
 * Actions : list, detail, send, mark_read, close, block, unread_count
 */

require_once __DIR__ . '/../../includes/config_bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

function jError(int $code, string $msg): never
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'message' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

// Authentification admin requise
if (empty($_SESSION['admin_id'])) {
    jError(403, 'Connexion admin requise.');
}

$db = db();
$action = $_GET['action'] ?? '';

$csrf = $_POST['csrf_token'] ?? ($_GET['csrf_token'] ?? '');
if ($action !== 'list' && $action !== 'detail' && $action !== 'unread_count' && !csrfCheck($csrf)) {
    jError(403, 'Jeton CSRF invalide.');
}

try {
    // ----------------------------------------------------------------
    // Liste des conversations (admin)
    // ----------------------------------------------------------------
    if ($action === 'list') {
        $statusFilter = $_GET['status'] ?? '';
        $search = trim($_GET['search'] ?? '');
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 30;
        $offset = ($page - 1) * $perPage;

        $where = ['1=1'];
        $params = [];
        if ($statusFilter !== '') {
            $where[] = 'c.status = ?';
            $params[] = $statusFilter;
        }
        if ($search !== '') {
            $where[] = '(c.visitor_name LIKE ? OR c.visitor_email LIKE ?)';
            $like = '%' . $search . '%';
            $params = array_merge($params, [$like, $like]);
        }
        $whereSql = implode(' AND ', $where);

        $countStmt = $db->prepare("SELECT COUNT(*) AS total FROM conversations c WHERE $whereSql");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetch()['total'];

        $stmt = $db->prepare(
            "SELECT c.id, c.visitor_name, c.visitor_email, c.visitor_ip, c.status, c.unread_by_admin,
                    c.last_message_at, c.created_at,
                    (SELECT COUNT(*) FROM messages m WHERE m.conversation_id = c.id) AS nb_messages
             FROM conversations c
             WHERE $whereSql
             ORDER BY c.last_message_at IS NULL DESC, c.last_message_at DESC, c.created_at DESC
             LIMIT ? OFFSET ?"
        );
        foreach ($params as $i => $p) {
            $stmt->bindValue($i + 1, $p, PDO::PARAM_STR);
        }
        $stmt->bindValue(count($params) + 1, $perPage, PDO::PARAM_INT);
        $stmt->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
        $stmt->execute();
        $conversations = $stmt->fetchAll();

        echo json_encode([
            'ok' => true,
            'conversations' => $conversations,
            'total' => $total,
            'page' => $page,
            'total_pages' => max(1, (int)ceil($total / $perPage)),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ----------------------------------------------------------------
    // Détail d'une conversation + messages (admin)
    // ----------------------------------------------------------------
    if ($action === 'detail') {
        $cid = (int)($_GET['conversation_id'] ?? 0);
        if ($cid <= 0) {
            jError(400, 'Conversation invalide.');
        }

        $stmt = $db->prepare("SELECT * FROM conversations WHERE id = ? LIMIT 1");
        $stmt->execute([$cid]);
        $conv = $stmt->fetch();
        if ($conv === false) {
            jError(404, 'Conversation introuvable.');
        }

        // Marquer comme lue par l'admin
        $db->prepare("UPDATE conversations SET unread_by_admin = 0 WHERE id = ?")->execute([$cid]);

        $stmt = $db->prepare(
            "SELECT m.id, m.sender, m.message, m.image, m.created_at, m.is_read
             FROM messages m
             WHERE m.conversation_id = ?
             ORDER BY m.created_at ASC"
        );
        $stmt->execute([$cid]);
        $messages = $stmt->fetchAll();

        echo json_encode([
            'ok' => true,
            'conversation' => $conv,
            'messages' => $messages,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    // ----------------------------------------------------------------
    // Répondre en tant qu'admin
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

        $stmt = $db->prepare("SELECT status FROM conversations WHERE id = ? LIMIT 1");
        $stmt->execute([$cid]);
        if ($stmt->fetch() === false) {
            jError(404, 'Conversation introuvable.');
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
            $name = 'chat_admin_' . $cid . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
            $dir = __DIR__ . '/../../uploads/chat/images/';
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            if (!move_uploaded_file($_FILES['image']['tmp_name'], $dir . $name)) {
                jError(500, 'Erreur upload image.');
            }
            $image = 'uploads/chat/images/' . $name;
        }

        $db->prepare(
            "INSERT INTO messages (conversation_id, sender, message, image, is_read, created_at)
             VALUES (?, 'admin', ?, ?, 1, NOW())"
        )->execute([$cid, $message, $image]);

        $db->prepare(
            "UPDATE conversations SET last_message_at = NOW(), updated_at = NOW() WHERE id = ?"
        )->execute([$cid]);

        echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ----------------------------------------------------------------
    // Marquer les messages comme lus par l'admin
    // ----------------------------------------------------------------
    if ($action === 'mark_read') {
        $cid = (int)($_POST['conversation_id'] ?? 0);
        if ($cid <= 0) {
            jError(400, 'Conversation invalide.');
        }
        $db->prepare("UPDATE conversations SET unread_by_admin = 0 WHERE id = ?")->execute([$cid]);
        echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ----------------------------------------------------------------
    // Clôturer / rouvrir une conversation
    // ----------------------------------------------------------------
    if ($action === 'close') {
        $cid = (int)($_POST['conversation_id'] ?? 0);
        $status = $_POST['status'] ?? 'fermée';
        if ($cid <= 0) {
            jError(400, 'Conversation invalide.');
        }
        $allowed = ['ouverte', 'fermée', 'bloquée'];
        if (!in_array($status, $allowed, true)) {
            jError(400, 'Statut invalide.');
        }
        $db->prepare("UPDATE conversations SET status = ?, updated_at = NOW() WHERE id = ?")
            ->execute([$status, $cid]);
        echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ----------------------------------------------------------------
    // Bloquer / débloquer un visiteur (conversation)
    // ----------------------------------------------------------------
    if ($action === 'block') {
        $cid = (int)($_POST['conversation_id'] ?? 0);
        $blocked = isset($_POST['blocked']) ? 1 : 0;
        if ($cid <= 0) {
            jError(400, 'Conversation invalide.');
        }
        $status = $blocked ? 'bloquée' : 'ouverte';
        $db->prepare("UPDATE conversations SET status = ?, updated_at = NOW() WHERE id = ?")
            ->execute([$status, $cid]);
        echo json_encode(['ok' => true, 'status' => $status], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ----------------------------------------------------------------
    // Nombre de messages non lus (admin, badge)
    // ----------------------------------------------------------------
    if ($action === 'unread_count') {
        $stmt = $db->query("SELECT COUNT(*) AS nb FROM messages m JOIN conversations c ON c.id = m.conversation_id WHERE m.sender = 'visitor' AND m.is_read = 0");
        $count = (int)$stmt->fetch()['nb'];
        echo json_encode(['ok' => true, 'count' => $count], JSON_UNESCAPED_UNICODE);
        exit;
    }

    jError(400, 'Action inconnue.');
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Erreur serveur : ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}
