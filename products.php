<?php
require_once 'config/db.php';
require_once 'includes/helpers.php';
check_auth();

$message = ""; $error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'save') {
        $id = $_POST['id'] ?? null;
        $name = trim($_POST['name'] ?? '');
        $category_id = $_POST['category_id'] ?: null;
        $buy_price = $_POST['buy_price'] ?? 0;
        $sell_price = $_POST['sell_price'] ?? 0;
        $stock_quantity = $_POST['stock_quantity'] ?? 0;
        $min_stock_threshold = $_POST['min_stock_threshold'] ?? 5;
        $description = trim($_POST['description'] ?? '');

        if (!empty($name)) {
            if ($id) {
                $pdo->prepare("UPDATE products SET name=?,category_id=?,buy_price=?,sell_price=?,stock_quantity=?,min_stock_threshold=?,description=? WHERE id=?")
                    ->execute([$name,$category_id,$buy_price,$sell_price,$stock_quantity,$min_stock_threshold,$description,$id]);
                $message = "Produit mis à jour.";
            } else {
                $pdo->prepare("INSERT INTO products (name,category_id,buy_price,sell_price,stock_quantity,min_stock_threshold,description) VALUES(?,?,?,?,?,?,?)")
                    ->execute([$name,$category_id,$buy_price,$sell_price,$stock_quantity,$min_stock_threshold,$description]);
                $message = "Produit ajouté.";
            }
        }
    } elseif ($_POST['action'] === 'delete') {
        $pdo->prepare("DELETE FROM products WHERE id=?")->execute([$_POST['id']]);
        $message = "Produit supprimé.";
    }
}

$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();
$products = $pdo->query("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id=c.id ORDER BY p.name ASC")->fetchAll();
$low_stock = array_filter($products, fn($p) => $p['stock_quantity'] <= $p['min_stock_threshold']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <title>Produits - LxTronic</title>
    <?php include 'includes/head.php'; ?>
</head>
<body>
<div class="app-layout">
    <?php include 'includes/sidebar.php'; ?>
    <main class="main-content">
        <header class="page-header">
            <div>
                <h1>Inventaire Produits</h1>
                <p>Gérez votre stock et vos prix d'achat/vente.</p>
            </div>
            <button onclick="openProductModal()" class="btn btn-primary btn-lg">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Ajouter un Produit
            </button>
        </header>

        <?php if ($message): ?><div class="alert alert-success animate-pulse"><?= $message ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

        <?php if (!empty($low_stock)): ?>
        <div class="alert-banner">
            <div class="alert-banner-icon">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            </div>
            <div>
                <p class="alert-banner-title">Alerte Stock Faible !</p>
                <p class="alert-banner-sub"><?= count($low_stock) ?> produit(s) ont atteint ou dépassé le seuil d'alerte de stock.</p>
            </div>
        </div>
        <?php endif; ?>

        <div class="card">
            <div class="data-table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Produit</th>
                            <th>Catégorie</th>
                            <th>Prix Achat</th>
                            <th>Prix Vente</th>
                            <th>Stock</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($products)): ?>
                            <tr><td colspan="6" class="table-empty">Aucun produit en stock.</td></tr>
                        <?php else: ?>
                            <?php foreach($products as $p): 
                                $stockClass = 'badge-green';
                                if ($p['stock_quantity'] <= 0) $stockClass = 'badge-red';
                                elseif ($p['stock_quantity'] <= $p['min_stock_threshold']) $stockClass = 'badge-orange';
                            ?>
                            <tr>
                                <td>
                                    <p class="col-name"><?= htmlspecialchars($p['name']) ?></p>
                                    <p class="col-sub"><?= htmlspecialchars(substr($p['description'],0,50)) ?></p>
                                </td>
                                <td><span class="badge badge-slate"><?= htmlspecialchars($p['category_name'] ?: 'Non classé') ?></span></td>
                                <td><?= format_price($p['buy_price']) ?></td>
                                <td class="col-amount"><?= format_price($p['sell_price']) ?></td>
                                <td><span class="badge <?= $stockClass ?>"><?= $p['stock_quantity'] ?></span></td>
                                <td>
                                    <div class="row-actions">
                                        <button onclick='editProduct(<?= json_encode($p) ?>)' class="btn-icon btn-icon-primary">
                                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        </button>
                                        <form method="POST" onsubmit="return confirm('Supprimer ?');" style="display:inline">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                            <button type="submit" class="btn-icon btn-icon-danger">
                                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
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
        </div>
    </main>
</div>

<!-- Product Modal -->
<div id="productModal" class="modal-overlay">
    <div class="modal modal-wide">
        <h3 id="prodModalTitle" class="modal-title">Ajouter un Produit</h3>
        <form method="POST" class="modal-form">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" id="prodId">
            <div class="form-grid">
                <div class="form-group form-grid-span">
                    <label class="form-label">Nom du produit</label>
                    <input type="text" name="name" id="prodName" required class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">Catégorie</label>
                    <select name="category_id" id="prodCat" class="form-control">
                        <option value="">Sélectionner...</option>
                        <?php foreach($categories as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Seuil alerte stock</label>
                    <input type="number" name="min_stock_threshold" id="prodThreshold" value="5" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">Prix d'achat (FCFA)</label>
                    <input type="number" step="0.01" name="buy_price" id="prodBuy" required class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">Prix de vente (FCFA)</label>
                    <input type="number" step="0.01" name="sell_price" id="prodSell" required class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">Quantité en stock</label>
                    <input type="number" name="stock_quantity" id="prodStock" value="0" class="form-control">
                </div>
                <div class="form-group form-grid-span">
                    <label class="form-label">Description</label>
                    <textarea name="description" id="prodDesc" rows="2" class="form-control"></textarea>
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" onclick="closeProductModal()" class="btn btn-ghost btn-full">Annuler</button>
                <button type="submit" class="btn btn-primary btn-full">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<script>
    const pm = document.getElementById('productModal');
    function openProductModal() {
        document.getElementById('prodModalTitle').innerText = 'Ajouter un Produit';
        ['prodId','prodName','prodBuy','prodSell','prodDesc'].forEach(id => document.getElementById(id).value='');
        document.getElementById('prodStock').value = '0';
        document.getElementById('prodThreshold').value = '5';
        document.getElementById('prodCat').value = '';
        pm.classList.add('open');
    }
    function closeProductModal() { pm.classList.remove('open'); }
    function editProduct(p) {
        document.getElementById('prodModalTitle').innerText = 'Modifier le Produit';
        document.getElementById('prodId').value = p.id;
        document.getElementById('prodName').value = p.name;
        document.getElementById('prodCat').value = p.category_id;
        document.getElementById('prodBuy').value = p.buy_price;
        document.getElementById('prodSell').value = p.sell_price;
        document.getElementById('prodStock').value = p.stock_quantity;
        document.getElementById('prodThreshold').value = p.min_stock_threshold;
        document.getElementById('prodDesc').value = p.description;
        pm.classList.add('open');
    }
    pm.addEventListener('click', e => { if(e.target===pm) closeProductModal(); });
</script>
</body>
</html>
