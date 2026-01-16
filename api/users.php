<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

header('Content-Type: application/json');

require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/session_check.php';

// Only admins may manage users
check_admin_only();

$method = $_SERVER['REQUEST_METHOD'];

// Helper: read JSON input
function get_json_input() {
	$data = file_get_contents('php://input');
	return json_decode($data, true) ?? [];
}

try {
	if ($method === 'GET') {
		// list users
		$stmt = $pdo->query('SELECT user_id, username, full_name, role, is_active, created_at FROM users ORDER BY user_id');
		$users = $stmt->fetchAll();
		echo json_encode(['success' => true, 'users' => $users]);
		exit;
	}

	if ($method === 'POST') {
		$input = get_json_input();
		$username = trim($input['username'] ?? '');
		$password = $input['password'] ?? '';
		$full_name = trim($input['full_name'] ?? '');
		$role = $input['role'] ?? 'employee';

		if ($username === '' || $password === '' || $full_name === '') {
			http_response_code(400);
			echo json_encode(['success' => false, 'message' => 'Missing required fields']);
			exit;
		}

		// check unique username
		$stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = ?');
		$stmt->execute([$username]);
		if ($stmt->fetchColumn() > 0) {
			http_response_code(409);
			echo json_encode(['success' => false, 'message' => 'Username already exists']);
			exit;
		}

		$password_hash = password_hash($password, PASSWORD_BCRYPT);
		$stmt = $pdo->prepare('INSERT INTO users (username, password_hash, full_name, role, is_active) VALUES (?, ?, ?, ?, 1)');
		$stmt->execute([$username, $password_hash, $full_name, $role]);
		$newId = $pdo->lastInsertId();
		echo json_encode(['success' => true, 'message' => 'User created', 'user_id' => (int)$newId]);
		exit;
	}

	if ($method === 'PUT') {
		$input = get_json_input();
		$user_id = (int)($input['user_id'] ?? 0);
		if ($user_id <= 0) {
			http_response_code(400);
			echo json_encode(['success' => false, 'message' => 'Invalid user id']);
			exit;
		}

		// prevent admin from removing own admin role or deleting themselves via update
		$currentId = get_current_user_id();

		$fields = [];
		$params = [];

		if (isset($input['username'])) { $fields[] = 'username = ?'; $params[] = trim($input['username']); }
		if (!empty($input['password'])) { $fields[] = 'password_hash = ?'; $params[] = password_hash($input['password'], PASSWORD_BCRYPT); }
		if (isset($input['full_name'])) { $fields[] = 'full_name = ?'; $params[] = trim($input['full_name']); }
		if (isset($input['role'])) { $fields[] = 'role = ?'; $params[] = $input['role']; }
		if (isset($input['is_active'])) { $fields[] = 'is_active = ?'; $params[] = (int)$input['is_active']; }

		if (empty($fields)) {
			echo json_encode(['success' => false, 'message' => 'No fields to update']);
			exit;
		}

		// If changing username, ensure uniqueness
		if (isset($input['username'])) {
			$stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = ? AND user_id <> ?');
			$stmt->execute([trim($input['username']), $user_id]);
			if ($stmt->fetchColumn() > 0) {
				http_response_code(409);
				echo json_encode(['success' => false, 'message' => 'Username already taken']);
				exit;
			}
		}

		$params[] = $user_id;
		$sql = 'UPDATE users SET ' . implode(', ', $fields) . ' WHERE user_id = ?';
		$stmt = $pdo->prepare($sql);
		$stmt->execute($params);
		echo json_encode(['success' => true, 'message' => 'User updated']);
		exit;
	}

	if ($method === 'DELETE') {
		// accept id via query param
		$id = (int)($_GET['id'] ?? 0);
		if ($id <= 0) {
			http_response_code(400);
			echo json_encode(['success' => false, 'message' => 'Invalid user id']);
			exit;
		}

		$currentId = get_current_user_id();
		if ($id == $currentId) {
			http_response_code(400);
			echo json_encode(['success' => false, 'message' => 'You cannot delete your own account']);
			exit;
		}

		// Optionally protect last admin deletion: ensure at least one other admin exists
		$stmt = $pdo->prepare('SELECT role FROM users WHERE user_id = ?');
		$stmt->execute([$id]);
		$row = $stmt->fetch();
		if (!$row) {
			http_response_code(404);
			echo json_encode(['success' => false, 'message' => 'User not found']);
			exit;
		}
		if ($row['role'] === 'admin') {
			$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
			if ($stmt->fetchColumn() <= 1) {
				http_response_code(400);
				echo json_encode(['success' => false, 'message' => 'Cannot delete the last admin']);
				exit;
			}
		}

		$stmt = $pdo->prepare('DELETE FROM users WHERE user_id = ?');
		$stmt->execute([$id]);
		echo json_encode(['success' => true, 'message' => 'User deleted']);
		exit;
	}

	// Method not allowed
	http_response_code(405);
	echo json_encode(['success' => false, 'message' => 'Method not allowed']);

} catch (Exception $e) {
	http_response_code(500);
	echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

?>

