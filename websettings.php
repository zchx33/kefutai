<?php
if (!defined('FROM_ROUTER')) {
    http_response_code(403);
    exit('喜乐科技@x60898');
}
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/dbconfig.php';
checkLogin();
checkAdmin();

$db = getDB();
if (!$db) {
    die("数据库连接失败");
}

function initWebConfigTable($db) {
    $sql = "CREATE TABLE IF NOT EXISTS webconfig (
        id INT AUTO_INCREMENT PRIMARY KEY,
        site_name VARCHAR(255) NOT NULL DEFAULT '',
        telegram_username VARCHAR(255) NOT NULL DEFAULT '',
        storage_type VARCHAR(50) NOT NULL DEFAULT 'local',
        popup_enabled TINYINT(1) NOT NULL DEFAULT 0,
        popup_title VARCHAR(255) NOT NULL DEFAULT '',
        popup_content TEXT NOT NULL,
        pwa_name VARCHAR(255) NOT NULL DEFAULT 'XE控制台',
        pwa_short_name VARCHAR(100) NOT NULL DEFAULT 'XE控制台',
        pwa_icon VARCHAR(255) NOT NULL DEFAULT '/xe-icon.png',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    
    if ($db->query($sql) === TRUE) {
        $result = $db->query("SELECT COUNT(*) as count FROM webconfig");
        $row = $result->fetch_assoc();
        
        if ($row['count'] == 0) {
            $insertSql = "INSERT INTO webconfig (site_name, telegram_username, storage_type, popup_enabled, popup_title, popup_content, pwa_name, pwa_short_name, pwa_icon) 
                         VALUES ('欧钛网络', '', 'local', 0, '网站公告', '欢迎访问我们的网站！', 'XE控制台', 'XE控制台', '/xe-icon.png')";
            $db->query($insertSql);
        } else {
            addColumnIfNotExists($db, 'webconfig', 'pwa_name', "VARCHAR(255) NOT NULL DEFAULT 'XE控制台'");
            addColumnIfNotExists($db, 'webconfig', 'pwa_short_name', "VARCHAR(100) NOT NULL DEFAULT 'XE控制台'");
            addColumnIfNotExists($db, 'webconfig', 'pwa_icon', "VARCHAR(255) NOT NULL DEFAULT '/xe-icon.png'");
            addColumnIfNotExists($db, 'webconfig', 'site_url', "VARCHAR(500) NOT NULL DEFAULT ''");
            addColumnIfNotExists($db, 'webconfig', 'site_url_enabled', "TINYINT(1) NOT NULL DEFAULT 0");
        }
    }
}

function addColumnIfNotExists($db, $table, $column, $definition) {
    $result = $db->query("DESCRIBE $table");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            if ($row['Field'] === $column) {
                return;
            }
        }
    }
    $db->query("ALTER TABLE $table ADD COLUMN $column $definition");
}

initWebConfigTable($db);

function getWebConfig($db) {
    $result = $db->query("SELECT * FROM webconfig ORDER BY id DESC LIMIT 1");
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    return [
        'site_name' => '欧钛网络',
        'site_url' => '',
        'site_url_enabled' => 0,
        'telegram_username' => '',
        'storage_type' => 'local',
        'popup_enabled' => 0,
        'popup_title' => '网站公告',
        'popup_content' => '欢迎访问我们的网站！',
        'pwa_name' => 'XE控制台',
        'pwa_short_name' => 'XE控制台',
        'pwa_icon' => '/xe-icon.png'
    ];
}

$config = getWebConfig($db);

