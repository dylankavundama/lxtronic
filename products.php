<?php
require_once 'config/db.php';
require_once 'includes/helpers.php';
check_auth();

$message = "";
$error = "";

// Action AJAX : Enregistrement Produit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'save') {
        $id = $_POST['id'] ?? null;
        $name = $_POST['name'] ?? '';
        $category_id = $_POST['category_id'] ?? null;
        $buy_price = $_POST['buy_price'] ?? 0;
        $sell_price = $_POST['sell_price'] ?? 0;
        $stock_quantity = $_POST['stock_quantity'] ?? 0;
        $min_stock_threshold = $_POST['min_stock_threshold'] ?? 5;
        $description = $_POST['description'] ?? '';

        if (!empty($name)) {
            if ($id) {
                $stmt = $pdo->prepare("UPDATE products SET name = ?, category_id = ?, buy_price = ?, sell_price = ?, stock_quantity = ?, min_stock_threshold = ?, description = ? WHERE id = ?");
                $stmt->execute([$name, $category_id, $buy_price, $sell_price, $stock_quantity, $min_stock_threshold, $description, $id]);
                $message = "Produit mis à jour.";
            } else {
                $stmt = $pdo->prepare("INSERT INTO products (name, category_id, buy_price, sell_price, stock_quantity, min_stock_threshold, description) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $category_id, $buy_price, $sell_price, $stock_quantity, $min_stock_threshold, $description]);
                $message = "Produit ajouté.";
            }
        }
    } elseif ($_POST['action'] === 'delete') {
        $id = $_POST['id'];
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$id]);
        $message = "Produit supprimé.";
    }
}

// Récupération des données
$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();
$products = $pdo->query("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id ORDER BY p.name ASC")->fetchAll();

