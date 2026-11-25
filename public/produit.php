<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/page_bootstrap.php';

$db = db();
$currency = detectCurrency($db);
$settings = loadSettings($db);
$countries = getCountries($db);

// --- Récupération du produit par référence (slug dans l'URL) ---
$reference = $_GET['slug'] ?? null;
$product = null;
if ($reference !== null) {
    $stmt = $db->prepare(
        "SELECT p.*, c.name AS category_name, c.slug AS category_slug
         FROM products p
         LEFT JOIN categories c ON p.category_id = c.id
         WHERE p.reference = ? AND p.is_active = 1
         LIMIT 1"
    );
    $stmt->execute([$reference]);
    $product = $stmt->fetch();
}

if ($product === null) {
    $_SESSION['flash']['error'] = 'Cet article est introuvable.';
    redirect('catalogue.php');
}

// --- Images du produit ---
$stmt = $db->prepare(
    "SELECT * FROM product_images WHERE product_id = ? ORDER BY sort_order ASC, id ASC"
);
$stmt->execute([(int)$product['id']]);
$productImages = $stmt->fetchAll();
if ($productImages === []) {
    $productImages = [['image' => productImagePath($db, (int)$product['id'], $reference), 'is_primary' => 1, 'alt_text' => $product['name']]];
}

// --- Catégorie et produits similaires ---
$similar = [];
if (!empty($product['category_id'])) {
    $stmt = $db->prepare(
        "SELECT * FROM products WHERE category_id = ? AND id <> ? AND is_active = 1 ORDER BY is_featured DESC, created_at DESC LIMIT 4"
    );
    $stmt->execute([(int)$product['category_id'], (int)$product['id']]);
    $similar = $stmt->fetchAll();
}

// --- Estimation de livraison selon le pays choisi ---
$deliveryCountryIso = $_COOKIE['delivery_country'] ?? null;
$shippingRate = null;
$deliveryEstimate = null;
if ($deliveryCountryIso !== null) {
    $shippingRate = getShippingRateForCountry($db, $deliveryCountryIso);
}
$vatRate = vatEnabled($db) ? getVatRate($db, $deliveryCountryIso ?? 'FR') : 0;

$convertedPrice = convertAmount((float)$product['price'], ['code' => 'EUR', 'exchange_rate' => 1.000000], $currency);

$pageTitle = e($product['name']) . ' — ' . e($siteName);
$pageDescription = e($product['short_description'] ?? $product['name']);

require_once INCLUDES_DIR . '/header.php';
?>

