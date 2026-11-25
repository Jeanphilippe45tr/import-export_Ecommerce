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
        $consent = isset($_POST['consent']) ? '1' : '0';
        $formEmail = $email;

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = 'Adresse email invalide.';
        } elseif ($consent !== '1') {
            $message = 'Vous devez accepter la politique de confidentialité.';
        } else {
            // Vérifier doublon
            $stmt = $db->prepare("SELECT id, is_active, unsubscribed_at FROM newsletter_subscribers WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $existing = $stmt->fetch();

            if ($existing !== false) {
                if ((int)$existing['is_active'] === 1 && $existing['unsubscribed_at'] === null) {
                    $message = 'Cet email est déjà abonné à la newsletter.';
                } else {
                    // Réactiver l'abonnement
                    $token = bin2hex(random_bytes(16));
                    $db->prepare("UPDATE newsletter_subscribers SET is_active=1, unsubscribed_at=NULL, token=?, subscribed_at=NOW() WHERE email=?")->execute([$token, $email]);
                    $message = 'Votre abonnement à la newsletter a été réactivé.';
                }
            } else {
                $token = bin2hex(random_bytes(16));
                $db->prepare(
                    "INSERT INTO newsletter_subscribers (email, source, consent_ip, token, is_active, created_at)
                     VALUES (?, 'website', ?, ?, 1, NOW())"
                )->execute([$email, $_SERVER['REMOTE_ADDR'] ?? null, $token]);
                $message = 'Vous êtes bien abonné à notre newsletter. Merci !';
            }
        }
    }
}

$pageTitle = 'Newsletter — ' . e($siteName);
$pageDescription = 'Abonnez-vous à notre lettre d information.';

require_once INCLUDES_DIR . '/header.php';
?>

<section class="section">
    <div class="container" style="max-width:430px; margin:0 auto;">
        <?php if ($message !== ''): ?>
            <div class="alert alert--info" style="margin-bottom:20px;">
                <?= e($message) ?>
            </div>
        <?php endif; ?>

        <form action="newsletter.php" method="POST" novalidate>
            <?= csrfField() ?>

            <div class="form-group">
                <label>Votre adresse email</label>
                <input type="email" name="email" value="<?= e($formEmail) ?>" required>
            </div>

            <div class="form-group consent" style="margin-top:8px;">
                <input type="checkbox" name="consent" id="newsletter-consent" required>
                <label for="newsletter-consent" style="cursor:pointer;">
                    J'accepte de recevoir la newsletter et la <a href="politique-confidentialite.php" style="color:var(--color-primary); text-decoration:underline;">politique de confidentialité</a>.
                </label>
            </div>

            <button type="submit" class="btn btn--primary btn--full" style="margin-top:18px;">S'abonner</button>
        </form>

        <p style="font-size:13px; color:var(--color-gray); margin-top:18px; text-align:center;">
            Vous pouvez vous désabonner à tout moment via le lien dans chaque email.
        </p>
    </div>
</section>

<?php
require_once INCLUDES_DIR . '/footer.php';
?>
