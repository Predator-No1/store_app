<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../inc/db.php';

$raw = file_get_contents('php://input');
$body = json_decode($raw, true);

if (!$body) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
    exit;
}

$username = trim($body['username'] ?? '');
$password = $body['password'] ?? '';

if ($username === '' || $password === '') {
    echo json_encode(['success' => false, 'message' => 'Missing username or password']);
    exit;
}

$stmt = $pdo->prepare('SELECT user_id, username, password_hash, full_name, role FROM users WHERE username = ? AND is_active = 1 LIMIT 1');
$stmt->execute([$username]);
$user = $stmt->fetch();

if (!$user) {
    echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
    exit;
}

if (!password_verify($password, $user['password_hash'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
    exit;
}

// Success - return basic session info
echo json_encode([
    'success' => true,
    'data' => [
        'userId' => (int)$user['user_id'],
        'username' => $user['username'],
        'fullName' => $user['full_name'],
        'role' => $user['role']
    ]
]);
