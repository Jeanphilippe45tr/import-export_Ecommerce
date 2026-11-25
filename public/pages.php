<?php
declare(strict_types=1);

/**
 * Pages statiques et légales : À propos, Contact, FAQ, Livraison et douane,
 * Retours et rétractation, CGV, Politique de confidentialité (RGPD), Mentions légales.
 * Le contenu est saisi par l admin en français uniquement (pas de traduction).
 */

require_once __DIR__ . '/../includes/page_bootstrap.php';

$db = db();
$settings = loadSettings($db);

$slug = $_GET['slug'] ?? 'mentions-legales';

// Récupération de la page
$stmt = $db->prepare("SELECT * FROM pages WHERE slug = ? AND is_active = 1 LIMIT 1");
$stmt->execute([$slug]);
$page = $stmt->fetch();

if ($page === false) {
    $_SESSION['flash']['error'] = 'Page introuvable.';
    redirect('index.php');
}

$siteName = !empty($settings['site_name']) ? $settings['site_name'] : 'EuroImport Export';
$sitePhone = $settings['public_phone'] ?? '+33 1 40 20 30 40';
$siteEmail = $settings['contact_email'] ?? 'contact@example.com';
$siteAddress = $settings['address'] ?? '';
$whatsapp = $settings['whatsapp_order'] ?? $sitePhone;

$pageTitle = e($page['title']) . ' — ' . e($siteName);
$pageDescription = mb_substr(strip_tags($page['content'] ?? ''), 0, 160) . '...';

require_once INCLUDES_DIR . '/header.php';
?>

<section class="section">
    <div class="container">
        <nav class="breadcrumbs" style="margin-bottom:16px; font-size:12px; color:var(--color-gray-light);">
            <a href="index.php">Accueil</a> <span style="color:var(--color-gray); margin:0 6px;">›</span>
            <strong><?= e($page['title']) ?></strong>
        </nav>

        <div class="section__title" style="font-size:24px; margin-bottom:20px;">
            <?= e($page['title']) ?>
        </div>

        <div class="legal-content" style="font-size:14px; line-height:1.7; color:var(--color-gray-dark); max-width:900px;">
            <?= $page['content'] ?>
        </div>

        <?php if ($slug === 'contact'): ?>
            <div style="margin-top:34px; background:var(--color-light); border:1px solid var(--color-border); border-radius:var(--radius); padding:26px;">
                <h3 style="font-size:16px; font-weight:800; color:var(--color-dark); margin-bottom:14px;">Contactez-nous</h3>
                <p style="font-size:13px; color:var(--color-gray-dark); margin-bottom:14px;">
                    Vous avez une question sur nos produits, une commande en cours ou besoin d assistance pour l import-export ?
                    Écrivez-nous ou appelez-nous.
                </p>
                <div style="display:flex; gap:26px; flex-wrap:wrap;">
                    <div style="flex:1 1 260px; min-width:240px;">
                        <h4 style="font-size:13px; font-weight:700; color:var(--color-primary); margin-bottom:8px; text-transform:uppercase; letter-spacing:0.5px;">Adresse</h4>
                        <p style="font-size:13px; color:var(--color-gray-dark); line-height:1.6;">
                            <?= nl2br(e($siteAddress)) ?>
                        </p>
                    </div>
                    <div style="flex:1 1 260px; min-width:240px;">
                        <h4 style="font-size:13px; font-weight:700; color:var(--color-primary); margin-bottom:8px; text-transform:uppercase; letter-spacing:0.5px;">Téléphone &amp; email</h4>
                        <p style="font-size:13px; color:var(--color-gray-dark); line-height:1.6;">
                            <span class="notranslate" translate="no"><?= nt($sitePhone) ?></span><br>
                            <span class="notranslate" translate="no"><?= nt($siteEmail) ?></span>
                        </p>
                    </div>
                    <div style="flex:1 1 260px; min-width:240px;">
                        <h4 style="font-size:13px; font-weight:700; color:var(--color-primary); margin-bottom:8px; text-transform:uppercase; letter-spacing:0.5px;">Commandes via WhatsApp</h4>
                        <p style="font-size:13px; color:var(--color-gray-dark); line-height:1.6;">
                            <span class="notranslate" translate="no"><?= nt($whatsapp) ?></span><br>
                            <span style="font-size:11px; color:var(--color-gray);">Enregistrez votre commande sur le site, puis nous vous recontactons par WhatsApp.</span>
                        </p>
                    </div>
                </div>
                <div style="margin-top:20px; border-top:1px solid var(--color-border); padding-top:16px;">
                    <h4 style="font-size:13px; font-weight:700; color:var(--color-primary); margin-bottom:8px; text-transform:uppercase; letter-spacing:0.5px;">Localisation</h4>
                    <iframe
                        src="https://www.openstreetmap.org/export/embed.html?bbox=2.3300%2C48.8500%2C2.3500%2C48.8700&amp;layer=mapnik"
                        style="width:100%; height:260px; border:1px solid var(--color-border); border-radius:var(--radius-sm);"
                        referrerpolicy="no-referrer-when-downgrade"></iframe>
                    <p style="font-size:11px; color:var(--color-gray-light); margin-top:6px; text-align:center;">
                        Carte fournie par OpenStreetMap (données ouvertes).
                    </p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php
require_once INCLUDES_DIR . '/footer.php';
?>
