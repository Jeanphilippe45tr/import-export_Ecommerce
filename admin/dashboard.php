<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/page_bootstrap.php';

$db = db();
$settings = loadSettings($db);
$admin = adminGet();

// --- Statistiques ---
// Commandes aujourd'hui (UTC)
$stmt = $db->query("SELECT COUNT(*) AS nb, COALESCE(SUM(total), 0) AS total FROM orders WHERE DATE(created_at) = CURDATE()");
$today = $stmt->fetch();
$todayCount = (int)$today['nb'];
$todayTotal = (float)$today['total'];

// Commandes hier (pour une tendance réelle)
$stmt = $db->query("SELECT COUNT(*) AS nb FROM orders WHERE DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)");
$yesterdayCount = (int)$stmt->fetch()['nb'];
if ($yesterdayCount > 0) {
    $trend = (int)round((($todayCount - $yesterdayCount) / $yesterdayCount) * 100);
} else {
    $trend = $todayCount > 0 ? 100 : 0;
}
$trendClass = $trend >= 0 ? 'up' : 'down';
$trendIcon = $trend >= 0 ? 'fa-arrow-up' : 'fa-arrow-down';
$trendLabel = ($trend > 0 ? '+' : '') . $trend . '% vs hier';

// Commandes du mois (UTC)
$stmt = $db->query("SELECT COUNT(*) AS nb, COALESCE(SUM(total), 0) AS total FROM orders WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
$month = $stmt->fetch();
$monthCount = (int)$month['nb'];
$monthTotal = (float)$month['total'];

// Articles en rupture de stock (stock = 0)
$stmt = $db->query("SELECT COUNT(*) AS nb FROM products WHERE is_active = 1 AND stock <= 0");
$rupture = (int)$stmt->fetch()['nb'];

// Nouveaux clients (30 jours)
$stmt = $db->query("SELECT COUNT(*) AS nb FROM customers WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
$newClients = (int)$stmt->fetch()['nb'];

// Messages non lus
$stmt = $db->query("SELECT COUNT(*) AS nb FROM messages m JOIN conversations c ON c.id = m.conversation_id WHERE m.sender = 'visitor' AND m.is_read = 0");
$unreadMessages = (int)$stmt->fetch()['nb'];

// Répartition des commandes par pays (top 5)
$stmt = $db->query(
    "SELECT c.name AS country_name, COUNT(o.id) AS nb
     FROM orders o
     JOIN countries c ON o.country_id = c.id
     GROUP BY o.country_id
     ORDER BY nb DESC
     LIMIT 5"
);
$countriesStats = $stmt->fetchAll();

// Commandes par mois (derniers 6 mois) pour le graphique
$stmt = $db->query(
    "SELECT DATE_FORMAT(created_at, '%Y-%m') AS mois, COUNT(*) AS nb, COALESCE(SUM(total), 0) AS total
     FROM orders
     WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
     GROUP BY mois
     ORDER BY mois ASC"
);
$monthsStats = $stmt->fetchAll();

$pageTitle = 'Tableau de bord — Administration';

require_once __DIR__ . '/includes/header.php';
?>

<div class="admin-content">
  <div class="admin-content-inner">

    <div class="admin-page-head">
        <div>
            <h1><i class="fas fa-gauge-high"></i> Tableau de bord</h1>
            <p class="admin-page-subtitle">Vue d'ensemble de la boutique — <?= date('d/m/Y') ?> · <?= e($admin['username']) ?></p>
        </div>
        <div class="admin-page-actions">
            <a href="commandes.php" class="btn btn--outline"><i class="fas fa-list"></i> Commandes</a>
            <a href="articles.php" class="btn btn--primary"><i class="fas fa-plus"></i> Gérer les articles</a>
        </div>
    </div>

    <!-- Cartes statistiques -->
    <div class="stat-grid">
        <div class="stat-card" style="--stat-color:#ff6600; --stat-soft:#fff1e6;">
            <div class="stat-card__top">
                <span class="stat-card__icon"><i class="fas fa-cart-plus"></i></span>
                <span class="stat-card__hint">Aujourd'hui</span>
            </div>
            <div class="stat-card__value"><?= (int)$todayCount ?></div>
            <div class="stat-card__label">Commandes aujourd'hui</div>
            <div class="stat-card__value stat-card__value--sm notranslate" translate="no"><?= number_format($todayTotal, 2, ',', ' ') ?> EUR</div>
            <div class="stat-card__trend stat-card__trend--<?= $trendClass ?>"><i class="fas <?= $trendIcon ?>"></i> <?= e($trendLabel) ?></div>
        </div>

        <div class="stat-card" style="--stat-color:#1565c0; --stat-soft:#e8f2fb;">
            <div class="stat-card__top">
                <span class="stat-card__icon"><i class="fas fa-calendar-check"></i></span>
                <span class="stat-card__hint">30 jours</span>
            </div>
            <div class="stat-card__value"><?= (int)$monthCount ?></div>
            <div class="stat-card__label">Commandes sur 30 jours</div>
            <div class="stat-card__value stat-card__value--sm notranslate" translate="no"><?= number_format($monthTotal, 2, ',', ' ') ?> EUR</div>
            <div class="stat-card__trend"><i class="fas fa-coins"></i> Chiffre d'affaires</div>
        </div>

        <div class="stat-card" style="--stat-color:#c62828; --stat-soft:#fdecea;">
            <div class="stat-card__top">
                <span class="stat-card__icon"><i class="fas fa-box-open"></i></span>
                <span class="stat-card__hint">Stock</span>
            </div>
            <div class="stat-card__value"><?= (int)$rupture ?></div>
            <div class="stat-card__label">Articles en rupture</div>
            <div class="stat-card__trend stat-card__trend--<?= $rupture > 0 ? 'down' : 'up' ?>">
                <i class="fas <?= $rupture > 0 ? 'fa-exclamation-triangle' : 'fa-check-circle' ?>"></i>
                <?= $rupture > 0 ? 'À réapprovisionner' : 'Stock correct' ?>
            </div>
        </div>

        <div class="stat-card" style="--stat-color:#2e7d32; --stat-soft:#e7f7ea;">
            <div class="stat-card__top">
                <span class="stat-card__icon"><i class="fas fa-user-plus"></i></span>
                <span class="stat-card__hint">30 jours</span>
            </div>
            <div class="stat-card__value"><?= (int)$newClients ?></div>
            <div class="stat-card__label">Nouveaux clients</div>
            <div class="stat-card__trend stat-card__trend--up"><i class="fas fa-users"></i> Inscriptions</div>
        </div>

        <div class="stat-card" style="--stat-color:#6a1b9a; --stat-soft:#f3eafb;">
            <div class="stat-card__top">
                <span class="stat-card__icon"><i class="fas fa-comment-dots"></i></span>
                <span class="stat-card__hint">Messagerie</span>
            </div>
            <div class="stat-card__value"><?= (int)$unreadMessages ?></div>
            <div class="stat-card__label">Messages non lus</div>
            <div class="stat-card__trend stat-card__trend--<?= $unreadMessages > 0 ? 'down' : 'up' ?>">
                <i class="fas <?= $unreadMessages > 0 ? 'fa-envelope' : 'fa-check-circle' ?>"></i>
                <?= $unreadMessages > 0 ? 'À traiter' : 'Tout est à jour' ?>
            </div>
        </div>
    </div>

    <!-- Graphiques -->
    <div class="panel-grid">
        <section class="panel">
            <div class="panel__head">
                <h2 class="panel__title"><i class="fas fa-chart-column"></i> Commandes par mois</h2>
                <span class="panel__hint">6 derniers mois</span>
            </div>
            <div class="panel__body">
                <div class="chart-wrap chart-wrap--flush"><canvas id="chartMonths" height="240"></canvas></div>
            </div>
        </section>

        <section class="panel">
            <div class="panel__head">
                <h2 class="panel__title"><i class="fas fa-globe"></i> Top 5 pays</h2>
                <span class="panel__hint">Nombre de commandes</span>
            </div>
            <div class="panel__body">
                <div class="chart-wrap chart-wrap--flush"><canvas id="chartCountries" height="240"></canvas></div>
            </div>
        </section>
    </div>

    <!-- Commandes récentes -->
    <?php
    $stmt = $db->query(
        "SELECT o.order_number, o.status, o.total, o.currency_code, o.created_at, c.email, c.firstname, c.lastname
         FROM orders o
         LEFT JOIN customers c ON o.customer_id = c.id
         ORDER BY o.created_at DESC
         LIMIT 10"
    );
    $recentOrders = $stmt->fetchAll();
    ?>
    <section class="panel">
        <div class="panel__head">
            <h2 class="panel__title"><i class="fas fa-shopping-bag"></i> Commandes récentes</h2>
            <a href="commandes.php" class="panel__hint">Voir toutes les commandes <i class="fas fa-arrow-right"></i></a>
        </div>
        <div class="panel__body panel__body--flush">
            <?php if ($recentOrders): ?>
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Numéro</th>
                                <th>Client</th>
                                <th>Date</th>
                                <th>Statut</th>
                                <th class="cell-right">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentOrders as $o): ?>
                                <tr>
                                    <td><span class="notranslate" translate="no"><?= e($o['order_number']) ?></span></td>
                                    <td>
                                        <?= e(trim($o['firstname'] . ' ' . $o['lastname']) ?: 'Client') ?>
                                        <div class="cell-sub"><?= e($o['email'] ?? '—') ?></div>
                                    </td>
                                    <td class="cell-sub"><?= date('d/m/Y H:i', strtotime($o['created_at'])) ?> UTC</td>
                                    <td><span class="badge badge--<?= e($o['status']) ?>"><?= e($o['status']) ?></span></td>
                                    <td class="cell-right"><span class="notranslate" translate="no"><?= number_format((float)$o['total'], 2, ',', ' ') ?> <?= e($o['currency_code']) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state"><i class="fas fa-shopping-bag"></i><p>Aucune commande récente.</p></div>
            <?php endif; ?>
        </div>
    </section>

  </div>
</div>

<!-- Chart.js via CDN (graphiques simples, admin uniquement) -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
(function () {
  var monthLabels = <?= json_encode(array_column($monthsStats, 'mois'), JSON_UNESCAPED_UNICODE) ?>;
  var monthData   = <?= json_encode(array_map('intval', array_column($monthsStats, 'nb'))) ?>;
  var countryLabels = <?= json_encode(array_column($countriesStats, 'country_name'), JSON_UNESCAPED_UNICODE) ?>;
  var countryData   = <?= json_encode(array_map('intval', array_column($countriesStats, 'nb'))) ?>;

  function esc(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[c];
    });
  }

  // Repli si Chart.js (CDN) n'est pas disponible : barres CSS locales.
  function fallback(container, labels, data) {
    if (!container || !labels.length) { return; }
    var max = Math.max.apply(null, data.concat([1]));
    container.innerHTML = '<div class="chart-fallback">' + labels.map(function (label, i) {
      var width = Math.round((data[i] / max) * 100);
      return '<div class="chart-fallback__row">'
        + '<span class="chart-fallback__label">' + esc(label) + '</span>'
        + '<span class="chart-fallback__track"><span class="chart-fallback__bar" style="width:' + width + '%"></span></span>'
        + '<b>' + data[i] + '</b></div>';
    }).join('') + '</div>';
  }

  if (typeof Chart === 'undefined') {
    var m = document.getElementById('chartMonths');
    var c = document.getElementById('chartCountries');
    fallback(m && m.parentNode, monthLabels, monthData);
    fallback(c && c.parentNode, countryLabels, countryData);
    return;
  }

  var ctxM = document.getElementById('chartMonths');
  if (ctxM) {
    new Chart(ctxM.getContext('2d'), {
      type: 'bar',
      data: {
        labels: monthLabels,
        datasets: [{
          label: 'Commandes',
          data: monthData,
          backgroundColor: 'rgba(255, 102, 0, 0.75)',
          hoverBackgroundColor: 'rgba(230, 81, 0, 1)',
          borderColor: 'rgba(255, 102, 0, 1)',
          borderWidth: 1,
          borderRadius: 6,
          maxBarThickness: 44
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
          y: { beginAtZero: true, ticks: { stepSize: 1, color: '#9ca3af' }, grid: { color: '#f3f4f6' } },
          x: { ticks: { color: '#6b7280' }, grid: { display: false } }
        }
      }
    });
  }

  var ctxC = document.getElementById('chartCountries');
  if (ctxC) {
    new Chart(ctxC.getContext('2d'), {
      type: 'doughnut',
      data: {
        labels: countryLabels,
        datasets: [{
          data: countryData,
          backgroundColor: ['#ff6600','#e65100','#ffa726','#ffb74d','#ffcc80'],
          borderWidth: 2,
          borderColor: '#fff'
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '62%',
        plugins: {
          legend: { position: 'bottom', labels: { boxWidth: 12, padding: 16, font: { size: 11 } } }
        }
      }
    });
  }
})();
</script>

<?php
require_once INCLUDES_DIR . '/footer.php';
?>
