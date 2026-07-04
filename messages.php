<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// 处理 OPTIONS 预检请求
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 直接用服务器根目录定位文件，100%不报错
$root = $_SERVER['DOCUMENT_ROOT'];
require_once $root . '/config/dbconfig.php';
require_once $root . '/config/session_parser.php';

$db = getDB();
if (!$db) {
    echo json_encode(array("success" => false, "message" => "数据库连接失败"));
    exit;
}

// 设置时区
date_default_timezone_set('Asia/Shanghai');

$method = $_SERVER['REQUEST_METHOD'];

function debugLog($message) {
     // 调试日志已禁用
    return;
}

/**
 * 推送消息到 WebSocket 服务器（完成完整的WebSocket握手）
 */
function pushToWebSocket($messageData) {
    $wsServer = '127.0.0.1';
    $wsPort = 8288;
    $timeout = 3;
    
    try {
        // 1. 建立 TCP 连接
        $socket = @fsockopen("tcp://{$wsServer}", $wsPort, $errno, $errstr, $timeout);
        
        if (!$socket) {
            error_log("WebSocket连接失败: {$errstr} ({$errno})");
            return false;
        }
        
        stream_set_timeout($socket, $timeout);
        
        // 2. 生成 WebSocket 握手 key
        $key = base64_encode(random_bytes(16));
        
        // 3. 发送 HTTP 升级请求（WebSocket握手）
        $handshake = "GET / HTTP/1.1\r\n"
                   . "Host: {$wsServer}:{$wsPort}\r\n"
                   . "Upgrade: websocket\r\n"
                   . "Connection: Upgrade\r\n"
                   . "Sec-WebSocket-Key: {$key}\r\n"
                   . "Sec-WebSocket-Version: 13\r\n"
                   . "\r\n";
        
        fwrite($socket, $handshake);
        
        // 4. 读取服务器握手响应
        $response = '';
        while (($line = fgets($socket)) !== false) {
            $response .= $line;
            if (rtrim($line) === '') {
                break; // 空行表示header结束
            }
        }
        
        if (strpos($response, '101') === false) {
            error_log("WebSocket握手失败: " . substr($response, 0, 200));
            fclose($socket);
            return false;
        }
        
        // 5. 发送 WebSocket 数据帧
        $data = json_encode($messageData);
        $frame = buildWebSocketFrame($data, true); // masked=true（客户端必须mask）
        fwrite($socket, $frame);
        
        // 6. 等待一小段时间确保数据发出
        usleep(50000); // 50ms
        fclose($socket);
        
        return true;
        
    } catch (Exception $e) {
        error_log("WebSocket推送异常: " . $e->getMessage());
        return false;
    }
}

/**
 * 构建 WebSocket 帧（支持mask，客户端发送必须mask）
 */
function buildWebSocketFrame($payload, $masked = false) {
    $frame = [];
    $payloadLength = strlen($payload);
    
    $frame[0] = 0x81; // FIN=1, opcode=1 (text)
    
    if ($masked) {
        $payloadLength |= 0x80; // 设置mask位
    }
    
    if ($payloadLength <= 125) {
        $frame[1] = $payloadLength;
    } elseif ($payloadLength <= 65535) {
        $frame[1] = 126;
        $frame[2] = ($payloadLength >> 8) & 0xFF;
        $frame[3] = $payloadLength & 0xFF;
    } else {
        $frame[1] = 127;
        for ($i = 7; $i >= 0; $i--) {
            $frame[] = ($payloadLength >> (8 * $i)) & 0xFF;
        }
    }
    
    if ($masked) {
        // 生成4字节mask key
        $maskKey = [rand(0, 255), rand(0, 255), rand(0, 255), rand(0, 255)];
        foreach ($maskKey as $byte) {
            $frame[] = $byte;
        }
        
        // 对payload进行XOR mask
        for ($i = 0; $i < strlen($payload); $i++) {
            $frame[] = ord($payload[$i]) ^ $maskKey[$i % 4];
        }
    } else {
        for ($i = 0; $i < $payloadLength; $i++) {
            $frame[] = ord($payload[$i]);
        }
    }
    
    return pack('C*', ...$frame);
}

// ==================== 改进的数据接收逻辑 ====================
function getRequestData() {
    $method = $_SERVER['REQUEST_METHOD'];
    $input = [];
    
    // 检查是否是 multipart/form-data 请求（文件上传）
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (strpos($contentType, 'multipart/form-data') !== false) {
        // 文件上传请求，直接使用 $_POST
        debugLog("检测到文件上传请求，使用 \$_POST");
        $input = $_POST;
    } else {
        $rawInput = file_get_contents("php://input");
        
        debugLog("请求方法: $method");
        debugLog("原始输入内容: " . $rawInput);
        debugLog("原始输入长度: " . strlen($rawInput));
        debugLog("CONTENT_TYPE: " . $contentType);
        
        // 尝试解析JSON
        if (!empty($rawInput)) {
            $input = json_decode($rawInput, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                debugLog("JSON解析失败: " . json_last_error_msg());
                // 尝试解析form-data格式
                parse_str($rawInput, $input);
            }
        }
        
        // 如果JSON解析失败，尝试使用$_POST
        if (empty($input) && !empty($_POST)) {
            debugLog("使用$_POST数据");
            $input = $_POST;
        }
    }
    
    // 合并GET参数（用于action等）
    if (!empty($_GET)) {
        $input = array_merge($_GET, $input);
    }
    
    debugLog("最终请求数据: " . json_encode($input));
    return $input;
}

$input = getRequestData();

// ==================== 核心功能函数 ====================

/**
 * 获取客户端真实IP地址
 */
// 修改 getClientIP 函数
function getClientIP() {
    $ip = 'unknown';
    
    // 检查HTTP头，处理代理情况
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = trim($ips[0]);
    } elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
        $ip = $_SERVER['HTTP_X_REAL_IP'];
    } elseif (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    
    // 验证IP地址格式（不过滤内网IP，保留原始IP）
    if (filter_var($ip, FILTER_VALIDATE_IP)) {
        return $ip;
    }
    
    return 'unknown';
}





if ($method == 'GET' && $action == 'get_customer_full_info') {
    $statistics = [
        'total_customers' => 0,
        'unique_ips' => 0,
        'online_customers' => 0,
        'duplicate_ips' => []
    ];
    
    // 获取总客户数（基于IP去重）
    $query = "SELECT COUNT(DISTINCT customer_name) as total_count 
              FROM chat_messages 
              WHERE agent_account = ?";
    $stmt = $db->prepare($query);
    if ($stmt) {
        $stmt->bind_param("s", $agentAccount);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $statistics['total_customers'] = $row['total_count'];
        }
        $stmt->close();
    }
    
    // 获取独立IP数量
    $query = "SELECT COUNT(DISTINCT client_ip) as unique_ips 
              FROM client_ips 
              WHERE agent_account = ?";
    $stmt = $db->prepare($query);
    if ($stmt) {
        $stmt->bind_param("s", $agentAccount);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $statistics['unique_ips'] = $row['unique_ips'];
        }
        $stmt->close();
    }
    
    // 获取在线客户数（基于IP去重）
    $online_threshold = date('Y-m-d H:i:s', strtotime('-5 minutes'));
    $query = "SELECT COUNT(DISTINCT ci.client_ip) as online_count 
              FROM client_ips ci
              INNER JOIN chat_messages cm ON ci.session_key = cm.session_key
              WHERE cm.agent_account = ? AND cm.created_at >= ?";
    $stmt = $db->prepare($query);
    if ($stmt) {
        $stmt->bind_param("ss", $agentAccount, $online_threshold);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $statistics['online_customers'] = $row['online_count'];
        }
        $stmt->close();
    }
    
    // 获取重复IP统计
    $query = "SELECT client_ip, COUNT(DISTINCT customer_name) as customer_count 
              FROM client_ips 
              WHERE agent_account = ? 
              GROUP BY client_ip 
              HAVING customer_count > 1";
    $stmt = $db->prepare($query);
    if ($stmt) {
        $stmt->bind_param("s", $agentAccount);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $statistics['duplicate_ips'][$row['client_ip']] = $row['customer_count'];
        }
        $stmt->close();
    }
    
    return $statistics;
}

/**
 * 获取IP查询接口（查看特定IP的所有会话）
 */
// 修改 getSessionsByIPDetail 函数
function getSessionsByIPDetail($db, $clientIP, $agentAccount) {
    $query = "SELECT 
                ci.session_key,
                ci.customer_name,
                ci.client_ip,
                ci.first_seen,
                ci.last_seen,
                cm.platform,
                (SELECT content FROM chat_messages cm2 
                 WHERE cm2.session_key = ci.session_key 
                 ORDER BY cm2.created_at DESC LIMIT 1) as last_message,
                MAX(cm.created_at) as last_message_time
              FROM client_ips ci
              LEFT JOIN chat_messages cm ON ci.session_key = cm.session_key
              WHERE ci.client_ip = ? AND ci.agent_account = ?
              GROUP BY ci.session_key, ci.customer_name
              ORDER BY ci.first_seen ASC";
    
    $stmt = $db->prepare($query);
    if (!$stmt) {
        debugLog("Prepare failed in getSessionsByIPDetail: " . $db->error);
        return [];
    }
    
    $stmt->bind_param("ss", $clientIP, $agentAccount);
    if (!$stmt->execute()) {
        debugLog("Execute failed in getSessionsByIPDetail: " . $stmt->error);
        $stmt->close();
        return [];
    }
    
    $result = $stmt->get_result();
    $sessions = [];
    while ($row = $result->fetch_assoc()) {
        $sessions[] = $row;
    }
    
    $stmt->close();
    return $sessions;
}

if ($method == 'GET' && $action == 'get_customer_full_info') {
    $customerName = $_GET['customer_name'] ?? '';
    $agentAccount = $_GET['agent_account'] ?? '';
    
    error_log("🔍 查询客户信息: 客户={$customerName}, 客服={$agentAccount}");
    
    if (empty($customerName) || empty($agentAccount)) {
        echo json_encode(['success' => false, 'message' => '参数不完整']);
        exit;
    }
    
    // 第一步：从 user_online_status 表获取所有信息
    $query = "SELECT 
                client_ip, 
                device_type, 
                browser, 
                os, 
                user_agent, 
                window_status, 
                last_heartbeat,
                created_at
              FROM user_online_status 
              WHERE username = ? AND user_type = 'customer' 
              AND client_ip IS NOT NULL 
              AND client_ip != '0.0.0.0'
              ORDER BY last_heartbeat DESC 
              LIMIT 1";
    
    $stmt = $db->prepare($query);
    if (!$stmt) {
        error_log("❌ 准备查询失败: " . $db->error);
        echo json_encode(['success' => false, 'message' => '数据库查询失败']);
        exit;
    }
    
    $stmt->bind_param("s", $customerName);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $deviceInfo = null;
    $clientIP = '未知';
    
    if ($row = $result->fetch_assoc()) {
        error_log("✅ 从 user_online_status 找到记录: " . json_encode($row));
        $deviceInfo = $row;
        $clientIP = $row['client_ip'] ?? '未知';
    } else {
        error_log("❌ user_online_status 表中未找到客户: {$customerName}");
    }
    $stmt->close();
    
    // 第二步：从 chat_messages 表获取消息相关信息
    $msgQuery = "SELECT 
                    platform, 
                    MAX(created_at) as last_message_time,
                    (SELECT content FROM chat_messages cm2 
                     WHERE cm2.customer_name = ? AND cm2.agent_account = ? 
                     ORDER BY cm2.created_at DESC LIMIT 1) as last_message
                  FROM chat_messages 
                  WHERE customer_name = ? AND agent_account = ?";
    
    $msgStmt = $db->prepare($msgQuery);
    $platform = '未知';
    $lastMessageTime = '未知';
    $lastMessage = '暂无消息';
    
    if ($msgStmt) {
        $msgStmt->bind_param("ssss", $customerName, $agentAccount, $customerName, $agentAccount);
        $msgStmt->execute();
        $msgResult = $msgStmt->get_result();
        if ($msgRow = $msgResult->fetch_assoc()) {
            $platform = $msgRow['platform'] ?? '未知';
            $lastMessageTime = $msgRow['last_message_time'] ?? '未知';
            $lastMessage = $msgRow['last_message'] ?? '暂无消息';
        }
        $msgStmt->close();
    }
    
    // 第三步：获取IP归属地
    $ipLocation = '未知';
    if ($clientIP && $clientIP !== '未知' && $clientIP !== '0.0.0.0') {
        $ipLocation = getIPLocation($clientIP);
    }
    
    // 第四步：判断IP类型
    $ipType = '未知';
    if ($clientIP && $clientIP !== '未知') {
        if (filter_var($clientIP, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $ipType = 'IPv4';
        } elseif (filter_var($clientIP, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $ipType = 'IPv6';
        }
    }
    
    // 第五步：构建返回结果
    $response = [
        'success' => true,
        'customer_info' => [
            'customer_name' => $customerName,
            'client_ip' => $clientIP,
            'ip_location' => $ipLocation,
            'ip_type' => $ipType,
            'platform' => $platform,
            'last_message_time' => $lastMessageTime,
            'last_message' => $lastMessage,
            'window_status' => $deviceInfo['window_status'] ?? 'window_closed',
            'device_type' => $deviceInfo['device_type'] ?? '未知',
            'browser' => $deviceInfo['browser'] ?? '未知',
            'os' => $deviceInfo['os'] ?? '未知',
            'last_heartbeat' => $deviceInfo['last_heartbeat'] ?? '未知'
        ],
        'debug' => [
            'query_executed' => true,
            'ip_found' => ($clientIP !== '未知'),
            'raw_ip' => $clientIP
        ]
    ];
    
    error_log("📤 返回客户信息: " . json_encode($response));
    
    echo json_encode($response);
    exit;
}

// 修复IP归属地查询函数
function getIPLocation($ip) {
    if (empty($ip) || $ip === '0.0.0.0' || $ip === 'unknown' || $ip === '未知') {
        return '未知';
    }
    
    // 检查内网IP
    if (strpos($ip, '192.168.') === 0 || 
        strpos($ip, '10.') === 0 || 
        strpos($ip, '172.') === 0 ||
        $ip === '127.0.0.1') {
        return '内网IP';
    }
    
    // 简单的地理位置判断（可以根据需要扩展）
    $ipSegments = explode('.', $ip);
    if (count($ipSegments) >= 1) {
        $firstSegment = intval($ipSegments[0]);
        
        // 中国大陆IP段
        if ($firstSegment >= 1 && $firstSegment <= 126) {
            return '中国';
        } elseif ($firstSegment >= 128 && $firstSegment <= 191) {
            return '中国';
        } elseif ($firstSegment >= 192 && $firstSegment <= 223) {
            return '中国';
        } elseif ($firstSegment >= 224 && $firstSegment <= 239) {
            return '多播地址';
        } elseif ($firstSegment >= 240 && $firstSegment <= 255) {
            return '保留地址';
        }
    }
    
    return '未知';
}

// 新增：判断IP类型
function getIPType($ip) {
    if (empty($ip) || $ip === 'unknown') {
        return '未知';
    }
    
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        return 'IPv4';
    }
    
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        return 'IPv6';
    }
    
    return '未知';
}

