<?php

error_reporting(E_ALL);
ini_set('display_errors', 0); // 关闭错误显示
ini_set('log_errors', 1); // 开启错误日志
ini_set('error_log', '/tmp/php_errors.log'); // 将错误记录到/tmp目录

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// 启动session
session_start();

// 处理OPTIONS预检请求
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 获取输入数据
$input = [];
$rawInput = file_get_contents("php://input");

if (!empty($rawInput)) {
    $input = json_decode($rawInput, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $input = [];
    }
}

// 同时检查POST数据
if (empty($input) && !empty($_POST)) {
    $input = $_POST;
}

// 修复文件包含路径
// 使用相对路径
require_once dirname(dirname(__DIR__)) . '/config/dbconfig.php';
require_once dirname(dirname(__DIR__)) . '/config/session_parser.php';


// 检查登录状态
checkLogin();

$currentAgent = $_SESSION['username'];

// 数据库连接
$db = getDB();
if (!$db) {
    echo json_encode(array("success" => false, "message" => "数据库连接失败"));
    exit;
}

// 设置时区
date_default_timezone_set('Asia/Shanghai');

function debugLog($message) {
     // 调试日志已禁用
    return;
}

// ================ 数据库表结构函数 ================

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

// 确保会话设置表存在
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
            debugLog("chat_settings table created successfully");
        } else {
            debugLog("Failed to create chat_settings table: " . $db->error);
        }
    }
}

function updateOnlineStatus($db, $username, $userType = 'agent', $isOnline = true) {
    ensureOnlineStatusTable($db);
    
    $query = "INSERT INTO user_online_status (username, is_online, last_seen, user_type) 
              VALUES (?, ?, NOW(), ?) 
              ON DUPLICATE KEY UPDATE 
              is_online = VALUES(is_online), last_seen = NOW()";
    
    $stmt = $db->prepare($query);
    if (!$stmt) {
        debugLog("Prepare failed: " . $db->error);
        return false;
    }
    
    $onlineValue = $isOnline ? 1 : 0;
    $stmt->bind_param("sis", $username, $onlineValue, $userType);
    $result = $stmt->execute();
    
    if (!$result) {
        debugLog("Execute failed: " . $stmt->error);
    }
    
    $stmt->close();
    return $result;
}

// ================ 会话设置函数 ================

// 切换会话置顶状态
function togglePinSession($db, $sessionKey, $agentAccount, $pin) {
    ensureSessionSettingsTable($db);
    
    // 检查记录是否存在
    $checkQuery = "SELECT id FROM chat_settings WHERE session_key = ? AND agent_account = ?";
    $checkStmt = $db->prepare($checkQuery);
    if (!$checkStmt) {
        debugLog("Prepare failed for checkQuery: " . $db->error);
        return false;
    }
    
    $checkStmt->bind_param("ss", $sessionKey, $agentAccount);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    $checkStmt->close();
    
    if ($checkResult->num_rows > 0) {
        // 更新现有记录
        $query = "UPDATE chat_settings SET is_pinned = ?, updated_at = NOW() 
                  WHERE session_key = ? AND agent_account = ?";
    } else {
        // 插入新记录
        $query = "INSERT INTO chat_settings (session_key, agent_account, is_pinned) 
                  VALUES (?, ?, ?)";
    }
    
    $stmt = $db->prepare($query);
    if (!$stmt) {
        debugLog("Prepare failed for togglePinSession: " . $db->error);
        return false;
    }
    
    $pinValue = $pin ? 1 : 0;
    
    if ($checkResult->num_rows > 0) {
        $stmt->bind_param("iss", $pinValue, $sessionKey, $agentAccount);
    } else {
        $stmt->bind_param("ssi", $sessionKey, $agentAccount, $pinValue);
    }
    
    $result = $stmt->execute();
    
    if (!$result) {
        debugLog("Execute failed for togglePinSession: " . $stmt->error);
    } else {
        debugLog("会话置顶状态已更新: session_key=$sessionKey, agent=$agentAccount, pin=$pinValue");
    }
    
    $stmt->close();
    return $result;
}

