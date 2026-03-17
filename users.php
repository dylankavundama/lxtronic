<?php
require_once 'config/db.php';
require_once 'includes/helpers.php';
require_admin(); // Sécurité : Seul l'admin a accès

$message = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'save') {
        $id = $_POST['id'] ?? null;
        $username = $_POST['username'] ?? '';
        $role = $_POST['role'] ?? 'vendeur';
        $password = $_POST['password'] ?? '';

        if (!empty($username)) {
            if ($id) {
                if (!empty($password)) {
                    $hashed = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET username = ?, role = ?, password = ? WHERE id = ?");
                    $stmt->execute([$username, $role, $hashed, $id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET username = ?, role = ? WHERE id = ?");
                    $stmt->execute([$username, $role, $id]);
                }
                $message = "Utilisateur mis à jour.";
            } else {
                if (!empty($password)) {
                    $hashed = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
                    $stmt->execute([$username, $hashed, $role]);
                    $message = "Utilisateur créé.";
                } else {
                    $error = "Le mot de passe est requis pour un nouvel utilisateur.";
                }
            }
        }
    } elseif ($_POST['action'] === 'delete') {
        $id = $_POST['id'];
        if ($id != $_SESSION['user_id']) {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$id]);
            $message = "Utilisateur supprimé.";
        } else {
            $error = "Vous ne pouvez pas supprimer votre propre compte.";
        }
    }
}

$users = $pdo->query("SELECT id, username, role, created_at FROM users ORDER BY username ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr" class="bg-[#F8FAFC]">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Utilisateurs - QuincaTech</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Outfit', sans-serif; }</style>
</head>
<body class="flex">
    <?php include 'includes/sidebar.php'; ?>

    <main class="ml-64 p-8 flex-1">
        <header class="flex justify-between items-center mb-10">
            <div>
                <h1 class="text-3xl font-bold text-slate-800">Gestion du Personnel</h1>
                <p class="text-slate-500 mt-1">Gérez les accès administrateurs et vendeurs.</p>
            </div>
            <button onclick="openUserModal()" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-3 rounded-2xl shadow-lg shadow-blue-200 transition-all flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" stroke-width="2"></path></svg>
                Nouvel Utilisateur
            </button>
        </header>

        <?php if ($message): ?>
            <div class="bg-green-50 text-green-600 p-4 rounded-2xl mb-6 border border-green-100"><?= $message ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="bg-red-50 text-red-600 p-4 rounded-2xl mb-6 border border-red-100"><?= $error ?></div>
        <?php endif; ?>

        <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
            <table class="w-full text-left">
                <thead class="bg-slate-50 border-b border-slate-100 text-slate-500 uppercase text-xs font-bold tracking-wider">
                    <tr>
                        <th class="px-6 py-4">Utilisateur</th>
                        <th class="px-6 py-4">Rôle</th>
                        <th class="px-6 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php foreach ($users as $u): ?>
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="px-6 py-4 font-semibold text-slate-700"><?= $u['username'] ?></td>
                            <td class="px-6 py-4">
                                <span class="px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider <?= $u['role']=='admin' ? 'bg-purple-50 text-purple-600' : 'bg-blue-50 text-blue-600' ?>">
                                    <?= $u['role'] ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex justify-end gap-2">
                                    <button onclick='editUser(<?= json_encode($u) ?>)' class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" stroke-width="2"></path></svg>
                                    </button>
                                    <form method="POST" onsubmit="return confirm('Supprimer cet utilisateur ?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                        <button type="submit" class="p-2 text-red-600 hover:bg-red-50 rounded-lg transition-colors">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" stroke-width="2"></path></svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>

    <!-- User Modal -->
    <div id="userModal" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-[100] hidden items-center justify-center p-4">
        <div class="bg-white rounded-3xl w-full max-w-md shadow-2xl p-8">
            <h3 id="modalTitle" class="text-xl font-bold text-slate-800 mb-6">Nouvel Utilisateur</h3>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" id="userId">
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">Nom d'utilisateur</label>
                    <input type="text" name="username" id="userName" required class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-blue-100">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">Rôle</label>
                    <select name="role" id="userRole" class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-blue-100">
                        <option value="vendeur">Vendeur</option>
                        <option value="admin">Administrateur</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">Mot de passe (laisser vide pour ne pas changer)</label>
                    <input type="password" name="password" id="userPass" class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-blue-100">
                </div>
                <div class="flex gap-4 pt-4">
                    <button type="button" onclick="closeUserModal()" class="flex-1 px-6 py-3 rounded-xl font-semibold bg-slate-100 text-slate-600 hover:bg-slate-200 transition-all">Annuler</button>
                    <button type="submit" class="flex-1 px-6 py-3 rounded-xl font-semibold bg-blue-600 text-white hover:bg-blue-700 transition-all">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openUserModal() {
            document.getElementById('modalTitle').innerText = "Nouvel Utilisateur";
            document.getElementById('userId').value = "";
            document.getElementById('userName').value = "";
            document.getElementById('userRole').value = "vendeur";
            document.getElementById('userPass').required = true;
            document.getElementById('userModal').classList.replace('hidden', 'flex');
        }
        function closeUserModal() {
            document.getElementById('userModal').classList.replace('flex', 'hidden');
        }
        function editUser(u) {
            document.getElementById('modalTitle').innerText = "Modifier Utilisateur";
            document.getElementById('userId').value = u.id;
            document.getElementById('userName').value = u.username;
            document.getElementById('userRole').value = u.role;
            document.getElementById('userPass').required = false;
            document.getElementById('userModal').classList.replace('hidden', 'flex');
        }
    </script>
</body>
</html>
