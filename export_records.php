<?php

session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/dbconfig.php';
checkLogin();

$currentAgent = $_SESSION['username'];

// 获取筛选参数
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$platform = isset($_GET['platform']) ? $_GET['platform'] : '';
$dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// 构建查询条件
$whereConditions = ["agent_account = ?"];
$params = [$currentAgent];
$paramTypes = "s";

if ($search) {
    $whereConditions[] = "(session_id LIKE ? OR customer_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $paramTypes .= "ss";
}

if ($status && $status !== 'all') {
    $whereConditions[] = "status = ?";
    $params[] = $status;
    $paramTypes .= "s";
}

if ($platform && $platform !== 'all') {
    $whereConditions[] = "platform = ?";
    $params[] = $platform;
    $paramTypes .= "s";
}

if ($dateFrom) {
    $whereConditions[] = "created_at >= ?";
    $params[] = $dateFrom . ' 00:00:00';
    $paramTypes .= "s";
}

if ($dateTo) {
    $whereConditions[] = "created_at <= ?";
    $params[] = $dateTo . ' 23:59:59';
    $paramTypes .= "s";
}

$whereSQL = implode(' AND ', $whereConditions);

// 查询数据
$db = getDB();
if ($db) {
    $sql = "SELECT * FROM `XE-SKDJWKSNCDATA` WHERE $whereSQL ORDER BY created_at DESC";
    $stmt = $db->prepare($sql);
    $stmt->bind_param($paramTypes, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // 设置CSV头
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="聊天链接记录_' . date('Ymd_His') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // 添加BOM头，确保Excel正确识别UTF-8编码
    fwrite($output, "\xEF\xBB\xBF");
    
    // 写入标题行
    fputcsv($output, ['ID', '会话ID', '客户名称', '平台', 'XEDATA令牌', '创建时间', '过期时间', '状态', '访问次数', '最后访问时间']);
    
    // 写入数据行
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [
            $row['id'],
            $row['session_id'],
            $row['customer_name'],
            $row['platform'],
            $row['xedata_token'],
            $row['created_at'],
            $row['expires_at'],
            $row['status'],
            $row['visit_count'] ?? 0,
            $row['last_visit_time'] ?? ''
        ]);
    }
    
    fclose($output);
    exit;
}

echo "导出失败，请重试";
?>