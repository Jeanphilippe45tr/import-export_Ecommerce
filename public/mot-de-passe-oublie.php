<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/header.php';

$db = db();
$settings = loadSettings($db);

$message = '';
$formEmail = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!csrfCheck($csrf)) {
        $message = 'Jeton CSRF invalide.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $formEmail = $email;
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = 'Adresse email invalide.';
        } else {
            $stmt = $db->prepare("SELECT id FROM customers WHERE email = ? AND is_active = 1 LIMIT 1");
            $stmt->execute([$email]);
            $customer = $stmt->fetch();

            if ($customer !== false) {
                // Générer un token de réinitialisation valable 1 heure
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', time() + 3600);
                $db->prepare("UPDATE customers SET reset_token=?, reset_expires=? WHERE id=?")->execute([$token, $expires, (int)$customer['id']]);

                // En mode démo / sans serveur mail, on affiche le token.
                // En production, envoyer un email avec un lien vers reset-password.php?token=...
                $message = 'Un lien de réinitialisation a été envoyé à ' . e($email) . '.<br><br>' .
                    '<strong>Mode démo (pas de serveur mail) :</strong> utilisez le token suivant pour réinitialiser votre mot de passe : ' .
                    '<span class="notranslate" translate="no" style="font-family:monospace; background:#f0f0f0; padding:2px 6px; border-radius:4px;">' . e($token) . '</span><br>' .
                    '<a href="reset-password.php?token=' . e($token) . '">Réinitialiser mon mot de passe</a>';
            } else {
                // Message générique pour ne pas révéler si l'email existe
                $message = 'Si cet email existe, un lien de réinitialisation vous a été envoyé.';
            }
        }
    }
}

$pageTitle = 'Mot de passe oublié — ' . e($siteName);
$pageDescription = 'Réinitialisez votre mot de passe de compte client.';

require_once INCLUDES_DIR . '/header.php';
?>

<section class="section">
    <div class="container" style="max-width:430px; margin:0 auto;">
        <?php if ($message !== ''): ?>
            <div class="alert alert--info" style="margin-bottom:20px;">
                <?= $message ?>
            </div>
        <?php endif; ?>

        <form action="mot-de-passe-oublie.php" method="POST" novalidate>
            <?= csrfField() ?>

            <div class="form-group">
                <label>Votre adresse email</label>
                <input type="email" name="email" value="<?= e($formEmail) ?>" required>
            </div>

            <button type="submit" class="btn btn--primary btn--full">Demander la réinitialisation</button>
        </form>

        <p style="font-size:13px; color:var(--color-gray); margin-top:18px; text-align:center;">
            Vous vous souvenez de votre mot de passe ? <a href="connexion.php">Se connecter</a>
        </p>
    </div>
</section>

<?php
require_once INCLUDES_DIR . '/footer.php';
?>
