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
    // Today's sales and orders
    $stmt = $pdo->prepare("SELECT COUNT(*) AS cnt, COALESCE(SUM(total_amount),0) AS total FROM orders WHERE DATE(created_at) = CURDATE() AND order_status = 'completed'");
    $stmt->execute();
    $row = $stmt->fetch();

    $todayOrders = (int)$row['cnt'];
    $todaySales = (float)$row['total'];

    // Product metrics:
    // totalUnitsInStock = sum(quantity_in_stock)
    // productCount = number of distinct active products with stock > 0
    $stmt = $pdo->query('SELECT COALESCE(SUM(quantity_in_stock),0) AS total_units, COALESCE(SUM(CASE WHEN quantity_in_stock>0 THEN 1 ELSE 0 END),0) AS product_count FROM products WHERE is_active = 1');
    $prodRow = $stmt->fetch();
    $totalUnitsInStock = (int)$prodRow['total_units'];
    $productCount = (int)$prodRow['product_count'];

    // Low stock count (use view low_stock_products if exists)
    $lowStockCount = 0;
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

    // Average basket (average order total) over last 30 days
    $stmt = $pdo->prepare("SELECT COALESCE(AVG(total_amount),0) AS avg_basket FROM orders WHERE order_status = 'completed' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
    $stmt->execute();
    $avgRow = $stmt->fetch();
    $avgBasket = (float)$avgRow['avg_basket'];

    // Weekly sales for last 7 days (date, orders, revenue)
    $weeklySales = [];
    $stmt = $pdo->prepare("SELECT DATE(created_at) AS day, COUNT(*) AS orders_count, COALESCE(SUM(total_amount),0) AS revenue FROM orders WHERE order_status='completed' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY DATE(created_at) ORDER BY DATE(created_at) ASC");
    $stmt->execute();
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
        $stmt = $pdo->query('SELECT product_id, product_name, total_quantity_sold AS qty, total_revenue AS revenue FROM top_products ORDER BY total_revenue DESC LIMIT 5');
        $rows = $stmt->fetchAll();
        foreach ($rows as $r) {
            $topProducts[] = ['id' => (int)$r['product_id'], 'name' => $r['product_name'], 'qty' => (int)$r['qty'], 'revenue' => (float)$r['revenue']];
        }
    } catch (Exception $e) {
        $stmt = $pdo->prepare('SELECT p.product_id, p.product_name, COALESCE(SUM(oi.quantity),0) AS qty, COALESCE(SUM(oi.subtotal),0) AS revenue FROM products p JOIN order_items oi ON p.product_id = oi.product_id JOIN orders o ON oi.order_id = o.order_id AND o.order_status = ? GROUP BY p.product_id ORDER BY revenue DESC LIMIT 5');
        $stmt->execute(['completed']);
        $rows = $stmt->fetchAll();
        foreach ($rows as $r) {
            $topProducts[] = ['id' => (int)$r['product_id'], 'name' => $r['product_name'], 'qty' => (int)$r['qty'], 'revenue' => (float)$r['revenue']];
        }
    }

    // Low stock products list (limit 10)
    $lowStockProducts = [];
    $stmt = $pdo->prepare('SELECT product_id, product_name, quantity_in_stock, price FROM products WHERE quantity_in_stock < 10 AND is_active = 1 ORDER BY quantity_in_stock ASC LIMIT 10');
    $stmt->execute();
    $rows = $stmt->fetchAll();
    foreach ($rows as $r) {
        $lowStockProducts[] = ['id' => (int)$r['product_id'], 'name' => $r['product_name'], 'stock' => (int)$r['quantity_in_stock'], 'price' => (float)$r['price']];
    }

    // Recent activity - construct a merged list from activity_logs (preferred), orders, products and invoices
    $recent = [];
    try {
        $stmt = $pdo->query('SELECT al.log_id, al.user_id, al.action_type, al.entity_type, al.entity_id, al.description, al.created_at, u.full_name FROM activity_logs al LEFT JOIN users u ON u.user_id = al.user_id ORDER BY al.created_at DESC LIMIT 6');
        $rows = $stmt->fetchAll();
        if (count($rows) > 0) {
            foreach ($rows as $r) {
                $recent[] = [
                    'id' => (int)$r['log_id'],
                    'type' => $r['entity_type'] ?? ($r['action_type'] ?? 'log'),
                    'title' => $r['action_type'] ?? ucfirst($r['entity_type'] ?? 'Action'),
                    'description' => $r['description'],
                    'user' => $r['full_name'] ?? null,
                    'created_at' => $r['created_at']
                ];
            }
        } else {
            // build merged recent activity from orders, products, invoices
            $q = "(SELECT 'order' AS type, CONCAT('Commande ', o.order_number) AS title, CONCAT(o.total_amount, ' FCFA') AS description, o.created_at FROM orders o WHERE o.order_status='completed')
            UNION
            (SELECT 'product' AS type, CONCAT('Produit ajouté: ', p.product_name) AS title, CONCAT('Stock: ', p.quantity_in_stock) AS description, p.created_at FROM products p)
            UNION
            (SELECT 'invoice' AS type, CONCAT('Facture ', i.invoice_number) AS title, CONCAT('Montant: ', i.total_amount, ' FCFA') AS description, i.issue_date AS created_at FROM invoices i)
            ORDER BY created_at DESC LIMIT 6";

            $stmt2 = $pdo->query($q);
            $rows2 = $stmt2->fetchAll();
            foreach ($rows2 as $r) {
                $recent[] = [
                    'id' => null,
                    'type' => $r['type'],
                    'title' => $r['title'],
                    'description' => $r['description'],
                    'user' => null,
                    'created_at' => $r['created_at']
                ];
            }
        }
    } catch (Exception $e) {
        // As a final fallback, return recent orders only
        $stmt = $pdo->query('SELECT order_id AS id, order_number AS order_number, total_amount AS amount, created_at FROM orders ORDER BY created_at DESC LIMIT 6');
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
