<?php

// PHP部分：根据XEid参数获取特定会话
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/dbconfig.php';
checkLogin();

// 获取当前登录的客服信息
$currentAgent = $_SESSION['username'];
$currentRole = $_SESSION['role'];

// 获取URL参数
$sessionKey = isset($_GET['XEid']) ? trim($_GET['XEid']) : null;
$customerName = isset($_GET['customer']) ? trim($_GET['customer']) : '未知客户';

// 验证会话是否存在
$db = getDB();
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

// ================ 定义会话设置函数 ================
function ensureSessionSettingsTable($db) {
    $checkTable = "SHOW TABLES LIKE 'chat_settings'";
    $result = $db->query($checkTable);
    if ($result->num_rows == 0) {
        $createTable = "CREATE TABLE chat_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            session_key VARCHAR(100) NOT NULL,
            agent_account VARCHAR(50) NOT NULL,
            is_pinned TINYINT(1) DEFAULT 0,
            is_muted TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_session_agent (session_key, agent_account)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        if ($db->query($createTable)) {
            error_log("chat_settings table created successfully");
        } else {
            error_log("Failed to create chat_settings table: " . $db->error);
        }
    }
}

function getSessionSettings($db, $sessionKey, $agentAccount) {
    ensureSessionSettingsTable($db);
    
    $settings = [
        'is_pinned' => false,
        'is_muted' => false
    ];
    
    $query = "SELECT is_pinned, is_muted FROM chat_settings 
              WHERE session_key = ? AND agent_account = ?";
    
    $stmt = $db->prepare($query);
    if (!$stmt) {
        error_log("Prepare failed for getSessionSettings: " . $db->error);
        return $settings;
    }
    
    $stmt->bind_param("ss", $sessionKey, $agentAccount);
    
    if (!$stmt->execute()) {
        error_log("Execute failed for getSessionSettings: " . $stmt->error);
        $stmt->close();
        return $settings;
    }
    
    $result = $stmt->get_result();
    if ($result && $row = $result->fetch_assoc()) {
        $settings['is_pinned'] = (bool)$row['is_pinned'];
        $settings['is_muted'] = (bool)$row['is_muted'];
    }
    
    $stmt->close();
    return $settings;
}
// ================ 结束定义会话设置函数 ================

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
}

// 如果会话无效，跳转
if (!$isValidSession) {
    error_log("[" . date('Y-m-d H:i:s') . "] 无效会话，跳转至列表页。SessionKey: " . $sessionKey);
    header('Location: /X-MSG?error=invalid_session');
    exit;
}

// 获取消息历史
try {
    $query = "SELECT id, speaker_type, content, message_type, created_at, image_url, image_path 
              FROM chat_messages 
              WHERE session_key = ? 
              ORDER BY created_at ASC";
    $stmt = $db->prepare($query);
    
    if ($stmt === false) {
        throw new Exception("准备消息查询语句失败: " . $db->error);
    }
    
    $stmt->bind_param("s", $sessionKey);
    if (!$stmt->execute()) {
        throw new Exception("执行消息查询失败: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        // 确保图片消息有正确的URL
        if ($row['message_type'] === 'image') {
            if (!empty($row['image_url'])) {
                // 已经有完整URL，直接使用
            } elseif (!empty($row['image_path'])) {
                // 只有路径，构造完整URL
                $row['image_url'] = '/uploads/' . ltrim($row['image_path'], '/');
            }
        }
        $messages[] = $row;
    }
    
    $stmt->close();
} catch (Exception $e) {
    error_log("[" . date('Y-m-d H:i:s') . "] 获取消息历史失败: " . $e->getMessage());
    // 不退出，继续显示页面，只是消息为空
}

// 获取会话设置（置顶、免打扰等）
try {
    $sessionSettings = getSessionSettings($db, $sessionKey, $currentAgent);
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
?>