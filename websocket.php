<?php 

// websocket_final.php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/WebPush.php';

use Workerman\Worker;
use Workerman\Connection\TcpConnection;
use Workerman\Timer;

// 设置日志
Worker::$logFile = '/tmp/websocket_final.log';
Worker::$stdoutFile = '/tmp/websocket_final_stdout.log';

// 安全地包含配置文件
function safeInclude($file) {
    $path = __DIR__ . '/config/' . $file;
    if (!file_exists($path)) {
        error_log("文件不存在: {$path}");
        return false;
    }
    
    // 检查文件内容
    $content = file_get_contents($path);
    $content = ltrim($content);
    
    // 检查是否以 <?php 开头
    if (strpos($content, '<?php') !== 0) {
        error_log("文件 {$file} 不以 <?php 开头，可能有输出");
        return false;
    }
    
    // 使用输出缓冲捕获任何输出
    ob_start();
    try {
        $result = include_once $path;
        $output = ob_get_clean();
        if (!empty($output)) {
            error_log("包含 {$file} 时有输出: " . substr($output, 0, 200));
        }
        return $result;
    } catch (Exception $e) {
        ob_end_clean();
        error_log("包含 {$file} 异常: " . $e->getMessage());
        return false;
    }
}

// 安全地获取数据库连接
function getSafeDB() {
    static $db = null;
    
    // 包含 dbconfig.php
    if (!safeInclude('dbconfig.php')) {
        error_log("包含 dbconfig.php 失败");
        return false;
    }
    
    if (!function_exists('getDB')) {
        error_log("getDB 函数不存在");
        return false;
    }
    
    // 检查缓存的连接是否仍然可用
    if ($db !== null) {
        if ($db->ping()) {
            return $db;
        }
        // 连接已断开，需要重连
        echo "[" . date('Y-m-d H:i:s') . "] ⚠️ 数据库连接已断开，尝试重连...\n";
        $db = null;
    }
    
    try {
        $db = getDB();
        if (!$db) {
            error_log("getDB() 返回 false");
            return false;
        }
        
        // 测试连接
        if (!$db->ping()) {
            error_log("数据库连接不可用");
            $db = null;
            return false;
        }
        
        return $db;
    } catch (Exception $e) {
        error_log("获取数据库连接异常: " . $e->getMessage());
        $db = null;
        return false;
    }
}

// ==================== 【新增】数据库状态更新函数 ====================

/**
 * 发送Push离线通知
 */
