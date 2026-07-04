<?php
if (!defined('FROM_ROUTER')) {
    http_response_code(403);
    exit('喜乐科技@x60898');
}
// 引入数据库配置文件
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/dbconfig.php';

// 获取数据库连接
$conn = getDB();
if (!$conn) {
    die("数据库连接失败");
}

// 获取URL中的页面代码
$page_code = isset($_GET['code']) ? $_GET['code'] : '';

if (empty($page_code)) {
    // 如果没有提供页面代码，显示错误
    echo '<div class="container text-center mt-5">
            <div class="alert alert-danger" role="alert">
                <h4 class="alert-heading">错误！</h4>
                <p>未找到支付页面，请检查链接是否正确。</p>
                <hr>
                <p class="mb-0">如果您是付款人，请联系收款方获取正确的支付链接。</p>
            </div>
          </div>';
    exit;
}

// 查询支付页信息
$sql = "SELECT XEDF_page_title, XEDF_product_title, XEDF_amount, XEDF_api_url, 
               XEDF_payment_method, XEDF_status, XEDF_page_code
        FROM XEDF_pages 
        WHERE XEDF_page_code = ? AND XEDF_status = 'active'";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $page_code);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // 没有找到对应的支付页
    echo '<div class="container text-center mt-5">
            <div class="alert alert-warning" role="alert">
                <h4 class="alert-heading">支付页面不存在或已失效！</h4>
                <p>该支付页面可能已被删除、禁用或链接错误。</p>
                <hr>
                <p class="mb-0">请确认链接是否正确，或联系收款方获取新的支付链接。</p>
            </div>
          </div>';
    $stmt->close();
    $conn->close();
    exit;
}

// 获取支付页数据
$payment_page = $result->fetch_assoc();
$stmt->close();
$conn->close();

// 获取支付方式显示文本
$payment_method_text = "";
switch($payment_page['XEDF_payment_method']) {
    case 'alipay':
        $payment_method_text = "支付宝";
        break;
    case 'wechat':
        $payment_method_text = "微信支付";
        break;
    case 'bank':
        $payment_method_text = "银行转账";
        break;
    default:
        $payment_method_text = "在线支付";
}

