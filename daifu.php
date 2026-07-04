<?php
if (!defined('FROM_ROUTER')) {
    http_response_code(403);
    exit('喜乐科技@x60898');
}
// 引入数据库配置文件
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/dbconfig.php';

// 检查登录状态
checkLogin();

// 获取数据库连接
$conn = getDB();
if (!$conn) {
    die("数据库连接失败");
}

// 获取当前域名和协议
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$base_url = $protocol . $_SERVER['HTTP_HOST'];

// 获取当前用户ID从session中
$user_id = $_SESSION['user_id'];

// 处理表单提交（添加/编辑代付页）
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';
    $id = $_POST['id'] ?? 0;
    
    if ($action === 'save_payment_page') {
        $XEDF_page_title = $conn->real_escape_string($_POST['XEDF_page_title']);
        $XEDF_product_title = $conn->real_escape_string($_POST['XEDF_product_title'] ?? '');
        $XEDF_amount = floatval($_POST['XEDF_amount']);
        $XEDF_api_url = $conn->real_escape_string($_POST['XEDF_api_url']);
        $XEDF_payment_method = $conn->real_escape_string($_POST['XEDF_payment_method']);
        $XEDF_status = $conn->real_escape_string($_POST['XEDF_status']);
        $XEDF_app_name = $conn->real_escape_string($_POST['XEDF_app_name'] ?? '');
        $XEDF_net_name = $conn->real_escape_string($_POST['XEDF_net_name'] ?? '');
        $XEDF_real_name = $conn->real_escape_string($_POST['XEDF_real_name'] ?? '');
        
        // 处理头像上传
        $XEDF_avatar_url = $_POST['current_avatar_url'] ?? '';
        
        // 如果有上传新文件
        if (isset($_FILES['XEDF_avatar_file']) && $_FILES['XEDF_avatar_file']['error'] == 0) {
            $avatar_file = $_FILES['XEDF_avatar_file'];
            
            // 检查文件类型 - 修复：使用多种方法检查文件类型
            $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
            
            // 方法1：先通过扩展名检查
            $file_extension = strtolower(pathinfo($avatar_file['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            
            if (in_array($file_extension, $allowed_extensions)) {
                // 检查文件大小（限制5MB）
                if ($avatar_file['size'] <= 5 * 1024 * 1024) {
                    // 使用getimagesize()检查是否为有效图片
                    $image_info = @getimagesize($avatar_file['tmp_name']);
                    
                    if ($image_info !== false) {
                        // 为用户创建上传目录
                        $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/daifu_avatars/' . $user_id . '/';
                        if (!file_exists($upload_dir)) {
                            mkdir($upload_dir, 0755, true);
                        }
                        
                        // 生成唯一文件名
                        $file_name = 'avatar_' . time() . '_' . uniqid() . '.' . $file_extension;
                        $file_path = $upload_dir . $file_name;
                        
                        // 移动上传的文件
                        if (move_uploaded_file($avatar_file['tmp_name'], $file_path)) {
                            // 生成可访问的URL路径
                            $XEDF_avatar_url = '/uploads/daifu_avatars/' . $user_id . '/' . $file_name;
                            
                            // 如果编辑时已有头像，删除旧文件
                            if (!empty($_POST['current_avatar_url']) && strpos($_POST['current_avatar_url'], '/uploads/daifu_avatars/') === 0) {
                                $old_file_path = $_SERVER['DOCUMENT_ROOT'] . $_POST['current_avatar_url'];
                                if (file_exists($old_file_path)) {
                                    unlink($old_file_path);
                                }
                            }
                        } else {
                            $error_message = "头像上传失败";
                        }
                    } else {
                        $error_message = "上传的文件不是有效的图片";
                    }
                } else {
                    $error_message = "头像文件大小不能超过5MB";
                }
            } else {
                $error_message = "只支持JPG, PNG, GIF格式的图片";
            }
        } elseif (isset($_POST['XEDF_avatar_url'])) {
            // 如果没有上传新文件，但表单中有URL字段，则使用原有URL
            $XEDF_avatar_url = $conn->real_escape_string($_POST['XEDF_avatar_url']);
        }
        
        // 转义头像URL
        $XEDF_avatar_url = $conn->real_escape_string($XEDF_avatar_url);
        
        if ($id > 0) {
            // 编辑现有代付页
            $sql = "UPDATE XEDF_pages SET 
                    XEDF_page_title = ?, 
                    XEDF_product_title = ?,
                    XEDF_amount = ?, 
                    XEDF_api_url = ?, 
                    XEDF_payment_method = ?, 
                    XEDF_status = ?,
                    XEDF_app_name = ?,
                    XEDF_net_name = ?,
                    XEDF_real_name = ?,
                    XEDF_avatar_url = ?
                    WHERE XEDF_id = ? AND XEDF_user_id = ?";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssdsssssssii", $XEDF_page_title, $XEDF_product_title, $XEDF_amount, 
                             $XEDF_api_url, $XEDF_payment_method, $XEDF_status, $XEDF_app_name, 
                             $XEDF_net_name, $XEDF_real_name, $XEDF_avatar_url, $id, $user_id);
        } else {
            // 添加新代付页
            $XEDF_page_code = substr(md5(uniqid(rand(), true)), 0, 10);
            
            $sql = "INSERT INTO XEDF_pages (XEDF_user_id, XEDF_page_title, XEDF_product_title, XEDF_amount, 
                    XEDF_api_url, XEDF_payment_method, XEDF_status, XEDF_page_code,
                    XEDF_app_name, XEDF_net_name, XEDF_real_name, XEDF_avatar_url) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("issdssssssss", $user_id, $XEDF_page_title, $XEDF_product_title, $XEDF_amount, 
                             $XEDF_api_url, $XEDF_payment_method, $XEDF_status, $XEDF_page_code,
                             $XEDF_app_name, $XEDF_net_name, $XEDF_real_name, $XEDF_avatar_url);
        }
        
        if ($stmt->execute()) {
            $success_message = $id > 0 ? "代付页更新成功！" : "代付页添加成功！";
        } else {
            $error_message = "操作失败: " . $stmt->error;
        }
        
        if (isset($stmt)) {
            $stmt->close();
        }
    } elseif ($action === 'delete_payment_page') {
        // 删除代付页
        $id = intval($_POST['id']);
        
        // 先获取头像路径，以便删除文件
        $sql = "SELECT XEDF_avatar_url FROM XEDF_pages WHERE XEDF_id = ? AND XEDF_user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $id, $user_id);
        $stmt->execute();
        $stmt->bind_result($avatar_url);
        $stmt->fetch();
        $stmt->close();
        
        // 如果是本地文件，则删除
        if (!empty($avatar_url) && strpos($avatar_url, '/uploads/daifu_avatars/') === 0) {
            $file_path = $_SERVER['DOCUMENT_ROOT'] . $avatar_url;
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }
        
        $sql = "DELETE FROM XEDF_pages WHERE XEDF_id = ? AND XEDF_user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $id, $user_id);
        
        if ($stmt->execute()) {
            $success_message = "代付页删除成功！";
        } else {
            $error_message = "删除失败: " . $stmt->error;
        }
        
        $stmt->close();
    }
}

// 查询当前用户的所有代付页
$sql = "SELECT * FROM XEDF_pages WHERE XEDF_user_id = ? ORDER BY XEDF_created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$payment_pages = [];

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $payment_pages[] = $row;
    }
}

