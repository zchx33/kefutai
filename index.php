<?php
if (!defined('FROM_ROUTER')) {
    http_response_code(403);
    exit('喜乐科技@x60898');
}
    // 引入数据库配置文件
    require_once $_SERVER['DOCUMENT_ROOT'] . '/config/dbconfig.php';
    
    // 使用配置文件中的getDB函数获取数据库连接
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
                    <p>未找到付款页面，请检查链接是否正确。</p>
                    <hr>
                    <p class="mb-0">如果您是付款人，请联系收款方获取正确的付款链接。</p>
                </div>
              </div>';
        exit;
    }
    
    // 查询付款页信息
    $sql = "SELECT id, page_title, amount, api_url, payment_method, status, page_code, created_at 
            FROM payment_pages 
            WHERE page_code = ? AND status = 'active'";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $page_code);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        // 没有找到对应的付款页
        echo '<div class="container text-center mt-5">
                <div class="alert alert-warning" role="alert">
                    <h4 class="alert-heading">付款页面不存在或已失效！</h4>
                    <p>该付款页面可能已被删除、禁用或链接错误。</p>
                    <hr>
                    <p class="mb-0">请确认链接是否正确，或联系收款方获取新的付款链接。</p>
                </div>
              </div>';
        $stmt->close();
        $conn->close();
        exit;
    }
    
    // 获取付款页数据
    $payment_page = $result->fetch_assoc();
    $stmt->close();
    
    // 获取支付方式显示文本
    $payment_method_text = "";
    $payment_method_icon = "";
    switch($payment_page['payment_method']) {
        case 'alipay':
            $payment_method_text = "支付宝";
            $payment_method_icon = "fab fa-alipay";
            break;
        case 'wechat':
            $payment_method_text = "微信支付";
            $payment_method_icon = "fab fa-weixin";
            break;
        case 'bank':
            $payment_method_text = "银行转账";
            $payment_method_icon = "fas fa-university";
            break;
        default:
            $payment_method_text = "在线支付";
            $payment_method_icon = "fas fa-credit-card";
    }
    ?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>银联安全支付</title>
     <link rel="icon" href="/pages/pay/1.png">
    <style>
        /* 基础样式重置 */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            border: 0;
            font-family: ui-sans-serif, system-ui, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", "Noto Color Emoji";
        }
        
        body {
            background-color: #ffffff;
            font-family: ui-sans-serif, system-ui, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", "Noto Color Emoji";
            padding-bottom: 4rem;
            line-height: 1.5;
            overflow-x: hidden;
        }
        
        /* 布局容器 */
        .wrapper {
            min-height: 100vh;
            padding-top: 0.001em;
        }
        
        .content-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding-top: 3rem;
            padding-left: 1rem;
            padding-right: 1rem;
        }
        
        /* 银联logo */
        .logo-container {
            margin-bottom: 2.5rem;
        }
        
        .logo {
            display: block;
            width: 220px;
            height: 56px;
            max-width: 100%;
        }
        
        /* 提示文字 */
        .payment-prompt {
            font-size: 16px;
            color: #1f2937; /* gray-800 */
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            justify-content: center;
        }
        
        .payment-method-name {
            font-weight: 600;
            font-size: 16px;
            margin-left: 0.25rem;
            margin-right: 0.25rem;
        }
        
        .payment-method-icon {
            width: 24px;
            height: 24px;
            margin-left: 0.375rem;
            margin-right: 0.375rem;
        }
        
        /* 不同支付方式的颜色 */
        .alipay-color {
            color: #1677FF;
        }
        
        .wechat-color {
            color: #09BB07;
        }
        
        .bank-color {
            color: #666666;
        }
        
        .default-color {
            color: #000000;
        }
        
        /* 订单编号 */
        .order-info {
            color: #6b7280; /* gray-500 */
            margin-bottom: 1.5rem;
            font-size: 1rem;
        }
        
        .order-number {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
            letter-spacing: 0.05em;
        }
        
        /* 金额 */
        .amount {
            color: #e64340;
            margin-bottom: 1.5rem;
            font-size: 3.75rem;
            font-weight: 700;
        }
        
        .currency-symbol {
            font-size: 3rem;
            position: relative;
            top: -0.1em;
            margin-right: 2px;
        }
        
        /* 二维码 */
        .qrcode-container {
            margin-bottom: 1rem;
        }
        
        .qrcode {
            display: block;
            width: 280px;
            height: 280px;
            max-width: 100%;
        }
        
        /* 页脚 */
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background-color: #d93a34;
            color: #ffffff;
            font-size: 10px;
            padding-top: 0.75rem;
            padding-bottom: 0.75rem;
            width: 100%;
        }
        
        .footer-content {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            align-items: center;
            text-align: center;
            column-gap: 1.5rem;
            row-gap: 0.25rem;
            padding-left: 1rem;
            padding-right: 1rem;
        }
        
        /* 响应式设计 - 媒体查询 */
        /* 小屏幕设备 (手机) */
        @media (max-width: 639px) {
            .content-container {
                padding-top: 2rem;
            }
            
            .amount {
                font-size: 2.5rem;
            }
            
            .currency-symbol {
                font-size: 2rem;
            }
            
            .payment-prompt {
                text-align: center;
            }
            
            .payment-method-icon {
                margin: 0.5rem 0;
            }
            
            .qrcode {
                width: 240px;
                height: 240px;
            }
        }
        
        /* 中等屏幕设备 (平板) */
        @media (min-width: 768px) {
            .content-container {
                padding-top: 4rem;
            }
        }
        
        /* 大屏幕设备 (桌面) */
        @media (min-width: 1024px) {
            .content-container {
                padding-top: 5rem;
            }
            
            .qrcode {
                width: 320px;
                height: 320px;
            }
        }
        
        /* 高分辨率屏幕 */
        @media (-webkit-min-device-pixel-ratio: 2), (min-resolution: 2dppx) {
            .logo, .qrcode {
                image-rendering: -webkit-optimize-contrast;
            }
        }
        
        /* 打印样式 */
        @media print {
            .footer {
                position: relative;
            }
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="content-container">
            <div class="logo-container">
                <img class="logo" src="/pages/pay/2.png" alt="UnionPay China UnionPay logo">
            </div>
            <p class="payment-prompt">
                <span>请使用</span>
                <?php
                // 根据支付方式设置颜色和图标
                $color_class = 'default-color';
                $svg_icon = '';
                
                switch($payment_page['payment_method']) {
                    case 'alipay':
                        $color_class = 'alipay-color';
                        $svg_icon = '<svg t="1766849316882" class="icon" viewBox="0 0 1325 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="5549" width="36" height="36"><path d="M240.941176 512c0 233.411765 188.235294 421.647059 421.647059 421.647059s421.647059-188.235294 421.647059-421.647059S896 90.352941 662.588235 90.352941 240.941176 278.588235 240.941176 512z" fill="#5A9EF7" p-id="5550"></path><path d="M751.435294 564.705882s15.058824-21.082353 30.117647-63.247058c16.564706-42.164706 18.070588-66.258824 18.070588-66.258824l-120.470588-1.505882v-40.658824l146.070588-1.505882v-30.117647H677.647059v-66.258824h-72.282353V361.411765H466.823529v30.117647l137.035295-1.505883v45.176471h-108.42353v24.094118h225.882353c-1.505882 15.058824-6.023529 28.611765-10.541176 42.164706-9.035294 22.588235-18.070588 43.670588-18.070589 43.670588s-105.411765-37.647059-162.635294-37.647059c-55.717647 0-123.482353 22.588235-131.011764 87.341176-6.023529 64.752941 31.623529 100.894118 85.835294 112.941177 52.705882 12.047059 102.4 0 146.070588-21.082353 43.670588-21.082353 85.835294-69.270588 85.835294-69.270588l224.376471 109.929411s13.552941-21.082353 24.094117-40.658823c7.529412-13.552941 13.552941-28.611765 19.576471-42.164706l-233.411765-79.811765z m-231.905882 106.917647c-79.811765 0-94.870588-39.152941-94.870588-66.258823 0-27.105882 16.564706-58.729412 84.329411-63.247059 66.258824-4.517647 158.117647 48.188235 158.117647 48.188235s-67.764706 81.317647-147.57647 81.317647z" fill="#FFFFFF" p-id="5551"></path></svg>';
                        break;
                    case 'wechat':
                        $color_class = 'wechat-color';
                        $svg_icon = '<svg t="1766849339436" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="6792" width="24" height="24"><path d="M404.511405 600.865957c-4.042059 2.043542-8.602935 3.223415-13.447267 3.223415-11.197016 0-20.934798-6.169513-26.045189-15.278985l-1.959631-4.296863-81.56569-178.973184c-0.880043-1.954515-1.430582-4.14746-1.430582-6.285147 0-8.251941 6.686283-14.944364 14.938224-14.944364 3.351328 0 6.441713 1.108241 8.94165 2.966565l96.242971 68.521606c7.037277 4.609994 15.433504 7.305383 24.464181 7.305383 5.40101 0 10.533914-1.00284 15.328104-2.75167l452.645171-201.459315C811.496653 163.274644 677.866167 100.777241 526.648117 100.777241c-247.448742 0-448.035176 167.158091-448.035176 373.361453 0 112.511493 60.353576 213.775828 154.808832 282.214547 7.582699 5.405103 12.537548 14.292518 12.537548 24.325012 0 3.312442-0.712221 6.358825-1.569752 9.515724-7.544837 28.15013-19.62599 73.202209-20.188808 75.314313-0.940418 3.529383-2.416026 7.220449-2.416026 10.917654 0 8.245801 6.692423 14.933107 14.944364 14.933107 3.251044 0 5.89015-1.202385 8.629541-2.7793l98.085946-56.621579c7.377014-4.266164 15.188934-6.89913 23.790846-6.89913 4.577249 0 9.003048 0.703011 13.174044 1.978051 45.75509 13.159718 95.123474 20.476357 146.239666 20.476357 247.438509 0 448.042339-167.162184 448.042339-373.372709 0-62.451354-18.502399-121.275087-51.033303-173.009356L407.778822 598.977957 404.511405 600.865957z" fill="#00C800" p-id="6793"></path></svg>';
                        break;
                    case 'bank':
                        $color_class = 'bank-color';
                        $svg_icon = '<svg class="payment-method-icon" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path fill="#666666" d="M12 2L2 7v2h20V7L12 2zM4 11v6h3v-6H4zm5 0v6h3v-6H9zm5 0v6h3v-6h-3zm5 0v6h3v-6h-3zM2 21h20v2H2v-2z"/></svg>';
                        break;
                    default:
                        $color_class = 'default-color';
                        $svg_icon = '<svg class="payment-method-icon" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path fill="#000000" d="M20 4H4c-1.11 0-1.99.89-1.99 2L2 18c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V6c0-1.11-.89-2-2-2zm0 14H4v-6h16v6zm0-10H4V6h16v2z"/></svg>';
                }
                ?>
                <span class="payment-method-name <?php echo $color_class; ?>"><?php echo $payment_method_text; ?></span>
                <?php echo $svg_icon; ?>
                <span>扫码进行付款</span>
            </p>
            <p class="order-info">
                <span>订单编号: </span>
                <span class="order-number"><?php echo $payment_page['page_code']; ?></span>
            </p>
            <p class="amount">
                <span class="currency-symbol">¥</span><?php echo $payment_page['amount']; ?>
            </p>
            <div class="qrcode-container">
                <img class="qrcode" src="https://api.qrtool.cn/?text=<?php echo $payment_page['api_url']; ?>" alt="A square QR code for payment.">
            </div>
        </div>
        <footer class="footer">
            <div class="footer-content">
                <span>© 中国银联版权所有</span>
                <span>沪ICP备07032180号-2</span>
                <span>沪公网安备31011502002335号</span>
            </div>
        </footer>
    </div>
</body>
</html>