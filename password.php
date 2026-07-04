<?php
if (!defined('FROM_ROUTER')) {
    http_response_code(403);
    exit('喜乐科技@x60898');
}
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/dbconfig.php';

checkLogin();

$isTokenLogin = isset($_SESSION['login_type']) && $_SESSION['login_type'] === 'token';

$error = '';
$success = '';
$current_user_id = $_SESSION['user_id'] ?? null;
$current_username = $_SESSION['username'] ?? '';

$user_info = [];
$has_second_password = false;
if ($current_user_id) {
    $db = getDB();
    if ($db) {
        $stmt = $db->prepare("SELECT username, created_at, second_password FROM users WHERE id = ?");
        $stmt->bind_param("i", $current_user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 1) {
            $user_info = $result->fetch_assoc();
            $has_second_password = !empty($user_info['second_password']);
        }
        $stmt->close();
    }
}

$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Token 登录模式下禁止修改密码
    if ($isTokenLogin) {
        $error = 'Token 登录模式下无法修改密码，请使用账号密码方式登录';
    } elseif ($action === 'change_password') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $second_password = $_POST['second_password'] ?? '';
        
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error = '请填写所有必填字段';
        } elseif ($new_password !== $confirm_password) {
            $error = '新密码和确认密码不一致';
        } elseif (strlen($new_password) < 6) {
            $error = '新密码长度至少6位';
        } elseif ($current_password === $new_password) {
            $error = '新密码不能与当前密码相同';
        } else {
            $db = getDB();
            if ($db) {
                $stmt = $db->prepare("SELECT password, username, second_password FROM users WHERE id = ?");
                $stmt->bind_param("i", $current_user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows === 1) {
                    $user = $result->fetch_assoc();
                    
                    $current_password_md5 = md5($current_password);
                    if ($current_password_md5 === $user['password']) {
                        if (!empty($user['second_password'])) {
                            if (empty($second_password)) {
                                $error = '请输入二级密码进行验证';
                            } elseif (md5($second_password) !== $user['second_password']) {
                                $error = '二级密码错误';
                            }
                        }
                        
                        if (empty($error)) {
                            $new_password_md5 = md5($new_password);
                            $update_stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
                            $update_stmt->bind_param("si", $new_password_md5, $current_user_id);
                            
                            if ($update_stmt->execute()) {
                                logPasswordChange($current_user_id, $user['username']);
                                invalidateAllUserSessions($current_user_id);

                                $_SESSION = array();
                                session_destroy();
                                setcookie('user_info', '', time() - 3600, '/');
                                setcookie('session_token', '', time() - 3600, '/');
                                setcookie(session_name(), '', time() - 3600, '/');

                                header('Location: /login.php');
                                exit;
                            } else {
                                $error = '密码修改失败，请稍后重试';
                            }
                            $update_stmt->close();
                        }
                    } else {
                        $error = '当前密码错误';
                    }
                } else {
                    $error = '用户不存在';
                }
                $stmt->close();
            } else {
                $error = '数据库连接失败';
            }
        }
    } elseif ($action === 'change_second_password') {
        $current_password = $_POST['current_password'] ?? '';
        $new_second_password = $_POST['new_second_password'] ?? '';
        $confirm_second_password = $_POST['confirm_second_password'] ?? '';
        
        if (empty($current_password) || empty($new_second_password) || empty($confirm_second_password)) {
            $error = '请填写所有必填字段';
        } elseif ($new_second_password !== $confirm_second_password) {
            $error = '新二级密码和确认密码不一致';
        } elseif (strlen($new_second_password) < 6) {
            $error = '二级密码长度至少6位';
        } else {
            $db = getDB();
            if ($db) {
                $stmt = $db->prepare("SELECT password, username FROM users WHERE id = ?");
                $stmt->bind_param("i", $current_user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows === 1) {
                    $user = $result->fetch_assoc();
                    
                    $current_password_md5 = md5($current_password);
                    if ($current_password_md5 === $user['password']) {
                        $new_password_md5 = md5($new_second_password);
                        $update_stmt = $db->prepare("UPDATE users SET second_password = ? WHERE id = ?");
                        $update_stmt->bind_param("si", $new_password_md5, $current_user_id);
                        
                        if ($update_stmt->execute()) {
                            logSecondPasswordChange($current_user_id, $user['username']);
                            $success = '二级密码修改成功';
                            $has_second_password = true;
                        } else {
                            $error = '二级密码修改失败，请稍后重试';
                        }
                        $update_stmt->close();
                    } else {
                        $error = '当前登录密码错误';
                    }
                } else {
                    $error = '用户不存在';
                }
                $stmt->close();
            } else {
                $error = '数据库连接失败';
            }
        }
    }
}

