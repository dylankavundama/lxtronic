<?php
require_once 'config/db.php';
require_once 'includes/helpers.php';
check_auth();

$stats = [
    'total_sales' => $pdo->query("SELECT SUM(total_amount) FROM sales WHERE DATE(created_at) = CURDATE()")->fetchColumn() ?: 0,
    'total_stock_value' => $pdo->query("SELECT SUM(stock_quantity * buy_price) FROM products")->fetchColumn() ?: 0,
    'low_stock_count' => $pdo->query("SELECT COUNT(*) FROM products WHERE stock_quantity <= min_stock_threshold")->fetchColumn() ?: 0,
    'total_debt' => $pdo->query("SELECT SUM(total_debt) FROM clients")->fetchColumn() ?: 0,
];
$recent_sales = $pdo->query("SELECT s.*, c.name as client_name FROM sales s LEFT JOIN clients c ON s.client_id = c.id ORDER BY s.created_at DESC LIMIT 6")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <title>Tableau de bord - QuincaTech</title>
    <?php include 'includes/head.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<div class="app-layout">
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <!-- Header -->
        <header class="page-header">
            <div>
                <h1>Bonjour, <?= $_SESSION['username'] ?> 👋</h1>
                <p>Voici ce qui se passe aujourd'hui.</p>
            </div>
            <div class="header-actions">
                <a href="sales.php" class="btn btn-primary btn-lg">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Nouvelle Vente
                </a>
            </div>
        </header>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon stat-icon-green">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <p class="stat-label">Ventes du jour</p>
                    <h3 class="stat-value"><?= format_price($stats['total_sales']) ?></h3>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon stat-icon-blue">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                </div>
                <div>
                    <p class="stat-label">Valeur Stock</p>
                    <h3 class="stat-value"><?= format_price($stats['total_stock_value']) ?></h3>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon stat-icon-red">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                </div>
                <div>
                    <p class="stat-label">Dettes Clients</p>
                    <h3 class="stat-value"><?= format_price($stats['total_debt']) ?></h3>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon <?= $stats['low_stock_count'] > 0 ? 'stat-icon-orange' : 'stat-icon-dark' ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <p class="stat-label">Alertes Stock</p>
                    <h3 class="stat-value"><?= $stats['low_stock_count'] ?> Produit<?= $stats['low_stock_count'] > 1 ? 's' : '' ?></h3>
                    <?php if ($stats['low_stock_count'] > 0): ?>
                        <p class="stat-sub" style="color: var(--color-warning);">Seuil atteint !</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Charts & Activity -->
        <div class="dashboard-grid">
            <div class="card chart-card">
                <div class="chart-header">
                    <h3>Évolution des ventes (7 jours)</h3>
                    <select class="chart-select"></select>
                </div>
                <canvas id="salesChart" height="200"></canvas>
            </div>

            <div class="card card-padded">
                <h3 class="section-title">Activités Récentes</h3>
                <div style="display:flex; flex-direction:column; gap:1.25rem;">
                    <?php if (empty($recent_sales)): ?>
                        <div class="table-empty">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                            <p>Aucune vente enregistrée</p>
                        </div>
                    <?php else: ?>
                        <?php foreach($recent_sales as $s): ?>
                        <div class="recent-activity-item">
                            <div class="activity-icon">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                            </div>
                            <div class="activity-info">
                                <p class="activity-name"><?= $s['client_name'] ?: 'Client comptant' ?></p>
                                <p class="activity-time"><?= date('H:i', strtotime($s['created_at'])) ?></p>
                            </div>
                            <span class="activity-amount">+<?= format_price($s['total_amount']) ?></span>
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
    const ctx = document.getElementById('salesChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'],
            datasets: [{
                label: 'Ventes (FCFA)',
                data: [12000, 19000, 15000, 25000, 22000, 30000, 15000],
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
