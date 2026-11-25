<?php
declare(strict_types=1);

/**
 * Bootstrap pour les pages publiques qui doivent exécuter de la logique
 * (redirections, formulaires) AVANT d'envoyer le HTML de l'en-tête.
 * Charge config, DB, fonctions, session et les variables de base.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

startSecureSession();

$db = db();
$settings = loadSettings($db);
$siteName = !empty($settings['site_name']) ? $settings['site_name'] : 'EuroImport Export';
