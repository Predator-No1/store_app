<?php
// session_check.php - Utility to check user session and verify roles
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

function check_session() {
    if (empty($_SESSION['userId']) || empty($_SESSION['role'])) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Unauthorized',
            'redirect' => 'index.html'
        ]);
        http_response_code(401);
        exit;
    }
    return [
        'userId' => $_SESSION['userId'],
        'username' => $_SESSION['username'] ?? '',
        'fullName' => $_SESSION['fullName'] ?? '',
        'role' => $_SESSION['role'] ?? 'employee'
    ];
}

function check_admin_only() {
    $user = check_session();
    if ($user['role'] !== 'admin') {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Admin access required'
        ]);
        http_response_code(403);
        exit;
    }
    return $user;
}

function is_admin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function is_employee() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'employee';
}

function get_current_user_id() {
    return $_SESSION['userId'] ?? null;
}
?>
