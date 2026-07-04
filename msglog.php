<?php
if (!defined('FROM_ROUTER')) {
    http_response_code(403);
    exit('喜乐科技@x60898');
}
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/dbconfig.php';
checkLogin();
checkAdmin();

// 分页参数
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 20; // 每页显示记录数
$offset = ($page - 1) * $limit;

// 搜索参数
$search_session = isset($_GET['session_key']) ? trim($_GET['session_key']) : '';
$search_speaker = isset($_GET['speaker_type']) ? trim($_GET['speaker_type']) : '';
$search_customer = isset($_GET['customer_name']) ? trim($_GET['customer_name']) : '';
$search_content = isset($_GET['content']) ? trim($_GET['content']) : '';
$search_agent = isset($_GET['agent_account']) ? trim($_GET['agent_account']) : '';
$search_date = isset($_GET['date']) ? trim($_GET['date']) : '';

// 检查表是否存在
function checkTableExists($db, $tableName) {
    $result = $db->query("SHOW TABLES LIKE '$tableName'");
    return $result && $result->num_rows > 0;
}

// 获取总记录数和总页数
function getChatsCount($isAdmin, $current_username, $search_session, $search_speaker, $search_customer, $search_content, $search_agent, $search_date) {
    $db = getDB();
    if (!$db) return 0;
    
    // 检查表是否存在
    if (!checkTableExists($db, 'chat_messages')) {
        return 0;
    }
    
    $whereClause = "WHERE 1=1";
    $params = [];
    $types = '';
    
    // 非管理员只能查看自己的聊天记录
    if (!$isAdmin) {
        $whereClause .= " AND agent_account = ?";
        $params[] = $current_username;
        $types .= 's';
    }
    
    if (!empty($search_session)) {
        $whereClause .= " AND session_key LIKE ?";
        $params[] = "%$search_session%";
        $types .= 's';
    }
    
    if (!empty($search_speaker) && $search_speaker != 'all') {
        $whereClause .= " AND speaker_type = ?";
        $params[] = intval($search_speaker);
        $types .= 'i';
    }
    
    if (!empty($search_customer)) {
        $whereClause .= " AND customer_name LIKE ?";
        $params[] = "%$search_customer%";
        $types .= 's';
    }
    
    if (!empty($search_content)) {
        $whereClause .= " AND content LIKE ?";
        $params[] = "%$search_content%";
        $types .= 's';
    }
    
    if (!empty($search_agent)) {
        $whereClause .= " AND agent_account LIKE ?";
        $params[] = "%$search_agent%";
        $types .= 's';
    }
    
    if (!empty($search_date)) {
        $whereClause .= " AND DATE(created_at) = ?";
        $params[] = $search_date;
        $types .= 's';
    }
    
    $sql = "SELECT COUNT(*) as total FROM chat_messages $whereClause";
    $stmt = $db->prepare($sql);
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return $row['total'] ?? 0;
}

// 获取聊天记录
function getChatMessages($isAdmin, $current_username, $search_session, $search_speaker, $search_customer, $search_content, $search_agent, $search_date, $limit, $offset) {
    $db = getDB();
    if (!$db) return [];
    
    // 检查表是否存在
    if (!checkTableExists($db, 'chat_messages')) {
        return [];
    }
    
    $whereClause = "WHERE 1=1";
    $params = [];
    $types = '';
    
    // 非管理员只能查看自己的聊天记录
    if (!$isAdmin) {
        $whereClause .= " AND agent_account = ?";
        $params[] = $current_username;
        $types .= 's';
    }
    
    if (!empty($search_session)) {
        $whereClause .= " AND session_key LIKE ?";
        $params[] = "%$search_session%";
        $types .= 's';
    }
    
    if (!empty($search_speaker) && $search_speaker != 'all') {
        $whereClause .= " AND speaker_type = ?";
        $params[] = intval($search_speaker);
        $types .= 'i';
    }
    
    if (!empty($search_customer)) {
        $whereClause .= " AND customer_name LIKE ?";
        $params[] = "%$search_customer%";
        $types .= 's';
    }
    
    if (!empty($search_content)) {
        $whereClause .= " AND content LIKE ?";
        $params[] = "%$search_content%";
        $types .= 's';
    }
    
    if (!empty($search_agent)) {
        $whereClause .= " AND agent_account LIKE ?";
        $params[] = "%$search_agent%";
        $types .= 's';
    }
    
    if (!empty($search_date)) {
        $whereClause .= " AND DATE(created_at) = ?";
        $params[] = $search_date;
        $types .= 's';
    }
    
    $sql = "SELECT * FROM chat_messages $whereClause ORDER BY created_at DESC, id DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $types .= 'i';
    $params[] = $offset;
    $types .= 'i';
    
    $stmt = $db->prepare($sql);
    
    if (!empty($types)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $messages = [];
    
    while ($row = $result->fetch_assoc()) {
        $messages[] = $row;
    }
    
    return $messages;
}

// 获取统计信息
function getChatStats($isAdmin, $current_username) {
    $db = getDB();
    if (!$db) return [];
    
    // 检查表是否存在
    if (!checkTableExists($db, 'chat_messages')) {
        return [
            'total_messages' => 0,
            'total_sessions' => 0,
            'today_messages' => 0,
            'yesterday_messages' => 0,
            'customer_messages' => 0,
            'agent_messages' => 0
        ];
    }
    
    $whereClause = $isAdmin ? "WHERE 1=1" : "WHERE agent_account = ?";
    $params = $isAdmin ? [] : [$current_username];
    $types = $isAdmin ? '' : 's';
    
    $sql = "
        SELECT 
            COUNT(*) as total_messages,
            COUNT(DISTINCT session_key) as total_sessions,
            COUNT(DISTINCT customer_name) as total_customers,
            COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as today_messages,
            COUNT(CASE WHEN DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY) THEN 1 END) as yesterday_messages,
            COUNT(CASE WHEN speaker_type = 1 THEN 1 END) as customer_messages,
            COUNT(CASE WHEN speaker_type = 2 THEN 1 END) as agent_messages
        FROM chat_messages 
        $whereClause
    ";
    
    $stmt = $db->prepare($sql);
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_assoc() ?: [];
}

