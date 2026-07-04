<?php
if (!defined('FROM_ROUTER')) {
    http_response_code(403);
    exit('喜乐科技@x60898');
}
// 引入数据库配置文件
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/dbconfig.php';
checkLogin();

// 定义常量
define('DEFAULT_AVATAR_PATH', '/assets/img/pz-yh.png');

// 获取当前域名和协议
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$base_url = $protocol . $_SERVER['HTTP_HOST'];

// 获取数据库连接
$conn = getDB();
if (!$conn) {
    die("数据库连接失败");
}

// 获取当前用户ID从session中
$user_id = $_SESSION['user_id'];

// 处理表单提交（添加/编辑群聊）
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';
    $id = intval($_POST['id'] ?? 0);
    
    if ($action === 'save_chatroom') {
        $XEyouxige_trader_name = $conn->real_escape_string($_POST['XEyouxige_trader_name']);
        $XEyouxige_group_code = $conn->real_escape_string($_POST['XEyouxige_group_code'] ?? '');
        $XEyouxige_welcome_message = $conn->real_escape_string($_POST['XEyouxige_welcome_message'] ?? '');
        $XEyouxige_page_status = $conn->real_escape_string($_POST['XEyouxige_page_status']);
        
        // 检查商品编号是否重复（编辑时排除自己）
        $check_sql = "SELECT XEyouxige_id FROM XEyouxige 
                     WHERE XEyouxige_user_id = ? 
                     AND XEyouxige_group_code = ?";
        
        if ($id > 0) {
            $check_sql .= " AND XEyouxige_id != ?";
        }
        
        $check_stmt = $conn->prepare($check_sql);
        
        if ($check_stmt === false) {
            $_SESSION['error_message'] = "SQL语句准备失败: " . $conn->error;
        } else {
            if ($id > 0) {
                $check_stmt->bind_param("isi", $user_id, $XEyouxige_group_code, $id);
            } else {
                $check_stmt->bind_param("is", $user_id, $XEyouxige_group_code);
            }
            
            $check_stmt->execute();
            $check_stmt->store_result();
            
            if ($check_stmt->num_rows > 0) {
                $_SESSION['error_message'] = "商品编号 '{$XEyouxige_group_code}' 已存在，请使用其他编号！";
                $check_stmt->close();
                
                // 使用JavaScript重定向
                echo '<script>window.location.href = window.location.href;</script>';
                exit;
            }
            
            $check_stmt->close();
        }
        
        // 初始化头像路径变量
        $XEyouxige_seller_avatar = $_POST['old_seller_avatar'] ?? '';
        
        // 处理卖家头像上传
        $defaultAvatarUsed = isset($_POST['default_avatar_used']) ? intval($_POST['default_avatar_used']) : 0;
        $sellerAvatarUrl = isset($_POST['seller_avatar_url']) ? $conn->real_escape_string($_POST['seller_avatar_url']) : '';
        
        // 如果用户选择了使用默认头像
        if ($defaultAvatarUsed == 1 && !empty($sellerAvatarUrl)) {
            $XEyouxige_seller_avatar = $sellerAvatarUrl;
        } 
        // 如果有上传新的头像文件
        else if (isset($_FILES['XEyouxige_seller_avatar']) && $_FILES['XEyouxige_seller_avatar']['error'] == 0) {
            $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/chatroom/';
            
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $fileExt = pathinfo($_FILES['XEyouxige_seller_avatar']['name'], PATHINFO_EXTENSION);
            $fileName = 'avatar_' . time() . '_' . uniqid() . '.' . $fileExt;
            $uploadFile = $uploadDir . $fileName;
            
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $fileType = $_FILES['XEyouxige_seller_avatar']['type'];
            
            if (in_array($fileType, $allowedTypes)) {
                if (move_uploaded_file($_FILES['XEyouxige_seller_avatar']['tmp_name'], $uploadFile)) {
                    $XEyouxige_seller_avatar = '/uploads/chatroom/' . $fileName;
                    
                    // 如果有旧的头像，可以删除旧文件
                    if (!empty($_POST['old_seller_avatar']) && 
                        $_POST['old_seller_avatar'] != DEFAULT_AVATAR_PATH && 
                        file_exists($_SERVER['DOCUMENT_ROOT'] . $_POST['old_seller_avatar'])) {
                        unlink($_SERVER['DOCUMENT_ROOT'] . $_POST['old_seller_avatar']);
                    }
                }
            }
        }
        // 如果是编辑模式但没有上传新头像也没有选择默认头像，则保持原头像
        else if ($id > 0) {
            $XEyouxige_seller_avatar = $_POST['old_seller_avatar'] ?? '';
        }
        // 如果是添加模式但没有头像，可以设置为空或默认值
        else {
            $XEyouxige_seller_avatar = '';
        }
        
        // 转义头像路径
        $XEyouxige_seller_avatar = $conn->real_escape_string($XEyouxige_seller_avatar);
        
        if ($id > 0) {
            // 编辑现有群聊 - UPDATE语句
            $sql = "UPDATE XEyouxige SET 
                    XEyouxige_trader_name = ?, 
                    XEyouxige_group_code = ?,
                    XEyouxige_welcome_message = ?,
                    XEyouxige_page_status = ?,
                    XEyouxige_seller_avatar = ?,
                    XEyouxige_updated_at = CURRENT_TIMESTAMP
                    WHERE XEyouxige_id = ? AND XEyouxige_user_id = ?";
            
            $stmt = $conn->prepare($sql);
            
            if ($stmt === false) {
                $_SESSION['error_message'] = "SQL语句准备失败: " . $conn->error;
            } else {
                $stmt->bind_param("sssssii", 
                    $XEyouxige_trader_name, 
                    $XEyouxige_group_code, 
                    $XEyouxige_welcome_message, 
                    $XEyouxige_page_status, 
                    $XEyouxige_seller_avatar, 
                    $id, 
                    $user_id
                );
                
                if ($stmt->execute()) {
                    $_SESSION['success_message'] = "更新成功！";
                } else {
                    $_SESSION['error_message'] = "更新失败: " . $stmt->error;
                }
                
                $stmt->close();
            }
        } else {
            // 添加新群聊
            $XEyouxige_page_code = substr(md5(uniqid(rand(), true)), 0, 10);
            
            $sql = "INSERT INTO XEyouxige (XEyouxige_user_id, XEyouxige_trader_name, 
                    XEyouxige_group_code, XEyouxige_welcome_message, XEyouxige_page_status, 
                    XEyouxige_page_code, XEyouxige_seller_avatar) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            
            if ($stmt === false) {
                $_SESSION['error_message'] = "SQL语句准备失败: " . $conn->error;
            } else {
                $stmt->bind_param("issssss", 
                    $user_id, 
                    $XEyouxige_trader_name, 
                    $XEyouxige_group_code, 
                    $XEyouxige_welcome_message, 
                    $XEyouxige_page_status, 
                    $XEyouxige_page_code, 
                    $XEyouxige_seller_avatar
                );
                
                if ($stmt->execute()) {
                    $_SESSION['success_message'] = "添加成功！";
                } else {
                    $_SESSION['error_message'] = "添加失败: " . $stmt->error;
                }
                
                $stmt->close();
            }
        }
        
        // 使用JavaScript重定向
        echo '<script>window.location.href = window.location.href;</script>';
        exit;
        
    } elseif ($action === 'delete_chatroom') {
        // 删除群聊
        $id = intval($_POST['id']);
        
        if ($id <= 0) {
            $_SESSION['error_message'] = "无效的商品ID";
        } else {
            // 先查询要删除的商品信息，以便删除相关文件
            $query_sql = "SELECT XEyouxige_seller_avatar FROM XEyouxige WHERE XEyouxige_id = ? AND XEyouxige_user_id = ?";
            $query_stmt = $conn->prepare($query_sql);
            if ($query_stmt) {
                $query_stmt->bind_param("ii", $id, $user_id);
                $query_stmt->execute();
                $query_result = $query_stmt->get_result();
                
                if ($row = $query_result->fetch_assoc()) {
                    // 如果存在头像文件，删除它
                    if (!empty($row['XEyouxige_seller_avatar']) && 
                        $row['XEyouxige_seller_avatar'] != DEFAULT_AVATAR_PATH && 
                        file_exists($_SERVER['DOCUMENT_ROOT'] . $row['XEyouxige_seller_avatar'])) {
                        unlink($_SERVER['DOCUMENT_ROOT'] . $row['XEyouxige_seller_avatar']);
                    }
                }
                
                $query_stmt->close();
            }
            
            $sql = "DELETE FROM XEyouxige WHERE XEyouxige_id = ? AND XEyouxige_user_id = ?";
            $stmt = $conn->prepare($sql);
            
            if ($stmt === false) {
                $_SESSION['error_message'] = "SQL语句准备失败: " . $conn->error;
            } else {
                $stmt->bind_param("ii", $id, $user_id);
                
                if ($stmt->execute()) {
                    $_SESSION['success_message'] = "删除成功！";
                } else {
                    $_SESSION['error_message'] = "删除失败: " . $stmt->error;
                }
                
                $stmt->close();
            }
        }
        
        // 使用JavaScript重定向
        echo '<script>window.location.href = window.location.href;</script>';
        exit;
    }
}

// 从session中获取消息
$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
// 清除session中的消息
unset($_SESSION['success_message'], $_SESSION['error_message']);

// 查询当前用户的所有群聊
$sql = "SELECT * FROM XEyouxige WHERE XEyouxige_user_id = ? ORDER BY XEyouxige_created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$chatrooms = [];

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $chatrooms[] = $row;
    }
}

