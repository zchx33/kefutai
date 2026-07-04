<?php

// /api/lib/msglist.php - 修复版本：直接使用数据库查询在线状态
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 设置最大执行时间
set_time_limit(20);

// 设置响应头
header('Content-Type: application/json; charset=utf-8');

// 开启错误日志
ini_set('log_errors', 1);
ini_set('error_log', '/tmp/msglist_debug.log');

// 清空输出缓冲区
while (ob_get_level()) {
    ob_end_clean();
}

// 检查配置文件
$config_path = dirname(dirname(dirname(__FILE__))) . '/config/dbconfig.php';
if (!file_exists($config_path)) {
    error_log("配置文件不存在: " . $config_path);
    echo json_encode(['success' => false, 'error' => '配置文件不存在']);
    exit;
}

require_once $config_path;

// 检查登录
session_start();
if (!isset($_SESSION['username']) || empty($_SESSION['username'])) {
    error_log("用户未登录");
    echo json_encode(['success' => false, 'error' => '未登录']);
    exit;
}

$currentAgent = $_SESSION['username'];
error_log("当前客服账号: " . $currentAgent);

// 获取参数
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 30;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$type = isset($_GET['type']) ? trim($_GET['type']) : ''; // all, private, group
if (!in_array($type, ['all', 'private', 'group'])) {
    $type = 'all';
}

$page = max(1, $page);
$limit = max(10, min($limit, 100));
$offset = ($page - 1) * $limit;

$response = [
    'success' => false,
    'html' => '',
    'has_more' => false,
    'page' => $page,
    'total' => 0,
    'online_count' => 0
];

// 记录开始时间
$start_time = microtime(true);

error_log("========== 开始聊天列表查询 ==========");
error_log("参数: page={$page}, limit={$limit}, search={$search}, agent={$currentAgent}");

