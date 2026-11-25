<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/page_bootstrap.php';

$db = db();
$currency = detectCurrency($db);
$settings = loadSettings($db);
$countries = getCountries($db);

// --- Panier ---
$cart = $_SESSION['cart'] ?? [];
if ($cart === []) {
    $_SESSION['flash']['info'] = 'Votre panier est vide.';
    redirect('panier.php');
}

$cartItems = [];
$subtotal = 0.0;
$totalWeight = 0.0;

foreach ($cart as $reference => $item) {
    $stmt = $db->prepare("SELECT id, reference, name, price, weight, stock FROM products WHERE reference = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$reference]);
    $product = $stmt->fetch();
    if ($product === false) {
        continue;
    }
    $qty = (int)$item['qty'];
    $unitPrice = (float)$product['price'];
    $subtotal += $unitPrice * $qty;
    $totalWeight += (float)$product['weight'] * $qty;
    $cartItems[] = [
        'reference' => $product['reference'],
        'name' => $product['name'],
        'price' => $unitPrice,
        'qty' => $qty,
        'line_total' => $unitPrice * $qty,
        'image' => imageUrl(productImagePath($db, (int)$product['id'], $product['reference'])),
    ];
}

// --- Pays et livraison ---
$deliveryCountryIso = $_COOKIE['delivery_country'] ?? 'FR';
$shippingRate = getShippingRateForCountry($db, $deliveryCountryIso);
$shippingAmount = $shippingRate !== null ? (float)$shippingRate['price'] : 0.0;
$shippingCurrency = $shippingRate !== null ? ($shippingRate['currency_code'] ?? 'EUR') : 'EUR';
$shippingConverted = $shippingAmount;
if ($shippingCurrency !== $currency['code']) {
    $shippingConverted = convertAmount($shippingAmount, ['code' => $shippingCurrency, 'exchange_rate' => 1.000000], $currency);
}

// --- TVA ---
$vatRate = vatEnabled($db) ? getVatRate($db, $deliveryCountryIso) : 0.0;
$vatAmount = 0.0;
$vatLabel = 'TVA non applicable';
$isEu = false;
$countryObj = null;
if (vatEnabled($db) && $deliveryCountryIso !== null) {
    $countryObj = getCountry($db, $deliveryCountryIso);
    if ($countryObj) {
        $isEu = (int)$countryObj['is_eu'] === 1;
        if ($isEu) {
            $vatAmount = round($subtotal * ($vatRate / 100), 2);
            $vatLabel = 'TVA ' . number_format((float)$vatRate, 2, ',', ' ') . '%';
        } else {
            $vatLabel = 'Exportation hors UE — TVA non applicable';
        }
    }
}

$totalBase = $subtotal + $shippingAmount + $vatAmount;
$totalConverted = convertAmount($totalBase, ['code' => 'EUR', 'exchange_rate' => 1.000000], $currency);
$subtotalConverted = convertAmount($subtotal, ['code' => 'EUR', 'exchange_rate' => 1.000000], $currency);
$vatConverted = convertAmount($vatAmount, ['code' => 'EUR', 'exchange_rate' => 1.000000], $currency);

// --- Client connecté ? ---
$customer = null;
$isLoggedIn = !empty($_SESSION['customer_id']);
if ($isLoggedIn) {
    $stmt = $db->prepare("SELECT * FROM customers WHERE id = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([(int)$_SESSION['customer_id']]);
    $customer = $stmt->fetch();
}

// --- Valeurs par défaut du formulaire ---
$form = [
    'firstname' => $customer ? e($customer['firstname']) : '',
    'lastname' => $customer ? e($customer['lastname']) : '',
    'email' => $customer ? e($customer['email']) : '',
    'phone' => $customer ? e($customer['phone']) : '',
    'company' => $customer ? e($customer['company']) : '',
    'vat_number' => $customer ? e($customer['vat_number']) : '',
    'address' => $customer ? e($customer['address']) : '',
    'city' => $customer ? e($customer['city']) : '',
    'postcode' => $customer ? e($customer['postcode']) : '',
    'country' => $deliveryCountryIso,
    'note' => '',
];

// Reprendre les valeurs POST en cas d'erreur de validation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form['firstname'] = e(trim($_POST['firstname'] ?? ''));
    $form['lastname'] = e(trim($_POST['lastname'] ?? ''));
    $form['email'] = e(trim($_POST['email'] ?? ''));
    $form['phone'] = e(trim($_POST['phone'] ?? ''));
    $form['company'] = e(trim($_POST['company'] ?? ''));
    $form['vat_number'] = e(trim($_POST['vat_number'] ?? ''));
    $form['address'] = e(trim($_POST['address'] ?? ''));
    $form['city'] = e(trim($_POST['city'] ?? ''));
    $form['postcode'] = e(trim($_POST['postcode'] ?? ''));
    $form['country'] = e($_POST['country'] ?? $deliveryCountryIso);
    $form['note'] = e(trim($_POST['note'] ?? ''));
}

$errors = [];
$whatsappNumber = $settings['whatsapp_order'] ?? '+33612345678';

$pageTitle = 'Passer commande — ' . e($siteName);
$pageDescription = 'Formulaire de commande. Paiement hors ligne via WhatsApp.';

require_once INCLUDES_DIR . '/header.php';
?>

<section class="section">
    <div class="container">
        <nav class="breadcrumbs" style="margin-bottom:16px; font-size:12px; color:var(--color-gray-light);">
            <a href="index.php">Accueil</a> <span style="color:var(--color-gray); margin:0 6px;">›</span>
            <a href="panier.php">Mon panier</a> <span style="color:var(--color-gray); margin:0 6px;">›</span>
            <strong>Passer commande</strong>
        </nav>

        <div class="section__title">Tunnel de commande</div>

        <?php if ($errors): ?>
            <div class="alert alert--error" style="margin-bottom:20px;">
                <?= implode('<br>', array_map('e', $errors)) ?>
            </div>
        <?php endif; ?>

        <div class="order-layout" style="display:grid; grid-template-columns: 1fr 360px; gap:30px; align-items:start;">
            <!-- Formulaire client -->
            <form action="api/commande.php" method="POST" id="order-form" novalidate style="background:var(--color-light); border:1px solid var(--color-border); border-radius:var(--radius); padding:24px;">
                <?= csrfField() ?>

                <h3 style="font-size:16px; font-weight:800; color:var(--color-dark); margin-bottom:16px; padding-bottom:10px; border-bottom:1px solid var(--color-border);">Vos informations</h3>

                <div class="form-row">
                    <div class="form-group">
                        <label>Prénom <span style="color:var(--color-error);">*</span></label>
                        <input type="text" name="firstname" value="<?= $form['firstname'] ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Nom <span style="color:var(--color-error);">*</span></label>
                        <input type="text" name="lastname" value="<?= $form['lastname'] ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Entreprise (facultatif)</label>
                    <input type="text" name="company" value="<?= $form['company'] ?>" placeholder="Nom de votre entreprise">
                </div>

                <div class="form-group">
                    <label>N° de TVA intracommunautaire (facultatif)</label>
                    <input type="text" name="vat_number" value="<?= $form['vat_number'] ?>" placeholder="Ex. FR 12 345 678 901">
                    <small style="color:var(--color-gray-light);">Uniquement si vous êtes une entreprise de l Union européenne.</small>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Téléphone international <span style="color:var(--color-error);">*</span></label>
                        <input type="tel" name="phone" value="<?= $form['phone'] ?>" placeholder="+33 6 12 34 56 78" required>
                        <small style="color:var(--color-gray-light);">Format international E.164 (ex. +33 6 12 34 56 78)</small>
                    </div>
                    <div class="form-group">
                        <label>Email <span style="color:var(--color-error);">*</span></label>
                        <input type="email" name="email" value="<?= $form['email'] ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Adresse <span style="color:var(--color-error);">*</span></label>
                    <input type="text" name="address" value="<?= $form['address'] ?>" required>
                </div>

                <div class="form-row">
                    <div class="form-group" style="flex:1 1 200px;">
                        <label>Code postal <span style="color:var(--color-error);">*</span></label>
                        <input type="text" name="postcode" value="<?= $form['postcode'] ?>" required>
                    </div>
                    <div class="form-group" style="flex:1 1 220px;">
                        <label>Ville <span style="color:var(--color-error);">*</span></label>
                        <input type="text" name="city" value="<?= $form['city'] ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Pays de livraison <span style="color:var(--color-error);">*</span></label>
                    <select name="country" required>
                        <option value="">Sélectionner un pays</option>
                        <?php foreach ($countries as $c): ?>
                            <option value="<?= e($c['iso_alpha2']) ?>" <?= $form['country'] === $c['iso_alpha2'] ? 'selected' : '' ?>>
                                <?= e($c['name']) ?> <?= nt('(' . $c['phone_code'] . ')') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Note / message pour l équipe (facultatif)</label>
                    <textarea name="note" rows="3" placeholder="Précisions sur votre commande…"><?= $form['note'] ?></textarea>
                </div>

                <div class="form-group consent" style="margin-top:8px;">
                    <input type="checkbox" name="cgv" id="cgv" required>
                    <label for="cgv" style="cursor:pointer;">
                        J ai lu et j accepte les <a href="cgv.php" style="color:var(--color-primary); text-decoration:underline;">Conditions générales de vente</a>.
                        <br>
                        <span style="font-size:12px; color:var(--color-gray);">Les droits de douane, taxes d importation et TVA locale éventuels sont à la charge de l acheteur hors Union européenne. Traduction automatique : la version française fait foi.</span>
                    </label>
                </div>

                <button type="submit" class="btn btn--primary btn--full" style="margin-top:18px; font-size:16px;">
                    Continuer vers WhatsApp
                </button>
            </form>

            <!-- Résumé de commande -->
            <div class="order-summary" style="background:var(--color-light); border:1px solid var(--color-border); border-radius:var(--radius); padding:20px; position:sticky; top:110px;">
                <h3 style="font-size:16px; font-weight:800; color:var(--color-dark); margin-bottom:16px; padding-bottom:10px; border-bottom:1px solid var(--color-border);">Récapitulatif</h3>

                <?php foreach ($cartItems as $item): ?>
                    <div style="display:flex; gap:10px; margin-bottom:12px; font-size:13px;">
                        <img src="<?= e($item['image']) ?>" alt="" style="width:44px; height:44px; object-fit:cover; border-radius:var(--radius-sm); flex-shrink:0;">
                        <div style="flex:1;">
                            <div style="font-weight:600; color:var(--color-dark);"><?= e($item['name']) ?></div>
                            <div style="font-size:11px; color:var(--color-gray);"><span class="notranslate" translate="no"><?= e($item['reference']) ?></span> × <?= (int)$item['qty'] ?></div>
                        </div>
                        <div style="text-align:right; font-weight:700; font-size:13px;"><span class="notranslate" translate="no"><?= formatPrice(convertAmount((float)$item['line_total'], ['code' => 'EUR', 'exchange_rate' => 1.000000], $currency), $currency) ?></span></div>
                    </div>
                <?php endforeach; ?>

                <div style="border-top:1px solid var(--color-border); padding-top:12px; margin-top:12px;">
                    <div style="display:flex; justify-content:space-between; font-size:13px; color:var(--color-gray-dark); margin-bottom:6px;">
                        <span>Sous-total</span>
                        <span class="notranslate" translate="no"><?= formatPrice($subtotalConverted, $currency) ?></span>
                    </div>
                    <div style="display:flex; justify-content:space-between; font-size:13px; color:var(--color-gray-dark); margin-bottom:6px;">
                        <span>Livraison</span>
                        <span class="notranslate" translate="no"><?= formatPrice($shippingConverted, $currency) ?></span>
                    </div>
                    <div style="display:flex; justify-content:space-between; font-size:13px; color:var(--color-gray-dark); margin-bottom:6px;">
                        <span><?= e($vatLabel) ?></span>
                        <span class="notranslate" translate="no"><?= formatPrice($vatConverted, $currency) ?></span>
                    </div>
                    <div style="border-top:1px solid var(--color-border); padding-top:10px; margin-top:10px; display:flex; justify-content:space-between; font-size:16px; font-weight:800; color:var(--color-dark);">
                        <span>Total</span>
                        <span class="notranslate" translate="no"><?= formatPrice($totalConverted, $currency) ?></span>
                    </div>
                </div>

                <p style="font-size:11px; color:var(--color-gray); margin-top:16px; line-height:1.5;">
                    Aucun paiement en ligne. Votre commande est enregistrée et vous serez redirigé vers WhatsApp pour finaliser le paiement hors ligne.
                </p>
            </div>
        </div>
    </div>
</section>

<script>
/* Envoi du formulaire de commande en AJAX + redirection WhatsApp */
(function(){
  var form = document.getElementById('order-form');
  if (!form) return;
  form.addEventListener('submit', function(e){
    e.preventDefault();
    var btn = form.querySelector('button[type="submit"]');
    var original = btn.innerHTML;
    btn.innerHTML = 'Enregistrement de la commande…';
    btn.disabled = true;

    var formData = new FormData(form);
    fetch(form.action, { method: 'POST', body: formData, credentials: 'same-origin' })
      .then(function(r){ return r.json(); })
      .then(function(data){
        if (data.ok) {
          // La page de confirmation ouvre automatiquement WhatsApp (navigation directe, non bloquée).
          var confirmUrl = 'commande-confirmation.php?order=' + encodeURIComponent(data.order_number) + '&auto_wa=1';
          if (data.whatsapp_url) {
            sessionStorage.setItem('ie_whatsapp_url', data.whatsapp_url);
          }
          window.location.href = confirmUrl;
        } else {
          alert(data.message || 'Erreur lors de l enregistrement de la commande.');
          btn.innerHTML = original;
          btn.disabled = false;
        }
      })
      .catch(function(){
        alert('Erreur de connexion.');
        btn.innerHTML = original;
        btn.disabled = false;
      });
    return false;
  });
})();
</script>

<?php
require_once INCLUDES_DIR . '/footer.php';
?>
