<?php
require_once 'config/db.php';
require_once 'includes/helpers.php';
check_auth();

$sale_id = $_GET['id'] ?? null;
if (!$sale_id) die("Vente non spécifiée.");

$stmt = $pdo->prepare("SELECT s.*, c.name as client_name, c.address as client_address, c.phone as client_phone, u.username as seller_name FROM sales s LEFT JOIN clients c ON s.client_id=c.id LEFT JOIN users u ON s.user_id=u.id WHERE s.id=?");
$stmt->execute([$sale_id]);
$sale = $stmt->fetch();
if (!$sale) die("Vente introuvable.");

$stmt = $pdo->prepare("SELECT si.*, p.name as product_name FROM sale_items si JOIN products p ON si.product_id=p.id WHERE si.sale_id=?");
$stmt->execute([$sale_id]);
$items = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <title>Facture #<?= $sale_id ?> - LxTronic</title>
    <?php include 'includes/head.php'; ?>
    <link href="https://fonts.googleapis.com/css2?family=Courier+Prime&display=swap" rel="stylesheet">
</head>
<body>
<div class="invoice-wrapper">
    <div class="invoice-actions no-print">
        <a href="sales_history.php" class="btn btn-ghost">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Retour
        </a>
        <button onclick="window.print()" class="btn btn-primary btn-lg" style="flex:1;">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
            Imprimer la Facture
        </button>
    </div>

    <div class="invoice-paper">
        <div class="invoice-header">
            <div class="invoice-brand">
                <img src="logo.jpg" alt="Logo" style="height:60px;object-fit:contain;margin-bottom:8px;display:block;">
                <p style="font-family:var(--font-main);color:var(--color-slate-400);font-size:0.8rem;">Vente de matériel de quincaillerie de haute qualité.</p>
            </div>
            <div class="invoice-meta">
                <h2>FACTURE</h2>
                <p>#VQ-<?= str_pad($sale_id, 5, '0', STR_PAD_LEFT) ?></p>
            </div>
        </div>

        <div class="invoice-parties">
            <div>
                <p class="party-label">Émis par</p>
                <p class="party-name"><?= htmlspecialchars($sale['seller_name']) ?></p>
                <p class="party-detail"><?= date('d F Y \à H:i', strtotime($sale['created_at'])) ?></p>
            </div>
            <div style="text-align:right;">
                <p class="party-label">Facturé à</p>
                <p class="party-name"><?= $sale['client_name'] ? htmlspecialchars($sale['client_name']) : 'Client de passage' ?></p>
                <p class="party-detail"><?= htmlspecialchars($sale['client_phone'] ?? '') ?></p>
                <p class="party-detail"><?= htmlspecialchars($sale['client_address'] ?? '') ?></p>
            </div>
        </div>

        <table class="invoice-table">
            <thead>
                <tr>
                    <th>Désignation</th>
                    <th style="text-align:center;">Qté</th>
                    <th style="text-align:right;">Prix Unit.</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($items as $it): ?>
                <tr>
                    <td><?= htmlspecialchars($it['product_name']) ?></td>
                    <td style="text-align:center;"><?= $it['quantity'] ?></td>
                    <td style="text-align:right;"><?= format_price($it['unit_price'], $sale['currency']) ?></td>
                    <td style="font-weight:700;"><?= format_price($it['subtotal'], $sale['currency']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="invoice-totals">
            <div class="invoice-totals-table">
                <div class="invoice-totals-row">
                    <span>Sous-total HT</span>
                    <span><?= format_price($sale['total_amount'] * 0.82, $sale['currency']) ?></span>
                </div>
                <div class="invoice-totals-row">
                    <span>TVA (18%)</span>
                    <span><?= format_price($sale['total_amount'] * 0.18, $sale['currency']) ?></span>
                </div>
                <div class="invoice-totals-final">
                    <span>NET À PAYER</span>
                    <span><?= format_price($sale['total_amount'], $sale['currency']) ?></span>
                </div>
                <div style="margin-top:1rem; text-align:right;">
                    <span class="badge badge-slate" style="font-size:11px;letter-spacing:0.08em;">
                        Payé par : <?= strtoupper($sale['payment_type']) ?>
                    </span>
                </div>
            </div>
        </div>

        <div class="invoice-footer">
            Merci de votre confiance. — LxTronic
        </div>
    </div>
</div>
</body>
</html>