// 切换会话免打扰状态
function toggleMuteSession($db, $sessionKey, $agentAccount, $mute) {
    ensureSessionSettingsTable($db);
    
    // 检查记录是否存在
    $checkQuery = "SELECT id FROM chat_settings WHERE session_key = ? AND agent_account = ?";
    $checkStmt = $db->prepare($checkQuery);
    if (!$checkStmt) {
        debugLog("Prepare failed for checkQuery: " . $db->error);
        return false;
    }
    
    $checkStmt->bind_param("ss", $sessionKey, $agentAccount);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    $checkStmt->close();
    
    if ($checkResult->num_rows > 0) {
        // 更新现有记录
        $query = "UPDATE chat_settings SET is_muted = ?, updated_at = NOW() 
                  WHERE session_key = ? AND agent_account = ?";
    } else {
        // 插入新记录
        $query = "INSERT INTO chat_settings (session_key, agent_account, is_muted) 
                  VALUES (?, ?, ?)";
    }
    
    $stmt = $db->prepare($query);
    if (!$stmt) {
        debugLog("Prepare failed for toggleMuteSession: " . $db->error);
        return false;
    }
    
    $muteValue = $mute ? 1 : 0;
    
    if ($checkResult->num_rows > 0) {
        $stmt->bind_param("iss", $muteValue, $sessionKey, $agentAccount);
    } else {
        $stmt->bind_param("ssi", $sessionKey, $agentAccount, $muteValue);
    }
    
    $result = $stmt->execute();
    
    if (!$result) {
        debugLog("Execute failed for toggleMuteSession: " . $stmt->error);
    } else {
        debugLog("会话免打扰状态已更新: session_key=$sessionKey, agent=$agentAccount, mute=$muteValue");
    }
    
    $stmt->close();
    return $result;
}

// 获取会话的设置状态
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
        debugLog("Prepare failed for getSessionSettings: " . $db->error);
        return $settings;
    }
    
    $stmt->bind_param("ss", $sessionKey, $agentAccount);
    
    if (!$stmt->execute()) {
        debugLog("Execute failed for getSessionSettings: " . $stmt->error);
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

// ================ 主请求处理逻辑 ================

// 获取请求参数
$action = isset($_GET['action']) ? $_GET['action'] : '';

debugLog("请求动作: " . $action);
debugLog("当前用户: " . $currentAgent);

// 处理不同的动作
switch ($action) {
    case 'toggle_pin':
        // 处理置顶请求
        if (empty($input['session_key'])) {
            echo json_encode(array("success" => false, "message" => "缺少session_key参数"));
            exit;
        }
        
        $sessionKey = $input['session_key'];
        $pin = isset($input['pin']) ? (bool)$input['pin'] : false;
        
        debugLog("置顶请求: session_key=" . $sessionKey . ", pin=" . ($pin ? "1" : "0"));
        
        $result = togglePinSession($db, $sessionKey, $currentAgent, $pin);
        
        if ($result) {
            echo json_encode(array(
                "success" => true, 
                "message" => $pin ? "已置顶会话" : "已取消置顶",
                "data" => array("is_pinned" => $pin)
            ));
        } else {
            echo json_encode(array("success" => false, "message" => "操作失败，请重试"));
        }
        break;
        
    case 'toggle_mute':
        // 处理免打扰请求
        if (empty($input['session_key'])) {
            echo json_encode(array("success" => false, "message" => "缺少session_key参数"));
            exit;
        }
        
        $sessionKey = $input['session_key'];
        $mute = isset($input['mute']) ? (bool)$input['mute'] : false;
        
        debugLog("免打扰请求: session_key=" . $sessionKey . ", mute=" . ($mute ? "1" : "0"));
        
        $result = toggleMuteSession($db, $sessionKey, $currentAgent, $mute);
        
        if ($result) {
            echo json_encode(array(
                "success" => true, 
                "message" => $mute ? "已开启免打扰" : "已关闭免打扰",
                "data" => array("is_muted" => $mute)
            ));
        } else {
            echo json_encode(array("success" => false, "message" => "操作失败，请重试"));
        }
        break;
        
    case 'get_session_settings':
        // 获取会话设置
        if (empty($input['session_key'])) {
            echo json_encode(array("success" => false, "message" => "缺少session_key参数"));
            exit;
        }
        
        $sessionKey = $input['session_key'];
        $settings = getSessionSettings($db, $sessionKey, $currentAgent);
        
        echo json_encode(array(
            "success" => true, 
            "message" => "获取成功",
            "data" => $settings
        ));
        break;
        
    case 'test_connection':
        // 测试连接接口
        echo json_encode(array(
            "success" => true, 
            "message" => "API连接正常",
            "data" => array(
                "user" => $currentAgent,
                "action" => $action
            )
        ));
        break;
        
    default:
        // 默认响应
        echo json_encode(array("success" => false, "message" => "未知的API请求"));
        break;
}

// 关闭数据库连接
$db->close();
