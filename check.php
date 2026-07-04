<?php
if (!defined('FROM_ROUTER')) {
    http_response_code(403);
    exit('喜乐科技@x60898');
}
header('Content-Type: application/json');

// 启动session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 引入数据库配置
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/dbconfig.php';

// 兜底：如果 dbconfig.php 未更新，确保 generateVisitorToken 函数存在
if (!function_exists('generateVisitorToken')) {
    function generateVisitorToken() {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $token = 'xile-';
        for ($i = 0; $i < 32; $i++) {
            $token .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $token;
    }
}

// 处理预检请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// 获取action参数
$action = $_REQUEST['action'] ?? '';

// 1. 检查登录状态
if ($action === 'check_login') {
    handleCheckLogin();
}
// 2. 处理登录
elseif ($action === 'login') {
    handleLogin();
}
// 2.1 处理二级密码验证
elseif ($action === 'verify_second_password') {
    handleVerifySecondPassword();
}
// 3. 处理登出
elseif ($action === 'logout') {
    handleLogout();
}
// 4. 获取用户信息
elseif ($action === 'get_user_info') {
    handleGetUserInfo();
}
// 5. 修改二级密码
elseif ($action === 'change_second_password') {
    handleChangeSecondPassword();
}
// 6. 验证修改密码时的二级密码
elseif ($action === 'verify_second_password_for_password_change') {
    handleVerifySecondPasswordForPasswordChange();
}
// 7. Token 登录
elseif ($action === 'token_login') {
    handleTokenLogin();
}
// 8. 获取/生成访客 Token
elseif ($action === 'get_visitor_token') {
    handleGetVisitorToken();
}
// 9. 重新生成访客 Token
elseif ($action === 'regenerate_visitor_token') {
    handleRegenerateVisitorToken();
}
// 未知操作
else {
    jsonResponse([
        'code' => 0,
        'message' => '未知操作'
    ]);
}

/**
 * 检查登录状态
 */
function handleCheckLogin() {
    if (isset($_SESSION['user_id'])) {
        $is_expired = false;
        if (isset($_SESSION['expire_time'])) {
            $current_time = time();
            $expire_timestamp = strtotime($_SESSION['expire_time']);
            $expire_days = ceil(($expire_timestamp - $current_time) / (60 * 60 * 24));
            
            if ($expire_days <= 0) {
                $is_expired = true;
                $_SESSION['is_expired'] = true;
                $_SESSION['expire_days'] = 0;
            } else {
                $_SESSION['is_expired'] = false;
                $_SESSION['expire_days'] = $expire_days;
            }
        } else {
            $is_expired = true;
            $_SESSION['is_expired'] = true;
        }
        
        jsonResponse([
            'code' => 1,
            'message' => '已登录',
            'data' => [
                'username' => $_SESSION['username'] ?? '',
                'user_role' => $_SESSION['user_role'] ?? '',
                'balance' => $_SESSION['balance'] ?? 0,
                'expire_days' => $_SESSION['expire_days'] ?? 0,
                'is_expired' => $is_expired
            ]
        ]);
    } else {
        jsonResponse(['code' => 0, 'message' => '未登录']);
    }
}

function getClientIP() {
    // 只使用 REMOTE_ADDR，不信任客户端可伪造的 HTTP 头
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '';
}

function updateUserLoginIP($db, $user_id) {
    $current_ip = getClientIP();
    $stmt = $db->prepare("UPDATE users SET last_login_ip = ? WHERE id = ?");
    $stmt->bind_param("si", $current_ip, $user_id);
    $stmt->execute();
    $stmt->close();
}

/**
 * 处理登录
 */
function handleLogin() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse([
            'code' => 0,
            'message' => '无效的请求方法'
        ]);
    }
    
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        jsonResponse([
            'code' => 0,
            'message' => '用户名和密码不能为空'
        ]);
    }
    
    try {
        $db = getDB();
        if (!$db) {
            throw new Exception('数据库连接失败');
        }
        
        $tableCheck = $db->query("SHOW TABLES LIKE 'users'");
        if ($tableCheck && $tableCheck->num_rows == 0) {
            throw new Exception('用户表不存在，请联系管理员');
        }
        
        $stmt = $db->prepare("SELECT id, username, password, role, balance, expire_time, last_login_ip, second_password FROM users WHERE username = ?");
        if (!$stmt) {
            throw new Exception('查询准备失败: ' . $db->error);
        }
        
        $stmt->bind_param("s", $username);
        if (!$stmt->execute()) {
            throw new Exception('查询执行失败: ' . $stmt->error);
        }
        
        $result = $stmt->get_result();
        
        if ($user = $result->fetch_assoc()) {
            if (md5($password) === $user['password']) {
                $current_ip = getClientIP();
                $has_second_password = !empty($user['second_password']);
                $ip_changed = $has_second_password && !empty($user['last_login_ip']) && $user['last_login_ip'] !== $current_ip;
                
                if ($ip_changed) {
                    $_SESSION['pending_user_id'] = $user['id'];
                    $_SESSION['pending_username'] = $user['username'];
                    $_SESSION['pending_verify_needed'] = true;
                    
                    jsonResponse([
                        'code' => 2,
                        'message' => '检测到IP地址变化，请输入二级密码验证',
                        'need_second_password' => true,
                        'username' => $user['username']
                    ]);
                    return;
                }
                
                $is_expired = false;
                $expire_days = 0;
                
                if ($user['expire_time']) {
                    $current_time = time();
                    $expire_timestamp = strtotime($user['expire_time']);
                    $expire_days = ceil(($expire_timestamp - $current_time) / (60 * 60 * 24));
                    
                    if ($expire_days <= 0) {
                        $is_expired = true;
                        $expire_days = 0;
                    }
                } else {
                    $is_expired = true;
                }
                
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['balance'] = $user['balance'];
                $_SESSION['expire_time'] = $user['expire_time'];
                $_SESSION['expire_days'] = $expire_days;
                $_SESSION['is_expired'] = $is_expired;
                $_SESSION['login_time'] = time();
                $_SESSION['login_type'] = 'password';
                
                // 防止会话固定攻击
                session_regenerate_id(true);
                
                updateUserLoginIP($db, $user['id']);
                createUserSession($user['id']);
                
                $cookie_value = json_encode([
                    'user' => $user['username'],
                    'id' => $user['id'],
                    'expire_days' => $expire_days,
                    'expire_time' => $user['expire_time'],
                    'is_expired' => $is_expired
                ]);
                
                setcookie('user_info', $cookie_value, time() + (30 * 24 * 60 * 60), '/', '', true, true);
                
                jsonResponse([
                    'code' => 1,
                    'message' => '登录成功',
                    'redirect' => './consle',
                    'data' => [
                        'id' => $user['id'],
                        'username' => $user['username'],
                        'role' => $user['role'],
                        'balance' => $user['balance'],
                        'expire_days' => $expire_days,
                        'expire_time' => $user['expire_time'],
                        'is_expired' => $is_expired
                    ]
                ]);
            } else {
                jsonResponse([
                    'code' => 0,
                    'message' => '密码错误'
                ]);
            }
        } else {
            jsonResponse([
                'code' => 0,
                'message' => '未知用户'
            ]);
        }
    } catch (Exception $e) {
        jsonResponse([
            'code' => 0,
            'message' => '操作失败，请稍后重试'
        ]);
    }
}

