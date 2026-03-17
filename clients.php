<?php
require_once 'config/db.php';
require_once 'includes/helpers.php';
check_auth();

$message = "";
$error = "";

// Actions Client
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'save') {
        $id = $_POST['id'] ?? null;
        $name = $_POST['name'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $email = $_POST['email'] ?? '';
        $address = $_POST['address'] ?? '';

        if (!empty($name)) {
            if ($id) {
                $stmt = $pdo->prepare("UPDATE clients SET name = ?, phone = ?, email = ?, address = ? WHERE id = ?");
                $stmt->execute([$name, $phone, $email, $address, $id]);
                $message = "Client mis à jour.";
            } else {
                $stmt = $pdo->prepare("INSERT INTO clients (name, phone, email, address) VALUES (?, ?, ?, ?)");
                $stmt->execute([$name, $phone, $email, $address]);
                $message = "Client ajouté.";
            }
        }
    } elseif ($_POST['action'] === 'delete') {
        $id = $_POST['id'];
        try {
            $stmt = $pdo->prepare("DELETE FROM clients WHERE id = ?");
            $stmt->execute([$id]);
            $message = "Client supprimé.";
        } catch (Exception $e) {
            $error = "Impossible de supprimer ce client car il possède un historique de ventes.";
        }
    } elseif ($_POST['action'] === 'pay_debt') {
        $client_id = $_POST['client_id'];
        $amount = $_POST['amount'];
        
        if ($amount > 0) {
            $pdo->beginTransaction();
            try {
                // Enregistrer le paiement
                $stmt = $pdo->prepare("INSERT INTO payments (client_id, amount_paid) VALUES (?, ?)");
                $stmt->execute([$client_id, $amount]);

                // Mettre à jour la dette du client
                $stmt = $pdo->prepare("UPDATE clients SET total_debt = total_debt - ? WHERE id = ?");
                $stmt->execute([$amount, $client_id]);

                $pdo->commit();
                $message = "Paiement de " . format_price($amount) . " enregistré.";
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Erreur lors de l'enregistrement du paiement.";
            }
        }
    }
}

