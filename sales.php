<?php
require_once 'config/db.php';
require_once 'includes/helpers.php';
check_auth();

$usd_to_cdf = (float)get_setting('usd_to_cdf', 2800);

// --- LOGIQUE POS (SI POST) ---
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
            
            // Re-calculate total with discount validation (max 10%)
            $total_amount_usd = 0;
            foreach ($cart as $item) {
                $unit_price = floatval($item['price']);
                $discount = isset($item['discount']) ? floatval($item['discount']) : 0;
                
                // Server-side validation: max 10%
                if ($discount > ($unit_price * 0.10)) {
                    $discount = $unit_price * 0.10;
                }
                
                $total_amount_usd += ($unit_price - $discount) * $item['quantity'];
            }
            
            $final_amount = ($currency === 'CDF') ? ($total_amount_usd * $usd_to_cdf) : $total_amount_usd;

            $stmt = $pdo->prepare("INSERT INTO sales (user_id, client_id, total_amount, payment_type, status, currency, exchange_rate) VALUES(?,?,?,?,?,?,?)");
            $stmt->execute([$_SESSION['user_id'], $client_id, $final_amount, $payment_type, $status, $currency, $usd_to_cdf]);
            $sale_id = $pdo->lastInsertId();

            foreach ($cart as $item) {
                $unit_price = floatval($item['price']);
                $discount = isset($item['discount']) ? floatval($item['discount']) : 0;
                if ($discount > ($unit_price * 0.10)) $discount = $unit_price * 0.10;

                $net_unit_price_usd = $unit_price - $discount;
                $subtotal_usd = $net_unit_price_usd * $item['quantity'];
                
                $item_price = ($currency === 'CDF') ? ($net_unit_price_usd * $usd_to_cdf) : $net_unit_price_usd;
                $item_discount = ($currency === 'CDF') ? ($discount * $usd_to_cdf) : $discount;
                $item_subtotal = ($currency === 'CDF') ? ($subtotal_usd * $usd_to_cdf) : $subtotal_usd;

                $pdo->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, unit_price, discount_amount, subtotal) VALUES(?,?,?,?,?,?)")
                    ->execute([$sale_id, $item['id'], $item['quantity'], $item_price, $item_discount, $item_subtotal]);
                $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id=?")->execute([$item['quantity'], $item['id']]);
            }

            if ($payment_type === 'credit' && $client_id) {
                $pdo->prepare("UPDATE clients SET total_debt_usd = total_debt_usd + ?, total_debt_cdf = total_debt_cdf + ? WHERE id=?")
                    ->execute([($currency === 'USD' ? $final_amount : 0), ($currency === 'CDF' ? $final_amount : 0), $client_id]);
            }

            $pdo->commit();
            header("Location: invoice.php?id=" . $sale_id);
            exit();
        } catch(Exception $e) { $pdo->rollBack(); $error = "Erreur : " . $e->getMessage(); }
    }
}

