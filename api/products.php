<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../inc/db.php';

$method = $_SERVER['REQUEST_METHOD'];

function respond($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

// Helper to get JSON payload
$rawInput = file_get_contents('php://input');
$payload = json_decode($rawInput, true);

try {
    if ($method === 'GET') {
        if (isset($_GET['id'])) {
            $stmt = $pdo->prepare('SELECT * FROM products WHERE product_id = ?');
            $stmt->execute([$_GET['id']]);
            $row = $stmt->fetch();
            if ($row) {
                $product = [
                    'id' => (int)$row['product_id'],
                    'name' => $row['product_name'],
                    'description' => $row['description'],
                    'price' => (float)$row['price'],
                    'stock' => (int)$row['quantity_in_stock'],
                    'category' => $row['category'],
                    'barcode' => $row['barcode'],
                    'icon' => $row['image_url'] ?? null,
                    'created_at' => $row['created_at'],
                    'updated_at' => $row['updated_at']
                ];
                respond(['success' => true, 'data' => $product]);
            }
            respond(['success' => false, 'message' => 'Product not found'], 404);
        }

        $stmt = $pdo->query('SELECT * FROM products ORDER BY product_id DESC');
        $rows = $stmt->fetchAll();
        $products = array_map(function($row) {
            return [
                'id' => (int)$row['product_id'],
                'name' => $row['product_name'],
                'description' => $row['description'],
                'price' => (float)$row['price'],
                'stock' => (int)$row['quantity_in_stock'],
                'category' => $row['category'],
                'barcode' => $row['barcode'],
                'icon' => $row['image_url'] ?? null,
                'is_active' => (bool)$row['is_active'],
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at']
            ];
        }, $rows);
        respond(['success' => true, 'data' => $products]);

    } elseif ($method === 'POST') {
        // Create
        $name = trim($payload['name'] ?? '');
        $price = isset($payload['price']) ? floatval($payload['price']) : null;
        $stock = isset($payload['stock']) ? intval($payload['stock']) : null;
        $category = trim($payload['category'] ?? '');

        if ($name === '' || $price === null || $stock === null || $category === '') {
            respond(['success' => false, 'message' => 'Missing required fields: name, price, stock, category'], 400);
        }

        $description = $payload['description'] ?? null;
        $barcode = $payload['barcode'] ?? null;
        $icon = $payload['icon'] ?? null; // stored in image_url

        $stmt = $pdo->prepare('INSERT INTO products (product_name, description, price, quantity_in_stock, category, barcode, image_url, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())');
        $stmt->execute([$name, $description, $price, $stock, $category, $barcode, $icon]);

        $id = $pdo->lastInsertId();
        $stmt = $pdo->prepare('SELECT * FROM products WHERE product_id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        $product = [
            'id' => (int)$row['product_id'],
            'name' => $row['product_name'],
            'description' => $row['description'],
            'price' => (float)$row['price'],
            'stock' => (int)$row['quantity_in_stock'],
            'category' => $row['category'],
            'barcode' => $row['barcode'],
            'icon' => $row['image_url'] ?? null,
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at']
        ];

        respond(['success' => true, 'data' => $product], 201);

    } elseif ($method === 'PUT') {
        // Update
        if (!isset($_GET['id'])) respond(['success' => false, 'message' => 'Missing product id'], 400);
        $id = intval($_GET['id']);

        $stmt = $pdo->prepare('SELECT * FROM products WHERE product_id = ?');
        $stmt->execute([$id]);
        $existing = $stmt->fetch();
        if (!$existing) respond(['success' => false, 'message' => 'Product not found'], 404);

        $name = trim($payload['name'] ?? $existing['product_name']);
        $price = isset($payload['price']) ? floatval($payload['price']) : $existing['price'];
        $stock = isset($payload['stock']) ? intval($payload['stock']) : $existing['quantity_in_stock'];
        $category = trim($payload['category'] ?? $existing['category']);
        $description = $payload['description'] ?? $existing['description'];
        $barcode = $payload['barcode'] ?? $existing['barcode'];
        $icon = $payload['icon'] ?? $existing['image_url'];

        $stmt = $pdo->prepare('UPDATE products SET product_name = ?, description = ?, price = ?, quantity_in_stock = ?, category = ?, barcode = ?, image_url = ?, updated_at = NOW() WHERE product_id = ?');
        $stmt->execute([$name, $description, $price, $stock, $category, $barcode, $icon, $id]);

        $stmt = $pdo->prepare('SELECT * FROM products WHERE product_id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        $product = [
            'id' => (int)$row['product_id'],
            'name' => $row['product_name'],
            'description' => $row['description'],
            'price' => (float)$row['price'],
            'stock' => (int)$row['quantity_in_stock'],
            'category' => $row['category'],
            'barcode' => $row['barcode'],
            'icon' => $row['image_url'] ?? null,
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at']
        ];

        respond(['success' => true, 'data' => $product]);

    } elseif ($method === 'DELETE') {
        if (!isset($_GET['id'])) respond(['success' => false, 'message' => 'Missing product id'], 400);
        $id = intval($_GET['id']);

        $stmt = $pdo->prepare('SELECT * FROM products WHERE product_id = ?');
        $stmt->execute([$id]);
        $existing = $stmt->fetch();
        if (!$existing) respond(['success' => false, 'message' => 'Product not found'], 404);

        $stmt = $pdo->prepare('DELETE FROM products WHERE product_id = ?');
        $stmt->execute([$id]);

        respond(['success' => true, 'message' => 'Product deleted']);
    } else {
        respond(['success' => false, 'message' => 'Method not allowed'], 405);
    }
} catch (Exception $e) {
    respond(['success' => false, 'message' => $e->getMessage()], 500);
}
