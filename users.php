<?php
require_once 'config/db.php';
require_once 'includes/helpers.php';
require_admin();

$message = ""; $error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'save') {
        $id = $_POST['id'] ?? null;
        $username = trim($_POST['username'] ?? '');
        $role = $_POST['role'] ?? 'vendeur';
        $password = $_POST['password'] ?? '';
        if (!empty($username)) {
            if ($id) {
                if (!empty($password)) {
                    $pdo->prepare("UPDATE users SET username=?,role=?,password=? WHERE id=?")->execute([$username,$role,password_hash($password,PASSWORD_DEFAULT),$id]);
                } else {
                    $pdo->prepare("UPDATE users SET username=?,role=? WHERE id=?")->execute([$username,$role,$id]);
                }
                $message = "Utilisateur mis à jour.";
            } else {
                if (!empty($password)) {
                    $pdo->prepare("INSERT INTO users (username,password,role) VALUES(?,?,?)")->execute([$username,password_hash($password,PASSWORD_DEFAULT),$role]);
                    $message = "Utilisateur créé.";
                } else {
                    $error = "Mot de passe requis pour un nouvel utilisateur.";
                }
            }
        }
    } elseif ($_POST['action'] === 'delete') {
        if ($_POST['id'] != $_SESSION['user_id']) {
            $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$_POST['id']]);
            $message = "Utilisateur supprimé.";
        } else {
            $error = "Vous ne pouvez pas supprimer votre propre compte.";
        }
    }
}

$users = $pdo->query("SELECT id,username,role,created_at FROM users ORDER BY username ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <title>Utilisateurs - QuincaTech</title>
    <?php include 'includes/head.php'; ?>
</head>
<body>
<div class="app-layout">
    <?php include 'includes/sidebar.php'; ?>
    <main class="main-content">
        <header class="page-header">
            <div>
                <h1>Gestion du Personnel</h1>
                <p>Gérez les accès administrateurs et vendeurs.</p>
            </div>
            <button onclick="openUserModal()" class="btn btn-primary btn-lg">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                Nouvel Utilisateur
            </button>
        </header>

        <?php if ($message): ?><div class="alert alert-success animate-pulse"><?= $message ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

        <div class="card">
            <div class="data-table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr><th>Utilisateur</th><th>Rôle</th><th>Créé le</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach($users as $u): ?>
                        <tr>
                            <td class="col-name"><?= htmlspecialchars($u['username']) ?></td>
                            <td><span class="badge <?= $u['role']==='admin'?'badge-purple':'badge-blue' ?>"><?= $u['role'] ?></span></td>
                            <td class="text-muted"><?= date('d/m/Y',strtotime($u['created_at'])) ?></td>
                            <td>
                                <div class="row-actions">
                                    <button onclick='editUser(<?= json_encode($u) ?>)' class="btn-icon btn-icon-primary">
                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </button>
                                    <form method="POST" onsubmit="return confirm('Supprimer ?');" style="display:inline">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                        <button type="submit" class="btn-icon btn-icon-danger">
                                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<!-- User Modal -->
<div id="userModal" class="modal-overlay">
    <div class="modal">
        <h3 id="userModalTitle" class="modal-title">Nouvel Utilisateur</h3>
        <form method="POST" class="modal-form">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" id="userId">
            <div class="form-group">
                <label class="form-label">Nom d'utilisateur</label>
                <input type="text" name="username" id="userName" required class="form-control">
            </div>
            <div class="form-group">
                <label class="form-label">Rôle</label>
                <select name="role" id="userRole" class="form-control">
                    <option value="vendeur">Vendeur</option>
                    <option value="admin">Administrateur</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Mot de passe <span id="passHint" class="text-muted" style="font-size:11px;">(laisser vide = inchangé)</span></label>
                <input type="password" name="password" id="userPass" class="form-control">
            </div>
            <div class="modal-actions">
                <button type="button" onclick="closeUserModal()" class="btn btn-ghost btn-full">Annuler</button>
                <button type="submit" class="btn btn-primary btn-full">Enregistrer</button>
            </div>
        </form>
    </div>
</div>
<script>
    const um = document.getElementById('userModal');
    function openUserModal() {
        document.getElementById('userModalTitle').innerText = 'Nouvel Utilisateur';
        document.getElementById('passHint').style.display = 'none';
        ['userId','userName','userPass'].forEach(id=>document.getElementById(id).value='');
        document.getElementById('userRole').value = 'vendeur';
        document.getElementById('userPass').required = true;
        um.classList.add('open');
    }
    function closeUserModal() { um.classList.remove('open'); }
    function editUser(u) {
        document.getElementById('userModalTitle').innerText = 'Modifier Utilisateur';
        document.getElementById('passHint').style.display = 'inline';
        document.getElementById('userId').value = u.id;
        document.getElementById('userName').value = u.username;
        document.getElementById('userRole').value = u.role;
        document.getElementById('userPass').value = '';
        document.getElementById('userPass').required = false;
        um.classList.add('open');
    }
    um.addEventListener('click', e => { if(e.target===um) closeUserModal(); });
</script>
</body>
</html>
