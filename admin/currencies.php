<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/page_bootstrap.php';

$db = db();
$settings = loadSettings($db);

// --- Gestion devises ---
$errors = [];
$currForm = ['id' => '', 'code' => '', 'name' => '', 'symbol' => '', 'exchange_rate' => '1.000000', 'is_default' => '0', 'is_active' => '1', 'sort_order' => '0'];
$editing = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!csrfCheck($csrf)) {
        $errors[] = 'Jeton CSRF invalide.';
    } else {
        $action = $_POST['action'] ?? '';
        $currForm['code'] = strtoupper(trim($_POST['code'] ?? ''));
        $currForm['name'] = trim($_POST['name'] ?? '');
        $currForm['symbol'] = trim($_POST['symbol'] ?? '');
        $currForm['exchange_rate'] = (float)($_POST['exchange_rate'] ?? 1);
        $currForm['is_default'] = isset($_POST['is_default']) ? '1' : '0';
        $currForm['is_active'] = isset($_POST['is_active']) ? '1' : '0';
        $currForm['sort_order'] = (int)($_POST['sort_order'] ?? 0);

        if ($action === 'create' || $action === 'update') {
            if ($currForm['code'] === '') { $errors[] = 'Code devise obligatoire (ex. EUR).'; }
            if ($currForm['name'] === '') { $errors[] = 'Nom obligatoire.'; }
            if ($currForm['symbol'] === '') { $errors[] = 'Symbole obligatoire.'; }
            if ($currForm['exchange_rate'] <= 0) { $errors[] = 'Taux de change invalide.'; }

            if ($errors === []) {
                try {
                    if ($action === 'create') {
                        $db->prepare(
                            "INSERT INTO currencies (code, name, symbol, exchange_rate, is_default, is_active, sort_order, created_at, updated_at)
                             VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())"
                        )->execute([
                            $currForm['code'], $currForm['name'], $currForm['symbol'], $currForm['exchange_rate'],
                            $currForm['is_default'], $currForm['is_active'], $currForm['sort_order']
                        ]);
                        $_SESSION['flash']['success'] = 'Devise créée.';
                    } else {
                        $currForm['id'] = (int)($_POST['id'] ?? 0);
                        $db->prepare(
                            "UPDATE currencies SET code=?, name=?, symbol=?, exchange_rate=?, is_default=?, is_active=?, sort_order=?, updated_at=NOW() WHERE id=?"
                        )->execute([
                            $currForm['code'], $currForm['name'], $currForm['symbol'], $currForm['exchange_rate'],
                            $currForm['is_default'], $currForm['is_active'], $currForm['sort_order'], $currForm['id']
                        ]);
                        $_SESSION['flash']['success'] = 'Devise mise à jour.';
                    }
                    redirect('currencies.php');
                } catch (Throwable $e) {
                    $errors[] = 'Erreur : ' . $e->getMessage();
                }
            }
        } elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            $stmt = $db->prepare("SELECT is_default FROM currencies WHERE id = ? LIMIT 1");
            $stmt->execute([$id]);
            $c = $stmt->fetch();
            if ($c !== false && (int)$c['is_default'] === 1) {
                $errors[] = 'Impossible de supprimer la devise par défaut.';
            } else {
                try {
                    $db->prepare("DELETE FROM currencies WHERE id = ?")->execute([$id]);
                    $_SESSION['flash']['success'] = 'Devise supprimée.';
                    redirect('currencies.php');
                } catch (Throwable $e) {
                    $errors[] = 'Erreur : ' . $e->getMessage();
                }
            }
        } elseif ($action === 'set_default') {
            $id = (int)($_POST['id'] ?? 0);
            $db->query("UPDATE currencies SET is_default = 0");
            $db->prepare("UPDATE currencies SET is_default = 1 WHERE id = ?")->execute([$id]);
            $_SESSION['flash']['success'] = 'Devise par défaut mise à jour.';
            redirect('currencies.php');
        }
    }
}

// Pré-remplir édition
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM currencies WHERE id = ? LIMIT 1");
    $stmt->execute([(int)$_GET['edit']]);
    $c = $stmt->fetch();
    if ($c !== false) {
        $editing = true;
        $currForm = [
            'id' => (int)$c['id'],
            'code' => e($c['code']),
            'name' => e($c['name']),
            'symbol' => e($c['symbol']),
            'exchange_rate' => number_format((float)$c['exchange_rate'], 6, '.', ''),
            'is_default' => (int)$c['is_default'],
            'is_active' => (int)$c['is_active'],
            'sort_order' => (int)$c['sort_order'],
        ];
    }
}

