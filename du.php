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
<html lang="zh-CN"><head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>得物App-官方客服</title>
    <link rel="icon" type="image/x-icon" href="https://dewu.com/static/favicon.ico">
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

        /* 基础样式重置 */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: ui-sans-serif, system-ui, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", "Noto Color Emoji";
            background-color: #e5e7eb;
            overflow: auto;
            overscroll-behavior: contain;
        }
        
        /* 布局容器 */
        .wrapper {
         
            padding-top: 0.001em;
            background-color: #e5e7eb;
        }
        
        .chat-container {
            max-width: 384px;
            margin: 0 auto;
            background-color: #f5f5f7;
         
            display: flex;
            flex-direction: column;
            font-family: ui-sans-serif, system-ui, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", "Noto Color Emoji";
        }
        
        /* 头部样式 */
        .header {
            background-color: white;
            border-bottom: 1px solid #e5e7eb;
            padding: 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: relative;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            z-index: 10;
        }
        
        .header-center {
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .logo {
            width: 32px;
            height: 32px;
            border-radius: 50%;
        }
        
        .title {
            font-weight: 600;
            font-size: 16px;
            color: black;
        }
        
        .official-badge {
    color: #01c2c3;
    border: 1px solid #01c2c3;
    background-color: white;
    font-size: 10px;
    font-weight: 600;
    padding: 2px 6px;
    border-radius: 4px;
}
        
        /* 聊天区域样式 */
        .chat-area {
            flex: 1;
            overflow-y: auto;
            padding: 16px;
        }
        
        .message-container {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }
        
        /* 用户消息样式 */
        .user-message {
            display: flex;
            justify-content: flex-end;
            align-items: flex-start;
            gap: 10px;
        }
        
        .user-bubble {
            background-color: #d0f2f2;
            color: black;
            padding: 12px;
            border-radius: 8px;
            max-width: 70%;
            word-wrap: break-word;
    overflow-wrap: break-word;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            flex-shrink: 0;
        }
        
        /* 机器人消息样式 */
        .XEdewu-message {
            display: flex;
            align-items: flex-start;
            gap: 16px;
        }
        
        .XEdewu-content {
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }
        
        .XEdewu-bubble {
            background-color: white;
            color: black;
            padding: 12px;
            border-radius: 8px;
            max-width: 280px;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            word-wrap: break-word;
    overflow-wrap: break-word;
        }
        
        .XEdewu-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            flex-shrink: 0;
        }
        
        .message-text {
            font-size: 16px;
            line-height: 1.375;
        }
        
        /* 快捷问题样式 */
        .quick-question {
            border-top: 1px solid #f3f4f6;
            margin-top: 8px;
            padding-top: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
        }
        
        /* 反馈按钮样式 */
        .feedback-buttons {
            display: flex;
            flex-direction: column;
            gap: 16px;
            color: #9ca3af;
            padding-top: 8px;
        }
        
        /* 底部输入区域样式 */
        .footer {
            border-top: 1px solid #e5e7eb;
            padding: 12px;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        .quick-actions {
            display: flex;
            gap: 8px;
            overflow-x: auto;
            padding-bottom: 4px;
        }
        
        .quick-action-btn {
            background-color: white;
            font-size: 14px;
            color: #1f2937;
            padding: 6px 12px;
            display: flex;
            align-items: center;
            gap: 4px;
            flex-shrink: 0;
            white-space: nowrap;
            cursor: pointer;
            border: none;
            font-family: inherit;
        }
        
        .input-area {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .message-input {
            flex: 1;
            background-color: white;
            padding: 8px 16px;
            color: #1f2937;
            border: 1px solid #e5e7eb;
            font-size: 14px;
            font-family: inherit;
        }
        
        /* 图标样式 */
        .icon {
            width: 1em;
            height: 1em;
        }
        
        .chevron-icon {
            font-size: 28px;
            color: #333;
        }
        
        .dots-icon {
            font-size: 28px;
            color: #333;
        }
        
        .feedback-icon {
            font-size: 20px;
            cursor: pointer;
        }
        
        .emoji-icon {
            font-size: 16px;
            color: #409eff;
        }
        
        .headset-icon {
            font-size: 16px;
        }
        
        .plus-icon {
            font-size: 28px;
            color: #4b5563;
            cursor: pointer;
        }
        
        /* 自适应设计 - 媒体查询 */
        @media (max-width: 480px) {
            .chat-container {
                max-width: 100%;
                height: 100%;
            }
            
            .header {
                padding: 10px;
            }
            
            .chat-area {
                padding: 12px;
            }
            
            .footer {
                padding: 10px;
            }
            
            .message-text {
                font-size: 15px;
            }
            
            .user-avatar, .XEdewu-avatar {
                width: 36px;
                height: 36px;
            }
            
            .quick-action-btn {
                font-size: 13px;
                padding: 5px 10px;
            }
        }
        
        @media (max-width: 360px) {
            .user-bubble, .XEdewu-bubble {
                max-width: 80%;
            }
            
            .message-text {
                font-size: 14px;
            }
            
            .title {
                font-size: 15px;
            }
            
            .quick-actions {
                gap: 6px;
            }
        }
        
        @media (min-width: 768px) {
            .chat-container {
                max-width: 448px;
            }
        }
        
        @media (min-width: 1024px) {
            .chat-container {
                max-width: 512px;
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
        
        .quick-actions::-webkit-scrollbar {
            display: none;
        }
        
        .quick-actions {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }

        /* 新增样式 - 图片消息 */
        .message-image {
            max-width: 200px;
            max-height: 200px;
            border-radius: 8px;
            cursor: pointer;
            margin: 5px 0;
        }

        /* 新增样式 - 图片预览模态框 */
        .image-preview-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.9);
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
            object-fit: contain;
        }

      

        /* 发送按钮样式 */
        .send-button {
            background: linear-gradient(90deg, #409eff 0%, #409eff 98%);
            color: white;
            border: none;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            flex-shrink: 0;
        }
        .send-button svg {
            width: 20px;
            height: 20px;
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
            color: #01c2c3;
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
            background: #01c2c3;
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
            box-shadow: 0 3px 8px rgba(1, 194, 195, 0.3);
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
    <div class="wrapper">
        <div class="chat-container">
            <!-- 头部 -->
            <header class="header">
                <svg class="icon chevron-icon" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" role="img" viewBox="0 0 24 24">
                    <path fill="currentColor" d="M15.41 16.58L10.83 12l4.58-4.59L14 6l-6 6l6 6z"></path>
                </svg>
               
                <div class="header-center">
                    <img class="logo" src="/assets/img/dw-kf.png" alt="XEdewu Avatar">
                    <span class="title">智能客服</span>
                    <span class="official-badge">官方</span>
                </div>

            </header>
            
            <!-- 聊天区域 -->
            <main class="chat-area" id="chat-area">
                <div class="message-container" id="message-container">
                    <div class="XEdewu-message">
                        <div class="XEdewu-content">
                            <img class="XEdewu-avatar" src="/assets/img/dw-kf.png" alt="XEdewu Avatar">
                            <div class="XEdewu-bubble">
                                <p class="message-text">亲亲您先不要着急, 请您先描述下您要咨询的问题~ 我们会第一时间为您解答, 确保您得到最贴心的帮助!</p>
                            </div>
                        </div>
                    </div>

                    </div>
            </main>
            
            <!-- 底部输入区域 -->
            <footer class="footer">
                <div class="quick-actions">
                    <button class="quick-action-btn" data-text="人工客服">
                        <svg class="icon headset-icon" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" role="img" viewBox="0 0 24 24">
                            <path fill="currentColor" d="M12 1c-5 0-9 4-9 9v7a3 3 0 0 0 3 3h3v-8H5v-2a7 7 0 0 1 7-7a7 7 0 0 1 7 7v2h-4v8h4v1h-7v2h6a3 3 0 0 0 3-3V10c0-5-4.03-9-9-9"></path>
                        </svg>
                        人工客服
                    </button>
                   
                    <button class="quick-action-btn" data-text="咨询订单">咨询订单</button>
                    
                    <button class="quick-action-btn" data-text="商品咨询">商品咨询</button>
                    <button class="quick-action-btn" data-text="热门问题">热门问题</button>
                </div>
                <div class="input-area">
                    <input type="text" class="message-input" id="message-input" placeholder="请输入您要咨询的问题">
                    <div id="image-upload-button" class="icon plus-icon" style="cursor: pointer;">
                        <svg class="icon plus-icon" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" role="img" viewBox="0 0 24 24">
                            <path fill="currentColor" d="M12 20c-4.41 0-8-3.59-8-8s3.59-8 8-8s8 3.59 8 8s-3.59 8-8 8m0-18A10 10 0 0 0 2 12a10 10 0 0 0 10 10a10 10 0 0 0 10-10A10 10 0 0 0 12 2m1 5h-2v4H7v2h4v4h2v-4h4v-2h-4z"></path>
                        </svg>
                    </div>
                    <button class="send-button" id="send-button">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="white">
                            <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"></path>
                        </svg>
                    </button>
                </div>
            </footer>
        </div>
    </div>

    <!-- 隐藏的文件上传输入 -->
    <input type="file" id="input-image" accept="image/*" style="display: none;">

    <!-- 图片预览模态框 -->
    <div id="image-preview-modal" class="image-preview-modal">
        <img id="image-preview-content" src="" alt="预览图片" class="image-preview-content">
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
// 得物客服聊天系统适配版
class DewuChatSystem {
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
        this.platform = '得物';
        
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
        
        console.log('得物聊天系统初始化:', {
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
        });
        
        // 图片上传按钮
        $('#image-upload-button').on('click', function() {
            $('#input-image').click();
        });
        
        // 图片文件选择
        $('#input-image').on('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                self.uploadImage(file);
            }
            $(this).val('');
        });
        
        // 快捷按钮事件
        $('.quick-action-btn').on('click', function() {
            const text = $(this).text() || $(this).attr('data-text');
            if (text) {
                $('#message-input').val(text);
                self.updateSendButton();
                $('#message-input').focus();
            }
        });
        
        // 返回按钮
        $('.chevron-icon').on('click', function() {
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
    
    updateSendButton() {
        const hasText = $('#message-input').val().trim().length > 0;
        const sendButton = $('#send-button');
        
        if (hasText) {
            sendButton.css('opacity', '1').prop('disabled', false);
        } else {
            sendButton.css('opacity', '0.5').prop('disabled', true);
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
            content: '[图片]',
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
            <div style="background: white; border-radius: 8px; margin: 4px 0; max-width: 260px;">
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
                    <a href="${this.escapeHtml(cardData.link)}" target="_blank" style="display: inline-block; padding: 6px 12px; background: #01c2c3; color: white; text-decoration: none; border-radius: 4px; font-size: 13px;">
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
        const container = $('#message-container');
        
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
                            <div class="user-bubble">
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
                                <div class="user-bubble">
                                    ${cardHtml}
                                </div>
                            </div>
                        `;
                    } else {
                        messageHtml = `
                            <div class="user-message" data-message-id="${message.id || 'temp_' + Date.now()}">
                                <div class="user-bubble">
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
                        <div class="XEdewu-message" data-message-id="${message.id || 'temp_' + Date.now()}">
                            <div class="XEdewu-content">
                                <img class="XEdewu-avatar" src="/assets/img/dw-kf.png" alt="XEdewu Avatar">
                                <div class="XEdewu-bubble">
                                    <img class="message-image" src="${imageUrl}" alt="图片" onerror="this.style.display='none'; this.parentNode.innerHTML='<p>[图片加载失败]</p>';">
                                </div>
                            </div>
                        </div>
                    `;
                } else {
                    // 检测是否为卡片消息
                    const cardInfo = this.isCardMessage(message.content);
                    if (cardInfo) {
                        const cardHtml = this.generateCardHtml(cardInfo.data);
                        messageHtml = `
                            <div class="XEdewu-message" data-message-id="${message.id || 'temp_' + Date.now()}">
                                <div class="XEdewu-content">
                                    <img class="XEdewu-avatar" src="/assets/img/dw-kf.png" alt="XEdewu Avatar">
                                    <div class="XEdewu-bubble">
                                        ${cardHtml}
                                    </div>
                                </div>
                            </div>
                        `;
                    } else {
                        messageHtml = `
                            <div class="XEdewu-message" data-message-id="${message.id || 'temp_' + Date.now()}">
                                <div class="XEdewu-content">
                                    <img class="XEdewu-avatar" src="/assets/img/dw-kf.png" alt="XEdewu Avatar">
                                    <div class="XEdewu-bubble">
                                        <p class="message-text">${this.escapeHtml(message.content)}</p>
                                    </div>
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
                    const now = Date.now();
                    const newMessages = data.messages.filter(msg => {
                        // 去重检查
                        if ($(`[data-message-id="${msg.id}"]`).length > 0) {
                            return false;
                        }

                        // 检查是否是自己刚发送的消息
                        if (msg.speaker_type === 1) {
                            if (self._lastSentMessages && self._lastSentMessages.length > 0) {
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
                        }

                        return true;
                    });

                    // 清理过期的去重记录
                    self._lastSentMessages = self._lastSentMessages.filter(sent =>
                        (now - sent.timestamp) < 10000
                    );
                    
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
        const container = $('#chat-area');
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

            // 先检查是否是自己刚发送的消息（内容匹配去重）
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
        console.log('得物聊天系统已销毁');
    }
}

// 初始化聊天系统
$(document).ready(function() {
    console.log('文档加载完成，初始化得物聊天系统...');
    window.chatSystem = new DewuChatSystem();
    
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