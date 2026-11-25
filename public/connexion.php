<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/page_bootstrap.php';

$db = db();
$settings = loadSettings($db);

// Déjà connecté ?
if (!empty($_SESSION['customer_id'])) {
    redirect('compte.php');
}

$errors = [];
$formEmail = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!csrfCheck($csrf)) {
        $errors[] = 'Jeton CSRF invalide.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $formEmail = $email;

        if ($email === '' || $password === '') {
            $errors[] = 'Email et mot de passe sont obligatoires.';
        } else {
            // Limitation simple des tentatives (compteur en session)
            $attemptsKey = 'login_attempts_' . md5($email);
            $attempts = $_SESSION[$attemptsKey] ?? 0;
            if ($attempts >= 5) {
                $errors[] = 'Trop de tentatives. Veuillez réessayer plus tard.';
            } else {
                $stmt = $db->prepare("SELECT * FROM customers WHERE email = ? AND is_active = 1 LIMIT 1");
                $stmt->execute([$email]);
                $customer = $stmt->fetch();

                if ($customer !== false && password_verify($password, $customer['password_hash'])) {
                    $_SESSION['customer_id'] = (int)$customer['id'];
                    $_SESSION['customer_email'] = $customer['email'];
                    $_SESSION['customer_name'] = $customer['firstname'] . ' ' . $customer['lastname'];
                    unset($_SESSION[$attemptsKey]);
                    $next = $_GET['next'] ?? 'compte.php';
                    redirect($next);
                } else {
                    $_SESSION[$attemptsKey] = $attempts + 1;
                    $errors[] = 'Email ou mot de passe incorrect.';
                }
            }
        }
    }
}

$pageTitle = 'Connexion — ' . e($siteName);
$pageDescription = 'Connexion à votre compte client.';

require_once INCLUDES_DIR . '/header.php';
?>

<section class="section">
    <div class="container" style="max-width:430px; margin:0 auto;">
        <?php if ($errors): ?>
            <div class="alert alert--error" style="margin-bottom:20px;">
                <?= implode('<br>', array_map('e', $errors)) ?>
            </div>
        <?php endif; ?>

        <form action="connexion.php" method="POST" novalidate>
            <?= csrfField() ?>
            <input type="hidden" name="next" value="<?= e($_GET['next'] ?? 'compte.php') ?>">

            <div class="form-group">
                <label>Email <span style="color:var(--color-error);">*</span></label>
                <input type="email" name="email" value="<?= e($formEmail) ?>" required>
            </div>

            <div class="form-group">
                <label>Mot de passe <span style="color:var(--color-error);">*</span></label>
                <input type="password" name="password" required>
            </div>

            <div style="font-size:12px; color:var(--color-gray); margin-bottom:16px; text-align:right;">
                <a href="mot-de-passe-oublie.php">Mot de passe oublié ?</a>
            </div>

            <button type="submit" class="btn btn--primary btn--full">Se connecter</button>
        </form>

        <p style="font-size:13px; color:var(--color-gray); margin-top:18px; text-align:center;">
            Pas encore de compte ? <a href="inscription.php">Créer un compte</a>
        </p>
    </div>
</section>

<?php
require_once INCLUDES_DIR . '/footer.php';
?>
