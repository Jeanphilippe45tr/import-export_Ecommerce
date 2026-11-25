<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/page_bootstrap.php';

$db = db();

// --- Recherche / filtre simple par nom (également sur les codes et l’indicatif) ---
$search = trim((string)($_GET['search'] ?? ''));
$where = [];
$params = [];

if ($search !== '') {
    $where[] = '(name LIKE ? OR iso_alpha2 LIKE ? OR iso_alpha3 LIKE ? OR phone_code LIKE ?)';
    $like = '%' . $search . '%';
    $params = [$like, $like, $like, $like];
}

$whereSql = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);

$stmt = $db->prepare(
    "SELECT id, name, iso_alpha2, iso_alpha3, phone_code, continent, is_eu, is_blocked, sort_order
     FROM countries
     $whereSql
     ORDER BY sort_order ASC, name ASC"
);
$stmt->execute($params);
$countries = $stmt->fetchAll();

$totalCountries = (int)$db->query("SELECT COUNT(*) FROM countries")->fetchColumn();
$blockedCount = (int)$db->query("SELECT COUNT(*) FROM countries WHERE is_blocked = 1")->fetchColumn();

$pageTitle = 'Pays';

require_once __DIR__ . '/includes/header.php';
?>

<div class="admin-content" style="padding:26px 22px;">
    <div class="admin-page-head" style="display:flex; justify-content:space-between; align-items:flex-start; gap:16px; flex-wrap:wrap; margin-bottom:18px;">
        <div>
            <div class="section-title"><i class="fas fa-globe"></i> Gestion des pays</div>
            <p class="admin-page-subtitle" style="font-size:13px; color:var(--color-gray); margin:6px 0 0;">
                <?= (int)$totalCountries ?> pays enregistré<?= (int)$totalCountries > 1 ? 's' : '' ?> — <?= (int)$blockedCount ?> bloqué<?= (int)$blockedCount > 1 ? 's' : '' ?>
            </p>
        </div>
        <div class="admin-page-actions" style="display:flex; gap:10px; flex-wrap:wrap;">
            <button type="button" class="btn btn--primary" onclick="openPaysModal(0)">
                <i class="fas fa-plus"></i> Ajouter un pays
            </button>
            <a class="btn btn--outline btn--sm" href="../api/admin/pays.php?action=pays_export&amp;csrf_token=<?= e(csrfToken()) ?>">
                <i class="fas fa-file-csv"></i> Exporter CSV
            </a>
        </div>
    </div>

    <form method="GET" action="pays.php" style="display:flex; gap:10px; align-items:flex-end; margin-bottom:20px; background:#fff; padding:14px 18px; border:1px solid var(--color-border); border-radius:var(--radius); flex-wrap:wrap;">
        <div style="flex:1 1 260px; min-width:200px;">
            <label for="paysSearch" class="visually-hidden">Rechercher un pays</label>
            <input type="search" id="paysSearch" name="search" placeholder="Nom, code ISO ou indicatif…" value="<?= e($search) ?>" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm);">
        </div>
        <button type="submit" class="btn btn--primary btn--sm"><i class="fas fa-search"></i> Filtrer</button>
        <a href="pays.php" class="btn btn--outline btn--sm">Réinitialiser</a>
    </form>

    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th style="width:60px;">ID</th>
                    <th>Nom</th>
                    <th style="width:120px;">Code ISO Alpha-2</th>
                    <th style="width:120px;">Indicatif</th>
                    <th style="width:150px;">Continent</th>
                    <th style="width:80px; text-align:center;">UE</th>
                    <th style="width:110px; text-align:center;">Bloqué</th>
                    <th style="width:70px; text-align:center;">Ordre</th>
                    <th style="width:210px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($countries as $c): ?>
                    <?php
                        $isEu = (int)$c['is_eu'] === 1;
                        $isBlocked = (int)$c['is_blocked'] === 1;
                    ?>
                    <tr>
                        <td>#<?= (int)$c['id'] ?></td>
                        <td><strong><?= e($c['name']) ?></strong></td>
                        <td>
                            <span class="notranslate" translate="no"><?= e($c['iso_alpha2']) ?></span>
                            <?php if (!empty($c['iso_alpha3'])): ?>
                                <div class="cell-sub notranslate" translate="no"><?= e($c['iso_alpha3']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="notranslate" translate="no"><?= e($c['phone_code']) ?></td>
                        <td>
                            <?php if (!empty($c['continent'])): ?>
                                <?= e($c['continent']) ?>
                            <?php else: ?>
                                <span style="color:var(--color-gray-light);">—</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:center;">
                            <span class="badge <?= $isEu ? 'badge--active' : 'badge--inactive' ?>"><?= $isEu ? 'Oui' : 'Non' ?></span>
                        </td>
                        <td style="text-align:center;">
                            <span class="badge <?= $isBlocked ? 'badge--inactive' : 'badge--active' ?>"><?= $isBlocked ? 'Oui' : 'Non' ?></span>
                            <button type="button" class="btn btn--outline btn--sm" style="display:block; margin:6px auto 0; padding:3px 7px; font-size:11px;" onclick="togglePaysBlock(<?= (int)$c['id'] ?>, <?= $isBlocked ? 1 : 0 ?>)">
                                <?= $isBlocked ? 'Débloquer' : 'Bloquer' ?>
                            </button>
                        </td>
                        <td style="text-align:center;"><?= (int)$c['sort_order'] ?></td>
                        <td>
                            <button type="button" class="btn btn--outline btn--sm" style="padding:4px 8px; margin-right:4px;" onclick="openPaysModal(<?= (int)$c['id'] ?>)">
                                <i class="fas fa-edit"></i> Modifier
                            </button>
                            <button type="button" class="btn btn--outline btn--sm" style="padding:4px 8px; color:var(--color-error); border-color:#fecaca;" onclick="deletePays(<?= (int)$c['id'] ?>, <?= json_encode((string)$c['name'], JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)">
                                <i class="fas fa-trash"></i> Supprimer
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($countries === []): ?>
        <div class="empty-state" style="margin-top:16px;">
            <i class="fas fa-globe"></i>
            <p>Aucun pays trouvé<?= $search !== '' ? ' pour « ' . e($search) . ' »' : '' ?>.</p>
        </div>
    <?php endif; ?>
</div>

<!-- Modale d’ajout / modification -->
<div id="paysModal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); z-index:2000; align-items:center; justify-content:center;" role="dialog" aria-modal="true" aria-labelledby="paysModalTitle">
    <div style="background:#fff; border-radius:var(--radius); width:calc(100% - 24px); max-width:620px; max-height:92vh; overflow-y:auto; padding:24px; box-shadow:var(--shadow);">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; gap:12px;">
            <h4 id="paysModalTitle" style="font-size:16px; font-weight:800; color:var(--color-dark); margin:0;">Ajouter un pays</h4>
            <button type="button" onclick="closePaysModal()" aria-label="Fermer" style="background:none; border:none; font-size:20px; color:var(--color-gray); cursor:pointer;">×</button>
        </div>

        <div id="paysFormError" role="alert" style="display:none; margin-bottom:14px; padding:10px 12px; border-radius:var(--radius-sm); background:#fef2f2; border:1px solid #fecaca; color:#991b1b; font-size:13px;"></div>

        <form id="paysForm" method="POST" action="../api/admin/pays.php?action=pays_save" novalidate>
            <?= csrfField() ?>
            <input type="hidden" name="id" id="paysId" value="0">

            <div class="form-group">
                <label for="paysName">Nom du pays <span style="color:var(--color-error);">*</span></label>
                <input type="text" id="paysName" name="name" maxlength="150" required autocomplete="off">
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label for="paysIso2">Code ISO Alpha-2 <span style="color:var(--color-error);">*</span></label>
                    <input type="text" id="paysIso2" name="iso_alpha2" maxlength="2" required pattern="[A-Za-z]{2}" placeholder="FR" style="text-transform:uppercase;">
                    <div class="form-help">2 lettres, ex. FR, BE, US.</div>
                </div>
                <div class="form-group">
                    <label for="paysIso3">Code ISO Alpha-3</label>
                    <input type="text" id="paysIso3" name="iso_alpha3" maxlength="3" pattern="[A-Za-z]{3}" placeholder="FRA" style="text-transform:uppercase;">
                    <div class="form-help">3 lettres, ex. FRA.</div>
                </div>
                <div class="form-group">
                    <label for="paysPhone">Indicatif téléphonique <span style="color:var(--color-error);">*</span></label>
                    <input type="text" id="paysPhone" name="phone_code" maxlength="10" required pattern="\+?[0-9]{1,9}" placeholder="+33">
                    <div class="form-help">Ex. +33, +1, +971. Le « + » est ajouté automatiquement.</div>
                </div>
                <div class="form-group">
                    <label for="paysContinent">Continent</label>
                    <input type="text" id="paysContinent" name="continent" maxlength="50" list="paysContinents" placeholder="Europe">
                    <datalist id="paysContinents">
                        <option value="Europe"></option>
                        <option value="Europe / Asie"></option>
                        <option value="Asie"></option>
                        <option value="Afrique"></option>
                        <option value="Amérique du Nord"></option>
                        <option value="Amérique du Sud"></option>
                        <option value="Océanie"></option>
                    </datalist>
                </div>
                <div class="form-group">
                    <label for="paysSort">Ordre d’affichage</label>
                    <input type="number" id="paysSort" name="sort_order" min="0" max="4294967295" step="1" value="0">
                </div>
            </div>

            <div class="form-check" style="display:flex; align-items:center; gap:8px; margin:6px 0;">
                <input type="checkbox" id="paysIsEu" name="is_eu" value="1">
                <label for="paysIsEu" style="margin:0;">Pays de l’Union européenne</label>
            </div>
            <div class="form-check" style="display:flex; align-items:center; gap:8px; margin:6px 0 18px;">
                <input type="checkbox" id="paysIsBlocked" name="is_blocked" value="1">
                <label for="paysIsBlocked" style="margin:0;">Bloquer ce pays (plus proposé au paiement)</label>
            </div>

            <div style="display:flex; gap:10px; margin-top:20px; flex-wrap:wrap;">
                <button type="submit" class="btn btn--primary"><i class="fas fa-save"></i> Enregistrer</button>
                <button type="button" class="btn btn--outline" onclick="closePaysModal()">Annuler</button>
            </div>
        </form>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>

<script>
(function () {
    'use strict';

    var csrf = <?= json_encode(csrfToken(), JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var modal = document.getElementById('paysModal');
    var form = document.getElementById('paysForm');
    var errorBox = document.getElementById('paysFormError');

    function api(action, data) {
        var body = new URLSearchParams();
        body.append('csrf_token', csrf);
        Object.keys(data || {}).forEach(function (key) {
            if (data[key] !== null && data[key] !== undefined) {
                body.append(key, data[key]);
            }
        });
        return fetch('../api/admin/pays.php?action=' + action, {
            method: 'POST',
            body: body,
            credentials: 'same-origin'
        }).then(function (response) {
            return response.json();
        });
    }

    function showError(message) {
        errorBox.textContent = message || 'Une erreur est survenue.';
        errorBox.style.display = 'block';
    }

    function clearError() {
        errorBox.textContent = '';
        errorBox.style.display = 'none';
    }

    function setField(id, value) {
        document.getElementById(id).value = (value === null || value === undefined) ? '' : value;
    }

    window.openPaysModal = function (rawId) {
        clearError();
        form.reset();
        setField('paysId', 0);
        document.getElementById('paysModalTitle').textContent = 'Ajouter un pays';

        var id = parseInt(rawId, 10) || 0;
        if (id > 0) {
            document.getElementById('paysModalTitle').textContent = 'Modifier le pays';
            api('pays_list', {}).then(function (data) {
                if (!data.ok) {
                    showError(data.message || 'Impossible de charger les pays.');
                    return;
                }
                var pays = (data.countries || []).find(function (item) {
                    return parseInt(item.id, 10) === id;
                });
                if (!pays) {
                    showError('Pays introuvable.');
                    return;
                }
                setField('paysId', pays.id);
                setField('paysName', pays.name);
                setField('paysIso2', pays.iso_alpha2);
                setField('paysIso3', pays.iso_alpha3);
                setField('paysPhone', pays.phone_code);
                setField('paysContinent', pays.continent);
                setField('paysSort', pays.sort_order);
                document.getElementById('paysIsEu').checked = parseInt(pays.is_eu, 10) === 1;
                document.getElementById('paysIsBlocked').checked = parseInt(pays.is_blocked, 10) === 1;
            }).catch(function () {
                showError('Erreur réseau lors du chargement du pays.');
            });
        }

        modal.style.display = 'flex';
        window.setTimeout(function () { document.getElementById('paysName').focus(); }, 0);
    };

    window.closePaysModal = function () {
        modal.style.display = 'none';
    };

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        clearError();

        var payload = new FormData(form);
        fetch('../api/admin/pays.php?action=pays_save', {
            method: 'POST',
            body: payload,
            credentials: 'same-origin'
        }).then(function (response) {
            return response.json();
        }).then(function (data) {
            if (data.ok) {
                window.location.reload();
            } else {
                showError(data.message);
            }
        }).catch(function () {
            showError('Erreur réseau lors de l’enregistrement.');
        });
    });

    window.togglePaysBlock = function (rawId, rawCurrent) {
        var id = parseInt(rawId, 10) || 0;
        var target = parseInt(rawCurrent, 10) === 1 ? 0 : 1;
        api('pays_save', { id: id, is_blocked: target }).then(function (data) {
            if (data.ok) {
                window.location.reload();
            } else {
                window.alert(data.message || 'Erreur lors du changement de statut.');
            }
        }).catch(function () {
            window.alert('Erreur réseau lors du changement de statut.');
        });
    };

    window.deletePays = function (rawId, name) {
        var id = parseInt(rawId, 10) || 0;
        if (!window.confirm('Supprimer le pays « ' + name + ' » ?')) {
            return;
        }
        api('pays_delete', { id: id }).then(function (data) {
            if (data.ok) {
                window.location.reload();
            } else {
                window.alert(data.message || 'Erreur lors de la suppression.');
            }
        }).catch(function () {
            window.alert('Erreur réseau lors de la suppression.');
        });
    };

    window.addEventListener('click', function (event) {
        if (event.target === modal) {
            closePaysModal();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closePaysModal();
        }
    });
})();
</script>
