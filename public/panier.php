<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/header.php';

$db = db();
$currency = detectCurrency($db);
$settings = loadSettings($db);
$countries = getCountries($db);

// --- Panier en session ---
$cart = $_SESSION['cart'] ?? [];
$cartItems = [];
$subtotal = 0.0;
$totalWeight = 0.0;

foreach ($cart as $reference => $item) {
    $stmt = $db->prepare("SELECT id, reference, name, price, weight, stock FROM products WHERE reference = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$reference]);
    $product = $stmt->fetch();

    if ($product === false) {
        continue; // article retiré du catalogue
    }

    $qty = (int)$item['qty'];
    $unitPrice = (float)$product['price'];
    $lineTotal = $unitPrice * $qty;
    $subtotal += $lineTotal;
    $totalWeight += (float)$product['weight'] * $qty;

    $cartItems[] = [
        'reference' => $product['reference'],
        'name' => $product['name'],
        'price' => $unitPrice,
        'qty' => $qty,
        'line_total' => $lineTotal,
        'image' => imageUrl(productImagePath($db, (int)$product['id'], $product['reference'])),
    ];
}

// --- Pays de livraison choisi ---
$deliveryCountryIso = $_COOKIE['delivery_country'] ?? 'FR';
$shippingRate = getShippingRateForCountry($db, $deliveryCountryIso);
$shippingAmount = $shippingRate !== null ? (float)$shippingRate['price'] : 0.0;
$shippingCurrency = $shippingRate !== null ? ($shippingRate['currency_code'] ?? 'EUR') : 'EUR';

// Convertir frais de livraison dans la devise du visiteur si nécessaire
$shippingConverted = $shippingAmount;
if ($shippingCurrency !== $currency['code']) {
    $shippingConverted = convertAmount($shippingAmount, ['code' => $shippingCurrency, 'exchange_rate' => 1.000000], $currency);
}

// --- TVA ---
$vatRate = vatEnabled($db) ? getVatRate($db, $deliveryCountryIso) : 0.0;
$vatAmount = 0.0;
$vatLabel = 'TVA non applicable';

if (vatEnabled($db)) {
    if ($deliveryCountryIso !== null) {
        $country = getCountry($db, $deliveryCountryIso);
        if ($country && (int)$country['is_eu'] === 1) {
            $vatAmount = round($subtotal * ($vatRate / 100), 2);
            $vatLabel = 'TVA ' . number_format((float)$vatRate, 2, ',', ' ') . '%';
        } else {
            $vatLabel = 'Exportation hors UE — TVA non applicable';
        }
    }
}

// Total en devise de base puis conversion
$totalBase = $subtotal + $shippingAmount + $vatAmount;
$totalConverted = convertAmount($totalBase, ['code' => 'EUR', 'exchange_rate' => 1.000000], $currency);

// Sous-total et TVA convertis séparément pour l'affichage
$subtotalConverted = convertAmount($subtotal, ['code' => 'EUR', 'exchange_rate' => 1.000000], $currency);
$vatConverted = convertAmount($vatAmount, ['code' => 'EUR', 'exchange_rate' => 1.000000], $currency);

$pageTitle = 'Mon panier — ' . e($siteName);
$pageDescription = 'Récapitulatif de votre commande avant validation.';

require_once INCLUDES_DIR . '/header.php';
?>

<section class="section">
    <div class="container">
        <nav class="breadcrumbs" style="margin-bottom:16px; font-size:12px; color:var(--color-gray-light);">
            <a href="index.php">Accueil</a> <span style="color:var(--color-gray); margin:0 6px;">›</span>
            <strong>Mon panier</strong>
        </nav>

        <div class="section__title" style="display:flex; align-items:center; gap:8px;">
            <i class="fas fa-shopping-cart"></i> Mon panier
        </div>

        <?php if ($cartItems === []): ?>
            <div class="empty-state">
                <i class="fas fa-shopping-cart"></i>
                <p>Votre panier est vide.</p>
                <a href="catalogue.php" class="btn btn--primary">Découvrir le catalogue</a>
            </div>
        <?php else: ?>
            <div class="cart-layout" style="display:grid; grid-template-columns: 1fr 340px; gap:30px; align-items:start;">
                <!-- Articles -->
                <div class="cart-items">
                    <table class="cart-table" style="width:100%; border-collapse:collapse; margin-bottom:20px;">
                        <thead>
                            <tr style="border-bottom:2px solid var(--color-border);">
                                <th style="text-align:left; padding:10px; font-size:13px; color:var(--color-gray);">Article</th>
                                <th style="text-align:right; padding:10px; font-size:13px; color:var(--color-gray);">Prix unitaire</th>
                                <th style="text-align:center; padding:10px; font-size:13px; color:var(--color-gray);">Qty</th>
                                <th style="text-align:right; padding:10px; font-size:13px; color:var(--color-gray);">Total</th>
                                <th style="padding:10px; font-size:13px; color:var(--color-gray);"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cartItems as $item): ?>
                                <tr style="border-bottom:1px solid var(--color-border);">
                                    <td style="padding:12px 10px; vertical-align:middle;">
                                        <div style="display:flex; gap:10px; align-items:center;">
                                            <img src="<?= e($item['image']) ?>" alt="" style="width:56px; height:56px; object-fit:cover; border-radius:var(--radius-sm);">
                                            <div>
                                                <div style="font-weight:600; color:var(--color-dark);"><?= e($item['name']) ?></div>
                                                <div style="font-size:12px; color:var(--color-gray);"><span class="notranslate" translate="no"><?= e($item['reference']) ?></span></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td style="padding:12px 10px; vertical-align:middle; text-align:right;">
                                        <span class="notranslate" translate="no"><?= formatPrice(convertAmount((float)$item['price'], ['code' => 'EUR', 'exchange_rate' => 1.000000], $currency), $currency) ?></span>
                                    </td>
                                    <td style="padding:12px 10px; vertical-align:middle; text-align:center;">
                                        <form action="api/panier.php" method="POST" style="display:flex; gap:6px; align-items:center; justify-content:center;" onsubmit="return updateCartQty(this, '<?= e($item['reference']) ?>', event);">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="update">
                                            <input type="hidden" name="reference" value="<?= e($item['reference']) ?>">
                                            <input type="number" name="qty" min="0" max="99" value="<?= (int)$item['qty'] ?>" style="width:50px; padding:4px 8px; border:1px solid var(--color-border); border-radius:var(--radius-sm); text-align:center;">
                                            <button type="submit" class="btn btn--outline btn--sm" style="padding:4px 10px;">OK</button>
                                        </form>
                                    </td>
                                    <td style="padding:12px 10px; vertical-align:middle; text-align:right; font-weight:700;">
                                        <span class="notranslate" translate="no"><?= formatPrice(convertAmount((float)$item['line_total'], ['code' => 'EUR', 'exchange_rate' => 1.000000], $currency), $currency) ?></span>
                                    </td>
                                    <td style="padding:12px 10px; vertical-align:middle; text-align:right;">
                                        <form action="api/panier.php" method="POST" style="display:inline;" onsubmit="return removeCartItem(this, '<?= e($item['reference']) ?>', event);">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="remove">
                                            <input type="hidden" name="reference" value="<?= e($item['reference']) ?>">
                                            <button type="submit" class="btn btn--outline btn--sm" style="color:var(--color-error);">Supprimer</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Résumé -->
                <div class="cart-summary" style="background:var(--color-light); border:1px solid var(--color-border); border-radius:var(--radius); padding:20px;">
                    <h3 style="font-size:16px; font-weight:800; color:var(--color-dark); margin-bottom:16px;">Récapitulatif</h3>

                    <div style="display:flex; justify-content:space-between; margin-bottom:8px; font-size:14px; color:var(--color-gray-dark);">
                        <span>Sous-total (<?= (int)array_sum(array_column($cartItems, 'qty')) ?> article<?= (int)array_sum(array_column($cartItems, 'qty')) > 1 ? 's' : '' ?>)</span>
                        <span class="notranslate" translate="no"><?= formatPrice($subtotalConverted, $currency) ?></span>
                    </div>

                    <div style="display:flex; justify-content:space-between; margin-bottom:8px; font-size:14px; color:var(--color-gray-dark);">
                        <span>Frais de livraison (<span class="notranslate" translate="no"><?= e($shippingRate !== null ? $shippingRate['zone_name'] : '—') ?></span>)</span>
                        <span class="notranslate" translate="no"><?= formatPrice($shippingConverted, $currency) ?></span>
                    </div>

                    <div style="display:flex; justify-content:space-between; margin-bottom:8px; font-size:14px; color:var(--color-gray-dark);">
                        <span><?= e($vatLabel) ?></span>
                        <span class="notranslate" translate="no"><?= formatPrice($vatConverted, $currency) ?></span>
                    </div>

                    <div style="border-top:2px solid var(--color-border); padding-top:14px; margin-top:14px; display:flex; justify-content:space-between; font-size:18px; font-weight:800; color:var(--color-dark);">
                        <span>Total TTC</span>
                        <span class="notranslate" translate="no"><?= formatPrice($totalConverted, $currency) ?></span>
                    </div>

                    <p style="font-size:12px; color:var(--color-gray); margin-top:16px; line-height:1.5;">
                        Les droits de douane, taxes d importation et TVA locale éventuels sont à la charge de l acheteur hors Union européenne.
                    </p>

                    <a href="commande.php" class="btn btn--primary btn--full" style="margin-top:16px;">Passer la commande</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php
