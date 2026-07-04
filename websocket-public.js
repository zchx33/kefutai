/**
 * WebSocket 公共连接管理器
 * 用于在所有页面保持 WebSocket 连接，确保在线状态
 */

class WebSocketManager {
    constructor(options = {}) {
        this.options = {
            server: options.server || window.location.hostname,
            port: options.port || 8288,
            reconnect: options.reconnect !== false,
            maxReconnectAttempts: options.maxReconnectAttempts || 10,
            reconnectDelay: options.reconnectDelay || 3000,
            heartbeatInterval: options.heartbeatInterval || 30000, // 30 秒心跳
            debug: options.debug || false
        };
        
        this.ws = null;
        this.connected = false;
        this.reconnectAttempts = 0;
        this.reconnectTimer = null;
        this.heartbeatTimer = null;
        this.userType = null;
        this.userId = null;
        this.sessionKey = null;
        this.onCustomMessage = null;
        
        // 页面可见性处理
        this.pageVisible = true;
        this.initVisibilityHandler();
    }
    
    /**
     * 初始化 WebSocket 连接
     */
    connect(userType, userId, sessionKey) {
        if (this.ws && (this.ws.readyState === WebSocket.OPEN || this.ws.readyState === WebSocket.CONNECTING)) {
            this.log('WebSocket 已连接或正在连接，无需重复连接');
            return;
        }
        
        this.userType = userType;
        this.userId = userId;
        this.sessionKey = sessionKey;
        
        const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
        const url = `${protocol}//${window.location.host}/wss`;
        
        this.log(`开始连接 WebSocket: ${url}`);
        
        try {
            this.ws = new WebSocket(url);
            
            this.ws.onopen = () => this.handleOpen();
            this.ws.onmessage = (event) => this.handleMessage(event);
            this.ws.onerror = (event) => this.handleError(event);
            this.ws.onclose = (event) => this.handleClose(event);
            
        } catch (error) {
            this.log(`创建 WebSocket 连接失败：${error.message}`, 'error');
            this.scheduleReconnect();
        }
    }
    
    /**
     * 处理连接打开
     */
    handleOpen() {
        this.log('✅ WebSocket 连接成功');
        this.connected = true;
        this.reconnectAttempts = 0;
        
        // 发送身份验证
        this.sendAuth();
        
        // 启动心跳
        this.startHeartbeat();
    }
    
    /**
     * 发送身份验证
     */
    sendAuth() {
        const authData = {
            type: 'auth',
            user_type: this.userType,
            user_id: this.userId,
            session_key: this.sessionKey
        };
        
        this.send(authData);
        this.log('已发送身份验证');
    }
    
    /**
     * 处理消息
     */
    handleMessage(event) {
        try {
            const data = JSON.parse(event.data);
            this.log('收到消息:', data);
            
            // 处理不同类型的消息
            switch (data.type) {
                case 'auth_success':
                    this.log('✅ 身份验证成功');
                    break;
                    
                case 'auth_failed':
                    this.log('❌ 身份验证失败：' + data.message, 'warn');
                    break;
                    
                case 'pong':
                    // 心跳响应，无需处理
                    break;
                    
                case 'new_message':
                    // 收到新消息
                    this.handleNewMessage(data);
                    break;
                    
                case 'message_sent':
                    // 消息发送成功回执
                    this.log('消息发送成功：' + data.message_id);
                    break;
                    
                default:
                    // 自定义消息处理
                    if (this.onCustomMessage) {
                        this.onCustomMessage(data);
                    }
            }
        } catch (error) {
            this.log(`解析消息失败：${error.message}`, 'error');
        }
    }
    
    /**
     * 处理新消息
     */
    handleNewMessage(data) {
        // 可以在这里添加消息通知逻辑
        this.log(`收到新消息：${data.content}`);
        
        // 触发自定义事件
        const event = new CustomEvent('websocket-new-message', { detail: data });
        window.dispatchEvent(event);
    }
    
