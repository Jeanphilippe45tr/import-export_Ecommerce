<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/page_bootstrap.php';

$db = db();
$settings = loadSettings($db);

// --- TVA par pays ---
$vatRates = $db->query("SELECT * FROM vat_rates ORDER BY country_name ASC")->fetchAll();

// Règles TVA (stockées dans settings)
$vatEnabled = !empty($settings['vat_enabled']) && $settings['vat_enabled'] === '1';
$shopCountry = $settings['shop_country'] ?? 'FR';
$euVatRule = $settings['eu_vat_rule'] ?? 'destination'; // destination ou vendeur

$pageTitle = 'TVA — Administration';

require_once __DIR__ . '/includes/header.php';
?>

<div class="admin-content" style="padding:26px 22px;">
    <div class="section-title"><i class="fas fa-percentage"></i> Taux de TVA par pays</div>

    <div class="btn-toolbar" style="margin-bottom:20px;">
        <button class="btn btn--primary" onclick="openVatModal()">+ Ajouter / modifier un taux</button>
    </div>

    <?php if (hasFlash('success')): ?>
        <div class="alert alert--success" style="margin-bottom:20px;"><?= e(flash('success')) ?></div>
    <?php endif; ?>

    <table class="admin-table">
        <thead>
            <tr>
                <th style="width:60px;">ID</th>
                <th>Pays</th>
                <th style="width:100px;">Code ISO</th>
                <th style="text-align:right; width:120px;">Taux (%)</th>
                <th style="width:130px;">Type</th>
                <th style="text-align:center; width:90px;">UE</th>
                <th style="width:160px;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($vatRates as $v): ?>
                <tr>
                    <td><?= (int)$v['id'] ?></td>
                    <td><?= e($v['country_name']) ?></td>
                    <td><span class="notranslate" translate="no"><?= e($v['country_iso_alpha2']) ?></span></td>
                    <td style="text-align:right;"><span class="notranslate" translate="no"><?= number_format((float)$v['rate'], 2, ',', ' ') ?></span></td>
                    <td><?= e($v['type']) ?></td>
                    <td style="text-align:center;"><span class="badge <?= (int)$v['is_eu'] === 1 ? 'badge--active' : 'badge--inactive' ?>"><?= (int)$v['is_eu'] === 1 ? 'Oui' : 'Non' ?></span></td>
                    <td>
                        <button class="btn btn--outline btn--sm" style="padding:4px 8px;" onclick="openVatModal(true, <?= (int)$v['id'] ?>, '<?= e($v['country_name']) ?>', '<?= e($v['country_iso_alpha2']) ?>', '<?= e($v['rate']) ?>', '<?= e($v['type']) ?>', <?= (int)$v['is_eu'] ?>)">Modifier</button>
                        <button class="btn btn--outline btn--sm" style="padding:4px 8px; color:var(--color-error);" onclick="if(confirm('Supprimer ce taux TVA ?')){ deleteVat(<?= (int)$v['id'] ?>) }">Supprimer</button>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Règles TVA -->
    <div style="background:#fff; border:1px solid var(--color-border); border-radius:var(--radius); padding:22px; margin-top:30px;">
        <h4 style="font-size:15px; font-weight:800; color:var(--color-dark); margin-bottom:16px; border-bottom:1px solid var(--color-border); padding-bottom:8px;">Règles de TVA</h4>

        <form action="../api/admin/tva.php?action=rules_save" method="POST" novalidate>
            <?= csrfField() ?>

            <div class="form-group" style="display:flex; align-items:center; gap:8px; margin-bottom:16px;">
                <input type="checkbox" name="vat_enabled" id="vatEnabled" value="1" <?= $vatEnabled ? 'checked' : '' ?>>
                <label for="vatEnabled" style="margin:0; cursor:pointer; font-weight:600;">TVA activée</label>
                <span style="font-size:12px; color:var(--color-gray); margin-left:10px;">Si désactivé : vendeur non assujetti, mention légale correspondante.</span>
            </div>

            <div class="form-group" style="margin-bottom:16px;">
                <label>Pays du vendeur <span style="color:var(--color-error);">*</span></label>
                <input type="text" name="shop_country" id="shopCountry" value="<?= e($shopCountry) ?>" maxlength="2">
                <div class="form-help">Code ISO Alpha-2 (ex. FR). Détermine l'assujettissement TVA.</div>
            </div>

            <div class="form-group" style="margin-bottom:20px;">
                <label>Règle de TVA pour vente à un particulier dans l'UE</label>
                <select name="eu_vat_rule" id="euVatRule">
                    <option value="destination" <?= $euVatRule === 'destination' ? 'selected' : '' ?>>TVA du pays de destination (distance selling)</option>
                    <option value="seller" <?= $euVatRule === 'seller' ? 'selected' : '' ?>>TVA du pays du vendeur</option>
                </select>
                <div class="form-help">S'applique aux ventes B2C au sein de l'Union européenne.</div>
            </div>

            <button type="submit" class="btn btn--primary">Enregistrer les règles</button>
        </form>
    </div>

    <div style="margin-top:24px; font-size:12px; color:var(--color-gray); line-height:1.6;">
        <p><strong>Règles appliquées :</strong></p>
        <ul>
            <li>Vente à un particulier dans l'UE : <?= $euVatRule === 'destination' ? 'TVA du pays de destination' : 'TVA du pays du vendeur' ?>.</li>
            <li>Vente hors UE : 0 % avec mention « Exportation, TVA non applicable ».</li>
            <li>Vente à une entreprise de l'UE avec n° de TVA intracommunautaire valide : autoliquidation.</li>
            <li>Les champs « Entreprise » et « N° de TVA » sont facultatifs dans le tunnel de commande.</li>
        </ul>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>

