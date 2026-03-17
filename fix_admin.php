<?php
require_once 'config/db.php';
require_once 'includes/helpers.php';

echo "<h2>LxTronic - Réinitialisation Admin</h2>";

try {
    // Créer la table users si elle n'existe pas
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        role ENUM('admin', 'vendeur') NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo "<p style='color:blue;'>ℹ️ Vérification de la table 'users' terminée.</p>";

    // Vérifier si l'utilisateur admin existe
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = 'admin'");
    $stmt->execute();
    $user = $stmt->fetch();

    $new_password = password_hash('password', PASSWORD_DEFAULT);

    if ($user) {
        // Mise à jour de l'admin existant
        $stmt = $pdo->prepare("UPDATE users SET password = ?, role = 'admin' WHERE username = 'admin'");
        $stmt->execute([$new_password]);
        echo "<p style='color:green;'>✅ Le mot de passe de l'utilisateur 'admin' a été réinitialisé à 'password'.</p>";
    } else {
        // Création d'un nouvel admin
        $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES ('admin', ?, 'admin')");
        $stmt->execute([$new_password]);
        echo "<p style='color:green;'>✅ L'utilisateur 'admin' a été créé avec le mot de passe 'password'.</p>";
    }
    
    echo "<p><a href='login.php'>Retour à la page de connexion</a></p>";
    echo "<p style='color:red;'>⚠️ Veuillez supprimer ce fichier (fix_admin.php) après utilisation pour des raisons de sécurité.</p>";

} catch (Exception $e) {
    echo "<p style='color:red;'>❌ Erreur : " . $e->getMessage() . "</p>";
}
?>
