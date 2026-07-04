<?php
/**
 * 保存 Push 订阅
 * POST /api/push/subscribe
 * 
 * 请求体: { user_id, user_type, endpoint, p256dh, auth }
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => '仅支持POST请求']);
    exit;
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/dbconfig.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);

    $userId = $input['user_id'] ?? '';
    $userType = $input['user_type'] ?? 'agent';
    $endpoint = $input['endpoint'] ?? '';
    $p256dh = $input['p256dh'] ?? '';
    $auth = $input['auth'] ?? '';

    if (empty($userId) || empty($endpoint) || empty($p256dh) || empty($auth)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '缺少必要参数']);
        exit;
    }

    if (!in_array($userType, ['agent', 'customer'])) {
        $userType = 'agent';
    }

    $db = getDB();
    if (!$db) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => '数据库连接失败']);
        exit;
    }

    // 使用 INSERT ... ON DUPLICATE KEY UPDATE 确保每个用户的订阅是最新的
    $query = "INSERT INTO push_subscriptions (user_id, user_type, endpoint, p256dh, auth_key, created_at, updated_at)
              VALUES (?, ?, ?, ?, ?, NOW(), NOW())
              ON DUPLICATE KEY UPDATE
              endpoint = VALUES(endpoint),
              p256dh = VALUES(p256dh),
              auth_key = VALUES(auth_key),
              updated_at = NOW()";

    $stmt = $db->prepare($query);
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'SQL准备失败: ' . $db->error]);
        exit;
    }

    $stmt->bind_param("sssss", $userId, $userType, $endpoint, $p256dh, $auth);
    $result = $stmt->execute();

    if ($result) {
        echo json_encode(['success' => true, 'message' => '推送订阅已保存']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => '保存失败: ' . $stmt->error]);
    }

    $stmt->close();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '服务器错误: ' . $e->getMessage()]);
}
