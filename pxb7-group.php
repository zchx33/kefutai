<?php
if (!defined('FROM_ROUTER')) {
    http_response_code(403);
    exit('喜乐科技@x60898');
}
?>
<?php
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

// 获取URL中的页面代码
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

// 查询商品页面信息 - 使用XEpxb7表结构
$sql = "SELECT 
            XEpxb7_id, 
            XEpxb7_user_id, 
            XEpxb7_product_name, 
            XEpxb7_game_name, 
            XEpxb7_product_code, 
            XEpxb7_product_amount, 
            XEpxb7_no_stock_compensation,
            XEpxb7_retrieve_compensation,
            XEpxb7_customer_service,
            XEpxb7_page_status,
            XEpxb7_page_code, 
            XEpxb7_product_image, 
            XEpxb7_seller_avatar,
            XEpxb7_created_at, 
            XEpxb7_updated_at
        FROM XEpxb7 
        WHERE XEpxb7_page_code = ? AND XEpxb7_page_status = 'active'";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $page_code);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // 没有找到对应的商品页
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

// 获取商品页面数据
$product_page = $result->fetch_assoc();

$formatted_amount = number_format($product_page['XEpxb7_product_amount'], 2, '.', '');

// 根据客服身份获取对应的头像和昵称
$customer_avatar = '';
$customer_nickname = '';

switch($product_page['XEpxb7_customer_service']) {
    case '螃蟹交易专员': 
        $customer_avatar = '/assets/img/px-kf.png'; 
        $customer_nickname = '暮青'; // 交易专员显示暮青
        break;
    case '螃蟹咨询专员': 
        $customer_avatar = '/assets/img/px-kf2.png'; 
        $customer_nickname = '凤竹'; // 咨询专员显示凤竹
        break;
    case '螃蟹售后专员': 
        $customer_avatar = '/assets/img/px-kf3.jpg'; 
        $customer_nickname = '星黛露'; // 售后专员显示星黛露
        break;
    default: 
        $customer_avatar = '<?php echo $customer_avatar; ?>';
        $customer_nickname = '不同昵称'; // 默认显示
}

// 新增：获取假人设置
$dummySettings = [
    'dummy_name' => '技术顾问',  // 默认名称
    'dummy_avatar' => '/assets/img/dummy1.png',  // 默认头像
    'is_dummy_mode' => false,
    'last_updated' => 0
];