/**
 * 处理二级密码验证
 */
function handleVerifySecondPassword() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse([
            'code' => 0,
            'message' => '无效的请求方法'
        ]);
    }
    
    if (!isset($_SESSION['pending_user_id']) || !$_SESSION['pending_verify_needed']) {
        jsonResponse([
            'code' => 0,
            'message' => '无效的验证请求，请重新登录'
        ]);
        return;
    }
    
    $second_password = $_POST['second_password'] ?? '';
    
    if (empty($second_password)) {
        jsonResponse([
            'code' => 0,
            'message' => '二级密码不能为空'
        ]);
        return;
    }
    
    try {
        $db = getDB();
        if (!$db) {
            throw new Exception('数据库连接失败');
        }
        
        $user_id = $_SESSION['pending_user_id'];
        $stmt = $db->prepare("SELECT id, username, password, role, balance, expire_time, second_password FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($user = $result->fetch_assoc()) {
            if (md5($second_password) === $user['second_password']) {
                $is_expired = false;
                $expire_days = 0;
                
                if ($user['expire_time']) {
                    $current_time = time();
                    $expire_timestamp = strtotime($user['expire_time']);
                    $expire_days = ceil(($expire_timestamp - $current_time) / (60 * 60 * 24));
                    
                    if ($expire_days <= 0) {
                        $is_expired = true;
                        $expire_days = 0;
                    }
                } else {
                    $is_expired = true;
                }
                
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['balance'] = $user['balance'];
                $_SESSION['expire_time'] = $user['expire_time'];
                $_SESSION['expire_days'] = $expire_days;
                $_SESSION['is_expired'] = $is_expired;
                $_SESSION['login_time'] = time();
                
                // 防止会话固定攻击
                session_regenerate_id(true);
                
                unset($_SESSION['pending_user_id']);
                unset($_SESSION['pending_username']);
                unset($_SESSION['pending_verify_needed']);
                
                updateUserLoginIP($db, $user['id']);
                createUserSession($user['id']);
                
                $cookie_value = json_encode([
                    'user' => $user['username'],
                    'id' => $user['id'],
                    'expire_days' => $expire_days,
                    'expire_time' => $user['expire_time'],
                    'is_expired' => $is_expired
                ]);
                
                setcookie('user_info', $cookie_value, time() + (30 * 24 * 60 * 60), '/', '', true, true);
                
                jsonResponse([
                    'code' => 1,
                    'message' => '验证成功',
                    'redirect' => './consle'
                ]);
            } else {
                jsonResponse([
                    'code' => 0,
                    'message' => '二级密码错误'
                ]);
            }
        } else {
            jsonResponse([
                'code' => 0,
                'message' => '用户不存在'
            ]);
        }
        
        $stmt->close();
    } catch (Exception $e) {
        jsonResponse([
            'code' => 0,
            'message' => '操作失败，请稍后重试'
        ]);
    }
}