$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <!-- PWA meta tags -->
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="客服聊天">
<link rel="apple-touch-icon" href="/assets/img/icon-192.png">
<link rel="manifest" href="/manifest.php">
<meta name="theme-color" content="#f7f8fa">
    <title>官方客服</title>
	<link rel="icon" type="image/x-icon" href="/favicon.png">
    <link rel="stylesheet" href="/assets/bootstrap-icons.css">
    <link rel="stylesheet" href="/assets/chatroom.css">
    <link rel="stylesheet" href="/assets/chatroom_add.css">
    <link rel="stylesheet" href="/assets/top_bar.css">
    <!-- 引入html2canvas库用于生成图片 -->
    <script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
    <!-- 引入QRCode.js库用于生成二维码 -->
    <script src="/assets/qrcode.min.js"></script>
    <style>
        /* 防红配置样式 */
        .anti-red-status {
            padding: 6px 12px;
            background: #f8f9fa;
            border-radius: 20px;
            font-size: 14px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .anti-red-status:hover {
            background: #e9ecef;
        }

        /* 防红模态框样式 */
        .anti-red-modal-content {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            border-radius: 20px 20px 0 0;
            box-shadow: 0 -5px 20px rgba(0,0,0,0.2);
            z-index: 1002;
            animation: slideUp 0.3s ease-out;
            max-height: 80vh;
            overflow-y: auto;
            width:100%;
        }
        .anti-red-body {
            padding: 20px;
            min-height: 200px;
            display: block;
            align-items: center;
            justify-content: center;
            color: #868e96;
        }
        .anti-red-header {
            padding: 20px 20px 10px;
            border-bottom: 1px solid #eee;
            position: relative;
        }

        .anti-red-title {
            margin: 0;
            font-size: 18px;
            text-align: center;
        }

        .anti-red-info {
            justify-content: space-around;
            margin-bottom: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
        }

        .anti-red-buy {
            padding: 15px;
            background: #007AFF;
            color: white;
            border-radius: 10px;
            text-align: center;
            margin-bottom: 20px;
            cursor: pointer;
            transition: transform 0.3s;
        }

        .anti-red-buy:hover {
            transform: translateY(-2px);
        }

        .anti-red-setting {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        /* 开关样式 */
        .switch {
            width: 50px;
            height: 28px;
            background: #ddd;
            border-radius: 14px;
            position: relative;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .switch.on {
            background: #28a745;
        }

        .switch-handle {
            position: absolute;
            width: 24px;
            height: 24px;
            background: white;
            border-radius: 50%;
            top: 2px;
            left: 2px;
            transition: transform 0.3s;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }

        .switch.on .switch-handle {
            transform: translateX(22px);
        }

        /* 防红链接列表 */
        .user-links-container {
            max-height: 300px;
            overflow-y: auto;
        }

        .anti-red-link {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 15px;
            border: 1px solid #eee;
            border-radius: 8px;
            margin-bottom: 10px;
            transition: all 0.3s;
        }

        .anti-red-link:hover {
            border-color: #667eea;
            box-shadow: 0 2px 8px rgba(102, 126, 234, 0.1);
        }

        .link-info {
            flex: 1;
        }

        .link-domain {
            font-weight: 600;
            margin-bottom: 4px;
        }

        .link-date {
            font-size: 12px;
            color: #888;
        }

        .apply-link {
            padding: 6px 12px;
            background: #f2f2f7;
            color: white;
            border-radius: 6px;
            font-size: 13px;
            cursor: pointer;
            transition: background-color 0.3s;
            min-width: 60px;
            text-align: center;
        }

        .apply-link.applied {
            background: #007AFF;
        }

        .apply-link:hover {
            opacity: 0.9;
        }

        .no-links {
            text-align: center;
            padding: 20px;
            color: #888;
            font-size: 14px;
        }

        /* 动画 */
        @keyframes slideUp {
            from {
                transform: translateY(100%);
            }
            to {
                transform: translateY(0);
            }
        }

        /* 模态框样式 - 抽屉式从底部滑出 */
        .modal-overlay, .confirm-overlay, .share-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            display: flex;
            align-items: flex-end;
            justify-content: center;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .modal-overlay.show, .confirm-overlay.show, .share-overlay.show {
            opacity: 1;
            visibility: visible;
        }

        .modal-content {
            background: white;
            border-radius: 20px 20px 0 0;
            width: 100%;
            max-width: 600px;
            max-height: 85vh;
            overflow-y: auto;
            transform: translateY(100%);
            transition: transform 0.3s ease-out;
            margin-bottom: 0;
            box-shadow: 0 -5px 20px rgba(0,0,0,0.2);
        }

        .modal-overlay.show .modal-content {
            transform: translateY(0);
        }

        .confirm-content {
            background: white;
            border-radius: 20px 20px 0 0;
            width: 100%;
            max-width: 400px;
            padding: 20px;
            transform: translateY(100%);
            transition: transform 0.3s ease-out;
            box-shadow: 0 -5px 20px rgba(0,0,0,0.2);
        }

        .confirm-overlay.show .confirm-content {
    transform: translateY(0);
}

/* 分享模态框特殊样式 */
.confirm-overlay#shareModal {
    align-items: flex-end;
}

.confirm-overlay#shareModal .share-modal-content {
    background: white;
    border-radius: 20px 20px 0 0;
    width: 100%;
    max-width: 500px;
    transform: translateY(100%);
    transition: transform 0.3s ease-out;
    box-shadow: 0 -5px 20px rgba(0,0,0,0.2);
    padding: 20px;
}

.confirm-overlay#shareModal.show .share-modal-content {
    transform: translateY(0);
}

/* 分享图模态框特殊样式 */
.confirm-overlay#shareImageModal {
    align-items: flex-end;
}

.confirm-overlay#shareImageModal .share-image-modal {
    background: white;
    border-radius: 20px 20px 0 0;
    width: 100%;
    max-width: 500px;
    transform: translateY(100%);
    transition: transform 0.3s ease-out;
    box-shadow: 0 -5px 20px rgba(0,0,0,0.2);
    padding: 20px;
    max-height: 85vh;
    overflow-y: auto;
}

.confirm-overlay#shareImageModal.show .share-image-modal {
    transform: translateY(0);
}

.share-image-modal {
            background: white;
            border-radius: 20px 20px 0 0;
            width: 90%;
            max-width: 500px;
            transform: translateY(100%);
            transition: transform 0.3s ease-out;
            box-shadow: 0 -5px 20px rgba(0,0,0,0.2);
            padding: 20px;
            max-height: 85vh;
            overflow-y: auto;
        }

        /* 分享图样式 - 来自 input.php */
        .qrPoster{
            --ink:#0b1324;
            --muted:rgba(15,23,42,.62);
            --line:rgba(15,23,42,.10);
            --card:rgba(255,255,255,.92);
            --accent:#0a84ff;

            position:relative;
            width:min(414px,100%);
            margin:14px auto 10px;
            border-radius:38px;
            overflow:hidden;
            background: radial-gradient(120% 90% at 50% -12%, rgba(255,220,0,.18) 0%, rgba(255,220,0,0) 64%),
radial-gradient(110% 90% at 50% 112%, rgba(255,220,0,.08) 0%, rgba(255,220,0,0) 62%),
linear-gradient(180deg, rgba(255,255,255,.98), rgba(246,248,252,.94));
            border:1px solid rgba(15,23,42,.08);
            box-shadow:0 30px 92px rgba(2,6,23,.16);
        }

        .qrPoster:before{
            content:"";
            position:absolute; inset:0;
            border-radius:38px;
            padding:1px;
            background:none!important;
            -webkit-mask-composite:xor;
            mask-composite:exclude;
            pointer-events:none;
            opacity:.55;
        }

        .qrPoster:after{
            content:"";
            position:absolute; inset:0;
            border-radius:38px;
            background:none!important;
            background-size: 120px 120px, 140px 140px, 160px 160px, 180px 180px;
            opacity:.55;
            pointer-events:none;
            mix-blend-mode:overlay;
        }

        .qrPosterInner{
            position:relative;
            z-index:1;
            margin:14px;
            border-radius:30px;
            background:rgba(255,255,255,.86);
            border:1px solid rgba(15,23,42,.08);
            box-shadow:0 18px 60px rgba(2,6,23,.12);
            overflow:hidden;
        }

        .qrPosterInner:before{ display:none; content:none; }

        .qrPosterTop{
            display:flex;
            flex-direction:column;
            align-items:center;
            text-align:center;
            padding:26px 16px 10px;
            gap:10px;
        }

        .qrPosterAvatar{
            width:64px;
            height:64px;
            border-radius:22px;
            object-fit:cover;
            background:#fff;
            border:7px solid rgba(255,255,255,.92);
            box-shadow:0 18px 46px rgba(2,6,23,.18);
            outline:1px solid rgba(15,23,42,.10);
        }

        .qrPosterName{
            font-size:20px;
            line-height:1.18;
            font-weight:980;
            letter-spacing:.25px;
            color:var(--ink);
            max-width:340px;
            word-break:break-word;
        }

        .qrPosterBadges{
            display:flex;
            flex-wrap:wrap;
            justify-content:center;
            gap:8px;
            margin-top:2px;
        }

        .qrBadge, .qrPosterTag{
            display:inline-flex;
            align-items:center;
            height:28px;
            padding:0 12px;
            border-radius:10px;
            font-size:12px;
            font-weight:900;
            letter-spacing:.18px;
            border:1px solid rgba(15,23,42,.10);
            background:rgba(255,255,255,.78);
            color:rgba(15,23,42,.70);
        }

        .qrPosterTag {
            border-color: rgba(255,220,0,.18);
background: linear-gradient(180deg, rgba(255,220,0,.14), rgba(255,220,0,.06));
color: rgba(255,220,0,.98);
        }

        .qrBadge.hours{ background:rgba(15,23,42,.05); }

        .qrDivider{ display:none; }

        .qrPosterMid{
            display:flex;
            flex-direction:column;
            align-items:center;
            justify-content:center;
            padding:14px 18px 8px;
        }

        .qrBox{
            position:relative;
            width:140px;
            height:140px;
            border-radius:12px;
            padding:10px;
            background:#ffffff;
            border:1px solid rgba(15,23,42,.10);
            box-shadow:0 22px 70px rgba(2,6,23,.18);
        }

        #shareImageQrCodeBox{
            width:100%;
            height:100%;
            overflow:hidden;
            background:#fff;
        }
        #shareImageQrCodeBox img, #shareImageQrCodeBox canvas{ width:100% !important; height:100% !important; display:block; }

        .qrPosterScan{
            margin-top:12px;
            font-size:12px;
            font-weight:900;
            color:rgba(15,23,42,.56);
            text-align:center;
            letter-spacing:.18px;
        }

        .qrPosterHint{
            margin:12px auto 0;
            width:fit-content;
            max-width:calc(100% - 36px);
            padding:10px 14px;
            border-radius:999px;
            border:1px solid rgba(15,23,42,.10);
            background:linear-gradient(180deg, rgba(255,255,255,.82), rgba(255,255,255,.62));
            box-shadow:0 14px 38px rgba(2,6,23,.12);
            color:rgba(15,23,42,.70);
            font-size:12px;
            font-weight:900;
            text-align:center;
            letter-spacing:.2px;
        }

        .qrPosterFoot{
            padding:12px 18px 16px;
            text-align:center;
            font-size:11.5px;
            font-weight:800;
            color:rgba(15,23,42,.46);
        }

        @media (max-width:420px){
            .qrPoster{ border-radius:34px; }
            .qrPosterInner{ margin:12px; border-radius:28px; }
            .qrPosterAvatar{ width:60px; height:60px; border-radius:20px; }
            .qrPosterName{ max-width:300px; }
            .qrBox{ width:136px; height:136px; border-radius:26px; }
            #shareImageQrCodeBox{ border-radius:18px; }
            .qrAvatar{ width:42px; height:42px; border-radius:15px; border-width:8px; }
        }

        .modal-header {
            padding: 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            background: white;
            z-index: 10;
        }

        .modal-title {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 28px;
            color: #666;
            cursor: pointer;
            padding: 0;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-body {
            padding: 20px;
        }

        .modal-footer {
            padding: 20px;
            border-top: 1px solid #eee;
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            position: sticky;
            bottom: 0;
            background: white;
        }

        /* 确保会话 ID 显示正常 */
        .share-param-info {
            margin-top: 10px;
            font-size: 12px;
            color: #666;
            display: block;
            background-color: #f8f9fa;
            border-radius: 4px;
        }

        #sessionIdDisplay {
            font-weight: bold;
            color: #0066ff;
            word-break: break-all;
        }
        .notification.info {
            background-color: #0d6efd;
        }
        
        .empty-state {
            background: transparent;
        }
        
        .welcome-message {
            font-style: italic;
            color: #666;
            font-size: 12px;
            margin-top: 5px;
        }
        
        /* 商品编号重复提示样式 */
        .duplicate-error {
            color: #dc3545;
            font-size: 12px;
            margin-top: 5px;
            display: none;
        }
        
        .duplicate-error.show {
            display: block;
        }
        
        .form-input.duplicate {
            border-color: #dc3545;
        }

        /* 新通知样式 - 从顶部进入 */
        .new-user-toast {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: #4caf50;
            border-radius: 8px;
            padding: 12px 16px;
            display: flex;
            align-items: center;
            box-shadow: 0 4px 12px rgba(0,0,0,.15);
            cursor: pointer;
            will-change: transform,opacity;
            backface-visibility: hidden;
            -webkit-backface-visibility: hidden;
            max-width: calc(100% - 20px);
            z-index: 9999;
            min-width: 400px;
            max-width: 400px;
        }

        .toast-icon {
            width: 24px;
            height: 24px;
            background: #fff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
            flex-shrink: 0;
            font-size: 16px;
            color: #4caf50;
            font-weight: bold;
        }

        .toast-text {
            flex: 1;
            color: #fff;
            font-size: 15px;
            font-weight: 500;
            line-height: 1.4;
            word-break: break-word;
        }

        .toast-close {
            width: 24px;
            height: 24px;
            background: transparent;
            border: none;
            color: #fff;
            font-size: 20px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: .8;
            flex-shrink: 0;
            transition: opacity .15s ease,transform .15s ease;
            padding: 0;
            margin-left: 8px;
        }

        .toast-close:hover {
            opacity: 1;
        }

        .toast-close:active {
            transform: scale(.85);
        }

        /* 通知进入和离开动画 - 从顶部进入 */
        .notification-enter-active {
            animation: notification-slide-in 0.3s ease-out;
        }

        .notification-leave-active {
            animation: notification-slide-out 0.3s ease-in forwards;
        }

        @keyframes notification-slide-in {
            0% {
                transform: translateX(-50%) translateY(-100px);
                opacity: 0;
            }
            100% {
                transform: translateX(-50%) translateY(0);
                opacity: 1;
            }
        }

        @keyframes notification-slide-out {
            0% {
                transform: translateX(-50%) translateY(0);
                opacity: 1;
            }
            100% {
                transform: translateX(-50%) translateY(-100px);
                opacity: 0;
            }
        }

        /* 不同颜色的通知 */
        .new-user-toast.info {
            background: #2196f3;
        }

        .new-user-toast.info .toast-icon {
            color: #2196f3;
        }

        .new-user-toast.warning {
            background: #ff9800;
        }

        .new-user-toast.warning .toast-icon {
            color: #ff9800;
        }

        .new-user-toast.error {
            background: #f44336;
        }

        .new-user-toast.error .toast-icon {
            color: #f44336;
        }
    </style>
