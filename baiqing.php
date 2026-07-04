<?php
if (!defined('FROM_ROUTER')) {
    http_response_code(403);
    exit('喜乐科技@x60898');
}
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/chatroom_setting.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/session_parser.php';
// 记录访问量
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/chatroom_web.php';
recordVisit();

$sessionId = $_GET['id'] ?? 'aaaccazzz-ptestadmins';
$parsedSession = SessionParser::parseSessionId($sessionId);
$customerName = $parsedSession['customer'];
$agentAccount = $parsedSession['agent'];

// 获取数据库连接
$conn = getDB();
if (!$conn) {
    die("数据库连接失败");
}

// 获取URL中的页面代码 - 修改为从XEchatroom参数获取
$page_code = isset($_GET['XEchatroom']) ? $_GET['XEchatroom'] : '';

if (empty($page_code)) {
    // 如果没有提供页面代码，显示错误
    echo '<div class="container text-center mt-5">
            <div class="alert alert-danger" role="alert">
                <h4 class="alert-heading">错误！</h4>
                <p>未找到群聊页面，请检查链接是否正确。</p>
                <hr>
                <p class="mb-0">如果您需要帮助，请联系客服获取正确的链接。</p>
            </div>
          </div>';
    exit;
}

// 查询群聊信息 - 使用XEyouxige表结构
$sql = "SELECT XEyouxige_id, XEyouxige_user_id, XEyouxige_trader_name, 
               XEyouxige_group_code, XEyouxige_welcome_message, 
               XEyouxige_page_status, XEyouxige_page_code, 
               XEyouxige_seller_avatar, XEyouxige_created_at, 
               XEyouxige_updated_at
        FROM XEyouxige 
        WHERE XEyouxige_page_code = ? AND XEyouxige_page_status = 'active'";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $page_code);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // 没有找到对应的群聊
    echo '<div class="container text-center mt-5">
            <div class="alert alert-warning" role="alert">
                <h4 class="alert-heading">群聊不存在或已失效！</h4>
                <p>该群聊可能已被删除、禁用或链接错误。</p>
                <hr>
                <p class="mb-0">请确认链接是否正确，或联系管理员获取新的群聊链接。</p>
            </div>
          </div>';
    $stmt->close();
    $conn->close();
    exit;
}

// 获取群聊数据
$chatroom_data = $result->fetch_assoc();

$stmt->close();
$conn->close();

// 设置默认头像路径
$defaultAvatarPath = '/assets/img/pz-yh.png';
$sellerAvatar = !empty($chatroom_data['XEyouxige_seller_avatar']) 
    ? $chatroom_data['XEyouxige_seller_avatar'] 
    : $defaultAvatarPath;

// 设置交易员名称
$traderName = $chatroom_data['XEyouxige_trader_name'] ?? '交易员';

// 设置群聊编号
$groupCode = $chatroom_data['XEyouxige_group_code'] ?? '';

// 设置欢迎语
$welcomeMessage = $chatroom_data['XEyouxige_welcome_message'] ?? '欢迎加入盼之群聊！';
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>白情在线交易群聊</title>
    <link rel="icon" href="/assets/img/youxige.ico">
    <link rel="stylesheet" href="/assets/Kefu/youxige.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Noto+Sans+SC:wght@400;500;700&display=swap">
    <script src="/assets/iconify.min.js"></script>
    <!-- 引入jQuery -->
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

/* 假人消息徽章 */
.dummy-badge {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
}

/* 图片预览模态框 */
.image-preview-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.9);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}

.image-preview-modal.active {
    display: flex;
}

.image-preview-content {
    max-width: 90%;
    max-height: 90%;
    object-fit: contain;
}

/* 消息图片样式 */
.message-image {
    max-width: 200px;
    max-height: 200px;
    border-radius: 8px;
    cursor: pointer;
    transition: transform 0.2s ease;
}

.message-image:hover {
    transform: scale(1.02);
}
.chat-area {
    padding: 5rem 0.75rem 5rem 0.75rem;
}

