<?php
require_once 'config/db.php';
require_once 'includes/helpers.php';
check_auth();

// Récupérer le taux de change
$usd_to_cdf = (float)get_setting('usd_to_cdf', 2800);

$products = $pdo->query("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id=c.id WHERE p.stock_quantity > 0 ORDER BY p.name ASC")->fetchAll();
$clients = $pdo->query("SELECT * FROM clients ORDER BY name ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cart_data'])) {
    $cart = json_decode($_POST['cart_data'], true);
    $client_id = !empty($_POST['client_id']) ? $_POST['client_id'] : null;
    $payment_type = $_POST['payment_type'] ?? 'comptant';
    $currency = $_POST['currency'] ?? 'USD';
    $total_amount_usd = 0;

    if (!empty($cart)) {
        $pdo->beginTransaction();
        try {
            $status = ($payment_type === 'credit') ? 'dette' : 'paye';
            
            // Calculer le total en USD car les produits sont prix en USD
            foreach ($cart as $item) {
                $total_amount_usd += $item['price'] * $item['quantity'];
            }

            // Enregistrer la vente dans la devise choisie
            $final_amount = ($currency === 'CDF') ? ($total_amount_usd * $usd_to_cdf) : $total_amount_usd;

            $stmt = $pdo->prepare("INSERT INTO sales (user_id, client_id, total_amount, payment_type, status, currency, exchange_rate) VALUES(?,?,?,?,?,?,?)");
            $stmt->execute([$_SESSION['user_id'], $client_id, $final_amount, $payment_type, $status, $currency, $usd_to_cdf]);
            $sale_id = $pdo->lastInsertId();

            foreach ($cart as $item) {
                $subtotal_usd = $item['price'] * $item['quantity'];
                $item_price = ($currency === 'CDF') ? ($item['price'] * $usd_to_cdf) : $item['price'];
                $item_subtotal = ($currency === 'CDF') ? ($subtotal_usd * $usd_to_cdf) : $subtotal_usd;

                $pdo->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, unit_price, subtotal) VALUES(?,?,?,?,?)")
                    ->execute([$sale_id, $item['id'], $item['quantity'], $item_price, $item_subtotal]);
                
                $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id=?")->execute([$item['quantity'], $item['id']]);
            }

            if ($payment_type === 'credit' && $client_id) {
                // La dette du client reste en USD pour simplification ou conversion ?
                // On va la stocker telle que payée si crédit ? Non, généralement on garde la dette en USD.
                // Par cohérence avec le reste, on va la stocker dans la devise de la vente.
                $pdo->prepare("UPDATE clients SET total_debt = total_debt + ? WHERE id=?")->execute([$final_amount, $client_id]);
            }

            $pdo->commit();
            header("Location: invoice.php?id=" . $sale_id);
            exit();
        } catch(Exception $e) {
            $pdo->rollBack();
            $error = "Erreur : " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <title>Caisse / POS - LxTronic</title>
    <?php include 'includes/head.php'; ?>
    <style>
        .currency-toggle { display: flex; gap: 4px; background: var(--color-slate-100); padding: 4px; border-radius: var(--radius-lg); margin-bottom: 1.25rem; }
        .currency-btn { flex: 1; padding: 10px; border: none; border-radius: var(--radius-md); font-weight: 700; cursor: pointer; transition: all 0.2s; background: transparent; color: var(--color-slate-500); }
        .currency-btn.active { background: white; color: var(--color-primary); box-shadow: var(--shadow-sm); }
    </style>
</head>
<body>
<div style="display:flex; min-height:100vh;">
    <?php include 'includes/sidebar.php'; ?>

    <!-- POS Main Area -->
    <div class="pos-layout" style="margin-left:var(--sidebar-width); flex:1;">
        <div class="pos-main">
            <header class="page-header">
                <div>
                    <h1>Caisse / Vente</h1>
                    <p>Prix des produits gérés en **USD**</p>
                </div>
            </header>

            <div class="search-wrap">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" id="productSearch" onkeyup="filterProducts()" placeholder="Rechercher un produit..." class="search-input">
            </div>

            <div class="product-grid" id="productList">
                <?php foreach($products as $p): ?>
                <div class="product-card product-item" onclick='addToCart(<?= json_encode($p) ?>)'>
                    <div class="product-card-top">
                        <span class="product-category"><?= htmlspecialchars($p['category_name'] ?: 'Général') ?></span>
                        <h3 class="product-name"><?= htmlspecialchars($p['name']) ?></h3>
                        <p class="product-stock">Stock: <?= $p['stock_quantity'] ?></p>
                    </div>
                    <div class="product-card-bottom">
                        <span class="product-price"><?= format_price($p['sell_price'], 'USD') ?></span>
                        <button class="product-add-btn">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Cart Sidebar -->
        <div class="pos-sidebar">
            <div class="cart-header">
                <h2 class="cart-title">Panier <span class="cart-count" id="cartCount">0</span></h2>
            </div>

            <div class="cart-items" id="cartItems">
                <div class="cart-empty"><p>Le panier est vide</p></div>
            </div>

            <!-- Checkout Form -->
            <form id="saleForm" method="POST">
                <input type="hidden" name="cart_data" id="cartData">
                <input type="hidden" name="currency" id="currencyInput" value="USD">

                <div class="form-group">
                    <label class="form-label">Devise de paiement</label>
                    <div class="currency-toggle">
                        <button type="button" class="currency-btn active" id="btnUSD" onclick="setCurrency('USD')">USD ($)</button>
                        <button type="button" class="currency-btn" id="btnCDF" onclick="setCurrency('CDF')">CDF (FC)</button>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom:1rem;">
                    <label class="form-label">Client (optionnel)</label>
                    <select name="client_id" id="clientSelect" class="form-control">
                        <option value="">Client de passage</option>
                        <?php foreach($clients as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom:1.25rem;">
                    <label class="form-label">Mode</label>
                    <div class="pay-options">
                        <label class="pay-option">
                            <input type="radio" name="payment_type" value="comptant" checked>
                            <span class="pay-option-label">💵 Comptant</span>
                        </label>
                        <label class="pay-option">
                            <input type="radio" name="payment_type" id="radioCredit" value="credit">
                            <span class="pay-option-label">📋 Crédit</span>
                        </label>
                    </div>
                </div>

                <div class="total-box" style="margin-bottom:1.25rem;">
                    <p class="total-label">Total à payer</p>
                    <h3 class="total-amount" id="totalDisplay">$0.00</h3>
                    <p id="subTotalDisplay" style="font-size:12px; color:var(--color-slate-400); margin-top:4px;"></p>
                </div>

                <button type="submit" onclick="submitSale(event)" class="btn btn-primary btn-lg btn-full">
                    Valider la vente
                </button>
            </form>
        </div>
    </div>
</div>

<script>
    let cart = [];
    let currentCurrency = 'USD';
    const exchangeRate = <?= $usd_to_cdf ?>;

    const fmt = (n, cur) => {
        if (cur === 'CDF') return parseFloat(n).toLocaleString('fr-FR') + ' FC';
        return '$' + parseFloat(n).toFixed(2);
    };

    function setCurrency(cur) {
        currentCurrency = cur;
        document.getElementById('currencyInput').value = cur;
        document.getElementById('btnUSD').classList.toggle('active', cur === 'USD');
        document.getElementById('btnCDF').classList.toggle('active', cur === 'CDF');
        render();
    }

    function addToCart(p) {
        const ex = cart.find(i => i.id == p.id);
        if (ex) {
            if (ex.quantity < p.stock_quantity) ex.quantity++;
            else return alert('Stock insuffisant !');
        } else {
            cart.push({...p, quantity: 1, price: parseFloat(p.sell_price)});
        }
        render();
    }
    function removeFromCart(id) { cart = cart.filter(i => i.id != id); render(); }
    function updateQty(id, d) {
        const item = cart.find(i => i.id == id);
        if (!item) return;
        const nq = item.quantity + d;
        if (nq <= 0) removeFromCart(id);
        else if (nq <= item.stock_quantity) { item.quantity = nq; render(); }
    }

    function render() {
        const box = document.getElementById('cartItems');
        const count = document.getElementById('cartCount');
        const total = document.getElementById('totalDisplay');
        const subtotalText = document.getElementById('subTotalDisplay');

        if (!cart.length) {
            box.innerHTML = '<div class="cart-empty"><p>Le panier est vide</p></div>';
            count.textContent = '0';
            total.textContent = fmt(0, currentCurrency);
            subtotalText.textContent = '';
            return;
        }

        let html = '', sumUSD = 0;
        cart.forEach(i => {
            const subUSD = i.price * i.quantity;
            sumUSD += subUSD;
            const displayPrice = (currentCurrency === 'CDF') ? (i.price * exchangeRate) : i.price;
            const displaySub = (currentCurrency === 'CDF') ? (subUSD * exchangeRate) : subUSD;

            html += `<div class="cart-item">
                <div class="cart-item-info">
                    <p class="cart-item-name">${i.name}</p>
                    <p class="cart-item-price">${fmt(displayPrice, currentCurrency)}</p>
                </div>
                <div class="cart-qty">
                    <button onclick="updateQty(${i.id},-1)">−</button>
                    <span>${i.quantity}</span>
                    <button onclick="updateQty(${i.id},1)">+</button>
                </div>
                <span class="cart-item-subtotal">${fmt(displaySub, currentCurrency)}</span>
            </div>`;
        });
        box.innerHTML = html;
        count.textContent = cart.length;

        const finalAmount = (currentCurrency === 'CDF') ? (sumUSD * exchangeRate) : sumUSD;
        total.textContent = fmt(finalAmount, currentCurrency);
        
        if (currentCurrency === 'CDF') {
            subtotalText.textContent = "Soit " + fmt(sumUSD, 'USD') + " (Taux: " + exchangeRate + ")";
        } else {
            subtotalText.textContent = "Soit " + fmt(sumUSD * exchangeRate, 'CDF') + " (Taux: " + exchangeRate + ")";
        }
    }

    function filterProducts() {
        const q = document.getElementById('productSearch').value.toLowerCase();
        document.querySelectorAll('.product-item').forEach(el => {
            el.style.display = el.innerText.toLowerCase().includes(q) ? 'flex' : 'none';
        });
    }

    function submitSale(e) {
        e.preventDefault();
        if (!cart.length) return alert('Le panier est vide !');
        if (document.getElementById('radioCredit').checked && !document.getElementById('clientSelect').value)
            return alert('Sélectionnez un client pour une vente à crédit.');
        document.getElementById('cartData').value = JSON.stringify(cart);
        document.getElementById('saleForm').submit();
    }
</script>
</body>
</html>
