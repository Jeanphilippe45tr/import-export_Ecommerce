<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/page_bootstrap.php';

$db = db();

// --- Recherche / filtre ---
$search = trim($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;

$where = ['is_active = 1'];
$params = [];
if ($search !== '') {
    $where[] = '(email LIKE ? OR firstname LIKE ? OR lastname LIKE ? OR company LIKE ?)';
    $like = '%' . $search . '%';
    $params = array_merge($params, [$like, $like, $like, $like]);
}
$whereSql = implode(' AND ', $where);

$countStmt = $db->prepare("SELECT COUNT(*) AS total FROM customers WHERE $whereSql");
$countStmt->execute($params);
$total = (int)$countStmt->fetch()['total'];

$stmt = $db->prepare(
    "SELECT c.*, COUNT(o.id) AS nb_commandes, COALESCE(MAX(o.created_at), NULL) AS derniere_commande
     FROM customers c
     LEFT JOIN orders o ON o.customer_id = c.id
     WHERE $whereSql
     GROUP BY c.id
     ORDER BY c.created_at DESC
     LIMIT ? OFFSET ?"
);
foreach ($params as $i => $p) {
    $stmt->bindValue($i + 1, $p, PDO::PARAM_STR);
}
$stmt->bindValue(count($params) + 1, $perPage, PDO::PARAM_INT);
$stmt->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
$stmt->execute();
$clients = $stmt->fetchAll();

$pageTitle = 'Clients — Administration';

require_once __DIR__ . '/includes/header.php';
?>

<div class="admin-content" style="padding:26px 22px;">
    <div class="section-title"><i class="fas fa-users"></i> Clients</div>

    <form method="GET" style="display:flex; gap:10px; align-items:flex-end; margin-bottom:20px; background:#fff; padding:14px 18px; border:1px solid var(--color-border); border-radius:var(--radius); flex-wrap:wrap;">
        <div style="flex:1 1 260px; min-width:200px;">
            <label class="visually-hidden">Recherche</label>
            <input type="search" name="search" placeholder="Nom, email, entreprise…" value="<?= e($search) ?>" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm);">
        </div>
        <button type="submit" class="btn btn--primary btn--sm">Filtrer</button>
        <a href="clients.php" class="btn btn--outline btn--sm">Réinitialiser</a>
        <a href="../api/admin/clients.php?action=export_csv<?= $search !== '' ? '&search=' . urlencode($search) : '' ?>" class="btn btn--outline btn--sm"><i class="fas fa-file-csv"></i> Export CSV</a>
    </form>

    <?php if ($clients): ?>
        <table class="admin-table">
            <thead>
                <tr>
                    <th style="width:60px;">ID</th>
                    <th>Client</th>
                    <th style="width:180px;">Email</th>
                    <th style="width:140px;">Entreprise</th>
                    <th style="width:120px;">Commandes</th>
                    <th style="width:140px;">Dernière commande</th>
                    <th style="width:160px;">Créé le</th>
                    <th style="width:140px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($clients as $c): ?>
                    <tr>
                        <td><?= (int)$c['id'] ?></td>
                        <td><?= e($c['firstname'] . ' ' . $c['lastname']) ?></td>
                        <td><span class="notranslate" translate="no"><?= e($c['email']) ?></span></td>
                        <td><?= e($c['company'] ?? '—') ?></td>
                        <td style="text-align:center;"><?= (int)$c['nb_commandes'] ?></td>
                        <td><?= $c['derniere_commande'] ? date('d/m/Y', strtotime($c['derniere_commande'])) : '—' ?></td>
                        <td><?= date('d/m/Y', strtotime($c['created_at'])) ?></td>
                        <td>
                            <a href="client-detail.php?id=<?= (int)$c['id'] ?>" class="btn btn--outline btn--sm" style="padding:4px 8px; font-size:12px;">Fiche</a>
                            <button class="btn btn--outline btn--sm" style="padding:4px 8px; font-size:12px; color:var(--color-error);" onclick="if(confirm('Supprimer ce client (anonymisation RGPD) ?')){ location.href='../api/admin/clients.php?action=delete&id=<?= (int)$c['id'] ?>&csrf_token=<?= e(csrfToken()) ?>' }">Supprimer</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php $totalPages = max(1, (int)ceil($total / $perPage)); ?>
        <?php if ($totalPages > 1): ?>
            <div style="display:flex; justify-content:center; gap:6px; margin-top:16px; flex-wrap:wrap;">
                <?php
                $query = [];
                if ($search !== '') { $query[] = 'search=' . urlencode($search); }
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
        <div class="empty-state"><i class="fas fa-users"></i><p>Aucun client trouvé.</p></div>
    <?php endif; ?>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
