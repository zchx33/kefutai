<?php
// /api/kefu/index.php - 客服页面管理API（前后端分离）
error_reporting(E_ALL);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/dbconfig.php';

session_start();
if (!isset($_SESSION['username']) || empty($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => '未登录']);
    exit;
}

$currentAgent = $_SESSION['username'];
$userId = $_SESSION['user_id'];

try {
    $db = getDB();
    if (!$db) {
        echo json_encode(['success' => false, 'message' => '数据库连接失败']);
        exit;
    }
    $db->set_charset('utf8mb4');
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => '数据库连接失败']);
    exit;
}

// 获取action参数（支持GET和POST）
$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'list':
            echo json_encode(getServicePages($db, $userId));
            break;

        case 'add_service_page':
            echo json_encode(addServicePage($db, $_POST, $userId));
            break;

        case 'edit_service_page':
            echo json_encode(editServicePage($db, $_POST, $userId));
            break;

        case 'delete_service_page':
            $page_id = intval($_POST['page_id'] ?? 0);
            echo json_encode(deleteServicePage($db, $page_id, $userId));
            break;

        case 'get_page_details':
            $page_id = intval($_POST['page_id'] ?? $_GET['page_id'] ?? 0);
            echo json_encode(getPageDetails($db, $page_id, $userId));
            break;

        case 'toggle_page_status':
            $page_id = intval($_POST['page_id'] ?? 0);
            echo json_encode(togglePageStatus($db, $page_id, $userId));
            break;

        case 'generate_share_url':
            $page_code = $_POST['page_code'] ?? '';
            $username = $_POST['username'] ?? '';
            $url = generateServiceUrl($page_code, '', $username);
            echo json_encode(['success' => true, 'url' => $url]);
            break;

        case 'upload_avatar':
            echo json_encode(uploadAvatar($_FILES, $userId));
            break;

        case 'get_site_url_config':
            echo json_encode(getSiteUrlConfig($db));
            break;

        default:
            echo json_encode(['success' => false, 'message' => '未知操作']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => '服务器错误: ' . $e->getMessage()]);
}

$db->close();
exit;

// ==================== 业务函数 ====================

function getSiteUrlConfig($db) {
    $result = $db->query("SELECT site_url, site_url_enabled FROM webconfig ORDER BY id DESC LIMIT 1");
    if ($result && $result->num_rows > 0) {
        $config = $result->fetch_assoc();
        return ['success' => true, 'config' => [
            'site_url' => $config['site_url'] ?? '',
            'site_url_enabled' => !empty($config['site_url_enabled'])
        ]];
    }
    return ['success' => true, 'config' => null];
}

function uploadAvatar($files, $user_id) {
    if (!isset($files['avatar']) || $files['avatar']['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => '文件上传失败'];
    }
    
    $file = $files['avatar'];
    
    // 大小限制
    if ($file['size'] > 2 * 1024 * 1024) {
        return ['success' => false, 'message' => '图片大小不能超过2MB'];
    }
    
    // 安全检测：用 finfo 检测真实文件类型（不信任浏览器提供的 type）
    $allowedMimes = ['image/jpeg', 'image/png', 'image/gif'];
    $realMime = '';
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $realMime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
    } elseif (function_exists('mime_content_type')) {
        $realMime = mime_content_type($file['tmp_name']);
    } else {
        $realMime = $file['type'];
    }
    
    if (!in_array($realMime, $allowedMimes)) {
        return ['success' => false, 'message' => '只支持 JPG, PNG, GIF 格式的图片'];
    }
    
    // 安全检测：检查文件内容是否包含 PHP 代码
    $fileContent = file_get_contents($file['tmp_name']);
    if (preg_match('/<\?php|<\?=/i', $fileContent)) {
        return ['success' => false, 'message' => '文件包含非法内容'];
    }
    
    // 强制使用安全扩展名（不信任原始文件名）
    $mimeToExt = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif'
    ];
    $safeExtension = $mimeToExt[$realMime] ?? 'jpg';
    
    $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/avatars/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // 生成安全的文件名（只包含安全字符）
    $fileName = 'avatar_' . $user_id . '_' . time() . '_' . uniqid() . '.' . $safeExtension;
    $filePath = $uploadDir . $fileName;
    
    if (move_uploaded_file($file['tmp_name'], $filePath)) {
        $relativePath = '/uploads/avatars/' . $fileName;
        return ['success' => true, 'message' => '上传成功', 'filePath' => $relativePath];
    } else {
        return ['success' => false, 'message' => '文件保存失败'];
    }
}

