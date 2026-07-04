<?php
if (!defined('FROM_ROUTER')) {
    http_response_code(403);
    exit('喜乐科技@x60898');
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/dbconfig.php';
checkLogin();
checkAdmin();
session_start();

$db = getDB();
if (!$db) {
    die('数据库连接失败');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'add_free_interface':
            handleAddFreeInterface($db);
            break;
        case 'batch_add_free_interface':
            handleBatchAddFreeInterface($db);
            break;
        case 'import_free_interface':
            handleImportFreeInterface($db);
            break;
        case 'delete_free_interface':
            handleDeleteFreeInterface($db);
            break;
        case 'batch_delete_free_interface':
            handleBatchDeleteFreeInterface($db);
            break;
        case 'delete_all_free_interface':
            handleDeleteAllFreeInterface($db);
            break;
        case 'edit_free_interface':
            handleEditFreeInterface($db);
            break;
        case 'delete_user_interface':
            handleDeleteUserInterface($db);
            break;
        case 'update_global_price':
            handleUpdateGlobalPrice($db);
            break;
        case 'add_paid_interface':
            handleAddPaidInterface($db);
            break;
        case 'batch_add_paid_interface':
            handleBatchAddPaidInterface($db);
            break;
        case 'import_paid_interface':
            handleImportPaidInterface($db);
            break;
        case 'delete_paid_interface':
            handleDeletePaidInterface($db);
            break;
        case 'batch_delete_paid_interface':
            handleBatchDeletePaidInterface($db);
            break;
        case 'delete_all_paid_interface':
            handleDeleteAllPaidInterface($db);
            break;
        case 'edit_paid_interface':
            handleEditPaidInterface($db);
            break;
    }
}

