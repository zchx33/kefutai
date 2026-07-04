<?php
if (!defined('FROM_ROUTER')) {
    http_response_code(403);
    exit('喜乐科技@x60898');
}
// ========== 基础配置和登录检查 ==========
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/dbconfig.php';

checkLogin();

$db = getDB();
if (!$db) {
    die("数据库连接失败");
}
// ========== 获取用户基本信息 ==========
$user_info = [];
if (isset($_COOKIE['user_info'])) {
    $user_info = json_decode($_COOKIE['user_info'], true);
}

// 当前登录的客服信息
$currentAgent = $_SESSION['username'];
$currentRole = $_SESSION['role'];
$balance = $_SESSION['balance'];
$username = $user_info['user'] ?? $_SESSION['username'] ?? '';
$XErole = $_SESSION['user_role'];
$XEroles = ($XErole === 'admin') ? '总控' : 
           (($XErole === 'user') ? '用户' : 
           (($XErole === 'visitor') ? '访客' : $XErole));
$expire_days = $user_info['expire_days'] ?? $_SESSION['expire_days'] ?? 0;
$expire_time = $user_info['expire_time'] ?? $_SESSION['expire_time'] ?? '';
$is_expired = $user_info['is_expired'] ?? $_SESSION['is_expired'] ?? false;
$isTokenLogin = isset($_SESSION['login_type']) && $_SESSION['login_type'] === 'token';

// 假设用户名是QQ号，如果不是QQ号则使用默认头像
$is_qq = preg_match('/^[1-9][0-9]{4,10}$/', $username);
$avatar_url = $is_qq ? "http://q1.qlogo.cn/g?b=qq&nk={$username}&s=100" : '';

// 计算用户头像缓存
$avatar_cache_key = md5($username . '_avatar');
$cached_avatar = isset($_SESSION[$avatar_cache_key]) ? $_SESSION[$avatar_cache_key] : '';

// 如果头像不存在或过期，重新获取
if (empty($cached_avatar) && $is_qq) {
    $avatar_url = "http://q1.qlogo.cn/g?b=qq&nk={$username}&s=100";
    $cached_avatar = $avatar_url;
    $_SESSION[$avatar_cache_key] = $cached_avatar;
} elseif (!$is_qq) {
    $cached_avatar = '/assets/img/xile.jpg';
    $_SESSION[$avatar_cache_key] = $cached_avatar;
}

// ========== 计算过期时间 ==========
// admin 用户不受过期时间限制
if ($XErole === 'admin') {
    $is_expired = false;
    $expire_days = 999999; // 显示为永久
    $_SESSION['is_expired'] = false;
    $_SESSION['expire_days'] = 999999;
} else {
    if ($expire_time) {
        $current_time = time();
        $expire_timestamp = strtotime($expire_time);
        $expire_days = ceil(($expire_timestamp - $current_time) / (60 * 60 * 24));
        
        if ($expire_days <= 0) {
            $is_expired = true;
            $expire_days = 0;
            $_SESSION['is_expired'] = true;
            $_SESSION['expire_days'] = 0;
        } else {
            $is_expired = false;
            $_SESSION['is_expired'] = false;
            $_SESSION['expire_days'] = $expire_days;
        }
    } else {
        $is_expired = true;
        $_SESSION['is_expired'] = true;
    }
}

function getWebConfig($db) {
    $result = $db->query("SELECT * FROM webconfig ORDER BY id DESC LIMIT 1");
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    return [
        'site_name' => '欧钛网络',
        'telegram_username' => '',
        'storage_type' => 'local',
        'popup_enabled' => 0,
        'popup_title' => '网站公告',
        'popup_content' => '欢迎访问我们的网站！'
    ];
}

$config = getWebConfig($db);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no,viewport-fit=cover">
	<meta name="theme-color" content="#f9fafb">
	<meta name="mobile-web-app-capable" content="yes">
	<meta name="apple-mobile-web-app-capable" content="yes">
	<meta name="apple-mobile-web-app-title" content="XEKEFU">
	<meta name="apple-mobile-web-app-status-bar-style" content="default">
	<link rel="manifest" href="/manifest.php">
	<link rel="apple-touch-icon" href="/xe-icon.png">
	<link rel="shortcut icon" href="/xe-icon.png" type="image/x-icon">
	<meta name="description" content="在线客户服务平台">
	<meta name="keywords" content="客服,咨询,服务">
	<meta name="robots" content="noindex, nofollow">
	<title><?php echo htmlspecialchars($config['site_name'] ?: '喜乐'); ?>-客服系统</title>
	
	<link rel="stylesheet" href="/assets/bootstrap-icons.css">
	<style>
		/* 全局样式重置 */
		* {
		    margin: 0;
		    padding: 0;
		    box-sizing: border-box;
		}
		
		body {
		    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
		    background-color: #f9fafb;
		    color: #1f2937;
		    line-height: 1.5;
		    min-height: 100vh;
		    overflow: hidden;
		}
		
		/* 通用样式类 */
		.container {
		    width: 100%;
		    max-width: 28rem;
		    margin: 0 auto;
		    height: 100vh;
		    overflow: hidden;
		}
		
		/* 页面容器 */
