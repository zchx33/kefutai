<?php
if (!defined('FROM_ROUTER')) {
    http_response_code(403);
    exit('喜乐科技@x60898');
}
// 引入数据库配置文件
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/dbconfig.php';
checkLogin();

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

// 处理表单提交（添加/编辑商品）
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';
    $id = $_POST['id'] ?? 0;
    
    if ($action === 'save_product') {
        $XEpzds_product_name = $conn->real_escape_string($_POST['XEpzds_product_name']);
        $XEpzds_product_code = $conn->real_escape_string($_POST['XEpzds_product_code'] ?? '');
        $XEpzds_product_amount = floatval($_POST['XEpzds_product_amount']);
        $XEpzds_compensation_type = $conn->real_escape_string($_POST['XEpzds_compensation_type']);
        $XEpzds_page_status = $conn->real_escape_string($_POST['XEpzds_page_status']);
        
        // 初始化图片路径变量
        $XEpzds_product_image = $_POST['old_product_image'] ?? '';
        $XEpzds_seller_avatar = $_POST['old_seller_avatar'] ?? '';
        
        // 处理商品图片上传
        if (isset($_FILES['XEpzds_product_image']) && $_FILES['XEpzds_product_image']['error'] == 0) {
            $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/chatroom/';
            
            // 如果目录不存在，则创建
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            // 生成唯一文件名
            $fileExt = pathinfo($_FILES['XEpzds_product_image']['name'], PATHINFO_EXTENSION);
            $fileName = 'product_' . time() . '_' . uniqid() . '.' . $fileExt;
            $uploadFile = $uploadDir . $fileName;
            
            // 检查文件类型
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $fileType = $_FILES['XEpzds_product_image']['type'];
            
            if (in_array($fileType, $allowedTypes)) {
                if (move_uploaded_file($_FILES['XEpzds_product_image']['tmp_name'], $uploadFile)) {
                    $XEpzds_product_image = '/uploads/chatroom/' . $fileName;
                }
            }
        }
        
        // 处理卖家头像上传 - 修改后的逻辑
        $defaultAvatarUsed = isset($_POST['default_avatar_used']) ? intval($_POST['default_avatar_used']) : 0;
        $sellerAvatarUrl = isset($_POST['seller_avatar_url']) ? $conn->real_escape_string($_POST['seller_avatar_url']) : '';
        
        // 如果用户选择了使用默认头像
        if ($defaultAvatarUsed == 1 && !empty($sellerAvatarUrl)) {
            $XEpzds_seller_avatar = $sellerAvatarUrl;
        } 
        // 如果有上传新的头像文件
        else if (isset($_FILES['XEpzds_seller_avatar']) && $_FILES['XEpzds_seller_avatar']['error'] == 0) {
            $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/chatroom/';
            
            // 如果目录不存在，则创建
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            // 生成唯一文件名
            $fileExt = pathinfo($_FILES['XEpzds_seller_avatar']['name'], PATHINFO_EXTENSION);
            $fileName = 'avatar_' . time() . '_' . uniqid() . '.' . $fileExt;
            $uploadFile = $uploadDir . $fileName;
            
            // 检查文件类型
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $fileType = $_FILES['XEpzds_seller_avatar']['type'];
            
            if (in_array($fileType, $allowedTypes)) {
                if (move_uploaded_file($_FILES['XEpzds_seller_avatar']['tmp_name'], $uploadFile)) {
                    $XEpzds_seller_avatar = '/uploads/chatroom/' . $fileName;
                }
            }
        }
        // 如果是编辑模式但没有上传新头像也没有选择默认头像，则保持原头像
        else if ($id > 0) {
            // 这里保持原来的 $XEpzds_seller_avatar 值（从 old_seller_avatar 获取）
            $XEpzds_seller_avatar = $_POST['old_seller_avatar'] ?? '';
        }
        // 如果是添加模式但没有头像，可以设置为空或默认值
        else {
            $XEpzds_seller_avatar = ''; // 或者可以设置一个默认头像路径
        }
        
        // 转义图片路径
        $XEpzds_product_image = $conn->real_escape_string($XEpzds_product_image);
        $XEpzds_seller_avatar = $conn->real_escape_string($XEpzds_seller_avatar);
        
        if ($id > 0) {
            // 编辑现有商品
            $sql = "UPDATE XEpzds SET 
                    XEpzds_product_name = ?, 
                    XEpzds_product_code = ?,
                    XEpzds_product_amount = ?, 
                    XEpzds_compensation_type = ?, 
                    XEpzds_page_status = ?,
                    XEpzds_product_image = ?,
                    XEpzds_seller_avatar = ?
                    WHERE XEpzds_id = ? AND XEpzds_user_id = ?";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssdssssii", $XEpzds_product_name, $XEpzds_product_code, $XEpzds_product_amount, 
                             $XEpzds_compensation_type, $XEpzds_page_status, $XEpzds_product_image, 
                             $XEpzds_seller_avatar, $id, $user_id);
        } else {
            // 添加新商品
            $XEpzds_page_code = substr(md5(uniqid(rand(), true)), 0, 10);
            
            $sql = "INSERT INTO XEpzds (XEpzds_user_id, XEpzds_product_name, XEpzds_product_code, XEpzds_product_amount, 
                    XEpzds_compensation_type, XEpzds_page_status, XEpzds_page_code,
                    XEpzds_product_image, XEpzds_seller_avatar) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("issdsssss", $user_id, $XEpzds_product_name, $XEpzds_product_code, $XEpzds_product_amount, 
                             $XEpzds_compensation_type, $XEpzds_page_status, $XEpzds_page_code,
                             $XEpzds_product_image, $XEpzds_seller_avatar);
        }
        
        if ($stmt->execute()) {
            $success_message = $id > 0 ? "更新成功！" : "添加成功！";
        } else {
            $error_message = "操作失败: " . $stmt->error;
        }
        
        if (isset($stmt)) {
            $stmt->close();
        }
    } elseif ($action === 'delete_product') {
        // 删除商品
        $id = intval($_POST['id']);
        
        $sql = "DELETE FROM XEpzds WHERE XEpzds_id = ? AND XEpzds_user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $id, $user_id);
        
        if ($stmt->execute()) {
            $success_message = "删除成功！";
        } else {
            $error_message = "删除失败: " . $stmt->error;
        }
        
        $stmt->close();
    }
}

