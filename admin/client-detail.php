<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/page_bootstrap.php';

$db = db();
$settings = loadSettings($db);

$clientId = (int)($_GET['id'] ?? 0);
if ($clientId <= 0) {
    $_SESSION['flash']['error'] = 'Client introuvable.';
    redirect('clients.php');
}

$stmt = $db->prepare("SELECT * FROM customers WHERE id = ? AND is_active = 1 LIMIT 1");
$stmt->execute([$clientId]);
$client = $stmt->fetch();
if ($client === false) {
    $_SESSION['flash']['error'] = 'Client introuvable.';
    redirect('clients.php');
}

// Commandes
$stmt = $db->prepare(
    "SELECT o.*, COUNT(oi.id) AS nb_articles
     FROM orders o
     LEFT JOIN order_items oi ON oi.order_id = o.id
     WHERE o.customer_id = ?
     GROUP BY o.id
     ORDER BY o.created_at DESC
     LIMIT 100"
);
$stmt->execute([$clientId]);
$orders = $stmt->fetchAll();

// Factures liées
$stmt = $db->prepare(
    "SELECT i.*, o.order_number
     FROM invoices i
     JOIN orders o ON o.id = i.order_id
     WHERE o.customer_id = ?
     ORDER BY i.invoice_date DESC
     LIMIT 100"
);
$stmt->execute([$clientId]);
$invoices = $stmt->fetchAll();

$pageTitle = 'Fiche client — ' . e($client['firstname'] . ' ' . $client['lastname']);

require_once __DIR__ . '/includes/header.php';
?>

