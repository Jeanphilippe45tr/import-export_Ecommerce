<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/page_bootstrap.php';

$db = db();
$admin = adminGet();
$adminId = (int)$admin['id'];

$errors = [];
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!csrfCheck($csrf)) {
        $errors[] = 'Jeton CSRF invalide.';
    } else {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $newPasswordConfirm = $_POST['new_password_confirm'] ?? '';

        $stmt = $db->prepare("SELECT password_hash FROM admins WHERE id = ? LIMIT 1");
        $stmt->execute([$adminId]);
        $row = $stmt->fetch();

        if ($row === false || !password_verify($currentPassword, $row['password_hash'])) {
            $errors[] = 'Mot de passe actuel incorrect.';
        } elseif ($newPassword === '' || strlen($newPassword) < 8) {
            $errors[] = 'Le nouveau mot de passe doit contenir au moins 8 caractères.';
        } elseif ($newPassword !== $newPasswordConfirm) {
            $errors[] = 'Les mots de passe ne correspondent pas.';
        } else {
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            $db->prepare("UPDATE admins SET password_hash = ?, updated_at = NOW() WHERE id = ?")->execute([$hash, $adminId]);
            $_SESSION['flash']['success'] = 'Mot de passe modifié avec succès.';
            redirect('profil.php');
        }
    }
}

$stmt = $db->prepare("SELECT username, email, role, last_login_at, created_at FROM admins WHERE id = ? LIMIT 1");
$stmt->execute([$adminId]);
$adminInfo = $stmt->fetch();

$pageTitle = 'Mon profil — Administration';

require_once __DIR__ . '/includes/header.php';
?>
<div class="admin-content" style="padding:26px 22px; max-width:520px;">
    <div class="section-title"><i class="fas fa-user-circle"></i> Mon profil</div>

    <?php if ($errors): ?>
        <div class="alert alert--error" style="margin-bottom:20px;"><?= implode('<br>', array_map('e', $errors)) ?></div>
    <?php endif; ?>
    <?php if (hasFlash('success')): ?>
        <div class="alert alert--success" style="margin-bottom:20px;"><?= e(flash('success')) ?></div>
    <?php endif; ?>

    <div style="background:#fff; border:1px solid var(--color-border); border-radius:var(--radius); padding:22px;">
        <div class="form-group">
            <label>Identifiant</label>
            <input type="text" value="<?= e($adminInfo['username'] ?? '') ?>" disabled style="background:#f5f5f5;">
        </div>
        <div class="form-group">
            <label>Email</label>
            <input type="email" value="<?= e($adminInfo['email'] ?? '') ?>" disabled style="background:#f5f5f5;">
        </div>
        <div class="form-group">
            <label>Rôle</label>
            <input type="text" value="<?= e(ucfirst($adminInfo['role'] ?? '')) ?>" disabled style="background:#f5f5f5;">
        </div>
        <div class="form-group">
            <label>Dernière connexion</label>
            <input type="text" value="<?= $adminInfo['last_login_at'] ? date('d/m/Y H:i', strtotime($adminInfo['last_login_at'])) : 'Jamais' ?>" disabled style="background:#f5f5f5;">
        </div>
        <div class="form-group">
            <label>Créé le</label>
            <input type="text" value="<?= date('d/m/Y H:i', strtotime($adminInfo['created_at'])) ?>" disabled style="background:#f5f5f5;">
        </div>
    </div>

    <div style="margin-top:26px; background:#fff; border:1px solid var(--color-border); border-radius:var(--radius); padding:22px;">
        <h4 style="font-size:14px; font-weight:800; color:var(--color-dark); margin-bottom:14px; border-bottom:1px solid var(--color-border); padding-bottom:8px;">Changer le mot de passe</h4>
        <form action="profil.php" method="POST" novalidate>
            <?= csrfField() ?>
            <div class="form-group">
                <label>Mot de passe actuel</label>
                <input type="password" name="current_password" required>
            </div>
            <div class="form-group">
                <label>Nouveau mot de passe</label>
                <input type="password" name="new_password" required>
                <div class="form-help">Minimum 8 caractères.</div>
            </div>
            <div class="form-group">
                <label>Confirmer le nouveau mot de passe</label>
                <input type="password" name="new_password_confirm" required>
            </div>
            <button type="submit" class="btn btn--primary" style="margin-top:10px;">Enregistrer</button>
        </form>
    </div>
</div>
<?php
require_once __DIR__ . '/includes/footer.php';
?>