/**
 * 处理登出
 */
function handleLogout() {
    // 清除session
    $_SESSION = array();
    
    // 销毁session
    if (session_destroy()) {
        // 清除cookie
        setcookie('user_info', '', time() - 3600, '/');
        jsonResponse([
            'code' => 1,
            'message' => '已退出登录',
            'redirect' => './'  // 返回登录页面
        ]);
    } else {
        jsonResponse([
            'code' => 0,
            'message' => '退出登录失败'
        ]);
    }
}

/**
 * 获取用户信息
 */
function handleGetUserInfo() {
    if (isset($_SESSION['user_id'])) {
        jsonResponse([
            'code' => 1,
            'message' => '成功',
            'data' => [
                'username' => $_SESSION['username'] ?? '',
                'user_role' => $_SESSION['user_role'] ?? '',
                'balance' => $_SESSION['balance'] ?? 0,
                'expire_days' => $_SESSION['expire_days'] ?? 0,
                'is_expired' => $_SESSION['is_expired'] ?? true,
                'login_time' => isset($_SESSION['login_time']) ? 
                    date('Y-m-d H:i:s', $_SESSION['login_time']) : ''
            ]
        ]);
    } else {
        jsonResponse(['code' => 0, 'message' => '未登录']);
    }
}

/**
 * 处理修改二级密码
 */
function handleChangeSecondPassword() {
    if (!isset($_SESSION['user_id'])) {
        jsonResponse([
            'code' => 0,
            'message' => '未登录'
        ]);
        return;
    }
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse([
            'code' => 0,
            'message' => '无效的请求方法'
        ]);
        return;
    }
    
    $current_password = $_POST['current_password'] ?? '';
    $new_second_password = $_POST['new_second_password'] ?? '';
    $confirm_second_password = $_POST['confirm_second_password'] ?? '';
    
    if (empty($current_password)) {
        jsonResponse([
            'code' => 0,
            'message' => '请输入当前登录密码'
        ]);
        return;
    }
    
    if (empty($new_second_password) || empty($confirm_second_password)) {
        jsonResponse([
            'code' => 0,
            'message' => '请输入新二级密码和确认密码'
        ]);
        return;
    }
    
    if (strlen($new_second_password) < 6) {
        jsonResponse([
            'code' => 0,
            'message' => '二级密码长度至少6位'
        ]);
        return;
    }
    
    if ($new_second_password !== $confirm_second_password) {
        jsonResponse([
            'code' => 0,
            'message' => '两次输入的二级密码不一致'
        ]);
        return;
    }
    
    try {
        $db = getDB();
        if (!$db) {
            throw new Exception('数据库连接失败');
        }
        
        $user_id = $_SESSION['user_id'];
        $stmt = $db->prepare("SELECT password, username, second_password FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($user = $result->fetch_assoc()) {
            if (md5($current_password) !== $user['password']) {
                jsonResponse([
                    'code' => 0,
                    'message' => '当前登录密码错误'
                ]);
                $stmt->close();
                return;
            }
            
            $new_password_md5 = md5($new_second_password);
            $update_stmt = $db->prepare("UPDATE users SET second_password = ? WHERE id = ?");
            $update_stmt->bind_param("si", $new_password_md5, $user_id);
            
            if ($update_stmt->execute()) {
                $action = '修改二级密码';
                $ip_address = getClientIP();
                $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
                $details = '二级密码已成功修改';
                
                $log_stmt = $db->prepare("INSERT INTO user_logs (user_id, username, action, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?)");
                $log_stmt->bind_param("isssss", $user_id, $user['username'], $action, $details, $ip_address, $user_agent);
                $log_stmt->execute();
                $log_stmt->close();
                
                jsonResponse([
                    'code' => 1,
                    'message' => '二级密码修改成功'
                ]);
            } else {
                jsonResponse([
                    'code' => 0,
                    'message' => '二级密码修改失败，请稍后重试'
                ]);
            }
            
            $update_stmt->close();
        } else {
            jsonResponse([
                'code' => 0,
                'message' => '用户不存在'
            ]);
        }
        
        $stmt->close();
    } catch (Exception $e) {
        jsonResponse([
            'code' => 0,
            'message' => '操作失败，请稍后重试'
        ]);
    }
}