$currencies = $db->query("SELECT * FROM currencies ORDER BY sort_order ASC, code ASC")->fetchAll();
$defaultCurrency = $db->query("SELECT code FROM currencies WHERE is_default = 1 LIMIT 1")->fetch();

$pageTitle = 'Devises — Administration';

require_once __DIR__ . '/includes/header.php';
?>

<div class="admin-content" style="padding:26px 22px;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
        <div class="section-title"><i class="fas fa-money-bill-wave"></i> Devises</div>
        <button class="btn btn--primary" onclick="openCurrModal(<?= $editing ? 'true' : 'false' ?>, <?= $editing ? (int)$_GET['edit'] : '0' ?>, '<?= e($currForm['code']) ?>', '<?= e($currForm['name']) ?>', '<?= e($currForm['symbol']) ?>', '<?= e($currForm['exchange_rate']) ?>', <?= (int)$currForm['is_default'] ?>, <?= (int)$currForm['is_active'] ?>, <?= (int)$currForm['sort_order'] ?>)">
            <i class="fas fa-plus"></i> <?= $editing ? 'Modifier' : 'Ajouter' ?> une devise
        </button>
    </div>

    <?php if (hasFlash('success')): ?>
        <div class="alert alert--success" style="margin-bottom:20px;"><?= e(flash('success')) ?></div>
    <?php endif; ?>
    <?php if ($errors): ?>
        <div class="alert alert--error" style="margin-bottom:20px;"><?= implode('<br>', array_map('e', $errors)) ?></div>
    <?php endif; ?>

    <div style="background:#fff; border:1px solid var(--color-border); border-radius:var(--radius); padding:16px; margin-bottom:20px;">
        <div style="font-size:13px; color:var(--color-gray-dark); margin-bottom:10px;"><strong>Devise par défaut :</strong> <span class="notranslate" translate="no"><?= e($defaultCurrency['code'] ?? '—') ?></span></div>
        <p style="font-size:12px; color:var(--color-gray); margin:0;">Les taux de change sont exprimés par rapport à la devise de base (EUR = 1). Le visiteur choisit sa devise et le montant est figé au moment de la commande.</p>
    </div>

    <table class="admin-table">
        <thead>
            <tr>
                <th style="width:60px;">ID</th>
                <th style="width:100px;">Code</th>
                <th>Nom</th>
                <th style="width:100px;">Symbole</th>
                <th style="width:130px;">Taux de change</th>
                <th style="width:100px; text-align:center;">Défaut</th>
                <th style="width:90px; text-align:center;">Actif</th>
                <th style="width:100px; text-align:center;">Ordre</th>
                <th style="width:160px;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($currencies as $c): ?>
                <tr>
                    <td><?= (int)$c['id'] ?></td>
                    <td><span class="notranslate" translate="no"><?= e($c['code']) ?></span></td>
                    <td><?= e($c['name']) ?></td>
                    <td><span class="notranslate" translate="no" style="font-weight:700;"><?= e($c['symbol']) ?></span></td>
                    <td><span class="notranslate" translate="no"><?= number_format((float)$c['exchange_rate'], 6, ',', ' ') ?></span></td>
                    <td style="text-align:center;">
                        <?php if ((int)$c['is_default'] === 1): ?>
                            <span class="badge badge--active">Défaut</span>
                        <?php else: ?>
                            <button class="btn btn--outline btn--sm" style="padding:3px 8px; font-size:11px;" onclick="setDefault(<?= (int)$c['id'] ?>)">Définir défaut</button>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:center;"><span class="badge <?= (int)$c['is_active'] === 1 ? 'badge--active' : 'badge--inactive' ?>"><?= (int)$c['is_active'] === 1 ? 'Oui' : 'Non' ?></span></td>
                    <td style="text-align:center;"><?= (int)$c['sort_order'] ?></td>
                    <td>
                        <button class="btn btn--outline btn--sm" style="padding:4px 8px; font-size:12px;" onclick="openCurrModal(true, <?= (int)$c['id'] ?>, '<?= e($c['code']) ?>', '<?= e($c['name']) ?>', '<?= e($c['symbol']) ?>', '<?= e(number_format((float)$c['exchange_rate'], 6, '.', '')) ?>', <?= (int)$c['is_default'] ?>, <?= (int)$c['is_active'] ?>, <?= (int)$c['sort_order'] ?>)">Modifier</button>
                        <?php if ((int)$c['is_default'] !== 1): ?>
                            <button class="btn btn--outline btn--sm" style="padding:4px 8px; font-size:12px; color:var(--color-error);" onclick="if(confirm('Supprimer cette devise ?')){ deleteCurr(<?= (int)$c['id'] ?>) }">Supprimer</button>
                        <?php else: ?>
                            <span style="font-size:11px; color:var(--color-gray);">Impossible (défaut)</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Modal devise -->
