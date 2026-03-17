<?php
// Configuration de la base de données
$host = 'localhost';
$db   = 'quincatech';
$user = 'root'; // Par défaut sous XAMPP
$pass = '';     // Par défaut sous XAMPP
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERR_MODE            => PDO::ERR_MODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // Dans un environnement de production, ne pas afficher l'erreur brute
    die("Erreur de connexion à la base de données : " . $e->getMessage());
}
?>
