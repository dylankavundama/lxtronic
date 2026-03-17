<?php
require_once 'config/db.php';
require_once 'includes/helpers.php';
check_auth();

$products = $pdo->query("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id=c.id WHERE p.stock_quantity > 0 ORDER BY p.name ASC")->fetchAll();
$clients = $pdo->query("SELECT * FROM clients ORDER BY name ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cart_data'])) {
    $cart = json_decode($_POST['cart_data'], true);
    $client_id = !empty($_POST['client_id']) ? $_POST['client_id'] : null;
    $payment_type = $_POST['payment_type'] ?? 'comptant';
    $total_amount = 0;

    if (!empty($cart)) {
        $pdo->beginTransaction();
        try {
            $status = ($payment_type === 'credit') ? 'dette' : 'paye';
            $pdo->prepare("INSERT INTO sales (user_id,client_id,total_amount,payment_type,status) VALUES(?,?,0,?,?)")->execute([$_SESSION['user_id'],$client_id,$payment_type,$status]);
            $sale_id = $pdo->lastInsertId();
            foreach ($cart as $item) {
                $subtotal = $item['price'] * $item['quantity'];
                $total_amount += $subtotal;
                $pdo->prepare("INSERT INTO sale_items (sale_id,product_id,quantity,unit_price,subtotal) VALUES(?,?,?,?,?)")->execute([$sale_id,$item['id'],$item['quantity'],$item['price'],$subtotal]);
                $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id=?")->execute([$item['quantity'],$item['id']]);
            }
            $pdo->prepare("UPDATE sales SET total_amount=? WHERE id=?")->execute([$total_amount,$sale_id]);
            if ($payment_type === 'credit' && $client_id) {
                $pdo->prepare("UPDATE clients SET total_debt = total_debt + ? WHERE id=?")->execute([$total_amount,$client_id]);
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
    <title>Nouvelle Vente - QuincaTech</title>
    <?php include 'includes/head.php'; ?>
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
                    <p>Cliquez sur les produits pour les ajouter au panier.</p>
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
                        <span class="product-price"><?= format_price($p['sell_price']) ?></span>
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
                <h2 class="cart-title">
                    Panier
                    <span class="cart-count" id="cartCount">0</span>
                </h2>
            </div>

            <div class="cart-items" id="cartItems">
                <div class="cart-empty">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                    <p>Le panier est vide</p>
                </div>
            </div>

            <!-- Checkout Form -->
            <form id="saleForm" method="POST">
                <input type="hidden" name="cart_data" id="cartData">

                <div class="form-group" style="margin-bottom:1rem;">
                    <label class="form-label">Client (optionnel)</label>
                    <select name="client_id" id="clientSelect" class="form-control">
                        <option value="">Client de passage</option>
                        <?php foreach($clients as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?> (<?= format_price($c['total_debt']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom:1.25rem;">
                    <label class="form-label">Mode de paiement</label>
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
                    <h3 class="total-amount" id="totalDisplay">0 FCFA</h3>
                </div>

                <button type="submit" onclick="submitSale(event)" class="btn btn-primary btn-lg btn-full">
                    Enregistrer la vente
                </button>
            </form>
        </div>
    </div>
</div>

<script>
    let cart = [];
    const fmt = n => parseFloat(n).toLocaleString('fr-FR') + ' FCFA';

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
        if (!cart.length) {
            box.innerHTML = `<div class="cart-empty"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width:56px;height:56px;margin-bottom:12px"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg><p>Le panier est vide</p></div>`;
            count.textContent = '0';
            total.textContent = '0 FCFA';
            return;
        }
        let html = '', sum = 0;
        cart.forEach(i => {
            const sub = i.price * i.quantity; sum += sub;
            html += `<div class="cart-item">
                <div class="cart-item-info">
                    <p class="cart-item-name">${i.name}</p>
                    <p class="cart-item-price">${fmt(i.price)}</p>
                </div>
                <div class="cart-qty">
                    <button onclick="updateQty(${i.id},-1)">−</button>
                    <span>${i.quantity}</span>
                    <button onclick="updateQty(${i.id},1)">+</button>
                </div>
                <span class="cart-item-subtotal">${fmt(sub)}</span>
                <button class="cart-remove" onclick="removeFromCart(${i.id})">
                    <svg fill="currentColor" viewBox="0 0 20 20" style="width:16px;height:16px"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                </button>
            </div>`;
        });
        box.innerHTML = html;
        count.textContent = cart.length;
        total.textContent = fmt(sum);
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
