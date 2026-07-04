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

$shop=$_GET['shop'] ?? '';
$title=$_GET['title'] ?? '';
$amount=$_GET['rmb'] ?? '';
$url=$_GET['img'] ?? '';
$xe=$_GET['x'] ?? '';

// 检查是否有参数传递（以shop参数为例，也可以检查其他参数）
$hasParams = !empty($shop) || !empty($title) || !empty($amount) || !empty($url) || !empty($xe);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>闲鱼官方客服</title>
    <link rel="shortcut icon" href="/assets/img/goofishicon.webp" type="image/x-icon">
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

    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }
    
    html, body {
        height: 100% !important;
        width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
        overflow: auto !important;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
    }
    
    /* 主应用容器 - 使用flex布局 */
    #app {
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        right: 0 !important;
        bottom: 0 !important;
        width: 100vw !important;
        height: 100% !important;
        display: flex !important;
        flex-direction: column !important;
        overflow: auto !important;
        background: #f5f5f5 !important;
    }
        .chat-container {
    max-height: calc(100% + 1px);
    min-height: calc(100% + 1px);
    max-width: 100%;
    min-width: 100%;
            background: #f7f7f7;
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        
        .chat-header {
            position: sticky;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            min-height: 47px;
            background: white;
            padding: 12px 16px;
            border-bottom: 1px solid #f0f0f0;
            flex-shrink: 0;
        }
        
        .chat-title {
            font-size: 18px;
            font-weight: 600;
            color: #272636;
        }
        
        .messages-container {
            flex: 1;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            padding: 12px;
            background: #f7f7f7;
        }
        
        .welcome-message {
            display: flex;
            flex-direction: column;
            color: #8e8e8e;
            font-size: 13px;
            align-items: center;
            margin-bottom: 18px;
            border-radius: 8px;
            text-align: center;
        }
        
        /* 消息样式 */
        .message {
            display: flex;
            margin-bottom: 16px;
        }
        
        .message.agent {
            align-items: flex-start;
        }
        
        .message.customer {
            flex-direction: row-reverse;
            align-items: flex-end;
        }
        
        .avatar {
            min-height: 36px;
            min-width: 36px;
            max-width: 36px;
            max-height: 36px;
            border-radius: 0;
            overflow: hidden;
            flex-shrink: 0;
        }
        
        .avatar img {
            height: 100%;
            width: 100%;
        }
        
        .message-content {
            width: 100%;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
        }
        
        .message.agent .message-content {
            margin-left: 3px;
            align-items: flex-start;
        }
        
        .message.customer .message-content {
            margin-right: 3px;
            align-items: flex-end;
        }
        
        .sender-name {
            color: #8e8e8e;
            font-size: 12px;
            margin-left: 1px;
            margin-bottom: 4px;
        }
        
        .message-bubble {
            padding: 10px;
            background: white;
            border-radius: 12px;
            width: fit-content;
            overflow: hidden;
            word-break: break-all;
            letter-spacing: 0.5px;
            user-select: auto;
            -webkit-user-select: auto;
            font-size: 15px;
            max-width: 70%;
        }
        
        .message-bubble.customer {
            background: #fbe440;
        }
        
        .special-message {
            background: #fff;
            border-radius: 8px;
            padding: 10px 12px;
            padding-bottom: 0;
            font-size: 15px;
            width: 85%;
        }
        
        .order-button {
            background: #fbe440;
            text-align: center;
            border-radius: 24px;
            padding: 7px 0;
            font-size: 14px;
            margin-top: 12px;
            font-weight: bold;
            margin-bottom: 12px;
            cursor: pointer;
        }
        
        .quick-question {
            padding: 12px 0;
            font-size: 13.5px;
            border-top: 1px solid rgba(0,0,0,.06);
            position: relative;
            cursor: pointer;
        }
        
        .quick-question::after {
            content: "";
            background: url(/assets/img/zz-2.png);
            position: absolute;
            right: 2px;
            top: 50%;
            transform: translateY(-50%);
            width: 10px;
            height: 10px;
            background-size: contain;
            background-repeat: no-repeat;
        }
        
        .highlight-text {
            text-decoration: underline;
            text-decoration-color: #fbe440;
            text-decoration-thickness: 6px;
            text-underline-offset: -1.5px;
            font-weight: bold;
        }
        
        .input-area {
            background: #f5f5f5;
            padding: 4px;
            flex-shrink: 0;
        }
        
        .quick-replies {
            width: 100%;
            font-size: .85rem;
             overflow: auto; /* 显示滚动条，根据内容决定是否显示 */
/* 隐藏默认的滚动条样式 */
  scrollbar-width: none; /* Firefox */
  -ms-overflow-style: none; /* IE and Edge */
        }
        
        .quick-replies-container {
            display: flex;
            gap: 8px;
            word-break: keep-all;
            padding: 5px 4px;
        }
        
        .quick-reply {
            padding: 5px 14px;
            border-radius: 16px;
            background-color: #fff;
            color: #000;
            white-space: nowrap;
            cursor: pointer;
        }
        
        .quick-reply.active {
            border: 1px solid #ffe60d;
        }
        
        .input-row {
            display: flex;
            align-items: center;
            padding: 6px 8px;
            box-sizing: border-box;
            gap: 8px;
        }
        
        .message-input {
            font-size: 14px;
            border: none;
            border-radius: 8px;
            font-family: inherit;
            padding: 8px 12px;
            width: 100%;
            box-sizing: border-box;
            background: white;
            flex: 1;
        }
        
        .message-input:focus {
            outline: none;
        }
        
        .upload-button {
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: #666;
            flex-shrink: 0;
        }
        
        .upload-icon {
            width: 32px;
            height: 32px;
        }
        
        .send-button {
            word-break: keep-all;
            padding: 8px 16px;
            background: #ffe60d;
            border: none;
            border-radius: 18px;
            font-size: 14px;
            color: #2d2d2d;
            font-family: inherit;
            cursor: pointer;
            white-space: nowrap;
            flex-shrink: 0;
        }
        
        .send-button:disabled {
            background: #cccccc;
            color: #666666;
            cursor: not-allowed;
        }
        
        .session-info {
            font-size: 12px;
            color: #666;
            margin-top: 4px;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .typing-indicator {
            display: none;
            padding: 8px 16px;
            color: #999;
            font-size: 12px;
            font-style: italic;
        }

        /* 隐藏文件输入框 */
        #input-image {
            display: none;
        }

        /* 图片消息样式 */
        .message-image {
            max-width: 200px;
            max-height: 200px;
            border-radius: 8px;
            cursor: pointer;
            display: block;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: transform 0.2s ease;
        }

        .message-image:hover {
            transform: scale(1.02);
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
        


.detail-button {
    background: #f5f5f5;
    color: #666;
    border: 1px solid #e0e0e0;
}

.detail-button:hover {
    background: #e8e8e8;
}

/* 系统消息样式 */
.message.system {
    justify-content: center;
    margin: 8px 0;
}

.system-message {
    background: rgba(0, 0, 0, 0.05);
    padding: 8px 16px;
    border-radius: 16px;
    font-size: 12px;
    color: #666;
    max-width: 60%;
    text-align: center;
}

.system-message.success {
    background: #f6ffed;
    color: #52c41a;
    border: 1px solid #b7eb8f;
}

.system-message.error {
    background: #fff2f0;
    color: #ff4d4f;
    border: 1px solid #ffccc7;
}
.quick-replies::-webkit-scrollbar {
  display: none; /* Chrome, Safari, and Opera */
}
    </style>
    <style>
        /* 弹窗样式 */
        .XExytc-overlay {
            height: 100%;
            width: 100%;
            position: fixed;
            top: 0;
            left: 0;
            background: rgba(0, 0, 0, 0.2);
            z-index: 999;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }

        .XExytc-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .XExytc-bottom-sheet {
            width: 100%;
            display: flex;
            flex-direction: column;
            padding: .5rem .5rem calc(env(safe-area-inset-bottom) + .5rem) .5rem;
            box-sizing: border-box;
            z-index: 1000;
            position: fixed;
            bottom: 0;
            left: 0;
            align-items: center;
            font-size: .9375rem;
            height: 85%;
            max-height: 85%;
            background: #f2f2f2;
            border-radius: 1.5rem 1.5rem 0 0;
            transform: translateY(100%);
            transition: transform 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);

            overflow: hidden;
        }

        .XExytc-bottom-sheet.active {
            transform: translateY(0);
        }

        .XExytc-title {
            font-size: .9375rem;
            font-weight: bold;
            margin-bottom: .5rem;
        }

        .XExytc-container {
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
        }

        .XExytc-header {
            width: 100%;
            position: relative;
            height: 3rem;
            display: flex;
            align-items: center;
            justify-content: center;
            line-height: 1.25rem;
            font-weight: bold;
            font-size: 1.0625rem;
            flex-shrink: 0;
        }

        .XExytc-close {
            position: absolute;
            right: 1rem;
            width: 1.5rem;
            height: 1.5rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.2s ease;
        }

        .XExytc-close:hover {
            transform: scale(1.1);
        }

        .XExytc-close svg {
            width: 1.5rem;
            height: 1.5rem;
        }

        .XExytc-content {
            height: 100%;
            display: flex;
            align-items: center;
            flex-direction: column;
            justify-content: center;
            gap: .75rem;
        }

        .XExytc-order-item {
            display: flex;
            flex-direction: column;
            width: 100%;
            background: #fff;
            padding: .625rem  .75rem;
            box-sizing: border-box;
            border-radius: .625rem;
            margin-top: .625rem;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .XExytc-order-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .XExytc-order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: .375rem;
        }

        .XExytc-order-header svg {
            width: 1.5rem;
            height: 1.5rem;
        }

        .XExytc-order-body {
            display: flex;
            align-items: flex-start;
        }

        .XExytc-order-image {
            max-width: 4.25rem;
            max-height: 4.25rem;
            min-height: 4.25rem;
            min-width: 4.25rem;
            object-fit: cover;
            border-radius: .5rem;
            overflow: hidden;
        }

        .XExytc-order-detail {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            width: 100%;
            font-size: .875rem;
            margin-left: .5rem;
            line-height: 1.125rem;
            position: relative;
            height: 100%;
        }

        .XExytc-order-detail button {
            position: relative;
            border: none;
            outline: none;
            padding: .375rem 1rem;
            border-radius: 1.125rem;
            right: 0;
            bottom: 0;
            color: #2d2d2d;
            font-size: .875rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .XExytc-order-detail button:hover {
            transform: scale(1.05);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .XExytc-order-description {
            max-height: 3.375rem;
            overflow: hidden;
            word-break: break-all;
            white-space: break-spaces;
            line-height: 1.125rem;
        }

        .XExytc-order-price {
            font-weight: bold;
            margin-left: .75rem;
        }

        .XExytc-order-price span {
            font-size: 0.6875rem;
        }

        /* 新增的弹窗内部滚动区域样式 */
        .XExytc-orders-list {
            flex: 1;
            overflow-y: auto;
            width: 100%;
        }
    </style>
    <style>
        .XE-1 {
    width: 100%;
    box-sizing: border-box;
    display: flex;
    flex-direction: column;
}
.XE-3.XE-2 .XE-1 {
    margin-right: 0.375rem;
    align-items: flex-end;
}
.XE-4 {
    background: #fff;
    border-radius: .5rem;
    display: flex;
    font-size: .875rem;
    box-sizing: border-box;
}
.XE-4 img {
    max-width: 3.8125rem;
    max-height: 3.8125rem;
    min-width: 3.8125rem;
    min-height: 3.8125rem;
    object-fit: cover;
    margin-right: .5rem;
}
.XE-5 {
    display: flex;
    flex-direction: column;
    width: 10rem;
}
.XE-6 {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    height: 100%;
}
.XE-7 {
    word-wrap: break-word;
    word-break: break-all;
    line-height: 1.25rem;
    max-height: 2.5rem;
    overflow: hidden;
}
.XE-8 span {
    font-size: 0.625rem;
}
    </style>
    <style>
        .xeweigui {
    height: 100%;
    display: flex;
    align-items: center;
    flex-direction: column;
    justify-content: center;
    gap: .75rem;
}
    </style>
    <style>
/* 修改订单详情容器 */
.XExytc-order-detail {
    display: flex;
    flex-direction: column;  /* 改为垂直排列 */
    justify-content: space-between;  /* 两端对齐 */
    width: 100%;
    font-size: .875rem;
    margin-left: .5rem;
    line-height: 1.125rem;
    position: relative;
    min-height: 4.25rem;  /* 与图片高度一致 */
    box-sizing: border-box;
    padding: 0.25rem 0;  /* 添加内边距 */
}

/* 商品描述 */
.XExytc-order-description {
    max-height: 2.5rem;  /* 调整最大高度 */
    overflow: hidden;
    word-break: break-all;
    white-space: break-spaces;
    line-height: 1.125rem;
    margin-bottom: 0.5rem;  /* 添加下边距 */
    display: -webkit-box;
    -webkit-line-clamp: 2;  /* 限制显示2行 */
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* 价格和按钮容器 */
.XExytc-order-detail > .price-button-container {
    display: flex;
    justify-content: space-between;
    align-items: center;
    width: 100%;
    margin-top: auto;  /* 推到底部 */
}

/* 价格样式 */
.XExytc-order-price {
    font-weight: bold;
    display: flex;
    align-items: center;
    font-size: 0.9375rem;
}

.XExytc-order-price span {
    font-size: 0.75rem;
    margin-right: 0.125rem;
}

/* 修改按钮样式，移除绝对定位 */
.XExytc-order-detail button {
    border: none;
    outline: none;
    padding: .375rem 1.25rem;
    border-radius: 1.125rem;
    color: #2d2d2d;
    font-size: .875rem;
    cursor: pointer;
    transition: all 0.2s ease;
    background: rgb(255, 230, 13);
    min-width: 3.5rem;  /* 固定最小宽度 */
    margin-left: 0.5rem;  /* 与价格间隔 */
}
/* 隐藏所有滚动条但保留滚动功能 */
body, .wrapper, .chat-container, .messages-container, .chat-messages,
.footer-buttons, .node-list-container  {
    /* 针对Webkit浏览器（Chrome, Safari, Edge） */
    &::-webkit-scrollbar {
        display: none;
        width: 0;
    }
    
    /* 标准属性 */
    scrollbar-width: none; /* Firefox */
    -ms-overflow-style: none; /* IE/Edge */
}
/* 隐藏所有滚动条但保留滚动功能 */
#app {
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
	  /* 卡片消息样式 */
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
        
        .message-card__button:active {
            transform: translateY(0);
        }
	</style>
</head>
<body> 
    <div class="xile-loading-container" id="loadingContainer"> 
        <div class="xile-loading-spinner"></div> 
        <div class="xile-loading-text">正在连接客服...</div> 
    </div>
    <div id="app">
        <div class="chat-container">
            
            <!-- 聊天内容 -->
            <div class="messages-container" id="chat-container">
           
            </div>
            
            <!-- 输入区域 -->
            <div class="input-area">
                <!-- 快捷话术 -->
                <div class="quick-replies">
                    <div class="quick-replies-container">
                        <?php if ($hasParams): ?>
                        <div class="quick-reply" style="display: flex;align-items: center;gap: 0.375rem;" data-text="咨询交易问题" id="consult-order-btn">
                            <svg viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" width="16" height="16"><path d="M828.2 131.4H702c-12.1-39.1-48.6-67.5-91.7-67.5H415.7c-43.1 0-79.6 28.4-91.7 67.5H196c-37.6 0-68 30.4-68 68v690.9c0 37.6 30.4 68 68 68h632.1c37.6 0 68-30.4 68-68V199.4c0.1-37.5-30.4-68-67.9-68zM513 724.3c0 17.6-14.3 31.9-31.9 31.9h-159c-17.6 0-31.9-14.3-31.9-31.9 0-17.6 14.3-31.9 31.9-31.9h159c17.6 0 31.9 14.3 31.9 31.9z m224.4-177.9c0 17.6-14.3 31.9-31.9 31.9h-385c-17.6 0-31.9-14.3-31.9-31.9 0-17.6 14.3-31.9 31.9-31.9h385.1c17.6 0 31.8 14.3 31.8 31.9z m0-177.9c0 17.6-14.3 31.9-31.9 31.9h-385c-17.6 0-31.9-14.3-31.9-31.9 0-17.6 14.3-31.9 31.9-31.9h385.1c17.6 0 31.8 14.3 31.8 31.9z" p-id="14798" fill="#ffe60d"></path></svg>
                            <div>选择订单</div>
                        </div>
                        <div class="quick-reply active" data-text="发货收货问题" id="consult-order-btn">咨询交易问题问题</div>
                        <div class="quick-reply" data-text="被处罚了如何申诉" id="consult-violation-btn">被处罚了如何申诉</div>
                        <div class="quick-reply" data-text="退款纠纷咨询" id="consult-order-btn">退款纠纷咨询</div>
                        <?php else: ?>
                        <div class="quick-reply active" data-text="发货收货问题" id="consult-order-btn">咨询交易问题</div>
                        <div class="quick-reply" data-text="被处罚了如何申诉" id="consult-violation-btn">被处罚了如何申诉</div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- 输入框和按钮 -->
                <div class="input-row">
                    <!-- 图片上传按钮 -->
                    <label class="upload-button" for="input-image">
                        <svg xmlns="http://www.w3.org/2000/svg" version="1.1" viewBox="0 0 48 48" class="upload-icon">
                            <path d="M37 6.5a7.5 7.5 0 0 1 7.5 7.5v21a7.5 7.5 0 0 1-7.5 7.5H11A7.5 7.5 0 0 1 3.5 35V14A7.5 7.5 0 0 1 11 6.5Zm0 3H11A4.5 4.5 0 0 0 6.5 14v21a4.5 4.5 0 0 0 4.5 4.5h26a4.5 4.5 0 0 0 4.5-4.5V14A4.5 4.5 0 0 0 37 9.5Zm2.493 13.358a1.5 1.5 0 0 1-1.351 1.635c-2.463.234-4.205.896-5.268 1.928-.249.241-.46.526-.696.946l-.183.34-.198.4-.258.55-.252.523-.252.483c-1.083 2.009-2.188 2.909-4.358 3.05-1.272.084-2.44-.05-4.01-.396l-1.97-.464-.614-.137-.284-.06-.528-.1c-1.007-.179-1.747-.218-2.6-.15-1.896.152-3.693 1.547-5.387 4.367a1.5 1.5 0 1 1-2.572-1.546c2.148-3.574 4.72-5.571 7.719-5.811 1.393-.112 2.497-.012 4.176.344l.704.156 1.633.388c1.516.352 2.515.483 3.536.416.944-.062 1.377-.41 2.039-1.713l.196-.4.257-.549c.62-1.322 1.072-2.072 1.81-2.79 1.615-1.567 3.987-2.468 7.074-2.761a1.5 1.5 0 0 1 1.635 1.351ZM14.5 13a4.5 4.5 0 1 1 0 9 4.5 4.5 0 0 1 0-9Zm0 3a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3Z" fill="currentColor"></path>
                        </svg>
                    </label>
                    <input type="file" accept="image/*" style="display: none;" id="input-image">
                    
                    <!-- 消息输入框 -->
                    <input class="message-input" placeholder="在这输入您的问题试试~" enterkeyhint="send" id="message-input">
                    
                    <!-- 发送按钮 -->
                    <button class="send-button" id="send-button">发送</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- 订单选择弹窗 -->
    <div class="XExytc-overlay" id="order-modal-overlay"></div>
    <div class="XExytc-bottom-sheet" id="order-modal">
        <!-- 拖拽指示器 -->
        <div class="XExytc-drag-handle" id="drag-handle"></div>
        
        <div class="XExytc-container">
            <div class="XExytc-header">
                <div>请选择您要咨询的订单</div>
                <div class="XExytc-close" id="close-order-modal">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                    </svg>
                </div>
            </div>
            
            <?php if ($hasParams): ?>
            <!-- 如果链接有参数显示这个 -->
            <div class="XExytc-orders-list">
                <div class="XExytc-order-item">
                    <div class="XExytc-order-header">
                       <svg viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="5668" width="200" height="200"><path d="M952.786158 352.800941c0-0.516042-0.172014-0.860071-0.172014-1.376113-0.344028-1.892155-0.860071-3.612296-1.548127-5.332437L875.895851 159.285066c-13.073072-38.703175-50.22812-62.441122-93.919704-62.613136L255.440954 96.67193c-44.207626 0-78.610449 23.565933-90.995464 60.720981L83.770872 347.984546c-0.344028 1.204099-0.860071 2.752226-1.204099 4.472367-5.84848 18.061482-8.77272 36.466991-8.77272 55.044515 0 65.193348 35.778935 124.71023 93.231648 155.328742 0 0 0 0 0 0s0 0 0 0l0 0c0 0 0 0 0 0 23.565933 12.55703 51.432219 18.921552 82.738787 18.921552 52.464304-0.172014 101.144297-23.221905 134.34302-62.78515 33.026709 39.219217 81.19066 62.097094 133.654964 62.441122 51.948261-0.344028 100.112212-23.393919 132.966907-62.613136 33.198723 39.563245 81.878717 62.441122 134.687049 62.441122 31.994625-0.172014 60.204939-6.880564 84.1149-19.953637 56.592642-30.96254 91.683521-90.135394 91.683521-154.640685C961.386864 388.235847 958.29061 369.658324 952.786158 352.800941zM839.084831 505.377457c-14.449185 7.912649-32.510667 12.040988-53.668402 12.040988-39.047203 0-74.48211-19.781623-95.295817-53.324374-1.548127-3.096254-3.956325-8.428691-8.600706-13.417101-5.332437-5.84848-14.793214-12.901058-30.618512-12.901058-12.729044 0-24.25399 5.160423-30.790526 13.245087-4.300353 4.816395-6.70855 9.63279-8.428691 13.245087-20.469679 33.198723-55.732572 53.15236-93.919704 53.324374-38.703175-0.172014-73.966068-20.125651-94.607761-53.496388-1.376113-2.92424-3.78431-7.912649-7.568621-12.040988-14.965228-17.889467-48.679993-17.029397-61.753066-1.376113-4.816395 5.332437-7.396607 10.664875-8.944734 14.277171-20.641693 33.198723-56.248614 52.980346-94.951789 53.15236-20.641693 0-38.359147-3.956325-52.636318-11.524945l0 0c0 0 0 0 0 0-36.639006-19.437594-59.344868-57.452713-59.344868-98.908114 0-12.213002 2.064169-24.598018 6.192508-36.639006 0.344028-0.860071 0.516042-1.892155 0.688056-2.752226l79.470519-187.839409c1.548127-4.816395 6.536536-19.437594 31.306568-19.437594l526.707206 0c10.148833 0.688056 27.694272 2.408198 33.88678 20.641693l74.310096 185.259197c0.344028 1.376113 0.688056 2.580212 1.032085 3.612296 4.128339 12.040988 6.192508 24.25399 6.192508 36.466991C897.397615 447.924744 875.03578 485.595834 839.084831 505.377457z" fill="#575B66" p-id="5669"></path><path d="M862.994793 607.897867c-17.717453 0-31.994625 14.277171-31.994625 31.994625l0 174.594322c0 9.976818-8.084663 18.061482-18.061482 18.061482l-602.049387 0.344028c-9.976818 0-18.061482-8.084663-18.061482-17.889467l-0.172014-171.498068c0-17.717453-14.449185-31.994625-31.994625-31.994625 0 0 0 0 0 0-17.717453 0-31.994625 14.449185-31.994625 31.994625l0.172014 171.498068c0 45.067697 36.81102 81.878717 82.050731 81.878717l602.221401-0.344028c45.067697-0.172014 81.878717-36.81102 81.878717-82.050731l0-174.594322C894.989417 622.347052 880.712246 607.897867 862.994793 607.897867z" fill="#575B66" p-id="5670"></path><path d="M768.043004 383.935495 255.956996 383.935495c-17.717453 0-31.994625-14.277171-31.994625-31.994625 0-17.717453 14.277171-31.994625 31.994625-31.994625l511.913993 0c17.717453 0 31.994625 14.277171 31.994625 31.994625C800.037628 369.658324 785.588443 383.935495 768.043004 383.935495z" fill="#575B66" p-id="5671"></path></svg>
                        <div style="margin-right: auto; margin-left: 0.1875rem; font-weight: bold;"><?php echo htmlspecialchars($shop ?: 't***2'); ?></div>
                        <div style="font-size: 0.875rem; color: rgb(255, 96, 0);">待发货</div>
                    </div>
                    <div class="XExytc-order-body">
                        <?php if (!empty($url)): ?>
                        <img class="XExytc-order-image" src="<?php echo htmlspecialchars($url); ?>" alt="商品图片">
                        <?php else: ?>
                        <img class="XExytc-order-image" src="https://cy-pic.kuaizhan.com/g3/e4/5d/becb-557a-46c7-a2bd-daecb7fe695762?cysign=0e267a58cd517d077e5bb3ca2c28d09c&amp;cyt=1768733746" alt="默认商品图片">
                        <?php endif; ?>
                        <div class="XExytc-order-detail">
    <div class="XExytc-order-description"><?php echo htmlspecialchars($title ?: '这是一个商品描述，描述这个订单的商品信息，可能会比较长，需要显示省略号'); ?></div>
    <div class="price-button-container">
        <div class="XExytc-order-price"><span>￥</span><?php echo htmlspecialchars($amount ?: '128.00'); ?></div>
        <button class="order-select-btn" data-order-id="<?php echo htmlspecialchars($xe ?: '1234567890'); ?>" data-order-title="<?php echo htmlspecialchars($title ?: '这是一个商品描述'); ?>" data-order-price="<?php echo htmlspecialchars($amount ?: '128.00'); ?>">发送</button>
    </div>
</div>
                    </div>
                </div>
              </div>
            <?php else: ?>
            <!-- 如果链接没有参数显示这个 -->
            <div class="xeweigui">
                <svg t="1758199422805" class="" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="6837" width="64" height="64">
                    <path d="M457 335.8v165.6l397.7 1.1V372.8z" fill="#CBCBCB" p-id="6838"></path>
                    <path d="M457 335.8V485l-321.5-32.7v-46.8z" fill="#B9B9B9" p-id="6839"></path>
                    <path d="M136.6 405.5v432.6l380.3 76.3V442.5z" fill="#D3D3D3" p-id="6840"></path>
                    <path d="M136.6 405.5l-74 160.4 380.3 76.3 74-199.7z" fill="#E9E9E9" p-id="6841"></path>
                    <path d="M855.8 375v432.6L516.9 914.4V442.5z" fill="#C9C9C9" p-id="6842"></path>
                    <path d="M855.8 375l103.5 168.8-338.8 106.8-103.6-208.1z" fill="#DDDDDD" p-id="6843"></path>
                    <path d="M618.8 90.7l53.1-19.5-4.7 89.8-30.6 8.9zM688.5 176.6l17.7 13.6L789 92.6l-61.5-29.2zM752.7 215.9l11.4 31.6 95.3-31.6-10.9-63.2z" fill="#E9E9E9" p-id="6844"></path>
                </svg>
                <div>暂无订单</div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- 违规弹窗 -->
    <div class="XExytc-overlay" id="violation-modal-overlay"></div>
    <div class="XExytc-bottom-sheet" id="violation-modal">
        <!-- 拖拽指示器 -->
        <div class="XExytc-drag-handle" id="violation-drag-handle"></div>
        
        <div class="XExytc-container">
            <div class="XExytc-header">
                <div>违规申诉</div>
                <div class="XExytc-close" id="close-violation-modal">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                    </svg>
                </div>
            </div>
                <div class="xeweigui">
                    <svg viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="8182" width="48" height="48">
                        <path d="M1000.030268 789.082353L614.524386 132.517647c-57.223529-96.376471-147.576471-96.376471-204.8 0L24.218504 789.082353C-33.005026 885.458824 15.183209 963.764706 126.618504 963.764706h771.011764c111.435294 0 159.623529-78.305882 102.4-174.682353zM512.124386 843.294118c-33.129412 0-60.235294-27.105882-60.235294-60.235294s27.105882-60.235294 60.235294-60.235295 60.235294 27.105882 60.235294 60.235295-27.105882 60.235294-60.235294 60.235294z m60.235294-271.058824c0 33.129412-27.105882 60.235294-60.235294 60.235294s-60.235294-27.105882-60.235294-60.235294v-240.941176c0-33.129412 27.105882-60.235294 60.235294-60.235294s60.235294 27.105882 60.235294 60.235294v240.941176z" p-id="8183" fill="#8a8a8a"></path>
                    </svg>
                    <div>暂无违规记录</div>
                </div>
            </div>
        </div>
    
    <!-- 图片预览模态框 -->
    <div class="image-preview-modal" id="image-preview-modal">
        <img class="image-preview-content" id="image-preview-content" src="" alt="预览图片">
    </div>
  <script>
class CustomerChatSystem {
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
        this.isUploadingImage = false;
        
        // 平台标识
        this.platform = '闲鱼';
        
        // 弹窗拖动相关变量
        this.isDragging = false;
        this.startY = 0;
        this.currentY = 0;
        this.modalHeight = 0;
        this.draggingModalType = null;
        
        // 设备信息检测
        this.deviceInfo = this.detectDevice();
        this.pageVisible = true;
        this.lastActivityTime = Date.now();
        
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
        
        // 消息去重集合
        this.recentlySentMessageIds = new Set();
        this.recentlyReceivedWsMessageIds = new Set();
        this._lastSentMessages = [];
        this._sentMessageCounter = 0;
        
        console.log('闲鱼聊天系统初始化:', {
            sessionId: this.sessionId,
            customerName: this.customerName,
            agentAccount: this.agentAccount
        });
        
        console.log('检测到设备信息:', this.deviceInfo);
        
        this.init();
    }
    
    init() {
        this.createWelcomeMessages();
        this.loadInitialMessages();
        this.setupEventListeners();
        this.setupOrderModalEvents();
        this.setupViolationModalEvents();
        this.setupDragEvents();
        this.setupPaymentCardEvents();
        this.startPolling();
        this.startStatusPolling();
        this.updateSendButton();
        this.setupImagePreview();
        this.updateCustomerOnlineStatus();
        
        // 延迟初始化 WebSocket
        setTimeout(() => {
            this.initWebSocket();
        }, 1000);
    }
    
    // 修改setupDragEvents方法，支持两个弹窗的拖动
    setupDragEvents() {
        const self = this;
        
        // 订单弹窗拖动
        const orderDragHandle = document.getElementById('drag-handle');
        const orderModal = document.getElementById('order-modal');
        
        if (orderDragHandle && orderModal) {
            this.setupModalDrag(orderDragHandle, orderModal, 'order');
        }
        
        // 违规弹窗拖动
        const violationDragHandle = document.getElementById('violation-drag-handle');
        const violationModal = document.getElementById('violation-modal');
        
        if (violationDragHandle && violationModal) {
            this.setupModalDrag(violationDragHandle, violationModal, 'violation');
        }
    }
    
    // 设置弹窗拖动的通用方法
    setupModalDrag(dragHandle, modal, modalType) {
        const self = this;
        
        // 触摸开始
        dragHandle.addEventListener('touchstart', function(e) {
            e.preventDefault();
            self.startDragging(e.touches[0].clientY, modalType);
        });
        
        // 触摸移动
        document.addEventListener('touchmove', function(e) {
            if (self.isDragging && self.draggingModalType === modalType) {
                e.preventDefault();
                self.dragging(e.touches[0].clientY, modal);
            }
        });
        
        // 触摸结束
        document.addEventListener('touchend', function(e) {
            if (self.isDragging && self.draggingModalType === modalType) {
                e.preventDefault();
                self.stopDragging(modal);
            }
        });
        
        // 鼠标事件（桌面端）
        dragHandle.addEventListener('mousedown', function(e) {
            e.preventDefault();
            self.startDragging(e.clientY, modalType);
        });
        
        document.addEventListener('mousemove', function(e) {
            if (self.isDragging && self.draggingModalType === modalType) {
                e.preventDefault();
                self.dragging(e.clientY, modal);
            }
        });
        
        document.addEventListener('mouseup', function(e) {
            if (self.isDragging && self.draggingModalType === modalType) {
                e.preventDefault();
                self.stopDragging(modal);
            }
        });
    }
    
    // 开始拖动
    startDragging(startY, modalType) {
        this.isDragging = true;
        this.draggingModalType = modalType;
        this.startY = startY;
        this.currentY = startY;
        $('body').css('user-select', 'none'); // 防止拖动时选中文本
    }
    
    // 拖动中
    dragging(currentY, modal) {
        if (!this.isDragging) return;
        
        const deltaY = currentY - this.startY;
        
        if (deltaY > 0) { // 向下拖动
            const translateY = Math.min(deltaY, 100); // 限制最大拖动距离
            modal.style.transform = `translateY(${translateY}px)`;
        }
    }
    
    // 停止拖动
    stopDragging(modal) {
        if (!this.isDragging) return;
        
        this.isDragging = false;
        $('body').css('user-select', 'auto');
        
        const currentTransform = window.getComputedStyle(modal).transform;
        const matrix = new DOMMatrixReadOnly(currentTransform);
        const currentTranslateY = matrix.m42;
        
        // 如果拖动距离超过50px，关闭弹窗
        if (currentTranslateY > 50) {
            if (this.draggingModalType === 'order') {
                this.hideOrderModalWithAnimation();
            } else if (this.draggingModalType === 'violation') {
                this.hideViolationModalWithAnimation();
            }
        } else {
            // 否则回到原位
            modal.style.transform = 'translateY(0)';
        }
        
        this.draggingModalType = null;
    }
    
    // 修改setupOrderModalEvents方法
    setupOrderModalEvents() {
        const self = this;
        
        // 打开订单弹窗的通用函数
        const openOrderModal = function(e) {
            e.preventDefault();
            e.stopPropagation();
            self.showOrderModalWithAnimation();
        };
        
        // 绑定所有"选择订单"按钮
        $('#consult-order-btn').on('click', openOrderModal);
        
        // 绑定欢迎消息中的"选择订单"按钮
        $('#welcome-order-btn').on('click', openOrderModal);
        
        // 绑定特殊消息中的按钮
        $('body').on('click', '.special-message .order-button', openOrderModal);
        
        // 绑定快捷话术中的特定按钮
        $('body').on('click', '.quick-reply[data-text="咨询交易问题"]', openOrderModal);
        $('body').on('click', '.quick-reply[data-text="发货收货问题"]', openOrderModal);
        $('body').on('click', '.quick-reply[data-text="退款纠纷咨询"]', openOrderModal);
        
        // 绑定特殊消息中的"对订单有疑问？"和"咨询交易问题？"
        $('body').on('click', '.special-message .quick-question', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const text = $(this).text();
            
            // 检查是否是订单相关的问题
            if (text.includes('订单') || text.includes('咨询交易问题')) {
                self.showOrderModalWithAnimation();
            } else {
                // 其他问题直接填充到输入框
                $('#message-input').val(text);
                self.updateSendButton();
                self.autoResizeTextarea();
                $('#message-input').focus();
            }
        });
        
        // 关闭订单弹窗
        $('#close-order-modal').on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            self.hideOrderModalWithAnimation();
        });
        
        // 点击订单弹窗遮罩层关闭弹窗
        $('#order-modal-overlay').on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            if (e.target === this) {
                self.hideOrderModalWithAnimation();
            }
        });
        
        // 订单选择按钮点击事件
        $('#order-modal').on('click', '.order-select-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
            
            console.log('订单发送按钮被点击');
            
            const orderId = $(this).data('order-id');
            const orderTitle = $(this).data('order-title');
            const orderPrice = $(this).data('order-price');
            
            if (!orderId || !orderTitle || !orderPrice) {
                console.error('订单数据不完整:', {orderId, orderTitle, orderPrice});
                return;
            }
            
            self.selectOrder(orderId, orderTitle, orderPrice);
        });
        
        // 阻止弹窗内点击事件冒泡
        $('#order-modal').on('click', function(e) {
            e.stopPropagation();
        });
    }
    
    // 设置违规弹窗事件
    setupViolationModalEvents() {
        const self = this;
        
        // 打开违规弹窗的通用函数
        const openViolationModal = function(e) {
            e.preventDefault();
            e.stopPropagation();
            self.showViolationModalWithAnimation();
        };
        
        // 绑定"被处罚了如何申诉"按钮
        $('#consult-violation-btn').on('click', openViolationModal);
        
        // 绑定特殊消息中的"违规如何申诉？"
        $('body').on('click', '.special-message .quick-question', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const text = $(this).text();
            
            if (text.includes('违规如何申诉')) {
                self.showViolationModalWithAnimation();
            }
        });
        
        // 关闭违规弹窗
        $('#close-violation-modal').on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            self.hideViolationModalWithAnimation();
        });
        
        // 点击违规弹窗遮罩层关闭弹窗
        $('#violation-modal-overlay').on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            if (e.target === this) {
                self.hideViolationModalWithAnimation();
            }
        });
        
        // 在线申诉按钮
        $('#violation-appeal-btn').on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            alert('将跳转到在线申诉页面...');
            // 这里可以添加跳转到申诉页面的逻辑
            self.hideViolationModalWithAnimation();
        });
        
        // 阻止违规弹窗内点击事件冒泡
        $('#violation-modal').on('click', function(e) {
            e.stopPropagation();
        });
    }
    
    // 支付卡片事件绑定
    setupPaymentCardEvents() {
        const self = this;
        
        $(document).on('click', '.pay-button', function(e) {
            e.preventDefault();
            const orderId = $(this).data('order-id');
            self.handlePayment(orderId);
        });
        
        $(document).on('click', '.detail-button', function(e) {
            e.preventDefault();
            const orderId = $(this).data('order-id');
            self.viewOrderDetails(orderId);
        });
        
        // 重试加载按钮
        $(document).on('click', '.retry-button', function(e) {
            e.preventDefault();
            const orderId = $(this).data('order-id');
            self.retryLoadPaymentCard(orderId);
        });
        
        // 代付卡片事件
        $(document).on('click', '.xedf-pay-button', function(e) {
            e.preventDefault();
            const orderId = $(this).data('order-id');
            self.handleXEDFPayment(orderId);
        });
        
        $(document).on('click', '.xedf-detail-button', function(e) {
            e.preventDefault();
            const orderId = $(this).data('order-id');
            self.viewXEDFDetails(orderId);
        });
        
        $(document).on('click', '.xedf-retry-button', function(e) {
            e.preventDefault();
            const orderId = $(this).data('order-id');
            self.retryLoadXEDFCard(orderId);
        });
        
        // 实名卡片事件
        $(document).on('click', '.xekk-start-btn', function(e) {
            e.preventDefault();
            const verifyCode = $(this).data('verify-code');
            self.startXEKKVerification(verifyCode);
        });
        
        $(document).on('click', '.xekk-detail-btn', function(e) {
            e.preventDefault();
            const verifyCode = $(this).data('verify-code');
            self.viewXEKKDetails(verifyCode);
        });
        
        $(document).on('click', '.xekk-retry-button', function(e) {
            e.preventDefault();
            const verifyCode = $(this).data('verify-code');
            self.retryLoadXEKKCard(verifyCode);
        });
    }
    
    // 显示订单弹窗（带动画）
    showOrderModalWithAnimation() {
        const overlay = $('#order-modal-overlay');
        const modal = $('#order-modal');
        
        // 显示遮罩层
        overlay.addClass('active');
        
        // 下一帧显示弹窗
        requestAnimationFrame(() => {
            modal.addClass('active');
            
            // 添加打开动画后的回调
            setTimeout(() => {
                modal.css('transition', 'transform 0.3s cubic-bezier(0.25, 0.8, 0.25, 1)');
            }, 10);
        });
        
        $('body').css('overflow', 'hidden');
    }
    
    // 隐藏订单弹窗（带动画）
    hideOrderModalWithAnimation() {
        const overlay = $('#order-modal-overlay');
        const modal = $('#order-modal');
        
        // 移除弹窗的active类，触发关闭动画
        modal.removeClass('active');
        
        // 等待弹窗动画完成后隐藏遮罩层
        setTimeout(() => {
            overlay.removeClass('active');
            $('body').css('overflow', '');
        }, 300); // 与CSS过渡时间匹配
    }
    
    // 显示违规弹窗（带动画）
    showViolationModalWithAnimation() {
        const overlay = $('#violation-modal-overlay');
        const modal = $('#violation-modal');
        
        // 显示遮罩层
        overlay.addClass('active');
        
        // 下一帧显示弹窗
        requestAnimationFrame(() => {
            modal.addClass('active');
            
            // 添加打开动画后的回调
            setTimeout(() => {
                modal.css('transition', 'transform 0.3s cubic-bezier(0.25, 0.8, 0.25, 1)');
            }, 10);
        });
        
        $('body').css('overflow', 'hidden');
    }
    
    // 隐藏违规弹窗（带动画）
    hideViolationModalWithAnimation() {
        const overlay = $('#violation-modal-overlay');
        const modal = $('#violation-modal');
        
        // 移除弹窗的active类，触发关闭动画
        modal.removeClass('active');
        
        // 等待弹窗动画完成后隐藏遮罩层
        setTimeout(() => {
            overlay.removeClass('active');
            $('body').css('overflow', '');
        }, 300); // 与CSS过渡时间匹配
    }
    
    selectOrder(orderId, orderTitle, orderPrice, orderStatus = '待发货') {
        console.log('selectOrder函数被调用:', {orderId, orderTitle, orderPrice, orderStatus});
        
        // 先关闭弹窗
        this.hideOrderModalWithAnimation();
        
        // 延迟发送消息，让弹窗关闭动画完成
        setTimeout(() => {
            console.log('开始创建订单卡片');
            // 创建订单卡片HTML
            const orderCardHtml = this.createOrderCardHtml(orderId, orderTitle, orderPrice, orderStatus);
            console.log('订单卡片HTML:', orderCardHtml);
            
            // 添加订单卡片到聊天区域
            this.appendOrderCard(orderCardHtml);
            
            this.scrollToBottom();
            
            // 发送到服务器
            console.log('开始发送订单消息到服务器');
            this.sendOrderMessage(orderId, orderTitle, orderPrice, orderStatus, orderCardHtml);
        }, 300);
    }
    
    // 检测 XECARD 自定义卡片消息
    isXECardMessage(message) {
        if (!message.content || typeof message.content !== 'string') {
            return false;
        }
        return message.content.startsWith('XECARD#') && message.content.length > 7;
    }
    
    // 检测 XEXYCARD 订单卡片消息
    isXYDLCardMessage(message) {
        if (!message.content || typeof message.content !== 'string') {
            return false;
        }
        return message.content.startsWith('XEXYCARD#') && message.content.length > 10;
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
            this.appendMessages([message]);
        }
    }
    
    // 处理 XEXYCARD 订单卡片消息
    handleXYDLCardMessage(message) {
        try {
            if (!message.content || typeof message.content !== 'string') {
                console.error('XEXYCARD 消息内容无效:', message);
                return;
            }
            
            const prefix = 'XEXYCARD#';
            const startIndex = message.content.indexOf(prefix);
            if (startIndex === -1) {
                console.error('XEXYCARD 消息不包含正确的前缀:', message.content);
                return;
            }
            
            const jsonStr = message.content.substring(startIndex + prefix.length);
            console.log('XEXYCARD JSON 字符串:', jsonStr);
            
            const cardData = JSON.parse(jsonStr);
            console.log('检测到 XEXYCARD 订单卡片消息:', cardData);
            this.showXYDLCard(cardData, message);
        } catch (error) {
            console.error('解析 XEXYCARD 卡片数据失败:', error);
            console.error('原始消息内容:', message.content);
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
        
        // 如果有 actions 数组
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
                    <div class="avatar">
                        <img src="/assets/img/xy-kf.png" alt="">
                    </div>
                    <div class="message-content">
                        <div class="sender-name">闲小蜜</div>
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
    
    // 显示 XEXYCARD 订单卡片（使用统一的 XE 样式）
    showXYDLCard(cardData, originalMessage) {
        const container = $('#chat-container');
        const title = cardData.title || '订单信息';
        const amount = cardData.rmb || cardData.amount || '0.00';
        const imgUrl = cardData.img || '';
        const status = cardData.status || '待发货';
        
        // 使用统一的 XE 样式
        const cardHtml = `
            <div class="message customer" data-message-id="${originalMessage?.id || Date.now()}">
                <div class="message-content">
                    <div class="message-bubble">
                        <div class="XE-1" style="--avatarMainGap: 0.375rem;">
                            <div class="XE-4">
                                <img src="${imgUrl}" alt="商品图片">
                                <div class="XE-5">
                                    <div class="XE-6">
                                        <div class="XE-7">${title}</div>
                                        <div class="XE-8" style="font-weight: bold;"><span>￥</span>${amount}</div>
                                    </div>
                                    <div style="color: rgb(255, 96, 0); text-align: right;">${status}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        container.append(cardHtml);
        this.scrollToBottom();
    }
    
    // 辅助函数：获取支付方式文本
    getPaymentMethodText(method) {
        const methodMap = {
            'alipay': '支付宝',
            'wechat': '微信支付',
            'unionpay': '银联支付'
        };
        return methodMap[method] || method;
    }
    
    // 辅助函数：获取状态文本
    getStatusText(status) {
        const statusMap = {
            'active': '可用',
            'inactive': '不可用',
            'pending': '待支付',
            'paid': '已支付',
            'failed': '支付失败'
        };
        return statusMap[status] || status;
    }
    
    // 辅助函数：获取代付状态文本
    getXEDFStatusText(status) {
        const statusMap = {
            'active': '可用',
            'inactive': '不可用',
            'pending': '待支付',
            'paid': '已支付',
            'failed': '支付失败'
        };
        return statusMap[status] || status;
    }
    
    // 辅助函数：获取实名验证状态文本
    getXEKKStatusText(status) {
        const statusMap = {
            'active': '待验证',
            'inactive': '已关闭',
            'pending': '验证中',
            'completed': '已完成',
            'failed': '验证失败'
        };
        return statusMap[status] || status;
    }
    
    createOrderCardHtml(orderId, orderTitle, orderPrice, orderStatus) {
        // 获取URL参数中的图片URL
        const urlParams = new URLSearchParams(window.location.search);
        const imgUrl = urlParams.get('img') || '';
        
        return `
            <div class="XE-1" style="--avatarMainGap: 0.375rem;">
                <div class="XE-4">
                    <img src="${imgUrl}" alt="商品图片">
                    <div class="XE-5">
                        <div class="XE-6">
                            <div class="XE-7">${orderTitle}</div>
                            <div class="XE-8" style="font-weight: bold;"><span>￥</span>${orderPrice}</div>
                        </div>
                        <div style="color: rgb(255, 96, 0); text-align: right;">${orderStatus}</div>
                    </div>
                </div>
            </div>
        `;
    }
    
    // 添加订单卡片到聊天区域的函数
    appendOrderCard(orderCardHtml, messageId) {
        const container = $('#chat-container');
        
        const messageHtml = `
            <div class="message customer" data-message-id="${messageId || 'temp_' + Date.now()}">
                <div class="message-content">
                    <div class="message-bubble">
                        ${orderCardHtml}
                    </div>
                </div>
            </div>
        `;
        
        container.append(messageHtml);
    }
    
    // 发送订单消息到服务器（使用 XEXYCARD# 格式）
    sendOrderMessage(orderId, orderTitle, orderPrice, orderStatus, orderCardHtml) {
        const self = this;
        
        this.isSending = true;
        this.updateSendButton();
        
        // 获取URL参数中的图片URL
        const urlParams = new URLSearchParams(window.location.search);
        const imgUrl = urlParams.get('img') || '';
        
        // 创建 XEXYCARD# 格式的消息内容
        const cardData = {
            title: orderTitle || '订单信息',
            amount: orderPrice,
            rmb: orderPrice,
            img: imgUrl,
            phone: '',
            time: new Date().toLocaleString('zh-CN'),
            order_id: orderId,
            status: orderStatus,
            type: 'order_submit'
        };
        
        const messageContent = `XEXYCARD#${JSON.stringify(cardData)}`;

        this._sentMessageCounter++;
        const tempMessageId = 'temp_order_' + Date.now() + '_' + this._sentMessageCounter;

        // 记录已发送订单消息，用于去重
        this._lastSentMessages.push({
            tempId: tempMessageId,
            content: messageContent,
            speaker_type: 1,
            messageType: 'card',
            timestamp: Date.now()
        });
        if (this._lastSentMessages.length > 20) {
            this._lastSentMessages.shift();
        }

        $.ajax({
            url: this.apiBaseUrl,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                action: 'send_message',
                session_id: this.sessionId,
                agent_account: this.agentAccount,
                speaker_type: 1,
                content: messageContent,
                customer_name: this.customerName,
                platform: this.platform
            }),
            success: function(data) {
                console.log('发送订单响应:', data);
                self.isSending = false;
                self.updateSendButton();
                
                if (data.success && data.message_id) {
                    self.lastMessageId = Math.max(self.lastMessageId, data.message_id);
                    console.log('订单消息发送成功，message_id:', data.message_id);
                    
                    // 发送成功后立即轮询获取新消息，确保卡片正确显示
                    setTimeout(() => {
                        self.checkNewMessages();
                    }, 300);
                } else {
                    console.error('发送订单失败:', data.message);
                }
            },
            error: function(xhr, status, error) {
                console.error('发送订单消息失败:', error);
                self.isSending = false;
                self.updateSendButton();
                // 可以显示一个错误提示
            }
        });
    }
    
    // 以下为原有方法的保持不变
    getSessionId() {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get('id') || 'default_testadmin';
    }
    
    getCustomerName() {
        const sessionId = this.getSessionId();
        if (sessionId.includes('-')) {
            const parts = sessionId.split('-');
            const customerPart = parts[0];
            return customerPart.substring(1, customerPart.length - 1);
        } else if (sessionId.includes('_')) {
            const parts = sessionId.split('_');
            return parts[0];
        }
        return 'default';
    }
    
    getAgentAccount() {
        const sessionId = this.getSessionId();
        if (sessionId.includes('-')) {
            const parts = sessionId.split('-');
            const agentPart = parts[1];
            return agentPart.substring(1, agentPart.length - 1);
        } else if (sessionId.includes('_')) {
            const parts = sessionId.split('_');
            return parts[1];
        }
        return 'testadmin';
    }
    
    createWelcomeMessages() {
        const container = $('#chat-container');
        
        container.append(`
            <div class="welcome-message">
                <div>智能客服24小时在线</div>
                <div>人工客服09:00-24:00提供服务</div>
            </div>
        `);
        
        container.append(`
            <div class="message agent">
                <div class="avatar">
                    <img src="/assets/img/xy-kf.png">
                </div>
                <div class="message-content">
                    <div class="sender-name">闲小蜜</div>
                    <div class="special-message">
                        <div style="margin-bottom: 6px;">Hi, 智能客服闲小蜜为你服务~</div>
                        <div>如有<span class="highlight-text">发货收货、退款纠纷、售后评价</span>相关问题，请选择具体订单，以便闲小蜜为你更好解答哦！</div>
                        <div class="order-button" id="welcome-order-btn">选择订单</div>
                        <div class="quick-question">违规如何申诉？</div>
                        <div class="quick-question">对订单有疑问？</div>
                        <div class="quick-question">咨询交易问题？</div>
                    </div>
                </div>
            </div>
        `);
    }
    
    setupEventListeners() {
        const self = this;
        
        $('#message-input').on('keypress', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                self.sendMessage();
            }
        });
        
        $('#message-input').on('input', function() {
            self.updateSendButton();
            self.autoResizeTextarea();
        });
        
        $('#send-button').on('click', function() {
            self.sendMessage();
        });
        
        // 修改快捷回复点击事件
        $('body').on('click', '.quick-reply', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const button = $(this);
            const text = button.data('text');
            
            // 检查这个按钮是否已经绑定打开弹窗事件
            if (text === '咨询交易问题' || text === '发货收货问题' || text === '退款纠纷咨询') {
                // 这些按钮已经在setupOrderModalEvents中绑定打开订单弹窗
                return;
            } else if (text === '被处罚了如何申诉') {
                // 这个按钮已经在setupViolationModalEvents中绑定打开违规弹窗
                return;
            }
            
            // 其他按钮填充文本
            $('#message-input').val(text);
            self.updateSendButton();
            self.autoResizeTextarea();
            $('#message-input').focus();
        });
        
        $('#input-image').on('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                self.uploadImage(file);
            }
            $(this).val('');
        });
        
        $(document).on('visibilitychange', function() {
            if (!document.hidden) {
                self.checkNewMessages();
                self.updateCustomerOnlineStatus();
            }
        });
        
        $(window).on('beforeunload', function() {
            self.setCustomerOffline();
        });
        
        // ESC键关闭弹窗
        $(document).on('keyup', function(e) {
            if (e.key === 'Escape') {
                if ($('#order-modal-overlay').hasClass('active')) {
                    self.hideOrderModalWithAnimation();
                } else if ($('#violation-modal-overlay').hasClass('active')) {
                    self.hideViolationModalWithAnimation();
                }
            }
        });
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
        setTimeout(() => {
            $('#image-preview-content').attr('src', '');
            $('body').css('overflow', '');
        }, 300);
    }
    
    autoResizeTextarea() {
        const textarea = $('#message-input')[0];
        textarea.style.height = 'auto';
        textarea.style.height = Math.min(textarea.scrollHeight, 120) + 'px';
    }
    
    updateSendButton() {
        const input = $('#message-input');
        const sendButton = $('#send-button');
        const hasText = input.val().trim().length > 0;
        const isSending = this.isSending || this.isUploadingImage;
        
        sendButton.prop('disabled', !hasText || isSending);
    }
    
    startStatusPolling() {
        const self = this;
        
        this.statusPollingInterval = setInterval(function() {
            self.updateCustomerOnlineStatus();
        }, 30000);
        
        console.log('客户在线状态轮询已启动');
    }
    
    updateCustomerOnlineStatus() {
        const self = this;
        
        console.log('更新客户在线状态:', this.customerName);
        
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
                console.log('客户在线状态更新成功:', data);
                self.isOnline = true;
            },
            error: function(xhr, status, error) {
                console.error('更新客户在线状态失败:', error);
                self.isOnline = false;
            }
        });
    }
    
    setCustomerOffline() {
        console.log('设置客户为离线状态:', this.customerName);
        
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
        
        console.log('加载初始消息，sessionId:', this.sessionId);
        
        $.get(`${this.apiBaseUrl}?action=get_messages&session_id=${encodeURIComponent(this.sessionId)}`)
            .done(function(data) {
                console.log('加载消息响应:', data);
                if (data.success && data.messages && data.messages.length > 0) {
                    // 处理所有消息
                    self.processMessages(data.messages);
                    
                    const messageIds = data.messages.map(msg => msg.id);
                    self.lastMessageId = Math.max(...messageIds);
                    self.scrollToBottom();
                } else {
                    console.log('没有历史消息或加载失败');
                }
            })
            .fail(function(xhr, status, error) {
                console.error('加载初始消息失败:', error);
            });
    }
    
    startPolling() {
        const self = this;
        
        this.pollingInterval = setInterval(function() {
            self.checkNewMessages();
        }, 1000);
        
        console.log('消息轮询已启动');
    }
    
    // 修改checkNewMessages方法，确保正确处理所有类型的卡片消息
   checkNewMessages() {
        const self = this;
        
        $.get(`${this.apiBaseUrl}?action=poll_messages&session_id=${encodeURIComponent(this.sessionId)}&last_id=${this.lastMessageId}`)
            .done(function(data) {
                if (data.success && data.messages && data.messages.length > 0) {
                    console.log('收到新消息:', data.messages);

                    // 过滤掉自己刚发送的消息（去重）
                    const now = Date.now();
                    const filteredMessages = data.messages.filter(msg => {
                        if (msg.speaker_type === 1) {
                            const isRecentlySent = self._lastSentMessages.some(sent => {
                                if (sent.messageType === 'image' && msg.message_type === 'image') {
                                    return (now - sent.timestamp) < 5000;
                                }
                                if (sent.messageType === 'card' && self.isXYDLCardMessage(msg)) {
                                    return (now - sent.timestamp) < 5000;
                                }
                                if (sent.content === msg.content && (now - sent.timestamp) < 5000) {
                                    return true;
                                }
                                return false;
                            });
                            if (isRecentlySent) {
                                console.log('跳过重复消息(自己发送):', msg.id, msg.content);
                                return false;
                            }
                        }
                        return true;
                    });

                    // 清理过期的去重记录
                    self._lastSentMessages = self._lastSentMessages.filter(sent =>
                        (now - sent.timestamp) < 10000
                    );

                    // 过滤重复消息
                    const uniqueMessages = self.filterDuplicateMessages(filteredMessages);
                    
                    if (uniqueMessages.length === 0) {
                        console.log('没有新的唯一消息');
                        return;
                    }
                    
                    console.log('过滤后唯一消息数量:', uniqueMessages.length);
                    
                    // 使用统一的处理函数
                    self.processMessages(uniqueMessages);
                    
                    // 更新最后消息ID
                    const allMessageIds = uniqueMessages.map(msg => msg.id);
                    self.lastMessageId = Math.max(...allMessageIds);
                    
                    // 播放新消息提示音
                    const hasNewMessage = uniqueMessages.some(msg => 
                        msg.speaker_type === 2 && 
                        !self.isXECardMessage(msg) &&
                        !self.isXYDLCardMessage(msg)
                    );
                    if (hasNewMessage) {
                        self.playNotificationSound();
                    }
                    
                    self.scrollToBottom();
                }
            })
            .fail(function(xhr, status, error) {
                console.log('轮询错误:', status, error);
            });
    }
    
     // 新增：过滤重复消息的方法
    filterDuplicateMessages(messages) {
        const now = Date.now();
        const uniqueMessages = [];
        
        messages.forEach(message => {
            const messageId = message.id;
            
            // 检查是否已处理过（使用新的去重集合）
            if (this.recentlyReceivedWsMessageIds.has(messageId)) {
                console.log(`消息 ${messageId} 已处理，跳过`);
                return;
            }
            
            // 添加到已处理集合
            this.recentlyReceivedWsMessageIds.add(messageId);
            uniqueMessages.push(message);
        });
        
        return uniqueMessages;
    }
    
    // 新增：统一的消息处理函数
    processMessages(messages) {
        const container = $('#chat-container');
        
        messages.forEach(message => {
            // 1. XECARD 自定义卡片消息
            if (this.isXECardMessage(message)) {
                this.handleXECardMessage(message);
                return;
            }
            
            // 2. XYDLCARD 订单卡片消息
            if (this.isXYDLCardMessage(message)) {
                this.handleXYDLCardMessage(message);
                return;
            }
            
            // 3. 订单卡片消息
            if (message.order_data || message.order_html) {
                this.displayOrderMessage(message);
                return;
            }
            
            // 5. 普通消息
            this.displayNormalMessage(message);
        });
    }
    
    // 新增：显示普通消息的方法
    displayNormalMessage(message) {
        const container = $('#chat-container');
        let messageHtml;
        
        if (message.speaker_type === 1) {
            // 客户消息
            if (message.message_type === 'image' && (message.image_url || message.image_path)) {
                const imageUrl = message.image_url || (`../uploads/${message.image_path}`);
                messageHtml = `
                    <div class="message customer" data-message-id="${message.id}">
                        <div class="message-content">
                            <div class="message-bubble customer">
                                <img class="message-image" src="${imageUrl}" alt="图片">
                            </div>
                        </div>
                    </div>
                `;
            } else {
                messageHtml = `
                    <div class="message customer" data-message-id="${message.id}">
                        <div class="message-content">
                            <div class="message-bubble customer">${this.escapeHtml(message.content)}</div>
                        </div>
                    </div>
                `;
            }
        } else {
            // 客服消息
            if (message.message_type === 'image' && (message.image_url || message.image_path)) {
                const imageUrl = message.image_url || (`../uploads/${message.image_path}`);
                messageHtml = `
                    <div class="message agent" data-message-id="${message.id}">
                        <div class="avatar">
                            <img src="/assets/img/xy-kf.png">
                        </div>
                        <div class="message-content">
                            <div class="sender-name">闲小蜜</div>
                            <div class="message-bubble">
                                <img class="message-image" src="${imageUrl}" alt="图片">
                            </div>
                        </div>
                    </div>
                `;
            } else {
                messageHtml = `
                    <div class="message agent" data-message-id="${message.id}">
                        <div class="avatar">
                            <img src="/assets/img/xy-kf.png">
                        </div>
                        <div class="message-content">
                            <div class="sender-name">闲小蜜</div>
                            <div class="message-bubble">${this.escapeHtml(message.content)}</div>
                        </div>
                    </div>
                `;
            }
        }
        
        // 检查是否已存在相同ID的消息
        if (!$(container).find(`[data-message-id="${message.id}"]`).length) {
            container.append(messageHtml);
        } else {
            console.log(`消息 ${message.id} 已存在，跳过显示`);
        }
    }
    // 修改appendMessages方法，添加订单消息的处理
      appendMessages(messages) {
        // 现在这个方法只在特定的地方使用
        this.processMessages(messages);
    }
    
    // 新增：显示订单消息的方法
    displayOrderMessage(message) {
        const container = $('#chat-container');
        
        // 检查是否已存在
        if (message.id && $(container).find(`[data-message-id="${message.id}"]`).length) {
            console.log(`订单消息 ${message.id} 已存在，跳过显示`);
            return;
        }
        
        // 如果数据库中有存储HTML，直接使用
        if (message.order_html) {
            const messageHtml = `
                <div class="message customer" data-message-id="${message.id || ''}">
                    <div class="message-content">
                        <div class="message-bubble">
                            ${message.order_html}
                        </div>
                    </div>
                </div>
            `;
            container.append(messageHtml);
        } 
        // 如果有订单数据，但没HTML，则重新生成
        else if (message.order_data) {
            const orderData = message.order_data;
            const orderCardHtml = this.createOrderCardHtml(
                orderData.order_id,
                orderData.order_title,
                orderData.order_price,
                orderData.order_status || '待发货'
            );
            
            const messageHtml = `
                <div class="message customer" data-message-id="${message.id || ''}">
                    <div class="message-content">
                        <div class="message-bubble">
                            ${orderCardHtml}
                        </div>
                    </div>
                </div>
            `;
            container.append(messageHtml);
        } 
        // 如果只有文本内容"[订单]"，就显示默认的订单卡片
        else if (message.content === '[订单]') {
            const defaultCardHtml = `
                <div class="XE-1" style="--avatarMainGap: 0.375rem;">
                    <div class="XE-4">
                        <img src="/assets/img/xy-kf.png" alt="商品图片">
                        <div class="XE-5">
                            <div class="XE-6">
                                <div class="XE-7">订单已选择</div>
                                <div class="XE-8" style="font-weight: bold;">订单详情已发送</div>
                            </div>
                            <div style="color: rgb(255, 96, 0); text-align: right;">待发货</div>
                        </div>
                    </div>
                </div>
            `;
            
            const messageHtml = `
                <div class="message customer" data-message-id="${message.id || ''}">
                    <div class="message-content">
                        <div class="message-bubble">
                            ${defaultCardHtml}
                        </div>
                    </div>
                </div>
            `;
            container.append(messageHtml);
        }
    }
    
    escapeHtml(unsafe) {
        return unsafe
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }
    
    // 设备检测
    detectDevice() {
        const userAgent = navigator.userAgent;
        const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(userAgent);
        const isDesktop = !isMobile;
        
        let browser = 'unknown';
        if (userAgent.includes('Chrome')) browser = 'Chrome';
        else if (userAgent.includes('Firefox')) browser = 'Firefox';
        else if (userAgent.includes('Safari') && !userAgent.includes('Chrome')) browser = 'Safari';
        else if (userAgent.includes('Edge')) browser = 'Edge';
        else if (userAgent.includes('Opera') || userAgent.includes('OPR')) browser = 'Opera';
        
        let os = 'unknown';
        if (userAgent.includes('Windows')) os = 'Windows';
        else if (userAgent.includes('Mac OS')) os = 'MacOS';
        else if (userAgent.includes('Linux') && !userAgent.includes('Android')) os = 'Linux';
        else if (userAgent.includes('Android')) os = 'Android';
        else if (userAgent.includes('iPhone') || userAgent.includes('iPad') || userAgent.includes('iPod')) os = 'iOS';
        
        return {
            type: isMobile ? 'mobile' : 'desktop',
            isMobile,
            isDesktop,
            browser,
            os,
            userAgent
        };
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
        const reader = new FileReader();
        
        reader.onload = function(e) {
            const imageData = e.target.result;
            
            self._sentMessageCounter++;
            const tempMessageId = 'temp_img_' + Date.now() + '_' + self._sentMessageCounter;
            self.appendMessages([{
                id: tempMessageId,
                agent_account: self.agentAccount,
                speaker_type: 1,
                content: '[图片]',
                customer_name: self.customerName,
                message_type: 'image',
                image_url: imageData,
                image_name: file.name,
                image_path: 'temp_' + tempMessageId,
                remark: '',
                created_at: new Date().toISOString()
            }]);

            // 记录已发送图片消息，用于去重
            self._lastSentMessages.push({
                tempId: tempMessageId,
                content: '[图片]',
                speaker_type: 1,
                messageType: 'image',
                timestamp: Date.now()
            });
            if (self._lastSentMessages.length > 20) {
                self._lastSentMessages.shift();
            }
            
            self.scrollToBottom();
            
            $.ajax({
                url: self.apiBaseUrl,
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({
                    action: 'upload_image',
                    session_id: self.sessionId,
                    agent_account: self.agentAccount,
                    customer_name: self.customerName,
                    image_data: imageData,
                    image_name: file.name,
                    image_size: file.size,
                    // 传递平台标识
                    platform: self.platform
                }),
                success: function(data) {
                    self.isUploadingImage = false;
                    self.updateSendButton();
                    
                    if (data.success) {
                        console.log('图片上传成功:', data.message_id);
                        self.lastMessageId = Math.max(self.lastMessageId, data.message_id);
                    } else {
                        console.error('图片上传失败:', data.message);
                        alert('图片上传失败: ' + data.message);
                    }
                },
                error: function(xhr, status, error) {
                    self.isUploadingImage = false;
                    self.updateSendButton();
                    console.error('图片上传请求失败:', error);
                    alert('图片上传失败，请重试');
                }
            });
        };
        
        reader.onerror = function() {
            self.isUploadingImage = false;
            self.updateSendButton();
            alert('图片读取失败，请重试');
        };
        
        reader.readAsDataURL(file);
    }
    
    sendMessage() {
        if (this.isSending) {
            return;
        }
        
        const input = $('#message-input');
        const content = input.val().trim();
        
        if (!content) return;
        
        this.isSending = true;
        this.updateSendButton();
        
        const self = this;
        
        console.log('发送消息:', content);
        
        this._sentMessageCounter++;
        const tempId = 'temp_' + Date.now() + '_' + this._sentMessageCounter;

        this.appendMessages([{
            id: tempId,
            agent_account: this.agentAccount,
            speaker_type: 1,
            content: content,
            customer_name: this.customerName,
            remark: '',
            created_at: new Date().toISOString()
        }]);

        // 记录已发送消息，用于去重
        this._lastSentMessages.push({
            tempId: tempId,
            content: content,
            speaker_type: 1,
            timestamp: Date.now()
        });
        // 只保留最近 20 条
        if (this._lastSentMessages.length > 20) {
            this._lastSentMessages.shift();
        }
        
        input.val('');
        this.updateSendButton();
        this.autoResizeTextarea();
        this.scrollToBottom();
        
        this.updateCustomerOnlineStatus();
        
        $.ajax({
            url: this.apiBaseUrl,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                action: 'send_message',
                session_id: this.sessionId,
                agent_account: this.agentAccount,
                speaker_type: 1,
                content: content,
                customer_name: this.customerName,
                // 传递平台标识
                platform: this.platform
            }),
            success: function(data) {
                console.log('发送响应:', data);
                self.isSending = false;
                self.updateSendButton();
                
                if (data.success && data.message_id) {
                    self.lastMessageId = Math.max(self.lastMessageId, data.message_id);
                } else {
                    console.error('发送失败:', data.message);
                    alert('发送失败: ' + (data.message || '未知错误'));
                }
            },
            error: function(xhr, status, error) {
                console.error('发送消息失败:', error);
                self.isSending = false;
                self.updateSendButton();
                alert('发送失败，请检查网络连接');
            }
        });
    }
    
    // 初始化 WebSocket
    initWebSocket() {
        if (!this.preferWebSocket) return;
        
        const wsProtocol = window.location.protocol === 'https:' ? 'wss://' : 'ws://';
        const wsUrl = `${wsProtocol}${window.location.host}/wss`;
        
        console.log('🔄 客户端初始化 WebSocket...');
        console.log('🌐 客户端连接 WebSocket:', wsUrl);
        
        this.ws = new WebSocket(wsUrl);
        
        this.ws.onopen = (event) => this.handleWebSocketOpen(event);
        this.ws.onmessage = (event) => this.handleWebSocketMessage(event);
        this.ws.onerror = (event) => this.handleWebSocketError(event);
        this.ws.onclose = (event) => this.handleWebSocketClose(event);
    }
    
    handleWebSocketOpen(event) {
        console.log('✅ 客户端 WebSocket 连接成功');
        this.wsConnected = true;
        this.wsConnectionStatus = 'connected';
        this.wsReconnectAttempts = 0;
        
        // 发送认证消息
        this.sendWebSocketAuth();
    }
    
    handleWebSocketMessage(event) {
        try {
            const data = JSON.parse(event.data);
            console.log('📥 收到 WebSocket 消息:', data);
            
            if (data.type === 'auth_success') {
                console.log('✅ WebSocket 认证成功');
                this.startWebSocketHeartbeat();
                
                // 发送队列中的消息
                this.flushMessageQueue();
            } else if (data.type === 'auth_failed') {
                console.error('❌ WebSocket 认证失败:', data.message);
                this.wsAuthSent = false;
            } else if (data.type === 'message') {
                const message = data.data;

                // 先检查是否是自己刚发送的消息（去重）
                if (message.speaker_type === 1) {
                    const now = Date.now();
                    const isRecentlySent = this._lastSentMessages.some(sent => {
                        if (sent.messageType === 'image' && message.message_type === 'image') {
                            return (now - sent.timestamp) < 5000;
                        }
                        if (sent.messageType === 'card' && this.isXYDLCardMessage(message)) {
                            return (now - sent.timestamp) < 5000;
                        }
                        if (sent.content === message.content && (now - sent.timestamp) < 5000) {
                            return true;
                        }
                        return false;
                    });
                    if (isRecentlySent) {
                        console.log('跳过WebSocket重复消息(自己发送):', message.id, message.content);
                        if (message.id) {
                            this.lastMessageId = Math.max(this.lastMessageId, message.id);
                        }
                        return;
                    }
                }

                if (!this.recentlyReceivedWsMessageIds.has(message.id)) {
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
                }
            } else if (data.type === 'message_sent') {
                console.log('✅ 消息已发送到服务器');
                if (data.message_id) {
                    this.lastMessageId = Math.max(this.lastMessageId, data.message_id);
                }
            }
        } catch (error) {
            console.error('解析 WebSocket 消息失败:', error);
        }
    }
    
    handleWebSocketError(event) {
        console.error('❌ WebSocket 错误:', event);
        this.wsConnected = false;
        this.wsConnectionStatus = 'error';
        
        // 尝试重新连接
        this.attemptReconnect();
    }
    
    handleWebSocketClose(event) {
        console.log('🔌 WebSocket 连接关闭:', event);
        this.wsConnected = false;
        this.wsConnectionStatus = 'disconnected';
        
        // 停止心跳
        if (this.wsHeartbeatInterval) {
            clearInterval(this.wsHeartbeatInterval);
            this.wsHeartbeatInterval = null;
        }
        
        // 尝试重新连接
        if (event.code !== 1000) {
            this.attemptReconnect();
        }
    }
    
    sendWebSocketAuth() {
        if (!this.ws || this.ws.readyState !== WebSocket.OPEN) return;
        
        const authData = {
            type: 'auth',
            user_type: 'customer',
            user_id: this.customerName,
            session_key: this.sessionId
        };
        
        console.log('📤 发送 WebSocket 认证:', authData);
        this.ws.send(JSON.stringify(authData));
        this.wsAuthSent = true;
    }
    
    startWebSocketHeartbeat() {
        if (this.wsHeartbeatInterval) {
            clearInterval(this.wsHeartbeatInterval);
        }
        
        this.wsHeartbeatInterval = setInterval(() => {
            if (this.ws && this.ws.readyState === WebSocket.OPEN) {
                this.ws.send(JSON.stringify({type: 'ping'}));
                console.log('❤️ WebSocket 心跳已发送');
            }
        }, 10000);
        
        console.log('❤️ WebSocket 心跳已启动（10秒间隔）');
    }
    
    sendMessageToWebSocket(content, messageType = 'text') {
        if (!this.ws || this.ws.readyState !== WebSocket.OPEN || !this.wsAuthSent) {
            // 如果 WebSocket 不可用，将消息加入队列
            this.wsMessageQueue.push({content, type: messageType});
            return;
        }
        
        const messageData = {
            type: 'send_message',
            session_key: this.sessionId,
            agent_account: this.agentAccount,
            speaker_type: 1,
            content: content,
            customer_name: this.customerName,
            message_type: messageType
        };
        
        console.log('📤 通过 WebSocket 发送消息:', messageData);
        this.ws.send(JSON.stringify(messageData));
    }
    
    flushMessageQueue() {
        while (this.wsMessageQueue.length > 0) {
            const messageData = this.wsMessageQueue.shift();
            this.sendMessageToWebSocket(messageData.content, messageData.type);
        }
    }
    
    attemptReconnect() {
        if (this.wsReconnectAttempts >= this.maxWsReconnectAttempts) {
            console.error('❌ WebSocket 重连次数已达上限');
            return;
        }
        
        this.wsReconnectAttempts++;
        const delay = this.wsReconnectDelay * this.wsReconnectAttempts;
        
        console.log(`🔄 WebSocket 第 ${this.wsReconnectAttempts} 次重连尝试，延迟 ${delay}ms`);
        
        setTimeout(() => {
            this.initWebSocket();
        }, delay);
    }
    
    destroy() {
        // 关闭 WebSocket
        if (this.ws) {
            this.ws.close();
            this.ws = null;
        }
        
        // 停止心跳
        if (this.wsHeartbeatInterval) {
            clearInterval(this.wsHeartbeatInterval);
            this.wsHeartbeatInterval = null;
        }
        
        this.stopPolling();
        this.closeImagePreview();
        this.hideOrderModalWithAnimation();
        this.hideViolationModalWithAnimation();
        this.setCustomerOffline();
        console.log('客户聊天系统已销毁');
    }
}

// 初始化聊天系统
$(document).ready(function() {
    console.log('文档加载完成，初始化客户聊天系统...');
    window.customerChat = new CustomerChatSystem();
    
    // 页面加载完成后隐藏加载动画
    setTimeout(function() {
        var loadingContainer = document.getElementById('loadingContainer');
        if (loadingContainer) {
            loadingContainer.classList.add('hidden');
            setTimeout(function() {
                loadingContainer.remove();
            }, 300);
        }
    }, 500);
    
    $(window).on('beforeunload', function() {
        if (window.customerChat) {
            window.customerChat.destroy();
        }
    });
});

</script>
</body>
</html>