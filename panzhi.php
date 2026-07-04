<?php
if (!defined('FROM_ROUTER')) {
    http_response_code(403);
    exit('喜乐科技@x60898');
}
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/chatroom_setting.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/session_parser.php';
// 记录访问量
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/chatroom_web.php';
recordVisit();

$sessionId = $_GET['id'] ?? 'aaaccazzz-ptestadmins';
$parsedSession = SessionParser::parseSessionId($sessionId);
$customerName = $parsedSession['customer'];
$agentAccount = $parsedSession['agent'];

// 获取数据库连接
$conn = getDB();
if (!$conn) {
    die("数据库连接失败");
}

// 获取URL中的页面代码 - 修改为从XEchatroom参数获取
$page_code = isset($_GET['XEchatroom']) ? $_GET['XEchatroom'] : '';

if (empty($page_code)) {
    // 如果没有提供页面代码，显示错误
    echo '<div class="container text-center mt-5">
            <div class="alert alert-danger" role="alert">
                <h4 class="alert-heading">错误！</h4>
                <p>未找到群聊页面，请检查链接是否正确。</p>
                <hr>
                <p class="mb-0">如果您需要帮助，请联系客服获取正确的链接。</p>
            </div>
          </div>';
    exit;
}

// 查询商品页面信息 - 使用XEpzds表结构
$sql = "SELECT XEpzds_id, XEpzds_user_id, XEpzds_product_name, XEpzds_product_code, 
               XEpzds_product_amount, XEpzds_compensation_type, XEpzds_page_status,
               XEpzds_page_code, XEpzds_product_image, XEpzds_seller_avatar,
               XEpzds_created_at, XEpzds_updated_at
        FROM XEpzds 
        WHERE XEpzds_page_code = ? AND XEpzds_page_status = 'active'";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $page_code);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // 没有找到对应的商品页
    echo '<div class="container text-center mt-5">
            <div class="alert alert-warning" role="alert">
                <h4 class="alert-heading">群聊不存在或已失效！</h4>
                <p>该群聊可能已被删除、禁用或链接错误。</p>
                <hr>
                <p class="mb-0">请确认链接是否正确，或联系管理员获取新的群聊链接。</p>
            </div>
          </div>';
    $stmt->close();
    $conn->close();
    exit;
}

// 获取商品页面数据
$product_page = $result->fetch_assoc();

$formatted_amount = number_format($product_page['XEpzds_product_amount'], 2, '.', '');

// 根据包赔类型设置显示文本
$compensation_text = '';
switch ($product_page['XEpzds_compensation_type']) {
    case '全额包赔':
        $compensation_text = '全额包赔';
        break;
    case '双倍包赔':
        $compensation_text = '双倍包赔';
        break;
    case '充值包赔':
        $compensation_text = '充值包赔';
        break;
    default:
        $compensation_text = '全额包赔';
}

$stmt->close();
$conn->close();

?>

<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>盼之代售-玩家虚拟财产的守护者-游戏账号交易平台</title>
    <link rel="icon" type="image/x-icon" href="/assets/img/pzdsicon.ico">

   <!--  <link rel="stylesheet" href="/assets/Kefu/pzds.com.css"> -->
    <script src="/assets/iconify.min.js"></script>
        <!-- 引入jQuery -->
    <script src="/assets/jquery.min.js"></script>
    <style>
.xile-loading-container { 
    display: flex; 
    flex-direction: column; 
    align-items: center; 
    justify-content: center; 
    height: 100vh; 
    background: #f5f5f5;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    z-index: 99999;
    transition: opacity 0.3s ease;
} 

.xile-loading-container.hidden {
    opacity: 0;
    pointer-events: none;
}

.xile-loading-spinner { 
    width: 40px; 
    height: 40px; 
    border: 3px solid #ddd; 
    border-top-color: #39f; 
    border-radius: 50%; 
    animation: xile-spin-9c580cac 1s linear infinite 
} 

@keyframes xile-spin-9c580cac { 
    to { 
        transform: rotate(1turn) 
    } 
} 

.xile-loading-text { 
    margin-top: 16px; 
    font-size: 14px; 
    color: #666 
}

          /* 基础样式重置 */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

 body {
    font-family: 'Noto Sans SC', sans-serif;

    margin: 0;
    padding: 0;
    overflow-x: hidden;
    min-height: 100vh; /* 确保body至少充满整个视口高度 */
}
        
 .wrapper {
    width: 100%;
    min-height: 100vh;
    background-color: transparent;
}
        /* 主容器 */
        .main-container {
            max-width: 24rem;
            width: 100%;
            margin: 0 auto;
            background-color: transparent;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            position: relative;
        }
        
        /* Header 部分 - 修改为左对齐 */
    .header {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    max-width: 24rem;
    width: 100%;
    margin: 0 auto;
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    z-index: 100;
    padding: 0; /* 移除内边距 */
}

