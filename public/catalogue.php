<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/header.php';

$db = db();
$currency = detectCurrency($db);

// --- Récupération des paramètres de filtrage / tri ---
$categorySlug = $_GET['category'] ?? null;
$subSlug = $_GET['sub'] ?? null;
$q = trim($_GET['q'] ?? '');
$sort = $_GET['sort'] ?? 'new'; // new, price_asc, price_desc, rating
$priceMin = filter_input(INPUT_GET, 'price_min', FILTER_VALIDATE_FLOAT);
$priceMax = filter_input(INPUT_GET, 'price_max', FILTER_VALIDATE_FLOAT);
$rating = filter_input(INPUT_GET, 'rating', FILTER_VALIDATE_INT);
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 12;
$offset = ($page - 1) * $perPage;

// --- Catégorie courante ---
$currentCategory = null;
$currentSubCategory = null;
$breadcrumbs = [['label' => 'Accueil', 'url' => 'index.php']];
if ($categorySlug !== null) {
    $currentCategory = getCategoryBySlug($db, $categorySlug);
    if ($currentCategory) {
        $breadcrumbs[] = ['label' => e($currentCategory['name']), 'url' => 'catalogue.php?category=' . e($currentCategory['slug'])];
        if ($subSlug !== null) {
            $currentSubCategory = getCategoryBySlug($db, $subSlug);
            if ($currentSubCategory && (int)$currentSubCategory['parent_id'] === (int)$currentCategory['id']) {
                $breadcrumbs[] = ['label' => e($currentSubCategory['name']), 'url' => 'catalogue.php?category=' . e($currentCategory['slug']) . '&sub=' . e($currentSubCategory['slug'])];
            } else {
                $currentSubCategory = null;
            }
        }
    }
}

// --- Construction de la requête produits ---
$where = ['p.is_active = 1'];
$params = [];

if ($currentCategory) {
    // Inclure les sous-catégories de la catégorie courante
    $subIds = [$currentCategory['id']];
    $subs = getCategories($db, (int)$currentCategory['id']);
    foreach ($subs as $s) {
        $subIds[] = (int)$s['id'];
    }
    $placeholders = implode(',', array_fill(0, count($subIds), '?'));
    $where[] = "p.category_id IN ($placeholders)";
    $params = array_merge($params, $subIds);
}

if ($currentSubCategory) {
    $where[] = 'p.category_id = ?';
    $params[] = (int)$currentSubCategory['id'];
}

if ($q !== '') {
    $where[] = '(p.name LIKE ? OR p.reference LIKE ? OR p.description LIKE ?)';
    $like = '%' . $q . '%';
    $params = array_merge($params, [$like, $like, $like]);
}

if ($priceMin !== false && $priceMin !== null) {
    $where[] = 'p.price >= ?';
    $params[] = (float)$priceMin;
}
if ($priceMax !== false && $priceMax !== null) {
    $where[] = 'p.price <= ?';
    $params[] = (float)$priceMax;
}
if ($rating !== false && $rating !== null && $rating >= 1 && $rating <= 5) {
    $where[] = 'p.rating >= ?';
    $params[] = (int)$rating;
}

// Tri
switch ($sort) {
    case 'price_asc':
        $order = 'p.price ASC';
        break;
    case 'price_desc':
        $order = 'p.price DESC';
        break;
    case 'rating':
        $order = 'p.rating DESC, p.created_at DESC';
        break;
    case 'new':
    default:
        $order = 'p.is_featured DESC, p.created_at DESC';
        break;
}

$whereSql = implode(' AND ', $where);

// Nombre total pour pagination
$countSql = "SELECT COUNT(*) AS total FROM products p WHERE $whereSql";
$stmt = $db->prepare($countSql);
$stmt->execute($params);
$total = (int)$stmt->fetch()['total'];
$totalPages = max(1, (int)ceil($total / $perPage));

// Liste des produits
$sql = "SELECT p.*, c.name AS category_name, c.slug AS category_slug
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE $whereSql
        ORDER BY $order
        LIMIT ? OFFSET ?";
$stmt = $db->prepare($sql);
foreach ($params as $i => $p) {
    $stmt->bindValue($i + 1, $p, PDO::PARAM_STR);
}
$stmt->bindValue(count($params) + 1, $perPage, PDO::PARAM_INT);
$stmt->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
$stmt->execute();
$products = $stmt->fetchAll();

// --- Sous-catégories pour le filtre ---
$filterSubs = [];
if ($currentCategory) {
    $filterSubs = getCategories($db, (int)$currentCategory['id']);
}

// --- Toutes les catégories pour le menu latéral ---
$allCategories = getCategories($db);

