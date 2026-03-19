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
                padding: 5mm 5mm;
                position: relative;
            }
        }

        .invoice-paper {
            width: 100%;
            max-width: 420px; /* Tighter Preview */
            padding: 1rem;
            font-size: 11px; /* Slightly smaller base font */
            line-height: 1.2;
        }

        .invoice-header { margin-bottom: 0.5rem; display: flex; flex-direction: column; align-items: center; border-bottom: 1px solid #e2e8f0; padding-bottom: 0.5rem; text-align: center; }
        .invoice-brand img { height: 50px !important; max-width: 150px; object-fit: contain; margin-bottom: 4px; }
        .brand-details { font-size: 8px; color: #475569; line-height: 1.1; }
        .invoice-meta { margin-top: 4px; }
        .invoice-meta h2 { font-size: 1rem; margin: 0; font-weight: 900; letter-spacing: 1px; }
        .invoice-meta p { font-size: 0.75rem; margin: 0; color: #0f172a; font-weight: 700; }

        .invoice-parties { 
            display: grid; 
            grid-template-columns: 1fr 1fr; 
            gap: 0.5rem; 
            margin-bottom: 0.5rem; 
            padding: 0.5rem 0; 
            border-bottom: 1px dashed #e2e8f0;
        }
        .party-label { font-size: 7px; text-transform: uppercase; color: #94a3b8; margin-bottom: 1px; }
        .party-name { font-weight: 800; font-size: 10px; }
        .party-detail { font-size: 9px; color: #64748b; line-height: 1.1; }

        .invoice-table { margin-bottom: 0.5rem; width: 100%; border-collapse: collapse; }
        .invoice-table th { font-size: 8px; padding: 4px 2px; border-bottom: 1px solid #0f172a; text-transform: uppercase; }
        .invoice-table td { padding: 4px 2px; font-size: 9px; border-bottom: 1px solid #f1f5f9; vertical-align: top; }
        
        .invoice-totals { border-top: 1px solid #0f172a; padding-top: 0.5rem; }
        .invoice-totals-table { width: 100%; }
        .invoice-totals-row { display: flex; justify-content: space-between; padding: 2px 0; font-size: 9px; }
        .invoice-totals-final { 
            display: flex; justify-content: space-between; padding-top: 4px; margin-top: 2px; 
            border-top: 1.5px solid #0f172a; font-size: 12px; font-weight: 900; color: #0f172a; 
        }

        .invoice-footer { margin-top: 0.5rem; font-size: 8px; text-align: center; color: #475569; font-weight: 600; }
        .footer-tagline { font-size: 7px; font-style: italic; color: #94a3b8; margin-top: 2px; text-align: center; }
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
                <div class="brand-details">
                    <p>Galerie MTB Numero 10, Rue Kinshasa, Butembo/NK</p>
                    <p>Tél : +243 897 205 777 | +243 992 003 159</p>
                    <p>RCCM : 0000000000 | ID NAT : 567890000</p>
                </div>
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
                            <div style="font-size: 7px; color: var(--color-primary); font-style: italic;">
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
                <div style="margin-top:0.25rem; text-align:center;">
                    <span style="font-size:8px; background: #f1f5f9; padding: 2px 6px; border-radius: 4px; font-weight: 800;">
                         PAIEMENT : <?= strtoupper($sale['payment_type']) ?>
                    </span>
                </div>
            </div>
        </div>

        <div class="invoice-footer">
            Merci de votre confiance. LXTRONIC
        </div>
        <div class="footer-tagline">"Innovation At Your Service"</div>
    </div>
</div>
</body>
</html>
