<?php
require_once 'config/db.php';
require_once 'includes/helpers.php';
check_auth();

// Fetch products for selection
$products = $pdo->query("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE stock_quantity > 0 ORDER BY p.name ASC")->fetchAll();
$clients = $pdo->query("SELECT * FROM clients ORDER BY name ASC")->fetchAll();

// Handle Sale Submission (AJAX or Form)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cart_data'])) {
    $cart = json_decode($_POST['cart_data'], true);
    $client_id = !empty($_POST['client_id']) ? $_POST['client_id'] : null;
    $payment_type = $_POST['payment_type'] ?? 'comptant';
    $total_amount = 0;

    if (!empty($cart)) {
        $pdo->beginTransaction();
        try {
            // 1. Create Sale entry
            $stmt = $pdo->prepare("INSERT INTO sales (user_id, client_id, total_amount, payment_type, status) VALUES (?, ?, ?, ?, ?)");
            $status = ($payment_type === 'credit') ? 'dette' : 'paye';
            $stmt->execute([$_SESSION['user_id'], $client_id, 0, $payment_type, $status]);
            $sale_id = $pdo->lastInsertId();

            foreach ($cart as $item) {
                $subtotal = $item['price'] * $item['quantity'];
                $total_amount += $subtotal;

                // 2. Insert Sale Items
                $stmt = $pdo->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, unit_price, subtotal) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$sale_id, $item['id'], $item['quantity'], $item['price'], $subtotal]);

                // 3. Update stock
                $stmt = $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?");
                $stmt->execute([$item['quantity'], $item['id']]);
            }

            // 4. Update total sale amount
            $stmt = $pdo->prepare("UPDATE sales SET total_amount = ? WHERE id = ?");
            $stmt->execute([$total_amount, $sale_id]);

            // 5. If Credit, update client debt
            if ($payment_type === 'credit' && $client_id) {
                $stmt = $pdo->prepare("UPDATE clients SET total_debt = total_debt + ? WHERE id = ?");
                $stmt->execute([$total_amount, $client_id]);
            }

            $pdo->commit();
            header("Location: invoice.php?id=" . $sale_id);
            exit();
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Erreur lors de la vente : " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr" class="bg-[#F8FAFC]">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nouvelle Vente - QuincaTech</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Outfit', sans-serif; }</style>
</head>
<body class="flex">
    <?php include 'includes/sidebar.php'; ?>

    <main class="ml-64 p-8 flex-1 flex gap-8 min-h-screen">
        <!-- Products Selection (Left) -->
        <div class="flex-1">
            <header class="mb-8">
                <h1 class="text-3xl font-bold text-slate-800">Caisse / Vente</h1>
                <p class="text-slate-500 mt-1">Sélectionnez les produits à vendre.</p>
            </header>

            <div class="mb-6 relative">
                <input type="text" id="productSearch" onkeyup="filterProducts()" placeholder="Rechercher un produit ou une catégorie..." 
                       class="w-full pl-12 pr-4 py-4 rounded-3xl border border-slate-200 outline-none focus:ring-4 focus:ring-blue-100 shadow-sm text-lg">
                <svg class="w-6 h-6 absolute left-4 top-1/2 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" stroke-width="2"></path></svg>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6" id="productList">
                <?php foreach($products as $p): ?>
                <div class="product-item bg-white p-6 rounded-3xl border border-slate-100 shadow-sm hover:shadow-xl transition-all cursor-pointer group flex flex-col justify-between h-56"
                     onclick='addToCart(<?= json_encode($p) ?>)'>
                    <div>
                        <div class="flex justify-between items-start mb-4">
                            <span class="px-3 py-1 bg-blue-50 text-blue-600 rounded-full text-[10px] font-bold uppercase tracking-wider"><?= $p['category_name'] ?></span>
                            <span class="text-xs font-bold text-slate-400">Stock: <?= $p['stock_quantity'] ?></span>
                        </div>
                        <h3 class="text-lg font-bold text-slate-800 mb-1 group-hover:text-blue-600 transition-colors"><?= $p['name'] ?></h3>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-xl font-bold text-slate-800"><?= format_price($p['sell_price']) ?></span>
                        <div class="w-10 h-10 bg-slate-50 group-hover:bg-blue-600 group-hover:text-white rounded-xl flex items-center justify-center transition-all">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 4v16m8-8H4" stroke-width="2"></path></svg>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Checkout / Cart (Right) -->
        <div class="w-[450px] bg-white border-l border-slate-100 p-8 sticky top-0 h-screen flex flex-col shadow-2xl">
            <h2 class="text-2xl font-bold text-slate-800 mb-8 flex items-center gap-3">
                Panier
                <span id="cartCount" class="w-6 h-6 bg-red-500 text-white text-[10px] flex items-center justify-center rounded-full">0</span>
            </h2>

            <!-- Cart Items -->
            <div id="cartItems" class="flex-1 overflow-y-auto space-y-6 mb-8 pr-2">
                <div class="flex flex-col items-center justify-center h-full text-slate-300">
                    <svg class="w-16 h-16 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" stroke-width="2"></path></svg>
                    <p class="font-medium">Le panier est vide</p>
                </div>
            </div>

            <!-- Sale Options -->
            <form id="saleForm" method="POST" class="space-y-6 pt-8 border-t border-slate-100">
                <input type="hidden" name="cart_data" id="cartData">
                
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-2">Client (Facultatif)</label>
                    <select name="client_id" id="clientSelect" class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-blue-100">
                        <option value="">Client de passage (Comptant)</option>
                        <?php foreach($clients as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= $c['name'] ?> (<?= format_price($c['total_debt']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-2">Mode de paiement</label>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="cursor-pointer">
                            <input type="radio" name="payment_type" value="comptant" checked class="hidden peer">
                            <div class="text-center py-3 rounded-xl border border-slate-200 peer-checked:border-blue-600 peer-checked:bg-blue-50 peer-checked:text-blue-600 font-semibold transition-all">Comptant</div>
                        </label>
                        <label class="cursor-pointer">
                            <input type="radio" name="payment_type" id="radioCredit" value="credit" class="hidden peer">
                            <div class="text-center py-3 rounded-xl border border-slate-200 peer-checked:border-blue-600 peer-checked:bg-blue-50 peer-checked:text-blue-600 font-semibold transition-all">Crédit</div>
                        </label>
                    </div>
                </div>

                <div class="bg-slate-900 rounded-3xl p-6 text-white overflow-hidden relative">
                    <div class="relative z-10">
                        <p class="text-slate-400 text-sm mb-1 uppercase tracking-widest font-bold">Total à payer</p>
                        <h3 id="totalDisplay" class="text-4xl font-bold">0,00 FCFA</h3>
                    </div>
                    <!-- Decorative Circle -->
                    <div class="absolute -right-10 -bottom-10 w-32 h-32 bg-blue-600 rounded-full blur-3xl opacity-30"></div>
                </div>

                <button type="submit" onclick="submitSale(event)" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-5 rounded-3xl transition-all shadow-xl shadow-blue-200 active:scale-95 text-lg">
                    Enregistrer la vente
                </button>
            </form>
        </div>
    </main>

    <script>
        let cart = [];
        const formatPrice = (p) => p.toLocaleString('fr-FR') + ' FCFA';

        function addToCart(product) {
            const existing = cart.find(item => item.id === product.id);
            if (existing) {
                if(existing.quantity < product.stock_quantity) {
                    existing.quantity++;
                } else {
                    alert("Stock insuffisant !");
                }
            } else {
                cart.push({ ...product, quantity: 1, price: product.sell_price });
            }
            renderCart();
        }

        function removeFromCart(id) {
            cart = cart.filter(item => item.id !== id);
            renderCart();
        }

        function updateQty(id, delta) {
            const item = cart.find(item => item.id === id);
            if(item) {
                const newQty = item.quantity + delta;
                if(newQty > 0 && newQty <= item.stock_quantity) {
                    item.quantity = newQty;
                } else if(newQty <= 0) {
                    removeFromCart(id);
                }
                renderCart();
            }
        }

        function renderCart() {
            const container = document.getElementById('cartItems');
            const totalDisplay = document.getElementById('totalDisplay');
            const cartCount = document.getElementById('cartCount');
            
            if (cart.length === 0) {
                container.innerHTML = `<div class="flex flex-col items-center justify-center h-full text-slate-300">
                    <svg class="w-16 h-16 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" stroke-width="2"></path></svg>
                    <p class="font-medium">Le panier est vide</p>
                </div>`;
                totalDisplay.innerText = "0,00 FCFA";
                cartCount.innerText = "0";
                return;
            }

            let html = '';
            let total = 0;
            cart.forEach(item => {
                const subtotal = item.price * item.quantity;
                total += subtotal;
                html += `
                <div class="flex items-center gap-4 group">
                    <div class="flex-1">
                        <h4 class="font-bold text-slate-800 text-sm truncate">${item.name}</h4>
                        <p class="text-xs text-slate-400">${formatPrice(item.price)}</p>
                    </div>
                    <div class="flex items-center gap-3 bg-slate-50 px-3 py-1.5 rounded-xl">
                        <button onclick="updateQty(${item.id}, -1)" class="text-slate-400 hover:text-blue-600 transition-colors font-bold">-</button>
                        <span class="text-sm font-bold w-4 text-center">${item.quantity}</span>
                        <button onclick="updateQty(${item.id}, 1)" class="text-slate-400 hover:text-blue-600 transition-colors font-bold">+</button>
                    </div>
                    <div class="text-right min-w-[80px]">
                        <p class="text-sm font-bold text-slate-800">${formatPrice(subtotal)}</p>
                    </div>
                    <button onclick="removeFromCart(${item.id})" class="p-1 text-slate-300 hover:text-red-500 opacity-0 group-hover:opacity-100 transition-all">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path></svg>
                    </button>
                </div>`;
            });

            container.innerHTML = html;
            totalDisplay.innerText = formatPrice(total);
            cartCount.innerText = cart.length;
        }

        function filterProducts() {
            const q = document.getElementById('productSearch').value.toLowerCase();
            document.querySelectorAll('.product-item').forEach(card => {
                const text = card.innerText.toLowerCase();
                card.style.display = text.includes(q) ? 'flex' : 'none';
            });
        }

        function submitSale(e) {
            e.preventDefault();
            if (cart.length === 0) {
                alert("Le panier est vide !");
                return;
            }

            const clientSelect = document.getElementById('clientSelect');
            const isCredit = document.getElementById('radioCredit').checked;

            if (isCredit && !clientSelect.value) {
                alert("Vous devez sélectionner un client pour une vente à crédit.");
                return;
            }

            document.getElementById('cartData').value = JSON.stringify(cart);
            document.getElementById('saleForm').submit();
        }
    </script>
</body>
</html>
