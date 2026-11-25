<?php
declare(strict_types=1);

/**
 * Bootstrap des pages admin : charge la session, l'authentification et les
 * variables communes SANS envoyer de HTML. Permet aux redirections et aux
 * traitements de formulaire de fonctionner avant l'en-tête admin.
 */
define('ADMIN_CONTEXT', true);

require_once __DIR__ . '/auth.php';
adminRequire();

$db = db();
$settings = loadSettings($db);
$admin = adminGet();

$siteName = !empty($settings['site_name']) ? $settings['site_name'] : 'EuroImport Export';