    /**
     * 处理错误
     */
    handleError(event) {
        this.log('WebSocket 错误:', event, 'error');
        this.triggerError(event);
    }
    
    /**
     * 处理连接关闭
     */
    handleClose(event) {
        this.log(`WebSocket 连接关闭：${event.code} ${event.reason || ''}`);
        this.connected = false;
        
        // 停止心跳
        this.stopHeartbeat();
        
        // 尝试重连
        if (this.options.reconnect && event.code !== 1000) {
            this.scheduleReconnect();
        }
    }
    
    /**
     * 发送消息
     */
    send(data) {
        if (typeof data !== 'string') {
            data = JSON.stringify(data);
        }
        
        if (this.connected && this.ws && this.ws.readyState === WebSocket.OPEN) {
            try {
                this.ws.send(data);
                return true;
            } catch (error) {
                this.log(`发送消息失败：${error.message}`, 'error');
                return false;
            }
        } else {
            this.log('WebSocket 未连接，消息发送失败');
            return false;
        }
    }
    
    /**
     * 发送心跳
     */
    sendHeartbeat() {
        if (this.connected && this.ws && this.ws.readyState === WebSocket.OPEN) {
            const heartbeat = {
                type: 'heartbeat',
                timestamp: Date.now()
            };
            this.send(heartbeat);
        }
    }
    
    /**
     * 开始心跳
     */
    startHeartbeat() {
        this.stopHeartbeat();
        
        this.heartbeatTimer = setInterval(() => {
            this.sendHeartbeat();
        }, this.options.heartbeatInterval);
        
        this.log('心跳已启动，间隔：' + (this.options.heartbeatInterval / 1000) + '秒');
    }
    
    /**
     * 停止心跳
     */
    stopHeartbeat() {
        if (this.heartbeatTimer) {
            clearInterval(this.heartbeatTimer);
            this.heartbeatTimer = null;
        }
    }
    
    /**
     * 安排重连
     */
    scheduleReconnect() {
        if (this.reconnectAttempts >= this.options.maxReconnectAttempts) {
            this.log('已达到最大重连次数，停止重连', 'warn');
            return;
        }
        
        this.reconnectAttempts++;
        const delay = Math.min(
            this.options.reconnectDelay * Math.pow(1.5, this.reconnectAttempts - 1),
            60000 // 最多等待 60 秒
        );
        
        this.log(`将在 ${delay}ms 后尝试第 ${this.reconnectAttempts} 次重连`);
        
        clearTimeout(this.reconnectTimer);
        this.reconnectTimer = setTimeout(() => {
            this.log('尝试重连 WebSocket...');
            this.connect(this.userType, this.userId, this.sessionKey);
        }, delay);
    }
    
    /**
     * 断开连接
     */
    disconnect() {
        this.log('主动断开 WebSocket 连接');
        
        clearTimeout(this.reconnectTimer);
        this.stopHeartbeat();
        
        if (this.ws) {
            this.ws.close(1000, '用户主动断开');
            this.ws = null;
        }
        
        this.connected = false;
    }
    
    /**
     * 设置自定义消息处理器
     */
    onMessage(callback) {
        this.onCustomMessage = callback;
    }
    
    /**
     * 触发错误事件
     */
    triggerError(event) {
        const errorEvent = new CustomEvent('websocket-error', { detail: event });
        window.dispatchEvent(errorEvent);
    }
    
