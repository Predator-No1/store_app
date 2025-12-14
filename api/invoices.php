<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
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

try {
    // Start session to get user info
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    
    $userId = $_SESSION['userId'] ?? null;
    $userRole = $_SESSION['role'] ?? null;
    
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        respond(['success' => false, 'message' => 'Method not allowed'], 405);
    }

    // If id is provided, return detailed invoice (with items)
    if (isset($_GET['id'])) {
        $id = intval($_GET['id']);

        $stmt = $pdo->prepare('SELECT i.invoice_id, i.invoice_number, i.order_id, i.total_amount, i.issue_date, i.notes, o.order_number, o.payment_method, o.created_at, o.created_by FROM invoices i JOIN orders o ON o.order_id = i.order_id WHERE i.invoice_id = ?' . ($userRole === 'employee' && $userId ? ' AND o.created_by = ?' : ''));
        $params = [$id];
        if ($userRole === 'employee' && $userId) $params[] = $userId;
        $stmt->execute($params);
        $inv = $stmt->fetch();
        if (!$inv) respond(['success' => false, 'message' => 'Invoice not found'], 404);

        $itemsStmt = $pdo->prepare('SELECT oi.order_item_id, oi.product_id, oi.product_name, oi.quantity, oi.unit_price, oi.subtotal FROM order_items oi WHERE oi.order_id = ?');
        $itemsStmt->execute([$inv['order_id']]);
        $items = $itemsStmt->fetchAll();

        $data = [
            'invoice_id' => (int)$inv['invoice_id'],
            'invoice_number' => $inv['invoice_number'],
            'order_id' => (int)$inv['order_id'],
            'order_number' => $inv['order_number'],
            'total_amount' => (float)$inv['total_amount'],
            'issue_date' => $inv['issue_date'],
            'notes' => $inv['notes'],
            'payment_method' => $inv['payment_method'],
            'items' => array_map(function($it){
                return [
                    'order_item_id' => (int)$it['order_item_id'],
                    'product_id' => (int)$it['product_id'],
                    'product_name' => $it['product_name'],
                    'quantity' => (int)$it['quantity'],
                    'unit_price' => (float)$it['unit_price'],
                    'subtotal' => (float)$it['subtotal']
                ];
            }, $items)
        ];

        respond(['success' => true, 'data' => $data]);
    }

    // Otherwise return list of invoices (optionally filter by date or search)
    $q = 'SELECT i.invoice_id, i.invoice_number, i.order_id, i.total_amount, i.issue_date, o.order_number, o.payment_method FROM invoices i JOIN orders o ON o.order_id = i.order_id WHERE 1=1' . ($userRole === 'employee' && $userId ? ' AND o.created_by = ?' : '') . ' ORDER BY i.issue_date DESC';
    $params = [];
    if ($userRole === 'employee' && $userId) $params[] = $userId;
    $stmt = $pdo->prepare($q);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    $list = array_map(function($r){
        return [
            'invoice_id' => (int)$r['invoice_id'],
            'invoice_number' => $r['invoice_number'],
            'order_id' => (int)$r['order_id'],
            'order_number' => $r['order_number'],
            'total_amount' => (float)$r['total_amount'],
            'issue_date' => $r['issue_date'],
            'payment_method' => $r['payment_method'] ?? 'cash'
        ];
    }, $rows);

    respond(['success' => true, 'data' => $list]);

} catch (Exception $e) {
    respond(['success' => false, 'message' => $e->getMessage()], 500);
}

?>
