<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/page_bootstrap.php';

$db = db();
$settings = loadSettings($db);
$currency = detectCurrency($db);

// Accès réservé aux clients connectés
if (empty($_SESSION['customer_id'])) {
    $_SESSION['flash']['info'] = 'Veuillez vous connecter pour accéder à votre compte.';
    redirect('connexion.php?next=compte.php');
}

$customerId = (int)$_SESSION['customer_id'];

// Récupération du client
$stmt = $db->prepare("SELECT * FROM customers WHERE id = ? AND is_active = 1 LIMIT 1");
$stmt->execute([$customerId]);
$customer = $stmt->fetch();
if ($customer === false) {
    $_SESSION['flash']['error'] = 'Compte introuvable.';
    redirect('index.php');
}

// --- Historique des commandes ---
$stmt = $db->prepare(
    "SELECT o.*, COUNT(oi.id) AS nb_articles
     FROM orders o
     LEFT JOIN order_items oi ON oi.order_id = o.id
     WHERE o.customer_id = ?
     GROUP BY o.id
     ORDER BY o.created_at DESC
     LIMIT 50"
);
$stmt->execute([$customerId]);
$orders = $stmt->fetchAll();

// --- Suppression de compte (RGPD) ---
$deleteMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_account') {
    $csrf = $_POST['csrf_token'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    if (!csrfCheck($csrf)) {
        $deleteMessage = 'Jeton CSRF invalide.';
    } elseif (!password_verify($confirmPassword, $customer['password_hash'])) {
        $deleteMessage = 'Mot de passe incorrect.';
    } else {
        // Anonymiser les données personnelles plutôt que supprimer les commandes (obligation comptable 10 ans)
        $anonEmail = 'supprime_' . $customerId . '@supprime.local';
        $anonPhone = null;
        $db->beginTransaction();
        try {
            $db->prepare("UPDATE customers SET email=?, firstname='Supprimé', lastname='Client', phone=NULL, address=NULL, city=NULL, postcode=NULL, company=NULL, vat_number=NULL, is_active=0, password_hash=NULL, email_verified=0 WHERE id=?")->execute([$anonEmail, $customerId]);
            $db->commit();
            session_destroy();
            $_SESSION['flash']['success'] = 'Votre compte a été supprimé (vos commandes sont conservées de manière anonyme pour les obligations comptables).';
            redirect('index.php');
        } catch (Throwable $e) {
            $db->rollBack();
            $deleteMessage = 'Erreur lors de la suppression : ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Mon compte — ' . e($siteName);
$pageDescription = 'Historique de vos commandes et gestion de votre compte client.';

require_once INCLUDES_DIR . '/header.php';
?>

<section class="section">
    <div class="container" style="max-width:900px; margin:0 auto;">
        <nav class="breadcrumbs" style="margin-bottom:16px; font-size:12px; color:var(--color-gray-light);">
            <a href="index.php">Accueil</a> <span style="color:var(--color-gray); margin:0 6px;">›</span>
            <strong>Mon compte</strong>
        </nav>

        <div class="section__title">Mon compte</div>

        <div class="compte-grid" style="display:grid; grid-template-columns: 260px 1fr; gap:30px; align-items:start;">
            <!-- Sidebar -->
            <aside style="background:var(--color-light); border:1px solid var(--color-border); border-radius:var(--radius); padding:18px;">
                <h3 style="font-size:15px; font-weight:800; color:var(--color-dark); margin-bottom:12px;"><?= e($customer['firstname'] . ' ' . $customer['lastname']) ?></h3>
                <p style="font-size:13px; color:var(--color-gray); margin-bottom:16px;">
                    <span class="notranslate" translate="no"><?= e($customer['email']) ?></span><br>
                    <?php if ($customer['company'] !== null && $customer['company'] !== ''): ?>
                        <span class="notranslate" translate="no"><?= e($customer['company']) ?></span><br>
                    <?php endif; ?>
                </p>
                <ul style="font-size:13px;">
                    <li style="margin-bottom:8px;"><a href="compte.php" style="font-weight:600; color:var(--color-dark);">Historique des commandes</a></li>
                    <li style="margin-bottom:8px;"><a href="mot-de-passe-oublie.php">Modifier mon mot de passe</a></li>
                    <li style="margin-bottom:8px;"><a href="api/auth.php?action=logout" style="color:var(--color-error);">Déconnexion</a></li>
                </ul>
            </aside>

            <!-- Contenu principal -->
            <main>
                <h3 style="font-size:16px; font-weight:800; color:var(--color-dark); margin-bottom:16px;">Historique des commandes</h3>

                <?php if ($orders): ?>
                    <table style="width:100%; border-collapse:collapse;">
                        <thead>
                            <tr style="border-bottom:2px solid var(--color-border);">
                                <th style="text-align:left; padding:10px; font-size:13px; color:var(--color-gray);">Numéro</th>
                                <th style="text-align:left; padding:10px; font-size:13px; color:var(--color-gray);">Date</th>
                                <th style="text-align:center; padding:10px; font-size:13px; color:var(--color-gray);">Statut</th>
                                <th style="text-align:right; padding:10px; font-size:13px; color:var(--color-gray);">Total</th>
                                <th style="padding:10px; font-size:13px; color:var(--color-gray);">Articles</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                                <tr style="border-bottom:1px solid var(--color-border);">
                                    <td style="padding:10px; font-size:13px;"><span class="notranslate" translate="no"><?= e($order['order_number']) ?></span></td>
                                    <td style="padding:10px; font-size:13px;"><?= date('d/m/Y H:i', strtotime($order['created_at'])) ?> UTC</td>
                                    <td style="padding:10px; text-align:center; font-size:13px;">
                                        <span style="font-weight:600; color:var(--color-primary);"><?= e($order['status']) ?></span>
                                    </td>
                                    <td style="padding:10px; text-align:right; font-size:13px; font-weight:700;"><span class="notranslate" translate="no"><?= formatPrice((float)$order['total'], ['code' => $order['currency_code'], 'symbol' => $currency['symbol'], 'exchange_rate' => 1.000000]) ?></span></td>
                                    <td style="padding:10px; font-size:12px; color:var(--color-gray);"><?= (int)$order['nb_articles'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state" style="padding:30px 0;">
                        <i class="fas fa-inbox" style="font-size:36px; color:var(--color-gray-light); margin-bottom:12px;"></i>
                        <p>Aucune commande pour le moment.</p>
                    </div>
                <?php endif; ?>

                <!-- Suppression de compte -->
                <div style="margin-top:40px; padding-top:24px; border-top:1px solid var(--color-border);">
                    <h3 style="font-size:15px; font-weight:800; color:var(--color-error); margin-bottom:12px;">Supprimer mon compte (RGPD)</h3>
                    <p style="font-size:13px; color:var(--color-gray); margin-bottom:16px; line-height:1.5;">
                        Conformément au RGPD, vous pouvez demander la suppression de votre compte. Vos données personnelles seront anonymisées et vos commandes conservées de manière anonyme pour les obligations comptables (durée de conservation : 10 ans).
                    </p>

                    <?php if ($deleteMessage !== ''): ?>
                        <div class="alert alert--<?= strpos($deleteMessage, 'succès') !== false ? 'success' : 'error' ?>" style="margin-bottom:16px;">
                            <?= e($deleteMessage) ?>
                        </div>
                    <?php endif; ?>

                    <form action="compte.php" method="POST" novalidate>
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="delete_account">
                        <div class="form-group">
                            <label>Confirmez votre mot de passe pour supprimer votre compte</label>
                            <input type="password" name="confirm_password" required>
                        </div>
                        <p style="font-size:11px; color:var(--color-error); margin-bottom:12px;">
                            Cette action est irréversible pour vos données personnelles.
                        </p>
                        <button type="submit" class="btn btn--outline" style="color:var(--color-error); border-color:var(--color-error);">Supprimer mon compte</button>
                    </form>
                </div>
            </main>
        </div>
    </div>
</section>

<?php
require_once INCLUDES_DIR . '/footer.php';
?>
