<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/page_bootstrap.php';

$db = db();
$settings = loadSettings($db);

$articleId = $_GET['id'] ?? null;
$article = null;
$images = [];
$isNew = $articleId === null;

if (!$isNew) {
    $stmt = $db->prepare("SELECT * FROM products WHERE id = ? LIMIT 1");
    $stmt->execute([(int)$articleId]);
    $article = $stmt->fetch();
    if ($article === false) {
        $_SESSION['flash']['error'] = 'Article introuvable.';
        redirect('articles.php');
    }

    $stmt = $db->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY sort_order ASC, id ASC");
    $stmt->execute([(int)$articleId]);
    $images = $stmt->fetchAll();
}

$categories = $db->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order ASC, name ASC")->fetchAll();

// --- Valeurs par défaut / POST ---
$form = [
    'category_id' => $article ? (int)$article['category_id'] : '',
    'reference' => $article ? e($article['reference']) : '',
    'name' => $article ? e($article['name']) : '',
    'short_description' => $article ? e($article['short_description'] ?? '') : '',
    'description' => $article ? e($article['description'] ?? '') : '',
    'price' => $article ? number_format((float)$article['price'], 2, '.', '') : '',
    'weight' => $article ? number_format((float)$article['weight'], 3, '.', '') : '',
    'stock' => $article ? (int)$article['stock'] : '',
    'min_stock' => $article ? ($article['min_stock'] !== null ? (int)$article['min_stock'] : '') : '',
    'rating' => $article ? (int)$article['rating'] : 0,
    'sku' => $article ? e($article['sku'] ?? '') : '',
    'is_active' => $article ? (int)$article['is_active'] : 1,
    'is_featured' => $article ? (int)$article['is_featured'] : 0,
    'is_bestseller' => $article ? (int)$article['is_bestseller'] : 0,
    'is_on_sale' => $article ? (int)$article['is_on_sale'] : 0,
    'sale_price' => $article ? ($article['sale_price'] !== null ? number_format((float)$article['sale_price'], 2, '.', '') : '') : '',
];

$errors = [];
$message = '';