$free_interfaces = getFreeInterfaces($db);
$user_interfaces = getUserCustomInterfaces($db);
$paid_interfaces = getPaidInterfaces($db);
$current_price = getCurrentPrice($db);
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>接口管理</title>
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

        .btn-outline {
            background-color: var(--white);
            color: var(--gray-700);
            border: 1px solid var(--gray-300);
        }

        .btn-outline:hover {
            background-color: var(--gray-50);
        }

        .btn-sm {
            padding: 0.375rem 0.75rem;
            font-size: 0.75rem;
        }
        
        .form-row {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }
        
        .form-row .input {
            flex: 1 1 0%;
        }
        
        .select {
            width: 100%;
            border: 1px solid var(--gray-300);
            border-radius: 0.375rem;
            padding: 0.5rem 0.75rem;
            color: var(--gray-900);
            font-size: 1rem;
            background-color: var(--white);
            cursor: pointer;
        }
        
        .select:focus {
            outline: none;
            border-color: transparent;
            box-shadow: 0 0 0 2px var(--blue-500);
        }
        
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
        
        .status {
            display: inline-block;
            padding: 2px 5px;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .status-active {
            background-color: rgba(16, 185, 129, 0.1);
            color: var(--green-500);
        }
        
        .status-inactive {
            background-color: rgba(156, 163, 175, 0.1);
            color: var(--gray-500);
        }
        
        .status-sold {
            background-color: rgba(239, 68, 68, 0.1);
            color: var(--red-500);
        }
        
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
        
        .stat-value.price {
            color: var(--red-500);
        }
        
        .price-display {
            text-align: center;
            padding: 1rem;
            margin-bottom: 1rem;
            background-color: var(--gray-50);
            border-radius: 0.375rem;
        }
        
        .price-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--red-500);
        }
        
        .price-label {
            font-size: 0.875rem;
            color: var(--gray-500);
        }
        
        .actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .btn-icon {
            padding: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .empty-state {
            text-align: center;
            padding: 2rem 1rem;
            color: var(--gray-500);
        }
        
        .message {
            padding: 1rem;
            border-radius: 0.375rem;
            margin-bottom: 1rem;
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

        .tab-bar {
            display: flex;
            gap: 0;
            margin-bottom: 1.25rem;
            border-bottom: 2px solid var(--gray-200);
        }

        .tab-item {
            padding: 0.5rem 1.25rem;
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--gray-500);
            cursor: pointer;
            border-bottom: 2px solid transparent;
            margin-bottom: -2px;
            transition: color 0.15s, border-color 0.15s;
            user-select: none;
        }

        .tab-item:hover {
            color: var(--gray-700);
        }

        .tab-item.active {
            color: var(--black);
            border-bottom-color: var(--black);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .batch-hint {
            background-color: var(--gray-50);
            border: 1px solid var(--gray-200);
            border-radius: 0.375rem;
            padding: 0.75rem;
            margin-bottom: 1rem;
            font-size: 0.8125rem;
            color: var(--gray-600);
            line-height: 1.6;
        }

        .batch-hint code {
            background-color: var(--gray-100);
            padding: 1px 4px;
            border-radius: 3px;
            font-size: 0.75rem;
        }

        .list-toolbar {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }

        .list-toolbar .select-all-label {
            display: flex;
            align-items: center;
            gap: 0.375rem;
            font-size: 0.8125rem;
            color: var(--gray-600);
            cursor: pointer;
            user-select: none;
        }

        .list-toolbar .select-all-label input[type="checkbox"] {
            width: 16px;
            height: 16px;
            cursor: pointer;
        }

        .selected-count {
            font-size: 0.8125rem;
            color: var(--blue-500);
            font-weight: 500;
        }

        .row-checkbox {
            width: 16px;
            height: 16px;
            cursor: pointer;
        }
        
        @media (min-width: 640px) {
            .form-row {
                flex-direction: row;
                gap: 1rem;
            }
            
            .form-row .input {
                flex: 1;
            }
            
            .stats-grid {
                grid-template-columns: repeat(4, 1fr);
            }
        }
        
        .mt-2 { margin-top: 0.5rem; }
        .mt-4 { margin-top: 1rem; }
        .mb-4 { margin-bottom: 1rem; }
        .w-full { width: 100%; }
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
                <h1 class="title">接口管理</h1>
            </div>
        </header>

        <main class="main-content">
            <?php if (isset($_GET['success'])): ?>
            <div class="message message-success">操作成功！</div>
            <?php endif; ?>
            
            <?php if (isset($_GET['error'])): ?>
            <div class="message message-error"><?php echo htmlspecialchars($_GET['error']); ?></div>
            <?php endif; ?>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo count($free_interfaces); ?></div>
                    <div class="stat-label">免费接口总数</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo count($user_interfaces); ?></div>
                    <div class="stat-label">用户自定义接口</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo getActiveFreeCount($db); ?></div>
                    <div class="stat-label">活跃免费接口</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value price">¥<?php echo number_format($current_price, 2); ?></div>
                    <div class="stat-label">当前防红接口价格</div>
                </div>
            </div>
            
            <section class="card">
                <h2 class="card-title">价格管理</h2>
                <div class="price-display">
                    <div class="price-value">¥<?php echo number_format($current_price, 2); ?></div>
                    <div class="price-label">当前价格</div>
                </div>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="update_global_price">
                    <div class="form-group">
                        <label for="unit_price" class="form-label">设置接口单价</label>
                        <input type="number" id="unit_price" name="unit_price" class="input" step="0.01" min="0.01" max="999.99" value="<?php echo number_format($current_price, 2); ?>" required>
                        <p class="form-hint">注意：新价格将在更新后立即生效，已售出的接口价格不受影响</p>
                    </div>
                    <button type="submit" class="btn btn-primary mt-4">更新价格</button>
                </form>
            </section>
            
            <section class="card">
                <h2 class="card-title">添加免费接口</h2>
                <div class="tab-bar">
                    <div class="tab-item active" data-tab="free-single" onclick="switchTab(this, 'free')">单个添加</div>
                    <div class="tab-item" data-tab="free-batch" onclick="switchTab(this, 'free')">批量添加</div>
                    <div class="tab-item" data-tab="free-import" onclick="switchTab(this, 'free')">导入域名</div>
                </div>

                <div class="tab-content active" id="free-single">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="add_free_interface">
                        <div class="form-group mb-4">
                            <label for="name" class="form-label">接口名称</label>
                            <input type="text" id="name" name="name" class="input" required placeholder="">
                        </div>
                        <div class="form-group mb-4">
                            <label for="api_url" class="form-label">接口URL</label>
                            <input type="url" id="api_url" name="api_url" class="input" required placeholder="url">
                        </div>
                        <button type="submit" class="btn btn-primary">添加免费接口</button>
                    </form>
                </div>

                <div class="tab-content" id="free-batch">
                    <div class="batch-hint">
                        每行一个接口，格式：接口名称|接口URL
                    </div>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="batch_add_free_interface">
                        <div class="form-group mb-4">
                            <label for="batch_free_data" class="form-label">批量接口数据</label>
                            <textarea id="batch_free_data" name="batch_data" class="textarea" style="min-height: 10rem;" required placeholder="一行一个"></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">批量添加</button>
                    </form>
                </div>

                <div class="tab-content" id="free-import">
                    <div class="batch-hint">
                        输入接口URL，每行一个，名称自动按日期编号自动生成
                    </div>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="import_free_interface">
                        <div class="form-group mb-4">
                            <label for="import_free_urls" class="form-label">接口接口数据</label>
                            <textarea id="import_free_urls" name="import_urls" class="textarea" style="min-height: 10rem;" required placeholder="一行一个"></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">导入域名</button>
                    </form>
                </div>
            </section>
            
            <section class="card">
                <h2 class="card-title">添加付费接口</h2>
                <div class="tab-bar">
                    <div class="tab-item active" data-tab="paid-single" onclick="switchTab(this, 'paid')">
                        单个添加</div>
                    <div class="tab-item" data-tab="paid-batch" onclick="switchTab(this, 'paid')">批量添加</div>
                    <div class="tab-item" data-tab="paid-import" onclick="switchTab(this, 'paid')">导入域名</div>
                </div>

                <div class="tab-content active" id="paid-single">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="add_paid_interface">
                        <div class="form-group mb-4">
                            <label for="paid_domain_name" class="form-label">接口名称</label>
                            <input type="text" id="paid_domain_name" name="domain_name" class="input" required placeholder="">
                        </div>
                        <div class="form-group mb-4">
                            <label for="paid_api_url" class="form-label">接口URL</label>
                            <input type="url" id="paid_api_url" name="api_url" class="input" required placeholder="">
                        </div>
                        <div class="form-group mb-4">
                            <label for="price" class="form-label">价格</label>
                            <input type="number" id="price" name="price" class="input" step="0.01" min="0.01" max="999.99" value="5.20" required>
                        </div>
                        <button type="submit" class="btn btn-success">添加付费接口</button>
                    </form>
                </div>

                <div class="tab-content" id="paid-batch">
                    <div class="batch-hint">
                        每行一个接口，格式：接口名称|接口URL|价格
                    </div>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="batch_add_paid_interface">
                        <div class="form-group mb-4">
                            <label for="batch_paid_data" class="form-label">批量接口数据</label>
                            <textarea id="batch_paid_data" name="batch_data" class="textarea" style="min-height: 10rem;" required placeholder="一行一个"></textarea>
                        </div>
                        <button type="submit" class="btn btn-success">批量添加</button>
                    </form>
                </div>

                <div class="tab-content" id="paid-import">
                    <div class="batch-hint">
                        输入接口URL，每行一个，名称自动按日期编号自动生成，统一设置价格
                    </div>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="import_paid_interface">
                        <div class="form-group mb-4">
                            <label for="import_paid_urls" class="form-label">接口URL列表</label>
                            <textarea id="import_paid_urls" name="import_urls" class="textarea" style="min-height: 10rem;" required placeholder="一行一个"></textarea>
                        </div>
                        <div class="form-group mb-4">
                            <label for="import_paid_price" class="form-label">统一价格 (元)</label>
                            <input type="number" id="import_paid_price" name="price" class="input" step="0.01" min="0.01" max="999.99" value="5.20" required>
                        </div>
                        <button type="submit" class="btn btn-success">导入域名</button>
                    </form>
                </div>
            </section>
            
            <section class="card">
                <h2 class="card-title">免费接口列表</h2>
                
                <?php if (empty($free_interfaces)): ?>
                <div class="empty-state">暂无免费接口</div>
                <?php else: ?>
                <div class="list-toolbar">
                    <label class="select-all-label">
                        <input type="checkbox" id="free-select-all" onchange="toggleSelectAll('free', this.checked)">
                        全选
                    </label>
                    <span class="selected-count" id="free-selected-count"></span>
                    <button type="button" class="btn btn-danger btn-sm" onclick="batchDelete('free')" id="free-batch-delete-btn" disabled>批量删除</button>
                    <button type="button" class="btn btn-outline btn-sm" onclick="deleteAll('free')">全部删除</button>
                </div>
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th style="width:40px;"></th>
                                <th>ID</th>
                                <th>接口名称</th>
                                <th>状态</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($free_interfaces as $interface): ?>
                            <tr>
                                <td><input type="checkbox" class="row-checkbox free-checkbox" data-id="<?php echo $interface['id']; ?>" onchange="updateBatchBtn('free')"></td>
                                <td><?php echo htmlspecialchars($interface['id']); ?></td>
                                <td>
                                    <div>
                                        <strong><?php echo htmlspecialchars($interface['name']); ?></strong>
                                        <?php if (!empty($interface['remark'])): ?>
                                        <div style="font-size: 0.75rem; color: var(--gray-500); margin-top: 0.25rem;">
                                            <?php echo htmlspecialchars($interface['remark']); ?>
                                        </div>
                                        <?php endif; ?>
                                        <div style="font-size: 0.75rem; color: var(--gray-400); margin-top: 0.25rem;">
                                            <?php echo htmlspecialchars($interface['api_url']); ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="status status-<?php echo $interface['status']; ?>">
                                        <?php echo $interface['status'] === 'active' ? '活跃' : '停用'; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="actions">
                                        <form method="POST" action="" style="display: inline;">
                                            <input type="hidden" name="action" value="delete_free_interface">
                                            <input type="hidden" name="id" value="<?php echo $interface['id']; ?>">
                                            <button type="submit" class="btn btn-danger btn-icon" onclick="return confirm('确定要删除这个免费接口吗？')">删除</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </section>
            
            <section class="card">
                <h2 class="card-title">付费接口列表</h2>
                
                <?php if (empty($paid_interfaces)): ?>
                <div class="empty-state">暂无付费接口</div>
                <?php else: ?>
                <div class="list-toolbar">
                    <label class="select-all-label">
                        <input type="checkbox" id="paid-select-all" onchange="toggleSelectAll('paid', this.checked)">
                        全选
                    </label>
                    <span class="selected-count" id="paid-selected-count"></span>
                    <button type="button" class="btn btn-danger btn-sm" onclick="batchDelete('paid')" id="paid-batch-delete-btn" disabled>批量删除</button>
                    <button type="button" class="btn btn-outline btn-sm" onclick="deleteAll('paid')">全部删除</button>
                </div>
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th style="width:40px;"></th>
                                <th>ID</th>
                                <th>域名名称</th>
                                <th>价格</th>
                                <th>状态</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($paid_interfaces as $interface): ?>
                            <tr>
                                <td><input type="checkbox" class="row-checkbox paid-checkbox" data-id="<?php echo $interface['id']; ?>" onchange="updateBatchBtn('paid')"></td>
                                <td><?php echo htmlspecialchars($interface['id']); ?></td>
                                <td>
                                    <div>
                                        <strong><?php echo htmlspecialchars($interface['domain_name']); ?></strong>
                                        <div style="font-size: 0.75rem; color: var(--gray-400); margin-top: 0.25rem;">
                                            <?php echo htmlspecialchars($interface['api_url']); ?>
                                        </div>
                                        <?php if ($interface['sold_to']): ?>
                                        <div style="font-size: 0.75rem; color: var(--gray-500); margin-top: 0.25rem;">
                                            购买用户: <?php echo htmlspecialchars($interface['sold_to']); ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <strong style="color: var(--red-500);">¥<?php echo number_format($interface['price'], 2); ?></strong>
                                </td>
                                <td>
                                    <?php if ($interface['is_sold'] == 1): ?>
                                    <span class="status status-sold">已售出</span>
                                    <?php else: ?>
                                    <span class="status status-active">未售出</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="actions">
                                        <?php if ($interface['is_sold'] == 0): ?>
                                        <button type="button" class="btn btn-primary btn-icon" onclick="editPaidInterface(<?php echo $interface['id']; ?>, '<?php echo htmlspecialchars(addslashes($interface['domain_name'])); ?>', '<?php echo htmlspecialchars(addslashes($interface['api_url'])); ?>', <?php echo $interface['price']; ?>)">编辑</button>
                                        <?php endif; ?>
                                        <form method="POST" action="" style="display: inline;">
                                            <input type="hidden" name="action" value="delete_paid_interface">
                                            <input type="hidden" name="id" value="<?php echo $interface['id']; ?>">
                                            <button type="submit" class="btn btn-danger btn-icon" onclick="return confirm('确定要删除这个付费接口吗？')">删除</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </section>
            
            <section class="card">
                <h2 class="card-title">用户自定义接口列表</h2>
                
                <?php if (empty($user_interfaces)): ?>
                <div class="empty-state">暂无用户自定义接口</div>
                <?php else: ?>
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>用户</th>
                                <th>接口名称</th>
                                <th>状态</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($user_interfaces as $interface): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($interface['id']); ?></td>
                                <td>
                                    <div>
                                        <strong><?php echo htmlspecialchars($interface['username']); ?></strong>
                                        <div style="font-size: 0.75rem; color: var(--gray-500); margin-top: 0.25rem;">
                                            ID: <?php echo htmlspecialchars($interface['user_id']); ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <strong><?php echo htmlspecialchars($interface['remark']); ?></strong>
                                        <div style="font-size: 0.75rem; color: var(--gray-400); margin-top: 0.25rem;">
                                            <?php echo htmlspecialchars($interface['api_url']); ?>
                                        </div>
                                        <div style="font-size: 0.75rem; color: var(--gray-500); margin-top: 0.25rem;">
                                            编码: <?php echo htmlspecialchars($interface['encoding']); ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="status status-<?php echo $interface['status']; ?>">
                                        <?php echo $interface['status'] === 'active' ? '正常' : '已删除'; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="actions">
                                        <form method="POST" action="" style="display: inline;">
                                            <input type="hidden" name="action" value="delete_user_interface">
                                            <input type="hidden" name="id" value="<?php echo $interface['id']; ?>">
                                            <button type="submit" class="btn btn-danger btn-icon" onclick="return confirm('确定要删除这个用户自定义接口吗？')">删除</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </section>
        </main>
    </div>
    
    <div id="editModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
        <div class="card" style="width: 90%; max-width: 500px; margin: 0 auto;">
            <h2 class="card-title">编辑付费接口</h2>
            <form method="POST" action="" id="editForm">
                <input type="hidden" name="action" value="edit_paid_interface">
                <input type="hidden" name="id" id="edit_id">
                <div class="form-group mb-4">
                    <label for="edit_domain_name" class="form-label">域名名称 *</label>
                    <input type="text" id="edit_domain_name" name="domain_name" class="input" required>
                </div>
                <div class="form-group mb-4">
                    <label for="edit_api_url" class="form-label">接口URL *</label>
                    <input type="url" id="edit_api_url" name="api_url" class="input" required>
                </div>
                <div class="form-group mb-4">
                    <label for="edit_price" class="form-label">价格 (元) *</label>
                    <input type="number" id="edit_price" name="price" class="input" step="0.01" min="0.01" max="999.99" required>
                </div>
                <div style="display: flex; gap: 1rem;">
                    <button type="button" class="btn" onclick="closeEditModal()" style="flex: 1; background-color: var(--gray-200); color: var(--gray-800);">取消</button>
                    <button type="submit" class="btn btn-primary" style="flex: 1;">保存</button>
                </div>
            </form>
        </div>
    </div>

    <form method="POST" action="" id="batchDeleteForm" style="display:none;">
        <input type="hidden" name="action" id="batchDeleteAction" value="">
        <input type="hidden" name="ids" id="batchDeleteIds" value="">
    </form>

    <form method="POST" action="" id="deleteAllForm" style="display:none;">
        <input type="hidden" name="action" id="deleteAllAction" value="">
    </form>
    
    <script>
    function switchTab(el, group) {
        var tabName = el.getAttribute('data-tab');
        el.parentElement.querySelectorAll('.tab-item').forEach(function(t) { t.classList.remove('active'); });
        el.classList.add('active');
        if (group === 'free') {
            document.getElementById('free-single').classList.remove('active');
            document.getElementById('free-batch').classList.remove('active');
            document.getElementById('free-import').classList.remove('active');
            document.getElementById(tabName).classList.add('active');
        } else {
            document.getElementById('paid-single').classList.remove('active');
            document.getElementById('paid-batch').classList.remove('active');
            document.getElementById('paid-import').classList.remove('active');
            document.getElementById(tabName).classList.add('active');
        }
    }

    function getSelectedIds(type) {
        var checkboxes = document.querySelectorAll('.' + type + '-checkbox:checked');
        var ids = [];
        checkboxes.forEach(function(cb) { ids.push(cb.getAttribute('data-id')); });
        return ids;
    }

    function updateBatchBtn(type) {
        var ids = getSelectedIds(type);
        var btn = document.getElementById(type + '-batch-delete-btn');
        var countEl = document.getElementById(type + '-selected-count');
        btn.disabled = ids.length === 0;
        countEl.textContent = ids.length > 0 ? '已选 ' + ids.length + ' 项' : '';
        var allCbs = document.querySelectorAll('.' + type + '-checkbox');
        var selectAll = document.getElementById(type + '-select-all');
        selectAll.checked = allCbs.length > 0 && ids.length === allCbs.length;
    }

    function toggleSelectAll(type, checked) {
        document.querySelectorAll('.' + type + '-checkbox').forEach(function(cb) { cb.checked = checked; });
        updateBatchBtn(type);
    }

    function batchDelete(type) {
        var ids = getSelectedIds(type);
        if (ids.length === 0) return;
        if (!confirm('确定要删除选中的 ' + ids.length + ' 个接口吗？')) return;
        var form = document.getElementById('batchDeleteForm');
        document.getElementById('batchDeleteAction').value = 'batch_delete_' + type + '_interface';
        document.getElementById('batchDeleteIds').value = ids.join(',');
        form.submit();
    }

    function deleteAll(type) {
        var label = type === 'free' ? '免费' : '付费';
        if (!confirm('确定要删除全部' + label + '接口吗？此操作不可恢复！')) return;
        var form = document.getElementById('deleteAllForm');
        document.getElementById('deleteAllAction').value = 'delete_all_' + type + '_interface';
        form.submit();
    }

    function editPaidInterface(id, domain_name, api_url, price) {
        document.getElementById('edit_id').value = id;
        document.getElementById('edit_domain_name').value = domain_name;
        document.getElementById('edit_api_url').value = api_url;
        document.getElementById('edit_price').value = price;
        document.getElementById('editModal').style.display = 'flex';
    }
    
    function closeEditModal() {
        document.getElementById('editModal').style.display = 'none';
    }
    
    document.getElementById('editModal').addEventListener('click', function(e) {
        if (e.target === this) closeEditModal();
    });
    
    document.querySelectorAll('form').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            var btn = this.querySelector('button[type="submit"]');
            if (btn) {
                var originalText = btn.textContent;
                btn.textContent = '保存中...';
                btn.disabled = true;
                setTimeout(function() { btn.textContent = originalText; btn.disabled = false; }, 2000);
            }
        });
    });
    </script>
