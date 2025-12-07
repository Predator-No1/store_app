<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../inc/db.php';

function respond($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['success' => false, 'message' => 'Method not allowed'], 405);
}

// Validate payload
$items = $payload['items'] ?? null;
$payment = $payload['paymentMethod'] ?? 'cash';
$notes = $payload['notes'] ?? null;
$created_by = isset($payload['userId']) ? intval($payload['userId']) : null;

if (!is_array($items) || count($items) === 0) {
    respond(['success' => false, 'message' => 'No items provided'], 400);
}
if ($created_by === null) {
    respond(['success' => false, 'message' => 'Missing userId'], 400);
}

try {
    $pdo->beginTransaction();

    $total = 0.0;
    $orderItems = [];

    // Lock and verify stock for each product
    $selectStmt = $pdo->prepare('SELECT product_id, product_name, price, quantity_in_stock FROM products WHERE product_id = ? FOR UPDATE');
    foreach ($items as $it) {
        $pid = intval($it['id']);
        $qty = intval($it['quantity']);
        if ($qty <= 0) {
            $pdo->rollBack();
            respond(['success' => false, 'message' => 'Invalid quantity for product ' . $pid], 400);
        }

        $selectStmt->execute([$pid]);
        $row = $selectStmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $pdo->rollBack();
            respond(['success' => false, 'message' => 'Product not found: ' . $pid], 404);
        }

        if (intval($row['quantity_in_stock']) < $qty) {
            $pdo->rollBack();
            respond(['success' => false, 'message' => 'Insufficient stock for product: ' . $row['product_name']], 400);
        }

        $unitPrice = (float)$row['price'];
        $subtotal = $unitPrice * $qty;
        $total += $subtotal;

        $orderItems[] = [
            'product_id' => $pid,
            'product_name' => $row['product_name'],
            'quantity' => $qty,
            'unit_price' => $unitPrice,
            'subtotal' => $subtotal
        ];
    }

    // Create order_number
    $orderNumber = 'CMD-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)),0,6));

    $insertOrder = $pdo->prepare('INSERT INTO orders (order_number, total_amount, payment_method, order_status, notes, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())');
    $insertOrder->execute([$orderNumber, $total, $payment, 'completed', $notes, $created_by]);
    $orderId = $pdo->lastInsertId();

    $insertItem = $pdo->prepare('INSERT INTO order_items (order_id, product_id, product_name, quantity, unit_price, subtotal) VALUES (?, ?, ?, ?, ?, ?)');
    $updateStock = $pdo->prepare('UPDATE products SET quantity_in_stock = quantity_in_stock - ? WHERE product_id = ?');

    foreach ($orderItems as $oi) {
        $insertItem->execute([$orderId, $oi['product_id'], $oi['product_name'], $oi['quantity'], $oi['unit_price'], $oi['subtotal']]);
        $updateStock->execute([$oi['quantity'], $oi['product_id']]);
    }

    // Create invoice
    $invoiceNumber = 'INV-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)),0,6));
    $insertInvoice = $pdo->prepare('INSERT INTO invoices (invoice_number, order_id, total_amount, issue_date, notes) VALUES (?, ?, ?, NOW(), ?)');
    $insertInvoice->execute([$invoiceNumber, $orderId, $total, $notes]);
    $invoiceId = $pdo->lastInsertId();

    $pdo->commit();

    respond([
        'success' => true,
        'data' => [
            'order' => ['order_id' => (int)$orderId, 'order_number' => $orderNumber, 'total' => $total],
            'invoice' => ['invoice_id' => (int)$invoiceId, 'invoice_number' => $invoiceNumber]
        ]
    ], 201);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    respond(['success' => false, 'message' => $e->getMessage()], 500);
}

?>
