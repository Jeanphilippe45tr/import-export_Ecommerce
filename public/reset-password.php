<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/page_bootstrap.php';

$db = db();
$settings = loadSettings($db);

$token = $_GET['token'] ?? '';
$message = '';
$form = ['password' => '', 'password_confirm' => ''];

if ($token === '') {
    $_SESSION['flash']['error'] = 'Token de réinitialisation manquant.';
    redirect('mot-de-passe-oublie.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!csrfCheck($csrf)) {
        $message = 'Jeton CSRF invalide.';
    } else {
        $password = $_POST['password'] ?? '';
        $passwordConfirm = $_POST['password_confirm'] ?? '';

        if ($password === '' || strlen($password) < 8) {
            $message = 'Le mot de passe doit contenir au moins 8 caractères.';
        } elseif ($password !== $passwordConfirm) {
            $message = 'Les mots de passe ne correspondent pas.';
        } else {
            $stmt = $db->prepare("SELECT id FROM customers WHERE reset_token = ? AND reset_expires > NOW() AND is_active = 1 LIMIT 1");
            $stmt->execute([$token]);
            $customer = $stmt->fetch();

            if ($customer === false) {
                $message = 'Token invalide ou expiré.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $db->prepare("UPDATE customers SET password_hash=?, reset_token=NULL, reset_expires=NULL WHERE id=?")->execute([$hash, (int)$customer['id']]);
                $_SESSION['flash']['success'] = 'Votre mot de passe a été réinitialisé. Vous pouvez vous connecter.';
                redirect('connexion.php');
            }
        }
    }
}

$pageTitle = 'Réinitialiser le mot de passe — ' . e($siteName);
$pageDescription = 'Créez un nouveau mot de passe pour votre compte client.';

require_once INCLUDES_DIR . '/header.php';
?>

<section class="section">
    <div class="container" style="max-width:430px; margin:0 auto;">
        <?php if ($message !== ''): ?>
            <div class="alert alert--error" style="margin-bottom:20px;">
                <?= e($message) ?>
            </div>
        <?php endif; ?>

        <form action="reset-password.php?token=<?= e($token) ?>" method="POST" novalidate>
            <?= csrfField() ?>

            <div class="form-group">
                <label>Nouveau mot de passe</label>
                <input type="password" name="password" required>
                <small style="color:var(--color-gray-light);">Minimum 8 caractères.</small>
            </div>

            <div class="form-group">
                <label>Confirmer le nouveau mot de passe</label>
                <input type="password" name="password_confirm" required>
            </div>

            <button type="submit" class="btn btn--primary btn--full" style="margin-top:18px;">Réinitialiser le mot de passe</button>
        </form>

        <p style="font-size:13px; color:var(--color-gray); margin-top:18px; text-align:center;">
            Vous avez votre mot de passe ? <a href="connexion.php">Se connecter</a>
        </p>
    </div>
</section>

<?php
require_once INCLUDES_DIR . '/footer.php';
?>
