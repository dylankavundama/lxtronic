<?php
require_once 'config/db.php';
require_once 'includes/helpers.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'clear') {
    $pdo->exec("TRUNCATE TABLE system_logs");
    $message = "Les logs ont été vidés avec succès.";
}

$logs = [];
try {
    $logs = $pdo->query("SELECT * FROM system_logs ORDER BY created_at DESC LIMIT 200")->fetchAll();
} catch (PDOException $e) {
    // Si la table n'existe pas encore
    $error = "La table `system_logs` n'est pas encore créée. Veuillez exécuter check_db.php ou attendre la première erreur capturée.";
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <title>Logs Système - LXTRONIC</title>
    <?php include 'includes/head.php'; ?>
    <style>
        .log-error { border-left: 4px solid var(--color-danger); }
        .log-warning { border-left: 4px solid var(--color-warning); }
        .log-trace {
            background: #1e293b;
            color: #cbd5e1;
            padding: 1rem;
            border-radius: 8px;
            font-family: monospace;
            font-size: 0.85rem;
            margin-top: 0.5rem;
            overflow-x: auto;
        }
    </style>
</head>
<body>
<div class="app-layout">
    <?php include 'includes/sidebar.php'; ?>
    <?php include 'includes/responsive_header.php'; ?>

    <main class="main-content">
        <header class="page-header" style="flex-wrap: wrap;">
            <div>
                <h1>Logs Système</h1>
                <p>Journal de bord des erreurs et exceptions.</p>
            </div>
            <form method="POST" onsubmit="return confirm('Supprimer définitivement tous les logs ?');">
                <input type="hidden" name="action" value="clear">
                <button type="submit" class="btn btn-ghost" style="color:var(--color-danger);">
                    Vider les logs
                </button>
            </form>
        </header>

        <?php if (!empty($message)): ?>
            <div class="alert alert-success animate-pulse"><?= $message ?></div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger animate-pulse"><?= $error ?></div>
        <?php endif; ?>

        <div class="card table-responsive" style="padding: 1.5rem;">
            <?php if(empty($logs) && empty($error)): ?>
                <div style="text-align:center; padding:3rem; color:var(--color-slate-400);">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width:48px;height:48px;margin:0 auto 1rem;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <p>Aucun bogue ou erreur enregistré pour le moment. Tout va bien !</p>
                </div>
            <?php else: ?>
                <?php foreach($logs as $log): ?>
                    <div style="margin-bottom: 2rem;" class="<?= strpos($log['level'], 'EXCEPTION') !== false ? 'log-error' : 'log-warning' ?> pl-4">
                        <div style="display:flex; justify-content:space-between; margin-bottom:0.5rem;">
                            <span class="badge <?= strpos($log['level'], 'EXCEPTION') !== false ? 'badge-red' : 'badge-orange' ?>"><?= htmlspecialchars($log['level']) ?></span>
                            <span style="font-size:0.8rem; color:var(--color-slate-400);"><?= date('d/m/Y H:i:s', strtotime($log['created_at'])) ?></span>
                        </div>
                        <h4 style="font-size:1rem; color:var(--color-slate-800); margin-bottom:0.5rem; word-break: break-all;"><?= htmlspecialchars($log['message']) ?></h4>
                        <div class="log-trace">
                            <b>Fichier :</b> <?= htmlspecialchars($log['file']) ?> <br>
                            <b>Ligne :</b> <?= $log['line'] ?>
                        </div>
                    </div>
                    <hr class="divider">
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>
</div>
</body>
</html>
