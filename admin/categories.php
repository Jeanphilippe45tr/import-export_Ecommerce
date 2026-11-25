<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/page_bootstrap.php';

$db = db();

// --- Récupérer toutes les catégories actives et inactives pour l'arborescence ---
$stmt = $db->query("SELECT * FROM categories ORDER BY parent_id IS NULL DESC, parent_id ASC, sort_order ASC, name ASC");
$allCats = $stmt->fetchAll();

// Construire l'arborescence
$byId = [];
$tree = [];
foreach ($allCats as $cat) {
    $cat['children'] = [];
    $byId[(int)$cat['id']] = $cat;
}
foreach ($allCats as $cat) {
    if ($cat['parent_id'] !== null && isset($byId[(int)$cat['parent_id']])) {
        $byId[(int)$cat['parent_id']]['children'][] = &$byId[(int)$cat['id']];
    } else {
        $tree[] = &$byId[(int)$cat['id']];
    }
}

// --- Gestion création / modification / suppression ---
$errors = [];
$catForm = ['id' => '', 'parent_id' => '', 'name' => '', 'slug' => '', 'description' => '', 'sort_order' => '0', 'is_active' => '1'];
$editing = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!csrfCheck($csrf)) {
        $errors[] = 'Jeton CSRF invalide.';
    } else {
        $action = $_POST['action'] ?? '';
        $catForm['name'] = trim($_POST['name'] ?? '');
        $catForm['slug'] = trim($_POST['slug'] ?? '');
        $catForm['parent_id'] = (trim($_POST['parent_id'] ?? '') === '' || (int)($_POST['parent_id'] ?? 0) <= 0)
            ? null
            : (int)$_POST['parent_id'];
        $catForm['description'] = trim($_POST['description'] ?? '');
        $catForm['sort_order'] = (int)($_POST['sort_order'] ?? 0);
        $catForm['is_active'] = isset($_POST['is_active']) ? '1' : '0';

        if ($action === 'create' || $action === 'update') {
            if ($catForm['name'] === '') { $errors[] = 'Nom obligatoire.'; }
            if ($catForm['slug'] === '') { $errors[] = 'Slug obligatoire.'; }
            // Éviter cycle : une catégorie ne peut pas être parente d'elle-même ni de ses ancêtres
            $editingId = (int)($_POST['id'] ?? 0);
            if ($editingId > 0 && $catForm['parent_id'] !== null && (int)$catForm['parent_id'] === $editingId) {
                $errors[] = 'Une catégorie ne peut pas être sa propre parente.';
            }

            if ($errors === []) {
                try {
                    if ($action === 'create') {
                        $db->prepare(
                            "INSERT INTO categories (parent_id, name, slug, description, sort_order, is_active, created_at, updated_at)
                             VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())"
                        )->execute([
                            $catForm['parent_id'], $catForm['name'], $catForm['slug'], $catForm['description'],
                            $catForm['sort_order'], $catForm['is_active']
                        ]);
                        $_SESSION['flash']['success'] = 'Catégorie créée.';
                    } else {
                        $catForm['id'] = (int)($_POST['id'] ?? 0);
                        $db->prepare(
                            "UPDATE categories SET parent_id=?, name=?, slug=?, description=?, sort_order=?, is_active=?, updated_at=NOW() WHERE id=?"
                        )->execute([
                            $catForm['parent_id'], $catForm['name'], $catForm['slug'], $catForm['description'],
                            $catForm['sort_order'], $catForm['is_active'], $catForm['id']
                        ]);
                        $_SESSION['flash']['success'] = 'Catégorie mise à jour.';
                    }
                    redirect('categories.php');
                } catch (Throwable $e) {
                    $errors[] = 'Erreur : ' . $e->getMessage();
                }
            }
        } elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            // Vérifier qu'il n'y a pas d'enfants
            $stmt = $db->prepare("SELECT COUNT(*) AS nb FROM categories WHERE parent_id = ?");
            $stmt->execute([$id]);
            if ((int)$stmt->fetch()['nb'] > 0) {
                $errors[] = 'Impossible de supprimer : cette catégorie a des sous-catégories.';
            } else {
                // Vérifier qu'il n'y a pas de produits
                $stmt = $db->prepare("SELECT COUNT(*) AS nb FROM products WHERE category_id = ?");
                $stmt->execute([$id]);
                if ((int)$stmt->fetch()['nb'] > 0) {
                    $errors[] = 'Impossible de supprimer : cette catégorie contient des produits.';
                } else {
                    try {
                        $db->prepare("DELETE FROM categories WHERE id = ?")->execute([$id]);
                        $_SESSION['flash']['success'] = 'Catégorie supprimée.';
                        redirect('categories.php');
                    } catch (Throwable $e) {
                        $errors[] = 'Erreur : ' . $e->getMessage();
                    }
                }
            }
        }
    }
}