/* 卡片消息样式 */
.message-card {
    background: #ffffff;
    border-radius: 8px;
    padding: 16px;
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

        /* 交易卡片样式 */
        .tradecard {
            width: clamp(248px, 82vw, 320px);
            min-width: 0;
            max-width: 100%;
            padding: 12px;
            border-radius: 10px;
            background: #fff;
            border: 1px solid rgba(15,23,42,.06);
            box-shadow: none;
            color: #222;
        }
        
        .tradecard-title {
            text-align: center;
            font-size: 17px;
            font-weight: 600;
            line-height: 1.25;
            margin-bottom: 12px;
            color: #111827;
        }
        
        .tradecard-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 10px;
        }
        
        .tradecard-tags {
            display: flex;
            gap: 5px;
            min-width: 0;
            flex-wrap: wrap;
        }
        
        .tradecard-tag {
            padding: 2px 5px;
            border-radius: 3px;
            font-size: 11px;
            line-height: 1.2;
            white-space: nowrap;
        }
        
       .tradecard-tag.game {
    background: #fffbe6;
    color: #ffdc00;
}
        
        .tradecard-tag.type {
            background: #e8f4ff;
            color: #3b82f6;
        }
        
        .tradecard-status {
            font-size: 14px;
            font-weight: 600;
            color: #ffdc00;
            white-space: nowrap;
        }
        
        .tradecard-goods {
            display: flex;
            gap: 8px;
            margin-bottom: 12px;
            align-items: flex-start;
        }
        
        .tradecard-img {
            width: 80px;
            height: 106px;
            border-radius: 6px;
            object-fit: cover;
            flex: 0 0 80px;
            background: #f3f4f6;
        }
        
        .tradecard-main {
            min-width: 0;
            flex: 1 1 auto;
        }
        
        .tradecard-name {
            font-size: 14px;
            line-height: 1.35;
            margin-bottom: 5px;
            color: #111827;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .tradecard-price {
            font-size: 16px;
            font-weight: 600;
            text-align: right;
            color: #ff4d4f;
        }
        
        .tradecard-tip {
            font-size: 11px;
            color: #666;
            margin-top: 3px;
            line-height: 1.35;
        }
        
        .tradecard-info {
            border-top: 1px solid #eee;
            padding-top: 8px;
        }
        
        .tradecard-item {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            padding: 6px 0;
            font-size: 13px;
            line-height: 1.35;
        }
        
        .tradecard-label {
            color: #666;
            flex: 0 0 auto;
        }
        
        .tradecard-value {
            color: #222;
            font-weight: 500;
            min-width: 0;
            text-align: right;
            word-break: break-all;
        }
        
        /* 付款卡片样式 */
        .paycard {
            width: clamp(204px, 72vw, 248px);
            min-width: 0;
            max-width: 100%;
            padding: 11px 12px;
            border-radius: 16px;
            background: #fff;
            border: 1px solid rgba(15,23,42,.06);
            box-shadow: none;
            color: #2f2f2f;
        }
        
        .paycard-title {
            font-size: 15px;
            font-weight: 900;
            line-height: 1.2;
            color: #2f2f2f;
        }
        
        .paycard-line {
            margin-top: 9px;
            font-size: 12.5px;
            line-height: 1.3;
            color: #7b7b7b;
            display: flex;
            align-items: center;
            gap: 0;
            white-space: nowrap;
            overflow: hidden;
        }
        
        .paycard-line.paycard-fit {
            font-size: 12.5px;
        }
        
        .paycard-label {
            font-weight: 400;
            color: #7b7b7b;
            flex: 0 0 auto;
        }
        
        .paycard-value {
            display: inline-block;
            min-width: 0;
            flex: 1 1 auto;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            font-weight: 400;
            color: #7b7b7b;
        }
        
        .paycard-line.amount-line {
            margin-top: 10px;
            padding-top: 7px;
            border-top: 1px solid rgba(15,23,42,.06);
        }
        
        .paycard-value.amount {
            color: #ffdc00;
            font-weight: 700;
        }
    </style>
