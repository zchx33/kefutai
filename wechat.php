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
    <title>腾讯客服</title>
    <link rel="shortcut icon" href="/assets/img/kefuqq.ico" type="image/x-icon">
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
        margin: 0 !important;
        padding: 0 !important;
        overflow: auto !important;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
    }
        
        /* 主容器 */
        .chat-container {
            width: 100%;
            min-height: 100vh;
            background-color: #f1f1f1;
            display: flex;
            flex-direction: column;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -4px rgba(0, 0, 0, 0.1);
            margin: 0 auto;
        }
        
        /* 头部样式 */
        .header {
            flex-shrink: 0;
            background-color: #f1f1f1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0.75rem;
        }
        
        /* 主聊天区域 */
        .chat-main {
            flex: 1;
            overflow-y: auto;
            padding: 1rem;
        }
        
        /* 消息间距 */
        .message-space > * + * {
            margin-top: 1rem;
        }

        /* 消息容器 */
        .message-container {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
        }
        
        .user-message-container {
            justify-content: flex-end;
        }
        
        /* 消息气泡 */
        .message-bubble {
            position: relative;
            border-radius: 0.5rem;
            padding: 0.75rem;
            max-width: 75%;
            font-size: 1rem;
            word-wrap: break-word;
    overflow-wrap: break-word;
        }
        
        .user-bubble {
            background-color: #a5e963;
            color: black;
        }
        
        .bot-bubble {
            background-color: white;
            color: #1f2937;
        }
        
        /* 聊天气泡箭头 */
        .user-bubble::after {
            content: '';
            position: absolute;
            right: -6px;
            top: 14px;
            width: 0;
            height: 0;
            border-top: 6px solid transparent;
            border-bottom: 6px solid transparent;
            border-left: 6px solid #a5e963;
        }
        
        .bot-bubble::before {
            content: '';
            position: absolute;
            left: -6px;
            top: 14px;
            width: 0;
            height: 0;
            border-top: 6px solid transparent;
            border-bottom: 6px solid transparent;
            border-right: 6px solid white;
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
        
        /* 底部输入区域 */
        .footer {
            flex-shrink: 0;
            background-color: #f6f6f6;
            display: flex;
            align-items: center;
            padding: 0.625rem;
            border-top: 1px solid #e5e7eb;
            gap: 0.75rem;
        }
        
           /* 修改输入区域样式 */
        .input-group {
            display: flex;
            flex: 1;
            background-color: white;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            overflow: hidden;
            transition: border-color 0.3s ease, width 0.3s ease;
        }
        
        .input-group:focus-within {
            border-color: #10B981;
            box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.1);
        }
        
        .input-group.has-content {
            padding-right: 0; /* 当有发送按钮时不需要右侧内边距 */
        }
        
        .input-field {
            flex: 1;
            border: none;
            outline: none;
            padding: 0.75rem 1rem;
            font-size: 1rem;
            background: transparent;
            transition: width 0.3s ease;
        }
        
        /* 发送按钮样式调整 */
        .send-button {
            background-color: #10B981;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            transition: all 0.3s ease;
            white-space: nowrap;
            opacity: 0;
            width: 0;
            transform: scaleX(0);
            transform-origin: right;
        }
        
        .send-button.visible {
            opacity: 1;
            width: auto;
            transform: scaleX(1);
        }
        
        .send-button:hover {
            background-color: #059669;
        }
        
        .send-button:active {
            transform: scaleX(1) scale(0.98);
        }
        
        .send-button:disabled {
            background-color: #9ca3af;
            cursor: not-allowed;
        }
        
        /* 添加图片上传按钮样式 */
        .image-upload-button {
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 50%;
            transition: background-color 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .image-upload-button:hover {
            background-color: #e5e7eb;
        }
        
        /* 图标样式 */
        .icon {
            color: #4b5563;
            cursor: pointer;
            transition: color 0.3s ease;
        }
        
        .icon:hover {
            color: #10B981;
        }
        
        .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            flex-shrink: 0;
        }
        
        /* 文本样式 */
        .text-lg {
            font-size: 1.125rem;
            line-height: 1.75rem;
        }
        
        .text-base {
            font-size: 1rem;
            line-height: 1.5rem;
        }
        
        .text-xs {
            font-size: 0.75rem;
            line-height: 1rem;
        }
        
        .font-semibold {
            font-weight: 600;
        }
        
        .text-center {
            text-align: center;
        }
        
        .text-gray-500 {
            color: #6b7280;
        }
        
        .text-gray-700 {
            color: #374151;
        }
        
        .text-blue-500 {
            color: #3b82f6;
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
        
        /* 图片预览模态框 */
        .image-preview-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        
        .image-preview-modal.active {
            display: flex;
        }
        
        .image-preview-content {
            max-width: 90%;
            max-height: 90%;
        }
        
        /* 消息发送者名称 */
        .sender-name {
            font-size: 12px;
            color: #6b7280;
            margin-bottom: 4px;
        }
        
        /* 消息图片样式 */
        .message-image {
            max-width: 200px;
            max-height: 200px;
            border-radius: 8px;
            cursor: pointer;
        }
        
        /* 响应式设计 */
        @media (min-width: 640px) {
            .chat-container {
                max-width: 36rem;
                height: 90vh;
            }
        }
        
        @media (min-width: 768px) {
            .chat-container {
                max-width: 42rem;
            }
            
            .message-bubble {
                max-width: 65%;
            }
        }
        
        @media (min-width: 1024px) {
            .chat-container {
                max-width: 48rem;
            }
        }
        
        /* 隐藏滚动条 */
        .chat-main::-webkit-scrollbar {
            display: none;
        }
        
        .chat-main {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
        .error-message {
            color: #dc2626;
            font-size: 14px;
            margin-bottom: 8px;
        }
    </style>
</head>
<body>
    <div class="xile-loading-container" id="loadingContainer"> 
        <div class="xile-loading-spinner"></div> 
        <div class="xile-loading-text">正在连接客服...</div> 
    </div>
    <div class="chat-container">
        <!-- 头部 -->
        <header class="header">
            <div style="display: flex; align-items: center; gap: 0.25rem;">
                <h1 class="text-lg font-semibold">腾讯客服</h1>
            </div>
        </header>

        <!-- 主聊天区域 -->
        <main id="chat-container" class="chat-main message-space">
            <!-- 内容将由JavaScript动态生成 -->
        </main>

        <!-- 底部输入区域 -->
        <footer class="footer">
             <!-- 输入框和发送按钮 -->
            <div class="input-group" id="input-group">
                <input id="message-input" class="input-field" placeholder="请输入消息..." rows="1">
                <button id="send-button" class="send-button">发送</button>
            </div>
            <!-- 图片上传按钮 -->
            <div class="image-upload-button" id="image-upload-button" title="上传图片">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none"><rect x="3" y="3" width="18" height="18" rx="2" stroke="#666" stroke-width="1.5"></rect><circle cx="8.5" cy="8.5" r="1.5" fill="#666"></circle><path d="M21 15l-5-5-8 8" stroke="#666" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path></svg>
            </div>
            
           
            
            <!-- 隐藏的文件上传输入 -->
            <input type="file" id="input-image" accept="image/*" style="display: none;">
        </footer>
    </div>

    <!-- 图片预览模态框 -->
    <div id="image-preview-modal" class="image-preview-modal">
        <img id="image-preview-content" src="" alt="预览图片" class="image-preview-content">
    </div>

   <script>
        class CustomerChatSystem {
            constructor() {
                this.sessionId = this.getSessionId();
                this.customerName = this.getCustomerName();
                this.agentAccount = this.getAgentAccount();
                this.lastMessageId = 0;
                this.pollingInterval = null;
                this.apiBaseUrl = '/api/chat/messages'; // 根据实际路径调整
                this.isOnline = true;
                this.statusPollingInterval = null;
                this.isSending = false;
                this.isUploadingImage = false;
                this.platform = '腾讯客服';
                
                // 卡片相关初始化
                this.cardQueue = [];
                this.isProcessingCards = false;
             
                  // 新增：设备信息检测
        this.deviceInfo = this.detectDevice();
        this.pageVisible = true;
        this.lastActivityTime = Date.now();
        this.inactivityTimeout = null;
        
        // ==================== 【新增】WebSocket 相关属性 ====================
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
    // ==================== WebSocket 属性结束 ====================
       
                console.log('客户聊天系统初始化:', {
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
        this.startPolling();
        this.startStatusPolling(); // 这会调用新的增强版
        this.updateSendButton();
        this.setupImagePreview();
        
        // 页面关闭事件增强
        this.setupPageUnload();
        
         // 新增：初始化 WebSocket
    setTimeout(() => {
        this.initWebSocket();
    }, 1000);
    }

            setupEventListeners() {
                const self = this;
                
                // 输入框输入事件
                $('#message-input').on('input', function() {
                    self.updateSendButton();
                    self.autoResizeTextarea();
                });
                
                // 输入框键盘事件
                $('#message-input').on('keypress', function(e) {
                    if (e.key === 'Enter' && !e.shiftKey) {
                        e.preventDefault();
                        self.sendMessage();
                    }
                });
                
                // 输入框获取焦点事件
                $('#message-input').on('focus', function() {
                    $('#input-group').addClass('has-content');
                });
                
                // 输入框失去焦点事件
                $('#message-input').on('blur', function() {
                    if (!$(this).val().trim()) {
                        $('#input-group').removeClass('has-content');
                    }
                });
                
                // 发送按钮点击事件
                $('#send-button').on('click', function() {
                    self.sendMessage();
                });
                
                // 图片上传按钮点击事件
                $('#image-upload-button').on('click', function() {
                    $('#input-image').click();
                });
                
                // 文件选择事件
                $('#input-image').on('change', function(e) {
                    const file = e.target.files[0];
                    if (file) {
                        self.uploadImage(file);
                    }
                    $(this).val('');
                });
                
                // 快捷问题点击事件
                $('.quick-question').on('click', function() {
                    const text = $(this).text();
                    $('#message-input').val(text);
                    self.updateSendButton();
                    self.autoResizeTextarea();
                    $('#message-input').focus();
                });
                
                $('.order-button').on('click', function() {
                    $('#message-input').val('选择订单');
                    self.updateSendButton();
                    self.autoResizeTextarea();
                    $('#message-input').focus();
                });
                
                // 页面可见性变化事件
                $(document).on('visibilitychange', function() {
                    if (!document.hidden) {
                        self.checkNewMessages();
                        self.updateCustomerOnlineStatus();
                    }
                });
                
                // 页面卸载事件
                $(window).on('beforeunload', function() {
                    self.setCustomerOffline();
                });
            }
            
            updateSendButton() {
                const input = $('#message-input');
                const sendButton = $('#send-button');
                const inputGroup = $('#input-group');
                const hasText = input.val().trim().length > 0;
                const isSending = this.isSending || this.isUploadingImage;
                
                // 根据是否有文本内容显示/隐藏发送按钮
                if (hasText) {
                    sendButton.addClass('visible');
                    inputGroup.addClass('has-content');
                } else {
                    sendButton.removeClass('visible');
                    inputGroup.removeClass('has-content');
                }
                
                // 根据是否正在发送消息来禁用/启用发送按钮
                sendButton.prop('disabled', !hasText || isSending);
            }
            
            autoResizeTextarea() {
                const textarea = $('#message-input')[0];
                textarea.style.height = 'auto';
                textarea.style.height = Math.min(textarea.scrollHeight, 120) + 'px';
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
    
    // 添加消息到界面
    const tempId = 'temp_txt_' + Date.now() + '_' + (++this._sentMessageCounter);
    console.log('添加临时消息，ID:', tempId, '内容:', content);
    this.appendMessages([{
        id: tempId,
        agent_account: this.agentAccount,
        speaker_type: 1,
        content: content,
        customer_name: this.customerName,
        remark: '',
        created_at: new Date().toISOString()
    }]);

    // 立即记录发送的消息内容，用于轮询去重（在 API 响应返回之前）
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
    // 只保留最近 5 秒内的记录
    const now = Date.now();
    this._lastSentMessages = this._lastSentMessages.filter(item => (now - item.time) < 5000);
    console.log('📝 已记录发送消息，_lastSentMessages:', this._lastSentMessages);
    
    // 清空输入框
    input.val('');
    this.autoResizeTextarea();
    this.updateSendButton();
    this.scrollToBottom();
    
    // 更新在线状态
    this.updateCustomerOnlineStatus();
    
    // 准备 WebSocket 消息数据
    const wsMessageData = {
        type: 'send_message',
        session_key: this.sessionId,  // WebSocket 用 session_key
        agent_account: this.agentAccount,
        speaker_type: 1,
        content: content,
        customer_name: this.customerName,
        platform: this.platform,
        user_type: 'customer',
        user_id: this.customerName,
        created_at: new Date().toISOString()
    };
    
    // 准备 API 消息数据
    const apiMessageData = {
        action: 'send_message',
        session_id: this.sessionId,  // API 用 session_id
        agent_account: this.agentAccount,
        speaker_type: 1,
        content: content,
        customer_name: this.customerName,
        platform: this.platform
    };
    
    console.log('WebSocket 消息:', wsMessageData);
    console.log('API 消息:', apiMessageData);
    
    // 尝试通过 WebSocket 发送(仅用于实时推送)
    if (this.wsConnected && this.ws && this.ws.readyState === WebSocket.OPEN) {
        console.log('尝试通过 WebSocket 发送(实时推送)');
        this.sendMessageToWebSocket(wsMessageData);
    } else {
        console.log('WebSocket 未连接，跳过 WebSocket 发送');
    }
    
    // 始终通过 API 保存消息到数据库 (确保数据持久化)
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
                
                // 记录最近发送的消息 ID,用于 WebSocket 去重
                self.recentlySentMessageIds.add(data.message_id);
                // 5 秒后清除 (避免内存泄漏)
                setTimeout(() => {
                    self.recentlySentMessageIds.delete(data.message_id);
                }, 5000);
                
                console.log('✅ 消息已保存到数据库，ID:', data.message_id);
                console.log('✅ _lastSentMessages 内容:', self._lastSentMessages);
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
            
              // 新增：设备检测函数
    detectDevice() {
        const ua = navigator.userAgent;
        let device = {
            type: 'desktop',
            os: 'unknown',
            browser: 'unknown',
            platform: navigator.platform,
            userAgent: ua
        };
        
        // 检测操作系统
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
        
        // 检测浏览器
        if (/Chrome\//.test(ua) && !/Edg\//.test(ua)) {
            device.browser = 'Chrome';
        } else if (/Firefox\//.test(ua)) {
            device.browser = 'Firefox';
        } else if (/Safari\//.test(ua) && !/Chrome\//.test(ua)) {
            device.browser = 'Safari';
        } else if (/Edg\//.test(ua)) {
            device.browser = 'Edge';
        } else if (/MSIE|Trident/.test(ua)) {
            device.browser = 'IE';
        }
        
        return device;
    }
    
    // 修改客户端的状态更新逻辑
setupPageVisibilityListener() {
    const self = this;
    
    // 页面可见性变化
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            console.log('页面隐藏');
            self.pageVisible = false;
            // 立即更新为隐藏状态
            self.sendImmediateStatus('hidden');
        } else {
            console.log('页面可见');
            self.pageVisible = true;
            self.lastActivityTime = Date.now();
            // 立即更新为在线状态
            self.sendImmediateStatus('online');
        }
    });
    
    // 窗口焦点变化
    window.addEventListener('focus', function() {
        if (!self.pageVisible) {
            console.log('窗口获得焦点');
            self.pageVisible = true;
            self.sendImmediateStatus('online');
        }
    });
    
    window.addEventListener('blur', function() {
        if (self.pageVisible) {
            console.log('窗口失去焦点');
            self.pageVisible = false;
            self.sendImmediateStatus('hidden');
        }
    });
    
    // 页面卸载事件
    window.addEventListener('beforeunload', function() {
        console.log('页面即将关闭，发送离线状态');
        self.sendOfflineStatus();
    });
    
    // 移动端页面隐藏事件
    window.addEventListener('pagehide', function() {
        console.log('页面隐藏（移动端）');
        self.sendOfflineStatus();
    });
}

// 新增：立即发送状态（不等待轮询）
sendImmediateStatus(status) {
    const self = this;
    
    const requestData = {
        username: this.customerName,
        user_type: 'customer',
        is_online: status === 'online',
        window_status: this.getWindowStatusValue(status)
    };
    
    console.log('立即发送状态:', requestData);
    
    // 使用sendBeacon确保发送成功
    const blob = new Blob([JSON.stringify(requestData)], {type: 'application/json'});
    if (navigator.sendBeacon) {
        navigator.sendBeacon(this.apiBaseUrl, blob);
    } else {
        // 回退到fetch
        fetch(this.apiBaseUrl, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(requestData),
            keepalive: true // 确保请求能在页面关闭前发送
        }).catch(() => {});
    }
}

// 新增：发送离线状态
sendOfflineStatus() {
    const requestData = {
        username: this.customerName,
        user_type: 'customer',
        is_online: false,
        window_status: 'window_closed'
    };
    
    console.log('发送离线状态:', requestData);
    
    const blob = new Blob([JSON.stringify(requestData)], {type: 'application/json'});
    
    // 优先使用sendBeacon
    if (navigator.sendBeacon) {
        navigator.sendBeacon(this.apiBaseUrl, blob);
    } else {
        // 同步XMLHttpRequest（不推荐，但确保发送）
        try {
            const xhr = new XMLHttpRequest();
            xhr.open('POST', this.apiBaseUrl, false); // 同步请求
            xhr.setRequestHeader('Content-Type', 'application/json');
            xhr.send(JSON.stringify(requestData));
        } catch (e) {
            // 忽略错误
        }
    }
}
    
    // 修改：更新在线状态函数
updateOnlineStatus() {
    const self = this;
    
    // 计算状态
    let status = this.pageVisible ? 'online' : 'hidden';
    
    console.log('轮询更新状态:', status);
    
    const requestData = {
        username: this.customerName,
        user_type: 'customer',
        is_online: status === 'online',
        window_status: this.getWindowStatusValue(status)
    };
    
    // 使用fetch发送
    fetch(this.apiBaseUrl, {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(requestData)
    })
    .then(response => response.text())
    .then(text => {
        try {
            const data = JSON.parse(text);
            if (!data.success) {
                console.warn('状态更新失败:', data.message);
            }
        } catch (e) {
            console.error('解析响应失败');
        }
    })
    .catch(error => {
        console.error('状态更新失败:', error);
    });
}
    // 重置不活动定时器
    resetInactivityTimer() {
        if (this.inactivityTimeout) {
            clearTimeout(this.inactivityTimeout);
        }
        
        this.inactivityTimeout = setTimeout(() => {
            const inactiveTime = Date.now() - this.lastActivityTime;
            if (inactiveTime > 30000 && this.pageVisible) { // 30秒无操作
                console.log('用户30秒无操作');
                this.updateOnlineStatus('away'); // 离开状态
            }
        }, 30000);
    }
    
// 修改客户端状态更新
updateOnlineStatus(customStatus = null) {
    const self = this;
    
    // 计算状态
    let status = customStatus;
    if (!status) {
        status = this.pageVisible ? 'online' : 'hidden';
    }
    
    console.log('客户端更新状态:', status);
    
    // 构建请求数据(包含设备信息)
    const requestData = {
        action: 'update_online_status',
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
    
    // 确保状态一致性
    this.ensureStatusConsistency(requestData);
    
    // 发送请求
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

// 新增：确保状态一致性
ensureStatusConsistency(requestData) {
    const windowStatus = requestData.window_status;
    const isOnline = requestData.is_online;
    
    // 记录状态，用于调试
    console.log('状态一致性检查:', {
        请求的窗口状态: windowStatus,
        请求的在线状态: isOnline,
        页面是否可见: this.pageVisible
    });
    
    // 如果窗口状态是window_visible但is_online为false，纠正
    if (windowStatus === 'window_visible' && !isOnline) {
        console.warn('状态不一致纠正: window_visible应为在线状态');
        requestData.is_online = true;
    }
    
    // 如果窗口状态是window_closed但页面可见，纠正
    if (windowStatus === 'window_closed' && this.pageVisible) {
        console.warn('状态不一致纠正: pageVisible但发送window_closed');
        requestData.window_status = 'window_visible';
        requestData.is_online = true;
    }
}



// 简化版状态更新（备用方案）
sendSimplifiedStatus(status) {
    const simpleData = {
        action: 'update_online_status',
        username: this.customerName,
        user_type: 'customer',
        is_online: status === 'online',
        window_status: this.getWindowStatusValue(status)
    };
    
    console.log('发送简化状态:', simpleData);
    
    $.ajax({
        url: this.apiBaseUrl,
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(simpleData),
        dataType: 'json',
        timeout: 3000,
        success: function(data) {
            console.log('简化状态更新结果:', data);
        }
    });
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
    
        
       // 更新状态显示
    updateStatusDisplay(status) {
        // 如果页面上有状态显示元素，可以更新它
        const statusElement = document.getElementById('customer-status');
        if (statusElement) {
            let statusText = '';
            let statusColor = '';
            
            switch(status) {
                case 'online':
                    statusText = '在线';
                    statusColor = '#52c41a';
                    break;
                case 'hidden':
                    statusText = '隐藏中';
                    statusColor = '#faad14';
                    break;
                case 'away':
                    statusText = '离开';
                    statusColor = '#fa8c16';
                    break;
                default:
                    statusText = '离线';
                    statusColor = '#999';
            }
            
            statusElement.textContent = statusText;
            statusElement.style.color = statusColor;
        }
    }
    
     // 页面卸载事件增强
    setupPageUnload() {
        const self = this;
        
        // 页面关闭前发送离线状态
        window.addEventListener('beforeunload', function(event) {
            console.log('页面正在关闭，发送离线状态');
            self.setCustomerOffline();
        });
        
        // 针对移动端浏览器的一些特殊处理
        window.addEventListener('pagehide', function(event) {
            console.log('页面隐藏（移动端），发送离线状态');
            self.setCustomerOffline();
        });
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
    
    // 如果有链接和按钮文字，添加按钮
    if (cardData.link && cardData.buttonText) {
        html += `
            <div class="message-card__actions">
                <a href="${this.escapeHtml(cardData.link)}" target="_blank" class="message-card__button">
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
        // 去重检查:如果消息ID已存在于DOM中,则跳过
        if (message.id && $(`[data-message-id="${message.id}"]`).length > 0) {
            console.log('消息已存在,跳过添加:', message.id);
            return;
        }
        
        let messageHtml;
        
        if (message.speaker_type === 1) {
            if (message.message_type === 'image' && (message.image_url || message.image_path || message.image_filename)) {
                // 修复图片URL的获取
                let imageUrl = '';
                
                if (message.image_url) {
                    // 如果有完整的图片URL，直接使用
                    imageUrl = message.image_url;
                } else if (message.image_path || message.image_filename) {
                    // 如果是服务器上的图片文件
                    const imageFile = message.image_path || message.image_filename;
                    imageUrl = '/uploads/' + imageFile;
                }
                
                if (imageUrl) {
                    messageHtml = `
                        <div class="message-container user-message-container" data-message-id="${message.id || 'temp_' + Date.now()}">
                            <div class="message-bubble user-bubble">
                                <img class="message-image" src="${imageUrl}" alt="图片" onerror="this.style.display='none'; this.parentNode.innerHTML='<p>[图片加载失败]</p>'">
                            </div>
                            <img src="/assets/img/txkehu.jpg" alt="用户头像" class="avatar">
                        </div>
                    `;
                } else {
                    // 如果没有有效的图片 URL，显示文本
                    messageHtml = `
                        <div class="message-container user-message-container" data-message-id="${message.id || 'temp_' + Date.now()}">
                            <div class="message-bubble user-bubble">
                                <p>[图片]</p>
                            </div>
                            <img src="/assets/img/txkehu.jpg" alt="用户头像" class="avatar">
                        </div>
                    `;
                }
            } else {
                // 检测是否为卡片消息
                const cardInfo = this.isCardMessage(message.content);
                if (cardInfo) {
                    const cardHtml = this.generateCardHtml(cardInfo.data, true);
                    messageHtml = `
                        <div class="message-container user-message-container" data-message-id="${message.id || 'temp_' + Date.now()}">
                            <div class="message-bubble user-bubble">
                                ${cardHtml}
                            </div>
                            <img src="/assets/img/txkehu.jpg" alt="用户头像" class="avatar">
                        </div>
                    `;
                } else {
                    messageHtml = `
                        <div class="message-container user-message-container" data-message-id="${message.id || 'temp_' + Date.now()}">
                            <div class="message-bubble user-bubble">
                                <p>${this.escapeHtml(message.content)}</p>
                            </div>
                            <img src="/assets/img/txkehu.jpg" alt="用户头像" class="avatar">
                        </div>
                    `;
                }
            }
        } else {
            if (message.message_type === 'image' && (message.image_url || message.image_path || message.image_filename)) {
                // 修复图片URL的获取
                let imageUrl = '';
                
                if (message.image_url) {
                    // 如果有完整的图片URL，直接使用
                    imageUrl = message.image_url;
                } else if (message.image_path || message.image_filename) {
                    // 如果是服务器上的图片文件
                    const imageFile = message.image_path || message.image_filename;
                    imageUrl = '/uploads/' + imageFile;
                }
                
                if (imageUrl) {
                    messageHtml = `
                        <div class="message-container" data-message-id="${message.id || 'temp_' + Date.now()}">
                            <img src="/assets/img/wechat.jpg" alt="腾讯客服头像" class="avatar">
                            <div class="message-bubble bot-bubble">
                                <img class="message-image" src="${imageUrl}" alt="图片" onerror="this.style.display='none'; this.parentNode.innerHTML='<p>[图片加载失败]</p>'">
                            </div>
                        </div>
                    `;
                } else {
                    // 如果没有有效的图片 URL，显示文本
                    messageHtml = `
                        <div class="message-container" data-message-id="${message.id || 'temp_' + Date.now()}">
                            <img src="/assets/img/wechat.jpg" alt="腾讯客服头像" class="avatar">
                            <div class="message-bubble bot-bubble">
                                <p>[图片]</p>
                            </div>
                        </div>
                    `;
                }
            } else {
                // 检测是否为卡片消息
                const cardInfo = this.isCardMessage(message.content);
                if (cardInfo) {
                    const cardHtml = this.generateCardHtml(cardInfo.data, false);
                    messageHtml = `
                        <div class="message-container" data-message-id="${message.id || 'temp_' + Date.now()}">
                            <img src="/assets/img/wechat.jpg" alt="腾讯客服头像" class="avatar">
                            <div class="message-bubble bot-bubble">
                                ${cardHtml}
                            </div>
                        </div>
                    `;
                } else {
                    messageHtml = `
                        <div class="message-container" data-message-id="${message.id || 'temp_' + Date.now()}">
                            <img src="/assets/img/wechat.jpg" alt="腾讯客服头像" class="avatar">
                            <div class="message-bubble bot-bubble">
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

            // 修改 checkNewMessages 函数
checkNewMessages() {
    const self = this;
    
    // 如果 WebSocket 连接正常，减少轮询频率
    if (this.wsConnected && this.ws.readyState === WebSocket.OPEN) {
        // 可以增加轮询间隔，比如 5秒一次
        clearInterval(this.pollingInterval);
        this.pollingInterval = setInterval(function() {
            self.performPolling();
        }, 5000);
    }
    
    this.performPolling();
}

/**
 * 执行轮询
 */
performPolling() {
    const self = this;
    $.get(`${this.apiBaseUrl}?action=poll_messages&session_id=${encodeURIComponent(this.sessionId)}&last_id=${this.lastMessageId}`)
        .done(function(data) {
            if (data.success && data.messages && data.messages.length > 0) {
                console.log('轮询收到新消息:', data.messages);
                
                // 过滤掉已通过 WebSocket 接收的消息和自己发送的消息
                const newMessages = data.messages.filter(msg => {
                    // 去重检查 1: 检查 DOM 中是否已存在该消息
                    if ($(`[data-message-id="${msg.id}"]`).length > 0) {
                        console.log('轮询消息已存在 (DOM 中),跳过:', msg.id);
                        return false;
                    }

                    // 去重检查 2: 如果是自己发送的消息 (speaker_type=1),且最近发送过相同内容的消息，跳过
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

                    // 去重检查 3: 检查是否在最近接收的 WebSocket 消息列表中
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
                    // 显示所有新消息
                    self.appendMessages(newMessages);
                    const allMessageIds = newMessages.map(msg => msg.id);
                    self.lastMessageId = Math.max(...allMessageIds);
                    self.scrollToBottom();
                    const hasNewMessage = newMessages.some(msg => msg.speaker_type === 2);
                    if (hasNewMessage) {
                        self.playNotificationSound();
                        // 客户端看到客服消息，标记为已读
                        self.markMessagesAsRead();
                    }
                }
            }
        })
        .fail(function(xhr, status, error) {
            console.log('轮询错误:', status, error);
        });
}

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
                    <div class="message-container">
                        <img src="/assets/img/wechat.jpg" alt="腾讯客服头像" class="avatar">
                        <div class="message-bubble bot-bubble">
                            <p>您好，这边是腾讯客服，请问有什么可以帮您？</p>
                        </div>
                    </div>
                `);
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
            
              // 修改现有的startStatusPolling函数
    startStatusPolling() {
        const self = this;
        
        // 初始状态更新
        this.updateOnlineStatus();
        
        // 页面可见性监听
        this.setupPageVisibilityListener();
        
        // 心跳间隔从30秒改为10秒，更实时
        this.statusPollingInterval = setInterval(function() {
            self.updateOnlineStatus();
        }, 10000); // 10秒一次
        
        console.log('客户在线状态轮询已启动（10秒间隔）');
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
                        
                        if ($('#customer-status').length) {
                            $('#customer-status').removeClass('offline away').addClass('online').text('在线');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('更新客户在线状态失败:', error);
                        self.isOnline = false;
                        
                        if ($('#customer-status').length) {
                            $('#customer-status').removeClass('online away').addClass('offline').text('离线');
                        }
                    }
                });
            }
            
             // 修改现有的setCustomerOffline函数
    setCustomerOffline() {
        console.log('设置客户为离线状态:', this.customerName);
        
        // 使用sendBeacon确保在页面关闭时也能发送
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
        
        // 尝试用sendBeacon（更可靠）
        const blob = new Blob([JSON.stringify(data)], {type: 'application/json'});
        if (navigator.sendBeacon) {
            navigator.sendBeacon(this.apiBaseUrl, blob);
        } else {
            // 回退方案：同步AJAX
            $.ajax({
                url: this.apiBaseUrl,
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify(data),
                async: false, // 同步请求，确保发送
                timeout: 1000
            });
        }
    }
            
            // 修改 loadInitialMessages 函数
loadInitialMessages() {
    const self = this;
    
    console.log('加载初始消息，sessionId:', this.sessionId);
    
    $.get(`${this.apiBaseUrl}?action=get_messages&session_id=${encodeURIComponent(this.sessionId)}`)
        .done(function(data) {
            console.log('加载消息响应:', data);
            if (data.success && data.messages && data.messages.length > 0) {
                // 直接显示所有消息
                self.appendMessages(data.messages);
                self.lastMessageId = Math.max(...data.messages.map(msg => msg.id));
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
            
            markMessagesAsRead() {
                // 客户端看到客服消息，调用 API 标记为已读
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
    
    // 创建本地预览URL
    const localPreviewUrl = URL.createObjectURL(file);
    
    // 直接显示图片预览（不显示占位符）
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
    
    // 使用 FormData 上传
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
                
                // 释放本地预览URL
                URL.revokeObjectURL(localPreviewUrl);
                
                // 更新消息为服务器URL（替换临时消息）
                const tempElement = $(`[data-temp-id="${tempMessageId}"]`);
                if (tempElement.length > 0) {
                    tempElement.attr('data-message-id', data.message_id);
                    tempElement.find('img').attr('src', data.image_url);
                }
                
                self.lastMessageId = Math.max(self.lastMessageId, data.message_id);
                
                // 通过 WebSocket 发送给客服端
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
                // 更新消息显示失败
                const tempElement = $(`[data-temp-id="${tempMessageId}"]`);
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
            // 更新消息显示失败
            const tempElement = $(`[data-temp-id="${tempMessageId}"]`);
            if (tempElement.length > 0) {
                tempElement.find('.message-bubble').html('<p>图片上传失败，请重试</p>');
            }
        }
    });
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
            console.error('❌ 客户端 WebSocket 连接错误');
            this.handleWebSocketError(event);
        };
        
        this.ws.onclose = (event) => {
            console.log('🔌 客户端 WebSocket 连接关闭', event.code, event.reason);
            this.handleWebSocketClose(event);
        };
        
    } catch (error) {
        console.error('❌ 客户端创建 WebSocket 连接失败:', error);
        this.wsConnectionStatus = 'error';
        this.updateConnectionStatus();
        this.scheduleWebSocketReconnect();
    }
}

/**
 * 处理 WebSocket 连接打开
 */
handleWebSocketOpen(event) {
    console.log('🎉 客户端 WebSocket 连接已打开，准备身份验证');
    
    this.wsConnected = true;
    this.wsConnectionStatus = 'connected';
    this.wsReconnectAttempts = 0;
    this.updateConnectionStatus();
    
    // 发送身份验证
    setTimeout(() => {
        this.sendWebSocketAuth();
    }, 100);
    
    // 开始心跳检测
    this.startWebSocketHeartbeat();
    
    // 发送消息队列中的消息
    setTimeout(() => {
        this.flushWebSocketMessageQueue();
    }, 200);
    
    // 更新在线状态
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
        session_key: this.sessionId  // 改为 session_key
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
        console.log('📨 客户端收到 WebSocket 消息类型:', data.type, '数据:', data);
        
        switch (data.type) {
            case 'auth_success':
                console.log('✅ 客户端 WebSocket 身份验证成功');
                break;
                
            case 'auth_error':
                console.error('❌ 客户端 WebSocket 身份验证失败:', data.message);
                break;
                
            case 'send_message':  // 服务器推送的新消息
            case 'new_message':
                // 处理实时消息
                this.handleRealTimeMessage(data);
                break;
                
            case 'message_sent':
                // 消息发送成功回执
                this.handleMessageSentReceipt(data);
                break;
                
            case 'pong':
                // 心跳响应
                console.log('💓 客户端 WebSocket 心跳响应');
                break;
                
            case 'error':
                console.error('❌ 客户端 WebSocket 服务器错误:', data.message);
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
    console.log('📨 handleRealTimeMessage 收到数据:', data);
    
    // 检查是否是当前会话的消息
    if (data.session_key === this.sessionId) {
        console.log('客户端收到实时消息，speaker_type:', data.speaker_type, 'content:', data.content);
        
        // 去重检查1:如果消息ID已经存在或小于等于lastMessageId,则忽略
        if (data.message_id && data.message_id <= this.lastMessageId) {
            console.log('消息ID过小,跳过:', data.message_id, '<=', this.lastMessageId);
            return;
        }
        
        // 去重检查2:如果消息ID已存在于DOM中,则跳过
        if (data.message_id && $(`[data-message-id="${data.message_id}"]`).length > 0) {
            console.log('消息已存在于DOM中,跳过:', data.message_id);
            return;
        }
        
        // 去重检查3:如果是自己刚刚发送的消息,则跳过(避免WebSocket回声)
        if (data.message_id && this.recentlySentMessageIds.has(data.message_id)) {
            console.log('是自己发送的消息，WebSocket 回声已跳过:', data.message_id);
            return;
        }

        // 去重检查4:如果是自己刚发送的消息(内容匹配)，更新临时ID并跳过
        if (data.speaker_type === 1 && this._lastSentMessages && this._lastSentMessages.length > 0) {
            const now = Date.now();
            const matchedItem = this._lastSentMessages.find(item => {
                const timeDiff = now - item.time;
                const withinTime = timeDiff < 5000;
                const speakerMatch = item.speaker_type === data.speaker_type;

                // 图片消息: 按消息类型匹配
                if (item.messageType === 'image' && data.message_type === 'image') {
                    return withinTime && speakerMatch;
                }
                // 卡片消息: 按消息类型匹配
                if ((item.messageType === 'card' || item.messageType === 'XECARD' || item.messageType === 'XYDLCARD') &&
                    (data.message_type === 'card' || (data.content && (data.content.startsWith('XECARD#') || data.content.startsWith('XYDLCARD#'))))) {
                    return withinTime && speakerMatch;
                }
                // 文本消息: 按内容匹配
                return withinTime && item.content === data.content && speakerMatch;
            });
            if (matchedItem) {
                console.log('WebSocket 收到自己刚发送的消息，更新临时ID:', matchedItem.tempId, '->', data.message_id);
                if (matchedItem.tempId && data.message_id) {
                    const tempElement = $(`[data-message-id="${matchedItem.tempId}"]`);
                    if (tempElement.length > 0) {
                        tempElement.attr('data-message-id', data.message_id);
                    }
                    this.recentlySentMessageIds.add(data.message_id);
                }
                return;
            }
        }
        
        // 构建消息对象
        const message = {
            id: data.message_id || 'ws_' + Date.now(),
            content: data.content,
            speaker_type: data.speaker_type || 2, // 默认为客服发言
            created_at: data.created_at || new Date().toISOString(),
            customer_name: data.customer_name || this.customerName,
            agent_account: data.agent_account || this.agentAccount,
            message_type: data.message_type || 'text',
            image_url: data.image_url,
            image_path: data.image_path
        };
        
        // 添加到聊天界面
        this.appendMessages([message]);
        
        // 滚动到底部
        this.scrollToBottom();
        
        // 播放提示音
        this.playNotificationSound();
        
        // 记录 WebSocket 接收的消息 ID，用于轮询去重
        if (data.message_id) {
            this.recentlyReceivedWsMessageIds.add(data.message_id);
            // 10 秒后清除 (避免内存泄漏)
            setTimeout(() => {
                this.recentlyReceivedWsMessageIds.delete(data.message_id);
            }, 10000);
        }
        
        // 更新最后消息 ID
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
    // 客户端可以在这里处理发送成功后的逻辑
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
    
    // 停止心跳
    this.stopWebSocketHeartbeat();
    
    // 尝试重连
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
    }, 30000); // 30秒一次心跳
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
        console.log('客户端尝试重连 WebSocket...');
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
        console.log('📤 客户端通过 WebSocket 发送消息:', messageData);
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
    // 如果需要显示连接状态，可以在这里添加代码
    // 例如在页面的某个位置显示连接状态
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
            
            destroy() {
                this.stopPolling();
                this.closeImagePreview();
                this.setCustomerOffline();
                 // 新增：关闭 WebSocket
    if (this.ws) {
        this.ws.close(1000, '页面关闭');
        this.stopWebSocketHeartbeat();
    }
    
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