/**
 * 验证修改密码时的二级密码
 */
function handleVerifySecondPasswordForPasswordChange() {
    if (!isset($_SESSION['user_id'])) {
        jsonResponse([
            'code' => 0,
            'message' => '未登录'
        ]);
        return;
    }
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse([
            'code' => 0,
            'message' => '无效的请求方法'
        ]);
        return;
    }
    
    $second_password = $_POST['second_password'] ?? '';
    
    if (empty($second_password)) {
        jsonResponse([
            'code' => 0,
            'message' => '二级密码不能为空'
        ]);
        return;
    }
    
    try {
        $db = getDB();
        if (!$db) {
            throw new Exception('数据库连接失败');
        }
        
        $user_id = $_SESSION['user_id'];
        $stmt = $db->prepare("SELECT second_password FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($user = $result->fetch_assoc()) {
            if (empty($user['second_password'])) {
                jsonResponse([
                    'code' => 0,
                    'message' => '未设置二级密码'
                ]);
            } elseif (md5($second_password) === $user['second_password']) {
                jsonResponse([
                    'code' => 1,
                    'message' => '验证成功'
                ]);
            } else {
                jsonResponse([
                    'code' => 0,
                    'message' => '二级密码错误'
                ]);
            }
        } else {
            jsonResponse([
                'code' => 0,
                'message' => '用户不存在'
            ]);
        }
        
        $stmt->close();
    } catch (Exception $e) {
        jsonResponse([
            'code' => 0,
            'message' => '操作失败，请稍后重试'
        ]);
    }
}

/**
 * Token 登录
 */
function handleTokenLogin() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse([
            'code' => 0,
            'message' => '无效的请求方法'
        ]);
        return;
    }
    
    $token = trim($_POST['visitor_token'] ?? '');
    
    if (empty($token)) {
        jsonResponse([
            'code' => 0,
            'message' => '访客Token不能为空'
        ]);
        return;
    }
    
    if (!preg_match('/^xile-[a-zA-Z0-9]{32}$/', $token)) {
        jsonResponse([
            'code' => 0,
            'message' => '无效的Token格式'
        ]);
        return;
    }
    
    try {
        $db = getDB();
        if (!$db) {
            throw new Exception('数据库连接失败');
        }
        
        $stmt = $db->prepare("SELECT id, username, role, balance, expire_time FROM users WHERE visitor_token = ?");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($user = $result->fetch_assoc()) {
            $expire_time = $user['expire_time'];
            $is_expired = false;
            $expire_days = 0;
            
            if ($user['role'] !== 'admin') {
                if ($expire_time) {
                    $current_time = time();
                    $expire_timestamp = strtotime($expire_time);
                    $expire_days = ceil(($expire_timestamp - $current_time) / (60 * 60 * 24));
                    
                    if ($expire_days <= 0) {
                        $is_expired = true;
                        $expire_days = 0;
                    }
                } else {
                    $is_expired = true;
                }
            } else {
                $is_expired = false;
                $expire_days = 999999;
            }
            
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['balance'] = $user['balance'];
            $_SESSION['expire_time'] = $user['expire_time'];
            $_SESSION['expire_days'] = $expire_days;
            $_SESSION['is_expired'] = $is_expired;
            $_SESSION['login_time'] = time();
            $_SESSION['login_type'] = 'token';
            
            // 防止会话固定攻击
            session_regenerate_id(true);
            
            createUserSession($user['id'], null, 'token');
            
            $cookie_value = json_encode([
                'user' => $user['username'],
                'id' => $user['id'],
                'expire_days' => $expire_days,
                'expire_time' => $user['expire_time'],
                'is_expired' => $is_expired
            ]);
            
            setcookie('user_info', $cookie_value, time() + (30 * 24 * 60 * 60), '/', '', true, true);
            
            jsonResponse([
                'code' => 1,
                'message' => '登录成功',
                'redirect' => './consle',
                'data' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'role' => $user['role'],
                    'balance' => $user['balance'],
                    'expire_days' => $expire_days,
                    'expire_time' => $user['expire_time'],
                    'is_expired' => $is_expired,
                    'login_type' => 'token'
                ]
            ]);
        } else {
            jsonResponse([
                'code' => 0,
                'message' => 'Token无效或已过期'
            ]);
        }
        
        $stmt->close();
    } catch (Exception $e) {
        jsonResponse([
            'code' => 0,
            'message' => '操作失败，请稍后重试'
        ]);
    }
}