$pageTitle = $currentCategory ? e($currentCategory['name']) . ' — ' . e($siteName) : e($siteName);
$pageDescription = 'Catalogue ' . e($siteName) . ' — ' . ($q !== '' ? 'Résultats pour : ' . e($q) : 'Retrouvez tous nos articles en promotion.');

require_once INCLUDES_DIR . '/header.php';
?>

<section class="section catalogue-header">
    <div class="container">
        <nav class="breadcrumbs" style="margin-bottom:16px; font-size:12px; color:var(--color-gray-light);">
            <?php foreach ($breadcrumbs as $i => $b): ?>
                <?php if ($i > 0): ?> <span style="color:var(--color-gray); margin:0 6px;">›</span> <?php endif; ?>
                <?php if ($i === count($breadcrumbs) - 1): ?>
                    <strong><?= e($b['label']) ?></strong>
                <?php else: ?>
                    <a href="<?= e($b['url']) ?>"><?= e($b['label']) ?></a>
                <?php endif; ?>
            <?php endforeach; ?>
            <?php if ($q !== ''): ?>
                <span style="color:var(--color-gray); margin:0 6px;">›</span>
                <strong>Recherche : <?= e($q) ?></strong>
            <?php endif; ?>
        </nav>

        <div class="catalogue-toolbar" style="display:flex; flex-wrap:wrap; gap:10px; align-items:center; margin-bottom:24px; border:1px solid var(--color-border); padding:12px 16px; border-radius:var(--radius-sm); background:var(--color-light);">
            <div style="font-size:13px; color:var(--color-gray-dark); font-weight:600;">
                <?= (int)$total ?> résultat<?= $total > 1 ? 's' : '' ?>
            </div>

            <div style="flex:1 1 220px; min-width:140px;">
                <label class="visually-hidden" for="catalogue-sort">Trier par</label>
                <select id="catalogue-sort" name="sort" style="width:100%; padding:6px 10px; border:1px solid var(--color-border); border-radius:var(--radius-sm); background:#fff;">
                    <option value="new" <?= $sort === 'new' ? 'selected' : '' ?>>Nouveautés</option>
                    <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Prix croissant</option>
                    <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Prix décroissant</option>
                    <option value="rating" <?= $sort === 'rating' ? 'selected' : '' ?>>Note client</option>
                </select>
            </div>

            <div style="flex:1 1 260px; min-width:140px; display:flex; gap:8px; align-items:center;">
                <label class="visually-hidden" for="catalogue-price-min">Prix min</label>
                <input type="number" id="catalogue-price-min" name="price_min" step="0.01" min="0" placeholder="Prix min" value="<?= $priceMin !== false ? e($priceMin) : '' ?>" style="padding:6px 10px; border:1px solid var(--color-border); border-radius:var(--radius-sm); flex:1;">
                <label class="visually-hidden" for="catalogue-price-max">Prix max</label>
                <input type="number" id="catalogue-price-max" name="price_max" step="0.01" min="0" placeholder="Prix max" value="<?= $priceMax !== false ? e($priceMax) : '' ?>" style="padding:6px 10px; border:1px solid var(--color-border); border-radius:var(--radius-sm); flex:1;">
            </div>

            <div style="flex:1 1 160px; min-width:140px;">
                <label class="visually-hidden" for="catalogue-rating">Note min</label>
                <select id="catalogue-rating" name="rating" style="width:100%; padding:6px 10px; border:1px solid var(--color-border); border-radius:var(--radius-sm); background:#fff;">
                    <option value="">Toutes les notes</option>
                    <option value="5" <?= $rating === 5 ? 'selected' : '' ?>>★★★★★ et +</option>
                    <option value="4" <?= $rating === 4 ? 'selected' : '' ?>>★★★★ et +</option>
                    <option value="3" <?= $rating === 3 ? 'selected' : '' ?>>★★★ et +</option>
                    <option value="2" <?= $rating === 2 ? 'selected' : '' ?>>★★ et +</option>
                    <option value="1" <?= $rating === 1 ? 'selected' : '' ?>>★ et +</option>
                </select>
            </div>
        </div>
    </div>
</section>

