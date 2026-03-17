<?php
require_once 'config/db.php';
require_once 'includes/helpers.php';
require_admin();

$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usd_to_cdf = $_POST['usd_to_cdf'] ?? '2800';
    $app_name = $_POST['app_name'] ?? 'LxTronic';
    $app_slogan = $_POST['app_slogan'] ?? 'Innovation At Your Service';

    $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    $stmt->execute(['usd_to_cdf', $usd_to_cdf]);
    $stmt->execute(['app_name', $app_name]);
    $stmt->execute(['app_slogan', $app_slogan]);

    $message = "Paramètres mis à jour avec succès.";
}

$usd_to_cdf = get_setting('usd_to_cdf', '2800');
$app_name = get_setting('app_name', 'LxTronic');
$app_slogan = get_setting('app_slogan', 'Innovation At Your Service');

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <title>Paramètres - <?= $app_name ?></title>
    <?php include 'includes/head.php'; ?>
</head>
<body>
<div class="app-layout">
    <?php include 'includes/sidebar.php'; ?>
    <?php include 'includes/responsive_header.php'; ?>

    <main class="main-content">
        <header class="page-header">
            <div>
                <h1>Paramètres Système</h1>
                <p>Configurez le taux de change et les informations de l'entreprise.</p>
            </div>
        </header>

        <?php if ($message): ?>
            <div class="alert alert-success animate-pulse"><?= $message ?></div>
        <?php endif; ?>

        <div class="card card-padded" style="max-width: 600px;">
            <form method="POST" class="modal-form">
                <div class="form-group">
                    <label class="form-label">Taux de change (1 USD en CDF)</label>
                    <input type="number" name="usd_to_cdf" value="<?= $usd_to_cdf ?>" required class="form-control form-control-lg">
                    <p class="text-muted" style="font-size: 12px; margin-top: 4px;">Actuellement : 1 USD = <?= number_format($usd_to_cdf, 0, '.', ' ') ?> FC</p>
                </div>

                <hr class="divider">

                <div class="form-group">
                    <label class="form-label">Nom de l'entreprise</label>
                    <input type="text" name="app_name" value="<?= $app_name ?>" required class="form-control">
                </div>

                <div class="form-group">
                    <label class="form-label">Slogan / Devise</label>
                    <input type="text" name="app_slogan" value="<?= $app_slogan ?>" required class="form-control">
                </div>

                <div class="modal-actions" style="margin-top: 1.5rem;">
                    <button type="submit" class="btn btn-primary btn-lg btn-full">Enregistrer les modifications</button>
                </div>
            </form>
        </div>
    </main>
</div>
</body>
</html>
