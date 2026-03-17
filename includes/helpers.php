<?php
session_start();

/**
 * Redirige si l'utilisateur n'est pas connecté
 */
function check_auth() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit();
    }
}

/**
 * Vérifie si l'utilisateur est un administrateur
 */
function is_admin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

/**
 * Empêche l'accès si l'utilisateur n'est pas admin
 */
function require_admin() {
    check_auth();
    if (!is_admin()) {
        header("Location: dashboard.php?error=unauthorized");
        exit();
    }
}

/**
 * Formatage des prix
 */
function format_price($amount) {
    return number_format($amount, 2, ',', ' ') . ' FCFA';
}
?>