function getServicePages($db, $user_id) {
    $query = "SELECT * FROM XEmsg_pages 
              WHERE XEmsg_user_id = ? 
              ORDER BY XEmsg_created_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $pages = $result->fetch_all(MYSQLI_ASSOC);
    
    return ['success' => true, 'data' => $pages];
}

function generateCustomerName($length = 6) {
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $randomString;
}

function generateShareParam($length = 16) {
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $randomString;
}

function getAntiRedConfig($username) {
    $db = getDB();
    if (!$db) return null;
    
    $stmt = $db->prepare("SELECT apply_status FROM user_anti_red_config WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $config = $result->fetch_assoc();
    
    if (!$config) {
        $apply_status = 'on';
    } else {
        $apply_status = $config['apply_status'];
    }
    
    if ($apply_status === 'off') {
        return null;
    }
    
    if (isset($_SESSION['applied_api_url']) && !empty($_SESSION['applied_api_url'])) {
        $type = $_SESSION['applied_domain_type'] ?? 'paid';
        $applied_domain = $_SESSION['applied_domain'] ?? '付费接口';
        
        return [
            'api_url' => $_SESSION['applied_api_url'],
            'apply_status' => 'on',
            'applied_domain' => $applied_domain,
            'encoding_mode' => 'base64',
            'type' => $type
        ];
    }
    
    if (isset($_SESSION['custom_interface_url']) && !empty($_SESSION['custom_interface_url'])) {
        return [
            'api_url' => $_SESSION['custom_interface_url'],
            'apply_status' => 'on',
            'applied_domain' => isset($_SESSION['custom_interface_remark']) ? $_SESSION['custom_interface_remark'] : '自定义接口',
            'encoding_mode' => isset($_SESSION['custom_encoding_mode']) ? $_SESSION['custom_encoding_mode'] : 'base64',
            'type' => 'custom'
        ];
    }
    
    if ($config && $config['apply_status'] === 'on' && !empty($config['applied_domain'])) {
        $applied_domain = $config['applied_domain'];
        
        $freeStmt = $db->prepare("SELECT api_url FROM freeantired WHERE name = ? AND status = 'active'");
        $freeStmt->bind_param("s", $applied_domain);
        $freeStmt->execute();
        $freeResult = $freeStmt->get_result();
        
        if ($freeResult->num_rows > 0) {
            $freeData = $freeResult->fetch_assoc();
            $config['api_url'] = $freeData['api_url'];
            $config['type'] = 'free';
            $config['encoding_mode'] = 'base64';
            return $config;
        }
        
        $paidStmt = $db->prepare("SELECT api_url FROM anti_red_links WHERE domain_name = ? AND sold_to = ?");
        $paidStmt->bind_param("ss", $applied_domain, $username);
        $paidStmt->execute();
        $paidResult = $paidStmt->get_result();
        
        if ($paidResult->num_rows > 0) {
            $paidData = $paidResult->fetch_assoc();
            $config['api_url'] = $paidData['api_url'];
            $config['type'] = 'paid';
            $config['encoding_mode'] = 'base64';
            return $config;
        }
        
        $customStmt = $db->prepare("SELECT ua.api_url, ua.encoding 
                                    FROM userantired ua 
                                    JOIN users u ON ua.user_id = u.id 
                                    WHERE u.username = ? AND ua.remark = ? AND ua.status = 'active'");
        $customStmt->bind_param("ss", $username, $applied_domain);
        $customStmt->execute();
        $customResult = $customStmt->get_result();
        
        if ($customResult->num_rows > 0) {
            $customData = $customResult->fetch_assoc();
            $config['api_url'] = $customData['api_url'];
            $config['encoding_mode'] = $customData['encoding'] ?? 'base64';
            $config['type'] = 'custom';
            return $config;
        }
    }
    
    return null;
}

function generateXEDataToken() {
    return md5(uniqid(mt_rand(), true));
}

function createChatSession($sessionId, $customerName, $agentAccount, $platform = '独立-自定义客服') {
    $db = getDB();
    if (!$db) return false;
    
    $stmt = $db->prepare("SELECT xedata_token FROM `XE-SKDJWKSNCDATA` WHERE session_id = ?");
    $stmt->bind_param("s", $sessionId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['xedata_token'];
    }
    
    $xedataToken = generateXEDataToken();
    $expiresAt = date('Y-m-d H:i:s', strtotime('+7 days'));
    
    $stmt = $db->prepare("INSERT INTO `XE-SKDJWKSNCDATA` 
                         (session_id, xedata_token, customer_name, agent_account, expires_at, platform) 
                         VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssss", $sessionId, $xedataToken, $customerName, $agentAccount, $expiresAt, $platform);
    
    if ($stmt->execute()) {
        return $xedataToken;
    }
    return false;
}

function generateServiceUrl($page_code, $share_param, $username = '') {
    $currentDomain = $_SERVER['HTTP_HOST'];
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $baseUrl = $protocol . "://" . $currentDomain;
    
    $fixedSeed = md5($page_code . ($username ?: 'default'));
    
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
    $customerName = '';
    for ($i = 0; $i < 6; $i++) {
        $customerName .= $characters[hexdec(substr($fixedSeed, $i * 2, 2)) % strlen($characters)];
    }
    
    $sessionId = 'a' . $customerName . 'z-p' . ($username ?: 'default') . 's';
    
    $xedataToken = createChatSession($sessionId, $customerName, $username ?: 'default', '闲鱼');
    
    $originalServiceUrl = $baseUrl . '/XeKefu?code=' . $page_code . '&id=' . $sessionId;
    
    if ($xedataToken) {
        $originalServiceUrl .= '&XEDATA=' . $xedataToken;
    }
    
    $antiRedConfig = getAntiRedConfig($username ?: 'default');
    
    if ($antiRedConfig && $antiRedConfig['apply_status'] === 'on' && !empty($antiRedConfig['api_url'])) {
        $encodedUrl = base64_encode($originalServiceUrl);
        return $antiRedConfig['api_url'] . $encodedUrl;
    }
    
    // 源站URL配置（防红未开启时生效）
    $siteDb = getDB();
    $siteUrlResult = $siteDb->query("SELECT site_url, site_url_enabled FROM webconfig ORDER BY id DESC LIMIT 1");
    if ($siteUrlResult && $siteUrlResult->num_rows > 0) {
        $siteUrlRow = $siteUrlResult->fetch_assoc();
        if (!empty($siteUrlRow['site_url_enabled']) && !empty($siteUrlRow['site_url'])) {
            return $siteUrlRow['site_url'] . base64_encode($originalServiceUrl);
        }
    }
    
    return $originalServiceUrl;
}

function addServicePage($db, $data, $user_id) {
    $page_code = generateUniquePageCode($db);
    $share_param = generateShareParam();
    
    $avatar_url = $data['avatar_url'] ?? '';
    $page_title = $data['page_title'];
    $company_name = $data['company_name'] ?? '';
    $company_subtitle = $data['company_subtitle'] ?? '';
    $badge_text = $data['badge_text'] ?? '';
    $service_hours = $data['service_hours'] ?? '';
    $top_badge_1 = $data['top_badge_1'] ?? '';
    $top_badge_2 = $data['top_badge_2'] ?? '';
    $welcome_message = $data['welcome_message'] ?? '';
    $status = $data['status'];
    $poster_entry_text = $data['poster_entry_text'] ?? '';
    
    $query = "INSERT INTO XEmsg_pages 
              (XEmsg_id, XEmsg_user_id, XEmsg_page_title, XEmsg_company_name, XEmsg_company_subtitle, 
               XEmsg_badge_text, XEmsg_service_hours, XEmsg_top_badge_1, XEmsg_top_badge_2,
               XEmsg_welcome_message, XEmsg_avatar_url, XEmsg_status, XEmsg_page_code,
               XEmsg_poster_entry_text, XEmsg_share_param) 
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    // 生成自增ID
    $idResult = $db->query("SELECT MAX(XEmsg_id) as max_id FROM XEmsg_pages");
    $idRow = $idResult->fetch_assoc();
    $newId = ($idRow['max_id'] ?? 0) + 1;
    
    $stmt = $db->prepare($query);
    $stmt->bind_param("iisssssssssssss", 
        $newId, $user_id, $page_title, $company_name, $company_subtitle,
        $badge_text, $service_hours, $top_badge_1, $top_badge_2,
        $welcome_message, $avatar_url, $status, $page_code,
        $poster_entry_text, $share_param
    );
    
    if ($stmt->execute()) {
        return ['success' => true, 'message' => '客服页面创建成功', 'page_code' => $page_code];
    } else {
        return ['success' => false, 'message' => '创建失败: ' . $stmt->error];
    }
}

function editServicePage($db, $data, $user_id) {
    if (!verifyPageOwnership($db, $data['page_id'], $user_id)) {
        return ['success' => false, 'message' => '无权限操作此页面'];
    }
    
    $avatar_url = $data['avatar_url'] ?? '';
    $page_title = $data['page_title'];
    $company_name = $data['company_name'] ?? '';
    $company_subtitle = $data['company_subtitle'] ?? '';
    $badge_text = $data['badge_text'] ?? '';
    $service_hours = $data['service_hours'] ?? '';
    $top_badge_1 = $data['top_badge_1'] ?? '';
    $top_badge_2 = $data['top_badge_2'] ?? '';
    $welcome_message = $data['welcome_message'] ?? '';
    $status = $data['status'];
    $poster_entry_text = $data['poster_entry_text'] ?? '';
    $page_id = $data['page_id'];
    
    $query = "UPDATE XEmsg_pages 
              SET XEmsg_page_title = ?, XEmsg_company_name = ?, XEmsg_company_subtitle = ?,
                  XEmsg_badge_text = ?, XEmsg_service_hours = ?, XEmsg_top_badge_1 = ?,
                  XEmsg_top_badge_2 = ?, XEmsg_welcome_message = ?, XEmsg_avatar_url = ?,
                  XEmsg_status = ?, XEmsg_poster_entry_text = ?, XEmsg_updated_at = CURRENT_TIMESTAMP
              WHERE XEmsg_id = ?";
    
    $stmt = $db->prepare($query);
    $stmt->bind_param("sssssssssssi", 
        $page_title, $company_name, $company_subtitle,
        $badge_text, $service_hours, $top_badge_1, $top_badge_2,
        $welcome_message, $avatar_url, $status, $poster_entry_text, $page_id
    );
    
    if ($stmt->execute()) {
        return ['success' => true, 'message' => '客服页面更新成功'];
    } else {
        return ['success' => false, 'message' => '更新失败: ' . $stmt->error];
    }
}

function deleteServicePage($db, $page_id, $user_id) {
    if (!verifyPageOwnership($db, $page_id, $user_id)) {
        return ['success' => false, 'message' => '无权限删除此页面'];
    }
    
    $query = "DELETE FROM XEmsg_pages WHERE XEmsg_id = ?";
    $stmt = $db->prepare($query);
    $stmt->bind_param("i", $page_id);
    
    if ($stmt->execute()) {
        return ['success' => true, 'message' => '客服页面删除成功'];
    } else {
        return ['success' => false, 'message' => '删除失败: ' . $stmt->error];
    }
}

function getPageDetails($db, $page_id, $user_id) {
    if (!verifyPageOwnership($db, $page_id, $user_id)) {
        return ['success' => false, 'message' => '无权限查看此页面'];
    }
    
    $query = "SELECT * FROM XEmsg_pages WHERE XEmsg_id = ?";
    $stmt = $db->prepare($query);
    $stmt->bind_param("i", $page_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $page = $result->fetch_assoc();
    
    if ($page) {
        return ['success' => true, 'data' => $page];
    } else {
        return ['success' => false, 'message' => '页面不存在'];
    }
}

function togglePageStatus($db, $page_id, $user_id) {
    if (!verifyPageOwnership($db, $page_id, $user_id)) {
        return ['success' => false, 'message' => '无权限操作此页面'];
    }
    
    $query = "UPDATE XEmsg_pages 
              SET XEmsg_status = CASE WHEN XEmsg_status = 'active' THEN 'inactive' ELSE 'active' END,
                  XEmsg_updated_at = CURRENT_TIMESTAMP 
              WHERE XEmsg_id = ?";
    
    $stmt = $db->prepare($query);
    $stmt->bind_param("i", $page_id);
    
    if ($stmt->execute()) {
        return ['success' => true, 'message' => '状态更新成功'];
    } else {
        return ['success' => false, 'message' => '状态更新失败'];
    }
}

function verifyPageOwnership($db, $page_id, $user_id) {
    $query = "SELECT XEmsg_user_id FROM XEmsg_pages WHERE XEmsg_id = ?";
    $stmt = $db->prepare($query);
    $stmt->bind_param("i", $page_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $page = $result->fetch_assoc();
    
    return $page && $page['XEmsg_user_id'] == $user_id;
}

function generateUniquePageCode($db) {
    do {
        $code = 'CS' . substr(md5(uniqid(rand(), true)), 0, 8);
        $query = "SELECT COUNT(*) FROM XEmsg_pages WHERE XEmsg_page_code = ?";
        $stmt = $db->prepare($query);
        $stmt->bind_param("s", $code);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();
    } while ($count > 0);
    
    return $code;
}
