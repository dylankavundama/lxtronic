<?php
require_once 'config/db.php';
require_once 'includes/helpers.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (!empty($username) && !empty($password)) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user) {
                if (password_verify($password, $user['password'])) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    session_write_close();
                    header("Location: dashboard.php");
                    exit();
                } else {
                    $error = "Mot de passe incorrect.";
                }
            } else {
                $error = "Utilisateur non trouvé.";
            }
        } catch (PDOException $e) {
            $error = "Erreur de connexion : " . $e->getMessage();
        }
    } else {
        $error = "Veuillez remplir tous les champs.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <title>Connexion - LXTRONIC</title>
    <?php include 'includes/head.php'; ?>
    <style>
        .password-toggle-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }
        .password-toggle-btn {
            position: absolute;
            right: 15px;
            background: none;
            border: none;
            color: var(--color-slate-400);
            cursor: pointer;
            padding: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
        }
        .password-toggle-btn:hover {
            color: var(--color-primary);
        }
        .password-toggle-btn svg {
            width: 20px;
            height: 20px;
        }
        .form-control-lg {
            padding-right: 50px !important;
        }
    </style>
</head>
<body>
<!-- Splash Screen Loader -->
<div id="page-loader">
    <div class="loader-content">
        <img src="logo.jpg" alt="LXTRONIC" class="loader-logo">
        <h1 class="loader-title">LXTRONIC</h1>
        <p class="loader-slogan">Innovation At Your Service</p>
        <div class="loader-spinner"></div>
    </div>
</div>

<div class="login-page">
    <div class="login-box">
        <!-- Logo -->
        <div class="login-logo-wrap">
            <img src="logo.jpg" alt="LXTRONIC" class="animate-bounce" style="width:90px;height:90px;object-fit:contain;border-radius:20px;margin: 0 auto 1.5rem; display: block; box-shadow:0 8px 32px rgba(37,99,235,0.2);">
            <!-- <h1 class="login-logo-title">LXTRONIC</h1> -->
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

            <!-- Temporary Debug Info (Remove after fix) -->
            <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
                <div style="font-size: 10px; color: #94a3b8; margin-bottom: 1rem; padding: 10px; background: #f8fafc; border-radius: 8px;">
                    DEBUG: Tentative pour <b><?= htmlspecialchars($username) ?></b><br>
                    <?php if (isset($user) && $user): ?>
                        - Utilisateur trouvé : OUI<br>
                        - Hash en base : <code><?= substr($user['password'], 0, 10) ?>...</code><br>
                        - Correspondance : <?= password_verify($password, $user['password']) ? 'OUI' : 'NON' ?><br>
                    <?php else: ?>
                        - Utilisateur trouvé : NON<br>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <form action="" method="POST" class="modal-form">
                <div class="form-group">
                    <label class="form-label" for="username">Nom d'utilisateur</label>
                    <input class="form-control form-control-lg" type="text" id="username" name="username" required placeholder="ex: admin">
                </div>
                <div class="form-group">
                    <label class="form-label" for="password">Mot de passe</label>
                    <div class="password-toggle-wrapper">
                        <input class="form-control form-control-lg" type="password" id="password" name="password" required placeholder="••••••••">
                        <button type="button" class="password-toggle-btn" id="togglePassword" aria-label="Afficher le mot de passe">
                            <svg id="eyeIcon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                        </button>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary btn-lg btn-full" style="margin-top:0.5rem;">
                    Se connecter
                </button>
            </form>
        </div>

        <p class="login-footer">&copy; <?= date('Y') ?> LXTRONIC — Tous droits réservés.</p>
        <br>
        <p class="login-footer">Développé par <a href="https://nextbytechno.com">Next Byte Technology</a></p>
    </div>
</div>

<script>
    const togglePassword = document.querySelector('#togglePassword');
    const password = document.querySelector('#password');
    const eyeIcon = document.querySelector('#eyeIcon');

    togglePassword.addEventListener('click', function (e) {
        // toggle the type attribute
        const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
        password.setAttribute('type', type);
        
        // toggle the eye slash icon
        if (type === 'password') {
            eyeIcon.innerHTML = `
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
            `;
            this.setAttribute('aria-label', 'Afficher le mot de passe');
        } else {
            eyeIcon.innerHTML = `
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" />
            `;
            this.setAttribute('aria-label', 'Masquer le mot de passe');
        }
    });
</script>

<script>
    // Affichage de la page de chargement pendant 2 secondes lors du lancement
    window.addEventListener('load', function() {
        const loader = document.getElementById('page-loader');
        setTimeout(() => {
            if(loader) loader.classList.add('hidden');
        }, 2000); // 2000ms = 2 secondes
    });
</script>
</body>
</html>