// Pré-remplir pour édition
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM categories WHERE id = ? LIMIT 1");
    $stmt->execute([(int)$_GET['edit']]);
    $cat = $stmt->fetch();
    if ($cat !== false) {
        $editing = true;
        $catForm = [
            'id' => (int)$cat['id'],
            'parent_id' => $cat['parent_id'] !== null ? (int)$cat['parent_id'] : '',
            'name' => e($cat['name']),
            'slug' => e($cat['slug']),
            'description' => e($cat['description'] ?? ''),
            'sort_order' => (int)$cat['sort_order'],
            'is_active' => (int)$cat['is_active'],
        ];
    }
}

$pageTitle = 'Catégories — Administration';

require_once __DIR__ . '/includes/header.php';
?>

<div class="admin-content" style="padding:26px 22px;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
        <div class="section-title"><i class="fas fa-layer-group"></i> Catégories et sous-catégories</div>
        <button class="btn btn--primary" onclick="openCatModal(<?= $editing ? 'true' : 'false' ?>, <?= $editing ? (int)$_GET['edit'] : '0' ?>, '<?= e($catForm['name']) ?>', '<?= e($catForm['slug']) ?>', '<?= e($catForm['description']) ?>', <?= $catForm['parent_id'] !== '' ? (int)$catForm['parent_id'] : 'null' ?>, <?= (int)$catForm['sort_order'] ?>, <?= (int)$catForm['is_active'] ?>)">
            <i class="fas fa-plus"></i> <?= $editing ? 'Modifier' : 'Ajouter' ?> une catégorie
        </button>
    </div>

    <?php if (hasFlash('success')): ?>
        <div class="alert alert--success" style="margin-bottom:20px;"><?= e(flash('success')) ?></div>
    <?php endif; ?>
    <?php if ($errors): ?>
        <div class="alert alert--error" style="margin-bottom:20px;"><?= implode('<br>', array_map('e', $errors)) ?></div>
    <?php endif; ?>

    <table class="admin-table">
        <thead>
            <tr>
                <th style="width:60px;">ID</th>
                <th>Nom</th>
                <th style="width:140px;">Slug</th>
                <th style="width:140px;">Parent</th>
                <th style="width:100px; text-align:center;">Ordre</th>
                <th style="width:90px; text-align:center;">Actif</th>
                <th style="width:140px;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php
            function renderCatTree(array $nodes, int $level = 0): void {
                foreach ($nodes as $node) {
                    echo '<tr>';
                    echo '<td>' . (int)$node['id'] . '</td>';
                    echo '<td><span style="color:var(--color-gray-dark);">' . str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $level) . htmlspecialchars($node['name'], ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</span></td>';
                    echo '<td><span class="notranslate" translate="no">' . htmlspecialchars($node['slug'], ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</span></td>';
                    echo '<td>' . ($node['parent_id'] !== null && isset($node['_parent_name']) ? htmlspecialchars($node['_parent_name'], ENT_QUOTES | ENT_HTML5, 'UTF-8') : 'Racine') . '</td>';
                    echo '<td style="text-align:center;">' . (int)$node['sort_order'] . '</td>';
                    echo '<td style="text-align:center;"><span class="badge ' . ((int)$node['is_active'] === 1 ? 'badge--active' : 'badge--inactive') . '">' . ((int)$node['is_active'] === 1 ? 'Oui' : 'Non') . '</span></td>';
                    echo '<td>
                        <button class="btn btn--outline btn--sm" style="padding:4px 8px;" onclick="openCatModal(true, ' . (int)$node['id'] . ', \'' . addslashes(htmlspecialchars($node['name'], ENT_QUOTES | ENT_HTML5, 'UTF-8')) . '\', \'' . addslashes($node['slug']) . '\', \'' . addslashes($node['description'] ?? '') . '\', ' . ($node['parent_id'] !== null ? (int)$node['parent_id'] : 'null') . ', ' . (int)$node['sort_order'] . ', ' . (int)$node['is_active'] . ')">Modifier</button>
                        <button class="btn btn--outline btn--sm" style="padding:4px 8px; color:var(--color-error);" onclick="if(confirm(\'Supprimer cette catégorie ?\')){ deleteCat(' . (int)$node['id'] . ') }">Supprimer</button>
                    </td>';
                    echo '</tr>';
                    if (!empty($node['children'])) {
                        renderCatTree($node['children'], $level + 1);
                    }
                }
            }
            // Ajouter le nom parent pour l'affichage
            foreach ($allCats as &$c) {
                if ($c['parent_id'] !== null && isset($byId[(int)$c['parent_id']])) {
                    $c['_parent_name'] = $byId[(int)$c['parent_id']]['name'];
                }
            }
            unset($c);
            renderCatTree($tree);
            ?>
        </tbody>
    </table>
</div>

<!-- Modal catégorie -->
<div id="catModal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); z-index:2000; align-items:center; justify-content:center;">
    <div style="background:#fff; border-radius:var(--radius); width:100%; max-width:520px; max-height:90vh; overflow-y:auto; padding:24px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <h4 id="catModalTitle" style="font-size:16px; font-weight:800; color:var(--color-dark); margin:0;">Nouvelle catégorie</h4>
            <button onclick="closeCatModal()" style="background:none; border:none; font-size:20px; color:var(--color-gray);">×</button>
        </div>
        <form id="catForm" method="POST" action="categories.php" novalidate>
            <?= csrfField() ?>
            <input type="hidden" name="action" id="catAction" value="create">
            <input type="hidden" name="id" id="catId" value="">

            <div class="form-group">
                <label>Nom <span style="color:var(--color-error);">*</span></label>
                <input type="text" name="name" id="catName" required>
            </div>
            <div class="form-group">
                <label>Slug <span style="color:var(--color-error);">*</span></label>
                <input type="text" name="slug" id="catSlug" required>
                <div class="form-help">Exemple: vetement-homme</div>
            </div>
            <div class="form-group">
                <label>Catégorie parente</label>
                <select name="parent_id" id="catParent">
                    <option value="">Racine (catégorie principale)</option>
                    <?php
                    function renderParentOptions(array $nodes, int $level = 0, int $excludeId = 0): void {
                        foreach ($nodes as $node) {
                            if ((int)$node['id'] === $excludeId) continue;
                            echo '<option value="' . (int)$node['id'] . '">' . str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $level) . htmlspecialchars($node['name'], ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</option>';
                            if (!empty($node['children'])) {
                                renderParentOptions($node['children'], $level + 1, $excludeId);
                            }
                        }
                    }
                    renderParentOptions($tree, 0, $editing ? (int)$_GET['edit'] : 0);
                    ?>
                </select>
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" id="catDescription" rows="3"></textarea>
            </div>
            <div class="form-group">
                <label>Ordre d'affichage</label>
                <input type="number" name="sort_order" id="catSortOrder" min="0" value="0">
            </div>
            <div class="form-group" style="display:flex; align-items:center; gap:8px;">
                <input type="checkbox" name="is_active" id="catIsActive" value="1" checked>
                <label for="catIsActive" style="margin:0; cursor:pointer;">Actif</label>
            </div>

            <div style="display:flex; gap:10px; margin-top:20px;">
                <button type="submit" class="btn btn--primary">Enregistrer</button>
                <button type="button" class="btn btn--outline" onclick="closeCatModal()">Annuler</button>
            </div>
        </form>
    </div>
</div>

<script>
function openCatModal(editing, id, name, slug, description, parentId, sortOrder, isActive) {
    document.getElementById('catAction').value = editing ? 'update' : 'create';
    document.getElementById('catId').value = id;
    document.getElementById('catModalTitle').textContent = editing ? 'Modifier la catégorie' : 'Nouvelle catégorie';
    document.getElementById('catName').value = name;
    document.getElementById('catSlug').value = slug;
    document.getElementById('catDescription').value = description;
    document.getElementById('catParent').value = parentId === null ? '' : parentId;
    document.getElementById('catSortOrder').value = sortOrder;
    document.getElementById('catIsActive').checked = isActive === 1;
    document.getElementById('catModal').style.display = 'flex';
}
function closeCatModal() {
    document.getElementById('catModal').style.display = 'none';
}
function deleteCat(id) {
    if (!confirm('Supprimer cette catégorie ?')) return;
    var form = document.createElement('form');
    form.method = 'POST';
    form.action = 'categories.php';
    form.innerHTML = '<input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="' + id + '">';
    document.body.appendChild(form);
    form.submit();
}
window.addEventListener('click', function(e) {
    var m = document.getElementById('catModal');
    if (e.target === m) closeCatModal();
});
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
