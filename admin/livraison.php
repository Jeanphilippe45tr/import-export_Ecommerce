<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/page_bootstrap.php';

$db = db();
$settings = loadSettings($db);

// --- CRUD zones via API séparée, ici on affiche ---
$zones = $db->query("SELECT * FROM shipping_zones WHERE is_active = 1 ORDER BY sort_order ASC, name ASC")->fetchAll();

// Pays par zone
$zoneCountries = [];
foreach ($zones as $z) {
    $stmt = $db->prepare(
        "SELECT c.id, c.name, c.iso_alpha2, c.phone_code, c.is_blocked
         FROM countries c
         JOIN shipping_zone_countries zc ON zc.country_id = c.id
         WHERE zc.zone_id = ?
         ORDER BY c.name ASC"
    );
    $stmt->execute([(int)$z['id']]);
    $zoneCountries[(int)$z['id']] = $stmt->fetchAll();
}

// Tarifs par zone
$zoneRates = [];
foreach ($zones as $z) {
    $stmt = $db->prepare("SELECT * FROM shipping_rates WHERE zone_id = ? ORDER BY id ASC");
    $stmt->execute([(int)$z['id']]);
    $zoneRates[(int)$z['id']] = $stmt->fetchAll();
}

// Pays disponibles (non encore rattachés à une zone, ou tous pour gestion)
$allCountries = $db->query("SELECT * FROM countries ORDER BY name ASC")->fetchAll();

// Pays bloqués
$blockedCountries = $db->query("SELECT * FROM countries WHERE is_blocked = 1 ORDER BY name ASC")->fetchAll();

$pageTitle = 'Livraison — Administration';

require_once __DIR__ . '/includes/header.php';
?>

