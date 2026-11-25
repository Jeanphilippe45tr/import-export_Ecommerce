<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/page_bootstrap.php';

$db = db();
$settings = loadSettings($db);

$orderId = (int)($_GET['id'] ?? 0);
if ($orderId <= 0) {
    $_SESSION['flash']['error'] = 'Commande introuvable.';
    redirect('commandes.php');
}

$stmt = $db->prepare("SELECT * FROM orders WHERE id = ? LIMIT 1");
$stmt->execute([$orderId]);
$order = $stmt->fetch();
if ($order === false) {
    $_SESSION['flash']['error'] = 'Commande introuvable.';
    redirect('commandes.php');
}

$stmt = $db->prepare("SELECT * FROM order_items WHERE order_id = ? ORDER BY id ASC");
$stmt->execute([$orderId]);
$items = $stmt->fetchAll();

$pageTitle = 'Commande ' . e($order['order_number']) . ' — Administration';

require_once __DIR__ . '/includes/header.php';
?>

<div class="admin-content" style="padding:26px 22px;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
        <div class="section-title"><i class="fas fa-shopping-bag"></i> Commande <span class="notranslate" translate="no"><?= e($order['order_number']) ?></span></div>
        <a href="commandes.php" class="btn btn--outline"><i class="fas fa-list"></i> Retour à la liste</a>
    </div>

    <div style="display:grid; grid-template-columns: 340px 1fr; gap:30px; margin-bottom:26px;">
        <!-- Infos commande -->
        <div style="background:#fff; border:1px solid var(--color-border); border-radius:var(--radius); padding:20px;">
            <h4 style="font-size:14px; font-weight:800; color:var(--color-dark); margin-bottom:14px; border-bottom:1px solid var(--color-border); padding-bottom:8px;">Commande</h4>
            <div class="form-group" style="margin-bottom:10px;">
                <label style="font-size:12px; color:var(--color-gray);">Numéro</label>
                <div style="font-weight:600;"><span class="notranslate" translate="no"><?= e($order['order_number']) ?></span></div>
            </div>
            <div class="form-group" style="margin-bottom:10px;">
                <label style="font-size:12px; color:var(--color-gray);">Statut</label>
                <span class="badge badge--<?= e($order['status']) ?>" style="font-weight:600;"><?= e($order['status']) ?></span>
            </div>
            <div class="form-group" style="margin-bottom:10px;">
                <label style="font-size:12px; color:var(--color-gray);">Date</label>
                <div><?= date('d/m/Y H:i', strtotime($order['created_at'])) ?> UTC</div>
            </div>
            <div class="form-group" style="margin-bottom:10px;">
                <label style="font-size:12px; color:var(--color-gray);">Devise</label>
                <div><span class="notranslate" translate="no"><?= e($order['currency_code']) ?></span></div>
            </div>
            <div class="form-group" style="margin-bottom:10px;">
                <label style="font-size:12px; color:var(--color-gray);">Taux de change</label>
                <div><span class="notranslate" translate="no"><?= number_format((float)$order['exchange_rate'], 6, ',', ' ') ?></span></div>
            </div>
            <div class="form-group" style="margin-bottom:10px;">
                <label style="font-size:12px; color:var(--color-gray);">Mode de paiement</label>
                <div><?= e($order['payment_method'] ?? '—') ?></div>
            </div>
            <div class="form-group" style="margin-bottom:10px;">
                <label style="font-size:12px; color:var(--color-gray);">Statut paiement</label>
                <div><?= e($order['payment_status']) ?></div>
            </div>
            <?php if ($order['tracking_number'] !== null && $order['tracking_number'] !== ''): ?>
                <div class="form-group" style="margin-bottom:10px;">
                    <label style="font-size:12px; color:var(--color-gray);">Transporteur</label>
                    <div><?= e($order['tracking_carrier'] ?? '') ?></div>
                </div>
                <div class="form-group" style="margin-bottom:10px;">
                    <label style="font-size:12px; color:var(--color-gray);">N° de suivi</label>
                    <div><span class="notranslate" translate="no"><?= e($order['tracking_number']) ?></span></div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Infos client -->
        <div style="background:#fff; border:1px solid var(--color-border); border-radius:var(--radius); padding:20px;">
            <h4 style="font-size:14px; font-weight:800; color:var(--color-dark); margin-bottom:14px; border-bottom:1px solid var(--color-border); padding-bottom:8px;">Client</h4>
            <div class="form-group" style="margin-bottom:10px;">
                <label style="font-size:12px; color:var(--color-gray);">Nom</label>
                <div style="font-weight:600;"><?= e($order['firstname'] . ' ' . $order['lastname']) ?></div>
            </div>
            <?php if ($order['company'] !== null && $order['company'] !== ''): ?>
                <div class="form-group" style="margin-bottom:10px;">
                    <label style="font-size:12px; color:var(--color-gray);">Entreprise</label>
                    <div><?= e($order['company']) ?></div>
                </div>
            <?php endif; ?>
            <?php if ($order['vat_number'] !== null && $order['vat_number'] !== ''): ?>
                <div class="form-group" style="margin-bottom:10px;">
                    <label style="font-size:12px; color:var(--color-gray);">TVA intra</label>
                    <div><span class="notranslate" translate="no"><?= e($order['vat_number']) ?></span></div>
                </div>
            <?php endif; ?>
            <div class="form-group" style="margin-bottom:10px;">
                <label style="font-size:12px; color:var(--color-gray);">Email</label>
                <div><span class="notranslate" translate="no"><?= e($order['email']) ?></span></div>
            </div>
            <div class="form-group" style="margin-bottom:10px;">
                <label style="font-size:12px; color:var(--color-gray);">Téléphone</label>
                <div><span class="notranslate" translate="no"><?= e($order['phone']) ?></span></div>
            </div>
            <div class="form-group" style="margin-bottom:10px;">
                <label style="font-size:12px; color:var(--color-gray);">Adresse</label>
                <div><?= e($order['address']) ?></div>
            </div>
            <div class="form-group" style="margin-bottom:10px;">
                <label style="font-size:12px; color:var(--color-gray);">Code postal / Ville</label>
                <div><span class="notranslate" translate="no"><?= e($order['postcode']) ?></span> <?= e($order['city']) ?></div>
            </div>
            <div class="form-group" style="margin-bottom:10px;">
                <label style="font-size:12px; color:var(--color-gray);">Pays</label>
                <div><?= e($order['country_name']) ?></div>
            </div>
            <?php if ($order['customer_note'] !== null && $order['customer_note'] !== ''): ?>
                <div class="form-group" style="margin-bottom:10px;">
                    <label style="font-size:12px; color:var(--color-gray);">Note client</label>
                    <div style="font-size:13px;"><?= nl2br(e($order['customer_note'])) ?></div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Récapitulatif financier -->
    <div style="background:#fff; border:1px solid var(--color-border); border-radius:var(--radius); padding:20px; margin-bottom:26px;">
        <h4 style="font-size:14px; font-weight:800; color:var(--color-dark); margin-bottom:14px; border-bottom:1px solid var(--color-border); padding-bottom:8px;">Montants</h4>
        <table style="width:100%; border-collapse:collapse; font-size:13px;">
            <tr style="border-bottom:1px solid #f0f0f0;">
                <td style="padding:8px 0;">Sous-total HT</td>
                <td style="text-align:right; padding:8px 0;"><span class="notranslate" translate="no"><?= number_format((float)$order['subtotal'], 2, ',', ' ') ?> <?= e($order['currency_code']) ?></span></td>
            </tr>
            <tr style="border-bottom:1px solid #f0f0f0;">
                <td style="padding:8px 0;">Frais de livraison</td>
                <td style="text-align:right; padding:8px 0;"><span class="notranslate" translate="no"><?= number_format((float)$order['shipping_amount'], 2, ',', ' ') ?> <?= e($order['currency_code']) ?></span></td>
            </tr>
            <tr style="border-bottom:1px solid #f0f0f0;">
                <td style="padding:8px 0;">TVA</td>
                <td style="text-align:right; padding:8px 0;"><span class="notranslate" translate="no"><?= number_format((float)$order['vat_amount'], 2, ',', ' ') ?> <?= e($order['currency_code']) ?></span></td>
            </tr>
            <tr style="border-bottom:1px solid #f0f0f0;">
                <td style="padding:8px 0;">Remise</td>
                <td style="text-align:right; padding:8px 0;"><span class="notranslate" translate="no"><?= number_format((float)$order['discount_amount'], 2, ',', ' ') ?> <?= e($order['currency_code']) ?></span></td>
            </tr>
            <tr style="border-top:2px solid var(--color-dark);">
                <td style="padding:10px 0; font-weight:800;">Total TTC</td>
                <td style="text-align:right; padding:10px 0; font-weight:800;"><span class="notranslate" translate="no"><?= number_format((float)$order['total'], 2, ',', ' ') ?> <?= e($order['currency_code']) ?></span></td>
            </tr>
        </table>
    </div>

    <!-- Articles -->
    <div style="background:#fff; border:1px solid var(--color-border); border-radius:var(--radius); padding:20px;">
        <h4 style="font-size:14px; font-weight:800; color:var(--color-dark); margin-bottom:14px; border-bottom:1px solid var(--color-border); padding-bottom:8px;">Articles (<?= count($items) ?>)</h4>
        <table class="admin-table">
            <thead>
                <tr>
                    <th style="width:130px;">Référence</th>
                    <th>Produit</th>
                    <th style="text-align:center; width:80px;">Qty</th>
                    <th style="text-align:right; width:130px;">Prix unitaire</th>
                    <th style="text-align:right; width:130px;">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $it): ?>
                    <tr>
                        <td><span class="notranslate" translate="no"><?= e($it['reference']) ?></span></td>
                        <td><?= e($it['name']) ?></td>
                        <td style="text-align:center;"><?= (int)$it['quantity'] ?></td>
                        <td style="text-align:right;"><span class="notranslate" translate="no"><?= number_format((float)$it['unit_price'], 2, ',', ' ') ?> <?= e($it['unit_price_currency']) ?></span></td>
                        <td style="text-align:right;"><span class="notranslate" translate="no"><?= number_format((float)$it['total'], 2, ',', ' ') ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Changement rapide de statut -->
    <div style="background:#fff3e0; border:1px solid var(--color-primary-light); border-radius:var(--radius); padding:16px; margin-top:20px;">
        <h4 style="font-size:14px; font-weight:800; color:var(--color-primary-dark); margin-bottom:12px;">Modifier le statut et le suivi</h4>
        <form action="../api/admin/commandes.php?action=status_update" method="POST" novalidate style="display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap;">
            <?= csrfField() ?>
            <input type="hidden" name="id" value="<?= (int)$order['id'] ?>">
            <div style="flex:1 1 160px; min-width:140px;">
                <label style="font-size:12px; color:var(--color-gray-dark); display:block; margin-bottom:6px;">Nouveau statut</label>
                <select name="status" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm);">
                    <option value="nouvelle" <?= $order['status'] === 'nouvelle' ? 'selected' : '' ?>>Nouvelle</option>
                    <option value="confirmée" <?= $order['status'] === 'confirmée' ? 'selected' : '' ?>>Confirmée</option>
                    <option value="expédiée" <?= $order['status'] === 'expédiée' ? 'selected' : '' ?>>Expédiée</option>
                    <option value="livrée" <?= $order['status'] === 'livrée' ? 'selected' : '' ?>>Livrée</option>
                    <option value="annulée" <?= $order['status'] === 'annulée' ? 'selected' : '' ?>>Annulée</option>
                </select>
            </div>
            <div style="flex:1 1 180px; min-width:140px;">
                <label style="font-size:12px; color:var(--color-gray-dark); display:block; margin-bottom:6px;">Transporteur</label>
                <input type="text" name="carrier" value="<?= e($order['tracking_carrier'] ?? '') ?>" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm);">
            </div>
            <div style="flex:1 1 180px; min-width:140px;">
                <label style="font-size:12px; color:var(--color-gray-dark); display:block; margin-bottom:6px;">N° de suivi</label>
                <input type="text" name="tracking" value="<?= e($order['tracking_number'] ?? '') ?>" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm);">
            </div>
            <button type="submit" class="btn btn--primary">Enregistrer</button>
        </form>
    </div>

    <!-- Facture -->
    <div style="margin-top:26px; display:flex; gap:10px;">
        <a href="../api/admin/facture.php?action=pdf&order_id=<?= (int)$order['id'] ?>" class="btn btn--primary"><i class="fas fa-file-pdf"></i> Générer / télécharger la facture PDF</a>
        <button class="btn btn--outline" onclick="openWhatsAppInvoice(<?= (int)$order['id'] ?>, '<?= e($order['order_number']) ?>')"><i class="fab fa-whatsapp"></i> Envoyer par WhatsApp</button>
    </div>

    <!-- Modal WhatsApp facture -->
    <div id="waInvoiceModal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); z-index:2000; align-items:center; justify-content:center;">
        <div style="background:#fff; border-radius:var(--radius); width:100%; max-width:520px; padding:24px;">
            <h4 style="font-size:16px; font-weight:800; color:var(--color-dark); margin-bottom:16px;">Envoyer la facture par WhatsApp</h4>
            <p style="font-size:13px; color:var(--color-gray); margin-bottom:16px;">Numéro : <span class="notranslate" translate="no" style="font-weight:600;"><?= e($settings['whatsapp_order'] ?? '+33 6 12 34 56 78') ?></span></p>
            <div style="display:flex; gap:10px;">
                <a href="#" id="waInvoiceLink" class="btn btn--primary">Ouvrir WhatsApp</a>
                <button class="btn btn--outline" onclick="document.getElementById('waInvoiceModal').style.display='none'">Fermer</button>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>

<script>
function openWhatsAppInvoice(orderId, orderNumber) {
    const link = document.getElementById('waInvoiceLink');
    const msg = encodeURIComponent('Bonjour, voici la facture de votre commande ' + orderNumber + '. Merci.');
    link.href = 'https://wa.me/?text=' + msg;
    document.getElementById('waInvoiceModal').style.display = 'flex';
}
</script>
