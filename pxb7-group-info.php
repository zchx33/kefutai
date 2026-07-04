<?php
if (!defined('FROM_ROUTER')) {
    http_response_code(403);
    exit('喜乐科技@x60898');
}
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
   	<title>im聊天房间</title>
	<link rel="shortcut icon" href="/assets/img/pxb7.png" type="image/x-icon">
    <style>
        /* 重置与基础样式 */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: #f3f4f6;
            color: #333;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
            overflow-x: hidden;
        }
        
        /* 容器与布局 */
        .app-container {
            display: flex;
            justify-content: center;
            min-height: 100vh;
            width: 100%;
        }
        
        .main-wrapper {
            width: 100%;
            min-height: 100vh;
            background-color: white;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1);
        }
        
        /* 头部样式 */
        .header {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: flex-start;
    background-color: #fff;
    padding-left: 18px;
    padding-right: 18px;
    padding-top: 10px;
    padding-bottom: 10px;
        }
        
        .header-title {
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
            font-size: 1.125rem;
            font-weight: 500;
            color: #333;
        }
        .header svg {
    color: #374151;
    font-size: 1.875rem;
    width: 1em;
    height: 1em;
}
        
        .back-icon {
            font-size: 26px;
            color: #4b5563;
            cursor: pointer;
        }
        
        .section-title {
            background-color: #f3f3f3;
            padding: 0.75rem 1rem;
            font-size: 16px;
            color: #9e9e9e;
        }
        
        .member-item {
            padding: 0.75rem 1rem;
            display: flex;
            align-items: center;
        }
        
        .member-item:not(:last-child) {
            border-bottom: 1px solid #f5f5f5;
        }
        
        /* 头像与在线状态 */
        .avatar-container {
            position: relative;
            flex-shrink: 0;
        }
        
        .avatar {
            width: 45.86px;
            height: 45.86px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .status-indicator {
            position: absolute;
            bottom: 4px;
            right: 0;
            width: 0.625rem;
            height: 0.625rem;
            border-radius: 50%;
            border: 2px solid white;
        }
        
        .status-online {
            background-color: #22c55e;
        }
        
        .status-offline {
            background-color: #d1d5db;
            width: 0.5rem;
            height: 0.5rem;
            bottom: 0.125rem;
            right: 0.125rem;
        }
        
        /* 成员信息 */
        .member-info {
            margin-left: 0.75rem;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        
        .member-name {
            font-size: 16px;
            color: #333;
        }
        
        /* 标签样式 */
        .tag {
            font-size: 12.5px;
            border-radius: 4.58px;
            padding: 0.125rem 0.375rem;
        }
        
        .tag-group-owner {
            color: white;
            background-color: #f6a046;
        }
        
        .tag-official {
            color: #d2a155;
            background-color: #fff7e0;
        }
        
        .tag-seller {
            color: #888;
            background-color: #dde0e4;
        }
        
        .tag-buyer {
            color: #888;
            background-color: #dde0e4;
        }
        
        /* 响应式设计 */
        @media (max-width: 420px) {
            .main-wrapper {
                max-width: 100%;
                box-shadow: none;
            }
            
            .header {
                height: 3rem;
                padding: 0 0.75rem;
            }
            
            .header-title {
                font-size: 1rem;
            }
            
            .member-item {
                padding: 0.625rem 0.75rem;
            }
            
            .avatar {
                width: 40px;
                height: 40px;
            }
        }
        
        @media (max-width: 320px) {
            .header-title {
                font-size: 0.875rem;
            }
            
            .section-title {
                font-size: 0.75rem;
                padding: 0.25rem 0.75rem;
            }
            
            .member-name {
                font-size: 0.8125rem;
            }
            
            .member-info {
                margin-left: 0.5rem;
            }
        }
        
        @media (min-width: 768px) {
            .app-container {
                padding: 1rem 0;
            }
            
            .main-wrapper {
                border-radius: 8px;
                overflow: hidden;
            }
        }
        
        @media (min-width: 1024px) {
            .main-wrapper {
                max-width: 420px;
            }
        }
        
        /* 动画效果 */
        .member-item {
            transition: background-color 0.2s ease;
        }
        
        .member-item:hover {
            background-color: #f9f9f9;
        }
        
        .back-icon {
            transition: transform 0.2s ease;
        }
        
        .back-icon:hover {
            transform: translateX(-2px);
        }
        .tag-imgimg {
            flex-shrink: 0;
            height: 19.3px;
            weight: 36.6px;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <div class="main-wrapper">
            <!-- 头部 -->
            <header class="header">
               <svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true" role="img" width="1em" height="1em" viewBox="0 0 24 24" class="iconify">
                    <path fill="currentColor" d="M15.41 16.58L10.83 12l4.58-4.59L14 6l-6 6l6 6z"></path>
                </svg>
                <h1 class="header-title">群成员</h1>
            </header>
            
            <!-- 成员列表 -->
            <main>
                <!-- 客服部分 -->
                <div class="member-section">
                    <div class="section-title">客服 (1人)</div>
                    
                    <div class="member-item" style="
    margin-top: 10px;">
                        <div class="avatar-container">
                            <img class="avatar" src="https://spark-builder.s3.cn-north-1.amazonaws.com.cn/image/2026/2/2/dee41089-946c-4db9-a2fc-705564c361c1.png" alt="螃蟹交付专员-慕青头像">
                            <span class="status-indicator status-online"></span>
                        </div>
                        <div class="member-info">
                            <span class="member-name member-name-bold">螃蟹交付专员-慕青</span>
                            <span class="tag tag-group-owner">群主</span>
                            <img data-v-b9922509="" class="tag-imgimg" src="data:image/svg+xml,%3csvg%20xmlns='http://www.w3.org/2000/svg'%20xmlns:xlink='http://www.w3.org/1999/xlink'%20fill='none'%20version='1.1'%20width='32'%20height='16'%20viewBox='0%200%2032%2016'%3e%3cdefs%3e%3clinearGradient%20x1='0'%20y1='0.5'%20x2='1'%20y2='0.5'%20id='master_svg0_1_6585'%3e%3cstop%20offset='0%25'%20stop-color='%23FFF7EA'%20stop-opacity='1'/%3e%3cstop%20offset='100%25'%20stop-color='%23FFECCA'%20stop-opacity='1'/%3e%3c/linearGradient%3e%3c/defs%3e%3cg%3e%3crect%20x='0'%20y='0'%20width='32'%20height='16'%20rx='4'%20fill='url(%23master_svg0_1_6585)'%20fill-opacity='1'/%3e%3cg%3e%3cpath%20d='M6.41688,5.96106L7.32644,5.96106L7.32644,13.9476L6.41688,13.9476L6.41688,5.96106ZM6.87025,5.96106L13.54319,5.96106L13.54319,9.03456L6.87025,9.03456L6.87025,8.246690000000001L12.6475,8.246690000000001L12.6475,6.74894L6.87025,6.74894L6.87025,5.96106ZM6.917120000000001,10.18412L13.9825,10.18412L13.9825,13.8923L13.06075,13.8923L13.06075,10.99356L6.917120000000001,10.99356L6.917120000000001,10.18412ZM6.95444,12.6064L13.3585,12.6064L13.3585,13.4086L6.95444,13.4086L6.95444,12.6064ZM4.89475,4.07444L15.0985,4.07444L15.0985,6.20988L14.1535,6.20988L14.1535,4.9232499999999995L5.80244,4.9232499999999995L5.80244,6.20988L4.89475,6.20988L4.89475,4.07444ZM9.372620000000001,3.05725L10.2775,2.85888Q10.46012,3.18812,10.62119,3.58956Q10.782250000000001,3.99081,10.84487,4.2741299999999995L9.9025,4.501189999999999Q9.837060000000001,4.22256,9.687809999999999,3.81063Q9.53856,3.3985,9.372620000000001,3.05725ZM16.816200000000002,4.99788L27.2279,4.99788L27.2279,5.87012L16.816200000000002,5.87012L16.816200000000002,4.99788ZM20.5714,7.79481L25.3667,7.79481L25.3667,8.6695L20.5714,8.6695L20.5714,7.79481ZM25.1206,7.79481L26.0588,7.79481Q26.0588,7.79481,26.0528,7.87563Q26.0468,7.95625,26.0444,8.05713Q26.0421,8.15781,26.0254,8.22925Q25.9075,9.9325,25.7755,10.98644Q25.6435,12.0404,25.4768,12.6064Q25.3101,13.1725,25.0681,13.4117Q24.8631,13.6381,24.6213,13.7211Q24.3796,13.8042,24.0332,13.8302Q23.7124,13.8563,23.1631,13.8417Q22.6139,13.8271,22.0066,13.7931Q21.9946,13.5921,21.9096,13.3416Q21.8249,13.0911,21.6852,12.9115Q22.3337,12.9668,22.9037,12.9826Q23.4738,12.9981,23.7179,12.9981Q23.9172,12.9981,24.0479,12.9719Q24.1786,12.9454,24.2886,12.8567Q24.4759,12.6861,24.6248,12.1473Q24.7739,11.6082,24.8965,10.58444Q25.0191,9.5605,25.1206,7.94894L25.1206,7.79481ZM20.1074,5.51631L21.0972,5.51631Q21.0492,6.457,20.9519,7.42938Q20.8546,8.40156,20.6335,9.34019Q20.4126,10.27881,19.9996,11.1381Q19.5867,11.9974,18.909599999999998,12.7255Q18.2327,13.4536,17.217399999999998,13.9878Q17.1132,13.8154,16.921599999999998,13.6099Q16.7299,13.4046,16.5529,13.2724Q17.5188,12.7904,18.151,12.1225Q18.7832,11.4544,19.1622,10.666Q19.5411,9.87756,19.729,9.01075Q19.9169,8.14375,19.9904,7.25669Q20.0641,6.36944,20.1074,5.51631ZM21.2851,3.184L22.1494,2.8434999999999997Q22.3967,3.26875,22.6463,3.76713Q22.8961,4.2653099999999995,23.0254,4.627940000000001L22.1112,5.02262Q21.9959,4.65531,21.7559,4.13706Q21.5159,3.61862,21.2851,3.184Z'%20fill='%23A57A43'%20fill-opacity='1'/%3e%3c/g%3e%3c/g%3e%3c/svg%3e">
                        </div>
                    </div>
                </div>
                
                <!-- 用户部分 -->
                <div class="member-section">
                    <div class="section-title">用户 (2人)</div>
                    
                    <div class="member-item" style="
    margin-top: 10px;">
                        <div class="avatar-container">
                            <img class="avatar" src="https://spark-builder.s3.cn-north-1.amazonaws.com.cn/image/2026/2/2/fb5693b8-1ee3-43c1-aa9d-0b0290e222e7.png" alt="用户_171488137910头像">
                            <span class="status-indicator status-online"></span>
                        </div>
                        <div class="member-info">
                            <span class="member-name">用户_171488137910</span>
                            <span class="tag tag-seller">卖家</span>
                        </div>
                    </div>
                    
                    <div class="member-item">
                        <div class="avatar-container">
                            <img class="avatar" src="https://spark-builder.s3.cn-north-1.amazonaws.com.cn/image/2026/2/2/9ae398ee-7a16-4d9a-9b25-03fa10d3567f.png" alt="用户_167268032117头像">
                            <span class="status-indicator status-online"></span>
                        </div>
                        <div class="member-info">
                            <span class="member-name">用户_167268032117</span>
                            <span class="tag tag-buyer">买家</span>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        // 添加交互功能
        document.addEventListener('DOMContentLoaded', function() {
            // 返回按钮功能
            const backButton = document.querySelector('.back-icon');
            if (backButton) {
                backButton.addEventListener('click', function() {
                    window.history.back();
                });
            }
            
            // 模拟点击成员项
            const memberItems = document.querySelectorAll('.member-item');
            memberItems.forEach(item => {
                item.addEventListener('click', function() {
                    const memberName = this.querySelector('.member-name').textContent;
                    console.log(`点击了成员: ${memberName}`);
                    
                    // 在实际应用中，这里可以跳转到成员详情页
                    // window.location.href = `/member-profile?name=${encodeURIComponent(memberName)}`;
                });
                
                // 添加触摸反馈
                item.addEventListener('touchstart', function() {
                    this.style.backgroundColor = '#f0f0f0';
                });
                
                item.addEventListener('touchend', function() {
                    this.style.backgroundColor = '';
                });
            });
            
            // 添加窗口大小变化时的处理
            window.addEventListener('resize', function() {
                const wrapper = document.querySelector('.main-wrapper');
                if (window.innerWidth < 420) {
                    wrapper.style.boxShadow = 'none';
                } else {
                    wrapper.style.boxShadow = '0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1)';
                }
            });
            
            // 触发初始窗口大小检查
            window.dispatchEvent(new Event('resize'));
        });
    </script>
</body>
</html>
    