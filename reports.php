<?php
require_once 'config/db.php';
require_once 'includes/helpers.php';
require_admin();

$usd_to_cdf = (float)get_setting('usd_to_cdf', 2800);
$filter = $_GET['filter'] ?? 'daily';

if ($filter === 'monthly') {
    $date_query = "MONTH(s.created_at)=MONTH(CURDATE()) AND YEAR(s.created_at)=YEAR(CURDATE())";
    $exp_date_query = "MONTH(expense_date)=MONTH(CURDATE()) AND YEAR(expense_date)=YEAR(CURDATE())";
    $label = "ce mois-ci";
} elseif ($filter === 'yearly') {
    $date_query = "YEAR(s.created_at)=YEAR(CURDATE())";
    $exp_date_query = "YEAR(expense_date)=YEAR(CURDATE())";
    $label = "cette année";
} else {
    $date_query = "DATE(s.created_at)=CURDATE()";
    $exp_date_query = "expense_date=CURDATE()";
    $label = "aujourd'hui";
}

// 1. Ventes et Marge (tout converti en USD-Equivalent pour le rapport consolidé)
$stats_query = "
    SELECT 
        SUM(CASE WHEN currency = 'USD' THEN total_amount ELSE total_amount / exchange_rate END) as sales_usd,
        SUM(CASE WHEN currency = 'USD' THEN total_amount * exchange_rate ELSE total_amount END) as sales_cdf
    FROM sales s 
    WHERE $date_query
";
$stats = $pdo->query($stats_query)->fetch();

// Profit Brut (USD) = (Prix Vente USD - Prix Achat USD) * Qté
$profit_query = "
    SELECT 
        SUM(si.quantity * (
            CASE 
                WHEN s.currency = 'USD' THEN si.unit_price 
                ELSE si.unit_price / s.exchange_rate 
            END - p.buy_price
        )) as profit_usd
    FROM sale_items si 
    JOIN sales s ON si.sale_id = s.id 
    JOIN products p ON si.product_id = p.id 
    WHERE $date_query
";
$profit_usd = $pdo->query($profit_query)->fetchColumn() ?: 0;

// 2. Dépenses (Consolidées en USD)
$expenses_query = "
    SELECT 
        SUM(CASE WHEN currency = 'USD' THEN amount ELSE amount / $usd_to_cdf END) as total_expenses_usd
    FROM expenses 
    WHERE $exp_date_query
";
$total_expenses_usd = $pdo->query($expenses_query)->fetchColumn() ?: 0;

$net_profit_usd = $profit_usd - $total_expenses_usd;

// 3. Situation Globale
$total_debt_usd = $pdo->query("SELECT SUM(total_debt_usd) + SUM(total_debt_cdf / $usd_to_cdf) FROM clients")->fetchColumn() ?: 0;
$total_stock_value_usd = $pdo->query("SELECT SUM(stock_quantity * buy_price) FROM products")->fetchColumn() ?: 0;

$top_products = $pdo->query("SELECT p.name, SUM(si.quantity) as qty FROM sale_items si JOIN products p ON si.product_id=p.id JOIN sales s ON si.sale_id=s.id WHERE $date_query GROUP BY p.id ORDER BY qty DESC LIMIT 5")->fetchAll();
$history = $pdo->query("SELECT s.*, c.name as client_name, u.username as seller FROM sales s LEFT JOIN clients c ON s.client_id=c.id LEFT JOIN users u ON s.user_id=u.id WHERE $date_query ORDER BY s.created_at DESC")->fetchAll();
$max_qty = !empty($top_products) ? max(array_column($top_products,'qty')) : 1;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <title>Rapports - LXTRONIC</title>
    <?php include 'includes/head.php'; ?>
