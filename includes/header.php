<?php
declare(strict_types=1);

// Les pages admin ont leur propre en-tête.
if (defined('ADMIN_CONTEXT')) {
    return;
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

startSecureSession();

$db = db();
$lang = currentLanguageFromCookie();
$rtl = currentLanguageIsRtl();
$currency = detectCurrency($db);
$settings = loadSettings($db);
$currencies = getCurrencies($db);
$countries = getCountries($db);
$topCategories = getCategories($db);
$allSubCategories = [];
foreach (getCategories($db) as $cat) {
    $subs = getCategories($db, (int)$cat['id']);
    if ($subs !== []) {
        $allSubCategories[(int)$cat['id']] = $subs;
    }
}

$siteName = !empty($settings['site_name']) ? $settings['site_name'] : 'EuroImport Export';
$sitePhone = $settings['public_phone'] ?? '+33 1 40 20 30 40';
$siteEmail = $settings['contact_email'] ?? 'contact@example.com';
$whatsapp = $settings['whatsapp_order'] ?? $sitePhone;
$siteAddress = $settings['address'] ?? '';
$pageTitle = $pageTitle ?? null;
$pageDescription = $pageDescription ?? null;

$cartCount = 0;
if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $cartCount += (int)($item['qty'] ?? 0);
    }
}

$title = $pageTitle !== null ? $pageTitle : ($settings['meta_title'] ?? $siteName . ' – Import-Export international');
$description = $pageDescription !== null ? $pageDescription : ($settings['meta_description'] ?? 'Import-export professionnel : vente en gros et détail partout dans le monde. Commande via WhatsApp. Livraison internationale.');

