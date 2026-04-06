<?php
require_once 'config/db.php';
require_once 'includes/helpers.php';
check_auth();

// Filtres (Mois et Année)
$filter_month = $_GET['month'] ?? date('m');
$filter_year  = $_GET['year']  ?? date('Y');
$usd_to_cdf = get_setting('usd_to_cdf', 2800);

// --- COMPTE USD ---
// 1. Ventes au comptant USD
$stmt = $pdo->prepare("SELECT SUM(total_amount) FROM sales WHERE MONTH(created_at) = ? AND YEAR(created_at) = ? AND payment_type = 'comptant' AND currency = 'USD'");
$stmt->execute([$filter_month, $filter_year]);
$cash_sales_usd = $stmt->fetchColumn() ?: 0;

// 2. Paiements de dettes USD
$stmt = $pdo->prepare("SELECT SUM(amount_paid) FROM payments WHERE MONTH(payment_date) = ? AND YEAR(payment_date) = ? AND currency = 'USD'");
$stmt->execute([$filter_month, $filter_year]);
$debt_payments_usd = $stmt->fetchColumn() ?: 0;

// 3. Dépenses USD
$stmt = $pdo->prepare("SELECT SUM(amount) FROM expenses WHERE MONTH(expense_date) = ? AND YEAR(expense_date) = ? AND currency = 'USD'");
$stmt->execute([$filter_month, $filter_year]);
$expenses_usd = $stmt->fetchColumn() ?: 0;

$net_usd = ($cash_sales_usd + $debt_payments_usd) - $expenses_usd;

// --- COMPTE CDF ---
// 1. Ventes au comptant CDF
$stmt = $pdo->prepare("SELECT SUM(total_amount) FROM sales WHERE MONTH(created_at) = ? AND YEAR(created_at) = ? AND payment_type = 'comptant' AND currency = 'CDF'");
$stmt->execute([$filter_month, $filter_year]);
$cash_sales_cdf = $stmt->fetchColumn() ?: 0;

// 2. Paiements de dettes CDF
$stmt = $pdo->prepare("SELECT SUM(amount_paid) FROM payments WHERE MONTH(payment_date) = ? AND YEAR(payment_date) = ? AND currency = 'CDF'");
$stmt->execute([$filter_month, $filter_year]);
$debt_payments_cdf = $stmt->fetchColumn() ?: 0;

// 3. Dépenses CDF
$stmt = $pdo->prepare("SELECT SUM(amount) FROM expenses WHERE MONTH(expense_date) = ? AND YEAR(expense_date) = ? AND currency = 'CDF'");
$stmt->execute([$filter_month, $filter_year]);
$expenses_cdf = $stmt->fetchColumn() ?: 0;

$net_cdf = ($cash_sales_cdf + $debt_payments_cdf) - $expenses_cdf;

