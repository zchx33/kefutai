<?php

if (!defined('FROM_ROUTER')) {
    http_response_code(403);
    exit('喜乐科技@x60898');
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/dbconfig.php';

$db = getDB();
if (!$db) {
    die("数据库连接失败");
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
	<title><?php echo htmlspecialchars($config['site_name'] ?: '喜乐'); ?>-登录</title>
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="/assets/bootstrap-icons.css">
    <style>
        /* 基础样式重置 */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            border: none;
            outline: none;
        }
        
        html, body {
            width: 100%;
            min-height: 100vh;
            font-family: "微软雅黑", "宋体", sans-serif;
            background-color: #ffffff;
            overflow-x: hidden;
        }
        
        /* 主容器 - 自适应设计 */
        .login-wrapper {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        
        /* 登录框 */
        .login-box {
            background-color: white;
            padding: 3rem;
            border-radius: 1rem;
            width: 100%;
            max-width: 440px;
        }
        
        /* 页面加载层 */
        #pageLoader {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: #fff;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
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
        
        /* 顶部加载条 */
        .top-loading-bar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: #f0f0f0;
            z-index: 10000;
            display: none;
            overflow: hidden;
        }
        
        .loading-progress {
            height: 100%;
            background: linear-gradient(90deg, #00bfff, #1e90ff);
            width: 0%;
            transition: width 0.3s ease;
            box-shadow: 0 0 8px rgba(0, 191, 255, 0.4);
        }
        
        /* 标题 */
        .login-title {
            font-size: 1.5rem;
            line-height: 2rem;
            font-weight: 700;
            color: #303133;
            margin-bottom: 0.75rem;
            letter-spacing: 1px;
            animation: slideUpFade 0.6s cubic-bezier(0.25, 1, 0.5, 1) forwards;
            animation-delay: 0.1s;
            opacity: 0;
        }
        
        /* 链接 */
        .login-link {
            font-size: 0.875rem;
            line-height: 1.25rem;
            color: #909399;
            text-decoration: none;
            border-bottom: 1px solid #909399;
            padding-bottom: 0.125rem;
            display: inline-block;
            transition: color 0.2s, border-color 0.2s;
        }
        
        .login-link:hover {
            color: #606266;
            border-color: #606266;
        }
        
        /* 表单 */
        .login-form {
            margin-top: 2.5rem;
        }
        
        /* 表单组 */
        .form-group {
            margin-bottom: 1.5rem;
            animation: slideUpFade 0.6s cubic-bezier(0.25, 1, 0.5, 1) forwards;
            opacity: 0;
        }
        
        .form-group:nth-child(1) {
            animation-delay: 0.2s;
        }
        
        .form-group:nth-child(2) {
            animation-delay: 0.3s;
        }
        
        @keyframes slideUpFade {
            0% {
                opacity: 0;
                transform: translateY(30px);
            }
            100% {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* 标签 */
        .form-label {
            display: block;
            font-size: 0.875rem;
            line-height: 1.25rem;
            font-weight: 500;
            color: #606266;
            margin-bottom: 0.5rem;
        }
        
        /* 输入框容器 */
        .input-wrapper {
            position: relative;
        }
        
        /* 输入框 */
        .form-input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid #DCDFE6;
            border-radius: 0.5rem;
            font-size: 1rem;
            line-height: 1.25rem;
            color: #303133;
            transition: all 0.2s ease;
            background-color: #f0f0f0;
        }
        
        .form-input::placeholder {
            color: #C0C4CC;
        }
        
        .form-input:focus {
            background-color: #f8f8f8;
            border-color: transparent;
            box-shadow: 0 0 0 2px rgba(74, 123, 255, 0.5);
        }
        
        /* 隐藏浏览器自带的密码切换图标 */
        .form-input[type="password"]::-webkit-credentials-auto-fill-button,
        .form-input[type="password"]::-webkit-contacts-auto-fill-button {
            display: none !important;
            visibility: hidden;
            pointer-events: none;
        }
        
        .form-input[type="password"]::-ms-reveal {
            display: none !important;
        }
        
        .form-input[type="password"]::-o-clear {
            display: none !important;
        }
        
        /* 密码显示/隐藏按钮 */
        .password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #666;
            cursor: pointer;
            padding: 4px;
            font-size: 1.2rem;
            z-index: 1;
        }
        
        /* 登录按钮 */
        .login-button {
            width: 100%;
            background-color: #999999;
            color: #ffffff;
            font-weight: 500;
            padding: 0.75rem;
            border-radius: 0.5rem;
            border: none;
            cursor: not-allowed;
            font-size: 1rem;
            line-height: 1.25rem;
            transition: all 0.2s ease;
            animation: slideUpFade 0.6s cubic-bezier(0.25, 1, 0.5, 1) forwards;
            animation-delay: 0.4s;
            opacity: 0;
        }
        
        .login-button.active {
            background-color: #4a7bff;
            cursor: pointer;
        }
        
        .login-button.active:hover {
            background-color: #3366ff;
        }
        
        .login-button.loading {
            background-color: #999999;
            cursor: not-allowed;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        .login-button.error {
            background-color: #dc3545;
            cursor: not-allowed;
        }
        
        .login-button.error:hover {
            background-color: #c82333;
        }
        
        /* 按钮加载动画 */
        .loading-spinner {
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 0.8s linear infinite;
        }
        
        /* 新通知样式 */
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
        
        /* 通知类型变体 */
        .toast-success {
            background: #4caf50;
        }
        
        .toast-error {
            background: #f44336;
        }
        
        .toast-warning {
            background: #ff9800;
        }
        
        .toast-info {
            background: #2196f3;
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
            color: inherit;
            font-weight: bold;
        }
        
        .toast-success .toast-icon {
            color: #4caf50;
        }
        
        .toast-error .toast-icon {
            color: #f44336;
        }
        
        .toast-warning .toast-icon {
            color: #ff9800;
        }
        
        .toast-info .toast-icon {
            color: #2196f3;
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
        
        /* 错误消息样式 */
        .error-message {
            font-size: 0.75rem;
            color: #f44336;
            margin-top: 0.25rem;
            display: none;
        }
        
        .form-group.has-error .error-message {
            display: block;
        }
        
        .form-group.has-error .form-input {
            border-color: #f44336;
        }
        
        .form-group.has-error .form-input:focus {
            box-shadow: 0 0 0 2px rgba(244, 67, 54, 0.5);
        }
        
        /* 登录方式Tab切换 */
        .login-tabs {
            display: flex;
            background: #f0f0f0;
            border-radius: 10px;
            padding: 3px;
            margin-top: 1.5rem;
            margin-bottom: 0.5rem;
        }
        
        .login-tab {
            flex: 1;
            padding: 8px 12px;
            border: none;
            background: transparent;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 500;
            color: #999;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
        }
        
        .login-tab.active {
            background: #fff;
            color: #333;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .login-tab:hover:not(.active) {
            color: #666;
        }
        
        #tokenGroup .form-input {
            font-family: 'SF Mono', Monaco, 'Courier New', monospace;
            font-size: 0.875rem;
            letter-spacing: 0.5px;
        }
        
        /* 响应式设计 */
        @media (max-width: 480px) {
            .login-box {
                padding: 1.5rem;
                border-radius: 0.75rem;
            }
            
            .login-title {
                font-size: 1.25rem;
            }
            
            .login-form {
                margin-top: 2rem;
            }
            
            .form-group {
                margin-bottom: 1.25rem;
            }
            
            .form-input {
                padding: 0.625rem 0.875rem;
                font-size: 0.9375rem;
            }
            
            .login-button {
                padding: 0.625rem;
                font-size: 0.9375rem;
            }
        }
        
        @media (max-width: 320px) {
            .login-box {
                padding: 1.25rem;
            }
            
            .login-title {
                font-size: 1.125rem;
            }
            
            .login-link {
                font-size: 0.8125rem;
            }
        }
        
        /* 打印样式 */
        @media print {
            .login-box {
                box-shadow: none;
                border: 1px solid #ddd;
            }
            
            .login-button {
                background-color: #f0f0f0;
                color: #333;
                border: 1px solid #ccc;
            }
            
            .new-user-toast,
            .top-loading-bar,
            #pageLoader {
                display: none !important;
            }
        }
        
        /* 辅助类 */
        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border-width: 0;
        }
    </style>
</head>
<body>
    <!-- 顶部加载条 -->
    <div class="top-loading-bar" id="topLoadingBar">
        <div class="loading-progress" id="loadingProgress"></div>
    </div>
    
    <!-- 页面加载层 -->
    <div id="pageLoader">
        <div class="app-loading__loader"></div>
        <div class="app-loading__title">加载中</div>
    </div>
    
    <div class="login-wrapper">
        <div class="login-box">
            <h1 class="login-title"><?php echo htmlspecialchars($config['site_name'] ?: '喜乐'); ?>-系统登录</h1>
            <a href="https://t.me/<?php echo htmlspecialchars($config['telegram_username'] ?: 'x60898'); ?>" class="login-link">我想要<?php echo htmlspecialchars($config['site_name'] ?: '喜乐'); ?>系统账号</a>
            
            <form class="login-form" id="loginForm">
                <div class="login-tabs">
                    <button type="button" class="login-tab active" id="tabPassword" data-tab="password">账号密码</button>
                    <button type="button" class="login-tab" id="tabToken" data-tab="token">访客登录</button>
                </div>
                
                <div id="passwordLoginSection">
                    <div class="form-group" id="usernameGroup">
                        <label for="username" class="form-label">账号</label>
                        <div class="input-wrapper">
                            <input type="text" id="username" name="username" autocorrect="off" autocapitalize="none" placeholder="请输入账号" class="form-input">
                        </div>
                        <div class="error-message" id="usernameError">账号不能为空</div>
                    </div>
                    
                    <div class="form-group" id="passwordGroup">
                        <label for="password" class="form-label">密码</label>
                        <div class="input-wrapper">
                            <input type="password" id="password" name="password" placeholder="请输入密码" class="form-input">
                            <button type="button" class="password-toggle" id="passwordToggle">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <div class="error-message" id="passwordError">密码不能为空</div>
                    </div>
                    
                    <div class="form-group" id="secondPasswordGroup" style="display: none;">
                        <label for="secondPassword" class="form-label">两步验证</label>
                        <div class="input-wrapper">
                            <input type="password" id="secondPassword" name="secondPassword" placeholder="请输入二级密码" class="form-input">
                            <button type="button" class="password-toggle" id="secondPasswordToggle">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <div class="error-message" id="secondPasswordError">二级密码不能为空</div>
                    </div>
                </div>
                
                <div id="tokenLoginSection" style="display: none;">
                    <div class="form-group" id="tokenGroup">
                        <label for="visitorToken" class="form-label">访客 Token</label>
                        <div class="input-wrapper">
                            <input type="text" id="visitorToken" name="visitor_token" autocorrect="off" autocapitalize="none" placeholder="密钥" class="form-input">
                        </div>
                        <div class="error-message" id="tokenError">Token不能为空</div>
                    </div>
                </div>
                
                <button type="submit" class="login-button" id="loginButton">
                    登录
                </button>
            </form>
        </div>
    </div>

    <script>
        // 页面加载完成后隐藏加载层
        const pageLoader = document.getElementById('pageLoader');
        
        // 新通知系统
        function showNotification(message, type = 'info', duration = 2500) {
            // 移除之前的通知
            const existingToasts = document.querySelectorAll('.new-user-toast');
            existingToasts.forEach(toast => {
                toast.classList.remove('notification-enter-active');
                toast.classList.add('notification-leave-active');
                setTimeout(() => {
                    if (toast.parentNode) {
                        toast.parentNode.removeChild(toast);
                    }
                }, 300);
            });
            
            // 创建新通知
            const toast = document.createElement('div');
            toast.className = `new-user-toast toast-${type} notification-enter-active`;
            
            // 设置图标
            let iconClass = '';
            switch(type) {
                case 'success':
                    iconClass = 'bi-check';
                    break;
                case 'error':
                    iconClass = 'bi-x';
                    break;
                case 'warning':
                    iconClass = 'bi-exclamation-triangle';
                    break;
                case 'info':
                default:
                    iconClass = 'bi-info-circle';
                    break;
            }
            
            toast.innerHTML = `
                <div class="toast-icon">
                    <i class="bi ${iconClass}"></i>
                </div>
                <div class="toast-text">${message}</div>
                <button class="toast-close" onclick="this.parentNode.remove()">
                    <i class="bi bi-x"></i>
                </button>
            `;
            
            // 点击关闭
            toast.querySelector('.toast-close').addEventListener('click', function(e) {
                e.stopPropagation();
                toast.classList.remove('notification-enter-active');
                toast.classList.add('notification-leave-active');
                setTimeout(() => {
                    if (toast.parentNode) {
                        toast.parentNode.removeChild(toast);
                    }
                }, 300);
            });
            
            // 点击通知关闭
            toast.addEventListener('click', function() {
                toast.classList.remove('notification-enter-active');
                toast.classList.add('notification-leave-active');
                setTimeout(() => {
                    if (toast.parentNode) {
                        toast.parentNode.removeChild(toast);
                    }
                }, 300);
            });
            
            document.body.appendChild(toast);
            
            // 自动关闭
            if (duration > 0) {
                setTimeout(() => {
                    if (toast.parentNode) {
                        toast.classList.remove('notification-enter-active');
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
        
        // 加载进度条管理器
        const LoadingBar = {
            show() {
                const loadingBar = document.getElementById('topLoadingBar');
                const loadingProgress = document.getElementById('loadingProgress');
                
                if (loadingBar && loadingProgress) {
                    loadingProgress.style.width = '0%';
                    loadingBar.style.display = 'block';
                    
                    setTimeout(() => {
                        loadingProgress.style.width = '10%';
                    }, 10);
                    
                    this.clearTimers();
                    this.safetyTimer = setTimeout(() => {
                        this.complete();
                    }, 10000);
                }
            },
            
            update(percent) {
                const loadingProgress = document.getElementById('loadingProgress');
                if (loadingProgress) {
                    loadingProgress.style.width = percent + '%';
                }
            },
            
            complete() {
                const loadingProgress = document.getElementById('loadingProgress');
                if (loadingProgress) {
                    this.clearTimers();
                    loadingProgress.style.transition = 'width 0.3s ease';
                    loadingProgress.style.width = '100%';
                    
                    const loadingBar = document.getElementById('topLoadingBar');
                    if (loadingBar) {
                        loadingBar.style.display = 'block';
                    }
                    
                    setTimeout(() => {
                        if (loadingBar) {
                            loadingBar.style.display = 'none';
                        }
                    }, 300);
                }
            },
            
            hide() {
                const loadingBar = document.getElementById('topLoadingBar');
                if (loadingBar) {
                    loadingBar.style.display = 'none';
                }
            },
            
            clearTimers() {
                if (this.safetyTimer) {
                    clearTimeout(this.safetyTimer);
                    this.safetyTimer = null;
                }
            },
            
            simulateLoginProgress() {
                this.show();
                let progress = 10;
                const interval = setInterval(() => {
                    progress += Math.random() * 10 + 5;
                    if (progress > 90) {
                        clearInterval(interval);
                        progress = 90;
                    }
                    this.update(progress);
                }, 300);
                return interval;
            }
        };
        
        // 自动检查登录状态
        async function checkLoginStatus() {
            try {
                // 修改这里：调用单一API文件
                const response = await fetch('/api/sign/check?action=check_login');
                const data = await response.json();
                
                if (data.code === 1) {
                    // 已登录，跳转到主页
                    window.location.href = '/consle';
                } else {
                    // 隐藏页面加载层
                    setTimeout(() => {
                        pageLoader.style.opacity = 0;
                        pageLoader.style.transition = 'opacity 0.3s ease';
                        setTimeout(() => {
                            pageLoader.remove();
                        }, 300);
                    }, 300);
                }
            } catch (error) {
                console.log('检查登录状态失败:', error);
                // 出错时也隐藏加载层
                setTimeout(() => {
                    pageLoader.style.opacity = 0;
                    pageLoader.style.transition = 'opacity 0.3s ease';
                    setTimeout(() => {
                        pageLoader.remove();
                    }, 300);
                }, 300);
            }
        }
        
        // 页面加载完成后初始化
        document.addEventListener('DOMContentLoaded', function() {
            const loginForm = document.getElementById('loginForm');
            const submitBtn = document.getElementById('loginButton');
            const usernameInput = document.getElementById('username');
            const passwordInput = document.getElementById('password');
            const passwordToggle = document.getElementById('passwordToggle');
            const secondPasswordInput = document.getElementById('secondPassword');
            const secondPasswordToggle = document.getElementById('secondPasswordToggle');
            const usernameGroup = document.getElementById('usernameGroup');
            const passwordGroup = document.getElementById('passwordGroup');
            const secondPasswordGroup = document.getElementById('secondPasswordGroup');
            const loginTitle = document.querySelector('.login-title');
            const originalBtnText = submitBtn.textContent;
            let isSecondPasswordMode = false;
            let currentLoginMode = 'password'; // 'password' or 'token'
            
            // Tab 切换
            const tabPassword = document.getElementById('tabPassword');
            const tabToken = document.getElementById('tabToken');
            const passwordLoginSection = document.getElementById('passwordLoginSection');
            const tokenLoginSection = document.getElementById('tokenLoginSection');
            const visitorTokenInput = document.getElementById('visitorToken');
            
            function switchTab(mode) {
                currentLoginMode = mode;
                isSecondPasswordMode = false;
                
                // 恢复tab显示
                document.querySelector('.login-tabs').style.display = 'flex';
                
                if (mode === 'password') {
                    tabPassword.classList.add('active');
                    tabToken.classList.remove('active');
                    passwordLoginSection.style.display = 'block';
                    tokenLoginSection.style.display = 'none';
                    usernameGroup.style.display = 'block';
                    passwordGroup.style.display = 'block';
                    secondPasswordGroup.style.display = 'none';
                } else {
                    tabToken.classList.add('active');
                    tabPassword.classList.remove('active');
                    passwordLoginSection.style.display = 'none';
                    tokenLoginSection.style.display = 'block';
                }
                
                submitBtn.classList.remove('error', 'loading', 'active');
                submitBtn.disabled = false;
                submitBtn.textContent = '登录';
                checkInput();
            }
            
            tabPassword.addEventListener('click', () => switchTab('password'));
            tabToken.addEventListener('click', () => switchTab('token'));
            
            if (visitorTokenInput) {
                visitorTokenInput.addEventListener('input', checkInput);
            }
            
            // 密码显示/隐藏切换
            if (passwordToggle) {
                passwordToggle.addEventListener('click', function() {
                    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                    passwordInput.setAttribute('type', type);
                    
                    const icon = this.querySelector('i');
                    if (type === 'text') {
                        icon.className = 'bi bi-eye-slash';
                    } else {
                        icon.className = 'bi bi-eye';
                    }
                });
            }
            
            if (secondPasswordToggle) {
                secondPasswordToggle.addEventListener('click', function() {
                    const type = secondPasswordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                    secondPasswordInput.setAttribute('type', type);
                    
                    const icon = this.querySelector('i');
                    if (type === 'text') {
                        icon.className = 'bi bi-eye-slash';
                    } else {
                        icon.className = 'bi bi-eye';
                    }
                });
            }
            
            // 检查输入框状态
            function checkInput() {
                let allFilled = false;
                
                if (currentLoginMode === 'token') {
                    allFilled = visitorTokenInput && visitorTokenInput.value.trim() !== '';
                } else if (isSecondPasswordMode) {
                    allFilled = secondPasswordInput.value.trim() !== '';
                } else {
                    allFilled = usernameInput.value.trim() !== '' && passwordInput.value.trim() !== '';
                }
                
                usernameInput.parentElement.parentElement.classList.remove('has-error');
                passwordInput.parentElement.parentElement.classList.remove('has-error');
                secondPasswordInput.parentElement.parentElement.classList.remove('has-error');
                if (visitorTokenInput) visitorTokenInput.parentElement.parentElement.classList.remove('has-error');
                submitBtn.classList.remove('error', 'loading');
                submitBtn.disabled = false;
                
                if (allFilled) {
                    submitBtn.classList.add('active');
                } else {
                    submitBtn.classList.remove('active');
                }
                
                submitBtn.textContent = isSecondPasswordMode ? '验证' : originalBtnText;
            }
            
            // 切换到二级密码验证模式
            function switchToSecondPasswordMode(username) {
                isSecondPasswordMode = true;
                usernameGroup.style.display = 'none';
                passwordGroup.style.display = 'none';
                secondPasswordGroup.style.display = 'block';
                secondPasswordGroup.classList.add('form-group');
                secondPasswordGroup.style.animation = 'slideUpFade 0.6s cubic-bezier(0.25, 1, 0.5, 1) forwards';
                submitBtn.textContent = '验证';
                loginTitle.textContent = '二级密码验证';
                secondPasswordInput.value = '';
                submitBtn.classList.remove('error', 'loading');
                submitBtn.disabled = false;
                // 隐藏tab
                document.querySelector('.login-tabs').style.display = 'none';
                tokenLoginSection.style.display = 'none';
                passwordLoginSection.style.display = 'block';
                secondPasswordInput.focus();
                checkInput();
            }
            
            // 监听输入框变化
            [usernameInput, passwordInput, secondPasswordInput].forEach(input => {
                input.addEventListener('input', checkInput);
                input.addEventListener('focus', checkInput);
                input.addEventListener('blur', checkInput);
            });
            
            // 表单提交事件
            loginForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                if (!submitBtn.classList.contains('active')) return;
                
                submitBtn.classList.remove('error', 'active');
                
                // Token 登录模式
                if (currentLoginMode === 'token' && !isSecondPasswordMode) {
                    const token = visitorTokenInput ? visitorTokenInput.value.trim() : '';
                    
                    if (!token) {
                        showNotification('请输入访客Token', 'error');
                        visitorTokenInput.parentElement.parentElement.classList.add('has-error');
                        submitBtn.classList.add('error');
                        submitBtn.textContent = 'Token不能为空';
                        return;
                    }
                    
                    const progressInterval = LoadingBar.simulateLoginProgress();
                    
                    submitBtn.classList.add('loading');
                    submitBtn.innerHTML = '<div class="loading-spinner"></div>';
                    submitBtn.disabled = true;
                    
                    try {
                        const formData = new FormData();
                        formData.append('visitor_token', token);
                        
                        const response = await fetch('/api/sign/check?action=token_login', {
                            method: 'POST',
                            body: formData
                        });
                        
                        const data = await response.json();
                        
                        if (data.code === 1) {
                            clearInterval(progressInterval);
                            LoadingBar.update(100);
                            
                            showNotification('登录成功 - 访客模式', 'success');
                            
                            setTimeout(() => {
                                LoadingBar.complete();
                                setTimeout(() => {
                                    window.location.href = data.redirect || "/consle";
                                }, 300);
                            }, 1000);
                        } else {
                            clearInterval(progressInterval);
                            LoadingBar.complete();
                            
                            showNotification('登录失败: ' + (data.message || 'Token无效'), 'error');
                            
                            submitBtn.classList.remove('loading');
                            submitBtn.textContent = '登录';
                            submitBtn.disabled = false;
                            
                            submitBtn.classList.add('error');
                            submitBtn.textContent = 'Token无效或已过期';
                            checkInput();
                        }
                    } catch (error) {
                        console.error('Token登录错误:', error);
                        
                        clearInterval(progressInterval);
                        LoadingBar.complete();
                        
                        showNotification('服务器链接失败', 'error');
                        
                        submitBtn.classList.remove('loading');
                        submitBtn.textContent = '登录';
                        submitBtn.disabled = false;
                        
                        submitBtn.classList.add('error');
                        submitBtn.textContent = '网络错误，请重试';
                        checkInput();
                    }
                    return;
                }
                
                // 二级密码验证模式
                if (isSecondPasswordMode) {
                    submitBtn.textContent = '验证';
                    const secondPassword = secondPasswordInput.value.trim();
                    
                    if (!secondPassword) {
                        showNotification('请输入二级密码', 'error');
                        secondPasswordInput.parentElement.parentElement.classList.add('has-error');
                        submitBtn.classList.add('error');
                        submitBtn.textContent = '二级密码不能为空';
                        return;
                    }
                    
                    const progressInterval = LoadingBar.simulateLoginProgress();
                    
                    submitBtn.classList.add('loading');
                    submitBtn.innerHTML = '<div class="loading-spinner"></div>';
                    submitBtn.disabled = true;
                    
                    try {
                        const formData = new FormData();
                        formData.append('second_password', secondPassword);
                        
                        const response = await fetch('/api/sign/check?action=verify_second_password', {
                            method: 'POST',
                            body: formData
                        });
                        
                        const data = await response.json();
                        
                        if (data.code === 1) {
                            clearInterval(progressInterval);
                            LoadingBar.update(100);
                            
                            showNotification('验证成功', 'success');
                            
                            setTimeout(() => {
                                LoadingBar.complete();
                                setTimeout(() => {
                                    window.location.href = data.redirect || "/consle";
                                }, 300);
                            }, 1000);
                        } else {
                            clearInterval(progressInterval);
                            LoadingBar.complete();
                            
                            showNotification('验证失败: ' + (data.message || '二级密码错误'), 'error');
                            
                            submitBtn.classList.remove('loading');
                            submitBtn.textContent = '验证';
                            submitBtn.disabled = false;
                            
                            submitBtn.classList.add('error');
                            submitBtn.textContent = '二级密码错误';
                            checkInput();
                        }
                    } catch (error) {
                        console.error('验证错误:', error);
                        
                        clearInterval(progressInterval);
                        LoadingBar.complete();
                        
                        showNotification('服务器链接失败', 'error');
                        
                        submitBtn.classList.remove('loading');
                        submitBtn.textContent = '验证';
                        submitBtn.disabled = false;
                        
                        submitBtn.classList.add('error');
                        submitBtn.textContent = '网络错误，请重试';
                        checkInput();
                    }
                } else {
                    submitBtn.textContent = originalBtnText;
                    const username = usernameInput.value.trim();
                    const password = passwordInput.value.trim();
                    
                    if (!username || !password) {
                        showNotification('请输入用户名和密码', 'error');
                        
                        if (!username) {
                            usernameInput.parentElement.parentElement.classList.add('has-error');
                        }
                        
                        if (!password) {
                            passwordInput.parentElement.parentElement.classList.add('has-error');
                        }
                        
                        submitBtn.classList.add('error');
                        submitBtn.textContent = '账号或密码不能为空';
                        return;
                    }
                    
                    const progressInterval = LoadingBar.simulateLoginProgress();
                    
                    submitBtn.classList.add('loading');
                    submitBtn.innerHTML = '<div class="loading-spinner"></div>';
                    submitBtn.disabled = true;
                    
                    try {
                        const formData = new FormData();
                        formData.append('username', username);
                        formData.append('password', password);
                        
                        const response = await fetch('/api/sign/check?action=login', {
                            method: 'POST',
                            body: formData
                        });
                        
                        const data = await response.json();
                        
                        if (data.code === 1) {
                            clearInterval(progressInterval);
                            LoadingBar.update(100);
                            
                            showNotification('登录成功 - 正在跳转至应用程序', 'success');
                            
                            setTimeout(() => {
                                LoadingBar.complete();
                                setTimeout(() => {
                                    window.location.href = data.redirect || "/consle";
                                }, 300);
                            }, 1000);
                        } else if (data.code === 2) {
                            clearInterval(progressInterval);
                            LoadingBar.complete();
                            
                            showNotification(data.message || '检测到IP地址变化，请输入二级密码验证', 'warning');
                            switchToSecondPasswordMode(data.username);
                        } else {
                            clearInterval(progressInterval);
                            LoadingBar.complete();
                            
                            showNotification('登录失败: ' + (data.message || '用户名或密码错误'), 'error');
                            
                            submitBtn.classList.remove('loading');
                            submitBtn.textContent = originalBtnText;
                            submitBtn.disabled = false;
                            
                            submitBtn.classList.add('error');
                            submitBtn.textContent = '账号或密码错误';
                            checkInput();
                        }
                    } catch (error) {
                        console.error('登录错误:', error);
                        
                        clearInterval(progressInterval);
                        LoadingBar.complete();
                        
                        showNotification('服务器链接失败', 'error');
                        
                        submitBtn.classList.remove('loading');
                        submitBtn.textContent = originalBtnText;
                        submitBtn.disabled = false;
                        
                        submitBtn.classList.add('error');
                        submitBtn.textContent = '网络错误，请重试';
                        checkInput();
                    }
                }
            });
            
            checkLoginStatus();
            
            setTimeout(() => {
                if (pageLoader.parentNode) {
                    pageLoader.style.opacity = 0;
                    pageLoader.style.transition = 'opacity 0.3s ease';
                    setTimeout(() => {
                        if (pageLoader.parentNode) {
                            pageLoader.remove();
                        }
                    }, 300);
                }
            }, 5000);
            
            setTimeout(() => {
                if (usernameInput) {
                    usernameInput.focus();
                }
            }, 100);
        });
    </script>
</body>
</html>