// 查询当前用户的所有商品
$sql = "SELECT * FROM XEpzds WHERE XEpzds_user_id = ? ORDER BY XEpzds_created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$products = [];

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
}

$stmt->close();

// 自动生成并保存分享链接到数据库
$currentUsername = $_SESSION['username'] ?? 'default';
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$baseUrl = $protocol . $_SERVER['HTTP_HOST'];

foreach ($products as $product) {
    if (empty($product['XEpzds_share_link'])) {
        $shareLink = $baseUrl . "/Pzds?XEchatroom=" . $product['XEpzds_page_code'];
        $stmt = $conn->prepare("UPDATE XEpzds SET XEpzds_share_link = ? WHERE XEpzds_id = ?");
        $stmt->bind_param("si", $shareLink, $product['XEpzds_id']);
        $stmt->execute();
        $stmt->close();
    }
}

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
    <script src="/assets/html2canvas.min.js"></script>
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
   background: radial-gradient(120% 90% at 50% -12%, rgba(230,15,15,.18) 0%, rgba(230,15,15,0) 64%),
radial-gradient(110% 90% at 50% 112%, rgba(230,15,15,.08) 0%, rgba(230,15,15,0) 62%),
linear-gradient(180deg, rgba(255,255,255,.98), rgba(246,248,252,.94));
    border:1px solid rgba(15,23,42,.08);
    box-shadow:0 30px 92px rgba(2,6,23,.16);
}

.qrPoster:before{
    content:"";
    position:absolute; inset:0;
    border-radius:38px;
    padding:1px;
    background: none!important;
    -webkit-mask-composite:xor;
    mask-composite:exclude;
    pointer-events:none;
    opacity:.55;
}

