<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/page_bootstrap.php';

$db = db();

// --- Filtres ---
$search = trim($_GET['search'] ?? '');
$categoryFilter = $_GET['category'] ?? '';
$statusFilter = $_GET['status'] ?? '';

// --- Requête liste articles ---
$where = ['1=1'];
$params = [];

if ($search !== '') {
    $where[] = '(p.name LIKE ? OR p.reference LIKE ?)';
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
}
if ($categoryFilter !== '') {
    $where[] = 'p.category_id = ?';
    $params[] = (int)$categoryFilter;
}
if ($statusFilter !== '') {
    $where[] = 'p.is_active = ?';
    $params[] = ($statusFilter === '1' ? 1 : 0);
}

$whereSql = implode(' AND ', $where);

$stmt = $db->prepare("SELECT COUNT(*) AS total FROM products p WHERE $whereSql");
$stmt->execute($params);
$total = (int)$stmt->fetch()['total'];

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$stmt = $db->prepare(
    "SELECT p.*, c.name AS category_name
     FROM products p
     LEFT JOIN categories c ON p.category_id = c.id
     WHERE $whereSql
     ORDER BY p.created_at DESC
     LIMIT ? OFFSET ?"
);
foreach ($params as $i => $p) {
    $stmt->bindValue($i + 1, $p, PDO::PARAM_STR);
}
$stmt->bindValue(count($params) + 1, $perPage, PDO::PARAM_INT);
$stmt->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
$stmt->execute();
$articles = $stmt->fetchAll();

// --- Catégories pour le filtre ---
$categories = $db->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order ASC, name ASC")->fetchAll();

$pageTitle = 'Articles — Administration';

require_once __DIR__ . '/includes/header.php';
?>

<div class="admin-content" style="padding:26px 22px;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
        <div class="section-title"><i class="fas fa-box"></i> Articles</div>
        <a href="article-edit.php" class="btn btn--primary"><i class="fas fa-plus"></i> Nouvel article</a>
    </div>

    <form method="GET" style="display:flex; gap:10px; align-items:center; margin-bottom:20px; background:#fff; padding:14px 18px; border:1px solid var(--color-border); border-radius:var(--radius); flex-wrap:wrap;">
        <div style="flex:1 1 260px; min-width:200px;">
            <label class="visually-hidden">Recherche</label>
            <input type="search" name="search" placeholder="Rechercher nom ou référence…" value="<?= e($search) ?>" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm);">
        </div>
        <div style="flex:1 1 200px; min-width:160px;">
            <label class="visually-hidden">Catégorie</label>
            <select name="category" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); background:#fff;">
                <option value="">Toutes les catégories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= (int)$cat['id'] ?>" <?= $categoryFilter == $cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="flex:1 1 160px; min-width:140px;">
            <label class="visually-hidden">Statut</label>
            <select name="status" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); background:#fff;">
                <option value="">Tous</option>
                <option value="1" <?= $statusFilter === '1' ? 'selected' : '' ?>>Actif</option>
                <option value="0" <?= $statusFilter === '0' ? 'selected' : '' ?>>Inactif</option>
            </select>
        </div>
        <button type="submit" class="btn btn--primary btn--sm">Filtrer</button>
        <a href="articles.php" class="btn btn--outline btn--sm">Réinitialiser</a>
    </form>

    <?php if ($articles): ?>
        <table class="admin-table">
            <thead>
                <tr>
                    <th style="width:50px;">ID</th>
                    <th style="width:130px;">Référence</th>
                    <th>Nom</th>
                    <th style="width:150px;">Catégorie</th>
                    <th style="width:110px; text-align:right;">Prix</th>
                    <th style="width:70px; text-align:center;">Stock</th>
                    <th style="width:80px; text-align:center;">Note</th>
                    <th style="width:90px; text-align:center;">Actif</th>
                    <th style="width:150px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($articles as $a): ?>
                    <tr>
                        <td><?= (int)$a['id'] ?></td>
                        <td><span class="notranslate" translate="no"><?= e($a['reference']) ?></span></td>
                            <td><?= e($a['name']) ?></td>
                        <td><?= e($a['category_name'] ?? '—') ?></td>
                        <td style="text-align:right;"><span class="notranslate" translate="no"><?= number_format((float)$a['price'], 2, ',', ' ') ?> EUR</span></td>
                        <td style="text-align:center;">
                            <span style="font-weight:700; color:<?= (int)$a['stock'] <= 0 ? 'var(--color-error)' : 'var(--color-success)' ?>;">
                                <?= (int)$a['stock'] ?>
                            </span>
                        </td>
                        <td style="text-align:center;">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <span style="color:#f9a825;"><?= $i <= (int)$a['rating'] ? '★' : '☆' ?></span>
                            <?php endfor; ?>
                        </td>
                        <td style="text-align:center;">
                            <span class="badge <?= (int)$a['is_active'] === 1 ? 'badge--active' : 'badge--inactive' ?>">
                                <?= (int)$a['is_active'] === 1 ? 'Oui' : 'Non' ?>
                            </span>
                        </td>
                        <td>
                            <a href="article-edit.php?id=<?= (int)$a['id'] ?>" class="btn btn--outline btn--sm" style="padding:4px 8px; font-size:12px;">Modifier</a>
                            <button class="btn btn--outline btn--sm" style="padding:4px 8px; font-size:12px; color:var(--color-error);" onclick="if(confirm('Supprimer cet article ?')){ location.href='../api/admin/articles.php?action=delete&id=<?= (int)$a['id'] ?>&csrf_token=<?= e(csrfToken()) ?>' }">Supprimer</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Pagination -->
        <?php $totalPages = max(1, (int)ceil($total / $perPage)); ?>
        <?php if ($totalPages > 1): ?>
            <div style="display:flex; justify-content:center; gap:6px; margin-top:16px; flex-wrap:wrap;">
                <?php
                $query = [];
                if ($search !== '') { $query[] = 'search=' . urlencode($search); }
                if ($categoryFilter !== '') { $query[] = 'category=' . $categoryFilter; }
                if ($statusFilter !== '') { $query[] = 'status=' . $statusFilter; }
                $qs = $query !== [] ? '?' . implode('&', $query) : '';
                ?>
                <?php if ($page > 1): ?><a href="<?= $qs ?>&page=<?= ($page - 1) ?>" class="btn btn--outline btn--sm">Précédent</a><?php endif; ?>
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <?php if ($i === 1 || $i === $totalPages || abs($i - $page) <= 1): ?>
                        <a href="<?= $qs ?>&page=<?= $i ?>" class="btn <?= $i === $page ? 'btn--primary' : 'btn--outline' ?> btn--sm"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?><a href="<?= $qs ?>&page=<?= ($page + 1) ?>" class="btn btn--outline btn--sm">Suivant</a><?php endif; ?>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="empty-state"><i class="fas fa-box"></i><p>Aucun article trouvé.</p></div>
    <?php endif; ?>
</div>

<?php
require_once INCLUDES_DIR . '/footer.php';
?>
