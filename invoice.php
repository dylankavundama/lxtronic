<?php
require_once 'config/db.php';
require_once 'includes/helpers.php';
check_auth();

$sale_id = $_GET['id'] ?? null;
if (!$sale_id) die("Vente non spécifiée.");

$stmt = $pdo->prepare("SELECT s.*, c.name as client_name, c.address as client_address, c.phone as client_phone, u.username as seller_name 
                        FROM sales s 
                        LEFT JOIN clients c ON s.client_id = c.id 
                        LEFT JOIN users u ON s.user_id = u.id 
                        WHERE s.id = ?");
$stmt->execute([$sale_id]);
$sale = $stmt->fetch();

if (!$sale) die("Vente introuvable.");

$stmt = $pdo->prepare("SELECT si.*, p.name as product_name FROM sale_items si JOIN products p ON si.product_id = p.id WHERE sale_id = ?");
$stmt->execute([$sale_id]);
$items = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr" class="bg-slate-100">
<head>
    <meta charset="UTF-8">
    <title>Facture #<?= $sale_id ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Courier+Prime&family=Outfit:wght@400;700&display=swap" rel="stylesheet">
    <style>
        @media print {
            body { background: white; padding: 0; }
            .no-print { display: none; }
            .print-area { box-shadow: none; border: none; width: 100%; margin: 0; }
        }
        .receipt { font-family: 'Courier Prime', monospace; }
        h1, h2, h3 { font-family: 'Outfit', sans-serif; }
    </style>
</head>
<body class="p-8 flex flex-col items-center">
    
    <div class="no-print mb-8 flex gap-4 w-full max-w-2xl">
        <a href="sales.php" class="bg-white px-6 py-3 rounded-xl border border-slate-200 font-bold text-slate-600 hover:bg-slate-50 transition-all flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M10 19l-7-7m0 0l7-7m-7 7h18" stroke-width="2"></path></svg>
            Retour
        </a>
        <button onclick="window.print()" class="flex-1 bg-blue-600 text-white px-6 py-3 rounded-xl font-bold hover:bg-blue-700 transition-all shadow-lg shadow-blue-200 flex items-center justify-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" stroke-width="2"></path></svg>
            Imprimer la Facture
        </button>
    </div>

    <div class="print-area bg-white w-full max-w-2xl p-12 shadow-2xl rounded-3xl receipt text-slate-800 border-t-8 border-blue-600">
        <div class="flex justify-between items-start mb-12">
            <div>
                <h1 class="text-4xl font-black text-blue-600 tracking-tighter mb-2">QUINCA<span>TECH</span></h1>
                <p class="text-xs text-slate-400">Vente de matériel de quincaillerie de haute qualité.</p>
            </div>
            <div class="text-right">
                <h2 class="text-2xl font-bold text-slate-800">FACTURE</h2>
                <p class="text-slate-500 font-bold">#VQ-<?= str_pad($sale_id, 5, '0', STR_PAD_LEFT) ?></p>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-12 mb-12 border-y border-slate-100 py-8">
            <div>
                <p class="text-[10px] uppercase font-bold text-slate-400 mb-4 tracking-widest">Émis par :</p>
                <p class="font-bold text-slate-700"><?= $sale['seller_name'] ?></p>
                <p class="text-sm text-slate-500 italic"><?= date('d F Y \à H:i', strtotime($sale['created_at'])) ?></p>
            </div>
            <div class="text-right">
                <p class="text-[10px] uppercase font-bold text-slate-400 mb-4 tracking-widest">Facturé à :</p>
                <p class="font-bold text-slate-700"><?= $sale['client_name'] ?: 'Client de passage' ?></p>
                <p class="text-sm text-slate-500"><?= $sale['client_phone'] ?></p>
                <p class="text-sm text-slate-500"><?= $sale['client_address'] ?></p>
            </div>
        </div>

        <table class="w-full mb-12">
            <thead class="text-[10px] uppercase font-bold text-slate-400 border-b border-slate-100">
                <tr>
                    <th class="text-left py-4">Désignation</th>
                    <th class="text-center py-4">Qté</th>
                    <th class="text-right py-4">Prix Unit.</th>
                    <th class="text-right py-4">Total</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50 text-slate-700">
                <?php foreach($items as $it): ?>
                <tr>
                    <td class="py-4 font-bold"><?= $it['product_name'] ?></td>
                    <td class="py-4 text-center"><?= $it['quantity'] ?></td>
                    <td class="py-4 text-right"><?= format_price($it['unit_price']) ?></td>
                    <td class="py-4 text-right font-bold"><?= format_price($it['subtotal']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="flex justify-end pt-8">
            <div class="w-64 space-y-3">
                <div class="flex justify-between text-slate-500">
                    <span>Total HT</span>
                    <span><?= format_price($sale['total_amount'] * 0.8) ?></span>
                </div>
                <div class="flex justify-between text-slate-500">
                    <span>TVA (20%)</span>
                    <span><?= format_price($sale['total_amount'] * 0.2) ?></span>
                </div>
                <div class="flex justify-between pt-3 border-t-2 border-slate-900 text-xl font-bold text-slate-900">
                    <span>NET À PAYER</span>
                    <span><?= format_price($sale['total_amount']) ?></span>
                </div>
                <div class="mt-4 text-right">
                    <span class="px-3 py-1 bg-slate-100 rounded text-[10px] font-bold uppercase tracking-widest">
                        Payé par : <?= $sale['payment_type'] ?>
                    </span>
                </div>
            </div>
        </div>

        <div class="mt-24 pt-8 border-t border-slate-100 text-center text-slate-400 text-[10px] uppercase tracking-[0.2em]">
            Merci de votre confiance.
        </div>
    </div>

</body>
</html>