.page-container {
    position: relative;
    width: 100%;
    height: calc(100vh - 70px);
    overflow: hidden;
}
		
		.page {
		    position: absolute;
		    top: 0;
		    left: 0;
		    width: 100%;
		    height: 100%;
		    overflow-x: hidden;
		    overflow-y: auto;
		    pointer-events: none;
		    overflow: auto;
		    -ms-overflow-style: none;
		    scrollbar-width: none;
		    transform: translateX(100%);
        }
		
		.page.slide-enter-active,
		.page.slide-leave-active {
		    transition: transform 0.3s ease;
		}
		
		.page.slide-enter-from {
		    transform: translateX(100%);
		}
		
		.page.slide-enter-to {
		    transform: translateX(0);
		    pointer-events: auto;
		}
		
		.page.slide-leave-from {
		    transform: translateX(0);
		    pointer-events: auto;
		}
		
		.page.slide-leave-to {
		    transform: translateX(-100%);
		}
		
		.page.active {
		    transform: translateX(0);
		    pointer-events: auto;
		}
		
		.page:not(.active):not(.slide-enter-to):not(.slide-leave-from) {
		    transform: translateX(100%);
		}
		
		/* 主页头部样式 */
		.header {
		    position: relative;
		    display: flex;
		    align-items: center;
		    justify-content: center;
		    padding: 1rem;
		}
		
		.header h1 {
		    font-size: 14px;
		    font-weight: 600;
		    color: #1f2937;
		}
		
		.settings-btn {
		    position: absolute;
		    right: 1rem;
		    font-size: 1.5rem;
		    color: #374151;
		    background: none;
		    border: none;
		    cursor: pointer;
		}
		
		/* 主内容区域 */
		.main-content {
		    padding: 0 1rem 3.5rem;
		}
		
		/* 卡片样式 */
		.card {
		    background-color: white;
		    border-radius: 1rem;
		    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
		}
		
		/* 用户信息卡片 - 列表行样式 */
		.profile-card {
		    padding: 0;
		    background: rgba(255, 255, 255, 0.25);
		    backdrop-filter: blur(10px) saturate(150%);
		    -webkit-backdrop-filter: blur(10px) saturate(150%);
		    border: 1px solid rgba(255, 255, 255, 0.3);
		    box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.15),
		                inset 0 1px 0 rgba(255, 255, 255, 0.4);
		    overflow: hidden;
		    will-change: auto;
		}
		
		.profile-row {
		    display: flex;
		    align-items: center;
		    padding: 14px 16px;
		    position: relative;
		}
		
		.profile-row:not(:last-child)::after {
		    content: '';
		    position: absolute;
		    bottom: 0;
		    left: 16px;
		    right: 16px;
		    height: 1px;
		    background-color: #f0f0f0;
		}
		
		.profile-row-label {
		    color: #9ca3af;
		    font-size: 14px;
		    min-width: 48px;
		    flex-shrink: 0;
		}
		
		.profile-row-value {
		    font-weight: 700;
		    color: #1f2937;
		    font-size: 14px;
		    flex: 1;
		    margin-left: 8px;
		}
		
		.profile-row-right {
		    display: flex;
		    align-items: center;
		    gap: 6px;
		    margin-left: auto;
		    flex-shrink: 0;
		}
		
		.profile-row-arrow {
		    color: #c0c0c0;
		    font-size: 14px;
		}
		
		.profile-role-tag {
		    background-color: #f3f4f6;
		    color: #6b7280;
		    font-size: 12px;
		    padding: 2px 10px;
		    border-radius: 20px;
		    font-weight: 500;
		}
		
		.profile-expire-tag {
		    background: linear-gradient(135deg, #34d399, #10b981);
		    color: #fff;
		    font-size: 12px;
		    padding: 2px 10px;
		    border-radius: 20px;
		    font-weight: 500;
		}
		
		.profile-expire-tag.expired {
		    background: linear-gradient(135deg, #f87171, #ef4444);
		}
		
		.profile-refresh-text {
		    color: #9ca3af;
		    font-size: 12px;
		}
		
		/* 推广信息卡片 */
		.promo-card {
		    background: linear-gradient(135deg, rgba(254, 243, 199, 0.5), rgba(254, 215, 170, 0.5));
		    backdrop-filter: blur(10px) saturate(150%);
		    -webkit-backdrop-filter: blur(10px) saturate(150%);
		    border-radius: 12px;
		    padding: 14px 16px;
		    margin-top: 12px;
		    display: flex;
		    align-items: center;
		    gap: 10px;
		    cursor: pointer;
		    transition: opacity 0.2s ease;
		    border: 1px solid rgba(255, 255, 255, 0.3);
		    box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.15),
		                inset 0 1px 0 rgba(255, 255, 255, 0.4);
		    will-change: auto;
		}
		
		.promo-card:active {
		    transform: scale(0.97);
		    opacity: 0.8;
		}
		
		.promo-card-icon {
		    width: 36px;
		    height: 36px;
		    background: rgba(255,255,255,0.7);
		    border-radius: 10px;
		    display: flex;
		    align-items: center;
		    justify-content: center;
		    flex-shrink: 0;
		}
		
		.promo-card-icon i {
		    color: #d97706;
		    font-size: 18px;
		}
		
		.promo-card-text {
		    flex: 1;
		}
		
		.promo-card-text h4 {
		    font-size: 14px;
		    font-weight: 600;
		    color: #92400e;
		    margin-bottom: 2px;
		}
		
		.promo-card-text p {
		    font-size: 12px;
		    color: #b45309;
		}
		
		/* 功能网格 - 2x2液态玻璃卡片 */
		.function-grid {
		    display: grid;
		    grid-template-columns: repeat(2, 1fr);
		    gap: 12px;
		    margin-top: 14px;
		}
		
		.function-card {
		    background: rgba(255, 255, 255, 0.25);
		    backdrop-filter: blur(10px) saturate(150%);
		    -webkit-backdrop-filter: blur(10px) saturate(150%);
		    border-radius: 1rem;
		    padding: 16px;
		    border: 1px solid rgba(255, 255, 255, 0.3);
		    box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.15),
		                inset 0 1px 0 rgba(255, 255, 255, 0.4);
		    cursor: pointer;
		    transition: transform 0.15s ease, box-shadow 0.15s ease;
		    display: flex;
		    align-items: center;
		    gap: 12px;
		    position: relative;
		    overflow: hidden;
		    will-change: auto;
		}
		
		.function-card::before {
		    content: '';
		    position: absolute;
		    top: 0;
		    left: -100%;
		    width: 100%;
		    height: 100%;
		    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
		    transition: left 0.4s ease;
		    pointer-events: none;
		}
		
		.function-card:hover::before {
		    left: 100%;
		}
		
		.function-card:hover {
		    transform: translateY(-2px);
		    box-shadow: 0 8px 24px 0 rgba(31, 38, 135, 0.2),
		                inset 0 1px 0 rgba(255, 255, 255, 0.5);
		    background: rgba(255, 255, 255, 0.3);
		    border-color: rgba(255, 255, 255, 0.4);
		}
		
		.function-card:active {
		    transform: translateY(0) scale(0.98);
		    box-shadow: 0 4px 12px 0 rgba(31, 38, 135, 0.15),
		                inset 0 1px 0 rgba(255, 255, 255, 0.3);
		}
		
		.function-card.expired {
		    background: #fff5f5;
		    cursor: not-allowed;
		    pointer-events: none;
		    opacity: 0.6;
		}
		
		.function-card.expired h3,
		.function-card.expired i {
		    color: #ef5350;
		}
		
		.function-card h3 {
		    font-weight: 600;
		    color: #1f2937;
		    font-size: 14px;
		}
		
		.function-icon-circle {
		    width: 40px;
		    height: 40px;
		    border-radius: 12px;
		    display: flex;
		    align-items: center;
		    justify-content: center;
		    flex-shrink: 0;
		}
		
		.function-icon-circle i {
		    color: #fff;
		    font-size: 18px;
		}
		
		.function-icon-circle.gradient-blue {
		    background: linear-gradient(135deg, #60a5fa, #3b82f6);
		}
		
		.function-icon-circle.gradient-green {
		    background: linear-gradient(135deg, #34d399, #10b981);
		}
		
		.function-icon-circle.gradient-orange {
		    background: linear-gradient(135deg, #fbbf24, #f59e0b);
		}
		
		.function-icon-circle.gradient-purple {
		    background: linear-gradient(135deg, #a78bfa, #8b5cf6);
		}
		
		/* 其他功能 */
		.section-title {
		    font-size: 0.875rem;
		    color: #6b7280;
		    margin-top: 1.5rem;
		    margin-bottom: 0.75rem;
		}
		
		.other-functions {
		    margin-top: 12px;
		}
		
		.other-functions-grid {
		    display: grid;
		    grid-template-columns: repeat(2, 1fr);
		    gap: 12px;
		    margin-top: 14px;
		}
		
		.other-function-card {
		    font-size:14px;
		    background: rgba(255, 255, 255, 0.25);
		    backdrop-filter: blur(10px) saturate(150%);
		    -webkit-backdrop-filter: blur(10px) saturate(150%);
		    border-radius: 1rem;
		    padding: 1rem;
		    border: 1px solid rgba(255, 255, 255, 0.3);
		    box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.15),
		                inset 0 1px 0 rgba(255, 255, 255, 0.4);
		    display: flex;
		    justify-content: space-between;
		    align-items: center;
		    cursor: pointer;
		    transition: transform 0.15s ease, box-shadow 0.15s ease;
		    position: relative;
		    overflow: hidden;
		    will-change: auto;
		}
		
		.other-function-card::before {
		    content: '';
		    position: absolute;
		    top: 0;
		    left: -100%;
		    width: 100%;
		    height: 100%;
		    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
		    transition: left 0.4s ease;
		    pointer-events: none;
		}
		
		.other-function-card:hover::before {
		    left: 100%;
		}
		
		.other-function-card:hover {
		    transform: translateY(-2px);
		    box-shadow: 0 8px 24px 0 rgba(31, 38, 135, 0.2),
		                inset 0 1px 0 rgba(255, 255, 255, 0.5);
		    background: rgba(255, 255, 255, 0.3);
		    border-color: rgba(255, 255, 255, 0.4);
		}
		
		.other-function-card:active {
		    transform: translateY(0) scale(0.98);
		    box-shadow: 0 4px 12px 0 rgba(31, 38, 135, 0.15),
		                inset 0 1px 0 rgba(255, 255, 255, 0.3);
		}
		
		.other-function-card.expired {
		    background: rgba(255, 235, 238, 0.4);
		    cursor: not-allowed;
		    pointer-events: none;
		    opacity: 0.6;
		}
		
		.other-function-card.expired h3,
		.other-function-card.expired p,
		.other-function-card.expired i {
		    color: #ef5350;
		}
		

		.other-function-card p {
		    font-size: 0.75rem;
		    color: #6b7280;
		    margin-top: 0.25rem;
		}
		

		.icon-circle {
		    background: rgba(243, 244, 246, 0.6);
		    backdrop-filter: blur(10px);
		    border-radius: 12px;
		    width: 2.75rem;
		    height: 2.75rem;
		    display: flex;
		    align-items: center;
		    justify-content: center;
		    border: 1px solid rgba(255, 255, 255, 0.3);
		}
		
		.icon-circle svg {
		    width: 1.5rem;
		    height: 1.5rem;
		    color: #374151;
		}
		
		/* 账户过期样式 */
		.chat-container.expired {
		    background: linear-gradient(135deg, #ffebee 0%, #ffcdd2 100%);
		    display: flex;
		    align-items: center;
		    justify-content: center;
		    min-height: calc(100vh - 150px);
		}
		
		.expired-message {
		    text-align: center;
		    color: #c62828;
		    font-size: 32px;
		    font-weight: 900;
		    padding: 40px 20px;
		    animation: pulse 2s ease-in-out infinite;
		}
		
		@keyframes pulse {
		    0%, 100% {
		        transform: scale(1);
		        opacity: 1;
		    }
		    50% {
		        transform: scale(1.05);
		        opacity: 0.8;
		    }
		}
		
		/* 底部导航过期样式 */
		.bottom-nav.expired {
		    pointer-events: none;
		    opacity: 0.5;
		    cursor: not-allowed;
		}
		
		/* 聊天页面 */
		.chat-header {
		    padding: 8px 16px 0px;
		    text-align: center;
		}
		
		.chat-header h1 {
		    font-size: 20px;
		    font-weight: 600;
		    margin-bottom: 4px;
		}
		
		.online-tip {
		    font-size: 14px;
		    color: #007aff;
		    font-weight: 500;
		}
		
		.session-list {
		    flex: 1;
		    overflow-y: auto;
		    padding: 0 12px 20px;
		}
		
		.session-list {
		    position: relative;
		}
		
		.session-item-wrapper {
		    position: relative;
		    margin-bottom: 8px;
		    overflow: visible;
		}
		
		.session-item {
		    display: flex;
		    align-items: center;
		    padding: 14px 16px;
		    background: rgba(255,255,255,0.85);
		    backdrop-filter: blur(12px);
		    border-radius: 20px;
		    text-decoration: none;
		    color: inherit;
		    position: relative;
		    border: 1px solid rgba(255,255,255,0.3);
		    transition: transform 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
		    touch-action: pan-y;
		    z-index: 2;
		    background-color: white;
		}
		
		.session-item.sticky {
		    background: rgba(0,122,255,0.08);
		    border-left: 3px solid #007aff;
		}
		
		.session-delete-btn {
		    position: absolute;
		    top: 0;
		    right: 0;
		    bottom: 0;
		    width: 80px;
		    background: #ff3b30;
		    display: flex;
		    align-items: center;
		    justify-content: center;
		    border-radius: 0 20px 20px 0;
		    cursor: pointer;
		    opacity: 0;
		    pointer-events: none;
		    transition: opacity 0.3s ease, transform 0.3s ease;
		    z-index: 1;
		}
		
		.session-delete-btn i {
		    color: white;
		    font-size: 20px;
		}
		
		.session-item-wrapper.swiping .session-delete-btn {
		    opacity: 1;
		    pointer-events: auto;
		}
		
		.session-item-wrapper.swiped-out .session-item {
		    transform: translateX(-100%);
		}
		
		.session-item-wrapper.swiped-out {
		    transition: height 0.3s ease, opacity 0.3s ease;
		    height: 0;
		    opacity: 0;
		    margin-bottom: 0;
		}
		
	/* 1. 确保父容器是完美的圆形并允许溢出显示 */
.avatar {
    position: relative;
    width: 44px;
    height: 44px;
    border-radius: 50%; /* 必须是50% */
    background: #f0f0f0;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 12px;
    flex-shrink: 0;
    overflow: visible; /* 这里改为 visible，允许角标超出圆形边界一点点 */
}

.avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 50%;
}

/* 2. 优化群聊角标样式 */
.avatar .group-chat-badge {
    position: absolute;
    /* 关键修改：调整位置，稍微往里收，不要完全贴边 */
    bottom: -1px;     
    right: -1px;
    
    background: #f0ad4e; /* 保持群聊黄色 */
    color: white;
    border-radius: 50%;
    width: 18px;       /* 稍微大一点点 */
    height: 18px;
    font-size: 11px;   /* 图标稍大 */
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10;
    
    /* 关键修改：添加白色描边，防止遮挡 */
    border: 1px solid #ffffff; 
    
    box-sizing: border-box; /* 确保宽高包含边框 */
    box-shadow: 0 1px 3px rgba(0,0,0,0.2); /* 加点阴影更有立体感 */
}

.avatar .group-chat-badge i {
    font-size: 11px;
}
		
		.session-content {
		    flex: 1;
		    min-width: 0;
		}
		
		.session-id {
		    font-size: 16px;
		    font-weight: 500;
		    margin-bottom: 4px;
		    display: flex;
		    align-items: center;
		    gap: 6px;
		}
		
		.last-message {
		    font-size: 14px;
		    color: #888;
		    white-space: nowrap;
		    overflow: hidden;
		    text-overflow: ellipsis;
		}
		
		.session-time {
		    font-size: 13px;
		    color: #aaa;
		    margin-left: auto;
		    flex-shrink: 0;
		}
		
		.empty-tip {
		    padding: 60px 20px;
		    text-align: center;
		    color: #999;
		    font-size: 15px;
		}
		
		/* WiFi图标样式的在线状态 */
	.status-text {
	    display: inline-flex;
	    align-items: center;
	    justify-content: center;
	    width: 16px;
	    height: 16px;
	    margin-left: 6px;
	    position: relative;
	}
	
	.status-text::before {
	    content: '';
	    display: block;
	    width: 100%;
	    height: 100%;
	}
	
	/* online - 绿色满格WiFi */
	.status-online::before {
	    background-image: url("data:image/png;base64,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");
	    background-repeat: no-repeat;
	    background-position: center;
	    background-size: contain;
	}
	
	/* hidden - 黄色WiFi（离开） */
	.status-away::before {
	    background-image: url("data:image/png;base64,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");
	    background-repeat: no-repeat;
	    background-position: center;
	    background-size: contain;
	}
	
	/* offline - 红色WiFi（离线） */
	.status-offline::before {
	    background-image: url("data:image/png;base64,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");
	    background-repeat: no-repeat;
	    background-position: center;
	    background-size: contain;
	    opacity: 0.5;
	}
		
		.tag {
		    font-size: 12px;
		    padding: 2px 6px;
		    border-radius: 4px;
		    margin-left: 4px;
		}
		
		.tag-no-disturb {
		    color: #ff3b30;
		    background: rgba(255,59,48,0.1);
		}
		
		.tag-remark {
		    color: #007aff;
		    background: rgba(0,122,255,0.1);
		}
		
		/* 新通知样式 */
		.new-user-toast {
		    position: fixed;
		    top: 1px;
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
		     max-width: calc(100vw - 20px);  /* 添加最大宽度限制 */
    box-sizing: border-box;           /* 确保padding不会撑开宽度 */
		}
		
		.toast-icon {
		    width: 24px;
		    height: 24px;
		    background: transparent;
		    border-radius: 0;
		    display: flex;
		    align-items: center;
		    justify-content: center;
		    margin-right: 12px;
		    flex-shrink: 0;
		    font-size: 20px;
		    color: #fff;
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
		
		/* 通知进入和离开动画 */
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
		
		/* 不同通知类型的背景色 */
		.toast-success {
		    background: #4caf50;
		}
		
		.toast-error {
		    background: #f44336;
		}
		
		.toast-info {
		    background: #2196f3;
		}
		
		.toast-warning {
		    background: #ff9800;
		}
		
		/* 确认对话框样式 */
		.confirm-dialog {
		    position: fixed;
		    top: 0;
		    left: 0;
		    right: 0;
		    bottom: 0;
		    background: rgba(0, 0, 0, 0.5);
		    display: flex;
		    align-items: center;
		    justify-content: center;
		    z-index: 10000;
		    backdrop-filter: blur(5px)
		}
		
		.dialog-content {
		    background: #fff;
		    border-radius: 18px;
		    padding: 24px;
		    max-width: 320px;
		    width: 90%;
		    box-shadow: 0 12px 36px rgba(2, 6, 23, .08);
		    border: 1px solid rgba(15, 23, 42, .10)
		}
		
		.dialog-title {
		    font-weight: 900;
		    font-size: 18px;
		    margin-bottom: 8px;
		    color: #0f172a
		}
		
		.dialog-message {
		    color: #64748b;
		    margin-bottom: 20px;
		    line-height: 1.5
		}
		
		.dialog-actions {
		    display: flex;
		    gap: 12px;
		    justify-content: flex-end
		}
		
		.dialog-btn {
		    padding: 8px 16px;
		    border-radius: 12px;
		    border: 1px solid rgba(15, 23, 42, .10);
		    background: #fff;
		    color: #0f172a;
		    cursor: pointer;
		    font-weight: 600;
		    transition: all 0.2s ease
		}
		
		.dialog-btn.cancel:hover {
		    background: rgba(255, 255, 255, .88)
		}
		
		.dialog-btn.confirm {
		    background: #ef4444;
		    color: white;
		    border-color: #ef4444
		}
		
		.dialog-btn.confirm:hover {
		    background: #dc2626
		}
		
		@keyframes fadeIn {
		    from {
		        opacity: 0;
		        transform: translateY(-20px)
		    }
		
		    to {
		        opacity: 1;
		        transform: translateY(0)
		    }
		}
		
		@keyframes fadeOut {
		    from {
		        opacity: 1;
		        transform: translateY(0)
		    }
		
		    to {
		        opacity: 0;
		        transform: translateY(-20px)
		    }
		}
		
		.confirm-dialog {
		    animation: fadeIn 0.3s ease-out forwards
		}
		
		.confirm-dialog.fade-out {
		    animation: fadeOut 0.25s ease-out forwards
		}
		
		/* 工具栏样式 */
		.toolbar {
		    display: flex;
		    flex-direction: column;
		    gap: 10px;
		    padding: 12px 12px 8px;
		    background: transparent;
		}

		/* 聊天类型切换Tab */
		.chat-type-tabs {
		    display: flex;
		    background: rgba(0,0,0,0.04);
		    border-radius: 20px;
		    padding: 3px;
		    width: 100%;
		    position: relative;
		}

		.chat-type-tabs .tab-slider {
		    position: absolute;
		    top: 3px;
		    left: 3px;
		    height: calc(100% - 6px);
		    width: calc(50% - 4px);
		    background: #fff;
		    border-radius: 17px;
		    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
		    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
		    z-index: 0;
		}

		.chat-type-tabs .tab-slider[data-active="1"] {
		    transform: translateX(0);
		}

		.chat-type-tabs .tab-slider[data-active="2"] {
		    transform: translateX(calc(100% + 4px));
		}

		.chat-type-tab {
		    flex: 1;
		    padding: 7px 0;
		    border: none;
		    border-radius: 17px;
		    background: transparent;
		    color: #64748b;
		    font-size: 13px;
		    font-weight: 500;
		    cursor: pointer;
		    transition: color 0.3s ease;
		    white-space: nowrap;
		    font-family: inherit;
		    text-align: center;
		    position: relative;
		    z-index: 1;
		}

		.chat-type-tab.active {
		    color: #007aff;
		    font-weight: 600;
		    background: transparent;
		    box-shadow: none;
		}

		.chat-type-tab:hover:not(.active) {
		    color: #333;
		}
		
		.searchbox {
		    flex: 1 1 auto;
		    display: flex;
		    align-items: center;
		    gap: 10px;
		    padding: 10px 16px;
		    border-radius: 20px;
		    border: 1px solid rgba(15, 23, 42, .10);
		    background: rgba(255,255,255,0.85);
		    transition: all 0.2s ease
		}
		
		.searchbox:focus-within {
		    border-color: #007aff;
		    box-shadow: 0 0 0 3px rgba(0, 122, 255, 0.1)
		}
		
		.searchbox .ico {
		    opacity: .7;
		    color: #64748b
		}
		
		.search {
		    flex: 1 1 auto;
		    background: transparent;
		    border: 0;
		    outline: none;
		    color: #0f172a;
		    font-size: 14px
		}
		
		.search::placeholder {
		    color: #64748b
		}
		
		.tab {
		    height: 40px;
		    width: 40px;
		    padding: 0 8px;
		    border-radius: 20px;
		    border: 1px solid rgba(15, 23, 42, .10);
		    background: rgba(255,255,255,0.85);
		    color: #0f172a;
		    font-weight: 850;
		    cursor: pointer;
		    white-space: nowrap;
		    transition: all 0.2s ease;
		    display: flex;
		    align-items: center;
		    justify-content: center;
		}
		
		.tab.on {
		    border-color: #007aff;
		    color: #007aff;
		    background: rgba(0, 122, 255, .08)
		}
		
		.tab:hover:not(.on) {
		    background: rgba(255, 255, 255, .88)
		}
		
		/* 顶部统计 */
		.stats-container {
		    display: flex;
		    gap: 20px;
		    align-items: center
		}
		
		.stat-item {
		    display: flex;
		    flex-direction: column;
		    align-items: center;
		    gap: 4px
		}
		
		.stat-value {
		    font-size: 20px;
		    font-weight: 900;
		    color: #007aff
		}
		
		.stat-label {
		    font-size: 12px;
		    color: #64748b;
		    text-transform: uppercase;
		    letter-spacing: 0.5px
		}
		
		.stat-divider {
		    width: 1px;
		    height: 30px;
		    background: rgba(15, 23, 42, .10)
		}

/* 群聊标签样式 */
.tag-group {
    background: #f0ad4e;
    color: white;
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 12px;
    margin-left: 4px;
}
		
		.sticky-icon {
		    color: #007aff;
		    font-size: 14px;
		}
		
		.session-actions {
		    display: flex;
		    gap: 4px;
		    margin-left: 8px;
		}
		
		.action-btn {
		    background: none;
		    border: none;
		    color: #888;
		    cursor: pointer;
		    padding: 4px;
		    border-radius: 4px;
		    transition: all 0.2s ease
		}
		
		.action-btn:hover {
		    background: rgba(0,0,0,0.05);
		    color: #333
		}
		
		/* 弹窗样式 */
		.page-modal {
		    position: fixed;
		    top: 0;
		    left: 0;
		    width: 100%;
		    height: 100%;
		    background: #f9fafb;
		    display: none;
		    z-index: 10000;
		    transform: translateY(100%);
		    transition: transform 0.25s cubic-bezier(0.25, 0.46, 0.45, 0.94);
		}
		
		.page-modal.active {
		    display: block;
		    transform: translateY(0);
		}
		
		.page-iframe {
		    width: 100%;
		    height: 100%;
		    border: none;
		    display: block;
		}
		
		/* 从下到上弹窗样式 */
		.bottom-modal {
		    position: fixed;
		    top: 0;
		    left: 0;
		    width: 100%;
		    height: 100%;
		    background: rgba(0, 0, 0, 0);
		    backdrop-filter: blur(0);
		    display: none;
		    align-items: flex-end;
		    justify-content: center;
		    z-index: 10000;
		    opacity: 0;
		    transition: background 0.3s ease, opacity 0.3s ease, backdrop-filter 0.3s ease;
		}
		
		.bottom-modal.active {
		    display: flex;
		    opacity: 1;
		    background: rgba(0, 0, 0, 0.5);
		    backdrop-filter: blur(5px);
		}
		
		.bottom-modal-content {
		    background: white;
		    border-radius: 20px 20px 0 0;
		    width: 100%;
		    max-width: 28rem;
		    transform: translateY(100%);
		    transition: transform 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
		    max-height: 80vh;
		    overflow-y: auto;
		}
		
		.bottom-modal.active .bottom-modal-content {
		    transform: translateY(0);
		}
		
		.modal-header {
		    padding: 20px;
		    text-align: center;
		    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
		    position: relative;
		}
		
		.modal-header h3 {
		    font-size: 18px;
		    font-weight: 600;
		    color: #2d2d2d;
		    margin: 0;
		}
		
		.close-modal {
		    position: absolute;
		    right: 20px;
		    top: 50%;
		    transform: translateY(-50%);
		    background: none;
		    border: none;
		    font-size: 1.5rem;
		    color: #6b7280;
		    cursor: pointer;
		}
		
		.modal-body {
		    padding: 20px;
		}
		
		.modal-body p {
		    color: #666;
		    font-size: 15px;
		    margin: 0;
		    line-height: 1.4;
		}
		
		.chatroom-buttons {
		    display: flex;
		    flex-direction: column;
		    gap: 12px;
		}
		
		.chatroom-btn {
		    padding: 16px;
		    border: none;
		    border-radius: 12px;
		    font-size: 15px;
		    font-weight: 600;
		    cursor: pointer;
		    transition: all 0.2s ease;
		    text-align: center;
		    display: block;
		    width: 100%;
		    font-family: inherit;
		    display: flex;
		    align-items: center;
		    justify-content: center;
		    gap: 8px;
		}
		
		.chatroom-btn i {
		    font-size: 18px;
		}
		
		.modal-footer {
		    padding: 20px;
		    display: flex;
		    gap: 10px;
		    border-top: 1px solid rgba(0, 0, 0, 0.05);
		}
		
		.modal-footer button {
		    flex: 1;
		    padding: 12px 15px;
		    border: none;
		    border-radius: 12px;
		    font-size: 15px;
		    font-weight: 600;
		    cursor: pointer;
		    transition: all 0.2s ease;
		    font-family: inherit;
		}
		
		.btn-secondary {
		    background: rgba(0, 0, 0, 0.05);
		    color: #666;
		}
		
		.btn-secondary:hover {
		    background: rgba(0, 0, 0, 0.1);
		}
		
		.btn-orange {
		    background: #FF9500;
		    color: white;
		}
		
		.btn-orange:hover {
		    background: #e68900;
		}
		
		.btn-green {
		    background: #34C759;
		    color: white;
		}
		
		.btn-green:hover {
		    background: #2db350;
		}
		
		.btn-primary {
		    background: #53a2f4;
		    color: white;
		}
		
		.btn-primary:hover {
		    background: #3a92f2;
		}
		
		.btn-danger {
		    background: #FF3B30;
		    color: white;
		}
		
		.btn-danger:hover {
		    background: #ff1a1a;
		}
		
		/* 设置选项样式 */
		.settings-options {
		    display: flex;
		    flex-direction: column;
		    gap: 1px;
		}
		
		.setting-item {
		    display: flex;
		    align-items: center;
		    justify-content: space-between;
		    padding: 16px 20px;
		    border-bottom: 1px solid #f3f4f6;
		    cursor: pointer;
		    transition: background-color 0.2s ease;
		}
		
		.setting-item:last-child {
		    border-bottom: none;
		}
		
		.setting-item:hover {
		    background-color: #f9fafb;
		}
		
		.setting-item-left {
		    display: flex;
		    align-items: center;
		    gap: 12px;
		}
		
		.setting-icon {
		    width: 24px;
		    height: 24px;
		    display: flex;
		    align-items: center;
		    justify-content: center;
		    color: #374151;
		}
		
		.setting-text h4 {
		    font-size: 16px;
		    font-weight: 500;
		    color: #1f2937;
		    margin-bottom: 2px;
		}
		
		.setting-text p {
		    font-size: 13px;
		    color: #6b7280;
		}
		
		.setting-item.disabled {
		    background-color: #f5f5f5;
		    cursor: not-allowed;
		    opacity: 0.5;
		    pointer-events: none;
		}
		
		.setting-item.disabled h4,
		.setting-item.disabled .setting-icon {
		    color: #999;
		}
		
		.settings-options.expired .setting-item:not(:last-child) {
		    background-color: #ffebee;
		}
		
		.settings-options.expired .setting-item:not(:last-child) h4,
		.settings-options.expired .setting-item:not(:last-child) .setting-icon {
		    color: #ef5350;
		}
		
		.setting-arrow {
		    color: #9ca3af;
		}
		
		/* 底部导航 - 改为直接固定在底部 */
.bottom-nav {
    position: fixed;
    bottom: 0;
    left: 0;
    width: 100%;
    background: #ffffff;
    display: flex;
    padding: 8px 0;
    z-index: 1000;
    border-top: 1px solid rgba(0, 0, 0, 0.1);
    height: 70px;
    /* 移除毛玻璃效果和圆角 */
    backdrop-filter: none;
    -webkit-backdrop-filter: none;
    border-radius: 0;
    transform: none;
    max-width: none;

}

.nav-item {
    flex: 1;
    text-align: center;
    text-decoration: none;
    color: #8E8E93;
    padding: 8px 0;
    transition: all 0.2s ease;
    position: relative;
    border-radius: 0;
    margin: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    /* 移除悬浮效果 */
    background: transparent;
}

.nav-item.active { 
    color: #000;
    background: transparent;
    height: auto;
    min-height: 50px; 
}

.nav-item:active {
    background: rgba(0, 122, 255, 0.1);
}

.nav-icon {
    font-size: 20px;
    margin-bottom: 4px;
    transition: transform 0.2s ease;
    color: #8E8E93;
}

.nav-item.active .nav-icon {
    transform: translateY(-2px);
    color: #000;
}

.nav-label {
    font-size: 10px;
    font-weight: 500;
    transition: all 0.2s ease;
    color: #8E8E93;
}

.nav-item.active .nav-label {
    font-weight: 600;
    font-size: 11px;
    color: #000;
}
		
		/* 加载动画 */
		.app-loading {
		    position: absolute;
		    top: 0;
		    left: 0;
		    right: 0;
		    bottom: 0;
		    display: flex;
		    justify-content: center;
		    align-items: center;
		    flex-direction: column;
		    background: #fff;
		    z-index: 1;
		    opacity: 1;
		    transition: opacity 0.3s ease;
		}

		.app-loading.hidden {
		    opacity: 0;
		    pointer-events: none;
		}

		.app-loading__loader {
		    box-sizing: border-box;
		    width: 35px;
		    height: 35px;
		    border: 5px solid transparent;
		    border-top-color: #000;
		    border-radius: 50%;
		    animation: 1s loader linear infinite;
		    position: relative;
		}

		.app-loading__loader:before {
		    box-sizing: border-box;
		    content: '';
		    display: block;
		    width: inherit;
		    height: inherit;
		    position: absolute;
		    top: -5px;
		    left: -5px;
		    border: 5px solid #ccc;
		    border-radius: 50%;
		    opacity: .5;
		}

		.app-loading__title {
		    color: #333;
		    margin-top: 30px;
		}

		@keyframes loader {
		    0% { transform: rotate(0deg); }
		    100% { transform: rotate(360deg); }
		}

		/* 角色标签 */
		.role-badge {
		    display: inline-block;
		    padding: 2px 8px;
		    border-radius: 4px;
		    font-size: 11px;
		    font-weight: 600;
		    margin-left: 6px;
		    vertical-align: middle;
		}
		
		.role-admin {
		    background: rgba(255, 215, 0, 0.2);
		    color: #FFD700;
		    border: 1px solid rgba(255, 215, 0, 0.3);
		}
		
		.role-user {
		    background: rgba(0, 122, 255, 0.2);
		    color: #007AFF;
		    border: 1px solid rgba(0, 122, 255, 0.3);
		}
		
		.role-visitor {
		    background: rgba(142, 142, 147, 0.2);
		    color: #8E8E93;
		    border: 1px solid rgba(142, 142, 147, 0.3);
		}
		
		.role-success {
		    background: rgba(52, 199, 89, 0.2);
		    color: #34C759;
		    border: 1px solid rgba(52, 199, 89, 0.3);
		}
		
		.role-danger {
		    background: rgba(255, 59, 48, 0.2);
		    color: #FF3B30;
		    border: 1px solid rgba(255, 59, 48, 0.3);
		}
		
		/* 文件上传隐藏 */
		.file-input {
		    display: none;
		}
		
		/* 响应式设计 */
		@media (min-width: 640px) {
		    .container {
		        max-width: 640px;
		    }
		}
		.XE-app {
		       max-height: calc(100% + 1px);
    min-height: calc(100% + 1px);
    max-width: 100%;
    min-width: 100%;
    position: relative;
    overflow: hidden;
}
/* 当有no-page-transition类时禁用所有过渡 */
.no-page-transition * {
    transition: none !important;
    animation: none !important;
    animation-delay: 0s !important;
    transition-delay: 0s !important;
}
	</style>
	<style>
	    
        .popup-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            z-index: 1001;
            justify-content: center;
            align-items: center;
            padding: 1.5rem;
            backdrop-filter: blur(8px);
        }

        .popup-overlay.active {
            display: flex;
            animation: popupFadeIn 0.35s cubic-bezier(0.4, 0, 0.2, 1);
        }

        @keyframes popupFadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .popup-content {
            background: #ffffff;
            border-radius: 24px;
            padding: 2.5rem 2rem;
            max-width: 340px;
            width: 100%;
            text-align: center;
            animation: popupSlideUp 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15), 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        @keyframes popupSlideUp {
            from {
                opacity: 0;
                transform: translateY(25px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .popup-title {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 1rem;
            color: #1d1d1f;
            letter-spacing: -0.2px;
        }

        .popup-body {
            font-size: 15px;
            color: #636366;
            margin-bottom: 1.75rem;
            line-height: 1.6;
        }

        .popup-btn {
            background: #1d1d1f;
            color: #ffffff;
            border: none;
            padding: 12px 24px;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            min-width: 200px;
        }

        .popup-btn:hover {
            background: #333333;
            transform: scale(1.02);
        }

        .popup-btn:active {
            background: #000000;
            transform: scale(0.98);
        }

        .popup-checkbox-container {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 8px;
            cursor: pointer;
            user-select: none;
        }

        .popup-checkbox {
            width: 18px;
            height: 18px;
            border-radius: 50%;
            border: 2px solid #d1d1d6;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            flex-shrink: 0;
        }

        .popup-checkbox.checked {
            background: #1d1d1f;
            border-color: #1d1d1f;
        }

        .popup-checkbox.checked::after {
            content: '';
            width: 8px;
            height: 8px;
            background: white;
            border-radius: 50%;
        }

        .popup-checkbox-label {
            font-size: 14px;
            color: #86868b;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            transition: color 0.2s ease;
        }

        .popup-checkbox-container:hover .popup-checkbox-label {
            color: #636366;
        }

        .popup-checkbox-container:hover .popup-checkbox {
            border-color: #999;
        }
	</style>
</head>
<body>
      <?php if ($config['popup_enabled'] && !isset($_COOKIE['popup_hide'])): ?>
    <div class="popup-overlay active" id="popup">
        <div class="popup-content">
            <h3 class="popup-title"><?php echo htmlspecialchars($config['popup_title']); ?></h3>
            <p class="popup-body"><?php echo htmlspecialchars($config['popup_content']); ?></p>
            <button class="popup-btn" onclick="document.getElementById('popup').classList.remove('active')">
                我知道了
            </button>
            <div class="popup-checkbox-container" onclick="toggleHideToday()">
                <div class="popup-checkbox" id="hideTodayCheckbox"></div>
                <span class="popup-checkbox-label">今日不再提醒</span>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <div class="XE-app">
	<div class="container">
		<!-- 主页容器 -->
		<div class="page-container">
			<!-- 动态壁纸Canvas -->
			<canvas id="live-wallpaper-canvas" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 0; pointer-events: none; will-change: transform;"></canvas>
			<video id="live-wallpaper-video" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 0; pointer-events: none; object-fit: cover; display: none; will-change: transform;" loop muted playsinline></video>
			<!-- 主页 -->
			<div id="home-page" class="page active">
				<!-- 头部 -->
				<header class="header">
					<h1><?php echo htmlspecialchars($config['site_name'] ?: '喜乐'); ?> 客服系统</h1>
					<button class="settings-btn" onclick="openSettingsModal()">
						<i class="bi bi-gear-fill"></i>
					</button>
				</header>
				<!-- 主内容区域 -->
				<main class="main-content">
				    
					<!-- 用户信息卡片 -->
					<div class="card profile-card">
						<!-- 账号行 -->
						<div class="profile-row">
							<span class="profile-row-label">账号：</span>
							<span class="profile-row-value"><?php echo $isTokenLogin ? '***' : htmlspecialchars($username); ?></span>
							<div class="profile-row-right">
								<span class="profile-role-tag"><?php echo htmlspecialchars($XEroles); ?></span>
								<i class="bi bi-chevron-right profile-row-arrow"></i>
							</div>
						</div>
						<!-- 到期行 -->
						<div class="profile-row">
							<span class="profile-row-label">到期：</span>
							<span class="profile-row-value"><?php echo $XErole === 'admin' ? '永久' : htmlspecialchars($expire_time); ?></span>
							<div class="profile-row-right">
								<span class="profile-expire-tag<?php echo $is_expired ? ' expired' : ''; ?>"><?php echo $XErole === 'admin' ? '永久' : $expire_days . '天'; ?></span>
								<i class="bi bi-chevron-right profile-row-arrow"></i>
							</div>
						</div>
						<!-- 余额行 -->
						<div class="profile-row" onclick="refreshBalance()" style="cursor:pointer;">
							<span class="profile-row-label">余额：</span>
							<span class="profile-row-value"><?php echo htmlspecialchars($balance); ?>元</span>
							<div class="profile-row-right">
								<span class="profile-refresh-text">点击刷新</span>
								<i class="bi bi-chevron-right profile-row-arrow"></i>
							</div>
						</div>
					</div>
					<?php if (!empty($config['telegram_username'])): ?>
					<!-- 推广信息卡片 -->
					<div class="promo-card" onclick="window.open('https://t.me/<?php echo htmlspecialchars($config['telegram_username']); ?>', '_blank')">
						<div class="promo-card-icon">
							<i class="bi bi-telegram"></i>
						</div>
						<div class="promo-card-text">
							<h4>Telegram 频道</h4>
							<p>@<?php echo htmlspecialchars($config['telegram_username']); ?></p>
						</div>
						<i class="bi bi-chevron-right" style="color:#d97706;font-size:16px;"></i>
					</div>
					<?php endif; ?>
					<!-- 功能网格 -->
					<div class="function-grid">
						<!-- 分享图 -->
						<div class="function-card <?php echo $is_expired ? 'expired' : ''; ?>" <?php echo $is_expired ? 'style="pointer-events:none;"' : ''; ?> onclick="<?php echo $is_expired ? 'return false;' : 'openPageModal(\'/consle/share\')'; ?>">
							<div class="function-icon-circle gradient-blue">
								<i class="bi bi-qr-code"></i>
							</div>
							<h3>分享图</h3>
						</div>
						<!-- 付款图 -->
						<div class="function-card <?php echo $is_expired ? 'expired' : ''; ?>" <?php echo $is_expired ? 'style="pointer-events:none;"' : ''; ?> onclick="<?php echo $is_expired ? 'return false;' : 'openPageModal(\'/consle/fukuan\')'; ?>">
							<div class="function-icon-circle gradient-green">
								<i class="bi bi-images"></i>
							</div>
							<h3>付款图</h3>
						</div>
						<!-- 风控图 -->
						<div class="function-card <?php echo $is_expired ? 'expired' : ''; ?>" <?php echo $is_expired ? 'style="pointer-events:none;"' : ''; ?> onclick="<?php echo $is_expired ? 'return false;' : 'openPageModal(\'/consle/fengkong\')'; ?>">
							<div class="function-icon-circle gradient-orange">
								<i class="bi bi-exclamation-triangle"></i>
							</div>
							<h3>风控图</h3>
						</div>
						<!-- 设置 -->
						<div class="function-card" onclick="openSettingsModal()">
							<div class="function-icon-circle gradient-purple">
								<i class="bi bi-gear"></i>
							</div>
							<h3>设置</h3>
						</div>
					</div>
					<!-- 其他功能 -->
					<h2 class="section-title">更多功能</h2>
					<div class="other-functions">
						<div class="other-functions-grid">
							<!-- 鱼付款页 -->
							<div class="other-function-card <?php echo $is_expired ? 'expired' : ''; ?>" <?php echo $is_expired ? 'style="pointer-events:none;"' : ''; ?> onclick="<?php echo $is_expired ? 'return false;' : 'openPageModal(\'/fukuan\')'; ?>">
								<div class="function-icon-circle gradient-blue">
									<i class="bi bi-credit-card"></i>
								</div>
								<h3>鱼付款页</h3>
							</div>
							<!-- 鱼代付页 -->
							<div class="other-function-card <?php echo $is_expired ? 'expired' : ''; ?>" <?php echo $is_expired ? 'style="pointer-events:none;"' : ''; ?> onclick="<?php echo $is_expired ? 'return false;' : 'openPageModal(\'/daifu\')'; ?>">
								<div class="function-icon-circle gradient-green">
									<i class="bi bi-currency-exchange"></i>
								</div>
								<h3>鱼代付页</h3>
							</div>
							<!-- 聊天室 -->
							<div class="other-function-card <?php echo $is_expired ? 'expired' : ''; ?>" <?php echo $is_expired ? 'style="pointer-events:none;"' : ''; ?> onclick="<?php echo $is_expired ? 'return false;' : 'openPageModal(\'/group\')'; ?>">
								<div class="function-icon-circle gradient-orange">
									<i class="bi bi-chat-dots"></i>
								</div>
								<h3>群聊</h3>
							</div>
							<!-- 自定义客服 -->
							<div class="other-function-card <?php echo $is_expired ? 'expired' : ''; ?>" <?php echo $is_expired ? 'style="pointer-events:none;"' : ''; ?> onclick="<?php echo $is_expired ? 'return false;' : 'openPageModal(\'/kefu\')'; ?>">
								<div class="function-icon-circle gradient-purple">
									<i class="bi bi-headset"></i>
								</div>
								<h3>自定义客服</h3>
							</div>
							<!-- 卡扣 -->
							<div class="other-function-card <?php echo $is_expired ? 'expired' : ''; ?>" <?php echo $is_expired ? 'style="pointer-events:none;"' : ''; ?> onclick="<?php echo $is_expired ? 'return false;' : 'openPageModal(\'/kakou\')'; ?>">
								<div class="function-icon-circle gradient-blue">
									<i class="bi bi-postcard"></i>
								</div>
								<h3>卡扣</h3>
							</div>
						</div>
					</div>
					
					<?php if ($XErole === 'admin'): ?>
					<!-- 其他功能 -->
				<h2 class="section-title">管理功能</h2>
<div class="other-functions">
	<div class="other-functions-grid">
		<!-- 系统设置 -->
		<div class="other-function-card" onclick="openPageModal('/root/websettings')">
			<div class="function-icon-circle gradient-purple">
				<i class="bi bi-gear-fill"></i>
			</div>
			<h3>系统设置</h3>
		</div>

		<!-- 接口管理 -->
		<div class="other-function-card" onclick="openPageModal('/root/antired')">
			<div class="function-icon-circle gradient-blue">
				<i class="bi bi-plug-fill"></i>
			</div>
			<h3>接口管理</h3>
		</div>

		<!-- AI模块 -->
		<div class="other-function-card" onclick="openPageModal('/root/aisettings')">
			<div class="function-icon-circle gradient-green">
				<i class="bi bi-robot"></i>
			</div>
			<h3>AI模块</h3>
		</div>

		<!-- 用户管理 -->
		<div class="other-function-card" onclick="openPageModal('/root/users')">
			<div class="function-icon-circle gradient-orange">
				<i class="bi bi-people-fill"></i>
			</div>
			<h3>用户管理</h3>
		</div>

		<!-- 充值审核 -->
		<div class="other-function-card" onclick="openPageModal('/root/payreview')">
			<div class="function-icon-circle gradient-blue">
				<i class="bi bi-check-circle-fill"></i>
			</div>
			<h3>充值审核</h3>
		</div>

		<!-- 访问详情 -->
		<div class="other-function-card" onclick="openPageModal('/root/ipreview')">
			<div class="function-icon-circle gradient-green">
				<i class="bi bi-eye-fill"></i>
			</div>
			<h3>访问日志</h3>
		</div>

		<!-- 系统日志 -->
		<div class="other-function-card" onclick="openPageModal('/root/systemlog')">
			<div class="function-icon-circle gradient-orange">
				<i class="bi bi-journal-text"></i>
			</div>
			<h3>系统日志</h3>
		</div>

			<!-- 系统监控 -->
		<div class="other-function-card" onclick="openPageModal('/root/monitoring')">
			<div class="function-icon-circle gradient-purple">
				<i class="bi bi-laptop"></i>
			</div>
			<h3>系统监控</h3>
		</div>

		<!-- 分享记录 -->
		<div class="other-function-card" onclick="openPageModal('/root/linklog')">
			<div class="function-icon-circle gradient-blue">
				<i class="bi bi-share-fill"></i>
			</div>
			<h3>分享记录</h3>
		</div>

		<!-- 聊天记录 -->
		<div class="other-function-card" onclick="openPageModal('/root/msglog')">
			<div class="function-icon-circle gradient-green">
				<i class="bi bi-chat-left-text-fill"></i>
			</div>
			<h3>聊天记录</h3>
		</div>

		<!-- 资源管理 -->
		<div class="other-function-card" onclick="openPageModal('/root/images')">
			<div class="function-icon-circle gradient-orange">
				<i class="bi bi-images"></i>
			</div>
			<h3>资源管理</h3>
		</div>
	</div>
</div>
					<?php endif; ?>
				</main>
			</div>
			<!-- 聊天页面 -->
			<div id="chat-page" class="page">
				<div class="chat-container<?php echo $is_expired ? ' expired' : ''; ?>">
					<?php if ($is_expired): ?>
					<div class="expired-message">
						该账户已过期
					</div>
					<?php else: ?>
					<div class="header">
						<h1>聊天&nbsp;</h1>
						<div class="online-tip" id="online-tip">加载中...</div>
					</div>
					<!-- 工具栏 -->
					<div class="toolbar">
						<div class="searchbox">
							<i class="bi bi-search ico"></i>
							<input class="search" id="q" type="text" placeholder="搜索客户名或聊天记录..." autocomplete="off" spellcheck="false">
						</div>
						<div class="chat-type-tabs">
							<div class="tab-slider" data-active="1"></div>
							<button class="chat-type-tab active" data-type="private" onclick="switchChatType('private')">全部</button>
							<button class="chat-type-tab" data-type="group" onclick="switchChatType('group')">群聊</button>
						</div>
					</div>
					<div class="session-list-container" id="session-list-container">
						<div class="session-list" id="session-list">
							<!-- 这里将动态加载内容 -->
							<div class="empty-tip">
								<i class="bi bi-chat-dots" style="font-size: 48px; margin-bottom: 16px; opacity: 0.5;"></i>
								<h3>加载中...</h3>
								<p>正在加载会话列表，请稍候</p>
							</div>
						</div>
					</div>
					<?php endif; ?>
				</div>
			</div>
		</div>

	</div>

	</div>
	
		<!-- 底部导航 -->
	<div class="bottom-nav<?php echo $is_expired ? ' expired' : ''; ?>" style="display: flex;">
		<a href="javascript:void(0)" class="nav-item active" data-page="home" onclick="<?php echo $is_expired ? 'return false;' : "switchPage('home')"; ?>">
			<div class="nav-icon"><i class="bi bi-house-door"></i></div>
			<div class="nav-label">主页</div>
		</a>
		<a href="javascript:void(0)" class="nav-item" data-page="chat" onclick="<?php echo $is_expired ? 'return false;' : "switchPage('chat')"; ?>">
			<div class="nav-icon"><i class="bi bi-chat-left-dots"></i></div>
			<div class="nav-label">聊天</div>
		</a>
		<a href="javascript:void(0)" class="nav-item" onclick="<?php echo $is_expired ? 'return false;' : "openPageModal('/consle/settings')"; ?>">
			<div class="nav-icon"><i class="bi bi-gear"></i></div>
			<div class="nav-label">设置</div>
		</a>
	</div>
	<!-- 弹窗 -->
	<div id="pageModal" class="page-modal">
		<div class="app-loading" id="loaderContainer">
			<div class="app-loading__loader"></div>
			<div class="app-loading__title">加载中</div>
		</div>
		<iframe id="pageIframe" class="page-iframe" frameborder="0" allow="fullscreen"></iframe>
	</div>
	<!-- 设置从下到上弹窗 -->
	<div id="settingsModal" class="bottom-modal">
		<div class="bottom-modal-content">
			<div class="modal-header">
				<h3>设置</h3>
				<button class="close-modal" onclick="closeSettingsModal()">
					<i class="bi bi-x-lg"></i>
				</button>
			</div>
			<div class="modal-body">
				<div class="settings-options<?php echo $is_expired ? ' expired' : ''; ?>">
				    <div class="setting-item <?php echo $is_expired ? 'disabled' : ''; ?>" onclick="<?php echo $is_expired ? 'return false;' : "openFromSettings('/consle/antired')"; ?>">
    <div class="setting-item-left">
        <div class="setting-icon">
            <i class="bi bi-shield-lock"></i>
        </div>
        <div class="setting-text">
            <h4>防红配置</h4>
        </div>
    </div>
    <i class="bi bi-chevron-right setting-arrow"></i>
</div>
				    
<div class="setting-item <?php echo $is_expired ? 'disabled' : ''; ?>" onclick="<?php echo $is_expired ? 'return false;' : "openFromSettings('/consle/settings')"; ?>">
    <div class="setting-item-left">
        <div class="setting-icon">
            <i class="bi bi-gear"></i>
        </div>
        <div class="setting-text">
            <h4>全局设置</h4>
        </div>
    </div>
    <i class="bi bi-chevron-right setting-arrow"></i>
</div>

<?php if (!$isTokenLogin): ?>
<div class="setting-item <?php echo $is_expired ? 'disabled' : ''; ?>" onclick="<?php echo $is_expired ? 'return false;' : "openFromSettings('/consle/password')"; ?>">
    <div class="setting-item-left">
        <div class="setting-icon">
            <i class="bi bi-key"></i>
        </div>
        <div class="setting-text">
            <h4>修改密码</h4>
        </div>
    </div>
    <i class="bi bi-chevron-right setting-arrow"></i>
</div>
<?php endif; ?>
					
					<div class="setting-item <?php echo $is_expired ? 'disabled' : ''; ?>" onclick="<?php echo $is_expired ? 'return false;' : "openFromSettings('/consle/xufei')"; ?>">
						<div class="setting-item-left">
							<div class="setting-icon">
								<i class="bi bi-wallet2"></i>
							</div>
							<div class="setting-text">
								<h4>续费</h4>
							</div>
						</div>
						<i class="bi bi-chevron-right setting-arrow"></i>
					</div>
					
					<div class="setting-item" onclick="confirmLogout()">
						<div class="setting-item-left">
							<div class="setting-icon">
								<i class="bi bi-box-arrow-right"></i>
							</div>
							<div class="setting-text">
								<h4>退出登录</h4>
							</div>
						</div>
						<i class="bi bi-chevron-right setting-arrow"></i>
					</div>
				</div>
			</div>
		</div>
	</div>
	<!-- 聊天弹窗 -->
	<div id="chatModal" class="page-modal">
		<div class="app-loading" id="chatLoaderContainer">
			<div class="app-loading__loader"></div>
			<div class="app-loading__title">加载中</div>
		</div>
		<iframe id="chatIframe" class="page-iframe" frameborder="0" allow="fullscreen"></iframe>
	</div>
	<script src="/assets/jquery.min.js"></script>
	<script>
// ========== 全局变量定义 ==========
// 分页相关变量
var currentPage = 1;
var pageSize = 30;
var isLoading = false;
var hasMore = true;
var isSearching = false;

// 聊天类型过滤
var currentChatType = 'private'; // private, group

// 切换聊天类型
function switchChatType(type) {
    currentChatType = type;
    // 更新Tab样式
    document.querySelectorAll('.chat-type-tab').forEach(function(tab) {
        tab.classList.toggle('active', tab.dataset.type === type);
    });
    // 移动滑块
    var slider = document.querySelector('.tab-slider');
    var index = type === 'private' ? 1 : 2;
    slider.setAttribute('data-active', index);
    // 重新加载列表
    window.loadSessions(1);
}

// 弹窗状态
var isModalOpen = false;

function toggleHideToday() {
    var checkbox = document.getElementById('hideTodayCheckbox');
    checkbox.classList.toggle('checked');
    
    if (checkbox.classList.contains('checked')) {
        var today = new Date();
        today.setHours(23, 59, 59, 999);
        var expires = today.toUTCString();
        document.cookie = 'popup_hide=1; expires=' + expires + '; path=/';
    } else {
        document.cookie = 'popup_hide=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/';
    }
}

// 搜索相关
var searchTimeout = null;
var refreshInterval = null;
var onlineStatusInterval = null; // 在线状态刷新定时器

// 页面状态管理
var appState = {
    modalOpen: false,
    currentPage: 'home',
    isSwitchingPage: false
};

// 在线状态管理器
var onlineStatusManager = {
    refreshInterval: 10000, // 10秒刷新一次在线状态
    isRefreshing: false,
    timer: null,
    
    init: function() {
        console.log('在线状态管理器初始化');
        this.startRefreshing();
        
        // 页面卸载时清理
        window.addEventListener('beforeunload', () => this.stopRefreshing());
    },
    
    startRefreshing: function() {
        this.stopRefreshing();
        
        // 立即刷新一次
        this.refreshOnlineStatus();
        
        // 设置定时器
        this.timer = setInterval(() => {
            this.refreshOnlineStatus();
        }, this.refreshInterval);
        
        console.log('在线状态刷新已启动，每' + (this.refreshInterval/1000) + '秒刷新一次');
    },
    
    stopRefreshing: function() {
        if (this.timer) {
            clearInterval(this.timer);
            this.timer = null;
        }
    },
    
    // 刷新在线状态
    refreshOnlineStatus: function() {
        if (this.isRefreshing) {
            console.log('在线状态刷新中，跳过');
            return;
        }
        
        var sessionWrappers = $('.session-item-wrapper:visible');
        if (sessionWrappers.length === 0) {
            console.log('没有会话项需要刷新');
            return;
        }
        
        var customers = [];
        var sessionKeys = [];
        
        sessionWrappers.each(function() {
            var customer = $(this).data('customer');
            var sessionKey = $(this).data('session-key');
            if (customer && customer.trim() && sessionKey && sessionKey.trim()) {
                customers.push(customer);
                sessionKeys.push(sessionKey);
            }
        });
        
        if (customers.length === 0) {
            console.log('没有有效的客户名称');
            return;
        }
        
        this.isRefreshing = true;
        
        // 使用与chat.php相同的API获取在线状态（批量查询）
        $.ajax({
            url: '/api/chat/messages?action=get_batch_online_status',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                usernames: customers
            }),
            dataType: 'json',
            timeout: 10000
        })
        .done((response) => {
            if (response.success && response.statuses) {
                this.updateSessionStatus(response.statuses);
                this.updateOnlineCount(response.statuses);
            } else {
                console.warn('获取在线状态失败:', response.message || '未知错误');
            }
        })
        .fail((xhr, status, error) => {
            console.error('刷新在线状态失败:', error, status);
        })
        .always(() => {
            this.isRefreshing = false;
        });
    },
    
    // 更新会话状态
    updateSessionStatus: function(statuses) {
        var onlineCount = 0;
        var hiddenCount = 0;
        
        $('.session-item-wrapper:visible').each(function() {
            var $wrapper = $(this);
            var $item = $wrapper.find('.session-item');
            var customer = $wrapper.data('customer');
            
            if (customer && statuses.hasOwnProperty(customer)) {
                var statusValue = statuses[customer];
                
                // 处理新的API返回格式（字符串或布尔值）
                var isOnline = false;
                var status = 'offline';
                
                if (statusValue === 'online') {
                    isOnline = true;
                    status = 'online';
                } else if (statusValue === 'hidden') {
                    isOnline = true;
                    status = 'hidden';
                } else if (statusValue === 'away') {
                    isOnline = false;
                    status = 'away';
                } else {
                    isOnline = false;
                    status = 'offline';
                }
                
                // 更新 data 属性
                $wrapper.data('online', isOnline);
                $wrapper.attr('data-online', isOnline);
                $wrapper.data('status', status);
                
                // 查找并更新状态显示元素
                var $statusEl = $item.find('.status-text');
                if ($statusEl.length) {
                    var statusClass = 'status-offline';
                    
                    switch(status) {
                        case 'online':
                            statusClass = 'status-online';
                            onlineCount++;
                            break;
                        case 'hidden':
                            statusClass = 'status-away';
                            hiddenCount++;
                            break;
                        case 'away':
                            statusClass = 'status-away';
                            break;
                        case 'offline':
                            statusClass = 'status-offline';
                            break;
                    }
                    
                    $statusEl.removeClass('status-online status-away status-offline')
                        .addClass(statusClass);
                }
                
                if (isOnline) onlineCount++;
            }
        });
        
        console.log('在线状态已更新，在线: ' + onlineCount + '人，离开: ' + hiddenCount + '人');
    },
    
    // 格式化时间差
    formatTimeAgo: function(seconds) {
        if (seconds < 60) {
            return seconds + '秒前';
        } else if (seconds < 3600) {
            return Math.floor(seconds / 60) + '分钟前';
        } else if (seconds < 86400) {
            return Math.floor(seconds / 3600) + '小时前';
        } else {
            return Math.floor(seconds / 86400) + '天前';
        }
    },
    
    // 更新在线人数
    updateOnlineCount: function(statuses) {
        var onlineCount = 0;
        var hiddenCount = 0;
        
        for (var customer in statuses) {
            if (statuses[customer].status === 'online') {
                onlineCount++;
            } else if (statuses[customer].status === 'hidden') {
                hiddenCount++;
            }
        }
        
        // 更新页面上的在线人数显示
        var totalText = onlineCount + '个在线';
        if (hiddenCount > 0) {
            totalText += ' (' + hiddenCount + '个离开)';
        }
        $('#online-tip').text(totalText);
    }
};

// 更新头像状态指示器
function updateAvatarStatus($sessionItem, status) {
    var $avatar = $sessionItem.find('.avatar');
    if (!$avatar.length) return;
    
    // 移除旧的状态类
    $avatar.removeClass('online away offline');
    
    // 添加新状态类
    switch(status) {
        case 'online':
            $avatar.addClass('online');
            break;
        case 'hidden':
            $avatar.addClass('away');
            break;
        case 'offline':
            $avatar.addClass('offline');
            break;
    }
}

// ========== 页面切换功能 ==========
function switchPage(pageName) {
    if (isModalOpen) {
        console.log('有弹窗打开，跳过页面切换');
        return;
    }
    
    if (appState.isSwitchingPage && appState.currentPage === pageName) {
        return;
    }
    
    appState.isSwitchingPage = true;
    
    const currentPage = document.querySelector('.page.active, .page.slide-enter-to');
    const targetPage = document.getElementById(pageName + '-page');
    
    if (!targetPage) {
        appState.isSwitchingPage = false;
        return;
    }
    
    // 如果有当前页面，触发离开动画
    if (currentPage && currentPage !== targetPage) {
        currentPage.classList.remove('active', 'slide-enter-from', 'slide-enter-to');
        currentPage.classList.add('slide-leave-active', 'slide-leave-from');
        
        // 触发重排
        void currentPage.offsetWidth;
        
        currentPage.classList.add('slide-leave-to');
        currentPage.classList.remove('slide-leave-from');
    }
    
    // 触发进入动画
    targetPage.classList.remove('slide-leave-active', 'slide-leave-to');
    targetPage.classList.add('slide-enter-active', 'slide-enter-from');
    
    // 触发重排
    void targetPage.offsetWidth;
    
    targetPage.classList.add('slide-enter-to');
    targetPage.classList.remove('slide-enter-from');
    
    appState.currentPage = pageName;
    
    // 如果是聊天页面，则加载会话
    if (pageName === 'chat') {
        setTimeout(() => {
            if (typeof window.loadSessions === 'function') {
                window.loadSessions(1);
            }
        }, 50);
    }
    
    // 更新导航栏状态
    document.querySelectorAll('.nav-item').forEach(nav => {
        nav.classList.remove('active');
        if (nav.dataset.page === pageName) {
            nav.classList.add('active');
        }
    });
        
        // 重置切换状态
    setTimeout(() => {
        appState.isSwitchingPage = false;
    }, 350);
}

// ========== 页面加载完成后执行 ==========
document.addEventListener('DOMContentLoaded', function() {
    // 确保初始状态正确
    isModalOpen = false;
    appState.modalOpen = false;
    appState.isSwitchingPage = false;
    
    // 确保底部导航可见
    const bottomNav = document.querySelector('.bottom-nav');
    if (bottomNav) {
        bottomNav.style.display = 'flex';
        bottomNav.style.transition = ''; // 恢复过渡动画
    }
    
        // 正常显示主页
        switchPage('home');
    
    // ========== 通用弹窗功能 ==========
    var pageModal = document.getElementById('pageModal');
    var pageIframe = document.getElementById('pageIframe');
    var loaderContainer = document.getElementById('loaderContainer');
    var isLoading = false;
    
   // 修改checkHashAndSwitchPage函数
function checkHashAndSwitchPage() {
    const hash = window.location.hash.replace('#', '');
    if (hash === 'chat') {
        // 临时禁用页面切换动画
        document.documentElement.classList.add('no-page-transition');
        
        setTimeout(() => {
            switchPage('chat');
            
            // 短时间后恢复动画
            setTimeout(() => {
                document.documentElement.classList.remove('no-page-transition');
            }, 100);
        }, 100);
        
        // 清除hash，避免刷新时重复执行
        setTimeout(() => {
            if (window.history && window.history.replaceState) {
                window.history.replaceState({}, document.title, window.location.pathname);
            }
        }, 200);
    }
}
    
    // 初始检查
    checkHashAndSwitchPage();
    
    // 监听hash变化
    window.addEventListener('hashchange', checkHashAndSwitchPage);
    
    // 显示加载动画
    function showLoader() {
        if (loaderContainer && !loaderContainer.classList.contains('hidden')) {
            return;
        }
        isLoading = true;
        if (loaderContainer) {
            loaderContainer.classList.remove('hidden');
        }
        if (pageIframe) {
            pageIframe.classList.remove('loaded');
        }
    }
    
    // 隐藏加载动画
    function hideLoader() {
        isLoading = false;
        if (loaderContainer) {
            loaderContainer.classList.add('hidden');
        }
        if (pageIframe) {
            setTimeout(function() {
                pageIframe.classList.add('loaded');
            }, 100);
        }
    }
    
    // 打开页面弹窗
    function openPageModal(pagePath) {
        if (pageModal && pageIframe) {
            // 立即隐藏导航栏
            const bottomNav = document.querySelector('.bottom-nav');
            bottomNav.style.display = 'none';
            bottomNav.style.transition = 'none';
            
            // 更新状态
            isModalOpen = true;
            appState.modalOpen = true;
            
            // 防止背景滚动
            document.documentElement.style.overflow = 'hidden';
            document.body.style.overflow = 'hidden';
            
            // 先设为可见（但仍在底部），触发重排后添加active执行动画
            pageModal.style.display = 'block';
            void pageModal.offsetWidth; // 强制重排
            pageModal.classList.add('active');
            
            // 显示加载动画
            showLoader();
            
            // 立即加载页面
            pageIframe.src = pagePath;
            
            // 添加键盘ESC监听
            document.addEventListener('keydown', handleEscapeKey);
            
            // 监听iframe加载完成
            pageIframe.onload = function() {
                hideLoader();
            };
            
            // 设置加载超时
            setTimeout(function() {
                if (isLoading) {
                    hideLoader();
                }
            }, 10000);
        }
    }
    
    // 关闭页面弹窗
    function closePageModal() {
        if (pageModal && isModalOpen) {
            isLoading = false;
            
            // 移除active触发滑出动画
            pageModal.classList.remove('active');
            isModalOpen = false;
            appState.modalOpen = false;
            
            // 等动画完成后隐藏
            setTimeout(function() {
                pageModal.style.display = 'none';
                pageIframe.src = '';
            }, 250);
            
            // 立即显示底部导航
            const bottomNav = document.querySelector('.bottom-nav');
            bottomNav.style.display = 'flex';
            bottomNav.style.transition = '';
            
            document.documentElement.style.overflow = '';
            document.body.style.overflow = '';
            
            hideLoader();
            document.removeEventListener('keydown', handleEscapeKey);
            
            setTimeout(() => {
                switchPage('home');
            }, 10);
        }
    }
    
    // ESC键处理函数
    function handleEscapeKey(e) {
        if (e.key === 'Escape' && pageModal.classList.contains('active')) {
            closePageModal();
        }
    }
    
    // 监听浏览器返回按钮
    window.addEventListener('popstate', function(event) {
        if (isModalOpen) {
            closePageModal();
        }
    });
    
    // 点击弹窗内容区域外部关闭弹窗
    pageModal.addEventListener('click', function(e) {
        if (e.target === pageModal) {
            closePageModal();
        }
    });
    
    // 监听iframe内部的消息
    window.addEventListener('message', function(event) {
        if (event.data === 'closeModal') {
            closePageModal();
        }
        
        if (event.data === 'closeChatModal') {
            closeChatModal();
        }
        
        if (event.data === 'pageLoaded') {
            hideLoader();
        }
        
        // 处理删除会话的消息
        if (event.data && event.data.type === 'REMOVE_SESSION') {
            const sessionKey = event.data.sessionKey;
            console.log('🗑️ [首页] 收到移除会话请求:', sessionKey);
            
            // 从聊天列表中移除该会话
            const sessionWrapper = $(`.session-item-wrapper[data-session-key="${sessionKey}"]`);
            if (sessionWrapper.length > 0) {
                sessionWrapper.addClass('swiped-out');
                setTimeout(function() {
                    sessionWrapper.remove();
                    console.log('✅ [首页] 已移除会话:', sessionKey);
                    
                    // 检查是否还有会话，如果没有显示空状态
                    if ($('.session-item-wrapper:visible').length === 0) {
                        // 重新加载空状态或显示提示
                        console.log('📭 所有会话已删除');
                    }
                }, 300);
            }
        }
         // 处理刷新会话列表的消息
        if (event.data && event.data.type === 'REFRESH_SESSION_LIST') {
            console.log('🔄 [首页] 收到刷新会话列表请求');
            
            // 重新加载会话列表
            if (typeof loadSessions === 'function') {
                loadSessions();
            }
        }
    });
    
    window.openPageModal = openPageModal;
    window.closePageModal = closePageModal;
    
    // ========== 从下到上弹窗功能 ==========
    
    // 设置弹窗
    var settingsModal = document.getElementById('settingsModal');
    var settingsModalContent = settingsModal ? settingsModal.querySelector('.bottom-modal-content') : null;
    
    function openSettingsModal() {
        if (settingsModal) {
            settingsModal.style.display = 'flex';
            // 强制重排，触发动画
            void settingsModal.offsetWidth;
            setTimeout(() => {
                settingsModal.classList.add('active');
            }, 10);
            
            document.documentElement.style.overflow = 'hidden';
            document.body.style.overflow = 'hidden';
        }
    }
    
    function closeSettingsModal() {
        if (settingsModal && settingsModal.classList.contains('active')) {
            settingsModal.classList.remove('active');
            // 等待动画完成后隐藏
            setTimeout(() => {
                if (!settingsModal.classList.contains('active')) {
                    settingsModal.style.display = 'none';
                    document.documentElement.style.overflow = '';
                    document.body.style.overflow = '';
                }
            }, 300);
        }
    }

    // 从下到上弹窗点击外部关闭
    var bottomModals = document.querySelectorAll('.bottom-modal');
    bottomModals.forEach(function(modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                if (modal.id === 'settingsModal') {
                    closeSettingsModal();
                }
            }
        });
    });

    // 从设置弹窗打开页面
    function openFromSettings(pagePath) {
        if (window.closeSettingsModal) {
            closeSettingsModal();
        }
        setTimeout(function() {
            if (window.openPageModal) {
                openPageModal(pagePath);
            }
        }, 300);
    }

    // 导出弹窗函数到全局
    window.openSettingsModal = openSettingsModal;
    window.closeSettingsModal = closeSettingsModal;
    window.openFromSettings = openFromSettings;
    
    // ========== 聊天弹窗功能 ==========
    var chatModal = document.getElementById('chatModal');
    var chatIframe = document.getElementById('chatIframe');
    var chatLoaderContainer = document.getElementById('chatLoaderContainer');
    var isChatLoading = false;
    
    window.openChatModal = function(sessionKey, customerName) {
        if (chatModal && chatIframe) {
            const bottomNav = document.querySelector('.bottom-nav');
            bottomNav.style.display = 'none';
            bottomNav.style.transition = 'none';
            
            chatIframe.src = '';
            
            isModalOpen = true;
            appState.modalOpen = true;
            
            chatModal.classList.add('active');
            
            document.documentElement.style.overflow = 'hidden';
            document.body.style.overflow = 'hidden';
            
            if (chatLoaderContainer) {
                chatLoaderContainer.classList.remove('hidden');
            }
            if (chatIframe) {
                chatIframe.classList.remove('loaded');
            }
            isChatLoading = true;
            
            setTimeout(function() {
                var chatUrl = '/consle/chat?XEid=' + decodeURIComponent(sessionKey) + '&customer=' + decodeURIComponent(customerName);
                chatIframe.src = chatUrl;
                
                document.addEventListener('keydown', handleChatEscapeKey);
                
                chatIframe.onload = function() {
                    if (chatLoaderContainer) {
                        chatLoaderContainer.classList.add('hidden');
                    }
                    if (chatIframe) {
                        setTimeout(function() {
                            chatIframe.classList.add('loaded');
                        }, 100);
                    }
                    isChatLoading = false;
                };
                
                setTimeout(function() {
                    if (isChatLoading) {
                        if (chatLoaderContainer) {
                            chatLoaderContainer.classList.add('hidden');
                        }
                        if (chatIframe) {
                            chatIframe.classList.add('loaded');
                        }
                        isChatLoading = false;
                    }
                }, 10000);
            }, 10);
        }
    };
    
    window.closeChatModal = function() {
        if (chatModal && isModalOpen) {
            isChatLoading = false;
            
            chatModal.classList.remove('active');
            isModalOpen = false;
            appState.modalOpen = false;
            
            const bottomNav = document.querySelector('.bottom-nav');
            bottomNav.style.display = 'flex';
            bottomNav.style.transition = '';
            
            document.documentElement.style.overflow = '';
            document.body.style.overflow = '';
            
            chatIframe.src = '';
            
            if (chatLoaderContainer) {
                chatLoaderContainer.classList.add('hidden');
            }
            if (chatIframe) {
                chatIframe.classList.remove('loaded');
            }
            
            document.removeEventListener('keydown', handleChatEscapeKey);
            
            setTimeout(() => {
                switchPage('chat');
            }, 10);
        }
    };
    
    function handleChatEscapeKey(e) {
        if (e.key === 'Escape' && chatModal.classList.contains('active')) {
            closeChatModal();
        }
    }
    
    chatModal.addEventListener('click', function(e) {
        if (e.target === chatModal) {
            closeChatModal();
        }
    });
    
    // ========== 聊天室页面功能 ==========
    // 监听会话数据加载完成事件
    $(document).on('sessionsLoaded', function() {
        // 渲染会话标签
        renderSessionTags();
        // 排序置顶会话
        sortStickySessions();
    });
    
    // 数据加载完成后绑定事件
    $(document).on('sessionsLoaded', function() {
        console.log('会话数据加载完成');
        
        // 绑定会话事件
        bindSessionEvents();
        
        // 初始化客户备注
        initCustomerNotes();
        
        // 隐藏已删除的会话
        hideDeletedSessions();
        
        // 更新会话统计
        updateSessionStats();
    });
    
    // 搜索功能
    var searchTimer;
    $('#q').on('input', function() {
        clearTimeout(searchTimer);
        var searchTerm = $(this).val().trim();
        isSearching = searchTerm.length > 0; // 更新搜索状态
        searchTimer = setTimeout(function() {
            window.loadSessions(1);
        }, 500);
    });
    
    // ========== 头像上传功能 ==========
    var avatarUpload = document.getElementById('avatarUpload');
    if (avatarUpload) {
        avatarUpload.addEventListener('change', function(e) {
            var file = e.target.files[0];
            if (file) {
                // 检查文件类型
                if (!file.type.match('image.*')) {
                    showToast('请选择图片文件');
                    return;
                }
                
                // 检查文件大小（限制为5MB）
                if (file.size > 5 * 1024 * 1024) {
                    showToast('图片大小不能超过5MB');
                    return;
                }
                
                var reader = new FileReader();
                reader.onload = function(e) {
                    var avatarImg = document.getElementById('userAvatar');
                    avatarImg.src = e.target.result;
                    
                    // 保存到localStorage
                    try {
                        localStorage.setItem('user_avatar', e.target.result);
                        console.log('头像已保存到本地存储 - XE网络科技');
                    } catch (err) {
                        console.error('保存头像到本地存储失败:', err);
                    }
                };
                reader.readAsDataURL(file);
            }
        });
    }
    
    // 页面加载时尝试从localStorage加载头像
    function loadAvatarFromStorage() {
        try {
            var savedAvatar = localStorage.getItem('user_avatar');
            if (savedAvatar) {
                var avatarImg = document.getElementById('userAvatar');
                avatarImg.src = savedAvatar;
            }
        } catch (err) {
            console.error('从本地存储加载头像失败:', err);
        }
    }
    
    // 页面加载完成后加载头像
    loadAvatarFromStorage();
    
    // ========== 工具函数 ==========
    
    // 复制文本函数
    function copyText(text) {
        // 先尝试现代API
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(() => {
            }).catch(err => {
                console.error('使用clipboard API复制失败: ', err);
                // 如果现代API失败，回退到传统方法
                fallbackCopyText(text);
            });
        } else {
            // 如果浏览器不支持clipboard API，使用传统方法
            fallbackCopyText(text);
        }
    }
    
    // 传统复制方法
    function fallbackCopyText(text) {
        var textArea = document.createElement("textarea");
        
        // 设置样式，避免影响页面
        textArea.style.position = 'fixed';
        textArea.style.top = '0';
        textArea.style.left = '0';
        textArea.style.width = '2em';
        textArea.style.height = '2em';
        textArea.style.padding = '0';
        textArea.style.border = 'none';
        textArea.style.outline = 'none';
        textArea.style.boxShadow = 'none';
        textArea.style.background = 'transparent';
        
        textArea.value = text;
        document.body.appendChild(textArea);
        
        // 选择文本
        textArea.select();
        textArea.setSelectionRange(0, 99999); // 移动设备支持
        
        try {
            var successful = document.execCommand('copy');
            if (successful) {
            } else {
                alert('复制失败，请手动复制账号');
            }
        } catch (err) {
            console.error('传统复制方法失败: ', err);
            alert('复制失败，请手动复制账号: ' + text);
        } finally {
            // 清理DOM
            document.body.removeChild(textArea);
        }
    }
    
    // 显示提示
    function showToast(message, duration, type) {
        duration = duration || 2000;
        type = type || 'success';
        
        // 定义不同类型对应的背景色和图标
        var typeClass = 'toast-info';
        var iconHtml = '<i class="bi bi-info-circle"></i>';
        
        switch(type) {
            case 'success':
                typeClass = 'toast-success';
                iconHtml = '<i class="bi bi-check-circle-fill"></i>';
                break;
            case 'error':
                typeClass = 'toast-error';
                iconHtml = '<i class="bi bi-x-circle-fill"></i>';
                break;
            case 'warning':
                typeClass = 'toast-warning';
                iconHtml = '<i class="bi bi-exclamation-triangle-fill"></i>';
                break;
            case 'info':
                typeClass = 'toast-info';
                iconHtml = '<i class="bi bi-info-circle-fill"></i>';
                break;
        }
        
        // 创建通知元素
        var toastId = 'toast-' + Date.now();
        var toast = $(
            '<div id="' + toastId + '" class="new-user-toast notification-enter-active ' + typeClass + '">' +
                '<div class="toast-icon">' + iconHtml + '</div>' +
                '<div class="toast-text">' + message + '</div>' +
                '<button class="toast-close"><i class="bi bi-x-lg"></i></button>' +
            '</div>'
        );
        
        // 添加到body
        $('body').append(toast);
        
        // 点击整个通知关闭
        toast.on('click', function(e) {
            if (!$(e.target).closest('.toast-close').length) {
                removeToast(toastId);
            }
        });
        
        // 点击关闭按钮关闭
        toast.find('.toast-close').on('click', function(e) {
            e.stopPropagation(); // 防止触发父元素的点击事件
            removeToast(toastId);
        });
        
        // 自动移除
        setTimeout(function() {
            removeToast(toastId);
        }, duration);
    }
    
    // 移除通知的函数
    function removeToast(id) {
        var $toast = $('#' + id);
        if ($toast.length) {
            $toast.removeClass('notification-enter-active').addClass('notification-leave-active');
            setTimeout(function() {
                if ($toast.parent().length) {
                    $toast.remove();
                }
            }, 300);
        }
    }
    
    // 绑定复制按钮
    var copyAccountBtn = document.getElementById('copyAccountBtn');
    if (copyAccountBtn) {
        copyAccountBtn.addEventListener('click', function() {
            var username = "<?php echo htmlspecialchars($username); ?>";
            copyText(username);
        });
    }
    
    // 确认退出
    function confirmLogout() {
        if (settingsModal && settingsModal.classList.contains('active')) {
            closeSettingsModal();
        }
        
        setTimeout(function() {
            if (confirm('您确定要退出当前账号吗？')) {
                // 登出时只取消浏览器端的Push订阅，不删除数据库记录
                // 因为同一设备切换账号时，新账号登录后会自动更新订阅绑定
                var unsubPromise = Promise.resolve();
                if (window.pushNotificationManager && window.pushNotificationManager.swRegistration) {
                    unsubPromise = window.pushNotificationManager.swRegistration.pushManager.getSubscription().then(function(sub) {
                        if (sub) {
                            return sub.unsubscribe().then(function() {
                                console.log('[登出] 浏览器端Push订阅已取消');
                            });
                        }
                    }).catch(function(err) {
                        console.warn('[登出] 取消浏览器Push订阅失败:', err);
                    });
                }
                
                // 等待取消订阅完成（最多2秒），然后再执行登出
                var timeoutPromise = new Promise(function(resolve) { setTimeout(resolve, 2000); });
                Promise.race([unsubPromise, timeoutPromise]).then(function() {
                    // 通过API执行登出
                    fetch('/api/sign/check?action=logout', {
                        method: 'POST',
                        credentials: 'same-origin'
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.code === 1) {
                            window.location.href = '/login';
                        } else {
                            alert('退出登录失败，请重试');
                        }
                    })
                    .catch(error => {
                        console.error('登出请求失败:', error);
                        alert('退出登录失败，请重试');
                    });
                });
            }
        }, 300);
    }
    
    // ESC键关闭模态框
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            if (settingsModal && settingsModal.classList.contains('active')) {
                closeSettingsModal();
            }

            if (pageModal && pageModal.classList.contains('active')) {
                closePageModal();
            }
            
            if (chatModal && chatModal.classList.contains('active')) {
                closeChatModal();
            }
        }
    });
    
    // 底部导航点击效果
    var navItems = document.querySelectorAll('.nav-item');
    navItems.forEach(function(item) {
        item.addEventListener('click', function(e) {
            if (this.getAttribute('href') === 'javascript:void(0)') {
                e.preventDefault();
                return;
            }
            
            navItems.forEach(function(navItem) {
                navItem.classList.remove('active');
            });
            
            this.classList.add('active');
        });
    });
    
    // 导出函数到全局
    window.switchPage = switchPage;
    window.confirmLogout = confirmLogout;
    
    // 初始化在线状态管理器
    onlineStatusManager.init();
});

