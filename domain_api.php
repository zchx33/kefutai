<?php

// 启用所有错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 设置JSON响应头
header('Content-Type: application/json; charset=utf-8');

// 开始会话
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 检查用户是否登录
if (!isset($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => '未登录'], JSON_UNESCAPED_UNICODE);
    exit;
}

$username = $_SESSION['username'];
$user_id = $_SESSION['user_id'] ?? 0; // 假设session中有user_id
$action = $_POST['action'] ?? '';

// 引入数据库配置
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/dbconfig.php';

// 获取数据库连接
$db = getDB();
if (!$db) {
    echo json_encode(['success' => false, 'message' => '数据库连接失败'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 根据action执行相应操作
try {
    switch ($action) {
        case 'get_user_anti_red_config':
            getUserAntiRedConfig($username, $db);
            break;
        case 'update_redirect_switch':
            $redirect_to_browser = intval($_POST['redirect_to_browser'] ?? 0);
            updateRedirectSwitch($username, $redirect_to_browser, $db);
            break;
        case 'apply_anti_red_domain':
            $domain_name = $_POST['domain_name'] ?? '';
            applyAntiRedDomain($username, $domain_name, $db);
            break;
        case 'cancel_anti_red_domain':
            cancelAntiRedDomain($username, $db);
            break;
        case 'get_user_info':
            getUserInfo($username, $db);
            break;
        case 'get_user_links':
            getUserLinks($username, $db);
            break;
        case 'buy_anti_red':
            buyAntiRed($username, $db);
            break;
        case 'get_paid_interfaces':
            getPaidInterfaces($username, $db);
            break;
        case 'purchase_interface':
            $interface_id = intval($_POST['interface_id'] ?? 0);
            purchaseInterface($username, $interface_id, $db);
            break;
        case 'apply_custom_interface':
            $interface_url = $_POST['interface_url'] ?? '';
            $encoding_mode = $_POST['encoding_mode'] ?? 'base64';
            applyCustomInterface($username, $interface_url, $encoding_mode, $db);
            break;
        case 'get_anti_red_price':
            getAntiRedPrice($db);
            break;
            
        // 新增的防红开关接口
        case 'get_anti_red_switch_status':
            getAntiRedSwitchStatus($username, $db);
            break;
        case 'toggle_anti_red_switch':
            $enabled = intval($_POST['enabled'] ?? 0);
            toggleAntiRedSwitch($username, $enabled, $db);
            break;
            
        // 新增的接口操作
        case 'get_free_interfaces':
            getFreeInterfaces($db);
            break;
        case 'get_user_custom_interfaces':
            getUserCustomInterfaces($user_id, $db);
            break;
        case 'save_custom_interface':
            $remark = $_POST['remark'] ?? '';
            $url = $_POST['url'] ?? '';
            $encoding = $_POST['encoding'] ?? 'base64';
            saveCustomInterface($user_id, $remark, $url, $encoding, $db);
            break;
        case 'delete_custom_interface':
            $interface_id = intval($_POST['id'] ?? 0);
            deleteCustomInterface($user_id, $interface_id, $db);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => '未知操作: ' . $action], JSON_UNESCAPED_UNICODE);
            exit;
    }
} catch (Exception $e) {
    error_log("API错误: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    echo json_encode(['success' => false, 'message' => '服务器错误: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}

// 获取用户防红配置
function getUserAntiRedConfig($username, $db) {
    $stmt = $db->prepare("SELECT * FROM user_anti_red_config WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $config = $result->fetch_assoc();
    
    // 获取 session 中存储的 api_url
    session_start();
    // 优先检查自定义接口 URL，然后是付费/免费接口 URL
    $api_url = $_SESSION['custom_interface_url'] ?? $_SESSION['applied_api_url'] ?? null;
    $domain_type = $_SESSION['applied_domain_type'] ?? null;
    
    if (!$config) {
        // 如果用户没有配置记录，创建默认记录
        $stmt = $db->prepare("INSERT INTO user_anti_red_config (username, redirect_to_browser, apply_status, applied_domain) VALUES (?, 1, 'off', NULL)");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        
        $response = [
            'success' => true,
            'config' => [
                'redirect_to_browser' => true,
                'apply_status' => 'off',
                'applied_domain' => null,
                'api_url' => null
            ]
        ];
    } else {
        $response = [
            'success' => true,
            'config' => [
                'redirect_to_browser' => (bool)$config['redirect_to_browser'],
                'apply_status' => $config['apply_status'],
                'applied_domain' => $config['applied_domain'],
                'api_url' => $api_url
            ]
        ];
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

// 更新引到浏览器开关
function updateRedirectSwitch($username, $redirect_to_browser, $db) {
    // 检查用户配置记录是否存在
    $checkStmt = $db->prepare("SELECT id FROM user_anti_red_config WHERE username = ?");
    $checkStmt->bind_param("s", $username);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->fetch_assoc()) {
        // 更新现有记录
        $stmt = $db->prepare("UPDATE user_anti_red_config SET redirect_to_browser = ?, updated_at = NOW() WHERE username = ?");
        $stmt->bind_param("is", $redirect_to_browser, $username);
        $stmt->execute();
    } else {
        // 插入新记录
        $stmt = $db->prepare("INSERT INTO user_anti_red_config (username, redirect_to_browser, apply_status, applied_domain) VALUES (?, ?, 'off', NULL)");
        $stmt->bind_param("si", $username, $redirect_to_browser);
        $stmt->execute();
    }
    
    echo json_encode(['success' => true, 'message' => '设置已更新'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 获取防红开关状态
function getAntiRedSwitchStatus($username, $db) {
    $stmt = $db->prepare("SELECT apply_status FROM user_anti_red_config WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $config = $result->fetch_assoc();

    if (!$config) {
        // 如果没有记录，返回默认值'on'（开启）
        $apply_status = 'on';
    } else {
        $apply_status = $config['apply_status'];
    }

    echo json_encode([
        'success' => true,
        'apply_status' => $apply_status,
        'enabled' => $apply_status === 'on' ? 1 : 0
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 切换防红开关
function toggleAntiRedSwitch($username, $enabled, $db) {
    $apply_status = $enabled ? 'on' : 'off';
    
    // 检查用户配置记录是否存在
    $checkStmt = $db->prepare("SELECT id FROM user_anti_red_config WHERE username = ?");
    $checkStmt->bind_param("s", $username);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->fetch_assoc()) {
        // 更新现有记录
        $stmt = $db->prepare("UPDATE user_anti_red_config SET apply_status = ?, updated_at = NOW() WHERE username = ?");
        $stmt->bind_param("ss", $apply_status, $username);
        $stmt->execute();
    } else {
        // 插入新记录
        $stmt = $db->prepare("INSERT INTO user_anti_red_config (username, redirect_to_browser, apply_status, applied_domain) VALUES (?, 1, ?, NULL)");
        $stmt->bind_param("ss", $username, $apply_status);
        $stmt->execute();
    }
    
    // 更新session
    $_SESSION['apply_status'] = $apply_status;
    
    // 如果关闭防红开关，同时清除已应用的接口
    if ($enabled == 0) {
        // 清除已应用的接口
        $clearStmt = $db->prepare("UPDATE user_anti_red_config SET applied_domain = NULL WHERE username = ?");
        $clearStmt->bind_param("s", $username);
        $clearStmt->execute();
        
        // 清除session中的接口信息
        unset($_SESSION['applied_api_url']);
        unset($_SESSION['applied_domain']);
        unset($_SESSION['applied_domain_type']);
        unset($_SESSION['custom_interface_url']);
        unset($_SESSION['custom_encoding_mode']);
        unset($_SESSION['custom_interface_remark']);
    }
    
    echo json_encode([
        'success' => true,
        'enabled' => $enabled,
        'apply_status' => $apply_status,
        'message' => '防红开关已' . ($enabled ? '开启' : '关闭')
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 应用防红域名
function applyAntiRedDomain($username, $domain_name, $db) {
    // 首先检查是否是免费接口
    $freeStmt = $db->prepare("SELECT api_url FROM freeantired WHERE name = ? AND status = 'active' LIMIT 1");
    if ($freeStmt) {
        $freeStmt->bind_param("s", $domain_name);
        $freeStmt->execute();
        $freeResult = $freeStmt->get_result();
        $freeInterface = $freeResult->fetch_assoc();
    } else {
        $freeInterface = false;
    }
    
    if ($freeInterface) {
        // 找到了免费接口
        $api_url = $freeInterface['api_url'];
        $domain_type = 'free';
    } else {
        // 检查是否是付费接口
        $checkStmt = $db->prepare("SELECT api_url FROM anti_red_links WHERE domain_name = ? AND sold_to = ?");
        $checkStmt->bind_param("ss", $domain_name, $username);
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        $link = $result->fetch_assoc();
        
        if ($link) {
            // 找到了用户购买的接口
            $api_url = $link['api_url'];
            $domain_type = 'paid';
        } else {
            echo json_encode(['success' => false, 'message' => '您没有该域名的使用权'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
    
    // 清除自定义接口的Session变量
    unset($_SESSION['custom_interface_url']);
    unset($_SESSION['custom_encoding_mode']);
    unset($_SESSION['custom_interface_remark']);
    
    // 将API URL存储在session中
    $_SESSION['applied_api_url'] = $api_url;
    $_SESSION['applied_domain_type'] = $domain_type;
    $_SESSION['applied_domain'] = $domain_name;
    
    // 更新用户配置
    $configStmt = $db->prepare("SELECT id FROM user_anti_red_config WHERE username = ?");
    $configStmt->bind_param("s", $username);
    $configStmt->execute();
    $result = $configStmt->get_result();
    
    if ($result->fetch_assoc()) {
        // 更新现有记录
        $stmt = $db->prepare("UPDATE user_anti_red_config SET apply_status = 'on', applied_domain = ?, updated_at = NOW() WHERE username = ?");
        $stmt->bind_param("ss", $domain_name, $username);
        $stmt->execute();
    } else {
        // 插入新记录
        $stmt = $db->prepare("INSERT INTO user_anti_red_config (username, redirect_to_browser, apply_status, applied_domain) VALUES (?, 1, 'on', ?)");
        $stmt->bind_param("ss", $username, $domain_name);
        $stmt->execute();
    }
    
    echo json_encode([
        'success' => true, 
        'message' => '域名应用成功',
        'api_url' => $api_url,
        'domain_type' => $domain_type
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 取消应用防红域名
function cancelAntiRedDomain($username, $db) {
    // 检查用户配置记录是否存在
    $checkStmt = $db->prepare("SELECT id FROM user_anti_red_config WHERE username = ?");
    $checkStmt->bind_param("s", $username);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->fetch_assoc()) {
        // 更新现有记录
        $stmt = $db->prepare("UPDATE user_anti_red_config SET apply_status = 'off', applied_domain = NULL, updated_at = NOW() WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
    } else {
        // 插入新记录
        $stmt = $db->prepare("INSERT INTO user_anti_red_config (username, redirect_to_browser, apply_status, applied_domain) VALUES (?, 1, 'off', NULL)");
        $stmt->bind_param("s", $username);
        $stmt->execute();
    }
    
    // 清除session中的API URL
    unset($_SESSION['applied_api_url']);
    unset($_SESSION['applied_domain_type']);
    
    echo json_encode(['success' => true, 'message' => '已取消应用'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 获取用户信息
function getUserInfo($username, $db) {
    // 获取用户余额
    $userStmt = $db->prepare("SELECT balance FROM users WHERE username = ?");
    if (!$userStmt) {
        echo json_encode(['success' => false, 'message' => '数据库查询准备失败'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $userStmt->bind_param("s", $username);
    $userStmt->execute();
    $result = $userStmt->get_result();
    $user = $result->fetch_assoc();
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => '用户不存在'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // 获取可用防红链接库存
    $stockStmt = $db->prepare("SELECT COUNT(*) as stock FROM anti_red_links WHERE is_sold = 0");
    if ($stockStmt) {
        $stockStmt->execute();
        $result = $stockStmt->get_result();
        $stock = $result->fetch_assoc();
    } else {
        $stock = ['stock' => 0];
    }
    
    // 获取用户的自定义接口数量
    $user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
    $customStmt = $db->prepare("SELECT COUNT(*) as custom_count FROM userantired WHERE user_id = ? AND status = 'active'");
    if ($customStmt) {
        $customStmt->bind_param("i", $user_id);
        $customStmt->execute();
        $customResult = $customStmt->get_result();
        $customCount = $customResult->fetch_assoc();
    } else {
        $customCount = ['custom_count' => 0];
    }
    
    echo json_encode([
        'success' => true,
        'balance' => $user['balance'] ?? 0,
        'stock' => $stock['stock'] ?? 0,
        'custom_count' => $customCount['custom_count'] ?? 0
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 获取用户已购买的防红链接
function getUserLinks($username, $db) {
    $limit = intval($_POST['limit'] ?? 20);
    $stmt = $db->prepare("SELECT domain_name, api_url, sold_date FROM anti_red_links WHERE sold_to = ? ORDER BY sold_date DESC LIMIT ?");
    $stmt->bind_param("si", $username, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $links = $result->fetch_all(MYSQLI_ASSOC);
    
    echo json_encode([
        'success' => true,
        'links' => $links
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 在 buyAntiRed 函数中，修改价格获取逻辑
function buyAntiRed($username, $db) {
    $db->begin_transaction();
    
    try {
        // 获取当前全局价格
        $price_sql = "SELECT unit_price FROM anti_red_pricing WHERE item_name = '防红接口' AND is_active = 1 ORDER BY updated_at DESC LIMIT 1";
        $price_result = $db->query($price_sql);
        $current_price = 5.20; // 默认价格
        if ($price_result && $price_result->num_rows > 0) {
            $price_row = $price_result->fetch_assoc();
            $current_price = $price_row['unit_price'];
        }
        
        $userStmt = $db->prepare("SELECT balance FROM users WHERE username = ?");
        $userStmt->bind_param("s", $username);
        $userStmt->execute();
        $result = $userStmt->get_result();
        $user = $result->fetch_assoc();
        
        if (!$user) {
            $db->rollback();
            echo json_encode(['success' => false, 'message' => '用户不存在'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        if ($user['balance'] < $current_price) {
            $db->rollback();
            echo json_encode(['success' => false, 'message' => '余额不足，需要' . $current_price . '元'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        $linkStmt = $db->prepare("SELECT id, domain_name, api_url FROM anti_red_links WHERE is_sold = 0 LIMIT 1");
        $linkStmt->execute();
        $result = $linkStmt->get_result();
        $link = $result->fetch_assoc();
        
        if (!$link) {
            $db->rollback();
            echo json_encode(['success' => false, 'message' => '库存不足'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        $updateBalanceStmt = $db->prepare("UPDATE users SET balance = balance - ? WHERE username = ?");
        $updateBalanceStmt->bind_param("ds", $current_price, $username);
        $updateBalanceStmt->execute();
        
        $updateLinkStmt = $db->prepare("UPDATE anti_red_links SET is_sold = 1, sold_to = ?, sold_date = CURDATE() WHERE id = ?");
        $updateLinkStmt->bind_param("si", $username, $link['id']);
        $updateLinkStmt->execute();
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => '购买成功',
            'domain_name' => $link['domain_name'],
            'api_url' => $link['api_url'],
            'price' => $current_price
        ], JSON_UNESCAPED_UNICODE);
        exit;
    } catch (Exception $e) {
        $db->rollback();
        error_log("购买防红链接失败: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => '购买失败: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// 获取付费接口列表
function getPaidInterfaces($username, $db) {
    $stmt = $db->prepare("
        SELECT id, domain_name, api_url, 5.2 as price, 'safe' as status 
        FROM anti_red_links 
        WHERE is_sold = 0 
        ORDER BY id ASC
    ");
    
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        $interfaces = $result->fetch_all(MYSQLI_ASSOC);
    } else {
        $interfaces = [];
    }
    
    echo json_encode([
        'success' => true,
        'interfaces' => $interfaces
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 购买单个接口
function purchaseInterface($username, $interface_id, $db) {
    $db->begin_transaction();
    
    try {
        $stmt = $db->prepare("SELECT id, domain_name, api_url, 5.2 as price FROM anti_red_links WHERE id = ? AND is_sold = 0");
        if ($stmt) {
            $stmt->bind_param("i", $interface_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $interface = $result->fetch_assoc();
        } else {
            $interface = null;
        }
        
        if (!$interface) {
            $db->rollback();
            echo json_encode(['success' => false, 'message' => '接口不存在或已售出'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        $price = 5.2;
        
        $userStmt = $db->prepare("SELECT balance FROM users WHERE username = ?");
        $userStmt->bind_param("s", $username);
        $userStmt->execute();
        $result = $userStmt->get_result();
        $user = $result->fetch_assoc();
        
        if (!$user) {
            $db->rollback();
            echo json_encode(['success' => false, 'message' => '用户不存在'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        if ($user['balance'] < $price) {
            $db->rollback();
            echo json_encode(['success' => false, 'message' => '余额不足，需要' . $price . '元'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        $updateBalanceStmt = $db->prepare("UPDATE users SET balance = balance - ? WHERE username = ?");
        $updateBalanceStmt->bind_param("ds", $price, $username);
        $updateBalanceStmt->execute();
        
        $updateLinkStmt = $db->prepare("UPDATE anti_red_links SET is_sold = 1, sold_to = ?, sold_date = CURDATE() WHERE id = ?");
        $updateLinkStmt->bind_param("si", $username, $interface_id);
        $updateLinkStmt->execute();
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => '购买成功',
            'interface' => $interface
        ], JSON_UNESCAPED_UNICODE);
        exit;
    } catch (Exception $e) {
        $db->rollback();
        error_log("购买接口失败: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => '购买失败: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// 应用自定义接口
function applyCustomInterface($username, $interface_url, $encoding_mode, $db) {
    if (!filter_var($interface_url, FILTER_VALIDATE_URL)) {
        echo json_encode(['success' => false, 'message' => '无效的URL格式'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $valid_modes = ['base64', 'url', 'none'];
    if (!in_array($encoding_mode, $valid_modes)) {
        $encoding_mode = 'base64';
    }
    
    // 从POST参数获取备注
    $remark = $_POST['remark'] ?? '自定义接口';
    
    // 检查备注是否为空
    if (empty($remark)) {
        $remark = '自定义接口';
    }
    
    // 清除付费/免费接口的Session变量
    unset($_SESSION['applied_api_url']);
    unset($_SESSION['applied_domain']);
    unset($_SESSION['applied_domain_type']);
    
    // 将自定义接口信息存储在session中
    $_SESSION['custom_interface_url'] = $interface_url;
    $_SESSION['custom_encoding_mode'] = $encoding_mode;
    $_SESSION['custom_interface_remark'] = $remark;
    
    // 检查用户配置记录是否存在
    $checkStmt = $db->prepare("SELECT id FROM user_anti_red_config WHERE username = ?");
    $checkStmt->bind_param("s", $username);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->fetch_assoc()) {
        // 更新现有记录
        $stmt = $db->prepare("UPDATE user_anti_red_config SET apply_status = 'on', applied_domain = ?, updated_at = NOW() WHERE username = ?");
        $stmt->bind_param("ss", $remark, $username);
        $stmt->execute();
    } else {
        // 插入新记录
        $stmt = $db->prepare("INSERT INTO user_anti_red_config (username, redirect_to_browser, apply_status, applied_domain) VALUES (?, 1, 'on', ?)");
        $stmt->bind_param("ss", $username, $remark);
        $stmt->execute();
    }
    
    echo json_encode([
        'success' => true,
        'message' => '自定义接口应用成功',
        'interface_url' => $interface_url,
        'encoding_mode' => $encoding_mode,
        'remark' => $remark
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 获取免费接口列表
function getFreeInterfaces($db) {
    $stmt = $db->prepare("SELECT id, name, api_url, remark, status, created_at FROM freeantired WHERE status = 'active' ORDER BY id ASC");
    
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        $interfaces = $result->fetch_all(MYSQLI_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => $interfaces
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            'success' => false,
            'message' => '查询失败'
        ], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// 获取用户自定义接口列表
function getUserCustomInterfaces($user_id, $db) {
    if (!$user_id) {
        echo json_encode([
            'success' => false,
            'message' => '用户ID无效'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $stmt = $db->prepare("SELECT id, remark, api_url as url, encoding, status, created_at FROM userantired WHERE user_id = ? AND status = 'active' ORDER BY created_at DESC");
    
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $interfaces = $result->fetch_all(MYSQLI_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => $interfaces
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            'success' => false,
            'message' => '查询失败'
        ], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// 保存自定义接口
function saveCustomInterface($user_id, $remark, $url, $encoding, $db) {
    if (!$user_id) {
        echo json_encode(['success' => false, 'message' => '用户ID无效'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    if (empty($url)) {
        echo json_encode(['success' => false, 'message' => '接口URL不能为空'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        echo json_encode(['success' => false, 'message' => '无效的URL格式'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $valid_encodings = ['base64', 'url', 'none'];
    if (!in_array($encoding, $valid_encodings)) {
        $encoding = 'base64';
    }
    
    // 检查用户自定义接口数量（限制20个）
    $check_sql = "SELECT COUNT(*) as count FROM userantired WHERE user_id = ? AND status = 'active'";
    $check_stmt = $db->prepare($check_sql);
    $check_stmt->bind_param("i", $user_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    $count_row = $check_result->fetch_assoc();
    
    if ($count_row['count'] >= 20) {
        echo json_encode(['success' => false, 'message' => '最多只能添加20个自定义接口'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // 检查是否已存在相同的URL
    $check_url_sql = "SELECT COUNT(*) as count FROM userantired WHERE user_id = ? AND api_url = ? AND status = 'active'";
    $check_url_stmt = $db->prepare($check_url_sql);
    $check_url_stmt->bind_param("is", $user_id, $url);
    $check_url_stmt->execute();
    $check_url_result = $check_url_stmt->get_result();
    $url_count_row = $check_url_result->fetch_assoc();
    
    if ($url_count_row['count'] > 0) {
        echo json_encode(['success' => false, 'message' => '该接口URL已存在'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // 插入新记录
    $sql = "INSERT INTO userantired (user_id, remark, api_url, encoding, status, created_at) VALUES (?, ?, ?, ?, 'active', NOW())";
    $stmt = $db->prepare($sql);
    
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => '数据库语句准备失败'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    if (empty($remark)) {
        // 如果没有提供备注，生成一个默认的
        $count = $count_row['count'] + 1;
        $remark = "自定义接口{$count}";
    }
    
    $stmt->bind_param("isss", $user_id, $remark, $url, $encoding);
    
    if ($stmt->execute()) {
        $interface_id = $db->insert_id;
        echo json_encode([
            'success' => true,
            'id' => $interface_id,
            'message' => '接口保存成功'
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(['success' => false, 'message' => '保存失败: ' . $db->error], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// 删除自定义接口
function deleteCustomInterface($user_id, $interface_id, $db) {
    if (!$user_id || !$interface_id) {
        echo json_encode(['success' => false, 'message' => '参数错误'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // 确保用户只能删除自己的接口
    $sql = "UPDATE userantired SET status = 'deleted' WHERE id = ? AND user_id = ?";
    $stmt = $db->prepare($sql);
    
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => '数据库语句准备失败'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $stmt->bind_param("ii", $interface_id, $user_id);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => '接口已删除']);
        } else {
            echo json_encode(['success' => false, 'message' => '接口不存在或无权删除']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => '删除失败: ' . $db->error]);
    }
    exit;
}

// 设置应用接口（统一管理）
function setAppliedInterface($type, $api_url, $domain_name, $encoding = 'base64') {
    // 清理所有接口相关的Session变量
    $interface_vars = [
        'applied_api_url', 'applied_domain', 'applied_domain_type',
        'custom_interface_url', 'custom_encoding_mode', 'custom_interface_remark'
    ];
    
    foreach ($interface_vars as $var) {
        unset($_SESSION[$var]);
    }
    
    // 根据类型设置相应的Session变量
    if ($type === 'custom') {
        $_SESSION['custom_interface_url'] = $api_url;
        $_SESSION['custom_encoding_mode'] = $encoding;
        $_SESSION['custom_interface_remark'] = $domain_name;
    } else {
        $_SESSION['applied_api_url'] = $api_url;
        $_SESSION['applied_domain'] = $domain_name;
        $_SESSION['applied_domain_type'] = $type; // paid 或 free
    }
}

// 获取防红接口价格
function getAntiRedPrice($db) {
    // 防止缓存
    header("Cache-Control: no-cache, no-store, must-revalidate");
    header("Pragma: no-cache");
    header("Expires: 0");
    
    // 首先尝试从 anti_red_pricing 表获取价格
    $price_sql = "SELECT unit_price FROM anti_red_pricing WHERE item_name = '防红接口' AND is_active = 1 ORDER BY updated_at DESC LIMIT 1";
    $price_result = $db->query($price_sql);
    
    if ($price_result && $price_result->num_rows > 0) {
        $price_row = $price_result->fetch_assoc();
        $current_price = floatval($price_row['unit_price']);
    } else {
        // 如果没有价格记录，使用默认价格
        $current_price = 5.20;
    }
    
    echo json_encode([
        'success' => true,
        'price' => $current_price,
        'formatted_price' => '¥' . number_format($current_price, 2),
        'timestamp' => time()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
?>