$successMessage = "";
$errorMessage = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_site_settings'])) {
        $site_name = trim($_POST['OTIXxname']);
        $site_url = trim($_POST['siteUrl']);
        $site_url_enabled = isset($_POST['siteUrlEnabled']) ? 1 : 0;
        
        $stmt = $db->prepare("UPDATE webconfig SET site_name = ?, site_url = ?, site_url_enabled = ? WHERE id = ?");
        $stmt->bind_param("ssii", $site_name, $site_url, $site_url_enabled, $config['id']);
        
        if ($stmt->execute()) {
            $successMessage = "网站设置已保存！";
            $config = getWebConfig($db);
        } else {
            $errorMessage = "保存失败: " . $stmt->error;
        }
    } elseif (isset($_POST['save_telegram_settings'])) {
        $telegram_username = trim($_POST['telegramUsername']);
        
        $stmt = $db->prepare("UPDATE webconfig SET telegram_username = ? WHERE id = ?");
        $stmt->bind_param("si", $telegram_username, $config['id']);
        
        if ($stmt->execute()) {
            $successMessage = "Telegram设置已保存！";
            $config = getWebConfig($db);
        } else {
            $errorMessage = "保存失败: " . $stmt->error;
        }
    } elseif (isset($_POST['save_storage_settings'])) {
        $storage_type = 'local';
        
        $stmt = $db->prepare("UPDATE webconfig SET storage_type = ? WHERE id = ?");
        $stmt->bind_param("si", $storage_type, $config['id']);
        
        if ($stmt->execute()) {
            $successMessage = "存储设置已保存！";
            $config = getWebConfig($db);
        } else {
            $errorMessage = "保存失败: " . $stmt->error;
        }
    } elseif (isset($_POST['save_popup_settings'])) {
        $popup_enabled = isset($_POST['popupEnabled']) ? 1 : 0;
        $popup_title = trim($_POST['popupTitle']);
        $popup_content = trim($_POST['popupContent']);
        
        $stmt = $db->prepare("UPDATE webconfig SET popup_enabled = ?, popup_title = ?, popup_content = ? WHERE id = ?");
        $stmt->bind_param("issi", $popup_enabled, $popup_title, $popup_content, $config['id']);
        
        if ($stmt->execute()) {
            $successMessage = "弹窗设置已保存！";
            $config = getWebConfig($db);
        } else {
            $errorMessage = "保存失败: " . $stmt->error;
        }
    } elseif (isset($_POST['save_pwa_settings'])) {
        $pwa_name = trim($_POST['pwaName']);
        $pwa_short_name = trim($_POST['pwaShortName']);
        $pwa_icon = '/xe-icon.png';
        
        if (isset($_FILES['pwaIcon']) && $_FILES['pwaIcon']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/';
            $fileName = 'xe-icon.png';
            $targetPath = $uploadDir . $fileName;
            
            if (move_uploaded_file($_FILES['pwaIcon']['tmp_name'], $targetPath)) {
                $pwa_icon = '/xe-icon.png';
            }
        }
        
        $stmt = $db->prepare("UPDATE webconfig SET pwa_name = ?, pwa_short_name = ?, pwa_icon = ? WHERE id = ?");
        $stmt->bind_param("sssi", $pwa_name, $pwa_short_name, $pwa_icon, $config['id']);
        
        if ($stmt->execute()) {
            $successMessage = "PWA设置已保存！";
            $config = getWebConfig($db);
        } else {
            $errorMessage = "保存失败: " . $stmt->error;
        }
    } elseif (isset($_POST['reset_all_settings'])) {
        $stmt = $db->prepare("UPDATE webconfig SET site_name = '欧钛网络', site_url = '', site_url_enabled = 0, telegram_username = '', storage_type = 'local', popup_enabled = 0, popup_title = '网站公告', popup_content = '欢迎访问我们的网站！', pwa_name = 'XE控制台', pwa_short_name = 'XE控制台', pwa_icon = '/xe-icon.png' WHERE id = ?");
        $stmt->bind_param("i", $config['id']);
        
        if ($stmt->execute()) {
            $config = getWebConfig($db);
            $successMessage = "配置已恢复默认值！";
        } else {
            $errorMessage = "恢复默认失败: " . $stmt->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>网站信息配置</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Noto+Sans+SC:wght@400;500;700&display=swap">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        :root {
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-400: #9ca3af;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --gray-900: #111827;
            --zinc-800: #27272a;
            --blue-500: #3b82f6;
            --blue-600: #2563eb;
            --black: #18181b;
            --white: #ffffff;
        }
        
        body {
            font-family: 'Noto Sans SC', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, 'Noto Sans', sans-serif;
            background-color: var(--gray-50);
            min-height: 100vh;
            line-height: 1.5;
        }
        
        .container {
            max-width: 42rem;
            margin: 0 auto;
            min-height: 100vh;
            background-color: var(--gray-50);
        }
        
        .header {
            position: sticky;
            top: 0;
            z-index: 10;
            background-color: var(--white);
            padding: 1rem;
            border-bottom: 1px solid var(--gray-200);
        }
        
        .header-content {
            display: flex;
            align-items: center;
        }
        
        .back-link {
            display: flex;
            align-items: center;
            color: var(--blue-500);
            text-decoration: none;
            font-weight: 500;
            font-size: 1rem;
        }
        
        .back-link:hover {
            color: var(--blue-600);
        }
        
        .back-icon {
            width: 1.25em;
            height: 1.25em;
        }
        
        .back-text {
            margin-left: 0.25rem;
        }
        
        .title {
            margin-left: 1rem;
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--gray-800);
        }
        
        .main-content {
            padding: 1rem;
        }
        
        .card {
            background-color: var(--white);
            border-radius: 0.5rem;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px -1px rgba(0, 0, 0, 0.1);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .card-title {
            font-size: 1rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 1.5rem;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--gray-600);
            margin-bottom: 0.5rem;
        }
        
        .input {
            width: 100%;
            border: 1px solid var(--gray-300);
            border-radius: 0.375rem;
            padding: 0.5rem 0.75rem;
            color: var(--gray-900);
            font-size: 1rem;
        }
        
        .input:focus {
            outline: none;
            border-color: transparent;
            box-shadow: 0 0 0 2px var(--blue-500);
        }
        
        .input::placeholder {
            color: var(--gray-400);
        }
        
        .textarea {
            width: 100%;
            border: 1px solid var(--gray-300);
            border-radius: 0.375rem;
            padding: 0.5rem 0.75rem;
            color: var(--gray-900);
            font-size: 1rem;
            min-height: 6rem;
            resize: vertical;
            font-family: 'Noto Sans SC', sans-serif;
        }
        
        .textarea:focus {
            outline: none;
            border-color: transparent;
            box-shadow: 0 0 0 2px var(--blue-500);
        }
        
        .form-hint {
            margin-top: 0.5rem;
            font-size: 0.75rem;
            color: var(--gray-500);
        }
        
        .btn {
            background-color: var(--black);
            color: var(--white);
            font-weight: 500;
            padding: 0.5rem 1.25rem;
            border-radius: 0.375rem;
            border: none;
            cursor: pointer;
            font-size: 0.875rem;
            transition: background-color 0.15s, color 0.15s, border-color 0.15s, fill 0.15s, stroke 0.15s;
        }
        
        .btn:hover {
            background-color: var(--zinc-800);
        }
        
        .btn-primary {
            background-color: var(--black);
            color: var(--white);
        }
        
        .btn-primary:hover {
            background-color: var(--zinc-800);
        }
        
        .checkbox-switch {
            display: flex;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .checkbox-switch input[type="checkbox"] {
            margin-right: 0.75rem;
            width: 1.25rem;
            height: 1.25rem;
        }
        
        .checkbox-switch label {
            font-size: 0.875rem;
            color: var(--gray-700);
            cursor: pointer;
        }
        
        .mt-2 {
            margin-top: 0.5rem;
        }
        
        .mb-4 {
            margin-bottom: 1rem;
        }
        
        .mb-6 {
            margin-bottom: 1.5rem;
        }
        
        .hidden-input {
            display: none;
        }
        
        /* iOS风格开关样式 */
        .switch-container {
            display: flex;
            align-items: center;
        }
        
        .toggle-switch {
            margin-left: 0;
            position: relative;
            width: 51px;
            height: 31px;
        }
        
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .switch-slider {
            width: 100%;
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #e9e9eb;
            transition: .4s;
            border-radius: 34px;
        }
        
        .switch-slider:before {
            position: absolute;
            content: "";
            height: 27px;
            width: 27px;
            left: 2px;
            bottom: 2px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        }
        
        input:checked + .switch-slider {
            background-color: #4cd964;
        }
        
        input:checked + .switch-slider:before {
            transform: translateX(20px);
        }
    </style>
</head>
<body>
    <div class="container">
        <header class="header">
            <div class="header-content">
                <a href="javascript:void(0)" onclick="window.parent.postMessage('closeModal', '*')" class="back-link">
                    <svg class="back-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M17 10a.75.75 0 0 1-.75.75H5.612l4.158 3.96a.75.75 0 1 1-1.04 1.08l-5.5-5.25a.75.75 0 0 1 0-1.08l5.5-5.25a.75.75 0 1 1 1.04 1.08L5.612 9.25H16.25A.75.75 0 0 1 17 10" clip-rule="evenodd"></path>
                    </svg>
                    <span class="back-text">返回</span>
                </a>
                <h1 class="title">系统设置</h1>
            </div>
        </header>

        <main class="main-content">
            <form method="POST" id="siteConfigForm" class="card">
                <h2 class="card-title">网站基本信息设置</h2>
                
                <div class="form-group mb-6">
                    <label for="OTIXxname" class="form-label">网站名称</label>
                    <input type="text" id="OTIXxname" name="OTIXxname" class="input" 
                           value="<?php echo htmlspecialchars($config['site_name'] ?? ''); ?>" 
                           placeholder="例如: 欧钛网络" required>
                    <p class="form-hint">用于设置网站名称，显示在页面头部</p>
                </div>

                <div class="form-group mb-6">
                    <label for="siteUrl" class="form-label">URL源站(防死域名)</label>
                    <input type="text" id="siteUrl" name="siteUrl" class="input" 
                           value="<?php echo htmlspecialchars($config['site_url'] ?? ''); ?>" 
                           placeholder="<?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/chat?u='; ?>">
                    <p class="form-hint">用于设置URL源站地址，可设置外源地址</p>
                </div>

                <div class="form-group mb-6">
                    <label class="form-label">是否开启URL源站跳转</label>
                    <div class="switch-container">
                        <label class="toggle-switch">
                            <input type="checkbox" name="siteUrlEnabled" id="siteUrlEnabled" <?php echo ($config['site_url_enabled'] ?? 0) ? 'checked' : ''; ?>>
                            <span class="switch-slider"></span>
                        </label>
                    </div>
                    <p class="form-hint">开启后，分享链接会使用URL源站地址进行跳转</p>
                </div>
                
                <button type="submit" class="btn btn-primary mt-4" name="save_site_settings">
                    保存网站设置
                </button>
            </form>

            <form method="POST" id="popupConfigForm" class="card">
                <h2 class="card-title">弹窗公告设置</h2>
                
                <div class="checkbox-switch">
                    <input type="checkbox" id="popupEnabled" name="popupEnabled" 
                           <?php echo ($config['popup_enabled'] ?? 0) ? 'checked' : ''; ?>>
                    <label for="popupEnabled">启用弹窗公告</label>
                </div>
                <p class="form-hint mb-6">用户访问网站时显示弹窗公告</p>
                
                <div class="form-group mb-6">
                    <label for="popupTitle" class="form-label">公告标题</label>
                    <input type="text" id="popupTitle" name="popupTitle" class="input" 
                           value="<?php echo htmlspecialchars($config['popup_title'] ?? ''); ?>" 
                           placeholder="请输入公告标题" required>
                </div>
                
                <div class="form-group mb-6">
                    <label for="popupContent" class="form-label">公告内容</label>
                    <textarea id="popupContent" name="popupContent" class="textarea" 
                              placeholder="请输入公告内容" required><?php echo htmlspecialchars($config['popup_content'] ?? ''); ?></textarea>
                    <p class="form-hint">支持多行文本，用户首次访问时会看到此公告</p>
                </div>
                
                <button type="submit" class="btn btn-primary mt-4" name="save_popup_settings">
                    保存弹窗设置
                </button>
            </form>

            <form method="POST" id="telegramConfigForm" class="card">
                <h2 class="card-title">Telegram设置</h2>
                
                <div class="form-group mb-6">
                    <label for="telegramUsername" class="form-label">用户名</label>
                    <input type="text" id="telegramUsername" name="telegramUsername" class="input" 
                           value="<?php echo htmlspecialchars($config['telegram_username'] ?? ''); ?>" 
                           placeholder="例如: @username">
                    <p class="form-hint">用于系统通知的Telegram用户名</p>
                </div>
                
                <button type="submit" class="btn btn-primary mt-4" name="save_telegram_settings">
                    保存Telegram设置
                </button>
            </form>

            <form method="POST" id="storageConfigForm" class="card">
                <h2 class="card-title">图片存储设置</h2>
                
                <div class="form-group mb-6">
                    <label class="form-label">存储方式</label>
                    <div style="display: flex; align-items: center; padding: 0.5rem 0.75rem; border: 1px solid var(--gray-300); border-radius: 0.375rem; background-color: var(--gray-50);">
                        <input type="radio" id="storage_local" name="storageType" value="local" checked disabled>
                        <label for="storage_local" style="margin-left: 0.5rem; font-size: 0.875rem; color: var(--gray-700);">本地存储</label>
                    </div>
                    <p class="form-hint">图片将存储在服务器本地目录中</p>
                </div>
                
                <button type="submit" class="btn btn-primary mt-4" name="save_storage_settings">
                    保存存储设置
                </button>
            </form>

            <form method="POST" id="pwaConfigForm" class="card" enctype="multipart/form-data">
                <h2 class="card-title">PWA 设置（添加到主屏幕）</h2>
                
                <div class="form-group mb-6">
                    <label for="pwaName" class="form-label">应用名称</label>
                    <input type="text" id="pwaName" name="pwaName" class="input" 
                           value="<?php echo htmlspecialchars($config['pwa_name'] ?? ''); ?>" 
                           placeholder="例如: XE控制台" required>
                    <p class="form-hint">iOS 添加到主屏幕时显示的完整名称</p>
                </div>
                
                <div class="form-group mb-6">
                    <label for="pwaShortName" class="form-label">简称</label>
                    <input type="text" id="pwaShortName" name="pwaShortName" class="input" 
                           value="<?php echo htmlspecialchars($config['pwa_short_name'] ?? ''); ?>" 
                           placeholder="例如: XE" required>
                    <p class="form-hint">主屏幕图标下方显示的简短名称</p>
                </div>
                
                <div class="form-group mb-6">
                    <label for="pwaIcon" class="form-label">应用图标</label>
                    <input type="file" id="pwaIcon" name="pwaIcon" class="input" 
                           accept="image/png,image/jpeg" style="padding: 0.375rem 0.75rem;">
                    <p class="form-hint">支持 PNG/JPG 格式，建议尺寸 512x512px，上传后将替换现有图标</p>
                    <?php if (!empty($config['pwa_icon'])): ?>
                    <div style="margin-top: 0.5rem;">
                        <img src="<?php echo htmlspecialchars($config['pwa_icon']); ?>" 
                             alt="当前图标" style="width: 64px; height: 64px; border-radius: 12px; object-fit: cover;">
                    </div>
                    <?php endif; ?>
                </div>
                
                <button type="submit" class="btn btn-primary mt-4" name="save_pwa_settings">
                    保存PWA设置
                </button>
            </form>

            <form method="POST" id="globalConfigForm" class="card">
                <h2 class="card-title">全局操作</h2>
                
                <div class="form-group">
                    <label for="reset_to_default" class="form-label">恢复默认配置</label>
                    <p class="form-hint">将所有设置恢复为默认值</p>
                </div>
                
                <button type="submit" class="btn btn-primary mt-4" name="reset_all_settings" id="resetBtn" style="padding-left: 1.5rem; padding-right: 1.5rem;">
                    恢复默认
                </button>
            </form>
        </main>
    </div>

    <script>
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const card = this.closest('.card');
                const cardTitle = card.querySelector('.card-title').textContent;
                
                if (this.id === 'popupConfigForm') {
                    const popupEnabled = document.getElementById('popupEnabled').checked;
                    const popupTitle = document.getElementById('popupTitle').value.trim();
                    const popupContent = document.getElementById('popupContent').value.trim();
                    
                    if (popupEnabled) {
                        if (!popupTitle) {
                            alert('请填写公告标题');
                            e.preventDefault();
                            return false;
                        }
                        if (!popupContent) {
                            alert('请填写公告内容');
                            e.preventDefault();
                            return false;
                        }
                    }
                }
                
                if (this.id === 'siteConfigForm') {
                    const requiredFields = ['OTIXxname'];
                    for (const fieldId of requiredFields) {
                        const field = document.getElementById(fieldId);
                        if (!field.value.trim()) {
                            alert(`请填写${field.previousElementSibling.textContent}`);
                            e.preventDefault();
                            field.focus();
                            return false;
                        }
                    }
                }
                
                if (this.id === 'globalConfigForm' && e.submitter.name === 'reset_all_settings') {
                    if (!confirm('确定要恢复默认配置吗？所有当前修改将会丢失。')) {
                        e.preventDefault();
                        return false;
                    }
                }
                
                setTimeout(() => {
                    alert(`已保存 ${cardTitle} 的设置`);
                }, 100);
                
                return true;
            });
        });

        document.addEventListener('DOMContentLoaded', function() {
            document.body.style.opacity = '0';
            setTimeout(() => {
                document.body.style.transition = 'opacity 0.3s';
                document.body.style.opacity = '1';
            }, 10);
        });
        
        <?php if (isset($successMessage) && !empty($successMessage)): ?>
        setTimeout(() => {
            alert("<?php echo htmlspecialchars($successMessage); ?>");
        }, 100);
        <?php elseif (isset($errorMessage) && !empty($errorMessage)): ?>
        setTimeout(() => {
            alert("<?php echo htmlspecialchars($errorMessage); ?>");
        }, 100);
        <?php endif; ?>
    </script>
</body>
</html>