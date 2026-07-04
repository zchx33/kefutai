<?php
/**
 * 取消 Push 订阅
 * POST /api/push/unsubscribe
 * 
 * 请求体: { user_id } 或 { endpoint }
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
    $endpoint = $input['endpoint'] ?? '';

    if (empty($userId) && empty($endpoint)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '缺少user_id或endpoint']);
        exit;
    }

    $db = getDB();
    if (!$db) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => '数据库连接失败']);
        exit;
    }

    if (!empty($userId)) {
        $stmt = $db->prepare("DELETE FROM push_subscriptions WHERE user_id = ?");
        $stmt->bind_param("s", $userId);
    } else {
        $stmt = $db->prepare("DELETE FROM push_subscriptions WHERE endpoint = ?");
        $stmt->bind_param("s", $endpoint);
    }

    $result = $stmt->execute();

    if ($result) {
        echo json_encode(['success' => true, 'message' => '推送订阅已取消']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => '取消失败: ' . $stmt->error]);
    }

    $stmt->close();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '服务器错误: ' . $e->getMessage()]);
}
