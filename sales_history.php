<?php
require_once 'config/db.php';
require_once 'includes/helpers.php';
check_auth();

$sales = $pdo->query("SELECT s.*, c.name as client_name, u.username as seller FROM sales s LEFT JOIN clients c ON s.client_id=c.id LEFT JOIN users u ON s.user_id=u.id ORDER BY s.created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <title>Historique des Ventes - LxTronic</title>
    <?php include 'includes/head.php'; ?>
</head>
<body>
<div class="app-layout">
    <?php include 'includes/sidebar.php'; ?>
    <main class="main-content">
        <header class="page-header">
            <div>
                <h1>Historique des Ventes</h1>
                <p>Toutes les transactions passées — <?= count($sales) ?> vente(s)</p>
            </div>
            <a href="sales.php" class="btn btn-primary btn-lg">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Nouvelle Vente
            </a>
        </header>

        <div class="card">
            <div class="data-table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Date & Heure</th>
                            <th>Client</th>
                            <th>Vendeur</th>
                            <th>Paiement</th>
                            <th>Total</th>
                            <th>Facture</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($sales)): ?>
                            <tr><td colspan="7" class="table-empty">Aucune vente enregistrée.</td></tr>
                        <?php else: ?>
                            <?php foreach($sales as $s): ?>
                            <tr>
                                <td class="text-muted font-bold">#<?= $s['id'] ?></td>
                                <td class="text-muted"><?= date('d/m/Y H:i',strtotime($s['created_at'])) ?></td>
                                <td class="col-name"><?= htmlspecialchars($s['client_name']?:'Client de passage') ?></td>
                                <td class="text-muted"><?= htmlspecialchars($s['seller']) ?></td>
                                <td><span class="badge <?= $s['payment_type']==='credit'?'badge-red':'badge-green' ?>"><?= $s['payment_type'] ?></span></td>
                                <td class="col-amount"><?= format_price($s['total_amount'], $s['currency']) ?></td>
                                <td style="text-align:right;">
                                    <a href="invoice.php?id=<?= $s['id'] ?>" class="btn btn-ghost" style="padding:6px 14px;font-size:0.8rem;border-radius:10px;">
                                        Voir Facture
                                    </a>
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
