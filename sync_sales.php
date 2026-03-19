<?php
require_once 'config/db.php';
require_once 'includes/helpers.php';
check_auth();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cart_data'])) {
    $cart = json_decode($_POST['cart_data'], true);
    $client_id = !empty($_POST['client_id']) ? $_POST['client_id'] : null;
    $payment_type = $_POST['payment_type'] ?? 'comptant';
    $currency = $_POST['currency'] ?? 'USD';
    $usd_to_cdf = (float)get_setting('usd_to_cdf', 2800);

    if (empty($cart)) {
        echo json_encode(['success' => false, 'message' => 'Panier vide']);
        exit;
    }

    $pdo->beginTransaction();
    try {
        $status = ($payment_type === 'credit') ? 'dette' : 'paye';
        
        $total_amount_usd = 0;
        foreach ($cart as $item) {
            $unit_price = floatval($item['price']);
            $discount = isset($item['discount']) ? floatval($item['discount']) : 0;
            if ($discount > ($unit_price * 0.10)) $discount = $unit_price * 0.10;
            $total_amount_usd += ($unit_price - $discount) * $item['quantity'];
        }
        
        $final_amount = ($currency === 'CDF') ? ($total_amount_usd * $usd_to_cdf) : $total_amount_usd;

        $stmt = $pdo->prepare("INSERT INTO sales (user_id, client_id, total_amount, payment_type, status, currency, exchange_rate) VALUES(?,?,?,?,?,?,?)");
        $stmt->execute([$_SESSION['user_id'], $client_id, $final_amount, $payment_type, $status, $currency, $usd_to_cdf]);
        $sale_id = $pdo->lastInsertId();

        foreach ($cart as $item) {
            $unit_price = floatval($item['price']);
            $discount = isset($item['discount']) ? floatval($item['discount']) : 0;
            if ($discount > ($unit_price * 0.10)) $discount = $unit_price * 0.10;

            $net_unit_price_usd = $unit_price - $discount;
            $subtotal_usd = $net_unit_price_usd * $item['quantity'];
            
            $item_price = ($currency === 'CDF') ? ($net_unit_price_usd * $usd_to_cdf) : $net_unit_price_usd;
            $item_discount = ($currency === 'CDF') ? ($discount * $usd_to_cdf) : $discount;
            $item_subtotal = ($currency === 'CDF') ? ($subtotal_usd * $usd_to_cdf) : $subtotal_usd;

            $pdo->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, unit_price, discount_amount, subtotal) VALUES(?,?,?,?,?,?)")
                ->execute([$sale_id, $item['id'], $item['quantity'], $item_price, $item_discount, $item_subtotal]);
            
            $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id=?")->execute([$item['quantity'], $item['id']]);
        }

        if ($payment_type === 'credit' && $client_id) {
            $pdo->prepare("UPDATE clients SET total_debt_usd = total_debt_usd + ?, total_debt_cdf = total_debt_cdf + ? WHERE id=?")
                ->execute([($currency === 'USD' ? $final_amount : 0), ($currency === 'CDF' ? $final_amount : 0), $client_id]);
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'sale_id' => $sale_id]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Requête invalide']);
}
?>