function sendPushNotification($receiverId, $receiverType, $messageData) {
    try {
        $db = getSafeDB();
        if (!$db) {
            error_log("[Push] 数据库不可用，跳过推送");
            return false;
        }
        
        // 查询接收方的Push订阅
        $stmt = $db->prepare("SELECT endpoint, p256dh, auth_key FROM push_subscriptions WHERE user_id = ? AND user_type = ? LIMIT 1");
        $stmt->bind_param("ss", $receiverId, $receiverType);
        $stmt->execute();
        $result = $stmt->get_result();
        $sub = $result->fetch_assoc();
        $stmt->close();
        
        if (!$sub || empty($sub['endpoint']) || empty($sub['p256dh']) || empty($sub['auth_key'])) {
            echo "[" . date('Y-m-d H:i:s') . "] 📱 用户 {$receiverId} ({$receiverType}) 无Push订阅，跳过离线推送\n";
            return false;
        }
        
        echo "[" . date('Y-m-d H:i:s') . "] 📱 准备发送Push通知给: {$receiverId} ({$receiverType})\n";
        echo "[" . date('Y-m-d H:i:s') . "]    endpoint: " . substr($sub['endpoint'], 0, 60) . "...\n";
        echo "[" . date('Y-m-d H:i:s') . "]    p256dh长度: " . strlen($sub['p256dh']) . " chars\n";
        echo "[" . date('Y-m-d H:i:s') . "]    auth_key长度: " . strlen($sub['auth_key']) . " chars\n";
        
        // 加载VAPID密钥
        $vapidKeys = WebPush::loadFromConfig();
        $publicKeyRaw = base64_decode($vapidKeys['publicKeyRaw']);
        $privateKeyPem = $vapidKeys['privateKeyPem'];
        
        // 获取网站域名作为subject
        $subject = 'mailto:admin@xe.com';
        
        $webPush = new WebPush($publicKeyRaw, $privateKeyPem, $subject);
        
        // 获取客户名称和平台信息
        $customerName = $messageData['customer_name'] ?? '客户';
        $sessionKey = $messageData['session_key'] ?? '';
        $platform = $messageData['platform'] ?? '默认';
        
        // 如果 platform 为空，从数据库查询
        if (empty($platform) || $platform === '默认') {
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
        }
        
        // 网站基础 URL（用于构建完整的头像 URL）
        $baseUrl = 'https://zzcc.pro';
        
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
        
        // 构建推送数据
        $pushPayload = [
            'title' => '对话 ' . $customerName,
            'body' => mb_substr($messageData['content'] ?? '您有一条新消息', 0, 50, 'UTF-8'),
            'url' => '/consle/chat',
            'sessionKey' => $messageData['session_key'] ?? '',
            'tag' => 'msg-' . ($messageData['message_id'] ?? uniqid()),
            'icon' => $avatarIcon,
        ];
        
        // 如果是图片消息
        if (isset($messageData['message_type']) && $messageData['message_type'] === 'image') {
            $pushPayload['body'] = '[图片]';
        }
        
        $pushResult = $webPush->sendNotification($sub['endpoint'], $sub['p256dh'], $sub['auth_key'], $pushPayload);
        
        if ($pushResult['success']) {
            echo "[" . date('Y-m-d H:i:s') . "] 📱 Push离线通知发送成功: {$receiverId}\n";
        } else {
            echo "[" . date('Y-m-d H:i:s') . "] ❌ Push离线通知发送失败: {$receiverId}, HTTP {$pushResult['statusCode']}, Error: {$pushResult['error']}\n";
            if (!empty($pushResult['responseBody'])) {
                echo "[" . date('Y-m-d H:i:s') . "]    响应内容: {$pushResult['responseBody']}\n";
            }
            
            // 如果是410 Gone，说明订阅已失效，删除数据库记录
            if ($pushResult['statusCode'] == 410) {
                $delStmt = $db->prepare("DELETE FROM push_subscriptions WHERE endpoint = ?");
                $delStmt->bind_param("s", $sub['endpoint']);
                $delStmt->execute();
                $delStmt->close();
                echo "[" . date('Y-m-d H:i:s') . "] 🗑️ 已删除失效的Push订阅: {$receiverId}\n";
            }
        }
        
        return $pushResult['success'];
    } catch (Exception $e) {
        echo "[" . date('Y-m-d H:i:s') . "] ❌ Push通知异常: " . $e->getMessage() . "\n";
        return false;
    }
}

/**
 * 更新用户在线状态到数据库
 */
function updateUserOnlineStatus($username, $userType, $isOnline, $windowStatus = 'window_visible', $sessionKey = '', $clientIp = '0.0.0.0') {
    try {
        $db = getSafeDB();
        if (!$db) {
            error_log("❌ 数据库连接失败，无法更新在线状态");
            return false;
        }
        
        $onlineValue = $isOnline ? 1 : 0;
        
        $query = "INSERT INTO user_online_status 
                 (username, is_online, window_status, last_seen, last_activity, 
                  last_heartbeat, user_type, session_key, client_ip, user_agent) 
                 VALUES (?, ?, ?, NOW(), NOW(), NOW(), ?, ?, ?, ?) 
                 ON DUPLICATE KEY UPDATE 
                 is_online = VALUES(is_online), 
                 window_status = VALUES(window_status), 
                 last_seen = NOW(), 
                 last_activity = VALUES(last_activity), 
                 last_heartbeat = NOW(),
                 session_key = VALUES(session_key),
                 client_ip = VALUES(client_ip)";
        
        $stmt = $db->prepare($query);
        if (!$stmt) {
            error_log("❌ 准备SQL失败: " . $db->error);
            return false;
        }
        
        $userAgent = 'WebSocket Client';
        $stmt->bind_param("sisssss", 
            $username, $onlineValue, $windowStatus, $userType, 
            $sessionKey, $clientIp, $userAgent);
        
        $executeResult = $stmt->execute();
        
        if (!$executeResult) {
            error_log("❌ 执行SQL失败: " . $stmt->error);
            $stmt->close();
            return false;
        }
        
        $affectedRows = $stmt->affected_rows;
        $stmt->close();
        
        $statusText = $isOnline ? '在线' : '离线';
        echo "[" . date('Y-m-d H:i:s') . "] 📊 数据库状态更新: {$username} ({$userType}) => {$statusText}\n";
        
        return true;
    } catch (Exception $e) {
        error_log("❌ 更新用户状态异常: " . $e->getMessage());
        return false;
    }
}