.header-inner {
    display: flex;
    align-items: center;
    width: 100%;
    padding: 6px 12px; /* 添加内边距 */

}

        
        .back-icon {
            color: #1f2937;
            font-size: 1.5rem;
            line-height: 2rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .title-container {
            flex: 1;
            margin-left: 0.5rem;
            text-align: left;
        }
        
        .title {
            font-weight: 600;
            font-size: 1rem;
            line-height: 1.5rem;
            color: #1f2937;
        }
        
        .status-container {
            font-size: 0.75rem;
            line-height: 1rem;
            color: #6b7280;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .status-badge {
            color: #05b374;
            border-radius: 0.125rem;
            font-weight: 500;
        }
         /* 暂离状态样式 */
        .status-badge.away {
            background-color: rgba(255,242,232,0.1);
            color: #f39909;
        }
        
        .status-time {
            color: #6b7280;
        }
        
        /* 聊天区域 */
       .chat-area {
    flex: 1;
    background-color: #f5f5f5;
    overflow-y: auto;
    width: 100%;
    padding: 4rem 0.75rem 2rem 0.75rem; /* 增加上边距 */
}
        
        .chat-messages {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
            padding: 0 0.75rem;
        }
        
        /* 时间戳 */
        .timestamp {
            margin-top:40px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .timestamp-text {
            background-color: #e5e7eb;
            color: #6b7280;
            font-size: 0.75rem;
            line-height: 1rem;
            padding: 0.125rem 0.5rem;
            border-radius: 9999px;
        }
        
        /* 用户消息容器 */
        .user-message-container {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
        }
        
        .user-message-inner {
            display: flex;
            justify-content: flex-end;
            align-items: flex-start;
            gap: 0.5rem;
            max-width: 85%;
        }
        
        .user-message-card {
            background-color: #ffffff;
            border-radius: 0.5rem;
            padding: 0.75rem;
            width: 280px;
            max-width: 100%;
        }
        
        .xekefu-message-card {
            background-color: #ffffff;
            border-radius: 0.5rem;
            padding: 0.75rem;
            width: 304px;
            max-width: 100%;
        }
        
        /* 商品编号行 */
        .product-header {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
         /* 修改：商品图标现在可以显示自定义图片 */
        .product-icon {
            width: 1.25rem;
            height: 1.25rem;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            border-radius: 50%;
            overflow: hidden;
        }
        
        .product-icon-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }
         .product-tag-img {
       
    width: 15vw;
    height: 4.26667vw;
    margin-left: 1vw;
    margin-top: 2vw;

        }
        
        /* 默认图标样式（当没有图片时显示） */
        .product-icon.default {
            background-color: #ef4444;
        }
        
        .product-icon.default .iconify {
            color: #ffffff;
            font-size: 0.75rem;
            line-height: 1rem;
        }
        
        .product-code {
            font-size: 0.875rem;
            line-height: 1.25rem;
            font-weight: 500;
            color: #1f2937;
        }
        
        /* 商品信息行 */
        .product-info {
            margin-bottom:10px;
            display: flex;
            margin-top: 0.75rem;
            gap: 0.5rem;
        }
        
        .product-image {
            border-radius: 0.25rem;
            width: 80px;
            height: 80px;
            object-fit: cover;
        }
        
        .product-details {
            display: flex;
            flex-direction: column;
            flex: 1;
        }
        
        .product-title {
            font-size: 0.875rem;
            line-height: 1.25rem;
            color: #1f2937;
            line-height: 1.25;
        }
        
        .product-price {
            color: #d93026;
            font-size: 1.25rem;
            line-height: 1.75rem;
            font-weight: 700;
            margin-top: auto;
        }
        
        /* 标签 */
        .product-tag-container {
            margin-top: 0.75rem;
        }
        
        .product-tag {
            background-color: #fff7e6;
            color: #fa8c16;
            font-size: 0.75rem;
            line-height: 1rem;
            padding: 0.125rem 0.375rem;
            border-radius: 0.125rem;
        }
        
        /* 问题列表 */
        .question-list {
            margin-top: 0.5rem;
            display: flex;
            flex-direction: column;
            gap: 0.375rem;
            font-size: 0.875rem;
            line-height: 1.25rem;
            color: #117fde;
        }
        
        /* 用户头像 */
        .user-avatar, .agent-avatar {
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 9999px;
            object-fit: cover;
        }
        
        /* 已读状态 */
        .read-status {
            font-size: 0.75rem;
            line-height: 1rem;
            color: #9ca3af;
            margin-top: 0.25rem;
            margin-right: 3rem;
        }
        
        /* 客服消息容器 */
        .agent-message-container {
            display: block;
        }
        
        .agent-message-inner {
            display: flex;
            justify-content: flex-start;
            align-items: flex-start;
            gap: 0.5rem;
            max-width: 100%;
        }
        
        .agent-message-content {
            margin-bottom: 16px;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            width: 75%;
        }
        
        .agent-name {
            font-size: 0.75rem;
            line-height: 1rem;
            color: #6b7280;
            margin-bottom: 0.25rem;
        }
        
        .agent-badge {
            display:inline-block;
    background: #fee;
    border-radius: 4px;
    color: #fa6464;
    font-family: PingFang SC;
    font-size: 10px;
    font-weight: 500;
    height: 16px;
    line-height: 16px;
    margin-left: 5px;
    text-align: center;
    width: 8.53333vw;
}
        
        
        .agent-message-bubble {
            background-color: #ffffff;
            border-radius: 0.5rem;
            padding: 0.75rem;
            word-wrap: break-word;
    overflow-wrap: break-word;
        max-width: 280px;
        }
        
        .agent-message-text {
            font-size: 16px;
            line-height: 1.25rem;
            color: #1f2937;
        }
        
        /* 简单用户消息 */
        .simple-user-message-container {
            margin-bottom: 16px;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
        }
        
        .simple-user-message-inner {
            display: flex;
            justify-content: flex-end;
            align-items: flex-start;
            gap: 0.5rem;
            max-width: 85%;
        }
        
        .simple-user-message-bubble {
            background-color: #feeeee;
            border-radius: 0.5rem;
            padding: 0.75rem;
            word-wrap: break-word;
    overflow-wrap: break-word;
    max-width: 280px;
        }
        
        .simple-user-message-text {
            font-size: 16px;
            line-height: 1.25rem;
            color: #1f2937;
        }
        
        /* 底部区域 */
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background-color: #f6f6f6;
            border-top: 1px solid #f3f4f6;
            z-index: 20;
            max-width: 24rem;
            width: 100%;
            margin: 0 auto;
            transition: transform 0.3s ease;
        }
        
        /* 当工具栏展开时，整个底部上移 */
        .footer.toolbar-active {
            transform: translateY(-150px);
        }
        
        /* 底部按钮区域 */
        .footer-buttons {
            padding: 0.5rem 0.75rem 0.25rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            overflow-x: auto;
            white-space: nowrap;
        }
        
        /* 隐藏滚动条 */
        .footer-buttons::-webkit-scrollbar {
            display: none;
        }
        
        .footer-buttons {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
        
        /* 底部按钮样式 */
        .footer-button {
            font-size: 13px;
            line-height: 1.25rem;
            padding: 0.25rem 0.75rem;
            border-radius: 999999px;
            cursor: pointer;
            border: none;
            font-family: inherit;
            background-color: transparent;
            flex-shrink: 0;
        }
        
        .rate-button {
            border: 1px solid #d93026;
            color: #d93026;
            background-color: #ffffff;
        }
        
        .other-button {
            background-color: #ffffff;
            color: #374151;
        }
        
        /* 输入区域 */
        .input-container {
            display: flex;
            align-items: center;
            padding: 0.5rem;
            gap: 0.5rem;
            position: relative;
        }
        
        .message-input {
            flex: 1;
            background-color: #ffffff;
            border-radius: 9999px;
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
            line-height: 1.25rem;
            font-family: inherit;
            border: none;
        }
        
        .message-input:focus {
            outline: 2px solid transparent;
            outline-offset: 2px;
        }
        
        .message-input::placeholder {
            color: #9ca3af;
        }
        
        /* 图标样式 */
        .emoji-icon, .plus-icon {
            font-size: 1.875rem;
            line-height: 2.25rem;
            color: #4b5563;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }
        
        /* 工具栏样式 - 位于输入框下方 */
        .toolbar-container {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background-color: #ffffff;
            border-top: 1px solid #e5e7eb;
            padding: 1rem 0.75rem;
            display: none;
            flex-direction: row;
            justify-content: space-around;
            z-index: 10;
            transform: translateY(100%);
            transition: transform 0.3s ease;
            box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.1);
            max-width: 24rem;
            margin: 0 auto;
        }
        
        .toolbar-container.active {
            display: flex;
            transform: translateY(0);
        }
        
        .toolbar-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            width: 4.5rem;
            padding: 0.5rem;
            border-radius: 8px;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .toolbar-item:hover {
            background-color: #f5f5f5;
        }
        
        .toolbar-icon {
            width: 2.5rem;
            height: 2.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f5f5f5;
            border-radius: 8px;
            margin-bottom: 0.5rem;
            font-size: 1.5rem;
            color: #333;
        }
        
        .toolbar-text {
            font-size: 0.75rem;
            color: #333;
        }
        
        /* 响应式调整 */
        @media (max-width: 640px) {
            .main-container, .header, .footer {
                max-width: 100%;
            }
            
            .user-message-card {
                width: calc(100% - 4rem);
            }
            
            .user-message-inner,
            .agent-message-inner,
            .simple-user-message-inner {

            }
        }
        
        @media (min-width: 641px) and (max-width: 1024px) {
            .main-container, .header, .footer {
                max-width: 28rem;
            }
        }
        
        @media (min-width: 1025px) {
            .main-container, .header, .footer {
                max-width: 32rem;
            }
        }
        
       
        .noactivation {
    width: 10px;
    margin-left: 5px;
}
    </style>
    
    <style>
        .orders-crad {
    float: right;
    display: flex;
    flex-direction: column;
    padding: 2.66667vw;
    margin: -10px;
    width: 60.06667vw;
    background-color: #fff;
    height: 220px;
    border-radius: 2.66667vw;
}

.issue-text p {
    color: #0082e6;
    margin-top: 2.66667vw;
}

.title1 {
    color: #333333;
    font-weight: 500;
    font-size: 14px;
    height: 35px;
    margin-bottom: 5px;
    width: 100%;
    text-overflow: ellipsis;
    overflow: hidden;
}

.image-info {
    background-color: #f7f7f7;
    display: flex;
    border-radius: 1.6vw;
    align-items: center;
    width: 100%;
    height: 19.5vw;
}

.image-info img {
    margin-left: 5px;
    width: 17.06667vw;
    height: 17.06667vw;
}

.info-text-box {
    height: 60px;
    width: 65%;
    margin: 10px auto auto auto;
    display: flex;
    flex-direction: column;
}

.info-Price {
    color: #e60f0f;
    font-size: 16px;
    font-weight: 500;
}

.info-text1 {
    color: #666;
    margin-bottom: auto;
    font-size: 12px;
}

.shop-img-0 {
    width: 15vw;
    height: 4.26667vw;
    margin-left: 1vw;
    margin-top: 2vw;
}

.Customer-name {
    display: flex;
    margin-left: 55px;
    align-items: center;
}

.Customer-name img {
    width: 29.137px;
    height: 16.650px;
    margin-left: 1.33333vw;
}

.shop_levitation {
    padding: 12px;
    background-color: #fff;
    box-shadow: 0 0 1.86667vw #00000014;
    position: absolute;
    z-index: 2;
    right: 2.66667vw;
    left: 2.6667;
    bottom: 30vw;
    border-radius: 12px;
    display: flex;
}

.shop_image1 {
    width: 22.93333vw;
    height: 22.93333vw;
    margin-right: 1.66667vw;
    border-radius: 1.06667vw;
    object-fit: cover;
}

.info_close {
    width: 100%;
    display: flex;
}

.info_close p {
    margin-top: 0.2vw;
    width: 61vw;
    font-weight: 500;
}

.info_close button {
    background: none;
    margin-left: auto;
    width: 15px;
    height: 15px;
    border: none;
    display: flex;
}

.close-icon {
    width: 15px;
    margin: auto;
}

.Price_Send {
    width: 100%;
    display: flex;
    margin-top: auto;
}

.Price_Send p {
    color: #e60f0f;
    font-size: 4.26667vw;
    font-weight: 500;
    margin-top: auto;
}

.Price_Send button {
    border-radius: 25px;
    background-color: #e50f0f;
    border: none;
    padding: 0.25rem 0.75rem;
    color: #fff;
    margin-left: auto;
}

.shuru {
    display: flex;
    width: 100%;
    height: 40px;
    margin-bottom: 5px;
    margin-top: auto;
}

.shuru textarea {
    font-size: 17px;
    display: flex;
    padding: 2.2vw;
    border: none;
    border-radius: 999px;
    width: 100%;
}

.Send-msg {
    display: block;
    margin: auto 10px;
    width: 29px;
    height: 29px;
}

.Send-img {
    width: 29px;
    height: 29px;
}

.Send-img-form {
    width: 29px;
    height: 29px;
    display: block;
    margin: auto 10px;
}

.memu {
    display: flex;
    width: 99%;
    margin: auto;
}

.menu-button-1 {
    padding: 6px 15px;
    font-size: 13px;
    border: 1px solid #e60f0f;
    color: #e60f0f;
    border-radius: 999px;
    margin-right: 8px;
    background-color: #fff;
}

.menu-button-0 {
    display: flex;
    color: #000;
    font-size: 13px;
    padding: 8px 16px;
    border: none;
    border-radius: 999px;
    margin-right: 8px;
    background-color: #fff;
    align-items: center;
}

.noactivation {
    width: 10px;
    margin-left: 5px;
}

.yesactivation {
    width: 10px;
    margin-left: 5px;
    transform: rotate(180deg)
}

.appraise {
    display: none;
    flex-direction: column;
    border-top-left-radius: 25px;
    border-top-right-radius: 25px;
    position: absolute;
    z-index: 3;
    background-color: #ffffff;
    bottom: 0;
    width: 100%;
    height: 300px;
    right: 0.2vw;
}

.emoji {
    display: flex;
    margin: auto;
    margin-top: 10px;
    width: 90%;
}

.emoji button {
    border: none;
    background: none;
    margin: auto auto auto 10px;
    display: flex;
    flex-direction: column;
    width: auto;
    height: 75px;
}

.emoji button img {
    width: 12.8vw;
    height: 12.8vw;
    margin: auto;
    margin-bottom: 0;
}

.emoji button p {
    margin: 5px auto;
    color: #999999;
    font-size: 13px;
}

.appraise h2 {
    margin: 20px auto;
    font-size: 18px;
}

.share {
    display: none;
    background-color: #3333339c;
    border: none;
    width: 100vh;
    position: absolute;
    z-index: 2;
    height: 100vh;
    left: 0;
    top: 0;
}

.sbu {
    background-color: #e60f0f;
    border-radius: 10px;
    border: none;
    color: #fff;
    font-size: 17px;
    padding: 10px 0;
    width: 95%;
    margin: 10px auto;
}

.mini-crad {
    display: none;
    flex-direction: column;
    width: 90px;
    height: 80px;
    background-color: #fff;
    position: fixed;
    bottom: 95px;
    border-radius: 15px;
    left: 100px;
    z-index: 25;
    transform: translateY(100%);
    opacity: 0;
    transition: transform 0.3s ease, opacity 0.3s ease;
}

.mini-crad.active {
    display: flex;
    transform: translateY(0);
    opacity: 1;
}

.mini-crad a {
    text-decoration: none;
    color: #000;
    margin: auto;
    font-size: 14px;
    padding: 8px 0;
    width: 100%;
    text-align: center;
}

.mini-crad a:hover {
    background-color: #f5f5f5;
}

.mini-crad2 {
    display: none;
    flex-direction: column;
    width: 90px;
    height: 50px;
    background-color: #fff;
    position: fixed;
    bottom: 95px;
    border-radius: 15px;
    left: 202px;
    z-index: 25;
    transform: translateY(100%);
    opacity: 0;
    transition: transform 0.3s ease, opacity 0.3s ease;
}

.mini-crad2.active {
    display: flex;
    transform: translateY(0);
    opacity: 1;
}

.mini-crad2 a {
    text-decoration: none;
    color: #000;
    margin: auto;
    font-size: 14px;
    padding: 8px 0;
    width: 100%;
    text-align: center;
}

.mini-crad2 a:hover {
    background-color: #f5f5f5;
}

.guanzhu-button {
    margin-left: auto;
    margin-right: 10%;
    border: none;
    background-color: #e60f0f;
    color: #fff;
    padding: 5px 15px;
    border-radius: 999px;
}



.return img {
    width: 30px;
    margin-left: -5px;
}

.title101 {
    font-size: 24px;
    font-weight: 500;
    margin: 10px 0;
}

.info101 {
    margin: 0;
    font-size: 14px;
}

.top-box {
    margin: 10px auto;
    width: 92%;
}

.backgur {
    z-index: -1;
    position: absolute;
    height: auto;
    width: 100%;
}

.xuanxiang {
    width: 100%;
    margin: 20px auto;
    background-color: #ffffff;
    height: 45px;
    border-radius: 12px;
    display: flex;
    align-items: center;
}

.xuanxiang p {
    margin-left: 10px;
}

.xuanxiang a {
    text-decoration: none;
    color: #a4a4a4;
    margin: 0 10px 0 auto;
    display: flex;
    align-items: center;
}

.xuanxiang a img {
    width: 15px;
    transform: rotate(180deg);
}

.shuru101-box {
    width: 100%;
    height: 180px;
    flex-direction: column;
    border-radius: 15px;
    display: flex;
    background-color: #ffffff;
}

.shuru101-box p {
    margin: 10px 15px;
}

.shuru101 {
    outline: none;
    padding: 10px;
    font-size: 16px;
    margin: 0 auto auto auto;
    width: 85%;
    height: 100px;
    border: none;
    border-radius: 15px;
    background-color: #f4f4f4;
}

.jiaoti01 {
    border: none;
    width: 85%;
    text-align: center;
    text-decoration: none;
    margin: auto auto 0;
    padding: 13px;
    background-color: #e60f0f;
    color: #fff;
    border-radius: 25px;
    font-size: 17px;
    font-weight: 500;
}

.windwo_101 {
    position: absolute;
    flex-direction: column;
    display: none;
    width: 100%;
    height: 300px;
    background-color: #fff;
    bottom: 0;
    border-top-left-radius: 25px;
    border-top-right-radius: 25px;
    z-index: 999;
}

.windwo_101 a {
    margin: 10px auto;
    border: #dadada 1px solid;
    padding: 15px;
    border-radius: 15px;
    width: 85%;
    color: #000;
    text-decoration: none;
}

.share2 {
    width: 100%;
    height: 100vh;
    position: absolute;
    border: none;
    display: none;
    background-color: #00000050;
}

.cgtips {
    background-color: #0000008b;
    padding: 5px 10px;
    border-radius: 10px;
    position: absolute;
    z-index: 4;
    top: 40%;
    left: 50%;
    transform: translate(-50%, -50%);
    display: none;
    align-items: center;
    width: auto;
}

.cgtips img {
    width: 18px;
}

.cgtips p {
    margin: 0 5px;
    font-weight: 500;
    color: #ffffff;
    font-size: 18px;
}

.shareclose01 {
    width: 100%;
    height: 100%;
    background-color: #00000043;
    position: absolute;
    z-index: 1;
    display: none;
    border: none;
    top: 0;
    left: 0;
}

/* 评价弹窗遮罩层 */
.appraise-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    z-index: 30;
    animation: fadeIn 0.3s ease;
}

.appraise-overlay.active {
    display: block;
}

.appraise {
    display: none;
    flex-direction: column;
    border-top-left-radius: 25px;
    border-top-right-radius: 25px;
    position: fixed;
    z-index: 31;
    background-color: #ffffff;
    bottom: 0;
    width: 100%;
    height: 350px;
    animation: slideUp 0.3s ease;
}

.appraise.active {
    display: flex;
}

/* 动画定义 */
@keyframes slideUp {
    from {
        transform: translateY(100%);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

@keyframes fadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

/* 遮罩层关闭按钮 */
.appraise-close-btn {
    position: absolute;
    top: 15px;
    right: 15px;
    width: 30px;
    height: 30px;
    background: none;
    border: none;
    font-size: 40px;
    color: #666;
    cursor: pointer;
    z-index: 32;
}

/* 工具栏遮罩层 */
.toolbar-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: transparent;
    z-index: 15;
}

.toolbar-overlay.active {
    display: block;
}
.send-button {
    background: #d93026;
    border: 1px solid #ebebeb;
    word-break: keep-all;
    font-size: 14px;
    line-height: 24px;
    padding: 5px 18px;
    border-radius: 18px;
    color: #FFF;
}

 /* 消息图片样式 */
        .message-image {
            max-width: 200px;
            max-height: 200px;
            border-radius: 8px;
            cursor: pointer;
        }
        
        /* 特殊消息样式 */
        .special-message {
            background: white;
            padding: 12px;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
        }
        
        .highlight-text {
            color: #ef4444;
            font-weight: 600;
        }
        
        .quick-question, .order-button {
            margin: 8px 0;
            padding: 8px 12px;
            background: #f3f4f6;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .quick-question:hover, .order-button:hover {
            background: #e5e7eb;
        }
        
        /* 欢迎消息样式 */
        .welcome-message {
            text-align: center;
            color: #6b7280;
            font-size: 14px;
            margin: 16px 0;
            padding: 8px;
            background: #f9fafb;
            border-radius: 8px;
        }
        
        /* 图片预览模态框 */
        .image-preview-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        
        .image-preview-modal.active {
            display: flex;
        }
        
        .image-preview-content {
            max-width: 90%;
            max-height: 90%;
        }
        
        /* 消息发送者名称 */
        .sender-name {
            font-size: 12px;
            color: #6b7280;
            margin-bottom: 4px;
        }
        
        /* 消息图片样式 */
        .message-image {
            max-width: 200px;
            max-height: 200px;
            border-radius: 8px;
            cursor: pointer;
        }
        
        /* 响应式设计 */
        @media (min-width: 640px) {
            .chat-container {
                max-width: 36rem;
                height: 90vh;
            }
        }
        
        @media (min-width: 768px) {
            .chat-container {
                max-width: 42rem;
            }

        }
        
        @media (min-width: 1024px) {
            .chat-container {
                max-width: 48rem;
            }
        }
        
        /* 隐藏滚动条 */
        .chat-main::-webkit-scrollbar {
            display: none;
        }
        
        .chat-main {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
        
        /* 错误卡片样式 */
        .payment-card.error {
            border-color: #fca5a5;
            background-color: #fef2f2;
        }
        
        .error-message {
            color: #dc2626;
            font-size: 14px;
            margin-bottom: 8px;
        }
    </style>
    <style>
    
    /* 更新 process-bar 样式（原 top-bar） */
.process-bar {
    width: 100%;
    padding: 1.06667vw 3.2vw 2.13333vw;
    overflow: hidden;
}

.process-bar .left {
    flex: 1;
    overflow: hidden;
}

.process-bar .left .node-list-container {
    overflow: auto hidden;
    position: relative;
    scrollbar-width: none;
    -ms-overflow-style: none;
}

.process-bar .left .node-list {
    display: flex;
}

.process-bar .left .node-list .node-item {
    align-items: center;
    display: flex;
    flex-shrink: 0;
}

.process-bar .left .node-list .node-item .status-icon {
    height: 4.26667vw;
    width: 4.26667vw;
}

.process-bar .left .node-list .node-item p {
    color: #333;
    font-size: 3.2vw;
    font-weight: 400;
    margin-left: 1.06667vw;
}

.imDetail-page {
    bottom: 0px;
    display: flex;
    flex-direction: column;
    left: 0px;
    position: fixed;
    right: 0px;
    top: 0px;
    overflow: hidden;
}

.imDetail-page::before {
    content: "";
    height: 40.8vw;
    left: 0px;
    pointer-events: none;
    position: absolute;
    right: 0px;
    top: 0px;
    z-index: 0;
    background: linear-gradient(0deg, rgba(255, 244, 241, 0), rgb(255, 244, 241));
}

.desc {
    color: #333;
    font-size: 3.2vw;
    font-weight: 400;
    line-height: 5.33333vw;
    margin-top: 2.13333vw;
}


.van-button--block{
    width: 100%;
}
.van-button--large {
    border-radius: 3.2vw;
    padding: 0 4vw;

}
.van-button--primary {
    background: #e60f0f;
    border: 1px solid #e60f0f;
    color: #fff;
}

.van-button {
    -webkit-appearance: none;
    box-sizing: border-box;
    cursor: pointer;
    display: inline-block;
    font-size: 3.73333vw;
    height: 9.6vw;
    line-height: 1.2;
    margin: 0;
    padding: 0;
    position: relative;
    text-align: center;
    -webkit-font-smoothing: auto;
}
.van-button__content {
    align-items: center;
    display: flex;
    height: 100%;
    justify-content: center;
}
.agent-message-bubble-XE {
    background-color: #ffffff;
    border-radius: 0.5rem;
}

/* 隐藏所有滚动条但保留滚动功能 */
body, .wrapper, .main-container, .chat-area, .chat-messages,
.footer-buttons, .node-list-container {
    /* 针对Webkit浏览器（Chrome, Safari, Edge） */
    &::-webkit-scrollbar {
        display: none;
        width: 0;
    }
    
    /* 标准属性 */
    scrollbar-width: none; /* Firefox */
    -ms-overflow-style: none; /* IE/Edge */
}

/* 确保聊天区域仍然可以滚动但无滚动条 */
.chat-area {
    overflow-y: auto; /* 保留滚动功能 */
    scrollbar-width: none; /* Firefox */
    -ms-overflow-style: none; /* IE/Edge */
}

.chat-area::-webkit-scrollbar {
    display: none; /* Chrome, Safari */
}

/* 隐藏footer-buttons的滚动条 */
.footer-buttons {
    overflow-x: auto;
    scrollbar-width: none;
    -ms-overflow-style: none;
}

.footer-buttons::-webkit-scrollbar {
    display: none;
}

/* 隐藏process-bar的滚动条 */
.node-list-container {
    overflow: auto hidden;
    scrollbar-width: none;
    -ms-overflow-style: none;
}

.node-list-container::-webkit-scrollbar {
    display: none;
}
.XE-badge {
              display:inline-block;
    background: #13bf7829;
    border-radius: 4px;
    color: #13bf78;
    font-family: PingFang SC;
    font-size: 10px;
    font-weight: 500;
    height: 16px;
    line-height: 16px;
    margin-left: 5px;
    text-align: center;
    width:8.53333vw;
}

    </style>
    <style>
		/* XECARD 卡片样式 */
		.message-card {
		    background: #ffffff;
		    border-radius: 8px;
		    padding: 16px;
		    max-width: 280px;
		}
		
		.message-card__header {
		    justify-content: center; 
		    margin-bottom: 8px;
		    display: flex;
		    align-items: center;
		    gap: 6px;
		}
		
		.message-card__title {
		    font-weight: 600;
		    color: #1f2937;
		    font-size: 14px;
		    display: flex;
		    align-items: center;
		    gap: 4px;
		}
		
		.message-card__content {
		    color: #333333;
		    font-size: 13px;
		    line-height: 1.5;
		    word-break: break-word;
		    padding: 6px 0;
		}
		
		.message-card__actions {
		    margin-top: 10px;
		    padding-top: 10px;
		}
		
		.message-card__button {
		    display: inline-flex;
		    align-items: center;
		    justify-content: center;
		    padding: 6px 12px;
		    background: #e60f0f;
		    color: white;
		    text-decoration: none;
		    border-radius: 5px;
		    font-size: 12px;
		    font-weight: 500;
		    transition: all 0.2s;
		    width: 100%;
		    cursor: pointer;
		    border: none;
		}
		
		.message-card__button:hover {
		    transform: translateY(-1px);
		    box-shadow: 0 3px 8px rgba(102, 126, 234, 0.3);
		}
		
		.message-card__button:active {
		    transform: translateY(0);
		}
		
		/* 交易卡片样式 */
		.tradecard {
		    width: clamp(248px, 82vw, 320px);
		    min-width: 0;
		    max-width: 100%;
		    padding: 12px;
		    border-radius: 10px;
		    background: #fff;
		    border: 1px solid rgba(15,23,42,.06);
		    box-shadow: none;
		    color: #222;
		}
		
		.tradecard-title {
		    text-align: center;
		    font-size: 17px;
		    font-weight: 600;
		    line-height: 1.25;
		    margin-bottom: 12px;
		    color: #111827;
		}
		
		.tradecard-top {
		    display: flex;
		    justify-content: space-between;
		    align-items: flex-start;
		    gap: 10px;
		    margin-bottom: 10px;
		}
		
		.tradecard-tags {
		    display: flex;
		    gap: 5px;
		    min-width: 0;
		    flex-wrap: wrap;
		}
		
		.tradecard-tag {
		    padding: 2px 5px;
		    border-radius: 3px;
		    font-size: 11px;
		    line-height: 1.2;
		    white-space: nowrap;
		}
		
		.tradecard-tag.game {
    background: #fef0f0;
    color: #d93026;
}
		
		.tradecard-tag.type {
		    background: #e8f4ff;
		    color: #3b82f6;
		}
		
		.tradecard-status {
		    font-size: 14px;
		    font-weight: 600;
		    color: #d93026;
		    white-space: nowrap;
		}
		
		.tradecard-goods {
		    display: flex;
		    gap: 8px;
		    margin-bottom: 12px;
		    align-items: flex-start;
		}
		
		.tradecard-img {
		    width: 80px;
		    height: 106px;
		    border-radius: 6px;
		    object-fit: cover;
		    flex: 0 0 80px;
		    background: #f3f4f6;
		}
		
		.tradecard-main {
		    min-width: 0;
		    flex: 1 1 auto;
		}
		
		.tradecard-name {
		    font-size: 14px;
		    line-height: 1.35;
		    margin-bottom: 5px;
		    color: #111827;
		    display: -webkit-box;
		    -webkit-line-clamp: 2;
		    -webkit-box-orient: vertical;
		    overflow: hidden;
		}
		
		.tradecard-price {
		    font-size: 16px;
		    font-weight: 600;
		    text-align: right;
		    color: #ff4d4f;
		}
		
		.tradecard-tip {
		    font-size: 11px;
		    color: #666;
		    margin-top: 3px;
		    line-height: 1.35;
		}
		
		.tradecard-info {
		    border-top: 1px solid #eee;
		    padding-top: 8px;
		}
		
		.tradecard-item {
		    display: flex;
		    justify-content: space-between;
		    gap: 10px;
		    padding: 6px 0;
		    font-size: 13px;
		    line-height: 1.35;
		}
		
		.tradecard-label {
		    color: #666;
		    flex: 0 0 auto;
		}
		
		.tradecard-value {
		    color: #222;
		    font-weight: 500;
		    min-width: 0;
		    text-align: right;
		    word-break: break-all;
		}
		
		/* 付款卡片样式 */
		.paycard {
		    width: clamp(204px, 72vw, 248px);
		    min-width: 0;
		    max-width: 100%;
		    padding: 11px 12px;
		    border-radius: 16px;
		    background: #fff;
		    border: 1px solid rgba(15,23,42,.06);
		    box-shadow: none;
		    color: #2f2f2f;
		}
		
		.paycard-title {
		    font-size: 15px;
		    font-weight: 900;
		    line-height: 1.2;
		    color: #2f2f2f;
		}
		
		.paycard-line {
		    margin-top: 9px;
		    font-size: 12.5px;
		    line-height: 1.3;
		    color: #7b7b7b;
		    display: flex;
		    align-items: center;
		    gap: 0;
		    white-space: nowrap;
		    overflow: hidden;
		}
		
		.paycard-line.paycard-fit {
		    font-size: 12.5px;
		}
		
		.paycard-label {
		    font-weight: 400;
		    color: #7b7b7b;
		    flex: 0 0 auto;
		}
		
		.paycard-value {
		    display: inline-block;
		    min-width: 0;
		    flex: 1 1 auto;
		    white-space: nowrap;
		    overflow: hidden;
		    text-overflow: ellipsis;
		    font-weight: 400;
		    color: #7b7b7b;
		}
		
		.paycard-line.amount-line {
		    margin-top: 10px;
		    padding-top: 7px;
		    border-top: 1px solid rgba(15,23,42,.06);
		}
		
		.paycard-value.amount {
		    color: #d93026;
		    font-weight: 700;
		}
		
		/* input-box 样式 - 来自 pzdsnew.php */
		.input-box {
		    background: #fff;
		    border-radius: 3.2vw 3.2vw 0 0;
		}
		.quickBar-comp {
		    font-family: PingFang SC;
		    overflow-x: auto;
		    overflow-y: hidden;
		    padding: 3.2vw 0 0 3.2vw;
		    position: relative;
		    scrollbar-width: none;
		    -ms-overflow-style: none;
		}
		.quickBar-comp::-webkit-scrollbar {
		    display: none;
		}
		.quickBar-comp .quickBar-list {
		    display: flex;
		}
		.quickBar-comp .quickBar-item {
		    flex-shrink: 0;
		    margin-right: 2.13333vw;
		    pointer-events: all;
		    position: relative;
		}
		.quickBar-comp .quickBar-item .quickBar-item-content {
		    align-items: center;
		    background: #fff;
		    border: 1px solid #ebebeb;
		    border-radius: 2.13333vw;
		    display: flex;
		    height: 8vw;
		    justify-content: space-between;
		    padding: 0 1.6vw;
		    position: relative;
		}
		.quickBar-comp .quickBar-item .quickBar-item-content img {
		    height: 4.26667vw;
		    margin-right: .53333vw;
		    width: 4.26667vw;
		}
		img, video {
		    height: auto;
		    max-width: 100%;
		}
		.quickBar-comp .quickBar-item .quickBar-item-content span {
		    color: #333;
		    font-size: 3.2vw;
		    font-weight: 600;
		    text-align: center;
		}
		.input-box-main {
		    align-items: center;
		    display: flex;
		    padding: 3.2vw 3.2vw calc(env(safe-area-inset-bottom)/2 + 3.2vw);
		}
		.input-box-main .input-box-textarea-wrapper {
		    align-items: center;
		    background: #f7f7f7;
		    border-radius: 3.2vw;
		    display: flex;
		    flex: 1;
		    flex-direction: row;
		    padding: 2.13333vw 3.2vw;
		    position: relative;
		}
		.input-box-main .input-box-textarea-wrapper .input-box-field {
		    background: transparent;
		    flex: 1;
		    padding: 0;
		}
		.van-field__value {
		    overflow: visible;
		}
		.van-cell__value {
		    color: var(--van-cell-value-color);
		    font-size: var(--van-cell-value-font-size);
		    overflow: hidden;
		    position: relative;
		    text-align: right;
		    vertical-align: middle;
		    word-wrap: break-word;
		}
		.van-field__body {
		    align-items: center;
		    display: flex;
		}
		.input-box-main .input-box-textarea-wrapper .input-box-field .van-field__control {
		    color: #333;
		    font-size: 3.73333vw;
		    height: 5.86667vw;
		    line-height: 5.86667vw;
		    min-height: 5.86667vw;
		    width: 100%;
		    outline: none;
		    border: none;
		}
		.van-field__control--min-height {
		    min-height: var(--van-field-text-area-min-height);
		}
		.van-field__control {
		    background-color: transparent;
		    border: 0;
		    box-sizing: border-box;
		    color: var(--van-field-input-text-color);
		    display: block;
		    line-height: inherit;
		    margin: 0;
		    min-width: 0;
		    padding: 0;
		    resize: none;
		}
		.input-box-main .input-box-textarea-wrapper .input-box-icons {
		    display: flex;
		    flex-shrink: 0;
		    gap: 3.2vw;
		    margin-left: 2.13333vw;
		}
		.input-box-main .input-box-btn {
		    flex-shrink: 0;
		    height: 8.53333vw;
		    width: 8.53333vw;
		}
		.input-box-tools {
		    display: none;
		    background: #fff;
		    border-top: 1px solid #f0f0f0;
		    padding: 3.2vw;
		}
		.input-box-tools .input-box-others {
		    display: flex;
		}
		.input-box-tools .input-box-others .others-item {
		    display: flex;
		    flex-direction: column;
		    align-items: center;
		    gap: 1.6vw;
		}
		.input-box-tools .input-box-others .others-item .tool-img {
		    width: 16vw;
		    height: 16vw;
		    display: flex;
		    align-items: center;
		    justify-content: center;
		    background: #f7f7f7;
		    border-radius: 2.13333vw;
		}
		.input-box-tools .input-box-others .others-item .tool-name {
		    font-size: 2.93333vw;
		    color: #666;
		}
		.input-box-tools .van-uploader__input {
		    display: none;
		}
		.hide-scrollbar {
		    -ms-overflow-style: none;
		    overflow-x: auto;
		    overflow-y: hidden;
		    scrollbar-width: none;
		}
		.hide-scrollbar::-webkit-scrollbar {
		    display: none;
		}
	</style>
</head>
<body>
	<div class="xile-loading-container" id="loadingContainer"> 
        <div class="xile-loading-spinner"></div> 
        <div class="xile-loading-text">正在连接群聊...</div> 
    </div>
	<div id="cgtips" class="cgtips">
		<p id="cgtipstext">成功</p>
		<img src="/assets/img/pzcg.svg" alt="">
	</div>
	<div class="wrapper">
		<div class="main-container">
			<div class="imDetail-page">
				<header class="header">
					<div class="header-inner">
						<span class="iconify back-icon" data-icon="mdi:chevron-left"></span>
						<div class="title-container">
							<h1 class="title"><?php echo htmlspecialchars($product_page['XEpzds_product_code']); ?>-<?php echo htmlspecialchars($formatted_amount); ?>-<?php echo htmlspecialchars($compensation_text); ?></h1>
							<div class="status-container">
								<span class="status-time">09:30-00:30</span>
							</div>
						</div>
					</div>
					<!-- 原 top-bar 内容 -->
					<div class="process-bar">
						<div class="left">
							<div class="node-list-container">
								<div class="node-list">
									<div class="node-item">
										<img src="/assets/img/Frame000006791-20250730%402x.png" alt="" class="status-icon">
										<p>资料提交</p>
									</div>
									<div class="node-item">
										<img src="/assets/img/icon-arrow%402x-20250731.png" alt="" class="arrow-icon">
										<img src="/assets/img/Frame000006791-20250730%402x.png" alt="" class="status-icon">
										<p>买家验号</p>
									</div>
									<div class="node-item">
										<img src="/assets/img/icon-arrow%402x-20250731.png" alt="" class="arrow-icon">
										<img src="/assets/img/Frame000006791-20250730%402x.png" alt="" class="status-icon">
										<p>帐号换绑</p>
									</div>
									<div class="node-item">
										<img src="/assets/img/icon-arrow%402x-20250731.png" alt="" class="arrow-icon">
										<img src="/assets/img/Frame000006791-20250730%402x.png" alt="" class="status-icon">
										<p>交易完成</p>
									</div>
								</div>
							</div>
						</div>
					</div>
				</header>
				<!-- Chat Area -->
				<main class="chat-area" id="chat-container">
					<div class="chat-messages">
						<!-- Timestamp -->
						<div class="timestamp">
							<span class="timestamp-text">交易开始</span>
						</div>
					</div>
					<div class="agent-message-container">
                            <div class="agent-message-inner">
                                <img class="agent-avatar" src="/assets/img/pz-jy.jpeg" alt="Agent avatar">
                                <div class="agent-message-content">
                                    <p class="agent-name">
                                        盼之交易管家-咚呛
                                        <span class="agent-badge">官方</span>
                                    </p>
                                    <div class="agent-message-bubble">
                                        <p class="agent-message-text">买家已支付完成</p>
                                        <div class="desc"><p><span style="color: #8c8c8c;">老板请稍等，交易流程将在卖家确认后开始。</span></p><p><span style="color: #e13c39;"><u><strong>！温馨提示：</strong></u></span><span style="color: #8c8c8c;"><u>近期受厂商风控机制影响，部分账号在换绑过程中或换绑完成后，可能出现新设备操作受限的场景，</u></span><span style="color: #e13c39;"><u><strong>通常在1-3天后可解除。</strong></u></span></p><p></p></div>
                                    </div>
                                </div>
                            </div>
                        </div>
					<div class="agent-message-container">
						<div class="agent-message-inner">
							<img class="agent-avatar" src="/assets/img/pz-xzl.png" alt="Agent avatar">
							<div class="agent-message-content">
								<p class="agent-name"> 盼宝小助理 <span class="agent-badge">官方</span>
								</p>
								<div class="agent-message-bubble-XE">
									<div class="xekefu-message-card">
										<div class="product-header">
											<!-- 显示自定义图片的商品图标 -->
											<div class="product-icon">
												<img src="/assets/img/panzhi.png" alt="XELOGO" class="product-icon-img">
											</div>
											<p class="product-code">商品编号: <?php echo htmlspecialchars($product_page['XEpzds_product_code']); ?></p>
										</div>
										<div class="product-info">
										 
											<img class="product-image" 
											src="<?php echo htmlspecialchars($product_page['XEpzds_product_image']); ?>" 
											alt="Game inventory">
									
											<div class="product-details">
												<p class="product-title"><?php echo htmlspecialchars($product_page['XEpzds_product_code']); ?> 号 
												【<?php echo htmlspecialchars($product_page['XEpzds_product_name']); ?>】
												</p>
												<p class="product-price">¥ <?php echo htmlspecialchars($formatted_amount); ?></p>
											</div>
										</div>
										<button data-text="确认交易" class="van-button van-button--primary van-button--large van-button--block">
											<div class="van-button__content">
												<span class="van-button__text"> 确认交易 </span>
											</div>
										</button>
									</div>
								</div>
							</div>
						</div>
					</div>
				</main>
            
            <div class="input-box"><!----><!---->
        <div class="quickBar-comp hide-scrollbar">
            <div class="quickBar-list"><!---->
                <div class="quickBar-item evaluateService" onclick="showAppraise()">
                    <div class="quickBar-item-content"><img src="/assets/img/wgticon-evaluate.png" alt=""><span>评价客服</span></div>
                </div>
                <?php if ($hasParams): ?>
                <div class="quickBar-item consultGoods" data-track-click="{&quot;eventName&quot;:&quot;咨询-咨询商品&quot;}">
                    <div class="quickBar-item-content"><img src="/assets/img/wgticon-prod.png" alt=""><span>咨询商品</span></div><!----><!---->
                </div>
                <div class="quickBar-item myOrder" data-track-click="{&quot;eventName&quot;:&quot;咨询-我的订单&quot;}">
                    <div class="quickBar-item-content"><img src="/assets/img/wgticon-order.png" alt=""><span>我的订单</span></div><!----><!---->
                </div>
                <?php endif; ?>
                <div class="quickBar-item">
                    <div class="quickBar-item-content" onclick="window.location.href='https://m7.pzds.com/center/complaint/service';"><img src="/assets/img/wgticon-complaint.png" alt=""><span>投诉服务</span></div>
                </div>
                <div class="quickBar-item">
                    <div class="quickBar-item-content" onclick="window.location.href='https://m7.pzds.com/center/complaint/feedback';"><img src="/assets/img/wgticon-feedback%403x.png" alt=""><span>问题反馈</span></div>
                </div><!----><!----><!----><!---->
                <div class="quickBar-item">
                    <div class="quickBar-item-content"><img src="/assets/img/wgticon-%40%403x.png" alt=""><span>催客服</span></div>
                </div><!----><!----><!----><!----><!----><!---->
            </div><!----><!----><!----><!---->
        </div>
        <div  class="input-box-main">
            <div  class="input-box-textarea-wrapper">
                <div  class="van-cell van-cell--borderless van-field input-box-field"><!----><!---->
                    <div class="van-cell__value van-field__value">
                        <div class="van-field__body"><textarea id="van-field-22-input" rows="1" class="van-field__control van-field__control--min-height" placeholder="在这儿输入您的问题试试～" data-allow-mismatch="attribute"></textarea><!----><!----><!----></div><!----><!---->
                    </div><!----><!---->
                </div>
                <div  class="input-box-icons">
                    <img  class="input-box-btn" src="/assets/img/wgticon-chat_add.png">
                    <img  class="input-box-btn"
                        src="/assets/img/wgticon-chat_send.png" style="display: none;"></div>
            </div>
        </div>
        <div  class="input-box-tools">
            <div  class="input-box-others">
                <div  class="others-item">
                    <div  class="tool-img" id="album-btn">
                        <div  class="van-uploader">
                            <div class="van-uploader__wrapper">
                                <div class="van-uploader__input-wrapper"><img  src="/assets/img/wgtFrame.png"><input type="file" id="album-input" class="van-uploader__input" accept="image/*" multiple=""></div>
                            </div>
                        </div>
                    </div>
                    <div  class="tool-name"> 相册 </div>
                </div>
            </div>
        </div>
    </div>
	<!-- 工具栏遮罩层，点击关闭工具栏 -->
	<div id="toolbar-overlay" class="toolbar-overlay" onclick="hideToolbar()"></div>
	<!-- 评价弹窗遮罩层 -->
	<div id="appraise-overlay" class="appraise-overlay" onclick="closeAppraise()"></div>
	<!-- 评价弹窗 -->
	<div id="appraise" class="appraise">
		<button class="appraise-close-btn" onclick="closeAppraise()">×</button>
		<h2>您对本次服务满意吗？</h2>
		<div class="emoji">
			<button id="emoji-1" onclick="emoji1()">
				<img id="emoji1" src="/assets/img/pzds/1.png" alt="">
				<p>非常不满意</p>
			</button>
			<button id="emoji-2" type="button" onclick="emoji2()">
				<img id="emoji2" src="/assets/img/pzds/2.png" alt="">
				<p>不满意</p>
			</button>
			<button id="emoji-3" type="button" onclick="emoji3()">
				<img id="emoji3" src="/assets/img/pzds/3.png" alt="">
				<p>一般</p>
			</button>
			<button id="emoji-4" type="button" onclick="emoji4()">
				<img id="emoji4" src="/assets/img/pzds/4.png" alt="">
				<p>满意</p>
			</button>
			<button id="emoji-5" type="button" onclick="emoji5()">
				<img id="emoji5" src="/assets/img/pzds/5.png" alt="">
				<p>非常满意</p>
			</button>
		</div>
		<button onclick="submitAppraise()" class="sbu">提交</button>
	</div>
	<!-- 浮动菜单 -->
	<div id="mini_crad" class="mini-crad">
		<a href="https://m7.pzds.com/center/complaint/feedback">投诉服务</a>
		<a href="https://m7.pzds.com/center/complaint/feedback">问题反馈</a>
	</div>
	<div id="mini_crad2" class="mini-crad2">
		<a href="/PzdsJylc">交易流程</a>
	</div>
	<!-- 图片预览模态框 -->
	<div id="image-preview-modal" class="image-preview-modal">
		<img id="image-preview-content" src="" alt="预览图片" class="image-preview-content">
	</div>
    
    
    <script>
class CustomerChatSystem {
    constructor() {
        this.sessionId = this.getSessionId();
        this.customerName = this.getCustomerName();
        this.agentAccount = this.getAgentAccount();
        this.lastMessageId = 0;
        this.pollingInterval = null;
        this.apiBaseUrl = '/api/chat/messages';
        this.isOnline = true;
        this.statusPollingInterval = null;
        this.isSending = false;
        this.isUploadingImage = false;
        this.platform = '盼之群聊';
        
        // 设备信息检测
        this.deviceInfo = this.detectDevice();
        this.pageVisible = true;
        this.lastActivityTime = Date.now();
        this.inactivityTimeout = null;
        
        // WebSocket 相关属性
        this.ws = null;
        this.wsConnected = false;
        this.wsConnectionStatus = 'disconnected';
        this.wsReconnectAttempts = 0;
        this.maxWsReconnectAttempts = 5;
        this.wsReconnectDelay = 3000;
        this.wsHeartbeatInterval = null;
        this.wsMessageQueue = [];
        this.preferWebSocket = true;
        this.wsAuthSent = false;
        
        // 消息去重相关
        this.recentlySentMessageIds = new Set();
        this.recentlyReceivedWsMessageIds = new Set();
        this._lastSentMessages = [];
        this._sentMessageCounter = 0;

        // 假人设置初始化
        this.dummySettings = {
            dummy_name: '技术顾问',
            dummy_avatar: '/assets/img/dummy1.png',
            is_dummy_mode: false
        };
        this.lastDummyUpdate = 0;
        this.lastDummyCheckTime = 0;
        this.dummyPollingInterval = null;
        
        console.log('盼之聊天系统初始化:', {
            sessionId: this.sessionId,
            customerName: this.customerName,
            agentAccount: this.agentAccount
        });
        
        console.log('检测到设备信息:', this.deviceInfo);
        
        this.init();
    }
    
    init() {
        this.loadInitialMessages();
        this.setupEventListeners();
        this.startPolling();
        this.startStatusPolling();
        this.updateSendButton();
        this.setupImagePreview();
        this.checkDummySettings();
        
        // 初始化 WebSocket
        setTimeout(() => {
            this.initWebSocket();
        }, 1000);
    }
    
    // 检测是否为卡片消息
    isCardMessage(content) {
        if (!content || typeof content !== 'string') return false;
        
        if (content.startsWith('XECARD#') && content.length > 7) {
            try {
                const cardJson = content.substring(7);
                const cardData = JSON.parse(cardJson);
                if (cardData.type === 'custom_card' && cardData.title && cardData.content) {
                    return { type: 'custom', data: cardData };
                }
            } catch (e) {
                console.error('解析卡片数据失败:', e);
            }
        }
        
        // 检测交易卡片格式 XEXXCARD#JSON
        if (content.startsWith('XEXXCARD#') && content.length > 9) {
            try {
                const tradeJson = content.substring(9);
                const tradeData = JSON.parse(tradeJson);
                console.log('检测到交易卡片:', tradeData);
                if (tradeData.type === 'trade_card') {
                    return { type: 'trade', data: tradeData };
                }
            } catch (e) {
                console.error('解析交易卡片数据失败:', e);
            }
        }
        
        // 检测付款卡片格式 XEPAYCARD#JSON
        if (content.startsWith('XEPAYCARD#') && content.length > 10) {
            try {
                const payJson = content.substring(10);
                const payData = JSON.parse(payJson);
                console.log('检测到付款卡片:', payData);
                if (payData.type === 'pay_card') {
                    return { type: 'pay', data: payData };
                }
            } catch (e) {
                console.error('解析付款卡片数据失败:', e);
            }
        }

        return false;
    }

    // 生成卡片HTML
    generateCardHtml(cardData, isUser = false) {
        // 如果是交易卡片，使用交易卡片渲染
        if (cardData.type === 'trade_card') {
            return this.renderTradeCard(cardData);
        }
        
        // 如果是付款卡片，使用付款卡片渲染
        if (cardData.type === 'pay_card') {
            return this.renderPayCard(cardData);
        }
        
        // 自定义卡片渲染
        let html = `
            <div class="message-card">
                <div class="message-card__header">
                    <span class="message-card__title">${this.escapeHtml(cardData.title)}</span>
                </div>
                <div class="message-card__content">
                    ${this.escapeHtml(cardData.content)}
                </div>
        `;
        
        if (cardData.link && cardData.buttonText) {
            html += `
                <div class="message-card__actions">
                    <a href="${this.escapeHtml(cardData.link)}" target="_blank" class="message-card__button">
                        ${this.escapeHtml(cardData.buttonText)}
                    </a>
                </div>
            `;
        }
        
        html += `
            </div>
        `;
        
        return html;
    }
    
    // 渲染交易卡片
    renderTradeCard(cardData) {
        const imageUrl = cardData.image_url || '';
        const price = cardData.price ? (cardData.price.startsWith('¥') ? cardData.price : '¥' + cardData.price) : '';
        
        return `
            <div class="tradecard">
                <div class="tradecard-title">${this.escapeHtml(cardData.title || '交易信息')}</div>
                <div class="tradecard-top">
                    <div class="tradecard-tags">
                        <span class="tradecard-tag game">${this.escapeHtml(cardData.main_title || '')}</span>
                        <span class="tradecard-tag type">${this.escapeHtml(cardData.subtitle || '')}</span>
                    </div>
                    <div class="tradecard-status">${this.escapeHtml(cardData.trade_status || '')}</div>
                </div>
                <div class="tradecard-goods">
                    ${imageUrl ? `<img src="${this.escapeHtml(imageUrl)}" class="tradecard-img" alt="商品图">` : ''}
                    <div class="tradecard-main">
                        <div class="tradecard-name">${this.escapeHtml(cardData.description || '')}</div>
                        <div class="tradecard-price">${price}</div>
                        <div class="tradecard-tip">${this.escapeHtml(cardData.note || '')}</div>
                    </div>
                </div>
                <div class="tradecard-info">
                    <div class="tradecard-item"><span class="tradecard-label">订单编号：</span><span class="tradecard-value">${this.escapeHtml(cardData.order_no || '')}</span></div>
                    <div class="tradecard-item"><span class="tradecard-label">商品编号：</span><span class="tradecard-value">${this.escapeHtml(cardData.goods_no || '')}</span></div>
                    <div class="tradecard-item"><span class="tradecard-label">创建时间：</span><span class="tradecard-value">${this.escapeHtml(cardData.create_time || '')}</span></div>
                    <div class="tradecard-item"><span class="tradecard-label">合同状态：</span><span class="tradecard-value">${this.escapeHtml(cardData.contract_status || '')}</span></div>
                </div>
            </div>
        `;
    }
    
    // 渲染付款卡片
    renderPayCard(cardData) {
        const amount = cardData.amount ? (cardData.amount.startsWith('¥') ? cardData.amount : '¥' + cardData.amount) : '';
        
        return `
            <div class="paycard">
                <div class="paycard-title">订单已支付</div>
                <div class="paycard-line paycard-fit"><span class="paycard-label">订单编号：</span><span class="paycard-value">${this.escapeHtml(cardData.order_no || '')}</span></div>
                <div class="paycard-line paycard-fit"><span class="paycard-label">商品编号：</span><span class="paycard-value">${this.escapeHtml(cardData.goods_no || '')}</span></div>
                <div class="paycard-line paycard-fit amount-line"><span class="paycard-label">支付金额：</span><span class="paycard-value amount">${amount}</span></div>
            </div>
        `;
    }

    detectDevice() {
        const ua = navigator.userAgent;
        let device = {
            type: 'desktop',
            os: 'unknown',
            browser: 'unknown',
            platform: navigator.platform,
            userAgent: ua
        };
        
        if (/Android/.test(ua)) {
            device.os = 'Android';
            device.type = 'mobile';
        } else if (/iPhone|iPad|iPod/.test(ua)) {
            device.os = 'iOS';
            device.type = /iPad/.test(ua) ? 'tablet' : 'mobile';
        } else if (/Windows/.test(ua)) {
            device.os = 'Windows';
        } else if (/Mac OS X/.test(ua)) {
            device.os = 'macOS';
        } else if (/Linux/.test(ua)) {
            device.os = 'Linux';
        }
        
        if (/Chrome\//.test(ua) && !/Edg\//.test(ua)) {
            device.browser = 'Chrome';
        } else if (/Firefox\//.test(ua)) {
            device.browser = 'Firefox';
        } else if (/Safari\//.test(ua) && !/Chrome\//.test(ua)) {
            device.browser = 'Safari';
        } else if (/Edg\//.test(ua)) {
            device.browser = 'Edge';
        } else if (/MSIE|Trident/.test(ua)) {
            device.browser = 'IE';
        }
        
        return device;
    }
    
    // 检查假人设置
    checkDummySettings() {
        const self = this;
        
        $.ajax({
            url: this.apiBaseUrl,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                action: 'get_dummy_settings',
                session_id: this.sessionId
            }),
            success: function(data) {
                if (data && data.success && data.dummy_settings) {
                    const newSettings = data.dummy_settings;
                    
                    console.log('收到假人设置响应:', newSettings);
                    
                    if (!newSettings.dummy_name || !newSettings.dummy_avatar) {
                        console.log('假人设置数据不完整，使用默认值');
                        return;
                    }
                    
                    if (newSettings.dummy_avatar && !newSettings.dummy_avatar.startsWith('http') && !newSettings.dummy_avatar.startsWith('/')) {
                        newSettings.dummy_avatar = '/assets/img/' + newSettings.dummy_avatar;
                    }
                    
                    const settingsChanged = 
                        newSettings.dummy_name !== self.dummySettings.dummy_name ||
                        newSettings.dummy_avatar !== self.dummySettings.dummy_avatar ||
                        newSettings.is_dummy_mode !== self.dummySettings.is_dummy_mode;
                    
                    const newUpdateTime = newSettings.last_updated || 0;
                    
                    if (settingsChanged && newUpdateTime > self.lastDummyUpdate) {
                        console.log('检测到假人设置更新:', newSettings);
                        self.dummySettings = {
                            dummy_name: newSettings.dummy_name || '技术顾问',
                            dummy_avatar: newSettings.dummy_avatar || '/assets/img/dummy1.png',
                            is_dummy_mode: Boolean(newSettings.is_dummy_mode)
                        };
                        self.lastDummyUpdate = newUpdateTime;
                        
                        self.updateExistingDummyMessages();
                    }
                } else {
                    console.log('未获取到假人设置或请求失败:', data);
                }
            },
            error: function(xhr, status, error) {
                console.error('获取假人设置失败:', error);
            }
        });
    }
    
    // 更新现有假人消息的显示
    updateExistingDummyMessages() {
        const self = this;
        const dummyMessages = $('.dummy-message');
        
        if (dummyMessages.length === 0) {
            console.log('没有假人消息需要更新');
            return;
        }
        
        console.log(`找到 ${dummyMessages.length} 条假人消息需要更新`);
        
        dummyMessages.each(function(index) {
            const messageElement = $(this);
            const nameElement = messageElement.find('.agent-name');
            const avatarElement = messageElement.find('.agent-avatar');
            
            if (nameElement.length > 0) {
                nameElement.text(self.dummySettings.dummy_name);
            }
            
            if (avatarElement.length > 0) {
                const currentSrc = avatarElement.attr('src');
                const dummyAvatar = self.dummySettings.dummy_avatar || '/assets/img/dummy1.png';
                const newSrc = dummyAvatar.startsWith('http') || dummyAvatar.startsWith('/') 
                    ? dummyAvatar 
                    : '/assets/img/' + dummyAvatar;
                
                if (currentSrc !== newSrc) {
                    const img = new Image();
                    img.onload = function() {
                        avatarElement.attr('src', newSrc);
                        avatarElement.attr('alt', self.dummySettings.dummy_name);
                        console.log(`消息 ${index+1} 头像更新成功`);
                    };
                    img.onerror = function() {
                        console.warn(`头像加载失败: ${newSrc}，使用默认头像`);
                        avatarElement.attr('src', '/assets/img/dummy1.png');
                        avatarElement.attr('alt', self.dummySettings.dummy_name);
                    };
                    img.src = newSrc;
                }
            }
        });
        
        console.log('假人消息更新完成');
    }
    
    // 催客服自动发送消息
    sendUrgentMessage() {
        const urgentMessage = '@盼之交易管家-咚呛                             我有问题急需处理';
        const messageInput = $('#van-field-22-input');
        
        messageInput.val(urgentMessage);
        this.updateSendButton();
        this.scrollToBottom();
        messageInput.focus();
        
        // 延迟发送，让用户看到消息
        setTimeout(() => {
            this.sendMessage();
        }, 300);
    }
    
    setupEventListeners() {
        const self = this;
        const messageInput = $('#van-field-22-input');
        const sendButton = $('.input-box-btn').filter(function() {
            return $(this).attr('src').includes('chat_send');
        });
        const addButton = $('.input-box-btn').filter(function() {
            return $(this).attr('src').includes('chat_add');
        });
        const fileInput = $('.van-uploader__input');
        
        messageInput.on('keypress', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                self.sendMessage();
            }
        });
        
        $('.van-button__content').on('click', function() {
            const text = $(this).text().trim();
            
            if (text === '确认交易') {
                self.sendDirectMessage(text);
            } else {
                messageInput.val(text);
                self.updateSendButton();
                messageInput.focus();
            }
        });
        
        messageInput.on('input', function() {
            self.updateSendButton();
        });
        
        sendButton.on('click', function() {
            self.sendMessage();
        });
        
        addButton.on('click', function() {
            const $toolbox = $('.input-box-tools');
            const $icon = $(this);
            
            $toolbox.toggle();
            
            if ($toolbox.is(':visible')) {
                // 展开时切换图标
                $icon.attr('src', '/assets/img/wgticon-chat_close.png');
            } else {
                // 收起时恢复图标
                $icon.attr('src', '/assets/img/wgticon-chat_add.png');
            }
        });
        
        // 催客服按钮点击 - 自动发送消息
        $('.quickBar-item span').filter(function() {
            return $(this).text() === '催客服';
        }).closest('.quickBar-item').on('click', function() {
            self.sendUrgentMessage();
        });
        
        $(document).on('visibilitychange', function() {
            if (!document.hidden) {
                self.checkNewMessages();
                self.updateCustomerOnlineStatus();
                self.checkDummySettings();
            }
        });
        
        $(window).on('beforeunload', function() {
            self.setCustomerOffline();
        });
    }
    
    updateSendButton() {
        const messageInput = $('#van-field-22-input');
        const sendButton = $('.input-box-btn').filter(function() {
            return $(this).attr('src').includes('chat_send');
        });
        const inputVal = messageInput.val();
        const hasText = inputVal && inputVal.trim().length > 0;
        const isSending = this.isSending || this.isUploadingImage;
        
        if (hasText && !isSending) {
            sendButton.show();
        } else {
            sendButton.hide();
        }
    }
    
    setupImagePreview() {
        const self = this;
        
        $(document).on('click', '.message-image', function() {
            const imageUrl = $(this).attr('src');
            self.previewImage(imageUrl);
        });
        
        $('#image-preview-modal').on('click', function(e) {
            if (e.target === this) {
                self.closeImagePreview();
            }
        });
        
        $(document).on('keyup', function(e) {
            if (e.key === 'Escape') {
                self.closeImagePreview();
            }
        });
    }
    
    previewImage(imageUrl) {
        $('#image-preview-content').attr('src', imageUrl);
        $('#image-preview-modal').addClass('active');
        $('body').css('overflow', 'hidden');
    }
    
    closeImagePreview() {
        $('#image-preview-modal').removeClass('active');
        $('#image-preview-content').attr('src', '');
        $('body').css('overflow', '');
    }
    
    sendDirectMessage(content) {
        if (this.isSending) {
            return;
        }

        this.isSending = true;
        this.updateSendButton();

        const self = this;

        console.log('直接发送消息:', content);

        this.appendMessages([{
            id: Date.now(),
            agent_account: this.agentAccount,
            speaker_type: 1,
            content: content,
            customer_name: this.customerName,
            remark: '',
            created_at: new Date().toISOString()
        }]);

        this.scrollToBottom();
        this.updateCustomerOnlineStatus();

        $.ajax({
            url: this.apiBaseUrl,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                action: 'send_message',
                session_id: this.sessionId,
                agent_account: this.agentAccount,
                speaker_type: 1,
                content: content,
                customer_name: this.customerName,
                platform: this.platform
            }),
            success: function(data) {
                console.log('直接发送响应:', data);
                self.isSending = false;
                self.updateSendButton();
                
                if (data.success && data.message_id) {
                    self.lastMessageId = Math.max(self.lastMessageId, data.message_id);
                } else {
                    console.error('直接发送失败:', data.message);
                }
            },
            error: function(xhr, status, error) {
                console.error('直接发送消息失败:', error);
                self.isSending = false;
                self.updateSendButton();
            }
        });
    }
    
    sendMessage() {
        if (this.isSending) {
            return;
        }
        
        const input = $('#van-field-22-input');
        const content = input.val().trim();
        
        if (!content) return;
        
        this.isSending = true;
        this.updateSendButton();
        
        const self = this;
        
        console.log('发送消息:', content);
        
        this._sentMessageCounter++;
        const tempId = 'temp_' + Date.now() + '_' + this._sentMessageCounter;

        this.appendMessages([{
            id: tempId,
            agent_account: this.agentAccount,
            speaker_type: 1,
            content: content,
            customer_name: this.customerName,
            remark: '',
            created_at: new Date().toISOString()
        }]);
        
        // 记录已发送消息，用于去重
        this._lastSentMessages.push({
            tempId: tempId,
            content: content,
            speaker_type: 1,
            timestamp: Date.now()
        });
        if (this._lastSentMessages.length > 20) {
            this._lastSentMessages.shift();
        }
        
        input.val('');
        this.updateSendButton();
        this.scrollToBottom();
        
        this.updateCustomerOnlineStatus();
        
        // WebSocket 消息数据
        const wsMessageData = {
            type: 'send_message',
            session_key: this.sessionId,
            agent_account: this.agentAccount,
            speaker_type: 1,
            content: content,
            customer_name: this.customerName,
            platform: this.platform,
            user_type: 'customer',
            user_id: this.customerName,
            created_at: new Date().toISOString()
        };
        
        // API 消息数据
        const apiMessageData = {
            action: 'send_message',
            session_id: this.sessionId,
            agent_account: this.agentAccount,
            speaker_type: 1,
            content: content,
            customer_name: this.customerName,
            platform: this.platform
        };
        
        // 通过 WebSocket 发送
        if (this.wsConnected && this.ws && this.ws.readyState === WebSocket.OPEN) {
            console.log('尝试通过 WebSocket 发送(实时推送)');
            this.sendMessageToWebSocket(wsMessageData);
        } else {
            console.log('WebSocket 未连接，跳过 WebSocket 发送');
        }
        
        // 通过 API 保存到数据库
        $.ajax({
            url: this.apiBaseUrl,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(apiMessageData),
            success: function(data) {
                console.log('✅ API 保存响应:', data);
                self.isSending = false;
                self.updateSendButton();
                if (data.success && data.message_id) {
                    self.lastMessageId = Math.max(self.lastMessageId, data.message_id);
                    
                    self.recentlySentMessageIds.add(data.message_id);
                    setTimeout(() => {
                        self.recentlySentMessageIds.delete(data.message_id);
                    }, 5000);
                    
                    console.log('✅ 消息已保存到数据库，ID:', data.message_id);
                } else {
                    console.error('❌ 保存失败:', data.message);
                }
            },
            error: function(xhr, status, error) {
                console.error('❌ 保存消息到数据库失败:', error, xhr.responseText);
                self.isSending = false;
                self.updateSendButton();
            }
        });
    }
    
    appendMessages(messages) {
        const container = $('#chat-container');
        
        messages.forEach(message => {
            if (message.id && $(`[data-message-id="${message.id}"]`).length > 0) {
                console.log('消息已存在,跳过添加:', message.id);
                return;
            }
            
            let messageHtml;
            
            // 假人消息的特殊处理 (speaker_type === 3)
            if (message.speaker_type === 3) {
                const dummyName = message.dummy_name || this.dummySettings.dummy_name || '技术顾问';
                const dummyAvatar = message.dummy_avatar || this.dummySettings.dummy_avatar || '/assets/img/dummy1.png';
                
                const avatarSrc = dummyAvatar.startsWith('http') || dummyAvatar.startsWith('/') 
                    ? dummyAvatar 
                    : '/assets/img/' + dummyAvatar;
                
                if (message.message_type === 'image' && (message.image_url || message.image_path)) {
                    const imageUrl = message.image_url || ('/uploads/' + message.image_path);
                    messageHtml = `
                        <div class="agent-message-container dummy-message" data-message-id="${message.id}">
                            <div class="agent-message-inner">
                                <img class="agent-avatar" src="${avatarSrc}" alt="${dummyName}" onerror="this.src='/assets/img/dummy1.png'">
                                <div class="agent-message-content">
                                    <p class="agent-name">${dummyName}</p>
                                    <div class="agent-message-bubble">
                                        <img class="message-image" src="${imageUrl}" alt="图片">
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                } else {
                    const cardInfo = this.isCardMessage(message.content);
                    if (cardInfo) {
                        const cardHtml = this.generateCardHtml(cardInfo.data, false);
                        messageHtml = `
                            <div class="agent-message-container dummy-message" data-message-id="${message.id}">
                                <div class="agent-message-inner">
                                    <img class="agent-avatar" src="${avatarSrc}" alt="${dummyName}" onerror="this.src='/assets/img/dummy1.png'">
                                    <div class="agent-message-content">
                                        <p class="agent-name">${dummyName}</p>
                                       
                                            ${cardHtml}
                                       
                                    </div>
                                </div>
                            </div>
                        `;
                    } else {
                        messageHtml = `
                            <div class="agent-message-container dummy-message" data-message-id="${message.id}">
                                <div class="agent-message-inner">
                                    <img class="agent-avatar" src="${avatarSrc}" alt="${dummyName}" onerror="this.src='/assets/img/dummy1.png'">
                                    <div class="agent-message-content">
                                        <p class="agent-name">${dummyName}</p>
                                        <div class="agent-message-bubble">
                                            <p class="agent-message-text">${this.escapeHtml(message.content)}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                    }
                }
            }
            // 普通用户消息 (speaker_type === 1)
            else if (message.speaker_type === 1) {
                if (message.message_type === 'image' && (message.image_url || message.image_path)) {
                    const imageUrl = message.image_url || ('/uploads/' + message.image_path);
                    messageHtml = `
                        <div class="simple-user-message-container" data-message-id="${message.id}">
                            <div class="simple-user-message-inner">
                                <div class="simple-user-message-bubble">
                                    <img class="message-image" src="${imageUrl}" alt="图片">
                                </div>
                                <img class="user-avatar" src="<?php echo htmlspecialchars($product_page['XEpzds_seller_avatar']); ?>" alt="User avatar">
                            </div>
                        </div>
                    `;
                } else {
                    const cardInfo = this.isCardMessage(message.content);
                    if (cardInfo) {
                        const cardHtml = this.generateCardHtml(cardInfo.data, true);
                        messageHtml = `
                            <div class="simple-user-message-container" data-message-id="${message.id}">
                                <div class="simple-user-message-inner">
                                   
                                        ${cardHtml}
                                 
                                    <img class="user-avatar" src="<?php echo htmlspecialchars($product_page['XEpzds_seller_avatar']); ?>" alt="User avatar">
                                </div>
                            </div>
                        `;
                    } else {
                        messageHtml = `
                            <div class="simple-user-message-container" data-message-id="${message.id}">
                                <div class="simple-user-message-inner">
                                    <div class="simple-user-message-bubble">
                                        <p class="simple-user-message-text">${this.escapeHtml(message.content)}</p>
                                    </div>
                                    <img class="user-avatar" src="<?php echo htmlspecialchars($product_page['XEpzds_seller_avatar']); ?>" alt="User avatar">
                                </div>
                            </div>
                        `;
                    }
                }
            }
            // 普通客服消息 (speaker_type === 2)
            else {
                const isDummyMessage = message.dummy_name || message.dummy_avatar;
                
                if (isDummyMessage) {
                    const dummyName = message.dummy_name || this.dummySettings.dummy_name || '技术顾问';
                    let dummyAvatar = message.dummy_avatar || this.dummySettings.dummy_avatar || '/assets/img/dummy1.png';
                    
                    if (dummyAvatar && !dummyAvatar.startsWith('http') && !dummyAvatar.startsWith('/')) {
                        dummyAvatar = '/assets/img/' + dummyAvatar;
                    }
                    
                    if (message.message_type === 'image' && (message.image_url || message.image_path)) {
                        const imageUrl = message.image_url || ('/uploads/' + message.image_path);
                        messageHtml = `
                            <div class="agent-message-container dummy-message" data-message-id="${message.id}">
                                <div class="agent-message-inner">
                                    <img class="agent-avatar" src="${dummyAvatar}" alt="${dummyName}" onerror="this.src='/assets/img/dummy1.png'">
                                    <div class="agent-message-content">
                                        <p class="agent-name">${dummyName}</p>
                                        <div class="agent-message-bubble">
                                            <img class="message-image" src="${imageUrl}" alt="图片">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                    } else {
                        const cardInfo = this.isCardMessage(message.content);
                        if (cardInfo) {
                            const cardHtml = this.generateCardHtml(cardInfo.data, false);
                            messageHtml = `
                                <div class="agent-message-container dummy-message" data-message-id="${message.id}">
                                    <div class="agent-message-inner">
                                        <img class="agent-avatar" src="${dummyAvatar}" alt="${dummyName}" onerror="this.src='/assets/img/dummy1.png'">
                                        <div class="agent-message-content">
                                            <p class="agent-name">${dummyName}</p>
                                            
                                                ${cardHtml}
                                            
                                        </div>
                                    </div>
                                </div>
                            `;
                        } else {
                            messageHtml = `
                                <div class="agent-message-container dummy-message" data-message-id="${message.id}">
                                    <div class="agent-message-inner">
                                        <img class="agent-avatar" src="${dummyAvatar}" alt="${dummyName}" onerror="this.src='/assets/img/dummy1.png'">
                                        <div class="agent-message-content">
                                            <p class="agent-name">${dummyName}</p>
                                            <div class="agent-message-bubble">
                                                <p class="agent-message-text">${this.escapeHtml(message.content)}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            `;
                        }
                    }
                } else {
                    if (message.message_type === 'image' && (message.image_url || message.image_path)) {
                        const imageUrl = message.image_url || ('/uploads/' + message.image_path);
                        messageHtml = `
                            <div class="agent-message-container" data-message-id="${message.id}">
                                <div class="agent-message-inner">
                                    <img class="agent-avatar" src="/assets/img/pz-jy.jpeg" alt="Agent avatar">
                                    <div class="agent-message-content">
                                        <p class="agent-name">
                                            盼之交易管家-咚呛
                                            <span class="agent-badge">官方</span>
                                        </p>
                                        <div class="agent-message-bubble">
                                            <img class="message-image" src="${imageUrl}" alt="图片">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                    } else {
                        const cardInfo = this.isCardMessage(message.content);
                        if (cardInfo) {
                            const cardHtml = this.generateCardHtml(cardInfo.data, false);
                            messageHtml = `
                                <div class="agent-message-container" data-message-id="${message.id}">
                                    <div class="agent-message-inner">
                                        <img class="agent-avatar" src="/assets/img/pz-jy.jpeg" alt="Agent avatar">
                                        <div class="agent-message-content">
                                            <p class="agent-name">
                                                盼之交易管家-咚呛
                                                <span class="agent-badge">官方</span>
                                            </p>
                                          
                                                ${cardHtml}
                                            
                                        </div>
                                    </div>
                                </div>
                            `;
                        } else {
                            messageHtml = `
                                <div class="agent-message-container" data-message-id="${message.id}">
                                    <div class="agent-message-inner">
                                        <img class="agent-avatar" src="/assets/img/pz-jy.jpeg" alt="Agent avatar">
                                        <div class="agent-message-content">
                                            <p class="agent-name">
                                                盼之交易管家-咚呛
                                                <span class="agent-badge">官方</span>
                                            </p>
                                            <div class="agent-message-bubble">
                                                <p class="agent-message-text">${this.escapeHtml(message.content)}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            `;
                        }
                    }
                }
            }
            
            container.append(messageHtml);
        });
    }
    
    getSessionId() {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get('id') || 'aaaccazzz-ptestadmins';
    }
    
    getCustomerName() {
        const sessionId = this.getSessionId();
        if (sessionId.includes('-')) {
            const parts = sessionId.split('-');
            if (parts.length >= 2) {
                const customerPart = parts[0];
                return customerPart.substring(1, customerPart.length - 1);
            }
        } else if (sessionId.includes('_')) {
            const parts = sessionId.split('_');
            if (parts.length >= 2) {
                return parts[0];
            }
        }
        return 'default';
    }
    
    getAgentAccount() {
        const sessionId = this.getSessionId();
        if (sessionId.includes('-')) {
            const parts = sessionId.split('-');
            if (parts.length >= 2) {
                const agentPart = parts[1];
                return agentPart.substring(1, agentPart.length - 1);
            }
        } else if (sessionId.includes('_')) {
            const parts = sessionId.split('_');
            if (parts.length >= 2) {
                return parts[1];
            }
        }
        return 'testadmin';
    }
    
    loadInitialMessages() {
        const self = this;
        
        console.log('加载初始消息，sessionId:', this.sessionId);
        
        $.get(`${this.apiBaseUrl}?action=get_messages&session_id=${encodeURIComponent(this.sessionId)}`)
            .done(function(data) {
                console.log('加载消息响应:', data);
                if (data.success && data.messages && data.messages.length > 0) {
                    self.appendMessages(data.messages);
                    self.lastMessageId = Math.max(...data.messages.map(msg => msg.id));
                    self.scrollToBottom();
                } else {
                    console.log('没有历史消息或加载失败');
                }
            })
            .fail(function(xhr, status, error) {
                console.error('加载初始消息失败:', error);
            });
    }
    
    checkNewMessages() {
        const self = this;
        
        // 检查假人设置更新 - 降低频率到每30秒检查一次
        const now = Date.now();
        if (now - (this.lastDummyCheckTime || 0) > 30000) {
            this.checkDummySettings();
            this.lastDummyCheckTime = now;
        }
        
        // 如果 WebSocket 连接正常，减少轮询频率
        if (this.wsConnected && this.ws && this.ws.readyState === WebSocket.OPEN) {
            clearInterval(this.pollingInterval);
            this.pollingInterval = setInterval(function() {
                self.performPolling();
            }, 5000);
        }
        
        this.performPolling();
    }
    
    performPolling() {
        const self = this;
        $.get(`${this.apiBaseUrl}?action=poll_messages&session_id=${encodeURIComponent(this.sessionId)}&last_id=${this.lastMessageId}`)
            .done(function(data) {
                if (data.success && data.messages && data.messages.length > 0) {
                    console.log('轮询收到新消息:', data.messages);
                    
                    const newMessages = data.messages.filter(msg => {
                        if ($(`[data-message-id="${msg.id}"]`).length > 0) {
                            console.log('轮询消息已存在 (DOM 中),跳过:', msg.id);
                            return false;
                        }
                        
                        // 去重：检查自己发送的消息
                        if (msg.speaker_type === 1) {
                            if (self._lastSentMessages && self._lastSentMessages.length > 0) {
                                const now = Date.now();
                                const isRecentlySent = self._lastSentMessages.some(sent => {
                                    if (sent.messageType === 'image' && msg.message_type === 'image') {
                                        return (now - sent.timestamp) < 5000;
                                    }
                                    if (sent.content === msg.content && (now - sent.timestamp) < 5000) {
                                        return true;
                                    }
                                    return false;
                                });
                                if (isRecentlySent) {
                                    console.log('跳过重复消息(自己发送):', msg.id, msg.content);
                                    return false;
                                }
                            }
                        }
                        
                        // 去重：检查是否在 WebSocket 接收的消息列表中
                        if (self.recentlyReceivedWsMessageIds && self.recentlyReceivedWsMessageIds.has(msg.id)) {
                            console.log('轮询消息已通过 WebSocket 接收，跳过:', msg.id);
                            return false;
                        }
                        
                        return true;
                    });
                    
                    // 清理过期的去重记录
                    const now = Date.now();
                    self._lastSentMessages = self._lastSentMessages.filter(sent => 
                        (now - sent.timestamp) < 10000
                    );
                    
                    if (newMessages.length > 0) {
                        console.log('轮询过滤后显示', newMessages.length, '条消息');
                        self.appendMessages(newMessages);
                        const allMessageIds = newMessages.map(msg => msg.id);
                        self.lastMessageId = Math.max(...allMessageIds);
                        self.scrollToBottom();
                        
                        const hasAgentMessage = newMessages.some(msg => msg.speaker_type === 2);
                        if (hasAgentMessage) {
                            self.playNotificationSound();
                        }
                    }
                }
            })
            .fail(function(xhr, status, error) {
                console.log('轮询错误:', status, error);
            });
    }
    
    startPolling() {
        const self = this;
        
        this.pollingInterval = setInterval(function() {
            self.checkNewMessages();
        }, 1000);
        
        // 假人设置轮询（独立，每30秒一次）
        this.dummyPollingInterval = setInterval(function() {
            self.checkDummySettings();
        }, 30000);
        
        console.log('消息轮询已启动');
        console.log('假人设置轮询已启动（30秒间隔）');
    }
    
    startStatusPolling() {
        const self = this;
        
        this.updateOnlineStatus();
        this.setupPageVisibilityListener();
        
        this.statusPollingInterval = setInterval(function() {
            self.updateOnlineStatus();
        }, 10000);
        
        console.log('客户在线状态轮询已启动（10秒间隔）');
    }
    
    setupPageVisibilityListener() {
        const self = this;
        
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                console.log('页面隐藏');
                self.pageVisible = false;
                self.sendImmediateStatus('hidden');
            } else {
                console.log('页面可见');
                self.pageVisible = true;
                self.lastActivityTime = Date.now();
                self.sendImmediateStatus('online');
            }
        });
        
        window.addEventListener('focus', function() {
            if (!self.pageVisible) {
                console.log('窗口获得焦点');
                self.pageVisible = true;
                self.sendImmediateStatus('online');
            }
        });
        
        window.addEventListener('blur', function() {
            if (self.pageVisible) {
                console.log('窗口失去焦点');
                self.pageVisible = false;
                self.sendImmediateStatus('hidden');
            }
        });
    }
    
    sendImmediateStatus(status) {
        const requestData = {
            action: 'update_online_status',
            username: this.customerName,
            user_type: 'customer',
            is_online: status === 'online',
            window_status: this.getWindowStatusValue(status)
        };
        
        console.log('立即发送状态:', requestData);
        
        const blob = new Blob([JSON.stringify(requestData)], {type: 'application/json'});
        if (navigator.sendBeacon) {
            navigator.sendBeacon(this.apiBaseUrl, blob);
        } else {
            fetch(this.apiBaseUrl, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(requestData),
                keepalive: true
            }).catch(() => {});
        }
    }
    
    updateOnlineStatus() {
        const self = this;
        
        let status = this.pageVisible ? 'online' : 'hidden';
        
        console.log('轮询更新状态:', status);
        
        const requestData = {
            action: 'update_online_status',
            username: this.customerName,
            user_type: 'customer',
            is_online: status === 'online',
            window_status: this.getWindowStatusValue(status)
        };
        
        fetch(this.apiBaseUrl, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(requestData)
        })
        .then(response => response.text())
        .then(text => {
            try {
                const data = JSON.parse(text);
                if (!data.success) {
                    console.warn('状态更新失败:', data.message);
                }
            } catch (e) {
                console.error('解析响应失败');
            }
        })
        .catch(error => {
            console.error('状态更新失败:', error);
        });
    }
    
    getWindowStatusValue(status) {
        switch(status) {
            case 'online':
                return 'window_visible';
            case 'hidden':
            case 'away':
                return 'window_hidden';
            case 'offline':
            default:
                return 'window_closed';
        }
    }
    
    updateCustomerOnlineStatus() {
        const self = this;
        
        console.log('更新客户在线状态:', this.customerName);
        
        $.ajax({
            url: this.apiBaseUrl,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                action: 'update_online_status',
                username: this.customerName,
                user_type: 'customer',
                is_online: true
            }),
            success: function(data) {
                console.log('客户在线状态更新成功:', data);
                self.isOnline = true;
            },
            error: function(xhr, status, error) {
                console.error('更新客户在线状态失败:', error);
                self.isOnline = false;
            }
        });
    }
    
    setCustomerOffline() {
        console.log('设置客户为离线状态:', this.customerName);
        
        const data = {
            action: 'update_online_status',
            username: this.customerName,
            user_type: 'customer',
            is_online: false,
            window_status: 'window_closed',
            device_type: this.deviceInfo.type,
            browser: this.deviceInfo.browser,
            os: this.deviceInfo.os
        };
        
        const blob = new Blob([JSON.stringify(data)], {type: 'application/json'});
        if (navigator.sendBeacon) {
            navigator.sendBeacon(this.apiBaseUrl, blob);
        } else {
            $.ajax({
                url: this.apiBaseUrl,
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify(data),
                async: false,
                timeout: 1000
            });
        }
    }
    
    uploadImage(file) {
        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/bmp'];
        const maxSize = 5 * 1024 * 1024;
        
        if (!allowedTypes.includes(file.type)) {
            alert('请选择图片文件 (JPEG, PNG, GIF, WebP, BMP)');
            return;
        }
        
        if (file.size > maxSize) {
            alert('图片大小不能超过 5MB');
            return;
        }
        
        if (this.isUploadingImage) {
            alert('正在上传图片，请稍候...');
            return;
        }
        
        this.isUploadingImage = true;
        this.updateSendButton();
        
        const self = this;
        const reader = new FileReader();
        
        reader.onload = function(e) {
            const imageData = e.target.result;
            
            self._sentMessageCounter++;
            const tempMessageId = 'temp_img_' + Date.now() + '_' + self._sentMessageCounter;
            self.appendMessages([{
                id: tempMessageId,
                agent_account: self.agentAccount,
                speaker_type: 1,
                content: '[图片]',
                customer_name: self.customerName,
                message_type: 'image',
                image_url: imageData,
                image_name: file.name,
                image_path: 'temp_' + tempMessageId,
                remark: '',
                created_at: new Date().toISOString()
            }]);
            
            // 记录已发送图片消息，用于去重
            self._lastSentMessages.push({
                tempId: tempMessageId,
                content: '[图片]',
                speaker_type: 1,
                messageType: 'image',
                timestamp: Date.now()
            });
            if (self._lastSentMessages.length > 20) {
                self._lastSentMessages.shift();
            }
            
            self.scrollToBottom();
            
            $.ajax({
                url: self.apiBaseUrl,
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({
                    action: 'upload_image',
                    session_id: self.sessionId,
                    agent_account: self.agentAccount,
                    customer_name: self.customerName,
                    image_data: imageData,
                    image_name: file.name,
                    image_size: file.size,
                    platform: self.platform
                }),
                success: function(data) {
                    self.isUploadingImage = false;
                    self.updateSendButton();
                    
                    if (data.success) {
                        console.log('图片上传成功:', data.message_id);
                        self.lastMessageId = Math.max(self.lastMessageId, data.message_id);
                    } else {
                        console.error('图片上传失败:', data.message);
                        alert('图片上传失败: ' + data.message);
                    }
                },
                error: function(xhr, status, error) {
                    self.isUploadingImage = false;
                    self.updateSendButton();
                    console.error('图片上传请求失败:', error);
                    alert('图片上传失败，请重试');
                }
            });
        };
        
        reader.onerror = function() {
            self.isUploadingImage = false;
            self.updateSendButton();
            alert('图片读取失败，请重试');
        };
        
        reader.readAsDataURL(file);
    }
    
    escapeHtml(unsafe) {
        if (unsafe === undefined || unsafe === null) {
            return '';
        }
        
        const safe = String(unsafe);
        return safe
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }
    
    scrollToBottom() {
        const container = $('#chat-container');
        setTimeout(() => {
            container.scrollTop(container[0].scrollHeight);
        }, 100);
    }
    
    playNotificationSound() {
        try {
            const audioContext = new (window.AudioContext || window.webkitAudioContext)();
            const oscillator = audioContext.createOscillator();
            const gainNode = audioContext.createGain();
            
            oscillator.connect(gainNode);
            gainNode.connect(audioContext.destination);
            
            oscillator.frequency.value = 800;
            oscillator.type = 'sine';
            
            gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
            gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.5);
            
            oscillator.start(audioContext.currentTime);
            oscillator.stop(audioContext.currentTime + 0.5);
        } catch (e) {
            console.log('播放提示音失败:', e);
        }
    }
    
    // ==================== WebSocket 相关方法 ====================
    
    initWebSocket() {
        console.log('🔄 客户端初始化 WebSocket...');
        
        if (this.ws && (this.ws.readyState === WebSocket.OPEN || this.ws.readyState === WebSocket.CONNECTING)) {
            console.log('客户端 WebSocket 已连接或正在连接');
            return;
        }
        
        const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
        const hostname = window.location.hostname;
        const wsUrl = `${protocol}//${window.location.host}/wss`;
        
        console.log('🌐 客户端连接 WebSocket:', wsUrl);
        
        try {
            this.ws = new WebSocket(wsUrl);
            this.wsConnectionStatus = 'connecting';
            
            this.ws.onopen = (event) => {
                console.log('✅ 客户端 WebSocket 连接成功');
                this.handleWebSocketOpen(event);
            };
            
            this.ws.onmessage = (event) => {
                this.handleWebSocketMessage(event);
            };
            
            this.ws.onerror = (event) => {
                console.error('❌ 客户端 WebSocket 连接错误');
                this.handleWebSocketError(event);
            };
            
            this.ws.onclose = (event) => {
                console.log('🔌 客户端 WebSocket 连接关闭', event.code, event.reason);
                this.handleWebSocketClose(event);
            };
            
        } catch (error) {
            console.error('❌ 客户端创建 WebSocket 连接失败:', error);
            this.wsConnectionStatus = 'error';
            this.scheduleWebSocketReconnect();
        }
    }
    
    handleWebSocketOpen(event) {
        console.log('🎉 客户端 WebSocket 连接已打开，准备身份验证');
        
        this.wsConnected = true;
        this.wsConnectionStatus = 'connected';
        this.wsReconnectAttempts = 0;
        
        setTimeout(() => {
            this.sendWebSocketAuth();
        }, 100);
        
        this.startWebSocketHeartbeat();
        
        setTimeout(() => {
            this.flushWebSocketMessageQueue();
        }, 200);
        
        this.updateCustomerOnlineStatus();
    }
    
    sendWebSocketAuth() {
        if (!this.wsConnected || this.ws.readyState !== WebSocket.OPEN) {
            console.warn('客户端 WebSocket 未连接，无法发送身份验证');
            return;
        }
        
        const authData = {
            type: 'auth',
            user_type: 'customer',
            user_id: this.customerName,
            session_key: this.sessionId
        };
        
        this.ws.send(JSON.stringify(authData));
        console.log('客户端发送 WebSocket 身份验证:', authData);
        this.wsAuthSent = true;
    }
    
    handleWebSocketMessage(event) {
        try {
            const data = JSON.parse(event.data);
            console.log('📨 客户端收到 WebSocket 消息类型:', data.type, '数据:', data);
            
            switch (data.type) {
                case 'auth_success':
                    console.log('✅ 客户端 WebSocket 身份验证成功');
                    break;
                    
                case 'auth_error':
                    console.error('❌ 客户端 WebSocket 身份验证失败:', data.message);
                    break;
                    
                case 'send_message':
                case 'new_message':
                    this.handleRealTimeMessage(data);
                    break;
                    
                case 'message_sent':
                    this.handleMessageSentReceipt(data);
                    break;
                    
                case 'pong':
                    console.log('💓 客户端 WebSocket 心跳响应');
                    break;
                    
                case 'error':
                    console.error('❌ 客户端 WebSocket 服务器错误:', data.message);
                    break;
            }
            
        } catch (error) {
            console.error('客户端解析 WebSocket 消息失败:', error, '原始数据:', event.data);
        }
    }
    
    handleRealTimeMessage(data) {
        console.log('📨 handleRealTimeMessage 收到数据:', data);
        
        if (data.session_key === this.sessionId) {
            console.log('客户端收到实时消息，speaker_type:', data.speaker_type, 'content:', data.content);
            
            if (data.message_id && data.message_id <= this.lastMessageId) {
                console.log('消息ID过小,跳过:', data.message_id, '<=', this.lastMessageId);
                return;
            }
            
            if (data.message_id && $(`[data-message-id="${data.message_id}"]`).length > 0) {
                console.log('消息已存在于DOM中,跳过:', data.message_id);
                return;
            }
            
            if (data.message_id && this.recentlySentMessageIds.has(data.message_id)) {
                console.log('是自己发送的消息，WebSocket 回声已跳过:', data.message_id);
                return;
            }
            
            // 先检查是否是自己刚发送的消息（去重）
            if (data.speaker_type === 1) {
                const now = Date.now();
                const isRecentlySent = this._lastSentMessages.some(sent => {
                    if (sent.messageType === 'image' && data.message_type === 'image') {
                        return (now - sent.timestamp) < 5000;
                    }
                    if (sent.content === data.content && (now - sent.timestamp) < 5000) {
                        return true;
                    }
                    return false;
                });
                if (isRecentlySent) {
                    console.log('跳过WebSocket重复消息(自己发送):', data.message_id, data.content);
                    if (data.message_id) {
                        this.lastMessageId = Math.max(this.lastMessageId, data.message_id);
                    }
                    return;
                }
            }
            
            const message = {
                id: data.message_id || 'ws_' + Date.now(),
                content: data.content,
                speaker_type: data.speaker_type || 2,
                created_at: data.created_at || new Date().toISOString(),
                customer_name: data.customer_name || this.customerName,
                agent_account: data.agent_account || this.agentAccount,
                message_type: data.message_type || 'text',
                image_url: data.image_url,
                image_path: data.image_path,
                dummy_name: data.dummy_name,
                dummy_avatar: data.dummy_avatar
            };
            
            this.appendMessages([message]);
            this.scrollToBottom();
            this.playNotificationSound();
            
            if (data.message_id) {
                this.recentlyReceivedWsMessageIds.add(data.message_id);
                setTimeout(() => {
                    this.recentlyReceivedWsMessageIds.delete(data.message_id);
                }, 10000);
            }
            
            if (data.message_id && data.message_id > this.lastMessageId) {
                this.lastMessageId = data.message_id;
            }
        }
    }
    
    handleMessageSentReceipt(data) {
        console.log('客户端消息发送回执:', data);
    }
    
    handleWebSocketError(event) {
        console.error('客户端 WebSocket 错误:', event);
        this.wsConnectionStatus = 'error';
    }
    
    handleWebSocketClose(event) {
        console.log('客户端 WebSocket 连接关闭:', event.code, event.reason);
        this.wsConnected = false;
        this.wsConnectionStatus = 'disconnected';
        this.wsAuthSent = false;
        
        this.stopWebSocketHeartbeat();
        this.scheduleWebSocketReconnect();
    }
    
    startWebSocketHeartbeat() {
        this.stopWebSocketHeartbeat();
        
        this.wsHeartbeatInterval = setInterval(() => {
            if (this.wsConnected && this.ws.readyState === WebSocket.OPEN) {
                const heartbeat = {
                    type: 'ping',
                    timestamp: Date.now()
                };
                this.ws.send(JSON.stringify(heartbeat));
            }
        }, 30000);
    }
    
    stopWebSocketHeartbeat() {
        if (this.wsHeartbeatInterval) {
            clearInterval(this.wsHeartbeatInterval);
            this.wsHeartbeatInterval = null;
        }
    }
    
    scheduleWebSocketReconnect() {
        if (this.wsReconnectAttempts >= this.maxWsReconnectAttempts) {
            console.log('客户端已达到最大重连次数，停止重连');
            return;
        }
        
        this.wsReconnectAttempts++;
        const delay = this.wsReconnectDelay * Math.pow(1.5, this.wsReconnectAttempts - 1);
        
        console.log(`客户端将在 ${delay}ms 后尝试第 ${this.wsReconnectAttempts} 次重连`);
        
        setTimeout(() => {
            console.log('客户端尝试重连 WebSocket...');
            this.initWebSocket();
        }, delay);
    }
    
    sendMessageToWebSocket(messageData) {
        if (!this.wsConnected || this.ws.readyState !== WebSocket.OPEN) {
            console.log('客户端 WebSocket 未连接，将消息加入队列');
            this.wsMessageQueue.push(messageData);
            return false;
        }
        
        try {
            this.ws.send(JSON.stringify(messageData));
            console.log('📤 客户端通过 WebSocket 发送消息:', messageData);
            return true;
        } catch (error) {
            console.error('客户端 WebSocket 发送消息失败:', error);
            this.wsMessageQueue.push(messageData);
            return false;
        }
    }
    
    flushWebSocketMessageQueue() {
        if (this.wsMessageQueue.length === 0) return;
        
        console.log(`客户端刷新消息队列，有 ${this.wsMessageQueue.length} 条待发送消息`);
        
        const queue = [...this.wsMessageQueue];
        this.wsMessageQueue = [];
        
        queue.forEach(messageData => {
            this.sendMessageToWebSocket(messageData);
        });
    }
    
    // ==================== WebSocket 方法结束 ====================
    
    destroy() {
        this.stopPolling();
        this.closeImagePreview();
        this.setCustomerOffline();
        
        if (this.ws) {
            this.ws.close(1000, '页面关闭');
            this.stopWebSocketHeartbeat();
        }
        
        console.log('盼之聊天系统已销毁');
    }
}