// 尝试从API获取假人设置
try {
    // 使用curl获取假人设置
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, '/config/api.php');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'action' => 'get_dummy_settings',
        'session_id' => $sessionId
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 3);
    
    $response = curl_exec($ch);
    if ($response !== false) {
        $data = json_decode($response, true);
        if ($data && $data['success'] && $data['dummy_settings']) {
            $dummySettings = $data['dummy_settings'];
            
            // 确保头像路径正确
            if (isset($dummySettings['dummy_avatar']) && 
                $dummySettings['dummy_avatar'] && 
                !str_starts_with($dummySettings['dummy_avatar'], 'http') && 
                !str_starts_with($dummySettings['dummy_avatar'], '/')) {
                $dummySettings['dummy_avatar'] = '/assets/img/' . $dummySettings['dummy_avatar'];
            }
        }
    }
    curl_close($ch);
} catch (Exception $e) {
    // 如果获取失败，使用默认设置
    error_log('获取假人设置失败: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>im聊天房间</title>
	<link rel="shortcut icon" href="/assets/img/pxb7.png" type="image/x-icon">
    <script src="https://code.iconify.design/3/3.1.0/iconify.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        /* 基础重置 */
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        /* 基础样式 */
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", "Noto Color Emoji";
            font-size: 16px;
            line-height: 1.5;
            color: #000;
            background-color: #f7f8fa;
            min-height: 100vh;
            overflow-x: hidden;
        }
        
        /* 容器 */
        .container {
            max-width: 32rem;
            margin: 0 auto;
            width: 100%;
        }
        
        /* 头部 */
        .header {
            background-color: #fff;
            padding-left: 18px;
            padding-right: 18px;
            padding-top:6px;
            padding-bottom:6px;
        }
        
        .header a {
            display: inline-block;
            text-decoration: none;
        }
        
        .header svg {
            color: #374151;
            font-size: 1.875rem;
            width: 1em;
            height: 1em;
        }
        
        /* 主内容区域 */
        .main-content {
            padding: 1rem;
        }
        
        /* 卡片样式 */
        .card {
            padding:13.76px;
            background-color: #fff;
            border-radius: 0.75rem;
            margin-bottom: 15px;
            cursor: pointer;
            transition: box-shadow 0.2s ease;
        }
        
        /* 群组信息卡片 */
        .group-info {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .group-avatar-container {
            position: relative;
            width: 3rem;
            height: 3rem;
            flex-shrink: 0;
            background-color: #e5e7eb;
            border-radius: 9999px;
            padding: 0.125rem;
        }
        
        .group-avatar {
            position: absolute;
            width: 1.5rem;
            height: 1.5rem;
            border-radius: 9999px;
            object-fit: cover;
        }
        
        .group-avatar-1 {
            top: 0.125rem;
            left: 50%;
            transform: translateX(-50%);
        }
        
        .group-avatar-2 {
            bottom: 0.125rem;
            left: 0.125rem;
        }
        
        .group-avatar-3 {
            bottom: 0.125rem;
            right: 0.125rem;
        }
        
        .group-name {
            font-size: 18px;
            color: #111827;
        }
        
        .chevron-right {
            color: #333;
            font-size: 1.25rem;
            width: 24px;
            height: 24px;
        }
        
        /* 群成员卡片 */
        .group-members-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
        }
        
        .group-members-count {
            display: flex;
            align-items: center;
        }
        
        .group-members-count span {
            font-size: 16px;
            color: #9ca3af;
            margin-right: 0.25rem;
        }
        
        .members-list {
            display: flex;
        }
        
        .member-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 0.5rem;
        }
        
        .member-avatar:last-child {
            margin-right: 0;
        }
        
        /* 设置卡片 */
        .settings-item {
            padding-top: 12px;
            padding-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid #f3f4f6;
        }
        
        .settings-item:last-child {
            border-bottom: none;
        }
        
        /* 开关样式 */
        .switch {
            position: relative;
            display: inline-flex;
            align-items: center;
            cursor: pointer;
        }
        
        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
            position: absolute;
        }
        
        .switch-slider {
            display: block;
            width: 3rem;
            height: 1.75rem;
            background-color: #e5e7eb;
            border-radius: 9999px;
            position: relative;
        }
        
        .switch-slider::after {
            content: '';
            position: absolute;
            top: 3px;
            left: 3px;
            width: 1.375rem;
            height: 1.375rem;
            background-color: white;
            border: 1px solid #d1d5db;
            border-radius: 50%;
            transition: transform 0.2s ease;
        }
        
        .switch input:checked + .switch-slider {
            background-color: rgb(246, 160, 70);
        }
        
        .switch input:checked + .switch-slider::after {
            transform: translateX(1.25rem);
        }
        
        /* 文本样式 */
        .text-xl {
            font-size: 1.25rem;
            line-height: 1.75rem;
        }
        
        .text-base {
            font-size: 1rem;
            line-height: 1.5rem;
        }
        
        .text-sm {
            font-size: 0.875rem;
            line-height: 1.25rem;
        }
        
        .text-gray-900 {
            color: #111827;
        }
        
        .text-gray-700 {
            color: #374151;
        }
        
        .text-gray-400 {
            color: #9ca3af;
        }
        
        /* 底部弹出模态框样式 - 根据图片完全重新设计 */
        .bottom-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: flex-end;
            justify-content: center;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }
        
        .bottom-modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }
        
        .bottom-modal-content {
            background-color: #fff;
            border-top-left-radius: 16px;
            border-top-right-radius: 16px;
            width: 100%;
            height: 40%;
            max-width: 32rem;
            box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.15);
            transform: translateY(100%);
            transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            position: relative;
            overflow: hidden;
        }
        
        .bottom-modal-overlay.active .bottom-modal-content {
            transform: translateY(0);
        }
        
        .bottom-modal-header {
            padding: 18px 20px 16px;
            border-bottom: 1px solid #f0f0f0;
            position: relative;
            text-align: center;
            background-color: #fff;
        }
        
        .bottom-modal-title {
            font-size: 18px;
            font-weight: 600;
            color: #000;
            line-height: 1.4;
        }
        
        .bottom-modal-close {
            position: absolute;
            top: 50%;
            right: 20px;
            transform: translateY(-50%);
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 22px;
            color: #999;
            transition: color 0.2s;
            font-weight: 300;
            background: none;
            border: none;
            padding: 0;
            border-radius: 50%;
        }
        
        .bottom-modal-close:hover {
            background-color: #f5f5f5;
            color: #333;
        }
        
        .bottom-modal-body {
            padding: 20px;
            background-color: #fff;
        }
        
        .modal-info-item {
            margin-bottom: 20px;
        }
        
        .modal-info-item:last-child {
            margin-bottom: 0;
        }
        
        .modal-info-label {
            font-size: 16px;
            color: #888;
            margin-bottom: 8px;
            font-weight: 400;
            line-height: 1.4;
        }
        
        .modal-info-value {
            font-size: 17.5px;
            color: #333;
            font-weight: 500;
            line-height: 1.4;
            word-break: break-all;
        }
        
        /* 工具类 */
        .flex {
            display: flex;
        }
        
        .items-center {
            align-items: center;
        }
        
        .justify-between {
            justify-content: space-between;
        }
        
        .flex-shrink-0 {
            flex-shrink: 0;
        }
        
        .space-x-4 > *:not(:last-child) {
            margin-right: 1rem;
        }
        
        /* 响应式设计 */
        @media (max-width: 640px) {
            .container {
                max-width: 100%;
            }
            
            .text-xl {
                font-size: 1.125rem;
            }
            
            .bottom-modal-content {
                max-width: 100%;
                border-top-left-radius: 20px;
                border-top-right-radius: 20px;
            }
        }
        
        @media (min-width: 641px) and (max-width: 1024px) {
            .container {
                max-width: 40rem;
            }
        }
        
        @media (min-width: 1025px) {
            .container {
                max-width: 32rem;
            }
        }
        
        /* 隐藏滚动条 */
        ::-webkit-scrollbar {
            display: none;
        }
        
        * {
            scrollbar-width: none;
        }
        
        /* 消息提示样式 */
        .ttc {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 9999;
            width: 35%;
            max-width: 400px;
            height: 40px;
            background-color: rgba(0, 0, 0, 0.7);
            border-radius: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 14px;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s, visibility 0.3s;
        }
        
        .ttc.show {
            opacity: 1;
            visibility: visible;
        }
        
        .ttc .zd {
            color: white;
        }
    </style>

