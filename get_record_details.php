<?php

session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/dbconfig.php';
checkLogin();

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => '缺少参数']);
    exit;
}

$recordId = intval($_GET['id']);
$currentAgent = $_SESSION['username'];

$db = getDB();
if (!$db) {
    echo json_encode(['success' => false, 'message' => '数据库连接失败']);
    exit;
}

$stmt = $db->prepare("SELECT * FROM `XE-SKDJWKSNCDATA` WHERE id = ? AND agent_account = ?");
$stmt->bind_param("is", $recordId, $currentAgent);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $record = $result->fetch_assoc();
    echo json_encode(['success' => true, 'record' => $record]);
} else {
    echo json_encode(['success' => false, 'message' => '记录不存在或无权限访问']);
}
?>