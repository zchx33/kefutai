<?php
// 禁止缓存此页面（iOS Safari可能缓存旧版本导致推送代码不更新）
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if (!defined('FROM_ROUTER')) {
    http_response_code(403);
    exit('喜乐科技@x60898');
}
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/dbconfig.php';
checkLogin();

$isTokenLogin = isset($_SESSION['login_type']) && $_SESSION['login_type'] === 'token';

if (checkExpiration()) {
    jsonResponse([
        'success' => false,
        'message' => '您的账号已过期，请充值后使用此功能'
    ]);
}
?>
<!DOCTYPE html>
<html lang="zh">
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
    <title>聊天设置</title>
<link rel="stylesheet" href="/assets/bootstrap-icons.css">
    <style>
        /* 重置样式 */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: 'Noto Sans SC', sans-serif;
            background-color: #f7f8fa;
            color: #111827;
            line-height: 1.5;
            overflow-x: hidden;
            padding: 8px;
            min-height: 100vh;
        }
        
        /* 容器样式 */
        .container {
            max-width: 24rem;
            margin: 0 auto;
        }
        
        .settings-section {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        /* 卡片通用样式 */
        .settings-card {
            background-color: white;
            border-radius: 1rem;
            padding: 1.25rem;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        }
        
        /* 标题样式 */
        .card-title {
            font-size: 1rem;
            font-weight: 700;
            color: #111827;
            margin: 0;
        }
        
        .card-subtitle {
            font-size: 0.875rem;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 0.75rem;
        }
        
        /* 切换开关样式 */
        .toggle-switch {
            position: relative;
            display: inline-flex;
            align-items: center;
            cursor: pointer;
        }
        
        .toggle-checkbox {
            position: absolute;
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .toggle-slider {
            width: 46px;
            height: 26px;
            background-color: #1d7afe;
            border-radius: 9999px;
            position: relative;
            transition: background-color 0.3s;
        }
        
        .toggle-slider::after {
            content: '';
            position: absolute;
            top: 2px;
            left: 2px;
            width: 22px;
            height: 22px;
            background-color: white;
            border-radius: 50%;
            transition: transform 0.3s;
        }
        
        .toggle-checkbox:checked + .toggle-slider {
            background-color: #1d7afe;
        }
        
        .toggle-checkbox:checked + .toggle-slider::after {
            transform: translateX(20px);
        }
        
        .toggle-checkbox:not(:checked) + .toggle-slider {
            background-color: #e5e7eb;
        }
        
        /* 背景预览区域 */
        .bg-preview {
            margin-top: 1rem;
            border-radius: 0.75rem;
            height: 100px;
            width: 100%;
            background-color: #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        
        .bg-preview-image {
            object-fit: cover;
            width: 100%;
            height: 100%;
        }
        
        /* 预设背景样式 */
        .preset-bg-container {
            display: flex;
            gap: 0.75rem;
            margin-top: 1.25rem;
            flex-wrap: wrap;
        }
        
        .preset-bg-item {
            width: 60px;
            height: 60px;
            border-radius: 0.75rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            border: 2px solid transparent;
        }
        
        .preset-bg-selected {
            border: 2px solid #1d7afe;
            padding: 0.125rem;
        }
        
        .preset-bg-inner {
            width: 100%;
            height: 100%;
            border-radius: 0.5rem;
        }
        
        /* 颜色选择区域 */
        .color-option {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 1.25rem;
        }
        
        .color-picker {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .color-sample {
            width: 48px;
            height: 48px;
            border-radius: 4px;
            border: 1px solid #e5e7eb;
            cursor: pointer;
        }
        
        .color-input {
            display: none;
        }
        
        .color-hex {
            background-color: white;
            border: 1px solid #e5e7eb;
            border-radius: 9999px;
            padding: 0.375rem 1rem;
            font-size: 0.875rem;
            min-width: 100px;
            text-align: center;
        }
        
        .color-hex span {
            color: #1f2937;
            font-weight: 500;
            letter-spacing: 0.05em;
        }
        
        /* 按钮样式 */
        .btn {
            padding: 0.375rem 1rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            cursor: pointer;
            border: none;
        }
        
        .btn-primary {
            background-color: #1d7afe;
            color: white;
        }
        
        .btn-secondary {
            background-color: #f7f8fa;
            color: #1f2937;
        }
        
        .btn-text {
            color: #6b7280;
            font-size: 0.75rem;
            font-weight: 600;
            background: none;
            border: none;
            cursor: pointer;
        }
        
        .btn-full {
            display: block;
            width: 100%;
            padding: 0.75rem;
            text-align: center;
            border: none;
            background-color: white;
            color: #1f2937;
            font-size: 1rem;
            font-weight: 600;
            border-radius: 1rem;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            cursor: pointer;
        }
        
        /* 选项卡样式 */
        .tab-container {
            background-color: #f3f4f6;
            padding: 0.25rem;
            border-radius: 9999px;
            display: flex;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .tab {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            cursor: pointer;
            border: none;
            background: none;
            color: #6b7280;
        }
        
        .tab-active {
            background-color: #1d7afe;
            color: white;
        }
        
        /* 描述文本 */
        .description {
            font-size: 0.75rem;
            color: #6b7280;
            margin-top: 0.25rem;
            max-width: 210px;
        }
        
        /* 气泡颜色预览 */
        .bubble-preview-container {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .bubble-preview {
            padding: 0.75rem;
            border-radius: 1rem;
            max-width: 200px;
            position: relative;
        }
        
        .bubble-preview::before {
            content: '';
            position: absolute;
            width: 0;
            height: 0;
            border-style: solid;
        }
        
        .bubble-preview-left {
            border-bottom-left-radius: 4px;
        }
        
        .bubble-preview-left::before {
            bottom: 0;
            left: -8px;
            border-width: 0 8px 8px 0;
            border-color: transparent;
        }
        
        .bubble-preview-right {
            border-bottom-right-radius: 4px;
        }
        
        .bubble-preview-right::before {
            bottom: 0;
            right: -8px;
            border-width: 8px 8px 0 0;
            border-color: transparent;
        }
        
        .bubble-label {
            font-size: 0.75rem;
            margin-bottom: 0.5rem;
            color: #6b7280;
        }
        
        /* 顶部栏样式 */
        .top-header {
            padding: 0px 25px;
            padding-left: 8px;
            align-items: center;
            display: flex;
            height: 49px;
            position: relative;
        }
        
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
        
        /* 响应式设计 */
        @media (max-width: 640px) {
            .container {
                max-width: 100%;
                padding: 0 8px;
            }
            
            .settings-card {
                padding: 1rem;
            }
            
            .preset-bg-container {
                flex-wrap: wrap;
            }
            
            .color-option {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.75rem;
            }
            
            .color-picker {
                width: 100%;
                justify-content: space-between;
            }
            
            .color-hex {
                min-width: 120px;
            }
            
            .button-group {
                display: flex;
                gap: 0.5rem;
                width: 100%;
                justify-content: space-between;
            }
        }
        
        @media (min-width: 641px) and (max-width: 768px) {
            .container {
                max-width: 28rem;
            }
        }
        
        @media (min-width: 769px) {
            .container {
                max-width: 24rem;
            }
        }
        
        /* 辅助类 */
        .flex {
            display: flex;
        }
        
        .justify-between {
            justify-content: space-between;
        }
        
        .items-center {
            align-items: center;
        }
        
        .mt-1 {
            margin-top: 0.25rem;
        }
        
        .mt-4 {
            margin-top: 1rem;
        }
        
        .mt-5 {
            margin-top: 1.25rem;
        }
        
        .ml-1 {
            margin-left: 0.25rem;
        }
        
        .space-x-5 > *:not(:last-child) {
            margin-right: 1.25rem;
        }
    </style>
</head>
<body>
    <!-- 顶部栏 -->
    <div class="top-header">
        <a href="javascript:void(0)" onclick="window.parent.postMessage('closeModal', '*')" style="display: inline-flex; align-items: center; text-decoration: none; color: inherit;">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 h-6 w-6">
                <path d="M14 6l-6 6 6 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path>
            </svg>
            <div style="border: 14px solid transparent;">返回</div>
        </a>
    </div>
    
    <div class="container">
        <div class="settings-section">
            <!-- 卡片1: 自定义聊天背景 -->
            <div class="settings-card" id="background-settings">
                <div class="flex justify-between items-center">
                    <h2 class="card-title">自定义聊天背景</h2>
                    <label class="toggle-switch" for="bg-toggle">
                        <input type="checkbox" id="bg-toggle" class="toggle-checkbox">
                        <div class="toggle-slider"></div>
                    </label>
                </div>
                
                <div class="bg-preview" id="background-preview">
                    <!-- 背景预览将通过JS动态设置 -->
                </div>
                
                <div class="mt-5">
                    <h3 class="card-subtitle">预设背景</h3>
                    <div class="preset-bg-container" id="preset-backgrounds">
    <div class="preset-bg-item" data-bg-type="solid" data-bg-color="#000000">
        <div class="preset-bg-inner" style="background-color: #000000;"></div>
    </div>
    <div class="preset-bg-item" data-bg-type="gradient" 
         data-bg-value="linear-gradient(120deg, #a1c4fd 0%, #c2e9fb 100%)">
        <div class="preset-bg-inner" 
             style="background: linear-gradient(120deg, #a1c4fd 0%, #c2e9fb 100%)"></div>
    </div>
    <div class="preset-bg-item preset-bg-selected" data-bg-type="pattern" data-bg-value="/assets/img/pattern.svg">
        <div class="preset-bg-inner" style="background: linear-gradient(135deg, #4a7bff 0%, #8ab4ff 100%); display: flex; align-items: center; justify-content: center; font-size: 10px; color: #fff;">图案</div>
    </div>
</div>
                </div>
                
                <div class="color-option">
                    <span class="card-subtitle">背景色</span>
                    <div class="color-picker">
                        <input type="color" id="background-color-picker" class="color-input" value="#f0f0f0">
                        <div class="color-sample" id="background-color-sample" style="background-color: #f0f0f0;"></div>
                        <div class="color-hex">
                            <span id="background-color-hex">#F0F0F0</span>
                        </div>
                    </div>
                </div>
                
                <div class="color-option">
                    <span class="card-subtitle">背景图片</span>
                    <div class="button-group">
                        <input type="file" id="background-image-input" accept="image/*" style="display: none;">
                        <button class="btn btn-secondary" id="select-image-btn">选择图片</button>
                        <button class="btn-text" id="clear-image-btn">清除</button>
                    </div>
                </div>
            </div>

            <!-- 卡片1.5: 动态壁纸设置 -->
            <div class="settings-card" id="livewallpaper-settings">
                <div class="flex justify-between items-center">
                    <div>
                        <h2 class="card-title">动态壁纸</h2>
                        <p class="description">开启后聊天背景将显示动态动画效果</p>
                    </div>
                    <label class="toggle-switch" for="livewallpaper-toggle">
                        <input type="checkbox" id="livewallpaper-toggle" class="toggle-checkbox">
                        <div class="toggle-slider"></div>
                    </label>
                </div>
                
                <div class="bg-preview" id="livewallpaper-preview" style="margin-top: 1rem;">
                    <canvas id="livewallpaper-preview-canvas" style="width: 100%; height: 100%; border-radius: 0.75rem;"></canvas>
                </div>
                
                <div class="mt-5">
                    <h3 class="card-subtitle">动画效果</h3>
                    <div class="preset-bg-container" id="livewallpaper-effects">
                        <div class="preset-bg-item preset-bg-selected" data-effect="particles">
                            <div class="preset-bg-inner" style="background: linear-gradient(135deg, #0f0c29 0%, #302b63 50%, #24243e 100%); display: flex; align-items: center; justify-content: center; font-size: 10px; color: #fff;">粒子</div>
                        </div>
                        <div class="preset-bg-item" data-effect="waves">
                            <div class="preset-bg-inner" style="background: linear-gradient(135deg, #1a2980 0%, #26d0ce 100%); display: flex; align-items: center; justify-content: center; font-size: 10px; color: #fff;">波浪</div>
                        </div>
                        <div class="preset-bg-item" data-effect="starry">
                            <div class="preset-bg-inner" style="background: linear-gradient(135deg, #000000 0%, #1a1a2e 100%); display: flex; align-items: center; justify-content: center; font-size: 10px; color: #fff;">星空</div>
                        </div>
                        <div class="preset-bg-item" data-effect="bubbles">
                            <div class="preset-bg-inner" style="background: linear-gradient(135deg, #2193b0 0%, #6dd5ed 100%); display: flex; align-items: center; justify-content: center; font-size: 10px; color: #fff;">气泡</div>
                        </div>
                        <div class="preset-bg-item" data-effect="matrix">
                            <div class="preset-bg-inner" style="background: linear-gradient(135deg, #000000 0%, #0f380f 100%); display: flex; align-items: center; justify-content: center; font-size: 10px; color: #0f0;">代码</div>
                        </div>
                        <div class="preset-bg-item" data-effect="video">
                            <div class="preset-bg-inner" style="background: linear-gradient(135deg, #2c3e50 0%, #4a6741 100%); display: flex; align-items: center; justify-content: center; font-size: 10px; color: #fff;">视频</div>
                        </div>
                    </div>
                </div>
                
                <!-- 视频动态壁纸设置 -->
                <div id="video-wallpaper-settings" style="display: none; margin-top: 1rem;">
                    <h3 class="card-subtitle">自定义视频</h3>
                    <div class="color-option">
                        <div class="button-group">
                            <input type="file" id="video-wallpaper-input" accept="video/mp4,video/webm,video/ogg" style="display: none;">
                            <button class="btn btn-secondary" id="select-video-btn">选择视频</button>
                            <button class="btn-text" id="clear-video-btn">清除</button>
                        </div>
                    </div>
                    <div id="video-wallpaper-info" style="margin-top: 0.5rem; font-size: 0.75rem; color: #6b7280; display: none;">
                        <span id="video-wallpaper-name"></span>
                    </div>
                    <div class="mt-3">
                        <label class="flex items-center gap-2" style="cursor: pointer;">
                            <input type="checkbox" id="video-loop-toggle" checked style="width: 16px; height: 16px;">
                            <span style="font-size: 0.875rem; color: #374151;">循环播放</span>
                        </label>
                    </div>
                    <div class="mt-2">
                        <label class="flex items-center gap-2" style="cursor: pointer;">
                            <input type="checkbox" id="video-mute-toggle" checked style="width: 16px; height: 16px;">
                            <span style="font-size: 0.875rem; color: #374151;">静音播放</span>
                        </label>
                    </div>
                    <div class="mt-3" style="font-size: 0.75rem; color: #6b7280; background: #f3f4f6; padding: 0.75rem; border-radius: 0.5rem;">
                        <strong>支持的浏览器：</strong><br>
                        Chrome, Edge, Firefox, Safari, Opera<br><br>
                        <strong>推荐格式：</strong>MP4 (H.264)<br>
                        <strong>文件大小：</strong>最大 4MB<br>
                        <strong>注意事项：</strong>部分浏览器可能需要静音才能自动播放
                    </div>
                </div>
            </div>
            
            <!-- 卡片2: 聊天气泡颜色 -->
            <div class="settings-card" id="bubble-settings">
                <div class="flex justify-between items-center">
                    <h2 class="card-title">聊天气泡颜色</h2>
                    <div class="tab-container">
                        <button class="tab" data-theme="light">浅色</button>
                        <button class="tab tab-active ml-1" data-theme="dark">深色</button>
                    </div>
                </div>
                
                <!-- 气泡预览 -->
                <div class="mt-5">
                    <div class="bubble-preview-container">
                        <div>
                            <div class="bubble-label">客服气泡</div>
                            <div class="bubble-preview bubble-preview-right" id="agent-bubble-preview" style="background-color: #effdde;">
                                <div>客服消息示例</div>
                            </div>
                        </div>
                        <div>
                            <div class="bubble-label">客户气泡</div>
                            <div class="bubble-preview bubble-preview-left" id="customer-bubble-preview" style="background-color: #ffffff;">
                                <div>客户消息示例</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- 卡片3: 显示平台名称 -->
<div class="settings-card">
    <div class="flex justify-between items-center">
        <div>
            <h2 class="card-title">显示平台名称</h2>
            <p class="description">开启后在聊天页面标题中显示[平台名称]前缀</p>
        </div>
        <label class="toggle-switch" for="platform-toggle">
            <input type="checkbox" id="platform-toggle" class="toggle-checkbox">
            <div class="toggle-slider"></div>
        </label>
    </div>
</div>

<!-- 卡片4: AI智能回复功能 -->
<div class="settings-card">
    <div class="flex justify-between items-center">
        <div>
            <h2 class="card-title">AI智能回复</h2>
            <p class="description">开启后在聊天页面显示AI智能回复按钮</p>
        </div>
        <label class="toggle-switch" for="ai-function-toggle">
            <input type="checkbox" id="ai-function-toggle" class="toggle-checkbox" checked>
            <div class="toggle-slider"></div>
        </label>
    </div>
</div>

<!-- 卡片 5: WebSocket 状态条 -->
<div class="settings-card">
    <div class="flex justify-between items-center">
        <div>
            <h2 class="card-title">显示 WebSocket 状态</h2>
            <p class="description">开启后在聊天页面顶部显示 WebSocket 连接状态条</p>
        </div>
        <label class="toggle-switch" for="ws-status-bar-toggle">
            <input type="checkbox" id="ws-status-bar-toggle" class="toggle-checkbox">
            <div class="toggle-slider"></div>
        </label>
    </div>
</div>
            
            <!-- 卡片6: 消息通知 -->
<div class="settings-card">
    <div class="flex justify-between items-center">
        <div>
            <h2 class="card-title">消息通知</h2>
            <p class="description" id="notify-description">开启后支持离线消息推送，需添加到主屏幕使用</p>
        </div>
        <label class="toggle-switch" for="notify-toggle">
            <input type="checkbox" id="notify-toggle" class="toggle-checkbox">
            <div class="toggle-slider"></div>
        </label>
    </div>
    <div id="notify-status" style="margin-top: 8px; font-size: 12px; color: #6b7280; display: none;"></div>
</div>

<!-- 卡片7: 访客Token -->
<div class="settings-card" id="visitor-token-card">
    <div class="flex justify-between items-center">
        <div>
            <h2 class="card-title">访客 Token</h2>
            <p class="description">密钥</p>
        </div>
    </div>
    <div style="margin-top: 12px;">
        <div style="display: flex; align-items: center; gap: 8px; background: #f3f4f6; border-radius: 8px; padding: 10px 12px;">
            <code id="visitorTokenDisplay" style="flex: 1; font-size: 13px; color: #1f2937; word-break: break-all; font-family: 'SF Mono', Monaco, 'Courier New', monospace;">加载中...</code>
            <button class="btn btn-secondary" id="copyTokenBtn" style="padding: 4px 10px; font-size: 12px; white-space: nowrap;">复制</button>
        </div>
        <?php if (!$isTokenLogin): ?>
        <div style="display: flex; gap: 8px; margin-top: 10px;">
            <button class="btn btn-primary" id="regenerateTokenBtn" style="flex: 1; font-size: 13px;">重新生成</button>
        </div>
        <p style="font-size: 11px; color: #9ca3af; margin-top: 8px; line-height: 1.4;">重置Token后，旧Token将立即失效，Token登录不受二级密码验证！！！</p>
        <?php else: ?>
        <p style="font-size: 11px; color: #9ca3af; margin-top: 8px; line-height: 1.4;">当前为Token登录模式，无法修改Token</p>
        <?php endif; ?>
    </div>
</div>

            <!-- 按钮4: 恢复默认 -->
            <button class="btn-full" id="reset-default-btn">恢复默认</button>
        </div>
    </div>

    <!-- 消息提示 -->

    <script>
    // 本地存储键
    const STORAGE_KEYS = {
        BACKGROUND: 'chat_background_settings',
        BUBBLE: 'chat_bubble_settings',
        PLATFORM_NAME: 'chat_platform_name_settings',
        NOTIFICATION: 'chat_notification_settings',
        AI_FUNCTION: 'chat_ai_function_enabled',
        WS_STATUS_BAR: 'chat_ws_status_bar_enabled',
        LIVE_WALLPAPER: 'chat_live_wallpaper_settings'
    };

    // 默认设置
    const DEFAULT_SETTINGS = {
        background: {
            enabled: false,
            type: 'pattern',
            color: '#f0f0f0',
            gradient: 'linear-gradient(135deg, #4a7bff 0%, #8ab4ff 100%)',
            pattern: '/assets/img/pattern.svg',
            image: null
        },
        bubble: {
            theme: 'light',
            agentColor: '#effdde',
            customerColor: '#ffffff'
        },
        platformName: {
            enabled: true
        },
        notification: {
            enabled: false,
            requested: false,
            granted: false,
            deviceType: null
        },
        aiFunction: {
            enabled: true
        },
        wsStatusBar: {
            enabled: false
        },
        liveWallpaper: {
            enabled: false,
            effect: 'particles',
            videoData: null,
            videoName: null,
            videoLoop: true,
            videoMute: true
        }
    };

    // 工具函数
    function showToast(message, duration, type) {
        duration = duration || 2000;
        type = type || 'success';
        
        var typeClass = 'toast-info';
        var iconHtml = '<i class="iconify" data-icon="bi:info-circle"></i>';
        
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
        
        var toastId = 'toast-' + Date.now();
        var toast = document.createElement('div');
        toast.id = toastId;
        toast.className = 'new-user-toast notification-enter-active ' + typeClass;
        toast.innerHTML = 
            '<div class="toast-icon">' + iconHtml + '</div>' +
            '<div class="toast-text">' + message + '</div>' +
            '<button class="toast-close"><i class="iconify" data-icon="bi:x-lg"></i></button>';
        
        document.body.appendChild(toast);
        
        toast.addEventListener('click', function(e) {
            if (!e.target.closest('.toast-close')) {
                removeToast(toastId);
            }
        });
        
        toast.querySelector('.toast-close').addEventListener('click', function(e) {
            e.stopPropagation();
            removeToast(toastId);
        });
        
        setTimeout(function() {
            removeToast(toastId);
        }, duration);
    }
    
    function removeToast(id) {
        var toast = document.getElementById(id);
        if (toast) {
            toast.classList.remove('notification-enter-active');
            toast.classList.add('notification-leave-active');
            setTimeout(function() {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 300);
        }
    }

    function hexToRgb(hex) {
        const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
        return result ? {
            r: parseInt(result[1], 16),
            g: parseInt(result[2], 16),
            b: parseInt(result[3], 16)
        } : null;
    }

    function isLightColor(hex) {
        const rgb = hexToRgb(hex);
        if (!rgb) return true;
        
        // 计算相对亮度
        const brightness = (rgb.r * 299 + rgb.g * 587 + rgb.b * 114) / 1000;
        return brightness > 128;
    }

    // 设备检测函数
    function detectDeviceType() {
        const userAgent = navigator.userAgent.toLowerCase();
        const isIOS = /iphone|ipad|ipod/.test(userAgent);
        const isStandalone = window.navigator.standalone || window.matchMedia('(display-mode: standalone)').matches;
        
        return {
            isIOS: isIOS,
            isStandalone: isStandalone,
            isPWA: isStandalone && isIOS,
            userAgent: userAgent
        };
    }

    // 设置管理器
    class SettingsManager {
        constructor() {
            this.currentSettings = this.loadSettings();
            this.initEventListeners();
            this.loadUIFromSettings();
        }

        loadSettings() {
            return {
                background: this.loadSetting(STORAGE_KEYS.BACKGROUND, DEFAULT_SETTINGS.background),
                bubble: this.loadSetting(STORAGE_KEYS.BUBBLE, DEFAULT_SETTINGS.bubble),
                platformName: this.loadSetting(STORAGE_KEYS.PLATFORM_NAME, DEFAULT_SETTINGS.platformName),
                notification: this.loadSetting(STORAGE_KEYS.NOTIFICATION, {
                    ...DEFAULT_SETTINGS.notification,
                    deviceType: detectDeviceType()
                }),
                aiFunction: this.loadSetting(STORAGE_KEYS.AI_FUNCTION, DEFAULT_SETTINGS.aiFunction),
                wsStatusBar: this.loadSetting(STORAGE_KEYS.WS_STATUS_BAR, DEFAULT_SETTINGS.wsStatusBar),
                liveWallpaper: this.loadSetting(STORAGE_KEYS.LIVE_WALLPAPER, DEFAULT_SETTINGS.liveWallpaper)
            };
        }

        loadSetting(key, defaultValue) {
            try {
                const saved = localStorage.getItem(key);
                if (saved) {
                    const parsed = JSON.parse(saved);
                    
                    // 如果是背景设置，确保color存在
                    if (key === STORAGE_KEYS.BACKGROUND && parsed) {
                        if (!parsed.color) {
                            parsed.color = defaultValue.color || '#000000';
                        }
                    }
                    
                    // 如果是气泡设置，确保颜色存在
                    if (key === STORAGE_KEYS.BUBBLE && parsed) {
                        if (!parsed.agentColor) {
                            parsed.agentColor = defaultValue.agentColor || '#e0ffc7';
                        }
                        if (!parsed.customerColor) {
                            parsed.customerColor = defaultValue.customerColor || '#ffffff';
                        }
                    }
                    
                    // 如果是通知设置，确保设备类型存在
                    if (key === STORAGE_KEYS.NOTIFICATION && parsed) {
                        if (!parsed.deviceType) {
                            parsed.deviceType = detectDeviceType();
                        }
                        // 确保所有必需的字段都存在
                        if (typeof parsed.enabled === 'undefined') {
                            parsed.enabled = defaultValue.enabled || false;
                        }
                        if (typeof parsed.requested === 'undefined') {
                            parsed.requested = defaultValue.requested || false;
                        }
                        if (typeof parsed.granted === 'undefined') {
                            parsed.granted = defaultValue.granted || false;
                        }
                    }
                    
                    // 如果是AI功能设置，确保enabled存在
                    if (key === STORAGE_KEYS.AI_FUNCTION && parsed) {
                        if (typeof parsed.enabled === 'undefined') {
                            parsed.enabled = defaultValue.enabled !== undefined ? defaultValue.enabled : true;
                        }
                    }
                    
                    // 如果是动态壁纸设置，合并默认值确保所有字段存在
                    if (key === STORAGE_KEYS.LIVE_WALLPAPER && parsed) {
                        return { ...defaultValue, ...parsed };
                    }
                    
                    return parsed;
                }
                return defaultValue;
            } catch (error) {
                console.error(`加载设置失败 (${key}):`, error);
                return defaultValue;
            }
        }

        saveSetting(key, value) {
            try {
                // 视频数据可能很大，需要检查存储空间
                if (key === STORAGE_KEYS.LIVE_WALLPAPER && value && value.videoData) {
                    const dataSize = new Blob([value.videoData]).size;
                    const maxSize = 4 * 1024 * 1024; // 4MB限制（localStorage通常限制5-10MB，留一些余量给其他数据）
                    if (dataSize > maxSize) {
                        console.warn('视频数据过大，仅保存设置信息，不保存视频数据');
                        // 创建一个不包含视频数据的副本
                        const settingsWithoutVideo = { ...value };
                        delete settingsWithoutVideo.videoData;
                        localStorage.setItem(key, JSON.stringify(settingsWithoutVideo));
                        showToast('视频文件过大（超过4MB），已保存其他设置。请选择较小的视频文件。');
                        return true;
                    }
                }
                localStorage.setItem(key, JSON.stringify(value));
                return true;
            } catch (error) {
                console.error(`保存设置失败 (${key}):`, error);
                // 如果是QuotaExceededError，尝试清理旧数据
                if (error.name === 'QuotaExceededError' || error.code === 22) {
                    showToast('存储空间不足，请清除视频或清理缓存');
                } else {
                    showToast('保存设置失败，请检查存储空间');
                }
                return false;
            }
        }

        initEventListeners() {
            // 背景开关
            const bgToggle = document.getElementById('bg-toggle');
            if (bgToggle) {
                bgToggle.addEventListener('change', (e) => {
                    this.currentSettings.background.enabled = e.target.checked;
                    this.saveSetting(STORAGE_KEYS.BACKGROUND, this.currentSettings.background);
                    this.updateBackgroundPreview();
                    showToast('背景设置已更新');
                });
            }

            // 预设背景选择
            document.querySelectorAll('.preset-bg-item').forEach(item => {
                item.addEventListener('click', (e) => {
                    const type = item.dataset.bgType;
                    const value = item.dataset.bgValue;
                    
                    // 移除其他选中状态
                    document.querySelectorAll('.preset-bg-item').forEach(i => {
                        i.classList.remove('preset-bg-selected');
                    });
                    
                    // 添加选中状态
                    item.classList.add('preset-bg-selected');
                    
                    // 更新设置
                    this.currentSettings.background.type = type;
                    
                    if (type === 'solid') {
                        this.currentSettings.background.color = value;
                        this.updateColorPicker('background-color-picker', value);
                    } else if (type === 'gradient') {
                        this.currentSettings.background.gradient = value;
                    } else if (type === 'pattern') {
                        this.currentSettings.background.pattern = value;
                    }
                    
                    this.saveSetting(STORAGE_KEYS.BACKGROUND, this.currentSettings.background);
                    this.updateBackgroundPreview();
                    showToast('预设背景已应用');
                });
            });

            // 背景颜色选择
            const bgColorPicker = document.getElementById('background-color-picker');
            const bgColorSample = document.getElementById('background-color-sample');
            const bgColorHex = document.getElementById('background-color-hex');

            if (bgColorPicker && bgColorSample && bgColorHex) {
                bgColorPicker.addEventListener('input', (e) => {
                    const color = e.target.value;
                    bgColorSample.style.backgroundColor = color;
                    bgColorHex.textContent = color.toUpperCase();
                    
                    this.currentSettings.background.type = 'solid';
                    this.currentSettings.background.color = color;
                    this.saveSetting(STORAGE_KEYS.BACKGROUND, this.currentSettings.background);
                    
                    // 选中自定义背景选项
                    document.querySelectorAll('.preset-bg-item').forEach(item => {
                        item.classList.remove('preset-bg-selected');
                    });
                    
                    this.updateBackgroundPreview();
                });

                if (bgColorSample) {
                    bgColorSample.addEventListener('click', () => {
                        bgColorPicker.click();
                    });
                }
            }

            // 背景图片选择
            const selectImageBtn = document.getElementById('select-image-btn');
            const clearImageBtn = document.getElementById('clear-image-btn');
            const backgroundImageInput = document.getElementById('background-image-input');

            if (selectImageBtn) {
                selectImageBtn.addEventListener('click', () => {
                    if (backgroundImageInput) {
                        backgroundImageInput.click();
                    }
                });
            }

            if (backgroundImageInput) {
                backgroundImageInput.addEventListener('change', (e) => {
                    const file = e.target.files[0];
                    if (file) {
                        if (!file.type.startsWith('image/')) {
                            showToast('请选择图片文件');
                            return;
                        }
                        
                        if (file.size > 5 * 1024 * 1024) {
                            showToast('图片大小不能超过5MB');
                            return;
                        }
                        
                        const reader = new FileReader();
                        reader.onload = (e) => {
                            this.currentSettings.background.type = 'image';
                            this.currentSettings.background.image = e.target.result;
                            this.saveSetting(STORAGE_KEYS.BACKGROUND, this.currentSettings.background);
                            
                            // 选中自定义背景选项
                            document.querySelectorAll('.preset-bg-item').forEach(item => {
                                item.classList.remove('preset-bg-selected');
                            });
                            
                            this.updateBackgroundPreview();
                            showToast('背景图片已设置');
                        };
                        reader.readAsDataURL(file);
                    }
                });
            }

            if (clearImageBtn) {
                clearImageBtn.addEventListener('click', () => {
                    if (this.currentSettings.background.type === 'image') {
                        this.currentSettings.background.type = 'solid';
                        this.currentSettings.background.image = null;
                        this.saveSetting(STORAGE_KEYS.BACKGROUND, this.currentSettings.background);
                        this.updateBackgroundPreview();
                        showToast('背景图片已清除');
                    }
                });
            }

            // 气泡主题切换
            document.querySelectorAll('.tab').forEach(tab => {
                tab.addEventListener('click', (e) => {
                    const theme = e.target.dataset.theme;
                    
                    // 移除其他激活状态
                    document.querySelectorAll('.tab').forEach(t => {
                        t.classList.remove('tab-active');
                    });
                    
                    // 添加激活状态
                    e.target.classList.add('tab-active');
                    
                    // 更新设置
                    this.currentSettings.bubble.theme = theme;
                    this.saveSetting(STORAGE_KEYS.BUBBLE, this.currentSettings.bubble);
                    
                    // 根据主题设置默认颜色
                    if (theme === 'light') {
                        this.currentSettings.bubble.agentColor = '#effdde';
                        this.currentSettings.bubble.customerColor = '#ffffff';
                    } else {
                        this.currentSettings.bubble.agentColor = '#2b5278';
                        this.currentSettings.bubble.customerColor = '#182533';
                    }
                    
                    this.saveSetting(STORAGE_KEYS.BUBBLE, this.currentSettings.bubble);
                    this.updateBubblePreviews();
                    this.loadColorPickersFromSettings();
                    showToast(`已切换到${theme === 'light' ? '浅色' : '深色'}主题`);
                });
            });

            // 平台名称显示开关
            const platformToggle = document.getElementById('platform-toggle');
            if (platformToggle) {
                platformToggle.addEventListener('change', (e) => {
                    this.currentSettings.platformName.enabled = e.target.checked;
                    this.saveSetting(STORAGE_KEYS.PLATFORM_NAME, this.currentSettings.platformName);
                    showToast(`平台名称显示已${e.target.checked ? '开启' : '关闭'}`);
                    
                    // 通知聊天页面更新
                    this.notifyChatPage('platform_name_updated');
                });
            }

            // 消息通知开关
            const notifyToggle = document.getElementById('notify-toggle');
            if (notifyToggle) {
                notifyToggle.addEventListener('change', (e) => {
                    const isEnabled = e.target.checked;
                    this.currentSettings.notification.enabled = isEnabled;
                    
                    if (isEnabled) {
                        this.requestNotificationPermission();
                    } else {
                        // 关闭通知时取消Push订阅
                        this.currentSettings.notification.enabled = false;
                        this.saveSetting(STORAGE_KEYS.NOTIFICATION, this.currentSettings.notification);
                        this.unsubscribePushNotification();
                        showToast('消息通知已关闭');
                    }
                });
            }

            // AI功能开关
            const aiFunctionToggle = document.getElementById('ai-function-toggle');
            if (aiFunctionToggle) {
                aiFunctionToggle.addEventListener('change', (e) => {
                    this.currentSettings.aiFunction.enabled = e.target.checked;
                    this.saveSetting(STORAGE_KEYS.AI_FUNCTION, this.currentSettings.aiFunction);
                    showToast(`AI功能已${e.target.checked ? '开启' : '关闭'}`);
                    
                    // 通知聊天页面更新
                    this.notifyAIFunctionChanged(e.target.checked);
                });
            }
            
            // WebSocket 状态条开关
            const wsStatusBarToggle = document.getElementById('ws-status-bar-toggle');
            if (wsStatusBarToggle) {
                wsStatusBarToggle.addEventListener('change', (e) => {
                    this.currentSettings.wsStatusBar.enabled = e.target.checked;
                    this.saveSetting(STORAGE_KEYS.WS_STATUS_BAR, this.currentSettings.wsStatusBar);
                    showToast(`WebSocket 状态条已${e.target.checked ? '开启' : '关闭'}`);
                    
                    // 通知聊天页面更新
                    this.notifyWsStatusBarChanged(e.target.checked);
                });
            }

            // 动态壁纸开关
            const liveWallpaperToggle = document.getElementById('livewallpaper-toggle');
            if (liveWallpaperToggle) {
                liveWallpaperToggle.addEventListener('change', (e) => {
                    this.currentSettings.liveWallpaper.enabled = e.target.checked;
                    this.saveSetting(STORAGE_KEYS.LIVE_WALLPAPER, this.currentSettings.liveWallpaper);
                    showToast(`动态壁纸已${e.target.checked ? '开启' : '关闭'}`);
                    
                    // 更新预览
                    this.updateLiveWallpaperPreview();
                    
                    // 通知聊天页面更新
                    this.notifyLiveWallpaperChanged();
                });
            }

            // 动态壁纸效果选择
            document.querySelectorAll('#livewallpaper-effects .preset-bg-item').forEach(item => {
                item.addEventListener('click', (e) => {
                    const effect = item.dataset.effect;
                    
                    // 移除其他选中状态
                    document.querySelectorAll('#livewallpaper-effects .preset-bg-item').forEach(i => {
                        i.classList.remove('preset-bg-selected');
                    });
                    
                    // 添加选中状态
                    item.classList.add('preset-bg-selected');
                    
                    // 更新设置
                    this.currentSettings.liveWallpaper.effect = effect;
                    this.saveSetting(STORAGE_KEYS.LIVE_WALLPAPER, this.currentSettings.liveWallpaper);
                    
                    // 显示/隐藏视频设置
                    const videoSettings = document.getElementById('video-wallpaper-settings');
                    if (videoSettings) {
                        videoSettings.style.display = effect === 'video' ? 'block' : 'none';
                    }
                    
                    // 更新预览
                    this.updateLiveWallpaperPreview();
                    
                    // 通知聊天页面更新
                    this.notifyLiveWallpaperChanged();
                    
                    showToast(`已切换到${this.getEffectName(effect)}效果`);
                });
            });
            
            // 视频壁纸文件选择
            const selectVideoBtn = document.getElementById('select-video-btn');
            const videoInput = document.getElementById('video-wallpaper-input');
            if (selectVideoBtn && videoInput) {
                selectVideoBtn.addEventListener('click', () => {
                    videoInput.click();
                });
                
                videoInput.addEventListener('change', (e) => {
                    const file = e.target.files[0];
                    if (file) {
                        // 验证文件类型
                        if (!file.type.startsWith('video/')) {
                            showToast('请选择有效的视频文件');
                            return;
                        }
                        
                        // 检查视频格式兼容性
                        const supportedFormats = [
                            'video/mp4',  // H.264 - 所有浏览器支持
                            'video/webm', // VP8/VP9 - Chrome, Firefox, Edge
                            'video/ogg'   // Theora - Firefox, Chrome
                        ];
                        
                        if (!supportedFormats.includes(file.type)) {
                            showToast('不支持的视频格式: ' + file.type + '。请使用 MP4 (H.264) 格式以获得最佳兼容性');
                            return;
                        }
                        
                        // 验证文件大小（最大4MB，因为localStorage通常限制5-10MB）
                        if (file.size > 4 * 1024 * 1024) {
                            showToast('视频文件过大，请选择小于4MB的文件');
                            return;
                        }
                        
                        // 读取文件为DataURL
                        const reader = new FileReader();
                        reader.onload = (event) => {
                            this.currentSettings.liveWallpaper.videoData = event.target.result;
                            this.currentSettings.liveWallpaper.videoName = file.name;
                            this.saveSetting(STORAGE_KEYS.LIVE_WALLPAPER, this.currentSettings.liveWallpaper);
                            
                            // 更新UI
                            const videoInfo = document.getElementById('video-wallpaper-info');
                            const videoName = document.getElementById('video-wallpaper-name');
                            if (videoInfo && videoName) {
                                videoName.textContent = file.name;
                                videoInfo.style.display = 'block';
                            }
                            
                            this.updateLiveWallpaperPreview();
                            this.notifyLiveWallpaperChanged();
                            showToast('视频已选择');
                        };
                        reader.readAsDataURL(file);
                    }
                });
            }
            
            // 清除视频
            const clearVideoBtn = document.getElementById('clear-video-btn');
            if (clearVideoBtn) {
                clearVideoBtn.addEventListener('click', () => {
                    this.currentSettings.liveWallpaper.videoData = null;
                    this.currentSettings.liveWallpaper.videoName = null;
                    this.saveSetting(STORAGE_KEYS.LIVE_WALLPAPER, this.currentSettings.liveWallpaper);
                    
                    const videoInfo = document.getElementById('video-wallpaper-info');
                    if (videoInfo) {
                        videoInfo.style.display = 'none';
                    }
                    
                    if (videoInput) {
                        videoInput.value = '';
                    }
                    
                    this.updateLiveWallpaperPreview();
                    this.notifyLiveWallpaperChanged();
                    showToast('视频已清除');
                });
            }
            
            // 视频循环播放开关
            const videoLoopToggle = document.getElementById('video-loop-toggle');
            if (videoLoopToggle) {
                videoLoopToggle.addEventListener('change', (e) => {
                    this.currentSettings.liveWallpaper.videoLoop = e.target.checked;
                    this.saveSetting(STORAGE_KEYS.LIVE_WALLPAPER, this.currentSettings.liveWallpaper);
                    this.notifyLiveWallpaperChanged();
                });
            }
            
            // 视频静音开关
            const videoMuteToggle = document.getElementById('video-mute-toggle');
            if (videoMuteToggle) {
                videoMuteToggle.addEventListener('change', (e) => {
                    this.currentSettings.liveWallpaper.videoMute = e.target.checked;
                    this.saveSetting(STORAGE_KEYS.LIVE_WALLPAPER, this.currentSettings.liveWallpaper);
                    this.notifyLiveWallpaperChanged();
                });
            }

            // 访客Token管理
            this.loadVisitorToken();

            const copyTokenBtn = document.getElementById('copyTokenBtn');
            if (copyTokenBtn) {
                copyTokenBtn.addEventListener('click', () => {
                    const tokenDisplay = document.getElementById('visitorTokenDisplay');
                    if (tokenDisplay && tokenDisplay.textContent && tokenDisplay.textContent !== '加载中...') {
                        navigator.clipboard.writeText(tokenDisplay.textContent).then(() => {
                            showToast('Token已复制到剪贴板');
                            copyTokenBtn.textContent = '已复制';
                            setTimeout(() => { copyTokenBtn.textContent = '复制'; }, 2000);
                        }).catch(() => {
                            const textarea = document.createElement('textarea');
                            textarea.value = tokenDisplay.textContent;
                            document.body.appendChild(textarea);
                            textarea.select();
                            document.execCommand('copy');
                            document.body.removeChild(textarea);
                            showToast('Token已复制');
                        });
                    }
                });
            }

            const regenerateTokenBtn = document.getElementById('regenerateTokenBtn');
            if (regenerateTokenBtn) {
                regenerateTokenBtn.addEventListener('click', async () => {
                    if (!confirm('确定要重新生成Token吗？旧Token将立即失效。')) return;

                    regenerateTokenBtn.disabled = true;
                    regenerateTokenBtn.textContent = '生成中...';

                    try {
                        const response = await fetch('/api/sign/check?action=regenerate_visitor_token', {
                            method: 'POST'
                        });
                        const data = await response.json();

                        if (data.code === 1) {
                            const tokenDisplay = document.getElementById('visitorTokenDisplay');
                            if (tokenDisplay && data.data && data.data.visitor_token) {
                                tokenDisplay.textContent = data.data.visitor_token;
                            }
                            showToast('Token已重新生成');
                        } else {
                            showToast(data.message || '重置失败', 'error');
                        }
                    } catch (error) {
                        showToast('网络错误', 'error');
                    }

                    regenerateTokenBtn.disabled = false;
                    regenerateTokenBtn.textContent = '重新生成';
                });
            }

            // 恢复默认设置
            const resetDefaultBtn = document.getElementById('reset-default-btn');
            if (resetDefaultBtn) {
                resetDefaultBtn.addEventListener('click', () => {
                    if (confirm('确定要恢复所有设置为默认值吗？')) {
                        this.currentSettings = JSON.parse(JSON.stringify(DEFAULT_SETTINGS));
                        this.currentSettings.notification.deviceType = detectDeviceType();
                        
                        // 清除所有本地存储
                        localStorage.removeItem(STORAGE_KEYS.BACKGROUND);
                        localStorage.removeItem(STORAGE_KEYS.BUBBLE);
                        localStorage.removeItem(STORAGE_KEYS.PLATFORM_NAME);
                        localStorage.removeItem(STORAGE_KEYS.NOTIFICATION);
                        localStorage.removeItem(STORAGE_KEYS.AI_FUNCTION);
                        localStorage.removeItem(STORAGE_KEYS.WS_STATUS_BAR);
                        localStorage.removeItem(STORAGE_KEYS.LIVE_WALLPAPER);
                        
                        this.loadUIFromSettings();
                        this.updateBackgroundPreview();
                        this.updateBubblePreviews();
                        this.updateNotificationDescription();
                        showToast('已恢复默认设置');
                    }
                });
            }
        }

        loadUIFromSettings() {
            // 背景设置
            const bgToggle = document.getElementById('bg-toggle');
            if (bgToggle && this.currentSettings.background) {
                bgToggle.checked = this.currentSettings.background.enabled;
            }
            
            // 选择对应的预设背景
            document.querySelectorAll('.preset-bg-item').forEach(item => {
                item.classList.remove('preset-bg-selected');
                
                const type = item.dataset.bgType;
                const value = item.dataset.bgValue;
                
                if (type === this.currentSettings.background.type) {
                    if (type === 'solid' && value === this.currentSettings.background.color) {
                        item.classList.add('preset-bg-selected');
                    } else if (type === 'gradient' && value === this.currentSettings.background.gradient) {
                        item.classList.add('preset-bg-selected');
                    } else if (type === 'pattern' && value === this.currentSettings.background.pattern) {
                        item.classList.add('preset-bg-selected');
                    }
                }
            });
            
            // 确保颜色值存在
            const bgColor = this.currentSettings.background.color || '#000000';
            
            // 更新颜色选择器
            this.updateColorPicker('background-color-picker', bgColor);
            
            // 气泡主题
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('tab-active');
                if (tab.dataset.theme === this.currentSettings.bubble.theme) {
                    tab.classList.add('tab-active');
                }
            });
            
            // 平台名称显示开关
            const platformToggle = document.getElementById('platform-toggle');
            if (platformToggle && this.currentSettings.platformName) {
                platformToggle.checked = this.currentSettings.platformName.enabled;
            }
            
            // 消息通知开关
            const notifyToggle = document.getElementById('notify-toggle');
            if (notifyToggle && this.currentSettings.notification) {
                notifyToggle.checked = this.currentSettings.notification.enabled;
            }
            
            // AI功能开关
            const aiFunctionToggle = document.getElementById('ai-function-toggle');
            if (aiFunctionToggle && this.currentSettings.aiFunction) {
                aiFunctionToggle.checked = this.currentSettings.aiFunction.enabled;
            }
              // WebSocket 状态条开关
            const wsStatusBarToggle = document.getElementById('ws-status-bar-toggle');
            if (wsStatusBarToggle && this.currentSettings.wsStatusBar) {
                wsStatusBarToggle.checked = this.currentSettings.wsStatusBar.enabled;
            }
            
            // 动态壁纸开关
            const liveWallpaperToggle = document.getElementById('livewallpaper-toggle');
            if (liveWallpaperToggle && this.currentSettings.liveWallpaper) {
                liveWallpaperToggle.checked = this.currentSettings.liveWallpaper.enabled;
            }
            
            // 动态壁纸效果选择
            if (this.currentSettings.liveWallpaper && this.currentSettings.liveWallpaper.effect) {
                document.querySelectorAll('#livewallpaper-effects .preset-bg-item').forEach(item => {
                    item.classList.remove('preset-bg-selected');
                    if (item.dataset.effect === this.currentSettings.liveWallpaper.effect) {
                        item.classList.add('preset-bg-selected');
                    }
                });
            }
            
            // 视频壁纸设置
            const videoSettings = document.getElementById('video-wallpaper-settings');
            if (videoSettings && this.currentSettings.liveWallpaper) {
                videoSettings.style.display = this.currentSettings.liveWallpaper.effect === 'video' ? 'block' : 'none';
                
                // 视频信息
                const videoInfo = document.getElementById('video-wallpaper-info');
                const videoName = document.getElementById('video-wallpaper-name');
                if (videoInfo && videoName && this.currentSettings.liveWallpaper.videoName) {
                    videoName.textContent = this.currentSettings.liveWallpaper.videoName;
                    videoInfo.style.display = 'block';
                }
                
                // 循环播放
                const videoLoopToggle = document.getElementById('video-loop-toggle');
                if (videoLoopToggle) {
                    videoLoopToggle.checked = this.currentSettings.liveWallpaper.videoLoop !== false;
                }
                
                // 静音
                const videoMuteToggle = document.getElementById('video-mute-toggle');
                if (videoMuteToggle) {
                    videoMuteToggle.checked = this.currentSettings.liveWallpaper.videoMute !== false;
                }
            }
            
            // 更新预览
            this.updateBackgroundPreview();
            this.updateBubblePreviews();
            this.updateNotificationDescription();
            this.updateLiveWallpaperPreview();
        }

        updateColorPicker(pickerId, color) {
            // 防御性检查
            if (color === undefined || color === null) {
                console.warn(`${pickerId}: 颜色值为 ${color}，使用默认值`);
                
                // 根据选择器类型设置默认值
                if (pickerId.includes('background')) {
                    color = '#f0f0f0';
                } else if (pickerId.includes('agent')) {
                    color = '#effdde';
                } else if (pickerId.includes('customer')) {
                    color = '#ffffff';
                } else {
                    color = '#f0f0f0';
                }
            }
            
            // 转换为字符串
            const colorStr = String(color).trim();
            
            const picker = document.getElementById(pickerId);
            const sample = document.getElementById(pickerId.replace('-picker', '-sample'));
            const hex = document.getElementById(pickerId.replace('-picker', '-hex'));
            
            if (picker && sample && hex) {
                try {
                    picker.value = colorStr;
                    sample.style.backgroundColor = colorStr;
                    hex.textContent = colorStr.toUpperCase();
                } catch (error) {
                    console.error(`更新颜色选择器失败 ${pickerId}:`, error, colorStr);
                    
                    // 设置安全回退值
                    const fallbackColor = pickerId.includes('background') ? '#f0f0f0' : 
                                        pickerId.includes('agent') ? '#effdde' : '#ffffff';
                    
                    picker.value = fallbackColor;
                    sample.style.backgroundColor = fallbackColor;
                    hex.textContent = fallbackColor.toUpperCase();
                }
            } else {
                console.error(`找不到颜色选择器元素: ${pickerId}`);
            }
        }

        updateBackgroundPreview() {
            const preview = document.getElementById('background-preview');
            if (!preview) return;
            
            const defaultGradient = 'linear-gradient(135deg, #4a7bff 0%, #8ab4ff 100%)';
            
            if (!this.currentSettings.background.enabled) {
                preview.style.background = '#dbdbdb';
                preview.innerHTML = '<div style="color: #999; font-size: 14px;">背景已禁用</div>';
                return;
            }
            
            const { type, color, gradient, pattern, image } = this.currentSettings.background;
            
            switch(type) {
                case 'solid':
                    preview.style.background = color;
                    preview.innerHTML = '';
                    break;
                case 'gradient':
                    preview.style.background = gradient;
                    preview.innerHTML = '';
                    break;
                case 'pattern':
                    preview.style.background = defaultGradient;
                    preview.style.backgroundSize = 'cover';
                    preview.innerHTML = '';
                    break;
                case 'image':
                    if (image) {
                        preview.style.backgroundImage = `url(${image})`;
                        preview.style.backgroundSize = 'cover';
                        preview.style.backgroundPosition = 'center';
                        preview.innerHTML = '';
                    }
                    break;
            }
        }

        updateBubblePreviews() {
            const agentPreview = document.getElementById('agent-bubble-preview');
            const customerPreview = document.getElementById('customer-bubble-preview');
            
            if (agentPreview && customerPreview) {
                agentPreview.style.backgroundColor = this.currentSettings.bubble.agentColor;
                customerPreview.style.backgroundColor = this.currentSettings.bubble.customerColor;
                
                // 根据背景色调整文字颜色
                const agentTextColor = isLightColor(this.currentSettings.bubble.agentColor) ? '#000' : '#fff';
                const customerTextColor = isLightColor(this.currentSettings.bubble.customerColor) ? '#000' : '#fff';
                
                agentPreview.style.color = agentTextColor;
                customerPreview.style.color = customerTextColor;
            }
        }

        loadColorPickersFromSettings() {
            this.updateColorPicker('background-color-picker', this.currentSettings.background.color);
        }

        // 请求通知权限（支持PWA离线推送）
        requestNotificationPermission() {
            const notifyStatus = document.getElementById('notify-status');
            
            // 获取当前用户信息
            const userInfo = this.getCurrentUserInfo();
            if (!userInfo) {
                showToast('请先登录', 3000, 'error');
                const notifyToggle = document.getElementById('notify-toggle');
                if (notifyToggle) notifyToggle.checked = false;
                return;
            }
            
            if (!window.pushNotificationManager) {
                window.pushNotificationManager = new PushNotificationManager({
                    enableBrowserNotification: true,
                    enableCustomToast: true
                });
            }
            
            // 显示状态
            if (notifyStatus) {
                notifyStatus.style.display = 'block';
                notifyStatus.textContent = '正在注册推送服务...';
                notifyStatus.style.color = '#2196f3';
            }
            
            // 请求权限并订阅Push
            window.pushNotificationManager.requestPermission(userInfo.userId, userInfo.userType).then(result => {
                this.currentSettings.notification.granted = result.granted;
                this.currentSettings.notification.requested = true;
                this.saveSetting(STORAGE_KEYS.NOTIFICATION, this.currentSettings.notification);
                
                if (result.granted) {
                    showToast('通知权限已授予，离线推送已开启');
                    this.currentSettings.notification.enabled = true;
                    this.saveSetting(STORAGE_KEYS.NOTIFICATION, this.currentSettings.notification);
                    
                    const notifyToggle = document.getElementById('notify-toggle');
                    if (notifyToggle) notifyToggle.checked = true;
                    
                    if (notifyStatus) {
                        notifyStatus.textContent = '离线推送已开启，来消息时将收到通知';
                        notifyStatus.style.color = '#4caf50';
                    }
                } else {
                    const notifyToggle = document.getElementById('notify-toggle');
                    if (notifyToggle) notifyToggle.checked = false;
                    this.currentSettings.notification.enabled = false;
                    this.saveSetting(STORAGE_KEYS.NOTIFICATION, this.currentSettings.notification);
                    
                    if (notifyStatus) {
                        notifyStatus.style.color = '#f44336';
                        // 判断是否是PWA环境
                        var isStandalone = window.navigator.standalone === true || 
                                          window.matchMedia('(display-mode: standalone)').matches;
                        var isIOSDevice = /iPhone|iPad|iPod/.test(navigator.userAgent);
                        
                        if (isIOSDevice && isStandalone) {
                            notifyStatus.innerHTML = '⚠️ 通知权限未授予。<br>请尝试：<br>1. 删除主屏幕上的此应用<br>2. 用Safari重新打开网站<br>3. 再次"添加到主屏幕"<br>4. 重新打开并开启通知';
                        } else if (isIOSDevice) {
                            notifyStatus.innerHTML = '⚠️ 请先"添加到主屏幕"后再开启通知。<br>在Safari中点击分享按钮 → 添加到主屏幕';
                        } else {
                            notifyStatus.textContent = '通知权限被拒绝，请在浏览器设置中开启通知权限';
                        }
                    }
                    showToast(result.message || '通知权限未授予', 4000, 'error');
                }
            }).catch(err => {
                console.error('请求通知权限异常:', err);
                showToast('请求通知权限失败', 3000, 'error');
                const notifyToggle = document.getElementById('notify-toggle');
                if (notifyToggle) notifyToggle.checked = false;
                
                if (notifyStatus) {
                    notifyStatus.style.display = 'block';
                    notifyStatus.style.color = '#f44336';
                    notifyStatus.textContent = '注册推送服务失败，请检查网络连接后重试';
                }
            });
        }
        
        // 取消Push订阅
        unsubscribePushNotification() {
            if (window.pushNotificationManager) {
                const userInfo = this.getCurrentUserInfo();
                window.pushNotificationManager.unsubscribePush(userInfo ? userInfo.userId : null);
            }
        }
        
        // 获取当前用户信息
        getCurrentUserInfo() {
            // 尝试从sessionStorage获取
            const userData = sessionStorage.getItem('user_data');
            if (userData) {
                try {
                    const user = JSON.parse(userData);
                    let userType = 'agent';
                    return {
                        userId: user.username,
                        userType: userType
                    };
                } catch (e) {}
            }
            
            // 尝试从全局变量获取
            if (window.currentUser) {
                let userType = 'agent';
                return {
                    userId: window.currentUser.username,
                    userType: userType
                };
            }
            
            return null;
        }
        

        // 显示iOS通知设置指南
        showiOSNotificationGuide() {
            const guideHtml = `
                <div style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 1000;">
                    <div style="background: white; border-radius: 12px; padding: 20px; max-width: 300px; text-align: center;">
                        <h3 style="margin-bottom: 10px; color: #333;">iOS 通知设置</h3>
                        <p style="margin-bottom: 15px; color: #666; font-size: 14px; line-height: 1.4;">
                            iOS设备上，PWA应用的通知权限需要在系统设置中开启：
                        </p>
                        <ol style="text-align: left; margin-bottom: 20px; color: #666; font-size: 13px; line-height: 1.6;">
                            <li>打开iOS设备的"设置"应用</li>
                            <li>找到并点击"屏幕使用时间"</li>
                            <li>点击"查看所有活动"</li>
                            <li>找到此应用并点击</li>
                            <li>开启"允许通知"</li>
                        </ol>
                        <div style="display: flex; justify-content: space-between; gap: 10px;">
                            <button id="cancel-notify" style="padding: 8px 16px; background: #f0f0f0; border: none; border-radius: 6px; cursor: pointer;">
                                取消
                            </button>
                            <button id="enable-notify" style="padding: 8px 16px; background: #007AFF; color: white; border: none; border-radius: 6px; cursor: pointer;">
                                我已开启
                            </button>
                        </div>
                    </div>
                </div>
            `;
            
            const guideElement = document.createElement('div');
            guideElement.innerHTML = guideHtml;
            document.body.appendChild(guideElement);
            
            // 添加事件监听
            const cancelBtn = document.getElementById('cancel-notify');
            const enableBtn = document.getElementById('enable-notify');
            
            if (cancelBtn) {
                cancelBtn.addEventListener('click', () => {
                    const notifyToggle = document.getElementById('notify-toggle');
                    if (notifyToggle) {
                        notifyToggle.checked = false;
                    }
                    this.currentSettings.notification.enabled = false;
                    this.saveSetting(STORAGE_KEYS.NOTIFICATION, this.currentSettings.notification);
                    document.body.removeChild(guideElement);
                });
            }
            
            if (enableBtn) {
                enableBtn.addEventListener('click', () => {
                    this.currentSettings.notification.enabled = true;
                    this.currentSettings.notification.granted = true;
                    this.saveSetting(STORAGE_KEYS.NOTIFICATION, this.currentSettings.notification);
                    showToast('消息通知已开启');
                    document.body.removeChild(guideElement);
                });
            }
        }

        // 显示不支持通知的提示
        showNotificationNotSupported() {
            alert('您的设备或浏览器不支持通知功能。\n\n在iOS设备上，请将本应用"添加到主屏幕"以使用PWA功能。');
            
            // 关闭开关
            const notifyToggle = document.getElementById('notify-toggle');
            if (notifyToggle) {
                notifyToggle.checked = false;
            }
            this.currentSettings.notification.enabled = false;
            this.saveSetting(STORAGE_KEYS.NOTIFICATION, this.currentSettings.notification);
        }

        // 更新通知描述
        updateNotificationDescription() {
            const descriptionElement = document.getElementById('notify-description');
            if (!descriptionElement) return;
            
            // 检查安全上下文
            if (!window.isSecureContext) {
                descriptionElement.textContent = '离线推送需要HTTPS访问，当前为HTTP不支持';
                return;
            }
            
            const isStandalone = window.navigator.standalone || window.matchMedia('(display-mode: standalone)').matches;
            const isIOSDevice = /iPhone|iPad|iPod/.test(navigator.userAgent) || (navigator.userAgent.includes("Mac") && "ontouchend" in document);
            const pushSupported = 'serviceWorker' in navigator && 'PushManager' in window;
            
            if (isIOSDevice && isStandalone && pushSupported) {
                descriptionElement.textContent = '离线推送已就绪，来消息时将收到系统通知';
            } else if (isIOSDevice && !isStandalone) {
                descriptionElement.textContent = '请在Safari中点击分享按钮，选择"添加到主屏幕"后开启离线推送';
            } else if (pushSupported) {
                descriptionElement.textContent = '开启后支持离线消息推送';
            } else {
                descriptionElement.textContent = '当前浏览器不支持离线推送功能';
            }
        }

        // 通知聊天页面
        notifyChatPage(type) {
            try {
                // 方法1: 通过localStorage触发storage事件
                localStorage.setItem('chat_settings_trigger', Date.now().toString());
                
                // 方法2: 发送特定类型的storage事件
                window.dispatchEvent(new StorageEvent('storage', {
                    key: type === 'platform_name_updated' ? STORAGE_KEYS.PLATFORM_NAME : STORAGE_KEYS.NOTIFICATION
                }));
                
                console.log(`已通知聊天页面: ${type}`);
            } catch (error) {
                console.error('通知聊天页面失败:', error);
            }
        }

        // 通知AI功能变化
        notifyAIFunctionChanged(enabled) {
            try {
                // 方法1: 通过storage事件通知
                window.dispatchEvent(new StorageEvent('storage', {
                    key: STORAGE_KEYS.AI_FUNCTION
                }));
                
                // 方法2: 发送自定义事件
                window.dispatchEvent(new CustomEvent('chatAIFunctionChanged', {
                    detail: { enabled: enabled }
                }));
                
                console.log('已通知AI功能状态变更:', enabled);
            } catch (error) {
                console.error('通知AI功能变更失败:', error);
            }
        }
        
       
        notifyWsStatusBarChanged(enabled) {
            try {
                // 方法 1: 发送自定义事件（同窗口/iframe）
                window.dispatchEvent(new CustomEvent('chatWsStatusBarChanged', {
                    detail: { enabled: enabled }
                }));

                // 方法 2: 通过 postMessage 通知父窗口（如果在 iframe 中）
                if (window.parent && window.parent !== window) {
                    window.parent.postMessage({
                        type: 'WS_STATUS_BAR_CHANGED',
                        enabled: enabled
                    }, '*');
                }

                // 方法 3: 直接操作 localStorage 触发 storage 事件（其他标签页）
                // 先保存当前值，然后重新设置以触发 storage 事件
                const current = localStorage.getItem(STORAGE_KEYS.WS_STATUS_BAR);
                localStorage.setItem(STORAGE_KEYS.WS_STATUS_BAR, current || JSON.stringify({enabled}));

                console.log('📡 已通知 WebSocket 状态条变更:', enabled ? '显示' : '隐藏');
            } catch (error) {
                console.error('通知 WebSocket 状态条变更失败:', error);
            }
        }

        // 获取效果名称
        getEffectName(effect) {
            const names = {
                'particles': '粒子',
                'waves': '波浪',
                'starry': '星空',
                'bubbles': '气泡',
                'matrix': '代码雨',
                'video': '视频'
            };
            return names[effect] || effect;
        }

        // 更新动态壁纸预览
        updateLiveWallpaperPreview() {
            const preview = document.getElementById('livewallpaper-preview');
            const canvas = document.getElementById('livewallpaper-preview-canvas');
            if (!preview || !canvas) return;

            // 停止之前的动画
            if (this.liveWallpaperAnimationId) {
                cancelAnimationFrame(this.liveWallpaperAnimationId);
                this.liveWallpaperAnimationId = null;
            }

            if (!this.currentSettings.liveWallpaper.enabled) {
                preview.style.background = '#dbdbdb';
                const ctx = canvas.getContext('2d');
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                ctx.fillStyle = '#999';
                ctx.font = '14px sans-serif';
                ctx.textAlign = 'center';
                ctx.fillText('动态壁纸已禁用', canvas.width / 2, canvas.height / 2);
                return;
            }

            const effect = this.currentSettings.liveWallpaper.effect || 'particles';
            
            // 视频效果预览
            if (effect === 'video') {
                this.updateVideoPreview(preview, canvas);
                return;
            }
            
            this.startLiveWallpaperEffect(canvas, effect, true);
        }
        
        // 视频预览
        updateVideoPreview(preview, canvas) {
            // 清除之前的视频元素
            const oldVideo = preview.querySelector('video');
            if (oldVideo) {
                oldVideo.pause();
                oldVideo.remove();
            }
            
            canvas.style.display = 'none';
            
            if (this.currentSettings.liveWallpaper.videoData) {
                const video = document.createElement('video');
                video.src = this.currentSettings.liveWallpaper.videoData;
                video.style.cssText = 'width: 100%; height: 100%; object-fit: cover; border-radius: 0.75rem;';
                video.muted = true;
                video.loop = true;
                video.autoplay = true;
                video.playsInline = true;
                preview.appendChild(video);
            } else {
                canvas.style.display = 'block';
                const ctx = canvas.getContext('2d');
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                ctx.fillStyle = '#2c3e50';
                ctx.fillRect(0, 0, canvas.width, canvas.height);
                ctx.fillStyle = '#fff';
                ctx.font = '14px sans-serif';
                ctx.textAlign = 'center';
                ctx.fillText('请选择视频文件', canvas.width / 2, canvas.height / 2);
            }
        }

        // 启动动态壁纸效果
        startLiveWallpaperEffect(canvas, effect, isPreview = false) {
            const ctx = canvas.getContext('2d');
            canvas.width = canvas.offsetWidth || 300;
            canvas.height = canvas.offsetHeight || 100;

            // 停止之前的动画
            if (this.liveWallpaperAnimationId) {
                cancelAnimationFrame(this.liveWallpaperAnimationId);
            }

            switch(effect) {
                case 'particles':
                    this.liveWallpaperAnimationId = this.startParticlesEffect(ctx, canvas.width, canvas.height);
                    break;
                case 'waves':
                    this.liveWallpaperAnimationId = this.startWavesEffect(ctx, canvas.width, canvas.height);
                    break;
                case 'starry':
                    this.liveWallpaperAnimationId = this.startStarryEffect(ctx, canvas.width, canvas.height);
                    break;
                case 'bubbles':
                    this.liveWallpaperAnimationId = this.startBubblesEffect(ctx, canvas.width, canvas.height);
                    break;
                case 'matrix':
                    this.liveWallpaperAnimationId = this.startMatrixEffect(ctx, canvas.width, canvas.height);
                    break;
            }
        }

        // 粒子效果
        startParticlesEffect(ctx, width, height) {
            const particles = [];
            const particleCount = 50;

            for (let i = 0; i < particleCount; i++) {
                particles.push({
                    x: Math.random() * width,
                    y: Math.random() * height,
                    vx: (Math.random() - 0.5) * 2,
                    vy: (Math.random() - 0.5) * 2,
                    size: Math.random() * 3 + 1,
                    opacity: Math.random() * 0.5 + 0.2
                });
            }

            const animate = () => {
                ctx.fillStyle = 'rgba(15, 12, 41, 0.1)';
                ctx.fillRect(0, 0, width, height);

                particles.forEach(p => {
                    p.x += p.vx;
                    p.y += p.vy;

                    if (p.x < 0 || p.x > width) p.vx *= -1;
                    if (p.y < 0 || p.y > height) p.vy *= -1;

                    ctx.beginPath();
                    ctx.arc(p.x, p.y, p.size, 0, Math.PI * 2);
                    ctx.fillStyle = `rgba(100, 200, 255, ${p.opacity})`;
                    ctx.fill();
                });

                // 绘制连线
                particles.forEach((p1, i) => {
                    particles.slice(i + 1).forEach(p2 => {
                        const dx = p1.x - p2.x;
                        const dy = p1.y - p2.y;
                        const dist = Math.sqrt(dx * dx + dy * dy);
                        if (dist < 80) {
                            ctx.beginPath();
                            ctx.moveTo(p1.x, p1.y);
                            ctx.lineTo(p2.x, p2.y);
                            ctx.strokeStyle = `rgba(100, 200, 255, ${0.2 * (1 - dist / 80)})`;
                            ctx.lineWidth = 0.5;
                            ctx.stroke();
                        }
                    });
                });

                this.liveWallpaperAnimationId = requestAnimationFrame(animate);
            };

            animate();
            return this.liveWallpaperAnimationId;
        }

        // 波浪效果
        startWavesEffect(ctx, width, height) {
            let time = 0;

            const animate = () => {
                ctx.fillStyle = 'rgba(26, 41, 128, 0.1)';
                ctx.fillRect(0, 0, width, height);

                for (let i = 0; i < 3; i++) {
                    ctx.beginPath();
                    ctx.moveTo(0, height / 2);

                    for (let x = 0; x < width; x++) {
                        const y = height / 2 + 
                            Math.sin(x * 0.02 + time + i * 2) * 20 +
                            Math.sin(x * 0.01 + time * 0.5 + i) * 10;
                        ctx.lineTo(x, y);
                    }

                    ctx.lineTo(width, height);
                    ctx.lineTo(0, height);
                    ctx.closePath();
                    ctx.fillStyle = `rgba(38, 208, 206, ${0.1 + i * 0.05})`;
                    ctx.fill();
                }

                time += 0.05;
                this.liveWallpaperAnimationId = requestAnimationFrame(animate);
            };

            animate();
            return this.liveWallpaperAnimationId;
        }

        // 星空效果
        startStarryEffect(ctx, width, height) {
            const stars = [];
            const starCount = 80;

            for (let i = 0; i < starCount; i++) {
                stars.push({
                    x: Math.random() * width,
                    y: Math.random() * height,
                    size: Math.random() * 2 + 0.5,
                    twinkleSpeed: Math.random() * 0.05 + 0.01,
                    twinkleOffset: Math.random() * Math.PI * 2
                });
            }

            let time = 0;

            const animate = () => {
                ctx.fillStyle = 'rgba(0, 0, 0, 0.2)';
                ctx.fillRect(0, 0, width, height);

                stars.forEach(star => {
                    const opacity = 0.5 + 0.5 * Math.sin(time * star.twinkleSpeed + star.twinkleOffset);
                    ctx.beginPath();
                    ctx.arc(star.x, star.y, star.size, 0, Math.PI * 2);
                    ctx.fillStyle = `rgba(255, 255, 255, ${opacity})`;
                    ctx.fill();
                });

                time += 1;
                this.liveWallpaperAnimationId = requestAnimationFrame(animate);
            };

            animate();
            return this.liveWallpaperAnimationId;
        }

        // 气泡效果
        startBubblesEffect(ctx, width, height) {
            const bubbles = [];
            const bubbleCount = 20;

            for (let i = 0; i < bubbleCount; i++) {
                bubbles.push({
                    x: Math.random() * width,
                    y: height + Math.random() * 100,
                    size: Math.random() * 15 + 5,
                    speed: Math.random() * 1 + 0.5,
                    wobble: Math.random() * Math.PI * 2,
                    wobbleSpeed: Math.random() * 0.05 + 0.02
                });
            }

            const animate = () => {
                ctx.fillStyle = 'rgba(33, 147, 176, 0.1)';
                ctx.fillRect(0, 0, width, height);

                bubbles.forEach(bubble => {
                    bubble.y -= bubble.speed;
                    bubble.wobble += bubble.wobbleSpeed;
                    const x = bubble.x + Math.sin(bubble.wobble) * 20;

                    if (bubble.y < -bubble.size) {
                        bubble.y = height + bubble.size;
                        bubble.x = Math.random() * width;
                    }

                    ctx.beginPath();
                    ctx.arc(x, bubble.y, bubble.size, 0, Math.PI * 2);
                    ctx.fillStyle = 'rgba(109, 213, 237, 0.3)';
                    ctx.fill();
                    ctx.strokeStyle = 'rgba(109, 213, 237, 0.5)';
                    ctx.lineWidth = 1;
                    ctx.stroke();
                });

                this.liveWallpaperAnimationId = requestAnimationFrame(animate);
            };

            animate();
            return this.liveWallpaperAnimationId;
        }

        // 代码雨效果
        startMatrixEffect(ctx, width, height) {
            const fontSize = 14;
            const columns = Math.floor(width / fontSize);
            const drops = [];
            const chars = '01アイウエオカキクケコサシスセソタチツテトナニヌネノハヒフヘホマミムメモヤユヨラリルレロワヲン';

            for (let i = 0; i < columns; i++) {
                drops[i] = Math.random() * -100;
            }

            const animate = () => {
                ctx.fillStyle = 'rgba(0, 0, 0, 0.05)';
                ctx.fillRect(0, 0, width, height);

                ctx.font = fontSize + 'px monospace';

                for (let i = 0; i < drops.length; i++) {
                    const char = chars[Math.floor(Math.random() * chars.length)];
                    const x = i * fontSize;
                    const y = drops[i] * fontSize;

                    ctx.fillStyle = '#0f0';
                    ctx.fillText(char, x, y);

                    if (y > height && Math.random() > 0.975) {
                        drops[i] = 0;
                    }
                    drops[i]++;
                }

                this.liveWallpaperAnimationId = requestAnimationFrame(animate);
            };

            animate();
            return this.liveWallpaperAnimationId;
        }

        // 通知动态壁纸变更
        notifyLiveWallpaperChanged() {
            try {
                // 方法1: 发送自定义事件
                window.dispatchEvent(new CustomEvent('chatLiveWallpaperChanged', {
                    detail: { 
                        enabled: this.currentSettings.liveWallpaper.enabled,
                        effect: this.currentSettings.liveWallpaper.effect
                    }
                }));

                // 方法2: 通过 postMessage 通知父窗口
                if (window.parent && window.parent !== window) {
                    window.parent.postMessage({
                        type: 'LIVE_WALLPAPER_CHANGED',
                        enabled: this.currentSettings.liveWallpaper.enabled,
                        effect: this.currentSettings.liveWallpaper.effect
                    }, '*');
                }

                // 方法3: 触发storage事件（通过先移除再设置来确保事件触发）
                const current = localStorage.getItem(STORAGE_KEYS.LIVE_WALLPAPER);
                if (current) {
                    localStorage.removeItem(STORAGE_KEYS.LIVE_WALLPAPER);
                    localStorage.setItem(STORAGE_KEYS.LIVE_WALLPAPER, current);
                }

                console.log('🎨 已通知动态壁纸变更:', this.currentSettings.liveWallpaper.enabled ? '开启' : '关闭', this.currentSettings.liveWallpaper.effect);
            } catch (error) {
                console.error('通知动态壁纸变更失败:', error);
            }
        }

        async loadVisitorToken() {
            const tokenDisplay = document.getElementById('visitorTokenDisplay');
            if (!tokenDisplay) return;

            try {
                const response = await fetch('/api/sign/check?action=get_visitor_token', {
                    method: 'POST'
                });
                const data = await response.json();

                if (data.code === 1 && data.data && data.data.visitor_token) {
                    tokenDisplay.textContent = data.data.visitor_token;
                } else {
                    tokenDisplay.textContent = '未生成';
                }
            } catch (error) {
                tokenDisplay.textContent = '加载失败';
            }
        }
        
        
    }

    // 初始化设置管理器
    let settingsManager;
    document.addEventListener('DOMContentLoaded', () => {
        // 确保localStorage中的数据格式正确
        try {
            // 检查并修复现有的设置
            const bgSettings = localStorage.getItem(STORAGE_KEYS.BACKGROUND);
            if (bgSettings) {
                const parsed = JSON.parse(bgSettings);
                if (!parsed.color) {
                    parsed.color = '#000000';
                    localStorage.setItem(STORAGE_KEYS.BACKGROUND, JSON.stringify(parsed));
                }
            }
            
            const bubbleSettings = localStorage.getItem(STORAGE_KEYS.BUBBLE);
            if (bubbleSettings) {
                const parsed = JSON.parse(bubbleSettings);
                if (!parsed.agentColor) {
                    parsed.agentColor = '#e0ffc7';
                }
                if (!parsed.customerColor) {
                    parsed.customerColor = '#ffffff';
                }
                localStorage.setItem(STORAGE_KEYS.BUBBLE, JSON.stringify(parsed));
            }
            
            const notificationSettings = localStorage.getItem(STORAGE_KEYS.NOTIFICATION);
            if (notificationSettings) {
                const parsed = JSON.parse(notificationSettings);
                if (!parsed.deviceType) {
                    parsed.deviceType = detectDeviceType();
                    localStorage.setItem(STORAGE_KEYS.NOTIFICATION, JSON.stringify(parsed));
                }
            }
            
            const aiFunctionSettings = localStorage.getItem(STORAGE_KEYS.AI_FUNCTION);
            if (aiFunctionSettings) {
                const parsed = JSON.parse(aiFunctionSettings);
                if (typeof parsed.enabled === 'undefined') {
                    parsed.enabled = true;
                    localStorage.setItem(STORAGE_KEYS.AI_FUNCTION, JSON.stringify(parsed));
                }
            }
        } catch (error) {
            console.error('修复localStorage设置失败:', error);
        }
        
        settingsManager = new SettingsManager();
    });
</script>

<!-- 引入推送通知管理器 -->
<script src="/js/push-notification.js"></script>
<script>
// 从PHP Session输出用户信息，确保iOS PWA也能获取到
window.currentUser = window.currentUser || {
    username: '<?php echo addslashes($_SESSION["username"] ?? ""); ?>',
    role: '<?php echo addslashes($_SESSION["user_role"] ?? ""); ?>',
    user_id: '<?php echo addslashes($_SESSION["user_id"] ?? ""); ?>'
};
if (!sessionStorage.getItem('user_data') && window.currentUser.username) {
    sessionStorage.setItem('user_data', JSON.stringify(window.currentUser));
}
</script>
<script>
// 页面加载后自动注册Service Worker（提前注册，避免权限请求时SW未就绪）
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/sw.js', { scope: '/' }).then(function(reg) {
        console.log('[设置页] Service Worker 已注册, 状态:', reg.active ? 'active' : (reg.waiting ? 'waiting' : (reg.installing ? 'installing' : 'unknown')));
    }).catch(function(err) {
        console.error('[设置页] Service Worker 注册失败:', err);
    });
}
</script>
</body>
</html>