/**
 * 更新用户最后心跳时间
 */
function updateUserHeartbeat($username, $userType) {
    try {
        $db = getSafeDB();
        if (!$db) {
            return false;
        }
        
        $query = "UPDATE user_online_status 
                 SET last_heartbeat = NOW(),
                     is_online = 1
                 WHERE username = ? AND user_type = ?";
        
        $stmt = $db->prepare($query);
        if (!$stmt) {
            return false;
        }
        
        $stmt->bind_param("ss", $username, $userType);
        $stmt->execute();
        $stmt->close();
        
        return true;
    } catch (Exception $e) {
        return false;
    }
}

// ==================== 【新增】HTTP API 服务 ====================

/**
 * 设置HTTP API服务器
 */
function setupHttpApi($host = '127.0.0.1', $port = 8289) {
    $httpSocket = @stream_socket_server("tcp://$host:$port", $errno, $errstr);
    
    if (!$httpSocket) {
        echo "[" . date('Y-m-d H:i:s') . "] ❌ HTTP API 启动失败: $errstr ($errno)\n";
        return false;
    }
    
    echo "[" . date('Y-m-d H:i:s') . "] ✅ HTTP API 已启动: http://$host:$port\n";
    return $httpSocket;
}

/**
 * 处理HTTP请求
 */

function handleHttpRequest($socket, $wsConnections) {
    $connection = @stream_socket_accept($socket);
    
    if (!$connection) {
        return;
    }
    
    $request = fread($connection, 8192);
    
    if (empty($request)) {
        fclose($connection);
        return;
    }
    
    // 解析请求
    $lines = explode("\r\n", $request);
    $requestLine = explode(' ', $lines[0]);
    $method = $requestLine[0];
    $path = $requestLine[1];
    
    // 解析查询参数
    $urlParts = parse_url($path);
    $path = $urlParts['path'] ?? '/';
    $query = $urlParts['query'] ?? '';
    parse_str($query, $params);
    
    // 【修复】添加完整的CORS头
    $origin = isset($lines[1]) && strpos($lines[1], 'Origin: ') === 0 ? 
              substr($lines[1], 8) : '*';
    
    $corsHeaders = [
        "HTTP/1.1 200 OK",
        "Content-Type: application/json; charset=utf-8",
        "Access-Control-Allow-Origin: " . $origin,
        "Access-Control-Allow-Methods: GET, POST, OPTIONS",
        "Access-Control-Allow-Headers: Content-Type, Authorization",
        "Access-Control-Allow-Credentials: true",
        "Access-Control-Max-Age: 86400"  // 24小时
    ];
    
    // 处理OPTIONS预检请求
    if ($method === 'OPTIONS') {
        fwrite($connection, implode("\r\n", $corsHeaders) . "\r\n\r\n");
        fclose($connection);
        echo "[" . date('Y-m-d H:i:s') . "] 🌐 处理OPTIONS预检请求，来自: {$origin}\n";
        return;
    }
    
    // 只处理特定路径
    if ($method === 'GET' && $path === '/api/online_status') {
        $response = getOnlineClientsJson($wsConnections, $params);
        
        // 发送响应
        fwrite($connection, implode("\r\n", $corsHeaders) . "\r\n\r\n");
        fwrite($connection, $response);
        echo "[" . date('Y-m-d H:i:s') . "] 🌐 处理HTTP API请求: {$path}\n";
    } else if ($method === 'GET' && $path === '/api/stats') {
        // 获取完整统计信息
        $response = getFullStatsJson($wsConnections);
        
        // 发送响应
        fwrite($connection, implode("\r\n", $corsHeaders) . "\r\n\r\n");
        fwrite($connection, $response);
        echo "[" . date('Y-m-d H:i:s') . "] 🌐 处理统计 API 请求：{$path}\n";
    } else {
        fwrite($connection, "HTTP/1.1 404 Not Found\r\n\r\n");
    }
    
    fclose($connection);
}