// 删除聊天记录
if (isset($_GET['delete']) && $isAdmin) {
    $message_id = intval($_GET['delete']);
    $db = getDB();
    if ($db && checkTableExists($db, 'chat_messages')) {
        $stmt = $db->prepare("DELETE FROM chat_messages WHERE id = ?");
        $stmt->bind_param("i", $message_id);
        if ($stmt->execute()) {
            header("Location: " . strtok($_SERVER['REQUEST_URI'], '?') . "?success=聊天记录已删除");
            exit;
        }
    }
}

// 清除所有聊天记录
if (isset($_GET['clear_all']) && $isAdmin) {
    $db = getDB();
    if ($db && checkTableExists($db, 'chat_messages')) {
        $stmt = $db->prepare("DELETE FROM chat_messages");
        if ($stmt->execute()) {
            header("Location: " . strtok($_SERVER['REQUEST_URI'], '?') . "?success=所有聊天记录已清空");
            exit;
        }
    }
}

// 获取数据
$total_messages = getChatsCount($isAdmin, $current_username, $search_session, $search_speaker, $search_customer, $search_content, $search_agent, $search_date);
$total_pages = ceil($total_messages / $limit);
$messages = getChatMessages($isAdmin, $current_username, $search_session, $search_speaker, $search_customer, $search_content, $search_agent, $search_date, $limit, $offset);
$stats = getChatStats($isAdmin, $current_username);

// 获取唯一的会话列表
function getSessionsList($isAdmin, $current_username) {
    $db = getDB();
    if (!$db) return [];
    
    if (!checkTableExists($db, 'chat_messages')) {
        return [];
    }
    
    $whereClause = $isAdmin ? "WHERE 1=1" : "WHERE agent_account = ?";
    $params = $isAdmin ? [] : [$current_username];
    $types = $isAdmin ? '' : 's';
    
    $sql = "SELECT DISTINCT session_key FROM chat_messages $whereClause ORDER BY session_key LIMIT 50";
    
    if ($isAdmin) {
        $result = $db->query($sql);
    } else {
        $stmt = $db->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
    }
    
    $sessions = [];
    while ($row = $result->fetch_assoc()) {
        $sessions[] = $row['session_key'];
    }
    
    return $sessions;
}

$sessions = getSessionsList($isAdmin, $current_username);

// 获取唯一的客户列表
function getCustomersList($isAdmin, $current_username) {
    $db = getDB();
    if (!$db) return [];
    
    if (!checkTableExists($db, 'chat_messages')) {
        return [];
    }
    
    $whereClause = $isAdmin ? "WHERE 1=1" : "WHERE agent_account = ?";
    $params = $isAdmin ? [] : [$current_username];
    $types = $isAdmin ? '' : 's';
    
    $sql = "SELECT DISTINCT customer_name FROM chat_messages $whereClause AND customer_name IS NOT NULL AND customer_name != '' ORDER BY customer_name LIMIT 50";
    
    if ($isAdmin) {
        $result = $db->query($sql);
    } else {
        $stmt = $db->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
    }
    
    $customers = [];
    while ($row = $result->fetch_assoc()) {
        $customers[] = $row['customer_name'];
    }
    
    return $customers;
}

$customers = getCustomersList($isAdmin, $current_username);