// 初始化聊天系统
$(document).ready(function() {
    console.log('文档加载完成，初始化盼之聊天系统...');
    window.customerChat = new CustomerChatSystem();
    
    // 页面加载完成后隐藏加载动画
    setTimeout(function() {
        var loadingContainer = document.getElementById('loadingContainer');
        if (loadingContainer) {
            loadingContainer.classList.add('hidden');
            setTimeout(function() {
                loadingContainer.remove();
            }, 300);
        }
    }, 500);
    
    $(window).on('beforeunload', function() {
        if (window.customerChat) {
            window.customerChat.destroy();
        }
    });
});
</script>

   <!-- <script src="/assets/XEpz.js"></script> -->
   <script>

        // 全局变量，用于兼容旧代码
        let card1 = null;
        let card2 = null;
        let icon1 = null;
        let icon2 = null;
        let Send_msg = null;
        let Send_img = null;

        // 隐藏所有浮动菜单（空实现，兼容旧代码）
        function hideAllFloatingMenus() {
            // 已废弃，保留函数签名以避免错误
        }

        // 关闭商品浮动卡片
        function close_shop() {
            const shopLevitation = document.getElementById('shop_levitation');
            if (shopLevitation) {
                shopLevitation.style.display = 'none';
            }
        }

        // 空函数，避免旧代码调用出错
        function hide_button() {
            // 已废弃
        }

