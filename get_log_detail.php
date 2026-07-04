<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/dbconfig.php';
checkLogin();
checkAdmin();

header('Content-Type: application/json');

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => '参数错误']);
    exit;
}

$log_id = intval($_GET['id']);
$db = getDB();

if (!$db) {
    echo json_encode(['success' => false, 'message' => '数据库连接失败']);
    exit;
}

$stmt = $db->prepare("SELECT * FROM visit_logs WHERE id = ?");
$stmt->bind_param("i", $log_id);
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
        'message' => '日志不存在'
    ]);
}
?>