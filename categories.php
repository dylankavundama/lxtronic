<?php
require_once 'config/db.php';
require_once 'includes/helpers.php';
check_auth();

$message = ""; $error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'save') {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $id = $_POST['id'] ?? null;
        if (!empty($name)) {
            if ($id) {
                $pdo->prepare("UPDATE categories SET name=?, description=? WHERE id=?")->execute([$name,$description,$id]);
                $message = "Catégorie mise à jour.";
            } else {
                $pdo->prepare("INSERT INTO categories (name,description) VALUES(?,?)")->execute([$name,$description]);
                $message = "Catégorie ajoutée.";
            }
        }
    } elseif ($_POST['action'] === 'delete') {
        try {
            $pdo->prepare("DELETE FROM categories WHERE id=?")->execute([$_POST['id']]);
            $message = "Catégorie supprimée.";
        } catch(Exception $e) {
            $error = "Impossible : cette catégorie est liée à des produits.";
        }
    }
}

$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <title>Catégories - LXTRONIC</title>
    <?php include 'includes/head.php'; ?>
</head>
<body>
<div class="app-layout">
    <?php include 'includes/sidebar.php'; ?>
    <?php include 'includes/responsive_header.php'; ?>
    <main class="main-content">
        <header class="page-header">
            <div>
                <h1>Gestion des Catégories</h1>
                <p>Organisez vos produits par type.</p>
            </div>
            <button onclick="openModal()" class="btn btn-primary btn-lg">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Nouvelle Catégorie
            </button>
        </header>

        <?php if($message): ?><div class="alert alert-success animate-pulse"><?= $message ?></div><?php endif; ?>
        <?php if($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

        <div class="card">
            <div class="data-table-wrapper table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Description</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($categories)): ?>
                            <tr><td colspan="3" class="table-empty">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                                Aucune catégorie trouvée.
                            </td></tr>
                        <?php else: ?>
                            <?php foreach($categories as $cat): ?>
                            <tr>
                                <td class="col-name"><?= htmlspecialchars($cat['name']) ?></td>
                                <td><?= htmlspecialchars($cat['description']) ?></td>
                                <td>
                                    <div class="row-actions">
                                        <button onclick='editCategory(<?= json_encode($cat) ?>)' class="btn-icon btn-icon-primary">
                                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        </button>
                                        <form method="POST" onsubmit="return confirm('Supprimer cette catégorie ?');" style="display:inline;">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $cat['id'] ?>">
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

<!-- Modal -->
<div id="catModal" class="modal-overlay">
    <div class="modal">
        <h3 id="modalTitle" class="modal-title">Ajouter une catégorie</h3>
        <form method="POST" class="modal-form">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" id="catId">
            <div class="form-group">
                <label class="form-label">Nom de la catégorie</label>
                <input type="text" name="name" id="catName" required class="form-control">
            </div>
            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea name="description" id="catDesc" rows="3" class="form-control"></textarea>
            </div>
            <div class="modal-actions">
                <button type="button" onclick="closeModal()" class="btn btn-ghost btn-full">Annuler</button>
                <button type="submit" class="btn btn-primary btn-full">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<script>
    const m = document.getElementById('catModal');
    function openModal() {
        document.getElementById('modalTitle').innerText = 'Nouvelle Catégorie';
        ['catId','catName','catDesc'].forEach(id => document.getElementById(id).value = '');
        m.classList.add('open');
    }
    function closeModal() { m.classList.remove('open'); }
    function editCategory(cat) {
        document.getElementById('modalTitle').innerText = 'Modifier la catégorie';
        document.getElementById('catId').value = cat.id;
        document.getElementById('catName').value = cat.name;
        document.getElementById('catDesc').value = cat.description;
        m.classList.add('open');
    }
    m.addEventListener('click', e => { if(e.target===m) closeModal(); });
</script>
</body>
</html>