.qrPoster:after{
    content:"";
    position:absolute; inset:0;
    border-radius:38px;
    background: none!important;
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
    border-color: rgba(230,15,15,.18);
background: linear-gradient(180deg, rgba(230,15,15,.14), rgba(230,15,15,.06));
color: rgba(230,15,15,.98);
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
    <style>
        .empty-state {
            background: transparent;
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
	
		<div style="border: 14px solid transparent;">盼之群聊</div>
		<!-- 防红状态显示（只读） -->
<div class="anti-red-status" id="anti-red-status" style="margin-right: 10px; cursor: default;" title="防红配置请前往聊天室管理页面">
    防红状态：<span id="current-anti-red-domain" style="color: #666;">未配置</span>
</div>
		<div class="action-buttons">
			<button class="add-btn" onclick="openModal('add')"> 创建聊天室 </button>
		</div>
	</div>
	<!-- 主容器 -->
	<div class="container">
		<!-- 商品列表 --> <?php if (count($products) > 0): ?> <!-- 添加JavaScript数据存储 -->
		<script>
			// 存储所有商品数据用于编辑时填充表单
			var productsData = {
			    <?php foreach ($products as $product): ?>
			    <?php echo $product['XEpzds_id']; ?>: {
			        product_name: "<?php echo addslashes($product['XEpzds_product_name']); ?>",
			        product_code: "<?php echo addslashes($product['XEpzds_product_code'] ?? ''); ?>",
			        product_amount: "<?php echo $product['XEpzds_product_amount']; ?>",
			        compensation_type: "<?php echo $product['XEpzds_compensation_type']; ?>",
			        page_status: "<?php echo $product['XEpzds_page_status']; ?>",
			        product_image: "<?php echo addslashes($product['XEpzds_product_image'] ?? ''); ?>",
			        seller_avatar: "<?php echo addslashes($product['XEpzds_seller_avatar'] ?? ''); ?>",
			        page_code: "<?php echo addslashes($product['XEpzds_page_code']); ?>"
			    },
			    <?php endforeach; ?>
			};
		</script>
		<div class="products-grid"> <?php foreach ($products as $product): 
               $product_url = $base_url . "/Pzds?XEchatroom=" . $product['XEpzds_page_code'];
                $compensation_text = "";
                switch($product['XEpzds_compensation_type']) {
                    case '全额包赔': $compensation_text = "全额包赔"; break;
                    case '双倍包赔': $compensation_text = "双倍包赔"; break;
                    case '充值包赔': $compensation_text = "充值包赔"; break;
                    default: $compensation_text = "全额包赔";
                }
            ?> <div class="product-card" id="product-card-<?php echo $product['XEpzds_id']; ?>">
				<!-- 商品图片 -->
				<div class="product-image"> <?php if (!empty($product['XEpzds_product_image'])): ?> <img src="<?php echo $product['XEpzds_product_image']; ?>" alt="<?php echo $product['XEpzds_product_name']; ?>"> <?php else: ?> <div class="placeholder">
						<i class="bi bi-image"></i>
					</div> <?php endif; ?> </div>
				<!-- 商品信息 -->
				<div class="product-info">
					<div class="product-details-grid">
						<!-- 第一行 -->
						<div class="grid-cell">
							<span class="grid-label">商品名称:</span>
							<span class="grid-value product-name"><?php echo $product['XEpzds_product_name']; ?></span>
						</div>
						<div class="grid-cell">
							<span class="grid-label">商品编号:</span>
							<span class="grid-value"><?php echo $product['XEpzds_product_code']; ?></span>
						</div>
						<div class="grid-cell">
							<span class="grid-label">金额:</span>
							<span class="grid-value price">¥<?php echo number_format($product['XEpzds_product_amount'], 2, '.', ''); ?></span>
						</div>
						<!-- 第二行 -->
						<div class="grid-cell">
							<span class="grid-label">包赔类型:</span>
							<span class="grid-value compensation-type"><?php echo $compensation_text; ?></span>
						</div>
						<div class="grid-cell">
							<span class="grid-label">群聊状态:</span>
							<span class="grid-value status <?php echo $product['XEpzds_page_status'] === 'active' ? 'status-active' : 'status-inactive'; ?>"> <?php echo $product['XEpzds_page_status'] === 'active' ? '开启' : '关闭'; ?> </span>
						</div>
						<div class="grid-cell">
							<span class="grid-label">创建时间:</span>
							<span class="grid-value"><?php echo date('m-d H:i', strtotime($product['XEpzds_created_at'])); ?></span>
						</div>
					</div>
				</div>
				<!-- 操作按钮 -->
<div class="card-actions">
    <button class="card-btn btn-edit" data-id="<?php echo $product['XEpzds_id']; ?>">
        <i class="bi bi-pencil-square"></i> 编辑
    </button>
    <button class="card-btn btn-delete" data-id="<?php echo $product['XEpzds_id']; ?>">
        <i class="bi bi-trash"></i> 删除
    </button>

    <button class="card-btn btn-share" data-id="<?php echo $product['XEpzds_id']; ?>">
        <i class="bi bi-share"></i> 分享
    </button>
    <button class="card-btn btn-share-image" data-id="<?php echo $product['XEpzds_id']; ?>">
        <i class="bi bi-image"></i> 分享图
    </button>
</div>
			</div> <?php endforeach; ?> </div> <?php else: ?> <!-- 空状态 -->
		<div class="empty-state">
			<div class="empty-icon">
				<i class="bi bi-terminal-x"></i>
			</div>
			<h3 class="empty-title">暂无聊天室</h3>
			<p class="empty-text">您还没有创建过盼之聊天室</p>
			
		</div> <?php endif; ?>
	</div>
	<!-- 添加/编辑商品模态框 -->
	<div class="modal-overlay" id="productModal">
		<div class="modal-content">
			<form method="POST" action="" id="productForm" enctype="multipart/form-data">
				<div class="modal-header">
					<h5 class="modal-title" id="modalTitle">群聊设置</h5>
					<button type="button" class="modal-close" onclick="closeModal()">×</button>
				</div>
				<div class="modal-body">
					<input type="hidden" name="action" value="save_product">
					<input type="hidden" name="id" id="productId" value="0">
					<input type="hidden" name="old_product_image" id="old_product_image" value="">
					<input type="hidden" name="old_seller_avatar" id="old_seller_avatar" value="">
					<!-- 在表单的隐藏字段区域添加 -->
					<input type="hidden" name="default_avatar_used" id="defaultAvatarUsed" value="0">
					<input type="hidden" name="seller_avatar_url" id="sellerAvatarUrl" value="">
					<!-- 商品名称 -->
					<div class="form-group">
						<label for="XEpzds_product_name" class="form-label required">商品名称</label>
						<input type="text" class="form-input" id="XEpzds_product_name" name="XEpzds_product_name" required placeholder="例如：王者荣耀V10账号">
					</div>
					<!-- 商品编号 -->
					<div class="form-group">
						<label for="XEpzds_product_code" class="form-label required">商品编号</label>
						<div class="input-with-button">
							<input type="text" class="form-input" id="XEpzds_product_code" name="XEpzds_product_code" placeholder="例如：F1JSM1">
							<button type="button" class="random-code-btn" id="generateRandomCode">
								<i class="bi bi-shuffle"></i> 随机编号 </button>
						</div>
					</div>
					<!-- 商品金额 -->
					<div class="form-group">
						<label for="XEpzds_product_amount" class="form-label required">商品金额</label>
						<input type="number" class="form-input" id="XEpzds_product_amount" name="XEpzds_product_amount" step="0.01" min="0.01" required placeholder="0.00">
					</div>
					<!-- 包赔类型 -->
					<div class="form-group">
						<label for="XEpzds_compensation_type" class="form-label required">包赔类型</label>
						<select class="form-select" id="XEpzds_compensation_type" name="XEpzds_compensation_type" required>
							<option value="全额包赔">全额包赔</option>
							<option value="双倍包赔">双倍包赔</option>
							<option value="充值包赔">充值包赔</option>
						</select>
					</div>
					<!-- 页面状态 -->
					<div class="form-group">
						<label for="XEpzds_page_status" class="form-label required">群聊状态</label>
						<select class="form-select" id="XEpzds_page_status" name="XEpzds_page_status" required>
							<option value="active">开启</option>
							<option value="inactive">关闭</option>
						</select>
					</div>
					<!-- 商品图片上传 -->
					<div class="form-group">
						<label class="form-label required">商品图片</label>
						<div class="upload-container" id="productImageUpload">
							<div class="upload-icon">
								<i class="bi bi-image"></i>
							</div>
							<div class="upload-text">点击上传商品图片</div>
							<div class="upload-hint">支持 JPG、PNG、GIF、WebP 格式，最大 2MB</div>
							<input type="file" class="upload-input" id="XEpzds_product_image" name="XEpzds_product_image" accept="image/*">
						</div>
						<!-- 当前商品图片（仅编辑时显示） -->
						<div id="currentProductImage" class="current-image" style="display: none;">
							<span class="current-image-label">当前图片：</span>
							<img src="" alt="当前商品图片" class="current-image-preview">
							<span class="current-image-text"></span>
						</div>
						<!-- 新图片预览 -->
						<div class="upload-preview" id="productImagePreview">
							<button type="button" class="preview-remove" onclick="removeProductImage()">×</button>
							<img src="" alt="商品图片预览" class="preview-image" id="productImagePreviewImg">
							<div class="preview-info">
								<span>新图片：</span>
								<span class="preview-name" id="productImageName"></span>
							</div>
						</div>
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
							<input type="file" class="upload-input" id="XEpzds_seller_avatar" name="XEpzds_seller_avatar" accept="image/*">
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
			<p class="confirm-text">您确定要删除这个商品吗？此操作不可撤销。</p>
			<div class="confirm-buttons">
				<button class="confirm-btn confirm-cancel" onclick="closeConfirmModal()">取消</button>
				<button class="confirm-btn confirm-delete" id="confirmDelete">确认删除</button>
			</div>
		</div>
	</div>
	
<div class="confirm-overlay" id="shareModal">
	<!-- 在分享模态框中，修改会话ID显示部分 -->
<div class="share-modal-content">
    <div class="share-header">
        <h3 class="share-title">分享商品</h3>
        <button type="button" class="modal-close" onclick="closeShareModal()" style="position: absolute; right: 15px; top: 15px; font-size: 24px; background: none; border: none; color: #666; cursor: pointer;">×</button>
    </div>
    <div class="share-url-container">
        <!-- 修改这里的ID -->
        <div class="share-url" id="shareUrlText"></div>
        <!-- 新增：随机参数显示区域 -->
        <div class="share-param-info" id="shareParamInfo" style="margin-top: 10px; font-size: 12px; color: #666;">
            <!-- 修改这里的ID -->
            <span>会话ID: <span id="sessionIdDisplay"></span></span>
        </div>
          <!-- 添加调试信息 -->
        <div id="debugInfo" style="margin-top: 10px; font-size: 10px; color: #999; display: none;">
            <div>防红域名: <span id="debugAntiRedStatus">未加载</span></div>
            <div>应用域名: <span id="debugAppliedDomain">无</span></div>
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
            <h3 style="margin: 0; font-size: 18px; font-weight: 600;">【<?php echo $product['XEpzds_product_name']; ?>】- 分享图</h3>
            <button type="button" class="modal-close" onclick="closeShareImageModal()" style="font-size: 24px; background: none; border: none; color: #666; cursor: pointer;">×</button>
        </div>
        
        <div class="qrPoster" id="shareImageContainer">
            <div class="qrPosterInner">
                <div class="qrPosterTop">
                    <img class="qrPosterAvatar" src="/assets/img/pzqr.png" alt="头像">
                    <div class="qrPosterName" id="shareImagePosterName">商品名称商品编号</div>
                    <div class="qrPosterBadges">
                        <span class="qrPosterTag" id="shareImagePosterTag">盼之担保</span>
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
		<input type="hidden" name="action" value="delete_product">
		<input type="hidden" name="id" id="deleteId" value="">
	</form>
<script>
// ==================== 全局常量和工具函数 ====================
const IMAGE_MAX_SIZE = 2 * 1024 * 1024; // 2MB
const ALLOWED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
const DEFAULT_AVATAR_PATH = '/assets/img/pz-yh.png';

// DOM 元素引用
const domElements = {
    productModal: document.getElementById('productModal'),
    confirmModal: document.getElementById('confirmModal'),
    shareModal: document.getElementById('shareModal'),
    shareImageModal: null,
    antiRedModal: document.getElementById('antiRedModal'),
    productForm: document.getElementById('productForm'),
    notification: document.getElementById('notification'),
    randomCodeBtn: document.getElementById('generateRandomCode'),
    savingOverlay: document.getElementById('savingOverlay'),
    
    // 分享相关
    shareUrlText: document.getElementById('shareUrlText'),
    shareCopyBtn: document.getElementById('shareCopyBtn'),
    shareImageBtn: null,
    sessionIdDisplay: document.getElementById('sessionIdDisplay'),
    
    // 分享图相关
    returnFromImageBtn: null,
    saveImageBtn: null,
    refreshQrLinkBtn: null,
    posterName: null,
    posterTag: null,
    posterPrice: null,
    posterHint: null,
    posterAvatar: null,
    qrCodeBox: null,
    shareImageContainer: null,
    
    // 表单相关
    productId: document.getElementById('productId'),
    modalTitle: document.getElementById('modalTitle'),
    submitBtn: document.getElementById('submitBtn'),
    productName: document.getElementById('XEpzds_product_name'),
    productCode: document.getElementById('XEpzds_product_code'),
    productAmount: document.getElementById('XEpzds_product_amount'),
    productImageInput: document.getElementById('XEpzds_product_image'),
    sellerAvatarInput: document.getElementById('XEpzds_seller_avatar'),
    
    // 预览相关
    productImageUpload: document.getElementById('productImageUpload'),
    productImagePreview: document.getElementById('productImagePreview'),
    productImagePreviewImg: document.getElementById('productImagePreviewImg'),
    productImageName: document.getElementById('productImageName'),
    currentProductImage: document.getElementById('currentProductImage'),
    
    sellerAvatarUpload: document.getElementById('sellerAvatarUpload'),
    avatarPreview: document.getElementById('avatarPreview'),
    avatarPreviewImg: document.getElementById('avatarPreviewImg'),
    avatarImageName: document.getElementById('avatarImageName'),
    currentSellerAvatar: document.getElementById('currentSellerAvatar'),
    
    // 隐藏字段
    oldProductImage: document.getElementById('old_product_image'),
    oldSellerAvatar: document.getElementById('old_seller_avatar'),
    defaultAvatarUsed: document.getElementById('defaultAvatarUsed'),
    sellerAvatarUrl: document.getElementById('sellerAvatarUrl')
};

// 当前分享的URL和产品ID
let currentShareUrl = '';
let currentProductId = 0;
let currentSessionId = '';
let currentShareParam = '';
let userAntiRedConfig = null;

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
            console.error('读取本地状态失败:', error);
            return null;
        }
    },
    
    isStateExpired(state) {
        const SEVEN_DAYS = 7 * 24 * 60 * 60 * 1000;
        return Date.now() - state.timestamp > SEVEN_DAYS;
    }
};
/**
 * 生成随机字符串（6位）
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
 * 生成分享参数（16位）
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

function generateProductShareUrl(productData) {
    const baseUrl = window.location.origin + "/Pzds";
    const productCode = productData.page_code;
    const username = '<?php echo isset($_SESSION['username']) ? $_SESSION['username'] : 'default'; ?>';
    
    const fixedSeed = simpleHash(productCode + (username || 'default'));
    
    const characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
    let customerName = '';
    for (let i = 0; i < 6; i++) {
        const index = parseInt(fixedSeed.substr(i, 1), 36) || (i * 7) % characters.length;
        customerName += characters[index % characters.length];
    }
    
    currentSessionId = 'a' + customerName + 'z-p' + (username || 'default') + 's';
    
    const originalUrl = `${baseUrl}?XEchatroom=${productCode}&id=${currentSessionId}`;
    
    if (userAntiRedConfig && 
        userAntiRedConfig.apply_status === 'on' && 
        userAntiRedConfig.api_url) {
        
        let apiUrl = userAntiRedConfig.api_url.replace(/\/+$/, '');
        const encodedUrl = btoa(originalUrl);
        const antiRedUrl = apiUrl + encodedUrl;
        
        return antiRedUrl;
    }
    
    return originalUrl;
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
    const IMAGE_MAX_SIZE = 2 * 1024 * 1024; // 2MB
    const ALLOWED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    
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

// 重置预览
function resetPreview(previewElement, previewImg, nameElement, uploadContainer) {
    if (previewElement) previewElement.classList.remove('show');
    if (uploadContainer) uploadContainer.classList.remove('has-file');
    if (nameElement) nameElement.textContent = '';
    if (previewImg) previewImg.src = '';
}

// 设置图片上传
function setupImageUpload(inputElement, previewElement, previewImg, nameElement, uploadContainer, options = {}) {
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

// ==================== 模态框管理 ====================
function openModal(mode = 'add', id = null) {
    const modal = domElements.productModal;
    const title = domElements.modalTitle;
    const submitBtn = domElements.submitBtn;
    const randomCodeBtn = domElements.randomCodeBtn;
    
    if (mode === 'add') {
        title.textContent = '订单信息';
        submitBtn.textContent = '制作订单';
        resetForm();
    } else if (mode === 'edit' && id) {
        title.textContent = '编辑订单';
        submitBtn.textContent = '更新订单';
        loadProductData(id);
    }
    
    modal.classList.add('show');
    document.body.style.overflow = 'hidden';
}

function resetForm() {
    domElements.productForm.reset();
    domElements.productId.value = '0';
    domElements.oldProductImage.value = '';
    domElements.oldSellerAvatar.value = '';
    domElements.defaultAvatarUsed.value = '0';
    domElements.sellerAvatarUrl.value = '';
    
    // 重置随机编号按钮
    domElements.randomCodeBtn.innerHTML = '<i class="bi bi-shuffle"></i> 随机编号';
    domElements.randomCodeBtn.classList.remove('success');
    
    // 重置所有预览
    resetImagePreviews();
}

function resetImagePreviews() {
    // 重置商品图片
    resetPreview(domElements.productImagePreview, domElements.productImagePreviewImg, 
                 domElements.productImageName, domElements.productImageUpload);
    domElements.currentProductImage.style.display = 'none';
    
    // 重置卖家头像
    resetPreview(domElements.avatarPreview, domElements.avatarPreviewImg, 
                 domElements.avatarImageName, domElements.sellerAvatarUpload);
    domElements.currentSellerAvatar.style.display = 'none';
}

function loadProductData(id) {
    if (!window.productsData || !window.productsData[id]) {
        showNotification('加载商品数据失败', 'error');
        console.error('未找到商品数据，ID:', id);
        return;
    }
    
    const productData = window.productsData[id];
    domElements.productId.value = id;
    domElements.productName.value = productData.product_name || '';
    domElements.productCode.value = productData.product_code || '';
    domElements.productAmount.value = productData.product_amount || '';
    domElements.oldProductImage.value = productData.product_image || '';
    domElements.oldSellerAvatar.value = productData.seller_avatar || '';
    
    // 设置默认头像状态
    if (productData.seller_avatar === DEFAULT_AVATAR_PATH) {
        domElements.defaultAvatarUsed.value = '1';
        domElements.sellerAvatarUrl.value = DEFAULT_AVATAR_PATH;
    }
    
    // 显示现有图片
    displayExistingImage(productData.product_image, 'product');
    displayExistingImage(productData.seller_avatar, 'avatar');
    
    // 重置按钮状态
    domElements.randomCodeBtn.innerHTML = '<i class="bi bi-shuffle"></i> 随机编号';
    domElements.randomCodeBtn.classList.remove('success');
}
function displayExistingImage(imageUrl, type) {
    if (!imageUrl) return;
    
    const isProduct = type === 'product';
    const previewElement = isProduct ? domElements.currentProductImage : domElements.currentSellerAvatar;
    
    if (!previewElement) {
        console.warn(`未找到预览元素: ${type}`);
        return;
    }
    
    // 在容器内查找图片和文本元素
    const previewImg = previewElement.querySelector('img');
    const previewText = previewElement.querySelector('.current-image-text');
    
    if (imageUrl && previewImg) {
        previewImg.src = imageUrl;
        if (previewText) {
            previewText.textContent = isProduct ? '已上传商品图片' : '已上传卖家头像';
        }
        previewElement.style.display = 'flex';
    } else {
        console.warn(`未找到图片元素或图片URL为空: ${type}`, { previewImg, imageUrl });
    }
}

function closeModal() {
    domElements.productModal.classList.remove('show');
    document.body.style.overflow = 'auto';
}

// 更新调试信息
function updateDebugInfo() {
    const debugInfo = document.getElementById('debugInfo');
    const antiRedStatus = document.getElementById('debugAntiRedStatus');
    const appliedDomain = document.getElementById('debugAppliedDomain');
    
    if (debugInfo && antiRedStatus && appliedDomain) {
        if (userAntiRedConfig) {
            antiRedStatus.textContent = userAntiRedConfig.apply_status === 'on' ? '已应用' : '未应用';
            appliedDomain.textContent = userAntiRedConfig.applied_domain || '无';
        } else {
            antiRedStatus.textContent = '未加载';
            appliedDomain.textContent = '无';
        }
        
        // 开发环境下显示调试信息
        if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
            debugInfo.style.display = 'block';
        }
    }
}


// 分享模态框
async function openShareModal(id) {
    currentProductId = id;
    const productData = window.productsData[id];
    if (!productData) {
        showNotification('商品数据加载失败', 'error');
        return;
    }
    
    console.log("打开分享模态框，商品ID:", id);
    console.log("当前防红配置:", userAntiRedConfig);
    
    // 确保防红配置已加载
    if (!userAntiRedConfig) {
        console.log("防红配置未加载，重新加载");
        userAntiRedConfig = await loadAntiRedConfig();
    }
    // 生成分享链接
    currentShareUrl = generateProductShareUrl(productData);
    saveShareLinkToDb(currentProductId, currentShareUrl);

    console.log("生成的分享链接:", currentShareUrl);

    // 更新模态框显示
    domElements.shareUrlText.textContent = currentShareUrl;
    domElements.sessionIdDisplay.textContent = currentSessionId;
    
    domElements.shareModal.classList.add('show');
    document.body.style.overflow = 'hidden';
    
        // 更新调试信息
    updateDebugInfo();
}

function closeShareModal() {
    domElements.shareModal.classList.remove('show');
    document.body.style.overflow = 'auto';
}

// 打开分享图模态框
async function openShareImageModal(productId) {
    if (!productId) {
        showNotification('商品ID不存在', 'error');
        return;
    }
    
    currentProductId = productId;
    const productData = window.productsData && window.productsData[productId];
    
    if (!productData) {
        showNotification('商品数据加载失败', 'error');
        return;
    }
    
    // 确保防红配置已加载
    if (!userAntiRedConfig) {
        userAntiRedConfig = await loadAntiRedConfig();
    }
    
    // 生成分享链接（支持防红）
    currentShareUrl = generateProductShareUrl(productData);
    saveShareLinkToDb(currentProductId, currentShareUrl);

    // 填充分享图数据
    fillShareImageData(productData);
    
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
        document.body.style.overflow = 'auto';
    }
}

function fillShareImageData(productData) {
    // 设置商品名称和编号
    const productName = productData.product_name || '商品名称';
    const productCode = productData.product_code || '';
    
    document.getElementById('shareImagePosterName').textContent = 
        productCode ? `${productName}${productCode}` : productName;
    
    // 设置标签
    document.getElementById('shareImagePosterTag').textContent = '盼之担保';
    document.getElementById('shareImagePosterHours').textContent = '安全交易';
    
    // 设置提示语
    document.getElementById('shareImagePosterHint').textContent = 
        `请扫描下方二维码加入群聊`;
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
        const fileName = `分享图_${currentProductId}_${timestamp}.png`;
        
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

function openConfirmModal(id) {
    document.getElementById('deleteId').value = id;
    domElements.confirmModal.classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeConfirmModal() {
    domElements.confirmModal.classList.remove('show');
    document.body.style.overflow = 'auto';
}

// ==================== 头像处理 ====================
function handleDefaultAvatar() {
    domElements.defaultAvatarUsed.value = '1';
    domElements.sellerAvatarUrl.value = DEFAULT_AVATAR_PATH;
    domElements.sellerAvatarInput.value = '';
    
    domElements.avatarPreviewImg.src = DEFAULT_AVATAR_PATH;
    domElements.avatarImageName.textContent = '默认头像 (pz-yh.png)';
    domElements.avatarPreview.classList.add('show');
    domElements.sellerAvatarUpload.classList.add('has-file');
    
    showNotification('已选择默认头像', 'success');
}

function removeSellerAvatar() {
    domElements.sellerAvatarInput.value = '';
    domElements.avatarPreview.classList.remove('show');
    domElements.sellerAvatarUpload.classList.remove('has-file');
    domElements.avatarImageName.textContent = '';
    domElements.defaultAvatarUsed.value = '0';
    domElements.sellerAvatarUrl.value = '';
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
    const randomCode = generateRandomCode();
    domElements.productCode.value = randomCode;
    domElements.productCode.dispatchEvent(new Event('input'));
    
    const button = domElements.randomCodeBtn;
    button.classList.add('success');
    button.innerHTML = '<i class="bi bi-check-lg"></i> 已生成';
    
    showNotification(`已生成商品编号: ${randomCode}`);
    
    setTimeout(() => {
        button.classList.remove('success');
        button.innerHTML = '<i class="bi bi-shuffle"></i> 随机编号';
    }, 1000);
}

// ==================== 表单验证 ====================
function validateForm() {
    const amount = parseFloat(domElements.productAmount.value);
    if (isNaN(amount) || amount <= 0) {
        showNotification('商品金额必须大于0', 'error');
        return false;
    }
    
    const name = domElements.productName.value.trim();
    if (name === '') {
        showNotification('请输入商品名称', 'error');
        return false;
    }
    
    const code = domElements.productCode.value.trim();
    if (code === '') {
        showNotification('请输入商品编号', 'error');
        return false;
    }
    
    // 验证图片大小
    if (domElements.productImageInput.files[0] && 
        domElements.productImageInput.files[0].size > IMAGE_MAX_SIZE) {
        showNotification('商品图片不能超过2MB', 'error');
        return false;
    }
    
    if (domElements.sellerAvatarInput.files[0] && 
        domElements.sellerAvatarInput.files[0].size > IMAGE_MAX_SIZE) {
        showNotification('卖家头像不能超过2MB', 'error');
        return false;
    }
    
    // 验证头像
    const isEditMode = domElements.productId.value !== '0';
    const hasFile = domElements.sellerAvatarInput.files[0];
    const defaultAvatarUsed = domElements.defaultAvatarUsed.value === '1';
    const oldAvatar = domElements.oldSellerAvatar.value;
    
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

// 生成页面URL
function generatePageUrl(pageCode, shareParam, username) {
    const baseUrl = `${window.location.origin}/Pzds`;
    
    function generateSessionId() {
        const characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
        let randomString = '';
        for (let i = 0; i < 6; i++) {
            randomString += characters[Math.floor(Math.random() * characters.length)];
        }
        return `a${randomString}z-p${username || 'default'}s`;
    }
    
    const sessionId = generateSessionId();
    return `${baseUrl}?XEchatroom=${pageCode}&id=${sessionId}`;
}

// ==================== 复制链接功能 ====================
function copyProductLink(url) {
    if (!url) {
        showNotification('链接为空', 'error');
        return;
    }
    
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(url).then(() => {
            showNotification('商品链接已复制到剪贴板');
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
            showNotification('商品链接已复制到剪贴板');
        } else {
            showNotification('复制失败，请手动复制', 'error');
        }
    } catch (err) {
        showNotification('复制失败，请手动复制', 'error');
    }
    document.body.removeChild(tempInput);
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
        console.log("防红配置API响应:", response);
        
        if (response.success && response.config) {
            // 确保配置格式正确
            const config = response.config;
            
            // 如果配置中没有api_url，但提供了applied_domain，则构建默认的api_url
            if (!config.api_url && config.applied_domain) {
                config.api_url = `${config.applied_domain}/`;
            }
            
            console.log("防红配置数据:", config);
            return config;
        }
    } catch (error) {
        console.error('加载防红配置失败:', error);
    }
    return null;
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

// 刷新分享链接
async function refreshShareLink() {
    if (!currentProductId) {
        showNotification('请先选择商品', 'error');
        return;
    }
    
    const productData = window.productsData[currentProductId];
    if (!productData) {
        showNotification('商品数据加载失败', 'error');
        return;
    }
    
    // 重新加载防红配置
    userAntiRedConfig = await loadAntiRedConfig();
    
    // 重新生成链接
    currentShareUrl = generateProductShareUrl(productData);
    domElements.shareUrlText.textContent = currentShareUrl;
    saveShareLinkToDb(currentProductId, currentShareUrl);
    
    // 更新调试信息
    updateDebugInfo();
    
    showNotification('链接已刷新', 'success');
    console.log("刷新后的链接:", currentShareUrl);
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

// 日期格式化函数
function formatDate(dateString) {
    if (!dateString) return '未知日期';
    
    const date = new Date(dateString);
    const month = (date.getMonth() + 1).toString().padStart(2, '0');
    const day = date.getDate().toString().padStart(2, '0');
    return `${month}/${day}`;
}

// 保存分享链接到数据库
function saveShareLinkToDb(productId, shareUrl) {
    fetch('/group', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=save_share_link&platform=pzds&chatroom_id=' + encodeURIComponent(productId) + '&share_link=' + encodeURIComponent(shareUrl)
    }).catch(err => console.error('保存分享链接失败:', err));
}

function setupEventListeners() {
    // 编辑按钮
    document.addEventListener('click', function(e) {
        if (e.target.closest('.btn-edit')) {
            const button = e.target.closest('.btn-edit');
            const id = button.getAttribute('data-id');
            openModal('edit', id);
        }
        
        if (e.target.closest('.btn-delete')) {
            const button = e.target.closest('.btn-delete');
            const id = button.getAttribute('data-id');
            openConfirmModal(id);
        }

        // 分享按钮
        if (e.target.closest('.btn-share')) {
            const button = e.target.closest('.btn-share');
            const id = button.getAttribute('data-id');
            currentProductId = id;
            openShareModal(id);
        }
        
        // 分享图按钮
        if (e.target.closest('.btn-share-image')) {
            const button = e.target.closest('.btn-share-image');
            const id = button.getAttribute('data-id');
            openShareImageModal(id);
        }
    });
    
    // 确认删除
    document.getElementById('confirmDelete')?.addEventListener('click', function() {
        document.getElementById('deleteForm').submit();
    });
    
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
    
    // 分享模态框刷新链接按钮
    document.getElementById('refreshShareLinkBtn')?.addEventListener('click', refreshShareLink);
    
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
    
    // 防红模态框事件
    domElements.antiRedModal?.addEventListener('click', function(e) {
        if (e.target === this || e.target.closest('.modal-close')) {
            closeAntiRedModal();
        }
    });
    
    // 购买防红按钮
    document.getElementById('buy-button')?.addEventListener('click', buyAntiRedLink);
    
    // 引到浏览器开关
    document.getElementById('redirect-switch')?.addEventListener('click', toggleRedirectSwitch);
    
    // 模态框外部点击关闭
    [domElements.productModal, domElements.confirmModal, domElements.shareModal, 
     domElements.shareImageModal].forEach(modal => {
        modal?.addEventListener('click', function(e) {
            if (e.target === this) {
                if (this === domElements.productModal) closeModal();
                else if (this === domElements.confirmModal) closeConfirmModal();
                else if (this === domElements.shareModal) closeShareModal();
                else if (this === domElements.shareImageModal) closeShareImageModal();
            }
        });
    });
    
    // 键盘事件
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeModal();
            closeConfirmModal();
            closeShareModal();
            closeShareImageModal();
            closeAntiRedModal();
        }
    });
    
    // 图片上传设置
    if (domElements.productImageInput && domElements.productImagePreview && domElements.productImagePreviewImg) {
        setupImageUpload(
            domElements.productImageInput,
            domElements.productImagePreview,
            domElements.productImagePreviewImg,
            domElements.productImageName,
            domElements.productImageUpload
        );
    }
    
    if (domElements.sellerAvatarInput && domElements.avatarPreview && domElements.avatarPreviewImg) {
        setupImageUpload(
            domElements.sellerAvatarInput,
            domElements.avatarPreview,
            domElements.avatarPreviewImg,
            domElements.avatarImageName,
            domElements.sellerAvatarUpload,
            {
                onChange: function(file) {
                    domElements.defaultAvatarUsed.value = '0';
                    domElements.sellerAvatarUrl.value = '';
                }
            }
        );
    }
    
    // 拖拽上传
    if (domElements.productImageUpload && domElements.productImageInput) {
        setupDragAndDrop(domElements.productImageUpload, domElements.productImageInput);
    }
    
    if (domElements.sellerAvatarUpload && domElements.sellerAvatarInput) {
        setupDragAndDrop(domElements.sellerAvatarUpload, domElements.sellerAvatarInput);
    }
    
    // 默认头像按钮
    document.getElementById('useDefaultAvatar')?.addEventListener('click', handleDefaultAvatar);
    
    // 随机编号按钮
    domElements.randomCodeBtn?.addEventListener('click', handleRandomCode);
    
    // 表单提交验证
    domElements.productForm?.addEventListener('submit', function(e) {
        if (!validateForm()) {
            e.preventDefault();
        }
    });
}

// 你妈的初始化
document.addEventListener('DOMContentLoaded', function() {
    setupEventListeners();
    loadAntiRedConfig().then(config => {
        userAntiRedConfig = config;
        if (userAntiRedConfig && userAntiRedConfig.apply_status === 'on' && userAntiRedConfig.applied_domain) {
            updateAntiRedStatus(userAntiRedConfig.applied_domain);
        }
    }).catch(error => {
        console.error('加载防红配置失败:', error);
    });
    <?php if (isset($success_message)): ?>
    setTimeout(() => {
        showNotification(<?php echo json_encode($success_message, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT); ?>, 'success');
    }, 300);
    <?php endif; ?>
    <?php if (isset($error_message)): ?>
    setTimeout(() => {
        showNotification(<?php echo json_encode($error_message, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT); ?>, 'error');
    }, 300);
    <?php endif; ?>
}); 
</script>
</body>
</html>