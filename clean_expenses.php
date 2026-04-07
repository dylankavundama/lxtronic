<?php
require_once 'config/db.php';
require_admin();

echo "<h1>Nettoyage des Dépenses en Double</h1>";
echo "<pre>";

try {
    // 1. Trouver combien de doublons vont être supprimés
    $stmt_count = $pdo->query("
        SELECT COUNT(*) as count 
        FROM expenses e1 
        INNER JOIN expenses e2 
        WHERE e1.id > e2.id 
          AND e1.title = e2.title 
          AND e1.amount = e2.amount 
          AND e1.expense_date = e2.expense_date 
          AND e1.user_id = e2.user_id
    ");
    $duplicates = $stmt_count->fetchColumn();

    if ($duplicates > 0) {
        echo "🗑️ Trouvé $duplicates dépenses en double exactes (même titre, même montant, même date).\n";
        
        // 2. Supprimer les doublons (On garde l'ID le plus petit)
        $pdo->exec("
            DELETE e1 FROM expenses e1 
            INNER JOIN expenses e2 
            WHERE e1.id > e2.id 
              AND e1.title = e2.title 
              AND e1.amount = e2.amount 
              AND e1.expense_date = e2.expense_date 
              AND e1.user_id = e2.user_id
        ");

        echo "✅ Suppression réussie ! $duplicates doublons ont été retirés de la base de données de manière sécurisée.\n";
    } else {
        echo "🎉 Aucun doublon trouvé ! Votre base de données est déjà propre.\n";
    }

} catch (PDOException $e) {
    echo "❌ Erreur SQL : " . $e->getMessage();
}

echo "</pre>";
echo "<a href='expenses.php' style='display:inline-block; margin-top:20px; padding:10px 20px; background:#e11d48; color:white; text-decoration:none; border-radius:5px;'>Retour aux Dépenses</a>";
?>
