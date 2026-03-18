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
    <title>Facture #<?= $sale_id ?> - LXTRONIC</title>
    <?php include 'includes/head.php'; ?>
    <link href="https://fonts.googleapis.com/css2?family=Courier+Prime&display=swap" rel="stylesheet">
    <style>
        :root {
            --invoice-w: 105mm;
            --invoice-h: 148mm;
        }
        
        @media print {
            @page { size: A6; margin: 0; }
            body { background: white; padding: 0; margin: 0; transform: scale(1); }
            .no-print { display: none !important; }
            .invoice-wrapper { padding: 0; background: white; min-height: auto; }
            .invoice-paper { 
                width: var(--invoice-w); 
                height: var(--invoice-h);
                max-width: none;
                box-shadow: none; 
                border-radius: 0; 
                border-top: none; 
                padding: 10mm 8mm;
                position: relative;
            }
        }

        .invoice-paper {
            width: 100%;
            max-width: 480px; /* Preview width */
            padding: 2rem;
            font-size: 13px;
        }

        .invoice-header { margin-bottom: 1rem; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; padding-bottom: 0.5rem; }
        .invoice-brand img { height: 75px !important; max-width: 180px; object-fit: contain; }
        .invoice-meta h2 { font-size: 1.2rem; margin: 0; }
        .invoice-meta p { font-size: 0.8rem; margin: 0; color: #64748b; }

        .invoice-parties { 
            display: grid; 
            grid-template-columns: 1fr 1fr; 
            gap: 1rem; 
            margin-bottom: 1rem; 
            padding: 0.75rem 0; 
            border-bottom: 1px dashed #e2e8f0;
        }
        .party-label { font-size: 8px; text-transform: uppercase; color: #94a3b8; margin-bottom: 2px; }
        .party-name { font-weight: 800; font-size: 11px; }
        .party-detail { font-size: 10px; color: #64748b; line-height: 1.2; }

        .invoice-table { margin-bottom: 1rem; }
        .invoice-table th { font-size: 9px; padding: 6px 0; border-bottom: 1px solid #f1f5f9; }
        .invoice-table td { padding: 8px 0; font-size: 10px; border-bottom: 1px solid #f8fafc; }
        
        .invoice-totals { border-top: 1px solid #e2e8f0; padding-top: 0.75rem; }
        .invoice-totals-table { width: 100%; }
        .invoice-totals-row { display: flex; justify-content: space-between; padding: 4px 0; font-size: 10px; }
        .invoice-totals-final { 
            display: flex; justify-content: space-between; padding-top: 8px; margin-top: 4px; 
            border-top: 2px solid #0f172a; font-size: 14px; font-weight: 900; color: #0f172a; 
        }

        .invoice-footer { margin-top: 1rem; font-size: 8px; text-align: center; color: #94a3b8; border-top: none; padding-top: 0; }
    </style>
</head>
<body>
<div class="invoice-wrapper">
    <div class="invoice-actions no-print">
        <a href="sales.php" class="btn btn-ghost">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Retour
        </a>
        <button onclick="window.print()" class="btn btn-primary btn-lg" style="flex:1;">
            Imprimer (A6)
        </button>
    </div>

    <div class="invoice-paper">
        <div class="invoice-header">
            <div class="invoice-brand">
                <img src="logo.jpg" alt="Logo">
            </div>
            <div class="invoice-meta">
                <h2>FACTURE</h2>
                <p>#VQ-<?= str_pad($sale_id, 4, '0', STR_PAD_LEFT) ?></p>
            </div>
        </div>

        <div class="invoice-parties">
            <div>
                <p class="party-label">Vendeur</p>
                <p class="party-name"><?= htmlspecialchars($sale['seller_name']) ?></p>
                <p class="party-detail"><?= date('d/m/y H:i', strtotime($sale['created_at'])) ?></p>
            </div>
            <div style="text-align:right;">
                <p class="party-label">Client</p>
                <p class="party-name"><?= $sale['client_name'] ? htmlspecialchars($sale['client_name']) : 'Client de passage' ?></p>
                <?php if($sale['client_phone']): ?><p class="party-detail"><?= htmlspecialchars($sale['client_phone']) ?></p><?php endif; ?>
            </div>
        </div>

        <table class="invoice-table">
            <thead>
                <tr>
                    <th style="text-align:left;">Désignation</th>
                    <th style="text-align:center;">Qté</th>
                    <th style="text-align:right;">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $total_discount = 0;
                foreach($items as $it): 
                    $total_discount += $it['discount_amount'] * $it['quantity'];
                ?>
                <tr>
                    <td>
                        <div style="font-weight:700;"><?= htmlspecialchars($it['product_name']) ?></div>
                        <?php if ($it['discount_amount'] > 0): ?>
                            <div style="font-size: 8px; color: var(--color-primary); font-style: italic;">
                                Remise: -<?= format_price($it['discount_amount'], $sale['currency']) ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:center;"><?= $it['quantity'] ?></td>
                    <td style="text-align:right; font-weight:700;"><?= format_price($it['subtotal'], $sale['currency']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="invoice-totals">
            <div class="invoice-totals-table">
                <?php if ($total_discount > 0): ?>
                <div class="invoice-totals-row">
                    <span>Sous-total brut</span>
                    <span><?= format_price($sale['total_amount'] + $total_discount, $sale['currency']) ?></span>
                </div>
                <div class="invoice-totals-row" style="color: var(--color-primary); font-weight: 700;">
                    <span>Remises / Réductions</span>
                    <span>-<?= format_price($total_discount, $sale['currency']) ?></span>
                </div>
                <?php endif; ?>
                <div class="invoice-totals-final">
                    <span>TOTAL</span>
                    <span><?= format_price($sale['total_amount'], $sale['currency']) ?></span>
                </div>
                <div style="margin-top:0.5rem; text-align:center;">
                    <span class="badge badge-slate" style="font-size:9px; background: #f1f5f9;">
                         PAYÉ PAR : <?= strtoupper($sale['payment_type']) ?>
                    </span>
                </div>
            </div>
        </div>

        <div class="invoice-footer">
            Merci de votre confiance. LXTRONIC
        </div>
    </div>
</div>
</body>
</html>