</body>
</html>

<?php
function getFreeInterfaces($db) {
    $sql = "SELECT * FROM freeantired ORDER BY id DESC";
    $result = $db->query($sql);
    $interfaces = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $interfaces[] = $row;
        }
    }
    return $interfaces;
}

function getPaidInterfaces($db) {
    $sql = "SELECT * FROM anti_red_links ORDER BY id DESC";
    $result = $db->query($sql);
    $interfaces = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $interfaces[] = $row;
        }
    }
    return $interfaces;
}

function getActiveFreeCount($db) {
    $sql = "SELECT COUNT(*) as count FROM freeantired WHERE status = 'active'";
    $result = $db->query($sql);
    if ($result && $row = $result->fetch_assoc()) {
        return $row['count'];
    }
    return 0;
}

function getUserCustomInterfaces($db) {
    $sql = "SELECT ua.*, u.username 
            FROM userantired ua 
            LEFT JOIN users u ON ua.user_id = u.id 
            WHERE ua.status = 'active' 
            ORDER BY ua.created_at DESC";
    $result = $db->query($sql);
    $interfaces = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $interfaces[] = $row;
        }
    }
    return $interfaces;
}

function getCurrentPrice($db) {
    $sql = "SELECT unit_price FROM anti_red_pricing WHERE item_name = '防红接口' AND is_active = 1 ORDER BY updated_at DESC LIMIT 1";
    $result = $db->query($sql);
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return floatval($row['unit_price']);
    }
    return 5.20;
}