/**
 * 获取在线客户端JSON格式
 */
function getOnlineClientsJson($connections, $params) {
    $agentAccount = $params['agent_account'] ?? '';
    $customers = isset($params['customers']) ? explode(',', $params['customers']) : [];
    
    $onlineStatuses = [];
    $onlineClients = [];
    
    // 【关键修复】检查连接池结构
    echo "[" . date('Y-m-d H:i:s') . "] 🔍 HTTP API查询连接池:\n";
    echo "[" . date('Y-m-d H:i:s') . "]   - agents: " . (isset($connections['agents']) ? count($connections['agents']) : 0) . "\n";
    echo "[" . date('Y-m-d H:i:s') . "]   - customers: " . (isset($connections['customers']) ? count($connections['customers']) : 0) . "\n";
    
    // 统计所有在线的客户
    if (isset($connections['customers']) && is_array($connections['customers'])) {
        foreach ($connections['customers'] as $customerId => $customerConn) {
            if ($customerConn instanceof TcpConnection && isset($customerConn->userId) && isset($customerConn->authenticated)) {
                $customerName = $customerConn->userId;
                $sessionKey = $customerConn->sessionKey ?? '';
                
                echo "[" . date('Y-m-d H:i:s') . "]   - 在线客户: {$customerName} (session: {$sessionKey})\n";
                
                // 如果有指定客服，检查会话是否属于该客服
                if (!empty($agentAccount) && !empty($sessionKey)) {
                    // 这里可以添加逻辑检查会话所属客服
                }
                
                // 如果有指定客户列表，只返回这些客户
                if (!empty($customers) && !in_array($customerName, $customers)) {
                    continue;
                }
                
                $onlineStatuses[$customerName] = true;
                $onlineClients[] = [
                    'username' => $customerName,
                    'is_online' => true,
                    'connected_at' => $customerConn->connectedAt ?? date('Y-m-d H:i:s'),
                    'session_key' => $sessionKey
                ];
            }
        }
    }
    
    return json_encode([
        'success' => true,
        'online_count' => count($onlineStatuses),
        'online_status' => $onlineStatuses,
        'online_clients' => $onlineClients,  // 添加详细信息
        'timestamp' => time(),
        'agent_account' => $agentAccount,
        'debug_info' => [
            'total_customers_in_pool' => isset($connections['customers']) ? count($connections['customers']) : 0
        ]
    ]);
}

/**
 * 获取完整统计信息 JSON
 */
function getFullStatsJson($connections) {
    $stats = [
        'success' => true,
        'timestamp' => time(),
        'data' => [
            'total_connections' => 0,
            'online_agents' => 0,
            'online_customers' => 0,
            'websocket_status' => 'running',
            'http_api_status' => 'running'
        ]
    ];
    
    // 统计客服连接
    if (isset($connections['agents']) && is_array($connections['agents'])) {
        foreach ($connections['agents'] as $agentId => $agentConn) {
            if ($agentConn instanceof TcpConnection && isset($agentConn->authenticated) && $agentConn->authenticated) {
                $stats['data']['online_agents']++;
                $stats['data']['total_connections']++;
            }
        }
    }
    
    // 统计客户连接
    if (isset($connections['customers']) && is_array($connections['customers'])) {
        foreach ($connections['customers'] as $customerId => $customerConn) {
            if ($customerConn instanceof TcpConnection && isset($customerConn->authenticated) && $customerConn->authenticated) {
                $stats['data']['online_customers']++;
                $stats['data']['total_connections']++;
            }
        }
    }
    
    return json_encode($stats);
}

// ==================== 主 WebSocket 服务器逻辑 ====================

echo "[" . date('Y-m-d H:i:s') . "] === WebSocket Server By @x60898 ===\n";

// 创建 WebSocket 服务器
$ws_worker = new Worker('websocket://0.0.0.0:8288');

// 连接池
$connections = [
    'agents' => [],
    'customers' => []
];

