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
	<link rel="icon" type="image/ico" href="/assets/img/jym.ico" />
	<title>交易猫官方客服</title>
	<link rel="stylesheet" href="/assets/Kefu/jiaoyimao.css">
</head>
<body>
    <div class="xile-loading-container" id="loadingContainer"> 
        <div class="xile-loading-spinner"></div> 
        <div class="xile-loading-text">正在连接客服...</div> 
    </div>
	<div id="app-wrapper">
	<div id="app" data-v-app="">
		<div class="XEjym-chat-app"><svg xmlns="http://www.w3.org/2000/svg" style="display: none;">
				<symbol id="icon-image" viewBox="0 0 48 48">
					<path d="M37 6.5a7.5 7.5 0 0 1 7.5 7.5v21a7.5 7.5 0 0 1-7.5 7.5H11A7.5 7.5 0 0 1 3.5 35V14A7.5 7.5 0 0 1 11 6.5Zm0 3H11A4.5 4.5 0 0 0 6.5 14v21a4.5 4.5 0 0 0 4.5 4.5h26a4.5 4.5 0 0 0 4.5-4.5V14A4.5 4.5 0 0 0 37 9.5Zm2.493 13.358a1.5 1.5 0 0 1-1.351 1.635c-2.463.234-4.205.896-5.268 1.928-.249.241-.46.526-.696.946l-.183.34-.198.4-.258.55-.252.523-.25.483c-1.083 2.009-2.188 2.909-4.358 3.05-1.272.084-2.44-.05-4.01-.396l-1.97-.464-.614-.137-.284-.06-.528-.1c-1.007-.179-1.747-.218-2.6-.15-1.896.152-3.693 1.547-5.387 4.367a1.5 1.5 0 1 1-2.572-1.546c2.148-3.574 4.72-5.571 7.719-5.811 1.393-.112 2.497-.012 4.176.344l.704.156 1.633.388c1.516.352 2.515.483 3.536.416.944-.062 1.377-.41 2.039-1.713l.196-.4.257-.549c.62-1.322 1.072-2.072 1.81-2.79 1.615-1.567 3.987-2.468 7.074-2.761a1.5 1.5 0 0 1 1.635 1.351ZM14.5 13a4.5 4.5 0 1 1 0 9 4.5 4.5 0 0 1 0-9Zm0 3a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3Z">
					</path>
				</symbol>
			</svg>
			<div class="XEjym-main-container">
				<div class="XEjym-header">
					<div>交易猫智能小喵</div>
				</div>
				<div class="XEjym-messages-container" id="XEjym-messages-container">
				    <div class="XEjym-message-row left" style="--emptySize: 4.125rem; --messageBottom: .75rem; --messagePadding: 0;">
						<div class="XEjym-message-avatar" style="--avatarSize: 2.25rem; --avatarBorderRadius: 50%; --avatarBackground: transparent;"><img src="/assets/img/jym-kf.jpg"></div>
						<div class="XEjym-message-content" style="--avatarMainGap: .25rem;">
							<div class="XEjym-sender-info">交易员048231</div>
							<div class="XEjym-message-bubble left">您好!欢迎来到本平台交易，我是您的专属客服。非常感谢您选择我们平台来进行游戏账号的交易。在这里，我们致力于为您提供安全、可靠、高效的服务体验。</div><!---->
						</div>
					</div>
		      </div>
				<div class="XEjym-bottom-area">
					<div class="XEjym-quick-buttons-container">
						<div class="XEjym-quick-buttons-scroll">
							<div class="XEjym-quick-buttons-inner">
								<div class="XEjym-quick-button-item"><button class="XEjym-quick-button" type="button" data-text="催发货">
										<div class=""><span>催发货</span></div>
									</button></div>
								<div class="XEjym-quick-button-item"><button class="XEjym-quick-button" type="button" data-text="催退款">
										<div class=""><span>催退款</span></div>
									</button></div>
								<div class="XEjym-quick-button-item"><button class="XEjym-quick-button" type="button" data-text="怎么联系卖家">
										<div class=""><span>怎么联系卖家</span></div>
									</button></div>
								<div class="XEjym-quick-button-item"><button class="XEjym-quick-button" type="button" data-text="账号被找回">
										<div class=""><span>账号被找回</span></div>
									</button></div>
								<div class="XEjym-quick-button-item"><button class="XEjym-quick-button" type="button" data-text="卖号问题咨询">
										<div class=""><span>卖号问题咨询</span></div>
									</button></div>
							</div>
						</div>
					</div>
					<div class="XEjym-input-area">
						<div class="XEjym-input-container">
							<div><input class="XEjym-input" id="XEjym-input" enterkeyhint="send" placeholder="请输入您想要咨询的问题....."></div>
						</div>
						<div class="XEjym-icon-container"><label for="XEjym-upload" class="">
								<div class="XEjym-image-button"><svg xmlns="http://www.w3.org/2000/svg" version="1.1" viewBox="0 0 48 48">
										<use xlink:href="#icon-image"></use>
									</svg></div>
							</label><input type="file" accept="image/*" id="XEjym-upload" class="" style="display: none;"></div>
						<div class="XEjym-icon-container"><button class="XEjym-send-button" id="XEjym-send-button" type="button">发送</button></div>
					</div>
				</div>
			</div>
		</div>
	</div>
	<!-- 图片预览模态框 -->
	<div id="XEjym-image-preview-modal" class="XEjym-image-preview-modal">
		<img id="XEjym-image-preview-content" src="" alt="预览图片" class="XEjym-image-preview-content">
	</div>
	<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
