<?php
if (!defined('FROM_ROUTER')) {
    http_response_code(403);
    exit('喜乐科技@x60898');
}
// 引入数据库配置文件
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/dbconfig.php';

// 检查登录状态
checkLogin();
// 获取当前域名和协议
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$base_url = $protocol . $_SERVER['HTTP_HOST'];

// 获取数据库连接
$conn = getDB();
if (!$conn) {
    die("数据库连接失败");
}

// 获取当前用户ID从session中
$user_id = $_SESSION['user_id'];

// 处理表单提交（添加/编辑付款页）
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';
    $id = $_POST['id'] ?? 0;
    
    if ($action === 'save_payment_page') {
        $page_title = $conn->real_escape_string($_POST['page_title']);
        $amount = floatval($_POST['amount']);
        $api_url = $conn->real_escape_string($_POST['api_url']);
        $payment_method = $conn->real_escape_string($_POST['payment_method']);
        $status = $conn->real_escape_string($_POST['status']);
        
        if ($id > 0) {
            // 编辑现有付款页
            $sql = "UPDATE payment_pages SET 
                    page_title = ?, 
                    amount = ?, 
                    api_url = ?, 
                    payment_method = ?, 
                    status = ? 
                    WHERE id = ? AND user_id = ?";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sdsssii", $page_title, $amount, $api_url, $payment_method, $status, $id, $user_id);
        } else {
            // 添加新付款页
            // 生成唯一的页面代码
            $page_code = substr(md5(uniqid(rand(), true)), 0, 10);
            
            $sql = "INSERT INTO payment_pages (user_id, page_title, amount, api_url, payment_method, status, page_code) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isdssss", $user_id, $page_title, $amount, $api_url, $payment_method, $status, $page_code);
        }
        
        if ($stmt->execute()) {
            $success_message = $id > 0 ? "付款页更新成功！" : "付款页添加成功！";
        } else {
            $error_message = "操作失败: " . $stmt->error;
        }
        
        if (isset($stmt)) {
            $stmt->close();
        }
    }
}

// 查询当前用户的所有付款页
$sql = "SELECT id, page_title, amount, api_url, payment_method, status, page_code, created_at, updated_at 
        FROM payment_pages 
        WHERE user_id = ? 
        ORDER BY created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$payment_pages = [];

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $payment_pages[] = $row;
    }
}

$stmt->close();

// 读取源站URL配置
$siteUrlResult = $conn->query("SELECT site_url, site_url_enabled FROM webconfig ORDER BY id DESC LIMIT 1");
$siteUrlConfig = null;
if ($siteUrlResult && $siteUrlResult->num_rows > 0) {
    $siteUrlConfig = $siteUrlResult->fetch_assoc();
    $siteUrlConfig['site_url_enabled'] = !empty($siteUrlConfig['site_url_enabled']);
}

