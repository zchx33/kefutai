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
$limit = 20;
$offset = ($page - 1) * $limit;

// 搜索参数
$search_username = isset($_GET['username']) ? trim($_GET['username']) : '';
$search_action = isset($_GET['action']) ? trim($_GET['action']) : '';
$search_date = isset($_GET['date']) ? trim($_GET['date']) : '';

// 检查表是否存在
function checkTableExists($db, $tableName) {
    $result = $db->query("SHOW TABLES LIKE '$tableName'");
    return $result && $result->num_rows > 0;
}

// 获取总记录数和总页数
function getLogsCount($isAdmin, $current_user_id, $search_username, $search_action, $search_date) {
    $db = getDB();
    if (!$db) return 0;
    
    if (!checkTableExists($db, 'user_logs')) {
        return 0;
    }
    
    $whereClause = "WHERE 1=1";
    $params = [];
    $types = '';
    
    if (!$isAdmin) {
        $whereClause .= " AND user_id = ?";
        $params[] = $current_user_id;
        $types .= 'i';
    }
    
    if (!empty($search_username)) {
        $whereClause .= " AND username LIKE ?";
        $params[] = "%$search_username%";
        $types .= 's';
    }
    
    if (!empty($search_action)) {
        $whereClause .= " AND action LIKE ?";
        $params[] = "%$search_action%";
        $types .= 's';
    }
    
    if (!empty($search_date)) {
        $whereClause .= " AND DATE(created_at) = ?";
        $params[] = $search_date;
        $types .= 's';
    }
    
    $sql = "SELECT COUNT(*) as total FROM user_logs $whereClause";
    $stmt = $db->prepare($sql);
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return $row['total'] ?? 0;
}

// 获取日志记录
function getPasswordLogs($isAdmin, $current_user_id, $search_username, $search_action, $search_date, $limit, $offset) {
    $db = getDB();
    if (!$db) return [];
    
    if (!checkTableExists($db, 'user_logs')) {
        return [];
    }
    
    $whereClause = "WHERE 1=1";
    $params = [];
    $types = '';
    
    if (!$isAdmin) {
        $whereClause .= " AND user_id = ?";
        $params[] = $current_user_id;
        $types .= 'i';
    }
    
    if (!empty($search_username)) {
        $whereClause .= " AND username LIKE ?";
        $params[] = "%$search_username%";
        $types .= 's';
    }
    
    if (!empty($search_action)) {
        $whereClause .= " AND action LIKE ?";
        $params[] = "%$search_action%";
        $types .= 's';
    }
    
    if (!empty($search_date)) {
        $whereClause .= " AND DATE(created_at) = ?";
        $params[] = $search_date;
        $types .= 's';
    }
    
    $sql = "SELECT * FROM user_logs $whereClause ORDER BY created_at DESC LIMIT ? OFFSET ?";
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
    $logs = [];
    
    while ($row = $result->fetch_assoc()) {
        $logs[] = $row;
    }
    
    return $logs;
}

// 获取统计信息
function getLogsStats($isAdmin, $current_user_id) {
    $db = getDB();
    if (!$db) return [];
    
    if (!checkTableExists($db, 'user_logs')) {
        return [
            'total_logs' => 0,
            'total_users' => 0,
            'today_logs' => 0,
            'yesterday_logs' => 0
        ];
    }
    
    $whereClause = $isAdmin ? "WHERE 1=1" : "WHERE user_id = ?";
    $params = $isAdmin ? [] : [$current_user_id];
    $types = $isAdmin ? '' : 'i';
    
    $sql = "
        SELECT 
            COUNT(*) as total_logs,
            COUNT(DISTINCT username) as total_users,
            COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as today_logs,
            COUNT(CASE WHEN DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY) THEN 1 END) as yesterday_logs
        FROM user_logs 
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

// 删除日志记录
if (isset($_GET['delete']) && $isAdmin) {
    $log_id = intval($_GET['delete']);
    $db = getDB();
    if ($db && checkTableExists($db, 'user_logs')) {
        $stmt = $db->prepare("DELETE FROM user_logs WHERE id = ?");
        $stmt->bind_param("i", $log_id);
        if ($stmt->execute()) {
            header("Location: " . $_SERVER['PHP_SELF'] . "?success=记录已删除");
            exit;
        }
    }
}

