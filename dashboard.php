<?php
require_once 'config/db.php';
require_once 'includes/helpers.php';
check_auth();

$usd_to_cdf = (float)get_setting('usd_to_cdf', 2800);

// Ventes du jour (séparées)
$sales_usd = $pdo->query("SELECT SUM(total_amount) FROM sales WHERE DATE(created_at) = CURDATE() AND currency = 'USD'")->fetchColumn() ?: 0;
$sales_cdf = $pdo->query("SELECT SUM(total_amount) FROM sales WHERE DATE(created_at) = CURDATE() AND currency = 'CDF'")->fetchColumn() ?: 0;

// Valeur du Stock (Les produits sont en USD)
$stock_value_usd = $pdo->query("SELECT SUM(stock_quantity * buy_price) FROM products")->fetchColumn() ?: 0;

// Dettes (séparées)
$debt_usd = $pdo->query("SELECT SUM(total_debt_usd) FROM clients")->fetchColumn() ?: 0;
$debt_cdf = $pdo->query("SELECT SUM(total_debt_cdf) FROM clients")->fetchColumn() ?: 0;

// Alertes stock
$low_stock_count = $pdo->query("SELECT COUNT(*) FROM products WHERE stock_quantity <= min_stock_threshold")->fetchColumn() ?: 0;

$recent_sales = $pdo->query("SELECT s.*, c.name as client_name FROM sales s LEFT JOIN clients c ON s.client_id = c.id ORDER BY s.created_at DESC LIMIT 6")->fetchAll();

// Données graphique : ventes des 7 derniers jours (équivalent USD)
$chart_raw = $pdo->query("
    SELECT
        DATE(created_at) as sale_date,
        SUM(CASE WHEN currency = 'USD' THEN total_amount ELSE total_amount / " . $usd_to_cdf . " END) as total_usd_equiv
    FROM sales
    WHERE created_at >= CURDATE() - INTERVAL 6 DAY
    GROUP BY DATE(created_at)
    ORDER BY sale_date ASC
")->fetchAll();

// Construire tableau sur 7 jours (avec 0 pour les jours sans vente)
$chart_data = [];
$chart_labels = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $label = date('D', strtotime($d)); // Mon, Tue...
    $chart_labels[] = "'$label'";
    $found = array_filter($chart_raw, fn($r) => $r['sale_date'] === $d);
    $chart_data[] = $found ? round(array_values($found)[0]['total_usd_equiv'], 2) : 0;
}
$chart_labels_js = implode(',', $chart_labels);
$chart_data_js   = implode(',', $chart_data);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <title>Tableau de bord - LXTRONIC</title>
    <?php include 'includes/head.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .stat-multi { display: flex; flex-direction: column; gap: 2px; }
        .stat-sub-val { font-size: 0.85rem; color: var(--color-slate-500); font-weight: 500; }
    </style>
</head>
<body>
<div class="app-layout">
    <?php include 'includes/sidebar.php'; ?>
    <?php include 'includes/responsive_header.php'; ?>

    <main class="main-content">
        <header class="page-header">
            <div>
                <h1>Bonjour, <?= $_SESSION['username'] ?> 👋</h1>
                <p>État de votre commerce aujourd'hui.</p>
            </div>
            <div class="header-actions">
                <a href="sales.php?new_sale=1" class="btn btn-primary btn-lg">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Nouvelle Vente
                </a>
            </div>
        </header>

        <div class="stats-grid">
            <!-- Ventes -->
            <div class="stat-card">
                <div class="stat-icon stat-icon-green">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div class="stat-multi">
                    <p class="stat-label">Ventes du jour</p>
                    <h3 class="stat-value"><?= format_price($sales_usd, 'USD') ?></h3>
                    <p class="stat-sub-val"><?= format_price($sales_cdf, 'CDF') ?></p>
                </div>
            </div>

            <!-- Stock -->
            <div class="stat-card">
                <div class="stat-icon stat-icon-blue">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                </div>
                <div>
                    <p class="stat-label">Valeur Stock (Achat)</p>
                    <h3 class="stat-value"><?= format_price($stock_value_usd, 'USD') ?></h3>
                    <p class="stat-sub-val">Soit <?= format_price($stock_value_usd * $usd_to_cdf, 'CDF') ?></p>
                </div>
            </div>

            <!-- Dettes -->
            <div class="stat-card">
                <div class="stat-icon stat-icon-red">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                </div>
                <div class="stat-multi">
                    <p class="stat-label">Dettes Clients</p>
                    <h3 class="stat-value text-danger"><?= format_price($debt_usd, 'USD') ?></h3>
                    <p class="stat-sub-val"><?= format_price($debt_cdf, 'CDF') ?></p>
                </div>
            </div>

            <!-- Alertes -->
            <div class="stat-card">
                <div class="stat-icon <?= $low_stock_count > 0 ? 'stat-icon-orange' : 'stat-icon-dark' ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <p class="stat-label">Alertes Stock</p>
                    <h3 class="stat-value"><?= $low_stock_count ?> Produit<?= $low_stock_count > 1 ? 's' : '' ?></h3>
                    <?php if ($low_stock_count > 0): ?>
                        <p class="stat-sub" style="color: var(--color-warning);">Faible stock !</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="dashboard-grid">
            <div class="card chart-card">
                <div class="chart-header">
                    <h3>Ventes des 7 derniers jours</h3>
                </div>
                <canvas id="salesChart" height="200"></canvas>
            </div>

            <div class="card card-padded">
                <h3 class="section-title">Ventes Récentes</h3>
                <div style="display:flex; flex-direction:column; gap:1.25rem;">
                    <?php if (empty($recent_sales)): ?>
                        <div class="table-empty"><p>Aucune transaction.</p></div>
                    <?php else: ?>
                        <?php foreach($recent_sales as $s): ?>
                        <div class="recent-activity-item">
                            <div class="activity-icon">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                            </div>
                            <div class="activity-info">
                                <p class="activity-name"><?= $s['client_name'] ?: 'Client au comptant' ?></p>
                                <p class="activity-time"><?= date('H:i', strtotime($s['created_at'])) ?></p>
                            </div>
                            <span class="activity-amount" style="color: <?= $s['status'] == 'dette' ? 'var(--color-danger)' : 'var(--color-success)' ?>;">
                                <?= $s['status'] == 'dette' ? '(Crédit)' : '+' ?> <?= format_price($s['total_amount'], $s['currency']) ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <div style="text-align:center; margin-top:1.5rem; padding-top:1rem; border-top:1px solid var(--color-slate-100);">
                    <a href="sales_history.php" class="btn btn-ghost">Voir tout l'historique &rarr;</a>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
    // Note: Dans une version réelle, ces données viendraient de la base de données
    const ctx = document.getElementById('salesChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: [<?= $chart_labels_js ?>],
            datasets: [{
                label: 'Ventes (USD-Équivalent)',
                data: [<?= $chart_data_js ?>],
                borderColor: '#2563eb',
                backgroundColor: 'rgba(37, 99, 235, 0.08)',
                borderWidth: 3,
                tension: 0.4,
                fill: true,
                pointBackgroundColor: '#fff',
                pointBorderColor: '#2563eb',
                pointBorderWidth: 2,
                pointRadius: 5
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, grid: { color: '#f1f5f9' }, border: { display: false } },
                x: { grid: { display: false }, border: { display: false } }
            }
        }
    });
</script>
</body>
</html>