    /**
     * 页面可见性处理
     */
    initVisibilityHandler() {
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                this.pageVisible = false;
                this.log('页面隐藏');
            } else {
                this.pageVisible = true;
                this.log('页面可见');
                // 页面重新可见时，如果未连接则重连
                if (!this.connected) {
                    this.connect(this.userType, this.userId, this.sessionKey);
                }
            }
        });
    }
    
    /**
     * 获取连接状态
     */
    isConnected() {
        return this.connected && this.ws && this.ws.readyState === WebSocket.OPEN;
    }
    
    /**
     * 获取就绪状态
     */
    getReadyState() {
        return this.ws ? this.ws.readyState : WebSocket.CLOSED;
    }
    
    /**
     * 日志
     */
    log(message, level = 'info') {
        if (!this.options.debug && level === 'info') return;
        
        const timestamp = new Date().toLocaleTimeString();
        const prefix = `[WebSocket ${timestamp}]`;
        
        switch (level) {
            case 'error':
                console.error(prefix, message);
                break;
            case 'warn':
                console.warn(prefix, message);
                break;
            default:
                console.log(prefix, message);
        }
    }
}

/**
 * 初始化 WebSocket 连接
 * 在页面加载时自动调用
 */
function initWebSocketConnection() {
    // 检查用户是否已登录
    const userInfo = getUserInfo();
    
    if (!userInfo) {
        console.log('[WebSocket] 用户未登录，跳过连接');
        return null;
    }
    
    // 创建 WebSocket 管理器
    const wsManager = new WebSocketManager({
        debug: true, // 生产环境可改为 false
        reconnect: true,
        heartbeatInterval: 30000 // 30 秒心跳
    });
    
    // 连接到 WebSocket
    wsManager.connect(userInfo.userType, userInfo.userId, userInfo.sessionKey);
    
    // 保存到全局
    window.wsManager = wsManager;
    
    // 监听新消息
    window.addEventListener('websocket-new-message', (event) => {
        console.log('[WebSocket] 收到新消息:', event.detail);
        // 可以在这里添加消息通知逻辑
    });
    
    // 监听错误
    window.addEventListener('websocket-error', (event) => {
        console.error('[WebSocket] 发生错误:', event.detail);
    });
    
    console.log('[WebSocket] 初始化完成');
    
    return wsManager;
}

/**
 * 获取用户信息
 * 根据实际项目调整
 */
function getUserInfo() {
    // 尝试从 sessionStorage 获取
    const userData = sessionStorage.getItem('user_data');
    if (userData) {
        try {
            const user = JSON.parse(userData);
            // 正确映射 PHP 的 role 到 WebSocket 的 userType
            // PHP 可能返回字符串或数字类型的 role
            let userType = 'customer';
            if (user.role === 'admin' || user.role === 'agent' || user.role === 1 || user.role === '1') {
                userType = 'agent';
            }
            return {
                userType: userType,
                userId: user.username,
                sessionKey: user.session_key || sessionStorage.getItem('session_key') || ''
            };
        } catch (e) {
            console.error('[WebSocket] 解析用户数据失败', e);
        }
    }
    
    // 尝试从全局变量获取
    if (window.currentUser) {
        // 正确映射 PHP 的 role 到 WebSocket 的 userType
        let userType = 'customer';
        if (window.currentUser.role === 'admin' || window.currentUser.role === 'agent' || window.currentUser.role === 1 || window.currentUser.role === '1') {
            userType = 'agent';
        }
        return {
            userType: userType,
            userId: window.currentUser.username,
            sessionKey: window.currentUser.session_key || ''
        };
    }
    
    return null;
}

/**
 * 页面卸载时断开连接
 */
window.addEventListener('beforeunload', () => {
    if (window.wsManager) {
        // 不要完全断开，保持连接以便其他标签页
        // window.wsManager.disconnect();
        console.log('[WebSocket] 页面卸载，保持连接');
    }
});

// 自动初始化（如果页面已完全加载）
if (document.readyState === 'complete' || document.readyState === 'interactive') {
    // DOM 已就绪，稍后初始化
    setTimeout(() => {
        initWebSocketConnection();
    }, 100);
} else {
    // 等待 DOM 加载完成
    document.addEventListener('DOMContentLoaded', () => {
        setTimeout(() => {
            initWebSocketConnection();
        }, 100);
    });
}
