/**
 * 消息通知管理器
 * 用于在收到新消息时显示顶部通知
 */

class MessageNotification {
    constructor(options = {}) {
        this.options = {
            duration: options.duration || 5000,        // 通知显示时长（毫秒）
            clickable: options.clickable !== false,     // 是否可点击
            showIcon: options.showIcon !== false,       // 是否显示图标
            maxNotifications: options.maxNotifications || 3, // 最大显示数量
            position: options.position || 'top-left',   // 位置：'top-left', 'top-right', 'top-center'
            ...options
        };
        
        this.notifications = [];
        this.container = null;
        this.initContainer();
        this.initStyles();
    }
    
    /**
     * 初始化容器
     */
    initContainer() {
        // 检查是否已存在容器
        let container = document.getElementById('notification-container');
        if (container) {
            this.container = container;
            return;
        }
        
        // 创建容器
        container = document.createElement('div');
        container.id = 'notification-container';
        container.style.cssText = `
            position: fixed;
            ${this.getPositionStyles()};
            z-index: 99999;
            pointer-events: none;
        `;
        
        document.body.appendChild(container);
        this.container = container;
    }
    
    /**
     * 获取位置样式
     */
    getPositionStyles() {
        switch (this.options.position) {
            case 'top-right':
                return 'top: 10px; right: 10px;';
            case 'top-center':
                return 'top: 10px; left: 50%; transform: translateX(-50%);';
            case 'top-left':
            default:
                return 'top: 10px; left: 10px;';
        }
    }
    
    /**
     * 初始化样式
     */
    initStyles() {
        // 检查是否已存在样式
        if (document.getElementById('notification-styles')) {
            return;
        }
        
        const style = document.createElement('style');
        style.id = 'notification-styles';
        style.textContent = `
            /* 新通知样式 - 从顶部进入 */
            .new-user-toast {
                position: relative;
                background: #4caf50;
                border-radius: 8px;
                padding: 12px 16px;
                display: flex;
                align-items: center;
                box-shadow: 0 4px 12px rgba(0,0,0,.15);
                cursor: pointer;
                will-change: transform,opacity;
                backface-visibility: hidden;
                -webkit-backface-visibility: hidden;
                max-width: calc(100% - 20px);
                margin-bottom: 10px;
                pointer-events: auto;
                min-width: 400px;
                max-width: 400px;
            }
            
            .toast-icon {
                width: 24px;
                height: 24px;
                background: #fff;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                margin-right: 12px;
                flex-shrink: 0;
                font-size: 16px;
                color: #4caf50;
                font-weight: bold;
            }
            
            .toast-text {
                flex: 1;
                color: #fff;
                font-size: 15px;
                font-weight: 500;
                line-height: 1.4;
                word-break: break-word;
            }
            
            .toast-close {
                width: 24px;
                height: 24px;
                background: transparent;
                border: none;
                color: #fff;
                font-size: 20px;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                opacity: .8;
                flex-shrink: 0;
                transition: opacity .15s ease,transform .15s ease;
                padding: 0;
                margin-left: 8px;
            }
            
            .toast-close:hover {
                opacity: 1;
            }
            
            .toast-close:active {
                transform: scale(.85);
            }
            
            /* 通知进入和离开动画 - 从顶部进入 */
            .notification-enter-active {
                animation: notification-slide-in 0.3s ease-out;
            }
            
            .notification-leave-active {
                animation: notification-slide-out 0.3s ease-in forwards;
            }
            
            @keyframes notification-slide-in {
                0% {
                    transform: translateY(-100px);
                    opacity: 0;
                }
                100% {
                    transform: translateY(0);
                    opacity: 1;
                }
            }
            
            @keyframes notification-slide-out {
                0% {
                    transform: translateY(0);
                    opacity: 1;
                }
                100% {
                    transform: translateY(-100px);
                    opacity: 0;
                }
            }
            
            /* 暗色模式支持 */
            @media (prefers-color-scheme: dark) {
                .new-user-toast {
                    box-shadow: 0 4px 12px rgba(0,0,0,.3);
                }
                
                .toast-text {
                    color: #ffffff;
                }
            }
            
            /* 多行文本支持 */
            .toast-text.multiline {
                line-height: 1.3;
                max-height: 60px;
                overflow: hidden;
                text-overflow: ellipsis;
                display: -webkit-box;
                -webkit-line-clamp: 3;
                -webkit-box-orient: vertical;
            }
            
            /* 隐藏状态 */
            .toast-hidden {
                display: none;
            }
            
            /* 不同颜色的通知 */
            .new-user-toast.info {
                background: #2196f3;
            }
            
            .new-user-toast.info .toast-icon {
                color: #2196f3;
            }
            
            .new-user-toast.warning {
                background: #ff9800;
            }
            
            .new-user-toast.warning .toast-icon {
                color: #ff9800;
            }
            
            .new-user-toast.error {
                background: #f44336;
            }
            
            .new-user-toast.error .toast-icon {
                color: #f44336;
            }
        `;
        
        document.head.appendChild(style);
    }
    
