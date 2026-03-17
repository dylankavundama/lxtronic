<?php
require_once 'config/db.php';
require_once 'includes/helpers.php';
check_auth();

// Message flash
$message = "";
$error = "";

// Ajouter / Modifier une catégorie
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'save') {
        $name = $_POST['name'] ?? '';
        $description = $_POST['description'] ?? '';
        $id = $_POST['id'] ?? null;

        if (!empty($name)) {
            if ($id) {
                $stmt = $pdo->prepare("UPDATE categories SET name = ?, description = ? WHERE id = ?");
                $stmt->execute([$name, $description, $id]);
                $message = "Catégorie mise à jour avec succès.";
            } else {
                $stmt = $pdo->prepare("INSERT INTO categories (name, description) VALUES (?, ?)");
                $stmt->execute([$name, $description]);
                $message = "Catégorie ajoutée avec succès.";
            }
        }
    } elseif ($_POST['action'] === 'delete') {
        $id = $_POST['id'] ?? null;
        if ($id) {
            try {
                $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
                $stmt->execute([$id]);
                $message = "Catégorie supprimée.";
            } catch (Exception $e) {
                $error = "Impossible de supprimer cette catégorie car elle est liée à des produits.";
            }
        }
    }
}

$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catégories - QuincaTech</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Outfit', sans-serif; }</style>
</head>
<body class="bg-[#F8FAFC]">
    <?php include 'includes/sidebar.php'; ?>

    <main class="ml-64 p-8 min-h-screen">
        <header class="flex justify-between items-center mb-10">
            <div>
                <h1 class="text-3xl font-bold text-slate-800">Gestion des Catégories</h1>
                <p class="text-slate-500 mt-1">Organisez vos produits par type.</p>
            </div>
            <button onclick="openModal()" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-3 rounded-2xl shadow-lg shadow-blue-200 transition-all flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 4v16m8-8H4" stroke-width="2"></path></svg>
                Nouvelle Catégorie
            </button>
        </header>

        <?php if ($message): ?>
            <div class="bg-green-50 text-green-600 p-4 rounded-2xl mb-6 flex items-center border border-green-100 animate-pulse">
                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                <?= $message ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="bg-red-50 text-red-600 p-4 rounded-2xl mb-6 flex items-center border border-red-100">
                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>
                <?= $error ?>
            </div>
        <?php endif; ?>

        <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
            <table class="w-full text-left">
                <thead class="bg-slate-50 border-b border-slate-100 text-slate-500 uppercase text-xs font-bold tracking-wider">
                    <tr>
                        <th class="px-6 py-4">Nom</th>
                        <th class="px-6 py-4">Description</th>
                        <th class="px-6 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php if (empty($categories)): ?>
                        <tr><td colspan="3" class="px-6 py-10 text-center text-slate-400">Aucune catégorie trouvée.</td></tr>
                    <?php else: ?>
                        <?php foreach ($categories as $cat): ?>
                            <tr class="hover:bg-slate-50 transition-colors group">
                                <td class="px-6 py-4 font-semibold text-slate-700"><?= $cat['name'] ?></td>
                                <td class="px-6 py-4 text-slate-500"><?= $cat['description'] ?></td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex justify-end gap-2">
                                        <button onclick='editCategory(<?= json_encode($cat) ?>)' class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" stroke-width="2"></path></svg>
                                        </button>
                                        <form method="POST" onsubmit="return confirm('Supprimer cette catégorie ?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $cat['id'] ?>">
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

    <!-- Modal Modal -->
    <div id="categoryModal" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-[100] hidden items-center justify-center p-4">
        <div class="bg-white rounded-3xl w-full max-w-md shadow-2xl p-8 transform transition-all">
            <h3 id="modalTitle" class="text-xl font-bold text-slate-800 mb-6">Ajouter une catégorie</h3>
            <form method="POST" class="space-y-5">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" id="catId">
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-2">Nom</label>
                    <input type="text" name="name" id="catName" required class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:ring-4 focus:ring-blue-100 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-2">Description</label>
                    <textarea name="description" id="catDesc" rows="3" class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:ring-4 focus:ring-blue-100 outline-none"></textarea>
                </div>
                <div class="flex gap-4">
                    <button type="button" onclick="closeModal()" class="flex-1 px-6 py-3 rounded-xl font-semibold bg-slate-100 text-slate-600 hover:bg-slate-200 transition-all">Annuler</button>
                    <button type="submit" class="flex-1 px-6 py-3 rounded-xl font-semibold bg-blue-600 text-white hover:bg-blue-700 shadow-lg shadow-blue-200 transition-all">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const modal = document.getElementById('categoryModal');
        function openModal() {
            document.getElementById('modalTitle').innerText = "Nouvelle Catégorie";
            document.getElementById('catId').value = "";
            document.getElementById('catName').value = "";
            document.getElementById('catDesc').value = "";
            modal.classList.replace('hidden', 'flex');
        }
        function closeModal() {
            modal.classList.replace('flex', 'hidden');
        }
        function editCategory(cat) {
            document.getElementById('modalTitle').innerText = "Modifier la catégorie";
            document.getElementById('catId').value = cat.id;
            document.getElementById('catName').value = cat.name;
            document.getElementById('catDesc').value = cat.description;
            modal.classList.replace('hidden', 'flex');
        }
    </script>
</body>
</html>