// --- Gestion upload des images produits ---
function handleProductImages(PDO $database, int $productId, string $altText = ''): array
{
    $errors = [];
    $uploaded = 0;

    if (!isset($_FILES['new_image']) || !is_array($_FILES['new_image']['name'])) {
        return ['errors' => $errors, 'count' => 0];
    }

    $maxSize = 8 * 1024 * 1024;
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];

    // Chemin corrigé : IMAGES_DIR = <racine du site>/uploads/images
    $destDir = IMAGES_DIR . '/products';
    if (!is_dir($destDir) && !@mkdir($destDir, 0755, true)) {
        return ['errors' => ['Impossible de créer le dossier des images.'], 'count' => 0];
    }

    $stmt = $database->prepare("SELECT COUNT(*) AS nb FROM product_images WHERE product_id = ?");
    $stmt->execute([$productId]);
    $count = (int)$stmt->fetch()['nb'];
    $isPrimary = $count === 0 ? 1 : 0;

    foreach ($_FILES['new_image']['name'] as $idx => $name) {
        $errorCode = $_FILES['new_image']['error'][$idx] ?? UPLOAD_ERR_NO_FILE;
        if ($errorCode === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if ($errorCode !== UPLOAD_ERR_OK) {
            $errors[] = 'Image « ' . $name . ' » : erreur d envoi (' . $errorCode . ').';
            continue;
        }

        $tmp = $_FILES['new_image']['tmp_name'][$idx];
        $size = (int)($_FILES['new_image']['size'][$idx] ?? 0);
        if ($size > $maxSize) {
            $errors[] = 'Image « ' . $name . ' » : taille supérieure à 8 Mo.';
            continue;
        }

        $mime = $finfo->file($tmp);
        if (!isset($allowed[$mime])) {
            $errors[] = 'Image « ' . $name . ' » : format non autorisé (JPEG, PNG, WebP, GIF).';
            continue;
        }

        $newName = 'product_' . $productId . '_' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
        $destPath = $destDir . DIRECTORY_SEPARATOR . $newName;
        if (!is_uploaded_file($tmp) || !move_uploaded_file($tmp, $destPath)) {
            $errors[] = 'Image « ' . $name . ' » : enregistrement impossible.';
            continue;
        }

        $database->prepare(
            "INSERT INTO product_images (product_id, image, is_primary, sort_order, alt_text, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())"
        )->execute([
            $productId,
            'uploads/images/products/' . $newName,
            $isPrimary,
            $count + 1,
            $altText
        ]);
        $count++;
        $uploaded++;
        if ($isPrimary === 1) {
            $isPrimary = 0; // seule la première uploadée devient principale
        }
    }

    return ['errors' => $errors, 'count' => $uploaded];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!csrfCheck($csrf)) {
        $errors[] = 'Jeton CSRF invalide.';
    } else {
        $form['category_id'] = (int)($_POST['category_id'] ?? 0);
        $form['reference'] = trim($_POST['reference'] ?? '');
        $form['name'] = trim($_POST['name'] ?? '');
        $form['short_description'] = trim($_POST['short_description'] ?? '');
        $form['description'] = trim($_POST['description'] ?? '');
        $form['price'] = trim($_POST['price'] ?? '');
        $form['weight'] = trim($_POST['weight'] ?? '');
        $form['stock'] = (int)($_POST['stock'] ?? 0);
        $form['min_stock'] = trim($_POST['min_stock'] ?? '') !== '' ? (int)($_POST['min_stock'] ?? 0) : null;
        $form['rating'] = (int)($_POST['rating'] ?? 0);
        $form['sku'] = trim($_POST['sku'] ?? '');
        $form['is_active'] = isset($_POST['is_active']) ? 1 : 0;
        $form['is_featured'] = isset($_POST['is_featured']) ? 1 : 0;
        $form['is_bestseller'] = isset($_POST['is_bestseller']) ? 1 : 0;
        $form['is_on_sale'] = isset($_POST['is_on_sale']) ? 1 : 0;
        $form['sale_price'] = trim($_POST['sale_price'] ?? '');

        if ($form['category_id'] <= 0) { $errors[] = 'Catégorie obligatoire.'; }
        if ($form['reference'] === '') { $errors[] = 'Référence obligatoire.'; }
        if ($form['name'] === '') { $errors[] = 'Nom obligatoire.'; }
        if (!is_numeric($form['price']) || (float)$form['price'] < 0) { $errors[] = 'Prix invalide.'; }
        if (!is_numeric($form['weight']) || (float)$form['weight'] < 0) { $errors[] = 'Poids invalide.'; }
        if ($form['rating'] < 0 || $form['rating'] > 5) { $errors[] = 'Note invalide (1-5).'; }

        // Vérifier unicité référence (sauf soi-même)
        if ($errors === []) {
            $stmt = $db->prepare("SELECT id FROM products WHERE reference = ? AND id <> ? LIMIT 1");
            $stmt->execute([$form['reference'], $isNew ? 0 : (int)$articleId]);
            if ($stmt->fetch() !== false) {
                $errors[] = 'Cette référence existe déjà.';
            }
        }

        if ($errors === []) {
            try {
                if ($isNew) {
                    $db->prepare(
                        "INSERT INTO products (category_id, reference, name, short_description, description, price, weight, stock, min_stock, rating, sku, is_active, is_featured, is_bestseller, is_on_sale, sale_price, created_at, updated_at)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())"
                    )->execute([
                        $form['category_id'], $form['reference'], $form['name'], $form['short_description'],
                        $form['description'], (float)$form['price'], (float)$form['weight'], $form['stock'],
                        $form['min_stock'], $form['rating'], $form['sku'] ?: null, $form['is_active'],
                        $form['is_featured'], $form['is_bestseller'], $form['is_on_sale'],
                        $form['is_on_sale'] && $form['sale_price'] !== '' ? (float)$form['sale_price'] : null
                    ]);
                    $newId = (int)$db->lastInsertId();
                    $upload = handleProductImages($db, $newId, $form['name']);
                    if ($upload['errors'] !== []) {
                        $_SESSION['flash']['error'] = implode(' | ', $upload['errors']);
                    }
                    $_SESSION['flash']['success'] = 'Article créé avec succès.'
                        . ($upload['count'] > 0 ? ' ' . $upload['count'] . ' photo(s) ajoutée(s).' : '');
                    redirect('article-edit.php?id=' . $newId);
                } else {
                    $db->prepare(
                        "UPDATE products SET category_id=?, reference=?, name=?, short_description=?, description=?, price=?, weight=?, stock=?, min_stock=?, rating=?, sku=?, is_active=?, is_featured=?, is_bestseller=?, is_on_sale=?, sale_price=?, updated_at=NOW() WHERE id=?"
                    )->execute([
                        $form['category_id'], $form['reference'], $form['name'], $form['short_description'],
                        $form['description'], (float)$form['price'], (float)$form['weight'], $form['stock'],
                        $form['min_stock'], $form['rating'], $form['sku'] ?: null, $form['is_active'],
                        $form['is_featured'], $form['is_bestseller'], $form['is_on_sale'],
                        $form['is_on_sale'] && $form['sale_price'] !== '' ? (float)$form['sale_price'] : null,
                        (int)$articleId
                    ]);
                    $upload = handleProductImages($db, (int)$articleId, $form['name']);
                    if ($upload['errors'] !== []) {
                        $_SESSION['flash']['error'] = implode(' | ', $upload['errors']);
                    }
                    $_SESSION['flash']['success'] = 'Article mis à jour.'
                        . ($upload['count'] > 0 ? ' ' . $upload['count'] . ' photo(s) ajoutée(s).' : '');
                    redirect('article-edit.php?id=' . (int)$articleId);
                }
            } catch (Throwable $e) {
                $errors[] = 'Erreur : ' . $e->getMessage();
            }
        }
    }
}

