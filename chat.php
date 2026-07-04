<?php
if (!defined('FROM_ROUTER')) {
    http_response_code(403);
    exit('喜乐科技@yxhxzc888');
}
// 必须在任何输出之前启动会话，避免"headers already sent"错误
try {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    // 获取当前登录的客服信息
    $currentAgent = $_SESSION['username'] ?? '未知';
    $currentRole = $_SESSION['role'] ?? 'user';
} catch (Exception $e) {
    // 会话启动失败时的处理
    error_log("会话启动失败: " . $e->getMessage());
    $currentAgent = '未知';
    $currentRole = 'user';
}

// 检查必要文件是否存在
if (!file_exists($_SERVER['DOCUMENT_ROOT'] . '/config/dbconfig.php')) {
    die('数据库配置文件不存在');
}

if (!file_exists($_SERVER['DOCUMENT_ROOT'] . '/config/functions.php')) {
    die('函数文件不存在');
}

// 加载配置文件
try {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/config/dbconfig.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/config/functions.php';
    
    // 检查checkLogin函数是否存在并执行
    if (function_exists('checkLogin')) {
        checkLogin();
    }
} catch (Exception $e) {
    error_log("加载配置文件时出错: " . $e->getMessage());
    die('加载配置文件时出错: ' . $e->getMessage());
}

// 强制刷新输出缓冲区，确保加载动画立即显示
ob_start();

$page_start_time = microtime(true);
$load_stage = '开始加载';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
 <meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no,viewport-fit=cover">
	<meta name="theme-color" content="#ffffff">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">

<!-- 隐藏状态栏样式 -->
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="XEKEFU">
	<link rel="manifest" href="/manifest.php">
	<link rel="apple-touch-icon" href="/xe-icon.png">
	<meta name="description" content="在线客户服务平台">
	<meta name="keywords" content="客服,咨询,服务">
	<meta name="robots" content="noindex, nofollow">
	<title>XEkefu - 加载中...</title>
	<link rel="icon" type="image/x-icon" href="/favicon.png">

	<!-- 延迟加载其他样式 -->
	<link href="/assets/bootstrap-icons.css" rel="stylesheet" media="print" onload="this.media='all'">
    <link href="/assets/Home/app.css" rel="stylesheet" media="print" onload="this.media='all'">
    <link href="/assets/Home/more.css" rel="stylesheet" media="print" onload="this.media='all'">

	<!-- 提前加载jQuery库 -->
	<script src="/assets/jquery.min.js"></script>
	<!-- 在现有脚本之后添加 -->
    <script src="/js/websocket-client.js"></script>

</head>
<body>

<?php
// 强制刷新输出缓冲区，确保加载动画立即显示
// 注意：不要在已存在的输出缓冲区上嵌套调用ob_start()
if (ob_get_level() == 0) {
    ob_start();
}

$page_start_time = microtime(true);
$load_stage = '开始加载';

// 立即发送加载动画HTML
echo str_repeat(' ', 1024 * 64);
if (ob_get_level() > 0) {
    ob_flush();
}
flush();

// 检查必要文件是否存在
if (!file_exists($_SERVER['DOCUMENT_ROOT'] . '/config/dbconfig.php')) {
    die('数据库配置文件不存在');
}

if (!file_exists($_SERVER['DOCUMENT_ROOT'] . '/config/functions.php')) {
    die('函数文件不存在');
}

// 获取当前登录的客服信息
try {
    $currentAgent = $_SESSION['username'] ?? '未知';
    $currentRole = $_SESSION['role'] ?? 'user';
} catch (Exception $e) {
    die('获取客服信息时出错: ' . $e->getMessage());
}

// PHP部分：根据XEid参数获取特定会话
try {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/config/dbconfig.php';
    
    require_once $_SERVER['DOCUMENT_ROOT'] . '/config/functions.php';
    
    // 检查checkLogin函数是否存在
    if (function_exists('checkLogin')) {
        // 执行checkLogin函数
        checkLogin();
    } else {
        die('checkLogin函数不存在');
    }
} catch (Exception $e) {
    die('加载配置文件时出错: ' . $e->getMessage());
}

// 获取URL参数
$sessionKey = isset($_GET['XEid']) ? trim($_GET['XEid']) : null;
$customerName = isset($_GET['customer']) ? trim($_GET['customer']) : '未知客户';

// 验证会话是否存在
try {
    $db = getDB();
} catch (Exception $e) {
    die('数据库连接失败: ' . $e->getMessage());
}

$isValidSession = false;
$sessionData = [];
$platform = '默认';
$messages = [];
$sessionSettings = [
    'is_pinned' => false,
    'is_muted' => false
];

// 确保数据库连接正常
if (!$db) {
    error_log("[" . date('Y-m-d H:i:s') . "] 数据库连接失败");
    header('Location: /X-MSG?error=db_connection');
    exit;
}

// 验证会话参数
if (!$sessionKey) {
    error_log("[" . date('Y-m-d H:i:s') . "] 缺少会话参数");
    header('Location: /X-MSG?error=missing_session');
    exit;
}

// 检查会话有效性
try {
    // 检查会话是否存在且属于当前客服
    $query = "SELECT DISTINCT customer_name, session_key, platform 
              FROM chat_messages 
              WHERE session_key = ? AND agent_account = ? 
              LIMIT 1";
    
    $stmt = $db->prepare($query);
    
    if ($stmt === false) {
        throw new Exception("准备会话验证语句失败: " . $db->error);
    }
    
    $stmt->bind_param("ss", $sessionKey, $currentAgent);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $isValidSession = true;
        $sessionData = $row;
        $customerName = $row['customer_name'];
        $platform = isset($row['platform']) ? $row['platform'] : '默认';
    } else {
        error_log("[" . date('Y-m-d H:i:s') . "] 未找到会话记录: session_key=" . $sessionKey . ", agent=" . $currentAgent);
    }
    
    $stmt->close();
} catch (Exception $e) {
    error_log("[" . date('Y-m-d H:i:s') . "] 会话验证异常: " . $e->getMessage());
    die('会话验证异常: ' . $e->getMessage());
}

// 如果会话无效，跳转
if (!$isValidSession) {
    error_log("[" . date('Y-m-d H:i:s') . "] 无效会话，跳转至列表页。SessionKey: " . $sessionKey);
    header('Location: /X-MSG?error=invalid_session');
    exit;
}

// 消息历史将通过JavaScript异步加载

// 获取会话设置（置顶、免打扰等）
try {
    // 检查getSessionSettings函数是否存在
    if (function_exists('getSessionSettings')) {
        // 使用你提供的新API接口中的函数
        $sessionSettings = getSessionSettings($db, $sessionKey, $currentAgent);
    } else {
        // 使用默认设置
        $sessionSettings = [
            'is_pinned' => false,
            'is_muted' => false
        ];
    }
} catch (Exception $e) {
    error_log("[" . date('Y-m-d H:i:s') . "] 获取会话设置失败: " . $e->getMessage());
    // 使用默认设置
    $sessionSettings = [
        'is_pinned' => false,
        'is_muted' => false
    ];
}

// 调试日志（可选，生产环境可注释掉）
error_log("Messages for session $sessionKey: " . count($messages) . " messages found");
error_log("Session settings: " . print_r($sessionSettings, true));

// 确保所有变量都已初始化

// 添加会话设置函数（如果不存在）
if (!function_exists('getSessionSettings')) {
    function getSessionSettings($db, $sessionKey, $agentAccount) {
        // 简单的默认实现，你应该使用你API接口中的完整实现
        $settings = [
            'is_pinned' => false,
            'is_muted' => false
        ];
        
        try {
            $query = "SELECT is_pinned, is_muted FROM chat_settings 
                      WHERE session_key = ? AND agent_account = ?";
            $stmt = $db->prepare($query);
            
            if ($stmt) {
                $stmt->bind_param("ss", $sessionKey, $agentAccount);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($row = $result->fetch_assoc()) {
                    $settings['is_pinned'] = (bool)$row['is_pinned'];
                    $settings['is_muted'] = (bool)$row['is_muted'];
                }
                
                $stmt->close();
            }
        } catch (Exception $e) {
            error_log("获取会话设置错误: " . $e->getMessage());
        }
        
        return $settings;
    }
}


// 在现有的PHP代码中添加IP归属地查询函数
function getIPLocation($ip) {
    if (empty($ip) || $ip == '未知' || $ip == '127.0.0.1' || substr($ip, 0, 3) == '10.' || 
        substr($ip, 0, 7) == '192.168' || substr($ip, 0, 4) == '172.') {
        return '内网IP';
    }
    
    // 内存缓存
    static $memory_cache = [];
    if (isset($memory_cache[$ip])) {
        return $memory_cache[$ip];
    }
    
    // 缓存文件路径
    $cache_dir = $_SERVER['DOCUMENT_ROOT'] . '/cache';
    $cache_file = $cache_dir . '/ip_cache.json';
    $ip_cache = [];
    
    // 读取文件缓存
    if (file_exists($cache_file)) {
        $cache_content = @file_get_contents($cache_file);
        if ($cache_content) {
            $cache_data = @json_decode($cache_content, true);
            if ($cache_data && isset($cache_data['ips'])) {
                $ip_cache = $cache_data['ips'];
                // 检查缓存是否过期（24小时）
                if (isset($cache_data['timestamp']) && (time() - $cache_data['timestamp']) < 86400) {
                    if (isset($ip_cache[$ip])) {
                        $memory_cache[$ip] = $ip_cache[$ip];
                        return $ip_cache[$ip];
                    }
                }
            }
        }
    }
    
    // 方法1: 使用在线API查询（ip-api.com）
    $location = '未知';
    $url = "http://ip-api.com/json/{$ip}?lang=zh-CN";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 0.3);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0.1);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
    curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
    curl_setopt($ch, CURLOPT_FAILONERROR, true);
    $response = curl_exec($ch);
    curl_close($ch);
    
    if ($response) {
        $data = json_decode($response, true);
        if ($data && $data['status'] == 'success') {
            $country = $data['country'] ?? '';
            $region = $data['regionName'] ?? '';
            $city = $data['city'] ?? '';
            
            if ($country == '中国') {
                if (!empty($region) && !empty($city) && $region != $city) {
                    $location = $region . '·' . $city;
                } elseif (!empty($region)) {
                    $location = $region;
                } else {
                    $location = $country;
                }
            } else {
                $location = $country;
            }
        }
    }
    
    // 方法2: 备用API（淘宝IP库）
    if ($location == '未知') {
        $url2 = "http://ip.taobao.com/outGetIpInfo?ip={$ip}&accessKey=alibaba-inc";
        $ch2 = curl_init();
        curl_setopt($ch2, CURLOPT_URL, $url2);
        curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch2, CURLOPT_TIMEOUT, 0.5);
        curl_setopt($ch2, CURLOPT_CONNECTTIMEOUT, 0.2);
        curl_setopt($ch2, CURLOPT_FORBID_REUSE, true);
        curl_setopt($ch2, CURLOPT_FRESH_CONNECT, true);
        $response2 = curl_exec($ch2);
        curl_close($ch2);
        
        if ($response2) {
            $data = json_decode($response2, true);
            if ($data && $data['code'] == 0) {
                $region = $data['data']['region'] ?? '';
                $city = $data['data']['city'] ?? '';
                if (!empty($region) && !empty($city)) {
                    $location = $region . '·' . $city;
                }
            }
        }
    }
    
    // 保存到缓存
    $memory_cache[$ip] = $location;
    $ip_cache[$ip] = $location;
    
    // 保存到文件缓存
    if (!is_dir($cache_dir)) {
        @mkdir($cache_dir, 0755, true);
    }
    
    $cache_data = [
        'timestamp' => time(),
        'ips' => $ip_cache
    ];
    @file_put_contents($cache_file, json_encode($cache_data));
    
    return $location;
}

// 获取当前会话的IP地址
$client_ip = '未知';
$ip_location = '未知';

try {
    // 查询当前会话的IP地址（取最近的一条消息的IP）
    $query_ip = "SELECT client_ip FROM chat_messages 
                 WHERE session_key = ? AND agent_account = ? 
                 AND client_ip IS NOT NULL AND client_ip != '' 
                 ORDER BY created_at DESC LIMIT 1";
    $stmt_ip = $db->prepare($query_ip);
    
    if ($stmt_ip) {
        $stmt_ip->bind_param("ss", $sessionKey, $currentAgent);
        if ($stmt_ip->execute()) {
            $result_ip = $stmt_ip->get_result();
            if ($row_ip = $result_ip->fetch_assoc()) {
                $client_ip = $row_ip['client_ip'];
                $ip_location = getIPLocation($client_ip);
            }
        }
        $stmt_ip->close();
    }
} catch (Exception $e) {
    error_log("获取IP地址失败: " . $e->getMessage());
}

// 获取IP重复会话数量
$duplicate_ips = 0;
try {
    $query_dup = "SELECT COUNT(DISTINCT session_key) as dup_count 
                  FROM chat_messages 
                  WHERE client_ip = ? AND agent_account = ? 
                  AND session_key != ?";
    $stmt_dup = $db->prepare($query_dup);
    
    if ($stmt_dup) {
        $stmt_dup->bind_param("sss", $client_ip, $currentAgent, $sessionKey);
        if ($stmt_dup->execute()) {
            $result_dup = $stmt_dup->get_result();
            if ($row_dup = $result_dup->fetch_assoc()) {
                $duplicate_ips = $row_dup['dup_count'];
            }
        }
        $stmt_dup->close();
    }
} catch (Exception $e) {
    error_log("获取重复IP数量失败: " . $e->getMessage());
}

// 刷新并关闭输出缓冲区，确保所有内容都能正确输出
if (ob_get_level() > 0) {
    ob_flush();
    flush();
    ob_end_flush();
}
?>
	<style>
		:root {
		    --XEmsg-color-primary: #0066ff;
		    --XEmsg-color-background: #fff;
		    --XEmsg-color-text-primary: #2d2d2d;
		    --XEmsg-color-text-secondary: #757575;
		    --XEmsg-color-bubble-left: #fff;
		    --XEmsg-color-bubble-right: #e0ffc7;
		    --XEmsg-color-text-bubble-left: #1a1a1a;
		    --XEmsg-color-text-bubble-right: #1a1a1a;
		    --XEmsg-color-time-bubble: rgba(0,0,0,0.35);
		    --XEmsg-color-border: #c9c9c9;
		    --XEmsg-border-radius: 1rem;
		    --XEmsg-spacing-sm: 0.25rem;
		    --XEmsg-spacing-md: 0.5rem;
		    --XEmsg-spacing-lg: 1rem;
		    --XEmsg-font-size-sm: 0.6rem;
		    --XEmsg-font-size-md: 0.8rem;
		    --XEmsg-font-size-lg: 1rem;
		    --safe-area-inset-bottom: env(safe-area-inset-bottom);
		    --input-area-height: 3.5rem; /* 根据您输入框的实际高度调整 */
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
		
		html, body {
		    height: 100%;
		    width: 100%;
		    font-family:'Maple Mono CN Light';
		    font-weight:'400';
		    background: #fff;
		    margin: 0;
		    padding: 0;
		}
		
		.XEmsg-app {
		     display: flex;
		    flex-direction: column;
		    height: 100vh; /* 关键：使用视口高度 */
		    width: 100%;
		}
		
.XEmsg-header {
    width: 100%;
    display: flex;
    align-items: center;
    padding: 0;
    box-sizing: border-box;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 100;
    min-height: 3.5rem;
    max-height: 3.5rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.3);
    
    /* 改进的玻璃效果 */
     background: linear-gradient(
        135deg,
        rgba(255, 255, 255, 0.8) 0%,
        rgba(255, 255, 255, 0.6) 100%
    );
    backdrop-filter: blur(20px) saturate(180%);
    -webkit-backdrop-filter: blur(20px) saturate(180%);
    overflow: hidden;
}
		
		.XEmsg-header__back {
		    margin: 16px;
		    min-width: 1rem;
		    font-size: 1.5rem;
		    color: #333;
		    cursor: pointer;
		}
		
		.XEmsg-header__info {
		    margin-top:6px;
		    display: flex;
		    flex-direction: column;
		    font-size: 0.95rem;
		    color: #333;
		    padding: 0.375rem 0;
		    width: 100%;
		    overflow: hidden;
		}
		
		.XEmsg-header__title {
		    font-weight: 600;
		    text-overflow: ellipsis;
		    overflow: hidden;
		    word-break: keep-all;
		    line-height: 1.25rem;
		    height: 1.25rem;
		    font-size: 1rem;
		}
		
		.XEmsg-header__status {
		    color: #999;
		    display: flex;
		    align-items: center;
		    gap: 0.375rem;
		    width: fit-content;
		    border-radius: 0.25rem;
		}
		
		.XEmsg-header__actions {
		    display: flex;
		    align-items: center;
		    margin-left: auto;
		    margin-right: 0.75rem;
		    transition: all 0.3s ease;
		    gap: 0.5rem;
		}
		
		.XEmsg-header__action {
		    border-radius: 0.25rem;
		    display: flex;
		    align-items: center;
		    justify-content: center;
		    font-size: 1.5rem;
		    color: #666;
		    cursor: pointer;
		    padding: 0.25rem;
		}
		
		.XEmsg-header__action:active {
		    color: #9c9c9c;
		}
		
		.XEmsg-content {
		  flex: 1;
		  position: relative;
		  min-height: 0;
		  overflow: hidden;
		  display: flex; /* 添加flex布局 */
		  flex-direction: column; /* 垂直方向 */
		}
		
.XEmsg-background {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  background: #dbdbdb;
  background-size: cover;
  background-repeat: no-repeat;
  background-position: center center;
}

/* pattern类型背景的渐变叠加层 */
.XEmsg-background--pattern::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: linear-gradient(135deg, rgba(74,123,255,0.85) 0%, rgba(138,180,255,0.85) 100%);
  z-index: 1;
}
		
		.XEmsg-canvas {
		    position: absolute;
		    top: 0;
		    left: 0;
		    width: 100%;
		    height: 100%;
		}
		
		/* 消息列表 */
		       .XEmsg-messages {
		  /* 关键：使用flex:1占据剩余空间，并允许滚动 */
		  flex: 1;
		  min-height: 0; /* 重要：允许在flex容器中收缩 */
		  overflow-y: auto;
		  overflow-x: hidden;
		  padding-top: 3.5rem; /* 为顶部导航栏留出空间 */
		  padding-bottom: 1rem;
		  box-sizing: border-box;
		  /* 移除绝对定位相关的样式 */
		  position: relative;
		  /* 确保在IE/Edge中正常工作 */
		  -ms-overflow-style: none;
		  scrollbar-width: none;
		   padding-bottom: calc(var(--input-area-height) + 0.5rem + var(--safe-area-inset-bottom, 0px));
		}
		
		.XEmsg-messages::-webkit-scrollbar-thumb {
		  background: rgba(0, 0, 0, 0.1);
		  border-radius: 2px;
		}
		      /* 确保消息容器正确布局 */
		.XEmsg-message-container {
		  margin-top: 5px;
		  margin-bottom: 5px;
		  display: flex;
		  align-items: center;
		  width: 100%;
		  font-size: 0.6rem;
		  color: #757575;
		  flex-direction: column;
		  animation: XEmsg-fadeIn 0.24s ease;
		  animation-fill-mode: forwards;
		  animation-delay: var(--delay);
		  opacity: 0;
		}
		
		.date-separator {
		    display: flex;
		    justify-content: center;
		    align-items: center;
		    width: 100%;
		    padding: 8px 0 4px;
		}
		
		.date-separator .date-text {
		    background: rgba(0,0,0,0.3);
		    color: #fff;
		    padding: 4px 12px;
		    border-radius: 12px;
		    font-size: 12px;
		    font-weight: 500;
		}
		
		.XEmsg-message {
		    width: 100%;
		    padding: 1px 8px;
		    display: flex;
		    box-sizing: border-box;
		    align-items: flex-end;
		}
		
		.XEmsg-message--incoming {
		    justify-content: flex-start;
		    transform: translateX(calc(-100% - 1.875rem));
		    animation: XEmsg-slideInLeft 0.24s ease;
		    animation-delay: var(--delay);
		    animation-fill-mode: forwards;
		    padding-right: 3.625rem;
		}
		
		.XEmsg-message--outgoing {
		    flex-direction: row-reverse;
		    animation: XEmsg-slideInRight 0.24s ease;
		    animation-delay: var(--delay);
		    animation-fill-mode: forwards;
		    transform: translateX(calc(100% + 1.875rem));
		    padding-left: 3.625rem;
		}
		
		.XEmsg-message__bubble {
		    padding: 6px 10px 4px;
		    border-radius: 12px 12px 12px 4px;
		    max-width: 80%;
		    width: fit-content;
		    word-wrap: break-word;
		    line-height: 1.35rem;
		    word-break: break-word;
		    position: relative;
		    display: inline-block;
		    box-shadow: 0 1px 2px rgba(0,0,0,0.08);
		}
		
		.XEmsg-message__bubble--incoming {
		    background-color: var(--XEmsg-color-bubble-left, #ffffff);
		    color: var(--XEmsg-color-text-bubble-left, #1a1a1a);
		    border-radius: 12px 12px 12px 4px;
		}
		
		.XEmsg-message__bubble--outgoing {
		    background-color: var(--XEmsg-color-bubble-right, #e3fee0);
		    color: var(--XEmsg-color-text-bubble-right, #1a1a1a);
		    border-radius: 12px 12px 4px 12px;
		}
		
		.XEmsg-message__time {
		    word-break: keep-all;
		    font-size: 11px;
		    line-height: 1;
		    padding-left: 6px;
		    color: var(--XEmsg-color-time-bubble, rgba(0,0,0,0.35));
		    white-space: nowrap;
		    display: inline;
		    vertical-align: baseline;
		}
		
		.XEmsg-message__bubble--outgoing .XEmsg-message__time {
		    color: var(--XEmsg-color-time-bubble, rgba(0,0,0,0.35));
		}
		
		.XEmsg-message__status {
		    word-break: keep-all;
		    font-size: 11px;
		    line-height: 1;
		    display: inline-flex;
		    align-items: center;
		    gap: 2px;
		}
		
		.XEmsg-message__time, .XEmsg-message__status {
		    display: inline-flex;
		    align-items: center;
		    gap: 2px;
		}
		
		.message-status {
		    font-size: 20px;
		    color: rgba(0,0,0,0.3);
		    transition: color 0.3s ease;
		    vertical-align: middle;
		}
		
		.message-status.read {
		    color: #4fae4e;
		}
		    justify-content: center;
		    gap: 0.0625rem;
		}
		
		.XEmsg-message__bubble--incoming .XEmsg-message__status {
		    color: rgba(0,0,0,0.25);
		}
		
		.XEmsg-message__bubble--outgoing .XEmsg-message__status {
		    color: rgba(0,0,0,0.3);
		}
		
		       .XEmsg-input {
		  position: fixed; /* 将 absolute 改为 fixed，使其始终固定在视口底部 */
		  bottom: 0;
		  left: 0;
		  width: 100%;
		  z-index: 100; /* 确保输入区域不被其他元素覆盖 */
		  /* 使用 calc() 函数将原有的 padding-bottom 与安全区域相加 */
		  padding-bottom: calc(0.5rem + var(--safe-area-inset-bottom, 0px));
		  /* 确保背景色和模糊效果覆盖到底部 */
		  background-color: rgba(255, 255, 255, 0.15)!important;
		  backdrop-filter: blur(0.375rem);
		  -webkit-backdrop-filter: blur(0.375rem);
		  box-sizing: border-box;
		}
		
		.XEmsg-input__actions {
		    display: flex;
		    align-items: center;
		    background-color: transparent;
		    width: 100%;
		    box-sizing: border-box;
		    overflow-x: scroll;
		    gap: 0.5rem;
		    padding: 0 0.5rem;
		    max-height: 0;
		    position: relative;
		    opacity: 0;
		}
		
		.XEmsg-input__actions--expanded {
		    max-height: 2.75rem;
		    padding: 0.5rem;
		    padding-bottom: 3px;
		    opacity: 1;
		}
		
		.XEmsg-input__action {
		    display: flex;
		    align-items: center;
		    font-size: 0.9rem;
		    padding: 0 0.875rem;
		    border-radius: 10px;
		    height: 0;
		    background: #fff;
		    box-shadow: none!important;
		    word-break: keep-all;
		    cursor: pointer;
		}
		
		.XEmsg-input__actions--expanded > .XEmsg-input__action {
		    height: 2.125rem;
		}
		
		.XEmsg-input__action > i {
		    font-size: 17px;
		    margin-right: 0.375rem;
		}
		
		.XEmsg-input__wrapper {
		    display: flex;
		    align-items: center;
		    position: relative;
		    margin: 0 0.5rem;
		    margin-top: 0.5rem;
		    box-sizing: border-box;
		    border-radius: 5.3125rem;
		    overflow: hidden;
		    box-shadow: 0 0 1px #999;
		}
		
		.XEmsg-input__field {
		    flex: 1;
		    padding: 0 1.125rem;
		    height: 2.5rem;
		    line-height: 2.5rem;
		    font-size: 1rem;
		    border-radius: 0;
		    border-left: 1px solid #e0e0e0;
		    padding-right: 4.5rem;
		    box-sizing: border-box;
		    font-family: inherit;
		    white-space: nowrap;
		    resize: none;
		    transition: all 0.3s ease;
		    background-color: rgba(255, 255, 255, 0.93);
		}
		
		       /* 发送按钮基础样式 */
		.XEmsg-send-button {
		  position: absolute;
		  right: 0.5rem;
		  top: 50%;
		  transform: translateY(-50%);
		  background: linear-gradient(135deg, var(--XEmsg-color-primary), #004ecc); /* 渐变背景 */
		  color: white;
		  border: none;
		  border-radius: 50%; /* 圆形按钮 */
		  width: 2.25rem;
		  height: 2.25rem;
		  display: flex;
		  align-items: center;
		  justify-content: center;
		  cursor: pointer;
		  opacity: 0;
		  visibility: hidden;
		  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); /* 平滑的过渡动画 */
		  z-index: 2;
		  box-shadow: 0 2px 8px rgba(0, 102, 255, 0.3); /* 添加阴影增强立体感 */
		}
		
		/* 按钮可见状态 */
		.XEmsg-send-button--visible {
		  opacity: 1;
		  visibility: visible;
		}
		
		/* 鼠标悬停效果：颜色变亮，阴影加深，轻微上浮 */
		.XEmsg-send-button:hover {
		  background: linear-gradient(135deg, #1a7cff, #0062e6);
		  box-shadow: 0 4px 12px rgba(0, 102, 255, 0.4);
		  transform: translateY(-50%) scale(1.05); /* 轻微放大效果 */
		}
		
		/* 按钮点击效果：颜色变深，阴影收紧，模拟按下 */
		.XEmsg-send-button:active {
		  background: linear-gradient(135deg, #0052cc, #003d99);
		  box-shadow: 0 1px 4px rgba(0, 102, 255, 0.5);
		  transform: translateY(-50%) scale(0.95);
		  transition-duration: 0.1s; /* 点击反馈更迅速 */
		}
		
		/* 为图标添加微妙的缩放动画，增强交互感 */
		.XEmsg-send-button i {
		  transition: transform 0.2s ease;
		}
		.XEmsg-send-button:hover i {
		  transform: translateX(1px); /* 悬停时图标轻微右移 */
		}
		
		/* 动画定义 */
		@keyframes XEmsg-fadeIn {
		    0% { opacity: 0; }
		    100% { opacity: 1; }
		}
		
		@keyframes XEmsg-slideInLeft {
		    0% {
		        opacity: 0;
		        transform: translateX(calc(-100% - 0.75rem));
		    }
		    100% {
		        opacity: 1;
		        transform: translateX(0);
		    }
		}
		
		@keyframes XEmsg-slideInRight {
		    0% {
		        opacity: 0;
		        transform: translateX(calc(100% + 0.75rem));
		    }
		    100% {
		        opacity: 1;
		        transform: translateX(0);
		    }
		}
		
		/* 响应式设计 */
		@media screen and (min-width: 1024px) {
		    .XEmsg-header {
		        min-height: 3rem;
		        max-height: 3rem;
		        padding-left: 0.75rem;
		        box-sizing: border-box;
		    }
		}
		.XEmsg-message__text{
		    font-size:15px;
		    line-height:1.4;
		}
		
/* 底部弹窗基础样式 - 与mac-modal保持一致 */
.bottom-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 999;
    display: flex;
    justify-content: center;
    align-items: flex-end;
    background: rgba(0, 0, 0, 0);
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.3s ease, visibility 0.3s ease;
    pointer-events: none;
    transform: translateZ(0);
}

.bottom-modal.show {
    opacity: 1;
    visibility: visible;
    pointer-events: auto;
    background: rgba(0, 0, 0, 0.5);
    transition: opacity 0.3s ease, visibility 0.3s ease;
}

.bottom-modal-box {
    background: rgba(255, 255, 255, 0.9);
    width: 100%;
    max-width: 450px;
    border-radius: 20px 20px 0 0;
    box-shadow: 0 -10px 40px rgba(0, 0, 0, 0.15);
    transform: translateY(100%) translateZ(0);
    will-change: transform;
    backface-visibility: hidden;
    -webkit-backface-visibility: hidden;
    transform-style: preserve-3d;
    opacity: 1;
    transition: transform 0.3s cubic-bezier(0.1, 0.7, 0.1, 1);
    max-height: 75vh;
    overflow-y: auto;
    position: relative;
    margin: 0;
}

.bottom-modal.show .bottom-modal-box {
    transform: translateY(0) translateZ(0);
}

.bottom-modal-header {
    padding: 18px 20px;
    border-bottom: 1px solid #f0f0f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: sticky;
    top: 0;
    background: rgba(255, 255, 255, 0.95);
    z-index: 1;
}

.bottom-modal-title {
    font-size: 16px;
    font-weight: 600;
    color: #1a1a1a;
}

.bottom-modal-close {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background: #f5f5f5;
    border: none;
    font-size: 14px;
    color: #666;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.15s ease;
}

.bottom-modal-close:hover {
    background: #e8e8e8;
}

.bottom-modal-close:active {
    transform: scale(0.95);
}

.bottom-modal-body {
    background:white;
    padding: 12px;
}

.bottom-modal-footer {
    padding: 12px 16px;
    border-top: 1px solid #f0f0f0;
    position: sticky;
    bottom: 0;
    background: rgba(255, 255, 255, 0.95);
}

/* 优化 .mac-modal 动画 */
.mac-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 1000;
    display: flex;
    justify-content: center;
    align-items: flex-end;
    background: rgba(0, 0, 0, 0);
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.3s ease, visibility 0.3s ease; /* 只过渡这两个属性 */
    pointer-events: none;
    transform: translateZ(0); /* 启用GPU加速 */
}

.mac-modal.active {
    opacity: 1;
    visibility: visible;
    pointer-events: auto;
    background: rgba(0, 0, 0, 0.5); /* 背景色渐变 */
    transition: opacity 0.3s ease, visibility 0.3s ease; /* 保持一致的过渡 */
}

/* 修改 .mac-modal-content 的 transform */
.mac-modal-content {
    background: rgba(255, 255, 255, 0.85);
    width: 100%;
    border-radius: 20px 20px 0 0;
    box-shadow: 0 -10px 40px rgba(0, 0, 0, 0.15);
    transform: translateY(100%) translateZ(0); /* 添加 translateZ(0) 启用GPU加速 */
    will-change: transform; /* 提示浏览器优化 */
    backface-visibility: hidden; /* 修复闪烁 */
    -webkit-backface-visibility: hidden;
    transform-style: preserve-3d; /* 启用3D加速 */
    opacity: 1;

    transition: transform 0.3s cubic-bezier(0.1, 0.7, 0.1, 1); /* 更平滑的缓动 */
    max-width: 100%;
    max-height: 80vh;
    overflow-y: auto;
    position: relative;
    margin: 0;
}

.mac-modal.active .mac-modal-content {
    transform: translateY(0) translateZ(0); /* 保持GPU加速 */
    opacity: 1;
}
		
	.mac-modal-header {
    padding: 20px;
    text-align: center;
    border-bottom: 1px solid #e5e5ea;
    position: sticky;
    top: 0;
    background: #ffffff;
    z-index: 1;
    border-radius: 20px 20px 0 0;
}

.mac-modal-body {
    background: #fff;
    padding: 20px;
    flex: 1; /* 占据剩余空间 */
    overflow-y: auto; /* 允许滚动 */
}

.mac-modal-footer {
    padding: 20px;
    border-top: 1px solid rgba(0, 0, 0, 0.1);
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    position: sticky; /* 使底部按钮在滚动时固定 */
    bottom: 0;
    background: rgba(255, 255, 255, 0.85);
    z-index: 1;

}
		
		.mac-modal-header h3 {
		    margin: 0;
		    font-size: 17px;
		    font-weight: 600;
		    color: #1c1c1e;
		    letter-spacing: -0.2px;
		}
		
	
		
		.mac-modal-input {
		    width: 100%;
		    padding: 10px;
		    border: 1px solid #ddd;
		    border-radius: 8px;
		    font-size: 14px;
		    background: rgba(255, 255, 255, 0.7);
		}
		
		.mac-modal-text {
		    color: #666;
		    font-size: 14px;
		    line-height: 2;
		}
		
		.mac-modal-btn {
		    padding: 12px 24px;
		    border-radius: 8px;
		    border: none;
		    font-size: 16px;
		    font-weight: 500;
		    cursor: pointer;
		    transition: all 0.15s ease;
		    -webkit-tap-highlight-color: transparent;
		}
		
		.mac-modal-btn-primary {
		    background: #007aff;
		    color: white;
		}
		
		.mac-modal-btn-primary:hover {
		    background: #0066cc;
		}
		
		.mac-modal-btn-primary:active {
		    background: #0052aa;
		    transform: scale(0.98);
		}
		
		.mac-modal-btn-secondary {
		    background: transparent;
		    color: #007aff;
		}
		
		.mac-modal-btn-secondary:hover {
		    background: rgba(0, 122, 255, 0.1);
		}
		
		.mac-modal-btn-secondary:active {
		    background: rgba(0, 122, 255, 0.2);
		    transform: scale(0.98);
		}
		
		.mac-modal-btn-danger {
		    background: #ff3b30;
		    color: white;
		}
		
		.mac-modal-btn-danger:hover {
		    background: #e6362d;
		}
		
		.mac-modal-btn-danger:active {
		    background: #cc2f28;
		    transform: scale(0.98);
		}
		
		.info-grid {
		    display: grid;
		    grid-template-columns: 1fr 1fr;
		    gap: 12px;
		    margin-top: 16px;
		}
		
		.info-item {
		    background: #f5f5f7;
		    border-radius: 12px;
		    padding: 14px 12px;
		    text-align: center;
		    transition: all 0.15s ease;
		}
		
		.info-item:active {
		    background: #e8e8ed;
		    transform: scale(0.98);
		}
		
		.info-label {
		    font-size: 12px;
		    color: #86868b;
		    margin-bottom: 6px;
		    display: block;
		    font-weight: 400;
		}
		
		.info-value {
		    font-size: 17px;
		    color: #1c1c1e;
		    font-weight: 600;
		    display: block;
		}
		
		.settings-group {
		    background: #fff;
		    border-radius: 16px;
		    margin-bottom: 16px;
		    overflow: hidden;
		}
		
		.settings-row {
		    display: flex;
		    align-items: center;
		    padding: 15px 16px;
		    border-bottom: 1px solid #f0f0f2;
		}
		
		.settings-row:last-child {
		    border-bottom: none;
		}
		
		.settings-row:active {
		    background-color: #f5f5f7;
		}
		
		.settings-row.warning-row {
		    background-color: #fff5f5;
		}
		
		.settings-row.warning-row:active {
		    background-color: #ffebee;
		}
		
		.settings-label {
		    font-size: 17px;
		    color: #1c1c1e;
		    flex: 1;
		}
		
		.settings-value {
		    font-size: 17px;
		    color: #86868b;
		    text-align: right;
		}
		
		.settings-value.mono-text {
		    font-family: 'SF Mono', Monaco, 'Courier New', monospace;
		    font-size: 14px;
		    background: #f5f5f7;
		    padding: 4px 8px;
		    border-radius: 6px;
		}
		
		.settings-value.location-text {
		    color: #007aff;
		}
		
		.settings-value.warning-text {
		    color: #ff3b30;
		}
		
		/* 开关按钮样式 */
		.switch-container {
		    display: flex;
		    justify-content: space-between;
		    align-items: center;
		    padding: 12px 0;
		    border-bottom: 1px solid rgba(0, 0, 0, 0.08);
		}
		
		.switch-container:last-child {
		    border-bottom: none;
		}
		
		.switch-label {
		    display: flex;
		    flex-direction: column;
		}
		
		.switch-title {
		    font-size: 16px;
		    font-weight: 500;
		    color: #333;
		    margin-bottom: 4px;
		}
		
		.switch-subtitle {
		    font-size: 13px;
		    color: #999;
		}
		
		.switch {
		    position: relative;
		    display: inline-block;
		    width: 51px;
		    height: 31px;
		}
		
		.switch input {
		    opacity: 0;
		    width: 0;
		    height: 0;
		}
		
		.slider {
		    position: absolute;
		    cursor: pointer;
		    top: 0;
		    left: 0;
		    right: 0;
		    bottom: 0;
		    background-color: #e9e9eb;
		    transition: .4s;
		    border-radius: 34px;
		}
		
		.slider:before {
		    position: absolute;
		    content: "";
		    height: 27px;
		    width: 27px;
		    left: 2px;
		    bottom: 2px;
		    background-color: white;
		    transition: .4s;
		    border-radius: 50%;
		    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
		}
		
		input:checked + .slider {
		    background-color: #007AFF;
		}
		
		input:checked + .slider:before {
		    transform: translateX(20px);
		}
		
		/* 开关容器动画 */
		.switch-container {
		    transition: all 0.2s ease;
		}
		
		.switch-container:active {
		    background-color: rgba(0, 0, 0, 0.05);
		    border-radius: 8px;
		}
		
		/* 话术面板样式 */
		.phrases-panel {
		    position: fixed;
		    background: white;
		    border-radius: 12px;
		    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
		    z-index: 1000;
		    max-width: 300px;
		    display: none;
		    
		       /* 增加高度 */
		    max-height: 500px; /* 从400px增加到500px */
		    min-height: 400px; /* 添加最小高度 */
		    
		    overflow-y: auto;
		    scrollbar-width: none;
		    -ms-overflow-style: none;
		}
		
		.phrases-panel::-webkit-scrollbar {
		    display: none;
		    width: 0;
		    height: 0;
		}
		
		/* 增加分组内容的高度限制 */
		.phrase-group-content.expanded {
		    max-height: 300px; /* 增加分组内容的高度 */
		}
		
		/* 调整内部布局，使空间利用更高效 */
		.phrase-item {
		    padding: 8px 16px; /* 减小内边距，显示更多项 */
		    font-size: 13px;
		    line-height: 1.3; /* 减小行高 */
		}
		
		.phrase-group-header {
		    padding: 10px 16px; /* 减小分组标题内边距 */
		}
		
		.phrase-item:hover {
		    background: #f5f5f5;
		}
		
		.toast-message {
		    position: fixed;
		    top: 6%;
		    left: 50%;
		    transform: translate(-50%, -50%);
		    background: rgba(0,0,0,0.8);
		    color: white;
		    padding: 12px 20px;
		    border-radius: 8px;
		    z-index: 10000;
		}
				/* 图片消息特殊样式 */
				.XEmsg-message--image .XEmsg-message__content {
				    display: flex;
				    flex-direction: column;
				    align-items: flex-start;
				}
				
				.message-image-container {
				    display: inline-block;
				}
				
				.message-image {
				    max-width: 200px;
				    max-height: 200px;
				    border-radius: 12px;
				    display: block;
				    cursor: pointer;
				    object-fit: cover;
				}
				
				.message-image-placeholder {
				    color: #999;
				    font-style: italic;
				}
				
				/* 图片加载失败提示 */
				.XEmsg-message--image .image-error {
				    color: #999;
				    font-style: italic;
				    padding: 10px;
				}

				.message-goods {
				    background: #fff;
				    border-radius: 3.2vw;
				    font-family: PingFang SC;
				    padding: 3.2vw;
				    width: 68vw;
				    max-width: 280px;
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
				    width: 14.93333vw;
				    margin-right: 2.13333vw;
				    object-fit: cover;
				    object-position: left top;
				    background: #f2f2f2;
				    font-size: 0;
				}

				.message-goods .goods-content .content-box .pz-image img {
				    border-radius: inherit;
				    height: inherit;
				    object-fit: cover;
				    object-position: left;
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

				.message-goods .ellipsis-1 {
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
		/* 在线状态指示器 */
		.status-indicator {
		    display: inline-block;
		    width: 8px;
		    height: 8px;
		    border-radius: 50%;
		    margin-right: 5px;
		}
		
		.status-online {
		    background-color: #52c41a;
		}
		
		.status-offline {
		    background-color: #999;
		}
		
		.status-away {
		    background-color: #faad14;
		}
		
		/* 标题状态动画 */
		@keyframes pulse {
		    0% { opacity: 1; }
		    50% { opacity: 0.5; }
		    100% { opacity: 1; }
		}
		
		.title-status-pulse {
		    animation: pulse 2s infinite;
		}
		
		.retry-btn {
		  /* 基础样式 */
		  display: inline-flex;
		  align-items: center;
		  gap: 6px;
		  padding: 8px 16px;
		  font-size: 14px;
		  font-weight: 500;
		  color: #fff;
		  background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
		  border: none;
		  border-radius: 8px;
		  cursor: pointer;
		  transition: all 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
		  box-shadow: 0 2px 6px rgba(0, 123, 255, 0.3);
		  position: relative;
		  overflow: hidden;
		}
		
		/* 悬停效果 */
		.retry-btn:hover {
		  background: linear-gradient(135deg, #0056b3 0%, #004494 100%);
		  transform: translateY(-2px);
		  box-shadow: 0 4px 12px rgba(0, 123, 255, 0.4);
		}
		
		/* 点击效果 */
		.retry-btn:active {
		  transform: translateY(0);
		  box-shadow: 0 1px 3px rgba(0, 123, 255, 0.5);
		}
		
		/* 焦点样式 */
		.retry-btn:focus {
		  outline: none;
		  box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.3);
		}
		
		/* 图标动画 */
		.retry-btn i {
		  transition: transform 0.3s ease;
		  font-size: 14px;
		}
		
		.retry-btn:hover i {
		  transform: rotate(30deg);
		}
		
		/* 加载状态动画 */
		.retry-btn.loading i {
		  animation: spin 1s linear infinite;
		}
		
		.retry-btn.loading {
		  pointer-events: none;
		  opacity: 0.8;
		}
		
		/* 旋转动画 */
		@keyframes spin {
		  0% { transform: rotate(0deg); }
		  100% { transform: rotate(360deg); }
		}
		
		/* 禁用状态 */
		.retry-btn:disabled {
		  background: #6c757d;
		  cursor: not-allowed;
		  transform: none;
		  box-shadow: none;
		}
		
		.retry-btn:disabled:hover {
		  transform: none;
		  box-shadow: none;
		}
		
		/* 为内部元素添加优化 */
.mac-modal-header,
.mac-modal-body,
.mac-modal-footer {
    transform: translateZ(0); /* 启用硬件加速 */
    will-change: transform; /* 提示浏览器优化 */
}

/* 假人面板样式 - 抽屉式弹窗 */
.dummy-panel {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    width: 100%;
    height: 70vh;
    background: white;
    border-radius: 20px 20px 0 0;
    box-shadow: 0 -10px 40px rgba(0, 0, 0, 0.15);
    z-index: 10000;
    display: flex;
    flex-direction: column;
    opacity: 0;
    visibility: hidden;
    pointer-events: none;
    transform: translateY(100%) translateZ(0);
    will-change: transform;
    backface-visibility: hidden;
    -webkit-backface-visibility: hidden;
    transition: transform 0.3s cubic-bezier(0.1, 0.7, 0.1, 1), 
                opacity 0.3s ease, 
                visibility 0.3s ease;
}

.dummy-panel.show {
    opacity: 1;
    visibility: visible;
    pointer-events: auto;
    transform: translateY(0) translateZ(0);
}

.dummy-panel-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.5);
    z-index: 9999;
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.3s ease, visibility 0.3s ease;
}

.dummy-panel-overlay.show {
    opacity: 1;
    visibility: visible;
}
		
		.dummy-option {
		    padding: 12px;
		    border-bottom: 1px solid #f5f5f5;
		}
		
		.dummy-option label {
		    display: block;
		    margin-bottom: 6px;
		    font-size: 14px;
		    color: #fff;
		    font-weight: 500;
		}
		
		.dummy-option input, .dummy-option select {
		    width: 100%;
		    padding: 8px 12px;
		    border: 1px solid #ddd;
		    border-radius: 6px;
		    font-size: 14px;
		    box-sizing: border-box;
		}
		
		.dummy-save-btn, .dummy-toggle-btn {
		    width: 100%;
		    padding: 10px;
		    margin: 5px 0;
		    border: none;
		    border-radius: 6px;
		    font-size: 14px;
		    cursor: pointer;
		    transition: all 0.2s;
		}
		
		.dummy-save-btn {
		    background: #f0f0f0;
		    color: #333;
		}
		
		.dummy-save-btn:hover {
		    background: #e0e0e0;
		}
		
		.dummy-toggle-btn {
		    background: #007AFF;
		    color: white;
		}
		
		.dummy-toggle-btn:hover {
		    background: #0056cc;
		}
		
		.dummy-toggle-btn.active {
		    background: #ff3b30;
		}
		
		/* 假人模式呼吸动画 */
		@keyframes dummyBreath {
		    0%, 100% {
		        box-shadow: 0 0 5px rgba(0, 122, 255, 0.5);
		        transform: scale(1);
		    }
		    50% {
		        box-shadow: 0 0 20px rgba(0, 122, 255, 0.8);
		        transform: scale(1.05);
		    }
		}
		
		/* 默认隐藏假人按钮，根据平台动态显示 */
		#dummy-mode-btn {
		    display: none !important;
		}
		
		/* 平台匹配时显示假人按钮 */
		#dummy-mode-btn.platform-visible {
		    display: flex !important;
		    width: auto !important;
		    height: 2.125rem !important;
		    min-width: auto !important;
		    min-height: auto !important;
		    padding: 0 0.875rem !important;
		    margin: 0 !important;
		    opacity: 1 !important;
		    visibility: visible !important;
		    pointer-events: auto !important;
		    overflow: visible !important;
		    align-items: center !important;
		    border-radius: 10px !important;
		    box-shadow: none !important;
		}
		
		/* 假人模式激活状态 */
		#dummy-mode-btn.active {
		    background-color: #007AFF;
		    color: white;
		    animation: dummyBreath 2s ease-in-out infinite;
		}
		
		.dummy-status {
		    position: absolute;
		    top: -25px;
		    left: 0;
		    background: #007AFF;
		    color: white;
		    padding: 4px 8px;
		    border-radius: 4px;
		    font-size: 12px;
		    white-space: nowrap;
		}
		.XEmsg-message--dummy {
		    flex-direction: row-reverse;
		    animation: XEmsg-slideInRight 0.24s ease;
		    animation-delay: var(--delay);
		    animation-fill-mode: forwards;
		    transform: translateX(calc(100% + 1.875rem));
		    padding-left: 3.625rem;
		
		}
		.XEmsg-message__bubble--dummy {
		    background-color: #effdde;
		    color: #1a1a1a;
		    border-radius: 12px 12px 4px 12px;
		}
	
		/* 自定义短语面板样式 */
		.custom-phrases-panel {
		    position: fixed;
		    bottom: 60px;
		    right: 120px;
		    width: 300px;
		    background: #fff;
		    border-radius: 8px;
		    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
		    z-index: 1000;
		    display: none;
		    border: 1px solid #e0e0e0;
		}
		
		.custom-phrases-header {
		    padding: 12px;
		    border-bottom: 1px solid #f0f0f0;
		    display: flex;
		    justify-content: space-between;
		    align-items: center;
		    font-weight: bold;
		    background: #f8f9fa;
		    border-radius: 8px 8px 0 0;
		}
		
		.close-btn {
		    background: none;
		    border: none;
		    font-size: 18px;
		    cursor: pointer;
		    color: #666;
		}
		
		.close-btn:hover {
		    color: #333;
		}
		
		.custom-phrases-add {
		    padding: 12px;
		    display: flex;
		    gap: 8px;
		    border-bottom: 1px solid #f0f0f0;
		}
		
		#new-phrase-input {
		    flex: 1;
		    padding: 4px;
		    border: 1px solid #ddd;
		    border-radius: 4px;
		    font-size: 14px;
		}
		
		#add-phrase-btn {
		    padding: 8px 12px;
		    background: #007bff;
		    color: white;
		    border: none;
		    border-radius: 4px;
		    cursor: pointer;
		    font-size: 14px;
		}
		
		#add-phrase-btn:hover {
		    background: #0056b3;
		}
		
		.custom-phrases-list {
		    max-height: 300px;
		    overflow-y: auto;
		    padding: 8px 0;
		}
		
		.phrase-item {
		    display: flex;
		    justify-content: space-between;
		    align-items: center;
		    padding: 8px 12px;
		    cursor: pointer;
		
		}
		
		.phrase-item:hover {
		    background: #f8f9fa;
		}
		
		.phrase-text {
		    flex: 1;
		    font-size: 14px;
		    word-break: break-word;
		}
		
		.delete-phrase {
		    background: none;
		    border: none;
		    color: #ff4757;
		    cursor: pointer;
		    font-size: 16px;
		    padding: 4px;
		}
		
		.delete-phrase:hover {
		    color: #ff2e43;
		}
		
		.custom-phrases-footer {
		    padding: 12px;
		    text-align: center;
		    border-top: 1px solid #f0f0f0;
		}
		
		#clear-all-phrases {
		    padding: 6px 12px;
		    background: #ff4757;
		    color: white;
		    border: none;
		    border-radius: 4px;
		    cursor: pointer;
		    font-size: 14px;
		}
		
		#clear-all-phrases:hover {
		    background: #ff2e43;
		}
		
		/* 卡片项特殊样式 */
		.phrase-item-card {
		    background: linear-gradient(135deg, rgba(102, 126, 234, 0.05) 0%, rgba(118, 75, 162, 0.05) 100%);
		    border-left: 3px solid #ED2939;
		}
		
		.phrase-item-card .phrase-text {
		    color: #ED2939;
		    font-weight: 500;
		}
		
		.phrase-item-card:hover {
		    background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
		}
		
		/* 自定义短语按钮激活状态 */
		#custom-phrases-btn.active {
		    color: #007bff;
		}
		
			/* 自定义短语按钮激活状态 */
		#phrases-btn.active {
		    color: #007bff;
		}
		
		/* 卡片抽屉式弹窗样式 - 与mac-modal保持一致 */
		.card-drawer {
		    position: fixed;
		    top: 0;
		    left: 0;
		    width: 100%;
		    height: 100%;
		    z-index: 10000;
		    display: flex;
		    justify-content: center;
		    align-items: flex-end;
		    background: rgba(0, 0, 0, 0);
		    opacity: 0;
		    visibility: hidden;
		    transition: opacity 0.3s ease, visibility 0.3s ease;
		    pointer-events: none;
		    transform: translateZ(0);
		}
		
		.card-drawer.active {
		    opacity: 1;
		    visibility: visible;
		    pointer-events: auto;
		    background: rgba(0, 0, 0, 0.5);
		    transition: opacity 0.3s ease, visibility 0.3s ease;
		}
		
		.card-drawer-content {
		    position: relative;
		    width: 100%;
		    max-width: 600px;
		    background: rgba(255, 255, 255, 0.9);
		    border-radius: 20px 20px 0 0;
		    box-shadow: 0 -10px 40px rgba(0, 0, 0, 0.15);
		    transform: translateY(100%) translateZ(0);
		    will-change: transform;
		    backface-visibility: hidden;
		    -webkit-backface-visibility: hidden;
		    transform-style: preserve-3d;
		    opacity: 1;
		    transition: transform 0.3s cubic-bezier(0.1, 0.7, 0.1, 1);
		    max-height: 80vh;
		    overflow-y: auto;
		    margin: 0;
		}
		
		.card-drawer.active .card-drawer-content {
		    transform: translateY(0) translateZ(0);
		}
		
		.card-drawer-header {
		    padding: 20px;
		    border-bottom: 1px solid #f0f0f0;
		    display: flex;
		    justify-content: space-between;
		    align-items: center;
		    position: sticky;
		    top: 0;
		    background: white;
		    z-index: 10;
		}
		
		.card-drawer-header span {
		    font-size: 18px;
		    font-weight: 600;
		    color: #1f2937;
		}
		
		.card-drawer-close {
		    background: none;
		    border: none;
		    font-size: 20px;
		    color: #6b7280;
		    cursor: pointer;
		    padding: 4px;
		    border-radius: 4px;
		    transition: all 0.2s;
		}
		
		.card-drawer-close:hover {
		    background: #f3f4f6;
		    color: #1f2937;
		}
		
		.card-drawer-body {
		    background:white;
		    padding: 20px;
		}
		
		.card-form-group {
		    margin-bottom: 16px;
		}
		
		.card-form-group label {
		    display: block;
		    font-size: 14px;
		    font-weight: 500;
		    color: #374151;
		    margin-bottom: 6px;
		}
		
		.card-form-group input,
		.card-form-group textarea {
		    width: 100%;
		    padding: 10px 12px;
		    border: 1px solid #d1d5db;
		    border-radius: 8px;
		    font-size: 14px;
		    transition: all 0.2s;
		    font-family: inherit;
		}
		
		.card-form-group input:focus,
		.card-form-group textarea:focus {
		    outline: none;
		    border-color: #007aff;
		    box-shadow: 0 0 0 3px rgba(0, 122, 255, 0.1);
		}
		
		.card-form-group textarea {
		    resize: vertical;
		    min-height: 80px;
		}
		
		.card-preview {
		    margin-top: 20px;
		    padding: 16px;
		    background: #f9fafb;
		    border-radius: 12px;
		    border: 1px solid #e5e7eb;
		}
		
		.card-preview-title {
		    font-size: 13px;
		    font-weight: 600;
		    color: #6b7280;
		    margin-bottom: 12px;
		}
		
		.card-preview-content {
		    background: white;
		    border-radius: 8px;
		    padding: 12px;
		}
		
		.card-drawer-footer {
		    padding: 16px 20px;
		    border-top: 1px solid #f0f0f0;
		    display: flex;
		    gap: 12px;
		    position: sticky;
		    bottom: 0;
		    background: white;
		}
		
		/* 交易信息抽屉式弹窗样式 */
		/* 拉群列表项样式 */
		.invite-group-item {
		    display: flex;
		    align-items: center;
		    gap: 12px;
		    padding: 12px;
		    background: #f8f9fa;
		    border-radius: 10px;
		    cursor: pointer;
		    transition: background 0.2s;
		}
		.invite-group-item:hover {
		    background: #e9ecef;
		}
		.invite-group-item img {
		    width: 50px;
		    height: 50px;
		    border-radius: 8px;
		    object-fit: cover;
		    flex-shrink: 0;
		}
		.invite-group-item-info {
		    flex: 1;
		    min-width: 0;
		}
		.invite-group-item-name {
		    font-size: 14px;
		    font-weight: 500;
		    color: #333;
		    overflow: hidden;
		    text-overflow: ellipsis;
		    white-space: nowrap;
		}
		.invite-group-item-code {
		    font-size: 12px;
		    color: #999;
		    margin-top: 2px;
		}
		.invite-group-item-btn {
		    padding: 6px 14px;
		    background: #07C160;
		    color: white;
		    border: none;
		    border-radius: 6px;
		    font-size: 13px;
		    cursor: pointer;
		    flex-shrink: 0;
		}
		.invite-group-item-btn:hover {
		    background: #06ad56;
		}
		.trade-drawer-overlay {
		    position: fixed;
		    top: 0;
		    left: 0;
		    right: 0;
		    bottom: 0;
		    background: rgba(0,0,0,0.5);
		    z-index: 9999;
		    opacity: 0;
		    visibility: hidden;
		    transition: opacity 0.3s ease, visibility 0.3s ease;
		}
		
		.trade-drawer-overlay.show {
		    opacity: 1;
		    visibility: visible;
		}
		
		.trade-drawer {
		    position: fixed;
		    bottom: 0;
		    left: 0;
		    right: 0;
		    width: 100%;
		    max-height: 85vh;
		    background: white;
		    border-radius: 20px 20px 0 0;
		    box-shadow: 0 -10px 40px rgba(0, 0, 0, 0.15);
		    z-index: 10000;
		    opacity: 0;
		    visibility: hidden;
		    transform: translateY(100%) translateZ(0);
		    will-change: transform;
		    backface-visibility: hidden;
		    -webkit-backface-visibility: hidden;
		    transition: transform 0.3s cubic-bezier(0.1, 0.7, 0.1, 1), 
		                opacity 0.3s ease, 
		                visibility 0.3s ease;
		    overflow: hidden;
		    display: flex;
		    flex-direction: column;
		}
		
		.trade-drawer.show {
		    opacity: 1;
		    visibility: visible;
		    transform: translateY(0) translateZ(0);
		}
		
		.trade-drawer-header {
		    padding: 20px;
		    border-bottom: 1px solid #f0f0f0;
		    display: flex;
		    align-items: center;
		    justify-content: space-between;
		    font-weight: 600;
		    font-size: 18px;
		    flex-shrink: 0;
		}
		
		.trade-drawer-close {
		    background: none;
		    border: none;
		    font-size: 24px;
		    cursor: pointer;
		    color: #6b7280;
		    padding: 4px;
		    line-height: 1;
		    transition: all 0.2s;
		}
		
		.trade-drawer-close:hover {
		    background: #f3f4f6;
		    color: #1f2937;
		}
		
		.trade-drawer-body {
		    background: white;
		    padding: 20px;
		    overflow-y: auto;
		    flex: 1;
		}
		
		.trade-form-group {
		    margin-bottom: 20px;
		}
		
		.trade-form-group label {
		    display: block;
		    font-size: 14px;
		    font-weight: 600;
		    color: #374151;
		    margin-bottom: 8px;
		}
		
		.trade-form-group input[type="text"],
		.trade-form-group textarea,
		.trade-form-group select {
		    width: 100%;
		    padding: 12px;
		    border: 1px solid #e5e7eb;
		    border-radius: 8px;
		    font-size: 14px;
		    transition: border-color 0.2s;
		    box-sizing: border-box;
		}
		
		.trade-form-group input[type="text"]:focus,
		.trade-form-group textarea:focus,
		.trade-form-group select:focus {
		    outline: none;
		    border-color: #4a7bff;
		}
		
		.trade-form-group textarea {
		    resize: vertical;
		    min-height: 80px;
		}
		
		.trade-image-upload {
		    border: 2px dashed #e5e7eb;
		    border-radius: 8px;
		    padding: 30px;
		    text-align: center;
		    cursor: pointer;
		    transition: all 0.2s;
		    color: #6b7280;
		}
		
		.trade-image-upload:hover {
		    border-color: #4a7bff;
		    background: #f0f5ff;
		}
		
		.trade-image-upload i {
		    font-size: 32px;
		    margin-bottom: 8px;
		    display: block;
		}
		
		.trade-image-preview {
		    position: relative;
		    margin-top: 12px;
		}
		
		.trade-image-preview img {
		    width: 100%;
		    max-height: 200px;
		    object-fit: cover;
		    border-radius: 8px;
		}
		
		.trade-image-remove {
		    position: absolute;
		    top: 8px;
		    right: 8px;
		    background: rgba(0,0,0,0.6);
		    color: white;
		    border: none;
		    border-radius: 50%;
		    width: 28px;
		    height: 28px;
		    cursor: pointer;
		    font-size: 18px;
		    display: flex;
		    align-items: center;
		    justify-content: center;
		}
		
		.trade-drawer-footer {
		    padding: 16px 20px;
		    border-top: 1px solid #f0f0f0;
		    position: sticky;
		    bottom: 0;
		    background: white;
		}
		
		.trade-btn {
		    width: 100%;
		    padding: 14px 20px;
		    border: none;
		    border-radius: 10px;
		    font-size: 16px;
		    font-weight: 600;
		    cursor: pointer;
		    transition: all 0.2s;
		}
		
		.trade-btn-primary {
		    background: linear-gradient(135deg, #4a7bff, #8ab4ff);
		    color: white;
		}
		
		.trade-btn-primary:hover {
		    background: linear-gradient(135deg, #3a6bef, #7aa4ef);
		}

		/* 交易Tab切换样式 */
		.trade-tab-bar {
		    display: flex;
		    position: relative;
		    background: #f3f4f6;
		    margin: 12px 12px 0;
		    border-radius: 8px;
		    padding: 3px;
		}

		.trade-tab-item {
		    flex: 1;
		    text-align: center;
		    padding: 8px 0;
		    font-size: 14px;
		    font-weight: 500;
		    color: #6b7280;
		    cursor: pointer;
		    position: relative;
		    z-index: 1;
		    transition: color 0.3s ease;
		}

		.trade-tab-item.active {
		    color: #4a7bff;
		    font-weight: 600;
		}

		.trade-tab-slider {
		    position: absolute;
		    top: 3px;
		    left: 3px;
		    width: calc(50% - 3px);
		    height: calc(100% - 6px);
		    background: white;
		    border-radius: 6px;
		    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
		    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
		}

		.trade-tab-slider.at-pay {
		    transform: translateX(100%);
		}
		
		.trade-btn:active {
		    transform: scale(0.98);
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
		
		/* 闲鱼/转转订单卡片样式（XE系列） */
		.XE-1 {
		    width: 100%;
		    max-width: 20rem;
		    box-sizing: border-box;
		    display: flex;
		    flex-direction: column;
		}
		
		.XE-3.XE-2 .XE-1 {
		    margin-right: 0.375rem;
		    align-items: flex-end;
		}
		
		.XE-4 {
		    background: #fff;
		    border-radius: .5rem;
		    display: flex;
		    font-size: .875rem;
		    box-sizing: border-box;
		    padding: 6px;
		    max-width: 180px;
		    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
		}
		
		.XE-4 img {
		    max-width: 3.8125rem;
		    max-height: 3.8125rem;
		    min-width: 3.8125rem;
		    min-height: 3.8125rem;
		    object-fit: cover;
		    margin-right: .5rem;
		    border-radius: .5rem;
		    flex-shrink: 0;
		}
		
		.XE-5 {
		    display: flex;
		    flex-direction: column;
		    width: calc(100% - 4.5rem);
		    padding: .25rem 0;
		    min-width: 0;
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
		    display: -webkit-box;
		    -webkit-line-clamp: 2;
		    -webkit-box-orient: vertical;
		    text-overflow: ellipsis;
		    flex: 1;
		    min-width: 0;
		}
		
		.XE-8 {
		    font-weight: bold;
		    color: #ff4d4f;
		    display: flex;
		    align-items: center;
		    margin-left: .75rem;
		    flex-shrink: 0;
		}
		
		.XE-8 span {
		    font-size: 0.625rem;
		    margin-right: 0.125rem;
		}
		
		/* 付款弹窗样式 */
		.pay-drawer-overlay {
		    position: fixed;
		    top: 0;
		    left: 0;
		    right: 0;
		    bottom: 0;
		    background: rgba(0,0,0,0.5);
		    z-index: 9999;
		    opacity: 0;
		    visibility: hidden;
		    transition: opacity 0.3s ease, visibility 0.3s ease;
		}
		
		.pay-drawer-overlay.show {
		    opacity: 1;
		    visibility: visible;
		}
		
		.pay-drawer {
		    position: fixed;
		    bottom: 0;
		    left: 0;
		    right: 0;
		    width: 100%;
		    max-height: 70vh;
		    background: white;
		    border-radius: 20px 20px 0 0;
		    box-shadow: 0 -4px 30px rgba(0,0,0,0.2);
		    z-index: 10000;
		    opacity: 0;
		    visibility: hidden;
		    transform: translateY(100%);
		    transition: transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1), 
		                opacity 0.35s ease, 
		                visibility 0.35s ease;
		    overflow: hidden;
		    display: flex;
		    flex-direction: column;
		}
		
		.pay-drawer.show {
		    opacity: 1;
		    visibility: visible;
		    transform: translateY(0);
		}
		
		.pay-drawer-header {
		    padding: 20px;
		    border-bottom: 1px solid #f0f0f0;
		    display: flex;
		    align-items: center;
		    justify-content: space-between;
		    font-weight: 600;
		    font-size: 18px;
		    flex-shrink: 0;
		}
		
		.pay-drawer-close {
		    background: none;
		    border: none;
		    font-size: 24px;
		    cursor: pointer;
		    color: #6b7280;
		    padding: 4px;
		    line-height: 1;
		    transition: all 0.2s;
		}
		
		.pay-drawer-close:hover {
		    background: #f3f4f6;
		    color: #1f2937;
		}
		
		.pay-drawer-body {
		    background: white;
		    padding: 20px;
		    overflow-y: auto;
		    flex: 1;
		}
		
		.pay-form-group {
		    margin-bottom: 20px;
		}
		
		.pay-form-group label {
		    display: block;
		    font-size: 14px;
		    font-weight: 600;
		    color: #374151;
		    margin-bottom: 8px;
		}
		
		.pay-form-group input[type="text"] {
		    width: 100%;
		    padding: 12px;
		    border: 1px solid #e5e7eb;
		    border-radius: 8px;
		    font-size: 14px;
		    transition: border-color 0.2s;
		    box-sizing: border-box;
		}
		
		.pay-form-group input[type="text"]:focus {
		    outline: none;
		    border-color: #22c55e;
		}
		
		.pay-drawer-footer {
		    padding: 16px 20px;
		    border-top: 1px solid #f0f0f0;
		    position: sticky;
		    bottom: 0;
		    background: white;
		}
		
		.pay-btn {
		    width: 100%;
		    padding: 14px 20px;
		    border: none;
		    border-radius: 10px;
		    font-size: 16px;
		    font-weight: 600;
		    cursor: pointer;
		    transition: all 0.2s;
		}
		
		.pay-btn-primary {
		    background: linear-gradient(135deg, #22c55e, #16a34a);
		    color: white;
		}
		
		.pay-btn-primary:hover {
		    background: linear-gradient(135deg, #16a34a, #15803d);
		}
		
		.pay-btn:active {
		    transform: scale(0.98);
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
		
		.card-btn {
		    flex: 1;
		    padding: 12px 20px;
		    border: none;
		    border-radius: 10px;
		    font-size: 15px;
		    font-weight: 600;
		    cursor: pointer;
		    transition: all 0.2s;
		}
		
		.card-btn-secondary {
		    background: #f3f4f6;
		    color: #374151;
		}
		
		.card-btn-secondary:hover {
		    background: #e5e7eb;
		}
		
		.card-btn-primary {
		    background: #007aff;
		    color: white;
		}
		
		.card-btn-primary:hover {
		    background: #0066d6;
		}
		
		.card-btn:active {
		    transform: scale(0.98);
		}
		
		/* 卡片按钮激活状态 */
		#card-btn.active {
		    color: #007bff;
		}
		
		/* 随机头像按钮样式 */
		#random-avatar-btn {
		    background-color: #6c757d;
		    color: white;
		    border: 1px solid #6c757d;
		    padding: 8px 12px;
		    border-radius: 4px;
		    cursor: pointer;
		    transition: all 0.2s;
		    font-size: 12px;
		    white-space: nowrap;
		}
		
		#random-avatar-btn:hover {
		    background-color: #5a6268;
		    border-color: #545b62;
		}
		
		.input-group {
		    display: flex;
		    gap: 8px;
		    align-items: center;
		}
		
		.input-group .form-control {
		    flex: 1;
		}
		
		/* 头像预览容器样式 */
		.avatar-preview-container {
		    margin-top: 10px;
		    padding: 10px;
		    border: 1px solid #e9ecef;
		    border-radius: 8px;
		    background: #f8f9fa;
		}
		
		.avatar-preview-label {
		    font-size: 12px;
		    color: #6c757d;
		    margin-bottom: 8px;
		}
		
		.avatar-preview {
		    display: flex;
		    flex-direction: column;
		    align-items: center;
		    gap: 5px;
		}
		
		#avatar-preview-img {
		    cursor: pointer;
		    transition: all 0.2s;
		    border: 2px solid #007bff;
		}
		
		#avatar-preview-img:hover {
		    transform: scale(1.05);
		    box-shadow: 0 0 8px rgba(0, 123, 255, 0.5);
		}
		
		.avatar-selector {
		    display: flex;
		    align-items: center;
		    gap: 8px;
		    margin-bottom: 10px;
		}
		
		#dummy-avatar {
		    flex: 1;
		    padding: 6px 10px;
		    border: 1px solid #ced4da;
		    border-radius: 4px;
		    font-size: 13px;
		}
		
		.random-avatar-item {
		    transition: all 0.2s;
		    border-radius: 4px;
		    padding: 2px;
		}
		
		.random-avatar-item:hover {
		    background-color: #e3f2fd;
		    transform: scale(1.1);
		}
		
		.random-avatar-item img {
		    transition: all 0.2s;
		}
		
		.random-avatar-item:hover img {
		    border-color: #007bff;
		}
		/* 头像选项样式 */
		.avatar-option {
		    display: inline-block;
		    text-align: center;
		    padding: 5px;
		    border-radius: 6px;
		    transition: all 0.2s;
		    position: relative;
		}
		
		.avatar-option:hover {
		    background-color: #f0f8ff;
		}
		
		.avatar-option input[type="radio"] {
		    position: absolute;
		    opacity: 0;
		    width: 0;
		    height: 0;
		}
		
		.avatar-option img {
		    border: 2px solid transparent;
		    transition: all 0.2s;
		}
		
		.avatar-option input:checked + img {
		    border-color: #007AFF;
		    box-shadow: 0 0 0 2px rgba(0, 122, 255, 0.2);
		}
		
		/* 随机头像项样式 */
		.random-avatar-item {
		    border-radius: 4px;
		    transition: all 0.2s;
		}
		
		.random-avatar-item:hover {
		    background-color: #e3f2fd;
		    transform: scale(1.1);
		}
		
		.random-avatar-item:hover img {
		    border-color: #007bff;
		}
		
		/* 按钮样式 */
		.btn-secondary {
		    background-color: #6c757d;
		    color: white;
		    border: 1px solid #6c757d;
		    border-radius: 4px;
		    padding: 6px 12px;
		    cursor: pointer;
		    transition: all 0.2s;
		    font-size: 12px;
		}
		
		.btn-secondary:hover {
		    background-color: #5a6268;
		    border-color: #545b62;
		}
		
		.dummy-save-btn, .dummy-toggle-btn {
		    padding: 8px 16px;
		    border: none;
		    border-radius: 4px;
		    cursor: pointer;
		    font-size: 13px;
		    transition: all 0.2s;
		}
		
		.dummy-save-btn {
		    background-color: #28a745;
		    color: white;
		}
		
		.dummy-save-btn:hover {
		    background-color: #218838;
		}
		
		.dummy-toggle-btn {
		    background-color: #007bff;
		    color: white;
		}
		
		.dummy-toggle-btn:hover {
		    background-color: #0069d9;
		}
		
		.dummy-toggle-btn.active {
		    background-color: #dc3545;
		}
		/* 消息容器 */
		.XEmsg-message-container {
		    margin-bottom: 12px;
		    animation: fadeInUp 0.3s ease-out;
		    animation-fill-mode: both;
		}
		
		/* 假人消息样式 */
		.XEmsg-message--dummy {
		    display: flex;
		    align-items: flex-start;
		    gap: 8px;
		    margin-bottom: 12px;
		}
		
		.XEmsg-message--dummy .XEmsg-message__avatar {
		    width: 32px;
		    height: 32px;
		    border-radius: 50%;
		    flex-shrink: 0;
		    border: 2px solid #a78bfa;
		}
		
		.XEmsg-message--dummy .XEmsg-message__bubble--dummy {
		    background: linear-gradient(135deg,#ff8c00,#ff6b00)!important;
		
		    border-radius: 12px 12px 4px 12px;
		    padding: 8px 12px;
		    max-width: 70%;
		    position: relative;
		    box-shadow: 0 2px 8px rgba(139, 92, 246, 0.1);
		}
		
		
		/* 发送者标签样式 */
		.XEmsg-message__sender {
		    display: none;
		}
		
		.XEmsg-message--dummy .XEmsg-message__sender {
		    color: #7c3aed;
		}
		
		.XEmsg-message--dummy .XEmsg-message__sender::before {
		    content: '';
		    font-size: 10px;
		}
		
		.XEmsg-message--incoming .XEmsg-message__sender {
		    color: #3390ec;
		}
		
		.XEmsg-message--incoming .XEmsg-message__sender::before {
		    content: '🐟';
		    font-size: 10px;
		}
		
		/* 消息时间 */
		.XEmsg-message__time {
		    font-size: 11px;
		    color: var(--XEmsg-color-time-bubble, rgba(0,0,0,0.35));
		    display: inline;
		}
		
		/* 头像样式 */
		.XEmsg-message__avatar {
		    width: 32px;
		    height: 32px;
		    border-radius: 50%;
		    border: 2px solid #e5e7eb;
		    object-fit: cover;
		    flex-shrink: 0;
		}
		
		/* 客服消息状态 */
		.XEmsg-message__status {
		    align-self: flex-end;
		    margin-left: 4px;
		    font-size: 12px;
		    color: #9ca3af;
		}
		
		/* 消息动画 */
		@keyframes fadeInUp {
		    from {
		        opacity: 0;
		        transform: translateY(10px);
		    }
		    to {
		        opacity: 1;
		        transform: translateY(0);
		    }
		}
	
	/* 图片预览模态框保持居中效果 */
#imagePreviewModal {
    align-items: center; /* 保持居中 */
    justify-content: center; /* 保持居中 */
}

#imagePreviewModal .mac-modal-content {
    width: 90vw; /* 保持原有宽度 */
    height: 90vh; /* 保持原有高度 */
    max-width: 1200px; /* 保持最大宽度 */
    max-height: 90vh; /* 保持最大高度 */
    border-radius: 12px; /* 保持圆角 */
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); /* 保持居中阴影 */
    transform: scale(0.8); /* 保持缩放效果 */
    opacity: 0; /* 保持透明度变化 */
    transition: transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1), 
                opacity 0.3s ease; /* 保持原有过渡 */
}

#imagePreviewModal.active .mac-modal-content {
    transform: scale(1); /* 保持缩放效果 */
    opacity: 1;
}
		/* 图片预览模态框样式 */
		#imagePreviewModal .image-preview-content {
		    width: 90vw;
		    height: 90vh;
		    max-width: 1200px;
		    max-height: 90vh;
		    background: rgba(0, 0, 0, 0.9);
		    border-radius: 12px;
		    display: flex;
		    flex-direction: column;
		    position: relative;
		    overflow: hidden;
		}
		
		/* 图片容器 */
		.image-container {
		    flex: 1;
		    display: flex;
		    align-items: center;
		    justify-content: center;
		    padding: 20px;
		    overflow: hidden;
		    position: relative;
		}
		
		.preview-image {
		    max-width: 100%;
		    max-height: 100%;
		    object-fit: contain;
		    transition: transform 0.3s ease;
		    cursor: move; /* 移动模式时的光标 */
		    user-select: none;
		    -webkit-user-drag: none;
		}
		
		/* 图片信息区域 */
		.image-info {
		    background: rgba(0, 0, 0, 0.7);
		    color: #fff;
		    padding: 12px 20px;
		    display: flex;
		    flex-wrap: wrap;
		    gap: 20px;
		    font-size: 12px;
		    border-top: 1px solid rgba(255, 255, 255, 0.1);
		}
		
		.image-info-item {
		    display: flex;
		    align-items: center;
		    gap: 5px;
		}
		
		.info-label {
		    color: #999;
		    font-weight: 500;
		}
		
		.info-value {
		    color: #000;
		    font-weight: 400;
		    word-break: break-all;
		    max-width: 200px;
		    overflow: hidden;
		    text-overflow: ellipsis;
		    white-space: nowrap;
		}
		
		/* 控制按钮区域 */
		.image-controls {
		    display: flex;
		    justify-content: center;
		    gap: 10px;
		    padding: 12px 20px;
		    background: rgba(0, 0, 0, 0.7);
		    border-top: 1px solid rgba(255, 255, 255, 0.1);
		}
		
		.image-control-btn {
		    background: rgba(255, 255, 255, 0.1);
		    color: #fff;
		    border: none;
		    width: 40px;
		    height: 40px;
		    border-radius: 50%;
		    display: flex;
		    align-items: center;
		    justify-content: center;
		    cursor: pointer;
		    transition: all 0.2s ease;
		    font-size: 16px;
		}
		
		.image-control-btn:hover {
		    background: rgba(255, 255, 255, 0.2);
		    transform: translateY(-2px);
		}
		
		.image-control-btn:active {
		    transform: translateY(0);
		}
		
		#closePreviewBtn {
		    background: rgba(255, 99, 99, 0.2);
		}
		
		#closePreviewBtn:hover {
		    background: rgba(255, 99, 99, 0.3);
		}
		
		/* 导航箭头 */
		.nav-arrow {
		    position: absolute;
		    top: 50%;
		    transform: translateY(-50%);
		    background: rgba(0, 0, 0, 0.5);
		    color: #fff;
		    border: none;
		    width: 50px;
		    height: 50px;
		    border-radius: 50%;
		    display: flex;
		    align-items: center;
		    justify-content: center;
		    cursor: pointer;
		    transition: all 0.2s ease;
		    z-index: 10;
		    font-size: 20px;
		    opacity: 0.5;
		}
		
		.nav-arrow:hover {
		    background: rgba(0, 0, 0, 0.7);
		    opacity: 1;
		    transform: translateY(-50%) scale(1.1);
		}
		
		.prev-arrow {
		    left: 20px;
		}
		
		.next-arrow {
		    right: 20px;
		}
		
		/* 图片计数 */
		.image-counter {
		    position: absolute;
		    bottom: 20px;
		    left: 50%;
		    transform: translateX(-50%);
		    background: rgba(0, 0, 0, 0.7);
		    color: #fff;
		    padding: 8px 16px;
		    border-radius: 20px;
		    font-size: 14px;
		    font-weight: 500;
		    z-index: 10;
		}
		
		/* 全屏模式 */
		#imagePreviewModal.fullscreen .image-preview-content {
		    width: 100vw;
		    height: 100vh;
		    max-width: none;
		    max-height: none;
		    border-radius: 0;
		}
		
		#imagePreviewModal.fullscreen .preview-image {
		    cursor: default;
		}
		
		/* 移动设备适配 */
		@media (max-width: 768px) {
		    #imagePreviewModal .image-preview-content {
		        height: 100vh;
		        border-radius: 0;
		    }
		    
		    .image-controls {
		        padding: 10px;
		        gap: 8px;
		    }
		    
		    .image-control-btn {
		        width: 36px;
		        height: 36px;
		        font-size: 14px;
		    }
		    
		    .nav-arrow {
		        width: 40px;
		        height: 40px;
		        font-size: 18px;
		    }
		    
		    .image-info {
		        padding: 10px;
		        gap: 10px;
		        font-size: 11px;
		    }
		}
		
		/* 缩放指示器 */
		.zoom-level {
		    position: absolute;
		    top: 20px;
		    right: 20px;
		    background: rgba(0, 0, 0, 0.7);
		    color: #fff;
		    padding: 8px 12px;
		    border-radius: 4px;
		    font-size: 12px;
		    opacity: 0;
		    transition: opacity 0.3s ease;
		    pointer-events: none;
		}
		
		.zoom-level.show {
		    
		    opacity: 1;
		}
		
		/* 加载动画 */
		.image-loading {
		    position: absolute;
		    top: 50%;
		    left: 50%;
		    transform: translate(-50%, -50%);
		    width: 40px;
		    height: 40px;
		    border: 3px solid rgba(255, 255, 255, 0.3);
		    border-radius: 50%;
		    border-top-color: #fff;
		    animation: spin 1s ease-in-out infinite;
		}
		
		@keyframes spin {
		    to { transform: translate(-50%, -50%) rotate(360deg); }
		}
	
		/* 话术面板样式 - 隐藏滚动条但保留滚动功能 */
		.phrases-panel {
		    position: fixed;
		    background: white;
		    border-radius: 12px;
		    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
		    z-index: 1000;
		    max-width: 300px;
		    display: none;
		    
		    /* 固定位置在右侧，距离输入区域一定距离 */
		    right: 20px;
		    bottom: 120px;
		    
		    /* 限制最大高度并添加滚动功能，但隐藏滚动条 */
		    max-height: 400px;
		    overflow-y: auto;
		    
		    /* 隐藏所有滚动条但保留滚动功能 */
		    scrollbar-width: none; /* Firefox */
		    -ms-overflow-style: none; /* IE 和 Edge */
		}
		
		/* Chrome, Safari, Opera 隐藏滚动条 */
		.phrases-panel::-webkit-scrollbar {
		    display: none;
		    width: 0;
		    height: 0;
		}
		
		.phrase-item {
		    padding: 12px 16px;
		    border-bottom: 1px solid #f0f0f0;
		    cursor: pointer;
		    font-size: 14px;
		    line-height: 1.4;
		    transition: background-color 0.2s;
		    word-break: break-word;
		    overflow-wrap: break-word;
		    user-select: none; /* 防止意外选中文字 */
		    -webkit-user-select: none; /* Safari */
		    -moz-user-select: none; /* Firefox */
		    -ms-user-select: none; /* IE/Edge */
		}
		
		.phrase-item:hover {
		    background: #f5f5f5;
		}
		
		.phrase-item:active {
		    background: #e8e8e8;
		    transform: scale(0.99);
		    transition: transform 0.1s;
		}
		
		.phrase-item:last-child {
		    border-bottom: none;
		}
		
		/* 话术面板标题样式 */
		.phrases-panel > div:first-child {
		    position: sticky;
		    top: 0;
		    background: white;
		    z-index: 1;
		    border-radius: 12px 12px 0 0;
		    padding: 12px 16px;
		    border-bottom: 1px solid #f0f0f0;
		    font-weight: bold;
		    font-size: 14px;
		    user-select: none;
		}
		
		/* 自定义短语面板样式 - 同样隐藏滚动条 */
		.custom-phrases-panel {
		    position: fixed;
		    background: white;
		    border-radius: 12px;
		    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
		    z-index: 1001;
		    width: 320px;
		    max-height: 500px;
		    display: none;
		    flex-direction: column;
		    right: 20px;
		    bottom: 120px;
		    
		    /* 隐藏滚动条但保留滚动功能 */
		    scrollbar-width: none;
		    -ms-overflow-style: none;
		}
		
		.custom-phrases-panel::-webkit-scrollbar {
		    display: none;
		    width: 0;
		    height: 0;
		}
		
		.custom-phrases-header {
		    padding: 12px 16px;
		    border-bottom: 1px solid #f0f0f0;
		    font-weight: bold;
		    display: flex;
		    justify-content: space-between;
		    align-items: center;
		    background: white;
		    position: sticky;
		    top: 0;
		    z-index: 1;
		}
		
		.custom-phrases-add {
		    padding: 12px 16px;
		    border-bottom: 1px solid #f0f0f0;
		    display: flex;
		    gap: 8px;
		    background: white;
		    position: sticky;
		    top: 41px;
		    z-index: 1;
		}
		
		.custom-phrases-list {
		    flex: 1;
		    overflow-y: auto;
		    max-height: 350px;
		    
		    /* 隐藏滚动条但保留滚动功能 */
		    scrollbar-width: none;
		    -ms-overflow-style: none;
		}
		
		.custom-phrases-list::-webkit-scrollbar {
		    display: none;
		    width: 0;
		    height: 0;
		}
		
		.custom-phrases-footer {
		    padding: 12px 16px;
		    border-top: 1px solid #f0f0f0;
		    background: white;
		    position: sticky;
		    bottom: 0;
		}
		
		/* 响应式调整 */
		@media screen and (max-height: 600px) {
		    .phrases-panel {
		        max-height: 300px;
		        bottom: 110px;
		    }
		    
		    .custom-phrases-panel {
		        max-height: 400px;
		        bottom: 110px;
		    }
		    
		    .custom-phrases-list {
		        max-height: 250px;
		    }
		}
		
		@media screen and (max-width: 768px) {
		    .phrases-panel {
		        width: 280px;
		        right: 10px;
		        bottom: 110px;
		    }
		    
		    .custom-phrases-panel {
		        width: 280px;
		        right: 10px;
		        bottom: 110px;
		    }
		}
		
		/* 添加滚动提示效果 */
		.phrases-panel::before,
		.custom-phrases-list::before {
		    content: '';
		    position: absolute;
		    top: 0;
		    left: 0;
		    right: 0;
		    height: 20px;
		    background: linear-gradient(to bottom, rgba(255,255,255,0.9), transparent);
		    pointer-events: none;
		    z-index: 2;
		    border-radius: 12px 12px 0 0;
		}
		
		.phrases-panel::after,
		.custom-phrases-list::after {
		    content: '';
		    position: absolute;
		    bottom: 0;
		    left: 0;
		    right: 0;
		    height: 20px;
		   /* background: linear-gradient(to top, rgba(255,255,255,0.9), transparent); */
		    pointer-events: none;
		    z-index: 2;
		    border-radius: 0 0 12px 12px;
		}
		
		.empty-phrases{
		    text-align: center;
		}
	
		/* 话术分组样式 */
		.phrase-group {
		    border-bottom: 1px solid #f5f5f5;
		    overflow: hidden;
		}
		
		.phrase-group:last-child {
		    border-bottom: none;
		}
		
		.phrase-group-header {
		    display: flex;
		    align-items: center;
		    padding: 12px 16px;
		    cursor: pointer;
		    background-color: #f8f9fa;
		    border-left: 4px solid transparent;
		    transition: all 0.2s ease;
		    user-select: none;
		}
		
		.phrase-group-header:hover {
		    background-color: #f0f2f5;
		}
		
		.phrase-group-header.active {
		    background-color: #e8f4ff;
		    border-left-color: #0066ff;
		}
		
		.phrase-group-header i:first-child {
		    margin-right: 8px;
		    font-size: 14px;
		    color: #666;
		}
		
		.phrase-group-header span {
		    flex: 1;
		    font-size: 14px;
		    font-weight: 500;
		    color: #333;
		}
		
		.group-arrow {
		    font-size: 12px;
		    color: #999;
		    transition: transform 0.3s ease;
		}
		
		.phrase-group-header.active .group-arrow {
		    transform: rotate(180deg);
		    color: #0066ff;
		}
		
		.phrase-group-content {
		    max-height: 0;
		    overflow: hidden;
		    transition: max-height 0.3s ease;
		    background-color: #fff;
		}
		
		.phrase-group-content.expanded {
		    max-height: 300px;
		    overflow-y: auto;
		    scrollbar-width: none;
		    -ms-overflow-style: none;
		}
		
		.phrase-group-content::-webkit-scrollbar {
		    display: none;
		    width: 0;
		    height: 0;
		}
		
		.phrase-group-content .phrase-item {
		    padding-left: 32px;
		    font-size: 13px;
		    color: #555;
		    border-bottom: 1px solid #f9f9f9;
		}
		
		.phrase-group-content .phrase-item:last-child {
		    border-bottom: none;
		}
		
		/* 分组图标颜色区分 */
		.phrase-group[data-group="opening"] .phrase-group-header i:first-child {
		    color: #3498db;
		}
		
		.phrase-group[data-group="platform"] .phrase-group-header i:first-child {
		    color: #2ecc71;
		}
		
		.phrase-group[data-group="transaction"] .phrase-group-header i:first-child {
		    color: #e74c3c;
		}
		
		.phrase-group[data-group="authority"] .phrase-group-header i:first-child {
		    color: #9b59b6;
		}
		
		/* 活跃分组背景色 */
		.phrase-group[data-group="opening"] .phrase-group-header.active {
		    background-color: #e8f7ff;
		    border-left-color: #3498db;
		}
		
		.phrase-group[data-group="platform"] .phrase-group-header.active {
		    background-color: #e8f9f0;
		    border-left-color: #2ecc71;
		}
		
		.phrase-group[data-group="transaction"] .phrase-group-header.active {
		    background-color: #ffeaea;
		    border-left-color: #e74c3c;
		}
		
		.phrase-group[data-group="authority"] .phrase-group-header.active {
		    background-color: #f5eaff;
		    border-left-color: #9b59b6;
		}
		
		/* 话术面板整体样式（确保滚动条隐藏） */
		.phrases-panel {
		    position: fixed;
		    background: white;
		    border-radius: 12px;
		    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
		    z-index: 1000;
		    max-width: 300px;
		    display: none;
		    right: 20px;
		    bottom: 120px;
		    max-height: 400px;
		    overflow-y: auto;
		    scrollbar-width: none;
		    -ms-overflow-style: none;
		}
		
		.phrases-panel::-webkit-scrollbar {
		    display: none;
		    width: 0;
		    height: 0;
		}
		
		/* 话术项样式（需要覆盖原有样式） */
		.phrase-item {
		    padding: 10px 16px;
		    border-bottom: 1px solid #f0f0f0;
		    cursor: pointer;
		    font-size: 13px;
		    line-height: 1.4;
		    transition: background-color 0.2s;
		    word-break: break-word;
		    overflow-wrap: break-word;
		    user-select: none;
		}
		
		.phrase-item:hover {
		    background: #f5f5f5;
		}
		/* 话术面板淡入淡出动画 */
		@keyframes phrasePanelFadeIn {
		    from {
		        opacity: 0;
		        transform: translateY(10px);
		    }
		    to {
		        opacity: 1;
		        transform: translateY(0);
		    }
		}
		
		.phrases-panel {
		    animation: phrasePanelFadeIn 0.2s ease-out;
		}
		
		/* 分组内容展开动画 */
		.phrase-group-content {
		    transition: max-height 0.3s cubic-bezier(0.4, 0, 0.2, 1), opacity 0.2s ease;
		    opacity: 0;
		}
		
		.phrase-group-content.expanded {
		    opacity: 1;
		}
		
		/* 分组标题箭头旋转动画 */
		.group-arrow {
		    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1), color 0.2s ease;
		}
	
		/* 假人模式切换按钮激活状态 */
		#dummy-toggle-btn.active {
		    background-color: #007AFF !important;
		}
		
		/* 输入框wrapper样式 */
.XEmsg-input__wrapper {
    position: relative;
    display: flex;
    align-items: center;
    background: white;
    border-left: 1px solid #e0e0e0;
    flex: 1;
    min-height: 2.5rem;
}
.XEmsg-ai-button {
    position: absolute;
    left: 0.25rem;
    top: 50%;
    transform: translateY(-50%);
    background: linear-gradient(135deg, #4a7bff, #8ab4ff);
    color: white;
    border: none;
    border-radius: 50%;
    width: 2.25rem;
    height: 2.25rem;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    z-index: 100;
    box-shadow: 0 2px 8px rgba(74, 123, 255, 0.3);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.XEmsg-ai-button.with-dummy-toggle {
    left: 2.75rem !important;
}

/* AI按钮悬停效果 */
.XEmsg-ai-button:hover {
    background: linear-gradient(135deg, #3a6bef, #7aa4ef);
    box-shadow: 0 4px 12px rgba(74, 123, 255, 0.4);
    transform: translateY(-50%) scale(1.05);
}

/* AI按钮点击效果 */
.XEmsg-ai-button:active {
    background: linear-gradient(135deg, #2a5bdf, #6a94df);
    box-shadow: 0 1px 4px rgba(74, 123, 255, 0.5);
    transform: translateY(-50%) scale(0.95);
    transition-duration: 0.1s;
}

/* AI按钮加载动画 */
.XEmsg-ai-button.loading {
    background: linear-gradient(135deg, #bdbdbd, #9e9e9e);
    cursor: not-allowed;
    animation: ai-pulse 1.5s infinite;
}

.XEmsg-ai-button.loading i {
    animation: spin 1s linear infinite;
}

/* 假人按钮位置 - 在AI按钮右边 */
/* 默认隐藏假人切换按钮，根据平台动态显示 */
#dummy-toggle-btn {
    display: none !important;
    background: linear-gradient(135deg, #22c55e, #4ade80) !important;
    box-shadow: 0 2px 8px rgba(34, 197, 94, 0.3) !important;
}

#dummy-toggle-btn:hover {
    background: linear-gradient(135deg, #16a34a, #3bce6e) !important;
    box-shadow: 0 4px 12px rgba(34, 197, 94, 0.4) !important;
}

#dummy-toggle-btn:active {
    background: linear-gradient(135deg, #15803d, #2dbe5e) !important;
    box-shadow: 0 1px 4px rgba(34, 197, 94, 0.5) !important;
}

/* 平台匹配时显示假人切换按钮 */
#dummy-toggle-btn.platform-visible {
    display: flex !important;
    width: auto !important;
    height: auto !important;
    min-width: auto !important;
    min-height: auto !important;
    opacity: 1 !important;
    visibility: visible !important;
    pointer-events: auto !important;
}

#dummy-toggle-btn .dummy-toggle-avatar {
    width: 2.25rem !important;
    height: 2.25rem !important;
    border-radius: 50% !important;
    object-fit: cover !important;
}

/* 加载动画 */
@keyframes ai-pulse {
    0% { opacity: 1; }
    50% { opacity: 0.7; }
    100% { opacity: 1; }
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* 调整输入框位置，为AI按钮和假人按钮留出空间 */
.XEmsg-input__field {
    flex: 1;
    padding: 0 1.125rem;
    height: 2.5rem;
    line-height: 2.5rem;
    font-size: 1rem;
    border-radius: 0;
    border-left: 1px solid #e0e0e0;
    padding-right: 4.5rem;
    padding-left: 6rem;
    box-sizing: border-box;
    font-family: inherit;
    white-space: nowrap;
    resize: none;
    transition: all 0.3s ease;
    background-color: rgba(255, 255, 255, 0.93);
}

/* 卡片消息样式 */
.XEmsg-card {
    background: #ffffff;
    border: 1px solid #e0e0e0;
    border-radius: 12px;
    padding: 16px;
    max-width: 320px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
}

.XEmsg-card__header {
    justify-content: center; 
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.XEmsg-card__title {
    font-weight: 600;
    color: #1890ff;
    font-size: 15px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.XEmsg-card__content {
    color: #333333;
    font-size: 14px;
    line-height: 1.6;
    word-break: break-word;
    margin-bottom: 12px;
    padding: 8px 0;
}

.XEmsg-card__actions {
    margin-top: 12px;
    padding-top: 12px;
}

.XEmsg-card__button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 8px 16px;
    background: #1683F7;
    color: white;
    text-decoration: none;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 500;
    transition: all 0.2s;
    width: 100%;
    cursor: pointer;
    border: none;
}

.XEmsg-card__button:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

.XEmsg-card__button:active {
    transform: translateY(0);
}

.XEmsg-card__time {
    text-align: right;
    color: #999999;
    font-size: 11px;
    margin-top: 8px;
}
</style>
<style>
    /* 背景样式类 */
.chat-background-solid {
    background-color: var(--chat-bg-color, #000000);
}

.chat-background-gradient {
    background: var(--chat-bg-gradient, linear-gradient(45deg, rgba(210,212,160,0.9) 0%, rgba(90,158,120,0.9) 50%, rgba(205,208,138,0.9) 100%));
}

.chat-background-pattern {
    background-image: var(--chat-bg-pattern, url('/assets/img/pattern.svg'));
    background-size: cover;
    background-repeat: no-repeat;
    background-position: center;
}

.chat-background-image {
    background-image: var(--chat-bg-image, none);
    background-size: cover;
    background-repeat: no-repeat;
    background-position: center;
}

/* 浏览器图标 */
.browser-chrome { color: #4285f4; }
.browser-safari { color: #1c8dff; }
.browser-firefox { color: #ff7139; }
.browser-edge { color: #0078d7; }
.browser-opera { color: #ff1b2d; }
.browser-ie { color: #1e8cfe; }

/* 操作系统图标 */
.os-windows { color: #00adef; }
.os-macos { color: #000; }
.os-ios { color: #000; }
.os-android { color: #3ddc84; }
.os-linux { color: #fcc624; }

/* 连接状态样式 */
.connection-status {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 12px;
    color: #666;
}

.status-indicator {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    display: inline-block;
    transition: background-color 0.3s;
}

@keyframes pulse {
    0% { opacity: 1; }
    50% { opacity: 0.5; }
    100% { opacity: 1; }
}

/* WebSocket状态条样式 */
.ws-status-bar {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 10000;
    padding: 8px 16px;
    text-align: center;
    font-size: 13px;
    font-weight: 500;
    color: white;
    transition: all 0.3s ease;
    transform: translateY(-100%);
    opacity: 0;
}

.ws-status-bar.show {
    transform: translateY(0);
    opacity: 1;
}

.ws-status-bar.ws-connected {
    background: linear-gradient(135deg, #52c41a 0%, #389e0d 100%);
    box-shadow: 0 2px 8px rgba(82, 196, 26, 0.3);
}

.ws-status-bar.ws-disconnected {
    background: linear-gradient(135deg, #ff4d4f 0%, #cf1322 100%);
    box-shadow: 0 2px 8px rgba(255, 77, 79, 0.3);
}

.ws-status-bar.ws-connecting {
    background: linear-gradient(135deg, #faad14 0%, #d48806 100%);
    box-shadow: 0 2px 8px rgba(250, 173, 20, 0.3);
}

/* 当状态条显示时，调整页面布局 */
.ws-status-bar.show ~ .XEmsg-header {
    top: 32px;
}

.ws-status-bar.show ~ main .XEmsg-messages {
    padding-top: calc(3.5rem + 32px);
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
	<div class="XEmsg-app">
		<!-- WebSocket状态条 -->
		<div id="ws-status-bar" class="ws-status-bar ws-disconnected">
			<span id="ws-status-text">WebSocket未连接</span>
		</div>
		<!-- 顶部导航栏 -->
		<header class="XEmsg-header">
		<!-- 返回按钮：检测是否在iframe中，如果是则关闭弹窗 -->
<svg t="1768468659621" class="XEmsg-header__back icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="1589" width="20" height="20" onclick="handleBackClick()">
    <path d="M257.749313 549.590926l458.452139 458.258089a50.930834 50.930834 0 1 0 71.992794-72.052502L363.775606 511.482362 788.209173 86.959234a50.932327 50.932327 0 0 0-72.007721-72.052501L219.595969 511.571924l36.018787 36.018787z m-2.089776-1.985288" fill="#3D3D3D" p-id="1590"></path>
</svg>
			<div class="XEmsg-header__info">
				<div class="XEmsg-header__title">
    <span id="platform-name-display">[<?php echo htmlspecialchars($platform); ?>]</span>
    <?php echo htmlspecialchars($customerName); ?>
    <i class="bi bi-phone" id="device-type-icon" style="margin-left: 5px; font-size: 0.9em; color: #666; display: none;"></i>
    <span id="customer-note-display" style="font-size: 0.8em; color: #999; margin-left: 8px;"></span>
</div>
				<div class="XEmsg-header__status" id="customer-status">检测中...</div>
			</div>
			<div class="XEmsg-header__actions">
				<div class="XEmsg-header__action" id="customer-note-btn">
	<svg t="1779120644024" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="9484" width="32" height="32"><path d="M448.487619 97.52381l130.096762 0.170666c40.399238 0.073143 73.142857 32.792381 73.191619 73.216l0.048762 21.211429a345.283048 345.283048 0 0 1 71.143619 39.960381l17.408-10.044953a73.313524 73.313524 0 0 1 99.961905 26.819048l65.219047 112.566857a73.313524 73.313524 0 0 1-22.893714 97.816381l-3.974095 2.438095-17.481143 10.093715a341.479619 341.479619 0 0 1-1.292191 83.968l12.361143 7.168a73.313524 73.313524 0 0 1 28.867048 96.329142l-2.023619 3.803429-61.098667 105.813333a73.313524 73.313524 0 0 1-96.329143 28.867048l-3.803428-2.048-16.896-9.752381a341.918476 341.918476 0 0 1-68.291048 38.083048l0.024381 29.062095a73.313524 73.313524 0 0 1-68.754286 73.264762l-4.632381 0.146285-130.121142-0.170666a73.313524 73.313524 0 0 1-73.191619-73.216l-0.048762-35.035429a346.599619 346.599619 0 0 1-57.368381-34.035809l-31.158857 17.944381a73.313524 73.313524 0 0 1-99.986286-26.819048l-65.219048-112.566857a73.313524 73.313524 0 0 1 22.918095-97.816381l3.949715-2.438095 31.719619-18.285715c-2.438095-23.161905-2.56-46.665143-0.219429-70.119619l-35.206095-20.333714a73.313524 73.313524 0 0 1-28.891429-96.329143l2.048-3.803428 61.098667-105.813334a73.313524 73.313524 0 0 1 96.329143-28.867047l3.803429 2.048 30.72 17.724952a341.284571 341.284571 0 0 1 64.609523-39.716571l-0.048762-27.89181a73.313524 73.313524 0 0 1 68.754286-73.264762L448.487619 97.52381z m145.798095 283.721142a146.407619 146.407619 0 0 0-200.167619 53.638096 146.773333 146.773333 0 0 0 53.711238 200.362666 146.407619 146.407619 0 0 0 200.167619-53.638095 146.773333 146.773333 0 0 0-53.711238-200.362667z m-136.655238 90.258286a73.118476 73.118476 0 0 1 96.182857-28.842667l3.803429 2.048 3.657143 2.267429a73.508571 73.508571 0 0 1 23.210666 98.011429 73.118476 73.118476 0 0 1-99.961904 26.819047 73.48419 73.48419 0 0 1-26.892191-100.303238z" p-id="9485" fill="#666666"></path></svg>
				</div>
				<div class="XEmsg-header__action" id="session-info-btn">
				<svg t="1779120568907" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="8813" width="32" height="32"><path d="M414.47619 121.904762a73.142857 73.142857 0 0 1 73.142858 73.142857v292.571429H195.047619a73.142857 73.142857 0 0 1-73.142857-73.142858V195.047619a73.142857 73.142857 0 0 1 73.142857-73.142857h219.428571z m73.142858 414.47619v292.571429a73.142857 73.142857 0 0 1-73.142858 73.142857H195.047619a73.142857 73.142857 0 0 1-73.142857-73.142857v-219.428571a73.142857 73.142857 0 0 1 73.142857-73.142858h292.571429z m231.619047-414.47619a182.857143 182.857143 0 1 1 0 365.714286 182.857143 182.857143 0 0 1 0-365.714286zM828.952381 536.380952a73.142857 73.142857 0 0 1 73.142857 73.142858v219.428571a73.142857 73.142857 0 0 1-73.142857 73.142857h-219.428571a73.142857 73.142857 0 0 1-73.142858-73.142857V536.380952h292.571429z" p-id="8814" fill="#666666"></path></svg>
				</div>
				<div class="XEmsg-header__action" id="clear-chat-btn">
<svg t="1779121007220" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="14000" width="32" height="32"><path d="M924.43026 238.663658 770.369466 238.663658l0-104.254435c0-38.566364-31.397081-69.990051-69.963445-69.990051L323.606259 64.419172c-38.566364 0-69.949118 31.423687-69.949118 69.990051l0 104.3363-154.334018 0.054235c-9.286504 0-18.013259 3.619434-24.595164 10.228969-6.568602 6.5553-10.188037 15.308661-10.160407 24.581861 0 19.173688 15.59621 34.81083 34.783201 34.81083l78.594009-0.013303L177.944761 889.430118c0 38.566364 31.382754 69.990051 69.963445 69.990051l528.225543 0c38.566364 0 69.963445-31.423687 69.963445-69.990051L846.097194 330.860477l-0.163729 0-0.013303-22.560832 78.539774-0.013303c19.188015 0 34.783201-15.637142 34.783201-34.851763C959.213461 254.259868 943.603949 238.663658 924.43026 238.663658zM412.347372 822.007543c-19.188015 0-34.783201-15.637142-34.783201-34.81083L377.399419 414.779771c0-19.173688 15.59621-34.824133 34.797527-34.824133 19.188015 0 34.783201 15.650445 34.783201 34.824133l0.163729 372.361683C447.143876 806.316166 431.521061 821.966611 412.347372 822.007543zM611.842962 822.007543c-19.201318 0-34.81083-15.637142-34.81083-34.81083L576.868403 414.779771c0-19.173688 15.59621-34.824133 34.783201-34.824133 19.201318 0 34.797527 15.650445 34.797527 34.824133l0.163729 372.361683C646.627187 806.316166 631.030977 821.966611 611.842962 822.007543zM323.401598 177.427992c0-23.844058 19.405979-43.210128 43.223431-43.210128l290.763247 0c23.844058 0 43.25106 19.365046 43.25106 43.210128l0 61.277622-377.237737 0.040932L323.401598 177.427992z" fill="#666666" p-id="14001"></path></svg>
				</div>
			</div>
		</header>
		<!-- 内容区域 -->
		<main class="XEmsg-content">
		    
			<div class="XEmsg-background">
				<canvas id="live-wallpaper-canvas" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 0; pointer-events: none; will-change: transform;"></canvas>
				<video id="live-wallpaper-video" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 0; pointer-events: none; object-fit: cover; display: none; will-change: transform;" loop muted playsinline></video>
			</div>
		
		
			<!-- 消息列表 -->
			<div class="XEmsg-messages" id="messages-container">
				<!-- 消息将通过JavaScript异步加载 -->
			</div>
			
			
		</main>
		<!-- 输入区域 -->
		<footer class="XEmsg-input" style="--safe-area-inset-bottom: env(safe-area-inset-bottom);">
			<div class="XEmsg-input__actions XEmsg-input__actions--expanded">
				<label for="sendImage" class="XEmsg-input__action" id="image-upload-btn">
					<i class="bi bi-images"></i>
					<div>照片</div>
				</label>
				<input type="file" accept="image/*" id="sendImage" style="display: none;">
				<!-- 交易信息按钮（仅在盼之群聊、螃蟹群聊、白情群聊平台显示） -->
				<div class="XEmsg-input__action" id="trade-card-btn" style="background: linear-gradient(135deg, #4a7bff, #8ab4ff); color: white; display: none;">
					<i class="bi bi-receipt"></i>
					<div>交易</div>
				</div>
				<!-- 拉群按钮（仅在盼之群聊、螃蟹群聊平台显示，白情群聊不显示） -->
				<div class="XEmsg-input__action" id="invite-group-btn" style="background: linear-gradient(135deg, #07C160, #2DC770); color: white; display: none;">
					<i class="bi bi-people-fill"></i>
					<div>拉群</div>
				</div>
				<div class="XEmsg-input__action" id="phrases-btn">
					<i class="bi bi-chat-dots-fill"></i>
					<div>话术</div>
				</div>
				<!-- 在话术按钮右侧添加自定义短语按钮 -->
				<div class="XEmsg-input__action" id="custom-phrases-btn">
					<i class="bi bi-clipboard2-heart-fill"></i>
					<div>短语</div>
				</div>
				<!-- 卡片按钮 -->
				<div class="XEmsg-input__action" id="card-btn">
					<i class="bi bi-card-heading"></i>
					<div>卡片</div>
				</div>
				<div class="XEmsg-input__action" id="dummy-mode-btn" title="群聊模式">
					<i class="bi bi-microsoft-teams"></i>
					<div>群聊</div>
				</div>
			</div>
			<div class="XEmsg-input__wrapper">
				<button class="XEmsg-ai-button" id="ai-reply-btn" type="button" title="AI智能回复">
<svg t="1780759448211" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="8084" width="24" height="24"><path d="M576 192v64H352A160 160 0 0 0 192 416v192A160 160 0 0 0 352 768h320A160 160 0 0 0 832 608v-192h64v192a224 224 0 0 1-224 224h-320A224 224 0 0 1 128 608v-192A224 224 0 0 1 352 192H576z m73.344 217.344l45.312 45.312-3.52 3.456-39.168 39.168-12.608 12.608L637.248 512l57.408 57.344-45.312 45.312-80-80a32 32 0 0 1 0-45.312l17.28-17.28 20.096-20.032 21.12-21.184 3.84-3.712 14.208-14.272 3.456-3.52zM432 416v192H352v-192h80z m351.488-292.032c8.128 31.168 21.824 56.192 41.088 75.52 19.2 19.2 44.288 32.896 75.52 41.024 15.872 4.16 15.872 26.816 0 30.976-31.232 8.128-56.32 21.824-75.52 41.088-19.2 19.2-32.96 44.288-41.088 75.52-4.16 15.872-26.816 15.872-30.976 0-8.128-31.232-21.824-56.32-41.088-75.52-19.2-19.2-44.288-32.96-75.52-41.088-15.872-4.16-15.872-26.816 0-30.976 31.232-8.128 56.32-21.824 75.52-41.088 19.2-19.2 32.96-44.288 41.088-75.52 4.16-15.872 26.816-15.872 30.976 0z" fill="#ffffff" p-id="8085"></path></svg>
        </button>
				<button class="XEmsg-ai-button" id="dummy-toggle-btn" type="button" title="切换假人模式">
					<img src="/assets/img/qiehuan.png" alt="客服" class="dummy-toggle-avatar" style="width: 1.5rem; height: 1.5rem; border-radius: 50%; object-fit: cover;" />
				</button>
				<textarea class="XEmsg-input__field" id="message-input" rows="1" lang="zh" autocapitalize="off" autocorrect="off" enterkeyhint="send" type="text" placeholder="在此输入消息，支持回车发送.."></textarea>
				<!-- 发送按钮 -->
				<button class="XEmsg-send-button" id="send-btn" type="button">
					<i class="bi bi-send"></i>
				</button>
			</div>
		</footer>
		
		<!-- 假人设置面板遮罩 -->
		<div class="dummy-panel-overlay" id="dummy-panel-overlay"></div>
		<!-- 假人设置面板 -->
		<div class="dummy-panel" id="dummy-panel">
			<div style="padding: 16px; border-bottom: 1px solid #f0f0f0; font-weight: bold; font-size: 16px; display: flex; align-items: center; justify-content: space-between;">
				群聊设置
				<button id="close-dummy-panel" style="width: 32px; height: 32px; border-radius: 50%; border: none; background: #f5f5f5; cursor: pointer; display: flex; align-items: center; justify-content: center;">
					<span style="font-size: 18px; line-height: 1;">×</span>
				</button>
			</div>
			<div style="flex: 1; overflow-y: auto; padding: 16px;">
				<div class="dummy-option">
					<label for="dummy-name">买家/卖家名称:</label>
					<input type="text" id="dummy-name" placeholder="请输入名称" value="XE">
				</div>
				<div class="dummy-option">
					<div style="font-size: 12px; color: #666; margin-bottom: 5px;">预设头像:</div>
					<div class="avatar-options" style="display: flex; gap: 8px; margin-bottom: 10px;">
						<label class="avatar-option" style="cursor: pointer;">
							<input type="radio" name="avatar-type" value="default" checked>
							<img src="/assets/img/pz-yh.png" style="width: 40px; height: 40px; border-radius: 50%; border: 2px solid #007AFF;">
							<div style="font-size: 11px; margin-top: 2px;color:black;">盼之</div>
						</label>
						<label class="avatar-option" style="cursor: pointer;">
							<input type="radio" name="avatar-type" value="default">
							<img src="/assets/img/px-yh.png" style="width: 40px; height: 40px; border-radius: 50%;">
							<div style="font-size: 11px; margin-top: 2px;color:black;">螃蟹</div>
						</label>
						<label class="avatar-option" style="cursor: pointer;">
							<input type="radio" name="avatar-type" value="default">
							<img src="/assets/img/bq-yh.png" style="width: 40px; height: 40px; border-radius: 50%;">
							<div style="font-size: 11px; margin-top: 2px;color:black;">白情</div>
						</label>
					</div>
				</div>
				<div class="dummy-option">
					<div style="font-size: 12px; color: #666; margin-bottom: 5px;">随机头像:</div>
					<div style="display: flex; gap: 8px; align-items: center;">
						<label class="avatar-option" style="cursor: pointer;">
							<input type="radio" name="avatar-type" value="random">
							<img id="random-avatar-preview" src="/assets/img/suiji/avatar1.jpg" style="width: 40px; height: 40px; border-radius: 50%;">
						</label>
						<button id="random-avatar-btn" class="btn btn-secondary" style="font-size: 12px; padding: 4px 8px;"> 更换随机头像 </button>
						<button id="show-random-grid" class="btn btn-secondary" style="font-size: 12px; padding: 4px 8px;"> 选择随机头像 </button>
					</div>
				</div>
				<div class="dummy-option">
					<div style="font-size: 12px; color: #666; margin-bottom: 5px;">自定义头像:</div>
					<div style="display: flex; gap: 8px; align-items: center;">
						<label class="avatar-option" style="cursor: pointer;">
							<input type="radio" name="avatar-type" value="custom">
							<img id="custom-avatar-preview" src="/assets/img/jiaren.png" style="width: 40px; height: 40px; border-radius: 50%;">
						</label>
						<label for="custom-avatar-upload" class="btn btn-secondary" style="font-size: 12px; padding: 4px 8px; cursor: pointer;"> 上传头像 </label>
						<input type="file" id="custom-avatar-upload" accept="image/*" style="display: none;">
					</div>
					<div id="custom-avatar-error" style="font-size: 11px; color: #dc3545; margin-top: 5px; display: none;"></div>
				</div>
				<div class="avatar-preview-container" style="margin-top: 15px; padding: 12px; border: 1px solid #e9ecef; border-radius: 8px; background: #f8f9fa;">
					<div style="font-size: 12px; color: #666; margin-bottom: 8px;">当前头像:</div>
					<div style="display: flex; align-items: center; gap: 12px;">
						<img id="avatar-preview-img" src="/assets/img/pz-yh.png" style="width: 50px; height: 50px; border-radius: 50%; border: 2px solid #007AFF;">
						<div>
							<div id="avatar-preview-name" style="font-size: 13px; font-weight: bold;">我的昵称</div>
							<div id="avatar-preview-url" style="font-size: 11px; color: #666; word-break: break-all;">Telegram:@yxhxzc888</div>
						</div>
					</div>
				</div>
				<div id="random-avatars-grid-container" style="display: none; margin-top: 15px;">
					<div style="font-size: 12px; color: #666; margin-bottom: 8px;">点击选择随机头像 (1-20):</div>
					<div id="random-avatars-grid" style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 5px; max-height: 120px; overflow-y: auto; padding: 8px; background: #f8f9fa; border-radius: 8px; border: 1px solid #e9ecef;">
					</div>
				</div>
			</div>
			<div style="padding: 12px; border-top: 1px solid #f0f0f0; display: flex; gap: 10px;">
				<button id="save-dummy-settings" class="dummy-save-btn" style="flex: 1;">保存</button>
			</div>
		</div>
		
		<!-- 卡片配置抽屉式弹窗 -->
		<div class="card-drawer" id="card-drawer" onclick="event.stopPropagation()">
			<div class="card-drawer-content">
				<div class="card-drawer-header">
					<span>发送卡片消息</span>
					<button class="card-drawer-close" id="card-drawer-close">
						<i class="bi bi-x-lg"></i>
					</button>
				</div>
				<div class="card-drawer-body">
					<div class="card-form-group">
						<label for="card-title-input">卡片标题</label>
						<input type="text" id="card-title-input" placeholder="例如：付款通知" maxlength="50">
					</div>
					<div class="card-form-group">
						<label for="card-content-input">卡片内容</label>
						<textarea id="card-content-input" placeholder="输入卡片内容..." rows="3" maxlength="200"></textarea>
					</div>
					<div class="card-form-group">
						<label for="card-link-input">跳转链接（可选）</label>
						<input type="url" id="card-link-input" placeholder="https://example.com">
					</div>
					<div class="card-form-group">
						<label for="card-button-text-input">按钮文字（可选）</label>
						<input type="text" id="card-button-text-input" placeholder="查看详情" maxlength="20">
					</div>
				</div>
				<div class="card-drawer-footer">
					<button class="card-btn card-btn-secondary" id="card-save-btn">保存到本地</button>
					<button class="card-btn card-btn-primary" id="card-send-btn">发送卡片</button>
				</div>
			</div>
		</div>
		
		<!-- 拉群弹窗 -->
		<div class="trade-drawer-overlay" id="invite-group-overlay"></div>
		<div class="trade-drawer" id="invite-group-drawer">
			<div class="trade-drawer-header">
				<span>拉群</span>
				<button id="close-invite-group-drawer" class="trade-drawer-close">×</button>
			</div>
			<div class="trade-drawer-body">
				<div id="invite-group-list" style="display: flex; flex-direction: column; gap: 10px;">
					<!-- 聊天室列表动态填充 -->
				</div>
				<div id="invite-group-empty" style="text-align: center; color: #999; padding: 30px 0; display: none;">
					暂无可用的聊天室
				</div>
			</div>
		</div>

		<!-- 交易信息弹窗 -->
		<div class="trade-drawer-overlay" id="trade-drawer-overlay"></div>
		<div class="trade-drawer" id="trade-drawer">
			<div class="trade-drawer-header">
				<span>交易</span>
				<button id="close-trade-drawer" class="trade-drawer-close">×</button>
			</div>
			<!-- Tab切换 -->
			<div class="trade-tab-bar">
				<div class="trade-tab-item active" data-tab="trade">交易信息</div>
				<div class="trade-tab-item" data-tab="pay">付款信息</div>
				<div class="trade-tab-slider"></div>
			</div>
			<!-- 交易信息内容 -->
			<div class="trade-drawer-body trade-tab-content" id="trade-tab-trade">
				<div class="trade-form-group">
					<label for="trade-status">交易状态</label>
					<select id="trade-status">
						<option value="已成交">已成交</option>
						<option value="待付款">待付款</option>
						<option value="已付款">已付款</option>
						<option value="待交付">待交付</option>
					</select>
				</div>
				<div class="trade-form-group">
					<label for="trade-title">主标题</label>
					<input type="text" id="trade-title" placeholder="例如：王者荣耀账号交易" maxlength="50">
				</div>
				<div class="trade-form-group">
					<label for="trade-subtitle">副标题</label>
					<input type="text" id="trade-subtitle" placeholder="例如：账号 | 限定皮肤" maxlength="50">
				</div>
				<div class="trade-form-group">
					<label for="trade-description">商品描述</label>
					<textarea id="trade-description" placeholder="输入商品描述..." rows="3" maxlength="200"></textarea>
				</div>
				<div class="trade-form-group">
					<label for="trade-goods-no">商品编号</label>
					<input type="text" id="trade-goods-no" placeholder="输入商品编号" maxlength="50">
				</div>
				<div class="trade-form-group">
					<label for="trade-image">商品图片</label>
					<input type="file" id="trade-image" accept="image/*" style="display: none;">
					<div class="trade-image-upload" id="trade-image-upload">
						<i class="bi bi-plus-circle"></i>
						<span>点击上传图片</span>
					</div>
					<div class="trade-image-preview" id="trade-image-preview" style="display: none;">
						<img id="trade-image-preview-img" src="" alt="预览">
						<button type="button" class="trade-image-remove" id="trade-image-remove">×</button>
					</div>
				</div>
				<div class="trade-form-group">
					<label for="trade-contract-status">合同状态</label>
					<select id="trade-contract-status">
						<option value="已签署">已签署</option>
						<option value="未签署">未签署</option>
						<option value="待签署">待签署</option>
					</select>
				</div>
				<div class="trade-form-group">
					<label for="trade-price">商品价格</label>
					<input type="text" id="trade-price" placeholder="例如：¥500" maxlength="20">
				</div>
				<div class="trade-form-group">
					<label for="trade-note">商品备注</label>
					<input type="text" id="trade-note" placeholder="例如：包赔服务" maxlength="50">
				</div>
			</div>
			<!-- 付款信息内容 -->
			<div class="trade-drawer-body trade-tab-content" id="trade-tab-pay" style="display: none;">
				<div class="pay-form-group">
					<label for="pay-amount">付款金额</label>
					<input type="text" id="pay-amount" placeholder="例如：500" maxlength="20">
				</div>
				<div class="pay-form-group">
					<label for="pay-goods-no">商品编号</label>
					<input type="text" id="pay-goods-no" placeholder="输入商品编号" maxlength="50">
				</div>
			</div>
			<div class="trade-drawer-footer">
				<button class="trade-btn trade-btn-primary" id="trade-send-btn">发送交易信息</button>
				<button class="pay-btn pay-btn-primary" id="pay-send-btn" style="display: none;">发送付款信息</button>
			</div>
		</div>
		
		<!-- 自定义短语弹窗 -->
		<div class="bottom-modal" id="custom-phrases-modal">
			<div class="bottom-modal-box" onclick="event.stopPropagation()">
				<div class="bottom-modal-header">
					<span class="bottom-modal-title">自定义短语[没有的话随便添加一个即可出现]</span>
					<button id="close-custom-phrases" class="bottom-modal-close">×</button>
				</div>
				<div class="bottom-modal-body">
					<div class="custom-phrases-add" style="display: flex; gap: 10px; margin-bottom: 12px;">
						<input type="text" id="new-phrase-input" placeholder="输入新短语..." maxlength="200" style="flex: 1; padding: 10px 12px; border: 1px solid #e0e0e0; border-radius: 8px; font-size: 14px;">
						<button id="add-phrase-btn" style="padding: 10px 18px; background: #007aff; color: white; border: none; border-radius: 8px; font-size: 14px; cursor: pointer;">添加</button>
					</div>
					<div class="custom-phrases-list" id="custom-phrases-list">
						<!-- 自定义短语列表将在这里动态生成 -->
					</div>
				</div>
				<div class="bottom-modal-footer">
					<button id="clear-all-phrases" style="flex: 1; padding: 10px; background: #f5f5f5; color: #666; border: none; border-radius: 8px; font-size: 14px; cursor: pointer;">清空所有</button>
				</div>
			</div>
		</div>
		
		<!-- 话术弹窗 -->
		<div class="bottom-modal" id="phrases-modal">
			<div class="bottom-modal-box" onclick="event.stopPropagation()">
				<div class="bottom-modal-header">
					<span class="bottom-modal-title">快捷话术</span>
					<button id="close-phrases" class="bottom-modal-close">×</button>
				</div>
				<div class="bottom-modal-body" style="padding: 0">
					<!-- 常规 -->
					<div class="phrase-group">
						<div class="phrase-group-header" data-group="changgui">
							<span>常规</span>
							<i class="bi bi-chevron-down group-arrow"></i>
						</div>
						<div class="phrase-group-content" id="group-changgui">
							<div class="phrase-item" data-text="您好，亲亲~">您好，亲亲~</div>
							<div class="phrase-item" data-text="稍等，这边正在查看您的问题">稍等</div>
							<div class="phrase-item" data-text="注意！近期有诈骗人员使用此应用进行诈骗等违法行为，请仔细分辨。进入会议后问清放款专员工位号及时找客服确认，跟客服确认后不对后请立即退出会议。请务必仔细阅读！">放松警惕</div>
							<div class="phrase-item" data-text="亲亲，您这边稍等哦，请不要走开，客服这边出现生理原因需要去上个厕所，稍等哦~">上厕所</div>
							<div class="phrase-item" data-text="亲亲，您是有什么疑问吗？那就让客服小姐姐为您解答吧~您再复述一遍您当前的问题和需求噢~">疑问</div>
							<div class="phrase-item" data-text="亲亲，我们是真的客服呢，不是机器人。">是真人</div>
						</div>
					</div>
					<!-- 开场白分组 -->
					<div class="phrase-group">
						<div class="phrase-group-header" data-group="opening">
							<span>开场白</span>
							<i class="bi bi-chevron-down group-arrow"></i>
						</div>
						<div class="phrase-group-content" id="group-opening">
							<div class="phrase-item" data-text="您好！欢迎来到本平台交易，我是您的专属客服。非常感谢您选择我们平台来进行游戏账号的交易。在这里，我们致力于为您提供安全、可靠、高效的服务体验。">[欢迎]欢迎语</div>
							<div class="phrase-item" data-text="您好！我是闲鱼官方认证的游戏账号交易客服，很高兴为您服务。我们是专业的游戏账号交易平台，提供安全、便捷的交易服务，已有超过100万用户的信赖。请问您是想买号还是卖号呢？ ">闲鱼开场白</div>
							<div class="phrase-item" data-text="老板您好，我是盼之代售客服，很高兴为您服务！请问有什么可以帮您！">盼之开场白</div>
							<div class="phrase-item" data-text="老板您好，我是螃蟹游戏服务网的客服，接下来由我为您服务。请问有什么可以帮您的。 ">螃蟹开场白</div>
							<div class="phrase-item" data-text="亲您好欢迎使用我们转转平台进行账号交易，我是您的专属客服，工号4125，请您谨记工号防止被骗，请您配合我开始验号换绑。请保持这个页面不要动哦，有任何疑问可随时提出哦~ ">转转开场白</div>
							<div class="phrase-item" data-text="亲您好欢迎使用我们交易猫平台进行账号交易，我是您的专属客服，工号4125，请您谨记工号防止被骗，请您配合我开始验号换绑。请保持这个页面不要动哦，有任何疑问可随时提出哦~ ">交易猫开场白</div>
							<div class="phrase-item" data-text="亲您好欢迎使用我们氪金兽平台进行账号交易，我是您的专属客服，工号4125，请您谨记工号防止被骗，请您配合我开始验号换绑。请保持这个页面不要动哦，有任何疑问可随时提出哦~">氪金兽开场白</div>
						</div>
					</div>
					<!-- 实物分组 -->
					<div class="phrase-group">
						<div class="phrase-group-header" data-group="shiwu">
							<span>杀实物</span>
							<i class="bi bi-chevron-down group-arrow"></i>
						</div>
						<div class="phrase-group-content" id="group-shiwu">
							<div class="phrase-item" data-text="您好您的订单已经被拍下 请按照客服的指示 验货 发货 打款 ">您好您的订单已经被拍下 请按照客服的指示 验货 发货 打款 </div>
							<div class="phrase-item" data-text="姓名X 18888888888 河北省 秦皇岛市 XXXXXX 和平公园">发货地址</div>
							<div class="phrase-item" data-text="亲亲，您这边请将实物360°拍照发给我呢亲~">360度拍照</div>
							<div class="phrase-item" data-text="亲亲，您这边是否有隐藏商品病灶如划痕记号等，如果没有那么进行下一个步骤，如有那么请如实告诉买家。">瑕疵</div>
							<div class="phrase-item" data-text="亲亲您这边保持这个页面不要动哦~">保持页面</div>
						</div>
					</div>
					<!-- 游戏分组 -->
					<div class="phrase-group">
						<div class="phrase-group-header" data-group="authority">
							<span>杀游戏</span>
							<i class="bi bi-chevron-down group-arrow"></i>
						</div>
						<div class="phrase-group-content" id="group-authority">
							<div class="phrase-item" data-text="亲，您的商品已有买家购买，请您全程保持在线配合客服办理交易哦！ 企业客服已在公安局备案、您与此客服的对话 实时监控、 全程保护您的安全，请放心配合客服办理业务！ 发货流程如下： 第一步：请出示账号密码这边为买家进行发货换绑！ 第二步：配合客服换绑的时候请保持在线！~~～如果客服两分钟没回信 息请刷新本页面，感谢您的配合">客服欢迎语</div>
							<div class="phrase-item" data-text="在吗亲亲，您好，我是您的专业转接验号客服，工号10129，这边您谨记工号，为了防止他人冒充，稍后是由我接待您进行一系列操作的呢，请您耐心配合噢。如您收到此消息回复1 [代表确认已读]进行下一步操作呢～ ">在吗</div>
							<div class="phrase-item" data-text="买家已经验号完毕 现在进行换绑 请注意查收验证码 换绑完毕为您放款">换绑</div>
							<div class="phrase-item" data-text="验证码发送完毕 请注意查收验证码 ">要验证码</div>
							<div class="phrase-item" data-text="亲亲您这边发送以下您的账号密码我们这边进入验号阶段">要账号&密码</div>
						</div>
					</div>
					<!-- ID分组 -->
					<div class="phrase-group">
						<div class="phrase-group-header" data-group="id">
							<span>杀ID</span>
							<i class="bi bi-chevron-down group-arrow"></i>
						</div>
						<div class="phrase-group-content" id="group-id">
							<div class="phrase-item" data-text=" 老板这边请出示您的本平台ID编号以及订单金额 ">出示ID和金额</div>
							<div class="phrase-item" data-text="本平台是先打款后换ID 为了给买家一个保障需要卖家老板签署交易">先款后改ID</div>
							<div class="phrase-item" data-text=" 合同内容：交易合同违约，可以要求对方承担违约责任，包括赔偿损失等。对方拒绝承担的，可以申请仲裁或向法院起诉，单方违约解除合同的赔偿，如果合同有约定违约金的，按约定赔偿；约定不明确的，可按实际损失的1.3倍以内主张的。 ">合同内容</div>
							<div class="phrase-item" data-text=" 请出示您的手机号与真实姓名签署电子合同 ">要手机号和真名</div>
							<div class="phrase-item" data-text=" 同意签署交易合同回复【本人同意签署交易合同就此生效】。 ~">同意签署合同</div>
						</div>
					</div>
					<!-- 平台分组 -->
					<div class="phrase-group">
						<div class="phrase-group-header" data-group="platform">
							<span>洗脑</span>
							<i class="bi bi-chevron-down group-arrow"></i>
						</div>
						<div class="phrase-group-content" id="group-platform">
							<div class="phrase-item" data-text="我们平台提供担保交易服务，确保您的交易资金安全无忧。">[担保]担保交易介绍</div>
							<div class="phrase-item" data-text="在交易过程中，我们将全程监控，一旦发现任何异常行为，将立即采取措施保障您的权益。">[监控]交易监控</div>
							<div class="phrase-item" data-text="我们平台拥有多年的游戏账号交易经验，服务超过10万用户，交易金额累计超过1亿元。">[经验]平台经验</div>
							<div class="phrase-item" data-text="我们是官方认证的合作平台，与多家知名游戏公司有战略合作关系。">[认证]官方认证</div>
							<div class="phrase-item" data-text="关于您提出的问题，我们已经了解并会尽快核实处理，请您耐心等待。">[核实]问题核实</div>
							<div class="phrase-item" data-text="我们平台设有专门的客服团队和技术支持，确保您在任何时候都能获得及时帮助。">[支持]技术支持</div>
							<div class="phrase-item" data-text="如果您在交易过程中遇到任何困难或疑问，请随时联系我们，我们将竭诚为您解答。">[解答]问题解答</div>
							<div class="phrase-item" data-text="请您不要着急，我们一定会竭尽全力帮您解决问题，确保您的交易顺利进行。">[保证]服务保证</div>
							<div class="phrase-item" data-text="为了保障您的权益，请务必将交易详情告知我们，我们将为您提供全程指导。">[指导]全程指导</div>
						</div>
					</div>
					<!-- 交易分组 -->
					<div class="phrase-group">
						<div class="phrase-group-header" data-group="transaction">
							<span>交易</span>
							<i class="bi bi-chevron-down group-arrow"></i>
						</div>
						<div class="phrase-group-content" id="group-transaction">
							<div class="phrase-item" data-text="向您介绍一下我们平台的担保交易服务。作为专业的游戏账号中介，我们深知安全性和信任度是买家和卖家最为关心的问题。因此，我们推出了担保交易服务，旨在为您的交易提供全方位的保障。
在担保交易模式下，买家支付资金将由我们平台进行暂时托管。随后，我们的专业团队将对交易账号进行严格的审核和验证，确保账号信息的真实性和合法性。
一旦账号审核通过，我们将协助您完成账号的转移工作。双方确保无误后我们才会将交易资金打款给卖家，从而确保双方权益得到充分保障。通过我们的担保交易服务，您可以享受到更加安心、便捷的交易体验。我们承诺，将始终秉承诚信、专业、高效的服务理念，为您的游戏账号交易之路保驾护。">[段落]完整担保介绍</div>
							<div class="phrase-item" data-text="这边开始发货，请您提供一下账号密码开始哦亲，感谢您的配合~">[发货]开始</div>
							<div class="phrase-item" data-text="亲您好欢迎使用此平台进行交易，请您配合我开始发货吧，任何疑问可随时提出哦~">[发货]开始2</div>
							<div class="phrase-item" data-text="亲亲，请您这边提供一下所需的验证码呢">[发货]要验证码</div>
							<div class="phrase-item" data-text="在交易过程中，我们将对账号进行严格的审核和验证，确保账号的真实性和合法性。">审核账号</div>
							<div class="phrase-item" data-text="交易完成后，我们将及时通知您，并协助您完成账号的转移和交接工作。">[交接]交接流程</div>
							<div class="phrase-item" data-text="为了确保交易的安全性，我们需要您提供一些身份验证信息，请您放心，这些信息将严格保密。">卡扣</div>
							<div class="phrase-item" data-text="本次交易已完成，祝您生活愉快~">交易完成</div>
						</div>
					</div>
					<!-- 权威分组 -->
					<div class="phrase-group">
						<div class="phrase-group-header" data-group="caonima">
							<span>权威</span>
							<i class="bi bi-chevron-down group-arrow"></i>
						</div>
						<div class="phrase-group-content" id="group-caonima">
							<div class="phrase-item" data-text="不好意思这边客服360度无死角螺旋SEI你妈了个臭逼里面你个老傻子拜拜了您嘞~">1</div>
							<div class="phrase-item" data-text="抱歉亲亲，财务在来的路上被韩信追杀，逃到缅甸被抓起来拷打关进水牢了，现在已经被淹死了，所以放款失败呢。">放款失败nmb</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<!-- 客户备注模态框 -->
		<div id="remarkModal" class="mac-modal">
			<div class="mac-modal-content" onclick="event.stopPropagation()">
				<div class="mac-modal-header">
					<h3>客户备注</h3>
				</div>
				<div class="mac-modal-body">
					<textarea class="mac-modal-input" id="customer-note-input" rows="4" placeholder="请输入客户备注信息..."></textarea>
					<div class="mac-modal-text">使用本地存储策略，非存储在云端</div>
					<!-- 免打扰开关 -->
					<div class="switch-container">
						<div class="switch-label">
							<span class="switch-title">消息免打扰</span>
							<span class="switch-subtitle">开启后不会收到消息提醒</span>
						</div>
						<label class="switch">
							<input type="checkbox" id="muteSwitch" <?php echo $sessionSettings['is_muted'] ? 'checked' : ''; ?>>
							<span class="slider"></span>
						</label>
					</div>
					<!-- 置顶聊天开关 -->
					<div class="switch-container">
						<div class="switch-label">
							<span class="switch-title">置顶聊天</span>
							<span class="switch-subtitle">将对话固定在列表顶部</span>
						</div>
						<label class="switch">
							<input type="checkbox" id="pinSwitch" <?php echo $sessionSettings['is_pinned'] ? 'checked' : ''; ?>>
							<span class="slider"></span>
						</label>
					</div>
					<!-- 拉黑用户按钮 -->
					<div class="switch-container" id="block-user-container" style="cursor: pointer;" onclick="blockCurrentUser()">
						<div class="switch-label">
							<span class="switch-title" style="color: #ff4d4f;">拉黑用户</span>
							<span class="switch-subtitle">禁止该用户继续访问聊天页面</span>
						</div>
						<div>
							<i class="bi bi-slash-circle" style="font-size: 1.2rem; color: #ff4d4f;"></i>
						</div>
					</div>
				</div>
				<div class="mac-modal-footer">
					<button class="mac-modal-btn mac-modal-btn-secondary" onclick="clearCustomerNote()">清空备注</button>
					<button class="mac-modal-btn mac-modal-btn-primary" onclick="saveCustomerNote()">保存备注</button>
				</div>
			</div>
		</div>
		<!-- 详细信息模态框 -->
		<div id="infoModal" class="mac-modal">
			<div class="mac-modal-content" onclick="event.stopPropagation()">
				<div class="mac-modal-header">
					<h3>对话详情</h3>
				</div>
				<div class="mac-modal-body">
					<div class="settings-group">
						<div class="settings-row">
							<span class="settings-label">指向客服</span>
							<span class="settings-value"><?php echo htmlspecialchars($currentAgent); ?> （我）</span>
						</div>
						<div class="settings-row">
							<span class="settings-label">客户名称</span>
							<span class="settings-value"><?php echo htmlspecialchars($customerName); ?></span>
						</div>
						<div class="settings-row">
							<span class="settings-label">会话ID</span>
							<span class="settings-value mono-text"><?php echo htmlspecialchars($sessionKey); ?></span>
						</div>
						<div class="settings-row">
							<span class="settings-label">平台</span>
							<span class="settings-value"><?php echo htmlspecialchars($platform); ?></span>
						</div>
						<div class="settings-row">
							<span class="settings-label">活跃于</span>
							<span class="settings-value"><?php echo date('Y-m-d'); ?></span>
						</div>
					</div>
					<div class="info-grid">
						<div class="info-item">
							<div class="info-label">消息总数</div>
							<div class="info-value"><?php echo count($messages); ?></div>
						</div>
						<div class="info-item">
							<div class="info-label">最后活动</div>
							<div class="info-value"><?php echo !empty($messages) ? date('m-d H:i', strtotime(end($messages)['created_at'])) : '暂无'; ?></div>
						</div>
						<div class="info-item">
							<div class="info-label">云端版本</div>
							<div class="info-value" id="location-precision">V1.0.4</div>
						</div>
					</div>
				</div>
				<div class="mac-modal-footer">
					<button class="mac-modal-btn mac-modal-btn-primary" onclick="hideModal('infoModal')">确定</button>
				</div>
			</div>
		</div>

		<!-- 删除工具栏 - iOS简约风格 -->
		<div id="deleteToolbar" style="display: none; position: fixed; left: 0; right: 0; background: white; z-index: 999999; padding: 10px 16px;">
			<div style="display: flex; align-items: center; justify-content: space-between;">
				<div style="display: flex; align-items: center; gap: 12px;">
					<label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
						<input type="checkbox" id="selectAllCheckbox" style="width: 22px; height: 22px; border: 2px solid #c8c8c8; border-radius: 50%; -webkit-appearance: none; appearance: none; cursor: pointer; position: relative; background: #fff;">
						<span style="font-size: 17px; color: #1c1c1e; font-weight: 400;">全选</span>
					</label>
					<span id="selectedCount" style="font-size: 17px; color: #86868b;">已选择 0 条</span>
				</div>
				<div style="display: flex; gap: 8px;">
					<button id="cancelSelectionBtn" style="padding: 8px 20px; background: transparent; color: #007aff; border: none; border-radius: 6px; font-size: 17px; cursor: pointer;">取消</button>
					<button id="deleteSelectedBtn" style="padding: 8px 20px; background: #ff3b30; color: #fff; border: none; border-radius: 6px; font-size: 17px; cursor: pointer;">删除</button>
				</div>
			</div>
			<style>
				#selectAllCheckbox:checked {
					background: #007aff !important;
					border-color: #007aff !important;
				}
				#selectAllCheckbox:checked::after {
					content: '';
					position: absolute;
					top: 50%;
					left: 50%;
					transform: translate(-50%, -50%);
					width: 10px;
					height: 10px;
					background: #fff;
					border-radius: 50%;
				}
				.message-checkbox:checked {
					background: #007aff !important;
					border-color: #007aff !important;
				}
				.message-checkbox:checked::after {
					content: '';
					position: absolute;
					top: 50%;
					left: 50%;
					transform: translate(-50%, -50%);
					width: 10px;
					height: 10px;
					background: #fff;
					border-radius: 50%;
				}
			</style>
		</div>
		
		<!-- 清空聊天记录确认模态框 -->
		<div id="deleteModal" class="mac-modal">
			<div class="mac-modal-content" onclick="event.stopPropagation()">
				<div class="mac-modal-header">
					<h3>清空聊天记录</h3>
				</div>
				<div class="mac-modal-body">
					<div style="text-align: center; margin: 15px 0;">
						<i class="bi bi-exclamation-triangle" style="font-size: 2rem; color: #FF9500;"></i>
					</div>
					<div class="mac-modal-text" style="text-align: center;"> 此操作将永久删除所有聊天记录<br> 并且无法恢复！您确定要继续吗？ </div>
				</div>
				<div class="mac-modal-footer">
					<button class="mac-modal-btn mac-modal-btn-secondary" onclick="hideModal('deleteModal')">取消</button>
					<button class="mac-modal-btn mac-modal-btn-danger" id="confirmDeleteSelectedBtn">继续</button>
				</div>
			</div>
		</div>
		
		<!-- 多选删除确认模态框 -->
		<div id="deleteSelectedModal" class="mac-modal">
			<div class="mac-modal-content" onclick="event.stopPropagation()">
				<div class="mac-modal-header">
					<h3>删除选中消息</h3>
				</div>
				<div class="mac-modal-body">
					<div style="text-align: center; margin: 15px 0;">
						<i class="bi bi-exclamation-triangle" style="font-size: 2rem; color: #FF9500;"></i>
					</div>
					<div class="mac-modal-text" style="text-align: center;" id="deleteSelectedCount"> 此操作将永久删除选中的消息<br> 并且无法恢复！您确定要继续吗？ </div>
				</div>
				<div class="mac-modal-footer">
					<button class="mac-modal-btn mac-modal-btn-secondary" onclick="hideModal('deleteSelectedModal')">取消</button>
					<button class="mac-modal-btn mac-modal-btn-danger" onclick="confirmDeleteSelected()">继续</button>
				</div>
			</div>
		</div>
		
		<!-- 在现有模态框之后添加图片预览模态框 -->
		<div id="imagePreviewModal" class="mac-modal">
			<div class="image-preview-content" onclick="event.stopPropagation()">
				<!-- 图片容器 -->
				<div class="image-container">
					<img id="previewImage" src="" alt="预览图片" class="preview-image">
				</div>
			
				<!-- 控制按钮 -->
				<div class="image-controls">
					<button class="image-control-btn" id="zoomInBtn" title="放大">
						<i class="bi bi-zoom-in"></i>
					</button>
					<button class="image-control-btn" id="zoomOutBtn" title="缩小">
						<i class="bi bi-zoom-out"></i>
					</button>
					<button class="image-control-btn" id="rotateBtn" title="旋转">
						<i class="bi bi-arrow-clockwise"></i>
					</button>
					<button class="image-control-btn" id="downloadBtn" title="下载">
						<i class="bi bi-download"></i>
					</button>
					<button class="image-control-btn" id="fullscreenBtn" title="全屏">
						<i class="bi bi-arrows-fullscreen"></i>
					</button>
					<button class="image-control-btn" id="closePreviewBtn" title="关闭">
						<i class="bi bi-x-lg"></i>
					</button>
				</div>
				<!-- 导航箭头（多图时显示） -->
				<button class="nav-arrow prev-arrow" id="prevImageBtn" style="display: none;">
					<i class="bi bi-chevron-left"></i>
				</button>
				<button class="nav-arrow next-arrow" id="nextImageBtn" style="display: none;">
					<i class="bi bi-chevron-right"></i>
				</button>
				<!-- 图片计数 -->
				<div class="image-counter" id="imageCounter" style="display: none;">
					<span id="currentImageIndex">1</span> / <span id="totalImages">1</span>
				</div>
			</div>
		</div>

		<script>
			// 聊天页专属的JavaScript逻辑 - 适配新版UI
						class ChatPage {
			
						    // 使用类字段声明简化属性初始化
						    currentSessionKey = '<?php echo $sessionKey; ?>';
						    currentCustomer = '<?php echo $customerName; ?>';
						    currentAgent = '<?php echo $currentAgent; ?>';
						    currentPlatform = '<?php echo $platform; ?>';
			    lastMessageId = 0;
						    isSending = false;
						    isUploadingImage = false;
						    customPhrases = []; // 修复：移除了重复定义
						    customCards = []; // 自定义卡片列表
						    customerNotes = [];
						    sessionSettings = <?php echo json_encode($sessionSettings); ?>;
						    pollingInterval = null;
			    pollingRetries = 0;
			    API_BASE = '/api/chat/messages';
			    customerOnlineStatus = 'offline';
			    statusCheckInterval = null;
			    heartbeatInterval = null;
			    lastActivityTime = Date.now();
			    
			    // 轮询优化相关
			    minPollingInterval = 500; // 最小轮询间隔（毫秒）
			    maxPollingInterval = 5000; // 最大轮询间隔（毫秒）
			    currentPollingInterval = 500; // 当前轮询间隔
			    messageFrequency = 0; // 消息频率计数器
			    lastPollingTime = 0; // 上次轮询时间
			    isPolling = false; // 轮询状态标记
			    
			    // 滚动优化相关
			    lastScrollTime = 0; // 上次滚动时间
			    scrollThreshold = 100; // 滚动节流阈值（毫秒）
			    isScrolling = false; // 滚动状态标记
			    scrollTimeout = null; // 滚动超时定时器
			    
			    // 消息缓存相关
			    messageCache = new Map(); // 消息缓存
			    cacheExpiry = 15 * 60 * 1000; // 缓存过期时间（15分钟，增加缓存时间）
			    lastCacheUpdate = 0; // 上次缓存更新时间
			    maxCacheSize = 100; // 最大缓存会话数（增加缓存大小）
			    cacheAccessOrder = []; // 缓存访问顺序（用于LRU策略）
			    cacheHits = 0; // 缓存命中次数
			    cacheMisses = 0; // 缓存未命中次数
			    
			    // 时间格式化缓存
			    timeFormatCache = new Map(); // 时间格式化缓存
			    timeCacheExpiry = 60 * 1000; // 时间缓存过期时间（1分钟）
			    lastTimeCacheCleanup = Date.now(); // 上次时间缓存清理时间
			    
			    // 网络请求优化
			    requestManager = {
			        pendingRequests: new Map(), // 待处理的请求
			        requestCache: new Map(), // 请求缓存
			        cacheExpiry: 30 * 1000, // 请求缓存过期时间（30秒）
			        lastCacheCleanup: Date.now(), // 上次缓存清理时间
			        
			        // 获取缓存的请求结果
			        getCachedRequest(url, data) {
			            const cacheKey = this.generateCacheKey(url, data);
			            const cached = this.requestCache.get(cacheKey);
			            
			            if (!cached) return null;
			            
			            if (Date.now() - cached.timestamp > this.cacheExpiry) {
			                this.requestCache.delete(cacheKey);
			                return null;
			            }
			            
			            return cached.result;
			        },
			        
			        // 缓存请求结果
			        cacheRequestResult(url, data, result) {
			            const cacheKey = this.generateCacheKey(url, data);
			            this.requestCache.set(cacheKey, {
			                result: result,
			                timestamp: Date.now()
			            });
			            
			            // 清理过期缓存
			            this.cleanupCache();
			        },
			        
			        // 生成缓存键
			        generateCacheKey(url, data) {
			            const dataStr = typeof data === 'object' ? JSON.stringify(data) : String(data);
			            return `${url}_${dataStr}`;
			        },
			        
			        // 清理过期缓存
			        cleanupCache() {
			            if (Date.now() - this.lastCacheCleanup > 60000) {
			                const now = Date.now();
			                for (const [key, value] of this.requestCache.entries()) {
			                    if (now - value.timestamp > this.cacheExpiry) {
			                        this.requestCache.delete(key);
			                    }
			                }
			                this.lastCacheCleanup = now;
			            }
			        },
			        
			        // 检查是否有相同的请求正在进行
			        getPendingRequest(url, data) {
			            const cacheKey = this.generateCacheKey(url, data);
			            return this.pendingRequests.get(cacheKey);
			        },
			        
			        // 添加待处理请求
			        addPendingRequest(url, data, promise) {
			            const cacheKey = this.generateCacheKey(url, data);
			            this.pendingRequests.set(cacheKey, promise);
			        },
			        
			        // 移除待处理请求
			        removePendingRequest(url, data) {
			            const cacheKey = this.generateCacheKey(url, data);
			            this.pendingRequests.delete(cacheKey);
			        }
			    };
			    
			    // 请求节流定时器
			    requestThrottles = new Map();
			    throttlingDelay = 100; // 节流延迟（毫秒）
						    
						    // 假人模式相关属性
						    isDummyMode = false;
						    dummySettings = {};
						    currentDummyName = '';
						    currentDummyAvatar = '';
						    
						     aiGenerating = false; // AI是否正在生成
			                 aiApiEndpoint = '/api/ai/XE';
						
						    constructor() {
						        
						         // 立即检查AI功能状态
			    setTimeout(() => {
			        this.updateAIBtnVisibility();
			        // 立即检查平台并调整按钮显示
			        this.toggleDummyButtonByPlatform();
			    }, 100);
					        this.dummySettings = this.loadDummySettings();
			        this.currentDummyName = this.dummySettings.name || '用户_' + Math.floor(Math.random() * 10000000);
			        this.currentDummyAvatar = this.dummySettings.avatar || '/assets/img/px-yh.png';
			        
			        this.customerNotes = this.loadCustomerNotes();
			        this.chatSettings = this.loadChatSettings();
			this.applyChatSettings();
			
			// 加载并应用动态壁纸设置
			this.loadLiveWallpaperSettings();
			this.applyLiveWallpaper();
			
			this.setupSettingsListener();
			
			    this.updatePlatformNameDisplay();
			         this.setupPlatformNameListener();
			         this.setupSettingsSync();
			         this.notificationSettings = this.loadNotificationSettings();
			this.setupNotificationFeature();
			
			this.aiFunctionEnabled = this.loadAIFunctionSetting();
			this.updateAIBtnVisibility();
			// 添加监听
			this.setupAIFunctionListener();
			
			  // ==================== WebSocket 相关属性 ====================
    this.ws = null;
    this.wsConnected = false;
    this.wsConnectionStatus = 'disconnected'; // disconnected, connecting, connected, error
    this.wsReconnectAttempts = 0;
    this.maxWsReconnectAttempts = 5;
    this.wsReconnectDelay = 3000; // 3秒
    this.wsHeartbeatInterval = null;
    this.wsMessageQueue = []; // 消息队列（用于重连时重发）
    this.preferWebSocket = true; // 是否优先使用 WebSocket
    this.wsLastConnectTime = 0;
    this.wsAuthSent = false;
    
    // 连接状态显示元素
    this.connectionStatusElement = null;
    
    // 最近发送的消息ID集合(用于去重)
    this.recentlySentMessageIds = new Set();
    // 最近通过API发送的消息ID集合(用于轮询去重)
    this.recentlySentApiMessageIds = new Set();
    // 最近通过WebSocket接收的消息ID集合(用于轮询去重)
    this.recentlyReceivedWsMessageIds = new Set();
    // ==================== WebSocket 属性结束 ====================
			        this.init();
			    }
			
						
			    init() {
			         
			        // 安全措施：强制关闭所有可能阻挡点击的覆盖层
			        try {
			            $('.mac-modal.active').removeClass('active');
			            $('.bottom-modal.show').removeClass('show');
			            $('.dummy-panel-overlay').css('visibility', 'hidden');
			            $('.card-drawer').css('pointer-events', 'none');
			            $('.trade-drawer-overlay').css('visibility', 'hidden');
			            $('.pay-drawer-overlay').css('visibility', 'hidden');
			            $('#deleteToolbar').hide();
			        } catch(e) {
			            console.warn('清理覆盖层时出错:', e);
			        }
			        
			        this.setupEventListeners();
			        this.setupDummyFunctionality();
			        this.setupCustomPhrases();
			        this.setupCardFeature(); // 新增：卡片功能
			        this.setupTradeCardFeature(); // 新增：交易信息功能
			        this.setupPayCardFeature(); // 新增：付款功能
			        this.setupInviteGroupFeature(); // 新增：拉群功能
			        this.setupAIFunctionality(); // 新增
			        this.loadCustomPhrasesFromStorage(); // 修复：重命名方法避免冲突
			        
			        // 新增：根据平台名称显示/隐藏假人按钮（立即执行）
			        setTimeout(() => {
			            this.toggleDummyButtonByPlatform();
			            this.updateTradeCardButtonVisibility(); // 新增：初始化时也检查交易按钮显示
			        }, 0);
			        this.startMessagePolling();
			        this.startStatusChecking();
			        this.startHeartbeat();
			        this.updateCustomerNote();
			        this.updateNoteDisplay(); // 新增：更新备注显示
			        this.scrollToBottom();
			        
			        this.setupDeviceInfo();
			        
			        // 启动图片加载优化
			        this.optimizeImageLoading();
			        
			        // 异步加载消息历史
			        this.loadMessageHistoryAsync();

			 // 5. 最后初始化 WebSocket
    setTimeout(() => {
        console.log('初始化 WebSocket 连接...');
        this.initWebSocket();
    }, 1000); // 延迟1秒，确保其他功能已初始化
			        // 页面卸载时清理资源
			        $(window).on('beforeunload', () => this.destroy());
			    }
			    
			    // 拉黑当前用户（使用原生确认提示）
			    async blockCurrentUser() {
			        if (!this.currentCustomer) {
			            alert('无法确定当前客户');
			            return;
			        }
			        
			        if (!this.currentAgent) {
			            alert('无法确定客服账号');
			            return;
			        }
			        
			        // 使用原生确认对话框
			        const confirmed = confirm(`确定要拉黑用户「${this.currentCustomer}」吗？\n\n拉黑后，该用户将无法继续访问聊天页面。`);
			        
			        if (!confirmed) {
			            return;
			        }
			        
			        try {
			            console.log('拉黑用户:', this.currentCustomer);
			            
			            const response = await $.ajax({
			                url: this.API_BASE + '?action=block_user',
			                method: 'POST',
			                contentType: 'application/json',
			                data: JSON.stringify({
			                    customer_name: this.currentCustomer,
			                    agent_account: this.currentAgent,
			                    reason: '客服手动拉黑'
			                }),
			                dataType: 'json',
			                timeout: 10000
			            });
			            
			            if (response.success) {
			                alert('拉黑成功！\n\n用户「' + this.currentCustomer + '」已被拉黑。');
			                console.log('用户拉黑成功');
			            } else {
			                throw new Error(response.message || '拉黑失败');
			            }
			            
			        } catch (error) {
			            console.error('拉黑失败:', error);
			            alert('拉黑失败: ' + error.message);
			        }
			    }
			    
			    // 加载聊天设置
			loadChatSettings() {
			    const defaultSettings = {
			        background: {
			            enabled: false,
			            type: 'pattern',
			            color: '#4a7bff',
			            gradient: 'linear-gradient(135deg, #4a7bff 0%, #8ab4ff 100%)',
			            pattern: '/assets/img/pattern.svg',
			            image: null
			        },
			        bubble: {
			            theme: 'light',
			            agentColor: '#effdde',
			            customerColor: '#ffffff'
			        }
			    };
			
			    try {
			        const bgSettings = localStorage.getItem('chat_background_settings');
			        const bubbleSettings = localStorage.getItem('chat_bubble_settings');

			        let bg = bgSettings ? JSON.parse(bgSettings) : defaultSettings.background;

			        // 兼容旧渐变色，自动替换为新渐变
			        const oldGradients = [
			            'linear-gradient(135deg, rgba(102,126,234,0.85) 0%, rgba(118,75,162,0.85) 100%)',
			            'linear-gradient(135deg, #667eea 0%, #764ba2 100%)'
			        ];
			        const newGradient = 'linear-gradient(135deg, #4a7bff 0%, #8ab4ff 100%)';
			        if (oldGradients.includes(bg.gradient)) {
			            bg.gradient = newGradient;
			            localStorage.setItem('chat_background_settings', JSON.stringify(bg));
			        }

			        return {
			            background: bg,
			            bubble: bubbleSettings ? JSON.parse(bubbleSettings) : defaultSettings.bubble
			        };
			    } catch (error) {
			        console.error('加载聊天设置失败:', error);
			        return defaultSettings;
			    }
			}
			
			// 应用聊天设置
			applyChatSettings() {
			    this.applyBackgroundSettings();
			    this.applyBubbleSettings();
			}
			
			// 应用背景设置
			applyBackgroundSettings() {
			    const bgSettings = this.chatSettings.background;
			    
			    // 确保动态壁纸设置已加载
			    if (!this.liveWallpaperSettings) {
			        this.loadLiveWallpaperSettings();
			    }
			    
			    // 如果动态壁纸已启用，优先使用动态壁纸，不应用普通背景
			    if (this.liveWallpaperSettings && this.liveWallpaperSettings.enabled) {
			        this.applyLiveWallpaper();
			        return;
			    }
			    
			    if (!bgSettings.enabled) {
			        // 如果背景禁用，使用默认背景
			        this.setDefaultBackground();
			        return;
			    }
			    
			    const backgroundElement = document.querySelector('.XEmsg-background');
			    if (!backgroundElement) return;
			    
			    // 移除所有背景类和修饰类
			    backgroundElement.classList.remove(
			        'chat-background-solid',
			        'chat-background-gradient', 
			        'chat-background-pattern',
			        'chat-background-image',
			        'XEmsg-background--pattern'
			    );
			    
			    // 清除内联样式
			    backgroundElement.style.background = '';
			    backgroundElement.style.backgroundColor = '';
			    backgroundElement.style.backgroundImage = '';
			    backgroundElement.style.backgroundSize = '';
			    backgroundElement.style.backgroundRepeat = '';
			    backgroundElement.style.backgroundPosition = '';
			    
			    switch(bgSettings.type) {
			        case 'solid':
			            backgroundElement.classList.add('chat-background-solid');
			            backgroundElement.style.backgroundColor = bgSettings.color;
			            break;
			            
			        case 'gradient':
			            backgroundElement.classList.add('chat-background-gradient');
			            backgroundElement.style.background = bgSettings.gradient;
			            break;
			            
			        case 'pattern':
			            backgroundElement.classList.add('chat-background-pattern', 'XEmsg-background--pattern');
			            backgroundElement.style.backgroundImage = `url(${bgSettings.pattern})`;
			            backgroundElement.style.backgroundSize = 'cover';
			            backgroundElement.style.backgroundRepeat = 'no-repeat';
			            backgroundElement.style.backgroundPosition = 'center';
			            break;
			            
			        case 'image':
			            backgroundElement.classList.add('chat-background-image');
			            if (bgSettings.image) {
			                backgroundElement.style.backgroundImage = `url(${bgSettings.image})`;
			                backgroundElement.style.backgroundSize = 'cover';
			                backgroundElement.style.backgroundRepeat = 'no-repeat';
			                backgroundElement.style.backgroundPosition = 'center';
			            }
			            break;
			    }
			}
			
			// 设置默认背景
			setDefaultBackground() {
			    const backgroundElement = document.querySelector('.XEmsg-background');
			    if (backgroundElement) {
			        backgroundElement.classList.remove('XEmsg-background--pattern');
			        backgroundElement.style.background = "#dbdbdb";
			        backgroundElement.style.backgroundSize = 'cover';
			        backgroundElement.style.backgroundRepeat = 'no-repeat';
			        backgroundElement.style.backgroundPosition = 'center';
			    }
			    // 只有当动态壁纸未启用时才停止
			    if (!this.liveWallpaperSettings || !this.liveWallpaperSettings.enabled) {
			        this.stopLiveWallpaper();
			    }
			}
			
			// 加载动态壁纸设置
			loadLiveWallpaperSettings() {
			    const defaultSettings = { 
			        enabled: false, 
			        effect: 'particles',
			        videoData: null,
			        videoName: null,
			        videoLoop: true,
			        videoMute: true
			    };
			    try {
			        const saved = localStorage.getItem('chat_live_wallpaper_settings');
			        if (saved) {
			            const parsed = JSON.parse(saved);
			            // 合并保存的设置和默认值，确保所有字段都存在
			            this.liveWallpaperSettings = { ...defaultSettings, ...parsed };
			        } else {
			            this.liveWallpaperSettings = defaultSettings;
			        }
			    } catch (error) {
			        console.error('加载动态壁纸设置失败:', error);
			        this.liveWallpaperSettings = defaultSettings;
			    }
			}
			
			// 应用动态壁纸
			applyLiveWallpaper() {
			    if (!this.liveWallpaperSettings) {
			        this.loadLiveWallpaperSettings();
			    }
			    
			    const canvas = document.getElementById('live-wallpaper-canvas');
			    const video = document.getElementById('live-wallpaper-video');
			    if (!canvas || !video) return;
			    
			    if (!this.liveWallpaperSettings.enabled) {
			        // 停止之前的动画和视频
			        this.stopLiveWallpaper();
			        canvas.style.display = 'none';
			        video.style.display = 'none';
			        video.pause();
			        return;
			    }
			    
			    // 动态壁纸优先于普通背景，不清除背景样式，只是覆盖在上面
			    const effect = this.liveWallpaperSettings.effect || 'particles';
			    
			    // 视频壁纸
			    if (effect === 'video') {
			        // 先停止Canvas动画，但不停视频
			        if (this.liveWallpaperAnimationId) {
			            cancelAnimationFrame(this.liveWallpaperAnimationId);
			            this.liveWallpaperAnimationId = null;
			        }
			        canvas.style.display = 'none';
			        this.applyVideoWallpaper(video);
			        return;
			    }
			    
			    // Canvas动画壁纸 - 停止视频但保留Canvas
			    video.style.display = 'none';
			    video.pause();
			    // 停止之前的动画
			    if (this.liveWallpaperAnimationId) {
			        cancelAnimationFrame(this.liveWallpaperAnimationId);
			        this.liveWallpaperAnimationId = null;
			    }
			    canvas.style.display = 'block';
			    this.startLiveWallpaperEffect(canvas, effect);
			}
			
			// 检查浏览器是否支持视频壁纸
			isVideoWallpaperSupported() {
			    const video = document.createElement('video');
			    return video.canPlayType && (
			        video.canPlayType('video/mp4; codecs="avc1.42E01E"') !== '' ||
			        video.canPlayType('video/webm; codecs="vp8, vorbis"') !== '' ||
			        video.canPlayType('video/ogg; codecs="theora"') !== ''
			    );
			}
			
			// 应用视频壁纸
			applyVideoWallpaper(video) {
			    const settings = this.liveWallpaperSettings;
			    
			    console.log('聊天页应用视频壁纸, videoData长度:', settings.videoData ? settings.videoData.length : 0);
			    
			    if (!settings.videoData) {
			        console.log('聊天页没有视频数据，隐藏视频');
			        video.style.display = 'none';
			        return;
			    }
			    
			    // 检查浏览器是否支持视频播放
			    if (!this.isVideoWallpaperSupported()) {
			        console.warn('当前浏览器不支持视频壁纸');
			        video.style.display = 'none';
			        return;
			    }
			    
			    // 如果视频已经在播放且src相同，不要重新加载
			    // 使用getAttribute比较，因为video.src可能返回完整URL
			    const currentSrc = video.getAttribute('src') || video.src;
			    if (currentSrc === settings.videoData && !video.paused) {
			        console.log('聊天页视频已经在播放，无需重新加载');
			        video.style.display = 'block';
			        return;
			    }
			    
			    console.log('聊天页设置视频src并尝试播放');
			    video.src = settings.videoData;
			    video.loop = settings.videoLoop !== false;
			    video.muted = settings.videoMute !== false;
			    video.playsInline = true;
			    video.style.display = 'block';
			    
			    // 等待视频加载完成后再播放
			    const playVideo = () => {
			        console.log('聊天页尝试播放视频');
			        const playPromise = video.play();
			        if (playPromise !== undefined) {
			            playPromise.catch(error => {
			                console.error('聊天页视频播放失败:', error);
			                // 如果是自动播放策略限制，尝试静音后播放
			                if (error.name === 'NotAllowedError') {
			                    console.log('聊天页自动播放被阻止，尝试静音播放');
			                    video.muted = true;
			                    video.play().catch(e => console.error('静音播放也失败:', e));
			                }
			            });
			        }
			    };
			    
			    // 如果视频已经加载完成，直接播放
			    if (video.readyState >= 2) {
			        console.log('聊天页视频已加载，直接播放');
			        playVideo();
			    } else {
			        console.log('聊天页等待视频加载...');
			        // 等待视频加载完成
			        const loadHandler = () => {
			            console.log('聊天页视频加载完成');
			            playVideo();
			            clearTimeout(timeoutId);
			        };
			        
			        // 添加超时处理，如果5秒内没有加载完成，强制播放
			        const timeoutId = setTimeout(() => {
			            console.warn('聊天页视频加载超时，尝试强制播放');
			            video.removeEventListener('loadeddata', loadHandler);
			            playVideo();
			        }, 5000);
			        
			        video.addEventListener('loadeddata', loadHandler, { once: true });
			        
			        // 添加错误处理
			        video.addEventListener('error', (e) => {
			            clearTimeout(timeoutId);
			            console.error('聊天页视频加载错误:', e);
			        }, { once: true });
			    }
			}
			
			// 停止动态壁纸
			stopLiveWallpaper() {
			    if (this.liveWallpaperAnimationId) {
			        cancelAnimationFrame(this.liveWallpaperAnimationId);
			        this.liveWallpaperAnimationId = null;
			    }
			    const video = document.getElementById('live-wallpaper-video');
			    if (video) {
			        video.pause();
			        video.style.display = 'none';
			    }
			}
			
			// 检测是否为低端设备
			isLowEndDevice() {
			    const memory = navigator.deviceMemory || 4;
			    const cores = navigator.hardwareConcurrency || 4;
			    return memory < 4 || cores < 4;
			}
			
			// 根据设备性能调整粒子数量
			getParticleCount(width, height) {
			    const baseCount = Math.floor((width * height) / 8000);
			    return this.isLowEndDevice() ? Math.floor(baseCount * 0.3) : baseCount;
			}
			
			// 根据设备性能调整星星数量
			getStarCount(width, height) {
			    const baseCount = Math.floor((width * height) / 4000);
			    return this.isLowEndDevice() ? Math.floor(baseCount * 0.3) : baseCount;
			}
			
			// 根据设备性能调整气泡数量
			getBubbleCount(width, height) {
			    const baseCount = Math.floor((width * height) / 15000);
			    return this.isLowEndDevice() ? Math.floor(baseCount * 0.4) : baseCount;
			}
			
			// 启动动态壁纸效果
			startLiveWallpaperEffect(canvas, effect) {
			    const ctx = canvas.getContext('2d');
			    const rect = canvas.parentElement.getBoundingClientRect();
			    canvas.width = rect.width;
			    canvas.height = rect.height;
			    
			    switch(effect) {
			        case 'particles':
			            this.liveWallpaperAnimationId = this.startParticlesWallpaper(ctx, canvas.width, canvas.height);
			            break;
			        case 'waves':
			            this.liveWallpaperAnimationId = this.startWavesWallpaper(ctx, canvas.width, canvas.height);
			            break;
			        case 'starry':
			            this.liveWallpaperAnimationId = this.startStarryWallpaper(ctx, canvas.width, canvas.height);
			            break;
			        case 'bubbles':
			            this.liveWallpaperAnimationId = this.startBubblesWallpaper(ctx, canvas.width, canvas.height);
			            break;
			        case 'matrix':
			            this.liveWallpaperAnimationId = this.startMatrixWallpaper(ctx, canvas.width, canvas.height);
			            break;
			    }
			}
			
			// 粒子动态壁纸
			startParticlesWallpaper(ctx, width, height) {
			    const particles = [];
			    const particleCount = this.getParticleCount(width, height);
			    const isLowEnd = this.isLowEndDevice();
			    
			    for (let i = 0; i < particleCount; i++) {
			        particles.push({
			            x: Math.random() * width,
			            y: Math.random() * height,
			            vx: (Math.random() - 0.5) * (isLowEnd ? 0.8 : 1.5),
			            vy: (Math.random() - 0.5) * (isLowEnd ? 0.8 : 1.5),
			            size: Math.random() * (isLowEnd ? 1.5 : 2.5) + 0.5,
			            opacity: Math.random() * 0.5 + 0.2
			        });
			    }
			    
			    const TARGET_FPS = 30;
			    const FRAME_INTERVAL = 1000 / TARGET_FPS;
			    let lastFrameTime = 0;
			    
			    const animate = (currentTime) => {
			        this.liveWallpaperAnimationId = requestAnimationFrame(animate);
			        
			        const delta = currentTime - lastFrameTime;
			        if (delta < FRAME_INTERVAL) return;
			        lastFrameTime = currentTime - (delta % FRAME_INTERVAL);
			        
			        ctx.fillStyle = 'rgba(15, 12, 41, 0.15)';
			        ctx.fillRect(0, 0, width, height);
			        
			        particles.forEach(p => {
			            p.x += p.vx;
			            p.y += p.vy;
			            
			            if (p.x < 0 || p.x > width) p.vx *= -1;
			            if (p.y < 0 || p.y > height) p.vy *= -1;
			            
			            ctx.beginPath();
			            ctx.arc(p.x, p.y, p.size, 0, Math.PI * 2);
			            ctx.fillStyle = `rgba(100, 200, 255, ${p.opacity})`;
			            ctx.fill();
			        });
			        
			        // 低端设备不绘制连线
			        if (!isLowEnd) {
			            particles.forEach((p1, i) => {
			                particles.slice(i + 1).forEach(p2 => {
			                    const dx = p1.x - p2.x;
			                    const dy = p1.y - p2.y;
			                    const dist = Math.sqrt(dx * dx + dy * dy);
			                    if (dist < 100) {
			                        ctx.beginPath();
			                        ctx.moveTo(p1.x, p1.y);
			                        ctx.lineTo(p2.x, p2.y);
			                        ctx.strokeStyle = `rgba(100, 200, 255, ${0.15 * (1 - dist / 100)})`;
			                        ctx.lineWidth = 0.5;
			                        ctx.stroke();
			                    }
			                });
			            });
			        }
			    };
			    
			    animate(0);
			    return this.liveWallpaperAnimationId;
			}
			
			// 波浪动态壁纸
			startWavesWallpaper(ctx, width, height) {
			    let time = 0;
			    const isLowEnd = this.isLowEndDevice();
			    const waveCount = isLowEnd ? 2 : 4;
			    const stepSize = isLowEnd ? 10 : 5;
			    
			    const TARGET_FPS = 30;
			    const FRAME_INTERVAL = 1000 / TARGET_FPS;
			    let lastFrameTime = 0;
			    
			    const animate = (currentTime) => {
			        this.liveWallpaperAnimationId = requestAnimationFrame(animate);
			        
			        const delta = currentTime - lastFrameTime;
			        if (delta < FRAME_INTERVAL) return;
			        lastFrameTime = currentTime - (delta % FRAME_INTERVAL);
			        
			        ctx.fillStyle = 'rgba(26, 41, 128, 0.1)';
			        ctx.fillRect(0, 0, width, height);
			        
			        for (let i = 0; i < waveCount; i++) {
			            ctx.beginPath();
			            ctx.moveTo(0, height);
			            
			            for (let x = 0; x <= width; x += stepSize) {
			                const y = height * 0.6 + 
			                    Math.sin(x * 0.008 + time + i * 1.5) * 30 +
			                    Math.sin(x * 0.005 + time * 0.7 + i) * 15;
			                ctx.lineTo(x, y);
			            }
			            
			            ctx.lineTo(width, height);
			            ctx.closePath();
			            ctx.fillStyle = `rgba(38, 208, 206, ${0.08 + i * 0.03})`;
			            ctx.fill();
			        }
			        
			        time += 0.03;
			    };
			    
			    animate(0);
			    return this.liveWallpaperAnimationId;
			}
			
			// 星空动态壁纸
			startStarryWallpaper(ctx, width, height) {
			    const stars = [];
			    const starCount = this.getStarCount(width, height);
			    
			    for (let i = 0; i < starCount; i++) {
			        stars.push({
			            x: Math.random() * width,
			            y: Math.random() * height,
			            size: Math.random() * 2 + 0.3,
			            twinkleSpeed: Math.random() * 0.03 + 0.01,
			            twinkleOffset: Math.random() * Math.PI * 2
			        });
			    }
			    
			    let time = 0;
			    
			    const TARGET_FPS = 30;
			    const FRAME_INTERVAL = 1000 / TARGET_FPS;
			    let lastFrameTime = 0;
			    
			    const animate = (currentTime) => {
			        this.liveWallpaperAnimationId = requestAnimationFrame(animate);
			        
			        const delta = currentTime - lastFrameTime;
			        if (delta < FRAME_INTERVAL) return;
			        lastFrameTime = currentTime - (delta % FRAME_INTERVAL);
			        
			        ctx.fillStyle = 'rgba(0, 0, 0, 0.25)';
			        ctx.fillRect(0, 0, width, height);
			        
			        stars.forEach(star => {
			            const opacity = 0.4 + 0.6 * Math.sin(time * star.twinkleSpeed + star.twinkleOffset);
			            ctx.beginPath();
			            ctx.arc(star.x, star.y, star.size, 0, Math.PI * 2);
			            ctx.fillStyle = `rgba(255, 255, 255, ${opacity})`;
			            ctx.fill();
			        });
			        
			        time += 1;
			    };
			    
			    animate(0);
			    return this.liveWallpaperAnimationId;
			}
			
			// 气泡动态壁纸
			startBubblesWallpaper(ctx, width, height) {
			    const bubbles = [];
			    const bubbleCount = this.getBubbleCount(width, height);
			    
			    for (let i = 0; i < bubbleCount; i++) {
			        bubbles.push({
			            x: Math.random() * width,
			            y: height + Math.random() * 200,
			            size: Math.random() * 12 + 3,
			            speed: Math.random() * 0.8 + 0.3,
			            wobble: Math.random() * Math.PI * 2,
			            wobbleSpeed: Math.random() * 0.03 + 0.01
			        });
			    }
			    
			    const TARGET_FPS = 30;
			    const FRAME_INTERVAL = 1000 / TARGET_FPS;
			    let lastFrameTime = 0;
			    
			    const animate = (currentTime) => {
			        this.liveWallpaperAnimationId = requestAnimationFrame(animate);
			        
			        const delta = currentTime - lastFrameTime;
			        if (delta < FRAME_INTERVAL) return;
			        lastFrameTime = currentTime - (delta % FRAME_INTERVAL);
			        
			        ctx.fillStyle = 'rgba(33, 147, 176, 0.12)';
			        ctx.fillRect(0, 0, width, height);
			        
			        bubbles.forEach(bubble => {
			            bubble.y -= bubble.speed;
			            bubble.wobble += bubble.wobbleSpeed;
			            const x = bubble.x + Math.sin(bubble.wobble) * 15;
			            
			            if (bubble.y < -bubble.size) {
			                bubble.y = height + bubble.size;
			                bubble.x = Math.random() * width;
			            }
			            
			            ctx.beginPath();
			            ctx.arc(x, bubble.y, bubble.size, 0, Math.PI * 2);
			            ctx.fillStyle = 'rgba(109, 213, 237, 0.25)';
			            ctx.fill();
			            ctx.strokeStyle = 'rgba(109, 213, 237, 0.4)';
			            ctx.lineWidth = 0.8;
			            ctx.stroke();
			        });
			    };
			    
			    animate(0);
			    return this.liveWallpaperAnimationId;
			}
			
			// 代码雨动态壁纸
			startMatrixWallpaper(ctx, width, height) {
			    const fontSize = 14;
			    const columns = Math.floor(width / fontSize);
			    const drops = [];
			    const chars = '01アイウエオカキクケコサシスセソタチツテトナニヌネノハヒフヘホマミムメモヤユヨラリルレロワヲン';
			    const isLowEnd = this.isLowEndDevice();
			    const updateInterval = isLowEnd ? 2 : 1;
			    let frameCount = 0;
			    
			    for (let i = 0; i < columns; i++) {
			        drops[i] = Math.random() * -100;
			    }
			    
			    const TARGET_FPS = 30;
			    const FRAME_INTERVAL = 1000 / TARGET_FPS;
			    let lastFrameTime = 0;
			    
			    const animate = (currentTime) => {
			        this.liveWallpaperAnimationId = requestAnimationFrame(animate);
			        
			        const delta = currentTime - lastFrameTime;
			        if (delta < FRAME_INTERVAL) return;
			        lastFrameTime = currentTime - (delta % FRAME_INTERVAL);
			        
			        frameCount++;
			        if (frameCount % updateInterval !== 0) return;
			        
			        ctx.fillStyle = 'rgba(0, 0, 0, 0.06)';
			        ctx.fillRect(0, 0, width, height);
			        
			        ctx.font = fontSize + 'px monospace';
			        
			        for (let i = 0; i < drops.length; i++) {
			            const char = chars[Math.floor(Math.random() * chars.length)];
			            const x = i * fontSize;
			            const y = drops[i] * fontSize;
			            
			            ctx.fillStyle = `rgba(0, 255, 70, ${Math.random() * 0.5 + 0.5})`;
			            ctx.fillText(char, x, y);
			            
			            if (y > height && Math.random() > 0.98) {
			                drops[i] = 0;
			            }
			            drops[i]++;
			        }
			    };
			    
			    animate(0);
			    return this.liveWallpaperAnimationId;
			}
			
			// 应用气泡设置
			applyBubbleSettings() {
			    const bubbleSettings = this.chatSettings.bubble;
			    
			    // 设置CSS变量
			    document.documentElement.style.setProperty(
			        '--XEmsg-color-bubble-right', 
			        bubbleSettings.agentColor
			    );
			    
			    document.documentElement.style.setProperty(
			        '--XEmsg-color-bubble-left', 
			        bubbleSettings.customerColor
			    );

			    // 根据主题设置文字颜色和时间颜色
			    const isDark = bubbleSettings.theme === 'dark';
			    const textColor = isDark ? '#ffffff' : '#1a1a1a';
			    const timeColor = isDark ? 'rgba(255,255,255,0.55)' : 'rgba(0,0,0,0.35)';
			    document.documentElement.style.setProperty(
			        '--XEmsg-color-text-bubble-right',
			        textColor
			    );
			    document.documentElement.style.setProperty(
			        '--XEmsg-color-text-bubble-left',
			        textColor
			    );
			    document.documentElement.style.setProperty(
			        '--XEmsg-color-time-bubble',
			        timeColor
			    );
			    
			    // 更新现有消息气泡的颜色
			    this.updateExistingBubbles();
			}
			
			// 更新现有消息气泡的颜色
			updateExistingBubbles() {
			    const agentBubbles = document.querySelectorAll('.XEmsg-message__bubble--outgoing');
			    const customerBubbles = document.querySelectorAll('.XEmsg-message__bubble--incoming');
			    
			    const bubbleSettings = this.chatSettings.bubble;
			    const isDark = bubbleSettings.theme === 'dark';
			    const textColor = isDark ? '#ffffff' : '#1a1a1a';
			    const timeColor = isDark ? 'rgba(255,255,255,0.55)' : 'rgba(0,0,0,0.35)';

			    agentBubbles.forEach(bubble => {
			        bubble.style.backgroundColor = bubbleSettings.agentColor;
			        bubble.style.color = textColor;
			        const time = bubble.querySelector('.XEmsg-message__time');
			        if (time) time.style.color = timeColor;
			    });
			    
			    customerBubbles.forEach(bubble => {
			        bubble.style.backgroundColor = bubbleSettings.customerColor;
			        bubble.style.color = textColor;
			        const time = bubble.querySelector('.XEmsg-message__time');
			        if (time) time.style.color = timeColor;
			    });
			}
			
			
			setupSettingsListener() {
			    window.addEventListener('storage', (e) => {
			        if (e.key === 'chat_background_settings' || e.key === 'chat_bubble_settings') {
			            this.chatSettings = this.loadChatSettings();
			            this.applyChatSettings();
			            this.showToast('设置已更新');
			        }
			        if (e.key === 'chat_live_wallpaper_settings') {
			            this.loadLiveWallpaperSettings();
			            this.applyLiveWallpaper();
			            this.showToast('动态壁纸设置已更新');
			        }
			    });
			    
			    // 监听动态壁纸自定义事件
			    window.addEventListener('chatLiveWallpaperChanged', (e) => {
			        this.loadLiveWallpaperSettings();
			        this.applyLiveWallpaper();
			    });
			}
			
			// 在ChatPage类中添加
			loadPlatformNameSettings() {
			    const defaultSettings = { enabled: true };
			    try {
			        const saved = localStorage.getItem('chat_platform_name_settings');
			        return saved ? JSON.parse(saved) : defaultSettings;
			    } catch (error) {
			        console.error('加载平台名称设置失败:', error);
			        return defaultSettings;
			    }
			}
			
			// 更新updatePlatformNameDisplay方法
			updatePlatformNameDisplay() {
			    const platformNameElement = document.getElementById('platform-name-display');
			    if (!platformNameElement) {
			        console.error('找不到平台名称显示元素');
			        return;
			    }
			    
			    const settings = this.loadPlatformNameSettings();
			    
			    if (settings.enabled) {
			        platformNameElement.style.display = 'inline';
			    } else {
			        platformNameElement.style.display = 'none';
			    }
			}
			
			// 在ChatPage类中添加
			setupPlatformNameListener() {
			    // 监听storage事件，当设置改变时更新显示
			    window.addEventListener('storage', (e) => {
			        if (e.key === 'chat_platform_name_settings') {
			            this.updatePlatformNameDisplay();
			        }
			    });
			    
			    // 监听来自设置页面的消息
			    window.addEventListener('message', (e) => {
			        if (e.data && e.data.type === 'chat_settings_updated') {
			            this.updatePlatformNameDisplay();
			        }
			    });
			}
			
			setupSettingsSync() {
			    // 监听storage事件（同域名下不同标签页/窗口的通信）
			    window.addEventListener('storage', (e) => {
			        if (e.key === 'chat_platform_name_settings') {
			            this.updatePlatformNameDisplay();
			            this.showToast('平台名称显示设置已更新');
			        }
			    });
			    
			    // 监听来自设置页面的消息（iframe或窗口通信）
			    window.addEventListener('message', (e) => {
			        // 验证消息来源，确保安全
			        if (e.data && e.data.type === 'CHAT_SETTINGS_UPDATED') {
			            if (e.data.platformName !== undefined) {
			                this.updatePlatformNameDisplay();
			                this.showToast('平台名称显示设置已更新');
			            }
			        }
			    });
			}
			
			// 在ChatPage类中添加
			updateCustomerDeviceInfo() {
			    const self = this;
			    
			    $.ajax({
			        url: this.API_BASE + '?action=get_user_device_info',
			        method: 'POST',
			        contentType: 'application/json',
			        data: JSON.stringify({
			            username: this.currentCustomer,
			            user_type: 'customer'
			        }),
			        success: function(data) {
			            if (data.success && data.device_info) {
			                const info = data.device_info;
			                
			                // 更新模态框中的设备信息
			                self.updateModalDeviceInfo(info);
			                
			                // 可以在状态旁边显示小图标
			                self.updateStatusWithDevice(info);
			            }
			        },
			        error: function(error) {
			            console.error('获取设备信息失败:', error);
			        }
			    });
			}
			
			// 更新模态框中的设备信息显示
			updateModalDeviceInfo(deviceInfo) {
			    // 如果有设备信息模态框，更新它
			    const modal = $('#deviceInfoModal');
			    if (modal.length) {
			        let deviceIcon = '🖥️';
			        if (deviceInfo.device_type === 'mobile') deviceIcon = '📱';
			        else if (deviceInfo.device_type === 'tablet') deviceIcon = '📱';
			        
			        let browserIcon = '🌐';
			        if (deviceInfo.browser === 'Chrome') browserIcon = '🚀';
			        else if (deviceInfo.browser === 'Safari') browserIcon = '🧭';
			        else if (deviceInfo.browser === 'Firefox') browserIcon = '🦊';
			        
			        modal.find('#device-type').text(deviceIcon + ' ' + (deviceInfo.device_type || '未知'));
			        modal.find('#device-browser').text(browserIcon + ' ' + (deviceInfo.browser || '未知'));
			        modal.find('#device-os').text('💻 ' + (deviceInfo.os || '未知'));
			        modal.find('#device-last-seen').text('🕐 ' + (deviceInfo.last_seen || '未知'));
			    }
			}
			
			// 在状态旁边显示设备信息
			updateStatusWithDevice(deviceInfo) {
			    const statusElement = $('.XEmsg-header__status');
			    if (statusElement.length) {
			        let deviceText = '';
			        if (deviceInfo.device_type === 'mobile') deviceText = '📱';
			        else if (deviceInfo.device_type === 'tablet') deviceText = '📱';
			        else deviceText = '💻';
			        
			        // 在状态文本后添加设备图标
			        const currentText = statusElement.text();
			        if (!currentText.includes('📱') && !currentText.includes('💻')) {
			            statusElement.text(currentText + ' ' + deviceText);
			        }
			    }
			}
			
			
			// 添加通知设置相关方法
			loadNotificationSettings() {
			    const defaultSettings = { 
			        enabled: false,
			        requested: false,
			        granted: false,
			        deviceType: this.detectDeviceType()
			    };
			    
			    try {
			        const saved = localStorage.getItem('chat_notification_settings');
			        return saved ? JSON.parse(saved) : defaultSettings;
			    } catch (error) {
			        console.error('加载通知设置失败:', error);
			        return defaultSettings;
			    }
			}
			
			// 检测设备类型
			detectDeviceType() {
			    const userAgent = navigator.userAgent.toLowerCase();
			    const isIOS = /iphone|ipad|ipod/.test(userAgent);
			    const isStandalone = window.navigator.standalone || window.matchMedia('(display-mode: standalone)').matches;
			    
			    return {
			        isIOS: isIOS,
			        isStandalone: isStandalone,
			        isPWA: isStandalone && isIOS,
			        userAgent: userAgent
			    };
			}
			
			// 设置通知功能
			setupNotificationFeature() {
			    // 监听通知设置变化
			    window.addEventListener('storage', (e) => {
			        if (e.key === 'chat_notification_settings') {
			            this.notificationSettings = this.loadNotificationSettings();
			        }
			    });
			    
			    // 在收到新消息时检查是否显示通知
			    this.setupMessageNotification();
			}
			
			// 设置消息通知
			setupMessageNotification() {
			    // 在轮询消息时检查是否显示通知
			    const originalPollMessages = this.pollMessages;
			    this.pollMessages = async function() {
			        try {
			            const response = await originalPollMessages.call(this);
			            
			            // 如果有新消息且通知开启
			            if (response && response.messages && response.messages.length > 0) {
			                this.checkAndShowNotifications(response.messages);
			            }
			            
			            return response;
			        } catch (error) {
			            console.error('轮询消息失败:', error);
			            throw error;
			        }
			    };
			}
			
			// 检查并显示通知
			checkAndShowNotifications(newMessages) {
			    if (!this.notificationSettings.enabled) return;
			    
			    // 过滤出客户消息
			    const customerMessages = newMessages.filter(msg => 
			        msg.speaker_type !== 2 && msg.speaker_type !== 3
			    );
			    
			    if (customerMessages.length === 0) return;
			    
			    // 对每个客户消息显示通知
			    customerMessages.forEach(message => {
			        this.showNotification(message);
			    });
			}
			
			// 显示通知
			showNotification(message) {
			    const deviceInfo = this.notificationSettings.deviceType;
			    
			    // iOS PWA通知
			    if (deviceInfo.isPWA && this.notificationSettings.granted) {
			        this.showiOSPWANotification(message);
			    }
			    // 标准浏览器通知
			    else if ('Notification' in window && Notification.permission === 'granted') {
			        this.showBrowserNotification(message);
			    }
			}
			
			// 显示iOS PWA通知
			showiOSPWANotification(message) {
			    const title = this.currentCustomer || '新消息';
			    const body = message.content.length > 50 
			        ? message.content.substring(0, 50) + '...' 
			        : message.content;
			    
			    // 在iOS PWA中，我们可以使用HTML5通知
			    if ('Notification' in window) {
			        new Notification(title, {
			            body: body,
			            icon: '/assets/img/icon-192.png',
			            tag: 'chat-notification'
			        });
			    }
			}
			
			// 显示浏览器通知
			showBrowserNotification(message) {
			    const title = this.currentCustomer || '新消息';
			    const body = message.content.length > 50 
			        ? message.content.substring(0, 50) + '...' 
			        : message.content;
			    
			    new Notification(title, {
			        body: body,
			        icon: '/assets/img/icon-192.png',
			        tag: 'chat-notification'
			    });
			}
			
			loadAIFunctionSetting() {
			    const defaultSetting = { enabled: true };
			    try {
			        const saved = localStorage.getItem('chat_ai_function_enabled');
			        if (saved) {
			            const parsed = JSON.parse(saved);
			            return parsed.enabled !== undefined ? parsed.enabled : defaultSetting.enabled;
			        }
			    } catch (error) {
			        console.error('加载AI功能设置失败:', error);
			    }
			    return defaultSetting.enabled;
			}
			
			// 更新AI按钮和假人按钮显示状态
			updateAIBtnVisibility() {
			    const aiBtn = document.getElementById('ai-reply-btn');
			    const dummyBtn = document.getElementById('dummy-toggle-btn');
			    if (!aiBtn) {
			        console.warn('未找到AI按钮元素');
			    }
			    if (!dummyBtn) {
			        console.warn('未找到假人按钮元素');
			    }
			    
			    this.aiFunctionEnabled = this.loadAIFunctionSetting();
			    
			    if (this.aiFunctionEnabled) {
			        // 显示AI按钮
			        if (aiBtn) {
			            aiBtn.style.display = 'flex';
			            aiBtn.style.opacity = '1';
			            aiBtn.style.pointerEvents = 'auto';
			            aiBtn.style.visibility = 'visible';
			            // 确保事件监听器正常工作
			            this.setupAIFunctionality();
			        }
			        // 显示假人按钮
			        if (dummyBtn) {
			            dummyBtn.style.display = 'flex';
			            dummyBtn.style.opacity = '1';
			            dummyBtn.style.pointerEvents = 'auto';
			            dummyBtn.style.visibility = 'visible';
			        }
			    } else {
			        // 隐藏AI按钮
			        if (aiBtn) {
			            aiBtn.style.display = 'none';
			            aiBtn.style.opacity = '0';
			            aiBtn.style.pointerEvents = 'none';
			            aiBtn.style.visibility = 'hidden';
			        }
			        // 隐藏假人按钮
			        if (dummyBtn) {
			            dummyBtn.style.display = 'none';
			            dummyBtn.style.opacity = '0';
			            dummyBtn.style.pointerEvents = 'none';
			            dummyBtn.style.visibility = 'hidden';
			        }
			    }
			}
			
			// 设置AI功能监听器
			setupAIFunctionListener() {
			    // 监听storage事件
			    window.addEventListener('storage', (e) => {
			        if (e.key === 'chat_ai_function_enabled') {
			            this.updateAIBtnVisibility();
			            this.showToast('AI功能设置已更新');
			        }
			    });
			    
			    // 监听自定义事件
			    window.addEventListener('chatAIFunctionChanged', (e) => {
			        if (e.detail && typeof e.detail.enabled !== 'undefined') {
			            this.aiFunctionEnabled = e.detail.enabled;
			            this.updateAIBtnVisibility();
			            this.showToast(`AI功能已${e.detail.enabled ? '开启' : '关闭'}`);
			        }
			    });
			    
			    // 监听来自设置页面的消息
			    window.addEventListener('message', (e) => {
			        if (e.data && e.data.type === 'AI_FUNCTION_UPDATED') {
			            this.aiFunctionEnabled = e.data.enabled;
			            this.updateAIBtnVisibility();
			        }
			    });
			}
			
			    // 新增：更新备注显示
			    updateNoteDisplay() {
			        const noteElement = $('#customer-note-display');
			        const currentNote = this.customerNotes[this.currentCustomer] || '';
			        
			        if (currentNote) {
			            noteElement.text(`（${currentNote}）`);
			        } else {
			            noteElement.text('');
			        }
			    }
			    
			    // 异步加载消息历史
			    async loadMessageHistoryAsync() {
			        try {
			            // 检查缓存是否有效
			            if (this.isCacheValid(this.currentSessionKey)) {
			                const cachedMessages = this.getCachedMessages(this.currentSessionKey);
			                if (cachedMessages && cachedMessages.length > 0) {
			                    // 使用缓存消息
			                    this.renderMessagesFromCache(cachedMessages);
			                    
			                    // 消息加载完成后，重新检查客户状态
			                    setTimeout(() => {
			                        this.checkCustomerStatus();
			                    }, 500);
			                    return;
			                }
			            }
			            
			            const response = await this.optimizedRequest('/api/chat/messages?action=get_message_history', {
			                method: 'GET',
			                data: {
			                    session_key: this.currentSessionKey,
			                    agent_account: this.currentAgent
			                },
			                timeout: 5000,
			                cacheable: true
			            });
			            
			            if (response.success && response.messages) {
			                // 缓存消息
			                this.cacheMessages(this.currentSessionKey, response.messages);
			                
			                // 批量处理消息，减少DOM操作
			                const messagesHtml = [];
			                let maxMessageId = this.lastMessageId;
			                
			                // 批量构建消息HTML
			                response.messages.forEach(message => {
			                    // 确保图片消息有正确的URL
			                    if (message.message_type === 'image') {
			                        if (!message.image_url && message.image_path) {
			                            message.image_url = '/uploads/' + message.image_path;
			                        }
			                    }
			                    
			                    const isAgent = message.speaker_type === 2;
			                    const isDummy = message.speaker_type === 3;
			                    const time = this.formatTime(message.created_at, true);
			                    
			                    let messageClass, bubbleClass, name;
			                    
			                    if (isDummy) {
			                        messageClass = 'XEmsg-message XEmsg-message--dummy';
			                        bubbleClass = 'XEmsg-message__bubble XEmsg-message__bubble--dummy';
			                        name = message.dummy_name || '群聊模式';
			                    } else if (isAgent) {
			                        messageClass = 'XEmsg-message XEmsg-message--outgoing';
			                        bubbleClass = 'XEmsg-message__bubble XEmsg-message__bubble--outgoing';
			                        name = '客服';
			                    } else {
                messageClass = 'XEmsg-message XEmsg-message--incoming';
                bubbleClass = 'XEmsg-message__bubble XEmsg-message__bubble--incoming';
                name = this.currentCustomer;
            }
            
            // 检查是否为卡片消息
            const cardInfo = this.isCardMessage(message.content);
            if (cardInfo) {
                const cardData = this.getCardContent(message.content, cardInfo);
                const cardHtml = this.generateCardHtml(cardData, time);
                
                const cardMessageHtml = `
                    <div class="XEmsg-message-container" data-message-id="${message.id || 'temp_' + Date.now()}">
                        <div class="message-checkbox-wrapper" style="position: absolute; left: -35px; top: 50%; transform: translateY(-50%); display: none; z-index: 10;">
                            <input type="checkbox" class="message-checkbox" data-message-id="${message.id || 'temp_' + Date.now()}" style="width: 20px; height: 20px; border: 2px solid #c8c8c8; border-radius: 50%; -webkit-appearance: none; appearance: none; cursor: pointer; position: relative; background: #fff;">
                        </div>
                        <div class="${messageClass}">
                            ${cardHtml}
                        </div>
                    </div>
                `;
                
                messagesHtml.push(cardMessageHtml);
                
                if (message.id > maxMessageId) {
                    maxMessageId = message.id;
                }
                return; // 跳过后续处理
            }
            
            let messageContent;
			                    if (message.message_type === 'image') {
			            const imageUrl = message.image_url || (message.image_path ? `/uploads/${message.image_path}` : null);
			            if (imageUrl) {
			                messageContent = `
			                    <div class="message-image-container">
			                        <div class="image-loading" style="display: flex; align-items: center; justify-content: center; width: 100px; height: 100px; border-radius: 8px; background: #f5f5f5;">
			                            <div class="loading-spinner" style="width: 20px; height: 20px; border: 2px solid #e0e0e0; border-top: 2px solid #007AFF; border-radius: 50%; animation: spin 1s linear infinite;"></div>
			                        </div>
			                        <img src="${imageUrl}" alt="聊天图片" class="message-image" 
			                             loading="lazy"
			                             style="display: none; max-width: 200px; max-height: 100px; border-radius: 8px; transition: opacity 0.3s ease;">
			                        <div class="image-error" style="display: none; color: #999; font-style: italic; padding: 10px; text-align: center;">
			                            图片加载失败，<a href="${imageUrl}" target="_blank" style="color: #007AFF; text-decoration: none;">点击查看</a>
			                        </div>
			                    </div>
			                `;
			            } else {
			                messageContent = '[图片]';
			            }
			        } else {
			                        messageContent = this.escapeHtml(message.content);
			                    }
			                    
			                    const isImgMsg = message.message_type === 'image';
			                    let messageHtml;
			                    if (isImgMsg) {
			                        messageHtml = `
			                            <div class="XEmsg-message-container" data-message-id="${message.id || 'temp_' + Date.now()}">
			                                <div class="${messageClass}">
			                                    ${messageContent}
			                                </div>
			                            </div>
			                        `;
			                    } else {
			                        messageHtml = `
			                            <div class="XEmsg-message-container" data-message-id="${message.id || 'temp_' + Date.now()}">
			                                <div class="${messageClass}">
			                                    <div class="${bubbleClass}">
			                                        <div class="XEmsg-message__sender">${name}</div>
			                                        <span class="XEmsg-message__text">${messageContent}</span>
			                                        <span class="XEmsg-message__time">
			                                            ${time}
			                                            ${isAgent && message.id ? `<span class="message-status" data-message-id="${message.id}"><i class="bi bi-check"></i></span>` : ''}
			                                        </span>
			                                    </div>
			                                </div>
			                            </div>
			                        `;
			                    }
			                    
			                    messagesHtml.push(messageHtml);
			                    
			                    // 更新最大消息ID
			                    if (message.id > maxMessageId) {
			                        maxMessageId = message.id;
			                    }
			                });
			                
			                // 一次性添加所有消息
			                if (messagesHtml.length > 0) {
			                    const container = $('#messages-container');
			                    // 使用DocumentFragment减少重排
			                    const fragment = document.createDocumentFragment();
			                    const tempDiv = document.createElement('div');
			                    tempDiv.innerHTML = messagesHtml.join('');
			                    while (tempDiv.firstChild) {
			                        fragment.appendChild(tempDiv.firstChild);
			                    }
			                    container.append(fragment);
			                    
			                    // 更新最后消息ID
			                    this.lastMessageId = maxMessageId;
			                    
			                    // 延迟滚动，确保DOM已经渲染完成
			                    setTimeout(() => {
			                        container.scrollTop(container[0].scrollHeight);
			                    }, 50);
			                }
			                
			                // 消息加载完成后，重新检查客户状态
			                setTimeout(() => {
			                    this.checkCustomerStatus();
			                }, 500);
			            }
			        } catch (error) {
			            console.error('异步加载消息历史失败:', error);
			        }
			    }
			    
			    // 从缓存渲染消息
			    renderMessagesFromCache(messages) {
			        if (!messages || messages.length === 0) return;
			        
			        const messagesHtml = [];
			        let maxMessageId = this.lastMessageId;
			        
			        messages.forEach(message => {
			            // 确保图片消息有正确的URL
			            if (message.message_type === 'image') {
			                if (!message.image_url && message.image_path) {
			                    message.image_url = '/uploads/' + message.image_path;
			                }
			            }
			            
			            // 直接构建HTML
			            const isAgent = message.speaker_type === 2;
			            const isDummy = message.speaker_type === 3;
			            const time = this.formatTime(message.created_at, true);
			            
			            let messageClass, bubbleClass, name;
			            
			            if (isDummy) {
			                messageClass = 'XEmsg-message XEmsg-message--dummy';
			                bubbleClass = 'XEmsg-message__bubble XEmsg-message__bubble--dummy';
			                name = message.dummy_name || '群聊模式';
			            } else if (isAgent) {
			                messageClass = 'XEmsg-message XEmsg-message--outgoing';
			                bubbleClass = 'XEmsg-message__bubble XEmsg-message__bubble--outgoing';
			                name = '客服';
			            } else {
			                messageClass = 'XEmsg-message XEmsg-message--incoming';
			                bubbleClass = 'XEmsg-message__bubble XEmsg-message__bubble--incoming';
			                name = this.currentCustomer;
			            }
			            
			            let messageContent;
			            if (message.message_type === 'image') {
			                const imageUrl = message.image_url || (message.image_path ? `/uploads/${message.image_path}` : null);
			                if (imageUrl) {
			                    messageContent = `
			                        <div class="message-image-container">
			                            <div class="image-loading" style="display: flex; align-items: center; justify-content: center; width: 100px; height: 100px; border-radius: 8px; background: #f5f5f5;">
			                                <div class="loading-spinner" style="width: 20px; height: 20px; border: 2px solid #e0e0e0; border-top: 2px solid #007AFF; border-radius: 50%; animation: spin 1s linear infinite;"></div>
			                            </div>
			                            <img src="${imageUrl}" alt="聊天图片" class="message-image" 
			                                 style="display: none; max-width: 200px; max-height: 100px; border-radius: 8px; transition: opacity 0.3s ease;">
			                            <div class="image-error" style="display: none; color: #999; font-style: italic; padding: 10px; text-align: center;">
			                                图片加载失败，<a href="${imageUrl}" target="_blank" style="color: #007AFF; text-decoration: none;">点击查看</a>
			                            </div>
			                        </div>
			                    `;
			                } else {
			                    messageContent = '[图片]';
			                }
			            } else {
			                // 检查是否为卡片消息
			                const cardInfo = this.isCardMessage(message.content);
			                if (cardInfo) {
			                    const cardData = this.getCardContent(message.content, cardInfo);
			                    messageContent = this.generateCardHtml(cardData, time);
			                } else {
			                    messageContent = this.escapeHtml(message.content);
			                }
			            }
			            
			            const msgId = message.id || ('temp_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9));
			            const isCustomerMessage = !isDummy && !isAgent;
			            const containerStyle = isCustomerMessage 
			                ? 'position: relative; min-height: 36px;' 
			                : 'position: relative; min-height: 36px;';
			            
			            // 卡片消息不使用bubble包裹
			            const isCard = this.isCardMessage(message.content);
			            const isImgMsg = message.message_type === 'image';
			            let messageHtml;
			            if (isCard || isImgMsg) {
			                messageHtml = `
			                    <div class="XEmsg-message-container" data-message-id="${msgId}" style="${containerStyle}">
			                        <div class="message-checkbox-wrapper" style="position: absolute; left: -35px; top: 50%; transform: translateY(-50%); display: none; z-index: 10;">
			                            <input type="checkbox" class="message-checkbox" data-message-id="${msgId}" style="width: 20px; height: 20px; border: 2px solid #c8c8c8; border-radius: 50%; -webkit-appearance: none; appearance: none; cursor: pointer; position: relative; background: #fff;">
			                        </div>
			                        <div class="${messageClass}" style="margin-left: 0;">
			                            ${messageContent}
			                        </div>
			                    </div>
			                `;
			            } else {
			                messageHtml = `
			                    <div class="XEmsg-message-container" data-message-id="${msgId}" style="${containerStyle}">
			                        <div class="message-checkbox-wrapper" style="position: absolute; left: -35px; top: 50%; transform: translateY(-50%); display: none; z-index: 10;">
			                            <input type="checkbox" class="message-checkbox" data-message-id="${msgId}" style="width: 20px; height: 20px; border: 2px solid #c8c8c8; border-radius: 50%; -webkit-appearance: none; appearance: none; cursor: pointer; position: relative; background: #fff;">
			                        </div>
			                        <div class="${messageClass}" style="margin-left: 0;">
			                            <div class="${bubbleClass}">
			                                ${isDummy || isAgent ? `<div class="XEmsg-message__sender">${name}</div>` : ''}
			                                <span class="XEmsg-message__text">${messageContent}</span>
			                                <span class="XEmsg-message__time">
			                                    ${time}
			                                    ${isAgent || isDummy ? `<span class="message-status ${message.is_read ? 'read' : ''}" data-message-id="${msgId}">${message.is_read ? '<i class="bi bi-check-all"></i>' : '<i class="bi bi-check"></i>'}</span>` : ''}
			                                </span>
			                            </div>
			                        </div>
			                    </div>
			                `;
			            }
			            
			            messagesHtml.push(messageHtml);
			            
			            // 更新最大消息ID
			            if (message.id > maxMessageId) {
			                maxMessageId = message.id;
			            }
			        });
			        
			        // 一次性添加所有消息
			        if (messagesHtml.length > 0) {
			            const container = $('#messages-container');
			            // 使用DocumentFragment减少重排
			            const fragment = document.createDocumentFragment();
			            const tempDiv = document.createElement('div');
			            tempDiv.innerHTML = messagesHtml.join('');
			            while (tempDiv.firstChild) {
			                fragment.appendChild(tempDiv.firstChild);
			            }
			            container.append(fragment);
			            
			            // 更新最后消息ID
			            this.lastMessageId = maxMessageId;
			            
			            // 延迟滚动，确保DOM已经渲染完成
			            setTimeout(() => {
			                container.scrollTop(container[0].scrollHeight);
			            }, 50);
			        }
			    }
						     // 1. 添加卡片检测方法
    isCardMessage(content) {
        if (!content || typeof content !== 'string') return false;
        
        console.log('isCardMessage - 完整内容:', content);
        console.log('isCardMessage - 内容长度:', content.length);
        
        // 检查内容的前20个字符的详细信息
        for (let i = 0; i < Math.min(20, content.length); i++) {
            const char = content[i];
            console.log(`字符 ${i}: '${char}' (ASCII: ${char.charCodeAt(0)})`);
        }
        
        // 检查是否以预期的前缀开头
        console.log('以 XEZZCARD# 开头:', content.startsWith('XEZZCARD#'));
        console.log('以 XEXYCARD# 开头:', content.startsWith('XEXYCARD#'));
        
        // 如果长度足够，显示前缀后的第一个字符
        if (content.length > 10) {
            const afterPrefix = content.substring(10);
            console.log('前缀后内容:', afterPrefix.substring(0, 20), '...');
            if (afterPrefix.length > 0) {
                console.log('前缀后第一个字符:', afterPrefix[0], '(ASCII:', afterPrefix.charCodeAt(0), ')');
            }
        }
        
        // 首先尝试直接解析（原始内容）
        // 检测闲鱼订单卡片格式 XEXYCARD#JSON
        if (content.startsWith('XEXYCARD#') && content.length > 10) {
            try {
                let xyJson = content.substring(10);
                console.log('尝试解析闲鱼卡片 JSON:', xyJson.substring(0, 100), '...');
                
                // 尝试清理可能的转义字符
                xyJson = this.cleanJson(xyJson);
                console.log('清理后的 JSON:', xyJson.substring(0, 100), '...');
                
                const xyData = JSON.parse(xyJson);
                console.log('检测到闲鱼订单卡片:', xyData);
                if (xyData.title || xyData.order_id) {
                    return { type: 'xeycard', data: xyData, prefix: 'XEXYCARD#' };
                }
            } catch (e) {
                console.error('解析闲鱼订单卡片数据失败:', e);
                console.error('原始JSON内容:', content.substring(10, 50), '...');
            }
        }
        
        // 检测转转订单卡片格式 XEZZCARD#JSON
        if (content.startsWith('XEZZCARD#') && content.length > 10) {
            try {
                let zzJson = content.substring(10);
                console.log('尝试解析转转卡片 JSON:', zzJson.substring(0, 100), '...');
                
                // 尝试清理可能的转义字符
                zzJson = this.cleanJson(zzJson);
                console.log('清理后的 JSON:', zzJson.substring(0, 100), '...');
                
                const zzData = JSON.parse(zzJson);
                console.log('检测到转转订单卡片:', zzData);
                if (zzData.title || zzData.order_id) {
                    return { type: 'zezcard', data: zzData, prefix: 'XEZZCARD#' };
                }
            } catch (e) {
                console.error('解析转转订单卡片数据失败:', e);
                console.error('原始JSON内容:', content.substring(10, 50), '...');
            }
        }
        
        // 检测盼之商品卡片格式 XEPZCARD#JSON
        if (content.startsWith('XEPZCARD#') && content.length > 10) {
            try {
                let pzJson = content.substring(10);
                pzJson = this.cleanJson(pzJson);
                const pzData = JSON.parse(pzJson);
                console.log('检测到盼之商品卡片:', pzData);
                if (pzData.title || pzData.order_id) {
                    return { type: 'pzcard', data: pzData, prefix: 'XEPZCARD#' };
                }
            } catch (e) {
                console.error('解析盼之商品卡片数据失败:', e);
            }
        }
        
        // 如果直接解析失败，尝试解码后再解析
        const decodedContent = this.decodeContent(content);
        console.log('isCardMessage - 解码后:', decodedContent);
        
        // 检测闲鱼订单卡片格式（解码后）
        if (decodedContent.startsWith('XEXYCARD#') && decodedContent.length > 10) {
            try {
                let xyJson = decodedContent.substring(10);
                console.log('尝试解析解码后的闲鱼卡片 JSON:', xyJson.substring(0, 100), '...');
                
                // 尝试清理可能的转义字符
                xyJson = this.cleanJson(xyJson);
                console.log('解码后清理的 JSON:', xyJson.substring(0, 100), '...');
                
                const xyData = JSON.parse(xyJson);
                console.log('检测到解码后的闲鱼订单卡片:', xyData);
                if (xyData.title || xyData.order_id) {
                    return { type: 'xeycard', data: xyData, prefix: 'XEXYCARD#' };
                }
            } catch (e) {
                console.error('解析解码后的闲鱼订单卡片数据失败:', e);
            }
        }
        
        // 检测转转订单卡片格式（解码后）
        if (decodedContent.startsWith('XEZZCARD#') && decodedContent.length > 10) {
            try {
                let zzJson = decodedContent.substring(10);
                console.log('尝试解析解码后的转转卡片 JSON:', zzJson.substring(0, 100), '...');
                
                // 尝试清理可能的转义字符
                zzJson = this.cleanJson(zzJson);
                console.log('解码后清理的 JSON:', zzJson.substring(0, 100), '...');
                
                const zzData = JSON.parse(zzJson);
                console.log('检测到解码后的转转订单卡片:', zzData);
                if (zzData.title || zzData.order_id) {
                    return { type: 'zezcard', data: zzData, prefix: 'XEZZCARD#' };
                }
            } catch (e) {
                console.error('解析解码后的转转订单卡片数据失败:', e);
            }
        }
        
        // 检测盼之商品卡片格式（解码后）
        if (decodedContent.startsWith('XEPZCARD#') && decodedContent.length > 10) {
            try {
                let pzJson = decodedContent.substring(10);
                pzJson = this.cleanJson(pzJson);
                const pzData = JSON.parse(pzJson);
                console.log('检测到解码后的盼之商品卡片:', pzData);
                if (pzData.title || pzData.order_id) {
                    return { type: 'pzcard', data: pzData, prefix: 'XEPZCARD#' };
                }
            } catch (e) {
                console.error('解析解码后的盼之商品卡片数据失败:', e);
            }
        }
        
        // 检测自定义卡片格式 XECARD#JSON
        if (decodedContent.startsWith('XECARD#') && decodedContent.length > 7) {
            try {
                const cardJson = decodedContent.substring(7);
                const cardData = JSON.parse(cardJson);
                if (cardData.type === 'custom_card' && cardData.title && cardData.content) {
                    return { type: 'custom', data: cardData, prefix: 'XECARD#' };
                }
            } catch (e) {
                console.error('解析卡片数据失败:', e);
            }
        }
        
        // 检测交易卡片格式 XEXXCARD#JSON
        if (decodedContent.startsWith('XEXXCARD#') && decodedContent.length > 9) {
            try {
                const tradeJson = decodedContent.substring(9);
                const tradeData = JSON.parse(tradeJson);
                console.log('检测到交易卡片:', tradeData);
                if (tradeData.type === 'trade_card' && tradeData.main_title) {
                    return { type: 'trade', data: tradeData, prefix: 'XEXXCARD#' };
                }
            } catch (e) {
                console.error('解析交易卡片数据失败:', e);
            }
        }
        
        // 检测付款卡片格式 XEPAYCARD#JSON
        if (decodedContent.startsWith('XEPAYCARD#') && decodedContent.length > 10) {
            try {
                const payJson = decodedContent.substring(10);
                const payData = JSON.parse(payJson);
                console.log('检测到付款卡片:', payData);
                if (payData.type === 'pay_card' && payData.amount) {
                    return { type: 'pay', data: payData, prefix: 'XEPAYCARD#' };
                }
            } catch (e) {
                console.error('解析付款卡片数据失败:', e);
            }
        }
        
        // 检测拉群卡片格式 XEINVITECARD#JSON
        if (decodedContent.startsWith('XEINVITECARD#') && decodedContent.length > 13) {
            try {
                const inviteJson = decodedContent.substring(13);
                const inviteData = JSON.parse(inviteJson);
                console.log('检测到拉群卡片:', inviteData);
                if (inviteData.type === 'invite_group_card') {
                    return { type: 'invite_group', data: inviteData, prefix: 'XEINVITECARD#' };
                }
            } catch (e) {
                console.error('解析拉群卡片数据失败:', e);
            }
        }
        
        return false;
    }
			    
			    // 内容解码 - 处理多种编码情况
    decodeContent(text) {
        if (!text) return text;
        
        let result = text;
        
        // 处理 HTML 实体编码
        const htmlEntities = {
            '&amp;': '&',
            '&quot;': '"',
            '&#34;': '"',
            '&#39;': "'",
            '&#35;': '#',
            '&#x23;': '#',
            '&lt;': '<',
            '&gt;': '>'
        };
        
        result = result.replace(/&[^;]+;/g, match => htmlEntities[match] || match);
        
        // 处理双重编码（如 &amp;quot; -> &quot; -> "）
        if (result.includes('&')) {
            result = result.replace(/&[^;]+;/g, match => htmlEntities[match] || match);
        }
        
        // 处理可能的 Unicode 编码
        result = result.replace(/\\u([0-9a-fA-F]{4})/g, (match, code) => {
            return String.fromCharCode(parseInt(code, 16));
        });
        
        // 处理转义字符
        result = result.replace(/\\n/g, '\n').replace(/\\r/g, '\r').replace(/\\t/g, '\t');
        
        return result;
    }
    
    // 清理 JSON 字符串中的转义问题
    cleanJson(text) {
        if (!text) return text;
        
        let result = text;
        
        // 移除首尾的空白字符
        result = result.trim();
        
        console.log('cleanJson - 原始:', result.substring(0, 50), '...');
        
        // 处理双重转义的引号（\\\" -> \"）
        result = result.replace(/\\\\\"/g, '\\"');
        
        // 处理多余的转义斜杠
        result = result.replace(/\\\\/g, '\\');
        
        console.log('cleanJson - 转义处理后:', result.substring(0, 50), '...');
        
        // 修复缺少大括号的情况
        // 如果以引号开头（属性名）且不以 { 开头，添加 {
        if (!result.startsWith('{') && !result.startsWith('[') && result.startsWith('"')) {
            result = '{' + result;
            console.log('cleanJson - 添加开头 {');
        }
        
        // 如果以逗号结尾且不以 } 或 ] 结尾，添加 }
        if (!result.endsWith('}') && !result.endsWith(']')) {
            // 移除末尾可能的逗号
            result = result.replace(/,\s*$/, '');
            // 添加结尾 }
            result = result + '}';
            console.log('cleanJson - 添加结尾 }');
        }
        
        console.log('cleanJson - 最终:', result.substring(0, 50), '...');
        
        return result;
    }
			    
			    getCardContent(originalContent, cardInfo) {
			        // 自定义卡片、交易卡片、付款卡片和拉群卡片都直接返回完整数据
			        if (cardInfo.type === 'custom' || cardInfo.type === 'trade' || cardInfo.type === 'pay' || cardInfo.type === 'invite_group') {
			            return cardInfo.data;
			        }
			        
			        // 闲鱼订单卡片
			        if (cardInfo.type === 'xeycard') {
			            const data = cardInfo.data;
			            data._cardType = 'xeycard';
			            return data;
			        }
			        
			        // 转转订单卡片
			        if (cardInfo.type === 'zezcard') {
			            const data = cardInfo.data;
			            data._cardType = 'zezcard';
			            return data;
			        }
			        
			        // 盼之商品卡片
			        if (cardInfo.type === 'pzcard') {
			            const data = cardInfo.data;
			            data._cardType = 'pzcard';
			            return data;
			        }
			        
			        // 其他类型卡片
			        const content = originalContent.substring(cardInfo.prefix.length).trim();
			        return {
			            title: cardInfo.title,
			            content: content
			        };
			    }
			    
			    generateCardHtml(cardData, time) {
			        console.log('generateCardHtml - cardData:', cardData);
			        console.log('generateCardHtml - cardData.type:', cardData.type);
			        
			        // 如果是拉群卡片，使用拉群卡片渲染
			        if (cardData.type === 'invite_group_card') {
			            console.log('调用renderInviteGroupCard');
			            return this.renderInviteGroupCard(cardData);
			        }
			        
			        // 如果是交易卡片，使用交易卡片渲染
			        if (cardData.type === 'trade_card') {
			            console.log('调用renderTradeCard');
			            return this.renderTradeCard(cardData);
			        }
			        
			        // 如果是付款卡片，使用付款卡片渲染
			        if (cardData.type === 'pay_card') {
			            console.log('调用renderPayCard');
			            return this.renderPayCard(cardData);
			        }
			        
			        // 如果是闲鱼订单卡片，使用闲鱼卡片渲染
		        if (cardData._cardType === 'xeycard' || (cardData.type === 'order_submit' && !cardData._cardType)) {
		            console.log('调用renderXEYCard');
		            return this.renderXEYCard(cardData);
		        }
		        
		        // 如果是转转订单卡片，使用转转卡片渲染
		        if (cardData._cardType === 'zezcard') {
		            console.log('调用renderZEZCard');
		            return this.renderZEZCard(cardData);
		        }
		        
		        // 如果是盼之商品卡片，使用盼之卡片渲染
		        if (cardData._cardType === 'pzcard') {
		            console.log('调用renderPZCard');
		            return this.renderPZCard(cardData);
		        }
			        
			        // 自定义卡片渲染
			        let html = `
			            <div class="XEmsg-card">
			                <div class="XEmsg-card__header">
			                    <span class="XEmsg-card__title">${this.escapeHtml(cardData.title)}</span>
			                </div>
			                <div class="XEmsg-card__content">
			                    ${this.escapeHtml(cardData.content)}
			                </div>
			        `;
			        
			        // 如果有按钮文字，添加按钮（不跳转）
			        if (cardData.buttonText) {
			            html += `
			                <div class="XEmsg-card__actions">
			                    <span class="XEmsg-card__button">
			                        ${this.escapeHtml(cardData.buttonText)}
			                    </span>
			                </div>
			            `;
			        }
			        
			        html += `
			            </div>
			        `;
			        
			        return html;
			    }
			    
			    // 渲染闲鱼订单卡片（XEXYCARD）
			    renderXEYCard(cardData) {
			        const title = cardData.title || '订单信息';
			        const amount = cardData.rmb || cardData.amount || '0.00';
			        const imgUrl = cardData.img || 'https://cy-pic.kuaizhan.com/g3/e4/5d/becb-557a-46c7-a2bd-daecb7fe695762?cysign=0e267a58cd517d077e5bb3ca2c28d09c&cyt=1768733746';
			        const status = cardData.status || '待发货';
			        const orderId = cardData.order_id || '';
			        
			        return `
			            <div class="XE-1" style="--avatarMainGap: 0.375rem;">
			                <div class="XE-4">
			                    <img src="${imgUrl}" alt="商品图片">
			                    <div class="XE-5">
			                        <div class="XE-6">
			                            <div class="XE-7">${this.escapeHtml(title)}</div>
			                            <div class="XE-8" style="font-weight: bold;"><span>￥</span>${amount}</div>
			                        </div>
			                        <div style="color: rgb(255, 96, 0); text-align: right;">${status}</div>
			                    </div>
			                </div>
			            </div>
			        `;
			    }
			    
			    // 渲染转转订单卡片（XEZZCARD）
			    renderZEZCard(cardData) {
			        const title = cardData.title || '未知订单';
			        const amount = cardData.rmb || cardData.amount || '0.00';
			        const imgUrl = cardData.img || 'https://cy-pic.kuaizhan.com/g3/e4/5d/becb-557a-46c7-a2bd-daecb7fe695762?cysign=0e267a58cd517d077e5bb3ca2c28d09c&cyt=1768733746';
			        const status = cardData.status || '待发货';
			        const orderId = cardData.order_id || '';
			        
			        return `
			            <div class="XE-1" style="--avatarMainGap: 0.375rem;">
			                <div class="XE-4">
			                    <img src="${imgUrl}" alt="商品图片">
			                    <div class="XE-5">
			                        <div class="XE-6">
			                            <div class="XE-7">${this.escapeHtml(title)}</div>
			                            <div class="XE-8" style="font-weight: bold;"><span>￥</span>${amount}</div>
			                        </div>
			                        <div style="color: rgb(255, 96, 0); text-align: right;">${status}</div>
			                    </div>
			                </div>
			            </div>
			        `;
			    }
			    
			    // 渲染盼之商品卡片（XEPZCARD）
			    renderPZCard(cardData) {
			        const title = cardData.title || '订单信息';
			        const amount = cardData.rmb || cardData.amount || '0.00';
			        const imgUrl = cardData.img || '';
			        const orderId = cardData.order_id || '';
			        const singles = cardData.singles || '';
			        
			        return `
			            <div class="message-goods">
			                <div class="goods-content">
			                    <div class="top"><span>商品编号：${this.escapeHtml(orderId)}</span></div>
			                    <div class="content-box">
			                        <div class="pz-image">
			                            <img alt="" src="${imgUrl}">
			                        </div>
			                        <div class="info">
			                            <div class="desc ellipsis-1">${this.escapeHtml(title)}</div>
			                            <div class="singles ellipsis-1">${this.escapeHtml(singles)}</div>
			                            <div class="price">￥${amount}</div>
			                        </div>
			                    </div>
			                    <div class="labels"></div>
			                </div>
			            </div>
			        `;
			    }
			    
			    // 渲染交易卡片HTML
			    // 渲染拉群卡片（仿微信邀请卡片样式）
			    renderInviteGroupCard(inviteData) {
			        const imageUrl = inviteData.product_image || '/assets/img/normal.png';
			        const productName = inviteData.product_code ? (inviteData.product_code + ' ' + (inviteData.product_name || '')) : (inviteData.product_name || '群聊');
			        const shareLink = inviteData.share_link || '#';
			        
			        return `
			            <a href="${this.escapeHtml(shareLink)}" target="_blank" class="wx-invite" style="display: block; text-decoration: none;">
			                <div class="wx-card" style="background: #FFFFFF; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.08); font-family: -apple-system, BlinkMacSystemFont, 'PingFang SC', 'Microsoft YaHei', sans-serif;">
			                    <div class="wx-content" style="padding: 15px 14px; display: flex; align-items: center;">
			                        <img class="wx-avatar" src="${this.escapeHtml(imageUrl)}" alt="图片" style="width: 60px; height: 60px; border-radius: 8px; object-fit: cover; margin-right: 12px;" onerror="this.src='/assets/img/normal.png'">
			                        <div class="wx-info" style="flex: 1;">
			                            <h3 class="wx-title" style="font-size: 15px; color: #121212; margin: 0 0 4px; font-weight: normal; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${this.escapeHtml(productName)}</h3>
			                            <p class="wx-desc" style="font-size: 13px; color: #888888; margin: 0;">邀请你加入群聊</p>
			                        </div>
			                    </div>
			                    <div class="wx-footer" style="background: #07C160; color: white; text-align: center; padding: 9px 0; font-size: 14px;">查看并加入群聊</div>
			                </div>
			            </a>
			        `;
			    }

			    renderTradeCard(tradeData) {
			        const imageHtml = tradeData.image_url 
			            ? `<img src="${tradeData.image_url}" class="tradecard-img" alt="商品图">` 
			            : `<div class="tradecard-img" style="background: #f3f4f6; display: flex; align-items: center; justify-content: center; font-size: 12px; color: #999;">无图片</div>`;
			        
			        // 价格添加¥符号
			        const priceText = tradeData.price ? `¥${tradeData.price}` : '';
			        
			        return `
			            <div class="tradecard">
			                <div class="tradecard-title">交易信息</div>
			                <div class="tradecard-top">
			                    <div class="tradecard-tags">
			                        <span class="tradecard-tag game">${tradeData.main_title || ''}</span>
			                        <span class="tradecard-tag type">${tradeData.subtitle || ''}</span>
			                    </div>
			                    <div class="tradecard-status">${tradeData.trade_status || '待处理'}</div>
			                </div>
			                <div class="tradecard-goods">
			                    ${imageHtml}
			                    <div class="tradecard-main">
			                        <div class="tradecard-name">${tradeData.description || ''}</div>
			                        <div class="tradecard-price">${priceText}</div>
			                        ${tradeData.note ? `<div class="tradecard-tip">${tradeData.note}</div>` : ''}
			                    </div>
			                </div>
			                <div class="tradecard-info">
			                    ${tradeData.order_no ? `<div class="tradecard-item"><span class="tradecard-label">订单编号：</span><span class="tradecard-value">${tradeData.order_no}</span></div>` : ''}
			                    ${tradeData.goods_no ? `<div class="tradecard-item"><span class="tradecard-label">商品编号：</span><span class="tradecard-value">${tradeData.goods_no}</span></div>` : ''}
			                    ${tradeData.create_time ? `<div class="tradecard-item"><span class="tradecard-label">创建时间：</span><span class="tradecard-value">${tradeData.create_time}</span></div>` : ''}
			                    ${tradeData.contract_status ? `<div class="tradecard-item"><span class="tradecard-label">合同状态：</span><span class="tradecard-value">${tradeData.contract_status}</span></div>` : ''}
			                </div>
			            </div>
			        `;
			    }
			    
			    setupDummyFunctionality() {
			        const self = this;
			        
			        // 假人按钮点击事件
			        $('#dummy-mode-btn').on('click', function(e) {
			            e.stopPropagation();
			            self.toggleDummyPanel();
			        });
			        
			        // 假人模式快速切换按钮
			        $('#dummy-toggle-btn').on('click', function(e) {
			            e.stopPropagation();
			            self.toggleDummyMode();
			            self.updateDummyToggleButton();
			        });
			        
			        // 保存假人设置
			        $('#save-dummy-settings').on('click', function() {
			            self.saveDummySettings();
			        });
			        
			        // 关闭假人面板按钮
			        $('#close-dummy-panel').on('click', function(e) {
			            e.stopPropagation();
			            self.closeDummyPanel();
			        });
			        
			        // 遮罩点击关闭面板
			        $('#dummy-panel-overlay').on('click', function(e) {
			            e.stopPropagation();
			            self.closeDummyPanel();
			        });
			        
			        // 自定义头像上传
			        $('#custom-avatar-upload').on('change', function(e) {
			            e.stopPropagation();
			            self.uploadCustomAvatar(this);
			        });
			        
			        // 自定义头像选项点击
			        $('input[name="avatar-type"][value="custom"]').on('click', function(e) {
			            e.stopPropagation();
			            const customAvatarUrl = $('#custom-avatar-preview').attr('src');
			            if (customAvatarUrl && customAvatarUrl !== '/assets/img/jiaren.png') {
			                self.updateAvatarPreview(customAvatarUrl);
			            }
			        });
			        
			        // 修改setupDummyFunctionality中的默认头像点击事件
			        $('.default-avatars .avatar-option').on('click', function(e) {
						e.stopPropagation();
						
						console.log('点击了默认头像选项');
						
						// 取消所有头像选项的选中状态
						$('.default-avatars .avatar-option input').prop('checked', false);
						$('input[name="avatar-type"][value="random"]').prop('checked', false);
						
						// 设置当前为选中
						$(this).find('input').prop('checked', true);
						
						// 选中默认类型
						$('input[name="avatar-type"][value="default"]').prop('checked', true);
						
						// 获取头像URL
						const avatarUrl = $(this).find('img').attr('src');
						console.log('选择了头像:', avatarUrl);
						
						// 更新预览
						self.updateAvatarPreview(avatarUrl);
						self.showToast('已选择预设头像');
						});
						
						 // 修改随机头像选项点击事件
						$('.random-avatar-section .avatar-option').on('click', function(e) {
						e.stopPropagation();
						
						console.log('点击了随机头像选项');
						
						// 取消默认头像的选中
						$('.default-avatars .avatar-option input').prop('checked', false);
						
						// 选择随机类型
						$('input[name="avatar-type"][value="random"]').prop('checked', true);
						
						// 更新预览
						const avatarUrl = $('#random-avatar-preview').attr('src');
						self.updateAvatarPreview(avatarUrl);
						});
						
						// 更换随机头像按钮
						$('#random-avatar-btn').on('click', function(e) {
						    e.stopPropagation();
						    
						    const randomAvatar = self.getRandomAvatar();
						    
						    // 更新预览图
						    $('#random-avatar-preview').attr('src', randomAvatar);
						    
						    // 选中随机类型
						    $('input[name="avatar-type"][value="random"]').prop('checked', true);
						    
						    // 更新预览
						    self.updateAvatarPreview(randomAvatar);
						    
						    self.showToast('已更换随机头像');
						});
						
						// 显示随机头像网格按钮
						$('#show-random-grid').on('click', function(e) {
						    e.stopPropagation();
						    
						    const gridContainer = $('#random-avatars-grid-container');
						    if (gridContainer.is(':visible')) {
						        gridContainer.hide();
						        $(this).text('选择随机头像');
						    } else {
						        // 生成随机头像网格
						        self.generateRandomAvatarGrid();
						        gridContainer.show();
						        $(this).text('隐藏头像网格');
						        
						        // 选中随机类型
						        $('input[name="avatar-type"][value="random"]').prop('checked', true);
						    }
						});
						
						// 名称输入变化
						$('#dummy-name').on('input', function() {
						    const name = $(this).val().trim() || '用户';
						    $('#avatar-preview-name').text(name);
						});
						
						// 点击其他地方关闭假人面板
						$(document).on('click', function(e) {
						    if (!$(e.target).closest('#dummy-panel').length && 
						        !$(e.target).closest('#dummy-mode-btn').length &&
						        !$(e.target).closest('#dummy-toggle-btn').length) {
						        self.closeDummyPanel();
						    }
						});
						
						// 初始化预览
						this.updateAvatarPreview(this.currentDummyAvatar, this.currentDummyName);
						}
						
						getSelectedAvatar() {
						const selectedType = $('input[name="avatar-type"]:checked').val();
						let avatarUrl = '';
						
						if (selectedType === 'default') {
						    const avatarOptions = $('.default-avatars .avatar-option');
						    let selectedIndex = -1;
						    avatarOptions.each(function(index) {
						        if ($(this).find('input').is(':checked')) {
						            selectedIndex = index;
						            return false;
						        }
						    });
						    
						    switch(selectedIndex) {
						        case 0:
						            avatarUrl = '/assets/img/pz-yh.png';
						            break;
						        case 1:
						            avatarUrl = '/assets/img/px-yh.png';
						            break;
						        case 2:
						            avatarUrl = '/assets/img/bq-yh.png';
						            break;
						        default:
						            avatarUrl = '/assets/img/pz-yh.png';
						    }
						} else if (selectedType === 'custom') {
						    avatarUrl = $('#custom-avatar-preview').attr('src') || this.currentDummyAvatar;
						} else {
						    avatarUrl = $('#random-avatar-preview').attr('src') || this.currentDummyAvatar || this.getRandomAvatar();
						}
						
						return avatarUrl;
						}
						
						// 新增：更新头像预览
						updateAvatarPreview(avatarUrl, name) {
						const previewImg = $('#avatar-preview-img');
						previewImg.attr('src', avatarUrl);
						
						// 更新预览文本
						$('#avatar-preview-url').text(avatarUrl || '无头像');
						if (name) {
						    $('#avatar-preview-name').text(name);
						}
						
						// 错误处理
						previewImg.on('error', function() {
						    $(this).attr('src', 'Telegram:@yxhxzc888');
						    console.warn('头像加载失败，使用默认头像');
						});
						}
						
						
						// 新增：更新头像预览
						updateAvatarPreview(avatarUrl) {
						const previewImg = $('#avatar-preview-img');
						previewImg.attr('src', avatarUrl);
						
						// 添加加载错误处理
						previewImg.on('error', function() {
						    $(this).attr('src', '/assets/img/pz-yh.png');
						    console.warn('头像加载失败，使用默认头像');
						});
						}
						generateRandomAvatarGrid() {
						const gridContainer = $('#random-avatars-grid');
						if (!gridContainer.length) return;
						
						// 清空现有内容
						gridContainer.empty();
						
						// 生成1-20的随机头像
						for (let i = 1; i <= 20; i++) {
						    const extension = (i === 9 || i === 14 || i === 17) ? 'jpeg' : 'jpg';
						    const avatarUrl = `/assets/img/suiji/avatar${i}.${extension}`;
						    
						    const avatarItem = $(`
						        <div class="random-avatar-item" style="cursor: pointer; text-align: center; padding: 2px;" 
						             data-avatar="${avatarUrl}" title="avatar${i}.${extension}">
						            <img src="${avatarUrl}" style="width: 30px; height: 30px; border-radius: 50%; border: 1px solid #ddd;"
						                 onerror="this.style.display='none'; this.parentElement.innerHTML='❌';">
						            <div style="font-size: 9px; color: #666;">${i}</div>
						        </div>
						    `);
						    
						    // 点击选择头像
						    avatarItem.on('click', function() {
						        const avatarUrl = $(this).data('avatar');
						        
						        // 更新预览图
						        $('#random-avatar-preview').attr('src', avatarUrl);
						        
						        // 选中随机类型
						        $('input[name="avatar-type"][value="random"]').prop('checked', true);
						        
						        // 更新预览
						        self.updateAvatarPreview(avatarUrl);
						        
						        // 隐藏网格
						        $('#random-avatars-grid-container').hide();
						        $('#show-random-grid').text('选择随机头像');
						        
						        self.showToast(`已选择头像 ${i}`);
						    });
						    
						    gridContainer.append(avatarItem);
						}
						}
						
						    // 新增：获取随机头像
						getRandomAvatar() {
						const randomNum = Math.floor(Math.random() * 20) + 1; // 生成1-20的随机数
						let extension = 'jpg';
						
						// 检查需要特殊处理的头像
						switch(randomNum) {
						    case 9:
						    case 14:
						    case 17:
						        extension = 'jpeg';
						        break;
						    default:
						        extension = 'jpg';
						}
						
						return `/assets/img/suiji/avatar${randomNum}.${extension}`;
						}
						
						    // 新增方法：根据平台名称控制假人按钮显示
						    toggleDummyButtonByPlatform() {
						        const validPlatforms = ['盼之群聊', '螃蟹群聊', '白情群聊'];
						        const dummyButton = $('#dummy-mode-btn');
						        const dummyToggleBtn = $('#dummy-toggle-btn');
						        const aiButton = $('#ai-reply-btn');
						        const isValidPlatform = validPlatforms.includes(this.currentPlatform);
						        
						        console.log('当前平台:', this.currentPlatform);
						        console.log('有效平台列表:', validPlatforms);
						        console.log('是否在有效平台中:', isValidPlatform);
						        console.log('按钮元素:', dummyButton, dummyToggleBtn);
						        
						        if (isValidPlatform) {
						            dummyButton.addClass('platform-visible');
						            dummyToggleBtn.addClass('platform-visible');
						            aiButton.addClass('with-dummy-toggle');
						            console.log('添加platform-visible类后的按钮class:', dummyButton.attr('class'), dummyToggleBtn.attr('class'));
						            console.log('显示假人按钮');
						        } else {
						            dummyButton.removeClass('platform-visible');
						            dummyToggleBtn.removeClass('platform-visible');
						            aiButton.removeClass('with-dummy-toggle');
						            console.log('移除platform-visible类后的按钮class:', dummyButton.attr('class'), dummyToggleBtn.attr('class'));
						            console.log('隐藏假人按钮');
						        }
						    }
						
						    toggleDummyPanel() {
					        const panel = $('#dummy-panel');
					        const overlay = $('#dummy-panel-overlay');
					        
					        if (panel.hasClass('show')) {
					            panel.removeClass('show');
					            overlay.removeClass('show');
					        } else {
					            overlay.addClass('show');
					            panel.addClass('show');
					            this.updateDummyPanelStatus();
					        }
					    }
					    
					    closeDummyPanel() {
					        $('#dummy-panel').removeClass('show');
					        $('#dummy-panel-overlay').removeClass('show');
					    }
						
						  
						updateDummyPanelStatus() {
						console.log('更新面板状态，当前头像:', this.currentDummyAvatar);
						
						// 更新名称
						$('#dummy-name').val(this.currentDummyName);
						
						// 重置所有选中状态
						$('input[name="avatar-type"]').prop('checked', false);
						$('.default-avatars .avatar-option input').prop('checked', false);
						
						if (this.currentDummyAvatar) {
						    // 检查是否是三个默认头像之一
						    const defaultAvatars = {
						        '/assets/img/pz-yh.png': 0,
						        '/assets/img/px-yh.png': 1, 
						        '/assets/img/bq-yh.png': 2
						    };
						    
						    const avatarIndex = defaultAvatars[this.currentDummyAvatar];
						    
						    if (avatarIndex !== undefined) {
						        console.log('当前是默认头像，索引:', avatarIndex);
						        // 选中默认类型
						        $('input[name="avatar-type"][value="default"]').prop('checked', true);
						        
						        // 选中对应的默认头像选项
						        const defaultOption = $('.default-avatars .avatar-option').eq(avatarIndex);
						        defaultOption.find('input').prop('checked', true);
						        
						        console.log('选中的默认头像:', defaultOption.find('img').attr('src'));
						    } else if (this.currentDummyAvatar.startsWith('/uploads/groupsettings/')) {
					        $('input[name="avatar-type"][value="custom"]').prop('checked', true);
					        $('#custom-avatar-preview').attr('src', this.currentDummyAvatar);
					    } else {
					        $('input[name="avatar-type"][value="random"]').prop('checked', true);
					        $('#random-avatar-preview').attr('src', this.currentDummyAvatar);
					    }
						} else {
						    console.log('没有头像，使用默认');
						    // 默认选中第一个
						    $('input[name="avatar-type"][value="default"]').prop('checked', true);
						    $('.default-avatars .avatar-option:first-child input').prop('checked', true);
						}
						
						this.updateAvatarPreview(this.currentDummyAvatar, this.currentDummyName);
						this.updateDummyToggleButton();
						
						if (this.isDummyMode) {
						    $('#dummy-mode-btn').addClass('active');
						} else {
						    $('#dummy-mode-btn').removeClass('active');
						}
						}
						
						    // 修改切换假人模式的方法
						  toggleDummyMode() {
						this.isDummyMode = !this.isDummyMode;
						
						if (this.isDummyMode) {
						    if (!this.currentDummyAvatar) {
						        this.currentDummyAvatar = this.getRandomAvatar();
						    }
						    if (!this.currentDummyName) {
						        this.currentDummyName = '用户_' + Math.floor(Math.random() * 10000000);
						    }
						    
						    this.showToast(`已切换买家身份,滑动工具栏可自定义头像`);
						    this.createDummyStatusIndicator();
						    this.broadcastDummySettings();
						} else {
						    this.showToast('已切换客服身份');
						    this.removeDummyStatusIndicator();
						    this.broadcastDummySettings();
						}
						
						this.updateDummyPanelStatus();
						this.updateTradeCardButtonVisibility();
						}
						
						updateDummyToggleButton() {
						const toggleBtn = $('#dummy-toggle-btn');
						const avatarImg = toggleBtn.find('.dummy-toggle-avatar');
						
						if (this.isDummyMode) {
						    toggleBtn.addClass('active');
						    avatarImg.attr('src', this.currentDummyAvatar || '/assets/img/pz-yh.png');
						    avatarImg.attr('alt', '假人');
						    toggleBtn.attr('title', '当前为假人模式，点击切换为客服模式');
						} else {
						    toggleBtn.removeClass('active');
						    avatarImg.attr('src', '/assets/img/qiehuan.png');
						    avatarImg.attr('alt', '客服');
						    toggleBtn.attr('title', '当前为客服模式，点击切换为假人模式');
						}
						}
						
						uploadCustomAvatar(input) {
						const file = input.files[0];
						if (!file) return;
						
						const errorDiv = $('#custom-avatar-error');
						errorDiv.hide();
						
						if (!file.type.startsWith('image/')) {
						    errorDiv.text('请选择图片文件').show();
						    return;
						}
						
						const maxSize = 2 * 1024 * 1024;
						if (file.size > maxSize) {
						    errorDiv.text('图片大小不能超过2MB').show();
						    return;
						}
						
						const formData = new FormData();
						formData.append('avatar', file);
						formData.append('upload_path', 'groupsettings');
						
						const self = this;
						$.ajax({
						    url: '/api/groupchat/upload_avatar',
						    type: 'POST',
						    data: formData,
						    processData: false,
						    contentType: false,
						    beforeSend: function() {
						        self.showToast('上传中...');
						    },
						    success: function(response) {
						        let result = response;
						        if (typeof response === 'string') {
						            try {
						                result = JSON.parse(response);
						            } catch(e) {
						                errorDiv.text('服务器响应格式错误').show();
						                return;
						            }
						        }
						        
						        if (result.success) {
						            const avatarUrl = result.filepath || ('/uploads/groupsettings/' + result.filename);
						            $('#custom-avatar-preview').attr('src', avatarUrl);
						            $('input[name="avatar-type"][value="custom"]').prop('checked', true);
						            self.updateAvatarPreview(avatarUrl);
						            self.showToast('头像上传成功');
						        } else {
						            errorDiv.text(result.message || '上传失败').show();
						        }
						    },
						    error: function(xhr, status, error) {
						        errorDiv.text('上传失败，请重试 (' + error + ')').show();
						        console.error('Upload error:', xhr, status, error);
						    }
						});
						
						input.value = '';
						}
						
						    createDummyStatusIndicator() {
						        if ($('#dummy-status-indicator').length === 0) {
						            $('body').append(`
						                <div id="dummy-status-indicator" class="dummy-status">
						                    <i class="bi bi-person-plus"></i> 群聊模式: ${this.currentDummyName}
						                </div>
						            `);
						        }
						    }
						
						    removeDummyStatusIndicator() {
						        $('#dummy-status-indicator').remove();
						    }
						
						saveDummySettings() {
						const name = $('#dummy-name').val().trim();
						
						if (!name) {
						    this.showToast('请输入买家名称');
						    return;
						}
						
						const selectedType = $('input[name="avatar-type"]:checked').val();
						let avatarUrl = '';
						
						console.log('选择的头像类型:', selectedType);
						
						if (selectedType === 'default') {
						    const selectedDefault = $('input[name="default-avatar"]:checked');
						    if (selectedDefault.length > 0) {
						        avatarUrl = selectedDefault.val();
						    } else {
						        avatarUrl = $('input[name="default-avatar"]').first().val();
						    }
						} else if (selectedType === 'custom') {
						    avatarUrl = $('#custom-avatar-preview').attr('src');
						    if (!avatarUrl || avatarUrl === '/assets/img/jiaren.png') {
						        avatarUrl = '/assets/img/pz-yh.png';
						    }
						} else {
						    avatarUrl = $('#random-avatar-preview').attr('src') || this.getRandomAvatar();
						}
						
						console.log('保存设置 - 名称:', name, '头像:', avatarUrl);
						
						// 更新变量
						this.currentDummyName = name;
						this.currentDummyAvatar = avatarUrl;
						
						this.dummySettings = {
						    name: this.currentDummyName,
						    avatar: this.currentDummyAvatar
						};
						
						this.saveDummySettingsToStorage();
						
						// 更新预览
						this.updateAvatarPreview(avatarUrl, name);
						
						this.showToast('群聊设置已保存');
						
						// 广播设置给客户端
						this.broadcastDummySettings();
						
						if (this.isDummyMode) {
						    this.removeDummyStatusIndicator();
						    this.createDummyStatusIndicator();
						}
						}
						    // 设置自定义短语功能
						    setupCustomPhrases() {
						        const self = this;
						        
						        // 自定义短语按钮点击事件
						        $('#custom-phrases-btn').on('click', function(e) {
						            e.stopPropagation();
						            self.showCustomPhrasesModal();
						        });
						        
						        // 关闭按钮
						        $('#close-custom-phrases').on('click', function(e) {
						            e.stopPropagation();
						            self.hideCustomPhrasesModal();
						        });
						        
						        // 添加短语按钮
						        $('#add-phrase-btn').on('click', function() {
						            self.addCustomPhrase();
						        });
						        
						        // 回车键添加短语
						        $('#new-phrase-input').on('keydown', function(e) {
						            if (e.key === 'Enter') {
						                self.addCustomPhrase();
						            }
						        });
						        
						        // 清空所有短语
						        $('#clear-all-phrases').on('click', function() {
						            self.clearAllPhrases();
						        });
						        
						        // 点击遮罩关闭弹窗
						        $('#custom-phrases-modal').on('click', function(e) {
						            if (e.target === this) {
						                self.hideCustomPhrasesModal();
						            }
						        });
						    }
						    
						    // 显示自定义短语弹窗
						    showCustomPhrasesModal() {
						        $('#custom-phrases-modal').addClass('show');
						        $('#custom-phrases-btn').addClass('active');
						        $('#new-phrase-input').focus();
						    }
						    
						    // 隐藏自定义短语弹窗
						    hideCustomPhrasesModal() {
						        $('#custom-phrases-modal').removeClass('show');
						        $('#custom-phrases-btn').removeClass('active');
						    }
						    
						    // 添加自定义短语
						    addCustomPhrase() {
						        const input = $('#new-phrase-input');
						        const text = input.val().trim();
						        
						        if (!text) {
						            this.showToast('请输入短语内容');
						            return;
						        }
						        
						        if (this.customPhrases.includes(text)) {
						            this.showToast('该短语已存在');
						            return;
						        }
						        
						        // 添加到列表
						        this.customPhrases.unshift(text);
						        
						        // 保存到本地存储
						        this.saveCustomPhrases();
						        
						        // 更新显示
						        this.renderCustomPhrases();
						        
						        // 清空输入框
						        input.val('');
						        
						        this.showToast('短语添加成功');
						    }
						    
						    // 删除自定义短语
						    deleteCustomPhrase(index) {
						        if (index >= 0 && index < this.customPhrases.length) {
						            this.customPhrases.splice(index, 1);
						            this.saveCustomPhrases();
						            this.renderCustomPhrases();
						            this.showToast('短语已删除');
						        }
						    }
						    
						    // 清空所有自定义短语
						    clearAllPhrases() {
						        if (this.customPhrases.length === 0) {
						            this.showToast('没有可清空的短语');
						            return;
						        }
						        
						        if (confirm('确定要清空所有自定义短语吗？此操作不可撤销。')) {
						            this.customPhrases = [];
						            this.saveCustomPhrases();
						            this.renderCustomPhrases();
						            this.showToast('所有短语已清空');
						        }
						    }
						    
						    // 渲染自定义短语列表（包含卡片）
						    renderCustomPhrases() {
						        const list = $('#custom-phrases-list');
						        list.empty();
						        
						        const hasPhrases = this.customPhrases.length > 0;
						        const hasCards = this.customCards.length > 0;
						        
						        if (!hasPhrases && !hasCards) {
						            list.html('<div class="empty-phrases">暂无</div>');
						            return;
						        }
						        
						        // 渲染卡片列表（在前面）
						        if (hasCards) {
						            this.customCards.forEach((card, index) => {
						                const phraseItem = $('<div class="phrase-item phrase-item-card"></div>');
						                
						                // 卡片标题（带[卡片]标识）
						                const cardLabel = `[卡片] ${card.title}`;
						                const phraseText = $('<span class="phrase-text"></span>').text(cardLabel);
						                phraseText.on('click', () => {
						                    // 发送卡片消息
						                    const cardMessage = this.buildCardMessage(card.title, card.content, card.link, card.buttonText);
                            this.sendMessageDirectly(cardMessage);
                            this.hideCustomPhrasesModal();
                        });
						                
						                // 删除按钮
						                const deleteBtn = $('<button class="delete-phrase" title="删除卡片">×</button>');
						                deleteBtn.on('click', (e) => {
						                    e.stopPropagation();
						                    this.deleteCustomCard(index);
						                    this.renderCustomPhrases(); // 重新渲染
						                });
						                
						                phraseItem.append(phraseText, deleteBtn);
						                list.append(phraseItem);
						            });
						        }
						        
						        // 渲染普通短语列表
						        if (hasPhrases) {
						            this.customPhrases.forEach((phrase, index) => {
						                const phraseItem = $('<div class="phrase-item"></div>');
						                
						                // 短语文本（点击发送）
						                const phraseText = $('<span class="phrase-text"></span>').text(phrase);
						                phraseText.on('click', () => {
                    this.sendMessageDirectly(phrase);
                    this.hideCustomPhrasesModal();
                });
						                
						                // 删除按钮
						                const deleteBtn = $('<button class="delete-phrase" title="删除短语">×</button>');
						                deleteBtn.on('click', (e) => {
						                    e.stopPropagation();
						                    this.deleteCustomPhrase(index);
						                });
						                
						                phraseItem.append(phraseText, deleteBtn);
						                list.append(phraseItem);
						            });
						        }
						    }
						    
						    // 修复：重命名方法避免冲突
						    loadCustomPhrasesFromStorage() {
						        try {
						            const saved = localStorage.getItem('custom_phrases');
						            if (saved) {
						                this.customPhrases = JSON.parse(saved);
						                this.renderCustomPhrases();
						            }
						        } catch (error) {
						            console.error('加载自定义短语失败:', error);
						            this.customPhrases = [];
						        }
						    }
						    
						    // 保存自定义短语到本地存储
						    saveCustomPhrases() {
						        try {
						            localStorage.setItem('custom_phrases', JSON.stringify(this.customPhrases));
						        } catch (error) {
						            console.error('保存自定义短语失败:', error);
						        }
						    }
						
						// ==================== 卡片功能 ====================
						setupCardFeature() {
						    const self = this;
						    
						    // 初始化自定义卡片列表
						    this.customCards = [];
						    this.loadCustomCards();
						    
						    // 卡片按钮点击事件
						    $('#card-btn').on('click', function(e) {
						        e.stopPropagation();
						        self.openCardDrawer();
						    });
						    
						    // 关闭按钮
						    $('#card-drawer-close').on('click', function(e) {
						        e.stopPropagation();
						        self.closeCardDrawer();
						    });
						    
						    // 遮罩层点击
						    $('#card-drawer').on('click', function(e) {
						        if (e.target === this) {
						            self.closeCardDrawer();
						        }
						    });
						    
						    // 保存到本地按钮
						    $('#card-save-btn').on('click', function() {
						        self.saveCardToLocal();
						    });
						    
						    // 发送卡片按钮
						    $('#card-send-btn').on('click', function() {
						        self.sendCardFromDrawer();
						    });
						    
						    // 实时预览更新
						    $('#card-title-input, #card-content-input, #card-link-input, #card-button-text-input').on('input', function() {
						        self.updateCardPreview();
						    });
						}
						
						// 打开卡片抽屉
						openCardDrawer() {
						    const drawer = $('#card-drawer');
						    const button = $('#card-btn');
						    
						    // 清空表单
						    $('#card-title-input').val('');
						    $('#card-content-input').val('');
						    $('#card-link-input').val('');
						    $('#card-button-text-input').val('');
						    
						    // 重置预览
						    this.updateCardPreview();
						    
						    drawer.addClass('active');
						    button.addClass('active');
						}
						
						// 关闭卡片抽屉
						closeCardDrawer() {
						    const drawer = $('#card-drawer');
						    const button = $('#card-btn');
						    
						    drawer.removeClass('active');
						    button.removeClass('active');
						}
						
						// 更新卡片预览
						updateCardPreview() {
						    const title = $('#card-title-input').val().trim() || '卡片标题';
						    const content = $('#card-content-input').val().trim() || '卡片内容将显示在这里';
						    const link = $('#card-link-input').val().trim();
						    const buttonText = $('#card-button-text-input').val().trim();
						    
						    let previewHtml = `
						        <div class="XEmsg-card">
						            <div class="XEmsg-card__header">
						                <span class="XEmsg-card__title">${this.escapeHtml(title)}</span>
						            </div>
						            <div class="XEmsg-card__content">
						                ${this.escapeHtml(content)}
						            </div>
						    `;
						    
						    if (link && buttonText) {
						        previewHtml += `
						            <div class="XEmsg-card__actions">
						                <a href="${this.escapeHtml(link)}" target="_blank" class="XEmsg-card__button">
						                    ${this.escapeHtml(buttonText)}
						                </a>
						            </div>
						        `;
						    }
						    
						    previewHtml += `
						        </div>
						    `;
						    
						    $('#card-preview').html(previewHtml);
						}
						
						// 保存卡片到本地
						saveCardToLocal() {
						    const title = $('#card-title-input').val().trim();
						    const content = $('#card-content-input').val().trim();
						    const link = $('#card-link-input').val().trim();
						    const buttonText = $('#card-button-text-input').val().trim();
						    
						    if (!title || !content) {
						        this.showToast('请填写卡片标题和内容');
						        return;
						    }
						    
						    const cardData = {
						        id: Date.now(),
						        title: title,
						        content: content,
						        link: link,
						        buttonText: buttonText,
						        createdAt: new Date().toISOString()
						    };
						    
						    this.customCards.unshift(cardData);
						    this.saveCustomCards();
						    this.renderCustomPhrases(); // 重新渲染短语列表（包含卡片）
						    this.showToast('卡片已保存到短语列表，可快速发送');
						    this.closeCardDrawer();
						}
						
						// 从抽屉发送卡片
						sendCardFromDrawer() {
						    const title = $('#card-title-input').val().trim();
						    const content = $('#card-content-input').val().trim();
						    const link = $('#card-link-input').val().trim();
						    const buttonText = $('#card-button-text-input').val().trim();
						    
						    if (!title || !content) {
						        this.showToast('请填写卡片标题和内容');
						        return;
						    }
						    
						    // 构建卡片消息格式
						    const cardMessage = this.buildCardMessage(title, content, link, buttonText);
						    this.sendMessageDirectly(cardMessage);
						    this.closeCardDrawer();
						    this.showToast('卡片已发送');
						}
						
						// 构建卡片消息格式
						buildCardMessage(title, content, link, buttonText) {
						    const cardData = {
						        type: 'custom_card',
						        title: title,
						        content: content,
						        link: link || '',
						        buttonText: buttonText || ''
						    };
						    return 'XECARD#' + JSON.stringify(cardData);
						}
						
						// 保存自定义卡片到本地存储
						saveCustomCards() {
						    try {
						        localStorage.setItem('custom_cards', JSON.stringify(this.customCards));
						    } catch (error) {
						        console.error('保存自定义卡片失败:', error);
						    }
						}
						
						// 加载自定义卡片
						loadCustomCards() {
						    try {
						        const saved = localStorage.getItem('custom_cards');
						        if (saved) {
						            this.customCards = JSON.parse(saved);
						        }
						    } catch (error) {
						        console.error('加载自定义卡片失败:', error);
						        this.customCards = [];
						    }
						}
						
						// 删除自定义卡片
						deleteCustomCard(index) {
						    if (index >= 0 && index < this.customCards.length) {
						        this.customCards.splice(index, 1);
						        this.saveCustomCards();
						        this.showToast('卡片已删除');
						    }
						}
						
						// 发送已保存的卡片
						sendSavedCard(index) {
						    if (index >= 0 && index < this.customCards.length) {
						        const card = this.customCards[index];
						        const cardMessage = this.buildCardMessage(card.title, card.content, card.link, card.buttonText);
						        this.sendMessageDirectly(cardMessage);
						        this.showToast('卡片已发送');
						    }
						}
						
						// 设置交易信息功能
						setupTradeCardFeature() {
						    const self = this;
						    
						    // 交易信息按钮点击事件
						    $('#trade-card-btn').on('click', function(e) {
						        e.stopPropagation();
						        self.openTradeDrawer();
						    });
						    
						    // 关闭按钮
						    $('#close-trade-drawer').on('click', function(e) {
						        e.stopPropagation();
						        self.closeTradeDrawer();
						    });
						    
						    // 遮罩层点击
						    $('#trade-drawer-overlay').on('click', function() {
						        self.closeTradeDrawer();
						    });
						    
						    // 图片上传点击
						    $('#trade-image-upload').on('click', function() {
						        $('#trade-image').click();
						    });
						    
						    // 图片选择
						    $('#trade-image').on('change', function(e) {
						        const file = e.target.files[0];
						        if (file) {
						            self.handleTradeImageUpload(file);
						        }
						    });
						    
						    // 删除图片
						    $('#trade-image-remove').on('click', function() {
						        self.removeTradeImage();
						    });
						    
						    // 发送按钮
						    $('#trade-send-btn').on('click', function() {
						        self.sendTradeCard();
						    });
						}
						
						// 打开交易抽屉
						openTradeDrawer() {
						    $('#trade-drawer-overlay').addClass('show');
						    $('#trade-drawer').addClass('show');
						}
						
						// 关闭交易抽屉
						closeTradeDrawer() {
						    $('#trade-drawer-overlay').removeClass('show');
						    $('#trade-drawer').removeClass('show');
						}
						
						// 处理交易图片上传
						handleTradeImageUpload(file) {
						    const self = this;
						    const reader = new FileReader();
						    
						    reader.onload = function(e) {
						        $('#trade-image-preview-img').attr('src', e.target.result);
						        $('#trade-image-upload').hide();
						        $('#trade-image-preview').show();
						        self.tradeImageFile = file;
						    };
						    
						    reader.readAsDataURL(file);
						}
						
						// 移除交易图片
						removeTradeImage() {
						    $('#trade-image').val('');
						    $('#trade-image-preview-img').attr('src', '');
						    $('#trade-image-upload').show();
						    $('#trade-image-preview').hide();
						    this.tradeImageFile = null;
						}
						
						// 发送交易卡片
						sendTradeCard() {
						    const tradeStatus = $('#trade-status').val();
						    const tradeTitle = $('#trade-title').val().trim();
						    const tradeSubtitle = $('#trade-subtitle').val().trim();
						    const tradeDescription = $('#trade-description').val().trim();
						    const tradeGoodsNo = $('#trade-goods-no').val().trim();
						    const contractStatus = $('#trade-contract-status').val();
						    const tradePrice = $('#trade-price').val().trim();
						    const tradeNote = $('#trade-note').val().trim();
						    
						    if (!tradeTitle) {
						        this.showToast('请填写主标题');
						        return;
						    }
						    
						    const self = this;
						    
						    // 如果有图片，先上传
						    if (this.tradeImageFile) {
						        const formData = new FormData();
						        formData.append('image', this.tradeImageFile);
						        
						        $.ajax({
						            url: '/api/groupchat/upload_trade_image',
						            type: 'POST',
						            data: formData,
						            processData: false,
						            contentType: false,
						            dataType: 'json',
						            success: function(response) {
						                if (response.success && response.url) {
						                    self.sendTradeCardMessage(tradeStatus, tradeTitle, tradeSubtitle, tradeDescription, tradeGoodsNo, response.url, contractStatus, tradePrice, tradeNote);
						                } else {
						                    console.error('交易图片上传失败:', response.error || '未知错误');
						                    self.showToast(response.error || '图片上传失败');
						                }
						            },
						            error: function(xhr, textStatus, errorThrown) {
						                console.error('交易图片上传请求失败:', textStatus, errorThrown, xhr.status);
						                self.showToast('图片上传失败');
						            }
						        });
						    } else {
						        // 没有图片直接发送
						        this.sendTradeCardMessage(tradeStatus, tradeTitle, tradeSubtitle, tradeDescription, tradeGoodsNo, '', contractStatus, tradePrice, tradeNote);
						    }
						}
						
						// 发送交易卡片消息
						sendTradeCardMessage(tradeStatus, tradeTitle, tradeSubtitle, tradeDescription, goodsNo, imageUrl, contractStatus, tradePrice, tradeNote) {
						    const orderNo = 'ZH' + Date.now();
						    // 使用用户输入的商品编号，如果没有则随机生成
						    const finalGoodsNo = goodsNo || Math.floor(Math.random() * 100000000).toString();
						    const createTime = new Date().toLocaleString('zh-CN', { hour12: false });
						    
						    const tradeData = {
						        type: 'trade_card',
						        title: '交易信息',
						        trade_status: tradeStatus,
						        game_tag: '游戏',
						        type_tag: '交易',
						        main_title: tradeTitle,
						        subtitle: tradeSubtitle,
						        description: tradeDescription,
						        image_url: imageUrl,
						        order_no: orderNo,
						        goods_no: goodsNo.toString(),
						        create_time: createTime,
						        contract_status: contractStatus,
						        price: tradePrice,
						        note: tradeNote
						    };
						    
						    const tradeMessage = 'XEXXCARD#' + JSON.stringify(tradeData);
						    this.sendMessageDirectly(tradeMessage);
						    this.closeTradeDrawer();
						    this.clearTradeForm();
						    this.showToast('交易信息已发送');
						}
						
						// 清空交易表单
						clearTradeForm() {
						    $('#trade-status').val('已成交');
						    $('#trade-title').val('');
						    $('#trade-subtitle').val('');
						    $('#trade-description').val('');
						    $('#trade-goods-no').val('');
						    $('#trade-contract-status').val('已签署');
						    $('#trade-price').val('');
						    $('#trade-note').val('');
						    this.removeTradeImage();
						}
						
						// 拉群功能
						setupInviteGroupFeature() {
						    const self = this;
						    
						    // 拉群按钮点击
						    $('#invite-group-btn').on('click', function() {
						        self.openInviteGroupDrawer();
						    });
						    
						    // 关闭拉群弹窗
						    $('#close-invite-group-drawer').on('click', function() {
						        self.closeInviteGroupDrawer();
						    });
						    
						    // 点击遮罩关闭
						    $('#invite-group-overlay').on('click', function() {
						        self.closeInviteGroupDrawer();
						    });
						}
						
						// 打开拉群弹窗
						openInviteGroupDrawer() {
						    $('#invite-group-overlay').addClass('show');
						    $('#invite-group-drawer').addClass('show');
						    this.loadChatroomList();
						}
						
						// 关闭拉群弹窗
						closeInviteGroupDrawer() {
						    $('#invite-group-overlay').removeClass('show');
						    $('#invite-group-drawer').removeClass('show');
						}
						
						// 加载聊天室列表
						loadChatroomList() {
						    const self = this;
						    const $list = $('#invite-group-list');
						    const $empty = $('#invite-group-empty');
						    
						    $list.html('<div style="text-align:center;padding:20px;color:#999;">加载中...</div>');
						    $empty.hide();
						    
						    $.ajax({
						        url: this.API_BASE,
						        method: 'POST',
						        contentType: 'application/json',
						        data: JSON.stringify({
						            action: 'get_chatroom_list',
						            platform: this.currentPlatform,
						            agent_account: this.currentAgent
						        }),
						        success: function(data) {
						            if (data.success && data.chatrooms && data.chatrooms.length > 0) {
						                $list.empty();
						                data.chatrooms.forEach(function(room) {
						                    const itemHtml = `
						                        <div class="invite-group-item" data-page-code="${self.escapeHtml(room.page_code)}" data-product-name="${self.escapeHtml(room.product_name)}" data-product-code="${self.escapeHtml(room.product_code)}" data-product-image="${self.escapeHtml(room.product_image || '')}" data-share-link="${self.escapeHtml(room.share_link || '')}">
						                            <img src="${self.escapeHtml(room.product_image || '/assets/img/normal.png')}" alt="商品图片" onerror="this.src='/assets/img/normal.png'">
						                            <div class="invite-group-item-info">
						                                <div class="invite-group-item-name">${self.escapeHtml(room.product_code)} ${self.escapeHtml(room.product_name)}</div>
						                                <div class="invite-group-item-code">编号: ${self.escapeHtml(room.product_code)}</div>
						                            </div>
						                            <button class="invite-group-item-btn">拉群</button>
						                        </div>
						                    `;
						                    $list.append(itemHtml);
						                });
						                
						                // 绑定拉群按钮点击事件
						                $list.find('.invite-group-item-btn').on('click', function(e) {
						                    e.stopPropagation();
						                    const $item = $(this).closest('.invite-group-item');
						                    const pageCode = $item.data('page-code');
						                    const productName = $item.data('product-name');
						                    const productCode = $item.data('product-code');
						                    const productImage = $item.data('product-image');
						                    const shareLink = $item.data('share-link');
						                    self.sendInviteGroupCard(pageCode, productName, productCode, productImage, shareLink);
						                });
						            } else {
						                $list.empty();
						                $empty.show();
						            }
						        },
						        error: function() {
						            $list.html('<div style="text-align:center;padding:20px;color:#e74c3c;">加载失败，请重试</div>');
						        }
						    });
						}
						
						// 发送拉群卡片消息
						sendInviteGroupCard(pageCode, productName, productCode, productImage, shareLink) {
						    const protocol = window.location.protocol === 'https:' ? 'https://' : 'http://';
						    const host = window.location.host;
						    const fallbackLink = protocol + host + '/Pxb7group?XEchatroom=' + encodeURIComponent(pageCode);
						    const finalShareLink = shareLink || fallbackLink;
						    
						    const inviteData = {
						        type: 'invite_group_card',
						        product_name: productName,
						        product_code: productCode,
						        product_image: productImage,
						        share_link: finalShareLink
						    };
						    
						    const inviteMessage = 'XEINVITECARD#' + JSON.stringify(inviteData);
						    this.sendMessageDirectly(inviteMessage);
						    this.closeInviteGroupDrawer();
						    this.showToast('拉群卡片已发送');
						}
						
						// 根据假人模式显示/隐藏交易按钮和付款按钮
						updateTradeCardButtonVisibility() {
    const tradeBtn = $('#trade-card-btn');
    
    const validTradePlatforms = ['盼之群聊', '螃蟹群聊', '白情群聊'];
    const isValidTradePlatform = validTradePlatforms.includes(this.currentPlatform);
    
    if (isValidTradePlatform) {
        tradeBtn.show();
    } else {
        tradeBtn.hide();
    }
    
    // 拉群按钮：仅螃蟹和盼之平台显示
    const inviteGroupBtn = $('#invite-group-btn');
    const validInvitePlatforms = ['盼之', '螃蟹'];
    const isValidInvitePlatform = validInvitePlatforms.includes(this.currentPlatform);
    
    if (isValidInvitePlatform) {
        inviteGroupBtn.show();
    } else {
        inviteGroupBtn.hide();
    }
}
						
						// 付款功能
						setupPayCardFeature() {
						    const self = this;
						    
						    // Tab切换事件
						    $('.trade-tab-item').on('click', function() {
						        const tab = $(this).data('tab');
						        // 切换active状态
						        $('.trade-tab-item').removeClass('active');
						        $(this).addClass('active');
						        // 滑块动画
						        if (tab === 'pay') {
						            $('.trade-tab-slider').addClass('at-pay');
						        } else {
						            $('.trade-tab-slider').removeClass('at-pay');
						        }
						        // 切换内容
						        if (tab === 'trade') {
						            $('#trade-tab-trade').show();
						            $('#trade-tab-pay').hide();
						            $('#trade-send-btn').show();
						            $('#pay-send-btn').hide();
						        } else {
						            $('#trade-tab-trade').hide();
						            $('#trade-tab-pay').show();
						            $('#trade-send-btn').hide();
						            $('#pay-send-btn').show();
						        }
						    });
						    
						    // 发送按钮
						    $('#pay-send-btn').on('click', function() {
						        self.sendPayCard();
						    });
						}
						
						// 关闭付款抽屉（现在合并到交易抽屉）
						closePayDrawer() {
						    $('#trade-drawer-overlay').removeClass('show');
						    $('#trade-drawer').removeClass('show');
						}
						
						// 发送付款卡片
						sendPayCard() {
						    const payAmount = $('#pay-amount').val().trim();
						    const payGoodsNo = $('#pay-goods-no').val().trim();
						    
						    if (!payAmount) {
						        this.showToast('请填写付款金额');
						        return;
						    }
						    
						    this.sendPayCardMessage(payAmount, payGoodsNo);
						}
						
						// 发送付款卡片消息
						sendPayCardMessage(amount, goodsNo) {
						    const orderNo = 'ZH' + Date.now();
						    
						    const payData = {
						        type: 'pay_card',
						        order_no: orderNo,
						        goods_no: goodsNo,
						        amount: amount
						    };
						    
						    const payMessage = 'XEPAYCARD#' + JSON.stringify(payData);
						    this.sendMessageDirectly(payMessage);
						    this.closePayDrawer();
						    this.clearPayForm();
						    this.showToast('付款信息已发送');
						}
						
						// 清空付款表单
						clearPayForm() {
						    $('#pay-amount').val('');
						    $('#pay-goods-no').val('');
						}
						
						// 渲染付款卡片HTML
						renderPayCard(payData) {
						    return `
						        <div class="paycard">
						            <div class="paycard-title">订单已支付</div>
						            <div class="paycard-line paycard-fit"><span class="paycard-label">订单编号：</span><span class="paycard-value">${payData.order_no || ''}</span></div>
						            <div class="paycard-line paycard-fit"><span class="paycard-label">商品编号：</span><span class="paycard-value">${payData.goods_no || ''}</span></div>
						            <div class="paycard-line paycard-fit amount-line"><span class="paycard-label">支付金额：</span><span class="paycard-value amount">¥${payData.amount || ''}</span></div>
						        </div>
						    `;
						}

			// 修改客服端的状态检查
			startStatusChecking() {
			    const self = this;
			    
			    // 从20秒改为5秒
			    this.statusCheckInterval = setInterval(() => {
			        self.checkCustomerStatus();
			    }, 5000); // 5秒检查一次
			    
			    // 立即检查一次
			    this.checkCustomerStatus();

			}
						    
						    setupEventListeners() {
						        const self = this;
						        
						        // 发送消息
						        $('#send-btn').on('click', function() {
						            self.sendMessage();
						        });
						        
						        $('#message-input').on('keydown', function(e) {
						            if (e.key === 'Enter' && !e.shiftKey) {
						                e.preventDefault();
						                self.sendMessage();
						            }
						        });
						        
						        // 输入框变化时更新发送按钮状态
						        $('#message-input').on('input', function() {
						            self.updateSendButton();
						            self.autoResizeTextarea();
						        });
						        
						        // 图片上传
						        $('#sendImage').on('change', function(e) {
						            const file = e.target.files[0];
						            if (file) {
						                self.uploadImage(file);
						            }
						            $(this).val('');
						        });
						        
						        // 话术功能
						$('#phrases-btn').on('click', function(e) {
						    e.stopPropagation();
						    self.showPhrasesPanel();
						});
						
						// 关闭话术按钮
						$('#close-phrases').on('click', function(e) {
						    e.stopPropagation();
						    self.hidePhrasesModal();
						});
						
						// 点击遮罩关闭话术弹窗
						$('#phrases-modal').on('click', function(e) {
						    if (e.target === this) {
						        self.hidePhrasesModal();
						    }
						});
						        
						        // 预设话术点击
						        $('#phrases-modal .bottom-modal-body').on('click', '.phrase-item', function(e) {
						            e.stopPropagation();
						            const text = $(this).data('text');
						            if (text) {
						                self.sendMessageDirectly(text);
						                self.hidePhrasesModal();
						            }
						        });
						        
						        // 客户备注功能
						        $('#customer-note-btn').on('click', function() {
						            self.showCustomerNoteModal();
						        });
						        
						        // 会话信息
						        $('#session-info-btn').on('click', function() {
						            self.showInfoModal();
						        });
						        
						        // 删除聊天记录（进入多选模式）
						        $('#clear-chat-btn').on('click', function() {
						            self.startSelectionMode();
						        });
						        
						       // 修改现有的模态框点击事件
			$(document).on('click', '.mac-modal', function(e) {
			    // 检查点击的是否是模态框背景（不是内容区域）
			    if (e.target === this) {
			        // 对于普通抽屉，点击背景区域关闭
			        if (!$(this).is('#imagePreviewModal')) {
			            $(this).removeClass('active');
			        }
			    }
			});
						        
						        // ESC键关闭模态框
						        $(document).on('keydown', function(e) {
						            if (e.key === 'Escape') {
						                $('.mac-modal').removeClass('active');
						            }
						        });
						        
						        // 开关事件监听
						        document.getElementById('muteSwitch').addEventListener('change', () => {
						            this.toggleSessionSetting('muteSwitch', 'is_muted');
						        });
						        
						        document.getElementById('pinSwitch').addEventListener('change', () => {
						            this.toggleSessionSetting('pinSwitch', 'is_pinned');
						        });
						    }
						
						    
						    initPhraseGroups() {
						    const self = this;
						
						    // 为每个分组标题绑定点击事件
						    $('.phrase-group-header').off('click').on('click', function() {
						        const group = $(this).data('group');
						        self.togglePhraseGroup(group);
						    });
						    
						   // 为话术项绑定点击事件（使用事件委托）
						    $('#phrases-modal .bottom-modal-body').off('click', '.phrase-item').on('click', '.phrase-item', function(e) {
						        e.stopPropagation();
						        const text = $(this).data('text');
						        if (text) {
						            self.sendMessageDirectly(text);
						            self.hidePhrasesModal();
						        }
						    });
						}
						
						// 新增：切换话术分组
						togglePhraseGroup(groupName, expand) {
						    const header = $(`.phrase-group-header[data-group="${groupName}"]`);
						    const content = $(`#group-${groupName}`);
						    const arrow = header.find('.group-arrow');
						    
						    if (expand === undefined) {
						        expand = !content.hasClass('expanded');
						    }
						    
						    if (expand) {
						        content.addClass('expanded');
						        header.addClass('active');
						        arrow.css('transform', 'rotate(180deg)');
						        
						        // 计算内容实际高度并设置
						        const scrollHeight = content[0].scrollHeight;
						        content.css('max-height', scrollHeight + 'px');
						    } else {
						        content.removeClass('expanded');
						        header.removeClass('active');
						        arrow.css('transform', 'rotate(0deg)');
						        content.css('max-height', '0');
						    }
						}
						
						setupAIFunctionality() {
			    const self = this;
			    const aiBtn = $('#ai-reply-btn');
			    
			    // 先移除已有的事件监听器
			    aiBtn.off('click');
			    
			    // 只有在AI功能启用时才绑定点击事件
			    if (this.aiFunctionEnabled) {
			        // AI按钮点击事件
			        aiBtn.on('click', function() {
			            self.generateAIResponse();
			        });
			        
			        // 添加快捷键支持
			        $(document).on('keydown', function(e) {
			            if (e.ctrlKey && e.shiftKey && e.key === 'A') {
			                e.preventDefault();
			                self.generateAIResponse();
			            }
			        });
			    } else {
			        // AI功能禁用时，确保按钮不可点击
			        aiBtn.prop('disabled', true);
			    }
			}
			    
			 async generateAIResponse() {
			    if (this.aiGenerating) {
			        this.showToast('AI正在生成中，请稍候...');
			        return;
			    }
			    
			    if (!this.currentSessionKey) {
			        this.showToast('请先选择客户会话');
			        return;
			    }
			    
			    // 获取最后一条用户消息
			    const lastUserMessage = this.getLastUserMessage();
			    if (!lastUserMessage || !lastUserMessage.content) {
			        this.showToast('没有找到用户的最近消息');
			        return;
			    }
			    
			    
			    
			    this.aiGenerating = true;
			    this.setAIBtnLoading(true);
			    
			    try {
			        const userMessage = lastUserMessage.content;
			        
			        // 构建请求数据 - 只发送最后一条用户消息
			        const requestData = {
			            session_key: this.currentSessionKey,
			            user_message: userMessage, // 新增：用户最后一条消息
			            messages: [lastUserMessage], // 为了向后兼容，仍然保留messages字段
			            customer_name: this.currentCustomer || '客户',
			            platform: this.currentPlatform || '默认平台',
			            agent: this.currentAgent || 'admin',
			            // 新增字段，指定基于单条消息回复
			            reply_strategy: 'last_message'
			        };
			        
			
			        
			        const response = await $.ajax({
			            url: this.aiApiEndpoint + '?action=generate_reply&t=' + Date.now(),
			            method: 'POST',
			            contentType: 'application/json',
			            data: JSON.stringify(requestData),
			            dataType: 'json',
			            timeout: 15000,
			            beforeSend: function(xhr) {
			
			            }
			        });
			        
			
			        
			        if (response.success && response.reply) {
			            $('#message-input').val(response.reply);
			            $('#message-input').focus();
			            this.updateSendButton();
			            this.autoResizeTextarea();
			            this.showToast('AI生成成功');
			        } else {
			            throw new Error(response.message || 'AI生成回复失败');
			        }
			    } catch (error) {
			        console.error('AI生成失败详情:', error);
			        
			        let errorMsg = 'AI生成失败: ';
			        
			        if (error.status === 0) {
			            errorMsg = '网络连接失败，请检查网络连接';
			        } else if (error.status === 404) {
			            errorMsg = 'AI服务暂时不可用（404错误）';
			        } else if (error.status === 500) {
			            errorMsg = 'AI服务内部错误';
			        } else if (error.statusText === 'timeout') {
			            errorMsg = '请求超时，请稍后重试';
			        } else {
			            errorMsg += error.responseJSON?.message || error.statusText || error.message;
			        }
			        
			        this.showToast(errorMsg);
			        
			        // 使用改进的备用回复，基于最后一条用户消息
			        this.fallbackToTemplateResponse(lastUserMessage);
			    } finally {
			        this.aiGenerating = false;
			        this.setAIBtnLoading(false);
			    }
			}
			
			getRecentMessages(limit = 5) {
			    const messages = [];
			    let userMessageCount = 0;
			    
			    // 从现有DOM中获取消息，但只取用户消息
			    $('.XEmsg-message-container').each(function(index) {
			        if (userMessageCount >= limit) return false; // 限制用户消息数量
			        
			        const message = $(this).find('.XEmsg-message');
			        const bubble = message.find('.XEmsg-message__bubble');
			        const timeElem = message.find('.XEmsg-message__time');
			        const textElem = message.find('.XEmsg-message__text');
			        
			        // 只获取用户消息（非客服、非假人消息）
			        if (message.hasClass('XEmsg-message--incoming') && 
			            !message.hasClass('XEmsg-message--dummy')) {
			            
			            // 确定消息类型
			            let speaker = 'customer';
			            
			            // 获取消息内容
			            let content = '';
			            if (textElem.find('img').length > 0) {
			                // 如果是图片消息
			                const imgSrc = textElem.find('img').attr('src') || '';
			                content = `[图片] ${imgSrc}`;
			            } else {
			                // 文本消息
			                content = textElem.text().trim();
			            }
			            
			            // 获取时间
			            const time = timeElem.text().trim() || new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
			            
			            if (content) {
			                messages.unshift({ // 从旧到新排序
			                    speaker: speaker,
			                    content: content,
			                    time: time
			                });
			                userMessageCount++;
			            }
			        }
			    });
			    
			    return messages;
			}
			    // 新增：获取最后一条用户消息
			getLastUserMessage() {
			    const userMessages = [];
			    
			    $('.XEmsg-message-container').each(function(index) {
			        const message = $(this).find('.XEmsg-message');
			        const textElem = message.find('.XEmsg-message__text');
			        
			        // 只获取用户消息（非客服、非假人消息）
			        if (message.hasClass('XEmsg-message--incoming') && 
			            !message.hasClass('XEmsg-message--dummy')) {
			            
			            let content = '';
			            if (textElem.find('img').length > 0) {
			                const imgSrc = textElem.find('img').attr('src') || '';
			                content = `[图片] ${imgSrc}`;
			            } else {
			                content = textElem.text().trim();
			            }
			            
			            if (content) {
			                userMessages.unshift({
			                    content: content
			                });
			            }
			        }
			    });
			    
			    return userMessages.length > 0 ? userMessages[0] : null;
			}
			
			setAIBtnLoading(loading) {
			    const aiBtn = $('#ai-reply-btn');
			    if (loading) {
			        aiBtn.addClass('loading');
			        aiBtn.attr('title', 'AI正在思考中...');
			        aiBtn.prop('disabled', true);
			    } else {
			        aiBtn.removeClass('loading');
			        aiBtn.attr('title', 'AI智能回复');
			        aiBtn.prop('disabled', false);
			    }
			}
			    
			 fallbackToTemplateResponse(lastUserMessage = null) {
			    if (!lastUserMessage) {
			        // 尝试获取最后一条用户消息
			        lastUserMessage = this.getLastUserMessage();
			    }
			    
			    if (!lastUserMessage || !lastUserMessage.content) {
			        // 如果没有用户消息，使用默认回复
			        $('#message-input').val('您好！请问有什么可以帮助您的？');
			        $('#message-input').focus();
			        this.updateSendButton();
			        this.autoResizeTextarea();
			        this.showToast('已生成默认问候语');
			        return;
			    }
			    
			    // 根据用户最后一条消息生成智能回复
			    const userMessage = lastUserMessage.content.toLowerCase();
			    let reply = this.generateSmartReplyFromMessage(userMessage);
			    
			    $('#message-input').val(reply);
			    $('#message-input').focus();
			    this.updateSendButton();
			    this.autoResizeTextarea();
			    this.showToast('已根据用户消息生成智能回复');
			}
			generateSmartReplyFromMessage(userMessage) {
			    const lowerMessage = userMessage.toLowerCase();
			    
			    // 关键词匹配规则
			    const rules = [
			        {
			            keywords: ['价格', '多少钱', '费用', '收费', '价位', '多钱', '报价'],
			            replies: [
			                '关于价格的问题，我们需要根据具体情况来评估。您能详细描述一下您的需求吗？',
			                '价格会根据不同的服务方案有所差异。您方便告诉我具体需要什么服务吗？',
			                '我们可以根据您的预算提供最合适的方案。您预期的价格范围是多少呢？'
			            ]
			        },
			        {
			            keywords: ['时间', '多久', '什么时候', '时效', '周期', '多长时间', '几天'],
			            replies: [
			                '这个通常需要1-3个工作日完成，具体时间取决于工作量和复杂程度。',
			                '时间方面我们会尽快安排，正常情况下2-5天可以完成。',
			                '我们会根据紧急程度来安排，加急的话可以更快完成。'
			            ]
			        },
			        {
			            keywords: ['你好', '您好', '在吗', '有人吗', 'hello', 'hi', '喂', '哈喽'],
			            replies: [
			                '您好！我是客服，很高兴为您服务。请问有什么可以帮助您的？',
			                '您好！欢迎咨询，我会尽力为您解答问题。',
			                '您好！请问有什么可以帮到您的？'
			            ]
			        },
			        {
			            keywords: ['谢谢', '感谢', '辛苦了', '麻烦', '多谢', '感恩'],
			            replies: [
			                '不客气，这是我们应该做的！如有其他问题随时联系。',
			                '很高兴能帮助您！还有什么需要咨询的吗？',
			                '您太客气了，为您服务是我的职责。'
			            ]
			        },
			        {
			            keywords: ['联系方式', '电话', '微信', '手机', 'qq', '邮箱', '怎么联系'],
			            replies: [
			                '您可以通过平台在线客服联系我们，或者留下您的联系方式我们会主动联系您。',
			                '为了沟通更高效，建议您先在线描述具体需求，我们会安排专人对接。',
			                '客服热线是400-xxx-xxxx，工作时间内都有专人接听。'
			            ]
			        },
			        {
			            keywords: ['订单', '购买', '下单', '怎么买', '怎么购买', '想买'],
			            replies: [
			                '您可以在产品页面直接下单购买，如有问题我可以协助您。',
			                '购买流程很简单，选择好商品后点击购买按钮即可。',
			                '您对哪个产品感兴趣？我可以为您详细介绍购买流程。'
			            ]
			        },
			        {
			            keywords: ['售后', '退货', '退款', '维修', '问题', '坏了'],
			            replies: [
			                '关于售后问题，请提供订单号，我会尽快为您处理。',
			                '很抱歉给您带来不便，请描述具体情况，我会协助您解决。',
			                '售后问题请放心，我们会严格按照规定为您处理。'
			            ]
			        },
			        {
			            keywords: ['发货', '物流', '快递', '配送', '几天到'],
			            replies: [
			                '一般下单后24小时内发货，具体物流时间根据地区不同有所差异。',
			                '发货后我们会提供物流单号，您可以通过单号查询物流信息。',
			                '我们会尽快为您安排发货，请耐心等待。'
			            ]
			        },
			        {
			            keywords: ['优惠', '活动', '折扣', '便宜', '促销'],
			            replies: [
			                '目前有一些优惠活动，您可以在活动页面查看详情。',
			                '关注我们会有不定期的优惠活动，建议您持续关注。',
			                '针对新用户我们有专属优惠，您可以了解一下。'
			            ]
			        }
			    ];
			    
			    // 匹配规则
			    for (let rule of rules) {
			        for (let keyword of rule.keywords) {
			            if (lowerMessage.includes(keyword)) {
			                const randomIndex = Math.floor(Math.random() * rule.replies.length);
			                return rule.replies[randomIndex];
			            }
			        }
			    }
			    
			    // 如果用户消息是问句
			    if (lowerMessage.includes('?') || lowerMessage.includes('？') || 
			        lowerMessage.includes('吗') || lowerMessage.includes('么')) {
			        const questionReplies = [
			            '这个问题问得很好，让我为您详细说明一下。',
			            '关于这个问题，我的理解是...',
			            '您提的这个问题很关键，我来为您解答。',
			            '这个问题需要从几个方面来回答...',
			            '针对您的疑问，我的建议是...'
			        ];
			        const randomIndex = Math.floor(Math.random() * questionReplies.length);
			        return questionReplies[randomIndex];
			    }
			    
			    // 如果用户消息较短，可能是简单询问
			    if (lowerMessage.length < 10) {
			        const shortReplies = [
			            '我明白了，请问有什么具体需要帮助的吗？',
			            '了解，您是想咨询哪方面的信息呢？',
			            '好的，请继续描述您的需求。',
			            '收到，我会尽力为您提供帮助。'
			        ];
			        const randomIndex = Math.floor(Math.random() * shortReplies.length);
			        return shortReplies[randomIndex];
			    }
			    
			    // 通用回复
			    const defaultReplies = [
			        '感谢您的咨询，我会尽力为您解答。您能再详细描述一下您的问题吗？',
			        '您好，关于这个问题我需要了解更多细节才能给您准确的答复。',
			        '明白您的需求了，我会安排专业人员为您详细解答。',
			        '这个问题很好，让我为您详细说明一下相关流程。',
			        '感谢您的提问，我会为您提供最专业的解决方案。',
			        '我理解您的意思，请让我为您提供更详细的信息。',
			        '收到您的消息，我正在为您查询相关信息。',
			        '您提的这个问题很重要，我来为您详细介绍。',
			        '关于这个问题，我的建议是...',
			        '我了解您的需求了，我们会尽快为您处理。'
			    ];
			    
			    const randomIndex = Math.floor(Math.random() * defaultReplies.length);
			    return defaultReplies[randomIndex];
			}
			// 新增：更智能的关键词匹配
			extractKeywordsFromMessage(message) {
			    const keywords = new Set();
			    const commonWords = ['的', '了', '在', '是', '我', '你', '他', '她', '它', '这', '那', '有', '没有', '什么', '怎么', '为什么', '如何', '请问', '可以', '能', '不', '很', '都'];
			    
			    // 中文分词简化版（基于标点和空格）
			    const cleanMessage = message.replace(/[。，！？；："'\"'【】（）《》【】、]/g, ' ');
			    const words = cleanMessage.split(/\s+/).filter(word => 
			        word.length > 1 && 
			        !commonWords.includes(word) &&
			        !/^\d+$/.test(word) &&
			        word.length < 6 // 过滤过长词
			    );
			    
			    words.forEach(word => keywords.add(word));
			    return Array.from(keywords);
			}
			// 更智能的回复生成
			generateSmartReply(messages) {
			    // 获取最后几条消息进行分析
			    const lastMessages = messages.slice(-10); // 最近3条
			    
			    // 提取关键词
			    let keywords = this.extractKeywords(lastMessages);
			    
			    // 根据关键词生成回复
			    return this.generateReplyByKeywords(keywords, lastMessages);
			}
			
			// 根据关键词生成回复
			generateReplyByKeywords(keywords, messages) {
			    const lastMessage = messages[messages.length - 1];
			    const content = lastMessage.content.toLowerCase();
			    
			    // 定义关键词匹配规则
			    const rules = [
			        {
			            keywords: ['价格', '多少钱', '费用', '收费', '价位'],
			            replies: [
			                '关于价格的问题，我们需要根据具体情况来评估。您能详细描述一下您的需求吗？',
			                '价格会根据不同的服务方案有所差异。您方便告诉我具体需要什么服务吗？',
			                '我们可以根据您的预算提供最合适的方案。您预期的价格范围是多少呢？'
			            ]
			        },
			        {
			            keywords: ['时间', '多久', '什么时候', '时效', '周期'],
			            replies: [
			                '这个通常需要1-3个工作日完成，具体时间取决于工作量和复杂程度。',
			                '时间方面我们会尽快安排，正常情况下2-5天可以完成。',
			                '我们会根据紧急程度来安排，加急的话可以更快完成。'
			            ]
			        },
			        {
			            keywords: ['你好', '您好', '在吗', '有人吗', 'hello', 'hi'],
			            replies: [
			                '您好！我是客服，很高兴为您服务。请问有什么可以帮助您的？',
			                '您好！欢迎咨询，我会尽力为您解答问题。',
			                '您好！请问有什么可以帮到您的？'
			            ]
			        },
			        {
			            keywords: ['谢谢', '感谢', '辛苦了', '麻烦', '多谢'],
			            replies: [
			                '不客气，这是我们应该做的！如有其他问题随时联系。',
			                '很高兴能帮助您！还有什么需要咨询的吗？',
			                '您太客气了，为您服务是我的职责。'
			            ]
			        },
			        {
			            keywords: ['联系方式', '电话', '微信', '手机', 'qq', '邮箱'],
			            replies: [
			                '您可以通过平台在线客服联系我们，或者留下您的联系方式我们会主动联系您。',
			                '为了沟通更高效，建议您先在线描述具体需求，我们会安排专人对接。',
			                '客服热线是400-xxx-xxxx，工作时间内都有专人接听。'
			            ]
			        }
			    ];
			    
			    // 匹配规则
			    for (let rule of rules) {
			        for (let keyword of rule.keywords) {
			            if (content.includes(keyword)) {
			                const randomIndex = Math.floor(Math.random() * rule.replies.length);
			                return rule.replies[randomIndex];
			            }
			        }
			    }
			    
			    // 默认回复
			    const defaultReplies = [
			        '感谢您的咨询，我会尽力为您解答。您能再详细描述一下您的问题吗？',
			        '您好，关于这个问题我需要了解更多细节才能给您准确的答复。',
			        '明白您的需求了，我会安排专业人员为您详细解答。',
			        '这个问题很好，让我为您详细说明一下相关流程。',
			        '感谢您的提问，我会为您提供最专业的解决方案。'
			    ];
			    
			    const randomIndex = Math.floor(Math.random() * defaultReplies.length);
			    return defaultReplies[randomIndex];
			}
			    
			    // 记录AI使用情况
			    logAIGeneration(usage) {
			        // 可以记录AI使用次数、token消耗等
			
			        
			        // 保存到本地存储
			        try {
			            const aiStats = JSON.parse(localStorage.getItem('ai_stats') || '{}');
			            aiStats.total_requests = (aiStats.total_requests || 0) + 1;
			            aiStats.last_used = new Date().toISOString();
			            localStorage.setItem('ai_stats', JSON.stringify(aiStats));
			        } catch (error) {
			            console.error('记录AI使用统计失败:', error);
			        }
			    }
						
						
						    async sendMessage() {
    const messageInput = document.getElementById('message-input');
    const message = messageInput.value.trim();
    
    if (!message) {
        this.showToast('消息内容不能为空');
        return;
    }
    
    this.isSending = true;
    this.updateSendButton();
    
    let tempMessageId = '';
    
    try {
        // 检测是否为卡片消息
        const cardInfo = this.isCardMessage(message);
        
        // 确保使用当前的头像设置
        const currentAvatar = $('#dummy-avatar').val() || this.currentDummyAvatar || this.getRandomAvatar();
        const currentName = $('#dummy-name').val().trim() || this.currentDummyName || '用户_' + Math.floor(Math.random() * 10000000);
        
        const requestData = {
            type: 'send_message',
            session_key: this.currentSessionKey,
            agent_account: this.currentAgent,
            speaker_type: this.isDummyMode ? 3 : 2,
            content: message,
            customer_name: this.currentCustomer,
            // 假人模式额外参数
            dummy_mode: this.isDummyMode,
            dummy_name: this.isDummyMode ? currentName : null,
            dummy_avatar: this.isDummyMode ? currentAvatar : null
        };
        
        // 创建临时消息
        tempMessageId = 'temp_' + Date.now();
        requestData.temp_id = tempMessageId; // 添加临时ID用于回执
        
        const tempMessage = {
            id: tempMessageId,
            speaker_type: this.isDummyMode ? 3 : 2,
            content: message,
            created_at: new Date().toISOString(),
            isTemp: true,
            // 假人模式特有属性
            dummy_name: this.isDummyMode ? currentName : null,
            dummy_avatar: this.isDummyMode ? currentAvatar : null
        };
        
        // 如果检测到卡片消息
        if (cardInfo) {
            const cardData = this.getCardContent(message, cardInfo);
            const time = this.formatTime(new Date().toISOString(), true);
            
            // 根据是否为假人模式选择不同的消息类
            const messageClass = this.isDummyMode ? 'XEmsg-message XEmsg-message--dummy' : 'XEmsg-message XEmsg-message--outgoing';
            
            const cardHtml = `
                <div class="XEmsg-message-container" data-message-id="${tempMessageId}" style="position: relative; min-height: 36px;">
                    <div class="message-checkbox-wrapper" style="position: absolute; left: -35px; top: 50%; transform: translateY(-50%); display: none; z-index: 10;">
                        <input type="checkbox" class="message-checkbox" data-message-id="${tempMessageId}" style="width: 20px; height: 20px; border: 2px solid #c8c8c8; border-radius: 50%; -webkit-appearance: none; appearance: none; cursor: pointer; position: relative; background: #fff;">
                    </div>
                    <div class="${messageClass}" style="margin-left: 0;">
                        ${this.generateCardHtml(cardData, time)}
                    </div>
                </div>
            `;
            
            $('#messages-container').append(cardHtml);
        } else {
            this.appendMessageToChat(tempMessage);
        }
        
        messageInput.value = '';
        this.updateSendButton();
        this.autoResizeTextarea();
        this.scrollToBottom();
        
        // 始终通过 API 保存消息到数据库(确保数据持久化和WebSocket推送)
        console.log('通过 API 保存消息到数据库');
        const response = await $.ajax({
            url: this.API_BASE + '?action=send_message',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(requestData),
            dataType: 'json',
            timeout: 10000
        });
        
        if (response.success && response.message_id) {
            this.lastMessageId = Math.max(this.lastMessageId, response.message_id);
            this.markMessageAsConfirmed(tempMessageId, response.message_id);
            
            // 记录最近发送的消息ID,用于WebSocket去重
            this.recentlySentMessageIds.add(response.message_id);
            // 5秒后清除(避免内存泄漏)
            setTimeout(() => {
                this.recentlySentMessageIds.delete(response.message_id);
            }, 5000);
            
            // 记录最近通过API发送的消息ID,用于轮询去重
            this.recentlySentApiMessageIds.add(response.message_id);
            // 10秒后清除(避免内存泄漏)
            setTimeout(() => {
                this.recentlySentApiMessageIds.delete(response.message_id);
            }, 10000);
            
            // 记录最近发送的内容和时间,用于内容匹配去重
            if (!this._lastSentMessages) this._lastSentMessages = [];
            this._lastSentMessages.push({
                content: message,
                speaker_type: this.isDummyMode ? 3 : 2,
                time: Date.now()
            });
            // 只保留最近2秒内的记录（与去重检查的时间窗口保持一致）
            const now = Date.now();
            this._lastSentMessages = this._lastSentMessages.filter(item => (now - item.time) < 2000);
            
            console.log('消息已保存到数据库, ID:', response.message_id);
        } else {
            throw new Error(response.message || '发送失败');
        }
        
    } catch (error) {
        console.error('发送失败:', error);
        if (tempMessageId) {
            this.markMessageAsFailed(tempMessageId);
        }
        this.showToast('发送失败: ' + (error.responseJSON?.message || error.statusText || error.message));
    } finally {
        this.isSending = false;
        this.updateSendButton();
    }
}
						    
						    async broadcastDummySettings() {
						        try {
						            const response = await $.ajax({
						                url: this.API_BASE + '?action=broadcast_dummy_settings',
						                method: 'POST',
						                contentType: 'application/json',
						                data: JSON.stringify({
						                    session_key: this.currentSessionKey,
						                    dummy_name: this.currentDummyName,
						                    dummy_avatar: this.currentDummyAvatar,
						                    is_dummy_mode: this.isDummyMode
						                }),
						                dataType: 'json'
						            });
						            
						            if (response.success) {
			
						            }
						        } catch (error) {
						            console.error('XE群聊初始化失败:', error);
						        }
						    }
						
						// 修改客户状态检查函数
			async checkCustomerStatus() {
			    if (!this.currentCustomer) return;
			    
			    try {
			        const response = await $.ajax({
			            url: this.API_BASE + '?action=get_customer_online_status',
			            method: 'POST',
			            contentType: 'application/json',
			            data: JSON.stringify({
			                username: this.currentCustomer
			            }),
			            dataType: 'json',
			            timeout: 5000
			        });
			        
			        if (response.success && response.status) {
			            const statusData = response.status;
			            
			            // 使用统一的状态获取函数
			            const consistentStatus = this.getConsistentStatus(
			                statusData.window_status,
			                statusData.is_online,
			                statusData.seconds_ago
			            );
			            
			            // 更新显示
			            this.updateStatusDisplay(consistentStatus.display);
			        } else {
			            console.warn('获取状态失败:', response.message);
			            this.updateStatusDisplay('offline');
			        }
			    } catch (error) {
			        console.error('检查客户状态失败:', error);
			        this.updateStatusDisplay('offline');
			    }
			}
			// 新增：处理客户状态
			handleCustomerStatus(statusData) {
			    if (!statusData) {
			        this.updateStatusDisplay('offline');
			        return;
			    }
			    
			    // statusData 可能是布尔值或对象
			    if (typeof statusData === 'boolean') {
			        this.updateStatusDisplay(statusData ? 'online' : 'offline');
			    } else if (typeof statusData === 'object' && statusData.status) {
			        // 如果是对象，包含详细状态
			        this.updateStatusDisplay(statusData.status);
			    } else if (typeof statusData === 'string') {
			        // 如果是字符串状态
			        this.updateStatusDisplay(statusData);
			    } else {
			        this.updateStatusDisplay('offline');
			    }
			}
						
						    // 新增：根据最后活动时间推断状态
			    inferStatusFromActivity() {
			        const lastMessageTime = this.getLastCustomerMessageTime();
			        if (lastMessageTime) {
			            const minutesAgo = (Date.now() - lastMessageTime) / (1000 * 60);
			            if (minutesAgo < 2) {
			                this.updateStatusDisplay('online');
			            } else if (minutesAgo < 5) {
			                this.updateStatusDisplay('away');
			            } else {
			                this.updateStatusDisplay('offline');
			            }
			        } else {
			            // 如果没有客户消息，默认显示为离线
			            this.updateStatusDisplay('offline');
			        }
			    }
						
						    // 新增：获取最后客户消息时间
			    getLastCustomerMessageTime() {
			        const customerMessages = $('.XEmsg-message--incoming:not(.XEmsg-message--dummy)');
			        if (customerMessages.length > 0) {
			            const lastMessage = customerMessages.last();
			            const timeText = lastMessage.find('.XEmsg-message__time').text();
			            // 尝试从消息时间文本解析时间戳
			            // 如果无法解析，返回30分钟前的时间
			            return Date.now() - (30 * 60 * 1000); // 默认为30分钟前
			        }
			        return null;
			    }
						
						    // 修改更新状态显示函数
			updateStatusDisplay(status) {
			    const statusElement = $('.XEmsg-header__status');
			    this.customerOnlineStatus = status;
			    
			    let statusText = '';
			    let statusColor = '';
			    let titleStatus = '';
			    
			    switch(status) {
			        case 'online':
			            statusText = '在线';
			            statusColor = '#52c41a';
			            titleStatus = '在线';
			            break;
			        case 'hidden':
			        case 'away':
			            statusText = '隐藏窗口';
			            statusColor = '#faad14';
			            titleStatus = '隐藏窗口';
			            break;
			        case 'offline':
			        default:
			            statusText = '离线';
			            statusColor = '#999';
			            titleStatus = '离线';
			    }
			    
			    // 更新状态显示
			    statusElement.text(statusText).css('color', statusColor);
			    
			    // 同步更新浏览器标签页标题状态
			    this.updateTitleStatus(status, titleStatus);
			    
			    // 更新详情信息模态框中的状态（如果打开）
			    this.updateInfoModalStatus(status, statusText);

			}
						    
						    // 修正模态框状态更新方法的选择器
						    updateInfoModalStatus(status, statusText) {
						        const statusElement = $('#modal-status');
						        if (!statusElement.length) return;
						        
						        let displayText = statusText || '检测中';
						        let statusColor = '';
						        
						        switch(status) {
						            case 'online':
						                statusColor = '#52c41a';
						                break;
						            case 'offline':
						                statusColor = '#999';
						                break;
						            case 'away':
						                statusColor = '#faad14';
						                break;
						            default:
						                statusColor = '#666';
						        }
						        
						        statusElement.text(displayText).css('color', statusColor);
						    }
						
						    // 修改更新标题状态函数
			updateTitleStatus(status, customTitleText = null) {
			    const statusTexts = {
			        'online': '在线',
			        'hidden': '隐藏窗口',
			        'away': '隐藏窗口',
			        'offline': '离线'
			    };
			    
			    const originalTitle = `与 ${this.currentCustomer} 的对话`;
			    const statusText = customTitleText || statusTexts[status] || '离线';
			    
			    // 更新文档标题
			    document.title = `[${statusText}] ${originalTitle}`;
			}
			
			
			// 新增：统一获取和计算状态
			getConsistentStatus(windowStatus, isOnline, secondsAgo) {
			    // 状态优先级：离线 > 离开 > 隐藏 > 在线
			    if (!isOnline) {
			        return { display: 'offline', title: '离线' };
			    }
			    
			    // 检查心跳时间
			    if (secondsAgo > 20) { // 超过20秒无心跳
			        return { display: 'offline', title: '离线' };
			    } else if (secondsAgo > 10) { // 10-20秒
			        return { display: 'away', title: '隐藏窗口' };
			    }
			    
			    // 根据窗口状态判断
			    switch(windowStatus) {
			        case 'window_visible':
			            return { display: 'online', title: '在线' };
			        case 'window_hidden':
			            return { display: 'hidden', title: '隐藏窗口' };
			        case 'window_closed':
			        default:
			            return { display: 'offline', title: '离线' };
			    }
			}
						
						    // 新增：启动心跳机制（保持客服在线状态）
						    startHeartbeat() {
						        // 每25秒发送一次心跳
						        this.heartbeatInterval = setInterval(() => {
						            this.sendHeartbeat();
						        }, 25000);
						        
						        // 初始化时立即发送一次
						        this.sendHeartbeat();
						    }
						
						    // 新增：发送心跳
						    async sendHeartbeat() {
						        try {
						            await $.ajax({
						                url: this.API_BASE + '?action=update_online_status',
						                method: 'POST',
						                contentType: 'application/json',
						                data: JSON.stringify({
						                    username: this.currentAgent,
						                    user_type: 'agent',
						                    is_online: true
						                }),
						                dataType: 'json',
						                timeout: 3000
						            });
						        } catch (error) {
						            console.error('心跳发送失败:', error);
						        }
						    }
						
						   async sendMessageDirectly(content) {
			        if (!this.currentSessionKey) {
			            this.showToast('请先选择客户会话');
			            return;
			        }
			        
			        if (this.isSending) {
			            this.showToast('正在发送消息，请稍候...');
			            return;
			        }
			        
			        if (!content) return;
			        
			        this.isSending = true;
			        this.updateSendButton();
			        
			        let tempMessageId = '';
			        
			        try {
			            // 检测是否为卡片消息
			            const cardInfo = this.isCardMessage(content);
			            
			            tempMessageId = 'temp_' + Date.now();
			            const tempMessage = {
			                id: tempMessageId,
			                speaker_type: this.isDummyMode ? 3 : 2,
			                content: content,
			                created_at: new Date().toISOString(),
			                isTemp: true,
			                dummy_name: this.isDummyMode ? this.currentDummyName : null,
			                dummy_avatar: this.isDummyMode ? this.currentDummyAvatar : null
			            };
			            
			            // 如果检测到卡片消息
			            if (cardInfo) {
			                const cardData = this.getCardContent(content, cardInfo);
			                const time = this.formatTime(new Date().toISOString(), true);
			                
			                // 根据是否为假人模式选择不同的消息类
			                const messageClass = this.isDummyMode ? 'XEmsg-message XEmsg-message--dummy' : 'XEmsg-message XEmsg-message--outgoing';
			                
			                const cardHtml = `
			                    <div class="XEmsg-message-container" data-message-id="${tempMessageId}" style="position: relative; min-height: 36px;">
			                        <div class="message-checkbox-wrapper" style="position: absolute; left: -35px; top: 50%; transform: translateY(-50%); display: none; z-index: 10;">
			                            <input type="checkbox" class="message-checkbox" data-message-id="${tempMessageId}" style="width: 20px; height: 20px; border: 2px solid #c8c8c8; border-radius: 50%; -webkit-appearance: none; appearance: none; cursor: pointer; position: relative; background: #fff;">
			                        </div>
			                        <div class="${messageClass}" style="margin-left: 0;">
			                            ${this.generateCardHtml(cardData, time)}
			                        </div>
			                    </div>
			                `;
			                
			                $('#messages-container').append(cardHtml);
			            } else {
			                this.appendMessageToChat(tempMessage);
			            }
			            
			            this.scrollToBottom();
			            
			            const response = await $.ajax({
			                url: this.API_BASE + '?action=send_message',
			                method: 'POST',
			                contentType: 'application/json',
			                data: JSON.stringify({
			                    session_key: this.currentSessionKey,
			                    agent_account: this.currentAgent,
			                    speaker_type: this.isDummyMode ? 3 : 2,
			                    content: content,
			                    customer_name: this.currentCustomer,
			                    dummy_mode: this.isDummyMode,
			                    dummy_name: this.isDummyMode ? this.currentDummyName : null,
			                    dummy_avatar: this.isDummyMode ? this.currentDummyAvatar : null
			                }),
			                dataType: 'json'
			            });
			            
			            if (response.success && response.message_id) {
			                this.lastMessageId = Math.max(this.lastMessageId, response.message_id);
			                this.markMessageAsConfirmed(tempMessageId, response.message_id);
			            } else {
			                throw new Error(response.message || '发送失败');
			            }
			        } catch (error) {
			            console.error('发送消息失败:', error);
			            if (tempMessageId) {
			                this.markMessageAsFailed(tempMessageId);
			            }
			            this.showToast('发送失败: ' + error.message);
			        } finally {
			            this.isSending = false;
			            this.updateSendButton();
			        }
			    }
						    
						    async uploadImage(file, retryCount = 0) {
						        if (!this.currentSessionKey) {
						            this.showToast('请先选择客户会话');
						            return;
						        }
						        
						        if (this.isUploadingImage) {
						            this.showToast('正在上传图片，请稍候...');
						            return;
						        }
						        
						        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
						        const maxSize = 5 * 1024 * 1024;
						        
						        if (!allowedTypes.includes(file.type) || file.size > maxSize) {
						            this.showToast('请选择有效的图片文件（JPEG/PNG/GIF/WebP，小于5MB）');
						            return;
						        }
						        
						        this.isUploadingImage = true;
						        this.updateSendButton();
						        
						        let tempMessageId = '';
						        
						        try {
						            // 创建临时消息 ID（使用时间戳 + 随机数避免重复）
						            tempMessageId = 'temp_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
						            
						            // 创建临时消息对象
						            const tempMessage = {
						                id: tempMessageId,
						                speaker_type: this.isDummyMode ? 3 : 2,
						                content: '[图片]',
						                message_type: 'image',
						                image_url: URL.createObjectURL(file),
						                created_at: new Date().toISOString(),
						                isTemp: true,
						                dummy_name: this.isDummyMode ? this.currentDummyName : null,
						                dummy_avatar: this.isDummyMode ? this.currentDummyAvatar : null
						            };
						            
						            // 添加到聊天界面
						            this.appendMessageToChat(tempMessage);
						            this.scrollToBottom();
						            
						            // 使用FormData上传
						            const formData = new FormData();
						            formData.append('session_key', this.currentSessionKey);
						            formData.append('agent_account', this.currentAgent);
						            formData.append('customer_name', this.currentCustomer);
						            formData.append('image_file', file);
						            formData.append('speaker_type', this.isDummyMode ? 3 : 2);
						            formData.append('dummy_mode', this.isDummyMode);
						            formData.append('dummy_name', this.isDummyMode ? this.currentDummyName : '');
						            formData.append('dummy_avatar', this.isDummyMode ? this.currentDummyAvatar : '');
						            
						            console.log('开始上传图片，临时 ID:', tempMessageId);
						            
						            const response = await $.ajax({
						                url: this.API_BASE + '?action=upload_image',
						                method: 'POST',
						                data: formData,
						                processData: false,
						                contentType: false,
						                dataType: 'json',
						                timeout: 30000
						            });
						            
						            console.log('图片上传响应:', response);
						            
						            if (response.success && response.message_id) {
						                this.lastMessageId = Math.max(this.lastMessageId, response.message_id);
						                
						                // 清理临时创建的ObjectURL
						                URL.revokeObjectURL(tempMessage.image_url);
						                
						                // 标记消息为已确认
						                this.markMessageAsConfirmed(tempMessageId, response.message_id, response.image_url);
						                
						                this.showToast('图片发送成功');
						            } else {
						                throw new Error(response.message || '上传失败');
						            }
						        } catch (error) {
						            console.error('图片上传失败详情:', {
						                status: error.status,
						                statusText: error.statusText,
						                responseText: error.responseText,
						                message: error.message,
						                error: error
						            });
						            
						            // 自动重试逻辑（最多重试 2 次，排除 413 和 415 错误）
						            // 注意：如果是服务器返回的JSON错误（success=false），error.status是undefined
						            const isServerError = error.status === 500 || error.status === 502 || error.status === 503;
						            const isClientError = error.status === 413 || error.status === 415;
						            const isJsonError = !error.status && error.message && error.message !== '上传失败';
						            
						            if (retryCount < 2 && !isClientError) {
						                console.log(`图片上传失败，将在 1 秒后重试（第 ${retryCount + 1} 次）`);
						                this.showToast('上传失败，正在重试...');
						                
						                // 删除失败的临时消息
						                if (tempMessageId) {
						                    $(`[data-message-id="${tempMessageId}"]`).remove();
						                }
						                
						                setTimeout(() => {
						                    this.uploadImage(file, retryCount + 1);
						                }, 1000);
						                return;
						            }
						            
						            if (tempMessageId) {
						                this.markMessageAsFailed(tempMessageId);
						            }
						            
						            let errorMsg = '图片上传失败: ';
						            if (error.status === 413) {
						                errorMsg += '文件太大，请压缩后重试';
						            } else if (error.status === 415) {
						                errorMsg += '不支持的图片格式';
						            } else if (error.statusText === 'timeout') {
						                errorMsg += '上传超时，请检查网络连接';
						            } else if (error.status === 500) {
						                errorMsg += '服务器内部错误，请查看后端日志';
						            } else {
						                errorMsg += error.responseJSON?.message || error.statusText || error.message;
						            }
						            
						            this.showToast(errorMsg);
						        } finally {
						            this.isUploadingImage = false;
						            this.updateSendButton();
						        }
						    }
						    
						    markMessageAsConfirmed(tempId, realId, imageUrl = null) {
			        const tempElement = $(`[data-message-id="${tempId}"]`);
			        if (tempElement.length) {
			            tempElement.attr('data-message-id', realId)
			                     .removeClass('temp-message failed-message')
			                     .find('.send-failed, .message-sending').remove();
			            
			            // 获取图片元素
			            const imgElement = tempElement.find('img');
			            
			            // 如果有新的图片URL，更新图片src
			            if (imageUrl && imgElement.length) {
			                // 检查是否存在加载动画元素，如果不存在则创建一个
			                let loadingElement = imgElement.siblings('.image-loading');
			                if (!loadingElement.length) {
			                    // 创建加载动画元素
			                    loadingElement = $('<div class="image-loading" style="display: flex; align-items: center; justify-content: center; width: 100px; height: 100px; border-radius: 8px; background: #f5f5f5;"><div class="loading-spinner" style="width: 20px; height: 20px; border: 2px solid #e0e0e0; border-top: 2px solid #007AFF; border-radius: 50%; animation: spin 1s linear infinite;"></div></div>');
			                    imgElement.before(loadingElement);
			                }
			                
			                // 显示加载动画
			                loadingElement.show();
			                imgElement.hide();
			                imgElement.siblings('.image-error').hide();
			                
			                // 重置图片事件监听器
			                imgElement.off('load error');
			                
			                // 绑定load事件
			                imgElement.on('load', function() {
			                    $(this).show();
			                    $(this).siblings('.image-loading').hide();
			                    $(this).siblings('.image-error').hide();
			                });
			                
			                // 绑定error事件
			                imgElement.on('error', function() {
			                    $(this).hide();
			                    $(this).siblings('.image-loading').hide();
			                    $(this).siblings('.image-error').show();
			                });
			                
			                // 立即更新图片URL
			                imgElement.attr('src', imageUrl);
			                
			                // 立即触发一次处理，确保状态正确
			                setTimeout(() => {
			                    if (imgElement[0].complete) {
			                        if (imgElement[0].naturalWidth > 0) {
			                            // 图片已加载完成
			                            imgElement.show();
			                            imgElement.siblings('.image-loading').hide();
			                            imgElement.siblings('.image-error').hide();
			                        } else {
			                            // 图片加载失败
			                            imgElement.hide();
			                            imgElement.siblings('.image-loading').hide();
			                            imgElement.siblings('.image-error').show();
			                        }
			                    }
			                }, 50);
			                
			                // 再次触发处理，确保图片加载完成
			                setTimeout(() => {
			                    if (imgElement[0].complete) {
			                        if (imgElement[0].naturalWidth > 0) {
			                            // 图片已加载完成
			                            imgElement.show();
			                            imgElement.siblings('.image-loading').hide();
			                            imgElement.siblings('.image-error').hide();
			                        } else {
			                            // 图片加载失败
			                            imgElement.hide();
			                            imgElement.siblings('.image-loading').hide();
			                            imgElement.siblings('.image-error').show();
			                        }
			                    }
			                }, 150);
			            } else if (imgElement.length) {
			                // 处理没有新图片URL的情况（可能是文本消息或已有的图片消息）
			                // 重置事件监听器
			                imgElement.off('load error');
			                
			                // 绑定load事件
			                imgElement.on('load', function() {
			                    $(this).show();
			                    $(this).siblings('.image-loading').hide();
			                    $(this).siblings('.image-error').hide();
			                });
			                
			                // 绑定error事件
			                imgElement.on('error', function() {
			                    $(this).hide();
			                    $(this).siblings('.image-loading').hide();
			                    $(this).siblings('.image-error').show();
			                });
			                
			                // 检查图片是否已经加载完成
			                if (imgElement[0].complete) {
			                    if (imgElement[0].naturalWidth > 0) {
			                        // 图片已加载完成
			                        imgElement.show();
			                        imgElement.siblings('.image-loading').hide();
			                        imgElement.siblings('.image-error').hide();
			                    } else {
			                        // 图片加载失败
			                        imgElement.hide();
			                        imgElement.siblings('.image-loading').hide();
			                        imgElement.siblings('.image-error').show();
			                    }
			                } else {
			                    // 图片正在加载
			                    imgElement.hide();
			                    imgElement.siblings('.image-loading').show();
			                    imgElement.siblings('.image-error').hide();
			                }
			            }
			        }
			    }
			
			async pollMessages() {
    if (!this.currentSessionKey) return;
    
    try {
        const response = await this.optimizedRequest(this.API_BASE + '?action=poll_messages', {
            method: 'GET',
            data: {
                session_id: this.currentSessionKey,
                last_id: this.lastMessageId
            },
            timeout: 10000,
            cacheable: false
        });
        
        if (response.success && response.messages && response.messages.length > 0) {
            const newMessages = response.messages.filter(msg => msg.id > this.lastMessageId);
            
            if (newMessages.length > 0) {
                // 检查是否有客户的新消息
                const hasCustomerMessage = newMessages.some(msg => msg.speaker_type === 1);
                
                if (hasCustomerMessage) {
                    console.log('轮询发现客户消息，触发自动标记已读');
                    // 客户发送了消息，立即标记已读
                    this.autoMarkMessagesAsRead();
                    // 立即检查一次状态
                    this.checkCustomerStatus();
                }
                
                newMessages.forEach(message => {
                    // 去重检查0:如果消息ID在最近通过API发送的列表中，跳过（防止轮询到自己发送的消息）
                    if (this.recentlySentApiMessageIds.has(message.id)) {
                        console.log('轮询消息是自己刚通过API发送的,跳过:', message.id);
                        return;
                    }
                    
                    // 去重检查0.5:如果消息ID在最近通过WebSocket接收的列表中，跳过（防止与WebSocket推送重复）
                    if (this.recentlyReceivedWsMessageIds.has(message.id)) {
                        console.log('轮询消息已通过WebSocket接收,跳过:', message.id);
                        return;
                    }
                    
                    // 去重检查1:如果消息已通过WebSocket接收,则跳过（通过消息ID）
                    if ($(`[data-message-id="${message.id}"]`).length > 0) {
                        console.log('轮询消息已存在(WebSocket已接收),跳过:', message.id);
                        return;
                    }
                    
                    // 去重检查2:检查是否有相同内容和说话者类型的消息（防止重复）
                    const messageContainers = $('.XEmsg-message-container');
                    let hasDuplicate = false;
                    for (let i = 0; i < messageContainers.length; i++) {
                        const container = $(messageContainers[i]);
                        const textContent = container.find('.XEmsg-message__text').text();
                        const messageId = container.data('message-id');
                        const messageBubble = container.find('.XEmsg-message__bubble');
                        
                        // 确定DOM中的说话者类型
                        let domSpeakerType = 1; // 默认客户
                        if (messageBubble.hasClass('XEmsg-message__bubble--outgoing') || 
                            messageBubble.hasClass('XEmsg-message__bubble--dummy')) {
                            domSpeakerType = 2; // 客服或假人
                        }
                        
                        // 如果是相同内容和说话者类型，说明已经显示过了
                        if (textContent === message.content && domSpeakerType === message.speaker_type) {
                            console.log('检测到相同的消息（可能是刚发送的），轮询跳过:', message.id, '内容:', message.content.substring(0, 20));
                            hasDuplicate = true;
                            break;
                        }
                    }
                    if (hasDuplicate) {
                        return;
                    }
                    
                    if (message.message_type === 'image' && !message.image_url) {
                        if (message.image_path) {
                            message.image_url = '/uploads/' + message.image_path;
                        }
                    }
                    this.appendMessageToChat(message);
                });
                
                this.lastMessageId = Math.max(...newMessages.map(msg => msg.id));
                this.scrollToBottom();
            }
        }
    } catch (error) {
        console.error('轮询消息失败:', error);
    }
}
						    
						    destroy() {
    if (this.statusCheckInterval) {
        clearInterval(this.statusCheckInterval);
    }
    if (this.heartbeatInterval) {
        clearInterval(this.heartbeatInterval);
    }
    if (this.pollingInterval) {
        clearInterval(this.pollingInterval);
    }
    
    // 新增：关闭 WebSocket
    if (this.ws) {
        this.ws.close(1000, '页面关闭');
        this.stopWebSocketHeartbeat();
    }
    
    // 发送离线状态
    this.setOfflineStatus();
}
						
						    // 新增：设置离线状态
						    async setOfflineStatus() {
						        try {
						            await $.ajax({
						                url: this.API_BASE + '?action=update_online_status',
						                method: 'POST',
						                contentType: 'application/json',
						                data: JSON.stringify({
						                    username: this.currentAgent,
						                    user_type: 'agent',
						                    is_online: false
						                }),
						                dataType: 'json',
						                timeout: 3000
						            });
						        } catch (error) {
						            console.error('设置离线状态失败:', error);
						        }
						    }
						    
						    startMessagePolling() {
    // 先停止现有的轮询
    this.stopMessagePolling();
    
    // 创建新的轮询定时器
    this.pollingInterval = setInterval(() => {
        this.pollMessages();
    }, this.currentPollingInterval);
    
    // 立即执行一次轮询
    this.pollMessages();
}
						    
						    stopMessagePolling() {
			        if (this.pollingInterval) {
			            clearInterval(this.pollingInterval);
			            this.pollingInterval = null;
			        }
			    }
			    
			    // 新增：调整轮询间隔
		
adjustPollingInterval() {
    if (this.messageFrequency > 0) {
        // 有消息时，缩短轮询间隔
        this.currentPollingInterval = Math.max(this.minPollingInterval, this.currentPollingInterval - 500);
    } else {
        // 无消息时，增加轮询间隔
        this.currentPollingInterval = Math.min(this.maxPollingInterval, this.currentPollingInterval + 500);
    }
    
    console.log('轮询间隔调整为:', this.currentPollingInterval, 'ms');

}
			    
			   // 新增：重启轮询
restartMessagePolling() {
    console.log('重启消息轮询');
    this.stopMessagePolling();
    this.startMessagePolling();
}
						    
						    appendMessageToChat(message) {
    // 去重检查:如果消息ID已存在于DOM中,则跳过
    if (message.id && !$(`[data-message-id="${message.id}"]`).length) {
        // 消息不存在,继续添加
    } else if (message.id) {
        console.log('消息已存在,跳过添加:', message.id);
        return;
    }
			        
			        const isAgent = message.speaker_type === 2;
			        const isDummy = message.speaker_type === 3;
			        const time = this.formatTime(message.created_at, true);
			        
			        this.insertDateSeparatorIfNeeded(message.created_at);
			        
			        // 检查是否为卡片消息（客服、假人模式或客户消息）
			        const cardInfo = this.isCardMessage(message.content);
			        if (cardInfo) {
			            const cardData = this.getCardContent(message.content, cardInfo);
			            const cardHtml = this.generateCardHtml(cardData, time);
			            
			            let messageClass;
			            if (isDummy) {
			                messageClass = 'XEmsg-message XEmsg-message--dummy';
			            } else if (isAgent) {
			                messageClass = 'XEmsg-message XEmsg-message--outgoing';
			            } else {
			                // 客户消息
			                messageClass = 'XEmsg-message XEmsg-message--incoming';
			            }
			            
			            const messageHtml = `
			                <div class="XEmsg-message-container" data-message-id="${message.id || 'temp_' + Date.now()}">
			                    <div class="message-checkbox-wrapper" style="position: absolute; left: -35px; top: 50%; transform: translateY(-50%); display: none; z-index: 10;">
			                        <input type="checkbox" class="message-checkbox" data-message-id="${message.id || 'temp_' + Date.now()}" style="width: 20px; height: 20px; border: 2px solid #c8c8c8; border-radius: 50%; -webkit-appearance: none; appearance: none; cursor: pointer; position: relative; background: #fff;">
			                    </div>
			                    <div class="${messageClass}">
			                        ${cardHtml}
			                    </div>
			                </div>
			            `;
			            
			            this.optimizedAppendMessage(messageHtml);
			            this.scrollToBottom();
			            return; // 直接返回
			        }
						        let messageClass, bubbleClass, avatar, name;
						        
						        if (isDummy) {
						            // 假人消息样式
						            messageClass = 'XEmsg-message XEmsg-message--dummy';
						            bubbleClass = 'XEmsg-message__bubble XEmsg-message__bubble--dummy';
						            avatar = message.dummy_avatar || this.currentDummyAvatar;
						            name = message.dummy_name || this.currentDummyName;
						        } else if (isAgent) {
						            // 客服消息样式
						            messageClass = 'XEmsg-message XEmsg-message--outgoing';
						            bubbleClass = 'XEmsg-message__bubble XEmsg-message__bubble--outgoing';
						            avatar = '/assets/img/pz-kf.png';
						            name = '我';
						        } else {
						            // 客户消息样式
						            messageClass = 'XEmsg-message XEmsg-message--incoming';
						            bubbleClass = 'XEmsg-message__bubble XEmsg-message__bubble--incoming';
						            avatar = '/assets/img/pz-yh.png';
						            name = this.currentCustomer;
						        }
						        
						        let messageContent = '';
						        
						        if (message.message_type === 'image') {
			            const imageUrl = message.image_url || (message.image_path ? `/uploads/${message.image_path}` : null);
			            if (imageUrl) {
			                // 对于临时消息，直接显示图片，不显示加载动画
			                if (message.isTemp) {
			                    messageContent = `
			                        <div class="message-image-container">
			                            <img src="${imageUrl}" alt="已发送的图片" class="message-image" 
			                                 style="max-width: 200px; max-height: 100px; border-radius: 8px; transition: opacity 0.3s ease;">
			                        </div>
			                    `;
			                } else {
			                    // 对于非临时消息，显示加载动画
			                    messageContent = `
			                        <div class="message-image-container">
			                            <div class="image-loading" style="display: flex; align-items: center; justify-content: center; width: 100px; height: 100px; border-radius: 8px; background: #f5f5f5;">
			                                <div class="loading-spinner" style="width: 20px; height: 20px; border: 2px solid #e0e0e0; border-top: 2px solid #007AFF; border-radius: 50%; animation: spin 1s linear infinite;"></div>
			                            </div>
			                            <img src="${imageUrl}" alt="已发送的图片" class="message-image" 
			                                 style="display: none; max-width: 200px; max-height: 100px; border-radius: 8px; transition: opacity 0.3s ease;">
			                            <div class="image-error" style="display: none; color: #999; font-style: italic; padding: 10px; text-align: center;">
			                                图片加载失败，<a href="${imageUrl}" target="_blank" style="color: #007AFF; text-decoration: none;">点击查看</a>
			                            </div>
			                        </div>
			                    `;
			                }
			            } else {
			                messageContent = '<span class="message-image-placeholder">[图片]</span>';
			            }
			        } else {
						            messageContent = this.escapeHtml(message.content);
						        }
						        
						        const msgId = message.id || ('temp_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9));
			        const isCustomerMsg = !isDummy && !isAgent;
			        const isImageMsg = message.message_type === 'image';
			        let messageHtml;
			        if (isImageMsg) {
			            messageHtml = `
			                <div class="XEmsg-message-container" data-message-id="${msgId}" style="position: relative; min-height: 36px;">
			                    <div class="message-checkbox-wrapper" style="position: absolute; left: -35px; top: 50%; transform: translateY(-50%); display: none; z-index: 10;">
			                        <input type="checkbox" class="message-checkbox" data-message-id="${msgId}" style="width: 20px; height: 20px; border: 2px solid #c8c8c8; border-radius: 50%; -webkit-appearance: none; appearance: none; cursor: pointer; position: relative; background: #fff;">
			                    </div>
			                    <div class="${messageClass}" style="margin-left: 0;">
			                        ${messageContent}
			                    </div>
			                </div>
			            `;
			        } else {
			            messageHtml = `
			                <div class="XEmsg-message-container" data-message-id="${msgId}" style="position: relative; min-height: 36px;">
			                    <div class="message-checkbox-wrapper" style="position: absolute; left: -35px; top: 50%; transform: translateY(-50%); display: none; z-index: 10;">
			                        <input type="checkbox" class="message-checkbox" data-message-id="${msgId}" style="width: 20px; height: 20px; border: 2px solid #c8c8c8; border-radius: 50%; -webkit-appearance: none; appearance: none; cursor: pointer; position: relative; background: #fff;">
			                    </div>
			                    <div class="${messageClass}" style="margin-left: 0;">
			                        <div class="${bubbleClass}">
			                            ${isDummy || isAgent ? `<div class="XEmsg-message__sender">${name}</div>` : ''}
			                            <span class="XEmsg-message__text">${messageContent}</span>
			                            <span class="XEmsg-message__time">
			                                ${time}
			                                ${isAgent || isDummy ? `<span class="message-status ${message.is_read ? 'read' : ''}" data-message-id="${msgId}">${message.is_read ? '<i class="bi bi-check-all"></i>' : '<i class="bi bi-check"></i>'}</span>` : ''}
			                                ${message.isFailed ? '<span class="send-failed">发送失败</span>' : ''}
			                            </span>
			                        </div>
			                    </div>
			                </div>
			            `;
			        }
			        
			        this.optimizedAppendMessage(messageHtml);
			        this.scrollToBottom();
						    }
						
						    insertDateSeparatorIfNeeded(timestamp) {
						        if (!timestamp) return;
						        const msgDate = new Date(timestamp);
						        const dateKey = msgDate.getFullYear() + '-' + (msgDate.getMonth() + 1) + '-' + msgDate.getDate();
						        
						        if (this._lastRenderedDateKey === dateKey) return;
						        
						        const existing = document.querySelector(`.date-separator[data-date-key="${dateKey}"]`);
						        if (existing) {
						            this._lastRenderedDateKey = dateKey;
						            return;
						        }
						        
						        const label = this.formatDateLabel(msgDate);
						        const separatorHtml = `<div class="date-separator" data-date-key="${dateKey}"><span class="date-text">${label}</span></div>`;
						        this.optimizedAppendMessage(separatorHtml);
						        this._lastRenderedDateKey = dateKey;
						    }
						
						    formatDateLabel(date) {
						        const now = new Date();
						        const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
						        const yesterday = new Date(today);
						        yesterday.setDate(yesterday.getDate() - 1);
						        const msgDay = new Date(date.getFullYear(), date.getMonth(), date.getDate());
						        
						        if (msgDay.getTime() === today.getTime()) return '今天';
						        if (msgDay.getTime() === yesterday.getTime()) return '昨天';
						        
						        const m = date.getMonth() + 1;
						        const d = date.getDate();
						        const y = date.getFullYear();
						        const curY = now.getFullYear();
						        
						        if (y === curY) return m + '月' + d + '日';
						        return y + '年' + m + '月' + d + '日';
						    }
						
						    // 本地存储方法
						    loadDummySettings() {
						        try {
						            const settings = localStorage.getItem('agent_dummy_settings');
						            return settings ? JSON.parse(settings) : {};
						        } catch (error) {
						            console.error('加载群聊设置失败:', error);
						            return {};
						        }
						    }
						
						    saveDummySettingsToStorage() {
						        try {
						            localStorage.setItem('agent_dummy_settings', JSON.stringify(this.dummySettings));
						        } catch (error) {
						            console.error('保存群聊设置失败:', error);
						        }
						    }
						    
						    async loadMessageHistory() {
						        try {
						            const response = await $.ajax({
						                url: '/api/chat/messages?action=get_message_history',
						                method: 'GET',
						                data: {
						                    session_id: this.currentSessionKey,
						                    last_id: this.lastMessageId
						                },
						                dataType: 'json'
						            });
						            
						            if (response.success && response.messages) {
						                response.messages.forEach(message => {
						                    // 确保图片消息正确显示
						                    if (message.message_type === 'image' && !message.image_url && message.content.includes('http')) {
						                        message.image_url = message.content;
						                    }
						                    this.appendMessageToChat(message);
						                });
						            }
						        } catch (error) {
						            console.error('加载消息历史失败:', error);
						        }
						    }
						    
						    markMessageAsFailed(tempId) {
						        
						        
						        const tempElement = $(`[data-message-id="${tempId}"]`);
						        if (tempElement.length) {
						            
						            tempElement.addClass('failed-message');
						            
						            // 添加重试按钮
						            tempElement.find('.XEmsg-message__bubble').append(`
						                <button class="retry-btn" data-temp-id="${tempId}">
						                    <i class="bi bi-arrow-repeat"></i> 重试
						                </button>
						            `);
						            
						            // 添加重试事件监听
						            $(document).on('click', `.retry-btn[data-temp-id="${tempId}"]`, (e) => {
						                e.stopPropagation();
						                this.retryFailedMessage(tempId);
						            });
						        } else {
						            console.warn(`未找到临时消息元素: ${tempId}`);
						            // 创建新的失败提示
						            this.appendMessageToChat({
						                id: 'error_' + Date.now(),
						                speaker_type: 2,
						                content: '消息发送失败',
						                created_at: new Date().toISOString(),
						                isError: true
						            });
						        }
						    }
						    
						    // 新增：重试失败的消息
						    async retryFailedMessage(tempId) {
						        const tempElement = $(`[data-message-id="${tempId}"]`);
						        if (!tempElement.length) return;
						        
						        const messageContent = tempElement.find('.XEmsg-message__text').text();
						        const messageType = tempElement.find('.message-image').length > 0 ? 'image' : 'text';
						        
						        // 移除失败标记
						        tempElement.removeClass('failed-message');
						        tempElement.find('.retry-btn').remove();
						        
						        if (messageType === 'text') {
						            await this.sendMessageDirectly(messageContent);
						        } else {
						            // 对于图片消息，需要重新获取文件并上传
						            this.showToast('图片消息暂不支持重试，请重新发送');
						        }
						    }
						showPhrasesPanel() {
						    const modal = $('#phrases-modal');
						    const button = $('#phrases-btn');
						    
						    if (modal.hasClass('show')) {
						        this.hidePhrasesModal();
						        return;
						    }
						    
						    modal.addClass('show');
						    button.addClass('active');
						    
						    if (!$('.phrase-group-header').hasClass('initialized')) {
						        this.initPhraseGroups();
						        $('.phrase-group-header').addClass('initialized');
						    }
						}
						
						hidePhrasesModal() {
						    $('#phrases-modal').removeClass('show');
						    $('#phrases-btn').removeClass('active');
						}
						
						updatePhrasesPanelPosition() {
						    const panel = $('#phrases-panel');
						    const button = $('#phrases-btn');
						    
						    if (!panel.is(':visible')) return;
						    
						    const buttonRect = button[0].getBoundingClientRect();
						    const panelWidth = panel.outerWidth() || 280;
						    const panelHeight = panel.outerHeight() || 300;
						    const windowWidth = window.innerWidth;
						    const windowHeight = window.innerHeight;
						    
						    // 水平位置计算
						    let left = buttonRect.left - (panelWidth - buttonRect.width) / 2;
						    if (left + panelWidth > windowWidth) {
						        left = windowWidth - panelWidth - 10;
						    }
						    if (left < 10) {
						        left = 10;
						    }
						    
						    // 垂直位置计算 - 使用top定位
						    let top = buttonRect.bottom + 10;
						    if (top + panelHeight > windowHeight) {
						        top = buttonRect.top - panelHeight - 10;
						    }
						    
						    panel.css({
						        'left': left + 'px',
						        'top': top + 'px' // 使用top定位
						    });
						}
						    
						    showCustomerNoteModal() {
						        const modal = $('#remarkModal');
						        const currentNote = this.customerNotes[this.currentCustomer] || '';
						        
						        // 更新模态框内容
						        $('#customer-note-input').val(currentNote).focus();
						        
						        // 显示模态框
						        modal.addClass('active');
						    }
						    
						    saveCustomerNote() {
			        const note = $('#customer-note-input').val().trim();
			        
			        if (!this.currentCustomer) {
			            this.showToast('无法保存备注：客户信息丢失');
			            return;
			        }
			        
			        // 保存到本地存储
			        this.customerNotes[this.currentCustomer] = note;
			        this.saveCustomerNotes();
			        
			        // 更新备注显示
			        this.updateCustomerNote();
			        this.updateNoteDisplay(); // 新增：更新标题栏中的备注显示
			        
			        // 关闭模态框
			        $('#remarkModal').removeClass('active');
			        
			        // 显示提示
			        this.showToast('备注保存成功');
			    }
						    
						    clearCustomerNote() {
			        if (!this.currentCustomer) {
			            this.showToast('无法清空备注：客户信息丢失');
			            return;
			        }
			        
			        if (!confirm('确定要清空此客户的备注吗？')) {
			            return;
			        }
			        
			        // 从本地存储删除
			        delete this.customerNotes[this.currentCustomer];
			        this.saveCustomerNotes();
			        
			        // 更新备注显示
			        this.updateCustomerNote();
			        this.updateNoteDisplay(); // 新增：更新标题栏中的备注显示
			        
			        // 清空输入框
			        $('#customer-note-input').val('');
			        
			        // 显示提示
			        this.showToast('备注已清空');
			    }
						    
						    updateCustomerNote() {
						        const hasNote = this.customerNotes[this.currentCustomer] || '';
						        if (hasNote) {
						            $('#customer-note-btn i').css('color', '#007AFF');
						        } else {
						            $('#customer-note-btn i').css('color', '#666');
						        }
						    }
						// 修改获取客户完整信息
			async getCustomerFullInfo() {
			    // 确保参数存在
			    if (!this.currentCustomer) {
			        console.error('❌ 无法获取客户信息：客户名称为空');
			        return null;
			    }
			    
			    if (!this.agentAccount) {
			        this.agentAccount = '<?php echo $currentAgent; ?>';
			        console.log('当前客服账号:', this.agentAccount);
			    }
			    
			    console.log('🔍 获取客户信息，参数:', {
			        客户: this.currentCustomer,
			        客服: this.agentAccount
			    });
			    
			    try {
			        const response = await $.ajax({
			            url: `${this.API_BASE}?action=get_customer_full_info`,
			            method: 'GET',
			            data: {
			                customer_name: this.currentCustomer,
			                agent_account: this.agentAccount
			            },
			            dataType: 'json',
			            timeout: 5000
			        });
			        
			        console.log('📥 客户信息响应:', response);
			        
			        if (response.success && response.customer_info) {
			            const info = response.customer_info;
			            console.log('✅ 成功获取客户信息:');
			            console.log('  IP地址:', info.client_ip);
			            console.log('  归属地:', info.ip_location);
			            console.log('  IP类型:', info.ip_type);
			            return info;
			        } else {
			            console.warn('⚠️ 获取客户信息失败:', response.message);
			        }
			    } catch (error) {
			        console.error('❌ 获取客户完整信息失败:', error);
			    }
			    
			    return null;
			}
						    
					// 修改详情信息模态框显示函数
			async showInfoModal() {
			    const self = this;
			    
			    // 获取客户完整信息
			    const customerInfo = await this.getCustomerFullInfo();
			    
			    // 动态计算消息总数
			    const messageContainers = $('.XEmsg-message-container');
			    const messageCount = messageContainers.length;
			    
			    // 获取最后活动时间
			    let lastActivity = '暂无';
			    if (messageCount > 0) {
			        const lastMessage = messageContainers.last();
			        const timeElement = lastMessage.find('.XEmsg-message__time span:first');
			        if (timeElement.length) {
			            lastActivity = timeElement.text();
			        }
			    }
			    
			    // 更新模态框内容
			    const infoModal = $('#infoModal');
			    
			    // 清空现有内容
			    infoModal.find('.info-grid').empty();
			    
			    // 只创建四个信息项
			    const infoItems = [
			        { 
			            label: '客户名称', 
			            value: this.currentCustomer || '未知' 
			        },
			        { 
			            label: '消息总数', 
			            value: messageCount 
			        },
			        { 
			            label: '最后活动', 
			            value: lastActivity 
			        },
			        { 
			            label: '设备类型', 
			            value: this.getDeviceTypeText(customerInfo?.device_type || 'desktop') 
			        }
			    ];
			    
			    // 添加信息项
			    infoItems.forEach(item => {
			        infoModal.find('.info-grid').append(`
			            <div class="info-item">
			                <div class="info-label">${item.label}</div>
			                <div class="info-value">${item.value}</div>
			            </div>
			        `);
			    });
			    
			    // 显示模态框
			    infoModal.addClass('active');
			}
			
			// 新增：获取IP类型
			getIPType(ip) {
			    if (!ip || ip === '0.0.0.0' || ip === '未知') {
			        return '未知';
			    }
			    
			    // IPv4判断
			    if (/^(\d{1,3}\.){3}\d{1,3}$/.test(ip)) {
			        return 'IPv4';
			    }
			    
			    // IPv6判断
			    if (/^([0-9a-fA-F]{1,4}:){7}[0-9a-fA-F]{1,4}$/.test(ip)) {
			        return 'IPv6';
			    }
			    
			    return '未知';
			}
			
			// 新增：获取状态文本
			getStatusText(status) {
			    switch(status) {
			        case 'online': return '在线';
			        case 'hidden': return '隐藏中';
			        case 'away': return '离开';
			        case 'offline': return '离线';
			        default: return '未知';
			    }
			}
			
			// 新增：设备信息管理
			setupDeviceInfo() {
			    const self = this;
			    
			    // 初始加载设备信息
			    this.loadDeviceInfo();
			}
			
			// 新增：加载设备信息并更新图标
			async loadDeviceInfo() {
			    if (!this.currentCustomer) return;
			    
			    try {
			        const response = await $.ajax({
			            url: `${this.API_BASE}?action=get_user_device_info`,
			            method: 'POST',
			            contentType: 'application/json',
			            data: JSON.stringify({
			                username: this.currentCustomer,
			                user_type: 'customer'
			            }),
			            dataType: 'json',
			            timeout: 5000
			        });
			        
			        if (response.success && response.device_info) {
			            this.customerDeviceInfo = response.device_info;
			            // 更新客户名旁边的设备图标
			            this.updateDeviceIcon(response.device_info);
			        }
			    } catch (error) {
			        console.error('加载设备信息失败:', error);
			    }
			}
			
			// 新增：更新客户名旁边的设备图标
			updateDeviceIcon(deviceInfo) {
			    const deviceTypeIcon = $('#device-type-icon');
			    if (deviceTypeIcon.length) {
			        const deviceType = deviceInfo.device_type || 'desktop';
			        
			        // 根据设备类型设置图标
			        deviceTypeIcon.removeClass('bi-phone bi-tablet bi-laptop');
			        if (deviceType === 'mobile') {
			            deviceTypeIcon.addClass('bi-phone');
			        } else if (deviceType === 'tablet') {
			            deviceTypeIcon.addClass('bi-tablet');
			        } else {
			            deviceTypeIcon.addClass('bi-laptop');
			        }
			        
			        // 显示图标
			        deviceTypeIcon.css('display', 'inline-block');
			    }
			}
			
			// 获取设备类型文本
			getDeviceTypeText(deviceType) {
			    if (!deviceType || deviceType === null || deviceType === undefined || deviceType === '') {
			        return '电脑';
			    }
			    
			    const type = String(deviceType).toLowerCase().trim();
			    
			    switch(type) {
			        case 'mobile': return '手机';
			        case 'tablet': return '平板';
			        case 'desktop': return '电脑';
			        case 'pc': return 'PC端';
			        case 'phone': return '手机';
			        case 'laptop': return '电脑';
			        case 'unknown': 
			        case 'null': 
			        case 'undefined': 
			        case '': return '电脑';
			        default: return '电脑';
			    }
			}
			
						    
						    showDeleteModal() {
						        $('#deleteModal').addClass('active');
						    }
						    
						    async toggleSessionSetting(switchId, settingKey) {
						        if (!this.currentSessionKey || !this.currentAgent) {
						            this.showToast('无法更新设置：会话信息不完整');
						            return;
						        }
						        
						        const isChecked = document.getElementById(switchId).checked;
						        const action = settingKey === 'is_pinned' ? 'toggle_pin' : 'toggle_mute';
						        const paramName = settingKey === 'is_pinned' ? 'pin' : 'mute';
						        
						        try {
						            const response = await fetch('/api/msg/?action=' + action, {
						                method: 'POST',
						                headers: {
						                    'Content-Type': 'application/json',
						                },
						                body: JSON.stringify({
						                    session_key: this.currentSessionKey,
						                    [paramName]: isChecked
						                })
						            });
						            
						            const data = await response.json();
						            if (data.success) {
						                this.sessionSettings[settingKey] = isChecked;
						                this.showToast(data.message);
						            } else {
						                throw new Error(data.message || '更新失败');
						            }
						        } catch (error) {
						            console.error('更新设置失败:', error);
						            // 回滚开关状态
						            document.getElementById(switchId).checked = !isChecked;
						            this.showToast('更新失败: ' + error.message);
						        }
						    }
						    
						    async confirmDelete() {
						        try {
						            // 确保会话信息完整
						            if (!this.currentSessionKey || !this.currentAgent) {
						                this.showToast('无法清空记录：缺少会话信息');
						                return;
						            }
						            
						            const response = await $.ajax({
						                url: this.API_BASE + '?action=clear_chat_messages',
						                method: 'POST',
						                contentType: 'application/json',
						                data: JSON.stringify({
						                    session_key: this.currentSessionKey,
						                    agent_account: this.currentAgent
						                }),
						                dataType: 'json',
						                timeout: 10000
						            });
						            
						            if (response.success) {
						                $('#messages-container').empty();
						                this.lastMessageId = 0;
						                this.showToast('聊天记录已清空');
						                $('#deleteModal').removeClass('active');
						            } else {
						                throw new Error(response.message || '清空失败');
						            }
						        } catch (error) {
						            console.error('清空聊天记录失败:', {
						                error: error,
						                status: error.status,
						                responseText: error.responseText
						            });
						            
						            this.showToast('清空失败: ' + (error.responseJSON?.message || error.statusText || error.message));
						        }
						    }
						    
						    startSelectionMode() {
						        const self = this;
						        $('#deleteToolbar').show();
						        $('.message-checkbox-wrapper').show();
						        $('#messages-container').css('padding-left', '50px');
						        
						        $('#selectAllCheckbox').on('change', function() {
						            const isChecked = $(this).prop('checked');
						            $('.message-checkbox').prop('checked', isChecked);
						            self.updateSelectedCount();
						        });
						        
						        $('.message-checkbox').on('change', function() {
						            self.updateSelectedCount();
						        });
						        
						        $('#cancelSelectionBtn').on('click', function() {
						            self.cancelSelectionMode();
						        });
						        
						        $('#deleteSelectedBtn').on('click', function() {
						            self.showDeleteSelectedModal();
						        });
						    }
						    
						    cancelSelectionMode() {
						        $('#deleteToolbar').hide();
						        $('.message-checkbox-wrapper').hide();
						        $('#messages-container').css('padding-left', '');
						        $('.message-checkbox').prop('checked', false);
						        $('#selectAllCheckbox').prop('checked', false);
						        $('#selectedCount').text('已选择 0 条');
						    }
						    
						    updateSelectedCount() {
						        const selectedCount = $('.message-checkbox:checked').length;
						        $('#selectedCount').text('已选择 ' + selectedCount + ' 条');
						    }
						    
						    showDeleteSelectedModal() {
						        const selectedCount = $('.message-checkbox:checked').length;
						        if (selectedCount === 0) {
						            this.showToast('请先选择要删除的消息');
						            return;
						        }
						        $('#deleteSelectedCount').text('此操作将永久删除选中的 ' + selectedCount + ' 条消息!并且无法恢复.您确定要继续吗？');
						        $('#deleteSelectedModal').addClass('active');
						    }
						    
						    async confirmDeleteSelected() {
						        try {
						            if (!this.currentSessionKey || !this.currentAgent) {
						                this.showToast('无法删除：缺少会话信息');
						                return;
						            }
						            
						            const selectedIds = $('.message-checkbox:checked').map(function() {
						                return $(this).data('message-id');
						            }).get();
						            
						            if (selectedIds.length === 0) {
						                this.showToast('请先选择要删除的消息');
						                return;
						            }
						            
						            const response = await $.ajax({
						                url: this.API_BASE + '?action=delete_selected_messages',
						                method: 'POST',
						                contentType: 'application/json',
						                data: JSON.stringify({
						                    session_key: this.currentSessionKey,
						                    agent_account: this.currentAgent,
						                    message_ids: selectedIds
						                }),
						                dataType: 'json',
						                timeout: 10000
						            });
						            
						            if (response.success) {
						              
						                selectedIds.forEach(function(id) {
						                    $('[data-message-id="' + id + '"]').closest('.XEmsg-message-container').remove();
						                });
						                this.showToast('已删除 ' + selectedIds.length + ' 条消息');
						                $('#deleteSelectedModal').removeClass('active');
						                this.cancelSelectionMode();
						               // 通知父窗口刷新会话列表
						                if (window.parent && window.parent !== window) {
						                    window.parent.postMessage({
						                        type: 'REFRESH_SESSION_LIST'
						                    }, '*');
						                }
						                
						                // 检查是否还有消息剩余（通过检查聊天容器内的消息数量）
						               this.checkSessionEmpty(this.currentSessionKey);
						            } else {
						                throw new Error(response.message || '删除失败');
						            }
						        } catch (error) {
						            console.error('删除消息失败:', error);
						            this.showToast('删除失败: ' + (error.responseJSON?.message || error.statusText || error.message));
						        }
						    }
						    
						      // 检查会话是否为空
						    async checkSessionEmpty(sessionKey) {
						          // 等待 300ms 确保数据库已更新
						        await new Promise(resolve => setTimeout(resolve, 300));
						        
						        try {
						             console.log('🔍 检查会话是否为空:', sessionKey);
						            const response = await $.ajax({
						                url: '/api/chat/messages?action=get_messages',
						                method: 'GET',
						                data: {
						                      session_key: sessionKey
						                },
						                dataType: 'json'
						            });
						               console.log('📊 API 返回结果:', response);
						            
						            if (response.success && response.messages && response.messages.length === 0) {
						                // 会话已空，从聊天列表移除
						                console.log('✅ 会话已空，准备移除:', sessionKey);
						                this.removeSessionFromList(sessionKey);
						            } else {
						                console.log('📋 会话还有消息:', response.messages ? response.messages.length : 0);
						            }
						        } catch (error) {
						            console.error('检查会话是否为空失败:', error);
						        }
						    }
						    
						    // 加载客户备注
						    loadCustomerNotes() {
						        try {
						            const notes = localStorage.getItem('agent_customer_notes');
						            return notes ? JSON.parse(notes) : {};
						        } catch (error) {
						            console.error('加载客户备注失败:', error);
						            return {};
						        }
						    }
						    
						    saveCustomerNotes() {
						        try {
						            localStorage.setItem('agent_customer_notes', JSON.stringify(this.customerNotes));
						        } catch (error) {
						            console.error('保存客户备注失败:', error);
						        }
						    }
						    
						    updateSendButton() {
			    const hasText = $('#message-input').val().trim().length > 0;
			    const isSending = this.isSending || this.isUploadingImage;
			    $('#send-btn').prop('disabled', !hasText || isSending);
			    
			    if (hasText && !isSending) {
			        $('#send-btn').addClass('XEmsg-send-button--visible');
			    } else {
			        $('#send-btn').removeClass('XEmsg-send-button--visible');
			    }
			    
			    // 同步更新AI按钮状态
			    this.updateAIBtnVisibility();
			}
						    
						    autoResizeTextarea() {
						        const textarea = $('#message-input')[0];
						        if (textarea) {
						            textarea.style.height = 'auto';
						            textarea.style.height = Math.min(textarea.scrollHeight, 120) + 'px';
						        }
						    }
						    
						    scrollToBottom() {
			        const container = $('#messages-container');
			        if (!container.length || this.isScrolling) return;
			        
			        // 节流处理，避免过于频繁的滚动操作
			        const now = Date.now();
			        if (now - this.lastScrollTime < this.scrollThreshold) {
			            // 使用setTimeout延迟执行，避免堆叠
			            if (!this.scrollTimeout) {
			                this.scrollTimeout = setTimeout(() => {
			                    this.performScrollToBottom();
			                }, this.scrollThreshold);
			            }
			            return;
			        }
			        
			        this.performScrollToBottom();
			    }
			    
			    // 执行滚动到底部操作
			    performScrollToBottom() {
			        this.isScrolling = true;
			        this.lastScrollTime = Date.now();
			        
			        const container = $('#messages-container');
			        if (container.length) {
			            const containerElement = container[0];
			            
			            // 使用requestAnimationFrame优化滚动
			            requestAnimationFrame(() => {
			                // 直接操作DOM属性，避免jQuery的额外开销
			                containerElement.scrollTop = containerElement.scrollHeight;
			                
			                // 滚动完成后释放标记
			                setTimeout(() => {
			                    this.isScrolling = false;
			                    this.scrollTimeout = null;
			                }, 50);
			            });
			        } else {
			            this.isScrolling = false;
			            this.scrollTimeout = null;
			        }
			    }
			    
			    // 批量更新消息容器
			    batchUpdateMessages(updater) {
			        const container = $('#messages-container');
			        if (!container.length) return;
			        
			        // 使用requestAnimationFrame批量处理DOM更新
			        requestAnimationFrame(() => {
			            updater(container);
			        });
			    }
			    
			    // 优化的消息追加方法
			    optimizedAppendMessage(messageHtml) {
			        this.batchUpdateMessages((container) => {
			            // 使用DocumentFragment减少重排
			            const fragment = document.createDocumentFragment();
			            const tempDiv = document.createElement('div');
			            tempDiv.innerHTML = messageHtml;
			            
			            while (tempDiv.firstChild) {
			                fragment.appendChild(tempDiv.firstChild);
			            }
			            
			            container[0].appendChild(fragment);
			        });
			    }
			    
			    // 优化图片加载
			    optimizeImageLoading() {
			        // 处理单个图片的加载状态
			        const processSingleImage = (imgElement) => {
			            if (!imgElement) return;
			            
			            const img = imgElement;
			            const $img = $(img);
			            const $loading = $img.siblings('.image-loading');
			            const $error = $img.siblings('.image-error');
			            
			            if (img.complete) {
			                if (img.naturalWidth > 0) {
			                    // 图片加载成功
			                    $img.show();
			                    if ($loading.length) {
			                        $loading.hide();
			                    }
			                    if ($error.length) {
			                        $error.hide();
			                    }
			                } else {
			                    // 图片加载失败
			                    $img.hide();
			                    if ($loading.length) {
			                        $loading.hide();
			                    }
			                    if ($error.length) {
			                        $error.show();
			                    }
			                }
			            } else {
			                // 图片正在加载，确保加载动画显示
			                if ($loading.length) {
			                    $loading.show();
			                }
			                $img.hide();
			                if ($error.length) {
			                    $error.hide();
			                }
			            }
			        };
			        
			        // 处理已经加载的图片
			        const processLoadedImages = () => {
			            $('.message-image').each(function() {
			                processSingleImage(this);
			            });
			        };
			        
			        // 为单个图片绑定加载事件
			        const bindImageEvents = (imgElement) => {
			            if (!imgElement) return;
			            
			            const $img = $(imgElement);
			            
			            // 移除已有的事件监听器，避免重复绑定
			            $img.off('load error');
			            
			            // 绑定load事件
			            $img.on('load', function() {
			                $(this).show();
			                $(this).siblings('.image-loading').hide();
			                $(this).siblings('.image-error').hide();
			            });
			            
			            // 绑定error事件
			            $img.on('error', function() {
			                $(this).hide();
			                $(this).siblings('.image-loading').hide();
			                $(this).siblings('.image-error').show();
			            });
			        };
			        
			        // 为所有图片绑定加载事件
			        const bindAllImageEvents = () => {
			            $('.message-image').each(function() {
			                bindImageEvents(this);
			            });
			        };
			        
			        // 立即处理一次
			        processLoadedImages();
			        bindAllImageEvents();
			        
			        // 延迟处理，确保所有图片都已处理
			        setTimeout(() => {
			            processLoadedImages();
			            bindAllImageEvents();
			        }, 50);
			        
			        // 再次延迟处理，确保异步加载的图片也能被处理
			        setTimeout(() => {
			            processLoadedImages();
			            bindAllImageEvents();
			        }, 100);
			        
			        // 第三次延迟处理，确保所有图片都已加载完成
			        setTimeout(() => {
			            processLoadedImages();
			        }, 200);
			        
			        // 监听DOM变化，处理新添加的图片
			        const observer = new MutationObserver(function(mutations) {
			            mutations.forEach(function(mutation) {
			                if (mutation.addedNodes.length > 0) {
			                    // 立即处理新添加的图片
			                    mutation.addedNodes.forEach(function(node) {
			                        if (node.nodeType === Node.ELEMENT_NODE) {
			                            // 检查是否包含图片
			                            const images = node.querySelectorAll('.message-image');
			                            images.forEach(function(img) {
			                                // 立即处理图片加载状态
			                                processSingleImage(img);
			                                // 绑定事件监听器
			                                bindImageEvents(img);
			                                
			                                // 立即触发一次图片加载状态检查
			                                setTimeout(() => {
			                                    processSingleImage(img);
			                                }, 10);
			                            });
			                        }
			                    });
			                }
			            });
			        });
			        
			        // 观察消息容器的变化
			        const messagesContainer = document.getElementById('messages-container');
			        if (messagesContainer) {
			            observer.observe(messagesContainer, {
			                childList: true,
			                subtree: true
			            });
			        }
			        
			        // 为了确保万无一失，每500毫秒检查一次所有图片的加载状态
			        setInterval(() => {
			            processLoadedImages();
			        }, 500);
			    }
			    
			    // 预加载图片
			    preloadImage(imageUrl) {
			        if (!imageUrl) return;
			        
			        const img = new Image();
			        img.src = imageUrl;
			        img.onload = () => {
			            console.log('图片预加载完成:', imageUrl);
			        };
			        img.onerror = () => {
			            console.warn('图片预加载失败:', imageUrl);
			        };
			    }
			    
			    // 批量预加载图片
			    preloadImages(imageUrls) {
			        if (!Array.isArray(imageUrls)) return;
			        
			        // 限制并发预加载数量
			        const maxConcurrent = 3;
			        let currentIndex = 0;
			        
			        const loadNext = () => {
			            if (currentIndex >= imageUrls.length) return;
			            
			            const imageUrl = imageUrls[currentIndex++];
			            this.preloadImage(imageUrl);
			            
			            if (currentIndex < imageUrls.length) {
			                setTimeout(loadNext, 100);
			            }
			        };
			        
			        // 启动并发加载
			        for (let i = 0; i < maxConcurrent && i < imageUrls.length; i++) {
			            loadNext();
			        }
			    }

			    
			    // 新增：缓存消息
			    cacheMessages(sessionKey, messages) {
			        if (!sessionKey || !messages || !Array.isArray(messages)) return;
			        
			        const cacheKey = `messages_${sessionKey}`;
			        const cacheData = {
			            messages: messages,
			            timestamp: Date.now(),
			            accessCount: 0 // 访问次数
			        };
			        
			        // 移除旧的缓存键（如果存在）
			        if (this.messageCache.has(cacheKey)) {
			            const index = this.cacheAccessOrder.indexOf(cacheKey);
			            if (index > -1) {
			                this.cacheAccessOrder.splice(index, 1);
			            }
			        }
			        
			        // 添加到缓存
			        this.messageCache.set(cacheKey, cacheData);
			        this.cacheAccessOrder.push(cacheKey);
			        
			        // 检查缓存大小，超过限制时移除最久未使用的
			        this.trimCache();
			        
			        // 清理过期缓存
			        this.clearExpiredCache();
			        
			        this.lastCacheUpdate = Date.now();
			    }
			    
			    // 新增：获取缓存消息
			    getCachedMessages(sessionKey) {
			        if (!sessionKey) return null;
			        
			        const cacheKey = `messages_${sessionKey}`;
			        const cachedData = this.messageCache.get(cacheKey);
			        
			        if (!cachedData) {
			            this.cacheMisses++;
			            return null;
			        }
			        
			        // 检查缓存是否过期
			        if (Date.now() - cachedData.timestamp > this.cacheExpiry) {
			            this.messageCache.delete(cacheKey);
			            const index = this.cacheAccessOrder.indexOf(cacheKey);
			            if (index > -1) {
			                this.cacheAccessOrder.splice(index, 1);
			            }
			            this.cacheMisses++;
			            return null;
			        }
			        
			        // 更新访问时间和顺序（LRU策略）
			        cachedData.timestamp = Date.now();
			        cachedData.accessCount++;
			        this.messageCache.set(cacheKey, cachedData);
			        
			        // 移到访问顺序的末尾（最近使用）
			        const index = this.cacheAccessOrder.indexOf(cacheKey);
			        if (index > -1) {
			            this.cacheAccessOrder.splice(index, 1);
			        }
			        this.cacheAccessOrder.push(cacheKey);
			        
			        this.cacheHits++;
			        return cachedData.messages;
			    }
			    
			    // 新增：检查缓存是否有效
			    isCacheValid(sessionKey) {
			        if (!sessionKey) return false;
			        
			        const cacheKey = `messages_${sessionKey}`;
			        const cachedData = this.messageCache.get(cacheKey);
			        
			        if (!cachedData) return false;
			        
			        return Date.now() - cachedData.timestamp <= this.cacheExpiry;
			    }
			    
			    // 新增：清除过期缓存
			    clearExpiredCache() {
			        const now = Date.now();
			        const expiredKeys = [];
			        
			        for (const [key, data] of this.messageCache.entries()) {
			            if (now - data.timestamp > this.cacheExpiry) {
			                expiredKeys.push(key);
			            }
			        }
			        
			        // 批量删除过期缓存
			        expiredKeys.forEach(key => {
			            this.messageCache.delete(key);
			            const index = this.cacheAccessOrder.indexOf(key);
			            if (index > -1) {
			                this.cacheAccessOrder.splice(index, 1);
			            }
			        });
			        
			        // 定期清理缓存
			        if (now - this.lastCacheUpdate > 60000) { // 每分钟清理一次
			            this.lastCacheUpdate = now;
			        }
			    }
			    
			    // 新增：裁剪缓存到最大大小（LRU策略）
			    trimCache() {
			        while (this.messageCache.size > this.maxCacheSize && this.cacheAccessOrder.length > 0) {
			            // 移除最久未使用的缓存（访问顺序的第一个）
			            const oldestKey = this.cacheAccessOrder.shift();
			            if (oldestKey) {
			                this.messageCache.delete(oldestKey);
			            }
			        }
			    }
			    
			    // 新增：获取缓存统计信息
			    getCacheStats() {
			        return {
			            size: this.messageCache.size,
			            maxSize: this.maxCacheSize,
			            hits: this.cacheHits,
			            misses: this.cacheMisses,
			            hitRate: this.cacheHits + this.cacheMisses > 0 ? 
			                (this.cacheHits / (this.cacheHits + this.cacheMisses) * 100).toFixed(2) : '0.00'
			        };
			    }
			    
			    // 新增：优化的网络请求方法
			    async optimizedRequest(url, options) {
			        options = options || {};
			        const data = options.data || {};
			        const method = options.method || 'GET';
			        const cacheable = options.cacheable !== undefined ? options.cacheable : true;
			        const throttle = options.throttle || false;
			        
			        // 生成请求键（用于节流和缓存）
			        const requestKey = `${url}_${method}_${JSON.stringify(data)}`;
			        
			        // 检查是否有相同的请求正在进行
			        const pendingRequest = this.requestManager.getPendingRequest(url, data);
			        if (pendingRequest) {
			            return pendingRequest;
			        }
			        
			        // 检查缓存
			        if (cacheable) {
			            const cachedResult = this.requestManager.getCachedRequest(url, data);
			            if (cachedResult) {
			                return cachedResult;
			            }
			        }
			        
			        // 应用节流
			        if (throttle) {
			            const throttleKey = `throttle_${requestKey}`;
			            if (this.requestThrottles.has(throttleKey)) {
			                clearTimeout(this.requestThrottles.get(throttleKey));
			            }
			            
			            return new Promise((resolve, reject) => {
			                const timeoutId = setTimeout(async () => {
			                    this.requestThrottles.delete(throttleKey);
			                    try {
			                        const result = await this.performRequest(url, options);
			                        resolve(result);
			                    } catch (error) {
			                        reject(error);
			                    }
			                }, this.throttlingDelay);
			                
			                this.requestThrottles.set(throttleKey, timeoutId);
			            });
			        }
			        
			        // 执行请求
			        return this.performRequest(url, options);
			    }
			    
			    // 新增：执行实际的网络请求
			    async performRequest(url, options) {
			        options = options || {};
			        const data = options.data || {};
			        const method = options.method || 'GET';
			        const cacheable = options.cacheable !== undefined ? options.cacheable : true;
			        
			        // 创建请求配置
			        const requestConfig = $.extend({
			            url: url,
			            method: method,
			            dataType: 'json',
			            timeout: options.timeout || 10000
			        }, options);
			        
			        if (method === 'POST' && !requestConfig.contentType) {
			            requestConfig.contentType = 'application/json';
			            requestConfig.data = JSON.stringify(data);
			        } else if (method === 'GET') {
			            requestConfig.data = data;
			        }
			        
			        // 创建请求Promise
			        const requestPromise = new Promise((resolve, reject) => {
			            $.ajax(requestConfig)
			                .done((response) => {
			                    // 缓存结果
			                    if (cacheable && response.success) {
			                        this.requestManager.cacheRequestResult(url, data, response);
			                    }
			                    resolve(response);
			                })
			                .fail((xhr, status, error) => {
			                    reject({ xhr, status, error });
			                })
			                .always(() => {
			                    // 移除待处理请求
			                    this.requestManager.removePendingRequest(url, data);
			                });
			        });
			        
			        // 添加到待处理请求
			        this.requestManager.addPendingRequest(url, data, requestPromise);
			        
			        return requestPromise;
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
						    
						    formatTime(timestamp, showSeconds = false) {
			        if (!timestamp) return '';
			        
			        const date = new Date(timestamp);
			        const h = String(date.getHours()).padStart(2, '0');
			        const m = String(date.getMinutes()).padStart(2, '0');
			        return h + ':' + m;
			    }
			    
			    // 清理时间缓存
			    cleanupTimeCache() {
			        const now = Date.now();
			        for (const [key, value] of this.timeFormatCache.entries()) {
			            if (now - value.timestamp > this.timeCacheExpiry) {
			                this.timeFormatCache.delete(key);
			            }
			        }
			        this.lastTimeCacheCleanup = now;
			    }
						    
						    showToast(message) {
						        const $toast = $(`
						            <div class="toast-message">
						                ${this.escapeHtml(message)}
						            </div>
						        `);
						        
						        $('body').append($toast);
						        
						        setTimeout(() => {
						            $toast.fadeOut(300, function() {
						                $(this).remove();
						            });
						        }, 3000);
						    }
						      
						    // 从聊天列表中移除会话
						    removeSessionFromList(sessionKey) {
						        try {
						            // 通知父窗口（homepage.php）移除该会话
						            if (window.parent && window.parent !== window) {
						                window.parent.postMessage({
						                    type: 'REMOVE_SESSION',
						                    sessionKey: sessionKey
						                }, '*');
						            }
						            
						            // 如果在 iframe 中，也通知一下
						            if (window.top && window.top !== window) {
						                window.top.postMessage({
						                    type: 'REMOVE_SESSION',
						                    sessionKey: sessionKey
						                }, '*');
						            }
						            
						            console.log('🗑️ 已从聊天列表移除会话:', sessionKey);
						        } catch (e) {
						            console.error('移除会话失败:', e);
						        }
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
						            
						        }
						    }
						
						// ==================== WebSocket 相关方法 ====================

/**
 * 初始化 WebSocket
 */

initWebSocket() {
    
    // 如果已经连接或正在连接，不再重复连接
    if (this.ws) {
        const state = this.ws.readyState;
        console.log('当前 WebSocket 状态:', {
            CONNECTING: WebSocket.CONNECTING,
            OPEN: WebSocket.OPEN,
            CLOSING: WebSocket.CLOSING,
            CLOSED: WebSocket.CLOSED,
            currentState: state
        });
        
        if (state === WebSocket.OPEN || state === WebSocket.CONNECTING) {
            console.log('WebSocket 已连接或正在连接，跳过');
            return;
        }
    }
    
    // 清理旧的连接
    if (this.ws) {
        console.log('清理旧的 WebSocket 连接');
        this.ws.onopen = null;
        this.ws.onmessage = null;
        this.ws.onerror = null;
        this.ws.onclose = null;
        try {
            this.ws.close();
        } catch (e) {}
        this.ws = null;
    }
    
    const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
    const wsUrl = `${protocol}//${window.location.host}/wss`;
    
    console.log('当前 WebSocket URL:', wsUrl);
    
    try {
        this.ws = new WebSocket(wsUrl);
        this.wsConnectionStatus = 'connecting';
        this.updateConnectionStatus();
        
        // 设置事件处理器
        this.ws.onopen = (event) => {
            console.log('✅ WebSocket 连接成功');
            this.handleWebSocketOpen(event);
        };
        
        this.ws.onmessage = (event) => {
            this.handleWebSocketMessage(event);
        };
        
        this.ws.onerror = (event) => {
            console.error('❌ WebSocket 连接错误');
            this.handleWebSocketError(event);
        };
        
        this.ws.onclose = (event) => {
            console.log('🔌 WebSocket 连接关闭', event.code, event.reason);
            this.handleWebSocketClose(event);
        };
        
        console.log('✅ WebSocket 对象已创建:', this.ws);
        
    } catch (error) {
        console.error('❌ 创建 WebSocket 连接失败:', error);
        this.wsConnectionStatus = 'error';
        this.updateConnectionStatus();
        this.scheduleWebSocketReconnect();
    }
}

/**
 * 处理 WebSocket 连接打开
 */
/**
 * 处理 WebSocket 连接打开
 */
handleWebSocketOpen(event) {
    console.log('🎉 WebSocket 连接已打开，准备身份验证');
    console.log('当前 WebSocket 状态:', this.ws.readyState);
    
    this.wsConnected = true;
    this.wsConnectionStatus = 'connected';
    this.wsReconnectAttempts = 0;
    this.updateConnectionStatus();
    
    // 发送身份验证
    setTimeout(() => {
        this.sendWebSocketAuth();
    }, 100);
    
    // 开始心跳检测
    this.startWebSocketHeartbeat();
    
    // 发送消息队列中的消息
    setTimeout(() => {
        this.flushWebSocketMessageQueue();
    }, 200);

}

/**
 * 发送 WebSocket 身份验证
 */
sendWebSocketAuth() {
    if (!this.wsConnected || this.ws.readyState !== WebSocket.OPEN) {
        console.warn('WebSocket 未连接，无法发送身份验证');
        return;
    }
    
    const authData = {
        type: 'auth',
        user_type: 'agent',
        user_id: this.currentAgent,
        session_key: this.currentSessionKey
    };
    
    this.ws.send(JSON.stringify(authData));
    console.log('发送 WebSocket 身份验证:', authData);
    this.wsAuthSent = true;
}

/**
 * 处理 WebSocket 消息
 */
handleWebSocketMessage(event) {
    try {
        const data = JSON.parse(event.data);
        console.log('📨 收到 WebSocket 消息类型:', data.type, '数据:', data);
        
        switch (data.type) {
            case 'auth_success':
                console.log('✅ WebSocket 身份验证成功');
                // 不再显示toast提示，只更新状态条
                break;
                
            case 'auth_error':
                console.error('❌ WebSocket 身份验证失败:', data.message);
                this.showToast('身份验证失败: ' + data.message, 'error');
                break;
                
            case 'new_message':
                // 处理实时消息
                this.handleRealTimeMessage(data);
                break;
                
            case 'message_sent':
                // 消息发送成功回执
                this.handleMessageSentReceipt(data);
                break;
                
            case 'pong':
                // 心跳响应
                console.log('💓 WebSocket 心跳响应');
                break;
                
            case 'error':
                console.error('❌ WebSocket 服务器错误:', data.message);
                this.showToast('WebSocket 错误: ' + data.message, 'error');
                break;
        }
        
    } catch (error) {
        console.error('解析 WebSocket 消息失败:', error, '原始数据:', event.data);
    }
}
    
    /**
     * 处理实时消息
     */
    handleRealTimeMessage(data) {
        // 检查是否是当前会话的消息
        if (data.session_key === this.currentSessionKey) {
            // 如果是消息已读通知，更新 UI
            if (data.type === 'messages_read' && data.message_ids) {
                console.log('收到消息已读通知:', data.message_ids);
                data.message_ids.forEach(msgId => {
                    $(`[data-message-id="${msgId}"] .message-status`).addClass('read').html('<i class="bi bi-check-all"></i>');
                });
                return;
            }
            
            console.log('收到实时消息:', data);
            
            // 去重检查1:如果消息ID已经存在或小于等于lastMessageId,则忽略
            if (data.message_id && data.message_id <= this.lastMessageId) {
                console.log('消息ID过小,跳过:', data.message_id, '<=', this.lastMessageId);
                return;
            }
            
            // 去重检查2:如果消息ID已存在于DOM中,则跳过
            if (data.message_id && $(`[data-message-id="${data.message_id}"]`).length > 0) {
                console.log('消息已存在于DOM中,跳过:', data.message_id);
                return;
            }
            
            // 去重检查3:如果是自己刚刚发送的消息,则跳过(避免WebSocket回声)
            if (data.message_id && this.recentlySentMessageIds.has(data.message_id)) {
                console.log('是自己发送的消息,WebSocket回声已跳过:', data.message_id);
                return;
            }
            
            // 去重检查4:检查是否是最近2秒内发送的相同内容消息（防止WebSocket回声）
            const now = Date.now();
            if (!this._lastSentMessages) {
                this._lastSentMessages = [];
            }
            const recentSent = this._lastSentMessages.filter(item => (now - item.time) < 2000);
            const isEcho = recentSent.some(item => 
                item.content === data.content && 
                item.speaker_type === data.speaker_type
            );
            if (isEcho) {
                console.log('检测到WebSocket回声(内容匹配),跳过:', data.message_id);
                return;
            }
            
            // 去重检查5:检查DOM中是否有相同speaker_type和content的消息（防止重复显示）
            const messageContainers = $('.XEmsg-message-container');
            for (let i = 0; i < messageContainers.length; i++) {
                const container = $(messageContainers[i]);
                const textContent = container.find('.XEmsg-message__text').text();
                const messageBubble = container.find('.XEmsg-message__bubble');
                
                // 检查是否是相同方向的泡泡（incoming/outgoing）
                let domSpeakerType = 1; // 默认客户
                if (messageBubble.hasClass('XEmsg-message__bubble--outgoing') || 
                    messageBubble.hasClass('XEmsg-message__bubble--dummy')) {
                    domSpeakerType = 2; // 客服或假人
                }
                
                // 如果内容和说话者类型都匹配，说明是重复消息
                if (textContent === data.content && domSpeakerType === data.speaker_type) {
                    // 额外检查：这条消息是否是在最近2秒内添加的
                    const messageId = container.data('message-id');
                    if (messageId && messageId.toString().startsWith('temp_')) {
                        console.log('检测到刚发送的临时消息，跳过WebSocket推送:', data.message_id);
                        return;
                    }
                }
            }
            
            // 构建消息对象
            const message = {
                id: data.message_id || 'ws_' + Date.now(),
                content: data.content,
                speaker_type: data.speaker_type || 1, // 默认为客户发言
                created_at: data.created_at || new Date().toISOString(),
                customer_name: data.customer_name || this.currentCustomer,
                agent_account: data.agent_account || this.currentAgent,
                message_type: data.message_type || 'text',
                image_url: data.image_url,
                image_path: data.image_path
            };
            
            // 添加到聊天界面
            this.appendMessageToChat(message);
            
            // 记录最近通过WebSocket接收的消息ID，用于轮询去重
            if (data.message_id) {
                this.recentlyReceivedWsMessageIds.add(data.message_id);
                // 10秒后清除(避免内存泄漏)
                setTimeout(() => {
                    this.recentlyReceivedWsMessageIds.delete(data.message_id);
                }, 10000);
            }
            
            // 滚动到底部
            this.scrollToBottom();
            
            // 播放提示音
            this.playNotificationSound();
            
            // 更新最后消息ID
            if (data.message_id && data.message_id > this.lastMessageId) {
                this.lastMessageId = data.message_id;
            }
            
            // 如果收到的是客户消息，自动标记客服发送的消息为已读
            console.log('收到消息，speaker_type:', data.speaker_type);
            if (data.speaker_type === 1 || !data.speaker_type) {
                console.log('检测到客户消息，触发自动标记已读');
                this.autoMarkMessagesAsRead();
            }
        }
    }
    
    /**
     * 自动标记客服消息为已读
     */
    autoMarkMessagesAsRead() {
        if (!this.currentSessionKey) {
            console.log('currentSessionKey为空，跳过标记已读');
            return;
        }
        
        console.log('开始标记已读，session_key:', this.currentSessionKey);
        
        $.ajax({
            url: '/api/chat/messages',
            type: 'POST',
            data: {
                action: 'mark_read',
                session_key: this.currentSessionKey
            },
            dataType: 'json',
            success: (response) => {
                console.log('标记已读响应:', response);
                if (response.success) {
                    // 更新所有客服消息的已读状态 - 使用正确的选择器
                    $('.message-status').each(function() {
                        $(this).addClass('read').html('<i class="bi bi-check-all"></i>');
                    });
                    console.log('已成功标记消息为已读，更新元素数量:', $('.message-status').length);
                }
            },
            error: (xhr, status, error) => {
                console.error('自动标记已读失败:', status, error);
                console.error('响应内容:', xhr.responseText);
            }
        });
    }
    
    /**
     * 处理消息发送回执
     */
    handleMessageSentReceipt(data) {
        console.log('消息发送回执:', data);
        
        if (data.temp_id && data.message_id) {
            this.markMessageAsConfirmed(data.temp_id, data.message_id, data.image_url);
        }
    }
    
    handleWebSocketError(event) {
        console.error('WebSocket 错误:', event);
        console.error('WebSocket 状态:', this.ws?.readyState);
        this.wsConnectionStatus = 'error';
        this.updateConnectionStatus();
    }
    
    handleWebSocketClose(event) {
        console.log('WebSocket 连接关闭:', {
            code: event.code,
            reason: event.reason,
            wasClean: event.wasClean
        });
        this.wsConnected = false;
        this.wsConnectionStatus = 'disconnected';
        this.wsAuthSent = false;
        this.updateConnectionStatus();
    
    // 停止心跳
    this.stopWebSocketHeartbeat();

    // 尝试重连
    this.scheduleWebSocketReconnect();
}

/**
 * 发送 WebSocket 心跳
 */
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
    }, 30000); // 30秒一次心跳
}

/**
 * 停止 WebSocket 心跳
 */
stopWebSocketHeartbeat() {
    if (this.wsHeartbeatInterval) {
        clearInterval(this.wsHeartbeatInterval);
        this.wsHeartbeatInterval = null;
    }
}

/**
 * 安排 WebSocket 重连
 */
scheduleWebSocketReconnect() {
    if (this.wsReconnectAttempts >= this.maxWsReconnectAttempts) {
        console.log('已达到最大重连次数，停止重连');
        this.showToast('WebSocket 连接失败，已切换到轮询模式', 'error');
        return;
    }
    
    this.wsReconnectAttempts++;
    const delay = this.wsReconnectDelay * Math.pow(1.5, this.wsReconnectAttempts - 1);
    
    console.log(`将在 ${delay}ms 后尝试第 ${this.wsReconnectAttempts} 次重连`);
    
    setTimeout(() => {
        console.log('尝试重连 WebSocket...');
        this.initWebSocket();
    }, delay);
}

/**
 * 发送消息到 WebSocket
 */
sendMessageToWebSocket(messageData) {
    console.log('🔄 尝试通过 WebSocket 发送消息:', messageData);
    
    if (!this.wsConnected || !this.ws || this.ws.readyState !== WebSocket.OPEN) {
        console.log('❌ WebSocket 未连接，状态:', {
            connected: this.wsConnected,
            readyState: this.ws ? this.ws.readyState : 'no ws',
            messageQueueLength: this.wsMessageQueue.length
        });
        
        // 将消息加入队列
        this.wsMessageQueue.push(messageData);
        return false;
    }
    
    try {
        console.log('📤 通过 WebSocket 发送消息:', messageData);
        this.ws.send(JSON.stringify(messageData));
        return true;
    } catch (error) {
        console.error('❌ WebSocket 发送消息失败:', error);
        this.wsMessageQueue.push(messageData);
        return false;
    }
}

/**
 * 刷新 WebSocket 消息队列
 */
flushWebSocketMessageQueue() {
    if (this.wsMessageQueue.length === 0) return;
    
    console.log(`刷新消息队列，有 ${this.wsMessageQueue.length} 条待发送消息`);
    
    const queue = [...this.wsMessageQueue];
    this.wsMessageQueue = [];
    
    queue.forEach(messageData => {
        this.sendMessageToWebSocket(messageData);
    });
}

    /**
     * 更新连接状态显示 - 已禁用旁边的状态指示器，只保留顶部状态条
     */
    updateConnectionStatus() {
        // 不再在客户状态旁边显示WebSocket连接状态
        // 只更新顶部的WebSocket状态条
        this.updateWebSocketStatusBar();
    }
    
    /**
     * 更新顶部WebSocket状态条
     */
    updateWebSocketStatusBar() {
        const statusBar = $('#ws-status-bar');
        const statusText = $('#ws-status-text');
        
        if (!statusBar.length) return;
        
         // 检查设置是否允许显示状态栏
        const wsSettings = JSON.parse(localStorage.getItem('chat_ws_status_bar_enabled') || '{}');
        const enabled = wsSettings.enabled === true;
        
        // 如果设置禁止显示状态栏，则不添加 show 类
        if (!enabled) {
            statusBar.removeClass('show');
            return;
        }
        
        switch (this.wsConnectionStatus) {
            case 'connected':
                // 连接成功时显示绿色状态条
                statusBar.removeClass('ws-disconnected ws-connecting').addClass('ws-connected show');
                statusText.text('WebSocket已连接');
                break;
            case 'connecting':
                // 连接中显示黄色状态条
                statusBar.removeClass('ws-connected ws-disconnected').addClass('ws-connecting show');
                statusText.text('WebSocket连接中...');
                break;
            case 'disconnected':
            case 'error':
                // 未连接时显示红色状态条
                statusBar.removeClass('ws-connected ws-connecting').addClass('ws-disconnected show');
                statusText.text('WebSocket服务器未开启');
                break;
        }
    }
}
// ==================== WebSocket 方法结束 ====================

// ==================== 返回按钮处理 ====================
function handleBackClick() {
    if (window.self !== window.top) {
        window.parent.postMessage('closeChatModal', '*');
    } else {
        window.location.href = '/consle#chat';
    }
}
		</script>
		<script>
			$(document).ready(function() {
			 
			    // 初始化聊天页面
			    window.chatPage = new ChatPage();
			    
			    // 确保AI按钮位置正确（如果需要）
			    setTimeout(function() {
			        if (window.chatPage && typeof window.chatPage.toggleDummyButtonByPlatform === 'function') {
			            window.chatPage.toggleDummyButtonByPlatform();
			        }
			    }, 50);
			    
			   // 绑定删除选中消息按钮的点击事件（延迟绑定确保 DOM 已加载）
			    setTimeout(function() {
			        
			         // 绑定取消按钮
			        $('#cancelDeleteSelectedBtn').off('click').on('click', function() {
			            $('#deleteSelectedModal').removeClass('active');
			        });
			        
			        $('#confirmDeleteSelectedBtn').off('click').on('click', function() {
			            if (window.chatPage && typeof window.chatPage.confirmDeleteSelected === 'function') {
			                window.chatPage.confirmDeleteSelected();
			            }
			        });
			    }, 100);
			    
			    // 自动标记已读（看见就变成已读）
			    setTimeout(function() {
			        if (window.chatPage && typeof window.chatPage.autoMarkMessagesAsRead === 'function') {
			            window.chatPage.autoMarkMessagesAsRead();
			        }
			    }, 1000);

	 // 监听 WebSocket 状态条设置变化 - 方式 1: 自定义事件（同窗口）
			    window.addEventListener('chatWsStatusBarChanged', (event) => {
			        // 从 localStorage 读取最新值
			        const wsSettings = JSON.parse(localStorage.getItem('chat_ws_status_bar_enabled') || '{}');
			        const enabled = wsSettings.enabled === true;
			        console.log('📡 [自定义事件] WebSocket 状态条设置变更:', enabled ? '显示' : '隐藏');
			        
			        const statusBar = document.getElementById('ws-status-bar');
			        if (statusBar) {
			            statusBar.style.display = enabled ? 'block' : 'none';
			            if (!enabled) {
			                statusBar.classList.remove('show');
			            } else {
			                // 开启时触发一次状态更新，添加 show 类
			                if (window.chatPage) {
			                    window.chatPage.updateWebSocketStatusBar();
			                }
			            }
			        } else {
			            console.log('❌ 未找到状态栏元素 ws-status-bar');
			        }
			    });
			    
			    // 监听 WebSocket 状态条设置变化 - 方式 2: storage 事件（跨窗口/iframe）
			    window.addEventListener('storage', (event) => {
			        if (event.key === 'chat_ws_status_bar_enabled') {
			            // 从 localStorage 读取最新值
			            const wsSettings = JSON.parse(localStorage.getItem('chat_ws_status_bar_enabled') || '{}');
			            const enabled = wsSettings.enabled === true;
			            console.log('📡 [Storage 事件] WebSocket 状态条设置变更:', enabled ? '显示' : '隐藏');
			            
			            const statusBar = document.getElementById('ws-status-bar');
			            if (statusBar) {
			                statusBar.style.display = enabled ? 'block' : 'none';
			                if (!enabled) {
			                    statusBar.classList.remove('show');
			                } else {
			                // 开启时触发一次状态更新，添加 show 类
			                if (window.chatPage) {
			                    window.chatPage.updateWebSocketStatusBar();
			                }
			                }
			            } else {
			                console.log('❌ 未找到状态栏元素 ws-status-bar');
			            }
			        }
			    });
			    
			    // 监听 WebSocket 状态条设置变化 - 方式 3: message 事件（来自父窗口）
			    window.addEventListener('message', (e) => {
			        if (e.data && e.data.type === 'WS_STATUS_BAR_CHANGED') {
			            // 从 localStorage 读取最新值
			            const wsSettings = JSON.parse(localStorage.getItem('chat_ws_status_bar_enabled') || '{}');
			            const enabled = wsSettings.enabled === true;
			            console.log('📡 [Message 事件] WebSocket 状态条设置变更:', enabled ? '显示' : '隐藏');
			            
			            const statusBar = document.getElementById('ws-status-bar');
			            if (statusBar) {
			                statusBar.style.display = enabled ? 'block' : 'none';
			                if (!enabled) {
			                    statusBar.classList.remove('show');
			                } else {
			                // 开启时触发一次状态更新，添加 show 类
			                if (window.chatPage) {
			                    window.chatPage.updateWebSocketStatusBar();
			                }
			                }
			            } else {
			                console.log('❌ 未找到状态栏元素 ws-status-bar');
			            }
			        }
			    });
			    
			    // 初始化时检查设置
			    try {
			        const wsSettings = JSON.parse(localStorage.getItem('chat_ws_status_bar_enabled') || '{}');
			        const enabled = wsSettings.enabled === true;
			        console.log('🔍 初始化 WebSocket 状态条设置:', enabled ? '显示' : '隐藏');
			        
			        const statusBar = document.getElementById('ws-status-bar');
			        if (statusBar) {
			            if (!enabled) {
			                statusBar.style.display = 'none';
			                statusBar.classList.remove('show');
			                console.log('🔇 WebSocket 状态条已根据设置隐藏');
			            } else {
			                statusBar.style.display = 'block';
			                console.log('✅ WebSocket 状态条已显示');
			            }
			        } else {
			            console.log('❌ 未找到状态栏元素 ws-status-bar');
			        }
			    } catch (e) {
			        console.log('读取 WebSocket 状态条设置失败:', e);
			    }
			    
			    // 3秒后强制检查 WebSocket
    setTimeout(() => {
        console.log('⏰ 3秒后检查 WebSocket 状态');
        if (window.chatPage) {
            console.log('检查 chatPage 实例:', window.chatPage);
            console.log('检查 chatPage.ws:', window.chatPage.ws);
            
            if (!window.chatPage.ws || window.chatPage.ws.readyState !== WebSocket.OPEN) {
                console.log('🔄 WebSocket 未连接，尝试初始化...');
                window.chatPage.initWebSocket();
            } else {
                console.log('✅ WebSocket 已连接');
            }
        }
    }, 3000);
			    
			    // 监听页面卸载事件
			    $(window).on('beforeunload', function() {
			        if (window.chatPage) {
			            window.chatPage.destroy();
			        }
			    });
			    
			    // 窗口大小变化时重新计算面板位置
			    $(window).on('resize', function() {
			        if (window.chatPage && typeof window.chatPage.updatePhrasesPanelPosition === 'function') {
			            window.chatPage.updatePhrasesPanelPosition();
			        }
			    });
			    
			     // 新增：页面加载后5秒，强制检查 WebSocket 连接
    setTimeout(() => {
        if (window.chatPage && !window.chatPage.wsConnected) {
            console.log('🔄 页面加载完成，尝试连接 WebSocket...');
            window.chatPage.initWebSocket();
        }
    }, 5000);
			});
			
			// 全局函数
			function hideModal(modalId) {
			    $('#' + modalId).removeClass('active');
			}
			
			function saveCustomerNote() {
			    window.chatPage.saveCustomerNote();
			}
			
			function clearCustomerNote() {
			    window.chatPage.clearCustomerNote();
			}
			
			function toggleSessionSetting(switchId, settingKey) {
			    window.chatPage.toggleSessionSetting(switchId, settingKey);
			}
			
			function confirmDelete() {
			    window.chatPage.confirmDelete();
			}
			
			function confirmDeleteSelected() {
			    window.chatPage.confirmDeleteSelected();
			}
			
			function blockCurrentUser() {
			    window.chatPage.blockCurrentUser();
			}
			
			// 标记消息已读
			function markMessagesAsRead(messageIds) {
			    if (!messageIds || messageIds.length === 0) return;
			    
			    $.ajax({
			        url: '/api/chat/messages?action=mark_read',
			        type: 'POST',
			        data: {
			            message_ids: JSON.stringify(messageIds)
			        },
			        success: function(response) {
			            if (response.success) {
			                messageIds.forEach(function(msgId) {
			                    $(`.message-status[data-message-id="${msgId}"]`).addClass('read').html('<i class="bi bi-check-all"></i>');
			                });
			            }
			        },
			        error: function() {
			            console.error('标记已读失败');
			        }
			    });
			}
			
			// 初始化客户备注显示
			function initCustomerNotes() {
			    $('.XEmsg-header__title').each(function() {
			        const customerName = $(this).data('customer');
			        const note = getCustomerNote(customerName);
			        
			        if (note) {
			            const noteElement = $(this).find('.note-text');
			            noteElement.text('（' + note + '）');
			            noteElement.addClass('note-text');
			        }
			    });
			}
		</script>
		<script>
			// 动态计算输入区域高度并设置消息列表padding
			function adjustMessagePadding() {
			  const inputArea = document.querySelector('.XEmsg-input');
			  const messagesContainer = document.querySelector('.XEmsg-messages');
			  
			  if (inputArea && messagesContainer) {
			    const inputHeight = inputArea.offsetHeight;
			    // 为消息容器设置底部padding
			    messagesContainer.style.paddingBottom = `${inputHeight + 35}px`;
			    
			    // 如果需要，也可以滚动到底部
			    setTimeout(() => {
			      messagesContainer.scrollTop = messagesContainer.scrollHeight;
			    }, 100);
			  }
			}
			
			// 初始计算
			window.addEventListener('load', adjustMessagePadding);
			// 监听窗口大小变化
			window.addEventListener('resize', adjustMessagePadding);
			// 输入区域内容变化时也重新计算
			const observer = new MutationObserver(adjustMessagePadding);
			const inputWrapper = document.querySelector('.XEmsg-input__wrapper');
			if (inputWrapper) {
			  observer.observe(inputWrapper, { attributes: true, childList: true, subtree: true });
			}
		</script>
		<script>
			$(document).ready(function() {
			    // 图片预览相关变量
			    let currentImageIndex = 0;
			    let allImages = [];
			    let zoomLevel = 1;
			    let rotation = 0;
			    let isDragging = false;
			    let dragStartX = 0;
			    let dragStartY = 0;
			    let translateX = 0;
			    let translateY = 0;
			    let isFullscreen = false;
			    
			    // 初始化图片预览功能
			    function initImagePreview() {
			        // 收集页面中的所有图片
			        updateImageList();
			        
			        console.log('🖼️ 图片预览初始化，找到', allImages.length, '张图片');
			        
			        // 为所有消息图片添加点击事件
			        $(document).on('click', '.message-image', function(e) {
			            console.log('🖼️ 图片被点击', this);
			            e.stopPropagation();
			            
			            // 先更新图片列表，确保包含所有图片
			            updateImageList();
			            
			            const imgSrc = $(this).attr('src');
			            const imgAlt = $(this).attr('alt') || '预览图片';
			            
			            console.log('🖼️ 图片源:', imgSrc);
			            console.log('🖼️ 更新后图片列表长度:', allImages.length);
			            
			            // 找到当前图片在列表中的索引
			            currentImageIndex = Array.from(document.querySelectorAll('.message-image')).findIndex(img => 
			                img.src === $(this)[0].src
			            );
			            
			            console.log('🖼️ 图片索引:', currentImageIndex);
			            
			            if (currentImageIndex !== -1) {
			                showImagePreview(currentImageIndex);
			            } else {
			                console.warn('⚠️ 未找到图片索引');
			            }
			        });
			        
			        // 关闭按钮
			        $('#closePreviewBtn').click(hideImagePreview);
			        
			        // 模态框背景点击关闭
			        $('#imagePreviewModal').click(function(e) {
			            if ($(e.target).hasClass('mac-modal')) {
			                hideImagePreview();
			            }
			        });
			        
			        // 键盘事件
			        $(document).on('keydown', function(e) {
			            if ($('#imagePreviewModal').hasClass('active')) {
			                switch(e.key) {
			                    case 'Escape':
			                        hideImagePreview();
			                        break;
			                    case 'ArrowLeft':
			                        showPrevImage();
			                        break;
			                    case 'ArrowRight':
			                        showNextImage();
			                        break;
			                    case '+':
			                    case '=':
			                        zoomIn();
			                        break;
			                    case '-':
			                        zoomOut();
			                        break;
			                    case 'r':
			                    case 'R':
			                        rotateImage();
			                        break;
			                    case 'f':
			                    case 'F':
			                        toggleFullscreen();
			                        break;
			                }
			            }
			        });
			        
			        // 控制按钮事件
			        $('#zoomInBtn').click(zoomIn);
			        $('#zoomOutBtn').click(zoomOut);
			        $('#rotateBtn').click(rotateImage);
			        $('#fullscreenBtn').click(toggleFullscreen);
			        $('#downloadBtn').click(downloadImage);
			        $('#prevImageBtn').click(showPrevImage);
			        $('#nextImageBtn').click(showNextImage);
			        
			        // 触摸事件支持
			        let touchStartX = 0;
			        let touchStartY = 0;
			        let touchStartTime = 0;
			        
			        $('#previewImage').on('touchstart', function(e) {
			            const touch = e.originalEvent.touches[0];
			            touchStartX = touch.clientX;
			            touchStartY = touch.clientY;
			            touchStartTime = Date.now();
			        });
			        
			        $('#previewImage').on('touchmove', function(e) {
			            if (zoomLevel > 1) {
			                e.preventDefault();
			            }
			        });
			        
			        $('#previewImage').on('touchend', function(e) {
			            const touch = e.originalEvent.changedTouches[0];
			            const deltaX = touch.clientX - touchStartX;
			            const deltaY = touch.clientY - touchStartY;
			            const deltaTime = Date.now() - touchStartTime;
			            const distance = Math.sqrt(deltaX * deltaX + deltaY * deltaY);
			            
			            // 如果是轻扫手势且时间短
			            if (deltaTime < 300 && distance > 50) {
			                if (Math.abs(deltaX) > Math.abs(deltaY)) {
			                    if (deltaX > 0) {
			                        showPrevImage();
			                    } else {
			                        showNextImage();
			                    }
			                }
			            }
			        });
			        
			        // 双击缩放
			        $('#previewImage').on('dblclick', function(e) {
			            if (zoomLevel === 1) {
			                zoomIn();
			            } else {
			                resetZoom();
			            }
			        });
			        
			        // 鼠标滚轮缩放
			        $('#imagePreviewModal').on('wheel', function(e) {
			            if (!$(e.target).closest('.image-container').length) return;
			            
			            e.preventDefault();
			            if (e.originalEvent.deltaY < 0) {
			                zoomIn();
			            } else {
			                zoomOut();
			            }
			        });
			        
			        // 鼠标拖动
			        $('#previewImage').on('mousedown', function(e) {
			            if (zoomLevel <= 1) return;
			            
			            isDragging = true;
			            dragStartX = e.clientX - translateX;
			            dragStartY = e.clientY - translateY;
			            $(this).css('cursor', 'grabbing');
			        });
			        
			        $(document).on('mousemove', function(e) {
			            if (!isDragging || zoomLevel <= 1) return;
			            
			            e.preventDefault();
			            translateX = e.clientX - dragStartX;
			            translateY = e.clientY - dragStartY;
			            
			            $('#previewImage').css({
			                'transform': `rotate(${rotation}deg) translate(${translateX}px, ${translateY}px) scale(${zoomLevel})`
			            });
			        });
			        
			        $(document).on('mouseup', function() {
			            if (isDragging) {
			                isDragging = false;
			                $('#previewImage').css('cursor', 'move');
			            }
			        });
			    }
			    
			    // 更新图片列表
			    function updateImageList() {
			        allImages = Array.from(document.querySelectorAll('.message-image')).map(img => ({
			            src: img.src,
			            alt: img.alt,
			            parentMessage: $(img).closest('.XEmsg-message-container').data('message-id'),
			            timestamp: $(img).closest('.XEmsg-message-container').find('.XEmsg-message__time span').text() || '未知时间'
			        }));
			    }
			    
			    // 显示图片预览
			    function showImagePreview(index) {
			        if (index < 0 || index >= allImages.length) return;
			        
			        currentImageIndex = index;
			        const image = allImages[index];
			        
			        // 显示加载动画
			        $('#previewImage').hide();
			        $('.image-container').append('<div class="image-loading"></div>');
			        
			        // 加载图片
			        const img = new Image();
			        img.onload = function() {
			            $('.image-loading').remove();
			            $('#previewImage').show();
			            
			            // 设置图片
			            $('#previewImage').attr('src', image.src);
			            $('#previewImage').attr('alt', image.alt);
			            
			            // 重置变换
			            resetImageTransform();
			            
			            // 更新图片信息
			            updateImageInfo(img, image);
			            
			            // 更新导航状态
			            updateNavigation();
			            
			            // 显示模态框
			            $('#imagePreviewModal').addClass('active');
			            
			            // 阻止页面滚动
			            $('body').css('overflow', 'hidden');
			            
			            // 焦点保持在模态框
			            $('#closePreviewBtn').focus();
			        };
			        
			        img.onerror = function() {
			            $('.image-loading').remove();
			            $('#previewImage').show();
			            $('#previewImage').attr('src', 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200" viewBox="0 0 200 200"><rect width="200" height="200" fill="%23f0f0f0"/><text x="100" y="100" text-anchor="middle" fill="%23999" font-family="Arial" font-size="14">图片加载失败</text></svg>');
			            updateImageInfo(null, image);
			        };
			        
			        img.src = image.src;
			    }
			    
			    // 更新图片信息
			    function updateImageInfo(imgElement, imageData) {
			        if (imgElement) {
			            // 获取图片尺寸
			            $('#imageDimensions').text(imgElement.naturalWidth + ' × ' + imgElement.naturalHeight);
			            
			            // 获取图片大小（通过HTTP HEAD请求）
			            fetch(imageData.src, { method: 'HEAD' })
			                .then(response => {
			                    const size = response.headers.get('content-length');
			                    if (size) {
			                        $('#imageSize').text(formatFileSize(size));
			                    } else {
			                        $('#imageSize').text('-');
			                    }
			                })
			                .catch(() => {
			                    $('#imageSize').text('-');
			                });
			        } else {
			            $('#imageDimensions').text('-');
			            $('#imageSize').text('-');
			        }
			        
			        // 获取文件名
			        const pathParts = imageData.src.split('/');
			        const fileName = pathParts[pathParts.length - 1].split('?')[0];
			        $('#imageFileName').text(fileName || '-');
			        
			        // 时间
			        $('#imageTime').text(imageData.timestamp || '-');
			    }
			    
			    // 隐藏图片预览
			    function hideImagePreview() {
			        $('#imagePreviewModal').removeClass('active');
			        $('body').css('overflow', '');
			        
			        // 移除全屏状态
			        if (isFullscreen) {
			            toggleFullscreen();
			        }
			    }
			    
			    // 缩放功能
			    function zoomIn() {
			        if (zoomLevel < 3) {
			            zoomLevel += 0.1;
			            applyImageTransform();
			            showZoomLevel();
			        }
			    }
			    
			    function zoomOut() {
			        if (zoomLevel > 0.5) {
			            zoomLevel -= 0.1;
			            applyImageTransform();
			            showZoomLevel();
			        }
			    }
			    
			    function resetZoom() {
			        zoomLevel = 1;
			        translateX = 0;
			        translateY = 0;
			        applyImageTransform();
			        showZoomLevel();
			    }
			    
			    // 旋转功能
			    function rotateImage() {
			        rotation = (rotation + 90) % 360;
			        applyImageTransform();
			    }
			    
			    // 应用图片变换
			    function applyImageTransform() {
			        $('#previewImage').css({
			            'transform': `rotate(${rotation}deg) translate(${translateX}px, ${translateY}px) scale(${zoomLevel})`
			        });
			    }
			    
			    // 重置图片变换
			    function resetImageTransform() {
			        zoomLevel = 1;
			        rotation = 0;
			        translateX = 0;
			        translateY = 0;
			        $('#previewImage').css({
			            'transform': 'rotate(0deg) translate(0px, 0px) scale(1)',
			            'cursor': 'move'
			        });
			    }
			    
			    // 显示缩放级别
			    function showZoomLevel() {
			        let $zoomLevel = $('.zoom-level');
			        if ($zoomLevel.length === 0) {
			            $('.image-container').append('<div class="zoom-level">' + Math.round(zoomLevel * 100) + '%</div>');
			            $zoomLevel = $('.zoom-level');
			        } else {
			            $zoomLevel.text(Math.round(zoomLevel * 100) + '%');
			        }
			        
			        $zoomLevel.addClass('show');
			        clearTimeout($zoomLevel.data('hideTimeout'));
			        $zoomLevel.data('hideTimeout', setTimeout(() => {
			            $zoomLevel.removeClass('show');
			        }, 1000));
			    }
			    
			    // 全屏功能
			    function toggleFullscreen() {
			        isFullscreen = !isFullscreen;
			        $('#imagePreviewModal').toggleClass('fullscreen');
			        $('#fullscreenBtn').html(
			            isFullscreen ? 
			            '<i class="bi bi-arrows-angle-contract"></i>' : 
			            '<i class="bi bi-arrows-fullscreen"></i>'
			        );
			        
			        // 重置图片位置和缩放
			        resetImageTransform();
			    }
			    
			    // 下载图片
			    function downloadImage() {
			        const imageSrc = $('#previewImage').attr('src');
			        if (!imageSrc || imageSrc.startsWith('data:')) return;
			        
			        const link = document.createElement('a');
			        link.href = imageSrc;
			        link.download = $('#imageFileName').text() || 'image';
			        document.body.appendChild(link);
			        link.click();
			        document.body.removeChild(link);
			    }
			    
			    // 导航功能
			    function showPrevImage() {
			        if (currentImageIndex > 0) {
			            showImagePreview(currentImageIndex - 1);
			        }
			    }
			    
			    function showNextImage() {
			        if (currentImageIndex < allImages.length - 1) {
			            showImagePreview(currentImageIndex + 1);
			        }
			    }
			    
			    function updateNavigation() {
			        const $prevBtn = $('#prevImageBtn');
			        const $nextBtn = $('#nextImageBtn');
			        const $counter = $('#imageCounter');
			        
			        if (allImages.length > 1) {
			            $prevBtn.show();
			            $nextBtn.show();
			            $counter.show();
			            
			            $('#currentImageIndex').text(currentImageIndex + 1);
			            $('#totalImages').text(allImages.length);
			            
			            // 更新箭头状态
			            $prevBtn.toggleClass('disabled', currentImageIndex === 0);
			            $nextBtn.toggleClass('disabled', currentImageIndex === allImages.length - 1);
			        } else {
			            $prevBtn.hide();
			            $nextBtn.hide();
			            $counter.hide();
			        }
			    }
			    
			    // 工具函数：格式化文件大小
			    function formatFileSize(bytes) {
			        if (typeof bytes !== 'number') return '-';
			        
			        const units = ['B', 'KB', 'MB', 'GB'];
			        let size = bytes;
			        let unitIndex = 0;
			        
			        while (size >= 1024 && unitIndex < units.length - 1) {
			            size /= 1024;
			            unitIndex++;
			        }
			        
			        return size.toFixed(2) + ' ' + units[unitIndex];
			    }
			    
			    // 初始化图片预览功能
			    initImagePreview();
			    
			    // 监听新消息添加（如果有动态添加消息的功能）
			    $(document).on('messageAdded', function() {
			        updateImageList();
			    });
			});
		</script>

<!-- PWA Service Worker 注册 -->
<script src="/js/push-notification.js"></script>
<script>
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/sw.js', { scope: '/' }).then(function(reg) {
        console.log('[Chat] Service Worker 已注册');
    }).catch(function(err) {
        console.error('[Chat] Service Worker 注册失败:', err);
    });
}
</script>

</body>
</html>