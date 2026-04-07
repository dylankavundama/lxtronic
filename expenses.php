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
                // --- ANTI-DOUBLON (Vérifie si la même dépense exacte a été postée dans les 2 dernières minutes) ---
                $stmt_dup = $pdo->prepare("SELECT id FROM expenses WHERE user_id=? AND title=? AND amount=? AND currency=? AND expense_date=? AND created_at > (NOW() - INTERVAL 2 MINUTE)");
                $stmt_dup->execute([$_SESSION['user_id'], $title, $amount, $currency, $date]);
                
                if ($stmt_dup->rowCount() > 0) {
                    $error = "Cette dépense vient tout juste d'être enregistrée (doublon détecté).";
                    if (isset($_POST['is_ajax']) || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)) {
                        echo json_encode(['success' => false, 'message' => $error]);
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
        }
    } elseif ($_POST['action'] === 'delete') {
        $pdo->prepare("DELETE FROM expenses WHERE id=?")->execute([$_POST['id']]);
        $message = "Dépense supprimée.";
        if (isset($_POST['is_ajax']) || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)) {
            echo json_encode(['success' => true, 'message' => $message]);
            exit;
        }
    } elseif ($_POST['action'] === 'edit') {
        $id = $_POST['id'];
        $title = trim($_POST['title']);
        $amount = (float)$_POST['amount'];
        $currency = $_POST['currency'];
        $description = trim($_POST['description']);
        $date = $_POST['expense_date'];
        
        $pdo->prepare("UPDATE expenses SET title=?, amount=?, currency=?, description=?, expense_date=? WHERE id=?")
            ->execute([$title, $amount, $currency, $description, $date, $id]);
        $message = "Dépense modifiée avec succès.";
        if (isset($_POST['is_ajax']) || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)) {
            echo json_encode(['success' => true, 'message' => $message]);
            exit;
        }
    } elseif ($_POST['action'] === 'delete_multiple') {
        if (!empty($_POST['ids']) && is_array($_POST['ids'])) {
            $ids = implode(',', array_map('intval', $_POST['ids']));
            if (!empty($ids)) {
                $pdo->query("DELETE FROM expenses WHERE id IN ($ids)");
                $message = count($_POST['ids']) . " dépense(s) supprimée(s).";
            }
            if (isset($_POST['is_ajax']) || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)) {
                echo json_encode(['success' => true, 'message' => $message ?? '']);
                exit;
            }
        }
    }
}

// --- FILTRES ---
$filter_day = $_GET['day'] ?? '';
$filter_month = $_GET['month'] ?? ''; // Par défaut : Tout (avant c'était date('m'))
$filter_year = $_GET['year'] ?? ''; // Par défaut : Tout

$where_clause = "1=1";
$params = [];

if (!empty($filter_year)) {
    $where_clause .= " AND YEAR(expense_date) = ?";
    $params[] = $filter_year;
}
if (!empty($filter_month)) {
    $where_clause .= " AND MONTH(expense_date) = ?";
    $params[] = $filter_month;
}
if (!empty($filter_day)) {
    $where_clause .= " AND DAY(expense_date) = ?";
    $params[] = $filter_day;
}

// Requête de l'historique
$stmt_expenses = $pdo->prepare("SELECT e.*, u.username FROM expenses e JOIN users u ON e.user_id=u.id WHERE $where_clause ORDER BY e.expense_date DESC LIMIT 100");
$stmt_expenses->execute($params);
$expenses = $stmt_expenses->fetchAll();

// Totaux filtrés par devise
$stmt_usd = $pdo->prepare("SELECT SUM(amount) FROM expenses WHERE currency='USD' AND $where_clause");
$stmt_usd->execute($params);
$total_usd = $stmt_usd->fetchColumn() ?: 0;

