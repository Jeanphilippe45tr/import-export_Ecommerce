<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/db.php';

// -----------------------------------------------------------------------------
// Échappement des sorties (toutes les affichages doivent passer par e())
// -----------------------------------------------------------------------------
if (!function_exists('e')) {
    function e($value = null): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}

/**
 * Enveloppe un contenu dans un élément non traduit par Google :
 * classe notranslate + attribut translate="no".
 * À utiliser sur : marque/logo, références produits, prix et codes de devise,
 * numéros de commande/facture, numéros de téléphone, emails, messages du chat.
 */
if (!function_exists('nt')) {
    function nt(string $value): string
    {
        return '<span class="notranslate" translate="no">' . e($value) . '</span>';
    }
}

// -----------------------------------------------------------------------------
// Session sécurisée (httponly, secure si HTTPS, regenerate_id)
// -----------------------------------------------------------------------------
if (!function_exists('startSecureSession')) {
    function startSecureSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.cookie_httponly', '1');
            ini_set('session.use_only_cookies', '1');
            if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
                ini_set('session.cookie_secure', '1');
            }
            ini_set('session.cookie_samesite', 'Lax');
            session_name('import_export_session');
            session_start();
        }

        if (empty($_SESSION['__sec'])) {
            session_regenerate_id(true);
            $_SESSION['__sec'] = true;
            $_SESSION['__created'] = time();
            $_SESSION['__ua'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
        } elseif (!empty($_SESSION['__created']) && (int)$_SESSION['__created'] < time() - 86400) {
            session_regenerate_id(true);
            $_SESSION['__created'] = time();
        }
    }
}

// -----------------------------------------------------------------------------
// Jetons CSRF (à insérer dans tous les formulaires)
// -----------------------------------------------------------------------------
if (!function_exists('csrfToken')) {
    function csrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('csrfField')) {
    function csrfField(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . e(csrfToken()) . '">';
    }
}

if (!function_exists('csrfCheck')) {
    function csrfCheck(string $token): bool
    {
        if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
            return false;
        }
        return true;
    }
}

// -----------------------------------------------------------------------------
// Messages flash (une seule affichage)
// -----------------------------------------------------------------------------
if (!function_exists('flash')) {
    function flash(string $key, ?string $value = null): ?string
    {
        if ($value !== null) {
            $_SESSION['flash'][$key] = $value;
            return null;
        }
        $v = $_SESSION['flash'][$key] ?? null;
        unset($_SESSION['flash'][$key]);
        return $v;
    }
}

if (!function_exists('hasFlash')) {
    function hasFlash(string $key): bool
    {
        return !empty($_SESSION['flash'][$key] ?? null);
    }
}

// -----------------------------------------------------------------------------
// Redirection et alias d'URL
// -----------------------------------------------------------------------------
if (!function_exists('redirect')) {
    function redirect(string $url): void
    {
        header('Location: ' . $url, true, 302);
        exit;
    }
}

if (!function_exists('url')) {
    function url(string $path = ''): string
    {
        $path = ltrim($path, '/');
        return APP_URL === '' ? '/' . $path : rtrim(APP_URL, '/') . '/' . $path;
    }
}

if (!function_exists('productImagePath')) {
    /** Chemin de l'image principale d'un produit (base de données, puis repli). */
    function productImagePath(PDO $database, int $productId, string $reference = ''): string
    {
        static $cache = [];
        if (!array_key_exists($productId, $cache)) {
            $stmt = $database->prepare(
                "SELECT image FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, sort_order ASC, id ASC LIMIT 1"
            );
            $stmt->execute([$productId]);
            $row = $stmt->fetch();
            $cache[$productId] = $row !== false && !empty($row['image'])
                ? (string)$row['image']
                : ($reference !== '' ? 'uploads/images/products/' . strtolower($reference) . '-1.jpg' : '');
        }
        return $cache[$productId];
    }
}

