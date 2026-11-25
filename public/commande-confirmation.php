<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/page_bootstrap.php';

$db = db();
$settings = loadSettings($db);
$currency = detectCurrency($db);

$orderNumber = $_GET['order'] ?? null;
$order = null;
$orderItems = [];

if ($orderNumber !== null) {
    $stmt = $db->prepare("SELECT * FROM orders WHERE order_number = ? LIMIT 1");
    $stmt->execute([$orderNumber]);
    $order = $stmt->fetch();
    if ($order !== false) {
        $stmt = $db->prepare("SELECT * FROM order_items WHERE order_id = ? ORDER BY id ASC");
        $stmt->execute([(int)$order['id']]);
        $orderItems = $stmt->fetchAll();
    }
}

if ($order === false || $order === null) {
    $_SESSION['flash']['error'] = 'Commande introuvable.';
    redirect('index.php');
}

$whatsappNumber = preg_replace('/\s/', '', $settings['whatsapp_order'] ?? '+33612345678');
$whatsappNumberRaw = ltrim($whatsappNumber, '+');
$siteNameRaw = $settings['site_name'] ?? 'EuroImport Export';
$waMessage = urlencode('Bonjour, je confirme ma commande ' . $orderNumber . ' sur ' . $siteNameRaw . '. Merci.');
$waUrl = 'https://wa.me/' . $whatsappNumberRaw . '?text=' . $waMessage;

$pageTitle = 'Confirmation de commande ' . e($orderNumber) . ' — ' . e($siteName);
$pageDescription = 'Votre commande a été enregistrée. Redirection vers WhatsApp pour finaliser le paiement.';

require_once INCLUDES_DIR . '/header.php';
?>