// 显示评价弹窗
function showAppraise() {
    document.getElementById('appraise-overlay').classList.add('active');
    document.getElementById('appraise').classList.add('active');
}

// 关闭评价弹窗
function closeAppraise() {
    document.getElementById('appraise-overlay').classList.remove('active');
    document.getElementById('appraise').classList.remove('active');
}

// 提交评价
function submitAppraise() {
    closeAppraise();
    document.getElementById('cgtipstext').innerHTML = "评价成功";
    if (document.getElementById('cgtips')) {
        document.getElementById('cgtips').style.display = 'flex';
        setTimeout(function() {
            document.getElementById('cgtips').style.display = 'none';
        }, 1500);
    }
}

function emoji1() {
    // 已废弃
}

function emoji2() {
    // 已废弃
}

function emoji3() {
    // 已废弃
}

function emoji4() {
    // 已废弃
}

function emoji5() {
    // 已废弃
}

// 空函数，避免旧代码调用出错
function hideToolbar() {
    // 已废弃
}

function close_windows() {
    // 已废弃
}

function open_windwos() {
    // 已废弃
}

function shouqian() {
    // 已废弃
}

function shouhou() {
    // 已废弃
}

function jiaoti09() {
    document.getElementById('cgtipstext').innerHTML = "问题反馈成功";
    document.getElementById('cgtips').style.display = 'flex';
    setTimeout(function() {
        window.location.href = "../pz.php";
    }, 1500);
}