<div class="admin-content" style="padding:26px 22px;">
    <div class="section-title"><i class="fas fa-truck"></i> Zones de livraison</div>

    <div class="btn-toolbar">
        <button class="btn btn--primary" onclick="openZoneModal()">+ Nouvelle zone</button>
        <a href="pays.php" class="btn btn--outline"><i class="fas fa-globe"></i> Gérer les pays</a>
    </div>

    <?php if (hasFlash('success')): ?>
        <div class="alert alert--success" style="margin-bottom:20px;"><?= e(flash('success')) ?></div>
    <?php endif; ?>

    <table class="admin-table">
        <thead>
            <tr>
                <th style="width:60px;">ID</th>
                <th>Nom de la zone</th>
                <th style="width:200px;">Description</th>
                <th style="width:100px; text-align:center;">Actif</th>
                <th style="width:100px; text-align:center;">Ordre</th>
                <th style="width:180px;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($zones as $z): ?>
                <tr>
                    <td><?= (int)$z['id'] ?></td>
                    <td><?= e($z['name']) ?></td>
                    <td><?= e($z['description'] ?? '') ?></td>
                    <td style="text-align:center;"><span class="badge <?= (int)$z['is_active'] === 1 ? 'badge--active' : 'badge--inactive' ?>"><?= (int)$z['is_active'] === 1 ? 'Oui' : 'Non' ?></span></td>
                    <td style="text-align:center;"><?= (int)$z['sort_order'] ?></td>
                    <td>
                        <button class="btn btn--outline btn--sm" style="padding:4px 8px;" onclick="openZoneModal(true, <?= (int)$z['id'] ?>, '<?= e($z['name']) ?>', '<?= e($z['description'] ?? '') ?>', <?= (int)$z['sort_order'] ?>, <?= (int)$z['is_active'] ?>)">Modifier</button>
                        <button class="btn btn--outline btn--sm" style="padding:4px 8px;" onclick="openRatesModal(<?= (int)$z['id'] ?>, '<?= e($z['name']) ?>')">Tarifs</button>
                        <button class="btn btn--outline btn--sm" style="padding:4px 8px;" onclick="openCountriesModal(<?= (int)$z['id'] ?>, '<?= e($z['name']) ?>')">Pays</button>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Tarifs détail -->
    <h4 style="font-size:15px; font-weight:800; color:var(--color-dark); margin:30px 0 12px 0;">Tarifs de livraison par zone</h4>
    <?php foreach ($zones as $z): ?>
        <div style="background:#fff; border:1px solid var(--color-border); border-radius:var(--radius); padding:16px; margin-bottom:16px;">
            <div style="font-weight:700; color:var(--color-dark); margin-bottom:10px;"><?= e($z['name']) ?></div>
            <table style="width:100%; border-collapse:collapse; font-size:13px;">
                <thead>
                    <tr style="border-bottom:1px solid var(--color-border);">
                        <th style="text-align:left; padding:8px;">Type</th>
                        <th style="text-align:right; padding:8px;">Prix</th>
                        <th style="text-align:right; padding:8px;">Délai min</th>
                        <th style="text-align:right; padding:8px;">Délai max</th>
                        <th style="text-align:center; padding:8px;">Actif</th>
                        <th style="padding:8px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($zoneRates[(int)$z['id']] as $r): ?>
                        <tr style="border-bottom:1px solid #f0f0f0;">
                            <td><?= e($r['type']) ?></td>
                            <td style="text-align:right;"><span class="notranslate" translate="no"><?= number_format((float)$r['price'], 2, ',', ' ') ?> <?= e($r['currency_code'] ?? 'EUR') ?></span></td>
                            <td style="text-align:right;"><?= $r['delivery_days_min'] ?? '-' ?></td>
                            <td style="text-align:right;"><?= $r['delivery_days_max'] ?? '-' ?></td>
                            <td style="text-align:center;"><span class="badge <?= (int)$r['is_active'] === 1 ? 'badge--active' : 'badge--inactive' ?>"><?= (int)$r['is_active'] === 1 ? 'Oui' : 'Non' ?></span></td>
                            <td>
                                <button class="btn btn--outline btn--sm" style="padding:4px 8px; font-size:11px;" onclick="openRateModal(<?= (int)$r['id'] ?>, <?= (int)$z['id'] ?>, '<?= e($r['type']) ?>', '<?= e($r['price']) ?>', '<?= e($r['currency_code'] ?? 'EUR') ?>', <?= $r['delivery_days_min'] ?? 'null' ?>, <?= $r['delivery_days_max'] ?? 'null' ?>, <?= (int)$r['is_active'] ?>)">Modifier</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <tr>
                        <td colspan="6" style="padding:8px; text-align:right;">
                            <button class="btn btn--primary btn--sm" style="font-size:12px;" onclick="openRateModal(null, <?= (int)$z['id'] ?>)">+ Ajouter un tarif</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    <?php endforeach; ?>

    <!-- Modal zone -->
    <div id="zoneModal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); z-index:2000; align-items:center; justify-content:center;">
        <div style="background:#fff; border-radius:var(--radius); width:100%; max-width:520px; padding:24px; max-height:90vh; overflow-y:auto;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                <h4 id="zoneModalTitle" style="font-size:16px; font-weight:800; color:var(--color-dark); margin:0;">Nouvelle zone</h4>
                <button onclick="closeZoneModal()" style="background:none; border:none; font-size:20px; color:var(--color-gray);">×</button>
            </div>
            <form method="POST" action="../api/admin/livraison.php?action=zone_save" novalidate>
                <?= csrfField() ?>
                <input type="hidden" name="id" id="zoneId" value="">
                <div class="form-group">
                    <label>Nom de la zone <span style="color:var(--color-error);">*</span></label>
                    <input type="text" name="name" id="zoneName" required>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" id="zoneDescription" rows="2"></textarea>
                </div>
                <div class="form-group">
                    <label>Ordre d'affichage</label>
                    <input type="number" name="sort_order" id="zoneSortOrder" min="0" value="0">
                </div>
                <div class="form-group" style="display:flex; align-items:center; gap:8px;">
                    <input type="checkbox" name="is_active" id="zoneIsActive" value="1" checked>
                    <label for="zoneIsActive" style="margin:0; cursor:pointer;">Zone active</label>
                </div>
                <div style="display:flex; gap:10px; margin-top:20px;">
                    <button type="submit" class="btn btn--primary">Enregistrer</button>
                    <button type="button" class="btn btn--outline" onclick="closeZoneModal()">Annuler</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal tarifs -->
    <div id="ratesModal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); z-index:2000; align-items:center; justify-content:center;">
        <div style="background:#fff; border-radius:var(--radius); width:100%; max-width:600px; padding:24px; max-height:90vh; overflow-y:auto;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                <h4 id="ratesModalTitle" style="font-size:16px; font-weight:800; color:var(--color-dark); margin:0;">Tarifs — <span id="ratesZoneName"></span></h4>
                <button onclick="closeRatesModal()" style="background:none; border:none; font-size:20px; color:var(--color-gray);">×</button>
            </div>
            <input type="hidden" id="ratesZoneId">
            <table class="admin-table" style="margin-bottom:16px;">
                <thead>
                    <tr>
                        <th style="width:120px;">Type</th>
                        <th style="text-align:right; width:100px;">Prix</th>
                        <th style="text-align:center; width:80px;">Délai min</th>
                        <th style="text-align:center; width:80px;">Délai max</th>
                        <th style="width:100px;">Actions</th>
                    </tr>
                </thead>
                <tbody id="ratesTableBody"></tbody>
            </table>
            <div style="text-align:right;">
                <button class="btn btn--primary" onclick="openRateModal(null, document.getElementById('ratesZoneId').value)">+ Ajouter un tarif</button>
                <button class="btn btn--outline" onclick="closeRatesModal()">Fermer</button>
            </div>
        </div>
    </div>

    <!-- Modal tarif -->
    <div id="rateModal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); z-index:2000; align-items:center; justify-content:center;">
        <div style="background:#fff; border-radius:var(--radius); width:100%; max-width:520px; padding:24px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                <h4 style="font-size:16px; font-weight:800; color:var(--color-dark); margin:0;">Tarif de livraison</h4>
                <button onclick="closeRateModal()" style="background:none; border:none; font-size:20px; color:var(--color-gray);">×</button>
            </div>
            <form method="POST" action="../api/admin/livraison.php?action=rate_save" novalidate>
                <?= csrfField() ?>
                <input type="hidden" name="id" id="rateId" value="">
                <input type="hidden" name="zone_id" id="rateZoneId" value="">
                <div class="form-group">
                    <label>Type de tarif <span style="color:var(--color-error);">*</span></label>
                    <select name="type" id="rateType" required>
                        <option value="fixed">Forfait fixe</option>
                        <option value="per_weight">Au poids</option>
                        <option value="per_amount_tier">Par tranche de montant</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Prix (EUR) <span style="color:var(--color-error);">*</span></label>
                    <input type="number" name="price" id="ratePrice" step="0.01" min="0" required>
                </div>
                <div class="form-group">
                    <label>Devise</label>
                    <input type="text" name="currency_code" id="rateCurrency" value="EUR" maxlength="3">
                </div>
                <div class="form-group">
                    <label>Délai min (jours)</label>
                    <input type="number" name="delivery_days_min" id="rateDaysMin" min="0">
                </div>
                <div class="form-group">
                    <label>Délai max (jours)</label>
                    <input type="number" name="delivery_days_max" id="rateDaysMax" min="0">
                </div>
                <div class="form-group" style="display:flex; align-items:center; gap:8px;">
                    <input type="checkbox" name="is_active" id="rateIsActive" value="1" checked>
                    <label for="rateIsActive" style="margin:0; cursor:pointer;">Actif</label>
                </div>
                <div style="display:flex; gap:10px; margin-top:20px;">
                    <button type="submit" class="btn btn--primary">Enregistrer</button>
                    <button type="button" class="btn btn--outline" onclick="closeRateModal()">Annuler</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal pays de zone -->
    <div id="countriesModal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); z-index:2000; align-items:center; justify-content:center;">
        <div style="background:#fff; border-radius:var(--radius); width:100%; max-width:700px; padding:24px; max-height:90vh; overflow-y:auto;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                <h4 id="countriesModalTitle" style="font-size:16px; font-weight:800; color:var(--color-dark); margin:0;">Pays — <span id="countriesZoneName"></span></h4>
                <button onclick="closeCountriesModal()" style="background:none; border:none; font-size:20px; color:var(--color-gray);">×</button>
            </div>
            <input type="hidden" id="countriesZoneId">
            <p style="font-size:12px; color:var(--color-gray); margin-bottom:12px;">Sélectionner les pays rattachés à cette zone.</p>
            <div style="max-height:400px; overflow-y:auto; border:1px solid var(--color-border); border-radius:var(--radius-sm); padding:10px; margin-bottom:16px;">
                <div id="countriesList"></div>
            </div>
            <div style="display:flex; gap:10px;">
                <button class="btn btn--primary" onclick="saveZoneCountries()">Enregistrer</button>
                <button class="btn btn--outline" onclick="closeCountriesModal()">Fermer</button>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>

