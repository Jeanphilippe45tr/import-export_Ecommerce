<?php
declare(strict_types=1);

/**
 * Configuration centrale du site EuroImport Export.
 * Fichier unique à éditer pour les identifiants de base de données et les constantes du site.
 * Aucun fichier de langue n'est utilisé (traduction automatique côté navigateur uniquement).
 */

if (defined('CONFIG_LOADED')) {
    return;
}
define('CONFIG_LOADED', 1);

define('DS', DIRECTORY_SEPARATOR);
define('CONFIG_DIR', __DIR__);
define('APP_DIR', __DIR__ . DS . '..');
define('INCLUDES_DIR', APP_DIR . DS . 'includes');
define('PUBLIC_DIR', APP_DIR . DS . 'public');
define('ADMIN_DIR', APP_DIR . DS . 'admin');
define('API_DIR', APP_DIR . DS . 'api');
define('UPLOADS_DIR', APP_DIR . DS . 'uploads');
define('IMAGES_DIR', UPLOADS_DIR . DS . 'images');
define('ASSETS_DIR', PUBLIC_DIR . DS . 'assets');

// URL publique du site : détectée automatiquement depuis l'URL demandée.
// Fonctionne à la racine du domaine ou dans un sous-dossier (ex. /import-export).
$appDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
$appDir = rtrim($appDir, '/');
// On retire tous les segments applicatifs (ex. /api/admin), pas seulement le premier.
do {
    $changed = false;
    foreach (['/public', '/admin', '/api'] as $appSuffix) {
        if ($appDir !== '' && substr($appDir, -strlen($appSuffix)) === $appSuffix) {
            $appDir = substr($appDir, 0, -strlen($appSuffix));
            $changed = true;
            break;
        }
    }
} while ($changed);
define('APP_URL', ($appDir === '' || $appDir === '.' || $appDir === '/') ? '' : $appDir);
define('ASSETS_URL', APP_URL . '/public/assets');
define('UPLOADS_URL', APP_URL . '/uploads/images');

// -----------------------------------------------------
// Base de données (à adapter selon l'hébergeur cPanel)
// -----------------------------------------------------
define('DB_HOST', 'localhost');
define('DB_NAME', 'import_export');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_PORT', 3306);
define('DB_CHARSET', 'utf8mb4');

// -----------------------------------------------------
// Langue par défaut du site : français uniquement.
// Les contenus saisis en back-office sont en français.
// La traduction des textes affichés se fait par le widget Google Website Translator.
// -----------------------------------------------------
define('LANG_DEFAULT', 'fr');

// Fuseau horaire de l'administrateur (modifiable dans les réglages admin)
define('TIMEZONE_DEFAULT', 'Europe/Paris');

// Durée de conservation des données (RGPD, affichée dans la politique de confidentialité)
define('RETENTION_ORDERS_DAYS', 3650);      // 10 ans
define('RETENTION_CONVERSATIONS_DAYS', 365); // 12 mois

// -----------------------------------------------------
// Langues proposées par le widget de traduction automatique
// Tableau unique, facile à modifier. Codes acceptés par Google Translate.
// -----------------------------------------------------
$GLOBALS['LANGUAGES'] = [
    'fr'    => ['name' => 'Français',     'native' => 'Français (original)',   'flag' => '🇫🇷', 'rtl' => false],
    'en'    => ['name' => 'Anglais',      'native' => 'English',               'flag' => '🇬🇧', 'rtl' => false],
    'es'    => ['name' => 'Espagnol',     'native' => 'Español',               'flag' => '🇪🇸', 'rtl' => false],
    'de'    => ['name' => 'Allemand',     'native' => 'Deutsch',               'flag' => '🇩🇪', 'rtl' => false],
    'it'    => ['name' => 'Italien',      'native' => 'Italiano',              'flag' => '🇮🇹', 'rtl' => false],
    'pt'    => ['name' => 'Portugais',    'native' => 'Português',             'flag' => '🇵🇹', 'rtl' => false],
    'nl'    => ['name' => 'Néerlandais',  'native' => 'Nederlands',            'flag' => '🇳🇱', 'rtl' => false],
    'ar'    => ['name' => 'Arabe',        'native' => 'العربية',                'flag' => '🇸🇦', 'rtl' => true],
    'zh-CN' => ['name' => 'Chinois simplifié', 'native' => '中文（简体）',      'flag' => '🇨🇳', 'rtl' => false],
    'ru'    => ['name' => 'Russe',        'native' => 'Русский',               'flag' => '🇷🇺', 'rtl' => false],
    'tr'    => ['name' => 'Turc',         'native' => 'Türkçe',                'flag' => '🇹🇷', 'rtl' => false],
];

function languages(): array
{
    return $GLOBALS['LANGUAGES'];
}

function languageExists(string $code): bool
{
    return isset($GLOBALS['LANGUAGES'][$code]);
}

function languageIsRtl(string $code): bool
{
    return languageExists($code) && $GLOBALS['LANGUAGES'][$code]['rtl'] === true;
}

function languageLabel(string $code): string
{
    $list = languages();
    return isset($list[$code]) ? $list[$code]['name'] : $code;
}

/**
 * Détecte la langue active via le cookie Google `googtrans` (format /fr/CODE).
 * Retourne le code 'fr' par défaut si aucun cookie valide n'est présent.
 */
function currentLanguageFromCookie(): string
{
    $code = LANG_DEFAULT;
    if (isset($_COOKIE['googtrans'])) {
        $value = trim((string)$_COOKIE['googtrans']);
        if (preg_match('#^/fr/([a-zA-Z0-9_-]+)$#', $value, $m)) {
            $candidate = $m[1];
            if (languageExists($candidate)) {
                $code = $candidate;
            }
        }
    }
    return $code;
}

function currentLanguageIsRtl(): bool
{
    return languageIsRtl(currentLanguageFromCookie());
}
