<?php
require_once '../config/db.php';
require_once '../includes/helpers.php';
check_auth();

// --- PROTECTION PAR CODE PIN ---
if (!isset($_SESSION['bank_unlocked']) || $_SESSION['bank_unlocked'] !== true) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bank_pin'])) {
        if ($_POST['bank_pin'] === '9080') {
            $_SESSION['bank_unlocked'] = true;
            header("Location: banque.php");
            exit();
        } else {
            $pin_error = "Code PIN incorrect.";
        }
    }
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accès Sécurisé - LXTRONIC</title>
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;700;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Outfit', sans-serif; display: flex; align-items: center; justify-content: center; height: 100vh; background-color: var(--color-slate-50); margin: 0; }
        .lock-container { background: white; padding: 2.5rem; border-radius: 24px; box-shadow: var(--shadow-xl); text-align: center; max-width: 400px; width: 90%; }
        .lock-icon { font-size: 3rem; margin-bottom: 1rem; }
    </style>
</head>
<body>
    <div class="lock-container">
        <div class="lock-icon">🔒</div>
        <h2 style="margin-bottom: 0.5rem; color: var(--color-slate-800);">Coffre-fort</h2>
        <p style="color: var(--color-slate-500); margin-bottom: 1.5rem; font-size: 0.9rem;">Veuillez entrer le code PIN pour y accéder.</p>
        <?php if (isset($pin_error)): ?><div class="alert alert-danger" style="margin-bottom: 1.5rem;"><?= $pin_error ?></div><?php endif; ?>
        <form method="POST">
            <input type="password" name="bank_pin" class="form-control" placeholder="****" required autofocus style="text-align: center; letter-spacing: 0.5rem; font-size: 1.5rem; margin-bottom: 1rem; font-weight: bold;">
            <button type="submit" class="btn btn-primary" style="width: 100%;">Déverrouiller</button>
        </form>
    </div>
</body>
</html>
<?php
    exit();
}

if (isset($_GET['lock_bank'])) {
    unset($_SESSION['bank_unlocked']);
    header("Location: banque.php");
    exit();
}

// --- AUTO-MIGRATION (Création des tables si inexistantes) ---
$pdo->exec("
    CREATE TABLE IF NOT EXISTS bank_accounts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        account_name VARCHAR(100) NOT NULL,
        owner_name VARCHAR(100) NOT NULL,
        balance_usd DECIMAL(15, 2) DEFAULT 0.00,
        balance_cdf DECIMAL(15, 2) DEFAULT 0.00,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );
    CREATE TABLE IF NOT EXISTS bank_transactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        account_id INT,
        type ENUM('deposit', 'withdrawal') NOT NULL,
        amount DECIMAL(15, 2) NOT NULL,
        currency ENUM('USD', 'CDF') NOT NULL,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (account_id) REFERENCES bank_accounts(id) ON DELETE CASCADE
    );
");

$message = ""; $error = "";

