<?php
declare(strict_types=1);

/**
 * Point d'entrée du site.
 * L'accueil est dans public/index.php ; les pages publiques sont réécrites
 * vers le dossier public/ par le .htaccess à la racine.
 */
require_once __DIR__ . '/public/index.php';