</head>
<body>
	<!-- 通知容器 -->
	<div id="notification-container"></div>
	<!-- 保存图片加载提示 -->
	<div class="saving-overlay" id="savingOverlay">
	    <div class="saving-spinner"></div>
	</div>
	<div class="top-header">
		<a href="javascript:void(0)" onclick="window.parent.postMessage('closeModal', '*')" style="display: inline-flex; align-items: center; text-decoration: none; color: inherit;">
			<svg t="1768667202128" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="4699" width="18" height="18">
				<path
					d="M285.8112 565.76a56.4864 56.4864 0 0 0 39.04-16.3712l452.7744-453.76A56.5248 56.5248 0 0 0 778.24 16.64a54.8992 54.8992 0 0 0-78.08-0.5632L247.3344 469.76a56.5248 56.5248 0 0 0-0.5504 79.0144 50.048 50.048 0 0 0 39.0272 16.9344zM733.568 1024a56.1664 56.1664 0 0 0 39.6032-95.3856l-448.32-458.24a54.912 54.912 0 0 0-78.08-0.5632 56.5248 56.5248 0 0 0-0.5632 79.0144l448.32 458.24A53.76 53.76 0 0 0 733.568 1024z m0 0"
					fill="#333333" p-id="4700"></path>
			</svg>
		</a>
	
		<div style="border: 14px solid transparent;">白情群聊</div>
		<!-- 添加防红状态显示 -->
    <div class="anti-red-status" id="anti-red-status" style="margin-right: 10px;">
        防红配置状态: <span id="current-anti-red-domain" style="color: #666;">未配置</span>
    </div>
		<div class="action-buttons">
			<button class="add-btn" onclick="openModal('add')"> 创建群聊 </button>
		</div>
	</div>
	<!-- 主容器 -->
	<div class="container">
	<?php if (count($chatrooms) > 0): ?>
<script>
    // 存储所有群聊数据用于编辑时填充表单
    var chatroomsData = <?php 
        $data = [];
        foreach ($chatrooms as $chatroom) {
            $data[$chatroom['XEyouxige_id']] = [
                'trader_name' => $chatroom['XEyouxige_trader_name'],
                'group_code' => $chatroom['XEyouxige_group_code'] ?? '',
                'welcome_message' => $chatroom['XEyouxige_welcome_message'] ?? '',
                'page_status' => $chatroom['XEyouxige_page_status'],
                'seller_avatar' => $chatroom['XEyouxige_seller_avatar'] ?? '',
                'page_code' => $chatroom['XEyouxige_page_code']
            ];
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT);
    ?>;
</script>
<div class="products-grid"> 
    <?php foreach ($chatrooms as $index => $chatroom): 
        $chatroom_url = $base_url . "/Youxige?XEchatroom=" . $chatroom['XEyouxige_page_code'];
    ?>
    <div class="product-card" id="chatroom-card-<?php echo $chatroom['XEyouxige_id']; ?>">
        <!-- 卖家头像 -->
        <div class="product-image"> 
            <?php if (!empty($chatroom['XEyouxige_seller_avatar'])): ?> 
            <img src="<?php echo $chatroom['XEyouxige_seller_avatar']; ?>" alt="<?php echo $chatroom['XEyouxige_trader_name']; ?>"> 
            <?php else: ?> 
            <div class="placeholder">
                <i class="bi bi-person-circle"></i>
            </div> 
            <?php endif; ?> 
        </div>
        <!-- 群聊信息 -->
        <div class="product-info">
            <div class="product-details-grid">
               
                <div class="grid-cell">
                    <span class="grid-label">交易员名称:</span>
                    <span class="grid-value product-name"><?php echo $chatroom['XEyouxige_trader_name']; ?></span>
                </div>
                <!-- ... 其他字段 ... -->
						<div class="grid-cell">
							<span class="grid-label">群聊编号:</span>
							<span class="grid-value"><?php echo $chatroom['XEyouxige_group_code']; ?></span>
						</div>

						<div class="grid-cell">
							<span class="grid-label">群聊状态:</span>
							<span class="grid-value status <?php echo $chatroom['XEyouxige_page_status'] === 'active' ? 'status-active' : 'status-inactive'; ?>"> <?php echo $chatroom['XEyouxige_page_status'] === 'active' ? '开启' : '关闭'; ?> </span>
						</div>
						<div class="grid-cell">
							<span class="grid-label">创建时间:</span>
							<span class="grid-value"><?php echo date('m-d H:i', strtotime($chatroom['XEyouxige_created_at'])); ?></span>
						</div>
					</div>
				</div>
				<!-- 操作按钮 -->
<div class="card-actions">
   <button class="card-btn btn-edit" data-id="<?php echo htmlspecialchars($chatroom['XEyouxige_id']); ?>">
    <i class="bi bi-pencil-square"></i> 编辑 
</button>
<button class="card-btn btn-delete" data-id="<?php echo htmlspecialchars($chatroom['XEyouxige_id']); ?>">
    <i class="bi bi-trash"></i> 删除 
</button>
    <button class="card-btn btn-share" data-id="<?php echo $chatroom['XEyouxige_id']; ?>">
        <i class="bi bi-share"></i> 分享 
    </button>
    <button class="card-btn btn-share-image" data-id="<?php echo $chatroom['XEyouxige_id']; ?>">
        <i class="bi bi-image"></i> 分享图
    </button>
