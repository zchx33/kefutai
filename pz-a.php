<?php
if (!defined('FROM_ROUTER')) {
    http_response_code(403);
    exit('喜乐科技@x60898');
}
?>
<html lang="zh-CN">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>盼之代售-玩家虚拟财产的守护者-游戏账号交易平台</title>
	<link rel="icon" type="image/x-icon" href="https://oss.pzds.com/mobileV3/favicon/favicon.ico">
	<link rel="stylesheet" href="/assets/bootstrap-icons.css">
	<style>
		/* 全局样式 */
		:root {
		    --color-blue: #0066ff;
		    --color-gray-light: #f5f5f5;
		    --color-gray-medium: #dbdbdb;
		    --color-gray-dark: #9d9d9d;
		    --color-text: #2d2d2d;
		    --color-text-light: #555555;
		    --color-background: #f7f7f7;
		    --color-yellow: #fbe440;
		}
		
		* {
		    margin: 0;
		    border: none;
		    outline: none;
		    padding: 0;
		    touch-action: manipulation;
		    -webkit-tap-highlight-color: transparent;
		    box-sizing: border-box;
		}
		
		*::-webkit-scrollbar {
		    display: none;
		    width: 0;
		    height: 0;
		}
		
		html, body, #app {
		    overscroll-behavior: none;
		    height: 100%;
		    width: 100%;
		}
		
		html {
		    font-size: 16px;
		    font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;
		}
		
		body, #app {
		    overflow: hidden;
		    background: #fff;
		}
		
		body {
		    box-sizing: border-box;
		}
		
		svg {
		    box-sizing: content-box;
		}
		
		#app {
		    display: flex;
		    flex-direction: column;
		    height: 100%;
		    width: 100%;
		}
		
		/* 主容器样式 */
		.app-container {
		    height: 100%;
		    width: 100%;
		    background: var(--color-gray-light);
		    overflow: hidden;
		    position: relative;
		}
		
		.scroll-container {
		    overscroll-behavior: none;
		    height: 100%;
		    width: 100%;
		    overflow-y: scroll;
		}
		
		/* 顶部栏样式 */
		.top-header {
		    background: white;
		    padding-left: 8px;
		    align-items: center;
		    display: flex;
		    height: 49px;
		    position: relative;
		}
		
		.action-buttons {
		    margin-left: auto;
		    position: absolute;
		    right: 12px;
		    top: 50%;
		    transform: translateY(-50%);
		    display: flex;
		    align-items: center;
		    gap: 8px;
		}
		.hotTopic-page {
		    border-radius: 1.6vw;
		    margin-top: 3.2vw;
		}
		.hotTopic-page-main{
		    padding: 3.2vw;
		}
		img, video {
		    height: auto;
		    max-width: 100%;
		}
		.jylc {
		    color: #333;
		    flex: 1;
		    font-size: 4.26667vw;
		    font-weight: 400;
		    left: 50%;
		    padding-top: env(safe-area-inset-top);
		    position: absolute;
		    text-align: center;
		    top: 50%;
		    transform: translate(-50%,-50%);
		}
	</style>
</head>
<body>
	<div id="app" data-v-app="">
		<div class="app-container">
			<div class="scroll-container">
				<svg version="1.1" xmlns="http://www.w3.org/2000/svg" style="display: none;">
					<symbol id="arrow-left">
						<path d="M778.671 926.323a56.811 56.811 0 1 1-80.33 80.331L243.85 552.165a56.811 56.811 0 0 1 0-80.33l454.49-454.49a56.811 56.811 0 1 1 80.33 80.331L364.348 512 778.67 926.323z"></path>
					</symbol>
				</svg>
				<div class="top-header">
					<a href="JavaScript:history.back(-1)" style="display: inline-flex; align-items: center; text-decoration: none; color: inherit;margin-left:5px;">
						<svg width="18" height="18" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg">
							<use xlink:href="#arrow-left"></use>
						</svg>
					</a>
					<div class="jylc" style="border: 14px solid transparent;">交易流程</div>
				</div>
				<div class="hotTopic-page-main">
					<img src="https://pzdsoss.pzds.com/c/2/other/20250530/PZ_ADMIN_68390ccee4b030636b715b63.png" alt="210f77f988116e88ac3e7476dc6bcf7c.png" data-href="https://pzdsoss.pzds.com/c/2/other/20250530/PZ_ADMIN_68390ccee4b030636b715b63.png" class="hotTopic-page">
				</div>
			</div>
		</div>
	</div>
</body>
</html>