// 加载会话函数
window.loadSessions = function(page = 1) {
    console.log('加载会话，第', page, '页');
    
    isLoading = true;
    
    var apiUrl = '/api/lib/msglist?page=' + page + '&limit=30&type=' + currentChatType + '&_=' + Date.now();
    var searchTerm = $('#q').val();
    
    if (searchTerm) {
        apiUrl += '&search=' + encodeURIComponent(searchTerm);
    }
    
    $.ajax({
        url: apiUrl,
        type: 'GET',
        dataType: 'json',
        timeout: 15000,
        success: function(response) {
            console.log('API响应成功，执行时间:', response.execution_time + 's');
            
            isLoading = false;
            
            if (response && response.success === true) {
                // 清空列表
                $('#session-list').empty();
                
                if (response.html) {
                    // 直接设置HTML，不再转换
                    $('#session-list').html(response.html);
                    
                    // 更新统计数据
                    if (response.online_count !== undefined) {
                        $('#online-tip').text(response.online_count + '个在线');
                    }
                    
                    // 更新分页状态
                    hasMore = response.has_more;
                    
                    // 初始化会话事件绑定
                    bindSessionEvents();
                    
                    // 渲染会话标签
                    renderSessionTags();
                    
                    // 排序置顶会话
                    sortStickySessions();
                    
                    // 初始化客户备注
                    initCustomerNotes();
                    
                    // 隐藏已删除的会话
                    hideDeletedSessions();
                    
                    // 更新会话统计
                    updateSessionStats();
                    
                    // 触发会话加载完成事件
                    $(document).trigger('sessionsLoaded');
                    
                    // 重新初始化滑动删除
                    setTimeout(function() {
                        initSwipeDelete();
                    }, 100);
                    
                    // 重新启动在线状态刷新
                    onlineStatusManager.startRefreshing();
                    
                    // 立即刷新一次在线状态
                    setTimeout(() => {
                        onlineStatusManager.refreshOnlineStatus();
                    }, 1000);
                } else {
                    $('#session-list').html('<div class="empty-tip"><i class="bi bi-chat-dots" style="font-size: 48px; margin-bottom: 16px; opacity: 0.5;"></i><h3>暂无客户会话</h3><p>等待新客户连接</p></div>');
                }
            } else {
                var errorMsg = response && response.error ? response.error : '未知错误';
                $('#session-list').html('<div class="empty-tip"><i class="bi bi-exclamation-triangle" style="font-size: 48px; margin-bottom: 16px; opacity: 0.5;"></i><h3>加载失败</h3><p>' + errorMsg + '</p><button onclick="loadSessions(1)" style="margin-top:10px; padding: 8px 16px; background: #007aff; color: white; border: none; border-radius: 8px; cursor: pointer;">重试</button></div>');
                showToast('加载失败: ' + errorMsg, 3000, 'error');
            }
        },
        error: function(xhr, status, error) {
            console.error('请求失败:', { status: status, error: error });
            
            var errorMsg = '网络错误';
            if (status === 'timeout') {
                errorMsg = '请求超时（15秒），请检查服务器或网络';
            }
            
            $('#session-list').html('<div class="empty-tip"><i class="bi bi-exclamation-triangle" style="font-size: 48px; margin-bottom: 16px; opacity: 0.5;"></i><h3>加载失败</h3><p>' + errorMsg + '</p><button onclick="window.loadSessions(1)" style="margin-top:10px; padding: 8px 16px; background: #007aff; color: white; border: none; border-radius: 8px; cursor: pointer;">重试</button></div>');
            showToast('加载失败: ' + errorMsg, 3000, 'error');
        },
        complete: function() {
            isLoading = false;
        }
    });
};

