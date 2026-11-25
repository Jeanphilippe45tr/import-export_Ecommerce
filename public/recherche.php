<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/header.php';

$db = db();
$currency = detectCurrency($db);

$q = trim($_GET['q'] ?? '');
$sort = $_GET['sort'] ?? 'new';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 12;
$offset = ($page - 1) * $perPage;

$products = [];
$total = 0;

if ($q !== '') {
    $like = '%' . $q . '%';
    $countStmt = $db->prepare("SELECT COUNT(*) AS total FROM products WHERE is_active = 1 AND (name LIKE ? OR reference LIKE ? OR description LIKE ?)");
    $countStmt->execute([$like, $like, $like]);
    $total = (int)$countStmt->fetch()['total'];

    switch ($sort) {
        case 'price_asc': $order = 'price ASC'; break;
        case 'price_desc': $order = 'price DESC'; break;
        case 'rating': $order = 'rating DESC, created_at DESC'; break;
        default: $order = 'is_featured DESC, created_at DESC'; break;
    }

    $stmt = $db->prepare(
        "SELECT p.*, c.name AS category_name, c.slug AS category_slug
         FROM products p
         LEFT JOIN categories c ON p.category_id = c.id
         WHERE p.is_active = 1 AND (p.name LIKE ? OR p.reference LIKE ? OR p.description LIKE ?)
         ORDER BY $order
         LIMIT ? OFFSET ?"
    );
    $stmt->bindValue(1, $like, PDO::PARAM_STR);
    $stmt->bindValue(2, $like, PDO::PARAM_STR);
    $stmt->bindValue(3, $like, PDO::PARAM_STR);
    $stmt->bindValue(4, $perPage, PDO::PARAM_INT);
    $stmt->bindValue(5, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $products = $stmt->fetchAll();
}

$totalPages = max(1, (int)ceil($total / $perPage));

$pageTitle = 'Recherche — ' . e($siteName);
$pageDescription = 'Résultats de recherche pour « ' . e($q) . ' » sur ' . e($siteName);

require_once INCLUDES_DIR . '/header.php';
?>

<section class="section">
    <div class="container">
        <nav class="breadcrumbs" style="margin-bottom:16px; font-size:12px; color:var(--color-gray-light);">
            <a href="index.php">Accueil</a> <span style="color:var(--color-gray); margin:0 6px;">›</span>
            <strong>Recherche : <?= e($q) ?></strong>
        </nav>

        <div class="section__title" style="display:flex; align-items:center; gap:8px;">
            <i class="fas fa-search"></i> Résultats pour « <?= e($q) ?> »
            <span style="font-weight:500; font-size:13px; color:var(--color-gray); margin-left:auto;"><?= (int)$total ?> résultat<?= $total > 1 ? 's' : '' ?></span>
        </div>

        <?php if ($products): ?>
            <div class="product-grid">
                <?php foreach ($products as $product): ?>
                    <div class="product-card">
                        <div class="product-card__image">
                            <?php
                            $img = imageUrl(productImagePath($db, (int)$product['id'], $product['reference']));
                            ?>
                            <a href="produit.php?slug=<?= e($product['reference']) ?>">
                                <img src="<?= e($img) ?>" alt="<?= e($product['name']) ?>" loading="lazy">
                            </a>
                        </div>
                        <div class="product-card__stars">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <span class="star"><?= $i <= (int)$product['rating'] ? '★' : '☆' ?></span>
                            <?php endfor; ?>
                        </div>
                        <div class="product-card__title">
                            <a href="produit.php?slug=<?= e($product['reference']) ?>" style="color:inherit;"><?= e($product['name']) ?></a>
                        </div>
                        <div class="product-card__meta">
                            <span class="notranslate" translate="no"><?= e($product['reference']) ?></span>
                        </div>
                        <div class="product-card__price">
                            <?= formatPrice(convertAmount((float)$product['price'], ['code' => 'EUR', 'exchange_rate' => 1.000000], $currency), $currency) ?>
                        </div>
                        <div class="product-card__actions">
                            <a href="produit.php?slug=<?= e($product['reference']) ?>" class="btn btn--primary btn--sm btn--full">Voir l'article</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($totalPages > 1): ?>
                <nav class="catalogue-pagination" style="margin-top:28px; display:flex; gap:6px; justify-content:center; flex-wrap:wrap;">
                    <?php
                    $queryParts = ['q=' . urlencode($q)];
                    if ($sort !== 'new') { $queryParts[] = 'sort=' . $sort; }
                    $queryString = '?' . implode('&', $queryParts);
                    ?>
                    <?php if ($page > 1): ?>
                        <a href="<?= $queryString ?>&page=<?= ($page - 1) ?>" class="btn btn--outline btn--sm">Précédent</a>
                    <?php endif; ?>
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <?php if ($i === 1 || $i === $totalPages || abs($i - $page) <= 2): ?>
                            <a href="<?= $queryString ?>&page=<?= $i ?>" class="btn <?= $i === $page ? 'btn--primary' : 'btn--outline' ?> btn--sm"><?= $i ?></a>
                        <?php elseif (($i === 2 && $page > 4) || ($i === $totalPages - 1 && $page < $totalPages - 3)): ?>
                            <span style="padding:6px 10px; color:var(--color-gray-light);">…</span>
                        <?php endif; ?>
                    <?php endfor; ?>
                    <?php if ($page < $totalPages): ?>
                        <a href="<?= $queryString ?>&page=<?= ($page + 1) ?>" class="btn btn--outline btn--sm">Suivant</a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-search"></i>
                <p>Aucun article ne correspond à « <?= e($q) ?> ».</p>
                <a href="catalogue.php" class="btn btn--primary btn--sm" style="margin-top:12px;">Voir tout le catalogue</a>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php
require_once INCLUDES_DIR . '/footer.php';
?>