</div>
			</div> <?php endforeach; ?> </div> <?php else: ?> <!-- 空状态 -->
		<div class="empty-state">
			<div class="empty-icon">
				<i class="bi bi-terminal-x"></i>
			</div>
			<h3 class="empty-title">暂无群聊</h3>
			<p class="empty-text">您还没有创建过白情群聊</p>
			
		</div> <?php endif; ?>
	</div>
	<!-- 添加/编辑群聊模态框 -->
	<div class="modal-overlay" id="productModal">
		<div class="modal-content">
			<form method="POST" action="" id="productForm" enctype="multipart/form-data">
				<div class="modal-header">
					<h5 class="modal-title" id="modalTitle">群聊设置</h5>
					<button type="button" class="modal-close" onclick="closeModal()">×</button>
				</div>
				<div class="modal-body">
					<input type="hidden" name="action" value="save_chatroom">
					<input type="hidden" name="id" id="productId" value="0">
					<input type="hidden" name="old_seller_avatar" id="old_seller_avatar" value="">
					<!-- 在表单的隐藏字段区域添加 -->
					<input type="hidden" name="default_avatar_used" id="defaultAvatarUsed" value="0">
					<input type="hidden" name="seller_avatar_url" id="sellerAvatarUrl" value="">
					<!-- 交易员名称 -->
					<div class="form-group">
						<label for="XEyouxige_trader_name" class="form-label required">交易员名称</label>
						<input type="text" class="form-input" id="XEyouxige_trader_name" name="XEyouxige_trader_name" required placeholder="例如：盼之客服小张">
					</div>
					<!-- 群聊编号 -->
					<div class="form-group">
						<label for="XEyouxige_group_code" class="form-label required">群聊编号</label>
						<div class="input-with-button">
							<input type="text" class="form-input" id="XEyouxige_group_code" name="XEyouxige_group_code" placeholder="例如：F1JSM1">
							<button type="button" class="random-code-btn" id="generateRandomCode">
								<i class="bi bi-shuffle"></i> 随机编号 </button>
						</div>
						<!-- 添加重复提示 -->
						<div class="duplicate-error" id="duplicateError">
							<i class="bi bi-exclamation-triangle"></i> 该群聊编号已存在！
						</div>
					</div>
					<!-- 欢迎语 -->
					<div class="form-group">
						<label for="XEyouxige_welcome_message" class="form-label required">欢迎语</label>
						<textarea class="form-input" id="XEyouxige_welcome_message" name="XEyouxige_welcome_message" rows="3" placeholder="您好!欢迎来到本平台交易，我是您的专属客服。非常感谢您选择我们平台来进行游戏账号的交易。在这里，我们致力于为您提供安全、可靠、高效的服务体验。"></textarea>
					</div>
					<!-- 页面状态 -->
					<div class="form-group">
						<label for="XEyouxige_page_status" class="form-label required">群聊状态</label>
						<select class="form-select" id="XEyouxige_page_status" name="XEyouxige_page_status" required>
							<option value="active">开启</option>
							<option value="inactive">关闭</option>
						</select>
					</div>
					<!-- 卖家头像上传 -->
					<div class="form-group">
						<label class="form-label required">卖家头像</label>
						<div class="upload-container" id="sellerAvatarUpload">
							<div class="upload-icon">
								<i class="bi bi-person-circle"></i>
							</div>
							<div class="upload-text">点击上传卖家头像</div>
							<div class="upload-hint">支持 JPG、PNG、GIF、WebP 格式，最大 2MB</div>
							<input type="file" class="upload-input" id="XEyouxige_seller_avatar" name="XEyouxige_seller_avatar" accept="image/*">
						</div>
						<!-- 添加默认头像按钮 -->
						<div class="default-avatar-container" style="margin-top: 10px;">
							<button type="button" class="default-avatar-btn" id="useDefaultAvatar">
								<i class="bi bi-person-check"></i> 使用默认头像 </button>
						</div>
						<!-- 当前卖家头像（仅编辑时显示） -->
						<div id="currentSellerAvatar" class="current-image" style="display: none;">
							<span class="current-image-label">当前头像：</span>
							<img src="" alt="当前卖家头像" class="current-image-preview">
							<span class="current-image-text"></span>
						</div>
						<!-- 新头像预览 -->
						<div class="upload-preview" id="avatarPreview">
							<button type="button" class="preview-remove" onclick="removeSellerAvatar()">×</button>
							<img src="" alt="头像预览" class="preview-image" id="avatarPreviewImg">
							<div class="preview-info">
								<span>新头像：</span>
								<span class="preview-name" id="avatarImageName"></span>
							</div>
						</div>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" onclick="closeModal()">取消</button>
					<button type="submit" class="btn btn-primary" id="submitBtn">添加</button>
				</div>
			</form>
		</div>
	</div>
	<!-- 删除确认模态框 -->
	<div class="confirm-overlay" id="confirmModal">
		<div class="confirm-content">
			<h3 class="confirm-title">确认删除</h3>
			<p class="confirm-text">您确定要删除这个群聊吗？此操作不可撤销。</p>
			<div class="confirm-buttons">
				<button class="confirm-btn confirm-cancel" onclick="closeConfirmModal()">取消</button>
				<button class="confirm-btn confirm-delete" id="confirmDelete">确认删除</button>
			</div>
		</div>
	</div>
	
<div class="confirm-overlay" id="shareModal">
	<div class="share-modal-content">
    <div class="share-header">
        <h3 class="share-title">分享群聊</h3>
        <button type="button" class="modal-close" onclick="closeShareModal()" style="position: absolute; right: 15px; top: 15px; font-size: 24px; background: none; border: none; color: #666; cursor: pointer;">×</button>
    </div>
    <div class="share-url-container">
        <div class="share-url" id="shareUrlText"></div>
        <div class="share-param-info" id="shareParamInfo" style="margin-top: 10px; font-size: 12px; color: #666;">
            <span>会话ID: <span id="sessionIdDisplay"></span></span>
        </div>
    </div>
    <div class="share-buttons">
        <button class="share-btn share-btn-copy" id="shareCopyBtn">
            <i class="bi bi-clipboard"></i> 复制
        </button>
        <button class="share-btn share-btn-refresh" id="refreshShareLinkBtn" style="background: #6c757d;">
        <i class="bi bi-arrow-clockwise"></i> 刷新链接
    </button>
    </div>
</div>
	</div>

	<!-- 分享图模态框 -->
<div class="confirm-overlay" id="shareImageModal">
    <div class="share-image-modal">
        <div class="share-image-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
            <h3 style="margin: 0; font-size: 18px; font-weight: 600;">白情分享图</h3>
            <button type="button" class="modal-close" onclick="closeShareImageModal()" style="font-size: 24px; background: none; border: none; color: #666; cursor: pointer;">×</button>
        </div>
        
        <div class="qrPoster" id="shareImageContainer">
            <div class="qrPosterInner">
                <div class="qrPosterTop">
                    <img class="qrPosterAvatar" src="/assets/img/youxige.ico" alt="头像">
                    <div class="qrPosterName" id="shareImagePosterName">交易员名称</div>
                    <div class="qrPosterBadges">
                        <span class="qrPosterTag" id="shareImagePosterTag">白情群聊</span>
                        <span class="qrBadge hours" id="shareImagePosterHours">安全交易</span>
                    </div>
                    <div class="qrPosterHint" id="shareImagePosterHint">请扫描下方二维码加入群聊</div>
                </div>

                <div class="qrDivider"></div>

                <div class="qrPosterMid">
                    <div class="qrBox">
                        <div id="shareImageQrCodeBox">
                        </div>
                    </div>

                    <div class="qrPosterScan">建议使用浏览器"扫一扫"识别（更稳定）</div>
                </div>

                <div class="qrPosterFoot">提示：切勿将二维码转发给他人！</div>
            </div>
        </div>
        
        <div class="share-image-actions" style="display: flex; gap: 10px; margin-top: 15px;">
            <button class="share-image-btn" id="returnFromImageBtn" style="flex: 1; padding: 12px; background: #f2f2f7; border: none; border-radius: 10px; font-size: 14px; cursor: pointer;">
                <i class="bi bi-arrow-left"></i> 返回
            </button>
            <button class="share-image-btn" id="saveShareImageBtn" style="flex: 1; padding: 12px; background: #007AFF; color: white; border: none; border-radius: 10px; font-size: 14px; cursor: pointer;">
                <i class="bi bi-download"></i> 保存图片
            </button>
        </div>
    </div>
</div>
	<!-- 删除确认表单 -->
	<form id="deleteForm" method="POST" style="display: none;">
		<input type="hidden" name="action" value="delete_chatroom">
		<input type="hidden" name="id" id="deleteId" value="">
	</form>
<script>
// ==================== 全局常量和工具函数 ====================
const IMAGE_MAX_SIZE = 2 * 1024 * 1024; // 2MB
const ALLOWED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
const DEFAULT_AVATAR_PATH = '<?php echo DEFAULT_AVATAR_PATH; ?>';

// DOM 元素引用
const domElements = {
    productModal: document.getElementById('productModal'),
    confirmModal: document.getElementById('confirmModal'),
    shareModal: document.getElementById('shareModal'),
    shareImageModal: null,
    antiRedModal: null,
    productForm: document.getElementById('productForm'),
    notification: document.getElementById('notification'),
    randomCodeBtn: document.getElementById('generateRandomCode'),
    savingOverlay: document.getElementById('savingOverlay'),
    
    // 分享相关
    shareUrlText: document.getElementById('shareUrlText'),
    shareCopyBtn: document.getElementById('shareCopyBtn'),
    shareImageBtn: document.getElementById('shareImageBtn'),
    sessionIdDisplay: document.getElementById('sessionIdDisplay'),
    
    // 分享图相关
    returnFromImageBtn: document.getElementById('returnFromImageBtn'),
    saveImageBtn: document.getElementById('saveImageBtn'),
    refreshQrLinkBtn: document.getElementById('refreshQrLinkBtn'),
    posterName: document.getElementById('posterName'),
    posterTag: document.getElementById('posterTag'),
    posterHint: document.getElementById('posterHint'),
    posterAvatar: document.getElementById('posterAvatar'),
    qrCodeBox: document.getElementById('qrCodeBox'),
    shareImageContainer: document.getElementById('shareImageContainer'),
    
    // 表单相关
    productId: document.getElementById('productId'),
    modalTitle: document.getElementById('modalTitle'),
    submitBtn: document.getElementById('submitBtn'),
    traderName: document.getElementById('XEyouxige_trader_name'),
    groupCode: document.getElementById('XEyouxige_group_code'),
    welcomeMessage: document.getElementById('XEyouxige_welcome_message'),
    pageStatus: document.getElementById('XEyouxige_page_status'),
    sellerAvatarInput: document.getElementById('XEyouxige_seller_avatar'),
    
    // 预览相关
    sellerAvatarUpload: document.getElementById('sellerAvatarUpload'),
    avatarPreview: document.getElementById('avatarPreview'),
    avatarPreviewImg: document.getElementById('avatarPreviewImg'),
    avatarImageName: document.getElementById('avatarImageName'),
    currentSellerAvatar: document.getElementById('currentSellerAvatar'),
    
    // 隐藏字段
    oldSellerAvatar: document.getElementById('old_seller_avatar'),
    defaultAvatarUsed: document.getElementById('defaultAvatarUsed'),
    sellerAvatarUrl: document.getElementById('sellerAvatarUrl'),
    
    // 重复错误提示
    duplicateError: document.getElementById('duplicateError'),
    
    // 刷新链接按钮
    refreshShareLinkBtn: document.getElementById('refreshShareLinkBtn'),
    

};

// 当前分享的URL和群聊ID
let currentShareUrl = '';
let currentChatroomId = 0;
let currentSessionId = '';
let currentShareParam = '';
let userAntiRedConfig = null;

