<?php

// 系统监控 API
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// 关闭所有输出缓冲，确保只输出 JSON
while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// 处理 OPTIONS 预检请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// 只允许 GET 请求
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => '方法不允许']);
    exit();
}

try {
    // 包含数据库配置
    @include_once $_SERVER['DOCUMENT_ROOT'] . '/config/dbconfig.php';
    
    if (!function_exists('getDB')) {
        throw new Exception('数据库配置加载失败');
    }
    
    $db = getDB();
    if (!$db) {
        throw new Exception('数据库连接失败');
    }
    
    // 获取 WebSocket 连接统计
    $wsData = getWebSocketStats($db);
    
    // 获取在线用户详情
    $onlineUsers = getOnlineUsersDetail($db);
    
    // 获取服务器状态
    $serverStats = getServerStats();
    
    // 获取访问量统计
    $visitStats = getVisitStats($db);
    
    // 合并数据
    $responseData = array_merge($wsData, $serverStats, $visitStats, $onlineUsers);
    
    echo json_encode([
        'success' => true,
        'data' => $responseData,
        'timestamp' => time()
    ]);
    
} catch (Exception $e) {
    error_log('监控 API 异常：' . $e->getMessage() . "\n" . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => '获取监控数据失败：' . $e->getMessage()
    ]);
}

/**
 * 获取 WebSocket 连接统计
 */
function getWebSocketStats($db) {
    $stats = [
        'total_connections' => 0,
        'online_agents' => 0,
        'online_customers' => 0,
        'websocket_status' => 'offline',
        'http_api_status' => 'offline'
    ];
    
    // 从数据库获取在线客服
    $query = "SELECT COUNT(*) as count FROM user_online_status
              WHERE user_type = 'agent'
              AND is_online = 1
              AND last_heartbeat > DATE_SUB(NOW(), INTERVAL 5 MINUTE)";
    
    $result = $db->query($query);
    if ($result) {
        $row = $result->fetch_assoc();
        $stats['online_agents'] = (int)($row['count'] ?? 0);
    }
    
    // 从数据库获取在线客户
    $query = "SELECT COUNT(*) as count FROM user_online_status
              WHERE user_type = 'customer'
              AND is_online = 1
              AND last_heartbeat > DATE_SUB(NOW(), INTERVAL 5 MINUTE)";
    
    $result = $db->query($query);
    if ($result) {
        $row = $result->fetch_assoc();
        $stats['online_customers'] = (int)($row['count'] ?? 0);
    }
    
    $stats['total_connections'] = $stats['online_agents'] + $stats['online_customers'];
    
    // 尝试从 WebSocket HTTP API 获取实时数据（端口 8289）
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 1,
            'ignore_errors' => true
        ]
    ]);
    
    $result = @file_get_contents('http://127.0.0.1:8289/api/stats', false, $context);
    if ($result !== false) {
        $data = @json_decode($result, true);
        if (json_last_error() === 0 && isset($data['success']) && isset($data['data'])) {
            $stats['websocket_status'] = 'running';
            $stats['http_api_status'] = 'running';
            // 使用 WebSocket API 的数据（更实时）
            $stats['total_connections'] = $data['data']['total_connections'] ?? $stats['total_connections'];
            $stats['online_agents'] = $data['data']['online_agents'] ?? $stats['online_agents'];
            $stats['online_customers'] = $data['data']['online_customers'] ?? $stats['online_customers'];
        }
    }
    
    // 如果有在线用户，认为服务在运行
    if ($stats['total_connections'] > 0) {
        $stats['websocket_status'] = 'running';
    }
    
    return $stats;
}

/**
 * 获取在线用户详情
 */
function getOnlineUsersDetail($db) {
    $users = [
        'online_agents_list' => [],
        'online_customers_list' => []
    ];
    
    // 获取在线客服详情
    $query = "SELECT username, last_heartbeat, window_status, session_key, client_ip
              FROM user_online_status
              WHERE user_type = 'agent'
              AND is_online = 1
              AND last_heartbeat > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
              ORDER BY last_heartbeat DESC";
    
    $result = $db->query($query);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $users['online_agents_list'][] = [
                'username' => $row['username'],
                'last_heartbeat' => $row['last_heartbeat'],
                'window_status' => $row['window_status'],
                'session_key' => $row['session_key'] ?? '',
                'client_ip' => $row['client_ip'] ?? ''
            ];
        }
    }
    
    // 获取在线客户详情
    $query = "SELECT username, last_heartbeat, window_status, session_key, client_ip
              FROM user_online_status
              WHERE user_type = 'customer'
              AND is_online = 1
              AND last_heartbeat > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
              ORDER BY last_heartbeat DESC";
    
    $result = $db->query($query);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $users['online_customers_list'][] = [
                'username' => $row['username'],
                'last_heartbeat' => $row['last_heartbeat'],
                'window_status' => $row['window_status'],
                'session_key' => $row['session_key'] ?? '',
                'client_ip' => $row['client_ip'] ?? ''
            ];
        }
    }
    
    // 添加计数
    $users['online_agents_count'] = count($users['online_agents_list']);
    $users['online_customers_count'] = count($users['online_customers_list']);
    
    return $users;
}

/**
 * 获取服务器状态
 */
