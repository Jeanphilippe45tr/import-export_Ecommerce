<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/header.php';

$pageTitle = $settings['meta_title'] ?? 'EuroImport Export — Import-Export international';
$pageDescription = $settings['meta_description'] ?? 'Vente en gros et détail de produits importés, expédiés partout dans le monde.';

// Le fichier header.php a déjà initialisé : $db, $settings, $currency, $siteName, $cartCount

$featured = $db->query(
    "SELECT p.*, c.name AS category_name
     FROM products p
     LEFT JOIN categories c ON p.category_id = c.id
     WHERE p.is_active = 1
     ORDER BY p.is_featured DESC, p.created_at DESC
     LIMIT 12"
)->fetchAll();

// Catégories en vedette sur la page d'accueil
$homeCategories = $db->query(
    "SELECT * FROM categories WHERE parent_id IS NULL AND is_active = 1 ORDER BY sort_order ASC LIMIT 6"
)->fetchAll();

?>
<section class="hero">
    <div class="container hero__inner">
        <div class="hero__content">
            <span class="hero__eyebrow"><i class="fas fa-globe"></i> Import · Export · Livraison mondiale</span>
            <h1><?= e($siteName) ?></h1>
            <p><?= e($settings['site_slogan'] ?? 'Vente en gros et détail de produits importés, expédiés partout dans le monde.') ?></p>
            <div class="reassurance">
                <span><i class="fas fa-truck"></i> Livraison internationale</span>
                <span><i class="fab fa-whatsapp"></i> Commande puis paiement WhatsApp</span>
                <span><i class="fas fa-shield-alt"></i> Paiement hors ligne sécurisé</span>
            </div>
            <div class="hero__actions">
                <a href="catalogue.php" class="btn btn--primary">Voir le catalogue <i class="fas fa-arrow-right"></i></a>
                <a href="panier.php" class="btn btn--outline">Mon panier <span class="cart-count"><?= (int)$cartCount ?></span></a>
            </div>
        </div>
        <div class="hero__visual">
            <img src="<?= ASSETS_URL ?>/images/hero-import-export.svg" alt="Globe, avion et navire de marchandises illustrant le commerce international" width="640" height="460">
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="section__title">
            <i class="fas fa-layer-group"></i> Catégories en vedette
        </div>
        <div class="category-grid">
            <?php foreach ($homeCategories as $cat): ?>
                <a href="catalogue.php?category=<?= e($cat['slug']) ?>" class="category-card">
                    <div class="category-card__image">
                        <img src="<?= UPLOADS_URL ?>/categories/<?= e($cat['slug']) ?>.svg" alt="<?= e($cat['name']) ?>" loading="lazy">
                    </div>
                    <div class="category-card__body">
                        <span class="category-card__name"><?= e($cat['name']) ?></span>
                        <i class="fas fa-arrow-right"></i>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section" style="background:var(--color-lighter);">
    <div class="container">
        <div class="section__title">
            <i class="fas fa-box"></i> Sélection du moment
        </div>
        <?php if ($featured): ?>
            <div class="product-grid">
                <?php foreach ($featured as $product): ?>
                    <div class="product-card">
                        <div class="product-card__image">
                            <?php
                            $img = imageUrl(productImagePath($db, (int)$product['id'], $product['reference']));
                            ?>
                            <img src="<?= e($img) ?>" alt="<?= e($product['name']) ?>" loading="lazy">
                            <?php if (!empty($product['is_bestseller'])): ?>
                                <span class="product-card__badge">Meilleure vente</span>
                            <?php elseif (!empty($product['is_on_sale'])): ?>
                                <span class="product-card__badge">Promo</span>
                            <?php elseif (!empty($product['is_featured'])): ?>
                                <span class="product-card__badge">Nouveauté</span>
                            <?php endif; ?>
                        </div>
                        <div class="product-card__stars">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <span class="star"><?= $i <= (int)$product['rating'] ? '★' : '☆' ?></span>
                            <?php endfor; ?>
                        </div>
                        <div class="product-card__title"><?= e($product['name']) ?></div>
                        <div class="product-card__meta">
                            <span class="notranslate" translate="no"><?= e($product['reference']) ?></span>
                            <span class="notranslate" translate="no"><?= number_format((float)$product['weight'], 3, ',', ' ') ?> kg</span>
                        </div>
                        <div class="product-card__price">
                            <?= formatPrice(convertAmount((float)$product['price'], ['code' => 'EUR', 'exchange_rate' => 1.000000], $currency), $currency) ?>
                        </div>
                        <div class="product-card__actions">
                            <a href="produit.php?slug=<?= e($product['reference']) ?>" class="btn btn--primary btn--sm">Voir le produit</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-box-open"></i>
                <p>Aucun article disponible pour le moment.</p>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="section__title">
            <i class="fas fa-envelope"></i> Abonnez-vous à notre newsletter
        </div>
        <form action="newsletter.php" method="POST" class="newsletter-form" style="max-width:430px;" novalidate>
            <?= csrfField() ?>
            <div class="form-row">
                <input type="email" name="email" placeholder="Votre adresse email" required>
                <button type="submit" class="btn btn--primary">S'abonner</button>
            </div>
            <label class="consent" style="margin-top:10px;"><input type="checkbox" name="consent" required> J'accepte la <a href="politique-confidentialite.php">politique de confidentialité</a>.</label>
        </form>
    </div>
</section>

<?php
require_once INCLUDES_DIR . '/footer.php';
?>