$stmt_cdf = $pdo->prepare("SELECT SUM(amount) FROM expenses WHERE currency='CDF' AND $where_clause");
$stmt_cdf->execute($params);
$total_cdf = $stmt_cdf->fetchColumn() ?: 0;
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
        <header class="page-header" style="flex-wrap: wrap; gap: 1rem;">
            <div>
                <h1>Gestion des Dépenses</h1>
                <p>
                    Filtré : 
                    <strong class="text-danger"><?= format_price($total_usd, 'USD') ?></strong> et 
                    <strong class="text-danger"><?= format_price($total_cdf, 'CDF') ?></strong>
                </p>
            </div>
            
            <form action="" method="GET" class="header-actions" style="display: flex; gap: 8px; flex-wrap: wrap;">
                <select name="day" class="form-control" onchange="this.form.submit()" style="min-width: 100px;">
                    <option value="">Tous les jours</option>
                    <?php for($d=1; $d<=31; $d++): $dd = sprintf('%02d', $d); ?>
                        <option value="<?= $dd ?>" <?= $filter_day == $dd ? 'selected' : '' ?>><?= $dd ?></option>
                    <?php endfor; ?>
                </select>
                <select name="month" class="form-control" onchange="this.form.submit()" style="min-width: 120px;">
                    <option value="">Tous les mois</option>
                    <?php 
                    $months = ['01'=>'Janvier', '02'=>'Février', '03'=>'Mars', '04'=>'Avril', '05'=>'Mai', '06'=>'Juin', '07'=>'Juillet', '08'=>'Août', '09'=>'Sep.', '10'=>'Oct.', '11'=>'Nov.', '12'=>'Déc.'];
                    foreach($months as $num => $name): ?>
                        <option value="<?= $num ?>" <?= $filter_month == $num ? 'selected' : '' ?>><?= $name ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="year" class="form-control" onchange="this.form.submit()" style="min-width: 90px;">
                    <option value="">Toutes (Années)</option>
                    <?php for($y = 2024; $y <= date('Y') + 2; $y++): ?>
                        <option value="<?= $y ?>" <?= $filter_year == $y ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
                <button type="button" onclick="openExpenseModal()" class="btn btn-danger" style="margin-left:auto;">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Nouvelle
                </button>
            </form>
        </header>

        <?php if ($message): ?><div class="alert alert-success animate-pulse"><?= $message ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger animate-pulse"><?= $error ?></div><?php endif; ?>

        <form method="POST" id="bulk-delete-form" onsubmit="return confirm('Confirmer la suppression définitive de la sélection ?');">
            <input type="hidden" name="action" value="delete_multiple">
            <div id="bulk-actions" style="display:none; margin-bottom: 1rem; text-align:right;">
                <button type="submit" class="btn btn-danger">🗑️ Supprimer les dépenses sélectionnées</button>
            </div>
            
            <div class="card">
                <div class="data-table-wrapper table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th style="width:40px; text-align:center;">
                                    <input type="checkbox" id="selectAll" onclick="toggleAll(this)" style="cursor:pointer; transform:scale(1.2);">
                                </th>
                                <th>Titre</th><th>Date</th><th>Enregistré par</th><th>Montant</th><th style="text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($expenses)): ?>
                                <tr><td colspan="6" class="table-empty">Aucune dépense enregistrée.</td></tr>
                            <?php else: ?>
                                <?php foreach($expenses as $e): ?>
                                <tr>
                                    <td style="text-align:center;">
                                        <input type="checkbox" name="ids[]" value="<?= $e['id'] ?>" class="exp-checkbox" onchange="checkBulk()" style="cursor:pointer; transform:scale(1.2);">
                                    </td>
                                    <td>
                                        <p class="col-name"><?= htmlspecialchars($e['title']) ?></p>
                                    <p class="col-sub"><?= htmlspecialchars($e['description']) ?></p>
                                </td>
                                <td><?= date('d/m/Y',strtotime($e['expense_date'])) ?></td>
                                <td class="text-muted"><?= htmlspecialchars($e['username']) ?></td>
                                <td class="col-amount-danger">− <?= format_price($e['amount'], $e['currency']) ?></td>
                                <td>
                                    <div style="display:flex;justify-content:flex-end; gap:0.5rem;">
                                        <button type="button" class="btn-icon" style="color:var(--color-primary); background:var(--color-slate-100);" onclick='editExpense(<?= json_encode($e) ?>)' title="Modifier">
                                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        </button>
                                        <form method="POST" onsubmit="return confirm('Confirmer la suppression définitive de cette dépense ?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $e['id'] ?>">
                                            <button type="submit" class="btn-icon btn-icon-danger" title="Supprimer">
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
        </form>
    </main>
</div>

<!-- Expense Modal -->
<div id="expModal" class="modal-overlay">
    <div class="modal">
        <h3 class="modal-title" id="expModalTitle">Nouvelle Dépense</h3>
        <form method="POST" class="modal-form" id="expModalForm">
            <input type="hidden" name="action" id="expAction" value="save">
            <input type="hidden" name="id" id="expId" value="">
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
                <button type="submit" id="btn-save-exp" class="btn btn-danger btn-full" onclick="setTimeout(()=>this.disabled=true, 10);">Enregistrer</button>
            </div>
        </form>
    </div>
</div>
<script>
    const em = document.getElementById('expModal');
    em.addEventListener('click', e => { if(e.target===em) em.classList.remove('open'); });

    function toggleAll(source) {
        document.querySelectorAll('.exp-checkbox').forEach(cb => cb.checked = source.checked);
        checkBulk();
    }

    function checkBulk() {
        const total = document.querySelectorAll('.exp-checkbox').length;
        const checked = document.querySelectorAll('.exp-checkbox:checked').length;
        document.getElementById('bulk-actions').style.display = checked > 0 ? 'block' : 'none';
        if(total > 0) document.getElementById('selectAll').checked = (checked === total);
    }

    function openExpenseModal() {
        document.getElementById('expModalTitle').innerText = 'Nouvelle Dépense';
        document.getElementById('expAction').value = 'save';
        document.getElementById('expId').value = '';
        document.getElementById('expModalForm').reset();
        document.getElementById('expModal').classList.add('open');
    }

    function editExpense(exp) {
        document.getElementById('expModalTitle').innerText = 'Modifier la Dépense';
        document.getElementById('expAction').value = 'edit';
        document.getElementById('expId').value = exp.id;
        document.querySelector('[name="title"]').value = exp.title;
        document.querySelector('[name="amount"]').value = exp.amount;
        document.querySelector('[name="currency"]').value = exp.currency;
        document.querySelector('[name="description"]').value = exp.description || '';
        document.querySelector('[name="expense_date"]').value = exp.expense_date.split(' ')[0];
        document.getElementById('expModal').classList.add('open');
    }

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