// --- DONNÉES ---
$products = $pdo->query("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id=c.id WHERE p.stock_quantity > 0 ORDER BY p.name ASC")->fetchAll();
$clients = $pdo->query("SELECT * FROM clients ORDER BY name ASC")->fetchAll();
$sales = $pdo->query("SELECT s.*, c.name as client_name, u.username as seller FROM sales s LEFT JOIN clients c ON s.client_id=c.id LEFT JOIN users u ON s.user_id=u.id ORDER BY s.created_at DESC LIMIT 50")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <title>Ventes - LXTRONIC</title>
    <?php include 'includes/head.php'; ?>
    <style>
        .fab { position: fixed; bottom: 30px; right: 30px; width: 64px; height: 64px; border-radius: 50%; background: var(--color-primary); color: white; display: flex; align-items: center; justify-content: center; box-shadow: 0 8px 24px rgba(37, 99, 235, 0.4); cursor: pointer; transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); z-index: 100; border: none; }
        .fab:hover { transform: scale(1.1) rotate(0deg); background: var(--color-primary-dark); }
        .fab svg { width: 32px; height: 32px; }

        /* Mini Dialog Styles */
        #pos-overlay { 
            position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(8px); 
            z-index: 2000; display: none; align-items: center; justify-content: center; padding: 1rem;
            animation: fadeIn 0.2s ease;
        }
        #pos-overlay.open { display: flex; }

        #pos-dialog {
            background: white; width: 100%; max-width: 580px; max-height: 90vh; border-radius: 24px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.3); display: flex; flex-direction: column; overflow: hidden;
            animation: zoomIn 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        @media (max-width: 768px) {
            #pos-dialog { max-height: 100vh; border-radius: 0; }
            .summary-grid { grid-template-columns: 1fr; }
        }

        @keyframes zoomIn { from { opacity: 0; transform: scale(0.9); } to { opacity: 1; transform: scale(1); } }

        .dialog-header { padding: 1.5rem; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #f1f5f9; }
        .dialog-body { flex: 1; overflow-y: auto; padding: 1.5rem; display: flex; flex-direction: column; gap: 1.5rem; }
        .dialog-footer { padding: 1.5rem; background: #f8fafc; border-top: 1px solid #e2e8f0; }

        /* Search & Results */
        .search-container { position: relative; }
        .search-container input { width: 100%; padding: 14px 16px 14px 44px; border-radius: 12px; border: 2px solid #e2e8f0; font-size: 1rem; font-weight: 500; transition: 0.2s; }
        .search-container input:focus { border-color: var(--color-primary); box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1); outline: none; }
        .search-container svg { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); width: 20px; color: #94a3b8; }

        .search-results { 
            display: none; position: absolute; top: calc(100% + 4px); left: 0; right: 0; 
            background: white; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: var(--shadow-xl);
            max-height: 250px; overflow-y: auto; z-index: 10;
        }
        .search-results.open { display: block; }
        .res-item { padding: 12px 16px; border-bottom: 1px solid #f1f5f9; cursor: pointer; display: flex; justify-content: space-between; align-items: center; }
        .res-item:hover { background: #f8fafc; }
        .res-item-name { font-weight: 700; color: var(--color-slate-800); }
        .res-item-price { color: var(--color-primary); font-weight: 800; }

        /* Cart */
        .cart-mini { display: flex; flex-direction: column; gap: 8px; }
        .cart-title { font-size: 0.75rem; font-weight: 800; text-transform: uppercase; color: #94a3b8; letter-spacing: 0.05em; margin-bottom: 4px; }
        .cart-item-mini { display: grid; grid-template-columns: 1fr auto auto; gap: 12px; align-items: center; padding: 10px; background: #f8fafc; border-radius: 10px; }
        .qty-ctrl { display: flex; align-items: center; gap: 8px; background: white; padding: 2px 8px; border-radius: 20px; border: 1px solid #e2e8f0; }
        .qty-ctrl button { background: none; border: none; font-weight: 800; color: var(--color-primary); cursor: pointer; padding: 0 4px; }

        /* Summary Area */
        .summary-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        .currency-btn-mini { flex: 1; padding: 10px; border-radius: 10px; border: 2px solid #e2e8f0; background: white; font-weight: 700; cursor: pointer; transition: 0.2s; }
        .currency-btn-mini.active { border-color: var(--color-primary); color: var(--color-primary); background: rgba(37, 99, 235, 0.05); }

        .discount-input { width: 70px; padding: 4px 8px; border-radius: 8px; border: 1px solid #e2e8f0; font-size: 0.8rem; font-weight: 700; text-align: right; }
        .discount-input:focus { border-color: var(--color-primary); outline: none; }
        .discount-label { font-size: 0.7rem; color: #94a3b8; font-weight: 700; margin-bottom: 2px; display: block; }

    </style>
</head>
<body>
<div class="app-layout">
    <?php include 'includes/sidebar.php'; ?>
    <?php include 'includes/responsive_header.php'; ?>

    <main class="main-content">
        <header class="page-header">
            <div>
                <h1>Historique des Ventes</h1>
                <p>Transactions récentes et facturation.</p>
            </div>
        </header>

        <div class="card">
            <div class="data-table-wrapper table-responsive">
                <table class="data-table">
                    <thead>
                        <tr><th>ID</th><th>Client</th><th>Paiement</th><th>Total</th><th>Date</th><th>Facture</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach($sales as $s): ?>
                        <tr>
                            <td class="text-muted font-bold">#<?= $s['id'] ?></td>
                            <td class="col-name"><?= htmlspecialchars($s['client_name']?:'Client de passage') ?></td>
                            <td><span class="badge <?= $s['payment_type']==='credit'?'badge-red':'badge-green' ?>"><?= $s['payment_type'] ?></span></td>
                            <td class="col-amount"><?= format_price($s['total_amount'], $s['currency']) ?></td>
                            <td class="text-muted"><?= date('d/m/Y H:i', strtotime($s['created_at'])) ?></td>
                            <td><a href="invoice.php?id=<?= $s['id'] ?>" class="btn btn-ghost btn-sm">Détails</a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <button class="fab" id="btnNewSale" title="Nouvelle Vente">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
        </button>
    </main>
</div>

<!-- MINI POS DIALOG -->
<div id="pos-overlay">
    <div id="pos-dialog">
        <div class="dialog-header">
            <h2 style="font-weight:800; font-size:1.25rem;">Effectuer une vente</h2>
            <button onclick="closePOS()" style="background:none; border:none; color:#94a3b8; cursor:pointer;"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width:24px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg></button>
        </div>

        <div class="dialog-body">
            <!-- Search & Selection -->
            <div style="display:flex; flex-direction:column; gap:12px;">
                <div class="search-container">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input type="text" id="pSearch" oninput="searchProd()" placeholder="Rechercher un produit..." autocomplete="off">
                    <div id="resBox" class="search-results"></div>
                </div>

                <div class="form-group">
                    <label class="form-label">Ou sélectionner dans la liste</label>
                    <select id="pSelect" class="form-control" onchange="addFromSelect(this.value)">
                        <option value="">-- Choisir un produit --</option>
                        <?php foreach($products as $p): ?>
                            <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?> (<?= format_price($p['sell_price'], 'USD') ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Cart -->
            <div class="cart-mini">
                <p class="cart-title">Produits sélectionnés</p>
                <div id="cartList">
                    <p style="text-align:center; color:#94a3b8; padding:1.5rem; font-size:0.9rem;">Aucun produit ajouté</p>
                </div>
            </div>

            <!-- Options -->
            <div class="summary-grid">
                <div class="form-group">
                    <label class="form-label">Client</label>
                    <select id="clId" class="form-control">
                        <option value="">Client de passage</option>
                        <?php foreach($clients as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Devise</label>
                    <div style="display:flex; gap:8px;">
                        <button type="button" class="currency-btn-mini active" id="bUSD" onclick="setCur('USD')">USD ($)</button>
                        <button type="button" class="currency-btn-mini" id="bCDF" onclick="setCur('CDF')">CDF (FC)</button>
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Mode de règlement</label>
                <div style="display:flex; gap:8px;">
                    <label style="flex:1; cursor:pointer;"><input type="radio" name="pay_type" id="mCash" value="comptant" checked style="display:none;"><div style="text-align:center; padding:10px; border:2px solid #e2e8f0; border-radius:10px; font-weight:700; font-size:0.8rem;">💵 CASH</div></label>
                    <label style="flex:1; cursor:pointer;"><input type="radio" name="pay_type" id="mCredit" value="credit" style="display:none;"><div style="text-align:center; padding:10px; border:2px solid #e2e8f0; border-radius:10px; font-weight:700; font-size:0.8rem;">📋 CRÉDIT</div></label>
                </div>
            </div>
        </div>

        <div class="dialog-footer">
            <form id="sForm" method="POST">
                <input type="hidden" name="cart_data" id="cData">
                <input type="hidden" name="client_id" id="fClId">
                <input type="hidden" name="currency" id="fCurr" value="USD">
                <input type="hidden" name="payment_type" id="fPayType">

                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.25rem;">
                    <div>
                        <p style="font-size:0.75rem; color:#64748b; font-weight:700; text-transform:uppercase; letter-spacing:0.05em;">Total à payer</p>
                        <h3 id="tDisp" style="font-size:1.75rem; font-weight:900; color:var(--color-slate-900);">$0.00</h3>
                    </div>
                    <p id="sDisp" style="font-size:0.8rem; font-weight:700; color:var(--color-primary);"></p>
                </div>

                <button type="button" onclick="doSale()" class="btn btn-primary btn-lg btn-full" style="padding:18px; border-radius:16px;">
                    CONFIRMER LA VENTE
                </button>
            </form>
        </div>
    </div>
</div>

<script>
    const prods = <?= json_encode($products) ?>;
    const rate = <?= $usd_to_cdf ?>;
    let cart = []; let cur = 'USD';

    const overlay = document.getElementById('pos-overlay');
    const pSearch = document.getElementById('pSearch');
    const resBox = document.getElementById('resBox');

    document.getElementById('btnNewSale').onclick = () => { overlay.classList.add('open'); pSearch.focus(); };
    function closePOS() { overlay.classList.remove('open'); cart=[]; render(); pSearch.value=''; }

    const fmt = (n, c) => c==='CDF' ? n.toLocaleString('fr-FR')+' FC' : '$'+n.toFixed(2);

    function searchProd() {
        const q = pSearch.value.toLowerCase();
        if(!q) { resBox.classList.remove('open'); return; }
        const matches = prods.filter(p => p.name.toLowerCase().includes(q)).slice(0, 5);
        if(matches.length) {
            resBox.innerHTML = matches.map(m => `<div class="res-item" onclick='addToCart(${JSON.stringify(m)})'><span class="res-item-name">${m.name}</span><span class="res-item-price">$${m.sell_price}</span></div>`).join('');
            resBox.classList.add('open');
        } else { resBox.classList.remove('open'); }
    }

    function addToCart(p) {
        let it = cart.find(x => x.id == p.id);
        if (it) { it.quantity++; } else { cart.push({...p, quantity:1, price:parseFloat(p.sell_price), discount:0}); }
        pSearch.value=''; resBox.classList.remove('open'); 
        document.getElementById('pSelect').value = ''; // Reset select
        render();
    }

    function addFromSelect(id) {
        if (!id) return;
        const p = prods.find(x => x.id == id);
        if (p) addToCart(p);
    }

    function updDisc(id, val) {
        let it = cart.find(x => x.id == id);
        if (!it) return;
        let d = parseFloat(val) || 0;
        const maxD = it.price * 0.10;
        if (d > maxD) d = maxD;
        it.discount = d;
        render();
    }

    function updQty(id, d) {
        let it = cart.find(x => x.id == id);
        if (!it) return;
        it.quantity += d;
        if (it.quantity <= 0) cart = cart.filter(x => x.id != id);
        render();
    }

    function setCur(c) {
        cur = c;
        document.getElementById('bUSD').classList.toggle('active', c==='USD');
        document.getElementById('bCDF').classList.toggle('active', c==='CDF');
        render();
    }

    function render() {
        const list = document.getElementById('cartList');
        const tDisp = document.getElementById('tDisp');
        const sDisp = document.getElementById('sDisp');

        if(!cart.length) {
            list.innerHTML = '<p style="text-align:center; color:#94a3b8; padding:1.5rem; font-size:0.9rem;">Aucun produit ajouté</p>';
            tDisp.innerText = fmt(0, cur); sDisp.innerText = ''; return;
        }

        let totalUSD = 0;
        list.innerHTML = cart.map(it => {
            const sub = (it.price - it.discount) * it.quantity; totalUSD += sub;
            return `<div class="cart-item-mini" style="grid-template-columns: 1fr auto auto auto;">
                <div>
                    <span style="font-weight:700; font-size:0.85rem;">${it.name}</span>
                    <div style="margin-top:4px;">
                        <span class="discount-label">Réduction ($)</span>
                        <input type="number" class="discount-input" value="${it.discount}" step="0.01" min="0" max="${(it.price * 0.1).toFixed(2)}" onchange="updDisc(${it.id}, this.value)">
                    </div>
                </div>
                <div class="qty-ctrl"><button onclick="updQty(${it.id},-1)">−</button><span>${it.quantity}</span><button onclick="updQty(${it.id},1)">+</button></div>
                <div style="text-align:right;">
                    <div style="font-weight:800; color:var(--color-slate-700);">${fmt(cur==='CDF'?sub*rate:sub, cur)}</div>
                    ${it.discount > 0 ? `<div style="font-size:0.7rem; color:var(--color-primary); font-weight:700;">-${fmt(cur==='CDF'?it.discount*rate*it.quantity:it.discount*it.quantity, cur)}</div>` : ''}
                </div>
            </div>`;
        }).join('');

        const final = cur==='CDF' ? totalUSD*rate : totalUSD;
        tDisp.innerText = fmt(final, cur);
        sDisp.innerText = cur==='CDF' ? '≈ ' + fmt(totalUSD, 'USD') : '≈ ' + fmt(totalUSD*rate, 'CDF');
    }

    function doSale() {
        if(!cart.length) return alert('Panier vide!');
        const payType = document.querySelector('input[name="pay_type"]:checked').value;
        const clientId = document.getElementById('clId').value;
        if(payType === 'credit' && !clientId) return alert('Sélectionnez un client pour le crédit.');

        document.getElementById('cData').value = JSON.stringify(cart);
        document.getElementById('fClId').value = clientId;
        document.getElementById('fCurr').value = cur;
        document.getElementById('fPayType').value = payType;
        document.getElementById('sForm').submit();
    }

    // Modal behavior for radio buttons active state
    document.querySelectorAll('input[name="pay_type"]').forEach(rad => {
        rad.addEventListener('change', () => {
            document.querySelectorAll('input[name="pay_type"]').forEach(r => r.nextElementSibling.style.borderColor = '#e2e8f0');
            rad.nextElementSibling.style.borderColor = 'var(--color-primary)';
            rad.nextElementSibling.style.background = 'rgba(37, 99, 235, 0.05)';
        });
    });
    // Initial state trigger
    document.getElementById('mCash').dispatchEvent(new Event('change'));
</script>
</body>
</html>