require_once INCLUDES_DIR . '/footer.php';
?>

<script>
function updateCartQty(form, reference, event) {
  event.preventDefault();
  var btn = form.querySelector('button[type="submit"]');
  var original = btn.innerHTML;
  btn.innerHTML = '…';
  btn.disabled = true;

  var formData = new FormData(form);
  // Ne pas utiliser form.action : le champ caché name="action" l'écrase.
  var actionUrl = new URL(form.getAttribute('action'), document.baseURI).href;
  fetch(actionUrl, { method: 'POST', body: formData, credentials: 'same-origin' })
    .then(function(r){ return r.json(); })
    .then(function(data){
      if (data.ok) {
        location.reload();
      } else {
        alert(data.message || 'Erreur.');
        btn.innerHTML = original;
        btn.disabled = false;
      }
    })
    .catch(function(err){
      alert((err && err.message) ? err.message : 'Erreur de connexion.');
      btn.innerHTML = original;
      btn.disabled = false;
    });
  return false;
}

function removeCartItem(form, reference, event) {
  if (!confirm('Retirer cet article du panier ?')) { event.preventDefault(); return false; }
  var btn = form.querySelector('button[type="submit"]');
  var original = btn.innerHTML;
  btn.innerHTML = '…';
  btn.disabled = true;

  var formData = new FormData(form);
  // Ne pas utiliser form.action : le champ caché name="action" l'écrase.
  var actionUrl = new URL(form.getAttribute('action'), document.baseURI).href;
  fetch(actionUrl, { method: 'POST', body: formData, credentials: 'same-origin' })
    .then(function(r){ return r.json(); })
    .then(function(data){
      if (data.ok) {
        location.reload();
      } else {
        alert(data.message || 'Erreur.');
        btn.innerHTML = original;
        btn.disabled = false;
      }
    })
    .catch(function(err){
      alert((err && err.message) ? err.message : 'Erreur de connexion.');
      btn.innerHTML = original;
      btn.disabled = false;
    });
  return false;
}
</script>