// ==================== 在线状态管理 ====================
function ensureOnlineStatusTable($db) {
    $checkTable = "SHOW TABLES LIKE 'user_online_status'";
    $result = $db->query($checkTable);
    if ($result->num_rows == 0) {
        $createTable = "CREATE TABLE user_online_status (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            is_online TINYINT(1) DEFAULT 0,
            last_seen DATETIME DEFAULT CURRENT_TIMESTAMP,
            user_type ENUM('customer', 'agent') NOT NULL,
            INDEX idx_username (username),
            INDEX idx_user_type (user_type),
            INDEX idx_last_seen (last_seen)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        if ($db->query($createTable)) {
            debugLog("user_online_status table created successfully");
        } else {
            debugLog("Failed to create user_online_status table: " . $db->error);
        }
    }
}

// 修改现有的 updateOnlineStatus 函数
function updateOnlineStatus($db, $username, $userType = 'agent', $isOnline = true, 
                           $windowStatus = 'window_visible', $deviceType = null, 
                           $browser = null, $os = null, $userAgent = null, $sessionKey = null) {
    
    // 确保表存在
    $ensureResult = ensureOnlineStatusTable($db);
    if (!$ensureResult) {
        debugLog("确保表存在失败");
        return false;
    }
    
    // 获取客户端IP
    $clientIP = getClientIP();
    
    $query = "INSERT INTO user_online_status 
              (username, is_online, window_status, last_seen, last_activity, 
               last_heartbeat, user_type, session_key, client_ip, user_agent,
               device_type, browser, os) 
              VALUES (?, ?, ?, NOW(), NOW(), NOW(), ?, ?, ?, ?, ?, ?, ?) 
              ON DUPLICATE KEY UPDATE 
              is_online = VALUES(is_online), 
              window_status = VALUES(window_status), 
              last_seen = NOW(), 
              last_activity = IF(VALUES(is_online) = 1, NOW(), last_activity), 
              last_heartbeat = NOW(),
              session_key = VALUES(session_key),
              client_ip = VALUES(client_ip),
              user_agent = VALUES(user_agent),
              device_type = VALUES(device_type),
              browser = VALUES(browser),
              os = VALUES(os)";
    
    $stmt = $db->prepare($query);
    if (!$stmt) {
        $error = $db->error;
        debugLog("Prepare failed: " . $error);
        
        // 尝试创建表
        if (strpos($error, "Table 'user_online_status' doesn't exist") !== false) {
            ensureOnlineStatusTable($db);
            $stmt = $db->prepare($query);
            if (!$stmt) {
                debugLog("Prepare failed after table creation: " . $db->error);
                return false;
            }
        } else {
            return false;
        }
    }
    
    $onlineValue = $isOnline ? 1 : 0;
    
    // 绑定参数
    $bindResult = $stmt->bind_param("sissssssssss", 
        $username, $onlineValue, $windowStatus, $userType, 
        $sessionKey, $clientIP, $userAgent, $deviceType, $browser, $os);
    
    if (!$bindResult) {
        debugLog("绑定参数失败: " . $stmt->error);
        $stmt->close();
        return false;
    }
    
    $executeResult = $stmt->execute();
    
    if (!$executeResult) {
        debugLog("执行失败: " . $stmt->error);
        $stmt->close();
        return false;
    }
    
    $stmt->close();
    debugLog("在线状态更新成功: {$username} - {$windowStatus} - 设备:{$deviceType}");
    return true;
}

function getOnlineStatus($db, $usernames) {
    if (empty($usernames)) {
        debugLog("获取在线状态失败: 用户名列表为空");
        return [];
    }
    
    if (!is_array($usernames)) {
        $usernames = explode(',', $usernames);
    }
    
    $usernames = array_map('trim', $usernames);
    $usernames = array_filter($usernames);
    
    if (empty($usernames)) {
        debugLog("获取在线状态失败: 清理后的用户名为空");
        return [];
    }
    
    debugLog("查询在线状态的用户名: " . json_encode($usernames));
    
    $statuses = [];
    
    foreach ($usernames as $username) {
        $statuses[$username] = false;
    }
    
    if (count($usernames) == 1) {
        $username = $usernames[0];
        $query = "SELECT username, is_online, window_status, last_heartbeat, 
                         last_activity, user_type, device_type, browser, os 
                  FROM user_online_status 
                  WHERE username = ?";
        $stmt = $db->prepare($query);
        if ($stmt) {
            $stmt->bind_param("s", $username);
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $isOnline = (bool)$row['is_online'];
                    $windowStatus = $row['window_status'];
                    $lastHeartbeat = strtotime($row['last_heartbeat']);
                    
                    $timeDiff = time() - $lastHeartbeat;
                    
                    // 更严格的在线判断逻辑
                    if ($isOnline) {
                        if ($timeDiff <= 15) { // 15秒内心跳
                            if ($windowStatus === 'window_visible') {
                                $statuses[$username] = 'online';
                            } else if ($windowStatus === 'window_hidden') {
                                $statuses[$username] = 'hidden';
                            } else {
                                $statuses[$username] = 'away';
                            }
                        } else if ($timeDiff <= 30) { // 15-30秒
                            $statuses[$username] = 'away';
                        } else {
                            $statuses[$username] = false;
                        }
                    } else {
                        $statuses[$username] = false;
                    }
                    
                    debugLog("用户 {$username} 状态: {$statuses[$username]} (心跳:{$timeDiff}秒前)");
                }
            }
            $stmt->close();
        }
    } else {
        // 批量查询类似逻辑...
    }
    
    debugLog("最终在线状态: " . json_encode($statuses));
    return $statuses;
}

// ==================== 假人模式管理 ====================
function ensureDummySettingsTable($db) {
    $checkTable = "SHOW TABLES LIKE 'dummy_settings'";
    $result = $db->query($checkTable);
    if ($result->num_rows == 0) {
        $createTable = "CREATE TABLE dummy_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            session_key VARCHAR(100) NOT NULL,
            dummy_name VARCHAR(50) DEFAULT '技术顾问',
            dummy_avatar VARCHAR(255) DEFAULT '/assets/img/dummy1.png',
            is_dummy_mode TINYINT(1) DEFAULT 0,
            last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_session (session_key),
            INDEX idx_session_key (session_key),
            INDEX idx_last_updated (last_updated)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        if ($db->query($createTable)) {
            debugLog("dummy_settings table created successfully");
        } else {
            debugLog("Failed to create dummy_settings table: " . $db->error);
        }
    }
}

function broadcastDummySettings($db, $sessionKey, $dummyName, $dummyAvatar, $isDummyMode) {
    ensureDummySettingsTable($db);
    
    $query = "INSERT INTO dummy_settings (session_key, dummy_name, dummy_avatar, is_dummy_mode) 
              VALUES (?, ?, ?, ?) 
              ON DUPLICATE KEY UPDATE 
              dummy_name = VALUES(dummy_name), 
              dummy_avatar = VALUES(dummy_avatar), 
              is_dummy_mode = VALUES(is_dummy_mode),
              last_updated = CURRENT_TIMESTAMP";
    
    $stmt = $db->prepare($query);
    if (!$stmt) {
        debugLog("Prepare failed in broadcastDummySettings: " . $db->error);
        return false;
    }
    
    $dummyModeValue = $isDummyMode ? 1 : 0;
    $stmt->bind_param("sssi", $sessionKey, $dummyName, $dummyAvatar, $dummyModeValue);
    $result = $stmt->execute();
    
    if (!$result) {
        debugLog("Execute failed in broadcastDummySettings: " . $stmt->error);
    } else {
        debugLog("假人设置广播成功 - Session: $sessionKey, Name: $dummyName, Avatar: $dummyAvatar, Mode: $dummyModeValue");
    }
    
    $stmt->close();
    return $result;
}

function getDummySettings($db, $sessionKey) {
    ensureDummySettingsTable($db);
    
    $query = "SELECT dummy_name, dummy_avatar, is_dummy_mode, last_updated 
              FROM dummy_settings 
              WHERE session_key = ?";
    
    $stmt = $db->prepare($query);
    if (!$stmt) {
        debugLog("Prepare failed in getDummySettings: " . $db->error);
        return null;
    }
    
    $stmt->bind_param("s", $sessionKey);
    $result = $stmt->execute();
    
    if (!$result) {
        debugLog("Execute failed in getDummySettings: " . $stmt->error);
        $stmt->close();
        return null;
    }
    
    $resultSet = $stmt->get_result();
    if ($resultSet->num_rows > 0) {
        $settings = $resultSet->fetch_assoc();
        $stmt->close();
        
        // 转换数据类型
        $settings['is_dummy_mode'] = (bool)$settings['is_dummy_mode'];
        $settings['last_updated'] = strtotime($settings['last_updated']);
        
        debugLog("获取假人设置成功 - Session: $sessionKey, Settings: " . json_encode($settings));
        return $settings;
    } else {
        $stmt->close();
        debugLog("未找到假人设置 - Session: $sessionKey");
        // 返回默认设置
        return [
            'dummy_name' => '技术顾问',
            'dummy_avatar' => '/assets/img/dummy1.png',
            'is_dummy_mode' => false,
            'last_updated' => 0
        ];
    }
}

function checkDummySettingsUpdate($db, $sessionKey, $lastUpdateTime) {
    ensureDummySettingsTable($db);
    
    $query = "SELECT last_updated FROM dummy_settings WHERE session_key = ?";
    $stmt = $db->prepare($query);
    if (!$stmt) {
        return false;
    }
    
    $stmt->bind_param("s", $sessionKey);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $currentUpdateTime = strtotime($row['last_updated']);
        $stmt->close();
        
        return $currentUpdateTime > $lastUpdateTime;
    }
    
    $stmt->close();
    return false;
}

// ==================== 会话设置管理 ====================
function ensureSessionSettingsTable($db) {
    $checkTable = "SHOW TABLES LIKE 'chat_session_settings'";
    $result = $db->query($checkTable);
    if ($result->num_rows == 0) {
        $createTable = "CREATE TABLE chat_session_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            session_key VARCHAR(100) NOT NULL,
            agent_account VARCHAR(50) NOT NULL,
            setting_key VARCHAR(50) NOT NULL,
            setting_value VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_session_setting (session_key, agent_account, setting_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        if ($db->query($createTable)) {
            debugLog("chat_session_settings table created successfully");
        } else {
            debugLog("Failed to create chat_session_settings table: " . $db->error);
        }
    }
}

function getSessionSettings($db, $sessionKey, $agentAccount) {
    ensureSessionSettingsTable($db);
    
    $settings = [];
    
    $query = "SELECT setting_key, setting_value 
              FROM chat_session_settings 
              WHERE session_key = ? AND agent_account = ?";
    
    $stmt = $db->prepare($query);
    if (!$stmt) {
        debugLog("获取会话设置准备失败: " . $db->error);
        return $settings;
    }
    
    $stmt->bind_param("ss", $sessionKey, $agentAccount);
    if (!$stmt->execute()) {
        debugLog("获取会话设置执行失败: " . $stmt->error);
        $stmt->close();
        return $settings;
    }
    
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    
    $stmt->close();
    return $settings;
}

function updateSessionSetting($db, $sessionKey, $agentAccount, $settingKey, $settingValue) {
    ensureSessionSettingsTable($db);
    
    $query = "INSERT INTO chat_session_settings (session_key, agent_account, setting_key, setting_value) 
              VALUES (?, ?, ?, ?) 
              ON DUPLICATE KEY UPDATE 
              setting_value = VALUES(setting_value), updated_at = NOW()";
    
    $stmt = $db->prepare($query);
    if (!$stmt) {
        debugLog("更新会话设置准备失败: " . $db->error);
        return false;
    }
    
    $stmt->bind_param("ssss", $sessionKey, $agentAccount, $settingKey, $settingValue);
    $result = $stmt->execute();
    
    if (!$result) {
        debugLog("更新会话设置执行失败: " . $stmt->error);
    }
    
    $stmt->close();
    return $result;
}

// ==================== 工具函数 ====================
function getSessionKey($sessionId) {
    return SessionParser::getSessionKey($sessionId);
}

function validateAndProcessImage($imageData, $maxSize = 5242880) {
    $dataSize = strlen($imageData);
    if ($dataSize > $maxSize) {
        throw new Exception('图片数据过大');
    }
    
    if (preg_match('/^data:image\/(\w+);base64,/', $imageData, $matches)) {
        $imageData = substr($imageData, strpos($imageData, ',') + 1);
    }
    
    $imageBinary = base64_decode($imageData);
    if ($imageBinary === false) {
        throw new Exception('图片数据格式错误');
    }
    
    $imageInfo = @getimagesizefromstring($imageBinary);
    if ($imageInfo === false) {
        throw new Exception('无效的图片文件');
    }
    
    $allowedTypes = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG => 'png',
        IMAGETYPE_GIF => 'gif',
        IMAGETYPE_WEBP => 'webp',
        IMAGETYPE_BMP => 'bmp'
    ];
    
    $imageType = $imageInfo[2];
    if (!isset($allowedTypes[$imageType])) {
        throw new Exception('不支持的图片格式');
    }
    
    return [
        'binary' => $imageBinary,
        'extension' => $allowedTypes[$imageType],
        'mime_type' => $imageInfo['mime']
    ];
}