// 获取单个付款页信息（用于编辑）
$edit_page = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $sql = "SELECT * FROM payment_pages WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $edit_id, $user_id);
    $stmt->execute();
    $edit_result = $stmt->get_result();
    
    if ($edit_result->num_rows > 0) {
        $edit_page = $edit_result->fetch_assoc();
    }
    
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <!-- PWA meta tags -->
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="客服聊天">
<link rel="apple-touch-icon" href="/assets/img/icon-192.png">
<link rel="manifest" href="/manifest.php">
<meta name="theme-color" content="#f7f8fa">
    <title>官方客服</title>

	<link rel="icon" type="image/x-icon" href="/favicon.png">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="/assets/top_bar.css">
    <link rel="stylesheet" href="/assets/bootstrap-icons.css">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
        }
        
        body {
            background-color: #f0f2f5;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'PingFang SC', 'Microsoft YaHei', sans-serif;
            color: #333;
            line-height: 1.5;
        }
        
        /* 悬浮返回按钮 */
        .floating-back-btn {
            position: fixed;
            top: 20px;
            left: 20px;
            background-color: #fff;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            z-index: 100;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .floating-back-btn:hover {
            background-color: #f5f5f5;
            transform: scale(1.05);
        }
        
        .floating-back-btn:active {
            transform: scale(0.95);
        }
        
        .floating-back-btn i {
            font-size: 20px;
            color: #333;
        }
        
        /* 主容器 */
        .container {
            max-width: 100%;
            padding: 20px;
        }
        
        /* 添加按钮 - 扁平化 */
        .add-btn-container {
            margin-bottom: 20px;
        }
        
        .add-btn {
            background-color: #1890ff;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 14px;
            font-size: 16px;
            font-weight: 500;
            width: 100%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background-color 0.2s ease;
        }
        
        .add-btn:hover {
            background-color: #40a9ff;
        }
        
        .add-btn:active {
            background-color: #096dd9;
        }
        
        .add-btn i {
            margin-right: 8px;
            font-size: 18px;
        }
        
        /* 付款卡片列表 */
        .payment-list {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        
        /* 付款卡片 - 扁平化 */
        .payment-card {
            background-color: #ffffff;
            border-radius: 8px;
            padding: 20px;
            border: 1px solid #e8e8e8;
            animation: cardFadeIn 0.4s ease-out;
        }
        
        /* 卡片入场动画 */
        @keyframes cardFadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* 订单信息行 */
        .order-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .order-label {
            font-size: 14px;
            color: #666;
        }
        
        .order-value {
            font-size: 14px;
            color: #333;
            font-weight: 500;
        }
        
        .order-number {
            font-weight: 600;
            color: #333;
        }
        
        /* 已过期标签 */
        .expired-label {
            font-size: 14px;
            color: #666;
        }
        
        .expired-badge {
            color: #ff4d4f;
            font-size: 13px;
            font-weight: 600;
        }
        
        .amount-label {
            font-size: 14px;
            color: #666;
        }
        
        .amount {
            font-size: 24px;
            font-weight: 700;
            color: #333;
        }
        
        .method-label {
            font-size: 14px;
            color: #666;
        }
        
        .payment-method-value {
            color: #333;
            font-weight: 500;
        }
        
        /* 按钮组 - 水平排列 */
        .button-group {
            display: flex;
            gap: 10px;
        }
        
        .card-btn {
            flex: 1;
            padding: 10px 0;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .card-btn i {
            margin-right: 5px;
            font-size: 14px;
        }
        
        .btn-update {
            background-color: #27ae60;
            color: white;
        }
        
        .btn-update:hover {
            background-color: #73d13d;
        }
        
        .btn-update:active {
            background-color: #389e0d;
        }
        
        .btn-copy-link {
            background-color: #555;
            color: white;
        }
        
        .btn-copy-link:hover {
            background-color: #9254de;
        }
        
        .btn-copy-link:active {
            background-color: #531dab;
        }
        
        /* 新通知样式 - 从顶部进入 */
        .new-user-toast {
            position: fixed;
            top: 10px;
            left: 10px;
            right: 10px;
            background: #4caf50;
            border-radius: 8px;
            padding: 12px 16px;
            display: flex;
            align-items: center;
            z-index: 99999;
            box-shadow: 0 4px 12px rgba(0,0,0,.15);
            cursor: pointer;
            will-change: transform,opacity;
            backface-visibility: hidden;
            -webkit-backface-visibility: hidden;
            max-width: calc(100% - 20px);
            margin: 0 auto;
        }
        
        .toast-icon {
            width: 24px;
            height: 24px;
            background: #fff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
            flex-shrink: 0;
            font-size: 16px;
            color: #4caf50;
            font-weight: bold;
        }
        
        .toast-text {
            flex: 1;
            color: #fff;
            font-size: 15px;
            font-weight: 500;
            line-height: 1.4;
        }
        
        .toast-close {
            width: 24px;
            height: 24px;
            background: transparent;
            border: none;
            color: #fff;
            font-size: 20px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: .8;
            flex-shrink: 0;
            transition: opacity .15s ease,transform .15s ease;
            padding: 0;
            margin-left: 8px;
        }
        
        .toast-close:hover {
            opacity: 1;
        }
        
        .toast-close:active {
            transform: scale(.85);
        }
        
        /* 通知进入和离开动画 - 从顶部进入 */
        .notification-enter-active {
            animation: notification-slide-in 0.3s ease-out;
        }
        
        .notification-leave-active {
            animation: notification-slide-out 0.3s ease-in forwards;
        }
        
        @keyframes notification-slide-in {
            0% {
                transform: translateY(-100px);
                opacity: 0;
            }
            100% {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        @keyframes notification-slide-out {
            0% {
                transform: translateY(0);
                opacity: 1;
            }
            100% {
                transform: translateY(-100px);
                opacity: 0;
            }
        }
        
        /* 暗色模式支持 */
        @media (prefers-color-scheme: dark) {
            .new-user-toast {
                box-shadow: 0 4px 12px rgba(0,0,0,.3);
            }
            
            .toast-text {
                color: #ffffff;
            }
        }
        
        /* 多行文本支持 */
        .toast-text.multiline {
            line-height: 1.3;
            max-height: 60px;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
        }
        
        /* 通知类型样式 */
        .toast-success {
            background: #4caf50;
        }
        
        .toast-info {
            background: #2196f3;
        }
        
        .toast-warning {
            background: #ff9800;
        }
        
        .toast-error {
            background: #f44336;
        }
        
        .toast-success .toast-icon {
            color: #4caf50;
        }
        
        .toast-info .toast-icon {
            color: #2196f3;
        }
        
        .toast-warning .toast-icon {
            color: #ff9800;
        }
        
        .toast-error .toast-icon {
            color: #f44336;
        }
        
        /* 隐藏状态 */
        .toast-hidden {
            display: none;
        }
        
        /* 空状态 */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            animation: fadeIn 0.6s ease-out;
        }
        
        .empty-icon {
            font-size: 60px;
            color: #d9d9d9;
            margin-bottom: 20px;
        }
        
        .empty-title {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin-bottom: 10px;
        }
        
        .empty-text {
            font-size: 14px;
            color: #666;
            margin-bottom: 30px;
        }
        
        /* 抽屉样式 - 从下往上弹出 */
        .drawer-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .drawer-overlay.show {
            display: block;
            opacity: 1;
        }
        
        .drawer-content {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            background-color: white;
            border-radius: 16px 16px 0 0;
            max-height: 85vh;
            overflow-y: auto;
            transform: translateY(100%);
            transition: transform 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            display: flex;
            flex-direction: column;
        }
        
        .drawer-overlay.show .drawer-content {
            transform: translateY(0);
        }
        
        .drawer-header {
            padding: 20px 20px 10px 20px;
            border-bottom: 1px solid #f0f0f0;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .drawer-title {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            text-align: center;
        }
        
        .drawer-body {
            padding: 20px;
            flex: 1;
            overflow-y: auto;
        }
        
        .drawer-footer {
            padding: 20px;
            border-top: 1px solid #f0f0f0;
            display: flex;
            gap: 12px;
        }
        
        .drawer-close-btn {
            position: absolute;
            right: 20px;
            top: 20px;
            background: none;
            border: none;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }
        
        .drawer-close-btn:hover {
            background-color: #f5f5f5;
        }
        
        .drawer-close-btn i {
            font-size: 20px;
            color: #666;
        }
        
        /* 表单样式 */
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }
        
        .form-input {
            width: 100%;
            padding: 12px;
            border: 1px solid #d9d9d9;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.2s ease;
            background-color: #fafafa;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #1890ff;
            background-color: #fff;
        }
        
        .form-select {
            width: 100%;
            padding: 12px;
            border: 1px solid #d9d9d9;
            border-radius: 8px;
            font-size: 16px;
            background-color: #fafafa;
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23333' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 16px;
        }
        
        .form-select:focus {
            outline: none;
            border-color: #1890ff;
            background-color: #fff;
        }
        
        .form-text {
            font-size: 12px;
            color: #999;
            margin-top: 5px;
        }
        
        /* 按钮样式 */
        .btn {
            flex: 1;
            padding: 16px 24px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .btn-secondary {
            background-color: #f5f5f5;
            color: #333;
        }
        
        .btn-secondary:hover {
            background-color: #e8e8e8;
        }
        
        .btn-primary {
            background-color: #1890ff;
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #40a9ff;
        }
        
        /* 状态行样式 */
        .status-row {
            margin-bottom: 20px;
        }
        
        .status-label {
            font-size: 14px;
            color: #666;
        }
        
        .status-value {
            color: #333;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .status-active {
            color: #52c41a;
        }
        
        .status-inactive {
            color: #ff4d4f;
        }
        
        .status-value i {
            font-size: 14px;
        }
        
        /* 动画定义 */
        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }
        
        /* 响应式调整 */
        @media (max-width: 480px) {
            .container {
                padding: 15px;
            }
            
            .payment-card {
                padding: 15px;
            }
            
            .button-group {
                flex-direction: row;
            }
            
            .card-btn {
                font-size: 13px;
                padding: 8px 0;
            }
            
            .card-btn i {
                font-size: 12px;
                margin-right: 3px;
            }
            
            .drawer-content {
                max-height: 80vh;
            }
            
            .drawer-header {
                padding: 16px 16px 8px 16px;
            }
            
            .drawer-body {
                padding: 16px;
            }
            
            .drawer-footer {
                padding: 16px;
            }
            
            .btn {
                padding: 14px 20px;
                font-size: 15px;
            }
        }
        
        @media (max-width: 360px) {
            .button-group {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
<div class="top-header">
	<a href="javascript:void(0)" onclick="window.parent.postMessage('closeModal', '*')" style="display: inline-flex; align-items: center; text-decoration: none; color: inherit;">
		<svg t="1768667202128" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="4699" width="18" height="18">
			<path
				d="M285.8112 565.76a56.4864 56.4864 0 0 0 39.04-16.3712l452.7744-453.76A56.5248 56.5248 0 0 0 778.24 16.64a54.8992 54.8992 0 0 0-78.08-0.5632L247.3344 469.76a56.5248 56.5248 0 0 0-0.5504 79.0144 50.048 50.048 0 0 0 39.0272 16.9344zM733.568 1024a56.1664 56.1664 0 0 0 39.6032-95.3856l-448.32-458.24a54.912 54.912 0 0 0-78.08-0.5632 56.5248 56.5248 0 0 0-0.5632 79.0144l448.32 458.24A53.76 53.76 0 0 0 733.568 1024z m0 0"
				fill="#333333" p-id="4700"></path>
		</svg>
	</a>
	<div style="border: 14px solid transparent;">付款页管理</div>
</div>

<!-- 防红/源站URL配置状态显示 -->
<div class="anti-red-status-bar" id="antiRedStatusBar" style="display: none; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 12px 20px; margin: 10px 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
    <div style="display: flex; align-items: center; gap: 10px;">
        <i class="bi bi-shield-check" style="font-size: 20px;"></i>
        <div>
            <div style="font-weight: 600; font-size: 14px;">防红配置已启用</div>
            <div style="font-size: 12px; opacity: 0.9; margin-top: 2px;">
                当前配置接口：<span id="currentAntiRedDomain" style="font-weight: 500;"></span>
            </div>
        </div>
    </div>
</div>
    <!-- 主容器 -->
    <div class="container">
        <!-- 添加按钮 -->
        <div class="add-btn-container">
            <button class="add-btn" onclick="openDrawer()">
                <i class="bi bi-plus-lg"></i> 添加新的付款页
            </button>
        </div>
        
        <!-- 付款页列表 -->
        <?php if (count($payment_pages) > 0): ?>
        <div class="payment-list">
            <?php foreach ($payment_pages as $page): 
                 // 使用动态域名生成付款链接
                $payment_url = $base_url . "/pay/?code=" . $page['page_code'];
                
                // 获取支付方式显示文本
                $payment_method_text = "";
                switch($page['payment_method']) {
                    case 'alipay':
                        $payment_method_text = "支付宝";
                        break;
                    case 'wechat':
                        $payment_method_text = "微信支付";
                        break;
                    case 'bank':
                        $payment_method_text = "银行转账";
                        break;
                    default:
                        $payment_method_text = "在线支付";
                }
                
            ?>
            <div class="payment-card" id="payment-card-<?php echo $page['id']; ?>">
                <!-- 订单编号 -->
                <div class="order-row">
                    <div>
                        <span class="order-label">订单编号：</span>
                        <span class="order-value order-number"><?php echo $page['page_code']; ?></span>
                    </div>
                </div>
                
                <!-- 金额 -->
                <div class="amount-row">
                    <span class="amount-label">金额：</span>
                    <span class="order-value amount">¥<?php echo number_format($page['amount'], 2); ?></span>
                </div>
                
                <!-- 支付方式 -->
                <div class="method-row">
                    <span class="method-label">支付方式：</span>
                    <span class="order-value payment-method-value"><?php echo $payment_method_text; ?></span>
                </div>
                
         <!-- 状态行 -->
<div class="row" style="display: flex; align-items: center; margin-bottom: 20px;">
    <span class="status-label" style="min-width: 80px;">页面状态：</span>
    <span class="order-value status-value <?php echo $page['status'] === 'active' ? 'status-active' : 'status-inactive'; ?>">
        <?php 
        if($page['status'] === 'active') {
            echo '<i class="bi bi-check-circle-fill"></i> 正常';
        } else {
            echo '<i class="bi bi-x-circle-fill"></i> 未开启';
        }
        ?>
    </span>
</div>
                
                <!-- 按钮组 - 水平排列 -->
                <div class="button-group">
                    <button class="card-btn btn-update" data-id="<?php echo $page['id']; ?>" data-title="<?php echo htmlspecialchars($page['page_title']); ?>" data-amount="<?php echo $page['amount']; ?>" data-api-url="<?php echo htmlspecialchars($page['api_url']); ?>" data-method="<?php echo $page['payment_method']; ?>" data-status="<?php echo $page['status']; ?>">
                        <i class="bi bi-pencil-square"></i> 更新信息
                    </button>

                    <button class="card-btn btn-copy-link" onclick="copyPaymentLink('<?php echo $payment_url; ?>')">
                        <i class="bi bi-link-45deg"></i> 复制链接
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <!-- 空状态 -->
        <div class="empty-state">
            <div class="empty-icon">
                <i class="bi bi-credit-card"></i>
            </div>
            <h3 class="empty-title">暂无付款页</h3>
            <p class="empty-text">数据为空</p>
            
        </div>
        <?php endif; ?>
    </div>
    
    <!-- 添加/编辑付款页抽屉 -->
    <div class="drawer-overlay" id="paymentDrawer">
        <div class="drawer-content">
            <form method="POST" action="" id="paymentForm">
                <div class="drawer-header">
                    <div class="drawer-title" id="drawerTitle">添加付款页</div>
                    <button type="button" class="drawer-close-btn" onclick="closeDrawer()">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <div class="drawer-body">
                    <input type="hidden" name="action" value="save_payment_page">
                    <input type="hidden" name="id" id="paymentId" value="0">
                    
                    <div class="form-group">
                        <label for="page_title" class="form-label">付款页标题</label>
                        <input type="text" class="form-input" id="page_title" name="page_title" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="amount" class="form-label">付款金额</label>
                        <input type="number" class="form-input" id="amount" name="amount" step="0.01" min="0.01" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="api_url" class="form-label">支付接口地址</label>
                        <input type="url" class="form-input" id="api_url" name="api_url" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="payment_method" class="form-label">支付方式</label>
                        <select class="form-select" id="payment_method" name="payment_method" required>
                            <option value="alipay">支付宝</option>
                            <option value="wechat">微信支付</option>
                            <option value="bank">银行转账</option>
                            <option value="other">在线支付</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="status" class="form-label">付款页状态</label>
                        <select class="form-select" id="status" name="status">
                            <option value="active">开启</option>
                            <option value="inactive">关闭</option>
                        </select>
                        <div class="form-text">激活状态才能被访问</div>
                    </div>
                </div>
                <div class="drawer-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeDrawer()">取消</button>
                    <button type="submit" class="btn btn-primary" id="submitBtn">添加付款页</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // 防红配置变量
        let userAntiRedConfig = null;
        // 源站URL配置
        let siteUrlConfig = <?php echo $siteUrlConfig ? json_encode($siteUrlConfig) : 'null'; ?>;
        
        // 加载防红配置
        async function loadAntiRedConfig() {
            try {
                const response = await fetch('/config/domain_api.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: new URLSearchParams({action: 'get_user_anti_red_config'})
                });
                
                const result = await response.json();
                
                if (result.success && result.config) {
                    userAntiRedConfig = result.config;
                    
                    // 显示防红状态栏
                    const statusBar = document.getElementById('antiRedStatusBar');
                    const domainSpan = document.getElementById('currentAntiRedDomain');
                    
                    if (userAntiRedConfig.apply_status === 'on' && userAntiRedConfig.applied_domain) {
                        domainSpan.textContent = userAntiRedConfig.applied_domain;
                        statusBar.style.display = 'block';
                    } else {
                        statusBar.style.display = 'none';
                    }
                    
                    return userAntiRedConfig;
                }
            } catch (error) {
                console.error('加载防红配置失败:', error);
            }
            return null;
        }
        
        // 生成最终链接（防红 > 源站URL > 原始）
        function generateFinalUrl(originalUrl) {
            if (userAntiRedConfig && userAntiRedConfig.apply_status === 'on' && userAntiRedConfig.api_url) {
                try {
                    let apiUrl = userAntiRedConfig.api_url.replace(/\/+$/, '');
                    const encodedUrl = btoa(originalUrl);
                    return apiUrl + encodedUrl;
                } catch (error) {
                    console.error('生成防红链接失败:', error);
                    return originalUrl;
                }
            } else if (siteUrlConfig && siteUrlConfig.site_url_enabled && siteUrlConfig.site_url) {
                try {
                    return siteUrlConfig.site_url + btoa(unescape(encodeURIComponent(originalUrl)));
                } catch (error) {
                    console.error('生成源站链接失败:', error);
                    return originalUrl;
                }
            }
            return originalUrl;
        }
        
        // 新通知函数
        function showNotification(message, type = 'success', duration = 3000) {
            // 如果已存在通知，先移除
            const existingToast = document.querySelector('.new-user-toast');
            if (existingToast) {
                existingToast.classList.add('notification-leave-active');
                setTimeout(() => {
                    if (existingToast.parentNode) {
                        existingToast.parentNode.removeChild(existingToast);
                    }
                }, 300);
            }
            
            // 创建通知元素
            const toast = document.createElement('div');
            toast.className = `new-user-toast notification-enter-active toast-${type}`;
            
            // 设置图标
            let iconChar = '✓';
            if (type === 'info') iconChar = 'i';
            if (type === 'warning') iconChar = '!';
            if (type === 'error') iconChar = '×';
            
            toast.innerHTML = `
                <div class="toast-icon">${iconChar}</div>
                <div class="toast-text">${message}</div>
                <button class="toast-close">×</button>
            `;
            
            // 添加到页面
            document.body.appendChild(toast);
            
            // 为关闭按钮添加点击事件
            const closeBtn = toast.querySelector('.toast-close');
            closeBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                toast.classList.add('notification-leave-active');
                setTimeout(() => {
                    if (toast.parentNode) {
                        toast.parentNode.removeChild(toast);
                    }
                }, 300);
            });
            
            // 点击整个通知也可以关闭
            toast.addEventListener('click', function(e) {
                if (e.target !== closeBtn) {
                    toast.classList.add('notification-leave-active');
                    setTimeout(() => {
                        if (toast.parentNode) {
                            toast.parentNode.removeChild(toast);
                        }
                    }, 300);
                }
            });
            
            // 自动消失
            if (duration > 0) {
                setTimeout(() => {
                    if (toast.parentNode) {
                        toast.classList.add('notification-leave-active');
                        setTimeout(() => {
                            if (toast.parentNode) {
                                toast.parentNode.removeChild(toast);
                            }
                        }, 300);
                    }
                }, duration);
            }
        }
        
        // 抽屉控制
        function openDrawer() {
            const drawer = document.getElementById('paymentDrawer');
            drawer.classList.add('show');
            // 防止背景滚动
            document.body.style.overflow = 'hidden';
        }
        
        function closeDrawer() {
            const drawer = document.getElementById('paymentDrawer');
            drawer.classList.remove('show');
            // 恢复背景滚动
            document.body.style.overflow = '';
            
            // 动画结束后重置表单
            setTimeout(() => {
                resetDrawer();
            }, 300);
        }
        
        function resetDrawer() {
            document.getElementById('paymentForm').reset();
            document.getElementById('paymentId').value = '0';
            document.getElementById('drawerTitle').textContent = '添加付款页';
            document.getElementById('submitBtn').textContent = '添加付款页';
            
            // 清除URL中的edit参数
            const url = new URL(window.location);
            if (url.searchParams.has('edit')) {
                url.searchParams.delete('edit');
                window.history.replaceState({}, '', url.toString());
            }
        }
        
       // 复制付款链接 - 支持防红和源站URL
    function copyPaymentLink(url) {
        // 生成最终链接（防红 > 源站URL > 原始）
        const finalUrl = generateFinalUrl(url);
        console.log('复制链接:', finalUrl);
        
        // 使用现代 API 复制
        if (navigator.clipboard) {
            navigator.clipboard.writeText(finalUrl).then(() => {
                console.log('复制成功');
                showNotification('付款链接已复制', 'success');
            }).catch(err => {
                console.error('复制失败:', err);
                // 降级方案
                fallbackCopy(finalUrl);
            });
        } else {
            // 降级方案
            fallbackCopy(finalUrl);
        }
    }
    
    // 降级复制方法
    function fallbackCopy(text) {
        const tempInput = document.createElement('input');
        tempInput.value = text;
        tempInput.style.position = 'fixed';
        tempInput.style.opacity = '0';
        tempInput.style.left = '-9999px';
        document.body.appendChild(tempInput);
        tempInput.select();
        
        try {
            document.execCommand('copy');
            showNotification('付款链接已复制', 'success');
        } catch (err) {
            console.error('降级复制失败:', err);
            showNotification('复制失败，请手动复制', 'warning');
        }
        
        document.body.removeChild(tempInput);
    }
        
        // 页面加载完成后的初始化
        document.addEventListener('DOMContentLoaded', function() {
            // 加载防红配置
            loadAntiRedConfig();
            
            // 为所有"更新信息"按钮添加事件监听
            const updateButtons = document.querySelectorAll('.btn-update');
            updateButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation(); // 防止事件冒泡
                    
                    // 获取数据属性
                    const id = this.getAttribute('data-id');
                    const title = this.getAttribute('data-title');
                    const amount = this.getAttribute('data-amount');
                    const apiUrl = this.getAttribute('data-api-url');
                    const method = this.getAttribute('data-method');
                    const status = this.getAttribute('data-status');
                    
                    // 设置编辑模式
                    document.getElementById('drawerTitle').textContent = '编辑付款页';
                    document.getElementById('submitBtn').textContent = '更新付款页';
                    document.getElementById('paymentId').value = id;
                    
                    // 填充表单数据
                    document.getElementById('page_title').value = title;
                    document.getElementById('amount').value = amount;
                    document.getElementById('api_url').value = apiUrl;
                    document.getElementById('payment_method').value = method;
                    document.getElementById('status').value = status;
                    
                    // 打开抽屉
                    openDrawer();
                });
            });
            
            // 表单验证
            document.getElementById('paymentForm').addEventListener('submit', function(e) {
                const amount = parseFloat(document.getElementById('amount').value);
                if (isNaN(amount) || amount <= 0) {
                    e.preventDefault();
                    showNotification('付款金额必须大于0', 'info');
                    return false;
                }
                
                const apiUrl = document.getElementById('api_url').value;
                if (!isValidUrl(apiUrl)) {
                    e.preventDefault();
                    showNotification('请输入有效的URL地址', 'info');
                    return false;
                }
            });
            
            // 点击抽屉外部关闭
            document.getElementById('paymentDrawer').addEventListener('click', function(e) {
                if (e.target === this) {
                    closeDrawer();
                }
            });
            
            // 为所有卡片添加悬停效果
            const cards = document.querySelectorAll('.payment-card');
            cards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-4px)';
                    this.style.boxShadow = '0 4px 12px rgba(0, 0, 0, 0.1)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                    this.style.boxShadow = 'none';
                });
            });
            
            // 为所有按钮添加点击效果
            const buttons = document.querySelectorAll('.card-btn, .btn, .add-btn');
            buttons.forEach(button => {
                button.addEventListener('mousedown', function() {
                    this.style.transform = 'scale(0.98)';
                });
                
                button.addEventListener('mouseup', function() {
                    this.style.transform = 'scale(1)';
                });
                
                button.addEventListener('mouseleave', function() {
                    this.style.transform = 'scale(1)';
                });
            });
            
            // 页面加载时检查是否有消息需要显示
            <?php if (isset($success_message)): ?>
            setTimeout(() => {
                showNotification('<?php echo $success_message; ?>', 'success');
            }, 300);
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
            setTimeout(() => {
                showNotification('<?php echo $error_message; ?>', 'error');
            }, 300);
            <?php endif; ?>
        });
        
        function isValidUrl(string) {
            try {
                new URL(string);
                return true;
            } catch (_) {
                return false;
            }
        }
        
        // 添加键盘快捷键支持
        document.addEventListener('keydown', function(e) {
            // ESC键关闭抽屉
            if (e.key === 'Escape') {
                closeDrawer();
            }
        });
        
        // 防止抽屉内的滚动传播到背景
        document.querySelector('.drawer-content').addEventListener('scroll', function(e) {
            e.stopPropagation();
        });
    </script>
</body>
</html>

<?php
// 关闭数据库连接
if (isset($conn)) {
    $conn->close();
}