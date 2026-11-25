<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/page_bootstrap.php';

$db = db();
$settings = loadSettings($db);

// --- Mise à jour des paramètres ---
$errors = [];
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!csrfCheck($csrf)) {
        $errors[] = 'Jeton CSRF invalide.';
    } else {
        $updates = [];
        $fields = [
            'site_name' => 'text',
            'site_slogan' => 'text',
            'meta_title' => 'text',
            'meta_description' => 'textarea',
            'public_phone' => 'text',
            'contact_email' => 'text',
            'whatsapp_order' => 'text',
            'address' => 'text',
            'timezone' => 'text',
            'chat_hours' => 'text',
            'facebook_url' => 'url',
            'instagram_url' => 'url',
            'linkedin_url' => 'url',
            'siret' => 'text',
            'tva_intracommunautaire' => 'text',
            'eori' => 'text',
            'iban' => 'text',
            'bic' => 'text',
            'bank_name' => 'text',
            'shop_country' => 'text',
            'order_terms' => 'textarea',
        ];
        foreach ($fields as $key => $type) {
            if (!array_key_exists($key, $_POST)) {
                continue;
            }
            $value = trim((string)$_POST[$key]);
            // Validation simple email
            if ($key === 'contact_email' && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'L email de contact est invalide.';
                continue;
            }
            // Validation IBAN simple (non vide si présent)
            $updates[] = [$key, $value === '' ? null : $value, $type];
        }

        // Gestion du sélecteur de langue (checkbox)
        $langEnabled = isset($_POST['language_selector_enabled']) ? '1' : '0';
        $updates[] = ['language_selector_enabled', $langEnabled, 'boolean'];
        // Gestion TVA
        $vatEnabled = isset($_POST['vat_enabled']) ? '1' : '0';
        $updates[] = ['vat_enabled', $vatEnabled, 'boolean'];
        // Devise par défaut
        $currencyDefault = $_POST['currency_default'] ?? '';
        $updates[] = ['currency_default', $currencyDefault, 'text'];

        if ($errors === []) {
            try {
                $db->beginTransaction();
                foreach ($updates as [$key, $value, $type]) {
                    $db->prepare(
                        "INSERT INTO settings (`key`, `value`, `type`, updated_at)
                         VALUES (?, ?, ?, NOW())
                         ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `type` = VALUES(`type`), updated_at = NOW()"
                    )->execute([$key, $value, $type]);
                }
                $db->commit();
                $_SESSION['flash']['success'] = 'Paramètres enregistrés.';
                redirect('settings.php');
            } catch (Throwable $e) {
                $db->rollBack();
                $errors[] = 'Erreur : ' . $e->getMessage();
            }
        }
    }
}

// --- Liste des devises pour le select devise par défaut ---
$currencies = $db->query("SELECT * FROM currencies WHERE is_active = 1 ORDER BY code ASC")->fetchAll();

$pageTitle = 'Paramètres du site — Administration';

require_once __DIR__ . '/includes/header.php';
?>

