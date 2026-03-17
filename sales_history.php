<?php
require_once 'config/db.php';
require_once 'includes/helpers.php';
check_auth();

$sales = $pdo->query("SELECT s.*, c.name as client_name, u.username as seller_name 
                      FROM sales s 
                      LEFT JOIN clients c ON s.client_id = c.id 
                      LEFT JOIN users u ON s.user_id = u.id 
                      ORDER BY s.created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr" class="bg-[#F8FAFC]">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historique des Ventes - QuincaTech</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Outfit', sans-serif; }</style>
</head>
<body class="flex">
    <?php include 'includes/sidebar.php'; ?>

    <main class="ml-64 p-8 flex-1">
        <header class="flex justify-between items-center mb-10">
            <div>
                <h1 class="text-3xl font-bold text-slate-800">Historique des Ventes</h1>
                <p class="text-slate-500 mt-1">Consultez et gérez toutes les transactions passées.</p>
            </div>
            <a href="sales.php" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-3 rounded-2xl shadow-lg shadow-blue-200 transition-all flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 4v16m8-8H4" stroke-width="2"></path></svg>
                Nouvelle Vente
            </a>
        </header>

        <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
            <table class="w-full text-left">
                <thead class="bg-slate-50 border-b border-slate-100 text-slate-500 uppercase text-xs font-bold tracking-wider">
                    <tr>
                        <th class="px-6 py-4">ID</th>
                        <th class="px-6 py-4">Date & Heure</th>
                        <th class="px-6 py-4">Client</th>
                        <th class="px-6 py-4">Vendeur</th>
                        <th class="px-6 py-4">Type</th>
                        <th class="px-6 py-4 text-right">Montant Total</th>
                        <th class="px-6 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php if (empty($sales)): ?>
                        <tr><td colspan="7" class="px-6 py-10 text-center text-slate-400">Aucune vente enregistrée.</td></tr>
                    <?php else: ?>
                        <?php foreach ($sales as $s): ?>
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="px-6 py-4 font-bold text-slate-400">#<?= $s['id'] ?></td>
                                <td class="px-6 py-4 text-slate-600"><?= date('d/m/Y H:i', strtotime($s['created_at'])) ?></td>
                                <td class="px-6 py-4 font-semibold text-slate-700"><?= $s['client_name'] ?: 'Client de passage' ?></td>
                                <td class="px-6 py-4 text-slate-500"><?= $s['seller_name'] ?></td>
                                <td class="px-6 py-4">
                                    <span class="px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider <?= $s['payment_type'] == 'comptant' ? 'bg-green-50 text-green-600' : 'bg-red-50 text-red-600' ?>">
                                        <?= $s['payment_type'] ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right font-black text-slate-800"><?= format_price($s['total_amount']) ?></td>
                                <td class="px-6 py-4 text-right">
                                    <a href="invoice.php?id=<?= $s['id'] ?>" class="text-blue-600 hover:text-blue-800 font-bold text-sm">Voir Facture</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</body>
</html>
