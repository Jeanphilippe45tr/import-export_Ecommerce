<?php
declare(strict_types=1);

/**
 * Entrée de l'espace administration.
 * Redirige vers le tableau de bord (qui renvoie vers la connexion si besoin).
 */
require_once __DIR__ . '/includes/config_bootstrap.php';

redirect(url('admin/dashboard.php'));
