<?php
require_once 'config/db.php';
require_once 'includes/helpers.php';
check_auth();

$message = ""; $error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'save') {
        $id = $_POST['id'] ?? null;
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $address = trim($_POST['address'] ?? '');
        if (!empty($name)) {
            if ($id) {
                $pdo->prepare("UPDATE clients SET name=?,phone=?,email=?,address=? WHERE id=?")->execute([$name,$phone,$email,$address,$id]);
                $message = "Client mis à jour.";
            } else {
                $pdo->prepare("INSERT INTO clients (name,phone,email,address) VALUES(?,?,?,?)")->execute([$name,$phone,$email,$address]);
                $message = "Client ajouté.";
            }
        }
    } elseif ($_POST['action'] === 'delete') {
        try {
            $pdo->prepare("DELETE FROM clients WHERE id=?")->execute([$_POST['id']]);
            $message = "Client supprimé.";
        } catch(Exception $e) {
            $error = "Impossible : ce client a un historique de ventes.";
        }
    } elseif ($_POST['action'] === 'pay_debt') {
        $client_id = $_POST['client_id'];
        $amount = (float)$_POST['amount'];
        if ($amount > 0) {
            $pdo->beginTransaction();
            try {
                $pdo->prepare("INSERT INTO payments (client_id, amount_paid) VALUES(?,?)")->execute([$client_id,$amount]);
                $pdo->prepare("UPDATE clients SET total_debt = total_debt - ? WHERE id=?")->execute([$amount,$client_id]);
                $pdo->commit();
                $message = "Paiement de " . format_price($amount) . " enregistré.";
            } catch(Exception $e) {
                $pdo->rollBack();
                $error = "Erreur lors de l'enregistrement du paiement.";
            }
        }
    }
}

$clients = $pdo->query("SELECT * FROM clients ORDER BY total_debt DESC, name ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <title>Clients & Dettes - QuincaTech</title>
    <?php include 'includes/head.php'; ?>
</head>
<body>
<div class="app-layout">
    <?php include 'includes/sidebar.php'; ?>
    <main class="main-content">
        <header class="page-header">
            <div>
                <h1>Clients & Dettes</h1>
                <p>Suivez vos clients et leurs paiements en cours.</p>
            </div>
            <button onclick="openClientModal()" class="btn btn-primary btn-lg">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                Nouveau Client
            </button>
        </header>

        <?php if ($message): ?><div class="alert alert-success animate-pulse"><?= $message ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

        <div class="card">
            <div class="data-table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Client</th>
                            <th>Contact</th>
                            <th>Dette Actuelle</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($clients)): ?>
                            <tr><td colspan="4" class="table-empty">Aucun client trouvé.</td></tr>
                        <?php else: ?>
                            <?php foreach($clients as $c): ?>
                            <tr>
                                <td>
                                    <p class="col-name"><?= htmlspecialchars($c['name']) ?></p>
                                    <p class="col-sub"><?= htmlspecialchars($c['address']) ?></p>
                                </td>
                                <td>
                                    <p><?= htmlspecialchars($c['phone']) ?></p>
                                    <p class="col-sub"><?= htmlspecialchars($c['email']) ?></p>
                                </td>
                                <td>
                                    <span class="font-bold <?= $c['total_debt'] > 0 ? 'text-danger' : 'text-success' ?>">
                                        <?= format_price($c['total_debt']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="row-actions">
                                        <?php if ($c['total_debt'] > 0): ?>
                                        <button onclick='openPaymentModal(<?= json_encode($c) ?>)' class="btn btn-success" style="padding:6px 14px;font-size:0.8rem;border-radius:10px;">
                                            Payer
                                        </button>
                                        <?php endif; ?>
                                        <button onclick='editClient(<?= json_encode($c) ?>)' class="btn-icon btn-icon-primary">
                                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        </button>
                                        <form method="POST" onsubmit="return confirm('Supprimer ce client ?');" style="display:inline">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $c['id'] ?>">
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

<!-- Client Modal -->
<div id="clientModal" class="modal-overlay">
    <div class="modal">
        <h3 id="clientModalTitle" class="modal-title">Nouveau Client</h3>
        <form method="POST" class="modal-form">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" id="clientId">
            <div class="form-group">
                <label class="form-label">Nom complet</label>
                <input type="text" name="name" id="clientName" required class="form-control">
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Téléphone</label>
                    <input type="text" name="phone" id="clientPhone" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" id="clientEmail" class="form-control">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Adresse</label>
                <textarea name="address" id="clientAddress" rows="2" class="form-control"></textarea>
            </div>
            <div class="modal-actions">
                <button type="button" onclick="closeClientModal()" class="btn btn-ghost btn-full">Annuler</button>
                <button type="submit" class="btn btn-primary btn-full">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<!-- Payment Modal -->
<div id="paymentModal" class="modal-overlay">
    <div class="modal">
        <h3 class="modal-title">Enregistrer un paiement</h3>
        <p id="payDebtInfo" style="color:var(--color-slate-500);margin-bottom:1.5rem;font-weight:600;font-size:0.9rem;"></p>
        <form method="POST" class="modal-form">
            <input type="hidden" name="action" value="pay_debt">
            <input type="hidden" name="client_id" id="payClientId">
            <div class="form-group">
                <label class="form-label">Montant du versement (FCFA)</label>
                <input type="number" step="0.01" name="amount" id="payAmount" required class="form-control form-control-lg" style="font-weight:800;color:var(--color-primary);font-size:1.5rem;">
            </div>
            <div class="modal-actions">
                <button type="button" onclick="closePaymentModal()" class="btn btn-ghost btn-full">Annuler</button>
                <button type="submit" class="btn btn-success btn-full">Confirmer le paiement</button>
            </div>
        </form>
    </div>
</div>

<script>
    const cm = document.getElementById('clientModal');
    const pm = document.getElementById('paymentModal');

    function openClientModal() {
        document.getElementById('clientModalTitle').innerText = 'Nouveau Client';
        ['clientId','clientName','clientPhone','clientEmail','clientAddress'].forEach(id => document.getElementById(id).value='');
        cm.classList.add('open');
    }
    function closeClientModal() { cm.classList.remove('open'); }
    function editClient(c) {
        document.getElementById('clientModalTitle').innerText = 'Modifier Client';
        document.getElementById('clientId').value = c.id;
        document.getElementById('clientName').value = c.name;
        document.getElementById('clientPhone').value = c.phone;
        document.getElementById('clientEmail').value = c.email;
        document.getElementById('clientAddress').value = c.address;
        cm.classList.add('open');
    }

    function openPaymentModal(c) {
        document.getElementById('payClientId').value = c.id;
        document.getElementById('payDebtInfo').innerText = 'Client : ' + c.name + ' — Dette : ' + parseFloat(c.total_debt).toLocaleString('fr-FR') + ' FCFA';
        document.getElementById('payAmount').value = c.total_debt;
        document.getElementById('payAmount').max = c.total_debt;
        pm.classList.add('open');
    }
    function closePaymentModal() { pm.classList.remove('open'); }

    cm.addEventListener('click', e => { if(e.target===cm) closeClientModal(); });
    pm.addEventListener('click', e => { if(e.target===pm) closePaymentModal(); });
</script>
</body>
</html>