<!-- Modal TVA -->
<div id="vatModal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); z-index:2000; align-items:center; justify-content:center;">
    <div style="background:#fff; border-radius:var(--radius); width:100%; max-width:520px; padding:24px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <h4 id="vatModalTitle" style="font-size:16px; font-weight:800; color:var(--color-dark); margin:0;">Taux de TVA</h4>
            <button onclick="closeVatModal()" style="background:none; border:none; font-size:20px; color:var(--color-gray);">×</button>
        </div>
        <form id="vatForm" method="POST" action="../api/admin/tva.php?action=rate_save" novalidate>
            <?= csrfField() ?>
            <input type="hidden" name="id" id="vatId" value="">
            <div class="form-group">
                <label>Pays</label>
                <select name="country_iso" id="vatCountry" required>
                    <option value="">Sélectionner un pays</option>
                    <?php
                    $allC = $db->query("SELECT * FROM countries ORDER BY name ASC")->fetchAll();
                    foreach ($allC as $c): ?>
                        <option value="<?= e($c['iso_alpha2']) ?>"><?= e($c['name']) ?> <?= nt('(' . $c['phone_code'] . ')') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Taux (%) <span style="color:var(--color-error);">*</span></label>
                <input type="number" name="rate" id="vatRate" step="0.0001" min="0" required>
            </div>
            <div class="form-group">
                <label>Type</label>
                <select name="type" id="vatType">
                    <option value="standard">Standard</option>
                    <option value="reduced">Réduit</option>
                    <option value="zero">Zéro</option>
                    <option value="exempt">Exonéré</option>
                </select>
            </div>
            <div class="form-group" style="display:flex; align-items:center; gap:8px;">
                <input type="checkbox" name="is_eu" id="vatIsEu" value="1">
                <label for="vatIsEu" style="margin:0; cursor:pointer;">Pays de l'Union européenne</label>
            </div>
            <div style="display:flex; gap:10px; margin-top:20px;">
                <button type="submit" class="btn btn--primary">Enregistrer</button>
                <button type="button" class="btn btn--outline" onclick="closeVatModal()">Annuler</button>
            </div>
        </form>
    </div>
</div>

<script>
function openVatModal(editing, id, name, iso, rate, type, isEu) {
    document.getElementById('vatModalTitle').textContent = editing ? 'Modifier le taux TVA' : 'Nouveau taux TVA';
    document.getElementById('vatId').value = id || '';
    document.getElementById('vatCountry').value = iso || '';
    document.getElementById('vatRate').value = rate || '';
    document.getElementById('vatType').value = type || 'standard';
    document.getElementById('vatIsEu').checked = isEu === 1;
    document.getElementById('vatModal').style.display = 'flex';
}
function closeVatModal() { document.getElementById('vatModal').style.display = 'none'; }
function deleteVat(id) {
    if (!confirm('Supprimer ce taux TVA ?')) return;
    var form = document.createElement('form');
    form.method = 'POST';
    form.action = 'api/admin/tva.php?action=rate_delete';
    form.innerHTML = '<input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="id" value="' + id + '">';
    document.body.appendChild(form);
    form.submit();
}
window.addEventListener('click', function(e) {
    const m = document.getElementById('vatModal');
    if (e.target === m) m.style.display = 'none';
});
</script>