</head>
<body>
    <div class="container">
        <!-- 头部 -->
        <header class="header">
            <a href="javascript:history.back();" aria-label="返回">
                <svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true" role="img" width="1em" height="1em" viewBox="0 0 24 24" class="iconify">
                    <path fill="currentColor" d="M15.41 16.58L10.83 12l4.58-4.59L14 6l-6 6l6 6z"></path>
                </svg>
            </a>
        </header>
        
        <!-- 主内容区域 -->
        <div class="main-content">
            <!-- 群组信息 -->
            <div class="card group-info" id="groupInfoCard">
                <div class="flex items-center">
                    <div class="group-avatar-container">
                        <img src="<?php echo $customer_avatar; ?>" 
                             alt="" 
                             class="group-avatar group-avatar-1 dummy-avatar-1">
                        <img src="<?php echo htmlspecialchars($dummySettings['dummy_avatar']); ?>" 
                             alt="" 
                             class="group-avatar group-avatar-2 dummy-avatar-2">
                        <img src="<?php echo htmlspecialchars($product_page['XEpxb7_seller_avatar']); ?>" 
                             alt="" 
                             class="group-avatar group-avatar-3 dummy-avatar-3">
                    </div>
                    <span class="group-name" style="margin-left: 1rem;">
                        <?php echo htmlspecialchars($product_page['XEpxb7_product_code']); ?><?php echo htmlspecialchars($product_page['XEpxb7_game_name']); ?>
                        (<span id="groupNumberShort" class="group-number-short">加载中...</span>)
                    </span>
                </div>
                <svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true" role="img" width="1em" height="1em" viewBox="0 0 24 24" class="chevron-right">
                    <path fill="currentColor" d="M8.59 16.58L13.17 12L8.59 7.41L10 6l6 6l-6 6z"></path>
                </svg>
            </div>
            
            <!-- 群成员 -->
            <div class="card">
                <div class="card-content">
                    <div class="group-members-header">
                        <span class="text-base font-medium text-gray-900">群成员</span>
                        <div class="group-members-count">
                            <span class="text-sm text-gray-400">3人</span>
                        </div>
                    </div>
                    <div class="members-list">
                        <img src="<?php echo $customer_avatar; ?>" 
                             alt="" 
                             class="member-avatar member-avatar-1">
                        <img src="<?php echo htmlspecialchars($dummySettings['dummy_avatar']); ?>" 
                             alt="" 
                             class="member-avatar member-avatar-2 dummy-avatar">
                        <img src="<?php echo htmlspecialchars($product_page['XEpxb7_seller_avatar']); ?>" 
                             alt="" 
                             class="member-avatar member-avatar-3">
                  <!--  傻逼群成员以后做    <svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true" role="img" width="1em" height="1em" viewBox="0 0 24 24" class="chevron-right" style="margin-left:auto;margin-top:10px;">
                            <path fill="currentColor" d="M8.59 16.58L13.17 12L8.59 7.41L10 6l6 6l-6 6z"></path>
                        </svg>  --->
                    </div>
                </div>
            </div>
            
            <!-- 交易信息 -->
            <div class="card group-info" onclick="window.location.href='/Pxb7DD';">
                <span class="text-base font-medium text-gray-900">交易信息</span>
                <svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true" role="img" width="1em" height="1em" viewBox="0 0 24 24" class="chevron-right">
                    <path fill="currentColor" d="M8.59 16.58L13.17 12L8.59 7.41L10 6l6 6l-6 6z"></path>
                </svg>
            </div>
            
            <!-- 设置 -->
            <div class="card">
                
                <div class="settings-item">
                    <span class="text-base font-medium text-gray-900">置顶聊天</span>
                    <label class="switch">
                        <input type="checkbox" class="switch-input" id="topChatSwitch">
                        <span class="switch-slider"></span>
                    </label>
                </div>
                <div class="settings-item">
                    <span class="text-base font-medium text-gray-900">屏蔽消息</span>
                    <label class="switch">
                        <input type="checkbox" class="switch-input" id="blockMsgSwitch">
                        <span class="switch-slider"></span>
                    </label>
                </div>
            </div>
        </div>
    </div>

    <!-- 底部弹出式群聊信息弹窗 -->
    <div class="bottom-modal-overlay" id="groupInfoModal">
        <div class="bottom-modal-content">
            <div class="bottom-modal-header">
                <div class="bottom-modal-title">群聊信息</div>
                <button class="bottom-modal-close" id="modalClose">&times;</button>
            </div>
            <div class="bottom-modal-body">
                <div class="modal-info-item">
                    <div class="modal-info-label">群名</div>
                    <div class="modal-info-value"><?php echo htmlspecialchars($product_page['XEpxb7_product_code']); ?><?php echo htmlspecialchars($product_page['XEpxb7_game_name']); ?></div>
                </div>
                <div class="modal-info-item">
                    <div class="modal-info-label">群号</div>
                    <div class="modal-info-value" id="groupNumberFull">加载中...</div>
                </div>
                <div class="modal-info-item">
                    <div class="modal-info-label">创建时间</div>
                    <div class="modal-info-value"><?php echo date('Y-m-d H:i:s', strtotime($product_page['XEpxb7_created_at'])); ?></div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- 置顶聊天提示 -->
    <div class="ttc" id="topChatSuccess">
        <span class="zd">聊天置顶成功</span>
    </div>
    <div class="ttc" id="topChatCancel">
        <span class="zd">取消置顶成功</span>
    </div>
    
    <!-- 屏蔽消息提示 -->
    <div class="ttc" id="blockMsgSuccess">
        <span class="zd">聊天已被屏蔽</span>
    </div>
    <div class="ttc" id="blockMsgCancel">
        <span class="zd">聊天已取消屏蔽</span>
    </div>

    <script>
        // 生成15位随机数字
        function generate15DigitNumber() {
            // 15位数字，从 100,000,000,000,000 到 999,999,999,999,999
            return Math.floor(Math.random() * 900000000000000) + 100000000000000;
        }
        
        // 获取或生成群号
        function getOrCreateGroupNumber() {
            const storageKey = 'chatGroupNumber_' + '<?php echo $page_code; ?>';
            let groupNumber = localStorage.getItem(storageKey);
            
            if (!groupNumber) {
                // 生成新的15位随机数字
                groupNumber = generate15DigitNumber().toString();
                localStorage.setItem(storageKey, groupNumber);
            }
            
            return groupNumber;
        }
        
        // 格式化群号显示（前7位加省略号）
        function formatGroupNumberShort(groupNumber) {
            if (groupNumber.length >= 7) {
                return groupNumber.substring(0, 7) + '...';
            }
            return groupNumber;
        }
        
        // 实时更新假人头像
        class DummyAvatarUpdater {
            constructor() {
                this.sessionId = '<?php echo $sessionId; ?>';
                this.apiBaseUrl = '/config/api.php';
                this.lastUpdateTime = 0; // 记录最后更新时间戳
                this.updateInterval = 30000; // 30秒更新一次
                this.updateTimer = null;
                
                // 存储当前假人头像
                this.currentDummyAvatar = '<?php echo htmlspecialchars($dummySettings["dummy_avatar"]); ?>';
                
                this.init();
            }
            
            init() {
                this.startPolling();
            }
            
            // 开始轮询更新
            startPolling() {
                const self = this;
                
                // 立即检查一次
                this.checkDummySettings();
                
                // 设置定时器
                this.updateTimer = setInterval(function() {
                    self.checkDummySettings();
                }, this.updateInterval);
            }
            
            // 检查假人设置
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
                            const newUpdateTime = newSettings.last_updated || 0;
                            
                            // 检查是否有更新
                            if (newUpdateTime > self.lastUpdateTime) {
                                self.updateDummyAvatar(newSettings);
                                self.lastUpdateTime = newUpdateTime;
                            }
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('获取假人设置失败:', error);
                    }
                });
            }
            
            // 更新假人头像
            updateDummyAvatar(settings) {
                if (!settings.dummy_avatar) {
                    console.log('没有假人头像设置');
                    return;
                }
                
                // 确保头像路径正确
                let newAvatar = settings.dummy_avatar;
                if (newAvatar && !newAvatar.startsWith('http') && !newAvatar.startsWith('/')) {
                    newAvatar = '/assets/img/' + newAvatar;
                }
                
                // 检查头像是否有变化
                if (newAvatar === this.currentDummyAvatar) {
                    console.log('假人头像无变化，跳过更新');
                    return;
                }
                
                console.log('更新假人头像:', newAvatar);
                
                // 更新存储的头像
                this.currentDummyAvatar = newAvatar;
                
                // 更新所有假人头像
                this.updateAllDummyAvatars(newAvatar);
            }
            
            // 更新所有假人头像
            updateAllDummyAvatars(avatarUrl) {
                // 更新群组信息卡片中的假人头像
                $('.group-avatar-2.dummy-avatar-2').attr('src', avatarUrl);
                
                // 更新群成员卡片中的假人头像
                $('.member-avatar-2.dummy-avatar').attr('src', avatarUrl);
                
                // 添加淡入淡出动画效果
                this.fadeAvatarEffect(avatarUrl);
            }
            
            // 淡入淡出动画效果
            fadeAvatarEffect(avatarUrl) {
                // 找到所有假人头像
                const dummyAvatars = $('.group-avatar-2.dummy-avatar-2, .member-avatar-2.dummy-avatar');
                
                dummyAvatars.each(function() {
                    const $avatar = $(this);
                    
                    // 创建新图片
                    const newImg = new Image();
                    newImg.onload = function() {
                        // 淡出当前图片
                        $avatar.css('opacity', '0.3');
                        
                        // 等待淡出完成
                        setTimeout(() => {
                            // 更新图片源
                            $avatar.attr('src', avatarUrl);
                            
                            // 淡入新图片
                            $avatar.css('opacity', '1');
                        }, 200);
                    };
                    newImg.onerror = function() {
                        console.warn('假人头像加载失败:', avatarUrl);
                    };
                    newImg.src = avatarUrl;
                });
            }
            
            // 停止轮询
            stopPolling() {
                if (this.updateTimer) {
                    clearInterval(this.updateTimer);
                    this.updateTimer = null;
                }
            }
        }
        
        // 页面加载完成后初始化
        document.addEventListener('DOMContentLoaded', function() {
            // 获取或生成群号
            const groupNumber = getOrCreateGroupNumber();
            
            // 更新页面显示
            document.getElementById('groupNumberShort').textContent = formatGroupNumberShort(groupNumber);
            document.getElementById('groupNumberFull').textContent = groupNumber;
            
            const switches = document.querySelectorAll('.switch-input');
            
            // 获取开关元素
            const topChatSwitch = document.getElementById('topChatSwitch');
            const blockMsgSwitch = document.getElementById('blockMsgSwitch');
            
            // 获取提示元素
            const topChatSuccess = document.getElementById('topChatSuccess');
            const topChatCancel = document.getElementById('topChatCancel');
            const blockMsgSuccess = document.getElementById('blockMsgSuccess');
            const blockMsgCancel = document.getElementById('blockMsgCancel');
            
            // 显示提示信息的函数
            function showToast(toastElement) {
                // 隐藏所有提示
                [topChatSuccess, topChatCancel, blockMsgSuccess, blockMsgCancel].forEach(el => {
                    el.classList.remove('show');
                });
                
                // 显示指定的提示
                toastElement.classList.add('show');
                
                // 3秒后自动隐藏
                setTimeout(() => {
                    toastElement.classList.remove('show');
                }, 3000);
            }
            
            // 为置顶聊天开关添加事件监听
            topChatSwitch.addEventListener('change', function() {
                if (this.checked) {
                    console.log('置顶聊天: 开');
                    showToast(topChatSuccess);
                } else {
                    console.log('置顶聊天: 关');
                    showToast(topChatCancel);
                }
            });
            
            // 为屏蔽消息开关添加事件监听
            blockMsgSwitch.addEventListener('change', function() {
                if (this.checked) {
                    console.log('屏蔽消息: 开');
                    showToast(blockMsgSuccess);
                } else {
                    console.log('屏蔽消息: 关');
                    showToast(blockMsgCancel);
                }
            });
            
            // 为其他开关添加事件监听
            switches.forEach(switchEl => {
                if (switchEl.id !== 'topChatSwitch' && switchEl.id !== 'blockMsgSwitch') {
                    switchEl.addEventListener('change', function() {
                        console.log('开关状态:', this.checked ? '开' : '关');
                    });
                }
            });
            
            // 获取DOM元素
            const groupInfoCard = document.getElementById('groupInfoCard');
            const groupInfoModal = document.getElementById('groupInfoModal');
            const modalClose = document.getElementById('modalClose');
            
            // 点击群组信息卡片显示弹窗
            groupInfoCard.addEventListener('click', function() {
                groupInfoModal.classList.add('active');
                document.body.style.overflow = 'hidden'; // 防止背景滚动
            });
            
            // 点击关闭按钮关闭弹窗
            modalClose.addEventListener('click', function() {
                closeModal();
            });
            
            // 点击模态框外部关闭弹窗
            groupInfoModal.addEventListener('click', function(event) {
                if (event.target === groupInfoModal) {
                    closeModal();
                }
            });
            
            // 按下ESC键关闭弹窗
            document.addEventListener('keydown', function(event) {
                if (event.key === 'Escape') {
                    closeModal();
                }
            });
            
            // 滑动关闭弹窗
            let startY = 0;
            let currentY = 0;
            let isSwiping = false;
            
            groupInfoModal.addEventListener('touchstart', function(event) {
                const touch = event.touches[0];
                startY = touch.clientY;
                currentY = startY;
                isSwiping = true;
            });
            
            groupInfoModal.addEventListener('touchmove', function(event) {
                if (!isSwiping) return;
                
                const touch = event.touches[0];
                currentY = touch.clientY;
                
                // 计算滑动距离
                const deltaY = currentY - startY;
                
                // 如果向下滑动，移动弹窗
                if (deltaY > 0) {
                    const modalContent = document.querySelector('.bottom-modal-content');
                    modalContent.style.transform = `translateY(${deltaY}px)`;
                }
            });
            
            groupInfoModal.addEventListener('touchend', function(event) {
                if (!isSwiping) return;
                
                const deltaY = currentY - startY;
                
                // 如果滑动距离超过100px，关闭弹窗
                if (deltaY > 100) {
                    closeModal();
                } else {
                    // 否则恢复原位置
                    const modalContent = document.querySelector('.bottom-modal-content');
                    modalContent.style.transform = 'translateY(0)';
                }
                
                isSwiping = false;
            });
            
            // 关闭弹窗函数
            function closeModal() {
                groupInfoModal.classList.remove('active');
                document.body.style.overflow = 'auto';
                
                // 重置弹窗位置
                const modalContent = document.querySelector('.bottom-modal-content');
                modalContent.style.transform = 'translateY(0)';
            }
            
            // 卡片点击效果（除了群组信息卡片）
            const cards = document.querySelectorAll('.card:not(#groupInfoCard)');
            cards.forEach(card => {
                card.addEventListener('click', function() {
                    console.log('点击了卡片:', this.textContent.trim());
                });
            });
            
            // 初始化假人头像更新器
            window.dummyUpdater = new DummyAvatarUpdater();
        });
        
        // 页面关闭时清理
        $(window).on('beforeunload', function() {
            if (window.dummyUpdater) {
                window.dummyUpdater.stopPolling();
            }
        });
    </script>
</body>
</html>