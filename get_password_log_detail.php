<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/dbconfig.php';
checkLogin();

header('Content-Type: application/json');

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => '参数错误']);
    exit;
}

$log_id = intval($_GET['id']);
$current_user_id = $_SESSION['user_id'] ?? null;
$isAdmin = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';

$db = getDB();

if (!$db) {
    echo json_encode(['success' => false, 'message' => '数据库连接失败']);
    exit;
}

// 构建查询条件
$sql = "SELECT * FROM user_logs WHERE id = ?";
$params = [$log_id];
$types = "i";

// 如果不是管理员，只能查看自己的记录
if (!$isAdmin) {
    $sql .= " AND user_id = ?";
    $params[] = $current_user_id;
    $types .= "i";
}

$stmt = $db->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    echo json_encode([
        'success' => true,
        'data' => $row
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => '记录不存在或没有权限查看'
    ]);
}
?>