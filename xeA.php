<?php
if (!defined('FROM_ROUTER')) {
    http_response_code(403);
    exit('喜乐科技@x60898');
}
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/session_parser.php';

// 记录访问量
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/chat_web.php';
recordVisit();

// 验证XEDATA令牌（仅检查有效性和过期时间）
function verifyXEDataToken($sessionId, $xedataToken) {
    $db = getDB();
    if (!$db) return false;
    
    $currentTime = date('Y-m-d H:i:s');
    
    $stmt = $db->prepare("SELECT * FROM `XE-SKDJWKSNCDATA` 
                         WHERE session_id = ? AND xedata_token = ? 
                         AND expires_at > ?");
    $stmt->bind_param("sss", $sessionId, $xedataToken, $currentTime);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->num_rows > 0;
}

// 获取参数
$sessionId = isset($_GET['id']) ? $_GET['id'] : '';
$xedataToken = isset($_GET['XEDATA']) ? $_GET['XEDATA'] : '';

// 验证
if (empty($sessionId) || empty($xedataToken)) {
    die('非法访问：缺少验证参数');
}

if (!verifyXEDataToken($sessionId, $xedataToken)) {
    die('非法访问：验证失败或链接已过期');
}

$sessionId = $_GET['id'] ?? 'aaaccazzz-ptestadmins';
$parsedSession = SessionParser::parseSessionId($sessionId);
$customerName = $parsedSession['customer'];
$agentAccount = $parsedSession['agent'];
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>闲鱼官方客服</title>
    <link rel="shortcut icon" href="/assets/img/goofishicon.webp" type="image/x-icon">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Noto+Sans+SC:wght@400;500;700&display=swap">
    <script src="/assets/jquery.min.js"></script>
    <style>
.xile-loading-container { 
    display: flex; 
    flex-direction: column; 
    align-items: center; 
    justify-content: center; 
    height: 100vh; 
    background: #f5f5f5;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    z-index: 99999;
    transition: opacity 0.3s ease;
} 

.xile-loading-container.hidden {
    opacity: 0;
    pointer-events: none;
}

.xile-loading-spinner { 
    width: 40px; 
    height: 40px; 
    border: 3px solid #ddd; 
    border-top-color: #39f; 
    border-radius: 50%; 
    animation: xile-spin-9c580cac 1s linear infinite 
} 

@keyframes xile-spin-9c580cac { 
    to { 
        transform: rotate(1turn) 
    } 
} 

.xile-loading-text { 
    margin-top: 16px; 
    font-size: 14px; 
    color: #666 
}

        /* 基础重置和全局样式 */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: 'Noto Sans SC', sans-serif;
            background-color: #e5e7eb;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            overflow: auto;
            overscroll-behavior: contain;
        }
        
        /* 主容器 - 自适应核心 */
        .main-container {
            width: 100%;
            height: auto;
            min-height: 600px;
            max-height: 896px;
            background-color: #f7f8fa;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            transition: filter 0.3s ease;
        }
        
        /* 头部样式 */
        .header {
            background: linear-gradient(115deg, #d55e31, #efad63);
            padding:8px 1px 8px 15px;
            display: flex;
            align-items: center;
            flex-shrink: 0;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }
        
        .avatar-container {
            position: relative;
        }
        
        .avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .avatar img {
            border-radius: 50%;
            width: 44px;
            height: 44px;
        }
        
        .online-indicator {
            position: absolute;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            bottom: -4px;
            right: -4px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .online-dot {
            width: 12px;
            height: 12px;
            background-color: #00ff00;
            border-radius: 50%;
            animation: breathe 2s infinite alternate ease-in-out;
        }
        
        /* 呼吸灯动画定义 */
        @keyframes breathe {
            0% {
                opacity: 0.3;
                transform: scale(0.95);
                box-shadow: 0 0 10px rgba(0, 255, 0, 0.5);
            }
            50% {
                opacity: 1;
                transform: scale(1);
                box-shadow: 0 0 25px rgba(0, 255, 0, 1);
            }
            100% {
                opacity: 0.3;
                transform: scale(0.95);
                box-shadow: 0 0 10px rgba(0, 255, 0, 0.5);
            }
        }
        
        .header-info {
            margin-left: 12px;
            color: white;
        }
        
        .vip-badge {
            background: linear-gradient(to right, #5a3b1a, #3c2811);
            font-size: 12px;
            font-weight: 700;
            padding: 2px 4px;
            border-radius: 2px;
            margin-right: 4px;
        }
        
        .header-title {
            font-size: 15px;
            font-weight: 450;
            display: flex;
            align-items: center;
        }
        
        .header-subtitle {
            font-size: 12px;
            opacity: 0.9;
            margin-top: 4px;
        }
        
        /* 公告栏样式 - 修复滚动问题 */
        .announcement {
            background-color: #fff7eb;
            padding: 8px 12px;
            display: flex;
            align-items: center;
            font-size: 12px;
            color: #FF0000;
            flex-shrink: 0;
            overflow: hidden;
            position: relative;
        }
        
        .announcement-icon {
            font-size: 16px;
            flex-shrink: 0;
            z-index: 2;
        }
        
        .announcement-content {
            flex: 1;
            margin-left: 6px;
            overflow: hidden;
            position: relative;
            height: 20px;
        }
        
        .announcement-text {
            font-weight: 500;
            white-space: nowrap;
            position: absolute;
            animation: scroll-text 15s linear 2s infinite;
            animation-play-state: running;
        }
        
        /* 修复：优化滚动动画 */
        @keyframes scroll-text {
            0% {
                transform: translateX(100%);
            }
            100% {
                transform: translateX(-100%);
            }
        }
        
        /* 修复：添加鼠标悬停暂停效果 */
        .announcement-content:hover .announcement-text {
            animation-play-state: paused;
        }
        
        .announcement-tag {
            background-color: #e5e7eb;
            color: #6b7280;
            border-radius: 4px;
            padding: 2px 6px;
            font-size: 10px;
            font-weight: 700;
            margin-left: 8px;
            flex-shrink: 0;
        }
        
        .close-icon {
            margin-left: auto;
            color: #9ca3af;
            font-size: 16px;
            flex-shrink: 0;
            z-index: 2;
        }
        
        /* 聊天区域样式 */
        .chat-area {
            flex: 1;
            padding: 12px;
            display: flex;
            flex-direction: column;
            gap: 24px;
            overflow-y: auto;
        }
        
        .system-message {
            margin-top:5px;
            text-align: center;
        }
        
        .system-text {
            background-color: #e5e7eb;
            color: #6b7280;
            font-size: 12px;
            padding: 4px 12px;
            border-radius: 9999px;
        }
        
        .welcome-card {
            background-color: white;
            border-radius: 8px;
            padding: 8px 15px;
            text-align: center;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
            width: 50%;
            margin: 16px auto;
        }
        
        .welcome-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background-color: #ffde33;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
        }
        
        .welcome-avatar img {
            border-radius: 50%;
            width: 40px;
            height: 40px;
        }
        
        .welcome-title {
            margin-top: 12px;
            font-size: 14px;
            font-weight: 600;
            color: #1f2937;
        }
        
        .welcome-subtitle {
            font-size: 12px;
            color: #9ca3af;
            margin-top: 4px;
        }
        
        /* 消息样式 */
        .message {
            display: flex;
            align-items: flex-start;
        }
        
        .message-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        
        .message-avatar img {
            border-radius: 50%;
            width: 40px;
            height: 40px;
        }
        
        .message-content {
            margin-left: 8px;
            flex-grow: 1;
        }
        
        .message-info {
            font-size: 12px;
            color: #9ca3af;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
        }
        
        .official-badge {
            background-color: #ffb246;
            color: white;
            font-size: 10px;
            font-weight: 600;
            padding: 2px 4px;
            border-radius: 2px;
            margin-right: 6px;
        }
        
        .message-bubble {
            background-color: white;
            border-radius: 8px;
            padding: 12px;
            font-size: 14px;
            color: #1f2937;
            line-height: 1.625;
            display: inline-block;
            max-width: 350px;
            word-wrap: break-word;
    overflow-wrap: break-word;

        }
        
        .highlight-text {
            color: #FF0000;
        }
        
        /*  outgoing message */
        .outgoing-message {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
        }
        
        .outgoing-info {
            font-size: 12px;
            color: #9ca3af;
            margin-bottom: 6px;
        }
        
        .outgoing-bubble {
            background-color: #3b82f6;
            color: white;
            border-radius: 8px;
            padding: 6px 14px;
            font-size: 14px;
            max-width: 350px;
        }
        
        .read-receipt {
            font-size: 12px;
            color: #9ca3af;
            margin-top: 4px;
        }
        
        /* 底部输入栏样式 */
        .footer {
            background-color: white;
            padding: 8px 12px 12px 12px;
            border-top: 1px solid #f3f4f6;
            flex-shrink: 0;
        }
        
        .input-container {
            display: flex;
            align-items: center;
        }
        
        .message-input {
            flex: 1;
            background-color: transparent;
            font-size: 14px;
            border: none;
            outline: none;
        }
        
        .message-input::placeholder {
            color: #9ca3af;
        }
        
        .send-button {
            background-color: #f3f4f6;
            color: #9ca3af;
            font-size: 14px;
            font-weight: 500;
            border-radius: 4px;
            padding: 6px 16px;
            margin-left: 8px;
            border: none;
            cursor: pointer;
        }
        
        .footer-icons {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 12px;
        }
        
        .icon-group {
            display: flex;
            align-items: center;
            gap: 16px;
            color: #6b7280;
        }
        
        .icon {
            font-size: 24px;
        }
        
        .footer-logo {
            width: 48px;
            height: 48px;
        }
        
        /* 响应式设计 - 媒体查询 */
        @media (max-width: 480px) {
            
            .main-container {
                max-height: none;
                height: 100vh;
                border-radius: 0;
            }
            
           
            
            .chat-area {
                gap: 20px;
            }
        }
        
        /* 滚动条样式 */
        .chat-area::-webkit-scrollbar {
            width: 4px;
        }
        
        .chat-area::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        
        .chat-area::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 4px;
        }
        
        .chat-area::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }

        /* 新增样式 - 适配功能 */
        .send-button:enabled {
            background-color: #ffe60d;
            color: black;
        }
        
        .send-button:disabled {
            background-color: #f3f4f6;
            color: #9ca3af;
            cursor: not-allowed;
        }
        
        .upload-button {
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: #666;
        }
        
        .upload-button:hover {
            color: #f86442;
        }
        
        /* 图片预览模态框 */
        .image-preview-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
            z-index: 10000;
            justify-content: center;
            align-items: center;
        }
        
        .image-preview-content {
            max-width: 90%;
            max-height: 90%;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        }
        
        .image-preview-modal.active {
            display: flex;
        }
        
        .message-image {
            max-width: 200px;
            max-height: 200px;
            border-radius: 8px;
            cursor: pointer;
            display: block;
        }
        
        /* 加载动画 */
        .loading-spinner {
            position: relative;
            top: -10px;
            left: -4px;
        }
        
        .loading-spinner > div {
            background-color: #F6A046;
            width: 4px;
            height: 35px;
            border-radius: 2px;
            margin: 2px;
            animation-fill-mode: both;
            position: absolute;
            width: 5px;
            height: 15px;
        }
        
        @keyframes loading-animation {
            50% {
                opacity: 0.3;
            }
            100% {
                opacity: 1;
            }
        }
        
        .loading-spinner > div:nth-child(1) {
            top: 20px;
            left: 0;
            animation: loading-animation 1.2s -0.84s infinite ease-in-out;
        }
        
        .loading-spinner > div:nth-child(2) {
            top: 13.63636px;
            left: 13.63636px;
            transform: rotate(-45deg);
            animation: loading-animation 1.2s -0.72s infinite ease-in-out;
        }
        
        .loading-spinner > div:nth-child(3) {
            top: 0;
            left: 20px;
            transform: rotate(90deg);
            animation: loading-animation 1.2s -0.6s infinite ease-in-out;
        }
        
        .loading-spinner > div:nth-child(4) {
            top: -13.63636px;
            left: 13.63636px;
            transform: rotate(45deg);
            animation: loading-animation 1.2s -0.48s infinite ease-in-out;
        }
        
        .loading-spinner > div:nth-child(5) {
            top: -20px;
            left: 0;
            animation: loading-animation 1.2s -0.36s infinite ease-in-out;
        }
        
        .loading-spinner > div:nth-child(6) {
            top: -13.63636px;
            left: -13.63636px;
            transform: rotate(-45deg);
            animation: loading-animation 1.2s -0.24s infinite ease-in-out;
        }
        
        .loading-spinner > div:nth-child(7) {
            top: 0;
            left: -20px;
            transform: rotate(90deg);
            animation: loading-animation 1.2s -0.12s infinite ease-in-out;
        }
        
        .loading-spinner > div:nth-child(8) {
            top: 13.63636px;
            left: -13.63636px;
            transform: rotate(45deg);
            animation: loading-animation 1.2s 0s infinite ease-in-out;
        }