// ==================== API路由处理 ====================
try {
    $action = $_GET['action'] ?? ($input['action'] ?? '');
    
    // 添加详细的请求日志
    debugLog("接收到请求: action=$action, method=$method");
    
    // ==================== 在线状态接口 ====================
    if ($method == 'POST' && $action == 'update_online_status') {
    // 确保在输出JSON之前，开启输出缓冲（检查是否已存在缓冲区）
    if (ob_get_level() == 0) {
        ob_start();
    }
    
    // 设置响应头为JSON
    header('Content-Type: application/json; charset=UTF-8');
    
    $username = $input['username'] ?? '';
    $userType = $input['user_type'] ?? 'customer';
    $isOnline = $input['is_online'] ?? true;
    $windowStatus = $input['window_status'] ?? 'window_visible';
    $deviceType = $input['device_type'] ?? null;
    $browser = $input['browser'] ?? null;
    $os = $input['os'] ?? null;
    $userAgent = $input['user_agent'] ?? null;
    $sessionKey = $input['session_key'] ?? null;
    
    debugLog("收到在线状态更新: 用户={$username}, 类型={$userType}, 在线={$isOnline}, 窗口状态={$windowStatus}");
    
    if (empty($username)) {
        // 清除可能的输出
        if (ob_get_level() > 0) {
            ob_end_clean();
        }
        echo json_encode(['success' => false, 'message' => '用户名不能为空']);
        exit;
    }
    
    try {
        // 获取客户端IP
        $clientIP = getClientIP();
        
        // 转换布尔值为整数
        $onlineValue = $isOnline ? 1 : 0;
        
        $query = "INSERT INTO user_online_status 
                 (username, is_online, window_status, last_seen, last_activity, 
                  last_heartbeat, user_type, session_key, client_ip, user_agent,
                  device_type, browser, os) 
                 VALUES (?, ?, ?, NOW(), NOW(), NOW(), ?, ?, ?, ?, ?, ?, ?) 
                 ON DUPLICATE KEY UPDATE 
                 is_online = VALUES(is_online), 
                 window_status = VALUES(window_status), 
                 last_seen = NOW(), 
                 last_activity = VALUES(last_activity), 
                 last_heartbeat = NOW(),
                 session_key = VALUES(session_key),
                 client_ip = VALUES(client_ip),
                 user_agent = VALUES(user_agent),
                 device_type = VALUES(device_type),
                 browser = VALUES(browser),
                 os = VALUES(os)";
        
        debugLog("执行的SQL: {$query}");
        
        $stmt = $db->prepare($query);
        if (!$stmt) {
            throw new Exception("准备SQL失败: " . $db->error);
        }
        
        // 参数绑定类型字符串: s=string, i=int
        // username(s), onlineValue(i), windowStatus(s), userType(s), 
        // sessionKey(s), clientIP(s), userAgent(s), deviceType(s), browser(s), os(s)
        $bindResult = $stmt->bind_param("sissssssss", 
            $username, $onlineValue, $windowStatus, $userType, 
            $sessionKey, $clientIP, $userAgent, $deviceType, $browser, $os);
        
        if (!$bindResult) {
            throw new Exception("绑定参数失败: " . $stmt->error);
        }
        
        $executeResult = $stmt->execute();
        
        if (!$executeResult) {
            throw new Exception("执行SQL失败: " . $stmt->error);
        }
        
        $affectedRows = $stmt->affected_rows;
        $stmt->close();
        
        debugLog("状态更新成功: 用户={$username}, 影响行数={$affectedRows}");
        
        // 清除输出缓冲区，确保只输出JSON
        if (ob_get_level() > 0) {
            ob_end_clean();
        }
        echo json_encode([
            'success' => true, 
            'message' => '状态更新成功',
            'affected_rows' => $affectedRows
        ]);
        exit;
        
    } catch (Exception $e) {
        // 捕获异常，记录日志
        $errorMsg = "状态更新异常: " . $e->getMessage();
        debugLog($errorMsg);
        
        // 清除输出缓冲区
        if (ob_get_level() > 0) {
            ob_end_clean();
        }
        echo json_encode([
            'success' => false, 
            'message' => '状态更新失败',
            'error' => $errorMsg
        ]);
        exit;
    }
}

// 在 messages.php 中添加这个接口
if ($method == 'GET' && isset($_GET['action']) && $_GET['action'] == 'get_customer_full_info') {
    $customerName = $_GET['customer_name'] ?? '';
    $agentAccount = $_GET['agent_account'] ?? '';
    
    error_log("🔍 接口调用: get_customer_full_info, 客户={$customerName}, 客服={$agentAccount}");
    
    if (empty($customerName) || empty($agentAccount)) {
        echo json_encode(['success' => false, 'message' => '参数不完整']);
        exit;
    }
    
    // 查询IP信息
    $ipQuery = "SELECT client_ip FROM user_online_status 
                WHERE username = ? AND user_type = 'customer' 
                AND client_ip IS NOT NULL 
                AND client_ip != '0.0.0.0'
                ORDER BY last_heartbeat DESC 
                LIMIT 1";
    
    $ipStmt = $db->prepare($ipQuery);
    $clientIP = '未知';
    
    if ($ipStmt) {
        $ipStmt->bind_param("s", $customerName);
        $ipStmt->execute();
        $ipResult = $ipStmt->get_result();
        if ($ipRow = $ipResult->fetch_assoc()) {
            $clientIP = $ipRow['client_ip'];
        }
        $ipStmt->close();
    }
    
    // 查询设备信息
    $deviceQuery = "SELECT device_type, browser, os, window_status, last_heartbeat 
                    FROM user_online_status 
                    WHERE username = ? AND user_type = 'customer' 
                    ORDER BY last_heartbeat DESC LIMIT 1";
    
    $deviceStmt = $db->prepare($deviceQuery);
    $deviceInfo = [
        'device_type' => '未知',
        'browser' => '未知',
        'os' => '未知',
        'window_status' => 'window_closed',
        'last_heartbeat' => '未知'
    ];
    
    if ($deviceStmt) {
        $deviceStmt->bind_param("s", $customerName);
        $deviceStmt->execute();
        $deviceResult = $deviceStmt->get_result();
        if ($deviceRow = $deviceResult->fetch_assoc()) {
            $deviceInfo = $deviceRow;
        }
        $deviceStmt->close();
    }
    
    // 获取IP归属地
    $ipLocation = '未知';
    if ($clientIP && $clientIP !== '未知' && $clientIP !== '0.0.0.0') {
        $ipLocation = getIPLocation($clientIP);
    }
    
    // 判断IP类型
    $ipType = '未知';
    if ($clientIP && $clientIP !== '未知') {
        if (filter_var($clientIP, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $ipType = 'IPv4';
        } elseif (filter_var($clientIP, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $ipType = 'IPv6';
        }
    }
    
    // 获取消息相关信息
    $msgQuery = "SELECT platform, MAX(created_at) as last_message_time 
                 FROM chat_messages 
                 WHERE customer_name = ? AND agent_account = ?";
    
    $msgStmt = $db->prepare($msgQuery);
    $platform = '未知';
    $lastMessageTime = '未知';
    
    if ($msgStmt) {
        $msgStmt->bind_param("ss", $customerName, $agentAccount);
        $msgStmt->execute();
        $msgResult = $msgStmt->get_result();
        if ($msgRow = $msgResult->fetch_assoc()) {
            $platform = $msgRow['platform'] ?? '未知';
            $lastMessageTime = $msgRow['last_message_time'] ?? '未知';
        }
        $msgStmt->close();
    }
    
    // 获取最后一条消息
    $lastMsgQuery = "SELECT content FROM chat_messages 
                     WHERE customer_name = ? AND agent_account = ? 
                     ORDER BY created_at DESC LIMIT 1";
    
    $lastMsgStmt = $db->prepare($lastMsgQuery);
    $lastMessage = '暂无消息';
    
    if ($lastMsgStmt) {
        $lastMsgStmt->bind_param("ss", $customerName, $agentAccount);
        $lastMsgStmt->execute();
        $lastMsgResult = $lastMsgStmt->get_result();
        if ($lastMsgRow = $lastMsgResult->fetch_assoc()) {
            $lastMessage = $lastMsgRow['content'] ?? '暂无消息';
        }
        $lastMsgStmt->close();
    }
    
    $response = [
        'success' => true,
        'customer_info' => [
            'customer_name' => $customerName,
            'client_ip' => $clientIP,
            'ip_location' => $ipLocation,
            'ip_type' => $ipType,
            'platform' => $platform,
            'last_message_time' => $lastMessageTime,
            'last_message' => $lastMessage,
            'window_status' => $deviceInfo['window_status'],
            'device_type' => $deviceInfo['device_type'],
            'browser' => $deviceInfo['browser'],
            'os' => $deviceInfo['os'],
            'last_heartbeat' => $deviceInfo['last_heartbeat']
        ]
    ];
    
    echo json_encode($response);
    exit;
}

// 新增：获取客户在线状态
if ($method == 'POST' && $action == 'get_customer_online_status') {
    $username = $input['username'] ?? '';
    
    if (empty($username)) {
        echo json_encode(['success' => false, 'message' => '用户名不能为空']);
        exit;
    }
    
    $query = "SELECT is_online, window_status, last_heartbeat, 
                     TIMESTAMPDIFF(SECOND, last_heartbeat, NOW()) as seconds_ago
              FROM user_online_status 
              WHERE username = ? AND user_type = 'customer'
              ORDER BY last_heartbeat DESC 
              LIMIT 1";
    
    $stmt = $db->prepare($query);
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => '数据库查询失败']);
        exit;
    }
    
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        echo json_encode([
            'success' => true,
            'status' => [
                'is_online' => (bool)$row['is_online'],
                'window_status' => $row['window_status'],
                'seconds_ago' => $row['seconds_ago'],
                'last_heartbeat' => $row['last_heartbeat']
            ]
        ]);
    } else {
        // 没有记录，表示离线
        echo json_encode([
            'success' => true,
            'status' => [
                'is_online' => false,
                'window_status' => 'window_closed',
                'seconds_ago' => 9999, // 大数值表示很久没心跳
                'last_heartbeat' => null
            ]
        ]);
    }
    
    $stmt->close();
    exit;
}
    
    if ($action == 'get_online_status') {
        $usernames = $_GET['usernames'] ?? '';
        
        if (empty($usernames)) {
            echo json_encode([
                "success" => false, 
                "message" => "用户名不能为空"
            ]);
            exit;
        }
        
        $usernameList = explode(',', $usernames);
        $usernameList = array_map('trim', $usernameList);
        $usernameList = array_filter($usernameList);
        
        debugLog("查询在线状态: " . implode(',', $usernameList));
        
        $statuses = [];
        
        foreach ($usernameList as $username) {
            $statuses[$username] = false;
        }
        
        if (!empty($usernameList)) {
            $placeholders = str_repeat('?,', count($usernameList) - 1) . '?';
            $query = "SELECT username, is_online, last_seen FROM user_online_status WHERE username IN ($placeholders)";
            
            $stmt = $db->prepare($query);
            if ($stmt) {
                $types = str_repeat('s', count($usernameList));
                $stmt->bind_param($types, ...$usernameList);
                
                if ($stmt->execute()) {
                    $result = $stmt->get_result();
                    $now = time();
                    
                    while ($row = $result->fetch_assoc()) {
                        $username = $row['username'];
                        $isOnline = (bool)$row['is_online'];
                        $lastSeen = strtotime($row['last_seen']);
                        $timeDiff = $now - $lastSeen;
                        
                        if ($isOnline && $timeDiff <= 120) {
                            $statuses[$username] = true;
                        } else {
                            $statuses[$username] = false;
                        }
                        
                        debugLog("用户 {$username}: is_online={$isOnline}, 时间差={$timeDiff}秒, 最终状态=" . ($statuses[$username] ? '在线' : '离线'));
                    }
                } else {
                    debugLog("查询失败: " . $stmt->error);
                }
                $stmt->close();
            } else {
                debugLog("准备查询失败: " . $db->error);
            }
        }
        
        echo json_encode([
            "success" => true,
            "statuses" => $statuses
        ]);
        exit;
    }
    
   if ($method == 'POST' && $action == 'get_batch_online_status') {
    $usernames = $input['usernames'] ?? [];
    
    if (empty($usernames) || !is_array($usernames)) {
        echo json_encode(['success' => false, 'message' => '用户名列表不能为空']);
        exit;
    }
    
    $statuses = [];
    
    // 构建查询条件
    $placeholders = str_repeat('?,', count($usernames) - 1) . '?';
    $query = "SELECT username, is_online, window_status, last_heartbeat, 
                     TIMESTAMPDIFF(SECOND, last_heartbeat, NOW()) as seconds_ago
              FROM user_online_status 
              WHERE username IN ($placeholders) AND user_type = 'customer'";
    
    $stmt = $db->prepare($query);
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => '数据库查询失败']);
        exit;
    }
    
    $types = str_repeat('s', count($usernames));
    $stmt->bind_param($types, ...$usernames);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // 先初始化所有用户为离线
    foreach ($usernames as $username) {
        $statuses[$username] = false;
    }
    
    // 处理查询结果
    while ($row = $result->fetch_assoc()) {
        $username = $row['username'];
        $isOnline = (bool)$row['is_online'];
        $windowStatus = $row['window_status'];
        $secondsAgo = $row['seconds_ago'];
        
        debugLog("用户状态查询: {$username}, 在线={$isOnline}, 窗口={$windowStatus}, 心跳={$secondsAgo}秒前");
        
        // 新的状态判断逻辑
        if ($isOnline) {
            if ($secondsAgo <= 10) { // 10秒内心跳
                if ($windowStatus === 'window_visible') {
                    $statuses[$username] = 'online';
                } else if ($windowStatus === 'window_hidden') {
                    $statuses[$username] = 'hidden';
                } else {
                    $statuses[$username] = 'online';
                }
            } else if ($secondsAgo <= 20) { // 10-20秒
                $statuses[$username] = 'away'; // 离开
            } else {
                $statuses[$username] = false; // 离线
            }
        } else {
            $statuses[$username] = false; // 离线
        }
        
        debugLog("最终状态: {$username} => " . ($statuses[$username] ?: '离线'));
    }
    
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'statuses' => $statuses
    ]);
    exit;
}
    
    if ($method == 'POST' && $action == 'get_user_device_info') {
    $username = $input['username'] ?? '';
    $userType = $input['user_type'] ?? 'customer';
    
    if (empty($username)) {
        echo json_encode(['success' => false, 'message' => '用户名不能为空']);
        exit;
    }
    
    $query = "SELECT device_type, browser, os, user_agent, platform, 
                     client_ip, last_seen, window_status
              FROM user_online_status 
              WHERE username = ? AND user_type = ?
              ORDER BY last_seen DESC LIMIT 1";
    
    $stmt = $db->prepare($query);
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => '数据库查询失败']);
        exit;
    }
    
    $stmt->bind_param("ss", $username, $userType);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        echo json_encode([
            'success' => true,
            'device_info' => [
                'device_type' => $row['device_type'] ?? '未知',
                'browser' => $row['browser'] ?? '未知',
                'os' => $row['os'] ?? '未知',
                'user_agent' => $row['user_agent'] ?? '',
                'platform' => $row['platform'] ?? '',
                'client_ip' => $row['client_ip'] ?? '',
                'last_seen' => $row['last_seen'] ?? '',
                'window_status' => $row['window_status'] ?? 'window_closed'
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => '未找到设备信息']);
    }
    
    $stmt->close();
    exit;
}
    
    // ==================== 消息管理接口 ====================
  if ($method == 'POST' && $action == 'send_message') {
    // 兼容新旧参数名
    $sessionKey = $input['session_key'] ?? $input['session_id'] ?? '';
    $content = $input['content'] ?? '';
    $agentAccount = $input['agent_account'] ?? $input['agent'] ?? '';
    $customerName = $input['customer_name'] ?? $input['customer'] ?? '';
    $speakerType = $input['speaker_type'] ?? 1;
    $platform = $input['platform'] ?? '默认';

    debugLog("send_message接口接收到的完整参数: " . json_encode($input));
    debugLog("解析后的参数 - session_key: '$sessionKey', content: '$content', agent_account: '$agentAccount'");
    
    // 参数验证也要使用正确的变量名
    if (empty($sessionKey)) {
        debugLog("参数验证失败: session_key 为空");
        echo json_encode(['success' => false, 'message' => '会话ID不能为空']);
        exit;
    }
    
    if (empty($content)) {
        debugLog("参数验证失败: content 为空");
        echo json_encode(['success' => false, 'message' => '消息内容不能为空']);
        exit;
    }
    
    // 如果sessionKey看起来像sessionId，转换成session_key格式
    if (strpos($sessionKey, '_') === false && function_exists('getSessionKey')) {
        $sessionKey = getSessionKey($sessionKey);
    }
    
    // 添加数据库操作前的日志
    debugLog("准备插入消息到数据库: session_key=$sessionKey, agent_account=$agentAccount, content=$content");
    
    $query = "INSERT INTO chat_messages (session_key, agent_account, speaker_type, content, customer_name, platform) 
              VALUES (?, ?, ?, ?, ?, ?)";
    
    $stmt = $db->prepare($query);
    if (!$stmt) {
        $error = $db->error;
        debugLog("数据库准备失败: " . $error);
        echo json_encode(['success' => false, 'message' => '数据库操作失败: ' . $error]);
        exit;
    }
    
    $stmt->bind_param("ssisss", $sessionKey, $agentAccount, $speakerType, $content, $customerName, $platform);
    
    if ($stmt->execute()) {
        $messageId = $stmt->insert_id;
        debugLog("消息插入成功 - ID: $messageId");
        
        // ==================== 【新增】WebSocket 实时推送 ====================
        $wsPushed = false;
        $wsError = '';
        try {
            // 构建推送数据
$wsMessage = [
    'type' => 'send_message',  // 必须是 'send_message' 类型
    'session_key' => $sessionKey,
    'content' => $content,
    'agent_account' => $agentAccount,
    'customer_name' => $customerName,
    'speaker_type' => $speakerType,
    'platform' => $platform,
    'message_id' => $messageId,  // 数据库消息ID
    'from_api' => true,  // 标记来自API
    'created_at' => date('Y-m-d H:i:s')
];

// 推送到WebSocket服务器
$wsPushed = pushToWebSocket($wsMessage);
debugLog("WebSocket推送结果: " . ($wsPushed ? '成功' : '失败'));
            
        } catch (Exception $e) {
            $wsError = $e->getMessage();
            debugLog("WebSocket推送异常: " . $wsError);
        }
        // ==================== WebSocket推送结束 ====================
        
        // ==================== 【新增】直接发送Push通知 ====================
        $pushResult = null;
        $pushDebugLog = [];
        try {
            $pushDebugLog[] = "start: speakerType={$speakerType}, agentAccount={$agentAccount}, customerName={$customerName}";
            
            require_once $root . '/config/WebPush.php';
            $pushDebugLog[] = "webpush_loaded: YES";
            
            // 只有客户发消息(speakerType=1)才推送，客服消息(2)和假人消息(3)跳过
            if ($speakerType != 1) {
                $pushDebugLog[] = "skip: speakerType={$speakerType}, only type=1 needs push";
                @file_put_contents($root . '/push_debug.log', date('Y-m-d H:i:s') . " " . implode(" | ", $pushDebugLog) . "\n", FILE_APPEND);
                echo json_encode([
                    'success' => true, 
                    'message' => '消息发送成功', 
                    'message_id' => $messageId,
                    'ws_pushed' => $wsPushed,
                    'ws_error' => $wsError,
                    'push_sent' => false,
                    'push_debug' => $pushDebugLog
                ]);
                $stmt->close();
                exit;
            }
            
            // 客户发言 → 推送给客服(agent)
            $pushReceiverId = $agentAccount;
            $pushReceiverType = 'agent';
            $pushDebugLog[] = "receiver: id={$pushReceiverId}, type={$pushReceiverType}";
            
            // 获取网站基础 URL（用于构建完整的头像 URL）
            $isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
            $baseUrl = ($isHttps ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'zzcc.pro');
            
            // 查询该会话的平台信息（用于获取头像）
            $platform = '默认';
            $platformStmt = $db->prepare("SELECT COALESCE(platform, '默认') as platform FROM chat_messages WHERE session_key = ? AND platform IS NOT NULL AND platform != '' ORDER BY id DESC LIMIT 1");
            if ($platformStmt) {
                $platformStmt->bind_param("s", $sessionKey);
                $platformStmt->execute();
                $platformResult = $platformStmt->get_result();
                if ($platformRow = $platformResult->fetch_assoc()) {
                    $platform = $platformRow['platform'] ?? '默认';
                }
                $platformStmt->close();
            }
            $pushDebugLog[] = "platform={$platform}";
            
            // 根据平台获取头像（使用完整 URL）
            $avatarMap = [
                '闲鱼' => $baseUrl . '/assets/img/xianyulist.jpg',
                '闲鱼代练' => $baseUrl . '/assets/img/xy-logo.png',
                '闲鱼话费版本' => $baseUrl . '/assets/img/xy-logo.png',
                '转转' => $baseUrl . '/assets/img/zz-kf.png',
                '腾讯客服' => $baseUrl . '/assets/img/wechat.jpg',
                '盼之' => $baseUrl . '/assets/img/panzhi.png',
                '盼之群聊' => $baseUrl . '/assets/img/panzhi.png',
                '抖音' => $baseUrl . '/assets/img/douyin.png',
                '大麦' => $baseUrl . '/assets/img/dm-kf.png',
                '螃蟹' => $baseUrl . '/assets/img/pangxielist.png',
                '螃蟹群聊' => $baseUrl . '/assets/img/pangxielist.png',
                '白情' => $baseUrl . '/assets/img/bq-kf.jpg',
                '白情群聊' => $baseUrl . '/assets/img/bq-kf.jpg',
                '京东' => $baseUrl . '/assets/img/jd.png',
                '得物' => $baseUrl . '/assets/img/dw-kf.png',
                '钉钉' => $baseUrl . '/assets/img/dingding.png',
                '拼多多' => $baseUrl . '/assets/img/pdd.png',
                '自定义' => $baseUrl . '/assets/img/zidingyikefu.png',
                '自定义聊天' => $baseUrl . '/assets/img/wangluokefu.png',
                '交易猫' => $baseUrl . '/assets/img/jym-kf.png',
                '千岛' => $baseUrl . '/assets/img/qd-kefu.png',
                '银联' => $baseUrl . '/assets/img/yinlian.ico',
                '氪金兽' => $baseUrl . '/assets/img/kjs-kefu.jpg',
                '默认' => $baseUrl . '/assets/img/normal.png'
            ];
            $avatarIcon = isset($avatarMap[$platform]) ? $avatarMap[$platform] : $baseUrl . '/assets/img/normal.png';
            $pushDebugLog[] = "avatar_icon={$avatarIcon}";
            
            // 查询接收方的Push订阅
            $pushStmt = $db->prepare("SELECT endpoint, p256dh, auth_key FROM push_subscriptions WHERE user_id = ? AND user_type = ? LIMIT 1");
            if ($pushStmt) {
                $pushStmt->bind_param("ss", $pushReceiverId, $pushReceiverType);
                $pushStmt->execute();
                $pushSub = $pushStmt->get_result()->fetch_assoc();
                $pushStmt->close();
                
                $pushDebugLog[] = "subscription_found: " . ($pushSub ? 'YES' : 'NO');
                
                if ($pushSub && !empty($pushSub['endpoint']) && !empty($pushSub['p256dh']) && !empty($pushSub['auth_key'])) {
                    $vapidKeys = WebPush::loadFromConfig();
                    $publicKeyRaw = base64_decode($vapidKeys['publicKeyRaw']);
                    $webPush = new WebPush($publicKeyRaw, $vapidKeys['privateKeyPem'], 'mailto:admin@xe.com');
                    
                    $pushPayload = [
                        'title' => '对话 ' . $customerName,
                        'body' => mb_substr($content, 0, 50, 'UTF-8'),
                        'url' => '/consle/chat',
                        'sessionKey' => $sessionKey,
                        'tag' => 'msg-' . $messageId,
                        'icon' => $avatarIcon,
                    ];
                    
                    $pushResult = $webPush->sendNotification($pushSub['endpoint'], $pushSub['p256dh'], $pushSub['auth_key'], $pushPayload);
                    $pushDebugLog[] = "push_result: success=" . ($pushResult['success'] ? 'YES' : 'NO') . ", statusCode=" . $pushResult['statusCode'];
                } else {
                    $pushDebugLog[] = "skip: no valid subscription (endpoint=" . (!empty($pushSub['endpoint']) ? 'yes' : 'no') . ", p256dh=" . (!empty($pushSub['p256dh']) ? 'yes' : 'no') . ", auth=" . (!empty($pushSub['auth_key']) ? 'yes' : 'no') . ")";
                }
            } else {
                $pushDebugLog[] = "db_prepare_failed: " . $db->error;
            }
        } catch (Exception $e) {
            $pushDebugLog[] = "exception: " . $e->getMessage();
        }
        // 写入调试日志文件
        @file_put_contents($root . '/push_debug.log', date('Y-m-d H:i:s') . " " . implode(" | ", $pushDebugLog) . "\n", FILE_APPEND);
        // ==================== Push通知结束 ====================
        
        echo json_encode([
            'success' => true, 
            'message' => '消息发送成功', 
            'message_id' => $messageId,
            'ws_pushed' => $wsPushed,
            'ws_error' => $wsError,
            'push_sent' => $pushResult ? $pushResult['success'] : null,
            'push_debug' => $pushDebugLog
        ]);
    } else {
        $error = $stmt->error;
        debugLog("数据库执行失败: " . $error);
        echo json_encode([
            'success' => false, 
            'message' => '消息发送失败: ' . $error
        ]);
    }
    
    $stmt->close();
    exit;
}