// Logs cumulés
$transactions = $pdo->prepare("
    (SELECT 'Vente' as type, total_amount as amount, currency, created_at as date, 'Entrée' as category 
     FROM sales WHERE MONTH(created_at) = ? AND YEAR(created_at) = ? AND payment_type = 'comptant')
    UNION ALL
    (SELECT 'Paiement Dette' as type, amount_paid as amount, currency, payment_date as date, 'Entrée' as category 
     FROM payments WHERE MONTH(payment_date) = ? AND YEAR(payment_date) = ?)
    UNION ALL
    (SELECT title as type, amount, currency, created_at as date, 'Sortie' as category 
     FROM expenses WHERE MONTH(expense_date) = ? AND YEAR(expense_date) = ?)
    ORDER BY date DESC
");
$transactions->execute([$filter_month, $filter_year, $filter_month, $filter_year, $filter_month, $filter_year]);
$logs = $transactions->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <title>Caisse Multi-Devise - LXTRONIC</title>
    <?php include 'includes/head.php'; ?>
</head>
<body>
<div class="app-layout">
    <?php include 'includes/sidebar.php'; ?>
    <?php include 'includes/responsive_header.php'; ?>

    <main class="main-content">
<?php
$months = [
    '01'=>'Janvier', '02'=>'Février', '03'=>'Mars', '04'=>'Avril',
    '05'=>'Mai', '06'=>'Juin', '07'=>'Juillet', '08'=>'Août',
    '09'=>'Septembre', '10'=>'Octobre', '11'=>'Novembre', '12'=>'Décembre'
];
?>
        <header class="page-header">
            <div>
                <h1>Ma Caisse Multi-Devise</h1>
                <p>Suivi séparé USD et CDF pour <strong><?= $months[sprintf('%02d', $filter_month)] ?> <?= $filter_year ?></strong></p>
            </div>
            <form action="" method="GET" class="header-actions" style="display: flex; gap: 10px;">
                <select name="month" class="form-control" onchange="this.form.submit()">
                    <?php foreach($months as $num => $name): ?>
                        <option value="<?= $num ?>" <?= $filter_month == $num ? 'selected' : '' ?>><?= $name ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="year" class="form-control" onchange="this.form.submit()">
                    <?php for($y = 2024; $y <= date('Y') + 2; $y++): ?>
                        <option value="<?= $y ?>" <?= $filter_year == $y ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </form>
        </header>

        <div class="caisse-grid">
            <!-- Compte USD -->
            <div class="caisse-box" style="border-left: 5px solid #3b82f6;">
                <h2>Compte USD (Dollar)</h2>
                <div class="caisse-row">
                    <span>Ventes Cash :</span>
                    <span class="text-success"><?= format_price($cash_sales_usd, 'USD') ?></span>
                </div>
                <div class="caisse-row">
                    <span>Recouvrement :</span>
                    <span class="text-success"><?= format_price($debt_payments_usd, 'USD') ?></span>
                </div>
                <div class="caisse-row">
                    <span>Dépenses :</span>
                    <span class="text-danger"><?= format_price($expenses_usd, 'USD') ?></span>
                </div>
                <div class="caisse-total" style="color: #1e40af;">
                    <?= format_price($net_usd, 'USD') ?>
                </div>
            </div>

            <!-- Compte CDF -->
            <div class="caisse-box" style="border-left: 5px solid #10b981;">
                <h2>Compte CDF (Franc Congolais)</h2>
                <div class="caisse-row">
                    <span>Ventes Cash :</span>
                    <span class="text-success"><?= format_price($cash_sales_cdf, 'CDF') ?></span>
                </div>
                <div class="caisse-row">
                    <span>Recouvrement :</span>
                    <span class="text-success"><?= format_price($debt_payments_cdf, 'CDF') ?></span>
                </div>
                <div class="caisse-row">
                    <span>Dépenses :</span>
                    <span class="text-danger"><?= format_price($expenses_cdf, 'CDF') ?></span>
                </div>
                <div class="caisse-total" style="color: #065f46;">
                    <?= format_price($net_cdf, 'CDF') ?>
                </div>
            </div>
        </div>

        <!-- Journal cumulé -->
        <div class="card">
            <div style="padding: 1.5rem 1.75rem; border-bottom: 1px solid var(--color-slate-100);">
                <h3 class="section-title">Journal des transactions (Toutes devises)</h3>
            </div>
            <div class="data-table-wrapper table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date / Heure</th>
                            <th>Description</th>
                            <th>Mouvement</th>
                            <th style="text-align: right;">Montant</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                            <tr><td colspan="4" class="table-empty">Aucun mouvement pour ce mois.</td></tr>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                            <tr>
                                <td class="text-muted"><?= date('d/m à H:i', strtotime($log['date'])) ?></td>
                                <td class="col-name"><?= htmlspecialchars($log['type']) ?></td>
                                <td>
                                    <span class="badge <?= $log['category'] == 'Entrée' ? 'badge-green' : 'badge-red' ?>">
                                        <?= $log['category'] ?> (<?= $log['currency'] ?>)
                                    </span>
                                </td>
                                <td style="text-align: right; font-weight: 700; color: <?= $log['category'] == 'Entrée' ? 'var(--color-success)' : 'var(--color-danger)' ?>;">
                                    <?= $log['category'] == 'Entrée' ? '+' : '−' ?> <?= format_price($log['amount'], $log['currency']) ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>
</body>
</html>