// 转换旧样式为新样式
function convertToNewStyle($oldItem) {
    var customerName = $oldItem.data('customer') || '';
    var sessionKey = $oldItem.data('session-key') || '';
    var isOnline = $oldItem.data('online') === 'true';
    var isPinned = $oldItem.data('pinned') === 'true';
    var isMuted = $oldItem.data('muted') === 'true';
    var lastMessage = $oldItem.find('.preview').text() || '点击开始对话';
    var lastTime = $oldItem.find('.time').text() || '';
    var avatarSrc = $oldItem.find('.ava img').attr('src') || '';
    var isGroupChat = $oldItem.find('.group-chat-badge').length > 0;
    
    // 构建新样式的HTML
    var $newItem = $('<a href="/consle/chat?XEid=' + sessionKey + '&customer=' + encodeURIComponent(customerName) + '" class="session-item' + (isPinned ? ' sticky' : '') + '" data-session-key="' + sessionKey + '" data-customer="' + customerName + '" data-online="' + isOnline + '" data-pinned="' + isPinned + '" data-muted="' + isMuted + '">');
    
    // 头像
    $newItem.append('<div class="avatar"><img src="' + (avatarSrc || '/assets/img/normal.png') + '" alt="' + customerName + '"></div>');
    
    // 内容区域
    var $content = $('<div class="session-content"></div>');
    var $sessionId = $('<div class="session-id"></div>');
    $sessionId.append('<span class="customer-name">' + customerName + '</span>');
    $sessionId.append('<span class="status-text ' + (isOnline ? 'status-online' : 'status-offline') + '"></span>');
    $content.append($sessionId);
    $content.append('<div class="last-message" title="' + lastMessage + '">' + (lastMessage.length > 50 ? lastMessage.substring(0, 50) + '...' : lastMessage) + '</div>');
    
    $newItem.append($content);
    
    // 时间
    $newItem.append('<div class="session-time">' + lastTime + '</div>');
    
    // 替换旧元素
    $oldItem.replaceWith($newItem);
}

