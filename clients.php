<?php
require_once 'config/db.php';
require_once 'includes/helpers.php';
check_auth();

$message = ""; $error = "";
$usd_to_cdf = (float)get_setting('usd_to_cdf', 2800);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'save') {
        $id = $_POST['id'] ?? null;
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        if (!empty($name)) {
            if ($id) {
                $pdo->prepare("UPDATE clients SET name=?,phone=? WHERE id=?")->execute([$name,$phone,$id]);
                $message = "Client mis à jour.";
            } else {
                $pdo->prepare("INSERT INTO clients (name,phone) VALUES(?,?)")->execute([$name,$phone]);
                $message = "Client ajouté.";
            }
        }
    } elseif ($_POST['action'] === 'delete') {
        try {
            $pdo->prepare("DELETE FROM clients WHERE id=?")->execute([$_POST['id']]);
            $message = "Client supprimé.";
        } catch(Exception $e) {
            $error = "Impossible : ce client a un historique de ventes ou dettes.";
        }
    } elseif ($_POST['action'] === 'pay_debt') {
        $client_id = $_POST['client_id'];
        $amount = (float)$_POST['amount'];
        $currency = $_POST['currency'] ?? 'USD';
        
        if ($amount > 0) {
            $pdo->beginTransaction();
            try {
                $pdo->prepare("INSERT INTO payments (client_id, amount_paid, currency) VALUES(?,?,?)")->execute([$client_id,$amount,$currency]);
                
                $col = ($currency === 'USD') ? 'total_debt_usd' : 'total_debt_cdf';
                $pdo->prepare("UPDATE clients SET $col = $col - ? WHERE id=?")->execute([$amount,$client_id]);
                
                $pdo->commit();
                $message = "Paiement de " . format_price($amount, $currency) . " enregistré.";
            } catch(Exception $e) {
                $pdo->rollBack();
                $error = "Erreur : " . $e->getMessage();
            }
        }
    }
}

$clients = $pdo->query("SELECT * FROM clients ORDER BY (total_debt_usd + total_debt_cdf/$usd_to_cdf) DESC, name ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <title>Clients & Dettes - LXTRONIC</title>
    <?php include 'includes/head.php'; ?>
</head>
<body>
<div class="app-layout">
    <?php include 'includes/sidebar.php'; ?>
    <?php include 'includes/responsive_header.php'; ?>
    <main class="main-content">
        <header class="page-header">
            <div>
                <h1>Gestion des Clients</h1>
                <p>Suivi des dettes en **USD** et **CDF**.</p>
            </div>
            <button onclick="openClientModal()" class="btn btn-primary btn-lg">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                Nouveau Client
            </button>
        </header>

        <?php if ($message): ?><div class="alert alert-success animate-pulse"><?= $message ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

        <div class="card">
            <div class="data-table-wrapper table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Client</th>
                            <th>Contact</th>
                            <th>Dette USD</th>
                            <th>Dette CDF</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($clients)): ?>
                            <tr><td colspan="5" class="table-empty">Aucun client trouvé.</td></tr>
                        <?php else: ?>
                            <?php foreach($clients as $c): ?>
                            <tr>
                                <td><p class="col-name"><?= htmlspecialchars($c['name']) ?></p></td>
                                <td><p class="col-sub"><?= htmlspecialchars($c['phone'] ?: '-') ?></p></td>
                                <td><span class="<?= $c['total_debt_usd'] > 0 ? 'text-danger' : 'text-success' ?> font-bold"><?= format_price($c['total_debt_usd'], 'USD') ?></span></td>
                                <td><span class="<?= $c['total_debt_cdf'] > 0 ? 'text-danger' : 'text-success' ?> font-bold"><?= format_price($c['total_debt_cdf'], 'CDF') ?></span></td>
                                <td>
                                    <div class="row-actions">
                                        <?php if ($c['total_debt_usd'] > 0 || $c['total_debt_cdf'] > 0): ?>
                                        <button onclick='openPaymentModal(<?= json_encode($c) ?>)' class="btn btn-success" style="padding:6px 14px;font-size:0.8rem;border-radius:10px;">Payer</button>
                                        <?php endif; ?>
                                        <button onclick='editClient(<?= json_encode($c) ?>)' class="btn-icon btn-icon-primary"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg></button>
                                        <form method="POST" onsubmit="return confirm('Supprimer ?');" style="display:inline"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $c['id'] ?>"><button type="submit" class="btn-icon btn-icon-danger"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button></form>
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
            <input type="hidden" name="action" value="save"><input type="hidden" name="id" id="clientId">
            <div class="form-group">
                <label class="form-label">Nom complet</label>
                <input type="text" name="name" id="clientName" required class="form-control" placeholder="Ex: dylan">
            </div>
            <div class="form-group">
                <label class="form-label">Téléphone (Optionnel)</label>
                <input type="text" name="phone" id="clientPhone" class="form-control" placeholder="Ex: +243...">
            </div>
            <div class="modal-actions"><button type="button" onclick="closeClientModal()" class="btn btn-ghost btn-full">Annuler</button><button type="submit" class="btn btn-primary btn-full">Enregistrer le client</button></div>
        </form>
    </div>