$clients = $pdo->query("SELECT * FROM clients ORDER BY name ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clients - QuincaTech</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Outfit', sans-serif; }</style>
</head>
<body class="bg-[#F8FAFC]">
    <?php include 'includes/sidebar.php'; ?>

    <main class="ml-64 p-8 min-h-screen">
        <header class="flex justify-between items-center mb-10">
            <div>
                <h1 class="text-3xl font-bold text-slate-800">Gestion des Clients & Dettes</h1>
                <p class="text-slate-500 mt-1">Suivez vos clients et leurs paiements.</p>
            </div>
            <button onclick="openClientModal()" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-3 rounded-2xl shadow-lg shadow-blue-200 transition-all flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" stroke-width="2"></path></svg>
                Nouveau Client
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
                        <th class="px-6 py-4">Client</th>
                        <th class="px-6 py-4">Contact</th>
                        <th class="px-6 py-4">Dette Actuelle</th>
                        <th class="px-6 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php if (empty($clients)): ?>
                        <tr><td colspan="4" class="px-6 py-10 text-center text-slate-400">Aucun client trouvé.</td></tr>
                    <?php else: ?>
                        <?php foreach ($clients as $c): ?>
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="px-6 py-4">
                                    <p class="font-semibold text-slate-700"><?= $c['name'] ?></p>
                                    <p class="text-xs text-slate-400"><?= $c['address'] ?></p>
                                </td>
                                <td class="px-6 py-4 text-sm text-slate-600">
                                    <p><?= $c['phone'] ?></p>
                                    <p class="text-xs"><?= $c['email'] ?></p>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="font-bold <?= $c['total_debt'] > 0 ? 'text-red-600' : 'text-green-600' ?>">
                                        <?= format_price($c['total_debt']) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex justify-end gap-2">
                                        <?php if ($c['total_debt'] > 0): ?>
                                        <button onclick='openPaymentModal(<?= json_encode($c) ?>)' class="px-3 py-1 bg-green-100 text-green-700 rounded-lg text-xs font-bold hover:bg-green-200 transition-all">
                                            Payer
                                        </button>
                                        <?php endif; ?>
                                        <button onclick='editClient(<?= json_encode($c) ?>)' class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" stroke-width="2"></path></svg>
                                        </button>
                                        <form method="POST" onsubmit="return confirm('Supprimer ce client ?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $c['id'] ?>">
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

    <!-- Client Modal -->
    <div id="clientModal" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-[100] hidden items-center justify-center p-4">
        <div class="bg-white rounded-3xl w-full max-w-lg shadow-2xl p-8 transform transition-all">
            <h3 id="modalTitle" class="text-xl font-bold text-slate-800 mb-6">Ajouter un Client</h3>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" id="clientId">
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">Nom complet</label>
                    <input type="text" name="name" id="clientName" required class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-blue-100">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Téléphone</label>
                        <input type="text" name="phone" id="clientPhone" class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-blue-100">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Email</label>
                        <input type="email" name="email" id="clientEmail" class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-blue-100">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">Adresse</label>
                    <textarea name="address" id="clientAddress" rows="2" class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-blue-100"></textarea>
                </div>
                <div class="flex gap-4 pt-4">
                    <button type="button" onclick="closeClientModal()" class="flex-1 px-6 py-3 rounded-xl font-semibold bg-slate-100 text-slate-600 hover:bg-slate-200 transition-all">Annuler</button>
                    <button type="submit" class="flex-1 px-6 py-3 rounded-xl font-semibold bg-blue-600 text-white hover:bg-blue-700 shadow-lg shadow-blue-200 transition-all">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Payment Modal -->
    <div id="paymentModal" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-[100] hidden items-center justify-center p-4">
        <div class="bg-white rounded-3xl w-full max-w-md shadow-2xl p-8 transform transition-all">
            <h3 class="text-xl font-bold text-slate-800 mb-2">Enregistrer un paiement</h3>
            <p id="payClientName" class="text-slate-500 mb-6 font-medium"></p>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="pay_debt">
                <input type="hidden" name="client_id" id="payClientId">
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-2">Montant du versement</label>
                    <div class="relative">
                        <input type="number" step="0.01" name="amount" id="payAmount" required class="w-full pl-4 pr-12 py-4 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-blue-100 text-2xl font-bold text-blue-600">
                        <span class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 font-bold">FCFA</span>
                    </div>
                </div>
                <div class="flex gap-4 pt-4">
                    <button type="button" onclick="closePaymentModal()" class="flex-1 px-6 py-3 rounded-xl font-semibold bg-slate-100 text-slate-600 hover:bg-slate-200 transition-all">Annuler</button>
                    <button type="submit" class="flex-1 px-6 py-3 rounded-xl font-semibold bg-green-600 text-white hover:bg-green-700 shadow-lg shadow-green-200 transition-all">Confirmer</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openClientModal() {
            document.getElementById('modalTitle').innerText = "Nouveau Client";
            document.getElementById('clientId').value = "";
            document.getElementById('clientName').value = "";
            document.getElementById('clientPhone').value = "";
            document.getElementById('clientEmail').value = "";
            document.getElementById('clientAddress').value = "";
            document.getElementById('clientModal').classList.replace('hidden', 'flex');
        }
        function closeClientModal() {
            document.getElementById('clientModal').classList.replace('flex', 'hidden');
        }
        function editClient(c) {
            document.getElementById('modalTitle').innerText = "Modifier Client";
            document.getElementById('clientId').value = c.id;
            document.getElementById('clientName').value = c.name;
            document.getElementById('clientPhone').value = c.phone;
            document.getElementById('clientEmail').value = c.email;
            document.getElementById('clientAddress').value = c.address;
            document.getElementById('clientModal').classList.replace('hidden', 'flex');
        }
        function openPaymentModal(c) {
            document.getElementById('payClientId').value = c.id;
            document.getElementById('payClientName').innerText = "Dette actuelle : " + c.total_debt + " FCFA";
            document.getElementById('payAmount').max = c.total_debt;
            document.getElementById('payAmount').value = c.total_debt;
            document.getElementById('paymentModal').classList.replace('hidden', 'flex');
        }
        function closePaymentModal() {
            document.getElementById('paymentModal').classList.replace('flex', 'hidden');
        }
    </script>
</body>
</html>