// 更新会话统计函数
function updateSessionStats() {
    var visibleCount = $('.session-item-wrapper:visible').length;
    var onlineCount = $('.status-online').length;
    var awayCount = $('.status-away').length;
    
    var totalText = onlineCount + '/' + visibleCount;
    if (awayCount > 0) {
        totalText += ' (' + awayCount + '离开)';
    }
    $('#online-tip').text(totalText);
}

// ========== 客户备注功能 ==========
// 客户备注功能 - 合并版本
function initCustomerNotes() {
    $('.session-item-wrapper').each(function() {
        var $wrapper = $(this);
        var $this = $wrapper.find('.session-item');
        var customerName = $wrapper.data('customer');
        var sessionKey = $wrapper.data('session-key');
        
        // 优先获取聊天页面设置中的备注
        var chatSettingNote = '';
        try {
            var chatSetting = JSON.parse(localStorage.getItem(`chatSetting_${sessionKey}`)) || {};
            chatSettingNote = chatSetting.remark || '';
        } catch (error) {
            console.error('读取聊天设置失败:', error);
        }
        
        // 然后获取旧的备注系统
        var oldNote = getCustomerNote(customerName);
        
        // 确定最终使用的备注
        var finalNote = chatSettingNote || oldNote || '';
        
        if (finalNote) {
            // 先移除可能已存在的备注标签
            $this.find('.tag-remark').remove();
            
            // 添加新的备注标签
            var $customerName = $this.find('.customer-name');
            $customerName.after('<span class="tag tag-remark">(' + finalNote + ')</span>');
        }
    });
}

