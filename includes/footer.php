<?php
declare(strict_types=1);

// Les pages admin ont leur propre pied de page.
if (defined('ADMIN_CONTEXT')) {
    require_once ADMIN_DIR . '/includes/footer.php';
    return;
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

$db = db();
$settings = loadSettings($db);
$siteName = !empty($settings['site_name']) ? $settings['site_name'] : 'EuroImport Export';
$siteEmail = $settings['contact_email'] ?? 'contact@example.com';
$sitePhone = $settings['public_phone'] ?? '+33 1 40 20 30 40';
$siteAddress = $settings['address'] ?? '';
$siret = $settings['siret'] ?? '';
$tva = $settings['tva_intracommunautaire'] ?? '';
$eori = $settings['eori'] ?? '';
$timezone = $settings['timezone'] ?? 'Europe/Paris';
?>

</main>

<!-- ======================= PIED DE PAGE ======================= -->
<footer class="site-footer">
    <div class="container footer__grid">
        <div class="footer__col">
            <h4 class="footer__title"><?= e($siteName) ?></h4>
            <p class="footer__slogan"><?= e($settings['site_slogan'] ?? '') ?></p>
            <p class="footer__contact">
                <i class="fas fa-map-marker-alt"></i> <?= nt($siteAddress) ?><br>
                <i class="fas fa-phone"></i> <?= nt($sitePhone) ?><br>
                <i class="fas fa-envelope"></i> <?= nt($siteEmail) ?>
            </p>
        </div>

        <div class="footer__col">
            <h4 class="footer__title">Boutique</h4>
            <ul class="footer__list">
                <li><a href="catalogue.php">Catalogue</a></li>
                <li><a href="recherche.php">Recherche</a></li>
                <li><a href="panier.php">Panier</a></li>
                <li><a href="compte.php">Mon compte</a></li>
            </ul>
        </div>

        <div class="footer__col">
            <h4 class="footer__title">Compte</h4>
            <ul class="footer__list">
                <li><a href="connexion.php">Connexion</a></li>
                <li><a href="inscription.php">Inscription</a></li>
                <li><a href="mot-de-passe-oublie.php">Mot de passe oublié</a></li>
            </ul>
        </div>

        <div class="footer__col">
            <h4 class="footer__title">Légal</h4>
            <ul class="footer__list">
                <li><a href="mentions-legales.php">Mentions légales</a></li>
                <li><a href="cgv.php">CGV</a></li>
                <li><a href="politique-confidentialite.php">Politique de confidentialité</a></li>
                <li><a href="livraison-douane.php">Livraison et douane</a></li>
                <li><a href="retours-retractation.php">Retours et rétractation</a></li>
                <li><a href="faq.php">FAQ</a></li>
            </ul>
        </div>

        <div class="footer__col footer__newsletter">
            <h4 class="footer__title">Newsletter</h4>
            <p>Abonnez-vous à notre lettre d'information (en français).</p>
            <form action="newsletter.php" method="POST" class="newsletter-form" novalidate>
                <?= csrfField() ?>
                <input type="email" name="email" placeholder="Votre adresse email" required>
                <label class="consent"><input type="checkbox" name="consent" required> J'accepte de recevoir la newsletter et la politique de confidentialité.</label>
                <button type="submit" class="btn btn--primary btn--sm">S'abonner</button>
            </form>
            <p class="newsletter-note">Vous pouvez vous désabonner à tout moment. Conformément au RGPD.</p>
        </div>
    </div>

    <div class="footer__bottom">
        <div class="container footer__bottom__inner">
            <p class="footer__legal">
                <?= nt($siteName) ?> –
                <?php if ($siret !== ''): ?><?= nt('SIRET ' . $siret) ?> – <?php endif; ?>
                <?php if ($tva !== ''): ?><?= nt('TVA ' . $tva) ?> – <?php endif; ?>
                <?php if ($eori !== ''): ?><?= nt('EORI ' . $eori) ?> – <?php endif; ?>
                <span class="notranslate" translate="no"><?= e($timezone) ?></span>
            </p>
            <p class="footer__disclaimer">
                Traduction automatique. La version française fait foi.
            </p>
        </div>
    </div>
</footer>

<link rel="stylesheet" href="<?= ASSETS_URL ?>/css/chat.css?v=1.2.0">

<!-- Widget de chat d'assistance (bulle bas à droite) -->
<div class="chat-widget">
    <button class="chat-toggle" id="chat-toggle" aria-label="Ouvrir le chat" aria-controls="chat-window" aria-expanded="false">
        <i class="fas fa-comment"></i>
        <span class="badge" id="chat-badge"></span>
    </button>
    <div class="chat-window" id="chat-window">
        <div class="chat-header">
            <i class="fas fa-comment-dots"></i>
            <h3>Assistance</h3>
            <span class="chat-status" id="chat-status">Chargement…</span>
            <button class="chat-close" id="chat-close" aria-label="Fermer le chat">×</button>
        </div>
        <div class="chat-body" id="chat-body"></div>
        <div class="chat-intro" id="chat-intro" style="display:none;"></div>
        <div class="chat-identity" id="chat-identity" style="display:none;">
            <p class="chat-identity__title">Présentez-vous pour commencer :</p>
            <input type="text" id="chat-name" placeholder="Votre nom" autocomplete="name">
            <input type="email" id="chat-email" placeholder="Votre email (facultatif)" autocomplete="email">
            <button type="button" id="chat-identity-start">Démarrer la conversation</button>
            <p class="chat-identity__error" id="chat-identity-error"></p>
        </div>
        <p class="chat-offline-note" id="chat-offline-note"></p>
        <form class="chat-form" id="chat-form">
            <input type="text" name="message" id="chat-input" placeholder="Écrire un message…" autocomplete="off">
            <label class="chat-attach" aria-label="Joindre une image">
                <i class="fas fa-paperclip"></i>
                <input type="file" name="image" id="chat-attach-input" accept="image/*" style="display:none;">
            </label>
            <button type="submit" id="chat-send"><i class="fas fa-paper-plane"></i></button>
        </form>
    </div>
</div>

<!-- Bandeau cookies (consentement requis avant chargement du widget Google Translate) -->
<div id="cookie-banner" class="cookie-banner" style="display:none;">
    <div class="cookie-banner__inner">
        <div class="cookie-banner__text">
            <strong>Cookies et traduction automatique :</strong>
            Ce site utilise des cookies pour fonctionner (panier, compte, session) et, avec votre consentement,
            le widget de traduction automatique de Google (Google Website Translator) qui dépose ses propres cookies.
            La traduction automatique n’a aucune valeur juridique ; la version française fait foi.
        </div>
        <div class="cookie-banner__actions">
            <button id="cookie-accept" class="btn btn--primary btn--sm" style="padding:6px 14px;">J’accepte</button>
            <button id="cookie-decline" class="btn btn--outline btn--sm" style="padding:6px 14px; margin-left:8px;">Refuser</button>
            <a href="politique-confidentialite.php" style="font-size:12px; color:var(--color-primary);">En savoir plus</a>
        </div>
    </div>
</div>

<style>
.cookie-banner {
  position: fixed;
  bottom: 0; left: 0; right: 0;
  background: #1a1a1a;
  color: #fff;
  border-top: 1px solid #333;
  box-shadow: 0 -2px 12px rgba(0,0,0,0.3);
  z-index: 2000;
  padding: 14px 0;
  font-size: 13px;
}
.cookie-banner__inner {
  max-width: var(--container-width);
  margin: 0 auto;
  padding: 0 18px;
  display: flex;
  flex-direction: column;
  gap: 10px;
  align-items: center;
  text-align: center;
}
.cookie-banner__text {
  max-width: 700px;
  line-height: 1.5;
  color: #ddd;
}
.cookie-banner__actions {
  display: flex;
  gap: 8px;
  align-items: center;
  justify-content: center;
}
@media (min-width: 768px) {
  .cookie-banner__inner {
    flex-direction: row;
    justify-content: space-between;
    align-items: center;
  }
}
</style>

<script>
/* Gestion du consentement cookies */
(function(){
  var banner = document.getElementById('cookie-banner');
  var acceptBtn = document.getElementById('cookie-accept');
  var declineBtn = document.getElementById('cookie-decline');

  function hasConsent() {
    return document.cookie.match('(?:^|; )ie_cookie_consent=([^;]*)') !== null;
  }

  function setConsent(value) {
    var d = new Date();
    d.setTime(d.getTime() + 365*24*60*60*1000);
    document.cookie = 'ie_cookie_consent=' + value + '; expires=' + d.toUTCString() + '; path=/; SameSite=Lax';
    if (banner) banner.style.display = 'none';
    if (value === 'true') {
      // Charger le widget Google Translate et recharger pour appliquer la traduction
      var s = document.createElement('script');
      s.src = '//translate.google.com/translate_a/element.js?cb=googleTranslateElementInit';
      document.head.appendChild(s);
      location.reload();
    }
  }

  if (!hasConsent() && banner) {
    banner.style.display = 'block';
  }
  if (acceptBtn) {
    acceptBtn.addEventListener('click', function(){ setConsent('true'); });
  }
  if (declineBtn) {
    declineBtn.addEventListener('click', function(){ setConsent('false'); });
  }
})();
</script>

<script src="<?= ASSETS_URL ?>/js/main.js?v=1.1.0"></script>
<script src="<?= ASSETS_URL ?>/js/catalogue.js?v=1.2.0"></script>
<script src="<?= ASSETS_URL ?>/js/chat.js?v=1.3.0"></script>
</div>
</body>
</html>
