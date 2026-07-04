/**
 * 推送通知管理器
 * 支持浏览器原生通知、自定义顶部通知、PWA离线推送
 */

class PushNotificationManager {
    constructor(options = {}) {
        this.options = {
            enableBrowserNotification: options.enableBrowserNotification || false,
            enableCustomToast: options.enableCustomToast !== false,
            icon: options.icon || '/xe-icon.png',
            badge: options.badge || '/xe-icon.png',
            ...options
        };
        
        this.permission = 'default';
        this.customNotificationManager = null;
        this.pushSubscription = null;
        this.swRegistration = null;
        
        // 检查浏览器通知支持
        this.browserNotificationSupported = 'Notification' in window;
        this.pushSupported = 'serviceWorker' in navigator && 'PushManager' in window;
        
        // 检查安全上下文（Service Worker需要HTTPS或localhost）
        this.isSecureContext = window.isSecureContext;
        if (!this.isSecureContext && this.pushSupported) {
            console.warn('[推送通知] 当前非安全上下文(HTTP)，Service Worker和Push API不可用。需要HTTPS或localhost。');
            this.pushSupported = false;
        }
        
        // 初始化自定义通知管理器
        if (this.options.enableCustomToast && typeof MessageNotification !== 'undefined') {
            this.customNotificationManager = new MessageNotification({
                duration: 5000,
                clickable: true,
                position: 'top-left'
            });
        }
        
        // 检查当前权限
        if (this.browserNotificationSupported) {
            this.permission = Notification.permission;
            console.log('[推送通知] 浏览器通知支持，当前权限:', this.permission);
        }
        
        if (this.pushSupported) {
            console.log('[推送通知] Push API 支持');
        }
    }
    
    /**
     * 注册 Service Worker
     */
    async registerServiceWorker() {
        if (!('serviceWorker' in navigator)) {
            console.log('[推送通知] 浏览器不支持Service Worker');
            return null;
        }
        
        try {
            const registration = await navigator.serviceWorker.register('/sw.js', { scope: '/' });
            console.log('[推送通知] Service Worker 注册成功');
            this.swRegistration = registration;
            
            // 监听 Service Worker 消息（免打扰列表请求等）
            navigator.serviceWorker.addEventListener('message', (event) => {
                if (event.data && event.data.type === 'getMutedSessions') {
                    // 从localStorage获取免打扰列表
                    var mutedSessions = [];
                    try {
                        var settings = localStorage.getItem('chat_notification_settings');
                        if (settings) {
                            var parsed = JSON.parse(settings);
                            mutedSessions = parsed.mutedSessions || [];
                        }
                    } catch (e) {}
                    event.ports[0].postMessage(mutedSessions);
                }
            });
            
            return registration;
        } catch (error) {
            console.error('[推送通知] Service Worker 注册失败:', error);
            return null;
        }
    }
    
    /**
     * 订阅 Push 通知
     */
    async subscribePush(userId, userType) {
        if (!this.pushSupported) {
            console.log('[推送通知] 浏览器不支持Push API');
            return { success: false, message: '浏览器不支持Push API', errorName: 'NotSupportedError' };
        }
        
        try {
            // 确保Service Worker已注册
            if (!this.swRegistration) {
                await this.registerServiceWorker();
            }
            
            if (!this.swRegistration) {
                return { success: false, message: 'Service Worker注册失败', errorName: 'SWError' };
            }
            
            // 获取VAPID公钥
            const vapidResponse = await fetch('/api/push/vapid');
            const vapidData = await vapidResponse.json();
            
            if (!vapidData.success || !vapidData.publicKey) {
                console.error('[推送通知] 获取VAPID公钥失败:', vapidData);
                return { success: false, message: '获取VAPID公钥失败', errorName: 'VAPIDError' };
            }
            
            const applicationServerKey = this.urlB64ToUint8Array(vapidData.publicKey);
            
            // 检查是否已有订阅
            let subscription = await this.swRegistration.pushManager.getSubscription();
            
            if (!subscription) {
                // 创建新订阅 - 这会触发iOS的权限弹窗
                subscription = await this.swRegistration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: applicationServerKey
                });
                console.log('[推送通知] 新Push订阅创建成功');
            } else {
                console.log('[推送通知] 已有Push订阅');
            }
            
