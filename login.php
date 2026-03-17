<?php
require_once 'config/db.php';
require_once 'includes/helpers.php';

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (!empty($username) && !empty($password)) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            header("Location: dashboard.php");
            exit();
        } else {
            $error = "Identifiants invalides.";
        }
    } else {
        $error = "Veuillez remplir tous les champs.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <title>Connexion - LxTronic</title>
    <?php include 'includes/head.php'; ?>
</head>
<body>
<div class="login-page">
    <div class="login-box">
        <!-- Logo -->
        <div class="login-logo-wrap">
            <img src="logo.jpg" alt="LxTronic" class="animate-bounce" style="width:90px;height:90px;object-fit:contain;border-radius:20px;margin-bottom:1rem;box-shadow:0 8px 32px rgba(37,99,235,0.2);">
            <h1 class="login-logo-title">LxTronic</h1>
            <p class="login-logo-sub">Innovation At Your Service</p>
        </div>

        <!-- Card -->
        <div class="login-card">
            <h2>Connexion à votre espace</h2>

            <?php if ($error): ?>
                <div class="alert alert-danger animate-pulse" style="margin-bottom:1.5rem;">
                    <svg fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                    <?= $error ?>
                </div>
            <?php endif; ?>

            <form action="" method="POST" class="modal-form">
                <div class="form-group">
                    <label class="form-label" for="username">Nom d'utilisateur</label>
                    <input class="form-control form-control-lg" type="text" id="username" name="username" required placeholder="ex: admin">
                </div>
                <div class="form-group">
                    <label class="form-label" for="password">Mot de passe</label>
                    <input class="form-control form-control-lg" type="password" id="password" name="password" required placeholder="••••••••">
                </div>
                <button type="submit" class="btn btn-primary btn-lg btn-full" style="margin-top:0.5rem;">
                    Se connecter
                </button>
            </form>
        </div>

        <p class="login-footer">&copy; <?= date('Y') ?> LxTronic — Tous droits réservés.</p>
    </div>
</div>
</body>
</html>