/**
 * 获取/生成访客 Token
 */
function handleGetVisitorToken() {
    if (!isset($_SESSION['user_id'])) {
        jsonResponse([
            'code' => 0,
            'message' => '未登录'
        ]);
        return;
    }
    
    try {
        $db = getDB();
        if (!$db) {
            throw new Exception('数据库连接失败');
        }
        
        $user_id = $_SESSION['user_id'];
        $stmt = $db->prepare("SELECT visitor_token FROM users WHERE id = ?");
        if (!$stmt) {
            throw new Exception('数据库字段缺失，请先执行SQL添加visitor_token字段');
        }
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($user = $result->fetch_assoc()) {
            $token = $user['visitor_token'];
            
            if (empty($token)) {
                $token = generateVisitorToken();
                $update_stmt = $db->prepare("UPDATE users SET visitor_token = ? WHERE id = ?");
                if (!$update_stmt) {
                    throw new Exception('数据库字段缺失，请先执行SQL添加visitor_token字段');
                }
                $update_stmt->bind_param("si", $token, $user_id);
                $update_stmt->execute();
                $update_stmt->close();
            }
            
            jsonResponse([
                'code' => 1,
                'message' => '获取成功',
                'data' => [
                    'visitor_token' => $token
                ]
            ]);
        } else {
            jsonResponse([
                'code' => 0,
                'message' => '用户不存在'
            ]);
        }
        
        $stmt->close();
    } catch (Exception $e) {
        jsonResponse([
            'code' => 0,
            'message' => '操作失败，请稍后重试'
        ]);
    }
}

/**
 * 重新生成访客 Token
 */
function handleRegenerateVisitorToken() {
    if (!isset($_SESSION['user_id'])) {
        jsonResponse([
            'code' => 0,
            'message' => '未登录'
        ]);
        return;
    }
    
    // Token 登录模式下禁止重新生成 Token
    if (isset($_SESSION['login_type']) && $_SESSION['login_type'] === 'token') {
        jsonResponse([
            'code' => 0,
            'message' => 'Token 登录模式下无法修改 Token'
        ]);
        return;
    }
    
    try {
        $db = getDB();
        if (!$db) {
            throw new Exception('数据库连接失败');
        }
        
        $user_id = $_SESSION['user_id'];
        $token = generateVisitorToken();
        
        $stmt = $db->prepare("UPDATE users SET visitor_token = ? WHERE id = ?");
        if (!$stmt) {
            throw new Exception('数据库字段缺失，请先执行SQL添加visitor_token字段');
        }
        $stmt->bind_param("si", $token, $user_id);
        
        if ($stmt->execute()) {
            jsonResponse([
                'code' => 1,
                'message' => 'Token已重置',
                'data' => [
                    'visitor_token' => $token
                ]
            ]);
        } else {
            jsonResponse([
                'code' => 0,
                'message' => '重置失败'
            ]);
        }
        
        $stmt->close();
    } catch (Exception $e) {
        jsonResponse([
            'code' => 0,
            'message' => '操作失败，请稍后重试'
        ]);
    }
}
?>