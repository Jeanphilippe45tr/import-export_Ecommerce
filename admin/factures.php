<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/page_bootstrap.php';

$db = db();
$settings = loadSettings($db);

// --- Filtres ---
$statusFilter = $_GET['status'] ?? '';
$search = trim($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;

$where = ['1=1'];
$params = [];

if ($statusFilter !== '') {
    $where[] = 'i.status = ?';
    $params[] = $statusFilter;
}
if ($search !== '') {
    $where[] = '(i.invoice_number LIKE ? OR o.order_number LIKE ? OR o.email LIKE ?)';
    $like = '%' . $search . '%';
    $params = array_merge($params, [$like, $like, $like]);
}

$whereSql = implode(' AND ', $where);

$countStmt = $db->prepare("SELECT COUNT(*) AS total FROM invoices i JOIN orders o ON o.id = i.order_id WHERE $whereSql");
$countStmt->execute($params);
$total = (int)$countStmt->fetch()['total'];

$stmt = $db->prepare(
    "SELECT i.*, o.order_number, o.email, o.firstname, o.lastname, o.currency_code
     FROM invoices i
     JOIN orders o ON o.id = i.order_id
     WHERE $whereSql
     ORDER BY i.invoice_date DESC
     LIMIT ? OFFSET ?"
);
foreach ($params as $i => $p) {
    $stmt->bindValue($i + 1, $p, PDO::PARAM_STR);
}
$stmt->bindValue(count($params) + 1, $perPage, PDO::PARAM_INT);
$stmt->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
$stmt->execute();
$invoices = $stmt->fetchAll();

$pageTitle = 'Factures — Administration';

require_once __DIR__ . '/includes/header.php';
?>

<div class="admin-content" style="padding:26px 22px;">
    <div class="section-title"><i class="fas fa-file-invoice"></i> Factures A4</div>

    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:10px;">
        <form method="GET" style="display:flex; gap:10px; align-items:flex-end; background:#fff; padding:12px 16px; border:1px solid var(--color-border); border-radius:var(--radius); flex-wrap:wrap;">
            <div style="flex:1 1 200px; min-width:150px;">
                <label class="visually-hidden">Recherche</label>
                <input type="search" name="search" placeholder="N° facture, commande, email…" value="<?= e($search) ?>" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm);">
            </div>
            <div style="flex:1 1 160px; min-width:140px;">
                <label class="visually-hidden">Statut</label>
                <select name="status" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); background:#fff;">
                    <option value="">Tous les statuts</option>
                    <option value="émise" <?= $statusFilter === 'émise' ? 'selected' : '' ?>>Émise</option>
                    <option value="annulée" <?= $statusFilter === 'annulée' ? 'selected' : '' ?>>Annulée</option>
                </select>
            </div>
            <button type="submit" class="btn btn--primary btn--sm">Filtrer</button>
            <a href="factures.php" class="btn btn--outline btn--sm">Réinitialiser</a>
        </form>
    </div>

    <?php if (hasFlash('success')): ?>
        <div class="alert alert--success" style="margin-bottom:20px;"><?= e(flash('success')) ?></div>
    <?php endif; ?>

    <?php if ($invoices): ?>
        <table class="admin-table">
            <thead>
                <tr>
                    <th style="width:150px;">Numéro facture</th>
                    <th style="width:150px;">Commande</th>
                    <th style="width:180px;">Client</th>
                    <th style="width:130px;">Date émission</th>
                    <th style="width:130px;">Date échéance</th>
                    <th style="text-align:right; width:120px;">Total TTC</th>
                    <th style="width:100px; text-align:center;">Statut</th>
                    <th style="width:180px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($invoices as $inv): ?>
                    <tr>
                        <td><span class="notranslate" translate="no"><?= e($inv['invoice_number']) ?></span></td>
                        <td><span class="notranslate" translate="no"><?= e($inv['order_number']) ?></span></td>
                        <td><?= e($inv['firstname'] . ' ' . $inv['lastname']) ?><br><span style="font-size:11px; color:var(--color-gray);"><span class="notranslate" translate="no"><?= e($inv['email']) ?></span></span></td>
                        <td><?= date('d/m/Y', strtotime($inv['invoice_date'])) ?></td>
                        <td><?= $inv['due_date'] ? date('d/m/Y', strtotime($inv['due_date'])) : '—' ?></td>
                        <td style="text-align:right;"><span class="notranslate" translate="no"><?= number_format((float)$inv['total_ttc'], 2, ',', ' ') ?> <?= e($inv['currency_code']) ?></span></td>
                        <td style="text-align:center;"><span class="badge badge--<?= e($inv['status']) ?>"><?= e($inv['status']) ?></span></td>
                        <td>
                            <a href="../api/admin/facture.php?action=pdf&invoice_id=<?= (int)$inv['id'] ?>&download=1" class="btn btn--outline btn--sm" style="padding:4px 8px; font-size:11px;">PDF</a>
                            <a href="../api/admin/facture.php?action=pdf&invoice_id=<?= (int)$inv['id'] ?>" class="btn btn--outline btn--sm" style="padding:4px 8px; font-size:11px;">Aperçu</a>
                            <?php if ((int)$inv['status'] === 1): ?>
                                <button class="btn btn--outline btn--sm" style="padding:4px 8px; font-size:11px; color:var(--color-error);" onclick="if(confirm('Annuler cette facture et émettre un avoir ?')){ annulerFacture(<?= (int)$inv['id'] ?>) }">Annuler (avoir)</button>
                            <?php else: ?>
                                <span style="font-size:11px; color:var(--color-gray);">Annulée</span>
                            <?php endif; ?>
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
                if ($statusFilter !== '') { $query[] = 'status=' . urlencode($statusFilter); }
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
        <div class="empty-state"><i class="fas fa-file-invoice"></i><p>Aucune facture trouvée.</p></div>
    <?php endif; ?>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>

<script>
function annulerFacture(invoiceId) {
    var form = document.createElement('form');
    form.method = 'POST';
    form.action = 'api/admin/facture.php?action=annuler';
    form.innerHTML = '<input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="invoice_id" value="' + invoiceId + '">';
    document.body.appendChild(form);
    form.submit();
}
</script>