// ==================== 标记消息已读接口 ====================

if ($method == 'POST' && $action == 'mark_read') {
    // 先检查 is_read 字段是否存在，不存在则添加
    $checkColumnSql = "SHOW COLUMNS FROM chat_messages LIKE 'is_read'";
    $checkResult = $db->query($checkColumnSql);
    if ($checkResult && $checkResult->num_rows === 0) {
        // 字段不存在，添加它
        $alterSql = "ALTER TABLE chat_messages ADD COLUMN is_read TINYINT(1) DEFAULT 0";
        $db->query($alterSql);
    }
    
    $messageIds = isset($_POST['message_ids']) ? json_decode($_POST['message_ids'], true) : [];
    $sessionKey = $_POST['session_key'] ?? $_POST['session_id'] ?? '';
    
    if (empty($messageIds) && empty($sessionKey)) {
        echo json_encode(['success' => false, 'message' => '缺少必要参数']);
        exit;
    }

    if (!empty($sessionKey) && strpos($sessionKey, '_') === false && function_exists('getSessionKey')) {
        $sessionKey = getSessionKey($sessionKey);
    }

    if (!empty($messageIds) && is_array($messageIds)) {
        $placeholders = implode(',', array_fill(0, count($messageIds), '?'));
        $sql = "UPDATE chat_messages SET is_read = 1 WHERE id IN ($placeholders)";
        $stmt = $db->prepare($sql);
        if ($stmt) {
            $types = str_repeat('i', count($messageIds));
            $stmt->bind_param($types, ...$messageIds);
            $stmt->execute();
            $affected = $stmt->affected_rows;
            $stmt->close();
            
            // 触发 WebSocket 推送通知客服端
            if ($affected > 0 && function_exists('sendWebSocketMessage')) {
                sendWebSocketMessage($sessionKey, [
                    'type' => 'messages_read',
                    'message_ids' => $messageIds
                ]);
            }
            
            echo json_encode(['success' => true, 'message' => "成功标记 $affected 条消息为已读", 'affected' => $affected]);
        } else {
            echo json_encode(['success' => false, 'message' => 'SQL 准备失败：' . $db->error]);
        }
    } elseif (!empty($sessionKey)) {
        // 获取需要标记的消息 ID
        $selectSql = "SELECT id FROM chat_messages WHERE session_key = ? AND speaker_type = 2 AND is_read = 0";
        $selectStmt = $db->prepare($selectSql);
        if ($selectStmt) {
            $selectStmt->bind_param('s', $sessionKey);
            $selectStmt->execute();
            $result = $selectStmt->get_result();
            $messageIds = [];
            while ($row = $result->fetch_assoc()) {
                $messageIds[] = $row['id'];
            }
            $selectStmt->close();
            
            // 更新已读状态
            $sql = "UPDATE chat_messages SET is_read = 1 WHERE session_key = ? AND speaker_type = 2 AND is_read = 0";
            $stmt = $db->prepare($sql);
            if ($stmt) {
                $stmt->bind_param('s', $sessionKey);
                $stmt->execute();
                $affected = $stmt->affected_rows;
                $stmt->close();
                
                // 触发 WebSocket 推送通知客服端
                if ($affected > 0 && function_exists('sendWebSocketMessage')) {
                    sendWebSocketMessage($sessionKey, [
                        'type' => 'messages_read',
                        'message_ids' => $messageIds
                    ]);
                }
                
                echo json_encode(['success' => true, 'message' => "成功标记 $affected 条消息为已读", 'affected' => $affected]);
            } else {
                echo json_encode(['success' => false, 'message' => 'SQL 准备失败：' . $db->error]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'SQL 准备失败：' . $db->error]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => '参数错误']);
    }
    exit;
}