function handleAddFreeInterface($db) {
    $name = trim($_POST['name'] ?? '');
    $api_url = trim($_POST['api_url'] ?? '');
    
    if (empty($name) || empty($api_url)) {
        header('Location: ?error=接口名称和URL不能为空');
        exit;
    }
    
    if (!filter_var($api_url, FILTER_VALIDATE_URL)) {
        header('Location: ?error=无效的URL格式');
        exit;
    }
    
    $sql = "INSERT INTO freeantired (name, api_url, status, created_at) VALUES (?, ?, 'active', NOW())";
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        header('Location: ?error=数据库错误');
        exit;
    }
    
    $stmt->bind_param("ss", $name, $api_url);
    if ($stmt->execute()) {
        header('Location: ?success=1');
    } else {
        header('Location: ?error=添加失败');
    }
    exit;
}

function handleBatchAddFreeInterface($db) {
    $batch_data = trim($_POST['batch_data'] ?? '');
    if (empty($batch_data)) {
        header('Location: ?error=批量数据不能为空');
        exit;
    }
    
    $lines = explode("\n", $batch_data);
    $success = 0;
    $fail = 0;
    
    $sql = "INSERT INTO freeantired (name, api_url, status, created_at) VALUES (?, ?, 'active', NOW())";
    $stmt = $db->prepare($sql);
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;
        
        $parts = explode('|', $line);
        $name = trim($parts[0] ?? '');
        $api_url = trim($parts[1] ?? '');
        
        if (empty($name) || empty($api_url)) {
            $fail++;
            continue;
        }
        
        if (!filter_var($api_url, FILTER_VALIDATE_URL)) {
            $fail++;
            continue;
        }
        
        $stmt->bind_param("ss", $name, $api_url);
        if ($stmt->execute()) {
            $success++;
        } else {
            $fail++;
        }
    }
    
    if ($success > 0) {
        header('Location: ?success=1');
    } else {
        header('Location: ?error=批量添加全部失败');
    }
    exit;
}