    /**
     * 显示通知
     * @param {string} message - 通知内容
     * @param {object} options - 可选配置
     */
    show(message, options = {}) {
        const notification = {
            id: Date.now(),
            message: message,
            type: options.type || 'success',  // success, info, warning, error
            duration: options.duration || this.options.duration,
            clickable: options.clickable !== undefined ? options.clickable : this.options.clickable,
            onClose: options.onClose,
            onClick: options.onClick
        };
        
        this.createNotificationElement(notification);
        this.notifications.push(notification);
        
        // 限制最大显示数量
        if (this.notifications.length > this.options.maxNotifications) {
            const oldest = this.notifications.shift();
            this.closeNotification(oldest.id);
        }
        
        return notification.id;
    }
    
    /**
     * 创建通知元素
     */
    createNotificationElement(notification) {
        const toast = document.createElement('div');
        toast.className = `new-user-toast ${notification.type} notification-enter-active`;
        toast.dataset.id = notification.id;
        
        // 图标
        if (this.options.showIcon) {
            const icon = document.createElement('div');
            icon.className = 'toast-icon';
            icon.textContent = this.getIconForType(notification.type);
            toast.appendChild(icon);
        }
        
        // 文本
        const text = document.createElement('div');
        text.className = 'toast-text';
        text.textContent = notification.message;
        toast.appendChild(text);
        
        // 关闭按钮
        const closeBtn = document.createElement('button');
        closeBtn.className = 'toast-close';
        closeBtn.innerHTML = '×';
        closeBtn.onclick = (e) => {
            e.stopPropagation();
            this.closeNotification(notification.id);
        };
        toast.appendChild(closeBtn);
        
        // 点击事件
        if (notification.clickable && notification.onClick) {
            toast.onclick = () => {
                notification.onClick(notification);
            };
        }
        
        // 添加到容器
        this.container.appendChild(toast);
        
        // 自动关闭
        if (notification.duration > 0) {
            setTimeout(() => {
                this.closeNotification(notification.id);
            }, notification.duration);
        }
    }
    
    /**
     * 获取图标
     */
    getIconForType(type) {
        const icons = {
            success: '✓',
            info: 'ℹ',
            warning: '⚠',
            error: '✕'
        };
        return icons[type] || icons.success;
    }
    
    /**
     * 关闭通知
     */
    closeNotification(id) {
        const toast = document.querySelector(`[data-id="${id}"]`);
        if (!toast) return;
        
        // 添加离开动画
        toast.classList.remove('notification-enter-active');
        toast.classList.add('notification-leave-active');
        
        // 动画结束后移除
        setTimeout(() => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
            
            // 从数组中移除
            const index = this.notifications.findIndex(n => n.id === id);
            if (index > -1) {
                const notification = this.notifications[index];
                if (notification.onClose) {
                    notification.onClose(notification);
                }
                this.notifications.splice(index, 1);
            }
        }, 300);
    }
    
    /**
     * 关闭所有通知
     */
    closeAll() {
        this.notifications.forEach(notification => {
            this.closeNotification(notification.id);
        });
    }
    
    /**
     * 显示成功通知
     */
    success(message, options = {}) {
        return this.show(message, { ...options, type: 'success' });
    }
    
    /**
     * 显示信息通知
     */
    info(message, options = {}) {
        return this.show(message, { ...options, type: 'info' });
    }
    
    /**
     * 显示警告通知
     */
    warning(message, options = {}) {
        return this.show(message, { ...options, type: 'warning' });
    }
    
    /**
     * 显示错误通知
     */
    error(message, options = {}) {
        return this.show(message, { ...options, type: 'error' });
    }
}

/**
 * 全局实例
 */
window.MessageNotification = MessageNotification;

/**
 * 快捷方法
 */
window.showToast = function(message, duration = 5000, type = 'success') {
    if (!window.notificationManager) {
        window.notificationManager = new MessageNotification();
    }
    return window.notificationManager.show(message, { type, duration });
};

// 自动初始化（可选）
// window.notificationManager = new MessageNotification();