// --- LOGIQUE ACTIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_account') {
        $name = trim($_POST['account_name']);
        $owner = trim($_POST['owner_name']);
        if (!empty($name) && !empty($owner)) {
            $pdo->prepare("INSERT INTO bank_accounts (account_name, owner_name) VALUES(?,?)")->execute([$name, $owner]);
            $message = "Compte créé avec succès.";
        }
    } elseif ($_POST['action'] === 'transaction') {
        $acc_id = $_POST['account_id'];
        $type = $_POST['type']; // deposit / withdrawal
        $amount = (float)$_POST['amount'];
        $currency = $_POST['currency']; // USD / CDF
        $desc = trim($_POST['description'] ?? '');

        if ($amount > 0) {
            $pdo->beginTransaction();
            try {
                // Update account balance
                $col = ($currency === 'USD') ? 'balance_usd' : 'balance_cdf';
                $sign = ($type === 'deposit') ? '+' : '-';
                
                $pdo->prepare("UPDATE bank_accounts SET $col = $col $sign ? WHERE id=?")->execute([$amount, $acc_id]);
                
                // Record transaction
                $pdo->prepare("INSERT INTO bank_transactions (account_id, type, amount, currency, description) VALUES(?,?,?,?,?)")
                    ->execute([$acc_id, $type, $amount, $currency, $desc]);
                
                $pdo->commit();
                $message = "Opération effectuée : " . ($type==='deposit'?'Dépôt':'Retrait') . " de " . format_price($amount, $currency);
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Erreur : " . $e->getMessage();
            }
        }
    } elseif ($_POST['action'] === 'delete_trans') {
        $trans_id = $_POST['id'];
        $trans = $pdo->prepare("SELECT * FROM bank_transactions WHERE id=?");
        $trans->execute([$trans_id]);
        $t = $trans->fetch();
        if ($t) {
            $pdo->beginTransaction();
            try {
                $col = ($t['currency'] === 'USD') ? 'balance_usd' : 'balance_cdf';
                $sign = ($t['type'] === 'deposit') ? '-' : '+';
                $pdo->prepare("UPDATE bank_accounts SET $col = $col $sign ? WHERE id=?")->execute([$t['amount'], $t['account_id']]);
                $pdo->prepare("DELETE FROM bank_transactions WHERE id=?")->execute([$trans_id]);
                $pdo->commit();
                $message = "Transaction supprimée et solde réajusté.";
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Erreur : " . $e->getMessage();
            }
        }
    } elseif ($_POST['action'] === 'delete_account') {
        $acc_id = $_POST['id'];
        $pdo->prepare("DELETE FROM bank_accounts WHERE id=?")->execute([$acc_id]);
        $message = "Compte supprimé avec succès.";
    } elseif ($_POST['action'] === 'edit_account') {
        $acc_id = $_POST['id'];
        $name = trim($_POST['account_name']);
        $owner = trim($_POST['owner_name']);
        if (!empty($name) && !empty($owner)) {
            $pdo->prepare("UPDATE bank_accounts SET account_name=?, owner_name=? WHERE id=?")->execute([$name, $owner, $acc_id]);
            $message = "Compte modifié avec succès.";
        }
    }
}

// --- DONNÉES ---
$usd_to_cdf_rate = (float)get_setting('usd_to_cdf', 2800);
$accounts = $pdo->query("SELECT * FROM bank_accounts ORDER BY (balance_usd * $usd_to_cdf_rate + balance_cdf) DESC, account_name ASC")->fetchAll();
$transactions = $pdo->query("SELECT t.*, a.account_name FROM bank_transactions t JOIN bank_accounts a ON t.account_id = a.id ORDER BY t.created_at DESC LIMIT 50")->fetchAll();

