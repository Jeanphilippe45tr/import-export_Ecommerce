<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/page_bootstrap.php';

$db = db();
$settings = loadSettings($db);

if (!empty($_SESSION['customer_id'])) {
    redirect('compte.php');
}

$errors = [];
$form = [
    'firstname' => '',
    'lastname' => '',
    'email' => '',
    'phone' => '',
    'company' => '',
    'vat_number' => '',
    'address' => '',
    'city' => '',
    'postcode' => '',
    'country' => 'FR',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!csrfCheck($csrf)) {
        $errors[] = 'Jeton CSRF invalide.';
    } else {
        $form['firstname'] = e(trim($_POST['firstname'] ?? ''));
        $form['lastname'] = e(trim($_POST['lastname'] ?? ''));
        $form['email'] = e(trim($_POST['email'] ?? ''));
        $form['phone'] = e(trim($_POST['phone'] ?? ''));
        $form['company'] = e(trim($_POST['company'] ?? ''));
        $form['vat_number'] = e(trim($_POST['vat_number'] ?? ''));
        $form['address'] = e(trim($_POST['address'] ?? ''));
        $form['city'] = e(trim($_POST['city'] ?? ''));
        $form['postcode'] = e(trim($_POST['postcode'] ?? ''));
        $form['country'] = e($_POST['country'] ?? 'FR');

        $password = $_POST['password'] ?? '';
        $passwordConfirm = $_POST['password_confirm'] ?? '';
        $consent = isset($_POST['consent']) ? '1' : '0';

        if ($form['firstname'] === '' || $form['lastname'] === '') {
            $errors[] = 'Prénom et nom sont obligatoires.';
        }
        if ($form['email'] === '' || !filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Adresse email invalide.';
        }
        if ($password === '' || strlen($password) < 8) {
            $errors[] = 'Le mot de passe doit contenir au moins 8 caractères.';
        }
        if ($password !== $passwordConfirm) {
            $errors[] = 'Les mots de passe ne correspondent pas.';
        }
        if ($consent !== '1') {
            $errors[] = 'Vous devez accepter la politique de confidentialité.';
        }

        $country = getCountry($db, $form['country']);
        if ($country === null) {
            $errors[] = 'Pays invalide.';
        }

        if ($errors === []) {
            // Vérifier doublon email
            $stmt = $db->prepare("SELECT id FROM customers WHERE email = ? LIMIT 1");
            $stmt->execute([$form['email']]);
            if ($stmt->fetch() !== false) {
                $errors[] = 'Cet email est déjà utilisé.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare(
                    "INSERT INTO customers (email, password_hash, firstname, lastname, phone, address, city, postcode, country_id, company, vat_number, is_active, email_verified, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 0, NOW(), NOW())"
                );
                $stmt->execute([
                    $form['email'], $hash, $form['firstname'], $form['lastname'], $form['phone'],
                    $form['address'], $form['city'], $form['postcode'], (int)$country['id'],
                    $form['company'] ?: null, $form['vat_number'] ?: null
                ]);

                $_SESSION['flash']['success'] = 'Compte créé avec succès. Vous pouvez vous connecter.';
                redirect('connexion.php');
            }
        }
    }
}

$countries = getCountries($db);
$pageTitle = 'Inscription — ' . e($siteName);
$pageDescription = 'Créez votre compte client pour passer commande et suivre vos achats.';

require_once INCLUDES_DIR . '/header.php';
?>

<section class="section">
    <div class="container" style="max-width:430px; margin:0 auto;">
        <?php if ($errors): ?>
            <div class="alert alert--error" style="margin-bottom:20px;">
                <?= implode('<br>', array_map('e', $errors)) ?>
            </div>
        <?php endif; ?>

        <form action="inscription.php" method="POST" novalidate>
            <?= csrfField() ?>

            <div class="form-group">
                <label>Prénom <span style="color:var(--color-error);">*</span></label>
                <input type="text" name="firstname" value="<?= e($form['firstname']) ?>" required>
            </div>

            <div class="form-group">
                <label>Nom <span style="color:var(--color-error);">*</span></label>
                <input type="text" name="lastname" value="<?= e($form['lastname']) ?>" required>
            </div>

            <div class="form-group">
                <label>Entreprise (facultatif)</label>
                <input type="text" name="company" value="<?= e($form['company']) ?>" placeholder="Nom de votre entreprise">
            </div>

            <div class="form-group">
                <label>N° de TVA intracommunautaire (facultatif)</label>
                <input type="text" name="vat_number" value="<?= e($form['vat_number']) ?>" placeholder="Ex. FR 12 345 678 901">
            </div>

            <div class="form-group">
                <label>Email <span style="color:var(--color-error);">*</span></label>
                <input type="email" name="email" value="<?= e($form['email']) ?>" required>
            </div>

            <div class="form-group">
                <label>Téléphone international <span style="color:var(--color-error);">*</span></label>
                <input type="tel" name="phone" value="<?= e($form['phone']) ?>" placeholder="+33 6 12 34 56 78" required>
            </div>

            <div class="form-group">
                <label>Adresse <span style="color:var(--color-error);">*</span></label>
                <input type="text" name="address" value="<?= e($form['address']) ?>" required>
            </div>

            <div class="form-row">
                <div class="form-group" style="flex:1 1 200px;">
                    <label>Code postal <span style="color:var(--color-error);">*</span></label>
                    <input type="text" name="postcode" value="<?= e($form['postcode']) ?>" required>
                </div>
                <div class="form-group" style="flex:1 1 220px;">
                    <label>Ville <span style="color:var(--color-error);">*</span></label>
                    <input type="text" name="city" value="<?= e($form['city']) ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label>Pays <span style="color:var(--color-error);">*</span></label>
                <select name="country" required>
                    <option value="">Sélectionner un pays</option>
                    <?php foreach ($countries as $c): ?>
                        <option value="<?= e($c['iso_alpha2']) ?>" <?= $form['country'] === $c['iso_alpha2'] ? 'selected' : '' ?>>
                            <?= e($c['name']) ?> <?= nt('(' . $c['phone_code'] . ')') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Mot de passe <span style="color:var(--color-error);">*</span></label>
                <input type="password" name="password" required>
                <small style="color:var(--color-gray-light);">Minimum 8 caractères.</small>
            </div>

            <div class="form-group">
                <label>Confirmer le mot de passe <span style="color:var(--color-error);">*</span></label>
                <input type="password" name="password_confirm" required>
            </div>

            <div class="form-group consent" style="margin-top:8px;">
                <input type="checkbox" name="consent" id="consent" required>
                <label for="consent" style="cursor:pointer;">
                    J ai lu et j accepte la <a href="politique-confidentialite.php" style="color:var(--color-primary); text-decoration:underline;">politique de confidentialité</a>.
                </label>
            </div>

            <button type="submit" class="btn btn--primary btn--full" style="margin-top:18px;">Créer mon compte</button>
        </form>

        <p style="font-size:13px; color:var(--color-gray); margin-top:18px; text-align:center;">
            Déjà inscrit ? <a href="connexion.php">Se connecter</a>
        </p>
    </div>
</section>

<?php
require_once INCLUDES_DIR . '/footer.php';
?>