function handleImportFreeInterface($db) {
    $import_urls = trim($_POST['import_urls'] ?? '');
    if (empty($import_urls)) {
        header('Location: ?error=URL列表不能为空');
        exit;
    }
    
    $lines = explode("\n", $import_urls);
    $datePrefix = date('Ymd');
    
    $maxSql = "SELECT MAX(CAST(SUBSTRING_INDEX(name, '-', -1) AS UNSIGNED)) as max_num FROM freeantired WHERE name LIKE ?";
    $maxStmt = $db->prepare($maxSql);
    $likePattern = $datePrefix . '-%';
    $startNum = 1;
    if ($maxStmt) {
        $maxStmt->bind_param("s", $likePattern);
        $maxStmt->execute();
        $maxResult = $maxStmt->get_result();
        if ($maxRow = $maxResult->fetch_assoc()) {
            if ($maxRow['max_num'] > 0) {
                $startNum = intval($maxRow['max_num']) + 1;
            }
        }
    }
    
    $sql = "INSERT INTO freeantired (name, api_url, status, created_at) VALUES (?, ?, 'active', NOW())";
    $stmt = $db->prepare($sql);
    
    $success = 0;
    $fail = 0;
    $seq = $startNum;
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;
        
        $api_url = $line;
        if (!filter_var($api_url, FILTER_VALIDATE_URL)) {
            $fail++;
            continue;
        }
        
        $name = $datePrefix . '-' . $seq;
        $stmt->bind_param("ss", $name, $api_url);
        if ($stmt->execute()) {
            $success++;
            $seq++;
        } else {
            $fail++;
        }
    }
    
    if ($success > 0) {
        header('Location: ?success=1');
    } else {
        header('Location: ?error=导入全部失败');
    }
    exit;
}