$pageTitle = ($isNew ? 'Nouvel article' : 'Modifier l article') . ' — Administration';

require_once __DIR__ . '/includes/header.php';
?>
<div class="admin-content" style="padding:26px 22px;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
        <div class="section-title"><?= $isNew ? '<i class="fas fa-plus"></i> Nouvel article' : '<i class="fas fa-pen"></i> Modifier l article' ?></div>
        <a href="articles.php" class="btn btn--outline"><i class="fas fa-list"></i> Retour à la liste</a>
    </div>

    <?php if ($errors): ?>
        <div class="alert alert--error" style="margin-bottom:20px;">
            <?= implode('<br>', array_map('e', $errors)) ?>
        </div>
    <?php endif; ?>
    <?php if ($message !== ''): ?>
        <div class="alert alert--success" style="margin-bottom:20px;"><?= e($message) ?></div>
    <?php endif; ?>
    <?php if (hasFlash('success')): ?>
        <div class="alert alert--success" style="margin-bottom:20px;"><?= e(flash('success')) ?></div>
    <?php endif; ?>
    <?php if (hasFlash('error')): ?>
        <div class="alert alert--error" style="margin-bottom:20px;"><?= e(flash('error')) ?></div>
    <?php endif; ?>

    <form action="article-edit.php<?= $isNew ? '' : '?id=' . (int)$articleId ?>" method="POST" enctype="multipart/form-data" novalidate>
        <?= csrfField() ?>

        <div class="form-grid">
            <div class="form-group" style="grid-column: 1 / -1;">
                <label>Référence <span style="color:var(--color-error);">*</span></label>
                <input type="text" name="reference" value="<?= e($form['reference']) ?>" required>
            </div>

            <div class="form-group" style="grid-column: 1 / -1;">
                <label>Nom <span style="color:var(--color-error);">*</span></label>
                <input type="text" name="name" value="<?= e($form['name']) ?>" required>
            </div>

            <div class="form-group">
                <label>Catégorie <span style="color:var(--color-error);">*</span></label>
                <select name="category_id" required>
                    <option value="">Sélectionner</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= (int)$cat['id'] ?>" <?= $form['category_id'] == $cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Prix (EUR) <span style="color:var(--color-error);">*</span></label>
                <input type="number" name="price" step="0.01" min="0" value="<?= e($form['price']) ?>" required>
            </div>

            <div class="form-group">
                <label>Prix promo (EUR)</label>
                <input type="number" name="sale_price" step="0.01" min="0" value="<?= e($form['sale_price']) ?>">
                <div class="form-help">Laissez vide si pas de promotion. Ne s'applique que si "En promotion" est coché.</div>
            </div>

            <div class="form-group">
                <label>Poids (kg)</label>
                <input type="number" name="weight" step="0.001" min="0" value="<?= e($form['weight']) ?>">
                <div class="form-help">Utilisé pour le calcul des frais de livraison.</div>
            </div>

            <div class="form-group">
                <label>Stock</label>
                <input type="number" name="stock" min="0" value="<?= e($form['stock']) ?>">
            </div>

            <div class="form-group">
                <label>Stock minimum (alerte)</label>
                <input type="number" name="min_stock" min="0" value="<?= $form['min_stock'] !== null ? e($form['min_stock']) : '' ?>">
            </div>

            <div class="form-group">
                <label>SKU (référence interne)</label>
                <input type="text" name="sku" value="<?= e($form['sku']) ?>">
            </div>

            <div class="form-group" style="grid-column: 1 / -1;">
                <label>Description courte</label>
                <textarea name="short_description" rows="2"><?= e($form['short_description']) ?></textarea>
            </div>

            <div class="form-group" style="grid-column: 1 / -1;">
                <label>Description complète</label>
                <textarea name="description" rows="6"><?= e($form['description']) ?></textarea>
            </div>

            <div class="form-group">
                <label>Note (étoiles, 1-5)</label>
                <select name="rating">
                    <option value="0" <?= $form['rating'] === 0 ? 'selected' : '' ?>>0 (aucune)</option>
                    <option value="1" <?= $form['rating'] === 1 ? 'selected' : '' ?>>1 étoile</option>
                    <option value="2" <?= $form['rating'] === 2 ? 'selected' : '' ?>>2 étoiles</option>
                    <option value="3" <?= $form['rating'] === 3 ? 'selected' : '' ?>>3 étoiles</option>
                    <option value="4" <?= $form['rating'] === 4 ? 'selected' : '' ?>>4 étoiles</option>
                    <option value="5" <?= $form['rating'] === 5 ? 'selected' : '' ?>>5 étoiles</option>
                </select>
                <div class="form-help">Note attribuée par l admin, affichée sur la fiche produit.</div>
            </div>

            <div style="grid-column: 1 / -1; display:flex; gap:10px; align-items:center; margin-top:10px;">
                <label style="display:flex; align-items:center; gap:6px; cursor:pointer;">
                    <input type="checkbox" name="is_active" <?= $form['is_active'] === 1 ? 'checked' : '' ?>>
                    Actif (visible sur le site)
                </label>
                <label style="display:flex; align-items:center; gap:6px; cursor:pointer;">
                    <input type="checkbox" name="is_featured" <?= $form['is_featured'] === 1 ? 'checked' : '' ?>>
                    Nouveauté / en vedette
                </label>
                <label style="display:flex; align-items:center; gap:6px; cursor:pointer;">
                    <input type="checkbox" name="is_bestseller" <?= $form['is_bestseller'] === 1 ? 'checked' : '' ?>>
                    Meilleure vente
                </label>
                <label style="display:flex; align-items:center; gap:6px; cursor:pointer;">
                    <input type="checkbox" name="is_on_sale" <?= $form['is_on_sale'] === 1 ? 'checked' : '' ?>>
                    En promotion
                </label>
            </div>
        </div>

        <!-- Photos -->
        <div style="grid-column: 1 / -1; margin-top:24px;">
            <h4 style="font-size:15px; font-weight:800; color:var(--color-dark); margin-bottom:12px; border-bottom:1px solid var(--color-border); padding-bottom:8px;">Photos de l'article</h4>

            <div class="form-group">
                <label>Ajouter une photo</label>
                <input type="file" name="new_image[]" accept="image/*" multiple>
                <div class="form-help">JPEG, PNG, WebP. Taille max 8 Mo. La première image devient l'image principale.</div>
            </div>

            <?php if ($images): ?>
                <div style="display:flex; gap:12px; flex-wrap:wrap;">
                    <?php foreach ($images as $img): ?>
                        <div style="width:140px; border:1px solid var(--color-border); border-radius:var(--radius-sm); padding:8px; text-align:center; position:relative;">
                            <img src="<?= e(imageUrl($img['image'], $article['reference'])) ?>" alt="" style="width:100%; height:100px; object-fit:cover; border-radius:4px;">
                            <div style="position:absolute; top:4px; right:4px; background:rgba(0,0,0,0.7); color:#fff; font-size:10px; padding:2px 6px; border-radius:4px;">
                                <?= $img['is_primary'] === 1 ? 'Principale' : 'Secondaire' ?>
                            </div>
                            <div style="margin-top:6px; font-size:12px;">
                                <a href="../api/admin/upload.php?action=delete_image&id=<?= (int)$img['id'] ?>&csrf_token=<?= e(csrfToken()) ?>" class="btn btn--outline btn--sm" style="color:var(--color-error); font-size:11px; padding:2px 6px;" onclick="return confirm('Supprimer cette photo ?')">Supprimer</a>
                                <?php if ($img['is_primary'] !== 1): ?>
                                    <a href="../api/admin/upload.php?action=set_primary&id=<?= (int)$img['id'] ?>&product_id=<?= ($isNew ? 0 : (int)$articleId) ?>&csrf_token=<?= e(csrfToken()) ?>" class="btn btn--outline btn--sm" style="font-size:11px; padding:2px 6px;">Définir principale</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php elseif (!$isNew): ?>
                <p style="color:var(--color-gray);">Aucune photo pour ce produit.</p>
            <?php endif; ?>
        </div>

        <div style="margin-top:26px; display:flex; gap:10px;">
            <button type="submit" class="btn btn--primary"><i class="fas fa-save"></i> Enregistrer</button>
            <a href="articles.php" class="btn btn--outline">Annuler</a>
        </div>
    </form>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