// 连接建立
$ws_worker->onConnect = function(TcpConnection $connection) {
    echo "[" . date('Y-m-d H:i:s') . "] 🔌 新连接建立，连接ID: {$connection->id}\n";
    $connection->lastHeartbeat = time();
    $connection->connectedAt = date('Y-m-d H:i:s');
};

// 接收消息
$ws_worker->onMessage = function(TcpConnection $connection, $data) use (&$connections) {
    // 更新最后心跳时间
    $connection->lastHeartbeat = time();
    
    echo "[" . date('Y-m-d H:i:s') . "] 📨 收到消息: " . substr($data, 0, 200) . "\n";
    
    try {
        $message = json_decode($data, true);
        if (!$message) {
            $connection->send(json_encode(['type' => 'error', 'message' => '消息格式错误']));
            return;
        }
        
        $type = $message['type'] ?? '';
        
        switch ($type) {
           // 在 websocket_final.php 中，修改 onMessage 函数中的 auth 部分
case 'auth':
    // 身份验证
    $userType = $message['user_type'] ?? '';
    $userId = $message['user_id'] ?? '';
    $sessionKey = $message['session_key'] ?? '';
    
    if (empty($userType) || empty($userId)) {
        $connection->send(json_encode(['type' => 'auth_failed', 'message' => '缺少必要参数']));
        return;
    }
    
    if (!in_array($userType, ['agent', 'customer'])) {
        $connection->send(json_encode(['type' => 'auth_failed', 'message' => '用户类型必须是 agent 或 customer']));
        return;
    }
    
    $connection->userType = $userType;
    $connection->userId = $userId;
    $connection->sessionKey = $sessionKey;
    $connection->authenticated = true;  // 添加认证标记
    $connection->connectedAt = date('Y-m-d H:i:s');
    
    // 【修复】正确添加到连接池
    if ($userType === 'agent') {
        $connections['agents'][$userId] = $connection;
    } else if ($userType === 'customer') {
        $connections['customers'][$userId] = $connection;
    }
    
    echo "[" . date('Y-m-d H:i:s') . "] ✅ 用户身份验证成功: {$userType} - {$userId}\n";
    echo "[" . date('Y-m-d H:i:s') . "] 📊 当前连接池状态:\n";
    echo "[" . date('Y-m-d H:i:s') . "]   - 客服: " . count($connections['agents']) . " 个\n";
    echo "[" . date('Y-m-d H:i:s') . "]   - 客户: " . count($connections['customers']) . " 个\n";
    
    // 更新数据库在线状态
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    updateUserOnlineStatus($userId, $userType, true, 'window_visible', $sessionKey, $clientIp);
    
    $connection->send(json_encode([
        'type' => 'auth_success',
        'user_type' => $userType,
        'user_id' => $userId
    ]));
    break;
                
            case 'send_message':
                // 发送消息
                $sessionKey = $message['session_key'] ?? '';
                $content = $message['content'] ?? '';
                $agentAccount = $message['agent_account'] ?? '';
                $customerName = $message['customer_name'] ?? '';
                $speakerType = $message['speaker_type'] ?? 1;
                $platform = $message['platform'] ?? 'WebSocket';
                $fromApi = $message['from_api'] ?? false;
                
                // 对于图片消息，允许content为空，但必须有image_url
                $isImageMessage = isset($message['message_type']) && $message['message_type'] === 'image';
                $hasImageUrl = isset($message['image_url']) && !empty($message['image_url']);
                
                // 验证：sessionKey不能为空，且要么有content，要么是有image_url的图片消息
                if (empty($sessionKey) || (empty($content) && !($isImageMessage && $hasImageUrl))) {
                    $connection->send(json_encode(['type' => 'error', 'message' => '缺少必要参数']));
                    return;
                }
                
                // 生成消息ID
                $messageId = uniqid();
                
                // 如果是来自 API，使用 API 提供的 message_id
                if ($fromApi && isset($message['message_id'])) {
                    $messageId = $message['message_id'];
                    echo "[" . date('Y-m-d H:i:s') . "] ⚡ 消息来自 API，使用 message_id: {$messageId}\n";
                }
                
                // 构建消息数据
                $messageData = [
                    'type' => 'new_message',
                    'message_id' => $messageId,
                    'session_key' => $sessionKey,
                    'content' => $content,
                    'speaker_type' => $speakerType,
                    'agent_account' => $agentAccount,
                    'customer_name' => $customerName,
                    'platform' => $platform,
                    'created_at' => date('Y-m-d H:i:s')
                ];
                
                // 添加图片消息相关字段
                if (isset($message['message_type'])) {
                    $messageData['message_type'] = $message['message_type'];
                }
                if (isset($message['image_url'])) {
                    $messageData['image_url'] = $message['image_url'];
                }
                if (isset($message['image_path'])) {
                    $messageData['image_path'] = $message['image_path'];
                }
                
                // 确定接收方：客户发言(speakerType=1)→推送给客服，客服/假人消息不需要推送
                if ($speakerType == 1) {
                    // 客户发言 → 推送给客服(agent)
                    $receiverType = 'agent';
                    $receiverId = $agentAccount;
                } else {
                    // 客服/假人消息 → 不需要推送
                    $receiverType = 'customer';
                    $receiverId = $customerName;
                }

                // 如果不是来自 API，才保存到数据库
                if (!$fromApi) {
                    // 尝试保存到数据库
                    try {
                        $db = getSafeDB();
                        if ($db) {
                            $query = "INSERT INTO chat_messages (session_key, agent_account, speaker_type, content, customer_name, platform) VALUES (?, ?, ?, ?, ?, ?)";
                            $stmt = $db->prepare($query);
                            if ($stmt) {
                                $stmt->bind_param("ssisss", $sessionKey, $agentAccount, $speakerType, $content, $customerName, $platform);
                                if ($stmt->execute()) {
                                    $dbMessageId = $stmt->insert_id;
                                    $stmt->close();
                                    echo "[" . date('Y-m-d H:i:s') . "] ✅ 消息保存到数据库，ID: {$dbMessageId}\n";
                                    
                                    // 更新消息ID为数据库ID
                                    $messageId = $dbMessageId;
                                    $messageData['message_id'] = $dbMessageId;
                                } else {
                                    echo "[" . date('Y-m-d H:i:s') . "] ❌ 数据库保存失败: " . $stmt->error . "\n";
                                    $stmt->close();
                                }
                            } else {
                                echo "[" . date('Y-m-d H:i:s') . "] ❌ 数据库准备失败: " . $db->error . "\n";
                            }
                        } else {
                            echo "[" . date('Y-m-d H:i:s') . "] ⚠️ 数据库不可用，仅使用内存ID\n";
                        }
                    } catch (Exception $e) {
                        echo "[" . date('Y-m-d H:i:s') . "] ❌ 数据库操作异常: " . $e->getMessage() . "\n";
                    }
                } else {
                    echo "[" . date('Y-m-d H:i:s') . "] ⚠️ 消息来自 API，跳过数据库保存\n";
                }
                
                // 推送消息给接收方
                $receiverOnline = false;
                if (isset($connections[$receiverType . 's'][$receiverId])) {
                    $receiverConn = $connections[$receiverType . 's'][$receiverId];
                    if ($receiverConn instanceof TcpConnection) {
                        try {
                            $receiverConn->send(json_encode($messageData));
                            $receiverOnline = true;
                            echo "[" . date('Y-m-d H:i:s') . "] ✅ 实时推送给: {$receiverType} - {$receiverId}\n";
                        } catch (Exception $e) {
                            echo "[" . date('Y-m-d H:i:s') . "] ❌ 推送失败: " . $e->getMessage() . "\n";
                        }
                    }
                } else {
                    echo "[" . date('Y-m-d H:i:s') . "] ⚠️ 接收方不在线: {$receiverType} - {$receiverId}\n";
                }
                
                // 只有客户发消息(speakerType=1)才发送Push通知，客服消息(2)和假人消息(3)跳过
                if ($speakerType == 1) {
                    // 原因：iOS PWA切到后台时WebSocket可能仍连接，但用户看不到页面，需要Push通知提醒
                    sendPushNotification($receiverId, $receiverType, $messageData);
                } else {
                    echo "[" . date('Y-m-d H:i:s') . "] ⏭️  speakerType={$speakerType}, only type=1 needs push, skipped\n";
                }
                
                // 如果不是来自 API 才发送回执
                if (!$fromApi) {
                    $connection->send(json_encode([
                        'type' => 'message_sent',
                        'message_id' => $messageId,
                        'success' => true,
                        'receiver_online' => $receiverOnline
                    ]));
                } else {
                    echo "[" . date('Y-m-d H:i:s') . "] 消息来自 API，不发送回执\n";
                }
                
                // ==================== 【关键】更新发送方最后活动时间 ====================
                if (isset($connection->userId, $connection->userType)) {
                    updateUserHeartbeat($connection->userId, $connection->userType);
                }
                
                echo "[" . date('Y-m-d H:i:s') . "] ✅ 消息处理完成\n";
                break;
                
            case 'ping':
            case 'heartbeat':
                // 心跳处理
                echo "[" . date('Y-m-d H:i:s') . "] 💓 收到心跳\n";
                
                // 更新最后心跳时间
                $connection->lastHeartbeat = time();
                
                // 更新数据库最后心跳时间
                if (isset($connection->userId, $connection->userType)) {
                    updateUserHeartbeat($connection->userId, $connection->userType);
                }
                
                $connection->send(json_encode([
                    'type' => 'pong',
                    'timestamp' => time()
                ]));
                break;
                
            default:
                $connection->send(json_encode(['type' => 'error', 'message' => '未知的消息类型']));
        }
    } catch (Exception $e) {
        echo "[" . date('Y-m-d H:i:s') . "] ❌ 处理消息异常: " . $e->getMessage() . "\n";
        echo "[" . date('Y-m-d H:i:s') . "] ❌ 堆栈: " . $e->getTraceAsString() . "\n";
        $connection->send(json_encode([
            'type' => 'error',
            'message' => '服务器内部错误'
        ]));
    }
};

