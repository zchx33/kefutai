<?php

define('DB_HOST', 'localhost');
define('DB_USER', 'hxzc');
define('DB_PASS', 'hxzc');
define('DB_NAME', 'hxzc');

// 获取数据库连接
function getDB() {
    static $db = null;
    if ($db === null) {
        $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($db->connect_error) {
            error_log("数据库连接失败: " . $db->connect_error);
            return false;
        }
        $db->set_charset("utf8mb4");
    }
    return $db;
}

// JSON响应函数
function jsonResponse($data) {
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function generateSessionToken() {
    return bin2hex(random_bytes(32));
}

function createUserSession($user_id, $device_info = null) {
    $db = getDB();
    if (!$db) {
        return false;
    }
    
    $session_token = generateSessionToken();
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $device_info_json = $device_info ? json_encode($device_info) : null;
    $expires_at = date('Y-m-d H:i:s', time() + (30 * 24 * 60 * 60));
    
    $stmt = $db->prepare("INSERT INTO user_sessions (user_id, session_token, ip_address, user_agent, device_info, expires_at) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssss", $user_id, $session_token, $ip_address, $user_agent, $device_info_json, $expires_at);
    
    if ($stmt->execute()) {
        $_SESSION['session_token'] = $session_token;
        setcookie('session_token', $session_token, time() + (30 * 24 * 60 * 60), '/', '', true, true);
        return $session_token;
    }
    
    return false;
}

function validateUserSession() {
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    $session_token = $_SESSION['session_token'] ?? $_COOKIE['session_token'] ?? null;
    if (!$session_token) {
        return false;
    }
    
    $db = getDB();
    if (!$db) {
        return false;
    }
    
    $user_id = $_SESSION['user_id'];
    $stmt = $db->prepare("SELECT id FROM user_sessions WHERE user_id = ? AND session_token = ? AND is_active = 1 AND (expires_at IS NULL OR expires_at > NOW())");
    $stmt->bind_param("is", $user_id, $session_token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $is_valid = $result->num_rows > 0;
    $stmt->close();
    
    return $is_valid;
}

function invalidateAllUserSessions($user_id) {
    $db = getDB();
    if (!$db) {
        return false;
    }
    
    $stmt = $db->prepare("UPDATE user_sessions SET is_active = 0 WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $result = $stmt->execute();
    $stmt->close();
    
    return $result;
}

// 检查登录状态
function checkLogin() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['user_id'])) {
        header('Location: ../login');
        exit;
    }
    
    if (!validateUserSession()) {
        $_SESSION = array();
        session_destroy();
        setcookie('user_info', '', time() - 3600, '/');
        setcookie('session_token', '', time() - 3600, '/');
        setcookie(session_name(), '', time() - 3600, '/');
        header('Location: ../login');
        exit;
    }
    
    // 实时检查过期状态
    checkExpiration();
}

/**
 * 检查用户是否过期
 */
function checkExpiration() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['user_id'])) {
        return true;
    }
    
    // 实时检查过期时间
    if (isset($_SESSION['expire_time'])) {
        $current_time = time();
        $expire_timestamp = strtotime($_SESSION['expire_time']);
        $expire_days = ceil(($expire_timestamp - $current_time) / (60 * 60 * 24));
        
        if ($expire_days <= 0) {
            $_SESSION['is_expired'] = true;
            $_SESSION['expire_days'] = 0;
            return true;
        } else {
            $_SESSION['is_expired'] = false;
            $_SESSION['expire_days'] = $expire_days;
            return false;
        }
    } else {
        $_SESSION['is_expired'] = true;
        return true;
    }
}

/**
 * 检查功能权限
 */
function checkFeatureAccess($feature) {
    $allowed_features = ['chongzhi', 'xufei', 'shezhi', 'out'];
    
    if (in_array($feature, $allowed_features)) {
        return true;
    }
    
    return !checkExpiration();
}

/**
 * 在功能页面开头调用，检查是否允许访问该功能
 */
function requireFeatureAccess($feature) {
    if (!checkFeatureAccess($feature)) {
        die('您的账号已过期，请充值后使用此功能');
    }
}

/**
 * 检查管理员权限
 */
function checkAdmin() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
        die('无权限访问此页面');
    }
}

/**
 * 获取充值记录
 */
function getRechargeRecords($status = null, $username = null, $limit = 10, $offset = 0) {
    $db = getDB();
    if (!$db) {
        return [];
    }

    $whereClause = '';
    $params = [];
    $types = '';

    if ($status !== null) {
        $whereClause .= " AND status = ?";
        $params[] = $status;
        $types .= 's';
    }

    if ($username !== null) {
        $whereClause .= " AND username = ?";
        $params[] = $username;
        $types .= 's';
    }

    $sql = "SELECT * FROM recharge_records WHERE 1=1 $whereClause ORDER BY created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $types .= 'i';
    $params[] = $offset;
    $types .= 'i';

    $stmt = $db->prepare($sql);
    if ($whereClause) {
        $stmt->bind_param($types, ...$params);
    } else {
        // 如果没有条件，则直接绑定分页参数
        $stmt->bind_param('ii', $limit, $offset);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $records = [];
    while ($row = $result->fetch_assoc()) {
        $records[] = $row;
    }
    return $records;
}

/**
 * 更新用户余额
 */
function updateUserBalance($username, $amount) {
    $db = getDB();
    if (!$db) {
        return false;
    }
    
    $stmt = $db->prepare("UPDATE users SET balance = balance + ? WHERE username = ?");
    $stmt->bind_param("ds", $amount, $username);
    return $stmt->execute();
}

// 错误处理
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("Error: [$errno] $errstr in $errfile on line $errline");
});

// 设置时区
date_default_timezone_set('Asia/Shanghai');
?>