#orderFormModal {
    display: none;
    position: fixed;
    z-index: 10001;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.6);
}

#orderFormModal .modal-content {
    background-color: #fff;
    position: fixed; /* 改为fixed定位 */
    top: 50%; /* 垂直居中 */
    left: 50%; /* 水平居中 */
    transform: translate(-50%, -50%); /* 自身宽高的一半偏移 */
    padding: 0;
    border-radius: 12px;
    width: 90%;
    max-width: 400px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
    /* 移除margin属性 */
}

        .modal-header {
            background: linear-gradient(115deg, #d55e31, #efad63);
            color: white;
            padding: 20px;
            border-radius: 12px 12px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
        }

        .close-modal {
            color: white;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            line-height: 1;
        }

        .close-modal:hover {
            opacity: 0.8;
        }

        #orderInfoForm {
            padding: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
            font-size: 14px;
        }

        .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus {
            outline: none;
            border-color: #f86442;
            box-shadow: 0 0 0 3px rgba(248, 100, 66, 0.1);
        }

        .form-group input:invalid {
            border-color: #ef4444;
        }

        .form-actions {
            text-align: center;
            margin-top: 10px;
        }

        #confirmOrderBtn {
            background: linear-gradient(115deg, #d55e31, #efad63);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s ease;
            width: 100%;
        }

        #confirmOrderBtn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(213, 94, 49, 0.3);
        }

        #confirmOrderBtn:active {
            transform: translateY(0);
        }

        /* 动画效果 */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideDown {
            from { 
                transform: translateY(-50px);
                opacity: 0;
            }
            to { 
                transform: translateY(0);
                opacity: 1;
            }
        }

        /* 输入验证样式 */
        .form-group input:valid {
            border-color: #10b981;
        }

        .error-message {
            color: #ef4444;
            font-size: 12px;
            margin-top: 5px;
            display: none;
        }
        
        /* 订单信息消息样式 */
        .order-info-bubble {
            background-color:  #3b82f6;
            padding: 12px;
            font-size: 14px;
            line-height: 1.5;
            max-width: 350px;
            border-radius: 8px;
        }
        
        .order-info-title {
            font-weight: 600;
            color: #1e40af;
            margin-bottom: 8px;
            font-size: 15px;
        }
        
        .order-info-item {
            margin-bottom: 4px;
            display: flex;
            justify-content: space-between;
        }
        
        .order-info-label {
            color: #6b7280;
            font-weight: 500;
        }
        
        .order-info-value {
            color: #1f2937;
            font-weight: 600;
        }
        /* 隐藏所有滚动条但保留滚动功能 */
body, .wrapper, .main-container, .chat-area, .chat-messages,
.footer-buttons, .node-list-container {
    /* 针对Webkit浏览器（Chrome, Safari, Edge） */
    &::-webkit-scrollbar {
        display: none;
        width: 0;
    }
    
    /* 标准属性 */
    scrollbar-width: none; /* Firefox */
    -ms-overflow-style: none; /* IE/Edge */
}
    </style>
    	<style>
		/* XECARD 自定义卡片样式 */
		.xecard {
		    background: linear-gradient(145deg, #ffffff, #f8fafc);
		    border-radius: 12px;
		    padding: 16px;
		    max-width: 320px;
		    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
		    border: 1px solid #e2e8f0;
		    overflow: hidden;
		    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
		}
		
		.xecard .xecard-header {
		    display: flex;
		    align-items: center;
		    margin-bottom: 12px;
		    padding-bottom: 12px;
		    border-bottom: 1px dashed #cbd5e1;
		}
		
		.xecard .xecard-icon {
		    width: 40px;
		    height: 40px;
		    background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
		    border-radius: 8px;
		    display: flex;
		    align-items: center;
		    justify-content: center;
		    color: white;
		    font-size: 18px;
		    margin-right: 12px;
		    flex-shrink: 0;
		}
		
		.xecard .xecard-title {
		    font-size: 15px;
		    font-weight: 600;
		    color: #1e293b;
		    display: block;
		}
		
		.xecard .xecard-subtitle {
		    font-size: 12px;
		    color: #64748b;
		    display: block;
		}
		
		.xecard .xecard-body {
		    margin-bottom: 12px;
		    font-size: 13px;
		    color: #475569;
		    line-height: 1.6;
		    word-break: break-word;
		}
		
		.xecard .xecard-actions {
		    display: flex;
		    gap: 8px;
		}
		
		.xecard .xecard-button {
		    flex: 1;
		    padding: 10px;
		    background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
		    color: white;
		    border: none;
		    border-radius: 8px;
		    font-size: 13px;
		    font-weight: 500;
		    cursor: pointer;
		    transition: all 0.2s;
		}
		
		.xecard .xecard-button:hover {
		    transform: translateY(-1px);
		    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
		}
		
		.xecard .xecard-button.secondary {
		    background: white;
		    color: #64748b;
		    border: 1px solid #e2e8f0;
		}
		
		.xecard .xecard-button.secondary:hover {
		    background: #f8fafc;
		    border-color: #cbd5e1;
		}
		
		/* message-card 卡片样式（与 wechat.php 一致） */
		.message-card {
		    background: #ffffff;
		    border-radius: 8px;
		    padding: 2px;
		    max-width: 280px;
		}

		.message-card__header {
		    justify-content: center; 
		    margin-bottom: 8px;
		    display: flex;
		    align-items: center;
		    gap: 6px;
		}

		.message-card__title {
		    font-weight: 600;
		    color: #1890ff;
		    font-size: 14px;
		    display: flex;
		    align-items: center;
		    gap: 4px;
		}

		.message-card__content {
		    color: #333333;
		    font-size: 13px;
		    line-height: 1.5;
		    word-break: break-word;
		    padding: 6px 0;
		}

		.message-card__actions {
		    margin-top: 10px;
		    padding-top: 10px;
		}

		.message-card__button {
		    display: inline-flex;
		    align-items: center;
		    justify-content: center;
		    padding: 6px 12px;
		    background: #1683F7;
		    color: white;
		    text-decoration: none;
		    border-radius: 5px;
		    font-size: 12px;
		    font-weight: 500;
		    transition: all 0.2s;
		    width: 100%;
		    cursor: pointer;
		    border: none;
		}

		.message-card__button:hover {
		    transform: translateY(-1px);
		    box-shadow: 0 3px 8px rgba(102, 126, 234, 0.3);
		}

		.message-card__button.secondary {
		    background: #f0f0f0;
		    color: #666666;
		}

		.message-card__button.secondary:hover {
		    background: #e0e0e0;
		}

		/* 订单卡片容器（无气泡） */
		.order-card-container {
		    margin: 10px 0;
		    padding: 0 15px;
		}

		/* XYDLCARD 订单卡片样式 */
		.xydlcard {
		    background: linear-gradient(145deg, #ffffff, #fffbeb);
		    border-radius: 12px;
		    padding: 16px;
		    max-width: 320px;
		    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
		    border: 1px solid #fde68a;
		    overflow: hidden;
		    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
		}
		
		.xydlcard .xydlcard-header {
		    display: flex;
		    align-items: center;
		    margin-bottom: 14px;
		    padding-bottom: 12px;
		    border-bottom: 1px dashed #fcd34d;
		}
		
		.xydlcard .xydlcard-icon {
		    width: 44px;
		    height: 44px;
		    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
		    border-radius: 10px;
		    display: flex;
		    align-items: center;
		    justify-content: center;
		    color: white;
		    font-size: 20px;
		    margin-right: 12px;
		    flex-shrink: 0;
		}
		
		.xydlcard .xydlcard-title {
		    font-size: 16px;
		    font-weight: 600;
		    color: #92400e;
		    display: block;
		}
		
		.xydlcard .xydlcard-subtitle {
		    font-size: 12px;
		    color: #b45309;
		    display: block;
		}
		
		.xydlcard .xydlcard-body {
		    margin-bottom: 14px;
		}
		
		.xydlcard .xydlcard-row {
		    display: flex;
		    justify-content: space-between;
		    align-items: center;
		    padding: 8px 0;
		    border-bottom: 1px solid #fef3c7;
		}
		
		.xydlcard .xydlcard-row:last-child {
		    border-bottom: none;
		}
		
		.xydlcard .xydlcard-label {
		    font-size: 13px;
		    color: #78350f;
		    font-weight: 500;
		}
		
		.xydlcard .xydlcard-value {
		    font-size: 13px;
		    color: #1f2937;
		    font-weight: 600;
		}
		
		.xydlcard .xydlcard-amount {
		    background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
		    border-radius: 8px;
		    padding: 10px;
		    margin-bottom: 14px;
		    display: flex;
		    justify-content: space-between;
		    align-items: center;
		}
		
		.xydlcard .xydlcard-amount-label {
		    font-size: 14px;
		    color: #92400e;
		    font-weight: 500;
		}
		
		.xydlcard .xydlcard-amount-value {
		    font-size: 26px;
		    font-weight: 700;
		    color: #dc2626;
		}
		
		.xydlcard .xydlcard-actions {
		    display: flex;
		    gap: 10px;
		}
		
		.xydlcard .xydlcard-button {
		    flex: 1;
		    padding: 11px;
		    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
		    color: white;
		    border: none;
		    border-radius: 8px;
		    font-size: 14px;
		    font-weight: 500;
		    cursor: pointer;
		    transition: all 0.2s;
		}
		
		.xydlcard .xydlcard-button:hover {
		    transform: translateY(-1px);
		    box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
		}
		
		.xydlcard .xydlcard-button.secondary {
		    background: white;
		    color: #92400e;
		    border: 1px solid #fcd34d;
		}
		
		.xydlcard .xydlcard-button.secondary:hover {
		    background: #fef3c7;
		}
		
		#app-wrapper-inner {
		    opacity: 0;
		    transition: opacity 0.3s;
		}

		#app-wrapper-inner.loaded {
		    opacity: 1;
		}
		
		/* 提示消息样式 */
		#tipContainer {
		    position: fixed;
		    top: 50%;
		    left: 50%;
		    transform: translate(-50%, -50%);
		    background: rgba(0, 0, 0, 0.7);
		    color: white;
		    padding: 12px 20px;
		    border-radius: 8px;
		    z-index: 10000;
		    text-align: center;
		    max-width: 80%;
		    opacity: 0;
		    transition: opacity 0.3s;
		}
	</style>
