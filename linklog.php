<?php

if (!defined('FROM_ROUTER')) {
    http_response_code(403);
    exit('喜乐科技@x60898');
}

session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/dbconfig.php';
checkLogin();
checkAdmin();

$currentAgent = $_SESSION['username'];

// 平台图标映射
$platformIcons = [
    '闲鱼' => '/assets/img/xy-kf.png',
    '闲鱼代练' => '/assets/img/xy-logo.png',
    '闲鱼话费' => '/assets/img/xy-logo.png',
    '转转' => '/assets/img/zz-kf.png',
    '微信' => '/assets/img/wechat.jpg',
    '盼之' => '/assets/img/panzhi.png',
    '盼之群聊' => '/assets/img/panzhi.png',
    '抖音' => '/assets/img/douyin.png',
    '大麦' => '/assets/img/dm-kf.png',
    '螃蟹' => '/assets/img/pxb7.png',
    '螃蟹群聊' => '/assets/img/pxb7.png',
    '白情' => '/assets/img/bq-kf.jpg',
    '白情群聊' => '/assets/img/bq-kf.jpg',
    '京东' => '/assets/img/jd.png',
    '得物' => '/assets/img/dw-kf.png',
    '钉钉' => '/assets/img/dingding.png',
    '拼多多' => '/assets/img/pdd.png',
    '自定义' => '/assets/img/diy.png',
    '交易猫' => '/assets/img/jym-kf.png',
    '默认' => '/assets/img/normal.png'
];

// 分页设置
$recordsPerPage = 20;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $recordsPerPage;

// 搜索和筛选参数
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

