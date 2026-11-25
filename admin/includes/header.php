<?php
declare(strict_types=1);

// Contexte administration : évite de charger deux fois l'en-tête/pied publics.
if (!defined('ADMIN_CONTEXT')) {
    define('ADMIN_CONTEXT', true);
}

require_once __DIR__ . '/auth.php';
adminRequire();

$db = db();
$settings = loadSettings($db);
$admin = adminGet();

$siteName = !empty($settings['site_name']) ? $settings['site_name'] : 'EuroImport Export';

$adminPageKey = basename($_SERVER['PHP_SELF'], '.php');
$adminPageLabels = [
    'dashboard' => 'Tableau de bord', 'articles' => 'Articles', 'article-edit' => 'Édition article',
    'categories' => 'Catégories', 'currencies' => 'Devises', 'commandes' => 'Commandes',
    'commande-detail' => 'Détail commande', 'clients' => 'Clients', 'client-detail' => 'Détail client',
    'newsletter' => 'Newsletter', 'messagerie' => 'Messagerie', 'livraison' => 'Livraison',
    'pays' => 'Pays', 'tva' => 'TVA', 'factures' => 'Factures', 'settings' => 'Paramètres',
    'admins' => 'Administrateurs', 'profil' => 'Mon profil',
];
$adminPageLabel = $adminPageLabels[$adminPageKey] ?? ucfirst(str_replace('-', ' ', $adminPageKey));

?>
<!DOCTYPE html>
<html lang="fr" dir="ltr">
<head>
<base href="<?= url('admin/') ?>">
<script>window.IE_APP_URL = <?= json_encode(APP_URL, JSON_UNESCAPED_SLASHES) ?>;</script>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($siteName) ?> — <?= e($adminPageLabel) ?></title>
<meta name="robots" content="noindex,nofollow">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="<?= ASSETS_URL ?>/css/admin.css?v=1.3.1">
</head>
<body class="admin">
<div class="admin-wrapper">

