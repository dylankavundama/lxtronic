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
 * Récupère un paramètre de la base de données
 */
function get_setting($key, $default = null) {
    global $pdo;
    static $settings = [];
    if (empty($settings)) {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
        while ($row = $stmt->fetch()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    }
    return $settings[$key] ?? $default;
}

/**
 * Formatage des prix selon la devise
 */
function format_price($amount, $currency = 'USD') {
    if ($currency === 'CDF') {
        return number_format($amount, 0, '.', ' ') . ' FC';
    }
    return '$' . number_format($amount, 2, '.', ' ');
}
?>
