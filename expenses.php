<?php
require_once 'config/db.php';
require_once 'includes/helpers.php';
check_auth();

$message = ""; $error = "";
$usd_to_cdf = (float)get_setting('usd_to_cdf', 2800);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'save') {
        $title = trim($_POST['title'] ?? '');
        $amount = (float)($_POST['amount'] ?? 0);
        $currency = $_POST['currency'] ?? 'USD';
        $description = trim($_POST['description'] ?? '');
        $date = $_POST['expense_date'] ?? date('Y-m-d');
        if (!empty($title) && $amount > 0) {
            // Calculer le solde global disponible
            $stmt_sales = $pdo->prepare("SELECT SUM(total_amount) FROM sales WHERE payment_type = 'comptant' AND currency = ?");
            $stmt_sales->execute([$currency]);
            $cash_sales = $stmt_sales->fetchColumn() ?: 0;

            $stmt_payments = $pdo->prepare("SELECT SUM(amount_paid) FROM payments WHERE currency = ?");
            $stmt_payments->execute([$currency]);
            $debt_payments = $stmt_payments->fetchColumn() ?: 0;

            $stmt_expenses = $pdo->prepare("SELECT SUM(amount) FROM expenses WHERE currency = ?");
            $stmt_expenses->execute([$currency]);
            $past_expenses = $stmt_expenses->fetchColumn() ?: 0;

            $available_balance = ($cash_sales + $debt_payments) - $past_expenses;

            if ($amount > $available_balance) {
                $error = "Fonds insuffisants en caisse. Solde disponible : " . format_price($available_balance, $currency);
                if (isset($_POST['is_ajax']) || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)) {
                    echo json_encode(['success' => false, 'message' => $error]); // used as message in AJAX logic below
                    exit;
                }
            } else {
                $pdo->prepare("INSERT INTO expenses (user_id,title,amount,currency,description,expense_date) VALUES(?,?,?,?,?,?)")->execute([$_SESSION['user_id'],$title,$amount,$currency,$description,$date]);
                $message = "Dépense enregistrée.";
                if (isset($_POST['is_ajax']) || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)) {
                    echo json_encode(['success' => true, 'message' => $message]);
                    exit;
                }
            }
        }
    } elseif ($_POST['action'] === 'delete') {
        $pdo->prepare("DELETE FROM expenses WHERE id=?")->execute([$_POST['id']]);
        $message = "Dépense supprimée.";
        if (isset($_POST['is_ajax']) || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)) {
            echo json_encode(['success' => true, 'message' => $message]);
            exit;
        }
    }
}

$expenses = $pdo->query("SELECT e.*, u.username FROM expenses e JOIN users u ON e.user_id=u.id ORDER BY e.expense_date DESC LIMIT 50")->fetchAll();

// Totaux du mois par devise
$total_usd = $pdo->query("SELECT SUM(amount) FROM expenses WHERE currency='USD' AND MONTH(expense_date)=MONTH(CURDATE()) AND YEAR(expense_date)=YEAR(CURDATE())")->fetchColumn() ?: 0;
$total_cdf = $pdo->query("SELECT SUM(amount) FROM expenses WHERE currency='CDF' AND MONTH(expense_date)=MONTH(CURDATE()) AND YEAR(expense_date)=YEAR(CURDATE())")->fetchColumn() ?: 0;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <title>Dépenses - LXTRONIC</title>
    <?php include 'includes/head.php'; ?>
</head>
<body>
<div class="app-layout">
    <?php include 'includes/sidebar.php'; ?>
    <?php include 'includes/responsive_header.php'; ?>

    <main class="main-content">
        <header class="page-header">
            <div>
                <h1>Gestion des Dépenses</h1>
                <p>
                    Budget mensuel : 
                    <strong class="text-danger"><?= format_price($total_usd, 'USD') ?></strong> et 
                    <strong class="text-danger"><?= format_price($total_cdf, 'CDF') ?></strong>
                </p>
            </div>
            <button onclick="document.getElementById('expModal').classList.add('open')" class="btn btn-danger btn-lg">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Nouvelle Dépense
            </button>
        </header>

        <?php if ($message): ?><div class="alert alert-success animate-pulse"><?= $message ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger animate-pulse"><?= $error ?></div><?php endif; ?>

        <div class="card">
            <div class="data-table-wrapper table-responsive">
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
                                <td class="col-amount-danger">− <?= format_price($e['amount'], $e['currency']) ?></td>
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
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Montant</label>
                    <input type="number" step="0.01" name="amount" required class="form-control form-control-lg" style="font-weight:800;color:var(--color-danger);">
                </div>
                <div class="form-group">
                    <label class="form-label">Devise</label>
                    <select name="currency" class="form-control form-control-lg">
                        <option value="USD">USD ($)</option>
                        <option value="CDF">CDF (FC)</option>
                    </select>
                </div>
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

    // --- OFFLINE SUPPORT ---
    function updateStatus() {
        const h1 = document.querySelector('h1');
        let status = document.getElementById('conn-status');
        if (!status) {
            status = document.createElement('span');
            status.id = 'conn-status';
            status.style = 'margin-left:8px; font-size:0.75rem; vertical-align:middle;';
            h1.parentElement.appendChild(status);
        }
        if (navigator.onLine) {
            status.innerText = '● En Ligne';
            status.style.color = 'var(--color-success)';
        } else {
            status.innerText = '● Hors-Ligne';
            status.style.color = 'var(--color-danger)';
        }
    }
    window.addEventListener('online', updateStatus);
    window.addEventListener('offline', updateStatus);
    window.addEventListener('load', updateStatus);

    // Intercept Expense Form
    document.querySelector('#expModal form').addEventListener('submit', async function(e) {
        if (!navigator.onLine) {
            e.preventDefault();
            const formData = new FormData(this);
            const payload = {
                title: formData.get('title'),
                amount: formData.get('amount'),
                currency: formData.get('currency'),
                description: formData.get('description'),
                expense_date: formData.get('expense_date')
            };

            await queueAction('expense', payload);
            alert("💸 Dépense enregistrée en local.\nElle sera synchronisée dès le retour de la connexion.");
            document.getElementById('expModal').classList.remove('open');
        }
    });
</script>
</body>
</html>