// 清除所有日志记录
if (isset($_GET['clear_all']) && $isAdmin) {
    $db = getDB();
    if ($db && checkTableExists($db, 'user_logs')) {
        $stmt = $db->prepare("DELETE FROM user_logs");
        if ($stmt->execute()) {
            header("Location: " . $_SERVER['PHP_SELF'] . "?success=所有日志记录已清空");
            exit;
        }
    }
}

// 获取数据
$total_logs = getLogsCount($isAdmin, $current_user_id, $search_username, $search_action, $search_date);
$total_pages = ceil($total_logs / $limit);
$logs = getPasswordLogs($isAdmin, $current_user_id, $search_username, $search_action, $search_date, $limit, $offset);
$stats = getLogsStats($isAdmin, $current_user_id);

// 消息提示
$success_msg = isset($_GET['success']) ? htmlspecialchars($_GET['success']) : '';
$error_msg = isset($_GET['error']) ? htmlspecialchars($_GET['error']) : '';
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>操作日志</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Noto+Sans+SC:wght@400;500;700&display=swap">
    <style>
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
            --black: #18181b;
            --white: #ffffff;
            --red-500: #ef4444;
            --green-500: #10b981;
            --orange-500: #f59e0b;
        }
        
        body {
            font-family: 'Noto Sans SC', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, 'Noto Sans', sans-serif;
            background-color: var(--gray-50);
            min-height: 100vh;
            line-height: 1.5;
        }
        
        .container {
            max-width: 42rem;
            margin: 0 auto;
            min-height: 100vh;
            background-color: var(--gray-50);
        }
        
        /* Header */
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
            margin-left: 1rem;
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--gray-800);
        }
        
        /* Main Content */
        .main-content {
            padding: 1rem;
        }
        
        /* Cards */
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
        
        /* Stats */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .stat-card {
            background-color: var(--white);
            border-radius: 0.5rem;
            padding: 1rem;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
        }
        
        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--gray-900);
        }
        
        .stat-label {
            font-size: 0.875rem;
            color: var(--gray-500);
            margin-top: 0.25rem;
        }
        
        /* Button */
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
            color: var(--white);
        }
        
        .btn-danger:hover {
            background-color: #dc2626;
        }
        
        .btn-outline {
            background-color: var(--gray-200);
            color: var(--gray-800);
        }
        
        .btn-outline:hover {
            background-color: var(--gray-300);
        }
        
        .btn-sm {
            padding: 0.25rem 0.75rem;
            font-size: 0.75rem;
        }
        
        /* Table */
        .table-container {
            overflow-x: auto;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.875rem;
        }
        
        .table th {
            background-color: var(--gray-50);
            text-align: left;
            padding: 0.75rem 1rem;
            font-weight: 600;
            color: var(--gray-600);
            border-bottom: 1px solid var(--gray-200);
        }
        
        .table td {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid var(--gray-200);
        }
        
        .table tr:hover {
            background-color: var(--gray-50);
        }
        
        /* Search Form */
        .search-form {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1rem;
        }
        
        .search-row {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .search-field {
            flex: 1;
            min-width: 150px;
        }
        
        .search-field.full-width {
            flex: 1 0 100%;
        }
        
        /* Form Elements */
        .form-group {
            margin-bottom: 1rem;
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
            font-size: 0.875rem;
        }
        
        .input:focus {
            outline: none;
            border-color: transparent;
            box-shadow: 0 0 0 2px var(--blue-500);
        }
        
        .input::placeholder {
            color: var(--gray-400);
        }
        
        /* Actions */
        .actions {
            display: flex;
            gap: 0.5rem;
        }
        
        /* Pagination */
        .pagination {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            margin-top: 1.5rem;
            padding-top: 1rem;
            border-top: 1px solid var(--gray-200);
        }
        
        .page-link {
            padding: 0.5rem 0.75rem;
            border: 1px solid var(--gray-300);
            border-radius: 0.375rem;
            text-decoration: none;
            color: var(--gray-700);
            font-size: 0.875rem;
        }
        
        .page-link:hover {
            background-color: var(--gray-100);
        }
        
        .page-link.active {
            background-color: var(--black);
            color: var(--white);
            border-color: var(--black);
        }
        
        /* Badge */
        .badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .badge-info {
            background-color: rgba(59, 130, 246, 0.1);
            color: var(--blue-500);
        }
        
        .badge-success {
            background-color: rgba(16, 185, 129, 0.1);
            color: var(--green-500);
        }
        
        .badge-warning {
            background-color: rgba(245, 158, 11, 0.1);
            color: var(--orange-500);
        }
        
        .badge-danger {
            background-color: rgba(239, 68, 68, 0.1);
            color: var(--red-500);
        }
        
        /* Message */
        .message {
            padding: 1rem;
            border-radius: 0.375rem;
            margin-bottom: 1rem;
            font-size: 0.875rem;
        }
        
        .message-success {
            background-color: rgba(16, 185, 129, 0.1);
            color: var(--green-500);
            border: 1px solid rgba(16, 185, 129, 0.2);
        }
        
        .message-error {
            background-color: rgba(239, 68, 68, 0.1);
            color: var(--red-500);
            border: 1px solid rgba(239, 68, 68, 0.2);
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 2rem 1rem;
            color: var(--gray-500);
        }
        
        /* Text Muted */
        .text-muted {
            color: var(--gray-500);
        }
        
        /* User Avatar */
        .user-avatar {
            width: 2rem;
            height: 2rem;
            border-radius: 50%;
            background-color: var(--blue-500);
            color: var(--white);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.875rem;
        }
        
        .user-cell {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        /* IP Address */
        .ip-address {
            font-family: monospace;
            font-weight: 500;
        }
        
        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: var(--white);
            border-radius: 0.5rem;
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-header {
            padding: 1.5rem 1.5rem 1rem;
            border-bottom: 1px solid var(--gray-200);
        }
        
        .modal-header h3 {
            font-size: 1rem;
            font-weight: 700;
            color: var(--gray-900);
        }
        
        .modal-body {
            padding: 1.5rem;
        }
        
        .modal-close {
            float: right;
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--gray-500);
        }
        
        /* Admin Actions */
        .admin-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding: 1rem;
            background-color: var(--white);
            border-radius: 0.5rem;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
        }
        
        /* Filter Tags */
        .filter-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        
        .filter-tag {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.375rem 0.75rem;
            background-color: var(--gray-100);
            color: var(--gray-700);
            border-radius: 0.25rem;
            font-size: 0.75rem;
        }
        
        .filter-tag-close {
            background: none;
            border: none;
            color: var(--gray-500);
            cursor: pointer;
            font-size: 1rem;
            line-height: 1;
        }
        
        /* Responsive */
        @media (min-width: 640px) {
            .stats-grid {
                grid-template-columns: repeat(4, 1fr);
            }
            
            .search-form {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .search-row {
                grid-column: span 2;
            }
            
            .search-field.full-width {
                flex: 1;
            }
        }
        
        /* Helper classes */
        .mt-2 {
            margin-top: 0.5rem;
        }
        
        .mt-4 {
            margin-top: 1rem;
        }
        
        .mb-4 {
            margin-bottom: 1rem;
        }
        
        .mb-6 {
            margin-bottom: 1.5rem;
        }
        
        .w-full {
            width: 100%;
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
                <h1 class="title">系统日志</h1>
            </div>
        </header>

        <!-- Main Content -->
        <main class="main-content">
            <!-- 消息提示 -->
            <?php if ($success_msg): ?>
                <div class="message message-success">
                    <?php echo htmlspecialchars($success_msg); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error_msg): ?>
                <div class="message message-error">
                    <?php echo htmlspecialchars($error_msg); ?>
                </div>
            <?php endif; ?>
            
            <!-- 统计信息 -->
            <section class="card">
                <h2 class="card-title">统计信息</h2>
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $stats['total_logs'] ?? 0; ?></div>
                        <div class="stat-label">总记录数</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $stats['today_logs'] ?? 0; ?></div>
                        <div class="stat-label">今日记录</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $stats['total_users'] ?? 0; ?></div>
                        <div class="stat-label">用户数</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $stats['yesterday_logs'] ?? 0; ?></div>
                        <div class="stat-label">昨日记录</div>
                    </div>
                </div>
            </section>
            
            <!-- 高级搜索 -->
            <section class="card">
                <h2 class="card-title">高级搜索</h2>
                <form method="GET" class="search-form">
                    <div class="search-field">
                        <label class="form-label">用户名</label>
                        <input type="text" 
                               name="username" 
                               class="input" 
                               placeholder="输入用户名搜索"
                               value="<?php echo htmlspecialchars($search_username); ?>">
                    </div>
                    
                    <div class="search-field">
                        <label class="form-label">操作类型</label>
                        <input type="text" 
                               name="action" 
                               class="input" 
                               placeholder="输入操作类型"
                               value="<?php echo htmlspecialchars($search_action); ?>">
                    </div>
                    
                    <div class="search-field">
                        <label class="form-label">日期筛选</label>
                        <input type="date" 
                               name="date" 
                               class="input" 
                               value="<?php echo htmlspecialchars($search_date); ?>">
                    </div>
                    
                    <div class="search-row">
                        <div class="search-field full-width" style="display: flex; gap: 1rem;">
                            <button type="submit" class="btn btn-primary">
                                搜索
                            </button>
                            <?php if ($search_username || $search_action || $search_date): ?>
                            <a href="password_logs.php" class="btn btn-outline">清除筛选</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
                
                <!-- 筛选标签 -->
                <?php if ($search_username || $search_action || $search_date): ?>
                <div class="filter-tags mt-4">
                    <span style="font-size: 0.875rem; color: var(--gray-600);">当前筛选：</span>
                    <?php if ($search_username): ?>
                        <div class="filter-tag">
                            用户名包含"<?php echo htmlspecialchars($search_username); ?>"
                            <button class="filter-tag-close" onclick="removeFilter('username')">&times;</button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($search_action): ?>
                        <div class="filter-tag">
                            操作包含"<?php echo htmlspecialchars($search_action); ?>"
                            <button class="filter-tag-close" onclick="removeFilter('action')">&times;</button>
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
            </section>
            
            <!-- 管理员操作 -->
            <?php if ($isAdmin && $total_logs > 0): ?>
            <section class="admin-actions">
                <div>
                    <strong>管理员操作</strong>
                    <div style="color: var(--gray-500); font-size: 0.875rem; margin-top: 0.25rem;">请谨慎操作以下功能</div>
                </div>
                <div>
                    <button class="btn btn-danger" onclick="if(confirm('确定要清空所有操作记录吗？此操作不可恢复！')) location.href='?clear_all=1'">
                        清空所有记录
                    </button>
                </div>
            </section>
            <?php endif; ?>
            
            <!-- 操作记录列表 -->
            <section class="card">
                <h2 class="card-title">操作记录列表</h2>
                
                <?php if (!empty($logs)): ?>
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>用户</th>
                                <th>操作类型</th>
                                <th>IP地址</th>
                                <th>时间</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($logs as $log): ?>
                            <tr>
                                <td>#<?php echo $log['id']; ?></td>
                                <td>
                                    <div class="user-cell">
                                        <div class="user-avatar">
                                            <?php echo strtoupper(substr($log['username'], 0, 1)); ?>
                                        </div>
                                        <span><?php echo htmlspecialchars($log['username']); ?></span>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-info"><?php echo htmlspecialchars($log['action']); ?></span>
                                </td>
                                <td>
                                    <span class="ip-address"><?php echo htmlspecialchars($log['ip_address'] ?? '未知'); ?></span>
                                </td>
                                <td style="font-size: 0.75rem; color: var(--gray-500);">
                                    <?php echo date('m-d H:i', strtotime($log['created_at'])); ?>
                                </td>
                                <td>
                                    <div class="actions">
                                        <button class="btn btn-outline btn-sm" onclick="showLogDetail(<?php echo $log['id']; ?>)">详情</button>
                                        
                                        <?php if ($isAdmin): ?>
                                            <button class="btn btn-danger btn-sm" 
                                                    onclick="confirmDelete(<?php echo $log['id']; ?>)">删除</button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <?php echo ($search_username || $search_action || $search_date) ? '没有找到匹配的记录' : '暂无记录'; ?>
                </div>
                <?php endif; ?>
                
                <!-- 分页 -->
                <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <div style="font-size: 0.875rem; color: var(--gray-600);">
                        显示第 <?php echo $offset + 1; ?> - <?php echo min($offset + $limit, $total_logs); ?> 条，共 <?php echo $total_logs; ?> 条记录
                    </div>
                    
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <?php 
                        $query_string = '';
                        if ($search_username) $query_string .= "&username=" . urlencode($search_username);
                        if ($search_action) $query_string .= "&action=" . urlencode($search_action);
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
            </section>
        </main>
    </div>
    
    <!-- 日志详情模态框 -->
    <div id="detailModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>操作日志详情</h3>
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
        function confirmDelete(logId) {
            if (confirm('确定要删除这条记录吗？此操作不可恢复！')) {
                location.href = '?delete=' + logId;
            }
        }
        
        // 显示日志详情
        async function showLogDetail(logId) {
            try {
                const response = await fetch(`/config/get_password_log_detail.php?id=${logId}`);
                const data = await response.json();
                
                if (data.success) {
                    const log = data.data;
                    let html = `
                        <div style="margin-bottom: 1.5rem;">
                            <div style="font-size: 0.875rem; font-weight: 600; color: var(--gray-600); margin-bottom: 0.5rem;">ID</div>
                            <div style="background: var(--gray-50); padding: 0.5rem 0.75rem; border-radius: 0.375rem; font-family: monospace;">#${log.id}</div>
                        </div>
                        
                        <div style="margin-bottom: 1.5rem;">
                            <div style="font-size: 0.875rem; font-weight: 600; color: var(--gray-600); margin-bottom: 0.5rem;">用户名</div>
                            <div style="background: var(--gray-50); padding: 0.5rem 0.75rem; border-radius: 0.375rem;">${log.username}</div>
                        </div>
                        
                        <div style="margin-bottom: 1.5rem;">
                            <div style="font-size: 0.875rem; font-weight: 600; color: var(--gray-600); margin-bottom: 0.5rem;">操作类型</div>
                            <div style="background: var(--gray-50); padding: 0.5rem 0.75rem; border-radius: 0.375rem;">${log.action}</div>
                        </div>
                        
                        <div style="margin-bottom: 1.5rem;">
                            <div style="font-size: 0.875rem; font-weight: 600; color: var(--gray-600); margin-bottom: 0.5rem;">操作详情</div>
                            <div style="background: var(--gray-50); padding: 0.5rem 0.75rem; border-radius: 0.375rem; white-space: pre-wrap;">${log.details || '无详情'}</div>
                        </div>
                        
                        <div style="margin-bottom: 1.5rem;">
                            <div style="font-size: 0.875rem; font-weight: 600; color: var(--gray-600); margin-bottom: 0.5rem;">IP地址</div>
                            <div style="background: var(--gray-50); padding: 0.5rem 0.75rem; border-radius: 0.375rem; font-family: monospace;">${log.ip_address || '未知'}</div>
                        </div>
                        
                        <div style="margin-bottom: 1.5rem;">
                            <div style="font-size: 0.875rem; font-weight: 600; color: var(--gray-600); margin-bottom: 0.5rem;">用户ID</div>
                            <div style="background: var(--gray-50); padding: 0.5rem 0.75rem; border-radius: 0.375rem; font-family: monospace;">${log.user_id || '-'}</div>
                        </div>
                        
                        <div style="margin-bottom: 1.5rem;">
                            <div style="font-size: 0.875rem; font-weight: 600; color: var(--gray-600); margin-bottom: 0.5rem;">操作时间</div>
                            <div style="background: var(--gray-50); padding: 0.5rem 0.75rem; border-radius: 0.375rem; font-family: monospace;">${new Date(log.created_at).toLocaleString()}</div>
                        </div>
                    `;
                    
                    document.getElementById('detailContent').innerHTML = html;
                    document.getElementById('detailModal').style.display = 'flex';
                } else {
                    alert('获取详情失败');
                }
            } catch (error) {
                console.error('获取日志详情失败:', error);
                // 如果API不存在，显示静态详情
                document.getElementById('detailContent').innerHTML = `
                    <div style="text-align: center; padding: 2rem;">
                        <div style="color: var(--gray-500);">详情加载失败</div>
                        <div style="color: var(--gray-400); font-size: 0.875rem; margin-top: 0.5rem;">请确保API文件存在</div>
                    </div>
                `;
                document.getElementById('detailModal').style.display = 'flex';
            }
        }
        
        // 关闭详情模态框
        function closeDetailModal() {
            document.getElementById('detailModal').style.display = 'none';
        }
        
        // 点击背景关闭模态框
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                closeDetailModal();
            }
        };
        
        // 消息自动隐藏
        const messages = document.querySelectorAll('.message');
        messages.forEach(message => {
            setTimeout(() => {
                message.style.opacity = '0';
                message.style.transition = 'opacity 0.3s ease-out';
                setTimeout(() => {
                    message.style.display = 'none';
                }, 300);
            }, 3000);
        });
    </script>
</body>
</html>