if (!function_exists('imageUrl')) {
    /**
     * URL publique d'une image produit, avec repli si le fichier est absent.
     * Les chemins en base sont du type uploads/images/products/xxx.jpg.
     */
    function imageUrl(?string $path = null, string $reference = ''): string
    {
        $path = trim((string)$path);
        if ($path === '' && $reference !== '') {
            $path = 'uploads/images/products/' . strtolower($reference) . '-1.jpg';
        }
        if ($path === '') {
            return ASSETS_URL . '/images/placeholder.svg';
        }
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }
        $relative = ltrim($path, '/');
        $absolute = url($relative);
        $diskPath = APP_DIR . '/' . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        if (!file_exists($diskPath)) {
            return ASSETS_URL . '/images/placeholder.svg';
        }
        return $absolute;
    }
}

// -----------------------------------------------------------------------------
// Slug simple (français)
// -----------------------------------------------------------------------------
if (!function_exists('slugify')) {
    function slugify(string $string): string
    {
        $string = mb_strtolower($string, 'UTF-8');
        $string = htmlentities($string, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $string = preg_replace('/&([a-z])(?:acute|cedil|circ|grave|orn|ring|slash|th|tilde|uml);/', '$1', $string);
        $string = preg_replace('/&[a-z]+;/', '', $string);
        $string = preg_replace('/[^a-z0-9]+/', '-', $string);
        return trim($string, '-');
    }
}

// -----------------------------------------------------------------------------
// Paramètres du site (table settings)
// -----------------------------------------------------------------------------
if (!function_exists('loadSettings')) {
    function loadSettings(PDO $database): array
    {
        $stmt = $database->query("SELECT `key`, `value` FROM settings");
        $out = [];
        while ($row = $stmt->fetch()) {
            $out[$row['key']] = $row['value'];
        }
        return $out;
    }
}

if (!function_exists('setting')) {
    function setting(PDO $database, string $key, mixed $default = null): mixed
    {
        static $cache = null;
        if ($cache === null) {
            $cache = loadSettings($database);
        }
        return $cache[$key] ?? $default;
    }
}

// -----------------------------------------------------------------------------
// Devises
// -----------------------------------------------------------------------------
if (!function_exists('getCurrencies')) {
    function getCurrencies(PDO $database): array
    {
        $stmt = $database->query(
            "SELECT * FROM currencies WHERE is_active = 1 ORDER BY sort_order ASC, code ASC"
        );
        return $stmt->fetchAll();
    }
}

if (!function_exists('getCurrency')) {
    function getCurrency(PDO $database, string $code): ?array
    {
        $stmt = $database->prepare(
            "SELECT * FROM currencies WHERE code = ? AND is_active = 1 LIMIT 1"
        );
        $stmt->execute([$code]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }
}

if (!function_exists('getDefaultCurrency')) {
    function getDefaultCurrency(PDO $database): array
    {
        $stmt = $database->query(
            "SELECT * FROM currencies WHERE is_default = 1 AND is_active = 1 LIMIT 1"
        );
        $row = $stmt->fetch();
        if ($row !== false) {
            return $row;
        }
        $all = getCurrencies($database);
        return $all[0] ?? ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'exchange_rate' => 1.000000];
    }
}

/**
 * Détecte la devise du visiteur via le cookie 'currency' (choix mémorisé),
 * sinon la devise par défaut configurée par l'admin.
 */
if (!function_exists('detectCurrency')) {
    function detectCurrency(PDO $database): array
    {
        $code = $_COOKIE['currency'] ?? null;
        if ($code !== null) {
            $currency = getCurrency($database, $code);
            if ($currency !== null) {
                return $currency;
            }
        }
        return getDefaultCurrency($database);
    }
}

/**
 * Convertit un montant d'une devise vers une autre.
 * Les taux sont stockés par rapport à la devise de base (EUR).
 */
if (!function_exists('convertAmount')) {
    function convertAmount(float $amount, array $fromCurrency, array $toCurrency): float
    {
        if ($fromCurrency['code'] === $toCurrency['code']) {
            return (float)$amount;
        }
        $inBase = (float)$amount / (float)$fromCurrency['exchange_rate'];
        return $inBase * (float)$toCurrency['exchange_rate'];
    }
}

/**
 * Formate un prix pour affichage (prix + symbole devise non traduisible).
 */
if (!function_exists('formatPrice')) {
    function formatPrice(float $amount, array $currency): string
    {
        return number_format(round((float)$amount, 2), 2, ',', ' ')
            . ' <span class="currency notranslate" translate="no">'
            . e($currency['symbol']) . '</span>';
    }
}

// -----------------------------------------------------------------------------
// Pays
// -----------------------------------------------------------------------------
if (!function_exists('getCountries')) {
    function getCountries(PDO $database, bool $onlyDeliverable = true): array
    {
        $sql = "SELECT * FROM countries";
        if ($onlyDeliverable) {
            $sql .= " WHERE is_blocked = 0";
        }
        $sql .= " ORDER BY sort_order ASC, name ASC";
        $stmt = $database->query($sql);
        return $stmt->fetchAll();
    }
}

if (!function_exists('getCountry')) {
    function getCountry(PDO $database, string $iso): ?array
    {
        $stmt = $database->prepare(
            "SELECT * FROM countries WHERE iso_alpha2 = ? LIMIT 1"
        );
        $stmt->execute([$iso]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }
}

// -----------------------------------------------------------------------------
// TVA
// -----------------------------------------------------------------------------
if (!function_exists('getVatRate')) {
    function getVatRate(PDO $database, string $iso): float
    {
        $stmt = $database->prepare(
            "SELECT rate FROM vat_rates WHERE country_iso_alpha2 = ? LIMIT 1"
        );
        $stmt->execute([$iso]);
        $row = $stmt->fetch();
        return $row !== false ? (float)$row['rate'] : 0.0;
    }
}

if (!function_exists('vatEnabled')) {
    function vatEnabled(PDO $database): bool
    {
        return (string)(setting($database, 'vat_enabled', '1')) === '1';
    }
}

// -----------------------------------------------------------------------------
// Zones et frais de livraison
// -----------------------------------------------------------------------------
if (!function_exists('getShippingZones')) {
    function getShippingZones(PDO $database): array
    {
        $stmt = $database->query(
            "SELECT * FROM shipping_zones WHERE is_active = 1 ORDER BY sort_order ASC, name ASC"
        );
        return $stmt->fetchAll();
    }
}

if (!function_exists('getShippingRateForCountry')) {
    /**
     * Retourne le tarif de livraison applicable pour un pays donné.
     * Cherche la zone contenant le pays, puis le premier taux actif de cette zone.
     */
    function getShippingRateForCountry(PDO $database, string $iso): ?array
    {
        $country = getCountry($database, $iso);
        if ($country === null) {
            return null;
        }
        $stmt = $database->prepare(
            "SELECT r.*, z.name AS zone_name
             FROM shipping_rates r
             JOIN shipping_zone_countries zc ON zc.zone_id = r.zone_id
             JOIN shipping_zones z ON z.id = r.zone_id
             WHERE zc.country_id = ? AND r.is_active = 1 AND z.is_active = 1
             ORDER BY z.sort_order ASC, r.id ASC
             LIMIT 1"
        );
        $stmt->execute([(int)$country['id']]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }
}

// -----------------------------------------------------------------------------
// Catégories
// -----------------------------------------------------------------------------
if (!function_exists('getCategories')) {
    function getCategories(PDO $database, ?int $parentId = null): array
    {
        if ($parentId === null) {
            $stmt = $database->query(
                "SELECT * FROM categories WHERE parent_id IS NULL AND is_active = 1
                 ORDER BY sort_order ASC, name ASC"
            );
        } else {
            $stmt = $database->prepare(
                "SELECT * FROM categories WHERE parent_id = ? AND is_active = 1
                 ORDER BY sort_order ASC, name ASC"
            );
            $stmt->execute([$parentId]);
        }
        return $stmt->fetchAll();
    }
}

if (!function_exists('getCategoryBySlug')) {
    function getCategoryBySlug(PDO $database, string $slug): ?array
    {
        $stmt = $database->prepare(
            "SELECT * FROM categories WHERE slug = ? AND is_active = 1 LIMIT 1"
        );
        $stmt->execute([$slug]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }
}
