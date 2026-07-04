<?php
if (!defined('FROM_ROUTER')) {
    http_response_code(403);
    exit('喜乐科技@x60898');
}
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/dbconfig.php';
checkLogin();

$currentAgent = $_SESSION['username'];

$kefutxUrl = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['kefutx'])) {
    $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    $file = $_FILES['kefutx'];
    $fileName = uniqid() . '_' . basename($file['name']);
    $filePath = $uploadDir . $fileName;
    
    if (move_uploaded_file($file['tmp_name'], $filePath)) {
        $kefutxUrl = '/uploads/' . $fileName;
        echo json_encode(['success' => true, 'url' => $kefutxUrl]);
        exit;
    } else {
        echo json_encode(['success' => false, 'error' => '上传失败']);
        exit;
    }
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
function createChatSession($sessionId, $customerName, $agentAccount, $platform = '自定义') {
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
$xedataToken = createChatSession($sessionId, $customerName, $currentAgent, '自定义');

$currentDomain = $_SERVER['HTTP_HOST'];
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$baseUrl = $protocol . "://" . $currentDomain;
$customerServiceName = "喜乐"; // 默认客服名称
$originalServiceUrl = $baseUrl . '/' . "ChatZdy" . '?id=' . $sessionId;

// 添加XEDATA参数
if ($xedataToken) {
    $originalServiceUrl .= '&XEDATA=' . $xedataToken;
}

$originalServiceUrl .= '&zdykefu=' . urlencode($customerServiceName);

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
    <link rel="stylesheet" href="/assets/SharePhoto/zidingyi.css">
<style>
    .zidingyi-preview {
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
					<!-- 标题输入框 -->
					<div class="form-row">
						<label>标题</label>
						<input type="text" id="title-input" placeholder="请输入商品标题">
					</div>
					<!-- 价格输入框 -->
					<div class="form-row">
						<label>价格</label>
						<input type="text" id="price-input" placeholder="请输入价格">
					</div>
					<!-- 客服名称 -->
					<div class="form-row">
						<label>客服名称</label>
						<input type="text" id="customer-service-input" placeholder="请输入客服名称" value="喜乐">
					</div>
					<!-- 模板切换选择 -->
					<div class="form-row">
						<label>模板类型</label>
						<select id="template-select">
							<option value="game">游戏模板</option>
							<option value="physical">实物模板</option>
						</select>
					</div>

					<!-- 订单状态选择 -->
					<div class="form-row">
						<label>订单状态</label>
						<select id="order-status-select">
							<option value="进行中">进行中</option>
							<option value="担保中">担保中</option>
							<option value="待发货">待发货</option>
							<option value="待收货">待收货</option>
						</select>
					</div>
					<!-- 扫码支付方式选择 -->
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
					<!-- 封面图上传 -->
					<div class="form-row">
						<label>封面图</label>
						<label for="upload-cover" class="form-link" id="upload-label">上传图片</label>
						<input type="file" accept="image/*" id="upload-cover" style="display: none;">
					</div>
					<!-- 自定义图标上传（二维码中间图标） -->
					<div class="form-row">
						<label>QR图标</label>
						<label for="upload-qr-icon" class="form-link" id="upload-qr-icon-label">上传图标</label>
						<input type="file" accept="image/*" id="upload-qr-icon" style="display: none;">
					</div>
					<!-- 自定义底部banner上传 -->
					<div class="form-row">
						<label>底部横幅</label>
						<label for="upload-banner" class="form-link" id="upload-banner-label">上传图片</label>
						<input type="file" accept="image/*" id="upload-banner" style="display: none;">
					</div>
					<!-- 主题颜色 -->
					<div class="form-row">
						<label>主题色</label>
						<input type="color" id="theme-color-input" value="#4d8bff" style="width:40px;height:30px;border:1px solid #ddd;border-radius:6px;cursor:pointer;padding:2px;">
					</div>
					<!-- 客服头像上传 -->
					<div class="form-row">
						<label>客服头像</label>
						<label for="upload-kefutx" class="form-link" id="upload-kefutx-label">上传头像</label>
						<input type="file" accept="image/*" id="upload-kefutx" style="display: none;">
					</div>
				</div>
				
				<!-- 新的预览区域开始 -->
				<div class="zidingyi-preview order-status-container">
					<!-- 状态进度条卡片 -->
					<div class="status-card">
						<div class="status-header" id="preview-status-header">已下单，等待卖家联系客服</div>
						<div class="status-progress-container">
							<div class="status-progress-wrapper">
								<!-- 进度条轨道 -->
								<div class="status-progress-track"></div>
								
								<!-- 进度条填充 -->
								<div class="status-progress-fill"></div>
								
								<!-- 步骤点 -->
								<div class="status-steps" id="status-steps">
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

					<!-- 联系客服卡片 -->
					<div class="status-card">
						<div class="contact-section">
							<div class="contact-info">
								<h2 class="contact-title">联系客服</h2>
								<p class="contact-detail">客服：交易专员-<span class="contact-name" id="preview-customer-service"></span></p>
								<p class="contact-detail">请提醒卖家<span class="contact-method" id="preview-zhifu">微信</span>扫码处理</p>
								<p class="contact-tip">为保障您的交易安全，请勿将此二维码泄露给他人</p>
							</div>
							<div class="qr-code-container">
								<div class="qr-code-wrapper" id="qrcode">
									<!-- 二维码将通过JavaScript生成 -->
								</div>
								<div class="qr-logo">
									<img src="/xe-icon.png" alt="客服">
								</div>
							</div>
						</div>
					</div>
					
					<!-- 商品信息卡片 -->
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
								<span class="price-value" id="preview-order-status">进行中</span>
							</div>
							<div class="price-row">
								<span>订单编号</span>
								<span class="price-value"><?php echo date('YmdHis') . rand(1000, 9999); ?></span>
							</div>
						</div>
					</div>
					
					<!-- 底部横幅 -->
					<div class="status-card bottom-banner">
						<img src="/assets/img/zdybanner.png" alt="banner" class="banner-img" id="preview-banner">
					</div>
				</div>
				<!-- 新的预览区域结束 -->
			</div>
		</div>
	</div>
	<script src="/assets/qrcode.min.js"></script>
	<script src="/assets/qrcode-helper.js"></script>
	<script>
// 全局变量定义
var updateQRCodeTimeout = null;
var currentQRCode = null;
var kefutxUrl = '';

// 二维码更新函数
function updateQRCodeWithServiceName(serviceName) {
    // 获取原始链接（不包含zdykefu和kefutx参数的部分）
    var baseLink = "<?php 
        $baseLink = $baseUrl . '/' . "ChatZdy" . '?id=' . $sessionId;
        if ($xedataToken) {
            $baseLink .= '&XEDATA=' . $xedataToken;
        }
        echo $baseLink;
    ?>";
    
    // 创建新的完整链接
    var newLink = baseLink + '&zdykefu=' + encodeURIComponent(serviceName);
    
    // 添加客服头像参数
    if (kefutxUrl) {
        newLink += '&kefutx=' + encodeURIComponent(kefutxUrl);
    }
    
    // 处理防红链接和源站URL跳转
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
    
    // 清除之前的二维码
    var qrcodeContainer = document.getElementById("qrcode");
    
    // 先清除容器内容
    qrcodeContainer.innerHTML = "";
    
    // 如果存在之前的 QRCode 实例，先销毁它
    if (currentQRCode) {
        try {
            // QRCode.js 库没有官方的销毁方法，所以我们清空容器
            currentQRCode.clear();
        } catch(e) {
            // 如果 clear 方法不存在，直接清空容器
            qrcodeContainer.innerHTML = "";
        }
    }
    
    // 生成新的二维码
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

// 游戏模板步骤点
var gameSteps = ['已付款', '联系客服', '客服核号', '换绑账号', '完成交易'];
// 实物模板步骤点
var physicalSteps = ['已付款', '待发货', '待检验', '待收货', '待评价'];

// 在 DOM 完全加载后执行
document.addEventListener('DOMContentLoaded', function() {
    // 获取所有 DOM 元素
    var titleInput = document.getElementById('title-input');
    var priceInput = document.getElementById('price-input');
    var customerServiceInput = document.getElementById('customer-service-input');
    var orderStatusSelect = document.getElementById('order-status-select');
    var paySelect = document.getElementById('pay-select');
    var uploadCover = document.getElementById('upload-cover');
    var uploadLabel = document.getElementById('upload-label');
    var templateSelect = document.getElementById('template-select');
    var uploadQrIcon = document.getElementById('upload-qr-icon');
    var uploadQrIconLabel = document.getElementById('upload-qr-icon-label');
    var uploadBanner = document.getElementById('upload-banner');
    var uploadBannerLabel = document.getElementById('upload-banner-label');
    
    var previewPrice = document.getElementById('preview-price');
    var previewPrice2 = document.getElementById('preview-price2');
    var previewPayType = document.getElementById('preview-zhifu');
    var previewImage = document.getElementById('preview-image');
    var previewTitle = document.getElementById('preview-title');
    var previewCustomerService = document.getElementById('preview-customer-service');
    var previewOrderStatus = document.getElementById('preview-order-status');
    var previewStatusHeader = document.getElementById('preview-status-header');
    var statusSteps = document.getElementById('status-steps');
    var previewBanner = document.getElementById('preview-banner');
    var qrLogoImg = document.querySelector('.qr-logo img');
    
    // 获取初始客服名称
    var initialServiceName = customerServiceInput.value || '喜乐';
    
    // 生成初始二维码
    updateQRCodeWithServiceName(initialServiceName);
    
    // 客服名称输入监听
    customerServiceInput.addEventListener('input', function() {
        var serviceName = this.value || '喜乐';
        previewCustomerService.textContent = serviceName;
        
        // 防抖处理，避免频繁更新二维码
        clearTimeout(updateQRCodeTimeout);
        updateQRCodeTimeout = setTimeout(function() {
            updateQRCodeWithServiceName(serviceName);
        }, 500); // 延迟 500ms
    });
    
    // 价格输入监听
    priceInput.addEventListener('input', function() {
        var price = this.value;
        // 如果用户输入了数字，添加¥符号
        if (price && !isNaN(price)) {
            var formattedPrice = formatPrice(price);
            previewPrice.textContent = '¥' + formattedPrice;
            previewPrice2.textContent = '¥' + formattedPrice;
        } else {
            previewPrice.textContent = '¥0.00';
            previewPrice2.textContent = '¥0.00';
        }
    });
    
    // 价格格式化函数
    function formatPrice(price) {
        // 移除非数字字符
        var num = price.replace(/[^\d.]/g, '');
        if (!num) return '0.00';
        
        // 格式化为两位小数
        var parsed = parseFloat(num);
        if (isNaN(parsed)) return '0.00';
        
        return parsed.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    }
    
    // 支付方式监听
    paySelect.addEventListener('change', function() {
        previewPayType.textContent = this.value;
    });
    
    // 标题输入监听
    titleInput.addEventListener('input', function() {
        previewTitle.textContent = this.value || '商品标题';
    });
    
    // 订单状态选择监听
    orderStatusSelect.addEventListener('change', function() {
        previewOrderStatus.textContent = this.value;
    });
    
    // 模板切换监听
    templateSelect.addEventListener('change', function() {
        var template = this.value;
        updateTemplate(template);
    });
    
    // 更新模板函数
    function updateTemplate(template) {
        if (template === 'physical') {
            // 实物模板
            previewStatusHeader.textContent = '已付款，等待联系客服验机';
            updateSteps(physicalSteps);
        } else {
            // 游戏模板
            previewStatusHeader.textContent = '已下单，等待卖家联系客服';
            updateSteps(gameSteps);
        }
    }
    
    // 更新步骤点函数
    function updateSteps(steps) {
        var currentColor = themeColorInput.value;
        var stepElements = statusSteps.querySelectorAll('.status-step');
        stepElements.forEach(function(stepElement, index) {
            var circle = stepElement.querySelector('.status-step-circle');
            var text = stepElement.querySelector('.status-step-text, .status-step-text-px');
            
            // 更新文字
            text.textContent = steps[index];
            
            // 更新样式：第一个步骤是激活状态（主题色），其他是灰色
            if (index === 0) {
                circle.classList.add('active');
                text.className = 'status-step-text-px';
                circle.style.borderColor = currentColor;
                circle.style.backgroundColor = currentColor;
                text.style.color = currentColor;
            } else {
                circle.classList.remove('active');
                text.className = 'status-step-text';
                circle.style.borderColor = '';
                circle.style.backgroundColor = '';
                text.style.color = '';
            }
        });
    }
    
    // 封面图片上传监听
    uploadCover.addEventListener('change', function(e) {
        var file = e.target.files[0];
        if (file) {
            var reader = new FileReader();
            reader.onload = function(e) {
                previewImage.src = e.target.result;
                uploadLabel.textContent = '已上传';
                // 确保图片显示
                previewImage.style.display = 'block';
            };
            reader.readAsDataURL(file);
        }
    });
    
    // 二维码图标上传监听
    uploadQrIcon.addEventListener('change', function(e) {
        var file = e.target.files[0];
        if (file) {
            var reader = new FileReader();
            reader.onload = function(e) {
                qrLogoImg.src = e.target.result;
                uploadQrIconLabel.textContent = '已上传';
            };
            reader.readAsDataURL(file);
        }
    });
    
    // 底部横幅上传监听
    uploadBanner.addEventListener('change', function(e) {
        var file = e.target.files[0];
        if (file) {
            var reader = new FileReader();
            reader.onload = function(e) {
                previewBanner.src = e.target.result;
                uploadBannerLabel.textContent = '已上传';
            };
            reader.readAsDataURL(file);
        }
    });
    
    // 客服头像上传监听
    var uploadKefutx = document.getElementById('upload-kefutx');
    var uploadKefutxLabel = document.getElementById('upload-kefutx-label');
    
    // 主题颜色选择器
    var themeColorInput = document.getElementById('theme-color-input');
    themeColorInput.addEventListener('input', function() {
        var color = this.value;
        // 动态修改所有使用主题色的元素
        document.querySelectorAll('.status-step-circle.active').forEach(function(el) {
            el.style.borderColor = color;
            el.style.backgroundColor = color;
        });
        document.querySelectorAll('.status-step-text-px').forEach(function(el) {
            el.style.color = color;
        });
        document.querySelectorAll('.status-progress-fill').forEach(function(el) {
            el.style.backgroundColor = color;
        });
        document.querySelectorAll('.contact-method').forEach(function(el) {
            el.style.color = color;
        });
        document.querySelectorAll('.qr-code-container').forEach(function(el) {
            el.style.borderColor = color;
        });
    });
    uploadKefutx.addEventListener('change', function(e) {
        var file = e.target.files[0];
        if (file) {
            var formData = new FormData();
            formData.append('kefutx', file);
            
            var xhr = new XMLHttpRequest();
            xhr.open('POST', window.location.href, true);
            xhr.onload = function() {
                if (xhr.status === 200) {
                    try {
                        var response = JSON.parse(xhr.responseText);
                        if (response.success) {
                            kefutxUrl = response.url;
                            uploadKefutxLabel.textContent = '已上传';
                            
                            // 更新二维码
                            var serviceName = customerServiceInput.value || '喜乐';
                            updateQRCodeWithServiceName(serviceName);
                            
                            showToast('头像上传成功！', 'success');
                        } else {
                            showToast('上传失败: ' + response.error, 'error');
                        }
                    } catch (e) {
                        showToast('上传失败，服务器响应异常', 'error');
                    }
                } else {
                    showToast('上传失败，HTTP状态: ' + xhr.status, 'error');
                }
            };
            xhr.onerror = function() {
                showToast('上传失败，网络错误', 'error');
            };
            xhr.send(formData);
        }
    });
    
    // 初始化默认价格显示
    if (priceInput.value) {
        var formattedPrice = formatPrice(priceInput.value);
        previewPrice.textContent = '¥' + formattedPrice;
        previewPrice2.textContent = '¥' + formattedPrice;
    }
    
    // 初始化标题显示
    previewTitle.textContent = titleInput.value || '商品标题';
    
    // 初始化支付方式显示
    previewPayType.textContent = paySelect.value;
    
    // 初始化图片显示
    if (!previewImage.src) {
        previewImage.style.display = 'none';
    }

    // 复制链接按钮功能
    var copyLinkBtn = document.getElementById('copy-link-btn');
    if (copyLinkBtn) {
        copyLinkBtn.addEventListener('click', function() {
            var serviceName = document.getElementById('customer-service-input').value || '喜乐';
            
            // 构建完整链接
            var baseLink = "<?php 
                $baseLink = $baseUrl . '/' . "ChatZdy" . '?id=' . $sessionId;
                if ($xedataToken) {
                    $baseLink .= '&XEDATA=' . $xedataToken;
                }
                echo $baseLink;
            ?>";
            
            var linkToCopy = baseLink + '&zdykefu=' + encodeURIComponent(serviceName);
            
            // 添加客服头像参数
            if (kefutxUrl) {
                linkToCopy += '&kefutx=' + encodeURIComponent(kefutxUrl);
            }
            
            <?php if ($antiRedConfig && $antiRedConfig['apply_status'] === 'on' && !empty($antiRedConfig['api_url'])): ?>
                var antiRedApiUrl = "<?php echo $antiRedConfig['api_url']; ?>";
                var encodedLink = btoa(unescape(encodeURIComponent(linkToCopy)));
                linkToCopy = antiRedApiUrl + encodedLink;
            <?php elseif ($siteUrlConfig && !empty($siteUrlConfig['site_url_enabled']) && !empty($siteUrlConfig['site_url'])): ?>
                var siteUrl = "<?php echo $siteUrlConfig['site_url']; ?>";
                var encodedLink = btoa(unescape(encodeURIComponent(linkToCopy)));
                linkToCopy = siteUrl + encodedLink;
            <?php endif; ?>
            
            // 复制到剪贴板
            copyToClipboard(linkToCopy);
        });
    }
    
    // 复制函数
    function copyToClipboard(text) {
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(function() {
                showToast('链接已复制到剪贴板！', 'success');
            }).catch(function(err) {
                console.error('复制失败: ', err);
                fallbackCopy(text);
            });
        } else {
            fallbackCopy(text);
        }
    }
    
    // 备用复制方法
    function fallbackCopy(text) {
        var textArea = document.createElement('textarea');
        textArea.value = text;
        textArea.style.position = 'fixed';
        textArea.style.opacity = '0';
        textArea.style.left = '-9999px';
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        
        try {
            var successful = document.execCommand('copy');
            document.body.removeChild(textArea);
            if (successful) {
                showToast('链接已复制到剪贴板！', 'success');
            } else {
                showToast('复制失败，请手动复制', 'error');
            }
        } catch (err) {
            console.error('复制失败:', err);
            document.body.removeChild(textArea);
            showToast('复制失败，请手动复制', 'error');
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
});

// updateQRCodeWithServiceName 函数实现
function updateQRCodeWithServiceName(serviceName) {
    // 获取原始链接（不包含zdykefu和kefutx参数的部分）
    var baseLink = "<?php 
        $baseLink = $baseUrl . '/' . "ChatZdy" . '?id=' . $sessionId;
        if ($xedataToken) {
            $baseLink .= '&XEDATA=' . $xedataToken;
        }
        echo $baseLink;
    ?>";
    
    // 移除可能存在的&zdykefu和&kefutx参数
    baseLink = baseLink.replace(/&zdykefu=[^&]*/, '');
    baseLink = baseLink.replace(/&kefutx=[^&]*/, '');
    
    // 确保链接以?或&开头
    if (baseLink.indexOf('?') === -1) {
        baseLink += '?';
    } else if (!baseLink.endsWith('?') && !baseLink.endsWith('&')) {
        baseLink += '&';
    }
    
    // 创建新的完整链接
    var newLink = baseLink + 'zdykefu=' + encodeURIComponent(serviceName);
    
    // 添加客服头像参数
    if (kefutxUrl) {
        newLink += '&kefutx=' + encodeURIComponent(kefutxUrl);
    }
    
    // 处理防红链接和源站URL跳转
    var finalLink = newLink;
    <?php if ($antiRedConfig && $antiRedConfig['apply_status'] === 'on' && !empty($antiRedConfig['api_url'])): ?>
        var antiRedApiUrl = "<?php echo $antiRedConfig['api_url']; ?>";
        // 注意：btoa对非ASCII字符可能有问题
        try {
            var encodedLink = btoa(unescape(encodeURIComponent(newLink)));
            finalLink = antiRedApiUrl + encodedLink;
        } catch(e) {
            console.error("Base64编码错误:", e);
            finalLink = newLink; // 回退到原始链接
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
    
    // 清理容器
    var qrcodeContainer = document.getElementById("qrcode");
    qrcodeContainer.innerHTML = "";
    
    // 创建新的容器
    var qrDiv = document.createElement("div");
    qrDiv.id = "qrcode-" + Date.now();
    
    // 添加到容器
    qrcodeContainer.appendChild(qrDiv);
    
    // 生成新的二维码
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