function getCustomerNote(customerName) {
    try {
        var notes = localStorage.getItem('agent_customer_notes');
        if (notes) {
            var notesObj = JSON.parse(notes);
            return notesObj[customerName] || '';
        }
    } catch (error) {
        console.error('获取备注失败:', error);
    }
    return '';
}

// ========== 置顶和免打扰功能 ==========
function togglePinSession(sessionKey, pin, button) {
    $.ajax({
        url: '/api/msg/?action=toggle_pin',
        type: 'POST',
        data: { session_key: sessionKey, pin: pin ? 1 : 0 },
        success: function(response) {
            if (response.success) {
                var item = button.closest('.session-item');
                var wrapper = button.closest('.session-item-wrapper');
                item.data('pinned', pin);
                
                if (pin) {
                    item.addClass('sticky');
                    button.attr('title', '取消置顶');
                    button.find('i').css('color', '#f59e0b');
                    var list = $('#session-list');
                    wrapper.prependTo(list);
                } else {
                    item.removeClass('sticky');
                    button.attr('title', '置顶聊天');
                    button.find('i').css('color', '');
                }
                showToast(pin ? '已置顶会话' : '已取消置顶', 1500, 'success');
            } else {
                showToast(response.message || '操作失败', 2000, 'error');
            }
        },
        error: function() {
            showToast('网络错误，请重试', 2000, 'error');
        }
    });
}

function toggleMuteSession(sessionKey, mute, button) {
    $.ajax({
        url: '/api/msg/?action=toggle_mute',
        type: 'POST',
        data: { session_key: sessionKey, mute: mute ? 1 : 0 },
        success: function(response) {
            if (response.success) {
                var item = button.closest('.session-item');
                item.data('muted', mute);
                
                if (mute) {
                    button.attr('title', '取消免打扰');
                    button.find('i').removeClass('bi-bell').addClass('bi-bell-slash');
                    item.find('.session-id').append('<span class="tag tag-no-disturb">免打扰</span>');
                } else {
                    button.attr('title', '消息免打扰');
                    button.find('i').removeClass('bi-bell-slash').addClass('bi-bell');
                    item.find('.tag-no-disturb').remove();
                }
                showToast(mute ? '已开启免打扰' : '已关闭免打扰', 1500, 'success');
            } else {
                showToast(response.message || '操作失败', 2000, 'error');
            }
        },
        error: function() {
            showToast('网络错误，请重试', 2000, 'error');
        }
    });
}

// ========== 删除功能 ==========
function getDeletedSessions() {
    try {
        var deleted = localStorage.getItem('deleted_sessions');
        return deleted ? JSON.parse(deleted) : [];
    } catch (error) {
        console.error('获取已删除会话失败:', error);
        return [];
    }
}

function setDeletedSessions(sessions) {
    try {
        localStorage.setItem('deleted_sessions', JSON.stringify(sessions));
    } catch (error) {
        console.error('保存已删除会话失败:', error);
    }
}

function hideDeletedSessions() {
    var deletedSessions = getDeletedSessions();
    $('.session-item-wrapper').each(function() {
        var sessionKey = $(this).data('session-key');
        if (deletedSessions.includes(sessionKey)) {
            $(this).addClass('swiped-out');
            setTimeout(() => {
                $(this).remove();
                updateSessionStats();
            }, 300);
        }
    });
}

function showDeleteConfirm(sessionKey, customerName) {
    var dialog = $('<div class="confirm-dialog">' +
        '<div class="dialog-content">' +
            '<div class="dialog-title">确认删除</div>' +
            '<div class="dialog-message">确定要删除与 <strong>' + customerName + '</strong> 的会话吗？</div>' +
            '<div class="dialog-actions">' +
                '<button class="dialog-btn cancel">取消</button>' +
                '<button class="dialog-btn confirm">确认删除</button>' +
            '</div>' +
        '</div>' +
    '</div>');
    
    $('body').append(dialog);
    dialog.hide().fadeIn(200);
    
    dialog.find('.cancel').on('click', function() {
        dialog.fadeOut(200, function() {
            $(this).remove();
        });
    });
    
    dialog.find('.confirm').on('click', function() {
        dialog.fadeOut(200, function() {
            $(this).remove();
            deleteSession(sessionKey);
        });
    });
    
    dialog.on('click', function(e) {
        if (e.target === dialog[0]) {
            dialog.fadeOut(200, function() {
                $(this).remove();
            });
        }
    });
}

function deleteSession(sessionKey) {
    var deletedSessions = getDeletedSessions();
    if (!deletedSessions.includes(sessionKey)) {
        deletedSessions.push(sessionKey);
        setDeletedSessions(deletedSessions);
    }
    
    executeSwipeDelete(sessionKey);
    showToast('会话已删除', 1000, 'success');
}

// ========== 左滑删除功能 ==========
var touchHandlers = {};

