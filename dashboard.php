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

// Total Caisse (Total Cash - Toutes dates)
$total_cash_sales_usd = $pdo->query("SELECT SUM(total_amount) FROM sales WHERE payment_type = 'comptant' AND currency = 'USD'")->fetchColumn() ?: 0;
$total_debt_payments_usd = $pdo->query("SELECT SUM(amount_paid) FROM payments WHERE currency = 'USD'")->fetchColumn() ?: 0;
$total_expenses_usd = $pdo->query("SELECT SUM(amount) FROM expenses WHERE currency = 'USD'")->fetchColumn() ?: 0;
$total_caisse_usd = ($total_cash_sales_usd + $total_debt_payments_usd) - $total_expenses_usd;

$total_cash_sales_cdf = $pdo->query("SELECT SUM(total_amount) FROM sales WHERE payment_type = 'comptant' AND currency = 'CDF'")->fetchColumn() ?: 0;
$total_debt_payments_cdf = $pdo->query("SELECT SUM(amount_paid) FROM payments WHERE currency = 'CDF'")->fetchColumn() ?: 0;
$total_expenses_cdf = $pdo->query("SELECT SUM(amount) FROM expenses WHERE currency = 'CDF'")->fetchColumn() ?: 0;
$total_caisse_cdf = ($total_cash_sales_cdf + $total_debt_payments_cdf) - $total_expenses_cdf;

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
        /* Toggle visibility styles */
        .visibility-toggle {
            cursor: pointer;
            padding: 4px;
            border-radius: 6px;
            color: var(--color-slate-400);
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
        }
        .visibility-toggle:hover { color: var(--color-primary); background: var(--color-slate-100); }
        .amount-hidden { filter: blur(5px); pointer-events: none; user-select: none; }
        .stat-header { display: flex; justify-content: space-between; align-items: flex-start; width: 100%; }
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
                <div class="stat-multi" style="width:100%;">
                    <div class="stat-header">
                        <p class="stat-label">Ventes du jour</p>
                        <span class="visibility-toggle" onclick="toggleDashboardAmounts()" title="Masquer/Afficher les montants">
                            <svg id="eye-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width:18px;height:18px;"><path id="eye-path" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path id="eye-outer-path" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        </span>
                    </div>
                    <h3 class="stat-value balance-value"><?= format_price($sales_usd, 'USD') ?></h3>
                    <p class="stat-sub-val balance-value"><?= format_price($sales_cdf, 'CDF') ?></p>
                </div>
            </div>

            <!-- Total Caisse (Added) -->
            <div class="stat-card">
                <div class="stat-icon" style="background:#fef3c7; color:#d97706;">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                </div>
                <div class="stat-multi">
                    <p class="stat-label">Ma Caisse (Total)</p>
                    <h3 class="stat-value balance-value"><?= format_price($total_caisse_usd, 'USD') ?></h3>
                    <p class="stat-sub-val balance-value"><?= format_price($total_caisse_cdf, 'CDF') ?></p>
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
                    <span class="activity-amount balance-value" style="color: <?= $s['status'] == 'dette' ? 'var(--color-danger)' : 'var(--color-success)' ?>;">
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

    // Dashboard Visibility Toggle
    function toggleDashboardAmounts() {
        const balances = document.querySelectorAll('.balance-value');
        const eyeIconPath = document.getElementById('eye-path');
        const eyeOuterPath = document.getElementById('eye-outer-path');
        const isHidden = balances[0].classList.toggle('amount-hidden');
        
        // Propagate to all balance-value elements
        balances.forEach(el => {
            if (isHidden) el.classList.add('amount-hidden');
            else el.classList.remove('amount-hidden');
        });

        // Update icon
        if (isHidden) {
            // Crossed eye
            eyeIconPath.setAttribute('d', 'M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18');
            eyeOuterPath.style.display = 'none';
        } else {
            // Normal eye
            eyeIconPath.setAttribute('d', 'M15 12a3 3 0 11-6 0 3 3 0 016 0z');
            eyeOuterPath.setAttribute('d', 'M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z');
            eyeOuterPath.style.display = 'block';
        }
        
        // Store preference
        localStorage.setItem('dashboard_amounts_hidden', isHidden);
    }

    // Restore preference on load
    window.addEventListener('DOMContentLoaded', () => {
        if (localStorage.getItem('dashboard_amounts_hidden') === 'true') {
            toggleDashboardAmounts();
        }
    });
</script>
</body>
</html>