// 消息提示
$success_msg = isset($_GET['success']) ? htmlspecialchars($_GET['success']) : '';
$error_msg = isset($_GET['error']) ? htmlspecialchars($_GET['error']) : '';
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>聊天记录查看 - 管理面板</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Noto+Sans+SC:wght@400;500;700&display=swap">
    <style>
        /* 完全一致的CSS样式 */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        :root {
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-400: #9ca3af;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --gray-900: #111827;
            --zinc-800: #27272a;
            --blue-500: #3b82f6;
            --blue-600: #2563eb;
            --red-500: #ef4444;
            --red-600: #dc2626;
            --green-500: #10b981;
            --green-600: #059669;
            --yellow-500: #f59e0b;
            --yellow-600: #d97706;
            --purple-500: #8b5cf6;
            --purple-600: #7c3aed;
            --black: #18181b;
            --white: #ffffff;
        }
        
        body {
            font-family: 'Noto Sans SC', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, 'Noto Sans', sans-serif;
            background-color: var(--gray-50);
            min-height: 100vh;
            line-height: 1.5;
        }
        
        .container {
            max-width: 64rem;
            margin: 0 auto;
            min-height: 100vh;
            background-color: var(--gray-50);
        }
        
        /* Header - 完全一致 */
        .header {
            position: sticky;
            top: 0;
            z-index: 10;
            background-color: var(--white);
            padding: 1rem;
            border-bottom: 1px solid var(--gray-200);
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        }
        
        .header-content {
            display: flex;
            align-items: center;
        }
        
        .back-link {
            display: flex;
            align-items: center;
            color: var(--blue-500);
            text-decoration: none;
            font-weight: 500;
            font-size: 1rem;
        }
        
        .back-link:hover {
            color: var(--blue-600);
        }
        
        .back-icon {
            width: 1.25em;
            height: 1.25em;
        }
        
        .back-text {
            margin-left: 0.25rem;
        }
        
        .title {
            margin-left:1.5rem;
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--gray-800);
        }
        
        .header-actions {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        /* Main Content - 完全一致 */
        .main-content {
            padding: 1rem;
        }
        
        /* Cards - 完全一致 */
        .card {
            background-color: var(--white);
            border-radius: 0.5rem;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px -1px rgba(0, 0, 0, 0.1);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .card-title {
            font-size: 1rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 1.5rem;
        }
        
        /* 统计信息网格 - 每行2个，分2行 */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin-bottom: 1.5rem;
        }
        
        /* 统计卡片样式 */
        .stat-card {
            padding: 1.5rem;
            border-radius: 0.5rem;
            background: var(--white);
            border: 1px solid var(--gray-200);
            transition: all 0.2s;
        }
        
        .stat-card:hover {
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        
        .stat-icon {
            width: 3rem;
            height: 3rem;
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
            font-size: 1.5rem;
        }
        
        .stat-card:nth-child(1) .stat-icon { background-color: rgba(139, 92, 246, 0.1); color: var(--purple-500); }
        .stat-card:nth-child(2) .stat-icon { background-color: rgba(245, 158, 11, 0.1); color: var(--yellow-500); }
        .stat-card:nth-child(3) .stat-icon { background-color: rgba(59, 130, 246, 0.1); color: var(--blue-500); }
        .stat-card:nth-child(4) .stat-icon { background-color: rgba(16, 185, 129, 0.1); color: var(--green-500); }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 0.25rem;
        }
        
        .stat-label {
            font-size: 0.875rem;
            color: var(--gray-600);
            margin-bottom: 0.5rem;
        }
        
        .stat-trend {
            font-size: 0.75rem;
            color: var(--gray-500);
        }
        
        /* 搜索表单 */
        .search-form {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1rem;
        }
        
        .search-field {
            display: flex;
            flex-direction: column;
        }
        
        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--gray-600);
            margin-bottom: 0.5rem;
        }
        
        .input {
            width: 100%;
            border: 1px solid var(--gray-300);
            border-radius: 0.375rem;
            padding: 0.5rem 0.75rem;
            color: var(--gray-900);
            font-size: 1rem;
        }
        
        .input:focus {
            outline: none;
            border-color: var(--blue-500);
        }
        
        .select {
            width: 100%;
            appearance: none;
            background-color: var(--white);
            border: 1px solid var(--gray-300);
            border-radius: 0.375rem;
            padding: 0.5rem 0.75rem;
            color: var(--gray-900);
            line-height: 1.25;
            font-size: 1rem;
        }
        
        .select:focus {
            outline: none;
            border-color: var(--blue-500);
        }
        
        .search-actions {
            display: flex;
            gap: 0.75rem;
            margin-top: 0.5rem;
        }
        
        /* 按钮 - 完全一致 */
        .btn {
            background-color: var(--black);
            color: var(--white);
            font-weight: 500;
            padding: 0.5rem 1.25rem;
            border-radius: 0.375rem;
            border: none;
            cursor: pointer;
            font-size: 0.875rem;
            transition: background-color 0.15s, color 0.15s, border-color 0.15s, fill 0.15s, stroke 0.15s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn:hover {
            background-color: var(--zinc-800);
        }
        
        .btn-primary {
            background-color: var(--black);
            color: var(--white);
        }
        
        .btn-primary:hover {
            background-color: var(--zinc-800);
        }
        
        .btn-danger {
            background-color: var(--red-500);
        }
        
        .btn-danger:hover {
            background-color: var(--red-600);
        }
        
        .btn-outline {
            background-color: transparent;
            color: var(--gray-700);
            border: 1px solid var(--gray-300);
        }
        
        .btn-outline:hover {
            background-color: var(--gray-100);
        }
        
        .btn-sm {
            padding: 0.375rem 0.75rem;
            font-size: 0.75rem;
        }
        
        .btn-icon {
            width: 1em;
            height: 1em;
        }
        
        /* 筛选标签 */
        .filter-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
        }
        
        .filter-tag {
            display: inline-flex;
            align-items: center;
            background-color: var(--gray-100);
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
            font-size: 0.875rem;
            color: var(--gray-700);
        }
        
        .filter-tag-close {
            background: none;
            border: none;
            color: var(--gray-500);
            cursor: pointer;
            font-size: 1.25rem;
            line-height: 1;
            margin-left: 0.5rem;
        }
        
        .filter-tag-close:hover {
            color: var(--gray-700);
        }
        
        /* 管理操作 */
        .admin-actions {
            background-color: var(--red-50);
            border: 1px solid var(--red-200);
            border-radius: 0.375rem;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }
        
        /* 表格容器 */
        .table-container {
            width: 100%;
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px; /* 确保在小屏幕上有滚动条 */
        }
        
        th {
            background-color: var(--gray-50);
            padding: 0.75rem 1rem;
            text-align: left;
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--gray-600);
            text-transform: uppercase;
            border-bottom: 1px solid var(--gray-200);
        }
        
        td {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid var(--gray-200);
            color: var(--gray-700);
        }
        
        tr:hover {
            background-color: var(--gray-50);
        }
        
        /* 徽章样式 */
        .badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 0.375rem;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .badge-customer {
            background-color: var(--blue-100);
            color: var(--blue-700);
        }
        
        .badge-agent {
            background-color: var(--green-100);
            color: var(--green-700);
        }
        
        .badge-image {
            background-color: var(--purple-100);
            color: var(--purple-700);
        }
        
        .badge-text {
            background-color: var(--gray-100);
            color: var(--gray-700);
        }
        
        /* 消息预览 */
        .message-preview {
            max-width: 300px;
            word-break: break-word;
        }
        
        .message-image {
            max-width: 100px;
            max-height: 100px;
            border-radius: 0.375rem;
            cursor: pointer;
        }
        
        /* 分页 */
        .pagination {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 1.5rem;
            padding: 1rem 0;
            border-top: 1px solid var(--gray-200);
        }
        
        .page-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 2rem;
            height: 2rem;
            padding: 0 0.5rem;
            border: 1px solid var(--gray-300);
            border-radius: 0.375rem;
            font-size: 0.875rem;
            color: var(--gray-700);
            text-decoration: none;
            transition: all 0.2s;
        }
        
        .page-link:hover {
            background-color: var(--gray-100);
        }
        
        .page-link.active {
            background-color: var(--black);
            color: var(--white);
            border-color: var(--black);
        }
        
        /* 空状态 */
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--gray-500);
        }
        
        /* 消息提示 */
        .message {
            padding: 1rem;
            border-radius: 0.375rem;
            margin-bottom: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .message.success {
            background-color: var(--green-100);
            color: var(--green-700);
            border: 1px solid var(--green-200);
        }
        
        .message.error {
            background-color: var(--red-100);
            color: var(--red-700);
            border: 1px solid var(--red-200);
        }
        
        /* 模态框 */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        
        .detail-modal-content {
            background: var(--white);
            border-radius: 0.5rem;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-body {
            padding: 1.5rem;
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--gray-500);
            cursor: pointer;
        }
        
        .modal-close:hover {
            color: var(--gray-700);
        }
        
        /* 详情部分 */
        .detail-section {
            margin-bottom: 1rem;
        }
        
        .detail-label {
            font-size: 0.875rem;
            color: var(--gray-600);
            margin-bottom: 0.25rem;
        }
        
        .detail-value {
            font-size: 1rem;
            color: var(--gray-900);
        }
        
        /* 图片预览模态框 */
        .image-preview {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.8);
            z-index: 1001;
            justify-content: center;
            align-items: center;
        }
        
        .image-preview img {
            max-width: 90%;
            max-height: 90%;
        }
        
        .close-preview {
            position: absolute;
            top: 1rem;
            right: 1rem;
            color: var(--white);
            font-size: 2rem;
            cursor: pointer;
        }

        /* Helper classes */
        .mb-4 {
            margin-bottom: 1rem;
        }
        
        .mb-6 {
            margin-bottom: 1.5rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <header class="header">
            <div class="header-content">
                <a href="javascript:void(0)" onclick="window.parent.postMessage('closeModal', '*')" class="back-link">
                    <svg class="back-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M17 10a.75.75 0 0 1-.75.75H5.612l4.158 3.96a.75.75 0 1 1-1.04 1.08l-5.5-5.25a.75.75 0 0 1 0-1.08l5.5-5.25a.75.75 0 1 1 1.04 1.08L5.612 9.25H16.25A.75.75 0 0 1 17 10" clip-rule="evenodd"></path>
                    </svg>
                    <span class="back-text">返回</span>
                </a>
                <h1 class="title">聊天记录</h1>
                <div class="header-actions"></div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="main-content">
            <!-- 消息提示 -->
            <?php if ($success_msg): ?>
                <div class="message success">
                    <span><?php echo $success_msg; ?></span>
                    <button onclick="this.parentElement.style.display='none'">×</button>
                </div>
            <?php endif; ?>
            
            <?php if ($error_msg): ?>
                <div class="message error">
                    <span><?php echo $error_msg; ?></span>
                    <button onclick="this.parentElement.style.display='none'">×</button>
                </div>
            <?php endif; ?>

            <!-- 统计信息 -->
            <div class="card">
                <h2 class="card-title">统计概览</h2>
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon"><svg t="1778623147696" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="13710" width="24" height="24"><path d="M682.7 554.7c23.6 0 42.7-19.1 42.7-42.7s-19.1-42.7-42.7-42.7S640 488.4 640 512s19.1 42.7 42.7 42.7z m-170.7 0c23.6 0 42.7-19.1 42.7-42.7s-19.1-42.7-42.7-42.7-42.7 19.1-42.7 42.7 19.1 42.7 42.7 42.7z m-170.7 0c23.6 0 42.7-19.1 42.7-42.7s-19.1-42.7-42.7-42.7-42.7 19.1-42.7 42.7 19.2 42.7 42.7 42.7zM104 512.5v-5.6c0-175.4 142.2-317.6 317.6-317.6h180.8c175.4 0 317.6 142.2 317.6 317.6v5.6c0 168.9-134.3 306.5-301.9 311.8L528.9 913c-9.4 9.3-24.5 9.3-33.9 0l-89.1-88.7C238.3 819 104 681.5 104 512.5z m487.1 271c4.5-4.5 10.6-7 16.9-7 145.8 0 264-118.2 264-264v-5.6c0-148.9-120.7-269.6-269.6-269.6H421.6C272.7 237.3 152 358 152 506.9v5.6c0 145.8 118.2 264 264 264 6.3 0 12.4 2.5 16.9 7l79.1 78.7 79.1-78.7z" fill="#2c2c2c" p-id="13711"></path></svg></div>
                        <div class="stat-value"><?php echo $stats['total_messages'] ?? 0; ?></div>
                        <div class="stat-label">总消息数</div>
                        <div class="stat-trend"><?php echo $stats['total_sessions'] ?? 0; ?> 个会话</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon"><svg t="1778623176031" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="14894" width="24" height="24"><path d="M512 504.832a194.3552 194.3552 0 1 0 0-388.7104 194.3552 194.3552 0 0 0 0 388.7104zM614.4 549.4784H409.6a307.2 307.2 0 0 0-307.2 307.2 51.2 51.2 0 0 0 51.2 51.2h716.8a51.2 51.2 0 0 0 51.2-51.2 307.2 307.2 0 0 0-307.2-307.2z" fill="#070707" p-id="14895"></path></svg></div>
                        <div class="stat-value"><?php echo $stats['total_customers'] ?? 0; ?></div>
                        <div class="stat-label">客户数量</div>
                        <div class="stat-trend">今日消息 <?php echo $stats['today_messages'] ?? 0; ?> 条</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon"><svg t="1778623196794" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="16012" width="24" height="24"><path d="M511.219 58.483l-227.213 227.313h-161.038c-41.846 0-75.781 33.935-75.781 75.781v303.097c0 41.882 33.935 75.782 75.781 75.782h161.037l227.213 227.377c41.845 0 75.748-33.934 75.748-75.78v-757.789c0.001-41.846-33.901-75.781-75.746-75.781zM511.219 889.427l-198.83-219.372h-189.421v-303.093h189.421l198.83-219.402v741.868z" fill="#2c2c2c" p-id="16013"></path><path d="M960.31 546.395c7.483 0 13.534-14.892 13.534-33.239s-6.051-33.239-13.534-33.239h-94.755c-7.482 0-13.533 14.889-13.533 33.239 0 18.347 6.051 33.239 13.533 33.239h94.755z" fill="#2c2c2c" p-id="16014"></path><path d="M970.582 116.844c6.484-3.743 4.277-19.663-4.897-35.552s-21.858-25.759-28.341-22.017l-82.059 47.376c-6.483 3.743-4.277 19.663 4.897 35.552s21.859 25.758 28.342 22.016l82.058-47.375z" fill="#2c2c2c" p-id="16015"></path><path d="M970.582 909.47c6.484 3.743 4.277 19.662-4.897 35.553-9.174 15.888-21.858 25.759-28.341 22.016l-82.059-47.376c-6.483-3.743-4.277-19.662 4.897-35.552 9.174-15.889 21.859-25.758 28.342-22.016l82.058 47.375z" fill="#2c2c2c" p-id="16016"></path><path d="M640.286 683.412c-6.756-18.186-4.889-35.436 13.299-42.194 45.93-21.204 67.853-62.464 73.459-114.215 6.541-60.379-22.355-115.869-72.24-138.725-16.317-10.496-23.073-28.677-12.579-44.995 10.497-16.316 28.683-23.073 44.995-12.579 73.893 42.911 118.17 117.522 108.828 203.777-7.477 69.002-49.452 134.269-113.569 162.23-14.983 8.481-35.070 2.914-42.193-13.294z" fill="#2c2c2c" p-id="16017"></path></svg></div>
                        <div class="stat-value"><?php echo $stats['customer_messages'] ?? 0; ?></div>
                        <div class="stat-label">客户消息</div>
                        <div class="stat-trend">客服消息 <?php echo $stats['agent_messages'] ?? 0; ?> 条</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon"><svg t="1778623212018" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="17266" width="24" height="24"><path d="M794.102 819.99C759.68 852.247 713.397 872 662.5 872 556.185 872 470 785.815 470 679.5S556.185 487 662.5 487 855 573.185 855 679.5c0 23.663-4.27 46.328-12.08 67.268l99.814 67.842c20.098 13.66 25.316 41.026 11.656 61.124-13.66 20.098-41.026 25.316-61.124 11.656l-99.164-67.4zM84 147c-24.3 0-44-19.7-44-44s19.7-44 44-44h834c24.3 0 44 19.7 44 44s-19.7 44-44 44H84z m0 498.5c-24.3 0-44-19.7-44-44s19.7-44 44-44h251c24.3 0 44 19.7 44 44s-19.7 44-44 44H84z m0-249c-24.3 0-44-19.7-44-44s19.7-44 44-44h834c24.3 0 44 19.7 44 44s-19.7 44-44 44H84z m0 499c-24.3 0-44-19.7-44-44s19.7-44 44-44h251c24.3 0 44 19.7 44 44s-19.7 44-44 44H84zM662.5 784c57.714 0 104.5-46.786 104.5-104.5S720.214 575 662.5 575 558 621.786 558 679.5 604.786 784 662.5 784z" fill="#2c2c2c" p-id="17267"></path></svg></div>
                        <div class="stat-value"><?php echo $total_messages; ?></div>
                        <div class="stat-label">当前筛选</div>
                        <div class="stat-trend">共 <?php echo $total_pages; ?> 页</div>
                    </div>
                </div>
            </div>

            <!-- 高级搜索 -->
            <div class="card">
                <h2 class="card-title">高级搜索</h2>
                <form method="GET" class="search-form">
                    <div class="search-field">
                        <label class="form-label">会话ID</label>
                        <input type="text" 
                               name="session_key" 
                               class="input" 
                               placeholder="输入会话ID"
                               value="<?php echo htmlspecialchars($search_session); ?>"
                               list="sessionsList">
                        <datalist id="sessionsList">
                            <?php foreach ($sessions as $session): ?>
                                <option value="<?php echo htmlspecialchars($session); ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    
                    <div class="search-field">
                        <label class="form-label">发言者</label>
                        <select name="speaker_type" class="select">
                            <option value="all">全部发言者</option>
                            <option value="1" <?php echo $search_speaker == '1' ? 'selected' : ''; ?>>客户</option>
                            <option value="2" <?php echo $search_speaker == '2' ? 'selected' : ''; ?>>客服</option>
                        </select>
                    </div>
                    
                    <div class="search-field">
                        <label class="form-label">客户名称</label>
                        <input type="text" 
                               name="customer_name" 
                               class="input" 
                               placeholder="输入客户名称"
                               value="<?php echo htmlspecialchars($search_customer); ?>"
                               list="customersList">
                        <datalist id="customersList">
                            <?php foreach ($customers as $customer): ?>
                                <option value="<?php echo htmlspecialchars($customer); ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    
                    <div class="search-field">
                        <label class="form-label">消息内容</label>
                        <input type="text" 
                               name="content" 
                               class="input" 
                               placeholder="输入消息内容"
                               value="<?php echo htmlspecialchars($search_content); ?>">
                    </div>
                    
                    <div class="search-field">
                        <label class="form-label">客服账号</label>
                        <input type="text" 
                               name="agent_account" 
                               class="input" 
                               placeholder="输入客服账号"
                               value="<?php echo htmlspecialchars($search_agent); ?>">
                    </div>
                    
                    <div class="search-field">
                        <label class="form-label">日期筛选</label>
                        <input type="date" 
                               name="date" 
                               class="input" 
                               value="<?php echo htmlspecialchars($search_date); ?>">
                    </div>
                    
                    <div class="search-actions">
                        <button type="submit" class="btn btn-primary">
                            <svg class="btn-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11ZM2 9a7 7 0 1 1 12.452 4.391l3.328 3.329a.75.75 0 1 1-1.06 1.06l-3.329-3.328A7 7 0 0 1 2 9Z" clip-rule="evenodd" />
                            </svg>
                            搜索
                        </button>
                        <?php if ($search_session || $search_speaker || $search_customer || $search_content || $search_agent || $search_date): ?>
                        <a href="<?php echo strtok($_SERVER['REQUEST_URI'], '?'); ?>" class="btn btn-outline">清除筛选</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- 筛选标签 -->
            <?php if ($search_session || $search_speaker != 'all' || $search_customer || $search_content || $search_agent || $search_date): ?>
                <div class="filter-tags">
                    <span>当前筛选：</span>
                    <?php if ($search_session): ?>
                        <div class="filter-tag">
                            会话ID包含"<?php echo htmlspecialchars($search_session); ?>"
                            <button class="filter-tag-close" onclick="removeFilter('session_key')">&times;</button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($search_speaker != 'all'): ?>
                        <div class="filter-tag">
                            发言者：<?php echo $search_speaker == '1' ? '客户' : '客服'; ?>
                            <button class="filter-tag-close" onclick="removeFilter('speaker_type')">&times;</button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($search_customer): ?>
                        <div class="filter-tag">
                            客户名称包含"<?php echo htmlspecialchars($search_customer); ?>"
                            <button class="filter-tag-close" onclick="removeFilter('customer_name')">&times;</button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($search_content): ?>
                        <div class="filter-tag">
                            消息内容包含"<?php echo htmlspecialchars($search_content); ?>"
                            <button class="filter-tag-close" onclick="removeFilter('content')">&times;</button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($search_agent): ?>
                        <div class="filter-tag">
                            客服账号包含"<?php echo htmlspecialchars($search_agent); ?>"
                            <button class="filter-tag-close" onclick="removeFilter('agent_account')">&times;</button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($search_date): ?>
                        <div class="filter-tag">
                            日期：<?php echo htmlspecialchars($search_date); ?>
                            <button class="filter-tag-close" onclick="removeFilter('date')">&times;</button>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- 管理操作（仅管理员） -->
            <?php if ($isAdmin && $total_messages > 0): ?>
                <div class="admin-actions">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <strong>管理员操作</strong>
                         
                        </div>
                        <div>
                            <button class="btn btn-danger" onclick="if(confirm('确定要清空所有聊天记录吗？此操作不可恢复！')) location.href='?clear_all=1'">
                                <svg class="btn-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 0 0 6 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 1 0 .23 1.482l.149-.022.841 10.518A2.75 2.75 0 0 0 7.596 19h4.807a2.75 2.75 0 0 0 2.742-2.53l.841-10.52.149.023a.75.75 0 0 0 .23-1.482A41.03 41.03 0 0 0 14 4.193V3.75A2.75 2.75 0 0 0 11.25 1h-2.5ZM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4ZM8.58 7.72a.75.75 0 0 0-1.5.06l.3 7.5a.75.75 0 1 0 1.5-.06l-.3-7.5Zm4.34.06a.75.75 0 1 0-1.5-.06l-.3 7.5a.75.75 0 1 0 1.5.06l.3-7.5Z" clip-rule="evenodd" />
                                </svg>
                                清空所有记录
                            </button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- 聊天记录表格 -->
            <div class="card">
                <h2 class="card-title">聊天记录列表</h2>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>会话ID</th>
                                <th>客服账号</th>
                                <th>发言者</th>
                                <th>消息内容</th>
                                <th>客户名称</th>
                                <th>时间</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($messages)): ?>
                                <?php foreach($messages as $msg): ?>
                                <tr>
                                    <td>#<?php echo $msg['id']; ?></td>
                                    <td>
                                        <span><?php echo htmlspecialchars($msg['session_key']); ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($msg['agent_account']); ?></td>
                                    <td>
                                        <?php if ($msg['speaker_type'] == 1): ?>
                                            <span class="badge badge-customer">客户</span>
                                        <?php else: ?>
                                            <span class="badge badge-agent">客服</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="message-preview">
                                            <?php if ($msg['message_type'] == 'image' && $msg['image_url']): ?>
                                                <span class="badge badge-image">图片</span>
                                                <?php if ($msg['image_name']): ?>
                                                    <div><?php echo htmlspecialchars($msg['image_name']); ?></div>
                                                <?php endif; ?>
                                                <div style="margin-top: 0.5rem;">
                                                    <img src="<?php echo htmlspecialchars($msg['image_url']); ?>" 
                                                         alt="聊天图片" 
                                                         class="message-image"
                                                         onclick="previewImage('<?php echo htmlspecialchars($msg['image_url']); ?>')"
                                                         onerror="this.style.display='none'">
                                                </div>
                                            <?php else: ?>
                                                <span class="badge badge-text">文本</span>
                                                <div style="margin-top: 0.5rem;">
                                                    <?php echo htmlspecialchars(mb_strlen($msg['content']) > 50 ? mb_substr($msg['content'], 0, 50) . '...' : $msg['content']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($msg['customer_name'] ?? '-'); ?></td>
                                    <td><?php echo date('Y-m-d H:i', strtotime($msg['created_at'])); ?></td>
                                    <td>
                                        <div>
                                            <button class="btn btn-outline btn-sm" onclick="showMessageDetail(<?php echo $msg['id']; ?>)">详情</button>
                                            
                                            <?php if ($isAdmin): ?>
                                                <button class="btn btn-danger btn-sm" 
                                                        onclick="confirmDelete(<?php echo $msg['id']; ?>)">删除</button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="empty-state">
                                        <?php echo ($search_session || $search_speaker || $search_customer || $search_content || $search_agent || $search_date) ? '没有找到匹配的聊天记录' : '暂无聊天记录'; ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- 分页 -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <div>
                    显示第 <?php echo $offset + 1; ?> - <?php echo min($offset + $limit, $total_messages); ?> 条，共 <?php echo $total_messages; ?> 条记录
                </div>
                
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <?php 
                    $query_string = '';
                    if ($search_session) $query_string .= "&session_key=" . urlencode($search_session);
                    if ($search_speaker) $query_string .= "&speaker_type=" . urlencode($search_speaker);
                    if ($search_customer) $query_string .= "&customer_name=" . urlencode($search_customer);
                    if ($search_content) $query_string .= "&content=" . urlencode($search_content);
                    if ($search_agent) $query_string .= "&agent_account=" . urlencode($search_agent);
                    if ($search_date) $query_string .= "&date=" . urlencode($search_date);
                    ?>
                    
                    <?php if ($page > 1): ?>
                    <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo $query_string; ?>">‹</a>
                    <?php endif; ?>
                    
                    <?php
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    
                    for ($i = $start_page; $i <= $end_page; $i++) {
                        if ($i == $page) {
                            echo '<a class="page-link active" href="?page=' . $i . $query_string . '">' . $i . '</a>';
                        } else {
                            echo '<a class="page-link" href="?page=' . $i . $query_string . '">' . $i . '</a>';
                        }
                    }
                    ?>
                    
                    <?php if ($page < $total_pages): ?>
                    <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo $query_string; ?>">›</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- 图片预览模态框 -->
    <div id="imagePreview" class="image-preview">
        <span class="close-preview" onclick="closeImagePreview()">&times;</span>
        <img id="previewImage" src="" alt="预览">
    </div>

    <!-- 聊天详情模态框 -->
    <div id="detailModal" class="modal">
        <div class="detail-modal-content">
            <div class="modal-header">
                <h3>聊天详情</h3>
                <button class="modal-close" onclick="closeDetailModal()">×</button>
            </div>
            <div class="modal-body">
                <div id="detailContent">
                    <!-- 详情内容将通过JavaScript加载 -->
                </div>
            </div>
        </div>
    </div>

    <script>
        // 移除筛选条件
        function removeFilter(filterName) {
            const url = new URL(window.location.href);
            url.searchParams.delete(filterName);
            url.searchParams.set('page', 1);
            window.location.href = url.toString();
        }

        // 确认删除记录
        function confirmDelete(messageId) {
            if (confirm('确定要删除这条聊天记录吗？此操作不可恢复！')) {
                location.href = '?delete=' + messageId;
            }
        }

        // 显示消息详情
        async function showMessageDetail(messageId) {
            try {
                const response = await fetch(`/config/get_message_detail.php?id=${messageId}`);
                const data = await response.json();
                
                if (data.success) {
                    const msg = data.data;
                    let html = `
                        <div class="detail-section">
                            <div class="detail-label">消息ID</div>
                            <div class="detail-value">#${msg.id}</div>
                        </div>
                        
                        <div class="detail-section">
                            <div class="detail-label">会话ID</div>
                            <div class="detail-value">${msg.session_key}</div>
                        </div>
                        
                        <div class="detail-section">
                            <div class="detail-label">客服账号</div>
                            <div class="detail-value">${msg.agent_account}</div>
                        </div>
                        
                        <div class="detail-section">
                            <div class="detail-label">发言者类型</div>
                            <div class="detail-value">
                                <span class="${msg.speaker_type == 1 ? 'badge badge-customer' : 'badge badge-agent'}">
                                    ${msg.speaker_type == 1 ? '客户' : '客服'} (${msg.speaker_type})
                                </span>
                            </div>
                        </div>
                        
                        <div class="detail-section">
                            <div class="detail-label">消息内容</div>
                            <div class="detail-value">${msg.content || '(无内容)'}</div>
                        </div>
                        
                        <div class="detail-section">
                            <div class="detail-label">图片URL</div>
                            <div class="detail-value">${msg.image_url || '(无图片)'}</div>
                        </div>
                        
                        <div class="detail-section">
                            <div class="detail-label">客户名称</div>
                            <div class="detail-value">${msg.customer_name || '(未设置)'}</div>
                        </div>
                        
                        <div class="detail-section">
                            <div class="detail-label">备注</div>
                            <div class="detail-value">${msg.remark || '(无备注)'}</div>
                        </div>
                        
                        <div class="detail-section">
                            <div class="detail-label">创建时间</div>
                            <div class="detail-value">${new Date(msg.created_at).toLocaleString()}</div>
                        </div>
                    `;
                    
                    document.getElementById('detailContent').innerHTML = html;
                    document.getElementById('detailModal').style.display = 'flex';
                } else {
                    // 如果API不存在，显示静态详情
                    showStaticMessageDetail(messageId);
                }
            } catch (error) {
                console.error('获取消息详情失败:', error);
                // 如果API不存在，显示静态详情
                showStaticMessageDetail(messageId);
            }
        }

        // 显示静态消息详情（当API不存在时）
        function showStaticMessageDetail(messageId) {
            const message = <?php echo json_encode($messages); ?>.find(m => m.id == messageId);
            if (message) {
                let html = `
                    <div class="detail-section">
                        <div class="detail-label">消息ID</div>
                        <div class="detail-value">#${message.id}</div>
                    </div>
                    
                    <div class="detail-section">
                        <div class="detail-label">会话ID</div>
                        <div class="detail-value">${message.session_key}</div>
                    </div>
                    
                    <div class="detail-section">
                        <div class="detail-label">客服账号</div>
                        <div class="detail-value">${message.agent_account}</div>
                    </div>
                    
                    <div class="detail-section">
                        <div class="detail-label">发言者类型</div>
                        <div class="detail-value">
                            <span class="${message.speaker_type == 1 ? 'badge badge-customer' : 'badge badge-agent'}">
                                ${message.speaker_type == 1 ? '客户' : '客服'} (${message.speaker_type})
                            </span>
                        </div>
                    </div>
                    
                    <div class="detail-section">
                        <div class="detail-label">消息内容</div>
                        <div class="detail-value">${message.content || '(无内容)'}</div>
                    </div>
                    
                    <div class="detail-section">
                        <div class="detail-label">图片URL</div>
                        <div class="detail-value">${message.image_url || '(无图片)'}</div>
                    </div>
                    
                    <div class="detail-section">
                        <div class="detail-label">客户名称</div>
                        <div class="detail-value">${message.customer_name || '(未设置)'}</div>
                    </div>
                    
                    <div class="detail-section">
                        <div class="detail-label">备注</div>
                        <div class="detail-value">${message.remark || '(无备注)'}</div>
                    </div>
                    
                    <div class="detail-section">
                        <div class="detail-label">创建时间</div>
                        <div class="detail-value">${new Date(message.created_at).toLocaleString()}</div>
                    </div>
                `;
                
                document.getElementById('detailContent').innerHTML = html;
                document.getElementById('detailModal').style.display = 'flex';
            }
        }

        // 关闭详情模态框
        function closeDetailModal() {
            document.getElementById('detailModal').style.display = 'none';
        }

        // 预览图片
        function previewImage(imageUrl) {
            if (!imageUrl) return;
            document.getElementById('previewImage').src = imageUrl;
            document.getElementById('imagePreview').style.display = 'flex';
        }

        // 关闭图片预览
        function closeImagePreview() {
            document.getElementById('imagePreview').style.display = 'none';
        }

        // 点击背景关闭模态框
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                closeDetailModal();
            }
            if (event.target.classList.contains('image-preview')) {
                closeImagePreview();
            }
        };

        // 页面加载动画
        document.addEventListener('DOMContentLoaded', function() {
            document.body.style.opacity = '0';
            setTimeout(() => {
                document.body.style.transition = 'opacity 0.3s';
                document.body.style.opacity = '1';
            }, 10);

            // 消息提示自动隐藏
            const messages = document.querySelectorAll('.message');
            messages.forEach(message => {
                setTimeout(() => {
                    message.style.opacity = '0';
                    message.style.transition = 'opacity 0.5s';
                    setTimeout(() => {
                        message.style.display = 'none';
                    }, 500);
                }, 5000);
            });
        });
    </script>
</body>
</html>