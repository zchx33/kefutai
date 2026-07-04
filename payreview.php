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

// 分页设置
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = isset($_GET['per_page']) ? intval($_GET['per_page']) : 10;
$per_page_options = [10, 20, 50, 100];
if (!in_array($per_page, $per_page_options)) {
    $per_page = 10;
}
$offset = ($page - 1) * $per_page;

// 处理删除操作
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_records'])) {
        $record_ids = $_POST['record_ids'] ?? [];
        if (!empty($record_ids)) {
            $db = getDB();
            if ($db) {
                $placeholders = implode(',', array_fill(0, count($record_ids), '?'));
                $types = str_repeat('i', count($record_ids));
                $stmt = $db->prepare("DELETE FROM recharge_records WHERE id IN ($placeholders)");
                $stmt->bind_param($types, ...$record_ids);
                if ($stmt->execute()) {
                    $_SESSION['message'] = '成功删除 ' . $stmt->affected_rows . ' 条记录';
                    $_SESSION['message_type'] = 'success';
                } else {
                    $_SESSION['message'] = '删除失败';
                    $_SESSION['message_type'] = 'error';
                }
            }
        } else {
            $_SESSION['message'] = '请选择要删除的记录';
            $_SESSION['message_type'] = 'error';
        }
    } elseif (isset($_POST['delete_single'])) {
        $record_id = intval($_POST['record_id']);
        $db = getDB();
        if ($db) {
            $stmt = $db->prepare("DELETE FROM recharge_records WHERE id = ?");
            $stmt->bind_param("i", $record_id);
            if ($stmt->execute()) {
                $_SESSION['message'] = '记录删除成功';
                $_SESSION['message_type'] = 'success';
            } else {
                $_SESSION['message'] = '删除失败';
                $_SESSION['message_type'] = 'error';
            }
        }
    } elseif (isset($_POST['action'])) {
        $record_id = intval($_POST['record_id']);
        $action = $_POST['action'];
        $review_notes = $_POST['review_notes'] ?? '';
        $show_notes_to_user = isset($_POST['show_notes_to_user']) ? 1 : 0;
        
        $db = getDB();
        if ($db) {
            if ($action === 'approve') {
                $stmt = $db->prepare("SELECT username, amount FROM recharge_records WHERE id = ? AND status = 'pending'");
                $stmt->bind_param("i", $record_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($record = $result->fetch_assoc()) {
                    if (updateUserBalance($record['username'], $record['amount'])) {
                        $stmt = $db->prepare("UPDATE recharge_records SET status = 'approved', reviewed_at = NOW(), review_notes = ? WHERE id = ?");
                        $stmt->bind_param("si", $review_notes, $record_id);
                        $stmt->execute();
                        $_SESSION['message'] = '审核通过，用户余额已更新';
                        $_SESSION['message_type'] = 'success';
                    } else {
                        $_SESSION['message'] = '更新用户余额失败';
                        $_SESSION['message_type'] = 'error';
                    }
                } else {
                    $_SESSION['message'] = '记录不存在或已被处理';
                    $_SESSION['message_type'] = 'error';
                }
            } elseif ($action === 'reject') {
                $stmt = $db->prepare("UPDATE recharge_records SET status = 'rejected', reviewed_at = NOW(), review_notes = ?, show_notes_to_user = ? WHERE id = ?");
                $stmt->bind_param("sii", $review_notes, $show_notes_to_user, $record_id);
                if ($stmt->execute()) {
                    $_SESSION['message'] = '审核拒绝完成';
                    $_SESSION['message_type'] = 'success';
                } else {
                    $_SESSION['message'] = '操作失败';
                    $_SESSION['message_type'] = 'error';
                }
            }
        } else {
            $_SESSION['message'] = '数据库连接失败';
            $_SESSION['message_type'] = 'error';
        }
    }
    
    header("Location: " . $_SERVER['PHP_SELF'] . "?page=" . $page . "&per_page=" . $per_page);
    exit;
}

// 获取总记录数
$total_records = 0;
$db = getDB();
if ($db) {
    $result = $db->query("SELECT COUNT(*) as total FROM recharge_records");
    if ($result) {
        $row = $result->fetch_assoc();
        $total_records = $row['total'];
    }
}
$total_pages = ceil($total_records / $per_page);

