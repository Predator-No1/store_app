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
    
    $action = isset($_GET['action']) ? $_GET['action'] : 'sales_graph';

    if ($action === 'sales_graph') {
        // Get daily sales for the last 30 days
        // For employees, filter to their own orders only
        $createdByFilter = '';
        $params = [];
        if ($userRole === 'employee' && $userId) {
            $createdByFilter = ' AND created_by = ?';
            $params[] = $userId;
        }
        
        $stmt = $pdo->prepare("
            SELECT 
                DATE(created_at) as sale_date,
                COUNT(*) as total_orders,
                COALESCE(SUM(total_amount), 0) as total_revenue
            FROM orders
            WHERE order_status = 'completed' 
            AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            {$createdByFilter}
            GROUP BY DATE(created_at)
            ORDER BY DATE(created_at) ASC
        ");
        $stmt->execute($params);
        $results = $stmt->fetchAll();

        // Build a map for all days in the last 30 days
        $dayMap = [];
        foreach ($results as $row) {
            $dayMap[$row['sale_date']] = [
                'date' => $row['sale_date'],
                'orders' => (int)$row['total_orders'],
                'revenue' => (float)$row['total_revenue']
            ];
        }

        $salesData = [];
        for ($i = 29; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $salesData[] = $dayMap[$date] ?? [
                'date' => $date,
                'orders' => 0,
                'revenue' => 0.0
            ];
        }

        respond([
            'success' => true,
            'data' => $salesData
        ]);

    } elseif ($action === 'payment_methods') {
        // Get payment method distribution
        // For employees, filter to their own orders only
        $createdByFilter = '';
        $params = [];
        if ($userRole === 'employee' && $userId) {
            $createdByFilter = ' AND created_by = ?';
            $params[] = $userId;
        }
        
        $stmt = $pdo->prepare("
            SELECT 
                payment_method,
                COUNT(*) as count,
                COALESCE(SUM(total_amount), 0) as total
            FROM orders
            WHERE order_status = 'completed'
            {$createdByFilter}
            GROUP BY payment_method
            ORDER BY total DESC
        ");
        $stmt->execute($params);
        $results = $stmt->fetchAll();

        $paymentMethods = [];
        $labels = [
            'cash' => 'Espèces',
            'mobile_money' => 'Mobile Money',
            'bank_transfer' => 'Virement',
            'card' => 'Carte'
        ];
        $colors = [
            'cash' => '#10b981',
            'mobile_money' => '#f59e0b',
            'bank_transfer' => '#3b82f6',
            'card' => '#8b5cf6'
        ];

        foreach ($results as $row) {
            $method = $row['payment_method'];
            $paymentMethods[] = [
                'method' => $method,
                'label' => $labels[$method] ?? ucfirst(str_replace('_', ' ', $method)),
                'count' => (int)$row['count'],
                'total' => (float)$row['total'],
                'color' => $colors[$method] ?? '#667eea'
            ];
        }

        respond([
            'success' => true,
            'data' => $paymentMethods
        ]);

    } else {
        respond(['success' => false, 'message' => 'Unknown action'], 400);
    }

} catch (Exception $e) {
    respond([
        'success' => false,
        'message' => 'Erreur serveur: ' . $e->getMessage()
    ], 500);
}
?>
