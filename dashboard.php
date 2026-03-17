<?php
require_once 'config/db.php';
require_once 'includes/helpers.php';
check_auth();

// Fetch basic stats (Real stats would come from DB queries)
$stats = [
    'total_sales' => $pdo->query("SELECT SUM(total_amount) FROM sales WHERE DATE(created_at) = CURDATE()")->fetchColumn() ?: 0,
    'total_stock_value' => $pdo->query("SELECT SUM(stock_quantity * buy_price) FROM products")->fetchColumn() ?: 0,
    'low_stock_count' => $pdo->query("SELECT COUNT(*) FROM products WHERE stock_quantity <= min_stock_threshold")->fetchColumn() ?: 0,
    'total_debt' => $pdo->query("SELECT SUM(total_debt) FROM clients")->fetchColumn() ?: 0,
];

$recent_sales = $pdo->query("SELECT s.*, c.name as client_name FROM sales s LEFT JOIN clients c ON s.client_id = c.id ORDER BY s.created_at DESC LIMIT 5")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord - QuincaTech</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { font-family: 'Outfit', sans-serif; }
    </style>
</head>
<body class="bg-[#F8FAFC]">
    
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <main class="ml-64 p-8 min-h-screen">
        <!-- Header -->
        <header class="flex justify-between items-center mb-10">
            <div>
                <h1 class="text-3xl font-bold text-slate-800">Bonjour, <?= $_SESSION['username'] ?> 👋</h1>
                <p class="text-slate-500 mt-1">Voici ce qui se passe aujourd'hui.</p>
            </div>
            <div class="flex items-center gap-4">
                <button class="bg-white p-3 rounded-2xl shadow-sm border border-slate-200 text-slate-600 hover:text-blue-600 transition-all">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" stroke-width="2"></path></svg>
                </button>
                <a href="sales.php" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-3 rounded-2xl shadow-lg shadow-blue-200 transition-all flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 4v16m8-8H4" stroke-width="2"></path></svg>
                    Nouvelle Vente
                </a>
            </div>
        </header>

        <!-- Stats Grid -->
        <section class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-10">
            <!-- Total Sales Today -->
            <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-100 group hover:shadow-xl transition-all duration-300">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 bg-green-50 rounded-2xl flex items-center justify-center text-green-600 group-hover:scale-110 transition-transform">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" stroke-width="2"></path></svg>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-slate-500 uppercase tracking-wider">Ventes du jour</p>
                        <h3 class="text-2xl font-bold text-slate-800"><?= format_price($stats['total_sales']) ?></h3>
                    </div>
                </div>
            </div>

            <!-- Value in Stock -->
            <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-100 group hover:shadow-xl transition-all duration-300">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 bg-blue-50 rounded-2xl flex items-center justify-center text-blue-600 group-hover:scale-110 transition-transform">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" stroke-width="2"></path></svg>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-slate-500 uppercase tracking-wider">Valeur Stock</p>
                        <h3 class="text-2xl font-bold text-slate-800"><?= format_price($stats['total_stock_value']) ?></h3>
                    </div>
                </div>
            </div>

            <!-- Debts -->
            <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-100 group hover:shadow-xl transition-all duration-300">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 bg-red-50 rounded-2xl flex items-center justify-center text-red-600 group-hover:scale-110 transition-transform">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" stroke-width="2"></path></svg>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-slate-500 uppercase tracking-wider">Dettes Clients</p>
                        <h3 class="text-2xl font-bold text-slate-800"><?= format_price($stats['total_debt']) ?></h3>
                    </div>
                </div>
            </div>

            <!-- Low Stock Alert -->
            <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-100 group hover:shadow-xl transition-all duration-300">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 <?= $stats['low_stock_count'] > 0 ? 'bg-orange-50 text-orange-600' : 'bg-green-50 text-green-600' ?> rounded-2xl flex items-center justify-center group-hover:scale-110 transition-transform">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" stroke-width="2"></path></svg>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-slate-500 uppercase tracking-wider">Alertes Stock</p>
                        <h3 class="text-2xl font-bold text-slate-800"><?= $stats['low_stock_count'] ?> Produits</h3>
                    </div>
                </div>
            </div>
        </section>

        <!-- Charts & History -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Main Chart area (Placeholder for now) -->
            <div class="lg:col-span-2 bg-white p-8 rounded-3xl shadow-sm border border-slate-100">
                <div class="flex justify-between items-center mb-8">
                    <h3 class="text-lg font-bold text-slate-800">Évolution des ventes</h3>
                    <select class="bg-slate-50 border-none rounded-xl text-sm px-4 py-2 outline-none focus:ring-2 focus:ring-blue-100">
                        <option>7 derniers jours</option>
                        <option>30 derniers jours</option>
                    </select>
                </div>
                <!-- Placeholder for Chart.js -->
                <canvas id="salesChart" height="200"></canvas>
            </div>

            <!-- Recent Sales Sidebar -->
            <div class="bg-white p-8 rounded-3xl shadow-sm border border-slate-100">
                <h3 class="text-lg font-bold text-slate-800 mb-6">Activités Récentes</h3>
                <div class="space-y-6">
                    <?php if (empty($recent_sales)): ?>
                        <p class="text-slate-400 text-center py-10">Aucune vente enregistrée.</p>
                    <?php else: ?>
                        <?php foreach($recent_sales as $sale): ?>
                        <div class="flex items-start gap-4">
                            <div class="w-10 h-10 rounded-full bg-slate-50 flex items-center justify-center text-slate-400">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" stroke-width="2"></path></svg>
                            </div>
                            <div class="flex-1">
                                <p class="text-sm font-bold text-slate-800"><?= $sale['client_name'] ?: 'Client Comptant' ?></p>
                                <p class="text-xs text-slate-500"><?= date('H:i', strtotime($sale['created_at'])) ?></p>
                            </div>
                            <span class="text-sm font-bold text-blue-600">+<?= format_price($sale['total_amount']) ?></span>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <a href="sales_history.php" class="block text-center mt-8 text-sm font-semibold text-blue-600 hover:text-blue-700 transition-colors">Voir tout l'historique &rarr;</a>
            </div>
        </div>
    </main>

    <script>
        const ctx = document.getElementById('salesChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'],
                datasets: [{
                    label: 'Ventes (FCFA)',
                    data: [12000, 19000, 15000, 25000, 22000, 30000, 15000],
                    borderColor: '#2563eb',
                    backgroundColor: 'rgba(37, 99, 235, 0.1)',
                    borderWidth: 3,
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: '#2563eb',
                    pointBorderWidth: 2,
                    pointRadius: 4
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, grid: { color: '#f1f5f9' }, border: { display: false } },
                    x: { grid: { display: false }, border: { display: false } }
                }
            }
        });
    </script>
</body>
</html>