</head>
<body>
<div class="app-layout">
    <?php include 'includes/sidebar.php'; ?>
    <?php include 'includes/responsive_header.php'; ?>

    <main class="main-content">
        <header class="page-header">
            <div>
                <h1>Rapports & Statistiques</h1>
                <p>Analyse consolidée en **USD** (Équivalent) — <strong><?= $label ?></strong></p>
            </div>
            <div class="filter-bar">
                <a href="?filter=daily" class="filter-btn <?= $filter==='daily'?'active':'' ?>">Journalier</a>
                <a href="?filter=monthly" class="filter-btn <?= $filter==='monthly'?'active':'' ?>">Mensuel</a>
                <a href="?filter=yearly" class="filter-btn <?= $filter==='yearly'?'active':'' ?>">Annuel</a>
            </div>
        </header>

        <div class="stats-grid reports-stats">
            <div class="stat-card">
                <div class="stat-icon stat-icon-green">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <p class="stat-label">Ventes (Éq. USD)</p>
                    <h3 class="stat-value" style="font-size:1.2rem;"><?= format_price($stats['sales_usd']??0, 'USD') ?></h3>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon stat-icon-blue">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                </div>
                <div>
                    <p class="stat-label">Marge Brute (USD)</p>
                    <h3 class="stat-value" style="font-size:1.2rem;color:var(--color-primary);"><?= format_price($profit_usd, 'USD') ?></h3>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon stat-icon-red">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <p class="stat-label">Dépenses (Éq. USD)</p>
                    <h3 class="stat-value" style="font-size:1.2rem;color:var(--color-danger);"><?= format_price($total_expenses_usd, 'USD') ?></h3>
                </div>
            </div>
            <div class="stat-card" style="background:var(--color-slate-900);border-color:var(--color-slate-800);">
                <div class="stat-icon" style="background:rgba(255,255,255,0.1);color:<?= $net_profit_usd>=0?'#4ade80':'#f87171' ?>;">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                </div>
                <div>
                    <p class="stat-label" style="color:var(--color-slate-400);">Bénéfice Net (USD)</p>
                    <h3 class="stat-value" style="font-size:1.2rem;color:<?= $net_profit_usd>=0?'#4ade80':'#f87171' ?>;"><?= format_price($net_profit_usd, 'USD') ?></h3>
                </div>
            </div>
        </div>

        <div class="dashboard-grid">
            <div class="card">
                <div style="padding:1.5rem 1.5rem 0; margin-bottom:0;">
                    <h3 class="section-title">Détail des transactions</h3>
                </div>
                <div class="data-table-wrapper table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr><th>ID</th><th>Client</th><th>Vendeur</th><th>Paiement</th><th>Montant</th></tr>
                        </thead>
                        <tbody>
                            <?php if(empty($history)): ?>
                                <tr><td colspan="5" class="table-empty">Aucune transaction.</td></tr>
                            <?php else: ?>
                                <?php foreach($history as $h): ?>
                                <tr>
                                    <td class="text-muted font-bold">#<?= $h['id'] ?></td>
                                    <td class="col-name"><?= $h['client_name']?:'Passage' ?></td>
                                    <td class="text-muted"><?= $h['seller'] ?></td>
                                    <td><span class="badge <?= $h['payment_type']==='credit'?'badge-red':'badge-green' ?>"><?= $h['payment_type'] ?></span></td>
                                    <td class="col-amount"><?= format_price($h['total_amount'], $h['currency']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card card-padded table-responsive">
                <h3 class="section-title">Produits les plus vendus</h3>
                <h3 class="section-title">Performance Globale</h3>
                <div>
                    <p class="stat-label" style="margin-bottom:8px;">Dettes clients (Total Éq. USD)</p>
                    <p class="font-bold text-danger" style="font-size:1.2rem;"><?= format_price($total_debt_usd, 'USD') ?></p>
                </div>
                <hr class="divider">
                <div>
                    <p class="stat-label" style="margin-bottom:8px;">Valeur du stock (Achat USD)</p>
                    <p class="font-bold" style="font-size:1.2rem;color:var(--color-slate-700);"><?= format_price($total_stock_value_usd, 'USD') ?></p>
                </div>
                
                <hr class="divider">
                <h3 class="section-title" style="margin-top:1rem;">Top 5 Produits</h3>
                <div style="display:flex;flex-direction:column;gap:1.5rem;">
                    <?php foreach($top_products as $i => $tp): ?>
                    <div class="top-product">
                        <div class="top-product-rank"><?= $i+1 ?></div>
                        <div class="top-product-info">
                            <p class="top-product-name"><?= htmlspecialchars($tp['name']) ?></p>
                            <div class="top-product-bar"><div class="top-product-fill" style="width:<?= round($tp['qty']/$max_qty*100) ?>%"></div></div>
                        </div>
                        <span class="top-product-qty"><?= $tp['qty'] ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </main>
</div>
</body>
</html>