<!-- Sidebar -->
<aside class="admin-sidebar">
    <button class="admin-sidebar__close" id="adminSidebarClose" aria-label="Fermer le menu"><i class="fas fa-times"></i></button>
    <div class="admin-brand">
        <img src="<?= ASSETS_URL ?>/images/logo.svg" alt="" class="admin-brand__icon">
        <div>
            <div class="admin-brand__logo"><?= e($siteName) ?></div>
            <div class="admin-brand__sous">Espace administration</div>
        </div>
    </div>
    <nav class="admin-nav">
        <p class="admin-nav__label">Vue d'ensemble</p>
        <a href="dashboard.php" class="admin-nav__link<?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? ' active' : '' ?>">
            <i class="fas fa-chart-pie"></i> Tableau de bord
        </a>
        <p class="admin-nav__label">Catalogue</p>
        <a href="articles.php" class="admin-nav__link<?= basename($_SERVER['PHP_SELF']) === 'articles.php' ? ' active' : '' ?>">
            <i class="fas fa-box"></i> Articles
        </a>
        <a href="categories.php" class="admin-nav__link<?= basename($_SERVER['PHP_SELF']) === 'categories.php' ? ' active' : '' ?>">
            <i class="fas fa-layer-group"></i> Catégories
        </a>
        <a href="currencies.php" class="admin-nav__link<?= basename($_SERVER['PHP_SELF']) === 'currencies.php' ? ' active' : '' ?>">
            <i class="fas fa-money-bill-wave"></i> Devises
        </a>
        <p class="admin-nav__label">Ventes & clients</p>
        <a href="commandes.php" class="admin-nav__link<?= in_array(basename($_SERVER['PHP_SELF']), ['commandes.php','commande-detail.php']) ? ' active' : '' ?>">
            <i class="fas fa-shopping-bag"></i> Commandes
        </a>
        <a href="clients.php" class="admin-nav__link<?= basename($_SERVER['PHP_SELF']) === 'clients.php' ? ' active' : '' ?>">
            <i class="fas fa-users"></i> Clients
        </a>
        <a href="newsletter.php" class="admin-nav__link<?= basename($_SERVER['PHP_SELF']) === 'newsletter.php' ? ' active' : '' ?>">
            <i class="fas fa-envelope"></i> Newsletter
        </a>
        <a href="messagerie.php" class="admin-nav__link<?= basename($_SERVER['PHP_SELF']) === 'messagerie.php' ? ' active' : '' ?>">
            <i class="fas fa-comments"></i> Messagerie
            <span class="admin-nav__badge" id="admin-nav-badge"><?= (int)($_SESSION['admin_unread_count'] ?? 0) ?></span>
        </a>
        <p class="admin-nav__label">Configuration</p>
        <a href="livraison.php" class="admin-nav__link<?= basename($_SERVER['PHP_SELF']) === 'livraison.php' ? ' active' : '' ?>">
            <i class="fas fa-truck"></i> Livraison
        </a>
        <a href="pays.php" class="admin-nav__link<?= basename($_SERVER['PHP_SELF']) === 'pays.php' ? ' active' : '' ?>">
            <i class="fas fa-globe"></i> Pays
        </a>
        <a href="tva.php" class="admin-nav__link<?= basename($_SERVER['PHP_SELF']) === 'tva.php' ? ' active' : '' ?>">
            <i class="fas fa-percentage"></i> TVA
        </a>
        <a href="factures.php" class="admin-nav__link<?= basename($_SERVER['PHP_SELF']) === 'factures.php' ? ' active' : '' ?>">
            <i class="fas fa-file-invoice"></i> Factures
        </a>
        <a href="settings.php" class="admin-nav__link<?= basename($_SERVER['PHP_SELF']) === 'settings.php' ? ' active' : '' ?>">
            <i class="fas fa-cog"></i> Paramètres
        </a>
        <?php if ($admin['role'] === 'superadmin'): ?>
            <a href="admins.php" class="admin-nav__link<?= basename($_SERVER['PHP_SELF']) === 'admins.php' ? ' active' : '' ?>">
                <i class="fas fa-user-shield"></i> Administrateurs
            </a>
        <?php endif; ?>
        <a href="profil.php" class="admin-nav__link<?= basename($_SERVER['PHP_SELF']) === 'profil.php' ? ' active' : '' ?>">
            <i class="fas fa-user-circle"></i> Profil
        </a>
        <a href="../api/auth.php?action=logout" class="admin-nav__link admin-nav__link--logout">
            <i class="fas fa-sign-out-alt"></i> Déconnexion
        </a>
    </nav>
</aside>
<div class="admin-sidebar-overlay" id="adminSidebarOverlay"></div>

<!-- Contenu principal -->
<main class="admin-main">
    <?php if (hasFlash('success')): ?>
        <div style="margin:14px 22px 0; padding:10px 14px; border-radius:6px; background:#ecfdf5; border:1px solid #a7f3d0; color:#065f46; font-size:13px;">
            <i class="fas fa-check-circle"></i> <?= e(flash('success')) ?>
        </div>
    <?php endif; ?>
    <?php if (hasFlash('error')): ?>
        <div style="margin:14px 22px 0; padding:10px 14px; border-radius:6px; background:#fef2f2; border:1px solid #fecaca; color:#991b1b; font-size:13px;">
            <i class="fas fa-exclamation-circle"></i> <?= e(flash('error')) ?>
        </div>
    <?php endif; ?>
    <header class="admin-bar">
        <button class="admin-bar__toggle" id="adminSidebarToggle" aria-label="Ouvrir le menu"><i class="fas fa-bars"></i></button>
        <div class="admin-bar__location">
            <span class="admin-bar__eyebrow">Administration</span>
            <span class="admin-bar__page"><?= e($adminPageLabel) ?></span>
        </div>
        <div class="admin-bar__actions">
            <a href="<?= e(url('')) ?>" class="admin-bar__site" target="_blank" rel="noopener"><i class="fas fa-external-link-alt"></i> Voir le site</a>
            <div class="admin-bar__user">
                <span class="admin-bar__avatar"><i class="fas fa-user-shield"></i></span>
                <span class="admin-bar__user-text">
                    <span class="admin-bar__user-name"><?= e($admin['username']) ?></span>
                    <span class="admin-bar__user-role"><?= e($admin['role']) ?></span>
                </span>
            </div>
        </div>
    </header>

    <div class="admin-content">