// 获取当前页的记录
$recharge_records = getRechargeRecords(null, null, $per_page, $offset);
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>充值审核</title>
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
            --orange-500: #f59e0b;
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
        
        /* Toolbar */
        .toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
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
        
        .btn-success {
            background-color: var(--green-500);
            color: var(--white);
        }
        
        .btn-success:hover {
            background-color: #059669;
        }
        
        .btn-warning {
            background-color: var(--orange-500);
            color: var(--white);
        }
        
        .btn-warning:hover {
            background-color: #d97706;
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
        
        /* Table */
        .table-container {
            overflow-x: auto;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.875rem;
        }
        
        .table th {
            background-color: var(--gray-50);
            text-align: left;
            padding: 0.75rem 1rem;
            font-weight: 600;
            color: var(--gray-600);
            border-bottom: 1px solid var(--gray-200);
        }
        
        .table td {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid var(--gray-200);
        }
        
        .table tr:hover {
            background-color: var(--gray-50);
        }
        
        /* Status Badge */
        .status {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .status-pending {
            background-color: rgba(245, 158, 11, 0.1);
            color: #f59e0b;
        }
        
        .status-approved {
            background-color: rgba(16, 185, 129, 0.1);
            color: var(--green-500);
        }
        
        .status-rejected {
            background-color: rgba(239, 68, 68, 0.1);
            color: var(--red-500);
        }
        
        /* Actions */
        .actions {
            display: flex;
            gap: 0.5rem;
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
        
        .textarea {
            width: 100%;
            border: 1px solid var(--gray-300);
            border-radius: 0.375rem;
            padding: 0.5rem 0.75rem;
            color: var(--gray-900);
            font-size: 0.875rem;
            min-height: 6rem;
            resize: vertical;
            font-family: 'Noto Sans SC', sans-serif;
        }
        
        .textarea:focus {
            outline: none;
            border-color: transparent;
            box-shadow: 0 0 0 2px var(--blue-500);
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
        
        /* Checkbox */
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: 1rem;
            height: 1rem;
        }
        
        /* Select */
        .select {
            width: 100%;
            border: 1px solid var(--gray-300);
            border-radius: 0.375rem;
            padding: 0.5rem 0.75rem;
            color: var(--gray-900);
            font-size: 0.875rem;
            background-color: var(--white);
            cursor: pointer;
        }
        
        .select:focus {
            outline: none;
            border-color: transparent;
            box-shadow: 0 0 0 2px var(--blue-500);
        }
        
        /* Bulk Actions */
        .bulk-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            background-color: var(--white);
            border-radius: 0.5rem;
        }
        
        .select-all {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            margin-top: 1.5rem;
        }
        
        .page-link {
            padding: 0.5rem 0.75rem;
            border: 1px solid var(--gray-300);
            border-radius: 0.375rem;
            text-decoration: none;
            color: var(--gray-700);
            font-size: 0.875rem;
        }
        
        .page-link:hover {
            background-color: var(--gray-100);
        }
        
        .page-link.active {
            background-color: var(--black);
            color: var(--white);
            border-color: var(--black);
        }
        
        /* Per Page Selector */
        .per-page-selector {
            padding: 0.5rem 0.75rem;
            border: 1px solid var(--gray-300);
            border-radius: 0.375rem;
            font-size: 0.875rem;
            background-color: var(--white);
            cursor: pointer;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 2rem 1rem;
            color: var(--gray-500);
        }
        
        /* Notes Button */
        .notes-btn {
            background: none;
            border: 1px solid var(--gray-300);
            color: var(--gray-600);
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            cursor: pointer;
        }
        
        .notes-btn:hover {
            background-color: var(--gray-100);
        }
        
        /* Responsive */
        @media (min-width: 640px) {
            .stats-grid {
                grid-template-columns: repeat(4, 1fr);
            }
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
        
        .text-muted {
            color: var(--gray-500);
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <header class="header">
            <div class="header-content">
                <a href="javascript:void(0)" onclick="window.parent.postMessage('closeModal', '*')" class="back-link">
                    <svg class="back-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M17 10a.75.75 0 0 1-.75.75H5.612l4.158 3.96a.75.75 0 1 1-1.04 1.08l-5.5-5.25a.75.75 0 0 1 0-1.08l5.5-5.25a.75.75 0 1 1 1.04 1.08L5.612 9.25H16.25A.75.75 0 0 1 17 10" clip-rule="evenodd"></path>
                    </svg>
                    <span class="back-text">返回</span>
                </a>
                <h1 class="title">充值审核</h1>
            </div>
         
        </header>

        <!-- Main Content -->
        <main class="main-content">
            <!-- 消息提示 -->
            <?php if (isset($_SESSION['message'])): ?>
                <div class="message message-<?php echo $_SESSION['message_type']; ?>">
                    <?php echo htmlspecialchars($_SESSION['message']); ?>
                </div>
                <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
            <?php endif; ?>

            <!-- 统计信息 -->
            <section class="card">
                <h2 class="card-title">统计信息</h2>
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $total_records; ?></div>
                        <div class="stat-label">总记录数</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo getPendingCount(); ?></div>
                        <div class="stat-label">待审核</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo getApprovedCount(); ?></div>
                        <div class="stat-label">已通过</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo getRejectedCount(); ?></div>
                        <div class="stat-label">已拒绝</div>
                    </div>
                </div>
            </section>
            
            <!-- 批量操作 -->
            <section class="card">
                <div class="bulk-actions">
                    <div class="select-all">
                        <input type="checkbox" id="selectAll">
                        <label for="selectAll">全选</label>
                    </div>
                    <form method="POST" id="bulkDeleteForm">
                        <input type="hidden" name="delete_records" value="1">
                        <button type="submit" class="btn btn-danger">删除选中</button>
                    </form>
                </div>
            </section>
            
            <!-- 充值记录列表 -->
            <section class="card">
                <h2 class="card-title">充值记录列表</h2>
                
                <?php if (!empty($recharge_records)): ?>
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th width="40">
                                    <input type="checkbox" id="headerCheckbox">
                                </th>
                                <th>序号</th>
                                <th>用户账号</th>
                                <th>充值金额</th>
                                <th>状态</th>
                                <th>充值时间</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recharge_records as $index => $record): ?>
                            <tr>
                                <td>
                                    <input type="checkbox" name="record_ids[]" value="<?php echo $record['id']; ?>" class="record-checkbox">
                                </td>
                                <td><?php echo $offset + $index + 1; ?></td>
                                <td><?php echo htmlspecialchars($record['username']); ?></td>
                                <td>¥<?php echo number_format($record['amount'], 2); ?></td>
                                <td>
                                    <?php 
                                    $status_text = [
                                        'pending' => '未审核',
                                        'approved' => '通过',
                                        'rejected' => '未通过'
                                    ];
                                    $status_class = [
                                        'pending' => 'status-pending',
                                        'approved' => 'status-approved',
                                        'rejected' => 'status-rejected'
                                    ];
                                    ?>
                                    <span class="status <?php echo $status_class[$record['status']]; ?>">
                                        <?php echo $status_text[$record['status']]; ?>
                                    </span>
                                    <?php if (!empty($record['user_notes'])): ?>
                                    <button type="button" class="notes-btn" onclick="showNotesModal('用户备注', '<?php echo htmlspecialchars(addslashes($record['user_notes'])); ?>')" style="margin-left: 0.5rem;">备注</button>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('Y-m-d H:i', strtotime($record['created_at'])); ?></td>
                                <td>
                                    <div class="actions">
                                        <?php if ($record['status'] === 'pending'): ?>
                                            <button class="btn btn-success btn-sm" onclick="showReviewModal(<?php echo $record['id']; ?>, 'approve')">通过</button>
                                            <button class="btn btn-warning btn-sm" onclick="showReviewModal(<?php echo $record['id']; ?>, 'reject')">拒绝</button>
                                        <?php endif; ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="delete_single" value="1">
                                            <input type="hidden" name="record_id" value="<?php echo $record['id']; ?>">
                                            <button type="submit" class="btn btn-outline btn-sm" onclick="return confirmDelete()">删除</button>
                                        </form>
                                        <?php if (!empty($record['review_notes'])): ?>
                                        <button type="button" class="notes-btn" onclick="showNotesModal('审核备注', '<?php echo htmlspecialchars(addslashes($record['review_notes'])); ?>')">审核备注</button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="empty-state">暂无充值记录</div>
                <?php endif; ?>
                
                <!-- 分页 -->
                <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <div style="flex: 1; font-size: 0.875rem; color: var(--gray-600);">
                        显示第 <?php echo $offset + 1; ?> - <?php echo min($offset + $per_page, $total_records); ?> 条，共 <?php echo $total_records; ?> 条记录
                    </div>
                    
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <!-- 每页显示条数选择 -->
                        <select class="per-page-selector" onchange="changePerPage(this.value)">
                            <?php foreach ($per_page_options as $option): ?>
                            <option value="<?php echo $option; ?>" <?php echo $per_page == $option ? 'selected' : ''; ?>>
                                <?php echo $option; ?> 条/页
                            </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <!-- 分页链接 -->
                        <?php if ($page > 1): ?>
                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&per_page=<?php echo $per_page; ?>">‹</a>
                        <?php endif; ?>
                        
                        <?php
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        
                        for ($i = $start_page; $i <= $end_page; $i++) {
                            if ($i == $page) {
                                echo '<a class="page-link active" href="?page=' . $i . '&per_page=' . $per_page . '">' . $i . '</a>';
                            } else {
                                echo '<a class="page-link" href="?page=' . $i . '&per_page=' . $per_page . '">' . $i . '</a>';
                            }
                        }
                        ?>
                        
                        <?php if ($page < $total_pages): ?>
                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&per_page=<?php echo $per_page; ?>">›</a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </section>
        </main>
    </div>
    
    <!-- 审核模态框 -->
    <div id="reviewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">审核操作</h3>
                <button class="modal-close" onclick="closeModal()">×</button>
            </div>
            <div class="modal-body">
                <form id="reviewForm" method="POST">
                    <input type="hidden" id="recordId" name="record_id">
                    <input type="hidden" id="actionType" name="action">
                    
                    <div class="form-group">
                        <label class="form-label" for="review_notes">审核备注</label>
                        <textarea id="review_notes" name="review_notes" class="textarea" placeholder="请输入审核备注（可选）"></textarea>
                    </div>
                    
                    <div id="rejectOptions" style="display: none;">
                        <div class="checkbox-group">
                            <input type="checkbox" id="show_notes_to_user" name="show_notes_to_user" value="1">
                            <label for="show_notes_to_user">允许用户查看此备注</label>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn btn-outline" onclick="closeModal()">取消</button>
                        <button type="submit" class="btn" id="submitBtn">确认</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- 备注查看模态框 -->
    <div id="notesModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="notesTitle">备注</h3>
                <button class="modal-close" onclick="closeNotesModal()">×</button>
            </div>
            <div class="modal-body">
                <div id="notesContent" style="padding: 1rem; background: var(--gray-50); border-radius: 0.375rem; min-height: 5rem;"></div>
                <div class="form-actions" style="margin-top: 1.5rem;">
                    <button type="button" class="btn btn-outline" onclick="closeNotesModal()">关闭</button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // 确认删除函数
        function confirmDelete() {
            return confirm('确定要删除这条记录吗？');
        }
        
        // 全选功能
        document.addEventListener('DOMContentLoaded', function() {
            const selectAllCheckbox = document.getElementById('selectAll');
            const headerCheckbox = document.getElementById('headerCheckbox');
            const recordCheckboxes = document.querySelectorAll('.record-checkbox');
            
            if (selectAllCheckbox) {
                selectAllCheckbox.addEventListener('change', function() {
                    recordCheckboxes.forEach(checkbox => {
                        checkbox.checked = this.checked;
                    });
                    if (headerCheckbox) {
                        headerCheckbox.checked = this.checked;
                    }
                });
            }
            
            if (headerCheckbox) {
                headerCheckbox.addEventListener('change', function() {
                    recordCheckboxes.forEach(checkbox => {
                        checkbox.checked = this.checked;
                    });
                    if (selectAllCheckbox) {
                        selectAllCheckbox.checked = this.checked;
                    }
                });
            }
            
            // 单个复选框变化时更新全选状态
            recordCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    updateSelectAllState();
                });
            });
            
            function updateSelectAllState() {
                const allChecked = recordCheckboxes.length > 0 && 
                    Array.from(recordCheckboxes).every(cb => cb.checked);
                
                if (selectAllCheckbox) {
                    selectAllCheckbox.checked = allChecked;
                }
                if (headerCheckbox) {
                    headerCheckbox.checked = allChecked;
                }
            }
            
            // 批量删除表单处理
            const bulkDeleteForm = document.getElementById('bulkDeleteForm');
            if (bulkDeleteForm) {
                bulkDeleteForm.addEventListener('submit', function(e) {
                    const checkedBoxes = document.querySelectorAll('.record-checkbox:checked');
                    if (checkedBoxes.length === 0) {
                        e.preventDefault();
                        alert('请至少选择一条记录');
                        return false;
                    }
                    
                    if (!confirm('确定要删除选中的记录吗？此操作不可恢复！')) {
                        e.preventDefault();
                        return false;
                    }
                    
                    // 将选中的记录ID添加到表单中
                    checkedBoxes.forEach(checkbox => {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'record_ids[]';
                        input.value = checkbox.value;
                        this.appendChild(input);
                    });
                    
                    return true;
                });
            }
            
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
        });
        
        // 每页显示条数切换
        function changePerPage(perPage) {
            window.location.href = `?page=1&per_page=${perPage}`;
        }
        
        // 审核模态框
        function showReviewModal(recordId, action) {
            document.getElementById('recordId').value = recordId;
            document.getElementById('actionType').value = action;
            
            const rejectOptions = document.getElementById('rejectOptions');
            const submitBtn = document.getElementById('submitBtn');
            
            if (action === 'reject') {
                document.getElementById('modalTitle').textContent = '拒绝审核';
                submitBtn.textContent = '确认拒绝';
                submitBtn.className = 'btn btn-warning';
                rejectOptions.style.display = 'block';
            } else {
                document.getElementById('modalTitle').textContent = '通过审核';
                submitBtn.textContent = '确认通过';
                submitBtn.className = 'btn btn-success';
                rejectOptions.style.display = 'none';
            }
            
            document.getElementById('reviewModal').style.display = 'flex';
        }
        
        function closeModal() {
            document.getElementById('reviewModal').style.display = 'none';
            const reviewNotes = document.getElementById('review_notes');
            if (reviewNotes) {
                reviewNotes.value = '';
            }
            const showNotesCheckbox = document.getElementById('show_notes_to_user');
            if (showNotesCheckbox) {
                showNotesCheckbox.checked = false;
            }
        }
        
        // 备注查看模态框
        function showNotesModal(title, notes) {
            document.getElementById('notesTitle').textContent = title;
            const notesContent = document.getElementById('notesContent');
            if (notesContent) {
                // 处理换行和特殊字符
                const formattedNotes = notes
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;')
                    .replace(/\n/g, '<br>');
                notesContent.innerHTML = formattedNotes;
            }
            document.getElementById('notesModal').style.display = 'flex';
        }
        
        function closeNotesModal() {
            document.getElementById('notesModal').style.display = 'none';
        }
        
        // 点击背景关闭模态框
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        };
    </script>
</body>
</html>

<?php
// 辅助函数
function getPendingCount() {
    $db = getDB();
    if ($db) {
        $result = $db->query("SELECT COUNT(*) as count FROM recharge_records WHERE status = 'pending'");
        if ($result) {
            $row = $result->fetch_assoc();
            return $row['count'];
        }
    }
    return 0;
}

function getApprovedCount() {
    $db = getDB();
    if ($db) {
        $result = $db->query("SELECT COUNT(*) as count FROM recharge_records WHERE status = 'approved'");
        if ($result) {
            $row = $result->fetch_assoc();
            return $row['count'];
        }
    }
    return 0;
}

function getRejectedCount() {
    $db = getDB();
    if ($db) {
        $result = $db->query("SELECT COUNT(*) as count FROM recharge_records WHERE status = 'rejected'");
        if ($result) {
            $row = $result->fetch_assoc();
            return $row['count'];
        }
    }
    return 0;
}
?>