// Alerte Stock Faible global
$low_stock_count = 0;
foreach ($products as $p) {
    if ($p['stock_quantity'] <= $p['min_stock_threshold']) $low_stock_count++;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Produits - QuincaTech</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Outfit', sans-serif; }</style>
</head>
<body class="bg-[#F8FAFC]">
    <?php include 'includes/sidebar.php'; ?>

    <main class="ml-64 p-8 min-h-screen">
        <header class="flex justify-between items-center mb-10">
            <div>
                <h1 class="text-3xl font-bold text-slate-800">Inventaire des Produits</h1>
                <p class="text-slate-500 mt-1">Gérez votre stock et vos prix.</p>
            </div>
            <button onclick="openProductModal()" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-3 rounded-2xl shadow-lg shadow-blue-200 transition-all flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 4v16m8-8H4" stroke-width="2"></path></svg>
                Ajouter un Produit
            </button>
        </header>

        <!-- Low Stock Alert Banner -->
        <?php if ($low_stock_count > 0): ?>
        <div class="bg-orange-50 border border-orange-100 text-orange-700 p-4 rounded-2xl mb-8 flex items-center gap-3">
            <div class="w-10 h-10 bg-orange-100 rounded-xl flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" stroke-width="2"></path></svg>
            </div>
            <div>
                <p class="font-bold">Alerte Stock Faible !</p>
                <p class="text-sm"><?= $low_stock_count ?> produit(s) ont atteint le seuil d'alerte.</p>
            </div>
        </div>
        <?php endif; ?>

        <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
            <table class="w-full text-left">
                <thead class="bg-slate-50 border-b border-slate-100 text-slate-500 uppercase text-xs font-bold tracking-wider">
                    <tr>
                        <th class="px-6 py-4">Produit</th>
                        <th class="px-6 py-4">Catégorie</th>
                        <th class="px-6 py-4">Prix Achat</th>
                        <th class="px-6 py-4">Prix Vente</th>
                        <th class="px-6 py-4">Stock</th>
                        <th class="px-6 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php if (empty($products)): ?>
                        <tr><td colspan="6" class="px-6 py-10 text-center text-slate-400">Aucun produit en stock.</td></tr>
                    <?php else: ?>
                        <?php foreach ($products as $p): ?>
                            <tr class="hover:bg-slate-50 transition-colors group">
                                <td class="px-6 py-4">
                                    <p class="font-semibold text-slate-700"><?= $p['name'] ?></p>
                                    <p class="text-xs text-slate-400"><?= substr($p['description'], 0, 50) ?>...</p>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-3 py-1 bg-slate-100 text-slate-600 rounded-full text-xs font-medium"><?= $p['category_name'] ?: 'Non classé' ?></span>
                                </td>
                                <td class="px-6 py-4 text-slate-600"><?= format_price($p['buy_price']) ?></td>
                                <td class="px-6 py-4 font-bold text-blue-600"><?= format_price($p['sell_price']) ?></td>
                                <td class="px-6 py-4">
                                    <?php 
                                        $stockClass = "bg-green-100 text-green-700";
                                        if ($p['stock_quantity'] <= 0) $stockClass = "bg-red-100 text-red-700";
                                        elseif ($p['stock_quantity'] <= $p['min_stock_threshold']) $stockClass = "bg-orange-100 text-orange-700";
                                    ?>
                                    <span class="px-3 py-1 <?= $stockClass ?> rounded-full text-xs font-bold"><?= $p['stock_quantity'] ?></span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex justify-end gap-2">
                                        <button onclick='editProduct(<?= json_encode($p) ?>)' class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" stroke-width="2"></path></svg>
                                        </button>
                                        <form method="POST" onsubmit="return confirm('Supprimer ce produit ?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                            <button type="submit" class="p-2 text-red-600 hover:bg-red-50 rounded-lg transition-colors">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" stroke-width="2"></path></svg>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

    <!-- Product Modal -->
    <div id="productModal" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-[100] hidden items-center justify-center p-4">
        <div class="bg-white rounded-3xl w-full max-w-2xl shadow-2xl p-8 overflow-y-auto max-h-[90vh]">
            <h3 id="modalTitle" class="text-xl font-bold text-slate-800 mb-6">Ajouter un produit</h3>
            <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" id="prodId">
                
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-slate-600 mb-2">Nom du produit</label>
                    <input type="text" name="name" id="prodName" required class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:ring-4 focus:ring-blue-100 outline-none">
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-2">Catégorie</label>
                    <select name="category_id" id="prodCat" class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:ring-4 focus:ring-blue-100 outline-none">
                        <option value="">Sélectionner...</option>
                        <?php foreach($categories as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= $c['name'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-2">Seuil d'alerte stock</label>
                    <input type="number" name="min_stock_threshold" id="prodThreshold" value="5" class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:ring-4 focus:ring-blue-100 outline-none">
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-2">Prix d'achat</label>
                    <input type="number" step="0.01" name="buy_price" id="prodBuy" required class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:ring-4 focus:ring-blue-100 outline-none">
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-2">Prix de vente</label>
                    <input type="number" step="0.01" name="sell_price" id="prodSell" required class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:ring-4 focus:ring-blue-100 outline-none">
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-2">Quantité initiale</label>
                    <input type="number" name="stock_quantity" id="prodStock" value="0" class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:ring-4 focus:ring-blue-100 outline-none">
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-slate-600 mb-2">Description</label>
                    <textarea name="description" id="prodDesc" rows="2" class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:ring-4 focus:ring-blue-100 outline-none"></textarea>
                </div>

                <div class="md:col-span-2 flex gap-4 mt-4">
                    <button type="button" onclick="closeProductModal()" class="flex-1 px-6 py-3 rounded-xl font-semibold bg-slate-100 text-slate-600 hover:bg-slate-200 transition-all">Annuler</button>
                    <button type="submit" class="flex-1 px-6 py-3 rounded-xl font-semibold bg-blue-600 text-white hover:bg-blue-700 shadow-lg shadow-blue-200 transition-all">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const modal = document.getElementById('productModal');
        function openProductModal() {
            document.getElementById('modalTitle').innerText = "Ajouter un Produit";
            document.getElementById('prodId').value = "";
            document.getElementById('prodName').value = "";
            document.getElementById('prodCat').value = "";
            document.getElementById('prodBuy').value = "";
            document.getElementById('prodSell').value = "";
            document.getElementById('prodStock').value = "0";
            document.getElementById('prodThreshold').value = "5";
            document.getElementById('prodDesc').value = "";
            modal.classList.replace('hidden', 'flex');
        }
        function closeProductModal() {
            modal.classList.replace('flex', 'hidden');
        }
        function editProduct(p) {
            document.getElementById('modalTitle').innerText = "Modifier le Produit";
            document.getElementById('prodId').value = p.id;
            document.getElementById('prodName').value = p.name;
            document.getElementById('prodCat').value = p.category_id;
            document.getElementById('prodBuy').value = p.buy_price;
            document.getElementById('prodSell').value = p.sell_price;
            document.getElementById('prodStock').value = p.stock_quantity;
            document.getElementById('prodThreshold').value = p.min_stock_threshold;
            document.getElementById('prodDesc').value = p.description;
            modal.classList.replace('hidden', 'flex');
        }
    </script>
</body>
</html>
