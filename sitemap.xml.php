<?php
declare(strict_types=1);

/**
 * Sitemap.xml dynamique (SEO en français).
 * Génère les URLs du site : accueil, catalogue, catégories, produits, pages légales.
 */

require_once __DIR__ . '/includes/config_bootstrap.php';

$db = db();
$settings = loadSettings($db);

$siteName = !empty($settings['site_name']) ? $settings['site_name'] : 'EuroImport Export';
$scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? '';
$base = $scheme . '://' . $host . (APP_URL === '' ? '' : APP_URL);

header('Content-Type: application/xml; charset=utf-8');

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

function url($loc, $changefreq = 'weekly', $priority = '0.5', $lastmod = null) {
    global $base;
    $loc = $base . '/' . ltrim($loc, '/');
    echo '  <url>' . "\n";
    echo '    <loc>' . htmlspecialchars($loc, ENT_QUOTES | ENT_XML1, 'UTF-8') . '</loc>' . "\n";
    if ($lastmod) {
        echo '    <lastmod>' . htmlspecialchars($lastmod, ENT_QUOTES | ENT_XML1, 'UTF-8') . '</lastmod>' . "\n";
    }
    echo '    <changefreq>' . htmlspecialchars($changefreq, ENT_QUOTES | ENT_XML1, 'UTF-8') . '</changefreq>' . "\n";
    echo '    <priority>' . htmlspecialchars($priority, ENT_QUOTES | ENT_XML1, 'UTF-8') . '</priority>' . "\n";
    echo '  </url>' . "\n";
}

$now = gmdate('Y-m-d');

// Page d'accueil
url('index.php', 'daily', '0.9', $now);

// Catalogue
url('catalogue.php', 'daily', '0.8', $now);

// Recherche
url('recherche.php', 'monthly', '0.3', $now);

// Panier et commande (pas d'indexation prioritaire)
url('panier.php', 'monthly', '0.3', $now);
url('commande.php', 'monthly', '0.3', $now);

// Comptes
url('connexion.php', 'monthly', '0.2', $now);
url('inscription.php', 'monthly', '0.2', $now);
url('compte.php', 'monthly', '0.2', $now);

// Pages légales et institutionnelles
$legalPages = [
    'a-propos' => 'monthly',
    'contact' => 'monthly',
    'faq' => 'monthly',
    'livraison-douane' => 'monthly',
    'retours-retractation' => 'monthly',
    'cgv' => 'monthly',
    'politique-confidentialite' => 'monthly',
    'mentions-legales' => 'monthly',
];
foreach ($legalPages as $slug => $freq) {
    url('pages.php?slug=' . $slug, $freq, '0.4', $now);
}

// Catégories actives
try {
    $stmt = $db->query("SELECT slug, updated_at FROM categories WHERE is_active = 1");
    while ($cat = $stmt->fetch()) {
        url('catalogue.php?category=' . $cat['slug'], 'weekly', '0.6', $cat['updated_at'] ? date('Y-m-d', strtotime($cat['updated_at'])) : $now);
    }
} catch (Throwable $e) {
    // Ignorer en cas d'erreur (base non installée)
}

// Produits actifs
try {
    $stmt = $db->query("SELECT reference, updated_at FROM products WHERE is_active = 1");
    while ($p = $stmt->fetch()) {
        url('produit.php?slug=' . $p['reference'], 'weekly', '0.7', $p['updated_at'] ? date('Y-m-d', strtotime($p['updated_at'])) : $now);
    }
} catch (Throwable $e) {
    // Ignorer
}

echo '</urlset>' . "\n";
exit;