<script>
var LIVRAISON_CSRF = <?= json_encode(csrfToken(), JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

function openZoneModal(editing, id, name, description, sortOrder, isActive) {
    document.getElementById('zoneModalTitle').textContent = editing ? 'Modifier la zone' : 'Nouvelle zone';
    document.getElementById('zoneId').value = id || '';
    document.getElementById('zoneName').value = name || '';
    document.getElementById('zoneDescription').value = description || '';
    document.getElementById('zoneSortOrder').value = sortOrder || 0;
    document.getElementById('zoneIsActive').checked = isActive === 1;
    document.getElementById('zoneModal').style.display = 'flex';
}
function closeZoneModal() { document.getElementById('zoneModal').style.display = 'none'; }

function openRatesModal(zoneId, zoneName) {
    document.getElementById('ratesZoneId').value = zoneId;
    document.getElementById('ratesZoneName').textContent = zoneName;
    fetch('../api/admin/livraison.php?action=zone_rates&zone_id=' + zoneId, { credentials: 'same-origin' })
        .then(r => r.json())
        .then(data => {
            const tbody = document.getElementById('ratesTableBody');
            tbody.innerHTML = '';
            (data.rates || []).forEach(r => {
                const tr = document.createElement('tr');
                tr.innerHTML = '<td>' + r.type + '</td>' +
                    '<td style="text-align:right;">' + r.price + ' ' + r.currency_code + '</td>' +
                    '<td style="text-align:center;">' + (r.delivery_days_min ?? '-') + '</td>' +
                    '<td style="text-align:center;">' + (r.delivery_days_max ?? '-') + '</td>' +
                    '<td><button class="btn btn--outline btn--sm" style="padding:4px 8px; font-size:11px;" onclick="openRateModal(' + r.id + ', ' + zoneId + ', \'' + r.type + '\', \'' + r.price + '\', \'' + r.currency_code + '\', ' + (r.delivery_days_min ?? 'null') + ', ' + (r.delivery_days_max ?? 'null') + ', ' + r.is_active + ')">Modifier</button></td>';
                tbody.appendChild(tr);
            });
        });
    document.getElementById('ratesModal').style.display = 'flex';
}
function closeRatesModal() { document.getElementById('ratesModal').style.display = 'none'; }

function openRateModal(id, zoneId, type, price, currency, daysMin, daysMax, isActive) {
    document.getElementById('rateId').value = id || '';
    document.getElementById('rateZoneId').value = zoneId;
    document.getElementById('rateType').value = type || 'fixed';
    document.getElementById('ratePrice').value = price || '';
    document.getElementById('rateCurrency').value = currency || 'EUR';
    document.getElementById('rateDaysMin').value = daysMin === null ? '' : daysMin;
    document.getElementById('rateDaysMax').value = daysMax === null ? '' : daysMax;
    document.getElementById('rateIsActive').checked = isActive === 1;
    document.getElementById('rateModal').style.display = 'flex';
}
function closeRateModal() { document.getElementById('rateModal').style.display = 'none'; }

function openCountriesModal(zoneId, zoneName) {
    document.getElementById('countriesZoneId').value = zoneId;
    document.getElementById('countriesZoneName').textContent = zoneName;
    const container = document.getElementById('countriesList');
    container.innerHTML = '<p style="font-size:12px; color:var(--color-gray);">Chargement des pays…</p>';
    fetch('../api/admin/livraison.php?action=zone_countries&zone_id=' + encodeURIComponent(zoneId), { credentials: 'same-origin' })
        .then(r => r.json())
        .then(data => {
            container.innerHTML = '';
            const inZone = Array.isArray(data.in_zone) ? data.in_zone : [];
            (data.all || []).forEach(c => {
                const label = document.createElement('label');
                label.style.cssText = 'display:flex; align-items:center; gap:8px; padding:4px 0; cursor:pointer;';

                const cb = document.createElement('input');
                cb.type = 'checkbox';
                cb.name = 'country';
                cb.value = c.iso_alpha2;
                cb.checked = inZone.indexOf(c.iso_alpha2) !== -1;

                const txt = document.createElement('span');
                txt.textContent = c.name + ' (' + c.phone_code + ')';

                label.appendChild(cb);
                label.appendChild(txt);

                if (String(c.is_blocked) === '1') {
                    const blocked = document.createElement('span');
                    blocked.style.cssText = 'color:var(--color-error); font-size:11px;';
                    blocked.textContent = ' (bloqué)';
                    label.appendChild(blocked);
                }
                container.appendChild(label);
            });
            if (!(data.all || []).length) {
                container.innerHTML = '<p style="font-size:12px; color:var(--color-gray);">Aucun pays disponible.</p>';
            }
        })
        .catch(() => {
            container.innerHTML = '<p style="font-size:12px; color:var(--color-error);">Impossible de charger les pays.</p>';
        });
    document.getElementById('countriesModal').style.display = 'flex';
}
function closeCountriesModal() { document.getElementById('countriesModal').style.display = 'none'; }

function saveZoneCountries() {
    const zoneId = document.getElementById('countriesZoneId').value;
    const checkboxes = document.querySelectorAll('#countriesList input[name="country"]:checked');
    const countries = Array.from(checkboxes).map(cb => cb.value);
    fetch('../api/admin/livraison.php?action=zone_countries_save', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        credentials: 'same-origin',
        body: JSON.stringify({ csrf_token: LIVRAISON_CSRF, zone_id: zoneId, countries: countries })
    }).then(r => r.json()).then(data => {
        if (data.ok) {
            closeCountriesModal();
            location.reload();
        } else {
            alert(data.message || 'Erreur.');
        }
    }).catch(() => alert('Erreur réseau lors de l’enregistrement.'));
}

window.addEventListener('click', function(e) {
    ['zoneModal','ratesModal','rateModal','countriesModal'].forEach(id => {
        const m = document.getElementById(id);
        if (e.target === m) m.style.display = 'none';
    });
});
</script>