?>
<!DOCTYPE html>
<html lang="<?= e($lang) ?>" dir="<?= $rtl ? 'rtl' : 'ltr' ?>">
<head>
<base href="<?= APP_URL ?>/">
<script>
window.IE_APP_URL = <?= json_encode(APP_URL, JSON_UNESCAPED_SLASHES) ?>;
window.IE_CUSTOMER = <?= json_encode([
    'name' => !empty($_SESSION['customer_name']) ? $_SESSION['customer_name'] : '',
    'email' => !empty($_SESSION['customer_email']) ? $_SESSION['customer_email'] : '',
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#ff6600">
<title><?= e($title) ?></title>
<meta name="description" content="<?= e($description) ?>">
<meta name="author" content="<?= e($siteName) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="<?= ASSETS_URL ?>/css/main.css?v=1.2.0">

<?php
// Balises Open Graph (SEO en français)
$ogTitle = $pageTitle !== null ? $pageTitle : ($settings['meta_title'] ?? $siteName . ' – Import-Export international');
$ogDescription = $pageDescription !== null ? $pageDescription : ($settings['meta_description'] ?? 'Import-export professionnel : vente en gros et détail partout dans le monde.');
$ogUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
$ogImage = ASSETS_URL . '/images/logo-og.png';
?>
<meta property="og:title" content="<?= e($ogTitle) ?>">
<meta property="og:description" content="<?= e($ogDescription) ?>">
<meta property="og:type" content="website">
<meta property="og:url" content="<?= e($ogUrl) ?>">
<meta property="og:image" content="<?= e($ogImage) ?>">
<meta property="og:locale" content="fr_FR">
<meta property="og:site_name" content="<?= e($siteName) ?>">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:site" content="@<?= e($siteName) ?>">

<!-- Google Website Translator : chargement conditionnel au consentement cookie -->
<div id="google_translate_element" class="gtranslate-container"></div>
<script>
// Le widget Google Translate n'est chargé qu'après consentement (cookie 'ie_cookie_consent' = 'true').
// Voir le bandeau cookies en pied de page.
function loadGoogleTranslator() {
    if (document.getElementById('google_translate_element_loaded')) return;
    var s = document.createElement('script');
    s.type = 'text/javascript';
    s.src = '//translate.google.com/translate_a/element.js?cb=googleTranslateElementInit';
    document.head.appendChild(s);
    document.body.appendChild(Object.assign(document.createElement('div'), { id: 'google_translate_element_loaded', style: 'display:none' }));
}
function googleTranslateElementInit() {
    if (typeof google !== 'undefined' && google.translate) {
        new google.translate.TranslateElement({
            pageLanguage: 'fr',
            includedLanguages: 'en,es,de,it,pt,nl,ar,zh-CN,ru,tr',
            layout: google.translate.TranslateElement.InlineLayout.SIMPLE,
            autoDisplay: false
        }, 'google_translate_element');
    }
}
var consent = document.cookie.match('(?:^|; )ie_cookie_consent=([^;]*)');
if (consent && consent[1] === 'true') {
    loadGoogleTranslator();
}
</script>
</head>
<body class="<?= $rtl ? 'rtl' : 'ltr' ?>">
<div class="site">

<!-- ======================= BARRE SUPÉRIEURE ======================= -->
<header class="top-bar">
    <div class="container top-bar__inner">
        <div class="top-bar__left">
            <span class="top-bar__item"><i class="fas fa-phone"></i> <?= nt($sitePhone) ?></span>
            <span class="top-bar__item top-bar__email"><i class="fas fa-envelope"></i> <?= nt($siteEmail) ?></span>
        </div>

        <div class="top-bar__center">
            <!-- Sélecteur de devise (choix mémorisé en cookie) -->
            <form id="currency-form" class="top-bar__form">
                <i class="fas fa-coins" aria-hidden="true"></i>
                <label class="visually-hidden" for="currency-select">Devise</label>
                <select id="currency-select" name="currency" title="Choisir une devise">
                    <?php foreach ($currencies as $c): ?>
                        <option value="<?= e($c['code']) ?>" <?= $currency['code'] === $c['code'] ? 'selected' : '' ?>>
                            <?= e($c['code']) ?> <?= e($c['symbol']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>

            <!-- Sélecteur de pays de livraison -->
            <form id="country-form" class="top-bar__form">
                <i class="fas fa-globe" aria-hidden="true"></i>
                <label class="visually-hidden" for="country-select">Pays de livraison</label>
                <select id="country-select" name="country" title="Pays de livraison">
                    <option value="">Pays de livraison</option>
                    <?php foreach ($countries as $c): ?>
                        <option value="<?= e($c['iso_alpha2']) ?>" <?= (!empty($_COOKIE['delivery_country']) && $_COOKIE['delivery_country'] === $c['iso_alpha2']) ? 'selected' : '' ?>>
                            <?= e($c['name']) ?> <?= nt('(' . $c['phone_code'] . ')') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>

            <!-- Langue de traduction automatique -->
            <form id="lang-form" class="top-bar__form">
                <i class="fas fa-language" aria-hidden="true"></i>
                <label class="visually-hidden" for="lang-select">Langue</label>
                <select id="lang-select" name="lang" title="Langue de traduction automatique">
                    <?php foreach (languages() as $code => $data): ?>
                        <option value="<?= e($code) ?>" <?= $lang === $code ? 'selected' : '' ?> data-flag="<?= e($data['flag']) ?>" data-rtl="<?= $data['rtl'] ? 'true' : 'false' ?>">
                            <?= e($data['flag']) ?> <?= $code === 'fr' ? e($data['native']) : e($data['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <div class="top-bar__right">
            <a href="connexion.php" class="top-bar__link"><i class="fas fa-sign-in-alt"></i> Connexion</a>
            <a href="inscription.php" class="top-bar__link"><i class="fas fa-user-plus"></i> Inscription</a>
        </div>
    </div>
</header>

<!-- ======================= EN-TÊTE COLLANT ======================= -->
<header class="main-header">
    <div class="container main-header__inner">
        <a href="index.php" class="logo">
            <img src="<?= ASSETS_URL ?>/images/logo.svg" alt="" class="logo__icon">
            <span class="logo__text notranslate" translate="no"><?= e($siteName) ?></span>
        </a>

        <form class="search-bar" action="recherche.php" method="GET" id="search-form" aria-label="Recherche">
            <input type="search" name="q" id="search-input" placeholder="Rechercher des produits…" value="<?= e($_GET['q'] ?? '') ?>">
            <button type="submit"><i class="fas fa-search"></i></button>
        </form>

        <div class="main-header__right">
            <a href="compte.php" class="main-header__link account-link"><i class="fas fa-user"></i> Mon compte</a>
            <a href="panier.php" class="main-header__link cart-link"><i class="fas fa-shopping-cart"></i> <span class="cart-label">Panier</span> <span class="cart-count"><?= (int)$cartCount ?></span></a>
        </div>

        <button class="menu-toggle" id="menu-toggle" aria-label="Ouvrir le menu" aria-expanded="false"><i class="fas fa-bars"></i></button>
    </div>
</header>

<!-- Menu catégories (simple en phase 1, méga-menu en phase 2) -->
<nav class="category-nav">
    <div class="container">
        <ul class="category-list">
            <?php foreach ($topCategories as $cat): ?>
                <li>
                    <a href="catalogue.php?category=<?= e($cat['slug']) ?>"><?= e($cat['name']) ?></a>
                    <?php if (!empty($allSubCategories[(int)$cat['id']])): ?>
                        <ul class="sub-list">
                            <?php foreach ($allSubCategories[(int)$cat['id']] as $sub): ?>
                                <li><a href="catalogue.php?category=<?= e($sub['slug']) ?>"><?= e($sub['name']) ?></a></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</nav>

<!-- Tiroir menu mobile -->
<div class="mobile-drawer" id="mobile-drawer" aria-hidden="true">
    <div class="mobile-drawer__overlay" id="drawer-overlay"></div>
    <div class="mobile-drawer__panel" role="dialog" aria-modal="true" aria-label="Menu de navigation">
        <div class="mobile-drawer__head">
            <h3 class="mobile-drawer__title">Menu</h3>
            <button class="mobile-drawer__close" id="drawer-close" aria-label="Fermer le menu"><i class="fas fa-times"></i></button>
        </div>
        <p class="mobile-drawer__label">Catégories</p>
        <ul class="mobile-drawer__list">
            <?php foreach ($topCategories as $cat): ?>
                <li><a href="catalogue.php?category=<?= e($cat['slug']) ?>"><?= e($cat['name']) ?></a></li>
            <?php endforeach; ?>
            <li><a href="catalogue.php"><i class="fas fa-th"></i> Catalogue complet</a></li>
        </ul>
        <p class="mobile-drawer__label">Mon espace</p>
        <ul class="mobile-drawer__list">
            <li><a href="compte.php"><i class="fas fa-user"></i> Mon compte</a></li>
            <li><a href="panier.php"><i class="fas fa-shopping-cart"></i> Panier <span class="cart-count"><?= (int)$cartCount ?></span></a></li>
            <li><a href="connexion.php"><i class="fas fa-sign-in-alt"></i> Connexion</a></li>
            <li><a href="inscription.php"><i class="fas fa-user-plus"></i> Inscription</a></li>
        </ul>
        <p class="mobile-drawer__note">Traduction automatique. La version française fait foi.</p>
    </div>
</div>

<main class="site-main" id="main-content">
