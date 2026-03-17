<?php
require_once 'config/db.php';
require_once 'includes/helpers.php';
check_auth();

$message = ""; $error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'save') {
        $title = trim($_POST['title'] ?? '');
        $amount = (float)($_POST['amount'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        $date = $_POST['expense_date'] ?? date('Y-m-d');
        if (!empty($title) && $amount > 0) {
            $pdo->prepare("INSERT INTO expenses (user_id,title,amount,description,expense_date) VALUES(?,?,?,?,?)")->execute([$_SESSION['user_id'],$title,$amount,$description,$date]);
            $message = "Dépense enregistrée.";
        }
    } elseif ($_POST['action'] === 'delete') {
        $pdo->prepare("DELETE FROM expenses WHERE id=?")->execute([$_POST['id']]);
        $message = "Dépense supprimée.";
    }
}

$expenses = $pdo->query("SELECT e.*, u.username FROM expenses e JOIN users u ON e.user_id=u.id ORDER BY e.expense_date DESC LIMIT 50")->fetchAll();
$total_month = $pdo->query("SELECT SUM(amount) FROM expenses WHERE MONTH(expense_date)=MONTH(CURDATE()) AND YEAR(expense_date)=YEAR(CURDATE())")->fetchColumn() ?: 0;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <title>Dépenses - QuincaTech</title>
    <?php include 'includes/head.php'; ?>
</head>
<body>
<div class="app-layout">
    <?php include 'includes/sidebar.php'; ?>
    <main class="main-content">
        <header class="page-header">
            <div>
                <h1>Gestion des Dépenses</h1>
                <p>Suivi des sorties d'argent — Total ce mois-ci : <strong class="text-danger"><?= format_price($total_month) ?></strong></p>
            </div>
            <button onclick="document.getElementById('expModal').classList.add('open')" class="btn btn-danger btn-lg">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Nouvelle Dépense
            </button>
        </header>

        <?php if ($message): ?><div class="alert alert-success animate-pulse"><?= $message ?></div><?php endif; ?>

        <div class="card">
            <div class="data-table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr><th>Titre</th><th>Date</th><th>Enregistré par</th><th>Montant</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                        <?php if(empty($expenses)): ?>
                            <tr><td colspan="5" class="table-empty">Aucune dépense enregistrée.</td></tr>
                        <?php else: ?>
                            <?php foreach($expenses as $e): ?>
                            <tr>
                                <td>
                                    <p class="col-name"><?= htmlspecialchars($e['title']) ?></p>
                                    <p class="col-sub"><?= htmlspecialchars($e['description']) ?></p>
                                </td>
                                <td><?= date('d/m/Y',strtotime($e['expense_date'])) ?></td>
                                <td class="text-muted"><?= htmlspecialchars($e['username']) ?></td>
                                <td class="col-amount-danger">− <?= format_price($e['amount']) ?></td>
                                <td>
                                    <form method="POST" onsubmit="return confirm('Supprimer ?');" style="display:flex;justify-content:flex-end;">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $e['id'] ?>">
                                        <button type="submit" class="btn-icon btn-icon-danger">
                                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </form>
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

<!-- Expense Modal -->
<div id="expModal" class="modal-overlay">
    <div class="modal">
        <h3 class="modal-title">Ajouter une dépense</h3>
        <form method="POST" class="modal-form">
            <input type="hidden" name="action" value="save">
            <div class="form-group">
                <label class="form-label">Titre / Objet</label>
                <input type="text" name="title" required class="form-control" placeholder="Ex: Loyer du magasin">
            </div>
            <div class="form-group">
                <label class="form-label">Montant (FCFA)</label>
                <input type="number" step="0.01" name="amount" required class="form-control form-control-lg" style="font-weight:800;color:var(--color-danger);">
            </div>
            <div class="form-group">
                <label class="form-label">Date</label>
                <input type="date" name="expense_date" value="<?= date('Y-m-d') ?>" required class="form-control">
            </div>
            <div class="form-group">
                <label class="form-label">Description (optionnel)</label>
                <textarea name="description" rows="2" class="form-control"></textarea>
            </div>
            <div class="modal-actions">
                <button type="button" onclick="document.getElementById('expModal').classList.remove('open')" class="btn btn-ghost btn-full">Annuler</button>
                <button type="submit" class="btn btn-danger btn-full">Enregistrer</button>
            </div>
        </form>
    </div>
</div>
<script>
    const em = document.getElementById('expModal');
    em.addEventListener('click', e => { if(e.target===em) em.classList.remove('open'); });
</script>
</body>
</html>
