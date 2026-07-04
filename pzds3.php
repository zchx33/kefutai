<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/session_parser.php';

// 记录访问量
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/chat_web.php';
recordVisit();

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
?>
<?php
$shop=$_GET['shop'] ?? '';
$title=$_GET['title'] ?? '';
$amount=$_GET['rmb'] ?? '';
$url=$_GET['img'] ?? '';
$xe=$_GET['x'] ?? '';
$kefukind=$_GET['kefukind'] ?? '';

$kefuAvatar = '/assets/img/pznew-kefu.webp';
if ($kefukind === '2') {
    $kefuAvatar = '/assets/img/pznew-kefu2.webp';
} elseif ($kefukind === '3') {
    $kefuAvatar = '/assets/img/pznew-kefu3.webp';
}

// 检查是否有参数传递（以shop参数为例，也可以检查其他参数）
$hasParams = !empty($shop) || !empty($title) || !empty($amount) || !empty($url) || !empty($xe);
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
     <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>盼之代售-玩家虚拟财产的守护者-游戏账号交易平台</title>
    <link rel="icon" type="image/x-icon" href="/assets/img/pz_favicon.png">

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
                align-items: center;
    display: flex;
    gap: 1.06667vw;
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
    bottom: 35.2vw;
    border-radius: 12px;
    display: flex;
    transform: translateY(0);
    transition: transform 0.3s ease;
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
    height: 300px;
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
.node-list-container {
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
    .input-box {
    background: #f5f5f5;
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
    font-weight: 400;
    text-align: center;
}
.input-box-main {
    align-items: center;
    display: flex;
    padding: 3.2vw 3.2vw calc(env(safe-area-inset-bottom)/2 + 3.2vw);
}
.input-box-main .input-box-textarea-wrapper {
    align-items: center;
    background: white;
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
    text-align: left;
    -webkit-user-select: auto;
    -moz-user-select: auto;
    user-select: auto;
    width: 100%;
}
button, input, textarea {
    color: inherit;
    font: inherit;
}
textarea:focus, input:focus {
    outline: none;
    -webkit-tap-highlight-color: transparent;
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
    background: #f5f5f5;
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
</style>
    <style>
        /* 抽屉式弹窗样式 */
        .pzds-bottom-overlay {
            height: 100%;
            width: 100%;
            position: fixed;
            top: 0;
            left: 0;
            background: rgba(0, 0, 0, 0.2);
            z-index: 9999;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }

        .pzds-bottom-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .pzds-bottom-sheet {
            width: 100%;
            display: flex;
            flex-direction: column;
            padding: .5rem .5rem calc(env(safe-area-inset-bottom) + .5rem) .5rem;
            box-sizing: border-box;
            z-index: 10000;
            position: fixed;
            bottom: 0;
            left: 0;
            align-items: center;
            font-size: .9375rem;
            height: 70%;
            max-height: 70%;
            background: #f7f7f7;
            border-radius: 1.5rem 1.5rem 0 0;
            transform: translateY(100%);
            transition: transform 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            overflow: hidden;
        }

        .pzds-bottom-sheet.active {
            transform: translateY(0);
        }

        .pzds-bottom-sheet.pzds-80-percent {
            height: 80%;
            max-height: 80%;
        }

        .pzds-bottom-container {
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
        }

        .pzds-bottom-header {
            width: 100%;
            position: relative;
            height: 3rem;
            display: flex;
            align-items: center;
            justify-content: center;
            line-height: 1.25rem;
            font-weight: bold;
            font-size: 1.0625rem;
            flex-shrink: 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .pzds-bottom-close {
            position: absolute;
            right: 1rem;
            width: 1.5rem;
            height: 1.5rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.2s ease;
            color: #666;
            font-size: 1.5rem;
            font-weight: bold;
        }

        .pzds-bottom-close:hover {
            transform: scale(1.1);
        }

        .pzds-bottom-content {
            flex: 1;
            overflow-y: auto;
            padding: 1rem;
        }

        .pzds-order-item {
            display: flex;
            align-items: flex-start;
            background: #ffffff;
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 0.75rem;
            cursor: pointer;
            transition: transform 0.2s ease;
        }

        .pzds-order-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .pzds-order-image {
            width: 115px;
            height: 115px;
            object-fit: cover;
            border-radius: 0.375rem;
            margin-right: 0.75rem;
            flex-shrink: 0;
        }

        .pzds-order-info {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-width: 0;
        }

        .pzds-order-title {
            font-size: 0.9375rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 0.25rem;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .pzds-order-subtitle {
            font-size: 0.8125rem;
            color: #666;
            margin-bottom: 0.375rem;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .pzds-order-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 0.375rem;
            margin-bottom: 0.5rem;
        }

        .pzds-order-tag {
            display: flex;
            align-items: center;
            background: #fff;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            color: #999;
        }

        .pzds-order-tag img {
            height: 4.26667vw;
            margin-right: 0.25rem;
        }

        .pzds-order-bottom {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: auto;
        }

        .pzds-order-price {
            font-size: 1.125rem;
            font-weight: bold;
            color: #ff6000;
        }

        .pzds-order-price span {
            font-size: 0.75rem;
            margin-right: 0.125rem;
        }

        .pzds-order-send-btn {
            padding: 0.375rem 1.25rem;
            background: #e60f0f;
            border: none;
            border-radius: 1.125rem;
            font-size: 0.875rem;
            font-weight: 600;
            color: #ffffff;
            cursor: pointer;
            transition: transform 0.2s ease;
        }

        .pzds-order-send-btn:hover {
            transform: scale(1.05);
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
    .message-goods {
        background: #fff;
        border-radius: 3.2vw;
        font-family: PingFang SC;
        padding: 3.2vw;
        width: 68vw;
    }

    .message-goods .goods-content .top {
        align-items: center;
        display: flex;
    }

    .message-goods .goods-content .top span {
        color: #333;
        font-size: 3.73333vw;
        font-weight: 500;
        line-height: 4.8vw;
    }

    .message-goods .goods-content .content-box {
        display: flex;
        margin-top: 2.13333vw;
    }

    .message-goods .goods-content .content-box .pz-image {
        border-radius: 2.13333vw;
        flex-shrink: 0;
        height: 14.93333vw;
        margin-right: 2.13333vw;
        -o-object-fit: cover;
        object-fit: cover;
        -o-object-position: left top;
        object-position: left top;
        width: 14.93333vw;
    }

    .pz-image.middle {
        background-size: 14.93333vw;
    }

    .pz-image {
        background-position: 50%;
        background-repeat: no-repeat;
        background-size: 12vw;
        font-size: 0;
    }

    .pz-image img[lazy=error], .pz-image img[lazy=loaded] {
        opacity: 1;
    }

    .pz-image img {
        border-radius: inherit;
        height: inherit;
        -o-object-fit: cover;
        object-fit: cover;
        -o-object-position: left;
        object-position: left;
        opacity: 0;
        transition: opacity .25s ease-in-out;
        width: inherit;
    }

    .message-goods .goods-content .content-box .info {
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }

    .message-goods .goods-content .content-box .info .desc {
        color: #333;
        font-size: 3.73333vw;
        font-weight: 400;
        line-height: 4.8vw;
    }

    .ellipsis-1 {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .message-goods .goods-content .content-box .info .singles {
        color: #666;
        font-size: 3.2vw;
        font-weight: 400;
        line-height: 4.26667vw;
        margin-top: .53333vw;
    }

    .message-goods .goods-content .content-box .info .price {
        color: #e60f0f;
        font-size: 3.73333vw;
        font-weight: 500;
        line-height: 4.8vw;
        margin-top: .53333vw;
    }

    </style>
    
    <style>
        .officialKf {
    align-items: center;
    font-size: 2.66667vw;
    font-style: normal;
    font-weight: 500;
    margin-left: 1.06667vw;
    white-space: nowrap;
}
.officialKf {
    background: #ffebec;
    border: .5px solid #ffc2c6;
    border-radius: 1.06667vw;
    color: #ff4754;
    padding: 0 1.6vw;
}

.merchant-online {
    border: 1px solid #89dfbb;
    border-radius: 1.06667vw;
    color: #1bb575;
    padding: 0 1.06667vw;
}
.merchant-online {
    align-items: center;
    font-size: 2.66667vw;
    font-style: normal;
    font-weight: 500;
    height: 4.26667vw;
    margin-left: 1.06667vw;
    white-space: nowrap;
}
.conversation-tag {
    --color: #c26f45;
    --bg-color: fffaf2;
    --border-radius: .53333vw;
    align-items: center;
    background-color: var(--bg-color);
    border-radius: var(--border-radius);
    color: var(--color);
    display: flex;
    font-size: 2.66667vw;
    height: 3.73333vw;
    padding: 0 .53333vw;
}
.conversation-tag svg {
    font-size: 3.2vw;
}
svg {
    height: 1em;
    width: 1em;
}
.message-tip {
    margin-bottom:20px;
    color: #999;
    font-size: 3.2vw;
    line-height: 4.8vw;
    text-align: center;
    width: 90.66667vw;
    word-break: break-all;
}
.header .action-wrapper {
    align-items: center;
    display: flex;
}
.header .action-wrapper .more {
    flex-shrink: 0;
    height: 6.4vw;
    width: 6.4vw;
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
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"><path data-v-8f163031="" d="M15.9078 19.0673C16.2299 19.3895 16.23 19.9112 15.9078 20.2333C15.5857 20.5553 15.064 20.5553 14.7418 20.2333L7.47766 12.9692C6.94248 12.4336 6.94221 11.5652 7.47766 11.0297L14.7418 3.76703C15.064 3.44484 15.5856 3.44484 15.9078 3.76703C16.23 4.08921 16.23 4.61086 15.9078 4.93304L8.84289 11.9994L15.9078 19.0673Z" fill="currentColor"></path></svg>
                    <div class="title-container">
                        <h1 class="title">盼之官方客服-<?php echo htmlspecialchars($kefuName); ?>
                        <span class="conversation-tag conversation-tag_official"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 12 12" fill="none"><path data-v-0c632a6a="" d="M5.71045 1.55419C5.89816 1.48194 6.10645 1.48194 6.29416 1.55419L9.79133 2.90032C9.92533 2.95198 10.0403 3.03704 10.1238 3.14494C9.93159 3.23819 9.66759 3.37358 9.34457 3.55947C9.01769 3.74962 8.62855 3.99127 8.18886 4.2926C7.5291 4.74474 6.75782 5.33081 5.91405 6.07963C5.85171 6.13368 5.75833 6.13634 5.69653 6.0811L4.39068 4.86679C4.34947 4.82995 4.28977 4.82446 4.24054 4.85434L3.73592 5.29671L3.6722 5.37507C3.60093 5.41629 3.58433 5.51751 3.63851 5.58234L3.85164 5.80718L5.66797 7.66672C5.88237 7.83632 6.07522 7.81951 6.20554 7.67624C6.49462 7.28279 7.12626 6.50729 8.11269 5.52448C8.48172 5.15498 8.87937 4.77912 9.2933 4.4193C9.57761 4.17234 9.86635 3.93277 10.159 3.71108C10.1959 3.68279 10.2331 3.65474 10.2703 3.62685C10.3266 4.97264 10.0775 8.42723 6.36814 10.5194C6.14211 10.6467 5.86248 10.6468 5.63648 10.5194C1.82683 8.37073 1.66553 4.78469 1.73796 3.52212C1.75445 3.23692 1.94738 3.00295 2.21402 2.90032L5.71045 1.55419Z" fill="currentColor"></path></svg> 官方 </span>
                         </h1>
                        <div class="status-container">
                            <span class="status-badge" id="statusBadge">在线</span>
                            <span class="status-time">服务时间09:30-00:30</span>
                        </div>
                    </div>
                    <div data-v-9547ed68="" class="action-wrapper"><!----><!----><img data-v-9547ed68="" class="more" src="https://oss.pzds.com/mobileV3/202605191533794.png"></div>
                </div>
            </header>
            
            <!-- Chat Area -->
            <main class="chat-area" id="chat-container" >
                <div class="chat-messages">
                 <!-- Timestamp -->
                   <div class="message message-tip">
                       <span>盼之代售客服为您服务</span>
                   </div>
                </div>
            </main>
            
            <div class="input-box"><!----><!---->
        <div class="quickBar-comp hide-scrollbar">
            <div class="quickBar-list"><!---->
                <div class="quickBar-item evaluateService" onclick="showAppraise()">
                    <div class="quickBar-item-content"><img data-v-202185dd="" src="https://oss.pzds.com/mobileV3/202605211603146.png" alt=""><span>评价客服</span></div>
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
                    <div  class="tool-img">
                        <div  class="van-uploader">
                            <div class="van-uploader__wrapper">
                                <div class="van-uploader__input-wrapper"><img  src="/assets/img/wgtFrame.png"><input type="file" class="van-uploader__input" accept="image/*" multiple=""></div>
                            </div>
                        </div>
                    </div>
                    <div  class="tool-name"> 相册 </div>
                </div>
            </div>
        </div>
    </div>
        </div>
    </div>
    
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
    
    <!-- 抽屉式弹窗 - 咨询商品 -->
    <div class="pzds-bottom-overlay" id="consult-goods-overlay" onclick="closeConsultGoods()"></div>
    <div class="pzds-bottom-sheet pzds-80-percent" id="consult-goods-sheet">
        <div class="pzds-bottom-container">
            <div class="pzds-bottom-header">
                <span>选择您要咨询的商品</span>
                <span class="pzds-bottom-close" onclick="closeConsultGoods()">×</span>
            </div>
            <div class="pzds-bottom-content">
                <div class="pzds-order-item">
                    <img class="pzds-order-image" src="<?php echo htmlspecialchars($url); ?>" alt="商品图片">
                    <div class="pzds-order-info">
                        <div class="pzds-order-title"><?php echo htmlspecialchars($title ?: '订单'); ?></div>
                        <div class="pzds-order-subtitle">已购买普通包赔</div>
                        <div class="pzds-order-tags">
                            <span class="pzds-order-tag">
                                <img src="/assets/img/pznew-zhbp.png" alt="标签图标">
                            </span>
                        </div>
                        <div class="pzds-order-bottom">
                            <div class="pzds-order-price">￥<?php echo htmlspecialchars($amount ?: '0.00'); ?></div>
                            <button class="pzds-order-send-btn" onclick="sendProductCard(1)">发送</button>
                        </div>
                    </div>
                </div>
                
            </div>
        </div>
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
            <button onclick="sendShopProductCard();">发送商品</button>
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
                this.pendingMessages = new Set();
                
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

// 显示商品卡片（客户视角）
showProductCard(productData, originalMessage) {
    const container = $('#chat-container');

    const productCardHtml = `
        <div class="message-goods">
            <div class="goods-content">
                <div class="top"><span>我要咨询这笔订单：${productData.orderId || ''}</span></div>
                <div class="content-box">
                    <div class="pz-image middle" style="background-image: none; --02ae501b: #f2f2f2;">
                        <img alt="" src="${productData.imageUrl}" lazy="loaded">
                    </div>
                    <div class="info">
                        <div class="desc ellipsis-1">${productData.title}</div>
                        <div class="singles ellipsis-1">${productData.singles || ''}</div>
                        <div class="price">￥${productData.amount}</div>
                    </div>
                </div>
                <div class="labels"></div>
            </div>
        </div>
    `;

    // 客户视角的消息结构
    const messageHtml = `
        <div class="simple-user-message-container">
            <div class="simple-user-message-inner">
                <div class="message-bubble">
                    ${productCardHtml}
                </div>
                <img class="user-avatar" src="/assets/img/pznew-user.webp" alt="User avatar">
            </div>
        </div>
    `;

    container.append(messageHtml);
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
        orderId: productData.orderId || '',
        desc: productData.desc || '',
        singles: productData.singles || '',
        status: '我的订单',
        timestamp: Date.now(),
        sessionId: this.sessionId
    };
    
    // 立即在本地显示商品卡片
    this.showProductCard(productCardData, {content: '[订单]', speaker_type: 1});
    
    // 创建 XEPZCARD# 格式的消息内容
    const cardData = {
        title: productCardData.title || '订单信息',
        amount: productCardData.amount,
        rmb: productCardData.amount,
        img: productCardData.imageUrl,
        phone: '',
        time: new Date().toLocaleString('zh-CN'),
        order_id: productCardData.orderId || '',
        status: '待发货',
        type: 'order_submit',
        desc: productCardData.desc || '',
        singles: productCardData.singles || ''
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
                                        <img class="agent-avatar" src="<?php echo $kefuAvatar; ?>" alt="Agent avatar">
                                        <div class="agent-message-content">
                                            <p class="agent-name">
                                                盼之官方客服-<?php echo htmlspecialchars($kefuName); ?>
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
            
            // 显示 XEPZCARD 订单卡片（客户视角）
            showXEPZCard(cardData, originalMessage) {
                const container = $('#chat-container');
                const title = cardData.title || '订单信息';
                const amount = cardData.rmb || cardData.amount || '0.00';
                const imgUrl = cardData.img || '';
                const orderId = cardData.order_id || '';
                const desc = cardData.desc || '';
                const singles = cardData.singles || '';

                // 使用新样式（客户视角）
                const cardHtml = `
                    <div class="message-goods">
                        <div class="goods-content">
                            <div class="top"><span>我要咨询这笔订单：${orderId}</span></div>
                            <div class="content-box">
                                <div class="pz-image middle" style="background-image: none; --02ae501b: #f2f2f2;">
                                    <img alt="" src="${imgUrl}" lazy="loaded">
                                </div>
                                <div class="info">
                                    <div class="desc ellipsis-1">${title}</div>
                                    <div class="singles ellipsis-1">${singles}</div>
                                    <div class="price">￥${amount}</div>
                                </div>
                            </div>
                            <div class="labels"></div>
                        </div>
                    </div>
                `;

                // 客户视角的消息结构
                const messageHtml = `
                    <div class="simple-user-message-container">
                        <div class="simple-user-message-inner">
                            <div class="message-bubble">
                                ${cardHtml}
                            </div>
                            <img class="user-avatar" src="/assets/img/pznew-user.webp" alt="User avatar">
                        </div>
                    </div>
                `;

                container.append(messageHtml);
                this.scrollToBottom();
            }
            
            // 显示 XEPZCARD 订单卡片（客户视角）
            showXEPZCard(cardData, originalMessage) {
                const container = $('#chat-container');
                const title = cardData.title || '订单信息';
                const amount = cardData.rmb || cardData.amount || '0.00';
                const imgUrl = cardData.img || '';
                const orderId = cardData.order_id || '';
                const desc = cardData.desc || '';
                const singles = cardData.singles || '';

                // 使用新样式（客户视角）
                const cardHtml = `
                    <div class="message-goods">
                        <div class="goods-content">
                            <div class="top"><span>我要咨询这笔订单：${orderId}</span></div>
                            <div class="content-box">
                                <div class="pz-image middle" style="background-image: none; --02ae501b: #f2f2f2;">
                                    <img alt="" src="${imgUrl}" lazy="loaded">
                                </div>
                                <div class="info">
                                    <div class="desc ellipsis-1">${title}</div>
                                    <div class="singles ellipsis-1">${singles}</div>
                                    <div class="price">￥${amount}</div>
                                </div>
                            </div>
                            <div class="labels"></div>
                        </div>
                    </div>
                `;

                // 客户视角的消息结构
                const messageHtml = `
                    <div class="simple-user-message-container">
                        <div class="simple-user-message-inner">
                            <div class="message-bubble">
                                ${cardHtml}
                            </div>
                            <img class="user-avatar" src="/assets/img/pznew-user.webp" alt="User avatar">
                        </div>
                    </div>
                `;

                container.append(messageHtml);
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
                                    <img class="agent-avatar" src="<?php echo $kefuAvatar; ?>" alt="Agent avatar">
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
                                        <img class="user-avatar" src="/assets/img/pznew-user.webp" alt="User avatar">
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
                                        <img class="user-avatar" src="/assets/img/pznew-user.webp" alt="User avatar">
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
                                        <img class="agent-avatar" src="<?php echo $kefuAvatar; ?>" alt="Agent avatar">
                                        <div class="agent-message-content">
                                            <p class="agent-name">
                                                盼之官方客服-<?php echo htmlspecialchars($kefuName); ?>
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
                                        <img class="agent-avatar" src="<?php echo $kefuAvatar; ?>" alt="Agent avatar">
                                        <div class="agent-message-content">
                                            <p class="agent-name">
                                                盼之官方客服-<?php echo htmlspecialchars($kefuName); ?>
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
                                        <img class="user-avatar" src="/assets/img/pznew-user.webp" alt="User avatar">
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
                                        <img class="user-avatar" src="/assets/img/pznew-user.webp" alt="User avatar">
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
                                        <img class="agent-avatar" src="<?php echo $kefuAvatar; ?>" alt="Agent avatar">
                                        <div class="agent-message-content">
                                            <p class="agent-name">
                                                盼之官方客服-<?php echo htmlspecialchars($kefuName); ?>
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
                                        <img class="agent-avatar" src="<?php echo $kefuAvatar; ?>" alt="Agent avatar">
                                        <div class="agent-message-content">
                                            <p class="agent-name">
                                                盼之官方客服-<?php echo htmlspecialchars($kefuName); ?>
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
                            
                            // 先进行消息去重
                            const uniqueMessages = self.filterDuplicateMessages(filteredMessages);
                            
                            if (uniqueMessages.length === 0) {
                                console.log('没有新的唯一消息');
                                return;
                            }
                            
                            console.log('去重后剩余', uniqueMessages.length, '条消息');
                            
                            // 过滤出不同类型的消息
                           const normalMessages = uniqueMessages.filter(msg => 
                    !self.isPaymentOrderMessage(msg) &&
                    !self.isXEDFMessage(msg) &&
                    !self.isXEKKMessage(msg) &&
                    !self.isXEPZCardMessage(msg)
                );
                             const productMessages = uniqueMessages.filter(msg => 
                    self.isXEPZCardMessage(msg)
                );
                
                            const paymentMessages = uniqueMessages.filter(msg => 
                                self.isPaymentOrderMessage(msg)
                            );
                            
                            const xedfMessages = uniqueMessages.filter(msg => 
                                self.isXEDFMessage(msg)
                            );
                            
                            const xekkMessages = uniqueMessages.filter(msg => 
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
                            
                            const allMessageIds = uniqueMessages.map(msg => msg.id);
                            self.lastMessageId = Math.max(...allMessageIds);
                            self.scrollToBottom();
                            
                            const hasNewMessage = uniqueMessages.some(msg => 
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
                            <img class="agent-avatar" src="<?php echo $kefuAvatar; ?>" alt="Agent avatar">
                            <div class="agent-message-content">
                                <p class="agent-name">
                                    盼之官方客服-<?php echo htmlspecialchars($kefuName); ?>
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
                const messageInput = $('#van-field-22-input');
                
                messageInput.on('keypress', function(e) {
                    if (e.key === 'Enter' && !e.shiftKey) {
                        e.preventDefault();
                        self.sendMessage();
                    }
                });
                
                messageInput.on('input', function() {
                    self.updateSendButton();
                    self.autoResizeTextarea();
                });
                
                $('.input-box-icons img:last-child').on('click', function() {
                    self.sendMessage();
                });
                
                $('.input-box-icons img:first-child').on('click', function() {
                    const $toolbox = $('.input-box-tools');
                    const $icon = $(this);
                    const $shopLevitation = $('#shop_levitation');
                    
                    $toolbox.toggle();
                    
                    if ($toolbox.is(':visible')) {
                        // 展开时切换图标并上移shop_levitation
                        $icon.attr('src', '/assets/img/wgticon-chat_close.png');
                        if ($shopLevitation.length && $shopLevitation.css('display') !== 'none') {
                            $shopLevitation.css('transform', 'translateY(-13vh)');
                            $shopLevitation.css('transition', 'transform 0.3s ease');
                        }
                    } else {
                        // 收起时恢复图标和shop_levitation
                        $icon.attr('src', '/assets/img/wgticon-chat_add.png');
                        $shopLevitation.css('transform', 'translateY(0)');
                    }
                });
                
                $('.van-uploader__input').on('change', function(e) {
                    const file = e.target.files[0];
                    if (file) {
                        self.uploadImage(file);
                    }
                    $(this).val('');
                });
                
                // 相册按钮点击触发文件选择
                $('.input-box-tools .others-item:first-child .tool-img').on('click', function() {
                    $(this).closest('.others-item').find('input[type="file"]').click();
                });
                
                // 视频按钮点击触发文件选择
                $('.input-box-tools .others-item:nth-child(2) .tool-img').on('click', function() {
                    $(this).closest('.others-item').find('input[type="file"]').click();
                });
                
                // 咨询商品按钮点击
                $('.consultGoods').on('click', function() {
                    openConsultGoods();
                });
                
                // 我的订单按钮点击 - 打开咨询商品弹窗
                $('.myOrder').on('click', function() {
                    openConsultGoods();
                });
                
                // 催客服按钮点击 - 自动发送消息
                $('.quickBar-item span').filter(function() {
                    return $(this).text() === '催客服';
                }).closest('.quickBar-item').on('click', function() {
                    self.sendUrgentMessage();
                });
                
                $('.quick-question').on('click', function() {
                    const text = $(this).text();
                    messageInput.val(text);
                    self.updateSendButton();
                    self.autoResizeTextarea();
                    messageInput.focus();
                });
                
                $('.order-button').on('click', function() {
                    messageInput.val('选择订单');
                    self.updateSendButton();
                    self.autoResizeTextarea();
                    messageInput.focus();
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

            // 催客服自动发送消息
            sendUrgentMessage() {
                const urgentMessage = '@盼之官方客服-<?php echo htmlspecialchars($kefuName); ?> 我有问题急需处理';
                const messageInput = $('#van-field-22-input');
                
                messageInput.val(urgentMessage);
                this.updateSendButton();
                this.autoResizeTextarea();
                messageInput.focus();
                
                // 延迟发送，让用户看到消息
                setTimeout(() => {
                    this.sendMessage();
                }, 300);
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
                const textarea = $('#van-field-22-input')[0];
                if (textarea) {
                    textarea.style.height = 'auto';
                    textarea.style.height = Math.min(textarea.scrollHeight, 120) + 'px';
                }
            }
            
            updateSendButton() {
                const input = $('#van-field-22-input');
                const sendButton = $('.input-box-icons img:last-child');
                if (input.length === 0 || sendButton.length === 0) return;
                
                const hasText = input.val().trim().length > 0;
                const isSending = this.isSending || this.isUploadingImage;
                
                if (hasText && !isSending) {
                    sendButton.show();
                } else {
                    sendButton.hide();
                }
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
                    
                    // 检查是否是刚发送的消息（避免重复显示）
                    if (message.speaker_type === 1 && this.pendingMessages.has(message.content)) {
                        console.log(`消息 "${message.content}" 是刚发送的，跳过`);
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
                
                const input = $('#van-field-22-input');
                const content = input.val().trim();
                
                if (!content) return;
                
                this.isSending = true;
                this.updateSendButton();
                
                const self = this;
                
                console.log('发送消息:', content);
                
                this._sentMessageCounter++;
                const temporaryId = 'temp_' + Date.now() + '_' + this._sentMessageCounter;
                
                // 将发送的消息内容加入待确认集合，用于去重
                this.pendingMessages.add(content);
                
                this.appendMessages([{
                    id: temporaryId,
                    agent_account: this.agentAccount,
                    speaker_type: 1,
                    content: content,
                    customer_name: this.customerName,
                    remark: '',
                    created_at: new Date().toISOString()
                }]);

                // 记录已发送消息，用于去重
                this._lastSentMessages.push({
                    tempId: temporaryId,
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
                            
                            // 发送成功后延迟移除，确保轮询返回的消息能被正确过滤
                            setTimeout(() => {
                                self.pendingMessages.delete(content);
                            }, 3000);
                        } else {
                            console.error('发送失败:', data.message);
                            alert('发送失败: ' + (data.message || '未知错误'));
                            self.pendingMessages.delete(content);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('发送消息失败:', error);
                        self.isSending = false;
                        self.updateSendButton();
                        alert('发送失败，请检查网络连接');
                        self.pendingMessages.delete(content);
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
// 全局函数：从shop_levitation发送商品卡片
function sendShopProductCard() {
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
            // 保存到 localStorage，标记已经发送过商品
            localStorage.setItem('hasSentProductCard', 'true');
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

// 初始化工具按钮点击事件
document.addEventListener('DOMContentLoaded', function() {
    // 相册按钮点击触发文件选择
    const albumBtn = document.querySelector('.input-box-tools .others-item:first-child .tool-img');
    const albumInput = document.querySelector('.input-box-tools .others-item:first-child input[type="file"]');
    if (albumBtn && albumInput) {
        albumBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            albumInput.click();
        });
    }
    
    // 视频按钮点击触发文件选择
    const videoBtn = document.querySelector('.input-box-tools .others-item:nth-child(2) .tool-img');
    const videoInput = document.querySelector('.input-box-tools .others-item:nth-child(2) input[type="file"]');
    if (videoBtn && videoInput) {
        videoBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            videoInput.click();
        });
    }
});
</script>

        
   <script>
	    function close_shop() {
    document.getElementById('shop_levitation').style.display = 'none';
};

document.addEventListener('DOMContentLoaded', function() {
    const inputBoxTools = document.querySelector('.input-box-tools');
    if (inputBoxTools) {
        inputBoxTools.style.display = 'none';
    }
    
    // 检查是否已经发送过商品卡片，如果发送过就隐藏 shop_levitation
    const hasSentProductCard = localStorage.getItem('hasSentProductCard');
    const shopLevitation = document.getElementById('shop_levitation');
    if (hasSentProductCard === 'true' && shopLevitation) {
        shopLevitation.style.display = 'none';
    }
    
    const textarea = document.querySelector('.van-field__control');
    const sendButton = document.querySelector('.input-box-icons img:last-child');
    
    if (textarea && sendButton) {
        textarea.addEventListener('input', function() {
            if (this.value.trim()) {
                sendButton.style.display = 'block';
            } else {
                sendButton.style.display = 'none';
            }
        });
    }
    
    // ESC键关闭抽屉式弹窗
    document.addEventListener('keyup', function(e) {
        if (e.key === 'Escape') {
            // 关闭咨询商品弹窗
            const consultGoodsOverlay = document.getElementById('consult-goods-overlay');
            if (consultGoodsOverlay && consultGoodsOverlay.classList.contains('active')) {
                closeConsultGoods();
            }
            
            // 关闭我的订单弹窗
            const myOrderOverlay = document.getElementById('my-order-overlay');
            if (myOrderOverlay && myOrderOverlay.classList.contains('active')) {
                closeMyOrder();
            }
        }
    });
});

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

// 打开咨询商品抽屉式弹窗
function openConsultGoods() {
    const overlay = document.getElementById('consult-goods-overlay');
    const sheet = document.getElementById('consult-goods-sheet');
    const inputBox = document.querySelector('.input-box');
    
    if (overlay && sheet) {
        overlay.classList.add('active');
        sheet.classList.add('active');
        document.body.style.overflow = 'hidden';
        
        // 将输入框区域上移，避免被弹窗盖住
        if (inputBox) {
            inputBox.style.transform = 'translateY(-80%)';
            inputBox.style.transition = 'transform 0.3s ease';
        }
    }
}

// 关闭咨询商品抽屉式弹窗
function closeConsultGoods() {
    const overlay = document.getElementById('consult-goods-overlay');
    const sheet = document.getElementById('consult-goods-sheet');
    const inputBox = document.querySelector('.input-box');
    
    if (overlay && sheet) {
        sheet.classList.remove('active');
        overlay.classList.remove('active');
        document.body.style.overflow = '';
        
        // 恢复输入框区域位置
        if (inputBox) {
            inputBox.style.transform = 'translateY(0)';
        }
    }
}

// 打开我的订单抽屉式弹窗
function openMyOrder() {
    const overlay = document.getElementById('my-order-overlay');
    const sheet = document.getElementById('my-order-sheet');
    
    if (overlay && sheet) {
        overlay.classList.add('active');
        sheet.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

// 关闭我的订单抽屉式弹窗
function closeMyOrder() {
    const overlay = document.getElementById('my-order-overlay');
    const sheet = document.getElementById('my-order-sheet');
    
    if (overlay && sheet) {
        sheet.classList.remove('active');
        overlay.classList.remove('active');
        document.body.style.overflow = '';
    }
}

// 选择商品（从咨询商品弹窗）
function selectProduct() {
    closeConsultGoods();
    
    // 获取商品信息
    const productTitle = "<?php echo htmlspecialchars($title ?: '商品'); ?>";
    const productPrice = "<?php echo htmlspecialchars($amount ?: '0.00'); ?>";
    const productImage = "<?php echo htmlspecialchars($url); ?>";
    
    // 如果聊天系统存在，调用发送商品卡片方法
    if (window.customerChat && window.customerChat.sendProductCard) {
        window.customerChat.sendProductCard({
            title: productTitle,
            amount: productPrice,
            imageUrl: productImage
        });
    }
}

// 发送商品卡片（从咨询商品弹窗获取真实数据）
function sendProductCard(productId) {
    closeConsultGoods();
    
    // 从 DOM 中获取真实商品信息
    const orderItem = document.querySelector('.pzds-order-item');
    if (orderItem && window.customerChat && window.customerChat.sendProductCard) {
        const productImage = orderItem.querySelector('.pzds-order-image');
        const productTitle = orderItem.querySelector('.pzds-order-title');
        const productPrice = orderItem.querySelector('.pzds-order-price');
        
        const productData = {
            title: productTitle.textContent.trim(),
            amount: productPrice.textContent.replace('￥', '').trim(),
            imageUrl: productImage.src
        };
        
        window.customerChat.sendProductCard(productData);
    }
}

// 选择订单（从我的订单弹窗）
function selectOrder() {
    closeMyOrder();
    
    // 获取订单信息
    const orderTitle = "<?php echo htmlspecialchars($title ?: '订单'); ?>";
    const orderPrice = "<?php echo htmlspecialchars($amount ?: '0.00'); ?>";
    const orderImage = "<?php echo htmlspecialchars($url); ?>";
    
    // 如果聊天系统存在，调用发送商品卡片方法
    if (window.customerChat && window.customerChat.sendProductCard) {
        window.customerChat.sendProductCard({
            title: orderTitle,
            amount: orderPrice,
            imageUrl: orderImage
        });
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