</head>
<body>
    <div class="xile-loading-container" id="loadingContainer"> 
        <div class="xile-loading-spinner"></div> 
        <div class="xile-loading-text">正在连接群聊...</div> 
    </div>
    	<div id="cgtips" class="cgtips">
		<p id="cgtipstext">成功</p>
		<img src="/assets/img/pzcg.svg" alt="">
	</div>
    <div class="wrapper">
        <div class="main-container">
            <!-- Header - 左对齐 -->
            <header class="header">
                <div class="header-inner">
                    <span class="iconify back-icon" data-icon="mdi:chevron-left"></span>
                    <div class="title-container">
                        <h1 class="title">白情交易专群<?php echo htmlspecialchars($chatroom_data['XEyouxige_group_code']); ?></h1>
                        <div class="status-container">
                            <span class="status-badge">在线</span>
                            <span class="status-time">09:30-00:30</span>
                        </div>
                    </div>
                </div>
            </header>
            
            <!-- Chat Area -->
            <main class="chat-area" id="chat-container" >
                
            </main>
            
            <!-- Footer 和工具栏 -->
            <footer class="footer" id="footer">
            
                
                <!-- 输入区域 -->
                <div class="input-container">
                    <input type="file" id="input-image" accept="image/*" style="display: none;">
                    <svg t="1768758594620" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="5034" width="24" height="24"><path d="M942.545455 68.049455H81.454545a34.909091 34.909091 0 0 0-34.90909 34.90909v818.08291c0 19.269818 15.639273 34.909091 34.90909 34.90909h861.09091a34.909091 34.909091 0 0 0 34.90909-34.90909V102.958545a34.909091 34.909091 0 0 0-34.90909-34.90909z m-34.909091 69.818181V663.738182l-165.003637-138.984727a34.862545 34.862545 0 0 0-45.847272 0.791272l-123.159273 111.104-274.385455-261.166545a34.816 34.816 0 0 0-47.616-0.465455L116.363636 498.734545V137.867636h791.272728z m-791.272728 748.264728v-297.937455a34.304 34.304 0 0 0 13.730909-7.354182l144.570182-132.282182 274.199273 260.980364a34.816 34.816 0 0 0 47.429818 0.651636l124.602182-112.360727 181.248 152.576c1.675636 1.396364 3.630545 2.141091 5.492364 3.165091v132.608h-791.272728z m558.871273-432.500364c65.768727 0 119.296-53.480727 119.296-119.249455s-53.480727-119.249455-119.296-119.249454c-65.722182 0-119.202909 53.480727-119.202909 119.249454s53.480727 119.249455 119.202909 119.249455z m0-168.634182c27.275636 0 49.477818 22.155636 49.477818 49.431273s-22.202182 49.431273-49.477818 49.431273a49.477818 49.477818 0 0 1 0-98.862546z" fill="#231815" p-id="5035"></path></svg>
                    <input id="message-input" type="text" class="message-input" placeholder="请输入内容"><button id="send-button" class="send-button">发送</button>
                    
                    
                </div>
            </footer>
        </div>
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
        this.apiBaseUrl = '/api/chat/messages';
        this.isOnline = true;
        this.statusPollingInterval = null;
        this.isSending = false;
        this.isUploadingImage = false;
        this.platform = '白情群聊';
        
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
        
        // 消息去重相关
        this.recentlySentMessageIds = new Set();
        this.recentlyReceivedWsMessageIds = new Set();
        this._lastSentMessages = [];
        this._sentMessageCounter = 0;

        // 假人设置初始化
        this.dummySettings = {
            dummy_name: '技术顾问',
            dummy_avatar: '/assets/img/dummy1.png',
            is_dummy_mode: false
        };
        this.lastDummyUpdate = 0;
        this.lastDummyCheckTime = 0;
        this.dummyPollingInterval = null;
        
        console.log('客户聊天系统初始化:', {
            sessionId: this.sessionId,
            customerName: this.customerName,
            agentAccount: this.agentAccount,
            dummySettings: this.dummySettings,
        });
        
        console.log('检测到设备信息:', this.deviceInfo);
        
        this.init();
    }
    
    init() {
        this.createWelcomeMessages();
        this.loadInitialMessages();
        this.setupEventListeners();
        this.startPolling();
        this.startStatusPolling();
        this.updateSendButton();
        this.setupImagePreview();
        this.updateCustomerOnlineStatus();
        this.setupPageUnload();
        
        // 初始化时检查假人设置
        this.checkDummySettings();
        
        // 初始化 WebSocket
        setTimeout(() => {
            this.initWebSocket();
        }, 1000);
    }

    // 检查假人设置的方法
    checkDummySettings() {
        const self = this;
        
        $.ajax({
            url: this.apiBaseUrl,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                action: 'get_dummy_settings',
                session_id: this.sessionId
            }),
            success: function(data) {
                if (data && data.success && data.dummy_settings) {
                    const newSettings = data.dummy_settings;
                    
                    console.log('收到假人设置响应:', newSettings);
                    
                    if (!newSettings.dummy_name || !newSettings.dummy_avatar) {
                        console.log('假人设置数据不完整，使用默认值');
                        return;
                    }
                    
                    if (newSettings.dummy_avatar && !newSettings.dummy_avatar.startsWith('http') && !newSettings.dummy_avatar.startsWith('/')) {
                        newSettings.dummy_avatar = '/assets/img/' + newSettings.dummy_avatar;
                    }
                    
                    const settingsChanged = 
                        newSettings.dummy_name !== self.dummySettings.dummy_name ||
                        newSettings.dummy_avatar !== self.dummySettings.dummy_avatar ||
                        newSettings.is_dummy_mode !== self.dummySettings.is_dummy_mode;
                    
                    const newUpdateTime = newSettings.last_updated || 0;
                    
                    if (settingsChanged && newUpdateTime > self.lastDummyUpdate) {
                        console.log('检测到假人设置更新:', newSettings);
                        self.dummySettings = {
                            dummy_name: newSettings.dummy_name || '技术顾问',
                            dummy_avatar: newSettings.dummy_avatar || '/assets/img/dummy1.png',
                            is_dummy_mode: Boolean(newSettings.is_dummy_mode)
                        };
                        self.lastDummyUpdate = newUpdateTime;
                        
                        self.updateExistingDummyMessages();
                    }
                } else {
                    console.log('未获取到假人设置或请求失败:', data);
                }
            },
            error: function(xhr, status, error) {
                console.error('获取假人设置失败:', error);
            }
        });
    }

    // 更新现有假人消息的显示
    updateExistingDummyMessages() {
        const self = this;
        const dummyMessages = $('.dummy-message');
        
        if (dummyMessages.length === 0) {
            console.log('没有假人消息需要更新');
            return;
        }
        
        console.log(`找到 ${dummyMessages.length} 条假人消息需要更新`);
        
        dummyMessages.each(function(index) {
            const messageElement = $(this);
            const nameElement = messageElement.find('.agent-name');
            const avatarElement = messageElement.find('.agent-avatar');
            
            if (nameElement.length > 0) {
                nameElement.empty();
                nameElement.text(self.dummySettings.dummy_name);
            }
            
            if (avatarElement.length > 0) {
                const currentSrc = avatarElement.attr('src');
                const newSrc = self.dummySettings.dummy_avatar;
                
                if (currentSrc !== newSrc) {
                    const img = new Image();
                    img.onload = function() {
                        avatarElement.attr('src', newSrc);
                        avatarElement.attr('alt', self.dummySettings.dummy_name);
                        console.log(`消息 ${index+1} 头像更新成功`);
                    };
                    img.onerror = function() {
                        console.warn(`头像加载失败: ${newSrc}，使用默认头像`);
                        avatarElement.attr('src', '/assets/img/dummy1.png');
                        avatarElement.attr('alt', self.dummySettings.dummy_name);
                    };
                    img.src = newSrc;
                }
            }
        });
        
        console.log('假人消息更新完成');
    }

    setupEventListeners() {
        const self = this;
        
        $('#message-input').on('input', function() {
            self.updateSendButton();
        });
        
        $('#message-input').on('keypress', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                self.sendMessage();
            }
        });
        
        $(document).on('click', '.icon', function() {
            $('#input-image').click();
        });
        
        $('#send-button').on('click', function() {
            self.sendMessage();
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
                self.checkDummySettings();
            }
        });
        
        $(window).on('beforeunload', function() {
            self.setCustomerOffline();
        });
    }

    updateSendButton() {
        const input = $('#message-input');
        const sendButton = $('#send-button');
        const hasText = input.val().trim().length > 0;
        const isSending = this.isSending || this.isUploadingImage;
        
        sendButton.prop('disabled', !hasText || isSending);
        
        if (isSending) {
            sendButton.text('发送中...');
        } else if (hasText) {
            sendButton.text('发送');
        } else {
            sendButton.text('发送');
        }
    }

    // 检测是否为卡片消息
    isCardMessage(content) {
        if (!content || typeof content !== 'string') return false;
        
        // 检测交易卡片
        if (content.startsWith('XEXXCARD#') && content.length > 9) {
            try {
                const cardJson = content.substring(9);
                const cardData = JSON.parse(cardJson);
                if (cardData.type === 'trade_card') {
                    return { type: 'trade', data: cardData };
                }
            } catch (e) {
                console.error('解析交易卡片数据失败:', e);
            }
        }
        
        // 检测付款卡片
        if (content.startsWith('XEPAYCARD#') && content.length > 10) {
            try {
                const cardJson = content.substring(10);
                const cardData = JSON.parse(cardJson);
                if (cardData.type === 'pay_card') {
                    return { type: 'pay', data: cardData };
                }
            } catch (e) {
                console.error('解析付款卡片数据失败:', e);
            }
        }
        
        // 检测自定义卡片
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
        // 交易卡片
        if (cardData.type === 'trade_card') {
            return this.renderTradeCard(cardData);
        }
        
        // 付款卡片
        if (cardData.type === 'pay_card') {
            return this.renderPayCard(cardData);
        }
        
        // 自定义卡片
        let html = `
            <div class="message-card">
                <div class="message-card__header">
                    <span class="message-card__title">${this.escapeHtml(cardData.title)}</span>
                </div>
                <div class="message-card__content">
                    ${this.escapeHtml(cardData.content)}
                </div>
        `;
        
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
    
    // 渲染交易卡片
    renderTradeCard(cardData) {
        const imageUrl = cardData.image_url || '';
        const price = cardData.price ? (cardData.price.startsWith('¥') ? cardData.price : '¥' + cardData.price) : '';
        
        return `
            <div class="tradecard">
                <div class="tradecard-title">${this.escapeHtml(cardData.title || '交易信息')}</div>
                <div class="tradecard-top">
                    <div class="tradecard-tags">
                        <span class="tradecard-tag game">${this.escapeHtml(cardData.main_title || '')}</span>
                        <span class="tradecard-tag type">${this.escapeHtml(cardData.subtitle || '')}</span>
                    </div>
                    <div class="tradecard-status">${this.escapeHtml(cardData.trade_status || '')}</div>
                </div>
                <div class="tradecard-goods">
                    ${imageUrl ? `<img src="${this.escapeHtml(imageUrl)}" class="tradecard-img" alt="商品图">` : ''}
                    <div class="tradecard-main">
                        <div class="tradecard-name">${this.escapeHtml(cardData.description || '')}</div>
                        <div class="tradecard-price">${price}</div>
                        <div class="tradecard-tip">${this.escapeHtml(cardData.note || '')}</div>
                    </div>
                </div>
                <div class="tradecard-info">
                    <div class="tradecard-item"><span class="tradecard-label">订单编号：</span><span class="tradecard-value">${this.escapeHtml(cardData.order_no || '')}</span></div>
                    <div class="tradecard-item"><span class="tradecard-label">商品编号：</span><span class="tradecard-value">${this.escapeHtml(cardData.goods_no || '')}</span></div>
                    <div class="tradecard-item"><span class="tradecard-label">创建时间：</span><span class="tradecard-value">${this.escapeHtml(cardData.create_time || '')}</span></div>
                    <div class="tradecard-item"><span class="tradecard-label">合同状态：</span><span class="tradecard-value">${this.escapeHtml(cardData.contract_status || '')}</span></div>
                </div>
            </div>
        `;
    }
    
    // 渲染付款卡片
    renderPayCard(cardData) {
        const amount = cardData.amount ? (cardData.amount.startsWith('¥') ? cardData.amount : '¥' + cardData.amount) : '';
        
        return `
            <div class="paycard">
                <div class="paycard-title">订单已支付</div>
                <div class="paycard-line paycard-fit"><span class="paycard-label">订单编号：</span><span class="paycard-value">${this.escapeHtml(cardData.order_no || '')}</span></div>
                <div class="paycard-line paycard-fit"><span class="paycard-label">商品编号：</span><span class="paycard-value">${this.escapeHtml(cardData.goods_no || '')}</span></div>
                <div class="paycard-line paycard-fit amount-line"><span class="paycard-label">支付金额：</span><span class="paycard-value amount">${amount}</span></div>
            </div>
        `;
    }

    appendMessages(messages) {
        const container = $('#chat-container');
        
        messages.forEach(message => {
            if (message.id && $(`[data-message-id="${message.id}"]`).length > 0) {
                console.log('消息已存在,跳过添加:', message.id);
                return;
            }
            
            let messageHtml;
            
            // 假人消息的特殊处理 (speaker_type === 3)
            if (message.speaker_type === 3) {
                const dummyName = message.dummy_name || this.dummySettings.dummy_name || '技术顾问';
                const dummyAvatar = message.dummy_avatar || this.dummySettings.dummy_avatar || '/assets/img/dummy1.png';
                
                const avatarSrc = dummyAvatar.startsWith('http') || dummyAvatar.startsWith('/') 
                    ? dummyAvatar 
                    : '/assets/img/' + dummyAvatar;
                
                if (message.message_type === 'image' && (message.image_url || message.image_path)) {
                    const imageUrl = message.image_url || ('/uploads/' + message.image_path);
                    messageHtml = `
                        <div class="agent-message-container dummy-message" data-message-id="${message.id}">
                            <div class="agent-message-inner">
                                <img class="agent-avatar" src="${avatarSrc}" alt="${dummyName}" onerror="this.src='/assets/img/dummy1.png'">
                                <div class="agent-message-content">
                                    <p class="agent-name">
                                        ${dummyName}
                                    </p>
                                    <div class="agent-message-bubble">
                                        <img class="message-image" src="${imageUrl}" alt="图片">
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                } else {
                    const cardInfo = this.isCardMessage(message.content);
                    if (cardInfo) {
                        const cardHtml = this.generateCardHtml(cardInfo.data, false);
                        messageHtml = `
                            <div class="agent-message-container dummy-message" data-message-id="${message.id}">
                                <div class="agent-message-inner">
                                    <img class="agent-avatar" src="${avatarSrc}" alt="${dummyName}" onerror="this.src='/assets/img/dummy1.png'">
                                    <div class="agent-message-content">
                                        <p class="agent-name">
                                            ${dummyName}
                                        </p>
                                       
                                            ${cardHtml}
                                     
                                    </div>
                                </div>
                            </div>
                        `;
                    } else {
                        messageHtml = `
                            <div class="agent-message-container dummy-message" data-message-id="${message.id}">
                                <div class="agent-message-inner">
                                    <img class="agent-avatar" src="${avatarSrc}" alt="${dummyName}" onerror="this.src='/assets/img/dummy1.png'">
                                    <div class="agent-message-content">
                                        <p class="agent-name">
                                            ${dummyName}
                                        </p>
                                        <div class="agent-message-bubble">
                                            <p class="agent-message-text">${this.escapeHtml(message.content)}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                    }
                }
            } 
            // 普通用户消息 (speaker_type === 1)
            else if (message.speaker_type === 1) {
                if (message.message_type === 'image' && (message.image_url || message.image_path)) {
                    const imageUrl = message.image_url || ('/uploads/' + message.image_path);
                    messageHtml = `
                        <div class="simple-user-message-container" data-message-id="${message.id}">
                            <div class="simple-user-message-inner">
                                <div class="simple-user-message-bubble">
                                    <img class="message-image" src="${imageUrl}" alt="图片">
                                </div>
                                <img class="user-avatar" src="<?php echo htmlspecialchars($chatroom_data['XEyouxige_seller_avatar']); ?>" alt="User avatar">
                            </div>
                        </div>
                    `;
                } else {
                    const cardInfo = this.isCardMessage(message.content);
                    if (cardInfo) {
                        const cardHtml = this.generateCardHtml(cardInfo.data, true);
                        messageHtml = `
                            <div class="simple-user-message-container" data-message-id="${message.id}">
                                <div class="simple-user-message-inner">
                                    <div class="simple-user-message-bubble">
                                        ${cardHtml}
                                    </div>
                                    <img class="user-avatar" src="<?php echo htmlspecialchars($chatroom_data['XEyouxige_seller_avatar']); ?>" alt="User avatar">
                                </div>
                            </div>
                        `;
                    } else {
                        messageHtml = `
                            <div class="simple-user-message-container" data-message-id="${message.id}">
                                <div class="simple-user-message-inner">
                                    <div class="simple-user-message-bubble">
                                        <p class="simple-user-message-text">${this.escapeHtml(message.content)}</p>
                                    </div>
                                    <img class="user-avatar" src="<?php echo htmlspecialchars($chatroom_data['XEyouxige_seller_avatar']); ?>" alt="User avatar">
                                </div>
                            </div>
                        `;
                    }
                }
            } 
            // 普通客服消息 (speaker_type === 2)
            else {
                if (message.message_type === 'image' && (message.image_url || message.image_path)) {
                    const imageUrl = message.image_url || ('/uploads/' + message.image_path);
                    messageHtml = `
                        <div class="agent-message-container" data-message-id="${message.id}">
                            <div class="agent-message-inner">
                                <img class="agent-avatar" src="/assets/img/bq-kf.jpg" alt="Agent avatar">
                                <div class="agent-message-content">
                                    <p class="agent-name">
                                        白情交易专员-<?php echo htmlspecialchars($chatroom_data['XEyouxige_trader_name']); ?>
                                        <span class="agent-badge">官方</span>
                                    </p>
                                    <div class="agent-message-bubble">
                                        <img class="message-image" src="${imageUrl}" alt="图片">
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                } else {
                    const cardInfo = this.isCardMessage(message.content);
                    if (cardInfo) {
                        const cardHtml = this.generateCardHtml(cardInfo.data, false);
                        messageHtml = `
                            <div class="agent-message-container" data-message-id="${message.id}">
                                <div class="agent-message-inner">
                                    <img class="agent-avatar" src="/assets/img/bq-kf.jpg" alt="Agent avatar">
                                    <div class="agent-message-content">
                                        <p class="agent-name">
                                            白情交易专员-<?php echo htmlspecialchars($chatroom_data['XEyouxige_trader_name']); ?>
                                            <span class="agent-badge">官方</span>
                                        </p>
                                      
                                            ${cardHtml}
                                      
                                    </div>
                                </div>
                            </div>
                        `;
                    } else {
                        messageHtml = `
                            <div class="agent-message-container" data-message-id="${message.id}">
                                <div class="agent-message-inner">
                                    <img class="agent-avatar" src="/assets/img/bq-kf.jpg" alt="Agent avatar">
                                    <div class="agent-message-content">
                                        <p class="agent-name">
                                            白情交易专员-<?php echo htmlspecialchars($chatroom_data['XEyouxige_trader_name']); ?>
                                            <span class="agent-badge">官方</span>
                                        </p>
                                        <div class="agent-message-bubble">
                                            <p class="agent-message-text">${this.escapeHtml(message.content)}</p>
                                        </div>
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

    checkNewMessages() {
        const self = this;
        
        // 先检查假人设置更新 - 降低频率到每30秒检查一次
        const now = Date.now();
        if (now - (this.lastDummyCheckTime || 0) > 30000) {
            this.checkDummySettings();
            this.lastDummyCheckTime = now;
        }
        
        // 如果 WebSocket 连接正常，减少轮询频率
        if (this.wsConnected && this.ws && this.ws.readyState === WebSocket.OPEN) {
            clearInterval(this.pollingInterval);
            this.pollingInterval = setInterval(function() {
                self.performPolling();
            }, 5000);
        }
        
        this.performPolling();
    }

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
                        
                        // 去重：检查自己发送的消息
                        if (msg.speaker_type === 1) {
                            if (self._lastSentMessages && self._lastSentMessages.length > 0) {
                                const now = Date.now();
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
                        
                        // 去重：检查是否在 WebSocket 接收的消息列表中
                        if (self.recentlyReceivedWsMessageIds && self.recentlyReceivedWsMessageIds.has(msg.id)) {
                            console.log('轮询消息已通过 WebSocket 接收，跳过:', msg.id);
                            return false;
                        }
                        
                        return true;
                    });
                    
                    // 清理过期的去重记录
                    const now = Date.now();
                    self._lastSentMessages = self._lastSentMessages.filter(sent => 
                        (now - sent.timestamp) < 10000
                    );
                    
                    if (newMessages.length > 0) {
                        console.log('轮询过滤后显示', newMessages.length, '条消息');
                        self.appendMessages(newMessages);
                        const allMessageIds = newMessages.map(msg => msg.id);
                        self.lastMessageId = Math.max(...allMessageIds);
                        self.scrollToBottom();
                        
                        const hasAgentMessage = newMessages.some(msg => msg.speaker_type === 2);
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
        if (this._lastSentMessages.length > 20) {
            this._lastSentMessages.shift();
        }
        
        input.val('');
        this.updateSendButton();
        this.scrollToBottom();
        
        this.updateCustomerOnlineStatus();
        
        // WebSocket 消息数据
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
        
        // API 消息数据
        const apiMessageData = {
            action: 'send_message',
            session_id: this.sessionId,
            agent_account: this.agentAccount,
            speaker_type: 1,
            content: content,
            customer_name: this.customerName,
            platform: this.platform
        };
        
        // 通过 WebSocket 发送
        if (this.wsConnected && this.ws && this.ws.readyState === WebSocket.OPEN) {
            console.log('尝试通过 WebSocket 发送(实时推送)');
            this.sendMessageToWebSocket(wsMessageData);
        } else {
            console.log('WebSocket 未连接，跳过 WebSocket 发送');
        }
        
        // 通过 API 保存到数据库
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
    
    createWelcomeMessages() {
        const container = $('#chat-container');
        
        container.append(`
            <div class="agent-message-container">
                <div class="agent-message-inner">
                    <img class="agent-avatar" src="/assets/img/bq-kf.jpg" alt="Agent avatar">
                    <div class="agent-message-content">
                        <p class="agent-name">
                            白情交易专员-<?php echo htmlspecialchars($chatroom_data['XEyouxige_trader_name']); ?>
                            <span class="agent-badge">官方</span>
                        </p>
                        <div class="agent-message-bubble">
                            <p class="agent-message-text"><?php echo htmlspecialchars($chatroom_data['XEyouxige_welcome_message']); ?></p>
                        </div>
                    </div>
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
        } else if (/MSIE|Trident/.test(ua)) {
            device.browser = 'IE';
        }
        
        return device;
    }

    setupPageVisibilityListener() {
        const self = this;
        
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                console.log('页面隐藏');
                self.pageVisible = false;
                self.sendImmediateStatus('hidden');
            } else {
                console.log('页面可见');
                self.pageVisible = true;
                self.lastActivityTime = Date.now();
                self.sendImmediateStatus('online');
            }
        });
        
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
    }

    sendImmediateStatus(status) {
        const self = this;
        
        const requestData = {
            action: 'update_online_status',
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

    sendOfflineStatus() {
        const requestData = {
            action: 'update_online_status',
            username: this.customerName,
            user_type: 'customer',
            is_online: false,
            window_status: 'window_closed'
        };
        
        console.log('发送离线状态:', requestData);
        
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
            }
        }
    }

    updateOnlineStatus() {
        const self = this;
        
        let status = this.pageVisible ? 'online' : 'hidden';
        
        console.log('轮询更新状态:', status);
        
        const requestData = {
            action: 'update_online_status',
            username: this.customerName,
            user_type: 'customer',
            is_online: status === 'online',
            window_status: this.getWindowStatusValue(status)
        };
        
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

    startStatusPolling() {
        const self = this;
        
        this.updateOnlineStatus();
        this.setupPageVisibilityListener();
        
        this.statusPollingInterval = setInterval(function() {
            self.updateOnlineStatus();
        }, 10000);
        
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
                
                if ($('.status-badge').length) {
                    $('.status-badge').removeClass('offline away').addClass('online').text('在线');
                }
            },
            error: function(xhr, status, error) {
                console.error('更新客户在线状态失败:', error);
                self.isOnline = false;
                
                if ($('.status-badge').length) {
                    $('.status-badge').removeClass('online away').addClass('offline').text('离线');
                }
            }
        });
    }
    
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

    loadInitialMessages() {
        const self = this;
        
        console.log('加载初始消息，sessionId:', this.sessionId);
        
        $.get(`${this.apiBaseUrl}?action=get_messages&session_id=${encodeURIComponent(this.sessionId)}`)
            .done(function(data) {
                console.log('加载消息响应:', data);
                if (data.success && data.messages && data.messages.length > 0) {
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
        
        // 假人设置轮询（独立，每30秒一次）
        this.dummyPollingInterval = setInterval(function() {
            self.checkDummySettings();
        }, 30000);
        
        console.log('消息轮询已启动');
        console.log('假人设置轮询已启动（30秒间隔）');
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
        
        if (this.dummyPollingInterval) {
            clearInterval(this.dummyPollingInterval);
            this.dummyPollingInterval = null;
        }
        
        console.log('所有轮询已停止');
    }

    escapeHtml(unsafe) {
        if (unsafe === undefined || unsafe === null) {
            return '';
        }
        
        const safe = String(unsafe);
        return safe
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    scrollToBottom() {
        const container = $('#chat-container');
        setTimeout(() => {
            container.scrollTop(container[0].scrollHeight);
        }, 100);
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

    // ==================== WebSocket 相关方法 ====================

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
            this.scheduleWebSocketReconnect();
        }
    }

    handleWebSocketOpen(event) {
        console.log('🎉 客户端 WebSocket 连接已打开，准备身份验证');
        
        this.wsConnected = true;
        this.wsConnectionStatus = 'connected';
        this.wsReconnectAttempts = 0;
        
        setTimeout(() => {
            this.sendWebSocketAuth();
        }, 100);
        
        this.startWebSocketHeartbeat();
        
        setTimeout(() => {
            this.flushWebSocketMessageQueue();
        }, 200);
        
        this.updateCustomerOnlineStatus();
    }

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
                    
                case 'send_message':
                case 'new_message':
                    this.handleRealTimeMessage(data);
                    break;
                    
                case 'message_sent':
                    this.handleMessageSentReceipt(data);
                    break;
                    
                case 'pong':
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

    handleRealTimeMessage(data) {
        console.log('📨 handleRealTimeMessage 收到数据:', data);
        
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
            
            if (data.speaker_type === 1) {
                const messageContainers = $('.simple-user-message-container');
                for (let i = 0; i < messageContainers.length; i++) {
                    const container = $(messageContainers[i]);
                    const messageBubble = container.find('.simple-user-message-bubble');
                    const tempContent = messageBubble.find('p').text();
                    
                    if (tempContent === data.content) {
                        const messageId = container.attr('data-message-id');
                        const isTempMessage = !messageId || 
                                              messageId.toString().startsWith('temp_') || 
                                              (messageId && !isNaN(messageId) && parseInt(messageId) > Date.now() - 5000);
                        
                        if (isTempMessage) {
                            console.log('检测到刚发送的临时消息，更新为正式 ID:', data.message_id);
                            if (data.message_id) {
                                container.attr('data-message-id', data.message_id);
                                this.recentlySentMessageIds.add(data.message_id);
                            }
                            return;
                        }
                    }
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
                image_path: data.image_path,
                dummy_name: data.dummy_name,
                dummy_avatar: data.dummy_avatar
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

    handleMessageSentReceipt(data) {
        console.log('客户端消息发送回执:', data);
    }

    handleWebSocketError(event) {
        console.error('客户端 WebSocket 错误:', event);
        this.wsConnectionStatus = 'error';
    }

    handleWebSocketClose(event) {
        console.log('客户端 WebSocket 连接关闭:', event.code, event.reason);
        this.wsConnected = false;
        this.wsConnectionStatus = 'disconnected';
        this.wsAuthSent = false;
        
        this.stopWebSocketHeartbeat();
        this.scheduleWebSocketReconnect();
    }

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

    stopWebSocketHeartbeat() {
        if (this.wsHeartbeatInterval) {
            clearInterval(this.wsHeartbeatInterval);
            this.wsHeartbeatInterval = null;
        }
    }

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

    flushWebSocketMessageQueue() {
        if (this.wsMessageQueue.length === 0) return;
        
        console.log(`客户端刷新消息队列，有 ${this.wsMessageQueue.length} 条待发送消息`);
        
        const queue = [...this.wsMessageQueue];
        this.wsMessageQueue = [];
        
        queue.forEach(messageData => {
            this.sendMessageToWebSocket(messageData);
        });
    }

    destroy() {
        this.stopPolling();
        this.closeImagePreview();
        this.setCustomerOffline();
        
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