<section class="section">
    <div class="container catalogue-layout" style="display:grid; grid-template-columns: 240px 1fr; gap:30px; align-items:start;">
        <!-- Filtres latéraux -->
        <aside class="catalogue-sidebar">
            <?php if ($currentCategory): ?>
                <div class="catalogue-filter-block" style="margin-bottom:24px;">
                    <strong style="display:block; font-size:13px; color:var(--color-dark); margin-bottom:10px; padding-bottom:8px; border-bottom:1px solid var(--color-border);">Sous-catégories</strong>
                    <ul style="font-size:13px;">
                        <li style="margin-bottom:6px;">
                            <a href="catalogue.php?category=<?= e($currentCategory['slug']) ?><?= $q !== '' ? '&q=' . urlencode($q) : '' ?><?= $sort !== 'new' ? '&sort=' . $sort : '' ?>" class="<?= $currentSubCategory === null ? 'active' : '' ?>" style="color:var(--color-gray-dark);">
                                Tous <?= e($currentCategory['name']) ?>
                            </a>
                        </li>
                        <?php foreach ($filterSubs as $sub): ?>
                            <li style="margin-bottom:6px;">
                                <a href="catalogue.php?category=<?= e($currentCategory['slug']) ?>&sub=<?= e($sub['slug']) ?><?= $q !== '' ? '&q=' . urlencode($q) : '' ?><?= $sort !== 'new' ? '&sort=' . $sort : '' ?>" class="<?= $currentSubCategory && (int)$currentSubCategory['id'] === (int)$sub['id'] ? 'active' : '' ?>" style="color:var(--color-gray-dark);">
                                    <?= e($sub['name']) ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php else: ?>
                <div class="catalogue-filter-block" style="margin-bottom:24px;">
                    <strong style="display:block; font-size:13px; color:var(--color-dark); margin-bottom:10px; padding-bottom:8px; border-bottom:1px solid var(--color-border);">Toutes les catégories</strong>
                    <ul style="font-size:13px;">
                        <?php foreach ($allCategories as $cat): ?>
                            <li style="margin-bottom:6px;">
                                <a href="catalogue.php?category=<?= e($cat['slug']) ?><?= $q !== '' ? '&q=' . urlencode($q) : '' ?><?= $sort !== 'new' ? '&sort=' . $sort : '' ?>" class="<?= $currentCategory && (int)$currentCategory['id'] === (int)$cat['id'] ? 'active' : '' ?>" style="color:var(--color-gray-dark);">
                                    <?= e($cat['name']) ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </aside>

        <!-- Résultats -->
        <main class="catalogue-results">
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
                                <?php if (!empty($product['is_bestseller'])): ?>
                                    <span class="product-card__badge">Meilleure vente</span>
                                <?php elseif (!empty($product['is_on_sale'])): ?>
                                    <span class="product-card__badge">Promo</span>
                                <?php elseif (!empty($product['is_featured'])): ?>
                                    <span class="product-card__badge">Nouveauté</span>
                                <?php endif; ?>
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
                                <?php if (!empty($product['is_on_sale']) && !empty($product['sale_price'])): ?>
                                    <span style="font-size:12px; color:var(--color-gray-light); text-decoration:line-through; margin-left:6px;">
                                        <?= formatPrice(convertAmount((float)$product['sale_price'], ['code' => 'EUR', 'exchange_rate' => 1.000000], $currency), $currency) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="product-card__actions">
                                <a href="produit.php?slug=<?= e($product['reference']) ?>" class="btn btn--primary btn--sm btn--full">Voir l'article</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <nav class="catalogue-pagination" style="margin-top:28px; display:flex; gap:6px; justify-content:center; flex-wrap:wrap;">
                        <?php
                        $queryParts = [];
                        if ($categorySlug) { $queryParts[] = 'category=' . urlencode($categorySlug); }
                        if ($subSlug) { $queryParts[] = 'sub=' . urlencode($subSlug); }
                        if ($q !== '') { $queryParts[] = 'q=' . urlencode($q); }
                        if ($sort !== 'new') { $queryParts[] = 'sort=' . $sort; }
                        if ($priceMin !== false && $priceMin !== null) { $queryParts[] = 'price_min=' . urlencode((string)$priceMin); }
                        if ($priceMax !== false && $priceMax !== null) { $queryParts[] = 'price_max=' . urlencode((string)$priceMax); }
                        if ($rating !== false && $rating !== null) { $queryParts[] = 'rating=' . urlencode((string)$rating); }
                        $queryString = $queryParts !== [] ? '?' . implode('&', $queryParts) : '';
                        ?>
                        <?php if ($page > 1): ?>
                            <a href="<?= $queryString ?>&page=<?= ($page - 1) ?>" class="btn btn--outline btn--sm">Précédent</a>
                        <?php endif; ?>
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <?php if ($i === 1 || $i === $totalPages || abs($i - $page) <= 2): ?>
                                <a href="<?= $queryString ?>&page=<?= $i ?>" class="btn <?= $i === $page ? 'btn--primary' : 'btn--outline' ?> btn--sm"><?= $i ?></a>
                            <?php elseif ($i === 2 && $page > 4 || $i === $totalPages - 1 && $page < $totalPages - 3): ?>
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
                    <p>Aucun article ne correspond à vos critères.</p>
                    <?php if ($currentCategory || $q !== ''): ?>
                        <a href="catalogue.php" class="btn btn--primary btn--sm" style="margin-top:12px;">Voir tout le catalogue</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>
</section>

<?php
require_once INCLUDES_DIR . '/footer.php';
?>