<div id="currModal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); z-index:2000; align-items:center; justify-content:center;">
    <div style="background:#fff; border-radius:var(--radius); width:100%; max-width:520px; max-height:90vh; overflow-y:auto; padding:24px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <h4 id="currModalTitle" style="font-size:16px; font-weight:800; color:var(--color-dark); margin:0;">Nouvelle devise</h4>
            <button onclick="closeCurrModal()" style="background:none; border:none; font-size:20px; color:var(--color-gray);">×</button>
        </div>
        <form id="currForm" method="POST" action="currencies.php" novalidate>
            <?= csrfField() ?>
            <input type="hidden" name="action" id="currAction" value="create">
            <input type="hidden" name="id" id="currId" value="">

            <div class="form-group">
                <label>Code devise <span style="color:var(--color-error);">*</span></label>
                <input type="text" name="code" id="currCode" required maxlength="3">
                <div class="form-help">Ex. EUR, USD, GBP</div>
            </div>
            <div class="form-group">
                <label>Nom <span style="color:var(--color-error);">*</span></label>
                <input type="text" name="name" id="currName" required>
            </div>
            <div class="form-group">
                <label>Symbole <span style="color:var(--color-error);">*</span></label>
                <input type="text" name="symbol" id="currSymbol" required>
                <div class="form-help">Ex. €, $, £</div>
            </div>
            <div class="form-group">
                <label>Taux de change (vs EUR) <span style="color:var(--color-error);">*</span></label>
                <input type="number" name="exchange_rate" id="currRate" step="0.000001" min="0.000001" required>
                <div class="form-help">Taux par rapport à la devise de base (EUR = 1.000000).</div>
            </div>
            <div class="form-group" style="display:flex; align-items:center; gap:8px;">
                <input type="checkbox" name="is_default" id="currIsDefault" value="1">
                <label for="currIsDefault" style="margin:0; cursor:pointer;">Devise par défaut</label>
            </div>
            <div class="form-group" style="display:flex; align-items:center; gap:8px;">
                <input type="checkbox" name="is_active" id="currIsActive" value="1" checked>
                <label for="currIsActive" style="margin:0; cursor:pointer;">Actif (visible pour le visiteur)</label>
            </div>
            <div class="form-group">
                <label>Ordre d'affichage</label>
                <input type="number" name="sort_order" id="currSortOrder" min="0" value="0">
            </div>

            <div style="display:flex; gap:10px; margin-top:20px;">
                <button type="submit" class="btn btn--primary">Enregistrer</button>
                <button type="button" class="btn btn--outline" onclick="closeCurrModal()">Annuler</button>
            </div>
        </form>
    </div>
</div>

<script>
function openCurrModal(editing, id, code, name, symbol, rate, isDefault, isActive, sortOrder) {
    document.getElementById('currAction').value = editing ? 'update' : 'create';
    document.getElementById('currId').value = id;
    document.getElementById('currModalTitle').textContent = editing ? 'Modifier la devise' : 'Nouvelle devise';
    document.getElementById('currCode').value = code;
    document.getElementById('currName').value = name;
    document.getElementById('currSymbol').value = symbol;
    document.getElementById('currRate').value = rate;
    document.getElementById('currIsDefault').checked = isDefault === 1;
    document.getElementById('currIsActive').checked = isActive === 1;
    document.getElementById('currSortOrder').value = sortOrder;
    document.getElementById('currModal').style.display = 'flex';
}
function closeCurrModal() {
    document.getElementById('currModal').style.display = 'none';
}
function deleteCurr(id) {
    var form = document.createElement('form');
    form.method = 'POST';
    form.action = 'currencies.php';
    form.innerHTML = '<input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="' + id + '">';
    document.body.appendChild(form);
    form.submit();
}
function setDefault(id) {
    var form = document.createElement('form');
    form.method = 'POST';
    form.action = 'currencies.php';
    form.innerHTML = '<input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="action" value="set_default"><input type="hidden" name="id" value="' + id + '">';
    document.body.appendChild(form);
    form.submit();
}
window.addEventListener('click', function(e) {
    var m = document.getElementById('currModal');
    if (e.target === m) closeCurrModal();
});
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