function logPasswordChange($user_id, $username) {
    $db = getDB();
    if ($db) {
        $action = '修改密码';
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $details = '密码已成功修改，所有设备已注销';
        
        $stmt = $db->prepare("INSERT INTO user_logs (user_id, username, action, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssss", $user_id, $username, $action, $details, $ip_address, $user_agent);
        if ($stmt->execute()) {
            return true;
        } else {
            error_log("记录密码修改日志失败: " . $stmt->error);
            return false;
        }
        $stmt->close();
    }
    return false;
}

function logSecondPasswordChange($user_id, $username) {
    $db = getDB();
    if ($db) {
        $action = '修改二级密码';
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $details = '二级密码已成功修改';
        
        $stmt = $db->prepare("INSERT INTO user_logs (user_id, username, action, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssss", $user_id, $username, $action, $details, $ip_address, $user_agent);
        if ($stmt->execute()) {
            return true;
        } else {
            error_log("记录二级密码修改日志失败: " . $stmt->error);
            return false;
        }
        $stmt->close();
    }
    return false;
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no,viewport-fit=cover">
	<meta name="theme-color" content="#f5f5f5">
	<meta name="mobile-web-app-capable" content="yes">
	<meta name="apple-mobile-web-app-capable" content="yes">
	<meta name="apple-mobile-web-app-title" content="XEKEFU">
	<meta name="apple-mobile-web-app-status-bar-style" content="default">
	<link rel="manifest" href="/manifest.php">
	<link rel="apple-touch-icon" href="/xe-icon.png">
	<meta name="description" content="在线客户服务平台">
	<meta name="keywords" content="客服,咨询,服务">
	<meta name="robots" content="noindex, nofollow">
	<title>喜乐-客服系统</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }

        body {
            background-color: #f5f5f5;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            min-height: 100vh;
        }

        .container {
            width: 100%;
            max-width: 500px;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .XE-loginer {
            width: 100%;
        }

        .top-header {            
            border-bottom: 1px solid #dbdbdb;
            padding: 0px 25px;
            padding-left: 8px;
            align-items: center;
            display: flex;
            height: 49px;
            position: relative;
            background: #fff;
        }

        .header {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }

        .title {
            font-size: 18px;
            font-weight: bold;
            color: #333;
            text-align: center;
            flex-grow: 1;
        }

        .section-card {
            background: #fff;
            border-radius: 12px;
            padding: 20px;
            margin: 0 15px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .section-title {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }

        .section-desc {
            font-size: 13px;
            color: #999;
            margin-bottom: 20px;
        }

        .status-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 12px;
            margin-left: 8px;
        }

        .status-enabled {
            background: rgba(52, 199, 89, 0.1);
            color: #34c759;
        }

        .status-disabled {
            background: rgba(255, 149, 0, 0.1);
            color: #ff9500;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-label {
            display: block;
            font-size: 14px;
            color: #666;
            margin-bottom: 6px;
        }

        .form-input {
            width: 100%;
            height: 46px;
            padding: 0 15px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            font-size: 15px;
            outline: none;
            transition: border-color 0.3s;
            background: #fafafa;
        }

        .form-input::placeholder {
            color: #999;
        }

        .form-input:focus {
            border-color: #007aff;
            background: #fff;
        }

        .form-input.error {
            border-color: #ff3b30;
        }

        .error-message {
            color: #ff3b30;
            font-size: 12px;
            margin-top: 4px;
            display: none;
        }

        .error-message.show {
            display: block;
        }

        .submit-btn {
            width: 100%;
            height: 46px;
            background-color: #c7c7cc;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 500;
            cursor: pointer;
            display: flex;
            justify-content: center;
            align-items: center;
            pointer-events: none;
            opacity: 0.6;
            transition: background-color 0.3s, opacity 0.3s;
            margin-top: 10px;
        }

        .submit-btn.active {
            background-color: #007aff;
            pointer-events: auto;
            opacity: 1;
        }

        .server-message {
            margin: 15px 15px;
            padding: 12px 15px;
            border-radius: 8px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .server-error {
            background-color: rgba(255, 59, 48, 0.1);
            color: #ff3b30;
            border: 1px solid rgba(255, 59, 48, 0.2);
        }

        .server-success {
            background-color: rgba(52, 199, 89, 0.1);
            color: #34c759;
            border: 1px solid rgba(52, 199, 89, 0.2);
        }

        .divider {
            height: 1px;
            background: #eee;
            margin: 10px 0;
        }

        .input-wrapper {
            position: relative;
        }

        .password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #999;
            cursor: pointer;
            padding: 4px;
            font-size: 1.1rem;
            z-index: 1;
        }

        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            display: none;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .modal-overlay.show {
            display: flex;
            opacity: 1;
        }

        .modal-overlay.hiding {
            opacity: 0;
        }

        .drawer-modal {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: #fff;
            border-radius: 20px 20px 0 0;
            z-index: 1001;
            transform: translateY(100%);
            transition: transform 0.3s cubic-bezier(0.25, 1, 0.5, 1);
            max-width: 500px;
            margin: 0 auto;
        }

        .drawer-modal.show {
            transform: translateY(0);
        }

        .drawer-modal.hiding {
            transform: translateY(100%);
        }

        .drawer-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            padding-bottom: 10px;
        }

        .drawer-title {
            font-size: 17px;
            font-weight: 600;
            color: #333;
        }

        .drawer-close {
            background: none;
            border: none;
            font-size: 24px;
            color: #999;
            cursor: pointer;
            padding: 0;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .drawer-close:hover {
            color: #666;
        }

        .drawer-body {
            padding: 0 20px 30px 20px;
        }

        .drawer-desc {
            font-size: 14px;
            color: #666;
            margin-bottom: 20px;
            line-height: 1.5;
        }

        .drawer-input-wrapper {
            margin-bottom: 20px;
        }

        .drawer-label {
            display: block;
            font-size: 14px;
            color: #333;
            margin-bottom: 8px;
            font-weight: 500;
        }

        .drawer-input {
            width: 100%;
            height: 46px;
            padding: 0 15px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            font-size: 15px;
            outline: none;
            transition: border-color 0.3s;
            background: #fafafa;
        }

        .drawer-input:focus {
            border-color: #007aff;
            background: #fff;
        }

        .drawer-input.error {
            border-color: #ff3b30;
        }

        .drawer-error {
            color: #ff3b30;
            font-size: 13px;
            margin-top: 6px;
            display: none;
        }

        .drawer-error.show {
            display: block;
        }

        .drawer-buttons {
            display: flex;
            gap: 12px;
        }

        .drawer-btn {
            flex: 1;
            height: 46px;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
        }

        .drawer-btn-cancel {
            background: #f2f2f7;
            border: none;
            color: #333;
        }

        .drawer-btn-cancel:hover {
            background: #e5e5ea;
        }

        .drawer-btn-confirm {
            background: #007aff;
            border: none;
            color: white;
        }

        .drawer-btn-confirm:hover {
            background: #0051d5;
        }

        .drawer-btn-confirm:disabled {
            background: #c7c7cc;
            cursor: not-allowed;
        }

        @media (max-width: 480px) {
            .section-card {
                margin: 0 10px;
                padding: 16px;
            }

            .drawer-modal {
                border-radius: 16px 16px 0 0;
            }
        }
    </style>
    <link href="/assets/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <div class="XE-loginer">
        <div class="container">
            <div class="top-header">
                <a href="javascript:void(0)" onclick="window.parent.postMessage('closeModal', '*')" style="display: inline-flex; align-items: center; text-decoration: none; color: inherit;">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 h-6 w-6">
                        <path d="M14 6l-6 6 6 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path>
                    </svg>
                    <div style="border: 14px solid transparent;">返回</div>
                </a>
            </div>

            <?php if ($error): ?>
                <div class="server-message server-error">
                    <i class="bi bi-exclamation-circle"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="server-message server-success">
                    <i class="bi bi-check-circle"></i>
                    <span><?php echo htmlspecialchars($success); ?></span>
                </div>
            <?php endif; ?>

            <?php if ($isTokenLogin): ?>
            <div class="section-card">
                <div class="header">
                    <div class="section-title">Token 登录模式</div>
                </div>
                <div style="padding: 24px; text-align: center; color: #6b7280;">
                    <i class="bi bi-shield-lock" style="font-size: 48px; color: #9ca3af; display: block; margin-bottom: 16px;"></i>
                    <p style="font-size: 16px; font-weight: 600; color: #374151; margin-bottom: 8px;">当前为 Token 登录模式</p>
                    <p style="font-size: 14px; line-height: 1.6;">Token 登录模式下无法修改密码。<br>如需修改密码，请使用账号密码方式登录。</p>
                </div>
            </div>
            <?php else: ?>

            <div class="section-card">
                <div class="header">
                    <div class="section-title">修改登录密码</div>
                </div>
                <div class="section-desc">修改您的登录密码，修改后所有设备都需要重新登录</div>
                <form method="POST" action="" id="changePasswordForm">
                    <input type="hidden" name="action" value="change_password">
                    <input type="hidden" name="second_password" id="secondPasswordHidden" value="">
                    
                    <div class="form-group">
                        <label class="form-label">当前密码</label>
                        <div class="input-wrapper">
                            <input type="password" 
                                   class="form-input" 
                                   name="current_password" 
                                   id="currentPassword"
                                   placeholder="请输入当前登录密码"
                                   required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">新密码</label>
                        <div class="input-wrapper">
                            <input type="password" 
                                   class="form-input" 
                                   name="new_password" 
                                   id="newPassword"
                                   placeholder="请输入新密码 (至少6位)"
                                   minlength="6"
                                   required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">确认新密码</label>
                        <div class="input-wrapper">
                            <input type="password" 
                                   class="form-input" 
                                   name="confirm_password" 
                                   id="confirmPassword"
                                   placeholder="再次输入新密码"
                                   minlength="6"
                                   required>
                        </div>
                    </div>

                    <button type="submit" class="submit-btn" id="saveBtn1">保存并重新登录</button>
                </form>
            </div>

            <div class="section-card">
                <div class="header">
                    <div class="section-title">
                        二级密码
                        <span class="status-badge <?php echo $has_second_password ? 'status-enabled' : 'status-disabled'; ?>">
                            <?php echo $has_second_password ? '已启用' : '未启用'; ?>
                        </span>
                    </div>
                </div>
                <div class="section-desc">二级密码用于IP变更时的身份验证，启用后异地登录需要输入二级密码</div>
                <form method="POST" action="" id="changeSecondPasswordForm">
                    <input type="hidden" name="action" value="change_second_password">
                    
                    <div class="form-group">
                        <label class="form-label">当前登录密码</label>
                        <div class="input-wrapper">
                            <input type="password" 
                                   class="form-input" 
                                   name="current_password" 
                                   placeholder="请输入当前登录密码进行验证"
                                   required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">新二级密码</label>
                        <div class="input-wrapper">
                            <input type="password" 
                                   class="form-input" 
                                   name="new_second_password" 
                                   placeholder="请输入新二级密码 (至少6位)"
                                   minlength="6"
                                   required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">确认二级密码</label>
                        <div class="input-wrapper">
                            <input type="password" 
                                   class="form-input" 
                                   name="confirm_second_password" 
                                   placeholder="再次输入二级密码"
                                   minlength="6"
                                   required>
                        </div>
                    </div>

                    <button type="submit" class="submit-btn" id="saveBtn2">
                        <?php echo $has_second_password ? '修改二级密码' : '设置二级密码'; ?>
                    </button>
                </form>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="modal-overlay" id="modalOverlay"></div>
    
    <div class="drawer-modal" id="drawerModal">
        <div class="drawer-header">
            <div class="drawer-title">验证二级密码</div>
            <button type="button" class="drawer-close" id="drawerClose">
                <i class="bi bi-x"></i>
            </button>
        </div>
        <div class="drawer-body">
            <div class="drawer-desc">
                检测到您已设置二级密码，修改登录密码前需要验证二级密码。
                <br><small style="color: #ff9500;">注意：修改密码后，该账号在所有设备都将被注销登录。</small>
            </div>
            
            <div class="drawer-input-wrapper">
                <label class="drawer-label">二级密码</label>
                <div class="input-wrapper">
                    <input type="password" 
                           class="drawer-input" 
                           id="drawerSecondPassword"
                           placeholder="请输入二级密码">
                    <button type="button" class="password-toggle" id="drawerPasswordToggle">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
                <div class="drawer-error" id="drawerError"></div>
            </div>
            
            <div class="drawer-buttons">
                <button type="button" class="drawer-btn drawer-btn-cancel" id="drawerCancel">取消</button>
                <button type="button" class="drawer-btn drawer-btn-confirm" id="drawerConfirm" disabled>确认</button>
            </div>
        </div>
    </div>

    <script>
        const hasSecondPassword = <?php echo $has_second_password ? 'true' : 'false'; ?>;

        document.addEventListener('DOMContentLoaded', function() {
            const changePasswordForm = document.getElementById('changePasswordForm');
            const changeSecondPasswordForm = document.getElementById('changeSecondPasswordForm');
            const saveBtn1 = document.getElementById('saveBtn1');
            const saveBtn2 = document.getElementById('saveBtn2');
            const secondPasswordHidden = document.getElementById('secondPasswordHidden');
            
            const modalOverlay = document.getElementById('modalOverlay');
            const drawerModal = document.getElementById('drawerModal');
            const drawerClose = document.getElementById('drawerClose');
            const drawerCancel = document.getElementById('drawerCancel');
            const drawerConfirm = document.getElementById('drawerConfirm');
            const drawerSecondPassword = document.getElementById('drawerSecondPassword');
            const drawerError = document.getElementById('drawerError');
            const drawerPasswordToggle = document.getElementById('drawerPasswordToggle');

            function showDrawer() {
                modalOverlay.classList.add('show');
                drawerModal.classList.add('show');
                drawerSecondPassword.value = '';
                drawerError.classList.remove('show');
                drawerError.textContent = '';
                drawerConfirm.disabled = true;
                setTimeout(() => {
                    drawerSecondPassword.focus();
                }, 300);
            }

            function hideDrawer() {
                drawerModal.classList.add('hiding');
                modalOverlay.classList.add('hiding');
                
                setTimeout(() => {
                    drawerModal.classList.remove('show', 'hiding');
                    modalOverlay.classList.remove('show', 'hiding');
                }, 300);
            }

            drawerClose.addEventListener('click', hideDrawer);
            drawerCancel.addEventListener('click', hideDrawer);
            modalOverlay.addEventListener('click', function(e) {
                if (e.target === modalOverlay) {
                    hideDrawer();
                }
            });

            if (drawerPasswordToggle) {
                drawerPasswordToggle.addEventListener('click', function() {
                    const type = drawerSecondPassword.getAttribute('type') === 'password' ? 'text' : 'password';
                    drawerSecondPassword.setAttribute('type', type);
                    
                    const icon = this.querySelector('i');
                    if (type === 'text') {
                        icon.className = 'bi bi-eye-slash';
                    } else {
                        icon.className = 'bi bi-eye';
                    }
                });
            }

            drawerSecondPassword.addEventListener('input', function() {
                const value = this.value.trim();
                drawerConfirm.disabled = value.length < 6;
                drawerError.classList.remove('show');
                this.classList.remove('error');
            });

            drawerSecondPassword.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' && !drawerConfirm.disabled) {
                    drawerConfirm.click();
                }
            });

            drawerConfirm.addEventListener('click', async function() {
                const secondPassword = drawerSecondPassword.value.trim();
                if (secondPassword.length < 6) {
                    drawerError.textContent = '二级密码至少6位';
                    drawerError.classList.add('show');
                    drawerSecondPassword.classList.add('error');
                    return;
                }

                try {
                    const formData = new FormData();
                    formData.append('second_password', secondPassword);
                    
                    const response = await fetch('/api/sign/check?action=verify_second_password_for_password_change', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const data = await response.json();
                    
                    if (data.code === 1) {
                        secondPasswordHidden.value = secondPassword;
                        hideDrawer();
                        setTimeout(() => {
                            changePasswordForm.submit();
                        }, 300);
                    } else {
                        drawerError.textContent = data.message || '二级密码错误';
                        drawerError.classList.add('show');
                        drawerSecondPassword.classList.add('error');
                    }
                } catch (error) {
                    console.error('验证错误:', error);
                    drawerError.textContent = '验证失败，请重试';
                    drawerError.classList.add('show');
                }
            });

            function setupFormValidation(form, btn) {
                const inputs = form.querySelectorAll('.form-input');
                
                function checkInputs() {
                    let allFilled = true;
                    inputs.forEach(input => {
                        if (input.value.trim() === '') {
                            allFilled = false;
                        }
                    });
                    
                    if (allFilled) {
                        btn.classList.add('active');
                    } else {
                        btn.classList.remove('active');
                    }
                }

                inputs.forEach(input => {
                    input.addEventListener('input', () => {
                        checkInputs();
                        if (input.value) {
                            input.style.borderColor = '#34c759';
                        } else {
                            input.style.borderColor = '#e0e0e0';
                        }
                    });
                });

                form.addEventListener('submit', function(e) {
                    if (form === changePasswordForm && hasSecondPassword) {
                        e.preventDefault();
                        e.stopPropagation();
                        showDrawer();
                        return false;
                    }
                    
                    btn.disabled = true;
                    btn.textContent = '处理中...';
                });

                checkInputs();
            }

            setupFormValidation(changePasswordForm, saveBtn1);
            setupFormValidation(changeSecondPasswordForm, saveBtn2);
        });
    </script>
</body>
</html>
