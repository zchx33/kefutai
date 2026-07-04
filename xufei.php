<?php
if (!defined('FROM_ROUTER')) {
    http_response_code(403);
    exit('喜乐科技@x60898');
}
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/dbconfig.php';
checkLogin();
requireFeatureAccess('xufei');

// 获取当前用户信息
$username = $_SESSION['username'];
$balance = $_SESSION['balance'];
$expire_time = $_SESSION['expire_time'];
$expire_days = $_SESSION['expire_days'];

// 处理续费请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['renew_days'])) {
    $renew_days = intval($_POST['renew_days']);
    $price_per_day = 3; // 每天3元
    $total_cost = $renew_days * $price_per_day;
    
    if ($renew_days <= 0) {
        $error = '请选择有效的续费天数';
    } elseif ($balance < $total_cost) {
        $error = '余额不足，请先充值。当前余额：' . $balance . '元，需要：' . $total_cost . '元';
    } else {
        $db = getDB();
        if (!$db) {
            $error = '数据库连接失败';
        } else {
            // 计算新的过期时间
            $current_time = time();
            $current_expire = $expire_time ? strtotime($expire_time) : $current_time;
            $new_expire = max($current_expire, $current_time) + ($renew_days * 24 * 60 * 60);
            $new_expire_date = date('Y-m-d H:i:s', $new_expire);
            
            // 更新用户余额和过期时间
            $new_balance = $balance - $total_cost;
            $stmt = $db->prepare("UPDATE users SET balance = ?, expire_time = ? WHERE username = ?");
            if (!$stmt) {
                $error = '系统错误，请重试';
            } else {
                $stmt->bind_param("dss", $new_balance, $new_expire_date, $username);
                if ($stmt->execute()) {
                    // 更新session
                    $_SESSION['balance'] = $new_balance;
                    $_SESSION['expire_time'] = $new_expire_date;
                    $_SESSION['expire_days'] = ceil(($new_expire - $current_time) / (60 * 60 * 24));
                    
                    $success = '续费成功！花费 ' . $total_cost . ' 元';
                } else {
                    $error = '续费失败，请重试';
                }
            }
        }
    }
}

// 套餐配置
$packages = [
    ['days' => 1, 'cost' => 3, 'label' => '体验'],
    ['days' => 7, 'cost' => 21, 'label' => '周卡'],
    ['days' => 15, 'cost' => 45, 'label' => '半月'],
    ['days' => 30, 'cost' => 90, 'label' => '月卡', 'recommended' => true],
    ['days' => 90, 'cost' => 270, 'label' => '季卡'],
    ['days' => 365, 'cost' => 1095, 'label' => '年卡']
];

// 默认选择30天套餐
$selected_days = 30;
$selected_cost = 90;
$price_per_day = 3;
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
	<meta charset="UTF-8">
   <!-- PWA meta tags -->
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-stylecontent="black-translucent">
<meta name="apple-mobile-web-app-title" content="客服聊天">
<link rel="apple-touch-icon" href="/assets/img/icon-192.png">
<link rel="manifest" href="/manifest.php">
<meta name="theme-color" content="#f7f8fa">
	<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">