if ($method == 'GET' && $action == 'get_messages') {
    $sessionKey = $_GET['session_key'] ?? $_GET['session_id'] ?? '';
    
    if (empty($sessionKey)) {
        echo json_encode(['success' => false, 'message' => '会话ID不能为空']);
        exit;
    }
    
    // 如果sessionKey看起来像sessionId，转换成session_key格式
    if (strpos($sessionKey, '_') === false && function_exists('getSessionKey')) {
        $sessionKey = getSessionKey($sessionKey);
    }
    
    $query = "SELECT 
                id, 
                agent_account, 
                speaker_type, 
                content, 
                customer_name, 
                message_type, 
                image_path,
                CONCAT('/uploads/', image_path) AS image_url,
                created_at,
                is_read
              FROM chat_messages 
              WHERE session_key = ? 
              ORDER BY created_at ASC";
              
    $stmt = $db->prepare($query);
    if (!$stmt) {
        debugLog("Prepare failed: " . $db->error);
        echo json_encode(["success" => false, "message" => "数据库查询失败"]);
        exit;
    }
    
    $stmt->bind_param("s", $sessionKey);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $messages = [];
    while ($row = $result->fetch_assoc()) {
        $messages[] = $row;
    }
    
    $stmt->close();
    
    echo json_encode([
        "success" => true, 
        "messages" => $messages
    ]);
    exit;
}
    
    if ($method == 'GET' && $action == 'poll_messages') {
    $sessionKey = $_GET['session_key'] ?? $_GET['session_id'] ?? '';
    $lastMessageId = $_GET['last_id'] ?? 0;
    $agentUsername = $_GET['agent_username'] ?? '';
    
    debugLog("poll_messages请求: session_key=$sessionKey, last_id=$lastMessageId, agent=$agentUsername");
    
    if (empty($sessionKey)) {
        echo json_encode(['success' => false, 'message' => '会话ID不能为空']);
        exit;
    }
    
    // 如果sessionKey看起来像sessionId，转换成session_key格式
    if (strpos($sessionKey, '_') === false && function_exists('getSessionKey')) {
        $sessionKey = getSessionKey($sessionKey);
    }
    
    $query = "SELECT id, agent_account, speaker_type, content, customer_name, remark, 
                     message_type, image_path, image_name, image_size,
                     CONCAT('/uploads/', image_path) AS image_url, created_at 
              FROM chat_messages 
              WHERE session_key = ? AND id > ? 
              ORDER BY created_at ASC";
    
    $stmt = $db->prepare($query);
    if (!$stmt) {
        $error = $db->error;
        debugLog("poll_messages准备失败: " . $error);
        echo json_encode(['success' => false, 'message' => '数据库查询失败: ' . $error]);
        exit;
    }
    
    $stmt->bind_param("si", $sessionKey, $lastMessageId);
    if (!$stmt->execute()) {
        $error = $stmt->error;
        debugLog("poll_messages执行失败: " . $error);
        echo json_encode(['success' => false, 'message' => '数据库查询执行失败: ' . $error]);
        $stmt->close();
        exit;
    }
    
    $result = $stmt->get_result();
    $newMessages = [];
    
    while ($row = $result->fetch_assoc()) {
        $newMessages[] = $row;
    }
    
    $stmt->close();
    
    debugLog("找到 " . count($newMessages) . " 条新消息");
    
    echo json_encode([
        'success' => true, 
        'messages' => $newMessages,
        'session_info' => ['session_key' => $sessionKey]
    ]);
    exit;
}
    
    if ($method == 'GET' && $action == 'poll_messages_with_dummy') {
        $sessionId = $_GET['session_id'] ?? '';
        $lastMessageId = $_GET['last_id'] ?? 0;
        $lastDummyUpdate = $_GET['last_dummy_update'] ?? 0;
        $agentUsername = $_GET['agent_username'] ?? '';
        
        debugLog("poll_messages_with_dummy - session_id: $sessionId, last_id: $lastMessageId, last_dummy_update: $lastDummyUpdate");
        
        if (!empty($agentUsername)) {
            updateOnlineStatus($db, $agentUsername, 'agent', true);
        }
        
        $sessionKey = getSessionKey($sessionId);
        
        $query = "SELECT id, agent_account, speaker_type, content, customer_name, remark, 
                         message_type, image_path, image_name, image_size,
                         CONCAT('/uploads/', image_path) AS image_url, created_at 
                  FROM chat_messages 
                  WHERE session_key = ? AND id > ? 
                  ORDER BY created_at ASC";
        $stmt = $db->prepare($query);
        if (!$stmt) {
            debugLog("Prepare failed in poll_messages_with_dummy: " . $db->error);
            echo json_encode(array("success" => false, "message" => "数据库查询失败"));
            exit;
        }
        
        $stmt->bind_param("si", $sessionKey, $lastMessageId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $newMessages = array();
        while ($row = $result->fetch_assoc()) {
            $messageData = array(
                'id' => $row['id'],
                'agent_account' => $row['agent_account'],
                'speaker_type' => $row['speaker_type'],
                'content' => $row['content'],
                'customer_name' => $row['customer_name'],
                'remark' => $row['remark'],
                'message_type' => $row['message_type'],
                'image_path' => $row['image_path'],
                'image_name' => $row['image_name'],
                'image_size' => $row['image_size'],
                'created_at' => $row['created_at']
            );
            
            if ($row['message_type'] === 'image' && $row['image_path']) {
                $messageData['image_url'] = '../uploads/' . $row['image_path'];
            }
            
            $newMessages[] = $messageData;
        }
        
        $stmt->close();
        
        $dummySettings = null;
        $hasDummyUpdate = checkDummySettingsUpdate($db, $sessionKey, $lastDummyUpdate);
        if ($hasDummyUpdate) {
            $dummySettings = getDummySettings($db, $sessionKey);
        }
        
        echo json_encode(array(
            "success" => true, 
            "messages" => $newMessages,
            "dummy_settings" => $dummySettings,
            "has_dummy_update" => $hasDummyUpdate,
            "session_info" => ['session_key' => $sessionKey]
        ));
        exit;
    }
    
    // ==================== 图片上传接口 ====================
    
    if ($method == 'POST' && $action == 'upload_image') {
    // 检查是否有文件上传
    if (isset($_FILES['image']) || isset($_FILES['image_file'])) {
        // 新版本的文件上传方式
        $file = isset($_FILES['image_file']) ? $_FILES['image_file'] : $_FILES['image'];
        $sessionKey = $_POST['session_key'] ?? $_POST['session_id'] ?? '';
        $agentAccount = $_POST['agent_account'] ?? '';
        $customerName = $_POST['customer_name'] ?? '';
        $speakerType = $_POST['speaker_type'] ?? 2;
        
        // 如果sessionKey看起来像sessionId，转换成session_key格式（与get_messages保持一致）
        if (strpos($sessionKey, '_') === false && function_exists('getSessionKey')) {
            $sessionKey = getSessionKey($sessionKey);
            error_log("sessionId转换为session_key: " . $sessionKey);
        }
        
        // 添加调试信息
        error_log("========== 开始图片上传 ==========");
        error_log("文件信息: " . print_r($file, true));
        error_log("POST参数: " . print_r($_POST, true));
        error_log("服务器文档根目录: " . $_SERVER['DOCUMENT_ROOT']);
        error_log("最终session_key: " . $sessionKey);
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errorMessages = [
                UPLOAD_ERR_INI_SIZE => '文件大小超过服务器限制',
                UPLOAD_ERR_FORM_SIZE => '文件大小超过表单限制',
                UPLOAD_ERR_PARTIAL => '文件只有部分被上传',
                UPLOAD_ERR_NO_FILE => '没有文件被上传',
                UPLOAD_ERR_NO_TMP_DIR => '找不到临时文件夹',
                UPLOAD_ERR_CANT_WRITE => '文件写入失败'
            ];
            $errorMsg = $errorMessages[$file['error']] ?? '未知上传错误';
            error_log("上传错误代码: " . $file['error'] . " - " . $errorMsg);
            echo json_encode(['success' => false, 'message' => $errorMsg]);
            exit;
        }
        
        try {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            $validExtensions = [
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'gif' => 'image/gif',
                'webp' => 'image/webp'
            ];
            
            // 首先尝试从文件扩展名判断类型
            if (array_key_exists($fileExtension, $validExtensions)) {
                $mimeType = $validExtensions[$fileExtension];
            } else {
                // 如果扩展名无法识别，尝试从 MIME 类型判断
                $detectedMime = $file['type'] ?? '';
                if (in_array($detectedMime, $allowedTypes)) {
                    // 根据 MIME 类型反向查找扩展名
                    $extMap = array_flip($validExtensions);
                    $fileExtension = $extMap[$detectedMime] ?? 'jpg';
                    $mimeType = $detectedMime;
                    error_log("通过MIME类型识别格式: {$detectedMime} -> {$fileExtension}");
                } else {
                    // 最后尝试用 finfo 检测真实文件类型
                    if (function_exists('finfo_open') && file_exists($file['tmp_name'])) {
                        $finfo = finfo_open(FILEINFO_MIME_TYPE);
                        $realMime = finfo_file($finfo, $file['tmp_name']);
                        finfo_close($finfo);
                        error_log("finfo检测到的MIME类型: {$realMime}");
                        
                        if (in_array($realMime, $allowedTypes)) {
                            $extMap = array_flip($validExtensions);
                            $fileExtension = $extMap[$realMime] ?? 'jpg';
                            $mimeType = $realMime;
                        } else {
                            throw new Exception('不支持的图片格式: ' . $fileExtension . ' (MIME: ' . $detectedMime . ', 真实类型: ' . $realMime . ')');
                        }
                    } else {
                        throw new Exception('不支持的图片格式: ' . $fileExtension . ' (MIME: ' . $detectedMime . ')');
                    }
                }
            }
            
            // 安全检测：检查文件内容是否包含 PHP 代码（防止图片马）
            $fileContent = file_get_contents($file['tmp_name']);
            if (preg_match('/<\?php|<\?=/i', $fileContent)) {
                throw new Exception('文件包含非法内容');
            }
            
            // 使用项目根目录的绝对路径（兼容 Windows 和 Linux）
            $projectRoot = dirname(dirname(__DIR__));
            $uploadDir = rtrim($projectRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;
            
            error_log("项目根目录：" . $projectRoot);
            error_log("上传目录计算结果：" . $uploadDir);
            error_log("DOCUMENT_ROOT：" . ($_SERVER['DOCUMENT_ROOT'] ?? '未设置'));
            error_log("PHP 版本：" . PHP_VERSION);
            error_log("操作系统：" . PHP_OS);
            
            // 检查目录是否存在，不存在则创建
            if (!is_dir($uploadDir)) {
                error_log("目录不存在，尝试创建: " . $uploadDir);
                if (!mkdir($uploadDir, 0755, true)) {
                    $lastError = error_get_last();
                    error_log("创建目录失败: " . print_r($lastError, true));
                    throw new Exception('无法创建上传目录: ' . $uploadDir);
                }
                error_log("目录创建成功");
            }
            
            // 检查目录权限
            if (!is_writable($uploadDir)) {
                $perms = substr(sprintf('%o', fileperms($uploadDir)), -4);
                error_log("目录不可写: " . $uploadDir . " 权限: " . $perms);
                throw new Exception('上传目录不可写，当前权限: ' . $perms);
            }
            
            // 检查磁盘空间
            $freeSpace = disk_free_space($uploadDir);
            error_log("磁盘剩余空间: " . round($freeSpace / 1024 / 1024, 2) . "MB");
            if ($freeSpace < 1024 * 1024) { // 小于1MB
                throw new Exception('磁盘空间不足，剩余: ' . round($freeSpace / 1024 / 1024, 2) . 'MB');
            }
            
            $filename = uniqid() . '.' . $fileExtension;
            $filePath = $uploadDir . $filename;
            
            error_log("目标文件路径: " . $filePath);
            
            // 检查文件是否已存在
            if (file_exists($filePath)) {
                error_log("文件已存在，重新生成文件名");
                $filename = uniqid() . '_' . time() . '.' . $fileExtension;
                $filePath = $uploadDir . $filename;
            }
            
            // 检查临时文件
            if (!file_exists($file['tmp_name'])) {
                error_log("临时文件不存在: " . $file['tmp_name']);
                throw new Exception('临时文件不存在');
            }
            
            if (!is_uploaded_file($file['tmp_name'])) {
                error_log("不是有效的上传文件: " . $file['tmp_name']);
                throw new Exception('无效的上传文件');
            }
            
            // 移动文件
            if (!move_uploaded_file($file['tmp_name'], $filePath)) {
                $lastError = error_get_last();
                error_log("move_uploaded_file 失败: " . print_r($lastError, true));
                error_log("临时文件大小: " . filesize($file['tmp_name']));
                error_log("目标文件是否存在: " . (file_exists($filePath) ? '是' : '否'));
                throw new Exception('文件保存失败: move_uploaded_file 返回 false');
            }
            
            // 验证文件是否真的保存成功
            if (!file_exists($filePath)) {
                error_log("文件保存后检查不存在: " . $filePath);
                throw new Exception('文件保存失败: 保存后文件不存在');
            }
            
            $fileSize = filesize($filePath);
            if ($fileSize === 0) {
                error_log("保存的文件大小为0: " . $filePath);
                unlink($filePath);
                throw new Exception('文件保存失败: 保存的文件为空');
            }
            
            error_log("文件保存成功，大小: " . $fileSize . " bytes");
            
            // ==================== 【关键修复2】图片URL路径 ====================
            $imageUrl = "/uploads/{$filename}";
            error_log("图片URL: " . $imageUrl);
            
            $query = "INSERT INTO chat_messages 
                     (session_key, agent_account, speaker_type, content, 
                      customer_name, message_type, image_path, image_name, image_size) 
                     VALUES (?, ?, ?, ?, ?, 'image', ?, ?, ?)";
            
            error_log("SQL查询: " . $query);
            
            $stmt = $db->prepare($query);
            if (!$stmt) {
                error_log("数据库准备失败: " . $db->error);
                throw new Exception('数据库准备失败: ' . $db->error);
            }
            
            $content = '[图片]';
            error_log("绑定参数: " . print_r([
                'sessionKey' => $sessionKey,
                'agentAccount' => $agentAccount,
                'speakerType' => $speakerType,
                'content' => $content,
                'customerName' => $customerName,
                'filename' => $filename,
                'imageName' => $file['name'],
                'imageSize' => $file['size']
            ], true));
            
            $stmt->bind_param("ssissssi", 
                $sessionKey, $agentAccount, $speakerType, $content, 
                $customerName, $filename, $file['name'], $file['size']
            );
            
            if (!$stmt->execute()) {
                error_log("数据库执行失败: " . $stmt->error);
                throw new Exception('数据库执行失败: ' . $stmt->error);
            }
            
            $messageId = $stmt->insert_id;
            $stmt->close();
            
            error_log("消息ID: " . $messageId);
            
            // 保存客户端 IP
            
            // ==================== 【关键修复】添加WebSocket推送 ====================
            try {
                // 构建推送数据
                $wsMessage = [
                    'type' => 'send_message',
                    'session_key' => $sessionKey,
                    'content' => '[图片]',
                    'agent_account' => $agentAccount,
                    'customer_name' => $customerName,
                    'speaker_type' => $speakerType,
                    'platform' => '默认',
                    'message_type' => 'image',
                    'image_url' => $imageUrl,
                    'message_id' => $messageId,
                    'from_api' => true,
                    'created_at' => date('Y-m-d H:i:s')
                ];
                
                // 推送到WebSocket服务器
                $wsPushed = pushToWebSocket($wsMessage);
                error_log("图片上传 - WebSocket推送结果: " . ($wsPushed ? '成功' : '失败'));
            } catch (Exception $e) {
                error_log("图片上传 - WebSocket推送异常: " . $e->getMessage());
            }
            // ==================== WebSocket推送结束 ====================
            
            // ==================== 【新增】直接发送Push通知 ====================
            try {
                require_once $root . '/config/WebPush.php';
                
                // 只有客户发消息(speakerType=1)才推送，客服消息(2)和假人消息(3)跳过
                if ($speakerType != 1) {
                    error_log("图片Push通知: speakerType={$speakerType}, only type=1 needs push, skipped");
                } else {
                    // 客户发言 → 推送给客服(agent)
                    $pushReceiverId = $agentAccount;
                    $pushReceiverType = 'agent';
                    
                    // 获取网站基础 URL（用于构建完整的头像 URL）
                    $isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
                    $baseUrl = ($isHttps ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'zzcc.pro');
                    
                    // 查询该会话的平台信息（用于获取头像）
                    $platform = '默认';
                    $platformStmt = $db->prepare("SELECT COALESCE(platform, '默认') as platform FROM chat_messages WHERE session_key = ? AND platform IS NOT NULL AND platform != '' ORDER BY id DESC LIMIT 1");
                    if ($platformStmt) {
                        $platformStmt->bind_param("s", $sessionKey);
                        $platformStmt->execute();
                        $platformResult = $platformStmt->get_result();
                        if ($platformRow = $platformResult->fetch_assoc()) {
                            $platform = $platformRow['platform'] ?? '默认';
                        }
                        $platformStmt->close();
                    }
                    error_log("图片Push通知: platform={$platform}");
                    
                    // 根据平台获取头像（使用完整 URL）
                    $avatarMap = [
                        '闲鱼' => $baseUrl . '/assets/img/xianyulist.jpg',
                        '闲鱼代练' => $baseUrl . '/assets/img/xy-logo.png',
                        '闲鱼话费版本' => $baseUrl . '/assets/img/xy-logo.png',
                        '转转' => $baseUrl . '/assets/img/zz-kf.png',
                        '腾讯客服' => $baseUrl . '/assets/img/wechat.jpg',
                        '盼之' => $baseUrl . '/assets/img/panzhi.png',
                        '盼之群聊' => $baseUrl . '/assets/img/panzhi.png',
                        '抖音' => $baseUrl . '/assets/img/douyin.png',
                        '大麦' => $baseUrl . '/assets/img/dm-kf.png',
                        '螃蟹' => $baseUrl . '/assets/img/pangxielist.png',
                        '螃蟹群聊' => $baseUrl . '/assets/img/pangxielist.png',
                        '白情' => $baseUrl . '/assets/img/bq-kf.jpg',
                        '白情群聊' => $baseUrl . '/assets/img/bq-kf.jpg',
                        '京东' => $baseUrl . '/assets/img/jd.png',
                        '得物' => $baseUrl . '/assets/img/dw-kf.png',
                        '钉钉' => $baseUrl . '/assets/img/dingding.png',
                        '拼多多' => $baseUrl . '/assets/img/pdd.png',
                        '自定义' => $baseUrl . '/assets/img/zidingyikefu.png',
                        '自定义聊天' => $baseUrl . '/assets/img/wangluokefu.png',
                        '交易猫' => $baseUrl . '/assets/img/jym-kf.png',
                        '千岛' => $baseUrl . '/assets/img/qd-kefu.png',
                        '银联' => $baseUrl . '/assets/img/yinlian.ico',
                        '氪金兽' => $baseUrl . '/assets/img/kjs-kefu.jpg',
                        '默认' => $baseUrl . '/assets/img/normal.png'
                    ];
                    $avatarIcon = isset($avatarMap[$platform]) ? $avatarMap[$platform] : $baseUrl . '/assets/img/normal.png';
                    
                    $pushStmt = $db->prepare("SELECT endpoint, p256dh, auth_key FROM push_subscriptions WHERE user_id = ? AND user_type = ? LIMIT 1");
                    if ($pushStmt) {
                        $pushStmt->bind_param("ss", $pushReceiverId, $pushReceiverType);
                        $pushStmt->execute();
                        $pushSub = $pushStmt->get_result()->fetch_assoc();
                        $pushStmt->close();
                        
                        if ($pushSub && !empty($pushSub['endpoint']) && !empty($pushSub['p256dh']) && !empty($pushSub['auth_key'])) {
                            $vapidKeys = WebPush::loadFromConfig();
                            $publicKeyRaw = base64_decode($vapidKeys['publicKeyRaw']);
                            $webPush = new WebPush($publicKeyRaw, $vapidKeys['privateKeyPem'], 'mailto:admin@xe.com');
                            
                            $pushPayload = [
                                'title' => '对话 ' . $customerName,
                                'body' => '[图片]',
                                'url' => '/consle/chat',
                                'sessionKey' => $sessionKey,
                                'tag' => 'msg-' . $messageId,
                                'icon' => $avatarIcon,
                            ];
                            
                            $pushResult = $webPush->sendNotification($pushSub['endpoint'], $pushSub['p256dh'], $pushSub['auth_key'], $pushPayload);
                            error_log("图片Push通知: receiver={$pushReceiverId}, success=" . ($pushResult['success'] ? 'YES' : 'NO'));
                        }
                    }
                }
            } catch (Exception $e) {
                error_log("图片Push通知异常: " . $e->getMessage());
            }
            // ==================== Push通知结束 ====================
            
            $response = [
                'success' => true,
                'message_id' => $messageId,
                'image_url' => $imageUrl
            ];
            
            error_log("返回响应: " . json_encode($response));
            
            echo json_encode($response);
            
        } catch (Exception $e) {
            debugLog("图片上传失败: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'message' => '上传失败: ' . $e->getMessage()
            ]);
        }
    } 
    // 兼容base64上传方式
      else if (isset($input['image_data'])) {
            $sessionId = $input['session_id'] ?? '';
            $customerName = $input['customer_name'] ?? '';
            $agentAccount = $input['agent_account'] ?? '';
            $imageData = $input['image_data'] ?? '';
            $imageName = $input['image_name'] ?? '';
            $imageSize = $input['image_size'] ?? 0;
            $speakerType = $input['speaker_type'] ?? 1;
            $platform = $input['platform'] ?? '默认';
        
            if (empty($sessionId) || empty($imageData)) {
                echo json_encode(['success' => false, 'message' => '缺少必要参数']);
                exit;
            }
        
            try {
                // 使用项目根目录的绝对路径（兼容 Windows 和 Linux）
            $projectRoot = dirname(dirname(__DIR__));
            $uploadDir = rtrim($projectRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;
                
                error_log("Base64 上传 - 项目根目录：" . $projectRoot);
                error_log("Base64 上传 - 上传目录：" . $uploadDir);
                
                // 检查目录是否存在，不存在则创建
                if (!is_dir($uploadDir)) {
                    error_log("Base64 上传 - 目录不存在，尝试创建");
                    if (!mkdir($uploadDir, 0755, true)) {
                        throw new Exception('无法创建上传目录');
                    }
                }
                
                // 检查目录权限
                if (!is_writable($uploadDir)) {
                    $perms = substr(sprintf('%o', fileperms($uploadDir)), -4);
                    throw new Exception('上传目录不可写，权限：' . $perms);
                }
            
                $imageInfo = validateAndProcessImage($imageData);
                $fileExtension = $imageInfo['extension'];
                $imageBinary = $imageInfo['binary'];
            
                $originalName = pathinfo($imageName, PATHINFO_FILENAME);
                $timestamp = date('Ymd_His');
                $randomChars = substr(str_shuffle('abcdefghijklmnopqrstuvwxyz0123456789'), 0, 4);
                $filename = $originalName . '_' . $timestamp . '_' . $randomChars . '.' . $fileExtension;
                $filePath = $uploadDir . $filename;
            
                if (file_put_contents($filePath, $imageBinary)) {
                    $sessionKey = getSessionKey($sessionId);
                
                    $query = "INSERT INTO chat_messages 
                             (session_key, agent_account, speaker_type, content, customer_name, message_type, image_path, image_name, image_size, platform, created_at) 
                             VALUES (?, ?, ?, ?, ?, 'image', ?, ?, ?, ?, NOW())";
                
                    $stmt = $db->prepare($query);
                    if (!$stmt) {
                        throw new Exception('数据库准备失败: ' . $db->error);
                    }
                    
                    $content = '[图片]';
                    $stmt->bind_param("ssissssis", $sessionKey, $agentAccount, $speakerType, $content, $customerName, $filename, $imageName, $imageSize, $platform);
                    
                    if ($stmt->execute()) {
                        $messageId = $stmt->insert_id;
                    
                        $senderUsername = $speakerType == 2 ? $agentAccount : $customerName;
                        $userType = $speakerType == 2 ? 'agent' : 'customer';
                        updateOnlineStatus($db, $senderUsername, $userType, true);

                        echo json_encode([
                            'success' => true, 
                            'message_id' => $messageId,
                            'image_url' => '/uploads/' . $filename,
                            'filename' => $filename,
                            'message_type' => 'image',
                            'speaker_type' => $speakerType
                        ]);
                    } else {
                        throw new Exception('数据库插入失败: ' . $stmt->error);
                    }
                
                    $stmt->close();
                } else {
                    throw new Exception('图片文件保存失败');
                }
            
            } catch (Exception $e) {
                debugLog("Base64上传错误: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => '上传失败: ' . $e->getMessage()]);
            }
        
    } else {
        echo json_encode(['success' => false, 'message' => '没有接收到图片数据']);
    }
    exit;
}
    
    // ==================== 会话管理接口 ====================
    if ($method == 'GET' && $action == 'get_recent_sessions') {
        $agentAccount = $_GET['agent_account'] ?? '';
        $since = $_GET['since'] ?? date('Y-m-d H:i:s', strtotime('-1 hour'));
        
        if (empty($agentAccount)) {
            echo json_encode(array("success" => false, "message" => "客服账号不能为空"));
            exit;
        }
        
        $query = "SELECT 
                    sq.session_key,
                    sq.customer_name,
                    sq.last_message_time,
                    sq.last_message,
                    COALESCE(cm.platform, '默认') as platform
                  FROM (
                      SELECT 
                          session_key,
                          customer_name,
                          MAX(created_at) as last_message_time,
                          (SELECT content FROM chat_messages cm2 
                           WHERE cm2.session_key = cm.session_key 
                           ORDER BY cm2.created_at DESC LIMIT 1) as last_message
                      FROM chat_messages cm
                      WHERE cm.agent_account = ? 
                      AND cm.created_at > ?
                      GROUP BY session_key, customer_name
                  ) sq
                  LEFT JOIN chat_messages cm ON sq.session_key = cm.session_key 
                      AND sq.last_message_time = cm.created_at
                  ORDER BY sq.last_message_time DESC";
        
        $stmt = $db->prepare($query);
        if (!$stmt) {
            debugLog("Prepare failed in get_recent_sessions: " . $db->error);
            echo json_encode(array("success" => false, "message" => "数据库查询准备失败"));
            exit;
        }
        
        $stmt->bind_param("ss", $agentAccount, $since);
        if (!$stmt->execute()) {
            debugLog("Execute failed in get_recent_sessions: " . $stmt->error);
            $stmt->close();
            echo json_encode(array("success" => false, "message" => "数据库查询执行失败"));
            exit;
        }
        
        $result = $stmt->get_result();
        $sessions = array();
        
        while ($row = $result->fetch_assoc()) {
            $sessions[] = array(
                'session_key' => $row['session_key'],
                'customer_name' => $row['customer_name'],
                'last_message_time' => $row['last_message_time'],
                'last_message' => $row['last_message'] ?? '新客户咨询',
                'platform' => $row['platform']
            );
        }
        
        $stmt->close();
        
        debugLog("修复后返回的会话数据: " . json_encode($sessions));
        
        echo json_encode(array(
            "success" => true, 
            "sessions" => $sessions,
            "count" => count($sessions)
        ));
        exit;
    }
    
    if ($method == 'GET' && $action == 'get_client_ip') {
        $clientIP = getClientIP();
        echo json_encode([
            "success" => true,
            "client_ip" => $clientIP
        ]);
        exit;
    }
    
    if ($method == 'POST' && $action == 'detect_client_ip') {
        $sessionId = $input['session_id'] ?? '';
        $customerName = $input['customer_name'] ?? '';
        
        $clientIP = getClientIP();
        
        if ($clientIP !== '0.0.0.0') {
            $sessionKey = getSessionKey($sessionId);
            echo json_encode([
                "success" => true,
                "client_ip" => $clientIP,
                "message" => "IP检测成功"
            ]);
        } else {
            echo json_encode([
                "success" => false,
                "client_ip" => "unknown",
                "message" => "无法检测到有效IP"
            ]);
        }
        exit;
    }
    
    if ($method == 'POST' && $action == 'save_client_ip') {
        $sessionId = $input['session_id'] ?? '';
        $customerName = $input['customer_name'] ?? '';
        $clientIP = $input['client_ip'] ?? '';
        $platform = $input['platform'] ?? '默认';
        
        debugLog("收到IP保存请求: session_id=$sessionId, customer=$customerName, ip=$clientIP");
        
        if (empty($sessionId) || empty($customerName)) {
            debugLog("IP保存失败: 参数不全");
            echo json_encode(['success' => false, 'message' => '参数不全']);
            exit;
        }
        
        if ($clientIP === 'unknown' || empty($clientIP)) {
            debugLog("IP保存失败: IP地址无效");
            echo json_encode(['success' => false, 'message' => 'IP地址无效']);
            exit;
        }
        
        $sessionKey = getSessionKey($sessionId);
        
        // 使用新的保存方法
        // saveClientIP 已注释
        $result = true;
        
        if ($result) {
            debugLog("IP保存成功");
            echo json_encode(['success' => true, 'message' => 'IP保存成功']);
        } else {
            debugLog("IP保存失败");
            echo json_encode(['success' => false, 'message' => 'IP保存失败']);
        }
        exit;
    }
    
    if ($method == 'POST' && $action == 'save_ip_simple') {
        $sessionKey = $input['session_key'] ?? '';
        $customerName = $input['customer_name'] ?? '';
        $clientIP = $input['client_ip'] ?? '';
        $platform = $input['platform'] ?? '默认';
        
        if (empty($sessionKey) || empty($customerName)) {
            echo json_encode(['success' => false, 'message' => '参数不全']);
            exit;
        }
        
        $query = "INSERT INTO client_ips (session_key, customer_name, client_ip, platform) 
                  VALUES (?, ?, ?, ?) 
                  ON DUPLICATE KEY UPDATE 
                  client_ip = VALUES(client_ip), 
                  last_seen = CURRENT_TIMESTAMP";
        
        $stmt = $db->prepare($query);
        if (!$stmt) {
            echo json_encode(['success' => false, 'message' => '数据库准备失败']);
            exit;
        }
        
        $stmt->bind_param("ssss", $sessionKey, $customerName, $clientIP, $platform);
        $result = $stmt->execute();
        
        if ($result) {
            echo json_encode(['success' => true, 'message' => 'IP保存成功']);
        } else {
            echo json_encode(['success' => false, 'message' => 'IP保存失败: ' . $stmt->error]);
        }
        
        $stmt->close();
        exit;
    }
    
    // ==================== 假人模式接口 ====================
    if ($method == 'POST' && $action == 'broadcast_dummy_settings') {
        $sessionKey = $input['session_key'] ?? '';
        $dummyName = $input['dummy_name'] ?? '技术顾问';
        $dummyAvatar = $input['dummy_avatar'] ?? '/assets/img/dummy1.png';
        $isDummyMode = $input['is_dummy_mode'] ?? false;
        
        if (empty($sessionKey)) {
            echo json_encode(['success' => false, 'message' => '会话密钥不能为空']);
            exit;
        }
        
        $result = broadcastDummySettings($db, $sessionKey, $dummyName, $dummyAvatar, $isDummyMode);
        
        if ($result) {
            echo json_encode([
                'success' => true, 
                'message' => '假人设置广播成功',
                'last_updated' => time()
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => '假人设置广播失败']);
        }
        exit;
    }
    
    if ($method == 'POST' && $action == 'get_dummy_settings') {
        $sessionId = $input['session_id'] ?? '';
        
        if (empty($sessionId)) {
            echo json_encode(['success' => false, 'message' => '会话ID不能为空']);
            exit;
        }
        
        $sessionKey = getSessionKey($sessionId);
        $settings = getDummySettings($db, $sessionKey);
        
        echo json_encode([
            'success' => true,
            'dummy_settings' => $settings,
            'last_updated' => $settings['last_updated']
        ]);
        exit;
    }
    
    if ($method == 'POST' && $action == 'check_dummy_update') {
        $sessionId = $input['session_id'] ?? '';
        $lastUpdateTime = $input['last_update_time'] ?? 0;
        
        if (empty($sessionId)) {
            echo json_encode(['success' => false, 'message' => '会话ID不能为空']);
            exit;
        }
        
        $sessionKey = getSessionKey($sessionId);
        $hasUpdate = checkDummySettingsUpdate($db, $sessionKey, $lastUpdateTime);
        
        if ($hasUpdate) {
            $settings = getDummySettings($db, $sessionKey);
            echo json_encode([
                'success' => true,
                'has_update' => true,
                'dummy_settings' => $settings,
                'last_updated' => $settings['last_updated']
            ]);
        } else {
            echo json_encode([
                'success' => true,
                'has_update' => false,
                'last_updated' => $lastUpdateTime
            ]);
        }
        exit;
    }
    
    // ==================== 会话设置接口 ====================
    if ($method == 'GET' && $action == 'get_session_settings') {
        $sessionKey = $_GET['session_key'] ?? '';
        $agentAccount = $_GET['agent_account'] ?? '';
        
        if (empty($sessionKey) || empty($agentAccount)) {
            echo json_encode(['success' => false, 'message' => '会话ID和客服账号不能为空']);
            exit;
        }
        
        $settings = getSessionSettings($db, $sessionKey, $agentAccount);
        
        echo json_encode([
            'success' => true,
            'settings' => $settings
        ]);
        exit;
    }
    
    if ($method == 'POST' && $action == 'update_session_setting') {
        $sessionKey = $input['session_key'] ?? '';
        $agentAccount = $input['agent_account'] ?? '';
        $settingKey = $input['setting_key'] ?? '';
        $settingValue = $input['setting_value'] ?? '';
        
        if (empty($sessionKey) || empty($agentAccount) || empty($settingKey)) {
            echo json_encode(['success' => false, 'message' => '参数不完整']);
            exit;
        }
        
        $result = updateSessionSetting($db, $sessionKey, $agentAccount, $settingKey, $settingValue);
        
        if ($result) {
            echo json_encode(['success' => true, 'message' => '设置更新成功']);
        } else {
            echo json_encode(['success' => false, 'message' => '设置更新失败']);
        }
        exit;
    }
    
    if ($method == 'POST' && $action == 'clear_chat_messages') {
        $sessionKey = $input['session_key'] ?? '';
        $agentAccount = $input['agent_account'] ?? '';
        
        debugLog("clear_chat_messages请求参数: " . json_encode($input));
        debugLog("解析后的参数 - session_key: '$sessionKey', agent_account: '$agentAccount'");
        
        if (empty($sessionKey) || empty($agentAccount)) {
            echo json_encode(['success' => false, 'message' => '会话ID和客服账号不能为空']);
            exit;
        }
        
        $query = "DELETE FROM chat_messages 
                  WHERE session_key = ? AND agent_account = ?";
        
        $stmt = $db->prepare($query);
        if (!$stmt) {
            $error = $db->error;
            debugLog("清空聊天记录准备失败: " . $error);
            echo json_encode(['success' => false, 'message' => '数据库操作失败: ' . $error]);
            exit;
        }
        
        $stmt->bind_param("ss", $sessionKey, $agentAccount);
        
        if ($stmt->execute()) {
            $affectedRows = $stmt->affected_rows;
            debugLog("成功清空会话 {$sessionKey} 的聊天记录，影响行数: {$affectedRows}");
            echo json_encode([
                'success' => true,
                'message' => '聊天记录已清空',
                'affected_rows' => $affectedRows
            ]);
        } else {
            $error = $stmt->error;
            debugLog("清空聊天记录失败: " . $error);
            echo json_encode([
                'success' => false,
                'message' => '清空失败: ' . $error
            ]);
        }
        
        $stmt->close();
        exit;
    }
    
    // 删除选中消息接口
    if ($method == 'POST' && $action == 'delete_selected_messages') {
        $sessionKey = $input['session_key'] ?? '';
        $agentAccount = $input['agent_account'] ?? '';
        $messageIds = $input['message_ids'] ?? [];
        
        debugLog("delete_selected_messages 请求参数：session_key={$sessionKey}, agent_account={$agentAccount}, message_ids=" . json_encode($messageIds));
        
        if (empty($sessionKey) || empty($agentAccount) || empty($messageIds)) {
            debugLog("删除失败：参数为空 - session_key={$sessionKey}, agent_account={$agentAccount}, message_ids count=" . (is_array($messageIds) ? count($messageIds) : 'not array'));
            echo json_encode(['success' => false, 'message' => '会话 ID、客服账号和消息 ID 不能为空']);
            exit;
        }
        
        if (!is_array($messageIds)) {
            echo json_encode(['success' => false, 'message' => '消息ID必须是数组']);
            exit;
        }
        
        $placeholders = implode(',', array_fill(0, count($messageIds), '?'));
        
        $query = "DELETE FROM chat_messages 
                  WHERE session_key = ? AND agent_account = ? AND id IN ($placeholders)";
        
        $stmt = $db->prepare($query);
        if (!$stmt) {
            $error = $db->error;
            debugLog("删除选中消息准备失败: " . $error);
            echo json_encode(['success' => false, 'message' => '数据库操作失败: ' . $error]);
            exit;
        }
        
        // 修复：正确构建类型字符串
        $types = 'ss' . str_repeat('i', count($messageIds));
        $bindArgs = array_merge([$sessionKey, $agentAccount], $messageIds);
        
        debugLog("绑定类型：{$types}, 参数：" . json_encode($bindArgs));
        
           // 先测试一下不使用预编译语句的删除
        $testIds = implode(',', array_map('intval', $messageIds));
        $testQuery = "DELETE FROM chat_messages WHERE session_key = '{$sessionKey}' AND agent_account = '{$agentAccount}' AND id IN ({$testIds})";
        debugLog("测试 SQL: {$testQuery}");
        $testResult = $db->query($testQuery);
        debugLog("测试删除影响行数：" . $db->affected_rows);
        
        $stmt->bind_param($types, ...$bindArgs);
        
        if ($stmt->execute()) {
            $affectedRows = $stmt->affected_rows;
            debugLog("成功删除会话 {$sessionKey} 的 {$affectedRows} 条消息");
            echo json_encode([
                'success' => true,
                'message' => '消息删除成功',
                'affected_rows' => $affectedRows
            ]);
        } else {
            $error = $stmt->error;
            debugLog("删除选中消息失败: " . $error);
            echo json_encode([
                'success' => false,
                'message' => '删除失败: ' . $error
            ]);
        }
        
        $stmt->close();
        exit;
    }
    
// 获取聊天室列表（拉群功能）
if ($method == 'POST' && $action == 'get_chatroom_list') {
    // 确保session可用
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    $platform = $input['platform'] ?? '';
    $user_id = $_SESSION['user_id'] ?? 0;
    $agent_account = $input['agent_account'] ?? '';
    
    // 如果session中没有user_id，尝试通过agent_account查询
    if (empty($user_id) && !empty($agent_account)) {
        $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->bind_param("s", $agent_account);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $user_id = $row['id'];
        }
        $stmt->close();
    }
    
    if (empty($platform) || empty($user_id)) {
        echo json_encode(['success' => false, 'message' => '参数不完整', 'debug' => ['user_id' => $user_id, 'platform' => $platform]]);
        exit;
    }
    
    $chatrooms = [];
    
    if ($platform === '螃蟹群聊' || $platform === '螃蟹') {
        $stmt = $db->prepare("SELECT XEpxb7_id, XEpxb7_product_name, XEpxb7_product_code, XEpxb7_product_image, XEpxb7_page_code, XEpxb7_share_link FROM XEpxb7 WHERE XEpxb7_user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $chatrooms[] = [
                'id' => $row['XEpxb7_id'],
                'product_name' => $row['XEpxb7_product_name'],
                'product_code' => $row['XEpxb7_product_code'],
                'product_image' => $row['XEpxb7_product_image'],
                'page_code' => $row['XEpxb7_page_code'],
                'share_link' => $row['XEpxb7_share_link'] ?? ''
            ];
        }
        $stmt->close();
    } elseif ($platform === '盼之群聊' || $platform === '盼之') {
        $stmt = $db->prepare("SELECT XEpzds_id, XEpzds_product_name, XEpzds_product_code, XEpzds_product_image, XEpzds_page_code, XEpzds_share_link FROM XEpzds WHERE XEpzds_user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $chatrooms[] = [
                'id' => $row['XEpzds_id'],
                'product_name' => $row['XEpzds_product_name'],
                'product_code' => $row['XEpzds_product_code'],
                'product_image' => $row['XEpzds_product_image'],
                'page_code' => $row['XEpzds_page_code'],
                'share_link' => $row['XEpzds_share_link'] ?? ''
            ];
        }
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => '不支持的平台类型']);
        exit;
    }
    
    echo json_encode(['success' => true, 'chatrooms' => $chatrooms]);
    exit;
}