function handleDeleteFreeInterface($db) {
    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) {
        header('Location: ?error=无效的接口ID');
        exit;
    }
    
    $sql = "DELETE FROM freeantired WHERE id = ?";
    $stmt = $db->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            header('Location: ?success=1');
        } else {
            header('Location: ?error=删除失败');
        }
    } else {
        header('Location: ?error=数据库错误');
    }
    exit;
}

function handleBatchDeleteFreeInterface($db) {
    $ids = $_POST['ids'] ?? '';
    if (empty($ids)) {
        header('Location: ?error=未选择要删除的接口');
        exit;
    }
    
    $idArr = explode(',', $ids);
    $idArr = array_map('intval', $idArr);
    $idArr = array_filter($idArr, function($id) { return $id > 0; });
    
    if (empty($idArr)) {
        header('Location: ?error=无效的接口ID');
        exit;
    }
    
    $placeholders = implode(',', array_fill(0, count($idArr), '?'));
    $types = str_repeat('i', count($idArr));
    $sql = "DELETE FROM freeantired WHERE id IN ($placeholders)";
    $stmt = $db->prepare($sql);
    
    if ($stmt) {
        $stmt->bind_param($types, ...$idArr);
        if ($stmt->execute()) {
            header('Location: ?success=1');
        } else {
            header('Location: ?error=批量删除失败');
        }
    } else {
        header('Location: ?error=数据库错误');
    }
    exit;
}

