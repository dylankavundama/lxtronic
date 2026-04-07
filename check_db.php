<?php
/**
 * Script de vérification et de mise à jour de la base de données LXTRONIC.
 * À exécuter sur le serveur en ligne pour s'assurer que toutes les tables et colonnes existent.
 */

require_once 'config/db.php';

echo "<h1>Vérification de la Base de Données LXTRONIC</h1>";
echo "<pre>";

$expected_tables = [
    'users' => [
        'id' => 'INT', 'username' => 'VARCHAR', 'password' => 'VARCHAR', 'role' => 'ENUM', 'created_at' => 'TIMESTAMP'
    ],
    'categories' => [
        'id' => 'INT', 'name' => 'VARCHAR', 'description' => 'TEXT', 'created_at' => 'TIMESTAMP'
    ],
    'products' => [
        'id' => 'INT', 'category_id' => 'INT', 'name' => 'VARCHAR', 'description' => 'TEXT',
        'buy_price' => 'DECIMAL', 'sell_price' => 'DECIMAL', 'stock_quantity' => 'INT',
        'min_stock_level' => 'INT', 'image_url' => 'VARCHAR', 'created_at' => 'TIMESTAMP'
    ],
    'clients' => [
        'id' => 'INT', 'name' => 'VARCHAR', 'phone' => 'VARCHAR', 'address' => 'TEXT',
        'total_debt_usd' => 'DECIMAL', 'total_debt_cdf' => 'DECIMAL', 'created_at' => 'TIMESTAMP'
    ],
    'sales' => [
        'id' => 'INT', 'user_id' => 'INT', 'client_id' => 'INT', 'total_amount' => 'DECIMAL',
        'currency' => 'ENUM', 'exchange_rate' => 'DECIMAL', 'payment_type' => 'ENUM',
        'status' => 'ENUM', 'created_at' => 'TIMESTAMP'
    ],
    'sale_items' => [
        'id' => 'INT', 'sale_id' => 'INT', 'product_id' => 'INT', 'quantity' => 'INT',
        'unit_price' => 'DECIMAL', 'subtotal' => 'DECIMAL'
    ],
    'payments' => [
        'id' => 'INT', 'sale_id' => 'INT', 'client_id' => 'INT', 'amount_paid' => 'DECIMAL',
        'currency' => 'ENUM', 'payment_method' => 'ENUM', 'payment_date' => 'TIMESTAMP',
        'reference' => 'VARCHAR'
    ],
    'expenses' => [
        'id' => 'INT', 'user_id' => 'INT', 'title' => 'VARCHAR', 'amount' => 'DECIMAL',
        'currency' => 'ENUM', 'description' => 'TEXT', 'expense_date' => 'DATE', 'created_at' => 'TIMESTAMP'
    ],
    'settings' => [
        'id' => 'INT', 'setting_key' => 'VARCHAR', 'setting_value' => 'TEXT', 'updated_at' => 'TIMESTAMP'
    ],
    'bank_accounts' => [
        'id' => 'INT', 'account_name' => 'VARCHAR', 'owner_name' => 'VARCHAR',
        'balance_usd' => 'DECIMAL', 'balance_cdf' => 'DECIMAL', 'created_at' => 'TIMESTAMP'
    ],
    'bank_transactions' => [
        'id' => 'INT', 'account_id' => 'INT', 'type' => 'ENUM', 'amount' => 'DECIMAL',
        'currency' => 'ENUM', 'description' => 'TEXT', 'created_at' => 'TIMESTAMP'
    ],
    'system_logs' => [
        'id' => 'INT', 'level' => 'VARCHAR', 'message' => 'TEXT', 'file' => 'VARCHAR',
        'line' => 'INT', 'created_at' => 'TIMESTAMP'
    ]
];

$all_good = true;