// 修正版 CustomerChatSystem
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
        this.platform = '交易猫';
        
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
        
        console.log('交易猫聊天系统初始化:', {
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
        $('#XEjym-send-button').on('click', function() {
            self.sendMessage();
        });
        
        // 输入框回车发送
        $('#XEjym-input').on('keypress', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                self.sendMessage();
            }
        });
        
        // 输入框输入事件
        $('#XEjym-input').on('input', function() {
            self.updateSendButton();
        });
        
        // 快捷按钮
        $('.XEjym-quick-button').on('click', function() {
            const text = $(this).find('span').text() || $(this).text();
            $('#XEjym-input').val(text);
            self.updateSendButton();
        });
        
        // 图片上传
        $('#XEjym-upload').on('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                self.uploadImage(file);
            }
            $(this).val('');
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
        const hasText = $('#XEjym-input').val().trim().length > 0;
        const sendButton = $('#XEjym-send-button');
        
        if (hasText) {
            sendButton.prop('disabled', false);
        } else {
            sendButton.prop('disabled', true);
        }
    }
    
    sendMessage() {
        if (this.isSending) {
            return;
        }

        const input = $('#XEjym-input');
        const content = input.val().trim();

        if (!content) {
            this.updateSendButton();
            return;
        }

        this.isSending = true;
        this.updateSendButton();

        const self = this;

        // 添加消息到界面
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

        // 清空输入框
        input.val('');
        this.updateSendButton();
        this.scrollToBottom();

        // 更新在线状态
        this.updateCustomerOnlineStatus();

        // 准备 WebSocket 消息数据
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

        // 准备 API 消息数据
        const apiMessageData = {
            action: 'send_message',
            session_id: this.sessionId,
            agent_account: this.agentAccount,
            speaker_type: 1,
            content: content,
            customer_name: this.customerName,
            platform: this.platform
        };

        // 尝试通过 WebSocket 发送(仅用于实时推送)
        if (this.wsConnected && this.ws && this.ws.readyState === WebSocket.OPEN) {
            console.log('尝试通过 WebSocket 发送(实时推送)');
            this.sendMessageToWebSocket(wsMessageData);
        }

        // 始终通过 API 保存消息到数据库
        $.ajax({
            url: this.apiBaseUrl,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(apiMessageData),
            success: function(data) {
                console.log('API 保存响应:', data);
                self.isSending = false;
                self.updateSendButton();
                if (data.success && data.message_id) {
                    self.lastMessageId = Math.max(self.lastMessageId, data.message_id);
                    
                    // 更新消息ID
                    if ($(`[data-message-id="${tempId}"]`).length > 0) {
                        $(`[data-message-id="${tempId}"]`).attr('data-message-id', data.message_id);
                    }
                    
                    // 记录最近发送的消息 ID,用于 WebSocket 去重
                    self.recentlySentMessageIds.add(data.message_id);
                    setTimeout(() => {
                        self.recentlySentMessageIds.delete(data.message_id);
                    }, 5000);
                }
            },
            error: function(xhr, status, error) {
                console.error('保存消息到数据库失败:', error);
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
        $(document).on('click', '.XEjym-message-image', function() {
            const imageUrl = $(this).attr('src');
            if (imageUrl) {
                $('#XEjym-image-preview-content').attr('src', imageUrl);
                $('#XEjym-image-preview-modal').addClass('active');
            }
        });
        
        // 关闭预览
        $('#XEjym-image-preview-modal').on('click', function(e) {
            if (e.target === this || $(e.target).hasClass('XEjym-image-preview-modal')) {
                $(this).removeClass('active');
                $('#XEjym-image-preview-content').attr('src', '');
            }
        });
        
        // ESC键关闭
        $(document).on('keyup', function(e) {
            if (e.key === 'Escape') {
                $('#XEjym-image-preview-modal').removeClass('active');
                $('#XEjym-image-preview-content').attr('src', '');
            }
        });
    }
    
    uploadImage(file) {
        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/bmp'];
        const maxSize = 5 * 1024 * 1024;

        if (!allowedTypes.includes(file.type)) {
            this.showTips('请选择图片文件 (JPEG, PNG, GIF, WebP, BMP)');
            return;
        }

        if (file.size > maxSize) {
            this.showTips('图片大小不能超过 5MB');
            return;
        }

        if (this.isUploadingImage) {
            this.showTips('正在上传图片，请稍候...');
            return;
        }

        this.isUploadingImage = true;
        const self = this;

        // 创建本地预览
        const localPreviewUrl = URL.createObjectURL(file);

        // 添加临时图片消息
        self._sentMessageCounter++;
        const tempId = 'temp_img_' + Date.now() + '_' + self._sentMessageCounter;
        self.appendMessages([{
            id: tempId,
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

        // 记录已发送图片消息，用于去重
        self._lastSentMessages.push({
            tempId: tempId,
            content: '[图片]',
            speaker_type: 1,
            messageType: 'image',
            timestamp: Date.now()
        });
        if (self._lastSentMessages.length > 20) {
            self._lastSentMessages.shift();
        }

        self.scrollToBottom();

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
                
                if (data.success && data.image_url) {
                    console.log('图片上传成功:', data.message_id);
                    
                    URL.revokeObjectURL(localPreviewUrl);
                    
                    const tempElement = $(`[data-message-id="${tempId}"]`);
                    if (tempElement && data.message_id) {
                        tempElement.attr('data-message-id', data.message_id);
                        const img = tempElement.find('img');
                        if (img) {
                            img.attr('src', data.image_url);
                        }
                        self.lastMessageId = Math.max(self.lastMessageId, data.message_id);
                        
                        if (self.wsConnected && self.ws && self.ws.readyState === WebSocket.OPEN) {
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
                        
                        self.showTips('图片上传成功');
                    }
                } else {
                    console.error('图片上传失败:', data.message);
                    URL.revokeObjectURL(localPreviewUrl);
                    const tempElement = $(`[data-message-id="${tempId}"]`);
                    if (tempElement) {
                        const bubble = tempElement.find('.XEjym-message-bubble');
                        if (bubble) {
                            bubble.html('<span>图片上传失败: ' + (data.message || '未知错误') + '</span>');
                        }
                    }
                    self.showTips('图片上传失败');
                }
            },
            error: function(xhr, status, error) {
                self.isUploadingImage = false;
                console.error('图片上传请求失败:', error);
                URL.revokeObjectURL(localPreviewUrl);
                const tempElement = $(`[data-message-id="${tempId}"]`);
                if (tempElement) {
                    const bubble = tempElement.find('.XEjym-message-bubble');
                    if (bubble) {
                        bubble.html('<span>图片上传失败，请重试</span>');
                    }
                }
                self.showTips('图片上传失败，请重试');
            }
        });
    }
    
    showTips(text) {
        if ($('#tipContainer').length === 0) {
            $('body').append(`
                <div id="tipContainer" style="
                    position: fixed;
                    top: 20%;
                    left: 50%;
                    transform: translateX(-50%);
                    background: rgba(0,0,0,0.7);
                    color: white;
                    padding: 12px 20px;
                    border-radius: 8px;
                    z-index: 10000;
                    text-align: center;
                    max-width: 80%;
                    opacity: 0;
                    transition: opacity 0.3s;
                "></div>
            `);
        }
        
        $('#tipContainer').text(text).css('opacity', 1);
        
        setTimeout(() => {
            $('#tipContainer').css('opacity', 0);
        }, 2000);
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
        const container = $('#XEjym-messages-container');
        
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
                        <div class="XEjym-message-row right no-avatar" data-message-id="${message.id || 'temp_' + Date.now()}">
                            <div class="XEjym-message-content">
                                <div class="XEjym-message-bubble right">
                                    <img class="XEjym-message-image" src="${imageUrl}" alt="图片" onerror="this.style.display='none'; this.parentNode.innerHTML='<p>[图片加载失败]</p>';">
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
                            <div class="XEjym-message-row right no-avatar" data-message-id="${message.id || 'temp_' + Date.now()}">
                                <div class="XEjym-message-content">
                                    <div class="XEjym-message-bubble right">
                                        ${cardHtml}
                                    </div>
                                </div>
                            </div>
                        `;
                    } else {
                        messageHtml = `
                            <div class="XEjym-message-row right no-avatar" data-message-id="${message.id || 'temp_' + Date.now()}">
                                <div class="XEjym-message-content">
                                    <div class="XEjym-message-bubble right">${this.escapeHtml(message.content)}</div>
                                </div>
                            </div>
                        `;
                    }
                }
            } else { // 客服消息
                if (message.message_type === 'image' && (message.image_url || message.image_path)) {
                    const imageUrl = message.image_url || (message.image_path ? '/uploads/' + message.image_path : '');
                    messageHtml = `
                        <div class="XEjym-message-row left" data-message-id="${message.id || 'temp_' + Date.now()}">
                            <div class="XEjym-message-avatar">
                                <img src="/assets/img/jym-kf.jpg">
                            </div>
                            <div class="XEjym-message-content">
                                <div class="XEjym-sender-info">交易员048231</div>
                                <div class="XEjym-message-bubble left">
                                    <img class="XEjym-message-image" src="${imageUrl}" alt="图片" onerror="this.style.display='none'; this.parentNode.innerHTML='<p>[图片加载失败]</p>';">
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
                            <div class="XEjym-message-row left" data-message-id="${message.id || 'temp_' + Date.now()}">
                                <div class="XEjym-message-avatar">
                                    <img src="/assets/img/jym-kf.jpg">
                                </div>
                                <div class="XEjym-message-content">
                                    <div class="XEjym-sender-info">交易员048231</div>
                                    <div class="XEjym-message-bubble left">
                                        ${cardHtml}
                                    </div>
                                </div>
                            </div>
                        `;
                    } else {
                        messageHtml = `
                            <div class="XEjym-message-row left" data-message-id="${message.id || 'temp_' + Date.now()}">
                                <div class="XEjym-message-avatar">
                                    <img src="/assets/img/jym-kf.jpg">
                                </div>
                                <div class="XEjym-message-content">
                                    <div class="XEjym-sender-info">交易员048231</div>
                                    <div class="XEjym-message-bubble left">${this.escapeHtml(message.content)}</div>
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

        if (this.wsConnected && this.ws.readyState === WebSocket.OPEN) {
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
                        if ($(`[data-message-id="${msg.id}"]`).length > 0) {
                            return false;
                        }

                        if (self.recentlyReceivedWsMessageIds && self.recentlyReceivedWsMessageIds.has(msg.id)) {
                            return false;
                        }

                        return true;
                    });
                    
                    if (newMessages.length > 0) {
                        self.appendMessages(newMessages);
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
    
    scrollToBottom() {
        const container = $('#XEjym-messages-container');
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
    
    startStatusPolling() {
        const self = this;

        this.updateOnlineStatus();

        this.setupPageVisibilityListener();

        this.statusPollingInterval = setInterval(function() {
            self.updateOnlineStatus();
        }, 10000);

        console.log('客户在线状态轮询已启动（10秒间隔）');
    }

    updateOnlineStatus(customStatus = null) {
        let status = customStatus;
        if (!status) {
            status = this.pageVisible ? 'online' : 'hidden';
        }

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
        console.log('聊天系统已销毁');
    }
}

// 初始化聊天系统
$(document).ready(function() {
    console.log('文档加载完成，初始化交易猫聊天系统...');
    window.chatSystem = new CustomerChatSystem();
    
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