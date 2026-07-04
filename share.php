<?php
if (!defined('FROM_ROUTER')) {
    http_response_code(403);
    exit('喜乐科技@x60898');
}
// ========== 基础配置和登录检查 ==========
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/dbconfig.php';

checkLogin();

$db = getDB();
if (!$db) {
    die("数据库连接失败");
}
// ========== 获取用户基本信息 ==========
$user_info = [];
if (isset($_COOKIE['user_info'])) {
    $user_info = json_decode($_COOKIE['user_info'], true);
}

// 当前登录的客服信息
$currentAgent = $_SESSION['username'];
$currentRole = $_SESSION['role'];
$username = $user_info['user'] ?? $_SESSION['username'] ?? '';
$XErole = $_SESSION['user_role'];
$XEroles = ($XErole === 'admin') ? '总控' : 
           (($XErole === 'user') ? '用户' : 
           (($XErole === 'visitor') ? '访客' : $XErole));
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <!-- PWA meta tags -->
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="客服聊天">
<link rel="apple-touch-icon" href="/assets/img/icon-192.png">
<link rel="manifest" href="/manifest.php">
<meta name="theme-color" content="#f7f8fa">
    <title>喜乐-客服系统</title>
    <script src="/assets/jquery.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
        }

        body {
            background: #f7f7f7;
            font-family: 'Noto Sans SC', sans-serif;
            overflow: hidden;
            height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* ========== 头部 ========== */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 12px;
            flex-shrink: 0;
            background: #fff;
            border-bottom: 1px solid #f0f0f0;
            position: sticky;
            top: 0;
            z-index: 101;
        }

        .back-link {
            display: flex;
            align-items: center;
            font-size: 1rem;
            font-weight: 500;
            color: #1f2937;
            text-decoration: none;
        }

        .back-icon {
            width: 22px;
            height: 22px;
        }

        .back-text {
            margin-left: 0.25rem;
        }

        .header-title {
            font-size: 1rem;
            font-weight: 600;
            color: #1f2937;
        }

        .header-right {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        /* 全屏按钮 */
        .fullscreen-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border: none;
            background: #eff6ff;
            border-radius: 8px;
            cursor: pointer;
            color: #2563eb;
            padding: 0;
        }

        /* ========== iframe 预览区 ========== */
        .iframe-section {
            flex: 1;
            min-height: 0;
            position: relative;
            background: #f7f8fa;
        }

        .iframe-container {
            position: relative;
            width: 100%;
            height: 100%;
        }

        #contentFrame {
            width: 100%;
            height: 100%;
            border: none;
            background: transparent;
        }

        .loader-wrapper {
            position: absolute;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: #f7f8fa;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10;
        }
        .loader {
            display: block;
            --height-of-loader: 4px;
            --loader-color: #0071e2;
            width: 130px;
            height: var(--height-of-loader);
            border-radius: 30px;
            background-color: rgba(0,0,0,0.2);
            position: relative;
        }
        .loader::before {
            content: "";
            position: absolute;
            background: var(--loader-color);
            top: 0; left: 0;
            width: 0%; height: 100%;
            border-radius: 30px;
            animation: moving 1s ease-in-out infinite;
        }
        @keyframes moving {
            50% { width: 100%; }
            100% { width: 0; right: 0; left: unset; }
        }
        .loader-wrapper.hidden {
            display: none;
        }

        /* ========== 平台选择栏（顶部紧凑） ========== */
        .platform-bar {
            flex-shrink: 0;
            background: #fff;
            padding: 8px 12px;
            border-bottom: 1px solid #f0f0f0;
            position: absolute;
            top: 44px;
            left: 0;
            width: 100%;
            z-index: 100;
            transition: transform 0.3s ease;
        }

        .platform-bar.hidden {
            transform: translateY(-100%);
        }

        .platform-bar .platform-grid {
            gap: 8px;
            padding: 8px;
        }

        /* ========== 平台选择网格 ========== */
        .platform-grid-section {
            margin-bottom: 12px;
        }

        .platform-grid-section .section-label {
            font-size: 13px;
            color: #999;
            margin-bottom: 8px;
            padding-left: 4px;
        }

        .platform-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            background: #e5e5e5;
            padding: 12px;
            border-radius: 12px;
        }

        .platform-grid-item {
            padding: 10px 0;
            text-align: center;
            border-radius: 8px;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
            color: #666;
            background: transparent;
            border: none;
            outline: none;
            -webkit-tap-highlight-color: transparent;
        }

        .platform-grid-item.active {
            background: #fff;
            color: #111;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .platform-grid-item:active {
            opacity: 0.7;
        }

        /* ========== 全屏模式 ========== */
        .fullscreen-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            z-index: 9999;
            background: #fff;
            transition: transform 0.3s ease;
        }
        .fullscreen-overlay iframe {
            width: 100%;
            height: 100%;
            border: none;
        }
        .fullscreen-overlay.sliding-out {
            transform: translateX(100%);
        }
        .fullscreen-swipe-zone {
            position: absolute;
            top: 0;
            left: 0;
            width: 20px;
            height: 100%;
            z-index: 10001;
        }
        .fullscreen-hint {
            position: fixed;
            top: 50%;
            left: 12px;
            transform: translateY(-50%);
            background: rgba(0,0,0,0.5);
            color: #fff;
            padding: 8px 12px;
            border-radius: 20px;
            font-size: 12px;
            z-index: 10002;
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .fullscreen-hint.show {
            opacity: 1;
        }
        .fullscreen-exit-btn {
            position: fixed;
            top: 0;
            right: 0;
            z-index: 10002;
            width: 60px;
            height: 60px;
            border-radius: 0;
            background: transparent;
            color: transparent;
            border: none;
            font-size: 18px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        ::-webkit-scrollbar {
            display: none;
        }
    </style>
</head>

<body>
    <!-- 头部 -->
    <header class="page-header">
        <a href="javascript:void(0)" onclick="window.parent.postMessage('closeModal', '*')" class="back-link">
            <svg class="back-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256">
                <path fill="currentColor" d="M168.49 199.51a12 12 0 0 1-17 17l-80-80a12 12 0 0 1 0-17l80-80a12 12 0 0 1 17 17L97 128Z"></path>
            </svg>
            <span class="back-text">返回</span>
        </a>

        <span class="header-title">分享页</span>

        <div class="header-right">
            <button class="fullscreen-btn" id="fullscreenBtn" title="全屏预览">
               <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 3 21 3 21 9"></polyline><polyline points="9 21 3 21 3 15"></polyline><line x1="21" y1="3" x2="14" y2="10"></line><line x1="3" y1="21" x2="10" y2="14"></line></svg>
            </button>
        </div>
    </header>

    <!-- 平台选择栏（顶部紧凑） -->
    <div class="platform-bar">
        <div class="platform-grid" id="hotPlatformGrid">
            <button class="platform-grid-item active" data-src="/Share-goofish">闲鱼</button>
            <button class="platform-grid-item" data-src="/Share-zhuanzhuan">转转</button>
            <button class="platform-grid-item" data-src="/Share-pangxie">螃蟹</button>
            <button class="platform-grid-item" data-src="/Share-panzhi">盼之</button>
            <button class="platform-grid-item" data-src="/Share-jiaoyimao">交易猫</button>
            <button class="platform-grid-item" data-src="/Share-weixin">微信</button>
            <button class="platform-grid-item" data-src="/Share-yinlian">银联</button>
            <button class="platform-grid-item" data-src="/Share-qiandao">千岛</button>
            <button class="platform-grid-item" data-src="/Share-kejinshou">氪金兽</button>
            <button class="platform-grid-item" data-src="/Share-baiqing">白情</button>
            <button class="platform-grid-item" data-src="/Share-goofishdl">闲鱼代练</button>
            <button class="platform-grid-item" data-src="/Share-dewu">得物</button>
            <button class="platform-grid-item" data-src="/Share-pdd">拼多多</button>
            <button class="platform-grid-item" data-src="/Share-dingding">钉钉</button>
            <button class="platform-grid-item" data-src="/Share-damai">大麦</button>
            <button class="platform-grid-item" data-src="/Share-douyin">抖音</button>
            <button class="platform-grid-item" data-src="/Share-jingdong">京东</button>
            <button class="platform-grid-item" data-src="/Share-zidingyi">自定义</button>
            <button class="platform-grid-item" data-src="/Share-tongyong">通用</button>
        </div>
    </div>

    <!-- iframe 预览区（占剩余空间） -->
    <div class="iframe-section">
        <div class="iframe-container">
            <div class="loader-wrapper" id="loaderWrapper">
                <div class="loader"></div>
            </div>
            <iframe id="contentFrame" src="/Share-goofish"></iframe>
        </div>
    </div>



<script>
    // ========== DOM 引用 ==========
    const fullscreenBtn = document.getElementById('fullscreenBtn');
    const iframe = document.getElementById('contentFrame');
    const loaderWrapper = document.getElementById('loaderWrapper');
    const allGridItems = document.querySelectorAll('.platform-grid-item');

    // ========== 平台标签栏滚动隐藏/显示 ==========
    let lastScrollTop = 0;
    let platformBarHidden = false;

    // ========== 平台网格切换 ==========
    allGridItems.forEach(item => {
        item.onclick = () => {
            allGridItems.forEach(i => i.classList.remove('active'));
            item.classList.add('active');
            loaderWrapper.classList.remove('hidden');
            iframe.src = item.dataset.src;
        };
    });

    // iframe 加载完成
    iframe.onload = () => {
        loaderWrapper.classList.add('hidden');

        // 监听 iframe 内部滚动，实现平台标签栏隐藏/显示
        try {
            const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
            const iframeBody = iframeDoc.body || iframeDoc.documentElement;
            const platformBar = document.querySelector('.platform-bar');

            iframeDoc.addEventListener('scroll', () => {
                const scrollTop = iframeBody.scrollTop || iframeDoc.documentElement.scrollTop;

                if (scrollTop > lastScrollTop && scrollTop > 10) {
                    // 向下滚动 → 隐藏
                    if (!platformBarHidden) {
                        platformBar.classList.add('hidden');
                        platformBarHidden = true;
                    }
                } else if (scrollTop < lastScrollTop) {
                    // 向上滚动 → 显示
                    if (platformBarHidden) {
                        platformBar.classList.remove('hidden');
                        platformBarHidden = false;
                    }
                }

                lastScrollTop = scrollTop;
            }, { passive: true });
        } catch(e) {
            // 跨域安全限制，忽略
        }
    };

    // ========== 全屏功能 ==========
    let isFullscreen = false;
    let fullscreenOverlay = null;
    let swipeZone = null;
    let touchStartX = 0;
    let touchStartY = 0;
    let touchCurrentX = 0;
    let isSwiping = false;

    fullscreenBtn.onclick = () => {
        enterFullscreen();
    };

    function enterFullscreen() {
        if (isFullscreen) return;
        isFullscreen = true;

        fullscreenOverlay = document.createElement('div');
        fullscreenOverlay.className = 'fullscreen-overlay';

        const fsIframe = document.createElement('iframe');
        fsIframe.src = iframe.src;
        fsIframe.style.width = '100%';
        fsIframe.style.height = '100%';
        fsIframe.style.border = 'none';
        fullscreenOverlay.appendChild(fsIframe);

        swipeZone = document.createElement('div');
        swipeZone.className = 'fullscreen-swipe-zone';
        fullscreenOverlay.appendChild(swipeZone);

        const exitBtn = document.createElement('button');
        exitBtn.className = 'fullscreen-exit-btn';
        exitBtn.innerHTML = '✕';
        exitBtn.onclick = () => exitFullscreen();
        fullscreenOverlay.appendChild(exitBtn);

        const hint = document.createElement('div');
        hint.className = 'fullscreen-hint';
        hint.id = 'fullscreenHint';
        hint.textContent = '点击右上角即可退出，该消息1秒后消失';
        fullscreenOverlay.appendChild(hint);

        document.body.appendChild(fullscreenOverlay);

        setTimeout(() => {
            hint.classList.add('show');
            setTimeout(() => hint.classList.remove('show'), 2500);
        }, 500);

        swipeZone.addEventListener('touchstart', onTouchStart, { passive: true });
        swipeZone.addEventListener('touchmove', onTouchMove, { passive: false });
        swipeZone.addEventListener('touchend', onTouchEnd, { passive: true });

        document.addEventListener('touchmove', onDocTouchMove, { passive: false });
        document.addEventListener('touchend', onDocTouchEnd, { passive: true });
    }

    function exitFullscreen() {
        if (!isFullscreen || !fullscreenOverlay) return;
        document.removeEventListener('touchmove', onDocTouchMove);
        document.removeEventListener('touchend', onDocTouchEnd);
        fullscreenOverlay.classList.add('sliding-out');
        setTimeout(() => {
            if (fullscreenOverlay && fullscreenOverlay.parentNode) {
                fullscreenOverlay.parentNode.removeChild(fullscreenOverlay);
            }
            fullscreenOverlay = null;
            swipeZone = null;
            isFullscreen = false;
        }, 300);
    }

    function onTouchStart(e) {
        touchStartX = e.touches[0].clientX;
        touchStartY = e.touches[0].clientY;
        touchCurrentX = touchStartX;
        isSwiping = true;
    }

    function onTouchMove(e) {
        if (!isSwiping || !fullscreenOverlay) return;
        touchCurrentX = e.touches[0].clientX;
        const diffX = touchCurrentX - touchStartX;
        if (diffX > 0) {
            e.preventDefault();
            fullscreenOverlay.style.transition = 'none';
            fullscreenOverlay.style.transform = 'translateX(' + diffX + 'px)';
        }
    }

    function onTouchEnd(e) {
        if (!isSwiping || !fullscreenOverlay) return;
        finishSwipe();
    }

    function onDocTouchMove(e) {
        if (!isSwiping || !fullscreenOverlay) return;
        onTouchMove(e);
    }

    function onDocTouchEnd(e) {
        if (!isSwiping || !fullscreenOverlay) return;
        finishSwipe();
    }

    function finishSwipe() {
        if (!fullscreenOverlay) return;
        const diffX = touchCurrentX - touchStartX;
        if (diffX > 80) {
            fullscreenOverlay.style.transition = 'transform 0.3s ease';
            fullscreenOverlay.style.transform = 'translateX(100%)';
            document.removeEventListener('touchmove', onDocTouchMove);
            document.removeEventListener('touchend', onDocTouchEnd);
            setTimeout(() => {
                if (fullscreenOverlay && fullscreenOverlay.parentNode) {
                    fullscreenOverlay.parentNode.removeChild(fullscreenOverlay);
                }
                fullscreenOverlay = null;
                swipeZone = null;
                isFullscreen = false;
            }, 300);
        } else {
            fullscreenOverlay.style.transition = 'transform 0.3s ease';
            fullscreenOverlay.style.transform = 'translateX(0)';
        }
        isSwiping = false;
    }

    // ========== 页面初始化 ==========
    // （防红相关功能已移除）
</script>

<!-- 添加公共 WebSocket 连接 -->
<script src="/js/websocket-public.js"></script>
<!-- 添加消息通知 -->
<script src="/js/message-notification.js"></script>
<script>
    // 等待 websocket-public.js 加载完成
    function initWebSocket() {
        if (typeof WebSocketManager === 'undefined') {
            console.log('[首页] 等待 WebSocketManager 加载...');
            setTimeout(initWebSocket, 100);
            return;
        }
        
        // 设置用户信息 - 使用正确的 session 字段
        const currentUser = {
            username: '<?php echo addslashes($currentAgent); ?>',
            role: '<?php echo addslashes($XErole); ?>',  // 使用 $XErole 而不是 $currentRole
            session_key: '<?php echo session_id(); ?>'
        };
        
        console.log('[首页] PHP 变量：currentAgent=<?php echo $currentAgent; ?>, XErole=<?php echo $XErole; ?>');
        console.log('[首页] 用户信息对象:', currentUser);
        
        // 保存到全局变量
        window.currentUser = currentUser;
        
        // 保存到 sessionStorage（用于页面刷新后自动重连）
        sessionStorage.setItem('user_data', JSON.stringify({
            username: currentUser.username,
            role: currentUser.role,
            session_key: currentUser.session_key
        }));
        
        console.log('[首页] 已保存到 sessionStorage:', JSON.parse(sessionStorage.getItem('user_data')));
        
        // 手动映射 user_type - 确保客服身份
        let userType = 'customer';
        if (currentUser.role === 'admin' || currentUser.role === 'agent' || currentUser.role == 1) {
            userType = 'agent';
        }
        console.log('[首页] 映射后的用户类型:', userType);
        
        // 手动初始化 WebSocket，确保以正确身份连接
        if (!window.wsManager) {
            window.wsManager = new WebSocketManager({
                debug: true
            });
            
            console.log('[首页] 开始连接 WebSocket，参数：', {
                userType: userType,
                userId: currentUser.username,
                sessionKey: currentUser.session_key
            });
            
            window.wsManager.connect(userType, currentUser.username, currentUser.session_key);
        }
        
        // 初始化消息通知
        if (!window.notificationManager) {
            window.notificationManager = new MessageNotification({
                duration: 5000,
                clickable: true,
                position: 'top-left'
            });
        }
        
        // 监听新消息
        window.addEventListener('websocket-new-message', function(event) {
            const data = event.detail;
            console.log('[首页] 收到新消息:', data);
            
               // 显示通知
            if (data.type === 'new_message' || data.content) {
                 // 获取发送者名称 - 优先使用 customer_name
                const sender = data.customer_name || data.sender || data.username || data.from || '客户';
                const content = data.content || data.message || '收到新消息';
                
                window.notificationManager.show(`${sender}: ${content}`, {
                    type: 'success',
                    duration: 5000,
                    clickable: true,
                    onClick: function() {
                        console.log('通知被点击，来自:', sender);
                    }
                });
            }
        });
        
        // 监听错误
        window.addEventListener('websocket-error', function(event) {
            console.error('[首页] WebSocket 错误:', event);
        });
    }
    
    // 页面加载完成后初始化
    $(document).ready(function() {
        initWebSocket();
    });
</script>
</body>
</html>
