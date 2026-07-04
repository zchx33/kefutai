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

$db = getDB();
if (!$db) {
    die("数据库连接失败");
}

// 分页设置
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = isset($_GET['per_page']) ? intval($_GET['per_page']) : 20;
$per_page_options = [10, 20, 50, 100];
if (!in_array($per_page, $per_page_options)) {
    $per_page = 20;
}
$offset = ($page - 1) * $per_page;

// 搜索和筛选参数
$search_ip = isset($_GET['search_ip']) ? trim($_GET['search_ip']) : '';
$search_url = isset($_GET['search_url']) ? trim($_GET['search_url']) : '';
$date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';

// 构建查询条件
$where_conditions = [];
$params = [];
$types = '';

if (!empty($search_ip)) {
    $where_conditions[] = "ip_address LIKE ?";
    $params[] = "%{$search_ip}%";
    $types .= 's';
}

if (!empty($search_url)) {
    $where_conditions[] = "page_url LIKE ?";
    $params[] = "%{$search_url}%";
    $types .= 's';
}

if (!empty($date_from)) {
    $where_conditions[] = "DATE(created_at) >= ?";
    $params[] = $date_from;
    $types .= 's';
}

if (!empty($date_to)) {
    $where_conditions[] = "DATE(created_at) <= ?";
    $params[] = $date_to;
    $types .= 's';
}

$where_sql = '';
if (!empty($where_conditions)) {
    $where_sql = 'WHERE ' . implode(' AND ', $where_conditions);
}

// 获取总记录数
$count_sql = "SELECT COUNT(*) as total FROM visit_logs {$where_sql}";
$count_stmt = $db->prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_records = $count_result->fetch_assoc()['total'];
$count_stmt->close();

// 计算分页信息
$total_pages = ceil($total_records / $per_page);

// 获取当前页记录
$data_sql = "SELECT * FROM visit_logs {$where_sql} ORDER BY created_at DESC LIMIT ? OFFSET ?";
$data_stmt = $db->prepare($data_sql);

if (!empty($params)) {
    $params[] = $per_page;
    $params[] = $offset;
    $data_stmt->bind_param($types . "ii", ...$params);
} else {
    $data_stmt->bind_param("ii", $per_page, $offset);
}

$data_stmt->execute();
$result = $data_stmt->get_result();
$visit_logs = $result->fetch_all(MYSQLI_ASSOC);
$data_stmt->close();

// 获取统计信息
function getVisitStats($db) {
    $stats = [
        'today_logs' => 0,
        'total_logs' => 0,
        'unique_ips' => 0,
        'total_pages' => 0
    ];
    
    $today = date('Y-m-d');
    
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM visit_logs WHERE DATE(created_at) = ?");
    $stmt->bind_param("s", $today);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $stats['today_logs'] = $row['count'];
    }
    
    $result = $db->query("SELECT COUNT(*) as count FROM visit_logs");
    if ($result && $row = $result->fetch_assoc()) {
        $stats['total_logs'] = $row['count'];
    }
    
    $result = $db->query("SELECT COUNT(DISTINCT ip_address) as count FROM visit_logs");
    if ($result && $row = $result->fetch_assoc()) {
        $stats['unique_ips'] = $row['count'];
    }
    
    $result = $db->query("SELECT COUNT(DISTINCT page_url) as count FROM visit_logs");
    if ($result && $row = $result->fetch_assoc()) {
        $stats['total_pages'] = $row['count'];
    }
    
    return $stats;
}

