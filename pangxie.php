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
$share = $_GET['share'] ?? ''; 
$parsedSession = SessionParser::parseSessionId($sessionId);
$customerName = $parsedSession['customer'];
$agentAccount = $parsedSession['agent'];

// 获取数据库连接
$conn = getDB();
if (!$conn) {
    die("数据库连接失败");
}

// 获取URL中的页面代码
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

// 查询商品页面信息 - 使用XEpxb7表结构
$sql = "SELECT
            XEpxb7_id,
            XEpxb7_user_id,
            XEpxb7_product_name,
            XEpxb7_game_name,
            XEpxb7_product_code,
            XEpxb7_product_amount,
            XEpxb7_no_stock_compensation,
            XEpxb7_retrieve_compensation,
            XEpxb7_customer_service,
            XEpxb7_dummy_identity,
            XEpxb7_page_status,
            XEpxb7_page_code,
            XEpxb7_product_image,
            XEpxb7_seller_avatar,
            XEpxb7_created_at,
            XEpxb7_updated_at
        FROM XEpxb7
        WHERE XEpxb7_page_code = ? AND XEpxb7_page_status = 'active'";

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

$formatted_amount = number_format($product_page['XEpxb7_product_amount'], 2, '.', '');
$dummy_identity = $product_page['XEpxb7_dummy_identity'] ?? '买家';

// 根据客服身份获取对应的头像和昵称
$customer_avatar = '';
$customer_nickname = '';