<div class="admin-content" style="padding:26px 22px; max-width:900px;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
        <div class="section-title"><i class="fas fa-user"></i> Fiche client</div>
        <a href="clients.php" class="btn btn--outline"><i class="fas fa-list"></i> Retour à la liste</a>
    </div>

    <div style="background:#fff; border:1px solid var(--color-border); border-radius:var(--radius); padding:22px; margin-bottom:26px;">
        <h3 style="font-size:18px; font-weight:800; color:var(--color-dark); margin-bottom:8px;"><?= e($client['firstname'] . ' ' . $client['lastname']) ?></h3>
        <p style="font-size:13px; color:var(--color-gray); margin:0 0 14px 0;">
            <span class="notranslate" translate="no"><?= e($client['email']) ?></span><br>
            <?php if ($client['company'] !== null && $client['company'] !== ''): ?>
                <?= e($client['company']) ?><br>
            <?php endif; ?>
            <?php if ($client['vat_number'] !== null && $client['vat_number'] !== ''): ?>
                <span class="notranslate" translate="no">TVA : <?= e($client['vat_number']) ?></span><br>
            <?php endif; ?>
            <span class="notranslate" translate="no"><?= e($client['phone'] ?? '—') ?></span><br>
            <?= e($client['address'] ?? '') ?><br>
            <span class="notranslate" translate="no"><?= e($client['postcode'] ?? '') ?> <?= e($client['city'] ?? '') ?></span>
        </p>
        <div style="font-size:12px; color:var(--color-gray);">
            Créé le : <?= date('d/m/Y H:i', strtotime($client['created_at'])) ?> UTC
        </div>
    </div>

    <!-- Commandes -->
    <div style="margin-bottom:26px;">
        <h4 style="font-size:15px; font-weight:800; color:var(--color-dark); margin-bottom:12px; border-bottom:1px solid var(--color-border); padding-bottom:8px;">Historique des commandes (<?= count($orders) ?>)</h4>
        <?php if ($orders): ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th style="width:150px;">Numéro</th>
                        <th style="width:140px;">Date</th>
                        <th style="width:110px;">Statut</th>
                        <th style="text-align:right; width:120px;">Total</th>
                        <th style="width:120px;">Facture</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $o): ?>
                        <tr>
                            <td><span class="notranslate" translate="no"><?= e($o['order_number']) ?></span></td>
                            <td><?= date('d/m/Y H:i', strtotime($o['created_at'])) ?> UTC</td>
                            <td><span class="badge badge--<?= e($o['status']) ?>"><?= e($o['status']) ?></span></td>
                            <td style="text-align:right;"><span class="notranslate" translate="no"><?= number_format((float)$o['total'], 2, ',', ' ') ?> <?= e($o['currency_code']) ?></span></td>
                            <td>
                                <?php
                                $hasInvoice = false;
                                foreach ($invoices as $inv) {
                                    if ((int)$inv['order_id'] === (int)$o['id']) { $hasInvoice = true; break; }
                                }
                                ?>
                                <?php if ($hasInvoice): ?>
                                    <span class="badge badge--active">Émise</span>
                                <?php else: ?>
                                    <a href="../api/admin/facture.php?action=pdf&order_id=<?= (int)$o['id'] ?>" class="btn btn--outline btn--sm" style="padding:3px 8px; font-size:11px;">Générer</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p style="color:var(--color-gray);">Aucune commande.</p>
        <?php endif; ?>
    </div>

    <!-- Factures -->
    <div>
        <h4 style="font-size:15px; font-weight:800; color:var(--color-dark); margin-bottom:12px; border-bottom:1px solid var(--color-border); padding-bottom:8px;">Factures (<?= count($invoices) ?>)</h4>
        <?php if ($invoices): ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th style="width:160px;">Numéro facture</th>
                        <th style="width:150px;">Commande</th>
                        <th style="width:130px;">Date émission</th>
                        <th style="text-align:right; width:120px;">Total TTC</th>
                        <th style="width:100px; text-align:center;">Statut</th>
                        <th style="width:120px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($invoices as $inv): ?>
                        <tr>
                            <td><span class="notranslate" translate="no"><?= e($inv['invoice_number']) ?></span></td>
                            <td><span class="notranslate" translate="no"><?= e($inv['order_number']) ?></span></td>
                            <td><?= date('d/m/Y', strtotime($inv['invoice_date'])) ?></td>
                            <td style="text-align:right;"><span class="notranslate" translate="no"><?= number_format((float)$inv['total_ttc'], 2, ',', ' ') ?> <?= e($inv['currency_code']) ?></span></td>
                            <td style="text-align:center;"><span class="badge badge--<?= e($inv['status']) ?>"><?= e($inv['status']) ?></span></td>
                            <td>
                                <a href="../api/admin/facture.php?action=pdf&invoice_id=<?= (int)$inv['id'] ?>" class="btn btn--outline btn--sm" style="padding:4px 8px; font-size:11px;">PDF</a>
                                <?php if ((int)$inv['status'] === 1): ?>
                                    <button class="btn btn--outline btn--sm" style="padding:4px 8px; font-size:11px; color:var(--color-error);" onclick="if(confirm('Annuler cette facture (avoir) ?')){ location.href='../api/admin/facture.php?action=annuler&invoice_id=<?= (int)$inv['id'] ?>&csrf_token=<?= e(csrfToken()) ?>' }">Annuler</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p style="color:var(--color-gray);">Aucune facture.</p>
        <?php endif; ?>
    </div>

    <!-- Suppression RGPD -->
    <div style="margin-top:30px; padding-top:24px; border-top:1px solid var(--color-border);">
        <h4 style="font-size:15px; font-weight:800; color:var(--color-error); margin-bottom:12px;">Supprimer ce client (RGPD)</h4>
        <p style="font-size:13px; color:var(--color-gray); margin-bottom:16px; line-height:1.5;">
            Conformément au RGPD, les données personnelles du client seront anonymisées. Les commandes et factures sont conservées de manière anonyme pour les obligations comptables (durée : 10 ans).
        </p>
        <form action="../api/admin/clients.php?action=delete" method="POST" novalidate>
            <?= csrfField() ?>
            <input type="hidden" name="id" value="<?= (int)$clientId ?>">
            <button type="submit" class="btn btn--outline" style="color:var(--color-error); border-color:var(--color-error);">Anonymiser ce client</button>
        </form>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