// 获取总记录数
function getTotalRecords($whereSQL, $params, $paramTypes) {
    $db = getDB();
    if (!$db) return 0;
    
    $sql = "SELECT COUNT(*) as total FROM `XE-SKDJWKSNCDATA` WHERE $whereSQL";
    $stmt = $db->prepare($sql);
    
    if ($params) {
        $stmt->bind_param($paramTypes, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return $row ? $row['total'] : 0;
}

// 获取记录列表
function getRecords($whereSQL, $params, $paramTypes, $offset, $recordsPerPage) {
    $db = getDB();
    if (!$db) return [];
    
    $sql = "SELECT * FROM `XE-SKDJWKSNCDATA` 
            WHERE $whereSQL 
            ORDER BY created_at DESC 
            LIMIT ?, ?";
    
    $stmt = $db->prepare($sql);
    
    // 添加分页参数
    $params[] = $offset;
    $params[] = $recordsPerPage;
    $paramTypes .= "ii";
    
    $stmt->bind_param($paramTypes, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $records = [];
    while ($row = $result->fetch_assoc()) {
        $records[] = $row;
    }
    
    return $records;
}

// 获取平台列表
function getPlatformList() {
    $db = getDB();
    if (!$db) return [];
    
    $sql = "SELECT DISTINCT platform FROM `XE-SKDJWKSNCDATA` WHERE platform IS NOT NULL AND platform != '' ORDER BY platform";
    $result = $db->query($sql);
    
    $platforms = [];
    while ($row = $result->fetch_assoc()) {
        $platforms[] = $row['platform'];
    }
    
    return $platforms;
}

$totalRecords = getTotalRecords($whereSQL, $params, $paramTypes);
$totalPages = ceil($totalRecords / $recordsPerPage);
$records = getRecords($whereSQL, $params, $paramTypes, $offset, $recordsPerPage);
$platforms = getPlatformList();

// 生成完整链接
function generateFullUrl($sessionId, $xedataToken) {
    $currentDomain = $_SERVER['HTTP_HOST'];
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $baseUrl = $protocol . "://" . $currentDomain;
    return $baseUrl . '/ChatDY?id=' . $sessionId . '&XEDATA=' . $xedataToken;
}

// 获取平台图标
function getPlatformIcon($platformName) {
    global $platformIcons;
    return isset($platformIcons[$platformName]) ? $platformIcons[$platformName] : '/assets/img/normal.png';
}

// 删除记录
if (isset($_POST['delete_record'])) {
    $recordId = intval($_POST['record_id']);
    $db = getDB();
    if ($db) {
        $stmt = $db->prepare("DELETE FROM `XE-SKDJWKSNCDATA` WHERE id = ? AND agent_account = ?");
        $stmt->bind_param("is", $recordId, $currentAgent);
        $stmt->execute();
        
        // 刷新页面
         echo "<script>window.location.href = window.location.href;</script>";
       exit;
    }
}

// 批量失效
if (isset($_POST['batch_invalidate'])) {
    $recordIds = isset($_POST['record_ids']) ? $_POST['record_ids'] : [];
    if (!empty($recordIds)) {
        $db = getDB();
        if ($db) {
            // 构建IN条件
            $placeholders = implode(',', array_fill(0, count($recordIds), '?'));
            $sql = "UPDATE `XE-SKDJWKSNCDATA` 
                    SET status = 'expired', expires_at = NOW() 
                    WHERE id IN ($placeholders) AND agent_account = ?";
            
            $stmt = $db->prepare($sql);
            
            // 绑定参数
            $paramTypes = str_repeat('i', count($recordIds)) . 's';
            $bindParams = array_merge($recordIds, [$currentAgent]);
            $stmt->bind_param($paramTypes, ...$bindParams);
            $stmt->execute();
            
            // 刷新页面
             echo "<script>window.location.href = window.location.href;</script>";
    exit;
        }
    }
}

// 批量取消过期
if (isset($_POST['batch_reactivate'])) {
    $recordIds = isset($_POST['record_ids']) ? $_POST['record_ids'] : [];
    if (!empty($recordIds)) {
        $db = getDB();
        if ($db) {
            // 过滤ID为整数
            $recordIds = array_map('intval', $recordIds);
            
            // 构建IN条件
            $placeholders = implode(',', array_fill(0, count($recordIds), '?'));
            
            // 使用更简单的SQL语句
            $sql = "UPDATE `XE-SKDJWKSNCDATA` 
                    SET status = 'active' 
                    WHERE id IN ($placeholders) AND agent_account = ?";
            
            $stmt = $db->prepare($sql);
            
            if ($stmt) {
                // 绑定参数
                $paramTypes = str_repeat('i', count($recordIds)) . 's';
                $bindParams = array_merge($recordIds, [$currentAgent]);
                $stmt->bind_param($paramTypes, ...$bindParams);
                $stmt->execute();
            } else {
                // 记录错误
                error_log("SQL准备失败: " . $db->error);
            }
            
            // 刷新页面
            echo "<script>window.location.href = window.location.href;</script>";
    exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>生成记录管理 - 管理面板</title>
    <!-- 引入 Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
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
        
        .btn-success {
            background-color: var(--green-500);
        }
        
        .btn-success:hover {
            background-color: var(--green-600);
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
        
        /* 批量操作 */
        .batch-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding: 1rem;
            background-color: var(--gray-50);
            border-radius: 0.375rem;
            border: 1px solid var(--gray-200);
        }
        
        .batch-checkbox {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .records-count {
            font-size: 0.875rem;
            color: var(--gray-600);
        }
        
        /* 卡片网格布局 */
        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 1.5rem;
        }
        
        /* 记录卡片 */
        .record-card {
            background-color: var(--white);
            border-radius: 0.5rem;
            border: 1px solid var(--gray-200);
            overflow: hidden;
            transition: all 0.2s;
        }
        
        .record-card:hover {
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        
        .record-card.row-expired {
            opacity: 0.7;
            border-color: var(--red-200);
        }
        
        .record-card.selected {
            border-color: var(--blue-500);
            box-shadow: 0 0 0 1px var(--blue-500);
        }
        
        /* 卡片头部 */
        .card-header {
            padding: 1rem;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: var(--gray-50);
        }
        
        .card-checkbox {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .card-id {
            font-size: 0.875rem;
            color: var(--gray-600);
            font-weight: 600;
        }
        
        /* 卡片主体 */
        .card-body {
            padding: 1rem;
        }
        
        .card-row {
            display: flex;
            margin-bottom: 0.75rem;
            align-items: flex-start;
        }
        
        .card-label {
            width: 80px;
            font-size: 0.75rem;
            color: var(--gray-600);
            flex-shrink: 0;
        }
        
        .card-value {
            flex: 1;
            font-size: 0.875rem;
            color: var(--gray-900);
        }
        
        .card-session {
            font-family: 'Monaco', 'Menlo', 'Consolas', monospace;
            font-size: 0.75rem;
            background-color: var(--gray-100);
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            word-break: break-all;
        }
        
        .card-platform {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .platform-icon {
            width: 1.5rem;
            height: 1.5rem;
            border-radius: 0.375rem;
            object-fit: cover;
        }
        
        /* 状态徽章 */
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 0.375rem;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .status-active {
            background-color: var(--green-100);
            color: var(--green-700);
        }
        
        .status-used {
            background-color: var(--blue-100);
            color: var(--blue-700);
        }
        
        .status-expired {
            background-color: var(--red-100);
            color: var(--red-700);
        }
        
        /* 卡片底部 */
        .card-footer {
            padding: 1rem;
            border-top: 1px solid var(--gray-200);
            display: flex;
            gap: 0.5rem;
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
        
        .pagination-buttons {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .pagination-btn {
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
            background: var(--white);
            cursor: pointer;
        }
        
        .pagination-btn:hover:not(:disabled) {
            background-color: var(--gray-100);
        }
        
        .pagination-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .pagination-btn.active {
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
        
        .empty-state svg {
            width: 4rem;
            height: 4rem;
            margin-bottom: 1rem;
            color: var(--gray-300);
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
        
        .modal-content {
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
        
        .modal-footer {
            padding: 1.5rem;
            border-top: 1px solid var(--gray-200);
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
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
        
        /* 详情行 */
        .detail-row {
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
        
        .url-preview {
            background-color: var(--gray-100);
            padding: 0.5rem 0.75rem;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            word-break: break-all;
        }
        
        /* 响应式调整 */
        @media (min-width: 768px) {
            .search-form {
                grid-template-columns: repeat(3, 1fr);
            }
            
            .cards-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (min-width: 1024px) {
            .cards-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }
        
        @media (max-width: 767px) {
            .cards-grid {
                grid-template-columns: 1fr;
            }
        }
        
        /* Helper classes */
        .mb-4 {
            margin-bottom: 1rem;
        }
        
        .mb-6 {
            margin-bottom: 1.5rem;
        }
        
        .mt-4 {
            margin-top: 1rem;
        }
        
        /* 页面加载动画 */
        .page-load-animation {
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        /* Toast通知 */
        .toast {
            position: fixed;
            top: 1rem;
            right: 1rem;
            padding: 0.75rem 1.5rem;
            border-radius: 0.375rem;
            color: var(--white);
            font-size: 0.875rem;
            z-index: 9999;
            animation: slideIn 0.3s ease;
        }
        
        .toast-success {
            background-color: var(--green-500);
        }
        
        .toast-error {
            background-color: var(--red-500);
        }
        
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        @keyframes slideOut {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(100%); opacity: 0; }
        }
    </style>
</head>
<body class="page-load-animation">
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
                <h1 class="title">分享记录</h1>
                <div class="header-actions"></div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="main-content">
            <!-- 搜索筛选 -->
            <div class="card">
                <h2 class="card-title">搜索筛选</h2>
                <form method="GET" class="search-form">
                    <div class="search-field">
                        <label class="form-label">搜索</label>
                        <input type="text" 
                               name="search" 
                               class="input" 
                               placeholder="输入会话ID 或 客户名称进行搜索" 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="search-field">
                        <label class="form-label">状态</label>
                        <select name="status" class="select">
                            <option value="all">全部状态</option>
                            <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>活跃</option>
                            <option value="used" <?php echo $status === 'used' ? 'selected' : ''; ?>>已使用</option>
                            <option value="expired" <?php echo $status === 'expired' ? 'selected' : ''; ?>>已过期</option>
                        </select>
                    </div>
                    
                    <div class="search-field">
                        <label class="form-label">平台</label>
                        <select name="platform" class="select">
                            <option value="all">全部平台</option>
                            <?php foreach ($platforms as $p): ?>
                                <option value="<?php echo htmlspecialchars($p); ?>" 
                                        <?php echo $platform === $p ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($p); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="search-field">
                        <label class="form-label">开始日期</label>
                        <input type="date" 
                               name="date_from" 
                               class="input" 
                               value="<?php echo htmlspecialchars($dateFrom); ?>">
                    </div>
                    
                    <div class="search-field">
                        <label class="form-label">结束日期</label>
                        <input type="date" 
                               name="date_to" 
                               class="input" 
                               value="<?php echo htmlspecialchars($dateTo); ?>">
                    </div>
                    
                    <div class="search-actions">
                        <button type="button" class="btn btn-outline" onclick="resetFilters()">
                            <svg class="btn-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M15.312 11.424a5.5 5.5 0 0 1-9.201 2.466l-.312-.311h2.433a.75.75 0 0 0 0-1.5H3.989a.75.75 0 0 0-.75.75v4.242a.75.75 0 0 0 1.5 0v-2.43l.311.311a7 7 0 0 0 11.712-3.138.75.75 0 0 0-1.449-.39Zm1.23-3.723a.75.75 0 0 0 .219-.53V2.929a.75.75 0 0 0-1.5 0v2.43l-.311-.311A7 7 0 0 0 3.239 8.188a.75.75 0 1 0 1.448.389A5.5 5.5 0 0 1 13.89 6.11l.311.311h-2.432a.75.75 0 0 0 0 1.5h4.243a.75.75 0 0 0 .53-.219Z" clip-rule="evenodd" />
                            </svg>
                            重置
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <svg class="btn-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11ZM2 9a7 7 0 1 1 12.452 4.391l3.328 3.329a.75.75 0 1 1-1.06 1.06l-3.329-3.328A7 7 0 0 1 2 9Z" clip-rule="evenodd" />
                            </svg>
                            搜索
                        </button>
                    </div>
                </form>
            </div>

            <!-- 批量操作和记录列表 -->
            <form method="POST" id="batch-form">
                <div class="card">
                    <h2 class="card-title">生成记录列表</h2>
                    
                    <?php if (!empty($records)): ?>
                        <!-- 批量操作 -->
                        <div class="batch-actions">
                            <div class="batch-checkbox">
                                <input type="checkbox" id="select-all" onchange="toggleSelectAll()">
                                <label for="select-all" style="font-size: 0.875rem;">全选</label>
                            </div>
                            
                            <div style="display: flex; gap: 0.5rem;">
                                <button type="submit" 
                                        name="batch_invalidate" 
                                        class="btn btn-danger btn-sm" 
                                        onclick="return confirmBatchAction('invalidate')">
                                    <svg class="btn-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16ZM8.28 7.22a.75.75 0 0 0-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 1 0 1.06 1.06L10 11.06l1.72 1.72a.75.75 0 1 0 1.06-1.06L11.06 10l1.72-1.72a.75.75 0 0 0-1.06-1.06L10 8.94 8.28 7.22Z" clip-rule="evenodd" />
                                    </svg>
                                    设为过期
                                </button>
                                <button type="submit" 
                                        name="batch_reactivate" 
                                        class="btn btn-success btn-sm" 
                                        onclick="return confirmBatchAction('reactivate')">
                                    <svg class="btn-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.857-9.809a.75.75 0 0 0-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 1 0-1.06 1.061l2.5 2.5a.75.75 0 0 0 1.137-.089l4-5.5Z" clip-rule="evenodd" />
                                    </svg>
                                    取消过期
                                </button>
                            </div>
                            
                            
                        </div>

                        <!-- 记录卡片网格 -->
                        <div class="cards-grid">
                            <?php foreach ($records as $record): ?>
                                <?php
                                $fullUrl = $record['session_id'] . '|' . $record['xedata_token'] . '|' . $platformName;
                                $statusClass = '';
                                $statusText = '';
                                $platformIcon = getPlatformIcon($record['platform']);
                                $platformName = $record['platform'] ?: '默认';
                                
                                // 检查是否已过期
                                $currentTime = time();
                                $expiresTime = strtotime($record['expires_at']);
                                $isExpired = ($record['expire_hours'] != 0 && $currentTime > $expiresTime);
                                $isManuallyExpired = ($record['status'] == 'expired');
                                
                                if ($isManuallyExpired) {
                                    $statusClass = 'status-expired';
                                    $statusText = '已过期';
                                } elseif ($isExpired) {
                                    $statusClass = 'status-expired';
                                    $statusText = '已过期';
                                } else {
                                    switch ($record['status']) {
                                        case 'active':
                                            $statusClass = 'status-active';
                                            $statusText = '活跃';
                                            break;
                                        case 'used':
                                            $statusClass = 'status-used';
                                            $statusText = '已使用';
                                            break;
                                        case 'expired':
                                            $statusClass = 'status-expired';
                                            $statusText = '已过期';
                                            break;
                                        default:
                                            $statusClass = 'status-active';
                                            $statusText = '活跃';
                                    }
                                }
                                ?>
                                
                                <div class="record-card <?php echo $isExpired || $isManuallyExpired ? 'row-expired' : ''; ?>" id="record-<?php echo $record['id']; ?>">
                                    <!-- 卡片头部 -->
                                    <div class="card-header">
                                        <div class="card-checkbox">
                                            <input type="checkbox" 
                                                   name="record_ids[]" 
                                                   value="<?php echo $record['id']; ?>"
                                                   class="record-checkbox"
                                                   onchange="toggleRecordSelection(this, <?php echo $record['id']; ?>)">
                                            <span class="card-id">#<?php echo $record['id']; ?></span>
                                        </div>
                                        <span class="status-badge <?php echo $statusClass; ?>">
                                            <?php echo $statusText; ?>
                                        </span>
                                    </div>
                                    
                                    <!-- 卡片主体 -->
                                    <div class="card-body">
                                        <!-- 会话ID -->
                                        <div class="card-row">
                                            <div class="card-label">会话ID</div>
                                            <div class="card-value card-session"><?php echo htmlspecialchars($record['session_id']); ?></div>
                                        </div>
                                        
                                        <!-- 客户名称 -->
                                        <div class="card-row">
                                            <div class="card-label">客户名称</div>
                                            <div class="card-value"><?php echo htmlspecialchars($record['customer_name']); ?></div>
                                        </div>
                                        
                                        <!-- 平台 -->
                                        <div class="card-row">
                                            <div class="card-label">平台</div>
                                            <div class="card-value card-platform">
                                                <img src="<?php echo $platformIcon; ?>" 
                                                     alt="<?php echo htmlspecialchars($platformName); ?>" 
                                                     class="platform-icon">
                                                <span><?php echo htmlspecialchars($platformName); ?></span>
                                            </div>
                                        </div>
                                        
                                        <!-- 过期时间 -->
                                        <div class="card-row">
                                            <div class="card-label">过期时间</div>
                                            <div class="card-value">
                                                <?php 
                                                $expires_at = $record['expires_at'];
                                                if (strtotime($expires_at) > 0 && $expires_at != '0000-00-00 00:00:00') {
                                                    echo date('Y-m-d H:i', strtotime($expires_at));
                                                } else {
                                                    echo '<span style="color: var(--green-500);">永久有效</span>';
                                                }
                                                ?>
                                            </div>
                                        </div>
                                        
                                        <!-- 创建时间 -->
                                        <div class="card-row">
                                            <div class="card-label">创建时间</div>
                                            <div class="card-value"><?php echo date('Y-m-d H:i', strtotime($record['created_at'])); ?></div>
                                        </div>
                                    </div>
                                    
                                    <!-- 卡片底部操作 -->
                                    <div class="card-footer">
                                        <button type="button" 
                                                class="btn btn-outline btn-sm" 
                                                style="flex: 1;"
                                                data-session-id="<?php echo $record['session_id']; ?>"
                                                data-token="<?php echo $record['xedata_token']; ?>"
                                                data-platform="<?php echo htmlspecialchars($platformName); ?>"
                                                onclick="copyRecordLink(this)">
                                            <svg class="btn-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width: 14px; height: 14px;">
                                                <path fill-rule="evenodd" d="M13.887 3.182c.396.037.79.08 1.183.128C16.194 3.45 17 4.414 17 5.517V16.75A2.25 2.25 0 0 1 14.75 19h-9.5A2.25 2.25 0 0 1 3 16.75V5.517c0-1.103.806-2.068 1.93-2.207.393-.048.787-.09 1.183-.128A3.001 3.001 0 0 1 9 1h2c1.373 0 2.531.923 2.887 2.182ZM7.5 4A1.5 1.5 0 0 1 9 2.5h2A1.5 1.5 0 0 1 12.5 4v.5h-5V4Z" clip-rule="evenodd" />
                                            </svg>
                                            复制链接
                                        </button>
                                        
                                        <button type="button" 
                                                class="btn btn-outline btn-sm" 
                                                style="flex: 1;"
                                                onclick="viewDetails(<?php echo $record['id']; ?>)">
                                            <svg class="btn-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width: 14px; height: 14px;">
                                                <path d="M10 12.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z" />
                                                <path fill-rule="evenodd" d="M.664 10.59a1.651 1.651 0 0 1 0-1.186A10.004 10.004 0 0 1 10 3c4.257 0 7.893 2.66 9.336 6.41.147.381.146.804 0 1.186A10.004 10.004 0 0 1 10 17c-4.257 0-7.893-2.66-9.336-6.41ZM14 10a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z" clip-rule="evenodd" />
                                            </svg>
                                            详情
                                        </button>
                                        
                                        <form method="POST" 
                                              style="flex: 1;"
                                              onsubmit="return confirmDeleteRecord(this)">
                                            <input type="hidden" 
                                                   name="record_id" 
                                                   value="<?php echo $record['id']; ?>">
                                            <button type="submit" 
                                                    name="delete_record" 
                                                    class="btn btn-danger btn-sm"
                                                    >
                                                <svg class="btn-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width: 14px; height: 14px;">
                                                    <path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 0 0 6 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 1 0 .23 1.482l.149-.022.841 10.518A2.75 2.75 0 0 0 7.596 19h4.807a2.75 2.75 0 0 0 2.742-2.53l.841-10.52.149.023a.75.75 0 0 0 .23-1.482A41.03 41.03 0 0 0 14 4.193V3.75A2.75 2.75 0 0 0 11.25 1h-2.5ZM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4ZM8.58 7.72a.75.75 0 0 0-1.5.06l.3 7.5a.75.75 0 1 0 1.5-.06l-.3-7.5Zm4.34.06a.75.75 0 1 0-1.5-.06l-.3 7.5a.75.75 0 1 0 1.5.06l.3-7.5Z" clip-rule="evenodd" />
                                                </svg>
                                                删除
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <h3>暂无生成记录</h3>
                        </div>
                    <?php endif; ?>
                </div>
            </form>

            <!-- 分页 -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <div>
                        显示第 <?php echo $offset + 1; ?> - <?php echo min($offset + $recordsPerPage, $totalRecords); ?> 条，共 <?php echo $totalRecords; ?> 条记录
                    </div>
                    
                    <div class="pagination-buttons">
                        <button class="pagination-btn" onclick="goToPage(1)" <?php echo $page == 1 ? 'disabled' : ''; ?>>
                            <svg class="btn-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M15.79 14.77a.75.75 0 0 1-1.06.02l-4.5-4.25a.75.75 0 0 1 0-1.08l4.5-4.25a.75.75 0 1 1 1.04 1.08L11.832 10l3.938 3.71a.75.75 0 0 1 .02 1.06zm-6 0a.75.75 0 0 1-1.06.02l-4.5-4.25a.75.75 0 0 1 0-1.08l4.5-4.25a.75.75 0 1 1 1.04 1.08L5.832 10l3.938 3.71a.75.75 0 0 1 .02 1.06z" clip-rule="evenodd" />
                            </svg>
                        </button>
                        
                        <button class="pagination-btn" onclick="goToPage(<?php echo max(1, $page - 1); ?>)" <?php echo $page == 1 ? 'disabled' : ''; ?>>
                            <svg class="btn-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 0 1-.02 1.06L8.832 10l3.938 3.71a.75.75 0 1 1-1.04 1.08l-4.5-4.25a.75.75 0 0 1 0-1.08l4.5-4.25a.75.75 0 0 1 1.06.02z" clip-rule="evenodd" />
                            </svg>
                        </button>
                        
                        <?php
                        $startPage = max(1, $page - 2);
                        $endPage = min($totalPages, $page + 2);
                        
                        if ($startPage > 1) {
                            echo '<button class="pagination-btn" onclick="goToPage(1)">1</button>';
                            if ($startPage > 2) {
                                echo '<span style="padding: 0.5rem 0.25rem;">...</span>';
                            }
                        }
                        
                        for ($i = $startPage; $i <= $endPage; $i++):
                            if ($i == $page): ?>
                                <button class="pagination-btn active" disabled>
                                    <?php echo $i; ?>
                                </button>
                            <?php else: ?>
                                <button class="pagination-btn" onclick="goToPage(<?php echo $i; ?>)">
                                    <?php echo $i; ?>
                                </button>
                            <?php endif;
                        endfor; 
                        
                        if ($endPage < $totalPages) {
                            if ($endPage < $totalPages - 1) {
                                echo '<span style="padding: 0.5rem 0.25rem;">...</span>';
                            }
                            echo '<button class="pagination-btn" onclick="goToPage(' . $totalPages . ')">' . $totalPages . '</button>';
                        }
                        ?>
                        
                        <button class="pagination-btn" onclick="goToPage(<?php echo min($totalPages, $page + 1); ?>)" <?php echo $page == $totalPages ? 'disabled' : ''; ?>>
                            <svg class="btn-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 .02-1.06L11.168 10 7.23 6.29a.75.75 0 1 1 1.04-1.08l4.5 4.25a.75.75 0 0 1 0 1.08l-4.5 4.25a.75.75 0 0 1-1.06-.02z" clip-rule="evenodd" />
                            </svg>
                        </button>
                        
                        <button class="pagination-btn" onclick="goToPage(<?php echo $totalPages; ?>)" <?php echo $page == $totalPages ? 'disabled' : ''; ?>>
                            <svg class="btn-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M4.21 5.23a.75.75 0 0 1 1.06-.02l4.5 4.25a.75.75 0 0 1 0 1.08l-4.5 4.25a.75.75 0 1 1-1.04-1.08L8.168 10l-3.938-3.71a.75.75 0 0 1-.02-1.06zm6 0a.75.75 0 0 1 1.06-.02l4.5 4.25a.75.75 0 0 1 0 1.08l-4.5 4.25a.75.75 0 1 1-1.04-1.08L14.168 10l-3.938-3.71a.75.75 0 0 1-.02-1.06z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- 详情模态框 -->
    <div id="detailModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                记录详情
                <button class="modal-close" onclick="closeModal()">×</button>
            </div>
            <div class="modal-body" id="detailContent">
                <!-- 内容将通过JavaScript加载 -->
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal()">关闭</button>
                <button class="btn btn-primary" onclick="copyCurrentUrl()">
                    <svg class="btn-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M13.887 3.182c.396.037.79.08 1.183.128C16.194 3.45 17 4.414 17 5.517V16.75A2.25 2.25 0 0 1 14.75 19h-9.5A2.25 2.25 0 0 1 3 16.75V5.517c0-1.103.806-2.068 1.93-2.207.393-.048.787-.09 1.183-.128A3.001 3.001 0 0 1 9 1h2c1.373 0 2.531.923 2.887 2.182ZM7.5 4A1.5 1.5 0 0 1 9 2.5h2A1.5 1.5 0 0 1 12.5 4v.5h-5V4Z" clip-rule="evenodd" />
                    </svg>
                    复制链接
                </button>
            </div>
        </div>
    </div>

    <script>
    // 页面加载动画
    window.addEventListener('load', function() {
        setTimeout(() => {
            document.body.style.opacity = '1';
        }, 10);
    });

    // 平台图标映射
    const platformIcons = {
        '闲鱼': '/assets/img/xy-kf.png',
        '闲鱼代练': '/assets/img/xy-logo.png',
        '闲鱼话费': '/assets/img/xy-logo.png',
        '转转': '/assets/img/zz-kf.png',
        '微信': '/assets/img/wechat.jpg',
        '盼之': '/assets/img/panzhi.png',
        '盼之群聊': '/assets/img/panzhi.png',
        '抖音': '/assets/img/douyin.png',
        '大麦': '/assets/img/dm-kf.png',
        '螃蟹': '/assets/img/pxb7.png',
        '螃蟹群聊': '/assets/img/pxb7.png',
        '白情': '/assets/img/bq-kf.jpg',
        '白情群聊': '/assets/img/bq-kf.jpg',
        '京东': '/assets/img/jd.png',
        '得物': '/assets/img/dw-kf.png',
        '钉钉': '/assets/img/dingding.png',
        '拼多多': '/assets/img/pdd.png',
        '自定义': '/assets/img/diy.png',
        '交易猫': '/assets/img/jym-kf.png',
        '默认': '/assets/img/normal.png'
    };

    // 平台到聊天页面路径的映射
    const platformChatPaths = {
        '闲鱼': 'ChatGoofish',
        '闲鱼代练': 'ChatGoofishA',
        '闲鱼话费': 'ChatGoofish',
        '转转': 'ChatZZ',
        '微信': 'ChatWX',
        '盼之': 'ChatPZ',
        '盼之群聊': 'ChatPZ',
        '抖音': 'ChatDY',
        '大麦': 'ChatDM',
        '螃蟹': 'ChatPX',
        '螃蟹群聊': 'ChatPX',
        '白情': 'ChatBQ',
        '白情群聊': 'ChatBQ',
        '京东': 'ChatJD',
        '得物': 'ChatDW',
        '钉钉': 'ChatDD',
        '拼多多': 'ChatPDD',
        '自定义': 'ChatDIY',
        '交易猫': 'ChatJYM',
        '默认': 'ChatDY'
    };

    // 根据平台生成完整链接
    function generateFullUrl(sessionId, token, platform) {
        const protocol = window.location.protocol;
        const host = window.location.host;
        
        // 获取对应平台的聊天页面路径，如果没有则使用默认
        const chatPath = platformChatPaths[platform] || platformChatPaths['默认'];
        
        return `${protocol}//${host}/${chatPath}?id=${sessionId}&XEDATA=${token}`;
    }

    // 复制到剪贴板
    function copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(function() {
            showToast('success', '链接已复制到剪贴板！');
        }).catch(function(err) {
            console.error('复制失败:', err);
            // 降级方案
            const textArea = document.createElement('textarea');
            textArea.value = text;
            document.body.appendChild(textArea);
            textArea.select();
            try {
                document.execCommand('copy');
                showToast('success', '链接已复制到剪贴板！');
            } catch (e) {
                prompt('请手动复制链接：', text);
            }
            document.body.removeChild(textArea);
        });
    }

    // 复制记录链接
    function copyRecordLink(button) {
        const sessionId = button.getAttribute('data-session-id');
        const token = button.getAttribute('data-token');
        const platform = button.getAttribute('data-platform');
        
        const fullUrl = generateFullUrl(sessionId, token, platform);
        
        copyToClipboard(fullUrl);
    }

    // 显示Toast通知
    function showToast(type, message) {
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.textContent = message;
        document.body.appendChild(toast);
        
        // 3秒后自动移除
        setTimeout(() => {
            toast.style.animation = 'slideOut 0.3s ease';
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 300);
        }, 3000);
    }

    // 全选/取消全选
    function toggleSelectAll() {
        const checkboxes = document.querySelectorAll('.record-checkbox');
        const selectAll = document.getElementById('select-all').checked;
        
        checkboxes.forEach(checkbox => {
            checkbox.checked = selectAll;
            // 同时更新卡片的选择状态
            toggleRecordSelection(checkbox, checkbox.value);
        });
    }

    // 切换单个记录的选择状态
    function toggleRecordSelection(checkbox, recordId) {
        const recordCard = document.getElementById(`record-${recordId}`);
        if (checkbox.checked) {
            recordCard.classList.add('selected');
        } else {
            recordCard.classList.remove('selected');
        }
    }

    // 批量操作确认
    function confirmBatchAction(action) {
        const checkedCount = document.querySelectorAll('.record-checkbox:checked').length;
        if (checkedCount === 0) {
            showToast('error', '请先选择要操作的记录');
            return false;
        }
        
        const actionText = action === 'invalidate' ? '设为过期' : '取消过期';
        return confirm(`确定要将选中的 ${checkedCount} 条记录${actionText}吗？`);
    }

    // 删除记录确认
    function confirmDeleteRecord(form) {
        return confirm('确定要删除这条记录吗？此操作不可撤销！');
    }

    // 查看详情
    function viewDetails(recordId) {
        fetch(`/api/share/get_record_details?id=${recordId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const record = data.record;
                    const fullUrl = generateFullUrl(record.session_id, record.xedata_token, record.platform || '默认');
                    const platformIcon = platformIcons[record.platform] || platformIcons['默认'];
                    const platformName = record.platform || '默认';
                    
                    // 获取状态样式
                    let statusClass = '';
                    let statusText = '';
                    switch(record.status) {
                        case 'active':
                            statusClass = 'status-active';
                            statusText = '活跃';
                            break;
                        case 'used':
                            statusClass = 'status-used';
                            statusText = '已使用';
                            break;
                        case 'expired':
                            statusClass = 'status-expired';
                            statusText = '已过期';
                            break;
                        default:
                            statusClass = 'status-active';
                            statusText = '活跃';
                    }
                    
                    // 检查是否已过期
                    const currentTime = new Date();
                    const expiresTime = new Date(record.expires_at);
                    const isExpired = record.expire_hours != 0 && currentTime > expiresTime;
                    
                    if (isExpired) {
                        statusClass = 'status-expired';
                        statusText = '已过期';
                    }
                    
                    const html = `
                        <div class="detail-row">
                            <div class="detail-label">会话ID:</div>
                            <div class="detail-value">${record.session_id}</div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">XEDATA令牌:</div>
                            <div class="detail-value">${record.xedata_token}</div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">客户名称:</div>
                            <div class="detail-value">${record.customer_name}</div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">平台:</div>
                            <div class="detail-value">
                                <div class="platform-with-icon" style="display: flex; align-items: center; gap: 0.5rem;">
                                    <img src="${platformIcon}" alt="${platformName}" style="width: 1.5rem; height: 1.5rem; border-radius: 0.375rem;">
                                    <span>${platformName}</span>
                                </div>
                            </div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">过期时间:</div>
                            <div class="detail-value">
                                ${record.expire_hours == 0 ? '永久有效' : record.expires_at}
                            </div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">创建时间:</div>
                            <div class="detail-value">${record.created_at}</div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">最后访问:</div>
                            <div class="detail-value">${record.last_visit_time || '从未访问'}</div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">访问次数:</div>
                            <div class="detail-value">${record.visit_count || 0}</div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">状态:</div>
                            <div class="detail-value">
                                <span class="status-badge ${statusClass}" style="display: inline-block; margin-top: 0.3125rem;">
                                    ${statusText}
                                </span>
                            </div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">完整链接:</div>
                            <div class="detail-value">
                                <div class="url-preview">${fullUrl}</div>
                            </div>
                        </div>
                    `;
                    
                    document.getElementById('detailContent').innerHTML = html;
                    document.getElementById('detailModal').style.display = 'flex';
                    window.currentUrl = fullUrl;
                } else {
                    showToast('error', '获取详情失败');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('error', '获取详情失败');
            });
    }

    // 关闭模态框
    function closeModal() {
        document.getElementById('detailModal').style.display = 'none';
    }

    // 复制当前链接
    function copyCurrentUrl() {
        if (window.currentUrl) {
            copyToClipboard(window.currentUrl);
        }
    }

    // 重置筛选
    function resetFilters() {
        window.location.href = window.location.pathname;
    }

    // 跳转到指定页
    function goToPage(page) {
        const url = new URL(window.location.href);
        url.searchParams.set('page', page);
        window.location.href = url.toString();
    }

    // 点击模态框外部关闭
    window.onclick = function(event) {
        const modal = document.getElementById('detailModal');
        if (event.target == modal) {
            modal.style.display = 'none';
        }
    }

    // 页面加载完成后初始化
    document.addEventListener('DOMContentLoaded', function() {
        // 为复选框添加事件监听
        const checkboxes = document.querySelectorAll('.record-checkbox');
        const selectAllCheckbox = document.getElementById('select-all');
        
        // 更新全选复选框状态
        function updateSelectAllCheckbox() {
            if (checkboxes.length === 0 || !selectAllCheckbox) return;
            
            const allChecked = Array.from(checkboxes).every(checkbox => checkbox.checked);
            const someChecked = Array.from(checkboxes).some(checkbox => checkbox.checked);
            
            selectAllCheckbox.checked = allChecked;
            selectAllCheckbox.indeterminate = someChecked && !allChecked;
        }
        
        // 为每个复选框添加change事件
        checkboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                toggleRecordSelection(this, this.value);
                updateSelectAllCheckbox();
            });
        });
        
        // 初始化更新
        updateSelectAllCheckbox();
        
        // 为全选复选框添加事件
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function() {
                const allChecked = this.checked;
                checkboxes.forEach(checkbox => {
                    checkbox.checked = allChecked;
                    toggleRecordSelection(checkbox, checkbox.value);
                });
            });
        }
    });

    // 键盘快捷键支持
    document.addEventListener('keydown', function(e) {
        // ESC键关闭模态框
        if (e.key === 'Escape') {
            closeModal();
        }
        
        // Ctrl+A全选（仅在记录卡片页面上）
        if (e.ctrlKey && e.key === 'a' && document.querySelector('.cards-grid')) {
            e.preventDefault();
            const selectAllCheckbox = document.getElementById('select-all');
            if (selectAllCheckbox) {
                selectAllCheckbox.checked = !selectAllCheckbox.checked;
                toggleSelectAll();
            }
        }
    });

    // 添加滚动到顶部按钮
    const scrollToTopBtn = document.createElement('button');
    scrollToTopBtn.innerHTML = `
        <svg class="btn-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M10 17a.75.75 0 0 1-.75-.75V5.612L5.29 9.77a.75.75 0 0 1-1.08-1.04l5.25-5.5a.75.75 0 0 1 1.08 0l5.25 5.5a.75.75 0 1 1-1.08 1.04l-3.96-4.158V16.25A.75.75 0 0 1 10 17Z" clip-rule="evenodd" />
        </svg>
    `;
    scrollToTopBtn.style.cssText = `
        position: fixed;
        bottom: 2rem;
        right: 2rem;
        width: 3rem;
        height: 3rem;
        border-radius: 50%;
        background-color: var(--black);
        color: var(--white);
        border: none;
        cursor: pointer;
        display: none;
        align-items: center;
        justify-content: center;
        box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        z-index: 100;
        transition: opacity 0.3s;
    `;

    scrollToTopBtn.addEventListener('click', function() {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });

    document.body.appendChild(scrollToTopBtn);

    // 监听滚动显示/隐藏按钮
    window.addEventListener('scroll', function() {
        if (window.scrollY > 300) {
            scrollToTopBtn.style.display = 'flex';
        } else {
            scrollToTopBtn.style.display = 'none';
        }
    });
    </script>
</body>
</html>