// Statistiques
$stats = $pdo->query("SELECT SUM(balance_usd) as total_usd, SUM(balance_cdf) as total_cdf, COUNT(*) as count FROM bank_accounts")->fetch();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ma Banque - LXTRONIC</title>
    <!-- On adapte les chemins car on est dans un sous-dossier -->
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/layout.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700;900&display=swap" rel="stylesheet">

    
    <style>
        .bank-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
        .acc-card { background: white; border-radius: 24px; padding: 1.5rem; border: 1px solid #f1f5f9; transition: 0.3s; }
        .acc-card:hover { transform: translateY(-5px); box-shadow: var(--shadow-xl); }
        .acc-name { font-weight: 800; font-size: 1.1rem; color: var(--color-slate-900); }
        .acc-owner { font-size: 0.8rem; color: var(--color-slate-400); text-transform: uppercase; letter-spacing: 0.05em; font-weight: 700; margin-bottom: 1rem; }
        .acc-balance { display: flex; justify-content: space-between; align-items: center; margin-top: 0.5rem; }
        .val-usd { color: var(--color-primary); font-weight: 900; font-size: 1.25rem; }
        .val-cdf { color: var(--color-success); font-weight: 800; font-size: 1.1rem; }
    </style>
</head>
<body>
<div class="app-layout">
    <!-- Sidebar avec ajustement de chemin interne (le sidebar.php utilise des chemins relatifs au script appelant) -->
    <!-- NOTE: Pour que le sidebar marche, on doit soit modifier le sidebar pour utiliser des chemins absolus, 
         soit tricher un peu. Ici on va inclure un sidebar adapté ou simplement faire attention. -->
    <?php 
        // On change le dossier de travail temporairement pour l'inclusion ou on gère les liens
        // Le plus simple est de copier le contenu nécessaire du sidebar ou d'ajuster les href.
        // Comme demandé par le user, on reste indépendant.
    ?>
    
    <aside class="sidebar">
        <div class="sidebar-logo">
            <div style="display: flex; align-items: center; gap: 12px; flex: 1;">
                <img src="../logo.jpg" alt="Logo" style="width:44px;height:44px;object-fit:contain;border-radius:10px;">
                <span>BANQUE</span>
            </div>
        </div>
        <nav class="sidebar-nav">
            <div>
                <p class="nav-group-title">Navigation</p>
                <div class="nav-links">
                    <a href="../dashboard.php" class="nav-link">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                        <span>Retour au POS</span>
                    </a>
                    <a href="banque.php" class="nav-link active">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                        <span>Ma Banque</span>
                    </a>
                </div>
            </div>
        </nav>
    </aside>

    <main class="main-content">
        <header class="page-header">
            <div>
                <h1>Système Bancaire</h1>
                <p>Gestion indépendante des comptes et fonds.</p>
            </div>
            <div style="display:flex; gap:1rem;">
                <a href="?lock_bank=1" class="btn btn-ghost" style="background: white; border: 1px solid var(--color-slate-200);">🔒 Verrouiller</a>
                <button onclick="document.getElementById('modalAcc').classList.add('open')" class="btn btn-primary btn-lg">
                    + Nouveau Compte
                </button>
            </div>
        </header>

        <?php if ($message): ?><div class="alert alert-success"><?= $message ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

        <!-- Statistiques -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
            <div class="stat-card">
                <div class="stat-icon stat-icon-blue"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div>
                <div><p class="stat-label">Total USD</p><p class="stat-value"><?= format_price($stats['total_usd'] ?? 0, 'USD') ?></p></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon stat-icon-green"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg></div>
                <div><p class="stat-label">Total CDF</p><p class="stat-value"><?= format_price($stats['total_cdf'] ?? 0, 'CDF') ?></p></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon stat-icon-dark"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg></div>
                <div><p class="stat-label">Comptes</p><p class="stat-value"><?= $stats['count'] ?></p></div>
            </div>
        </div>

        <!-- Comptes -->
        <h2 class="section-title">Mes Comptes</h2>
        <div class="bank-grid">
            <?php foreach($accounts as $acc): ?>
            <div class="acc-card">
                <div class="acc-name"><?= htmlspecialchars($acc['account_name']) ?></div>
                <div class="acc-owner">Propriétaire: <?= htmlspecialchars($acc['owner_name']) ?></div>
                <div class="divider"></div>
                <div class="acc-balance">
                    <span style="font-size:0.8rem; font-weight:700; color:#94a3b8;">Solde USD</span>
                    <span class="val-usd"><?= format_price($acc['balance_usd'], 'USD') ?></span>
                </div>
                <div class="acc-balance">
                    <span style="font-size:0.8rem; font-weight:700; color:#94a3b8;">Solde CDF</span>
                    <span class="val-cdf"><?= format_price($acc['balance_cdf'], 'CDF') ?></span>
                </div>
                <div style="margin-top:1rem; display:flex; gap:0.5rem;">
                    <button onclick="openTransModal(<?= $acc['id'] ?>, '<?= htmlspecialchars($acc['account_name']) ?>')" class="btn btn-primary btn-sm" style="flex:1;">Opération</button>
                    <button onclick="openEditModal(<?= $acc['id'] ?>, '<?= htmlspecialchars($acc['account_name']) ?>', '<?= htmlspecialchars($acc['owner_name']) ?>')" class="btn btn-ghost btn-sm" title="Modifier" style="padding: 0 0.5rem; background: var(--color-slate-100);">
                        <svg width="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    </button>
                    <form method="POST" style="margin:0;" onsubmit="return confirm('Supprimer ce compte et TOUTES ses transactions ?');">
                        <input type="hidden" name="action" value="delete_account">
                        <input type="hidden" name="id" value="<?= $acc['id'] ?>">
                        <button type="submit" class="btn btn-ghost btn-sm" title="Supprimer" style="padding: 0 0.5rem; color: var(--color-danger); background: var(--color-slate-100);">
                            <svg width="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        </button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Historique -->
        <div class="card">
            <div style="padding: 1.5rem 1.75rem; border-bottom: 1px solid var(--color-slate-100);">
                <h3 class="section-title">Historique des Opérations Bancaires</h3>
            </div>
            <div class="data-table-wrapper table-responsive">
                <table class="data-table">
                    <thead>
                        <tr><th>Date</th><th>Compte</th><th>Type</th><th>Montant</th><th>Description</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach($transactions as $t): ?>
                        <tr>
                            <td class="text-muted"><?= date('d/m/Y H:i', strtotime($t['created_at'])) ?></td>
                            <td class="col-name"><?= htmlspecialchars($t['account_name']) ?></td>
                            <td><span class="badge <?= $t['type']==='deposit'?'badge-green':'badge-red' ?>"><?= $t['type']==='deposit'?'Dépôt':'Retrait' ?></span></td>
                            <td class="col-amount <?= $t['type']==='withdrawal'?'text-danger':'' ?>">
                                <?= $t['type']==='deposit'?'+':'-' ?> <?= format_price($t['amount'], $t['currency']) ?>
                            </td>
                            <td class="text-muted"><?= htmlspecialchars($t['description']) ?></td>
                            <td>
                                <form method="POST" onsubmit="return confirm('Vraiment supprimer cette transaction ? Le solde sera réajusté.');" style="margin:0;">
                                    <input type="hidden" name="action" value="delete_trans">
                                    <input type="hidden" name="id" value="<?= $t['id'] ?>">
                                    <button type="submit" class="btn-icon btn-icon-danger" title="Supprimer" style="background:transparent; border:none; color:var(--color-danger); cursor:pointer;">
                                        <svg width="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<!-- Modal Nouveau Compte -->
<div id="modalAcc" class="modal-overlay">
    <div class="modal">
        <h3 class="modal-title">Nouveau Compte Bancaire</h3>
        <form method="POST" class="modal-form">
            <input type="hidden" name="action" value="add_account">
            <div class="form-group">
                <label class="form-label">Nom de la Banque / Compte</label>
                <input type="text" name="account_name" required class="form-control" placeholder="Ex: Rawbank">
            </div>
            <div class="form-group">
                <label class="form-label">Nom du Propriétaire</label>
                <input type="text" name="owner_name" required class="form-control" placeholder="Ex: Dylan K.">
            </div>
            <div class="modal-actions">
                <button type="button" onclick="this.closest('.modal-overlay').classList.remove('open')" class="btn btn-ghost">Annuler</button>
                <button type="submit" class="btn btn-primary">Créer le compte</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Opération -->
<div id="modalTrans" class="modal-overlay">
    <div class="modal">
        <h3 class="modal-title" id="transTitle">Opération Bancaire</h3>
        <form method="POST" class="modal-form">
            <input type="hidden" name="action" value="transaction">
            <input type="hidden" name="account_id" id="transAccId">
            <div class="form-group">
                <label class="form-label">Type d'opération</label>
                <select name="type" class="form-control">
                    <option value="deposit">Dépôt (Entrée)</option>
                    <option value="withdrawal">Retrait (Sortie)</option>
                </select>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div class="form-group">
                    <label class="form-label">Montant</label>
                    <input type="number" step="0.01" name="amount" required class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">Devise</label>
                    <select name="currency" class="form-control">
                        <option value="USD">USD ($)</option>
                        <option value="CDF">CDF (FC)</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Description / Motif</label>
                <textarea name="description" class="form-control" rows="2"></textarea>
            </div>
            <div class="modal-actions">
                <button type="button" onclick="this.closest('.modal-overlay').classList.remove('open')" class="btn btn-ghost">Annuler</button>
                <button type="submit" class="btn btn-primary">Confirmer</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Edition Compte -->
<div id="modalEditAcc" class="modal-overlay">
    <div class="modal">
        <h3 class="modal-title">Modifier un Compte</h3>
        <form method="POST" class="modal-form">
            <input type="hidden" name="action" value="edit_account">
            <input type="hidden" name="id" id="editAccId">
            <div class="form-group">
                <label class="form-label">Nom de la Banque / Compte</label>
                <input type="text" name="account_name" id="editAccName" required class="form-control">
            </div>
            <div class="form-group">
                <label class="form-label">Nom du Propriétaire</label>
                <input type="text" name="owner_name" id="editAccOwner" required class="form-control">
            </div>
            <div class="modal-actions">
                <button type="button" onclick="this.closest('.modal-overlay').classList.remove('open')" class="btn btn-ghost">Annuler</button>
                <button type="submit" class="btn btn-primary">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openTransModal(id, name) {
        document.getElementById('transAccId').value = id;
        document.getElementById('transTitle').innerText = 'Opération : ' + name;
        document.getElementById('modalTrans').classList.add('open');
    }
    function openEditModal(id, name, owner) {
        document.getElementById('editAccId').value = id;
        document.getElementById('editAccName').value = name;
        document.getElementById('editAccOwner').value = owner;
        document.getElementById('modalEditAcc').classList.add('open');
    }
</script>
</body>
</html>