// 读取源站URL配置
$siteUrlResult = $conn->query("SELECT site_url, site_url_enabled FROM webconfig ORDER BY id DESC LIMIT 1");
$siteUrlConfig = null;
if ($siteUrlResult && $siteUrlResult->num_rows > 0) {
    $siteUrlConfig = $siteUrlResult->fetch_assoc();
    $siteUrlConfig['site_url_enabled'] = !empty($siteUrlConfig['site_url_enabled']);
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
    <link rel="stylesheet" href="/assets/top_bar.css">
    <link rel="stylesheet" href="/assets/bootstrap-icons.css">
    <script src="/assets/html2canvas.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
        }
        
        body {
            background-color: #f0f2f5;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'PingFang SC', 'Microsoft YaHei', sans-serif;
            color: #333;
            line-height: 1.5;
        }
        
        .floating-back-btn {
            position: fixed;
            top: 20px;
            left: 20px;
            background-color: #fff;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            z-index: 100;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .floating-back-btn:hover {
            background-color: #f5f5f5;
            transform: scale(1.05);
        }
        
        .floating-back-btn:active {
            transform: scale(0.95);
        }
        
        .floating-back-btn i {
            font-size: 20px;
            color: #333;
        }
        
        .container {
            max-width: 100%;
            padding: 20px;
        }
        
        .add-btn-container {
            margin-bottom: 20px;
        }
        
        .add-btn {
            background-color: #1890ff;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 14px;
            font-size: 16px;
            font-weight: 500;
            width: 100%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background-color 0.2s ease;
        }
        
        .add-btn:hover {
            background-color: #40a9ff;
        }
        
        .add-btn:active {
            background-color: #096dd9;
        }
        
        .add-btn i {
            margin-right: 8px;
            font-size: 18px;
        }
        
        .payment-list {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        
        .payment-card {
            background-color: #ffffff;
            border-radius: 8px;
            padding: 20px;
            border: 1px solid #e8e8e8;
            animation: cardFadeIn 0.4s ease-out;
            position: relative;
        }
        
        .card-delete-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            background-color: #ff4d4f;
            color: white;
            border: none;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
            z-index: 2;
        }
        
        .card-delete-btn:hover {
            background-color: #ff7875;
            transform: scale(1.1);
        }
        
        .card-delete-btn:active {
            transform: scale(0.95);
        }
        
        .card-delete-btn i {
            font-size: 14px;
        }
        
        @keyframes cardFadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .order-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .order-label {
            font-size: 14px;
            color: #666;
        }
        
        .order-value {
            font-size: 14px;
            color: #333;
            font-weight: 500;
        }
        
        .order-number {
            font-weight: 600;
            color: #333;
        }
        
        .amount-row {
            margin-bottom: 10px;
        }
        
        .amount-label {
            font-size: 14px;
            color: #666;
        }
        
        .amount {
            font-size: 24px;
            font-weight: 700;
            color: #333;
        }
        
        .method-row {
            margin-bottom: 10px;
        }
        
        .method-label {
            font-size: 14px;
            color: #666;
        }
        
        .payment-method-value {
            color: #333;
            font-weight: 500;
        }
        
        .status-row {
            margin-bottom: 20px;
        }
        
        .status-label {
            font-size: 14px;
            color: #666;
        }
        
        .status-value {
            color: #333;
            font-weight: 500;
            align-items: center;
            gap: 5px;
        }
        
        .status-active {
            color: #52c41a;
        }
        
        .status-inactive {
            color: #ff4d4f;
        }
        
        .status-value i {
            font-size: 14px;
        }
        
        .button-group {
            display: flex;
            gap: 10px;
        }
        
        .card-btn {
            flex: 1;
            padding: 10px 0;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .card-btn i {
            margin-right: 5px;
            font-size: 14px;
        }
        
        .btn-update {
            background-color: #27ae60;
            color: white;
        }
        
        .btn-update:hover {
            background-color: #73d13d;
        }
        
        .btn-update:active {
            background-color: #389e0d;
        }
        
        .btn-copy-card {
            background-color: #555;
            color: white;
        }
        
        .btn-copy-card:hover {
            background-color: #9254de;
        }
        
        .btn-copy-card:active {
            background-color: #531dab;
        }
        
        .btn-copy-link {
            background-color: #555;
            color: white;
        }
        
        .btn-copy-link:hover {
            background-color: #9254de;
        }
        
        .btn-copy-link:active {
            background-color: #531dab;
        }
        
          /* 新通知样式 - 从顶部进入 */
        .new-user-toast {
            position: fixed;
            top: 10px;
            left: 10px;
            right: 10px;
            background: #4caf50;
            border-radius: 8px;
            padding: 12px 16px;
            display: flex;
            align-items: center;
            z-index: 99999;
            box-shadow: 0 4px 12px rgba(0,0,0,.15);
            cursor: pointer;
            will-change: transform,opacity;
            backface-visibility: hidden;
            -webkit-backface-visibility: hidden;
            max-width: calc(100% - 20px);
            margin: 0 auto;
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
                transform: translateY(-100px);
                opacity: 0;
            }
            100% {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        @keyframes notification-slide-out {
            0% {
                transform: translateY(0);
                opacity: 1;
            }
            100% {
                transform: translateY(-100px);
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
        
        /* 隐藏状态 */
        .toast-hidden {
            display: none;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            border-radius: 12px;
        }
        
        .empty-icon {
            font-size: 60px;
            color: #d9d9d9;
            margin-bottom: 20px;
        }
        
        .empty-title {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin-bottom: 10px;
        }
        
        .empty-text {
            font-size: 14px;
            color: #666;
            margin-bottom: 30px;
        }
        
        /* 模态框覆盖层 - 改为从底部滑出的抽屉式 */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: flex-end;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .modal-overlay.show {
            display: flex;
            opacity: 1;
            animation: fadeInOverlay 0.3s ease-out;
        }
        
        /* 模态框内容 - 改为从底部滑出的抽屉 */
        .modal-content {
            background-color: white;
            border-radius: 16px 16px 0 0;
            width: 100%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            transform: translateY(100%);
            opacity: 1;
            transition: transform 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            position: relative;
            box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.15);
        }
        
        .modal-overlay.show .modal-content {
            transform: translateY(0);
        }
        
        /* 抽屉顶部把手 */
        .modal-header {
             padding: 10px;
            border-bottom: 1px solid #e8e8e8;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
        }
        
        .modal-title {
            font-size: 18px;
            font-weight: 600;
            color: #333;
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 20px;
            color: #666;
            cursor: pointer;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: background-color 0.2s ease;
        }
        
        .modal-close:hover {
            background-color: #f5f5f5;
        }
        
        .modal-body {
            background: #f5f5f5;
            padding: 20px;
        }
        
        .modal-footer {
            padding: 20px;
            border-top: 1px solid #e8e8e8;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }
        
        .form-input {
            width: 100%;
            padding: 12px;
            border: 1px solid #d9d9d9;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.2s ease;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #1890ff;
            box-shadow: 0 0 0 2px rgba(24, 144, 255, 0.2);
        }
        
        .form-select {
            width: 100%;
            padding: 12px;
            border: 1px solid #d9d9d9;
            border-radius: 6px;
            font-size: 14px;
            background-color: white;
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23333' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 16px;
        }
        
        .form-select:focus {
            outline: none;
            border-color: #1890ff;
            box-shadow: 0 0 0 2px rgba(24, 144, 255, 0.2);
        }
        
        .form-text {
            font-size: 12px;
            color: #999;
            margin-top: 5px;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .btn-secondary {
            background-color: #f5f5f5;
            color: #333;
        }
        
        .btn-secondary:hover {
            background-color: #e8e8e8;
        }
        
        .btn-primary {
            background-color: #1890ff;
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #40a9ff;
        }
        
        .confirm-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .confirm-overlay.show {
            display: flex;
            opacity: 1;
            animation: fadeInOverlay 0.3s ease-out;
        }
        
        .confirm-content {
            background-color: white;
            border-radius: 8px;
            width: 90%;
            max-width: 400px;
            padding: 30px;
            text-align: center;
            transform: translateY(20px);
            opacity: 0;
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        
        .confirm-overlay.show .confirm-content {
            transform: translateY(0);
            opacity: 1;
        }
        
        .confirm-title {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin-bottom: 15px;
        }
        
        .confirm-text {
            font-size: 14px;
            color: #666;
            margin-bottom: 25px;
        }
        
        .confirm-buttons {
            display: flex;
            gap: 10px;
        }
        
        .confirm-btn {
            flex: 1;
            padding: 12px 0;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .confirm-cancel {
            background-color: #f5f5f5;
            color: #333;
        }
        
        .confirm-cancel:hover {
            background-color: #e8e8e8;
        }
        
        .confirm-delete {
            background-color: #ff4d4f;
            color: white;
        }
        
        .confirm-delete:hover {
            background-color: #ff7875;
        }
        
        @keyframes fadeInOverlay {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }
        
        @media (max-width: 480px) {
            .container {
                padding: 15px;
            }
            
            .payment-card {
                padding: 15px;
            }
            
            .button-group {
                flex-direction: row;
            }
            
            .card-btn {
                font-size: 13px;
                padding: 8px 0;
            }
            
            .card-btn i {
                font-size: 12px;
                margin-right: 3px;
            }
            
            .dynamic-island {
                padding: 12px 20px;
                font-size: 14px;
                min-width: 160px;
                max-width: 280px;
            }
            
            .card-delete-btn {
                width: 28px;
                height: 28px;
            }
        }
        
        @media (max-width: 360px) {
            .button-group {
                flex-direction: column;
            }
        }
        
        .daifu-info {
            background-color: #f8f9fa;
            border-radius: 6px;
            padding: 12px;
            margin-bottom: 15px;
        }
        
        .daifu-info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2px;
        }
        
        .daifu-info-label {
            font-size: 13px;
            color: #666;
            min-width: 80px;
        }
        
        .daifu-info-value {
            font-size: 13px;
            color: #333;
            font-weight: 500;
            flex: 1;
            text-align: right;
        }
        
        /* 头像相关样式 */
        .avatar-upload-container {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .avatar-preview {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            overflow: hidden;
            border: 2px solid #e8e8e8;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f8f9fa;
        }
        
        .avatar-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .avatar-upload {
            flex: 1;
        }
        
        .avatar-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f8f9fa;
            color: #999;
            font-size: 12px;
        }
        
        .avatar-card {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            overflow: hidden;
            border: 2px solid #f0f0f0;
            margin-right: 12px;
            flex-shrink: 0;
        }
        
        .avatar-card img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .header-with-avatar {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .avatar-file-input-container {
            position: relative;
        }
        
        .avatar-file-input {
            width: 100%;
            padding: 10px;
            border: 1px solid #d9d9d9;
            border-radius: 6px;
            background-color: #fafafa;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .avatar-file-input:hover {
            background-color: #f0f0f0;
        }
        
        .avatar-file-input input[type="file"] {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }
        
        .avatar-preview-container {
            margin-top: 10px;
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: 6px;
            border: 1px dashed #d9d9d9;
        }
        
        .avatar-preview-label {
            font-size: 12px;
            color: #666;
            margin-bottom: 5px;
            display: block;
        }
        
        .avatar-preview-img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border: 1px solid #eee;
        }
        
        .avatar-remove-btn {
            margin-top: 5px;
            padding: 4px 8px;
            font-size: 12px;
            color: #ff4d4f;
            background-color: #fff;
            border: 1px solid #ffccc7;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .avatar-remove-btn:hover {
            background-color: #fff2f0;
        }
    </style>
    <style>
        /* 添加分享相关样式 */
.share-container {
    position: relative;
    flex: 1;
}

.share-btn {
    background-color: #9254de;
    color: white;
    width: 100%;
}

.share-btn:hover {
    background-color: #722ed1;
}

.share-btn:active {
    background-color: #531dab;
}

.btn-copy-card {
    background-color: #555;
    color: white;
}

.btn-copy-card:hover {
    background-color: #722ed1;
}

.btn-copy-card:active {
    background-color: #531dab;
}

.share-menu {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    z-index: 10;
    background: white;
    border-radius: 6px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    display: none;
    margin-top: 5px;
    overflow: hidden;
    animation: menuFadeIn 0.2s ease-out;
}

@keyframes menuFadeIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.share-option {
    width: 100%;
    padding: 10px;
    border: none;
    background: #f8f9fa;
    text-align: left;
    cursor: pointer;
    transition: background 0.2s;
    font-size: 13px;
    color: #333;
}

.share-option:hover {
    background: #e8e8e8;
}

.share-option i {
    margin-right: 8px;
    font-size: 12px;
}

/* 分享图模态框样式 */
.share-image-preview {
    width: 100%;
    max-width: 420px;
    margin: 0 auto;
    background-color: #f3f4f6;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.share-image-footer {
    margin-top: 20px;
    text-align: center;
    padding: 15px;
    background-color: #f8f9fa;
    border-radius: 8px;
    font-size: 13px;
    color: #666;
}
    </style>
    
    <style>
    /* 删除按钮样式 */
    .card-delete-btn {
        position: absolute;
        top: 12px;
        right: 12px;
        width: 32px;
        height: 32px;
        border-radius: 50%;
        border: none;
        background: rgba(255, 59, 48, 0.1);
        color: #FF3B30;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.2s ease;
        z-index: 2;
    }
    
    .card-delete-btn:hover {
        background: rgba(255, 59, 48, 0.2);
        transform: scale(1.1);
    }
    
    .card-delete-btn:active {
        transform: scale(0.95);
    }
    
    /* 删除确认模态框动画 */
    .modal-overlay#deleteConfirmModal .modal-content {
        opacity: 0;
        transform: scale(0.9) translateY(20px);
        transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }
    
    .modal-overlay#deleteConfirmModal.show .modal-content {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
    
    /* 模态框阴影效果 */
    .modal-overlay#deleteConfirmModal .modal-content {
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2), 0 2px 10px rgba(0, 0, 0, 0.1);
    }
    
    /* 按钮点击效果 */
    #cancelDeleteBtn:active,
    #confirmDeleteBtn:active {
        background-color: #F2F2F7 !important;
    }
    
    /* 卡片悬停时的删除按钮 */
    .payment-card:hover .card-delete-btn {
        opacity: 1;
    }
    
    .payment-card .card-delete-btn {
        opacity: 0.8;
    }
</style>
</head>
<body>
<div class="top-header">
	<a href="javascript:void(0)" onclick="window.parent.postMessage('closeModal', '*')" style="display: inline-flex; align-items: center; text-decoration: none; color: inherit;">
		<svg t="1768667202128" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="4699" width="18" height="18">
			<path
				d="M285.8112 565.76a56.4864 56.4864 0 0 0 39.04-16.3712l452.7744-453.76A56.5248 56.5248 0 0 0 778.24 16.64a54.8992 54.8992 0 0 0-78.08-0.5632L247.3344 469.76a56.5248 56.5248 0 0 0-0.5504 79.0144 50.048 50.048 0 0 0 39.0272 16.9344zM733.568 1024a56.1664 56.1664 0 0 0 39.6032-95.3856l-448.32-458.24a54.912 54.912 0 0 0-78.08-0.5632 56.5248 56.5248 0 0 0-0.5632 79.0144l448.32 458.24A53.76 53.76 0 0 0 733.568 1024z m0 0"
				fill="#333333" p-id="4700"></path>
		</svg>
	</a>
	<div style="border: 14px solid transparent;">代付页管理</div>
</div>

<!-- 防红配置状态显示 -->
<div class="anti-red-status-bar" id="antiRedStatusBar" style="display: none; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 12px 20px; margin: 10px 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
    <div style="display: flex; align-items: center; gap: 10px;">
        <i class="bi bi-shield-check" style="font-size: 20px;"></i>
        <div>
            <div style="font-weight: 600; font-size: 14px;">防红配置已启用</div>
            <div style="font-size: 12px; opacity: 0.9; margin-top: 2px;">
                当前配置接口：<span id="currentAntiRedDomain" style="font-weight: 500;"></span>
            </div>
        </div>
    </div>
</div>


    <!-- 主容器 -->
    <div class="container">
        <!-- 添加按钮 -->
        <div class="add-btn-container">
            <button class="add-btn" onclick="openModal('add')">
                <i class="bi bi-plus-lg"></i> 添加新的代付页
            </button>
        </div>
        
        <!-- 代付页列表 -->
        <?php if (count($payment_pages) > 0): ?>
        <!-- 添加JavaScript数据存储 -->
        <script>
          // 存储所有代付页数据用于编辑时填充表单
var paymentPagesData = {
    <?php foreach ($payment_pages as $page): ?>
    <?php echo $page['XEDF_id']; ?>: {
        title: "<?php echo addslashes($page['XEDF_page_title']); ?>",
        product_title: "<?php echo addslashes($page['XEDF_product_title'] ?? ''); ?>",
        amount: "<?php echo $page['XEDF_amount']; ?>",
        api_url: "<?php echo addslashes($page['XEDF_api_url'] ?? ''); ?>",
        payment_method: "<?php echo $page['XEDF_payment_method']; ?>",
        status: "<?php echo $page['XEDF_status']; ?>",
        app_name: "<?php echo addslashes($page['XEDF_app_name'] ?? ''); ?>",
        net_name: "<?php echo addslashes($page['XEDF_net_name'] ?? ''); ?>",
        real_name: "<?php echo addslashes($page['XEDF_real_name'] ?? ''); ?>",
        avatar_url: "<?php echo addslashes($page['XEDF_avatar_url'] ?? ''); ?>",
        page_code: "<?php echo $page['XEDF_page_code']; ?>"  // 添加这行
    },
    <?php endforeach; ?>
};
        </script>
        
        <div class="payment-list">
            <?php foreach ($payment_pages as $page): 
                $payment_url = $base_url . "/Daifu?code=" . $page['XEDF_page_code'];
                $payment_method_text = "";
                switch($page['XEDF_payment_method']) {
                    case 'alipay': $payment_method_text = "支付宝直连"; break;
                    case 'wechat': $payment_method_text = "微信直连"; break;
                    case 'bank': $payment_method_text = "支付宝扫码"; break;
                    default: $payment_method_text = "微信扫码";
                }
                
                // 处理头像URL，如果是相对路径，添加完整路径
                $avatar_url = $page['XEDF_avatar_url'];
                if (!empty($avatar_url) && strpos($avatar_url, 'http') !== 0) {
                     $avatar_url = $base_url . $avatar_url;
                }
            ?>
            <div class="payment-card" id="payment-card-<?php echo $page['XEDF_id']; ?>" 
                 data-id="<?php echo $page['XEDF_id']; ?>">
                <!-- 卡片右上角删除按钮 -->
                <button class="card-delete-btn" data-id="<?php echo $page['XEDF_id']; ?>">
                    <i class="bi bi-trash"></i>
                </button>
                
                <!-- 头像显示 -->
                <?php if (!empty($page['XEDF_avatar_url'])): ?>
                <div class="header-with-avatar">
                    <div class="avatar-card">
                         <?php
    $avatar_url = $page['XEDF_avatar_url'];
    if (!empty($avatar_url) && strpos($avatar_url, 'http') !== 0) {
        $avatar_url = $base_url . $avatar_url;
    }
    ?>
                        <img src="<?php echo htmlspecialchars($avatar_url); ?>" alt="头像" onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgdmlld0JveD0iMCAwIDEwMCAxMDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIxMDAiIGhlaWdodD0iMTAwIiBmaWxsPSIjZjBmMGYwIi8+CjxwYXRoIGQ9Ik01MCA0MkM1NS41MjI4IDQyIDYwIDM3LjUyMjggNjAgMzJDNjAgMjYuNDc3MiA1NS41MjI4IDIyIDUwIDIyQzQ0LjQ3NzIgMjIgNDAgMjYuNDc3MiA0MCAzMkM0MCAzNy41MjI4IDQ0LjQ3NzIgNDIgNTAgNDJaIiBmaWxsPSIjQ0NDIi8+CjxwYXRoIGQ9Ik01MCA1MEM1Ni4xIDUwIDYyLjUgNTAuMiA2OC4yIDUzLjFDNjkuNiA1My44IDcwIDU1LjQgNjkuMyA1Ni44QzY4LjYgNTguMiA2NyA1OC42IDY1LjYgNTcuOUMyMi43IDc2LjMgMzMuNyA0Ny41IDM0IDQ2LjlDMzQuNSA0NS41IDMzLjQgNDQgMzIgNDRDMzAuNiA0NCAyOS41IDQ1LjUgMzAgNDYuOUMzMC4zIDQ3LjUgNDEuMyA3Ni4zIDM0LjQgNTcuOUMzMy43IDU2LjUgMzQuMSA1NC45IDM1LjUgNTQuMkM0MS4yIDUxLjIgNDcuNiA1MCA1NCA1MEM0Ny42IDUwIDQxLjIgNDguOCAzNS41IDQ1LjhDMzQuMSA0NS4xIDMzLjcgNDMuNSAzNC40IDQyLjFDMzUuMSA0MC43IDM2LjcgNDAuMyAzOC4xIDQxQzQ0LjggNDQgNTEuOSA0NSA1OSA0NUM2NyA0NSA3NS4xIDQzLjkgODEuOSA0MC45QzgzLjMgNDAuMiA4NC45IDQwLjYgODUuNiA0MkM4Ni4zIDQzLjQgODUuOSA0NSA4NC41IDQ1LjdDNzcuNyA0OC44IDY5LjkgNTAuMSA2MiA1MEM2OS45IDUwIDc3LjcgNTEuMiA4NC41IDU0LjNDODUuOSA1NSA4Ni4zIDU2LjYgODUuNiA1OEM4NC45IDU5LjQgODMuMyA1OS44IDgxLjkgNTkuMUM3NS4xIDU2LjEgNjcgNTUgNTkgNTVDNS41IDU1IDM0LjkgNzYuOSA0MCA1Ny4yQzQwLjUgNTUuOCAzOS40IDU0LjIgMzggNTQuMkMzNi42IDU0LjIgMzUuNSA1NS44IDM2IDU3LjJDMzcuMyA2MS4zIDQ0LjMgNzUgNTkgNzVDNzQuMiA3NSA4MC4zIDYwLjIgODEuNyA1Ni4xQzgyLjIgNTQuNyA4MS4xIDUzLjEgNzkuNyA1My4xQzc4LjMgNTMuMSA3Ny4yIDU0LjcgNzcuNyA1Ni4xQzc3LjkgNTcuMSA3NS41IDYyLjIgNzAgNjYuOUM2NC41IDcxLjYgNTcuNCA3NSA1MCA3NUM0Mi42IDc1IDM1LjUgNzEuNiAzMCA2Ni45QzI0LjUgNjIuMiAyMi4xIDU3LjEgMjIuMyA1Ni4xQzIyLjggNTQuNyAyMS43IDUzLjEgMjAuMyA1My4xQzE4LjkgNTMuMSAxNy44IDU0LjcgMTguMyA1Ni4xQzE5LjcgNjAuMiAyNS44IDc1IDQxIDc1QzUzLjcgNzUgNjAuNyA2MS4zIDYyIDU3LjJDNjIuNSA1NS44IDYxLjQgNTQuMiA2MCA1NC4yQzU4LjYgNTQuMiA1Ny41IDU1LjggNTggNTcuMkM1OC4yIDU3LjggNjQuMyA3MC43IDc3LjMgNjUuOEM3OC42IDY1LjMgNzkuMyA2My45IDc4LjggNjIuNkM3OC4zIDYxLjMgNzYuOSA2MC42IDc1LjYgNjEuMUM2NC4zIDY1LjIgNTYuMiA1MCA1MCA1MFoiIGZpbGw9IiNDQ0MiLz4KPC9zdmc+'">
                    </div>
                    <div>
                        <div style="font-size: 16px; font-weight: 600;"><?php echo $page['XEDF_page_title']; ?></div>
                        <?php if (!empty($page['XEDF_product_title'])): ?>
                        <div style="font-size: 13px; color: #666; margin-top: 2px;"><?php echo $page['XEDF_product_title']; ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php else: ?>
                <!-- 如果没有头像，显示原有布局 -->
                <div class="order-row">
                    <div>
                        <span class="order-label">代付标题：</span>
                        <span class="order-value order-number"><?php echo $page['XEDF_page_title']; ?></span>
                    </div>
                </div>
                
                <!-- 商品标题 -->
                <?php if (!empty($page['XEDF_product_title'])): ?>
                <div class="order-row">
                    <div>
                        <span class="order-label">商品标题：</span>
                        <span class="order-value"><?php echo $page['XEDF_product_title']; ?></span>
                    </div>
                </div>
                <?php endif; ?>
                <?php endif; ?>
                
                <!-- 金额 -->
                <div class="amount-row">
                    <span class="amount-label">代付金额：</span>
                    <span class="order-value amount">¥<?php echo number_format($page['XEDF_amount'], 2); ?></span>
                </div>
                
                <!-- 代付信息 -->
                <div class="daifu-info">
                    <div class="daifu-info-row">
                        <span class="daifu-info-label">支付方式：</span>
                        <span class="daifu-info-value"><?php echo $payment_method_text; ?></span>
                    </div>
                    <?php if (!empty($page['XEDF_app_name'])): ?>
                    <div class="daifu-info-row">
                        <span class="daifu-info-label">APP名称：</span>
                        <span class="daifu-info-value"><?php echo $page['XEDF_app_name']; ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($page['XEDF_net_name'])): ?>
                    <div class="daifu-info-row">
                        <span class="daifu-info-label">网名：</span>
                        <span class="daifu-info-value"><?php echo $page['XEDF_net_name']; ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($page['XEDF_real_name'])): ?>
                    <div class="daifu-info-row">
                        <span class="daifu-info-label">真实姓名：</span>
                        <span class="daifu-info-value"><?php echo $page['XEDF_real_name']; ?></span>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- 状态行 -->
                <div class="status-row">
                    <span class="status-label">页面状态：</span>
                    <span class="order-value status-value <?php echo $page['XEDF_status'] === 'active' ? 'status-active' : 'status-inactive'; ?>">
                        <?php 
                        if($page['XEDF_status'] === 'active') {
                            echo '<i class="bi bi-check-circle-fill"></i> 正常';
                        } else {
                            echo '<i class="bi bi-x-circle-fill"></i> 未开启';
                        }
                        ?>
                    </span>
                </div>
            <div class="button-group">
        <button class="card-btn btn-update" 
                data-id="<?php echo $page['XEDF_id']; ?>">
            <i class="bi bi-pencil-square"></i> 更新信息
        </button>
        
        <div class="share-container" style="position: relative; flex: 1;">
            <button class="card-btn btn-share" data-id="<?php echo $page['XEDF_id']; ?>" style="width: 100%;">
                <i class="bi bi-share"></i> 分享
            </button>
            <div class="share-menu" id="share-menu-<?php echo $page['XEDF_id']; ?>" 
                 style="position: absolute; top: 100%; left: 0; right: 0; z-index: 10; background: white; border-radius: 6px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); display: none; margin-top: 5px; overflow: hidden;">
                <button class="share-option" onclick="copyPaymentLink('<?php echo $payment_url; ?>')" 
                        style="width: 100%; padding: 10px; border: none; background: #f8f9fa; text-align: left; cursor: pointer; transition: background 0.2s;">
                    <i class="bi bi-link-45deg" style="margin-right: 8px;"></i> 复制链接
                </button>
                <button class="share-option" onclick="generateShareImage('<?php echo $page['XEDF_id']; ?>')" 
                        style="width: 100%; padding: 10px; border: none; background: #f8f9fa; text-align: left; cursor: pointer; transition: background 0.2s; border-top: 1px solid #eee;">
                    <i class="bi bi-image" style="margin-right: 8px;"></i> 分享图
                </button>
            </div>
            </div>
        </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <!-- 空状态 -->
        <div class="empty-state">
            <div class="empty-icon">
                <i class="bi bi-credit-card"></i>
            </div>
            <h3 class="empty-title">暂无代付页</h3>
            <p class="empty-text">数据为空</p>
          
        </div>
        <?php endif; ?>
    </div>
    
    <!-- 添加/编辑代付页模态框 -->
    <div class="modal-overlay" id="paymentModal">
        <div class="modal-content">
            <form method="POST" action="" id="paymentForm" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">添加代付页</h5>
                    <button type="button" class="modal-close" onclick="closeModal()">×</button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="save_payment_page">
                    <input type="hidden" name="id" id="paymentId" value="0">
                    
                    <!-- 头像上传 -->
                    <div class="form-group">
                        <label class="form-label">头像图片</label>
                        <div class="avatar-file-input-container">
                            <div class="avatar-file-input" id="avatarFileInput">
                                <div style="display: flex; align-items: center; justify-content: center; padding: 8px;">
                                    <i class="bi bi-cloud-upload" style="font-size: 20px; margin-right: 8px; color: #666;"></i>
                                    <span id="avatarFileName">点击上传头像图片</span>
                                </div>
                                <input type="file" id="XEDF_avatar_file" name="XEDF_avatar_file" accept="image/*" style="display: none;">
                            </div>
                        </div>
                        <div class="form-text">支持JPG, PNG, GIF格式，建议尺寸：200×200像素，大小不超过5MB</div>
                        
                        <!-- 头像预览 -->
                        <div id="avatarPreviewContainer" style="margin-top: 10px; display: none;">
                            <div class="avatar-preview-label">当前头像：</div>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <img id="avatarPreview" src="" alt="头像预览" class="avatar-preview-img">
                                <button type="button" class="avatar-remove-btn" onclick="removeAvatar()">移除头像</button>
                            </div>
                        </div>
                        
                        <!-- 隐藏字段，用于存储现有的头像URL -->
                        <input type="hidden" id="current_avatar_url" name="current_avatar_url" value="">
                    </div>
                    
                    <!-- 核心字段 -->
                    <div class="form-group">
                        <label for="XEDF_page_title" class="form-label">代付页标题 <span style="color: #ff4d4f;">*</span></label>
                        <input type="text" class="form-input" id="XEDF_page_title" name="XEDF_page_title" required placeholder="例如：VIP会员充值">
                    </div>
                    
                    <div class="form-group">
                        <label for="XEDF_product_title" class="form-label">商品标题 <span style="color: #ff4d4f;">*</span></label>
                        <input type="text" class="form-input" id="XEDF_product_title" name="XEDF_product_title" required placeholder="例如：年度VIP会员服务">
                    </div>
                    
                    <div class="form-group">
                        <label for="XEDF_amount" class="form-label">代付金额 (¥) <span style="color: #ff4d4f;">*</span></label>
                        <input type="number" class="form-input" id="XEDF_amount" name="XEDF_amount" step="0.01" min="0.01" required placeholder="0.00">
                    </div>
                    
                    <div class="form-group">
                        <label for="XEDF_status" class="form-label">页面状态 <span style="color: #ff4d4f;">*</span></label>
                        <select class="form-select" id="XEDF_status" name="XEDF_status" required>
                            <option value="active">开启</option>
                            <option value="inactive">关闭</option>
                        </select>
                        <div class="form-text">创建后可随时修改状态</div>
                    </div>
                    
                    <!-- 扩展字段 -->
                    <div id="additionalFields" style="display: none;">
                        <div class="form-group">
                            <label for="XEDF_api_url" class="form-label">支付接口地址</label>
                            <input type="url" class="form-input" id="XEDF_api_url" name="XEDF_api_url" placeholder="https://api.example.com/payment">
                        </div>
                        
                        <div class="form-group">
                            <label for="XEDF_payment_method" class="form-label">支付方式</label>
                            <select class="form-select" id="XEDF_payment_method" name="XEDF_payment_method">
                                <option value="alipay">支付宝直连</option>
                                <option value="wechat">微信直连</option>
                                <option value="bank">支付宝扫码</option>
                                <option value="other">微信扫码</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="XEDF_app_name" class="form-label">APP名称</label>
                            <input type="text" class="form-input" id="XEDF_app_name" name="XEDF_app_name" placeholder="例如：超级会员APP">
                        </div>
                        
                        <div class="form-group">
                            <label for="XEDF_net_name" class="form-label">网名</label>
                            <input type="text" class="form-input" id="XEDF_net_name" name="XEDF_net_name" placeholder="例如：网络小达人">
                        </div>
                        
                        <div class="form-group">
                            <label for="XEDF_real_name" class="form-label">真实姓名</label>
                            <input type="text" class="form-input" id="XEDF_real_name" name="XEDF_real_name" placeholder="例如：张伟">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">取消</button>
                    <button type="submit" class="btn btn-primary" id="submitBtn">添加代付页</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- 添加分享图模态框 -->
