<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config_bootstrap.php';

$db = db();
$settings = loadSettings($db);
$siteName = !empty($settings['site_name']) ? $settings['site_name'] : 'EuroImport Export';

// Déjà connecté ?
if (!empty($_SESSION['admin_id'])) {
    redirect('dashboard.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!csrfCheck($csrf)) {
        $errors[] = 'Jeton CSRF invalide.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        // Limitation des tentatives
        $attemptsKey = 'admin_login_attempts_' . md5($username);
        $attempts = $_SESSION[$attemptsKey] ?? 0;
        if ($attempts >= 5) {
            $errors[] = 'Trop de tentatives. Veuillez réessayer plus tard.';
        } else {
            $stmt = $db->prepare("SELECT id, username, password_hash, role, is_active FROM admins WHERE username = ? LIMIT 1");
            $stmt->execute([$username]);
            $admin = $stmt->fetch();

            if ($admin !== false && password_verify($password, $admin['password_hash']) && (int)$admin['is_active'] === 1) {
                $_SESSION['admin_id'] = (int)$admin['id'];
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['admin_role'] = $admin['role'];
                unset($_SESSION[$attemptsKey]);
                $db->prepare("UPDATE admins SET last_login_at = NOW() WHERE id = ?")->execute([(int)$admin['id']]);
                redirect('dashboard.php');
            } else {
                $_SESSION[$attemptsKey] = $attempts + 1;
                $errors[] = 'Identifiant ou mot de passe incorrect.';
            }
        }
    }
}

$pageTitle = 'Connexion administration — ' . e($siteName);
?>
<!DOCTYPE html>
<html lang="fr" dir="ltr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= e($pageTitle) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="<?= ASSETS_URL ?>/css/admin.css?v=1.3.1">
<style>
.admin-login { min-height: 100vh; display: flex; align-items: center; justify-content: center; background: var(--color-lighter); padding: 20px; }
.admin-login__card { background: #fff; border: 1px solid var(--color-border); border-radius: var(--radius); width: 100%; max-width: 400px; padding: 36px 28px; box-shadow: var(--shadow-lg); }
.admin-login__brand { text-align: center; margin-bottom: 24px; }
.admin-login__brand h1 { font-size: 22px; font-weight: 800; color: var(--color-dark); margin: 0; }
.admin-login__brand p { font-size: 13px; color: var(--color-gray); margin-top: 6px; }
</style>
</head>
<body class="admin">
<div class="admin-login">
    <div class="admin-login__card">
        <div class="admin-login__brand">
            <h1><?= e($siteName) ?></h1>
            <p>Administration — Connexion</p>
        </div>
        <?php if ($errors): ?>
            <div class="alert alert--error" style="margin-bottom:16px;">
                <?= implode('<br>', array_map('e', $errors)) ?>
            </div>
        <?php endif; ?>
        <form action="login.php" method="POST" novalidate>
            <?= csrfField() ?>
            <div class="form-group">
                <label>Identifiant</label>
                <input type="text" name="username" required>
            </div>
            <div class="form-group">
                <label>Mot de passe</label>
                <input type="password" name="password" required>
            </div>
            <button type="submit" class="btn btn--primary btn--full" style="margin-top:10px;">Se connecter</button>
        </form>
        <p style="font-size:12px; color:var(--color-gray-light); margin-top:16px; text-align:center;">
            Compte par défaut : admin / password (à changer)
        </p>
    </div>
</div>
</body>
</html>
