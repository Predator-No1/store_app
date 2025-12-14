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
    
    // Get user role and ID from session or headers
    $userId = $_SESSION['userId'] ?? null;
    $userRole = $_SESSION['role'] ?? null;
    
    // For employees, filter data to their own orders only
    $createdByFilter = '';
    $params = [];
    if ($userRole === 'employee' && $userId) {
        $createdByFilter = ' AND created_by = ?';
        $params[] = $userId;
    }
    
    // Today's sales and orders
    $stmt = $pdo->prepare("SELECT COUNT(*) AS cnt, COALESCE(SUM(total_amount),0) AS total FROM orders WHERE DATE(created_at) = CURDATE() AND order_status = 'completed'{$createdByFilter}");
    $stmt->execute($params);
    $row = $stmt->fetch();

    $todayOrders = (int)$row['cnt'];
    $todaySales = (float)$row['total'];

    // Product metrics:
    // totalUnitsInStock = sum(quantity_in_stock)
    // productCount = number of distinct active products with stock > 0
    // Only admins see these
    $totalUnitsInStock = 0;
    $productCount = 0;
    $lowStockCount = 0;
    if ($userRole !== 'employee') {
        $stmt = $pdo->query('SELECT COALESCE(SUM(quantity_in_stock),0) AS total_units, COALESCE(SUM(CASE WHEN quantity_in_stock>0 THEN 1 ELSE 0 END),0) AS product_count FROM products WHERE is_active = 1');
        $prodRow = $stmt->fetch();
        $totalUnitsInStock = (int)$prodRow['total_units'];
        $productCount = (int)$prodRow['product_count'];

        // Low stock count (use view low_stock_products if exists)
        try {
            $stmt = $pdo->query('SELECT COUNT(*) AS cnt FROM low_stock_products');
            $ls = $stmt->fetch();
            $lowStockCount = (int)$ls['cnt'];
        } catch (Exception $e) {
            // Fallback: count products with quantity_in_stock < 10
            $stmt = $pdo->query('SELECT COUNT(*) AS cnt FROM products WHERE quantity_in_stock < 10 AND is_active = 1');
            $ls = $stmt->fetch();
            $lowStockCount = (int)$ls['cnt'];
        }
    }

    // Average basket (average order total) over last 30 days
    $stmt = $pdo->prepare("SELECT COALESCE(AVG(total_amount),0) AS avg_basket FROM orders WHERE order_status = 'completed' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY){$createdByFilter}");
    $stmt->execute($params);
    $avgRow = $stmt->fetch();
    $avgBasket = (float)$avgRow['avg_basket'];

    // Weekly sales for last 7 days (date, orders, revenue)
    $weeklySales = [];
    $stmt = $pdo->prepare("SELECT DATE(created_at) AS day, COUNT(*) AS orders_count, COALESCE(SUM(total_amount),0) AS revenue FROM orders WHERE order_status='completed' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY){$createdByFilter} GROUP BY DATE(created_at) ORDER BY DATE(created_at) ASC");
    $stmt->execute($params);
    $ws = $stmt->fetchAll();
    // build a map for days to ensure zero values for missing days
    $dayMap = [];
    foreach ($ws as $r) {
        $dayMap[$r['day']] = ['orders' => (int)$r['orders_count'], 'revenue' => (float)$r['revenue']];
    }
    // ensure last 7 days
    for ($i = 6; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-{$i} days"));
        $weeklySales[] = [ 'day' => $d, 'orders' => $dayMap[$d]['orders'] ?? 0, 'revenue' => $dayMap[$d]['revenue'] ?? 0.0 ];
    }

    // Top products (try view top_products, fallback to aggregated query)
    $topProducts = [];
    try {
        if ($userRole === 'employee' && $userId) {
            // Employees see only their top products
            $stmt = $pdo->prepare('SELECT p.product_id, p.product_name, COALESCE(SUM(oi.quantity),0) AS qty, COALESCE(SUM(oi.subtotal),0) AS revenue FROM products p JOIN order_items oi ON p.product_id = oi.product_id JOIN orders o ON oi.order_id = o.order_id AND o.order_status = ? AND o.created_by = ? GROUP BY p.product_id ORDER BY revenue DESC LIMIT 5');
            $stmt->execute(['completed', $userId]);
        } else {
            // Admins see all top products
            $stmt = $pdo->query('SELECT product_id, product_name, total_quantity_sold AS qty, total_revenue AS revenue FROM top_products ORDER BY total_revenue DESC LIMIT 5');
        }
        $rows = $stmt->fetchAll();
        foreach ($rows as $r) {
            $topProducts[] = ['id' => (int)$r['product_id'], 'name' => $r['product_name'], 'qty' => (int)$r['qty'], 'revenue' => (float)$r['revenue']];
        }
    } catch (Exception $e) {
        $stmt = $pdo->prepare('SELECT p.product_id, p.product_name, COALESCE(SUM(oi.quantity),0) AS qty, COALESCE(SUM(oi.subtotal),0) AS revenue FROM products p JOIN order_items oi ON p.product_id = oi.product_id JOIN orders o ON oi.order_id = o.order_id AND o.order_status = ?' . ($userRole === 'employee' && $userId ? ' AND o.created_by = ?' : '') . ' GROUP BY p.product_id ORDER BY revenue DESC LIMIT 5');
        $empParams = ['completed'];
        if ($userRole === 'employee' && $userId) $empParams[] = $userId;
        $stmt->execute($empParams);
        $rows = $stmt->fetchAll();
        foreach ($rows as $r) {
            $topProducts[] = ['id' => (int)$r['product_id'], 'name' => $r['product_name'], 'qty' => (int)$r['qty'], 'revenue' => (float)$r['revenue']];
        }
    }

    // Low stock products list (limit 10) - Admins only
    $lowStockProducts = [];
    if ($userRole !== 'employee') {
        $stmt = $pdo->prepare('SELECT product_id, product_name, quantity_in_stock, price FROM products WHERE quantity_in_stock < 10 AND is_active = 1 ORDER BY quantity_in_stock ASC LIMIT 10');
        $stmt->execute();
        $rows = $stmt->fetchAll();
        foreach ($rows as $r) {
            $lowStockProducts[] = ['id' => (int)$r['product_id'], 'name' => $r['product_name'], 'stock' => (int)$r['quantity_in_stock'], 'price' => (float)$r['price']];
        }
    }

    // Recent activity - construct a merged list from activity_logs (preferred), orders, products and invoices
    $recent = [];
    try {
        if ($userRole === 'employee' && $userId) {
            // Employees see only their activity
            $stmt = $pdo->prepare('SELECT order_id AS id, order_number AS order_number, total_amount AS amount, created_at FROM orders WHERE created_by = ? ORDER BY created_at DESC LIMIT 6');
            $stmt->execute([$userId]);
        } else {
            // Admins see all activity
            $stmt = $pdo->query('SELECT al.log_id, al.user_id, al.action_type, al.entity_type, al.entity_id, al.description, al.created_at, u.full_name FROM activity_logs al LEFT JOIN users u ON u.user_id = al.user_id ORDER BY al.created_at DESC LIMIT 6');
        }
        $rows = $stmt->fetchAll();
        if (count($rows) > 0) {
            foreach ($rows as $r) {
                if ($userRole === 'employee') {
                    // Employee view - show their orders
                    $recent[] = [
                        'id' => (int)$r['id'],
                        'type' => 'order',
                        'title' => 'Commande ' . ($r['order_number'] ?? ''),
                        'description' => ($r['amount'] ?? '') . ' FCFA',
                        'user' => null,
                        'created_at' => $r['created_at']
                    ];
                } else {
                    // Admin view
                    $recent[] = [
                        'id' => (int)$r['log_id'] ?? null,
                        'type' => $r['entity_type'] ?? ($r['action_type'] ?? 'log'),
                        'title' => $r['action_type'] ?? ucfirst($r['entity_type'] ?? 'Action'),
                        'description' => $r['description'],
                        'user' => $r['full_name'] ?? null,
                        'created_at' => $r['created_at']
                    ];
                }
            }
        } else {
            // Fallback for employees - show recent orders only
            if ($userRole === 'employee' && $userId) {
                $stmt = $pdo->prepare('SELECT order_id AS id, order_number AS order_number, total_amount AS amount, created_at FROM orders WHERE created_by = ? ORDER BY created_at DESC LIMIT 6');
                $stmt->execute([$userId]);
                $rows = $stmt->fetchAll();
                foreach ($rows as $r) {
                    $recent[] = [
                        'id' => (int)$r['id'],
                        'type' => 'order',
                        'title' => 'Commande ' . ($r['order_number'] ?? ''),
                        'description' => ($r['amount'] ?? '') . ' FCFA',
                        'user' => null,
                        'created_at' => $r['created_at']
                    ];
                }
            }
        }
    } catch (Exception $e) {
        // As a final fallback, return recent orders only
        if ($userRole === 'employee' && $userId) {
            $stmt = $pdo->prepare('SELECT order_id AS id, order_number AS order_number, total_amount AS amount, created_at FROM orders WHERE created_by = ? ORDER BY created_at DESC LIMIT 6');
            $stmt->execute([$userId]);
        } else {
            $stmt = $pdo->query('SELECT order_id AS id, order_number AS order_number, total_amount AS amount, created_at FROM orders ORDER BY created_at DESC LIMIT 6');
        }
        $rows = $stmt->fetchAll();
        foreach ($rows as $r) {
            $recent[] = [
                'id' => (int)$r['id'],
                'type' => 'order',
                'title' => 'Commande ' . ($r['order_number'] ?? ''),
                'description' => ($r['amount'] ?? '') . ' FCFA',
                'user' => null,
                'created_at' => $r['created_at']
            ];
        }
    }

    respond([
        'success' => true,
        'data' => [
            'todaySales' => $todaySales,
            'todayOrders' => $todayOrders,
            'productCount' => $productCount,
            'totalUnitsInStock' => $totalUnitsInStock,
            'lowStockCount' => $lowStockCount,
            'avgBasket' => $avgBasket,
            'weeklySales' => $weeklySales,
            'topProducts' => $topProducts,
            'lowStockProducts' => $lowStockProducts,
            'recentActivity' => $recent
        ]
    ]);

} catch (Exception $e) {
    respond(['success' => false, 'message' => $e->getMessage()], 500);
}

?>