<title>官方客服</title>
	<link rel="icon" type="image/x-icon" href="/favicon.png">
	<!-- 引入Bootstrap图标 -->
	<link rel="stylesheet" href="/assets/top_bar.css">
	<link rel="stylesheet" href="/assets/bootstrap-icons.css">
	<style type="text/css">
		/* Apple风格全局样式 */
		* {
		    margin: 0;
		    padding: 0;
		    box-sizing: border-box;
		    -webkit-tap-highlight-color: transparent;
		    /* 全局隐藏滚动条但保留滚动功能 */
		    -ms-overflow-style: none; /* IE 和 Edge */
		    scrollbar-width: none; /* Firefox */
		}
		body {
		    background: linear-gradient(135deg, #f5f7fa 0%, #e4e8f0 100%);
		    font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Text', 'SF Pro Display', 'Helvetica Neue', Arial, sans-serif;
		    min-height: 100vh;
		    color: #1d1d1f;
		    line-height: 1.47059;
		    font-weight: 400;
		    letter-spacing: -0.022em;
		    padding: 0;
		    /* 隐藏滚动条但保留滚动功能 */
		    -ms-overflow-style: none; /* IE 和 Edge */
		    scrollbar-width: none; /* Firefox */
		}
		
		/* 隐藏Chrome/Safari滚动条 */
		body::-webkit-scrollbar {
		    display: none;
		}
		
		/* 容器样式 */
		.container {
		    max-width: 680px;
		    margin: 0 auto;
		    padding: 16px 16px 90px; /* 底部留出90px的空间给固定按钮 */
		    display: flex;
		    flex-direction: column;
		    min-height: 100vh;
		    position: relative;
		    /* 隐藏滚动条但保留滚动功能 */
		    -ms-overflow-style: none; /* IE 和 Edge */
		    scrollbar-width: none; /* Firefox */
		}
		
		/* 隐藏Chrome/Safari滚动条 */
		.container::-webkit-scrollbar {
		    display: none;
		}
		
		/* 头部导航 */
		.header {
		    display: flex;
		    align-items: center;
		    padding: 12px 0;
		    margin-bottom: 8px;
		    flex-shrink: 0;
		}
		
		.back-button {
		    width: 40px;
		    height: 40px;
		    border-radius: 50%;
		    display: flex;
		    align-items: center;
		    justify-content: center;
		    background: rgba(255, 255, 255, 0.8);
		    backdrop-filter: blur(20px);
		    border: 1px solid rgba(255, 255, 255, 0.2);
		    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
		    cursor: pointer;
		    transition: all 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
		}
		
		.back-button:hover {
		    transform: scale(1.05);
		    background: rgba(255, 255, 255, 0.9);
		}
		
		.header-title {
		    flex: 1;
		    text-align: center;
		    font-size: 24px;
		    font-weight: 700;
		    color: #1d1d1f;
		    letter-spacing: -0.028em;
		}
		
		/* 主要内容区域 */
		.main-content {
		    flex: 1;
		    display: flex;
		    flex-direction: column;
		    gap: 16px;
		    overflow-y: auto;
		    padding-bottom: 20px; /* 为按钮留出一些内边距 */
		    /* 隐藏滚动条但保留滚动功能 */
		    -ms-overflow-style: none; /* IE 和 Edge */
		    scrollbar-width: none; /* Firefox */
		}
		
		/* 隐藏Chrome/Safari滚动条 */
		.main-content::-webkit-scrollbar {
		    display: none;
		}
		
		/* 卡片样式 */
		.card {
		    background: rgba(255, 255, 255, 0.8);
		    backdrop-filter: blur(20px);
		    border-radius: 16px;
		    border: 1px solid rgba(255, 255, 255, 0.2);
		    overflow: hidden;
		    flex-shrink: 0;
		}
		
		.card-header {
		    padding: 20px;
		    border-bottom: 1px solid rgba(60, 60, 67, 0.1);
		    background: rgba(255, 255, 255, 0.6);
		}
		
		.card-title {
		    font-size: 20px;
		    font-weight: 700;
		    color: #1d1d1f;
		    margin-bottom: 4px;
		}
		
		.card-subtitle {
		    font-size: 15px;
		    color: #86868b;
		    font-weight: 400;
		}
		
		.card-content {
		    padding: 20px;
		}
		
		/* 用户信息样式 */
		.user-info {
		    display: grid;
		    grid-template-columns: 1fr 1fr;
		    gap: 12px;
		}
		
		.info-item {
		    display: flex;
		    flex-direction: column;
		}
		
		.info-label {
		    font-size: 14px;
		    color: #86868b;
		    margin-bottom: 4px;
		    font-weight: 500;
		}
		
		.info-value {
		    font-size: 16px;
		    font-weight: 600;
		    color: #1d1d1f;
		}
		
		.balance-value {
		    color: #0071e3;
		}
		
		/* 九宫格套餐样式 - 严格3列布局 */
		.packages-grid {
		    display: grid;
		    grid-template-columns: repeat(3, 1fr);
		    gap: 12px;
		    margin: 8px 0;
		    width: 100%;
		}
		
		.package-item {
		    background: rgba(255, 255, 255, 0.6);
		    border: 2px solid rgba(60, 60, 67, 0.1);
		    border-radius: 12px;
		    padding: 16px 12px;
		    cursor: pointer;
		    transition: all 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
		    position: relative;
		    text-align: center;
		    aspect-ratio: 1/1;
		    display: flex;
		    flex-direction: column;
		    justify-content: center;
		    align-items: center;
		    min-height: 100px;
		}
		
		.package-item:hover {
		    transform: translateY(-2px);
		    border-color: rgba(0, 113, 227, 0.3);
		    box-shadow: 0 8px 24px rgba(0, 113, 227, 0.15);
		}
		
		.package-item.selected {
		    background: rgba(0, 113, 227, 0.1);
		    border-color: #0071e3;
		    box-shadow: 0 8px 24px rgba(0, 113, 227, 0.2);
		}
		
		.package-days {
		    font-size: 18px;
		    font-weight: 700;
		    color: #1d1d1f;
		    margin-bottom: 4px;
		}
		
		.package-label {
		    font-size: 13px;
		    color: #86868b;
		    margin-bottom: 6px;
		    font-weight: 500;
		}
		
		.package-cost {
		    font-size: 16px;
		    font-weight: 700;
		    color: #0071e3;
		}
		
		.recommended-badge {
		    position: absolute;
		    top: -6px;
		    right: -6px;
		    background: linear-gradient(135deg, #ff2d91 0%, #ff5e3a 100%);
		    color: white;
		    padding: 4px 6px;
		    border-radius: 10px;
		    font-size: 10px;
		    font-weight: 600;
		    box-shadow: 0 4px 12px rgba(255, 45, 145, 0.4);
		    line-height: 1;
		}
		
		/* 价格汇总 */
		.price-summary {
		    background: rgba(245, 245, 247, 0.8);
		    border-radius: 14px;
		    padding: 18px;
		    margin: 20px 0;
		    text-align: center;
		}
		
		.price-line {
		    font-size: 16px;
		    color: #86868b;
		    margin-bottom: 6px;
		}
		
		.price-total {
		    font-size: 28px;
		    font-weight: 700;
		    color: #0071e3;
		    margin: 6px 0;
		}
		
		.price-per-day {
		    font-size: 14px;
		    color: #86868b;
		}
		
		/* 支付按钮 - 固定在屏幕底部 */
		.payment-button {
		    position: fixed;
		    left: 0;
		    bottom: 0;
		    width: 100%;
		    background: linear-gradient(135deg, #0071e3 0%, #2997ff 100%);
		    border: none;
		    color: white;
		    font-size: 18px;
		    font-weight: 600;
		    cursor: pointer;
		    transition: all 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
		    box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.1);
		    z-index: 100;
		    border-radius: 0;
		    padding: 20px 24px;
		    min-height: 70px;
		    display: flex;
		    align-items: center;
		    justify-content: center;
		}
		
		.payment-button:hover {
		    background: linear-gradient(135deg, #0066cc 0%, #1e8eff 100%);
		    box-shadow: 0 -4px 25px rgba(0, 113, 227, 0.3);
		}
		
		.payment-button:active {
		    background: linear-gradient(135deg, #005bb5 0%, #1a7fe6 100%);
		}
		
		/* 为按钮添加安全区域适配（针对iPhone X及以上机型） */
		@supports (padding-bottom: env(safe-area-inset-bottom)) {
		    .payment-button {
		        padding-bottom: calc(20px + env(safe-area-inset-bottom));
		    }
		}
		
		/* 灵动岛消息提示样式 - 增强版带动画 */
		.dynamic-island {
		    position: fixed;
		    top: 20px;
		    left: 50%;
		    transform: translateX(-50%) scale(0.8);
		    background: rgba(0, 0, 0, 0.95);
		    backdrop-filter: blur(20px);
		    border-radius: 50px;
		    padding: 12px 20px;
		    display: flex;
		    align-items: center;
		    gap: 10px;
		    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
		    border: 1px solid rgba(255, 255, 255, 0.1);
		    z-index: 2000;
		    opacity: 0;
		    transition: all 0.5s cubic-bezier(0.25, 0.46, 0.45, 0.94);
		    max-width: 300px;
		    min-width: 200px;
		    transform-origin: center top;
		}
		
		/* 出现动画 */
		.dynamic-island.appear {
		    animation: islandAppear 0.6s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
		}
		
		/* 消失动画 */
		.dynamic-island.disappear {
		    animation: islandDisappear 0.4s cubic-bezier(0.36, 0, 0.66, -0.56) forwards;
		}
		
		/* 出现动画关键帧 */
		@keyframes islandAppear {
		    0% {
		        opacity: 0;
		        transform: translateX(-50%) scale(0.8) translateY(-20px);
		    }
		    50% {
		        opacity: 1;
		        transform: translateX(-50%) scale(1.05) translateY(5px);
		    }
		    70% {
		        transform: translateX(-50%) scale(0.98) translateY(0);
		    }
		    100% {
		        opacity: 1;
		        transform: translateX(-50%) scale(1) translateY(0);
		    }
		}
		
		/* 消失动画关键帧 */
		@keyframes islandDisappear {
		    0% {
		        opacity: 1;
		        transform: translateX(-50%) scale(1) translateY(0);
		    }
		    30% {
		        transform: translateX(-50%) scale(1.05) translateY(-5px);
		    }
		    100% {
		        opacity: 0;
		        transform: translateX(-50%) scale(0.8) translateY(-20px);
		    }
		}
		
		.dynamic-island .icon {
		    font-size: 20px;
		    display: flex;
		    align-items: center;
		    justify-content: center;
		    animation: iconBounce 0.8s ease-in-out;
		}
		
		.dynamic-island.success .icon {
		    color: #34c759;
		    animation: iconSuccess 0.6s ease-out;
		}
		
		.dynamic-island.error .icon {
		    color: #ff3b30;
		    animation: iconError 0.6s ease-out;
		}
		
		.dynamic-island .message {
		    color: white;
		    font-size: 14px;
		    font-weight: 500;
		    flex: 1;
		    text-align: center;
		    animation: textFadeIn 0.5s ease-out 0.1s both;
		}
		
		/* 图标动画 */
		@keyframes iconBounce {
		    0% { transform: scale(0); }
		    60% { transform: scale(1.1); }
		    80% { transform: scale(0.9); }
		    100% { transform: scale(1); }
		}
		
		@keyframes iconSuccess {
		    0% { transform: scale(0) rotate(-180deg); }
		    60% { transform: scale(1.1) rotate(10deg); }
		    80% { transform: scale(0.9) rotate(-5deg); }
		    100% { transform: scale(1) rotate(0); }
		}
		
		@keyframes iconError {
		    0% { transform: scale(0); }
		    50% { transform: scale(1.2) translateX(5px); }
		    60% { transform: scale(1.1) translateX(-3px); }
		    70% { transform: scale(1.1) translateX(2px); }
		    100% { transform: scale(1) translateX(0); }
		}
		
		/* 文字动画 */
		@keyframes textFadeIn {
		    0% { opacity: 0; transform: translateY(10px); }
		    100% { opacity: 1; transform: translateY(0); }
		}
		
		/* 拟态确认框 */
		.modal-overlay {
		    position: fixed;
		    top: 0;
		    left: 0;
		    right: 0;
		    bottom: 0;
		    background: rgba(0, 0, 0, 0.5);
		    backdrop-filter: blur(20px);
		    display: none;
		    align-items: center;
		    justify-content: center;
		    z-index: 1000;
		    padding: 20px;
		    animation: fadeIn 0.3s ease;
		}
		
		.modal-content {
		    background: rgba(255, 255, 255, 0.9);
		    backdrop-filter: blur(40px);
		    border-radius: 20px;
		    padding: 24px;
		    max-width: 400px;
		    width: 100%;
		    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
		    border: 1px solid rgba(255, 255, 255, 0.2);
		    animation: slideUp 0.3s ease;
		}
		
		.modal-title {
		    font-size: 22px;
		    font-weight: 700;
		    margin-bottom: 14px;
		    text-align: center;
		    color: #1d1d1f;
		}
		
		.modal-details {
		    background: rgba(245, 245, 247, 0.8);
		    border-radius: 12px;
		    padding: 16px;
		    margin: 16px 0;
		}
		
		.modal-detail {
		    display: flex;
		    justify-content: space-between;
		    padding: 6px 0;
		    border-bottom: 1px solid rgba(60, 60, 67, 0.1);
		    font-size: 14px;
		}
		
		.modal-detail:last-child {
		    border-bottom: none;
		}
		
		.modal-buttons {
		    display: grid;
		    grid-template-columns: 1fr 1fr;
		    gap: 10px;
		    margin-top: 20px;
		}
		
		.modal-button {
		    padding: 12px;
		    border: none;
		    border-radius: 12px;
		    font-size: 16px;
		    font-weight: 600;
		    cursor: pointer;
		    transition: all 0.3s ease;
		}
		
		.modal-button-cancel {
		    background: rgba(255, 255, 255, 0.6);
		    border: 1px solid rgba(60, 60, 67, 0.1);
		    color: #1d1d1f;
		}
		
		.modal-button-confirm {
		    background: linear-gradient(135deg, #0071e3 0%, #2997ff 100%);
		    color: white;
		    box-shadow: 0 4px 16px rgba(0, 113, 227, 0.3);
		}
		
		/* 动画 */
		@keyframes fadeIn {
		    from { opacity: 0; }
		    to { opacity: 1; }
		}
		
		@keyframes slideUp {
		    from { 
		        opacity: 0;
		        transform: translateY(20px);
		    }
		    to { 
		        opacity: 1;
		        transform: translateY(0);
		    }
		}
		
		/* 响应式设计 */
		@media (max-width: 480px) {
		    .packages-grid {
		        gap: 8px;
		    }
		    
		    .package-item {
		        padding: 12px 8px;
		        border-radius: 10px;
		        min-height: 90px;
		    }
		    
		    .package-days {
		        font-size: 16px;
		    }
		    
		    .package-label {
		        font-size: 12px;
		    }
		    
		    .package-cost {
		        font-size: 14px;
		    }
		    
		    .price-summary {
		        padding: 16px;
		        margin: 16px 0;
		    }
		    
		    .price-total {
		        font-size: 24px;
		    }
		    
		    .dynamic-island {
		        max-width: 280px;
		        min-width: 180px;
		        padding: 10px 16px;
		    }
		    
		    .container {
		        padding: 12px 12px 85px; /* 移动端稍微减小底部空间 */
		    }
		    
		    .payment-button {
		        font-size: 16px;
		        padding: 18px 20px;
		        min-height: 65px;
		    }
		}
		
		/* 为小屏幕设备进一步优化 */
		@media (max-height: 700px) {
		    .container {
		        padding-bottom: 80px;
		    }
		    
		    .payment-button {
		        min-height: 60px;
		        padding: 16px 20px;
		    }
		}
		.top-header {
    background-color: transparent!important;
}
	</style>
</head>
<body>
	<!-- 灵动岛消息提示 -->
	<div id="dynamicIsland" class="dynamic-island">
		<div class="icon">
			<i id="dynamicIslandIcon"></i>
		</div>
		<div class="message" id="dynamicIslandMessage"></div>
	</div>
	<div class="top-header">
		<a href="javascript:void(0)" onclick="window.parent.postMessage('closeModal', '*')" style="display: inline-flex; align-items: center; text-decoration: none; color: inherit;">
			<svg t="1768667202128" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="4699" width="18" height="18">
				<path
					d="M285.8112 565.76a56.4864 56.4864 0 0 0 39.04-16.3712l452.7744-453.76A56.5248 56.5248 0 0 0 778.24 16.64a54.8992 54.8992 0 0 0-78.08-0.5632L247.3344 469.76a56.5248 56.5248 0 0 0-0.5504 79.0144 50.048 50.048 0 0 0 39.0272 16.9344zM733.568 1024a56.1664 56.1664 0 0 0 39.6032-95.3856l-448.32-458.24a54.912 54.912 0 0 0-78.08-0.5632 56.5248 56.5248 0 0 0-0.5632 79.0144l448.32 458.24A53.76 53.76 0 0 0 733.568 1024z m0 0"
					fill="#333333" p-id="4700"></path>
			</svg>
		</a>
		<div style="border: 14px solid transparent;">在线续费[充值联系@yxhxzc888]</div>
	</div>
	<div class="container">
		<div class="main-content">
			<!-- 用户信息卡片 -->
			<div class="card">
				<div class="card-header">
					<div class="card-title">账户概览</div>
					<div class="card-subtitle">当前账户信息</div>
				</div>
				<div class="card-content">
					<div class="user-info">
						<div class="info-item">
							<span class="info-label">用户名</span>
							<span class="info-value"><?php echo htmlspecialchars($username); ?></span>
						</div>
						<div class="info-item">
							<span class="info-label">当前余额</span>
							<span class="info-value balance-value"><?php echo number_format($balance, 2); ?> 元</span>
						</div>
						<div class="info-item">
							<span class="info-label">到期时间</span>
							<span class="info-value"><?php echo $expire_time ? date('Y-m-d H:i', strtotime($expire_time)) : '未设置'; ?></span>
						</div>
						<div class="info-item">
							<span class="info-label">剩余天数</span>
							<span class="info-value"><?php echo $expire_days; ?> 天</span>
						</div>
					</div>
				</div>
			</div>
			<!-- 套餐选择卡片 -->
			<div class="card">
				<div class="card-header">
					<div class="card-title">选择套餐</div>
					<div class="card-subtitle">选择合适的续费时长</div>
				</div>
				<div class="card-content">
					<form method="POST" id="renewForm">
						<input type="hidden" name="renew_days" id="renewDays" value="<?php echo $selected_days; ?>">
						<!-- 九宫格布局 -->
						<div class="packages-grid"> <?php foreach ($packages as $package): ?> <div class="package-item <?php echo $package['days'] == $selected_days ? 'selected' : ''; ?>" data-days="<?php echo $package['days']; ?>" data-cost="<?php echo $package['cost']; ?>">
								<?php if (isset($package['recommended']) && $package['recommended']): ?> <div class="recommended-badge">推荐</div> <?php endif; ?> <div class="package-days"><?php echo $package['days']; ?>天</div>
								<div class="package-label"><?php echo $package['label']; ?></div>
								<div class="package-cost"><?php echo $package['cost']; ?>元</div>
							</div> <?php endforeach; ?> </div>
					</form>
				</div>
			</div>
		</div>
		<!-- 支付按钮 -->
		<button type="button" class="payment-button" id="paymentButton"> 立即支付 ¥<span id="paymentAmount"><?php echo $selected_cost; ?></span> 元 </button>
	</div>
	<!-- 拟态确认框 -->
	<div id="confirmModal" class="modal-overlay" style="display: none;">
		<div class="modal-content">
			<div class="modal-title">确认续费</div>
			<div class="modal-details">
				<div class="modal-detail">
					<span>续费时长：</span>
					<span id="confirmDays"><?php echo $selected_days; ?>天</span>
				</div>
				<div class="modal-detail">
					<span>每天费用：</span>
					<span>3元</span>
				</div>
				<div class="modal-detail">
					<span>总费用：</span>
					<span id="confirmCost"><?php echo $selected_cost; ?>元</span>
				</div>
				<div class="modal-detail">
					<span>当前余额：</span>
					<span><?php echo number_format($balance, 2); ?>元</span>
				</div>
				<div class="modal-detail">
					<span>续费后余额：</span>
					<span id="confirmBalance"><?php echo number_format($balance - $selected_cost, 2); ?>元</span>
				</div>
			</div>
			<div class="modal-buttons">
				<button type="button" class="modal-button modal-button-cancel" id="cancelButton">取消</button>
				<button type="button" class="modal-button modal-button-confirm" id="confirmButton">确认支付</button>
			</div>
		</div>
	</div>
	<script>
		// 灵动岛消息提示功能
		const dynamicIsland = document.getElementById('dynamicIsland');
		const dynamicIslandIcon = document.getElementById('dynamicIslandIcon');
		const dynamicIslandMessage = document.getElementById('dynamicIslandMessage');
		
		function showDynamicIsland(message, type) {
		    // 重置状态
		    dynamicIsland.classList.remove('appear', 'disappear', 'success', 'error');
		    
		    // 设置图标和消息
		    if (type === 'success') {
		        dynamicIslandIcon.className = 'bi bi-check-circle-fill';
		        dynamicIsland.classList.add('success');
		    } else if (type === 'error') {
		        dynamicIslandIcon.className = 'bi bi-exclamation-triangle-fill';
		        dynamicIsland.classList.add('error');
		    }
		    
		    dynamicIslandMessage.textContent = message;
		    
		    // 强制重绘
		    void dynamicIsland.offsetWidth;
		    
		    // 显示灵动岛 - 出现动画
		    dynamicIsland.classList.add('appear');
		    
		    // 3秒后自动隐藏 - 消失动画
		    setTimeout(() => {
		        dynamicIsland.classList.remove('appear');
		        dynamicIsland.classList.add('disappear');
		        
		        // 动画完成后完全隐藏
		        setTimeout(() => {
		            dynamicIsland.classList.remove('disappear');
		        }, 400);
		    }, 3000);
		}
		
		       // 套餐选择功能
		const packageItems = document.querySelectorAll('.package-item');
		const renewDaysInput = document.getElementById('renewDays');
		const paymentAmount = document.getElementById('paymentAmount');
		const confirmDays = document.getElementById('confirmDays');
		const confirmCost = document.getElementById('confirmCost');
		const confirmBalance = document.getElementById('confirmBalance');
		const paymentButton = document.getElementById('paymentButton');
		const confirmModal = document.getElementById('confirmModal');
		const cancelButton = document.getElementById('cancelButton');
		const confirmButton = document.getElementById('confirmButton');
		
		let currentBalance = <?php echo $balance; ?>;
		let selectedDays = <?php echo $selected_days; ?>;
		let selectedCost = <?php echo $selected_cost; ?>;
		
		// 套餐选择事件
		packageItems.forEach(item => {
		    item.addEventListener('click', function() {
		// 移除所有选中状态
		packageItems.forEach(pkg => pkg.classList.remove('selected'));
		
		// 设置当前选中
		this.classList.add('selected');
		
		// 更新选中的天数和费用
		selectedDays = parseInt(this.dataset.days);
		selectedCost = parseInt(this.dataset.cost);
		
		// 更新表单和显示
		renewDaysInput.value = selectedDays;
		paymentAmount.textContent = selectedCost;
		
		// 更新确认框内容
		updateConfirmModal();
		    });
		});
		
		// 更新确认框内容
		function updateConfirmModal() {
		    confirmDays.textContent = selectedDays + '天';
		    confirmCost.textContent = selectedCost + '元';
		    confirmBalance.textContent = (currentBalance - selectedCost).toFixed(2) + '元';
		}
		
		// 支付按钮点击事件
		paymentButton.addEventListener('click', function() {
		    if (selectedCost > currentBalance) {
		        showDynamicIsland('余额不足，请先充值！', 'error');
		        return;
		    }
		    updateConfirmModal();
		    confirmModal.style.display = 'flex';
		});
		
		// 取消按钮事件
		cancelButton.addEventListener('click', function() {
		    confirmModal.style.display = 'none';
		});
		
		// 确认支付按钮事件
		confirmButton.addEventListener('click', function() {
		    confirmModal.style.display = 'none';
		    document.getElementById('renewForm').submit();
		});
		
		// 点击遮罩层关闭确认框
		confirmModal.addEventListener('click', function(e) {
		    if (e.target === confirmModal) {
		        confirmModal.style.display = 'none';
		    }
		});
		
		// 初始化确认框内容
		updateConfirmModal();
		
		// PHP消息提示处理
		<?php if (isset($success)): ?>
		    document.addEventListener('DOMContentLoaded', function() {
		        showDynamicIsland('<?php echo $success; ?>', 'success');
		    });
		<?php endif; ?>
		
		<?php if (isset($error)): ?>
		    document.addEventListener('DOMContentLoaded', function() {
		        showDynamicIsland('<?php echo $error; ?>', 'error');
		    });
		<?php endif; ?>
	</script>
</body>
</html>