// 存储已存在的商品编号
let existingGroupCodes = [];
<?php if (isset($chatrooms)): ?>
<?php foreach ($chatrooms as $chatroom): ?>
existingGroupCodes.push("<?php echo $chatroom['XEyouxige_group_code']; ?>");
<?php endforeach; ?>
<?php endif; ?>

// 防红配置状态管理器
const antiRedStateManager = {
    saveAppliedState(domainName) {
        const state = {
            appliedDomain: domainName,
            applyStatus: 'on',
            timestamp: Date.now()
        };
        localStorage.setItem('antiRedConfig', JSON.stringify(state));
    },
    
    clearAppliedState() {
        localStorage.removeItem('antiRedConfig');
    },
    
    getAppliedState() {
        try {
            const saved = localStorage.getItem('antiRedConfig');
            return saved ? JSON.parse(saved) : null;
        } catch (error) {
            return null;
        }
    },
    
    isStateExpired(state) {
        const SEVEN_DAYS = 7 * 24 * 60 * 60 * 1000;
        return Date.now() - state.timestamp > SEVEN_DAYS;
    }
};

/**
 * 生成随机字符串
 */
function generateRandomString(length = 6) {
    const characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
    let randomString = '';
    for (let i = 0; i < length; i++) {
        randomString += characters[Math.floor(Math.random() * characters.length)];
    }
    return randomString;
}

/**
 * 生成分享参数
 */
function generateShareParam(length = 16) {
    return generateRandomString(length);
}

function simpleHash(input) {
    let hash = 5381;
    for (let i = 0; i < input.length; i++) {
        const char = input.charCodeAt(i);
        hash = ((hash << 5) + hash) + char;
    }
    return Math.abs(hash).toString(36);
}

function generateChatroomShareUrl(chatroomData) {
    const baseUrl = window.location.origin + "/Youxige";
    const pageCode = chatroomData.page_code;
    const username = '<?php echo isset($_SESSION['username']) ? $_SESSION['username'] : 'default'; ?>';
    
    const fixedSeed = simpleHash(pageCode + (username || 'default'));
    
    const characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
    let customerName = '';
    for (let i = 0; i < 6; i++) {
        const index = parseInt(fixedSeed.substr(i, 1), 36) || (i * 7) % characters.length;
        customerName += characters[index % characters.length];
    }
    
    currentSessionId = 'a' + customerName + 'z-p' + (username || 'default') + 's';
    
    const originalUrl = `${baseUrl}?XEchatroom=${pageCode}&id=${currentSessionId}`;
    
    if (userAntiRedConfig && 
        userAntiRedConfig.apply_status === 'on' && 
        userAntiRedConfig.applied_domain) {
        
        let apiUrl = userAntiRedConfig.api_url;
        if (!apiUrl && userAntiRedConfig.applied_domain) {
            apiUrl = userAntiRedConfig.applied_domain.startsWith('http') 
                ? userAntiRedConfig.applied_domain 
                : 'http://' + userAntiRedConfig.applied_domain;
            apiUrl = apiUrl.replace(/\/+$/, '');
        } else if (apiUrl) {
            apiUrl = apiUrl.replace(/\/+$/, '');
        }
        
        try {
            const encodedUrl = btoa(originalUrl);
            const antiRedUrl = apiUrl + encodedUrl;
            return antiRedUrl;
        } catch (error) {
            return originalUrl;
        }
    } else {
        return originalUrl;
    }
}

// ==================== 工具函数 ====================
function showNotification(message, type = 'success', duration = 3000) {
    const existingToast = document.querySelector('.new-user-toast');
    if (existingToast) {
        existingToast.classList.add('notification-leave-active');
        setTimeout(() => {
            if (existingToast.parentNode) {
                existingToast.parentNode.removeChild(existingToast);
            }
        }, 300);
    }
    
    const toast = document.createElement('div');
    toast.className = `new-user-toast notification-enter-active ${type}`;
    
    let iconChar = '✓';
    if (type === 'info') iconChar = 'i';
    if (type === 'warning') iconChar = '!';
    if (type === 'error') iconChar = '×';
    
    toast.innerHTML = `
        <div class="toast-icon">${iconChar}</div>
        <div class="toast-text">${message}</div>
        <button class="toast-close">×</button>
    `;
    
    document.body.appendChild(toast);
    
    const closeBtn = toast.querySelector('.toast-close');
    closeBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        toast.classList.add('notification-leave-active');
        setTimeout(() => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 300);
    });
    
    setTimeout(() => {
        if (toast.parentNode) {
            toast.classList.add('notification-leave-active');
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 300);
        }
    }, duration);
}

// 验证文件
function validateFile(file) {
    if (file.size > IMAGE_MAX_SIZE) {
        showNotification('文件大小不能超过2MB', 'error');
        return false;
    }
    
    if (!ALLOWED_IMAGE_TYPES.includes(file.type)) {
        showNotification('只支持JPG、PNG、GIF、WebP格式的图片', 'error');
        return false;
    }
    
    return true;
}

// 检查商品编号是否重复
function checkGroupCodeDuplicate(code, currentId = 0) {
    if (!code) return false;
    
    // 如果是编辑模式，要排除自己
    if (currentId > 0) {
        const currentData = window.chatroomsData ? window.chatroomsData[currentId] : null;
        if (currentData && currentData.group_code === code) {
            return false;
        }
    }
    
    return existingGroupCodes.includes(code);
}

// 更新重复检查状态
function updateDuplicateStatus() {
    const code = domElements.groupCode.value.trim();
    const currentId = parseInt(domElements.productId.value) || 0;
    
    if (!code) {
        if (domElements.duplicateError) {
            domElements.duplicateError.classList.remove('show');
        }
        if (domElements.groupCode) {
            domElements.groupCode.classList.remove('duplicate');
        }
        return false;
    }
    
    const isDuplicate = checkGroupCodeDuplicate(code, currentId);
    
    if (domElements.duplicateError && domElements.groupCode) {
        if (isDuplicate) {
            domElements.duplicateError.classList.add('show');
            domElements.groupCode.classList.add('duplicate');
        } else {
            domElements.duplicateError.classList.remove('show');
            domElements.groupCode.classList.remove('duplicate');
        }
    }
    
    return isDuplicate;
}

// 重置预览
function resetPreview(previewElement, previewImg, nameElement, uploadContainer) {
    if (previewElement) previewElement.classList.remove('show');
    if (uploadContainer) uploadContainer.classList.remove('has-file');
    if (nameElement) nameElement.textContent = '';
    if (previewImg) previewImg.src = '';
}

// 设置图片上传
function setupImageUpload(inputElement, previewElement, previewImg, nameElement, uploadContainer, options = {}) {
    if (!inputElement || !previewElement || !previewImg || !nameElement) return;
    
    inputElement.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (!file) {
            resetPreview(previewElement, previewImg, nameElement, uploadContainer);
            return;
        }
        
        if (!validateFile(file)) {
            this.value = '';
            return;
        }
        
        if (options.onChange) options.onChange(file);
        
        const reader = new FileReader();
        reader.onload = function(e) {
            previewImg.src = e.target.result;
            nameElement.textContent = file.name;
            previewElement.classList.add('show');
            if (uploadContainer) uploadContainer.classList.add('has-file');
        };
        reader.readAsDataURL(file);
    });
}

// 设置拖放上传
function setupDragAndDrop(uploadContainer, fileInput, onChangeCallback) {
    if (!uploadContainer || !fileInput) return;
    
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        uploadContainer.addEventListener(eventName, preventDefaults, false);
    });
    
    ['dragenter', 'dragover'].forEach(eventName => {
        uploadContainer.addEventListener(eventName, () => {
            uploadContainer.classList.add('highlight');
        }, false);
    });
    
    ['dragleave', 'drop'].forEach(eventName => {
        uploadContainer.addEventListener(eventName, () => {
            uploadContainer.classList.remove('highlight');
        }, false);
    });
    
    uploadContainer.addEventListener('drop', function(e) {
        const dt = e.dataTransfer;
        const file = dt.files[0];
        if (file && file.type.startsWith('image/')) {
            fileInput.files = dt.files;
            fileInput.dispatchEvent(new Event('change'));
        }
    }, false);
}

function preventDefaults(e) {
    e.preventDefault();
    e.stopPropagation();
}

// 防红配置API调用
async function apiCall(action, data = {}) {
    const formData = new FormData();
    formData.append('action', action);
    
    Object.keys(data).forEach(key => {
        formData.append(key, data[key]);
    });
    
    const response = await fetch('/config/domain_api.php', {
        method: 'POST',
        body: formData
    });
    
    if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
    }
    
    return await response.json();
}

// 加载用户防红配置
async function loadAntiRedConfig() {
    try {
        const response = await apiCall('get_user_anti_red_config');
        
        if (response.success && response.config) {
            const config = response.config;
            
            if (!config.api_url && config.applied_domain) {
                config.api_url = `${config.applied_domain}/`;
            }
            
            return config;
        }
    } catch (error) {
    }
    return null;
}

// 加载防红数据
async function loadAntiRedData() {
    try {
        showNotification('正在加载防红配置...', 'info');
        
        const userConfigResponse = await apiCall('get_user_anti_red_config');
        
        if (userConfigResponse.success && userConfigResponse.config) {
            const config = userConfigResponse.config;
            userAntiRedConfig = config;
            updateUserConfig(config);
            
            if (config.apply_status === 'on' && config.applied_domain) {
                updateAntiRedStatus(config.applied_domain);
            } else {
                updateAntiRedStatus('');
            }
        } else {
            userAntiRedConfig = null;
            updateAntiRedStatus('');
        }
        
        const userInfo = await apiCall('get_user_info');
        if (userInfo.success) {
            updateUserInfo(userInfo);
        }
        
        const userLinks = await apiCall('get_user_links', { limit: 20 });
        if (userLinks.success) {
            const appliedDomain = userAntiRedConfig?.applied_domain || '';
            renderUserLinks(userLinks.links, appliedDomain);
        }
        
    } catch (error) {
        showNotification('加载防红配置失败', 'error');
        userAntiRedConfig = null;
    }
}

// 更新用户信息
function updateUserInfo(data) {
    const stockElement = document.getElementById('stock-count');
    const balanceElement = document.getElementById('balance-amount');
    
    if (stockElement) {
        stockElement.textContent = data.stock || 0;
    }
    
    if (balanceElement) {
        const balance = parseFloat(data.balance) || 0;
        balanceElement.textContent = balance.toFixed(2) + '元';
    }
}