// 连接关闭
$ws_worker->onClose = function(TcpConnection $connection) use (&$connections) {
    echo "[" . date('Y-m-d H:i:s') . "] 🔌 连接关闭，连接ID: {$connection->id}\n";
    
    // 如果有用户身份信息，从连接池移除并更新数据库状态
    if (isset($connection->userType, $connection->userId)) {
        $userType = $connection->userType;
        $userId = $connection->userId;
        
        // 从连接池移除
        if (isset($connections[$userType . 's'][$userId])) {
            unset($connections[$userType . 's'][$userId]);
            echo "[" . date('Y-m-d H:i:s') . "] 🗑️ 从连接池移除: {$userType} - {$userId}\n";
            
            // ==================== 【关键】更新数据库为离线状态 ====================
            updateUserOnlineStatus($userId, $userType, false, 'window_closed', '');
        }
    }
};

// 心跳检测
$ws_worker->onWorkerStart = function() use (&$ws_worker) {
    echo "[" . date('Y-m-d H:i:s') . "] ⏰ 启动心跳检测定时器\n";
    
    // 每10秒检查一次心跳
    Timer::add(10, function() use (&$ws_worker) {
        $time = time();
        $timeoutCount = 0;
        
        foreach ($ws_worker->connections as $connection) {
            if (isset($connection->lastHeartbeat) && ($time - $connection->lastHeartbeat) > 60) {
                echo "[" . date('Y-m-d H:i:s') . "] ⏰ 心跳超时，断开连接 ID: {$connection->id}\n";
                $timeoutCount++;
                $connection->close();
            }
        }
        
        if ($timeoutCount > 0) {
            echo "[" . date('Y-m-d H:i:s') . "] ⏰ 心跳检测: 断开了 {$timeoutCount} 个超时连接\n";
        }
    });
    
    // ==================== 【新增】启动HTTP API服务器 ====================
    Timer::add(1, function() use (&$connections) {
        static $httpSocket = null;
        
        if ($httpSocket === null) {
            $httpSocket = setupHttpApi('127.0.0.1', 8289);
        }
        
        if ($httpSocket) {
            // 非阻塞处理HTTP请求
            $read = [$httpSocket];
            $write = null;
            $except = null;
            
            if (stream_select($read, $write, $except, 0) > 0) {
                handleHttpRequest($httpSocket, $connections);
            }
        }
    });
};

// 运行服务器
Worker::runAll();