function initSwipeDelete() {
    var count = 0;
    
    $('.session-item-wrapper').each(function() {
        var wrapper = this;
        var $wrapper = $(wrapper);
        var sessionKey = $wrapper.data('session-key');
        
        // 每次都重新绑定，不清除旧的处理器
        count++;
        var touchStartX = 0;
        var touchStartY = 0;
        var touchCurrentX = 0;
        var isSwiping = false;
        var isVerticalSwipe = false;
        var sessionItem = $wrapper.find('.session-item');
        
        // 移除旧的事件监听器（如果有）
        $wrapper.off('touchstart touchmove touchend mousedown mousemove mouseup mouseleave');
        
        // 直接使用原生事件监听器
        sessionItem[0].removeEventListener('touchstart', onTouchStart);
        sessionItem[0].removeEventListener('touchmove', onTouchMove);
        sessionItem[0].removeEventListener('touchend', onTouchEnd);
        
        // 定义事件处理函数
        var onTouchStart = function(e) {
            if (isSwiping) return;
            touchStartX = e.touches[0].clientX;
            touchStartY = e.touches[0].clientY;
            touchCurrentX = touchStartX;
            isSwiping = true;
            isVerticalSwipe = false;
        };
        
        var onTouchMove = function(e) {
            if (!isSwiping) return;
            
            touchCurrentX = e.touches[0].clientX;
            var touchCurrentY = e.touches[0].clientY;
            var diffX = touchCurrentX - touchStartX;
            var diffY = touchCurrentY - touchStartY;
            
            // 判断是水平滑动还是垂直滑动
            if (!isVerticalSwipe) {
                // 如果垂直移动超过 20px 且大于水平移动，认为是垂直滑动
                if (Math.abs(diffY) > 20 && Math.abs(diffY) > Math.abs(diffX)) {
                    isVerticalSwipe = true;
                    return;
                }
                // 如果水平移动超过 10px，认为是水平滑动
                if (Math.abs(diffX) > 10) {
                    isVerticalSwipe = false;
                }
            }
            
            // 只有水平滑动时才阻止默认行为
            if (!isVerticalSwipe) {
                e.preventDefault();
            }
            
            // 只处理水平滑动
            if (isVerticalSwipe) return;
            
            if (diffX < 0 && diffX > -80) {
                sessionItem.css('transform', 'translateX(' + diffX + 'px)');
            }
        };
        
        var onTouchEnd = function(e) {
            if (!isSwiping || isVerticalSwipe) {
                isSwiping = false;
                isVerticalSwipe = false;
                return;
            }
            isSwiping = false;
            
            var diffX = touchCurrentX - touchStartX;
            console.log('滑动距离:', diffX + 'px');
            
            if (diffX < -60) {
                console.log('✅ 触发删除按钮显示');
                $wrapper.addClass('swiping');
                sessionItem.css('transform', 'translateX(-80px)');
            } else {
                console.log('❌ 滑动距离不足，重置');
                resetSwipe(wrapper, sessionItem);
            }
            
            touchStartX = 0;
            touchCurrentX = 0;
            isVerticalSwipe = false;
        };
        
        // 绑定触摸事件
        sessionItem[0].addEventListener('touchstart', onTouchStart, { passive: false });
        sessionItem[0].addEventListener('touchmove', onTouchMove, { passive: false });
        sessionItem[0].addEventListener('touchend', onTouchEnd, { passive: false });
        
        // 鼠标事件作为备用（桌面端）
        sessionItem.on('mousedown', function(e) {
            console.log('🖱️ mousedown 触发');
            if (isSwiping) return;
            touchStartX = e.clientX;
            touchCurrentX = touchStartX;
            isSwiping = true;
        });
        
        sessionItem.on('mousemove', function(e) {
            if (!isSwiping) return;
            touchCurrentX = e.clientX;
            var diff = touchCurrentX - touchStartX;
            
            if (diff < 0 && diff > -80) {
                sessionItem.css('transform', 'translateX(' + diff + 'px)');
            }
        });
        
        sessionItem.on('mouseup mouseleave', function(e) {
            console.log('🖱️ mouseup/mouseleave 触发');
            if (!isSwiping) return;
            isSwiping = false;
            
            var diff = touchCurrentX - touchStartX;
            console.log('滑动距离:', diff + 'px');
            
            if (diff < -60) {
                console.log('✅ 触发删除按钮显示');
                $wrapper.addClass('swiping');
                sessionItem.css('transform', 'translateX(-80px)');
            } else {
                console.log('❌ 滑动距离不足，重置');
                resetSwipe(wrapper, sessionItem);
            }
            
            touchStartX = 0;
            touchCurrentX = 0;
        });
    });
    
}

function resetSwipe(wrapper, sessionItem) {
    $(wrapper).removeClass('swiping');
    sessionItem.css('transform', '');
}

function handleDeleteClick(sessionKey, customerName) {
    // 先重置滑动状态
    var wrapper = $('.session-item-wrapper[data-session-key="' + sessionKey + '"]');
    var sessionItem = wrapper.find('.session-item');
    resetSwipe(wrapper[0], sessionItem);
    // 然后显示确认对话框
    showDeleteConfirm(sessionKey, customerName);
}

function executeSwipeDelete(sessionKey) {
    var wrapper = $('.session-item-wrapper[data-session-key="' + sessionKey + '"]');
    if (wrapper.length > 0) {
        wrapper.addClass('swiped-out');
        
        setTimeout(function() {
            wrapper.remove();
            updateSessionStats();
        }, 300);
    }
}

// ========== 事件绑定 ==========
function bindSessionEvents() {
    
    $('.pin-btn').off('click').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        var sessionKey = $(this).data('session');
        var isPinned = $(this).closest('.session-item').data('pinned') === 'true';
        togglePinSession(sessionKey, !isPinned, $(this));
    });
    
    $('.mute-btn').off('click').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        var sessionKey = $(this).data('session');
        var isMuted = $(this).closest('.session-item').data('muted') === 'true';
        toggleMuteSession(sessionKey, !isMuted, $(this));
    });
    
    $('.delete-btn').off('click').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        var sessionKey = $(this).data('session');
        var customerName = $(this).closest('.session-item').data('customer');
        showDeleteConfirm(sessionKey, customerName);
    });
    
    // 初始化左滑删除
    initSwipeDelete();
}

// 渲染会话标签 - 修改版本
function renderSessionTags() {
    $('.session-item-wrapper').each(function() {
        const $wrapper = $(this);
        const $this = $wrapper.find('.session-item');
        const sessionKey = $wrapper.data('session-key');
        const chatSetting = JSON.parse(localStorage.getItem(`chatSetting_${sessionKey}`)) || {
            top: false, 
            noDisturb: false
            // 移除了 remark 字段
        };
        
        // 只处理免打扰标签
        if (chatSetting.noDisturb) {
            $this.find('.session-id').append(`<span class="tag tag-no-disturb"><i class="bi bi-bell-slash"></i> 免打扰</span>`);
        }
        
        // 只处理置顶
        if (chatSetting.top) {
            $this.addClass('sticky');
            $this.find('.customer-name').prepend(`<i class="bi bi-pin-angle sticky-icon"></i>`);
        }
    });
}

// 排序置顶会话
function sortStickySessions() {
    const $sessionList = $('#session-list');
    const $stickyWrappers = $sessionList.find('.session-item-wrapper:has(.session-item.sticky)');
    const $normalWrappers = $sessionList.find('.session-item-wrapper:not(:has(.session-item.sticky))');
    $sessionList.empty().append($stickyWrappers).append($normalWrappers);
}

// 定时刷新（每5秒刷新一次，始终执行）
var refreshInterval = setInterval(function() {
    if (!isSearching) {
        window.loadSessions(1);
    }
}, 5000);

// 页面可见性变化时刷新
document.addEventListener('visibilitychange', function() {
    var currentPageEl = document.getElementById('chat-page');
    if (!document.hidden && currentPageEl && currentPageEl.classList.contains('active') && currentPage === 1 && !isSearching) {
        window.loadSessions(1);
    }
});

// 页面加载完成后初始化聊天功能
$(document).ready(function() {
    // 初始化在线状态管理器
    onlineStatusManager.init();
    
    // 启动会话列表刷新
    window.loadSessions(1);
    
    // 监听会话加载完成事件
    $(document).on('sessionsLoaded', function() {
        console.log('会话列表加载完成，重新绑定事件');
        // 可以在这里执行其他初始化操作
    });
});
</script>


<!-- 添加公共 WebSocket 连接 -->
<script src="/js/websocket-public.js?v=2"></script>
<!-- 添加消息通知 -->
<script src="/js/message-notification.js"></script>
<!-- 添加推送通知 -->
<script src="/js/push-notification.js?v=9"></script>
<script>
    // 等待 websocket-public.js 加载完成
    function initWebSocket() {
        if (typeof WebSocketManager === 'undefined') {
            console.log('[首页] 等待 WebSocketManager 加载...');
            setTimeout(initWebSocket, 100);
            return;
        }
        
        // 设置用户信息 - 使用正确的 session 字段
        const currentUser = {
            username: '<?php echo addslashes($currentAgent); ?>',
            role: '<?php echo addslashes($XErole); ?>',  // 使用 $XErole 而不是 $currentRole
            session_key: '<?php echo session_id(); ?>'
        };
        
        // 保存到全局变量
        window.currentUser = currentUser;
        
        // 保存到 sessionStorage（用于页面刷新后自动重连）
        sessionStorage.setItem('user_data', JSON.stringify({
            username: currentUser.username,
            role: currentUser.role,
            session_key: currentUser.session_key
        }));
        
        console.log('[首页] 已保存到 sessionStorage:', JSON.parse(sessionStorage.getItem('user_data')));
        
        // 手动映射 user_type - 能登录后台的用户都是客服
        let userType = 'agent';
        console.log('[首页] 映射后的用户类型:', userType);
        
        // 手动初始化 WebSocket，确保以正确身份连接
        if (!window.wsManager) {
            window.wsManager = new WebSocketManager({
                debug: true
            });
            
            console.log('[首页] 开始连接 WebSocket，参数：', {
                userType: userType,
                userId: currentUser.username,
                sessionKey: currentUser.session_key
            });
            
            window.wsManager.connect(userType, currentUser.username, currentUser.session_key);
        }
        
        // 初始化推送通知管理器
        function initPushNotification() {
            if (!window.pushNotificationManager) {
                // 检查用户是否启用了通知
                const notificationSettings = JSON.parse(localStorage.getItem('chat_notification_settings') || '{}');
                const enableBrowserNotification = notificationSettings.enabled === true;
                
                window.pushNotificationManager = new PushNotificationManager({
                    enableBrowserNotification: enableBrowserNotification,
                    enableCustomToast: true,
                    icon: '/favicon.png'
                });
                
                console.log('[首页] 推送通知管理器已初始化，浏览器通知:', enableBrowserNotification);
                
                // 如果用户之前开启过通知，自动重新订阅Push（处理切换账号场景）
                if (enableBrowserNotification && 'serviceWorker' in navigator && 'PushManager' in window) {
                    // iOS非PWA环境跳过自动订阅
                    var isIOSDevice = /iPhone|iPad|iPod/.test(navigator.userAgent) || 
                                     (navigator.userAgent.includes("Mac") && "ontouchend" in document);
                    var isStandalone = window.navigator.standalone === true || 
                                      window.matchMedia('(display-mode: standalone)').matches;
                    if (isIOSDevice && !isStandalone) {
                        console.log('[首页] iOS非PWA环境，跳过自动Push订阅');
                        return;
                    }
                    
                    // 通知权限已被拒绝则跳过
                    if ('Notification' in window && Notification.permission === 'denied') {
                        console.log('[首页] 通知权限已被拒绝，跳过自动Push订阅');
                        return;
                    }
                    
                    window.pushNotificationManager.registerServiceWorker().then(function(reg) {
                        if (reg) {
                            // 检查当前浏览器是否已有Push订阅
                            return reg.pushManager.getSubscription();
                        }
                        return null;
                    }).then(function(existingSub) {
                        if (existingSub) {
                            // 已有订阅，需要重新绑定到当前账号
                            console.log('[首页] 检测到已有Push订阅，重新绑定到当前账号...');
                            window.pushNotificationManager.subscribePush(currentUser.username, 'agent').then(function(result) {
                                if (result.success) {
                                    console.log('[首页] Push订阅已重新绑定到当前账号');
                                } else {
                                    console.warn('[首页] Push订阅重新绑定失败:', result.message);
                                }
                            });
                        } else {
                            // 没有订阅但用户开启了通知，需要重新订阅
                            console.log('[首页] 用户已开启通知但无Push订阅，重新订阅...');
                            window.pushNotificationManager.subscribePush(currentUser.username, 'agent').then(function(result) {
                                if (result.success) {
                                    console.log('[首页] Push订阅已创建');
                                } else {
                                    console.warn('[首页] Push订阅创建失败:', result.message);
                                }
                            });
                        }
                    }).catch(function(err) {
                        console.warn('[首页] 自动重新订阅Push失败:', err);
                    });
                }
            }
        }
        
        initPushNotification();
        
        // 监听Service Worker发来的推送消息（iOS前台时不显示系统横幅，需要页面自行处理）
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.addEventListener('message', function(event) {
                if (event.data && event.data.type === 'push-notification') {
                    var pushData = event.data.data;
                    console.log('[首页] 收到前台推送通知:', pushData);
                    
                    // 使用推送通知管理器显示应用内通知
                    if (window.pushNotificationManager) {
                        window.pushNotificationManager.showMessageNotification(
                            pushData.title || '来消息了',
                            pushData.body || '您有一条新消息',
                            pushData
                        );
                    }
                    
                    // 同时显示顶部toast通知
                    if (window.notificationManager) {
                        window.notificationManager.show((pushData.title || '来消息了') + ': ' + (pushData.body || '您有一条新消息'), {
                            type: 'success',
                            duration: 5000,
                            clickable: true,
                            onClick: function() {
                                if (pushData.url) {
                                    window.location.href = pushData.url;
                                }
                            }
                        });
                    }
                    
                    // 播放提示音
                    try {
                        var audio = new Audio('/assets/notification.mp3');
                        audio.volume = 0.5;
                        audio.play().catch(function() {});
                    } catch(e) {}
                }
            });
            
            // 验证Service Worker版本
            navigator.serviceWorker.ready.then(function(reg) {
                var messageChannel = new MessageChannel();
                messageChannel.port1.onmessage = function(event) {
                    console.log('[首页] Service Worker 版本:', event.data.version);
                };
                if (reg.active) {
                    reg.active.postMessage({ type: 'get-version' }, [messageChannel.port2]);
                }
            });
        }
        
        // 监听新消息
        window.addEventListener('websocket-new-message', function(event) {
            const data = event.detail;
            console.log('[首页] 收到新消息:', data);
            
            // 显示通知
            if (data.type === 'new_message' || data.content) {
                const sender = data.customer_name || data.sender || data.username || data.from || '客户';
                const content = data.content || data.message || '收到新消息';
                
                // 使用推送通知管理器发送通知
                if (window.pushNotificationManager) {
                    window.pushNotificationManager.showMessageNotification(sender, content, data);
                }
                
                // 兼容旧代码：也显示自定义顶部通知
                if (window.notificationManager) {
                    window.notificationManager.show(`${sender}: ${content}`, {
                        type: 'success',
                        duration: 5000,
                        clickable: true,
                        onClick: function() {
                            console.log('通知被点击，来自:', sender);
                        }
                    });
                }
            }
        });
        
        // 监听错误
        window.addEventListener('websocket-error', function(event) {
            console.error('[首页] WebSocket 错误:', event);
        });
    }
    
    // 页面加载完成后初始化
    $(document).ready(function() {
        initWebSocket();
    });
</script>