// 渲染用户防红链接
function renderUserLinks(links, appliedDomain = '') {
    const container = document.getElementById('user-links-container');
    if (!container) return;
    
    if (links.length === 0) {
        container.innerHTML = '<div class="no-links">您还没有购买过任何防红链接</div>';
        return;
    }
    
    container.innerHTML = links.map(link => {
        const isApplied = link.domain_name === appliedDomain;
        return `
        <div class="anti-red-link" data-domain="${link.domain_name}">
            <div class="link-info">
                <div class="link-domain">${link.domain_name}</div>
                <div class="link-date">${formatDate(link.sold_date)} 购买</div>
            </div>
            <div class="apply-link ${isApplied ? 'applied' : ''}" 
                 onclick="toggleApplyButton(this, '${link.domain_name}')"
                 data-applied="${isApplied}">${isApplied ? '取消' : '应用'}</div>
        </div>
        `;
    }).join('');
}

// 应用/取消防红域名
async function toggleApplyButton(button, domainName) {
    const isApplied = button.getAttribute('data-applied') === 'true';
    
    if (!isApplied) {
        try {
            showNotification('正在应用防红域名...', 'info');
            const response = await apiCall('apply_anti_red_domain', {
                domain_name: domainName
            });
            
            if (response.success) {
                button.setAttribute('data-applied', 'true');
                button.textContent = '取消';
                button.classList.add('applied');
                
                antiRedStateManager.saveAppliedState(domainName);
                updateAntiRedStatus(domainName);
                resetAllApplyButtons(domainName);
                
                userAntiRedConfig = await loadAntiRedConfig();
                
                if (currentChatroomId && domElements.shareModal.classList.contains('show')) {
                    const chatroomData = window.chatroomsData[currentChatroomId];
                    if (chatroomData) {
                        currentShareUrl = generateChatroomShareUrl(chatroomData);
                        domElements.shareUrlText.textContent = currentShareUrl;
                    }
                }
                
                showNotification('防红域名应用成功: ' + domainName, 'success');
            } else {
                showNotification('应用失败: ' + response.message, 'error');
            }
        } catch (error) {
            showNotification('应用失败，请稍后重试', 'error');
        }
    } else {
        try {
            showNotification('正在取消应用...', 'info');
            const response = await apiCall('cancel_anti_red_domain');
            
            if (response.success) {
                button.setAttribute('data-applied', 'false');
                button.textContent = '应用';
                button.classList.remove('applied');
                
                antiRedStateManager.clearAppliedState();
                updateAntiRedStatus('');
                userAntiRedConfig = await loadAntiRedConfig();
                
                if (currentChatroomId && domElements.shareModal.classList.contains('show')) {
                    const chatroomData = window.chatroomsData[currentChatroomId];
                    if (chatroomData) {
                        currentShareUrl = generateChatroomShareUrl(chatroomData);
                        domElements.shareUrlText.textContent = currentShareUrl;
                    }
                }
                
                showNotification('已取消应用: ' + domainName, 'success');
            } else {
                showNotification('取消应用失败: ' + response.message, 'error');
            }
        } catch (error) {
            showNotification('取消应用失败，请稍后重试', 'error');
        }
    }
}

// 更新用户配置
function updateUserConfig(config) {
    if (!config) return;
    
    const redirectSwitch = document.getElementById('redirect-switch');
    if (redirectSwitch) {
        if (config.redirect_to_browser) {
            redirectSwitch.classList.add('on');
        } else {
            redirectSwitch.classList.remove('on');
        }
    }
}

// 更新页面顶部防红状态
function updateAntiRedStatus(domainName = '') {
    const statusElement = document.getElementById('current-anti-red-domain');
    if (!statusElement) return;
    
    if (domainName) {
        statusElement.textContent = '已应用';
        statusElement.style.color = '#28a745';
    } else {
        statusElement.textContent = '未配置';
        statusElement.style.color = '#666';
    }
}

// 重置所有应用按钮状态
function resetAllApplyButtons(exceptDomain) {
    const buttons = document.querySelectorAll('.apply-link');
    buttons.forEach(button => {
        const domain = button.closest('.anti-red-link').getAttribute('data-domain');
        if (domain !== exceptDomain) {
            button.setAttribute('data-applied', 'false');
            button.textContent = '应用';
            button.classList.remove('applied');
        }
    });
}

// 购买防红链接
async function buyAntiRedLink() {
    const balanceElement = document.getElementById('balance-amount');
    let balance = 0;
    
    if (balanceElement) {
        const balanceText = balanceElement.textContent;
        balance = parseFloat(balanceText) || 0;
    }
    
    if (balance < 5.2) {
        showNotification(`余额不足，当前余额 ${balance.toFixed(2)}元，需要5.2元`, 'error');
        return;
    }
    
    try {
        showNotification('正在购买防红链接...', 'info');
        const response = await apiCall('buy_anti_red');
        
        if (response.success) {
            showNotification('购买成功！您的防红链接：' + response.domain_name, 'success');
            await loadAntiRedData();
        } else {
            showNotification('购买失败：' + response.message, 'error');
        }
    } catch (error) {
        showNotification('购买失败，请稍后重试', 'error');
    }
}

// 切换引到浏览器开关
async function toggleRedirectSwitch() {
    const switchElement = document.getElementById('redirect-switch');
    const isEnabled = switchElement.classList.contains('on');
    const newState = !isEnabled;
    
    try {
        const response = await apiCall('update_redirect_switch', {
            redirect_to_browser: newState ? 1 : 0
        });
        
        if (response.success) {
            if (newState) {
                switchElement.classList.add('on');
            } else {
                switchElement.classList.remove('on');
            }
            showNotification('设置已更新', 'success');
        } else {
            showNotification('设置更新失败: ' + response.message, 'error');
        }
    } catch (error) {
        showNotification('设置更新失败，请稍后重试', 'error');
    }
}

// 日期格式化函数
function formatDate(dateString) {
    if (!dateString) return '未知日期';
    
    const date = new Date(dateString);
    const month = (date.getMonth() + 1).toString().padStart(2, '0');
    const day = date.getDate().toString().padStart(2, '0');
    return `${month}/${day}`;
}

// ==================== 模态框管理 ====================
function openModal(mode = 'add', id = null) {
    const modal = domElements.productModal;
    const title = domElements.modalTitle;
    const submitBtn = domElements.submitBtn;
    
    if (!modal || !title || !submitBtn) return;
    
    if (mode === 'add') {
        title.textContent = '群聊设置';
        submitBtn.textContent = '创建群聊';
        resetForm();
    } else if (mode === 'edit' && id) {
        title.textContent = '编辑群聊';
        submitBtn.textContent = '更新群聊';
        loadChatroomData(id);
    }
    
    modal.classList.add('show');
    document.body.style.overflow = 'hidden';
}

function resetForm() {
    const currentId = domElements.productId ? domElements.productId.value : '0';
    
    if (domElements.productForm) {
        domElements.productForm.reset();
    }
    
    if (currentId !== '0' && domElements.productId) {
        domElements.productId.value = currentId;
    } else if (domElements.productId) {
        domElements.productId.value = '0';
    }
    
    if (domElements.oldSellerAvatar) {
        domElements.oldSellerAvatar.value = '';
    }
    if (domElements.defaultAvatarUsed) {
        domElements.defaultAvatarUsed.value = '0';
    }
    if (domElements.sellerAvatarUrl) {
        domElements.sellerAvatarUrl.value = '';
    }
    
    if (domElements.duplicateError) {
        domElements.duplicateError.classList.remove('show');
    }
    if (domElements.groupCode) {
        domElements.groupCode.classList.remove('duplicate');
    }
    
    if (domElements.randomCodeBtn) {
        domElements.randomCodeBtn.innerHTML = '<i class="bi bi-shuffle"></i> 随机编号';
        domElements.randomCodeBtn.classList.remove('success');
    }
    
    resetImagePreviews();
}

function resetImagePreviews() {
    resetPreview(domElements.avatarPreview, domElements.avatarPreviewImg, 
                 domElements.avatarImageName, domElements.sellerAvatarUpload);
    if (domElements.currentSellerAvatar) {
        domElements.currentSellerAvatar.style.display = 'none';
    }
}

function loadChatroomData(id) {
    const numericId = parseInt(id);
    
    if (!window.chatroomsData || !window.chatroomsData[numericId]) {
        showNotification('加载群聊数据失败', 'error');
        return;
    }
    
    const chatroomData = window.chatroomsData[numericId];
    
    if (domElements.productId) {
        domElements.productId.value = numericId;
    }
    
    if (domElements.traderName) {
        domElements.traderName.value = chatroomData.trader_name || '';
    }
    
    if (domElements.groupCode) {
        domElements.groupCode.value = chatroomData.group_code || '';
    }
    
    if (domElements.welcomeMessage) {
        domElements.welcomeMessage.value = chatroomData.welcome_message || '';
    }
    
    if (domElements.oldSellerAvatar) {
        domElements.oldSellerAvatar.value = chatroomData.seller_avatar || '';
    }
    
    if (domElements.pageStatus) {
        domElements.pageStatus.value = chatroomData.page_status || 'active';
    }
    
    if (chatroomData.seller_avatar === DEFAULT_AVATAR_PATH) {
        if (domElements.defaultAvatarUsed) {
            domElements.defaultAvatarUsed.value = '1';
        }
        if (domElements.sellerAvatarUrl) {
            domElements.sellerAvatarUrl.value = DEFAULT_AVATAR_PATH;
        }
    } else {
        if (domElements.defaultAvatarUsed) {
            domElements.defaultAvatarUsed.value = '0';
        }
        if (domElements.sellerAvatarUrl) {
            domElements.sellerAvatarUrl.value = '';
        }
    }
    
    if (chatroomData.seller_avatar && domElements.currentSellerAvatar) {
        const previewImg = domElements.currentSellerAvatar.querySelector('img');
        const previewText = domElements.currentSellerAvatar.querySelector('.current-image-text');
        
        if (previewImg) {
            previewImg.src = chatroomData.seller_avatar;
        }
        if (previewText) {
            previewText.textContent = '已上传卖家头像';
        }
        domElements.currentSellerAvatar.style.display = 'flex';
    } else if (domElements.currentSellerAvatar) {
        domElements.currentSellerAvatar.style.display = 'none';
    }
    
    if (domElements.randomCodeBtn) {
        domElements.randomCodeBtn.innerHTML = '<i class="bi bi-shuffle"></i> 随机编号';
        domElements.randomCodeBtn.classList.remove('success');
    }
    
    updateDuplicateStatus();
}

function closeModal() {
    if (domElements.productModal) {
        domElements.productModal.classList.remove('show');
    }
    document.body.style.overflow = 'auto';
}