// 保存分享链接（拉群功能）
if ($method == 'POST' && $action == 'save_share_link') {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    $platform = $input['platform'] ?? '';
    $chatroom_id = intval($input['chatroom_id'] ?? 0);
    $share_link = $input['share_link'] ?? '';
    $user_id = $_SESSION['user_id'] ?? 0;
    $agent_account = $input['agent_account'] ?? '';
    
    if (empty($user_id) && !empty($agent_account)) {
        $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->bind_param("s", $agent_account);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $user_id = $row['id'];
        }
        $stmt->close();
    }
    
    if (empty($chatroom_id) || empty($share_link) || empty($user_id)) {
        echo json_encode(['success' => false, 'message' => '参数不完整']);
        exit;
    }
    
    $share_link = $db->real_escape_string($share_link);
    
    if ($platform === '螃蟹群聊' || $platform === '螃蟹') {
        $stmt = $db->prepare("UPDATE XEpxb7 SET XEpxb7_share_link = ? WHERE XEpxb7_id = ? AND XEpxb7_user_id = ?");
        $stmt->bind_param("sii", $share_link, $chatroom_id, $user_id);
    } elseif ($platform === '盼之群聊' || $platform === '盼之') {
        $stmt = $db->prepare("UPDATE XEpzds SET XEpzds_share_link = ? WHERE XEpzds_id = ? AND XEpzds_user_id = ?");
        $stmt->bind_param("sii", $share_link, $chatroom_id, $user_id);
    } else {
        echo json_encode(['success' => false, 'message' => '不支持的平台类型']);
        exit;
    }
    
    $success = $stmt->execute();
    $stmt->close();
    
    echo json_encode(['success' => $success]);
    exit;
}

    // 新增：根据客户名称和客服账号查询XEDATA令牌