$visit_stats = getVisitStats($db);
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>访问日志</title>
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
        
        /* Select */
        .select {
            padding: 0.5rem 0.75rem;
            border: 1px solid var(--gray-300);
            border-radius: 0.375rem;
            font-size: 0.875rem;
            background-color: var(--white);
            cursor: pointer;
        }
        
        .select:focus {
            outline: none;
            border-color: transparent;
            box-shadow: 0 0 0 2px var(--blue-500);
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
        
        /* User Agent Display */
        .user-agent {
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            cursor: pointer;
            color: var(--blue-500);
        }
        
        .user-agent:hover {
            text-decoration: underline;
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
        
        /* IP Address */
        .ip-address {
            font-family: monospace;
            font-weight: 500;
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
                <h1 class="title">访问日志</h1>
            </div>
        </header>

        <!-- Main Content -->
        <main class="main-content">
            <!-- 统计信息 -->
            <section class="card">
                <h2 class="card-title">统计信息</h2>
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $visit_stats['total_logs']; ?></div>
                        <div class="stat-label">总访问记录</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $visit_stats['today_logs']; ?></div>
                        <div class="stat-label">今日访问</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $visit_stats['unique_ips']; ?></div>
                        <div class="stat-label">独立IP</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $visit_stats['total_pages']; ?></div>
                        <div class="stat-label">访问页面</div>
                    </div>
                </div>
            </section>
            
            <!-- 搜索表单 -->
            <section class="card">
                <h2 class="card-title">高级搜索</h2>
                <form method="GET" class="search-form">
                    <div class="search-field">
                        <label class="form-label">IP地址</label>
                        <input type="text" name="search_ip" value="<?php echo htmlspecialchars($search_ip); ?>" 
                               placeholder="搜索IP地址..." class="input">
                    </div>
                    
                    <div class="search-field">
                        <label class="form-label">页面URL</label>
                        <input type="text" name="search_url" value="<?php echo htmlspecialchars($search_url); ?>" 
                               placeholder="搜索页面URL..." class="input">
                    </div>
                    
                    <div class="search-field">
                        <label class="form-label">开始日期</label>
                        <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>" 
                               class="input">
                    </div>
                    
                    <div class="search-field">
                        <label class="form-label">结束日期</label>
                        <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>" 
                               class="input">
                    </div>
                    
                    <div class="search-row">
                        <div class="search-field full-width" style="display: flex; gap: 1rem;">
                            <button type="submit" class="btn btn-primary">
                                搜索
                            </button>
                            <?php if ($search_ip || $search_url || $date_from || $date_to): ?>
                            <a href="visit_logs.php" class="btn btn-outline">清除筛选</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </section>
            
            <!-- 访问记录列表 -->
            <section class="card">
                <h2 class="card-title">访问记录列表</h2>
                
                <?php if (!empty($visit_logs)): ?>
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>IP地址</th>
                                <th>页面URL</th>
                                <th>访问时间</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($visit_logs as $log): ?>
                            <tr>
                                <td>#<?php echo $log['id']; ?></td>
                                <td>
                                    <span class="ip-address"><?php echo htmlspecialchars($log['ip_address']); ?></span>
                                </td>
                                <td>
                                    <div style="max-width: 150px; overflow: hidden; text-overflow: ellipsis;">
                                        <?php echo htmlspecialchars($log['page_url']); ?>
                                    </div>
                                </td>
                                <td style="font-size: 0.75rem; color: var(--gray-500);">
                                    <?php echo date('m-d H:i:s', strtotime($log['created_at'])); ?>
                                </td>
                                <td>
                                    <div class="actions">
                                        <button class="btn btn-outline btn-sm" onclick="showLogDetail(<?php echo $log['id']; ?>)">详情</button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <?php echo ($search_ip || $search_url || $date_from || $date_to) ? '没有找到匹配的访问记录' : '暂无访问记录'; ?>
                </div>
                <?php endif; ?>
                
                <!-- 分页 -->
                <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <div style="font-size: 0.875rem; color: var(--gray-600);">
                        显示第 <?php echo $offset + 1; ?> - <?php echo min($offset + $per_page, $total_records); ?> 条，共 <?php echo $total_records; ?> 条记录
                    </div>
                    
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <!-- 每页显示条数选择 -->
                        <select class="select" onchange="changePerPage(this.value)">
                            <?php foreach ($per_page_options as $option): ?>
                            <option value="<?php echo $option; ?>" <?php echo $per_page == $option ? 'selected' : ''; ?>>
                                <?php echo $option; ?> 条/页
                            </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <!-- 分页链接 -->
                        <?php 
                        $query_string = '';
                        if ($search_ip) $query_string .= "&search_ip=" . urlencode($search_ip);
                        if ($search_url) $query_string .= "&search_url=" . urlencode($search_url);
                        if ($date_from) $query_string .= "&date_from=" . urlencode($date_from);
                        if ($date_to) $query_string .= "&date_to=" . urlencode($date_to);
                        ?>
                        
                        <?php if ($page > 1): ?>
                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&per_page=<?php echo $per_page; ?><?php echo $query_string; ?>">‹</a>
                        <?php endif; ?>
                        
                        <?php
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        
                        for ($i = $start_page; $i <= $end_page; $i++) {
                            if ($i == $page) {
                                echo '<a class="page-link active" href="?page=' . $i . '&per_page=' . $per_page . $query_string . '">' . $i . '</a>';
                            } else {
                                echo '<a class="page-link" href="?page=' . $i . '&per_page=' . $per_page . $query_string . '">' . $i . '</a>';
                            }
                        }
                        ?>
                        
                        <?php if ($page < $total_pages): ?>
                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&per_page=<?php echo $per_page; ?><?php echo $query_string; ?>">›</a>
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
                <h3>访问日志详情</h3>
                <button class="modal-close" onclick="closeDetailModal()">×</button>
            </div>
            <div class="modal-body">
                <div id="detailContent">
                    <!-- 详情内容将通过AJAX加载 -->
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // 每页显示条数切换
        function changePerPage(perPage) {
            const url = new URL(window.location.href);
            url.searchParams.set('per_page', perPage);
            url.searchParams.set('page', 1);
            window.location.href = url.toString();
        }
        
        // 显示日志详情
        async function showLogDetail(logId) {
            try {
                const response = await fetch(`/config/get_log_detail.php?id=${logId}`);
                const data = await response.json();
                
                if (data.success) {
                    const log = data.data;
                    let html = `
                        <div style="margin-bottom: 1.5rem;">
                            <div style="font-size: 0.875rem; font-weight: 600; color: var(--gray-600); margin-bottom: 0.5rem;">ID</div>
                            <div style="background: var(--gray-50); padding: 0.5rem 0.75rem; border-radius: 0.375rem; font-family: monospace;">#${log.id}</div>
                        </div>
                        
                        <div style="margin-bottom: 1.5rem;">
                            <div style="font-size: 0.875rem; font-weight: 600; color: var(--gray-600); margin-bottom: 0.5rem;">IP地址</div>
                            <div style="background: var(--gray-50); padding: 0.5rem 0.75rem; border-radius: 0.375rem; font-family: monospace;">${log.ip_address}</div>
                        </div>
                        
                        <div style="margin-bottom: 1.5rem;">
                            <div style="font-size: 0.875rem; font-weight: 600; color: var(--gray-600); margin-bottom: 0.5rem;">页面URL</div>
                            <div style="background: var(--gray-50); padding: 0.5rem 0.75rem; border-radius: 0.375rem; font-family: monospace; word-break: break-all;">${log.page_url}</div>
                        </div>
                        
                        <div style="margin-bottom: 1.5rem;">
                            <div style="font-size: 0.875rem; font-weight: 600; color: var(--gray-600); margin-bottom: 0.5rem;">来源页面</div>
                            <div style="background: var(--gray-50); padding: 0.5rem 0.75rem; border-radius: 0.375rem; font-family: monospace; word-break: break-all;">${log.referrer || '-'}</div>
                        </div>
                        
                        <div style="margin-bottom: 1.5rem;">
                            <div style="font-size: 0.875rem; font-weight: 600; color: var(--gray-600); margin-bottom: 0.5rem;">用户代理</div>
                            <div style="background: var(--gray-50); padding: 0.5rem 0.75rem; border-radius: 0.375rem; font-family: monospace; word-break: break-all; font-size: 0.75rem;">${log.user_agent || '-'}</div>
                        </div>
                        
                        <div style="margin-bottom: 1.5rem;">
                            <div style="font-size: 0.875rem; font-weight: 600; color: var(--gray-600); margin-bottom: 0.5rem;">会话ID</div>
                            <div style="background: var(--gray-50); padding: 0.5rem 0.75rem; border-radius: 0.375rem; font-family: monospace; word-break: break-all;">${log.session_id || '-'}</div>
                        </div>
                        
                        <div style="margin-bottom: 1.5rem;">
                            <div style="font-size: 0.875rem; font-weight: 600; color: var(--gray-600); margin-bottom: 0.5rem;">用户ID</div>
                            <div style="background: var(--gray-50); padding: 0.5rem 0.75rem; border-radius: 0.375rem; font-family: monospace;">${log.user_id || '-'}</div>
                        </div>
                        
                        <div style="margin-bottom: 1.5rem;">
                            <div style="font-size: 0.875rem; font-weight: 600; color: var(--gray-600); margin-bottom: 0.5rem;">访问时间</div>
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
                alert('获取详情失败');
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
    </script>
</body>
</html>