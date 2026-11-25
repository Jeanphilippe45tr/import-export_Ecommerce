<?php
declare(strict_types=1);

/**
 * Authentification espace admin.
 * Vérifie que l'admin est connecté, sinon redirige vers le login.
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

startSecureSession();

function adminRequire(): void
{
    if (empty($_SESSION['admin_id'])) {
        redirect(url('admin/login.php'));
    }
    // Vérifier que l'admin est toujours actif
    $db = db();
    $stmt = $db->prepare("SELECT id, username, role, is_active FROM admins WHERE id = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([(int)$_SESSION['admin_id']]);
    $admin = $stmt->fetch();
    if ($admin === false) {
        session_destroy();
        redirect(url('admin/login.php'));
    }
    $_SESSION['admin_username'] = $admin['username'];
    $_SESSION['admin_role'] = $admin['role'];
}

function adminGet(): ?array
{
    if (empty($_SESSION['admin_id'])) {
        return null;
    }
    return [
        'id' => (int)$_SESSION['admin_id'],
        'username' => $_SESSION['admin_username'] ?? '',
        'role' => $_SESSION['admin_role'] ?? 'admin',
    ];
}