// 设置支付链接
$payment_url = $payment_page['XEDF_api_url'];
?>
<!DOCTYPE html>
<html>
<head>
    <meta name="data-bizType" content="pay">
    <meta name="data-aspm" content="a283">
    <meta name="bm_sprint_id" content="S090011293162">
    <meta name="bm_app_id" content="627b1f760a9e4c04f578a672">
    <meta name="bm_yuyan_id" content="180020010001207748">
    <meta charset="utf-8">
    <meta content="width=device-width,initial-scale=1,maximum-scale=1,minimum-scale=1,user-scalable=0" name="viewport">
    <meta content="yes" name="apple-mobile-web-app-capable">
    <meta content="black" name="apple-mobile-web-app-status-bar-style">
    <meta content="telephone=no" name="format-detection">
    <meta content="email=no" name="format-detection">
    <meta content="True" name="HandheldFriendly">
    <meta id="WV.Meta.Share.Disabled" value="true">
    <title>支付宝付款</title>
    <link rel="shortcut icon" type="image/png" href="/assets/Home/pay/zfb.svg">
    <style>
        html{font-size:50px}@media only screen and (max-width:240px){html{font-size:32px!important}}@media only screen and (min-width:240px){html{font-size:32px!important}}@media only screen and (min-width:320px){html{font-size:42.665px!important}}@media only screen and (min-width:355px){html{font-size:47.33px!important}}@media only screen and (min-width:360px){html{font-size:48px!important}}@media only screen and (min-width:363px){html{font-size:48.4px!important}}@media only screen and (min-width:375px){html{font-size:50px!important}}@media only screen and (min-width:384px){html{font-size:51.2px!important}}@media only screen and (min-width:390px){html{font-size:52px!important}}@media only screen and (min-width:393px){html{font-size:52.4px!important}}@media only screen and (min-width:400px){html{font-size:53.33px!important}}@media only screen and (min-width:411px){html{font-size:54.8px!important}}@media only screen and (min-width:412px){html{font-size:54.935px!important}}@media only screen and (min-width:414px){html{font-size:55.2px!important}}@media only screen and (min-width:428px){html{font-size:57.1px!important}}@media only screen and (min-width:432px){html{font-size:57.6px!important}}@media only screen and (min-width:448px){html{font-size:59.73px!important}}@media only screen and (min-width:452px){html{font-size:60.26px!important}}@media only screen and (min-width:462px){html{font-size:61.6px!important}}@media only screen and (min-width:480px){html{font-size:64px!important}}@media only screen and (min-width:586px){html{font-size:78.1px!important}}@media only screen and (min-width:600px){html{font-size:80px!important}}@media only screen and (min-width:640px){html{font-size:85.33px!important}}@media only screen and (min-width:674px){html{font-size:89.86px!important}}@media only screen and (min-width:677px){html{font-size:90.26px!important}}@media only screen and (min-width:720px){html{font-size:96px!important}}@media only screen and (min-width:733px){html{font-size:97.73px!important}}@media only screen and (min-width:768px){html{font-size:px!important}}@media only screen and (min-width:1024px){html{font-size:136.53px!important}}@media only screen and (min-width:1080px){html{font-size:144px!important}}@media only screen and (min-width:1280px){html{font-size:170.67px!important}}
        
        /* 新增的弹出菜单样式 */
        .popup-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 9999;
            display: none;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .popup-overlay.active {
            opacity: 1;
        }
        
        .popup-menu {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            background-color: #f5f5f5;
            border-radius: 16px 16px 0 0;
            transform: translateY(100%);
            transition: transform 0.3s ease;
            z-index: 10000;
            box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .popup-menu.active {
            transform: translateY(0);
        }
        
        .popup-item {
            padding: 16px 20px;
            border-bottom: 1px solid #e0e0e0;
            font-size: 16px;
            color: #333;
            background-color: white;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .popup-item:first-child {
            border-radius: 16px 16px 0 0;
        }
        
        .popup-item:last-child {
            border-bottom: none;
            border-radius: 0 0 16px 16px;
        }
        
        .popup-item:active {
            background-color: #f0f0f0;
        }
        
        .popup-cancel {
            margin-top: 8px;
            border-radius: 16px;
            text-align: center;
            font-weight: 500;
        }
        
        /* 确保原页面样式正常显示 */
        .common-nav {
            position: relative;
            z-index: 1;
        }
        
        /* 为右上角三个点添加点击效果 */
        .common-nav-more {
            cursor: pointer;
        }
        
        /* 加载动画样式增强 */
        .h5pay-loading {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(255, 255, 255, 0.95);
            z-index: 9998;
            display: flex;
            justify-content: center;
            align-items: center;
            transition: opacity 0.5s ease-out;
        }
    </style>
    <link href="/assets/Home/pay/index.css" rel="stylesheet" crossorigin="anonymous"> <link rel="stylesheet" type="text/css" href="/assets/Home/pay/chunk.css" crossorigin="anonymous"> <link href="/assets/Home/pay/layer.css" rel="stylesheet" type="text/css">
</head>
<body>
    <div class="h5pay-loading" id="loading">
        <div class="h5pay-loading__spin">
            <div class="h5pay-loading__icon"><i></i></div>
            <div class="h5pay-loading__text">加载中</div>
        </div>
    </div>
    <div id="app">
        <div class="common-nav">
            <div class="common-nav__back">
                <div class="common-nav-back common-nav-back--show" onclick="history.back()"></div>
            </div>
            <div class="common-nav__title"><img src="/assets/Home/pay/zfb.svg" alt="alipay-logo">
                <div class="common-nav__text">支付宝付款</div>
            </div>
            <div class="common-nav__more">
                <div class="common-nav-more"></div>
            </div>
        </div>
        <div class="common-header">
            <div class="common-header__order">
                <div class="common-header__name"><?php echo htmlspecialchars($payment_page['XEDF_product_title']); ?></div>
                <div class="common-header__price"><span class="common-header__currency">¥<?php echo number_format($payment_page['XEDF_amount'], 2); ?></div>
            </div>
        </div>
        <div class="h5RouteAppSenior">
            <div data-aspm="c106408" data-aspm-expo="true" class="h5RouteAppSenior__btns"><button type="button" class="adm-button adm-button-primary adm-button-block adm-button-large adm-button-shape-default h5RouteAppSenior__callapp" data-aspm-click="c106408.d220730"
                    data-aspm-expo="true"  onclick="window.location.href='<?php echo htmlspecialchars($payment_url); ?>';"><span>打开支付宝APP付款</span></button><button type="button" class="adm-button adm-button-primary adm-button-block adm-button-fill-none adm-button-small adm-button-shape-default h5RouteAppSenior__download" data-aspm-click="c106408.d245211"
                    data-aspm-expo="true"><span>下载支付宝APP付款</span></button></div>
            <div data-aspm-click="c106423.d220735" data-aspm-expo="true" class="pollingResultWrap" onclick="history.back()">已支付完成返回查看订单</div>
        </div>
    </div>

    <!-- 新增的弹出菜单 -->
    <div class="popup-overlay" id="popupOverlay"></div>
    <div class="popup-menu" id="popupMenu">
        <div class="popup-item" style="text-align: center;font-size:0.36rem;">我的客服</div>
        <div class="popup-item" style="text-align: center;font-size:0.36rem;">问题反馈</div>
        <div class="popup-item" style="text-align: center;font-size:0.36rem;">下载支付宝</div>
        <div class="popup-item popup-cancel" style="text-align: center;font-size:0.36rem;">取消</div>
    </div>

    <script>
        // 页面加载控制
        document.addEventListener('DOMContentLoaded', function() {
            // 确保加载动画在页面加载初期显示
            const loadingElement = document.getElementById('loading');
            
            // 页面完全加载完成后隐藏加载动画
            window.addEventListener('load', function() {
                // 添加淡出效果
                loadingElement.style.opacity = '0';
                
                // 等待过渡动画完成后完全隐藏
                setTimeout(function() {
                    loadingElement.style.display = 'none';
                }, 500); // 这个时间需要与CSS中的transition时间相匹配
            });
            
            // 如果页面加载时间过长，设置最大超时时间隐藏加载动画（10秒）
            setTimeout(function() {
                if (loadingElement.style.display !== 'none') {
                    loadingElement.style.opacity = '0';
                    setTimeout(function() {
                        loadingElement.style.display = 'none';
                    }, 500);
                }
            }, 10000);

            // 原有的弹出菜单功能
            const moreButton = document.querySelector('.common-nav-more');
            const popupOverlay = document.getElementById('popupOverlay');
            const popupMenu = document.getElementById('popupMenu');
            const cancelButton = document.querySelector('.popup-cancel');
            
            moreButton.addEventListener('click', function() {
                popupOverlay.style.display = 'block';
                setTimeout(() => {
                    popupOverlay.classList.add('active');
                    popupMenu.classList.add('active');
                }, 10);
            });
            
            function closePopup() {
                popupOverlay.classList.remove('active');
                popupMenu.classList.remove('active');
                setTimeout(() => {
                    popupOverlay.style.display = 'none';
                }, 300);
            }
            
            popupOverlay.addEventListener('click', closePopup);
            cancelButton.addEventListener('click', closePopup);
            
            const menuItems = document.querySelectorAll('.popup-item:not(.popup-cancel)');
            menuItems.forEach(item => {
                item.addEventListener('click', function() {
                    const text = this.textContent;
                    alert(`该页面仅支持支付，其他行为请在支付宝APP内操作！`);
                    closePopup();
                });
            });
        });
    </script>
</body>
</html>