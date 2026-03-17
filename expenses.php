<?php
require_once 'config/db.php';
require_once 'includes/helpers.php';
check_auth();

$message = "";
$error = "";

// Action : Ajouter/Supprimer une dépense
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'save') {
        $title = $_POST['title'] ?? '';
        $amount = $_POST['amount'] ?? 0;
        $description = $_POST['description'] ?? '';
        $date = $_POST['expense_date'] ?? date('Y-m-d');

        if (!empty($title) && $amount > 0) {
            $stmt = $pdo->prepare("INSERT INTO expenses (user_id, title, amount, description, expense_date) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$_SESSION['user_id'], $title, $amount, $description, $date]);
            $message = "Dépense enregistrée.";
        }
    } elseif ($_POST['action'] === 'delete') {
        $id = $_POST['id'];
        $stmt = $pdo->prepare("DELETE FROM expenses WHERE id = ?");
        $stmt->execute([$id]);
        $message = "Dépense supprimée.";
    }
}

$expenses = $pdo->query("SELECT e.*, u.username FROM expenses e JOIN users u ON e.user_id = u.id ORDER BY e.expense_date DESC LIMIT 50")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr" class="bg-[#F8FAFC]">
<head>
    <meta charset="UTF-8">
    <title>Dépenses - QuincaTech</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Outfit', sans-serif; }</style>
</head>
<body class="flex">
    <?php include 'includes/sidebar.php'; ?>

    <main class="ml-64 p-8 flex-1">
        <header class="flex justify-between items-center mb-10">
            <div>
                <h1 class="text-3xl font-bold text-slate-800">Gestion des Dépenses</h1>
                <p class="text-slate-500 mt-1">Suivi des sorties d'argent (loyer, électricité, etc.).</p>
            </div>
            <button onclick="document.getElementById('expenseModal').classList.replace('hidden', 'flex')" class="bg-red-600 hover:bg-red-700 text-white font-semibold px-6 py-3 rounded-2xl shadow-lg shadow-red-200 transition-all flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 4v16m8-8H4" stroke-width="2"></path></svg>
                Nouvelle Dépense
            </button>
        </header>

        <?php if ($message): ?>
            <div class="bg-green-50 text-green-600 p-4 rounded-2xl mb-6 border border-green-100"><?= $message ?></div>
        <?php endif; ?>

        <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
            <table class="w-full text-left">
                <thead class="bg-slate-50 border-b border-slate-100 text-slate-500 uppercase text-xs font-bold tracking-wider">
                    <tr>
                        <th class="px-6 py-4">Titre</th>
                        <th class="px-6 py-4">Date</th>
                        <th class="px-6 py-4">Enregistré par</th>
                        <th class="px-6 py-4 text-right">Montant</th>
                        <th class="px-6 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php if (empty($expenses)): ?>
                        <tr><td colspan="5" class="px-6 py-10 text-center text-slate-400">Aucune dépense enregistrée.</td></tr>
                    <?php else: ?>
                        <?php foreach ($expenses as $e): ?>
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="px-6 py-4 font-semibold text-slate-700">
                                    <?= $e['title'] ?>
                                    <p class="text-xs text-slate-400 font-normal"><?= $e['description'] ?></p>
                                </td>
                                <td class="px-6 py-4 text-slate-500"><?= date('d/m/Y', strtotime($e['expense_date'])) ?></td>
                                <td class="px-6 py-4 text-slate-500"><?= $e['username'] ?></td>
                                <td class="px-6 py-4 text-right font-black text-red-600">- <?= format_price($e['amount']) ?></td>
                                <td class="px-6 py-4 text-right">
                                    <form method="POST" onsubmit="return confirm('Supprimer cette dépense ?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $e['id'] ?>">
                                        <button type="submit" class="p-2 text-slate-300 hover:text-red-600 transition-colors">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" stroke-width="2"></path></svg>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

    <!-- Expense Modal -->
    <div id="expenseModal" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-[100] hidden items-center justify-center p-4">
        <div class="bg-white rounded-3xl w-full max-w-md shadow-2xl p-8">
            <h3 class="text-xl font-bold text-slate-800 mb-6">Ajouter une dépense</h3>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="save">
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">Titre / Objet</label>
                    <input type="text" name="title" required class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-red-100">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">Montant</label>
                    <input type="number" step="0.01" name="amount" required class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-red-100 font-bold">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">Date</label>
                    <input type="date" name="expense_date" value="<?= date('Y-m-d') ?>" required class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-red-100">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">Description</label>
                    <textarea name="description" rows="2" class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-red-100"></textarea>
                </div>
                <div class="flex gap-4 pt-4">
                    <button type="button" onclick="document.getElementById('expenseModal').classList.replace('flex', 'hidden')" class="flex-1 px-6 py-3 rounded-xl font-semibold bg-slate-100 text-slate-600 hover:bg-slate-200 transition-all">Annuler</button>
                    <button type="submit" class="flex-1 px-6 py-3 rounded-xl font-semibold bg-red-600 text-white hover:bg-red-700 transition-all">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
