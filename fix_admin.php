<?php
require_once 'config/db.php';
require_once 'includes/helpers.php';

echo "<h2>LxTronic - Réinitialisation Admin</h2>";

try {
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
