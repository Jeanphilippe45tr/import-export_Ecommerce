<?php
declare(strict_types=1);

$db = db();
$settings = loadSettings($db);
$siteName = !empty($settings['site_name']) ? $settings['site_name'] : 'EuroImport Export';

?>
    </div>
</main>
</div>

<footer class="admin-footer">
    <div class="container" style="max-width:1240px; padding:0 18px; margin:0 auto;">
        <p style="font-size:12px; color:var(--color-gray);">
            <?= e($siteName) ?> — Administration &bull; Connexion admin sécurisée &bull; Aucune traduction automatique sur cet espace
        </p>
    </div>
</footer>

<script src="<?= ASSETS_URL ?>/js/admin.js?v=1.2.1"></script>
</body>
</html>