function openConfirmModal(id) {
    const numericId = parseInt(id);
    if (!numericId || numericId <= 0) {
        showNotification('无效的商品ID: ' + id, 'error');
        return;
    }
    
    const deleteIdInput = document.getElementById('deleteId');
    if (deleteIdInput) {
        deleteIdInput.value = numericId;
    } else {
        const deleteForm = document.getElementById('deleteForm');
        if (deleteForm) {
            const hiddenInput = deleteForm.querySelector('input[name="id"]');
            if (hiddenInput) {
                hiddenInput.value = numericId;
            }
        }
        return;
    }
    
    if (domElements.confirmModal) {
        domElements.confirmModal.classList.add('show');
    }
    document.body.style.overflow = 'hidden';
}

function closeConfirmModal() {
    if (domElements.confirmModal) {
        domElements.confirmModal.classList.remove('show');
    }
    document.body.style.overflow = 'auto';
}

// 分享模态框
async function openShareModal(id) {
    currentChatroomId = id;
    const chatroomData = window.chatroomsData ? window.chatroomsData[id] : null;
    if (!chatroomData) {
        showNotification('群聊数据加载失败', 'error');
        return;
    }
    
    if (!userAntiRedConfig) {
        userAntiRedConfig = await loadAntiRedConfig();
        if (userAntiRedConfig && userAntiRedConfig.apply_status === 'on' && userAntiRedConfig.applied_domain) {
            updateAntiRedStatus(userAntiRedConfig.applied_domain);
        }
    }
    
    currentShareUrl = generateChatroomShareUrl(chatroomData);
    
    if (domElements.shareUrlText) domElements.shareUrlText.textContent = currentShareUrl;
    if (domElements.sessionIdDisplay) domElements.sessionIdDisplay.textContent = currentSessionId;
    
    if (domElements.shareModal) {
        domElements.shareModal.classList.add('show');
    }
    document.body.style.overflow = 'hidden';
}

function closeShareModal() {
    if (domElements.shareModal) {
        domElements.shareModal.classList.remove('show');
    }
    document.body.style.overflow = 'auto';
}



// 打开分享图模态框
async function openShareImageModal(id) {
    if (!id) {
        showNotification('群聊ID不存在', 'error');
        return;
    }
    
    currentChatroomId = id;
    const chatroomData = window.chatroomsData ? window.chatroomsData[id] : null;
    if (!chatroomData) {
        showNotification('群聊数据加载失败', 'error');
        return;
    }
    
    // 确保防红配置已加载
    if (!userAntiRedConfig) {
        userAntiRedConfig = await loadAntiRedConfig();
    }
    
    // 生成分享链接（支持防红）
    currentShareUrl = generateChatroomShareUrl(chatroomData);
    
    // 填充分享图数据
    fillShareImageData(chatroomData);
    
    // 生成二维码
    generateShareImageQRCode(currentShareUrl);
    
    // 显示模态框
    const modal = document.getElementById('shareImageModal');
    if (modal) {
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
    }
}

function closeShareImageModal() {
    const modal = document.getElementById('shareImageModal');
    if (modal) {
        modal.classList.remove('show');
    }
    document.body.style.overflow = 'auto';
}

// 刷新分享链接
async function refreshShareLink() {
    if (!currentChatroomId) {
        showNotification('请先选择群聊', 'error');
        return;
    }
    
    const chatroomData = window.chatroomsData ? window.chatroomsData[currentChatroomId] : null;
    if (!chatroomData) {
        showNotification('群聊数据加载失败', 'error');
        return;
    }
    
    userAntiRedConfig = await loadAntiRedConfig();
    
    currentShareUrl = generateChatroomShareUrl(chatroomData);
    if (domElements.shareUrlText) domElements.shareUrlText.textContent = currentShareUrl;
    
    showNotification('链接已刷新', 'success');
}

function fillShareImageData(chatroomData) {
    // 设置交易员名称
    document.getElementById('shareImagePosterName').textContent = 
        chatroomData.trader_name || '交易员名称';
    
    // 设置标签
    document.getElementById('shareImagePosterTag').textContent = '白情群聊';
    document.getElementById('shareImagePosterHours').textContent = '安全交易';
    
    // 设置提示语
    document.getElementById('shareImagePosterHint').textContent = 
        '请扫描下方二维码加入群聊';
}

function generateQRCode(url) {
    if (!domElements.qrCodeBox) return;
    
    domElements.qrCodeBox.innerHTML = '';
    
    try {
        if (typeof QRCode !== 'undefined') {
            const qrDiv = document.createElement('div');
            qrDiv.style.width = '118px';
            qrDiv.style.height = '118px';
            
            new QRCode(qrDiv, {
                text: url,
                width: 118,
                height: 118,
                colorDark: "#000000",
                colorLight: "#ffffff",
                correctLevel: QRCode.CorrectLevel.H
            });
            
            domElements.qrCodeBox.appendChild(qrDiv);
        } else {
            generateFallbackQRCode(url);
        }
    } catch (error) {
        generateFallbackQRCode(url);
    }
}

function generateFallbackQRCode(url) {
    if (!domElements.qrCodeBox) return;
    
    const qrDiv = document.createElement('div');
    qrDiv.style.width = '118px';
    qrDiv.style.height = '118px';
    qrDiv.style.backgroundColor = '#ffffff';
    qrDiv.style.display = 'flex';
    qrDiv.style.alignItems = 'center';
    qrDiv.style.justifyContent = 'center';
    qrDiv.style.border = '1px solid #ddd';
    
    const text = document.createElement('span');
    text.textContent = 'QR Code';
    text.style.color = '#666';
    text.style.fontSize = '12px';
    
    qrDiv.appendChild(text);
    domElements.qrCodeBox.appendChild(qrDiv);
}

// 生成分享图二维码（支持防红）
function generateShareImageQRCode(url) {
    const qrCodeBox = document.getElementById('shareImageQrCodeBox');
    if (!qrCodeBox) {
        console.error('二维码容器不存在');
        return;
    }
    
    // 清空现有的二维码容器
    qrCodeBox.innerHTML = '';
    
    try {
        // 使用 QRCode.js
        if (typeof QRCode !== 'undefined') {
            const qrDiv = document.createElement('div');
            qrDiv.style.width = '118px';
            qrDiv.style.height = '118px';
            
            new QRCode(qrDiv, {
                text: url,
                width: 118,
                height: 118,
                colorDark: "#000000",
                colorLight: "#ffffff",
                correctLevel: QRCode.CorrectLevel.H
            });
            
            qrCodeBox.appendChild(qrDiv);
            console.log('分享图二维码已生成，链接:', url);
        } else {
            console.error('QRCode 库未加载');
            showNotification('二维码生成失败，请刷新页面重试', 'error');
        }
    } catch (error) {
        console.error('生成二维码出错:', error);
        showNotification('二维码生成失败', 'error');
    }
}

// 保存分享图
function saveShareImage() {
    const container = document.getElementById('shareImageContainer');
    if (!container) {
        showNotification('无法生成图片', 'error');
        return;
    }
    
    // 显示加载提示
    const savingOverlay = document.getElementById('savingOverlay');
    if (savingOverlay) {
        savingOverlay.style.display = 'flex';
    }
    
    // 使用html2canvas生成图片
    html2canvas(container, {
        backgroundColor: null,
        scale: 2,
        useCORS: true,
        logging: false
    }).then(canvas => {
        const imageUrl = canvas.toDataURL('image/png');
        const downloadLink = document.createElement('a');
        const timestamp = new Date().getTime();
        const fileName = `分享图_${currentChatroomId}_${timestamp}.png`;
        
        downloadLink.href = imageUrl;
        downloadLink.download = fileName;
        
        document.body.appendChild(downloadLink);
        downloadLink.click();
        document.body.removeChild(downloadLink);
        
        if (savingOverlay) {
            savingOverlay.style.display = 'none';
        }
        showNotification('分享图保存成功！');
    }).catch(error => {
        console.error('生成图片失败:', error);
        if (savingOverlay) {
            savingOverlay.style.display = 'none';
        }
        showNotification('生成图片失败，请重试', 'error');
    });
}

function saveAsImage() {
    if (!domElements.shareImageContainer) {
        showNotification('无法生成图片', 'error');
        return;
    }
    
    if (domElements.savingOverlay) {
        domElements.savingOverlay.style.display = 'flex';
    }
    
    html2canvas(domElements.shareImageContainer, {
        backgroundColor: null,
        scale: 2,
        useCORS: true,
        logging: false
    }).then(canvas => {
        const imageUrl = canvas.toDataURL('image/png');
        const downloadLink = document.createElement('a');
        const timestamp = new Date().getTime();
        const fileName = `XEyouxige_${currentChatroomId}_${timestamp}.png`;
        
        downloadLink.href = imageUrl;
        downloadLink.download = fileName;
        
        document.body.appendChild(downloadLink);
        downloadLink.click();
        document.body.removeChild(downloadLink);
        
        if (domElements.savingOverlay) {
            domElements.savingOverlay.style.display = 'none';
        }
        showNotification('图片保存成功！');
    }).catch(error => {
        if (domElements.savingOverlay) {
            domElements.savingOverlay.style.display = 'none';
        }
        showNotification('生成图片失败，请重试', 'error');
    });
}

// ==================== 头像处理 ====================
function handleDefaultAvatar() {
    if (!domElements.defaultAvatarUsed || !domElements.sellerAvatarUrl || !domElements.sellerAvatarInput) return;
    
    domElements.defaultAvatarUsed.value = '1';
    domElements.sellerAvatarUrl.value = DEFAULT_AVATAR_PATH;
    domElements.sellerAvatarInput.value = '';
    
    if (domElements.avatarPreviewImg) domElements.avatarPreviewImg.src = DEFAULT_AVATAR_PATH;
    if (domElements.avatarImageName) domElements.avatarImageName.textContent = '默认头像 (pz-yh.png)';
    if (domElements.avatarPreview) domElements.avatarPreview.classList.add('show');
    if (domElements.sellerAvatarUpload) domElements.sellerAvatarUpload.classList.add('has-file');
    
    showNotification('已选择默认头像', 'success');
}

function removeSellerAvatar() {
    if (domElements.sellerAvatarInput) domElements.sellerAvatarInput.value = '';
    if (domElements.avatarPreview) domElements.avatarPreview.classList.remove('show');
    if (domElements.sellerAvatarUpload) domElements.sellerAvatarUpload.classList.remove('has-file');
    if (domElements.avatarImageName) domElements.avatarImageName.textContent = '';
    if (domElements.defaultAvatarUsed) domElements.defaultAvatarUsed.value = '0';
    if (domElements.sellerAvatarUrl) domElements.sellerAvatarUrl.value = '';
}