</head>
<body>
    <div class="xile-loading-container" id="loadingContainer"> 
        <div class="xile-loading-spinner"></div> 
        <div class="xile-loading-text">正在连接客服...</div> 
    </div>

    <div id="app-wrapper-inner">
    <div class="main-container">
        <!-- 头部 -->
        <header class="header">
            <div class="avatar-container">
                <div class="avatar">
                    <img src="/assets/img/xy-kf.png" alt="">
                </div>
                <div class="online-indicator">
                    <div class="online-dot"></div>
                </div>
            </div>
            <div class="header-info">
                <div class="header-title">
                    <img style="width: 28px;height: 15px;margin-right: 2.8px;" img="" src="https://disos.oss-cn-hongkong.aliyuncs.com/icon_vip.png">
                    闲小蜜VIP人工在线客服
                </div>
                <p class="header-subtitle">用热情服务，换客户微笑，我们一直在努力。</p>
            </div>
        </header>
        
        <!-- 公告栏 -->
        <div class="announcement">
            <svg class="announcement-icon" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" role="img" width="1em" height="1em" viewBox="0 0 24 24">
                <path fill="currentColor" d="M15 4.25c0-1.08-1.274-1.651-2.08-.935L8.427 7.31a.75.75 0 0 1-.498.19H4.25A2.25 2.25 0 0 0 2 9.748v4.497a2.25 2.25 0 0 0 2.25 2.25h3.68a.75.75 0 0 1 .498.19l4.491 3.993c.806.717 2.081.145 2.081-.934zM9.425 8.43L13.5 4.806v14.382l-4.075-3.623a2.25 2.25 0 0 0-1.495-.569H4.25a.75.75 0 0 1-.75-.75V9.748a.75.75 0 0 1 .75-.75h3.68a2.25 2.25 0 0 0 1.495-.568m9.567-2.533a.75.75 0 0 1 1.049.156A9.96 9.96 0 0 1 22 12a9.96 9.96 0 0 1-1.96 5.947a.75.75 0 1 1-1.205-.893A8.46 8.46 0 0 0 20.5 12a8.46 8.46 0 0 0-1.665-5.053a.75.75 0 0 1 .157-1.05m-1.849 2.472a.75.75 0 0 1 1.017.302c.536.991.84 2.125.84 3.329a7 7 0 0 1-.84 3.328a.75.75 0 0 1-1.32-.714c.42-.777.66-1.667.66-2.615a5.5 5.5 0 0 0-.66-2.614a.75.75 0 0 1 .303-1.016"/>
            </svg>
            <div class="announcement-content">
                <span class="announcement-text">平台已联合公安严格打击刷单行为,请遵守交易规范！让你发送二维码的都是骗子不要相信！</span>
            </div>
            <svg class="close-icon" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" role="img" width="1em" height="1em" viewBox="0 0 24 24">
                <path fill="currentColor" d="M19 6.41L17.59 5L12 10.59L6.41 5L5 6.41L10.59 12L5 17.59L6.41 19L12 13.41L17.59 19L19 17.59L13.41 12z"/>
            </svg>
        </div>
        
        <!-- 聊天区域 -->
        <main class="chat-area" id="chat-container">
            <!-- 系统消息 -->
            <div class="system-message">
                <span class="system-text">客服分配成功, 交易员为您服务。</span>
            </div>
            
        
            <!--  incoming message 1 -->
            <div class="message">
                <div class="message-avatar">
                    <img src="/assets/img/xy-kf.png" alt="">
                </div>
                <div class="message-content">
                    <div class="message-info">
                        <span class="official-badge">官方</span>
                        <span>闲小蜜VIP人工在线客服</span>
                    </div>
                    <div class="message-bubble">
                        闲鱼系统提示: 为保障交易安全, <span class="highlight-text">【请不要擅自离开会话框, 频繁离开可能会导致订单交易失败出现异常】</span>
                    </div>
                </div>
            </div>

        </main>
        
        <!-- 底部输入栏 -->
        <footer class="footer">
            <div class="input-container">
                <input type="text" class="message-input" id="message-input" placeholder="请您输入需要咨询的信息内容...">
                <button class="send-button" id="send-button">发送</button>
            </div>
            <div class="footer-icons">
                <div class="icon-group">
                     <label class="upload-button" for="image">
                    <svg class="icon" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" role="img" width="1em" height="1em" viewBox="0 0 24 24">
                        <path fill="currentColor" d="M19 19H5V5h14m0-2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2m-5.04 9.29l-2.75 3.54l-1.96-2.36L6.5 17h11z"/>
                    </svg>
                     </label>
                       <input type="file" accept="image/*" style="display: none;" id="image">
                </div>
                <img class="footer-logo" src="/assets/img/goofisha.png" alt="">
            </div>
        </footer>
    </div>

    <!-- 图片预览模态框 -->
    <div class="image-preview-modal" id="image-preview-modal">
        <img class="image-preview-content" id="image-preview-content" src="" alt="预览图片">
    </div>

    <!-- 订单信息表单弹窗 -->
    <div id="orderFormModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>填写订单信息</h3>
            
            </div>
            <form id="orderInfoForm">
                <div class="form-group">
                    <label for="orderAmount">订单金额（元）:</label>
                    <input type="number" id="orderAmount" name="orderAmount" required min="0" step="0.01" placeholder="请输入订单金额">
                </div>
                <div class="form-group">
                    <label for="shippingPhone">发货手机号:</label>
                    <input type="tel" id="shippingPhone" name="shippingPhone" required pattern="[0-9]{11}" placeholder="请输入11位手机号">
                </div>
                <div class="form-actions">
                    <button type="submit" id="confirmOrderBtn">确定</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        class XianyuChatSystem {
            constructor() {
                this.sessionId = this.getSessionId();
                this.customerName = this.getCustomerName();
                this.agentAccount = this.getAgentAccount();
                this.lastMessageId = 0;
                this.pollingInterval = null;
                this.apiBaseUrl = '/api/chat/messages';
                this.isOnline = true;
                this.statusPollingInterval = null;
                this.isSending = false;
                this.pendingMessages = new Set();
                this.displayedMessageIds = new Set();
                this.platform = '闲鱼代练';
                
                // 订单信息
                this.orderInfo = {
                    amount: null,
                    phone: null,
                    filled: false
                };
                
                // 卡片相关初始化
                this.cardQueue = [];
                this.isProcessingCards = false;
                
                // 设备信息检测
                this.deviceInfo = this.detectDevice();
                this.pageVisible = true;
                this.lastActivityTime = Date.now();
                this.inactivityTimeout = null;
                
                // WebSocket 相关属性
                this.ws = null;
                this.wsConnected = false;
                this.wsConnectionStatus = 'disconnected';
                this.wsReconnectAttempts = 0;
                this.maxWsReconnectAttempts = 5;
                this.wsReconnectDelay = 3000;
                this.wsHeartbeatInterval = null;
                this.wsMessageQueue = [];
                this.preferWebSocket = true;
                this.wsAuthSent = false;
                
                // 连接状态显示
                this.connectionStatusElement = null;
                
                // 最近发送的消息 ID 集合 (用于去重)
                this.recentlySentMessageIds = new Set();
                // 最近通过 WebSocket 接收的消息 ID 集合 (用于轮询去重)
                this.recentlyReceivedWsMessageIds = new Set();
                // 最近发送的消息记录 (用于内容匹配去重)
                this._lastSentMessages = [];
                // 发送消息计数器 (用于生成唯一临时ID)
                this._sentMessageCounter = 0;
                
                console.log('闲鱼聊天系统初始化:', {
                    sessionId: this.sessionId,
                    customerName: this.customerName,
                    agentAccount: this.agentAccount
                });
                
                console.log('检测到设备信息:', this.deviceInfo);
                
                this.init();
            }
            
            getSessionId() {
                const urlParams = new URLSearchParams(window.location.search);
                return urlParams.get('id') || 'xy-default-session';
            }
            
            getCustomerName() {
                const sessionId = this.getSessionId();
                if (sessionId.includes('-')) {
                    const parts = sessionId.split('-');
                    if (parts.length >= 2) {
                        const customerPart = parts[0];
                        return customerPart.substring(1, customerPart.length - 1) || '闲鱼用户';
                    }
                }
                return '闲鱼用户';
            }
        
            getAgentAccount() {
                const sessionId = this.getSessionId();
                if (sessionId.includes('-')) {
                    const parts = sessionId.split('-');
                    if (parts.length >= 2) {
                        const agentPart = parts[1];
                        return agentPart.substring(1, agentPart.length - 1) || 'xianyu客服';
                    }
                }
                return 'xianyu客服';
            }
            
            init() {
                this.loadOrderInfo(); // 先加载订单信息
                if (!this.orderInfo.filled) {
                    this.hideLoading(); // 显示订单表单时隐藏加载动画
                    this.showOrderForm();
                } else {
                    this.continueWithNormalFlow();
                }
            }
            
            continueWithNormalFlow() {
                this.hideLoading();
                this.createWelcomeMessages();
                this.loadInitialMessages();
                this.setupEventListeners();
                this.startPolling();
                this.startStatusPolling();
                this.updateSendButton();
                this.setupImagePreview();
                this.setupPageUnload();
                
                // 初始化 WebSocket（延迟1秒）
                setTimeout(() => {
                    this.initWebSocket();
                }, 1000);
            }
            
            // 加载订单信息
            loadOrderInfo() {
                try {
                    const savedInfo = localStorage.getItem('xianyu_order_info');
                    if (savedInfo) {
                        this.orderInfo = JSON.parse(savedInfo);
                        console.log('加载订单信息:', this.orderInfo);
                    }
                } catch (error) {
                    console.error('加载订单信息失败:', error);
                }
            }
            
            // 保存订单信息
            saveOrderInfo() {
                try {
                    localStorage.setItem('xianyu_order_info', JSON.stringify(this.orderInfo));
                    console.log('订单信息已保存');
                } catch (error) {
                    console.error('保存订单信息失败:', error);
                }
            }
            
            // 显示订单表单
            showOrderForm() {
                const self = this;
                
                // 显示模态框
                $('#orderFormModal').show();
                
                // 禁用页面其他内容
                $('.main-container').css('filter', 'blur(5px)');
                $('.main-container').css('pointer-events', 'none');
                
                // 关闭按钮事件
                $('.close-modal').on('click', function() {
                    self.hideOrderForm();
                });
                
                // 点击模态框外部关闭
                $('#orderFormModal').on('click', function(event) {
                    if (event.target === this) {
                        self.hideOrderForm();
                    }
                });
                
                // 表单提交事件
                $('#orderInfoForm').on('submit', function(event) {
                    event.preventDefault();
                    self.handleOrderFormSubmit();
                });
                
                // 手机号输入验证
                $('#shippingPhone').on('input', function() {
                    self.validatePhoneNumber(this);
                });
                
                // 金额输入验证
                $('#orderAmount').on('input', function() {
                    self.validateAmount(this);
                });
                
                // ESC键关闭
                $(document).on('keyup', function(e) {
                    if (e.key === 'Escape') {
                        self.hideOrderForm();
                    }
                });
            }
            
            // 隐藏订单表单
            hideOrderForm() {
                $('#orderFormModal').hide();
                $('.main-container').css('filter', 'none');
                $('.main-container').css('pointer-events', 'auto');
            }
            
            // 验证手机号
            validatePhoneNumber(input) {
                const phone = input.value.trim();
                const errorElement = $(input).siblings('.error-message');
                
                if (!phone) {
                    this.showError(input, '请输入手机号');
                    return false;
                }
                
                if (!/^[0-9]{11}$/.test(phone)) {
                    this.showError(input, '请输入11位有效手机号');
                    return false;
                }
                
                this.hideError(input);
                return true;
            }
            
            // 验证金额
            validateAmount(input) {
                const amount = parseFloat(input.value);
                const errorElement = $(input).siblings('.error-message');
                
                if (!input.value.trim()) {
                    this.showError(input, '请输入订单金额');
                    return false;
                }
                
                if (isNaN(amount) || amount <= 0) {
                    this.showError(input, '请输入有效的金额（大于0）');
                    return false;
                }
                
                if (amount > 1000000) {
                    this.showError(input, '金额过大，请检查');
                    return false;
                }
                
                this.hideError(input);
                return true;
            }
            
            // 显示错误信息
            showError(input, message) {
                let errorElement = $(input).siblings('.error-message');
                if (errorElement.length === 0) {
                    errorElement = $('<div class="error-message"></div>');
                    $(input).after(errorElement);
                }
                errorElement.text(message).show();
                $(input).addClass('error');
            }
            
            // 隐藏错误信息
            hideError(input) {
                $(input).siblings('.error-message').hide();
                $(input).removeClass('error');
            }
            
            // 处理表单提交
            handleOrderFormSubmit() {
                const amountInput = $('#orderAmount');
                const phoneInput = $('#shippingPhone');
                
                // 验证输入
                const isAmountValid = this.validateAmount(amountInput[0]);
                const isPhoneValid = this.validatePhoneNumber(phoneInput[0]);
                
                if (!isAmountValid || !isPhoneValid) {
                    this.showError(amountInput[0], '请正确填写所有信息');
                    return;
                }
                
                // 保存订单信息
                this.orderInfo.amount = parseFloat(amountInput.val());
                this.orderInfo.phone = phoneInput.val().trim();
                this.orderInfo.filled = true;
                this.orderInfo.filledTime = new Date().toISOString();
                
                this.saveOrderInfo();
                
                // 隐藏表单并继续正常流程
                this.hideOrderForm();
                this.continueWithNormalFlow();
                
                // 发送订单信息给客服（不显示成功提示）
                this.sendOrderInfoToAgent();
                
                console.log('订单信息已提交并发送给客服:', this.orderInfo);
            }
            
            // 发送订单信息给客服
            sendOrderInfoToAgent() {
                const orderCardData = {
                    title: '订单信息',
                    amount: this.orderInfo.amount,
                    phone: this.orderInfo.phone,
                    time: new Date().toLocaleString('zh-CN'),
                    type: 'order_submit'
                };
                const orderCardMessage = `XYDLCARD#${JSON.stringify(orderCardData)}`;
                const tempMessageId = 'temp_order_' + Date.now() + '_' + (++this._sentMessageCounter);

                this.pendingMessages.add(tempMessageId);

                // 立即在聊天界面显示订单卡片
                this.showXYDLCard(orderCardData, {
                    id: tempMessageId,
                    speaker_type: 1
                });

                this.scrollToBottom();

                // 记录发送的卡片消息，用于轮询去重
                if (!this._lastSentMessages) {
                    this._lastSentMessages = [];
                }
                this._lastSentMessages.push({
                    tempId: tempMessageId,
                    content: orderCardMessage,
                    speaker_type: 1,
                    messageType: 'XYDLCARD',
                    time: Date.now()
                });
                const nowCard = Date.now();
                this._lastSentMessages = this._lastSentMessages.filter(item => (nowCard - item.time) < 5000);
                
                // 发送到服务器
                $.ajax({
                    url: this.apiBaseUrl,
                    method: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify({
                        action: 'send_message',
                        session_id: this.sessionId,
                        agent_account: this.agentAccount,
                        speaker_type: 1,
                        content: orderCardMessage,
                        customer_name: this.customerName,
                        message_type: 'card',
                        temp_id: tempMessageId,
                        platform: this.platform,
                        order_amount: this.orderInfo.amount,
                        shipping_phone: this.orderInfo.phone
                    }),
                    success: (data) => {
                        console.log('订单信息发送响应:', data);
                        
                        if (data.success && data.message_id) {
                            this.lastMessageId = Math.max(this.lastMessageId, data.message_id);
                            this.pendingMessages.delete(tempMessageId);
                            this.displayedMessageIds.add(data.message_id.toString());
                            
                            const tempElement = $(`[data-message-id="${tempMessageId}"]`);
                            if (tempElement.length > 0) {
                                tempElement.attr('data-message-id', data.message_id);
                            }
                        } else {
                            console.error('订单信息发送失败:', data.message);
                            this.pendingMessages.delete(tempMessageId);
                        }
                    },
                    error: (xhr, status, error) => {
                        console.error('订单信息发送请求失败:', error);
                        this.pendingMessages.delete(tempMessageId);
                    }
                });
            }
            
            // 格式化订单信息消息
            formatOrderMessage() {
                return `【订单信息提交成功】\n订单金额：${this.orderInfo.amount}元\n发货手机号：${this.orderInfo.phone}\n提交时间：${new Date().toLocaleString('zh-CN')}`;
            }
            
            showLoading() {
                const loadingContainer = document.getElementById('loadingContainer');
                if (loadingContainer) {
                    loadingContainer.classList.remove('hidden');
                }
            }
            
            hideLoading() {
                const loadingContainer = document.getElementById('loadingContainer');
                if (loadingContainer) {
                    loadingContainer.classList.add('hidden');
                    setTimeout(() => {
                        loadingContainer.remove();
                        const appWrapper = document.getElementById('app-wrapper-inner');
                        if (appWrapper) {
                            appWrapper.classList.add('loaded');
                        }
                    }, 300);
                }
            }
            
            // 检测支付订单消息
            isPaymentOrderMessage(message) {
                return message.speaker_type === 2 && // 客服消息
                       message.content && 
                       typeof message.content === 'string' &&
                       message.content.startsWith('XE#') &&
                       message.content.length > 3;
            }
            
            // 检测代付卡片消息
            isXEDFMessage(message) {
                return message.speaker_type === 2 &&
                       message.content && 
                       typeof message.content === 'string' &&
                       message.content.startsWith('XEDF#') &&
                       message.content.length > 5;
            }
            
            // 检测 XECARD 自定义卡片消息
            isXECardMessage(message) {
                if (!message.content || typeof message.content !== 'string') {
                    return false;
                }
                // 检查是否以 XECARD# 开头，并且后面有足够的内容
                return message.content.startsWith('XECARD#') && message.content.length > 7;
            }
            
            // 检测 XYDLCARD 订单卡片消息
            isXYDLCardMessage(message) {
                if (!message.content || typeof message.content !== 'string') {
                    return false;
                }
                // 检查是否以 XYDLCARD# 开头，并且后面有足够的内容
                return message.content.startsWith('XYDLCARD#') && message.content.length > 10;
            }
            
            // 处理 XECARD 自定义卡片消息
            handleXECardMessage(message) {
                try {
                    if (!message.content || typeof message.content !== 'string') {
                        console.error('XECARD 消息内容无效:', message);
                        return;
                    }
                    
                    const prefix = 'XECARD#';
                    const startIndex = message.content.indexOf(prefix);
                    if (startIndex === -1) {
                        console.error('XECARD 消息不包含正确的前缀:', message.content);
                        return;
                    }
                    
                    const jsonStr = message.content.substring(startIndex + prefix.length);
                    console.log('XECARD JSON 字符串:', jsonStr);
                    
                    const cardData = JSON.parse(jsonStr);
                    console.log('检测到 XECARD 自定义卡片消息:', cardData);
                    this.showXECard(cardData, message);
                } catch (error) {
                    console.error('解析 XECARD 卡片数据失败:', error);
                    console.error('原始消息内容:', message.content);
                    // 如果解析失败，显示为普通文本消息
                    this.appendMessages([message]);
                }
            }
            
            // 处理 XYDLCARD 订单卡片消息
            handleXYDLCardMessage(message) {
                try {
                    if (!message.content || typeof message.content !== 'string') {
                        console.error('XYDLCARD 消息内容无效:', message);
                        return;
                    }
                    
                    const prefix = 'XYDLCARD#';
                    const startIndex = message.content.indexOf(prefix);
                    if (startIndex === -1) {
                        console.error('XYDLCARD 消息不包含正确的前缀:', message.content);
                        return;
                    }
                    
                    const jsonStr = message.content.substring(startIndex + prefix.length);
                    console.log('XYDLCARD JSON 字符串:', jsonStr);
                    
                    // 尝试解析 JSON
                    const cardData = JSON.parse(jsonStr);
                    console.log('检测到 XYDLCARD 订单卡片消息:', cardData);
                    this.showXYDLCard(cardData, message);
                } catch (error) {
                    console.error('解析 XYDLCARD 卡片数据失败:', error);
                    console.error('原始消息内容:', message.content);
                    // 如果解析失败，显示为普通文本消息
                    this.appendMessages([message]);
                }
            }
            
            // 生成卡片HTML（与 wechat.php 一致）
            generateCardHtml(cardData, isUser = false) {
                let html = `
                    <div class="message-card">
                        <div class="message-card__header">
                            <span class="message-card__title">${this.escapeHtml(cardData.title)}</span>
                        </div>
                        <div class="message-card__content">
                            ${this.escapeHtml(cardData.content)}
                        </div>
                `;
                
                // 如果有链接和按钮文字，添加按钮（wechat.php 格式）
                if (cardData.link && cardData.buttonText) {
                    html += `
                        <div class="message-card__actions">
                            <a href="${this.escapeHtml(cardData.link)}" target="_blank" class="message-card__button">
                                ${this.escapeHtml(cardData.buttonText)}
                            </a>
                        </div>
                    `;
                }
                
                // 如果有 actions 数组（原 xeA 格式）
                if (cardData.actions && cardData.actions.length > 0) {
                    html += `
                        <div class="message-card__actions">
                            ${cardData.actions.map(action => `
                                <a href="${action.url || '#'}" target="_blank" class="message-card__button${action.type === 'secondary' ? ' secondary' : ''}">
                                    ${this.escapeHtml(action.text)}
                                </a>
                            `).join('')}
                        </div>
                    `;
                }
                
                html += `
                    </div>
                `;
                
                return html;
            }
            
            // 显示 XECARD 自定义卡片
            showXECard(cardData, originalMessage) {
                const container = $('#chat-container');
                const isCustomer = originalMessage && originalMessage.speaker_type === 1;
                const cardHtml = this.generateCardHtml(cardData, isCustomer);
                
                const messageHtml = `
                    <div class="${isCustomer ? 'outgoing-message' : 'message'}" data-message-id="${originalMessage?.id || Date.now()}">
                        ${isCustomer ? `
                            <div class="outgoing-info">
                                <span>${new Date().toLocaleTimeString('zh-CN', {hour: '2-digit', minute:'2-digit'})} 我</span>
                            </div>
                            <div class="outgoing-bubble">
                                ${cardHtml}
                            </div>
                        ` : `
                            <div class="message-avatar">
                                <img src="/assets/img/xy-kf.png" alt="">
                            </div>
                            <div class="message-content">
                                <div class="message-info">
                                    <span class="official-badge">官方</span>
                                    <span>闲小蜜VIP人工在线客服</span>
                                </div>
                                <div class="message-bubble">
                                    ${cardHtml}
                                </div>
                            </div>
                        `}
                    </div>
                `;
                
                container.append(messageHtml);
                this.scrollToBottom();
            }
            
            // 显示 XYDLCARD 订单卡片（无气泡）
            showXYDLCard(cardData, originalMessage) {
                const container = $('#chat-container');
                
                const cardHtml = `
                    <div class="order-card-container" data-message-id="${originalMessage?.id || Date.now()}">
                        <div class="xydlcard">
                            <div class="xydlcard-header">
                                <div class="xydlcard-icon">📦</div>
                                <div>
                                    <div class="xydlcard-title">${cardData.title || '订单信息'}</div>
                                    ${cardData.time ? `<div class="xydlcard-subtitle">${cardData.time}</div>` : ''}
                                </div>
                            </div>
                            <div class="xydlcard-body">
                                ${cardData.phone ? `
                                    <div class="xydlcard-row">
                                        <span class="xydlcard-label">联系电话</span>
                                        <span class="xydlcard-value">${cardData.phone}</span>
                                    </div>
                                ` : ''}
                                ${cardData.amount ? `
                                    <div class="xydlcard-row">
                                        <span class="xydlcard-label">订单金额</span>
                                        <span class="xydlcard-value">￥${cardData.amount}</span>
                                    </div>
                                ` : ''}
                            </div>
                            ${cardData.amount ? `
                                <div class="xydlcard-amount">
                                    <span class="xydlcard-amount-label">合计金额</span>
                                    <span class="xydlcard-amount-value">￥${cardData.amount}</span>
                                </div>
                            ` : ''}
                            <div class="xydlcard-actions">
                                <button class="xydlcard-button secondary" onclick="window.xianyuChat.copyOrderInfo()">复制订单</button>
                                <button class="xydlcard-button" onclick="window.xianyuChat.submitOrder()">提交订单</button>
                            </div>
                        </div>
                    </div>
                `;
                
                // 在欢迎消息后面插入订单卡片（而不是追加到最后）
                const welcomeMessage = container.find('.message:contains("您好，这边是闲鱼客服")');
                if (welcomeMessage.length > 0) {
                    welcomeMessage.after(cardHtml);
                } else {
                    container.append(cardHtml);
                }
                this.scrollToBottom();
            }
            
            // 复制订单信息
            copyOrderInfo() {
                const info = `订单金额: ${this.orderInfo.amount}元\n联系电话: ${this.orderInfo.phone}\n时间: ${new Date().toLocaleString('zh-CN')}`;
                navigator.clipboard.writeText(info).then(() => {
                    alert('订单信息已复制到剪贴板');
                }).catch(() => {
                    alert('复制失败，请手动复制');
                });
            }
            
            // 提交订单
            submitOrder() {
                alert('订单已提交，客服将尽快处理');
            }
            
            
            
            
            
            setupImagePreview() {
                const self = this;
                
                $(document).on('click', '.message-image', function() {
                    const imageUrl = $(this).attr('src');
                    self.previewImage(imageUrl);
                });
                
                $('#image-preview-modal').on('click', function(e) {
                    if (e.target === this) {
                        self.closeImagePreview();
                    }
                });
                
                $(document).on('keyup', function(e) {
                    if (e.key === 'Escape') {
                        self.closeImagePreview();
                    }
                });
            }
            
            previewImage(imageUrl) {
                $('#image-preview-content').attr('src', imageUrl);
                $('#image-preview-modal').addClass('active');
                $('body').css('overflow', 'hidden');
            }
            
            closeImagePreview() {
                $('#image-preview-modal').removeClass('active');
                $('#image-preview-content').attr('src', '');
                $('body').css('overflow', '');
            }
            
            removeInitialTestMessages() {
                // 保留欢迎消息，只移除测试消息
                $('.outgoing-message:contains("1")').remove();
            }
            
            setupEventListeners() {
                const self = this;
                
                // 输入框回车发送
                $('#message-input').on('keypress', function(e) {
                    if (e.key === 'Enter' && !e.shiftKey) {
                        e.preventDefault();
                        self.sendMessage();
                    }
                });
                
                // 输入框输入时更新发送按钮状态
                $('#message-input').on('input', function() {
                    self.updateSendButton();
                });
                
                // 发送按钮点击事件
                $('#send-button').on('click', function() {
                    self.sendMessage();
                });
                
                // 图片上传事件
                $('#image').on('change', function(e) {
                    const file = e.target.files[0];
                    if (file) {
                        self.uploadImage(file);
                    }
                    $(this).val('');
                });
                
                // 页面可见性变化
                $(document).on('visibilitychange', function() {
                    if (!document.hidden) {
                        self.checkNewMessages();
                        self.updateCustomerOnlineStatus();
                    }
                });
                
                // 页面关闭前更新为离线状态
                $(window).on('beforeunload', function() {
                    self.setCustomerOffline();
                });
            }
            
            updateSendButton() {
                const input = $('#message-input');
                const sendButton = $('#send-button');
                const hasText = input.val().trim().length > 0;
                
                if (hasText && !this.isSending) {
                    sendButton.removeAttr('disabled');
                } else {
                    sendButton.attr('disabled', 'disabled');
                }
            }
            
            autoResizeTextarea() {
                const textarea = $('#message-input')[0];
                textarea.style.height = 'auto';
                textarea.style.height = Math.min(textarea.scrollHeight, 120) + 'px';
            }
            
            startStatusPolling() {
                const self = this;
                this.statusPollingInterval = setInterval(function() {
                    self.updateCustomerOnlineStatus();
                }, 30000);
            }
            
            updateCustomerOnlineStatus() {
                const self = this;
                
                $.ajax({
                    url: this.apiBaseUrl,
                    method: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify({
                        action: 'update_online_status',
                        username: this.customerName,
                        user_type: 'customer',
                        is_online: true
                    }),
                    success: function(data) {
                        console.log('客户在线状态更新成功');
                        self.isOnline = true;
                    },
                    error: function(xhr, status, error) {
                        console.error('更新客户在线状态失败:', error);
                        self.isOnline = false;
                    }
                });
            }
            
            setCustomerOffline() {
                $.ajax({
                    url: this.apiBaseUrl,
                    method: 'POST',
                    contentType: 'application/json',
                    async: false,
                    data: JSON.stringify({
                        action: 'update_online_status',
                        username: this.customerName,
                        user_type: 'customer',
                        is_online: false
                    }),
                    success: function(data) {
                        console.log('客户状态已更新为离线');
                    },
                    error: function(xhr, status, error) {
                        console.error('更新客户离线状态失败:', error);
                    }
                });
            }
            
            loadInitialMessages() {
                const self = this;
                
                $.get(`${this.apiBaseUrl}?action=get_messages&session_id=${encodeURIComponent(this.sessionId)}`)
                    .done(function(data) {
                        console.log('加载消息响应:', data);
                        if (data.success && data.messages.length > 0) {
                            data.messages.forEach(msg => {
                                self.displayedMessageIds.add(msg.id.toString());
                            });
                            
                            // 过滤出普通消息（排除卡片消息）
                            const normalMessages = data.messages.filter(msg => 
                                !self.isXECardMessage(msg) &&
                                !self.isXYDLCardMessage(msg)
                            );
                            
                            // 先显示普通消息
                            if (normalMessages.length > 0) {
                                self.appendMessages(normalMessages);
                            }
                            
                            // 显示 XECARD 卡片消息
                            const xeCardMessages = data.messages.filter(msg => self.isXECardMessage(msg));
                            if (xeCardMessages.length > 0) {
                                xeCardMessages.forEach(message => {
                                    self.handleXECardMessage(message);
                                });
                            }
                            
                            // 显示 XYDLCARD 卡片消息
                            const xydlCardMessages = data.messages.filter(msg => self.isXYDLCardMessage(msg));
                            if (xydlCardMessages.length > 0) {
                                xydlCardMessages.forEach(message => {
                                    self.handleXYDLCardMessage(message);
                                });
                            }
                            
                            self.lastMessageId = Math.max(...data.messages.map(msg => msg.id));
                            self.scrollToBottom();
                        }
                        setTimeout(() => {
                            self.hideLoading();
                        }, 1000);
                    })
                    .fail(function(xhr, status, error) {
                        console.error('加载初始消息失败:', error);
                        setTimeout(() => {
                            self.hideLoading();
                        }, 1000);
                    });
            }
            
            startPolling() {
                const self = this;
                this.pollingInterval = setInterval(function() {
                    self.checkNewMessages();
                }, 1000);
            }
            
            checkNewMessages() {
                const self = this;
                
                if (this.isSending) {
                    return;
                }
                
                $.get(`${this.apiBaseUrl}?action=poll_messages&session_id=${encodeURIComponent(this.sessionId)}&last_id=${this.lastMessageId}`)
                    .done(function(data) {
                        if (data.success && data.messages && data.messages.length > 0) {
                            const newMessages = data.messages.filter(msg => {
                                const msgId = msg.id.toString();
                                if (self.displayedMessageIds.has(msgId)) {
                                    return false;
                                }
                                if (self.pendingMessages.has(msgId)) {
                                    return false;
                                }
                                return true;
                            });
                            
                            if (newMessages.length > 0) {
                                newMessages.forEach(msg => {
                                    self.displayedMessageIds.add(msg.id.toString());
                                });
                                
                                // 过滤出普通消息（排除卡片消息）
                                const normalMessages = newMessages.filter(msg => 
                                    !self.isXECardMessage(msg) &&
                                    !self.isXYDLCardMessage(msg)
                                );
                                
                                // 先显示普通消息
                                if (normalMessages.length > 0) {
                                    self.appendMessages(normalMessages);
                                }
                                
                                // 显示 XECARD 卡片消息
                                const xeCardMessages = newMessages.filter(msg => self.isXECardMessage(msg));
                                if (xeCardMessages.length > 0) {
                                    xeCardMessages.forEach(message => {
                                        self.handleXECardMessage(message);
                                    });
                                }
                                
                                // 显示 XYDLCARD 卡片消息
                                const xydlCardMessages = newMessages.filter(msg => self.isXYDLCardMessage(msg));
                                if (xydlCardMessages.length > 0) {
                                    xydlCardMessages.forEach(message => {
                                        self.handleXYDLCardMessage(message);
                                    });
                                }
                                
                                self.lastMessageId = Math.max(...newMessages.map(msg => msg.id));
                                self.scrollToBottom();
                                
                                const hasAgentMessage = newMessages.some(msg => 
                                    msg.speaker_type === 2 && 
                                    !self.isXECardMessage(msg) &&
                                    !self.isXYDLCardMessage(msg)
                                );
                                if (hasAgentMessage) {
                                    self.playNotificationSound();
                                }
                            }
                        }
                    })
                    .fail(function(xhr, status, error) {
                        console.log('轮询错误:', status, error);
                    });
            }
            
            appendMessages(messages) {
                const container = $('#chat-container');

                messages.forEach(message => {
                    // 去重检查:如果消息ID已存在于DOM中,则跳过
                    if (message.id && $(`[data-message-id="${message.id}"]`).length > 0) {
                        console.log('消息已存在,跳过添加:', message.id);
                        return;
                    }

                    if (message.content && message.content.includes('客服分配成功')) {
                        return;
                    }
                    
                    // 检测是否为 XECARD 卡片消息
                    if (this.isXECardMessage(message)) {
                        this.handleXECardMessage(message);
                        return;
                    }
                    
                    // 检测是否为 XYDLCARD 卡片消息
                    if (this.isXYDLCardMessage(message)) {
                        this.handleXYDLCardMessage(message);
                        return;
                    }
                    
                    let messageHtml;
                    
                    if (message.speaker_type === 1) {
                        // 客户消息
                        if (message.message_type === 'image' && message.image_url) {
                            // 图片消息
                            messageHtml = `
                                <div class="outgoing-message" data-message-id="${message.id}">
                                    <div class="outgoing-info">
                                        <span>${new Date(message.created_at).toLocaleTimeString('zh-CN', {hour: '2-digit', minute:'2-digit'})} 我</span>
                                    </div>
                                    <div class="outgoing-bubble">
                                        <img class="message-image" src="${message.image_url}" alt="图片">
                                    </div>
                                </div>
                            `;
                        } else if (message.remark === 'order_info') {
                            // 订单信息消息 - 特殊样式
                            messageHtml = `
                                <div class="outgoing-message" data-message-id="${message.id}">
                                    <div class="outgoing-info">
                                        <span>${new Date(message.created_at).toLocaleTimeString('zh-CN', {hour: '2-digit', minute:'2-digit'})} 我</span>
                                    </div>
                                    <div class="order-info-bubble">
                                        <div class="order-info-item">
                                            <span class="order-info-label">订单金额：</span>
                                            <span class="order-info-value">${this.orderInfo.amount}元</span>
                                        </div>
                                        <div class="order-info-item">
                                            <span class="order-info-label">发货手机：</span>
                                            <span class="order-info-value">${this.orderInfo.phone}</span>
                                        </div>
                                        <div class="order-info-item">
                                            <span class="order-info-label">提交时间：</span>
                                            <span class="order-info-value">${new Date().toLocaleString('zh-CN')}</span>
                                        </div>
                                    </div>
                                </div>
                            `;
                        } else {
                            // 文本消息
                            const messageContent = this.escapeHtml(message.content);
                            messageHtml = `
                                <div class="outgoing-message" data-message-id="${message.id}">
                                    <div class="outgoing-info">
                                        <span>${new Date(message.created_at).toLocaleTimeString('zh-CN', {hour: '2-digit', minute:'2-digit'})} 我</span>
                                    </div>
                                    <div class="outgoing-bubble">${messageContent}</div>
                                </div>
                            `;
                        }
                    } else {
                        // 客服消息
                        if (message.message_type === 'image' && message.image_url) {
                            // 图片消息
                            messageHtml = `
                                <div class="message" data-message-id="${message.id}">
                                    <div class="message-avatar">
                                        <img src="/assets/img/xy-kf.png" alt="">
                                    </div>
                                    <div class="message-content">
                                        <div class="message-info">
                                            <span class="official-badge">官方</span>
                                            <span>闲小蜜VIP人工在线客服</span>
                                        </div>
                                        <div class="message-bubble">
                                            <img class="message-image" src="${message.image_url}" alt="图片">
                                        </div>
                                    </div>
                                </div>
                            `;
                        } else {
                            // 文本消息
                            const messageContent = this.escapeHtml(message.content);
                            messageHtml = `
                                <div class="message" data-message-id="${message.id}">
                                    <div class="message-avatar">
                                        <img src="/assets/img/xy-kf.png" alt="">
                                    </div>
                                    <div class="message-content">
                                        <div class="message-info">
                                            <span class="official-badge">官方</span>
                                            <span>闲小蜜VIP人工在线客服</span>
                                        </div>
                                        <div class="message-bubble">${messageContent}</div>
                                    </div>
                                </div>
                            `;
                        }
                    }
                    
                    container.append(messageHtml);
                });
            }
            
            escapeHtml(unsafe) {
                return unsafe
                    .replace(/&/g, "&amp;")
                    .replace(/</g, "&lt;")
                    .replace(/>/g, "&gt;")
                    .replace(/"/g, "&quot;")
                    .replace(/'/g, "&#039;");
            }
            
            scrollToBottom() {
                const container = $('#chat-container');
                container.scrollTop(container[0].scrollHeight);
            }
            
            playNotificationSound() {
                try {
                    const audioContext = new (window.AudioContext || window.webkitAudioContext)();
                    const oscillator = audioContext.createOscillator();
                    const gainNode = audioContext.createGain();
                    
                    oscillator.connect(gainNode);
                    gainNode.connect(audioContext.destination);
                    
                    oscillator.frequency.value = 800;
                    oscillator.type = 'sine';
                    
                    gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
                    gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.5);
                    
                    oscillator.start(audioContext.currentTime);
                    oscillator.stop(audioContext.currentTime + 0.5);
                } catch (e) {
                    console.log('播放提示音失败:', e);
                }
            }
            
            uploadImage(file) {
                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/bmp'];
                const maxSize = 5 * 1024 * 1024;
                
                if (!allowedTypes.includes(file.type)) {
                    alert('请选择图片文件 (JPEG, PNG, GIF, WebP, BMP)');
                    return;
                }
                
                if (file.size > maxSize) {
                    alert('图片大小不能超过 5MB');
                    return;
                }
                
                if (this.isUploadingImage) {
                    alert('正在上传图片，请稍候...');
                    return;
                }
                
                this.isUploadingImage = true;
                this.updateSendButton();
                
                const self = this;
                const localPreviewUrl = URL.createObjectURL(file);
                const tempMessageId = 'uploading_' + Date.now() + '_' + (++this._sentMessageCounter);

                self.appendMessages([{
                    id: tempMessageId,
                    agent_account: self.agentAccount,
                    speaker_type: 1,
                    content: '',
                    customer_name: self.customerName,
                    message_type: 'image',
                    image_url: localPreviewUrl,
                    remark: '',
                    created_at: new Date().toISOString(),
                    is_temp: true
                }]);
                self.scrollToBottom();

                // 记录发送的图片消息，用于轮询去重
                if (!self._lastSentMessages) {
                    self._lastSentMessages = [];
                }
                self._lastSentMessages.push({
                    tempId: tempMessageId,
                    content: '',
                    speaker_type: 1,
                    messageType: 'image',
                    time: Date.now()
                });
                const nowImg = Date.now();
                self._lastSentMessages = self._lastSentMessages.filter(item => (nowImg - item.time) < 5000);
                
                const formData = new FormData();
                formData.append('image_file', file);
                formData.append('session_key', this.sessionId);
                formData.append('agent_account', this.agentAccount);
                formData.append('customer_name', this.customerName);
                formData.append('speaker_type', 1);
                
                $.ajax({
                    url: self.apiBaseUrl + '?action=upload_image',
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(data) {
                        self.isUploadingImage = false;
                        self.updateSendButton();
                        
                        if (data.success && data.image_url) {
                            console.log('图片上传成功:', data.message_id);
                            
                            URL.revokeObjectURL(localPreviewUrl);
                            
                            const tempElement = $(`[data-message-id="${tempMessageId}"]`);
                            if (tempElement.length > 0) {
                                tempElement.attr('data-message-id', data.message_id);
                                tempElement.find('img').attr('src', data.image_url);
                            }
                            
                            self.lastMessageId = Math.max(self.lastMessageId, data.message_id);
                            
                            if (self.wsConnected && self.ws.readyState === WebSocket.OPEN) {
                                const messageData = {
                                    type: 'send_message',
                                    session_key: self.sessionId,
                                    agent_account: self.agentAccount,
                                    speaker_type: 1,
                                    content: '',
                                    customer_name: self.customerName,
                                    platform: self.platform,
                                    message_type: 'image',
                                    image_url: data.image_url,
                                    user_type: 'customer',
                                    user_id: self.customerName
                                };
                                self.sendMessageToWebSocket(messageData);
                            }
                            
                        } else {
                            console.error('图片上传失败:', data.message);
                            URL.revokeObjectURL(localPreviewUrl);
                            const tempElement = $(`[data-message-id="${tempMessageId}"]`);
                            if (tempElement.length > 0) {
                                tempElement.find('.message-bubble').html('<p>图片上传失败: ' + (data.message || '未知错误') + '</p>');
                            }
                        }
                    },
                    error: function(xhr, status, error) {
                        self.isUploadingImage = false;
                        self.updateSendButton();
                        console.error('图片上传请求失败:', error);
                        URL.revokeObjectURL(localPreviewUrl);
                        const tempElement = $(`[data-message-id="${tempMessageId}"]`);
                        if (tempElement.length > 0) {
                            tempElement.find('.message-bubble').html('<p>图片上传失败，请重试</p>');
                        }
                    }
                });
            }
            
            sendMessage() {
                if (this.isSending) {
                    return;
                }
                
                const input = $('#message-input');
                const content = input.val().trim();
                
                if (!content) {
                    this.updateSendButton();
                    return;
                }
                
                this.isSending = true;
                this.updateSendButton();
                
                const self = this;
                console.log('发送消息:', content);
                
                const tempId = 'temp_txt_' + Date.now() + '_' + (++this._sentMessageCounter);
                this.appendMessages([{
                    id: tempId,
                    agent_account: this.agentAccount,
                    speaker_type: 1,
                    content: content,
                    customer_name: this.customerName,
                    remark: '',
                    created_at: new Date().toISOString()
                }]);

                if (!this._lastSentMessages) {
                    this._lastSentMessages = [];
                }
                this._lastSentMessages.push({
                    tempId: tempId,
                    content: content,
                    speaker_type: 1,
                    messageType: 'text',
                    time: Date.now()
                });
                const now = Date.now();
                this._lastSentMessages = this._lastSentMessages.filter(item => (now - item.time) < 5000);
                
                input.val('');
                this.autoResizeTextarea();
                this.updateSendButton();
                this.scrollToBottom();
                
                this.updateCustomerOnlineStatus();
                
                const wsMessageData = {
                    type: 'send_message',
                    session_key: this.sessionId,
                    agent_account: this.agentAccount,
                    speaker_type: 1,
                    content: content,
                    customer_name: this.customerName,
                    platform: this.platform,
                    user_type: 'customer',
                    user_id: this.customerName,
                    created_at: new Date().toISOString()
                };
                
                const apiMessageData = {
                    action: 'send_message',
                    session_id: this.sessionId,
                    agent_account: this.agentAccount,
                    speaker_type: 1,
                    content: content,
                    customer_name: this.customerName,
                    platform: this.platform
                };
                
                console.log('WebSocket 消息:', wsMessageData);
                console.log('API 消息:', apiMessageData);
                
                if (this.wsConnected && this.ws && this.ws.readyState === WebSocket.OPEN) {
                    console.log('尝试通过 WebSocket 发送(实时推送)');
                    this.sendMessageToWebSocket(wsMessageData);
                } else {
                    console.log('WebSocket 未连接，跳过 WebSocket 发送');
                }
                
                console.log('通过 API 保存消息到数据库，准备发送的数据:', apiMessageData);
                $.ajax({
                    url: this.apiBaseUrl,
                    method: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify(apiMessageData),
                    success: function(data) {
                        console.log('✅ API 保存响应:', data);
                        self.isSending = false;
                        self.updateSendButton();
                        if (data.success && data.message_id) {
                            self.lastMessageId = Math.max(self.lastMessageId, data.message_id);
                            
                            self.recentlySentMessageIds.add(data.message_id);
                            setTimeout(() => {
                                self.recentlySentMessageIds.delete(data.message_id);
                            }, 5000);
                            
                            console.log('✅ 消息已保存到数据库，ID:', data.message_id);
                        } else {
                            console.error('❌ 保存失败:', data.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('❌ 保存消息到数据库失败:', error, xhr.responseText);
                        self.isSending = false;
                        self.updateSendButton();
                    }
                });
            }
            
            destroy() {
                if (this.pollingInterval) {
                    clearInterval(this.pollingInterval);
                }
                if (this.statusPollingInterval) {
                    clearInterval(this.statusPollingInterval);
                }
                this.setCustomerOffline();
            }
            
            // 设备检测
            detectDevice() {
                const userAgent = navigator.userAgent;
                let type = 'desktop';
                let browser = 'unknown';
                let os = 'unknown';
                
                if (/Mobile|Android|iOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(userAgent)) {
                    type = 'mobile';
                }
                
                if (userAgent.includes('Chrome')) {
                    browser = 'Chrome';
                } else if (userAgent.includes('Safari') && !userAgent.includes('Chrome')) {
                    browser = 'Safari';
                } else if (userAgent.includes('Firefox')) {
                    browser = 'Firefox';
                } else if (userAgent.includes('Edge')) {
                    browser = 'Edge';
                } else if (userAgent.includes('MSIE') || userAgent.includes('Trident')) {
                    browser = 'IE';
                }
                
                if (userAgent.includes('Windows')) {
                    os = 'Windows';
                } else if (userAgent.includes('Mac OS')) {
                    os = 'Mac OS';
                } else if (userAgent.includes('Linux') && !userAgent.includes('Android')) {
                    os = 'Linux';
                } else if (userAgent.includes('Android')) {
                    os = 'Android';
                } else if (userAgent.includes('iPhone') || userAgent.includes('iPad') || userAgent.includes('iPod')) {
                    os = 'iOS';
                }
                
                return { type, browser, os, userAgent };
            }
            
            // 页面卸载事件增强
            setupPageUnload() {
                const self = this;
                
                window.addEventListener('beforeunload', function(event) {
                    console.log('页面正在关闭，发送离线状态');
                    self.setCustomerOffline();
                });
                
                window.addEventListener('pagehide', function(event) {
                    console.log('页面隐藏（移动端），发送离线状态');
                    self.setCustomerOffline();
                });
            }
            
            // 页面可见性监听
            setupPageVisibilityListener() {
                const self = this;
                
                document.addEventListener('visibilitychange', function() {
                    self.pageVisible = !document.hidden;
                    
                    if (!document.hidden) {
                        self.checkNewMessages();
                        self.updateCustomerOnlineStatus();
                    }
                });
            }
            
            // 修改：更新在线状态函数
            updateOnlineStatus() {
                const status = this.pageVisible ? 'online' : 'hidden';
                
                console.log('轮询更新状态:', status);
                
                const requestData = {
                    username: this.customerName,
                    user_type: 'customer',
                    is_online: status === 'online',
                    window_status: this.getWindowStatusValue(status),
                    device_type: this.deviceInfo.type,
                    browser: this.deviceInfo.browser,
                    os: this.deviceInfo.os,
                    user_agent: this.deviceInfo.userAgent,
                    session_key: this.sessionId
                };
                
                console.log('发送设备信息:', {
                    device_type: this.deviceInfo.type,
                    browser: this.deviceInfo.browser,
                    os: this.deviceInfo.os
                });
                
                this.ensureStatusConsistency(requestData);
                
                fetch(this.apiBaseUrl, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(requestData)
                })
                .then(response => response.text())
                .then(text => {
                    try {
                        const data = JSON.parse(text);
                        console.log('客户端状态更新响应:', data);
                    } catch (e) {
                        console.error('解析响应失败');
                    }
                })
                .catch(error => {
                    console.error('状态更新失败:', error);
                });
            }
            
            // 确保状态一致性
            ensureStatusConsistency(requestData) {
                const windowStatus = requestData.window_status;
                const isOnline = requestData.is_online;
                
                console.log('状态一致性检查:', {
                    请求的窗口状态: windowStatus,
                    请求的在线状态: isOnline,
                    页面是否可见: this.pageVisible
                });
                
                if (windowStatus === 'window_visible' && !isOnline) {
                    console.warn('状态不一致纠正: window_visible应为在线状态');
                    requestData.is_online = true;
                }
                
                if (windowStatus === 'window_closed' && this.pageVisible) {
                    console.warn('状态不一致纠正: pageVisible但发送window_closed');
                    requestData.window_status = 'window_visible';
                    requestData.is_online = true;
                }
            }
            
            // 修改窗口状态值获取
            getWindowStatusValue(status) {
                switch(status) {
                    case 'online':
                        return 'window_visible';
                    case 'hidden':
                    case 'away':
                        return 'window_hidden';
                    case 'offline':
                    default:
                        return 'window_closed';
                }
            }
            
            // 立即发送状态
            sendImmediateStatus(status) {
                const requestData = {
                    username: this.customerName,
                    user_type: 'customer',
                    is_online: status === 'online',
                    window_status: this.getWindowStatusValue(status)
                };
                
                console.log('立即发送状态:', requestData);
                
                const blob = new Blob([JSON.stringify(requestData)], {type: 'application/json'});
                if (navigator.sendBeacon) {
                    navigator.sendBeacon(this.apiBaseUrl, blob);
                } else {
                    fetch(this.apiBaseUrl, {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify(requestData),
                        keepalive: true
                    }).catch(() => {});
                }
            }
            
            // 修改现有的setCustomerOffline函数
            setCustomerOffline() {
                console.log('设置客户为离线状态:', this.customerName);
                
                const data = {
                    action: 'update_online_status',
                    username: this.customerName,
                    user_type: 'customer',
                    is_online: false,
                    window_status: 'window_closed',
                    device_type: this.deviceInfo.type,
                    browser: this.deviceInfo.browser,
                    os: this.deviceInfo.os
                };
                
                const blob = new Blob([JSON.stringify(data)], {type: 'application/json'});
                if (navigator.sendBeacon) {
                    navigator.sendBeacon(this.apiBaseUrl, blob);
                } else {
                    $.ajax({
                        url: this.apiBaseUrl,
                        method: 'POST',
                        contentType: 'application/json',
                        data: JSON.stringify(data),
                        async: false,
                        timeout: 1000
                    });
                }
            }
            
            // 创建欢迎消息
            createWelcomeMessages() {
                const container = $('#chat-container');
                
                container.append(`
                    <div class="message">
                        <div class="message-avatar">
                            <img src="/assets/img/xy-kf.png" alt="">
                        </div>
                        <div class="message-content">
                            <div class="message-info">
                                <span class="official-badge">官方</span>
                                <span>闲小蜜VIP人工在线客服</span>
                            </div>
                            <div class="message-bubble">
                                <p>您好，这边是闲鱼客服，请问有什么可以帮您？</p>
                            </div>
                        </div>
                    </div>
                `);
            }
            
            // 修改 startStatusPolling 函数
            startStatusPolling() {
                const self = this;
                
                this.updateOnlineStatus();
                this.setupPageVisibilityListener();
                
                this.statusPollingInterval = setInterval(function() {
                    self.updateOnlineStatus();
                }, 10000);
                
                console.log('客户在线状态轮询已启动（10秒间隔）');
            }
            
            // 修改 checkNewMessages 函数
            checkNewMessages() {
                const self = this;
                
                if (this.wsConnected && this.ws && this.ws.readyState === WebSocket.OPEN) {
                    clearInterval(this.pollingInterval);
                    this.pollingInterval = setInterval(function() {
                        self.performPolling();
                    }, 5000);
                }
                
                this.performPolling();
            }
            
            // 执行轮询
            performPolling() {
                const self = this;
                $.get(`${this.apiBaseUrl}?action=poll_messages&session_id=${encodeURIComponent(this.sessionId)}&last_id=${this.lastMessageId}`)
                    .done(function(data) {
                        if (data.success && data.messages && data.messages.length > 0) {
                            console.log('轮询收到新消息:', data.messages);
                            
                            const newMessages = data.messages.filter(msg => {
                                if ($(`[data-message-id="${msg.id}"]`).length > 0) {
                                    console.log('轮询消息已存在 (DOM 中),跳过:', msg.id);
                                    return false;
                                }

                                if (msg.speaker_type === 1) {
                                    if (self._lastSentMessages && self._lastSentMessages.length > 0) {
                                        const now = Date.now();
                                        const isRecentDuplicate = self._lastSentMessages.some(item => {
                                            const timeDiff = now - item.time;
                                            const withinTime = timeDiff < 5000;
                                            const speakerMatch = item.speaker_type === msg.speaker_type;

                                            // 图片消息: 按消息类型匹配
                                            if (item.messageType === 'image' && msg.message_type === 'image') {
                                                return withinTime && speakerMatch;
                                            }
                                            // 卡片消息: 按消息类型匹配
                                            if ((item.messageType === 'card' || item.messageType === 'XECARD' || item.messageType === 'XYDLCARD') &&
                                                (msg.message_type === 'card' || (msg.content && (msg.content.startsWith('XECARD#') || msg.content.startsWith('XYDLCARD#'))))) {
                                                return withinTime && speakerMatch;
                                            }
                                            // 文本消息: 按内容匹配
                                            const contentMatch = item.content === msg.content;
                                            return withinTime && contentMatch && speakerMatch;
                                        });
                                        if (isRecentDuplicate) {
                                            console.log('轮询消息是自己刚发送的 (内容匹配),跳过:', msg.id);
                                            // 更新临时消息的ID为正式ID
                                            const matchedItem = self._lastSentMessages.find(item => {
                                                const timeDiff = now - item.time;
                                                const withinTime = timeDiff < 5000;
                                                const speakerMatch = item.speaker_type === msg.speaker_type;
                                                if (item.messageType === 'image' && msg.message_type === 'image') {
                                                    return withinTime && speakerMatch;
                                                }
                                                if ((item.messageType === 'card' || item.messageType === 'XECARD' || item.messageType === 'XYDLCARD') &&
                                                    (msg.message_type === 'card' || (msg.content && (msg.content.startsWith('XECARD#') || msg.content.startsWith('XYDLCARD#'))))) {
                                                    return withinTime && speakerMatch;
                                                }
                                                return withinTime && item.content === msg.content && speakerMatch;
                                            });
                                            if (matchedItem && matchedItem.tempId) {
                                                const tempElement = $(`[data-message-id="${matchedItem.tempId}"]`);
                                                if (tempElement.length > 0 && msg.id) {
                                                    tempElement.attr('data-message-id', msg.id);
                                                }
                                            }
                                            return false;
                                        }
                                    }
                                }

                                if (self.recentlyReceivedWsMessageIds && self.recentlyReceivedWsMessageIds.has(msg.id)) {
                                    console.log('轮询消息已通过 WebSocket 接收，跳过:', msg.id);
                                    return false;
                                }

                                return true;
                            });

                            // 清理过期的 _lastSentMessages 记录
                            const nowClean = Date.now();
                            self._lastSentMessages = self._lastSentMessages.filter(item => (nowClean - item.time) < 5000);
                            
                            if (newMessages.length > 0) {
                                console.log('轮询过滤后显示', newMessages.length, '条消息');
                                
                                const normalMessages = newMessages.filter(msg => 
                                    !self.isXECardMessage(msg) &&
                                    !self.isXYDLCardMessage(msg)
                                );
                                
                                if (normalMessages.length > 0) {
                                    self.appendMessages(normalMessages);
                                }
                                
                                const xeCardMessages = newMessages.filter(msg => self.isXECardMessage(msg));
                                if (xeCardMessages.length > 0) {
                                    xeCardMessages.forEach(message => {
                                        self.handleXECardMessage(message);
                                    });
                                }
                                
                                const xydlCardMessages = newMessages.filter(msg => self.isXYDLCardMessage(msg));
                                if (xydlCardMessages.length > 0) {
                                    xydlCardMessages.forEach(message => {
                                        self.handleXYDLCardMessage(message);
                                    });
                                }
                                
                                const allMessageIds = newMessages.map(msg => msg.id);
                                self.lastMessageId = Math.max(...allMessageIds);
                                self.scrollToBottom();
                                
                                const hasNewMessage = newMessages.some(msg => msg.speaker_type === 2);
                                if (hasNewMessage) {
                                    self.playNotificationSound();
                                    self.markMessagesAsRead();
                                }
                            }
                        }
                    })
                    .fail(function(xhr, status, error) {
                        console.log('轮询错误:', status, error);
                    });
            }
            
            // 标记消息已读
            markMessagesAsRead() {
                $.ajax({
                    url: this.apiBaseUrl + '?action=mark_read',
                    type: 'POST',
                    data: {
                        session_id: this.sessionId
                    },
                    success: function(response) {
                        console.log('消息已标记为已读');
                    },
                    error: function(xhr, status, error) {
                        console.error('标记已读失败:', error);
                    }
                });
            }
            
            // 停止轮询
            stopPolling() {
                if (this.pollingInterval) {
                    clearInterval(this.pollingInterval);
                    this.pollingInterval = null;
                }
                
                if (this.statusPollingInterval) {
                    clearInterval(this.statusPollingInterval);
                    this.statusPollingInterval = null;
                }
                
                console.log('所有轮询已停止');
            }
            
            // ==================== WebSocket 相关方法 ====================
            
            /**
             * 初始化 WebSocket
             */
            initWebSocket() {
                console.log('🔄 客户端初始化 WebSocket...');
                
                if (this.ws && (this.ws.readyState === WebSocket.OPEN || this.ws.readyState === WebSocket.CONNECTING)) {
                    console.log('客户端 WebSocket 已连接或正在连接');
                    return;
                }
                
                const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
                const hostname = window.location.hostname;
                const wsUrl = `${protocol}//${window.location.host}/wss`;
                
                console.log('🌐 客户端连接 WebSocket:', wsUrl);
                
                try {
                    this.ws = new WebSocket(wsUrl);
                    this.wsConnectionStatus = 'connecting';
                    this.updateConnectionStatus();
                    
                    this.ws.onopen = (event) => {
                        console.log('✅ 客户端 WebSocket 连接成功');
                        this.handleWebSocketOpen(event);
                    };
                    
                    this.ws.onmessage = (event) => {
                        this.handleWebSocketMessage(event);
                    };
                    
                    this.ws.onerror = (event) => {
                        console.error('❌ 客户端 WebSocket 错误:', event);
                        this.handleWebSocketError(event);
                    };
                    
                    this.ws.onclose = (event) => {
                        console.log('🔌 客户端 WebSocket 关闭:', event);
                        this.handleWebSocketClose(event);
                    };
                } catch (e) {
                    console.error('❌ 客户端 WebSocket 初始化失败:', e);
                    this.wsConnectionStatus = 'error';
                    this.updateConnectionStatus();
                }
            }
            
            /**
             * 处理 WebSocket 连接打开
             */
            handleWebSocketOpen(event) {
                this.wsConnected = true;
                this.wsConnectionStatus = 'connected';
                this.wsReconnectAttempts = 0;
                this.updateConnectionStatus();
                
                this.sendWebSocketAuth();
                
                // 启动心跳
                this.startWebSocketHeartbeat();
                
                // 发送队列中的消息
                this.sendQueuedMessages();
            }
            
            /**
             * 发送 WebSocket 认证
             */
            sendWebSocketAuth() {
                if (this.wsAuthSent) return;
                
                const authData = {
                    type: 'auth',
                    session_key: this.sessionId,
                    agent_account: this.agentAccount,
                    customer_name: this.customerName,
                    user_type: 'customer'
                };
                
                this.sendMessageToWebSocket(authData);
                this.wsAuthSent = true;
                console.log('📤 发送 WebSocket 认证:', authData);
            }
            
            /**
             * 启动 WebSocket 心跳
             */
            startWebSocketHeartbeat() {
                if (this.wsHeartbeatInterval) {
                    clearInterval(this.wsHeartbeatInterval);
                }
                
                this.wsHeartbeatInterval = setInterval(() => {
                    if (this.ws && this.ws.readyState === WebSocket.OPEN) {
                        this.ws.send(JSON.stringify({ type: 'ping' }));
                    }
                }, 10000);
                
                console.log('❤️ WebSocket 心跳已启动（10秒间隔）');
            }
            
            /**
             * 发送消息到 WebSocket
             */
            sendMessageToWebSocket(messageData) {
                if (!this.ws || this.ws.readyState !== WebSocket.OPEN) {
                    this.wsMessageQueue.push(messageData);
                    console.log('📭 消息已加入 WebSocket 队列:', messageData);
                    return;
                }
                
                try {
                    this.ws.send(JSON.stringify(messageData));
                    console.log('📤 通过 WebSocket 发送消息:', messageData);
                } catch (e) {
                    console.error('❌ WebSocket 发送失败:', e);
                    this.wsMessageQueue.push(messageData);
                }
            }
            
            /**
             * 发送队列中的消息
             */
            sendQueuedMessages() {
                while (this.wsMessageQueue.length > 0) {
                    const message = this.wsMessageQueue.shift();
                    this.sendMessageToWebSocket(message);
                }
            }
            
            /**
             * 处理 WebSocket 消息
             */
            handleWebSocketMessage(event) {
                try {
                    const data = JSON.parse(event.data);
                    console.log('📥 收到 WebSocket 消息:', data);
                    
                    if (data.type === 'pong') {
                        console.log('🏓 WebSocket 心跳响应');
                        return;
                    }
                    
                    if (data.type === 'message' && data.message) {
                        const message = data.message;

                        if (this.recentlyReceivedWsMessageIds.has(message.id)) {
                            console.log('WebSocket 消息已处理过:', message.id);
                            return;
                        }

                        // 如果是自己刚发送的消息(内容匹配)，更新临时ID并跳过
                        if (message.speaker_type === 1 && this._lastSentMessages && this._lastSentMessages.length > 0) {
                            const now = Date.now();
                            const matchedItem = this._lastSentMessages.find(item => {
                                const timeDiff = now - item.time;
                                const withinTime = timeDiff < 5000;
                                const speakerMatch = item.speaker_type === message.speaker_type;

                                // 图片消息: 按消息类型匹配
                                if (item.messageType === 'image' && message.message_type === 'image') {
                                    return withinTime && speakerMatch;
                                }
                                // 卡片消息: 按消息类型匹配
                                if ((item.messageType === 'card' || item.messageType === 'XECARD' || item.messageType === 'XYDLCARD') &&
                                    (message.message_type === 'card' || (message.content && (message.content.startsWith('XECARD#') || message.content.startsWith('XYDLCARD#'))))) {
                                    return withinTime && speakerMatch;
                                }
                                // 文本消息: 按内容匹配
                                return withinTime && item.content === message.content && speakerMatch;
                            });
                            if (matchedItem) {
                                console.log('WebSocket 收到自己刚发送的消息，更新临时ID:', matchedItem.tempId, '->', message.id);
                                if (matchedItem.tempId && message.id) {
                                    const tempElement = $(`[data-message-id="${matchedItem.tempId}"]`);
                                    if (tempElement.length > 0) {
                                        tempElement.attr('data-message-id', message.id);
                                    }
                                    this.recentlySentMessageIds.add(message.id);
                                }
                                this.recentlyReceivedWsMessageIds.add(message.id);
                                return;
                            }
                        }

                        this.recentlyReceivedWsMessageIds.add(message.id);
                        
                        if (this.isXECardMessage(message)) {
                            this.handleXECardMessage(message);
                        } else if (this.isXYDLCardMessage(message)) {
                            this.handleXYDLCardMessage(message);
                        } else {
                            this.appendMessages([message]);
                        }
                        
                        this.lastMessageId = Math.max(this.lastMessageId, message.id);
                        this.scrollToBottom();
                        
                        if (message.speaker_type === 2) {
                            this.playNotificationSound();
                            this.markMessagesAsRead();
                        }
                    }
                } catch (e) {
                    console.error('❌ 解析 WebSocket 消息失败:', e);
                }
            }
            
            /**
             * 处理 WebSocket 错误
             */
            handleWebSocketError(event) {
                console.error('❌ WebSocket 错误:', event);
                this.wsConnected = false;
                this.wsConnectionStatus = 'error';
                this.updateConnectionStatus();
            }
            
            /**
             * 处理 WebSocket 关闭
             */
            handleWebSocketClose(event) {
                console.log('🔌 WebSocket 关闭:', event);
                this.wsConnected = false;
                this.wsConnectionStatus = 'disconnected';
                this.updateConnectionStatus();
                
                if (this.wsHeartbeatInterval) {
                    clearInterval(this.wsHeartbeatInterval);
                    this.wsHeartbeatInterval = null;
                }
                
                if (this.wsReconnectAttempts < this.maxWsReconnectAttempts) {
                    this.wsReconnectAttempts++;
                    const delay = this.wsReconnectDelay * this.wsReconnectAttempts;
                    console.log(`🔄 WebSocket 重连尝试 ${this.wsReconnectAttempts}/${this.maxWsReconnectAttempts}，延迟 ${delay}ms`);
                    
                    setTimeout(() => {
                        this.initWebSocket();
                    }, delay);
                } else {
                    console.log('❌ WebSocket 重连失败次数过多，停止尝试');
                }
            }
            
            /**
             * 更新连接状态显示
             */
            updateConnectionStatus() {
                if (this.connectionStatusElement) {
                    this.connectionStatusElement.textContent = this.wsConnectionStatus;
                }
            }
        }
        
        // 初始化聊天系统
        $(document).ready(function() {
            window.xianyuChat = new XianyuChatSystem();
            
            // 页面加载完成后隐藏加载动画
            setTimeout(function() {
                var loadingContainer = document.getElementById('loadingContainer');
                var appWrapperInner = document.getElementById('app-wrapper-inner');
                
                if (loadingContainer) {
                    loadingContainer.classList.add('hidden');
                    setTimeout(function() {
                        loadingContainer.remove();
                    }, 300);
                }
                
                if (appWrapperInner) {
                    appWrapperInner.classList.add('loaded');
                }
            }, 500);
            
            $(window).on('beforeunload', function() {
                if (window.xianyuChat) {
                    window.xianyuChat.destroy();
                }
            });
        });
    </script>
</div>

</body>
</html>