<?php
declare(strict_types=1);

/**
 * Bootstrap minimal pour les fichiers API qui n'ont pas besoin du header HTML.
 * Charge config, DB et fonctions communes.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

startSecureSession();
