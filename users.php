<?php
if (!defined('FROM_ROUTER')) {
    http_response_code(403);
    exit('喜乐科技@x60898');
}
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/dbconfig.php';
checkLogin();
checkAdmin();

$db = getDB();

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$db) {
        $error_message = "数据库连接失败";
    } else {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add_user':
                    $username = trim($_POST['username']);
                    $password = $_POST['password'];
                    $balance = floatval($_POST['balance']);
                    $expire_time = $_POST['expire_time'];
                    
                    if (empty($username) || empty($password)) {
                        $error_message = "用户名和密码不能为空";
                    } else {
                        $stmt = $db->prepare("INSERT INTO users (username, password, role, balance, expire_time) VALUES (?, MD5(?), 'user', ?, ?)");
                        $stmt->bind_param("ssds", $username, $password, $balance, $expire_time);
                        
                        if ($stmt->execute()) {
                            $success_message = "用户添加成功";
                        } else {
                            $error_message = "添加用户失败: " . $db->error;
                        }
                    }
                    break;
                    
                case 'edit_user':
                    $user_id = intval($_POST['user_id']);
                    $username = trim($_POST['username']);
                    $balance = floatval($_POST['balance']);
                    $expire_time = $_POST['expire_time'];
                    
                    $sql = "UPDATE users SET username = ?, balance = ?, expire_time = ?";
                    $params = [$username, $balance, $expire_time];
                    $types = "sds";
                    
                    if (!empty($_POST['password'])) {
                        $sql .= ", password = MD5(?)";
                        $params[] = $_POST['password'];
                        $types .= "s";
                    }
                    
                    $sql .= " WHERE id = ?";
                    $params[] = $user_id;
                    $types .= "i";
                    
                    $stmt = $db->prepare($sql);
                    $stmt->bind_param($types, ...$params);
                    
                    if ($stmt->execute()) {
                        $success_message = "用户信息更新成功";
                    } else {
                        $error_message = "更新用户信息失败: " . $db->error;
                    }
                    break;
                    
                case 'delete_user':
                    $user_id = intval($_POST['user_id']);
                    $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
                    $stmt->bind_param("i", $user_id);
                    
                    if ($stmt->execute()) {
                        $success_message = "用户删除成功";
                    } else {
                        $error_message = "删除用户失败: " . $db->error;
                    }
                    break;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>用户管理</title>
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
            --red-500: #ef4444;
            --green-500: #10b981;
            --amber-500: #f59e0b;
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
        
        /* Header */
        .header {
            position: sticky;
            top: 0;
            z-index: 10;
            background-color: var(--white);
            padding: 1rem;
            border-bottom: 1px solid var(--gray-200);
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        }
        
        .header-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .header-left {
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
        
        /* Main Content */
        .main-content {
            padding: 1rem;
        }
        
        /* Cards */
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
        
        /* 用户卡片样式 */
        .user-cards {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1rem;
        }
        
        .user-card {
            background-color: var(--white);
            border-radius: 0.5rem;

            padding: 1.25rem;
            border: 1px solid var(--gray-200);
            transition: all 0.2s ease;
        }
        
        .user-card:hover {
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }
        
        .user-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.75rem;
        }
        
        .user-id {
            font-size: 0.75rem;
            color: var(--gray-500);
            background: var(--gray-100);
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
        }
        
        .user-username {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: 0.5rem;
        }
        
        .user-details {
            margin-bottom: 1rem;
        }
        
        .user-detail {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
        }
        
        .detail-label {
            color: var(--gray-500);
        }
        
        .detail-value {
            color: var(--gray-700);
            font-weight: 500;
        }
        
        .user-balance {
            color: var(--amber-500);
            font-weight: 600;
        }
        
        /* Stats */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .stat-card {
            background-color: var(--white);
            border-radius: 0.5rem;
            padding: 1rem;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
        }
        
        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--gray-900);
        }
        
        .stat-label {
            font-size: 0.875rem;
            color: var(--gray-500);
            margin-top: 0.25rem;
        }
        
        /* Button */
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
        
        .btn-danger {
            background-color: var(--red-500);
            color: var(--white);
        }
        
        .btn-danger:hover {
            background-color: #dc2626;
        }
        
        .btn-outline {
            background-color: var(--gray-200);
            color: var(--gray-800);
        }
        
        .btn-outline:hover {
            background-color: var(--gray-300);
        }
        
        .btn-sm {
            padding: 0.25rem 0.75rem;
            font-size: 0.75rem;
        }
        
        /* Status Badge */
        .status {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .status-warning {
            background-color: rgba(245, 158, 11, 0.1);
            color: #f59e0b;
        }
        
        .status-success {
            background-color: rgba(16, 185, 129, 0.1);
            color: var(--green-500);
        }
        
        .status-danger {
            background-color: rgba(239, 68, 68, 0.1);
            color: var(--red-500);
        }
        
        .status-info {
            background-color: rgba(59, 130, 246, 0.1);
            color: var(--blue-500);
        }
        
        /* Actions */
        .user-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }
        
        /* Message */
        .message {
            padding: 1rem;
            border-radius: 0.375rem;
            margin-bottom: 1rem;
            font-size: 0.875rem;
        }
        
        .message-success {
            background-color: rgba(16, 185, 129, 0.1);
            color: var(--green-500);
            border: 1px solid rgba(16, 185, 129, 0.2);
        }
        
        .message-error {
            background-color: rgba(239, 68, 68, 0.1);
            color: var(--red-500);
            border: 1px solid rgba(239, 68, 68, 0.2);
        }
        
        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: var(--white);
            border-radius: 0.5rem;
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-header {
            padding: 1.5rem 1.5rem 1rem;
            border-bottom: 1px solid var(--gray-200);
        }
        
        .modal-header h3 {
            font-size: 1rem;
            font-weight: 700;
            color: var(--gray-900);
        }
        
        .modal-body {
            padding: 1.5rem;
        }
        
        .modal-close {
            float: right;
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--gray-500);
        }
        
        /* Form Elements */
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
            font-size: 0.875rem;
        }
        
        .input:focus {
            outline: none;
            border-color: transparent;
            box-shadow: 0 0 0 2px var(--blue-500);
        }
        
        .input::placeholder {
            color: var(--gray-400);
        }
        
        /* Form Actions */
        .form-actions {
            display: flex;
            gap: 0.75rem;
            margin-top: 1.5rem;
        }
        
        .form-actions .btn {
            flex: 1;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 2rem 1rem;
            color: var(--gray-500);
        }
  
        
        /* Helper classes */
        .mt-2 {
            margin-top: 0.5rem;
        }
        
        .mt-4 {
            margin-top: 1rem;
        }
        
        .mb-4 {
            margin-bottom: 1rem;
        }
        
        .mb-6 {
            margin-bottom: 1.5rem;
        }
        
        .w-full {
            width: 100%;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <header class="header">
            <div class="header-content">
                <div class="header-left">
                    <a href="javascript:void(0)" onclick="window.parent.postMessage('closeModal', '*')" class="back-link">
                        <svg class="back-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M17 10a.75.75 0 0 1-.75.75H5.612l4.158 3.96a.75.75 0 1 1-1.04 1.08l-5.5-5.25a.75.75 0 0 1 0-1.08l5.5-5.25a.75.75 0 1 1 1.04 1.08L5.612 9.25H16.25A.75.75 0 0 1 17 10" clip-rule="evenodd"></path>
                        </svg>
                        <span class="back-text">返回</span>
                    </a>
                    <h1 class="title">用户管理</h1>
                </div>
                <button class="btn btn-primary" onclick="openAddModal()">+ 添加用户</button>
            </div>
        </header>

        <!-- Main Content -->
        <main class="main-content">
            <?php if (isset($success_message)): ?>
            <div class="message message-success">
                <?php echo htmlspecialchars($success_message); ?>
            </div>
            <?php elseif (isset($error_message)): ?>
            <div class="message message-error">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
            <?php endif; ?>
            
            <!-- 统计信息 -->
            <div class="stats-grid">
                <?php
                if ($db) {
                    $result = $db->query("SELECT COUNT(*) as count FROM users");
                    $total_users = $result ? $result->fetch_assoc()['count'] : 0;
                    
                    $result = $db->query("SELECT COUNT(*) as count FROM users WHERE role = 'admin'");
                    $admin_count = $result ? $result->fetch_assoc()['count'] : 0;
                    
                    $result = $db->query("SELECT COUNT(*) as count FROM users WHERE role = 'user'");
                    $user_count = $result ? $result->fetch_assoc()['count'] : 0;
                    
                    $result = $db->query("SELECT COUNT(*) as count FROM users WHERE expire_time < NOW()");
                    $expired_count = $result ? $result->fetch_assoc()['count'] : 0;
                } else {
                    $total_users = $admin_count = $user_count = $expired_count = 0;
                }
                ?>
                
                <div class="stat-card">
                    <div class="stat-value"><?php echo $total_users; ?></div>
                    <div class="stat-label">总用户数</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $admin_count; ?></div>
                    <div class="stat-label">管理员</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $user_count; ?></div>
                    <div class="stat-label">普通用户</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $expired_count; ?></div>
                    <div class="stat-label">已过期</div>
                </div>
            </div>
            
            <!-- 用户列表 -->
            <section class="card">
                <h2 class="card-title">用户列表</h2>
                
                <?php if ($db): ?>
                <div class="user-cards">
                    <?php
                    $result = $db->query("SELECT * FROM users ORDER BY id DESC");
                    if ($result && $result->num_rows > 0) {
                        while ($user = $result->fetch_assoc()) {
                            $role_badge = $user['role'] === 'admin' ? 'status-warning' : 'status-success';
                            
                            // 计算剩余天数
                            $expire_time = strtotime($user['expire_time']);
                            $current_time = time();
                            $diff_seconds = $expire_time - $current_time;
                            $days_left = floor($diff_seconds / (60 * 60 * 24));
                            
                            if ($days_left < 0) {
                                $days_display = "已过期";
                                $status_class = "status-danger";
                            } else if ($days_left == 0) {
                                $days_display = "今天到期";
                                $status_class = "status-danger";
                            } else {
                                $days_display = "剩余" . $days_left . "天";
                                $status_class = "status-info";
                            }
                            ?>
                            <div class="user-card">
                                <div class="user-header">
                                    <span class="user-id">#<?php echo $user['id']; ?></span>
                                    <span class="status <?php echo $role_badge; ?>"><?php echo $user['role'] === 'admin' ? '管理员' : '普通用户'; ?></span>
                                </div>
                                
                                <h3 class="user-username"><?php echo htmlspecialchars($user['username']); ?></h3>
                                
                                <div class="user-details">
                                    <div class="user-detail">
                                        <span class="detail-label">余额:</span>
                                        <span class="detail-value user-balance">¥<?php echo number_format($user['balance'], 2); ?></span>
                                    </div>
                                    
                                    <div class="user-detail">
                                        <span class="detail-label">过期时间:</span>
                                        <span class="status <?php echo $status_class; ?>"><?php echo $days_display; ?></span>
                                    </div>
                                    
                                    <div class="user-detail">
                                        <span class="detail-label">具体时间:</span>
                                        <span class="detail-value"><?php echo date('Y-m-d H:i', $expire_time); ?></span>
                                    </div>
                                </div>
                                
                                <div class="user-actions">
                                    <button class="btn btn-outline btn-sm" style="flex: 1;" onclick="openEditModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>', <?php echo $user['balance']; ?>, '<?php echo $user['expire_time']; ?>')">编辑</button>
                                    <button class="btn btn-danger btn-sm" style="flex: 1;" onclick="confirmDelete(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">删除</button>
                                </div>
                            </div>
                            <?php
                        }
                    } else {
                        echo '<div class="empty-state">暂无用户数据</div>';
                    }
                    ?>
                </div>
                <?php else: ?>
                <div class="empty-state">数据库连接失败</div>
                <?php endif; ?>
            </section>
        </main>
    </div>
    
    <!-- 添加用户模态框 -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>添加新用户</h3>
                <button class="modal-close" onclick="closeModal('addModal')">×</button>
            </div>
            <div class="modal-body">
                <form method="POST" id="addForm">
                    <input type="hidden" name="action" value="add_user">
                    <div class="form-group">
                        <label class="form-label">用户名</label>
                        <input type="text" name="username" class="input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">密码</label>
                        <input type="password" name="password" class="input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">余额</label>
                        <input type="number" name="balance" class="input" value="0.00" step="0.01" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">过期时间</label>
                        <input type="datetime-local" name="expire_time" class="input" required>
                    </div>
                    <div class="form-actions">
                        <button type="button" class="btn btn-outline" onclick="closeModal('addModal')">取消</button>
                        <button type="submit" class="btn btn-primary">添加用户</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- 编辑用户模态框 -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>编辑用户</h3>
                <button class="modal-close" onclick="closeModal('editModal')">×</button>
            </div>
            <div class="modal-body">
                <form method="POST" id="editForm">
                    <input type="hidden" name="action" value="edit_user">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    <div class="form-group">
                        <label class="form-label">用户名</label>
                        <input type="text" name="username" id="edit_username" class="input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">新密码（留空不修改）</label>
                        <input type="password" name="password" class="input" placeholder="留空则不修改密码">
                    </div>
                    <div class="form-group">
                        <label class="form-label">余额</label>
                        <input type="number" name="balance" id="edit_balance" class="input" step="0.01" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">过期时间</label>
                        <input type="datetime-local" name="expire_time" id="edit_expire_time" class="input" required>
                    </div>
                    <div class="form-actions">
                        <button type="button" class="btn btn-outline" onclick="closeModal('editModal')">取消</button>
                        <button type="submit" class="btn btn-primary">保存修改</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- 删除确认表单 -->
    <form id="deleteForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="delete_user">
        <input type="hidden" name="user_id" id="delete_user_id">
    </form>
    
    <script>
        // 设置默认过期时间为一个月后
        document.addEventListener('DOMContentLoaded', function() {
            const now = new Date();
            now.setMonth(now.getMonth() + 1);
            const defaultTime = now.toISOString().slice(0, 16);
            document.querySelector('input[name="expire_time"]').value = defaultTime;
        });
        
        // 消息自动隐藏
        const messages = document.querySelectorAll('.message');
        messages.forEach(message => {
            setTimeout(() => {
                message.style.opacity = '0';
                message.style.transition = 'opacity 0.3s ease-out';
                setTimeout(() => {
                    message.style.display = 'none';
                }, 300);
            }, 3000);
        });
        
        // 模态框控制
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'flex';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        };
        
        // 添加用户模态框
        function openAddModal() {
            openModal('addModal');
        }
        
        // 编辑用户模态框
        function openEditModal(id, username, balance, expireTime) {
            const formattedTime = expireTime.replace(' ', 'T').substring(0, 16);
            
            document.getElementById('edit_user_id').value = id;
            document.getElementById('edit_username').value = username;
            document.getElementById('edit_balance').value = balance;
            document.getElementById('edit_expire_time').value = formattedTime;
            openModal('editModal');
        }
        
        // 删除用户确认
        function confirmDelete(userId, username) {
            if (confirm(`确定要删除用户 "${username}" 吗？此操作不可撤销！`)) {
                document.getElementById('delete_user_id').value = userId;
                document.getElementById('deleteForm').submit();
            }
        }
        
        // 表单验证
        document.getElementById('addForm')?.addEventListener('submit', function(e) {
            const username = this.elements['username'].value.trim();
            const password = this.elements['password'].value;
            
            if (!username || !password) {
                e.preventDefault();
                alert('用户名和密码不能为空');
                return false;
            }
            
            if (password.length < 6) {
                e.preventDefault();
                alert('密码长度至少6位');
                return false;
            }
            
            return true;
        });
        
        document.getElementById('editForm')?.addEventListener('submit', function(e) {
            const username = this.elements['username'].value.trim();
            
            if (!username) {
                e.preventDefault();
                alert('用户名不能为空');
                return false;
            }
            
            return true;
        });
    </script>
</body>
</html>