<div class="modal-overlay" id="shareImageModal">
    <div class="modal-content" style="max-width: 450px; max-height: 90vh; overflow-y: auto;">
        <div class="modal-header">
            <h5 class="modal-title">分享图预览</h5>
            <button type="button" class="modal-close" onclick="closeShareImageModal()">×</button>
        </div>
        <div class="modal-body">
            <div id="shareImageContent" style="width: 100%; overflow: hidden;"></div>
            
            <div style="margin-top: 20px; display: flex; flex-direction: column; gap: 10px;">
         
                
                <button class="card-btn" onclick="saveShareImage()" style="background-color: #27ae60; color: white; margin-top: 10px;">
                    <i class="bi bi-download"></i> 保存图片
                </button>
            </div>
        </div>
    </div>
</div>
    
    <!-- 删除确认表单 -->
    <form id="deleteForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="delete_payment_page">
        <input type="hidden" name="id" id="deleteId" value="">
    </form>
    
    <!-- 通知提示 -->
    <div id="notification" class="new-user-toast toast-hidden"></div>
    
    <div class="modal-overlay" id="deleteConfirmModal">
    <div class="modal-content" style="overflow: hidden;">
        <div class="modal-body" style="padding: 0;">
            <!-- 顶部标题区域 -->
            <div style="padding: 20px 16px 0; text-align: center;">
                <div style="width: 60px; height: 60px; margin: 0 auto 16px; background: linear-gradient(135deg, #FF3B30, #FF6B6B); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                    <i class="bi bi-exclamation-triangle" style="font-size: 28px; color: white;"></i>
                </div>
                <h3 style="margin: 0 0 8px; font-size: 18px; font-weight: 600; color: #1D1D1F;">删除代付页</h3>
                <p style="margin: 0; font-size: 14px; line-height: 1.4; color: #86868B;">删除后代付页将无法恢复，确认要删除吗？</p>
            </div>
            
            <!-- 分隔线 -->
            <div style="height: 1px; background-color: #F2F2F7; margin: 20px 0;"></div>
            
            <!-- 按钮区域 -->
            <div style="display: flex; border-top: 1px solid #F2F2F7;">
                <button id="cancelDeleteBtn" 
                        style="flex: 1; padding: 16px; background: none; border: none; border-right: 1px solid #F2F2F7; font-size: 16px; font-weight: 600; color: #007AFF; cursor: pointer; transition: background-color 0.2s;"
                        onmouseover="this.style.backgroundColor='#F9F9F9'"
                        onmouseout="this.style.backgroundColor='transparent'">
                    取消
                </button>
                <button id="confirmDeleteBtn" 
                        style="flex: 1; padding: 16px; background: none; border: none; font-size: 16px; font-weight: 600; color: #FF3B30; cursor: pointer; transition: background-color 0.2s;"
                        onmouseover="this.style.backgroundColor='#F9F9F9'"
                        onmouseout="this.style.backgroundColor='transparent'">
                    删除
                </button>
            </div>
        </div>
    </div>