<section class="section">
    <div class="container">
        <div class="order-confirmation" style="max-width:700px; margin:0 auto; text-align:center;">
            <div style="font-size:48px; color:var(--color-success); margin-bottom:16px;">✔</div>
            <h1 style="font-size:24px; font-weight:800; color:var(--color-dark); margin-bottom:10px;">Commande enregistrée</h1>
            <p style="font-size:15px; color:var(--color-gray-dark); margin-bottom:24px;">
                Votre commande <strong class="notranslate" translate="no"><?= e($orderNumber) ?></strong> a bien été enregistrée en base de données.
            </p>

            <div style="background:var(--color-light); border:1px solid var(--color-border); border-radius:var(--radius); padding:20px; margin-bottom:24px; text-align:left;">
                <h3 style="font-size:15px; font-weight:800; color:var(--color-dark); margin-bottom:12px;">Résumé de la commande</h3>
                <div style="display:flex; justify-content:space-between; font-size:13px; color:var(--color-gray-dark); margin-bottom:6px;">
                    <span>Date</span>
                    <span><?= date('d/m/Y H:i', strtotime($order['created_at'])) ?> UTC</span>
                </div>
                <div style="display:flex; justify-content:space-between; font-size:13px; color:var(--color-gray-dark); margin-bottom:6px;">
                    <span>Statut</span>
                    <span style="font-weight:600; color:var(--color-primary);"><?= e($order['status']) ?></span>
                </div>
                <div style="display:flex; justify-content:space-between; font-size:13px; color:var(--color-gray-dark); margin-bottom:6px;">
                    <span>Devise</span>
                    <span class="notranslate" translate="no"><?= e($order['currency_code']) ?></span>
                </div>
                <div style="display:flex; justify-content:space-between; font-size:13px; color:var(--color-gray-dark); margin-bottom:6px;">
                    <span>Total TTC</span>
                    <span class="notranslate" translate="no" style="font-weight:700;"><?= formatPrice((float)$order['total'], ['code' => $order['currency_code'], 'symbol' => $currency['symbol'], 'exchange_rate' => 1.000000]) ?></span>
                </div>
            </div>

            <div style="background:#fff3e0; border:1px solid var(--color-primary-light); border-radius:var(--radius); padding:16px; margin-bottom:24px; text-align:left;">
                <p style="font-size:13px; color:var(--color-primary-dark); margin:0 0 10px 0; font-weight:600;">
                    Étape suivante : paiement via WhatsApp
                </p>
                <p style="font-size:13px; color:var(--color-gray-dark); margin:0;">
                    Vous allez être redirigé vers WhatsApp avec un message prérempli contenant vos informations et le détail de votre commande.<br>
                    Le paiement se fait hors ligne (virement bancaire ou autre moyen convenu).<br>
                    <span style="font-size:11px; color:var(--color-gray);">Numéro WhatsApp : <span class="notranslate" translate="no"><?= nt($settings['whatsapp_order'] ?? '+33 6 12 34 56 78') ?></span></span>
                </p>
            </div>

            <a href="<?= e($waUrl) ?>" class="btn btn--primary btn--full" style="margin-bottom:12px;">
                <i class="fab fa-whatsapp"></i> Ouvrir WhatsApp
            </a>

            <button class="btn btn--outline btn--full" onclick="location.href='#'" style="margin-bottom:24px;">
                Je n arrive pas à ouvrir WhatsApp
            </button>

            <div id="whatsapp-fallback" style="display:none; margin-top:16px; text-align:left;">
                <p style="font-size:13px; color:var(--color-gray-dark);">
                    Si la redirection n'a pas fonctionné, copiez ce numéro dans WhatsApp :<br>
                    <span class="notranslate" translate="no" style="font-weight:600;"><?= nt($settings['whatsapp_order'] ?? '+33 6 12 34 56 78') ?></span>
                </p>
                <p style="font-size:13px; color:var(--color-gray-dark);">
                    Envoyez simplement la mention : <span class="notranslate" translate="no">Commande <?= e($orderNumber) ?></span>
                </p>
            </div>
        </div>

        <!-- Détail des articles -->
        <div class="container" style="max-width:900px; margin:30px auto 0;">
            <h3 style="font-size:16px; font-weight:800; color:var(--color-dark); margin-bottom:12px;">Articles commandés</h3>
            <table style="width:100%; border-collapse:collapse;">
                <thead>
                    <tr style="border-bottom:2px solid var(--color-border);">
                        <th style="text-align:left; padding:10px; font-size:13px; color:var(--color-gray);">Article</th>
                        <th style="text-align:right; padding:10px; font-size:13px; color:var(--color-gray);">Prix unitaire</th>
                        <th style="text-align:center; padding:10px; font-size:13px; color:var(--color-gray);">Qty</th>
                        <th style="text-align:right; padding:10px; font-size:13px; color:var(--color-gray);">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orderItems as $item): ?>
                        <tr style="border-bottom:1px solid var(--color-border);">
                            <td style="padding:10px; font-size:13px; color:var(--color-dark);">
                                <div style="font-weight:600;"><?= e($item['name']) ?></div>
                                <div style="font-size:11px; color:var(--color-gray);"><span class="notranslate" translate="no"><?= e($item['reference']) ?></span></div>
                            </td>
                            <td style="padding:10px; text-align:right; font-size:13px;"><span class="notranslate" translate="no"><?= number_format((float)$item['unit_price'], 2, ',', ' ') ?> EUR</span></td>
                            <td style="padding:10px; text-align:center; font-size:13px;"><?= (int)$item['quantity'] ?></td>
                            <td style="padding:10px; text-align:right; font-size:13px; font-weight:700;"><span class="notranslate" translate="no"><?= number_format((float)$item['total'], 2, ',', ' ') ?> EUR</span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<script>
/* Ouverture automatique de WhatsApp après la commande + bouton de secours */
(function(){
  var waUrl = <?= json_encode($waUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
  var params = new URLSearchParams(window.location.search);
  var stored = null;
  try { stored = sessionStorage.getItem('ie_whatsapp_url'); } catch (e) {}
  if (stored) {
    waUrl = stored;
    try { sessionStorage.removeItem('ie_whatsapp_url'); } catch (e) {}
  }

  // Redirection automatique (même onglet : non bloquée par les navigateurs)
  if (params.get('auto_wa') === '1') {
    var notice = document.createElement('p');
    notice.style.cssText = 'font-size:13px;color:var(--color-primary-dark);font-weight:600;margin-bottom:16px;';
    notice.textContent = 'Redirection vers WhatsApp dans un instant…';
    var box = document.querySelector('.order-confirmation h1');
    if (box && box.parentNode) { box.parentNode.insertBefore(notice, box.nextSibling); }
    setTimeout(function(){ window.location.href = waUrl; }, 1200);
  }

  var btn = document.querySelector('button.btn--outline');
  var fallback = document.getElementById('whatsapp-fallback');
  if (btn && fallback) {
    btn.addEventListener('click', function(e){
      e.preventDefault();
      btn.style.display = 'none';
      fallback.style.display = 'block';
    });
  }
})();
</script>

<?php
require_once INCLUDES_DIR . '/footer.php';
?>