function getServerStats() {
    $stats = [
        'memory_usage' => '0 MB',
        'memory_percentage' => 0,
        'cpu_usage' => 0,
        'disk_usage' => 0,
        'uptime' => '未知',
        'php_version' => PHP_VERSION,
        'server_time' => date('Y-m-d H:i:s')
    ];
    
    // 内存使用
    if (function_exists('memory_get_usage')) {
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = ini_get('memory_limit');
        $memoryLimitBytes = parseMemoryLimit($memoryLimit);
        
        if ($memoryLimitBytes > 0) {
            $stats['memory_percentage'] = round(($memoryUsage / $memoryLimitBytes) * 100, 2);
        }
        $stats['memory_usage'] = formatBytes($memoryUsage);
    }
    
    // 系统内存（Linux）
    if (@file_exists('/proc/meminfo')) {
        $meminfo = @file_get_contents('/proc/meminfo');
        if (preg_match('/MemTotal:\s+(\d+)\s+kB/', $meminfo, $totalMatches) &&
            preg_match('/MemAvailable:\s+(\d+)\s+kB/', $meminfo, $availableMatches)) {
            $total = (int)$totalMatches[1];
            $available = (int)$availableMatches[1];
            $used = $total - $available;
            $stats['memory_percentage'] = round(($used / $total) * 100, 2);
            $stats['memory_usage'] = formatBytes($used * 1024);
        }
    }
    
    // CPU 使用率（Linux）
    if (@file_exists('/proc/stat')) {
        $cpuUsage = getCpuUsage();
        if ($cpuUsage !== null) {
            $stats['cpu_usage'] = $cpuUsage;
        }
    }
    
    // 磁盘使用率
    if (function_exists('disk_free_space') && function_exists('disk_total_space')) {
        $diskFree = @disk_free_space('/');
        $diskTotal = @disk_total_space('/');
        if ($diskFree !== false && $diskTotal !== false && $diskTotal > 0) {
            $diskUsed = $diskTotal - $diskFree;
            $stats['disk_usage'] = round(($diskUsed / $diskTotal) * 100, 2);
        }
    }
    
    // 系统运行时间（Linux）
    if (@file_exists('/proc/uptime')) {
        $uptime = @file_get_contents('/proc/uptime');
        $uptimeSeconds = (int)explode(' ', $uptime)[0];
        $stats['uptime'] = formatUptime($uptimeSeconds);
    }
    
    return $stats;
}

/**
 * 获取 CPU 使用率
 */
function getCpuUsage() {
    static $lastCpu = null;
    
    $cpuData = @file_get_contents('/proc/stat');
    if ($cpuData === false) {
        return null;
    }
    
    $lines = explode("\n", $cpuData);
    $firstLine = $lines[0];
    
    if (!preg_match('/cpu\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)/', $firstLine, $matches)) {
        return null;
    }
    
    $idle = (int)$matches[4];
    $total = array_sum(array_slice($matches, 1));
    
    if ($lastCpu === null) {
        $lastCpu = ['idle' => $idle, 'total' => $total];
        return 0;
    }
    
    $idleDelta = $idle - $lastCpu['idle'];
    $totalDelta = $total - $lastCpu['total'];
    $lastCpu = ['idle' => $idle, 'total' => $total];
    
    if ($totalDelta == 0) {
        return 0;
    }
    
    return round((1 - $idleDelta / $totalDelta) * 100, 2);
}

/**
 * 解析内存限制
 */
function parseMemoryLimit($memoryLimit) {
    if ($memoryLimit == '-1') {
        return -1;
    }
    
    $memoryLimit = strtoupper(trim($memoryLimit));
    $unit = substr($memoryLimit, -1);
    $value = (int)substr($memoryLimit, 0, -1);
    
    switch ($unit) {
        case 'G': return $value * 1024 * 1024 * 1024;
        case 'M': return $value * 1024 * 1024;
        case 'K': return $value * 1024;
        default: return $value;
    }
}

/**
 * 格式化字节数
 */
function formatBytes($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, 2) . ' ' . $units[$pow];
}

/**
 * 格式化运行时间
 */
function formatUptime($seconds) {
    $days = floor($seconds / 86400);
    $hours = floor(($seconds % 86400) / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    
    $parts = [];
    if ($days > 0) $parts[] = $days . '天';
    if ($hours > 0) $parts[] = $hours . '小时';
    if ($minutes > 0) $parts[] = $minutes . '分钟';
    
    return implode(' ', $parts) ?: '刚刚启动';
}

/**
 * 获取访问量统计
 */
function getVisitStats($db) {
    $stats = ['today_visits' => 0, 'today_unique_visits' => 0];
    
    // 检查表是否存在
    $result = $db->query("SHOW TABLES LIKE 'site_visits'");
    if (!$result || $result->num_rows === 0) {
        return $stats;
    }
    
    $today = date('Y-m-d');
    $query = "SELECT visits, unique_visitors FROM site_visits WHERE visit_date = ?";
    $stmt = $db->prepare($query);
    
    if ($stmt) {
        $stmt->bind_param("s", $today);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $stats['today_visits'] = (int)$row['visits'];
            $stats['today_unique_visits'] = (int)$row['unique_visitors'];
        }
        
        $stmt->close();
    }
    
    return $stats;
}