</div>
    
<script>
    // 防红配置变量
    let userAntiRedConfig = null;
    // 源站URL配置
    let siteUrlConfig = <?php echo $siteUrlConfig ? json_encode($siteUrlConfig) : 'null'; ?>;
    
    // 加载防红配置
    async function loadAntiRedConfig() {
        try {
            const response = await fetch('/config/domain_api.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({action: 'get_user_anti_red_config'})
            });
            
            const result = await response.json();
            
            if (result.success && result.config) {
                userAntiRedConfig = result.config;
                
                // 显示防红状态栏
                const statusBar = document.getElementById('antiRedStatusBar');
                const domainSpan = document.getElementById('currentAntiRedDomain');
                
                if (userAntiRedConfig.apply_status === 'on' && userAntiRedConfig.applied_domain) {
                    domainSpan.textContent = userAntiRedConfig.applied_domain;
                    statusBar.style.display = 'block';
                } else {
                    statusBar.style.display = 'none';
                }
                
                return userAntiRedConfig;
            }
        } catch (error) {
            console.error('加载防红配置失败:', error);
        }
        return null;
    }
    
    // 生成最终链接（防红 > 源站URL > 原始URL）
    function generateFinalUrl(originalUrl) {
        if (userAntiRedConfig && userAntiRedConfig.apply_status === 'on' && userAntiRedConfig.api_url) {
            try {
                let apiUrl = userAntiRedConfig.api_url.replace(/\/+$/, '');
                const encodedUrl = btoa(originalUrl);
                return apiUrl + encodedUrl;
            } catch (error) {
                console.error('生成防红链接失败:', error);
                return originalUrl;
            }
        } else if (siteUrlConfig && siteUrlConfig.site_url_enabled && siteUrlConfig.site_url) {
            try {
                return siteUrlConfig.site_url + btoa(unescape(encodeURIComponent(originalUrl)));
            } catch (error) {
                console.error('生成源站链接失败:', error);
                return originalUrl;
            }
        }
        return originalUrl;
    }
    
    // 打开模态框
    function openModal(mode = 'add', id = null) {
        const modal = document.getElementById('paymentModal');
        const modalTitle = document.getElementById('modalTitle');
        const submitBtn = document.getElementById('submitBtn');
        const additionalFields = document.getElementById('additionalFields');
        const avatarFileName = document.getElementById('avatarFileName');
        const avatarPreviewContainer = document.getElementById('avatarPreviewContainer');
        const avatarPreview = document.getElementById('avatarPreview');
        const currentAvatarUrlInput = document.getElementById('current_avatar_url');
        const avatarFileInput = document.getElementById('XEDF_avatar_file');
        
        // 重置表单和预览
        document.getElementById('paymentForm').reset();
        avatarPreviewContainer.style.display = 'none';
        avatarFileName.textContent = '点击上传头像图片';
        
        if (mode === 'add') {
            modalTitle.textContent = '添加代付页';
            submitBtn.textContent = '添加代付页';
            additionalFields.style.display = 'none';
            document.getElementById('paymentId').value = '0';
            currentAvatarUrlInput.value = '';
        } else if (mode === 'edit' && id) {
            modalTitle.textContent = '编辑代付页';
            submitBtn.textContent = '更新代付页';
            additionalFields.style.display = 'block';
            
            // 从JavaScript对象中获取数据
            if (window.paymentPagesData && window.paymentPagesData[id]) {
                const pageData = window.paymentPagesData[id];
                document.getElementById('paymentId').value = id;
                document.getElementById('XEDF_page_title').value = pageData.title || '';
                document.getElementById('XEDF_product_title').value = pageData.product_title || '';
                document.getElementById('XEDF_amount').value = pageData.amount || '';
                document.getElementById('XEDF_api_url').value = pageData.api_url || '';
                document.getElementById('XEDF_payment_method').value = pageData.payment_method || 'alipay';
                document.getElementById('XEDF_status').value = pageData.status || 'active';
                document.getElementById('XEDF_app_name').value = pageData.app_name || '';
                document.getElementById('XEDF_net_name').value = pageData.net_name || '';
                document.getElementById('XEDF_real_name').value = pageData.real_name || '';
                
                // 处理头像
                if (pageData.avatar_url) {
                    currentAvatarUrlInput.value = pageData.avatar_url;
                    
                    // 显示现有头像预览
                    if (pageData.avatar_url.startsWith('/uploads/')) {
                        avatarPreview.src = window.location.origin + pageData.avatar_url;
                    } else {
                        avatarPreview.src = pageData.avatar_url;
                    }
                    avatarPreviewContainer.style.display = 'block';
                    avatarFileName.textContent = '已上传头像，点击更换';
                }
            } else {
                console.error('未找到代付页数据，ID:', id);
                showNotification('加载代付页数据失败', 'warning');
            }
        }
        
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
    }
    
    // 关闭模态框
    function closeModal() {
        const modal = document.getElementById('paymentModal');
        modal.classList.remove('show');
        document.body.style.overflow = 'auto';
    }
    
    // 头像文件选择
    document.getElementById('avatarFileInput').addEventListener('click', function() {
        document.getElementById('XEDF_avatar_file').click();
    });
    
    document.getElementById('XEDF_avatar_file').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            // 检查文件类型
            const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
            if (!allowedTypes.includes(file.type)) {
                showNotification('只支持JPG, PNG, GIF格式的图片', 'warning');
                this.value = '';
                return;
            }
            
            // 检查文件大小（5MB）
            if (file.size > 5 * 1024 * 1024) {
                showNotification('图片大小不能超过5MB', 'warning');
                this.value = '';
                return;
            }
            
            // 显示文件名
            document.getElementById('avatarFileName').textContent = file.name;
            
            // 显示预览
            const reader = new FileReader();
            reader.onload = function(e) {
                const avatarPreview = document.getElementById('avatarPreview');
                const avatarPreviewContainer = document.getElementById('avatarPreviewContainer');
                avatarPreview.src = e.target.result;
                avatarPreviewContainer.style.display = 'block';
            }
            reader.readAsDataURL(file);
        }
    });
    
    // 移除头像
    function removeAvatar() {
        document.getElementById('XEDF_avatar_file').value = '';
        document.getElementById('avatarPreview').src = '';
        document.getElementById('avatarPreviewContainer').style.display = 'none';
        document.getElementById('current_avatar_url').value = '';
        document.getElementById('avatarFileName').textContent = '点击上传头像图片';
    }
    
 // 显示通知函数 - 使用新样式
    function showNotification(message, type = 'info', duration = 5000) {
        const notification = document.getElementById('notification');
        
        // 根据类型设置图标
        let icon = '✓';
        switch(type) {
            case 'success':
                icon = '✓';
                break;
            case 'warning':
                icon = '⚠';
                break;
            case 'error':
                icon = '✕';
                break;
            case 'info':
            default:
                icon = 'ℹ';
                break;
        }
        
        // 设置通知内容
        notification.innerHTML = `
            <div class="toast-icon">${icon}</div>
            <div class="toast-text">${message}</div>
            <button class="toast-close" onclick="closeNotification()">×</button>
        `;
        
        // 设置类型
        notification.className = `new-user-toast ${type}`;
        
        // 显示并添加进入动画
        notification.classList.remove('toast-hidden');
        notification.classList.add('notification-enter-active');
        
        // 自动关闭
        if (duration > 0) {
            setTimeout(() => {
                closeNotification();
            }, duration);
        }
    }
    
    // 关闭通知
    function closeNotification() {
        const notification = document.getElementById('notification');
        notification.classList.remove('notification-enter-active');
        notification.classList.add('notification-leave-active');
        
        setTimeout(() => {
            notification.classList.add('toast-hidden');
            notification.classList.remove('notification-leave-active');
        }, 300);
    }
    
   // 复制付款链接 - 支持防红和源站URL
    function copyPaymentLink(url) {
        // 生成最终链接（防红 > 源站URL > 原始URL）
        const finalUrl = generateFinalUrl(url);
        console.log('复制链接:', finalUrl);
        
        // 使用现代 API 复制
        if (navigator.clipboard) {
            navigator.clipboard.writeText(finalUrl).then(() => {
                console.log('复制成功');
                showNotification('付款链接已复制', 'success');
            }).catch(err => {
                console.error('复制失败:', err);
                // 降级方案
                fallbackCopy(finalUrl);
            });
        } else {
            // 降级方案
            fallbackCopy(finalUrl);
        }
    }
    
    // 降级复制方法
    function fallbackCopy(text) {
        const tempInput = document.createElement('input');
        tempInput.value = text;
        tempInput.style.position = 'fixed';
        tempInput.style.opacity = '0';
        tempInput.style.left = '-9999px';
        document.body.appendChild(tempInput);
        tempInput.select();
        
        try {
            document.execCommand('copy');
            showNotification('付款链接已复制', 'success');
        } catch (err) {
            console.error('降级复制失败:', err);
            showNotification('复制失败，请手动复制', 'warning');
        }
        
        document.body.removeChild(tempInput);
    }
    // 生成分享图并在模态框中显示
    function generateShareImage(pageId) {
        // 关闭分享菜单
        const shareMenu = document.getElementById(`share-menu-${pageId}`);
        if (shareMenu) {
            shareMenu.style.display = 'none';
        }
        
        // 获取页面数据
        if (!window.paymentPagesData || !window.paymentPagesData[pageId]) {
            showNotification('未找到页面数据', 'warning');
            return;
        }
        
        const pageData = window.paymentPagesData[pageId];
        const pageCode = pageData.page_code || '';
        // 使用当前域名生成支付链接
        const currentDomain = window.location.origin;
        const originalPaymentUrl = `${currentDomain}/Daifu?code=${pageCode}`;
        
        // 生成最终链接（防红 > 源站URL > 原始URL）
        const paymentUrl = generateFinalUrl(originalPaymentUrl);
        
        // 生成二维码 URL
        const qrCodeUrl = `https://api.qrserver.com/v1/create-qr-code/?size=240x240&data=${encodeURIComponent(paymentUrl)}`;
        
        // 获取头像URL
        let avatarUrl = pageData.avatar_url || '';
        if (avatarUrl && !avatarUrl.startsWith('http')) {
            avatarUrl = currentDomain + avatarUrl;
        }
        
        if (avatarUrl && !avatarUrl.startsWith('http')) {
            avatarUrl = window.location.protocol + '//' + window.location.host + avatarUrl;
        }
        
        // 创建分享图HTML
        const shareImageHTML = `
    <div style="width: 100%; max-width: 420px; margin: 0 auto; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
        <!-- 用户消息卡片 -->
        <div style="display: flex; align-items: center; gap: 0.75rem; background: linear-gradient(90deg, #eb8900 0%, #d31212 100%); padding: 0.75rem; border-top-left-radius: 8px; border-top-right-radius: 8px;">
            <img src="${avatarUrl || 'https://images.unsplash.com/photo-1761039265667-7758b00d0bfe?crop=entropy&cs=tinysrgb&fit=crop&fm=jpg&ixid=M3w3MjkzNDZ8MHwxfHNlYXJjaHwxfHxwZXJzb24lMjBob2xkaW5nJTIwY2F0fGVufDB8fHx8MTc2OTc2MTc0MXww&ixlib=rb-4.1.0&q=80&w=50&h=50'}" 
                 alt="用户头像" 
                 style="width: 50px; height: 50px; border-radius: 0.375rem; object-fit: cover; flex-shrink: 0;">
            <p style="font-size: 1rem; color: #fff; line-height: 1.5; margin: 0;">${pageData.net_name || '用户'}(${pageData.real_name || '**安'}): 我有一笔订单需要支付，请帮我代付吧，非常感谢你~</p>
        </div>
        
        <!-- 支付信息 -->
        <div style="background-color: #fff; padding: 1.5rem; padding-bottom: 2rem; text-align: center; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); border-bottom-left-radius: 8px; border-bottom-right-radius: 8px;">
            <!-- 金额显示 -->
            <div style="display: flex; align-items: baseline; justify-content: center; margin: 0.75rem 0;">
                <span style="font-size: 28px; font-weight: 600; color: #000;">¥</span>
                <span style="font-size: 32px; font-weight: 700; color: #000; letter-spacing: -0.025em; margin-left: 0.25rem;">${parseFloat(pageData.amount).toFixed(2)}</span>
            </div>
            
            <!-- 支付信息 -->
            <p style="font-size: 0.875rem; color: #9ca3af; line-height: 1.25rem; margin: 0;">即时到账--商户单号</p>
            <p style="font-size: 0.875rem; color: #6b7280; line-height: 1.25rem; margin-top: 0.25rem; margin-bottom: 1.5rem;">
                XP${pageCode.substring(0, 20).toUpperCase()}${Date.now().toString().slice(-6)}
            </p>
            
            <!-- 二维码 -->
            <div style="position: relative; display: inline-block;">
                <img src="${qrCodeUrl}" 
                     alt="支付二维码" 
                     style="display: block; width: 240px; height: 240px; border-radius: 0.5rem;">
            </div>
            
            <!-- 支付说明 -->
            <p style="font-size: 0.875rem; line-height: 1.625; color: #6b7280; margin-top: 1.5rem;">
                使用支付宝扫描二维码为我付款
            </p>
        </div>
    </div>`;
        
        // 将分享图HTML插入到模态框
        const shareImageContent = document.getElementById('shareImageContent');
        if (shareImageContent) {
            shareImageContent.innerHTML = shareImageHTML;
            
            // 打开分享图模态框
            openShareImageModal();
        }
    }

    // 打开分享图模态框
    function openShareImageModal() {
        const modal = document.getElementById('shareImageModal');
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    // 关闭分享图模态框
    function closeShareImageModal() {
        const modal = document.getElementById('shareImageModal');
        modal.classList.remove('show');
        document.body.style.overflow = 'auto';
    }

    // 保存分享图
    function saveShareImage() {
        const shareImageContent = document.getElementById('shareImageContent');
        if (!shareImageContent) return;
        
        // 创建一个canvas元素
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        
        // 获取分享图的尺寸
        const shareDiv = shareImageContent.firstElementChild;
        const width = shareDiv.offsetWidth;
        const height = shareDiv.offsetHeight;
        
        // 设置canvas尺寸
        canvas.width = width;
        canvas.height = height;
        
        // 设置背景色
        ctx.fillStyle = '#f3f4f6';
        ctx.fillRect(0, 0, width, height);
        
        // 使用html2canvas将div转换为图片
        if (typeof html2canvas !== 'undefined') {
            html2canvas(shareDiv, {
                scale: 2, // 提高分辨率
                useCORS: true, // 允许跨域图片
                backgroundColor: '#f3f4f6'
            }).then(canvas => {
                // 将canvas转换为图片URL
                const imgData = canvas.toDataURL('image/png');
                
                // 创建下载链接
                const link = document.createElement('a');
                link.download = 'XFDF.png';
                link.href = imgData;
                
                // 触发下载
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                
                showNotification('图片已保存', 'success');
            }).catch(error => {
                console.error('生成图片失败:', error);
                showNotification('生成图片失败，请使用浏览器截图功能', 'warning');
            });
        } else {
            // 如果没有html2canvas，提示用户截图
            showNotification('请使用浏览器截图功能保存图片', 'info');
        }
    }
    
    // 当前要删除的项目ID
    let currentDeleteId = null;
    
    // 显示删除确认模态框
    function showDeleteConfirm(id) {
        currentDeleteId = id;
        const deleteModal = document.getElementById('deleteConfirmModal');
        deleteModal.classList.add('show');
        document.body.style.overflow = 'hidden';
    }
    
    // 隐藏删除确认模态框
    function hideDeleteConfirm() {
        const deleteModal = document.getElementById('deleteConfirmModal');
        deleteModal.classList.remove('show');
        document.body.style.overflow = 'auto';
        currentDeleteId = null;
    }
    
    // 确认删除
    function confirmDelete() {
        if (currentDeleteId) {
            document.getElementById('deleteId').value = currentDeleteId;
            document.getElementById('deleteForm').submit();
        }
        hideDeleteConfirm();
    }
    
    // 页面加载完成后的初始化
        document.addEventListener('DOMContentLoaded', function() {
            // 加载防红配置
            loadAntiRedConfig();
            
            // 为所有"更新信息"按钮添加事件监听
        document.querySelectorAll('.btn-update').forEach(button => {
            button.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                openModal('edit', id);
            });
        });
        
        // 为所有删除按钮添加事件监听 - 使用新的确认模态框
        document.querySelectorAll('.card-delete-btn').forEach(button => {
            button.addEventListener('click', function(e) {
                e.stopPropagation(); // 防止事件冒泡
                const id = this.getAttribute('data-id');
                showDeleteConfirm(id);
            });
        });
        
        // 表单验证
        document.getElementById('paymentForm').addEventListener('submit', function(e) {
            const amount = parseFloat(document.getElementById('XEDF_amount').value);
            if (isNaN(amount) || amount <= 0) {
                e.preventDefault();
                showNotification('代付金额必须大于0', 'info');
                return false;
            }
            
            const title = document.getElementById('XEDF_page_title').value.trim();
            if (title === '') {
                e.preventDefault();
                showNotification('请输入代付页标题', 'info');
                return false;
            }
            
            const productTitle = document.getElementById('XEDF_product_title').value.trim();
            if (productTitle === '') {
                e.preventDefault();
                showNotification('请输入商品标题', 'info');
                return false;
            }
        });
        
        // 点击模态框外部关闭
        document.getElementById('paymentModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
        
        // 分享图模态框点击外部关闭
        document.getElementById('shareImageModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeShareImageModal();
            }
        });
        
        // 删除确认模态框事件监听
        const deleteModal = document.getElementById('deleteConfirmModal');
        const cancelDeleteBtn = document.getElementById('cancelDeleteBtn');
        const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
        
        // 点击删除确认模态框外部关闭
        deleteModal.addEventListener('click', function(e) {
            if (e.target === this) {
                hideDeleteConfirm();
            }
        });
        
        // 取消删除按钮
        cancelDeleteBtn.addEventListener('click', hideDeleteConfirm);
        
        // 确认删除按钮
        confirmDeleteBtn.addEventListener('click', confirmDelete);
        
        // 键盘事件
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
                closeShareImageModal();
                if (deleteModal.classList.contains('show')) {
                    hideDeleteConfirm();
                }
            }
        });
        
        // 为所有分享按钮添加事件监听
        document.querySelectorAll('.btn-share').forEach(button => {
            button.addEventListener('click', function(e) {
                e.stopPropagation(); // 阻止事件冒泡
                
                const shareMenu = this.nextElementSibling;
                if (shareMenu && shareMenu.classList.contains('share-menu')) {
                    // 先关闭所有其他分享菜单
                    document.querySelectorAll('.share-menu').forEach(menu => {
                        if (menu !== shareMenu) {
                            menu.style.display = 'none';
                        }
                    });
                    
                    // 切换当前菜单
                    if (shareMenu.style.display === 'block') {
                        shareMenu.style.display = 'none';
                    } else {
                        shareMenu.style.display = 'block';
                    }
                }
            });
        });
        
        // 点击页面其他地方时关闭所有分享菜单
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.share-container')) {
                document.querySelectorAll('.share-menu').forEach(menu => {
                    menu.style.display = 'none';
                });
            }
        });
        
        // 页面加载时检查是否有消息需要显示
        <?php if (isset($success_message)): ?>
        setTimeout(() => {
            showNotification('<?php echo $success_message; ?>', 'success');
        }, 300);
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
        setTimeout(() => {
            showNotification('<?php echo $error_message; ?>', 'warning');
        }, 300);
        <?php endif; ?>
    });
</script>
</body>
</html>