if ($method == 'GET' && $action == 'get_xedata_by_customer') {
    $customerName = $_GET['customer_name'] ?? '';
    $agentAccount = $_GET['agent_account'] ?? '';
    
    if (empty($customerName) || empty($agentAccount)) {
        echo json_encode(['success' => false, 'message' => '参数不完整']);
        exit;
    }
    
    // 查询最新的XEDATA令牌
    $query = "SELECT xedata_token, session_id, created_at, expires_at, status 
              FROM `XE-SKDJWKSNCDATA` 
              WHERE customer_name = ? AND agent_account = ? 
              AND (status = 'active' OR status IS NULL)
              ORDER BY created_at DESC 
              LIMIT 1";
    
    $stmt = $db->prepare($query);
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => '数据库查询失败']);
        exit;
    }
    
    $stmt->bind_param("ss", $customerName, $agentAccount);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        echo json_encode([
            'success' => true,
            'xedata_info' => $row
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => '未找到XEDATA令牌记录',
            'debug' => [
                'customer' => $customerName,
                'agent' => $agentAccount
            ]
        ]);
    }
    
    $stmt->close();
    exit;
}
    
if ($method == 'POST' && $action == 'block_user') {
    $xedataToken = $input['xedata_token'] ?? '';
    $customerName = $input['customer_name'] ?? '';
    $agentAccount = $input['agent_account'] ?? '';
    $reason = $input['reason'] ?? '客服手动拉黑';
    
    if (empty($customerName) || empty($agentAccount)) {
        echo json_encode(['success' => false, 'message' => '客户名称和客服账号不能为空']);
        exit;
    }
    
    try {
        // 如果提供了XEDATA令牌，直接使用
        // 如果没有提供，先查询最新的XEDATA令牌
        if (empty($xedataToken)) {
            $tokenQuery = "SELECT xedata_token 
                          FROM `XE-SKDJWKSNCDATA` 
                          WHERE customer_name = ? AND agent_account = ? 
                          AND (status = 'active' OR status IS NULL)
                          ORDER BY created_at DESC 
                          LIMIT 1";
            
            $tokenStmt = $db->prepare($tokenQuery);
            if (!$tokenStmt) {
                throw new Exception("查询XEDATA令牌失败: " . $db->error);
            }
            
            $tokenStmt->bind_param("ss", $customerName, $agentAccount);
            $tokenStmt->execute();
            $tokenResult = $tokenStmt->get_result();
            
            if ($tokenRow = $tokenResult->fetch_assoc()) {
                $xedataToken = $tokenRow['xedata_token'];
            } else {
                throw new Exception("该用户没有找到可用的XEDATA令牌");
            }
            
            $tokenStmt->close();
        }
        
        // 记录拉黑操作
        error_log("🔨 拉黑用户: 客户={$customerName}, 客服={$agentAccount}, XEDATA={$xedataToken}");
        
        // 更新XE-SKDJWKSNCDATA表
        $updateQuery = "UPDATE `XE-SKDJWKSNCDATA` 
                       SET status = 'expired', 
                           expires_at = NOW(), 
                           is_used = 1 
                       WHERE xedata_token = ? AND customer_name = ? AND agent_account = ?";
        
        $updateStmt = $db->prepare($updateQuery);
        if (!$updateStmt) {
            throw new Exception("准备更新语句失败: " . $db->error);
        }
        
        $updateStmt->bind_param("sss", $xedataToken, $customerName, $agentAccount);
        $updateResult = $updateStmt->execute();
        
        if (!$updateResult) {
            throw new Exception("更新XEDATA状态失败: " . $updateStmt->error);
        }
        
        $affectedRows = $updateStmt->affected_rows;
        $updateStmt->close();
        
        // 更新user_online_status表
        $offlineQuery = "UPDATE user_online_status 
                        SET is_online = 0, 
                            window_status = 'window_closed',
                            last_heartbeat = NOW() 
                        WHERE username = ? AND user_type = 'customer'";
        
        $offlineStmt = $db->prepare($offlineQuery);
        if ($offlineStmt) {
            $offlineStmt->bind_param("s", $customerName);
            $offlineStmt->execute();
            $offlineStmt->close();
        }
        
        echo json_encode([
            'success' => true,
            'message' => '用户已成功拉黑',
            'data' => [
                'customer_name' => $customerName,
                'agent_account' => $agentAccount,
                'xedata_token' => $xedataToken,
                'affected_rows' => $affectedRows,
                'reason' => $reason,
                'timestamp' => date('Y-m-d H:i:s')
            ]
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => '拉黑失败: ' . $e->getMessage()
        ]);
    }
    exit;
}

