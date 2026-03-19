<?php
require_once 'config/db.php';
require_once 'includes/helpers.php';
check_auth();

header('Content-Type: application/json');

try {
    $products = $pdo->query("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id=c.id ORDER BY p.name ASC")->fetchAll(PDO::FETCH_ASSOC);
    $clients = $pdo->query("SELECT * FROM clients ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
    $categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
    
    // Settings relevant for offline (like exchange rate)
    $settings = [
        'usd_to_cdf' => get_setting('usd_to_cdf', 2800)
    ];

    echo json_encode([
        'success' => true,
        'products' => $products,
        'clients' => $clients,
        'categories' => $categories,
        'settings' => $settings
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
