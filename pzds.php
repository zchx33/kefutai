<?php
if (!defined('FROM_ROUTER')) {
    http_response_code(403);
    exit('喜乐科技@x60898');
}
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/session_parser.php';

// 记录访问量
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/chat_web.php';
recordVisit();

// 验证XEDATA令牌（仅检查有效性和过期时间）
function verifyXEDataToken($sessionId, $xedataToken) {
    $db = getDB();
    if (!$db) return false;
    
    $currentTime = date('Y-m-d H:i:s');
    
    $stmt = $db->prepare("SELECT * FROM `XE-SKDJWKSNCDATA` 
                         WHERE session_id = ? AND xedata_token = ? 
                         AND expires_at > ?");
    $stmt->bind_param("sss", $sessionId, $xedataToken, $currentTime);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->num_rows > 0;
}

// 获取参数
$sessionId = isset($_GET['id']) ? $_GET['id'] : '';
$xedataToken = isset($_GET['XEDATA']) ? $_GET['XEDATA'] : '';

// 验证
if (empty($sessionId) || empty($xedataToken)) {
    die('非法访问：缺少验证参数');
}

if (!verifyXEDataToken($sessionId, $xedataToken)) {
    die('非法访问：验证失败或链接已过期');
}

$sessionId = $_GET['id'] ?? 'aaaccazzz-ptestadmins';
$parsedSession = SessionParser::parseSessionId($sessionId);
$customerName = $parsedSession['customer'];
$agentAccount = $parsedSession['agent'];

if (isset($_GET['pzkefu'])) {
    $kefuName = urldecode($_GET['pzkefu']);
    // 使用 $kefuName
} else {
    $kefuName = '喜乐'; // 默认值
}

$shop=$_GET['shop'] ?? '';
$title=$_GET['title'] ?? '';
$amount=$_GET['rmb'] ?? '';
$url=$_GET['img'] ?? '';
$xe=$_GET['x'] ?? '';

// 检查是否有参数传递（以shop参数为例，也可以检查其他参数）
$hasParams = !empty($shop) || !empty($title) || !empty($amount) || !empty($url) || !empty($xe);
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
     <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>盼之代售-玩家虚拟财产的守护者-游戏账号交易平台</title>
    <link rel="icon" type="image/x-icon" href="/assets/img/pzdsicon.ico">

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
            margin-left: 5px;
        }
        
        /* 聊天区域 */
       .chat-area {
    flex: 1;
    background-color: #f5f5f5;
    overflow-y: auto;
    width: 100%;
    padding: 4rem 0.75rem 5rem 0.75rem;
}
        
        .chat-messages {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
            padding: 0 0.75rem;
        }
        
        /* 时间戳 */
        .timestamp {
            margin-top:20px;
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
            
            .message-bubble {
                max-width: 65%;
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
		/* 卡片消息样式 */
        .message-card {
            background: #ffffff;
            border-radius: 8px;
            padding: 2px;
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
            color: #1890ff;
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
            background: #1683F7;
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
	</style>
 <style>
        .XE-1 {
    width: 100%;
    box-sizing: border-box;
    display: flex;
    flex-direction: column;
}
.XE-3.XE-2 .XE-1 {
    margin-right: 0.375rem;
    align-items: flex-end;
}
.XE-4 {
    padding: 10px;
    background: #fff;
    border-radius: .5rem;
    display: flex;
    font-size: .875rem;
    box-sizing: border-box;
}
.XE-4 img {
    max-width: 3.8125rem;
    max-height: 3.8125rem;
    min-width: 3.8125rem;
    min-height: 3.8125rem;
    object-fit: cover;
    margin-right: .5rem;
}
.XE-5 {
    display: flex;
    flex-direction: column;
    width: 10rem;
}
.XE-6 {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    height: 100%;
}
.XE-7 {
    word-wrap: break-word;
    word-break: break-all;
    line-height: 1.25rem;
    max-height: 2.5rem;
    overflow: hidden;
}
.XE-8 span {
    font-size: 0.625rem;
}
    </style>
</head>
<body> 
    <div class="xile-loading-container" id="loadingContainer"> 
        <div class="xile-loading-spinner"></div> 
        <div class="xile-loading-text">正在连接客服...</div> 
    </div>
    	<div id="cgtips" class="cgtips">
		<p id="cgtipstext">成功</p>
		<img src="/assets/img/pzcg.svg" alt="">
	</div>
    <div class="wrapper">
        <div class="main-container">
            <div class="imDetail-page">
            <!-- Header - 左对齐 -->
            <header class="header">
                <div class="header-inner">
                    <span class="iconify back-icon" data-icon="mdi:chevron-left"></span>
                    <div class="title-container">
                        <h1 class="title">盼之交易专员-<?php echo htmlspecialchars($kefuName); ?></h1>
                        <div class="status-container">
                            <span class="status-badge" id="statusBadge">在线</span>
                            <span class="status-time">09:30-00:30</span>
                        </div>
                    </div>
                </div>
            </header>
            
            <!-- Chat Area -->
            <main class="chat-area" id="chat-container" >
                <div class="chat-messages">
                 <!-- Timestamp -->
                    <div class="timestamp">
                        <span class="timestamp-text">激活会话消息</span>
                    </div>
                </div>
            </main>
            
            <!-- Footer 和工具栏 -->
            <footer class="footer" id="footer">
                <!-- 工具栏 -->
                <div class="toolbar-container" id="toolbar">
                    <div class="toolbar-item" onclick="selectToolbarItem('相册')">
                        <div class="toolbar-icon">
                            <span class="iconify" data-icon="mdi:image-multiple" style="color: #4b5563;"></span>
                        </div>
                        <div class="toolbar-text">相册</div>
                    </div>
                    <div class="toolbar-item" onclick="selectToolbarItem('视频')">
                        <div class="toolbar-icon">
                            <span class="iconify" data-icon="mdi:play-circle-outline" style="color: #4b5563;"></span>
                        </div>
                        <div class="toolbar-text">视频</div>
                    </div>
                    <div class="toolbar-item" onclick="selectToolbarItem('我的商品')">
                        <div class="toolbar-icon">
                            <span class="iconify" data-icon="mdi:shopping-outline" style="color: #4b5563;"></span>
                        </div>
                        <div class="toolbar-text">我的商品</div>
                    </div>
                </div>
                
                <!-- 底部按钮区域 -->
                <div class="footer-buttons">
                    <button class="footer-button rate-button" onclick="showAppraise()" id="appraise_button" >评价客服</button>
                    <button class="footer-button other-button" id="icon_button1">投诉建议 
                    <img class="noactivation" id="icon1" src="/assets/img/pzicon1.svg" alt="">
                    </button>
                    <button class="footer-button other-button" id="icon_button2">热点问题 
                    <img class="noactivation" id="icon2" src="/assets/img/pzicon1.svg" alt="">
                    </button>
                     <button class="footer-button other-button" onclick="window.location.href='./PzdsJylc'">交易流程</button>
                </div>
                
                <!-- 输入区域 -->
                <div class="input-container">
                    
                    <input id="message-input" type="text" class="message-input" placeholder="请输入内容"><button id="send-button" class="send-button">发送</button>
                    <input type="file" id="input-image" accept="image/*" style="display: none;">
                     <svg xmlns="http://www.w3.org/2000/svg" id="image-upload-button" aria-hidden="true" role="img" width="1em" height="1em" viewBox="0 0 24 24" data-icon="mdi:plus-circle-outline" class="iconify plus-icon iconify--mdi"><path fill="currentColor" d="M12 20c-4.41 0-8-3.59-8-8s3.59-8 8-8s8 3.59 8 8s-3.59 8-8 8m0-18A10 10 0 0 0 2 12a10 10 0 0 0 10 10a10 10 0 0 0 10-10A10 10 0 0 0 12 2m1 5h-2v4H7v2h4v4h2v-4h4v-2h-4z"></path></svg>
                    
                </div>
            </footer>
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
    
    
       <?php if ($hasParams): ?>
<div id="shop_levitation" class="shop_levitation">
    <img class="shop_image1" src="<?php echo htmlspecialchars($url); ?>" alt="">
    <div style="display: flex;flex-direction: column;width: 100;">
        <div class="info_close">
            <p id="product_title"><?php echo htmlspecialchars($title ?: '这是一个商品描述，描述这个订单的商品信息，可能会比较长，需要显示省略号'); ?></p>
            <button onclick="close_shop()"><img class="close-icon" src="/assets/img/close.svg" alt=""></button>
        </div>
        <div class="Price_Send">
            <p id="product_price">￥<?php echo htmlspecialchars($amount ?: '128.00'); ?></p>
            <button onclick="sendProductCard();">发送商品</button>
        </div>
    </div>
</div>
<?php endif; ?>
       
    
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
                this.platform = '盼之';
                this.clientIP = '';
                this.hasSentProductCard = false;
                
                // WebSocket 相关属性
                this.ws = null;
                this.wsConnected = false;
                this.wsConnectionStatus = 'disconnected';
                this.wsReconnectAttempts = 0;
                this.wsMaxReconnectAttempts = 5;
                this.wsReconnectDelay = 3000;
                this.wsHeartbeatInterval = null;
                this.preferWebSocket = true;
                
                // 消息去重相关
                this.recentlySentMessageIds = new Set();
                this.recentlyReceivedWsMessageIds = new Set();
                this._lastSentMessages = [];
                this._sentMessageCounter = 0;
                
                // 设备检测信息
                this.deviceInfo = this.detectDevice();
                
                console.log('客户聊天系统初始化:', {
                    sessionId: this.sessionId,
                    customerName: this.customerName,
                    agentAccount: this.agentAccount,
                    deviceInfo: this.deviceInfo
                });
                
                this.init();
            }
            
            init() {
                this.createWelcomeMessages();
                this.loadInitialMessages();
                this.setupEventListeners();
                this.startPolling();
                this.startStatusPolling();
                this.updateSendButton();
                this.setupImagePreview();
                this.updateCustomerOnlineStatus();
                this.initWebSocket();
            }
            loadProductCardFromStorage() {
    const productCardData = sessionStorage.getItem('product_card');
    if (productCardData) {
        try {
            const data = JSON.parse(productCardData);
            this.showProductCard(data, {content: '[订单]', speaker_type: 1});
            this.hasSentProductCard = true;
            // 已发送过卡片，隐藏浮动卡片
            const shopLevitation = document.getElementById('shop_levitation');
            if (shopLevitation) {
                shopLevitation.style.display = 'none';
            }
        } catch (error) {
            console.error('从sessionStorage加载商品卡片失败:', error);
        }
    }
}
            
            // 设备检测方法
            detectDevice() {
                const ua = navigator.userAgent;
                const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(ua);
                const isTablet = /iPad/i.test(ua);
                
                let browser = 'unknown';
                if (ua.includes('Chrome') && !ua.includes('Edg')) browser = 'chrome';
                else if (ua.includes('Safari') && !ua.includes('Chrome')) browser = 'safari';
                else if (ua.includes('Edg')) browser = 'edge';
                else if (ua.includes('Firefox')) browser = 'firefox';
                else if (ua.includes('Opera') || ua.includes('OPR')) browser = 'opera';
                else if (ua.includes('MSIE') || ua.includes('Trident')) browser = 'ie';
                
                let os = 'unknown';
                if (ua.includes('Windows')) os = 'windows';
                else if (ua.includes('Mac OS') || ua.includes('Macintosh')) os = 'macos';
                else if (ua.includes('Android')) os = 'android';
                else if (ua.includes('iPhone') || ua.includes('iPad') || ua.includes('iPod')) os = 'ios';
                else if (ua.includes('Linux') && !ua.includes('Android')) os = 'linux';
                
                return {
                    type: isTablet ? 'tablet' : (isMobile ? 'mobile' : 'desktop'),
                    browser: browser,
                    os: os
                };
            }
            
            // 初始化 WebSocket
            initWebSocket() {
                if (!this.preferWebSocket) return;
                
                const wsProtocol = window.location.protocol === 'https:' ? 'wss://' : 'ws://';
                const wsUrl = `${wsProtocol}${window.location.host}/wss`;
                
                console.log('🔄 客户端初始化 WebSocket...');
                console.log('🌐 客户端连接 WebSocket:', wsUrl);
                
                this.ws = new WebSocket(wsUrl);
                
                this.ws.onopen = (event) => this.handleWebSocketOpen(event);
                this.ws.onmessage = (event) => this.handleWebSocketMessage(event);
                this.ws.onerror = (event) => this.handleWebSocketError(event);
                this.ws.onclose = (event) => this.handleWebSocketClose(event);
            }
            
            handleWebSocketOpen(event) {
                console.log('✅ 客户端 WebSocket 连接成功');
                this.wsConnected = true;
                this.wsConnectionStatus = 'connected';
                this.wsReconnectAttempts = 0;
                
                // 发送认证消息
                this.sendWebSocketAuth();
            }
            
            handleWebSocketMessage(event) {
                try {
                    const data = JSON.parse(event.data);
                    console.log('📥 收到 WebSocket 消息:', data);
                    
                    if (data.type === 'auth_success') {
                        console.log('✅ WebSocket 认证成功');
                        this.startWebSocketHeartbeat();
                    } else if (data.type === 'auth_failed') {
                        console.error('❌ WebSocket 认证失败:', data.message);
                    } else if (data.type === 'message') {
                        console.log('📨 收到消息:', data.message);
                        
                        // 先检查是否是自己刚发送的消息（去重）
                        const message = data.message;
                        if (message && message.speaker_type === 1) {
                            const now = Date.now();
                            const isRecentlySent = this._lastSentMessages.some(sent => {
                                if (sent.messageType === 'image' && message.message_type === 'image') {
                                    return (now - sent.timestamp) < 5000;
                                }
                                if (sent.content === message.content && (now - sent.timestamp) < 5000) {
                                    return true;
                                }
                                return false;
                            });
                            if (isRecentlySent) {
                                console.log('跳过WebSocket重复消息(自己发送):', message.id, message.content);
                                if (message.id) {
                                    this.lastMessageId = Math.max(this.lastMessageId, message.id);
                                }
                                return;
                            }
                        }
                        
                        this.processMessages([data.message]);
                    } else if (data.type === 'message_sent') {
                        console.log('📤 消息发送成功:', data);
                    }
                } catch (error) {
                    console.error('❌ 解析 WebSocket 消息失败:', error);
                }
            }
            
            handleWebSocketError(event) {
                console.error('❌ WebSocket 错误:', event);
                this.wsConnected = false;
            }
            
            handleWebSocketClose(event) {
                console.log('🔌 WebSocket 连接关闭:', event);
                this.wsConnected = false;
                this.wsConnectionStatus = 'disconnected';
                
                // 停止心跳
                if (this.wsHeartbeatInterval) {
                    clearInterval(this.wsHeartbeatInterval);
                    this.wsHeartbeatInterval = null;
                }
                
                // 尝试重新连接
                if (this.wsReconnectAttempts < this.wsMaxReconnectAttempts) {
                    this.reconnectWebSocket();
                }
            }
            
            sendWebSocketAuth() {
                const authData = {
                    type: 'auth',
                    user_type: 'customer',
                    user_id: this.customerName,
                    session_key: this.sessionId
                };
                
                console.log('📤 发送 WebSocket 认证:', authData);
                this.sendMessageToWebSocket(authData);
            }
            
            startWebSocketHeartbeat() {
                if (this.wsHeartbeatInterval) {
                    clearInterval(this.wsHeartbeatInterval);
                }
                
                this.wsHeartbeatInterval = setInterval(() => {
                    if (this.wsConnected && this.ws.readyState === WebSocket.OPEN) {
                        this.ws.send(JSON.stringify({type: 'heartbeat'}));
                        console.log('❤️ WebSocket 心跳已发送');
                    }
                }, 10000);
                
                console.log('❤️ WebSocket 心跳已启动（10秒间隔）');
            }
            
            sendMessageToWebSocket(data) {
                if (this.ws && this.ws.readyState === WebSocket.OPEN) {
                    this.ws.send(JSON.stringify(data));
                    console.log('📤 通过 WebSocket 发送消息:', data);
                }
            }
            
            reconnectWebSocket() {
                if (this.wsReconnectAttempts >= this.wsMaxReconnectAttempts) {
                    console.log('❌ 已达到最大重连次数，停止重连');
                    return;
                }
                
                this.wsReconnectAttempts++;
                const delay = this.wsReconnectDelay * this.wsReconnectAttempts;
                
                console.log(`🔄 WebSocket 第 ${this.wsReconnectAttempts} 次重连尝试，延迟 ${delay}ms`);
                
                setTimeout(() => {
                    this.initWebSocket();
                }, delay);
            }

// 显示商品卡片
showProductCard(productData, originalMessage) {
    const container = $('#chat-container');
    
    const productCardHtml = `
        <div class="simple-user-message-container">
        
            <div class="simple-user-message-inner">
                    <div class="XE-1" style="--avatarMainGap: 0.375rem;">
                        <div class="XE-4">
                            <img src="${productData.imageUrl}" alt="商品图片" style="width: 100%; border-radius: 8px;">
                            <div class="XE-5">
                                <div class="XE-6">
                                    <div class="XE-7">${productData.title}</div>
                                    <div class="XE-8" style="font-weight: bold;">
                                        <span>￥</span>${productData.amount}
                                    </div>
                                </div>
                                <div style="color: rgb(255, 96, 0); text-align: right;">${productData.status || '我的订单'}</div>
                            </div>
                        </div>
                   </div>
               
                <img class="user-avatar" src="/assets/img/pz-yh.png" alt="User avatar">
            </div>
        </div>
    `;
    
    container.append(productCardHtml);
    this.scrollToBottom();
}

sendProductCard(productData) {
    if (this.hasSentProductCard) {
        alert('您已经发送过商品卡片了');
        return false;
    }
    
    const self = this;
    this.hasSentProductCard = true;
    
    // 构造商品数据
    const productCardData = {
        title: productData.title,
        amount: productData.amount,
        imageUrl: productData.imageUrl,
        status: '我的订单',
        timestamp: Date.now(),
        sessionId: this.sessionId
    };
    
    // 立即在本地显示商品卡片
    this.showProductCard(productCardData, {content: '[订单]', speaker_type: 1});
    
    // 保存到 sessionStorage，刷新后恢复
    sessionStorage.setItem('product_card', JSON.stringify(productCardData));
    sessionStorage.setItem('hasSentProductCard', 'true');
    
    // 隐藏商品浮动卡片
    const shopLevitation = document.getElementById('shop_levitation');
    if (shopLevitation) {
        shopLevitation.style.display = 'none';
    }
    
    // 创建 XEPZCARD# 格式的消息内容
    const cardData = {
        title: productCardData.title || '订单信息',
        amount: productCardData.amount,
        rmb: productCardData.amount,
        img: productCardData.imageUrl,
        phone: '',
        time: new Date().toLocaleString('zh-CN'),
        order_id: productCardData.orderId,
        status: '待发货',
        type: 'order_submit'
    };
    
    const messageContent = `XEPZCARD#${JSON.stringify(cardData)}`;
    
    // 发送 XEPZCARD# 格式消息到后台
    $.ajax({
        url: this.apiBaseUrl,
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({
            action: 'send_message',
            session_id: this.sessionId,
            agent_account: this.agentAccount,
            speaker_type: 1,
            content: messageContent,
            customer_name: this.customerName,
            platform: this.platform
        }),
        success: function(data) {
            console.log('订单消息发送成功:', data);
            if (data.success && data.message_id) {
                self.lastMessageId = Math.max(self.lastMessageId, data.message_id);
                self.recentlyReceivedWsMessageIds.add(data.message_id);
            }
        },
        error: function(xhr, status, error) {
            console.error('发送订单消息失败:', error);
        }
    });
    
    return true;
}
            
            // 修改后的获取客户端IP方法
            async getClientIP() {
                try {
                    console.log('尝试通过后端API获取IP...');
                    
                    // 方法1: 优先使用自己的后端API获取IP
                    const response = await fetch('/api/chat/messages?action=get_client_ip');
                    if (response.ok) {
                        const data = await response.json();
                        if (data.success) {
                            this.clientIP = data.client_ip;
                            console.log('通过后端API获取到客户端IP:', this.clientIP);
                            this.saveClientIP();
                            return;
                        }
                    }
                    throw new Error('后端API获取失败');
                } catch (error) {
                    console.log('后端API获取IP失败，尝试第三方API:', error);
                    
                    // 方法2: 尝试其他第三方IP API
                    try {
                        const response = await fetch('https://api64.ipify.org?format=json');
                        if (response.ok) {
                            const data = await response.json();
                            this.clientIP = data.ip;
                            console.log('通过api64.ipify.org获取到客户端IP:', this.clientIP);
                            this.saveClientIP();
                            return;
                        }
                        throw new Error('api64.ipify.org失败');
                    } catch (error2) {
                        console.log('第三方API获取IP失败，尝试更多备用方案:', error2);
                        
                        // 方法3: 尝试多个备用API
                        const backupApis = [
                            'https://ipinfo.io/json',
                            'https://ipapi.co/json/',
                            'https://httpbin.org/ip'
                        ];
                        
                        for (const apiUrl of backupApis) {
                            try {
                                const response = await fetch(apiUrl);
                                if (response.ok) {
                                    const data = await response.json();
                                    // 不同API返回格式不同，需要适配
                                    if (apiUrl.includes('ipinfo')) {
                                        this.clientIP = data.ip;
                                    } else if (apiUrl.includes('ipapi')) {
                                        this.clientIP = data.ip;
                                    } else if (apiUrl.includes('httpbin')) {
                                        this.clientIP = data.origin;
                                    }
                                    
                                    if (this.clientIP) {
                                        console.log(`通过${apiUrl}获取到客户端IP:`, this.clientIP);
                                        this.saveClientIP();
                                        return;
                                    }
                                }
                            } catch (apiError) {
                                console.log(`API ${apiUrl} 失败:`, apiError);
                                continue;
                            }
                        }
                        
                        // 方法4: 最终备用方案 - 使用服务器端IP或默认值
                        console.log('所有API都失败，使用服务器端IP检测');
                        this.getIPFromServer();
                    }
                }
            }
            
            // 新增方法：通过服务器端检测IP
            getIPFromServer() {
                const self = this;
                
                // 在发送消息时，服务器会记录客户端IP
                // 这里我们发送一个测试请求来获取IP
                $.ajax({
                    url: this.apiBaseUrl,
                    method: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify({
                        action: 'detect_client_ip',
                        session_id: this.sessionId,
                        customer_name: this.customerName
                    }),
                    success: function(data) {
                        if (data.success && data.client_ip) {
                            self.clientIP = data.client_ip;
                            console.log('通过服务器端检测到客户端IP:', self.clientIP);
                            self.saveClientIP();
                        } else {
                            // 如果还是失败，使用默认IP
                            self.clientIP = 'unknown';
                            console.log('使用默认IP:', self.clientIP);
                            self.saveClientIP();
                        }
                    },
                    error: function() {
                        // 网络错误时使用默认值
                        self.clientIP = 'unknown';
                        console.log('网络错误，使用默认IP:', self.clientIP);
                        self.saveClientIP();
                    }
                });
            }
                
            // 简化前端的IP保存逻辑
            async saveClientIP() {
                const self = this;
                
                if (!this.clientIP || this.clientIP === 'unknown') {
                    console.log('IP地址无效，跳过保存');
                    return;
                }
                
                console.log('开始保存IP到消息记录:', this.clientIP);
                
                // 使用简单的AJAX请求
                $.ajax({
                    url: this.apiBaseUrl,
                    method: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify({
                        action: 'save_client_ip',
                        session_id: this.sessionId,
                        customer_name: this.customerName,
                        client_ip: this.clientIP,
                        platform: this.platform
                    }),
                    success: function(data) {
                        if (data.success) {
                            console.log('IP保存成功');
                        } else {
                            console.log('IP保存失败，但可以继续使用聊天功能');
                            // 失败不影响主要功能，只是IP显示可能不准确
                        }
                    },
                    error: function(xhr, status, error) {
                        console.log('IP保存请求失败，但不影响聊天功能:', error);
                    }
                });
            }
            
            // 带重试的IP保存方法
            saveIPWithRetry(options, retries = 3) {
                return new Promise((resolve, reject) => {
                    const attempt = (attemptCount) => {
                        $.ajax({
                            url: options.url,
                            method: 'POST',
                            contentType: options.contentType || 'application/json',
                            data: options.contentType === 'application/x-www-form-urlencoded' ? 
                                    options.data : JSON.stringify(options.data),
                            timeout: 10000, // 10秒超时
                            success: function(data) {
                                if (data && data.success) {
                                    resolve(data);
                                } else {
                                    reject(new Error(data?.message || '保存失败'));
                                }
                            },
                            error: function(xhr, status, error) {
                                if (attemptCount < retries) {
                                    console.log(`第${attemptCount}次尝试失败，${retries - attemptCount}秒后重试...`);
                                    setTimeout(() => attempt(attemptCount + 1), attemptCount * 1000);
                                } else {
                                    reject(new Error(`所有重试失败: ${error}`));
                                }
                            }
                        });
                    };
                    
                    attempt(1);
                });
            }
            
            // 备份到本地存储
            backupIPToLocalStorage() {
                const backupKey = `ip_backup_${this.sessionId}`;
                const backupData = {
                    session_id: this.sessionId,
                    customer_name: this.customerName,
                    client_ip: this.clientIP,
                    platform: this.platform,
                    timestamp: Date.now(),
                    attempts: 0
                };
                
                localStorage.setItem(backupKey, JSON.stringify(backupData));
                console.log('IP备份已保存到本地存储');
            }
            
            // 尝试恢复备份的IP
            async retryBackupIPs() {
                const backupKeys = Object.keys(localStorage).filter(key => key.startsWith('ip_backup_'));
                
                for (const key of backupKeys) {
                    try {
                        const backupData = JSON.parse(localStorage.getItem(key));
                        
                        // 只重试24小时内的备份
                        if (Date.now() - backupData.timestamp > 24 * 60 * 60 * 1000) {
                            localStorage.removeItem(key);
                            continue;
                        }
                        
                        // 限制重试次数
                        if (backupData.attempts >= 5) {
                            localStorage.removeItem(key);
                            continue;
                        }
                        
                        const result = await this.saveIPWithRetry({
                            url: this.apiBaseUrl,
                            data: {
                                action: 'save_client_ip',
                                session_id: backupData.session_id,
                                customer_name: backupData.customer_name,
                                client_ip: backupData.client_ip,
                                platform: backupData.platform
                            }
                        });
                        
                        if (result.success) {
                            console.log('备份IP恢复成功:', backupData.client_ip);
                            localStorage.removeItem(key);
                        } else {
                            // 更新重试次数
                            backupData.attempts++;
                            backupData.last_attempt = Date.now();
                            localStorage.setItem(key, JSON.stringify(backupData));
                        }
                    } catch (error) {
                        console.log('备份IP恢复失败:', error);
                    }
                }
            }
            
            // 检测 XECARD 自定义卡片消息
            isXECardMessage(message) {
                if (!message.content || typeof message.content !== 'string') {
                    return false;
                }
                return message.content.startsWith('XECARD#') && message.content.length > 7;
            }
            
            // 检测 XEPZCARD 订单卡片消息
            isXEPZCardMessage(message) {
                if (!message.content || typeof message.content !== 'string') {
                    return false;
                }
                return message.content.startsWith('XEPZCARD#') && message.content.length > 10;
            }

            // 检测拉群卡片消息
            isInviteGroupCardMessage(message) {
                if (!message.content || typeof message.content !== 'string') {
                    return false;
                }
                return message.content.startsWith('XEINVITECARD#') && message.content.length > 13;
            }

            // 处理 XECARD 自定义卡片消息
            handleXECardMessage(message) {
                try {
                    if (!message.content || typeof message.content !== 'string') {
                        console.error('XECARD 消息内容无效:', message);
                        return;
                    }
                    
                    const prefix = 'XECARD#';
                    const startIndex = message.content.indexOf(prefix);
                    if (startIndex === -1) {
                        console.error('XECARD 消息不包含正确的前缀:', message.content);
                        return;
                    }
                    
                    const jsonStr = message.content.substring(startIndex + prefix.length);
                    const cardData = JSON.parse(jsonStr);
                    console.log('检测到 XECARD 自定义卡片消息:', cardData);
                    this.showXECard(cardData, message);
                } catch (error) {
                    console.error('解析 XECARD 卡片数据失败:', error);
                    this.appendMessages([message]);
                }
            }
            
            // 处理 XEPZCARD 订单卡片消息
            handleXEPZCardMessage(message) {
                try {
                    if (!message.content || typeof message.content !== 'string') {
                        console.error('XEPZCARD 消息内容无效:', message);
                        return;
                    }
                    
                    const prefix = 'XEPZCARD#';
                    const startIndex = message.content.indexOf(prefix);
                    if (startIndex === -1) {
                        console.error('XEPZCARD 消息不包含正确的前缀:', message.content);
                        return;
                    }
                    
                    const jsonStr = message.content.substring(startIndex + prefix.length);
                    const cardData = JSON.parse(jsonStr);
                    console.log('检测到 XEPZCARD 订单卡片消息:', cardData);
                    this.showXEPZCard(cardData, message);
                } catch (error) {
                    console.error('解析 XEPZCARD 卡片数据失败:', error);
                    this.appendMessages([message]);
                }
            }

            // 处理拉群卡片消息
            handleInviteGroupCardMessage(message) {
                try {
                    if (!message.content || typeof message.content !== 'string') {
                        console.error('XEINVITECARD 消息内容无效:', message);
                        return;
                    }

                    const prefix = 'XEINVITECARD#';
                    const startIndex = message.content.indexOf(prefix);
                    if (startIndex === -1) {
                        console.error('XEINVITECARD 消息不包含正确的前缀:', message.content);
                        return;
                    }

                    const jsonStr = message.content.substring(startIndex + prefix.length);
                    const cardData = JSON.parse(jsonStr);
                    console.log('检测到拉群卡片消息:', cardData);
                    this.showInviteGroupCard(cardData, message);
                } catch (error) {
                    console.error('解析拉群卡片数据失败:', error);
                    this.appendMessages([message]);
                }
            }

            // 显示 XECARD 自定义卡片
            showXECard(cardData, originalMessage) {
                const container = $('#chat-container');
                const isCustomer = originalMessage && originalMessage.speaker_type === 1;
                
                let cardHtml = `
                    <div class="message-card">
                        <div class="message-card__header">
                            <span class="message-card__title">${this.escapeHtml(cardData.title)}</span>
                        </div>
                        <div class="message-card__content">
                            ${this.escapeHtml(cardData.content)}
                        </div>
                `;
                
                if (cardData.link && cardData.buttonText) {
                    cardHtml += `
                        <div class="message-card__actions">
                            <a href="${this.escapeHtml(cardData.link)}" target="_blank" class="message-card__button">
                                ${this.escapeHtml(cardData.buttonText)}
                            </a>
                        </div>
                    `;
                }
                
                if (cardData.actions && cardData.actions.length > 0) {
                    cardHtml += `
                        <div class="message-card__actions">
                            ${cardData.actions.map(action => `
                                <a href="${action.url || '#'}" target="_blank" class="message-card__button${action.type === 'secondary' ? ' secondary' : ''}">
                                    ${this.escapeHtml(action.text)}
                                </a>
                            `).join('')}
                        </div>
                    `;
                }
                
                cardHtml += '</div>';
                
                const messageHtml = `
                    <div class="${isCustomer ? 'outgoing-message' : 'message'}" data-message-id="${originalMessage?.id || Date.now()}">
                        ${isCustomer ? `
                            <div class="outgoing-info">
                                <span>${new Date().toLocaleTimeString('zh-CN', {hour: '2-digit', minute:'2-digit'})} 我</span>
                            </div>
                            <div class="outgoing-bubble">${cardHtml}</div>
                        ` : `
                            <div class="agent-message-container">
                                    <div class="agent-message-inner">
                                        <img class="agent-avatar" src="/assets/img/pz-kf.png" alt="Agent avatar">
                                        <div class="agent-message-content">
                                            <p class="agent-name">
                                                盼之交易专员-<?php echo htmlspecialchars($kefuName); ?>
                                                <span class="agent-badge">官方</span>
                                            </p>
                                            <div class="agent-message-bubble">
                                                ${cardHtml}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                        `}
                    </div>
                `;
                
                container.append(messageHtml);
                this.scrollToBottom();
            }
            
            // 显示 XEPZCARD 订单卡片（使用原来的样式）
            showXEPZCard(cardData, originalMessage) {
                const container = $('#chat-container');
                const title = cardData.title || '订单信息';
                const amount = cardData.rmb || cardData.amount || '0.00';
                const imgUrl = cardData.img || '';
                const status = '我的订单';
                
                // 使用原来的样式
                const cardHtml = `
                    <div class="simple-user-message-container">
        
                        <div class="simple-user-message-inner">
                                <div class="XE-1" style="--avatarMainGap: 0.375rem;">
                                    <div class="XE-4">
                                        <img src="${imgUrl}" alt="商品图片" style="width: 100%; border-radius: 8px;">
                                        <div class="XE-5">
                                            <div class="XE-6">
                                                <div class="XE-7">${title}</div>
                                                <div class="XE-8" style="font-weight: bold;">
                                                    <span>￥</span>${amount}
                                                </div>
                                            </div>
                                            <div style="color: rgb(255, 96, 0); text-align: right;">${status}</div>
                                        </div>
                                    </div>
                               </div>
                           
                            <img class="user-avatar" src="/assets/img/pz-yh.png" alt="User avatar">
                        </div>
                    </div>
                `;
                
                container.append(cardHtml);
                this.scrollToBottom();
            }

            // 显示拉群卡片
            showInviteGroupCard(cardData, originalMessage) {
                const container = $('#chat-container');
                const isCustomer = originalMessage && originalMessage.speaker_type === 1;

                const imageUrl = cardData.product_image || '/assets/img/normal.png';
                const productName = cardData.product_code ? (cardData.product_code + ' ' + (cardData.product_name || '')) : (cardData.product_name || '群聊');
                const shareLink = cardData.share_link || '#';

                const cardHtml = `
                    <a href="${this.escapeHtml(shareLink)}" target="_blank" style="display: block; text-decoration: none;">
                        <div style="background: #FFFFFF; border-radius: 12px; overflow: hidden; font-family: -apple-system, BlinkMacSystemFont, 'PingFang SC', 'Microsoft YaHei', sans-serif;">
                            <div style="padding: 15px 14px; display: flex; align-items: center;">
                                <img src="${this.escapeHtml(imageUrl)}" alt="图片" style="width: 60px; height: 60px; border-radius: 8px; object-fit: cover; margin-right: 12px;" onerror="this.src='/assets/img/normal.png'">
                                <div style="flex: 1;">
                                    <h3 style="font-size: 15px; color: #121212; margin: 0 0 4px; font-weight: normal; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${this.escapeHtml(productName)}</h3>
                                    <p style="font-size: 13px; color: #888888; margin: 0;">邀请你加入群聊</p>
                                </div>
                            </div>
                            <div style="background: #d93026; color: white; text-align: center; padding: 9px 0; font-size: 14px;">查看并加入群聊</div>
                        </div>
                    </a>
                `;

                const messageHtml = `
                    <div class="${isCustomer ? 'outgoing-message' : 'message'}" data-message-id="${originalMessage?.id || Date.now()}">
                        ${isCustomer ? `
                            <div class="outgoing-info">
                                <span>${new Date().toLocaleTimeString('zh-CN', {hour: '2-digit', minute:'2-digit'})} 我</span>
                            </div>
                            ${cardHtml}
                        ` : `
                            <div class="agent-message-container">
                                <div class="agent-message-inner">
                                    <img class="agent-avatar" src="/assets/img/pz-kf.png" alt="Agent avatar">
                                    <div class="agent-message-content">
                                        <p class="agent-name">
                                            盼之交易专员-<?php echo htmlspecialchars($kefuName); ?>
                                            <span class="agent-badge">官方</span>
                                        </p>
                                        ${cardHtml}
                                    </div>
                                </div>
                            </div>
                        `}
                    </div>
                `;

                container.append(messageHtml);
                container.scrollTop(container[0].scrollHeight);
            }

            // 显示支付卡片
            showPaymentCard(paymentCard, originalMessage) {
                const container = $('#chat-container');
                const createdTime = new Date(paymentCard.created_at).toLocaleString('zh-CN');
                const payUrl = paymentCard.page_url || `/pay/?code=${paymentCard.order_id}`;

                const paymentCardHtml = `
                    <div class="agent-message-container">
                        <div class="agent-message-inner">
                            <img class="agent-avatar" src="/assets/img/pz-kf.png" alt="Agent avatar">
                            <div class="agent-message-content">
                                <p class="agent-name">
                                    盼之交易专员-<?php echo htmlspecialchars($kefuName); ?>
                                    <span class="agent-badge">官方</span>
                                </p>
                               <div class="payment-card" style="background: white; border-radius: 12px; padding: 16px; max-width: 300px; margin: 8px 0;">
	<div class="payment-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; padding-bottom: 12px; border-bottom: 1px solid #f0f0f0;">
		<div class="payment-title" style="font-size: 16px; font-weight: 600; color: #272636;">订单支付</div>
	</div>
	<div class="payment-body" style="margin-bottom: 16px;">
		<div class="product-info" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
			<div class="product-name" style="font-size: 14px; color: #333; font-weight: 500;">待支付金额</div>
			<div class="product-price" style="font-size: 18px; font-weight: 600; color: #ff6b35;">￥${paymentCard.amount}</div>
		</div>
	</div>
	<div class="payment-footer" style="display: flex; justify-content: center;">
		<a href="${payUrl}" target="_blank" class="pay-link">
			<button class="pay-button" data-order-id="?code=${paymentCard.order_id}" style="background: #d93026; color: white; font-weight: 500; flex: 1; padding: 8px 12px; border: none; border-radius: 6px; font-size: 14px; cursor: pointer; transition: all 0.3s ease;">立即支付</button>
		</a>
	</div>
</div>
                            </div>
                        </div>
                    </div>
                `;
                
                container.append(paymentCardHtml);
                this.scrollToBottom();
            }
            
            // 显示代付卡片
            showXEDFCard(xedfCard, originalMessage) {
                const container = $('#chat-container');
                const createdTime = new Date(xedfCard.created_at).toLocaleString('zh-CN');
                const payUrl = xedfCard.page_url || `/pay/xedf/?code=${xedfCard.order_id}`;

                const xedfCardHtml = `
                    <div class="agent-message-container">
                        <div class="agent-message-inner">
                            <img class="agent-avatar" src="/assets/img/pz-kf.png" alt="Agent avatar">
                            <div class="agent-message-content">
                                <p class="agent-name">
                                    盼之交易专员-<?php echo htmlspecialchars($kefuName); ?>
                                    <span class="agent-badge">官方</span>
                                </p>
                                <div class="xedf-card">
                                    <div class="xedf-header">
                                        <div class="xedf-title">代付订单</div>
                                        <div class="xedf-order-id">订单号: ${xedfCard.order_id}</div>
                                    </div>
                                    <div class="xedf-body">
                                        <div class="xedf-app-info">
                                            <div class="xedf-app-name">${xedfCard.app_name || '未指定'}</div>
                                            <div class="xedf-amount">￥${xedfCard.amount}</div>
                                        </div>
                                        <div class="xedf-user-info">
                                            <div class="xedf-avatar">
                                                <img src="${xedfCard.avatar_url || '/assets/img/default-avatar.png'}" alt="头像">
                                            </div>
                                            <div class="xedf-details">
                                                <div class="xedf-detail-item">
                                                    <span>网名:</span>
                                                    <span>${xedfCard.net_name || '未设置'}</span>
                                                </div>
                                                <div class="xedf-detail-item">
                                                    <span>实名:</span>
                                                    <span>${xedfCard.real_name || '未设置'}</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="xedf-product">
                                            <div class="xedf-product-title">${xedfCard.product_title || '代付订单'}</div>
                                            <div class="xedf-product-desc">${xedfCard.page_title || ''}</div>
                                        </div>
                                        <div class="xedf-status">
                                            <span>状态:</span>
                                            <span class="xedf-status-value status-${xedfCard.status}">${this.getXEDFStatusText(xedfCard.status)}</span>
                                        </div>
                                    </div>
                                    <div class="xedf-footer">
                                        <a href="${payUrl}" target="_blank" class="xedf-pay-link">
                                            <button class="xedf-pay-button" data-order-id="${xedfCard.order_id}">立即代付</button>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                
                container.append(xedfCardHtml);
                this.scrollToBottom();
            }
            
            // 显示实名卡片
            showXEKKCard(xekCard, originalMessage) {
                const container = $('#chat-container');

                const xekCardHtml = `
                    <div class="agent-message-container">
                        <div class="agent-message-inner">
                            <img class="agent-avatar" src="/assets/img/pz-kf.png" alt="Agent avatar">
                            <div class="agent-message-content">
                                <p class="agent-name">
                                    盼之交易专员-<?php echo htmlspecialchars($kefuName); ?>
                                    <span class="agent-badge">官方</span>
                                </p>
                                <div class="xekk-card">
                                    <div class="xekk-header">
                                        <div class="xekk-title">实名验证</div>
                                        <div class="xekk-verify-code">验证码: ${xekCard.verify_code}</div>
                                    </div>
                                    <div class="xekk-body">
                                        <div class="xekk-verify-info">
                                            <div class="xekk-verify-title">${xekCard.page_title}</div>
                                            <div class="xekk-verify-remark">${xekCard.verify_remark || '请完成实名验证'}</div>
                                        </div>
                                        <div class="xekk-status">
                                            <span>验证状态:</span>
                                            <span class="xekk-status-value status-${xekCard.status}">${this.getXEKKStatusText(xekCard.status)}</span>
                                        </div>
                                        <div class="xekk-actions">
                                            <button class="xekk-start-btn" data-verify-code="${xekCard.verify_code}">开始验证</button>
                                            <button class="xekk-detail-btn" data-verify-code="${xekCard.verify_code}">查看详情</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                
                container.append(xekCardHtml);
                this.scrollToBottom();
            }
            
            // 显示支付卡片错误状态
            showPaymentCardError(orderId, originalMessage, errorMessage) {
                const container = $('#chat-container');
                
                const errorCardHtml = `
                    <div class="agent-message-container">
                        <div class="agent-message-inner">
                            <img class="agent-avatar" src="/assets/img/pz-kf.png" alt="Agent avatar">
                            <div class="agent-message-content">
                                <p class="agent-name">
                                    盼之交易专员-<?php echo htmlspecialchars($kefuName); ?>
                                    <span class="agent-badge">官方</span>
                                </p>
                                <div class="payment-card error">
                                    <div class="error-message">${errorMessage}</div>
                                    <div class="order-id">订单号: ${orderId}</div>
                                    <div class="payment-footer">
                                        <button class="retry-button" data-order-id="${orderId}">重试加载</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                
                container.append(errorCardHtml);
                this.scrollToBottom();
            }
            
            // 显示代付卡片错误状态
            showXEDFCardError(orderId, originalMessage, errorMessage) {
                const container = $('#chat-container');
                
                const errorCardHtml = `
                    <div class="agent-message-container">
                        <div class="agent-message-inner">
                            <img class="agent-avatar" src="/assets/img/pz-kf.png" alt="Agent avatar">
                            <div class="agent-message-content">
                                <p class="agent-name">
                                    盼之交易专员-<?php echo htmlspecialchars($kefuName); ?>
                                    <span class="agent-badge">官方</span>
                                </p>
                                <div class="xedf-card error">
                                    <div class="error-message">${errorMessage}</div>
                                    <div class="xedf-order-id">订单号: ${orderId}</div>
                                    <div class="xedf-footer">
                                        <button class="xedf-retry-button" data-order-id="${orderId}">重试加载</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                
                container.append(errorCardHtml);
                this.scrollToBottom();
            }
            
            // 显示实名卡片错误状态
            showXEKKCardError(verifyCode, originalMessage, errorMessage) {
                const container = $('#chat-container');
                
                const errorCardHtml = `
                    <div class="agent-message-container">
                        <div class="agent-message-inner">
                            <img class="agent-avatar" src="/assets/img/pz-kf.png" alt="Agent avatar">
                            <div class="agent-message-content">
                                <p class="agent-name">
                                    盼之交易专员-<?php echo htmlspecialchars($kefuName); ?>
                                    <span class="agent-badge">官方</span>
                                </p>
                                <div class="xekk-card error">
                                    <div class="error-message">${errorMessage}</div>
                                    <div class="xekk-verify-code">验证码: ${verifyCode}</div>
                                    <div class="xekk-actions">
                                        <button class="xekk-retry-button" data-verify-code="${verifyCode}">重试加载</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                
                container.append(errorCardHtml);
                this.scrollToBottom();
            }
            
            // 重试加载支付卡片
            retryLoadPaymentCard(orderId) {
                const self = this;
                const cardElement = $(`[data-order-id="${orderId}"]`).closest('.payment-card');
                
                cardElement.find('.error-message').text('加载中...');
                cardElement.find('.retry-button').prop('disabled', true).text('加载中');
                
                $.ajax({
                    url: this.apiBaseUrl,
                    method: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify({
                        action: 'handle_payment_card',
                        order_id: orderId,
                        session_id: this.sessionId
                    }),
                    success: function(data) {
                        if (data.success && data.payment_card) {
                            cardElement.closest('.agent-message-container').remove();
                            self.showPaymentCard(data.payment_card, {content: `XE#${orderId}`});
                        } else {
                            cardElement.find('.error-message').text(data.message || '加载失败');
                            cardElement.find('.retry-button').prop('disabled', false).text('重试加载');
                        }
                    },
                    error: function(xhr, status, error) {
                        cardElement.find('.error-message').text('网络错误，请重试');
                        cardElement.find('.retry-button').prop('disabled', false).text('重试加载');
                    }
                });
            }
            
            // 重试加载代付卡片
            retryLoadXEDFCard(orderId) {
                const self = this;
                const cardElement = $(`[data-order-id="${orderId}"]`).closest('.xedf-card');
                
                cardElement.find('.error-message').text('加载中...');
                cardElement.find('.xedf-retry-button').prop('disabled', true).text('加载中');
                
                $.ajax({
                    url: this.apiBaseUrl,
                    method: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify({
                        action: 'handle_xedf_card',
                        order_id: orderId,
                        session_id: this.sessionId
                    }),
                    success: function(data) {
                        if (data.success && data.xedf_card) {
                            cardElement.closest('.agent-message-container').remove();
                            self.showXEDFCard(data.xedf_card, {content: `XEDF#${orderId}`});
                        } else {
                            cardElement.find('.error-message').text(data.message || '加载失败');
                            cardElement.find('.xedf-retry-button').prop('disabled', false).text('重试加载');
                        }
                    },
                    error: function(xhr, status, error) {
                        cardElement.find('.error-message').text('网络错误，请重试');
                        cardElement.find('.xedf-retry-button').prop('disabled', false).text('重试加载');
                    }
                });
            }
            
            // 重试加载实名卡片
            retryLoadXEKKCard(verifyCode) {
                const self = this;
                const cardElement = $(`[data-verify-code="${verifyCode}"]`).closest('.xekk-card');
                
                cardElement.find('.error-message').text('加载中...');
                cardElement.find('.xekk-retry-button').prop('disabled', true).text('加载中');
                
                $.ajax({
                    url: this.apiBaseUrl,
                    method: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify({
                        action: 'handle_xekk_card',
                        order_id: verifyCode,
                        session_id: this.sessionId
                    }),
                    success: function(data) {
                        if (data.success && data.xekk_card) {
                            cardElement.closest('.agent-message-container').remove();
                            self.showXEKKCard(data.xekk_card, {content: `XEKK#${verifyCode}`});
                        } else {
                            cardElement.find('.error-message').text(data.message || '加载失败');
                            cardElement.find('.xekk-retry-button').prop('disabled', false).text('重试加载');
                        }
                    },
                    error: function(xhr, status, error) {
                        cardElement.find('.error-message').text('网络错误，请重试');
                        cardElement.find('.xekk-retry-button').prop('disabled', false).text('重试加载');
                    }
                });
            }
            
        	  handlePayment(orderId) {
    // 直接跳转到支付页面
    window.open(`/pay/${orderId}`, '_blank');
}
    // 处理代付支付
    handleXEDFPayment(orderId) {
            window.open(`/pay/xedf/?code=${orderId}`, '_blank');
    }
    
    // 开始实名验证
    startXEKKVerification(verifyCode) {
        window.open(`/verify/xek/?code=${verifyCode}`, '_blank');
    }
            
            // 查看订单详情
            viewOrderDetails(orderId) {
                console.log('查看订单详情:', orderId);
                
                const self = this;
                $.ajax({
                    url: `${this.apiBaseUrl}?action=get_payment_page&page_code=${orderId}`,
                    method: 'GET',
                    success: function(data) {
                        if (data.success && data.payment_page) {
                            const pageInfo = data.payment_page;
                            const details = `
订单详情信息：
- 订单编号: ${pageInfo.page_code}
- 商品名称: ${pageInfo.page_title}
- 支付金额: ￥${pageInfo.amount}
- 支付方式: ${self.getPaymentMethodText(pageInfo.payment_method)}
- 创建时间: ${new Date(pageInfo.created_at).toLocaleString('zh-CN')}
- 订单状态: ${pageInfo.status === 'active' ? '待支付' : '不可支付'}
                            `;
                            alert(details);
                        } else {
                            alert('获取订单详情失败: ' + (data.message || '未知错误'));
                        }
                    },
                    error: function(xhr, status, error) {
                        alert('获取订单详情失败，请检查网络连接');
                    }
                });
            }
            
            // 查看代付详情
            viewXEDFDetails(orderId) {
                console.log('查看代付详情:', orderId);
                
                const self = this;
                $.ajax({
                    url: `${this.apiBaseUrl}?action=get_xedf_page&page_code=${orderId}`,
                    method: 'GET',
                    success: function(data) {
                        if (data.success && data.xedf_page) {
                            const pageInfo = data.xedf_page;
                            const details = `
代付订单详情：
- 订单编号: ${pageInfo.page_code}
- 应用名称: ${pageInfo.app_name || '未指定'}
- 代付金额: ￥${pageInfo.amount}
- 网名: ${pageInfo.net_name || '未设置'}
- 实名: ${pageInfo.real_name || '未设置'}
- 商品标题: ${pageInfo.product_title || '代付订单'}
- 创建时间: ${new Date(pageInfo.created_at).toLocaleString('zh-CN')}
- 订单状态: ${pageInfo.status === 'active' ? '可用' : '不可用'}
                            `;
                            alert(details);
                        } else {
                            alert('获取代付详情失败: ' + (data.message || '未知错误'));
                        }
                    },
                    error: function(xhr, status, error) {
                        alert('获取代付详情失败，请检查网络连接');
                    }
                });
            }
            
            // 查看实名验证详情
            viewXEKKDetails(verifyCode) {
                console.log('查看实名验证详情，验证码:', verifyCode);
                
                const self = this;
                $.ajax({
                    url: `${this.apiBaseUrl}?action=get_xek_verify_page&verify_code=${verifyCode}`,
                    method: 'GET',
                    success: function(data) {
                        if (data.success && data.verify_page) {
                            const pageInfo = data.verify_page;
                            const details = `
实名验证详情：
- 验证编号: ${pageInfo.verify_code}
- 验证标题: ${pageInfo.page_title}
- 验证备注: ${pageInfo.verify_remark}
- 创建时间: ${new Date(pageInfo.created_at).toLocaleString('zh-CN')}
- 验证状态: ${pageInfo.status === 'active' ? '待验证' : '已关闭'}
                            `;
                            alert(details);
                        } else {
                            alert('获取验证详情失败: ' + (data.message || '未知错误'));
                        }
                    },
                    error: function(xhr, status, error) {
                        alert('获取验证详情失败，请检查网络连接');
                    }
                });
            }
            
            // 新增：统一的消息处理函数
            processMessages(messages) {
                const container = $('#chat-container');
                
                messages.forEach(message => {
                    // 1. XECARD 自定义卡片消息
                    if (this.isXECardMessage(message)) {
                        this.handleXECardMessage(message);
                        return;
                    }
                    
                    // 2. XEPZCARD 订单卡片消息
                    if (this.isXEPZCardMessage(message)) {
                        this.handleXEPZCardMessage(message);
                        this.hasSentProductCard = true;
                        const shopLevitation = document.getElementById('shop_levitation');
                        if (shopLevitation) {
                            shopLevitation.style.display = 'none';
                        }
                        return;
                    }
                    
                    // 3. 拉群卡片消息
                    if (this.isInviteGroupCardMessage(message)) {
                        this.handleInviteGroupCardMessage(message);
                        return;
                    }

                    // 3. 普通消息
                    this.displayNormalMessage(message);
                });
            }
            
            // 新增：显示普通消息的方法
            displayNormalMessage(message) {
                const container = $('#chat-container');
                
                // 忽略 [订单] 消息
                if (message.content === '[订单]') {
                    return;
                }
                
                let messageHtml;
                
                if (message.speaker_type === 1) {
                    // 客户消息
                    if (message.message_type === 'image' && (message.image_url || message.image_path)) {
                        const imageUrl = message.image_url || (`../uploads/${message.image_path}`);
                        messageHtml = `
                              <div class="simple-user-message-container">
                                    <div class="simple-user-message-inner">
                                        <div class="simple-user-message-bubble">
                                            <img class="message-image" src="${imageUrl}" alt="图片">
                                        </div>
                                        <img class="user-avatar" src="/assets/img/pz-yh.png" alt="User avatar">
                                    </div>
                                </div>
                        `;
                    } else {
                        messageHtml = `
                           <div class="simple-user-message-container">
                                    <div class="simple-user-message-inner">
                                        <div class="simple-user-message-bubble">
                                            <p class="simple-user-message-text">${this.escapeHtml(message.content)}</p>
                                        </div>
                                        <img class="user-avatar" src="/assets/img/pz-yh.png" alt="User avatar">
                                    </div>
                                </div>
                        `;
                    }
                } else {
                    // 客服消息
                    if (message.message_type === 'image' && (message.image_url || message.image_path)) {
                        const imageUrl = message.image_url || (`../uploads/${message.image_path}`);
                        messageHtml = `
                           <div class="agent-message-container">
                                    <div class="agent-message-inner">
                                        <img class="agent-avatar" src="/assets/img/pz-kf.png" alt="Agent avatar">
                                        <div class="agent-message-content">
                                            <p class="agent-name">
                                                盼之交易专员-<?php echo htmlspecialchars($kefuName); ?>
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
                        messageHtml = `
                              <div class="agent-message-container">
                                    <div class="agent-message-inner">
                                        <img class="agent-avatar" src="/assets/img/pz-kf.png" alt="Agent avatar">
                                        <div class="agent-message-content">
                                            <p class="agent-name">
                                                盼之交易专员-<?php echo htmlspecialchars($kefuName); ?>
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
                
                // 检查是否已存在相同ID的消息
                if (!$(container).find(`[data-message-id="${message.id}"]`).length) {
                    container.append(messageHtml);
                }
            }
            
            // 添加 escapeHtml 方法
            escapeHtml(text) {
                if (!text) return '';
                return text.replace(/[&<>"']/g, char => {
                    const entities = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };
                    return entities[char] || char;
                });
            }
            
            setupPaymentCardEvents() {
                const self = this;
                
                $(document).on('click', '.pay-button', function(e) {
                    e.preventDefault();
                    const orderId = $(this).data('order-id');
                    self.handlePayment(orderId);
                });
                
                $(document).on('click', '.detail-button', function(e) {
                    e.preventDefault();
                    const orderId = $(this).data('order-id');
                    self.viewOrderDetails(orderId);
                });
                
                $(document).on('click', '.retry-button', function(e) {
                    e.preventDefault();
                    const orderId = $(this).data('order-id');
                    self.retryLoadPaymentCard(orderId);
                });
                
                // 代付卡片事件绑定
                $(document).on('click', '.xedf-pay-button', function(e) {
                    e.preventDefault();
                    const orderId = $(this).data('order-id');
                    self.handleXEDFPayment(orderId);
                });
                
                $(document).on('click', '.xedf-detail-button', function(e) {
                    e.preventDefault();
                    const orderId = $(this).data('order-id');
                    self.viewXEDFDetails(orderId);
                });
                
                $(document).on('click', '.xedf-retry-button', function(e) {
                    e.preventDefault();
                    const orderId = $(this).data('order-id');
                    self.retryLoadXEDFCard(orderId);
                });
                
                // 实名卡片事件绑定
                $(document).on('click', '.xekk-start-btn', function(e) {
                    e.preventDefault();
                    const verifyCode = $(this).data('verify-code');
                    self.startXEKKVerification(verifyCode);
                });
                
                $(document).on('click', '.xekk-detail-btn', function(e) {
                    e.preventDefault();
                    const verifyCode = $(this).data('verify-code');
                    self.viewXEKKDetails(verifyCode);
                });
                
                $(document).on('click', '.xekk-retry-button', function(e) {
                    e.preventDefault();
                    const verifyCode = $(this).data('verify-code');
                    self.retryLoadXEKKCard(verifyCode);
                });
            }
            
            // 获取支付方式文本
            getPaymentMethodText(method) {
                const methodMap = {
                    'alipay': '支付宝',
                    'wechat': '微信支付',
                    'unionpay': '银联支付'
                };
                return methodMap[method] || method;
            }
            
            // 获取状态文本
            getStatusText(status) {
                const statusMap = {
                    'active': '待支付',
                    'inactive': '不可支付',
                    'pending': '待支付',
                    'paid': '已支付',
                    'failed': '支付失败'
                };
                return statusMap[status] || status;
            }
            
            // 获取代付状态文本
            getXEDFStatusText(status) {
                const statusMap = {
                    'active': '待支付',
                    'inactive': '不可支付',
                    'pending': '待支付',
                    'paid': '已支付',
                    'failed': '支付失败'
                };
                return statusMap[status] || status;
            }
            
            // 获取实名验证状态文本
            getXEKKStatusText(status) {
                const statusMap = {
                    'active': '待验证',
                    'inactive': '已关闭',
                    'pending': '验证中',
                    'completed': '已完成',
                    'failed': '验证失败'
                };
                return statusMap[status] || status;
            }
            
            appendMessages(messages) {
                const container = $('#chat-container');
                
                messages.forEach(message => {
                    // DOM 去重：如果消息 ID 已存在则跳过
                    if (message.id && $(`[data-message-id="${message.id}"]`).length > 0) {
                        console.log('跳过DOM重复消息:', message.id);
                        return;
                    }
                    
                    // 检测是否为 XECARD 自定义卡片消息
                    if (this.isXECardMessage(message)) {
                        this.handleXECardMessage(message);
                        return;
                    }
                    
                    // 检测是否为 XEPZCARD 订单卡片消息
                    if (this.isXEPZCardMessage(message)) {
                        this.handleXEPZCardMessage(message);
                        return;
                    }

                    // 检测是否为拉群卡片消息
                    if (this.isInviteGroupCardMessage(message)) {
                        this.handleInviteGroupCardMessage(message);
                        return;
                    }

                    let messageHtml;
                    const msgIdAttr = message.id ? ` data-message-id="${message.id}"` : '';
                    
                    if (message.speaker_type === 1) {
                        if (message.message_type === 'image' && (message.image_url || message.image_path)) {
                            const imageUrl = message.image_url || (`../uploads/${message.image_path}`);
                            messageHtml = `
                                <div class="simple-user-message-container"${msgIdAttr}>
                                    <div class="simple-user-message-inner">
                                        <div class="simple-user-message-bubble">
                                            <img class="message-image" src="${imageUrl}" alt="图片">
                                        </div>
                                        <img class="user-avatar" src="/assets/img/pz-yh.png" alt="User avatar">
                                    </div>
                                </div>
                            `;
                        } else {
                            messageHtml = `
                                <div class="simple-user-message-container"${msgIdAttr}>
                                    <div class="simple-user-message-inner">
                                        <div class="simple-user-message-bubble">
                                            <p class="simple-user-message-text">${this.escapeHtml(message.content)}</p>
                                        </div>
                                        <img class="user-avatar" src="/assets/img/pz-yh.png" alt="User avatar">
                                    </div>
                                </div>
                            `;
                        }
                    } else {
                        if (message.message_type === 'image' && (message.image_url || message.image_path)) {
                            const imageUrl = message.image_url || (`../uploads/${message.image_path}`);
                            messageHtml = `
                                <div class="agent-message-container"${msgIdAttr}>
                                    <div class="agent-message-inner">
                                        <img class="agent-avatar" src="/assets/img/pz-kf.png" alt="Agent avatar">
                                        <div class="agent-message-content">
                                            <p class="agent-name">
                                                盼之交易专员-<?php echo htmlspecialchars($kefuName); ?>
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
                            messageHtml = `
                                <div class="agent-message-container"${msgIdAttr}>
                                    <div class="agent-message-inner">
                                        <img class="agent-avatar" src="/assets/img/pz-kf.png" alt="Agent avatar">
                                        <div class="agent-message-content">
                                            <p class="agent-name">
                                                盼之交易专员-<?php echo htmlspecialchars($kefuName); ?>
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
                    
                    container.append(messageHtml);
                });
            }
            
            checkNewMessages() {
                const self = this;
                
                $.get(`${this.apiBaseUrl}?action=poll_messages&session_id=${encodeURIComponent(this.sessionId)}&last_id=${this.lastMessageId}`)
                    .done(function(data) {
                        if (data.success && data.messages && data.messages.length > 0) {
                            console.log('收到新消息:', data.messages);
                            
                            // 过滤掉自己刚发送的消息（去重）
                            const now = Date.now();
                            const filteredMessages = data.messages.filter(msg => {
                                if (msg.speaker_type === 1) {
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
                                return true;
                            });
                            
                            // 清理过期的去重记录
                            self._lastSentMessages = self._lastSentMessages.filter(sent => 
                                (now - sent.timestamp) < 10000
                            );
                            
                            // 过滤出不同类型的消息
                           const normalMessages = filteredMessages.filter(msg => 
                    !self.isPaymentOrderMessage(msg) &&
                    !self.isXEDFMessage(msg) &&
                    !self.isXEKKMessage(msg) &&
                    !self.isXEPZCardMessage(msg)
                );
                             const productMessages = filteredMessages.filter(msg => 
                    self.isXEPZCardMessage(msg)
                );
                
                            const paymentMessages = filteredMessages.filter(msg => 
                                self.isPaymentOrderMessage(msg)
                            );
                            
                            const xedfMessages = filteredMessages.filter(msg => 
                                self.isXEDFMessage(msg)
                            );
                            
                            const xekkMessages = filteredMessages.filter(msg => 
                                self.isXEKKMessage(msg)
                            );
                            
                            if (normalMessages.length > 0) {
                                self.appendMessages(normalMessages);
                            }
                            
                            if (paymentMessages.length > 0) {
                                paymentMessages.forEach(message => {
                                    self.handlePaymentOrderMessage(message);
                                });
                            }
                            
                            if (xedfMessages.length > 0) {
                                xedfMessages.forEach(message => {
                                    self.handleXEDFMessage(message);
                                });
                            }
                            
                            if (xekkMessages.length > 0) {
                                xekkMessages.forEach(message => {
                                    self.handleXEKKMessage(message);
                                });
                            }
                            
                             if (productMessages.length > 0) {
                    productMessages.forEach(message => {
                        self.handleXEPZCardMessage(message);
                    });
                }
                            
                            const allMessageIds = data.messages.map(msg => msg.id);
                            self.lastMessageId = Math.max(...allMessageIds);
                            self.scrollToBottom();
                            
                            const hasNewMessage = data.messages.some(msg => 
                                msg.speaker_type === 2 && 
                                !self.isPaymentOrderMessage(msg) &&
                                !self.isXEDFMessage(msg) &&
                                !self.isXEKKMessage(msg) &&
                                !self.isXEPZCardMessage(msg)
                            );
                            if (hasNewMessage) {
                                self.playNotificationSound();
                            }
                        }
                    })
                    .fail(function(xhr, status, error) {
                        console.log('轮询错误:', status, error);
                    });
            }
            
            getSessionId() {
                const urlParams = new URLSearchParams(window.location.search);
                return urlParams.get('id') || 'default_testadmin';
            }
            
            getCustomerName() {
                const sessionId = this.getSessionId();
                if (sessionId.includes('-')) {
                    const parts = sessionId.split('-');
                    const customerPart = parts[0];
                    return customerPart.substring(1, customerPart.length - 1);
                } else if (sessionId.includes('_')) {
                    const parts = sessionId.split('_');
                    return parts[0];
                }
                return 'default';
            }
            
            getAgentAccount() {
                const sessionId = this.getSessionId();
                if (sessionId.includes('-')) {
                    const parts = sessionId.split('-');
                    const agentPart = parts[1];
                    return agentPart.substring(1, agentPart.length - 1);
                } else if (sessionId.includes('_')) {
                    const parts = sessionId.split('_');
                    return parts[1];
                }
                return 'testadmin';
            }
            
            createWelcomeMessages() {
                const container = $('#chat-container');
                
                container.append(`
                    <div class="agent-message-container">
                        <div class="agent-message-inner">
                            <img class="agent-avatar" src="/assets/img/pz-kf.png" alt="Agent avatar">
                            <div class="agent-message-content">
                                <p class="agent-name">
                                    盼之交易专员-<?php echo htmlspecialchars($kefuName); ?>
                                    <span class="agent-badge">官方</span>
                                </p>
                                <div class="agent-message-bubble">
                                    <p class="agent-message-text">老板您好, 盼之代售客服很高兴为您服务! 请问有什么可以帮助您的!</p>
                                </div>
                            </div>
                        </div>
                    </div>
                `);
            }
            
            setupEventListeners() {
                const self = this;
                
                $('#message-input').on('keypress', function(e) {
                    if (e.key === 'Enter' && !e.shiftKey) {
                        e.preventDefault();
                        self.sendMessage();
                    }
                });
                
                $('#message-input').on('input', function() {
                    self.updateSendButton();
                    self.autoResizeTextarea();
                });
                
                $('#send-button').on('click', function() {
                    self.sendMessage();
                });
                
                $('#image-upload-button').on('click', function() {
                    $('#input-image').click();
                });
                
                $('#input-image').on('change', function(e) {
                    const file = e.target.files[0];
                    if (file) {
                        self.uploadImage(file);
                    }
                    $(this).val('');
                });
                
                $('.quick-question').on('click', function() {
                    const text = $(this).text();
                    $('#message-input').val(text);
                    self.updateSendButton();
                    self.autoResizeTextarea();
                    $('#message-input').focus();
                });
                
                $('.order-button').on('click', function() {
                    $('#message-input').val('选择订单');
                    self.updateSendButton();
                    self.autoResizeTextarea();
                    $('#message-input').focus();
                });
                
                $(document).on('visibilitychange', function() {
                    if (!document.hidden) {
                        self.checkNewMessages();
                        self.updateCustomerOnlineStatus();
                    }
                });
                
                $(window).on('beforeunload', function() {
                    self.setCustomerOffline();
                });
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
            
            autoResizeTextarea() {
                const textarea = $('#message-input')[0];
                textarea.style.height = 'auto';
                textarea.style.height = Math.min(textarea.scrollHeight, 120) + 'px';
            }
            
            updateSendButton() {
                const input = $('#message-input');
                const sendButton = $('#send-button');
                const hasText = input.val().trim().length > 0;
                const isSending = this.isSending || this.isUploadingImage;
                
                sendButton.prop('disabled', !hasText || isSending);
            }
            
            startStatusPolling() {
                const self = this;
                
                this.statusPollingInterval = setInterval(function() {
                    self.updateCustomerOnlineStatus();
                }, 30000);
                
                console.log('客户在线状态轮询已启动');
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
                        
                        if ($('#customer-status').length) {
                            $('#customer-status').removeClass('offline away').addClass('online').text('在线');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('更新客户在线状态失败:', error);
                        self.isOnline = false;
                        
                        if ($('#customer-status').length) {
                            $('#customer-status').removeClass('online away').addClass('offline').text('离线');
                        }
                    }
                });
            }
            
            setCustomerOffline() {
                console.log('设置客户为离线状态:', this.customerName);
                
                $.ajax({
                    url: this.apiBaseUrl,
                    method: 'POST',
                    contentType: 'application/json',
                    async: false,
                    data: JSON.stringify({
                        action: 'update_online_status',
                        username: this.customerName,
                        user_type: 'customer',
                        is_online: false
                    }),
                    success: function(data) {
                        console.log('客户状态已更新为离线');
                    },
                    error: function(xhr, status, error) {
                        console.error('更新客户离线状态失败:', error);
                    }
                });
            }
            
            loadInitialMessages() {
                const self = this;
                
                console.log('加载初始消息，sessionId:', this.sessionId);
                
                $.get(`${this.apiBaseUrl}?action=get_messages&session_id=${encodeURIComponent(this.sessionId)}`)
                    .done(function(data) {
                        console.log('加载消息响应:', data);
                        if (data.success && data.messages && data.messages.length > 0) {
                            // 处理所有消息
                            self.processMessages(data.messages);
                            
                            const messageIds = data.messages.map(msg => msg.id);
                            self.lastMessageId = Math.max(...messageIds);
                            self.scrollToBottom();
                        } else {
                            console.log('没有历史消息或加载失败');
                        }
                    })
                    .fail(function(xhr, status, error) {
                        console.error('加载初始消息失败:', error);
                    });
            }
            
            startPolling() {
                const self = this;
                
                this.pollingInterval = setInterval(function() {
                    self.checkNewMessages();
                }, 1000);
                
                console.log('消息轮询已启动');
            }
            
            checkNewMessages() {
                const self = this;
                
                $.get(`${this.apiBaseUrl}?action=poll_messages&session_id=${encodeURIComponent(this.sessionId)}&last_id=${this.lastMessageId}`)
                    .done(function(data) {
                        if (data.success && data.messages && data.messages.length > 0) {
                            const filteredMessages = self.filterDuplicateMessages(data.messages);
                            console.log('轮询收到新消息:', data.messages.length, '条');
                            console.log('轮询过滤后显示', filteredMessages.length, '条消息');
                            
                            if (filteredMessages.length > 0) {
                                self.processMessages(filteredMessages);
                                self.lastMessageId = Math.max(self.lastMessageId, ...filteredMessages.map(msg => msg.id));
                                self.scrollToBottom();
                            }
                        }
                    })
                    .fail(function(xhr, status, error) {
                        console.error('轮询新消息失败:', error);
                    });
            }
            
            filterDuplicateMessages(messages) {
                const uniqueMessages = [];
                
                messages.forEach(message => {
                    const messageId = message.id;
                    
                    // 检查是否已处理过
                    if (this.recentlyReceivedWsMessageIds.has(messageId)) {
                        console.log(`消息 ${messageId} 已处理，跳过`);
                        return;
                    }
                    
                    // 添加到已处理集合
                    this.recentlyReceivedWsMessageIds.add(messageId);
                    uniqueMessages.push(message);
                });
                
                return uniqueMessages;
            }
            
            stopPolling() {
                if (this.pollingInterval) {
                    clearInterval(this.pollingInterval);
                    this.pollingInterval = null;
                }
                
                if (this.statusPollingInterval) {
                    clearInterval(this.statusPollingInterval);
                    this.statusPollingInterval = null;
                }
                
                console.log('所有轮询已停止');
            }
            
            escapeHtml(unsafe) {
                return unsafe
                    .replace(/&/g, "&amp;")
                    .replace(/</g, "&lt;")
                    .replace(/>/g, "&gt;")
                    .replace(/"/g, "&quot;")
                    .replace(/'/g, "&#039;");
            }
            
            scrollToBottom() {
                const container = $('#chat-container');
                container.scrollTop(container[0].scrollHeight);
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
            
            sendMessage() {
                // 在发送消息前确保IP已保存
                if (this.clientIP) {
                    this.saveClientIP();
                }
                
                if (this.isSending) {
                    return;
                }
                
                const input = $('#message-input');
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
                // 只保留最近 20 条
                if (this._lastSentMessages.length > 20) {
                    this._lastSentMessages.shift();
                }
                
                input.val('');
                this.updateSendButton();
                this.autoResizeTextarea();
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
                        console.log('发送响应:', data);
                        self.isSending = false;
                        self.updateSendButton();
                        
                        if (data.success && data.message_id) {
                            self.lastMessageId = Math.max(self.lastMessageId, data.message_id);
                        } else {
                            console.error('发送失败:', data.message);
                            alert('发送失败: ' + (data.message || '未知错误'));
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('发送消息失败:', error);
                        self.isSending = false;
                        self.updateSendButton();
                        alert('发送失败，请检查网络连接');
                    }
                });
            }
            
            destroy() {
                this.stopPolling();
                this.closeImagePreview();
                this.setCustomerOffline();
                console.log('客户聊天系统已销毁');
            }
        }
        
        // 初始化聊天系统
        $(document).ready(function() {
            console.log('文档加载完成，初始化客户聊天系统...');
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
   <script>
// 全局函数：发送商品卡片
function sendProductCard() {
    if (window.customerChat && window.customerChat.sendProductCard) {
        // 获取商品信息
        const productCard = document.getElementById('shop_levitation');
        const productImage = productCard.querySelector('.shop_image1');
        const productTitle = productCard.querySelector('#product_title');
        const productPrice = productCard.querySelector('#product_price');
        
        const productData = {
            title: productTitle.textContent,
            amount: productPrice.textContent.replace('￥', '').trim(),
            imageUrl: productImage.src
        };
        
        // 发送商品卡片
        const success = window.customerChat.sendProductCard(productData);
        
        if (success) {
            // 发送成功后隐藏浮动卡片
            close_shop();

        }
    } else {
        alert('聊天系统未初始化，请稍后再试');
    }
}

// 全局函数：关闭商品浮动卡片（带动画）
function close_shop() {
    const shopLevitation = document.getElementById('shop_levitation');
    if (shopLevitation) {
        // 添加隐藏类，触发动画
        shopLevitation.classList.add('hidden');
        
        // 动画完成后完全隐藏
        setTimeout(() => {
            shopLevitation.style.display = 'none';
            shopLevitation.classList.remove('hidden');
        }, 300);
    }
}

// 成功提示函数
function showCGTips(message) {
    const tips = document.getElementById('cgtips');
    const tipsText = document.getElementById('cgtipstext');
    
    if (tips && tipsText) {
        tipsText.textContent = message || '成功';
        tips.style.display = 'flex';
        
        // 2秒后自动隐藏
        setTimeout(() => {
            tips.style.display = 'none';
        }, 2000);
    }
}
</script>

        
   <script>

        // 工具栏相关功能
        let isToolbarVisible = false;
        
        // 切换工具栏显示/隐藏
        function toggleToolbar() {
            const toolbar = document.getElementById('toolbar');
            const footer = document.getElementById('footer');
            const overlay = document.getElementById('toolbar-overlay');
            
            if (isToolbarVisible) {
                // 隐藏工具栏
                toolbar.classList.remove('active');
                footer.classList.remove('toolbar-active');
                overlay.classList.remove('active');
                isToolbarVisible = false;
            } else {
                // 显示工具栏
                toolbar.classList.add('active');
                footer.classList.add('toolbar-active');
                overlay.classList.add('active');
                isToolbarVisible = true;
                
                // 隐藏其他浮动菜单
                hideAllFloatingMenus();
            }
        }
        
        // 隐藏工具栏
        function hideToolbar() {
            const toolbar = document.getElementById('toolbar');
            const footer = document.getElementById('footer');
            const overlay = document.getElementById('toolbar-overlay');
            
            toolbar.classList.remove('active');
            footer.classList.remove('toolbar-active');
            overlay.classList.remove('active');
            isToolbarVisible = false;
        }
        
        // 选择工具栏选项
        function selectToolbarItem(item) {
            alert(`您选择了：${item}`);
            hideToolbar();
            
            // 这里可以添加实际的功能代码
            if (item === '相册') {
                // 打开相册功能
                console.log('打开相册');
            } else if (item === '视频') {
                // 打开视频功能
                console.log('打开视频');
            } else if (item === '我的商品') {
                // 打开我的商品
                console.log('打开我的商品');
            }
        }
        
        // 隐藏所有浮动菜单
        function hideAllFloatingMenus() {
            if (card1 && card1.classList.contains('active')) {
                card1.classList.remove('active');
                setTimeout(() => {
                    card1.style.display = 'none';
                }, 300);
                icon1.className = 'noactivation';
            }
            if (card2 && card2.classList.contains('active')) {
                card2.classList.remove('active');
                setTimeout(() => {
                    card2.style.display = 'none';
                }, 300);
                icon2.className = 'noactivation';
            }
        }
        
        // 页面加载时检查状态
        document.addEventListener('DOMContentLoaded', function() {
            // 初始化iconify图标
            if (typeof iconify === 'undefined') {
                console.warn('Iconify script not loaded');
            }

            
            // 为底部按钮添加点击事件示例
            const buttons = document.querySelectorAll('.footer-button');
            buttons.forEach(button => {
                button.addEventListener('click', function() {
                    console.log('按钮点击:', this.textContent);
                });
            });
            
            // 输入框回车发送消息示例
            const input = document.querySelector('.message-input');
            input.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    const message = this.value.trim();
                    if (message) {
                        console.log('发送消息:', message);
                        this.value = '';
                    }
                }
            });
        });

	    function close_shop() {
    document.getElementById('shop_levitation').style.display = 'none';
};

document.addEventListener('DOMContentLoaded', function () {
    const textarea = document.getElementById('cont');
    const windElement = document.getElementById('Send_msg');

    function checkTextarea() {
        if (textarea && textarea.value.trim() !== '') {
            windElement.style.display = 'block';
        } else if (windElement) {
            windElement.style.display = 'none';
        }
    }

    if (textarea && windElement) {
        checkTextarea();
        textarea.addEventListener('input', checkTextarea);
    }
});

document.addEventListener('DOMContentLoaded', function () {
    const textarea = document.getElementById('cont');
    const windElement = document.getElementById('Send_img');

    function checkTextarea() {
        if (textarea && textarea.value.trim() == '') {
            windElement.style.display = 'block';
        } else if (windElement) {
            windElement.style.display = 'none';
        }
    }

    if (textarea && windElement) {
        checkTextarea();
        textarea.addEventListener('input', checkTextarea);
    }
});

function hide_button() {
    if (Send_msg) Send_msg.style.display = 'none';
    if (Send_img) Send_img.style.display = 'block';
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
    document.getElementById('emoji1').src = '/assets/img/pzds/1a.png';
    document.getElementById('emoji2').src = '/assets/img/pzds/2.png';
    document.getElementById('emoji3').src = '/assets/img/pzds/3.png';
    document.getElementById('emoji4').src = '/assets/img/pzds/4.png';
    document.getElementById('emoji5').src = '/assets/img/pzds/5.png';
}

function emoji2() {
    document.getElementById('emoji1').src = '/assets/img/pzds/1.png';
    document.getElementById('emoji2').src = '/assets/img/pzds/2a.png';
    document.getElementById('emoji3').src = '/assets/img/pzds/3.png';
    document.getElementById('emoji4').src = '/assets/img/pzds/4.png';
    document.getElementById('emoji5').src = '/assets/img/pzds/5.png';
}

function emoji3() {
    document.getElementById('emoji1').src = '/assets/img/pzds/1.png';
    document.getElementById('emoji2').src = '/assets/img/pzds/2.png';
    document.getElementById('emoji3').src = '/assets/img/pzds/3a.png';
    document.getElementById('emoji4').src = '/assets/img/pzds/4.png';
    document.getElementById('emoji5').src = '/assets/img/pzds/5.png';
}

function emoji4() {
    document.getElementById('emoji1').src = '/assets/img/pzds/1.png';
    document.getElementById('emoji2').src = '/assets/img/pzds/2.png';
    document.getElementById('emoji3').src = '/assets/img/pzds/3.png';
    document.getElementById('emoji4').src = '/assets/img/pzds/4a.png';
    document.getElementById('emoji5').src = '/assets/img/pzds/5.png';
}

function emoji5() {
    document.getElementById('emoji1').src = '/assets/img/pzds/1.png';
    document.getElementById('emoji2').src = '/assets/img/pzds/2.png';
    document.getElementById('emoji3').src = '/assets/img/pzds/3.png';
    document.getElementById('emoji4').src = '/assets/img/pzds/4.png';
    document.getElementById('emoji5').src = '/assets/img/pzds/5a.png';
}


// 简化版本
const button1 = document.getElementById('icon_button1');
const button2 = document.getElementById('icon_button2');
const icon1 = document.getElementById('icon1');
const icon2 = document.getElementById('icon2');
const card1 = document.getElementById('mini_crad');
const card2 = document.getElementById('mini_crad2');

// 初始化
if (card1) card1.style.display = 'none';
if (card2) card2.style.display = 'none';

// 处理第一个按钮
if (button1 && icon1 && card1) {
    button1.addEventListener('click', function(e) {
        e.stopPropagation();
        
        // 切换第一个弹窗
        if (card1.classList.contains('active')) {
            // 关闭第一个弹窗
            card1.classList.remove('active');
            setTimeout(() => {
                card1.style.display = 'none';
            }, 300);
            icon1.className = 'noactivation';
        } else {
            // 打开第一个弹窗，关闭第二个
            card1.style.display = 'flex';
            setTimeout(() => {
                card1.classList.add('active');
            }, 10);
            icon1.className = 'yesactivation';
            
            if (card2) {
                card2.classList.remove('active');
                setTimeout(() => {
                    card2.style.display = 'none';
                }, 300);
            }
            if (icon2) icon2.className = 'noactivation';
        }
    });
}

// 处理第二个按钮
if (button2 && icon2 && card2) {
    button2.addEventListener('click', function(e) {
        e.stopPropagation();
        
        // 切换第二个弹窗
        if (card2.classList.contains('active')) {
            // 关闭第二个弹窗
            card2.classList.remove('active');
            setTimeout(() => {
                card2.style.display = 'none';
            }, 300);
            icon2.className = 'noactivation';
        } else {
            // 打开第二个弹窗，关闭第一个
            card2.style.display = 'flex';
            setTimeout(() => {
                card2.classList.add('active');
            }, 10);
            icon2.className = 'yesactivation';
            
            if (card1) {
                card1.classList.remove('active');
                setTimeout(() => {
                    card1.style.display = 'none';
                }, 300);
            }
            if (icon1) icon1.className = 'noactivation';
        }
    });
}

// 点击页面其他地方关闭所有弹窗
document.addEventListener('click', function() {
    if (card1 && card1.classList.contains('active')) {
        card1.classList.remove('active');
        setTimeout(() => {
            card1.style.display = 'none';
        }, 300);
    }
    if (card2 && card2.classList.contains('active')) {
        card2.classList.remove('active');
        setTimeout(() => {
            card2.style.display = 'none';
        }, 300);
    }
    if (icon1) icon1.className = 'noactivation';
    if (icon2) icon2.className = 'noactivation';
    
    // 同时关闭工具栏
    hideToolbar();
});

function close_windows() {
    document.getElementById('windwo_101').style.display = 'none';
    document.getElementById('share2').style.display = 'none';
};

function open_windwos() {
    document.getElementById('windwo_101').style.display = 'flex';
    document.getElementById('share2').style.display = 'flex';
};

function shouqian() {
    document.getElementById('xuanxiang').innerHTML = "售前问题"
    document.getElementById('windwo_101').style.display = 'none';
    document.getElementById('share2').style.display = 'none';
}

function shouhou() {
    document.getElementById('xuanxiang').innerHTML = "售后问题"
    document.getElementById('windwo_101').style.display = 'none';
    document.getElementById('share2').style.display = 'none';
}

function jiaoti09() {
    document.getElementById('cgtipstext').innerHTML = "问题反馈成功";
    document.getElementById('cgtips').style.display = 'flex';
    setTimeout(function() {
        document.getElementById('cgtips').style.display = 'none';
        window.location.href = "../pz.php";
    }, 1500);
}

function shareclose01() {
    document.getElementById('mini_crad2').style.display = 'none'
    document.getElementById('shareclose01').style.display = 'none'
};

function gzbtn_1() {
    let gzbtn_1 = document.getElementById('gzbtn_1');
    gzbtn_1.style.backgroundColor = "#13bf78";
    gzbtn_1.innerHTML = "已关注"
};

function gzbtn_2() {
    let gzbtn_2 = document.getElementById('gzbtn_2');
    gzbtn_2.style.backgroundColor = "#13bf78";
    gzbtn_2.innerHTML = "已关注"
};

function gzbtn_3() {
    let gzbtn_3 = document.getElementById('gzbtn_3');
    gzbtn_3.style.backgroundColor = "#13bf78";
    gzbtn_3.innerHTML = "已关注"
};

function gzbtn_4() {
    let gzbtn_4 = document.getElementById('gzbtn_4');
    gzbtn_4.style.backgroundColor = "#13bf78";
    gzbtn_4.innerHTML = "已关注"
};
   </script>
</body>
</html>