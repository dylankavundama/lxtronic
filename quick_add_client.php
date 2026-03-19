<?php
require_once 'config/db.php';
require_once 'includes/helpers.php';
check_auth();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if (empty($name)) {
        echo json_encode(['success' => false, 'message' => 'Le nom est obligatoire.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO clients (name, phone) VALUES (?, ?)");
        $stmt->execute([$name, $phone]);
        $new_id = $pdo->lastInsertId();

        echo json_encode([
            'success' => true,
            'id' => $new_id,
            'name' => $name
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la création : ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée.']);
}
?>
