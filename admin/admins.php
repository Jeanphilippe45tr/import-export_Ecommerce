<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/page_bootstrap.php';

$db = db();
$admin = adminGet();
if ($admin['role'] !== 'superadmin') {
    $_SESSION['flash']['error'] = 'Accès réservé au super administrateur.';
    redirect('dashboard.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfCheck($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Jeton CSRF invalide.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'create') {
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = (string)($_POST['password'] ?? '');
            $role = ($_POST['role'] ?? '') === 'superadmin' ? 'superadmin' : 'admin';

            if ($username === '' || $email === '' || strlen($password) < 6) {
                $errors[] = 'Nom, email et mot de passe (6 caractères minimum) sont obligatoires.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Email invalide.';
            } else {
                $stmt = $db->prepare("SELECT id FROM admins WHERE username = ? OR email = ? LIMIT 1");
                $stmt->execute([$username, $email]);
                if ($stmt->fetch() !== false) {
                    $errors[] = 'Ce nom d utilisateur ou cet email existe déjà.';
                } else {
                    try {
                        $db->prepare(
                            "INSERT INTO admins (username, email, password_hash, role, is_active, created_at, updated_at)
                             VALUES (?, ?, ?, ?, 1, NOW(), NOW())"
                        )->execute([$username, $email, password_hash($password, PASSWORD_DEFAULT), $role]);
                        $_SESSION['flash']['success'] = 'Administrateur créé.';
                        redirect('admins.php');
                    } catch (Throwable $e) {
                        $errors[] = 'Erreur : ' . $e->getMessage();
                    }
                }
            }
        } elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) {
                $errors[] = 'Administrateur invalide.';
            } elseif ($id === (int)$admin['id']) {
                $errors[] = 'Vous ne pouvez pas supprimer votre propre compte.';
            } else {
                $stmt = $db->prepare("SELECT role FROM admins WHERE id = ? LIMIT 1");
                $stmt->execute([$id]);
                $target = $stmt->fetch();
                if ($target === false) {
                    $errors[] = 'Administrateur introuvable.';
                } elseif ($target['role'] === 'superadmin') {
                    $stmt = $db->query("SELECT COUNT(*) AS nb FROM admins WHERE role = 'superadmin' AND is_active = 1");
                    if ((int)$stmt->fetch()['nb'] <= 1) {
                        $errors[] = 'Impossible de supprimer le dernier super administrateur.';
                    } else {
                        $db->prepare("DELETE FROM admins WHERE id = ?")->execute([$id]);
                        $_SESSION['flash']['success'] = 'Administrateur supprimé.';
                        redirect('admins.php');
                    }
                } else {
                    $db->prepare("DELETE FROM admins WHERE id = ?")->execute([$id]);
                    $_SESSION['flash']['success'] = 'Administrateur supprimé.';
                    redirect('admins.php');
                }
            }
        }
    }
}

$admins = $db->query("SELECT id, username, email, role, is_active, last_login_at, created_at FROM admins ORDER BY id ASC")->fetchAll();

$pageTitle = 'Administrateurs — Administration';

require_once __DIR__ . '/includes/header.php';
?>
<div class="admin-content" style="padding:26px 22px;">
    <div class="section-title"><i class="fas fa-user-shield"></i> Administrateurs</div>

    <?php if ($errors): ?>
        <div class="alert alert--error" style="margin-bottom:18px;"><?= implode('<br>', array_map('e', $errors)) ?></div>
    <?php endif; ?>

    <div style="display:grid; grid-template-columns:1fr 320px; gap:20px; align-items:start;">
        <table class="admin-table">
            <thead>
                <tr>
                    <th style="width:50px;">ID</th>
                    <th>Utilisateur</th>
                    <th>Email</th>
                    <th style="width:120px;">Rôle</th>
                    <th style="width:90px;">Actif</th>
                    <th style="width:100px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($admins as $a): ?>
                    <tr>
                        <td><?= (int)$a['id'] ?></td>
                        <td><?= e($a['username']) ?><?= (int)$a['id'] === (int)$admin['id'] ? ' <span class="badge badge--active">vous</span>' : '' ?></td>
                        <td><?= e($a['email']) ?></td>
                        <td><?= e($a['role']) ?></td>
                        <td><span class="badge <?= (int)$a['is_active'] === 1 ? 'badge--active' : 'badge--inactive' ?>"><?= (int)$a['is_active'] === 1 ? 'Oui' : 'Non' ?></span></td>
                        <td>
                            <form method="POST" onsubmit="return confirm('Supprimer cet administrateur ?');" style="margin:0;">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                                <button type="submit" class="btn btn--outline btn--sm" style="padding:4px 8px; font-size:12px; color:var(--color-error);" <?= (int)$a['id'] === (int)$admin['id'] ? 'disabled' : '' ?>>Supprimer</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <form method="POST" class="admin-card" style="padding:18px; background:#fff; border:1px solid var(--color-border); border-radius:var(--radius);">
            <h3 style="font-size:15px; margin-bottom:14px;">Ajouter un administrateur</h3>
            <?= csrfField() ?>
            <input type="hidden" name="action" value="create">
            <div class="form-group"><label>Nom d utilisateur</label><input type="text" name="username" required></div>
            <div class="form-group"><label>Email</label><input type="email" name="email" required></div>
            <div class="form-group"><label>Mot de passe</label><input type="password" name="password" minlength="6" required></div>
            <div class="form-group">
                <label>Rôle</label>
                <select name="role">
                    <option value="admin">Administrateur</option>
                    <option value="superadmin">Super administrateur</option>
                </select>
            </div>
            <button type="submit" class="btn btn--primary" style="margin-top:8px;"><i class="fas fa-plus"></i> Créer</button>
        </form>
    </div>
</div>
<?php
require_once __DIR__ . '/includes/footer.php';