function handleDeleteAllFreeInterface($db) {
    $sql = "DELETE FROM freeantired";
    if ($db->query($sql)) {
        header('Location: ?success=1');
    } else {
        header('Location: ?error=删除全部失败');
    }
    exit;
}

function handleDeleteUserInterface($db) {
    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) {
        header('Location: ?error=无效的接口ID');
        exit;
    }
    
    $sql = "UPDATE userantired SET status = 'deleted' WHERE id = ?";
    $stmt = $db->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            header('Location: ?success=1');
        } else {
            header('Location: ?error=删除失败');
        }
    } else {
        header('Location: ?error=数据库错误');
    }
    exit;
}

function handleUpdateGlobalPrice($db) {
    $unit_price = floatval($_POST['unit_price'] ?? 0);
    if ($unit_price <= 0) {
        header('Location: ?error=价格必须大于0');
        exit;
    }
    
    $sql = "INSERT INTO anti_red_pricing (item_name, unit_price, is_active) 
            VALUES ('防红接口', ?, 1) 
            ON DUPLICATE KEY UPDATE unit_price = ?, updated_at = NOW()";
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        header('Location: ?error=数据库错误: ' . $db->error);
        exit;
    }
    
    $stmt->bind_param("dd", $unit_price, $unit_price);
    if ($stmt->execute()) {
        header('Location: ?success=1&message=价格更新成功');
    } else {
        header('Location: ?error=更新失败: ' . $stmt->error);
    }
    exit;
}

function handleAddPaidInterface($db) {
    $domain_name = trim($_POST['domain_name'] ?? '');
    $api_url = trim($_POST['api_url'] ?? '');
    $price = floatval($_POST['price'] ?? 5.20);
    
    if (empty($domain_name) || empty($api_url)) {
        header('Location: ?error=域名名称和URL不能为空');
        exit;
    }
    
    if (!filter_var($api_url, FILTER_VALIDATE_URL)) {
        header('Location: ?error=无效的URL格式');
        exit;
    }
    
    if ($price <= 0) {
        header('Location: ?error=价格必须大于0');
        exit;
    }
    
    $sql = "INSERT INTO anti_red_links (domain_name, api_url, price, is_sold, created_at) VALUES (?, ?, ?, 0, NOW())";
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        header('Location: ?error=数据库错误');
        exit;
    }
    
    $stmt->bind_param("ssd", $domain_name, $api_url, $price);
    if ($stmt->execute()) {
        header('Location: ?success=1');
    } else {
        header('Location: ?error=添加失败');
    }
    exit;
}

function handleBatchAddPaidInterface($db) {
    $batch_data = trim($_POST['batch_data'] ?? '');
    if (empty($batch_data)) {
        header('Location: ?error=批量数据不能为空');
        exit;
    }
    
    $lines = explode("\n", $batch_data);
    $success = 0;
    $fail = 0;
    
    $sql = "INSERT INTO anti_red_links (domain_name, api_url, price, is_sold, created_at) VALUES (?, ?, ?, 0, NOW())";
    $stmt = $db->prepare($sql);
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;
        
        $parts = explode('|', $line);
        $domain_name = trim($parts[0] ?? '');
        $api_url = trim($parts[1] ?? '');
        $price = floatval(trim($parts[2] ?? '5.20'));
        
        if (empty($domain_name) || empty($api_url)) {
            $fail++;
            continue;
        }
        
        if (!filter_var($api_url, FILTER_VALIDATE_URL)) {
            $fail++;
            continue;
        }
        
        if ($price <= 0) $price = 5.20;
        
        $stmt->bind_param("ssd", $domain_name, $api_url, $price);
        if ($stmt->execute()) {
            $success++;
        } else {
            $fail++;
        }
    }
    
    if ($success > 0) {
        header('Location: ?success=1');
    } else {
        header('Location: ?error=批量添加全部失败');
    }
    exit;
}