<section class="section">
    <div class="container product-page" style="display:grid; grid-template-columns: 520px 1fr; gap:30px; align-items:start;">
        <!-- Galerie photos -->
        <div class="product-gallery">
            <div class="product-gallery__main">
                <img id="main-product-img" src="<?= e(imageUrl($productImages[0]['image'] ?? '', $reference)) ?>" alt="<?= e($product['name']) ?>" style="width:100%; border-radius:var(--radius); cursor:zoom-in;" loading="lazy">
            </div>
            <?php if (count($productImages) > 1): ?>
                <div class="product-gallery__thumbs" style="display:flex; gap:8px; margin-top:12px; flex-wrap:wrap;">
                    <?php foreach ($productImages as $idx => $img): ?>
                        <img src="<?= e(imageUrl($img['image'], $reference)) ?>" alt="<?= e($img['alt_text'] ?? $product['name']) ?>" style="width:72px; height:72px; object-fit:cover; border:2px solid <?= $idx === 0 ? 'var(--color-primary)' : 'transparent' ?>; border-radius:var(--radius-sm); cursor:pointer; opacity:<?= $idx === 0 ? '1' : '0.7' ?>;" loading="lazy" data-img="<?= e(imageUrl($img['image'], $reference)) ?>">
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Infos produit -->
        <div class="product-info">
            <nav class="breadcrumbs" style="margin-bottom:14px; font-size:12px; color:var(--color-gray-light);">
                <a href="index.php">Accueil</a> <span style="color:var(--color-gray); margin:0 6px;">›</span>
                <a href="catalogue.php?category=<?= e($product['category_slug']) ?>"><?= e($product['category_name'] ?? 'Catalogue') ?></a> <span style="color:var(--color-gray); margin:0 6px;">›</span>
                <strong><?= e($product['name']) ?></strong>
            </nav>

            <h1 style="font-size:26px; font-weight:800; color:var(--color-dark); margin-bottom:10px; line-height:1.3;"><?= e($product['name']) ?></h1>

            <div class="product-card__stars" style="margin-bottom:10px;">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                    <span class="star"><?= $i <= (int)$product['rating'] ? '★' : '☆' ?></span>
                <?php endfor; ?>
                <span style="font-size:13px; color:var(--color-gray); margin-left:8px;">(note attribuée par l admin)</span>
            </div>

            <div class="notranslate" translate="no" style="font-size:13px; color:var(--color-gray); margin-bottom:6px;">
                Référence : <?= e($product['reference']) ?><?php if (!empty($product['sku'])): ?> — SKU : <?= e($product['sku']) ?><?php endif; ?>
            </div>

            <div style="font-size:24px; font-weight:800; color:var(--color-dark); margin-bottom:6px;">
                <?= formatPrice($convertedPrice, $currency) ?>
            </div>

            <?php if (!empty($product['is_on_sale']) && !empty($product['sale_price'])): ?>
                <div style="font-size:14px; color:var(--color-error); font-weight:700; margin-bottom:14px;">
                    Prix promo : <?= formatPrice(convertAmount((float)$product['sale_price'], ['code' => 'EUR', 'exchange_rate' => 1.000000], $currency), $currency) ?>
                </div>
            <?php endif; ?>

            <div style="font-size:13px; color:var(--color-gray); margin-bottom:14px;">
                <span style="font-weight:600; color:var(--color-dark);">Poids :</span>
                <span class="notranslate" translate="no"><?= number_format((float)$product['weight'], 3, ',', ' ') ?> kg</span>
            </div>

            <div style="font-size:13px; color:var(--color-gray); margin-bottom:14px;">
                <span style="font-weight:600; color:var(--color-dark);">Stock :</span>
                <span style="color:<?= (int)$product['stock'] > 0 ? 'var(--color-success)' : 'var(--color-error)' ?>; font-weight:600;">
                    <?= (int)$product['stock'] > 0 ? 'En stock (' . (int)$product['stock'] . ')' : 'Rupture de stock' ?>
                </span>
            </div>

            <?php if ($shippingRate): ?>
                <div style="font-size:13px; color:var(--color-gray); margin-bottom:10px;">
                    <span style="font-weight:600; color:var(--color-dark);">Livraison estimée :</span>
                    <?= number_format((float)$shippingRate['price'], 2, ',', ' ') ?> <span class="notranslate" translate="no"><?= e($shippingRate['currency_code'] ?? 'EUR') ?></span>
                    <?= $shippingRate['delivery_days_min'] && $shippingRate['delivery_days_max'] ? ' — ' . (int)$shippingRate['delivery_days_min'] . ' à ' . (int)$shippingRate['delivery_days_max'] . ' jours ouvrés' : '' ?>
                </div>
            <?php else: ?>
                <div style="font-size:13px; color:var(--color-warning); font-weight:600; margin-bottom:10px;">
                    Livraison à confirmer par WhatsApp pour ce pays.
                </div>
            <?php endif; ?>

            <?php if (vatEnabled($db) && $deliveryCountryIso !== null): ?>
                <div style="font-size:13px; color:var(--color-gray); margin-bottom:18px;">
                    <span style="font-weight:600; color:var(--color-dark);">TVA estimée :</span>
                    <?= number_format((float)$vatRate, 2, ',', ' ') ?> % pour <span class="notranslate" translate="no"><?= $deliveryCountryIso ?></span>
                </div>
            <?php endif; ?>

            <?php if ((int)$product['stock'] > 0): ?>
                <form action="api/panier.php" method="POST" id="add-to-cart-form" style="display:flex; gap:10px; align-items:flex-end; margin-bottom:24px;" novalidate>
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="reference" value="<?= e($product['reference']) ?>">
                    <label class="visually-hidden" for="product-qty">Quantité</label>
                    <input type="number" id="product-qty" name="qty" min="1" max="<?= (int)$product['stock'] ?>" value="1" style="width:80px; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm);">
                    <button type="submit" class="btn btn--primary" style="flex:1;">Ajouter au panier</button>
                </form>
            <?php else: ?>
                <button class="btn btn--outline" disabled style="width:100%;">Rupture de stock</button>
            <?php endif; ?>

            <p style="font-size:12px; color:var(--color-gray-light); margin-top:10px;">
                Paiement hors ligne : votre commande est enregistrée et vous êtes redirigé vers WhatsApp pour finaliser.
            </p>
        </div>
    </div>