<!-- 动态壁纸脚本 -->
<script>
(function() {
    const STORAGE_KEY = 'chat_live_wallpaper_settings';
    let animationId = null;
    let lastFrameTime = 0;
    const TARGET_FPS = 30;
    const FRAME_INTERVAL = 1000 / TARGET_FPS;
    
    // 检测是否为低端设备
    function isLowEndDevice() {
        const memory = navigator.deviceMemory || 4;
        const cores = navigator.hardwareConcurrency || 4;
        return memory < 4 || cores < 4;
    }
    
    // 根据设备性能调整粒子数量
    function getParticleCount(width, height) {
        const baseCount = Math.floor((width * height) / 8000);
        return isLowEndDevice() ? Math.floor(baseCount * 0.3) : baseCount;
    }
    
    // 根据设备性能调整星星数量
    function getStarCount(width, height) {
        const baseCount = Math.floor((width * height) / 4000);
        return isLowEndDevice() ? Math.floor(baseCount * 0.3) : baseCount;
    }
    
    // 根据设备性能调整气泡数量
    function getBubbleCount(width, height) {
        const baseCount = Math.floor((width * height) / 15000);
        return isLowEndDevice() ? Math.floor(baseCount * 0.4) : baseCount;
    }

    function loadLiveWallpaperSettings() {
        const defaultSettings = { 
            enabled: false, 
            effect: 'particles',
            videoData: null,
            videoName: null,
            videoLoop: true,
            videoMute: true
        };
        try {
            const saved = localStorage.getItem(STORAGE_KEY);
            if (saved) {
                const parsed = JSON.parse(saved);
                // 合并保存的设置和默认值，确保所有字段都存在
                return { ...defaultSettings, ...parsed };
            }
            return defaultSettings;
        } catch (error) {
            console.error('加载动态壁纸设置失败:', error);
            return defaultSettings;
        }
    }

    function stopLiveWallpaper() {
        if (animationId) {
            cancelAnimationFrame(animationId);
            animationId = null;
        }
        const video = document.getElementById('live-wallpaper-video');
        if (video) {
            video.pause();
            video.style.display = 'none';
        }
    }

    function startParticlesWallpaper(ctx, width, height) {
        const particles = [];
        const particleCount = getParticleCount(width, height);
        const isLowEnd = isLowEndDevice();
        
        for (let i = 0; i < particleCount; i++) {
            particles.push({
                x: Math.random() * width,
                y: Math.random() * height,
                vx: (Math.random() - 0.5) * (isLowEnd ? 0.8 : 1.5),
                vy: (Math.random() - 0.5) * (isLowEnd ? 0.8 : 1.5),
                size: Math.random() * (isLowEnd ? 1.5 : 2.5) + 0.5,
                opacity: Math.random() * 0.5 + 0.2
            });
        }
        
        const animate = (currentTime) => {
            animationId = requestAnimationFrame(animate);
            
            // 帧率控制
            const delta = currentTime - lastFrameTime;
            if (delta < FRAME_INTERVAL) return;
            lastFrameTime = currentTime - (delta % FRAME_INTERVAL);
            
            ctx.fillStyle = 'rgba(15, 12, 41, 0.15)';
            ctx.fillRect(0, 0, width, height);
            
            particles.forEach(p => {
                p.x += p.vx; p.y += p.vy;
                if (p.x < 0 || p.x > width) p.vx *= -1;
                if (p.y < 0 || p.y > height) p.vy *= -1;
                ctx.beginPath();
                ctx.arc(p.x, p.y, p.size, 0, Math.PI * 2);
                ctx.fillStyle = `rgba(100, 200, 255, ${p.opacity})`;
                ctx.fill();
            });
            
            // 低端设备不绘制连线
            if (!isLowEnd) {
                particles.forEach((p1, i) => {
                    particles.slice(i + 1).forEach(p2 => {
                        const dx = p1.x - p2.x, dy = p1.y - p2.y;
                        const dist = Math.sqrt(dx * dx + dy * dy);
                        if (dist < 100) {
                            ctx.beginPath();
                            ctx.moveTo(p1.x, p1.y);
                            ctx.lineTo(p2.x, p2.y);
                            ctx.strokeStyle = `rgba(100, 200, 255, ${0.15 * (1 - dist / 100)})`;
                            ctx.lineWidth = 0.5;
                            ctx.stroke();
                        }
                    });
                });
            }
        };
        animate(0);
    }

    function startWavesWallpaper(ctx, width, height) {
        let time = 0;
        const isLowEnd = isLowEndDevice();
        const waveCount = isLowEnd ? 2 : 4;
        const stepSize = isLowEnd ? 10 : 5;
        
        const animate = (currentTime) => {
            animationId = requestAnimationFrame(animate);
            
            const delta = currentTime - lastFrameTime;
            if (delta < FRAME_INTERVAL) return;
            lastFrameTime = currentTime - (delta % FRAME_INTERVAL);
            
            ctx.fillStyle = 'rgba(26, 41, 128, 0.1)';
            ctx.fillRect(0, 0, width, height);
            
            for (let i = 0; i < waveCount; i++) {
                ctx.beginPath();
                ctx.moveTo(0, height);
                for (let x = 0; x <= width; x += stepSize) {
                    const y = height * 0.6 + Math.sin(x * 0.008 + time + i * 1.5) * 30 + Math.sin(x * 0.005 + time * 0.7 + i) * 15;
                    ctx.lineTo(x, y);
                }
                ctx.lineTo(width, height);
                ctx.closePath();
                ctx.fillStyle = `rgba(38, 208, 206, ${0.08 + i * 0.03})`;
                ctx.fill();
            }
            time += 0.03;
        };
        animate(0);
    }

    function startStarryWallpaper(ctx, width, height) {
        const stars = [];
        const starCount = getStarCount(width, height);
        
        for (let i = 0; i < starCount; i++) {
            stars.push({
                x: Math.random() * width,
                y: Math.random() * height,
                size: Math.random() * 2 + 0.3,
                twinkleSpeed: Math.random() * 0.03 + 0.01,
                twinkleOffset: Math.random() * Math.PI * 2
            });
        }
        let time = 0;
        
        const animate = (currentTime) => {
            animationId = requestAnimationFrame(animate);
            
            const delta = currentTime - lastFrameTime;
            if (delta < FRAME_INTERVAL) return;
            lastFrameTime = currentTime - (delta % FRAME_INTERVAL);
            
            ctx.fillStyle = 'rgba(0, 0, 0, 0.25)';
            ctx.fillRect(0, 0, width, height);
            
            stars.forEach(star => {
                const opacity = 0.4 + 0.6 * Math.sin(time * star.twinkleSpeed + star.twinkleOffset);
                ctx.beginPath();
                ctx.arc(star.x, star.y, star.size, 0, Math.PI * 2);
                ctx.fillStyle = `rgba(255, 255, 255, ${opacity})`;
                ctx.fill();
            });
            time += 1;
        };
        animate(0);
    }

    function startBubblesWallpaper(ctx, width, height) {
        const bubbles = [];
        const bubbleCount = getBubbleCount(width, height);
        
        for (let i = 0; i < bubbleCount; i++) {
            bubbles.push({
                x: Math.random() * width,
                y: height + Math.random() * 200,
                size: Math.random() * 12 + 3,
                speed: Math.random() * 0.8 + 0.3,
                wobble: Math.random() * Math.PI * 2,
                wobbleSpeed: Math.random() * 0.03 + 0.01
            });
        }
        
        const animate = (currentTime) => {
            animationId = requestAnimationFrame(animate);
            
            const delta = currentTime - lastFrameTime;
            if (delta < FRAME_INTERVAL) return;
            lastFrameTime = currentTime - (delta % FRAME_INTERVAL);
            
            ctx.fillStyle = 'rgba(33, 147, 176, 0.12)';
            ctx.fillRect(0, 0, width, height);
            
            bubbles.forEach(bubble => {
                bubble.y -= bubble.speed;
                bubble.wobble += bubble.wobbleSpeed;
                const x = bubble.x + Math.sin(bubble.wobble) * 15;
                if (bubble.y < -bubble.size) {
                    bubble.y = height + bubble.size;
                    bubble.x = Math.random() * width;
                }
                ctx.beginPath();
                ctx.arc(x, bubble.y, bubble.size, 0, Math.PI * 2);
                ctx.fillStyle = 'rgba(109, 213, 237, 0.25)';
                ctx.fill();
                ctx.strokeStyle = 'rgba(109, 213, 237, 0.4)';
                ctx.lineWidth = 0.8;
                ctx.stroke();
            });
        };
        animate(0);
    }

    function startMatrixWallpaper(ctx, width, height) {
        const fontSize = 14;
        const columns = Math.floor(width / fontSize);
        const drops = [];
        const chars = '01アイウエオカキクケコサシスセソタチツテトナニヌネノハヒフヘホマミムメモヤユヨラリルレロワヲン';
        const isLowEnd = isLowEndDevice();
        const updateInterval = isLowEnd ? 2 : 1; // 低端设备隔帧更新
        let frameCount = 0;
        
        for (let i = 0; i < columns; i++) drops[i] = Math.random() * -100;
        
        const animate = (currentTime) => {
            animationId = requestAnimationFrame(animate);
            
            const delta = currentTime - lastFrameTime;
            if (delta < FRAME_INTERVAL) return;
            lastFrameTime = currentTime - (delta % FRAME_INTERVAL);
            
            frameCount++;
            if (frameCount % updateInterval !== 0) return;
            
            ctx.fillStyle = 'rgba(0, 0, 0, 0.06)';
            ctx.fillRect(0, 0, width, height);
            ctx.font = fontSize + 'px monospace';
            
            for (let i = 0; i < drops.length; i++) {
                const char = chars[Math.floor(Math.random() * chars.length)];
                const x = i * fontSize;
                const y = drops[i] * fontSize;
                ctx.fillStyle = `rgba(0, 255, 70, ${Math.random() * 0.5 + 0.5})`;
                ctx.fillText(char, x, y);
                if (y > height && Math.random() > 0.98) drops[i] = 0;
                drops[i]++;
            }
        };
        animate(0);
    }

    // 检查浏览器是否支持视频壁纸
    function isVideoWallpaperSupported() {
        const video = document.createElement('video');
        return video.canPlayType && (
            video.canPlayType('video/mp4; codecs="avc1.42E01E"') !== '' ||
            video.canPlayType('video/webm; codecs="vp8, vorbis"') !== '' ||
            video.canPlayType('video/ogg; codecs="theora"') !== ''
        );
    }

    function applyVideoWallpaper(video, settings) {
        console.log('应用视频壁纸, videoData长度:', settings.videoData ? settings.videoData.length : 0);
        
        if (!settings.videoData) {
            console.log('没有视频数据，隐藏视频');
            video.style.display = 'none';
            return;
        }
        
        // 检查浏览器是否支持视频播放
        if (!isVideoWallpaperSupported()) {
            console.warn('当前浏览器不支持视频壁纸');
            video.style.display = 'none';
            return;
        }
        
        // 如果视频已经在播放且src相同，不要重新加载
        // 使用getAttribute比较，因为video.src可能返回完整URL
        const currentSrc = video.getAttribute('src') || video.src;
        if (currentSrc === settings.videoData && !video.paused) {
            console.log('视频已经在播放，无需重新加载');
            video.style.display = 'block';
            return;
        }
        
        console.log('设置视频src并尝试播放');
        video.src = settings.videoData;
        video.loop = settings.videoLoop !== false;
        video.muted = settings.videoMute !== false;
        video.playsInline = true;
        video.style.display = 'block';
        
        // 等待视频加载完成后再播放
        const playVideo = () => {
            console.log('尝试播放视频');
            const playPromise = video.play();
            if (playPromise !== undefined) {
                playPromise.catch(error => {
                    console.error('视频播放失败:', error);
                    // 如果是自动播放策略限制，尝试静音后播放
                    if (error.name === 'NotAllowedError') {
                        console.log('自动播放被阻止，尝试静音播放');
                        video.muted = true;
                        video.play().catch(e => console.error('静音播放也失败:', e));
                    }
                });
            }
        };
        
        // 如果视频已经加载完成，直接播放
        if (video.readyState >= 2) {
            console.log('视频已加载，直接播放');
            playVideo();
        } else {
            console.log('等待视频加载...');
            // 等待视频加载完成
            const loadHandler = () => {
                console.log('视频加载完成');
                playVideo();
                clearTimeout(timeoutId);
            };
            
            // 添加超时处理，如果5秒内没有加载完成，强制播放
            const timeoutId = setTimeout(() => {
                console.warn('视频加载超时，尝试强制播放');
                video.removeEventListener('loadeddata', loadHandler);
                playVideo();
            }, 5000);
            
            video.addEventListener('loadeddata', loadHandler, { once: true });
            
            // 添加错误处理
            video.addEventListener('error', (e) => {
                clearTimeout(timeoutId);
                console.error('视频加载错误:', e);
            }, { once: true });
        }
    }

    function applyLiveWallpaper() {
        const canvas = document.getElementById('live-wallpaper-canvas');
        const video = document.getElementById('live-wallpaper-video');
        if (!canvas || !video) return;
        const settings = loadLiveWallpaperSettings();
        
        if (!settings.enabled) {
            stopLiveWallpaper();
            canvas.style.display = 'none';
            video.style.display = 'none';
            video.pause();
            return;
        }
        
        const effect = settings.effect || 'particles';
        
        // 视频壁纸
        if (effect === 'video') {
            // 先停止Canvas动画，但不停视频
            if (animationId) {
                cancelAnimationFrame(animationId);
                animationId = null;
            }
            canvas.style.display = 'none';
            applyVideoWallpaper(video, settings);
            return;
        }
        
        // Canvas动画壁纸 - 停止视频但保留Canvas
        video.style.display = 'none';
        video.pause();
        // 停止之前的动画
        if (animationId) {
            cancelAnimationFrame(animationId);
            animationId = null;
        }
        canvas.style.display = 'block';
        const container = canvas.parentElement;
        const rect = container.getBoundingClientRect();
        canvas.width = rect.width;
        canvas.height = rect.height;
        const ctx = canvas.getContext('2d');
        switch(effect) {
            case 'particles': startParticlesWallpaper(ctx, canvas.width, canvas.height); break;
            case 'waves': startWavesWallpaper(ctx, canvas.width, canvas.height); break;
            case 'starry': startStarryWallpaper(ctx, canvas.width, canvas.height); break;
            case 'bubbles': startBubblesWallpaper(ctx, canvas.width, canvas.height); break;
            case 'matrix': startMatrixWallpaper(ctx, canvas.width, canvas.height); break;
        }
    }

    // 初始化动态壁纸
    applyLiveWallpaper();

    // 监听设置变化
    window.addEventListener('storage', (e) => {
        if (e.key === STORAGE_KEY) {
            applyLiveWallpaper();
        }
    });
    window.addEventListener('chatLiveWallpaperChanged', () => {
        applyLiveWallpaper();
    });

    // 窗口大小改变时重新调整
    window.addEventListener('resize', () => {
        const settings = loadLiveWallpaperSettings();
        if (settings.enabled) {
            applyLiveWallpaper();
        }
    });
})();
</script>

<!-- 液态玻璃点击波纹效果 -->
<script>
(function() {
    function createLiquidGlassRipple(e, element) {
        const rect = element.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;
        
        const ripple = document.createElement('span');
        ripple.style.cssText = `
            position: absolute;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(255,255,255,0.6) 0%, rgba(255,255,255,0.2) 50%, transparent 70%);
            transform: scale(0);
            animation: liquidGlassRipple 0.8s cubic-bezier(0.4, 0, 0.2, 1) forwards;
            pointer-events: none;
            z-index: 10;
        `;
        
        const size = Math.max(rect.width, rect.height) * 1.5;
        ripple.style.width = size + 'px';
        ripple.style.height = size + 'px';
        ripple.style.left = (x - size / 2) + 'px';
        ripple.style.top = (y - size / 2) + 'px';
        
        element.appendChild(ripple);
        
        setTimeout(() => {
            if (ripple.parentNode) {
                ripple.parentNode.removeChild(ripple);
            }
        }, 800);
    }
    
    // 添加CSS动画
    const style = document.createElement('style');
    style.textContent = `
        @keyframes liquidGlassRipple {
            0% {
                transform: scale(0);
                opacity: 1;
            }
            50% {
                opacity: 0.6;
            }
            100% {
                transform: scale(2.5);
                opacity: 0;
            }
        }
        
        .function-card, .other-function-card {
            overflow: hidden !important;
        }
        
        .liquid-glass-shine {
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: conic-gradient(from 0deg, transparent 0%, rgba(255,255,255,0.08) 20%, transparent 40%, transparent 60%, rgba(255,255,255,0.08) 80%, transparent 100%);
            animation: liquidGlassRotate 8s linear infinite;
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.3s ease;
            will-change: transform;
        }
        
        .function-card:hover .liquid-glass-shine,
        .other-function-card:hover .liquid-glass-shine {
            opacity: 1;
        }
        
        @keyframes liquidGlassRotate {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .liquid-glass-reflection {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 50%;
            background: linear-gradient(180deg, rgba(255,255,255,0.25) 0%, rgba(255,255,255,0.08) 50%, transparent 100%);
            border-radius: 1rem 1rem 0 0;
            pointer-events: none;
        }
    `;
    document.head.appendChild(style);
    
    // 为功能卡片添加点击波纹效果
    function initLiquidGlassEffect() {
        const cards = document.querySelectorAll('.function-card:not(.expired), .other-function-card:not(.expired)');
        cards.forEach(card => {
            // 添加光泽旋转层
            if (!card.querySelector('.liquid-glass-shine')) {
                const shine = document.createElement('div');
                shine.className = 'liquid-glass-shine';
                card.appendChild(shine);
            }
            
            // 添加顶部反光层
            if (!card.querySelector('.liquid-glass-reflection')) {
                const reflection = document.createElement('div');
                reflection.className = 'liquid-glass-reflection';
                card.appendChild(reflection);
            }
            
            // 点击波纹效果
            card.addEventListener('click', function(e) {
                createLiquidGlassRipple(e, this);
            });
            
            // 鼠标移动时的光泽效果（使用节流）
            let mouseMoveTimeout = null;
            card.addEventListener('mousemove', function(e) {
                if (mouseMoveTimeout) return;
                mouseMoveTimeout = setTimeout(() => {
                    mouseMoveTimeout = null;
                }, 50);
                
                const rect = this.getBoundingClientRect();
                const x = ((e.clientX - rect.left) / rect.width) * 100;
                const y = ((e.clientY - rect.top) / rect.height) * 100;
                
                this.style.background = `radial-gradient(circle at ${x}% ${y}%, rgba(255,255,255,0.35) 0%, rgba(255,255,255,0.2) 50%, rgba(255,255,255,0.12) 100%)`;
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.background = '';
            });
        });
    }
    
    // 初始化
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initLiquidGlassEffect);
    } else {
        initLiquidGlassEffect();
    }
    
    // 监听动态添加的元素
    const observer = new MutationObserver(() => {
        initLiquidGlassEffect();
    });
    observer.observe(document.body, { childList: true, subtree: true });
})();
</script>

<!-- PWA Service Worker 注册 -->
<script>
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/sw.js', { scope: '/' }).then(function(reg) {
        console.log('[Home] Service Worker 已注册');
    }).catch(function(err) {
        console.error('[Home] Service Worker 注册失败:', err);
    });
}
</script>

</body>
</html>