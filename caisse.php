<?php
require_once 'config/db.php';
require_once 'includes/helpers.php';
check_auth();

// Date du jour par défaut
$date_filter = $_GET['date'] ?? date('Y-m-d');
$usd_to_cdf = get_setting('usd_to_cdf', 2800);

// --- COMPTE USD ---
// 1. Ventes au comptant USD
$stmt = $pdo->prepare("SELECT SUM(total_amount) FROM sales WHERE DATE(created_at) = ? AND payment_type = 'comptant' AND currency = 'USD'");
$stmt->execute([$date_filter]);
$cash_sales_usd = $stmt->fetchColumn() ?: 0;

// 2. Paiements de dettes USD
$stmt = $pdo->prepare("SELECT SUM(amount_paid) FROM payments WHERE DATE(payment_date) = ? AND currency = 'USD'");
$stmt->execute([$date_filter]);
$debt_payments_usd = $stmt->fetchColumn() ?: 0;

// 3. Dépenses USD
$stmt = $pdo->prepare("SELECT SUM(amount) FROM expenses WHERE expense_date = ? AND currency = 'USD'");
$stmt->execute([$date_filter]);
$expenses_usd = $stmt->fetchColumn() ?: 0;

$net_usd = ($cash_sales_usd + $debt_payments_usd) - $expenses_usd;

// --- COMPTE CDF ---
// 1. Ventes au comptant CDF
$stmt = $pdo->prepare("SELECT SUM(total_amount) FROM sales WHERE DATE(created_at) = ? AND payment_type = 'comptant' AND currency = 'CDF'");
$stmt->execute([$date_filter]);
$cash_sales_cdf = $stmt->fetchColumn() ?: 0;

// 2. Paiements de dettes CDF
$stmt = $pdo->prepare("SELECT SUM(amount_paid) FROM payments WHERE DATE(payment_date) = ? AND currency = 'CDF'");
$stmt->execute([$date_filter]);
$debt_payments_cdf = $stmt->fetchColumn() ?: 0;

// 3. Dépenses CDF
$stmt = $pdo->prepare("SELECT SUM(amount) FROM expenses WHERE expense_date = ? AND currency = 'CDF'");
$stmt->execute([$date_filter]);
$expenses_cdf = $stmt->fetchColumn() ?: 0;

$net_cdf = ($cash_sales_cdf + $debt_payments_cdf) - $expenses_cdf;

// Logs cumulés
$transactions = $pdo->prepare("
    (SELECT 'Vente' as type, total_amount as amount, currency, created_at as date, 'Entrée' as category 
     FROM sales WHERE DATE(created_at) = ? AND payment_type = 'comptant')
    UNION ALL
    (SELECT 'Paiement Dette' as type, amount_paid as amount, currency, payment_date as date, 'Entrée' as category 
     FROM payments WHERE DATE(payment_date) = ?)
    UNION ALL
    (SELECT title as type, amount, currency, created_at as date, 'Sortie' as category 
     FROM expenses WHERE expense_date = ?)
    ORDER BY date DESC
");
$transactions->execute([$date_filter, $date_filter, $date_filter]);
$logs = $transactions->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <title>Caisse Multi-Devise - LxTronic</title>
    <?php include 'includes/head.php'; ?>
    <style>
        .caisse-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem; }
        .caisse-box { padding: 1.5rem; border-radius: var(--radius-2xl); border: 1px solid var(--color-slate-100); background: white; }
        .caisse-box h2 { font-size: 1rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--color-slate-500); margin-bottom: 1rem; border-bottom: 1px solid var(--color-slate-50); padding-bottom: 0.5rem; }
        .caisse-row { display: flex; justify-content: space-between; margin-bottom: 0.5rem; font-size: 0.95rem; }
        .caisse-total { font-size: 1.5rem; font-weight: 800; margin-top: 1rem; padding-top: 1rem; border-top: 2px dashed var(--color-slate-100); }
    </style>
</head>
<body>
<div class="app-layout">
    <?php include 'includes/sidebar.php'; ?>
    <?php include 'includes/responsive_header.php'; ?>

    <main class="main-content">
        <header class="page-header">
            <div>
                <h1>Ma Caisse Multi-Devise</h1>
                <p>Suivi séparé USD et CDF pour le <strong><?= date('d/m/Y', strtotime($date_filter)) ?></strong></p>
            </div>
            <form action="" method="GET" class="header-actions">
                <input type="date" name="date" value="<?= $date_filter ?>" class="form-control" onchange="this.form.submit()">
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
                            <th>Heure</th>
                            <th>Description</th>
                            <th>Mouvement</th>
                            <th style="text-align: right;">Montant</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                            <tr><td colspan="4" class="table-empty">Aucun mouvement aujourd'hui.</td></tr>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                            <tr>
                                <td class="text-muted"><?= date('H:i', strtotime($log['date'])) ?></td>
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