try {
    $db = getDB();
    if (!$db) {
        error_log("数据库连接失败");
        $response['error'] = '数据库连接失败';
        echo json_encode($response);
        exit;
    }
    
    // 设置字符集
    $db->set_charset('utf8mb4');
    error_log("数据库连接成功");
    
    // 1. 获取总记录数
    $count_sql = "SELECT COUNT(DISTINCT customer_name) as total 
                 FROM chat_messages 
                 WHERE agent_account = ?";
    $params_count = [$currentAgent];
    $types_count = "s";
    
    if (!empty($search)) {
        $count_sql .= " AND (customer_name LIKE ? OR content LIKE ? OR platform LIKE ?)";
        $search_param = "%{$search}%";
        $params_count[] = $search_param;
        $params_count[] = $search_param;
        $params_count[] = $search_param;
        $types_count .= "sss";
    }
    
    // 按类型过滤：群聊为盼之群聊、白情群聊、螃蟹群聊
    if ($type === 'group') {
        $count_sql .= " AND platform IN ('盼之群聊', '白情群聊', '螃蟹群聊')";
    } elseif ($type === 'private') {
        $count_sql .= " AND (platform NOT IN ('盼之群聊', '白情群聊', '螃蟹群聊') OR platform IS NULL)";
    }
    
    $stmt_count = $db->prepare($count_sql);
    if ($stmt_count) {
        $stmt_count->bind_param($types_count, ...$params_count);
        $stmt_count->execute();
        $result_count = $stmt_count->get_result();
        if ($row_count = $result_count->fetch_assoc()) {
            $response['total'] = intval($row_count['total']);
            $response['has_more'] = ($page * $limit) < $response['total'];
        }
        $stmt_count->close();
    }
    
    error_log("总记录数: " . $response['total']);
    
    // 2. 获取会话数据 - 按客户名称分组，每个客户只显示最新消息
    // 使用更简洁的方式获取每个客户的最新消息（包括客户发送的图片）
    $subquery = "SELECT 
        customer_name,
        session_key,
        id as max_id,
        content,
        message_type,
        image_path
     FROM chat_messages 
     WHERE id IN (
         SELECT MAX(id) FROM chat_messages 
         WHERE customer_name IN (
             SELECT DISTINCT customer_name FROM chat_messages 
             WHERE agent_account = ?";
    
    $params_sub = [$currentAgent];
    $types_sub = "s";
    
    // 按类型过滤子查询中的内层
    if ($type === 'group') {
        $subquery .= " AND platform IN ('盼之群聊', '白情群聊', '螃蟹群聊')";
    } elseif ($type === 'private') {
        $subquery .= " AND (platform NOT IN ('盼之群聊', '白情群聊', '螃蟹群聊') OR platform IS NULL)";
    }
    
    $subquery .= ")";
    
    if (!empty($search)) {
        $subquery .= " AND (customer_name LIKE ? OR content LIKE ?)";
        $search_param = "%{$search}%";
        $params_sub[] = $search_param;
        $params_sub[] = $search_param;
        $types_sub .= "ss";
    }
    
    $subquery .= " GROUP BY customer_name";
    $subquery .= " )";
    
    $subquery .= " ORDER BY max_id DESC LIMIT ? OFFSET ?";
    $params_sub[] = $limit;
    $params_sub[] = $offset;
    $types_sub .= "ii";
    
    $stmt_sub = $db->prepare($subquery);
    if (!$stmt_sub) {
        $error = $db->error;
        error_log("子查询准备失败: " . $error);
        $response['error'] = '子查询准备失败: ' . $error;
        echo json_encode($response);
        exit;
    }
    
    $stmt_sub->bind_param($types_sub, ...$params_sub);
    
    if (!$stmt_sub->execute()) {
        $error = $stmt_sub->error;
        error_log("子查询执行失败: " . $error);
        $response['error'] = '子查询执行失败: ' . $error;
        echo json_encode($response);
        exit;
    }
    
    $result_sub = $stmt_sub->get_result();
    $message_ids = [];
    $session_keys = [];
    $customers = [];
    
    while ($row = $result_sub->fetch_assoc()) {
        $message_ids[] = intval($row['max_id']);
        $session_keys[] = $row['session_key'];
        $customers[] = $row['customer_name'];
    }
    $stmt_sub->close();
    
    error_log("找到 " . count($customers) . " 个客户");
    
    // 如果没有任何消息
    if (empty($message_ids)) {
        error_log("没有任何消息");
        $response['success'] = true;
        $response['html'] = '<div class="empty"><i class="bi bi-chat-dots"></i><h3>暂无客户会话</h3><p>等待新客户连接</p></div>';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // 3. 获取详细信息
    $placeholders = implode(',', array_fill(0, count($message_ids), '?'));
    $detail_sql = "SELECT 
        cm.session_key,
        cm.customer_name,
        cm.client_ip,
        -- 优先获取会话中最近的非空平台信息
        COALESCE(
            (SELECT platform FROM chat_messages 
             WHERE session_key = cm.session_key 
               AND platform IS NOT NULL 
               AND platform != '' 
               AND platform != '默认'
             ORDER BY id DESC LIMIT 1),
            COALESCE(cm.platform, '默认')
        ) as platform,
        cm.created_at as last_message_time,
        cm.content as last_message,
        cm.message_type,
        cm.image_path,
        COALESCE(cs.is_pinned, 0) as is_pinned,
        COALESCE(cs.is_muted, 0) as is_muted
    FROM chat_messages cm
    LEFT JOIN chat_settings cs ON cm.session_key = cs.session_key AND cs.agent_account = ?
    WHERE cm.id IN ($placeholders)
    ORDER BY FIELD(cm.id, " . implode(',', $message_ids) . ")";
    
    $params_detail = array_merge([$currentAgent], $message_ids);
    $types_detail = str_repeat("i", count($message_ids));
    $types_detail = "s" . $types_detail;
    
    $stmt_detail = $db->prepare($detail_sql);
    if (!$stmt_detail) {
        $error = $db->error;
        error_log("详情查询准备失败: " . $error);
        $response['error'] = '详情查询准备失败: ' . $error;
        echo json_encode($response);
        exit;
    }
    
    $stmt_detail->bind_param($types_detail, ...$params_detail);
    
    if (!$stmt_detail->execute()) {
        $error = $stmt_detail->error;
        error_log("详情查询执行失败: " . $error);
        $response['error'] = '详情查询执行失败: ' . $error;
        echo json_encode($response);
        exit;
    }
    
    $result_detail = $stmt_detail->get_result();
    
    // 生成HTML
    $html = '';
    $sessions = [];
    
    while ($row = $result_detail->fetch_assoc()) {
        $sessions[] = $row;
    }
    $stmt_detail->close();
    
    error_log("获取到 " . count($sessions) . " 个会话详情");
    
    // 处理置顶会话
    $pinned_sessions = [];
    $normal_sessions = [];
    
    foreach ($sessions as $session) {
        if ($session['is_pinned']) {
            $pinned_sessions[] = $session;
        } else {
            $normal_sessions[] = $session;
        }
    }
    
    // 合并：先置顶，后普通
    $sorted_sessions = array_merge($pinned_sessions, $normal_sessions);
    
    // ==================== 【关键】使用数据库查询在线状态 ====================
    error_log("开始获取在线状态...");
    $onlineStatuses = getOnlineStatusFromDatabase($customers, $currentAgent, $db);
    error_log("在线状态结果: " . json_encode($onlineStatuses));
    
    // 计算在线人数
    $response['online_count'] = count(array_filter($onlineStatuses));
    error_log("在线人数: " . $response['online_count']);
    
    foreach ($sorted_sessions as $row) {
        $customerName = $row['customer_name'];
        $isOnline = isset($onlineStatuses[$customerName]) && $onlineStatuses[$customerName] === true;
        $html .= buildSessionItem($row, $isOnline);
    }
    
    $response['success'] = true;
    $response['html'] = $html;
    
    $db->close();
    
} catch (Exception $e) {
    error_log("服务器异常: " . $e->getMessage());
    $response['error'] = '服务器异常: ' . $e->getMessage();
}

// 记录结束时间
$end_time = microtime(true);
$execution_time = $end_time - $start_time;
$response['execution_time'] = round($execution_time, 3);

error_log("执行时间: " . $response['execution_time'] . "秒");
error_log("========== 结束聊天列表查询 ==========\n");

echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;

function buildSessionItem($row, $isOnline) {
    $customerName = htmlspecialchars($row['customer_name'] ?? '', ENT_QUOTES, 'UTF-8');
    $sessionKey = htmlspecialchars($row['session_key'] ?? '', ENT_QUOTES, 'UTF-8');
    $lastMessage = htmlspecialchars($row['last_message'] ?? '点击开始对话', ENT_QUOTES, 'UTF-8');
    $lastTime = !empty($row['last_message_time']) ? date('m-d H:i', strtotime($row['last_message_time'])) : '刚刚';
    $platform = htmlspecialchars($row['platform'] ?? '默认', ENT_QUOTES, 'UTF-8');
    $isPinned = isset($row['is_pinned']) ? (bool)$row['is_pinned'] : false;
    $isMuted = isset($row['is_muted']) ? (bool)$row['is_muted'] : false;
    $clientIp = htmlspecialchars($row['client_ip'] ?? '', ENT_QUOTES, 'UTF-8');
    
    // 处理图片消息 - 兼容多种情况
    $messageType = $row['message_type'] ?? '';
    $imagePath = $row['image_path'] ?? '';
    $content = $row['last_message'] ?? '';
    
    // 检测图片消息的多种情况：
    // 1. message_type 是 image 且有 image_path
    // 2. content 是 [图片] 且有 image_path
    // 3. 只有 image_path（某些旧数据可能缺少其他字段）
    $isImageMessage = ($messageType === 'image' && !empty($imagePath)) || 
                      ($content === '[图片]' && !empty($imagePath)) || 
                      (!empty($imagePath) && strlen($imagePath) > 3);
    
    // 检查是否为群聊
    $isGroupChat = strpos($platform, '群聊') !== false;
    
    // 获取头像
    $avatarMap = [
        '闲鱼' => '/assets/img/xianyulist.jpg',
        '闲鱼代练' => '/assets/img/xy-logo.png',
        '闲鱼话费版本' => '/assets/img/xy-logo.png',
        '转转' => '/assets/img/zz-kf.png',
        '腾讯客服' => '/assets/img/wechat.jpg',
        '盼之' => '/assets/img/panzhi.png',
        '盼之群聊' => '/assets/img/panzhi.png',
        '抖音' => '/assets/img/douyin.png',
        '大麦' => '/assets/img/dm-kf.png',
        '螃蟹' => '/assets/img/pangxielist.png',
        '螃蟹群聊' => '/assets/img/pangxielist.png',
        '白情' => '/assets/img/bq-kf.jpg',
        '白情群聊' => '/assets/img/bq-kf.jpg',
        '京东' => '/assets/img/jd.png',
        '得物' => '/assets/img/dw-kf.png',
        '钉钉' => '/assets/img/dingding.png',
        '拼多多' => '/assets/img/pdd.png',
        '自定义' => '/assets/img/zidingyikefu.png',
        '自定义聊天' => '/assets/img/wangluokefu.png',
        '交易猫' => '/assets/img/jym-kf.png',
        '千岛' => '/assets/img/qd-kefu.png',
        '银联' => '/assets/img/yinlian.ico',
        '氪金兽' => '/assets/img/kjs-kefu.jpg',
        '默认' => '/assets/img/normal.png'
    ];
    
    // 确保平台存在映射关系
    $platform = isset($avatarMap[$platform]) ? $platform : '默认';
    $avatarUrl = $avatarMap[$platform] ?? '/assets/img/normal.png';
    
      // 生成新样式 HTML（添加左滑删除包装层）
    $html = '<div class="session-item-wrapper" data-session-key="' . $sessionKey . '" data-customer="' . $customerName . '">';
    $html .= '<a href="javascript:void(0)" onclick="openChatModal(\'' . urlencode($sessionKey) . '\', \'' . urlencode($customerName) . '\')" class="session-item' . ($isPinned ? ' sticky' : '') . '" data-session-key="' . $sessionKey . '" data-customer="' . $customerName . '" data-online="' . ($isOnline ? 'true' : 'false') . '" data-pinned="' . ($isPinned ? 'true' : 'false') . '" data-muted="' . ($isMuted ? 'true' : 'false') . '">';
    
    
    // 头像区域
    $html .= '<div class="avatar">';
    $html .= '<img src="' . $avatarUrl . '" alt="' . $customerName . '" onerror="this.src=\'/assets/img/normal.png\'">';
    
    // 如果是群聊，添加角标
    if ($isGroupChat) {
        $html .= '<div class="group-chat-badge"><i class="bi bi-people-fill"></i></div>';
    }
    $html .= '</div>';
    
    // 内容区域
    $html .= '<div class="session-content">';
    $html .= '<div class="session-id">';

    $html .= '<span class="customer-name">' . $customerName . '</span>';
    
        
    // 如果是群聊，添加标签
    if ($isGroupChat) {
        $html .= '<span class="tag tag-group">群聊</span>';
    }
    
    $html .= '<span class="status-text ' . ($isOnline ? 'status-online' : 'status-offline') . '"></span>';

    $html .= '</div>';
    $html .= '<div class="last-message" title="' . ($isImageMessage ? '图片消息' : $lastMessage) . '">';
    if ($isImageMessage || (!empty($imagePath) && strlen($imagePath) > 3)) {
        $imageUrl = '/uploads/' . htmlspecialchars($imagePath, ENT_QUOTES, 'UTF-8');
        $html .= '<img src="' . $imageUrl . '" alt="图片" style="width: 32px; height: 32px; border-radius: 4px; object-fit: cover;" onerror="this.style.display=\'none\'; this.parentNode.innerHTML=\'[图片]\'">';
    } else {
        $displayMessage = mb_strlen($lastMessage, 'UTF-8') > 50 ? mb_substr($lastMessage, 0, 50, 'UTF-8') . '...' : $lastMessage;
        $html .= $displayMessage ?: '[图片]';
    }
    $html .= '</div>';
    $html .= '</div>';
    
    // 时间
    $html .= '<div class="session-time">' . $lastTime . '</div>';
    $html .= '</a>';
    
      // 删除按钮
    $html .= '<div class="session-delete-btn" onclick="event.stopPropagation(); handleDeleteClick(\'' . $sessionKey . '\', \'' . str_replace("'", "\\'", $customerName) . '\')">';
    $html .= '<i class="bi bi-trash-fill"></i>';
    $html .= '</div>';
    $html .= '</div>';
    
    return $html;
}

// ==================== 【修复】数据库在线状态查询函数 ====================

function getOnlineStatusFromDatabase($customers, $agent, $db) {
    $onlineStatuses = [];
    
    if (empty($customers)) {
        return $onlineStatuses;
    }
    
    try {
        // 创建占位符
        $placeholders = implode(',', array_fill(0, count($customers), '?'));
        
        // 【修复】使用更准确的在线判断：最后心跳在5分钟内即为在线
        $sql = "SELECT username, is_online, last_heartbeat 
                FROM user_online_status 
                WHERE username IN ($placeholders) 
                  AND user_type = 'customer'";
        
        error_log("数据库查询SQL: " . $sql);
        
        $stmt = $db->prepare($sql);
        if (!$stmt) {
            error_log("❌ 数据库准备失败: " . $db->error);
            return $onlineStatuses;
        }
        
        $types = str_repeat("s", count($customers));
        $stmt->bind_param($types, ...$customers);
        
        if (!$stmt->execute()) {
            error_log("❌ 数据库执行失败: " . $stmt->error);
            $stmt->close();
            return $onlineStatuses;
        }
        
        $result = $stmt->get_result();
        
        // 初始化所有客户为离线
        foreach ($customers as $customer) {
            $onlineStatuses[$customer] = false;
        }
        
        $now = time();
        $onlineCount = 0;
        
        // 设置在线状态：最后心跳在5分钟内即为在线
        while ($row = $result->fetch_assoc()) {
            $username = $row['username'];
            $lastHeartbeat = strtotime($row['last_heartbeat']);
            $timeDiff = $now - $lastHeartbeat;
            
            // 最后心跳在5分钟内即为在线
            if ($timeDiff <= 30) { // 300秒 = 5分钟
                $onlineStatuses[$username] = true;
                $onlineCount++;
                error_log("✅ 用户 {$username}: 最后心跳{$timeDiff}秒前，标记为在线");
            } else {
                $onlineStatuses[$username] = false;
                error_log("❌ 用户 {$username}: 最后心跳{$timeDiff}秒前，标记为离线");
            }
        }
        
        $stmt->close();
        
        error_log("📊 数据库查询结果: 在线{$onlineCount}人，共" . count($customers) . "人");
        
    } catch (Exception $e) {
        error_log("❌ 从数据库获取在线状态失败: " . $e->getMessage());
    }
    
    return $onlineStatuses;
}
?>