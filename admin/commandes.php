<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/page_bootstrap.php';

$db = db();
$settings = loadSettings($db);

// --- Filtres ---
$statusFilter = $_GET['status'] ?? '';
$countryFilter = $_GET['country'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$search = trim($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;

// --- Requête ---
$where = ['1=1'];
$params = [];

if ($statusFilter !== '') {
    $where[] = 'o.status = ?';
    $params[] = $statusFilter;
}
if ($countryFilter !== '') {
    $where[] = 'o.country_id = ?';
    $params[] = (int)$countryFilter;
}
if ($dateFrom !== '') {
    $where[] = 'DATE(o.created_at) >= ?';
    $params[] = $dateFrom;
}
if ($dateTo !== '') {
    $where[] = 'DATE(o.created_at) <= ?';
    $params[] = $dateTo;
}
if ($search !== '') {
    $where[] = '(o.order_number LIKE ? OR o.email LIKE ? OR o.firstname LIKE ? OR o.lastname LIKE ?)';
    $like = '%' . $search . '%';
    $params = array_merge($params, [$like, $like, $like, $like]);
}

$whereSql = implode(' AND ', $where);

$countStmt = $db->prepare("SELECT COUNT(*) AS total FROM orders o WHERE $whereSql");
$countStmt->execute($params);
$total = (int)$countStmt->fetch()['total'];

$stmt = $db->prepare(
    "SELECT o.*, c.email AS customer_email, c.firstname AS customer_firstname, c.lastname AS customer_lastname
     FROM orders o
     LEFT JOIN customers c ON o.customer_id = c.id
     WHERE $whereSql
     ORDER BY o.created_at DESC
     LIMIT ? OFFSET ?"
);
foreach ($params as $i => $p) {
    $stmt->bindValue($i + 1, $p, PDO::PARAM_STR);
}
$stmt->bindValue(count($params) + 1, $perPage, PDO::PARAM_INT);
$stmt->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
$stmt->execute();
$orders = $stmt->fetchAll();

// --- Statuts et pays pour filtres ---
$statuts = ['nouvelle', 'confirmée', 'expédiée', 'livrée', 'annulée'];
$pays = $db->query("SELECT id, name FROM countries ORDER BY name ASC")->fetchAll();

$pageTitle = 'Commandes — Administration';

require_once __DIR__ . '/includes/header.php';
?>

<div class="admin-content" style="padding:26px 22px;">
    <div class="section-title"><i class="fas fa-shopping-bag"></i> Commandes</div>

    <form method="GET" style="display:flex; gap:10px; align-items:flex-end; margin-bottom:20px; background:#fff; padding:14px 18px; border:1px solid var(--color-border); border-radius:var(--radius); flex-wrap:wrap;">
        <div style="flex:1 1 200px; min-width:150px;">
            <label class="visually-hidden">Numéro / client</label>
            <input type="search" name="search" placeholder="Numéro, client, email…" value="<?= e($search) ?>" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm);">
        </div>
        <div style="flex:1 1 160px; min-width:140px;">
            <label class="visually-hidden">Statut</label>
            <select name="status" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); background:#fff;">
                <option value="">Tous les statuts</option>
                <?php foreach ($statuts as $s): ?>
                    <option value="<?= e($s) ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= e($s) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="flex:1 1 160px; min-width:140px;">
            <label class="visually-hidden">Pays</label>
            <select name="country" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); background:#fff;">
                <option value="">Tous les pays</option>
                <?php foreach ($pays as $p): ?>
                    <option value="<?= (int)$p['id'] ?>" <?= $countryFilter == $p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="flex:1 1 140px; min-width:120px;">
            <label class="visually-hidden">Du</label>
            <input type="date" name="date_from" value="<?= e($dateFrom) ?>" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm);">
        </div>
        <div style="flex:1 1 140px; min-width:120px;">
            <label class="visually-hidden">Au</label>
            <input type="date" name="date_to" value="<?= e($dateTo) ?>" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm);">
        </div>
        <button type="submit" class="btn btn--primary btn--sm">Filtrer</button>
        <a href="commandes.php" class="btn btn--outline btn--sm">Réinitialiser</a>
    </form>

    <?php if ($orders): ?>
        <table class="admin-table">
            <thead>
                <tr>
                    <th style="width:150px;">Numéro</th>
                    <th style="width:180px;">Client</th>
                    <th style="width:120px;">Pays</th>
                    <th style="width:140px;">Date</th>
                    <th style="width:110px;">Statut</th>
                    <th style="text-align:right; width:110px;">Total</th>
                    <th style="text-align:right; width:110px;">Livraison</th>
                    <th style="width:130px;">Paiement</th>
                    <th style="width:140px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $o): ?>
                    <tr>
                        <td><span class="notranslate" translate="no"><?= e($o['order_number']) ?></span></td>
                        <td><?= e($o['firstname'] . ' ' . $o['lastname']) ?><br><span style="font-size:11px; color:var(--color-gray);"><?= e($o['email']) ?></span></td>
                        <td><?= e($o['country_name']) ?></td>
                        <td><?= date('d/m/Y H:i', strtotime($o['created_at'])) ?> UTC</td>
                        <td>
                            <span class="badge badge--<?= e($o['status']) ?>"><?= e($o['status']) ?></span>
                        </td>
                        <td style="text-align:right;"><span class="notranslate" translate="no"><?= number_format((float)$o['total'], 2, ',', ' ') ?> <?= e($o['currency_code']) ?></span></td>
                        <td style="text-align:right;"><span class="notranslate" translate="no"><?= number_format((float)$o['shipping_amount'], 2, ',', ' ') ?></span></td>
                        <td><?= e($o['payment_method'] ?? '—') ?></td>
                        <td>
                            <a href="commande-detail.php?id=<?= (int)$o['id'] ?>" class="btn btn--outline btn--sm" style="padding:4px 8px; font-size:12px;">Détail</a>
                            <button class="btn btn--outline btn--sm" style="padding:4px 8px; font-size:12px;" onclick="openStatusModal(<?= (int)$o['id'] ?>, '<?= e($o['status']) ?>', '<?= e($o['order_number']) ?>')">Changer statut</button>
                            <button class="btn btn--outline btn--sm" style="padding:4px 8px; font-size:12px; color:var(--color-error);" onclick="if(confirm('Supprimer définitivement cette commande ?')){ location.href='../api/admin/commandes.php?action=delete&id=<?= (int)$o['id'] ?>&csrf_token=<?= e(csrfToken()) ?>' }">Supprimer</button>
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
                if ($statusFilter !== '') { $query[] = 'status=' . urlencode($statusFilter); }
                if ($countryFilter !== '') { $query[] = 'country=' . $countryFilter; }
                if ($dateFrom !== '') { $query[] = 'date_from=' . urlencode($dateFrom); }
                if ($dateTo !== '') { $query[] = 'date_to=' . urlencode($dateTo); }
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
        <div class="empty-state"><i class="fas fa-shopping-bag"></i><p>Aucune commande trouvée.</p></div>
    <?php endif; ?>
