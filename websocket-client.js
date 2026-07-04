/**
 * WebSocket 客户端
 * 连接端口：8288
 */

class WebSocketClient {
    constructor(options = {}) {
        this.options = {
            server: options.server || window.location.hostname,
            port: options.port || 8288,
            path: options.path || '/',
            reconnect: options.reconnect !== false,
            maxReconnectAttempts: options.maxReconnectAttempts || 5,
            reconnectDelay: options.reconnectDelay || 3000,
            debug: options.debug || false
        };
        
        this.ws = null;
        this.connected = false;
        this.reconnectAttempts = 0;
        this.reconnectTimer = null;
        this.messageQueue = [];
        this.eventListeners = {};
        this.heartbeatInterval = null;
        
        // 事件回调
        this.onConnect = options.onConnect || null;
        this.onMessage = options.onMessage || null;
        this.onError = options.onError || null;
        this.onDisconnect = options.onDisconnect || null;
    }
    
    /**
     * 连接到 WebSocket 服务器
     */
    connect() {
        if (this.ws && (this.ws.readyState === WebSocket.OPEN || this.ws.readyState === WebSocket.CONNECTING)) {
            this.log('WebSocket 已连接或正在连接');
            return;
        }
        
        const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
        const url = `${protocol}//${window.location.host}/wss`;
        
        this.log(`连接 WebSocket: ${url}`);
        
        try {
            this.ws = new WebSocket(url);
            
            this.ws.onopen = (event) => this.handleOpen(event);
            this.ws.onmessage = (event) => this.handleMessage(event);
            this.ws.onerror = (event) => this.handleError(event);
            this.ws.onclose = (event) => this.handleClose(event);
            
        } catch (error) {
            this.log(`创建 WebSocket 连接失败: ${error.message}`, 'error');
            this.handleError({ type: 'error', error: error });
        }
    }
    
    /**
     * 处理连接打开
     */
    handleOpen(event) {
        this.log('✅ WebSocket 连接成功');
        this.connected = true;
        this.reconnectAttempts = 0;
        
        // 启动心跳
        this.startHeartbeat();
        
        // 触发连接事件
        this.trigger('connect', event);
        
        if (typeof this.onConnect === 'function') {
            this.onConnect(event);
        }
    }
    
    /**
     * 处理消息
     */
    handleMessage(event) {
        try {
            const data = JSON.parse(event.data);
            this.log('收到消息:', data);
            
            // 触发消息事件
            this.trigger('message', data);
            
            if (typeof this.onMessage === 'function') {
                this.onMessage(data);
            }
            
        } catch (error) {
            this.log(`解析消息失败: ${error.message}`, 'error');
        }
    }
    
    /**
     * 处理错误
     */
    handleError(event) {
        this.log('WebSocket 错误:', event);
        this.trigger('error', event);
        
        if (typeof this.onError === 'function') {
            this.onError(event);
        }
    }
    
    /**
     * 处理连接关闭
     */
    handleClose(event) {
        this.log(`WebSocket 连接关闭: ${event.code} ${event.reason || ''}`);
        this.connected = false;
        
        // 停止心跳
        this.stopHeartbeat();
        
        // 触发断开连接事件
        this.trigger('disconnect', event);
        
        if (typeof this.onDisconnect === 'function') {
            this.onDisconnect(event);
        }
        
        // 尝试重连
        if (this.options.reconnect) {
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
        
        if (this.connected && this.ws.readyState === WebSocket.OPEN) {
            try {
                this.ws.send(data);
                this.log('发送消息:', data);
                return true;
            } catch (error) {
                this.log(`发送消息失败: ${error.message}`, 'error');
                this.messageQueue.push(data);
                return false;
            }
        } else {
            this.log('WebSocket 未连接，将消息加入队列');
            this.messageQueue.push(data);
            return false;
        }
    }
    
    /**
     * 发送身份验证
     */
    sendAuth(userType, userId, sessionKey) {
        const authData = {
            type: 'auth',
            user_type: userType,
            user_id: userId,
            session_key: sessionKey
        };
        
        return this.send(authData);
    }
    
    /**
     * 发送消息
     */
    sendMessage(sessionKey, content, agentAccount, customerName, speakerType = 2, platform = 'Web') {
        const messageData = {
            type: 'send_message',
            session_key: sessionKey,
            content: content,
            agent_account: agentAccount,
            customer_name: customerName,
            speaker_type: speakerType,
            platform: platform
        };
        
        return this.send(messageData);
    }
    
    /**
     * 开始心跳检测
     */
    startHeartbeat() {
        this.stopHeartbeat();
        
        this.heartbeatInterval = setInterval(() => {
            if (this.connected && this.ws.readyState === WebSocket.OPEN) {
                const heartbeat = {
                    type: 'ping',
                    timestamp: Date.now()
                };
                this.ws.send(JSON.stringify(heartbeat));
            }
        }, 30000); // 30秒一次心跳
    }
    
    /**
     * 停止心跳检测
     */
    stopHeartbeat() {
        if (this.heartbeatInterval) {
            clearInterval(this.heartbeatInterval);
            this.heartbeatInterval = null;
        }
    }
    
    /**
     * 安排重连
     */
    scheduleReconnect() {
        if (this.reconnectAttempts >= this.options.maxReconnectAttempts) {
            this.log('已达到最大重连次数，停止重连');
            return;
        }
        
        this.reconnectAttempts++;
        const delay = this.options.reconnectDelay * Math.pow(1.5, this.reconnectAttempts - 1);
        
        this.log(`将在 ${delay}ms 后尝试第 ${this.reconnectAttempts} 次重连`);
        
        clearTimeout(this.reconnectTimer);
        this.reconnectTimer = setTimeout(() => {
            this.log('尝试重连 WebSocket...');
            this.connect();
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
     * 添加事件监听器
     */
    on(event, callback) {
        if (!this.eventListeners[event]) {
            this.eventListeners[event] = [];
        }
        this.eventListeners[event].push(callback);
    }
    
    /**
     * 移除事件监听器
     */
    off(event, callback) {
        if (!this.eventListeners[event]) return;
        
        if (callback) {
            const index = this.eventListeners[event].indexOf(callback);
            if (index > -1) {
                this.eventListeners[event].splice(index, 1);
            }
        } else {
            this.eventListeners[event] = [];
        }
    }
    
    /**
     * 触发事件
     */
    trigger(event, data) {
        if (this.eventListeners[event]) {
            this.eventListeners[event].forEach(callback => {
                try {
                    callback(data);
                } catch (error) {
                    this.log(`事件监听器 ${event} 执行错误: ${error.message}`, 'error');
                }
            });
        }
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
    
    /**
     * 获取连接状态
     */
    get isConnected() {
        return this.connected && this.ws && this.ws.readyState === WebSocket.OPEN;
    }
    
    /**
     * 获取就绪状态
     */
    get readyState() {
        return this.ws ? this.ws.readyState : WebSocket.CLOSED;
    }
}