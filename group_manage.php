<?php
if (!defined('FROM_ROUTER')) {
    http_response_code(403);
    exit('喜乐科技@x60898');
}
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/dbconfig.php';
checkLogin();

$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$base_url = $protocol . $_SERVER['HTTP_HOST'];
$conn = getDB();
if (!$conn) die("数据库连接失败");
$user_id = $_SESSION['user_id'];

// 读取源站URL配置
$siteUrlJson = '{"site_url":"","site_url_enabled":0}';
try {
    @$wcr = $conn->query("SELECT site_url, site_url_enabled FROM webconfig ORDER BY id DESC LIMIT 1");
    if ($wcr && $wcr->num_rows > 0) {
        $suc = $wcr->fetch_assoc();
        $siteUrlJson = json_encode(['site_url' => $suc['site_url'] ?? '', 'site_url_enabled' => (int)($suc['site_url_enabled'] ?? 0)]);
    }
} catch (Exception $e) {}

// 处理POST请求
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $platform = $_POST['platform'] ?? '';
    $action = $_POST['action'] ?? '';
    $id = intval($_POST['id'] ?? 0);

    // 保存分享链接
    if ($action === 'save_share_link') {
        $chatroom_id = intval($_POST['chatroom_id'] ?? 0);
        $share_link = $_POST['share_link'] ?? '';
        if ($chatroom_id > 0 && !empty($share_link)) {
            $share_link = $conn->real_escape_string($share_link);
            if ($platform === 'pxb7') {
                $stmt = $conn->prepare("UPDATE XEpxb7 SET XEpxb7_share_link = ? WHERE XEpxb7_id = ? AND XEpxb7_user_id = ?");
            } elseif ($platform === 'pzds') {
                $stmt = $conn->prepare("UPDATE XEpzds SET XEpzds_share_link = ? WHERE XEpzds_id = ? AND XEpzds_user_id = ?");
            }
            if (isset($stmt)) {
                $stmt->bind_param("sii", $share_link, $chatroom_id, $user_id);
                $stmt->execute();
                $stmt->close();
            }
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => '参数不完整']);
        }
        exit;
    }

    // ==================== 螃蟹群聊 CRUD ====================
    if ($platform === 'pxb7') {
        if ($action === 'save_product') {
            $XEpxb7_product_name = $conn->real_escape_string($_POST['XEpxb7_product_name']);
            $XEpxb7_game_name = $conn->real_escape_string($_POST['XEpxb7_game_name'] ?? '');
            $XEpxb7_product_code = $conn->real_escape_string($_POST['XEpxb7_product_code'] ?? '');
            $XEpxb7_product_amount = floatval($_POST['XEpxb7_product_amount']);
            $XEpxb7_no_stock_compensation = $conn->real_escape_string($_POST['XEpxb7_no_stock_compensation'] ?? '否');
            $XEpxb7_retrieve_compensation = $conn->real_escape_string($_POST['XEpxb7_retrieve_compensation'] ?? '否');
            $XEpxb7_customer_service = $conn->real_escape_string($_POST['XEpxb7_customer_service'] ?? '螃蟹交易专员');
            $XEpxb7_dummy_identity = $conn->real_escape_string($_POST['XEpxb7_dummy_identity'] ?? '买家');
            $XEpxb7_page_status = $conn->real_escape_string($_POST['XEpxb7_page_status'] ?? 'active');

            $XEpxb7_product_image = $_POST['old_product_image'] ?? '';
            $XEpxb7_seller_avatar = $_POST['old_seller_avatar'] ?? '';

            // 商品图片上传
            if (isset($_FILES['XEpxb7_product_image']) && $_FILES['XEpxb7_product_image']['error'] == 0) {
                $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/chatroom/';
                if (!file_exists($uploadDir)) mkdir($uploadDir, 0755, true);
                $fileExt = pathinfo($_FILES['XEpxb7_product_image']['name'], PATHINFO_EXTENSION);
                $fileName = 'product_' . time() . '_' . uniqid() . '.' . $fileExt;
                $uploadFile = $uploadDir . $fileName;
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                if (in_array($_FILES['XEpxb7_product_image']['type'], $allowedTypes)) {
                    if (move_uploaded_file($_FILES['XEpxb7_product_image']['tmp_name'], $uploadFile)) {
                        $XEpxb7_product_image = '/uploads/chatroom/' . $fileName;
                    }
                }
            }

            // 卖家头像上传
            $defaultAvatarUsed = isset($_POST['default_avatar_used']) ? intval($_POST['default_avatar_used']) : 0;
            $sellerAvatarUrl = isset($_POST['seller_avatar_url']) ? $conn->real_escape_string($_POST['seller_avatar_url']) : '';
            if ($defaultAvatarUsed == 1 && !empty($sellerAvatarUrl)) {
                $XEpxb7_seller_avatar = $sellerAvatarUrl;
            } else if (isset($_FILES['XEpxb7_seller_avatar']) && $_FILES['XEpxb7_seller_avatar']['error'] == 0) {
                $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/chatroom/';
                if (!file_exists($uploadDir)) mkdir($uploadDir, 0755, true);
                $fileExt = pathinfo($_FILES['XEpxb7_seller_avatar']['name'], PATHINFO_EXTENSION);
                $fileName = 'avatar_' . time() . '_' . uniqid() . '.' . $fileExt;
                $uploadFile = $uploadDir . $fileName;
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                if (in_array($_FILES['XEpxb7_seller_avatar']['type'], $allowedTypes)) {
                    if (move_uploaded_file($_FILES['XEpxb7_seller_avatar']['tmp_name'], $uploadFile)) {
                        $XEpxb7_seller_avatar = '/uploads/chatroom/' . $fileName;
                    }
                }
            } else if ($id > 0) {
                $XEpxb7_seller_avatar = $_POST['old_seller_avatar'] ?? '';
            } else {
                $XEpxb7_seller_avatar = '';
            }

            $XEpxb7_product_image = $conn->real_escape_string($XEpxb7_product_image);
            $XEpxb7_seller_avatar = $conn->real_escape_string($XEpxb7_seller_avatar);

            if ($id > 0) {
                $sql = "UPDATE XEpxb7 SET XEpxb7_product_name=?, XEpxb7_game_name=?, XEpxb7_product_code=?, XEpxb7_product_amount=?, XEpxb7_no_stock_compensation=?, XEpxb7_retrieve_compensation=?, XEpxb7_customer_service=?, XEpxb7_dummy_identity=?, XEpxb7_page_status=?, XEpxb7_product_image=?, XEpxb7_seller_avatar=? WHERE XEpxb7_id=? AND XEpxb7_user_id=?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssdsssssssii", $XEpxb7_product_name, $XEpxb7_game_name, $XEpxb7_product_code, $XEpxb7_product_amount, $XEpxb7_no_stock_compensation, $XEpxb7_retrieve_compensation, $XEpxb7_customer_service, $XEpxb7_dummy_identity, $XEpxb7_page_status, $XEpxb7_product_image, $XEpxb7_seller_avatar, $id, $user_id);
            } else {
                $XEpxb7_page_code = substr(md5(uniqid(rand(), true)), 0, 10);
                $sql = "INSERT INTO XEpxb7 (XEpxb7_user_id, XEpxb7_product_name, XEpxb7_game_name, XEpxb7_product_code, XEpxb7_product_amount, XEpxb7_no_stock_compensation, XEpxb7_retrieve_compensation, XEpxb7_customer_service, XEpxb7_dummy_identity, XEpxb7_page_status, XEpxb7_page_code, XEpxb7_product_image, XEpxb7_seller_avatar) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("isssdssssssss", $user_id, $XEpxb7_product_name, $XEpxb7_game_name, $XEpxb7_product_code, $XEpxb7_product_amount, $XEpxb7_no_stock_compensation, $XEpxb7_retrieve_compensation, $XEpxb7_customer_service, $XEpxb7_dummy_identity, $XEpxb7_page_status, $XEpxb7_page_code, $XEpxb7_product_image, $XEpxb7_seller_avatar);
            }
            if ($stmt->execute()) { $success_message = $id > 0 ? "更新成功！" : "添加成功！"; } else { $error_message = "操作失败: " . $stmt->error; }
            $stmt->close();
        } elseif ($action === 'delete_product') {
            $id = intval($_POST['id']);
            $sql = "DELETE FROM XEpxb7 WHERE XEpxb7_id=? AND XEpxb7_user_id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $id, $user_id);
            if ($stmt->execute()) { $success_message = "删除成功！"; } else { $error_message = "删除失败: " . $stmt->error; }
            $stmt->close();
        }
    }
    // ==================== 盼之群聊 CRUD ====================
    elseif ($platform === 'pzds') {
        if ($action === 'save_product') {
            $XEpzds_product_name = $conn->real_escape_string($_POST['XEpzds_product_name']);
            $XEpzds_product_code = $conn->real_escape_string($_POST['XEpzds_product_code'] ?? '');
            $XEpzds_product_amount = floatval($_POST['XEpzds_product_amount']);
            $XEpzds_compensation_type = $conn->real_escape_string($_POST['XEpzds_compensation_type']);
            $XEpzds_page_status = $conn->real_escape_string($_POST['XEpzds_page_status']);

            $XEpzds_product_image = $_POST['old_product_image'] ?? '';
            $XEpzds_seller_avatar = $_POST['old_seller_avatar'] ?? '';

            if (isset($_FILES['XEpzds_product_image']) && $_FILES['XEpzds_product_image']['error'] == 0) {
                $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/chatroom/';
                if (!file_exists($uploadDir)) mkdir($uploadDir, 0755, true);
                $fileExt = pathinfo($_FILES['XEpzds_product_image']['name'], PATHINFO_EXTENSION);
                $fileName = 'product_' . time() . '_' . uniqid() . '.' . $fileExt;
                $uploadFile = $uploadDir . $fileName;
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                if (in_array($_FILES['XEpzds_product_image']['type'], $allowedTypes)) {
                    if (move_uploaded_file($_FILES['XEpzds_product_image']['tmp_name'], $uploadFile)) {
                        $XEpzds_product_image = '/uploads/chatroom/' . $fileName;
                    }
                }
            }

            $defaultAvatarUsed = isset($_POST['default_avatar_used']) ? intval($_POST['default_avatar_used']) : 0;
            $sellerAvatarUrl = isset($_POST['seller_avatar_url']) ? $conn->real_escape_string($_POST['seller_avatar_url']) : '';
            if ($defaultAvatarUsed == 1 && !empty($sellerAvatarUrl)) {
                $XEpzds_seller_avatar = $sellerAvatarUrl;
            } else if (isset($_FILES['XEpzds_seller_avatar']) && $_FILES['XEpzds_seller_avatar']['error'] == 0) {
                $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/chatroom/';
                if (!file_exists($uploadDir)) mkdir($uploadDir, 0755, true);
                $fileExt = pathinfo($_FILES['XEpzds_seller_avatar']['name'], PATHINFO_EXTENSION);
                $fileName = 'avatar_' . time() . '_' . uniqid() . '.' . $fileExt;
                $uploadFile = $uploadDir . $fileName;
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                if (in_array($_FILES['XEpzds_seller_avatar']['type'], $allowedTypes)) {
                    if (move_uploaded_file($_FILES['XEpzds_seller_avatar']['tmp_name'], $uploadFile)) {
                        $XEpzds_seller_avatar = '/uploads/chatroom/' . $fileName;
                    }
                }
            } else if ($id > 0) {
                $XEpzds_seller_avatar = $_POST['old_seller_avatar'] ?? '';
            } else {
                $XEpzds_seller_avatar = '';
            }

            $XEpzds_product_image = $conn->real_escape_string($XEpzds_product_image);
            $XEpzds_seller_avatar = $conn->real_escape_string($XEpzds_seller_avatar);

            if ($id > 0) {
                $sql = "UPDATE XEpzds SET XEpzds_product_name=?, XEpzds_product_code=?, XEpzds_product_amount=?, XEpzds_compensation_type=?, XEpzds_page_status=?, XEpzds_product_image=?, XEpzds_seller_avatar=? WHERE XEpzds_id=? AND XEpzds_user_id=?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssdssssii", $XEpzds_product_name, $XEpzds_product_code, $XEpzds_product_amount, $XEpzds_compensation_type, $XEpzds_page_status, $XEpzds_product_image, $XEpzds_seller_avatar, $id, $user_id);
            } else {
                $XEpzds_page_code = substr(md5(uniqid(rand(), true)), 0, 10);
                $sql = "INSERT INTO XEpzds (XEpzds_user_id, XEpzds_product_name, XEpzds_product_code, XEpzds_product_amount, XEpzds_compensation_type, XEpzds_page_status, XEpzds_page_code, XEpzds_product_image, XEpzds_seller_avatar) VALUES (?,?,?,?,?,?,?,?,?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("issdsssss", $user_id, $XEpzds_product_name, $XEpzds_product_code, $XEpzds_product_amount, $XEpzds_compensation_type, $XEpzds_page_status, $XEpzds_page_code, $XEpzds_product_image, $XEpzds_seller_avatar);
            }
            if ($stmt->execute()) { $success_message = $id > 0 ? "更新成功！" : "添加成功！"; } else { $error_message = "操作失败: " . $stmt->error; }
            $stmt->close();
        } elseif ($action === 'delete_product') {
            $id = intval($_POST['id']);
            $sql = "DELETE FROM XEpzds WHERE XEpzds_id=? AND XEpzds_user_id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $id, $user_id);
            if ($stmt->execute()) { $success_message = "删除成功！"; } else { $error_message = "删除失败: " . $stmt->error; }
            $stmt->close();
        }
    }
    // ==================== 白情群聊 CRUD ====================
    elseif ($platform === 'youxige') {
        if ($action === 'save_chatroom') {
            $XEyouxige_trader_name = $conn->real_escape_string($_POST['XEyouxige_trader_name']);
            $XEyouxige_group_code = $conn->real_escape_string($_POST['XEyouxige_group_code'] ?? '');
            $XEyouxige_welcome_message = $conn->real_escape_string($_POST['XEyouxige_welcome_message'] ?? '');
            $XEyouxige_page_status = $conn->real_escape_string($_POST['XEyouxige_page_status']);

            // 群聊编号重复检查
            $check_sql = "SELECT XEyouxige_id FROM XEyouxige WHERE XEyouxige_user_id=? AND XEyouxige_group_code=?";
            if ($id > 0) $check_sql .= " AND XEyouxige_id != ?";
            $check_stmt = $conn->prepare($check_sql);
            if ($check_stmt !== false) {
                if ($id > 0) { $check_stmt->bind_param("isi", $user_id, $XEyouxige_group_code, $id); }
                else { $check_stmt->bind_param("is", $user_id, $XEyouxige_group_code); }
                $check_stmt->execute();
                $check_stmt->store_result();
                if ($check_stmt->num_rows > 0) {
                    $error_message = "群聊编号 '{$XEyouxige_group_code}' 已存在，请使用其他编号！";
                    $check_stmt->close();
                } else {
                    $check_stmt->close();

                    $XEyouxige_seller_avatar = $_POST['old_seller_avatar'] ?? '';
                    $defaultAvatarUsed = isset($_POST['default_avatar_used']) ? intval($_POST['default_avatar_used']) : 0;
                    $sellerAvatarUrl = isset($_POST['seller_avatar_url']) ? $conn->real_escape_string($_POST['seller_avatar_url']) : '';

                    if ($defaultAvatarUsed == 1 && !empty($sellerAvatarUrl)) {
                        $XEyouxige_seller_avatar = $sellerAvatarUrl;
                    } else if (isset($_FILES['XEyouxige_seller_avatar']) && $_FILES['XEyouxige_seller_avatar']['error'] == 0) {
                        $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/chatroom/';
                        if (!file_exists($uploadDir)) mkdir($uploadDir, 0755, true);
                        $fileExt = pathinfo($_FILES['XEyouxige_seller_avatar']['name'], PATHINFO_EXTENSION);
                        $fileName = 'avatar_' . time() . '_' . uniqid() . '.' . $fileExt;
                        $uploadFile = $uploadDir . $fileName;
                        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                        if (in_array($_FILES['XEyouxige_seller_avatar']['type'], $allowedTypes)) {
                            if (move_uploaded_file($_FILES['XEyouxige_seller_avatar']['tmp_name'], $uploadFile)) {
                                $XEyouxige_seller_avatar = '/uploads/chatroom/' . $fileName;
                                if (!empty($_POST['old_seller_avatar']) && !in_array($_POST['old_seller_avatar'], ['/assets/img/pz-yh.png', '/assets/img/dummy1.png', '/assets/img/bq-yh.png']) && file_exists($_SERVER['DOCUMENT_ROOT'] . $_POST['old_seller_avatar'])) {
                                    unlink($_SERVER['DOCUMENT_ROOT'] . $_POST['old_seller_avatar']);
                                }
                            }
                        }
                    } else if ($id > 0) {
                        $XEyouxige_seller_avatar = $_POST['old_seller_avatar'] ?? '';
                    } else {
                        $XEyouxige_seller_avatar = '';
                    }

                    $XEyouxige_seller_avatar = $conn->real_escape_string($XEyouxige_seller_avatar);

                    if ($id > 0) {
                        $sql = "UPDATE XEyouxige SET XEyouxige_trader_name=?, XEyouxige_group_code=?, XEyouxige_welcome_message=?, XEyouxige_page_status=?, XEyouxige_seller_avatar=?, XEyouxige_updated_at=CURRENT_TIMESTAMP WHERE XEyouxige_id=? AND XEyouxige_user_id=?";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("sssssii", $XEyouxige_trader_name, $XEyouxige_group_code, $XEyouxige_welcome_message, $XEyouxige_page_status, $XEyouxige_seller_avatar, $id, $user_id);
                    } else {
                        $XEyouxige_page_code = substr(md5(uniqid(rand(), true)), 0, 10);
                        $sql = "INSERT INTO XEyouxige (XEyouxige_user_id, XEyouxige_trader_name, XEyouxige_group_code, XEyouxige_welcome_message, XEyouxige_page_status, XEyouxige_page_code, XEyouxige_seller_avatar) VALUES (?,?,?,?,?,?,?)";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("issssss", $user_id, $XEyouxige_trader_name, $XEyouxige_group_code, $XEyouxige_welcome_message, $XEyouxige_page_status, $XEyouxige_page_code, $XEyouxige_seller_avatar);
                    }
                    if ($stmt->execute()) { $success_message = $id > 0 ? "更新成功！" : "添加成功！"; } else { $error_message = "操作失败: " . $stmt->error; }
                    $stmt->close();
                }
            }
        } elseif ($action === 'delete_chatroom') {
            $id = intval($_POST['id']);
            $query_sql = "SELECT XEyouxige_seller_avatar FROM XEyouxige WHERE XEyouxige_id=? AND XEyouxige_user_id=?";
            $query_stmt = $conn->prepare($query_sql);
            if ($query_stmt) {
                $query_stmt->bind_param("ii", $id, $user_id);
                $query_stmt->execute();
                $query_result = $query_stmt->get_result();
                if ($row = $query_result->fetch_assoc()) {
                    if (!empty($row['XEyouxige_seller_avatar']) && !in_array($row['XEyouxige_seller_avatar'], ['/assets/img/pz-yh.png', '/assets/img/dummy1.png', '/assets/img/bq-yh.png']) && file_exists($_SERVER['DOCUMENT_ROOT'] . $row['XEyouxige_seller_avatar'])) {
                        unlink($_SERVER['DOCUMENT_ROOT'] . $row['XEyouxige_seller_avatar']);
                    }
                }
                $query_stmt->close();
            }
            $sql = "DELETE FROM XEyouxige WHERE XEyouxige_id=? AND XEyouxige_user_id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $id, $user_id);
            if ($stmt->execute()) { $success_message = "删除成功！"; } else { $error_message = "删除失败: " . $stmt->error; }
            $stmt->close();
        }
    }
}