// ==================== 随机编号生成 ====================
function generateRandomCode() {
    const letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    let code = '';
    
    code += letters.charAt(Math.floor(Math.random() * letters.length));
    code += Math.floor(Math.random() * 9) + 1;
    
    for (let i = 0; i < 3; i++) {
        code += letters.charAt(Math.floor(Math.random() * letters.length));
    }
    
    code += Math.floor(Math.random() * 9) + 1;
    return code;
}

function handleRandomCode() {
    if (!domElements.groupCode || !domElements.randomCodeBtn) return;
    
    const randomCode = generateRandomCode();
    domElements.groupCode.value = randomCode;
    domElements.groupCode.dispatchEvent(new Event('input'));
    
    updateDuplicateStatus();
    
    const button = domElements.randomCodeBtn;
    button.classList.add('success');
    button.innerHTML = '<i class="bi bi-check-lg"></i> 已生成';
    
    showNotification(`已生成群聊编号: ${randomCode}`);
    
    setTimeout(() => {
        button.classList.remove('success');
        button.innerHTML = '<i class="bi bi-shuffle"></i> 随机编号';
    }, 1000);
}

// ==================== 表单验证 ====================
function validateForm() {
    if (!domElements.traderName || !domElements.groupCode) return false;
    
    const name = domElements.traderName.value.trim();
    if (name === '') {
        showNotification('请输入交易员名称', 'error');
        return false;
    }
    
    const code = domElements.groupCode.value.trim();
    if (code === '') {
        showNotification('请输入群聊编号', 'error');
        return false;
    }
    
    if (updateDuplicateStatus()) {
        showNotification('商品编号已存在，请修改编号！', 'error');
        return false;
    }
    
    if (domElements.sellerAvatarInput && domElements.sellerAvatarInput.files[0] && 
        domElements.sellerAvatarInput.files[0].size > IMAGE_MAX_SIZE) {
        showNotification('卖家头像不能超过2MB', 'error');
        return false;
    }
    
    const isEditMode = domElements.productId ? domElements.productId.value !== '0' : false;
    const hasFile = domElements.sellerAvatarInput ? domElements.sellerAvatarInput.files[0] : false;
    const defaultAvatarUsed = domElements.defaultAvatarUsed ? domElements.defaultAvatarUsed.value === '1' : false;
    const oldAvatar = domElements.oldSellerAvatar ? domElements.oldSellerAvatar.value : '';
    
    if (!isEditMode) {
        if (!hasFile && !defaultAvatarUsed) {
            showNotification('请上传卖家头像或使用默认头像', 'error');
            return false;
        }
    } else {
        if (!hasFile && !defaultAvatarUsed && !oldAvatar) {
            showNotification('请上传卖家头像或使用默认头像', 'error');
            return false;
        }
    }
    
    return true;
}

// ==================== 复制链接功能 ====================
function copyProductLink(url) {
    if (!url) {
        showNotification('链接为空', 'error');
        return;
    }
    
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(url).then(() => {
            showNotification('群聊链接已复制到剪贴板');
        }).catch(err => {
            fallbackCopy(url);
        });
    } else {
        fallbackCopy(url);
    }
}

function fallbackCopy(url) {
    const tempInput = document.createElement('textarea');
    tempInput.style.position = 'fixed';
    tempInput.style.left = '-9999px';
    tempInput.value = url;
    document.body.appendChild(tempInput);
    tempInput.select();
    try {
        const success = document.execCommand('copy');
        if (success) {
            showNotification('群聊链接已复制到剪贴板');
        } else {
            showNotification('复制失败，请手动复制', 'error');
        }
    } catch (err) {
        showNotification('复制失败，请手动复制', 'error');
    }
    document.body.removeChild(tempInput);
}

// ==================== 事件监听器设置 ====================
function setupEventListeners() {
    document.addEventListener('click', function(e) {
        // 编辑按钮
        if (e.target.closest('.btn-edit')) {
            const button = e.target.closest('.btn-edit');
            const rawId = button.getAttribute('data-id');
            
            if (!rawId) {
                showNotification('商品ID为空，请检查数据', 'error');
                return;
            }
            
            const id = parseInt(rawId);
            if (isNaN(id) || id <= 0) {
                showNotification(`商品ID无效: ${rawId}`, 'error');
                return;
            }
            
            openModal('edit', id);
        }
        
        // 删除按钮
        if (e.target.closest('.btn-delete')) {
            const button = e.target.closest('.btn-delete');
            const rawId = button.getAttribute('data-id');
            
            if (!rawId) {
                showNotification('商品ID为空，请检查数据', 'error');
                return;
            }
            
            const id = parseInt(rawId);
            if (isNaN(id) || id <= 0) {
                showNotification(`商品ID无效: ${rawId}`, 'error');
                return;
            }
            
            openConfirmModal(id);
        }
        
        // 分享按钮
        if (e.target.closest('.btn-share')) {
            const button = e.target.closest('.btn-share');
            const rawId = button.getAttribute('data-id');
            
            if (rawId !== null && rawId !== undefined && rawId !== '' && !isNaN(parseInt(rawId)) && parseInt(rawId) > 0) {
                const id = parseInt(rawId);
                currentChatroomId = id;
                openShareModal(id);
            } else {
                showNotification('无效的商品ID: ' + rawId, 'error');
            }
        }
        
        // 分享图按钮
        if (e.target.closest('.btn-share-image')) {
            const button = e.target.closest('.btn-share-image');
            const rawId = button.getAttribute('data-id');
            
            if (rawId !== null && rawId !== undefined && rawId !== '' && !isNaN(parseInt(rawId)) && parseInt(rawId) > 0) {
                const id = parseInt(rawId);
                openShareImageModal(id);
            } else {
                showNotification('无效的商品ID: ' + rawId, 'error');
            }
        }
    });
    
    // 确认删除
    const confirmDeleteBtn = document.getElementById('confirmDelete');
    if (confirmDeleteBtn) {
        confirmDeleteBtn.addEventListener('click', function() {
            const deleteForm = document.getElementById('deleteForm');
            if (deleteForm) {
                deleteForm.submit();
            } else {
                showNotification('删除表单不存在', 'error');
            }
        });
    }
    
    // 分享模态框复制按钮 - 使用事件委托
    document.addEventListener('click', function(e) {
        const copyBtn = e.target.closest('#shareCopyBtn');
        if (copyBtn) {
            if (currentShareUrl) {
                copyProductLink(currentShareUrl);
                closeShareModal();
            } else {
                showNotification('链接尚未生成', 'error');
            }
        }
    });
    
    // 分享模态框分享图按钮
    if (domElements.shareImageBtn) {
        domElements.shareImageBtn.addEventListener('click', function() {
            openShareImageModal();
        });
    }
    
    // 分享模态框刷新链接按钮
    if (domElements.refreshShareLinkBtn) {
        domElements.refreshShareLinkBtn.addEventListener('click', refreshShareLink);
    }
    
    // 分享图模态框返回按钮
    const returnFromImageBtn = document.getElementById('returnFromImageBtn');
    if (returnFromImageBtn) {
        returnFromImageBtn.addEventListener('click', function() {
            closeShareImageModal();
        });
    }
    
    // 分享图模态框保存图片按钮
    const saveShareImageBtn = document.getElementById('saveShareImageBtn');
    if (saveShareImageBtn) {
        saveShareImageBtn.addEventListener('click', saveShareImage);
    }
    
    // 模态框外部点击关闭
    const productModal = document.getElementById('productModal');
    const confirmModal = document.getElementById('confirmModal');
    const shareModal = document.getElementById('shareModal');
    const shareImageModal = document.getElementById('shareImageModal');
    
    const modals = [productModal, confirmModal, shareModal, shareImageModal];
    
    modals.forEach(modal => {
        if (modal) {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    if (this === productModal) closeModal();
                    else if (this === confirmModal) closeConfirmModal();
                    else if (this === shareModal) closeShareModal();
                    else if (this === shareImageModal) closeShareImageModal();
                }
            });
        }
    });
    
    // 键盘事件
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeModal();
            closeConfirmModal();
            closeShareModal();
            closeShareImageModal();
        }
    });
    
    // 图片上传设置
    if (domElements.sellerAvatarInput && domElements.avatarPreview && domElements.avatarPreviewImg) {
        setupImageUpload(
            domElements.sellerAvatarInput,
            domElements.avatarPreview,
            domElements.avatarPreviewImg,
            domElements.avatarImageName,
            domElements.sellerAvatarUpload,
            {
                onChange: function(file) {
                    if (domElements.defaultAvatarUsed) domElements.defaultAvatarUsed.value = '0';
                    if (domElements.sellerAvatarUrl) domElements.sellerAvatarUrl.value = '';
                }
            }
        );
    }
    
    // 拖拽上传
    if (domElements.sellerAvatarUpload && domElements.sellerAvatarInput) {
        setupDragAndDrop(domElements.sellerAvatarUpload, domElements.sellerAvatarInput);
    }
    
    // 默认头像按钮
    const useDefaultAvatarBtn = document.getElementById('useDefaultAvatar');
    if (useDefaultAvatarBtn) {
        useDefaultAvatarBtn.addEventListener('click', handleDefaultAvatar);
    }
    
    // 随机编号按钮
    if (domElements.randomCodeBtn) {
        domElements.randomCodeBtn.addEventListener('click', handleRandomCode);
    }
    
    // 商品编号输入实时验证
    if (domElements.groupCode) {
        domElements.groupCode.addEventListener('input', updateDuplicateStatus);
    }
    
    // 表单提交验证
    if (domElements.productForm) {
        domElements.productForm.addEventListener('submit', function(e) {
            if (!validateForm()) {
                e.preventDefault();
            }
        });
    }
}

// ==================== 初始化 ====================
document.addEventListener('DOMContentLoaded', function() {
    setupEventListeners();
    
    loadAntiRedConfig().then(config => {
        userAntiRedConfig = config;
        if (userAntiRedConfig && userAntiRedConfig.apply_status === 'on' && userAntiRedConfig.applied_domain) {
            updateAntiRedStatus(userAntiRedConfig.applied_domain);
        }
    }).catch(error => {
    });
    
    <?php if (isset($success_message) && !empty($success_message)): ?>
    setTimeout(() => {
        showNotification(<?php echo json_encode($success_message, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT); ?>, 'success');
    }, 300);
    <?php endif; ?>
    
    <?php if (isset($error_message) && !empty($error_message)): ?>
    setTimeout(() => {
        showNotification(<?php echo json_encode($error_message, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT); ?>, 'error');
    }, 300);
    <?php endif; ?>
});
</script>
</body>
</html>