<?php
require_once 'config/db.php';
require_once 'includes/helpers.php';
require_admin(); // Sécurité : Seul l'admin a accès

$filter = $_GET['filter'] ?? 'daily';
$date_query = "DATE(s.created_at) = CURDATE()";
$label = "aujourd'hui";

if ($filter === 'monthly') {
    $date_query = "MONTH(s.created_at) = MONTH(CURDATE()) AND YEAR(s.created_at) = YEAR(CURDATE())";
    $label = "ce mois-ci";
} elseif ($filter === 'yearly') {
    $date_query = "YEAR(s.created_at) = YEAR(CURDATE())";
    $label = "cette année";
}

// 1. Total Sales and Benefits
$stats = $pdo->query("SELECT 
    SUM(s.total_amount) as total_sales,
    SUM(si.quantity * (si.unit_price - p.buy_price)) as total_profit
    FROM sales s
    JOIN sale_items si ON s.id = si.sale_id
    JOIN products p ON si.product_id = p.id
    WHERE $date_query")->fetch();

// 1.1 Total Expenses
$total_expenses = $pdo->query("SELECT SUM(amount) FROM expenses WHERE $date_query")->fetchColumn() ?: 0;
$net_profit = ($stats['total_profit'] ?: 0) - $total_expenses;

// 2. Top Products
$top_products = $pdo->query("SELECT 
    p.name, SUM(si.quantity) as total_qty
    FROM sale_items si
    JOIN products p ON si.product_id = p.id
    JOIN sales s ON si.sale_id = s.id
    WHERE $date_query
    GROUP BY p.id
    ORDER BY total_qty DESC
    LIMIT 5")->fetchAll();

// 3. Sales History for the period
$sales_history = $pdo->query("SELECT s.*, c.name as client_name, u.username as seller_name 
    FROM sales s 
    LEFT JOIN clients c ON s.client_id = c.id 
    LEFT JOIN users u ON s.user_id = u.id
    WHERE $date_query 
    ORDER BY s.created_at DESC")->fetchAll();

// 4. Debt Status
$total_debt = $pdo->query("SELECT SUM(total_debt) FROM clients")->fetchColumn() ?: 0;
$total_stock_value = $pdo->query("SELECT SUM(stock_quantity * buy_price) FROM products")->fetchColumn() ?: 0;
?>
<!DOCTYPE html>
<html lang="fr" class="bg-[#F8FAFC]">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rapports Financiers - QuincaTech</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Outfit', sans-serif; }</style>
</head>
<body class="flex">
    <?php include 'includes/sidebar.php'; ?>

    <main class="ml-64 p-8 flex-1">
        <header class="flex justify-between items-center mb-10">
            <div>
                <h1 class="text-3xl font-bold text-slate-800">Rapports & Statistiques</h1>
                <p class="text-slate-500 mt-1">Analyse des performances de la quincaillerie.</p>
            </div>
            <div class="flex bg-white p-1 rounded-2xl shadow-sm border border-slate-100">
                <a href="?filter=daily" class="px-6 py-2 rounded-xl text-sm font-bold transition-all <?= $filter=='daily' ? 'bg-blue-600 text-white shadow-lg shadow-blue-200' : 'text-slate-500 hover:bg-slate-50' ?>">Journalier</a>
                <a href="?filter=monthly" class="px-6 py-2 rounded-xl text-sm font-bold transition-all <?= $filter=='monthly' ? 'bg-blue-600 text-white shadow-lg shadow-blue-200' : 'text-slate-500 hover:bg-slate-50' ?>">Mensuel</a>
                <a href="?filter=yearly" class="px-6 py-2 rounded-xl text-sm font-bold transition-all <?= $filter=='yearly' ? 'bg-blue-600 text-white shadow-lg shadow-blue-200' : 'text-slate-500 hover:bg-slate-50' ?>">Annuel</a>
            </div>
        </header>

        <!-- Dynamic Summary -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-10">
            <div class="bg-white p-6 rounded-3xl border border-slate-100 shadow-sm">
                <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-1">Ventes Totales</p>
                <h3 class="text-2xl font-black text-slate-800"><?= format_price($stats['total_sales'] ?: 0) ?></h3>
                <p class="text-[10px] text-blue-600 mt-2 font-bold uppercase tracking-tighter">Période : <?= $label ?></p>
            </div>
            <div class="bg-white p-6 rounded-3xl border border-blue-100 shadow-sm relative overflow-hidden">
                <div class="relative z-10">
                    <p class="text-xs font-bold text-blue-400 uppercase tracking-widest mb-1">Marge Brute</p>
                    <h3 class="text-2xl font-black text-blue-600"><?= format_price($stats['total_profit'] ?: 0) ?></h3>
                    <p class="text-[10px] text-blue-400 mt-2 font-bold uppercase tracking-tighter">Bénéfice sur les ventes</p>
                </div>
            </div>
            <div class="bg-white p-6 rounded-3xl border border-red-100 shadow-sm relative overflow-hidden">
                <div class="relative z-10">
                    <p class="text-xs font-bold text-red-400 uppercase tracking-widest mb-1">Dépenses</p>
                    <h3 class="text-2xl font-black text-red-600"><?= format_price($total_expenses) ?></h3>
                    <p class="text-[10px] text-red-400 mt-2 font-bold uppercase tracking-tighter">Charges de la période</p>
                </div>
            </div>
            <div class="bg-slate-900 p-6 rounded-3xl border border-slate-800 shadow-xl relative overflow-hidden">
                <div class="relative z-10 text-white">
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-1">Bénéfice Net</p>
                    <h3 class="text-2xl font-black <?= $net_profit >= 0 ? 'text-green-400' : 'text-red-400' ?>"><?= format_price($net_profit) ?></h3>
                    <p class="text-[10px] text-slate-400 mt-2 font-bold uppercase tracking-tighter">Résultat final</p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Table of Sales -->
            <div class="lg:col-span-2 bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
                <div class="p-6 border-b border-slate-50 flex justify-between items-center">
                    <h3 class="font-bold text-slate-800 uppercase tracking-widest text-sm">Détails des transactions</h3>
                </div>
                <table class="w-full text-left">
                    <thead class="bg-slate-50 text-[10px] font-bold text-slate-400 uppercase">
                        <tr>
                            <th class="px-6 py-4">ID</th>
                            <th class="px-6 py-4">Client</th>
                            <th class="px-6 py-4">Vendeur</th>
                            <th class="px-6 py-4">Type</th>
                            <th class="px-6 py-4 text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <?php foreach($sales_history as $s): ?>
                        <tr class="hover:bg-slate-50 text-sm">
                            <td class="px-6 py-4 font-bold text-slate-400">#<?= $s['id'] ?></td>
                            <td class="px-6 py-4 font-bold text-slate-800"><?= $s['client_name'] ?: 'Passage' ?></td>
                            <td class="px-6 py-4 text-slate-500"><?= $s['seller_name'] ?></td>
                            <td class="px-6 py-4 capitalize">
                                <span class="px-2 py-1 rounded text-[10px] font-bold <?= $s['payment_type']=='credit' ? 'bg-red-50 text-red-600' : 'bg-green-50 text-green-600' ?>">
                                    <?= $s['payment_type'] ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right font-black"><?= format_price($s['total_amount']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Top Products -->
            <div class="bg-white rounded-3xl shadow-sm border border-slate-100 p-8">
                <h3 class="font-bold text-slate-800 uppercase tracking-widest text-sm mb-8 border-b border-slate-50 pb-4">Top 5 Produits</h3>
                <div class="space-y-6">
                    <?php if (empty($top_products)): ?>
                        <p class="text-center text-slate-400 py-10">Aucune donnée.</p>
                    <?php else: ?>
                        <?php foreach($top_products as $idx => $tp): ?>
                        <div class="flex items-center gap-4">
                            <div class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center font-bold text-xs"><?= $idx+1 ?></div>
                            <div class="flex-1">
                                <p class="text-sm font-bold text-slate-800 truncate"><?= $tp['name'] ?></p>
                                <div class="w-full bg-slate-100 h-1.5 rounded-full mt-2">
                                    <div class="bg-blue-600 h-full rounded-full" style="width: <?= min(100, $tp['total_qty'] * 5) ?>%"></div>
                                </div>
                            </div>
                            <span class="text-sm font-black text-slate-800"><?= $tp['total_qty'] ?></span>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