</div>

<!-- Payment Modal -->
<div id="paymentModal" class="modal-overlay">
    <div class="modal">
        <h3 class="modal-title">Enregistrer un paiement</h3>
        <p id="payDebtInfo" style="color:var(--color-slate-500);margin-bottom:1.5rem;font-weight:600;font-size:0.9rem;"></p>
        <form method="POST" class="modal-form">
            <input type="hidden" name="action" value="pay_debt"><input type="hidden" name="client_id" id="payClientId">
            <div class="form-group">
                <label class="form-label">Devise du paiement</label>
                <select name="currency" id="payCurrency" class="form-control" onchange="updatePayMax()">
                    <option value="USD">USD ($)</option>
                    <option value="CDF">CDF (FC)</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Montant du versement</label>
                <input type="number" step="0.01" name="amount" id="payAmount" required class="form-control form-control-lg" style="font-weight:800;color:var(--color-primary);font-size:1.5rem;">
            </div>
            <div class="modal-actions"><button type="button" onclick="closePaymentModal()" class="btn btn-ghost btn-full">Annuler</button><button type="submit" class="btn btn-success btn-full">Confirmer</button></div>
        </form>
    </div>
</div>

<script>
    let activeClient = null;
    function openClientModal() { document.getElementById('clientModalTitle').innerText='Nouveau Client'; ['clientId','clientName','clientPhone'].forEach(id=>document.getElementById(id).value=''); document.getElementById('clientModal').classList.add('open'); }
    function closeClientModal() { document.getElementById('clientModal').classList.remove('open'); }
    function editClient(c) { document.getElementById('clientModalTitle').innerText='Modifier Client'; document.getElementById('clientId').value=c.id; document.getElementById('clientName').value=c.name; document.getElementById('clientPhone').value=c.phone; document.getElementById('clientModal').classList.add('open'); }
    function openPaymentModal(c) {
        activeClient = c;
        document.getElementById('payClientId').value = c.id;
        document.getElementById('payDebtInfo').innerText = 'Client : ' + c.name + ' \nUSD : ' + c.total_debt_usd + '$ | CDF : ' + c.total_debt_cdf + ' FC';
        document.getElementById('payCurrency').value = c.total_debt_usd > 0 ? 'USD' : 'CDF';
        updatePayMax();
        document.getElementById('paymentModal').classList.add('open');
    }
    function updatePayMax() {
        if(!activeClient) return;
        const cur = document.getElementById('payCurrency').value;
        const max = (cur === 'USD') ? activeClient.total_debt_usd : activeClient.total_debt_cdf;
        document.getElementById('payAmount').value = max;
        document.getElementById('payAmount').max = max;
    }
    function closePaymentModal() { document.getElementById('paymentModal').classList.remove('open'); }

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

    // Intercept Client Form
    document.querySelector('#clientModal form').addEventListener('submit', async function(e) {
        if (!navigator.onLine) {
            e.preventDefault();
            const formData = new FormData(this);
            const payload = {
                name: formData.get('name'),
                phone: formData.get('phone')
            };
            // Note: Update not supported offline for simplicity, only new clients
            if (formData.get('id')) {
                return alert("La modification d'un client existant requiert une connexion.");
            }
            await queueAction('client', payload);
            alert("👤 Client enregistré en local.\nIl sera ajouté dès le retour de la connexion.");
            closeClientModal();
        }
    });

    // Intercept Payment Form
    document.querySelector('#paymentModal form').addEventListener('submit', async function(e) {
        if (!navigator.onLine) {
            e.preventDefault();
            alert("Désolé, l'enregistrement des paiements hors-ligne n'est pas encore supporté pour garantir la cohérence des soldes.");
            // In a real app, we would store this and re-calculate local balance, 
            // but that's much more complex for this MVP.
        }
    });

    // Refresh Data from IndexedDB if offline
    window.addEventListener('load', async () => {
        if (!navigator.onLine) {
            const offlineClients = await getData('clients');
            if (offlineClients && offlineClients.length > 0) {
                console.log("Clients: Loaded from offline storage");
                // The table is already populated by cached PHP, 
                // but we could refresh it here if needed.
            }
        }
    });
</body>
</html>