<div class="admin-content" style="padding:26px 22px;">
    <div class="section-title"><i class="fas fa-cog"></i> Paramètres du site</div>

    <?php if ($errors): ?>
        <div class="alert alert--error" style="margin-bottom:20px;"><?= implode('<br>', array_map('e', $errors)) ?></div>
    <?php endif; ?>
    <?php if (hasFlash('success')): ?>
        <div class="alert alert--success" style="margin-bottom:20px;"><?= e(flash('success')) ?></div>
    <?php endif; ?>

    <form action="settings.php" method="POST" novalidate>
        <?= csrfField() ?>

        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap:20px; margin-bottom:26px;">
            <!-- Identité -->
            <div style="background:#fff; border:1px solid var(--color-border); border-radius:var(--radius); padding:18px; grid-column: 1 / -1;">
                <h4 style="font-size:14px; font-weight:800; color:var(--color-dark); margin-bottom:14px; border-bottom:1px solid var(--color-border); padding-bottom:8px;">Identité du site</h4>
                <div class="form-group">
                    <label>Nom du site</label>
                    <input type="text" name="site_name" value="<?= e($settings['site_name'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Slogan</label>
                    <input type="text" name="site_slogan" value="<?= e($settings['site_slogan'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Titre méta (SEO)</label>
                    <input type="text" name="meta_title" value="<?= e($settings['meta_title'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Description méta (SEO)</label>
                    <textarea name="meta_description" rows="2"><?= e($settings['meta_description'] ?? '') ?></textarea>
                </div>
            </div>

            <!-- Contact -->
            <div style="background:#fff; border:1px solid var(--color-border); border-radius:var(--radius); padding:18px;">
                <h4 style="font-size:14px; font-weight:800; color:var(--color-dark); margin-bottom:14px; border-bottom:1px solid var(--color-border); padding-bottom:8px;">Contact &amp; commande</h4>
                <div class="form-group">
                    <label>Téléphone public</label>
                    <input type="text" name="public_phone" value="<?= e($settings['public_phone'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Email de contact</label>
                    <input type="email" name="contact_email" value="<?= e($settings['contact_email'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Numéro WhatsApp commandes</label>
                    <input type="text" name="whatsapp_order" value="<?= e($settings['whatsapp_order'] ?? '') ?>">
                    <div class="form-help">Servira pour la redirection wa.me après commande.</div>
                </div>
                <div class="form-group">
                    <label>Adresse / localisation</label>
                    <textarea name="address" rows="2"><?= e($settings['address'] ?? '') ?></textarea>
                </div>
            </div>

            <!-- Localisation -->
            <div style="background:#fff; border:1px solid var(--color-border); border-radius:var(--radius); padding:18px;">
                <h4 style="font-size:14px; font-weight:800; color:var(--color-dark); margin-bottom:14px; border-bottom:1px solid var(--color-border); padding-bottom:8px;">Localisation &amp; chat</h4>
                <div class="form-group">
                    <label>Fuseau horaire administrateur</label>
                    <input type="text" name="timezone" value="<?= e($settings['timezone'] ?? 'Europe/Paris') ?>">
                    <div class="form-help">Ex. Europe/Paris, UTC, America/New_York</div>
                </div>
                <div class="form-group">
                    <label>Horaires d'ouverture du chat</label>
                    <input type="text" name="chat_hours" value="<?= e($settings['chat_hours'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Pays du vendeur (TVA)</label>
                    <input type="text" name="shop_country" value="<?= e($settings['shop_country'] ?? 'FR') ?>" maxlength="2">
                    <div class="form-help">Code ISO Alpha-2 (ex. FR).</div>
                </div>
                <div class="form-group" style="display:flex; align-items:center; gap:8px;">
                    <input type="checkbox" name="language_selector_enabled" id="langEnabled" <?= (!empty($settings['language_selector_enabled']) && $settings['language_selector_enabled'] === '1') ? 'checked' : '' ?>>
                    <label for="langEnabled" style="margin:0; cursor:pointer;">Activer le sélecteur de langue (traduction auto)</label>
                </div>
                <div class="form-group" style="display:flex; align-items:center; gap:8px;">
                    <input type="checkbox" name="vat_enabled" id="vatEnabled" <?= (!empty($settings['vat_enabled']) && $settings['vat_enabled'] === '1') ? 'checked' : '' ?>>
                    <label for="vatEnabled" style="margin:0; cursor:pointer;">TVA activée (sinon vendeur non assujetti)</label>
                </div>
                <div class="form-group">
                    <label>Devise par défaut</label>
                    <select name="currency_default">
                        <?php foreach ($currencies as $c): ?>
                            <option value="<?= e($c['code']) ?>" <?= (!empty($settings['currency_default']) && $settings['currency_default'] === $c['code']) ? 'selected' : '' ?>><?= e($c['code']) ?> - <?= e($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Réseaux sociaux -->
            <div style="background:#fff; border:1px solid var(--color-border); border-radius:var(--radius); padding:18px;">
                <h4 style="font-size:14px; font-weight:800; color:var(--color-dark); margin-bottom:14px; border-bottom:1px solid var(--color-border); padding-bottom:8px;">Réseaux sociaux</h4>
                <div class="form-group">
                    <label>Facebook</label>
                    <input type="url" name="facebook_url" value="<?= e($settings['facebook_url'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Instagram</label>
                    <input type="url" name="instagram_url" value="<?= e($settings['instagram_url'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>LinkedIn</label>
                    <input type="url" name="linkedin_url" value="<?= e($settings['linkedin_url'] ?? '') ?>">
                </div>
            </div>

            <!-- Légal & bancaire -->
            <div style="background:#fff; border:1px solid var(--color-border); border-radius:var(--radius); padding:18px; grid-column: 1 / -1;">
                <h4 style="font-size:14px; font-weight:800; color:var(--color-dark); margin-bottom:14px; border-bottom:1px solid var(--color-border); padding-bottom:8px;">Informations légales &amp; bancaires</h4>
                <div class="form-row">
                    <div class="form-group" style="flex:1;">
                        <label>SIRET / immatriculation</label>
                        <input type="text" name="siret" value="<?= e($settings['siret'] ?? '') ?>">
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label>TVA intracommunautaire</label>
                        <input type="text" name="tva_intracommunautaire" value="<?= e($settings['tva_intracommunautaire'] ?? '') ?>">
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label>N° EORI</label>
                        <input type="text" name="eori" value="<?= e($settings['eori'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group" style="flex:1;">
                        <label>IBAN</label>
                        <input type="text" name="iban" value="<?= e($settings['iban'] ?? '') ?>">
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label>BIC</label>
                        <input type="text" name="bic" value="<?= e($settings['bic'] ?? '') ?>">
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label>Nom de la banque</label>
                        <input type="text" name="bank_name" value="<?= e($settings['bank_name'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label>Mention douane (CGV / tunnel de commande)</label>
                    <textarea name="order_terms" rows="3"><?= e($settings['order_terms'] ?? 'Les droits de douane, taxes d importation et TVA locale éventuels sont à la charge de l acheteur hors Union européenne.') ?></textarea>
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn--primary"><i class="fas fa-save"></i> Enregistrer les paramètres</button>
    </form>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
