<?php
require_once 'config/db.php';
require_once 'includes/helpers.php';
check_auth();

// Fetch all sales directly
$query = "SELECT s.*, c.name as client_name, u.username as seller_name 
          FROM sales s 
          LEFT JOIN clients c ON s.client_id = c.id 
          LEFT JOIN users u ON s.user_id = u.id 
          ORDER BY s.created_at DESC";
$sales = $pdo->query($query)->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <title>Historique des Ventes - LXTRONIC</title>
    <?php include 'includes/head.php'; ?>
    <style>
        .status-badge { font-size: 10px; padding: 4px 10px; border-radius: var(--radius-full); font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; }
        .status-comptant { background: var(--color-success-light); color: var(--color-success); border: 1px solid #bbf7d0; }
        .status-dette { background: var(--color-danger-light); color: var(--color-danger); border: 1px solid #fecaca; }
    </style>
</head>
<body>
<div class="app-layout">
    <?php include 'includes/sidebar.php'; ?>
    <?php include 'includes/responsive_header.php'; ?>

    <main class="main-content">
        <header class="page-header">
            <div>
                <h1>Historique des Ventes</h1>
                <p>Retrouvez toutes les transactions passées.</p>
            </div>
            <div class="header-actions">
                <a href="sales.php" class="btn btn-primary btn-lg">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Nouvelle Vente
                </a>
            </div>
        </header>

        <div class="card card-padded">
            <div class="data-table-wrapper table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date & Heure</th>
                            <th>Référence</th>
                            <th>Client</th>
                            <th>Vendeur</th>
                            <th>Statut</th>
                            <th style="text-align: right;">Total</th>
                            <th style="text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($sales)): ?>
                            <tr><td colspan="7" class="table-empty">Aucune vente enregistrée.</td></tr>
                        <?php else: ?>
                            <?php foreach ($sales as $s): ?>
                                <tr>
                                    <td class="text-muted"><?= date('d/m/Y H:i', strtotime($s['created_at'])) ?></td>
                                    <td class="col-name">#VQ-<?= str_pad($s['id'], 4, '0', STR_PAD_LEFT) ?></td>
                                    <td>
                                        <?php if ($s['client_name']): ?>
                                            <span style="font-weight: 600; color: var(--color-slate-800);"><?= htmlspecialchars($s['client_name']) ?></span>
                                        <?php else: ?>
                                            <span style="color: var(--color-slate-400); font-style: italic;">Client au comptant</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 6px;">
                                            <div style="width: 24px; height: 24px; border-radius: 6px; background: var(--color-slate-100); color: var(--color-slate-600); display: flex; align-items: center; justify-content: center; font-size: 10px; font-weight: 800;">
                                                <?= strtoupper(substr($s['seller_name'] ?? 'U', 0, 1)) ?>
                                            </div>
                                            <?= htmlspecialchars($s['seller_name'] ?? 'Inconnu') ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="status-badge <?= strtolower($s['payment_type'] ?? '') == 'dette' || strtolower($s['status'] ?? '') == 'dette' ? 'status-dette' : 'status-comptant' ?>">
                                            <?= strtoupper($s['payment_type'] ?? ($s['status'] ?? 'COMPTANT')) ?>
                                        </span>
                                    </td>
                                    <td class="col-amount" style="text-align: right;">
                                        <?= format_price($s['total_amount'], $s['currency']) ?>
                                    </td>
                                    <td style="text-align: right;">
                                        <div class="row-actions">
                                            <a href="invoice.php?id=<?= $s['id'] ?>" class="btn-icon btn-icon-primary" title="Voir Facture">
                                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                            </a>
                                        </div>
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