            this.pushSubscription = subscription;

            // 将订阅发送到服务器
            // 注意：getKey() 返回 ArrayBuffer，必须转为 Uint8Array 才能按索引访问
            const p256dhKey = subscription.getKey('p256dh');
            const authKey = subscription.getKey('auth');

            const subData = {
                user_id: userId,
                user_type: userType || 'agent',
                endpoint: subscription.endpoint,
                p256dh: p256dhKey ? this.uint8ArrayToBase64Url(new Uint8Array(p256dhKey)) : '',
                auth: authKey ? this.uint8ArrayToBase64Url(new Uint8Array(authKey)) : ''
            };

            console.log('[推送通知] 订阅数据:', {
                endpoint: subData.endpoint ? subData.endpoint.substring(0, 50) + '...' : 'empty',
                p256dh: subData.p256dh ? subData.p256dh.substring(0, 10) + '...' : 'empty',
                auth: subData.auth ? subData.auth.substring(0, 10) + '...' : 'empty'
            });

            const saveResponse = await fetch('/api/push/subscribe', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(subData)
            });

            const saveResult = await saveResponse.json();

            if (saveResult.success) {
                console.log('[推送通知] Push订阅已保存到服务器');
                return { success: true, subscription: subscription };
            } else {
                console.error('[推送通知] Push订阅保存失败:', saveResult);
                return { success: false, message: '订阅保存失败: ' + (saveResult.message || '未知错误'), errorName: 'SaveError' };
            }
        } catch (error) {
            console.error('[推送通知] Push订阅失败:', error);
            return { success: false, message: error.message, errorName: error.name || 'UnknownError' };
        }
    }
    
    /**
     * 取消 Push 订阅
     */
    async unsubscribePush(userId) {
        try {
            if (this.swRegistration) {
                const subscription = await this.swRegistration.pushManager.getSubscription();
                if (subscription) {
                    await subscription.unsubscribe();
                    console.log('[推送通知] Push订阅已取消');
                }
            }
            
            // 通知服务器删除订阅
            if (userId) {
                await fetch('/api/push/unsubscribe', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ user_id: userId })
                });
            }
            
            this.pushSubscription = null;
            return { success: true };
        } catch (error) {
            console.error('[推送通知] 取消Push订阅失败:', error);
            return { success: false, message: error.message };
        }
    }
    
    /**
     * 请求通知权限（同时订阅Push）
     * iOS PWA中必须通过pushManager.subscribe()触发权限弹窗，
     * Notification.requestPermission()会直接返回denied
     */
    async requestPermission(userId, userType) {
        // 先注册Service Worker并等待激活
        if (this.pushSupported) {
            await this.registerServiceWorker();
            // 等待SW激活
            await this.waitForServiceWorkerActive();
        }
        
        // 检查是否在iOS PWA环境中
        var isStandalone = window.navigator.standalone === true || 
                          window.matchMedia('(display-mode: standalone)').matches;
        var isIOSDevice = /iPhone|iPad|iPod/.test(navigator.userAgent) || 
                         (navigator.userAgent.includes("Mac") && "ontouchend" in document);
        
        // iOS PWA：直接通过pushManager.subscribe()请求权限
        if (isIOSDevice && isStandalone && this.pushSupported && userId) {
            console.log('[推送通知] iOS PWA环境，通过Push订阅请求权限');
            try {
                var subResult = await this.subscribePush(userId, userType);
                if (subResult.success) {
                    this.permission = 'granted';
                    return {
                        granted: true,
                        supported: true,
                        permission: 'granted',
                        message: '已获得通知权限'
                    };
                } else {
                    // 检查是否是权限被拒绝
                    if (subResult.errorName === 'NotAllowedError') {
                        this.permission = 'denied';
                        return {
                            granted: false,
                            supported: true,
                            permission: 'denied',
                            message: '通知权限被拒绝，请在iPhone设置中找到此应用并开启通知'
                        };
                    }
                    this.permission = Notification.permission || 'default';
                    return {
                        granted: false,
                        supported: true,
                        permission: this.permission,
                        message: '通知订阅失败：' + (subResult.message || '未知错误')
                    };
                }
            } catch (error) {
                console.error('[推送通知] iOS Push订阅异常:', error);
                if (error.name === 'NotAllowedError') {
                    this.permission = 'denied';
                    return {
                        granted: false,
                        supported: true,
                        permission: 'denied',
                        message: '通知权限被拒绝，请在iPhone设置中找到此应用并开启通知'
                    };
                }
                return {
                    granted: false,
                    supported: true,
                    message: '请求失败：' + error.message
                };
            }
        }
        
        // 非iOS环境：先尝试Notification.requestPermission()
        if (!this.browserNotificationSupported) {
            return {
                granted: false,
                supported: false,
                message: '浏览器不支持通知'
            };
        }
        
        try {
            // 如果支持Push，也通过subscribe触发权限（更可靠）
            if (this.pushSupported && userId) {
                var subResult = await this.subscribePush(userId, userType);
                if (subResult.success) {
                    this.permission = 'granted';
                    return {
                        granted: true,
                        supported: true,
                        permission: 'granted',
                        message: '已获得通知权限'
                    };
                }
                // subscribePush失败，降级用Notification API
                console.warn('[推送通知] Push订阅失败，降级使用Notification API');
            }
            
            var permission = await Notification.requestPermission();
            this.permission = permission;
            
            console.log('[推送通知] 权限请求结果:', permission);
            
            return {
                granted: permission === 'granted',
                supported: true,
                permission: permission,
                message: permission === 'granted' ? '已获得通知权限' : 
                        (permission === 'denied' ? '通知权限被拒绝' : '通知权限被禁用')
            };
        } catch (error) {
            console.error('[推送通知] 权限请求失败:', error);
            return {
                granted: false,
                supported: true,
                message: '请求失败：' + error.message
            };
        }
    }
    
    /**
     * 等待Service Worker激活
     */
    async waitForServiceWorkerActive() {
        if (!this.swRegistration) return;
        
        if (this.swRegistration.active) {
            return; // 已经激活
        }
        
        var reg = this.swRegistration;
        return new Promise(function(resolve) {
            if (reg.installing) {
                reg.installing.addEventListener('statechange', function(e) {
                    if (e.target.state === 'activated') resolve();
                });
            } else if (reg.waiting) {
                reg.waiting.addEventListener('statechange', function(e) {
                    if (e.target.state === 'activated') resolve();
                });
            } else {
                // 已经激活或无法确定状态，直接继续
                resolve();
            }
            
            // 超时3秒
            setTimeout(resolve, 3000);
        });
    }
    
    /**
     * 检查通知权限
     */
    checkPermission() {
        if (!this.browserNotificationSupported) {
            return {
                granted: false,
                supported: false,
                permission: 'unsupported'
            };
        }
        
        return {
            granted: this.permission === 'granted',
            supported: true,
            permission: this.permission
        };
    }
    
    /**
     * 发送推送通知（本地浏览器通知）
     */
    send(title, options = {}) {
        const body = options.body || '';
        const icon = options.icon || this.options.icon;
        const badge = options.badge || this.options.badge;
        const tag = options.tag || 'message-notification';
        const requireInteraction = options.requireInteraction !== false;
        const silent = options.silent || false;
        
        console.log('[推送通知] 发送通知:', title, body);
        
        // 1. 优先使用浏览器通知（如果已授权）
        if (this.browserNotificationSupported && this.permission === 'granted') {
            this.sendBrowserNotification(title, {
                body,
                icon,
                badge,
                tag,
                requireInteraction,
                silent,
                onClick: options.onClick,
                onClose: options.onClose
            });
            
            if (this.options.enableCustomToast && this.customNotificationManager) {
                this.sendCustomNotification(title, body, options);
            }
        }
        // 2. 否则使用自定义顶部通知
        else if (this.customNotificationManager) {
            this.sendCustomNotification(title, body, options);
        }
        // 3. 都不支持
        else {
            console.log('[推送通知] 无可用通知方式:', title, body);
        }
    }
    
    /**
     * 发送浏览器通知
     */
    sendBrowserNotification(title, options = {}) {
        try {
            const notification = new Notification(title, {
                body: options.body,
                icon: options.icon,
                badge: options.badge,
                tag: options.tag,
                requireInteraction: options.requireInteraction,
                silent: options.silent,
                vibrate: options.vibrate || [200, 100, 200]
            });
            
            notification.onclick = (event) => {
                event.preventDefault();
                window.focus();
                if (options.onClick) options.onClick(notification);
                notification.close();
            };
            
            notification.onclose = () => {
                if (options.onClose) options.onClose(notification);
            };
            
            notification.onerror = (error) => {
                console.error('[推送通知] 浏览器通知错误:', error);
            };
            
            if (options.autoClose !== false) {
                setTimeout(() => notification.close(), options.duration || 5000);
            }
        } catch (error) {
            console.error('[推送通知] 发送浏览器通知失败:', error);
        }
    }
    
    /**
     * 发送自定义顶部通知
     */
    sendCustomNotification(title, body, options = {}) {
        const message = body ? `${title}: ${body}` : title;
        this.customNotificationManager.show(message, {
            type: options.type || 'success',
            duration: options.duration || 5000,
            clickable: options.clickable !== false,
            onClick: () => {
                if (options.onClick) options.onClick();
            }
        });
    }
    
    /**
     * 显示新消息通知
     */
    showMessageNotification(sender, content, data = {}) {
        const title = `鱼 ${sender} 发来了一条新消息`;
        const body = content;
        
        this.send(title, {
            body: body,
            tag: `message-${data.message_id || Date.now()}`,
            data: data,
            onClick: () => {
                if (data.session_key) {
                    // window.location.href = '/home/chat.php?session=' + data.session_key;
                }
            }
        });
    }
    
    /**
     * 关闭所有通知
     */
    closeAll() {
        if (this.customNotificationManager) {
            this.customNotificationManager.closeAll();
        }
    }
    
    /**
     * 销毁通知管理器
     */
    destroy() {
        this.closeAll();
        this.customNotificationManager = null;
    }
    
    // ==================== 工具方法 ====================
    
    /**
     * Base64 URL 编码转 Uint8Array
     */
    urlB64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64 = (base64String + padding)
            .replace(/\-/g, '+')
            .replace(/_/g, '/');
        
        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);
        
        for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        return outputArray;
    }
    
    /**
     * Uint8Array 转 Base64 URL 编码
     */
    uint8ArrayToBase64Url(uint8Array) {
        let binary = '';
        for (let i = 0; i < uint8Array.length; i++) {
            binary += String.fromCharCode(uint8Array[i]);
        }
        return btoa(binary)
            .replace(/\+/g, '-')
            .replace(/\//g, '_')
            .replace(/=/g, '');
    }
}

/**
 * 检测是否为 iOS 设备
 */
function isIOS() {
    return [
        'iPad Simulator',
        'iPhone Simulator',
        'iPod Simulator',
        'iPad',
        'iPhone',
        'iPod'
    ].includes(navigator.platform)
    || (navigator.userAgent.includes("Mac") && "ontouchend" in document);
}

/**
 * 检测是否为移动设备
 */
function isMobile() {
    return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
}

/**
 * 检测设备类型
 */
function detectDeviceType() {
    if (isIOS()) return 'ios';
    if (isMobile()) return 'android';
    return 'desktop';
}

/**
 * 全局实例
 */
window.PushNotificationManager = PushNotificationManager;
window.isIOS = isIOS;
window.isMobile = isMobile;
window.detectDeviceType = detectDeviceType;