</div>

<!-- Modal changement de statut -->
<div id="statusModal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); z-index:2000; align-items:center; justify-content:center;">
    <div style="background:#fff; border-radius:var(--radius); width:100%; max-width:480px; padding:24px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <h4 style="font-size:16px; font-weight:800; color:var(--color-dark); margin:0;">Changer le statut</h4>
            <button onclick="closeStatusModal()" style="background:none; border:none; font-size:20px; color:var(--color-gray);">×</button>
        </div>
        <form id="statusForm" method="POST" action="../api/admin/commandes.php?action=status_update" novalidate>
            <?= csrfField() ?>
            <input type="hidden" name="id" id="statusOrderId" value="">
            <div class="form-group">
                <label>Numéro de commande</label>
                <input type="text" id="statusOrderNumber" disabled style="background:#f5f5f5;">
            </div>
            <div class="form-group">
                <label>Nouveau statut <span style="color:var(--color-error);">*</span></label>
                <select name="status" id="statusNewStatus" required>
                    <option value="nouvelle">Nouvelle</option>
                    <option value="confirmée">Confirmée</option>
                    <option value="expédiée">Expédiée</option>
                    <option value="livrée">Livrée</option>
                    <option value="annulée">Annulée</option>
                </select>
            </div>
            <div class="form-group">
                <label>Transporteur</label>
                <input type="text" name="carrier" id="statusCarrier" placeholder="Colissimo, DHL,…">
            </div>
            <div class="form-group">
                <label>Numéro de suivi</label>
                <input type="text" name="tracking" id="statusTracking" placeholder="Ex. RA123456789FR">
            </div>
            <div style="display:flex; gap:10px; margin-top:20px;">
                <button type="submit" class="btn btn--primary">Enregistrer</button>
                <button type="button" class="btn btn--outline" onclick="closeStatusModal()">Annuler</button>
            </div>
        </form>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>

<script>
function openStatusModal(orderId, currentStatus, orderNumber) {
    document.getElementById('statusOrderId').value = orderId;
    document.getElementById('statusOrderNumber').value = orderNumber;
    document.getElementById('statusNewStatus').value = currentStatus;
    document.getElementById('statusCarrier').value = '';
    document.getElementById('statusTracking').value = '';
    document.getElementById('statusModal').style.display = 'flex';
}
function closeStatusModal() { document.getElementById('statusModal').style.display = 'none'; }
window.addEventListener('click', function(e) {
    const m = document.getElementById('statusModal');
    if (e.target === m) m.style.display = 'none';
});
</script>