function shareclose01() {
    // 已废弃
}

function gzbtn_1() {
    // 已废弃
}

function gzbtn_2() {
    // 已废弃
}

function gzbtn_3() {
    // 已废弃
}

function gzbtn_4() {
    // 已废弃
}
   </script>
  <script>
    // 页面加载完成后执行
    document.addEventListener('DOMContentLoaded', function() {
        // 监听相册文件选择
        const albumInput = document.getElementById('album-input');
        if (albumInput) {
            albumInput.addEventListener('change', function(e) {
                console.log('文件选择触发');
                const file = e.target.files[0];
                console.log('选择的文件:', file);
                if (file) {
                    if (window.customerChat && window.customerChat.uploadImage) {
                        console.log('调用 uploadImage');
                        window.customerChat.uploadImage(file);
                    } else {
                        console.error('customerChat 或 uploadImage 不可用');
                    }
                }
                e.target.value = ''; // 清空选择，允许重复选择同一文件
            });
        } else {
            console.error('找不到 album-input 元素');
        }
        
        // 找到显示时间的元素
        const timeElement = document.querySelector('.timestamp-text');
        
        // 创建Date对象，获取当前时间
        const now = new Date();
        
        // 获取时间的各个部分
        const year = now.getFullYear();
        const month = String(now.getMonth() + 1).padStart(2, '0'); // 月份从0开始，所以要+1
        const day = String(now.getDate()).padStart(2, '0');
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        
        // 拼接成您想要的格式 "2025-11-20 22:38"
        const formattedTime = `${year}-${month}-${day} ${hours}:${minutes}`;
        
        // 将格式化后的时间填入元素
        timeElement.textContent = formattedTime;
    });
    
    // 相册按钮独立测试和事件绑定
    setTimeout(function() {
        const albumBtn = document.getElementById('album-btn');
        const albumInput = document.getElementById('album-input');
        
        if (albumBtn) {
            console.log('找到相册按钮，准备绑定事件');
            albumBtn.addEventListener('click', function() {
                console.log('相册按钮被点击');
                if (albumInput) {
                    console.log('触发文件选择');
                    albumInput.click();
                } else {
                    console.error('找不到文件输入框');
                }
            });
        } else {
            console.error('找不到相册按钮');
        }
        
        if (albumInput) {
            console.log('找到相册输入框，准备绑定change事件');
            albumInput.addEventListener('change', function(e) {
                console.log('文件选择触发');
                const file = e.target.files[0];
                console.log('选择的文件:', file);
                if (file) {
                    // 直接尝试调用 uploadImage
                    if (window.customerChat && typeof window.customerChat.uploadImage === 'function') {
                        console.log('调用 uploadImage');
                        window.customerChat.uploadImage(file);
                    } else {
                        console.warn('customerChat未初始化，等待后重试...');
                        // 等待 customerChat 初始化
                        setTimeout(function() {
                            if (window.customerChat && typeof window.customerChat.uploadImage === 'function') {
                                window.customerChat.uploadImage(file);
                            }
                        }, 500);
                    }
                }
                e.target.value = '';
            });
        } else {
            console.error('找不到相册输入框');
        }
    }, 1000);
</script>
   
</body>
</html>