// 查询三个表的数据
$products_pxb7 = [];
$sql_pxb7 = "SELECT *, XEpxb7_game_name as game_name FROM XEpxb7 WHERE XEpxb7_user_id=? ORDER BY XEpxb7_created_at DESC";
$stmt = $conn->prepare($sql_pxb7);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
while($row = $result->fetch_assoc()) $products_pxb7[] = $row;
$stmt->close();

$products_pzds = [];
$sql_pzds = "SELECT * FROM XEpzds WHERE XEpzds_user_id=? ORDER BY XEpzds_created_at DESC";
$stmt = $conn->prepare($sql_pzds);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
while($row = $result->fetch_assoc()) $products_pzds[] = $row;
$stmt->close();

$chatrooms_youxige = [];
$sql_youxige = "SELECT * FROM XEyouxige WHERE XEyouxige_user_id=? ORDER BY XEyouxige_created_at DESC";
$stmt = $conn->prepare($sql_youxige);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
while($row = $result->fetch_assoc()) $chatrooms_youxige[] = $row;
$stmt->close();

$currentUsername = $_SESSION['username'] ?? 'default';
$defaultTab = isset($group_default_tab) ? $group_default_tab : 'pxb7';
$conn->close();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="群聊管理">
    <link rel="apple-touch-icon" href="/assets/img/icon-192.png">
    <link rel="manifest" href="/manifest.php">
    <meta name="theme-color" content="#f7f8fa">
    <title>群聊管理</title>
    <link rel="icon" type="image/x-icon" href="/favicon.png">
    <link rel="stylesheet" href="/assets/bootstrap-icons.css">
    <link rel="stylesheet" href="/assets/chatroom.css">
    <link rel="stylesheet" href="/assets/chatroom_add.css">
    <link rel="stylesheet" href="/assets/top_bar.css">
    <script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
    <script src="/assets/qrcode.min.js"></script>
    <style>
        /* Tab切换样式 */
        .group-tab-bar {
            display: flex;
            position: relative;
            background: #f3f4f6;
            margin: 12px 16px 0;
            border-radius: 8px;
            padding: 3px;
        }
        .group-tab-item {
            flex: 1;
            text-align: center;
            padding: 8px 0;
            font-size: 14px;
            font-weight: 500;
            color: #666;
            cursor: pointer;
            position: relative;
            z-index: 1;
            transition: color 0.3s;
            user-select: none;
        }
        .group-tab-item.active {
            color: #333;
            font-weight: 600;
        }
        .group-tab-slider {
            position: absolute;
            top: 3px;
            left: 3px;
            width: calc(33.333% - 3px);
            height: calc(100% - 6px);
            background: white;
            border-radius: 6px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .group-tab-slider.at-pzds {
            transform: translateX(100%);
        }
        .group-tab-slider.at-youxige {
            transform: translateX(200%);
        }

        /* 内容区域切换 */
        .tab-content { display: none; }
        .tab-content.active { display: block; }

        /* 防红配置样式 */
        .anti-red-status {
            padding: 6px 12px;
            background: #f8f9fa;
            border-radius: 20px;
            font-size: 14px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        .anti-red-status:hover { background: #e9ecef; }

        /* 模态框样式 */
        .modal-overlay, .confirm-overlay, .share-overlay {
            position: fixed; top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.5); z-index: 1000;
            display: flex; align-items: flex-end; justify-content: center;
            opacity: 0; visibility: hidden; transition: all 0.3s ease;
        }
        .modal-overlay.show, .confirm-overlay.show, .share-overlay.show { opacity: 1; visibility: visible; }
        .modal-content {
            background: white; border-radius: 20px 20px 0 0; width: 100%; max-width: 600px;
            max-height: 85vh; overflow-y: auto; transform: translateY(100%);
            transition: transform 0.3s ease-out; box-shadow: 0 -5px 20px rgba(0,0,0,0.2);
        }
        .modal-overlay.show .modal-content { transform: translateY(0); }
        .confirm-content {
            background: white; border-radius: 20px 20px 0 0; width: 100%; max-width: 400px;
            padding: 20px; transform: translateY(100%); transition: transform 0.3s ease-out;
            box-shadow: 0 -5px 20px rgba(0,0,0,0.2);
        }
        .confirm-overlay.show .confirm-content { transform: translateY(0); }
        .confirm-overlay#shareModal { align-items: flex-end; }
        .confirm-overlay#shareModal .share-modal-content {
            background: white; border-radius: 20px 20px 0 0; width: 100%; max-width: 500px;
            transform: translateY(100%); transition: transform 0.3s ease-out;
            box-shadow: 0 -5px 20px rgba(0,0,0,0.2); padding: 20px;
        }
        .confirm-overlay#shareModal.show .share-modal-content { transform: translateY(0); }
        .confirm-overlay#shareImageModal { align-items: flex-end; }
        .confirm-overlay#shareImageModal .share-image-modal {
            background: white; border-radius: 20px 20px 0 0; width: 100%; max-width: 500px;
            transform: translateY(100%); transition: transform 0.3s ease-out;
            box-shadow: 0 -5px 20px rgba(0,0,0,0.2); padding: 20px; max-height: 85vh; overflow-y: auto;
        }
        .confirm-overlay#shareImageModal.show .share-image-modal { transform: translateY(0); }

        .modal-header {
            padding: 20px; border-bottom: 1px solid #eee;
            display: flex; justify-content: space-between; align-items: center;
            position: sticky; top: 0; background: white; z-index: 10;
        }
        .modal-title { margin: 0; font-size: 18px; font-weight: 600; }
        .modal-close {
            background: none; border: none; font-size: 28px; color: #666;
            cursor: pointer; padding: 0; width: 32px; height: 32px;
            display: flex; align-items: center; justify-content: center;
        }
        .modal-body { padding: 20px; }
        .modal-footer {
            padding: 20px; border-top: 1px solid #eee;
            display: flex; gap: 10px; justify-content: flex-end;
            position: sticky; bottom: 0; background: white;
        }

        .share-param-info { margin-top: 10px; font-size: 12px; color: #666; display: block; background-color: #f8f9fa; border-radius: 4px; }


        .customer-service-icon { width: 20px; height: 20px; object-fit: cover; border-radius: 50%; margin-right: 5px; vertical-align: middle; }
        .no-stock-indicator, .retrieve-indicator { display: inline-block; padding: 2px 6px; border-radius: 4px; font-size: 12px; }
        .no-stock-indicator.是 { width:fit-content; background-color: #d4edda; color: #155724; }
        .no-stock-indicator.否 { width:fit-content; background-color: #f8d7da; color: #721c24; }
        .retrieve-indicator.是 { width:fit-content; background-color: #cce5ff; color: #004085; }
        .retrieve-indicator.否 { width:fit-content; background-color: #f8d7da; color: #721c24; }



        /* 分享图样式 */
        .qrPoster {
            --ink:#0b1324; --muted:rgba(15,23,42,.62); --line:rgba(15,23,42,.10);
            --card:rgba(255,255,255,.92); --accent:#0a84ff;
            position:relative; width:min(414px,100%); margin:14px auto 10px;
            border-radius:38px; overflow:hidden;
            background:radial-gradient(120% 90% at 50% -12%, rgba(246,160,70,.18) 0%, rgba(246,160,70,0) 64%),
            radial-gradient(110% 90% at 50% 112%, rgba(246,160,70,.08) 0%, rgba(246,160,70,0) 62%),
            linear-gradient(180deg, rgba(255,255,255,.98), rgba(246,248,252,.94));
            border:1px solid rgba(15,23,42,.08); box-shadow:0 30px 92px rgba(2,6,23,.16);
        }
        .qrPoster.theme-pzds {
            background: radial-gradient(120% 90% at 50% -12%, rgba(230,15,15,.18) 0%, rgba(230,15,15,0) 64%),
            radial-gradient(110% 90% at 50% 112%, rgba(230,15,15,.08) 0%, rgba(230,15,15,0) 62%),
            linear-gradient(180deg, rgba(255,255,255,.98), rgba(246,248,252,.94));
        }
        .qrPoster.theme-youxige {
            background: radial-gradient(120% 90% at 50% -12%, rgba(255,220,0,.18) 0%, rgba(255,220,0,0) 64%),
            radial-gradient(110% 90% at 50% 112%, rgba(255,220,0,.08) 0%, rgba(255,220,0,0) 62%),
            linear-gradient(180deg, rgba(255,255,255,.98), rgba(246,248,252,.94));
        }
        .qrPoster:before { content:""; position:absolute; inset:0; border-radius:38px; background:none!important; padding:1px; -webkit-mask-composite:xor; mask-composite:exclude; pointer-events:none; opacity:.55; }
        .qrPoster:after { content:""; position:absolute; inset:0; border-radius:38px; background:none!important; opacity:.55; pointer-events:none; mix-blend-mode:overlay; }
        .qrPosterInner { position:relative; z-index:1; margin:14px; border-radius:30px; background:rgba(255,255,255,.86); border:1px solid rgba(15,23,42,.08); box-shadow:0 18px 60px rgba(2,6,23,.12); overflow:hidden; }
        .qrPosterInner:before { display:none; content:none; }
        .qrPosterTop { display:flex; flex-direction:column; align-items:center; text-align:center; padding:26px 16px 10px; gap:10px; }
        .qrPosterAvatar { width:64px; height:64px; border-radius:22px; object-fit:cover; background:#fff; border:7px solid rgba(255,255,255,.92); box-shadow:0 18px 46px rgba(2,6,23,.18); outline:1px solid rgba(15,23,42,.10); }
        .qrPosterName { font-size:20px; line-height:1.18; font-weight:980; letter-spacing:.25px; color:var(--ink); max-width:340px; word-break:break-word; }
        .qrPosterBadges { display:flex; flex-wrap:wrap; justify-content:center; gap:8px; margin-top:2px; }
        .qrBadge, .qrPosterTag { display:inline-flex; align-items:center; height:28px; padding:0 12px; border-radius:10px; font-size:12px; font-weight:900; letter-spacing:.18px; border:1px solid rgba(15,23,42,.10); background:rgba(255,255,255,.78); color:rgba(15,23,42,.70); }
        .qrPosterTag { border-color:rgba(246,160,70,.18); background:linear-gradient(180deg,rgba(246,160,70,.14),rgba(246,160,70,.06)); color:rgba(246,160,70,.98); }
        .qrPosterTag.theme-pzds { border-color:rgba(230,15,15,.18); background:linear-gradient(180deg,rgba(230,15,15,.14),rgba(230,15,15,.06)); color:rgba(230,15,15,.98); }
        .qrPosterTag.theme-youxige { border-color:rgba(255,220,0,.18); background:linear-gradient(180deg,rgba(255,220,0,.14),rgba(255,220,0,.06)); color:rgba(255,220,0,.98); }
        .qrBadge.hours { background:rgba(15,23,42,.05); }
        .qrDivider { display:none; }
        .qrPosterMid { display:flex; flex-direction:column; align-items:center; justify-content:center; padding:14px 18px 8px; }
        .qrBox { position:relative; width:140px; height:140px; border-radius:12px; padding:10px; background:#ffffff; border:1px solid rgba(15,23,42,.10); box-shadow:0 22px 70px rgba(2,6,23,.18); }
        #shareImageQrCodeBox { width:100%; height:100%; overflow:hidden; background:#fff; }
        #shareImageQrCodeBox img, #shareImageQrCodeBox canvas { width:100%!important; height:100%!important; display:block; }
        .qrPosterScan { margin-top:12px; font-size:12px; font-weight:900; color:rgba(15,23,42,.56); text-align:center; letter-spacing:.18px; }
        .qrPosterHint { margin:12px auto 0; width:fit-content; max-width:calc(100% - 36px); padding:10px 14px; border-radius:999px; border:1px solid rgba(15,23,42,.10); background:linear-gradient(180deg,rgba(255,255,255,.82),rgba(255,255,255,.62)); box-shadow:0 14px 38px rgba(2,6,23,.12); color:rgba(15,23,42,.70); font-size:12px; font-weight:900; text-align:center; letter-spacing:.2px; }
        .qrPosterFoot { padding:12px 18px 16px; text-align:center; font-size:11.5px; font-weight:800; color:rgba(15,23,42,.46); }

        /* 通知样式 */
        .new-user-toast { position:fixed; top:20px; left:50%; transform:translateX(-50%); background:#4caf50; border-radius:8px; padding:12px 16px; display:flex; align-items:center; box-shadow:0 4px 12px rgba(0,0,0,.15); cursor:pointer; z-index:9999; min-width:400px; max-width:400px; }
        .toast-icon { width:24px; height:24px; background:#fff; border-radius:50%; display:flex; align-items:center; justify-content:center; margin-right:12px; flex-shrink:0; font-size:16px; color:#4caf50; font-weight:bold; }
        .toast-text { flex:1; color:#fff; font-size:15px; font-weight:500; line-height:1.4; word-break:break-word; }
        .toast-close { width:24px; height:24px; background:transparent; border:none; color:#fff; font-size:20px; cursor:pointer; display:flex; align-items:center; justify-content:center; opacity:.8; flex-shrink:0; padding:0; margin-left:8px; }
        .notification-enter-active { animation: notification-slide-in 0.3s ease-out; }
        .notification-leave-active { animation: notification-slide-out 0.3s ease-in forwards; }
        @keyframes notification-slide-in { 0% { transform:translateX(-50%) translateY(-100px); opacity:0; } 100% { transform:translateX(-50%) translateY(0); opacity:1; } }
        @keyframes notification-slide-out { 0% { transform:translateX(-50%) translateY(0); opacity:1; } 100% { transform:translateX(-50%) translateY(-100px); opacity:0; } }
        .new-user-toast.info { background:#2196f3; } .new-user-toast.info .toast-icon { color:#2196f3; }
        .new-user-toast.warning { background:#ff9800; } .new-user-toast.warning .toast-icon { color:#ff9800; }
        .new-user-toast.error { background:#f44336; } .new-user-toast.error .toast-icon { color:#f44336; }

        .empty-state { background: transparent; }
        @media (max-width:420px) {
            .qrPoster { border-radius:34px; } .qrPosterInner { margin:12px; border-radius:28px; }
            .qrPosterAvatar { width:60px; height:60px; border-radius:20px; }
            .qrBox { width:136px; height:136px; border-radius:26px; }
        }
    </style>
</head>
<body>
    <div id="notification-container"></div>
    <div class="saving-overlay" id="savingOverlay"><div class="saving-spinner"></div></div>

    <div class="top-header">
        <a href="javascript:void(0)" onclick="window.parent.postMessage('closeModal', '*')" style="display: inline-flex; align-items: center; text-decoration: none; color: inherit;">
            <svg t="1768667202128" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="4699" width="18" height="18">
                <path d="M285.8112 565.76a56.4864 56.4864 0 0 0 39.04-16.3712l452.7744-453.76A56.5248 56.5248 0 0 0 778.24 16.64a54.8992 54.8992 0 0 0-78.08-0.5632L247.3344 469.76a56.5248 56.5248 0 0 0-0.5504 79.0144 50.048 50.048 0 0 0 39.0272 16.9344zM733.568 1024a56.1664 56.1664 0 0 0 39.6032-95.3856l-448.32-458.24a54.912 54.912 0 0 0-78.08-0.5632 56.5248 56.5248 0 0 0-0.5632 79.0144l448.32 458.24A53.76 53.76 0 0 0 733.568 1024z m0 0" fill="#333333" p-id="4700"></path>
            </svg>
        </a>
        <div style="border: 14px solid transparent;">群聊管理</div>
        <div class="anti-red-status" id="anti-red-status" style="margin-right: 10px; cursor: default;">
            防红状态: <span id="current-anti-red-domain" style="color: #666;">未配置</span>
        </div>
        <div class="action-buttons">
            <button class="add-btn" onclick="openModal('add')"> 创建聊天室 </button>
        </div>
    </div>

    <!-- Tab栏 -->
    <div class="group-tab-bar">
        <div class="group-tab-item <?php echo $defaultTab === 'pxb7' ? 'active' : ''; ?>" data-tab="pxb7">螃蟹群聊</div>
        <div class="group-tab-item <?php echo $defaultTab === 'pzds' ? 'active' : ''; ?>" data-tab="pzds">盼之群聊</div>
        <div class="group-tab-item <?php echo $defaultTab === 'youxige' ? 'active' : ''; ?>" data-tab="youxige">白情群聊</div>
        <div class="group-tab-slider <?php echo $defaultTab === 'pzds' ? 'at-pzds' : ($defaultTab === 'youxige' ? 'at-youxige' : ''); ?>"></div>
    </div>

    <!-- 螃蟹群聊内容 -->
    <div class="container tab-content <?php echo $defaultTab === 'pxb7' ? 'active' : ''; ?>" id="tab-pxb7">
        <?php if (count($products_pxb7) > 0): ?>
        <div class="products-grid">
            <?php foreach ($products_pxb7 as $product):
                $customer_avatar = '';
                switch($product['XEpxb7_customer_service']) {
                    case '螃蟹交易专员': $customer_avatar = '/assets/img/px-kf.png'; break;
                    case '螃蟹咨询专员': $customer_avatar = '/assets/img/px-kf2.png'; break;
                    case '螃蟹售后专员': $customer_avatar = '/assets/img/px-kf3.jpg'; break;
                    default: $customer_avatar = '/assets/img/px-kf.png';
                }
            ?>
            <div class="product-card" data-platform="pxb7" id="pxb7-card-<?php echo $product['XEpxb7_id']; ?>">
                <div class="product-image">
                    <?php if (!empty($product['XEpxb7_product_image'])): ?>
                    <img src="<?php echo $product['XEpxb7_product_image']; ?>" alt="">
                    <?php else: ?>
                    <div class="placeholder"><i class="bi bi-image"></i></div>
                    <?php endif; ?>
                </div>
                <div class="product-info">
                    <div class="product-details-grid">
                        <div class="grid-cell"><span class="grid-label">商品名称:</span><span class="grid-value product-name">【<?php echo $product['XEpxb7_game_name']; ?>】<?php echo $product['XEpxb7_product_name']; ?></span></div>
                        <div class="grid-cell"><span class="grid-label">商品编号:</span><span class="grid-value"><?php echo $product['XEpxb7_product_code']; ?></span></div>
                        <div class="grid-cell"><span class="grid-label">金额:</span><span class="grid-value price">¥<?php echo number_format($product['XEpxb7_product_amount'], 2, '.', ''); ?></span></div>
                        <div class="grid-cell"><span class="grid-label">无货立赔:</span><span class="grid-value no-stock-indicator <?php echo $product['XEpxb7_no_stock_compensation']; ?>"><?php echo $product['XEpxb7_no_stock_compensation'] === '是' ? '开启' : '关闭'; ?></span></div>
                        <div class="grid-cell"><span class="grid-label">找回包赔:</span><span class="grid-value retrieve-indicator <?php echo $product['XEpxb7_retrieve_compensation']; ?>"><?php echo $product['XEpxb7_retrieve_compensation'] === '是' ? '开启' : '关闭'; ?></span></div>
                        <div class="grid-cell"><span class="grid-label">客服身份:</span><span class="grid-value"><img src="<?php echo $customer_avatar; ?>" alt="" class="customer-service-icon"><?php echo $product['XEpxb7_customer_service']; ?></span></div>
                        <div class="grid-cell"><span class="grid-label">假人身份:</span><span class="grid-value"><?php echo $product['XEpxb7_dummy_identity'] ?? '买家'; ?></span></div>
                        <div class="grid-cell"><span class="grid-label">群聊状态:</span><span class="grid-value status <?php echo $product['XEpxb7_page_status'] === 'active' ? 'status-active' : 'status-inactive'; ?>"><?php echo $product['XEpxb7_page_status'] === 'active' ? '开启' : '关闭'; ?></span></div>
                        <div class="grid-cell"><span class="grid-label">创建时间:</span><span class="grid-value"><?php echo date('m-d H:i', strtotime($product['XEpxb7_created_at'])); ?></span></div>
                    </div>
                </div>
                <div class="card-actions">
                    <button class="card-btn btn-edit" data-id="<?php echo $product['XEpxb7_id']; ?>" data-platform="pxb7"><i class="bi bi-pencil-square"></i> 编辑</button>
                    <button class="card-btn btn-delete" data-id="<?php echo $product['XEpxb7_id']; ?>" data-platform="pxb7"><i class="bi bi-trash"></i> 删除</button>
                    <button class="card-btn btn-share" data-id="<?php echo $product['XEpxb7_id']; ?>" data-platform="pxb7"><i class="bi bi-share"></i> 分享</button>
                    <button class="card-btn btn-share-image" data-id="<?php echo $product['XEpxb7_id']; ?>" data-platform="pxb7"><i class="bi bi-image"></i> 分享图</button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-state"><div class="empty-icon"><i class="bi bi-terminal-x"></i></div><h3 class="empty-title">暂无聊天室</h3><p class="empty-text">您还没有创建过螃蟹聊天室</p></div>
        <?php endif; ?>
    </div>

    <!-- 盼之群聊内容 -->
    <div class="container tab-content <?php echo $defaultTab === 'pzds' ? 'active' : ''; ?>" id="tab-pzds">
        <?php if (count($products_pzds) > 0): ?>
        <div class="products-grid">
            <?php foreach ($products_pzds as $product):
                $compensation_text = $product['XEpzds_compensation_type'] ?? '全额包赔';
            ?>
            <div class="product-card" data-platform="pzds" id="pzds-card-<?php echo $product['XEpzds_id']; ?>">
                <div class="product-image">
                    <?php if (!empty($product['XEpzds_product_image'])): ?>
                    <img src="<?php echo $product['XEpzds_product_image']; ?>" alt="">
                    <?php else: ?>
                    <div class="placeholder"><i class="bi bi-image"></i></div>
                    <?php endif; ?>
                </div>
                <div class="product-info">
                    <div class="product-details-grid">
                        <div class="grid-cell"><span class="grid-label">商品名称:</span><span class="grid-value product-name"><?php echo $product['XEpzds_product_name']; ?></span></div>
                        <div class="grid-cell"><span class="grid-label">商品编号:</span><span class="grid-value"><?php echo $product['XEpzds_product_code']; ?></span></div>
                        <div class="grid-cell"><span class="grid-label">金额:</span><span class="grid-value price">¥<?php echo number_format($product['XEpzds_product_amount'], 2, '.', ''); ?></span></div>
                        <div class="grid-cell"><span class="grid-label">包赔类型:</span><span class="grid-value compensation-type"><?php echo $compensation_text; ?></span></div>
                        <div class="grid-cell"><span class="grid-label">群聊状态:</span><span class="grid-value status <?php echo $product['XEpzds_page_status'] === 'active' ? 'status-active' : 'status-inactive'; ?>"><?php echo $product['XEpzds_page_status'] === 'active' ? '开启' : '关闭'; ?></span></div>
                        <div class="grid-cell"><span class="grid-label">创建时间:</span><span class="grid-value"><?php echo date('m-d H:i', strtotime($product['XEpzds_created_at'])); ?></span></div>
                    </div>
                </div>
                <div class="card-actions">
                    <button class="card-btn btn-edit" data-id="<?php echo $product['XEpzds_id']; ?>" data-platform="pzds"><i class="bi bi-pencil-square"></i> 编辑</button>
                    <button class="card-btn btn-delete" data-id="<?php echo $product['XEpzds_id']; ?>" data-platform="pzds"><i class="bi bi-trash"></i> 删除</button>
                    <button class="card-btn btn-share" data-id="<?php echo $product['XEpzds_id']; ?>" data-platform="pzds"><i class="bi bi-share"></i> 分享</button>
                    <button class="card-btn btn-share-image" data-id="<?php echo $product['XEpzds_id']; ?>" data-platform="pzds"><i class="bi bi-image"></i> 分享图</button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-state"><div class="empty-icon"><i class="bi bi-terminal-x"></i></div><h3 class="empty-title">暂无聊天室</h3><p class="empty-text">您还没有创建过盼之聊天室</p></div>
        <?php endif; ?>
    </div>

    <!-- 白情群聊内容 -->
    <div class="container tab-content <?php echo $defaultTab === 'youxige' ? 'active' : ''; ?>" id="tab-youxige">
        <?php if (count($chatrooms_youxige) > 0): ?>
        <div class="products-grid">
            <?php foreach ($chatrooms_youxige as $chatroom): ?>
            <div class="product-card" data-platform="youxige" id="youxige-card-<?php echo $chatroom['XEyouxige_id']; ?>">
                <div class="product-image">
                    <?php if (!empty($chatroom['XEyouxige_seller_avatar'])): ?>
                    <img src="<?php echo $chatroom['XEyouxige_seller_avatar']; ?>" alt="">
                    <?php else: ?>
                    <div class="placeholder"><i class="bi bi-person-circle"></i></div>
                    <?php endif; ?>
                </div>
                <div class="product-info">
                    <div class="product-details-grid">
                        <div class="grid-cell"><span class="grid-label">交易员名称:</span><span class="grid-value product-name"><?php echo $chatroom['XEyouxige_trader_name']; ?></span></div>
                        <div class="grid-cell"><span class="grid-label">群聊编号:</span><span class="grid-value"><?php echo $chatroom['XEyouxige_group_code']; ?></span></div>
                        <div class="grid-cell"><span class="grid-label">群聊状态:</span><span class="grid-value status <?php echo $chatroom['XEyouxige_page_status'] === 'active' ? 'status-active' : 'status-inactive'; ?>"><?php echo $chatroom['XEyouxige_page_status'] === 'active' ? '开启' : '关闭'; ?></span></div>
                        <div class="grid-cell"><span class="grid-label">创建时间:</span><span class="grid-value"><?php echo date('m-d H:i', strtotime($chatroom['XEyouxige_created_at'])); ?></span></div>
                    </div>
                </div>
                <div class="card-actions">
                    <button class="card-btn btn-edit" data-id="<?php echo $chatroom['XEyouxige_id']; ?>" data-platform="youxige"><i class="bi bi-pencil-square"></i> 编辑</button>
                    <button class="card-btn btn-delete" data-id="<?php echo $chatroom['XEyouxige_id']; ?>" data-platform="youxige"><i class="bi bi-trash"></i> 删除</button>
                    <button class="card-btn btn-share" data-id="<?php echo $chatroom['XEyouxige_id']; ?>" data-platform="youxige"><i class="bi bi-share"></i> 分享</button>
                    <button class="card-btn btn-share-image" data-id="<?php echo $chatroom['XEyouxige_id']; ?>" data-platform="youxige"><i class="bi bi-image"></i> 分享图</button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-state"><div class="empty-icon"><i class="bi bi-terminal-x"></i></div><h3 class="empty-title">暂无群聊</h3><p class="empty-text">您还没有创建过白情群聊</p></div>
        <?php endif; ?>
    </div>

    <!-- 添加/编辑模态框 -->
    <div class="modal-overlay" id="productModal">
        <div class="modal-content">
            <form method="POST" action="" id="productForm" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">群聊设置</h5>
                    <button type="button" class="modal-close" onclick="closeModal()">×</button>
                </div>
                <div class="modal-body" id="modalBody">
                    <input type="hidden" name="platform" id="formPlatform" value="">
                    <input type="hidden" name="action" id="formAction" value="">
                    <input type="hidden" name="id" id="productId" value="0">
                    <input type="hidden" name="old_product_image" id="old_product_image" value="">
                    <input type="hidden" name="old_seller_avatar" id="old_seller_avatar" value="">
                    <input type="hidden" name="default_avatar_used" id="defaultAvatarUsed" value="0">
                    <input type="hidden" name="seller_avatar_url" id="sellerAvatarUrl" value="">
                    <!-- 动态表单内容区域 -->
                    <div id="formFieldsContainer"></div>
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
            <p class="confirm-text">您确定要删除吗？此操作不可撤销。</p>
            <div class="confirm-buttons">
                <button class="confirm-btn confirm-cancel" onclick="closeConfirmModal()">取消</button>
                <button class="confirm-btn confirm-delete" id="confirmDelete">确认删除</button>
            </div>
        </div>
    </div>

    <!-- 分享模态框 -->
    <div class="confirm-overlay" id="shareModal">
        <div class="share-modal-content">
            <div class="share-header">
                <h3 class="share-title">分享</h3>
                <button type="button" class="modal-close" onclick="closeShareModal()" style="position:absolute;right:15px;top:15px;font-size:24px;background:none;border:none;color:#666;cursor:pointer;">×</button>
            </div>
            <div class="share-url-container">
                <div class="share-url" id="shareUrlText"></div>
            </div>
            <div class="share-buttons">
                <button class="share-btn share-btn-copy" id="shareCopyBtn"><i class="bi bi-clipboard"></i> 复制链接</button>
            </div>
        </div>
    </div>

    <!-- 分享图模态框 -->
    <div class="confirm-overlay" id="shareImageModal">
        <div class="share-image-modal">
            <div class="share-image-header" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;">
                <h3 style="margin:0;font-size:18px;font-weight:600;" id="shareImageTitle">分享图</h3>
                <button type="button" class="modal-close" onclick="closeShareImageModal()" style="font-size:24px;background:none;border:none;color:#666;cursor:pointer;">×</button>
            </div>
            <div class="qrPoster" id="shareImageContainer">
                <div class="qrPosterInner">
                    <div class="qrPosterTop">
                        <img class="qrPosterAvatar" id="shareImagePosterAvatar" src="/assets/img/pxqr.png" alt="头像">
                        <div class="qrPosterName" id="shareImagePosterName">商品名称</div>
                        <div class="qrPosterBadges">
                            <span class="qrPosterTag" id="shareImagePosterTag">找回包赔</span>
                            <span class="qrBadge hours" id="shareImagePosterHours">无货立赔</span>
                        </div>
                        <div class="qrPosterHint" id="shareImagePosterHint">请扫描下方二维码加入群聊</div>
                    </div>
                    <div class="qrDivider"></div>
                    <div class="qrPosterMid">
                        <div class="qrBox"><div id="shareImageQrCodeBox"></div></div>
                        <div class="qrPosterScan">建议使用浏览器"扫一扫"识别（更稳定）</div>
                    </div>
                    <div class="qrPosterFoot">提示：切勿将二维码转发给他人！</div>
                </div>
            </div>
            <div class="share-image-actions" style="display:flex;gap:10px;margin-top:15px;">
                <button class="share-image-btn" id="returnFromImageBtn" style="flex:1;padding:12px;background:#f2f2f7;border:none;border-radius:10px;font-size:14px;cursor:pointer;"><i class="bi bi-arrow-left"></i> 返回</button>
                <button class="share-image-btn" id="saveShareImageBtn" style="flex:1;padding:12px;background:#007AFF;color:white;border:none;border-radius:10px;font-size:14px;cursor:pointer;"><i class="bi bi-download"></i> 保存图片</button>
            </div>
        </div>
    </div>

    <!-- 删除确认表单 -->
    <form id="deleteForm" method="POST" style="display:none;">
        <input type="hidden" name="platform" id="deletePlatform" value="">
        <input type="hidden" name="action" id="deleteAction" value="">
        <input type="hidden" name="id" id="deleteId" value="">
    </form>

<script>
// ==================== 数据存储 ====================
var productsData_pxb7 = {
    <?php foreach ($products_pxb7 as $p): ?>
    <?php echo $p['XEpxb7_id']; ?>: {
        product_name: "<?php echo addslashes($p['XEpxb7_product_name']); ?>",
        game_name: "<?php echo addslashes($p['XEpxb7_game_name'] ?? ''); ?>",
        product_code: "<?php echo addslashes($p['XEpxb7_product_code'] ?? ''); ?>",
        product_amount: "<?php echo $p['XEpxb7_product_amount']; ?>",
        no_stock_compensation: "<?php echo $p['XEpxb7_no_stock_compensation']; ?>",
        retrieve_compensation: "<?php echo $p['XEpxb7_retrieve_compensation']; ?>",
        customer_service: "<?php echo $p['XEpxb7_customer_service']; ?>",
        dummy_identity: "<?php echo $p['XEpxb7_dummy_identity'] ?? '买家'; ?>",
        page_status: "<?php echo $p['XEpxb7_page_status']; ?>",
        product_image: "<?php echo addslashes($p['XEpxb7_product_image'] ?? ''); ?>",
        seller_avatar: "<?php echo addslashes($p['XEpxb7_seller_avatar'] ?? ''); ?>",
        page_code: "<?php echo addslashes($p['XEpxb7_page_code']); ?>"
    },
    <?php endforeach; ?>
};

var productsData_pzds = {
    <?php foreach ($products_pzds as $p): ?>
    <?php echo $p['XEpzds_id']; ?>: {
        product_name: "<?php echo addslashes($p['XEpzds_product_name']); ?>",
        product_code: "<?php echo addslashes($p['XEpzds_product_code'] ?? ''); ?>",
        product_amount: "<?php echo $p['XEpzds_product_amount']; ?>",
        compensation_type: "<?php echo $p['XEpzds_compensation_type']; ?>",
        page_status: "<?php echo $p['XEpzds_page_status']; ?>",
        product_image: "<?php echo addslashes($p['XEpzds_product_image'] ?? ''); ?>",
        seller_avatar: "<?php echo addslashes($p['XEpzds_seller_avatar'] ?? ''); ?>",
        page_code: "<?php echo addslashes($p['XEpzds_page_code']); ?>"
    },
    <?php endforeach; ?>
};

var chatroomsData_youxige = <?php
    $data = [];
    foreach ($chatrooms_youxige as $c) {
        $data[$c['XEyouxige_id']] = [
            'trader_name' => $c['XEyouxige_trader_name'],
            'group_code' => $c['XEyouxige_group_code'] ?? '',
            'welcome_message' => $c['XEyouxige_welcome_message'] ?? '',
            'page_status' => $c['XEyouxige_page_status'],
            'seller_avatar' => $c['XEyouxige_seller_avatar'] ?? '',
            'page_code' => $c['XEyouxige_page_code']
        ];
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT);
?>;

// ==================== 全局状态 ====================
const IMAGE_MAX_SIZE = 2 * 1024 * 1024;
const ALLOWED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
const DEFAULT_AVATAR_MAP = {
    'pxb7': '/assets/img/dummy1.png',
    'pzds': '/assets/img/pz-yh.png',
    'youxige': '/assets/img/bq-yh.png'
};
function getDefaultAvatar() { return DEFAULT_AVATAR_MAP[currentPlatform] || '/assets/img/dummy1.png'; }
function isDefaultAvatar(path) { return Object.values(DEFAULT_AVATAR_MAP).includes(path); }
const CUSTOMER_SERVICE_AVATARS = {
    '螃蟹交易专员': '/assets/img/px-kf.png',
    '螃蟹咨询专员': '/assets/img/px-kf2.png',
    '螃蟹售后专员': '/assets/img/px-kf3.jpg'
};

let currentPlatform = '<?php echo $defaultTab; ?>';
let currentShareUrl = '';
let currentItemId = 0;
let currentSessionId = '';
let userAntiRedConfig = null;
let siteUrlConfig = <?php echo $siteUrlJson; ?>;

// ==================== 工具函数 ====================
function showNotification(message, type = 'success', duration = 3000) {
    const existingToast = document.querySelector('.new-user-toast');
    if (existingToast) { existingToast.classList.add('notification-leave-active'); setTimeout(() => { if (existingToast.parentNode) existingToast.parentNode.removeChild(existingToast); }, 300); }
    const toast = document.createElement('div');
    toast.className = `new-user-toast notification-enter-active ${type}`;
    let iconChar = '✓'; if (type === 'info') iconChar = 'i'; if (type === 'warning') iconChar = '!'; if (type === 'error') iconChar = '×';
    toast.innerHTML = `<div class="toast-icon">${iconChar}</div><div class="toast-text">${message}</div><button class="toast-close">×</button>`;
    document.body.appendChild(toast);
    toast.querySelector('.toast-close').addEventListener('click', function(e) { e.stopPropagation(); toast.classList.add('notification-leave-active'); setTimeout(() => { if (toast.parentNode) toast.parentNode.removeChild(toast); }, 300); });
    setTimeout(() => { if (toast.parentNode) { toast.classList.add('notification-leave-active'); setTimeout(() => { if (toast.parentNode) toast.parentNode.removeChild(toast); }, 300); } }, duration);
}

function validateFile(file) {
    if (file.size > IMAGE_MAX_SIZE) { showNotification('文件大小不能超过2MB', 'error'); return false; }
    if (!ALLOWED_IMAGE_TYPES.includes(file.type)) { showNotification('只支持JPG、PNG、GIF、WebP格式的图片', 'error'); return false; }
    return true;
}

function generateRandomCode() {
    const letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'; let code = '';
    code += letters.charAt(Math.floor(Math.random() * letters.length));
    code += Math.floor(Math.random() * 9) + 1;
    for (let i = 0; i < 3; i++) code += letters.charAt(Math.floor(Math.random() * letters.length));
    code += Math.floor(Math.random() * 9) + 1;
    return code;
}

// ==================== MD5 (螃蟹用) ====================
function simpleMd5(inputString) {
    const rotateLeft = (n, s) => (n << s) | (n >>> (32 - s));
    const addUnsigned = (x, y) => { const lsw = (x & 0xFFFF) + (y & 0xFFFF); const msw = (x >> 16) + (y >> 16) + (lsw >> 16); return (msw << 16) | (lsw & 0xFFFF); };
    const bytesToHex = (bytes) => { let hex = ''; for (let i = 0; i < bytes.length; i++) hex += (bytes[i] < 16 ? '0' : '') + bytes[i].toString(16); return hex; };
    const padding = [0x80,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0];
    const s = [7,12,17,22,7,12,17,22,7,12,17,22,7,12,17,22,5,9,14,20,5,9,14,20,5,9,14,20,5,9,14,20,4,11,16,23,4,11,16,23,4,11,16,23,4,11,16,23,6,10,15,21,6,10,15,21,6,10,15,21,6,10,15,21];
    const k = []; for (let i = 0; i < 64; i++) k[i] = Math.floor(Math.abs(Math.sin(i + 1)) * Math.pow(2, 32));
    let bytes = []; for (let i = 0; i < inputString.length; i++) bytes.push(inputString.charCodeAt(i));
    const bitLength = bytes.length * 8;
    bytes.push(0x80); while (bytes.length % 64 !== 56) bytes.push(0);
    for (let i = 0; i < 8; i++) bytes.push((bitLength >>> (8 * i)) & 0xFF);
    let a0 = 0x67452301, b0 = 0xEFCDAB89, c0 = 0x98BADCFE, d0 = 0x10325476;
    for (let i = 0; i < bytes.length; i += 64) {
        const M = []; for (let j = 0; j < 16; j++) M[j] = (bytes[i+j*4]<<24)|(bytes[i+j*4+1]<<16)|(bytes[i+j*4+2]<<8)|bytes[i+j*4+3];
        let A=a0,B=b0,C=c0,D=d0;
        for (let j = 0; j < 64; j++) { let F,g; if(j<16){F=(B&C)|(~B&D);g=j;}else if(j<32){F=(D&B)|(~D&C);g=(5*j+1)%16;}else if(j<48){F=B^C^D;g=(3*j+5)%16;}else{F=C^(B|~D);g=(7*j)%16;} const dTemp=D;D=C;C=B;B=addUnsigned(B,rotateLeft(addUnsigned(addUnsigned(A,F),addUnsigned(k[j],M[g])),s[j]));A=dTemp; }
        a0=addUnsigned(a0,A);b0=addUnsigned(b0,B);c0=addUnsigned(c0,C);d0=addUnsigned(d0,D);
    }
    return bytesToHex([(a0>>24)&0xFF,(a0>>16)&0xFF,(a0>>8)&0xFF,a0&0xFF,(b0>>24)&0xFF,(b0>>16)&0xFF,(b0>>8)&0xFF,b0&0xFF,(c0>>24)&0xFF,(c0>>16)&0xFF,(c0>>8)&0xFF,c0&0xFF,(d0>>24)&0xFF,(d0>>16)&0xFF,(d0>>8)&0xFF,d0&0xFF]);
}

function simpleHash(input) {
    let hash = 5381;
    for (let i = 0; i < input.length; i++) hash = ((hash << 5) + hash) + input.charCodeAt(i);
    return Math.abs(hash).toString(36);
}

// ==================== 分享链接生成 ====================
function generateShareUrl(platform, itemData) {
    const username = '<?php echo isset($_SESSION['username']) ? $_SESSION['username'] : 'default'; ?>';
    const characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
    let baseUrl, productCode, customerName = '', fixedSeed;

    if (platform === 'pxb7') {
        baseUrl = window.location.origin + '/Pxb7';
        productCode = itemData.page_code;
        fixedSeed = simpleMd5(productCode + (username || 'default'));
        for (let i = 0; i < 6; i++) customerName += characters[parseInt(fixedSeed.substr(i * 2, 2), 16) % characters.length];
    } else if (platform === 'pzds') {
        baseUrl = window.location.origin + '/Pzds';
        productCode = itemData.page_code;
        fixedSeed = simpleHash(productCode + (username || 'default'));
        for (let i = 0; i < 6; i++) { const index = parseInt(fixedSeed.substr(i, 1), 36) || (i * 7) % characters.length; customerName += characters[index % characters.length]; }
    } else {
        baseUrl = window.location.origin + '/Youxige';
        productCode = itemData.page_code;
        fixedSeed = simpleHash(productCode + (username || 'default'));
        for (let i = 0; i < 6; i++) { const index = parseInt(fixedSeed.substr(i, 1), 36) || (i * 7) % characters.length; customerName += characters[index % characters.length]; }
    }

    currentSessionId = 'a' + customerName + 'z-p' + (username || 'default') + 's';
    const originalUrl = `${baseUrl}?XEchatroom=${productCode}&id=${currentSessionId}`;

    if (userAntiRedConfig && userAntiRedConfig.apply_status === 'on' && (userAntiRedConfig.applied_domain || userAntiRedConfig.api_url)) {
        let apiUrl = userAntiRedConfig.api_url || userAntiRedConfig.applied_domain;
        if (apiUrl && !apiUrl.startsWith('http')) apiUrl = 'http://' + apiUrl;
        apiUrl = apiUrl.replace(/\/+$/, '');
        try { return apiUrl + btoa(originalUrl); } catch(e) { return originalUrl; }
    }

    // 源站URL跳转
    if (siteUrlConfig && siteUrlConfig.site_url_enabled && siteUrlConfig.site_url) {
        try { return siteUrlConfig.site_url + btoa(originalUrl); } catch(e) { return originalUrl; }
    }

    return originalUrl;
}

// ==================== 防红配置 ====================
async function apiCall(action, data = {}) {
    const formData = new FormData(); formData.append('action', action);
    Object.keys(data).forEach(key => formData.append(key, data[key]));
    const response = await fetch('/config/domain_api.php', { method: 'POST', body: formData });
    if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
    return await response.json();
}

async function loadAntiRedConfig() {
    try {
        const response = await apiCall('get_user_anti_red_config');
        if (response.success && response.config) {
            const config = response.config;
            if (!config.api_url && config.applied_domain) config.api_url = `${config.applied_domain}/`;
            return config;
        }
    } catch (error) { console.error('加载防红配置失败:', error); }
    return null;
}

function updateAntiRedStatus(domainName = '') {
    const el = document.getElementById('current-anti-red-domain');
    if (!el) return;
    if (domainName) { el.textContent = '已应用'; el.style.color = '#28a745'; }
    else { el.textContent = '未配置'; el.style.color = '#666'; }
}

// ==================== Tab切换 ====================
function switchTab(tab) {
    currentPlatform = tab;
    document.querySelectorAll('.group-tab-item').forEach(item => item.classList.remove('active'));
    document.querySelector(`.group-tab-item[data-tab="${tab}"]`).classList.add('active');
    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
    document.getElementById(`tab-${tab}`).classList.add('active');
    const slider = document.querySelector('.group-tab-slider');
    slider.classList.remove('at-pzds', 'at-youxige');
    if (tab === 'pzds') slider.classList.add('at-pzds');
    else if (tab === 'youxige') slider.classList.add('at-youxige');
}

// ==================== 表单动态生成 ====================
function getFormHTML(platform) {
    if (platform === 'pxb7') {
        return `
        <div class="form-group"><label for="XEpxb7_product_name" class="form-label required">商品名称</label><input type="text" class="form-input" id="XEpxb7_product_name" name="XEpxb7_product_name" required placeholder="例如：王者荣耀V10账号"></div>
        <div class="form-group"><label for="XEpxb7_game_name" class="form-label">游戏名称</label><input type="text" class="form-input" id="XEpxb7_game_name" name="XEpxb7_game_name" placeholder="例如：王者荣耀、英雄联盟、原神等"></div>
        <div class="form-group"><label for="XEpxb7_product_code" class="form-label required">商品编号</label><div class="input-with-button"><input type="text" class="form-input" id="XEpxb7_product_code" name="XEpxb7_product_code" placeholder="例如：F1JSM1"><button type="button" class="random-code-btn" id="generateRandomCode"><i class="bi bi-shuffle"></i> 随机编号</button></div></div>
        <div class="form-group"><label for="XEpxb7_product_amount" class="form-label required">商品金额</label><input type="number" class="form-input" id="XEpxb7_product_amount" name="XEpxb7_product_amount" step="0.01" min="0.01" required placeholder="0.00"></div>
        <div class="form-group"><label for="XEpxb7_no_stock_compensation" class="form-label required">是否开启无货立赔</label><select class="form-select" id="XEpxb7_no_stock_compensation" name="XEpxb7_no_stock_compensation" required><option value="是">是</option><option value="否">否</option></select></div>
        <div class="form-group"><label for="XEpxb7_retrieve_compensation" class="form-label required">是否开启找回包赔</label><select class="form-select" id="XEpxb7_retrieve_compensation" name="XEpxb7_retrieve_compensation" required><option value="是">是</option><option value="否">否</option></select></div>
        <div class="form-group"><label for="XEpxb7_customer_service" class="form-label required">选择客服身份</label><select class="form-select" id="XEpxb7_customer_service" name="XEpxb7_customer_service" required><option value="螃蟹交易专员" data-avatar="/assets/img/px-kf.png">螃蟹交易专员</option><option value="螃蟹咨询专员" data-avatar="/assets/img/px-kf2.png">螃蟹咨询专员</option><option value="螃蟹售后专员" data-avatar="/assets/img/px-kf3.jpg">螃蟹售后专员</option></select><div class="avatar-preview" id="customerAvatarPreview" style="margin-top:8px;display:flex;align-items:center;gap:8px;"><img src="/assets/img/px-kf.png" alt="客服头像预览" style="width:32px;height:32px;border-radius:50%;object-fit:cover;"><span style="font-size:12px;color:#666;">当前选择头像预览</span></div></div>
        <div class="form-group"><label for="XEpxb7_dummy_identity" class="form-label required">假人身份</label><select class="form-select" id="XEpxb7_dummy_identity" name="XEpxb7_dummy_identity" required><option value="买家">买家</option><option value="卖家">卖家</option></select></div>
        <div class="form-group"><label for="XEpxb7_page_status" class="form-label required">群聊状态</label><select class="form-select" id="XEpxb7_page_status" name="XEpxb7_page_status" required><option value="active">开启</option><option value="inactive">关闭</option></select></div>
        <div class="form-group"><label class="form-label required">商品图片</label><div class="upload-container" id="productImageUpload"><div class="upload-icon"><i class="bi bi-image"></i></div><div class="upload-text">点击上传商品图片</div><div class="upload-hint">支持 JPG、PNG、GIF、WebP 格式，最大 2MB</div><input type="file" class="upload-input" id="XEpxb7_product_image" name="XEpxb7_product_image" accept="image/*"></div><div id="currentProductImage" class="current-image" style="display:none;"><span class="current-image-label">当前图片：</span><img src="" alt="" class="current-image-preview"><span class="current-image-text"></span></div><div class="upload-preview" id="productImagePreview"><button type="button" class="preview-remove" onclick="removeProductImage()">×</button><img src="" alt="" class="preview-image" id="productImagePreviewImg"><div class="preview-info"><span>新图片：</span><span class="preview-name" id="productImageName"></span></div></div></div>
        <div class="form-group"><label class="form-label required">卖家头像</label><div class="upload-container" id="sellerAvatarUpload"><div class="upload-icon"><i class="bi bi-person-circle"></i></div><div class="upload-text">点击上传卖家头像</div><div class="upload-hint">支持 JPG、PNG、GIF、WebP 格式，最大 2MB</div><input type="file" class="upload-input" id="XEpxb7_seller_avatar" name="XEpxb7_seller_avatar" accept="image/*"></div><div class="default-avatar-container" style="margin-top:10px;"><button type="button" class="default-avatar-btn" id="useDefaultAvatar"><i class="bi bi-person-check"></i> 使用默认头像</button></div><div id="currentSellerAvatar" class="current-image" style="display:none;"><span class="current-image-label">当前头像：</span><img src="" alt="" class="current-image-preview"><span class="current-image-text"></span></div><div class="upload-preview" id="avatarPreview"><button type="button" class="preview-remove" onclick="removeSellerAvatar()">×</button><img src="" alt="" class="preview-image" id="avatarPreviewImg"><div class="preview-info"><span>新头像：</span><span class="preview-name" id="avatarImageName"></span></div></div></div>`;
    } else if (platform === 'pzds') {
        return `
        <div class="form-group"><label for="XEpzds_product_name" class="form-label required">商品名称</label><input type="text" class="form-input" id="XEpzds_product_name" name="XEpzds_product_name" required placeholder="例如：王者荣耀V10账号"></div>
        <div class="form-group"><label for="XEpzds_product_code" class="form-label required">商品编号</label><div class="input-with-button"><input type="text" class="form-input" id="XEpzds_product_code" name="XEpzds_product_code" placeholder="例如：F1JSM1"><button type="button" class="random-code-btn" id="generateRandomCode"><i class="bi bi-shuffle"></i> 随机编号</button></div></div>
        <div class="form-group"><label for="XEpzds_product_amount" class="form-label required">商品金额</label><input type="number" class="form-input" id="XEpzds_product_amount" name="XEpzds_product_amount" step="0.01" min="0.01" required placeholder="0.00"></div>
        <div class="form-group"><label for="XEpzds_compensation_type" class="form-label required">包赔类型</label><select class="form-select" id="XEpzds_compensation_type" name="XEpzds_compensation_type" required><option value="全额包赔">全额包赔</option><option value="双倍包赔">双倍包赔</option><option value="充值包赔">充值包赔</option></select></div>
        <div class="form-group"><label for="XEpzds_page_status" class="form-label required">群聊状态</label><select class="form-select" id="XEpzds_page_status" name="XEpzds_page_status" required><option value="active">开启</option><option value="inactive">关闭</option></select></div>
        <div class="form-group"><label class="form-label required">商品图片</label><div class="upload-container" id="productImageUpload"><div class="upload-icon"><i class="bi bi-image"></i></div><div class="upload-text">点击上传商品图片</div><div class="upload-hint">支持 JPG、PNG、GIF、WebP 格式，最大 2MB</div><input type="file" class="upload-input" id="XEpzds_product_image" name="XEpzds_product_image" accept="image/*"></div><div id="currentProductImage" class="current-image" style="display:none;"><span class="current-image-label">当前图片：</span><img src="" alt="" class="current-image-preview"><span class="current-image-text"></span></div><div class="upload-preview" id="productImagePreview"><button type="button" class="preview-remove" onclick="removeProductImage()">×</button><img src="" alt="" class="preview-image" id="productImagePreviewImg"><div class="preview-info"><span>新图片：</span><span class="preview-name" id="productImageName"></span></div></div></div>
        <div class="form-group"><label class="form-label required">卖家头像</label><div class="upload-container" id="sellerAvatarUpload"><div class="upload-icon"><i class="bi bi-person-circle"></i></div><div class="upload-text">点击上传卖家头像</div><div class="upload-hint">支持 JPG、PNG、GIF、WebP 格式，最大 2MB</div><input type="file" class="upload-input" id="XEpzds_seller_avatar" name="XEpzds_seller_avatar" accept="image/*"></div><div class="default-avatar-container" style="margin-top:10px;"><button type="button" class="default-avatar-btn" id="useDefaultAvatar"><i class="bi bi-person-check"></i> 使用默认头像</button></div><div id="currentSellerAvatar" class="current-image" style="display:none;"><span class="current-image-label">当前头像：</span><img src="" alt="" class="current-image-preview"><span class="current-image-text"></span></div><div class="upload-preview" id="avatarPreview"><button type="button" class="preview-remove" onclick="removeSellerAvatar()">×</button><img src="" alt="" class="preview-image" id="avatarPreviewImg"><div class="preview-info"><span>新头像：</span><span class="preview-name" id="avatarImageName"></span></div></div></div>`;
    } else {
        return `
        <div class="form-group"><label for="XEyouxige_trader_name" class="form-label required">交易员名称</label><input type="text" class="form-input" id="XEyouxige_trader_name" name="XEyouxige_trader_name" required placeholder="例如：盼之客服小张"></div>
        <div class="form-group"><label for="XEyouxige_group_code" class="form-label required">群聊编号</label><div class="input-with-button"><input type="text" class="form-input" id="XEyouxige_group_code" name="XEyouxige_group_code" placeholder="例如：F1JSM1"><button type="button" class="random-code-btn" id="generateRandomCode"><i class="bi bi-shuffle"></i> 随机编号</button></div></div>
        <div class="form-group"><label for="XEyouxige_welcome_message" class="form-label required">欢迎语</label><textarea class="form-input" id="XEyouxige_welcome_message" name="XEyouxige_welcome_message" rows="3" placeholder="您好!欢迎来到本平台交易，我是您的专属客服。"></textarea></div>
        <div class="form-group"><label for="XEyouxige_page_status" class="form-label required">群聊状态</label><select class="form-select" id="XEyouxige_page_status" name="XEyouxige_page_status" required><option value="active">开启</option><option value="inactive">关闭</option></select></div>
        <div class="form-group"><label class="form-label required">卖家头像</label><div class="upload-container" id="sellerAvatarUpload"><div class="upload-icon"><i class="bi bi-person-circle"></i></div><div class="upload-text">点击上传卖家头像</div><div class="upload-hint">支持 JPG、PNG、GIF、WebP 格式，最大 2MB</div><input type="file" class="upload-input" id="XEyouxige_seller_avatar" name="XEyouxige_seller_avatar" accept="image/*"></div><div class="default-avatar-container" style="margin-top:10px;"><button type="button" class="default-avatar-btn" id="useDefaultAvatar"><i class="bi bi-person-check"></i> 使用默认头像</button></div><div id="currentSellerAvatar" class="current-image" style="display:none;"><span class="current-image-label">当前头像：</span><img src="" alt="" class="current-image-preview"><span class="current-image-text"></span></div><div class="upload-preview" id="avatarPreview"><button type="button" class="preview-remove" onclick="removeSellerAvatar()">×</button><img src="" alt="" class="preview-image" id="avatarPreviewImg"><div class="preview-info"><span>新头像：</span><span class="preview-name" id="avatarImageName"></span></div></div></div>`;
    }
}

function getDataStore(platform) {
    if (platform === 'pxb7') return productsData_pxb7;
    if (platform === 'pzds') return productsData_pzds;
    return chatroomsData_youxige;
}

// ==================== 模态框管理 ====================
function openModal(mode = 'add', id = null) {
    const platform = currentPlatform;
    document.getElementById('formPlatform').value = platform;
    document.getElementById('formFieldsContainer').innerHTML = getFormHTML(platform);

    if (platform === 'youxige') {
        document.getElementById('formAction').value = 'save_chatroom';
    } else {
        document.getElementById('formAction').value = 'save_product';
    }

    if (mode === 'add') {
        document.getElementById('modalTitle').textContent = '订单信息';
        document.getElementById('submitBtn').textContent = '制作订单';
        document.getElementById('productId').value = '0';
        document.getElementById('old_product_image').value = '';
        document.getElementById('old_seller_avatar').value = '';
        document.getElementById('defaultAvatarUsed').value = '0';
        document.getElementById('sellerAvatarUrl').value = '';
    } else if (mode === 'edit' && id) {
        document.getElementById('modalTitle').textContent = '编辑订单';
        document.getElementById('submitBtn').textContent = '更新订单';
        loadData(platform, id);
    }

    setupFormEvents(platform);
    document.getElementById('productModal').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function loadData(platform, id) {
    const data = getDataStore(platform);
    const numericId = parseInt(id);
    if (!data || !data[numericId]) { showNotification('加载数据失败', 'error'); return; }
    const item = data[numericId];
    document.getElementById('productId').value = numericId;

    if (platform === 'pxb7') {
        document.getElementById('XEpxb7_product_name').value = item.product_name || '';
        document.getElementById('XEpxb7_game_name').value = item.game_name || '';
        document.getElementById('XEpxb7_product_code').value = item.product_code || '';
        document.getElementById('XEpxb7_product_amount').value = item.product_amount || '';
        document.getElementById('XEpxb7_no_stock_compensation').value = item.no_stock_compensation || '否';
        document.getElementById('XEpxb7_retrieve_compensation').value = item.retrieve_compensation || '否';
        document.getElementById('XEpxb7_customer_service').value = item.customer_service || '螃蟹交易专员';
        document.getElementById('XEpxb7_dummy_identity').value = item.dummy_identity || '买家';
        document.getElementById('XEpxb7_page_status').value = item.page_status || 'active';
        document.getElementById('old_product_image').value = item.product_image || '';
        document.getElementById('old_seller_avatar').value = item.seller_avatar || '';
        if (isDefaultAvatar(item.seller_avatar)) { document.getElementById('defaultAvatarUsed').value = '1'; document.getElementById('sellerAvatarUrl').value = item.seller_avatar; }
        displayExistingImage(item.product_image, 'product');
        displayExistingImage(item.seller_avatar, 'avatar');
        // 更新客服头像预览
        const sel = document.getElementById('XEpxb7_customer_service');
        if (sel) { const opt = sel.querySelector(`option[value="${item.customer_service}"]`); if (opt) { const img = document.querySelector('#customerAvatarPreview img'); if (img) img.src = opt.getAttribute('data-avatar') || '/assets/img/px-kf.png'; } }
    } else if (platform === 'pzds') {
        document.getElementById('XEpzds_product_name').value = item.product_name || '';
        document.getElementById('XEpzds_product_code').value = item.product_code || '';
        document.getElementById('XEpzds_product_amount').value = item.product_amount || '';
        document.getElementById('XEpzds_compensation_type').value = item.compensation_type || '全额包赔';
        document.getElementById('XEpzds_page_status').value = item.page_status || 'active';
        document.getElementById('old_product_image').value = item.product_image || '';
        document.getElementById('old_seller_avatar').value = item.seller_avatar || '';
        if (isDefaultAvatar(item.seller_avatar)) { document.getElementById('defaultAvatarUsed').value = '1'; document.getElementById('sellerAvatarUrl').value = item.seller_avatar; }
        displayExistingImage(item.product_image, 'product');
        displayExistingImage(item.seller_avatar, 'avatar');
    } else {
        document.getElementById('XEyouxige_trader_name').value = item.trader_name || '';
        document.getElementById('XEyouxige_group_code').value = item.group_code || '';
        document.getElementById('XEyouxige_welcome_message').value = item.welcome_message || '';
        document.getElementById('XEyouxige_page_status').value = item.page_status || 'active';
        document.getElementById('old_seller_avatar').value = item.seller_avatar || '';
        if (isDefaultAvatar(item.seller_avatar)) { document.getElementById('defaultAvatarUsed').value = '1'; document.getElementById('sellerAvatarUrl').value = item.seller_avatar; }
        displayExistingImage(item.seller_avatar, 'avatar');
    }
}

function displayExistingImage(imageUrl, type) {
    if (!imageUrl) return;
    const isProduct = type === 'product';
    const el = document.getElementById(isProduct ? 'currentProductImage' : 'currentSellerAvatar');
    if (!el) return;
    const img = el.querySelector('img');
    const txt = el.querySelector('.current-image-text');
    if (img) img.src = imageUrl;
    if (txt) txt.textContent = isProduct ? '已上传商品图片' : '已上传卖家头像';
    el.style.display = 'flex';
}

function setupFormEvents(platform) {
    // 随机编号
    const randomBtn = document.getElementById('generateRandomCode');
    if (randomBtn) {
        randomBtn.addEventListener('click', function() {
            const code = generateRandomCode();
            const inputId = platform === 'pxb7' ? 'XEpxb7_product_code' : (platform === 'pzds' ? 'XEpzds_product_code' : 'XEyouxige_group_code');
            const input = document.getElementById(inputId);
            if (input) { input.value = code; input.dispatchEvent(new Event('input')); }
            this.classList.add('success');
            this.innerHTML = '<i class="bi bi-check-lg"></i> 已生成';
            showNotification(`已生成编号: ${code}`);
            setTimeout(() => { this.classList.remove('success'); this.innerHTML = '<i class="bi bi-shuffle"></i> 随机编号'; }, 1000);
        });
    }

    // 默认头像
    const defaultBtn = document.getElementById('useDefaultAvatar');
    if (defaultBtn) {
        defaultBtn.addEventListener('click', function() {
            const defaultAvatar = getDefaultAvatar();
            document.getElementById('defaultAvatarUsed').value = '1';
            document.getElementById('sellerAvatarUrl').value = defaultAvatar;
            const avatarInputId = platform === 'pxb7' ? 'XEpxb7_seller_avatar' : (platform === 'pzds' ? 'XEpzds_seller_avatar' : 'XEyouxige_seller_avatar');
            const avatarInput = document.getElementById(avatarInputId);
            if (avatarInput) avatarInput.value = '';
            const previewImg = document.getElementById('avatarPreviewImg');
            const previewName = document.getElementById('avatarImageName');
            const previewContainer = document.getElementById('avatarPreview');
            const uploadContainer = document.getElementById('sellerAvatarUpload');
            if (previewImg) previewImg.src = defaultAvatar;
            const avatarFileName = defaultAvatar.split('/').pop();
            if (previewName) previewName.textContent = '默认头像 (' + avatarFileName + ')';
            if (previewContainer) previewContainer.classList.add('show');
            if (uploadContainer) uploadContainer.classList.add('has-file');
            showNotification('已选择默认头像', 'success');
        });
    }

    // 客服身份头像预览(螃蟹)
    if (platform === 'pxb7') {
        const csSelect = document.getElementById('XEpxb7_customer_service');
        if (csSelect) {
            csSelect.addEventListener('change', function() {
                const opt = this.querySelector(`option[value="${this.value}"]`);
                const img = document.querySelector('#customerAvatarPreview img');
                if (opt && img) img.src = opt.getAttribute('data-avatar') || '/assets/img/px-kf.png';
            });
        }
    }



    // 图片上传
    setupImageUploads(platform);
}

function setupImageUploads(platform) {
    // 商品图片(螃蟹/盼之)
    if (platform !== 'youxige') {
        const inputId = platform === 'pxb7' ? 'XEpxb7_product_image' : 'XEpzds_product_image';
        const productImageInput = document.getElementById(inputId);
        if (productImageInput) {
            productImageInput.addEventListener('change', function(e) {
                const file = e.target.files[0]; if (!file) return;
                if (!validateFile(file)) { this.value = ''; return; }
                const reader = new FileReader();
                reader.onload = function(ev) {
                    const img = document.getElementById('productImagePreviewImg');
                    const name = document.getElementById('productImageName');
                    const container = document.getElementById('productImagePreview');
                    const upload = document.getElementById('productImageUpload');
                    const currentImg = document.getElementById('currentProductImage');
                    if (img) img.src = ev.target.result;
                    if (name) name.textContent = file.name;
                    if (container) container.classList.add('show');
                    if (upload) upload.classList.add('has-file');
                    if (currentImg) currentImg.style.display = 'none';
                };
                reader.readAsDataURL(file);
            });
        }
    }

    // 卖家头像
    const avatarInputId = platform === 'pxb7' ? 'XEpxb7_seller_avatar' : (platform === 'pzds' ? 'XEpzds_seller_avatar' : 'XEyouxige_seller_avatar');
    const sellerAvatarInput = document.getElementById(avatarInputId);
    if (sellerAvatarInput) {
        sellerAvatarInput.addEventListener('change', function(e) {
            const file = e.target.files[0]; if (!file) return;
            if (!validateFile(file)) { this.value = ''; return; }
            document.getElementById('defaultAvatarUsed').value = '0';
            document.getElementById('sellerAvatarUrl').value = '';
            const reader = new FileReader();
            reader.onload = function(ev) {
                const img = document.getElementById('avatarPreviewImg');
                const name = document.getElementById('avatarImageName');
                const container = document.getElementById('avatarPreview');
                const upload = document.getElementById('sellerAvatarUpload');
                const currentImg = document.getElementById('currentSellerAvatar');
                if (img) img.src = ev.target.result;
                if (name) name.textContent = file.name;
                if (container) container.classList.add('show');
                if (upload) upload.classList.add('has-file');
                if (currentImg) currentImg.style.display = 'none';
            };
            reader.readAsDataURL(file);
        });
    }
}

function removeProductImage() {
    const inputId = currentPlatform === 'pxb7' ? 'XEpxb7_product_image' : 'XEpzds_product_image';
    const input = document.getElementById(inputId);
    const preview = document.getElementById('productImagePreview');
    const upload = document.getElementById('productImageUpload');
    if (input) input.value = '';
    if (preview) preview.classList.remove('show');
    if (upload) upload.classList.remove('has-file');
}

function removeSellerAvatar() {
    const avatarInputId = currentPlatform === 'pxb7' ? 'XEpxb7_seller_avatar' : (currentPlatform === 'pzds' ? 'XEpzds_seller_avatar' : 'XEyouxige_seller_avatar');
    const input = document.getElementById(avatarInputId);
    const preview = document.getElementById('avatarPreview');
    const upload = document.getElementById('sellerAvatarUpload');
    if (input) input.value = '';
    if (preview) preview.classList.remove('show');
    if (upload) upload.classList.remove('has-file');
    document.getElementById('defaultAvatarUsed').value = '0';
    document.getElementById('sellerAvatarUrl').value = '';
}

function closeModal() {
    document.getElementById('productModal').classList.remove('show');
    document.body.style.overflow = 'auto';
}

function openConfirmModal(id, platform) {
    document.getElementById('deleteId').value = id;
    document.getElementById('deletePlatform').value = platform;
    document.getElementById('deleteAction').value = platform === 'youxige' ? 'delete_chatroom' : 'delete_product';
    document.getElementById('confirmModal').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeConfirmModal() {
    document.getElementById('confirmModal').classList.remove('show');
    document.body.style.overflow = 'auto';
}

// ==================== 分享 ====================
function saveShareLinkToDb(platform, itemId, shareUrl) {
    if (platform === 'youxige') return; // 白情不需要保存
    fetch('/group', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=save_share_link&platform=' + encodeURIComponent(platform) + '&chatroom_id=' + encodeURIComponent(itemId) + '&share_link=' + encodeURIComponent(shareUrl)
    }).catch(err => console.error('保存分享链接失败:', err));
}

async function openShareModal(id, platform) {
    currentItemId = id;
    const data = getDataStore(platform);
    if (!data || !data[id]) { showNotification('数据加载失败', 'error'); return; }
    if (!userAntiRedConfig) { userAntiRedConfig = await loadAntiRedConfig(); if (userAntiRedConfig && userAntiRedConfig.apply_status === 'on' && userAntiRedConfig.applied_domain) updateAntiRedStatus(userAntiRedConfig.applied_domain); }
    currentShareUrl = generateShareUrl(platform, data[id]);
    saveShareLinkToDb(platform, id, currentShareUrl);
    document.getElementById('shareUrlText').textContent = currentShareUrl;
    document.getElementById('shareModal').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeShareModal() { document.getElementById('shareModal').classList.remove('show'); document.body.style.overflow = 'auto'; }

async function openShareImageModal(id, platform) {
    if (!id) { showNotification('ID不存在', 'error'); return; }
    currentItemId = id;
    const data = getDataStore(platform);
    if (!data || !data[id]) { showNotification('数据加载失败', 'error'); return; }
    if (!userAntiRedConfig) userAntiRedConfig = await loadAntiRedConfig();
    currentShareUrl = generateShareUrl(platform, data[id]);
    saveShareLinkToDb(platform, id, currentShareUrl);
    fillShareImageData(platform, data[id]);
    generateShareImageQRCode(currentShareUrl);

    // 设置分享图主题
    const container = document.getElementById('shareImageContainer');
    const tag = document.getElementById('shareImagePosterTag');
    container.classList.remove('theme-pzds', 'theme-youxige');
    tag.classList.remove('theme-pzds', 'theme-youxige');
    if (platform === 'pzds') { container.classList.add('theme-pzds'); tag.classList.add('theme-pzds'); }
    else if (platform === 'youxige') { container.classList.add('theme-youxige'); tag.classList.add('theme-youxige'); }

    // 设置头像
    const avatarEl = document.getElementById('shareImagePosterAvatar');
    if (platform === 'pxb7') avatarEl.src = '/assets/img/pxqr.png';
    else if (platform === 'pzds') avatarEl.src = '/assets/img/pzqr.png';
    else avatarEl.src = '/assets/img/youxige.ico';

    document.getElementById('shareImageModal').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function fillShareImageData(platform, itemData) {
    if (platform === 'pxb7') {
        const gameName = itemData.game_name || '';
        const productCode = itemData.product_code || '';
        document.getElementById('shareImagePosterName').textContent = gameName ? `${gameName}${productCode}` : (itemData.product_name || '商品名称');
        document.getElementById('shareImagePosterTag').textContent = itemData.retrieve_compensation === '是' ? '找回包赔' : '商品';
        document.getElementById('shareImagePosterHours').textContent = itemData.no_stock_compensation === '是' ? '无货立赔' : '商品';
        document.getElementById('shareImagePosterHint').textContent = `客服${itemData.customer_service || '螃蟹交易专员'}邀请您加入群聊`;
    } else if (platform === 'pzds') {
        const productName = itemData.product_name || '商品名称';
        const productCode = itemData.product_code || '';
        document.getElementById('shareImagePosterName').textContent = productCode ? `${productName}${productCode}` : productName;
        document.getElementById('shareImagePosterTag').textContent = '盼之担保';
        document.getElementById('shareImagePosterHours').textContent = '安全交易';
        document.getElementById('shareImagePosterHint').textContent = '请扫描下方二维码加入群聊';
    } else {
        document.getElementById('shareImagePosterName').textContent = itemData.trader_name || '交易员名称';
        document.getElementById('shareImagePosterTag').textContent = '白情群聊';
        document.getElementById('shareImagePosterHours').textContent = '安全交易';
        document.getElementById('shareImagePosterHint').textContent = '请扫描下方二维码加入群聊';
    }
}

function generateShareImageQRCode(url) {
    const qrCodeBox = document.getElementById('shareImageQrCodeBox');
    if (!qrCodeBox) return;
    qrCodeBox.innerHTML = '';

    // 根据URL长度选择纠错等级：短URL用M，长URL用L
    const urlLen = (url || '').length;
    let correctLevel, qrSize;
    if (urlLen <= 150) {
        correctLevel = QRCode.CorrectLevel.M;
        qrSize = 118;
    } else if (urlLen <= 300) {
        correctLevel = QRCode.CorrectLevel.L;
        qrSize = 130;
    } else {
        correctLevel = QRCode.CorrectLevel.L;
        qrSize = 140;
    }

    try {
        if (typeof QRCode !== 'undefined') {
            const qrDiv = document.createElement('div');
            qrDiv.style.width = qrSize + 'px'; qrDiv.style.height = qrSize + 'px';
            try {
                new QRCode(qrDiv, { text: url, width: qrSize, height: qrSize, colorDark: "#000000", colorLight: "#ffffff", correctLevel: correctLevel });
            } catch (e) {
                // 长URL生成失败，降级重试：更小尺寸+最低纠错
                qrDiv.innerHTML = '';
                new QRCode(qrDiv, { text: url, width: 118, height: 118, colorDark: "#000000", colorLight: "#ffffff", correctLevel: QRCode.CorrectLevel.L });
            }
            qrCodeBox.appendChild(qrDiv);
        } else { showNotification('二维码生成失败，请刷新页面重试', 'error'); }
    } catch (error) { showNotification('二维码生成失败，链接可能过长', 'error'); }
}

function closeShareImageModal() { document.getElementById('shareImageModal').classList.remove('show'); document.body.style.overflow = 'auto'; }

function saveShareImage() {
    const container = document.getElementById('shareImageContainer');
    if (!container) { showNotification('无法生成图片', 'error'); return; }
    document.getElementById('savingOverlay').style.display = 'flex';
    html2canvas(container, { backgroundColor: null, scale: 2, useCORS: true, logging: false }).then(canvas => {
        const imageUrl = canvas.toDataURL('image/png');
        const downloadLink = document.createElement('a');
        downloadLink.href = imageUrl;
        downloadLink.download = `分享图_${currentItemId}_${new Date().getTime()}.png`;
        document.body.appendChild(downloadLink); downloadLink.click(); document.body.removeChild(downloadLink);
        document.getElementById('savingOverlay').style.display = 'none';
        showNotification('分享图保存成功！');
    }).catch(error => {
        document.getElementById('savingOverlay').style.display = 'none';
        showNotification('生成图片失败，请重试', 'error');
    });
}

function copyProductLink(url) {
    if (!url) { showNotification('链接为空', 'error'); return; }
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(url).then(() => showNotification('链接已复制到剪贴板')).catch(() => fallbackCopy(url));
    } else { fallbackCopy(url); }
}

function fallbackCopy(url) {
    const tempInput = document.createElement('textarea');
    tempInput.style.position = 'fixed'; tempInput.style.left = '-9999px'; tempInput.value = url;
    document.body.appendChild(tempInput); tempInput.select();
    try { document.execCommand('copy') ? showNotification('链接已复制到剪贴板') : showNotification('复制失败，请手动复制', 'error'); } catch(e) { showNotification('复制失败，请手动复制', 'error'); }
    document.body.removeChild(tempInput);
}

// ==================== 表单验证 ====================
function validateForm() {
    const platform = document.getElementById('formPlatform').value;
    if (platform === 'pxb7') {
        const name = document.getElementById('XEpxb7_product_name');
        const code = document.getElementById('XEpxb7_product_code');
        const amount = document.getElementById('XEpxb7_product_amount');
        if (!name || !name.value.trim()) { showNotification('请输入商品名称', 'error'); return false; }
        if (!code || !code.value.trim()) { showNotification('请输入商品编号', 'error'); return false; }
        if (!amount || parseFloat(amount.value) <= 0) { showNotification('商品金额必须大于0', 'error'); return false; }
    } else if (platform === 'pzds') {
        const name = document.getElementById('XEpzds_product_name');
        const code = document.getElementById('XEpzds_product_code');
        const amount = document.getElementById('XEpzds_product_amount');
        if (!name || !name.value.trim()) { showNotification('请输入商品名称', 'error'); return false; }
        if (!code || !code.value.trim()) { showNotification('请输入商品编号', 'error'); return false; }
        if (!amount || parseFloat(amount.value) <= 0) { showNotification('商品金额必须大于0', 'error'); return false; }
    } else {
        const name = document.getElementById('XEyouxige_trader_name');
        const code = document.getElementById('XEyouxige_group_code');
        if (!name || !name.value.trim()) { showNotification('请输入交易员名称', 'error'); return false; }
        if (!code || !code.value.trim()) { showNotification('请输入群聊编号', 'error'); return false; }
    }

    // 头像验证
    const isEdit = document.getElementById('productId').value !== '0';
    const sellerInput = document.querySelector('[name$="_seller_avatar"]');
    const hasFile = sellerInput && sellerInput.files[0];
    const defaultUsed = document.getElementById('defaultAvatarUsed').value === '1';
    const oldAvatar = document.getElementById('old_seller_avatar').value;
    if (!isEdit && !hasFile && !defaultUsed) { showNotification('请上传卖家头像或使用默认头像', 'error'); return false; }
    if (isEdit && !hasFile && !defaultUsed && !oldAvatar) { showNotification('请上传卖家头像或使用默认头像', 'error'); return false; }

    return true;
}

// ==================== 事件绑定 ====================
document.addEventListener('DOMContentLoaded', function() {
    // Tab切换
    document.querySelectorAll('.group-tab-item').forEach(item => {
        item.addEventListener('click', function() { switchTab(this.getAttribute('data-tab')); });
    });

    // 卡片按钮事件委托
    document.addEventListener('click', function(e) {
        const editBtn = e.target.closest('.btn-edit');
        const deleteBtn = e.target.closest('.btn-delete');
        const shareBtn = e.target.closest('.btn-share');
        const shareImageBtn = e.target.closest('.btn-share-image');

        if (editBtn) {
            const id = editBtn.getAttribute('data-id');
            const platform = editBtn.getAttribute('data-platform') || currentPlatform;
            currentPlatform = platform; switchTab(platform);
            openModal('edit', id);
        }
        if (deleteBtn) {
            const id = deleteBtn.getAttribute('data-id');
            const platform = deleteBtn.getAttribute('data-platform') || currentPlatform;
            openConfirmModal(id, platform);
        }
        if (shareBtn) {
            const id = shareBtn.getAttribute('data-id');
            const platform = shareBtn.getAttribute('data-platform') || currentPlatform;
            openShareModal(id, platform);
        }
        if (shareImageBtn) {
            const id = shareImageBtn.getAttribute('data-id');
            const platform = shareImageBtn.getAttribute('data-platform') || currentPlatform;
            openShareImageModal(id, platform);
        }
    });

    // 确认删除
    document.getElementById('confirmDelete').addEventListener('click', function() {
        document.getElementById('deleteForm').submit();
    });

    // 分享复制
    document.getElementById('shareCopyBtn').addEventListener('click', function() {
        if (currentShareUrl) { copyProductLink(currentShareUrl); closeShareModal(); }
        else showNotification('链接尚未生成', 'error');
    });

    // 分享图按钮
    document.getElementById('returnFromImageBtn').addEventListener('click', closeShareImageModal);
    document.getElementById('saveShareImageBtn').addEventListener('click', saveShareImage);

    // 表单验证
    document.getElementById('productForm').addEventListener('submit', function(e) {
        if (!validateForm()) e.preventDefault();
    });

    // 模态框外部点击关闭
    document.querySelectorAll('.modal-overlay, .confirm-overlay').forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                if (this.id === 'productModal') closeModal();
                else if (this.id === 'confirmModal') closeConfirmModal();
                else if (this.id === 'shareModal') closeShareModal();
                else if (this.id === 'shareImageModal') closeShareImageModal();
            }
        });
    });

    // ESC关闭
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') { closeModal(); closeConfirmModal(); closeShareModal(); closeShareImageModal(); }
    });

    // 加载防红配置
    loadAntiRedConfig().then(config => {
        userAntiRedConfig = config;
        if (userAntiRedConfig && userAntiRedConfig.apply_status === 'on' && userAntiRedConfig.applied_domain) updateAntiRedStatus(userAntiRedConfig.applied_domain);
    }).catch(() => {});

    // 显示操作消息
    <?php if (isset($success_message) && !empty($success_message)): ?>
    setTimeout(() => { showNotification(<?php echo json_encode($success_message, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT); ?>, 'success'); }, 300);
    <?php endif; ?>
    <?php if (isset($error_message) && !empty($error_message)): ?>
    setTimeout(() => { showNotification(<?php echo json_encode($error_message, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT); ?>, 'error'); }, 300);
    <?php endif; ?>
});
</script>
</body>
</html>