// 检查用户是否被拉黑
if ($method == 'POST' && $action == 'check_block_status') {
    $xedataToken = $input['xedata_token'] ?? '';
    $customerName = $input['customer_name'] ?? '';
    
    if (empty($xedataToken) || empty($customerName)) {
        echo json_encode(['success' => false, 'message' => '参数不完整']);
        exit;
    }
    
    $query = "SELECT status, expires_at 
              FROM `XE-SKDJWKSNCDATA` 
              WHERE xedata_token = ? AND customer_name = ? 
              AND status = 'expired'";
    
    $stmt = $db->prepare($query);
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => '数据库查询失败']);
        exit;
    }
    
    $stmt->bind_param("ss", $xedataToken, $customerName);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $isBlocked = $result->num_rows > 0;
    $blockInfo = null;
    
    if ($isBlocked && $row = $result->fetch_assoc()) {
        $blockInfo = [
            'status' => $row['status'],
            'expires_at' => $row['expires_at'],
            'expired_time' => $row['expires_at']
        ];
    }
    
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'is_blocked' => $isBlocked,
        'block_info' => $blockInfo
    ]);
    exit;
}
    
    // 在文件末尾添加，在最后的catch之前
if ($method == 'GET' && $action == 'test_ip_query') {
    $customerName = $_GET['customer_name'] ?? '';
    $agentAccount = $_GET['agent_account'] ?? '';
    
    if (empty($customerName)) {
        echo json_encode(['success' => false, 'message' => '客户名称不能为空']);
        exit;
    }
    
    // 测试1：直接查询user_online_status表
    $testQuery1 = "SELECT username, client_ip, last_heartbeat, user_type 
                  FROM user_online_status 
                  WHERE username = ?";
    
    $stmt1 = $db->prepare($testQuery1);
    $result1 = [];
    if ($stmt1) {
        $stmt1->bind_param("s", $customerName);
        $stmt1->execute();
        $resultSet1 = $stmt1->get_result();
        while ($row = $resultSet1->fetch_assoc()) {
            $result1[] = $row;
        }
        $stmt1->close();
    }
    
    // 测试2：查询所有customer用户
    $testQuery2 = "SELECT username, client_ip, last_heartbeat, user_type 
                  FROM user_online_status 
                  WHERE user_type = 'customer' 
                  ORDER BY last_heartbeat DESC 
                  LIMIT 10";
    
    $result2 = [];
    $stmt2 = $db->query($testQuery2);
    if ($stmt2) {
        while ($row = $stmt2->fetch_assoc()) {
            $result2[] = $row;
        }
    }
    
    // 测试3：从chat_messages表获取最新会话
    $testQuery3 = "SELECT customer_name, session_key, created_at 
                  FROM chat_messages 
                  WHERE customer_name = ? 
                  ORDER BY created_at DESC 
                  LIMIT 1";
    
    $sessionKey = '';
    $stmt3 = $db->prepare($testQuery3);
    if ($stmt3) {
        $stmt3->bind_param("s", $customerName);
        $stmt3->execute();
        $resultSet3 = $stmt3->get_result();
        if ($row = $resultSet3->fetch_assoc()) {
            $sessionKey = $row['session_key'];
        }
        $stmt3->close();
    }
    
    echo json_encode([
        'success' => true,
        'tests' => [
            'test1_user_online_status' => $result1,
            'test2_all_customers' => $result2,
            'test3_chat_messages' => [
                'session_key' => $sessionKey,
                'customer_name' => $customerName
            ]
        ],
        'debug_info' => [
            'customer_name' => $customerName,
            'agent_account' => $agentAccount,
            'current_time' => date('Y-m-d H:i:s')
        ]
    ]);
    exit;
}

// ==================== Push通知诊断接口 ====================
if ($method == 'GET' && $action == 'test_push') {
    $userId = $_GET['user_id'] ?? 'admin';
    $userType = $_GET['user_type'] ?? 'agent';
    
    $result = ['step' => 'init', 'errors' => []];
    
    try {
        // Step 0: 列出所有订阅记录
        $allSubs = $db->query("SELECT user_id, user_type, LENGTH(p256dh) as p256dh_len, LENGTH(auth_key) as auth_len, endpoint, created_at, updated_at FROM push_subscriptions ORDER BY updated_at DESC");
        $result['all_subscriptions'] = [];
        if ($allSubs) {
            while ($row = $allSubs->fetch_assoc()) {
                $row['endpoint'] = substr($row['endpoint'], 0, 60) . '...';
                $result['all_subscriptions'][] = $row;
            }
        }
        $result['total_subscriptions'] = count($result['all_subscriptions']);
        
        // Step 1: 加载WebPush
        $result['step'] = 'load_webpush';
        require_once $root . '/config/WebPush.php';
        $result['webpush_loaded'] = true;
        
        // Step 2: 加载VAPID密钥
        $result['step'] = 'load_vapid';
        $vapidKeys = WebPush::loadFromConfig();
        $result['vapid_public_key'] = substr($vapidKeys['publicKeyB64'], 0, 20) . '...';
        
        // Step 3: 查询订阅
        $result['step'] = 'query_subscription';
        $stmt = $db->prepare("SELECT * FROM push_subscriptions WHERE user_id = ? AND user_type = ? LIMIT 1");
        $stmt->bind_param("ss", $userId, $userType);
        $stmt->execute();
        $sub = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$sub) {
            $result['error'] = "用户 {$userId} ({$userType}) 没有Push订阅记录";
            echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        $result['subscription'] = [
            'user_id' => $sub['user_id'],
            'user_type' => $sub['user_type'],
            'endpoint' => substr($sub['endpoint'], 0, 60) . '...',
            'p256dh_len' => strlen($sub['p256dh']),
            'auth_key_len' => strlen($sub['auth_key']),
        ];
        
        // Step 4: 发送推送
        $result['step'] = 'send_push';
        $publicKeyRaw = base64_decode($vapidKeys['publicKeyRaw']);
        $webPush = new WebPush($publicKeyRaw, $vapidKeys['privateKeyPem'], 'mailto:admin@xe.com');
        
        $pushPayload = [
            'title' => 'API诊断测试',
            'body' => '如果你看到这条通知，说明API推送正常',
            'url' => '/consle/chat',
            'sessionKey' => 'test',
            'tag' => 'test-' . time(),
        ];
        
        $pushResult = $webPush->sendNotification($sub['endpoint'], $sub['p256dh'], $sub['auth_key'], $pushPayload);
        $result['push_result'] = $pushResult;
        
    } catch (Exception $e) {
        $result['error'] = $e->getMessage();
        $result['error_trace'] = $e->getTraceAsString();
    }
    
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    "success" => false,
    "message" => "无效的操作",
    "available_actions" => [
        "消息管理" => ["send_message", "get_messages", "poll_messages", "poll_messages_with_dummy", 
                     "upload_image", "get_recent_sessions"],
        "IP 管理" => ["get_client_ip"],
        "在线状态" => ["update_online_status", "get_online_status", "get_batch_online_status"],
        "假人模式" => ["broadcast_dummy_settings", "get_dummy_settings", "check_dummy_update"],
        "会话设置" => ["get_session_settings", "update_session_setting", "clear_chat_messages", "delete_selected_messages"]
    ]
]);

} catch (Exception $e) {
    // 捕获异常，但不输出 HTML，只输出 JSON
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '服务器错误：' . $e->getMessage()]);
}
exit;
?>