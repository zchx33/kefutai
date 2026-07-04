<?php
if (!defined('FROM_ROUTER')) {
    http_response_code(403);
    exit('喜乐科技@x60898');
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>交易信息</title>
    <style>
        body, html {
            margin: 0;
            padding: 0;
            height: 100%;
            font-family: Arial, sans-serif;
            background-color: #f9f9f9;
            overflow: hidden;
        }

        /* 主容器 */
        .main-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            min-height: 100vh;

            box-sizing: border-box;
        }

        /* 卡片式标题栏 - 模拟图片中的圆角矩形 */
        .card-header {
            display: flex;
            align-items: center;
            background-color: #ffffff;
            padding: 4px 8px;
            width: 100%;
            max-width: 500px;
            border-radius: 12px;
            position: relative;
            margin-bottom: 20px;
        }

        /* 返回按钮 - 绝对定位，不影响文字居中 */
        .back-button {
            position: absolute;
            margin-left: 20px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            outline: none;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 24px;
            height: 24px;
        }

        /* 确保返回箭头样式 */
        .back-button svg {
            width: 20px;
            height: 20px;
            fill: #333;
        }

        /* 标题文字 - 完全居中 */
        .header-title {
            flex: 1;
            font-size: 19px;
            font-weight: 700;
            color: #333;
            text-align: center;
            /* 确保文字垂直居中 */
            display: flex;
            align-items: center;
            justify-content: center;
            /* 增加行高，确保垂直居中 */
            line-height: 1.5;
            padding: 8px 0; /* 增加上下内边距确保居中 */
        }

        /* 内容区域 */
        .container {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            flex: 1;
            width: 100%;
        }

        .image-container {
            text-align: center;
        }

        .center-image {
            max-width: 150px;
            height: auto;
            margin-bottom: 20px;
        }

        .text {
            margin-top: 20px;
            color: #666;
            font-size: 16px;
        }
    </style>
</head>
<body>
    <div class="main-container">
        <!-- 卡片式标题栏（模拟图片中的圆角矩形） -->
        <div class="card-header">
            <button class="back-button" onClick="javascript:history.back()">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" width="1em" height="1em">
                    <path fill="currentColor" fill-opacity=".8" fill-rule="evenodd" d="M16 6.068c0 .283-.114.554-.316.755L10.455 12l5.23 5.177c.201.2.315.472.315.755 0 .59-.483 1.068-1.078 1.068-.286 0-.56-.112-.763-.313l-5.738-5.68Q8 12.59 8 12t.421-1.007l5.738-5.68c.202-.2.477-.313.763-.313C15.517 5 16 5.478 16 6.068"></path>
                </svg>
            </button>
            <div class="header-title">交易信息</div>
        </div>
        
        <!-- 内容区域 -->
        <div class="container">
            <div class="image-container">
                <img src="/assets/img/pxb7-ypx.png" alt="无交易信息" class="center-image">
                <div class="text">买家已拍下 订单待发货~</div>
            </div>
        </div>
    </div>
</body>
</html>