// 1. Vérification des tables
foreach ($expected_tables as $table => $columns) {
    try {
        $result = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($result->rowCount() == 0) {
            echo "❌ Table manquante : $table\n";
            $all_good = false;
        } else {
            echo "✅ Table trouvée : $table\n";
            
            // 2. Vérification des colonnes
            $cols_query = $pdo->query("SHOW COLUMNS FROM $table")->fetchAll(PDO::FETCH_ASSOC);
            $actual_cols = array_column($cols_query, 'Field');
            
            foreach ($columns as $col => $type) {
                if (!in_array($col, $actual_cols)) {
                    echo "   ↪ ❌ Colonne manquante : $col dans la table $table\n";
                    $all_good = false;
                    
                    // Auto-correction proposée pour certains champs connus
                    if ($table === 'clients' && $col === 'total_debt_usd') {
                        $pdo->exec("ALTER TABLE clients ADD COLUMN total_debt_usd DECIMAL(15, 2) DEFAULT 0.00 AFTER address");
                        echo "      => [CORRIGÉ] Colonne 'total_debt_usd' ajoutée automatiquement.\n";
                    }
                    if ($table === 'clients' && $col === 'total_debt_cdf') {
                        $pdo->exec("ALTER TABLE clients ADD COLUMN total_debt_cdf DECIMAL(15, 2) DEFAULT 0.00 AFTER total_debt_usd");
                        echo "      => [CORRIGÉ] Colonne 'total_debt_cdf' ajoutée automatiquement.\n";
                    }
                    if ($table === 'products' && $col === 'min_stock_level') {
                        $pdo->exec("ALTER TABLE products ADD COLUMN min_stock_level INT DEFAULT 5");
                        echo "      => [CORRIGÉ] Colonne 'min_stock_level' ajoutée automatiquement.\n";
                    }
                    if ($table === 'products' && $col === 'image_url') {
                        $pdo->exec("ALTER TABLE products ADD COLUMN image_url VARCHAR(255) DEFAULT NULL");
                        echo "      => [CORRIGÉ] Colonne 'image_url' ajoutée automatiquement.\n";
                    }
                    if ($table === 'payments' && $col === 'payment_method') {
                        $pdo->exec("ALTER TABLE payments ADD COLUMN payment_method ENUM('cash', 'bank', 'mobile') DEFAULT 'cash'");
                        echo "      => [CORRIGÉ] Colonne 'payment_method' ajoutée automatiquement.\n";
                    }
                    if ($table === 'payments' && $col === 'reference') {
                        $pdo->exec("ALTER TABLE payments ADD COLUMN reference VARCHAR(100) DEFAULT NULL");
                        echo "      => [CORRIGÉ] Colonne 'reference' ajoutée automatiquement.\n";
                    }
                    if ($table === 'settings' && $col === 'id') {
                        $pdo->exec("ALTER TABLE settings ADD COLUMN id INT AUTO_INCREMENT PRIMARY KEY FIRST");
                        echo "      => [CORRIGÉ] Colonne 'id' ajoutée automatiquement.\n";
                    }
                    if ($table === 'settings' && $col === 'updated_at') {
                        $pdo->exec("ALTER TABLE settings ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
                        echo "      => [CORRIGÉ] Colonne 'updated_at' ajoutée automatiquement.\n";
                    }
                }
            }
        }
    } catch (PDOException $e) {
        echo "Erreur lors de la vérification de la table $table : " . $e->getMessage() . "\n";
    }
}

// 3. Auto-création des tables de banque si elles n'existent pas
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS bank_accounts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            account_name VARCHAR(100) NOT NULL,
            owner_name VARCHAR(100) NOT NULL,
            balance_usd DECIMAL(15, 2) DEFAULT 0.00,
            balance_cdf DECIMAL(15, 2) DEFAULT 0.00,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS bank_transactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            account_id INT,
            type ENUM('deposit', 'withdrawal') NOT NULL,
            amount DECIMAL(15, 2) NOT NULL,
            currency ENUM('USD', 'CDF') NOT NULL,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (account_id) REFERENCES bank_accounts(id) ON DELETE CASCADE
        );
        CREATE TABLE IF NOT EXISTS system_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            level VARCHAR(50) NOT NULL,
            message TEXT NOT NULL,
            file VARCHAR(255) NOT NULL,
            line INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    ");
} catch (PDOException $e) {
    echo "Erreur lors de la création des tables bancaires : " . $e->getMessage() . "\n";
}

echo "\n";
if ($all_good) {
    echo "🎉 TOUT EST À JOUR ! Votre base de données en ligne correspond parfaitement à la dernière version de LXTRONIC.\n";
} else {
    echo "⚠️ Attention : Des différences ont été détectées (voir ci-dessus). Des corrections automatiques ont peut-être été appliquées.\n";
    echo "Veuillez rafraîchir cette page pour vérifier si tous les '❌' ont disparu.\n";
}

echo "</pre>";
echo "<a href='dashboard.php' style='display:inline-block; margin-top:20px; padding:10px 20px; background:#4f46e5; color:white; text-decoration:none; border-radius:5px;'>Retour au Dashboard</a>";
?>