</section>

<!-- Description -->
<section class="section" style="background:var(--color-light);">
    <div class="container">
        <div class="section__title">Description</div>
        <div style="font-size:15px; line-height:1.7; color:var(--color-gray-dark); max-width:800px;">
            <?= nl2br(e($product['description'] ?? $product['short_description'])) ?>
        </div>
    </div>
</section>

<!-- Produits similaires -->
<?php if ($similar): ?>
    <section class="section">
        <div class="container">
            <div class="section__title">
                <i class="fas fa-cube"></i> Produits similaires
            </div>
            <div class="product-grid">
                <?php foreach ($similar as $sim): ?>
                    <div class="product-card">
                        <div class="product-card__image">
                            <?php
                            $simImg = imageUrl(productImagePath($db, (int)$sim['id'], $sim['reference']));
                            ?>
                            <a href="produit.php?slug=<?= e($sim['reference']) ?>">
                                <img src="<?= e($simImg) ?>" alt="<?= e($sim['name']) ?>" loading="lazy">
                            </a>
                        </div>
                        <div class="product-card__stars">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <span class="star"><?= $i <= (int)$sim['rating'] ? '★' : '☆' ?></span>
                            <?php endfor; ?>
                        </div>
                        <div class="product-card__title">
                            <a href="produit.php?slug=<?= e($sim['reference']) ?>" style="color:inherit;"><?= e($sim['name']) ?></a>
                        </div>
                        <div class="product-card__meta">
                            <span class="notranslate" translate="no"><?= e($sim['reference']) ?></span>
                        </div>
                        <div class="product-card__price">
                            <?= formatPrice(convertAmount((float)$sim['price'], ['code' => 'EUR', 'exchange_rate' => 1.000000], $currency), $currency) ?>
                        </div>
                        <div class="product-card__actions">
                            <a href="produit.php?slug=<?= e($sim['reference']) ?>" class="btn btn--primary btn--sm btn--full">Voir</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<?php
require_once INCLUDES_DIR . '/footer.php';
?>

<script>
/* Zoom galerie produit */
(function(){
  var thumbs = document.querySelectorAll('.product-gallery__thumbs img');
  var mainImg = document.getElementById('main-product-img');
  thumbs.forEach(function(thumb){
    thumb.addEventListener('click', function(){
      thumbs.forEach(function(t){ t.style.opacity='0.7'; t.style.borderColor='transparent'; });
      thumb.style.opacity='1';
      thumb.style.borderColor='var(--color-primary)';
      if (mainImg && thumb.dataset.img) {
        mainImg.style.opacity='0';
        setTimeout(function(){ mainImg.src = thumb.dataset.img; mainImg.style.opacity='1'; }, 150);
      }
    });
  });
})();
</script>