switch($product_page['XEpxb7_customer_service']) {
    case '螃蟹交易专员': 
        $customer_avatar = '/assets/img/px-kf.png'; 
        $customer_nickname = '暮青'; // 交易专员显示暮青
        break;
    case '螃蟹咨询专员': 
        $customer_avatar = '/assets/img/px-kf2.png'; 
        $customer_nickname = '凤竹'; // 咨询专员显示凤竹
        break;
    case '螃蟹售后专员': 
        $customer_avatar = '/assets/img/px-kf3.jpg'; 
        $customer_nickname = '星黛露'; // 售后专员显示星黛露
        break;
    default: 
        $customer_avatar = '<?php echo $customer_avatar; ?>';
        $customer_nickname = '不同昵称'; // 默认显示
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
	<meta charset="UTF-8">
	<meta name="apple-mobile-web-app-capable" content="yes">
	<meta name="mobile-web-app-capable" content="yes">
	<meta name="theme-color" content="#ffffff">
	<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0, user-scalable=no, viewport-fit=cover">
	<title>im聊天房间</title>
	<link rel="shortcut icon" href="/assets/img/pxb7.png" type="image/x-icon">
	<link href="/assets/Kefu/xepangxie.css" rel="stylesheet" type="text/css">
	<link href="/assets/Kefu/xepangxie3.css" rel="stylesheet" type="text/css">
	<link href="/assets/Kefu/xepangxie4.css" rel="stylesheet" type="text/css">
	<style>
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
		.message-author-userBuy {
		    margin-left:3px;
		    flex-shrink: 0;
		    border-radius: 4.58px;
		    padding: 0px 5.73px;
		    height: 18px;
		    line-height: 18px;
		    color: rgb(255, 255, 255);
		    font-size: 13.76px;
		}
	</style>
	<style>
		/* 更新 process-bar 样式（原 top-bar） */
		.process-bar {
		    width: 100%;
		    padding: 1.06667vw 7vw 2.13333vw;
		    overflow: hidden;
		    padding-left: 18px;
		    padding-right: 18px;
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
		    justify-content: space-between; /* 水平均匀分布 */
		    align-items: center; /* 垂直居中 */
		    width: 100%;
		}
		
		.process-bar .left .node-list .node-item {
		    align-items: center;
		    display: flex;
		
		    flex-shrink: 0;
		    text-align: center;
		    width: 33.33%; /* 三个节点平分宽度 */
		}
		
		.process-bar .left .node-list .node-item .status-icon {
		    height: 24px;
		    width: 24px;
		
		}
		
		.process-bar .left .node-list .node-item p {
		    color: #1414146B;
		    font-size: 14.9px;
		    font-weight: 400;
		    margin-left: 0; /* 移除原来的左边距 */
		    white-space: nowrap; /* 防止文字换行 */
		}
		 .xekefu-message-card {
		        background-color: #ffffff;
		        border-radius: 0.5rem;
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
		        color: rgb(246, 160, 70);
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
		    
		.van-button--block{
		width: 100%;
		}
		.van-button--large {
		border-radius: 3.2vw;
		padding: 0 4vw;
		
		}
		.van-button--primary {
		background: #f6a046;
		border: 1px solid #f6a046;
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
		margin-top: 10px;
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
		    background: #f6a046;
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
            background: #fff3e0;
            color: #f59e0b;
        }
        
        .tradecard-tag.type {
            background: #e8f4ff;
            color: #3b82f6;
        }
        
        .tradecard-status {
            font-size: 14px;
            font-weight: 600;
            color: #f59e0b;
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
            color: #ff8a1f;
            font-weight: 700;
        }
    </style>
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
        to { transform: rotate(1turn) } 
    } 
    .xile-loading-text { 
        margin-top: 16px; 
        font-size: 14px; 
        color: #666 
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
	<div id="app" data-v-app="">
		<div class="app-container">
			<div class="" style="display: none;">
				<div class=""></div>
			</div>
			<div class="header">
				<div>
					<img class="header-logo" src="/assets/img/px-3.png">
					<div class="header-info">
						<div class="agent-title" style="font-size:18px;margin-left:6px;">
							<div><?php echo htmlspecialchars($product_page['XEpxb7_product_code']); ?><?php echo htmlspecialchars($product_page['XEpxb7_game_name']); ?></div>
						</div>
					</div>
				</div>
				<div>
				 <div class="header-action" style="background-size: 100% 100%;" onclick="window.location.href='/Pxb7group?XEchatroom=<?php echo urlencode($page_code); ?>&id=<?php echo urlencode($sessionId); ?><?php echo !empty($share) ? '&share=' . urlencode($share) : ''; ?>'"></div>
				</div>
			</div>
			<div class="process-bar">
				<div class="left">
					<div class="node-list-container">
						<div class="node-list">
							<div class="node-item">
								<img src="/assets/img/pxqlqr.svg"
									alt="" class="status-icon">
								<p>确认账号信息</p>
							</div>
							<div class="node-item">
								<img src="/assets/img/pxqlqr.svg"
									alt="" class="status-icon">
								<p>买家上号验号</p>
							</div>
							<div class="node-item">
								<img src="/assets/img/pxqlqr.svg"
									alt="" class="status-icon">
								<p>双方换绑账号</p>
							</div>
						</div>
					</div>
				</div>
			</div>
			<div class="chat-area">
				<div class="chat-background"></div>
				<div class="chat-content" id="chat-container">
					<div class="chat-info">
						<div><?php echo htmlspecialchars($product_page['XEpxb7_customer_service']); ?>-<?php echo htmlspecialchars($customer_nickname); ?> 创建了群组</div>
					</div>
					<div class="chat-info">
						<div>激活会话消息</div>
					</div>
					<div class="XE-message-wrapper XE-wrapper-agent" style="--emptySize: 36px; --messageBottom: 0; --messagePadding: 10px;" data-message-id="${message.id}">
						<div style="--avatarSize: 40px; --avatarBorderRadius: 50%;" class="XE-avatar-container">
							<img src="<?php echo $customer_avatar; ?>">
						</div>
						<div style="--avatarMainGap: 10px;" class="XE-message-content">
							<div class="XE-message-header">
								<div class="XE-message-info"><?php echo htmlspecialchars($product_page['XEpxb7_customer_service']); ?>-<?php echo htmlspecialchars($customer_nickname); ?></div>
								<img src="/assets/img/pxgf.svg"
									alt="" style="width: 36px; height: 18px; margin-
		                          left:5px; ">
								<div class="XE-message-info" style="margin-left:3px;"></div>
							</div>
							<div class="XE-message-bubble XE-bubble-agent">
								<div class="xekefu-message-card">
									<div class="product-header">
									     <?php if ($product_page['XEpxb7_no_stock_compensation'] === '是'): ?>
                        <img src="/assets/img/whlp.png" alt="无货立赔" style="width: 56px; height: 16px; vertical-align: middle;">
                    <?php else: ?>
                  
                    <?php endif; ?>
                      <?php if ($product_page['XEpxb7_retrieve_compensation'] === '是'): ?>
                        <img src="/assets/img/pxzhbp.webp" alt="找回包赔" style="width: 56px; height: 16px; vertical-align: middle;">
                    <?php else: ?>
            
                    <?php endif; ?>
										<p class="product-code">商品编号: <?php echo htmlspecialchars($product_page['XEpxb7_product_code']); ?></p>
									</div>
									<div class="product-info">
										<img class="product-image" src="<?php echo htmlspecialchars($product_page['XEpxb7_product_image']); ?>" alt="Game inventory">
										<div class="product-details">
											<p class="product-title"><?php echo htmlspecialchars($product_page['XEpxb7_product_code']); ?> 号 【<?php echo htmlspecialchars($product_page['XEpxb7_product_name']); ?>】 </p>
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
			</div>
			<div class="input-section">
				<div class="toolbar">
					<div class="toolbar-container">
						<div class="tool-item">
							<img src="/assets/img/pxckf.svg"
								draggable="false" style="width:18px;height:18px;">
							<div class="tool-text">催客服</div>
						</div>
						<div class="tool-item" onclick="showAppraise()">
							<img src="/assets/img/pxpjkf.svg" draggable="false" style="width:18px;height:18px;">
							<div class="tool-text">评价客服</div>
						</div>
						<div class="tool-item" onclick="window.location.href='/Pxb7DD';">
							<img src="/assets/img/pxjyxx.svg" draggable="false" style="width:18px;height:18px;">
							<div class="tool-text">交易信息</div>
						</div>
						<div class="tool-item" onclick="window.location.href='https://ecnj5070ng8v.feishu.cn/share/base/form/shrcnnfoIZm2Lxr6h8UJBy1c46d';">
							<img src="/assets/img/pxtsjy.png"
								draggable="false" style="width:18px;height:18px;">
							<div class="tool-text">投诉建议</div>
						</div>
					</div>
				</div>
				<div class="input-container">
					<!-- 修改后的输入区域 - 只保留一个上传按钮 -->
					<div class="input-row">
						<!-- 消息输入框 -->
						<input class="input-field" placeholder="请输入内容" enterkeyhint="send" id="message-input" style="flex: 1;">
						<!-- 图片上传按钮 - 修改为SVG图标 -->
						<label class="upload-button" for="image">
							<div class="px-input-area_box_img"><img
									src="/assets/img/pxphoto.png"
									class="px-input-area_img"></div>
						</label>
						<input type="file" accept="image/*" style="display: none;" id="image" class="">
						<!-- 发送按钮 -->
						<button class="send-button" id="send-button"><span>发 送</span></button>
					</div>
				</div>
				<!-- 移除顶部上传按钮区域 -->
			</div>
			<div class="" style="display: none;"></div>
			<img class="" src="" alt="preview" style="display: none;">
		</div>
	</div>
	<!-- 图片预览模态框 -->
	<div class="image-preview-modal" id="image-preview-modal">
		<img class="image-preview-content" id="image-preview-content" src="" alt="预览图片">
	</div>
	<!-- 评价弹窗遮罩层 -->
	<div id="appraise-overlay" class="appraise-overlay" onclick="closeAppraise()"></div>
	<!-- 评价弹窗 -->
	<div id="appraise" class="appraise XE-pangxiebg">
		<button class="appraise-close-btn" onclick="closeAppraise()"><img data-v-0a66bb46=""
				src="/assets/img/pxclose.svg"
				class="w-20px h-20px absolute right-18px"></button>
		<h2>您对 <?php echo htmlspecialchars($product_page['XEpxb7_customer_service']); ?>-<?php echo htmlspecialchars($customer_nickname); ?> 的服务满意吗</h2>
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
	<script src="/assets/jquery.min.js"></script>
	<script src="/assets/Kefu/xepangxiepj.js"></script>
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
        this.platform = '螃蟹群聊';
        
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
        
        console.log('螃蟹聊天系统初始化:', {
            sessionId: this.sessionId,
            customerName: this.customerName,
            agentAccount: this.agentAccount
        });
        
        console.log('检测到设备信息:', this.deviceInfo);
        
        this.init();
    }
    
    init() {
        this.removeInitialTestMessages();
        this.loadInitialMessages();
        this.setupEventListeners();
        this.setupUrgeButtonEvent();
        this.startPolling();
        this.startStatusPolling();
        this.updateSendButton();
        this.updateCustomerOnlineStatus();
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
        
        // 检测交易卡片
        if (content.startsWith('XEXXCARD#') && content.length > 9) {
            try {
                const cardJson = content.substring(9);
                const cardData = JSON.parse(cardJson);
                if (cardData.type === 'trade_card') {
                    return { type: 'trade', data: cardData };
                }
            } catch (e) {
                console.error('解析交易卡片数据失败:', e);
            }
        }
        
        // 检测付款卡片
        if (content.startsWith('XEPAYCARD#') && content.length > 10) {
            try {
                const cardJson = content.substring(10);
                const cardData = JSON.parse(cardJson);
                if (cardData.type === 'pay_card') {
                    return { type: 'pay', data: cardData };
                }
            } catch (e) {
                console.error('解析付款卡片数据失败:', e);
            }
        }

        // 检测自定义卡片
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
        
        return false;
    }

    // 生成卡片HTML
    generateCardHtml(cardData, isUser = false) {
        // 交易卡片
        if (cardData.type === 'trade_card') {
            return this.renderTradeCard(cardData);
        }
        
        // 付款卡片
        if (cardData.type === 'pay_card') {
            return this.renderPayCard(cardData);
        }
        
        // 自定义卡片
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
            const nameElement = messageElement.find('.XE-message-info').first();
            const avatarElement = messageElement.find('.XE-avatar-container img');
            
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
    
    // 设置催客服按钮事件
    setupUrgeButtonEvent() {
        const self = this;
        
        $('.tool-item').filter(function() {
            return $(this).find('.tool-text').text() === '催客服';
        }).on('click', function() {
            self.handleUrgeButtonClick();
        });
    }
    
    // 处理催客服按钮点击
    handleUrgeButtonClick() {
        const now = Date.now();
        const lastUrgeTime = localStorage.getItem('lastUrgeTime');
        const threeMinutes = 3 * 60 * 1000;
        
        if (lastUrgeTime && (now - parseInt(lastUrgeTime)) < threeMinutes) {
            return;
        }
        
        this.addUrgeMessage();
        localStorage.setItem('lastUrgeTime', now.toString());
    }
    
    addUrgeMessage() {
        const container = $('#chat-container');
        const messageHtml = `
            <div class="chat-info">
                <div>催一催成功, 加急处理中, 客服预计会在3分钟内回复~</div>
            </div>
        `;
        container.append(messageHtml);
        this.scrollToBottom();
    }
    
    setupEventListeners() {
        const self = this;
        
        $('#message-input').on('keypress', function(e) {
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
                $('#message-input').val(text);
                self.updateSendButton();
                $('#message-input').focus();
            }
        });
        
        $('#message-input').on('input', function() {
            self.updateSendButton();
        });
        
        $('#send-button').on('click', function() {
            self.sendMessage();
        });
        
        $('#image').on('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                self.uploadImage(file);
            }
            $(this).val('');
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
        const input = $('#message-input');
        const sendButton = $('#send-button');
        const hasText = input.val().trim().length > 0;
        const isSending = this.isSending || this.isUploadingImage;
        
        if (hasText && !isSending) {
            sendButton.removeAttr('disabled');
        } else {
            sendButton.attr('disabled', 'disabled');
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
    
    removeInitialTestMessages() {
        $('.Nai-vy2Wi3XWe-Long, .Nai-l2gACcrep-Long').remove();
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
                const dummyTime = this.formatDateTime(message.created_at) || this.formatDateTime(new Date().toISOString());
                
                const avatarSrc = dummyAvatar.startsWith('http') || dummyAvatar.startsWith('/') 
                    ? dummyAvatar 
                    : '/assets/img/' + dummyAvatar;
                
                if (message.message_type === 'image' && (message.image_url || message.image_path)) {
                    const imageUrl = message.image_url || ('/uploads/' + message.image_path);
                    messageHtml = `
                        <div class="XE-message-wrapper XE-wrapper-agent dummy-message" style="--emptySize: 36px; --messageBottom: 0; --messagePadding: 10px;" data-message-id="${message.id}">
                            <div style="--avatarSize: 40px; --avatarBorderRadius: 50%;" class="XE-avatar-container">
                                <img src="${avatarSrc}" alt="${dummyName}" onerror="this.src='/assets/img/dummy1.png'">
                            </div>
                            <div style="--avatarMainGap: 10px;" class="XE-message-content">
                                <div class="XE-message-header">
                                    <div class="XE-message-info">${dummyName}</div>
                                    <span class="message-author-userBuy" style="background: rgb(221, 224, 228); color: rgba(20, 20, 20, 0.55);"><?php echo $dummy_identity; ?></span>
                                    <div class="XE-message-info" style="margin-left:3px;">${dummyTime}</div>
                                </div>
                                <div class="XE-message-bubble XE-bubble-agent">
                                    <img class="message-image" src="${imageUrl}" alt="图片" style="max-width: 200px; max-height: 200px; border-radius: 8px; cursor: pointer; display: block;">
                                </div>
                            </div>
                        </div>
                    `;
                } else {
                    const cardInfo = this.isCardMessage(message.content);
                    if (cardInfo) {
                        const cardHtml = this.generateCardHtml(cardInfo.data, false);
                        messageHtml = `
                            <div class="XE-message-wrapper XE-wrapper-agent dummy-message" style="--emptySize: 36px; --messageBottom: 0; --messagePadding: 10px;" data-message-id="${message.id}">
                                <div style="--avatarSize: 40px; --avatarBorderRadius: 50%;" class="XE-avatar-container">
                                    <img src="${avatarSrc}" alt="${dummyName}" onerror="this.src='/assets/img/dummy1.png'">
                                </div>
                                <div style="--avatarMainGap: 10px;" class="XE-message-content">
                                    <div class="XE-message-header">
                                        <div class="XE-message-info">${dummyName}</div>
                                        <span class="message-author-userBuy" style="background: rgb(221, 224, 228); color: rgba(20, 20, 20, 0.55);"><?php echo $dummy_identity; ?></span>
                                        <div class="XE-message-info" style="margin-left:3px;">${dummyTime}</div>
                                    </div>

                                        ${cardHtml}
                                    
                                </div>
                            </div>
                        `;
                    } else {
                        const messageContent = this.escapeHtml(message.content);
                        messageHtml = `
                            <div class="XE-message-wrapper XE-wrapper-agent dummy-message" style="--emptySize: 36px; --messageBottom: 0; --messagePadding: 10px;" data-message-id="${message.id}">
                                <div style="--avatarSize: 40px; --avatarBorderRadius: 50%;" class="XE-avatar-container">
                                    <img src="${avatarSrc}" alt="${dummyName}" onerror="this.src='/assets/img/dummy1.png'">
                                </div>
                                <div style="--avatarMainGap: 10px;" class="XE-message-content">
                                    <div class="XE-message-header">
                                        <div class="XE-message-info">${dummyName}</div>
                                        <span class="message-author-userBuy" style="background: rgb(221, 224, 228); color: rgba(20, 20, 20, 0.55);"><?php echo $dummy_identity; ?></span>
                                        <div class="XE-message-info" style="margin-left:3px;">${dummyTime}</div>
                                    </div>
                                    <div class="XE-message-bubble XE-bubble-agent">${messageContent}</div>
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
                        <div class="XE-message-wrapper XE-wrapper-customer" style="--emptySize: 36px; --messageBottom: 0; --messagePadding: 10px;" data-message-id="${message.id}">
                            <div style="--avatarSize: 40px; --avatarBorderRadius: 50%;" class="XE-avatar-container">
                                <img src="<?php echo htmlspecialchars($product_page['XEpxb7_seller_avatar']); ?>">
                            </div>
                            <div style="--avatarMainGap: 10px;" class="XE-message-content">
                                <div class="XE-message-header">
                                    <div class="XE-message-info">${this.formatDateTime(message.created_at)}</div> 
                                </div>
                                <div class="XE-message-bubble XE-bubble-customer"> 
                                    <img class="message-image" src="${imageUrl}" alt="图片" style="max-width: 200px; max-height: 200px; border-radius: 8px; cursor: pointer; display: block;">
                                </div>
                            </div>
                        </div>
                    `;
                } else {
                    const cardInfo = this.isCardMessage(message.content);
                    if (cardInfo) {
                        const cardHtml = this.generateCardHtml(cardInfo.data, true);
                        messageHtml = `
                            <div class="XE-message-wrapper XE-wrapper-customer" style="--emptySize: 36px; --messageBottom: 0; --messagePadding: 10px;" data-message-id="${message.id}">
                                <div style="--avatarSize: 40px; --avatarBorderRadius: 50%;" class="XE-avatar-container">
                                    <img src="<?php echo htmlspecialchars($product_page['XEpxb7_seller_avatar']); ?>">
                                </div>
                                <div style="--avatarMainGap: 10px;" class="XE-message-content">
                                    <div class="XE-message-header">
                                        <div class="XE-message-info">${this.formatDateTime(message.created_at)}</div> 
                                    </div>
                                    <div class="XE-message-bubble XE-bubble-customer">
                                        ${cardHtml}
                                    </div>
                                </div>
                            </div>
                        `;
                    } else {
                        const messageContent = this.escapeHtml(message.content);
                        messageHtml = `
                            <div class="XE-message-wrapper XE-wrapper-customer" style="--emptySize: 36px; --messageBottom: 0; --messagePadding: 10px;" data-message-id="${message.id}">
                                <div style="--avatarSize: 40px; --avatarBorderRadius: 50%;" class="XE-avatar-container">
                                    <img src="<?php echo htmlspecialchars($product_page['XEpxb7_seller_avatar']); ?>">
                                </div>
                                <div style="--avatarMainGap: 10px;" class="XE-message-content">
                                    <div class="XE-message-header">
                                        <div class="XE-message-info">${this.formatDateTime(message.created_at)}</div> 
                                    </div>
                                    <div class="XE-message-bubble XE-bubble-customer">${messageContent}</div>
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
                    const dummyTime = this.formatDateTime(message.created_at) || this.formatDateTime(new Date().toISOString());
                    
                    if (dummyAvatar && !dummyAvatar.startsWith('http') && !dummyAvatar.startsWith('/')) {
                        dummyAvatar = '/assets/img/' + dummyAvatar;
                    }
                    
                    if (message.message_type === 'image' && (message.image_url || message.image_path)) {
                        const imageUrl = message.image_url || ('/uploads/' + message.image_path);
                        messageHtml = `
                            <div class="XE-message-wrapper XE-wrapper-agent dummy-message" style="--emptySize: 36px; --messageBottom: 0; --messagePadding: 10px;" data-message-id="${message.id}">
                                <div style="--avatarSize: 40px; --avatarBorderRadius: 50%;" class="XE-avatar-container">
                                    <img src="${dummyAvatar}" alt="${dummyName}" onerror="this.src='/assets/img/dummy1.png'">
                                </div>
                                <div style="--avatarMainGap: 10px;" class="XE-message-content">
                                    <div class="XE-message-header">
                                        <div class="XE-message-info">${dummyName}</div>
                                        <span class="message-author-userBuy" style="background: rgb(221, 224, 228); color: rgba(20, 20, 20, 0.55);"><?php echo $dummy_identity; ?></span>
                                        <div class="XE-message-info" style="margin-left:3px;">${dummyTime}</div>
                                    </div>
                                    <div class="XE-message-bubble XE-bubble-agent">
                                        <img class="message-image" src="${imageUrl}" alt="图片" style="max-width: 200px; max-height: 200px; border-radius: 8px; cursor: pointer; display: block;">
                                    </div>
                                </div>
                            </div>
                        `;
                    } else {
                        const cardInfo = this.isCardMessage(message.content);
                        if (cardInfo) {
                            const cardHtml = this.generateCardHtml(cardInfo.data, false);
                            messageHtml = `
                                <div class="XE-message-wrapper XE-wrapper-agent dummy-message" style="--emptySize: 36px; --messageBottom: 0; --messagePadding: 10px;" data-message-id="${message.id}">
                                    <div style="--avatarSize: 40px; --avatarBorderRadius: 50%;" class="XE-avatar-container">
                                        <img src="${dummyAvatar}" alt="${dummyName}" onerror="this.src='/assets/img/dummy1.png'">
                                    </div>
                                    <div style="--avatarMainGap: 10px;" class="XE-message-content">
                                        <div class="XE-message-header">
                                            <div class="XE-message-info">${dummyName}</div>
                                            <span class="message-author-userBuy" style="background: rgb(221, 224, 228); color: rgba(20, 20, 20, 0.55);"><?php echo $dummy_identity; ?></span>
                                            <div class="XE-message-info" style="margin-left:3px;">${dummyTime}</div>
                                        </div>

                                            ${cardHtml}
                                       
                                    </div>
                                </div>
                            `;
                        } else {
                            const messageContent = this.escapeHtml(message.content);
                            messageHtml = `
                                <div class="XE-message-wrapper XE-wrapper-agent dummy-message" style="--emptySize: 36px; --messageBottom: 0; --messagePadding: 10px;" data-message-id="${message.id}">
                                    <div style="--avatarSize: 40px; --avatarBorderRadius: 50%;" class="XE-avatar-container">
                                        <img src="${dummyAvatar}" alt="${dummyName}" onerror="this.src='/assets/img/dummy1.png'">
                                    </div>
                                    <div style="--avatarMainGap: 10px;" class="XE-message-content">
                                        <div class="XE-message-header">
                                            <div class="XE-message-info">${dummyName}</div>
                                            <span class="message-author-userBuy" style="background: rgb(221, 224, 228); color: rgba(20, 20, 20, 0.55);"><?php echo $dummy_identity; ?></span>
                                            <div class="XE-message-info" style="margin-left:3px;">${dummyTime}</div>
                                        </div>
                                        <div class="XE-message-bubble XE-bubble-agent">${messageContent}</div>
                                    </div>
                                </div>
                            `;
                        }
                    }
                } else {
                    if (message.message_type === 'image' && (message.image_url || message.image_path)) {
                        const imageUrl = message.image_url || ('/uploads/' + message.image_path);
                        messageHtml = `
                            <div class="XE-message-wrapper XE-wrapper-agent" style="--emptySize: 36px; --messageBottom: 0; --messagePadding: 10px;" data-message-id="${message.id}">
                                <div style="--avatarSize: 40px; --avatarBorderRadius: 50%;" class="XE-avatar-container">
                                    <img src="<?php echo $customer_avatar; ?>">
                                </div>
                                <div style="--avatarMainGap: 10px;" class="XE-message-content">
                                    <div class="XE-message-header">
                                        <div class="XE-message-info"><?php echo htmlspecialchars($product_page['XEpxb7_customer_service']); ?>-<?php echo htmlspecialchars($customer_nickname); ?></div>
                                        <img src="/assets/img/pxgf.svg" alt="" style="width: 36px; height: 18px; margin-left:5px;">
                                        <div class="XE-message-info" style="margin-left:3px;">${this.formatDateTime(message.created_at)}</div> 
                                    </div>
                                    <div class="XE-message-bubble XE-bubble-agent"> 
                                        <img class="message-image" src="${imageUrl}" alt="图片" style="max-width: 200px; max-height: 200px; border-radius: 8px; cursor: pointer; display: block;">
                                    </div>
                                </div>
                            </div>
                        `;
                    } else {
                        const cardInfo = this.isCardMessage(message.content);
                        if (cardInfo) {
                            const cardHtml = this.generateCardHtml(cardInfo.data, false);
                            messageHtml = `
                                <div class="XE-message-wrapper XE-wrapper-agent" style="--emptySize: 36px; --messageBottom: 0; --messagePadding: 10px;" data-message-id="${message.id}">
                                    <div style="--avatarSize: 40px; --avatarBorderRadius: 50%;" class="XE-avatar-container">
                                        <img src="<?php echo $customer_avatar; ?>">
                                    </div>
                                    <div style="--avatarMainGap: 10px;" class="XE-message-content">
                                        <div class="XE-message-header">
                                            <div class="XE-message-info"><?php echo htmlspecialchars($product_page['XEpxb7_customer_service']); ?>-<?php echo htmlspecialchars($customer_nickname); ?></div>
                                            <img src="/assets/img/pxgf.svg" alt="" style="width: 36px; height: 18px; margin-left:5px;">
                                            <div class="XE-message-info" style="margin-left:3px;">${this.formatDateTime(message.created_at)}</div> 
                                        </div>
                                     
                                            ${cardHtml}
                                      
                                    </div>
                                </div>
                            `;
                        } else {
                            const messageContent = this.escapeHtml(message.content);
                            messageHtml = `
                                <div class="XE-message-wrapper XE-wrapper-agent" style="--emptySize: 36px; --messageBottom: 0; --messagePadding: 10px;" data-message-id="${message.id}">
                                    <div style="--avatarSize: 40px; --avatarBorderRadius: 50%;" class="XE-avatar-container">
                                        <img src="<?php echo $customer_avatar; ?>">
                                    </div>
                                    <div style="--avatarMainGap: 10px;" class="XE-message-content">
                                        <div class="XE-message-header">
                                            <div class="XE-message-info"><?php echo htmlspecialchars($product_page['XEpxb7_customer_service']); ?>-<?php echo htmlspecialchars($customer_nickname); ?></div>
                                            <img src="/assets/img/pxgf.svg" alt="" style="width: 36px; height: 18px; margin-left:5px;">
                                            <div class="XE-message-info" style="margin-left:3px;">${this.formatDateTime(message.created_at)}</div> 
                                        </div>
                                        <div class="XE-message-bubble XE-bubble-agent">${messageContent}</div>
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
    
    formatDateTime(dateString) {
        if (!dateString) return '';
        
        try {
            const date = new Date(dateString);
            if (isNaN(date.getTime())) {
                return '';
            }
            const month = (date.getMonth() + 1).toString().padStart(2, '0');
            const day = date.getDate().toString().padStart(2, '0');
            const hours = date.getHours().toString().padStart(2, '0');
            const minutes = date.getMinutes().toString().padStart(2, '0');
            
            return `${month}/${day} ${hours}:${minutes}`;
        } catch (error) {
            console.error('时间格式化错误:', error);
            return '';
        }
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
        
        console.log('螃蟹聊天系统已销毁');
    }
}

// 初始化聊天系统
$(document).ready(function() {
    console.log('文档加载完成，初始化螃蟹聊天系统...');
    
    setTimeout(function() {
        var loadingContainer = document.getElementById('loadingContainer');
        if (loadingContainer) {
            loadingContainer.classList.add('hidden');
            setTimeout(function() {
                loadingContainer.remove();
            }, 300);
        }
    }, 500);
    
    window.customerChat = new CustomerChatSystem();
    
    $(window).on('beforeunload', function() {
        if (window.customerChat) {
            window.customerChat.destroy();
        }
    });
});
	</script>
</body>
</html>