function handleImportPaidInterface($db) {
    $import_urls = trim($_POST['import_urls'] ?? '');
    $price = floatval($_POST['price'] ?? 5.20);
    
    if (empty($import_urls)) {
        header('Location: ?error=URL列表不能为空');
        exit;
    }
    
    if ($price <= 0) {
        header('Location: ?error=价格必须大于0');
        exit;
    }
    
    $lines = explode("\n", $import_urls);
    $datePrefix = date('Ymd');
    
    $maxSql = "SELECT MAX(CAST(SUBSTRING_INDEX(SUBSTRING(domain_name, 4), '-', -1) AS UNSIGNED)) as max_num FROM anti_red_links WHERE domain_name LIKE ?";
    $maxStmt = $db->prepare($maxSql);
    $likePattern = '私人' . $datePrefix . '-%';
    $startNum = 1;
    if ($maxStmt) {
        $maxStmt->bind_param("s", $likePattern);
        $maxStmt->execute();
        $maxResult = $maxStmt->get_result();
        if ($maxRow = $maxResult->fetch_assoc()) {
            if ($maxRow['max_num'] > 0) {
                $startNum = intval($maxRow['max_num']) + 1;
            }
        }
    }
    
    $sql = "INSERT INTO anti_red_links (domain_name, api_url, price, is_sold, created_at) VALUES (?, ?, ?, 0, NOW())";
    $stmt = $db->prepare($sql);
    
    $success = 0;
    $fail = 0;
    $seq = $startNum;
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;
        
        $api_url = $line;
        if (!filter_var($api_url, FILTER_VALIDATE_URL)) {
            $fail++;
            continue;
        }
        
        $domain_name = '私人' . $datePrefix . '-' . $seq;
        $stmt->bind_param("ssd", $domain_name, $api_url, $price);
        if ($stmt->execute()) {
            $success++;
            $seq++;
        } else {
            $fail++;
        }
    }
    
    if ($success > 0) {
        header('Location: ?success=1');
    } else {
        header('Location: ?error=导入全部失败');
    }
    exit;
}

function handleDeletePaidInterface($db) {
    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) {
        header('Location: ?error=无效的接口ID');
        exit;
    }
    
    $check_sql = "SELECT is_sold FROM anti_red_links WHERE id = ?";
    $check_stmt = $db->prepare($check_sql);
    if ($check_stmt) {
        $check_stmt->bind_param("i", $id);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        $row = $result->fetch_assoc();
        
        if ($row && $row['is_sold'] == 1) {
            header('Location: ?error=该接口已售出，不能删除');
            exit;
        }
    }
    
    $sql = "DELETE FROM anti_red_links WHERE id = ?";
    $stmt = $db->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            header('Location: ?success=1');
        } else {
            header('Location: ?error=删除失败');
        }
    } else {
        header('Location: ?error=数据库错误');
    }
    exit;
}

function handleBatchDeletePaidInterface($db) {
    $ids = $_POST['ids'] ?? '';
    if (empty($ids)) {
        header('Location: ?error=未选择要删除的接口');
        exit;
    }
    
    $idArr = explode(',', $ids);
    $idArr = array_map('intval', $idArr);
    $idArr = array_filter($idArr, function($id) { return $id > 0; });
    
    if (empty($idArr)) {
        header('Location: ?error=无效的接口ID');
        exit;
    }
    
    $placeholders = implode(',', array_fill(0, count($idArr), '?'));
    $types = str_repeat('i', count($idArr));
    $sql = "DELETE FROM anti_red_links WHERE id IN ($placeholders) AND is_sold = 0";
    $stmt = $db->prepare($sql);
    
    if ($stmt) {
        $stmt->bind_param($types, ...$idArr);
        if ($stmt->execute()) {
            header('Location: ?success=1');
        } else {
            header('Location: ?error=批量删除失败');
        }
    } else {
        header('Location: ?error=数据库错误');
    }
    exit;
}

function handleDeleteAllPaidInterface($db) {
    $sql = "DELETE FROM anti_red_links WHERE is_sold = 0";
    if ($db->query($sql)) {
        header('Location: ?success=1');
    } else {
        header('Location: ?error=删除全部失败');
    }
    exit;
}

function handleEditPaidInterface($db) {
    $id = intval($_POST['id'] ?? 0);
    $domain_name = trim($_POST['domain_name'] ?? '');
    $api_url = trim($_POST['api_url'] ?? '');
    $price = floatval($_POST['price'] ?? 5.20);
    
    if ($id <= 0) {
        header('Location: ?error=无效的接口ID');
        exit;
    }
    
    if (empty($domain_name) || empty($api_url)) {
        header('Location: ?error=域名名称和URL不能为空');
        exit;
    }
    
    if (!filter_var($api_url, FILTER_VALIDATE_URL)) {
        header('Location: ?error=无效的URL格式');
        exit;
    }
    
    if ($price <= 0) {
        header('Location: ?error=价格必须大于0');
        exit;
    }
    
    $check_sql = "SELECT is_sold FROM anti_red_links WHERE id = ?";
    $check_stmt = $db->prepare($check_sql);
    if ($check_stmt) {
        $check_stmt->bind_param("i", $id);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        $row = $result->fetch_assoc();
        
        if ($row && $row['is_sold'] == 1) {
            header('Location: ?error=该接口已售出，不能编辑');
            exit;
        }
    }
    
    $sql = "UPDATE anti_red_links SET domain_name = ?, api_url = ?, price = ? WHERE id = ?";
    $stmt = $db->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("ssdi", $domain_name, $api_url, $price, $id);
        if ($stmt->execute()) {
            header('Location: ?success=1');
        } else {
            header('Location: ?error=编辑失败');
        }
    } else {
        header('Location: ?error=数据库错误');
    }
    exit;
}
?>
